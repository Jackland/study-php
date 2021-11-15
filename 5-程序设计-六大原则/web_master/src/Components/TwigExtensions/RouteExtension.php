<?php

namespace App\Components\TwigExtensions;

/**
 * 路由相关
 */
class RouteExtension extends AbsTwigExtension
{
    protected $functions = [
        'url',
    ];

    public function url($url, $args = [])
    {
        if (is_string($url)) {
            $url = [$url];
        }
        if ($args) {
            $url = array_merge($url, $args);
        }
        return url()->to($url);
    }
}
