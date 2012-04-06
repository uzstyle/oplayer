<?php
// РЕКЛАМА!!!!!!!!!!!!!! + ДОНЕЙТ!!!!!!!!!!!!!!!1

// Powered by:
//   - Silex (http://silex.sensiolabs.org), 
//   - PHPActiveRecord (http://phpactiverecord.org), 
//   - OpenPlayer (https://github.com/uavn/openplayer), 
//   - Pagerfanta (https://github.com/whiteoctober/Pagerfanta)

session_start();
define('ROOT', __DIR__);
require_once ROOT . '/silex.phar';
require_once ROOT . '/autoload.php';
require_once ROOT . '/helpers.php';

$app = new Silex\Application();

$app['debug'] = 'localhost' == $_SERVER['HTTP_HOST'];
ini_set("display_errors", $app['debug'] ? "on" : "off");

require_once ROOT . '/services.php';
require_once ROOT . '/controllers.php';

$app->run();


// clearing old cache
$cache = Model\Cache::find('all', array('conditions' => array('expiredAt < ?', date("Y-m-d H:i:s"))));
foreach ( $cache as $c ) $c->delete();