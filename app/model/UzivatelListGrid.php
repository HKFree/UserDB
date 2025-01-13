<?php

namespace App\Model;

use Nette;
use Nette\Utils\Strings;
use Nette\Utils\Html;
use Latte\Engine;

/**
 * @author
 */
class UzivatelListGrid
{
    private $uzivatel;
    private $stitek;
    private $stitekUzivatele;
    private $ap;
    private $cestneClenstviUzivatele;
    private $parameters;

    public function __construct(Parameters $parameters, AP $ap, CestneClenstviUzivatele $cc, Uzivatel $uzivatel, Stitek $stitky, StitekUzivatele $stitekUzivatele) {
        $this->stitek = $stitky;
        $this->stitekUzivatele = $stitekUzivatele;
        $this->uzivatel = $uzivatel;
        $this->ap = $ap;
        $this->cestneClenstviUzivatele = $cc;
        $this->parameters = $parameters;
    }

    private function addressNotice($el, $item) {
        if ($item->location_status === 'approx') {
            $el->addHtml(' <i class="fa fa-exclamation-triangle" title="Nepřesná adresa, opravte!"></i>');
        } elseif ($item->location_status === 'unknown') {
            $el->addHtml(' <i class="fa fa-times-circle" title="Neznámá adresa, opravte!"></i>');
        }
    }

