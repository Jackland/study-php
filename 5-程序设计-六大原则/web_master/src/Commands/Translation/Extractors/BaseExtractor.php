<?php

namespace App\Commands\Translation\Extractors;

abstract class BaseExtractor implements ExtractorInterface
{
    private $defaultCategory = 'app';

    public function setDefaultCategory($category)
    {
        $this->defaultCategory = $category;

        return $this;
    }

    public function getDefaultCategory()
    {
        return $this->defaultCategory;
    }

    public function checkContentExistTransFn($content, $fnNames)
    {
        foreach ((array)$fnNames as $fnName) {
            if (strpos($content, $fnName) !== false) {
                return true;
            }
        }

        return false;
    }
}
