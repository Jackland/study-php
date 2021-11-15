<?php

namespace App\Components\TwigExtensions;

/**
 * 字符串相关
 */
class StringExtension extends AbsTwigExtension
{
    protected $filters = [
        'dprintf',
        'c_escape' => 'escape',
        'truncate',
    ];

    protected $functions = [
        'truncate'
    ];

    public function dprintf($format, ...$args)
    {
        if (empty($args) || !$format) {
            return empty($format) ? '' : $format;
        }
        if (!is_array($args[0])) {
            return $this->dprintf($format, $args);
        }
        $args = $args[0];
        foreach ($args as $k => $v) {
            $format = strtr($format, ['{' . $k . '}' => ($v ?? '')]);
        }
        $format = preg_replace_callback('/({[^{]+?})/', function () {
            return '';
        }, $format);

        return $format;
    }

    public function escape($string)
    {
        return trim(str_replace(["\r\n", "\n", "\r"], ' ', $string ?? ''));
    }

    public function truncate($string, int $length, $pads = '...', int $minLength = 0)
    {
        return truncate(...func_get_args());
    }
}
