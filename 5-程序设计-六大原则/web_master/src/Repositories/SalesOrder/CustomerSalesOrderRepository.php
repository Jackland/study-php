<?php

namespace App\Repositories\SalesOrder;

use App\Components\Storage\StorageCloud;
use App\Enums\Common\YesNoEnum;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Product\ProductType;
use App\Enums\SalesOrder\CustomerSalesExportStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\SalesOrder\HomePickCarrierType;
use App\Enums\SalesOrder\HomePickImportMode;
use App\Enums\SalesOrder\HomePickLabelContainerType;
use App\Enums\SalesOrder\JoyBuyOrderStatus;
use App\Enums\Warehouse\SellerType;
use App\Models\Customer\Customer;
use App\Models\Link\OrderAssociated;
use App\Models\Link\OrderAssociatedPre;
use App\Models\Product\Product;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use App\Models\SalesOrder\CustomerSalesOrderPickUp;
use App\Models\SalesOrder\CustomerSalesOrderTemp;
use App\Models\SalesOrder\HomePickAmazonTemp;
use App\Models\SalesOrder\HomePickOtherTemp;
use App\Models\SalesOrder\HomePickWalmartTemp;
use App\Models\SalesOrder\HomePickWayfairTemp;
use App\Models\SalesOrder\JoyBuyOrderInfo;
use App\Repositories\Order\OrderRepository;
use App\Repositories\Product\BatchRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\SellerAsset\SellerAssetRepository;
use App\Repositories\Setup\SetupRepository;
use App\Repositories\Track\TrackRepository;
use App\Widgets\ImageToolTipWidget;
use Exception;
use Framework\Helper\Json;
use Framework\Model\EloquentModel;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use kriss\bcmath\BCS;
use ModelAccountSalesOrderMatchInventoryWindow;

/**
 * Class CustomerSalesOrderRepository
 * @package App\Repositories\SalesOrder
 */
class CustomerSalesOrderRepository
{
    const SALES_ORDER_DROP_SHIP_LINK_URL = 'index.php?route=account/sales_order/sales_order_management/customerOrderSalesOrderDetails&id=';
    const SALES_ORDER_PICK_UP_LINK_URL = 'index.php?route=account/customer_order/customerOrderSalesOrderDetails&id=';
    const SALES_ORDER_CWF_LINK_URL = 'index.php?route=account/Sales_Order/CloudWholesaleFulfillment/info&id=';
    const PRODUCT_LINK_URL = 'index.php?route=product/product&product_id=';
    const ERROR_MSG = [
        'CWF_ERROR' => 'Shipment tracking is not available with CWF orders.',
        'ORDER_ERROR' => 'No search results found for this order.',
        'ORDER_EXIST_ERROR' => 'Shipment tracking is not available with this order.'
    ];

    /**
     * 校验一组订单中是否符合指定订单状态
     * 不符合的会被剔除
     *
     * @param array $salesOrderIdArr
     * @param int|array $orderStatus 需要校验的订单状态
     * @return array 返回剔除过后的订单id
     */
    public function checkOrderStatus(array $salesOrderIdArr, $orderStatus)
    {
        $orderStatus = is_array($orderStatus) ? $orderStatus : [$orderStatus];
        $salesOrderStatusList = $this->getCustomerSalesOrderStatus($salesOrderIdArr);
        foreach ($salesOrderIdArr as $key => $salesOrderId) {
            if (!array_key_exists($salesOrderId, $salesOrderStatusList)) {
                //不存在直接删除
                unset($salesOrderIdArr[$key]);
                continue;
            }
            $salesOrderStatus = (int)$salesOrderStatusList[$salesOrderId];
            if (!in_array($salesOrderStatus, $orderStatus)) {
                //订单状态不匹配，删除
                unset($salesOrderIdArr[$key]);
            }
        }
        return $salesOrderIdArr;
    }

    /**
     * 获取销售订单状态
     *
     * @param $salesOrderId
     * @return array|int
     */
    public function getCustomerSalesOrderStatus($salesOrderId)
    {
        if (is_array($salesOrderId)) {
            $list = CustomerSalesOrder::whereIn('id', $salesOrderId)->get(['id', 'order_status']);
            return $list->pluck('order_status', 'id')->toArray();
        } else {
            return (int)CustomerSalesOrder::find($salesOrderId, 'order_status')->order_status;
        }
    }

