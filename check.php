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

if ( !$ok ) {
	foreach ($errors as $error) {
		echo "<div style='margin-bottom:5px;background-color:yellow;padding:5px;border:1px solid orange;'>{$error}</div>";
	}
} else {
	echo "Все проверки пройдены успешно! Файл check.php рекомендуется удалить.<br/><br/> Если плеер все равно не работает, ошибку можно увидеть в error-логах, которые лежат в папке логов на сервере (напр. для apache2 в ubuntu - /var/log/apache2) или в панели управления хостингом.";
}
