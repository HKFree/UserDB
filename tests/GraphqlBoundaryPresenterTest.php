<?php

declare(strict_types=1);

namespace Test;

use App\MapiModule\Data\CteciSpojeni;
use App\MapiModule\Odpoved;
use App\Model\Authenticator;
use GraphQL\Type\Introspection;
use Nette\Application\Application;
use Nette\Database\Table\ActiveRow;
use Nette\Http;
use Nette\Utils\Json;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

/**
 * Access boundary of the GraphQL endpoint /userdb/mapi/graphql (issue #264): allowed roles (H1), identity (H4),
 * read only (H5) and refusal before execution (H9), plus the request rules of phase 1.
 */
final class GraphqlBoundaryPresenterTest extends DatabaseTestCase
{
    private const HEADERS = ['Content-Type' => 'application/json', 'X-UserDB-Request' => '1'];
    /** Kept apart from the application's list on purpose: widening the list in the code must fail here. */
    private const ALLOWED_ROLES = ['VV', 'TECH', 'KONTROLA', 'SO', 'ZSO', 'EXTSUPPORT'];
    private const AREA_ROLES = ['SO', 'ZSO'];
    private const JA = 'query Ja { ja { uid nick role { typ oblast { id jmeno ap { id jmeno } } } oblasti { id jmeno ap { id jmeno } } } }';
    private const FAILURE = 'Simulated outage in SELECT heslo FROM Uzivatel';

    private PermissionScenario $scenario;
    private RecordingMapiZaznam $log;

    protected function setUp(): void {
        parent::setUp();
        $this->scenario = new PermissionScenario($this->fixtures, $this->database, $this->container);
        $this->log = $this->container->getByType(RecordingMapiZaznam::class);
    }

    public function testEveryAllowedRoleReadsItself(): void {
        $area = $this->scenario->area;
        $oblast = ['id' => $area->id, 'jmeno' => $area->jmeno, 'ap' => [['id' => $this->scenario->ap->id, 'jmeno' => $this->scenario->ap->jmeno]]];
        foreach (self::ALLOWED_ROLES as $type) {
            $bound = in_array($type, self::AREA_ROLES, true);
            $member = $this->memberWithRole($type, $bound ? $area->id : null);
            $result = $this->request($member->id, self::query(self::JA));
            Assert::same(200, $result->status, $type);
            Assert::same(['data' => ['ja' => [
                'uid' => $member->id,
                'nick' => $member->nick,
                'role' => [['typ' => $type, 'oblast' => $bound ? $oblast : null]],
                'oblasti' => $bound ? [$oblast] : [],
            ]]], $result->json, $type);
            Assert::same([[$member->id, 'Ja', 'OK']], $this->takeLog(), $type);
        }
    }

    public function testAnswerHasExactlyTheRequestedKeys(): void {
        $member = $this->memberWithRole('SO', $this->scenario->area->id);
        $result = $this->request($member->id, self::query('query Kdo { ja { nick oblasti { jmeno } } }'));
        Assert::same(['data' => ['ja' => ['nick' => $member->nick, 'oblasti' => [['jmeno' => $this->scenario->area->jmeno]]]]], $result->json);
    }

    public function testRolesOutsideTheListAreForbidden(): void {
        $this->fixtures->insert('TypSpravceOblasti', ['text' => 'NOVAROLE']);
        $types = array_values($this->database->table('TypSpravceOblasti')->fetchPairs('id', 'text'));
        $denied = array_values(array_diff($types, self::ALLOWED_ROLES));
        // The lookup must hold every allowed role and something else, otherwise this test checks nothing.
        Assert::same([], array_diff(self::ALLOWED_ROLES, $types));
        Assert::contains('NOVAROLE', $denied);
        Assert::contains('DRUŽSTEVNÍK', $denied);
        foreach ($denied as $type) {
            foreach ([null, $this->scenario->area->id] as $areaId) {
                $member = $this->memberWithRole($type, $areaId);
                $result = $this->request($member->id, self::query(self::JA));
                $this->assertRejected($result, 403, 'FORBIDDEN', $type);
                Assert::same([[$member->id, null, 'FORBIDDEN']], $this->takeLog(), $type);
            }
        }
    }

