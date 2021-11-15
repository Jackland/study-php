<?php

namespace App\Repositories\Product\ProductInfo;

use Illuminate\Support\Collection;

class ProductPriceRangeFactory
{
    private $data;

    public function __construct()
    {
        $this->data = collect();
    }

    /**
     * @param int $id 产品ID
     * @param float|array $prices
     */
    public function addPrice($id, $prices)
    {
        if (!$prices) {
            return;
        }

        /** @var Collection $productPrices */
        $productPrices = $this->data->get($id, collect());
        if (!is_array($prices)) {
            $prices = [$prices];
        }
        foreach ($prices as $price) {
            if ($price <= 0) {
                continue;
            }
            $productPrices->add($price);
        }
        $this->data[$id] = $productPrices;
    }

    /**
     * 获取所有的区间
     * @return array
     */
    public function getRanges(): array
    {
        return $this->data->map(function (Collection $collection) {
            return [$collection->min(), $collection->max()];
        })->toArray();
    }

    /**
     * 根据 id 获取区间
     * @param int $id 产品ID
     * @param null $default
     * @return array|mixed [$min, $max]
     */
    public function getRangeById($id, $default = null)
    {
        $ranges = $this->getRanges();
        return $ranges[$id] ?? $default;
    }
}
