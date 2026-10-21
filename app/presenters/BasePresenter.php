<?php

namespace App\Presenters;

use App\Settings;
use Nette;
use App\Model;
use Nette\Application\UI\Form;
use Tracy\Debugger;
use Nette\Mail\IMailer;

/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    /** @persistent */
    public $id;

    public $oblast;
    private $spravceOblasti;
    private $ap;

    /** @var IMailer */
    protected $mailer;

    protected Settings $settings;

    public function inject(Model\Oblast $oblast, Model\SpravceOblasti $spravceOblasti, Model\AP $ap, IMailer $mailer, Settings $settings) {
        $this->oblast = $oblast;
        $this->spravceOblasti = $spravceOblasti;
        $this->ap = $ap;
        $this->mailer = $mailer;
        $this->settings = $settings;
    }

    public function startup() {
        parent::startup();

        //$uri = $this->getHttpRequest()->getUrl();
        if ($this->settings->fakeUser == false) {
            $this->getUser()->login($_SERVER['HTTP_UID'], null);
        } else {
            $this->getUser()->login("DBG", null);
        }
    }

    protected function beforeRender() {
        parent::__construct();
        parent::beforeRender();

        $this->template->oblasti = $this->oblast->getSeznamOblasti();

        $oblastiSpravce = $this->spravceOblasti->getOblastiSpravce($this->getIdentity()->getUid());
        if (count($oblastiSpravce) > 0) {
            $this->template->mojeOblasti = $this->oblast->formatujOblastiSAP($oblastiSpravce);
        } else {
            $this->template->mojeOblasti = false;
        }
    }

    protected function createComponentSearchForm() {
        $form = new Form();
        $form->getElementPrototype()->class('navbar-form navbar-right');
        $form->addText('search', 'Vyhledej:')->setAttribute('class', 'form-control')->setAttribute('placeholder', 'Hledat...');
        $form->addSubmit('send', 'Vyhledat');

        $form->onSuccess[] = array($this, 'searchFormSucceeded');
        return $form;
    }

    public function searchFormSucceeded(Form $form) {
        $values = $form->getValues();
        $ipIsInAp = $this->ap->findAPByIP($values->search);
        if ($ipIsInAp && $ipIsInAp->count() > 0) {
            $ap = $ipIsInAp->fetch();
            $this->redirect('Ap:show', array('id' => $ap->id));
        }
        $this->redirect('UzivatelList:listall', array('search' => $values->search, 'id' => null));
    }

    protected function getSubnetLinkFromIpAddress($ipAddress) {
        list($a, $b, $c, $d) = explode(".", $ipAddress);
        $resultnet = $a .".". $b .".". $c .".";
        return $this->link('Subnet:detail', array('id' => $resultnet)).'#ip'.$ipAddress;
    }

    protected function getWewimoLinkFromIpAddress($ip) {
        if ($ip->w_ssid) {
            // najit IPAdresu zarizeni (AP), na ktere je tato ip (podle Wewima) pripojena
            $apAdresa = $ip->ref('IPAdresa', 'w_ap_IPAdresa_id');
            if ($apAdresa) {
                // ok, mame adresu APcka, muze byt navazano na uzivatele (hodne nepravdepodobne),
                // nebo muze byt navazano na (entitu) AP
                if ($apAdresa->ref('Uzivatel')) {
                    $apId = $apAdresa->ref('Uzivatel')->ref('Ap')->id;
                    return $this->link('Wewimo:show', array('id' => $apId))."#mac:".$ip->w_client_mac;
                } elseif ($apAdresa->ref('Ap')) {
                    $apId = $apAdresa->ref('Ap')->id;
                    return $this->link('Wewimo:show', array('id' => $apId))."#mac:".$ip->w_client_mac;
                }
            }
        }
        return null; // fallback
    }

    protected function getSubnetLinksFromIPs($ips) {
        $result = array();
        foreach ($ips as $ip) {
            $result[$ip->ip_adresa] = $this->getSubnetLinkFromIpAddress($ip->ip_adresa);
        }
        return $result;
    }

    protected function getWewimoLinksFromIPs($ips) {
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
