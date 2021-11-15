<?php
// Site
$_['site_url'] = HTTP_SERVER;
$_['site_ssl'] = HTTPS_SERVER;

// Url
$_['url_autostart'] = false;

// Database
$_['db_autostart'] = true;
$_['db_engine'] = DB_DRIVER; // mpdo, mssql, mysql, mysqli or postgre
$_['db_hostname'] = DB_HOSTNAME;
$_['db_username'] = DB_USERNAME;
$_['db_password'] = DB_PASSWORD;
$_['db_database'] = DB_DATABASE;
$_['db_port'] = DB_PORT;

// Session
$_['session_autostart'] = true;
$_['session_engine'] = 'db';
$_['session_name'] = 'OCSESSID';

// Template
$_['template_engine'] = 'twig';
$_['template_directory'] = '';
$_['template_cache'] = true;

// Autoload Libraries
$_['library_autoload'] = array(
);

// Actions
$_['action_pre_action'] = array(
    'startup/session',
    'startup/customer',
    'startup/maintain',
    'startup/session_auth',
    'startup/safe_checker',
    'startup/lang',
    'startup/startup',
//	'startup/error',
    'startup/event',
    'startup/maintenance',
    'startup/seo_url'
);

// Action Events
$_['action_event'] = array(
    'controller/*/before' => array(
        'event/language/before',

        /** @see ControllerEventController::before() */
        'event/controller/before',
    ),
    'controller/*/after' => array(
        'event/language/after'
    ),
    'view/*/before' => array(
        998 => 'event/language',
        1100 => 'event/view/before',
    ),
    'model/account/customerpartner/addProduct/after' => [
        /** @see ControllerEventProduct::addAfter() */
        'event/product/addAfter'
    ],
    'model/account/customerpartner/addProduct/before' => [
        /** @see ControllerEventProduct::addBefore() */
        'event/product/addBefore'
    ],
    'model/account/customerpartner/editProduct/after' => [
        /** @see ControllerEventProduct::editAfter() */
        'event/product/editAfter'
    ],
    'model/account/customerpartner/editProduct/before' => [
        /** @see ControllerEventProduct::editBefore() */
        'event/product/editBefore'
    ],
    'model/account/product_quotes/margin_contract/updateMarginContractStatus/after' => [
        /** @see ControllerEventMargin::updateAfter() */
        'event/margin/updateAfter',
    ],
    'controller/account/customerpartner/rma_management/rmaInfo/before' => [
        /** @see ControllerEventRma::rma_before() */
        'event/rma/rma_before',
    ],
    'controller/account/customerpartner/rma_management/margin_rma_info/before' => [
        /** @see ControllerEventRma::margin_rma_before() */
        'event/rma/margin_rma_before',
    ],
    'controller/account/customerpartner/rma_management/futures_rma_info/before' => [
        /** @see ControllerEventRma::futures_rma_before() */
        'event/rma/futures_rma_before',
    ],
    'model/catalog/margin_product_lock/TailIn/after' => [
        /** @see ControllerEventProductStock::lockAfter() */
        'event/product_stock/lockAfter',
    ],
    'model/catalog/margin_product_lock/TailOut/after' => [
        /** @see ControllerEventProductStock::lockAfter() */
        'event/product_stock/lockAfter',
    ],
    'model/catalog/futures_product_lock/TailIn/after' => [
        /** @see ControllerEventProductStock::futuresLockAfter() */
        'event/product_stock/futuresLockAfter',
    ],
    'model/catalog/futures_product_lock/TailOut/after' => [
        /** @see ControllerEventProductStock::futuresLockAfter() */
        'event/product_stock/futuresLockAfter',
    ],

    //'view/*/before' => array(
    //	1000  => 'event/debug/before'
    //),
    //'controller/*/after'  => array(
    //	'event/debug/after'
    //)
);


// N-1062
$_['tip_ticket_proof_images'] = [
    'money' => '$30.00',
    'sku' => 'S001010006',
    'store' => 'US-SERVICE'
];
