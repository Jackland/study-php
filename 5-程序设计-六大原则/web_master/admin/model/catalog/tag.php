<?php

/**
 * Class ModelCatalogTag
 */
class ModelCatalogTag extends Model
{
    public function getAllPromotions()
    {
        $sql = "SELECT * FROM oc_promotions";
        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function savePromotions($data)
    {
        $saved_promotion_id = array();
        if (isset($data['tag_value'])) {
            foreach ($data['tag_value'] as $tag_value) {
                if ($tag_value['promotions_id']) {
                    $this->db->query(
                        "UPDATE oc_promotions SET " .
                        "`name` = '" . $this->db->escape($tag_value['name']) .
                        "',image = '" . $this->db->escape($tag_value['image']) .
                        "',link = '" . $this->db->escape($tag_value['link']) .
                        "',sort_order = " . (int)$tag_value['sort_order'] .
                        ",self_support = " . (int)$tag_value['self_support'] .
                        ",promotions_status = " . (int)$tag_value['promotions_status'] .
                        " WHERE promotions_id = " . (int)$tag_value['promotions_id']);
                    $saved_promotion_id[] = (int)$tag_value['promotions_id'];
                    if($tag_value['promotions_status'] == '0'){
                        $disable_pts_sql = "UPDATE `oc_promotions_to_seller` SET `status` = 0 WHERE promotions_id = " . (int)$tag_value['promotions_id'];
                        $this->db->query($disable_pts_sql);
                    }
                } else {
                    $this->db->query(
                        "INSERT INTO oc_promotions (`name`,image,link,sort_order,self_support,promotions_status) 
                        VALUE ('"
                        . $this->db->escape($tag_value['name']) . "','"
                        . $this->db->escape($tag_value['image']) . "','"
                        . $this->db->escape($tag_value['link']) . "',"
                        . (int)$tag_value['sort_order'] . ","
                        . (int)$tag_value['self_support'] . ","
                        . (int)$tag_value['promotions_status']
                        . ")"
                    );
                    $saved_promotion_id[] = $this->db->getLastId();
                }
            }

            $p_delete_sql = "DELETE FROM `oc_promotions`";
            $pd_delete_sql = "DELETE FROM `oc_promotions_description`";
            $pts_delete_sql = "DELETE FROM `oc_promotions_to_seller`";
            if(!empty($saved_promotion_id)){
                $p_delete_sql .= " WHERE promotions_id NOT IN (" . implode($saved_promotion_id, ',') . ")";
                $pd_delete_sql .= " WHERE promotions_id NOT IN (" . implode($saved_promotion_id, ',') . ")";
                $pts_delete_sql .= " WHERE promotions_id NOT IN (" . implode($saved_promotion_id, ',') . ")";
            }
            $this->db->query($p_delete_sql);
            $this->db->query($pd_delete_sql);
            $this->db->query($pts_delete_sql);
        }
    }
}