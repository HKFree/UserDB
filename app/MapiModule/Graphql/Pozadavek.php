<?php

declare(strict_types=1);

namespace App\MapiModule\Graphql;

/**
 * Tělo požadavku GraphQL: právě jeden dotaz, žádné dávky.
 */
final readonly class Pozadavek
{
    /** Gramatika jména GraphQL, nejvýše 64 znaků; jen takové jméno operace smí do záznamu o přístupu. */
    private const JMENO_PRO_ZAZNAM = '~^[_A-Za-z][_0-9A-Za-z]{0,63}$~D';
    private const CHYBI_DOTAZ = 'Tělo požadavku musí být objekt JSON s textem dotazu v poli "query".';

    /** @param array<string, mixed> $promenne */
    private function __construct(
        public string $dotaz,
        public ?string $jmenoOperace,
        public array $promenne,
    ) {
    }

    /**
     * @throws ChybaPozadavku
     */
    public static function zJson(?string $telo): self {
        try {
            $data = json_decode($telo ?? '', true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ChybaPozadavku('Tělo požadavku není platný JSON.');
        }
        if (!is_array($data)) {
            throw new ChybaPozadavku(self::CHYBI_DOTAZ);
        }
        $dotaz = $data['query'] ?? null;
        $jmenoOperace = $data['operationName'] ?? null;
        $promenne = $data['variables'] ?? [];
        if (!is_string($dotaz)) {
            throw new ChybaPozadavku(self::CHYBI_DOTAZ);
        }
        if ($jmenoOperace !== null && !is_string($jmenoOperace)) {
            throw new ChybaPozadavku('Pole "operationName" musí být text.');
        }
        if (!is_array($promenne) || ($promenne !== [] && array_is_list($promenne))) {
            throw new ChybaPozadavku('Pole "variables" musí být objekt.');
        }
        return new self($dotaz, $jmenoOperace, $promenne);
    }

    /**
     * Jméno operace smí do záznamu o přístupu jen tehdy, když odpovídá gramatice jména GraphQL.
     */
    public static function jmenoProZaznam(string $jmeno): ?string {
        return preg_match(self::JMENO_PRO_ZAZNAM, $jmeno) === 1 ? $jmeno : null;
    }
}
