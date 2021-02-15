<?php

namespace App\Model;

use Nette,
    GuzzleHttp\Client;

/**
 * Sojka connector
 */
class Sojka
{
    /**
    * @var string
    */
    protected $sojkaPingerURL;
    
    public function __construct($sojkaPingerURL)
    {
        $this->sojkaPingerURL = $sojkaPingerURL;
    }   
    
    /**
     * Get latest pinger results from Sojka Pinger
     * 
     * @param string[] $ips
     * @return object
     */
    public function pingIPS($ips) 
    {
        if(empty($ips)) {
            return([]);
        }
        
        $client = new Client();
        
        try {
            $r = $client->request('POST', $this->sojkaPingerURL . '?rq=pingjson', [
                'json' => $ips
            ]);
        } catch (\GuzzleHttp\Exception\TransferException $e) {
            return([]);
        }
        
        if($r->getStatusCode() != 200) {
            return([]);
        }
        
        $body = $r->getBody();        
        return(json_decode($body, True));
    }
}
