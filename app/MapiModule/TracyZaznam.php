<?php

declare(strict_types=1);

namespace App\MapiModule;

use Tracy\ILogger;

/**
 * Záznam přes Tracy: přístupy do log/mapi.log, nečekané chyby do log/exception.log.
 */
final class TracyZaznam implements Zaznam
{
    private const UROVEN_PRISTUPU = 'mapi';

    public function __construct(
        private readonly ILogger $logger,
    ) {
    }

    public function pristup(?int $uid, ?string $operace, string $vysledek): void {
        $this->logger->log(sprintf('uid=%s operace=%s vysledek=%s', $uid ?? '-', $operace ?? '-', $vysledek), self::UROVEN_PRISTUPU);
    }

    /**
     * Zapisuje text, ne výjimku samotnou: Tracy by k výjimce uložila i argumenty volání,
     * a v nich text dotazu a jeho proměnné.
     */
    public function vyjimka(\Throwable $vyjimka): void {
        $retez = [];
        for ($dalsi = $vyjimka; $dalsi !== null; $dalsi = $dalsi->getPrevious()) {
            $retez[] = $dalsi;
        }
        $popisy = array_map(
            static fn (\Throwable $clanek): string => sprintf('%s: %s in %s:%d', $clanek::class, $clanek->getMessage(), $clanek->getFile(), $clanek->getLine()),
            $retez,
        );
        $pricina = $retez[count($retez) - 1];
        $this->logger->log(implode(' caused by ', $popisy) . ' | ' . implode(' | ', $this->stopa($pricina)), ILogger::EXCEPTION);
    }

    /**
     * @return list<string> kroky volání bez argumentů
     */
    private function stopa(\Throwable $vyjimka): array {
        $kroky = [];
        foreach ($vyjimka->getTrace() as $krok) {
            $kroky[] = sprintf('%s%s%s() %s:%d', $krok['class'] ?? '', $krok['type'] ?? '', $krok['function'], $krok['file'] ?? '?', $krok['line'] ?? 0);
        }
        return $kroky;
    }
}
