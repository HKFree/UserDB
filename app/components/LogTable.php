<?php

namespace App\Components;

use Nette\Application\UI;
use Nette\Utils\Html;
use App\Model;
use Nette\Database\Table\ActiveRow;

/**
 * @property-read \Nette\Bridges\ApplicationLatte\Template $template
 * @phpstan-type ParsedColumn array{typ: 1|4, sloupec: string}|array{typ: 2, sloupec: string, ipId: numeric-string}|array{typ: 3, sloupec: string, pravoId: numeric-string}|array{typ: 5, sloupec: string, subnetId: numeric-string}
 */
class LogTable extends UI\Control
{
    private UI\Presenter $parentPresenter;
    private Model\Subnet $subnet;
    private Model\IPAdresa $ipAdresa;
    private Model\Log $log;

    public const UZIVATEL = 1;
    public const IPADRESA = 2;
    public const PRAVO = 3;
    public const AP = 4;
    public const SUBNET = 5;

    public const INSERT = "I";
    public const UPDATE = "U";
    public const DELETE = "D";

    public function __construct(UI\Presenter $parentPresenter, Model\IPAdresa $ipAdresa, Model\Subnet $subnet, Model\Log $log) {
        //parent::__construct();
        $this->parentPresenter = $parentPresenter;
        $this->ipAdresa = $ipAdresa;
        $this->subnet = $subnet;
        $this->log = $log;
    }

    /**
     * Funkce parsující věci, co se vyskytují v poli "sloupec" v DB.
     *
     * V případě Uživatele vrací "typ"=>UZIVATEL a "sloupec"
     * V případě IPAdresy vrací "typ"=>IPADRESA, "ipId" a "sloupec"
     *
     * @param string $sloupec IPAdresa.sloupec z DB
     * @return ParsedColumn|array{} Empty array when not matched.
     */
    private function parseSloupec($sloupec): array {
        $out = [];
        if (preg_match("/^uzivatel\.(.+)/i", $sloupec, $matches) === 1) {
            $out["typ"] = self::UZIVATEL;
            $out["sloupec"] = $matches[1];
        } elseif (preg_match("/^ipadresa\[(\d+)\]\.(.+)/i", $sloupec, $matches) === 1) {
            $out["typ"] = self::IPADRESA;
            $out["ipId"] = $matches[1];
            $out["sloupec"] = $matches[2];
        } elseif (preg_match("/^pravo\[(\d+)\]\.(.+)/i", $sloupec, $matches) === 1) {
            $out["typ"] = self::PRAVO;
            $out["pravoId"] = $matches[1];
            $out["sloupec"] = $matches[2];
        } elseif (preg_match("/^ap\.(.+)/i", $sloupec, $matches) === 1) {
            $out["typ"] = self::AP;
            $out["sloupec"] = $matches[1];
        } elseif (preg_match("/^subnet\[(\d+)\]\.(.+)/i", $sloupec, $matches) === 1) {
            $out["typ"] = self::SUBNET;
            $out["subnetId"] = $matches[1];
            $out["sloupec"] = $matches[2];
        }
        return ($out);
    }

    /**
     * Funkce která projde logy a pro všechny IPčka udělá seznam ipId - ipAdresa
     *
     * @param iterable<ActiveRow> $logy
     * @return array<int|string, string> pole ipId=>ipAdresa
     */
    private function getIPMapping(iterable $logy): array {
        $ipsKProhlednuti = array();
        foreach ($logy as $line) {
            if (($sloupec = $this->parseSloupec($line->sloupec)) !== [] && ($sloupec["typ"] === self::IPADRESA)) {
                $ipsKProhlednuti[$sloupec["ipId"]] = "Nenalezeno";
            }
        }

        $ipZDB = $this->ipAdresa->getIPzDB(array_keys($ipsKProhlednuti));
        $ipZDB = array_filter($ipZDB, static fn (?string $ip): bool => $ip !== null);
        $ipZLogu = $this->log->getAdvancedzLogu(array_keys($ipsKProhlednuti));
        // Bacha na poradi!
        return ($ipZDB + $ipZLogu + $ipsKProhlednuti);
    }

