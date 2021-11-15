<?php

use App\Enums\Common\CountryEnum;
use App\Enums\Product\ProductTransactionType;
use App\Enums\FeeOrder\FeeOrderFeeType;
use App\Enums\Product\ProductType;
use App\Exception\AssociatedPreException;
use App\Helper\MoneyHelper;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Link\OrderAssociatedPre;
use App\Models\Product\Product;
use App\Models\Product\ProductFee;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Repositories\FeeOrder\FeeOrderRepository;
use App\Repositories\FeeOrder\StorageFeeRepository;
use App\Repositories\Product\ProductPriceRepository;
use App\Repositories\Margin\MarginRepository;
use App\Repositories\Marketing\MarketingDiscountRepository;
use App\Repositories\Seller\SellerProductRatioRepository;
use App\Repositories\Stock\BuyerStockRepository;
use App\Services\FeeOrder\FeeOrderService;
use App\Services\Stock\BuyerStockService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Collection;
use kriss\bcmath\BCS;

/**
 * Class ModelAccountSalesOrderMatchInventoryWindow
 * Created by IntelliJ IDEA.
 * User: or
 * Date: 2020/6/11
 * Time: 16:00
 */
class ModelAccountSalesOrderMatchInventoryWindow extends Model
{
    public function selectLineInfo(array $lineIdArray)
    {
        $lineInfos = $this->orm->table('tb_sys_customer_sales_order_line as csol')
            ->leftJoin('tb_sys_customer_sales_order as cso', 'cso.id', '=', 'csol.header_id')
            ->whereIn('csol.id', $lineIdArray)
            ->select('csol.id', 'csol.qty', 'csol.item_status', 'csol.item_code', 'cso.order_status', 'cso.order_id','cso.ship_country','cso.ship_zip_code')
            ->get()
            ->keyBy('id')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        return $lineInfos;
    }

    public function getSalesOrderLineInfo(array $headerIdArray)
    {
        $lineInfos = $this->orm->table('tb_sys_customer_sales_order_line as csol')
            ->leftJoin('tb_sys_customer_sales_order as cso', 'cso.id', '=', 'csol.header_id')
            ->whereIn('csol.header_id', $headerIdArray)
            ->select('csol.id', 'csol.qty', 'csol.item_status', 'csol.item_code', 'cso.order_status', 'cso.order_id', 'csol.header_id','cso.is_international','cso.ship_country','cso.ship_zip_code')
            ->get();
        $lineInfos = obj2array($lineInfos);
        $lineCounts = $this->orm->table('tb_sys_customer_sales_order_line')
            ->whereIn('header_id', $headerIdArray)
            ->selectRaw('count(1) as num,id,header_id,GROUP_CONCAT(id) as concatId')
            ->groupBy('header_id')
            ->get();
        $lineCounts = obj2array($lineCounts);
        $data = [];
        foreach ($lineCounts as $lineCount) {
            $data[$lineCount['id']] = $lineCount['num'];
            $data[$lineCount['header_id']] = $lineCount['concatId'];
        }
        foreach ($lineInfos as &$lineInfo) {
            $lineInfo['is_international_flag'] = $lineInfo['is_international'];
            $lineInfo['num'] = isset($data[$lineInfo['id']]) ? $data[$lineInfo['id']] : 0;
            $lineInfo['concatId'] = isset($data[$lineInfo['header_id']]) ? $data[$lineInfo['header_id']] : 0;
        }

        return $lineInfos;
    }

