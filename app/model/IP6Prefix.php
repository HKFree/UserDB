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

    public function getPrefixForm(&$ip) {
		$ip->addHidden('id')->setAttribute('class', 'id ip');
		$ip->addText('prefix', 'IPv6 Prefix', 35)->setAttribute('class', '')->setAttribute('placeholder', 'IPv6 Prefix');
        $ip->addSelect('length', 'Délka', [48=>48,56=>56])->setDefaultValue(48);
		$ip->addCheckbox('povolit_prichozi_spojeni', 'Povolit příchozí spojení')->setAttribute('class', '')->setDefaultValue(0);
		$ip->addText('poznamka', 'Poznámka', 46)->setAttribute('class', '')->setAttribute('placeholder', 'Poznámka');
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
		$tr->setId('highlightable-ip'.($ip6prefix->prefix));

        $tr->create('td')->setText($title);

        // action buttons
        $buttons = array();

        // edit button, etc.
        array_unshift($buttons,
            Html::el('a')
                ->setHref('../edit/' . $ip6prefix->Uzivatel_id . '#ip' . $ip6prefix->prefix)
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
