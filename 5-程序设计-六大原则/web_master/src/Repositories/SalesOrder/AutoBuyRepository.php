<?php

namespace App\Repositories\SalesOrder;

use App\Components\Traits\RequestCachedDataTrait;
use App\Enums\Pay\PayCode;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Exception\SalesOrder\AutoPurchaseException;
use App\Exception\SalesOrder\Enums\AutoPurchaseCode;
use App\Logging\Logger;
use App\Models\Cart\Cart;
use App\Models\Link\CustomerPartnerToProduct;
use App\Models\Link\OrderAssociated;
use App\Models\Product\Product;
use  App\Models\SalesOrder\CustomerSalesOrderLine;

class AutoBuyRepository
{
    use RequestCachedDataTrait;

    /**
     * 自动购买判断能否添加到购物车
     * @param array $productInfo
     * @param int $salesLineId
     * @param int $quantity
     * @return false
     */
    function canAddCart($productInfo, $buyerId, $salesLineId, $quantity)
    {
        if ($productInfo['left_qty'] < $quantity) {
            return false;
        }
        $saleOrderLine = CustomerSalesOrderLine::select('item_code', 'qty')
            ->where('id', $salesLineId)
            ->first();
        $associatedQty = intval(OrderAssociated::where('sales_order_line_id', $salesLineId)->sum('qty'));
        $cartQuantity = Cart::query()
            ->where('product_id', $productInfo['product_id'])
            ->where('customer_id', $buyerId)
            ->where('api_id', session('api_id'))
            ->value('quantity') ?: 0;
        if ($saleOrderLine->qty < ($associatedQty + $quantity + $cartQuantity)) {
            return false;
        }
        return true;
    }

    /**
     * 校验自动购买的明细的是否在销售订单明细范围之内
     * @param array $cartIds
     * @param int $buyerId
     * @param int $saleOrderId
     * @return bool
     * @throws AutoPurchaseException
     */
    function checkSaleOder($cartIds, $buyerId, $saleOrderId)
    {
        $salesOrderLines = CustomerSalesOrderLine::select('item_code', 'qty')
            ->where('header_id', $saleOrderId)
            ->get()
            ->keyBy('item_code')->toArray();
        $associatedQtys = OrderAssociated::selectRaw('product_id,SUM(qty) as qty')
            ->where('sales_order_id', $saleOrderId)
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id')->toArray();
        $carts = Cart::query()
            ->alias('oc')
            ->select(['oc.*', 'op.sku', 'op.product_type'])
            ->leftJoin('oc_product as op', 'op.product_id', '=', 'oc.product_id')
            ->whereIn('oc.cart_id', $cartIds)
            ->get();
        foreach ($carts as $cart) {
            // 排除欧洲补运费的产品
            if ($cart->product_type == ProductType::COMPENSATION_FREIGHT) {
                continue;
            }

            if (!isset($salesOrderLines[$cart->sku])) {
                throw new AutoPurchaseException(AutoPurchaseCode::CODE_购买失败_购买数量超出销售订单范围, $cart->sku);
            }
            $associateQty = $associatedQtys[$cart->product_id]['qty'] ?? 0;
            if ($salesOrderLines[$cart->sku]['qty'] < $cart->quantity + $associateQty) {
                throw new AutoPurchaseException(AutoPurchaseCode::CODE_购买失败_购买数量超出销售订单范围, $cart->sku);
            }
        }
        Logger::autoPurchase(['-----自动购买--下单时购物车明细---', 'json' => ['saleOrderId' => $saleOrderId, 'carts' => $carts->toArray()]]);
        return true;
    }

