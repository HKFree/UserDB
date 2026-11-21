<?php

namespace App\Components;

use App\Components\LogTable;
use App\Model;

/**
 * LogTableFactory
 *
 * @author bkralik
 */
class LogTableFactory
{
    private Model\IPAdresa $ipAdresa;
    private Model\Subnet $subnet;
    private Model\Log $log;

    public function __construct(Model\IPAdresa $ipAdresa, Model\Subnet $subnet, Model\Log $log) {
        $this->ipAdresa = $ipAdresa;
        $this->subnet = $subnet;
        $this->log = $log;
    }

    public function create(\Nette\Application\UI\Presenter $presenter): LogTable {
        return new LogTable($presenter, $this->ipAdresa, $this->subnet, $this->log);
    }
}
