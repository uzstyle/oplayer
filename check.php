<?php
ini_set("display_errors", "on");
define("ROOT", __DIR__);
header ('Content-type: text/html; charset=utf-8');
?>

<h1>Проверка работоспособности</h1>

<?php
$ok = true;

if ( 5.3 > floatval(phpversion()) ) {
	$errors[] = "Версия PHP должна быть выше 5.3. Версия ниже 5.3 считается устаревшей и официально не поддерживается.";
	$ok = false;
}

if ( !function_exists('curl_init') ) {
	$errors[] = "Модуль php_curl не установлен. Он нужен для работы парсера вконтакте.";
	$ok = false;
}

if ( !file_exists( ROOT . '/conf/app.ini') ) {
	$errors[] = "Конфиг приложения (conf/app.ini) отсутствует.";
	$ok = false;	
}

require_once "autoload.php";
require_once "vendor/Art/Config.php";

file_get_contents(Art\Config::getInstance()->getOption('app', 'baseHref') . "cc");

$conf = Art\Config::getInstance()->getOptions('vk');

require_once ROOT . '/vendor/AR/ActiveRecord.php';
ActiveRecord\Config::initialize(function($cfg) {
    $cfg->set_model_directory( ROOT . '/model');
    $cfg->set_connections(array(
        'production' => Art\Config::getInstance()->getOption('db', 'dsn')
    ));

    $cfg->set_default_connection('production');
});

require_once "vendor/Art/OpenPlayer.php";
foreach ($conf['email'] as $key => $email) {
	$op = new OpenPlayer\Core(
		$conf['email'][$key], $conf['pass'][$key], $conf['appId'], $conf['uagent']
	);

	if ( !$op->getToken() ) {
		$errors[] = "Аккаунт {$conf['email'][$key]} не работает. Для восстановления работоспособности аккаунта, или его проверки - попробуйте войти под ним через браузер, желательно с айпи (прокси) хотя бы той же страны, в которой находится сервер OpenPlayer-а.";
		$ok = false;
	}
}

if ( !$ok ) {
	foreach ($errors as $error) {
		echo "<div style='margin-bottom:5px;background-color:yellow;padding:5px;border:1px solid orange;'>{$error}</div>";
	}
} else {
	echo "Все проверки пройдены успешно! Файл check.php рекомендуется удалить.<br/><br/> Если плеер все равно не работает, ошибку можно увидеть в error-логах, которые лежат в папке логов на сервере (напр. для apache2 в ubuntu - /var/log/apache2) или в панели управления хостингом.";
}
