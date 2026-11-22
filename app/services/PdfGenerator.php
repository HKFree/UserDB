<?php

namespace App\Services;

use Nette\Database\Table\ActiveRow;
use Nette\Bridges\ApplicationLatte\Template;
use PdfResponse\PdfResponse;
use App\Model;

/**
 * @author
 */
class PdfGenerator
{
    private Model\Subnet $subnet;

    public function __construct(Model\Subnet $subnet) {
        $this->subnet = $subnet;
    }

    public function generatePdf(ActiveRow $uzivatel, Template $template): PdfResponse {
        if ((int) $uzivatel->spolek !== 1) {
            throw new \InvalidArgumentException('Registrační PDF je dostupné pouze členům spolku.');
        }
        $clenstvi = $uzivatel->ref('TypClenstvi');
        if ($clenstvi === null) {
            throw new \InvalidArgumentException('Pro registrační PDF chybí typ členství ve spolku.');
        }
        if ($clenstvi->id === 0) {
            throw new \InvalidArgumentException('Registrační PDF nelze vytvořit pro plánované členství.');
        }
        if ($clenstvi->id === 1) {
            throw new \InvalidArgumentException('Registrační PDF nelze vytvořit pro zrušené členství.');
        }
        $forma = $uzivatel->ref('TypPravniFormyUzivatele', 'TypPravniFormyUzivatele_id');
        if ($forma === null) {
            throw new \InvalidArgumentException('Pro registrační PDF chybí právní forma člena.');
        }
        $template->oblast = $uzivatel->Ap->Oblast->jmeno;
        $oblastid = $uzivatel->Ap->Oblast->id;
        $template->oblastemail = "oblast$oblastid@hkfree.org";
        $template->jmeno = $uzivatel->jmeno;
        $template->prijmeni = $uzivatel->prijmeni;
        $template->forma = $forma->text;
        $template->firma = $uzivatel->firma_nazev;
        $template->ico = $uzivatel->firma_ico;
        $template->nick = $uzivatel->nick;
        $template->uid = $uzivatel->id;
        $template->heslo = $uzivatel->regform_downloaded_password_sent === 0 ? $uzivatel->heslo : "-- nelze zpětně zjistit --";
        $template->email = $uzivatel->email;
        $template->telefon = $uzivatel->telefon;
        $template->ulice = $uzivatel->ulice_cp;
        $template->mesto = $uzivatel->mesto;
        $template->psc = $uzivatel->psc;
        $template->clenstvi = $clenstvi->text;
        $ipadrs = $uzivatel->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa');
        $out = [];
        foreach ($ipadrs as $ip) {
            $subnet = $this->subnet->getSubnetOfIP($ip);

            if (isset($subnet["error"])) {
                $errorText = 'subnet není v databázi';
                $out[] = array('ip' => $ip, 'subnet' => $errorText, 'gateway' => $errorText, 'mask' => $errorText);
            } else {
                $out[] = array('ip' => $ip, 'subnet' => $subnet["subnet"], 'gateway' => $subnet["gateway"], 'mask' => $subnet["mask"]);
            }
        }

        if (count($ipadrs) === 0) {
            $out[] = array('ip' => 'není přidána žádná ip', 'subnet' => 'subnet není v databázi', 'gateway' => 'subnet není v databázi', 'mask' => 'subnet není v databázi');
        }
        $template->ips = $out;

        $pdf = new PDFResponse($template);
        $pdf->pageOrientation = PDFResponse::ORIENTATION_PORTRAIT;
        $pdf->pageFormat = "A4";
        $pdf->pageMargins = "5,5,5,5,20,60";
        $pdf->documentTitle = "hkfree-registrace-".$uzivatel->id;
        $pdf->documentAuthor = "hkfree.org z.s.";

        return $pdf;
    }

}
