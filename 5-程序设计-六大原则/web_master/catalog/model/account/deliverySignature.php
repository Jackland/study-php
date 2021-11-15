<?php

use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Services\Order\OrderService;

/**
 * Class ModelAccountDeliverySignature
 * Created by IntelliJ IDEA.
 * User: chenyang
 * Date: 2019/5/30
 * Time: 17:26
 */
class ModelAccountDeliverySignature extends Model
{
    /**
     * 查询签收服务费商品的信息
     * @param int $country_id
     * @return mixed
     */
    public function getDeliverySignatureProduct($country_id)
    {
        $customId = $this->customer->getId();
        if(!isset($customId)){
            $customId = session('customer_id');
        }
        $sql = "SELECT
                  p.product_id,
                  p.sku,
                  p.price,
                  pd.name,
                  ctc.screenname
                FROM
                  oc_product p
                  INNER JOIN oc_customerpartner_to_product ctp
                    ON p.product_id = ctp.product_id
                  INNER JOIN oc_product_description pd
                    ON p.product_id = pd.product_id
                  INNER JOIN oc_customer c
                    ON c.customer_id = ctp.customer_id
                  INNER JOIN oc_customerpartner_to_customer ctc
                    ON ctc.customer_id = ctp.customer_id
                  INNER JOIN oc_buyer_to_seller b2s
                  ON b2s.seller_id = ctp.customer_id
                WHERE p.sku = '" . $this->db->escape($this->config->get('signature_service_us_sku')) . "'
                  AND c.email LIKE '%service%@gigacloudlogistics.com%'
                  AND p.status = 1
                  AND c.country_id = " . (int)$country_id . "
                  AND b2s.buyer_id = " . (int)$customId ."
                  AND b2s.buy_status = 1 and b2s.buyer_control_status = 1 and b2s.seller_control_status = 1";

        $query = $this->db->query($sql);
        return $query->row;
    }

    /**
     * 根据订单主键ID和用户ID查询对应的服务费信息
     *
     * @param int $header_id  销售订单主键ID
     * @param int $customer_id   当前用户id，匹配buyerId
     * @return mixed
     */
    public function getDeliverySignatureByHeaderId($header_id, $customer_id)
    {
        $sql = "SELECT
                  cso.order_id,
                  oa.sales_order_line_id AS lineId,
                  oa.qty,
                  oa.product_id,
                  p.sku,
                  p.image,
                  pd.name,
                  oc.qty AS setQty
                FROM
                  tb_sys_customer_sales_order cso
                  INNER JOIN tb_sys_order_associated oa
                    ON cso.id = oa.sales_order_id
                  INNER JOIN oc_product p
                    ON p.product_id = oa.product_id
                  INNER JOIN oc_product_description pd
                    ON oa.product_id = pd.product_id
                  LEFT JOIN tb_sys_order_combo oc
                    ON (
                      oc.order_product_id = oa.order_product_id
                      AND oc.product_id = oa.product_id
                    )
                WHERE oa.sales_order_id = " . (int)$header_id . " AND oa.buyer_id = " . (int)$customer_id;

        $query = $this->db->query($sql);
        return $query->rows;
    }

    /**
     * 获取订单的包裹总数量(不考虑绑定关系中的服务费商品)
     * @param int $header_id
     * @param int $customer_id
     * @param $service_product_id
     * @return float|int
     */
    public function getASRPackageTotalByHeaderId($header_id, $customer_id,$service_product_id)
    {
        $sql = "SELECT
                  oa.qty,
                  oc.qty AS setQty
                FROM
                  tb_sys_customer_sales_order cso
                  INNER JOIN tb_sys_order_associated oa
                    ON cso.id = oa.sales_order_id
                  INNER JOIN oc_product p
                    ON p.product_id = oa.product_id
                  INNER JOIN oc_product_description pd
                    ON oa.product_id = pd.product_id
                  LEFT JOIN tb_sys_order_combo oc
                    ON (
                      oc.order_product_id = oa.order_product_id
                      AND oc.product_id = oa.product_id
                    )
                WHERE oa.sales_order_id = " . (int)$header_id . " AND oa.buyer_id = " . (int)$customer_id . " AND oa.product_id <> " . (int)$service_product_id ;

        $query = $this->db->query($sql);
        $package_total = 0;
        if(isset($query->rows) && !empty($query->rows)){
            foreach($query->rows as $row){
                if(!isset($row['setQty'])){
                    $set_qty = 1;
                }else{
                    $set_qty = intval($row['setQty']);
                }
                $qty = intval($row['qty']);
                $package_total += $qty * $set_qty;
            }
        }

        if ($package_total == 0) {
            $package_total = $this->getNewOrderAsrPackageTotal($header_id, $customer_id);
        }
        return $package_total;
    }

