<?php

namespace App\Repositories\SalesOrder;

use App\Components\Storage\StorageCloud;
use App\Enums\Product\ProductTransactionType;
use App\Enums\SalesOrder\CustomerSalesOrderLineItemStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\SalesOrder\HomePickImportMode;
use App\Models\Product\Product;
use App\Models\Product\Tag;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use App\Repositories\Product\Channel\ChannelRepository;
use Illuminate\Support\Collection;
use ModelExtensionModulePrice;

/**
 * 上门取货导单匹配处理
 *
 * Class WillCallMatchRepository
 * @package App\Repositories\SalesOrder
 */
class WillCallMatchRepository
{
    public function getCanBuyProductInfoBySku(int $buyerId, int $countryId, array $skuArr)
    {
        // 获取已经建立联系的商品列表
        $conList = $this->getRelationProductList($buyerId, $skuArr);
        $conSkuArr= array_column($conList, 'sku'); // 已经有绑定关系的商品列表（但不代表能够购买，还需进一步判断精细化管理）
        $missSku = array_diff($skuArr, $conSkuArr); // 不能购买或者平台不存的SKU商品
        $existSkuArr = []; // 平台（同一国别）存在，但没有绑定关系的商品
        $nonexistentSkuArr = []; // 平台（同一国别）不存在的商品
        $delicacySkuArr = []; // 精细化不可见
        if ($missSku) {
            $existSkuArr = $this->getSameCountryProductSku($countryId, $missSku);
            $nonexistentSkuArr = array_diff($missSku, $existSkuArr);
        }

        // 已有绑定关系的商品列表中，被设置了精细化不可见部分
        if ($conList) {
            $buyerDelicacyProductIds = app(ChannelRepository::class)->delicacyManagementProductId($buyerId);
            // 从绑定关系的商品列表中，过滤精细化不可见部分
            if ($buyerDelicacyProductIds) {
                foreach ($conList as $key => $item) {
                    if (in_array($item['product_id'], $buyerDelicacyProductIds)) {
                        $delicacySkuArr[] = $item['sku'];
                        unset($conList[$key]);
                    }
                }
            }
        }

        // 最后能够购买部分，取对应的支持的交易方式以及价格
        list($delicacySku, $noCost, $conList) = $this->getTransactionInfo($buyerId, $conList);
        $delicacySkuArr = array_merge($delicacySkuArr, $delicacySku);

        // 最后格式化处理 - 能够购买部分、平台不存在、平台无库存、不可购买
        return $this->formatProductPurchaseList($skuArr, $conList, $nonexistentSkuArr, $existSkuArr, $delicacySkuArr, $noCost);
    }

    /**
     * 格式化能够采购信息
     *
     * @param array $skuArr 目标SKU数组
     * @param array $conList 能够购买的列表
     * @param array $nonexistentSkuArr 平台不存在列表
     * @param array $existSkuArr 平台存在但未绑定列表
     * @param array $delicacySkuArr 精细化不可见列表
     * @param array $noCost 无库存列表
     * @return array
     */
    private function formatProductPurchaseList(array $skuArr, array $conList, array $nonexistentSkuArr, array $existSkuArr, array $delicacySkuArr, array $noCost)
    {
        $lastArr = [];
        $conSku = array_keys($conList);
        $delicacySkuArr = array_diff($delicacySkuArr, $conSku);

        foreach ($skuArr as $sku) {
            $list = [];
            if (in_array($sku, $nonexistentSkuArr)) { // 平台不存在此SKU的商品
                $status = 110;
                $msg = "This product does not exist in the Marketplace and cannot be purchased.";
            } elseif (in_array($sku, $existSkuArr) || in_array($sku, $delicacySkuArr)) { // 平台存在，但是没有绑定关系
                $status = 120;
                $msg = "You do not have permission to purchase this product, please contact Seller.";
            } elseif (in_array($sku, $noCost)) { // 无库存
                $status = 130;
                $msg = 'This product is temporarily out of stock and cannot be purchased.';
            } elseif (in_array($sku, $conSku)) { // 存在对应的可购买信息
                $status = 1;
                $msg = '';
                $list = $conList[$sku];
            } else {
                $status = 140;
                $msg = 'This product is temporarily unavailable for purchase. You may contact the Customer Service.';
            }

            $lastArr[$sku] = [
                'status' => $status,
                'msg' => $msg,
                'list' => $list
            ];
        }

        return $lastArr;
    }

