<?php

namespace App\Components;

use App\Components\RemoteApi\B2BManager\FileApi;
use App\Components\RemoteApi\Yzcm\SalesOrderApi;
use App\Components\RemoteApi\YzcTaskWork\EmailApi;

/**
 * 远程接口调用，融合 yzcm/yzcTaskWork/WOS 等所有远程的访问
 *
 * @method static FileApi file()
 * @method static SalesOrderApi salesOrder()
 * @method static EmailApi email()
 * @method static RemoteApi\Yzcm\AirwallexApi airwallex()
 * @method static RemoteApi\B2BManager\FreightApi freight()
 */
class RemoteApi
{
    protected const SERVICE_MAP = [
        'file' => FileApi::class,
        'salesOrder' => SalesOrderApi::class,
        'email' => EmailApi::class,
        'airwallex' => RemoteApi\Yzcm\AirwallexApi::class,
        'freight' => RemoteApi\B2BManager\FreightApi::class,
    ];

    public static function __callStatic($name, $arguments)
    {
        $api = self::SERVICE_MAP[$name];
        return app()->make($api);
    }
}
