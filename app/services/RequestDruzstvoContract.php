<?php

namespace App\Services;

use Nette;

class RequestDruzstvoContract
{
    private $connection;
    public function __construct(Nette\Database\Connection $connection) {
        $this->connection = $connection;
    }

    public function execute(int $userId): int {
        $this->connection->query('INSERT INTO Smlouva ?', [
            'Uzivatel_id' => $userId,
            'typ' => 'ucastnicka',
            'kdy_vygenerovano' => new \Nette\Utils\DateTime()
        ]);
        $newId = $this->connection->getInsertId();

        $cmd = sprintf("%s/../bin/console app:digisign_generovat_ucastnickou_smlouvu %u", getenv('CONTEXT_DOCUMENT_ROOT'), $newId);
        $cmd2 = "$cmd | sed -u 's/^/digisign_generovat_ucastnickou_smlouvu /' &";
        error_log("RUN: [$cmd2]", );
        proc_close(proc_open($cmd2, array(), $foo));

        return $newId;
    }

}
