<?php
namespace OpenPlayer;

// file caching
class Cache {
  public static $cacheRoot = "/assets";

  public static function set( $key, $value, $time = 60 ) {
    $data = new \Model\Cache;
    $data->key = $key;
    $data->data = $value;
    $data->expiredat = date( "Y-m-d H:i:s", time() + $time );

    $data->save();
  }
    
  public static function get( $key ) {
    $data = \Model\Cache::find('one', array('conditions' => array(
      '`expiredAt` > ? AND `key` = ?', time(), $key
    )));

    return $data ? $data->data : null;
  }
    
  public static function clear( $key ) {
    $cache = \Model\Cache::find('one', array('conditions' => array(
      '`expiredAt` > ? AND `key` = ?', time(), $key
    )));
    
    return $cache ? $cache->delete() : false;
  }
}

class Core {
  private $email;
  private $pass;
  private $appId;
  private $uagent = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_2) AppleWebKit/535.1 (KHTML, like Gecko) Chrome/14.0.835.202 Safari/535.1";
    
  public function __construct( $email, $pass, $appId, $uagent = null ) {
    $this->email = $email;
    $this->pass = $pass;
    $this->appId = $appId;

    if ( $uagent ) {
      $this->uagent = $uagent;
    }
  }
    
  public function getToken() {
    $cookies = ROOT . Cache::$cacheRoot . '/cookie_' . sha1(date('d_m_y') . 'token' . $this->email) . '.txt';
    $cacheKey = "access_token_".sha1($this->email).".txt";
    $token = Cache::get($cacheKey);

    if ( !$token ) {

      $ch0 = curl_init();
      // curl_setopt($ch0, CURLOPT_HEADER, 1);
      curl_setopt($ch0, CURLOPT_RETURNTRANSFER, 1);
      // curl_setopt($ch0, CURLOPT_POST, 1);
           // curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt($ch0, CURLOPT_USERAGENT, $this->uagent);
      curl_setopt($ch0, CURLOPT_COOKIEFILE, $cookies);
      curl_setopt($ch0, CURLOPT_COOKIEJAR, $cookies);
      // curl_setopt($ch0, CURLOPT_POSTFIELDS, "act=login&q=1&al_frame=1&expire=&captcha_sid=&captcha_key=&from_host=vk.com&from_protocol=http&ip_h=&email={$this->email}&pass={$this->pass}");
      curl_setopt($ch0, CURLOPT_URL, 'http://m.vk.com/login');
      // curl_setopt($ch0, CURLOPT_REFERER, 'http://vk.com/');
      
      $body = $this->curl_redirect_exec($ch0);
      curl_close($ch0);
      preg_match_all("/ip_h=(\w+)/", $body, $matches);
      $ip_h = $matches[1][0];

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_HEADER, 1);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_POST, 1);
//            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt($ch, CURLOPT_USERAGENT, $this->uagent);
      curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
      curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
      curl_setopt($ch, CURLOPT_POSTFIELDS, "email={$this->email}&pass={$this->pass}");
      curl_setopt($ch, CURLOPT_URL, "https://login.vk.com/?act=login&_origin=http://m.vk.com&ip_h={$ip_h}&role=pda&utf8=1");
      // curl_setopt($ch, CURLOPT_PORT , 443);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($ch, CURLOPT_REFERER, 'http://m.vk.com/login');
      
      $body = $this->curl_redirect_exec($ch);
      curl_close($ch);

      $ch2 = curl_init();
      curl_setopt($ch2, CURLOPT_HEADER, 1);
      curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch2, CURLOPT_NOBODY, 0);
