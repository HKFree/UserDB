<?php

declare(strict_types=1);

namespace Test;

use App\MapiModule\Data\PovoleneRole;
use App\MapiModule\Data\Prihlaseny;
use App\MapiModule\Data\RolePrihlaseneho;
use App\MapiModule\Odpoved;
use App\MapiModule\TracyZaznam;
use Nette\Http;
use Nette\Utils\JsonException;
use Tester\Assert;
use Tester\TestCase;
use Tracy\ILogger;

require_once __DIR__ . '/bootstrap.php';

/**
 * Small parts of the GraphQL endpoint (issue #264) that need neither a request nor the database.
 */
final class GraphqlPartsTest extends TestCase
{
    public function testRoleOfTheWebIsSplitIntoTypeAndArea(): void {
        foreach ([
            'VV' => ['VV', null],
            'SO-12' => ['SO', 12],
            'DRUŽSTEVNÍK' => ['DRUŽSTEVNÍK', null],
            'PŘEDSTAVENSTVO-7' => ['PŘEDSTAVENSTVO', 7],
            'NOVÁ-ROLE-3' => ['NOVÁ-ROLE', 3],
            'NOVÁ-ROLE' => ['NOVÁ-ROLE', null],
        ] as $text => [$typ, $oblastId]) {
            $role = RolePrihlaseneho::zTextu($text);
            Assert::same([$typ, $oblastId], [$role->typ, $role->oblastId], $text);
        }
    }

    public function testOnlyListedRoleTypesAllowTheApi(): void {
        Assert::same(['VV', 'TECH', 'KONTROLA', 'SO', 'ZSO', 'EXTSUPPORT'], PovoleneRole::TYPY);
        $signedIn = static fn (string ...$roles): Prihlaseny => new Prihlaseny(1, 'nick', array_map(RolePrihlaseneho::zTextu(...), array_values($roles)));
        Assert::true(PovoleneRole::maNekterou($signedIn('DRUŽSTEVNÍK', 'ZSO-4')));
        Assert::true(PovoleneRole::maNekterou($signedIn('KONTROLA')));
        Assert::false(PovoleneRole::maNekterou($signedIn('DRUŽSTEVNÍK-4', 'PMVTEAM', 'SKLADNIK', 'PŘEDSTAVENSTVO')));
        Assert::false(PovoleneRole::maNekterou($signedIn('so-4', 'VVV', 'TECHNIK', ' VV')));
        Assert::false(PovoleneRole::maNekterou($signedIn()));
    }

    public function testAccessLogLineHasUidOperationAndResult(): void {
        $logger = new RecordingLogger();
        $zaznam = new TracyZaznam($logger);
        $zaznam->pristup(123, 'Ja', 'OK');
        $zaznam->pristup(null, null, 'UNAUTHENTICATED');
        $zaznam->pristup(7, null, 'FORBIDDEN,INTERNAL');
        Assert::same([
            ['uid=123 operace=Ja vysledek=OK', 'mapi'],
            ['uid=- operace=- vysledek=UNAUTHENTICATED', 'mapi'],
            ['uid=7 operace=- vysledek=FORBIDDEN,INTERNAL', 'mapi'],
        ], $logger->records);
    }

    public function testExceptionIsLoggedWithoutCallArguments(): void {
        $logger = new RecordingLogger();
        $failure = self::failingResolver('query Tajne { hledej(jmeno: "Novák") }', ['jmeno' => 'Svoboda']);
        (new TracyZaznam($logger))->vyjimka(new \RuntimeException('Resolver failed', 0, $failure));

        Assert::count(1, $logger->records);
        [$text, $level] = $logger->records[0];
        Assert::same(ILogger::EXCEPTION, $level);
        // Handing Tracy the exception itself would dump the call arguments: the query text and its variables.
        Assert::true(is_string($text));
        Assert::contains('RuntimeException: Resolver failed in ' . __FILE__ . ':', $text);
        Assert::contains('LogicException: Column not found in ' . __FILE__ . ':', $text);
        Assert::contains(self::class . '::failingResolver', $text);
        foreach (['Tajne', 'hledej', 'Novák', 'Svoboda'] as $argument) {
            Assert::notContains($argument, $text);
        }
    }

    public function testAnswerIsNeverCacheable(): void {
        $odpoved = new Odpoved(405, ['errors' => [['message' => 'Příliš žluťoučký kůň']]], ['Allow' => 'POST']);
        Assert::same(405, $odpoved->stav);
        Assert::same(['Cache-Control' => 'no-store', 'Allow' => 'POST'], $odpoved->hlavicky);
        Assert::same(['Cache-Control' => 'no-store'], (new Odpoved(200, ['data' => null], ['Cache-Control' => 'public']))->hlavicky);

        $savedStatus = http_response_code();
        ob_start();
        try {
            $response = new Http\Response();
            $odpoved->send(new Http\Request(new Http\UrlScript('http://localhost/userdb/mapi/graphql')), $response);
            Assert::same('{"errors":[{"message":"Příliš žluťoučký kůň"}]}', ob_get_contents());
            Assert::same(405, $response->getCode());
        } finally {
            ob_end_clean();
            http_response_code(is_int($savedStatus) ? $savedStatus : 200);
        }
    }

    public function testAnswerThatCannotBeEncodedFailsBeforeItIsSent(): void {
        Assert::exception(static fn () => new Odpoved(200, ['data' => ['nick' => "\xff"]]), JsonException::class);
    }

    /** @param array<string, string> $variables */
    private static function failingResolver(string $query, array $variables): \LogicException {
        return new \LogicException('Column not found');
    }
}

(new GraphqlPartsTest())->run();
