<?php

use App\Logging\Logger;
use App\Enums\Warehouse\ReceiptOrderStatus;
use App\Enums\Product\ProductType;
use App\Enums\Product\ProductStatus;

/**
 * Class ModelCustomerpartnerProductManage
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCommonProduct $model_common_product
 * @property ModelAccountWishlist $model_account_wishlist
 */
class ModelCustomerpartnerProductManage extends Model
{
    const PRODUCT_TYPE_GENERAL = 1;
    const PRODUCT_TYPE_COMBO = 2;
    const PRODUCT_TYPE_LTL = 4;
    const PRODUCT_TYPE_PART = 8;
    private $date_format_arr = array('d/m/Y H', 'Y-m-d H','Y-m-d H:i:s');

    public function querySellerProductNum($args, $customer_id)
    {
        $productSql = "SELECT COUNT(*) AS total FROM (";
        $productSql .= "  SELECT  T1.* ,T2.NEW_PRICE, T2.EFFECT_TIME FROM
                          (SELECT PD.product_type, PD.BUYER_FLAG, PD.IS_DELETED,  PD.PRODUCT_ID, PD.SKU,PD.MPN,PD.PRICE, PD.QUANTITY,PD.PRICE_DISPLAY,PD.QUANTITY_DISPLAY ,PD.STATUS
                            FROM  `OC_CUSTOMERPARTNER_TO_PRODUCT` A   RIGHT JOIN `OC_PRODUCT` PD
                            ON A.PRODUCT_ID = PD.PRODUCT_ID
                            WHERE A.`CUSTOMER_ID` = :customer_id ) T1
                        LEFT JOIN OC_SELLER_PRICE T2 ON T1.PRODUCT_ID = T2.`PRODUCT_ID` AND T2.status !=2
                        where 1=1 ";
        // is deleted
        $productSql .= ' AND T1.is_deleted = 0';
        $args['filter_deposit'] = isset($args['filter_deposit']) ?? '';
        if ($args['filter_deposit'] && isset($args['filter_deposit'])) {
            $productSql .= ' AND t1.product_type not in (' . implode(',', ProductType::deposit()) . ')';
        }
        $productParam = array(":customer_id" => $customer_id);
        // filter_sku_mpn product_type available_qty
        if (isset($args['filter_sku_mpn']) && !is_null($args['filter_sku_mpn'])){
            $productSql .= ' AND (t1.sku like :filter_sku_mpn1 or t1.mpn like :filter_sku_mpn2) ';
            $productParam[':filter_sku_mpn1'] = '%' . str_replace(['!', '%','_'], ['\!', '\%','\_'], trim($args['filter_sku_mpn'])) . '%';
            $productParam[':filter_sku_mpn2'] = '%' . str_replace(['!', '%','_'], ['\!', '\%','\_'], trim($args['filter_sku_mpn'])) . '%';
        }
        if (
            isset($args['filter_product_type'])
            && !is_null($args['filter_product_type'])
            && $args['filter_product_type'] > 0
        )
        {
            $product_type = $args['filter_product_type'];
            $ids = [];
            $subQuery = $this->orm
                ->table('oc_product_to_tag as ptt')
                ->select('ptt.product_id')
                ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'ptt.product_id')
                ->where(['ctp.customer_id' => $customer_id,]);
            $query = $this->orm
                ->table('oc_product as p')
                ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
                ->where(['ctp.customer_id' => $customer_id,]);
            if ($product_type & static::PRODUCT_TYPE_GENERAL) {
                $where = ['p.combo_flag' => 0, 'p.part_flag' => 0,];
                $tempIds = (clone $query)->where($where)
                    ->whereNotIn('p.product_id', (clone $subQuery)->where(['ptt.tag_id' => 1]))
                    ->pluck('p.product_id')
                    ->toArray();
                $ids = array_unique(array_merge($ids, $tempIds));
            }
            if ($product_type & static::PRODUCT_TYPE_COMBO) {
                $where = ['p.combo_flag' => 1,];
                $tempIds = (clone $query)->where($where)
                    ->whereIn('p.product_id', (clone $subQuery)->where(['ptt.tag_id' => 3]))
                    ->pluck('p.product_id')
                    ->toArray();
                $ids = array_unique(array_merge($ids, $tempIds));
            }
            if ($product_type & static::PRODUCT_TYPE_LTL) {
                $tempIds = (clone $query)
                    ->whereIn('p.product_id', (clone $subQuery)->where(['ptt.tag_id' => 1]))
                    ->pluck('p.product_id')
                    ->toArray();
                $ids = array_unique(array_merge($ids,$tempIds));
            }
            if ($product_type & static::PRODUCT_TYPE_PART){
                $where = ['p.part_flag' => 1,];
                $tempIds = (clone $query)->where($where)
                    ->whereIn('p.product_id', (clone $subQuery)->where(['ptt.tag_id' => 2]))
                    ->pluck('p.product_id')
                    ->toArray();
                $ids = array_unique(array_merge($ids,$tempIds));
            }
            if (count($ids) === 0) {
                return  0;
            }
            $productSql .= ' AND t1.product_id in ( '.join(',',$ids).' ) ';
        }
        if (isset($args['filter_buyer_flag']) && !is_null($args['filter_buyer_flag'])){
            $productSql .= " AND T1.buyer_flag = :filter_buyer_flag ";
            $productParam[':filter_buyer_flag'] = $args['filter_buyer_flag'] ? 1 : 0;
        }
        if (isset($args['filter_available_qty']) && !is_null($args['filter_available_qty'])) {
            if ($args['filter_available_qty'] == 1) {
                $productSql .= " AND T1.quantity > 0 ";
            } else if ($args['filter_available_qty'] == 10) {
                $productSql .= " AND T1.quantity < 10 ";
            } else {
                $productSql .= " AND T1.quantity = 0 ";
            }
        }
        // end filter_sku_mpn product_type available_qty
        if (isset($args['filter_sku']) && !is_null($args['filter_sku'])) {
            $productSql .= " AND T1.SKU LIKE :filter_sku ";
            $productParam[':filter_sku'] = '%'.$args['filter_sku'] . '%';
        }
        if (isset($args['filter_mpn']) && !is_null($args['filter_mpn'])) {
            $productSql .= " AND t1.mpn like :filter_mpn ";
            $productParam[':filter_mpn'] = '%'.$args['filter_mpn'] . '%';
        }
        if (isset($args['filter_status']) && !is_null($args['filter_status'])) {
            $productSql .= " AND t1.status = :filter_status ";
            $productParam[':filter_status'] = $args['filter_status'];
        }
        if (isset($args['filter_effectTimeFrom']) && !is_null($args['filter_effectTimeFrom'])) {
            $filter_effectTimeFrom = $this->dateStr2dateTime($args['filter_effectTimeFrom'], $this->date_format_arr);
            if ($filter_effectTimeFrom) {
                $productSql .= " AND t2.effect_time >= :filter_effectTimeFrom";
                $productParam[':filter_effectTimeFrom'] = $filter_effectTimeFrom->format("Y-m-d H:i:s");
            }
        }
        if (isset($args['filter_effectTimeTo']) && !is_null($args['filter_effectTimeTo'])) {
            $filter_effectTimeTo = $this->dateStr2dateTime($args['filter_effectTimeTo'], $this->date_format_arr);
            if ($filter_effectTimeTo) {
                $productSql .= " AND t2.effect_time <= :filter_effectTimeTo";
                $productParam[':filter_effectTimeTo'] = $filter_effectTimeTo->format("Y-m-d H:i:s");
            }
        }
        $productSql .= " ) T_NUM";

