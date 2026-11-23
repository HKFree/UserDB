<?php

declare(strict_types=1);

namespace App\MapiModule\Schema;

use App\MapiModule\Data\Oblast;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

final class OblastType extends ObjectType
{
    public function __construct(ApType $ap) {
        parent::__construct([
            'name' => 'Oblast',
            'description' => 'Oblast sítě.',
            'fields' => [
                'id' => [
                    'type' => Type::nonNull(Type::int()),
                    'resolve' => static fn (Oblast $oblast): int => $oblast->id,
                ],
                'jmeno' => [
                    'type' => Type::nonNull(Type::string()),
                    'resolve' => static fn (Oblast $oblast): string => $oblast->jmeno,
                ],
                'ap' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($ap))),
                    'description' => 'Jen aktivní AP oblasti, podle jména.',
                    'resolve' => static fn (Oblast $oblast): array => $oblast->ap,
                ],
            ],
        ]);
    }
}
