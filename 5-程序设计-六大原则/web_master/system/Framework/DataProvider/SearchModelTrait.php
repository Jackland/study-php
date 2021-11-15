<?php

namespace Framework\DataProvider;

/**
 * @property array $searchAttributes
 */
trait SearchModelTrait
{
    public function getSearchData()
    {
        return $this->searchAttributes;
    }

    protected function loadAttributes($data)
    {
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $this->searchAttributes)) {
                $this->searchAttributes[$key] = $value;
            }
        }
    }
}
