<?php

use App\Repositories\Buyer\BuyerToSellerRepository;
use Illuminate\Database\Query\Expression;

/**
 * Class ModelCustomerpartnerMaster
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCustomerpartnerStoreRate $model_customerpartner_store_rate
 * @property ModelCatalogSearch $model_catalog_search
 * @property ModelAccountSellers $model_account_sellers
 */
class ModelCustomerpartnerMaster extends Model {

    const END_OF_TEXT = 10;
    public function getPartnerIdBasedonProduct($productid){

        $product_status = 1;

        if (($this->config->get('module_marketplace_status') && isset($this->request->get['user_token']) && isset($this->session->data['user_token']) && isset($this->session->data['user_id']) && $this->request->get['user_token'] == $this->session->data['user_token']) || ($this->config->get('module_marketplace_status') && isset($this->request->get['product_token']) && isset($this->session->data['product_token']) && $this->request->get['product_token'] == $this->session->data['product_token'])){
            $product_status = $this->db->query("SELECT status FROM ".DB_PREFIX."product WHERE product_id = ". (int)$productid)->row['status'];
            if (!$product_status) {
                $this->db->query("UPDATE ".DB_PREFIX."product SET status = '1' WHERE product_id = " . (int)$productid);
            }
        }

        $query = $this->db->query("SELECT c2p.customer_id as id FROM " . DB_PREFIX . "customerpartner_to_product c2p LEFT JOIN ".DB_PREFIX."product p ON(c2p.product_id = p.product_id) LEFT JOIN ".DB_PREFIX."product_to_store p2s ON (p.product_id = p2s.product_id) WHERE c2p.product_id = '".(int)$productid."' AND p.status = 1 AND p2s.store_id = '".$this->config->get('config_store_id')."' ORDER BY c2p.id ASC ")->row;

        if (!$product_status) {
            $this->db->query("UPDATE ".DB_PREFIX."product SET status = '0' WHERE product_id = " . (int)$productid);
        }

        return $query;
    }

    public function getProductItemCodeById($product_id) {
        if ($product_id) {
            $sql = "SELECT p.sku AS itemCode FROM oc_product p WHERE p.product_id = " . (int)$product_id;
            return $this->db->query($sql)->row['itemCode'];
        } else {
            return null;
        }
    }