//            curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt($ch2, CURLOPT_COOKIEFILE, $cookies);

      curl_setopt($ch2, CURLOPT_COOKIEJAR, $cookies);
      curl_setopt($ch2, CURLOPT_URL, 'http://oauth.vk.com/authorize?client_id='.$this->appId.'&scope=audio&response_type=token');
      $responce = $this->curl_redirect_exec($ch2);
      curl_close($ch2);


      if ( strpos($responce, 'action="https://login.vk.com') ) {
        // return null;
      }

      if ( preg_match_all("/access_token=(.*)&expires_in=86400/i", $responce, $res) ) {
        // everything is going fine
        $token = $res[1][0];
      } elseif ( preg_match_all('~"(https://login.vk.com/\?act=grant_access.+)"~', $responce, $res) ) {
        // accept application permissions
        $href = $res[1][0];

        $ch3 = curl_init();
        curl_setopt($ch3, CURLOPT_HEADER, 1);
        curl_setopt($ch3, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch3, CURLOPT_NOBODY, 0);
//                curl_setopt($ch3, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch3, CURLOPT_COOKIEFILE, $cookies);
        curl_setopt($ch3, CURLOPT_COOKIEJAR, $cookies);
        curl_setopt($ch3, CURLOPT_URL, $href);
        $responce = $this->curl_redirect_exec($ch3);

        curl_close($ch3);
        $token = $this->getToken();
      } else {
        $token = Cache::get('access_token');
        Cache::clear('access_token');
      }

      Cache::set($cacheKey, $token, 60*60);
    }

    if ( file_exists($cookies) ) {
      unlink($cookies);
    }
    return $token;
  }
    
  public function audioSearch( $q, $page = 0, $count = 10, $cacheTime = 86400 ) {
    if ( !$page || $page <= 0 ) $page = 0;
    else $page *= $count;

    $cachekey = "vkontakte_" . sha1($q.$page.$count).".xml";
    
    $audio = Cache::get( $cachekey );
  
	
    if ( !$audio ) {
      $ch = curl_init();
      // curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
      curl_setopt($ch, CURLOPT_TIMEOUT, 10);
      curl_setopt($ch, CURLOPT_URL, 'https://api.vk.com/method/audio.search.xml?access_token=' . $this->getToken() . '&count=' . $count . '&q=' . urlencode($q) . '&format=JSON&offset=' . $page)

      $audio = $this->curl_redirect_exec($ch);
      curl_close($ch);

      Cache::set($cachekey, $audio, $cacheTime);
    }
    
	
    libxml_use_internal_errors(true);
    $dom = new \DOMDocument("1.0", "UTF-8");
    $dom->strictErrorChecking = false;
    $dom->validateOnParse = false;
    $dom->recover = true;
    $dom->loadXML($audio);
    $data = simplexml_import_dom($dom);
    libxml_clear_errors();
    libxml_use_internal_errors(false);
    
    $result = array();
    if ( $count = (int)$data->count ) {
      foreach ($data->audio as $track) {
        if ( isset($track->aid) ) $result[] = (array)$track;
      }
    }
    return array(
      'count' => $count,
      'result' => $result
    );
  }
    
  public function audioGetById( $vkId ) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_URL, 'https://api.vk.com/method/audio.getById.xml?access_token=' . $this->getToken() . '&audios=' . $vkId);
    $all = curl_exec($ch);
    curl_close($ch);

    return new \SimpleXMLElement($all);
  }
  
  public function getTrack( $vkId ) {
    $song = $this->audioGetById($vkId);
    $url = $song->audio->url;

    return array(
      'url' => $url,
      'fname' => str_replace(" ", "_", "{$song->audio->artist} - {$song->audio->title}.mp3"),
      'size' => $this->remoteFilesize($url)
    );
  }

    //public function audioGet($a) {
    //  $ch = curl_init();
    //	curl_setopt($ch, CURLOPT_HEADER, 0);
    //	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    //	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    //	curl_setopt($ch, CURLOPT_URL, 'https://api.vk.com/method/audio.search.xml?access_token=' . $this->getToken() . '&count=1&q='.urlencode($a));
    //	$all = curl_exec($ch);
    //	curl_close($ch);
    //	return $all;
    //}
    
  public function audioGetLyrics($id) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_URL, 'https://api.vk.com/method/audio.getLyrics.xml?access_token=' . $this->getToken() . '&lyrics_id=' . $id);
    $lyrics = curl_exec($ch);
    curl_close($ch);
    
    $text = null;
    if ( $lyrics ) {
      $text = new \SimpleXMLElement($lyrics);
      $text = str_replace('&#39;', "'", htmlspecialchars_decode($text->lyrics->text));
    }
    
    return $text;
  }
    
  function remoteFilesize($url) {
    ob_start();
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $ok = curl_exec($ch);
    curl_close($ch);

    $head = ob_get_contents();
    ob_end_clean();
    
    $regex = '/Content-Length:\s([0-9].+?)\s/';
    preg_match($regex, $head, $matches);
    
    return isset($matches[1]) ? $matches[1] : "unknown";
  }
    
  public function curl_redirect_exec($ch, &$redirects = 0, $curloptHeader = false) {
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $data = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ( in_array($httpCode, array(301, 302)) ) {
      list($header) = explode("\r\n\r\n", $data, 2);
      $matches = array();

      //this part has been changes from the original
      preg_match("/(Location:|URI:)[^(\n)]*/", $header, $matches);
      $url = trim( str_replace($matches[1], "", $matches[0]) );

      if ( preg_match_all('/access_token=(.*)&expires_in=86400/i', $url, $matches) ) {
        Cache::set('access_token', $matches[1][0], 60*60);
      }
      //end changes

      $urlParsed = parse_url($url);
      if ( isset($urlParsed) ) {
        curl_setopt($ch, CURLOPT_URL, $url);
        $redirects++;
        return $this->curl_redirect_exec($ch, $redirects);
      }
    }

    if ( $curloptHeader ) {
      return $data;
    } else {
      $ttt = explode("\r\n\r\n", $data, 2);
      return isset($ttt[1]) ? $ttt[1] : null;
    }
  }
}