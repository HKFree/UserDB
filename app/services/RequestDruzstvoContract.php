<?php

namespace App\Services;

use Nette;
use App\Model\Log;

class RequestDruzstvoContract
{
    private Nette\Database\Connection $connection;
    private Log $log;

    public function __construct(Nette\Database\Connection $connection, Log $log) {
        $this->connection = $connection;
        $this->log = $log;
    }

    /** @param int|null $autorId kdo smlouvu vyžádal; bez něj přihlášený uživatel */
    public function execute(int $userId, ?int $autorId = null): int {
        $data = [
            'typ' => 'ucastnicka',
            'kdy_vygenerovano' => new \Nette\Utils\DateTime()
        ];
        $this->connection->query('INSERT INTO Smlouva ?', ['Uzivatel_id' => $userId] + $data);
        $newId = $this->connection->getInsertId();
        $entries = [];
        $this->log->logujInsert($data, 'Smlouva[' . $newId . ']', $entries);
        $this->log->loguj('Uzivatel', $userId, $entries, $autorId);

        $cmd = sprintf("%s/../bin/console app:digisign_generovat_ucastnickou_smlouvu %u", getenv('CONTEXT_DOCUMENT_ROOT'), $newId);
        $cmd2 = "$cmd | sed -u 's/^/digisign_generovat_ucastnickou_smlouvu /' &";
        error_log("RUN: [$cmd2]", );
        $process = proc_open($cmd2, array(), $foo);
        if ($process === false) {
            throw new Nette\InvalidStateException('Nelze spustit generování smlouvy.');
        }
        proc_close($process);

        // Nette returns the generated ID as a string; this service exposes an integer ID.
        return (int) $newId;
    }

}