    public function testExpiredAndFutureRolesAreForbidden(): void {
        $expired = $this->memberWithRole('VV', until: '2001-01-01');
        $future = $this->memberWithRole('TECH', from: '2090-01-01');
        $outdatedBesideDisallowed = $this->memberWithRole('DRUŽSTEVNÍK');
        $this->fixtures->role($outdatedBesideDisallowed->id, 'VV', until: '2001-01-01');
        $this->fixtures->role($outdatedBesideDisallowed->id, 'SO', $this->scenario->area->id, from: '2090-01-01');
        foreach ([$expired, $future, $outdatedBesideDisallowed] as $member) {
            $this->assertRejected($this->request($member->id, self::query(self::JA)), 403, 'FORBIDDEN');
            Assert::same([[$member->id, null, 'FORBIDDEN']], $this->takeLog());
        }
    }

    public function testOneAllowedRoleSufficesAndJaListsEveryValidRole(): void {
        $member = $this->memberWithRole('DRUŽSTEVNÍK');
        $this->fixtures->role($member->id, 'KONTROLA');
        $this->fixtures->role($member->id, 'TECH', until: '2001-01-01');
        $result = $this->request($member->id, self::query('query Role { ja { role { typ oblast { id } } } }'));
        Assert::same(200, $result->status);
        $roles = $result->json['data']['ja']['role'];
        sort($roles);
        Assert::same([['typ' => 'DRUŽSTEVNÍK', 'oblast' => null], ['typ' => 'KONTROLA', 'oblast' => null]], $roles);
    }

    public function testAnonymousIsUnauthenticated(): void {
        $this->assertRejected($this->request(null, self::query(self::JA)), 401, 'UNAUTHENTICATED');
        Assert::same([[null, null, 'UNAUTHENTICATED']], $this->takeLog());
    }

    public function testIntrospectionIsClosedToAnonymousAndDisallowedRoles(): void {
        $body = self::query(Introspection::getIntrospectionQuery());
        foreach ([[null, 401, 'UNAUTHENTICATED'], [$this->scenario->actors['cooperative']->id, 403, 'FORBIDDEN']] as [$uid, $status, $code]) {
            $result = $this->request($uid, $body);
            $this->assertRejected($result, $status, $code);
            Assert::notContains('__schema', $result->html);
            Assert::notContains('Query', $result->html);
        }
    }

    public function testStandardIntrospectionPassesTheLimits(): void {
        $uid = $this->scenario->actors['tech']->id;
        $result = $this->request($uid, self::query(Introspection::getIntrospectionQuery()));
        Assert::same(200, $result->status);
        Assert::same(['data'], array_keys($result->json));
        $schema = $result->json['data']['__schema'];
        Assert::same(['name' => 'Query'], $schema['queryType']);
        // H5: there is nothing to write through.
        Assert::null($schema['mutationType']);
        Assert::null($schema['subscriptionType']);
        Assert::same([[$uid, 'IntrospectionQuery', 'OK']], $this->takeLog());
    }

