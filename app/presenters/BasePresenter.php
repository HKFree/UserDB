<?php

namespace App\Presenters;

use App\Settings;
use Nette;
use App\Model;
use App\Services\VysledekOdeslaniEmailu;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Validators;
use Tracy\Debugger;
use Nette\Mail\IMailer;

/**
 * Base presenter for all application presenters.
 *
 * @phpstan-import-type PreskocenyPrijemce from VysledekOdeslaniEmailu
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
        if ((bool) $this->settings->fakeUser === false) {
            $this->getUser()->login($_SERVER['HTTP_UID'], null);
        } else {
            $this->getUser()->login("DBG", null);
        }
    }

    /** Adresu hlídá jen editace člena; neplatnou z databáze odmítne Nette\Mail výjimkou. */
    protected function maPlatnyEmail(ActiveRow $uzivatel): bool {
        return is_string($uzivatel->email) && Validators::isEmail($uzivatel->email);
    }

    /**
     * Zachovává význam prázdných parametrů: i 0 a "0" znamenají nezadanou hodnotu.
     * Neověřuje formát ani oprávnění; např. "00" je vyplněný parametr.
     */
    protected function maVyplnenyParametr(string $name): bool {
        return in_array($this->getParameter($name), [null, false, 0, 0.0, '', '0', []], true) === false;
    }

    protected function oznamVysledekEmailu(VysledekOdeslaniEmailu $vysledek, ActiveRow $uzivatel, string $uspech, ?VysledekOdeslaniEmailu $kopie = null): bool {
        if ($vysledek->odeslano() === false) {
            $zprava = Nette\Utils\Html::el()->addText('E-mail nebyl odeslán, člen nemá platnou e-mailovou adresu. Zadejte ji v ');
            if ($this->smiUpravitUzivatele($uzivatel)) {
                $zprava->addHtml(Nette\Utils\Html::el('a')->href($this->link('Uzivatel:edit', ['id' => $uzivatel->id]))->setText('editaci člena'));
            } else {
                $zprava->addText('editaci člena');
            }
            $this->flashMessage($zprava->addText('.'), 'danger');
            return false;
        }
        $this->flashMessage($uspech);
        $uvod = $kopie !== null && $kopie->odeslano() === false ? 'Kopie e-mailu nebyla odeslána, žádný ze správců nemá platnou e-mailovou adresu.' : '';
        $this->upozorniNaPreskocenePrijemce($this->smiUpravitUzivatele($uzivatel), $uvod, ...($kopie === null ? [$vysledek] : [$vysledek, $kopie]));
        return true;
    }

    protected function upozorniNaPreskocenePrijemce(bool $zobrazitJmena, string $uvod, VysledekOdeslaniEmailu ...$vysledky): void {
        $polozky = $this->popisPreskocenychPrijemcu($zobrazitJmena, ...$vysledky);
        if ($polozky !== []) {
            $uvod .= ($uvod !== '' ? ' ' : '') . 'Na neplatné e-mailové adresy zpráva neodešla: '
                . implode('; ', $polozky) . '. Opravte je v editaci dané osoby nebo o opravu požádejte správce její oblasti.';
        }
        if ($uvod !== '') {
            $this->flashMessage($uvod, 'warning');
        }
    }

    /**
     * Jedna položka na osobu v pořadí prvního výskytu, s upřesněním, která z jejích adres je neplatná.
     * @return list<string>
     */
    private function popisPreskocenychPrijemcu(bool $zobrazitJmena, VysledekOdeslaniEmailu ...$vysledky): array {
        $prijemci = [];
        foreach ($vysledky as $vysledek) {
            foreach ($vysledek->preskoceni as $prijemce) {
                $uid = $prijemce['uid'];
                $prijemci[$uid] ??= ['kdo' => $this->oznaceniPrijemce($prijemce, $zobrazitJmena), 'hlavni' => false, 'sekundarni' => false];
                $prijemci[$uid][$prijemce['druhaAdresa'] ? 'sekundarni' : 'hlavni'] = true;
            }
        }
        $polozky = [];
        foreach ($prijemci as $prijemce) {
            $upresneni = $prijemce['sekundarni'] ? ($prijemce['hlavni'] ? ', oba e-maily' : ', sekundární e-mail') : '';
            $polozky[] = $prijemce['kdo'] . $upresneni;
        }
        return $polozky;
    }

    /** @param PreskocenyPrijemce $prijemce */
    private function oznaceniPrijemce(array $prijemce, bool $zobrazitJmena): string {
        if ($prijemce['uid'] === $this->getIdentity()->getUid()) {
            return 'vaše vlastní';
        }
        if ($zobrazitJmena === false) {
            return 'UID ' . $prijemce['uid'];
        }
        $jmeno = trim($prijemce['jmeno'] . ' ' . $prijemce['prijmeni']);
        return ($jmeno !== '' ? $jmeno . ' ' : '') . '(UID ' . $prijemce['uid'] . ')';
    }

    /**
     * Převede syrovou hodnotu z požadavku na text, který si z ní vezme jednořádkové pole formuláře
     * (Nette\Forms\Form::DataLine): bez mezer a konců řádků na okrajích, chybějící nebo netextová hodnota je prázdná.
     * Validace nad syrovými daty formuláře (getHttpData()) tak kontroluje to, co se uloží.
     */
    protected static function oriznoutRadek(mixed $hodnota): string {
        return is_scalar($hodnota) ? Nette\Utils\Strings::trim(strtr((string) $hodnota, "\r\n", '  ')) : '';
    }

    /**
     * Obsluha formuláře nebo akce pokračuje jen s oprávněním, jinak 403.
     * Podmínka má být stejná, jakou dostává šablona: šablona formulář jen skryje, odeslat ho jde i bez ní.
     * @phpstan-assert true $smi
     */
    protected function vyzadujOpravneni(bool $smi): void {
        if ($smi === false) {
            $this->error('Nemáte oprávnění.', Nette\Http\IResponse::S403_Forbidden);
        }
    }

    /**
     * Odeslaná data obsahují tlačítko Uložit (pole save). Vlastní kontroly formuláře i jeho uložení se ptají
     * tady, aby se nedalo uložit bez kontrol. Jiné odeslání formulář jen znovu vykreslí, stejně jako přidání
     * nebo odebrání řádku.
     */
    protected function odeslanoTlacitkemUlozit(Nette\Forms\Form $form): bool {
        $data = $form->getHttpData();
        return is_array($data) && isset($data['save']);
    }

    /**
     * Kdo smí upravovat člena (canViewOrEdit na detailu a editaci člena): EXTSUPPORT, správce AP člena
     * (AP::canViewOrEditAP) a SO/ZSO, v jehož subnetech má člen z jiné oblasti IP adresu.
     */
    protected function smiUpravitUzivatele(ActiveRow|Nette\Database\Row $uzivatel): bool {
        if ($this->getUser()->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAP($uzivatel->Ap_id, $this->getUser())) {
            return true;
        }
        $oblastIds = [];
        foreach ($this->spravceOblasti->getOblastiSpravce($this->getIdentity()->getUid()) as $oblast) {
            $oblastIds[] = $oblast->id;
        }
        return $this->uzivatelModel->maIpAdresuVSubnetuCizihoAp($uzivatel->id, $oblastIds);
    }

    /**
     * Filtr nabídky na jednom AP; při zápisu se oprávnění kontroluje znovu přes smiUpravitUzivatele().
     * @param Nette\Database\Table\Selection<ActiveRow> $clenove
     * @return Nette\Database\Table\Selection<ActiveRow>
     */
    protected function omezNaUpravitelneUzivateleNaAp(Nette\Database\Table\Selection $clenove, int $apId): Nette\Database\Table\Selection {
        $clenove->where('Ap_id', $apId);
        if ($this->getUser()->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAP($apId, $this->getUser())) {
            return $clenove;
        }
        $oblastIds = [];
        foreach ($this->spravceOblasti->getOblastiSpravce($this->getIdentity()->getUid()) as $oblast) {
            $oblastIds[] = $oblast->id;
        }
        return $clenove->where('id', $this->uzivatelModel->najdiUzivateleNaApVSubnetechCizichAp($apId, $oblastIds));
    }

    protected function beforeRender() {
        parent::beforeRender();

        $this->template->demoPersonaUrl = $this->settings->demoPersonaUrl;
        $this->template->demoPersonaName = $this->settings->demoPersonaName;
        $this->template->oblasti = $this->oblast->getSeznamOblastiProMenu();

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
        $ap = $this->ap->findAPByIP($values->search)->fetch();
        if ($ap !== null) {
            $this->redirect('Ap:show', array('id' => $ap->id));
        }
        $this->redirect('UzivatelList:listall', array('search' => $values->search, 'id' => null));
    }

    /** @param string|null $ipAddress */
    protected function getSubnetLinkFromIpAddress($ipAddress): string {
        if ($ipAddress === null) {
            return '';
        }
        list($a, $b, $c, $d) = explode(".", $ipAddress);
        $resultnet = $a .".". $b .".". $c .".";
        return $this->link('Subnet:detail', array('id' => $resultnet)).'#ip'.$ipAddress;
    }

    protected function getWewimoLinkFromIpAddress(ActiveRow $ip): ?string {
        if (in_array($ip->w_ssid, [null, '', '0'], true) === false) {
            // najit IPAdresu zarizeni (AP), na ktere je tato ip (podle Wewima) pripojena
            $apAdresa = $ip->ref('IPAdresa', 'w_ap_IPAdresa_id');
            if ($apAdresa !== null) {
                // ok, mame adresu APcka, muze byt navazano na uzivatele (hodne nepravdepodobne),
                // nebo muze byt navazano na (entitu) AP
                $uzivatel = $apAdresa->ref('Uzivatel');
                $ap = $uzivatel !== null ? $uzivatel->ref('Ap') : $apAdresa->ref('Ap');
                if ($ap !== null) {
                    return $this->link('Wewimo:show', array('id' => $ap->id))."#mac:".$ip->w_client_mac;
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
        return $this->link($destination, $args);
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
