<?php

namespace App\Presenters;

use App\Components\AppGrid;
use App\Model;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Forms\Container;
use Nette\Mail\Message;
use Nette\Mail\SendException;
use Nette\Forms\Controls\SubmitButton;
use Nette\Utils\ArrayHash;

/**
 * Sprava presenter.
 */
class SpravaCcPresenter extends SpravaPresenter
{
    private Model\CestneClenstviUzivatele $cestneClenstviUzivatele;
    private Model\Uzivatel $uzivatel;
    private Model\cc $platneCC;
    private Model\AP $ap;

    public function __construct(Model\CestneClenstviUzivatele $cc, Model\cc $actualCC, Model\Uzivatel $uzivatel, Model\AP $ap) {
        parent::__construct($uzivatel, $ap);
        $this->cestneClenstviUzivatele = $cc;
        $this->uzivatel = $uzivatel;
        $this->platneCC = $actualCC;
        $this->ap = $ap;
    }

    public function renderPrehledcc(): void {
    }

    protected function createComponentGrid(string $name): void {
        $canViewOrEdit = $this->ap->canViewOrEditAll($this->getUser());

        $grid = new AppGrid($this, $name);
        $grid->setExport('cc_export');

        if ($canViewOrEdit) {
            $grid->setModel($this->platneCC->getCCWithNamesVV());
        } else {
            $grid->setModel($this->platneCC->getCCWithNames($this->getIdentity()->getUid()));
        }

        $grid->setDefaultPerPage(100);
        $grid->setDefaultSort(array('plati_od' => 'DESC'));

        $grid->addColumnText('id', 'UID')->setSortable()->setFilterText();
        $grid->addColumnText('plati_od', 'Platnost od')->setSortable()->setFilterText();
        $grid->addColumnText('plati_do', 'Platnost do')->setSortable()->setFilterText();
        $grid->addColumnText('typcc', 'Typ CC')->setSortable()->setFilterText();
        $grid->addColumnText('name', 'Jméno a příjmení')->setSortable()->setFilterText();
        $grid->addColumnText('ap', 'AP')->setSortable()->setFilterText();

        $grid->addActionHref('show', 'Zobrazit')
                ->setIcon('eye-open');
    }

    public function renderSchvalovanicc(): void {
        /*** clear old registration files ***/
        foreach (glob(sys_get_temp_dir()."/registrace*") ?: [] as $file) {
            /*** if file is 7 days old then delete it ***/
            if (filemtime($file) < time() - 604800) {
                unlink($file);
            }
        }

        $this->template->canApproveCC = $this->jeVV();
        $uzivatele = array();
        foreach ($this->cestneClenstviUzivatele->getNeschvalene() as $cc_id => $cc_data) {
            $uzivatele[] = $cc_data->Uzivatel_id;
            $uzivatele[] = $cc_data->zadost_podal;
        }
        $uzivatele = array_unique($uzivatele);
        $this->template->uzivatele = $this->uzivatel->findBy(array("id" => $uzivatele));
    }

    protected function createComponentSpravaCCForm(): Form {
        $form = new Form($this, "spravaCCForm");

        $rights = $form->addDynamic('rights', function (Container $right) {
            $right->addHidden('Uzivatel_id')->setAttribute('class', 'id ip');
            $right->addHidden('id')->setAttribute('class', 'id ip');

            $right->addDate('plati_od', 'Platnost od:')
                 ->addRule(Form::FILLED, 'Vyberte datum');

            $right->addDate('plati_do', 'Platnost do:');

            $right->addTextArea('poznamka', 'Poznámka:', 72, 5)
                 ->setAttribute('class', 'note ip');

            $schvalenoStates = array(
                0 => 'Nerozhodnuto',
                1 => 'Schváleno',
                2 => 'Zamítnuto');
            $right->addRadioList('schvaleno', 'Stav schválení: ', $schvalenoStates)
                  ->getSeparatorPrototype()->setName("span")->style('margin-right', '7px');

            $right->addHidden('zadost_podal');
            $right->addHidden('zadost_podana');
        }, 0, false);

        $form->addSubmit('save', 'Uložit')
             ->setAttribute('class', 'btn btn-success btn-xs btn-white');

        $form->onSuccess[] = array($this, 'spravaCCFormSucceded');

        $submitujeSe = ($form->isAnchored() && $form->isSubmitted() !== false);
        if (!$submitujeSe) {
            foreach ($this->cestneClenstviUzivatele->getNeschvalene() as $rights_id => $rights_data) {
                $rights->createOne($rights_id)->setValues($rights_data);
            }
        }

        return $form;
    }

