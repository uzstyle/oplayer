<?php
namespace Art;

class User {
    private static $storage = null;

    public static function get( $field = null ) {
        $user = self::$storage;

        if ( $user ) {
            if ( $field ) {
                return $user->{$field};
            }

            return $user;
        }

        return null;
    }

    public static function genKey() {
        return sha1( self::get('id') . time() . uniqid() );
    }

    public static function set( $user ) {
        if ( is_string($user) /* is json? */ ) {
            self::$storage = json_decode($user);
        } else {
            self::$storage = $user;
        }

        $_SESSION['user'] = json_encode(self::$storage);

        // session
        $sess = \Model\UserSession::find_by_userid(self::get('id'));

        if ( !$sess ) {
            $sess = new \Model\UserSession;
            $sess->userid = self::get('id');
            $sess->sesskey = self::genKey();
        }

        $expire = time() + 60*60*24*30;
        $sess->expiredat = date("Y-m-d H:i:s", $expire);//month
        $sess->save();

        setcookie("sesskey", $sess->sesskey, $expire, '/');
    }

    public function __construct() {
        if ( isset($_SESSION['user']) ) {
            self::$storage = json_decode($_SESSION['user']);
        } else {
            if ( isset( $_COOKIE['sesskey'] ) ) {
                $sess = \Model\UserSession::find_by_sesskey($_COOKIE['sesskey']);
                if ( $sess ) {
                    $user = \Model\User::find_by_id($sess->userid);
                    self::set($user->to_json());
                }
            }
        }
    }

    public static function logout() {
        self::$storage = null;
        unset($_SESSION['user']);

        setcookie("sesskey", null, 0, '/');
    }
    
}