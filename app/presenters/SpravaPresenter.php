<?php

namespace App\Presenters;

use App\Model;

/**
 * Sprava presenter.
 */
class SpravaPresenter extends BasePresenter
{
    private Model\Uzivatel $uzivatel;
    private Model\AP $ap;

    private ?string $googleMapsApiKey = null;

    public function __construct(Model\Uzivatel $uzivatel, Model\AP $ap) {
        parent::__construct();
        $this->uzivatel = $uzivatel;
        $this->ap = $ap;
    }

    public function setGoogleMapsApiKey(?string $googleMapsApiKey): void {
        $this->googleMapsApiKey = $googleMapsApiKey;
    }

    public function actionLogout(): void {
        $this->getUser()->logout();
        header("Location: https://userdb.hkfree.org/Shibboleth.sso/Logout?return=https://idp.hkfree.org/idp/logout?return=http://www.hkfree.org");
        die();
    }

    public function renderNastroje(): void {
        $this->template->canApproveCC = $this->jeVV();
        $this->template->canSeeMailList = $this->jeVV();
        $this->template->canCreateArea = $this->jeVVneboTECH();
        $this->template->canSeePayments = $this->jeVVneboTECH();
    }

    /** Schvalování ČČ, mailing list */
    protected function jeVV(): bool {
        return $this->getUser()->isInRole('VV');
    }

    /** Oblasti a AP, platby, SMS správcům, přešifrování */
    protected function jeVVneboTECH(): bool {
        return $this->getUser()->isInRole('VV') || $this->getUser()->isInRole('TECH');
    }

    /** @param int|string|null $id */
    public function actionShow($id): void {
        $this->redirect('Uzivatel:show', array('id' => $id));
    }

    public function renderMapa(): void {
        $aps = $this->ap->findAll();
        $povoleneAp = [];
        foreach ($aps as $ap) {
            if ($this->getUser()->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAP($ap->id, $this->getUser())) {
                $povoleneAp[] = $ap->id;
            }
        }
        $uzivatele = $this->uzivatel->findAll()->where('location_status IN (?, ?)', 'valid', 'approx')->where(Model\Uzivatel::AKTIVNI_CLEN);
        $uzivatele = $uzivatele->where('Ap_id', $povoleneAp); // Ap_id IN (..., ..., ...)
        $uzivatele = $uzivatele->fetchAll();
        $output = []; // klic = kombinace latitude + longitude
        foreach ($uzivatele as $uzivatel) {
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
                'li' => $this->link('Uzivatel:show', array('id' => $uzivatel->id))
            ];
            if ($uzivatel->location_status === 'approx') {
                $output[$key]['ax'] = 1;
            }
        }
        $this->template->data = json_encode(array_values($output));
        $this->template->googleMapsApiKey = $this->googleMapsApiKey;
    }
}
