<?php
// Version
define('VERSION', '3.0.2.0');

// Configuration
require __DIR__ . '/config.php';

defined('OC_DEBUG') or define('OC_DEBUG', false);
defined('OC_ENV') or define('OC_ENV', 'prod');

// Install
if (!defined('DIR_APPLICATION')) {
    header('Location: install/index.php');
    exit;
}

// Startup
require_once(DIR_SYSTEM . 'startup.php');

start('catalog');
