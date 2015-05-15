<?php

// router for php -S
if (preg_match('/\.(?:png|jpg|jpeg|gif|svg)$/', $_SERVER['REQUEST_URI']))
    return false;

ob_start();

define('ROOT', dirname(dirname(__FILE__)).'/');

require_once ROOT .'lib/utility.php';
require_once ROOT .'lib/config.php';

require_once ROOT .'lib/Dict.php';
require_once ROOT .'lib/Text.php';
require_once ROOT .'lib/Cache.php';
require_once ROOT .'lib/SQL.php';

require_once ROOT . 'lib/Server.php';

// find out where we are
$path    = @trim(array_shift(explode('?', $_SERVER['REQUEST_URI'])), '/');
$request = strlen($path) ? explode('/', $path): array();

// simple controller
$package = count($request)
    ? (preg_match('/^(21)?[a-z]+[a-z0-9_\-]+$/i', $request[0])
        ? array_shift($request) : '404')
    : $_ENV['pkg_default'];

if (!file_exists(ROOT ."pkg/$package.php"))
    $package = '404';

require_once ROOT .'lib/package.php';
require_once ROOT ."pkg/$package.php";

if (!class_exists('Package_'.$package))
	$package = '404';

$class = 'Package_' . $package;
$_pkg = new $class();

function pkg() { global $_pkg; return $_pkg; }

$_pkg->process();

ob_get_clean();
ob_start('ob_gzhandler');

$_pkg->display();
