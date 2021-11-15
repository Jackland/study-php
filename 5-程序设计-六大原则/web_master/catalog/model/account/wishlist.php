<?php

use App\Enums\Common\YesNoEnum;
use App\Enums\Product\ProductType;
use App\Enums\Order\OcOrderStatus;
use App\Enums\Warehouse\ReceiptOrderStatus;
use App\Helper\MoneyHelper;
use App\Models\Customer\Customer;
use App\Models\Message\Msg;
use App\Models\Message\MsgContent;
use App\Models\Message\MsgReceive;
use App\Models\Product\ProductFee;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\Marketing\CampaignRepository;
use App\Repositories\Marketing\MarketingDiscountRepository;
use App\Repositories\Product\ProductPriceRepository;
use App\Repositories\Seller\SellerProductRatioRepository;
use App\Services\Marketing\CampaignService;
use Catalog\model\filter\WishListFilter;
use Illuminate\Database\Query\JoinClause;

/**
 * Class ModelAccountWishlist
 *
 * @property ModelAccountCustomerpartnerOrder $model_account_customerpartnerorder
 * @property ModelAccountWishlist $model_account_wishlist
 * @property ModelToolImage $model_tool_image
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelExtensionModuleProductShow $model_extension_module_product_show
 * @property ModelMarketingCampaignActivity $model_marketing_campaign_activity
 * @property ModelMessageMessage $model_message_message
 */
class ModelAccountWishlist extends Model
{
    use WishListFilter;

    /**
     * [addWishlist description] 新增了运费 价格等字段
     * @param int $product_id
     * @param $price
     * @param null $extra
     */
    public function addWishlist($product_id, $price, $extra = null)
    {
        $this->db->query("DELETE FROM " . DB_PREFIX . "customer_wishlist WHERE customer_id = '" . (int)$this->customer->getId() . "' AND product_id = '" . (int)$product_id . "'");
        $sql = '';
        if ($extra) {
            foreach ($extra as $key => $value) {
                $sql .= ' ,' . $key . '=' . $value;
            }
        }
        $this->db->query("INSERT INTO " . DB_PREFIX . "customer_wishlist SET customer_id = '" . (int)$this->customer->getId() . "', product_id = '" . (int)$product_id . "', date_added = NOW(),price =" . $price . trim($sql, ','));
    }

    public function deleteWishlist($product_id)
    {
        $this->db->query("DELETE FROM " . DB_PREFIX . "customer_wishlist WHERE customer_id = '" . (int)$this->customer->getId() . "' AND product_id = '" . (int)$product_id . "'");
    }

    /**
     * 获取库存订阅产品分类
     * @param int $customer_id
     * @return array
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getCategories($customer_id)
    {
        $cacheKey = [__CLASS__, __FUNCTION__, $customer_id];
        if (cache()->has($cacheKey)) {
            return cache($cacheKey);
        }
        $res = db('oc_customer_wishlist as cw')
            ->select('c.category_id', 'c.parent_id', 'cd.name')
            ->selectRaw('0 as resolve_flag')
            ->leftJoin('oc_product_to_category as pc', 'pc.product_id', '=', 'cw.product_id')
            ->leftJoin('oc_category as c', 'c.category_id', '=', 'pc.category_id')
            ->leftJoin('oc_category_description as cd', 'c.category_id', '=', 'cd.category_id')
            ->where('cd.language_id', (int)$this->config->get('config_language_id'))
            ->where('c.status', 1)
            ->where('c.is_deleted', 0)
            ->where('cw.customer_id', $customer_id)
            ->orderBy('c.sort_order')
            ->get()
            ->keyBy('category_id');
        cache()->set($cacheKey, $this->getTree(obj2array($res)), 600);
        return cache($cacheKey);
    }

    /**
     * 生成分类树
     * @param $data
     * @return array
     */
    function getTree($data)
    {
        $tree = [];
        foreach ($data as $key => $item) {
            if (isset($data[$item['parent_id']])) {
                $data[$item['parent_id']]['children'][] = &$data[$key];
            } else {
                $tree[] = &$data[$key];
            }
        }
        return $tree;
    }


    public function getSellerPriceByProductId($product_id)
    {
        $res = $this->orm->table('oc_seller_price')
            ->select('new_price', 'effect_time')
            ->where('status', 1)
            ->where('product_id', $product_id)
            ->first();
        return obj2array($res);
    }


    /**
     * 获取产品打包费
     * @param int $product_id
     * @param $type_id 1 一件代发，2上门取货
     * @return int
     */
    public function getProductFeeByProductId($product_id, $type_id)
    {
        $package_fee = $this->orm
            ->table('oc_product_fee')
            ->where([
                'product_id' => $product_id,
                'type' => $type_id
            ])
            ->value('fee');
        return $package_fee ?: 0;
    }

    /**
     *
     * 产品的预计入库时间
     * @param int $product_id
     * @return int
     */
    public function getReceiptsOrderDetail($product_id)
    {
        $expected_date = $this->orm
            ->table('tb_sys_receipts_order as ro')
            ->leftJoin('tb_sys_receipts_order_detail as od', 'od.receive_order_id', '=', 'ro.receive_order_id')
            ->where('od.product_id', $product_id)
            ->where('ro.status', ReceiptOrderStatus::TO_BE_RECEIVED)
            ->value('ro.expected_date');
        if (strtotime($expected_date) < time()) {
            return null;
        }
        return $expected_date;
    }

    public function getAvailableWishList($customer_id, $filter = [])
    {
        $query = $this->buildAvailableWishListQuery(...func_get_args());
        // 各个分组数量
        if (isset($filter['groups'])) {
            return obj2array($query->groupBy(['group_id'])->get()->keyBy('group_id'));
        }
        $total = $query->count();
        // 排序
        if (isset($filter['order_by_store'])) {
            $query->orderBy('ctc.screenname', $filter['order_by_store']);
        } elseif (isset($filter['order_by_qty'])) {
            $query->orderBy('op.quantity', $filter['order_by_qty']);
        } else {
            $query->orderBy('cw.date_added', 'DESC');
        }
        //  判断是不是导出excel
        $data = (isset($filter['page_limit']) && !empty($filter['page_limit']))
            ? $query->forPage($filter['page'], $filter['page_limit'])->get()
            : $query->get();
        $fulfillment = $filter['fulfillment'] ?? 1;
        return [
            'total' => $total,
            'list' => $this->handleWistListData(obj2array($data), $fulfillment)
        ];
    }

