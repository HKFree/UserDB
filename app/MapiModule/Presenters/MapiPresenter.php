<?php

declare(strict_types=1);

namespace App\MapiModule\Presenters;

use App\MapiModule\Data\PovoleneRole;
use App\MapiModule\Data\Prihlaseny;
use App\MapiModule\Data\RolePrihlaseneho;
use App\MapiModule\KodChyby;
use App\MapiModule\Odpoved;
use App\MapiModule\Vyrizeni;
use App\MapiModule\Zaznam;
use App\Model\HkfIdentity;
use App\Settings;
use Nette\Application;
use Nette\Http;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\User;

/**
 * Společný základ API pro přihlášené uživatele pod /userdb/mapi/. Nedědí z BasePresenter: odpovídá jen JSON,
 * i když odmítá nebo selže. Pořadí kontrol je závazné (issue #264, H9): metoda, Content-Type, CSRF, přihlášení,
 * povolená role. Teprve pak dostane požadavek potomek.
 */
abstract class MapiPresenter implements Application\IPresenter
{
    private const HLAVICKA_CSRF = 'X-UserDB-Request';

    /** UID pro záznam o přístupu; známé až po přihlášení. */
    private ?int $uid = null;

    public function __construct(
        private readonly Http\IRequest $httpRequest,
        private readonly User $user,
        private readonly Settings $settings,
        private readonly Zaznam $zaznam,
    ) {
    }

    /**
     * Obslouží požadavek uživatele, který prošel všemi kontrolami.
     */
    abstract protected function vyrid(Prihlaseny $kdo, ?string $telo): Vyrizeni;

    final public function run(Application\Request $request): Application\Response {
        try {
            return $this->odpovezSeZaznamem();
        } catch (\Throwable) {
            // Selhal sám záznam. Bez záznamu o přístupu data nevydáme a chybová stránka webu sem nepatří.
            return Odpoved::chyba(500, KodChyby::Interni, KodChyby::ZPRAVA_INTERNI);
        }
    }

    private function odpovezSeZaznamem(): Odpoved {
        try {
            $vyrizeni = $this->obsluz();
        } catch (\Throwable $vyjimka) {
            $this->zaznam->vyjimka($vyjimka);
            $vyrizeni = new Vyrizeni(Odpoved::chyba(500, KodChyby::Interni, KodChyby::ZPRAVA_INTERNI));
        }
        $kody = $vyrizeni->odpoved->kodyChyb();
        $this->zaznam->pristup($this->uid, $vyrizeni->operace, $kody === [] ? 'OK' : implode(',', $kody));
        return $vyrizeni->odpoved;
    }

    private function obsluz(): Vyrizeni {
        $odmitnuti = $this->odmitnutiPozadavku();
        if ($odmitnuti !== null) {
            return new Vyrizeni($odmitnuti);
        }
        try {
            $kdo = $this->prihlas();
        } catch (AuthenticationException $chyba) {
            return new Vyrizeni($this->odmitnutiPrihlaseni($chyba));
        }
        $this->uid = $kdo->uid;
        if (PovoleneRole::maNekterou($kdo) === false) {
            return new Vyrizeni(self::bezPovoleneRole());
        }
        return $this->vyrid($kdo, $this->httpRequest->getRawBody());
    }

    /**
     * Kontroly, které nepotřebují vědět, kdo se ptá. Přihlášení je přes cookie, proto dotaz nesmí jít
     * vyvolat z cizí stránky: jen POST s JSON, vlastní hlavičkou a vlastním původem.
     */
    private function odmitnutiPozadavku(): ?Odpoved {
        if ($this->httpRequest->isMethod('POST') === false) {
            return Odpoved::chyba(405, KodChyby::SpatnyVstup, 'Použijte metodu POST.', ['Allow' => 'POST']);
        }
        if ($this->maJsonTelo() === false) {
            return Odpoved::chyba(415, KodChyby::SpatnyVstup, 'Pošlete tělo jako Content-Type: application/json.');
        }
        if ($this->jeZVlastniStranky() === false) {
            return Odpoved::chyba(403, KodChyby::Odepreno, 'Požadavek musí přijít ze stránek UserDB s hlavičkou ' . self::HLAVICKA_CSRF . ': 1.');
        }
        return null;
    }

    private function maJsonTelo(): bool {
        $typ = explode(';', $this->httpRequest->getHeader('Content-Type') ?? '', 2)[0];
        return strtolower(trim($typ)) === 'application/json';
    }

    private function jeZVlastniStranky(): bool {
        if ($this->httpRequest->getHeader(self::HLAVICKA_CSRF) !== '1') {
            return false;
        }
        $puvod = $this->httpRequest->getHeader('Origin');
        return $puvod === null || strcasecmp($puvod, $this->httpRequest->getUrl()->getHostUrl()) === 0;
    }

    /**
     * Přihlásí stejně jako BasePresenter::startup(): UID z hlavičky od Shibbolethu a role z Authenticatoru webu,
     * při debug.fakeUser nastavená osoba. Dál jde jen Prihlaseny, identita webu ne.
     * @throws AuthenticationException
     */
    private function prihlas(): Prihlaseny {
        $uid = $_SERVER['HTTP_UID'] ?? '';
        $this->user->login((bool) $this->settings->fakeUser ? 'DBG' : (is_string($uid) ? $uid : ''), null);
        $identita = $this->user->getIdentity();
        if (($identita instanceof HkfIdentity) === false) {
            throw new \LogicException('Přihlášení nevrátilo identitu UserDB.');
        }
        return new Prihlaseny($identita->getUid(), $identita->getNick(), array_map(RolePrihlaseneho::zTextu(...), array_values($identita->getRoles())));
    }

    /**
     * 401 pro nepřihlášeného, 403 pro přihlášeného bez platné role, aby klient poznal vypršené přihlášení.
     */
    private function odmitnutiPrihlaseni(AuthenticationException $chyba): Odpoved {
        if ($chyba->getCode() !== Authenticator::NotApproved) {
            return Odpoved::chyba(401, KodChyby::Neprihlasen, 'Nejste přihlášeni. Přihlaste se do UserDB a zkuste to znovu.');
        }
        $this->uid = $this->uidBezRole();
        return self::bezPovoleneRole();
    }

    private static function bezPovoleneRole(): Odpoved {
        return Odpoved::chyba(403, KodChyby::Odepreno, 'Vaše role k tomuto API neopravňují.');
    }

    /**
     * UID, pro které Authenticator nenašel platnou roli, pro záznam o přístupu.
     */
    private function uidBezRole(): ?int {
        $uid = is_array($this->settings->fakeUser) ? $this->settings->fakeUser['userID'] : ($_SERVER['HTTP_UID'] ?? null);
        return is_int($uid) || (is_string($uid) && ctype_digit($uid)) ? (int) $uid : null;
    }
}
