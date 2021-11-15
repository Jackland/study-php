<?php
// WORKSPACE
define('DIR_WORKSPACE',dirname( __DIR__ ).'/');

// Require the common config file.
require DIR_WORKSPACE . 'config/common.php';

// HTTP
define('HTTP_SERVER', 'http://' . HOST_NAME . '/admin/');
define('HTTP_CATALOG', 'http://' . HOST_NAME . '/');

// HTTPS
$http_ssl = defined('HTTPS_ENABLE') && HTTPS_ENABLE ? 'https' : 'http';
define('HTTPS_SERVER', $http_ssl . '://' . HOST_NAME . '/admin/');
define('HTTPS_CATALOG', $http_ssl . '://' . HOST_NAME . '/');

//APPLICATION
define('DIR_APPLICATION', DIR_WORKSPACE . 'admin/');

// LANGUAGE & TEMPLATE
define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
define('DIR_TEMPLATE', DIR_APPLICATION . 'view/template/');

// Catalog
define('DIR_CATALOG', DIR_WORKSPACE . 'catalog/');