    /**
     * @param array $orderIdArr
     * @param int $customerId BuyerId
     * @param bool $isCollectionFromDomicile 是否为上门取货的Buyer
     * @return array 返回订单物流数据
     * @throws Exception
     * @author xxl
     * @description
     * @date 14:23 2020/11/26
     */
    public function getIsExportCustomerSalesOrder($orderIdArr, $customerId, $isCollectionFromDomicile = false)
    {
        $salesOrderDetailLinkUrl = ($isCollectionFromDomicile ? self::SALES_ORDER_PICK_UP_LINK_URL : self::SALES_ORDER_DROP_SHIP_LINK_URL);
        $trackingRepository = app(TrackRepository::class);
        $imageTool = load()->model('tool/image');
        $saleOrders = CustomerSalesOrder::query()->alias('a')
            ->leftJoinRelations(['lines as b'])
            ->leftJoin('oc_order_cloud_logistics as c', 'a.id', '=', 'c.sales_order_id')
            ->with([
                'orderAssociates',
                'orderAssociates.product'
            ])
            ->whereIn('a.order_id', $orderIdArr)
            ->where('a.buyer_id', '=', $customerId)
            ->whereNull('c.id')
            ->where(function ($query) {
                $query->Where('b.program_code', '=', 'OMD SYN')
                    ->orWhere('b.is_exported', '=', 1);
            })
            ->selectRaw("a.id as id,b.header_id,a.order_id,a.ship_address1,a.ship_address2,a.ship_name as recipient,a.ship_state,a.ship_city,a.ship_zip_code,a.ship_country")
            ->get();
        $salesOrderIdArr = $saleOrders->pluck('order_id')->toArray();
        $trackingInfos = $trackingRepository->getTrackingInfoBySalesOrderId($salesOrderIdArr);
        $productImage = [];
        foreach ($saleOrders as $saleOrder) {
            $saleOrder->address = $saleOrder->ship_address1 . ' ' . $saleOrder->ship_address2;
            $headerId = $saleOrder->header_id;
            $orderId = $saleOrder->order_id;
            $address = $saleOrder->address . ',' . $saleOrder->ship_city . ',' . $saleOrder->ship_state . ',' . $saleOrder->ship_zip_code . ',' . $saleOrder->ship_country;
            $recipient = $saleOrder->recipient;
            $trackingInfos[$orderId]['linkUrl'] = $salesOrderDetailLinkUrl . $headerId;
            $trackingInfos[$orderId]['address'] = isset($trackingInfos[$orderId]['address']) ? $trackingInfos[$orderId]['address'] : $address;
            $trackingInfos[$orderId]['address'] = app('db-aes')->decrypt($trackingInfos[$orderId]['address']);
            $trackingInfos[$orderId]['recipient'] = isset($trackingInfos[$orderId]['recipient']) ? $trackingInfos[$orderId]['recipient'] : $recipient;
            $trackingInfos[$orderId]['recipient'] = app('db-aes')->decrypt($trackingInfos[$orderId]['recipient']);
            $trackingInfos[$orderId]['salesOrder'] = $orderId;
            $trackingInfos[$orderId]['flag'] = isset($trackingInfos[$orderId]['flag']) ? $trackingInfos[$orderId]['flag'] : true;
            $trackingInfos[$orderId]['itemList'] = isset($trackingInfos[$orderId]['itemList']) ? $trackingInfos[$orderId]['itemList'] : [];
            foreach ($saleOrder->orderAssociates as $orderAssociates) {
                $product = $orderAssociates->product;
                $sku = $product->sku;
                $image = $product->image;
                foreach ($trackingInfos[$orderId]['itemList'] as &$item) {
                    if ($item['itemCode'] == $sku) {
                        if (isset($productImage[$product->product_id])) {
                            $item['itemCodeImg'] = $productImage[$product->product_id];
                        } else {
                            $item['itemCodeImg'] = $imageTool->resize($image ?? null, 40, 40);
                        }
                        $item['linkUrl'] = self::PRODUCT_LINK_URL . $product->product_id;
                        break;
                    }
                }
            }
        }
        //查询云送仓订单
        $cwfSalesOrderIds = CustomerSalesOrder::query()->alias('a')
            ->leftJoin('oc_order_cloud_logistics as b', 'a.id', '=', 'b.sales_order_id')
            ->whereIn('a.order_id', $orderIdArr)
            ->where('a.buyer_id', '=', $customerId)
            ->whereNotNull('b.id')
            ->select('a.order_id', 'b.id')
            ->get()
            ->keyBy('order_id')
            ->toArray();
        $cwfSalesOrderArr = array_column($cwfSalesOrderIds, 'order_id');
        //查询buyer存在的订单
        $buyerExistSalesOrderIds = CustomerSalesOrder::query()->alias('a')
            ->whereIn('a.order_id', $orderIdArr)
            ->where('a.buyer_id', '=', $customerId)
            ->select(['a.order_id', 'a.id', 'a.order_status'])
            ->get()
            ->keyBy('order_id')
            ->toArray();
        $buyerExistSalesOrderArr = array_column($buyerExistSalesOrderIds, 'order_id');
        $returnJson = [];
        foreach ($orderIdArr as $orderId) {
            if (in_array($orderId, $cwfSalesOrderArr)) {
                $data = [
                    'flag' => false,
                    'message' => self::ERROR_MSG['CWF_ERROR'],
                    'salesOrder' => $orderId,
                    'recipient' => '',
                    'address' => '',
                    'linkUrl' => self::SALES_ORDER_CWF_LINK_URL . $cwfSalesOrderIds[$orderId]['id'],
                    'itemList' => ''
                ];
                $returnJson[] = $data;
                continue;
            }
            if (isset($trackingInfos[$orderId])) {
                $returnJson[] = $trackingInfos[$orderId];
                continue;
            } else {
                if (in_array($orderId, $buyerExistSalesOrderArr)) {
                    $data = [
                        'flag' => false,
                        'message' => self::ERROR_MSG['ORDER_EXIST_ERROR'],
                        'salesOrder' => $orderId,
                        'recipient' => '',
                        'address' => '',
                        'linkUrl' => $salesOrderDetailLinkUrl . $buyerExistSalesOrderIds[$orderId]['id'],
                        'itemList' => ''
                    ];
                    $returnJson[] = $data;
                    continue;
                }
                $data = [
                    'flag' => false,
                    'message' => self::ERROR_MSG['ORDER_ERROR'],
                    'salesOrder' => $orderId,
                    'recipient' => '',
                    'address' => '',
                    'linkUrl' => '',
                    'itemList' => ''
                ];
                $returnJson[] = $data;
                continue;
            }
        }
        return $returnJson;
    }

    /**
     * 通过销售订单获取seller_ids
     * @param $salesOrderId
     * @return array
     */
    public function calculateSellerListBySalesOrderId($salesOrderId)
    {
        return CustomerSalesOrder::query()
            ->alias('ass')
            ->leftJoin('oc_customer as oc', 'ass.seller_id', '=', 'oc.customer_id')
            ->select(['ass.seller_id', 'oc.accounting_type'])
            ->where([
                'ass.sales_order_id' => $salesOrderId,
            ])
            ->groupBy('ass.seller_id')
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();
    }