    public function testMethodContentTypeAndCsrfGuards(): void {
        $uid = $this->scenario->actors['vv']->id;
        $body = self::query(self::JA);

        $get = $this->request($uid, $body, method: 'GET');
        $this->assertRejected($get, 405, 'BAD_INPUT');
        Assert::same('POST', $this->headers($get)['Allow'] ?? null);

        foreach ([['X-UserDB-Request' => '1'], ['Content-Type' => 'text/plain'], ['Content-Type' => 'application/x-www-form-urlencoded'], ['Content-Type' => 'application/jsonx']] as $headers) {
            $this->assertRejected($this->request($uid, $body, $headers + ['X-UserDB-Request' => '1']), 415, 'BAD_INPUT', Json::encode($headers));
        }

        $json = ['Content-Type' => 'application/json'];
        foreach ([$json, $json + ['X-UserDB-Request' => '0'], self::HEADERS + ['Origin' => 'https://evil.example'], self::HEADERS + ['Origin' => 'http://localhost.evil.example'], self::HEADERS + ['Origin' => 'https://localhost'], self::HEADERS + ['Origin' => 'null']] as $headers) {
            $this->assertRejected($this->request($uid, $body, $headers), 403, 'FORBIDDEN', Json::encode($headers));
        }
        $this->takeLog();

        foreach ([self::HEADERS, self::HEADERS + ['Origin' => 'http://localhost'], ['Content-Type' => 'application/json; charset=utf-8', 'X-UserDB-Request' => '1']] as $headers) {
            Assert::same(200, $this->request($uid, $body, $headers)->status, Json::encode($headers));
        }
        Assert::same(array_fill(0, 3, [$uid, 'Ja', 'OK']), $this->takeLog());
    }

    /**
     * H9: each request breaks every later rule as well, so the status tells which check ran first.
     */
    public function testChecksRunInTheDocumentedOrder(): void {
        $denied = $this->scenario->actors['cooperative']->id;
        $allowed = $this->scenario->actors['support']->id;
        $json = ['Content-Type' => 'application/json'];
        $cases = [
            'method before content type' => [null, [], 'GET', 405, 'BAD_INPUT'],
            'content type before CSRF' => [null, [], 'POST', 415, 'BAD_INPUT'],
            'CSRF before login' => [null, $json, 'POST', 403, 'FORBIDDEN'],
            'login before role' => [null, self::HEADERS, 'POST', 401, 'UNAUTHENTICATED'],
            'role before request shape' => [$denied, self::HEADERS, 'POST', 403, 'FORBIDDEN'],
            'request shape last' => [$allowed, self::HEADERS, 'POST', 400, 'BAD_INPUT'],
        ];
        foreach ($cases as $name => [$uid, $headers, $method, $status, $code]) {
            $this->assertRejected($this->request($uid, '{"query": 7', $headers, $method), $status, $code, $name);
        }
        Assert::same([
            [null, null, 'BAD_INPUT'],
            [null, null, 'BAD_INPUT'],
            [null, null, 'FORBIDDEN'],
            [null, null, 'UNAUTHENTICATED'],
            [$denied, null, 'FORBIDDEN'],
            [$allowed, null, 'BAD_INPUT'],
        ], $this->takeLog());
    }

    public function testMalformedRequestsAreBadInput(): void {
        $uid = $this->scenario->actors['ownSo']->id;
        $ja = 'ja { uid }';
        $cases = [
            'empty body' => [null, null],
            'invalid JSON' => ['{"query": ', null],
            'JSON scalar' => ['"query Ja { ja { uid } }"', null],
            'batch of operations' => [Json::encode([['query' => "query Ja { $ja }"], ['query' => "query Ja { $ja }"]]), null],
            'missing query' => [Json::encode(['operationName' => 'Ja']), null],
            'query is not text' => [Json::encode(['query' => ['query Ja { ja { uid } }']]), null],
            'variables are a list' => [Json::encode(['query' => "query Ja { $ja }", 'variables' => [1, 2]]), null],
            'operation name is not text' => [Json::encode(['query' => "query Ja { $ja }", 'operationName' => 5]), null],
            'syntax error' => [self::query("query Ja { $ja"), null],
            'two operations' => [self::query("query Prvni { $ja } query Druha { $ja }", 'Prvni'), 'Prvni'],
            'anonymous operation' => [self::query("{ $ja }"), null],
            'operation without a name' => [self::query("query { $ja }"), null],
            'mutation' => [self::query("mutation Zmena { $ja }"), 'Zmena'],
            'subscription' => [self::query("subscription Odber { $ja }"), 'Odber'],
            'another operation name' => [self::query("query Ja { $ja }", 'Jina'), 'Ja'],
            'unknown field' => [self::query('query Ja { ja { heslo } }'), 'Ja'],
            'unknown argument' => [self::query('query Ja { ja(uid: 1) { uid } }'), 'Ja'],
            'missing variable' => [self::query('query Typ($jmeno: String!) { __type(name: $jmeno) { name } }'), 'Typ'],
            'wrong variable type' => [self::query('query Typ($jmeno: String!) { __type(name: $jmeno) { name } }', variables: ['jmeno' => 5]), 'Typ'],
            'fragment only' => [self::query("fragment Kus on Ja { uid }"), null],
        ];
        foreach ($cases as $name => [$body, $operation]) {
            $this->assertRejected($this->request($uid, $body), 400, 'BAD_INPUT', $name);
            Assert::same([[$uid, $operation, 'BAD_INPUT']], $this->takeLog(), $name);
        }
        Assert::same(200, $this->request($uid, self::query('query Typ($jmeno: String!) { __type(name: $jmeno) { name } }', 'Typ', ['jmeno' => 'Ja']))->status);
    }

