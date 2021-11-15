<?php

namespace App\Components\TwigExtensions;

class AesExtension extends AbsTwigExtension
{
    protected $filters = [
        'dbDecrypt',
    ];

    protected $functions = [
        'dbDecrypt',
    ];

    /**
     * 数据库字段解密
     * @param string $str
     * @return string
     */
    public function dbDecrypt($str)
    {
        return app('db-aes')->decrypt($str);
    }
}
