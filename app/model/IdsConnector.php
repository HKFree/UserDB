<?php

namespace App\Model;


use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Tracy\Debugger;

class IdsConnector
{
    private $idsUsername;
    private $idsPassword;
    private $idsUrl;
    private $idsIpsWhitelist;

    /**
     * Nedulezite a nevyladene (caste false-positive) typy udalosti:
     */
    const IGNORED_ALERTS = [
        ['match_phrase' => ['alert.category.raw' => 'Potential Corporate Privacy Violation'],],
        ['match_phrase' => ['alert.category.raw' => 'Potentially Bad Traffic'],],
        ['match_phrase' => ['alert.category.raw' => 'Not Suspicious Traffic'],],
        ['match_phrase' => ['alert.signature.raw' => 'ET SCAN Potential SSH Scan OUTBOUND'],],  // false-positive u ruznych multi-git / multi-ssh klientu apod.
        ['match_phrase' => ['alert.signature.raw' => 'HKFree rule HOME->EXT, track by_src, Generic Potential Attack Attempt'],], // false-positive u speedtest.net
        ['match_phrase' => ['alert.signature.raw' => 'HKFree rule HOME->EXT,UDP,track by_src,Generic Potential UDP DOS Attempt'],], // false-positive zrejme pri syncu GDrive
        ['match_phrase' => ['alert.signature.raw' => 'ET CNC Zeus Tracker Reported CnC Server'],], // spousta false-positives kvuli neupdatovani pravidel 13.9.2019-18.9.2019
        ['match_phrase' => ['tags.raw' => 'archived'],], // nebudeme brat v potaz ani zaarchivovane eventy (tlacitko archive v eveboxu)
    ];

    /**
     * IdsConnector constructor.
     */
    public function __construct(string $idsUrl, string $idsUsername, string $idsPassword, string $idsIpsWhitelist)
    {
        $this->idsUrl = $idsUrl;
        $this->idsUsername = $idsUsername;
        $this->idsPassword = $idsPassword;
        $this->idsIpsWhitelist = explode(',', $idsIpsWhitelist);
    }

    protected function getElasticHttpClient(): \GuzzleHttp\Client
    {
        $stack = HandlerStack::create();
        $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
            $contentsRequest = (string)$request->getBody();
            //var_dump($contentsRequest);   // debug GuzzleHttp requests
            return $request;
        }));


        $client = new \GuzzleHttp\Client(['verify' => false, 'handler' => $stack]);
        $jar = new \GuzzleHttp\Cookie\CookieJar();

        $loginFormResponse = $client->request('GET', $this->idsUrl, ['cookies' => $jar]);
        if (preg_match('/csrfmiddlewaretoken.*value=\'(.+)\'/', $loginFormResponse->getBody(), $matches)) {
            $csrfToken = $matches[1];
            $headers = ['Referer' => $this->idsUrl.'/accounts/login/'];
            $client->request(
                'POST',
                $this->idsUrl.'/accounts/login/',
                [
                    'cookies' => $jar,
                    'headers' => $headers,
                    'form_params' => [
                        'username' => $this->idsUsername,
                        'password' => $this->idsPassword,
                        'csrfmiddlewaretoken' => $csrfToken,
                    ]
                ]
            );
            $headers2 = [ 'kbn-xsrf' => 'reporting' ];
            return new \GuzzleHttp\Client([
                'verify' => false,
                'handler' => $stack,
                'headers' => $headers2,
                'cookies' => $jar,
            ]);
        }
        throw new \RuntimeException('Error getting IDS CSRF token');
    }

    protected function getRange($daysBack): array
    {
        return ['range' => ['@timestamp' => ['gte' => 'now-' . $daysBack . 'd', 'lte' => 'now']]];
    }

    private function getRelevantIndexes($prefix, $daysBack) {
        //
        $indexes = [];
        $date = new \DateTime();
        $date->setTimezone(new \DateTimeZone('GMT'));
        //$indexes = [$prefix.'2019.01.22',$prefix.'2019.01.23'];
        for ($i = 0; $i <= $daysBack; $i++) {
            $indexes []= $prefix.$date->format('Y.m.d');;
            $date = $date->modify('-1 day');
        }
        return $indexes;
    }

    public function getEventsForIps(array $ips, $daysBack=7, $limit=1000)
    {
        $client = $this->getElasticHttpClient();
        $indexes = implode(',', $this->getRelevantIndexes('logstash-alert-', $daysBack));

        $elasticFilter =
            [
                'json' => [
                        'size' => $limit,
                        'query' => [
                            'bool' => [
                                'must' => [
                                    [
                                        // "match_phrase" => ["src_ip.raw" => '10.107.212.241']],
                                        // "wildcard" => ["src_ip.raw" => '10.107.212.*']],
                                        'terms' => ['src_ip.raw' => $ips]
                                    ],
                                    $this->getRange($daysBack)
                                ],
                                'must_not' => self::IGNORED_ALERTS
                            ]
                        ],
                        'sort' => [
                            [
                                '@timestamp' => ['order' => 'desc']
                            ]
                        ]
                    ]
            ];

        $elasticResponse = $client->request('POST', $this->idsUrl.'/elasticsearch/'.$indexes.'/_search?ignore_unavailable=true', $elasticFilter);
        $json = json_decode($elasticResponse->getBody(), true);
        if ($json) {
            return $json['hits']['hits'];
        } else {
            throw new \RuntimeException('Empty response from IDS, maybe wrong IDS username/password?');
        }
    }

    public function getUniqueIpsFromPrivateSubnets($daysBack=7, $limit=2000)
    {
        $client = $this->getElasticHttpClient();
        $indexes = implode(',', $this->getRelevantIndexes('logstash-alert-', $daysBack));
        $elasticResponse = $client->request('POST', $this->idsUrl.'/elasticsearch/'.$indexes.'/_search?ignore_unavailable=true',
            [
                'json' => [
                    'size' => 0,
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                     "wildcard" => ["src_ip.raw" => '10.107.*'],
                                ],
                                $this->getRange($daysBack)
                            ],
                            'must_not' => self::IGNORED_ALERTS
                        ]
                    ],
                    'aggs' => [
                        'uniq_ip' => [
                            'terms' => [
                                'field' => 'src_ip.raw',
                                'size' => $limit,
                            ],
                        ],
                    ],
                ]
            ]
        );
        $json = json_decode($elasticResponse->getBody(), true);
        $resultArray = $json['aggregations']['uniq_ip']['buckets'];
        $resultArrayFiltered = array_filter($resultArray, function($k) {
            return !in_array($k['key'], $this->idsIpsWhitelist, true);
        });
        if ($json) {
            return $resultArrayFiltered;
        } else {
            throw new \RuntimeException('Empty response from IDS, maybe wrong IDS username/password?');
        }
    }
}
