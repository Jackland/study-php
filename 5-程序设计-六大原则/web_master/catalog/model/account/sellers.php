<?php

/**
 * Class ModelAccountSellers
 */
class ModelAccountSellers extends Model
{
    public function getSellersByCustomerId($customer_id, $filter_data)
    {
        $sql = "SELECT
                bts.*,
                ctc.`screenname` AS seller_name,
                sc.`email` AS seller_email,
                CONCAT(cc.`firstname`,' ',cc.`lastname`) AS buyer_name,
                cc.`email` AS buyer_email
                FROM `" . DB_PREFIX . "buyer_to_seller` bts
                LEFT JOIN `" . DB_PREFIX . "customer` sc ON bts.`seller_id` = sc.`customer_id`
                LEFT JOIN `" . DB_PREFIX . "customer` cc ON bts.`buyer_id` = cc.`customer_id`
                LEFT JOIN " . DB_PREFIX . "customerpartner_to_customer ctc ON sc.`customer_id` = ctc.`customer_id`
                WHERE bts.`buyer_id` = " . $customer_id;
        if (isset($filter_data)) {
            if (isset($filter_data["filter_seller_name"]) && $filter_data["filter_seller_name"] != "") {
                $filter_data["filter_seller_name"] = str_replace('%', '\%', $filter_data["filter_seller_name"]);
//                $sql .= " AND (sc.`firstname` LIKE '%" . $filter_data["filter_seller_name"] . "%' OR sc.`lastname` LIKE '%" . $filter_data["filter_seller_name"] . "%') ";
                $sql .= " AND replace(ctc.screenname,' ','') LIKE '%" . str_replace(' ', '', $this->db->escape($filter_data["filter_seller_name"])) . "%' ";
            }
            if (isset($filter_data["filter_seller_email"]) && $filter_data["filter_seller_email"] != "") {
                $sql .= " AND (sc.`email` LIKE '%" . $filter_data["filter_seller_email"] . "%') ";
            }
            if (isset($filter_data['filter_status']) && $filter_data["filter_status"] != "") {
                $sql .= " AND (bts.`buyer_control_status` = " . (int)$filter_data["filter_status"] . ")";
            }
            $sql .= " ORDER BY " . $filter_data["sort"] . " " . $filter_data["order"]." , bts.last_transaction_time DESC";
            if (isset($filter_data['page_num']) && $filter_data['page_limit']) {
                $sql .= " LIMIT " . (($filter_data['page_num'] - 1) * $filter_data['page_limit']) . "," . $filter_data['page_limit'];
            }
        }
        return $this->db->query($sql);
    }

    public function getSellersTotalByCustomerId($customer_id, $filter_data)
    {
        $sql = "SELECT COUNT(*) as total FROM `" . DB_PREFIX . "buyer_to_seller` bts
                LEFT JOIN `" . DB_PREFIX . "customer` sc ON bts.`seller_id` = sc.`customer_id`
                LEFT JOIN `" . DB_PREFIX . "customer` cc ON bts.`buyer_id` = cc.`customer_id`
                LEFT JOIN " . DB_PREFIX . "customerpartner_to_customer ctc ON sc.`customer_id` = ctc.`customer_id`
                WHERE bts.`buyer_id` = " . $customer_id;
        if (isset($filter_data)) {
            if (isset($filter_data["filter_seller_name"]) && $filter_data["filter_seller_name"] != "") {
                $filter_data["filter_seller_name"] = str_replace('%', '\%', $filter_data["filter_seller_name"]);
//                $sql .= " AND (sc.`firstname` LIKE '%" . $filter_data["filter_seller_name"] . "%' OR sc.`lastname` LIKE '%" . $filter_data["filter_seller_name"] . "%') ";
                $sql .= " AND replace(ctc.screenname,' ','') LIKE '%" . str_replace(' ', '', $this->db->escape($filter_data["filter_seller_name"])) . "%' ";
            }
            if (isset($filter_data["filter_seller_email"]) && $filter_data["filter_seller_email"] != "") {
                $sql .= " AND (sc.`email` LIKE '%" . $filter_data["filter_seller_email"] . "%') ";
            }
            if (isset($filter_data['filter_status']) && $filter_data["filter_status"] != "") {
                $sql .= " AND (bts.`buyer_control_status` = " . (int)$filter_data["filter_status"] . ")";
            }
//            $sql .= " ORDER BY " . $filter_data["sort"] . " " . $filter_data["order"];
        }
        return $this->db->query($sql)->row['total'];
    }

    public function updateSellerInfo($updateData)
    {
        $this->orm
            ->table(DB_PREFIX . "buyer_to_seller")
            ->where('id', $updateData['id'])
            ->update([
                'buyer_control_status' => $updateData['buyer_control_status'],
            ]);
    }

}