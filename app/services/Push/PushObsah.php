<?php

namespace App\Services\Push;

/** Validace obsahu notifikace a endpointu subscription (R11, H5, H10). */
final class PushObsah
{
    public const MAX_TITULEK = 80;
    public const MAX_TEXT = 250;

    /** @return string[] chybové hlášky, prázdné pole = v pořádku */
    public static function chyby(string $titulek, string $text, ?string $url): array {
        $chyby = [];
        if (trim($titulek) === '' || mb_strlen($titulek) > self::MAX_TITULEK) {
            $chyby[] = 'Titulek musí mít 1–' . self::MAX_TITULEK . ' znaků.';
        }
        if (trim($text) === '' || mb_strlen($text) > self::MAX_TEXT) {
            $chyby[] = 'Text musí mít 1–' . self::MAX_TEXT . ' znaků.';
        }
        if ($url !== null && $url !== '' && !self::jePovolenaUrl($url)) {
            $chyby[] = 'Odkaz musí vést na https://…hkfree.org.';
        }
        return $chyby;
    }

    /** Striktní regex místo parse_url – prohlížeč by např. `\` vyložil jinak (obejití domény). */
    public static function jePovolenaUrl(string $url): bool {
        return strlen($url) <= 255
            && preg_match('~^https://([a-z0-9-]+\.)*hkfree\.org([/?#][^\s\\\\]*)?$~iD', $url) === 1;
    }

    /** @param string[] $povolene např. "https://fcm.googleapis.com", "https://*.push.apple.com" */
    public static function jePovolenyEndpoint(string $endpoint, array $povolene): bool {
        $e = parse_url($endpoint);
        // Jen tisknutelné ASCII (sloupec je ascii) a bez `\` – obejití kontroly hostu
        if (!$e || isset($e['user']) || strlen($endpoint) > 1000 || !preg_match('~^[\x21-\x7e]+$~D', $endpoint) || str_contains($endpoint, '\\')) {
            return false;
        }
        foreach ($povolene as $vzor) {
            $v = parse_url($vzor);
            if (($e['scheme'] ?? '') === $v['scheme'] && ($e['port'] ?? null) === ($v['port'] ?? null)
                && self::hostOdpovida(strtolower($e['host'] ?? ''), $v['host'])) {
                return true;
            }
        }
        return false;
    }

    /** Klíče subscription musí jít použít k šifrování, jinak by worker selhal pro celou dávku. */
    public static function jsouPlatneKlice(mixed $p256dh, mixed $auth): bool {
        return is_string($p256dh) && is_string($auth)
            && ($k = self::base64url($p256dh)) !== null && strlen($k) === 65 && $k[0] === "\x04"
            && ($a = self::base64url($auth)) !== null && strlen($a) === 16;
    }

    private static function base64url(string $s): ?string {
        if (!preg_match('~^[A-Za-z0-9_-]+={0,2}$~D', $s)) {
            return null;
        }
        $bin = base64_decode(strtr(rtrim($s, '='), '-_', '+/'), true);
        return $bin === false ? null : $bin;
    }

    /** "*.example.org" odpovídá example.org i libovolné subdoméně. */
    private static function hostOdpovida(string $host, string $vzor): bool {
        if (!str_starts_with($vzor, '*.')) {
            return $host === $vzor;
        }
        $zaklad = substr($vzor, 2);
        return $host === $zaklad || str_ends_with($host, '.' . $zaklad);
    }
}
