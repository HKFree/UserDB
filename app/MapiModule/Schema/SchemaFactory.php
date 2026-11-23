<?php

declare(strict_types=1);

namespace App\MapiModule\Schema;

use App\MapiModule\Data\Cteni;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;

/**
 * Schéma GraphQL API. Jeho otisk je v app/MapiModule/schema.graphql a mění se s ním ve stejné změně.
 * Schéma má jen dotazy: žádné mutace ani odběry.
 */
final class SchemaFactory
{
    public function __construct(
        private readonly Cteni $cteni,
    ) {
    }

    public function vytvor(): Schema {
        $oblast = new OblastType(new ApType());
        $ja = new JaType(new RoleType($oblast), $oblast);
        return new Schema(SchemaConfig::create()->setQuery(new QueryType($this->cteni, $ja)));
    }
}
