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
        if (!$e || isset($e['user']) || strlen($endpoint) > 1000 || preg_match('~[\s\\\\]~', $endpoint)) {
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

    /** "*.example.org" odpovídá example.org i libovolné subdoméně. */
    private static function hostOdpovida(string $host, string $vzor): bool {
        if (!str_starts_with($vzor, '*.')) {
            return $host === $vzor;
        }
        $zaklad = substr($vzor, 2);
        return $host === $zaklad || str_ends_with($host, '.' . $zaklad);
    }
}
