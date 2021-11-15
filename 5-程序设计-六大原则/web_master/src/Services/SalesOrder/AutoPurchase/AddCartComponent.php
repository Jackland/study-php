<?php

namespace App\Services\SalesOrder\AutoPurchase;

use App\Exception\SalesOrder\AutoPurchaseException;
use App\Exception\SalesOrder\Enums\AutoPurchaseCode;
use App\Logging\Logger;
use App\Models\Cart\Cart;
use App\Repositories\SalesOrder\AutoBuyRepository;
use Carbon\Carbon;
use Framework\Exception\Exception;

class AddCartComponent
{
    private $itemCode;
    private $qty;
    private $saleOrderLineId;
    private $sellerId;
    private $customerId;

    /**
     * AddCartComponent constructor.
     * @param string $itemCode
     * @param  $qty
     * @param  $saleOrderLineId
     * @param int $sellerId
     * @throws Exception
     */
    public function __construct(string $itemCode, $qty, $saleOrderLineId, $sellerId)
    {
        $this->itemCode = $itemCode;
        $this->qty = $qty;
        $this->saleOrderLineId = $saleOrderLineId;
        $this->sellerId = $sellerId;
        $this->customerId = customer()->getId();

        $this->validate();
    }

    /**
     * 处理自动购买加购
     * @return Cart
     * @throws Exception
     */
    public function handle(): Cart
    {
        /** @var \ModelExtensionModulePrice $modelExtensionModulePrice */
        $modelExtensionModulePrice = load()->model('extension/module/price');
        // 找到优先购买的店铺的产品
        $product = $modelExtensionModulePrice->getAutoBuyProductId($this->itemCode, $this->customerId, $this->qty, $this->sellerId);
        Logger::autoPurchase('----ADD CART PRODUCT----', 'info', [
            Logger::CONTEXT_VAR_DUMPER => ['product' => $product],
        ]);

        // 判断能否添加到购物车
        if (!app(AutoBuyRepository::class)->canAddCart($product, $this->customerId, $this->saleOrderLineId, $this->qty)) {
            throw new AutoPurchaseException(AutoPurchaseCode::CODE_产品加入购物车失败_数量和实际购物车数量不一致, $this->itemCode);
        }

        $cartId = $this->addWithBuyerId($product['product_id'], $product['type_id'], $product['agreement_id']);

        return Cart::query()->find($cartId);
    }

    /**
     * 校验
     * @throws Exception
     */
    private function validate()
    {
        if (!app(AutoBuyRepository::class)->checkValidItemCode($this->itemCode)) {
            throw new AutoPurchaseException(AutoPurchaseCode::CODE_产品加入购物车失败_未上架不可售卖或被删除, $this->itemCode);
        }

        if (!app(AutoBuyRepository::class)->isBuyerNewOrderSalesOrderLine($this->customerId, $this->saleOrderLineId)) {
            throw new AutoPurchaseException(AutoPurchaseCode::CODE_产品加入购物车失败_不是销售订单上的产品, $this->itemCode);
        }
    }

    /**
     * 自动购买加购物车
     * @param int $productId
     * @param int $typeId
     * @param null $agreementId
     * @return int
     * @throws \Exception
     */
    private function addWithBuyerId($productId, $typeId = 0, $agreementId = null): int
    {
        // 自动购买api_id为1
        $apiId = session('api_id', 1);
        $deliveryType = session('delivery_type', 0);

        $cart = Cart::query()->where([
            'api_id' => $apiId,
            'customer_id' => $this->customerId,
            'session_id' => session()->getId(),
            'product_id' => $productId,
            'recurring_id' => 0,
            'option' => json_encode([]),
            'type_id' => $typeId,
            'agreement_id' => $agreementId,
            'delivery_type' => $deliveryType,
        ])->first();
        if (!empty($cart)) {
            //增加数量
            Cart::query()->where('cart_id', $cart->cart_id)->increment('quantity', $this->qty);
            return $cart->cart_id;
        }

        // 防止增加数据时唯一索引报错
        Cart::query()->where('customer_id', $this->customerId)->where('product_id', $productId)->where('delivery_type', $deliveryType)->delete();

        $saveCart = [
            'api_id' => $apiId,
            'customer_id' => $this->customerId,
            'session_id' => session()->getId(),
            'product_id' => $productId,
            'recurring_id' => 0,
            'option' => json_encode([]),
            'type_id' => $typeId,
            'quantity' => $this->qty,
            'date_added' => Carbon::now(),
            'agreement_id' => $agreementId,
            'delivery_type' => $deliveryType,
        ];

        return Cart::query()->insertGetId($saveCart);
    }
}
