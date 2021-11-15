<?php

namespace App\Repositories\Pay;

use App\Models\Setting\Parameter;
use Illuminate\Support\Collection;
use Psr\SimpleCache\CacheInterface;

class AirwallexRepository
{
    private const ACCOUNT_BIND_PARAM_KEY = 'AIRWALLEX_WEBHOOK_RECEIVE_SECRET_KEY_ACCOUNT_BIND';

    /**
     * 获取空中云汇回调加密盐值
     * @return Collection
     */
    public function getCallbackSecret()
    {
        $key = self::ACCOUNT_BIND_PARAM_KEY;
        $cache = app()->get(CacheInterface::class);
        $paramValue = $cache->get($key);
        if (! $paramValue) {
            $paramValue = Parameter::where('ParamKey', self::ACCOUNT_BIND_PARAM_KEY)->value('ParamValue');
            if ($paramValue) {
                $cache->set($key, $paramValue, 86400);
            }
        }

        return $paramValue;
    }
}