    public function testDepthLimitIsFifteen(): void {
        $uid = $this->scenario->actors['vv']->id;
        Assert::same(15, $this->container->parameters['mapi']['maxHloubkaDotazu']);
        Assert::same(200, $this->request($uid, self::query(self::nested(15)))->status);
        $this->assertRejected($this->request($uid, self::query(self::nested(16))), 400, 'BAD_INPUT');
        Assert::same([[$uid, 'Hluboky', 'OK'], [$uid, 'Hluboky', 'BAD_INPUT']], $this->takeLog());
    }

    public function testComplexityLimitComesFromTheConfiguration(): void {
        $uid = $this->scenario->actors['vv']->id;
        $limit = $this->container->parameters['mapi']['maxSlozitostDotazu'];
        Assert::true(is_int($limit) && $limit >= 20 && $limit % 2 === 0);
        // Each aliased "ja { uid }" costs two points.
        $aliases = implode(' ', array_map(static fn (int $i): string => "a$i: ja { uid }", range(1, intdiv($limit, 2))));
        Assert::same(200, $this->request($uid, self::query("query Tezky { $aliases }"))->status);
        $this->assertRejected($this->request($uid, self::query("query Tezky { $aliases navic: ja { uid } }")), 400, 'BAD_INPUT');
    }

    public function testFailureWhileResolvingIsInternalWithoutDetails(): void {
        $uid = $this->scenario->actors['ownSo']->id;
        $result = $this->request($uid, self::query(self::JA), services: ['mapiSpojeni' => new CteciSpojeni(new OfflineConnection())]);
        Assert::same(200, $result->status);
        Assert::same(['errors', 'data'], array_keys($result->json));
        Assert::null($result->json['data']);
        Assert::count(1, $result->json['errors']);
        Assert::same(['message', 'extensions', 'locations', 'path'], array_keys($result->json['errors'][0]));
        Assert::same(['code' => 'INTERNAL'], $result->json['errors'][0]['extensions']);
        Assert::same(['ja'], $result->json['errors'][0]['path']);
        $this->assertNoDetails($result->html, 'Database access is forbidden');
        Assert::same([[$uid, 'Ja', 'INTERNAL']], $this->takeLog());
        Assert::count(1, $this->log->vyjimky);
        Assert::same('Database access is forbidden in this test double.', $this->log->vyjimky[0]->getMessage());
    }

    public function testFailureBeforeExecutionIsInternalWithoutDetails(): void {
        $result = $this->request($this->scenario->actors['vv']->id, self::query(self::JA), services: ['authenticator' => $this->failingAuthenticator()]);
        $this->assertRejected($result, 500, 'INTERNAL');
        $this->assertNoDetails($result->html, 'Simulated outage');
        Assert::same([[null, null, 'INTERNAL']], $this->takeLog());
        Assert::count(1, $this->log->vyjimky);
        Assert::same(self::FAILURE, $this->log->vyjimky[0]->getMessage());
    }

