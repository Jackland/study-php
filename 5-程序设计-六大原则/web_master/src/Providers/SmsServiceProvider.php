<?php

namespace App\Providers;

use Framework\DI\ServiceProvider;
use Overtrue\EasySms\EasySms;

class SmsServiceProvider extends ServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->app->singleton('sms', function ($app) {
            $config = $app->config['sms'];
            $extends = $config['extend_gateways'] ?? [];
            unset($config['extend_gateways']);
            $easySms = new EasySms($config);
            foreach ($extends as $name => $gatewayClass) {
                $easySms->extend($name, function ($gatewayConfig) use ($gatewayClass) {
                    return new $gatewayClass($gatewayConfig);
                });
            }

            return $easySms;
        });
    }
}
