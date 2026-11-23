<?php

namespace App\Model;

use App\Components\AppGrid;
use Nette;
use Nette\Database\Row;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Strings;
use Nette\Utils\Html;
use Latte\Engine;

/**
 * @author
 */
class UzivatelListGrid
{
    /** Typy pohybů na kontě (TypPohybuNaUctu), ze kterých se bere poslední platba a poslední aktivace. */
    private const PLATBA = [1];
    private const AKTIVACE = [4, 5];
    private const MESICNI_AKTIVACE = [4];

    private Uzivatel $uzivatel;
    private Stitek $stitek;
    private StitekUzivatele $stitekUzivatele;
    private AP $ap;
    private CestneClenstviUzivatele $cestneClenstviUzivatele;
    private Parameters $parameters;
    private UzivatelskeKonto $uzivatelskeKonto;

    public function __construct(Parameters $parameters, AP $ap, CestneClenstviUzivatele $cc, Uzivatel $uzivatel, Stitek $stitky, StitekUzivatele $stitekUzivatele, UzivatelskeKonto $uzivatelskeKonto) {
        $this->stitek = $stitky;
        $this->stitekUzivatele = $stitekUzivatele;
        $this->uzivatel = $uzivatel;
        $this->ap = $ap;
        $this->cestneClenstviUzivatele = $cc;
        $this->parameters = $parameters;
        $this->uzivatelskeKonto = $uzivatelskeKonto;
    }

    /** Datum a částka pohybu pro sloupce Poslední platba a Poslední aktivace. */
    private function popisPohybu(?Row $pohyb, bool $sVlastnikem = false): string {
        if ($pohyb === null) {
            return '?';
        }
        if ($pohyb->datum === null) {
            return 'NIKDY';
        }
        $vlastnik = $sVlastnikem ? ',' . (in_array($pohyb->druzstvo, [0, false], true) ? 'S' : 'D') : '';
        return $pohyb->datum->format('d.m.Y') . ' (' . $pohyb->castka . $vlastnik . ')';
    }

    private function addressNotice(Html $el, Nette\Database\Table\ActiveRow $item): void {
        if ($item->location_status === 'approx') {
            $el->addHtml(' <i class="fa fa-exclamation-triangle" title="Nepřesná adresa, opravte!"></i>');
        } elseif ($item->location_status === 'unknown') {
            $el->addHtml(' <i class="fa fa-times-circle" title="Neznámá adresa, opravte!"></i>');
        }
    }

