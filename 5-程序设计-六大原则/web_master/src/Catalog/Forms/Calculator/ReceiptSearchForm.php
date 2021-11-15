<?php

namespace App\Catalog\Forms\Calculator;

use App\Models\Product\Product;
use App\Models\Product\ProductSetInfo;
use App\Models\Stock\ReceiptsOrderDetail;
use Framework\Model\RequestForm\RequestForm;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;

class ReceiptSearchForm extends RequestForm
{
    /**
     * 产品id
     * @var $productId
     */
    public $productId;
    /**
     * @var int $customerId
     */
    public $customerId;
    /**
     * @var string $keyword
     */
    public $keyword;

    protected function getRules(): array
    {
        return [
            'productId' => ['required', 'integer', 'min:1'],
            'customerId' => ['required', 'integer', 'min:1'],
            'keyword' => ['required', 'string', 'min:1']
        ];
    }

    public function getData(): array
    {
        // 获取product 信息
        $product = Product::with(['combos'])->find($this->productId);
        $productIds = [(int)$product->product_id];
        if ($product->combo_flag && $product->combos->isNotEmpty()) {
            $productIds = $product->combos->map(function (ProductSetInfo $product) {
                return $product->set_product_id;
            })->toArray();
        }

        $query = db('tb_sys_receipts_order as ro')
            ->select(['ro.receive_order_id', 'ro.receive_number'])
            ->selectRaw('ifnull(acf.fee,0) as fee')
            ->leftJoin('oc_seller_asset_combo_fee as acf', function (JoinClause $j) {
                $j->on('ro.receive_order_id', '=', 'acf.receive_order_id')
                    ->where('acf.type_id', 1); // type = 1 海运费
            })
            ->where(['ro.customer_id' => $this->customerId,])
            ->where('ro.receive_number', 'like', "$this->keyword%")
            ->groupBy(['ro.receive_order_id']);
        foreach ($productIds as $productId) {
            $tempQuery = ReceiptsOrderDetail::query()
                ->alias('rod')
                ->select('rod.receive_order_id')
                ->where('rod.product_id', $productId)
                ->whereExists(function ($q) {
                    $q->select('*')
                        ->from('tb_sys_batch as sb')
                        ->whereRaw('sb.product_id = rod.product_id')
                        ->whereRaw('sb.receipts_order_id = rod.receive_order_id')
                        ->where('sb.source_code', '入库单收货') // 1表示入库单收货
                        ->where('sb.customer_id', $this->customerId)
                        ->where('sb.onhand_qty', '>', 0);
                });
            $query = $query->whereIn('ro.receive_order_id', $tempQuery);
        }
        $res = db(new Expression('(' . get_complete_sql($query) . ') as t'))
            ->orderByDesc('t.receive_order_id')
            ->get();

        return $res->map(function ($item) {
            return get_object_vars($item);
        })->toArray();
    }
}
