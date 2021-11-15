<?php

namespace App\Components\TwigExtensions;

class WebpackEncoreEntryExtension extends AbsTwigExtension
{
    protected $functions = [
        'webpack_entry_css',
        'webpack_entry_js',
        'webpack_entry_asset',
    ];

    public function webpack_entry_asset(string $name)
    {
        $this->webpack_entry_css($name);
        $this->webpack_entry_js($name);
    }

    public function webpack_entry_css(string $name)
    {
        $files = app('webpack-encore.finder')->getCssFiles($name);
        if ($files) {
            view()->css($files);
        }
    }

    public function webpack_entry_js(string $name)
    {
        $files = app('webpack-encore.finder')->getJsFiles($name);
        if ($files) {
            view()->js($files);
        }
    }
}
