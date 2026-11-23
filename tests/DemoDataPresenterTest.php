<?php

declare(strict_types=1);

namespace Test;

use Nette\Database\Connection;
use Nette\Database\Explorer;
use Nette\Database\Structure;
use Nette\Database\Conventions\DiscoveredConventions;
use Nextras\Migrations\Bridges\NetteDatabase\NetteAdapter;
use Nextras\Migrations\Configurations\DefaultConfiguration;
use Nextras\Migrations\Drivers\MySqlDriver;
use Nextras\Migrations\Engine\Runner;
use Nextras\Migrations\Printers\DevNull;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../scripts/UserdbDemoVolume.php';
require_once __DIR__ . '/../scripts/UserdbDemoPersonas.php';

/** Builds a separate database; the shared userdb_test contract remains free of dummy data. */
final class DemoDataPresenterTest extends TestCase
{
    private Explorer $database;
    private PresenterRunner $presenters;
    private string $seededOn;

    public function testDemoMigrationsPagesAndVolume(): void {
        \Nette\Utils\FileSystem::createDir(TestEnvironment::TEMP_DIR . '/locks');
        Environment::lock('userdb_demo_test', TestEnvironment::TEMP_DIR . '/locks');
        $this->seededOn = (new \DateTime('now', new \DateTimeZone('Europe/Prague')))->format('Y-m-d');
        $this->database = $this->buildDatabase();
        $container = TestEnvironment::container($this->database);
        $this->presenters = new PresenterRunner($this->database, $container);
        $this->assertReferences();
        $this->assertScenarioSafety();
        // First of the page checks: the seeded login limit lasts 15 minutes from the load.
        $this->assertApi();
        $this->assertMemberPages();
        $this->assertUnusableSenders();
        $this->assertNetworkPages();
        $this->assertAdministration();
        $this->assertVolume();
        Assert::same([], $container->getByType(RecordingMailer::class)->messages);
        Assert::same([], $container->getByType(RecordingMexSmsSender::class)->calls);
    }

    private function buildDatabase(): Explorer {
        $socket = getenv('USERDB_MARIADB_SOCKET') ?: throw new \RuntimeException('Run inside Flox.');
        $connection = new Connection('mysql:unix_socket=' . $socket . ';dbname=userdb_demo_test', 'userdb_demo_test', 'demo-test-only', ['lazy' => true]);
        $this->waitForServer($connection);
        $this->guard($connection);
        $adapter = new class ($connection) extends NetteAdapter {
            public int $scenarioStatements = 0;
            public int $scenarioFiles = 0;
            private bool $scenario = false;

            public function __construct(private readonly Connection $connection) {
                parent::__construct($connection);
            }

            public function exec(string $sql): int {
                if (str_contains($sql, 'SET @demo_previous_sql_mode')) {
                    $this->scenario = true;
                    $this->scenarioFiles++;
                }
                $dataStatement = preg_match('/\b(?:INSERT INTO|UPDATE|DELETE FROM)\b/', $sql) === 1;
                if ($this->scenario && $dataStatement) {
                    Assert::contains('STRICT_ALL_TABLES', explode(',', $this->connection->fetchField('SELECT @@SESSION.sql_mode')));
                    $this->scenarioStatements++;
                }
                $count = parent::exec(str_replace('DEFINER=`userdb_v2`@`%`', 'DEFINER=CURRENT_USER', $sql));
                if ($this->scenario && $dataStatement) {
                    Assert::same([], $this->connection->fetchAll('SHOW WARNINGS'), $sql);
                }
                if (str_contains($sql, 'SET SESSION sql_mode = @demo_previous_sql_mode')) {
                    $this->scenario = false;
                }
                return $count;
            }
        };
        $driver = new MySqlDriver($adapter);
        $printer = new class () extends DevNull {
            public function printError(\Nextras\Migrations\Exception $e): void {
                throw $e;
            }
        };
        // Only the explicitly guarded, dedicated demo test database is rebuilt.
        (new Runner($driver, $printer))->run(Runner::MODE_RESET, new DefaultConfiguration(TestEnvironment::ROOT . '/db', $driver, true));
        Assert::same(10, $adapter->scenarioFiles);
        Assert::true($adapter->scenarioStatements >= 70);
        $files = glob(TestEnvironment::ROOT . '/db/{structures,basic-data,dummy-data}/*.sql', GLOB_BRACE);
        Assert::same(count($files === false ? [] : $files), $connection->fetchField('SELECT COUNT(*) FROM migrations WHERE ready = 1'));
        $connection->disconnect();
        $this->guard($connection);
        $structure = new Structure($connection, new \Nette\Caching\Storages\MemoryStorage());
        return new Explorer($connection, $structure, new DiscoveredConventions($structure));
    }

