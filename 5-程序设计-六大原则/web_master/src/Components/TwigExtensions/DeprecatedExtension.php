<?php

namespace App\Components\TwigExtensions;

use Framework\View\Twig\DeprecatedTokenParser;

class DeprecatedExtension extends AbsTwigExtension
{
    /**
     * @inheritDoc
     */
    public function getTokenParsers()
    {
        return [
            // {% deprecated '废弃' %}
            new DeprecatedTokenParser(),
        ];
    }
}
