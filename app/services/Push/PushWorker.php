<?php

namespace App\Services\Push;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Nette\Database\Explorer;
use Tracy\Debugger;

/** Zpracování fronty PushNotifikace a úklid (R10, R14). */
class PushWorker
{
    public const DORUCENI_DNU = 10;

    public function __construct(
        private Explorer $db,
        private PushPrijemci $prijemci,
        private array $vapid, // ['subject' => ..., 'publicKey' => ..., 'privateKey' => ...]
    ) {
    }

    /** @return int počet zpracovaných notifikací */
    public function zpracuj(): int {
        $this->uklid();
        $hotovo = 0;
        foreach ($this->db->table('PushNotifikace')->where('stav', 'cekajici')->order('id')->limit(20)->fetchAll() as $n) {
            // Zámek proti souběžnému workeru: notifikaci zpracuje jen ten, komu se povede změnit stav
            if ($this->db->table('PushNotifikace')->where(['id' => $n->id, 'stav' => 'cekajici'])->update(['stav' => 'odesila']) !== 1) {
                continue;
            }
            $pocet = $this->odesli($n);
            $n->update(['stav' => 'odeslano', 'odeslano' => new \DateTime(), 'pocet_prijemcu' => $pocet]);
            $hotovo++;
        }
        return $hotovo;
    }

    private function odesli($n): int {
        $odbery = $this->prijemci->odbery($n->PushKanal_id, $n->rozsah, $n->Oblast_id, $n->Ap_id);
        if (!$odbery) {
            return 0;
        }
        // Bez přesměrování: endpoint je ověřen při uložení, redirect by kontrolu obešel (H10)
        $webPush = new WebPush(['VAPID' => $this->vapid], ['TTL' => 86400], 10, ['allow_redirects' => false]);
        $payload = json_encode(['titulek' => $n->titulek, 'text' => $n->text, 'url' => $n->url], JSON_THROW_ON_ERROR);
        $podleEndpointu = [];
        foreach ($odbery as $o) {
            $podleEndpointu[$o->endpoint] = $o->id;
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $o->endpoint,
                'keys' => ['p256dh' => $o->p256dh, 'auth' => $o->auth],
                'contentEncoding' => 'aes128gcm',
            ]), $payload);
        }
        foreach ($webPush->flush() as $report) {
            $odberId = $podleEndpointu[$report->getEndpoint()] ?? null;
            $this->db->table('PushDoruceni')->insert([
                'PushNotifikace_id' => $n->id,
                'PushOdber_id' => $odberId,
                'http_kod' => $report->getResponse()?->getStatusCode(),
                'uspech' => (int) $report->isSuccess(),
                'vytvoreno' => new \DateTime(),
            ]);
            if (!$report->isSuccess()) {
                Debugger::log("notifikace {$n->id}, odber $odberId: " . $report->getReason(), 'push');
            }
            if ($report->isSubscriptionExpired() && $odberId) {
                $this->db->table('PushOdber')->where('id', $odberId)->delete();
            }
        }
        return count($odbery);
    }

    /** Staré doručenky, subscriptions zrušených členů a bývalých správců (R5, R14, H11). */
    private function uklid(): void {
        $this->db->table('PushDoruceni')->where('vytvoreno < ?', new \DateTime('-' . self::DORUCENI_DNU . ' days'))->delete();
        $this->db->query("DELETE o FROM PushOdber o JOIN Uzivatel u ON u.id = o.Uzivatel_id
            WHERE o.publikum = 'clenove' AND NOT COALESCE(" . PushPrijemci::AKTIVNI_CLEN . ', 0)');
        $this->db->query("DELETE o FROM PushOdber o WHERE o.publikum = 'spravci' AND NOT EXISTS (
            SELECT 1 FROM SpravceOblasti s WHERE s.Uzivatel_id = o.Uzivatel_id AND " . PushPrijemci::AKTIVNI_ROLE . ')');
    }
}
