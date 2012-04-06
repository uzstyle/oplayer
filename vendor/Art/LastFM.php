<?php
namespace Art;

class LastFM {
    
    private static $root = "http://ws.audioscrobbler.com/2.0/";
    
    public static function request($conf, $method, $params) {
        $apiKey = $conf->getOption('app', 'lastfmapikey');
        
        $qparams = http_build_query($params);
        $q = self::$root . "?method={$method}&format=json&api_key={$apiKey}&{$qparams}";
        
        $cacheKey = "lastfm_".sha1($q).".json";

        $data = \Model\Cache::find('one', array('conditions' => array(
            '`expiredAt` > ? AND `key` = ?', time(), $cacheKey
        )));

        if ( !$data ) {
            $resp = file_get_contents($q);

            $data = new \Model\Cache;
            $data->key = $cacheKey;
            $data->data = $resp;
            $data->expiredat = date( "Y-m-d H:i:s", time() + $conf->getOption('cache', 'artists') );
            
            $data->save();
        }

        $data = json_decode($data->data);
        return $data;
    }
    
}