    public function testFailingAccessLogWithholdsTheData(): void {
        $this->log->failure = new \RuntimeException('Simulated outage: the log is not writable');
        $result = $this->request($this->scenario->actors['vv']->id, self::query(self::JA));
        $this->assertRejected($result, 500, 'INTERNAL');
        $this->assertNoDetails($result->html, 'Simulated outage');
        Assert::same([], $this->takeLog());
    }

    public function testAccessLogKeepsOnlyGrammaticalOperationNames(): void {
        $uid = $this->scenario->actors['vv']->id;
        $longest = str_repeat('a', 64);
        $cases = [
            [self::query("query $longest { ja { uid } }"), 200, $longest],
            [self::query("query {$longest}b { ja { uid } }"), 200, null],
            [self::query('query Ja { ja { uid }', 'Novák Jan'), 400, null],
            [self::query('query Ja { ja { uid }', "Ja\nuid=1 operace=Podvrh vysledek=OK"), 400, null],
            [self::query('query Ja { ja { uid }', 'Nazev_2'), 400, 'Nazev_2'],
            [self::query('query Ja { ja { uid }', '2Nazev'), 400, null],
        ];
        foreach ($cases as [$body, $status, $operation]) {
            Assert::same($status, $this->request($uid, $body)->status);
            Assert::same([[$uid, $operation, $status === 200 ? 'OK' : 'BAD_INPUT']], $this->takeLog());
        }
    }

    /**
     * H9 through Application::run() with the web's error presenter: nothing is forwarded to the HTML error page.
     */
    public function testWholeApplicationAnswersInJson(): void {
        $allowed = $this->scenario->actors['tech'];
        $cases = [
            'allowed role' => [$allowed, [], 200, null],
            'anonymous' => [null, [], 401, 'UNAUTHENTICATED'],
            'role outside the list' => [$this->scenario->actors['cooperative'], [], 403, 'FORBIDDEN'],
            'unexpected exception' => [$allowed, ['authenticator' => $this->failingAuthenticator()], 500, 'INTERNAL'],
        ];
        foreach ($cases as $name => [$member, $services, $status, $code]) {
            [$sent, $output] = $this->runApplication($member, self::query('query Ja { ja { uid } }'), $services);
            Assert::same($status, $sent, $name);
            $json = Json::decode($output, forceArrays: true);
            if ($code === null) {
                Assert::same(['data' => ['ja' => ['uid' => $allowed->id]]], $json, $name);
            } else {
                Assert::same([$code], array_column(array_column($json['errors'], 'extensions'), 'code'), $name);
                Assert::false(array_key_exists('data', $json), $name);
            }
            $this->assertNoDetails($output, 'Simulated outage');
        }
    }

    /**
     * @param array<string, object> $services
     * @return array{int, string}
     */
    private function runApplication(?ActiveRow $member, string $body, array $services): array {
        $savedServer = $_SERVER;
        $savedStatus = http_response_code();
        $bufferLevel = ob_get_level();
        try {
            unset($_SERVER['HTTP_UID'], $_SERVER['HTTP_GIVENNAME'], $_SERVER['HTTP_INITIALS']);
            if ($member !== null) {
                $_SERVER['HTTP_UID'] = (string) $member->id;
                $_SERVER['HTTP_GIVENNAME'] = $member->nick;
                $_SERVER['HTTP_INITIALS'] = 'test-only';
            }
            http_response_code(200);
            $request = new Http\Request(
                new Http\UrlScript('http://localhost/userdb/mapi/graphql'),
                headers: self::HEADERS,
                method: 'POST',
                remoteAddress: '127.0.0.1',
                rawBodyCallback: static fn (): string => $body,
            );
            $container = TestEnvironment::container($this->database, $request, $this->container, services: $services);
            $application = $container->getByType(Application::class);
            // The debug test container disables the production error-presenter path by default.
            $application->errorPresenter = $application->error4xxPresenter = 'Error';
            ob_start();
            $application->run();
            $output = (string) ob_get_contents();
            Assert::same(['Mapi:Graphql'], array_map(static fn (\Nette\Application\Request $request): string => $request->getPresenterName(), $application->getRequests()));
            return [$container->getByType(Http\Response::class)->getCode(), $output];
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            $_SERVER = $savedServer;
            http_response_code(is_int($savedStatus) ? $savedStatus : 200);
        }
    }

