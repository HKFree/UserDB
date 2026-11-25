<?php

declare(strict_types=1);

/**
 * IDS of the local web and the preview: disconnected, so it has no events. Without the debug mode (the preview)
 * the pages would otherwise fail on the error of the connection the application lets through in production.
 */
final class UserdbLocalIdsConnector extends App\Model\IdsConnector
{
    public function __construct() {
        parent::__construct('http://127.0.0.1:0/', 'local-only', 'local-only', '');
    }

    /** @param array<array-key, string> $ips */
    public function getEventsForIps(array $ips, int $daysBack = 7, int $limit = 1000): mixed {
        return [];
    }

    /** @return array<array-key, mixed> */
    public function getUniqueIpsFromPrivateSubnets(int $daysBack = 7, int $limit = 2000): array {
        return [];
    }
}
