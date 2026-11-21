<?php

namespace App\Presenters;

use App\Settings;
use Nette;
use App\Model;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Tracy\Debugger;
use Nette\Mail\IMailer;

/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    /**
     * @persistent
     * @var int|string|null
     */
    public $id;

    /** @var Model\Oblast */
    public $oblast;
    private Model\SpravceOblasti $spravceOblasti;
    private Model\AP $ap;

    /** @var IMailer */
    protected $mailer;

    protected Settings $settings;

    private Model\Uzivatel $uzivatelModel;

    public function inject(Model\Oblast $oblast, Model\SpravceOblasti $spravceOblasti, Model\AP $ap, IMailer $mailer, Settings $settings): void {
        $this->oblast = $oblast;
        $this->spravceOblasti = $spravceOblasti;
        $this->ap = $ap;
        $this->mailer = $mailer;
        $this->settings = $settings;
    }

    public function injectUzivatelModel(Model\Uzivatel $uzivatel): void {
        $this->uzivatelModel = $uzivatel;
    }

    public function startup() {
        parent::startup();

        //$uri = $this->getHttpRequest()->getUrl();
        if (!(bool) $this->settings->fakeUser) {
            $this->getUser()->login($_SERVER['HTTP_UID'], null);
        } else {
            $this->getUser()->login("DBG", null);
        }
    }

    /**
     * Zachovává význam prázdných parametrů: i 0 a "0" znamenají nezadanou hodnotu.
     * Neověřuje formát ani oprávnění; např. "00" je vyplněný parametr.
     */
    protected function maVyplnenyParametr(string $name): bool {
        return !in_array($this->getParameter($name), [null, false, 0, 0.0, '', '0', []], true);
    }

    /**
     * Obsluha formuláře nebo akce pokračuje jen s oprávněním, jinak 403.
     * Podmínka má být stejná, jakou dostává šablona: šablona formulář jen skryje, odeslat ho jde i bez ní.
     * @phpstan-assert true $smi
     */
    protected function vyzadujOpravneni(bool $smi): void {
        if (!$smi) {
            $this->error('Nemáte oprávnění.', Nette\Http\IResponse::S403_Forbidden);
        }
    }

    /**
     * Kdo smí upravovat člena (canViewOrEdit na detailu a editaci člena): EXTSUPPORT, správce AP člena
     * (AP::canViewOrEditAP) a SO/ZSO, v jehož subnetech má člen z jiné oblasti IP adresu.
     */
    protected function smiUpravitUzivatele(ActiveRow $uzivatel): bool {
        if ($this->getUser()->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAP($uzivatel->Ap_id, $this->getUser())) {
            return true;
        }
        $oblastIds = [];
        foreach ($this->spravceOblasti->getOblastiSpravce($this->getIdentity()->getUid()) as $oblast) {
            $oblastIds[] = $oblast->id;
        }
        return $this->uzivatelModel->maIpAdresuVSubnetuCizihoAp($uzivatel->id, $oblastIds);
    }

    protected function beforeRender() {
        parent::beforeRender();

        $this->template->demoPersonaUrl = $this->settings->demoPersonaUrl;
        $this->template->demoPersonaName = $this->settings->demoPersonaName;
        $this->template->oblasti = $this->oblast->getSeznamOblasti();

        $oblastiSpravce = $this->spravceOblasti->getOblastiSpravce($this->getIdentity()->getUid());
        if (count($oblastiSpravce) > 0) {
            $this->template->mojeOblasti = $this->oblast->formatujOblastiSAP($oblastiSpravce);
        } else {
            $this->template->mojeOblasti = false;
        }
    }

    protected function createComponentSearchForm(): Form {
        $form = new Form();
        $form->getElementPrototype()->class('navbar-form navbar-right');
        $form->addText('search', 'Vyhledej:')->setAttribute('class', 'form-control')->setAttribute('placeholder', 'Hledat...');
        $form->addSubmit('send', 'Vyhledat');

        $form->onSuccess[] = array($this, 'searchFormSucceeded');
        return $form;
    }

    public function searchFormSucceeded(Form $form): void {
        $values = $form->getValues();
        $ipIsInAp = $this->ap->findAPByIP($values->search);
        if ($ipIsInAp->count() > 0) {
            $ap = $ipIsInAp->fetch();
            $this->redirect('Ap:show', array('id' => $ap->id));
        }
        $this->redirect('UzivatelList:listall', array('search' => $values->search, 'id' => null));
    }

    /** @param string|null $ipAddress */
    protected function getSubnetLinkFromIpAddress($ipAddress): string {
        list($a, $b, $c, $d) = explode(".", $ipAddress);
        $resultnet = $a .".". $b .".". $c .".";
        return $this->link('Subnet:detail', array('id' => $resultnet)).'#ip'.$ipAddress;
    }

    protected function getWewimoLinkFromIpAddress(ActiveRow $ip): ?string {
        if (!in_array($ip->w_ssid, [null, '', '0'], true)) {
            // najit IPAdresu zarizeni (AP), na ktere je tato ip (podle Wewima) pripojena
            $apAdresa = $ip->ref('IPAdresa', 'w_ap_IPAdresa_id');
            if ($apAdresa !== null) {
                // ok, mame adresu APcka, muze byt navazano na uzivatele (hodne nepravdepodobne),
                // nebo muze byt navazano na (entitu) AP
                if ($apAdresa->ref('Uzivatel') !== null) {
                    $apId = $apAdresa->ref('Uzivatel')->ref('Ap')->id;
                    return $this->link('Wewimo:show', array('id' => $apId))."#mac:".$ip->w_client_mac;
                } elseif ($apAdresa->ref('Ap') !== null) {
                    $apId = $apAdresa->ref('Ap')->id;
                    return $this->link('Wewimo:show', array('id' => $apId))."#mac:".$ip->w_client_mac;
                }
            }
        }
        return null; // fallback
    }

    /**
     * @param iterable<ActiveRow> $ips
     * @return array<string, string>
     */
    protected function getSubnetLinksFromIPs(iterable $ips): array {
        $result = array();
        foreach ($ips as $ip) {
            $result[$ip->ip_adresa] = $this->getSubnetLinkFromIpAddress($ip->ip_adresa);
        }
        return $result;
    }

    /**
     * @param iterable<ActiveRow> $ips
     * @return array<string, string|null>
     */
    protected function getWewimoLinksFromIPs(iterable $ips): array {
        $result = array();
        foreach ($ips as $ip) {
            $result[$ip->ip_adresa] = $this->getWewimoLinkFromIpAddress($ip);
        }
        return $result;
    }

    /**
     * Prasofunkce pro linkovani z modelu (vytvoreno pro Models\IPAdresa)
     *
     * Pouzivat co nejmene!
     *
     * @param string $destination
     * @param mixed $args
     * @return string
     */
    public function linker($destination, $args = []) {
        return ($this->link($destination, $args));
    }

    public function getIdentity(): Model\HkfIdentity {
        $i = $this->getUser()->getIdentity();
        if ($i instanceof Model\HkfIdentity) {
            return $i;
        } else {
            throw new Nette\InvalidStateException("Identity musi byt instance HkfIdentity");
        }
    }
}
