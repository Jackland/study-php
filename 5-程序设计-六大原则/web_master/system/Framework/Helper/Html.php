<?php

namespace Framework\Helper;

class Html extends BaseHtml
{
    public static function a($text, $url = null, $options = [])
    {
        return parent::a($text, url($url === null ? '' : $url), $options);
    }
}
