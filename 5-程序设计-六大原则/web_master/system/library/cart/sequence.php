<?php

namespace Cart;
class Sequence
{
    // 构造器
    public function __construct($registry)
    {
        $this->config = $registry->get('config');
        $this->db = $registry->get('db');
        $this->request = $registry->get('request');
        $this->session = $registry->get('session');
    }

    /**
     * 获取YZC_ORDER_ID,并更新序列表
     * @return string YZC_ORDER_ID
     */
    public function getYzcOrderId()
    {
        // 获取序列
        $sql = "SELECT tss.`seq_value` AS yzc_order_id FROM `tb_sys_sequence` tss WHERE tss.`seq_key` = 'tb_sys_customer_sales_order|YzcOrderId'";
        $result = $this->db->query($sql);
        $id = $result->row['yzc_order_id'] + 1;
        $yzc_order_id = "YC-" . $id;
        // 更新序列
        $sql = "UPDATE `tb_sys_sequence` SET seq_value = " . $id . ",update_time = NOW() WHERE seq_key = 'tb_sys_customer_sales_order|YzcOrderId'";
        $this->db->query($sql);
        return $yzc_order_id;
    }

    public function getYzcOrderIdNumber()
    {
        // 获取序列
        $sql = "SELECT tss.`seq_value` AS yzc_order_id FROM `tb_sys_sequence` tss WHERE tss.`seq_key` = 'tb_sys_customer_sales_order|YzcOrderId'";
        $result = $this->db->query($sql);
        return $result->row['yzc_order_id'];
    }

    public function updateYzcOrderIdNumber($id)
    {
        // 更新序列
        $sql = "UPDATE `tb_sys_sequence` SET seq_value = " . $id . ",update_time = NOW() WHERE seq_key = 'tb_sys_customer_sales_order|YzcOrderId'";
        $this->db->query($sql);
    }

    public function getCloudLogisticsOrderIdNumber()
    {
        // 获取序列
        $sql = "SELECT tss.`seq_value` AS cloud_order_id FROM `tb_sys_sequence` tss WHERE tss.`seq_key` = 'tb_sys_customer_sales_order|CloudOrderId'";
        $result = $this->db->query($sql);
        return $result->row['cloud_order_id'];
    }

    public function updateCloudLogisticsOrderIdNumber($id)
    {
        // 更新序列
        $sql = "UPDATE `tb_sys_sequence` SET seq_value = " . $id . ",update_time = NOW() WHERE seq_key = 'tb_sys_customer_sales_order|CloudOrderId'";
        $this->db->query($sql);
    }
}