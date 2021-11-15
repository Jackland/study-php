<?php

/**
 * Class ModelAccountInboundManagement
 */
class ModelAccountInboundManagement extends Model
{

    public function getReceiptsOrderById($id)
    {
        $sql = "SELECT * FROM `tb_sys_receipts_order` sro WHERE sro.`receive_order_id` = " . $id;
        return $this->db->query($sql)->row;
    }

    public function getReceiptsOrderDetailByHeaderId($id)
    {
        $sql = "SELECT sro.*, pd.`name` AS product_name,sr.`currency` FROM `tb_sys_receipts_order_detail` sro LEFT JOIN `tb_sys_receipts_order` sr ON sro.receive_order_id=sr.receive_order_id LEFT JOIN `oc_product_description` pd ON sro.`product_id` = pd.`product_id` WHERE sro.`receive_order_id` = " . $id;
        return $this->db->query($sql)->rows;
    }

    public function getReceiptsOrderCount($filter_data)
    {
        $sql = "SELECT COUNT(*) AS total FROM `tb_sys_receipts_order` sro WHERE 1 = 1 AND sro.`customer_id` = " . $filter_data['customer_id'];
        if (isset($filter_data['filter_inboundOrderNumber'])) {
            $sql .= " AND sro.`receive_number` LIKE '%" . $this->db->escape($filter_data['filter_inboundOrderNumber']) . "%'";
        }
        if (isset($filter_data['filter_estimatedDateStart'])) {
            $sql .= " AND sro.`expected_date` >= '" . $this->db->escape($filter_data['filter_estimatedDateStart']) . "'";
        }
        if (isset($filter_data['filter_estimatedDateEnd'])) {
            $sql .= " AND sro.`expected_date` <= '" . $this->db->escape($filter_data['filter_estimatedDateEnd']) . "'";
        }
        if (isset($filter_data['filter_inboundOrderStatus'])) {
            $sql .= " AND sro.`status` = " . $this->db->escape($filter_data['filter_inboundOrderStatus']);
        }
        if (isset($filter_data['filter_containerNumber'])) {
            $sql .= " AND sro.`container_code` LIKE '%" . $this->db->escape($filter_data['filter_containerNumber']) . "%'";
        }
        if (isset($filter_data['filter_receiptDateStart'])) {
            $sql .= " AND sro.`receive_date` >= '" . $this->db->escape($filter_data['filter_receiptDateStart']) . "'";
        }
        if (isset($filter_data['filter_receiptDateEnd'])) {
            $sql .= " AND sro.`receive_date` <= '" . $this->db->escape($filter_data['filter_receiptDateEnd']) . "'";
        }
        if (isset($filter_data['filter_shippingWay'])) {
            $sql .= " AND sro.`shipping_way` = " . $this->db->escape($filter_data['filter_shippingWay']);
        }
        return $this->db->query($sql)->row['total'];
    }

    public function getReceiptsOrders($filter_data)
    {
        $sql = "SELECT * FROM `tb_sys_receipts_order` sro WHERE 1 = 1 AND sro.`customer_id` = " . $filter_data['customer_id'];
        if (isset($filter_data['filter_inboundOrderNumber'])) {
            $sql .= " AND sro.`receive_number` LIKE '%" . $filter_data['filter_inboundOrderNumber'] . "%'";
        }
        if (isset($filter_data['filter_estimatedDateStart'])) {
            $sql .= " AND sro.`expected_date` >= '" . $filter_data['filter_estimatedDateStart'] . "'";
        }
        if (isset($filter_data['filter_estimatedDateEnd'])) {
            $sql .= " AND sro.`expected_date` <= '" . $filter_data['filter_estimatedDateEnd'] . "'";
        }
        if (isset($filter_data['filter_inboundOrderStatus'])) {
            $sql .= " AND sro.`status` = " . $filter_data['filter_inboundOrderStatus'];
        }
        if (isset($filter_data['filter_containerNumber'])) {
            $sql .= " AND sro.`container_code` LIKE '%" . $filter_data['filter_containerNumber'] . "%'";
        }
        if (isset($filter_data['filter_receiptDateStart'])) {
            $sql .= " AND sro.`receive_date` >= '" . $filter_data['filter_receiptDateStart'] . "'";
        }
        if (isset($filter_data['filter_receiptDateEnd'])) {
            $sql .= " AND sro.`receive_date` <= '" . $filter_data['filter_receiptDateEnd'] . "'";
        }
        if (isset($filter_data['filter_shippingWay'])) {
            $sql .= " AND sro.`shipping_way` = " . $filter_data['filter_shippingWay'];
        }
        if (isset($filter_data['start']) || isset($filter_data['limit'])) {
            if ($filter_data['start'] < 0) {
                $filter_data['start'] = 0;
            }

            if ($filter_data['limit'] < 1) {
                $filter_data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$filter_data['start'] . "," . (int)$filter_data['limit'];
        }
        return $this->db->query($sql)->rows;
    }
}