    /** The Flox service may still be starting, and creates the account of this database only when it starts. */
    private function waitForServer(Connection $connection): void {
        $deadline = microtime(true) + 30;
        while (true) {
            try {
                $connection->connect();
                return;
            } catch (\Nette\Database\ConnectionException $e) {
                if (microtime(true) >= $deadline) {
                    throw new \RuntimeException('Cannot connect to userdb_demo_test. Restart the Flox services if MariaDB was started before scripts/userdb_mariadb_init.sql added this database; otherwise check .flox/cache/logs/mariadb.log.', 0, $e);
                }
                usleep(100000);
            }
        }
    }

    private function guard(Connection $connection): void {
        $cache = realpath(TestEnvironment::ROOT . '/.flox/cache');
        $data = realpath(TestEnvironment::ROOT . '/.flox/cache/mariadb/data');
        if ($cache === false || $data === false || realpath(getenv('FLOX_ENV_CACHE') ?: '') !== $cache
            || realpath($connection->fetchField('SELECT @@datadir')) !== $data
            || $connection->fetchField('SELECT DATABASE()') !== 'userdb_demo_test'
        ) {
            throw new \RuntimeException('Refusing to rebuild outside this checkout\'s userdb_demo_test database.');
        }
    }

    private function assertReferences(): void {
        $constraints = [];
        foreach ($this->database->query(<<<'SQL'
            SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION
            SQL) as $column) {
            $constraints[$column->TABLE_NAME . '.' . $column->CONSTRAINT_NAME][] = $column;
        }
        Assert::true(count($constraints) >= 30, 'The generic foreign-key scan must actually inspect the schema.');
        foreach ($constraints as $name => $columns) {
            $join = [];
            $notNull = [];
            $joinParameters = [];
            $notNullParameters = [];
            foreach ($columns as $column) {
                $join[] = '?name = ?name';
                $joinParameters[] = 'child.' . $column->COLUMN_NAME;
                $joinParameters[] = 'parent.' . $column->REFERENCED_COLUMN_NAME;
                $notNull[] = '?name IS NOT NULL';
                $notNullParameters[] = 'child.' . $column->COLUMN_NAME;
            }
            $first = $columns[0];
            $sql = 'SELECT COUNT(*) FROM ?name child LEFT JOIN ?name parent ON '
                . implode(' AND ', $join) . ' WHERE ' . implode(' AND ', $notNull) . ' AND ?name IS NULL';
            $parameters = [$first->TABLE_NAME, $first->REFERENCED_TABLE_NAME, ...$joinParameters, ...$notNullParameters, 'parent.' . $first->REFERENCED_COLUMN_NAME];
            Assert::same(0, $this->database->fetchField($sql, ...$parameters), $name);
        }
        Assert::same([20102 => 29999], $this->database->query(<<<'SQL'
            SELECT child.id, child.w_ap_IPAdresa_id FROM IPAdresa child
            LEFT JOIN IPAdresa parent ON parent.id = child.w_ap_IPAdresa_id
            WHERE child.w_ap_IPAdresa_id IS NOT NULL AND parent.id IS NULL
            SQL)->fetchPairs('id', 'w_ap_IPAdresa_id'));
    }