    /**
     * Every request also proves H5 (it changes no data and records no external call) and that the answer is
     * not cacheable.
     * @param array<string, string> $headers
     * @param array<string, object> $services
     */
    private function request(?int $uid, ?string $body, array $headers = self::HEADERS, string $method = 'POST', array $services = []): PresenterResult {
        $before = $this->scenario->snapshot();
        $result = $this->presenters->mapi($uid, $body, $headers, $method, $services);
        Assert::same($before, $this->scenario->snapshot(), 'A GraphQL request changed data or recorded an external call.');
        Assert::same('no-store', $this->headers($result)['Cache-Control'] ?? null);
        Assert::same($result->json, Json::decode($result->html, forceArrays: true));
        return $result;
    }

    /** @return array<string, string> */
    private function headers(PresenterResult $result): array {
        $response = $result->response;
        Assert::true($response instanceof Odpoved);
        return $response->hlavicky;
    }

    private function assertRejected(PresenterResult $result, int $status, string $code, string $case = ''): void {
        Assert::same($status, $result->status, $case);
        Assert::same(['errors'], array_keys($result->json), $case);
        Assert::true(count($result->json['errors']) >= 1, $case);
        foreach ($result->json['errors'] as $error) {
            Assert::same(['message', 'extensions'], array_slice(array_keys($error), 0, 2), $case);
            Assert::same(['code' => $code], $error['extensions'], $case);
            Assert::same([], array_diff(array_keys($error), ['message', 'extensions', 'locations']), $case);
        }
    }

    private function assertNoDetails(string $output, string $exceptionText): void {
        foreach ([$exceptionText, 'SELECT', 'Uzivatel', 'OfflineConnection', '.php', '#0', 'Stack', 'trace', '<html', '<!DOCTYPE'] as $detail) {
            Assert::notContains($detail, $output);
        }
    }

    /** @return list<array{?int, ?string, string}> */
    private function takeLog(): array {
        $entries = $this->log->pristupy;
        $this->log->pristupy = [];
        return $entries;
    }

    private function memberWithRole(string $type, ?int $areaId = null, string $from = '2000-01-01', ?string $until = '2099-12-31'): ActiveRow {
        $member = $this->fixtures->member($this->scenario->ap->id);
        $this->fixtures->role($member->id, $type, $areaId, $from, $until);
        return $member;
    }

    private function failingAuthenticator(): Authenticator {
        return new class (false, $this->database) extends Authenticator {
            public function authenticate(array $credentials): \App\Model\HkfIdentity {
                throw new \RuntimeException('Simulated outage in SELECT heslo FROM Uzivatel');
            }
        };
    }

    /** @param array<string, mixed>|null $variables */
    private static function query(string $query, ?string $operationName = null, ?array $variables = null): string {
        return Json::encode(array_filter(['query' => $query, 'operationName' => $operationName, 'variables' => $variables], static fn (mixed $value): bool => $value !== null));
    }

    /** A query whose depth, as the library counts it, is exactly $depth. */
    private static function nested(int $depth): string {
        return 'query Hluboky { __type(name: "Ja") { ' . str_repeat('ofType { ', $depth) . 'name' . str_repeat(' }', $depth) . ' } }';
    }
}

(new GraphqlBoundaryPresenterTest())->run();