    /**
     * 获取无绑定关系时的订单签收服务费商品总数
     * @param int $header_id
     * @param int $customer_id
     * @return float|int
     */
    public function getNewOrderAsrPackageTotal($header_id,$customer_id){
        $sql = "SELECT
                  p.product_id AS productId,
                  csol.seller_id AS orderSeller,
                  ctp.customer_id AS productSeller,
                  csol.id AS lineId,
                  csol.qty,
                  psi.qty AS setQty
                FROM
                  tb_sys_customer_sales_order cso
                  INNER JOIN tb_sys_customer_sales_order_line csol
                    ON cso.id = csol.header_id
                  INNER JOIN oc_product p
                    ON p.sku = csol.item_code
                  INNER JOIN oc_customerpartner_to_product ctp
                    ON ctp.product_id = p.product_id
                  INNER JOIN oc_buyer_to_seller b2s
                    ON b2s.buyer_id = cso.buyer_id
                    AND b2s.seller_id = ctp.customer_id
                  LEFT JOIN vw_delicacy_management dm
                    ON p.product_id = dm.product_id
                    AND NOW() < dm.expiration_time
                    AND dm.buyer_id = cso.buyer_id
                  LEFT JOIN tb_sys_product_set_info psi
                    ON psi.product_id = p.product_id
                WHERE cso.id = ".(int)$header_id."
                  AND cso.buyer_id = ".(int)$customer_id."
                  AND p.status = 1
                  AND p.buyer_flag = 1
                  AND b2s.buy_status = 1
                  AND b2s.buyer_control_status = 1
                  AND b2s.seller_control_status = 1
                  AND (
                    dm.product_display = 1
                    OR dm.product_display IS NULL
                  )
                ORDER BY p.product_id";
        $query = $this->db->query($sql);
        $package_total = 0;
        if(isset($query->rows) && !empty($query->rows)){
            $line_record = array();
            $product_record = array();
            foreach($query->rows as $row){
                if(isset($row['orderSeller']) && $row['orderSeller'] != $row['productSeller']){
                    continue;
                }
                $pr_key = $row['productId'].'-'.$row['lineId'];
                $line_id = $row['lineId'];
                if(!in_array($line_id,$line_record) || in_array($pr_key,$product_record)){
                    if(!in_array($line_id,$line_record)){
                        $line_record[] = $line_id;
                    }
                    if(!in_array($pr_key,$product_record)){
                        $product_record[] = $pr_key;
                    }
                    if(!isset($row['setQty'])){
                        $set_qty = 1;
                    }else{
                        $set_qty = intval($row['setQty']);
                    }
                    $qty = intval($row['qty']);
                    $package_total += $qty * $set_qty;
                }else{
                    continue;
                }
            }
        }
        return $package_total;
    }

    /**
     * 获取最先的待支付签收服务费状态的订单
     * @param int $customer_id
     * @return mixed
     */
    public function getOldestAsrOrder($customer_id){
        $sql = "SELECT cso.id,cso.order_id FROM tb_sys_customer_sales_order cso WHERE cso.order_status = ".CustomerSalesOrderStatus::ASR_TO_BE_PAID." AND cso.buyer_id = ".(int)$customer_id." ORDER BY cso.id ASC LIMIT 1";
        $query = $this->db->query($sql);
        return $query->row;
    }

