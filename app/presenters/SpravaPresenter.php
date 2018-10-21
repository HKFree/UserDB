<?php

namespace App\Presenters;

use Nette,
    App\Model,
    Nette\Utils\Json,
    Tracy\Debugger;

use Nette\Forms\Controls\SubmitButton;
/**
 * Sprava presenter.
 */
class SpravaPresenter extends BasePresenter
{
    private $uzivatel;
    private $ap;

    private $googleMapsApiKey;

    function __construct(Model\Uzivatel $uzivatel, Model\AP $ap) {
    	$this->uzivatel = $uzivatel;
        $this->ap = $ap;
    }

    public function actionLogout() {
        $this->getUser()->logout();
        header("Location: https://userdb.hkfree.org/Shibboleth.sso/Logout?return=https://idp.hkfree.org/idp/logout?return=http://www.hkfree.org");
        die();
    }

    public function renderNastroje()
    {
    	$this->template->canApproveCC = $this->getUser()->isInRole('VV');
        $this->template->canSeeMailList = $this->getUser()->isInRole('VV');
        $this->template->canCreateArea = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
        $this->template->canSeePayments = $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
    }

    public function actionShow($id) {
        $this->redirect('Uzivatel:show', array('id'=>$id));
    }

    public function setGoogleMapsApiKey($googleMapsApiKey)
    {
        $this->googleMapsApiKey = $googleMapsApiKey;
    }

    public function renderMapa()
    {
        $aps = $this->ap->findAll();
        $povoleneAp = [];
        foreach ($aps as $ap) {
            if ($this->getUser()->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAP($ap->id, $this->getUser())) {
                $povoleneAp[] = $ap->id;
            }
        }
        $uzivatele = $this->uzivatel->findAll()->where('location_status IN (?, ?)', 'valid', 'approx')->where('TypClenstvi_id > ?', 1);
        $uzivatele = $uzivatele->where('Ap_id', $povoleneAp); // Ap_id IN (..., ..., ...)
        $uzivatele = $uzivatele->fetchAll();
        $output = []; // klic = kombinace latitude + longitude
        foreach($uzivatele as $uzivatel) {
            $key = "{$uzivatel->latitude},{$uzivatel->longitude}";
            if (!isset($output[$key])) {
                // na danych souradnicich jeste zadny bod v poli $output neni
                $output[$key] = [
                    'lat' => $uzivatel->latitude,
                    'lon' => $uzivatel->longitude,
                    'us' => [], // users
                    'ax' => 0 // approximate flag
                ];
            }
            $output[$key]['us'][] = [
                'id' => $uzivatel->id,
                'ni' => $uzivatel->nick,
                'jm' => $uzivatel->jmeno,
                'pr' => $uzivatel->prijmeni,
                'li' => $this->link('Uzivatel:show', array('id'=>$uzivatel->id))
            ];
            if ($uzivatel->location_status === 'approx') {
                $output[$key]['ax'] = 1;
            }
        }
        $this->template->data = json_encode(array_values($output));
        $this->template->googleMapsApiKey = $this->googleMapsApiKey;
    }
}
