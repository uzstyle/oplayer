<?php
namespace Art;

class LastFM {
    
    private static $root = "http://ws.audioscrobbler.com/2.0/";
    
    public static function request($conf, $method, $params) {
        $apiKey = $conf->getOption('app', 'lastfmapikey');
        $expired = $conf->getOption('cache', 'artists') ;
        $qparams = http_build_query($params);
        $q = self::$root . "?method={$method}&format=json&api_key={$apiKey}&{$qparams}";
        
        $cacheKey = "lastfm_".sha1($q).".json";

        $data = \Model\Cache::find('one', array('conditions' => array(
            '`expiredAt` > ? AND `key` = ?', date( "Y-m-d H:i:s", time()), $cacheKey
        )));

        if ( !$data ) {
            $ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_URL, $q);
			$resp = curl_exec($ch);
			curl_close($ch);
            //$resp = file_get_contents($q);

            $data = new \Model\Cache;
            $data->key = $cacheKey;
            $data->data = $resp;
            $data->expiredat = date( "Y-m-d H:i:s", time() + $expired );
            
            $data->save();
        }

        $data = json_decode($data->data);
        return $data;
    }
    
}
