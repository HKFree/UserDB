<?php

namespace App\Model;

use App\Services\CryptoSluzba;
use Nette;
use Nette\Utils\Html;
use Defuse\Crypto\Key;
use Defuse\Crypto\Crypto;

/**
 * @author
 * @phpstan-type SubnetOwner array{ipTitle: string, rowClass?: string, id: int, canViewOrEdit: bool, link: string, editLink: string, type: 'Uzivatel', nick: string|null}|array{ipTitle: string, rowClass?: string, id: int, canViewOrEdit: bool, link: string, editLink: string, type: 'Ap', jmeno: string}|array{ipTitle: string, rowClass?: string, canViewOrEdit: false, type: 'unknown'}
 */
class IPAdresa extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'IPAdresa';

    /**
     * @var string
     */
    protected $igw1IpCheckerUrl;

    /**
     * @var string
     */
    protected $igw2IpCheckerUrl;

    private CryptoSluzba $cryptoService;

    public function __construct(string $igw1IpCheckerUrl, string $igw2IpCheckerUrl, CryptoSluzba $cryptoService, Nette\Database\Context $db, Nette\Security\User $user) {
        parent::__construct($db, $user);
        $this->igw1IpCheckerUrl = $igw1IpCheckerUrl;
        $this->igw2IpCheckerUrl = $igw2IpCheckerUrl;
        $this->cryptoService = $cryptoService;
    }

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getSeznamIPAdres(): Nette\Database\Table\Selection {
        return ($this->findAll());
    }

    /** @param array<array-key, mixed> $by */
    public function findIp(array $by): ?Nette\Database\Table\ActiveRow {
        return ($this->findOneBy($by));
    }

    /**
     * @param string $prefix
     * @return array<string, Nette\Database\Table\ActiveRow>
     */
    public function getSeznamIPAdresZacinajicich($prefix): array {
        return $this->findAll()->where("ip_adresa LIKE ?", $prefix.'%')->fetchPairs('ip_adresa');
    }

    /** @param int|string|null $id */
    public function getIPAdresa($id): ?Nette\Database\Table\ActiveRow {
        return $id === null ? null : $this->find($id);
    }

    /**
     * @param string $ip
     * @param int|string|null $id
     */
    public function getDuplicateIP($ip, $id): ?string {
        $existujici = $this->findAll()->where('ip_adresa = ?', $ip)->where('id != ?', $id)->fetch();
        if ($existujici !== null) {
            return $existujici->ip_adresa;//$existujici->ref('Uzivatel', 'Uzivatel_id')->id;
        }
        return null;
    }

    /**
     * Párová metoda k \App\Model\Log::getAdvancedzLogu(), Vrati seznam idIp -> ipAdresa
     *
     * @param array<array-key, int|string> $ids ipId pro které chceme zjistit ipAdresy
     * @return array<int, string|null> pole ipId=>ipAdresa
     */
    public function getIPzDB(array $ids) {
        return ($this->getTable()->where("id", $ids)->fetchPairs("id", "ip_adresa"));
    }

    /** @param array<array-key, int|string> $ips */
    public function deleteIPAdresy(array $ips): int|true {
        if (count($ips) > 0) {
            return ($this->delete(array('id' => $ips)));
        } else {
            return true;
        }
    }

    /** @param string|null $ip */
    public function validateIP($ip): string|false {
        return (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4));
    }

    /** @param array<int, string> $typyZarizeni */
    public function getIPForm(Nette\Forms\Container &$ip, array $typyZarizeni, bool $apMode = false): void {
        $ip->addHidden('id')->setAttribute('class', 'id ip');
        $ip->addText('ip_adresa', 'IP Adresa', 11)->setAttribute('class', 'ip_adresa ip')->setAttribute('placeholder', 'IP Adresa');
        $ip->addText('hostname', 'Hostname', 11)->setAttribute('class', 'hostname ip')->setAttribute('placeholder', 'Hostname');
        $ip->addSelect('TypZarizeni_id', 'Typ Zařízení', $typyZarizeni)->setAttribute('class', 'TypZarizeni_id ip')->setPrompt('--Vyberte--');
        ;
        $ip->addCheckbox('internet', 'Internet')->setAttribute('class', 'internet ip')->setDefaultValue(1);
        $ip->addCheckbox('smokeping', 'Smokeping')->setAttribute('class', 'smokeping ip');
        $ip->addText('login', 'Login', 11)->setAttribute('class', 'login ip')->setAttribute('placeholder', 'Login');
        $ip->addText('heslo', 'Heslo', 11)->setAttribute('class', 'heslo ip')->setAttribute('placeholder', 'Heslo');
        $ip->addText('mac_adresa', 'MAC Adresa', 24)->setAttribute('class', 'mac_adresa ip')->setAttribute('placeholder', 'MAC Adresa');
        $ip->addCheckbox('mac_filter', 'MAC Filtr')->setAttribute('class', 'mac_filter ip');
        $ip->addCheckbox('dhcp', 'DHCP')->setAttribute('class', 'dhcp ip');
        if ($apMode) {
            $ip->addCheckbox('wewimo', 'Wewimo')->setAttribute('class', 'wewimo ip');
        }
        $ip->addText('popis', 'Popis')->setAttribute('class', 'popis ip')->setAttribute('placeholder', 'Popis');
    }

    /**
     * @param iterable<Nette\Database\Table\ActiveRow> $ips zaznamy z tabulky IpAdresa
     * @param bool $canViewCredentialsOrEdit zda vygenerovat tabulku, kde budou zobrazena hesla a odkazy primo
     * 							na editaci dane IP (at uz do AP nebo Uzivatele, podle toho, kde je pouzita)
     * @param array<string, string> $subnetLinks asoc. pole ip -> odkaz do subnet zobrazeni (do tabulky s IP adresami v danem C subnetu)
     * @param array<string, string|null> $wewimoLinks asoc. pole ip -> odkaz do wewimo zobrazeni
     * @param string|null $editLink link na editaci AP nebo Uzivatele, pokud zobrazujeme tabulku v detailu APcka nebo Uzivatele, jinak null
     * @param bool|int|string|null $igwCheck
     * @param (callable(string, array<string, mixed>): string)|null $linker
     */
    public function getIPTable($ips, $canViewCredentialsOrEdit, $subnetLinks, $wewimoLinks, $editLink = null, $igwCheck = false, $linker = null): Html {
        $adresyTab = Html::el('table')->setClass('table table-striped');

        $this->addIPTableHeader($adresyTab, $canViewCredentialsOrEdit, false, $igwCheck);

        foreach ($ips as $ip) {
            $subnetLink = $subnetLinks[$ip->ip_adresa];
            $wewimoLink = $wewimoLinks[$ip->ip_adresa];
            $this->addIPTableRow($ip, $canViewCredentialsOrEdit, $adresyTab, null, $subnetLink, $wewimoLink, $editLink, $igwCheck, $linker);
        }

        return $adresyTab;
    }

    /** @param bool|int|string|null $igwCheck */
    public function addIPTableHeader(Html $adresyTab, bool $canViewCredentialsOrEdit = false, bool $subnetMode = false, $igwCheck = false): void {
        $tr = $adresyTab->create('tr');

        $tr->create('th')->setText('IP');
        if (!in_array($igwCheck, [null, false, 0, '', '0'], true)) {
            $tr->create('th')->setText('IGW');
        }
        $tr->create('th')->setText(''); // action buttons
        if ($subnetMode) {
            $tr->create('th')->setText('UID');
            $tr->create('th')->setText('Nick');
        }
        $tr->create('th')->setText('Hostname');
        $tr->create('th')->setText('MAC Adresa');
        $tr->create('th')->setText('Zařízení');
        $tr->create('th')->setText('Atributy');
        $tr->create('th')->setText('SSID');
        $tr->create('th')->setText('Popis');
        if ($canViewCredentialsOrEdit) {
            $tr->create('th')->setText('Login');
            $tr->create('th')->setText('Heslo');
        }
        if ($subnetMode) {
            $tr->create('th')->setText('Subnet');
        }
    }

    /**
     * @param Nette\Utils\Html $tr
     * @param  Nette\Utils\Html[] $buttons
     */
    private function addActionButtonsTd($tr, $buttons): void {
        $td = $tr->create('td');
        foreach ($buttons as $b) {
            $td->addHtml($b);
        }
    }

    public function file_get_contents_curl(string $url): string|false {
        if ($url === '') {
            return false;
        }
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $data = curl_exec($ch);
        curl_close($ch);

        return is_string($data) ? $data : false;
    }

    /**
     * @param SubnetOwner|array{ipTitle: string, ipAdresa: string, rowClass?: string}|array{}|null $subnetModeInfo
     * @param bool|int|string|null $igwCheck
     * @param (callable(string, array<string, mixed>): string)|null $linker
     */
    public function addIPTableRow(?Nette\Database\Table\ActiveRow $ip, bool $canViewCredentialsOrEdit, Html $adresyTab, ?array $subnetModeInfo = null, ?string $subnetLink = null, ?string $wewimoLink = null, ?string $editLink = null, $igwCheck = false, $linker = null): Html {
        $tooltips = array('data-toggle' => 'tooltip', 'data-placement' => 'top');

        $isSubnetMode = $subnetModeInfo !== null && $subnetModeInfo !== [];
        $ipAddress = $ip !== null ? $ip->ip_adresa : ($subnetModeInfo['ipAdresa'] ?? throw new Nette\InvalidArgumentException('Chybí IP adresa řádku.'));
        $ipTitle = $isSubnetMode ? $subnetModeInfo['ipTitle'] : $ipAddress;

        $tr = $adresyTab->create('tr');
        $tr->setId('highlightable-ip'.$ipAddress);
        if ($isSubnetMode && array_key_exists('rowClass', $subnetModeInfo)) {
            $tr->setClass($subnetModeInfo['rowClass']);
        }

        if (!in_array($subnetLink, [null, '', '0'], true)) {
            // IP jako link do subnets z beznych agend (uzivatel, AP)
            $tr->create('td')->create('a')->setHref($subnetLink)->setTarget('_blank')->setText($ipTitle);
        } else {
            // zobrazit IP jen jako text
            $tr->create('td')->setText($ipTitle);
        }

        if (!in_array($igwCheck, [null, false, 0, '', '0'], true) && $ip !== null) {
            $igw1resp = $this->file_get_contents_curl($this->igw1IpCheckerUrl.$ip->ip_adresa);
            if ($igw1resp !== false && strpos($igw1resp, $ip->ip_adresa.' 1') !== false) {
                $attr = $tr->create('td');
                $attr->create('span')
                    ->setClass('glyphicon glyphicon-ok')
                    ->setTitle('IP je povolená do internetu na IGW 1')
                    ->addAttributes($tooltips);
            } else {
                $attr = $tr->create('td');
                $attr->create('span')
                    ->setClass('glyphicon glyphicon-remove')
                    ->setTitle('IP není povolená do internetu na IGW 1')
                    ->addAttributes($tooltips);
            }
            /*$igw2resp = $this->file_get_contents_curl($this->igw2IpCheckerUrl.$ip->ip_adresa);
            if (strpos($igw2resp, $ip->ip_adresa.' 1') !== false) {
                $attr = $tr->create('td');
    			$attr->create('span')
                    ->setClass('glyphicon glyphicon-ok')
                    ->setTitle('IP je povolená do internetu na IGW 2')
                    ->addAttributes($tooltips);
            }
            else
            {
                $attr = $tr->create('td');
    			$attr->create('span')
                    ->setClass('glyphicon glyphicon-remove')
                    ->setTitle('IP není povolená do internetu na IGW 2')
                    ->addAttributes($tooltips);
            }*/
        }

        if ($ip !== null) {
            $subnetOwnerInfo = isset($subnetModeInfo['type']) ? $subnetModeInfo : null;
            if ($isSubnetMode && $subnetOwnerInfo === null) {
                throw new Nette\InvalidArgumentException('U obsazené IP adresy chybí vlastník.');
            }
            if ($subnetOwnerInfo !== null && $subnetOwnerInfo['type'] === 'unknown') {
                $tr->create('td'); // akce
                $tr->create('td')->setColspan(2)->setText('Obsazeno, vlastník neznámý');
                for ($column = 0; $column < ($canViewCredentialsOrEdit ? 8 : 6); $column++) {
                    $tr->create('td');
                }
                return $tr;
            }
            $ip_password = '';
            if ($canViewCredentialsOrEdit) {
                if ($ip->heslo_sifrovane === 1) {
                    $ip_password = $this->cryptoService->decrypt($ip->heslo);
                } else {
                    $ip_password = $ip->heslo;
                }
            }

            // action buttons
            $buttons = array();

            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            $isAndroid = (stripos($userAgent, 'android') !== false);
            $isWindows = (stripos($userAgent, 'windows') !== false);

            // web button
            $typZarizeni = isset($ip->TypZarizeni->text) ? $ip->ref('TypZarizeni') : null;
            if ($typZarizeni !== null && $typZarizeni->id !== 1) {
                $webButton = Html::el('a')
                    ->setHref('http://' . $ip->ip_adresa)
                    ->setTarget('_blank')
                    ->setClass('btn btn-default btn-xs btn-in-table')
                    ->setTitle('Otevřít web')
                    ->addAttributes($tooltips)
                    ->addHtml(Html::el('span')
                        ->setClass('glyphicon glyphicon-globe')); // web button
                if (($canViewCredentialsOrEdit || ($subnetOwnerInfo !== null && $subnetOwnerInfo['canViewOrEdit']))
                        && isset($ip->TypZarizeni->text) && isset($ip_password) && preg_match('/routerboard/i', $ip->TypZarizeni->text) === 1) {
                    // routerboard, smim videt heslo a heslo je vyplneno
                    // -> otevrit primo zalogovany webfig
                    $webButton->setOnclick('return openMikrotikWebfig('.json_encode($ip->ip_adresa).','.json_encode($ip->login).','.json_encode($ip_password).')');
                    $webButton->setTitle('Otevřít Mikrotik WebFig');
                }
                $buttons[] = $webButton;
            }
            // ping button
            if (isset($ip->ip_adresa)) {
                $webButton = Html::el('a')
                    ->setHref('http://sojka.hkfree.org/grafana/d/H4wLPgJmk/hkfree-pinger?orgId=1&var-ip=' . $ip->ip_adresa)
                    ->setTarget('_blank')
                    ->setClass('btn btn-default btn-xs btn-in-table')
                    ->setTitle('Otevřít ping')
                    ->addAttributes($tooltips)
                    ->addHtml(Html::el('span')
                        ->setClass('glyphicon glyphicon-dashboard'));
                $buttons[] = $webButton;
            }
            // signal grafana button
            if (isset($ip->w_client_mac)) {
                $signalGrafanaButton = Html::el('a')
                    ->setHref('http://sojka.hkfree.org/grafana/d/dKlgTs6mk/wewimo?orgId=1&from=now-20d&to=now&refresh=1h&var-Station_MAC=' . $ip->w_client_mac)
                    ->setTarget('_blank')
                    ->setClass('btn btn-default btn-xs btn-in-table')
                    ->setTitle('Otevřít graf signálu')
                    ->addAttributes($tooltips)
                    ->addHtml(Html::el('span')
                        ->setClass('glyphicon glyphicon-signal'));
                $buttons[] = $signalGrafanaButton;
            }
            // winbox button (windows)
            if ($isWindows && isset($ip->TypZarizeni->text) && preg_match('/routerboard/i', $ip->TypZarizeni->text) === 1) {
                $link = 'winbox:'.$ip->ip_adresa;
                if ($canViewCredentialsOrEdit) {
                    $link .= ';'.$ip->login.';'.$ip_password;
                }
                $winboxButton = Html::el('a')
                    ->setHref($link)
                    ->setTitle('Otevřít Mikrotik Winbox')
                    ->addAttributes($tooltips)
                    ->setClass('btn btn-default btn-xs btn-in-table')
                    ->addHtml(Html::el('span')
                        ->setClass('glyphicon glyphicon-cog')); // winbox button
                $buttons[] = $winboxButton;
                $buttons[] = Html::el('a')
                                ->addHtml(Html::el('sup')->setText('?'))
                                ->setTarget('_blank')
                                ->setTitle('Jak zprovoznit otevření Winboxu z prohlížeče?')
                                ->addAttributes($tooltips)
                                ->setHref('http://wiki.hkfree.org/Winbox_URI');
            }
            // winbox cmd button
            if ($linker !== null && $isWindows && isset($ip->TypZarizeni->text) && preg_match('/routerboard/i', $ip->TypZarizeni->text) === 1) {
                $winboxButton = Html::el('span')
                    ->setAttribute('href', $linker("File:downloadWinboxCmd", ['id' => $ip->id]))
                    ->setTitle('Otevřít Mikrotik Winbox z CMD')
                    ->addAttributes($tooltips)
                    ->setClass('wboxbutton btn btn-default btn-xs btn-in-table')
                    ->addHtml(Html::el('span')
                        ->setClass('glyphicon glyphicon-download-alt')); // winbox button
                if ($canViewCredentialsOrEdit) {
                    $winboxButton->setAttribute('tag', $ip_password);
                }
                $buttons[] = $winboxButton;
                $buttons[] = Html::el('a')
                                ->addHtml(Html::el('sup')->setText('?'))
                                ->setTarget('_blank')
                                ->setTitle('Jak zprovoznit otevření Winboxu z CMD?')
                                ->addAttributes($tooltips)
                                ->setHref('http://wiki.hkfree.org/Winbox_CMD');
            }
            // winbox button (android)
            if ($isAndroid && isset($ip->TypZarizeni->text) && preg_match('/routerboard/i', $ip->TypZarizeni->text) === 1) {
                $link = 'winbox://'.$ip->ip_adresa;
                if ($canViewCredentialsOrEdit) {
                    $link .= '/'.$ip->login.'/'.$ip_password;
                }
                $winboxButton = Html::el('a')
                    ->setHref($link)
                    ->setTitle('Otevřít MikroTik app')
                    ->addAttributes($tooltips)
                    ->setClass('btn btn-default btn-xs btn-in-table')
                    ->addHtml(
                        Html::el('img')
                        ->src('../../images/mikrotik-app-logo.png')
                        ->addAttributes(array('width' => '16', 'height' => '16'))
                        ->setClass('mikrotik-app-logo')
                    );
                $buttons[] = $winboxButton;
                $buttons[] = Html::el('a')
                                ->addHtml(Html::el('sup')->setText('?'))
                                ->setTarget('_blank')
                                ->setTitle('Jak zprovoznit otevření MikroTik app na Androidu?')
                                ->addAttributes($tooltips)
                                ->setHref('http://wiki.hkfree.org/Winbox_URI');
            }

            // ssh button
            if (isset($ip->TypZarizeni->text) && preg_match('/routerboard/i', $ip->TypZarizeni->text) === 1) {
                $winboxButton = Html::el('span')
                    ->setTitle('Příkaz pro SSH spojení do schránky')
                    ->addAttributes($tooltips)
                    ->setClass('wboxbutton btn btn-default btn-xs btn-in-table')
                    ->addHtml(Html::el('span')
                        ->setClass('glyphicon glyphicon-lock')); // winbox button
                if ($canViewCredentialsOrEdit) {
                    $winboxButton->setAttribute('tag', ' sshpass -p '.$ip_password.' ssh '.$ip->login.'@'.$ip->ip_adresa);
                }
                $buttons[] = $winboxButton;
            }
            // edit button, etc.
            if ($subnetOwnerInfo !== null) {
                if ($subnetOwnerInfo['type'] === 'Uzivatel') {
                    if ($subnetOwnerInfo['canViewOrEdit']) {
                        array_unshift(
                            $buttons,
                            Html::el('a')
                                ->setHref($subnetOwnerInfo['editLink'])
                                ->setClass('btn btn-default btn-xs btn-in-table')
                                ->setTitle('Editovat')
                                ->addAttributes($tooltips)
                                ->addHtml(Html::el('span')
                                    ->setClass('glyphicon glyphicon-pencil'))
                        ); // edit button
                    } // jinak nema edit button
                    $this->addActionButtonsTd($tr, $buttons);
                    $tr->create('td')->create('a')->setHref($subnetOwnerInfo['link'])->setText($subnetOwnerInfo['id']); // UID
                    $tr->create('td')->setText($subnetOwnerInfo['nick']); // nick
                } elseif ($subnetOwnerInfo['type'] === 'Ap') {
                    if ($subnetOwnerInfo['canViewOrEdit']) {
                        array_unshift(
                            $buttons,
                            Html::el('a')
                            ->setHref($subnetOwnerInfo['editLink'])
                            ->setClass('btn btn-default btn-xs btn-in-table')
                            ->setTitle('Editovat')
                            ->addAttributes($tooltips)
                            ->addHtml(Html::el('span')
                                ->setClass('glyphicon glyphicon-pencil'))
                        ); // edit button
                    } // jinak nema edit button
                    $this->addActionButtonsTd($tr, $buttons);
                    // nazev AP + cislo (pres 2 bunky, aby se to nepletlo s UID+nick)
                    $tr->create('td')->setColspan(2)->create('a')->setHref($subnetOwnerInfo['link'])->setText($subnetOwnerInfo['jmeno'] . ' (' . $subnetOwnerInfo['id'] . ')');
                }
            } else {
                if ($canViewCredentialsOrEdit && !in_array($editLink, [null, '', '0'], true)) {
                    array_unshift(
                        $buttons,
                        Html::el('a')
                        ->setHref($editLink . '#ip' . $ip->ip_adresa)
                        ->setClass('btn btn-default btn-xs btn-in-table')
                        ->setTitle('Editovat')
                        ->addAttributes($tooltips)
                        ->addHtml(Html::el('span')
                            ->setClass('glyphicon glyphicon-pencil'))
                    ); // edit button
                }
                $this->addActionButtonsTd($tr, $buttons);
            }
            if ($subnetOwnerInfo === null || $subnetOwnerInfo['canViewOrEdit']) {
                $tr->create('td')->setText($ip->hostname); // hostname
                $tr->create('td')->setText($ip->mac_adresa); // MAC
                $tr->create('td')->setText((isset($ip->TypZarizeni->text)) ? $ip->TypZarizeni->text : ""); // typ zarizeni
                $attr = $tr->create('td'); // atributy
                if (!in_array($ip->internet, [0, false], true)) {
                    $attr->create('span')
                        ->setClass('glyphicon glyphicon-transfer')
                        ->setTitle('IP je povolená do internetu')
                        ->addAttributes($tooltips);
                    $attr->addHtml(' ');
                }

                if (!in_array($ip->smokeping, [0, false], true)) {
                    $attr->create('span')
                        ->setClass('glyphicon glyphicon-eye-open')
                        ->setTitle('IP je sledovaná ve smokepingu')
                        ->addAttributes($tooltips);
                    $attr->addHtml(' ');
                }

                if (!in_array($ip->dhcp, [0, false], true)) {
                    $attr->create('span')
                        ->setClass('glyphicon glyphicon-open')
                        ->setTitle('IP se exportuje do DHCP')
                        ->addAttributes($tooltips);
                    $attr->addHtml(' ');
                }

                if (!in_array($ip->mac_filter, [0, false], true)) {
                    $attr->create('span')
                        ->setClass('glyphicon glyphicon-filter')
                        ->setTitle('IP exportuje do MAC filteru')
                        ->addAttributes($tooltips);
                    $attr->addHtml(' ');
                }

                if (!in_array($ip->wewimo, [0, false], true)) {
                    $attr->create('span')
                        ->setClass('glyphicon glyphicon-signal')
                        ->setTitle('Zobrazovat signály klientů ve Wewimo')
                        ->addAttributes($tooltips);
                    $attr->addHtml(' ');
                }
                // ssid
                if (!in_array($wewimoLink, [null, '', '0'], true)) {
                    // SSID jako link do wewimo
                    $wewimoTag = $tr->create('td')->create('a')
                        ->setHref($wewimoLink)
                        ->setTarget('_blank');
                } else {
                    // zobrazit SSID jen jako text,
                    // TOHLE BY ASI NEMELO PRAKTICKY NASTAT,
                    // leda se napr. smaze definice AP zarizeni, ale SSID zustane v zaznamu ulozeno
                    $wewimoTag = $tr->create('td');
                }
                if (!in_array($ip->w_ssid, [null, '', '0'], true)) {
                    $tooOld =  (strtotime($ip->w_timestamp) < strtotime('-60 days'));
                    $addInfo = $tooOld ? ", ZASTARALÉ!" : "";
                    $wewimoTag->setText($ip->w_ssid)
                        ->setTitle('Údaj z Wewima z '.$ip->w_timestamp . ", shoda podle ".$ip->w_shoda.$addInfo . ", station MAC ".$ip->w_client_mac)
                        ->addAttributes($tooltips);
                    if ($tooOld) {
                        $wewimoTag->setClass('fade-out-old');
                    }
                }
                $tr->create('td')->setText($ip->popis); // popis
                if ($canViewCredentialsOrEdit) {
                    $tr->create('td')->setText($ip->login);
                    $tr->create('td')->setText($ip_password);
                }
            } else {
                // subnet mode, nesmi videt detaily
                $tr->create('td')
                    ->create('span')
                    ->setText('---')
                    ->setTitle('Nemáte právo vidět detaily') // hostname
                    ->addAttributes($tooltips);
                $tr->create('td'); // MAC
                $tr->create('td'); // typ zarizeni
                $tr->create('td'); // atributy
                $tr->create('td'); // ssid
                $tr->create('td'); // popis
                if ($canViewCredentialsOrEdit) {
                    $tr->create('td'); // login
                    $tr->create('td'); // heslo
                }
            }
        } elseif ($isSubnetMode) {
            // empty row
            $tr->create('td');
            $tr->create('td');
            $tr->create('td');
            $tr->create('td');
            $tr->create('td');
            $tr->create('td');
            $tr->create('td');
            $tr->create('td'); // ssid
            $tr->create('td'); // popis
            if ($canViewCredentialsOrEdit) {
                $tr->create('td')->setText(''); // login
                $tr->create('td')->setText(''); // heslo
            }
        }

        return $tr;
    }

    /**
     *
     * @param string|null $ipsubnet ip address subnet
     * @return list<string> IP addresses
     */
    public function getListOfIPFromSubnet($ipsubnet) {
        $genaddresses = array();
        if ($ipsubnet !== null) {
            if (preg_match("/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])(\/([0-9]|[1-2][0-9]|3[0-2]))$/i", $ipsubnet) === 1) {
                @list($sip, $slen) = explode('/', $ipsubnet);
                if (($smin = ip2long($sip)) !== false) {
                    $smax = ($smin | (1 << (32 - (int) $slen)) - 1);
                    for ($i = $smin; $i < $smax; $i++) {
                        $genaddresses[] = $this->formatIPv4($i);
                    }
                }
            }
        }
        return $genaddresses;
    }

    /**
     *
     * @param string|null $iprange ip address range
     * @return list<string> IP addresses
     */
    public function getListOfIPFromRange($iprange) {
        $genaddresses = array();
        if ($iprange !== null) {
            if (preg_match("/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])-(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/", $iprange) === 1) {
                $temp = explode("-", $iprange);
                $QRange1 = $temp[0];
                $QRange2 = $temp[1];
                $start = ip2long($QRange1);
                $end = ip2long($QRange2);
                if ($start === false || $end === false) {
                    return [];
                }
                $range = range($start, $end);
                $genaddresses = array_map($this->formatIPv4(...), $range);
            }
        }
        return $genaddresses;
    }

    private function formatIPv4(int $address): string {
        $ip = long2ip($address);
        if ($ip === false) {
            throw new Nette\UnexpectedValueException('Nelze převést číselnou adresu na IPv4.');
        }
        return $ip;
    }

    /**
     * @param string[] $macs
     * @return array<string, Nette\Database\Table\ActiveRow> asic. pole mac => zaznam pro danou mac adresu
     */
    public function getIpsByMacsMap(array $macs) {
        return $this->getTable()->where('mac_adresa', $macs)->fetchPairs('mac_adresa');
    }

    /** @param int|string $id */
    public function getAPOfIP($id): ?int {
        $ip = $this->getIPAdresa($id);
        if ($ip === null) {
            return null;
        }
        if (!in_array($ip->Ap_id, [null, 0], true)) {
            return ($ip->Ap_id);
        } else {
            return $ip->ref('Uzivatel', 'Uzivatel_id')?->Ap_id;
        }
    }

    /**
     * @param array<array-key, string> $ips
     * @return array<string, Nette\Database\Table\ActiveRow>
     */
    public function getIpsMap(array $ips): array {
        return $this->getTable()->where('ip_adresa', $ips)->fetchPairs('ip_adresa');
    }

    /**
     * @param array<array-key, string> $macs
     * @return array<string, list<Nette\Database\Table\ActiveRow>>
     */
    public function getLastIpsForMacs(array $macs): array {
        $arrayByMac = []; // asoc. array (key=mac) of arrays (records)
        foreach ($this->getTable()->where('w_client_mac', $macs)->fetchAll() as $row) {
            $key = $row['w_client_mac'];
            if (!array_key_exists($key, $arrayByMac)) {
                $arrayByMac[$key] = [];
            }
            $arrayByMac[$key][] = $row;
        }
        return $arrayByMac;
    }

    /** @param iterable<array<string, mixed>> $wewimoDevices */
    public function updateWewimoStatsHook($wewimoDevices): void {
        foreach ($wewimoDevices as $wewimoData) {
            foreach ($wewimoData['interfaces'] as $interface) {
                $updateFields = [
                    'w_ssid' => $interface['ssid'],
                    'w_ap_IPAdresa_id' => $wewimoData['ip_id'],
                    'w_timestamp' => new \DateTime()  // now
                ];
                // Prvne projdeme stanice, kde je podle Last-IP dohledane konkretni zarizeni (IPAdresa).
                // Spoje, kde je jen 1 stanice, povazujeme by-default za paterni a podle LAST_IP u nich IP neparujeme,
                // protoze na paternich spojich se mohou objevovat LAST_IP, ktere jsou nekde mnohem dal v siti.
                $isSector = (count($interface['stations']) > 1); // Autodetekce podle poctu stanic, pokud nejsou pouzita klicova slova nize.
                // Pokud je nekde bezny sektor jen s jednim klientem, je mozne pomoci komentare #WEWIMO_SEKTOR u interfacu prepsat toto vychozi chovani.
                if (array_key_exists('comment', $interface) && preg_match('/#WEWIMO_SEKTOR/', $interface['comment']) === 1) {
                    $isSector = true;
                }
                // Pokud je nekde paterni spoj se dvema stanicemi, je mozne pomoci komentare #WEWIMO_PTP u interfacu prepsat vychozi chovani.
                if (array_key_exists('comment', $interface) && preg_match('/#WEWIMO_PTP/', $interface['comment']) === 1) {
                    $isSector = false;
                }
                if ($isSector) {
                    $updateFields['w_shoda'] = 'LAST_IP';
                    foreach ($interface['stations'] as $station) {
                        // u shody podle Last-IP doplnime MAC klienta (pod jakou MAC je tato IP videt)
                        $updateFields['w_client_mac'] = $station['mac-address'];
                        if ($station['xx-last-ip-id']) {
                            // u dane IP updatujeme data (kam je pripojena)
                            $this->update($station['xx-last-ip-id'], $updateFields);
                        }
                    }
                }
                // pro shodu podle MAC sloupec w_client_mac nenastavujeme (nema smysl, byl by shodny s MAC uvedenou rucne u dane IP)
                if (array_key_exists('w_client_mac', $updateFields)) {
                    unset($updateFields['w_client_mac']);
                }
                // Pak projdeme stanice, kde je podle MAC dohledane konkretni zarizeni (IPAdresa).
                $updateFields['w_shoda'] = 'MAC';
                foreach ($interface['stations'] as $station) {
                    if ($station['xx-mac-ip-id']) {
                        // u dane IP updatujeme data (kam je pripojena)
                        $this->update($station['xx-mac-ip-id'], $updateFields);
                    }
                }
            }
        }
    }
}