    /**
     * @param int|string|null $id
     * @param bool|int|string|null $money
     * @param bool|int|string|null $fullnotes
     * @param string|false|null $search
     */
    public function getListOfOtherUsersGrid(Nette\Application\UI\Presenter $presenter, string $name, Nette\Security\User $loggedUser, $id, $money, $fullnotes, $search): \Grido\Grid {
        //\Tracy\Debugger::barDump($search);

        $canViewOrEdit = false;

        $grid = new AppGrid($presenter, $name);
        $grid->setExport('user_export');

        $apcko = $this->ap->getAP($id);
        if ($apcko === null) {
            $presenter->error('AP nebylo nalezeno.');
        }
        $subnety = $apcko->related('Subnet.Ap_id');

        $seznamUzivatelu = $this->uzivatel->findUsersFromOtherAreasByAreaId($apcko->id, $subnety);
        $seznamUzivateluCC = $this->cestneClenstviUzivatele->getListCCOfAP($apcko->id);
        $canViewOrEdit = $loggedUser->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAP($id, $loggedUser);

        $grid->setModel($seznamUzivatelu);

        $grid->setDefaultPerPage(500);
        $grid->setPerPageList(array(25, 50, 100, 250, 500, 1000));
        $grid->setDefaultSort(array('zalozen' => 'ASC'));

        $list = array('active' => 'bez zrušených, plánovaných a smazaných', 'all' => 'včetně zrušených, plánovaných a smazaných', 'planned' => 'pouze plánovaná členství ve spolku');

        $grid->addFilterSelect('TypClenstvi_id', 'Zobrazit', $list)
            ->setDefaultValue('active')
            ->setWhere(function ($value, $connection) {
                if ($value === 'active') {
                    return $connection->where(Uzivatel::AKTIVNI_CLEN);
                }
                if ($value === 'planned') {
                    return $connection->where('spolek = 1 AND TypClenstvi_id = 0');
                }
                return $connection;
            });

        if (!in_array($money, [null, false, 0, '', '0'], true) && $canViewOrEdit) { // přepínač "Informace z money" ukazuje šablona jen s oprávněním
            $thisparams = $this->parameters;
            $grid->setRowCallback(function ($item, $tr) use ($seznamUzivateluCC, $thisparams) {

                $konto = $item->related('UzivatelskeKonto.Uzivatel_id');
                if ((int) $item->money_aktivni !== 1) {
                    $tr->class[] = 'neaktivni';
                }
                if (($konto->sum('castka') - $item->kauce_mobil) > ($thisparams->getVyseClenskehoPrispevku() * 12)) {
                    $tr->class[] = 'preplatek';
                }
                if (in_array($item->id, $seznamUzivateluCC, true)) {
                    $tr->class[] = 'cestne';
                    return $tr;
                }
                // TODO: spolek / druzstvo
                if ($item->spolek && $item->TypClenstvi_id === 2) {
                    $tr->class[] = 'primarni';
                }
                return $tr;
            });
        } else {
            $grid->setRowCallback(function ($item, $tr) use ($seznamUzivateluCC) {

                if ((int) $item->email_invalid === 1) {
                    $tr->class[] = 'invalidemail';
                }
                if (in_array($item->id, $seznamUzivateluCC, true)) {
                    $tr->class[] = 'cestne';
                    return $tr;
                }
                if ($item->spolek && $item->TypClenstvi_id === 2) {
                    $tr->class[] = 'primarni';
                }
                if ($item->spolek && $item->TypClenstvi_id === 1 && (!$item->druzstvo)
                    || $item->druzstvo && $item->smazano && (!$item->spolek)
                    || $item->spolek && $item->druzstvo && $item->TypClenstvi_id === 1 && $item->smazano) {
                    $tr->class[] = 'zrusene';
                }
                if ($item->TypClenstvi_id === 0) {
                    $tr->class[] = 'planovane';
                }
                return $tr;
            });
        }

        $grid->addColumnText('id', 'UID')->setCustomRender(function ($item) use ($presenter, $canViewOrEdit) {
            $uidLink = Html::el('a')
            ->href($presenter->link('Uzivatel:show', array('id' => $item->id)))
            ->title($item->id)
            ->setText($item->id);

            if ($canViewOrEdit) {
                // edit button
                $anchor = Html::el('a')
                            ->setHref($presenter->link('Uzivatel:edit', array('id' => $item->id)))
                            ->setTitle('Editovat')
                            ->setClass('btn btn-default btn-xs btn-in-table');
                $anchor->create('span')->setClass('glyphicon glyphicon-pencil'); // inner edit icon
                $uidLink .= $anchor;
            }

            return Html::el('span')->setClass('user-uid')->setHtml($uidLink);
        })->setSortable();

        if ($canViewOrEdit) {
            $grid->addColumnText('jmeno', 'Jméno a příjmení (nick)')->setCustomRender(function ($item) {
                return $item->jmeno . ' '. $item->prijmeni . ($item->firma_nazev ? ", {$item->firma_nazev}" : '') . ($item->nick ? " ({$item->nick})" : '');
            })->setSortable();
            if (!in_array($fullnotes, [null, false, 0, '', '0'], true)) {
                $grid->addColumnText('ulice_cp', 'Ulice')->setCustomRender(function ($item) {
                    $el = Html::el('span');
                    $el->setText($item->ulice_cp);
                    $this->addressNotice($el, $item);
                    return $el;
                })->setSortable();
                $grid->addColumnText('mesto', 'Obec')->setSortable();
                $grid->addColumnText('psc', 'PSČ')->setSortable();
            } else {
                $grid->addColumnText('ulice_cp', 'Ulice')->setCustomRender(function ($item) {
                    $el = Html::el('span');
                    $el->title = $item->ulice_cp;
                    $el->setText(Strings::truncate($item->ulice_cp ?? '', 50, $append = '…'));
                    $this->addressNotice($el, $item);
                    return $el;
                })->setSortable();
            }

            $grid->addColumnEmail('email', 'E-mail')->setSortable();
            $grid->addColumnText('telefon', 'Telefon')->setSortable();
        }

        $grid->addColumnText('IPAdresa', 'IP adresy')->setColumn(function ($item) {
            return join(",", array_values($item->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa')));
        })->setCustomRender(function ($item) {
            $el = Html::el('span');
            $ipAdresy = $item->related('IPAdresa.Uzivatel_id');
            if ($ipAdresy->count() > 0) {
                $el->title = join(", ", array_values($ipAdresy->fetchPairs('id', 'ip_adresa')));
                $el->setText($ipAdresy->fetch()->ip_adresa);
            }
            return $el;
        });

        if ($canViewOrEdit) {
            if (!in_array($money, [null, false, 0, '', '0'], true)) {
                $grid->addColumnText('money_aktivni', 'Aktivní')->setSortable()->setReplacement(array('1' => 'ANO', '0' => 'NE'));
                $grid->addColumnText('money_deaktivace', 'Deaktivace')->setSortable()->setReplacement(array('1' => 'ANO', '0' => 'NE'));

                $posledniPohyby = new PosledniPohybyKonta($this->uzivatelskeKonto);
                $posledniPlatba = fn (ActiveRow $item): string => $this->popisPohybu($posledniPohyby->posledni($item, self::PLATBA));
                $grid->addColumnText('lastp', 'Poslední platba')->setColumn($posledniPlatba)->setCustomRender($posledniPlatba);

                // Stránka ukazuje jen měsíční aktivaci, export i dočasnou přes SMS.
                $grid->addColumnText('lasta', 'Poslední aktivace')->setColumn(function (ActiveRow $item) use ($posledniPohyby): string {
                    return $this->popisPohybu($posledniPohyby->posledni($item, self::AKTIVACE));
                })->setCustomRender(function (ActiveRow $item) use ($posledniPohyby): string {
                    return $this->popisPohybu($posledniPohyby->posledni($item, self::MESICNI_AKTIVACE));
                });

                $grid->addColumnText('acc', 'Stav účtu')->setColumn(function ($item) {
                    $stavUctu = $item->related('UzivatelskeKonto.Uzivatel_id')->sum('castka');
                    if ($item->kauce_mobil > 0) {
                        return ($stavUctu - $item->kauce_mobil) . ' (kauce: '.$item->kauce_mobil.')';
                    } else {
                        return $stavUctu;
                    }
                })->setCustomRender(function ($item) {
                    $stavUctu = $item->related('UzivatelskeKonto.Uzivatel_id')->sum('castka');
                    if ($item->kauce_mobil > 0) {
                        return ($stavUctu - $item->kauce_mobil) . ' (kauce: '.$item->kauce_mobil.')';
                    } else {
                        return $stavUctu;
                    }
                });
            }

            $grid->addColumnText('TechnologiePripojeni_id', 'Tech')->setCustomRender(function ($item) {
                return Html::el('span')
                        ->setClass('conntype'.$item->TechnologiePripojeni_id)
                        ->alt($item->TechnologiePripojeni_id)
                        ->setTitle($item->TechnologiePripojeni->text)
                        ->data("toggle", "tooltip")
                        ->data("placement", "right");
            })->setSortable();

            if (!in_array($fullnotes, [null, false, 0, '', '0'], true)) {
                $grid->addColumnText('poznamka', 'Dlouhá poznámka')->setSortable();
            } else {
                $grid->addColumnText('poznamka', 'Poznámka')->setCustomRender(function ($item) {
                    $el = Html::el('span');
                    $el->title = $item->poznamka;
                    $el->setText(Strings::truncate($item->poznamka ?? '', 20, $append = '…'));
                    return $el;
                })->setSortable();
            }
        }

        $this->pridejSloupecStitku($grid);

        return $grid;
    }

    /**
     * @param int|string|null $id
     * @param bool|int|string|null $money
     * @param bool|int|string|null $fullnotes
     * @param string|false|null $search
     */
    public function getListOfUsersGrid(Nette\Application\UI\Presenter $presenter, string $name, Nette\Security\User $loggedUser, $id, $money, $fullnotes, $search): \Grido\Grid {
        $canViewOrEdit = false;

        $grid = new AppGrid($presenter, $name);
        $grid->setExport('user_export');

        if (!in_array($id, [null, 0, '', '0'], true)) {
            $seznamUzivatelu = $this->uzivatel->getSeznamUzivateluZAP($id);
            $seznamUzivateluCC = $this->cestneClenstviUzivatele->getListCCOfAP($id);
            $canViewOrEdit = $loggedUser->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAP($id, $loggedUser);
        } else {
            if ($search !== null && $search !== false && $search !== '' && $search !== '0') {
                $seznamUzivatelu = $this->uzivatel->findUserByFulltext($search, $loggedUser);
                $seznamUzivateluCC = $this->cestneClenstviUzivatele->getListCC(); //TODO
                $canViewOrEdit = $loggedUser->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAll($loggedUser);
            } else {
                $seznamUzivatelu = $this->uzivatel->getSeznamUzivatelu();
                $seznamUzivateluCC = $this->cestneClenstviUzivatele->getListCC();
                $canViewOrEdit = $loggedUser->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAll($loggedUser);
            }

            $grid->addColumnText('Ap_id', 'AP')->setCustomRender(function ($item) {
                return $item->ref('Ap', 'Ap_id')->jmeno;
            })->setSortable();
        }

        $grid->setModel($seznamUzivatelu);

        $grid->setDefaultPerPage(500);
        $grid->setPerPageList(array(25, 50, 100, 250, 500, 1000));
        $grid->setDefaultSort(array('zalozen' => 'ASC'));

        $tz = $grid->addFilterSelect('spolek_druzstvo', 'Zobrazit', array(
                'active' => 'spolek i družstvo',
                'spolek' => 'spolek',
                'druzstvo' => 'družstvo',
                'all' => 'spolek i družstvo včetně zrušených a smazaných'))
            ->setWhere(function ($value, $connection) {
                if ($value === 'active') {
                    return $connection->where(Uzivatel::AKTIVNI_CLEN);
                } elseif ($value === 'spolek') {
                    return $connection->where('spolek = ? AND TypClenstvi_id > ?', 1, 1);
                } elseif ($value === 'druzstvo') {
                    return $connection->where('druzstvo = ? AND smazano = ?', 1, 0);
                    // } elseif ($value == 'nedruzstvo') {
                    // return ($connection->where('druzstvo = ?', 0));
                }
                return $connection;
            });

        if ($search !== null && $search !== false && $search !== '' && $search !== '0') {
            $tz->setDefaultValue('all');
        } else {
            $tz->setDefaultValue('active');
        }

        $seznamStitku = $this->stitek->getSeznamStitku()->order('text, id')->fetchPairs("id", "text");
        $seznamNotStitku = [];
        foreach ($seznamStitku as $stitekId => $stitekText) {
            $seznamNotStitku[-$stitekId] = "NOT " . $stitekText;
        }

        $stitkyKFiltrovani = [0 => "---"];
        $stitkyKFiltrovani += $seznamStitku;
        $stitkyKFiltrovani += $seznamNotStitku;

        $grid->addFilterSelect('stitek', 'Hledej štítek', $stitkyKFiltrovani)
            ->setWhere(function ($value, $connection) {
                if ($value > 0) { // YES podmínka
                    return $connection->where(":StitekUzivatele.Stitek_id = ?", $value);
                } elseif ($value < 0) { // NOT podmínka
                    // Pro NOT podmínku musíme filtrovat v joinu a to se dělá pomocí joinWhere
                    return $connection
                    ->joinWhere(':StitekUzivatele', ':StitekUzivatele.Stitek_id = ?', -$value)
                    ->whereOr([
                        ":StitekUzivatele.Stitek_id != ?" => -$value,
                        ":StitekUzivatele.Stitek_id ?" => null,
                    ]);
                }
                return $connection;
            });

        if (!in_array($money, [null, false, 0, '', '0'], true) && $canViewOrEdit) { // přepínač "Informace z money" ukazuje šablona jen s oprávněním
            $thisparams = $this->parameters;
            $grid->setRowCallback(function ($item, $tr) use ($seznamUzivateluCC, $thisparams) {

                $konto = $item->related('UzivatelskeKonto.Uzivatel_id');
                if ((int) $item->money_aktivni !== 1) {
                    $tr->class[] = 'neaktivni';
                }
                if (($konto->sum('castka') - $item->kauce_mobil) > ($thisparams->getVyseClenskehoPrispevku() * 12)) {
                    $tr->class[] = 'preplatek';
                }
                if (in_array($item->id, $seznamUzivateluCC, true)) {
                    $tr->class[] = 'cestne';
                    return $tr;
                }
                if ($item->spolek && $item->TypClenstvi_id === 2) {
                    $tr->class[] = 'primarni';
                }
                return $tr;
            });
        } else {
            $grid->setRowCallback(function ($item, $tr) use ($seznamUzivateluCC) {

                if ((int) $item->email_invalid === 1) {
                    $tr->class[] = 'invalidemail';
                }
                if (in_array($item->id, $seznamUzivateluCC, true)) {
                    $tr->class[] = 'cestne';
                    return $tr;
                }
                if ($item->spolek && $item->TypClenstvi_id === 2) {
                    $tr->class[] = 'primarni';
                }
                if ($item->spolek && $item->TypClenstvi_id === 1 && (!$item->druzstvo)
                    || $item->druzstvo && $item->smazano && (!$item->spolek)
                    || $item->spolek && $item->druzstvo && $item->TypClenstvi_id === 1 && $item->smazano) {
                    $tr->class[] = 'zrusene';
                }
                if ($item->TypClenstvi_id === 0) {
                    $tr->class[] = 'planovane';
                }
                return $tr;
            });
        }

        $grid->addColumnText('id', 'UID')->setCustomRender(function ($item) use ($presenter, $canViewOrEdit) {
            $uidLink = Html::el('a')
            ->href($presenter->link('Uzivatel:show', array('id' => $item->id)))
            ->title($item->id)
            ->setText($item->id);

            $spanSpolek = Html::el('span')->setText('Spolek')->setClass('label');
            $spanSpolek->addClass($item->TypClenstvi_id !== 1 ? "label-spolek" : "label-neaktivni");

            $spanDruzstvo = Html::el('span')->setText('Družstvo')->setClass('label');
            $spanDruzstvo->addClass(!$item->smazano ? "label-druzstvo" : "label-neaktivni");

            if ($item->spolek) {
                $uidLink .= $spanSpolek;
            }

            if ($item->druzstvo) {
                $uidLink .= $spanDruzstvo;
            }

            if ($canViewOrEdit) {
                // edit button
                $anchor = Html::el('a')
                            ->setHref($presenter->link('Uzivatel:edit', array('id' => $item->id)))
                            ->setTitle('Editovat')
                            ->setClass('btn btn-default btn-xs btn-in-table');
                $anchor->create('span')->setClass('glyphicon glyphicon-pencil'); // inner edit icon
                $uidLink .= $anchor;
            }

            return Html::el('span')->setClass('user-uid')->setHtml($uidLink);
        })->setSortable();

        if ($canViewOrEdit) {
            $grid->addColumnText('jmeno', 'Jméno a příjmení (nick)')->setCustomRender(function ($item) {
                return $item->jmeno . ' '. $item->prijmeni . ($item->firma_nazev ? ", {$item->firma_nazev}" : '') . ($item->nick ? " ({$item->nick})" : '');
            })->setSortable();
            if (!in_array($fullnotes, [null, false, 0, '', '0'], true)) {
                $grid->addColumnText('ulice_cp', 'Ulice')->setCustomRender(function ($item) {
                    $el = Html::el('span');
                    $el->setText($item->ulice_cp);
                    $this->addressNotice($el, $item);
                    return $el;
                })->setSortable()->setFilterText();
                $grid->addColumnText('mesto', 'Obec')->setSortable()->setFilterText();
                $grid->addColumnText('psc', 'PSČ')->setSortable()->setFilterText();
            } else {
                $grid->addColumnText('ulice_cp', 'Ulice')->setCustomRender(function ($item) {
                    $el = Html::el('span');
                    $el->title = $item->ulice_cp;
                    $el->setText(Strings::truncate($item->ulice_cp ?? '', 50, $append = '…'));
                    $this->addressNotice($el, $item);
                    return $el;
                })->setSortable()->setFilterText();
            }

            $grid->addColumnEmail('email', 'E-mail')->setSortable();
            $grid->addColumnText('telefon', 'Telefon')->setSortable();
        }

        $grid->addColumnText('IPAdresa', 'IP adresy')->setColumn(function ($item) {
            return join(",", array_values($item->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa')));
        })->setCustomRender(function ($item) {
            $el = Html::el('span');
            $ipAdresy = $item->related('IPAdresa.Uzivatel_id');
            if ($ipAdresy->count() > 0) {
                $el->title = join(", ", array_values($ipAdresy->fetchPairs('id', 'ip_adresa')));
                $el->setText($ipAdresy->fetch()->ip_adresa);
            }
            return $el;
        });

        if ($canViewOrEdit) {
            if (!in_array($money, [null, false, 0, '', '0'], true)) {
                $grid->addColumnText('money_aktivni', 'Aktivní')->setSortable()->setReplacement(array('1' => 'ANO', '0' => 'NE'));
                $grid->addColumnText('money_deaktivace', 'Deaktivace')->setSortable()->setReplacement(array('1' => 'ANO', '0' => 'NE'));

                $posledniPohyby = new PosledniPohybyKonta($this->uzivatelskeKonto);
                $grid->addColumnText('lastp', 'Poslední platba')->setColumn(function (ActiveRow $item) use ($posledniPohyby): string {
                    return $this->popisPohybu($posledniPohyby->posledni($item, self::PLATBA), true);
                });

                $grid->addColumnText('lasta', 'Poslední aktivace')->setColumn(function (ActiveRow $item) use ($posledniPohyby): string {
                    return $this->popisPohybu($posledniPohyby->posledni($item, self::AKTIVACE));
                });

                $grid->addColumnText('acc', 'Stav účtu')->setColumn(function ($item) {
                    $stavUctu = $item->related('UzivatelskeKonto.Uzivatel_id')->sum('castka');
                    if ($item->kauce_mobil > 0) {
                        return ($stavUctu - $item->kauce_mobil) . ' (kauce: '.$item->kauce_mobil.')';
                    } else {
                        return $stavUctu;
                    }
                });
            }

            $grid->addColumnText('TechnologiePripojeni_id', 'Tech')->setCustomRender(function ($item) {
                return Html::el('span')
                        ->setClass('conntype'.$item->TechnologiePripojeni_id)
                        ->alt($item->TechnologiePripojeni_id)
                        ->setTitle($item->TechnologiePripojeni->text)
                        ->data("toggle", "tooltip")
                        ->data("placement", "right");
            })->setSortable();

            if (!in_array($fullnotes, [null, false, 0, '', '0'], true)) {
                $grid->addColumnText('poznamka', 'Dlouhá poznámka')->setSortable()->setFilterText();
            } else {
                $grid->addColumnText('poznamka', 'Poznámka')->setCustomRender(function ($item) {
                    $el = Html::el('span');
                    $el->title = $item->poznamka;
                    $el->setText(Strings::truncate($item->poznamka ?? '', 20, $append = '…'));
                    return $el;
                })->setSortable()->setFilterText();
            }
        }

        $this->pridejSloupecStitku($grid);

        return $grid;
    }

    /** Štítky členů i nabídka všech štítků se načtou jednou na tabulku, ne pro každý řádek. */
    private function pridejSloupecStitku(\Grido\Grid $grid): void {
        $stitkyClenu = new StitkyClenu($this->stitekUzivatele);
        $latte = new Engine();
        $stitky = null;
        $grid->addColumnText('stitky', 'Štítky')->setCustomRender(function (ActiveRow $item) use ($stitkyClenu, $latte, &$stitky): string {
            $stitky ??= $this->stitek->getSeznamStitku()->fetchAll();
            $params = [
                'stitky' => $stitky,
                'stitkyUzivatele' => $stitkyClenu->clena($item),
                'userId' => $item->id,
            ];
            $templatePath = __DIR__ . '/../components/UserLabelsComponent.latte';
            return $latte->renderToString($templatePath, $params);
        })->setCustomRenderExport(function (ActiveRow $item) use ($stitkyClenu): string {
            $stitkyUzivatele = $stitkyClenu->clena($item);
            $out = [];
            foreach ($stitkyUzivatele as $stitekUzivateleItem) {
                $out[] = $stitekUzivateleItem->text;
            }
            return join(", ", $out);
        });
    }
}