    /**
     * 获取某个销售单已同步数量[giga onsite & omd & joy buy]
     * @param int $salesOrderId
     * @return array
     */
    public function calculateSalesOrderIsExportedNumber(int $salesOrderId)
    {
        $isExportNumber = CustomerSalesOrderLine::query()
            ->where('header_id', $salesOrderId)
            ->where(function (Builder $query2) {
                $query2->whereNotNull('is_exported')
                    ->orWhere('program_code', 'OMD SYN');
            })
            ->count();

        $isOmdNumber = CustomerSalesOrderLine::query()
            ->where('header_id', $salesOrderId)
            ->where('program_code', 'OMD SYN')
            ->count();

        $joyBuyerStatus = JoyBuyOrderInfo::query()
            ->where('sales_order_id', $salesOrderId)
            ->value('order_status');

        //欧洲订单+日本订单是否同步过,同步成功&同步失败,都记做同步过(目前没有同步中概念)
        $isZkSynNumber = 0;
        if (customer()->isEurope() || customer()->isJapan()) {
            $isZkSynNumber = CustomerSalesOrderLine::query()
                ->where('header_id', $salesOrderId)
                ->whereIn('is_synchroed', [CustomerSalesExportStatus::SYN_SUCCESS, CustomerSalesExportStatus::SYN_FAILED])
                ->count();
        }

        return [
            'is_export_number' => $isExportNumber,
            'is_omd_number' => $isOmdNumber,
            'joy_buyer_status' => $joyBuyerStatus,
            'is_zk_sync_number' => $isZkSynNumber,
        ];
    }

    /**
     * 获取用户指定状态的订单数量
     *
     * @param int $customerId
     * @param array|int $status
     *
     * @return int
     */
    public function getCustomerSalesOrderCountByStatus($customerId, $status)
    {
        $query = CustomerSalesOrder::query()
            ->where('buyer_id', $customerId);
        if (is_array($status)) {
            $query = $query->whereIn('order_status', $status);
        } else {
            $query = $query->where('order_status', $status);
        }
        return $query->count();
    }

    /**
     * [checkOrderContainsOversizeProduct description] 判断销售订单中华是否含有超大件
     * @param int $salesOrderId
     * @param int $overSizeTag
     * @return bool
     */
    public function checkOrderContainsOversizeProduct(int $salesOrderId, int $overSizeTag = 1)
    {
        return CustomerSalesOrder::query()->alias('o')
            ->joinRelations(['lines as l'])
            ->join('oc_product as p', 'p.sku', '=', 'l.item_code')
            ->join('oc_product_to_tag as tag', function (JoinClause $join) use ($overSizeTag) {
                $join->on('tag.product_id', '=', 'p.product_id')->where('tag.tag_id', '=', $overSizeTag);
            })
            ->where('o.id', $salesOrderId)
            ->exists();
    }

    /**
     * 上门取货，omd对应的StoreId，修改自 getDropshipOmdStoreId 方法
     * @param int $headerId
     * @return int|null
     * @see ModelAccountCustomerOrder::getDropshipOmdStoreId()
     */
    public function getOmdStoreId($headerId)
    {
        $salesOrderInfo = CustomerSalesOrder::query()->find($headerId);
        if (empty($salesOrderInfo)) {
            return null;
        }

        if ($salesOrderInfo->program_code == 'OMD SYN') { //omd同步过来的单子，存在于tb_sys_customer_sales_order_temp中
            $tempInfo = CustomerSalesOrderTemp::query()
                ->where('order_id', $salesOrderInfo->order_id)
                ->where('run_id', $salesOrderInfo->run_id)
                ->first();

            return !empty($tempInfo->buyer_id) ? (int)$tempInfo->buyer_id : null; // 这个的buyer_id 是omd的store_id
        }

        $customerInfo = Customer::query()->find($salesOrderInfo->buyer_id);

        return $customerInfo->groupdesc->name == 'B2B-WillCall' ? 212 : ($customerInfo->groupdesc->name == 'US-DropShip-Buyer' ? 205 : 888);
    }


    /**
     * 销售订单资产风控
     * @param array $sellerProductList [seller_id=>[{product_id=>1,qty=>estimate_freight=>1},...],...]
     * estimate_freight是line表中预估费用
     *
     * @return bool
     */
    public function checkFulfillmentFee($sellerProductList)
    {
        if (empty($sellerProductList)) {
            return true;
        }
        $sellerAssetRepo = app(SellerAssetRepository::class);
        $batchRepo = app(BatchRepository::class);
        $latestClosingBalance = app(SetupRepository::class)->getValueByKey('LATEST_CLOSING_BALANCE') ?? 0;
        foreach ($sellerProductList as $sellerId => $sellerProduct) {
            if (!($sellerAssetRepo->checkSellerRiskCountry($sellerId))) {
                // 非外部seller不校验
                continue;
            }
            // 判断期末余额是否小于设定值
            $sellerBillingAssets = $sellerAssetRepo->getBillingAssets($sellerId);
            if (BCS::create($sellerBillingAssets['current_balance'], ['scale' => 2])->isLessThan($latestClosingBalance)) {
                // 是 订单状态改为 4 On Hold
                return false;
            }
            // 获取总资产(不包含纯物流预计运费的)
            $totalAssets = $sellerAssetRepo->getTotalAssets($sellerId, false);
            if ($totalAssets < 0) {
                // 总资产小于0，直接返回错误
                return false;
            }
            // 获取总资产（包含纯物流预计运费）
            $totalAssets = $sellerAssetRepo->getTotalAssets($sellerId);
            // 当前抵押物减值金额
            $collateralValue = 0;
            // 纯物流订单预计费用
            $estimateFreight = 0;
            foreach ($sellerProduct as $product) {
                $estimateFreight += $product['estimate_freight'];
                $collateralValue += $batchRepo->getCollateralAmountByProduct($product['product_id'], $product['qty']);
            }
            // 当前BP的抵押物获取
            $bpCollateralValue = $sellerAssetRepo->getPureLogisticsCollateralValueInBP($sellerId);
            // 总资产(这里总资产已经减过BP的纯物流费用了)-当前BP的抵押物获取-当前抵押物减值金额 < 订单费用 表示资产不足
            if (BCS::create($totalAssets, ['scale' => 2])->sub($bpCollateralValue, $collateralValue)->isLessThan($estimateFreight)) {
                // 是 订单状态改为 4 On Hold
                return false;
            }
        }
        return true;
    }

