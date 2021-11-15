<?php

namespace App\Enums\Search;

use Framework\Enum\BaseEnum;

class CreateDateRange extends BaseEnum
{
    const ANY_TIME = 0;
    const TODAY = 1;
    const PAST_WEEK = 2;
    const PAST_MONTH = 3;
    const PAST_YEAR = 4;
    const CUSTOM_RANGE = 5;

    public static function getViewItems()
    {
        return [
            static::ANY_TIME => 'Any Time',
            static::TODAY => 'Today',
            static::PAST_WEEK => 'Past Week',
            static::PAST_MONTH => 'Past Month',
            static::PAST_YEAR => 'Past Year',
            static::CUSTOM_RANGE => 'Custom Range',
        ];
    }

    /**
     * 前端控件使用
     *
     * @return array
     */
    public static function getWebSelect()
    {
        return [
            'anytime' => self::getDescription(static::ANY_TIME),
            'd' => self::getDescription(static::TODAY),
            'w' => self::getDescription(static::PAST_WEEK),
            'month' => self::getDescription(static::PAST_MONTH),
            'y' => self::getDescription(static::PAST_YEAR),
            'custom' => self::getDescription(static::CUSTOM_RANGE),
            //            static::CUSTOM_RANGE => 'Custom range',
        ];
    }
}
