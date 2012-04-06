<?php
$controllers = array(
    'index', 'user', 'search', 'meta'
);

foreach ($controllers as $controller) {
    require_once ROOT . "/controller/{$controller}.php";
}