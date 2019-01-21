<?php

namespace App\Model;


use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;

class IdsConnector
{
    private $idsUsername;
    private $idsPassword;
    private $idsUrl;

    /**
     * IdsConnector constructor.
     */
    public function __construct(string $idsUrl, string $idsUsername, string $idsPassword)
    {
        $this->idsUrl = $idsUrl;
        $this->idsUsername = $idsUsername;
        $this->idsPassword = $idsPassword;
    }

    public function getEventsForIps(array $ips, $daysBack=7, $limit=1000)
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
            $elasticResponse = $client->request('POST', $this->idsUrl.'/elasticsearch/logstash-alert-*/_search',
                [
                    'cookies' => $jar,
                    'headers' => $headers2,
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
                                        ['range' => [
                                            '@timestamp' => [
                                                'gte' => 'now-' .$daysBack. 'd',
                                                'lte' => 'now'
                                            ]
                                        ]
                                        ]
                                    ],
                                    'must_not' => [
                                        // nedulezite udalosti:
                                        [
                                            'match_phrase' => ['alert.category.raw' => 'Potential Corporate Privacy Violation'],
                                        ],
                                        [
                                            'match_phrase' => ['alert.category.raw' => 'Potentially Bad Traffic'],
                                        ]
                                    ]
                                ]
                            ],
                            'sort' => [
                                [
                                    '@timestamp' => ['order' => 'desc']
                                ]
                            ]
                        ]
                ]
            );
            $json = json_decode($elasticResponse->getBody(), true);
            if ($json) {
                return $json['hits']['hits'];
            } else {
                throw new \RuntimeException('Empty response from IDS, maybe wrong IDS username/password?');
            }
        } else {
            throw new \RuntimeException('Error getting IDS CSRF token');
        }
    }
}
