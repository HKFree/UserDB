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
    /** @persistent */
    public $money = 0;

    private $cestneClenstviUzivatele;
    private $uzivatel;
    private $ap;
    private $tabulkaUzivatelu;

    public function __construct(Model\UzivatelListGrid $ULGrid, Model\CestneClenstviUzivatele $cc, Model\Uzivatel $uzivatel, Model\AP $ap) {
        $this->cestneClenstviUzivatele = $cc;
        $this->uzivatel = $uzivatel;
        $this->ap = $ap;
        $this->tabulkaUzivatelu = $ULGrid;
    }

    public function renderList() {
        // otestujeme, jestli máme id APčka a ono existuje
        if ($this->getParameter('id') && $apt = $this->ap->getAP($this->getParameter('id'))) {
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

        $this->template->money = $this->getParameter("money", false);
        $this->template->fullnotes = $this->getParameter("fullnotes", false);
    }

    public function renderListall() {
        $search = $this->getParameter('search', false);
        if (!$search) {
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
        $this->template->money = $this->getParameter("money", false);
        $this->template->search = $this->getParameter('search', false);
    }

    protected function createComponentGrid($name) {
        $this->tabulkaUzivatelu->getListOfUsersGrid(
            $this,
            $name,
            $this->getUser(),
            $this->getParameter('id'),
            $this->getParameter('money', false),
            $this->getParameter('fullnotes', false),
            $this->getParameter('search', false)
        );
    }

    protected function createComponentOthersGrid($name) {
        $this->tabulkaUzivatelu->getListOfOtherUsersGrid(
            $this,
            $name,
            $this->getUser(),
            $this->getParameter('id'),
            $this->getParameter('money', false),
            $this->getParameter('fullnotes', false),
            $this->getParameter('search', false)
        );
    }
}