    private function assertScenarioSafety(): void {
        Assert::same(72, $this->database->table('Uzivatel')->where('id BETWEEN 20000 AND 20071')->count('*'));
        Assert::same(72, $this->database->table('Uzivatel')->where('id BETWEEN 20000 AND 20071 AND poznamka LIKE ?', 'DEMO: %')->count('*'));
        Assert::same(60, $this->database->table('IPAdresa')->where('Uzivatel_id', 20061)->count('*'));
        foreach ([
            'SELECT COUNT(*) FROM Oblast o LEFT JOIN Ap a ON a.Oblast_id = o.id WHERE a.id IS NULL',
            'SELECT COUNT(*) FROM IPAdresa WHERE Uzivatel_id IS NULL AND Ap_id IS NULL',
            'SELECT COUNT(*) FROM IPAdresa WHERE w_ssid IS NOT NULL AND w_timestamp IS NULL',
            // Another loopback address is not refused at once on macOS: the Wewimo page would hang.
            "SELECT COUNT(*) FROM IPAdresa WHERE wewimo = 1 AND ip_adresa <> '127.0.0.1'",
            'SELECT COUNT(*) FROM IPAdresa WHERE heslo_sifrovane <> 0',
            // A save of the AP or member form encrypts the password: 168 characters and 2 more per byte, the column holds 255.
            'SELECT COUNT(*) FROM IPAdresa WHERE LENGTH(heslo) > 43',
            'SELECT COUNT(*) FROM Uzivatel WHERE oneclick_auth IS NOT NULL',
            "SELECT COUNT(*) FROM Subnet WHERE subnet NOT LIKE '%/%'",
            'SELECT COUNT(*) FROM Smlouva WHERE kdy_vygenerovano IS NULL',
            'SELECT COUNT(*) FROM CestneClenstviUzivatele WHERE schvaleno NOT IN (0, 1, 2)',
            'SELECT COUNT(*) FROM AplikaceToken t JOIN Uzivatel u ON u.id = t.Uzivatel_id WHERE u.TypClenstvi_id IS NULL',
        ] as $query) {
            Assert::same(0, $this->database->fetchField($query), $query);
        }
        // Planned members can still be deleted by the edit form: no dependent records.
        foreach (['UzivatelskeKonto', 'Smlouva', 'StitekUzivatele', 'SpravceOblasti', 'AplikaceToken', 'CestneClenstviUzivatele'] as $table) {
            Assert::same(0, $this->database->table($table)->where('Uzivatel_id', 20016)->count('*'));
        }
        Assert::same([1, 4], array_values($this->database->table('SpravceOblasti')->where('Uzivatel_id', 1)->order('TypSpravceOblasti_id')->fetchPairs('id', 'TypSpravceOblasti_id')));
        Assert::same([3, 3, 3], array_values($this->database->query('SELECT smlouva_id, COUNT(*) AS n FROM PodpisSmlouvy WHERE smlouva_id BETWEEN 150005 AND 150007 GROUP BY smlouva_id')->fetchPairs('smlouva_id', 'n')));
        Assert::same(36, $this->database->fetchField('SELECT COUNT(DISTINCT DATE_FORMAT(datum, ?)) FROM UzivatelskeKonto WHERE id BETWEEN 2001000 AND 2001107', '%Y-%m'));
    }

    /** Both personas with an invalid own address are warned; only the one who may edit its own record gets the link. */
    private function assertUnusableSenders(): void {
        foreach ([20070 => [201, 20018, 201], 20071 => [-20, 20015, 217]] as $persona => [$area, $member, $ap]) {
            foreach (['email' => $member, 'emailall' => $ap] as $action => $id) {
                $page = $this->presenters->ui($persona, 'UzivatelMailSms', ['action' => $action, 'id' => $id]);
                Assert::same(200, $page->status, "Persona $persona, $action");
                Assert::contains('vaše vlastní e-mailová adresa není platná', $page->html);
                Assert::same(['oblast' . $area . '@hkfree.org'], $this->values($page->html, '//select[@name="from"]/option'));
                Assert::same($persona === 20070, str_contains($page->html, '/uzivatel/edit/' . $persona . '"'), "Persona $persona, $action");
            }
        }
    }

    private function assertMemberPages(): void {
        foreach ($this->database->table('Uzivatel')->where('id BETWEEN 20000 AND 20071')->order('id') as $member) {
            $show = $this->page('Uzivatel', 'show', $member->id);
            Assert::contains('UID ' . $member->id . ' - Demo ' . $member->prijmeni, $this->text($show));
            $edit = $this->page('Uzivatel', 'edit', $member->id);
            Assert::same([(string) $member->id], $this->values($edit, '//input[@name="id"]/@value'));
            Assert::same([$member->prijmeni], $this->values($edit, '//input[@name="prijmeni"]/@value'));
            $this->page('UzivatelAccount', 'account', $member->id);
            $this->page('UzivatelTelevize', 'show', $member->id);
            $this->page('UzivatelRightsCc', 'editrights', $member->id);
            $this->page('UzivatelRightsCc', 'editcc', $member->id);
        }
        foreach ([20034 => ['Aktivace'], 20035 => ['Reaktivace'], 20036 => ['Deaktivace'], 20037 => [], 20038 => []] as $id => $buttons) {
            Assert::same($buttons, $this->values($this->page('Uzivatel', 'show', $id), '//a[contains(@href,"/uzivatel-actions/money-")]'));
        }
        foreach ([20065 => 'čeká na podpis', 20066 => 'odmítnutá dne', 20067 => 'platná od', 20068 => 'ukončená dne'] as $id => $status) {
            Assert::contains($status, $this->text($this->page('Uzivatel', 'show', $id)));
        }
        Assert::contains('Sloučen pod uživatele', $this->text($this->page('Uzivatel', 'show', 20059)));
        Assert::contains('Sloučený uživatel', $this->text($this->page('Uzivatel', 'show', 20058)));
        Assert::count(1, $this->values($this->page('Uzivatel', 'show', 20028), '//*[contains(@class,"fade-out-old")]'));
        Assert::count(60, $this->values($this->page('Uzivatel', 'edit', 20061), '//input[contains(@name,"[ip_adresa]")]/@value'));
        Assert::contains('NIKDY', $this->text($this->page('Uzivatel', 'show', 20043)));
        foreach ([20053 => ['ANO', 'NE'], 20054 => ['ANO', 'NE'], 20055 => ['ANO', 'ANO'], 20056 => ['NE', 'ANO'], 20057 => ['ANO', 'NE'], 20063 => ['NE', 'NE'], 1010 => ['ANO', 'NE']] as $id => $state) {
            $html = $this->page('UzivatelTelevize', 'show', $id);
            Assert::same($state[0], $this->values($html, '//tr[th="Objednaná"]/td')[0]);
            Assert::true(str_starts_with($this->values($html, '//tr[th="Aktivní"]/td')[0], $state[1]));
        }
        foreach ([20031, 20034, 20047, 20053, 20057] as $id) {
            $html = $this->page('PaymentSchedule', 'show', $id);
            Assert::contains('Pevný přístup k internetu', $this->text($html));
            Assert::same(1, count($this->values($html, '//img[starts-with(@src,"data:image/png;base64,")]/@src')));
        }
    }

