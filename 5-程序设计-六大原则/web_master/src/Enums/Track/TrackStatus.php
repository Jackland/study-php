<?php
/**
 * Created by IntelliJ IDEA.
 * User: or
 * Date: 2020/11/23
 * Time: 11:19
 */

namespace App\Enums\Track;


use Framework\Enum\BaseEnum;

class TrackStatus extends BaseEnum
{
    //1： Label Created 2：Completed Prep 3：出库 4：Picked Up 5：In Transit 6： Delivered  7： Exception
    const LABEL_CREATED = 1;
    const COMPLETED_PREP = 2;
    const OUT_STOCK = 3;
    const PICKED_UP = 4;
    const IN_TRANSIT = 5;
    const DELIVERED = 6;
    const EXCEPTION = 7;

    public static function getViewItems()
    {
        return [
            static::LABEL_CREATED => 'Label Created',
            static::COMPLETED_PREP => 'In Prep',
            static::OUT_STOCK => 'Shipped from warehouse',
            static::PICKED_UP => 'Picked Up',
            static::IN_TRANSIT => 'In Transit',
            static::DELIVERED => 'Delivered',
            static::EXCEPTION => 'Exception',
        ];
    }

    public static function getHomePickViewItems(): array
    {
        return [
            static::COMPLETED_PREP => 'In Prep',
            static::OUT_STOCK => 'Shipped from warehouse',
            static::IN_TRANSIT => 'In Transit',
            static::DELIVERED => 'Delivered',
            static::EXCEPTION => 'Exception',
        ];
    }


    //查询条件中，需要特殊化展示Completed Prep 为 In Prep，且去掉Picked Up
    public static function getSpecialViewItems()
    {
        $viewItems = self::getViewItems();
        unset($viewItems[static::PICKED_UP]);

        return $viewItems;
    }

    public static function getSpecialDescription($value, $unKnown = 'Unknown')
    {
        $array = static::getSpecialViewItems();
        return isset($array[$value]) ? $array[$value] : $unKnown;
    }

}
