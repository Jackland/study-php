<?php

namespace App\Services\SalesOrder\AutoPurchase;

use App\Enums\Product\ProductType;
use App\Exception\SalesOrder\AutoPurchaseException;
use App\Exception\SalesOrder\Enums\AutoPurchaseCode;
use App\Logging\Logger;
use App\Models\Cart\Cart;
use App\Models\Link\CustomerPartnerToProduct;
use App\Models\Link\OrderAssociated;
use App\Models\Order\OrderProduct;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Repositories\SalesOrder\AutoBuyRepository;
use Carbon\Carbon;
use Cart\Customer;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use ModelExtensionModuleEuropeFreight;

/**
 * 欧洲补运费逻辑
 * Class EuropeFreightComponent
 * @package App\Services\SalesOrder\AutoPurchase
 */
class EuropeFreightComponent
{
    /**
     * @var CustomerSalesOrder
     */
    protected $salesOrder;

    /**
     * @var string
     */
    protected $country;

    /**
     * @var bool
     */
    private $isHandle;

    /**
     * @var array
     * @example ["seller_id"=>"freight_product_id"]
     */
    private $sellerFreightProductMap = [];

    /**
     * 获取补运费的请求数据
     * @var array
     */
    private $freightRequestData = [];

    /**
     * 加购处理的数据
     * @var array
     */
    private $freights = [];

    /**
     * EuropeFreightComponent constructor.
     * @param CustomerSalesOrder $salesOrder
     * @param Customer $customer
     */
    public function __construct(CustomerSalesOrder $salesOrder, Customer $customer)
    {
        $this->salesOrder = $salesOrder;
        // 国际单且不是上门取货的用户
        $this->isHandle = $this->isInternational() && !$customer->isCollectionFromDomicile();

        if ($this->isHandle) {
            Logger::autoPurchase('欧洲补运费逻辑：start, sales_order_id=' . $salesOrder->order_id);
            $this->sellerFreightProductMap = app(AutoBuyRepository::class)->getSellerFreightProductMap();
        }
    }

    /**
     * 判断是否需要处理欧洲补运费
     * @return bool
     */
    public function isHandle(): bool
    {
        return $this->isHandle;
    }

    /**
     * 补运费处理（获取和加购）
     * @throws Exception
     */
    public function handle()
    {
        if (!$this->isHandle || empty($this->freightRequestData)) {
            return;
        }
        Logger::autoPurchase('欧洲补运费逻辑请求数据：' . json_encode($this->freightRequestData));

        // 获取补运费产品的数据
        /** @var ModelExtensionModuleEuropeFreight $modelExtensionModuleEuropeFreight */
        $modelExtensionModuleEuropeFreight = load()->model('extension/module/europe_freight');
        $freights = $modelExtensionModuleEuropeFreight->getFreight($this->freightRequestData);
        Logger::autoPurchase('欧洲补运费逻辑请求数据结果：' . json_encode($freights));

        foreach ($freights as $freight) {
            // 没有取到店铺的补运费产品
            if (empty($this->sellerFreightProductMap[$freight['seller_id']])) {
                throw new AutoPurchaseException(AutoPurchaseCode::CODE_补运费失败_没有找到店铺的补运费产品);
            }

            if ($freight['code'] != 200) {
                throw new AutoPurchaseException(AutoPurchaseCode::CODE_补运费失败_计算补运费失败);
            }

            // 加购处理
            $productId = $this->sellerFreightProductMap[$freight['seller_id']]['product_id'];
            $qty = (int)(ceil($freight['freight']) * $freight['qty']);
            $this->addFreightProductCart($productId, $qty);

            // 加缓存记录
            $freight['cart_qty'] = $qty;
            $freight['cart_product_id'] = $productId;
            $this->freights[] = $freight;
        }
    }

    /**
     * 补运费绑定
     * @param int $orderId
     * @throws Exception
     */
    public function bind(int $orderId)
    {
        Logger::autoPurchase('欧洲补运费逻辑绑定: orderID=' . $orderId);
        if (empty($orderId)) {
            return;
        }

        $orderProducts = OrderProduct::query()->with('product')->where('order_id', $orderId)->get();
        if ($orderProducts->isEmpty()) {
            Logger::autoPurchase('欧洲补运费逻辑绑定: 未找到采购单明细');
            return;
        }

        // 校验需要购买的数量和实际购买数量
        $this->checkQtyAndProduct($orderProducts);

        $orderAssociatedRecords = [];
        foreach ($this->freights as $freight) {
            foreach ($orderProducts as $orderProduct) {
                /** @var OrderProduct $product_id */
                // 非补运费产品产品
                if ($orderProduct->product->product_type != ProductType::COMPENSATION_FREIGHT) {
                    continue;
                }
                if ($orderProduct->product_id != $freight['cart_product_id']) {
                    continue;
                }

                // 补运费产品不需计算优惠券的绑定金额
                $orderAssociatedRecords[] = [
                    'sales_order_id' => $this->salesOrder->id,
                    'sales_order_line_id' => $freight['line_id'],
                    'order_id' => $orderProduct->order_id,
                    'order_product_id' => $orderProduct->order_product_id,
                    'qty' => $freight['cart_qty'],
                    'product_id' => $orderProduct->product_id,
                    'seller_id' => $freight['seller_id'],
                    'buyer_id' => $this->salesOrder->buyer_id,
                    'CreateUserName' => '1',
                    'CreateTime' => Carbon::now(),
                ];

            }
        }
        OrderAssociated::query()->insert($orderAssociatedRecords);
    }