    private function assertNetworkPages(): void {
        foreach ($this->database->table('Oblast') as $area) {
            Assert::contains($area->jmeno, $this->text($this->page('Ap', 'list', $area->id)));
        }
        foreach ($this->database->table('Ap') as $ap) {
            $this->page('Ap', 'show', $ap->id);
            $this->page('Ap', 'edit', $ap->id);
            foreach ([0, 1] as $money) {
                $this->page('UzivatelList', 'list', $ap->id, ['money' => $money]);
            }
        }
        $list = $this->page('UzivatelList', 'list', 202, ['grid-filter' => ['spolek_druzstvo' => 'all']]);
        foreach (['planovane', 'primarni', 'zrusene', 'invalidemail'] as $class) {
            Assert::true(count($this->values($list, '//tr[contains(@class,"' . $class . '")]')) > 0, $class);
        }
        $list = $this->page('UzivatelList', 'list', 201);
        Assert::true(count($this->values($list, '//tr[contains(@class,"cestne")]')) >= 3);
        $money = $this->page('UzivatelList', 'list', 201, ['money' => 1]);
        Assert::true(count($this->values($money, '//tr[contains(@class,"preplatek")]')) > 0);
        Assert::true(count($this->values($money, '//tr[contains(@class,"neaktivni")]')) > 0);
        $foreignMembers = $this->text($this->page('UzivatelList', 'list', 213));
        foreach ([20062, 20064, 20065] as $foreign) {
            Assert::contains('demo' . $foreign, $foreignMembers);
            // ZSO 20004 may edit a foreign member only because of the member's address inside a subnet of AP 213.
            Assert::contains('Editovat', $this->text($this->presenters->ui(20004, 'Uzivatel', ['action' => 'show', 'id' => $foreign])->html));
        }
        Assert::notContains('Editovat', $this->text($this->presenters->ui(20004, 'Uzivatel', ['action' => 'show', 'id' => 20063])->html));
        // An area manager finds only the company of his own area, by its name and by its IČO.
        foreach ([[20012, 'společnost', [20031]], [20012, '00987654', []], [20002, 'lhota', [20063]], [20002, '00987654', [20063]], [20002, '9999999', []]] as [$manager, $search, $found]) {
            $html = $this->presenters->ui($manager, 'UzivatelList', ['action' => 'listall', 'search' => $search])->html;
            $rows = $this->values($html, '//tr[starts-with(@class,"grid-row-")]/@class');
            Assert::same(array_map(static fn (int $id): string => "grid-row-$id", $found), $rows, "$manager: $search");
        }
        foreach (['192.0.2', '198.51.100', '10.220.0', '10.220.1', '10.220.2', '10.220.3', '203.0.113', '89.248.240'] as $prefix) {
            $this->page('Subnet', 'overview', $prefix);
            $this->page('Subnet', 'detail', $prefix);
        }
        // Device logins and passwords on AP 200: each stored value, spaces included, has an element of its own and a copy button.
        $credentials = $this->page('Ap', 'show', 200);
        Assert::same(
            [
                'demo', 'demo-only', 'admin', 'demo-43-znaku!Aa1#Bb2$Cc3%Dd4&Ee5<Ff6>Gg7=H', 'demo', 'demo only', 'jenlogin', 'jen-heslo',
                'user.name@demo.example', '.tecky.na.zacatku.i.konci.', 'DEMO\\spravce', 'It\'s "x" \\n $(id) {$z} %20 <b>&amp;</b>\\',
                'správce', 'Žluťoučký kůň 密码 пароль', 'a b', "pevna mezera\u{200b}konec",
            ],
            $this->values($credentials, '//td/span[@class="copy-value"]'),
        );
        Assert::contains('<span class="copy-value"> demo  only </span>', $credentials);
        // Markup in a password is shown as text, not interpreted.
        Assert::contains('<span class="copy-value">It\'s "x" \\n $(id) {$z} %20 &lt;b&gt;&amp;amp;&lt;/b&gt;\\</span>', $credentials);
        // A tab, a non-breaking space and a zero-width space reach the page as stored.
        Assert::contains("<span class=\"copy-value\">a\tb</span>", $credentials);
        Assert::contains("<span class=\"copy-value\">pevna\u{a0}mezera\u{200b}konec</span>", $credentials);
        Assert::same(
            array_merge(...array_fill(0, 8, ['Zkopírovat login', 'Zkopírovat heslo'])),
            $this->values($credentials, '//td/span[@class="copy-value"]/following-sibling::button[@type="button"]/@aria-label'),
        );
        // On a phone the wide table scrolls in a frame of its own, not the whole page.
        foreach ([$credentials, $this->page('Uzivatel', 'show', 20027)] as $html) {
            Assert::true(count($this->values($html, '//div[@class="table-responsive"]/table//span[@class="copy-value"]')) >= 2);
        }
        $subnet = $this->page('Subnet', 'detail', '198.51.100');
        Assert::count(6, $this->values($subnet, '//tr[contains(@class,"subnet-ip-network-row")]'));
        Assert::contains('198.51.100.1 (GW)', $this->text($subnet));
        Assert::contains('FREE', $this->text($this->page('Subnet', 'overview', '198.51.100')));
        // Nothing listens for RouterOS on the loopback address: the page shows the device as unreachable.
        Assert::count(1, $this->values($this->page('Wewimo', 'show', 210), '//div[contains(@class,"alert-danger")]'));
        $multiple = $this->presenters->ui(1, 'AjaxApi', ['action' => 'getIpDetails', 'ip' => '10.220.0.70']);
        Assert::same(['error' => 'Chyba! Pro tuto IP existuje více subnetů! 10.220.0.0/24, 10.220.0.64/26'], $multiple->json);
        $missing = $this->presenters->ui(1, 'AjaxApi', ['action' => 'getIpDetails', 'ip' => '10.220.3.20']);
        Assert::same(['error' => 'Brána pro tuto IP není v databázi!'], $missing->json);
        $arp = $this->presenters->ui(1, 'AjaxApi', ['action' => 'getIpDetails', 'ip' => '89.248.240.20']);
        Assert::same('89.248.255.254', $arp->json['gateway']);
    }

