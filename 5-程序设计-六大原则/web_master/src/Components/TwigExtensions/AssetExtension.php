<?php

namespace App\Components\TwigExtensions;

use Framework\View\Enums\ViewWebPosition;
use Framework\View\Twig\ApplyTokenParser;
use Framework\View\Util;

/**
 * 前端资源相关
 */
class AssetExtension extends AbsTwigExtension
{
    protected $functions = [
        'asset',
        'css',
        'js',
        // 以下两个为相对 root 根目录，不建议使用，仅在兼容旧代码时使用
        'cssOrigin',
        'jsOrigin',
    ];

    protected $filters = [
        'script',
        'style',
    ];

    public function getTokenParsers()
    {
        return [
            new ApplyTokenParser(),
        ];
    }

    public function asset($path)
    {
        return $this->getAssetUrl($path);
    }

    public function css($cssPath)
    {
        foreach ((array)$cssPath as $path) {
            view()->css($this->getAssetUrl($path));
        }
    }

    public function js($jsPath, $position = ViewWebPosition::BODY_END)
    {
        foreach ((array)$jsPath as $path) {
            view()->js($this->getAssetUrl($path), [], $position);
        }
    }

    public function script($script, $position = ViewWebPosition::BODY_END)
    {
        preg_match_all('/<script.*?>(.*?)<\/script>/is', $script, $matches);
        view()->script($matches[1] ?? [], $position);
    }

    public function style($style)
    {
        preg_match_all('/<style.*?>(.*?)<\/style>/is', $style, $matches);
        view()->style($matches[1] ?? []);
    }

    /**
     * 获取资源路径
     * @param string $path 相对 /public 目录
     * @return string
     */
    private function getAssetUrl($path)
    {
        if (Util::isHttpUrl($path)) {
            return $path;
        }
        $path = ltrim($path, '/');
        if (strpos($path, 'public/') === 0) {
            $path = substr($path, strlen('public/'));
        }
        $timestamp = @filemtime(aliases('@public/' . $path));
        if ($timestamp > 0) {
            $pos = strpos($path, '?') === false ? '?' : '&';
            $path = "{$path}{$pos}v={$timestamp}";
        }
        return aliases('@publicUrl/' . $path);
    }

    /**
     * 给 path 资源追加 v=timestamp
     * @param $path
     * @return string
     */
    private function appendTimestamp($path)
    {
        if (Util::isHttpUrl($path)) {
            return $path;
        }
        $path = ltrim($path, '/');
        $timestamp = @filemtime(aliases('@root/' . $path));
        if ($timestamp > 0) {
            $pos = strpos($path, '?') === false ? '?' : '&';
            return "/{$path}{$pos}v={$timestamp}";
        }
        return '/' . $path;
    }

    public function cssOrigin($cssPath)
    {
        foreach ((array)$cssPath as $path) {
            view()->css($this->getOriginAssetUrl($path));
        }
    }

    public function jsOrigin($jsPath, $position = ViewWebPosition::BODY_END)
    {
        foreach ((array)$jsPath as $path) {
            view()->js($this->getOriginAssetUrl($path), [], $position);
        }
    }

    private function getOriginAssetUrl($path)
    {
        return $this->appendTimestamp($path);
    }
}
