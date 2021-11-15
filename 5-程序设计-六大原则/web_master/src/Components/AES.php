<?php

namespace App\Components;

class AES
{
    private $key;
    private $iv;

    public function __construct($key, $iv)
    {
        // generated ：base64_encode(openssl_random_pseudo_bytes(32));
        $this->key = base64_decode(strtr($key, [' ' => '+']));
        // generated：base64_encode(openssl_random_pseudo_bytes(16));
        $this->iv = base64_decode($iv);
    }

    /**
     * @param string $str
     * @return string|false
     */
    public function encrypt(string $str)
    {
        $encrypted = openssl_encrypt($str, 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $this->iv);
        if ($encrypted === false) {
            return false;
        }
        return strtr(base64_encode($encrypted), '+/', '_-');
    }

    /**
     * @param string $str
     * @return string|false
     */
    public function decrypt(string $str)
    {
        $str = strtr($str, '_-', '+/');
        if ($str !== base64_encode(base64_decode($str))) {
            // 非 base64 的文本直接解密失败
            return false;
        }
        return openssl_decrypt(base64_decode($str), 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $this->iv);
    }
}
