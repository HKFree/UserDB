<?php

declare(strict_types=1);

namespace Test;

use App\Model;
use App\Presenters\SpravaSlucovaniPresenter;
use Nette\Application\UI\Template;
use Nette\Bridges\ApplicationLatte\DefaultTemplate;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\GroupedSelection;
use Nette\Http;
use Tester\Assert;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/TestDoubles.php';
\Tester\Environment::setup();

class MergeUserRow extends ActiveRow
{
    public int $money_aktivni = 1;
    public int $money_deaktivace = 0;

    public function __construct(public int $kauce_mobil, private int $balance) {
        parent::__construct([
            'kauce_mobil' => $kauce_mobil,
            'money_aktivni' => $this->money_aktivni,
            'money_deaktivace' => $this->money_deaktivace,
        ], (new OfflineDatabase())->table('Uzivatel'));
    }

    public function related(string $key, ?string $throughColumn = null): GroupedSelection {
        return new class ($this->balance) extends GroupedSelection {
            public function __construct(private int $balance) {
                $database = new OfflineDatabase();
                parent::__construct($database, $database->getConventions(), 'UzivatelskeKonto', 'Uzivatel_id', $database->table('Uzivatel'));
            }

            /** @param string|array<array-key, mixed> $condition */
            public function where(string|array $condition, mixed ...$params): static {
                return $this;
            }

            public function order(string $columns, mixed ...$params): static {
                return $this;
            }

            public function limit(?int $limit, ?int $offset = null): static {
                return $this;
            }

            public function count(?string $column = null): int {
                return 0;
            }

            public function sum(string $column): int {
                return $this->balance;
            }
        };
    }
}

$cases = [
    [100, 250, '900 (kauce: 100)', '1750 (kauce: 250)'],
    [100, 0, '900 (kauce: 100)', 2000],
    [0, 250, 1000, '1750 (kauce: 250)'],
    [0, 0, 1000, 2000],
];

foreach ($cases as [$deposit1, $deposit2, $expected1, $expected2]) {
    $users = new class ([11 => new MergeUserRow($deposit1, 1000), 22 => new MergeUserRow($deposit2, 2000)]) extends Model\Uzivatel {
        /** @param array<int, MergeUserRow> $rows */
        public function __construct(private array $rows) {
            parent::__construct(new OfflineDatabase(), new \Nette\Security\User(new MemoryUserStorage()));
        }

        public function getUzivatel($id): ActiveRow {
            return $this->rows[$id];
        }
    };
    $memberships = new class () extends Model\CestneClenstviUzivatele {
        public function __construct() {
            parent::__construct(new OfflineDatabase(), new \Nette\Security\User(new MemoryUserStorage()));
        }

        public function getHasCC($userID): bool {
            return false;
        }
    };
    $mergedUsers = (new \ReflectionClass(Model\SloucenyUzivatel::class))->newInstanceWithoutConstructor();
    $log = (new \ReflectionClass(Model\Log::class))->newInstanceWithoutConstructor();
    $addresses = (new \ReflectionClass(Model\IPAdresa::class))->newInstanceWithoutConstructor();
    $presenter = new class ($mergedUsers, $users, $log, $addresses, $memberships, new Model\AP(new OfflineDatabase(), new \Nette\Security\User(new MemoryUserStorage()))) extends SpravaSlucovaniPresenter {
        protected function createTemplate(?string $class = null): Template {
            return new DefaultTemplate(new \Latte\Engine());
        }
    };
    $user = new class (new MemoryUserStorage()) extends \Nette\Security\User {
        /** @return list<string> */
        public function getRoles(): array {
            return ['VV'];
        }
    };
    $presenter->injectPrimary(new Http\Request(new Http\UrlScript('http://localhost/')), new Http\Response(), user: $user);
    $presenter->loadState(['u1' => 11, 'u2' => 22]);
    $presenter->renderSlucovani();

    Assert::same($expected1, $presenter->getTemplate()->u1money_bal);
    Assert::same($expected2, $presenter->getTemplate()->u2money_bal);
}