    private function assertAdministration(): void {
        $tools = $this->page('Sprava', 'nastroje');
        // All buttons actually linked from the tools page, guarded against future additions.
        Assert::count(10, $this->values($tools, '//a[contains(@class,"btn")]/@href'));
        foreach ([
            ['SpravaOblasti', 'novaoblast'], ['SpravaOblasti', 'noveap'],
            ['SpravaSlucovani', 'slucovani'], ['SpravaSlucovani', 'slouceni'],
            ['SpravaSifrovani', 'presifrovani'], ['SpravaCc', 'schvalovanicc'],
            ['SpravaPlateb', 'platbycu'], ['SpravaNesparovanych', 'nesparovane'],
            ['SpravaSms', 'sms'], ['SpravaMailu', 'mailinglist'],
            ['SpravaPlateb', 'odchoziplatby'], ['SpravaUctu', 'ucty'],
            ['SpravaCc', 'prehledcc'], ['SpravaStitku', 'default'], ['SpravaGrafu', 'usersgraph'],
        ] as [$presenter, $action]) {
            $this->page($presenter, $action);
        }
        $outgoing = $this->page('SpravaPlateb', 'odchoziplatby');
        Assert::true(count($this->values($outgoing, '//tr[contains(@class,"primarni")]')) > 0);
        Assert::same(120, $this->database->table('OdchoziPlatba')->count('*'));
        Assert::same(12, $this->database->table('BankovniUcet')->count('*'));
        $unmatched = $this->page('SpravaNesparovanych', 'nesparovane');
        Assert::contains('/40000100', $unmatched);
        Assert::contains('/40000101', $unmatched);
        foreach ([40000110, 40000111, 40000112, 40000113] as $id) {
            Assert::contains('/' . $id, $unmatched);
        }
        Assert::notContains('/40000102', $unmatched);
        Assert::notContains('/40000103', $unmatched);
        foreach ($this->database->table('Smlouva') as $contract) {
            $this->page('SpravaSmluv', 'show', $contract->id);
        }
        Assert::contains('Zatím žádné podpisy', $this->text($this->page('SpravaSmluv', 'show', 200000)));
        Assert::same(['Vypovězená smlouva č. 200004'], $this->values($this->page('SpravaSmluv', 'show', 200004), '//h1'));
        Assert::contains('Demo šablona', $this->text($this->page('SpravaSmluv', 'show', 200003)));
        foreach ([40000001 => 'ČS', 40000003 => 'FIO'] as $id => $bank) {
            Assert::contains($bank, $this->text($this->page('UzivatelAccount', 'platba', $id)));
        }
    }

