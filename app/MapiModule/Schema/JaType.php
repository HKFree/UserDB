<?php

declare(strict_types=1);

namespace App\MapiModule\Schema;

use App\MapiModule\Data\Ja;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

final class JaType extends ObjectType
{
    public function __construct(RoleType $role, OblastType $oblast) {
        parent::__construct([
            'name' => 'Ja',
            'description' => 'Přihlášený uživatel.',
            'fields' => [
                'uid' => [
                    'type' => Type::nonNull(Type::int()),
                    'description' => 'Číslo člena (UID).',
                    'resolve' => static fn (Ja $ja): int => $ja->uid,
                ],
                'nick' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => 'Přezdívka.',
                    'resolve' => static fn (Ja $ja): string => $ja->nick,
                ],
                'role' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($role))),
                    'description' => 'Všechny platné role, i ty, které k tomuto API neopravňují.',
                    'resolve' => static fn (Ja $ja): array => $ja->role,
                ],
                'oblasti' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($oblast))),
                    'description' => 'Oblasti, kde mám roli vázanou na oblast, v pořadí menu webu.',
                    'resolve' => static fn (Ja $ja): array => $ja->oblasti,
                ],
            ],
        ]);
    }
}
