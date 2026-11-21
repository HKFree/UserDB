<?php

declare(strict_types=1);

namespace Test;

use App\Services\GeneratorSmlouvy;
use App\Services\PdfGenerator;
use App\Services\SmlouvaStavSluzba;
use Nette\Bridges\ApplicationLatte\DefaultTemplate;
use Tester\Assert;

require_once __DIR__ . '/bootstrap.php';

final class ContractTypesDatabaseTest extends DatabaseTestCase
{
    public function testContractStateUsesDatesAndLatestSignature(): void {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id);
        $contract = $this->fixtures->insert('Smlouva', ['uzivatel_id' => $member->id, 'typ' => 'ucastnicka']);
        $service = new SmlouvaStavSluzba();
        $state = fn () => $service->getStav($this->database->table('Smlouva')->get($contract->id));
        Assert::same('---', $state());
        $signature = $this->fixtures->insert('PodpisSmlouvy', ['smlouva_id' => $contract->id, 'smluvni_strana' => 'ucastnik', 'jmeno' => 'Fixture']);
        Assert::same('⏳ čeká na podpis', $state());
        $signature->update(['kdy_podepsano' => '2026-01-02 12:00:00']);
        $this->fixtures->insert('PodpisSmlouvy', ['smlouva_id' => $contract->id, 'smluvni_strana' => 'druzstvo', 'jmeno' => 'Fixture', 'kdy_podepsano' => '2026-01-01 12:00:00']);
        Assert::same('✅ platná od 02.01.2026', $state());
        $signature->update(['kdy_odmitnuto' => '2026-01-03 12:00:00']);
        Assert::same('🚫 odmítnutá dne 03.01.2026', $state());
        $contract->update(['kdy_ukonceno' => '2026-01-04 12:00:00']);
        Assert::same('❌ ukončená dne 04.01.2026', $state());
    }

    public function testRegistrationPdfHidesAlreadySentPassword(): void {
        $member = $this->fixtures->member($this->fixtures->ap($this->fixtures->area()->id)->id, ['heslo' => 'test-only']);
        $generator = $this->container->getByType(PdfGenerator::class);
        foreach ([0 => 'test-only', 1 => '-- nelze zpětně zjistit --'] as $sent => $expected) {
            $member->update(['regform_downloaded_password_sent' => $sent]);
            $template = new DefaultTemplate(new \Latte\Engine());
            $generator->generatePdf($member, $template);
            Assert::same($expected, $template->heslo);
        }
    }

    public function testContractParametersDistinguishPersonAndCompany(): void {
        $GLOBALS['container'] = $this->container;
        $ap = $this->fixtures->ap($this->fixtures->area()->id);
        foreach (['FO', 'PO'] as $type) {
            $member = $this->fixtures->member($ap->id, [
                'TypPravniFormyUzivatele_id' => $this->fixtures->lookup('TypPravniFormyUzivatele', $type),
                'datum_narozeni' => '1980-01-02', 'firma_nazev' => 'Testovací firma', 'firma_ico' => '12345678',
            ]);
            $parameters = GeneratorSmlouvy::parametryUcastnickeSmlouvy($member);
            Assert::same($type === 'FO' ? 'Datum narození' : 'Firma', $parameters['upresneni']);
            Assert::same($type === 'FO' ? '02.01.1980' : '', $parameters['datum_narozeni']);
            if ($type === 'PO') {
                Assert::same('Testovací firma, IČO: 12345678', $parameters['firma']);
            } else {
                Assert::hasNotKey('firma', $parameters);
            }
        }
    }
}

(new ContractTypesDatabaseTest())->run();
