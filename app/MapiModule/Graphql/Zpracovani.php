<?php

declare(strict_types=1);

namespace App\MapiModule\Graphql;

use App\MapiModule\Data\Prihlaseny;
use App\MapiModule\Data\PristupOdepren;
use App\MapiModule\KodChyby;
use App\MapiModule\Odpoved;
use App\MapiModule\Schema\SchemaFactory;
use App\MapiModule\Vyrizeni;
use App\MapiModule\Zaznam;
use GraphQL\Error\Error;
use GraphQL\Error\SyntaxError;
use GraphQL\Executor\Executor;
use GraphQL\Executor\Values;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\SourceLocation;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;

/**
 * Od těla požadavku k odpovědi GraphQL. Chyba požadavku (JSON, syntaxe, tvar operace, validace, limity,
 * proměnné) končí 400 bez dat a dotaz se nevykoná. Chyba pole při vykonání je 200 s částečnými daty.
 */
final class Zpracovani
{
    public function __construct(
        private readonly int $maxHloubka,
        private readonly int $maxSlozitost,
        private readonly SchemaFactory $schemaFactory,
        private readonly Zaznam $zaznam,
    ) {
    }

    public function zpracuj(Prihlaseny $kdo, ?string $telo): Vyrizeni {
        try {
            $pozadavek = Pozadavek::zJson($telo);
        } catch (ChybaPozadavku $chyba) {
            return new Vyrizeni(Odpoved::chyba(400, KodChyby::SpatnyVstup, $chyba->getMessage()));
        }
        // Do záznamu jde jméno dotazu z dokumentu; dokud není známé, platí jméno, které uvádí požadavek.
        $jmeno = $pozadavek->jmenoOperace === null ? null : Pozadavek::jmenoProZaznam($pozadavek->jmenoOperace);
        try {
            $dokument = Parser::parse($pozadavek->dotaz);
            $operace = self::operace($dokument);
            if (count($operace) === 1 && $operace[0]->name !== null) {
                $jmeno = Pozadavek::jmenoProZaznam($operace[0]->name->value);
            }
            $dotaz = self::jedinyDotaz($operace, $pozadavek);
        } catch (ChybaPozadavku $chyba) {
            return new Vyrizeni(Odpoved::chyba(400, KodChyby::SpatnyVstup, $chyba->getMessage()), $jmeno);
        } catch (SyntaxError $chyba) {
            return new Vyrizeni(self::spatnyVstup([$chyba]), $jmeno);
        }

        $schema = $this->schemaFactory->vytvor();
        $chyby = $this->chybyDotazu($schema, $dokument, $dotaz, $pozadavek);
        if ($chyby !== []) {
            return new Vyrizeni(self::spatnyVstup($chyby), $jmeno);
        }
        return new Vyrizeni($this->vykonej($schema, $dokument, $kdo, $pozadavek), $jmeno);
    }

    /** @return list<OperationDefinitionNode> */
    private static function operace(DocumentNode $dokument): array {
        $operace = [];
        foreach ($dokument->definitions as $definice) {
            if ($definice instanceof OperationDefinitionNode) {
                $operace[] = $definice;
            }
        }
        return $operace;
    }

    /**
     * @param list<OperationDefinitionNode> $operace
     * @throws ChybaPozadavku
     */
    private static function jedinyDotaz(array $operace, Pozadavek $pozadavek): OperationDefinitionNode {
        if (count($operace) !== 1) {
            throw new ChybaPozadavku('Požadavek musí obsahovat právě jednu operaci.');
        }
        $dotaz = $operace[0];
        if ($dotaz->operation !== 'query') {
            throw new ChybaPozadavku('Povolené jsou jen dotazy (query).');
        }
        if ($dotaz->name === null) {
            throw new ChybaPozadavku('Dotaz musí mít jméno.');
        }
        if ($pozadavek->jmenoOperace !== null && $pozadavek->jmenoOperace !== $dotaz->name->value) {
            throw new ChybaPozadavku('Pole "operationName" neodpovídá jménu dotazu.');
        }
        return $dotaz;
    }

    /**
     * Validace proti schématu, limity hloubky a složitosti a nakonec hodnoty proměnných.
     * Pravidla vznikají pro každý požadavek znovu; sdílená pravidla knihovny se nemění.
     * @return array<int, Error>
     */
    private function chybyDotazu(Schema $schema, DocumentNode $dokument, OperationDefinitionNode $dotaz, Pozadavek $pozadavek): array {
        $slozitost = new QueryComplexity($this->maxSlozitost);
        $slozitost->setRawVariableValues($pozadavek->promenne);
        $pravidla = [...array_values(DocumentValidator::defaultRules()), new QueryDepth($this->maxHloubka), $slozitost];
        $chyby = DocumentValidator::validate($schema, $dokument, $pravidla);
        if ($chyby !== []) {
            return $chyby;
        }
        [$chybyPromennych] = Values::getVariableValues($schema, $dotaz->variableDefinitions, $pozadavek->promenne);
        return $chybyPromennych ?? [];
    }

    private function vykonej(Schema $schema, DocumentNode $dokument, Prihlaseny $kdo, Pozadavek $pozadavek): Odpoved {
        $vysledek = Executor::execute($schema, $dokument, null, $kdo, $pozadavek->promenne);
        $telo = [];
        if ($vysledek->errors !== []) {
            $telo['errors'] = array_map($this->chybaPole(...), $vysledek->errors);
        }
        $telo['data'] = $vysledek->data;
        return new Odpoved(200, $telo);
    }

    /**
     * Při vykonání dostane vlastní kód jen výjimka čtecí vrstvy. Všechno ostatní je nečekaná chyba:
     * klientovi obecná zpráva, podrobnosti do logu.
     * @return array<string, mixed>
     */
    private function chybaPole(Error $chyba): array {
        $pricina = $chyba->getPrevious();
        if ($pricina instanceof PristupOdepren) {
            $popis = ['message' => 'Nemáte oprávnění.', 'extensions' => ['code' => KodChyby::Odepreno->value]];
        } else {
            $this->zaznam->vyjimka($pricina ?? $chyba);
            $popis = ['message' => KodChyby::ZPRAVA_INTERNI, 'extensions' => ['code' => KodChyby::Interni->value]];
        }
        $popis += self::mista($chyba);
        if ($chyba->getPath() !== null) {
            $popis['path'] = $chyba->getPath();
        }
        return $popis;
    }

    /**
     * Chyby zjištěné před vykonáním: zprávu knihovny o vlastním dotazu klient vidět smí.
     * @param array<int, Error> $chyby
     */
    private static function spatnyVstup(array $chyby): Odpoved {
        return new Odpoved(400, ['errors' => array_map(
            static fn (Error $chyba): array => ['message' => $chyba->getMessage(), 'extensions' => ['code' => KodChyby::SpatnyVstup->value]] + self::mista($chyba),
            array_values($chyby),
        )]);
    }

    /** @return array{locations?: list<array{line: int, column: int}>} */
    private static function mista(Error $chyba): array {
        $mista = array_map(static fn (SourceLocation $misto): array => $misto->toSerializableArray(), array_values($chyba->getLocations()));
        return $mista === [] ? [] : ['locations' => $mista];
    }
}
