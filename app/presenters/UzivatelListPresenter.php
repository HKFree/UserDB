<?php

namespace App\Presenters;

use Nette;
use App\Model;
use Tracy\Debugger;

/**
 * Uzivatel presenter.
 */
class UzivatelListPresenter extends BasePresenter
{
    /**
     * @persistent
     * @var int
     */
    public $money = 0;

    /** Režim „Kompletní poznámky a adresy“. Persistentní, aby ho nesly i odkazy a formuláře tabulek. */
    #[Nette\Application\Attributes\Persistent]
    public bool $fullnotes = false;

    /** Text hledaný v kompletním seznamu členů; null, když se nehledá. */
    private ?string $hledanyText = null;

    private Model\CestneClenstviUzivatele $cestneClenstviUzivatele;
    private Model\Uzivatel $uzivatel;
    private Model\AP $ap;
    private Model\UzivatelListGrid $tabulkaUzivatelu;

    public function __construct(Model\UzivatelListGrid $ULGrid, Model\CestneClenstviUzivatele $cc, Model\Uzivatel $uzivatel, Model\AP $ap) {
        parent::__construct();
        $this->cestneClenstviUzivatele = $cc;
        $this->uzivatel = $uzivatel;
        $this->ap = $ap;
        $this->tabulkaUzivatelu = $ULGrid;
    }

    public function renderList(): void {
        // otestujeme, jestli máme id APčka a ono existuje
        if ($this->maVyplnenyParametr('id') && ($apt = $this->ap->getAP($this->getParameter('id'))) !== null) {
            $id = $this->getParameter('id');
            $cestnych = count($this->cestneClenstviUzivatele->getListCCOfAP($id));
            $this->template->u_celkem = $this->uzivatel->getSeznamUzivateluZAP($id)->where("TypClenstvi_id>?", 1)->count("*");
            $this->template->u_celkemz = $this->uzivatel->getSeznamUzivateluZAP($id)->where("TypClenstvi_id>?", 0)->count("*");
            $this->template->u_aktivnich = $this->uzivatel->getSeznamUzivateluZAP($id)->where("money_aktivni=?", 1)->count("*");
            $this->template->u_zrusenych = $this->uzivatel->getSeznamUzivateluZAP($id)->where("TypClenstvi_id=?", 1)->count("*");
            $this->template->u_primarnich = $this->uzivatel->getSeznamUzivateluZAP($id)->where("TypClenstvi_id=?", 2)->count("*");
            $this->template->u_radnych = $this->uzivatel->getSeznamUzivateluZAP($id)->where("TypClenstvi_id=?", 3)->count("*") - $cestnych;
            $this->template->u_cestnych = $cestnych;

            $this->template->ap = $apt;
            $this->template->canViewOrEdit = $this->getUser()->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAP($this->getParameter('id'), $this->getUser());
        } else {
            $this->flashMessage("Chyba, AP s tímto ID neexistuje.", "danger");
            $this->redirect("Homepage:default", array("id" => null)); // a přesměrujeme
        }

        $this->template->money = $this->maVyplnenyParametr("money") && $this->template->canViewOrEdit; // přepínač jen s oprávněním
        $this->template->fullnotes = $this->fullnotes;
        $this->template->cipyStitku = $this->tabulkaUzivatelu->cipyStitku($this->tabulka());
    }

    /**
     * Hledaný text je argument akce, aby ho nesly i odkazy a formuláře tabulky: filtr, řazení, stránka a export.
     * Odkaz na pojmenovanou akci ho nenese, takže hledání končí odchodem ze stránky.
     */
    public function actionListall(?string $search = null): void {
        // Prázdný text a nula hledání nespouštějí.
        $this->hledanyText = in_array($search, [null, '', '0'], true) ? null : $search;
    }

    public function renderListall(): void {
        if ($this->hledanyText === null) {
            $cestnych = count($this->cestneClenstviUzivatele->getListCC());
            $this->template->u_celkem = $this->uzivatel->getSeznamUzivatelu()->where("TypClenstvi_id>?", 1)->count("*");
            $this->template->u_celkemz = $this->uzivatel->getSeznamUzivatelu()->where("TypClenstvi_id>?", 0)->count("*");
            $this->template->u_aktivnich = $this->uzivatel->getSeznamUzivatelu()->where("money_aktivni=?", 1)->count("*");
            $this->template->u_zrusenych = $this->uzivatel->getSeznamUzivatelu()->where("TypClenstvi_id=?", 1)->count("*");
            $this->template->u_primarnich = $this->uzivatel->getSeznamUzivatelu()->where("TypClenstvi_id=?", 2)->count("*");
            $this->template->u_radnych = $this->uzivatel->getSeznamUzivatelu()->where("TypClenstvi_id=?", 3)->count("*") - $cestnych;
            $this->template->u_cestnych = $cestnych;
        }

        $this->template->canViewOrEdit = $this->ap->canViewOrEditAll($this->getUser());
        // režim money i plné poznámky a adresy se řídí stejnou podmínkou jako grid (UzivatelListGrid pouští i EXTSUPPORT)
        $vidiUdajeClenu = $this->getUser()->isInRole('EXTSUPPORT') || $this->template->canViewOrEdit;
        $this->template->money = $this->maVyplnenyParametr("money") && $vidiUdajeClenu;
        $this->template->vidiUdajeClenu = $vidiUdajeClenu;
        $this->template->fullnotes = $this->fullnotes;
        $this->template->search = $this->hledanyText;
        $this->template->cipyStitku = $this->tabulkaUzivatelu->cipyStitku($this->tabulka());
    }

    /** Hlavní tabulka stránky; filtry nad ní kreslí šablona. */
    private function tabulka(): \Grido\Grid {
        return $this->getComponent('grid');
    }

    protected function createComponentGrid(string $name): \Grido\Grid {
        return $this->tabulkaUzivatelu->getListOfUsersGrid(
            $this,
            $name,
            $this->getUser(),
            $this->getParameter('id'),
            $this->getParameter('money', false),
            $this->fullnotes,
            $this->hledanyText
        );
    }

    protected function createComponentOthersGrid(string $name): void {
        $this->tabulkaUzivatelu->getListOfOtherUsersGrid(
            $this,
            $name,
            $this->getUser(),
            $this->getParameter('id'),
            $this->getParameter('money', false),
            $this->fullnotes,
            $this->hledanyText
        );
    }
}
