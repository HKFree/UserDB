<?php

namespace App\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateLocationsCommand extends Command
{
    protected function configure()
    {
        $this->setName('app:update_locations')
            ->setDescription('Aktualizovat hromadne zemepis. souradnice podle adres u uzivatelu (kde nejsou ve stavu "valid")');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $googleMapsApiKey = $this->getHelper('container')->getParameter('googleMapsApiKey');
        echo "update_locations started, googleMapsApiKey: $googleMapsApiKey\n";
        /** @var \App\Model\Uzivatel $uzivatelModel */
        $uzivatelModel = $this->getHelper('container')->getByType('\App\Model\Uzivatel');
        $uzivatele = $uzivatelModel->findAll()->where('location_status = ?', 'pending')->limit(500)->fetchAll();
        //$uzivatele = $uzivatelModel->findAll()->where('id IN ?', [1066, 1980])->limit(500)->fetchAll();
        foreach($uzivatele as $uzivatel) {
            $adresa = "{$uzivatel->ulice_cp}, {$uzivatel->mesto}";
            $uid = $uzivatel->id;
            echo "[$uid] $adresa\n";
            $client = new \GuzzleHttp\Client(['verify' => false]);
            $res = $client->request('GET', 'https://maps.googleapis.com/maps/api/geocode/json?key='.
                urlencode($googleMapsApiKey).
                '&address='.urlencode($adresa));
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
                    $uzivatelModel->update($uid, [
                        'location_status' => $status,
                        'latitude' => $lat,
                        'longitude' => $lon
                    ]);
                } else if ($data['status'] === 'ZERO_RESULTS') {
                    // vysledek nenalezen
                    echo "  unknown\n";
                    $uzivatelModel->update($uid, [
                        'location_status' => 'unknown',
                        'latitude' => null,
                        'longitude' => null
                    ]);
                } else {
                    // prekrocen denni limit volani API, anebo byl request odmitnut, anebo jina chyba
                    echo "  API ERROR ".$data['status']."\n";
                    break; // dal uz to ted zkouset nebudeme
                }
            } else {
                echo "  HTTP ERROR ".$res->getStatusCode()."\n";
            }

            sleep(2);
        }
        echo "update_locations finished\n";
    }
}