    private function assertApi(): void {
        $credentials = ['apikey200', 'demo-global-only'];
        $areas = $this->presenters->api('Api:Areas', $credentials);
        Assert::same(200, $areas->status);
        Assert::same('Demo malá oblast', $areas->json[200]['jmeno']);
        Assert::same([], $areas->json[203]['admins']);
        Assert::same('ZSO', $areas->json[204]['admins'][20004]['role']);
        Assert::false(isset($areas->json[-20]));
        Assert::false(isset($areas->json[201]['admins'][20015]));
        $devices = $this->presenters->api('Api:DeviceDb', $credentials);
        Assert::same('Demo technická oblast', $devices->json[-20]);
        $device = $this->presenters->api('Api:Device', $credentials, ['action' => 'getCredentials', 'id' => '192.0.2.1']);
        Assert::same(['ip' => '192.0.2.1', 'login' => 'demo', 'heslo' => 'demo-only'], $device->json);
        $types = $this->presenters->api('Api:Monitoring', $credentials, ['action' => 'getTypyZarizeni']);
        Assert::same('RouterBoard', $types->json['typyZarizeni'][2]);
        foreach ([0, 1] as $users) {
            $monitoring = $this->presenters->api('Api:Monitoring', $credentials, ['action' => 'getZarizeni', 'ap' => 201, 'uzivatele' => $users]);
            Assert::same('OK', $monitoring->json['result']);
            Assert::same(201, $monitoring->json['zarizeni']['198.51.100.1']['Ap_id']);
            Assert::same((bool) $users, isset($monitoring->json['zarizeni']['198.51.100.20']));
        }
        $user = $this->presenters->api('Api:Uzivatel', $credentials, ['action' => 'getUzivatel', 'id' => 20027]);
        Assert::same('Kontaktní', $user->json['uzivatel']['prijmeni']);
        $byIp = $this->presenters->api('Api:Uzivatel', $credentials, ['action' => 'getUzivatelbyIP', 'id' => '203.0.113.20']);
        Assert::same(['result' => 'OK', 'Uzivatel_id' => 20062], $byIp->json);
        $admins = $this->presenters->api('Api:Hlasys', $credentials, ['action' => 'getSpravce', 'typSpravce' => 'ZSO']);
        Assert::same('demo20004', $admins->json['spravci'][20004]);
        $members = $this->presenters->api('Api:Hlasys', $credentials, ['action' => 'getClenySpolku']);
        Assert::same('demo20018', $members->json['clenove'][20018]);
        Assert::false(isset($members->json['clenove'][20015]));
        // Watched addresses: AP addresses with a description, with a hostname only and with neither, and two member addresses.
        Assert::same([20000, 20004, 20005, 20100, 20104], $this->database->table('IPAdresa')->where('smokeping', 1)->order('id')->fetchPairs(null, 'id'));
        $smoke = $this->text($this->presenters->api('Api:Smokeping', $credentials)->html);
        foreach ([
            '++ userdb-demo-mala-oblast-200 menu = Demo mala oblast - Demo male AP title = Demo mala oblast - Demo male AP',
            'menu = Demo brana RouterBoard title = Demo brana RouterBoard host = 192.0.2.1',
            // An AP named like its area gets a single title.
            '++ userdb-demo-shodny-nazev-211 menu = Demo shodny nazev title = Demo shodny nazev',
            'menu = demo-hostname title = demo-hostname host = 10.220.5.1',
            'menu = AP-IP-20005 title = AP-IP-20005 host = 10.220.5.2',
            '+++ userdb-demo-hlavni-ap-20027 menu = 20027-demo20027 title = 20027-demo20027',
            'menu = Demo RouterBoard, priznaky DHCP, MAC a internet title = Demo RouterBoard, priznaky DHCP, MAC a internet host = 198.51.100.20',
            'menu = Demo bez SSID title = Demo bez SSID host = 198.51.100.23',
        ] as $section) {
            Assert::contains($section, $smoke);
        }
        Assert::contains('IP je sledovaná ve smokepingu', $this->page('Ap', 'show', 211));
        $check = $this->presenters->api('Api:Smokeping', $credentials, ['action' => 'check']);
        Assert::match('~^[a-f0-9]{32}\s*$~', $check->html);
        $token = ['uid' => 20018, 'token' => 'demo-token-20018'];
        $membership = $this->presenters->api('Api:App', $credentials, ['action' => 'getMembership'] + $token);
        Assert::same(['result' => 'OK', 'clenstvi' => 'řádné', 'jmeno' => 'Demo'], $membership->json);
        $map = $this->presenters->api('Api:App', $credentials, ['action' => 'getMap'] + $token);
        Assert::same('OK', $map->json['result']);
        Assert::true(count($map->json['aps']) >= 5);
        $limited = $this->presenters->api('Api:App', $credentials, ['action' => 'getToken'], 'POST', ['uid' => '20018', 'heslo' => 'demo-only']);
        Assert::same(['result' => 'Too many unsuccessful attempts, try again in 15 minutes'], $limited->json);
        $second = $this->presenters->api('Api:Uzivatel', ['apikey203', 'demo-second-ap-only'], ['action' => 'getUzivatel', 'id' => 20027]);
        Assert::same(200, $second->status);
        $denied = $this->presenters->api('Api:Uzivatel', ['apikey203', 'demo-second-ap-only'], ['action' => 'getUzivatel', 'id' => 20062]);
        Assert::same(403, $denied->status);
        // The key is valid until the end of the Prague day the data were loaded on.
        if ($this->seededOn === (new \DateTime('now', new \DateTimeZone('Europe/Prague')))->format('Y-m-d')) {
            $today = $this->presenters->api('Api:HealthCheck', ['apikey202', 'demo-today-only']);
            Assert::same('OK', $today->json['result']);
        }
        $expired = $this->presenters->api('Api:HealthCheck', ['apikey204', 'demo-expired-only']);
        Assert::same(401, $expired->status);
        $multiple = $this->presenters->api('Api:Areas', ['apikey201', 'demo-multiple-only']);
        Assert::same(200, $multiple->status);
    }