    /**
     * 根据sku数组查询可购买的产品信息
     * @param array $skuArray
     * @param int $buyer_id
     * @return array 可购买的product_id数组
     */
    public function getCanBuyProductInfoBySku(array $skuArray, $buyer_id)
    {
        $whereMap = [
            ['op.status', '=', 1],
            ['op.buyer_flag', '=', 1],
            ['bts.buy_status', '=', 1],
            ['bts.buyer_control_status', '=', 1],
            ['bts.seller_control_status', '=', 1],
        ];
        $result = $this->orm->table('oc_product as op')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'op.product_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('oc_buyer_to_seller as bts', function ($join) use ($buyer_id) {
                $join->on('bts.seller_id', '=', 'ctc.customer_id')
                    ->where('bts.buyer_id', '=', $buyer_id);
            })
            ->where($whereMap)
            ->whereIn('op.sku', $skuArray)
            ->whereNotIn('ctc.customer_id', SERVICE_STORE_ARRAY)//过滤服务店铺
            ->select('op.sku', 'op.product_id', 'ctc.screenname', 'ctc.customer_id','op.image')
            ->get();
        //精细化是否可见
        $productResults = obj2array($result);
        $productArray = [];
        foreach ($productResults as $productInfo) {
            $productArray[] = $productInfo['product_id'];
        }
        $productArray = array_unique($productArray);
        //精细化单条明细过滤
        $result = $this->orm->table('oc_delicacy_management as dm')
            ->where([['dm.buyer_id', '=', $buyer_id], ['dm.product_display', '=', 0]])
            ->whereIn('dm.product_id', $productArray)
            ->selectRaw('DISTINCT(dm.product_id) as product_id')
            ->get();
        $canNotBuyResult = obj2array($result);
        $canNotBuyProduct = [];
        foreach ($canNotBuyResult as $productInfo) {
            $canNotBuyProduct[] = $productInfo['product_id'];
        }
        $productArray = array_diff($productArray, $canNotBuyProduct);
        //精细化分组过滤
        $delicacyMap = [
            ['dmg.status', '=', 1],
            ['pg.status', '=', 1],
            ['pgl.status', '=', 1],
            ['bg.status', '=', 1],
            ['bgl.buyer_id', '=', $buyer_id],
            ['bgl.status', '=', 1],
        ];
        $result = $this->orm->table('oc_delicacy_management_group as dmg')
            ->leftJoin('oc_customerpartner_buyer_group as bg', 'bg.id', '=', 'dmg.buyer_group_id')
            ->leftJoin('oc_customerpartner_product_group as pg', 'pg.id', '=', 'dmg.product_group_id')
            ->leftJoin('oc_customerpartner_buyer_group_link as bgl', 'bgl.buyer_group_id', '=', 'dmg.buyer_group_id')
            ->leftJoin('oc_customerpartner_product_group_link as pgl', 'pgl.product_group_id', '=', 'dmg.product_group_id')
            ->where($delicacyMap)
            ->whereIn('pgl.product_id', $productArray)
            ->select('pgl.product_id')
            ->get();
        $canNotBuyResult = obj2array($result);
        $canNotBuyProduct = [];
        foreach ($canNotBuyResult as $productInfo) {
            $canNotBuyProduct[] = $productInfo['product_id'];
        }
        $result = array_diff($productArray, $canNotBuyProduct);
        $canBuyerProductInfo = [];
        foreach ($productResults as $productInfo) {
            if (in_array($productInfo['product_id'], $result)) {
                $canBuyerProductInfo[] = $productInfo;
            }
        }
        return $canBuyerProductInfo;
    }

    /**
     * 根据sku获取可用库存
     * @param array $skuArray
     * @param int $customer_id
     * @return array
     */
    public function getCostBySkuArray(array $skuArray, $customer_id): array
    {
        // 获取在采购数量
        $purchaseQtyArray = $this->purchaseQty($skuArray, $customer_id);
        //获取RmaQty
        $rmaQtyArray = $this->rmaQty($skuArray, $customer_id);
        //已绑定数量
        $associateQtyArray = $this->associatedQty($skuArray, $customer_id);
        // buyer库存锁定数量
        $lockQtyArray = app(BuyerStockService::class)->getLockQuantityIndexBySkuBySkus($skuArray, (int)$customer_id);
        //合并数组
        $items = [];
        foreach ($purchaseQtyArray as $purchaseQty) {
            $sku = strtoupper($purchaseQty['sku']);
            if (isset($items[$sku]['buyQty'])) {
                $items[$sku]['buyQty'] = $items[$sku]['buyQty'] + $purchaseQty['buyQty'];
            } else {
                $items[$sku]['buyQty'] = $purchaseQty['buyQty'];
            }
        }
        foreach ($rmaQtyArray as $rmaQty) {
            $sku = strtoupper($rmaQty['sku']);
            if (isset($items[$sku]['rmaQty'])) {
                $items[$sku]['rmaQty'] = $items[$sku]['rmaQty'] + $rmaQty['rmaQty'];
            } else {
                $items[$sku]['rmaQty'] = $rmaQty['rmaQty'];
            }
        }
        foreach ($associateQtyArray as $associateQty) {
            $sku = strtoupper($associateQty['sku']);
            if (isset($items[$sku]['assQty'])) {
                $items[$sku]['assQty'] = $items[$sku]['assQty'] + $associateQty['assQty'];
            } else {
                $items[$sku]['assQty'] = $associateQty['assQty'];
            }
        }
        foreach ($lockQtyArray as $sku => $lockQty) {
            $items[$sku]['lockQty'] = $lockQty;
        }

        arsort($items);
        $costArray = [];
        foreach ($items as $sku => $item) {
            $buyerQty = $item['buyQty'] ?? 0;
            $rmaQty = $item['rmaQty'] ?? 0;
            $assQty = $item['assQty'] ?? 0;
            $lockQty = $item['lockQty'] ?? 0;
            $qty = intval($buyerQty - $rmaQty - $assQty - $lockQty);
            $costArray[$sku] = $qty < 0 ? 0 : $qty;
        }
        return $costArray;
    }

    //采购总量
    public function purchaseQty(array $skuArray, $customer_id)
    {
        $result = $this->orm->table('tb_sys_cost_detail as scd')
            ->leftJoin('oc_product as p', 'p.product_id', 'scd.sku_id')
            ->whereNotIn('scd.seller_id', SERVICE_STORE_ARRAY)//过滤服务店铺
            ->whereIn('p.sku', $skuArray)
            ->where('scd.buyer_id', $customer_id)
            ->whereNull('scd.rma_id')
            ->groupBy('p.sku')
            ->selectRaw('ifnull(sum(scd.original_qty),0) as buyQty,p.sku')
            ->get();
//        return array_column(obj2array($result),'qty','sku');
        return obj2array($result);
    }

    //采购退返数量
    public function rmaQty($skuArray, $customer_id)
    {
        $select = 'ifnull(sum(rp.quantity),0) as rmaQty,p.sku';
        $rma = $this->orm->table('oc_yzc_rma_order_product as rp')
            ->leftJoin('oc_yzc_rma_order as r', 'r.id', '=', 'rp.rma_id')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'rp.product_id')
            ->whereNotIn('r.seller_id', SERVICE_STORE_ARRAY)//过滤服务店铺
            ->whereIn('p.sku', $skuArray)
            ->where('r.cancel_rma', '=', 0)
            ->where('r.order_type', '=', 2)
            ->where('rp.status_refund', '<>', 2)
            ->where('r.buyer_id', $customer_id)
            ->groupBy('p.sku')
            ->selectRaw($select)
            ->get();