    public function getListOfOtherUsersGrid($presenter, $name, $loggedUser, $id, $money, $fullnotes, $search) {
        //\Tracy\Debugger::barDump($search);

        $canViewOrEdit = false;

        $grid = new \Grido\Grid($presenter, $name);
        $grid->translator->setLang('cs');
        $grid->setExport('user_export');

        $apcko = $this->ap->getAP($id);
        $subnety = $apcko->related('Subnet.Ap_id');

        $seznamUzivatelu = $this->uzivatel->findUsersFromOtherAreasByAreaId($id, $subnety);
        $seznamUzivateluCC = $this->cestneClenstviUzivatele->getListCCOfAP($id);
        $canViewOrEdit = $loggedUser->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAP($id, $loggedUser);

        $grid->setModel($seznamUzivatelu);

        $grid->setDefaultPerPage(500);
        $grid->setPerPageList(array(25, 50, 100, 250, 500, 1000));
        $grid->setDefaultSort(array('zalozen' => 'ASC'));

        $list = array('active' => 'bez zrušených, plánovaných a smazaných', 'all' => 'včetně zrušených, plánovaných a smazaných', 'planned' => 'pouze plánovaná členství ve spolku');

        $grid->addFilterSelect('TypClenstvi_id', 'Zobrazit', $list)
            ->setDefaultValue('active')
            ->setWhere(function ($value, $connection) {
                if ($value == 'active') {
                    return ($connection->where('(spolek = 1 AND TypClenstvi_id > 1) OR (druzstvo = 1 AND smazano = 0)'));
                }
                if ($value == 'planned') {
                    return ($connection->where('spolek = 1 AND TypClenstvi_id = 0'));
                }
                return ($connection);
            });

        if ($money) {
            $thisparams = $this->parameters;
            $grid->setRowCallback(function ($item, $tr) use ($seznamUzivateluCC, $presenter, $thisparams) {

                $konto = $item->related('UzivatelskeKonto.Uzivatel_id');
                if ($item->money_aktivni != 1) {
                    $tr->class[] = 'neaktivni';
                }
                if (($konto->sum('castka') - $item->kauce_mobil) > ($thisparams->getVyseClenskehoPrispevku() * 12)) {
                    $tr->class[] = 'preplatek';
                }
                if (in_array($item->id, $seznamUzivateluCC)) {
                    $tr->class[] = 'cestne';
                    return $tr;
                }
                // TODO: spolek / druzstvo
                if ($item->spolek && $item->TypClenstvi_id == 2) {
                    $tr->class[] = 'primarni';
                }
                return $tr;
            });
        } else {
            $grid->setRowCallback(function ($item, $tr) use ($seznamUzivateluCC, $presenter) {

                if ($item->email_invalid == 1) {
                    $tr->class[] = 'invalidemail';
                }
                if (in_array($item->id, $seznamUzivateluCC)) {
                    $tr->class[] = 'cestne';
                    return $tr;
                }
                if ($item->spolek && $item->TypClenstvi_id == 2) {
                    $tr->class[] = 'primarni';
                }
                if ($item->spolek && $item->TypClenstvi_id == 1 && (!$item->druzstvo)
                    || $item->druzstvo && $item->smazano && (!$item->spolek)
                    || $item->spolek && $item->druzstvo && $item->TypClenstvi_id == 1 && $item->smazano) {
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
                            ->setClass('btn btn-default btn-xs btn-in-table pull-right');
                $anchor->create('span')->setClass('glyphicon glyphicon-pencil'); // inner edit icon
                $uidLink .= $anchor;
            }

            return $uidLink;
        })->setSortable();

        if ($canViewOrEdit) {
            $grid->addColumnText('jmeno', 'Jméno a příjmení (nick)')->setCustomRender(function ($item) {
                return $item->jmeno . ' '. $item->prijmeni . ($item->firma_nazev ? ", {$item->firma_nazev}" : '') . ($item->nick ? " ({$item->nick})" : '');
            })->setSortable();
            if ($fullnotes) {
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
            if ($money) {
                $grid->addColumnText('money_aktivni', 'Aktivní')->setSortable()->setReplacement(array('1' => 'ANO', '0' => 'NE'));
                $grid->addColumnText('money_deaktivace', 'Deaktivace')->setSortable()->setReplacement(array('1' => 'ANO', '0' => 'NE'));

                $grid->addColumnText('lastp', 'Poslední platba')->setColumn(function ($item) {
                    $posledniPlatba = $item->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id', 1)->order('id DESC')->limit(1);
                    if ($posledniPlatba->count() > 0) {
                        $posledniPlatbaData = $posledniPlatba->fetch();
                        return ($posledniPlatbaData->datum == null) ? "NIKDY" : ($posledniPlatbaData->datum->format('d.m.Y') . " (" . $posledniPlatbaData->castka . ")");
                    }
                    return "?";
                })->setCustomRender(function ($item) {
                    $posledniPlatba = $item->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id', 1)->order('id DESC')->limit(1);
                    if ($posledniPlatba->count() > 0) {
                        $posledniPlatbaData = $posledniPlatba->fetch();
                        return ($posledniPlatbaData->datum == null) ? "NIKDY" : ($posledniPlatbaData->datum->format('d.m.Y') . " (" . $posledniPlatbaData->castka . ")");
                    }
                    return "?";
                });

                $grid->addColumnText('lasta', 'Poslední aktivace')->setColumn(function ($item) {
                    $posledniAktivace = $item->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id', array(4, 5))->order('id DESC')->limit(1);
                    if ($posledniAktivace->count() > 0) {
                        $posledniAktivaceData = $posledniAktivace->fetch();
                        return ($posledniAktivaceData->datum == null) ? "NIKDY" : ($posledniAktivaceData->datum->format('d.m.Y') . " (" . $posledniAktivaceData->castka . ")");
                    }
                    return "?";
                })->setCustomRender(function ($item) {
                    $posledniAktivace = $item->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id', 4)->order('id DESC')->limit(1);
                    if ($posledniAktivace->count() > 0) {
                        $posledniAktivaceData = $posledniAktivace->fetch();
                        return ($posledniAktivaceData->datum == null) ? "NIKDY" : ($posledniAktivaceData->datum->format('d.m.Y') . " (" . $posledniAktivaceData->castka . ")");
                    }
                    return "?";
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

            if ($fullnotes) {
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

        $grid->addColumnText('stitky', 'Štítky')->setCustomRender(function ($item) use ($presenter) {
            $latte = new Engine();
            $params = [
                'stitky' => $this->stitek->getSeznamStitku(),
                'stitkyUzivatele' => $this->stitekUzivatele->getStitekByUserId($item->id),
                'userId' => $item->id,
            ];
            $templatePath = __DIR__ . '/../components/UserLabelsComponent.latte';
            return $latte->renderToString($templatePath, $params);
        });

        return $grid;
    }

    public function getListOfUsersGrid($presenter, $name, $loggedUser, $id, $money, $fullnotes, $search) {
        $canViewOrEdit = false;

        $grid = new \Grido\Grid($presenter, $name);
        $grid->translator->setLang('cs');
        $grid->setExport('user_export');

        if ($id) {
            $seznamUzivatelu = $this->uzivatel->getSeznamUzivateluZAP($id);
            $seznamUzivateluCC = $this->cestneClenstviUzivatele->getListCCOfAP($id);
            $canViewOrEdit = $loggedUser->isInRole('EXTSUPPORT') || $this->ap->canViewOrEditAP($id, $loggedUser);
        } else {
            if ($search) {
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

        $grid->addFilterSelect('spolek_druzstvo', 'Zobrazit', array(
                'all' => 'spolek i družstvo',
                'spolek' => 'spolek',
                'druzstvo' => 'družstvo',
                'nedruzstvo' => 'není družstvo'))
            ->setDefaultValue('all')
            ->setWhere(function ($value, $connection) {
                if ($value == 'spolek') {
                    return ($connection->where('spolek = ?', 1));
                } elseif ($value == 'druzstvo') {
                    return ($connection->where('druzstvo = ?', 1));
                } elseif ($value == 'nedruzstvo') {
                    return ($connection->where('druzstvo = ?', 0));
                }
                return ($connection);
            });

        $list = array('active' => 'bez zrušených, plánovaných a smazaných', 'all' => 'včetně zrušených, plánovaných a smazaných', 'planned' => 'pouze plánovaná členství ve spolku');

        $tz = $grid->addFilterSelect('TypClenstvi_id', 'Zobrazit', $list)
            ->setWhere(function ($value, $connection) {
                if ($value == 'active') {
                    return ($connection->where('(spolek = 1 AND TypClenstvi_id > 1) OR (druzstvo = 1 AND smazano = 0)'));
                }
                if ($value == 'planned') {
                    return ($connection->where('spolek = 1 AND TypClenstvi_id = 0'));
                }
                return ($connection);
            });

        if ($search) {
            $tz->setDefaultValue('all');
        } else {
            $tz->setDefaultValue('active');
        }

        $seznamStitku = $this->stitek->getSeznamStitku()->fetchPairs("id", "text");
        $seznamNotStitku = [];
        foreach ($seznamStitku as $stitekId => $stitekText) {
            $seznamNotStitku[-$stitekId] = "NOT " . $stitekText;
        }

        $stitkyKFiltrovani[0] = "---";
        $stitkyKFiltrovani += $seznamStitku;
        $stitkyKFiltrovani += $seznamNotStitku;

        $grid->addFilterSelect('stitek', 'Hledej štítek', $stitkyKFiltrovani)
            ->setWhere(function ($value, $connection) {
                if ($value > 0) { // YES podmínka
                    return ($connection->where(":StitekUzivatele.Stitek_id = ?", $value));
                } elseif ($value < 0) { // NOT podmínka
                    // Pro NOT podmínku musíme filtrovat v joinu a to se dělá pomocí joinWhere
                    return ($connection
                    ->joinWhere(':StitekUzivatele', ':StitekUzivatele.Stitek_id = ?', -$value)
                    ->whereOr([
                        ":StitekUzivatele.Stitek_id != ?" => -$value,
                        ":StitekUzivatele.Stitek_id ?" => null,
                    ]));
                }
                return ($connection);
            });

        if ($money) {
            $thisparams = $this->parameters;
            $grid->setRowCallback(function ($item, $tr) use ($seznamUzivateluCC, $presenter, $thisparams) {

                $konto = $item->related('UzivatelskeKonto.Uzivatel_id');
                if ($item->money_aktivni != 1) {
                    $tr->class[] = 'neaktivni';
                }
                if (($konto->sum('castka') - $item->kauce_mobil) > ($thisparams->getVyseClenskehoPrispevku() * 12)) {
                    $tr->class[] = 'preplatek';
                }
                if (in_array($item->id, $seznamUzivateluCC)) {
                    $tr->class[] = 'cestne';
                    return $tr;
                }
                if ($item->spolek && $item->TypClenstvi_id == 2) {
                    $tr->class[] = 'primarni';
                }
                return $tr;
            });
        } else {
            $grid->setRowCallback(function ($item, $tr) use ($seznamUzivateluCC, $presenter) {

                if ($item->email_invalid == 1) {
                    $tr->class[] = 'invalidemail';
                }
                if (in_array($item->id, $seznamUzivateluCC)) {
                    $tr->class[] = 'cestne';
                    return $tr;
                }
                if ($item->spolek && $item->TypClenstvi_id == 2) {
                    $tr->class[] = 'primarni';
                }
                if ($item->spolek && $item->TypClenstvi_id == 1 && (!$item->druzstvo)
                    || $item->druzstvo && $item->smazano && (!$item->spolek)
                    || $item->spolek && $item->druzstvo && $item->TypClenstvi_id == 1 && $item->smazano) {
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

            $spanSpolek = Html::el('span')->setText('Spolek')->setClass('label')->setAttribute('style', 'margin-left: 4px;');
            $spanSpolek->addClass($item->TypClenstvi_id != 1 ? "label-spolek" : "label-neaktivni");

            $spanDruzstvo = Html::el('span')->setText('Družstvo')->setClass('label')->setAttribute('style', 'margin-left: 4px;');
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
                            ->setClass('btn btn-default btn-xs btn-in-table pull-right');
                $anchor->create('span')->setClass('glyphicon glyphicon-pencil'); // inner edit icon
                $uidLink .= $anchor;
            }

            return $uidLink;
        })->setSortable();

        if ($canViewOrEdit) {
            $grid->addColumnText('jmeno', 'Jméno a příjmení (nick)')->setCustomRender(function ($item) {
                return $item->jmeno . ' '. $item->prijmeni . ($item->firma_nazev ? ", {$item->firma_nazev}" : '') . ($item->nick ? " ({$item->nick})" : '');
            })->setSortable();
            if ($fullnotes) {
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
            if ($money) {
                $grid->addColumnText('money_aktivni', 'Aktivní')->setSortable()->setReplacement(array('1' => 'ANO', '0' => 'NE'));
                $grid->addColumnText('money_deaktivace', 'Deaktivace')->setSortable()->setReplacement(array('1' => 'ANO', '0' => 'NE'));

                $grid->addColumnText('lastp', 'Poslední platba')->setColumn(function ($item) {
                    $posledniPlatba = $item->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id', 1)->order('id DESC')->limit(1);
                    if ($posledniPlatba->count() > 0) {
                        $posledniPlatbaData = $posledniPlatba->fetch();
                        return ($posledniPlatbaData->datum == null) ? "NIKDY" : ($posledniPlatbaData->datum->format('d.m.Y') . " (" . $posledniPlatbaData->castka . ")");
                    }
                    return "?";
                });

                $grid->addColumnText('lasta', 'Poslední aktivace')->setColumn(function ($item) {
                    $posledniAktivace = $item->related('UzivatelskeKonto.Uzivatel_id')->where('TypPohybuNaUctu_id', array(4, 5))->order('id DESC')->limit(1);
                    if ($posledniAktivace->count() > 0) {
                        $posledniAktivaceData = $posledniAktivace->fetch();
                        return ($posledniAktivaceData->datum == null) ? "NIKDY" : ($posledniAktivaceData->datum->format('d.m.Y') . " (" . $posledniAktivaceData->castka . ")");
                    }
                    return "?";
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

            if ($fullnotes) {
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

        $grid->addColumnText('stitky', 'Štítky')->setCustomRender(function ($item) use ($presenter) {
            $latte = new Engine();
            $params = [
                'stitky' => $this->stitek->getSeznamStitku(),
                'stitkyUzivatele' => $this->stitekUzivatele->getStitekByUserId($item->id),
                'userId' => $item->id,
            ];
            $templatePath = __DIR__ . '/../components/UserLabelsComponent.latte';
            return $latte->renderToString($templatePath, $params);
        });

        return $grid;
    }
}
