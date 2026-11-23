<?php

declare(strict_types=1);

namespace App\MapiModule\Schema;

use App\MapiModule\Data\Cteni;
use App\MapiModule\Data\Ja;
use App\MapiModule\Data\Prihlaseny;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

final class QueryType extends ObjectType
{
    public function __construct(Cteni $cteni, JaType $ja) {
        parent::__construct([
            'name' => 'Query',
            'fields' => [
                'ja' => [
                    'type' => Type::nonNull($ja),
                    'description' => 'Kdo jsem: přihlášený uživatel, jeho role a oblasti.',
                    'resolve' => static fn (mixed $koren, array $argumenty, Prihlaseny $kdo): Ja => $cteni->ja($kdo),
                ],
            ],
        ]);
    }
}