    /**
     * 根据sales_order明细获取tags
     * @param int $lineId
     * @param string $sku
     * @return array
     * @throws Exception
     */
    public function getCustomerSalesOrderTags(int $lineId, $sku): array
    {
        $productId = OrderAssociated::where('sales_order_line_id', $lineId)->value('product_id');
        if (!$productId) {
            $productId = $this->getFirstProductId($sku, Customer()->getId());
        }
        if (!$productId) {
            return [];
        }
        $productInfo = Product::with(['tags'])->find($productId);
        return $productInfo->tags->map(function ($tag) {
            return ImageToolTipWidget::widget([
                'tip' => $tag->description,
                'image' => $tag->icon,
            ])->render();
        })->toArray();
    }

    public function getFirstProductId(string $sku, int $customerId)
    {
        $ret = Product::query()->alias('p')
            ->leftJoinRelations(['customerPartnerToProduct as ctp'])
            ->leftJoin(DB_PREFIX . 'buyer_to_seller as bts', 'bts.seller_id', '=', 'ctp.customer_id')
            ->leftJoin(DB_PREFIX . 'customer as c', 'bts.seller_id', '=', 'c.customer_id')
            ->where(
                [
                    'p.sku' => $sku,
                    'bts.buyer_id' => $customerId,
                    'p.status' => 1,
                    'p.is_deleted' => 0,
                    'c.status' => 1,
                    'p.buyer_flag' => 1,
                    'bts.buy_status' => 1,
                    'bts.buyer_control_status' => 1,
                    'bts.seller_control_status' => 1,
                ]
            )->whereIn('p.product_type', [ProductType::NORMAL, ProductType::COMPENSATION_FREIGHT])
            ->orderBy('p.product_id', 'desc')
            ->groupBy(['p.product_id'])
            ->pluck('p.product_id')
            ->toArray();
        if (count($ret) == 1) {
            return $ret[0];
        }
        $model_category_product = load()->model('catalog/product');
        foreach ($ret as $key => $value) {
            $dm_info = $model_category_product->getDelicacyManagementInfoByNoView($value, $customerId);
            if ($dm_info && $dm_info['product_display'] != 0) {
                return $value;
            }
            if ($dm_info == null) {

                return $value;
            }
        }

        return Product::query()
            ->where(
                [
                    'sku' => $sku,
                ]
            )
            ->orderBy('product_id', 'desc')
            ->value('product_id');
    }

    /**
     * homepick 临时表中对应 order_id buyer_id warehouse_code 映射关系
     *
     * @return array
     */
    public static function homePickWareWarehouseConfig(): array
    {
        return [
            HomePickImportMode::IMPORT_MODE_NORMAL => [
                'class' => CustomerSalesOrderTemp::class,
                'order_id' => 'order_id',
                'buyer_id' => 'buyer_id',
                'warehouse_code' => '',
            ],
            HomePickImportMode::IMPORT_MODE_AMAZON => [
                'class' => HomePickAmazonTemp::class,
                'order_id' => 'order_id',
                'buyer_id' => 'buyer_id',
                'warehouse_code' => 'warehouse_name',
            ],
            HomePickImportMode::IMPORT_MODE_WAYFAIR => [
                'class' => HomePickWayfairTemp::class,
                'order_id' => 'order_id',
                'buyer_id' => 'buyer_id',
                'warehouse_code' => 'warehouse_name',
            ],
            HomePickImportMode::IMPORT_MODE_WALMART => [
                'class' => HomePickWalmartTemp::class,
                'order_id' => 'order_id',
                'buyer_id' => 'buyer_id',
                'warehouse_code' => 'warehouse_code',
            ],
            HomePickImportMode::US_OTHER => [
                'class' => HomePickOtherTemp::class,
                'order_id' => 'sales_order_id',
                'buyer_id' => 'buyer_id',
                'warehouse_code' => 'warehouse_name',
            ]
        ];
    }

    /**
     * 根据buyer id & order_id import_mode 找到对应临时表中的仓库code
     *
     * @param string $orderId 销售订单中的order_id
     * @param int $buyerId
     * @param int $importMode 销售订单中的import_mode字段
     * @return string
     */
    public function getHomePickWarehouseCode(string $orderId, int $buyerId, int $importMode): string
    {
        //自提货
        if ($importMode == HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP) {
            $pickUp = CustomerSalesOrderPickUp::query()
                ->leftJoinRelations(['salesOrder as so', 'warehouse as w'])
                ->where('so.order_id', $orderId)
                ->value('WarehouseCode');
            return $pickUp ?? '';
        }
        $configArrMul = static::homePickWareWarehouseConfig();
        $config = $configArrMul[$importMode] ?? [];
        if (!$config) {
            return '';
        }
        /** @var EloquentModel $class */
        $class = app($config['class']);
        if (!$config['warehouse_code']) {
            return '';
        }
        $warehouse_code = $class::query()
            ->where([
                $config['order_id'] => $orderId,
                $config['buyer_id'] => $buyerId,
            ])
            ->value($config['warehouse_code']);
        if ($warehouse_code == 'warehouse_code') {
            $warehouse_code = '';
        }
        return $warehouse_code ?? '';
    }

