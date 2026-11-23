<?php

declare(strict_types=1);

namespace App\Presenters;

/**
 * Nápověda k tlačítku Winbox u IP adres RouterBoardů. Odkaz winbox: otevírá handler ze samostatného
 * repozitáře HKFree/winbox-uri-handler; příkazy na stránce instalují jeho poslední vydání z GitHubu.
 * Stránka je dostupná každé identitě přihlášené přes BasePresenter.
 */
final class WinboxPresenter extends BasePresenter
{
    public const REPOZITAR = 'https://github.com/HKFree/winbox-uri-handler';
    private const STAZENI = self::REPOZITAR . '/releases/latest/download/';
    /** Adresa z rozsahu pro dokumentaci (RFC 5737): Winbox ji předvyplní a nikam se nepřipojí. */
    private const ZKUSEBNI_ODKAZ = 'winbox:192.0.2.1';
    /** Záložky stránky v pořadí, v jakém se ukazují. */
    private const SYSTEMY = ['windows', 'macos', 'linux', 'android'];

    /** @param string|null $system záložka, která se otevře; bez ní ta podle systému návštěvníka */
    public function renderDefault(?string $system = null): void {
        $instalatorWindows = self::STAZENI . 'winbox-uri-handler.ps1';
        $instalatorMacosLinux = self::STAZENI . 'winbox-uri-handler.sh';
        $this->template->system = in_array($system, self::SYSTEMY, true)
            ? $system
            : self::systemNavstevnika($this->getHttpRequest()->getHeader('User-Agent') ?? '');
        $this->template->repozitar = self::REPOZITAR;
        $this->template->zkusebniOdkaz = self::ZKUSEBNI_ODKAZ;
        $this->template->instalaceWindows = 'irm ' . $instalatorWindows . ' -OutFile $env:TEMP\winbox-uri-handler.ps1;'
            . ' powershell -ExecutionPolicy Bypass -File $env:TEMP\winbox-uri-handler.ps1';
        $this->template->odinstalaceWindows = 'powershell -ExecutionPolicy Bypass -File'
            . ' "$env:LOCALAPPDATA\Winbox URI Handler\winbox-uri-handler.ps1" -Uninstall';
        $this->template->instalaceMacosLinux = 'curl -fsSL ' . $instalatorMacosLinux . ' | bash';
        $this->template->odinstalaceMacosLinux = 'curl -fsSL ' . $instalatorMacosLinux . ' | bash -s -- --uninstall';
    }

    /**
     * Záložka podle user agentu. Android hlásí i Linux a iPhone i Mac OS X, proto se zkoušejí první;
     * pro iPhone a neznámý systém je Windows, nejčastější počítač.
     */
    private static function systemNavstevnika(string $userAgent): string {
        return match (true) {
            stripos($userAgent, 'android') !== false => 'android',
            preg_match('/iPhone|iPad|iPod/i', $userAgent) === 1 => 'windows',
            stripos($userAgent, 'windows') !== false => 'windows',
            preg_match('/Macintosh|Mac OS X/i', $userAgent) === 1 => 'macos',
            preg_match('/Linux|X11|CrOS/i', $userAgent) === 1 => 'linux',
            default => 'windows',
        };
    }
}
