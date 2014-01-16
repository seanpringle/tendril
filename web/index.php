<?php

ob_start();

define('ROOT', dirname(dirname(__FILE__)).'/');

require_once ROOT .'lib/utility.php';
require_once ROOT .'lib/cache.php';
require_once ROOT .'lib/sql.php';
require_once ROOT .'lib/config.php';

// find out where we are
$path    = @trim(array_shift(explode('?', $_SERVER['REQUEST_URI'])), '/');
$request = strlen($path) ? explode('/', $path): array();

// simple controller
$package = count($request)
    ? (preg_match('/^(21)?[a-z]+[a-z0-9_\-]+$/i', $request[0])
        ? array_shift($request) : '404')
    : 'default';

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

$_pkg->display();
