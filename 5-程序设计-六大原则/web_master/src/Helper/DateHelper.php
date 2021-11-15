<?php

namespace App\Helper;

class DateHelper
{
    /**
     * 日期是否正确
     * @param string $date 日期
     * @param array $formats 日期格式
     * @return bool
     */
    public static function isCorrectDateFormat(string $date, array $formats = ['m/d/Y']): bool
    {
        $unixTime = strtotime($date);
        if (!$unixTime) { //无法用strtotime转换，说明日期格式非法
            return false;
        }

        //校验日期合法性，只要满足其中一个格式就可以
        foreach ($formats as $format) {
            if (date($format, $unixTime) == $date) {
                return true;
            }
        }
        return false;
    }
}
