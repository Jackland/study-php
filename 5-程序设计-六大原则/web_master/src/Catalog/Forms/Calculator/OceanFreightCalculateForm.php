<?php

namespace App\Catalog\Forms\Calculator;

use App\Enums\Country\Country;
use App\Helper\CurrencyHelper;
use App\Models\Product\Product;
use App\Models\Product\ProductSetInfo;
use App\Models\Stock\ReceiptsOrder;
use App\Models\Stock\ReceiptsOrderDetail;
use App\Repositories\FeeOrder\StorageFeeRepository;
use Framework\Model\RequestForm\RequestForm;
use kriss\bcmath\BCS;

class OceanFreightCalculateForm extends RequestForm
{
    public $productId;

    public $receiveOrderIds = [];

    private $bcConfig = [
        'scale' => 4, // 保留4位
        'ceil' => true, // 向上保留
    ];

    protected function getRules(): array
    {
        return [
            'productId' => ['required', 'integer', 'min:1'],
            'receiveOrderIds' => ['required', 'array'],
        ];
    }

    // 详细计算单个产品的海运费

    /**
     * 【Case 1】 一个批次有多个产品，先计算单位体积海运费，再计算某个产品的海运费：
     * 1. 单位体积海运费：批次M的整柜海运费/（产品A单位体积*产品A件数+产品B单位体积*产品B件数）
     * 2. 产品A海运费：产品A体积*单位体积海运费
     * 【Case 2】 同一产品分批货运（关联了多个入库单号<以输入为准，逗号隔开<支持中英文>>），算加权平均值
     * 1. 批次M的单位体积海运费：批次M的整柜海运费/（产品A单位体积*产品A件数+产品B单位体积*产品B件数）
     * 2. 批次N的单位体积海运费：批次N的整柜海运费/（产品A单位体积*产品A件数+产品B单位体积*产品B件数）
     * 3. 产品A海运费：产品A体积*（批次M的单位体积海运费*批次M中A的件数+批次N的单位体积海运费*批次N中A的件数)/(批次M中A的件数+批次N中A的件数)
     */
    public function getData(): array
    {
        bcscale(4);
        $receiptOrders = ReceiptsOrder::with(['receiptDetails'])
            ->whereIn('receive_number', $this->receiveOrderIds)
            ->get();
        $storageFeeRepo = app(StorageFeeRepository::class);
        $result = $receiptOrders->map(function (ReceiptsOrder $receiptsOrder) use ($storageFeeRepo) {
            $productQtyMap = [];
            // 获取海运费
            $oceanFreight = (float)db('oc_seller_asset_combo_fee')
                ->where(['receive_order_id' => $receiptsOrder->receive_order_id, 'type_id' => 1])
                ->value('fee');
            // 获取总计体积
            $volumeList = $receiptsOrder->receiptDetails
                ->map(function (ReceiptsOrderDetail $receiptsOrderDetail) use ($storageFeeRepo, &$productQtyMap) {
                    $receiveQty = (int)($receiptsOrderDetail->received_qty ?? $receiptsOrderDetail->expected_qty);
                    $productId = $receiptsOrderDetail->product_id;
                    $productQtyMap[$productId] = $productQtyMap[$productId] ?? 0 + $receiveQty;
                    $product = Product::find($receiptsOrderDetail->product_id);
                    return BCS::create(1, $this->bcConfig)->mul(
                        $storageFeeRepo->calculateProductVolume($product)[0],
                        $receiveQty
                    )->getResult();
                })
                ->toArray();
            $totalVolume = BCS::create(0, $this->bcConfig)->add(...$volumeList)->getResult();
            // 单位体积运费 美元/立方米
            $oceanFeePerVolume = bcdiv($oceanFreight, $totalVolume);
            // 考虑到可能为combo的情况 算出当前运单下理论的数量
            $product = Product::with(['combos',])->find($this->productId);
            $qtyArr = [];
            if ($product->combo_flag && $product->combos->isNotEmpty()) {
                $product->combos->map(function (ProductSetInfo $combo) use ($productQtyMap, &$qtyArr) {
                    $qtyArr[] = floor($productQtyMap[$combo->set_product_id] / $combo->qty);
                });
            } else {
                $qtyArr[] = $productQtyMap[$this->productId];
            }
            return [
                'receive_order_id' => $receiptsOrder->receive_order_id,
                'fee' => $oceanFeePerVolume,
                'qty' => (int)min($qtyArr)
            ];
        })->toArray();
        // 计算最后的税运费
        $totalFee = 0;
        $totalQty = 0;
        foreach ($result as $val) {
            ['fee' => $fee, 'qty' => $qty] = $val;
            $totalFee += (float)bcmul($fee, $qty, 4);
            $totalQty += $qty;
        }
        [$volume,] = $storageFeeRepo->calculateProductVolume(Product::find($this->productId));
        // 获取当前seller国别id 获取汇率
        $exchangeRate = CurrencyHelper::getExchangeRateByCountryId(
            Country::AMERICAN,
            Product::find($this->productId)->customerPartner->country_id
        );
        return ['oceanFreight' => bcmul(bcmul($volume, bcdiv($totalFee, $totalQty)), $exchangeRate)];
    }
}
