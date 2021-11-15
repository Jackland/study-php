<?php

namespace Framework\Translation;

use Illuminate\Contracts\Translation\Loader;

class Translator extends \Illuminate\Translation\Translator
{
    private $defaultCategory;
    private $enable;
    private $defaultLocale;

    public function __construct(Loader $loader, $locale, $defaultCategory)
    {
        parent::__construct($loader, $locale);
        $this->defaultCategory = $defaultCategory;

        $this->enable = true;
        $this->defaultLocale = $this->getLocale();
    }

    public function enable()
    {
        $this->enable = true;
    }

    public function disable()
    {
        $this->enable = false;
    }

    public function isEnable(): bool
    {
        return $this->enable;
    }

    public function getDefaultCategory()
    {
        return $this->defaultCategory;
    }

    /**
     * 翻译，按照 category 传参形式
     * @param $key
     * @param array $replace
     * @param string|null $category
     * @param null $locale
     * @return array|string
     */
    public function t($key, array $replace = [], $category = null, $locale = null)
    {
        $category = $category ?: $this->defaultCategory;

        return $this->trans($category . '.' . $key, $replace, $locale);
    }

    /**
     * 选择翻译，按照 category 传参形式
     * @param $key
     * @param $number
     * @param array $replace
     * @param string|null $category
     * @param null $locale
     * @return string
     */
    public function tc($key, $number, array $replace = [], $category = null, $locale = null)
    {
        $category = $category ?: $this->defaultCategory;

        return $this->choice($category . '.' . $key, $number, $replace, $locale);
    }

    /**
     * @inheritDoc
     */
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        $result = parent::get($key, $replace, $locale, $fallback);
        if ($result === '@origin') {
            $result = $key;
        }
        if ($result === $key) {
            $category = '';
            $oldKey = $key;
            if (($pos = mb_strpos($key, '.')) !== false) {
                $category = mb_substr($key, 0, $pos);
                // 移除 category 后的 key
                $key = mb_substr($key, $pos + 1);
            }
            // 特殊 category 不需要移除的
            $larvalLangCategories = [
                'validation',
            ];
            if ($category && in_array($category, $larvalLangCategories)) {
                $key = $oldKey;
            }

            return $this->makeReplacements($key, $replace);
        }
        return $result;
    }
}
