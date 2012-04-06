<?php
set_time_limit(0);
session_write_close();
header ('Content-type: text/html; charset=utf-8');

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// session_start();
define('ROOT', __DIR__);
require_once ROOT . '/silex.phar';
require_once ROOT . '/autoload.php';
require_once ROOT . '/helpers.php';

$app = new Silex\Application();

$app['debug'] = 'localhost' == $_SERVER['HTTP_HOST'];
// ini_set("display_errors", $app['debug'] ? "on" : "off");

require_once ROOT . '/services.php';

$app['pdo'] = function() use ($app) {
	$pdo = Model\User::connection();
	return $pdo;
};

$app->post('/', function(Request $request) use($app) {
	$ids = $request->get('id');

	$sql = 'SELECT * FROM pl_song WHERE id IN ('.join(',', $ids).')';
	$res = $app['pdo']->query( $sql );
	$all = $res->fetchAll( PDO::FETCH_OBJ );

	if ( count($all) ) {
		foreach ( $all as $one ) {
			if ( !$one->plid ) continue;
			$info = unserialize($one->songinfo);
			
			$q = $info['name'] . ' - ' . $info['artist'];

			$res = $app['openplayer']->audioSearch($q, 0, 100);
			$res = $res['result'];

			$f = null;
			$found = false;
			foreach ( $res as $r ) {
				if ( !$found && $info['name'] == $r['title'] && $info['artist'] == $r['artist'] ) {
					$found = true;
					$f = $r;
				}
			}

			if ( !$found ) {
				$f = $res[0];
			}

			if ( $f ) {
				$vkid = $f['owner_id'].'_'.$f['aid'];

				$pltrack = new Model\PlTrack;
				$pltrack->plid = $one->plid;
				$pltrack->artist = $f['artist'];
				$pltrack->name = $f['title'];
				$pltrack->vkid = $vkid;
				$pltrack->lyricsid = $f['lyrics_id'];
				$pltrack->duration = $f['duration'];
				$pltrack->pos = 666;

				$pltrack->save();

				$sql = "DELETE FROM `pl_song` WHERE id = {$one->id}";
				$res = $app['pdo']->query( $sql );
				$res->execute();
			} else {
				echo "<br/><br/>Track {$q} not found<br/><br/>";
			}
		}
	} else {
		// $sql = 'DROP TABLE `pl_song`';
		// $res = $app['pdo']->query( $sql );
		// $res->execute();

		// $sql = 'TRUNCATE TABLE `cache`';
		// $res = $app['pdo']->query( $sql );
		// $res->execute();

		// @todo move from here

	}

	
	return new \Symfony\Component\HttpFoundation\RedirectResponse('./update.php');
});

$app->get('/', function(Request $request) use($app) {
	if ( $id = $request->get('del') ) {
		$sql = "DELETE FROM pl_song WHERE id = {$id}";
		$res = $app['pdo']->query( $sql );
		$all = $res->execute();
	}

	$sql = 'SHOW TABLES';
	$res = $app['pdo']->query( $sql );
	$all = $res->fetchAll( PDO::FETCH_OBJ );

	$tocreate = true;
	$toconvert = false;
	foreach ( $all as $one ) {
		if ( 'cache' == reset($one) ) {
			$tocreate = false;
		}

		if ( 'pl_song' == reset($one) ) {
			$toconvert = true;
		}
	}

	if ( $tocreate ) {
		$sqls = array(
			"CREATE TABLE `cache` (`id` int(11) NOT NULL AUTO_INCREMENT,`key` varchar(128) NOT NULL,`data` mediumtext,`expiredAt` datetime DEFAULT NULL,PRIMARY KEY (`id`),KEY `key` (`key`,`expiredAt`)) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8",
			"DROP TABLE `stat`",
			"CREATE TABLE `user_session` (`id` int(11) NOT NULL AUTO_INCREMENT,`userId` int(11) DEFAULT NULL,`sessKey` varchar(32) DEFAULT NULL,`expiredAt` datetime DEFAULT NULL,PRIMARY KEY (`id`),KEY `user` (`userId`),KEY `sessKey` (`sessKey`,`expiredAt`)) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8",
			"CREATE TABLE `pl_track` (`id` int(11) NOT NULL AUTO_INCREMENT,`plId` int(11) NOT NULL,`artist` varchar(32) DEFAULT NULL,`name` varchar(32) DEFAULT NULL,`vkId` varchar(32) DEFAULT NULL,`lyricsid` bigint(20) DEFAULT NULL,`duration` varchar(32) DEFAULT NULL,`pos` int(11) DEFAULT '666',PRIMARY KEY (`id`),KEY `pl` (`plId`)) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8",
			"ALTER TABLE `user` DROP COLUMN `settings` , DROP COLUMN `sessionKey`, ADD INDEX `login` (`login` ASC), ADD INDEX `login_pass` (`login` ASC, `password` ASC)",
			"ALTER TABLE `pl` ADD COLUMN `status` TINYINT(1) NULL DEFAULT 0  AFTER `name`;",
			"ALTER TABLE `pl` ADD COLUMN `pos` INT NULL DEFAULT 666 AFTER `name`, ADD INDEX `pos` (`pos` ASC)",
			"ALTER TABLE `user_session` ADD CONSTRAINT `userId` FOREIGN KEY (`userId` ) REFERENCES `user` (`id` ) ON DELETE CASCADE ON UPDATE CASCADE, ADD INDEX `userId` (`userId` ASC), DROP INDEX `user`",
			"ALTER TABLE `pl_track` ADD CONSTRAINT `pl` FOREIGN KEY (`plId` ) REFERENCES `pl` (`id` ) ON DELETE CASCADE ON UPDATE CASCADE"
		);
	
		foreach ( $sqls as $sql ) {
			$res = $app['pdo']->query( $sql );
			try {
				$res->execute();
			} catch ( Exception $e ) {
				echo $e->getMessage(), "<br/>";
			}
		}
	}

	if ( $toconvert ) {
		$sql = 'SELECT * FROM pl_song';
		$res = $app['pdo']->query( $sql );
		$all = $res->fetchAll( PDO::FETCH_OBJ );

?>
<h1>Скрипт конвертирования данных (плейлисты пользователей) из таблицы старого движка pl_song в таблицу нового pl_track</h1>
После окончания скрипт лучше удалить.<br/></br/>
Данные могут не конвертироваться по двум причинам:<br/>
- Трек с таким именем удален или переименован в базе вконтакте (в таком случае восстановить его невозможно, остается только удалить)
<br/>
- Аккаунт вконтакте не работает, или исчерпал лимит (может быть изза массового восстановления через данный скрипт, в таком случае нужно либо подождать, либо сменить аккаунт). Проверить работоспособность аккаунтов можно в скрипте check.php.
<form action="" method="post">
<input type="submit" value="Конвертировать в новую базу"/>
<table border=1><?
foreach ( $all as $index => $one ) {
	$songinfo = unserialize($one->songinfo);
	?>
	<tr>
		<td>
			<input type="checkbox" name="id[]" value="<?php echo $one->id ?>" <?php if ($index < 100): ?>checked="checked"<?php endif ?>/>
		</td>

		<td>
			<?php echo $one->id ?>
		</td>

		<td>
			<?php echo $songinfo['name'] ?> - <?php echo $songinfo['artist'] ?>
		</td>

		<td>
			<a href="?del=<?php echo $one->id ?>" onclick="return confirm('Точно?')">Удалить</a>
		</td>
	</tr>
	<?}?></table></form><?
	}

    return "";
});

$app->run();