    /**
     * 获取商品的交易方式及价格
     *
     * @param int $buyerId BuyerId
     * @param array $conList 初步能够购买的商品列表
     * @return array
     * @throws \Exception
     */
    private function getTransactionInfo(int $buyerId, array $conList)
    {
        $conProductList = [];
        $delicacySku = []; // 虽有绑定关系，但仍然不可购买部分
        $noCost = []; // 有绑定关系，但没有库存部分
        $type = [
            ProductTransactionType::NORMAL,
            ProductTransactionType::REBATE,
            ProductTransactionType::MARGIN,
            ProductTransactionType::SPOT,
            ProductTransactionType::FUTURE
        ]; // 交易方式 普通交易、返点、现货、议价,期货（旧协议）

        /** @var ModelExtensionModulePrice $priceModel */
        $priceModel = load()->model('extension/module/price');
        foreach ($conList as $item) {
            $transactionInfo = $priceModel->getProductPriceInfo($item['product_id'], $buyerId, $type, false, true, ['filter_future_v3' => true, 'qty' => 0]);
            unset($transactionInfo['first_get']);

            if (empty($transactionInfo['base_info']) || $transactionInfo['base_info']['unavailable'] == 1) {
                $delicacySku[] = $item['sku'];
                continue;
            }

            $item['status'] = 1;
            $item['error_msg'] = '';
            $item['freight'] = $transactionInfo['base_info']['freight'];
            $item['freight_show'] = $transactionInfo['base_info']['freight_show'];
            $item['combo_flag'] = $transactionInfo['base_info']['combo_flag'];
            $item['currency'] = session('currency');
            $transactionList = []; // 交易方式列表(存在库存)

            // 对于复杂交易的再次处理
            if (!empty($transactionInfo['transaction_type'])) {
                foreach ($transactionInfo['transaction_type'] as $kk => $sp) {
                    // 对于议价 过滤议价协议数量 大于 商品上架数量 的协议
                    if ($sp['type'] == ProductTransactionType::SPOT) {
                        if ($sp['qty'] > $sp['left_qty']) {
                            unset($transactionInfo['transaction_type'][$kk]);
                        } else {
                            $transactionInfo['transaction_type'][$kk]['left_qty'] = $sp['qty'];
                        }
                    }
                    // 对于返点 协议有效期内可用的采购数量 为 商品上架的数量
                    if ($sp['type'] == ProductTransactionType::REBATE) {
                        $transactionInfo['transaction_type'][$kk]['left_qty'] = $transactionInfo['base_info']['quantity'];
                    }
                    // 对于期货老版本 展示剩余时间不能大于7天
                    if ($sp['type'] == ProductTransactionType::FUTURE && ! empty($sp['left_time_secs'])) {
                        $sp['left_time_secs'] > 7 * 86400 && $transactionInfo['transaction_type'][$kk]['left_time_secs'] = 604800; // 7天
                    }
                    // 对于现货协议 展示剩余时间不能超过30天
                    if ($sp['type'] == ProductTransactionType::MARGIN && ! empty($sp['left_time_secs'])) {
                        $sp['left_time_secs'] > 30 * 86400 && $transactionInfo['transaction_type'][$kk]['left_time_secs'] = 2592000; // 30天
                    }
                }
            }

            // 格式化处理复杂交易
            if (!empty($transactionInfo['transaction_type'])) {
                $transactionList = $priceModel->transactionListSort($transactionInfo['transaction_type']); // 排序 1.剩余时间 2.价格 3.有限议价

                // 用户二次店铺排序信息
                $item['sort_price'] = $transactionList[0]['price'];
                $item['sort_expire_time'] = $transactionList[0]['expire_time'];
                $item['sort_type'] = $transactionList[0]['type'] == ProductTransactionType::SPOT ? 1 : 0;
            } else {
                // 用户二次店铺排序信息
                $item['sort_price'] = $transactionInfo['base_info']['price'];
                $item['sort_expire_time'] = '9999-99-99';
                $item['sort_type'] = 0;
            }
            // 普通购买方式
            if ($transactionInfo['base_info']['quantity'] > 0) {
                $transactionList[] = [
                    'type' => $transactionInfo['base_info']['type'],
                    'price' => $transactionInfo['base_info']['price'],
                    'price_show' => $transactionInfo['base_info']['price_show'],
                    'product_id' => $transactionInfo['base_info']['product_id'],
                    'quantity' => $transactionInfo['base_info']['quantity'],
                    'left_qty' => $transactionInfo['base_info']['quantity'],
                    'time_limit_price' => $transactionInfo['base_info']['time_limit_price'] ?? 0,
                    'time_limit_qty' => $transactionInfo['base_info']['time_limit_qty'] ?? 0,
                    'time_limit_starting' => $transactionInfo['base_info']['time_limit_starting'] ?? false,
                    'price_all' => $transactionInfo['base_info']['price_all'],
                    'price_all_show' => $transactionInfo['base_info']['price_all_show'],
                    'agreement_name' => 'Normal Transaction'
                ];
            }
            if (! $transactionList) {
                $noCost[] = $item['sku'];
                continue;
            }
            $item['transaction_type'] = $transactionList;

            $conProductList[$item['sku']][] = $item;
        }

        // 店铺排序
        $conProductList = $this->dealStoreSortAndInfo($conProductList);

        $delicacySku = array_unique($delicacySku);
        $noCost = array_unique($noCost);
        $conSku = array_keys($conProductList); // 最后真正能够购买的SKU列表
        $delicacySku = array_diff($delicacySku, $noCost); // 如果一个店铺不能购买，而另一个店铺是无库存，则无库存提示优先级高
        $delicacySku = array_diff($delicacySku, $conSku); // 有绑定，但实际不能购买部分
        $noCost = array_diff($noCost, $conSku); // 有绑定，但实际所有店铺都无库存部分

        return [$delicacySku, $noCost, $conProductList];
    }

