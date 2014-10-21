<?php

namespace App\Model;

use Nette,
    Nette\Utils\Html;



/**
 * @author 
 */
class Subnet extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'Subnet';

    public function getSeznamSubnetu()
    {
	//$row = $this->findAll();
        return($this->findAll());
    }

    public function getSubnet($id)
    {
        return($this->find($id));
    }

    /*public function deleteSubnet(array $ips)
    {
	if(count($ips)>0)
	    return($this->delete(array('id' => $ips)));
	else
	    return true;
    }*/
    
    public function getSubnetTable($subnets) {
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