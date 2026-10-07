<?php

namespace App\Services\Push;

use Nette\Database\Explorer;

/** Subscriptions a preference přihlášeného uživatele; vše je vázané na $uid (H2, H3, H10). */
class PushOdbery
{
    /** @param string[] $povoleneEndpointy viz PushObsah::jePovolenyEndpoint */
    public function __construct(private Explorer $db, private array $povoleneEndpointy) {
    }

    /** @param array{endpoint?: mixed, keys?: array{p256dh?: mixed, auth?: mixed}} $sub JSON z PushSubscription */
    public function uloz(int $uid, string $publikum, array $sub, ?string $zarizeni): void {
        $endpoint = $sub['endpoint'] ?? null;
        $p256dh = $sub['keys']['p256dh'] ?? null;
        $auth = $sub['keys']['auth'] ?? null;
        $base64url = '~^[A-Za-z0-9_-]+={0,2}$~D';
        if (!is_string($endpoint) || !PushObsah::jePovolenyEndpoint($endpoint, $this->povoleneEndpointy)
            || !is_string($p256dh) || strlen($p256dh) > 255 || !preg_match($base64url, $p256dh)
            || !is_string($auth) || strlen($auth) > 64 || !preg_match($base64url, $auth)) {
            throw new PushException('Neplatná subscription.', 400);
        }
        // Cizí subscription lze převzít jen se stejným tajemstvím auth, tj. ze stejného prohlížeče
        $puvodni = $this->db->table('PushOdber')->where('endpoint_hash', hash('sha256', $endpoint))->fetch();
        if ($puvodni && $puvodni->Uzivatel_id !== $uid && !hash_equals($puvodni->auth, $auth)) {
            throw new PushException('Toto zařízení je přihlášené k odběru jiného uživatele.', 409);
        }
        $this->db->query('INSERT INTO PushOdber ? ON DUPLICATE KEY UPDATE Uzivatel_id = VALUES(Uzivatel_id),
            publikum = VALUES(publikum), p256dh = VALUES(p256dh), auth = VALUES(auth), zarizeni = VALUES(zarizeni)', [
            'Uzivatel_id' => $uid,
            'publikum' => $publikum,
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
            'p256dh' => $p256dh,
            'auth' => $auth,
            'zarizeni' => $zarizeni === null ? null : mb_substr($zarizeni, 0, 255),
            'vytvoreno' => new \DateTime(),
        ]);
    }

    public function smaz(int $uid, int $odberId): void {
        $this->db->table('PushOdber')->where(['id' => $odberId, 'Uzivatel_id' => $uid])->delete();
    }

    public function smazEndpoint(int $uid, string $endpoint): void {
        $this->db->table('PushOdber')->where(['endpoint_hash' => hash('sha256', $endpoint), 'Uzivatel_id' => $uid])->delete();
    }

    public function zarizeni(int $uid, string $publikum): array {
        return $this->db->table('PushOdber')->where(['Uzivatel_id' => $uid, 'publikum' => $publikum])->order('vytvoreno DESC')->fetchAll();
    }

    /** @return array<int, array{kanal: \Nette\Database\Table\ActiveRow, zapnuto: bool}> kanály daného publika s efektivním stavem */
    public function kanaly(int $uid, string $publikum): array {
        $pref = $this->db->table('PushPreference')->where('Uzivatel_id', $uid)->fetchPairs('PushKanal_id', 'zapnuto');
        $out = [];
        foreach ($this->db->table('PushKanal')->where(['publikum' => $publikum, 'aktivni' => 1])->order('nazev') as $k) {
            $out[$k->id] = ['kanal' => $k, 'zapnuto' => (bool) ($pref[$k->id] ?? $k->vychozi_zapnuto)];
        }
        return $out;
    }

    public function nastav(int $uid, string $publikum, int $kanalId, bool $zapnuto): void {
        if (!isset($this->kanaly($uid, $publikum)[$kanalId])) {
            throw new PushException('Neznámý kanál.', 404);
        }
        $this->db->query('REPLACE INTO PushPreference ?', ['Uzivatel_id' => $uid, 'PushKanal_id' => $kanalId, 'zapnuto' => (int) $zapnuto]);
    }
}