    public function getLatest($data = array()) {
        $sql = "";
        if(isset($data['customFields'])){
            $sql .= "SELECT case when c.customer_id in (select seller_id  from oc_buyer_to_seller b2s where b2s.buyer_id = " . $data['customFields'] . " and b2s.buy_status = 1 and b2s.buyer_control_status =1 and b2s.seller_control_status = 1 ) then 1 else 0 end as canSell,p.sku,p.mpn,c2p.seller_price,c2p.quantity,p.product_id,pd.description,p.image,p.price,p.minimum,p.tax_class_id,pd.name,c2c.screenname,c2c.avatar,c2c.backgroundcolor,c.customer_id,CONCAT(c.firstname ,' ',c.lastname) seller_name,co.name country, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special FROM " . DB_PREFIX . "customerpartner_to_product c2p LEFT JOIN " . DB_PREFIX . "product p ON (c2p.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_description pd ON (pd.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "customerpartner_to_customer c2c ON (c2c.customer_id = c2p.customer_id) LEFT JOIN " . DB_PREFIX . "customer c ON (c2c.customer_id = c.customer_id) LEFT JOIN " . DB_PREFIX . "country co ON (c2c.country = co.iso_code_2) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE c2c.is_partner = '1' AND p.status = '1' AND p.date_available <= NOW() AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p2s.store_id = '" . $this->config->get('config_store_id') . "'";
        }else{
            $sql .= "SELECT 0 as canSell, p.sku,p.mpn,c2p.seller_price,c2p.quantity,p.product_id,pd.description,p.image,p.price,p.minimum,p.tax_class_id,pd.name,c2c.screenname,c2c.avatar,c2c.backgroundcolor,c.customer_id,CONCAT(c.firstname ,' ',c.lastname) seller_name,co.name country, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special FROM " . DB_PREFIX . "customerpartner_to_product c2p LEFT JOIN ".DB_PREFIX ."product p ON (c2p.product_id = p.product_id) LEFT JOIN ".DB_PREFIX ."product_description pd ON (pd.product_id = p.product_id) LEFT JOIN ".DB_PREFIX ."customerpartner_to_customer c2c ON (c2c.customer_id = c2p.customer_id) LEFT JOIN ".DB_PREFIX ."customer c ON (c2c.customer_id = c.customer_id) LEFT JOIN ".DB_PREFIX ."country co ON (c2c.country = co.iso_code_2) LEFT JOIN ".DB_PREFIX."product_to_store p2s ON (p.product_id = p2s.product_id) WHERE c2c.is_partner = '1' AND p.status = '1' AND p.date_available <= NOW() AND pd.language_id = '".(int)$this->config->get('config_language_id')."' AND p2s.store_id = '".$this->config->get('config_store_id')."'";
        }
        $sql .= " AND c2c.show = 1";
//            $sql = "SELECT case when c.customer_id in (select GROUP_CONCAT(seller_id SEPARATOR ',') from oc_buyer_to_seller b2s where b2s.buyer_id = " . $data['customFields'] . ") then 1 else 0 end as canSell,p.sku,p.mpn,c2p.seller_price,c2p.quantity,p.product_id,pd.description,p.image,p.price,p.minimum,p.tax_class_id,pd.name,c2c.screenname,c2c.avatar,c2c.backgroundcolor,c.customer_id,CONCAT(c.firstname ,' ',c.lastname) seller_name,co.name country, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special FROM " . DB_PREFIX . "customerpartner_to_product c2p LEFT JOIN " . DB_PREFIX . "product p ON (c2p.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_description pd ON (pd.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "customerpartner_to_customer c2c ON (c2c.customer_id = c2p.customer_id) LEFT JOIN " . DB_PREFIX . "customer c ON (c2c.customer_id = c.customer_id) LEFT JOIN " . DB_PREFIX . "country co ON (c2c.country = co.iso_code_2) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE c2c.is_partner = '1' AND p.status = '1' AND p.date_available <= NOW() AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p2s.store_id = '" . $this->config->get('config_store_id') . "'";
        $sort_data = array(
            'pd.name',
            'p.model',
            'p.quantity',
            'p.price',
            'rating',
            'c2p.product_id',
            'p.date_added'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
                $sql .= " ORDER BY LCASE(" . $data['sort'] . ")";
            } elseif ($data['sort'] == 'p.price') {
                $sql .= " ORDER BY (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";
            } else {
                $sql .= " ORDER BY " . $data['sort'];
            }
        } else {
            $sql .= " ORDER BY c2p.product_id";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC, LCASE(pd.name) DESC";
        } else {
            $sql .= " ASC, LCASE(pd.name) ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            if ((int)$this->config->get('marketplace_seller_product_list_limit') && ($data['limit'] > (int)$this->config->get('marketplace_seller_product_list_limit'))) {
                $data['limit'] = (int)$this->config->get('marketplace_seller_product_list_limit');
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        $products = array();
        foreach ($query->rows as $key => $value) {
            $products[$value['product_id']] = $value;
        }

        return $products;
    }

    public function getTotalLatest($data = array()) {
        $sql = "SELECT p.product_id,pd.description,p.image,p.price,p.tax_class_id,pd.name,c2c.avatar,c2c.backgroundcolor,c.customer_id,CONCAT(c.firstname ,' ',c.lastname) seller_name,co.name country, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special FROM " . DB_PREFIX . "customerpartner_to_product c2p LEFT JOIN ".DB_PREFIX ."product p ON (c2p.product_id = p.product_id) LEFT JOIN ".DB_PREFIX ."product_description pd ON (pd.product_id = p.product_id) LEFT JOIN ".DB_PREFIX ."customerpartner_to_customer c2c ON (c2c.customer_id = c2p.customer_id) LEFT JOIN ".DB_PREFIX ."customer c ON (c2c.customer_id = c.customer_id) LEFT JOIN ".DB_PREFIX ."country co ON (c2c.country = co.iso_code_2) LEFT JOIN ".DB_PREFIX."product_to_store p2s ON (p.product_id = p2s.product_id) WHERE c2c.is_partner = '1' AND p.status = '1' AND p.date_available <= NOW() AND pd.language_id = '".(int)$this->config->get('config_language_id')."' AND p2s.store_id = '".$this->config->get('config_store_id')."'";

        if ((int)$this->config->get('marketplace_seller_product_list_limit')) {
            $sql .= 'ORDER BY c2p.product_id DESC limit '.(int)$this->config->get('marketplace_seller_product_list_limit').'';
        }

        $query = $this->db->query($sql);

        $products = array();
        foreach ($query->rows as $key => $value) {
            $products[$value['product_id']] = $value;
        }

        return count($products);
    }