    /**
     * 检查货值价格
     * @param array $total_data
     * @param array $products
     * @param bool $isEurope
     * @return bool
     */
    function checkSubToal($total_data, $products, $isEurope = false)
    {
        $totals = $total_data['all_totals'] ?? $total_data['totals'];

        $totalDataByCode = array_column($totals, null, 'code');
        $subTotal = $totalDataByCode['sub_total']['value'];
        if (!empty($totalDataByCode['wk_pro_quote'])) {
            $subTotal += $totalDataByCode['wk_pro_quote']['value'];
            // 欧洲加上服务费的拆分
            if ($isEurope) {
                $subTotal += $totalDataByCode['wk_pro_quote_service_fee']['value'];
            }
        }
        $total = 0;
        foreach ($products as $item) {
            $price = $item['current_price'] ?? $item['price'];
            if ($item['type_id'] == ProductTransactionType::SPOT) {
                $price = $item['quote_amount'] ?? $item['spot_price'];
            }
            $total += $price * $item['quantity'];
        }
        // 欧洲加上服务费
        if ($isEurope) {
            $serviceFee = array_column($totals, null, 'code')['service_fee']['value'];
            $subTotal = bcadd($subTotal, $serviceFee, 2);
        }
        if (bccomp($total, $subTotal) === 0 && $subTotal >= 0) {
            return true;
        }
        Logger::autoPurchase(['----自动购买--货值校验--ERROR--', 'json' => ['total_data' => $total_data, 'products' => $products]]);
        return false;
    }

    /**
     * 判断是某个buyer的new order的销售单
     * @param int $buyerId
     * @param int $lineId
     * @return bool
     */
    public function isBuyerNewOrderSalesOrderLine($buyerId, $lineId): bool
    {
        return CustomerSalesOrderLine::query()->alias('l')
            ->joinRelations(['customerSalesOrder as o'], 'left')
            ->where([
                'o.buyer_id' => $buyerId,
                'l.id' => $lineId,
                'o.order_status' => CustomerSalesOrderStatus::TO_BE_PAID
            ])->exists();
    }

    /**
     * 检测是否有效的产品
     * @param $itemCode
     * @return bool
     */
    public function checkValidItemCode($itemCode): bool
    {
        return Product::query()->where([
            'sku' => $itemCode,
            'buyer_flag' => 1,
            'is_deleted' => 0,
            'status' => 1
        ])->exists();
    }

    public function checkSalesOrderAssociate(array $salesOrderIdArr):array
    {
        $ret = [];
        $list = CustomerSalesOrderLine::query()->alias('l')
            ->leftJoinRelations(['orderAssociates as a'])
            ->whereIn('l.header_id',$salesOrderIdArr)
            ->selectRaw('l.id,l.header_id,l.qty,sum(a.qty) as associate_qty')
            ->groupBy('l.id')
            ->get();
        $bool = $list->isEmpty();
        foreach($salesOrderIdArr as $key => $value){
            $ret[$value] = !$bool;
        }
        if(!$bool){
            foreach($list as $item){
                if( $ret[$item->header_id] && ($item->qty != $item->associate_qty)){
                    $ret[$item->header_id] = false;
                }
            }
        }
        return  $ret;

    }

    /**
     * 获取支付方式和Code
     * @return array
     * @throws \Exception
     */
    public function getPaymentCodeAndMethod()
    {
        $paymentCode = PayCode::PAY_LINE_OF_CREDIT;
        // 内部自动购买采销异体账号
        if (customer()->innerAutoBuyAttr1()) {
            $paymentCode = PayCode::PAY_VIRTUAL;
        }

        $paymentMethod = PayCode::getDescriptionWithPoundage($paymentCode);

        //内部 自动购买-FBA自提 只支持虚拟支付
        if (customer()->isInnerFBA() && PayCode::PAY_VIRTUAL != $paymentCode) {
            throw new AutoPurchaseException(AutoPurchaseCode::CODE_购买失败_账号与支付方式异常);
        }

        return [$paymentCode, $paymentMethod];
    }

    /**
     * 获取欧洲补运费产品，以customerId为key, 添加缓存
     * @return array
     */
    public function getSellerFreightProductMap(): array
    {
        $cacheKey = [__CLASS__, __FUNCTION__];
        $sellerFreightProductMap = $this->getRequestCachedData($cacheKey);
        if (!is_null($sellerFreightProductMap)) {
            return $sellerFreightProductMap;
        }

        $sellerProductIds = json_decode(configDB('europe_freight_product_id'),true);
        $sellerFreightProductMap =  CustomerPartnerToProduct::query()->alias('tp')
            ->joinRelations('product as p')
            ->whereIn('tp.product_id', $sellerProductIds)
            ->select(['tp.customer_id', 'p.product_id', 'p.sku'])
            ->get()
            ->keyBy('customer_id')
            ->toArray();

        if ($sellerFreightProductMap) {
            $this->setRequestCachedData($cacheKey, $sellerFreightProductMap);
        }

        return $sellerFreightProductMap;
    }
}
