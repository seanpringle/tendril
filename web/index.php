<?php

require_once '../lib/config.php';
require_once ROOT . 'lib/package.php';

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

require_once ROOT ."pkg/$package.php";

$pkg_class = 'Package_' . $package;
$pkg = new $pkg_class();

function pkg() { global $pkg; return $pkg; }

$pkg->process();
$pkg->display();
