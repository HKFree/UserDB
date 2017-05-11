<?php

namespace App\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateLocationsCommand extends Command
{
    private $googleMapsApiKey;

    protected function configure()
    {
        $this->setName('app:update_locations')
            ->setDescription('Aktualizovat hromadne zemepis. souradnice podle adres u uzivatelu (kde nejsou ve stavu "valid")');
        $this->addArgument('mode', InputArgument::OPTIONAL, 'normal nebo retry, retry zkusi krome pending adres znovu geokodovat adresy ve stavu approx nebo uknown', 'normal');
    }

    protected function googleMapsGeocode($adresa) {
        $client = new \GuzzleHttp\Client(['verify' => false]);
        return $client->request('GET', 'https://maps.googleapis.com/maps/api/geocode/json?key='.
            urlencode($this->googleMapsApiKey).
            '&address='.urlencode($adresa));
    }

    protected function vugtkGeocode($adresa) {
        $client = new \GuzzleHttp\Client(['verify' => false]);
        return $client->request('GET', 'http://www.vugtk.cz/euradin/ruian/rest.py/Geocode/json?ExtraInformation=standard&SearchText='.
            urlencode($adresa));
    }

    private function krowToWgs($X,$Y,$H)
    {
        $H=$H+45;

        ///*Vypocet zemepisnych souradnic z rovinnych souradnic*/
        $a=6377397.15508; $e=0.081696831215303;
        $n=0.97992470462083; $konst_u_ro=12310230.12797036;
        $sinUQ=0.863499969506341; $cosUQ=0.504348889819882;
        $sinVQ=0.420215144586493; $cosVQ=0.907424504992097;
        $alfa=1.000597498371542; $k=1.003419163966575;
        $ro=sqrt($X*$X+$Y*$Y);
        $epsilon=2*atan($Y/($ro+$X));
        $D=$epsilon/$n; $S=2*atan(exp(1/$n*log($konst_u_ro/$ro)))-pi()/2;
        $sinS=sin($S);$cosS=cos($S);
        $sinU=$sinUQ*$sinS-$cosUQ*$cosS*cos($D);$cosU=sqrt(1-$sinU*$sinU);
        $sinDV=sin($D)*$cosS/$cosU; $cosDV=sqrt(1-$sinDV*$sinDV);
        $sinV=$sinVQ*$cosDV-$cosVQ*$sinDV; $cosV=$cosVQ*$cosDV+$sinVQ*$sinDV;
        $Ljtsk=2*atan($sinV/(1+$cosV))/$alfa;
        $t=exp(2/$alfa*log((1+$sinU)/$cosU/$k));
        $pom=($t-1)/($t+1);
        do
        {
            $sinB=$pom;
            $pom=$t*exp($e*log((1+$e*$sinB)/(1-$e*$sinB)));
            $pom=($pom-1)/($pom+1);
        }
        while (abs($pom-$sinB)>1e-15);
        $Bjtsk=atan($pom/sqrt(1-$pom*$pom));

        ///* Pravoúhlé souřadnice ve S-JTSK */
        $a=6377397.15508; $f_1=299.152812853;
        $e2=1-((1-1/$f_1)*(1-1/$f_1)); $ro=$a/sqrt(1-$e2*sin($Bjtsk)*sin($Bjtsk));
        $x=($ro+$H)*cos($Bjtsk)*cos($Ljtsk);
        $y=($ro+$H)*cos($Bjtsk)*sin($Ljtsk);
        $z=((1-$e2)*$ro+$H)*sin($Bjtsk);

        ///* Pravoúhlé souřadnice v WGS-84*/
        $dx=570.69; $dy=85.69; $dz=462.84;
        $wz=-5.2611/3600*pi()/180;$wy=-1.58676/3600*pi()/180;$wx=-4.99821/3600*pi()/180; $m=0.000003543;
        $xn=$dx+(1+$m)*($x+$wz*$y-$wy*$z); $yn=$dy+(1+$m)*(-$wz*$x+$y+$wx*$z); $zn=$dz+(1+$m)*($wy*$x-$wx*$y+$z);

        ///* Geodetické souřadnice v systému WGS-84*/
        $a=6378137; $f_1=298.257223563;
        $a_b=$f_1/($f_1-1); $p=sqrt($xn*$xn+$yn*$yn); $e2=1-(1-1/$f_1)*(1-1/$f_1);
        $theta=atan($zn*$a_b/$p); $st=sin($theta); $ct=cos($theta);
        $t=($zn+$e2*$a_b*$a*$st*$st*$st)/($p-$e2*$a*$ct*$ct*$ct);
        $B=atan($t);  $L=2*atan($yn/($p+$xn));  $H=sqrt(1+$t*$t)*($p-$a/sqrt(1+(1-$e2)*$t*$t));

        $B=$B/pi()*180; //if ($B<0) {$B='S'.-$B;} else {$B='N'.$B;};
        $L=$L/pi()*180; //if ($L<0) {$L='W'.-$L;} else {$L='E'.$L;};

        return(array("lat" => $B,"lon" => $L,"h" => $H));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->googleMapsApiKey = $this->getHelper('container')->getParameter('googleMapsApiKey');
        $mode = $input->getArgument('mode');
        echo "update_locations started, mode $mode, googleMapsApiKey: $this->googleMapsApiKey\n";
        /** @var \App\Model\Uzivatel $uzivatelModel */
        $uzivatelModel = $this->getHelper('container')->getByType('\App\Model\Uzivatel');
        $statuses = ($mode === 'retry') ? ['pending', 'approx', 'unknown'] : ['pending'];
        $uzivatele = $uzivatelModel->findAll()->where('location_status IN ?', $statuses)->limit(500)->fetchAll();
        //$uzivatele = $uzivatelModel->findAll()->where('id IN ?', [1016])->limit(500)->fetchAll();
        foreach($uzivatele as $uzivatel) {
            $adresa = "{$uzivatel->ulice_cp}, {$uzivatel->mesto}";
            $uid = $uzivatel->id;
            echo "[$uid] $adresa\n";
            $lat = null;
            $lon = null;
            $status = 'error';
            $res = $this->googleMapsGeocode($adresa);
            if ($res->getStatusCode() === 200) {
                //echo $res->getBody()."\n";
                $data = json_decode($res->getBody(), true);
                //print_r($data);
                if ($data['status'] === 'OK') {
                    // nalezen vysledek (s ruznou presnosti)
                    $geometry = $data['results'][0]['geometry'];
                    $lat = $geometry['location']['lat'];
                    $lon = $geometry['location']['lng'];
                    $precision = $geometry['location_type'];
                    echo "  found $lat,$lon $precision\n";
                    $status = ($precision === 'ROOFTOP') ? 'valid' : 'approx';
                } else if ($data['status'] === 'ZERO_RESULTS') {
                    // vysledek nenalezen
                    echo "  unknown\n";
                    $status = 'uknown';
                } else {
                    // prekrocen denni limit volani API, anebo byl request odmitnut, anebo jina chyba
                    echo "  API ERROR ".$data['status']."\n";
                    break; // dal uz to ted zkouset nebudeme
                }
            } else {
                echo "  HTTP ERROR ".$res->getStatusCode()."\n";
            }

            if ($status === 'valid') {
                // google nasel adresu presne, ulozit
                $uzivatelModel->update($uid, [
                    'location_status' => $status,
                    'latitude' => $lat,
                    'longitude' => $lon
                ]);
            } else if ($status === 'error') {
                // pri chybe ignorovat, pri pristim behu to zkusime znovu (zustava status 'pending')
            } else {
                // google adresu nenasel (uknown) nebo nasel, ale nepresne (approx);
                // zkusit jeste jine geocoding api
                $res = $this->vugtkGeocode($adresa);
                if ($res->getStatusCode() === 200) {
                    $data = json_decode($res->getBody(), true);
                    if (isset($data['records']) && (count($data['records']) === 1)) {
                        // adresa nalezena (presne 1 misto)
                        $jtskx = $data['records'][0]['JTSKX'];
                        $jtsky = $data['records'][0]['JTSKY'];
                        // prevedeme Krovaka na WGS
                        $wgs = $this->krowToWgs($jtskx, $jtsky, 240);
                        $status = 'valid';
                        $lat = $wgs['lat'];
                        $lon = $wgs['lon'];
                        echo "  vugtk found $lat,$lon\n";
                    } else {
                        // adresa nenalezena nebo nalezeno vetsi uzemi, ulozime tedy aspon to, co nalezly google mapy
                        echo "  vugtk not found\n";
                    }
                    // ulozime stav a souradnice (muze byt uknown+null nebo approx+lat,lon z google nebo valid+lat,lon z vugtk;
                    //                              valid+lan,lon z google se uklada vyse)
                    $uzivatelModel->update($uid, [
                        'location_status' => $status,
                        'latitude' => $lat,
                        'longitude' => $lon
                    ]);
                } else {
                    // vugtk API nefunguje, vysledek (i z google map) ignorujeme a zkusime za chvili znovu (zustava status 'pending')
                }
            }

            sleep(2);
        }
        echo "update_locations finished\n";
    }
}