    /**
     * 对应同一SKU存在多个店铺可采购进行店铺排序
     *
     * @param array $conProductList 能够采购列表
     * @return array
     */
    private function dealStoreSortAndInfo(array $conProductList)
    {
        if (empty($conProductList)) {
            return $conProductList;
        }

        foreach ($conProductList as $key => $item) {
            // 存在多个店铺，才需要排序
            if (count($item) >= 2) {
                $sortOne = array_column($item, 'sort_expire_time'); // 快到期 第一优先级
                $sortTwo = array_column($item, 'sort_price'); // 价格 第二优先级
                $sortThree = array_column($item, 'sort_type'); // 议价 第三优先级

                array_multisort($sortOne, SORT_ASC, $sortTwo, SORT_ASC, $sortThree, SORT_DESC, $conProductList[$key]);
            }
        }

        return $conProductList;
    }

    /**
     * 通过SKU获取Buyer已经和Seller建立联系的商品(过滤服务店铺)
     *
     * @param int $buyerId BuyerId
     * @param array $skuArray SKU数组
     * @return array
     */
    private function getRelationProductList(int $buyerId, array $skuArray)
    {
        $whereMap = [
            ['op.status', '=', 1],
            ['op.buyer_flag', '=', 1],
            ['bts.buy_status', '=', 1],
            ['bts.buyer_control_status', '=', 1],
            ['bts.seller_control_status', '=', 1],
        ];

        return Product::query()->alias('op')
            ->leftJoinRelations('customerPartnerToProduct as ctp')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('oc_buyer_to_seller as bts', function ($join) use ($buyerId) {
                $join->on('bts.seller_id', '=', 'ctc.customer_id')
                    ->where('bts.buyer_id', $buyerId);
            })
            ->with('tags')
            ->whereNotIn('ctc.customer_id', SERVICE_STORE_ARRAY) // 过滤服务店铺
            ->where($whereMap)
            ->whereIn('op.sku', $skuArray)
            ->select('op.sku', 'op.product_id', 'ctc.screenname', 'ctc.customer_id','op.image')
            ->get()
            ->each(function ($product) {
                /** @var Product $product */
                $tags = [];
                if ($product->tags->isNotEmpty()) {
                    foreach ($product->tags as $tag) {
                        /** @var Tag $tag */
                        $tags[] = $tag->tag_widget;
                    }
                }
                unset($product->tags);
                $product->tags = $tags;
                $product->image = StorageCloud::image()->getUrl($product->image, ['w' => 60, 'h' => 60]);
            })
            ->toArray();
    }

    /**
     * 通过SKU获取当前国别下存在的商品
     *
     * @param int $countryId 国别ID
     * @param array $skuArray SKU数组
     * @return array
     */
    private function getSameCountryProductSku(int $countryId, array $skuArray)
    {
        return Product::query()->alias('op')
            ->leftJoinRelations('customerPartnerToProduct as ctp')
            ->leftJoin('oc_customer as c', 'ctp.customer_id', '=', 'c.customer_id')
            ->whereIn('op.sku', $skuArray)
            ->where('op.status', 1)
            ->where('op.buyer_flag', 1)
            ->where('c.country_id', $countryId)
            ->pluck('op.sku')
            ->toArray();
    }

    /**
     * 获取To be Paid的销售单列表
     *
     * @param int $customerId BuyerId
     * @param array $orderIds 销售订单ID
     * @param int $countryId 国家
     * @return CustomerSalesOrderLine[]|Collection
     */
    public function getNewOrderList(int $customerId, array $orderIds, int $countryId)
    {
        $isEurope = in_array($countryId, EUROPE_COUNTRY_ID) ? 1 : 0;
        return  CustomerSalesOrderLine::query()->alias('l')
            ->leftJoinRelations('customerSalesOrder as o')
            ->select(['o.*', 'l.product_name', 'l.item_code', 'l.qty', 'l.id as line_id'])
            ->where('o.buyer_id', $customerId)
            ->where('o.order_status', CustomerSalesOrderStatus::TO_BE_PAID)
            ->where('l.item_status', CustomerSalesOrderLineItemStatus::PENDING)
            ->whereIn('l.header_id', $orderIds)
            ->when($isEurope, function ($q) {
                $q->leftJoin('tb_sys_customer_sales_order_file as f', 'f.order_id', '=', 'l.header_id')
                    ->where(function ($qq) {
                        $qq->where('o.import_mode', '<>', HomePickImportMode::IMPORT_MODE_WAYFAIR)
                            ->orWhereNull('o.import_mode')
                            ->orWhere(function ($qqq) {
                                $qqq->where('o.import_mode', HomePickImportMode::IMPORT_MODE_WAYFAIR)
                                    ->whereNotNull('f.id');
                            });
                    });
            })
            ->orderBy('l.id', 'asc')
            ->get();
    }

}
