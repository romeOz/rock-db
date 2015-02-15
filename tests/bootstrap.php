<?php
use rock\base\Alias;

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    /** @var \Composer\Autoload\ClassLoader $loader */
    $loader = require($composerAutoload);
}

$loader->addPsr4('rockunit\\', __DIR__);

date_default_timezone_set('UTC');
require(dirname(__DIR__) . '/src/polyfills.php');

Alias::setAlias('rockunit', __DIR__);
defined('ROCKUNIT_RUNTIME') or define('ROCKUNIT_RUNTIME', __DIR__ . '/runtime');

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
