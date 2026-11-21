<?php

namespace Test;

use App\Model;
use App\Presenters\UzivatelPresenter;
use Nette\Forms\Form;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

$users = new class () extends Model\Uzivatel {
    public function __construct() {
        parent::__construct(new OfflineDatabase(), new \Nette\Security\User(new MemoryUserStorage()));
    }

    public function getUzivatel($id): ?\Nette\Database\Table\ActiveRow {
        Assert::same('', $id);
        return null;
    }
};

// Test only validation, without constructing services that can contact external systems.
$reflection = new \ReflectionClass(UzivatelPresenter::class);
$presenter = $reflection->newInstanceWithoutConstructor();
$reflection->getProperty('uzivatel')->setValue($presenter, $users);

$missingBirthDate = ['Právní vztah k družstvu vyžaduje zadání data narození.'];
$cases = [
    'FO without birth date' => ['1', '1', '', $missingBirthDate],
    'FO with birth date' => ['1', '1', '1980-01-01', []],
    'FO without cooperative relationship' => ['1', '0', '', []],
    'FO with omitted birth date' => ['1', '1', null, $missingBirthDate],
    'PO with birth date' => ['2', '1', '1980-01-01', []],
    'PO without birth date' => ['2', '1', '', []],
    'PO with omitted birth date' => ['2', '1', null, []],
];

foreach ($cases as $name => [$legalForm, $cooperative, $birthDate, $expectedErrors]) {
    // Values are strings, as in a submitted HTML form. Null means the field is absent.
    $data = [
        'save' => 'Uložit',
        'id' => '',
        'TypPravniFormyUzivatele_id' => $legalForm,
        'druzstvo' => $cooperative,
        'smazano' => '0',
        'spolek' => '0',
        'TypClenstvi_id' => '-1',
    ];
    if ($birthDate !== null) {
        $data['datum_narozeni'] = $birthDate;
    }

    $form = new class ($data) extends Form {
        /** @param array<string, string> $data */
        public function __construct(private array $data) {
            parent::__construct();
        }

        /** @return array<string, string> */
        protected function receiveHttpData(): array {
            return $this->data;
        }
    };
    $form->onValidate[] = [$presenter, 'validateUzivatelForm'];
    $form->validate();

    Assert::same($expectedErrors, $form->getErrors(), $name);
}
