<?php

namespace App\Components;

use App\Components\CountDownResolver\IncreasingResolver;
use App\Components\CountDownResolver\SimpleResolver;

class CountDownResolver
{
    /**
     * 简单倒计时
     * @param string $key
     * @param int $countDown
     * @return SimpleResolver
     */
    public static function simple(string $key, int $countDown = 60): SimpleResolver
    {
        return new SimpleResolver($key, $countDown);
    }

    /**
     * 按照规则的倒计时
     * @param string $key
     * @param array $rules
     * @return IncreasingResolver
     */
    public static function increasing(string $key, array $rules = [
        5 => 60,
        10 => 300,
        9999 => 3600,
    ]): IncreasingResolver
    {
        return new IncreasingResolver($key, $rules);
    }
}