        $query = $this->db->query($productSql, $productParam);
        return $query->row['total'];
    }

    /**
     * 商品批量更新
     * #6446 修改为只更新产品库存
     *
     * @param array $products
     * @return void
     * @throws Exception
     */
    public function updateSellerProduct($products)
    {
        $this->load->model('account/wishlist');

        foreach ($products as $product) {
            $product_qty = $this->db->query("SELECT * FROM `oc_product` WHERE product_id =" . $product['product_id'])->row['quantity'];
            $addCommunication = false;

            $update_pd_sql = "UPDATE oc_product  SET  quantity_display = ? ,date_modified=NOW()";
            $update_pd_param = [$product['quantity_display']];
            //同时更新oc_customerpartner_to_product表
            $update_custom2product_sql = "UPDATE `oc_customerpartner_to_product` SET customer_id=customer_id  ";
            $update_custom2product_param = array();
            if (trim($product['quantity']) != null && ($product['quantity'] = (int)$product['quantity']) >= 0) {
                $update_pd_sql .= " ,quantity = ? ";
                $update_pd_param[] = $product['quantity'];
                $update_custom2product_sql .= ",quantity=? ";
                $update_custom2product_param[] = $product['quantity'];

                // 库存订阅提醒
                $addCommunication = $product_qty != $product['quantity'];
            }

            $update_pd_sql .= " WHERE product_id =  ?";
            $update_pd_param[] = $product['product_id'];
            $sql_entities = ['sql' => $update_pd_sql, 'param' => $update_pd_param];
            $this->db->query($sql_entities['sql'], $sql_entities['param']);
            if (!empty($update_custom2product_param)) {
                $update_custom2product_sql .= "where product_id=?";
                $update_custom2product_param [] = $product['product_id'];
                $sql_entities = ['sql' => $update_custom2product_sql, 'param' => $update_custom2product_param];
            }
            $this->db->query($sql_entities['sql'], $sql_entities['param']);

            //库存订阅提醒
            if ($addCommunication) {
                $this->model_account_wishlist->addCommunication($product['product_id']);
            }
        }
    }

    public function querySellerProductPrice($product_id)
    {
        $sql = "SELECT new_price FROM oc_seller_price WHERE id = (SELECT MAX(id) FROM oc_seller_price WHERE status!=2 AND product_id = " . $product_id . " AND id NOT IN (
        SELECT MAX(id) FROM oc_seller_price WHERE status!=2 AND product_id = " . $product_id . "
        ))";

        return $this->db->query($sql)->row;
    }


    public function querySellerProducts($args, $customer_id)
    {
        $productSql = "SELECT 0 AS instock_qty, t1.* ,t2.new_price AS modified_price, t2.effect_time FROM
                          (SELECT pd.product_type,  pd.is_deleted, pd.product_id, pd.sku,pd.mpn,pd.price AS current_price, pd.quantity,pd.price_display,pd.quantity_display ,pd.status,pd.combo_flag,pd.buyer_flag,pd.part_flag,pd.freight,pd.package_fee, a.pickup_price
                            FROM  `oc_customerpartner_to_product` a   RIGHT JOIN `oc_product` pd
                            ON a.product_id = pd.product_id
                            WHERE a.`customer_id` = :customer_id ) t1
                            LEFT JOIN   (SELECT * FROM oc_seller_price  WHERE status!=2  ) t2
                            ON t1.product_id = t2.`product_id`
                            where 1=1  ";
        $productParam = array(":customer_id" => $customer_id);
        //is deleted
        $productSql .= ' AND t1.is_deleted = 0';
        $args['filter_deposit'] = isset($args['filter_deposit']) ?? '';
        if ($args['filter_deposit'] && isset($args['filter_deposit'])) {
            $productSql .= ' AND t1.product_type not in (' . implode(',', ProductType::deposit()) . ')';
        }
        // filter_sku_mpn product_type available_qty
        if (isset($args['filter_sku_mpn']) && !is_null($args['filter_sku_mpn'])){
            $productSql .= ' AND (t1.sku like :filter_sku_mpn1 or t1.mpn like :filter_sku_mpn2) ';
            $productParam[':filter_sku_mpn1'] = '%' . str_replace(['!', '%','_'], ['\!', '\%','\_'], trim($args['filter_sku_mpn'])) . '%';
            $productParam[':filter_sku_mpn2'] = '%' . str_replace(['!', '%','_'], ['\!', '\%','\_'], trim($args['filter_sku_mpn'])) . '%';
        }
        if (
            isset($args['filter_product_type'])
            && !is_null($args['filter_product_type'])
            && $args['filter_product_type'] > 0
        )
        {
            $product_type = $args['filter_product_type'];
            $ids = [];
            $subQuery = $this->orm
                ->table('oc_product_to_tag as ptt')
                ->select('ptt.product_id')
                ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'ptt.product_id')
                ->where(['ctp.customer_id' => $customer_id,]);
            $query = $this->orm
                ->table('oc_product as p')
                ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
                ->where(['ctp.customer_id' => $customer_id,]);
            if ($product_type & static::PRODUCT_TYPE_GENERAL) {
                $where = ['p.combo_flag' => 0, 'p.part_flag' => 0,];
                $tempIds = (clone $query)->where($where)
                    ->whereNotIn('p.product_id', (clone $subQuery)->where(['ptt.tag_id' => 1]))
                    ->pluck('p.product_id')
                    ->toArray();
                $ids = array_unique(array_merge($ids, $tempIds));
            }
            if ($product_type & static::PRODUCT_TYPE_COMBO) {
                $where = ['p.combo_flag' => 1,];
                $tempIds = (clone $query)->where($where)
                    ->whereIn('p.product_id', (clone $subQuery)->where(['ptt.tag_id' => 3]))
                    ->pluck('p.product_id')
                    ->toArray();
                $ids = array_unique(array_merge($ids, $tempIds));
            }
            if ($product_type & static::PRODUCT_TYPE_LTL) {
                $tempIds = (clone $query)
                    ->whereIn('p.product_id', (clone $subQuery)->where(['ptt.tag_id' => 1]))
                    ->pluck('p.product_id')
                    ->toArray();
                $ids = array_unique(array_merge($ids,$tempIds));
            }
            if ($product_type & static::PRODUCT_TYPE_PART){
                $where = ['p.part_flag' => 1,];
                $tempIds = (clone $query)->where($where)
                    ->whereIn('p.product_id', (clone $subQuery)->where(['ptt.tag_id' => 2]))
                    ->pluck('p.product_id')
                    ->toArray();
                $ids = array_unique(array_merge($ids,$tempIds));
            }
            if (count($ids) === 0) {
                return [];
            }
            $productSql .= ' AND t1.product_id in ( '.join(',',$ids).' ) ';
        }
        if (isset($args['filter_buyer_flag']) && !is_null($args['filter_buyer_flag'])){
            $productSql .= " AND T1.buyer_flag = :filter_buyer_flag ";
            $productParam[':filter_buyer_flag'] = $args['filter_buyer_flag'] ? 1 : 0;
        }
        if (isset($args['filter_available_qty']) && !is_null($args['filter_available_qty'])) {
            if ($args['filter_available_qty'] == 1) {
                $productSql .= " AND T1.quantity > 0 ";
            } else if ($args['filter_available_qty'] == 10) {
                $productSql .= " AND T1.quantity < 10 ";
            } else {
                $productSql .= " AND T1.quantity = 0 ";
            }
        }
        // end filter_sku_mpn product_type available_qty
        //filter
        if (isset($args['filter_sku']) && !is_null($args['filter_sku'])) {
            $productSql .= " AND t1.sku like :filter_sku ";
            $productParam[':filter_sku'] = '%'.$args['filter_sku'] . '%';
        }
        if (isset($args['filter_mpn']) && !is_null($args['filter_mpn'])) {
            $productSql .= " AND t1.mpn like :filter_mpn ";
            $productParam[':filter_mpn'] = '%'.$args['filter_mpn'] . '%';
        }
        if (isset($args['filter_status']) && !is_null($args['filter_status'])) {
            $productSql .= " AND t1.status = :filter_status ";
            $productParam[':filter_status'] = $args['filter_status'];
        }
        if (isset($args['filter_effectTimeFrom']) && !is_null($args['filter_effectTimeFrom'])) {
            $filter_effectTimeFrom = $this->dateStr2dateTime($args['filter_effectTimeFrom'], $this->date_format_arr);
            if ($filter_effectTimeFrom) {
                $productSql .= " AND t2.effect_time >= :filter_effectTimeFrom";
                $productParam[':filter_effectTimeFrom'] = $filter_effectTimeFrom->format("Y-m-d H:i:s");
            }
        }
        if (isset($args['filter_effectTimeTo']) && !is_null($args['filter_effectTimeTo'])) {
            $filter_effectTimeTo = $this->dateStr2dateTime($args['filter_effectTimeTo'], $this->date_format_arr);
            if ($filter_effectTimeTo) {
                $productSql .= " AND t2.effect_time <= :filter_effectTimeTo";
                $productParam[':filter_effectTimeTo'] = $filter_effectTimeTo->format("Y-m-d H:i:s");
            }
        }

        //排序
        $sort_data = array(
            't1.mpn',
            't1.product_id',
        );
        if (isset($args['sort']) && in_array($args['sort'], $sort_data)) {
            $productSql .= " ORDER BY :sort ";
            $productParam[":sort"] = $args['sort'];
        } else {
            $productSql .= " ORDER BY t1.product_id";
        }

        if (isset($args['order']) && ($args['order'] == 'DESC')) {
            $productSql .= " DESC";
        } else {
            $productSql .= " ASC";
        }
        //分页
        if (isset($args['start']) || isset($args['limit'])) {
            if ($args['start'] < 0) {
                $args['start'] = 0;
            }

            if ($args['limit'] < 1) {
                $args['limit'] = 20;
            }

            $productSql .= " LIMIT " . (int)$args['start'] . " , " . (int)$args['limit'] . " ";
        }
        //结果
        $result_data = array();
        $query = $this->db->query($productSql, $productParam);
        if ($query->num_rows > 0) {
            foreach ($query->rows as $row) {
                $result_data[$row['product_id']] = $row;
            }
            $product_id_array = array_keys($result_data);
            $wenhao_array = array();
            foreach ($product_id_array as $a) {
                $wenhao_array[] = "?";
            }

            $this->load->model('catalog/product');
            foreach ($result_data as $k => $v) {
                $stock_num = $this->model_catalog_product->queryStockByProductId($k)['total_onhand_qty'];
                $result_data[$k]['instock_qty'] = $stock_num;

            }

            //查询next_shipment
            $shipmentSql = " SELECT  rod.product_id, rod.expected_qty,  ro.expected_date
                                FROM
                                  `tb_sys_receipts_order` ro
                                  INNER JOIN `tb_sys_receipts_order_detail` rod
                                    ON ro.`receive_order_id` = rod.`receive_order_id`
                                WHERE ro.`status` = " . ReceiptOrderStatus::TO_BE_RECEIVED . "
                                  AND ro.`expected_date` >= NOW()
                                  AND rod.product_id IN (" . implode(',', $wenhao_array) . ")
                                ORDER BY rod.product_id ASC,ro.`expected_date` DESC";
            $shipmentQuery = $this->db->query($shipmentSql, $product_id_array);
            //筛选出最近一批到货的product
            if ($shipmentQuery->num_rows > 0) {
                foreach ($shipmentQuery->rows as $row) {
                    $product_id = $row['product_id'];
                    if (!isset($result_data[$product_id]['next_shipment'])) {
                        $result_data[$product_id]['next_shipment'] = $row['expected_date'];
                        $result_data[$product_id]['next_shipment_qty'] = $row['expected_qty'];
                    }
                }
            }
        }
        return $result_data;
    }

    public function dateStr2dateTime($time_str, $format_arr = array())
    {
        $date = false;
        if ($time_str && $format_arr) {
            foreach ($format_arr as $fm) {
                $date = date_create_from_format($fm, $time_str);
                if ($date) break;
            }
        }
        return $date;
    }

    public function dateStr2dateTimeWithTimeZone($time_str, $format_arr = array(), $time_zone)
    {
        $date = false;
        if ($time_str && $format_arr) {
            foreach ($format_arr as $fm) {
                $date = date_create_from_format($fm, $time_str, $time_zone);
                if ($date) break;
            }
        }
        return $date;
    }

    public function getProductIdByMpnOrSku($customer_id, $mpn = null, $sku = null)
    {
        $sql = <<<SQL
 SELECT p.product_id,p.product_type,p.sku
FROM oc_product p
INNER JOIN oc_customerpartner_to_product ctp ON p.product_id = ctp.product_id
WHERE ctp.customer_id = :customer_id
SQL;
        $param = array(":customer_id" => $customer_id);
        if (isset($mpn) && $mpn != '') {
            $sql .= " AND p.mpn = :mpn";
            $param[':mpn'] = $mpn;
        }
        if (isset($sku) && $sku != '') {
            $sql .= " AND p.sku = :sku";
            $param[':sku'] = $sku;
        }
        $query = $this->db->query($sql, $param);
        if (isset($query->row) && !empty($query->row)) {
            return [$query->row['product_id'], $query->row['product_type'], $query->row['sku']];
        }

        return null;
    }

    public function validateStock($product)
    {
        $this->load->model('catalog/product');
        $this->load->model('common/product');
        $invalid_product = array();
        foreach ($product as $product_id => $qty) {
            $actual_qty = $this->model_common_product->getProductAvailableQuantity($product_id);
            if ($actual_qty < $qty) {
                $invalid_product[] = $product_id;
            }
        }
        return $invalid_product;
    }

    /**
     * [validateEuropeFreightProduct description]
     * @param int $product_id
     * @param int $country_id
     * @return bool
     * date:2020/11/20 10:50
     */
    public function validateEuropeFreightProduct($product_id,$country_id):bool
    {
        $product_type = $this->orm->table('oc_product')->where('product_id',$product_id)->value('product_type');
        if($product_type == 3 && in_array($country_id,EUROPE_COUNTRY_ID)){
            return true;
        }

        return false;
    }

    /**
     * [validatePriceChangeStatus description] 处于价格保护的产品需要更改库存
     * @param $data // 更改的数据,单条数据更新，无批量更新
     * @return boolean
     */
    public  function validatePriceChangeStatus($data){
        $map = [];
        foreach($data as $key => $value){
            $map['status'] = $value['status'];
            $map['product_id'] = $key;
            $map['effect_time'] = $value['effect_time'].':00:00';
            $map['new_price']  = sprintf('%.2f',$value['modified_price']);
            $res = $this->orm->table(DB_PREFIX.'seller_price')->where($map)->exists();
            return $res;
        }

    }

    /**
     * 校验价格变动是否上涨过快（距离上次修改价格小于24小时）
     * @param array $product_price_map  product_id => new_price
     * @return array
     */
    public function validatePriceIncreaseTime($product_price_map)
    {
        $invalid_product = array();
        if (isset($product_price_map) && !empty($product_price_map)) {
            $product_id_str = implode(",", array_keys($product_price_map));
            $sql = "SELECT p.product_id,p.price AS current_price,p.status,p.date_modified AS modify_date FROM oc_product p WHERE p.product_id IN (" . $product_id_str . ")";
            $query = $this->db->query($sql);

            $price_record_sql = "SELECT DISTINCT sph.`product_id` FROM `oc_seller_price_history` sph WHERE sph.`product_id` IN (" . $product_id_str . ")  AND sph.`price` > 0";
            $price_record_query = $this->db->query($price_record_sql);
            $price_changed_record = array();
            if (isset($price_record_query->rows)) {
                foreach ($price_record_query->rows as $value) {
                    $price_changed_record[] = (int)$value['product_id'];
                }
            }

            $one_day_second = 60 * 60 * 24;
            $twenty_five_hour_second = 60 * 60 * 25;
            $now = strtotime(date("Y-m-d H:i:s"));

            if (isset($query->rows)) {
                foreach ($query->rows as $row) {
                    if ("0000-00-00 00:00:00" === $row['modify_date'] || !in_array($row['product_id'], $price_changed_record)) {
                        continue;
                    }

                    $product_info = $product_price_map[$row['product_id']];
                    if (isset($product_info['effect_time']) && !empty($product_info['effect_time'])) {
                        $intent_date = strtotime($product_info['effect_time'] . ":00:00");
                    } else {
                        $intent_date = $now;
                    }

                    //上架商品起始校验时间为上一次商品条目修改时间。目前无下架时间记录，而且下架后也允许修改价格，所以以最新的修改时间为准
                    if ($row['status'] === '0' && $product_info['status'] === '1') {
                        $last_modify_date = strtotime($row['modify_date']);
                        if ($row['current_price'] < $product_info['modified_price'] && ($intent_date - $last_modify_date) < $one_day_second) {
                            //上架校验不通过的需要单独记录给出用户上次修改时间的提示

                            if ($last_modify_date % 100 === 0) {
                                $amended_date = date("Y-m-d H", $last_modify_date + $one_day_second);
                            } else {
                                $amended_date = date("Y-m-d H", floor(($last_modify_date + $twenty_five_hour_second) / 100) * 100);
                            }
                            $invalid_product[$row['product_id']] = $amended_date;
                        }
                    } else {
                        if ($row['current_price'] < $product_info['modified_price'] && ($intent_date - $now) < $one_day_second) {
                            $invalid_product[$row['product_id']] = "";
                        }
                    }
                }
            }
        }
        return $invalid_product;
    }


    /**
     * 未下载商品信息提供数据
     * @param array $param
     * @param int $customer_id
     * @return array
     */
    public function getCommodityStatistics($param, $customer_id)
    {
        $preProducts = $this->querySellerProducts($param, $customer_id);
        $productIds = array();
        $question_mark_array = array();
        $result = array();
        if (!$preProducts || empty($preProducts)) {
            return $result;
        }

        foreach ($preProducts as $key => $v) {
            $productIds[] = $key;
            $question_mark_array[] = "?";

            $detail_info = array(
                'mpn' => $v['mpn'],
                'item_code' => $v['sku'],
                'product_name' => '',
                'combo_flag' => '',
                'mpn_in_detail' => '',
                'instock_qty' => $v['instock_qty'],
                'on_shelf_qty' => $v['quantity'],
                'current_price' => $v['current_price'],
                'is_info_complete' => 'No',
                'is_Material_complete' => 'No',
                'status' => ProductStatus::getDescription($v['status']),
                'freight'=>$v['freight'],
                'buyer_flag'=>$v['buyer_flag'],
                'product_type' => $this->getProductType($key),
            );
            $result[$key] = $detail_info;
        }


        $nameAndComboQuery = $this->db->query("SELECT pd.product_id,pd.name,psi.set_mpn,psi.qty
                        FROM oc_product_description pd LEFT JOIN tb_sys_product_set_info psi ON pd.product_id = psi.product_id
                        WHERE pd.product_id IN (" . implode(',', $productIds) . ")", $productIds);

//        $checkInfoQuery = $this->db->query("SELECT p.product_id,IF(ISNULL(p.image) OR LENGTH(TRIM(p.image))<1 OR ISNULL(pd.description) OR LENGTH(TRIM(pd.description))<1,'No','Yes') AS check_res,pd.description
//                        FROM oc_product p INNER JOIN oc_product_description pd ON p.product_id = pd.product_id
//                        WHERE p.product_id IN (" . implode(',', $productIds) . ")", $productIds);
//
//        $checkMeterialQuery = $this->db->query("SELECT p.product_id,'Yes' AS check_res FROM oc_product p LEFT JOIN oc_product_package_file ppf ON p.product_id = ppf.product_id
//                        LEFT JOIN oc_product_package_image ppi ON p.product_id = ppi.product_id
//                        LEFT JOIN oc_product_package_video ppv ON p.product_id = ppv.product_id
//                        WHERE p.product_id IN (" . implode(',', $productIds) . ")
//                            AND ((ppf.file IS NOT NULL AND LENGTH(TRIM(ppf.file)) > 1)
//                                OR (ppi.image IS NOT NULL AND LENGTH(TRIM(ppi.image)) > 1)
//                                OR (ppv.video IS NOT NULL AND LENGTH(TRIM(ppv.video)) > 1))
//                        GROUP BY p.product_id", $productIds);

        //更新商品名称和combo信息
        if (isset($nameAndComboQuery) && !empty($nameAndComboQuery)) {
            foreach ($nameAndComboQuery->rows as $nameAndCombo) {
                $detail_info = $result[$nameAndCombo['product_id']];
                if (isset($detail_info)) {
                    $detail_info['product_name'] = html_entity_decode($nameAndCombo['name']);

                    if (isset($nameAndCombo['set_mpn']) && isset($nameAndCombo['qty'])) {
                        $detail_info['combo_flag'] = 'Yes';
                        $tempComboInfo = $detail_info['mpn_in_detail'];
                        $detail_info['mpn_in_detail'] = $tempComboInfo . $nameAndCombo['set_mpn'] . "(" . $nameAndCombo['qty'] . ");\r\n";
                    } else {
                        $detail_info['combo_flag'] = 'No';
                    }
                    $result[$nameAndCombo['product_id']] = $detail_info;
                }
            }
        }

        //更新是否商品信息完备
        if (isset($checkInfoQuery) && !empty($checkInfoQuery)) {
            foreach ($checkInfoQuery->rows as $isInfoComplete) {
                $detail_info = $result[$isInfoComplete['product_id']];
                if (isset($detail_info)) {
                    if (isset($isInfoComplete['description']) && !empty($isInfoComplete['description'])) {
                        $purified_description = strip_tags(trim(str_replace('&nbsp;', '', html_entity_decode($isInfoComplete['description']))));
                        if (!isset($purified_description) || empty($purified_description)) {
                            $detail_info['is_info_complete'] = 'No';
                        } else {
                            $detail_info['is_info_complete'] = $isInfoComplete['check_res'];
                        }
                    } else {
                        $detail_info['is_info_complete'] = $isInfoComplete['check_res'];
                    }

                    $result[$isInfoComplete['product_id']] = $detail_info;
                }
            }
        }

        //更新商品素材包是否完备
        if (isset($checkMeterialQuery) && !empty($checkMeterialQuery)) {
            foreach ($checkMeterialQuery->rows as $isMaterialComplete) {
                $detail_info = $result[$isMaterialComplete['product_id']];
                if (isset($detail_info)) {
                    $detail_info['is_Material_complete'] = $isMaterialComplete['check_res'];
                    $result[$isMaterialComplete['product_id']] = $detail_info;
                }
            }
        }
        return $result;
    }

    /**
     * N-457  添加30天销售量和总销售量
     * @param array $product_ids
     * @return array
     */
    public function get_sell_count($product_ids){
        //获取三十天销售总量
        $sell_count=$this->orm->table('tb_sys_product_sales')
            ->whereIn('product_id',$product_ids)
//            ->where('is_deleted','=',0)
            ->select(['product_id','quantity_30','quantity_all'])
            ->get();
        $sell_count=obj2array($sell_count);
        $sell_count_30=array();
        $sell_count_all=array();
        if($sell_count){
            $sell_count_30=array_combine(array_column($sell_count,'product_id'),array_column($sell_count,'quantity_30'));
            $sell_count_all=array_combine(array_column($sell_count,'product_id'),array_column($sell_count,'quantity_all'));
        }
        return array(
            'days'=>$sell_count_30,
            'all'=>$sell_count_all
        );
    }

    /**
     * 获取产品类型 目前有4种类型 1-general 2-Combo 4-ltl 8-part
     * @param int $product_id
     * @return int
     */
    public function getProductType(int $product_id): int
    {
        static $product_id_type = [];
        if (isset($product_id_type[$product_id])) {
            return $product_id_type[$product_id];
        }
        $product_info = $this->orm->table('oc_product')->where(['product_id' => $product_id])->first();
        if (!$product_info) {
            $product_id_type[$product_id] = 0;
            return 0;
        }
        $product_info = get_object_vars($product_info);
        $tag_ids = $this->orm
            ->table('oc_product_to_tag')
            ->where('product_id', $product_id)
            ->pluck('tag_id')
            ->toArray();
        if ($product_info['combo_flag'] == 0 && $product_info['part_flag'] == 0 && count($tag_ids) == 0) {
            $product_id_type[$product_id] = static::PRODUCT_TYPE_GENERAL;
            return static::PRODUCT_TYPE_GENERAL;
        }
        $res_id = 0;
        // 下面请参照表oc_product oc_tag oc_product_to_tag
        if ($product_info['combo_flag'] == 1 && in_array(3, $tag_ids)) {
            $res_id += static::PRODUCT_TYPE_COMBO;
        }
        if ($product_info['part_flag'] == 1 && in_array(2, $tag_ids)) {
            $res_id += static::PRODUCT_TYPE_PART;
        }
        if (in_array(1, $tag_ids)) {
            $res_id += static::PRODUCT_TYPE_LTL;
        }
        $product_id_type[$product_id] = $res_id;
        return $res_id;
    }

    // region 商品下架库存设置为0
    /**
     * @param string|int|array $productId
     */
    public function setProductsOffShelf($productId)
    {
        if (!$productId || empty($productId)) {
            return;
        }
        $this->orm->table('oc_product')
            ->whereIn('product_id', (array)$productId)
            ->update(['quantity' => 0, 'status' => 0,]);
        $this->orm->table('oc_customerpartner_to_product')
            ->whereIn('product_id', (array)$productId)
            ->update(['quantity' => 0,]);
    }
    // endregion

    /**
     * 验证货值价格比例
     * @param int $product_id
     * @return bool
     */
    public function checkSettingPriceAndFreight($product_id,$price)
    {
        $obj = $this->orm->table('oc_product')
            ->where([
                ['product_id', '=', $product_id],
                ['status', '=', 1],
                ['is_deleted', '=', 0],
                ['buyer_flag', '=', 1]
            ])
            ->first(['freight', 'package_fee']);
        if (empty($obj)) {
            if (($obj->freight + $obj->package_fee) / ($obj->freight + $obj->package_fee + $price) < PRODUCT_PRICE_PROPORTION) {
                return true;
            }else{
                return false;
            }
        }
        return false;
    }

}
