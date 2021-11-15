<?php

/**
 * Class ModelExtensionModuleFreight
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelExtensionModuleProductShow $model_extension_module_product_show
 */
class ModelExtensionModuleFreight extends Model
{
    protected $product_id;
    protected $customer_id;
    protected $country_id;

    /**
     * @var ModelCatalogProduct $catalog_product
     */
    private $catalog_product;
    /**
     * @var ModelExtensionModuleProductShow $product_show
     */
    private $product_show;
    protected $isCollectionFromDomicile;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->load->model('catalog/product');
        $this->catalog_product = $this->model_catalog_product;
        $this->load->model('extension/module/product_show');
        $this->product_show = $this->model_extension_module_product_show;
        $this->customer_id = $this->customer->getId();
        $this->country_id = $this->customer->getCountryId();
        $this->isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
    }

    /**
     * [getFreight description]
     * @param int $product_id
     * @param string $buyer_id
     * @return array
     */
    public function getFreight($product_id, $buyer_id = 'default')
    {
        //判断是否登陆
        //判断是否本人
        if ($this->customer_id) {
            if ($buyer_id == 'default') {
                $buyer_id = $this->customer_id;
            }
            $dm_info = $this->catalog_product->getDelicacyManagementInfoByNoView($product_id, $buyer_id);
            $commission_amount = 0;
            $sql = $this->getProductSql($product_id, $buyer_id);
            $query = $this->db->query($sql);
            if ($query->num_rows) {

                $price = $query->row['price'];
                $freight = $query->row['freight'];
                $package_fee = $query->row['package_fee'];
                // 折扣
                $discountResult = $this->catalog_product->getDiscount($this->customer->getId(), $query->row['seller_id']);
                if (isset($discountResult['discount'])) {
                    $discount = (float)$discountResult['discount'];
                } else {
                    $discount = 1;
                }
                // 判断是否是登陆本人
                if ($this->customer_id == $buyer_id) {

                    if ($this->isCollectionFromDomicile) {
                        $freight_tmp = 0;
                    } else {
                        $freight_tmp = round($freight, 4);
                    }

                } else {
                    // seller 或者是其他登录的
                    if ($this->product_show->get_is_collection_from_domicile($buyer_id)) {
                        $freight_tmp = 0;
                    } else {
                        $freight_tmp = round($freight, 4);
                    }

                }

                if (in_array($query->row['seller_id'], PRODUCT_SHOW_ID) !== false) {
                    if ($dm_info && $dm_info['product_display'] && isset($dm_info['current_price'])) {
                        $delicacy_management_price = $dm_info['current_price'] + $freight_tmp + $package_fee;
                    } else {
                        $delicacy_management_price = $price + $freight_tmp + $package_fee;
                    }
                    $ret_price = (float)$delicacy_management_price * $discount + $commission_amount;

                    $ret = [
                        'result' => 1, //可见
                        'freight' => $freight,
                        'price' => $ret_price,
                        'package_fee' => $package_fee,
                    ];

                    if ($query->row['country_id'] == JAPAN_COUNTRY_ID) {
                        $ret['price'] = (int)$ret['price'];
                    } else {
                        $ret['price'] = sprintf('%.2f', $ret['price']);
                    }

                } else {

                    $ret = [
                        'result' => 1, //可见
                        'freight' => $freight,
                        'price' => $freight_tmp + $price + $package_fee,
                        'package_fee' => $package_fee,
                    ];

                    if ($query->row['country_id'] == JAPAN_COUNTRY_ID) {
                        $ret['price'] = (int)$ret['price'];
                    } else {
                        $ret['price'] = sprintf('%.2f', $ret['price']);
                    }


                }

                //验证可不可见
                $unsee = 0;
                if ($query->row['buyer_flag'] == 0) {
                    $unsee = 1;
                } elseif ($query->row['status'] == 0) {
                    $unsee = 1;
                } elseif ($query->row['c_status'] == 0) {
                    $unsee = 1;
                } elseif ($dm_info && $dm_info['product_display'] == 0) {
                    $unsee = 1;
                }
                // seller 拥有所有查看
                if ($buyer_id != $query->row['customer_id']) {

                    if ($unsee == 1 || $query->row['canSell'] == 0) {

                        $ret = [
                            'result' => 0, //不可见
                            'freight' => null,
                            'price' => null,
                            'package_fee' => null,
                        ];
                        //return $ret;
                    }
                }


            } else {

                $ret = [
                    'result' => 0, //不可见
                    'freight' => null,
                    'price' => null,
                    'package_fee' => null,
                ];

            }
            return $ret;

        } else {

            //默认未登录的满足查询条件的全部加运费
            $tmp = $this->orm->table(DB_PREFIX . 'product as p')
                ->leftJoin(DB_PREFIX . 'customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
                ->leftJoin(DB_PREFIX . 'customer as c', 'c.customer_id', '=', 'ctp.customer_id')
                ->where('p.product_id', $product_id)
                ->select('p.buyer_flag', 'p.status', 'p.freight','p.package_fee', 'p.price', 'p.price_display', 'c.status as c_status', 'c.country_id')
                ->get()
                ->map(
                    function ($vt) {
                        return (array)$vt;
                    })
                ->toArray();
            $tmp = current($tmp);
            if ($tmp) {
                if ($tmp['buyer_flag'] == 1
                    && $tmp['status'] == 1
                    && $tmp['price_display'] == 1
                    && $tmp['c_status'] == 1
                ) {
                    $ret = [
                        'result' => 1, //可见
                        'freight' => $tmp['freight'],
                        'price' => $tmp['freight'] + $tmp['price'] + $tmp['package_fee'],
                        'package_fee' => $tmp['package_fee'],
                    ];

                    if ($tmp['country_id'] == JAPAN_COUNTRY_ID) {
                        $ret['price'] = (int)$ret['price'];
                    } else {
                        $ret['price'] = sprintf('%.2f', $ret['price']);
                    }

                } else {
                    $ret = [
                        'result' => 0, //不可见
                        'freight' => null,
                        'price' => null,
                        'package_fee' => null,
                    ];
                }

            } else {
                $ret = [
                    'result' => 0, //不可见
                    'freight' => null,
                    'price' => null,
                    'package_fee' => null,
                ];

            }
            return $ret;

        }


    }

    /**
     * [getProductSql description]
     * @param int $product_id
     * @param int $buyer_id
     * @return string
     */
    public function getProductSql($product_id, $buyer_id)
    {
        $language_id = (int)$this->config->get('config_language_id');
        $store_id = (int)$this->config->get('config_store_id');
        $sql = 'select ';
        $sql .= 'DISTINCT
                c2c.screenname,
                c2c.customer_id as seller_id,
                cus.status as seller_status,
                p.buyer_flag,
                p.product_id AS productId,
                p.price,
                p.price_display,
                p.status,
                p.sku,
                p.freight,
                p.package_fee,
                c2p.seller_price,
                c2p.customer_id,
                c2p.quantity AS c2pQty,
                p.buyer_flag, 
                cus.country_id,
                cus.status as c_status,
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
                null as discount     
            FROM
                oc_product p
                LEFT JOIN oc_product_description pd ON ( p.product_id = pd.product_id )
                LEFT JOIN oc_product_to_store p2s ON ( p.product_id = p2s.product_id )
                LEFT JOIN oc_customerpartner_to_product c2p ON ( c2p.product_id = p.product_id )
                LEFT JOIN oc_customerpartner_to_customer c2c ON ( c2p.customer_id = c2c.customer_id )
                LEFT JOIN oc_customer cus ON ( c2p.customer_id = cus.customer_id )
                LEFT JOIN oc_buyer_to_seller AS b2s ON (b2s.seller_id = c2p.customer_id AND b2s.buyer_id = ' . $buyer_id . ' ) 
            WHERE
                p.product_id = ' . $product_id . '
                AND c2c.`show` = 1
                AND pd.language_id = ' . $language_id . '
                AND p.date_available <= NOW()
                AND p2s.store_id = ' . $store_id;
        return $sql;
    }


}