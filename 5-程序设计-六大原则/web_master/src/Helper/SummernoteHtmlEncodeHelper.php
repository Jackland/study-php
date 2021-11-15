<?php

namespace App\Helper;


class SummernoteHtmlEncodeHelper
{
    /**
     * 用于Summernote富文本，HTML转义
     * @param string $str
     * @param bool $isAll true 直接处理
     * @return string
     */
    public static function encode($str = '', $isAll = false): string
    {
        if (!is_string($str)) {
            $str = strval($str);
        }
        if ($isAll || strpos($str, '&lt;') !== 0) {
            $str = htmlentities($str);
        }
        return $str;
    }

    /**
     * 用于Summernote富文本，HTML反转义
     * @param string $str
     * @param bool $isAll true 直接处理
     * @return string
     */
    public static function decode($str = '', $isAll = false): string
    {
        if (!is_string($str)) {
            $str = strval($str);
        }
        if ($isAll || strpos($str, '&lt;') === 0) {
            $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
        }
        // 将 nbsp 替换为空格是因为如果是 nbsp 的话前端展示时不会换行，#39006
        $str = str_replace('&nbsp;', ' ', $str);

        return $str;
    }
}
