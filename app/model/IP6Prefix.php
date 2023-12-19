<?php

namespace App\Model;

use Nette;
use Nette\Utils\Html;

/**
 * @author vpithart@lhota.hkfree.org
 */
class IP6Prefix extends Table
{
    /**
    * @var string
    */
    protected $tableName = 'IP6Prefix';

    public function getSeznamIP6Prefixu()
    {
        return($this->findAll());
	}

	public function findIp(array $by) {
		return($this->findOneBy($by));
    }

    public function getIP6Prefix($id)
    {
        return($this->find($id));
    }

    public function getSeznamIP6PrefixuZacinajicich($prefix)
    {
        return $this->findAll()->where("prefix LIKE ?", $prefix.'%')->fetchPairs('prefix');
    }

    public function getDuplicateIP($prefix, $id)
    {
        $existujici = $this->findAll()->where('prefix = ?', $prefix)->where('id != ?', $id)->fetch();
        if($existujici)
        {
            return $existujici->ip_adresa;
        }
        return null;
    }

    public function getIPForm(&$ip, $typyZarizeni, $apMode=false) {
		$ip->addHidden('id')->setAttribute('class', 'id ip');
		$ip->addText('ip_adresa', 'IP Adresa', 11)->setAttribute('class', 'ip_adresa ip')->setAttribute('placeholder', 'IP Adresa');
		$ip->addText('hostname', 'Hostname', 11)->setAttribute('class', 'hostname ip')->setAttribute('placeholder', 'Hostname');
		$ip->addSelect('TypZarizeni_id', 'Typ Zařízení', $typyZarizeni)->setAttribute('class', 'TypZarizeni_id ip')->setPrompt('--Vyberte--');;
		$ip->addCheckbox('internet', 'Internet')->setAttribute('class', 'internet ip')->setDefaultValue(1);
		$ip->addCheckbox('smokeping', 'Smokeping')->setAttribute('class', 'smokeping ip');
		$ip->addText('login', 'Login', 11)->setAttribute('class', 'login ip')->setAttribute('placeholder', 'Login');
		$ip->addText('heslo', 'Heslo', 11)->setAttribute('class', 'heslo ip')->setAttribute('placeholder', 'Heslo');
		$ip->addText('mac_adresa', 'MAC Adresa', 24)->setAttribute('class', 'mac_adresa ip')->setAttribute('placeholder', 'MAC Adresa');
		$ip->addCheckbox('mac_filter', 'MAC Filtr')->setAttribute('class', 'mac_filter ip');
		$ip->addCheckbox('dhcp', 'DHCP')->setAttribute('class', 'dhcp ip');
		if ($apMode) {
			$ip->addCheckbox('wewimo', 'Wewimo')->setAttribute('class', 'wewimo ip');
		}
		$ip->addText('popis', 'Popis')->setAttribute('class', 'popis ip')->setAttribute('placeholder', 'Popis');
    }

	/**
	 * @param $ip6prefixes zaznamy z tabulky IP6Prefix
	 * @return mixed
	 */
    public function getIP6PrefixTable($ip6prefixes)
	{
		$adresyTab = Html::el('table')->setClass('table table-striped table-responsive');
		$this->addIPTableHeader($adresyTab);

		foreach ($ip6prefixes as $ip6prefix)
		{
			$this->addIPTableRow($ip6prefix, $adresyTab);
		}

		return $adresyTab;
	}

	public function addIPTableHeader($adresyTab, $canViewCredentialsOrEdit=false, $subnetMode=false, $igwCheck=false)
	{
		$tr = $adresyTab->create('tr');

		$tr->create('th')->setAttribute('width', '225px')->setText('Prefix/délka');
		$tr->create('th')->setAttribute('width', '100px'); // action buttons
		$tr->create('th')->setAttribute('width', '140px')->create('nobr')->setText('Příchozí spojení');
		$tr->create('th')->setAttribute('width', 'auto')->setText('Poznámka');
	}

	/**
	 * @param Nette\Utils\Html $tr
	 * @param  Nette\Utils\Html[] $buttons
	 */
	private function addActionButtonsTd($tr, $buttons) {
		$td = $tr->create('td');
		foreach ($buttons as $b) {
			$td->addHtml($b);
		}
	}

	public function addIPTableRow($ip6prefix, $adresyTab)
	{
		$tooltips = array('data-toggle' => 'tooltip', 'data-placement' => 'top');

		$title = $ip6prefix->prefix . '/' . $ip6prefix->length;

		$tr = $adresyTab->create('tr');
		$tr->setId('highlightable-ip'.($ip6prefix->prefix ));

        $tr->create('td')->setText($title);

        // action buttons
        $buttons = array();

        // edit button, etc.
        array_unshift($buttons,
            Html::el('a')
                ->setHref('editLink')
                ->setClass('btn btn-default btn-xs btn-in-table')
                ->setTitle('Editovat')
                ->addAttributes($tooltips)
                ->addHtml(Html::el('span')
                ->setClass('glyphicon glyphicon-pencil'))
        ); // edit button

        $this->addActionButtonsTd($tr, $buttons);

        $attr = $tr->create('td'); // atributy
        $attr->create('span')
            ->setClass($ip6prefix->povolit_prichozi_spojeni ? 'glyphicon glyphicon-log-in' : 'glyphicon glyphicon-remove')
            ->setTitle($ip6prefix->povolit_prichozi_spojeni ? 'povolené příchozí spojení z internetu (žádný firewall)' : 'zablokováno')
            ->addAttributes($tooltips);

        $tr->create('td')->setText($ip6prefix->poznamka);

		return $tr;
    }
}
