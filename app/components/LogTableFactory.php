<?php

namespace App\Components;

use App\Components\LogTable,
    App\Model;

/**
 * LogTableFactory
 *
 * @author bkralik
 */
class LogTableFactory {
    private $ipAdresa, $subnet, $log;

    public function __construct(Model\IPAdresa $ipAdresa, Model\Subnet $subnet, Model\Log $log)
    {
        $this->ipAdresa = $ipAdresa;
        $this->subnet = $subnet;
        $this->log = $log;
    }

    public function create($presenter)
    {
        return new LogTable($presenter, $this->ipAdresa, $this->subnet, $this->log);
    }
}