    /**
     * @TODO 如果精细化视图修改，此处也要修改
     *
     * @param int $customer_id
     * @param int $buyer_id
     * @return mixed
     * @since 2019-8-23 lester.you
     */
    public function getPartnerCollectionCount($customer_id,$buyer_id = 0){
        $sql = "SELECT count(DISTINCT p.product_id) as total FROM oc_customerpartner_to_product c2p
LEFT JOIN " . DB_PREFIX . "product p ON (c2p.product_id = p.product_id)
LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) ";
        $sql.="WHERE c2p.customer_id='" . (int)$customer_id . "' AND p.status='1' AND p.buyer_flag = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . $this->config->get('config_store_id') . "'";
        if ($buyer_id) {
            $sql.=" and p.product_id not in ( select dm.product_id from oc_delicacy_management as dm where dm.seller_id=$customer_id and dm.buyer_id=$buyer_id and dm.product_display=0 )";
            $sql.=" AND p.product_id NOT IN (
SELECT pgl.product_id
FROM oc_delicacy_management_group AS dmg
	JOIN oc_customerpartner_product_group_link AS pgl ON pgl.product_group_id = dmg.product_group_id
	JOIN oc_customerpartner_buyer_group_link AS bgl ON bgl.buyer_group_id = dmg.buyer_group_id
WHERE dmg.seller_id = $customer_id AND bgl.buyer_id = $buyer_id AND bgl.seller_id = $customer_id AND pgl.seller_id = $customer_id AND dmg.STATUS = 1 AND bgl.STATUS = 1 AND pgl.STATUS = 1 )";
        }
        return $this->db->query($sql)->row['total'];
    }

    public function getOldPartner(){

        $limit = (int)$this->config->get('marketplace_seller_list_limit') ? (int)$this->config->get('marketplace_seller_list_limit') : 4;

        return $this->db->query("SELECT *,co.name as country,companylocality FROM " . DB_PREFIX . "customerpartner_to_customer c2c LEFT JOIN ".DB_PREFIX ."customer c ON (c2c.customer_id = c.customer_id) LEFT JOIN ".DB_PREFIX ."country co ON (c2c.country = co.iso_code_2) WHERE is_partner = 1 AND c.status = '1' AND c2c.`show` = 1 ORDER BY c2c.customer_id ASC LIMIT ". $limit ."")->rows;
    }

    public function getOldPartnerByCountryId($filter){

        $limit = (($filter['page'] - 1) * $filter['page_size']) . "," . $filter['page_size'];

        if($filter['country']){
            if($this->customer->isLogged()){
                if($this->customer->isPartner()){
                    $sql = "SELECT c2c.*,co.name as country,companylocality FROM " . DB_PREFIX . "country co ," . DB_PREFIX . "customerpartner_to_customer c2c
                    LEFT JOIN ".DB_PREFIX ."customer c ON (c2c.customer_id = c.customer_id)
                    WHERE is_partner = 1  AND c.status = '1'  AND c.country_id=co.country_id AND co.iso_code_3='" .$filter['country']. "'
                    ORDER BY c2c.customer_id ASC LIMIT ". $limit ;
                    $countSql = "SELECT count(*) as total FROM " . DB_PREFIX . "country co ," . DB_PREFIX . "customerpartner_to_customer c2c
                    LEFT JOIN ".DB_PREFIX ."customer c ON (c2c.customer_id = c.customer_id)
                    WHERE is_partner = 1  AND c.status = '1'  AND c.country_id=co.country_id AND co.iso_code_3='" .$filter['country']. "'";

                    return [
                        'data' => $this->db->query($sql)->rows,
                        'total' => $this->db->query($countSql)->row['total']
                    ];
                }else{
                    $sql = "SELECT c2c.*,co.name as country,companylocality
FROM " . DB_PREFIX . "country co ," . DB_PREFIX . "customerpartner_to_customer c2c
LEFT JOIN ".DB_PREFIX ."customer c ON (c2c.customer_id = c.customer_id)
LEFT JOIN ( SELECT sum(quantity) as sellQty,customer_id from oc_customerpartner_to_order where date_added>DATE_SUB(CURDATE(),INTERVAL 30 DAY)  group by customer_id ) t on t.customer_id=c.customer_id
LEFT JOIN ( SELECT seller_id as seller_id from oc_buyer_to_seller where buyer_id= ".$this->customer->getId()." and buyer_control_status=1 and seller_control_status=1 and buy_status=1 ) t2 on t2.seller_id= c.customer_id
WHERE is_partner = 1  AND c.status = '1'  AND c.country_id=co.country_id AND co.iso_code_3='" .$filter['country']. "'
ORDER BY case when t2.seller_id is null then 1 else 0 end , case when c.customer_group_id =2 then 0 when c.customer_group_id=3 then 1 when c.customer_group_id in(17,18,19,20) then 4 else 3 end,t.sellQty desc LIMIT ". $limit;
                    $countSql = "SELECT count(*) as total
FROM " . DB_PREFIX . "country co ," . DB_PREFIX . "customerpartner_to_customer c2c
LEFT JOIN ".DB_PREFIX ."customer c ON (c2c.customer_id = c.customer_id)
LEFT JOIN ( SELECT sum(quantity) as sellQty,customer_id from oc_customerpartner_to_order where date_added>DATE_SUB(CURDATE(),INTERVAL 30 DAY)  group by customer_id ) t on t.customer_id=c.customer_id
LEFT JOIN ( SELECT seller_id as seller_id from oc_buyer_to_seller where buyer_id= ".$this->customer->getId()." and buyer_control_status=1 and seller_control_status=1 and buy_status=1 ) t2 on t2.seller_id= c.customer_id
WHERE is_partner = 1  AND c.status = '1'   AND c.country_id=co.country_id AND co.iso_code_3='" .$filter['country']. "'";
                    return [
                        'data' => $this->db->query($sql)->rows,
                        'total' => $this->db->query($countSql)->row['total']
                    ];
                }
            }else{
                $sql = "SELECT c2c.*,co.name as country,companylocality
FROM " . DB_PREFIX . "country co ," . DB_PREFIX . "customerpartner_to_customer c2c
LEFT JOIN ".DB_PREFIX ."customer c ON (c2c.customer_id = c.customer_id)
LEFT JOIN ( SELECT sum(quantity) as sellQty,customer_id from oc_customerpartner_to_order where date_added>DATE_SUB(CURDATE(),INTERVAL 30 DAY)  group by customer_id ) t on t.customer_id=c.customer_id
WHERE is_partner = 1  AND c.status = '1'  AND c.country_id=co.country_id AND co.iso_code_3='" .$filter['country']. "'
ORDER BY case when c.customer_group_id =2 then 0 when c.customer_group_id=3 then 1 when c.customer_group_id in(17,18,19,20) then 4 else 3 end,t.sellQty desc LIMIT ". $limit ;
                $countSql = "SELECT count(*) as total
FROM " . DB_PREFIX . "country co ," . DB_PREFIX . "customerpartner_to_customer c2c
LEFT JOIN ".DB_PREFIX ."customer c ON (c2c.customer_id = c.customer_id)
LEFT JOIN ( SELECT sum(quantity) as sellQty,customer_id from oc_customerpartner_to_order where date_added>DATE_SUB(CURDATE(),INTERVAL 30 DAY)  group by customer_id ) t on t.customer_id=c.customer_id
WHERE is_partner = 1  AND c.status = '1'  AND c.country_id=co.country_id AND co.iso_code_3='" .$filter['country']. "'";
                return [
                    'data' => $this->db->query($sql)->rows,
                    'total' => $this->db->query($countSql)->row['total']
                ];
            }
        }else{
            $sql = "SELECT *,co.name as country,companylocality
FROM " . DB_PREFIX . "customerpartner_to_customer c2c
LEFT JOIN ".DB_PREFIX ."customer c ON (c2c.customer_id = c.customer_id)
LEFT JOIN ".DB_PREFIX ."country co ON (c2c.country = co.iso_code_2)
WHERE is_partner = 1 AND c.status = '1' ORDER BY c2c.customer_id ASC LIMIT ". $limit;
            $countSql = "SELECT count(*)
FROM " . DB_PREFIX . "customerpartner_to_customer c2c
LEFT JOIN ".DB_PREFIX ."customer c ON (c2c.customer_id = c.customer_id)
LEFT JOIN ".DB_PREFIX ."country co ON (c2c.country = co.iso_code_2)
WHERE is_partner = 1 AND c.status = '1' ";
            return [
                'data' => $this->db->query($sql)->rows,
                'total' => $this->db->query($countSql)->row['total']
            ];
        }
    }
    //调用一次，统一结果
    public function jingXiHuaNoSeeStr($customer_id)
    {
        //精细化管理1，设置不可见
        $delicacyNoView = $this->orm->table('oc_delicacy_management_group AS dmg')
            ->join('oc_customerpartner_product_group_link AS pgl','pgl.product_group_id', '=', 'dmg.product_group_id')
            ->join('oc_customerpartner_buyer_group_link AS bgl','bgl.buyer_group_id', '=', 'dmg.buyer_group_id')
            ->select(['pgl.product_id'])
            ->whereRaw("dmg.status =1 and pgl.status=1 and bgl.status=1")
            ->whereRaw("bgl.buyer_id={$customer_id}")
            ->groupBy('pgl.product_id')
            ->get()->toArray();
        $delicacyNoView = $delicacyNoView ? array_column($delicacyNoView, 'product_id') : [];
        //精细化管理2，不显示价格
        $noPrice = $this->orm->table('oc_delicacy_management')
            ->select(['product_id'])
            ->whereRaw("buyer_id={$customer_id} AND product_display=0")
            ->get()->toArray();
        $noPrice = $noPrice ? array_column($noPrice, 'product_id') : [];
        return implode(',',array_unique($delicacyNoView + $noPrice));
    }
    //2020.06.17 店铺热销展示
    public function getSellerHotProductsByCostumerId($customer_id, $offset, $limit, $store_name = '')
    {
        $unavailable_products_id = $this->jingXiHuaNoSeeStr($customer_id);
        //获取购物去过的店铺：条件是可以买、是卖家
        $query = $this->orm->table('oc_product as p')
            ->leftjoin('oc_customerpartner_to_product as c2p', 'p.product_id', 'c2p.product_id')
            ->leftjoin('oc_product_to_store as p2s', 'p.product_id', 'p2s.product_id')
            ->leftjoin('oc_customer as c', 'c.customer_id', 'c2p.customer_id')
            ->leftjoin('oc_customerpartner_to_customer as c2c', 'c2c.customer_id', 'c2p.customer_id')
            ->selectRaw("ifnull(c2p.customer_id, 0) AS customer_id,COUNT(p.product_id) AS total")
            ->whereRaw("p.status=1 AND p.is_deleted=0 AND p.buyer_flag = 1 AND "
                . 'p2s.store_id=' . (int)$this->config->get('config_store_id') . ' AND p.product_type IN (0) AND c2c.show=1 AND c.status=1 '
            )->where(function ($q) use ($unavailable_products_id) {
                strlen($unavailable_products_id) && $q->whereRaw("p.product_id NOT IN({$unavailable_products_id})");
            })
            ->where('p.date_available', '<=', date('Y-m-d H:i:s', time()))
            ->groupBy(['c2p.customer_id']);
        $seller_id_rows = $this->orm->table('oc_buyer_to_seller AS bts')
            ->leftJoin('oc_customer AS c', 'bts.seller_id', '=', 'c.customer_id')
            ->leftJoin('oc_customerpartner_to_customer AS c2c', 'c.customer_id', '=', 'c2c.customer_id')
            ->leftJoin(
                new Expression('(' . get_complete_sql($query) . ') AS oso'),
                'oso.customer_id', '=', 'bts.seller_id'
            )
            ->selectRaw("c.customer_id,c.nickname,if(oso.total, bts.last_transaction_time, '0000-01-01') AS last_transaction_time,oso.total")
            ->whereRaw("bts.buyer_control_status=1 AND bts.seller_control_status=1 AND bts.buyer_id={$customer_id} AND c2c.is_partner=1 AND c.status=1")
            ->where(function ($query) use ($store_name) {
                strlen($store_name) && $query->where('c2c.screenname', 'LIKE', '%' . $store_name . '%');
            })
            ->orderBy('last_transaction_time', 'desc')
            ->orderBy('c.date_added', 'desc')
            ->offset($offset)->limit($limit)
            ->get()
            ->toArray();

        if (empty($seller_id_rows)) {
            return [];
        }
        $seller_ids = array_column($seller_id_rows, 'customer_id');
        //数据1：分页店铺
        $sellers = $this->orm->table('oc_customer AS c')
            ->leftJoin('oc_customerpartner_to_customer AS c2c', 'c2c.customer_id', '=', 'c.customer_id')
            ->select([
                'c.customer_id', 'c2c.companyname', 'c2c.companylogo', 'c2c.screenname', 'c2c.companybanner', 'c2c.avatar', 'c2c.performance_score',
                'c2c.backgroundcolor', 'c2c.customer_name', 'c2c.returns_rate', 'c2c.response_rate'
            ])
            ->whereRaw('c.customer_id IN (' . implode(',', $seller_ids) . ')')
            ->orderByRaw('FIELD(c.customer_id, ' . implode(',', $seller_ids) . ')')
            ->get()
            ->toArray();

        //1.1店铺计数
        $this->load->model('customerpartner/store_rate');
        $this->load->model('catalog/product');
        $this->load->model('catalog/search');
        $this->load->model('extension/module/product_show');
        $this->load->model('account/sellers');
        $seller_total_products = array_combine($seller_ids, array_column($seller_id_rows,'total'));
        //数据2：每个店铺的前5个
        //seller ReturnRate等级评定，类似产品等级 2020.06.18
        $seller_products = $this->model_catalog_product->get5HotDownloadProductsFromShop($seller_ids, $unavailable_products_id);
        $buyerToSellerRepo = app(BuyerToSellerRepository::class);
        for ($i = 0; $i < count($sellers); $i++) {
            $sellers[$i]->total = $seller_total_products[$sellers[$i]->customer_id] ?? 0;
            $sellers[$i]->products = $seller_products[$sellers[$i]->customer_id] ?? [];
            $sellers[$i]->main_category = $buyerToSellerRepo->getProductCountByCateNew($customer_id, $sellers[$i]->customer_id);
            $sellers[$i]->contactSellerLink = $this->url->link('message/seller/addMessage', '&receiver_id=' . $sellers[$i]->customer_id, true);
            //店铺退返率标签
            $store_return_rate_mark = $this->model_customerpartner_store_rate->returnsMarkByRate($sellers[$i]->returns_rate);
            $sellers[$i]->store_return_rate_mark = $store_return_rate_mark;
            //店铺回复率标签
            $store_response_rate_mark = $this->model_customerpartner_store_rate->responseMarkByRate($sellers[$i]->response_rate);
            $sellers[$i]->store_response_rate_mark = $store_response_rate_mark;
            $sellers[$i]->return_approval_rate = $this->model_catalog_product->returnApprovalRate($sellers[$i]->customer_id);
        }

        return obj2array($sellers);
    }

    public function getContactedPartnerByCountryId($customer_id,$country){

        $seller_id_rows = $this->db->query("SELECT  bts.seller_id
FROM oc_buyer_to_seller bts
	WHERE buyer_control_status =1 and seller_control_status =1 and bts.buyer_id = ".$customer_id)->rows;
        if(empty($seller_id_rows)){
            return [];
        }
        $seller_ids = array();
        foreach ($seller_id_rows as $id){
            $seller_ids[] = $id['seller_id'];
        }

        $limit = (int)$this->config->get('marketplace_seller_list_limit') ? (int)$this->config->get('marketplace_seller_list_limit') : 4;
        if($country){
            $partner_sql = "SELECT c.firstname,c.lastname,c2c.*,co.name as country,companylocality
FROM " . DB_PREFIX . "country co ," . DB_PREFIX . "customerpartner_to_customer c2c
LEFT JOIN ".DB_PREFIX ."customer c
	ON (c2c.customer_id = c.customer_id)
	WHERE  c.customer_id in (".implode(",",$seller_ids).") AND is_partner = 1  AND c.status = '1'  AND c2c.`show` = 1 AND c.country_id=co.country_id AND co.iso_code_3='" .$country. "' ORDER BY c2c.customer_id ASC LIMIT ". $limit;
        }else{
            $partner_sql =  "SELECT *,co.name as country,companylocality
FROM " . DB_PREFIX . "customerpartner_to_customer c2c
LEFT JOIN ".DB_PREFIX ."customer c
	ON (c2c.customer_id = c.customer_id)
LEFT JOIN ".DB_PREFIX ."country co
	ON (c2c.country = co.iso_code_2)
	WHERE  c.customer_id in (".implode(",",$seller_ids).") AND is_partner = 1 AND c.status = '1' AND c2c.`show` = 1 ORDER BY c2c.customer_id ASC LIMIT ". $limit;
        }

        return $this->db->query($partner_sql)->rows;
    }

    public function getProfile($customerid){
        //$sql = "SELECT c2c.*, c.*,c.firstname,c.lastname,co.name as country, a.address_1, a.address_2, a.city, a.postcode, a.country_id, a.zone_id, a.custom_field FROM " . DB_PREFIX . "customerpartner_to_customer c2c LEFT JOIN ".DB_PREFIX ."customer c ON (c2c.customer_id = c.customer_id) LEFT JOIN ".DB_PREFIX ."address a ON (c.address_id = a.address_id) LEFT JOIN ".DB_PREFIX ."country co ON (c.country_id = co.country_id) WHERE c2c.customer_id = '".(int)$customerid."' AND c2c.is_partner = '1' AND c.status = '1'";
        $ret = $this->orm->table('oc_customerpartner_to_customer as c2c')
                ->leftJoin('oc_customer as c','c2c.customer_id','=','c.customer_id')
                ->leftJoin('oc_address as a','c.address_id','=','a.address_id')
                ->leftJoin('oc_country as co','c.country_id','=','co.country_id')
                ->where([
                    'c2c.customer_id'=>$customerid,
                    'c2c.is_partner'=>1,
                    'c.STATUS'=>1,
                ])
                ->selectRaw('c2c.*,
                    c.*,
                    c.firstname,
                    c.lastname,
                    co.NAME AS country,
                    a.address_1,
                    a.address_2,
                    a.city,
                    a.postcode,
                    a.country_id,
                    a.zone_id,
                    a.custom_field
                ')
                ->get()
                ->map(function ($v){
                    $v = (array)$v;
                    if(ord($v['shortprofile']) == self::END_OF_TEXT){  //END of TEXT
                        $v['shortprofile'] = null;
                    }
                    return (array)$v;
                })
                ->toArray();
        return current($ret);
    }

    public function getInfoByProductId($productId)
    {
        $query = $this->orm->table('oc_customer AS c')
            ->leftJoin('oc_customerpartner_to_product AS c2p', 'c2p.customer_id', '=', 'c.customer_id')
            ->where([
                ['c2p.product_id', '=', $productId]
            ])
            ->select(['c.*'])
            ->first();
        return obj2array($query);
    }

    public function getFeedbackList($customerid) {
        $sql = "SELECT c2f.* FROM " . DB_PREFIX . "customerpartner_to_feedback c2f LEFT JOIN ".DB_PREFIX ."customer c ON (c2f.customer_id = c.customer_id) LEFT JOIN ".DB_PREFIX ."customerpartner_to_customer cpc ON (cpc.customer_id = c.customer_id) where c2f.seller_id = '".(int)$customerid."' AND c2f.status = '1'";
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getTotalFeedback($customerid){
        $query = $this->db->query("SELECT id FROM " . DB_PREFIX . "customerpartner_to_feedback c2f where c2f.seller_id='".(int)$customerid."' AND c2f.status = '1'");
        return count($query->rows);
    }

    public function getAverageFeedback($customerid, $field_id = 0){

        $sql = "SELECT round(AVG(field_value)) avg  FROM `" . DB_PREFIX . "wk_feedback_attribute_values` WHERE feedback_id IN (SELECT id FROM `".DB_PREFIX."customerpartner_to_feedback` WHERE seller_id='".(int)$customerid."' AND status = '1')";

        if ($field_id) {
            $sql .= " AND field_id = ".(int)$field_id;
        }

        $avg = $this->db->query($sql)->row;

        if (isset($avg['avg'])) {
            return $avg['avg'];
        }

        return 0;
    }

    public function getProductFeedbackList($customerid) {
        //14052 【需求优化】产品评论功能隐藏Buyer昵称和编码
        $query = $this->db->query("SELECT r.*,pd.name,r.text FROM " . DB_PREFIX . "customerpartner_to_product c2p INNER JOIN ".DB_PREFIX ."review r ON (c2p.product_id = r.product_id) LEFT JOIN ".DB_PREFIX."product_description pd ON (pd.product_id = c2p.product_id) WHERE c2p.customer_id = '".(int)$customerid."' AND pd.language_id = '".(int)$this->config->get('config_language_id')."' AND r.status = 1 order by r.date_added desc");
        return $query->rows;
    }

    public function getTotalProductFeedbackList($customerid){
        $query = $this->db->query("SELECT r.* FROM " . DB_PREFIX . "customerpartner_to_product c2p INNER JOIN ".DB_PREFIX ."review r ON (c2p.product_id = r.product_id) WHERE c2p.customer_id = '".(int)$customerid."' AND r.status = 1 ");
        return count($query->rows);
    }


    public function saveFeedback($data,$seller_id){

        $feedback_id = 0;

        $result = $this->db->query("SELECT id FROM ".DB_PREFIX ."customerpartner_to_feedback WHERE customer_id = ".(int)$this->customer->getId()." AND seller_id = '".(int)$seller_id."'")->row;

        if(!$result){
            $this->db->query("INSERT INTO ".DB_PREFIX ."customerpartner_to_feedback SET customer_id = '".(int)$this->customer->getId()."',seller_id = '".(int)$seller_id."', nickname = '".$this->db->escape($data['name'])."',  review = '".$this->db->escape($data['text'])."', createdate = NOW(), status = '0'");
            $feedback_id = $this->db->getLastId();
        }else{
            $this->db->query("UPDATE ".DB_PREFIX ."customerpartner_to_feedback set nickname='".$this->db->escape($data['name'])."', review='".$this->db->escape($data['text'])."',createdate = NOW(), status = '0' WHERE id = '".$result['id']."'");
            $feedback_id = $result['id'];
        }

        if ($feedback_id && isset($data['review_attributes']) && is_array($data['review_attributes']) && !empty($data['review_attributes'])) {
            foreach ($data['review_attributes'] as $key => $value) {
                if ($this->db->query("SELECT * FROM ".DB_PREFIX."wk_feedback_attribute WHERE field_id=".$key)->row) {
                    $this->db->query("DELETE FROM " . DB_PREFIX . "wk_feedback_attribute_values WHERE feedback_id = '" . (int)$feedback_id . "' AND field_id=".$key);

                    $this->db->query("INSERT INTO `" . DB_PREFIX . "wk_feedback_attribute_values` SET `feedback_id` = '" . (int)$this->db->escape($feedback_id) . "', `field_id` = '" . (int)$this->db->escape($key) . "',  field_value = '" . $this->db->escape($value) . "'");
                }
            }
        }
    }

    public function getShopData($shop){
        $sql = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_customer where companyname = '" .$this->db->escape($shop)."'")->row;
        if($sql)
            return $sql;
        return false;
    }
    public function getShopDataByScreenname($screenname){
        $sql = $this->db->query("SELECT * FROM " . DB_PREFIX . "customerpartner_to_customer where screenname = '" .$this->db->escape($screenname)."'")->row;
        if($sql)
            return $sql;
        return false;
    }

    public function checkCustomerBought($seller_id){

        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` o LEFT JOIN " . DB_PREFIX . "customerpartner_to_order c2o ON (o.order_id = c2o.order_id) where  o.customer_id = '" .$this->db->escape((int)$this->customer->getId())."' AND c2o.customer_id = '" . (int)$this->db->escape($seller_id) . "'")->row;

        return $query;
    }

    /**
     * [getAllAverageFeedback uses to fetch average feedprice, feedvalue, feedquality of the seller]
     * @return [type] [array]
     */
    public function getAllAverageFeedback($seller_id,$field_id = 0){
        $avg = $this->db->query("SELECT round(AVG(field_value)) avg  FROM `" . DB_PREFIX . "wk_feedback_attribute_values` WHERE field_id	= ". (int)$field_id ." AND feedback_id IN (SELECT id FROM `".DB_PREFIX."customerpartner_to_feedback` WHERE seller_id='".(int)$seller_id."' AND status = '1')")->row;

        if (isset($avg['avg'])) {
            return $avg['avg'];
        }

        return 0;
    }

    public function getReviewField($reviewfield_id) {

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "wk_feedback_attribute WHERE field_id = '" . (int)$reviewfield_id . "'");

        return $query->row;
    }

    public function getReviewFieldByName($reviewfield_name) {

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "wk_feedback_attribute WHERE field_name = '" . $this->db->escape($reviewfield_name) . "'");

        return $query->row;
    }

    public function getAllReviewFields() {
        $sql = "SELECT * FROM " . DB_PREFIX . "wk_feedback_attribute WHERE field_status = 1";

        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function getReviewAttributeValue($feedback_id = 0, $field_id = 0) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "wk_feedback_attribute_values WHERE feedback_id = '" . (int)$feedback_id . "' AND field_id = ".(int)$field_id);

        return $query->row;
    }

}
