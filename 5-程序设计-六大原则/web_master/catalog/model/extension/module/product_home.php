<?php

use App\Components\Storage\StorageCloud;
use App\Enums\Warehouse\ReceiptOrderStatus;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\DelicacyManagement\DelicacyManagement;
use App\Models\DelicacyManagement\DelicacyManagementGroup;
use App\Repositories\Product\ProductInfo\BaseInfo;
use App\Repositories\Product\ProductInfo\ProductPriceRangeFactory;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Seller\SellerRepository;
use App\Services\Product\ProductService;
use Carbon\Carbon;

/**
 * Class ModelExtensionModuleProductShow
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCustomerpartnerStoreRate $model_customerpartner_store_rate
 * @property ModelExtensionModuleProductShow $model_extension_module_product_show
 * @property ModelCustomerpartnerProfile $model_customerpartner_profile
 * @property ModelToolImage $model_tool_image
 * @property ModelCustomerpartnerSellerCenterIndex $model_customerpartner_seller_center_index
 */
class ModelExtensionModuleProductHome extends Model
{
    const INFORMATION_DEV = 133;
    const INFORMATION_PRO = 131;
    /**
     * @var \Yzc\freight
     */
    private $freightModel;

    //protected $seller_comprehensive_score_list;

    /**
     * ModelExtensionModuleProductHome constructor.
     * @param $registry
     * @throws Exception
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
        load()->model('tool/image');
        load()->model('customerpartner/store_rate');
        load()->model('extension/module/product_show');
        load()->model('customerpartner/profile');
        load()->model('catalog/product');
        load()->model('customerpartner/seller_center/index');
        $this->freightModel = new \Yzc\freight($registry);
    }

    /**
     * @param array $productIds
     * @param int $customerId
     * @param array $config
     * @return array
     * @throws Exception
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getHomeProductInfo(array $productIds, $customerId, $config = []): array
    {
        $config = array_merge([
            'isMarketingTimeLimit' => false, // 是否是限时限量活动
        ], $config);
        
        // 云送仓数据  整体返回数据
        $cwf_freight_all = $rtn = $dm = $receipt_array = [];
        $commission_amount = 0;
        $informationId = ENV_DROPSHIP_YZCM == 'pro' ? self::INFORMATION_PRO : self::INFORMATION_DEV;
        //获取预期入库的商品时间和数量
        if ($config['isMarketingTimeLimit']) {
            //限时限量活动
            $receipt_array = [];
        } else {
            $receipt_array = $this->model_catalog_product->getReceptionProduct();
        }
        // config 信息
        $customer_group_id = (int)$this->config->get('config_customer_group_id');
        $language_id = (int)$this->config->get('config_language_id');
        $store_id = (int)$this->config->get('config_store_id');
        $originalProductImage = $this->config->get('original_product_image');

        /** @var BaseInfo[] $baseInfos */
        /** @var ProductPriceRangeFactory $productPriceFactory */
        [$baseInfos, $productPriceFactory, $transactionPriceRange] = app(ProductRepository::class)
            ->getProductBaseInfosAndPriceRanges($productIds, $customerId, [
                'withProductComplexTag' => true,
                'withTransactionPriceRange' => $config['isMarketingTimeLimit'],
            ]);
        // 价格区间
        $priceLists = $productPriceFactory->getRanges();

        if ($customerId) {
            $dm = $this->getDelicacyManagementInfoByNoView($productIds, $customerId);
            $sql = $this->getHomeProductBuyerSql($productIds, $language_id, $store_id, $customer_group_id, $customerId);
        } else {
            $sql = $this->getNoBuyerSql($productIds, $language_id, $store_id, $customer_group_id);
        }
        // 批量产品的查询数据
        $query_all = $this->db->query($sql)->rows;
        if (!customer()->isCollectionFromDomicile()) {
            $cwf_freight_all = $this->freightModel->getFreightAndPackageFeeByProducts($productIds);
        }
        //download 验证
        $material_list = $this->getMaterial($productIds);
        //是否加入wishlist的记录
        $productWishList = $this->getWishListProduct($productIds, $customerId);
        //获取保证金产品的最高最低价
        $returnData = $this->getProductReturnRate($productIds);
        $seller_id_list = array_unique(array_column($query_all, 'seller_id'));
        $seller_return_approval_rate = $this->returnApprovalRate($seller_id_list);

