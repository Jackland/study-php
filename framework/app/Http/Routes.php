<?php
/**
 * Created by routes.php.
 * User: fuyunnan
 * Date: 2021/10/11
 * Time: 15:38
 */

namespace App\Http;

$app['router']->get('/',function(){

    return 'hello self frame';
});

$app['router']->get('welcome','App\Http\Controllers\WelcomeController@index');