    /**
     * 以订单主键ID获取订单包裹数量信息
     * @param int $header_id
     * @param int $customer_id
     * @return array
     */
    function getOrderPackageQtyInfo($header_id, $customer_id)
    {
        $package_qty_array = array();
        $sql = "SELECT
                  oa.sales_order_line_id AS lineId,
                  oa.qty,
                  oc.qty AS setQty
                FROM
                  tb_sys_customer_sales_order cso
                  INNER JOIN tb_sys_order_associated oa
                    ON cso.id = oa.sales_order_id
                  LEFT JOIN tb_sys_order_combo oc
                    ON (
                      oc.order_product_id = oa.order_product_id
                      AND oc.product_id = oa.product_id
                    )
                WHERE oa.sales_order_id = " . (int)$header_id . " AND oa.buyer_id = " . (int)$customer_id;

        $query = $this->db->query($sql);
        if (isset($query->rows)) {
            foreach ($query->rows as $row) {
                $set_qty = isset($row['setQty']) ? $row['setQty'] : 1;
                $package_qty = intval($row['qty']) * intval($set_qty);
                if(isset($package_qty_array[$row['lineId']])){
                    $package_qty_array[$row['lineId']] += $package_qty;
                }else{
                    $package_qty_array[$row['lineId']] = $package_qty;
                }
            }
        }
        return $package_qty_array;
    }

    /**
     * 绑定签收服务费的虚拟商品
     * @param int $header_id 销售订单ID
     * @param int $line_id 销售订单明细ID
     * @param int $qty
     * @param int $customer_id
     * @param int $oc_order_id oc_order.order_id
     * @param string $item_code
     */
    function associateOrderWithASR($header_id, $line_id, $qty, $customer_id, $oc_order_id, $item_code)
    {
        $sql = "SELECT cost.seller_id,cost.buyer_id,cost.onhand_qty,cost.id,rline.oc_order_id,cost.sku_id,ocp.order_product_id,cost.original_qty-ifnull(t.associateQty,0) as leftQty FROM tb_sys_cost_detail cost ";
        $sql .= " LEFT JOIN oc_product p ON cost.sku_id = p.product_id ";
        $sql .= " LEFT JOIN tb_sys_receive_line rline ON rline.id = cost.source_line_id ";
        $sql .= " LEFT JOIN oc_order_product ocp ON ( ocp.order_id = rline.oc_order_id AND ocp.product_id = cost.sku_id ) ";
        //优化sql 避免全表扫描
        $sql .= " LEFT JOIN ( SELECT sum(qty) as associateQty,order_product_id FROM tb_sys_order_associated where buyer_id =".$customer_id."  GROUP BY order_product_id ) t on t.order_product_id = ocp.order_product_id ";
        $sql .= " WHERE cost.onhand_qty > 0 AND type = 1 AND  p.sku='" . $item_code . "' and cost.buyer_id = " . $customer_id . " and rline.oc_order_id = " . $oc_order_id;
        $costArr = $this->db->query($sql)->rows;
        foreach ($costArr as $cost) {
            if ($cost['leftQty'] >= $qty) {
                //剩余数量大于本条明细所需库存数
                $discountsAmount = app(OrderService::class)->orderProductWillAssociateDiscountsAmount(intval($cost['order_product_id']), intval($qty), $this->customer->isJapan() ? 0 : 2);
                $this->db->query("INSERT INTO tb_sys_order_associated  (sales_order_id,sales_order_line_id,order_id,order_product_id,qty,product_id,seller_id,buyer_id,image_id,CreateUserName,CreateTime,ProgramCode,coupon_amount,campaign_amount) values(" . $header_id . "," . $line_id . "," . $cost['oc_order_id'] . "," . $cost['order_product_id'] . "," . $qty . "," . $cost['sku_id'] . "," . $cost['seller_id'] . "," . $cost['buyer_id'] . ",NULL,'B2B System',now(),'V1.0',{$discountsAmount['coupon_amount']},{$discountsAmount['campaign_amount']})");
                break;
            } else {
                if ($cost['leftQty'] > 0) {
                    $discountsAmount = app(OrderService::class)->orderProductWillAssociateDiscountsAmount(intval($cost['order_product_id']), intval($cost['leftQty']), $this->customer->isJapan() ? 0 : 2);
                    $this->db->query("INSERT INTO tb_sys_order_associated  (sales_order_id,sales_order_line_id,order_id,order_product_id,qty,product_id,seller_id,buyer_id,image_id,CreateUserName,CreateTime,ProgramCode,coupon_amount,campaign_amount) values(" . $header_id . "," . $line_id . "," . $cost['oc_order_id'] . "," . $cost['order_product_id'] . "," . $cost['leftQty'] . "," . $cost['sku_id'] . "," . $cost['seller_id'] . "," . $cost['buyer_id'] . ",NULL,'B2B System',now(),'V1.0',{$discountsAmount['coupon_amount']},{$discountsAmount['campaign_amount']})");
                    $lineQty['qty'] = $qty - $cost['leftQty'];
                }
            }
        }
    }

