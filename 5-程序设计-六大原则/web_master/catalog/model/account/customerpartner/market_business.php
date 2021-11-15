<?php

/**
 * Class ModelAccountCustomerpartnerMarketBusiness
 */
class ModelAccountCustomerpartnerMarketBusiness extends Model
{
    public function getAllMarketBusinessTag($seller_id)
    {
        $sql = "SELECT op.promotions_id,op.`name` AS tag_name,op.`promotions_status`,op.`self_support`,pts.`status` FROM oc_promotions op LEFT JOIN oc_promotions_to_seller pts ON op.promotions_id = pts.promotions_id
                WHERE pts.seller_id = " . (int)$seller_id . " ORDER BY op.promotions_id";
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getPromotions($promotion_id = null)
    {
        $sql = "SELECT promotions_id,`name`,promotions_status,self_support FROM oc_promotions";
        if (isset($promotion_id)) {
            $sql .= " WHERE promotions_id = " . (int)$promotion_id;
        }
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getSellerPromotions($promotion_id, $seller_id)
    {
        $sql = "SELECT promotions_id,`status` FROM oc_promotions_to_seller WHERE promotions_id = " . (int)$promotion_id . " AND seller_id = " . (int)$seller_id;
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function saveSellerPromotionRelation($data)
    {
        if (!empty($data)) {
            foreach ($data as $relation) {
                $sql = "INSERT INTO `oc_promotions_to_seller` (promotions_id,seller_id,`status`) 
                    VALUE ("
                    . (int)$relation['promotion_id'] . ","
                    . (int)$relation['seller_id'] . ","
                    . (int)$relation['seller_status'] . ") 
                    ON DUPLICATE KEY 
                    UPDATE 
                    `status`= " . (int)$relation['seller_status'];
                $this->db->query($sql);
            }
        }
    }

    public function getMarketDescription($seller_id, $promotion_id)
    {
        $sql = "SELECT p.`product_id`,p.`mpn`,p.`sku`,pd.`description` FROM oc_product p INNER JOIN oc_promotions_description pd ON p.`product_id` = pd.`product_id`
          INNER JOIN oc_promotions_to_seller pts ON pts.`promotions_id` = pd.`promotions_id`
          INNER JOIN oc_customerpartner_to_product ctp 
          ON ctp.`customer_id` = pts.`seller_id` AND ctp.`product_id` = p.`product_id`
          WHERE p.`is_deleted` = 0 AND pts.`seller_id` = " . (int)$seller_id . " AND pts.promotions_id = " . (int)$promotion_id;

        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getProductInformationForPromotion($seller_id, $mpn)
    {
        $sql = "SELECT p.`product_id`,p.`sku` FROM oc_customerpartner_to_product ctp INNER JOIN oc_product p ON p.`product_id` = ctp.`product_id`
                WHERE p.`mpn` = '" . $this->db->escape($mpn) . "' AND ctp.`customer_id` = " . (int)$seller_id . " AND p.`is_deleted` = 0";

        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function savePromotionDescription($seller_id, $data)
    {
        $saved_product_id = array();
        if (isset($data['promotion_id']) && isset($data['seller_market_status']) && isset($seller_id)) {
            $promotion_id = $data['promotion_id'];
            $update_sql = "UPDATE oc_promotions_to_seller SET `status` = " . (int)$data['seller_market_status'] . " 
            WHERE promotions_id = " . (int)$promotion_id . " AND seller_id = " . (int)$seller_id;
            $this->db->query($update_sql);

            if(isset($data['promotion_value'])){
                foreach ($data['promotion_value'] as $promotion_value) {
                    if(empty($promotion_value['product_id'])){
                        continue;
                    }
                    $sql = "INSERT INTO `oc_promotions_description` (promotions_id,product_id,description) 
                    VALUE ("
                        . (int)$promotion_id . ","
                        . (int)$promotion_value['product_id'] . ",'"
                        . $this->db->escape($promotion_value['description']) . "') 
                    ON DUPLICATE KEY 
                    UPDATE 
                    `description`= '" . $this->db->escape($promotion_value['description']) . "'";
                    $this->db->query($sql);

                    $saved_product_id[] = (int)$promotion_value['product_id'];
                }
            }
            $delete_sql = "DELETE pd.* FROM oc_promotions_description pd,oc_customerpartner_to_product ctp
                     WHERE ctp.product_id = pd.product_id 
                     AND ctp.customer_id = " . (int)$seller_id . " 
                     AND pd.promotions_id = " . (int)$promotion_id;
            if (!empty($saved_product_id)) {
                $delete_sql .= " AND pd.product_id NOT IN (" . implode($saved_product_id, ',') . ")";
            }
            $this->db->query($delete_sql);
        }
    }
}