    /**
     * @param int $salesOrderId
     * @param int $salesOrderLineId
     * @param string $purchaseRunId 一件代发的run id，如果订单是new order 或者 pc时用得到
     * @return array
     * @throws Exception
     */
    public function getPurchasesListBySalesOrderId(int $salesOrderId = 0, int $salesOrderLineId = 0, $purchaseRunId = null): array
    {
        if (!$salesOrderId && !$salesOrderLineId) {
            return [];
        }
        $salesOrder = null;
        if ($salesOrderId) {
            $salesOrder = CustomerSalesOrder::find($salesOrderId);
        } elseif ($salesOrderLineId) {
            $salesOrder = CustomerSalesOrder::query()
                ->whereHas('lines', function ($query) use ($salesOrderLineId) {
                    $query->where('id', $salesOrderLineId);
                })->first();
        }
        $orderAssociates = OrderAssociated::query()->with(['order', 'product', 'orderProductInfo', 'seller'])
            ->when($salesOrderId, function ($query) use ($salesOrderId) {
                $query->where('sales_order_id', $salesOrderId);
            })
            ->when($salesOrderLineId, function ($query) use ($salesOrderLineId) {
                $query->where('sales_order_line_id', $salesOrderLineId);
            })
            ->get();
        if ($orderAssociates->isEmpty()) {
            // 如果还是new order 查出pre里的绑定信息以及需要采购的信息
            $orderAssociates = OrderAssociatedPre::with(['order', 'product', 'orderProductInfo', 'seller'])
                ->when($salesOrderId, function ($query) use ($salesOrderId) {
                    $query->where('sales_order_id', $salesOrderId);
                })
                ->when($salesOrderLineId, function ($query) use ($salesOrderLineId) {
                    $query->where('sales_order_line_id', $salesOrderLineId);
                })
                ->where('run_id', $purchaseRunId)
                ->where('associate_type', 1)
                ->get();
            //region 获取新采购的数据
            if ($purchaseRunId) {
                /** @var ModelAccountSalesOrderMatchInventoryWindow $matchModel */
                $matchModel = load()->model('account/sales_order/match_inventory_window');
                // 获取下单页数据
                $purchaseRecords = $matchModel->getPurchaseRecord($purchaseRunId, $salesOrder->buyer_id, true, $salesOrder->id);
                if (!empty($purchaseRecords)) {
                    foreach ($purchaseRecords as $purchaseRecord) {
                        if ($salesOrderLineId && $purchaseRecord['line_id'] != $salesOrderLineId) {
                            // 如果指定了line，不是这个line的就忽略
                            continue;
                        }
                        $orderAssociatedPre = OrderAssociatedPre::where('sales_order_id', $salesOrderId)
                            ->when($salesOrderLineId, function ($query) use ($salesOrderLineId) {
                                $query->where('sales_order_line_id', $salesOrderLineId);
                            })
                            ->where('run_id', $purchaseRunId)
                            ->where('product_id', $purchaseRecord['product_id'])
                            ->where('associate_type', 2)// 新采购
                            ->orderByDesc('CreateTime')
                            ->first();
                        $orderAssociates->push($orderAssociatedPre);
                    }
                    $orderAssociates->load(['order', 'product', 'orderProductInfo', 'seller']);// 防止1+n查询
                }
            }
            //endregion
        }
        //region 构建均分补运费金额的数据
        // 计算每个line内的seller的补运费商品金额
        // [line_id => [seller_id=>金额]] 按每个seller每个line累加
        $lineSellerFreightProductAmount = [];
        // 计算每个line内的seller的其他商品的数量
        // [line_id => [seller_id=>qty]] 按每个seller每个line累加
        $lineSellerNormalProductQty = [];
        foreach ($orderAssociates as $key => $orderAssociate) {
            $_salesOrderId = $orderAssociate->sales_order_line_id;
            $_sellerId = $orderAssociate->seller_id;
            if ($orderAssociate->product->product_type === ProductType::COMPENSATION_FREIGHT) {
                // 补运费产品
                if (empty($lineSellerFreightProductAmount[$_salesOrderId][$_sellerId])) {
                    $lineSellerFreightProductAmount[$_salesOrderId][$_sellerId] = 0;
                }
                $lineSellerFreightProductAmount[$_salesOrderId][$_sellerId]
                    += ($orderAssociate->qty * ($orderAssociate->orderProduct->price
                        + $orderAssociate->orderProduct->freight_per
                        + $orderAssociate->orderProduct->package_fee
                        + $orderAssociate->orderProduct->service_fee_per));
                // 剔除补运费产品，因为补运费产品后面会均分到每个明细内
                $orderAssociates->pull($key);
            } else {
                // 非补运费产品
                if (empty($lineSellerNormalProductQty[$_salesOrderId][$_sellerId])) {
                    $lineSellerNormalProductQty[$_salesOrderId][$_sellerId] = 0;
                }
                $lineSellerNormalProductQty[$_salesOrderId][$_sellerId] += $orderAssociate->qty;
            }
        }
        //endregion
        $list = [];
        $productIds = $orderAssociates->pluck('product_id')->toArray();
        $productInfos = app(ProductRepository::class)->getProductInfoByProductId($productIds);
        $orderRepo = app(OrderRepository::class);
        foreach ($orderAssociates as $orderAssociate) {
            $productImage = "";
            $tags = [];
            if (!empty($productInfos[$orderAssociate->product_id])) {
                $productImage = StorageCloud::image()->getUrl($productInfos[$orderAssociate->product_id]['image'], ['w' => 60, 'h' => 60]);
                $tags = $productInfos[$orderAssociate->product_id]['tags'];
            }
            // 获取商品货值数据
            $orderProduct = $orderRepo->getOrderProductPrice($orderAssociate->orderProduct);
            // 计算总货值
            $price = ($orderProduct->price + $orderProduct->service_fee_per) * $orderAssociate->qty;
            $freightTotal = ($orderProduct->freight_per + $orderProduct->package_fee) * $orderAssociate->qty;
            // 处理欧洲补运费
            if (!empty($lineSellerFreightProductAmount[$orderAssociate->sales_order_line_id][$orderAssociate->seller_id])) {
                if (!empty($lineSellerNormalProductQty[$orderAssociate->sales_order_line_id][$orderAssociate->seller_id])) {
                    if ($orderAssociate->qty === $lineSellerNormalProductQty[$orderAssociate->sales_order_line_id][$orderAssociate->seller_id]) {
                        // 处理最后一个使用剩下所有的运费
                        $orderAssociateAddFreight = $lineSellerFreightProductAmount[$orderAssociate->sales_order_line_id][$orderAssociate->seller_id];
                    } else {
                        $freightRate = ($orderAssociate->qty / $lineSellerNormalProductQty[$orderAssociate->sales_order_line_id][$orderAssociate->seller_id]);
                        $orderAssociateAddFreight = (int)($freightRate * $lineSellerFreightProductAmount[$orderAssociate->sales_order_line_id][$orderAssociate->seller_id]);
                    }
                    $freightTotal += $orderAssociateAddFreight;
                    $lineSellerNormalProductQty[$orderAssociate->sales_order_line_id][$orderAssociate->seller_id] -= $orderAssociate->qty;
                    $lineSellerFreightProductAmount[$orderAssociate->sales_order_line_id][$orderAssociate->seller_id] -= $orderAssociateAddFreight;
                }
            }
            $list[] = [
                'product_id' => $orderAssociate->product_id,
                'item_code' => optional($orderAssociate->orderProductInfo)->item_code,
                'image' => $productImage,
                'tags' => $tags,
                'order_id' => $orderAssociate->order_id,
                'order_product_id' => $orderAssociate->order_product_id,
                'qty' => $orderAssociate->qty,
                'seller_name' => $orderAssociate->seller->seller->screenname,
                'price' => $price,
                'freight' => $freightTotal,
                'total_amount' => $price + $freightTotal,
                'transaction_type' => $orderRepo->getTransactionTypeHtmlByOrderProduct($orderAssociate->orderProduct)
            ];
        }
        return ['total' => [], 'list' => $list];
    }