    /**
     * 根据请求的用户ID以及IP地址获取刚刚的订单中还需支付签收服务费的订单
     *
     * @param int $customer_id
     * @param string $ip
     * @return array
     */
    function getUnPaidAsrOrder($customer_id,$ip,$order_id){
        $max_order_id = $order_id;

        $sql = "SELECT
                  o.order_id AS oc_order_id,
                  cso.order_id AS sale_order_id,
                  cso.id AS id,
                  cso.order_status,
                  cso.ship_method
                FROM
                  oc_order o,
                  tb_sys_order_associated oa,
                  tb_sys_customer_sales_order cso
                WHERE o.order_id = oa.order_id
                  AND oa.sales_order_id = cso.id
                  AND oa.buyer_id = ".intval($customer_id)."
                  AND o.ip = '".$this->db->escape($ip)."'
                  AND o.order_id = " . $max_order_id ."
                  GROUP BY o.order_id,cso.order_id,
                      cso.order_status,
                      cso.ship_method";
        $query = $this->db->query($sql);

        $order_array = array();
        if(isset($query->rows) && !empty($query->rows)){
            $row_num = $query->num_rows;
            $first = current($query->rows);
            if($row_num == 1 && $first['order_status'] == CustomerSalesOrderStatus::BEING_PROCESSED && $first['ship_method'] == 'ASR'){
                $sql_all = "SELECT DISTINCT cso.order_id,cso.id FROM tb_sys_customer_sales_order cso WHERE cso.buyer_id = " . intval($customer_id) . " AND cso.order_status = ".CustomerSalesOrderStatus::ASR_TO_BE_PAID;
                $query_all = $this->db->query($sql_all);
                if(isset($query_all->rows) && !empty($query_all->rows)){
                    foreach ($query_all->rows as $row) {
                        $order_array[$row['id']] = $row['order_id'];
                    }
                }
            }else{
                foreach ($query->rows as $row) {
                    if($row['order_status'] == CustomerSalesOrderStatus::ASR_TO_BE_PAID){
                        $order_array[$row['id']] = $row['sale_order_id'];
                    }
                }
            }
        }

        return $order_array;
    }

    /**
     * 根据订单导入批次号，获取本次导入的未支付签收服务费的订单号
     *
     * @param int $customer_id
     * @param $run_id
     * @return array
     */
    function getUnPaidAsrOrderByImportRunId($customer_id,$run_id){
        $sql = "SELECT cso.order_id FROM tb_sys_customer_sales_order cso WHERE cso.buyer_id = " . (int)$customer_id . " AND cso.run_id = '" . $this->db->escape($run_id) . "' AND cso.order_status = ".CustomerSalesOrderStatus::ASR_TO_BE_PAID;
        $query = $this->db->query($sql);
        $order_array = array();
        if(isset($query->rows)){
            foreach ($query->rows as $row) {
               $order_array[] = $row['order_id'];
            }
        }
        return $order_array;
    }
}
