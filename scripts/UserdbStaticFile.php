<?php

declare(strict_types=1);

/**
 * A file of www/ sent by the router of the local web and of the preview, with what the browser keeps it by.
 * PHP's own server sends files without it (and drops headers the router sets before leaving a file to it),
 * so the browser downloaded every style and script again on each page.
 */
final class UserdbStaticFile
{
    /** Types PHP's own server would send too; a file of another type is still left to it. */
    private const TYPES = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8',
        'map' => 'application/json',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'ico' => 'image/vnd.microsoft.icon',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
    ];

    /** A file with a version in its address (screen.css?v56) gets a new address when it changes. */
    private const VERSIONED = 'private, max-age=31536000, immutable';

    /** Other files are kept for a few minutes, then checked against ETag and Last-Modified. */
    private const UNVERSIONED = 'private, max-age=300';

    /**
     * Sends the file, or only that it has not changed.
     * @param array<string, mixed> $server $_SERVER of the request
     * @return bool false when the file is left to PHP's own server
     */
    public static function send(string $file, array $server): bool {
        $type = self::TYPES[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? null;
        $method = $server['REQUEST_METHOD'] ?? 'GET';
        $modified = filemtime($file);
        $size = filesize($file);
        if ($type === null || in_array($method, ['GET', 'HEAD'], true) === false || $modified === false || $size === false) {
            return false;
        }

        $etag = sprintf('"%x-%x"', $modified, $size);
        $query = $server['QUERY_STRING'] ?? '';
        header('Cache-Control: ' . (is_string($query) && preg_match('~^v\d+$~', $query) === 1 ? self::VERSIONED : self::UNVERSIONED));
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
        if (self::isUnchanged($server, $etag, $modified)) {
            http_response_code(304);
            return true;
        }
        header('Content-Type: ' . $type);
        header('Content-Length: ' . $size);
        if ($method === 'GET') {
            readfile($file);
        }
        return true;
    }

    /** @param array<string, mixed> $server */
    private static function isUnchanged(array $server, string $etag, int $modified): bool {
        $match = $server['HTTP_IF_NONE_MATCH'] ?? null;
        if (is_string($match)) {
            // When the browser names its version, the date is not looked at (RFC 9110, 13.2.2).
            $tags = array_map(static fn (string $tag): string => preg_replace('~^W/~', '', trim($tag)) ?? '', explode(',', $match));
            return in_array($etag, $tags, true) || in_array('*', $tags, true);
        }
        // Only a date of HTTP counts; strtotime() would also take "tomorrow" (RFC 9110, 13.1.3).
        $since = $server['HTTP_IF_MODIFIED_SINCE'] ?? null;
        $sinceTime = is_string($since) ? DateTimeImmutable::createFromFormat('!D, d M Y H:i:s \G\M\T', $since, new DateTimeZone('UTC')) : false;
        return $sinceTime !== false && $modified <= $sinceTime->getTimestamp();
    }
}