    /**
     * Funkce která projde logy a pro všechny Subnety udělá seznam subnetId - subnet
     *
     * @param iterable<ActiveRow> $logy
     * @return array<int|string, string> pole subnetId=>subnet
     */
    private function getSubnetMapping(iterable $logy): array {
        $subnetsKProhlednuti = array();
        foreach ($logy as $line) {
            if (($sloupec = $this->parseSloupec($line->sloupec)) !== [] && ($sloupec["typ"] === self::SUBNET)) {
                $subnetsKProhlednuti[$sloupec["subnetId"]] = "Nenalezeno";
            }
        }

        $subnetZDB = $this->subnet->getSubnetzDB(array_keys($subnetsKProhlednuti));
        $subnetZLogu = $this->log->getAdvancedzLogu(array_keys($subnetsKProhlednuti), 'subnet');
        // Bacha na poradi!
        return ($subnetZDB + $subnetZLogu + $subnetsKProhlednuti);
    }

    private function tableGetHeader(): Html {
        $logyTab = Html::el('table')->setClass('table table-striped logstable');
        $tr = $logyTab->create('tr');

        $sloupce = array('Kdy', 'Kdo', 'Co', 'Z', 'Na');
        foreach ($sloupce as $sloupec) {
            $tr->create('th')->setText($sloupec);
        }

        return ($logyTab);
    }

    private function tableGetFooter(Html &$table): Html {
        $table->create('script')->setHtml('$(\'a\').tooltip();');
        return ($table);
    }

    private function tableGetLineBegin(Html &$table, ActiveRow $line): Html {
        $tooltips = array('data-toggle' => 'tooltip', 'data-placement' => 'top');

        $tr = $table->create('tr');
        $tr->create('td')->setText($line->datum);

        $uzivatelUrl = $this->parentPresenter->link('Uzivatel:show', array('id' => $line->Uzivatel->id));
        $uzivatelA = Html::el('a')->href($uzivatelUrl)
                                  ->setText($line->Uzivatel->nick)
                                  ->setTitle('editoval z IP '.$line->ip_adresa)
                                  ->addAttributes($tooltips);
        $tr->create('td')->setHtml($uzivatelA);
        return ($tr);
    }

    /** @param ParsedColumn $rozparsovano */
    private function tableGetLineEndSimple(Html &$tr, ActiveRow $line, array $rozparsovano): void {
        $sloupec = $this->log->translateJmeno($rozparsovano["sloupec"]);
        $tr->create('td')->setText($sloupec);

        if ($sloupec === 'heslo') {
            $tr->create('td')->setText('N/A');
        } else {
            if (($line->puvodni_hodnota ?? '') === '') {
                $tr->create('td')->setText('----');
            } else {
                $tr->create('td')->setText($line->puvodni_hodnota);
            }

            if (($line->nova_hodnota ?? '') === '') {
                $tr->create('td')->setText('----');
            } else {
                $tr->create('td')->setText($line->nova_hodnota);
            }
        }
    }

