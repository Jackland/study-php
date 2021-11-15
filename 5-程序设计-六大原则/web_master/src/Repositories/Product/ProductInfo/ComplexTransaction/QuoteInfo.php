<?php

namespace App\Repositories\Product\ProductInfo\ComplexTransaction;

use App\Models\Product\ProQuoteDetail;

/**
 * 阶梯价
 */
class QuoteInfo extends AbsComplexTransactionInfo implements Interfaces\TemplatePriceBuyerBasedInterface
{
    use Traits\Info\TemplatePriceBuyerBasedTrait;

    public function __construct($productId)
    {
        $this->id = $productId;
    }

    /**
     * 获取产品的所有阶梯价信息
     * @return array [{id, template_id, min_quantity, max_quantity, price}]
     */
    public function getAllList(): array
    {
        $list = ProQuoteDetail::query()
            ->where('product_id', $this->id)
            ->orderBy('sort_order')
            ->get(['id', 'template_id', 'min_quantity', 'max_quantity', 'home_pick_up_price']);
        $res = [];
        foreach ($list as $item) {
            $res[] = [
                'id' => $item->id,
                'template_id' => $item->template_id,
                'min_quantity' => $item->min_quantity,
                'max_quantity' => $item->max_quantity,
                'price' => $this->solveTemplatePriceByBuyer($item->home_pick_up_price),
            ];
        }
        return $res;
    }
}