    /**
    * Schválení čestného členství
    * @param ArrayHash<mixed>&object{rights: ArrayHash<ArrayHash<mixed>>} $values
    */
    public function spravaCCFormSucceded(Form $form, ArrayHash $values): void {
        $this->vyzadujOpravneni($this->jeVV());

        $log = array();
        $prava = $values->rights;

        $podklady = [];
        foreach ($prava as $key => $pravo) {
            if (in_array($pravo->id, [null, '', 0, '0'], true)) {
                continue;
            }
            $starePravo = $this->cestneClenstviUzivatele->getCC($pravo->id);
            if ($starePravo === null) {
                $this->error('Čestné členství nebylo nalezeno.');
            }
            $navrhovatel = $this->uzivatel->getUzivatel($pravo->zadost_podal);
            $schvaleny = $this->uzivatel->getUzivatel($pravo->Uzivatel_id);
            if ($navrhovatel === null || $schvaleny === null) {
                $this->error('Uživatel nebyl nalezen.');
            }
            $podklady[$key] = [$starePravo, $navrhovatel, $schvaleny];
        }

        // Zpracujeme prava až po ověření všech podkladů.
        foreach ($prava as $key => $pravo) {
            $pravoId = $pravo->id;

            if (in_array($pravo->schvaleno ?? null, [null, 0, '0'], true)) {
                $pravo->schvaleno = 0;
            }

            if (!in_array($pravo->id, [null, '', 0, '0'], true)) {
                [$starePravo, $navrhovatel, $schvaleny] = $podklady[$key];
                $this->cestneClenstviUzivatele->update($pravoId, $pravo);

                if ($starePravo->schvaleno !== $pravo->schvaleno && ($pravo->schvaleno === 1 || $pravo->schvaleno === 2)) {
                    $this->oznamRozhodnuti($pravo, $navrhovatel, $schvaleny);
                }
            }
        }

        $this->redirect('SpravaCc:schvalovanicc');
    }

    /**
     * Rozhodnutí se dozví navrhovatel i člen. Komu oznámení nedošlo, to napíšeme schvalujícímu:
     * neplatná adresa ani výpadek pošty nesmí zastavit zpracování dalších žádostí formuláře.
     * @param ArrayHash<mixed> $pravo
     */
    private function oznamRozhodnuti(ArrayHash $pravo, ActiveRow $navrhovatel, ActiveRow $schvaleny): void {
        $stav = $pravo->schvaleno === 1 ? 'schválena' : 'zamítnuta';
        $adresy = [];
        $bezAdresy = [];
        foreach ([$navrhovatel, $schvaleny] as $osoba) {
            if ($this->maPlatnyEmail($osoba)) {
                $adresy[] = $osoba->email;
            } else {
                // Člen, který si žádost podal sám, je ve výpisu jednou, a to jako člen.
                $bezAdresy[$osoba->id] = $osoba->id === $schvaleny->id
                    ? 'sám člen'
                    : "navrhovatel $osoba->jmeno $osoba->prijmeni [$osoba->id]";
            }
        }

        if ($adresy !== []) {
            try {
                $this->mailer->send($this->zpravaORozhodnuti($pravo, $stav, $adresy));
            } catch (SendException $e) {
                $this->flashMessage(sprintf(
                    '%s Oznámení e-mailem se nepodařilo odeslat, výsledek je potřeba sdělit jinak. Chyba: %s',
                    $this->popisRozhodnuti($schvaleny, $stav),
                    $e->getMessage(),
                ), 'danger');
            }
        }

        if ($bezAdresy !== []) {
            $this->flashMessage(sprintf(
                '%s Oznámení e-mailem nedostal %s: v UserDB %s platnou e-mailovou adresu. Výsledek je potřeba sdělit jinak.',
                $this->popisRozhodnuti($schvaleny, $stav),
                implode(' ani ', $bezAdresy),
                count($bezAdresy) === 1 ? 'nemá' : 'nemají',
            ), 'warning');
        }
    }

    private function popisRozhodnuti(ActiveRow $schvaleny, string $stav): string {
        return "Žádost o čestné členství pro člena $schvaleny->jmeno $schvaleny->prijmeni [$schvaleny->id] je $stav.";
    }

    /**
     * @param ArrayHash<mixed> $pravo
     * @param non-empty-list<string> $adresy
     */
    private function zpravaORozhodnuti(ArrayHash $pravo, string $stav, array $adresy): Message {
        $platiOd = $pravo->plati_od->format('d.m.Y');
        $platiDo = $pravo->plati_do?->format('d.m.Y') ?? 'neomezeně';

        $mail = new Message();
        $mail->setFrom('UserDB <userdb@hkfree.org>')
            ->setSubject('Žádost o čestné členství')
            ->setBody("Dobrý den,\nbyla $stav žádost o čestné členství na dobu $platiOd - $platiDo.\nID:$pravo->Uzivatel_id\nPoznámka: $pravo->poznamka\n\n");
        foreach ($adresy as $adresa) {
            $mail->addTo($adresa);
        }

        return $mail;
    }
}
