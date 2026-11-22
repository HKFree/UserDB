<?php

declare(strict_types=1);

namespace App\MapiModule;

use Nette\Application\Response;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Utils\Json;

/**
 * Odpověď /userdb/mapi/: stavový kód, hlavičky a tělo v JSON. Žádná odpověď se nesmí ukládat do cache.
 */
final class Odpoved implements Response
{
    /** @var array<string, string> */
    public readonly array $hlavicky;
    private readonly string $json;

    /**
     * Tělo se kóduje hned, aby nezakódovatelná data selhala ještě v presenteru, ne až při odesílání.
     * @param array<string, mixed> $telo
     * @param array<string, string> $hlavicky
     */
    public function __construct(
        public readonly int $stav,
        public readonly array $telo,
        array $hlavicky = [],
    ) {
        $this->hlavicky = ['Cache-Control' => 'no-store'] + $hlavicky;
        $this->json = Json::encode($telo);
    }

    /** @param array<string, string> $hlavicky */
    public static function chyba(int $stav, KodChyby $kod, string $zprava, array $hlavicky = []): self {
        return new self($stav, ['errors' => [['message' => $zprava, 'extensions' => ['code' => $kod->value]]]], $hlavicky);
    }

    /**
     * @return list<string> kódy chyb v těle, každý jednou
     */
    public function kodyChyb(): array {
        $kody = [];
        foreach ($this->telo['errors'] ?? [] as $chyba) {
            $kody[] = $chyba['extensions']['code'];
        }
        return array_values(array_unique($kody));
    }

    public function send(IRequest $httpRequest, IResponse $httpResponse): void {
        $httpResponse->setCode($this->stav);
        $httpResponse->setContentType('application/json', 'utf-8');
        foreach ($this->hlavicky as $jmeno => $hodnota) {
            $httpResponse->setHeader($jmeno, $hodnota);
        }
        echo $this->json;
    }
}
