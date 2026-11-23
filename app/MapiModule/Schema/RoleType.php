<?php

declare(strict_types=1);

namespace App\MapiModule\Schema;

use App\MapiModule\Data\Oblast;
use App\MapiModule\Data\Role;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

final class RoleType extends ObjectType
{
    public function __construct(OblastType $oblast) {
        parent::__construct([
            'name' => 'Role',
            'description' => 'Platná role přihlášeného.',
            'fields' => [
                'typ' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => 'Typ role: VV, TECH, SO, ZSO a další podle číselníku.',
                    'resolve' => static fn (Role $role): string => $role->typ,
                ],
                'oblast' => [
                    'type' => $oblast,
                    'description' => 'Oblast, na kterou je role vázaná; null u globální role.',
                    'resolve' => static fn (Role $role): ?Oblast => $role->oblast,
                ],
            ],
        ]);
    }
}
