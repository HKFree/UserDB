<?php

declare(strict_types=1);

namespace App\MapiModule\Schema;

use App\MapiModule\Data\Ap;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

final class ApType extends ObjectType
{
    public function __construct() {
        parent::__construct([
            'name' => 'Ap',
            'description' => 'Přístupový bod (AP).',
            'fields' => [
                'id' => [
                    'type' => Type::nonNull(Type::int()),
                    'resolve' => static fn (Ap $ap): int => $ap->id,
                ],
                'jmeno' => [
                    'type' => Type::nonNull(Type::string()),
                    'resolve' => static fn (Ap $ap): string => $ap->jmeno,
                ],
            ],
        ]);
    }
}
