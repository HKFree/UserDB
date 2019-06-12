<?php

namespace App\Model;

use Nette,
    DateInterval,
    Nette\Database\Context,
    Nette\Utils\Random;


/**
 * @author
 */
class AplikaceLog extends Table
{
    /**
     * @var string
     */
    protected $tableName = 'AplikaceLog';

    private $request;

    public function __construct(
        Nette\Database\Context $db,
        Nette\Security\User $user,
        Nette\Http\Request $request
    ) {
        parent::__construct($db, $user);
        $this->request = $request;
    }


    public function log($action, $data = array())
    {
        return($this->insert(array(
            'action' => $action,
            'ip' => $this->request->getRemoteAddress(),
            'time' => new Nette\Utils\DateTime,
            'data' => json_encode($data)
        )));
    }

    public function getLogy()
    {
        return($this->findAll());
    }
}
