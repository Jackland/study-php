<?php

namespace App\Components\TwigExtensions;

use Framework\Translation\Twig\TransDefaultCategoryTokenParser;
use Framework\Translation\Twig\TranslationDefaultCategoryNodeVisitor;
use Framework\Translation\Twig\TranslationNodeVisitor;

/**
 * 翻译相关
 */
class TranslationExtension extends AbsTwigExtension
{
    protected $functions = [
        '__',
        '__choice',
    ];

    /**
     * @inheritDoc
     */
    public function getTokenParsers()
    {
        return [
            // {% trans_default_category 'catalog/view/common/footer' %}
            new TransDefaultCategoryTokenParser(),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getNodeVisitors()
    {
        return [
            new TranslationDefaultCategoryNodeVisitor(),
            $this->getTranslationNodeVisitor(),
        ];
    }

    private $translationNodeVisitor;

    public function getTranslationNodeVisitor()
    {
        return $this->translationNodeVisitor ?: $this->translationNodeVisitor = new TranslationNodeVisitor();
    }

    public function __($key, array $replace = [], $category = null, $locale = null)
    {
        return __($key, $replace, $category, $locale);
    }

    public function __choice($key, $number, array $replace = [], $category = null, $locale = null)
    {
        return __choice($key, $number, $replace, $category, $locale);
    }
}
