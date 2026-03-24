<?php
/**
 * PHPUnit bootstrap file.
 *
 * Loads Composer autoloader and sets up Brain Monkey.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Define WordPress constants that plugin code expects.
defined('ABSPATH')          || define('ABSPATH', '/tmp/fake-wp/');
defined('HOUR_IN_SECONDS')  || define('HOUR_IN_SECONDS', 3600);
