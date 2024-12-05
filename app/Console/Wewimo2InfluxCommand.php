<?php

namespace App\Console;

use App\Model\AP;
use App\Model\Wewimo;
use InfluxDB;
use InfluxDB\Point;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Wewimo2InfluxCommand extends Command
{

    /** @var AP */
    private $ap;
    /** @var Wewimo */
    private $wewimo;

    public function __construct(AP $ap, Wewimo $wewimo)
    {
        parent::__construct();
        $this->ap = $ap;
        $this->wewimo = $wewimo;
    }

    protected function configure()
    {
        $this->setName('app:wewimo2influx')
            ->setDescription('Ziskat Wewimo data ze vsech sledovanych RB a zapsat do InfluxDB (Grafany)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $influxUrl = $this->getHelper('container')->getParameter('influxUrl');

        $database = InfluxDB\Client::fromDSN($influxUrl); // 'influxdb://user:pass@host:port/db'

        // projit vsechny RB, u kterych je zaskrtnut priznak Wewimo
        $aps = $this->ap->findAll()->order('id');
        foreach ($aps as $ap) {
            try {
                // z kazdeho stahnout Wewimo data (signaly klientu)
                echo "Processing Wewimo data for AP id=".$ap->id;
                $counter = 0;
                $wewimoMultiData = $this->wewimo->getWewimoFullData($ap->id, 'SignalPlotter');
                foreach ($wewimoMultiData['devices'] as $device) {
                    foreach ($device['interfaces'] as $ifaceName => $interface) {
                        foreach ($interface['stations'] as $station) {
                            // kazdeho klienta podle MAC zapsat do InfluxDB
                            $fields = [
                                'macField' => $station['mac-address'],
                            ];
                            $this->addFloatField($fields, 'rx_signal_strength', $station, 'x-signal-strength');
                            $this->addFloatField($fields, 'rx_signal_strength_ch0', $station, 'signal-strength-ch0');
                            $this->addFloatField($fields, 'rx_signal_strength_ch1', $station, 'signal-strength-ch1');
                            $this->addFloatField($fields, 'tx_signal_strength', $station, 'x-tx-signal-strength');
                            $this->addFloatField($fields, 'tx_signal_strength_ch0', $station, 'tx-signal-strength-ch0');
                            $this->addFloatField($fields, 'tx_signal_strength_ch1', $station, 'tx-signal-strength-ch1');
                            $this->addFloatField($fields, 'signal_to_noise', $station, 'x-signal-to-noise');
                            $this->addFloatField($fields, 'rx_CCQ', $station, 'rx-ccq');
                            $this->addFloatField($fields, 'tx_CCQ', $station, 'tx-ccq');
                            $this->addFloatField($fields, 'rx_rate', $station, 'x-rx-rate');
                            $this->addFloatField($fields, 'tx_rate', $station, 'x-tx-rate');
                            $point = new Point(
                                'station', // name of the measurement
                                null, // the measurement value, dummy here
                                [   // tags:
                                    'macStationTag' => $station['mac-address'],
                                    'interfaceMacTag' => $interface['mac-address'],
                                    'deviceIPTag' => $device['ip'],
                                ],
                                $fields   // additional fields
                            );
                            $points = [
                                $point
                            ];
                            //echo "    Writing signal for station MAC=".$station['mac-address'].' '.$point;
                            $result = $database->writePoints($points);
                            $counter++;
                        }
                    }
                }
                echo " $counter station MACs stored ";
                echo " DONE.\n";
            } catch (\Exception $ex) {
                echo " ERROR: " .$ex->getMessage(). "\n";
            }
        }
        return 0;
    }

    private function addFloatField(&$fields, $dstField, $station, $srcField)
    {
        $val = $station[$srcField] ?? null;
        if (!is_null($val)) {
            $fields[$dstField] = floatval($val);
        }
    }
}
