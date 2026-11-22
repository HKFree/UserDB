<?php

namespace App\Model;

use Nette;
use DateInterval;
use Nette\Database\Context;
use Nette\Utils\Random;

/**
 * @author
 */
class AplikaceLog extends Table
{
    /**
     * @var string
     */
    protected $tableName = 'AplikaceLog';

    private Nette\Http\Request $request;

    public function __construct(
        Nette\Database\Context $db,
        Nette\Security\User $user,
        Nette\Http\Request $request
    ) {
        parent::__construct($db, $user);
        $this->request = $request;
    }

    /** @return Nette\Database\Table\ActiveRow|array<array-key, mixed>|int|bool */
    public function log(string $action, mixed $data = array()): Nette\Database\Table\ActiveRow|array|int|bool {
        return ($this->insert(array(
            'action' => $action,
            'ip' => $this->request->getRemoteAddress(),
            'time' => new Nette\Utils\DateTime(),
            'data' => json_encode($data)
        )));
    }

    /** @return Nette\Database\Table\Selection<Nette\Database\Table\ActiveRow> */
    public function getLogy(): Nette\Database\Table\Selection {
        return ($this->findAll());
    }
}
