<?php

namespace App\Model;

use Nette,
    Nette\Utils\Html;



/**
 * @author
 */
class Subnet6 extends Table
{
    const ERR_NOT_FOUND = 1;

    /**
    * @var string
    */
    protected $tableName = 'Subnet6';

    public function getSeznamSubnetu()
    {
        return($this->findAll());
    }

    public function getSeznamSubnetuZacinajicich($prefix)
    {
        return $this->findAll()->where("subnet LIKE ?", $prefix.'%')->fetchAll();
    }

    public function getSubnet($id)
    {
        return($this->find($id));
    }
    public function deleteSubnet(array $subnets)
    {
		if(count($subnets)>0)
			return($this->delete(array('id' => $subnets)));
		else
			return true;
    }

    public function getSubnetForm(&$subnet) {
		$subnet->addHidden('id')->setAttribute('class', 'id subnet');
		$subnet->addText('subnet', 'Subnet', 11)->setAttribute('class', 'subnet_text subnet')->setAttribute('placeholder', '2001:db8::');
		$subnet->addText('popis', 'Popis')->setAttribute('class', 'popis subnet')->setAttribute('placeholder', 'Popis');
    }

    public function getSubnetTable($subnets) {
        $tooltips = array('data-toggle' => 'tooltip', 'data-placement' => 'top');

        $subnetyTab = Html::el('table')->setClass('table table-striped');
        $tr = $subnetyTab->create('tr');
        $tr->create('th')->setText('Subnet');
        $tr->create('th')->setText('Popis');

        foreach ($subnets as $subnet) {
            $tr = $subnetyTab->create('tr');
            $tr->create('td')->setText($subnet->subnet);
            $tr->create('td')->setText($subnet->popis);
        }
        return($subnetyTab);
    }
}
