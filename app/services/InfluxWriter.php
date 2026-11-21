<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;

final class InfluxWriter
{
    private string $endpoint;
    private string $database;
    /** @var array{string, string}|null */
    private ?array $auth;
    private ClientInterface $client;

    public function __construct(string $influxUrl, ?ClientInterface $client = null) {
        $url = parse_url($influxUrl);
        if ($url === false || !in_array($url['scheme'] ?? '', ['influxdb', 'http+influxdb', 'https+influxdb'], true)
            || ($url['host'] ?? '') === '' || ($url['path'] ?? '/') === '/' || isset($url['query']) || isset($url['fragment'])) {
            throw new \InvalidArgumentException('Invalid INFLUX_URL; expected influxdb://user:password@host:port/database.');
        }
        $scheme = $url['scheme'] === 'https+influxdb' ? 'https' : 'http';
        $this->endpoint = $scheme . '://' . $url['host'] . ':' . ($url['port'] ?? 8086) . '/write';
        $this->database = rawurldecode(substr($url['path'], 1));
        $this->auth = isset($url['user']) ? [urldecode($url['user']), urldecode($url['pass'] ?? '')] : null;
        $this->client = $client ?? new Client();
    }

    /**
     * @param array<string, string> $tags
     * @param array<string, string|float> $fields
     */
    public function writePoint(string $measurement, array $tags, array $fields): void {
        $line = $this->escapeKey($measurement, false);
        foreach ($tags as $key => $value) {
            $line .= ',' . $this->escapeKey($key) . '=' . $this->escapeKey($value);
        }
        $values = [];
        foreach ($fields as $key => $value) {
            $values[] = $this->escapeKey($key) . '=' . $this->encodeValue($value);
        }
        if ($values === []) {
            throw new \InvalidArgumentException('InfluxDB point needs at least one field.');
        }

        try {
            $response = $this->client->request('POST', $this->endpoint, [
                'query' => ['db' => $this->database, 'precision' => 'n'],
                'auth' => $this->auth,
                'headers' => ['Content-Type' => 'text/plain; charset=utf-8'],
                'body' => $line . ' ' . implode(',', $values),
                'connect_timeout' => 5,
                'timeout' => 15,
                'allow_redirects' => false,
                'http_errors' => false,
            ]);
        } catch (TransferException) {
            // Transport exceptions can contain credentials or the full request URI.
            throw new \RuntimeException('InfluxDB write failed: connection error.');
        }
        if (!in_array($response->getStatusCode(), [200, 204], true)) {
            throw new \RuntimeException('InfluxDB write failed: HTTP ' . $response->getStatusCode() . '.');
        }
    }

    private function escapeKey(string $value, bool $equals = true): string {
        $this->rejectLineBreaks($value);
        if ($value === '') {
            throw new \InvalidArgumentException('InfluxDB names and tags must not be empty.');
        }
        $escapes = [' ' => '\\ ', ',' => '\\,'];
        if ($equals) {
            $escapes['='] = '\\=';
        }
        return strtr($value, $escapes);
    }

    private function encodeValue(string|float $value): string {
        if (is_string($value)) {
            $this->rejectLineBreaks($value);
            return '"' . strtr($value, ['\\' => '\\\\', '"' => '\\"']) . '"';
        }
        if (!is_finite($value)) {
            throw new \InvalidArgumentException('InfluxDB numeric fields must be finite.');
        }
        // PHP 8 float-to-string conversion is locale independent; no integer suffix.
        return (string) $value;
    }

    private function rejectLineBreaks(string $value): void {
        if (strpbrk($value, "\r\n") !== false) {
            throw new \InvalidArgumentException('InfluxDB values must not contain line breaks.');
        }
    }
}