    /**
     * 生成欧洲补运费囤货部分请求数据
     * @param int $productId
     * @param int $salesOrderLineId
     * @param int $qty
     * @param int $sellerId
     * @param int $orderProductId
     */
    public function generateStockRequestData(int $productId, int $salesOrderLineId, int $qty, int $sellerId, int $orderProductId)
    {
        if ($qty < 0 || empty($productId) || empty($salesOrderLineId) || empty($sellerId) || empty($orderProductId)) {
            return;
        }

        $this->freightRequestData[] = [
            'product_id' => $productId,
            'from' => $this->country,
            'to' => $this->salesOrder->ship_country,
            'zip_code' => $this->salesOrder->ship_zip_code,
            'line_id' => $salesOrderLineId,
            'header_id' => $this->salesOrder->id,
            'order_product_id' => $orderProductId,
            'seller_id' => $sellerId,
            'qty' => $qty,
        ];
    }

    /**
     * 处理欧洲补运费生成采购请求数据
     * @param int $productId
     * @param int $salesOrderLineId
     * @param int $qty
     */
    public function generatePurchaseRequestData(int $productId, int $salesOrderLineId, int $qty)
    {
        if ($qty < 0 || empty($productId) || empty($salesOrderLineId)) {
            return;
        }

        $this->freightRequestData[] = [
            'product_id' => $productId,
            'from' => $this->country,
            'to' => $this->salesOrder->ship_country,
            'zip_code' => $this->salesOrder->ship_zip_code,
            'line_id' => $salesOrderLineId,
            'header_id' => $this->salesOrder->id,
            'seller_id' => CustomerPartnerToProduct::query()->where('product_id', $productId)->value('customer_id'),
            'qty' => $qty,
        ];
    }

    public function __destruct()
    {
        Logger::autoPurchase('欧洲补运费逻辑：end, sales_order_id=' . $this->salesOrder->order_id);
    }

    /**
     * 判断是否国际单
     * @return bool
     */
    private function isInternational(): bool
    {
        // 欧洲国别的才有国际单， country在初始化某个buyer的销售单会设置session值
        $country = session('country', '');
        if (empty($country) || !in_array($country, ['DEU', 'GBR'])) {
            return false;
        }

        // 销售单国际单判断
        if (empty($this->salesOrder->is_international)) {
            return false;
        }

        // 是否需要判断ship_country

        $this->country = $country;
        return true;
    }

    /**
     * 添加补运费产品进购物车
     * @param int $productId
     * @param int $qty
     * @throws Exception
     */
    private function addFreightProductCart(int $productId, int $qty)
    {
        Logger::autoPurchase('欧洲补运费逻辑加购数据：product_id=' . $productId . ',qty=' . $qty);
        $data = [
            'api_id' => session('api_id', 1),
            'customer_id' => $this->salesOrder->buyer_id,
            'product_id' => $productId,
            'delivery_type' => session('delivery_type', 0),
        ];

        $cart = Cart::query()->where($data)->first();
        if (!empty($cart)) {
            Cart::query()->where('cart_id', $cart->cart_id)->increment('quantity', $qty);
            return;
        }

        // 防止增加数据时唯一索引报错
        Cart::query()
            ->where('api_id', 0)
            ->where('customer_id', $this->salesOrder->buyer_id)
            ->where('product_id', $productId)
            ->where('delivery_type', session('delivery_type', 0))
            ->delete();

        $data['session_id'] = session()->getId();
        $data['recurring_id'] = 0;
        $data['option'] = json_encode([]);
        $data['type_id'] = 0;
        $data['agreement_id'] = null;
        $data['quantity'] = $qty;
        $data['date_added'] = Carbon::now()->toDateTimeString();
        Cart::query()->insert($data);
    }

    /**
     * 校验需要购买的数量和实际购买数量
     * @param Collection $orderProducts
     * @throws AutoPurchaseException
     */
    private function checkQtyAndProduct(Collection $orderProducts)
    {
        $needBuyProductQtyMap = [];
        foreach ($this->freights as $freight) {
            $productId = $freight['cart_product_id'];
            if (isset($needBuyProductQtyMap[$productId])) {
                $needBuyProductQtyMap[$productId] += $freight['cart_qty'];
            } else {
                $needBuyProductQtyMap[$productId] = $freight['cart_qty'];
            }
        }
        foreach ($orderProducts as $orderProduct) {
            /** @var OrderProduct $product_id */
            // 非补运费产品产品
            if ($orderProduct->product->product_type != ProductType::COMPENSATION_FREIGHT) {
                continue;
            }
            if (!isset($needBuyProductQtyMap[$orderProduct->product_id])) {
                throw new AutoPurchaseException(AutoPurchaseCode::CODE_补运费失败_绑定产品和购买产品不一致);
            }
            if ($orderProduct->quantity <> $needBuyProductQtyMap[$orderProduct->product_id]) {
                throw new AutoPurchaseException(AutoPurchaseCode::CODE_补运费失败_绑定数量和购买数量不一致);
            }
        }
    }
}
