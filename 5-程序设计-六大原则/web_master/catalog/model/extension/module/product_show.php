<?php

use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Product\ProductType;
use App\Enums\Order\OcOrderStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Models\Warehouse\WarehouseInfo;
use App\Repositories\ProductLock\ProductLockRepository;
use App\Repositories\Product\ProductPriceRepository;
use App\Enums\Warehouse\ReceiptOrderStatus;
use App\Components\Storage\StorageCloud;
use App\Enums\Rebate\RebateAgreementResultEnum;
use App\Enums\Spot\SpotProductQuoteStatus;
use App\Models\Product\Product;
use App\Repositories\Seller\SellerRepository;

/**
 * Class ModelExtensionModuleProductShow
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCommonProduct $model_common_product
 * @property ModelCustomerpartnerStoreRate $model_customerpartner_store_rate
 * @property ModelCustomerpartnerSellerCenterIndex model_customerpartner_seller_center_index
 * @property ModelCustomerpartnerProfile $model_customerpartner_profile
 * @property ModelToolImage $model_tool_image
 */
class ModelExtensionModuleProductShow extends Model
{
    protected $product_id;
    protected $customer_id;
    /**
     * @var ModelCatalogProduct $catalog_product
     */
    private $catalog_product;
    /**
     * @var ModelToolImage $tool_image
     */
    private $tool_image;
    protected $country_id;
    protected $isCollectionFromDomicile;
    protected $is_partner;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->customer_id = $this->customer->getId();
        $this->load->model('catalog/product');
        $this->catalog_product = $this->model_catalog_product;
        $this->load->model('tool/image');
        $this->tool_image = $this->model_tool_image;
        $this->country_id = $this->customer->getCountryId();
        $this->isCollectionFromDomicile = $this->customer->isCollectionFromDomicile(); //是否是上门取货 上门取货不需要加运费
        $this->load->model('customerpartner/store_rate');
        $this->load->model('customerpartner/profile');
        $this->is_partner = $this->customer->isPartner();
    }


    /**
     * 有预计到货，判断页面上是否显示More on the way标签
     * 显示标签则显示预计到达时间；再判断quantity_display=1则同时显示预计到达数量
     * @param array $product
     * @return array
     */
    public function verifyCheck($product)
    {
        $arrival_available = 0;//是否显示预计到达标签
        $arrival_qty_show = 0;//是否显示预计到达数量
        if ($this->customer_id) {
            if ($product['seller_id'] == $this->customer_id) {
                //Seller自己的产品
                $arrival_available = 1;
                $arrival_qty_show = 1;
            } else {
                //Buyer
                if ($product['canSell']) {
                    //b2s建立联系
                    $arrival_available = 1;
                    $arrival_qty_show = 1;

                    //精细化是否可见|屏蔽
                    if (array_key_exists('dm_display', $product) && $product['dm_display'] == 0) {
                        $arrival_available = 0;
                        $arrival_qty_show = 0;
                    }
                } else {
                    //b2s未建立联系
                    $arrival_available = 0;
                    $arrival_qty_show = 0;
                    if ($product['quantity_display']) {
                        $arrival_available = 1;
                        $arrival_qty_show = 1;
                    }
                }
            }
        } else {
            //游客
            $arrival_available = 0;
            $arrival_qty_show = 0;
        }


        return [
            'arrival_available' => $arrival_available,
            'arrival_qty_show' => $arrival_qty_show
        ];
    }


    /**
     * [getIdealProductInfo description]    此方法无法支持批量查询产品信息，已经逐步弃用，可以使用 getHomeProductInfo代替
     * @param int $product_id
     * @param int $buyer_id
     * @param array $receipt_array
     * @return array | bool
     * @throws Exception
     * @see ModelExtensionModuleProductHome getHomeProductInfo()
     */
    public function getIdealProductInfo($product_id, $buyer_id, $receipt_array)
    {

        // 无法正确从数据库里面区分出来
        $informationId = 131;
        if (ENV_DROPSHIP_YZCM == 'pro') {
            $informationId = 133;
        }
        $commission_amount = 0;
        $customer_group_id = (int)$this->config->get('config_customer_group_id');
        $language_id = (int)$this->config->get('config_language_id');
        $store_id = (int)$this->config->get('config_store_id');
        $dm_info = null;
        if ($buyer_id) {
            $dm_info = $this->catalog_product->getDelicacyManagementInfoByNoView($product_id, $buyer_id);
            $sql = " SELECT DISTINCT
                        c2c.screenname,c2c.customer_id as seller_id,c2c.performance_score,
                        cus.status as seller_status,cus.customer_group_id,cus.accounting_type AS seller_accounting_type,
                        p.combo_flag,p.buyer_flag,
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
                        , CASE
                            WHEN ro.receive_order_id IS NOT NULL AND DATEDIFF( Now( ), p.date_added ) <= " . NEW_ARRIVAL_DAY . "
                                THEN 1
                            ELSE 0
                            END
                            AS is_new
                    FROM
                        oc_product p
                        LEFT JOIN oc_product_description pd ON ( p.product_id = pd.product_id )
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
                            AND ro.`status` = " . ReceiptOrderStatus::TO_BE_RECEIVED . "
                    WHERE
                        p.product_id = $product_id
                        AND c2c.`show` = 1
                        AND pd.language_id = $language_id
                        AND p.date_available <= NOW()
                        AND p2s.store_id = $store_id ";
        } else {
            $sql = " SELECT DISTINCT
                        c2c.screenname,c2c.customer_id as seller_id,c2c.performance_score,
                        cus.status as seller_status,cus.customer_group_id,cus.accounting_type AS seller_accounting_type,
                        p.combo_flag,p.buyer_flag,
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
                        , CASE
                            WHEN ro.receive_order_id IS NOT NULL AND DATEDIFF( Now( ), p.date_added ) <= " . NEW_ARRIVAL_DAY . "
                                THEN 1
                            ELSE 0
                            END
                            AS is_new
                    FROM
                        oc_product p
                        LEFT JOIN oc_product_description pd ON ( p.product_id = pd.product_id )
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
                        p.product_id = $product_id
                        AND c2c.`show` = 1
                        AND pd.language_id = $language_id
                        AND p.date_available <= NOW()
                        AND p2s.store_id = $store_id ";
        }
        $query = $this->db->query($sql);
        $returnData = $this->getProductReturnRate($product_id);//商品退返率
        $return_rate = $returnData['return_rate'];
        $return_rate_str = $returnData['return_rate_str'];
        $allDaysSale = $returnData['purchase_num'];
        $return_approval_rate = $this->catalog_product->returnApprovalRate($query->row['seller_id']);
        //// 获取seller店铺score
        //$scoreInfo = $this->model_customerpartner_profile->getSellerComprehensiveScore([$query->row['seller_id']],$this->country_id,true);
        //$score = isset($scoreInfo[$query->row['seller_id']]) ? $scoreInfo[$query->row['seller_id']]['score'] : 0;
        if ($query->num_rows) {
            /**
             * product 的当前价格
             * 如果 有精细化价格，则取该值(前提是该 buyer 对该 product 可见)。
             */
            $price = $query->row['price'];
            $freight = $query->row['freight'];
            $package_fee = $query->row['package_fee'];
            //14320
            //(1)价格区间的最小值是：Min（该商品历史成交最低价, 当前设置的原价, 阶梯价最小值, 精细化价格, 返点阶梯价最小值, 保证金阶梯价最小值, 期货阶梯价最小值, 打折的折后价）。
            //(2)价格区间的最大值是：Min（该商品历史成交最高价, 当前设置的原价, 阶梯价最大值, 精细化价格, 返点阶梯价最大值, 保证金阶梯价最大值, 期货阶梯价最大值, 即将涨价价格）。
            $discount = 1;

            // 国别 Email
            //外部产品 美国 bxw@gigacloudlogistics.com
            //内部产品 美国 bxo@gigacloudlogistics.com
            //内部产品 日本 nxb@gigacloudlogistics.com
            //内部产品 英国 UX_B@oristand.com
            //内部产品 德国 DX_B@oristand.com
            //
            //朱烨 Cecilia 9-30 20:54:39
            //国别 邮箱
            //德国 DE-SERVICE@oristand.com
            //日本 servicejp@gigacloudlogistics.com
            //英国 serviceuk@gigacloudlogistics.com
            //美国 service@gigacloudlogistics.com
            // 保证金店铺的价格直接取精细化价格不需要加运费 2019-12-11
            // 缩略图
            if (in_array($query->row['seller_id'], PRODUCT_SHOW_ID) !== false) {
                if ($this->isCollectionFromDomicile) {    // 上门取货
                    $freight_tmp = 0;
                } else {
                    //$freight_tmp = round($freight,4);
                    $freight_tmp = 0;
                }
                if ($dm_info && $dm_info['product_display'] && isset($dm_info['current_price'])) {
                    $delicacy_management_price = $dm_info['current_price'] + $freight_tmp;
                } else {
                    $delicacy_management_price = $price + $freight_tmp;
                }
                $max_price = (float)$delicacy_management_price * $discount + $commission_amount;
                $min_price = (float)$delicacy_management_price * $discount + $commission_amount;

            } else {
                //2919/12/23  价格区间为货值
                $max_price = (float)$this->getProductHighestPrice($price, $dm_info, $product_id, $discount, 0) + $commission_amount;
                $min_price = (float)$this->getProductLowestPrice($price, $dm_info, $product_id, $discount, 0) + $commission_amount;
            }
            if (isset($this->country_id) && $this->country_id == 107) {
                $max_price = round($max_price);
                $min_price = round($min_price);
            }
            //价格中出现历史成交价为0 需要交换 max 和 min 位置
            if ($max_price < $min_price) {
                list($max_price, $min_price) = [$min_price, $max_price];
            }
            // 获取价格，最高价 ，最低价
            //$max_price = min($common_price,$highest_price,$delicacy_management_price);
            //$min_price = min($common_price,$lowest_price,$delicacy_management_price);
            //14103 所有上过架的产品都可以被检索到，如果产品已失效，则在检索结果页和商品详情页标注出商品无效的原因
            $unsee = 0;
            if ($query->row['buyer_flag'] == 0) {
                $unsee = 1;
            } elseif ($query->row['status'] == 0) {
                $unsee = 1;
            } elseif ($dm_info && $dm_info['product_display'] == 0) {
                $unsee = 1;
            }

            $image = $this->dealWithImage($query->row['image']);

            if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				//#31737 商品详情页针Related Products对免税价调整
                if ($query->row['product_type'] == ProductType::NORMAL) {
                    $price = app(ProductPriceRepository::class)->getProductActualPriceByBuyer($query->row['seller_id'], customer()->getModel(), $price);
                    $max_price = app(ProductPriceRepository::class)->getProductActualPriceByBuyer($query->row['seller_id'], customer()->getModel(), $max_price);
                    $min_price = app(ProductPriceRepository::class)->getProductActualPriceByBuyer($query->row['seller_id'], customer()->getModel(), $min_price);
                }
                $price = $this->currency->format($this->tax->calculate(($query->row['discount'] ? $query->row['discount'] : $price), $query->row['tax_class_id'], $this->config->get('config_tax')), session('currency'));
                $max_price_show = $this->currency->format($this->tax->calculate(($query->row['discount'] ? $query->row['discount'] : $max_price), $query->row['tax_class_id'], $this->config->get('config_tax')), session('currency'));
                $min_price_show = $this->currency->format($this->tax->calculate(($query->row['discount'] ? $query->row['discount'] : $min_price), $query->row['tax_class_id'], $this->config->get('config_tax')), session('currency'));
            } else {
                $price = false;
                $max_price_show = false;
                $min_price_show = false;
            }

            if ((float)$query->row['special']) {
                $special = $this->currency->format($this->tax->calculate($query->row['special'], $query->row['tax_class_id'], $this->config->get('config_tax')), session('currency'));
            } else {
                $special = false;
            }


            if ($this->config->get('config_tax')) {
                $tax = $this->currency->format((float)$query->row['special'] ? $query->row['special'] : ($query->row['discount'] ? $query->row['discount'] : $price) + $commission_amount, session('currency'));
            } else {
                $tax = false;
            }

            //if ($this->config->get('config_review_status')) {
            //    $rating = (int)$query->row['rating'];
            //} else {
            //    $rating = false;
            //}


            $tag_array = $this->catalog_product->getProductTagHtmlForThumb($product_id);
            $materialShow = $this->catalog_product->getMaterial($product_id);

            $verify['price_available'] = 0;
            $verify['qty_available'] = 0;
            $verify['arrival_available'] = 0;
            $verify['message'] = '';
            $verify['url'] = '';
            //验证价格和数量是否可见以及
            if ($this->customer_id) {
                //已登录
                if ($this->is_partner) {
                    //登录者是 Seller
                    if ($this->customer_id == $query->row['customer_id']) {
                        //最高权限 是登录者自己的产品
                        $verify['price_available'] = 1;
                        $verify['qty_available'] = 1;
                        $verify['arrival_available'] = 1;
                    }
                } else {
                    //登录者是 Buyer
                    //登陆了不可见
                    if ($unsee == 1) {
                        $verify['message'] = 'The product is unavailable, you can contact seller for details.';
                        $verify['price_available'] = 0;
                        $verify['qty_available'] = 0;
                        $verify['arrival_available'] = 0;
                        $verify['url'] = $this->url->link('customerpartner/profile', '&id=' . $query->row['customer_id'] . '&itemCode=' . $query->row['sku'] . '&contact=1');
                    } else {
                        //available
                        if ($query->row['canSell'] != 0) {
                            $verify['price_available'] = 1;
                            $verify['qty_available'] = 1;
                            $verify['arrival_available'] = 0;
                        } else {
                            if ($query->row['quantity_display'] == 1 && $query->row['price_display'] != 1) {
                                $verify['price_available'] = 0;
                                $verify['qty_available'] = 1;
                                $verify['message'] = 'Contact Seller to get the price';
                                $url = $this->url->link('customerpartner/profile', '&id=' . $query->row['customer_id'] . '&itemCode=' . $query->row['sku'] . '&contact=1');
                            } elseif ($query->row['quantity_display'] != 1 && $query->row['price_display'] == 1) {
                                $verify['price_available'] = 1;
                                $verify['qty_available'] = 0;
                                $verify['message'] = 'Contact Seller to get the quantity available';
                                $verify['url'] = $this->url->link('customerpartner/profile', '&id=' . $query->row['customer_id'] . '&itemCode=' . $query->row['sku'] . '&contact=1');

                            } elseif ($query->row['quantity_display'] != 1 && $query->row['price_display'] != 1) {
                                $verify['price_available'] = 0;
                                $verify['qty_available'] = 0;
                                $verify['message'] = 'Contact Seller to get the price and quantity available';
                                $verify['url'] = $this->url->link('customerpartner/profile', '&id=' . $query->row['customer_id'] . '&itemCode=' . $query->row['sku'] . '&contact=1');

                            } else {
                                $verify['price_available'] = 1;
                                $verify['qty_available'] = 1;
                            }
                        }
                    }
                }

            } else {
                //未登录
                // unavailable
                if ($unsee == 1) {

                    $verify['message'] = 'The product is unavailable, you can contact seller for details.';
                    $verify['price_available'] = 0;
                    $verify['qty_available'] = 0;
                    $verify['arrival_available'] = 0;
                    $verify['url'] = $this->url->link('customerpartner/profile', '&id=' . $query->row['customer_id'] . '&itemCode=' . $query->row['sku'] . '&contact=1');

                } else {
                    //available
                    $verify['message'] = 'Login/Register to get the price and quantity available';
                    $verify['price_available'] = 0;
                    $verify['qty_available'] = 0;
                    $verify['arrival_available'] = 0;
                    $verify['url'] = $this->url->link('account/login', '', true);
                }
            }


            $verify['arrival_available'] = 0;//显示More on the way 标签，显示标签则显示预计到达时间；再判断quantity_display=1则同时显示预计到达数量
            if (isset($receipt_array[$product_id]) && $receipt_array[$product_id] && $query->row['status']) {
                $query->row['dm_display'] = empty($dm_info) ? 1 : ($dm_info['product_display'] ?? 1);
                $verify = array_merge($verify, $this->verifyCheck($query->row));
            }


            //查看该产品是否被订阅 edit by xxl
            $productWishList = $this->catalog_product->getWishListProduct($product_id, $this->customer_id);
            $query->row['discount'] = sprintf('%.2f', $query->row['discount']);

            /**
             * 打包费添加 附件打包费
             *
             * @since 101457
             */
            $package_fee_type = $this->isCollectionFromDomicile ? 2 : 1;
            $package_fee = $this->orm->table('oc_product_fee')
                ->where([
                    ['product_id', '=', $product_id],
                    ['type', '=', $package_fee_type]
                ])
                ->value('fee') ?: 0;

            if ($this->isCollectionFromDomicile) {
                $extra_fee = round($package_fee, 2);
                $extra_fee = $this->currency->format($extra_fee, session('currency'));
            } else {
                if ($this->customer->has_cwf_freight() && !($query->row['customer_group_id'] == 23 || in_array($query->row['seller_id'], array(340, 491, 631, 838)) || $query->row['product_type'])) {   //保证金店铺  ||in_array($query->row['seller_id'],array(694,696,746,907,908))
                    $cwf_freight_all = $this->freight->getFreightAndPackageFeeByProducts(array($product_id));
                    $cwf_freight = $cwf_freight_all[$product_id] ?? [];
                    $extra_fee_list[] = round(((float)$freight + (float)$package_fee), 2);
                    if ($query->row['combo_flag']) {//是combo
                        $freight_fee_tmp = 0;
                        foreach ($cwf_freight as $tmp_k => $tmp_v) {
                            $freight_fee_tmp += ($tmp_v['freight'] + $tmp_v['package_fee']) * $tmp_v['qty'] + ($tmp_v['overweight_surcharge'] ?? 0);
                        }
                        $extra_fee_list[] = round($freight_fee_tmp, 2);
                    } else {     //不是combo
                        $extra_fee_list[] = round(((float)($cwf_freight['freight'] ?? 0) + (float)($cwf_freight['package_fee'] ?? 0) + (float)($cwf_freight['overweight_surcharge'] ?? 0)), 2);
                    }
                    $min_extra_fee = min($extra_fee_list);
                    $max_extra_fee = max($extra_fee_list);
                    if ($min_extra_fee == $max_extra_fee) {
                        $extra_fee = $this->currency->format($min_extra_fee, session('currency'));
                    } else {
                        $extra_fee = ($this->currency->format($min_extra_fee, session('currency')) . '-' . $this->currency->format($max_extra_fee, session('currency')));
                    }
                } else {
                    $extra_fee = round(((float)$freight + (float)$package_fee), 2);
                    $extra_fee = $this->currency->format($extra_fee, session('currency'));
                }

            }

            //店铺评分--start
            $comprehensive = ['seller_show' => 0];
            $score = 0;
            $newSellerScore = false;
            if ($this->customer_id) {
                $isOutNewSeller = app(SellerRepository::class)->isOutNewSeller($query->row['customer_id'], 3);
                $this->load->model('customerpartner/seller_center/index');
                $task_info = $this->model_customerpartner_seller_center_index->getSellerNowScoreTaskNumberEffective($query->row['customer_id']);
                if ($isOutNewSeller && !isset($task_info['performance_score'])) {
                    $newSellerScore = true;//评分显示 new seller
                    $comprehensive = [
                        'seller_show' => 1,
                    ];
                } else {
                    $newSellerScore = false;
                    if ($task_info) {
                        $comprehensive = [
                            'seller_show' => 1,
                            'total' => isset($task_info['performance_score']) ? number_format(round($task_info['performance_score'], 2), 2) : '0'
                        ];
                        $score = $comprehensive['total'];
                    }
                }
            }
            //店铺评分--end

            //店铺退返率标签
            $store_return_rate_mark = $this->model_customerpartner_store_rate->returnsMarkByRate($query->row['returns_rate']);
            //店铺回复率标签
            $store_response_rate_mark = $this->model_customerpartner_store_rate->responseMarkByRate($query->row['response_rate']);


            $return = [
                'is_new' => $query->row['is_new'],
                'information_id' => $informationId,
                'horn_mark' => ($query->row['is_new'] == 1) ? 'new' : '',//N-94 新品角标
                'loginId' => $this->customer->getId(),
                'screenname' => $this->getDealScreenname(html_entity_decode($query->row['screenname'])),
                'material_show' => $materialShow,
                'extra_fee' => $extra_fee,//$this->currency->format($extra_fee,session('currency')),
                'seller_status' => $query->row['seller_status'],
                'seller_accounting_type' => $query->row['seller_accounting_type'],
                'seller_id' => $query->row['seller_id'],
                'verify' => $verify,
                'comboFlag' => $query->row['combo_flag'],
                'buyer_flag' => $query->row['buyer_flag'],
                'unsee' => $unsee,
                //'30Day'       => $day30Sale,
                'score' => $score,
                'comprehensive' => $comprehensive,
                'new_seller_score' => $newSellerScore,
                'all_days_sale' => $allDaysSale,
                'return_rate' => $return_rate,
                'return_rate_str' => $return_rate_str,
                'store_return_rate_mark' => $store_return_rate_mark,
                'return_approval_rate' => $return_approval_rate,
                'store_response_rate_mark' => $store_response_rate_mark,
                //'qa_rate'     => $qa_rate,
                'customer_id' => $query->row['customer_id'],
                'self_support' => $query->row['self_support'],
                'summary_description' => $query->row['summary_description'],
                'price_display' => $query->row['price_display'],
                'quantity_display' => $query->row['quantity_display'],
                //'aHref' => $query->row['aHref'],
                'can_sell' => $query->row['canSell'] ? 1 : 0,    // bts 是否建立关联
                'seller_price' => $query->row['seller_price'],
                'quantity' => $query->row['c2pQty'],
                'product_id' => $query->row['productId'],
                'name' => $query->row['name'],
                'description' => utf8_substr(trim(strip_tags(html_entity_decode($query->row['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
                'meta_title' => $query->row['meta_title'],
                'meta_description' => $query->row['meta_description'],
                'meta_keyword' => $query->row['meta_keyword'],
                'tag' => $tag_array,
                'model' => $query->row['model'],
                'sku' => $query->row['sku'],
                'upc' => $query->row['upc'],
                'ean' => $query->row['ean'],
                'jan' => $query->row['jan'],
                'isbn' => $query->row['isbn'],
                'mpn' => $query->row['mpn'],
                'location' => $query->row['location'],
                'stock_status' => $query->row['stock_status'],
                'image' => $image,
                'thumb' => $image,
                'manufacturer_id' => $query->row['manufacturer_id'],
                'manufacturer' => $query->row['manufacturer'],
                'price' => ($query->row['discount'] ? $query->row['discount'] : $price) + $commission_amount,
                'max_price' => $max_price,
                'max_price_show' => $max_price_show,
                'min_price_show' => $min_price_show,
                'min_price' => $min_price,
                'special' => $special,
                'tax' => $tax,
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
                'productWishList' => $productWishList,
                'receipt' => isset($receipt_array[$product_id]) ? $receipt_array[$product_id] : null,

                'commission_amount' => $commission_amount,
                'href' => $this->url->link('product/product', 'product_id=' . $query->row['productId']),
                'margin_status' => $this->getMarginStatus($query->row['productId']),
                'rebate_status' => $this->getRebateStatus($query->row['productId']),
                'future_status' => $this->getFutureStatus($query->row['productId']),
                'viewed' => $query->row['viewed'],
                'dm_display' => empty($dm_info) ? 1 : ($dm_info['product_display'] ?? 1),
                'currency_symbol_left' => $this->currency->getSymbolLeft(session('currency')),
                'currency_symbol_right' => $this->currency->getSymbolRight(session('currency')),
            ];
            return $return;
        } else {
            return false;
        }

    }

    /**
     * [getProductRealPrice description]
     * @param int $product_id
     * @return array
     * @throws Exception
     */
    public function getProductRealPrice($product_id)
    {
        $product_info = $this->orm->table(DB_PREFIX . 'product')->where('product_id', $product_id)
            ->select('price', 'freight')->first();
        $isCollectionFromDomicile = $this->isCollectionFromDomicile;
        $this->load->model('catalog/product');
        $dm_info = $this->model_catalog_product->getDelicacyManagementInfoByNoView($product_id, $this->customer_id);
        $unit_price = 0;
        if (isset($dm_info) && $dm_info['product_display'] == 1) {
            $unit_price = $dm_info['current_price'];
        } else {
            if ($isCollectionFromDomicile) {
                $unit_price = sprintf('%.2f', round($product_info->price, 2));
                if ($unit_price < 0) {
                    $unit_price = 0;
                }
            }
        }
        return $unit_price;


    }

    /**
     * [get_is_collection_from_domicile description]
     * @param int $buyer_id
     * @return boolean
     */
    function get_is_collection_from_domicile($buyer_id)
    {
        $map = [
            ['customer_id', '=', $buyer_id],
            ['status', '=', 1],
        ];
        $customer_group_id = $this->orm->table(DB_PREFIX . 'customer')->where($map)->value('customer_group_id');
        $isPartner = 0;
        if (in_array($customer_group_id, COLLECTION_FROM_DOMICILE) && !$isPartner) {
            return true;
        }
        return false;
    }


    /**
     * [getDealScreenname description]
     * @param $data
     * @return array
     */
    public function getDealScreenname($data)
    {
        $length = mb_strlen($data);
        if ($length > 23) {
            $data = mb_substr($data, 0, 20) . '...';
        }
        return $data;

    }

    /*
     * 获取各国对应的仓库代码
     * */
    public function getWarehouseCodeByCountryId($country_id = '')
    {
        if (empty($country_id)) {
            $country_id = $this->customer->getCountryId();
        }
        return $this->orm->table('tb_warehouses')
            ->where('country_id', $country_id)
            ->orderBy('WarehouseCode')
            ->pluck('WarehouseCode', 'WarehouseID')
            ->toArray();
    }

    /*
     * 商品库存分布情况
     * */
    public function getWarehouseQty($productId, $warehouse = [])
    {
        if (empty($warehouse)) {
            $warehouse = $this->getWarehouseCodeByCountryId();
        }
        $warehouseKey = array_keys($warehouse);
        //CA3临时使用
        //        $wareQty = $this->orm->table('tb_sys_warehouse_distribution')
        //            ->whereIn('warehouse_id', $warehouseKey)
        //            ->where('product_id', $productId)
        //            ->selectRaw('sum(stock_qty) as stock_qty,warehouse_id')
        //            ->groupBy('warehouse_id')
        //            ->get()
        //            ->toArray();
        $wareQty = $this->orm->table('tb_sys_warehouse_distribution')
            ->whereIn('warehouse_id', $warehouseKey)
            ->where('product_id', $productId)
            ->pluck('stock_qty', 'warehouse_id')
            ->toArray();

        return $wareQty;
    }

    /**
     * 以仓库适用的用户核算类型为划分标准
     * @param Product|int $product
     * @return array
     */
    public function getSellerTypeFromWarehouseAttribute($product)
    {
        if (!($product instanceof Product)) {
            $product = Product::find($product);
        }
        $seller_info = $product->customerPartner;

        switch ($seller_info->accounting_type) {//如果有新的核算类型，需要修改此处代码
            case 5:
                $seller_type = 'usNative';
                break;
            case 6:
                $seller_type = 'GIGA Onsite';
                break;
            default:
                $seller_type = 'normal';//all normal usNative //注释信息请参考tb_warehouses_to_attribute表seller_type字段
                break;
        }

        return [
            'country_id' => $seller_info->country_id,
            'accounting_type' => $seller_info->accounting_type ?? '',
            'seller_id' => $seller_info->customer_id,
            'seller_type' => $seller_type
        ];
    }


    /**
     * @param array $store_info_arr
     * @param array $seller_info getSellerTypeFromWarehouseAttribute()方法的返回值
     * @return array
     */
    private function processStoreInfoArr($store_info_arr = [], $seller_info = [])
    {
        $store_info = [];

        $seller_type = $seller_info['seller_type'];
        $seller_id = $seller_info['seller_id'];

        unset($vv);
        foreach ($store_info_arr as $kk => $vv) {
            //$vv['seller_assign']等于1的时候，已核算类型匹配权限，不用看tb_warehouse_to_seller表
            if ($vv['seller_assign']) {
                switch ($vv['seller_type']) {
                    case 'all':
                        $store_info[$vv['warehouse_code']] = $vv;
                        break;
                    default:
                        if ($vv['seller_type'] == $seller_type) {
                            $store_info[$vv['warehouse_code']] = $vv;
                        }
                        break;
                }
            } else {
                if ($vv['seller_ids'] == $seller_id) {
                    $store_info[$vv['warehouse_code']] = $vv;
                }
            }
        }
        unset($vv);

        return $store_info;
    }

    /**
     * 获取当前外部seller库存的对应值
     * @param int $product_id
     * @param int $seller_id
     * @param int $country_id
     * @return array
     */
    public function getOuterAccountWarehouseDistributionByProductId(int $product_id,int $seller_id,int $country_id): array
    {
        return WarehouseInfo::query()->alias('w')
            ->leftJoin('tb_sys_warehouse_batch as wb',function ($join) use ($product_id) {
                $join->on('wb.wh_id', '=', 'w.WarehouseID')
                    ->where('wb.product_id', '=', $product_id);
            })
            ->leftJoin('tb_warehouses_to_attribute AS wta', function ($join) {
                $join->on('wta.warehouse_id', '=', 'w.WarehouseID');
            })
            ->leftJoin('tb_warehouses_to_seller AS wts', function ($join) use ($seller_id) {
                $join->on('wts.warehouse_id', '=', 'wta.warehouse_id')
                    ->where('wts.seller_id', '=', $seller_id);
            })
            ->where('w.status', 1)
            ->where('w.country_id', $country_id)
            ->groupBy(['w.WarehouseID'])
            ->orderBy('w.WarehouseID')
            ->selectRaw(
                'COALESCE((CASE WHEN sum(wb.onhand_qty) < 0 THEN 0 ELSE sum(wb.onhand_qty) END),0) AS stock_qty,
                        w.WarehouseID as warehouse_id,
                        w.WarehouseCode as warehouse_code,
                        wta.seller_type,
                        wta.seller_assign,
                        GROUP_CONCAT(DISTINCT wts.seller_id ORDER BY wts.seller_id) AS seller_ids'
            )
            ->get()
            ->toArray();
    }

    /**
     * [getWarehouseDistributionByProductId description] 获取product_id 的库 //14408上门取货账号一键下载超大件库存分布列表
     * @param Product|int $product
     * @param array $productsComputeLockQtyMap
     * @return array
     * 必填字段        [ "warehouse_code"=>["stock_qty"=>"int 库存分布数量","warehouse_id"=>"int 仓库ID", "warehouse_code"=>"string code"], ...... ]
     * combo品子品返回 [ "warehouse_code"=>["stock_qty"=>"int 库存分布数量","warehouse_id"=>"int 仓库ID", "warehouse_code"=>"string code", "isComboShip"=>"int 用于标记Combo品的子产品在该仓库是否要取 出库时效"], ...... ]
     * @throws Exception
     */
    public function getWarehouseDistributionByProductId($product, $productsComputeLockQtyMap = [])
    {
        if (!($product instanceof Product)){
            $product = Product::find($product);
        }
        $product_id = $product->product_id;
        $seller_info = $this->getSellerTypeFromWarehouseAttribute($product);
        $seller_id = $seller_info['seller_id'];
        $accounting_type = $seller_info['accounting_type'];
        $country_id = $seller_info['country_id'];

        //判断是否为combo
        $comboInfo = db('tb_sys_product_set_info as s')
            ->where('s.product_id', $product_id)
            ->whereNotNull('s.set_product_id')
            ->select('s.set_product_id', 's.qty', 's.product_id')
            ->get()
            ->toArray();
        $arr = [];
        if (count($comboInfo)) {
            //查询出子sku的仓库的分布
            foreach ($comboInfo as $key => $value) {
                $child_product_id = $value->set_product_id;
                // 美国Outer Accounting 类型账号
                if ($accounting_type == CustomerAccountingType::OUTER && $country_id == AMERICAN_COUNTRY_ID) {
                    $store_info_arr = $this->getOuterAccountWarehouseDistributionByProductId($child_product_id,$seller_id,$this->country_id);
                }else{
                    $store_info_obj = db('tb_warehouses as w')
                        ->leftJoin('tb_sys_warehouse_distribution as d', function ($join) use ($child_product_id) {
                            $join->on('d.warehouse_id', '=', 'w.WarehouseID')
                                ->where('d.product_id', '=', $child_product_id);
                        })
                        ->leftJoin('tb_warehouses_to_attribute AS wta', function ($join) {
                            $join->on('wta.warehouse_id', '=', 'w.WarehouseID');
                        })
                        ->leftJoin('tb_warehouses_to_seller AS wts', function ($join) use ($seller_id) {
                            $join->on('wts.warehouse_id', '=', 'wta.warehouse_id')
                                ->where('wts.seller_id', '=', $seller_id);
                        })
                        ->where('w.country_id', $this->country_id)
                        ->where('w.status', 1)
                        ->whereNotNull('wta.seller_type')
                        ->groupBy('w.WarehouseID')
                        ->orderBy('WarehouseCode', 'asc')
                        ->selectRaw(
                            '(CASE WHEN d.`stock_qty` < 0 THEN 0 ELSE d.`stock_qty` END) AS stock_qty
                        ,w.WarehouseID as warehouse_id
                        ,w.WarehouseCode as warehouse_code
                        ,wta.seller_type
                        ,wta.seller_assign
                        ,GROUP_CONCAT(DISTINCT wts.seller_id ORDER BY wts.seller_id) AS seller_ids'
                        )
                        ->get()
                        ->toArray();
                    $store_info_arr = obj2array($store_info_obj);
                }
                $store_info = $this->processStoreInfoArr($store_info_arr, $seller_info);
                $comboInfo[$key]->list = $store_info;
                foreach ($store_info as $k => $v) {
                    $arr[$v['warehouse_id']][$value->set_product_id]['name'] = $v['warehouse_code'];
                    $arr[$v['warehouse_id']][$value->set_product_id]['id'] = $v['warehouse_id'];
                    $arr[$v['warehouse_id']][$value->set_product_id]['stock_qty'] = $v['stock_qty'];
                    $arr[$v['warehouse_id']][$value->set_product_id]['qty_set'] = $value->qty;
                }

            }
            $listMin = [];
            $store_info = [];
            foreach ($arr as $key => $value) {
                foreach ($value as $ks => $vs) {
                    $stock_qty = $vs['stock_qty'] ?? 0;
                    if ($vs['qty_set']) {
                        $listMin[] = floor($stock_qty / $vs['qty_set']);
                    } else {
                        $listMin[] = 0;
                    }
                }
                if ($listMin == null) {
                    $data = 0;
                } else {
                    $data = (int)min($listMin);
                }
                unset($listMin);
                $tmp['stock_qty'] = $data;
                $tmp['warehouse_id'] = $vs['id'];
                $tmp['warehouse_code'] = $vs['name'];
                $store_info[$tmp['warehouse_code']] = $tmp;
                //用于标记Combo品的子产品在某仓库是否要取 出库时效
                foreach ($value as $ks => $vs) {
                    if (!isset($store_info[$tmp['warehouse_code']]['isComboShip'])) {
                        $store_info[$tmp['warehouse_code']]['isComboShip'] = $vs['stock_qty'] ? 1 : 0;
                    } else {
                        if ($vs['stock_qty']) {
                            $store_info[$tmp['warehouse_code']]['isComboShip'] = $vs['stock_qty'] ? 1 : 0;
                        }
                    }
                }
            }

        } else {
            // Outer Accounting 类型账号
            if ($accounting_type == CustomerAccountingType::OUTER && $country_id == AMERICAN_COUNTRY_ID) {// 美国Outer Accounting 类型账号
                $store_info_arr = $store_info = $this->getOuterAccountWarehouseDistributionByProductId($product_id,$seller_id,$this->country_id);
            }else{
                $store_info_obj = db('tb_warehouses as w')
                    ->leftJoin('tb_sys_warehouse_distribution as d', function ($join) use ($product_id) {
                        $join->on('d.warehouse_id', '=', 'w.WarehouseID')
                            ->where('d.product_id', '=', $product_id);
                    })
                    ->leftJoin('tb_warehouses_to_attribute AS wta', function ($join) {
                        $join->on('wta.warehouse_id', '=', 'w.WarehouseID');
                    })
                    ->leftJoin('tb_warehouses_to_seller AS wts', function ($join) use ($seller_id) {
                        $join->on('wts.warehouse_id', '=', 'wta.warehouse_id')
                            ->where('wts.seller_id', '=', $seller_id);
                    })
                    ->where('w.country_id', $this->country_id)
                    ->where('w.status', 1)
                    ->whereNotNull('wta.seller_type')
                    ->groupBy('w.WarehouseID')
                    ->orderBy('WarehouseCode', 'asc')
                    ->selectRaw(
                        '(CASE WHEN d.`stock_qty` < 0 THEN 0 ELSE d.`stock_qty` END) AS stock_qty
                    ,w.WarehouseID as warehouse_id
                    ,w.WarehouseCode as warehouse_code
                    ,wta.seller_type
                    ,wta.seller_assign
                    ,GROUP_CONCAT(DISTINCT wts.seller_id ORDER BY wts.seller_id) AS seller_ids'
                    )
                    ->get();
                $store_info_arr = obj2array($store_info_obj);
            }

            $store_info = $this->processStoreInfoArr($store_info_arr, $seller_info);
        }

        $lockNum = $productsComputeLockQtyMap[$product->product_id] ?? app(ProductLockRepository::class)->getProductComputeLockQty($product);

        return $this->stockDistributionAfterLock($store_info, $lockNum);
    }

    /**
     * 中源的产品分仓显示库存时，取CA2-ZY的库存数据显示在前台的CA2仓库中。中源的seller编号：W 501
     * @param int $product_id
     * @param string $item_code sku
     * @return array
     */
    public function getWarehouseDistributionSpecial(int $product_id, string $item_code): array
    {
        $res = $this->orm->table('tb_sys_warehouse_distribution as d')
            ->select('d.stock_qty')
            ->where([
                ['d.product_id', '=', $product_id],
                ['d.item_code', '=', $item_code],
                ['d.seller_id', '=', 3222],
                ['d.warehouse_id', '=', 70],
            ])
            ->first();
        return obj2array($res);
    }

    public function distribution($warehouse, $lockNum)
    {
        if (!$lockNum) {
            return $warehouse;
        }
        $ware = [];
        foreach ($warehouse as $k => $v) {
            if ($v['stock_qty']) {
                $ware[] = $v;
            }
        }
        $count = count($ware);
        if (!$count) {
            return $warehouse;
        }
        $perLock = ceil($lockNum / $count);
        $lastLock = $lockNum - $perLock * ($count - 1);
        for ($i = 0; $i < $count - 1; $i++) {
            $perLockArr[] = $perLock;
        }
        $perLockArr[] = $lastLock;

        $lastLockNum = 0;
        $maxItem = 0;
        $maxQty = 0;
        foreach ($ware as $item => &$value) {
            if ($value['stock_qty'] >= $perLockArr[$item]) {
                $value['stock_qty'] -= $perLockArr[$item];
            } else {
                $lastLockNum += $perLockArr[$item] - $value['stock_qty'];
                $value['stock_qty'] = 0;
            }

            if ($maxQty < $value['stock_qty']) {
                $maxQty = $value['stock_qty'];
                $maxItem = $item;
            }
        }
        if (1 == $lastLockNum && $ware[$maxItem]['stock_qty']) {
            $ware[$maxItem]['stock_qty'] -= 1;
        }
        if ($lastLockNum > 1) {
            return $this->distribution($ware, $lastLockNum);
        } else {
            return $ware;
        }
    }

    public function stockDistributionAfterLock($warehouse, $lockNum)
    {
        $ware = $this->distribution($warehouse, $lockNum);
        $wareKV = [];
        if (!empty($ware)) {
            foreach ($ware as $kk => $vv) {
                $wareKV[$vv['warehouse_id']] = $vv['stock_qty'];
            }
        }
        foreach ($warehouse as &$value) {
            $value['stock_qty'] = $wareKV[$value['warehouse_id']] ?? 0;
        }
        unset($value);
        return $warehouse;
    }

    /**
     * [getProductHighestPrice description]
     * @param $default_price
     * @param $dm_info
     * @param int $product_id
     * @param $discountResult
     * @param $freight
     * @return float
     */
    public function getProductHighestPrice($default_price, $dm_info, $product_id, $discountResult, $freight)
    {
        // $default_price oc_product price
        //获取历史成交的最高价和最低价
        $is_zero = sprintf('%.4f', $default_price);
        //验证是否是上门取货的buyer
        //上门取货取原价 一件代发 + 运费

        if ($is_zero == '0.0000') {
            $default_price = 0;
        }

        if ($dm_info && $dm_info['product_display'] && isset($dm_info['current_price'])) {
            $delicacy_management_price = $dm_info['current_price'];
        } else {
            $delicacy_management_price = $default_price;
        }

        $margin_price = $this->getMarginPrice($product_id);
        if (!$margin_price) {
            $margin_price = $default_price;
        }
        //返点的需要先减运费，再进行乘折扣
        $rebate_price = $this->getRebatePrice($product_id, $freight);
        if (!$rebate_price) {
            $rebate_price = $default_price + $freight;
        }

        //阶梯价格
        $quote_price = $this->getQuotePrice($product_id);
        if (!$quote_price) {
            $quote_price = $default_price;
        }

        // 议价价格
        $product_quote_price = $this->getProductQuotePrice($product_id, $this->customer_id);
        if (!$product_quote_price) {
            $product_quote_price = $default_price;
        }

        // 期货保证金价格
        $futures_margin_price = $this->getFuturesMarginPrice($product_id);
        if (!$futures_margin_price) {
            $futures_margin_price = $default_price;
        }
        $arr = [
            (float)($default_price + $freight) * $discountResult < 0 ? 0 : (float)($default_price + $freight) * $discountResult,
            //(float)$highest_price*$discountResult,
            (float)($delicacy_management_price + $freight) * $discountResult < 0 ? 0 : (float)($delicacy_management_price + $freight) * $discountResult,
            (float)($margin_price + $freight) * $discountResult < 0 ? 0 : (float)($margin_price + $freight) * $discountResult,
            (float)$rebate_price * $discountResult,
            (float)$product_quote_price * $discountResult,
            (float)($quote_price + $freight) * $discountResult < 0 ? 0 : (float)($quote_price + $freight) * $discountResult,
            (float)($futures_margin_price + $freight) * $discountResult < 0 ? 0 : (float)($futures_margin_price + $freight) * $discountResult,
        ];

        $tmp = array_filter($arr);
        if (null != $tmp) {
            $max_price = max($tmp);
        } else {
            $max_price = 0;
        }
        return round($max_price, 2);


    }

    public function getMarginServiceProductId($product_id)
    {
        $this->orm->table('tb_sys_margin_process as mp')
            ->leftJoin('tb_sys_margin_agreement as a', 'a.id', '=', 'mp.margin_id')
            ->where('a.product_id', $product_id)
            ->get();

    }

    /**
     * [getRmaInfo description] 此方法已弃用，无法包含
     * @param int $product_id
     * @return float|int
     */
    public function getRmaInfo($product_id)
    {
        $purchase_order =
            $this->orm->table(DB_PREFIX . 'order_product as op')
                ->leftJoin(DB_PREFIX . 'order as o', 'o.order_id', '=', 'op.order_id')
                //->where($mapAll)
                ->whereIn('o.order_status_id', [OcOrderStatus::COMPLETED, OcOrderStatus::CHARGEBACK]) //2/10 计算销量需控制订单状态为5,13
                ->where('op.product_id', $product_id)
                ->selectRaw('ifnull(sum(quantity),0) as data')->first();
        if ($purchase_order->data == 0) {
            $return_rate = 0;
        } else {
            // 只统计针对Completed 销售订单的退返品
            // 采购订单的退款 不计算数量
            $mapChild = [
                ['op.product_id', '=', $product_id],
                ['ro.cancel_rma', '=', 0],
                //['o.order_status_id','>=',OcOrderStatus::COMPLETED], // oc_order 状态限制
                ['op.rma_type', '!=', 2] // 2 仅退款
            ];
            $res = $this->orm->table(DB_PREFIX . 'yzc_rma_order_product as op')
                ->leftJoin(DB_PREFIX . 'yzc_rma_order as ro', 'ro.id', '=', 'op.rma_id')
                ->leftJoin('oc_order_product as oop', 'oop.order_id', '=', 'ro.order_id')
                ->leftJoin(DB_PREFIX . 'order as o', 'o.order_id', '=', 'oop.order_id')
                ->whereIn('o.order_status_id', [OcOrderStatus::COMPLETED, OcOrderStatus::CHARGEBACK])
                ->where($mapChild)->whereNull('ro.from_customer_order_id')
                ->groupBy('ro.order_id')->selectRaw('case when sum(op.quantity) > oop.quantity then oop.quantity else sum(op.quantity) end as quantity,ro.order_id')->get();
            $res_1 = obj2array($res);
            // 有销售订单的rma
            $mapOrderChild = [
                ['op.product_id', '=', $product_id],
                ['ro.cancel_rma', '=', 0],
                //['o.order_status_id','>=',OcOrderStatus::COMPLETED], // oc_order 状态限制
                ['so.order_status', '=', CustomerSalesOrderStatus::COMPLETED], // oc_order 状态限制
            ];
            $res_2 = $this->orm->table(DB_PREFIX . 'yzc_rma_order_product as op')
                ->leftJoin(DB_PREFIX . 'yzc_rma_order as ro', 'ro.id', '=', 'op.rma_id')
                ->leftJoin('tb_sys_customer_sales_order as so', [['ro.from_customer_order_id', '=', 'so.order_id'], ['so.buyer_id', '=', 'ro.buyer_id']])
                ->leftJoin(DB_PREFIX . 'product as p', 'op.product_id', '=', 'p.product_id')
                ->leftJoin('tb_sys_customer_sales_order_line as l', [['l.header_id', '=', 'so.id'], ['l.item_code', '=', 'p.sku']])
                ->leftJoin('oc_order_product as oop', 'oop.order_id', '=', 'ro.order_id')
                ->leftJoin(DB_PREFIX . 'order as o', 'o.order_id', '=', 'oop.order_id')
                ->whereIn('o.order_status_id', [OcOrderStatus::COMPLETED, OcOrderStatus::CHARGEBACK])
                ->where($mapOrderChild)->whereNotNull('ro.from_customer_order_id')
                ->groupBy('ro.from_customer_order_id')->selectRaw('case when sum(op.quantity) > l.qty then l.qty else sum(op.quantity) end as quantity,ro.order_id')->get();
            $res_2 = obj2array($res_2);
            $res = array_merge($res_1, $res_2);
            $qty = array_column($res, 'quantity');
            $rma_qty = array_sum($qty);
            $return_rate = sprintf('%.2f', $rma_qty * 100 / $purchase_order->data);
        }
        return $return_rate;

    }

    /**
     * [getProductLowestPrice description]
     * @param $default_price
     * @param $dm_info
     * @param int $product_id
     * @param $discountResult
     * @param $freight
     * @return float
     */
    public function getProductLowestPrice($default_price, $dm_info, $product_id, $discountResult, $freight)
    {
        // $default_price oc_product price
        //获取历史成交的最高价和最低价
        $is_zero = sprintf('%.4f', $default_price);
        //验证是否是上门取货的buyer
        //上门取货取原价 一件代发 + 运费
        if ($is_zero == '0.0000') {
            $default_price = 0;
        }

        if ($dm_info && $dm_info['product_display'] && isset($dm_info['current_price'])) {
            $delicacy_management_price = $dm_info['current_price'];
        } else {
            $delicacy_management_price = $default_price;
        }

        $margin_price = $this->getMarginPrice($product_id, 'min');
        if (!$margin_price) {
            $margin_price = $default_price;
        }

        //返点的需要先减运费，再进行乘折扣
        $rebate_price = $this->getRebatePrice($product_id, $freight, 'min');
        if (!$rebate_price) {
            $rebate_price = $default_price + $freight;
        }

        //阶梯价格
        $quote_price = $this->getQuotePrice($product_id, 'min');
        if (!$quote_price) {
            $quote_price = $default_price;
        }

        // 议价价格
        $product_quote_price = $this->getProductQuotePrice($product_id, $this->customer_id, 'min');
        if (!$product_quote_price) {
            $product_quote_price = $default_price;
        }

        // 期货保证金价格
        $futures_margin_price = $this->getFuturesMarginPrice($product_id, 'min');
        if (!$futures_margin_price) {
            $futures_margin_price = $default_price;
        }

        $arr = [
            (float)($default_price + $freight) * $discountResult < 0 ? 0 : (float)($default_price + $freight) * $discountResult,
            //(float)$highest_price*$discountResult,
            (float)($delicacy_management_price + $freight) * $discountResult < 0 ? 0 : (float)($delicacy_management_price + $freight) * $discountResult,
            (float)($margin_price + $freight) * $discountResult < 0 ? 0 : (float)($margin_price + $freight) * $discountResult,
            (float)$rebate_price * $discountResult,
            (float)$product_quote_price * $discountResult,
            (float)($quote_price + $freight) * $discountResult < 0 ? 0 : (float)($quote_price + $freight) * $discountResult,
            (float)($futures_margin_price + $freight) * $discountResult < 0 ? 0 : (float)($futures_margin_price + $freight) * $discountResult,
        ];
        $tmp = array_filter($arr);
        if (null != $tmp) {
            $min_price = min($tmp);
        } else {
            $min_price = 0;
        }
        return round($min_price, 2);

    }

    /*
     * 商品退返率，及其对应等级
     * */
    public function getProductReturnRate($productId)
    {
        // 改这边条件记得修改App\Repositories\Product\ProductInfo\BaseInfo::getReturnRate()
        $return_rate_standard = '10.00'; //产品退返品率标准
        $data = $this->orm->table('oc_product_crontab')
            ->where('product_id', $productId)
            ->select('purchase_num', 'return_rate')
            ->first();
        $data = obj2array($data);
        if (empty($data)) {
            $data['purchase_num'] = 0;
            $data['return_rate'] = '0.00';
        }
        $data['return_rate'] = (string)$data['return_rate'];
        if ($data['return_rate'] > $return_rate_standard) {
            $data['return_rate_str'] = 'High';
        } elseif ($data['return_rate'] > '4.00') {
            $data['return_rate_str'] = 'Moderate';
        } else {
            $data['return_rate_str'] = 'Low';
        }
        return $data;
    }

    /**
     * [getMarginStatus description]
     * @param int $product_id
     * @return bool
     */
    public function getMarginStatus($product_id)
    {
        $map = [
            'product_id' => $product_id,
            'is_del' => 0,
        ];
        return $this->orm->table('tb_sys_margin_template')->where($map)->exists();
    }


    public function getFutureStatus($product_id)
    {
        $map = [
            'product_id' => $product_id,
            'status' => 1,
            'is_deleted' => 0,
        ];
        return $this->orm->table('oc_futures_contract')
            ->where($map)
            ->exists();
    }

    /**
     * 返点四期
     * @param int $product_id
     * @return mixed
     */
    public function getRebateStatus($product_id)
    {
        $sql = "SELECT COUNT(*) cnt
    FROM oc_rebate_template_item rti
    LEFT JOIN oc_rebate_template rt ON rt.id=rti.template_id
    WHERE rti.product_id={$product_id}
        AND rti.is_deleted=0
        AND rt.is_deleted=0";
        $query = $this->db->query($sql);
        return $query->row['cnt'];
    }

    /**
     * [getRebatePrice description] 返点模板更改表
     * @param int $product_id
     * @param $freight
     * @param string $default
     * @return string|int
     */
    public function getRebatePrice($product_id, $freight, $default = 'max')
    {
        $sort = $default == 'max' ? 'asc' : 'desc';
        $map = [
            'i.product_id' => $product_id,
            't.is_deleted' => 0,
            'i.is_deleted' => 0,
        ];
        $price = $this->orm->table(DB_PREFIX . 'rebate_template_item as i')
            ->leftJoin(DB_PREFIX . 'rebate_template as t', 'i.template_id', '=', 't.id')
            ->where($map)
            ->orderByRaw('i.price - i.rebate_amount', $sort)
            ->select('i.price', 'i.rebate_amount', 'i.product_id')
            ->first();
        if($this->customer_id){
            $mapAgreement = [
                't.buyer_id' => $this->customer_id,
                't.status' => 3,
                'i.product_id' => $product_id,
            ];
            $agreement_price = $this->orm->table(DB_PREFIX . 'rebate_agreement_item as i')
                ->leftJoin(DB_PREFIX . 'rebate_agreement as t', 't.id', '=', 'i.agreement_id')
                ->where($mapAgreement)
                ->whereIn('t.rebate_result', [
                    RebateAgreementResultEnum::__DEFAULT,
                    RebateAgreementResultEnum::ACTIVE,
                    RebateAgreementResultEnum::DUE_SOON,
                ])
                ->orderByRaw('i.template_price - i.rebate_amount', $sort)
                ->select('i.template_price', 'i.rebate_amount', 'i.product_id')
                ->first();
        }

        if (isset($price->price)) {
            $real_price = sprintf('%.2f', $price->price - $price->rebate_amount + $freight);
            if ($real_price < 0) {
                $real_price =  0;
            }
        } else {
            $real_price =  0;
        }

        if (isset($agreement_price->template_price) && isset($price->rebate_amount)) {
            $calc_price = sprintf('%.2f', $agreement_price->template_price - $price->rebate_amount + $freight);
            if ($calc_price < 0) {
                $calc_price =  0;
            }
        } else {
            $calc_price =  0;
        }

        if ($default == 'max') {
            return max([$real_price, $calc_price]);
        } else {
            // 判断是否为null
            if ($real_price && $calc_price) {
                return min([$real_price, $calc_price]);
            } else {
                if ($real_price) {
                    return $real_price;
                }
                return $calc_price;
            }
        }
    }

    public function getCommonPerformer($buyer_id)
    {
        $agreement_list = $this->orm->table('oc_agreement_common_performer')->where('buyer_id', $buyer_id)
            ->groupBy('agreement_id')
            //->select('agreement_id')
            ->get()
            ->pluck('agreement_id');
        return obj2array($agreement_list);
    }

    public function getMarginPrice($product_id, $default = 'max')
    {

        $sort = $default == 'max' ? 'desc' : 'asc';
        $mapTemplate = [
            ['product_id', '=', $product_id],
            ['is_del', '=', 0],
        ];
        $template_price = $this->orm->table('tb_sys_margin_template')->where($mapTemplate)->orderBy('price', $sort)->value('price');
        // 获取共同的履约人
        $agreement_list = $this->getCommonPerformer($this->customer_id);
        $map = [
            ['product_id', '=', $product_id],
            ['expire_time', '>', date('Y-m-d H:i:s')],
        ];
        $query = $this->orm->table('tb_sys_margin_agreement');
        //if($agreement_list){
        $query = $query->whereIn('id', $agreement_list);
        $query = $query->where($map)->whereIn('status', [3, 6]);
        //}

        $price = $query->orderByRaw('price - deposit_per', $sort)->selectRaw('price - deposit_per as price,id')->first();
        $after_price = $price->price ?? null;
        if ($default == 'max') {
            return max([$template_price, $after_price]);
        } else {
            // 判断是否为null
            if ($template_price && $after_price) {
                return min([$template_price, $after_price]);
            } else {
                if ($template_price) {
                    return $template_price;
                }
                return $after_price;
            }

        }
    }

    public function getQuotePrice($product_id, $default = 'max')
    {
        $sort = $default == 'max' ? 'desc' : 'asc';
        $map = [
            'product_id' => $product_id,
        ];
        return $this->orm->table(DB_PREFIX . 'wk_pro_quote_details')->where($map)->orderBy('home_pick_up_price', $sort)->value('home_pick_up_price') ?? 0;

    }

    public function getProductQuotePrice($product_id, $customer_id, $default = 'max')
    {
        $sort = $default == 'max' ? 'desc' : 'asc';
        $map = [
            'product_id' => $product_id,
            'status' => SpotProductQuoteStatus::APPROVED,
            'customer_id' => $customer_id,
        ];
        return $this->orm->table(DB_PREFIX . 'product_quote')
                ->where($map)
                ->orderBy('price', $sort)
                ->value('price') ?? 0;

    }

    /**
     * [getFuturesMarginPrice description] 期货保证金价格
     * @param int $product_id
     * @param string $default
     * @return float
     */
    public function getFuturesMarginPrice($product_id, $default = 'max')
    {
        $sort = $default == 'max' ? 'desc' : 'asc';
        $map = [
            'product_id' => $product_id,
            'status' => 1,
            'is_deleted' => 0,
        ];
        $price[] = $this->orm->table('oc_futures_contract')
                ->where($map)
                ->where('last_unit_price', '>', 0)
                ->orderBy('last_unit_price', $sort)
                ->value('last_unit_price') ?? 0;

        $price[] = $this->orm->table('oc_futures_contract')
                ->where($map)
                ->where('margin_unit_price', '>', 0)
                ->orderBy('margin_unit_price', $sort)
                ->value('margin_unit_price') ?? 0;

        if ($this->customer->isLogged()) {
            $price[] = $this->orm->table(DB_PREFIX . 'futures_margin_agreement as fa')
                    ->leftJoin(DB_PREFIX . 'futures_margin_delivery as fd', 'fa.id', 'fd.agreement_id')
                    ->leftJoin(DB_PREFIX . 'product_lock as pl', function ($join) {
                        $join->on('pl.agreement_id', '=', 'fa.id')->where('pl.type_id', '=', $this->config->get('transaction_type_margin_futures'));
                    })
                    ->where([
                        'fa.buyer_id' => $this->customer->getId(),
                        'fa.agreement_status' => 7,
                        'fd.delivery_status' => 6,
                        'fa.product_id' => $product_id,
                    ])
                    ->where('pl.qty', '>', 0)
                    ->orderBy('fd.last_unit_price', $sort)
                    ->value('fd.last_unit_price') ?? 0;
        }
        return $default == 'max' ? max($price) : min(array_filter($price) ?: [0]);
    }

    /**
     * [dealWithImage description]
     * @param $image
     * @return string
     */
    public function dealWithImage($image)
    {
        return StorageCloud::image()->getUrl($image, [
            'w' => $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'),
            'h' => $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'),
            'no-image' => 'placeholder.png',
        ]);
    }


}