//        return array_column(obj2array($rma),'rmaQty','sku');
        return obj2array($rma);
    }

    //已关联总量
    public function associatedQty($skuArray, $customer_id)
    {
        $result = $this->orm->table('tb_sys_order_associated as a')
            ->leftJoin('oc_product as p', 'p.product_id', 'a.product_id')
            ->whereNotIn('a.seller_id', SERVICE_STORE_ARRAY)//过滤服务店铺
            ->where('a.buyer_id', $customer_id)
            ->whereIn('p.sku', $skuArray)
            ->groupBy('p.sku')
            ->selectRaw('ifnull(sum(a.qty),0) as assQty,p.sku')
            ->get();

//        return array_column(obj2array($result),'assQty','sku');
        return obj2array($result);
    }

    public function savePurchaseRecord(array $purchaseRecord)
    {
        $this->orm->table('tb_purchase_pay_record')
            ->insert($purchaseRecord);
    }

    /**
     * 查询待支付的记录
     *
     * @param string $run_id
     * @param int $buyer_id
     * @param bool $onlyBuy 是否只需要需要购买的，即quantity > 0的记录，如果需要quantity=0的，该字段传false
     * @param int $salesOrderId 销售单ID 不传为所有
     *
     * @return array
     */
    public function getPurchaseRecord($run_id, $buyer_id, bool $onlyBuy = true, $salesOrderId = 0)
    {
        $result = $this->orm->table('tb_purchase_pay_record as pr')
            ->leftJoin('oc_customerpartner_to_customer as ctc','pr.seller_id','=','ctc.customer_id')
            ->where([
                ['pr.run_id', '=', $run_id],
                ['pr.customer_id', '=', $buyer_id],
            ])
            ->when($onlyBuy, function ($query) {
                $query->where('pr.quantity', '>', 0);
            })
            ->when($salesOrderId, function ($query) use ($salesOrderId) {
                $query->where('pr.order_id', $salesOrderId);
            })
            ->select('pr.order_id', 'pr.line_id', 'pr.item_code', 'pr.product_id', 'pr.type_id', 'pr.agreement_id','pr.sales_order_quantity', 'pr.quantity','pr.seller_id','ctc.screenname')
            ->get();
        return obj2array($result);
    }

    /**
     * 同一批次同一个产品的总的qty
     * @param $runId
     * @param $productId
     * @return mixed
     */
    public function getPurchaseSumQtyByRunId($runId, $productId)
    {
        return $this->orm->table('tb_purchase_pay_record')
            ->where([
                ['run_id', '=', $runId],
                ['product_id', '=', $productId],
            ])
            ->sum('quantity');
    }

    public function getSalesOrderInfo($header_id)
    {
        return CustomerSalesOrder::query()
            ->whereIn('id', $header_id)
            ->select(['id', 'order_id', 'ship_address1', 'ship_address2', 'ship_city', 'ship_state', 'ship_zip_code', 'ship_country', 'ship_phone', 'ship_name'])
            ->get()
            ->toArray();
    }

    /**
     * @param array $data 必传数据如下{type_id,agreement_id,product_id,customer_id,quantity,country_id,seller_id}
     * @return array
     */
    public function getLineTotal($data)
    {
        $type_id = $data['type_id'];
        $agreement_id = $data['agreement_id'];
        $product_id = $data['product_id'];
        $customer_id = $data['customer_id'];
        $quantity = $data['quantity']; // 需要购买的数量
        $customerCountry = $data['country_id'];
        $default_info = $this->orm->table(DB_PREFIX . 'product')
            ->where('product_id', $product_id)
            ->select('price', 'product_type')
            ->first();
        $default_price = $default_info->price;
        $normal_price = $default_price;
        $product_type = $default_info->product_type;
        $dm = $this->cart->getDelicacyManagementInfoByNoView($product_id, $customer_id);
        if (isset($dm) && $dm['product_display'] == 1 && $dm['is_rebate'] != 1) {
            $normal_price = $dm['current_price'];
        }
        $agreement_code = null;
        $useDelicacyPrice = false; // 是否精细化价格
        $useWkProQuotePrice = false; // 是否使用阶梯价
        if ($type_id == 0) {
            //可能是议价的产品价格需要结合数量来看
            //精细化产品价格
            //oc_product价格
            if (isset($dm) && $dm['product_display'] == 1 && $dm['is_rebate'] != 1) {
                $dmPrice = $dm['current_price'];
            }

            $home_pick_up_price = db('oc_wk_pro_quote_details')
                ->where([
                    ['min_quantity', '<=', $quantity],
                    ['max_quantity', '>=', $quantity],
                    ['product_id', '=', $product_id],
                ])
                ->value('home_pick_up_price');
            if ($home_pick_up_price && $home_pick_up_price < $default_price) {
                $price = $home_pick_up_price;
                $useWkProQuotePrice = true;
            } else {
                $price = $default_price;
            }

            //#39948 如果存在精细化价格，判断免税后的精细化和免税加打折后的阶梯价，哪个便宜用哪个
            if (isset($dmPrice)) {
                if ($home_pick_up_price) {
                    [$tmpHomePickPrice, ,] = app(ProductPriceRepository::class)->getProductTaxExemptionPrice(intval($data['seller_id']), $home_pick_up_price, 0);
                    $tmpHomePickPrice = app(MarketingDiscountRepository::class)->getPriceAfterDiscount($customer_id, $product_id, $tmpHomePickPrice, $quantity, ($useWkProQuotePrice ? ProductTransactionType::SPOT : ProductTransactionType::NORMAL));
                    [$tmpDmPrice, ,] = app(ProductPriceRepository::class)->getProductTaxExemptionPrice(intval($data['seller_id']), $dmPrice, 0);
                    if ($tmpDmPrice > $tmpHomePickPrice) {
                        $price = $home_pick_up_price;
                        $useWkProQuotePrice = true;
                    } else {
                        $price = $dmPrice;
                        $useDelicacyPrice = true;
                        $useWkProQuotePrice = false;
                    }
                } else {
                    $price = $dmPrice;
                    $useDelicacyPrice = true;
                }

            }

        } else {
            if ($product_type != 0 && $product_type != 3) {
                $price = $default_price;
            } else {
                $transaction_info = $this->cart->getTransactionTypeInfo($type_id, $agreement_id, $product_id);
                if ($transaction_info) {
                    $price = $transaction_info['price'];
                } else {
                    $price = $default_price;
                }
                $agreement_code = $transaction_info['agreement_code'];
                if (isset($transaction_info['is_valid']) && $transaction_info['is_valid'] == 0) {
                    if (isset($dm) && $dm['product_display'] == 1) {
                        $price = $dm['current_price'];
                    } else {
                        $price = $default_price;
                    }
                }
            }
        }

        $precision = $customerCountry == CountryEnum::JAPAN ? 0 : 2;

        // $price 为我们需要的价格
        //欧洲国别拆分服务费
        $product_price_per = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice($data['seller_id'], $price, $customerCountry);
        $service_fee_per = $price - $product_price_per;

        // 议价需要根据折扣来算服务费
        if ($type_id == ProductTransactionType::SPOT && $this->customer->isEurope()) {
            $product_price_per = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice($data['seller_id'], $normal_price, $customerCountry);
            $service_fee_per = $normal_price - $product_price_per;

            $amount_per = bcsub($normal_price, $price, $precision);
            $amount_price_per = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice($data['seller_id'], $amount_per, $customerCountry);
            $amount_service_fee_per = bcsub($amount_per, $amount_price_per, $precision);

            $service_fee_per = $service_fee_per - $amount_service_fee_per;//欧洲议价后的货值拆分的服务费
            $product_price_per = $price - $service_fee_per;
        }

        // #31737 下单针对于非复杂交易且普通产品的价格需要判断是否需免税
        if (empty($type_id) && $product_type == ProductType::NORMAL) {
            [$price, $product_price_per, $service_fee_per] = app(ProductPriceRepository::class)
                ->getProductTaxExemptionPrice(intval($data['seller_id']), $price, $service_fee_per);
        }

        // #22763 大客户-折扣 #31737 优化逻辑 （普通购买，非精细化）
        if ($type_id == 0 && !$useDelicacyPrice) {
            // 获取大客户折扣
            $discountInfo = app(MarketingDiscountRepository::class)->getMaxDiscount($customer_id, $product_id, $quantity, ($useWkProQuotePrice ? ProductTransactionType::SPOT :ProductTransactionType::NORMAL));
            $discount = $discountInfo->discount ?? null;
            $discountRate = $discount ? intval($discount) / 100 : 1;

            $price = MoneyHelper::upperAmount($price * $discountRate, $precision);

            // 重新整理计算服务费和货值
            if (isset($product_price_per) && isset($service_fee_per)) {
                $product_price_per = MoneyHelper::upperAmount($product_price_per * $discountRate, $precision);
                $service_fee_per = $price - $product_price_per;
            }
        }

        // 非欧洲、上门取货的buyer在非精细化价格、非议价时 减去运费, 不论何种类型的buyer，下单时均需记录运费
        $isCollectionFromDomicile = customer()->isCollectionFromDomicile();
        $freightFlag = $isCollectionFromDomicile ? 0 : 1;
        $freightAndPackageResult = $this->getFreightAndPackageFee($product_id, $freightFlag);
        $freight = $freightAndPackageResult['freight'];
        //获取该产品的打包费
        $packageFee = $freightAndPackageResult['package_fee'];

        return [
            'price' => $price,
            'deposit_per' => $transaction_info['deposit_per'] ?? 0,
            'freight' => $freight,
            'package_fee' => $packageFee,
            'product_price_per' => $product_price_per,
            'service_fee_per' => $service_fee_per,
            'agreement_code' => $agreement_code,
            'type_id' => $type_id
        ];
    }


    /**
     * 获取所需运费,打包费
     * @param Product|int $product
     * @param int $flag
     * @return array|int[]
     */
    public function getFreightAndPackageFee($product, $flag = 0)
    {
        if ($product) {
            if (!($product instanceof Product)) {
                $product = Product::query()->with(['fee'])->find($product);
            }
            // flag = true 或者非欧洲上门取货账号
            if ($flag) {
                /** @var ProductFee $fee */
                $fee = $product->fee->where('type', 1)->first();
                if ($fee) {
                    return [
                        'freight' => $product->freight,
                        'package_fee' => (float)$fee->fee,
                        'volume' => 0
                    ];
                }
            } elseif (customer()->isCollectionFromDomicile()) {
                // 上门取货
                $fee = $product->fee->where('type', 2)->first();
                if ($fee) {
                    return [
                        'freight' => 0,// 上门取货没有运费
                        'package_fee' => (float)$fee->fee,
                        'volume' => 0
                    ];
                }
            }
        }
        return [
            'freight' => 0,
            'package_fee' => 0,
            'volume' => 0
        ];
    }

    public function associateOrder($order_id,$run_id,$buyer_id){

        //订单购买的产品
        $orderProductInfos = $this->orm->table('oc_order_product')
            ->where('order_id','=',$order_id)
            ->select('order_product_id','order_id','product_id','quantity','type_id','agreement_id')
            ->get();
        $orderProductInfos = obj2array($orderProductInfos);

        //下单页需要购买的库存
        $result = $this->orm->table('tb_purchase_pay_record as pr')
            ->where([
                ['pr.run_id', '=', $run_id],
                ['pr.customer_id', '=', $buyer_id],
                ['pr.quantity','>',0]
            ])
            ->select('pr.order_id', 'pr.line_id', 'pr.item_code', 'pr.product_id', 'pr.type_id', 'pr.agreement_id', 'pr.quantity','pr.seller_id','pr.customer_id')
            ->get();
        $purchaseRecords = obj2array($result);

        //校验需要购买的数量和实际购买数量
        $needBuyerQty = [];
        foreach ($purchaseRecords as $purchaseRecord){
            $product_id = $purchaseRecord['product_id'];
            if(isset($needBuyerQty[$product_id])){
                $needBuyerQty[$product_id]['qty'] = $needBuyerQty[$product_id]['qty']+$purchaseRecord['quantity'];
            }else{
                $needBuyerQty[$product_id]['qty'] = $purchaseRecord['quantity'];
                $needBuyerQty[$product_id]['sku'] = $purchaseRecord['item_code'];
            }
        }
        foreach ($orderProductInfos as $orderProductInfo){
            $product_id = $orderProductInfo['product_id'];
            $buyerQty = $orderProductInfo['quantity'];
            $needQty = $needBuyerQty[$product_id]['qty'];
            $item_code = $needBuyerQty[$product_id]['sku'];
            if($buyerQty <> $needQty){
                $msg = $item_code . ' need buy quantity not equal buyer quantity';
                throw new Exception($msg);
            }
        }

        //先将本次的订单和需要购买的订单进行绑定
        //需要绑定的数组
        $associateArr = [];
        foreach ($purchaseRecords as $purchaseRecord) {
            //需要购买的产品id
            $product_id = $purchaseRecord['product_id'];
            //需要购买的数量
            $quantity = $purchaseRecord['quantity'];
            //使用的协议
            $agreement_id = $purchaseRecord['agreement_id'];
            //交易类型
            $type_id = $purchaseRecord['type_id'];
            foreach ($orderProductInfos as $orderProductInfo) {
                if ($orderProductInfo['product_id'] == $product_id) {
                    if ($orderProductInfo['type_id'] == $type_id && $orderProductInfo['agreement_id'] == $agreement_id) {
                        $associateArr[] = array(
                            'sales_order_id' => $purchaseRecord['order_id'],
                            'sales_order_line_id' => $purchaseRecord['line_id'],
                            'order_id' => $orderProductInfo['order_id'],
                            'order_product_id' => $orderProductInfo['order_product_id'],
                            'qty' => $quantity,
                            'product_id' =>$product_id,
                            'seller_id' =>$purchaseRecord['seller_id'],
                            'buyer_id' => $purchaseRecord['customer_id'],
                            'run_id' =>$run_id,
                            'status' =>0,
                            'memo' =>'purchase record add',
                            'associate_type' => 2,
                            'CreateUserName' => 'admin',
                            'CreateTime' =>date('Y-m-d H:i:s'),
                            'ProgramCode' => 'V1.0'
                        );
                    } else {
                        $msg = $purchaseRecord['item_code'] . ' Transaction_type is error';
                        throw new Exception($msg);
                    }
                }
            }
        }

        //region 使用囤货库存逻辑已经挪到前面
        //endregion


        //下单页预绑定
        $this->associateAdvance($associateArr);
    }

    /**
     * 校验囤货库存
     *
     * @param $runId
     * @param int $customerId
     * @return bool
     * @throws Exception 如果库存不足，会抛出异常
     */
    public function checkStockpileStockBuPurchaseList($runId, $customerId)
    {
        //查询使用库存的数据
        $useStockRecords = $this->getUseInventoryRecord($runId, $customerId);
        //使用的sku的数组
        $stockSkuArr = [];
        $stockSkuAndQty = [];
        foreach ($useStockRecords as $useStockRecord) {
            $item_code = $useStockRecord['item_code'];
            $qty = $useStockRecord['useStockQty'];
            $stockSkuArr[] = $item_code;
            if (isset($stockSkuAndQty[$item_code])) {
                $stockSkuAndQty[$item_code] = $stockSkuAndQty[$item_code] + $qty;
            } else {
                $stockSkuAndQty[$item_code] = $qty;
            }
        }

        // 获取囤货产品的锁定数量
        $inventoryLockSkuQtyMap = app(BuyerStockRepository::class)->getInventoryLockSkuQtyMapBySalesOrderPreAssociated(strval($runId), intval($customerId));

        //查看这些sku的现有库存
        $productCostMap = $this->getCostBySkuArray($stockSkuArr, $customerId);
        foreach ($productCostMap as $sku => $productCost) {
            // 这边判断需要排查当前销售单已锁的囤货库存
            $productCost = $productCost + ($inventoryLockSkuQtyMap[$sku] ?? 0);
            if ($stockSkuAndQty[$sku] > $productCost) {
                //只要有库存不足就抛出异常
                $msg = $sku . ' need use ' . $stockSkuAndQty[$sku] . ' available qty,but now available qty is ' . $productCost;
                throw new Exception($msg);
            }
        }
        return true;
    }

    public function getUseInventoryRecord($run_id,$buyer_id){
        $result = $this->orm->table('tb_purchase_pay_record as pr')
            ->where([
                ['pr.run_id', '=', $run_id],
                ['pr.customer_id', '=', $buyer_id]
            ])
            ->whereRaw('pr.sales_order_quantity-pr.quantity>0')
            ->selectRaw( 'pr.order_id,pr.line_id,pr.item_code,pr.seller_id,pr.customer_id,(pr.sales_order_quantity-pr.quantity) as useStockQty')
            ->get();
        return obj2array($result);
    }


    /**
     * @param string $item_code
     * @param int $buyer_id
     * @return array
     */
    public function getPurchaseOrderInfo($item_code, $buyer_id)
    {
        $buyerResults = db('tb_sys_cost_detail as scd')
            ->leftJoin('tb_sys_receive_line as srl', 'scd.source_line_id', '=', 'srl.id')
            ->leftJoin('oc_order_product as oop', [['oop.order_id', '=', 'srl.oc_order_id'], ['oop.product_id', '=', 'scd.sku_id']])
            ->leftJoin('oc_product as op', 'op.product_id', '=', 'scd.sku_id')
            ->select('scd.original_qty', 'scd.sku_id', 'op.sku', 'scd.seller_id', 'oop.order_id', 'oop.order_product_id')
            ->whereNotIn('scd.seller_id', SERVICE_STORE_ARRAY)//过滤服务店铺
            ->where(
                [
                    ['op.sku', '=', $item_code],
                    ['scd.onhand_qty', '>', 0],
                    ['scd.buyer_id', '=', $buyer_id]
                ]
            )
            ->whereNull('scd.rma_id')
            ->get();
        $buyerResults = obj2array($buyerResults);
        $orderProductInfoArr = [];
        foreach ($buyerResults as $buyerResult) {
            $orderProductInfoArr[] = $buyerResult['order_product_id'];
        }

        //已绑定的数量
        $assQtyResults = db('tb_sys_order_associated as soa')
            ->whereIn('soa.order_product_id', $orderProductInfoArr)
            ->selectRaw('sum(soa.qty) as assQty,soa.order_product_id')
            ->groupBy(['soa.order_product_id'])
            ->get();

        $assQtyResults = obj2array($assQtyResults);

        //采购订单的rma
        $rmaQtyResults = db('oc_yzc_rma_order as yro')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'yro.id')
            ->whereIn('rop.order_product_id', $orderProductInfoArr)
            ->where(
                [
                    ['yro.order_type', '=', 2],
                    ['yro.buyer_id', '=', $buyer_id],
                    ['yro.cancel_rma', '=', 0],
                    ['rop.status_refund', '<>', 2]
                ]
            )
            ->selectRaw('rop.product_id,rop.order_product_id,sum(rop.quantity) as rmaQty')
            ->groupBy(['rop.order_product_id'])
            ->get();

        $rmaQtyResults = obj2array($rmaQtyResults);
        // 锁定数量
        $lockQtyArr = app(BuyerStockService::class)->getLockQuantityIndexByOrderProductIdByOrderProductIds($orderProductInfoArr);

        //合并数组
        $items = [];
        foreach ($buyerResults as $purchaseQty) {
            $orderProductId = strtoupper($purchaseQty['order_product_id']);
            if (isset($items[$orderProductId]['buyQty'])) {
                $items[$orderProductId]['buyQty'] = $items[$orderProductId]['buyQty'] + $purchaseQty['original_qty'];
            } else {
                $items[$orderProductId]['buyQty'] = $purchaseQty['original_qty'];
                $items[$orderProductId]['seller_id'] = $purchaseQty['seller_id'];
                $items[$orderProductId]['order_id'] = $purchaseQty['order_id'];
                $items[$orderProductId]['sku_id'] = $purchaseQty['sku_id'];
                $items[$orderProductId]['order_product_id'] = $purchaseQty['order_product_id'];
            }
        }
        foreach ($rmaQtyResults as $rmaQty) {
            $orderProductId = strtoupper($rmaQty['order_product_id']);
            if (isset($items[$orderProductId]['rmaQty'])) {
                $items[$orderProductId]['rmaQty'] = $items[$orderProductId]['rmaQty'] + $rmaQty['rmaQty'];
            } else {
                $items[$orderProductId]['rmaQty'] = $rmaQty['rmaQty'];
            }
        }
        foreach ($assQtyResults as $associateQty) {
            $orderProductId = strtoupper($associateQty['order_product_id']);
            if (isset($items[$orderProductId]['assQty'])) {
                $items[$orderProductId]['assQty'] = $items[$orderProductId]['assQty'] + $associateQty['assQty'];
            } else {
                $items[$orderProductId]['assQty'] = $associateQty['assQty'];
            }
        }
        foreach ($lockQtyArr as $orderProductIdTemp => $lockQty) {
            $items[$orderProductIdTemp]['lockQty'] = $lockQty;
        }

        $costArray = [];
        foreach ($items as $orderProductId => $item) {
            $buyerQty = $item['buyQty'] ?? 0;
            $rmaQty = $item['rmaQty'] ?? 0;
            $assQty = $item['assQty'] ?? 0;
            $lockQty = $item['lockQty'] ?? 0;
            $leftQty = $buyerQty - $rmaQty - $assQty -$lockQty;
            if ($leftQty > 0) {
                $costArray[$orderProductId]['left_qty'] = $leftQty;
                $costArray[$orderProductId]['order_id'] = $item['order_id'];
                $costArray[$orderProductId]['order_product_id'] = $item['order_product_id'];
                $costArray[$orderProductId]['product_id'] = $item['sku_id'];
                $costArray[$orderProductId]['seller_id'] = $item['seller_id'];
            }
        }

        return $costArray;
    }

    /**
     * 创建仓租费用单
     *
     * @param $runId
     * @param int $customerId
     * @param float|int $totalStorageFee 总费用，用于校验仓租是否发生变化
     * @param string $feeOrderRunId 费用单标识
     * @return array [salesOrderId => feeOrderId]
     * @throws AssociatedPreException
     */
    public function createStorageFeeOrderByPurchaseList($runId, $customerId, $totalStorageFee,string $feeOrderRunId)
    {
        $feeOrderRepo = app(FeeOrderRepository::class);
        //查询run id是否有费用单，如果有，直接返回
        $feeOrderList = $feeOrderRepo->getCanPayFeeOrderByRunId($runId, $customerId, FeeOrderFeeType::STORAGE);
        if (!empty($feeOrderList)) {
            // 修改fee_order_run_id
            FeeOrder::whereIn('id', $feeOrderList)
                ->update(['fee_order_run_id' => $feeOrderRunId]);
            return $feeOrderList;
        }
        $salesStorageFeeOrderList = [];
        $useStockRecords = $this->getUseInventoryRecord($runId,$customerId);

        $storageFeeRepo = app(StorageFeeRepository::class);

        $associatedPreIds = [];
        $costInfoList = [];//暂存库存明细,避免多笔销售订单使用同一个sku导致分配一样的情况
        $salesOrderLineIdList = [];//暂存sales order 内order_product_id对应的line id
        //拼装获取仓租信息数据
        foreach ($useStockRecords as $useStockRecord){
            //预绑的囤货库存
            $associatedPreList = $this->getSalesOrderAssociatedPre($useStockRecord['order_id'], $useStockRecord['line_id'], $runId, 1);
            if(!empty($associatedPreList)){
                //region 判断预绑信息是否发生变化
                $useStockQty = $useStockRecord['useStockQty'];
                if (!empty($costInfoList[$useStockRecord['item_code']])) {
                    $costInfos = $costInfoList[$useStockRecord['item_code']];
                } else {
                    $costInfos = $this->getPurchaseOrderInfo($useStockRecord['item_code'], $customerId);
                }
                $nowAssociateArr = [];
                foreach ($costInfos as &$costInfo){
                    //该采购订单明细剩余可用库存
                    $leftQty = $costInfo['left_qty'];
                    if ($leftQty > 0) {
                        $associateStr = "{$costInfo['order_id']}-{$costInfo['order_product_id']}";
                        if ($leftQty >= $useStockQty) {
                            $associateStr .= "-{$useStockQty}";
                            $nowAssociateArr[] = $associateStr;
                            $costInfo['left_qty'] = $leftQty - $useStockQty;
                            break;
                        } else {
                            //该采购订单明细不够使用
                            $associateStr .= "-{$leftQty}";
                            //修改还需使用的数量
                            $useStockQty = $useStockQty - $leftQty;
                            $nowAssociateArr[] = $associateStr;
                            $costInfo['left_qty'] = 0;
                        }
                    }
                }
                //暂存库存明细
                $costInfoList[$useStockRecord['item_code']] = $costInfos;
                if (empty($nowAssociateArr) || $associatedPreList->count() != count($nowAssociateArr)) {
                    throw new AssociatedPreException();
                }
                foreach ($associatedPreList as $associatedItem) {
                    if (!in_array("{$associatedItem->order_id}-{$associatedItem->order_product_id}-{$associatedItem->qty}", $nowAssociateArr)) {
                        //如果预绑定的库存方案和当前不一致，抛出异常
                        throw new AssociatedPreException();
                    }
                }
                //endregion
                //暂存sales order 内order_product_id对应的line id
                foreach ($associatedPreList as $associatedPre) {
                    $associatedPreIds[] = $associatedPre->id;
                    $salesOrderLineIdList[$useStockRecord['order_id']][$associatedPre->order_product_id] = $useStockRecord['line_id'];
                }
            }
        }
        //判断金额是否发生变化
        $purchaseRecords = $this->getPurchaseRecord($runId, $customerId, false);
        $nowTotalStorageFee = $storageFeeRepo->getAllCanBindNeedPay($associatedPreIds, $purchaseRecords);
        if (BCS::create($nowTotalStorageFee)->compare($totalStorageFee) != 0) {
            throw new Exception('Information shown on this screen has been updated.  Please refresh this page.');
        }
        //获取仓租信息
        $orderStorageFeeInfos = $storageFeeRepo->getAllCanBind($associatedPreIds, $purchaseRecords);
        foreach ($purchaseRecords as $purchaseRecord) {
            if ($purchaseRecord['type_id'] == ProductTransactionType::MARGIN) {
                $orderProduct = app(MarginRepository::class)->getAdvanceOrderProductByAgreementId($purchaseRecord['agreement_id']);
                if ($orderProduct) {
                    $salesOrderLineIdList[$purchaseRecord['order_id']][$orderProduct->order_product_id] = $purchaseRecord['line_id'];
                }
            }
        }
        //拼装创建仓租单信息
        foreach ($orderStorageFeeInfos as $salesOrderId => $orderStorageFeeInfo) {
            foreach ($orderStorageFeeInfo as $orderProductId => $salesOrderProduct) {
                if (empty($salesOrderProduct) || empty($salesOrderProduct['storage_fee_ids'])) {
                    // 如果没数据跳过
                    continue;
                }
                foreach ($salesOrderProduct['storage_fee_ids'] as $storage_fee_id) {
                    if (empty($salesOrderLineIdList[$salesOrderId][$orderProductId])) {
                        throw new Exception('Information shown on this screen has been updated.  Please refresh this page.');
                    }
                    $salesStorageFeeOrderList[$salesOrderId][$storage_fee_id] = $salesOrderLineIdList[$salesOrderId][$orderProductId];
                }
            }
        }
        //创建囤货库存仓租订单
        $feeOrderIdList = [];
        if (!empty($salesStorageFeeOrderList)) {
            $feeOrderIdList = app(FeeOrderService::class)->createSalesFeeOrder($salesStorageFeeOrderList, $feeOrderRunId, $runId);
        }
        return $feeOrderIdList;
    }

    /**
     * 获取销售订单预绑定信息
     *
     * @param int $salesOrderId 销售订单号
     * @param int $salesOrderLineId 销售订单line
     * @param int $runId
     * @param int $associateType 预绑定类型 1-囤货 2-新采购
     * @param int $productId 商品id
     * @param int $status 状态 1-预绑定 2-已绑定
     * @return \Illuminate\Support\Collection|OrderAssociatedPre[]
     */
    public function getSalesOrderAssociatedPre($salesOrderId, $salesOrderLineId, $runId = 0, $associateType = 0, $productId = 0,$status = 0)
    {
        return OrderAssociatedPre::query()
            ->with(['orderProduct'])
            ->where('sales_order_id', $salesOrderId)
            ->where('sales_order_line_id', $salesOrderLineId)
            ->when($runId, function ($query) use ($runId) {
                $query->where('run_id', $runId);
            })
            ->when($associateType, function ($query) use ($associateType) {
                $query->where('associate_type', $associateType);
            })
            ->when($productId, function ($query) use ($productId) {
                $query->where('product_id', $productId);
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->get();
    }

    public function associateAdvance($associateArr){
        $this->orm->table('tb_sys_order_associated_pre')
            ->insert($associateArr);
    }

    public function associatedOrderByRecord($associatedArr){
        $this->orm->table('tb_sys_order_associated')
            ->insert($associatedArr);
    }

    public function associatedOrderByRecordGetId($associated){
        return $this->orm->table('tb_sys_order_associated')
            ->insertGetId($associated);
    }

    public function getFreightSku()
    {
        $sellerProductId = json_decode($this->config->get('europe_freight_product_id'),true);
        $skuInfos = $this->orm->table('oc_customerpartner_to_product as ctp')
            ->leftJoin('oc_product as op','op.product_id','=','ctp.product_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc','ctc.customer_id','=','ctp.customer_id')
//            ->whereIn('ctc.customer_id',$sellerIdArr)
            ->whereIn('ctp.product_id',$sellerProductId)
            ->select('ctp.customer_id','ctp.product_id','op.sku','ctc.screenname','op.image')
            ->get()
            ->keyby('customer_id')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        return $skuInfos;
    }

    public function getSalesOrderInfoByLineId($lineId)
    {
       $result = $this->orm->table('tb_sys_customer_sales_order_line as csol')
            ->leftJoin('tb_sys_customer_sales_order as cso','csol.header_id','=','cso.id')
            ->select('cso.ship_country','cso.ship_zip_code','csol.qty','cso.order_id')
            ->where('csol.id','=',$lineId)
            ->first();
       return obj2array($result);
    }

    public function getFreightSkuBySellertId($sellerId)
    {
        $sellerProductId = json_decode($this->config->get('europe_freight_product_id'),true);
        $result= $this->orm->table('oc_product as op')
            ->leftJoin('oc_customerpartner_to_product as ctp','ctp.product_id','=','op.product_id')
            ->select('ctp.customer_id','ctp.product_id','op.sku','op.image')
            ->whereIn('ctp.product_id',$sellerProductId)
            ->where('ctp.customer_id','=',$sellerId)
            ->first();
        return obj2array($result);
    }


    /**
     * @param array $skuArray
     * @param int $customerId
     * @return array
     */
    public function getCostDetailsBySkuArray($skuArray, $customerId)
    {
        $buyerResults = db('tb_sys_cost_detail as scd')
            ->leftJoin('oc_product as op', 'op.product_id', '=', 'scd.sku_id')
            ->leftJoin('tb_sys_receive_line as srl', 'srl.id', '=', 'scd.source_line_id')
            ->leftJoin('oc_order_product as oop', [['oop.order_id', '=', 'srl.oc_order_id'], ['oop.product_id', '=', 'scd.sku_id']])
            ->select('scd.original_qty', 'op.sku', 'scd.sku_id', 'op.sku', 'scd.seller_id', 'oop.order_id', 'oop.order_product_id')
            ->whereIn('op.sku', $skuArray)
            ->where([['scd.buyer_id', '=', $customerId], ['scd.onhand_qty', '>', '0']])
            ->whereNull('scd.rma_id')
            ->get()
            ->keyBy('order_product_id')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        $orderProductInfoArr = array_column($buyerResults, 'order_product_id');

        //已绑定的数量
        $assQtyResults = db('tb_sys_order_associated as soa')
            ->whereIn('soa.order_product_id', $orderProductInfoArr)
            ->selectRaw('sum(soa.qty) as assQty,soa.order_product_id')
            ->groupBy('soa.order_product_id')
            ->get()
            ->keyBy('order_product_id')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();

        //采购订单的rma
        $rmaQtyResults = db('oc_yzc_rma_order as yro')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'yro.id')
            ->whereIn('rop.order_product_id', $orderProductInfoArr)
            ->where(
                [
                    ['yro.order_type', '=', 2],
                    ['yro.buyer_id', '=', $customerId],
                    ['yro.cancel_rma', '=', 0],
                    ['rop.status_refund', '<>', 2]
                ]
            )
            ->selectRaw('rop.product_id,rop.order_product_id,sum(rop.quantity) as rmaQty')
            ->get()
            ->keyBy('order_product_id')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        // buyer锁定库存数量
        $lockQtyArr = app(BuyerStockService::class)->getLockQuantityIndexByOrderProductIdByOrderProductIds($orderProductInfoArr);

        foreach ($buyerResults as $orderProductId => &$buyerResult) {
            $buyerResult = isset($lockQtyArr[$orderProductId])
                ? array_merge($buyerResult, ['lockQty' => $lockQtyArr[$orderProductId]])
                : $buyerResult;
            $buyerResult = isset($assQtyResults[$orderProductId])
                ? array_merge($buyerResult, $assQtyResults[$orderProductId])
                : $buyerResult;
            $buyerResult = isset($rmaQtyResults[$orderProductId])
                ? array_merge($buyerResult, $rmaQtyResults[$orderProductId])
                : $buyerResult;
            $buyerResult['left_qty'] = $buyerResult['original_qty']
                - ($buyerResult['assQty'] ?? 0)
                - ($buyerResult['rmaQty'] ?? 0)
                - ($buyerResult['lockQty'] ?? 0);
        }

        return $buyerResults;
    }
}
