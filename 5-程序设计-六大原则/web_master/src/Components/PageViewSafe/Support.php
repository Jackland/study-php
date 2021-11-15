<?php

namespace App\Components\PageViewSafe;

use App\Components\PageViewSafe\CaptchaTransfer\TransferEncryption;

class Support
{
    /**
     * @param string $url
     * @param array $params
     * @return string
     */
    public static function buildUrl(string $url, $params = []): string
    {
        return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
    }

    /**
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public static function getConfig(?string $key, $default)
    {
        $arr = get_env('PAGE_VIEW_SAFE_CONFIG', []);

        if (!$key) {
            return $arr ?: $default;
        }

        return $arr[$key] ?? $default;
    }

    protected static $_encryption;

    /**
     * @return TransferEncryption
     */
    public static function getEncryption(): TransferEncryption
    {
        if (static::$_encryption === null) {
            static::$_encryption = new TransferEncryption(Support::getConfig('AES_KEY', ''), Support::getConfig('AES_IV', ''));
        }
        return static::$_encryption;
    }
}