    /**
     * 获取销售单bp的时间
     * @param array $salesOrderIds
     * @return array [$salesOrderId => 'Y-m-d H:i:s']
     */
    public function getBeingProcessedTimes(array $salesOrderIds): array
    {
        return OrderAssociated::query()->alias('a')
            ->leftJoinRelations(['customerSalesOrder as b'])
            ->whereIn('b.order_status', CustomerSalesOrderStatus::inAndAfterBeingProcessed())
            ->whereIn('a.sales_order_id', $salesOrderIds)
            ->select(['a.sales_order_id', 'a.CreateTime'])
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item['sales_order_id'] => $item['CreateTime']];
            })
            ->toArray();
    }

    /**
     * 通过header_id获取item_code
     * @param int $headerId
     * @return array
     */
    public function getItemCodesByHeaderId(int $headerId)
    {
        return CustomerSalesOrderLine::query()->where(['header_id' => $headerId])->pluck('item_code')->toArray();
    }

    /**
     * 判断订单是否为LTL
     * @param array $itemCodes
     * @param int $countryId
     * @return bool
     */
    public function isLTL(int $countryId, array $itemCodes)
    {
        if (empty($itemCodes)) {
            return false;
        }
        return Product::queryRead()->alias('p')
            ->leftJoinRelations(['customerPartnerToProduct as cpp', 'tags as t'])
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'cpp.customer_id')
            ->where(['t.tag_id' => 1, 'c.country_id' => $countryId])
            ->whereIn('p.sku', $itemCodes)->exists();
    }

    /**
     * 上门取货的销售订单，如果运输方式是LTL，需要增加仓库校验：
     * 如果导单中的item code是onsite seller的，那么导入的仓库对应的客户号与item code对应的seller客户号需要保持一致。
     * @param int $importMode
     * @param int $key
     * @param string|null $platformSku 销售平台SKU
     * @param string $sku B2B SKU
     * @param string $platformWarehouseName 销售平台仓库名称
     * @param string $carrier 运输公司
     * @param int $countryId 国家ID
     * @return string 错误消息
     */
    public function checkHomePickLTLTypeWarehouse($importMode, $key, $platformSku, $sku, $platformWarehouseName, $carrier, $countryId)
    {
        $item = $platformSku;
        //$warehouse = $warehouseCode;
        $msgError = 'Line' . $key . ',The seller of the item ' . $item . ' is inconsistent with that of the warehouse ' . $platformWarehouseName . '.';
        $msgErrorAmazonWarehouse = 'Line' . ($key) . ',Warehouse Code format error.';
        $msgErrorWayfairWarehouse = 'Line' . ($key) . ',Warehouse Name format error.';
        $msgErrorWalmartWarehouse = 'Line [' . $key . '], [Ship Node] can not be left blank.';
        $msgErrorOtherWarehouse = 'Line' . ($key) . ',[B2B Warehouse Code] can not be left blank.';
        $msgSuccess = '';
        $isCollectionFromDomicile = customer()->isCollectionFromDomicile();
        $LTLTypeItems = [];
        $platformId = 0;
        if ($isCollectionFromDomicile) {
            //上门取货
            switch ($importMode) {
                case HomePickImportMode::IMPORT_MODE_AMAZON:
                    $platformId = 2;
                    $LTLTypeItems = HomePickCarrierType::getAmazonLTLTypeViewItems();
                    break;
                case HomePickImportMode::IMPORT_MODE_WAYFAIR:
                    $platformId = 1;
                    $LTLTypeItems = HomePickCarrierType::getWayfairLTLTypeViewItems();
                    break;
                case HomePickImportMode::IMPORT_MODE_WALMART:
                    $platformId = 3;
                    $LTLTypeItems = HomePickCarrierType::getWalmartLTLTypeViewItems();
                    break;
                case HomePickImportMode::US_OTHER:
                    $platformId = 0;
                    $LTLTypeItems = HomePickCarrierType::getOtherLTLTypeViewItems();
                    break;
            }


            $isLTL = false;
            foreach ($LTLTypeItems as $item) {
                if (stripos(strtoupper($carrier), strtoupper($item)) !== false) {
                    $isLTL = true;
                    break;
                }
            }
            if ($isLTL) {
                //如果是LTL发货，那么就判断仓库必填
                if (!$platformWarehouseName) {
                    switch ($importMode) {
                        case HomePickImportMode::IMPORT_MODE_AMAZON:
                            return $msgErrorAmazonWarehouse;
                            break;
                        case HomePickImportMode::IMPORT_MODE_WAYFAIR:
                            return $msgErrorWayfairWarehouse;
                            break;
                        case HomePickImportMode::IMPORT_MODE_WALMART:
                            return $msgErrorWalmartWarehouse;
                            break;
                        case HomePickImportMode::US_OTHER:
                            return $msgErrorOtherWarehouse;
                            break;
                    }
                }

                //根据SKU 获取归属的Seller
                $builderSeller = Product::query()->alias('p')
                    ->leftJoinRelations(['customerPartnerToProduct as c2p'])
                    ->leftJoin('oc_customer as c', 'c2p.customer_id', '=', 'c.customer_id')
                    ->select(['c.customer_id', 'c.accounting_type'])
                    ->where('p.sku', '=', $sku);
                $sellerInfos = $builderSeller->get();
                $sellerInfosCount = $builderSeller->count();
                if (!$sellerInfosCount) {
                    return $msgError;
                }
                $sellerIdArr = [];
                foreach ($sellerInfos as $sellerInfo) {
                    if ($sellerInfo->accounting_type == CustomerAccountingType::GIGA_ONSIDE) {
                        $sellerIdArr[$sellerInfo->customer_id] = $sellerInfo->customer_id;
                    }
                }

                //如果有OnSite的Seller，那么判断仓库映射关系
                //如果非OnSite的Seller，判断仓库填写是否正确
                $collectionMapping = [];
                $mappingWarehouseIdArr = [];
                switch ($importMode) {
                    case HomePickImportMode::IMPORT_MODE_AMAZON:
                    case HomePickImportMode::IMPORT_MODE_WAYFAIR:
                    case HomePickImportMode::IMPORT_MODE_WALMART:
                        $builderMapping = DB::table('oc_mapping_warehouse')
                            ->select('customer_id', 'customer_id as buyer_id', 'warehouse_id')
                            ->where('customer_id', customer()->getId())
                            ->when($platformId > 0, function ($q) use ($platformId) {
                                $q->where('platform_id', '=', $platformId);
                            })
                            ->where('platform_warehouse_name', '=', $platformWarehouseName)
                            ->where('status', '=', 1);
                        $collectionMapping = $builderMapping->get();
                        if (!$builderMapping->count()) {
                            if ($sellerIdArr) {
                                return $msgError;
                            } else {
                                if ($importMode == HomePickImportMode::IMPORT_MODE_AMAZON) {
                                    return $msgErrorAmazonWarehouse;
                                }
                                if ($importMode == HomePickImportMode::IMPORT_MODE_WAYFAIR) {
                                    return $msgErrorWayfairWarehouse;
                                }
                                if ($importMode == HomePickImportMode::IMPORT_MODE_WALMART) {
                                    return $msgErrorWalmartWarehouse;
                                }
                            }
                        }
                        foreach ($collectionMapping as $mapping) {
                            $mappingWarehouseIdArr[$mapping->warehouse_id] = $mapping->warehouse_id;
                        }
                        break;
                    case HomePickImportMode::US_OTHER:
                        //填写B2B真实仓库，不判断仓库映射关系；如果是LTL，则必填
                        $wid = DB::table('tb_warehouses')->where('WarehouseCode', '=', $platformWarehouseName)->value('WarehouseID');
                        if (!$wid) {
                            return $msgErrorOtherWarehouse;
                        }
                        $mappingWarehouseIdArr[$wid] = $wid;
                        break;
                }

                //如果有OnSite的Seller
                if ($sellerIdArr) {
                    //B2B管理后台是否配置仓库
                    $isOK = false;
                    if ($mappingWarehouseIdArr) {
                        foreach ($sellerIdArr as $sellerId) {
                            $builder = DB::table('tb_warehouses AS w')
                                ->leftJoin('tb_warehouses_to_attribute AS wta', 'w.WarehouseID', '=', 'wta.warehouse_id')
                                ->leftJoin('tb_warehouses_to_seller AS wts', 'wta.warehouse_id', '=', 'wts.warehouse_id')
                                ->select('wta.warehouse_id')
                                ->whereIn('w.WarehouseId', $mappingWarehouseIdArr)
                                ->whereIn('wta.seller_type', [SellerType::ALL, SellerType::GIGA_ON_SITE])
                                ->whereRaw("(wta.seller_assign=1 OR (wta.seller_assign=0 AND wts.seller_id='{$sellerId}'))");
                            if ($builder->count()) {
                                $isOK = true;
                                break;
                            }
                        }
                    }
                    if (!$isOK) {
                        return $msgError;
                    }
                }
            }
        }
        return $msgSuccess;
    }

    /**
     * 自提货上传的明细
     * @param string $runId
     * @param int $customerId
     * @param int $importMode
     * @return CustomerSalesOrderLine[]|Collection
     */
    public function getPickUpUploadInfo(string $runId, int $customerId, int $importMode)
    {
        return CustomerSalesOrderLine::query()
            ->with(['customerSalesOrder' => function ($q) use ($customerId, $importMode) {
                $q->where('buyer_id', $customerId)->where('import_mode', $importMode);
            }, 'customerSalesOrder.pickUp', 'customerSalesOrder.pickUp.warehouse'])
            ->where('run_id', $runId)
            ->get();
    }

    /**
     * 根据销售订单id获取对应信息
     * @param int $id tb_sys_customer_sales_order.id
     * @return CustomerSalesOrder|\Framework\Model\Eloquent\Builder|Builder|Model|object|null
     */
    public function getPickUpOrderInfoByOrderId(int $id)
    {
        return CustomerSalesOrder::query()
            ->with(['lines', 'pickUp', 'pickUp.warehouse'])
            ->where('id', $id)
            ->first();
    }

    /**
     * 获取销售单的所有子 sku 的 qty 数量
     * @param CustomerSalesOrder $salesOrder
     * @return array [$item_code => $qty]
     */
    public function getSalesOrderAllItemsWithSubItems(CustomerSalesOrder $salesOrder): array
    {
        $data = [];
        foreach ($salesOrder->linesNoDelete as $line) {
            if (!$line->combo_info) {
                // 非 combo 的
                if (!isset($data[$line->item_code])) {
                    $data[$line->item_code] = 0;
                }
                $data[$line->item_code] += $line->qty;
                continue;
            }
            // combo
            $combos = Json::decode($line->combo_info);
            foreach ($combos as $combo) {
                $qty = $combo[$line->item_code];
                unset($combo[$line->item_code]); // 去除掉主产品
                foreach ($combo as $itemCode => $subQty) {
                    if (!isset($data[$itemCode])) {
                        $data[$itemCode] = 0;
                    }
                    $data[$itemCode] += $qty * $subQty;
                }
            }
        }

        return $data;
    }

    /**
     * Buyer销售单列表页 Order Status 下拉列表
     * @param bool $isCollectionFromDomicile Buyer是否为上门取货  true上门取货
     * @return array
     */
    public function getListCustomerSalesOrderStatusForBuyer($isCollectionFromDomicile = false)
    {
        $result = [
            CustomerSalesOrderStatus::TO_BE_PAID => CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::TO_BE_PAID),
            CustomerSalesOrderStatus::BEING_PROCESSED => CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::BEING_PROCESSED),
            CustomerSalesOrderStatus::ON_HOLD => CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::ON_HOLD),
            CustomerSalesOrderStatus::CANCELED => CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::CANCELED),
            CustomerSalesOrderStatus::COMPLETED => CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::COMPLETED),
            CustomerSalesOrderStatus::LTL_CHECK => CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::LTL_CHECK),
            CustomerSalesOrderStatus::PENDING_CHARGES => CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::PENDING_CHARGES),
            CustomerSalesOrderStatus::ASR_TO_BE_PAID => CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::ASR_TO_BE_PAID),
        ];
        if ($isCollectionFromDomicile) {
            $result[CustomerSalesOrderStatus::CHECK_LABEL] = CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::CHECK_LABEL);
        }
        return $result;
    }

    /**
     * 通过runId获取主列表
     *
     * @param int $buyerId
     * @param $runId
     * @return CustomerSalesOrder[]|Collection
     */
    public function getSalesOrderListByRunId(int $buyerId, $runId)
    {
        return CustomerSalesOrder::where('buyer_id', $buyerId)
            ->where('run_id', $runId)
            ->get();
    }

    /**
     * @param $salesOrderIds
     * @return CustomerSalesOrderPickUp[]|Collection
     */
    public function getPickUpInfoByOrderIds($salesOrderIds)
    {
        return CustomerSalesOrderPickUp::query()->alias('pu')
            ->with('warehouse')
            ->whereIn('pu.sales_order_id', $salesOrderIds)
            ->get();
    }

    // 根据返回类型来判断
    public function getHomePickLabelInfo(array $containerInfo, $comboExists, $sku)
    {
        // store label  count = 3
        // 商业发票 包含字符 commercial_invoice
        // 其他的label
        $page[0] = $page[1] = 1;
        if (count($containerInfo) == 3) {
            $ship_method_type = HomePickCarrierType::getWalmartAllCutTypeViewItems()[HomePickCarrierType::STORE_LABEL];
            $label_type = HomePickLabelContainerType::STORE_LABEL;
        } else if (in_array('invoice', $containerInfo)) {
            $ship_method_type = HomePickCarrierType::EUROPE_WAYFAIR_COMMERCIAL_INVOICE;
            $label_type = HomePickLabelContainerType::COMMERCIAL_INVOICE;
        } else {
            $label_type = HomePickLabelContainerType::COMMON;
            $ship_method_type = 0;
            if ($comboExists) {
                /** @var \ModelAccountCustomerOrderImport $model */
                $model = load()->model('account/customer_order_import');
                $sku = $model->getSkuByProductId($containerInfo[1]);
                $page[0] = $containerInfo[4];
                $page[1] = $containerInfo[5];
            }
        }


        return [
            $page,
            $ship_method_type,
            $label_type,
            $sku,
        ];
    }

    public function getUpdateInfoByCutResult($import_mode, $ship_method_type, $res, $fileData, $errMsg)
    {
        $json = [];
        $update = [];
        $update['update_time'] = date("Y-m-d H:i:s");
        $update['update_user_name'] = customer()->getId();
        $updateFlag = true;
        if ($res === true) {
            switch ($import_mode) {
                case HomePickImportMode::IMPORT_MODE_WALMART:
                    //部分tracking 是传回的，有的是不传回tracking的
                    if (in_array($ship_method_type, [21, 22, 23, 24]) == true) {
                        if ($res === true) {
                            $fileData['tracking_number'] = '';
                        } else {
                            $fileData['tracking_number'] = $res;
                        }
                        $update['tracking_number'] = $fileData['tracking_number'];
                        $json['error'] = 0;
                        $json['data'] = $fileData;
                        $json['special_fill'] = 0;
                    } elseif (in_array($ship_method_type, [26]) == true) {
                        //102582 Walmart Fedex Homedelivery 4:6格式有两种label 能读出tracking number的就正常返回，读不出的就返回为空
                        $update['tracking_number'] = $fileData['tracking_number'] = '';
                        $json['error'] = 0;
                        $json['data'] = $fileData;
                        $json['special_fill'] = 1;
                    } else {
                        $json['error'] = 1;
                        $json['data'] = $fileData;
                        $json['special_fill'] = 0;
                        $json['msg'] = $errMsg;
                    }
                    break;
                case HomePickImportMode::IMPORT_MODE_WAYFAIR:
                    // 欧洲wayfair中读取的label有错误需要手动 DPD
                    // 商业发票此处不报错，返回数据
                    if (in_array($ship_method_type, [30, 32,HomePickCarrierType::EUROPE_WAYFAIR_COMMERCIAL_INVOICE]) == true) {
                        $json['error'] = 0;
                        $json['data'] = $fileData;
                        $json['special_fill'] = 0;
                        $updateFlag = false;
                    } else {
                        $json['error'] = 1;
                        $json['data'] = $fileData;
                        $json['special_fill'] = 0;
                        $json['msg'] = __('wayfair上传label是否可以识别运单号',[], 'repositories/home_pick');
                        $update = ['status' => YesNoEnum::NO,];
                    }
                    break;
                case HomePickImportMode::IMPORT_MODE_AMAZON:
                    $json['error'] = 0;
                    $json['special_fill'] = 0;
                    $json['data'] = $fileData;
                    break;
                default:
                    $json['error'] = 1;
                    $json['data'] = $fileData;
                    $json['special_fill'] = 0;
                    $json['msg'] = $errMsg;
                    $update = ['status' => YesNoEnum::NO,];
            }

        } else {
            $update['tracking_number'] = $fileData['tracking_number'] = $res;
            $json['error'] = 0;
            $json['special_fill'] = 0;
            $json['data'] = $fileData;

        }

        return [$update, $json, $updateFlag];


    }

}
