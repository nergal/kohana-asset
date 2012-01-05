<?php

if ( ! defined('SYSPATH')) {
    error_reporting(E_ALL | E_STRICT);

    define('SYSPATH', dirname(__FILE__));
    define('DOCROOT', dirname('/tmp/'));

    require_once 'PHPUnit/Framework/TestCase.php';
    require_once __DIR__ . '/../classes/kohana/asset.php';

    // Mock objects

    class Kohana_Exception extends Exception { }
}