    private function assertVolume(): void {
        $connection = $this->database->getConnection();
        $loader = new \UserdbDemoVolume($connection);
        // Force a failure in the last step, after members, accounts and history: everything must roll back.
        $this->database->table('SpravceOblasti')->insert(['id' => 100000, 'Uzivatel_id' => 20018, 'Oblast_id' => 200, 'TypSpravceOblasti_id' => 2, 'od' => new \DateTime()]);
        Assert::exception(fn () => $loader->apply(), \Nette\Database\UniqueConstraintViolationException::class);
        Assert::same(0, $this->database->table('Oblast')->where('id >= 10000')->count('*'));
        Assert::same(0, $this->database->table('Uzivatel')->where('id >= 100000')->count('*'));
        Assert::same(0, $this->database->table('UzivatelskeKonto')->where('id >= 10000000')->count('*'));
        Assert::same(0, $this->database->table('Log')->where('puvodni_hodnota LIKE ?', 'Objem před %')->count('*'));
        $this->database->table('SpravceOblasti')->where('id', 100000)->delete();
        // Change log rows written by the application take ids from the same sequence; the volume must not need a fixed range.
        $this->database->table('Log')->insert([
            'id' => 160000, 'Uzivatel_id' => 1, 'datum' => new \DateTime(), 'ip_adresa' => '127.0.0.1',
            'tabulka' => 'Uzivatel', 'tabulka_id' => 20018, 'sloupec' => 'Uzivatel.poznamka', 'akce' => 'U',
        ]);
        Assert::true($loader->apply());
        $expected = ['Oblast' => 100, 'Ap' => 450, 'Subnet' => \UserdbDemoVolume::SUBNETS, 'Uzivatel' => 13500, 'IPAdresa' => 18000, 'UzivatelskeKonto' => 324550, 'PrichoziPlatba' => 54000];
        $starts = ['Oblast' => 10000, 'Ap' => 10000, 'Subnet' => 100000, 'Uzivatel' => 100000, 'IPAdresa' => 100000, 'UzivatelskeKonto' => 10000000, 'PrichoziPlatba' => 50000000];
        foreach ($expected as $table => $count) {
            Assert::same($count, $this->database->table($table)->where('id >= ?', $starts[$table])->count('*'), $table);
        }
        Assert::same(4500, $this->database->table('Uzivatel')->where('id >= 100000 AND money_aktivni = 1')->count('*'));
        Assert::same(9000, $this->database->table('Uzivatel')->where('id >= 100000 AND TypClenstvi_id = 1')->count('*'));
        Assert::same(100000, $this->database->table('Log')->where('puvodni_hodnota LIKE ?', 'Objem před %')->count('*'));
        Assert::same(600, $this->database->table('Uzivatel')->where('Ap_id = ? AND money_aktivni = 1', \UserdbDemoVolume::CROWDED_AP)->count('*'));
        // The latest payment and activation are picked by id: the newest month (the loader works in UTC) must have the highest id.
        Assert::same($this->database->fetchField("SELECT DATE_FORMAT(UTC_DATE(), '%Y-%m-01')"), $this->database->table('UzivatelskeKonto')->where('Uzivatel_id = 100700 AND TypPohybuNaUctu_id = 4')->order('id DESC')->fetch()?->datum->format('Y-m-d'));
        Assert::same(622, $this->database->table('UzivatelskeKonto')->where('Uzivatel_id = 100000 AND druzstvo = 1')->count('*'));
        Assert::same(3000, $this->database->table('Log')->where('tabulka = ? AND tabulka_id = 100000', 'Uzivatel')->count('*'));
        Assert::same(30, $this->database->table('Ap')->where('Oblast_id', 10000)->count('*'));
        Assert::same(\UserdbDemoVolume::BIG_AREA_SUBNETS, $this->database->table('Subnet')->where('Ap.Oblast_id', 10000)->count('*'));
        // Rows inserted out of primary key order made this table ten times larger than the server's memory for it.
        $this->database->query('ANALYZE TABLE UzivatelskeKonto')->fetchAll();
        Assert::true($this->database->fetchField("SELECT data_length FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'UzivatelskeKonto'") < 100 * 1024 * 1024);
        $this->assertReferences();
        Assert::false($loader->apply());
        foreach ($expected as $table => $count) {
            Assert::same($count, $this->database->table($table)->where('id >= ?', $starts[$table])->count('*'), $table . ' after second load');
        }
        // Every generated address lies in exactly one subnet and that subnet belongs to the member's own AP,
        // so the big administrator may edit his own member and gets the restricted view (after the expensive
        // check) for a foreign one.
        Assert::same(0, $this->database->fetchField(<<<'SQL'
            SELECT COUNT(*) FROM (
                SELECT i.id, COUNT(s.id) AS subnets, SUM(s.Ap_id = u.Ap_id) AS own
                FROM IPAdresa i JOIN Uzivatel u ON u.id = i.Uzivatel_id
                LEFT JOIN Subnet s ON s.id >= 100000
                    AND INET_ATON(i.ip_adresa) & (POWER(2, 32) - POWER(2, 32 - SUBSTRING_INDEX(s.subnet, '/', -1)))
                        = INET_ATON(SUBSTRING_INDEX(s.subnet, '/', 1))
                WHERE u.id >= 100000
                GROUP BY i.id
                HAVING subnets <> 1 OR own <> 1
            ) outside
            SQL));
        $own = $this->presenters->ui(20003, 'Uzivatel', ['action' => 'show', 'id' => 100600]);
        Assert::contains('Editovat', $this->text($own->html));
        $foreign = $this->presenters->ui(20003, 'Uzivatel', ['action' => 'show', 'id' => 100700]);
        Assert::same(200, $foreign->status);
        Assert::notContains('Editovat', $this->text($foreign->html));
        $this->assertPersonaRoles();
        $this->page('UzivatelList', 'list', \UserdbDemoVolume::CROWDED_AP);
        $this->page('UzivatelAccount', 'account', 100000);
    }

