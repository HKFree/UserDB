<?php

namespace App\Model;

use Nette,
    PdfResponse\PdfResponse,
    DateInterval;

/**
 * @author 
 */
class PdfGenerator
{
    private $subnet;

    function __construct(Subnet $subnet) {
        $this->subnet = $subnet;
    }
    
    public function generatePdf($uzivatel, $template)
    {
        $template->oblast = $uzivatel->Ap->Oblast->jmeno;
        $oblastid = $uzivatel->Ap->Oblast->id;
        $template->oblastemail = "oblast$oblastid@hkfree.org";
        $template->jmeno = $uzivatel->jmeno;
        $template->prijmeni = $uzivatel->prijmeni;
        $template->forma = $uzivatel->ref('TypPravniFormyUzivatele', 'TypPravniFormyUzivatele_id')->text;
        $template->firma = $uzivatel->firma_nazev;
        $template->ico = $uzivatel->firma_ico;
        $template->nick = $uzivatel->nick;
        $template->uid = $uzivatel->id;
        $template->heslo = $uzivatel->regform_downloaded_password_sent==0 ? $uzivatel->heslo : "-- nelze zpětně zjistit --";
        $template->email = $uzivatel->email;
        $template->telefon = $uzivatel->telefon;
        $template->ulice = $uzivatel->ulice_cp;
        $template->mesto = $uzivatel->mesto;
        $template->psc = $uzivatel->psc;
        $template->clenstvi = $uzivatel->TypClenstvi->text;
        $template->nthmesic = $uzivatel->ZpusobPripojeni_id==2 ? "třetího" : "prvního";
        $template->nthmesicname = $uzivatel->ZpusobPripojeni_id==2 ? $this->mesicName($uzivatel->zalozen,3) : $this->mesicName($uzivatel->zalozen,1);
        $template->nthmesicdate = $uzivatel->ZpusobPripojeni_id==2 ? $this->mesicDate($uzivatel->zalozen,2) : $this->mesicDate($uzivatel->zalozen,0);
        $ipadrs = $uzivatel->related('IPAdresa.Uzivatel_id')->fetchPairs('id', 'ip_adresa');
        foreach($ipadrs as $ip) {
            $subnet = $this->subnet->getSubnetOfIP($ip);

            if(isset($subnet["error"])) {
                $errorText = 'subnet není v databázi';
                $out[] = array('ip' => $ip, 'subnet' => $errorText, 'gateway' => $errorText, 'mask' => $errorText);
            } else {
                $out[] = array('ip' => $ip, 'subnet' => $subnet["subnet"], 'gateway' => $subnet["gateway"], 'mask' => $subnet["mask"]);
            }
        }

        if(count($ipadrs) == 0) {
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
  
    public function mesicName($indate, $addmonth){
        $date = new Nette\Utils\DateTime($indate);
        $date->add(new \DateInterval('P'.$addmonth.'M'));
        $datestr = $date->format('F');

        $aj = array("January","February","March","April","May","June","July","August","September","October","November","December");
        $cz = array("leden","únor","březen","duben","květen","červen","červenec","srpen","září","říjen","listopad","prosinec");
        $datum = str_replace($aj, $cz, $datestr);
        return $datum;
    }

    public function mesicDate($indate, $addmonth){
        $date = new Nette\Utils\DateTime($indate);
        $date->add(new \DateInterval('P'.$addmonth.'M'));
        return $date->format('17.m.Y');
    }
}