    /** @param iterable<ActiveRow> $logy */
    private function tableProcessLines(Html &$table, iterable $logy): void {
        $ipVsechny = $this->getIPMapping($logy);
        $subnetyVsechny = $this->getSubnetMapping($logy);

        $last = null;

        $toSkip = array("uzivatel_id", "ap_id");
        $toSkipString = implode("|", $toSkip);

        foreach ($logy as $key => $line) {
            if (($rozparsovano = $this->parseSloupec($line->sloupec)) === []) {
                continue;
            }

            if (preg_match("/^heslo/i", $rozparsovano["sloupec"]) === 1) {
                continue;
            }

            if ($rozparsovano["typ"] === self::UZIVATEL) {
                $tr = $this->tableGetLineBegin($table, $line);
                $this->tableGetLineEndSimple($tr, $line, $rozparsovano);
            } elseif ($rozparsovano["typ"] === self::AP) {
                $tr = $this->tableGetLineBegin($table, $line);
                $this->tableGetLineEndSimple($tr, $line, $rozparsovano);
            } elseif ($rozparsovano["typ"] === self::IPADRESA) {
                $id = $rozparsovano["ipId"];
                $sloupec = $rozparsovano["sloupec"];
                $presun = $sloupec === 'Uzivatel_id' && $line->akce === self::UPDATE;
                if (!$presun && preg_match("/(".$toSkipString.")/i", $sloupec) === 1) {
                    continue;
                }
                $sloupec = $this->log->translateJmeno($sloupec);

                $datum = (string) $line->datum;
                $titulek = "IP ".$ipVsechny[$id];

                if ($last !== null && $last['datum'] === $datum && $last['titulek'] === $titulek) {
                    $text = "";
                    if ($line->akce === self::INSERT) {
                        $text = $sloupec." = ".$line->nova_hodnota.", ";
                    } elseif ($line->akce === self::DELETE && ($line->puvodni_hodnota ?? '') !== '') {
                        $text = $sloupec." = ".$line->puvodni_hodnota.", ";
                    } elseif ($line->akce === self::UPDATE) {
                        $text = $sloupec." z ".$line->puvodni_hodnota." na ".$line->nova_hodnota.", ";
                    }
                    $last['bunka']->setText($last['bunka']->getText().$text);
                } else {
                    $tr = $this->tableGetLineBegin($table, $line);

                    $tr->create('td')->setText($titulek);

                    $text = "";
                    if ($line->akce === self::INSERT) {
                        $text = "Založení IP Adresy s parametry ".$sloupec." = ".$line->nova_hodnota.", ";
                    } elseif ($line->akce === self::DELETE) {
                        $text = "IP Adresa byla smazána. Parametry byly ".$sloupec." = ".$line->puvodni_hodnota.", ";
                    } elseif ($line->akce === self::UPDATE) {
                        $text = "Změna ".$sloupec." z ".$line->puvodni_hodnota." na ".$line->nova_hodnota.", ";
                    }

                    $last = ['datum' => $datum, 'titulek' => $titulek, 'bunka' => $tr->create('td')->setText($text)->setColspan(2)];
                }
            } elseif ($rozparsovano["typ"] === self::SUBNET) {
                $id = $rozparsovano["subnetId"];
                $sloupec = $rozparsovano["sloupec"];
                if (preg_match("/(".$toSkipString.")/i", $sloupec) === 1) {
                    continue;
                }
                $sloupec = $this->log->translateJmeno($sloupec);

                $datum = (string) $line->datum;
                $titulek = "Subnet ".$subnetyVsechny[$id];

                if ($last !== null && $last['datum'] === $datum && $last['titulek'] === $titulek) {
                    $text = "";
                    if ($line->akce === self::INSERT) {
                        $text = $sloupec." = ".$line->nova_hodnota.", ";
                    } elseif ($line->akce === self::DELETE && ($line->puvodni_hodnota ?? '') !== '') {
                        $text = $sloupec." = ".$line->puvodni_hodnota.", ";
                    } elseif ($line->akce === self::UPDATE) {
                        $text = $sloupec." z ".$line->puvodni_hodnota." na ".$line->nova_hodnota.", ";
                    }
                    $last['bunka']->setText($last['bunka']->getText().$text);
                } else {
                    $tr = $this->tableGetLineBegin($table, $line);

                    $tr->create('td')->setText($titulek);

                    $text = "";
                    if ($line->akce === self::INSERT) {
                        $text = "Založení subnetu s parametry ".$sloupec." = ".$line->nova_hodnota.", ";
                    } elseif ($line->akce === self::DELETE) {
                        $text = "Subnet byl smazán. Parametry byly ".$sloupec." = ".$line->puvodni_hodnota.", ";
                    } elseif ($line->akce === self::UPDATE) {
                        $text = "Změna ".$sloupec." z ".$line->puvodni_hodnota." na ".$line->nova_hodnota.", ";
                    }

                    $last = ['datum' => $datum, 'titulek' => $titulek, 'bunka' => $tr->create('td')->setText($text)->setColspan(2)];
                }
            }
        }
    }

    /** @param int|string $id */
    public function render($id, string $type = "user"): void {
        $template = $this->template;
        $template->setFile(__DIR__ . '/LogTable.latte');

        $tabulka = $this->tableGetHeader();
        if ($type === "user") {
            $logy = $this->log->getLogyUzivatele($id);
        } elseif ($type === "ap") {
            $logy = $this->log->getLogyAP($id);
        } else {
            die('Spatne pouziti komponenty LogTable.');
        }

        $this->tableProcessLines($tabulka, $logy);
        $this->tableGetFooter($tabulka);

        // vložíme do šablony nějaké parametry
        $template->tabulka = $tabulka;

        // a vykreslíme ji
        $template->render();
    }
}