    /** The switching page describes every persona by the roles the demo data really give it. */
    private function assertPersonaRoles(): void {
        foreach (\UserdbDemoPersonas::all() as $id => $persona) {
            $roles = $this->database->query(<<<'SQL'
                SELECT CONCAT(t.text, IF(s.Oblast_id IS NULL, '', CONCAT('-', s.Oblast_id))) AS role
                FROM SpravceOblasti s JOIN TypSpravceOblasti t ON t.id = s.TypSpravceOblasti_id
                WHERE s.Uzivatel_id = ? AND s.od < NOW() AND (s.`do` IS NULL OR s.`do` > NOW())
                SQL, $id)->fetchPairs(null, 'role');
            $expected = $persona['roles'];
            sort($expected);
            sort($roles);
            Assert::same($expected, $roles, "Roles of persona $id");
        }
    }

    /** @param array<string, mixed> $parameters */
    private function page(string $presenter, string $action, int|string|null $id = null, array $parameters = []): string {
        $result = $this->presenters->ui(1, $presenter, ['action' => $action, 'id' => $id] + $parameters);
        Assert::same(200, $result->status, "$presenter:$action $id");
        Assert::null($result->redirectUrl, "$presenter:$action $id redirected");
        Assert::contains('</html>', $result->html, "$presenter:$action $id did not render a complete page");
        return $result->html;
    }

    /** @return list<string> */
    private function values(string $html, string $query): array {
        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $nodes = (new \DOMXPath($document))->query($query);
        if ($nodes === false) {
            throw new \LogicException('Invalid test XPath: ' . $query);
        }
        $out = [];
        foreach ($nodes as $node) {
            Assert::true($node instanceof \DOMNode);
            $out[] = trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
        }
        return $out;
    }

    private function text(string $html): string {
        return html_entity_decode(trim((string) preg_replace('/\s+/u', ' ', strip_tags($html))), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

(new DemoDataPresenterTest())->run();
