<?php

namespace App\Helper;

class StringHelper
{
    /**
     * 字符串的字符长度(日文和汉字算2个字符)
     * @param string $str
     * @return mixed
     */
    public static function stringCharactersLen(string $str)
    {
        $length = 0;
        for ($i = 0, $l = mb_strlen($str, 'utf-8'); $i < $l; ++$i) {
            $charCode = self::characterUnicode(mb_substr($str, $i, 1, 'utf-8'));
            //中，日，韩的unicode范围是：4E00~9FA5 (19968, 40869);
            if ($charCode >= 2048 && $charCode <= 40869) {
                $length += 2;
            } else {
                $length++;
            }
        }

        return $length;
    }

    /**
     * 字符的Unicode
     * @param string $char
     * @param string $fromEncoding
     * @return int|mixed
     */
    public static function characterUnicode(string $char, string $fromEncoding = 'UTF-8')
    {
        if (strlen($char) == 1) {
            return ord($char);
        }

        $result = unpack('N', mb_convert_encoding($char, 'UCS-4BE', $fromEncoding));

        return $result[1];
    }

    /**
     * 手机号打星
     * @param string $cellphone
     * @return string
     */
    public static function maskCellphone(string $cellphone): string
    {
        return '******' . substr($cellphone, -4);
    }

    /**
     * 邮箱打星
     * @param string $email
     * @return string
     */
    public static function maskEmail(string $email): string
    {
        $atPos = strpos($email, '@');
        if ($atPos === false) {
            return substr($email, 0, 3) . '***';
        }
        return substr($email, 0, $atPos <= 2 ? $atPos : 3) . '***' . substr($email, $atPos);
    }

    /**
     * 是否包含汉字
     * @param string $str
     * @return bool
     */
    public static function stringIncludeChinese(string $str): bool
    {
        if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $str) > 0) {
            return true;
        }

        return false;
    }
}
