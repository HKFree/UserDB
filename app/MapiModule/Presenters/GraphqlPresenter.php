<?php

declare(strict_types=1);

namespace App\MapiModule\Presenters;

use App\MapiModule\Data\Prihlaseny;
use App\MapiModule\Graphql\Zpracovani;
use App\MapiModule\Vyrizeni;
use App\MapiModule\Zaznam;
use App\Settings;
use Nette\Http;
use Nette\Security\User;

/**
 * GraphQL API pro přihlášené uživatele: POST /userdb/mapi/graphql. Smlouvou je app/MapiModule/schema.graphql.
 */
final class GraphqlPresenter extends MapiPresenter
{
    public function __construct(
        Http\IRequest $httpRequest,
        User $user,
        Settings $settings,
        Zaznam $zaznam,
        private readonly Zpracovani $zpracovani,
    ) {
        parent::__construct($httpRequest, $user, $settings, $zaznam);
    }

    protected function vyrid(Prihlaseny $kdo, ?string $telo): Vyrizeni {
        return $this->zpracovani->zpracuj($kdo, $telo);
    }
}