    /**
     * @param int $customer_id
     * @param array $filter
     * @return Illuminate\Database\Query\Builder
     */
    public function buildAvailableWishListQuery($customer_id, $filter = [])
    {
        $query = $this->orm->connection('read')
            ->table('oc_customer_wishlist as cw');
        // 判断是不是查询各分组数量的
        if (isset($filter['groups'])) {
            $query->selectRaw('count(group_id) as count ,group_id');
        } else {
            $query->select(
                'cw.*', 'op.price as normal_price', 'op.product_type', 'op.combo_flag',
                'op.sku', 'op.quantity', 'op.freight as current_freight', 'op.package_fee', 'op.image',
                'ctp.customer_id as seller_id', 'ctc.screenname', 'dm.current_price as vip_price',
                'dm.price as vip_future_price', 'dm.expiration_time', 'dm.effective_time', 'opd.name', 'oc.customer_group_id'
            );
        }
        $query->leftJoin('oc_product as op', 'op.product_id', '=', 'cw.product_id')
            // 关联获取产品标题
            ->leftJoin('oc_product_description as opd', 'opd.product_id', '=', 'cw.product_id')
            // 关联获取产品所属的seller
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'cw.product_id')
            // 关联获取用户账号是否禁用
            ->leftJoin('oc_customer as oc', 'oc.customer_id', '=', 'ctp.customer_id')
            // 关联获取店铺名称
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            // 精细化管理相关表
            ->leftJoin('oc_delicacy_management as dm', function ($query) use ($customer_id) {
                $query->on('dm.product_id', '=', 'cw.product_id')
                    ->where('dm.expiration_time', '>=', date('Y-m-d H:i:s', time()))
                    ->where('dm.buyer_id', '=', $customer_id);
            })
            // 找出精细化可见或没有参与精细化的商品
            ->whereRaw('(dm.product_display = 1 or dm.product_display is null)')
            // 剔除精细化不可以见的商品
            ->whereRaw('NOT EXISTS (
                        SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                        JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                        JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                        WHERE
                            dmg.seller_id = ctp.customer_id  AND bgl.seller_id = ctp.customer_id AND pgl.seller_id = ctp.customer_id
                            AND bgl.buyer_id = ' . $customer_id . '   AND pgl.product_id = cw.product_id
                            AND dmg.status=1 and bgl.status=1 and pgl.status=1
                    )')
            // 判断订阅的库存是否还和卖家关联
            ->whereRaw('EXISTS (SELECT id from oc_buyer_to_seller as bts where bts.buyer_id=' . $customer_id . ' AND bts.seller_id=ctp.customer_id AND buy_status = 1 AND buyer_control_status = 1 AND seller_control_status = 1 )')
            ->where(['cw.customer_id' => $customer_id, 'op.status' => 1, 'op.buyer_flag' => 1, 'oc.status' => 1]);
        if (isset($filter['groups'])) {
            unset($filter['group_id']);
        }
        // 过滤器
        return $this->filter($query, $filter);
    }

    public function handleWistListData($data, $fulfillment = 1)
    {
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $this->load->model('marketing_campaign/activity');
        $currency = session('currency');
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        //获取allproducts库存订阅预警值
        $allProductsRemind = $this->model_account_wishlist->getAllProductsRemind();
        $now_timestamp = time();
        // 获取活动信息
        $campaignsMap = app(CampaignRepository::class)->getProductsCampaignsMap(array_column($data, 'product_id'));
        // 获取产品是否是囤货产品
        $unsupportStockMap = app(CustomerRepository::class)->getUnsupportStockData(array_column($data, 'product_id'));
        $campaignService = app(CampaignService::class);
        foreach ($data as &$item) {
            // #31737 下单针对于非复杂交易且普通产品的价格需要判断是否需免税
            if ($item['product_type'] == ProductType::NORMAL) {
                if ($item['normal_price']) {
                    $item['normal_price'] = app(ProductPriceRepository::class)
                        ->getProductActualPriceByBuyer(intval($item['seller_id']), intval($item['customer_id']), $item['normal_price']);
                }

                if ($item['vip_price']) {
                    $item['vip_price'] = app(ProductPriceRepository::class)
                        ->getProductActualPriceByBuyer(intval($item['seller_id']), intval($item['customer_id']), $item['vip_price']);
                }

                if ($item['vip_future_price']) {
                    $item['vip_future_price'] = app(ProductPriceRepository::class)
                        ->getProductActualPriceByBuyer(intval($item['seller_id']), intval($item['customer_id']), $item['vip_future_price']);
                }
            }

            //测试店铺、服务店铺、保证金店铺的产品、期货/现货保证金定金类产品在购物车中不显示转移到云送仓购物车的按钮
            if ($item['product_type'] != 0 && $item['product_type'] != 3) {
                $item['can_add_cart'] = 0;
            } elseif (($item['customer_group_id'] == 23 || in_array($item['seller_id'], array(340, 491, 631, 838)))) {
                $item['can_add_cart'] = 0;
            } else {
                $item['can_add_cart'] = 1;
            }
            $item['thumb'] = $this->model_tool_image->resize($item['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_wishlist_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_wishlist_height'));
            $item['popup_image'] = $this->model_tool_image->resize($item['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_popup_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_popup_height'));
            // 获取产品tag
            $item['tags'] = [];
            $tag_array = $this->model_catalog_product->getTag($item['product_id']);
            if (isset($tag_array)) {
                foreach ($tag_array as $tag) {
                    if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                        //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                        $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                        $item['tags'][] = '<img data-toggle="tooltip" class="' . $tag['class_style'] . '" title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                    }
                }
            }
            // 判断产品是否是囤货产品
            $item['unsupport_stock'] = in_array($item['product_id'], $unsupportStockMap);
            // 获取商品参加的活动
            $item['campaign'] = $campaignService->formatPromotionContentForCampaigns($campaignsMap[$item['product_id']]);
            // 库存订阅预警值
            // 获取各个店铺库存订阅预警值
            $sellerStoreRemind = $this->model_account_wishlist->getSellerStoreRemind($item['seller_id']);
            // 获取系统默认库存订阅预警值
            if ($item['remind_qty']) {
            } elseif (isset($sellerStoreRemind['remind_qty'])) {
                $item['remind_qty'] = $sellerStoreRemind['remind_qty'];
            } elseif (isset($allProductsRemind['remind_qty'])) {
                $item['remind_qty'] = $allProductsRemind['remind_qty'];
            } else {
                $item['remind_qty'] = SUBSCRIBE_COST_QTY;
            }
            // 产品的预计入库时间
            $item['expected_date'] = $this->getReceiptsOrderDetail($item['product_id']);
            if (
                $item['vip_future_price']
                && $item['vip_price'] != $item['normal_price']
                && strtotime($item['expiration_time']) > $now_timestamp
            ) {
                $item['vip_effective_status'] = 1;
            }
            // vip_price 精细化价格 ps:如果这个价格有的话 该条记录就是精细化的
            // vip_future_price 精细化未来价格
            // normal_price oc_product 里面价格
            $item['vip_price_change'] = bcsub(round($item['vip_future_price'], 2), $item['vip_price'], 2);
            $item['vip_price_change_show'] = $this->currency->formatCurrencyPrice($item['vip_price_change'], $currency);
            // 产品显示单价
            // 目前是参与的那种价格方式 精细化 还是 24小时价格保护
            $item['is_delicacy'] = 0;
            if ($item['vip_price']) {
                $item['new_price'] = $item['vip_price'];
                $item['is_delicacy'] = 1;
            } else {
                $item['new_price'] = $item['normal_price'];
            }
            // price 加入库存订阅的价格
            // new_price 新价格 上面3个中的一个
            $item['price_show'] = $this->currency->formatCurrencyPrice($item['new_price'], $currency);
            $item['normal_price_show'] = $this->currency->formatCurrencyPrice($item['normal_price'], $currency);
            $item['price_change'] = bcsub(round($item['new_price'], 2), $item['price'], 2);
            $item['price_change_show'] = $this->currency->formatCurrencyPrice(abs($item['price_change']), $currency);
            // 判断seller是否对商品设置了未来的价格
            if ($item['future_price'] = $this->getSellerPriceByProductId($item['product_id'])) {
                // #31737 下单针对于非复杂交易且普通产品的价格需要判断是否需免税
                if ($item['product_type'] == ProductType::NORMAL) {
                    $item['future_price']['new_price'] = app(ProductPriceRepository::class)
                            ->getProductActualPriceByBuyer(intval($item['seller_id']), intval($item['customer_id']),  $item['future_price']['new_price']);
                }
                $item['future_price_show'] = $this->currency->formatCurrencyPrice($item['future_price']['new_price'], $currency);
                $item['future_price_change'] = bcsub(round($item['future_price']['new_price'], 2), $item['new_price'], 2);
                $item['future_price_change_show'] = $this->currency->formatCurrencyPrice($item['future_price_change'], $currency);
            }
            // 上门取货账号显示超大件库存分布列表
            if ($isCollectionFromDomicile) {
                $item['current_freight'] = 0;
                $item['package_fee'] = $this->getProductFeeByProductId($item['product_id'], 2);
                $this->load->model('extension/module/product_show');
                $item['warehouse_list'] = $this->model_extension_module_product_show->getWarehouseDistributionByProductId($item['product_id']);
            } else {
                $item['package_fee'] = $this->getProductFeeByProductId($item['product_id'], 1);
            }
            // 判断一件代发运费还是云送仓运费
            if ($fulfillment == 2) {
                $freightAndPackageFeeInfo = $this->freight->getFreightAndPackageFeeByProducts(array($item['product_id']));
                $item['cwf_freight'] = 0;
                $item['cwf_package_fee'] = 0;
                $item['volume'] = 0;
                $item['volume_inch'] = 0;
                $item['freight_rate'] = 0;
                $item['overweight_surcharge'] = 0;
                $item['weight_total'] = 0;
                $item['weight_list_str'] = '';
                $item['wth_str'] = '';
                //服务店铺运费为0
                if (!in_array($item['seller_id'], SERVICE_STORE_ARRAY)) {
                    $tmpIndex = 1;
                    if (!empty($freightAndPackageFeeInfo[$item['product_id']])) {
                        if ($item['combo_flag'] == 1) {
                            foreach ($freightAndPackageFeeInfo[$item['product_id']] as $comboInfo) {
                                $item['cwf_freight'] += $comboInfo['freight'] * $comboInfo['qty'];
                                $item['cwf_package_fee'] += $comboInfo['package_fee'] * $comboInfo['qty'];
                                $item['volume'] += $comboInfo['volume'] * $comboInfo['qty'];
                                $item['volume_inch'] += $comboInfo['volume_inch'] * $comboInfo['qty'];
                                $item['freight_rate'] = $comboInfo['freight_rate'];   //费率
                                $item['overweight_surcharge'] += ($comboInfo['overweight_surcharge'] ?? 0);
                                $actualWeight = round($comboInfo['actual_weight'], 2);
                                $item['weight_total'] += $actualWeight * $comboInfo['qty'];
                                $item['weight_list_str'] .= sprintf($this->language->get('weight_detail_tip'),
                                    $tmpIndex, $actualWeight, $comboInfo['qty']);
                                $item['wth_str'] .= sprintf($this->language->get('volume_combo_detail_tip'), $tmpIndex,
                                    $comboInfo['length_inch'], $comboInfo['width_inch'],
                                    $comboInfo['height_inch'], $comboInfo['qty']);
                                $tmpIndex++;
                            }
                        } else {

                            $item['cwf_freight'] = $freightAndPackageFeeInfo[$item['product_id']]['freight'];
                            $item['cwf_package_fee'] = $freightAndPackageFeeInfo[$item['product_id']]['package_fee'];
                            $item['volume'] = $freightAndPackageFeeInfo[$item['product_id']]['volume'];
                            $item['volume_inch'] = $freightAndPackageFeeInfo[$item['product_id']]['volume_inch'];
                            $item['freight_rate'] = $freightAndPackageFeeInfo[$item['product_id']]['freight_rate'];
                            $item['overweight_surcharge'] = $freightAndPackageFeeInfo[$item['product_id']]['overweight_surcharge'] ?? 0;
                            $item['weight_total'] = $freightAndPackageFeeInfo[$item['product_id']]['actual_weight'];
                            $item['wth_str'] = sprintf($this->language->get('volume_detail_tip'),
                                $freightAndPackageFeeInfo[$item['product_id']]['length_inch'],
                                $freightAndPackageFeeInfo[$item['product_id']]['width_inch'],
                                $freightAndPackageFeeInfo[$item['product_id']]['height_inch']);
                        }
                    }
                }

                $item['weight_total'] = sprintf('%.2f', $item['weight_total']);
                $item['cwf_freight_show'] = $this->currency->formatCurrencyPrice($item['cwf_freight'], $currency);
                $item['cwf_package_fee_show'] = $this->currency->formatCurrencyPrice($item['cwf_package_fee'], $currency);
                $item['overweight_surcharge_show'] = $this->currency->formatCurrencyPrice($item['overweight_surcharge'], $currency);
                $item['total_freight'] = floatval(bcadd($item['cwf_package_fee'], $item['cwf_freight'], 2)) + $item['overweight_surcharge'];
            } else {
                $item['freight_show'] = $this->currency->formatCurrencyPrice($item['current_freight'], $currency);
                $item['package_fee_show'] = $this->currency->formatCurrencyPrice($item['package_fee'], $currency);
                $item['total_freight'] = bcadd($item['package_fee'], $item['current_freight'], 2);
            }

            $item['freight_change'] = bcsub(round($item['total_freight'], 2), $item['freight'], 2);
            $item['freight_change_show'] = $this->currency->formatCurrencyPrice($item['freight_change'], $currency);
            $item['total_freight_show'] = $this->currency->formatCurrencyPrice($item['total_freight'], $currency);
            $item['total_price'] = bcadd($item['total_freight'], $item['new_price'], 2); //通过下面一行得出
            $item['total_price_show'] = $this->currency->formatCurrencyPrice(bcadd($item['total_freight'], $item['new_price'], 2), $currency);
        }

        return $data;
    }

    public function getUnavailableWishList($customer_id, $filter = [])
    {
        $query = $this->buildUnavailableWishListQuery(...func_get_args());
        // 各个分组数量
        if (isset($filter['groups'])) {
            return obj2array($query->groupBy('group_id')->get()->keyBy('group_id'));
        }
        $total = $query->count();
        // 排序
        if (isset($filter['order_by_store'])) {
            $query->orderBy('ctc.screenname', $filter['order_by_store']);
        }
        if (isset($filter['order_by_qty'])) {
            $query->orderBy('op.quantity', $filter['order_by_qty']);
        }
        $data = obj2array($query->forPage($filter['page'], $filter['page_limit'])->get());
        $this->load->model('tool/image');
        foreach ($data as &$item) {
            $item['thumb'] = $this->model_tool_image->resize($item['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_wishlist_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_wishlist_height'));
            $item['popup_image'] = $this->model_tool_image->resize($item['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_popup_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_popup_height'));
        }
        return [
            'total' => $total,
            'list' => $data,
        ];

    }

    /**
     * @param int $customer_id
     * @param array $filter
     * @return mixed
     */
    public function buildUnavailableWishListQuery($customer_id, $filter = [])
    {
        $query = $this->orm->connection('read')
            ->table('oc_customer_wishlist as cw');
        if (isset($filter['groups'])) {
            $query->selectRaw('count(group_id) as count ,group_id');
        } else {
            $query->select(
                'cw.group_id', 'op.sku', 'op.product_id', 'op.image', 'op.status',
                'op.status', 'op.buyer_flag', 'oc.status as customer_status',
                'ctp.customer_id as seller_id', 'ctc.screenname', 'opd.name'
            );
        }
        $query->leftJoin('oc_product as op', 'op.product_id', '=', 'cw.product_id')
            // 关联获取产品标题
            ->leftJoin('oc_product_description as opd', 'opd.product_id', '=', 'cw.product_id')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'cw.product_id')
            // 关联获取店铺名称
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            // 关联获取用户账号是否禁用
            ->leftJoin('oc_customer as oc', 'oc.customer_id', '=', 'ctp.customer_id')
            // 精细化管理相关表
            ->leftJoin('oc_delicacy_management as dm', function ($query) use ($customer_id) {
                $query->on('dm.product_id', '=', 'cw.product_id')
                    ->where('dm.expiration_time', '>=', date('Y-m-d H:i:s', time()))
                    ->where('dm.buyer_id', '=', $customer_id);
            })
            ->where(function ($query) use ($customer_id) {
                // 找出精细化不可见
                $query->where('dm.product_display', 0)
                    // 找出精细化不可以见的商品
                    ->orWhereRaw('EXISTS (
                        SELECT dmg.id FROM oc_delicacy_management_group AS dmg
                        JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
                        JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
                        WHERE
                            dmg.seller_id = ctp.customer_id  AND bgl.seller_id = ctp.customer_id AND pgl.seller_id = ctp.customer_id
                            AND bgl.buyer_id = ' . $customer_id . '   AND pgl.product_id = cw.product_id
                            AND dmg.status=1 and bgl.status=1 and pgl.status=1
                    )')
                    // 判断订阅的库存是否还和卖家关联
                    ->orWhereRaw('NOT EXISTS (SELECT id from oc_buyer_to_seller as bts where bts.buyer_id=' . $customer_id . ' AND bts.seller_id=ctp.customer_id AND buy_status = 1 AND buyer_control_status = 1 AND seller_control_status = 1 )')
                    // 判断商品是否下架或是否可售卖或用户是否禁用
                    ->orWhereRaw('(op.status=0 or op.buyer_flag=0 or oc.status=0)');

            })
            ->where(['cw.customer_id' => $customer_id]);

        if (isset($filter['groups'])) {
            unset($filter['group_id']);
        }
        // 过滤器
        return $this->filter($query, $filter);
    }

    public function getWishTotal($customer_id)
    {
        return db('oc_customer_wishlist')->where('customer_id', $customer_id)->count();
    }

    public function getWishlist($filter_data, $customFields)
    {
        //14408上门取货账号一键下载超大件库存分布列表
        $ltl_tag = 0;
        $ltl_sql = '';
        if (isset($filter_data['filter_input_ltl']) && $filter_data['filter_input_ltl'] == 1) {
            $ltl_tag = 1;
            $ltl_sql = ' LEFT JOIN oc_product_to_tag otp on op.product_id = otp.product_id ';
        }

        $sql = "SELECT
                cw.*,temp.sellQty,op.combo_flag
            FROM
                oc_customer_wishlist cw
            LEFT JOIN oc_product op ON cw.product_id = op.product_id
            LEFT JOIN oc_product_description opd on opd.product_id=op.product_id
            LEFT JOIN oc_customerpartner_to_product ctp ON ctp.product_id = op.product_id
            LEFT JOIN oc_customerpartner_to_customer ctc on ctc.customer_id=ctp.customer_id
            " . $ltl_sql . "
            LEFT JOIN (SELECT sum(quantity) as sellQty,customer_id from oc_customerpartner_to_order group by customer_id ) temp
            on temp.customer_id=ctc.customer_id
            where cw.customer_id=" . $customFields;
        if (isset($filter_data['filter_input_name']) && trim($filter_data['filter_input_name']) != '') {
            $sql .= " AND ( op.sku LIKE '%" . $this->db->escape(trim($filter_data['filter_input_name'])) . "%'";
            $sql .= " OR opd.name LIKE '%" . $this->db->escape(trim($filter_data['filter_input_name'])) . "%')";
        }
        //14408上门取货账号一键下载超大件库存分布列表
        //oc_tag 中 id 为 1 代表ltl 若改变则改变此致
        if ($ltl_tag) {
            $sql .= " AND otp.tag_id = 1";
        }
        if (isset($filter_data['filter_input_sort']) && trim($filter_data['filter_input_sort']) != '') {
            if ($filter_data['filter_input_sort'] == 1) {
                $sql .= " order by op.product_id ";
            } elseif ($filter_data['filter_input_sort'] == 2) {
                $sql .= " order by ctc.screenname asc ";
            } elseif ($filter_data['filter_input_sort'] == 3) {
                $sql .= " order by ctc.screenname desc ";
            } elseif ($filter_data['filter_input_sort'] == 4) {
                $sql .= " order by temp.sellQty desc ";
            }
            $sql .= " ,op.quantity desc";
        } else {
            $sql .= " order by op.quantity desc ";
        }
        return $this->db->query($sql)->rows;
    }

    public function getTotalWishlist()
    {
        $result = $this->db->query("select customer_id from oc_customerpartner_to_customer where customer_id = " . (int)$this->customer->getId())->row;
        if (isset($result['customer_id'])) {
            $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customer_wishlist cw  WHERE  cw.customer_id = '" . (int)$this->customer->getId() . "'");
        } else {
            $sql = "SELECT
	count(*) as total
FROM
	oc_customer_wishlist cw
	WHERE cw.customer_id =  " . (int)$this->customer->getId();
            $query = $this->db->query($sql);
        }
        return $query->row['total'];
    }

    public function getProduct($product_id, $buyer_id = null)
    {

        $product_status = 1;
        $product_buyer_flag = 1;
        $commission_amount = 0;
        $customer_group_id = (int)$this->config->get('config_customer_group_id');
        $language_id = (int)$this->config->get('config_language_id');
        $store_id = (int)$this->config->get('config_store_id');

        $check_seller_product = $this->db->query("SELECT customer_id FROM oc_customerpartner_to_product WHERE product_id = " . (int)$product_id . " limit 1 ")->row;

        if (($this->config->get('module_marketplace_status') && isset($this->request->get['user_token']) && isset($this->session->data['user_token']) && isset($this->session->data['user_id']) && $this->request->get['user_token'] == session('user_token')) || ($this->config->get('module_marketplace_status') && isset($this->request->get['product_token']) && isset($this->session->data['product_token']) && $this->request->get['product_token'] == session('product_token'))) {
            $product_status_array = $this->db->query("SELECT status,buyer_flag FROM " . DB_PREFIX . "product WHERE product_id = " . (int)$product_id)->row;
            if (isset($product_status_array['status'])) {
                $product_status = $product_status_array['status'];
            }
            if (isset($product_status_array['buyer_flag'])) {
                $product_buyer_flag = $product_status_array['buyer_flag'];
            }
            if (!$product_status) {
                $this->db->query("UPDATE " . DB_PREFIX . "product SET status = '1' WHERE product_id = " . $product_id);
            }
            if (!$product_buyer_flag) {
                $this->db->query("UPDATE " . DB_PREFIX . "product SET buyer_flag = '1' WHERE product_id = " . $product_id);
            }
        }

        $dm_info = null;
        if ($buyer_id) {
            $this->load->model("catalog/product");
            $dm_info = $this->model_catalog_product->getDelicacyManagementInfoByNoView($product_id, $buyer_id, $check_seller_product['customer_id']);
            $sql = " SELECT DISTINCT
                    c2c.screenname,cus.status as c_status,
                    p.product_id AS productId,p.price,p.price_display,p.quantity_display,p.status,p.buyer_flag,p.product_type,
                    p.sku,p.upc,p.ean,p.jan,p.isbn,p.mpn,p.location,p.quantity,p.image,p.manufacturer_id,p.viewed,p.model,p.tax_class_id,p.date_available,
                    p.weight,p.weight_class_id,p.length,p.length_class_id,p.width,p.height,p.points,
                    p.aHref,p.length_class_id,p.subtract,p.minimum,p.date_added,p.date_modified,p.freight,p.package_fee,p.product_type,
                    ifnull( c2c.self_support, 0 ) AS self_support,
                    pd.summary_description,pd.name,pd.description,pd.meta_title,pd.meta_description,pd.meta_keyword,pd.tag,
                    c2p.seller_price,c2p.customer_id,
                    c2p.quantity AS c2pQty,
                    b2s.id as canSell,
                    null as discount,
                    m.NAME AS manufacturer,
                    (
                        SELECT price FROM oc_product_special ps
                        WHERE  ps.product_id = p.product_id AND ps.customer_group_id = $customer_group_id
                        AND ( ( ps.date_start = '0000-00-00' OR ps.date_start < NOW( ) ) AND ( ps.date_end = '0000-00-00' OR ps.date_end > NOW( ) ) )
                            ORDER BY ps.priority ASC, ps.price ASC  LIMIT 1
                    ) AS special,
                    ( SELECT points FROM oc_product_reward pr WHERE pr.product_id = p.product_id AND pr.customer_group_id = $customer_group_id ) AS reward,
                    ( SELECT ss.NAME FROM oc_stock_status ss WHERE ss.stock_status_id = p.stock_status_id AND ss.language_id = $language_id ) AS stock_status,
                    ( SELECT wcd.unit FROM oc_weight_class_description wcd WHERE p.weight_class_id = wcd.weight_class_id AND wcd.language_id = $language_id ) AS weight_class,
                    ( SELECT lcd.unit FROM oc_length_class_description lcd WHERE p.length_class_id = lcd.length_class_id AND lcd.language_id = $language_id ) AS length_class,
                    ( SELECT AVG( rating ) AS total FROM oc_review r1 WHERE r1.product_id = p.product_id AND r1.STATUS = '1'  GROUP BY r1.product_id ) AS rating,
                    ( SELECT COUNT( * ) AS total  FROM oc_review r2  WHERE r2.product_id = p.product_id  AND r2.STATUS = '1'  GROUP BY r2.product_id ) AS reviews,
                    p.sort_order
                FROM
                    oc_product p
                    LEFT JOIN oc_product_description pd ON ( p.product_id = pd.product_id )
                    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
                    LEFT JOIN oc_manufacturer m ON ( p.manufacturer_id = m.manufacturer_id )
                    LEFT JOIN oc_customerpartner_to_product c2p ON ( c2p.product_id = p.product_id )
                    LEFT JOIN oc_customerpartner_to_customer c2c ON ( c2p.customer_id = c2c.customer_id )
                    LEFT JOIN oc_customer cus ON ( c2p.customer_id = cus.customer_id )
                    LEFT JOIN oc_buyer_to_seller AS b2s ON (b2s.seller_id = c2p.customer_id AND b2s.buyer_id = " . $buyer_id . " AND b2s.buy_status = 1 AND b2s.buyer_control_status = 1 AND b2s.seller_control_status = 1 )
                WHERE
                    p.product_id = $product_id
                    AND pd.language_id = $language_id
                    AND p.date_available <= NOW()
                    AND p2s.store_id = $store_id ";
            //AND p.STATUS = '1'
            //AND p.buyer_flag = '1'
            //因为涉及到下架产品，所以需要去掉条件。14103 所有上过架的产品都可以被检索到，如果产品已失效，则在检索结果页和商品详情页标注出商品无效的原因
            //            $sql = "SELECT DISTINCT *,c2c.screenname,p.product_id as productId,p.aHref,ifnull(c2c.self_support,0) as self_support,pd.summary_description,c2p.seller_price,c2p.quantity as c2pQty,case when c2p.customer_id in (select seller_id  from oc_buyer_to_seller b2s where b2s.buyer_id = " . $buyer_id . " and b2s.buy_status = 1 and b2s.buyer_control_status =1 and b2s.seller_control_status = 1 ) then 1 else 0 end as canSell, pd.name AS name, p.image, m.name AS manufacturer, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special, (SELECT points FROM " . DB_PREFIX . "product_reward pr WHERE pr.product_id = p.product_id AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "') AS reward, (SELECT ss.name FROM " . DB_PREFIX . "stock_status ss WHERE ss.stock_status_id = p.stock_status_id AND ss.language_id = '" . (int)$this->config->get('config_language_id') . "') AS stock_status, (SELECT wcd.unit FROM " . DB_PREFIX . "weight_class_description wcd WHERE p.weight_class_id = wcd.weight_class_id AND wcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS weight_class, (SELECT lcd.unit FROM " . DB_PREFIX . "length_class_description lcd WHERE p.length_class_id = lcd.length_class_id AND lcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS length_class, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review r2 WHERE r2.product_id = p.product_id AND r2.status = '1' GROUP BY r2.product_id) AS reviews, p.sort_order,IFNULL((select current_price from vw_delicacy_management dm where dm.product_id=p.product_id and dm.buyer_id=" . $buyer_id . " and product_display=1 AND dm.expiration_time >= NOW()),p.price) as current_price FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id)  LEFT JOIN " . DB_PREFIX . "customerpartner_to_product c2p ON (c2p.product_id = p.product_id )  LEFT JOIN oc_customerpartner_to_customer c2c ON ( c2p.customer_id = c2c.customer_id )  LEFT JOIN oc_customer cus ON ( c2p.customer_id = cus.customer_id ) WHERE p.product_id = '" . (int)$product_id . "' AND c2c.`show` = 1 and cus.status=1 AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.buyer_flag = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";
        } else {
            //            $sql = "SELECT DISTINCT *,c2c.screenname,p.product_id as productId,p.aHref,ifnull(c2c.self_support,0) as self_support,0 as canSell,pd.summary_description,c2p.seller_price,c2p.quantity as c2pQty, pd.name AS name, p.image, m.name AS manufacturer, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special, (SELECT points FROM " . DB_PREFIX . "product_reward pr WHERE pr.product_id = p.product_id AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "') AS reward, (SELECT ss.name FROM " . DB_PREFIX . "stock_status ss WHERE ss.stock_status_id = p.stock_status_id AND ss.language_id = '" . (int)$this->config->get('config_language_id') . "') AS stock_status, (SELECT wcd.unit FROM " . DB_PREFIX . "weight_class_description wcd WHERE p.weight_class_id = wcd.weight_class_id AND wcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS weight_class, (SELECT lcd.unit FROM " . DB_PREFIX . "length_class_description lcd WHERE p.length_class_id = lcd.length_class_id AND lcd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS length_class, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review r2 WHERE r2.product_id = p.product_id AND r2.status = '1' GROUP BY r2.product_id) AS reviews, p.sort_order FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id)  LEFT JOIN " . DB_PREFIX . "customerpartner_to_product c2p ON (c2p.product_id = p.product_id ) LEFT JOIN oc_customerpartner_to_customer c2c ON ( c2p.customer_id = c2c.customer_id )  LEFT JOIN oc_customer cus ON ( c2p.customer_id = cus.customer_id )  WHERE p.product_id = '" . (int)$product_id . "' AND c2c.`show` = 1  and cus.status=1 AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1'  AND p.buyer_flag = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";
            $sql = " SELECT DISTINCT
                    c2c.screenname,cus.status as c_status,
                    p.product_id AS productId,p.price,p.price_display,p.quantity_display,p.status,p.buyer_flag,p.product_type,
                    p.sku,p.upc,p.ean,p.jan,p.isbn,p.mpn,p.location,p.quantity,p.image,p.manufacturer_id,p.viewed,p.model,p.tax_class_id,p.date_available,
                    p.weight,p.weight_class_id,p.length,p.length_class_id,p.width,p.height,p.points,
                    p.aHref,p.length_class_id,p.subtract,p.minimum,p.date_added,p.date_modified,p.freight,p.package_fee,p.product_type,
                    ifnull( c2c.self_support, 0 ) AS self_support,
                    pd.summary_description,pd.name,pd.description,pd.meta_title,pd.meta_description,pd.meta_keyword,pd.tag,
                    c2p.seller_price,c2p.customer_id,
                    c2p.quantity AS c2pQty,
                    0 as canSell,
                    null as discount,
                    m.NAME AS manufacturer,
                    (
                        SELECT price FROM oc_product_special ps
                        WHERE  ps.product_id = p.product_id AND ps.customer_group_id = $customer_group_id
                        AND ( ( ps.date_start = '0000-00-00' OR ps.date_start < NOW( ) ) AND ( ps.date_end = '0000-00-00' OR ps.date_end > NOW( ) ) )
                            ORDER BY ps.priority ASC, ps.price ASC  LIMIT 1
                    ) AS special,
                    ( SELECT points FROM oc_product_reward pr WHERE pr.product_id = p.product_id AND pr.customer_group_id = $customer_group_id ) AS reward,
                    ( SELECT ss.NAME FROM oc_stock_status ss WHERE ss.stock_status_id = p.stock_status_id AND ss.language_id = $language_id ) AS stock_status,
                    ( SELECT wcd.unit FROM oc_weight_class_description wcd WHERE p.weight_class_id = wcd.weight_class_id AND wcd.language_id = $language_id ) AS weight_class,
                    ( SELECT lcd.unit FROM oc_length_class_description lcd WHERE p.length_class_id = lcd.length_class_id AND lcd.language_id = $language_id ) AS length_class,
                    ( SELECT AVG( rating ) AS total FROM oc_review r1 WHERE r1.product_id = p.product_id AND r1.STATUS = '1'  GROUP BY r1.product_id ) AS rating,
                    ( SELECT COUNT( * ) AS total  FROM oc_review r2  WHERE r2.product_id = p.product_id  AND r2.STATUS = '1'  GROUP BY r2.product_id ) AS reviews,
                    p.sort_order
                FROM
                    oc_product p
                    LEFT JOIN oc_product_description pd ON ( p.product_id = pd.product_id )
                    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
                    LEFT JOIN oc_manufacturer m ON ( p.manufacturer_id = m.manufacturer_id )
                    LEFT JOIN oc_customerpartner_to_product c2p ON ( c2p.product_id = p.product_id )
                    LEFT JOIN oc_customerpartner_to_customer c2c ON ( c2p.customer_id = c2c.customer_id )
                    LEFT JOIN oc_customer cus ON ( c2p.customer_id = cus.customer_id )
                WHERE
                    p.product_id = $product_id
                    AND pd.language_id = $language_id
                    AND p.date_available <= NOW()
                    AND p2s.store_id = $store_id ";
            //AND p.STATUS = '1'
            //AND p.buyer_flag = '1'
            //因为涉及到下架产品，所以需要去掉条件。14103 所有上过架的产品都可以被检索到，如果产品已失效，则在检索结果页和商品详情页标注出商品无效的原因
        }
        $query = $this->db->query($sql);
        /**
         * Marketplace Code Starts Here
         */
        if ($this->config->get('module_marketplace_status') && $query->num_rows) {

            if ($check_seller_product) {

                $this->load->model('account/customerpartnerorder');

                if ($this->config->get('marketplace_commission_tax')) {
                    $commission_array = $this->model_account_customerpartnerorder->calculateCommission(array('product_id' => $product_id, 'product_total' => $this->tax->calculate($query->row['price'], $query->row['tax_class_id'], $this->config->get('config_tax'))), $check_seller_product['customer_id']);
                } else {
                    $commission_array = $this->model_account_customerpartnerorder->calculateCommission(array('product_id' => $product_id, 'product_total' => $query->row['price']), $check_seller_product['customer_id']);
                }

                if ($commission_array && isset($commission_array['commission']) && $this->config->get('marketplace_commission_unit_price')) {
                    $commission_amount = $commission_array['commission'];
                }
                $check_seller_status = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_customer WHERE customer_id = '" . (int)$check_seller_product['customer_id'] . "' AND is_partner = '1'")->row;

                if ($this->config->get('module_marketplace_status') && !$this->config->get('marketplace_sellerproductshow') && !$check_seller_status) {

                    return false;
                }
            }

        }

        if (!$product_status) {
            $this->db->query("UPDATE " . DB_PREFIX . "product SET status = '0' WHERE product_id = " . $product_id);
        }
        if (!$product_buyer_flag) {
            $this->db->query("UPDATE " . DB_PREFIX . "product SET buyer_flag = '0' WHERE product_id = " . $product_id);
        }
        /**
         * Marketplace Code Ends Here
         */

        //add by xxli
        $resultTotal = $this->db->query("select ifnull(sum(c2o.quantity),0) as totalSales from oc_customerpartner_to_order c2o left join oc_order ord on ord.order_id = c2o.order_id where ord.order_status_id = ".OcOrderStatus::COMPLETED." and c2o.product_id = " . $product_id)->row;
        if ($resultTotal['totalSales'] > 99999) {
            $totalSale = '99999+';
        } else {
            $totalSale = $resultTotal['totalSales'];
        }
        $result30 = $this->db->query("select ifnull(sum(c2o.quantity),0) as 30Day from oc_customerpartner_to_order c2o left join oc_order ord on ord.order_id = c2o.order_id where ord.order_status_id = ".OcOrderStatus::COMPLETED." and ord.date_added >=DATE_SUB(CURDATE(),INTERVAL 30 DAY) and c2o.product_id = " . $product_id)->row;
        if ($result30['30Day'] > 999) {
            $day30Sale = '999+';
        } else {
            $day30Sale = $result30['30Day'];
        }
        if (isset($query->row['viewed'])) {
            if ($query->row['viewed'] > 99999) {
                $pageView = '99999+';
            } else {
                $pageView = $query->row['viewed'];
            }
        }
        //end by xxli

        if ($query->num_rows) {

            /**
             * product 的当前价格
             * 如果 有精细化价格，则取该值(前提是该 buyer 对该 product 可见)。
             */
            $price = $query->row['price'];
            $be_delicacy = 0;
            $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
            $productPackageFee = null;
            if ($isCollectionFromDomicile) {
                $freight = 0;
                $productPackageFee = ProductFee::query()->where('product_id', $product_id)->where('type', 2)->first();
                // $freight_per = $query->row['package_fee'];
                $freight_per = $productPackageFee ? $productPackageFee->fee : 0;
            } else {
                $freight = 0;
                $productPackageFee = ProductFee::query()->where('product_id', $product_id)->where('type', 1)->first();
                // $freight_per = $query->row['freight'] + $query->row['package_fee'];
                $freight_per = $query->row['freight'] + ($productPackageFee ? $productPackageFee->fee : 0);
            }
            if ($dm_info && $dm_info['product_display'] && isset($dm_info['current_price'])) {
                $price = $dm_info['current_price'] + $freight;
                $be_delicacy = 1;
            } else {
                $price = round(($price + $freight), 2) > 0 ? round(($price + $freight), 2) : 0;
            }

            return array(
                'screenname' => $query->row['screenname'],
                'freight' => $query->row['freight'] == null ? 0 : $query->row['freight'],
                'original_price' => $query->row['price'],
                'is_delicacy' => $be_delicacy,
                'freight_per' => $freight_per,
               // 'package_fee' => $query->row['package_fee'] == null ? 0 : $query->row['package_fee'],
                'package_fee' => $productPackageFee ? $productPackageFee->fee : 0,
                'totalSale' => $totalSale,
                '30Day' => $day30Sale,
                'pageView' => $pageView,
                'customer_id' => $query->row['customer_id'],
                'self_support' => $query->row['self_support'],
                'summary_description' => $query->row['summary_description'],
                'price_display' => $query->row['price_display'],
                'quantity_display' => $query->row['quantity_display'],
                'aHref' => $query->row['aHref'],
                'canSell' => $query->row['canSell'] ? 1 : 0,    // bts 是否建立关联
                'seller_price' => $query->row['seller_price'],
                'c2pQty' => $query->row['c2pQty'],
                'product_id' => $query->row['productId'],
                'name' => $query->row['name'],
                'description' => $query->row['description'],
                'meta_title' => $query->row['meta_title'],
                'meta_description' => $query->row['meta_description'],
                'meta_keyword' => $query->row['meta_keyword'],
                'tag' => $query->row['tag'],
                'model' => $query->row['model'],
                'sku' => $query->row['sku'],
                'upc' => $query->row['upc'],
                'ean' => $query->row['ean'],
                'jan' => $query->row['jan'],
                'isbn' => $query->row['isbn'],
                'mpn' => $query->row['mpn'],
                'location' => $query->row['location'],
                'quantity' => $query->row['quantity'],
                'stock_status' => $query->row['stock_status'],
                'image' => $query->row['image'],
                'manufacturer_id' => $query->row['manufacturer_id'],
                'manufacturer' => $query->row['manufacturer'],

                'price' => ($query->row['discount'] ? $query->row['discount'] : $price) + $commission_amount,

                'special' => $query->row['special'],
                'reward' => $query->row['reward'],
                'points' => $query->row['points'],
                'tax_class_id' => $query->row['tax_class_id'],
                'date_available' => $query->row['date_available'],
                'weight' => $query->row['weight'],
                'weight_class_id' => $query->row['weight_class_id'],
                'length' => $query->row['length'],
                'width' => $query->row['width'],
                'height' => $query->row['height'],
                'length_class_id' => $query->row['length_class_id'],
                'subtract' => $query->row['subtract'],
                'rating' => round($query->row['rating']),
                'reviews' => $query->row['reviews'] ? $query->row['reviews'] : 0,
                'minimum' => $query->row['minimum'],
                'sort_order' => $query->row['sort_order'],
                'status' => $query->row['status'],
                'date_added' => $query->row['date_added'],
                'date_modified' => $query->row['date_modified'],

                'commission_amount' => $commission_amount,

                'viewed' => $query->row['viewed'],
                'c_status' => $query->row['c_status'],
                'buyer_flag' => $query->row['buyer_flag'],
                'product_type' => $query->row['product_type'],
            );
        } else {
            return false;
        }
    }

    /**
     * @param int $product_id
     * @return array
     */
    public function getProductSingleStatus($product_id)
    {
        $obj = $this->orm->table('oc_product as p')
            ->join('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->join('oc_customer as seller', 'seller.customer_id', '=', 'ctp.customer_id')
            ->select(['p.product_id', 'ctp.customer_id', 'p.buyer_flag', 'p.status', 'seller.status as c_status'])
            ->where([
                ['p.product_id', '=', $product_id],
            ])
            ->first();
        return empty($obj) ? [] : obj2array($obj);

    }

    public function getSellers()
    {
        $query = $this->db->query("SELECT b2s.seller_id,c2c.screenname FROM oc_buyer_to_seller b2s LEFT JOIN oc_customerpartner_to_customer c2c on c2c.customer_id = b2s.seller_id  Left join oc_customer c on c.customer_id = c2c.customer_id WHERE c.status = 1 and  b2s.buyer_id = '" . (int)$this->customer->getId() . "'");
        return $query->rows;
    }

    /**
     * 获取用户wishlist 的所有seller
     *
     * @param int $customerId
     * @param $flag
     * @return array [seller_id=>screenname]
     */
    public function getWishSellerList($customerId, $flag)
    {
        if ($flag == 1) {
            $query = $this->buildUnavailableWishListQuery($customerId, []);
        } else {
            $query = $this->buildAvailableWishListQuery($customerId, []);
        }
        return $query->groupBy(['ctp.customer_id'])->orderBy('screenname')
            ->get()->pluck('screenname','seller_id')->all();
    }

    public function addAllProductsRemind($remindQty)
    {
        $this->db->query("DELETE FROM tb_sys_buyer_storage  WHERE buyer_id = '" . (int)$this->customer->getId() . "'");
        $this->db->query("INSERT INTO tb_sys_buyer_storage  SET  buyer_id = '" . (int)$this->customer->getId() . "',remind_qty='" . $this->db->escape($remindQty) . "',create_time = NOW()");
    }

    public function addSellerStoreRemind($seller_id, $remindQty)
    {
        $this->db->query("DELETE FROM tb_sys_seller_storage  WHERE buyer_id = '" . (int)$this->customer->getId() . "'and seller_id='" . $this->db->escape($seller_id) . "'");
        $this->db->query("INSERT INTO tb_sys_seller_storage  SET  buyer_id = '" . (int)$this->customer->getId() . "',seller_id='" . $this->db->escape($seller_id) . "',remind_qty='" . $this->db->escape($remindQty) . "',create_time = NOW()");
    }

    public function getAllProductsRemind()
    {
        $query = $this->db->query("SELECT remind_qty FROM tb_sys_buyer_storage WHERE buyer_id = '" . (int)$this->customer->getId() . "'");
        return $query->row;
    }

    public function getSellerStoreRemind($seller_id)
    {
        $query = $this->db->query("SELECT seller_id,remind_qty FROM tb_sys_seller_storage WHERE buyer_id = '" . (int)$this->customer->getId() . "' and seller_id = " . $seller_id);
        return $query->row;
    }

    public function deleteSellerStoreRemind()
    {
        $this->db->query("DELETE FROM  tb_sys_seller_storage  WHERE buyer_id = '" . (int)$this->customer->getId() . "'");
    }

    public function addItemCodeRemind($product_id, $remind_qty)
    {
        $this->db->query("UPDATE oc_customer_wishlist SET remind_qty = '" . $this->db->escape($remind_qty) . "' WHERE product_id = '" . $this->db->escape($product_id) . "' AND customer_id = '" . (int)$this->customer->getId() . "'");
    }

    public function getCurrency($currency)
    {
        $query = $this->db->query("SELECT symbol_left,symbol_right FROM oc_currency WHERE code = '" . $currency . "' AND status = 1");
        return $query->row;
    }

    public function getPriceHistory($product_id, $buyer_id)
    {

        // 价格历史
        // 判断 buyer 是 上门取货还是 一件代发
        // 上门取货 一件代发都是不用加运费的
        $query = $this->db->query("SELECT price,UNIX_TIMESTAMP(add_date) as add_date FROM oc_seller_price_history WHERE product_id = '" . $product_id . "' order by add_date asc");
        if ($query->num_rows == 0) {

            $result = $this->db->query("(SELECT ph.price,UNIX_TIMESTAMP(ph.add_date) as add_date FROM oc_seller_price_history ph
                LEFT JOIN oc_delicacy_management_history mh on mh.product_id=ph.product_id and mh.buyer_id=" . $buyer_id . "
                WHERE ph.product_id = '" . $product_id . "' and (ph.add_date<mh.effective_time or ph.add_date>mh.expiration_time) )
                UNION ( SELECT ifnull(dm.current_price,p.price) as price,UNIX_TIMESTAMP(NOW()) AS add_date FROM oc_product p
                left join vw_delicacy_management dm on dm.product_id=p.product_id and dm.buyer_id=" . $buyer_id . "
                where p.product_id = '" . $product_id . "')
                UNION ( SELECT p.price,UNIX_TIMESTAMP(p.date_added) AS add_date FROM oc_product p where p.product_id = '" . $product_id . "') order by add_date asc");

        } else {

            $result = $this->db->query("(SELECT ph.price,UNIX_TIMESTAMP(ph.add_date) as add_date FROM oc_seller_price_history ph
                LEFT JOIN oc_delicacy_management_history mh on mh.product_id=ph.product_id and mh.buyer_id=" . $buyer_id . "
                WHERE ph.product_id = '" . $product_id . "' and (ph.add_date<mh.effective_time or ph.add_date>mh.expiration_time) )
                UNION ( SELECT ifnull(dm.current_price,p.price) as price,UNIX_TIMESTAMP(NOW()) AS add_date FROM oc_product p
                left join vw_delicacy_management dm on dm.product_id=p.product_id and dm.buyer_id=" . $buyer_id . "
                where p.product_id = '" . $product_id . "') order by add_date asc");

        }

        return $result->rows;
    }

    //站内信提醒
    public function addCommunication($product_id)
    {
        $this->load->model('message/message');
        $this->log->write("库存变动站内信开始:" . $product_id);
        try {
            $result = $this->db->query("SELECT p.sku,p.mpn,ctc.screenname,p.quantity,ctc.customer_id,pd.name FROM oc_product p LEFT JOIN oc_product_description pd ON p.product_id = pd.product_id LEFT JOIN oc_customerpartner_to_product ctp ON p.product_id = ctp.product_id LEFT JOIN oc_customerpartner_to_customer ctc ON ctc.customer_id = ctp.customer_id WHERE ctp.product_id = '" . $product_id . "'")->row;
            $remind_products = $this->db->query("SELECT cw.customer_id,cw.remind_qty,c.firstname,c.lastname,c.email FROM oc_customer_wishlist cw LEFT JOIN oc_customer c ON cw.customer_id = c.customer_id WHERE product_id = " . (int)$product_id)->rows;
            foreach ($remind_products as $remind_product) {
                // #6774 系统发送站内信通知前需校验此前12小时内是否发送该Item的低库存报警站内信，如果发送过，则系统不再通知 key: sku . buyerId . 'Low Inventory Alert'
                $redisKey = $result['sku'] . $remind_product['customer_id'] . Msg::KEY_LOW_INVENTORY_ALERT;
                if (app('redis')->exists($redisKey)) {
                    continue;
                }

                $subject = 'Low Inventory Alert (Item Code: ' . $result['sku'] . ')';
                if ($remind_product['remind_qty'] == '') {
                    $remind_seller = $this->db->query("SELECT remind_qty FROM tb_sys_seller_storage WHERE seller_id = '" . $result['customer_id'] . "' AND buyer_id = " . $remind_product['customer_id'])->row;
                    if (empty($remind_seller['remind_qty'])) {
                        $remind_buyer = $this->db->query("SELECT remind_qty FROM tb_sys_buyer_storage WHERE buyer_id = " . $remind_product['customer_id'])->row;
                        if (empty($remind_buyer['remind_qty'])) {
                            if ($result['quantity'] <= SUBSCRIBE_COST_QTY) {
                                $message = '<br><h4>This message is to notify you that the following product in your Saved Items has fallen below your desired minimum inventory quantity of ' . SUBSCRIBE_COST_QTY . ' and has triggered a Low Inventory Quantity Alert.</h4><br>';
                                $message .= '<table   border="0" cellspacing="0" cellpadding="0" >';
                                $message .= '<tr><th align="left">Item Code:</th><td>' . $result['sku'] . '</td></tr>';
                                $message .= '<tr><th align="left">Product Name:</th><td>' . $result['name'] . '</td></tr>';
//                                $message .= '<tr><th align="left">Preset Low Inventory Quantity：</th><td>' . SUBSCRIBE_COST_QTY . '</td></tr>';
                                $message .= '<tr><th align="left">Current Inventory Quantity：</th><td>' . $result['quantity'] . '</td></tr>';
                                $message .= '</table><br>';
                                $message .= 'Click <a href="' . HTTPS_SERVER . 'index.php?route=product/product&product_id=' . $product_id . '" target="_blank">here</a> to visit the product page.';

                                /*                                $this->db->query("INSERT INTO oc_wk_communication_message (message_subject,message_body,message_date,message_to,message_from,secure,user_id) values('" . $subject . "','" . $message . "',NOW(),'" . $remind_product['email'] . "','b2b@gigacloudlogistics.com',0,'_" . $remind_product['customer_id'] . "')");
                                                                $message_id = $this->db->getLastId();
                                                                $this->db->query("INSERT INTO oc_wk_communication_placeholder (user_id,user_name,placeholder_id,placeholder_name,message_id,status) values('-1','Admin',2,'Sent'," . $message_id . ",1)");
                                                                $this->db->query("INSERT INTO oc_wk_communication_placeholder (user_id,user_name,placeholder_id,placeholder_name,message_id,status) values('" . $remind_product['customer_id'] . "','" . $remind_product['firstname'] . " " . $remind_product['lastname'] . "',1,'Inbox'," . $message_id . ",1)");*/
//                            $this->sendMyMail('Low Inventory Alert',$message,$remind_product['email']);

                                //新消息中心 From System
                                $this->model_message_message->addSystemMessageToBuyer('product_inventory', $subject, $message, $remind_product['customer_id']);

                                app('redis')->setex($redisKey, 43200, 1);
                            }
                        } else if ($result['quantity'] <= $remind_buyer['remind_qty']) {
                            $message = '<br><h4>This message is to notify you that the following product in your Saved Items has fallen below your desired minimum inventory quantity of ' . $remind_buyer['remind_qty'] . ' and has triggered a Low Inventory Quantity Alert.</h4><br>';
                            $message .= '<table   border="0" cellspacing="0" cellpadding="0" >';
                            $message .= '<tr><th align="left">Item Code:</th><td>' . $result['sku'] . '</td></tr>';
                            $message .= '<tr><th align="left">Product Name:</th><td>' . $result['name'] . '</td></tr>';
//                            $message .= '<tr><th align="left">Preset Low Inventory Quantity：</th><td>' . $remind_buyer['remind_qty'] . '</td></tr>';
                            $message .= '<tr><th align="left">Current Inventory Quantity：</th><td>' . $result['quantity'] . '</td></tr>';
                            $message .= '</table><br>';
                            $message .= 'Click <a href="' . HTTPS_SERVER . 'index.php?route=product/product&product_id=' . $product_id . '" target="_blank">here</a> to visit the product page.';

                            /*                            $this->db->query("INSERT INTO oc_wk_communication_message (message_subject,message_body,message_date,message_to,message_from,secure,user_id) values('" . $subject . "','" . $message . "',NOW(),'" . $remind_product['email'] . "','b2b@gigacloudlogistics.com',0,'_" . $remind_product['customer_id'] . "')");
                                                        $message_id = $this->db->getLastId();
                                                        $this->db->query("INSERT INTO oc_wk_communication_placeholder (user_id,user_name,placeholder_id,placeholder_name,message_id,status) values('-1','Admin',2,'Sent'," . $message_id . ",1)");
                                                        $this->db->query("INSERT INTO oc_wk_communication_placeholder (user_id,user_name,placeholder_id,placeholder_name,message_id,status) values('" . $remind_product['customer_id'] . "','" . $remind_product['firstname'] . " " . $remind_product['lastname'] . "',1,'Inbox'," . $message_id . ",1)");*/
//                        $this->sendMyMail('Low Inventory Alert',$message,$remind_product['email']);

                            //新消息中心 From System
                            $this->model_message_message->addSystemMessageToBuyer('product_inventory', $subject, $message, $remind_product['customer_id']);

                            app('redis')->setex($redisKey, 43200, 1);
                        }
                    } else if ($result['quantity'] <= $remind_seller['remind_qty']) {
                        $message = '<br><h4>This message is to notify you that the following product in your Saved Items has fallen below your desired minimum inventory quantity of ' . $remind_seller['remind_qty'] . ' and has triggered a Low Inventory Quantity Alert.</h4><br>';
                        $message .= '<table   border="0" cellspacing="0" cellpadding="0" >';
                        $message .= '<tr><th align="left">Item Code:</th><td>' . $result['sku'] . '</td></tr>';
                        $message .= '<tr><th align="left">Product Name:</th><td>' . $result['name'] . '</td></tr>';
//                        $message .= '<tr><th align="left">Preset Low Inventory Quantity：</th><td>' . $remind_seller['remind_qty'] . '</td></tr>';
                        $message .= '<tr><th align="left">Current Inventory Quantity：</th><td>' . $result['quantity'] . '</td></tr>';
                        $message .= '</table><br>';
                        $message .= 'Click <a href="' . HTTPS_SERVER . 'index.php?route=product/product&product_id=' . $product_id . '" target="_blank">here</a> to visit the product page.';

                        /*                        $this->db->query("INSERT INTO oc_wk_communication_message (message_subject,message_body,message_date,message_to,message_from,secure,user_id) values('" . $subject . "','" . $message . "',NOW(),'" . $remind_product['email'] . "','b2b@gigacloudlogistics.com',0,'_" . $remind_product['customer_id'] . "')");
                                                $message_id = $this->db->getLastId();
                                                $this->db->query("INSERT INTO oc_wk_communication_placeholder (user_id,user_name,placeholder_id,placeholder_name,message_id,status) values('-1','Admin',2,'Sent'," . $message_id . ",1)");
                                                $this->db->query("INSERT INTO oc_wk_communication_placeholder (user_id,user_name,placeholder_id,placeholder_name,message_id,status) values('" . $remind_product['customer_id'] . "','" . $remind_product['firstname'] . " " . $remind_product['lastname'] . "',1,'Inbox'," . $message_id . ",1)");*/
//                    $this->sendMyMail('Low Inventory Alert',$message,$remind_product['email']);

                        //新消息中心 From System
                        $this->model_message_message->addSystemMessageToBuyer('product_inventory', $subject, $message, $remind_product['customer_id']);

                        app('redis')->setex($redisKey, 43200, 1);
                    }
                } else if ($result['quantity'] <= $remind_product['remind_qty']) {
                    $message = '<br><h4>This message is to notify you that the following product in your Saved Items has fallen below your desired minimum inventory quantity of ' . $remind_product['remind_qty'] . ' and has triggered a Low Inventory Quantity Alert.</h4><br>';
                    $message .= '<table   border="0" cellspacing="0" cellpadding="0" >';
                    $message .= '<tr><th align="left">Item Code:</th><td>' . $result['sku'] . '</td></tr>';
                    $message .= '<tr><th align="left">Product Name:</th><td>' . $result['name'] . '</td></tr>';
//                    $message .= '<tr><th align="left">Preset Low Inventory Quantity：</th><td>' . $remind_product['remind_qty'] . '</td></tr>';
                    $message .= '<tr><th align="left">Current Inventory Quantity：</th><td>' . $result['quantity'] . '</td></tr>';
                    $message .= '</table><br>';
                    $message .= 'Click <a href="' . HTTPS_SERVER . 'index.php?route=product/product&product_id=' . $product_id . '" target="_blank">here</a> to visit the product page.';

                    /*                    $this->db->query("INSERT INTO oc_wk_communication_message (message_subject,message_body,message_date,message_to,message_from,secure,user_id) values('" . $subject . "','" . $message . "',NOW(),'" . $remind_product['email'] . "','b2b@gigacloudlogistics.com',0,'_" . $remind_product['customer_id'] . "')");
                                        $message_id = $this->db->getLastId();
                                        $this->db->query("INSERT INTO oc_wk_communication_placeholder (user_id,user_name,placeholder_id,placeholder_name,message_id,status) values('-1','Admin',2,'Sent'," . $message_id . ",1)");
                                        $this->db->query("INSERT INTO oc_wk_communication_placeholder (user_id,user_name,placeholder_id,placeholder_name,message_id,status) values('" . $remind_product['customer_id'] . "','" . $remind_product['firstname'] . " " . $remind_product['lastname'] . "',1,'Inbox'," . $message_id . ",1)");*/
//                $this->sendMyMail('Low Inventory Alert',$message,$remind_product['email']);

                    //新消息中心 From System
                    $this->model_message_message->addSystemMessageToBuyer('product_inventory', $subject, $message, $remind_product['customer_id']);

                    app('redis')->setex($redisKey, 43200, 1);
                }
            }
            $this->log->write("库存变动站内信结束:" . $product_id);
        } catch (Exception $e) {
            $this->log->write("库存变动站内信错误" . $e->getMessage());
        }
    }

    /**
     * 价格变化提醒
     * @param $displayPriceBefore
     * @param $displayPriceAfter
     * @param int $product_id
     * @param null|DateTime $effect_time
     * @throws Exception
     */
    public function addPriceCommunication($displayPriceBefore, $displayPriceAfter, $product_id, $effect_time = null)
    {
        $this->load->model('message/message');
        $customerCountry = $this->customer->getCountryId();
        $result = $this->db->query("SELECT p.sku,p.mpn,ctc.screenname,p.quantity,ctc.customer_id,pd.name,p.price,cur.symbol_left,cur.symbol_right FROM oc_product p LEFT JOIN oc_product_description pd ON p.product_id = pd.product_id LEFT JOIN oc_customerpartner_to_product ctp ON p.product_id = ctp.product_id LEFT JOIN oc_customerpartner_to_customer ctc ON ctc.customer_id = ctp.customer_id LEFT JOIN oc_customer c on c.customer_id = ctc.customer_id LEFT JOIN oc_country cou on cou.country_id = c.country_id LEFT JOIN oc_currency cur on cur.currency_id = cou.currency_id WHERE ctp.product_id = '" . $product_id . "'")->row;
        //欧洲服务费系数适用的特殊用户分组帐号清单，分组做了硬编码13
        $europeCustomers = $this->db->query("SELECT customer_id FROM oc_customer WHERE customer_group_id = 13")->rows;
        if (!empty($europeCustomers)) {
            foreach ($europeCustomers as $key => $value) {
                $europe_customer_list[] = $value['customer_id'];
            }
        }
        $customerIds = array_keys($displayPriceAfter);
        $customerIdMap = Customer::query()->whereIn('customer_id', $customerIds)->get()->keyBy('customer_id');

        foreach ($displayPriceAfter as $customerId => $buyerDisplayPrice) {
            //by chenyang 站内信的价格，对欧洲特殊分组需要乘以服务费系数
            $customer = $customerIdMap->get($customerId, '');
            if (!empty($europe_customer_list) && isset($customerId) && in_array($customerId, $europe_customer_list)) {
                $modified_price = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice($result['customer_id'], $buyerDisplayPrice, $customerCountry);
                $serverFeeAfter = $buyerDisplayPrice - $modified_price;
                $modified_price = app(ProductPriceRepository::class)->getProductActualPriceByBuyer($result['customer_id'], $customer, $buyerDisplayPrice) - $serverFeeAfter;
                if (isset($displayPriceBefore[$customerId])) {
                    $beforePrice = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice($result['customer_id'], $displayPriceBefore[$customerId], $customerCountry);
                    $serverFeeBefore = $displayPriceBefore[$customerId] - $beforePrice;
                    $beforePrice = app(ProductPriceRepository::class)->getProductActualPriceByBuyer($result['customer_id'], $customer, $displayPriceBefore[$customerId]) - $serverFeeBefore;
                } else {
                    continue;
                }
            } else {
                $modified_price = app(ProductPriceRepository::class)->getProductActualPriceByBuyer($result['customer_id'], $customer, $buyerDisplayPrice);
                $beforePrice = app(ProductPriceRepository::class)->getProductActualPriceByBuyer($result['customer_id'], $customer, $displayPriceBefore[$customerId]);
            }
            if ($modified_price == $beforePrice) {
                continue;
            }
            $currentPrice = $this->currency->formatCurrencyPrice($modified_price, session('currency'));
            $oldPrice = $this->currency->formatCurrencyPrice($beforePrice, session('currency'));
            $subject = 'Unit Price Variation Alert (Item Code: ' . $result['sku'] . ')';
            $message = '<br><h3>Please remind that the unit price of one subscribed product is changed：</h3><br>';
            $message .= '<table   border="0" cellspacing="0" cellpadding="0" >';
            $message .= '<tr><th align="left">Item Code:</th><td>' . $result['sku'] . '</td></tr>';
            $message .= '<tr><th align="left">Product Name:</th><td>' . $result['name'] . '</td></tr>';
            if (isset($effect_time) && !empty($effect_time)) {
                $message .= '<tr><th align="left">Current Unit Price：</th><td>' . $oldPrice . '</td></tr>';
                if ($result['price'] != $modified_price) {
                    $message .= '<tr><th align="left">Modified Unit Price：</th><td>' . $currentPrice . '</td></tr>';
                }
                $effect_time_str = $effect_time->format("Y-m-d H:i:s");
                $message .= '<tr><th align="left">Effect Time：</th><td>' . $effect_time_str . '</td></tr>';
            } else {
                $message .= '<tr><th align="left">Previous Unit Price：</th><td>' . $oldPrice . '</td></tr>';
                $message .= '<tr><th align="left">Current Unit Price：</th><td>' . $currentPrice . '</td></tr>';
            }
            $message .= '</table><br>';
            $message .= 'Click <a href="' . HTTPS_SERVER . 'index.php?route=product/product&product_id=' . $product_id . '">here</a> to visit the product page.';


            //新消息中心 From System
            $this->model_message_message->addSystemMessageToBuyer('product_price', $subject, $message, $customerId);
        }
    }

    public function checkSellerConect($seller_id)
    {
        $customer_id = $this->customer->getId();
        if ($seller_id == $customer_id) {
            return true;
        }
        $result = $this->db->query("SELECT count(1) as num FROM oc_buyer_to_seller WHERE buyer_id = '" . $customer_id . "' AND seller_id = '" . $seller_id . "' AND buy_status = 1 AND buyer_control_status = 1 AND seller_control_status = 1 ")->row;
        if ($result['num'] > 0) {
            return true;
        }
    }

    public function sendMyMail($subject, $message, $email)
    {
        require_once DIR_SYSTEM . "library/phpmailer/PHPMailer.php";
        require_once DIR_SYSTEM . "library/phpmailer/Exception.php";
        require_once DIR_SYSTEM . "library/phpmailer/SMTP.php";
        require_once DIR_SYSTEM . "library/phpmailer/OAuth.php";
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);                              // Passing `true` enables exceptions
        //Server settings
        $mail->SMTPDebug = 0;                                 // Enable verbose debug output
        $mail->isSMTP();                                      // Set mailer to use SMTP
        $mail->CharSet = 'UTF-8';
        $mail->Host = $this->config->get('config_mail_smtp_hostname');  // Specify main and backup SMTP servers
        $mail->SMTPAuth = true;                               // Enable SMTP authentication
        $mail->Username = $this->config->get("config_mail_smtp_username");                 // SMTP username
        $mail->Password = $this->config->get('config_mail_smtp_password');                           // SMTP password
        $mail->SMTPSecure = 'ssl';                            // Enable TLS encryption, `ssl` also accepted
        $mail->Port = $this->config->get('config_mail_smtp_port');                                    // TCP port to connect to

        //Recipients
        $mail->setFrom($this->config->get("config_mail_smtp_username"));
        $mail->addAddress($email);
        $mail->FromName = $this->config->get('config_name');
        //Content
        $mail->isHTML(true);                                  // Set email format to HTML
        $subject = html_entity_decode($subject, ENT_QUOTES, 'UTF-8');
        $message = html_entity_decode($message, ENT_QUOTES, 'UTF-8');
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->send();
    }

    public function getSellerStoreReminds()
    {
        $query = $this->db->query("SELECT a.seller_id,a.remind_qty FROM tb_sys_seller_storage a left join oc_customer c on c.customer_id = a.seller_id WHERE c.status = 1 and a.buyer_id = '" . (int)$this->customer->getId() . "'");
        return $query->rows;
    }

    public function removeAllProductsRemind()
    {
        $this->db->query("Delete FROM tb_sys_buyer_storage WHERE buyer_id = '" . (int)$this->customer->getId() . "'");
    }

    public function removeItemCodeRemind($product_id)
    {
        $this->db->query("UPDATE oc_customer_wishlist set remind_qty = null WHERE customer_id = '" . (int)$this->customer->getId() . "' AND product_id = " . $product_id);
    }

    public function getDownloadWishListProduct($customerId, $filter_data)
    {
        //14408上门取货账号一键下载超大件库存分布列表
        $ltl_tag = 0;
        $ltl_sql = '';
        if (isset($filter_data['filter_input_ltl']) && $filter_data['filter_input_ltl'] == 1) {
            $ltl_tag = 1;
            $ltl_sql = ' LEFT JOIN oc_product_to_tag otp on p.product_id = otp.product_id ';
        }
        if ($this->customer->isCollectionFromDomicile()) {
            $package_fee_type = 2;
        } else {
            $package_fee_type = 1;
        }
        if ($this->customer->isPartner()) {
            $sql = "SELECT oc.status as c_status,p.buyer_flag,p.combo_flag,p.status,ctp.customer_id,p.product_id,p.sku,ctc.customer_id AS sellerId,ctc.screenname,pd.name AS productName,m.name AS brand,p.quantity,round(p.price,2) AS price,p.freight,p.package_fee,p.tax_class_id,p.product_type
                  FROM oc_customer_wishlist cw
                INNER JOIN oc_product p ON cw.product_id = p.product_id
                INNER JOIN oc_product_description pd ON p.product_id = pd.product_id
                INNER JOIN oc_customerpartner_to_product ctp ON p.product_id = ctp.product_id
                INNER JOIN oc_customer oc ON oc.customer_id=ctp.customer_id
                INNER JOIN oc_customerpartner_to_customer ctc ON ctp.customer_id = ctc.customer_id
                " . $ltl_sql . "
                LEFT JOIN oc_manufacturer m ON p.manufacturer_id = m.manufacturer_id
                WHERE  cw.customer_id = " . (int)$customerId;
        } else {
            $sql = "SELECT oc.status as c_status,p.buyer_flag,p.combo_flag,p.status,ctp.customer_id,p.product_id,p.sku,ctc.customer_id AS sellerId,ctc.screenname,pd.name AS productName,m.name AS brand,p.quantity,round(IFNULL(dm.current_price,p.price), 2) AS price,p.freight,pf.fee as package_fee,p.tax_class_id,bts.buyer_id,bts.seller_id,p.product_type FROM oc_customer_wishlist cw
                INNER JOIN oc_product p ON cw.product_id = p.product_id
                INNER JOIN oc_product_description pd ON p.product_id = pd.product_id
                INNER JOIN oc_customerpartner_to_product ctp ON p.product_id = ctp.product_id
                INNER JOIN oc_customer oc ON oc.customer_id=ctp.customer_id
                INNER JOIN oc_customerpartner_to_customer ctc ON ctp.customer_id = ctc.customer_id
                " . $ltl_sql . "
                INNER JOIN oc_buyer_to_seller bts ON bts.seller_id = ctp.customer_id AND bts.buyer_id = cw.customer_id
                LEFT JOIN oc_manufacturer m ON p.manufacturer_id = m.manufacturer_id
                LEFT JOIN oc_product_fee pf ON pf.product_id = cw.product_id AND pf.type={$package_fee_type}
				LEFT JOIN vw_delicacy_management dm on (dm.product_id=cw.product_id and dm.buyer_id=cw.customer_id)
                WHERE  cw.customer_id = " . (int)$customerId;
        }
        if (isset($filter_data['filter_input_name']) && trim($filter_data['filter_input_name']) != '') {
            $sql .= " AND ( p.sku LIKE '%" . $this->db->escape(trim($filter_data['filter_input_name'])) . "%'";
            $sql .= " OR pd.name LIKE '%" . $this->db->escape(trim($filter_data['filter_input_name'])) . "%')";
        }
        //14408上门取货账号一键下载超大件库存分布列表
        //oc_tag 中 id 为 1 代表ltl 若改变则改变此致
        if ($ltl_tag) {
            $sql .= " AND otp.tag_id = 1";
        }
        if (isset($filter_data['filter_input_sort']) && trim($filter_data['filter_input_sort']) != '') {
            if ($filter_data['filter_input_sort'] == 1) {
                $sql .= " order by p.product_id ";
            } elseif ($filter_data['filter_input_sort'] == 2) {
                $sql .= " order by ctc.screenname asc ";
            } elseif ($filter_data['filter_input_sort'] == 3) {
                $sql .= " order by ctc.screenname desc ";
            } elseif ($filter_data['filter_input_sort'] == 4) {
                $sql .= " order by temp.sellQty desc ";
            }
        } else {
            $sql .= " order by p.quantity desc ";
        }
        $sql .= " ,p.quantity desc";
        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * 获取未读的关于库存订阅的站内信
     * @param int $customerId
     * @return null
     * @author xxl
     */
    public function getUnreadWishListCommunication($customerId)
    {
        $msgIds = MsgReceive::queryRead()->alias('r')->joinRelations('msg as s')
            ->where('r.receiver_id', $customerId)
            ->where('r.is_read', YesNoEnum::NO)
            ->whereRaw('(s.title like ? or s.title like ? or s.title like ? or s.title like ?)', ['Low Inventory Reminder', 'Unit Price Variation Reminder', 'Low Inventory Alert%', 'Unit Price Variation Alert%'])
            ->pluck('r.msg_id')
            ->toJson();
        $msgContents = MsgContent::queryRead()->whereIn('msg_id', $msgIds)->pluck('content')->toArray();

        $productArray = array();
        foreach ($msgContents as $msgContent) {
            $product_id = $this->getNeedBetween($msgContent, '&product_id=', '</a>');
            $product_id_new = substr($product_id, strrpos($product_id, '=') + 1);
            array_push($productArray, $product_id_new);
        }
        array_unique($productArray);
        //这些站内信是否还在库存订阅列表里
        $countResult = $this->orm->table('oc_customer_wishlist as cw')
            ->whereIn('cw.product_id', $productArray)
            ->where('cw.customer_id', '=', $customerId)
            ->count();
        return $countResult;
    }


    function getNeedBetween($kw1, $mark1, $mark2)
    {
        $kw = $kw1;
        $kw = '123' . $kw . '123';
        $st = stripos($kw, $mark1);
        $ed = stripos($kw, $mark2);
        if (($st == false || $ed == false) || $st >= $ed)
            return 0;
        $kw = substr($kw, ($st + 1), ($ed - $st - 1));
        return $kw;
    }

    /**
     * 获取精细化的价格
     * @param int $product_id
     * @param int $buyer_id
     * @param string $effective_time
     * @return array
     * @author xxl
     */
    public function getDelicacyPrice($product_id, $buyer_id, $effective_time)
    {
        $results = $this->orm->table('oc_delicacy_management_history as dmh')
            ->leftJoin('oc_customer_wishlist as cw', [['cw.product_id', '=', 'dmh.product_id'], ['cw.customer_id', '=', 'dmh.buyer_id']])
            ->where([
                ['dmh.product_id', '=', $product_id],
                ['dmh.buyer_id', '=', $buyer_id],
                ['dmh.add_time', '>=', $effective_time]
            ])
            ->whereRaw('dmh.add_time>cw.date_added')
            ->select(['dmh.type',
                'dmh.current_price',
                'dmh.price'
            ])
            ->selectRaw('UNIX_TIMESTAMP(dmh.effective_time) as effective_time,
                UNIX_TIMESTAMP(dmh.add_time) as add_time')
            ->get();
        return obj2array($results);
    }

    /**
     * 获取产品可见的最新时间
     * @param int $product_id
     * @param int $buyer_id
     * @return string
     * @author  xxl
     */
    public function getLastProductDisplayTime($product_id, $buyer_id)
    {
        $results = $this->orm->table('oc_delicacy_management_history as dmh')
            ->where([['dmh.product_id', '=', $product_id], ['dmh.buyer_id', '=', $buyer_id], ['dmh.product_display', '=', '0']])
            ->orderBy('dmh.id', 'desc')
            ->select('dmh.add_time')
            ->first();
        if (isset($results)) {
            $add_time = $results->add_time;
        } else {
            $add_time = '0000-00-00 00:00:00';
            return $add_time;
        }
        $resultDisplayTime = $this->orm->table('oc_delicacy_management_history as dmh')
            ->where([
                ['dmh.product_id', '=', $product_id],
                ['dmh.buyer_id', '=', $buyer_id],
                ['dmh.product_display', '=', '1'],
                ['dmh.add_time', '>', $add_time]
            ])
            ->select('dmh.add_time')
            ->first();
        if (isset($resultDisplayTime)) {
            $effective_time = $resultDisplayTime->add_time;
        } else {
            $effective_time = '0000-00-00 00:00:00';
        }
        return $effective_time;
    }


    /**
     * 获取精细化管理是过期或者删除时的价格
     * @param int $product_id
     * @param $deleteTime
     * @return mixed
     * @author xxl
     */
    public function getDelicacyDeletePrice($product_id, $deleteTime)
    {
        $result = $this->orm->table('oc_seller_price_history as ph')
            ->where([
                ['ph.product_id', '=', $product_id],
                ['ph.status', '=', '1'],
                ['ph.add_date', '<', $deleteTime]
            ])
            ->select('ph.price')
            ->orderBy('ph.add_date', 'desc')
            ->first();
        if (isset($result)) {
            $priceDelete = $result->price;
        } else {
            $resultProduct = $this->orm->table('oc_product as op')
                ->where('op.product_id', '=', $product_id)
                ->select('op.price')
                ->first();
            $priceDelete = $resultProduct->price;
        }
        return $priceDelete;
    }

    /**
     * 获取库存订阅添加的时间
     * @param int $product_id
     * @param int $customer_id
     * @return mixed
     * @author  xxl
     */
    public function getWishListAddTime($product_id, $customer_id)
    {
        $result = $this->orm->table('oc_customer_wishlist as cw')
            ->where([
                ['cw.product_id', '=', $product_id],
                ['cw.customer_id', '=', $customer_id]
            ])
            ->selectRaw('cw.price,UNIX_TIMESTAMP(cw.date_added) as add_date')
            ->first();
        return obj2array($result);
    }

    public function getDiscountByProductId($buyer_id, $product_id)
    {

        $result = $this->orm->table('oc_customerpartner_to_product as ctp')
            ->leftJoin('oc_buyer_to_seller as bts', 'ctp.customer_id', '=', 'bts.seller_id')
            ->where([
                ['bts.buyer_id', '=', $buyer_id],
                ['ctp.product_id', '=', $product_id]
            ])
            ->selectRaw('ifnull(bts.discount,1) as discount')
            ->first();
        return obj2array($result);
    }


    /**
     * 获取所有订阅该产品的buyer的价格
     * @param int $product_id
     * @return array
     */
    public function getWishListPriceArray($product_id, $modified_price = null)
    {
        $remind_products = $this->db->query("SELECT cw.customer_id,cw.remind_qty,c.firstname,c.lastname,c.email,c.customer_group_id FROM oc_customer_wishlist cw LEFT JOIN oc_customer c ON cw.customer_id = c.customer_id WHERE product_id = " . (int)$product_id)->rows;
        //产品价格展示
        $productIdArray = array();
        foreach ($remind_products as $remind_product) {
            $displayPrice = $this->commonFunction->getDisplayPrice($product_id, $remind_product['customer_id'], $modified_price);
            $productIdArray[$remind_product['customer_id']] = $displayPrice;
        }
        return $productIdArray;
    }

    public function getProductInfo($product_id)
    {
        $info = $this->orm->table(DB_PREFIX . "product as op")
            ->leftJoin(DB_PREFIX . 'customerpartner_to_product as ctp', 'ctp.product_id', '=', 'op.product_id')
            ->leftJoin(DB_PREFIX . 'customer as c', 'c.customer_id', '=', 'ctp.customer_id')
            ->where('op.product_id', '=', $product_id)
            ->select('op.product_type', 'ctp.customer_id', 'c.customer_group_id')
            ->first();
        return obj2array($info);
    }

    /** 收藏夹分组设置
     * author: wyq
     * date: 2020.07.02
     */
    //添加分组，返回 group_id或0、-1(分组已达上限，不可新建)
    public function addWishGroup($name)
    {
        $customer_id = $this->customer->getId();
        $count = $this->orm->table('oc_wishlist_group')
            ->whereRaw("customer_id={$customer_id} AND id>0")
            ->count();
        //分组最多新建6个
        if ($count < 6) {
            $count_self = $this->orm->table('oc_wishlist_group')
                ->whereRaw("customer_id={$customer_id} AND name='{$name}'")
                ->count();
            if ($count_self) {
                return 0; //分组已存在
            } else {
                return $this->orm->table('oc_wishlist_group')
                    ->insertGetId(['customer_id' => $customer_id, 'name' => $name]);
            }
        } else {
            return -1; //分组已达上限，不可新建
        }
    }

    //单个分组删除（移入未分组），返回受影响行数
    public function delWishGroup($group_id)
    {
        $customer_id = $this->customer->getId();
        //把分组内产品移动到未分组
        $products = $this->orm->table('oc_customer_wishlist')
            ->whereRaw("customer_id={$customer_id} AND group_id={$group_id}")
            ->selectRaw('product_id')
            ->get()->toArray();
        if ($products) {
            $product_id_str = implode(',', array_column($products, 'product_id'));
            $this->orm->table('oc_customer_wishlist')
                ->whereRaw("customer_id={$customer_id} AND product_id IN ({$product_id_str})")
                ->update(['group_id' => 0]);
        }
        return (int)$this->orm->table('oc_wishlist_group')
            ->where(['customer_id' => $customer_id])
            ->delete($group_id);
    }

    //分组改名，返回受影响行数
    public function renameWishGroup($group_id, $new_name)
    {
        if (strlen($group_id) == 0 || strlen($new_name) == 0) {
            return 0;
        }
        $customer_id = $this->customer->getId();
        //无法修改all分组
        if ($group_id == 0) {
            return 0;
        } else {
            return (int)$this->orm->table('oc_wishlist_group')
                ->where(['id' => $group_id, 'customer_id' => $customer_id])
                ->update(['name' => $new_name]);
        }
    }
    //加入商品的info: (price,original_price,freight,is_delicacy,can_add_cart)等信息. 查询
    //【多店铺，多产品，批量信息查询】 定制返回字段
    //参数如: 输入'16956,17027,29473,19640,29164,30751'；输出如[16956 => array(),15615 => array (
    //    'customer_id' => 301,
    //    'product_id' => 15615,
    //    'price' => 1800.0,
    //    'freight_package_fee' => 910.0,
    //    'is_delicacy' => 0,
    //    'original_price' => '1900.00',
    //    //'can_add_cart' => 1,
    //  ) ]
    public function getProductByIds($product_id_str)
    {
        if (strlen($product_id_str) == 0) {
            return [];
        }
        $customer_id = $this->customer->getId();
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        $customerCountry = $this->customer->getCountryId();
        $unavailable_products_id = $this->getUserWishUnAvailableProductIds($customer_id);
        $package_fee_type = $isCollectionFromDomicile ? 2 : 1;

        //联合查询目标产品树：由于产品-卖家表设计上是一对一的关系，省略unionAll c2p.customer_id=' .$seller_id 条件
        $products = $this->orm->table('oc_product AS p')
            ->leftJoin('oc_customerpartner_to_product AS c2p', 'p.product_id', '=', 'c2p.product_id')
            ->leftJoin('oc_product_to_store as p2s', 'p2s.product_id', '=', 'p.product_id')
            ->leftJoin('oc_product_description as d', 'd.product_id', '=', 'p.product_id')
            ->leftJoin('oc_product_to_category as p2c', 'p2c.product_id', '=', 'p.product_id')
            ->leftJoin('oc_product_fee as fee', function (JoinClause $j) use ($package_fee_type) {
                $j->on('fee.product_id', '=', 'p.product_id')
                    ->where(['fee.type' => $package_fee_type]);
            })
            ->selectRaw('c2p.customer_id,p.product_id,p.price,p.tax_class_id,'
                . 'p.product_type,p.freight,fee.fee AS package_fee,group_concat(p2c.category_id) AS category_ids')   //自定义字段
            ->whereRaw('p.product_id IN (' . $product_id_str . ')')
            //强制查出产品信息
            ->groupBy('p.product_id')
            ->get()->toArray();
        $products = obj2array($products);

        //批量获取产品打折信息
        $seller_id_str = implode(',', array_unique(array_column($products, 'customer_id')));
        $discountInfo = $this->orm->table('oc_buyer_to_seller')
            ->whereRaw("buyer_id={$customer_id} AND seller_id IN ({$seller_id_str})")
            ->selectRaw('seller_id,discount')
            ->get()->toArray();
        if (empty($discountInfo)) {
            $discountInfo = [];
        } else {
            $discountInfo = array_combine(array_column($discountInfo, 'seller_id'), array_column($discountInfo, 'discount'));
        }
        $result_products = array();
        foreach ($products as $k => $product) {
            //关联需求号101929，计入打包费
            $product['freight_package_fee'] = $isCollectionFromDomicile ? $product['package_fee'] : $product['package_fee'] + $product['freight'];
            $product['is_delicacy'] = in_array($product['product_id'], $unavailable_products_id) ? 1 : 0;
            if (isset($discountInfo[$product['customer_id']])) {
                $price = round($product['price'] * $discountInfo[$product['customer_id']], 2);
                // #31737 下单针对于非复杂交易且普通产品的价格需要判断是否需免税
                if ($customerCountry && $this->customer->getGroupId() == 13) {
                    if ($product['product_type'] != ProductType::NORMAL) {
                        $price = app(SellerProductRatioRepository::class)->calculationSellerDisplayPrice(intval($product['customer_id']), $price, $customerCountry);
                    } else {
                        [, $price,] = app(ProductPriceRepository::class)->getProductTaxExemptionPrice(intval($product['customer_id']), $price);
                    }
                } else {
                    if ($product['product_type'] == ProductType::NORMAL) {
                        $price = app(ProductPriceRepository::class)->getProductActualPriceByBuyer(intval($product['customer_id']), customer()->getModel(), $price);
                    }
                }
            } else {
                // #31737 下单针对于非复杂交易且普通产品的价格需要判断是否需免税
                if ($product['product_type'] == ProductType::NORMAL) {
                    $price = app(ProductPriceRepository::class)->getProductActualPriceByBuyer(intval($product['customer_id']), customer()->getModel(), $product['price']);
                }
            }
            if (session('currency') == 'JPY') {
                $price = round($price, 0);
            }

            //大客户折扣，精细化不参与大客户折扣
            if ($product['product_type'] == ProductType::NORMAL) {
                if ($product['is_delicacy'] == 0) {
                    $bigClientDiscount = app(MarketingDiscountRepository::class)->getBuyerDiscountInfo($product['product_id'], 0, customer()->getId());
                    if ($bigClientDiscount > 0) {
                        $price = MoneyHelper::upperAmount(bcmul($price, $bigClientDiscount / 100, 3), customer()->isJapan() ? 0 : 2);
                    }
                }
            }

            $product['original_price'] = $product['price'];
            $product['price'] = $price;
            unset($product['tax_class_id'], $product['freight'], $product['package_fee'], $product['category_ids']);
            $result_products[$product['product_id']] = $product;
        }
        return $result_products;
    }

    //单个、批量设置分组【注意添加第三个参数：调用来源】，返回受影响行数
    public function setProductsToWishGroup($product_id_str, $group_id = 0, $from = 'download')
    {
        $customer_id = $this->customer->getId();
        $result_products = $this->getProductByIds($product_id_str);
        $row = 0;
        if ($from == 'download') {
            //$this->log->write('INFO: setDownloadToWishGroup=>' . $product_id_str);
            //关于心愿单下载页的下载问题，其它下载遇到已保存的项：跳过已保存项、保存到未分组
            foreach ($result_products as $product_id => $product) {
                $search = [
                    'customer_id' => $customer_id,
                    'product_id' => $product_id,
                ];
                $old = $this->orm->table('oc_customer_wishlist')->where($search)->count();
                if ($old == 0) {
                    $row += $this->orm->table('oc_customer_wishlist')
                        ->insert(
                            [
                                'customer_id' => $customer_id,
                                'product_id' => $product_id,
                                'group_id' => 0,
                                'date_added' => date('Y-m-d H:i:s'),
                                'price' => $product['price'],
                                'freight' => $product['freight_package_fee'],
                                'original_price' => $product['original_price'],
                                'is_delicacy' => $product['is_delicacy']
                            ]
                        );
                }
            }
        } else {
            //单个取消+加入
            if (substr_count($product_id_str, ',') == 0 && $result_products) {
                $product = array_values($result_products)[0];
                return $this->orm->table('oc_customer_wishlist')
                    ->updateOrInsert(
                        [
                            'customer_id' => $customer_id,
                            'product_id' => $product['product_id']
                        ],
                        [
                            'date_added' => date('Y-m-d H:i:s'),
                            'group_id' => $group_id,
                            'price' => $product['price'],
                            'freight' => $product['freight_package_fee'],
                            'original_price' => $product['original_price'],
                            'is_delicacy' => $product['is_delicacy']
                        ]
                    );
            }
            //已经取消收藏的，无法移入分组
            $old_exists = $this->orm->table('oc_customer_wishlist')
                ->whereRaw("customer_id={$customer_id} AND product_id IN ($product_id_str)")->count();
            if ($old_exists < substr_count($product_id_str, ',') + 1) {
                return -1;
            }
            //$this->log->write('INFO: setProductsToWishGroup=>' .$product_id_str);
            foreach ($result_products as $product_id => $product) {
                $row += $this->orm->table('oc_customer_wishlist')
                    ->updateOrInsert(
                        [
                            'customer_id' => $customer_id,
                            'product_id' => $product_id
                        ],
                        [
                            'group_id' => $group_id,
                            'price' => $product['price'],
                            'freight' => $product['freight_package_fee'],
                            'original_price' => $product['original_price'],
                            'is_delicacy' => $product['is_delicacy']
                        ]
                    );
            }
        }
        return $row;
    }

    //单个、删除分组，返回受影响行数
    public function delProductsToWishGroup($product_id_str)
    {
        if (strlen($product_id_str) == 0) {
            return 0;
        }
        return db('oc_customer_wishlist')
            ->where('customer_id', customer()->getId())
            ->whereIn('product_id', array_unique(explode(',', $product_id_str)))
            ->delete();
    }
    //设置产品提醒-3个类型级别，返回受影响行数
    //参数 remind_qty=>123, typeArr=[ type=>allProductsRemind/ type=>sellersRemind, seller_ids='11,22'/  type=>productsRemind, product_ids='11,22']
    public function setAllProductsRemind($typeArr, $num)
    {
        $customer_id = $this->customer->getId();
        if ($typeArr['type'] == 'allProductsRemind') {
            return (int)$this->orm->table('tb_sys_buyer_storage')
                ->updateOrInsert(
                    ['buyer_id' => $customer_id],
                    ['remind_qty' => $num, 'create_time' => date('Y-m-d H:i:s')]
                );
        } elseif ($typeArr['type'] == 'sellersRemind') {
            $this->orm->getConnection()->beginTransaction();
            try {
                $row = $this->orm->table('tb_sys_seller_storage')
                    ->whereRaw("buyer_id={$customer_id}")
                    ->update(['remind_qty' => 0]);
                if ($typeArr['seller_ids'] != '0') {
                    $seller_ids = explode(',', $typeArr['seller_ids']);
                    foreach ($seller_ids as $seller_id) {
                        $row += (int)$this->orm->table('tb_sys_seller_storage')
                            ->updateOrInsert(
                                ['buyer_id' => $customer_id, 'seller_id' => $seller_id],
                                ['remind_qty' => $num, 'create_time' => date('Y-m-d H:i:s')]
                            );
                    }
                }
                $this->orm->getConnection()->commit();
            } catch (Exception $e) {
                $this->orm->getConnection()->rollBack();
                $row = -1;
            }
            return $row;
        } elseif ($typeArr['type'] == 'productsRemind') {
            $this->orm->getConnection()->beginTransaction();
            try {
                $row = $this->orm->table('oc_customer_wishlist')
                    ->whereRaw("customer_id={$customer_id}")
                    ->update(['remind_qty' => 0]);
                if ($typeArr['product_ids'] != '0') {
                    $row = (int)$this->orm->table('oc_customer_wishlist')
                        ->whereRaw("customer_id={$customer_id} AND product_id IN ({$typeArr['product_ids']})")
                        ->update(['remind_qty' => $num, 'date_added' => date('Y-m-d H:i:s')]);
                }
                $this->orm->getConnection()->commit();
            } catch (Exception $e) {
                $this->orm->getConnection()->rollBack();
                $row = -1;
            }
            return $row;
        } else {
            return -1;
        }
    }

    //有限数量 预计入库数量: 整理于-预计入库数量
    //测试$combo产品输入无数据。输入：'16956,17027,29473,19640,29164,30751,20481,15615,17511,10637,10653,10685'；输出 [15615 => '400', 20481 => '753',]
    // catalog/model/futures/template.php: 参考 public function getExpectedQty($productId)
    public function getProductsExpectedQty($product_id_str)
    {
        if (strlen($product_id_str) == 0) {
            return [];
        }
        $product_ids = explode(',', $product_id_str);
        $combo_product = $this->orm->table('oc_product')
            ->whereRaw("combo_flag=1 AND product_id IN ({$product_id_str})")
            ->selectRaw('product_id')
            ->get()->toArray();
        if (empty($combo_product)) {
            $combo_product = [];
        } else {
            $combo_product = array_column($combo_product, 'product_id');
        }

        //是组合SKU产品，获取子产品情况
        $expectedQty1 = [];
        if ($combo_product) {
            $childProducts = $this->orm->table('tb_sys_product_set_info')
                ->selectRaw('product_id,set_product_id,qty')
                ->whereRaw('product_id IN (' . implode(',', $combo_product) . ')')
                ->get();
            $combo_product_query = [];
            foreach ($childProducts as $k => $product) {
                //计算每一批可组成的combo数量
                $combo_product_query[] = $this->orm->table('tb_sys_receipts_order_detail as od')
                    ->leftJoin('tb_sys_receipts_order as o', 'od.receive_order_id', 'o.receive_order_id')
                    ->selectRaw("{$product->product_id} AS combo_product_id, {$product->qty} AS combo_qty, od.expected_qty, od.product_id, od.receive_order_id")
                    ->whereRaw('o.status=' . ReceiptOrderStatus::TO_BE_RECEIVED . ' AND od.product_id=' . $product->set_product_id); //o.status=ReceiptOrderStatus::TO_BE_RECEIVED：待收货
            }
            $query = $combo_product_query[0];
            for ($i = 1; $i < count($combo_product_query); $i++) {
                $query->unionAll($combo_product_query[$i]);
            }
            $combo_products = $query->get();
            $qty = [];
            foreach ($combo_products as $product) {
                $qty[$product->product_id][$product->receive_order_id][] = intval($product->expected_qty / $product->qty);
            }
            foreach ($qty as $product_id => $p) {
                $expectedQty1[$product_id] = 0;
                foreach ($p as $roId => $q) {
                    $expectedQty1[$product_id] += min($q); //合计每一批combo数量
                }
            }
        }
        $product_ids = array_diff($product_ids, $combo_product);
        //非组合SKU产品
        $expectedQty2 = $this->orm->table('tb_sys_receipts_order_detail as od')
            ->leftJoin('tb_sys_receipts_order as o', 'od.receive_order_id', 'o.receive_order_id')
            ->whereRaw('o.status=' . ReceiptOrderStatus::TO_BE_RECEIVED . ' AND od.product_id IN (' . implode(',', $product_ids) . ')')
            ->selectRaw('od.product_id, sum(od.expected_qty) as expected_qty')
            ->groupBy('od.product_id')
            ->get()->toArray();
        if (empty($expectedQty2)) {
            $expectedQty2 = [];
        } else {
            $expectedQty2 = array_combine(array_column($expectedQty2, 'product_id'), array_column($expectedQty2, 'expected_qty'));
        }
        return $expectedQty1 + $expectedQty2; //+保留键名 product_id
    }

    /** 批量产品佣金费率查询、计算
     * author: wyq
     * date: 2020.07.06
     */
    public function calculateProductsCommission($productData)
    {
        //分类佣金费率：全表
        //注意一个产品有多个分类，查表如现有61<7--2>1个
        $categoryCommission = $this->orm->table('oc_category AS c')
            ->leftJoin('oc_customerpartner_commission_category AS ccc', 'ccc.category_id', '=', 'c.category_id')
            ->selectRaw('c.category_id,c.parent_id,ifnull(ccc.percentage, 0) AS percentage,ifnull(ccc.fixed,0) AS fixed')
            ->get()->toArray();
        if ($categoryCommission) {
            $categoryCommissionId = array_combine(array_column($categoryCommission, 'category_id'), obj2array($categoryCommission));
        } else {
            $categoryCommissionId = [];
        }

        $categoryData = [];
        if ($this->config->get('marketplace_commissionworkedon')) {
            foreach ($productData as $product) {
                $category_ids = explode(',', $product['category_ids']);
                foreach ($category_ids as $category_id) {
                    isset($categoryCommissionId[$category_id]) && $categoryData[$product['product_id']][] = $categoryCommissionId[$category_id];
                }
            }
        } else {
            foreach ($productData as $product) {
                $category_ids = explode(',', $product['category_ids']);
                isset($categoryCommissionId[$category_ids[0]]) && $categoryData[$product['product_id']][] = $categoryCommissionId[$category_ids[0]];
            }
        }
        //店铺佣金
        $store_id_arr = array_unique(array_column($productData, 'customer_id'));
        $storeCommission = $this->orm->table('oc_customerpartner_to_customer')
            ->selectRaw('customer_id,commission')
            ->whereRaw("customer_id IN(" . implode(',', $store_id_arr) . ")")
            ->get()->toArray();
        if ($categoryCommission) {
            $storeCommission = array_combine(array_column($storeCommission, 'customer_id'), array_column($storeCommission, 'commission'));
        } else {
            $storeCommission = [];
        }
        //get commission array for priority
        $commission = $this->config->get('marketplace_boxcommission');
        if ($commission) {
            $product_commission = [];
            foreach ($productData as &$product) {
                $commission_amount = 0;
                $commission_type = '';
                //单个产品的多个分类的佣金率信息
                $categories = isset($categoryData[$product['product_id']]) ? $categoryData[$product['product_id']] : [];
                //基于主分类佣金（全场品类佣金） get all parent category according to product and process
                if (isset($categories[0]) && $categories[0]) {
                    foreach ($categories as $category_commission) {
                        if (isset($category_commission) && $category_commission['parent_id'] == 0) {
                            $commission_amount += ($category_commission['percentage'] ? ($category_commission['percentage'] * $product['price'] / 100) : 0) + $category_commission['fixed'];
                        }
                    }
                    $commission_type = 'Category Based';
                }
                //基于子分类佣金 get all child category according to product and process
                if ($commission_amount == 0 && isset($categories[0]) && $categories[0]) {
                    foreach ($categories as $category_commission) {
                        if (isset($category_commission) && $category_commission['parent_id'] > 0) {
                            $commission_amount += ($category_commission['percentage'] ? ($category_commission['percentage'] * $product['price'] / 100) : 0) + $category_commission['fixed'];
                        }
                    }
                    $commission_type = 'Category Child Based';
                }
                //基于店铺百分比佣金 just get all amount and process on that (precentage based)
                if ($commission_amount == 0) {
                    $customer_commission = isset($storeCommission[$product['customer_id']]) ? $storeCommission[$product['customer_id']] : [];
                    if ($customer_commission) {
                        $commission_amount += $customer_commission ? ($customer_commission * $product['price'] / 100) : 0;
                    }
                    $commission_type = 'Partner Fixed Based';
                }
                $product_commission[$product['product_id']] = [
                    'commission' => $commission_amount,
                    'customer' => $this->config->get('marketplace_commission_unit_price') ? $product['price'] : $product['price'] - $commission_amount,
                    'type' => $commission_type,
                ];
            }
            return $product_commission;
        }
        return [];
    }

    //获取订阅店铺列表
    public function getStoreInWish($field_str = 'c2c.customer_id,c2c.screenname', $order_raw = 'c2c.screenname ASC', $group_by = 'c2c.customer_id', $filter = [])
    {
        $customer_id = $this->customer->getId();
        $subQuery = db('oc_customer_wishlist')->select('product_id')->where('customer_id', $customer_id);
        $storeInWish = $this->orm->table('oc_customerpartner_to_product AS c2p')
            ->join('oc_customer_wishlist AS cw', function ($q) use ($customer_id) {
                $q->on('c2p.product_id', '=', 'cw.product_id')->where('cw.customer_id', (int)$customer_id);
            })
            ->leftJoin('oc_customerpartner_to_customer AS c2c', 'c2c.customer_id', '=', 'c2p.customer_id')
            ->selectRaw($field_str)
            ->whereRaw('c2c.is_partner=1')
            ->where(function ($query) use ($filter) {
                isset($filter['screenname']) && strlen($filter['screenname']) && $query->where('c2c.screenname', 'LIKE', $filter['screenname']);
            })
            ->whereIn('c2p.product_id', $subQuery)
            ->groupBy($group_by)
            ->orderByRaw($order_raw)
            ->get()
            ->toArray();
        $storeInWish = obj2array($storeInWish);
        foreach ($storeInWish as &$v) {
            $v['customer_id'] = $v['customer_id'] . '';
            $v['screenname'] = html_entity_decode($v['screenname']);
        }
        return $storeInWish;
    }

    private function getUserWishUnAvailableProductIds($customer_id)
    {
        if (!$customer_id) {
            return [];
        }
        //剔除无效的产品
        $UnAvailable = $this->orm->table('oc_delicacy_management_group AS dmg')
            ->join('oc_customerpartner_product_group_link AS pgl', 'pgl.product_group_id', '=', 'dmg.product_group_id')
            ->join('oc_customerpartner_buyer_group_link AS bgl', 'bgl.buyer_group_id', '=', 'dmg.buyer_group_id')
            ->selectRaw('pgl.product_id')
            ->whereRaw("dmg.status =1 and pgl.status=1 and bgl.status=1")
            ->whereRaw("bgl.buyer_id={$customer_id}")
            ->groupBy('pgl.product_id')
            ->unionAll( //精细化管理2，不显示价格
                $this->orm->table('oc_delicacy_management')
                    ->selectRaw('product_id')
                    ->whereRaw("buyer_id={$customer_id} AND product_display=0")
            )->get()->toArray();
        return $UnAvailable ? array_unique(array_column($UnAvailable, 'product_id')) : [];
    }

    //获取订阅店铺列表
    public function getProductSkuInWish($flag = '', $field_str = 'p.product_id,p.sku,d.name', $filter = [])
    {
        $customer_id = $this->customer->getId();
        $order_raw = 'p.sku ASC';
        $product_ids = $this->orm->table('oc_customer_wishlist')
            ->selectRaw('product_id')
            ->whereRaw("customer_id={$customer_id}")
            ->where(function ($query) use ($filter) {
                isset($filter['sku']) && strlen($filter['sku']) && $query->where('sku', 'LIKE', "{$filter['sku']}%");
                isset($filter['product_id']) && strlen($filter['product_id']) && $query->whereRaw($filter['product_id']);
            })
            ->get()->toArray();
        if (empty($product_ids)) {
            return [];
        }
        $product_ids = array_column(obj2array($product_ids), 'product_id');
        //无效产品时的返回
        if ($flag == 'unavailable') {
            $product_ids = array_intersect($product_ids, $this->getUserWishUnAvailableProductIds($customer_id));
        } else {
            $product_ids = array_diff($product_ids, $this->getUserWishUnAvailableProductIds($customer_id));
        }
        if (empty($product_ids)) {
            return [];
        }

        $productInWish = $this->orm->table('oc_product AS p')
            ->leftJoin('oc_product_description AS d', 'd.product_id', '=', 'p.product_id')
            ->selectRaw($field_str)
            ->whereRaw('p.product_id IN (' . implode(',', $product_ids) . ')')
            ->orderByRaw($order_raw)
            ->get()->toArray();
        $productInWish = obj2array($productInWish);
        foreach ($productInWish as &$v) {
            $v['product_id'] = $v['product_id'] . '';
            if (isset($v['name'])) {
                $v['name'] = html_entity_decode($v['name']);
            }
        }
        return $productInWish;
    }

    //处理心愿单分组列表
    public function getWishGroupCountList($group_count)
    {
        $customer_id = $this->customer->getId();
        $list = $this->orm->table('oc_wishlist_group')
            ->selectRaw('id,name')
            ->whereRaw("customer_id={$customer_id}")
            ->orderBy('create_time')->orderBy('id')
            ->get()->toArray();
        $list = obj2array($list);
        foreach ($list as $k => $item) {
            $list[$k]['group_id'] = $item['id'];
            $list[$k]['count'] = $group_count[$item['id']]['count'] ?? 0;
            $list[$k]['name'] = html_entity_decode($list[$k]['name']);
        }
        array_push($list, ['id' => 0, 'group_id' => 0, 'count' => $group_count[0]['count'] ?? 0, 'name' => 'ungrouped']);
        return $list;
    }

}
