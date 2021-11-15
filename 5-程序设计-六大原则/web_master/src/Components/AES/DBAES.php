<?php

namespace App\Components\AES;

use App\Components\AES;

class DBAES
{
    private $aes;

    public function __construct(AES $aes)
    {
        $this->aes = $aes;
    }

    public function encrypt(?string $str): ?string
    {
        if (!$str) {
            return $str;
        }
        $res = $this->aes->encrypt($str);
        if ($res === false) {
            return $str;
        }
        return $res;
    }

    private $decryptCachedData = [];

    public function decrypt(?string $str, $default = '___ORIGIN'): ?string
    {
        $res = false;
        if ($str !== null) {
            if (!isset($this->decryptCachedData[$str])) {
                $res = $this->aes->decrypt($str);
                $this->decryptCachedData[$str] = $res;
            } else {
                $res = $this->decryptCachedData[$str];
            }
        }
        if ($res === false) {
            $res = $default === '___ORIGIN' ? $str : $default;
        }

        return $res;
    }
}