        foreach ($query_all as $query) {
            $productId = $product_id = $query['productId'];
            $return_rate = '0.00';
            $return_rate_str = 'Low';
            $score = $return_approval_rate = $unsee = $allDaysSale = 0;
            $newSellerScore = $tax = $special = false;
            $price_status = [
                'Margin' => false,
                'Rebates' => false,
                'quote' => false,
                'Future' => false,
                'productQuote' => false,
            ];
            $comprehensive = ['seller_show' => 0];

            if ($query['buyer_flag'] == 0
                || $query['status'] == 0
                || (isset($dm[$product_id]['product_display']) && $dm[$product_id]['product_display'] == 0)
                || $query['seller_status'] == 0
            ) {
                $unsee = 1;
            }

            if ($unsee || !isset($baseInfos[$product_id])) {
                continue;
            }
            /**
             * @var BaseInfo $baseInfo
             */
            $baseInfo = $baseInfos[$product_id];
            $price = $query['price'];
            $freight = $query['freight'];
            //店铺退返率标签
            $store_return_rate_mark = $this->model_customerpartner_store_rate->returnsMarkByRate($query['returns_rate']);
            //店铺回复率标签
            $store_response_rate_mark = $this->model_customerpartner_store_rate->responseMarkByRate($query['response_rate']);
            //商品退返率
            if (isset($returnData[$product_id])) {
                $return_rate = $returnData[$product_id]['return_rate'];
                $return_rate_str = $returnData[$product_id]['return_rate_str'];
                $allDaysSale = $returnData[$product_id]['purchase_num'];
            }

            if (isset($seller_return_approval_rate[$query['seller_id']])) {
                $return_approval_rate = $seller_return_approval_rate[$query['seller_id']];
            }

            if (!isset($dm[$product_id])) {
                $dm[$product_id] = [];
            }

            if (in_array($query['seller_id'], PRODUCT_SHOW_ID) !== false) {
                if (isset($dm[$product_id]['current_price']) && $dm[$product_id] && $dm[$product_id]['product_display']) {
                    $delicacy_management_price = $dm[$product_id]['current_price'];
                } else {
                    $delicacy_management_price = $price;
                }
                $max_price = (float)$delicacy_management_price;
                $min_price = (float)$delicacy_management_price;


            } else {
                //2919/12/23  价格区间为货值
                //查询如果没有保证金或者是期货，直接不使用
                $complexTags = $baseInfo->getComplexTags();
                foreach ($complexTags as $tag) {
                    $price_status[$tag] = true;
                }
                if (isset($priceLists[$product_id])) {
                    $min_price = $priceLists[$product_id][0] ?? 0;
                    $max_price = $priceLists[$product_id][1] ?? 0;
                } else {
                    $min_price = 0;
                    $max_price = 0;
                }
            }
            if (customer()->getCountryId() == JAPAN_COUNTRY_ID) {
                $max_price = round($max_price);
                $min_price = round($min_price);
            }

            $image = StorageCloud::image()->getUrl($query['image'], [
                'w' => 260,
                'h' => 260,
                'check-exist' => isset($config['check-exist']) ? $config['check-exist'] : true,
            ]);
            // 260 兼容多处地方尺寸不一致的问题，取最大的
            /** @see \system\library\cart\tax.php calculate() */
            $price = $this->currency->format($this->tax->calculate(($query['discount'] ? $query['discount'] : $price), $query['tax_class_id'], $this->config->get('config_tax')), session('currency'));
            $max_price_show = $this->currency->format($this->tax->calculate(($query['discount'] ? $query['discount'] : $max_price), $query['tax_class_id'], $this->config->get('config_tax')), session('currency'));
            $min_price_show = $this->currency->format($this->tax->calculate(($query['discount'] ? $query['discount'] : $min_price), $query['tax_class_id'], $this->config->get('config_tax')), session('currency'));
            if ($this->config->get('config_tax')) {
                $tax = $this->currency->format((float)$query['special'] ? $query['special'] : ($query['discount'] ? $query['discount'] : $price) + $commission_amount, session('currency'));
            }
            //这个是download
            $materialShow = in_array($product_id, $material_list) == true ? 1 : 0;

            $verify = $this->getLoginInfo($query);

            $verify['arrival_available'] = 0;//显示More on the way 标签，显示标签则显示预计到达时间；再判断quantity_display=1则同时显示预计到达数量
            if (isset($receipt_array[$product_id]) && $receipt_array[$product_id] && $query['status']) {
                $query['dm_display'] = empty($dm[$product_id]) ? 1 : ($dm[$product_id]['product_display'] ?? 1);
                $verify = array_merge($verify, $this->model_extension_module_product_show->verifyCheck($query));
            }

            //查看该产品是否被订阅 edit by xxl
            $product_wish_status = in_array($product_id, $productWishList);
            $query['discount'] = sprintf('%.2f', $query['discount']);

            $isSellerSelf = $this->customer->getId() === $query['seller_id'];
            $feeList = [];
            if ($isSellerSelf || customer()->isCollectionFromDomicile()) {
                // seller 自己或上门取货用户
                $packageFee = $this->model_catalog_product->getNewPackageFee($product_id, true);
                $feeList[] = round((float)$packageFee, 2);
            }
            if ($isSellerSelf || !customer()->isCollectionFromDomicile()) {
                // seller 自己或一件代发用户
                $packageFee = $this->model_catalog_product->getNewPackageFee($product_id, false);
                $feeList[] = round(((float)$freight + (float)$packageFee), 2);
            }
            if (customer()->has_cwf_freight()
                && !($query['customer_group_id'] == 23
                    || in_array($query['seller_id'], array(340, 491, 631, 838))
                    || $query['product_type'])
            ) {
                //保证金店铺  ||in_array($query->row['seller_id'],array(694,696,746,907,908))
                // 云送仓
                $cwf_freight = $cwf_freight_all[$product_id] ?? [];
                if ($query['combo_flag']) {//是combo
                    $freight_fee_tmp = 0;
                    foreach ($cwf_freight as $tmp_k => $tmp_v) {
                        $freight_fee_tmp += ($tmp_v['freight'] + $tmp_v['package_fee']) * $tmp_v['qty'] + ($tmp_v['overweight_surcharge'] ?? 0);
                    }
                    $feeList[] = round($freight_fee_tmp, 2);
                } else {     //不是combo
                    $feeList[] = round((float)($cwf_freight['freight'] ?? 0) + (float)($cwf_freight['package_fee'] ?? 0) + (float)($cwf_freight['overweight_surcharge'] ?? 0), 2);
                }
            }
            if (count($feeList) > 0) {
                $minExtraFee = min($feeList);
                $maxExtraFee = max($feeList);
                if ($minExtraFee == $maxExtraFee) {
                    $extraFee = $this->currency->format($minExtraFee, session('currency'));
                } else {
                    $extraFee = ($this->currency->format($minExtraFee, session('currency')) . '-' . $this->currency->format($maxExtraFee, session('currency')));
                }
            } else {
                $extraFee = 0;
            }

            if (customer()->getId()) {
                $isOutNewSeller = app(SellerRepository::class)->isOutNewSeller($query['seller_id'], 3);
                $task_info = $this->model_customerpartner_seller_center_index->getSellerNowScoreTaskNumberEffective($query['seller_id']);
                if ($isOutNewSeller && !isset($task_info['performance_score'])) {
                    $newSellerScore = true;//评分显示 new seller
                } else {
                    $newSellerScore = false;
                    if ($task_info) {
                        $score = isset($task_info['performance_score']) ? number_format(round($task_info['performance_score'], 2), 2) : '0';
                    }
                }
            }
            // giga onsite
            $productService = app(ProductService::class);
            $return = [
                'is_new' => $query['is_new'],
                'information_id' => $informationId,
                'original_image' => $query['is_original_design'] ? $originalProductImage : '',
                'is_original_design' => $query['is_original_design'],
                'horn_mark' => ($query['is_new'] == 1) ? 'new' : '',//N-94 新品角标
                'loginId' => customer()->getId(),
                'screenname' => $query['screenname'],
                'store_code' => $query['store_code'],
                'score' => $score, // 此为5085初始的seller评分
                'comprehensive' => $comprehensive, // 此为5083初始的seller评分
                'new_seller_score' => $newSellerScore, // 18591增加外部新seller逻辑
                'material_show' => $materialShow,
                'extra_fee' => $extraFee,//$this->currency->format($extra_fee,session('currency')),
                'seller_status' => $query['seller_status'],
                'seller_accounting_type' => $query['seller_accounting_type'],
                'seller_id' => $query['seller_id'],
                'verify' => $verify,
                'comboFlag' => $query['combo_flag'],
                'buyer_flag' => $query['buyer_flag'],
                'unsee' => $unsee,
                'all_days_sale' => $allDaysSale,
                'return_rate' => $return_rate,
                'return_rate_str' => $return_rate_str,
                'store_return_rate_mark' => $store_return_rate_mark,
                'return_approval_rate' => $return_approval_rate,
                'store_response_rate_mark' => $store_response_rate_mark,
                'customer_id' => $query['customer_id'],
                'self_support' => $query['self_support'],
                'summary_description' => $query['summary_description'],
                'price_display' => $query['price_display'],
                'quantity_display' => $query['quantity_display'],
                'can_sell' => $query['canSell'] ? 1 : 0,    // bts 是否建立关联
                'seller_price' => $query['seller_price'],
                'quantity' => $query['c2pQty'],
                'product_id' => $query['productId'],
                'name' => $query['name'],
                'description' => utf8_substr(trim(strip_tags(html_entity_decode($query['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
                'meta_title' => $query['meta_title'],
                'meta_description' => $query['meta_description'],
                'meta_keyword' => $query['meta_keyword'],
                'tag' => $baseInfo->getShowTags(),
                'model' => $query['model'],
                'sku' => $query['sku'],
                'upc' => $query['upc'],
                'ean' => $query['ean'],
                'jan' => $query['jan'],
                'isbn' => $query['isbn'],
                'mpn' => $query['mpn'],
                'location' => $query['location'],
                'image' => $image,
                'thumb' => $image,
                'manufacturer_id' => $query['manufacturer_id'],
                'manufacturer' => $query['manufacturer'],
                'price' => ($query['discount'] ? $query['discount'] : $price) + $commission_amount,
                'max_price' => $max_price,
                'max_price_show' => $max_price_show,
                'min_price_show' => $min_price_show,
                'min_price' => $min_price,
                'special' => $special,
                'tax' => $tax,
                'points' => $query['points'],
                'tax_class_id' => $query['tax_class_id'],
                'date_available' => $query['date_available'],
                'weight' => $query['weight'],
                'weight_class_id' => $query['weight_class_id'],
                'length' => $query['length'],
                'width' => $query['width'],
                'height' => $query['height'],
                'length_class_id' => $query['length_class_id'],
                'subtract' => $query['subtract'],
                'minimum' => $query['minimum'],
                'sort_order' => $query['sort_order'],
                'status' => $query['status'],
                'date_added' => $query['date_added'],
                'date_modified' => $query['date_modified'],
                'productWishList' => $product_wish_status,
                'receipt' => isset($receipt_array[$product_id]) ? $receipt_array[$product_id] : null,
                'commission_amount' => $commission_amount,
                'href' => url()->to(['product/product', 'product_id' => $query['productId']]),
                'margin_status' => $price_status['Margin'],
                'rebate_status' => $price_status['Rebates'],
                'future_status' => $price_status['Future'],
                'viewed' => $query['viewed'],
                'dm_display' => empty($dm[$product_id]) ? 1 : ($dm[$product_id]['product_display'] ?? 1),
                'currency_symbol_left' => $this->currency->getSymbolLeft($this->session->get('currency', 'USD')),
                'currency_symbol_right' => $this->currency->getSymbolRight($this->session->get('currency', 'USD')),
                'is_giga_onsite' => $productService->checkIsGigaOnsiteProduct($query['productId']),
                'transactionPriceRange' => $transactionPriceRange[$productId] ?? [],
            ];
            unset($price_status);
            $rtn[] = $return;
        }
        return $rtn;
    }

    public function getLoginInfo($query): array
    {
        $verify = [
            'price_available' => 0,
            'qty_available' => 0,
            'arrival_available' => 0,
            'message' => '',
            'url' => '',
        ];

        //验证价格和数量是否可见以及
        if (customer()->getId()) {
            //已登录
            if (customer()->isPartner()) {
                //登录者是 Seller
                if (customer()->getId() == $query['customer_id']) {
                    //最高权限 是登录者自己的产品
                    $verify['price_available'] = 1;
                    $verify['qty_available'] = 1;
                    $verify['arrival_available'] = 1;
                }
            } else {
                //登录者是 Buyer
                if ($query['canSell'] != 0) {
                    $verify['price_available'] = 1;
                    $verify['qty_available'] = 1;
                } else {
                    if ($query['quantity_display'] == 1 && $query['price_display'] != 1) {
                        $verify['qty_available'] = 1;
                        $verify['message'] = 'Contact Seller to get the price';
                        $verify['url'] = $this->url->link('customerpartner/profile', ['id' => $query['customer_id'], 'itemCode' => $query['sku'], 'contact' => 1]);
                    } elseif ($query['quantity_display'] != 1 && $query['price_display'] == 1) {
                        $verify['price_available'] = 1;
                        $verify['message'] = 'Contact Seller to get the quantity available';
                        $verify['url'] = $this->url->link('customerpartner/profile', ['id' => $query['customer_id'], 'itemCode' => $query['sku'], 'contact' => 1]);
                    } elseif ($query['quantity_display'] != 1 && $query['price_display'] != 1) {
                        $verify['message'] = 'Contact Seller to get the price and quantity available';
                        $verify['url'] = $this->url->link('customerpartner/profile', ['id' => $query['customer_id'], 'itemCode' => $query['sku'], 'contact' => 1]);
                    } else {
                        $verify['price_available'] = 1;
                        $verify['qty_available'] = 1;
                    }
                }

            }

        } else {
            //未登录
            $verify['message'] = 'Login/Register to get the price and quantity available';
            $verify['url'] = $this->url->link('account/login');
        }

        return $verify;
    }

    public function returnApprovalRate($seller_id_list)
    {
        //加索引seller_id
        return  CustomerPartnerToCustomer::queryRead()
            ->whereIn('customer_id',$seller_id_list)
            ->select(['return_approval_rate','customer_id'])
            ->get()
            ->keyBy('customer_id')
            ->map(function ($v){
                return $v->return_approval_rate;
            })
            ->toArray();

    }

    public function getProductReturnRate($product_id_list)
    {
        $return_rate_standard = '10.00'; //产品退返品率标准
        return db('oc_product_crontab')
            ->whereIn('product_id', $product_id_list)
            ->select('purchase_num', 'return_rate')
            ->selectRaw(
                "case when return_rate > $return_rate_standard Then
                            'High'
                            when return_rate > '4.00' Then
                            'Moderate'
                            ELSE
                            'Low'  END as return_rate_str,product_id"
            )
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
    }

    public function getMaterial($product_id_list)
    {
        $arr_1 = db('oc_product_package_image')->whereIn('product_id', $product_id_list)
            ->groupBy('product_id')
            ->select('product_id')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        if (count($arr_1) == count($product_id_list)) {
            return $product_id_list;
        }
        $arr_2 = db('oc_product_package_file')->whereIn('product_id', $product_id_list)
            ->groupBy('product_id')
            ->select('product_id')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        $arr_3 = db('oc_product_package_video')->whereIn('product_id', $product_id_list)
            ->groupBy('product_id')
            ->select('product_id')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        return array_unique(array_merge(array_column($arr_1, 'product_id'), array_column($arr_2, 'product_id'), array_column($arr_3, 'product_id')));

    }

    public function getProductTagHtmlForThumb($product_id_list)
    {
        $tag_multi_array = $this->getTagAndPromotion($product_id_list);
        $tag_array = [];
        if (isset($tag_multi_array)) {
            load()->model('tool/image');
            foreach ($tag_multi_array as $tags) {
                if (isset($tags['icon']) && !empty($tags['icon'])) {
                    $tag_html = '<a data-toggle="tooltip" style="display: inline;" data-original-title="' . $tags['description'] . '"';
                    if (!empty($tags['link'])) {
                        $tag_html .= ' href = "' . $tags['link'];
                    }
                    $tag_html .= '">';

                    //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                    if ($tags['tag_type'] == 1) {
                        $class = $tags['class_style'];
                        $img_url = $this->model_tool_image->getOriginImageProductTags($tags['icon']);
                    } else {
                        $class = '';
                        $img_url = $this->model_tool_image->resize($tags['icon'], 15, 15);
                    }

                    $tag_html .= '<img class="' . $class . '" src="' . $img_url . '"/></a>';
                    $tag_array[$tags['product_id']][] = $tag_html;
                }
            }
        }
        return $tag_array;
    }

    public function getTagAndPromotion($product_id_list)
    {
        $sql = "SELECT ";
        $sql .= "  tag.description,
                  tag.icon,
                  tag.tag_id,
                  tag.class_style,
                  tag.sort_order,
                  NULL AS link,
                  1 AS tag_type,
                  p.product_id
                FROM
                  oc_product p
                  INNER JOIN oc_product_to_tag pt
                    ON p.product_id = pt.product_id
                  LEFT JOIN oc_tag tag
                    ON tag.tag_id = pt.tag_id
                WHERE tag.status = 1
                  AND p.product_id in  (" . implode(',', $product_id_list) . ")
                UNION ALL
                SELECT pro.name,pro.image,pro.promotions_id,2 AS class_style,pro.sort_order,pro.link,2 AS tag_type,pd.product_id FROM oc_promotions pro
                INNER JOIN oc_promotions_to_seller pts ON pts.promotions_id = pro.promotions_id
                INNER JOIN oc_customerpartner_to_product ctp ON ctp.customer_id = pts.seller_id
                INNER JOIN oc_promotions_description pd ON pd.product_id = ctp.product_id AND pd.promotions_id = pro.promotions_id
                WHERE pd.product_id in  (" . implode(',', $product_id_list) . ")
                AND pro.promotions_status = 1 AND pts.status = 1 ORDER BY tag_type,sort_order ASC";
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getWishListProduct($product_id_list, $customer_id)
    {
        $list = db(DB_PREFIX . 'customer_wishlist')
            ->whereIn('product_id', $product_id_list)
            ->where([
                'customer_id' => $customer_id,
            ])
            ->select('product_id')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        return array_column($list, 'product_id');
    }


    public function getHomeProductBuyerSql($product_id_list, $language_id, $store_id, $customer_group_id, $buyer_id)
    {
        //
        $productStr = implode(',', $product_id_list);
        if(!$productStr){
            $productStr = 0;
        }
        $sql = ' SELECT ';
        $sql .= " DISTINCT
                    c2c.screenname,c2c.customer_id as seller_id,c2c.performance_score,
                    cus.status as seller_status,cus.customer_group_id,cus.accounting_type AS seller_accounting_type,
                    TRIM(CONCAT(cus.firstname, cus.lastname)) AS store_code,
                    p.combo_flag,p.buyer_flag,
                    exts.is_original_design,
                    p.product_id AS productId,p.price,p.price_display,p.quantity_display,p.status,p.product_type,
                    p.sku,p.upc,p.ean,p.jan,p.isbn,p.mpn,p.location,p.quantity,p.image,p.manufacturer_id,p.viewed,p.model,p.tax_class_id,p.date_available,
                    p.weight,p.weight_class_id,p.length,p.length_class_id,p.width,p.height,p.points,
                    p.aHref,p.length_class_id,p.subtract,p.minimum,p.date_added,p.date_modified,p.freight,p.package_fee,
                    ifnull( c2c.self_support, 0 ) AS self_support,
                    c2c.returns_rate, c2c.response_rate,
                    pd.summary_description,pd.name,pd.description,pd.meta_title,pd.meta_description,pd.meta_keyword,pd.tag,
                    c2p.seller_price,c2p.customer_id,
                    c2p.quantity AS c2pQty,
                    p.buyer_flag,
                    CASE
                        when b2s.seller_id is not null
                        and b2s.buy_status = 1
                        and b2s.buyer_control_status = 1
                        and b2s.seller_control_status = 1
                        Then
                            b2s.id
                        ELSE
                            0
                        END  as canSell,

                    null as discount,
                    m.NAME AS manufacturer,
                    NULL AS special,
                    p.sort_order
                    , CASE
                        WHEN ro.receive_order_id IS NOT NULL AND DATEDIFF( Now( ), p.date_added ) <= " . NEW_ARRIVAL_DAY . "
                            THEN 1
                        ELSE 0
                        END
                        AS is_new
                FROM
                    oc_product p
                    LEFT JOIN oc_product_description pd ON ( p.product_id = pd.product_id )
                    LEFT JOIN oc_product_exts exts ON ( p.product_id = exts.product_id )
                    LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
                    LEFT JOIN oc_manufacturer m ON ( p.manufacturer_id = m.manufacturer_id )
                    LEFT JOIN oc_customerpartner_to_product c2p ON ( c2p.product_id = p.product_id )
                    LEFT JOIN oc_customerpartner_to_customer c2c ON ( c2p.customer_id = c2c.customer_id )
                    LEFT JOIN oc_customer cus ON ( c2p.customer_id = cus.customer_id )
                    LEFT JOIN oc_buyer_to_seller AS b2s ON (b2s.seller_id = c2p.customer_id AND b2s.buyer_id = " . $buyer_id . " )
                    LEFT JOIN tb_sys_receipts_order_detail AS rod ON rod.product_id = p.product_id
                    LEFT JOIN tb_sys_receipts_order AS ro ON ro.receive_order_id = rod.receive_order_id
                        AND ro.`expected_date` IS NOT NULL
                        AND rod.`expected_qty` IS NOT NULL
                        AND ro.`expected_date` > NOW()
                        AND ro.`status` =  " . ReceiptOrderStatus::TO_BE_RECEIVED . "
                WHERE
                    p.product_id in (" . $productStr . ")
                    AND c2c.`show` = 1
                    AND pd.language_id = $language_id
                    AND p.date_available <= NOW() ";
        return $sql;
    }

    public function getNoBuyerSql($product_id_list, $language_id, $store_id, $customer_group_id)
    {
        $productStr = implode(',', $product_id_list);
        if(!$productStr){
            $productStr = 0;
        }
        $sql = ' SELECT ';
        $sql .= " DISTINCT
                        c2c.screenname,c2c.customer_id as seller_id,
                        cus.status as seller_status,cus.customer_group_id,cus.accounting_type AS seller_accounting_type,
                        TRIM(CONCAT(cus.firstname, cus.lastname)) AS store_code,
                        p.combo_flag,p.buyer_flag,
                        exts.is_original_design,
                        p.product_id AS productId,p.price,p.price_display,p.quantity_display,p.status,p.product_type,
                        p.sku,p.upc,p.ean,p.jan,p.isbn,p.mpn,p.location,p.quantity,p.image,p.manufacturer_id,p.viewed,p.model,p.tax_class_id,p.date_available,
                        p.weight,p.weight_class_id,p.length,p.length_class_id,p.width,p.height,p.points,
                        p.aHref,p.length_class_id,p.subtract,p.minimum,p.date_added,p.date_modified,p.freight,p.package_fee,
                        ifnull( c2c.self_support, 0 ) AS self_support,
                        c2c.returns_rate, c2c.response_rate,
                        pd.summary_description,pd.name,pd.description,pd.meta_title,pd.meta_description,pd.meta_keyword,pd.tag,
                        c2p.seller_price,c2p.customer_id,
                        c2p.quantity AS c2pQty,
                        p.buyer_flag,
                        0 as canSell,
                        null as discount,
                        m.NAME AS manufacturer,
                        NULL AS special,
                        p.sort_order
                        , CASE
                            WHEN ro.receive_order_id IS NOT NULL AND DATEDIFF( Now( ), p.date_added ) <= " . NEW_ARRIVAL_DAY . "
                                THEN 1
                            ELSE 0
                            END
                            AS is_new
                    FROM
                        oc_product p
                        LEFT JOIN oc_product_description pd ON ( p.product_id = pd.product_id )
                        LEFT JOIN oc_product_exts exts ON ( p.product_id = exts.product_id )
                        LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
                        LEFT JOIN oc_manufacturer m ON ( p.manufacturer_id = m.manufacturer_id )
                        LEFT JOIN oc_customerpartner_to_product c2p ON ( c2p.product_id = p.product_id )
                        LEFT JOIN oc_customerpartner_to_customer c2c ON ( c2p.customer_id = c2c.customer_id )
                        LEFT JOIN oc_customer cus ON ( c2p.customer_id = cus.customer_id )
                        LEFT JOIN tb_sys_receipts_order_detail AS rod ON rod.product_id = p.product_id
                        LEFT JOIN tb_sys_receipts_order AS ro ON ro.receive_order_id = rod.receive_order_id
                            AND ro.`expected_date` IS NOT NULL
                            AND rod.`expected_qty` IS NOT NULL
                            AND ro.`expected_date` > NOW()
                            AND ro.`status` = " . ReceiptOrderStatus::TO_BE_RECEIVED . "
                    WHERE
                        p.product_id in (" . $productStr . ")
                        AND c2c.`show` = 1
                        AND pd.language_id = $language_id
                        AND p.date_available <= NOW() ";
        return $sql;
    }

    public function getDelicacyManagementInfoByNoView($product_id_list, $buyer_id)
    {
        if (empty($product_id_list) || empty($buyer_id)) {
            return null;
        }

        $dm_res = DelicacyManagement::query()
            ->whereIn('product_id', $product_id_list)
            ->where('buyer_id', $buyer_id)
            ->where('product_display', 0)
            ->where('expiration_time', '>', Carbon::now())
            ->selectRaw('product_id,product_display,current_price,price')
            ->get()
            ->toArray();

        $ids = DelicacyManagementGroup::query()->alias('dmg')
            ->leftJoinRelations(['BuyerGroupLink as bgl', 'ProductGroupLink as pgl'])
            ->whereIn('pgl.product_id', $product_id_list)
            ->where([
                'dmg.status' => 1,
                'pgl.status' => 1,
                'bgl.status' => 1,
                'bgl.buyer_id' => $buyer_id,
            ])
            ->groupBy(['pgl.product_id'])
            ->select('pgl.product_id')
            ->get()->pluck('product_id')->toArray();

        $list = [];

        foreach ($ids as $id) {
            $list[$id]['product_display'] = 0;
        }

        if ($dm_res) {
            foreach ($dm_res as $key => $value) {
                if (!isset($list[$value['product_id']])) {
                    $list[$value['product_id']]['product_display'] = $value['product_display'];
                    $list[$value['product_id']]['current_price'] = $value['current_price'];
                }
            }
        }

        return $list;
    }
}
