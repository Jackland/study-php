<?php
// WORKSPACE
define('DIR_WORKSPACE', __DIR__ . '/');

// Require the common config file.
require DIR_WORKSPACE . 'config/common.php';

// HTTP
define('HTTP_SERVER', 'http://' . HOST_NAME . '/');
// HTTPS
$http_ssl = defined('HTTPS_ENABLE') && HTTPS_ENABLE ? 'https' : 'http';
define('HTTPS_SERVER', $http_ssl . '://' . HOST_NAME . '/');

//APPLICATION
define('DIR_APPLICATION', DIR_WORKSPACE . 'catalog/');

// LANGUAGE & TEMPLATE
define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
define('DIR_TEMPLATE', DIR_APPLICATION . 'view/theme/');
