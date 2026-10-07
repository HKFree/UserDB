<?php

namespace App\Services\Push;

use Nette\Database\Explorer;
use Tracy\Debugger;

/** Vytvoření notifikace ve frontě: kanál, rozsah, oprávnění, obsah, limit (R8, R9, R11, R12). */
class PushOdesilani
{
    public const LIMIT_ZA_HODINU = 5;

    public function __construct(private Explorer $db) {
    }

    /**
     * @param array{kanal: string, rozsah: string, cil_id: ?int, titulek: string, text: string, url: ?string} $n
     * @param string[] $role role uživatele (prázdné pro API klíč)
     * @throws PushException
     */
    public function vytvor(array $n, ?int $uzivatelId, array $role = [], ?int $apiKlicId = null): int {
        $kanal = $this->db->table('PushKanal')->where('kod', $n['kanal'])->where('aktivni', 1)->fetch()
            ?: throw new PushException('Neznámý kanál.', 404);
        [$oblastId, $apId] = $this->cil($n['rozsah'], $n['cil_id'] ?? null);

        $povoleno = $apiKlicId === null
            ? PushOpravneni::smiOdeslat($role, $n['rozsah'], $oblastId)
            : $this->smiKlic($apiKlicId, $kanal->id, $n['rozsah']);
        if (!$povoleno) {
            throw new PushException('Do tohoto kanálu nebo rozsahu nemáte oprávnění posílat.', 403);
        }

        $url = ($n['url'] ?? '') === '' ? null : $n['url'];
        if ($chyby = PushObsah::chyby($n['titulek'], $n['text'], $url)) {
            throw new PushException(implode(' ', $chyby), 400);
        }

        $sloupec = $apiKlicId === null ? 'odesilatel_Uzivatel_id' : 'odesilatel_ApiKlic_id';
        $odesilatel = $apiKlicId ?? $uzivatelId;
        $odeslano = $this->db->table('PushNotifikace')->where($sloupec, $odesilatel)
            ->where('vytvoreno > ?', new \DateTime('-1 hour'))->count('*');
        if ($odeslano >= self::LIMIT_ZA_HODINU) {
            Debugger::log("limit: $sloupec=$odesilatel", 'push');
            throw new PushException('Překročen limit ' . self::LIMIT_ZA_HODINU . ' notifikací za hodinu.', 429);
        }

        return $this->db->table('PushNotifikace')->insert([
            'PushKanal_id' => $kanal->id,
            'rozsah' => $n['rozsah'],
            'Oblast_id' => $n['rozsah'] === 'oblast' ? $oblastId : null,
            'Ap_id' => $apId,
            'titulek' => $n['titulek'],
            'text' => $n['text'],
            'url' => $url,
            $sloupec => $odesilatel,
            'vytvoreno' => new \DateTime(),
        ])->id;
    }

    /** @return array{?int, ?int} [oblast pro kontrolu oprávnění, AP] */
    private function cil(string $rozsah, ?int $cilId): array {
        if ($rozsah === 'sit') {
            return [null, null];
        }
        if ($rozsah === 'oblast') {
            $oblast = $this->db->table('Oblast')->get((int) $cilId) ?: throw new PushException('Neznámá oblast.', 404);
            return [$oblast->id, null];
        }
        if ($rozsah === 'ap') {
            $ap = $this->db->table('Ap')->get((int) $cilId) ?: throw new PushException('Neznámé AP.', 404);
            return [$ap->Oblast_id, $ap->id];
        }
        throw new PushException('Neplatný rozsah.', 400);
    }

    private function smiKlic(int $apiKlicId, int $kanalId, string $rozsah): bool {
        $pravo = $this->db->table('ApiKlic_PushKanal')
            ->where(['ApiKlic_id' => $apiKlicId, 'PushKanal_id' => $kanalId])->fetch();
        return $pravo && ($rozsah !== 'sit' || $pravo->smi_globalne);
    }
}
