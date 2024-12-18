<?php

namespace App\Services;

use Nette;
use App\Model;
use DateTime;

class RequestDruzstvoContract
{
    private $connection;
    private Model\Log $logger;

    public function __construct(
        Nette\Database\Connection $connection,
        Model\Log $logger,
    ) {
        $this->connection = $connection;
        $this->logger = $logger;
    }

    public function execute(int $userId): int {
        $now = new DateTime();

        $this->connection->query('INSERT INTO Smlouva ?', [
            'Uzivatel_id' => $userId,
            'typ' => 'ucastnicka',
            'kdy_vygenerovano' => $now
        ]);
        $newId = $this->connection->getInsertId();

        $cmd = sprintf("%s/../bin/console app:digisign_generovat_ucastnickou_smlouvu %u", getenv('CONTEXT_DOCUMENT_ROOT'), $newId);
        $cmd2 = "$cmd | sed -u 's/^/digisign_generovat_ucastnickou_smlouvu /' &";
        error_log("RUN: [$cmd2]", );
        proc_close(proc_open($cmd2, array(), $foo));

        $log = [];
        $new_data = [
            'id' => $newId,
            'Uzivatel_id' => $userId,
            'typ' => 'ucastnicka',
            'kdy_vygenerovano' => $now
        ];
        $this->logger->logujInsert($new_data, 'Smlouva', $log);
        $this->logger->loguj('Smlouva', $newId, $log);

        return (int) $newId;
    }
}
