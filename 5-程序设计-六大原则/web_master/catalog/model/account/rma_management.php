<?php

use App\Enums\Order\OrderDeliveryType;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Models\Rma\YzcRmaOrder;
use App\Models\SalesOrder\CustomerSalesReorder;

/**
 * Class ModelAccountRmaManagement
 * User: lilei
 * Date: 2019/2/21
 * Time: 14:40
 *
 * @property ModelAccountCustomerOrderImport $model_account_customer_order_import
 * @property ModelAccountCustomerpartnerMarginOrder $model_account_customerpartner_margin_order
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelToolImage $model_tool_image
 */
class ModelAccountRmaManagement extends Model
{
    public function getCustomerOrders($data = array())
    {
        $sql = "SELECT * FROM `tb_sys_customer_sales_order` cso";
        $sql .= " WHERE 1 = 1 AND cso.order_status <> ".CustomerSalesOrderStatus::TO_BE_PAID;
        $implode = array();

        if (!empty($data['filter_order_id'])) {
            $implode[] = "cso.order_id LIKE '%" . $this->db->escape($data['filter_order_id']) . "%'";
            $implode[] = "cso.buyer_id = " . $this->db->escape($data['customer_id']);
        }

        if ($implode) {
            $sql .= " AND " . implode(" AND ", $implode);
        }

        $sort_data = array(
            'order_id',
            'create_time'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY order_id";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function getPurchaseOrderInfo($customer_order_line_ids = array())
    {
        if (count($customer_order_line_ids) > 0) {
            $salesOrderIds = "(" . implode(",", $customer_order_line_ids) . ")";
            $sql = "SELECT
                      oa.order_product_id,
                      oa.`product_id`,
                      oa.`order_id`,
                      oa.`sales_order_line_id`,
                      oa.`qty`,
                      ctc.`screenname`,
                      ctc.`avatar`,
                      oa.`buyer_id`,
                      oa.`seller_id`,
                      oa.`coupon_amount`,
                      oa.`campaign_amount`,
                      p.`combo_flag`,
                      op.`price` as price,
                      op.`total`,
                      op.`service_fee`,
                      op.service_fee_per AS unit_service_fee,
                      op.type_id,
                      op.agreement_id,
                      CAST(op.`poundage`/op.`quantity` AS DECIMAL(18,2)) AS unit_poundage,
                      op.`poundage`,
                      o.`currency_code`,
                      o.delivery_type,
                      p.`image`,
                      pd.`name`,
                      op.freight_per,
                      op.package_fee,
                      op.agreement_id,op.type_id,
                      op.freight_difference_per
                    FROM
                      `tb_sys_order_associated` oa
                      LEFT JOIN `tb_sys_customer_sales_order_line` csol
                        ON oa.`sales_order_line_id` = csol.`id`
                      LEFT JOIN oc_product p
                        ON oa.`product_id` = p.`product_id`
                      LEFT JOIN `oc_order` o
                        ON oa.`order_id` = o.`order_id`
                      LEFT JOIN oc_customerpartner_to_customer ctc
                        ON oa.`seller_id` = ctc.`customer_id`
                      LEFT JOIN oc_order_product op
                        ON oa.`order_product_id` = op.`order_product_id`
                      LEFT JOIN oc_currency cur
                        ON o.`currency_id` = cur.`currency_id`
                        LEFT JOIN oc_product_description pd
                        ON p.`product_id` = pd.`product_id`
                    WHERE oa.product_id <> " . (int)$this->config->get('signature_service_us_product_id') . " AND oa.`sales_order_line_id` IN " . $salesOrderIds;
            $sql .= ' AND oa.buyer_id=o.customer_id ';
            return $this->db->query($sql)->rows;
        }
        return null;
    }

    public function getRmaReason($type)
    {
//        $result = $this->orm::table('oc_yzc_rma_reason')
//            ->select('reason_id', 'reason')
//            ->where('status', '=', 1)->get();
        if (!in_array($type, [16, 32])) {
            $type = 16;
        }
        if ($type == 16) {
            $sql = "SELECT r.reason_id,r.reason FROM oc_yzc_rma_reason r WHERE r.status = 1 and type in (1,3)";
        }
        if ($type == 32) {
            $sql = "SELECT r.reason_id,r.reason FROM oc_yzc_rma_reason r WHERE r.status = 1 and type in(2,3)";
        }
        return $this->db->query($sql)->rows;
    }

    public function getStoreNameBySellerId($seller_id)
    {
        $sql = "SELECT screenname as sellerName FROM oc_customerpartner_to_customer ctc WHERE ctc.customer_id = " . $seller_id;
        return $this->db->query($sql)->row;
    }

    public function getCustomerOrderStatus()
    {
        $sql = "SELECT * FROM tb_sys_dictionary sd WHERE sd.DicCategory = 'CUSTOMER_ORDER_STATUS'";
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getCustomerOrderItemStatus()
    {
        $sql = "SELECT * FROM tb_sys_dictionary sd WHERE sd.DicCategory = 'CUSTOMER_ITEM_STATUS'";
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getCustomerOrder($customer_order_id, $buyer_id)
    {
        $sql = "SELECT cso.*,sd.DicValue,LOWER(cso.orders_from) as lowOrderFrom FROM `tb_sys_customer_sales_order` cso
                LEFT JOIN tb_sys_dictionary sd on sd.DicCategory='CUSTOMER_ORDER_STATUS' AND sd.DicKey=cso.order_status
                WHERE cso.`order_id` = '" . $this->db->escape($customer_order_id) . "' AND cso.`buyer_id` = " . $this->db->escape($buyer_id);
        $query = $this->db->query($sql);
        return $query->row;
    }

    public function getCustomerOrderLineByHeaderId($header_id)
    {
        $sql = "SELECT * FROM `tb_sys_customer_sales_order_line` csol WHERE csol.header_id = " . $this->db->escape($header_id);
        $query = $this->db->query($sql);
        return $query->rows;
    }

    /**
     * @param int $sales_order_id
     * @param string $item_code
     * @param int $buyer_id
     * @param int $order_id
     * @param int|null $seller_id
     * @return array
     * @throws Exception
     */
    public function getOrderAssociated($sales_order_id, $item_code, $buyer_id, $order_id, $seller_id = null)
    {
        $europeFreight = json_decode($this->config->get("europe_freight_product_id"),true);
        $europeFreightStr = '('.implode(',',$europeFreight).')';
        $sql_1 = "SELECT
                  csol.`id` AS line_id, cso.`id` AS header_id
                FROM
                  `tb_sys_customer_sales_order` cso
                  LEFT JOIN `tb_sys_customer_sales_order_line` csol
                    ON cso.`id` = csol.`header_id`
                WHERE cso.`id` = " . (int)$sales_order_id . "
                  AND csol.`item_code` = '" . $this->db->escape($item_code) . "'
                  AND cso.`buyer_id` = " . (int)$buyer_id;
        $data = $this->db->cursor($sql_1);
        $result = null;
        foreach ($data as $row) {
            $sql_2 = "SELECT * FROM `tb_sys_order_associated` oa WHERE oa.`order_id` = "
                . (int)$order_id . " AND oa.`sales_order_id` = " . (int)$sales_order_id
                . " AND oa.`sales_order_line_id` = " . (int)$row->line_id
                . " AND oa.`buyer_id` = " . (int)$buyer_id;
            $sql_2 .= " AND oa.`product_id` not in".$europeFreightStr;
            if ($seller_id) {
                $sql_2 .= " AND oa.`seller_id` = " . (int)$seller_id;
            }
            $result = $this->db->query($sql_2)->row;
            if (empty($result)) {
                continue;
            } else {
                break;
            }
        }
        if ($result) {
            return $result;
        } else {
            throw new Exception('Error arguments.');
        }
    }

    /**
     * 创建rma order
     * @param $data
     * @return object
     * @throws Exception
     */
    public function addRmaOrder($data)
    {
        // 获取RMA ORDER ID
        $rmaOrder = array(
            "order_id" => $data['order_id'],
            "rma_order_id" => $data['rma_order_id'],
            "from_customer_order_id" => $data['from_customer_order_id'],
            "seller_id" => $data['seller_id'],
            "buyer_id" => $data['buyer_id'],
            "admin_status" => isset($data['admin_status']) ? $data['admin_status'] : null,
            "seller_status" => isset($data['seller_status']) ? $data['seller_status'] : null,
            "cancel_rma" => isset($data['cancel_rma']) ? $data['cancel_rma'] : false,
            "solve_rma" => isset($data['solve_rma']) ? $data['solve_rma'] : false,
            "create_user_name" => $data['create_user_name'],
            "create_time" => date('Y-m-d H:i:s', time()),
            "program_code" => PROGRAM_CODE,
            "order_type" => isset($data['order_type']) ? $data['order_type'] : 1
        );
        // 校验 rma order id 重复问题
        if (YzcRmaOrder::query()->where('rma_order_id', $data['rma_order_id'])->exists()) {
           throw new Exception($data['rma_order_id'].' has already been existed.');
        }
        $id = $this->orm::table('oc_yzc_rma_order')->insertGetId($rmaOrder);
        return $this->orm::table('oc_yzc_rma_order')->where('id', $id)->first();
    }

    /**
     * 添加rma文件信息
     * @param $data
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function addRmaFile($data)
    {
        $rmaFile = [
            "rma_id" => $data['rma_id'],
            "file_name" => $data['file_name'],
            "size" => $data['size'],
            "file_path" => $data['file_path'],
            "buyer_id" => $data['buyer_id'],
            "create_user_name" => $this->customer->getId(),
            "create_time" => date('Y-m-d H:i:s', time()),
            "program_code" => PROGRAM_CODE
        ];
        $id = $this->orm->table('oc_yzc_rma_file')->insertGetId($rmaFile);
        return $this->orm->table('oc_yzc_rma_file')->where('id', $id)->first();
    }

    public function addRmaOrderProduct($data)
    {
        $rmaFile = array(
            "rma_id" => $data['rma_id'],
            "product_id" => $data['product_id'],
            "item_code" => $data['item_code'],
            'asin' => $data['asin'],
            "quantity" => $data['quantity'],
            "reason_id" => $data['reason_id'],
            "order_product_id" => $data['order_product_id'],
            "comments" => $data['comments'],
            "rma_type" => $data['rma_type'],
            "apply_refund_amount" => $data['apply_refund_amount'],
            "create_user_name" => $this->customer->getId(),
            "create_time" => date('Y-m-d H:i:s', time()),
            "program_code" => PROGRAM_CODE,
            'coupon_amount' => isset($data['coupon_amount']) ? $data['coupon_amount'] : 0,
            'campaign_amount' => isset($data['campaign_amount']) ? $data['campaign_amount'] : 0,
        );
        $id = $this->orm::table('oc_yzc_rma_order_product')->insertGetId($rmaFile);
        return $this->orm::table('oc_yzc_rma_order_product')->where('id', $id)->first();
    }

    public function addReOrder($data)
    {
        $id = $this->orm::table('tb_sys_customer_sales_reorder')->insertGetId($data);
        $reorderArray = $this->orm->table('tb_sys_customer_sales_reorder')
            ->where('id', '=', $id)
            ->first();
        $this->orm->table('tb_sys_customer_sales_reorder_history')->insert(obj2array($reorderArray));
        return $this->orm::table('tb_sys_customer_sales_reorder')->where('id', $id)->first();
    }

    public function addReOrderLine($data)
    {
        $id = $this->orm::table('tb_sys_customer_sales_reorder_line')->insertGetId($data);
//        $this->orm::table('tb_sys_customer_sales_reorder_line_history')->insertGetId($data);
        $reorderLineArray = $this->orm->table('tb_sys_customer_sales_reorder_line')
            ->where('id', '=', $id)
            ->first();
        $this->orm->table('tb_sys_customer_sales_reorder_line_history')->insert(obj2array($reorderLineArray));
        return $this->orm::table('tb_sys_customer_sales_reorder_line')->where('id', $id)->first();
    }

    public function getStoreByBuyerId($buyer_id)
    {

        $result = $this->orm::table('oc_buyer_to_seller as bts')
            ->join('oc_customerpartner_to_customer as c', 'bts.seller_id', '=', 'c.customer_id')
            ->select('c.screenname', 'c.customer_id')
            ->where([
                ['bts.buyer_id', '=', $buyer_id],
                ['bts.seller_control_status', 1],
                ['bts.buyer_control_status', 1]
            ])
            ->get();
        $stores = array();
        if ($result != null) {
            foreach ($result as $item) {
                $stores[] = array(
                    "name" => $item->screenname,
                    "seller_id" => $item->customer_id
                );
            }
        }
        return $stores;
    }

    public function getRmaStatus()
    {
        $result = $this->orm::table('oc_yzc_rma_status as rs')
            ->select('rs.status_id', 'rs.name', 'rs.color')
            ->where('rs.status', '1')
            ->get();
        $rmaStatus = array();
        if ($result != null) {
            foreach ($result as $item) {
                $rmaStatus[] = array(
                    "status_id" => $item->status_id,
                    "name" => $item->name,
                    "color" => $item->color
                );
            }
        }
        return $rmaStatus;
    }

    /**
     * 获取重发单统计的数量
     * @param int $sales_order_id 销售单id
     * @return int
     */
    public function getReorderCountByCustomerOrderId($sales_order_id)
    {
        return $this->orm
            ->table('tb_sys_customer_sales_reorder')
            ->where("sales_order_id", '=', $sales_order_id)
            ->count();
    }

    /**
     * 获取重发单统计的数量
     * @param string $rmaId rma order id
     * @return int
     */
    public function getReorderCountByRmaId($rmaId): int
    {
        return $this->orm
            ->table('tb_sys_customer_sales_reorder')
            ->where('rma_id', (int)$rmaId)
            ->count();
    }

    /**
     * 获取销售单信息
     * @param int $sales_order_id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getCustomerOrderById($sales_order_id)
    {
        return $this->orm->table('tb_sys_customer_sales_order')
            ->where('id', $sales_order_id)
            ->first();
    }

    /**
     * 获取销售单明细信息
     * @param int $sales_order_line_id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getCustomerOrderLineById($sales_order_line_id)
    {
        return $this->orm
            ->table('tb_sys_customer_sales_order_line')
            ->where('id', $sales_order_line_id)
            ->first();
    }

    public function getRmaOrderInfoCount($filter_data)
    {
        $sql = "SELECT COUNT(*) AS total FROM `vw_rma_order_info` roi WHERE 1 = 1";
        if (isset($filter_data['seller_id']) && trim($filter_data['seller_id']) != '') {
            $sql .= " AND roi.seller_id = " . $this->db->escape(trim($filter_data['seller_id']));
        }
        if (isset($filter_data['b2b_order_id']) && trim($filter_data['b2b_order_id']) != '') {
            $sql .= " AND roi.b2b_order_id LIKE '%" . $this->db->escape(trim($filter_data['b2b_order_id'])) . "%'";
        }
        if (isset($filter_data['customer_order_id']) && trim($filter_data['customer_order_id']) != '') {
            $sql .= " AND roi.from_customer_order_id LIKE '%" . $this->db->escape(trim($filter_data['customer_order_id'])) . "%'";
        }
        if (isset($filter_data['cancelFlag']) && $filter_data['cancelFlag']) {
            // 取消订单查询
            $sql .= " AND roi.cancel_status = 1";
        } else {
            if (isset($filter_data['seller_status']) && !empty($filter_data['seller_status'])) {
                $seller_status = $filter_data['seller_status'];
                if (!is_array($seller_status)) {
                    $seller_status = [$seller_status];
                }
                $sql .= " AND roi.cancel_status <> 1 AND roi.seller_status in (" . join(',', $seller_status) . ")";
            }
        }
        if (isset($filter_data['rma_type']) && !empty($filter_data['rma_type'])) {
            $sql .= " AND roi.rma_type in (" . join(',', (array)$filter_data['rma_type']) . ")";
        }
        if (isset($filter_data['rma_id']) && trim($filter_data['rma_id']) != '') {
            $sql .= " AND roi.rma_order_id = " . $this->db->escape(trim($filter_data['rma_id']));
        }
        if (isset($filter_data['filter_applyDateFrom']) && trim($filter_data['filter_applyDateFrom']) != '') {
            $sql .= " AND roi.create_time >= '" . $this->db->escape(trim($filter_data['filter_applyDateFrom'])) . "'";
        }
        if (isset($filter_data['filter_applyDateTo']) && trim($filter_data['filter_applyDateTo']) != '') {
            $sql .= " AND roi.create_time <= '" . $this->db->escape(trim($filter_data['filter_applyDateTo'])) . "'";
        }
        if (isset($filter_data['filter_status_refund'])) {
            $sql .= " AND roi.status_refund = '" .(int)$filter_data['filter_status_refund']. "'";
        }
        if (isset($filter_data['filter_status_reshipment'])) {
            $sql .= " AND roi.status_reshipment = '" .(int)$filter_data['filter_status_reshipment']. "'";
        }
        $sql .= " AND roi.buyer_id = " . (int)$this->customer->getId();
        $query = $this->db->query($sql);
        return (int)$query->row['total'];
    }

    public function getRmaOrderInfo($filter_data)
    {
        $sql = "SELECT roi.*,o.delivery_type FROM `vw_rma_order_info` roi left join oc_order as o on o.order_id= roi.b2b_order_id WHERE 1 = 1";
        if (isset($filter_data['seller_id']) && trim($filter_data['seller_id']) != '') {
            $sql .= " AND roi.seller_id = " . $this->db->escape(trim($filter_data['seller_id']));
        }
        if (isset($filter_data['b2b_order_id']) && trim($filter_data['b2b_order_id']) != '') {
            $sql .= " AND roi.b2b_order_id LIKE '%" . $this->db->escape(trim($filter_data['b2b_order_id'])) . "%'";
        }
        if (isset($filter_data['customer_order_id']) && trim($filter_data['customer_order_id']) != '') {
            $sql .= " AND roi.from_customer_order_id LIKE '%" . $this->db->escape(trim($filter_data['customer_order_id'])) . "%'";
        }
        if (isset($filter_data['cancelFlag']) && $filter_data['cancelFlag']) {
            // 取消订单查询
            $sql .= " AND roi.cancel_status = 1";
        } else {
            if (isset($filter_data['seller_status']) && !empty($filter_data['seller_status'])) {
                $seller_status = $filter_data['seller_status'];
                if (!is_array($seller_status)) {
                    $seller_status = [$seller_status];
                }
                $sql .= " AND roi.cancel_status <> 1 AND roi.seller_status in (" . join(',', $seller_status) . ")";
            }
        }
        if (isset($filter_data['rma_type']) && !empty($filter_data['rma_type'])) {
            $sql .= " AND roi.rma_type in (" . join(',', (array)$filter_data['rma_type']) . ")";
        }
        if (isset($filter_data['rma_id']) && trim($filter_data['rma_id']) != '') {
            $sql .= " AND roi.rma_order_id = '" . $this->db->escape(trim($filter_data['rma_id'])) . "'";
        }
        if (isset($filter_data['filter_applyDateFrom']) && trim($filter_data['filter_applyDateFrom']) != '') {
            $sql .= " AND roi.create_time >= '" . $this->db->escape(trim($filter_data['filter_applyDateFrom'])) . "'";
        }
        if (isset($filter_data['filter_applyDateTo']) && trim($filter_data['filter_applyDateTo']) != '') {
            $sql .= " AND roi.create_time <= '" . $this->db->escape(trim($filter_data['filter_applyDateTo'])) . "'";
        }
        if (isset($filter_data['filter_status_refund'])) {
            $sql .= " AND roi.status_refund = '" .(int)$filter_data['filter_status_refund']. "'";
        }
        if (isset($filter_data['filter_status_reshipment'])) {
            $sql .= " AND roi.status_reshipment = '" .(int)$filter_data['filter_status_reshipment']. "'";
        }
        $sql .= " AND roi.buyer_id = " . $this->customer->getId();
        $sql .= " ORDER BY roi.rma_id DESC";
        if (isset($filter_data['page_num']) || isset($filter_data['page_limit'])) {
            $sql .= " LIMIT " . (int)$filter_data['page_num'] . "," . (int)$filter_data['page_limit'];
        }
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getIconByRmaId($rmaId)
    {
        return db(DB_PREFIX . 'yzc_rma_order_product as yrop')
            ->leftJoin(DB_PREFIX . 'order_product as oop', 'oop.order_product_id', '=', 'yrop.order_product_id')
            ->leftJoin('tb_sys_margin_agreement as sma', 'sma.id', '=', 'oop.agreement_id')
            ->leftJoin('oc_futures_margin_agreement as fma', 'fma.id', '=', 'oop.agreement_id')
            ->where('yrop.rma_id', $rmaId)
            ->select(['oop.type_id','oop.agreement_id',])
            ->selectRaw('case oop.type_id when 2 then sma.agreement_id when 3 then fma.agreement_no else "" end as agreement_no')
            ->first();
    }

    public function getFutureMarginInfo($rmaId)
    {
        $ret = $this->orm->table(DB_PREFIX . 'yzc_rma_order_product as yrop')
            ->leftJoin(DB_PREFIX . 'order_product as oop', 'oop.order_product_id', '=', 'yrop.order_product_id')
            ->leftJoin('oc_futures_margin_agreement as fma', 'fma.id', '=', 'oop.agreement_id')
            ->where('yrop.rma_id', $rmaId)
            ->selectRaw('oop.type_id,oop.agreement_id,fma.contract_id,fma.agreement_no as future_margin_agreement_id')->first();
        return obj2array($ret);
    }

    public function getIconByRmaIdAndProductId($rmaId, $product_id)
    {
        return $this->orm->table(DB_PREFIX . 'yzc_rma_order_product as yrop')
            ->leftJoin(DB_PREFIX . 'order_product as oop', 'oop.order_product_id', '=', 'yrop.order_product_id')
            ->where(['yrop.rma_id' => $rmaId, 'yrop.product_id' => $product_id])
            ->value('oop.type_id');
    }

    public function getRmaInfoById($rmaId)
    {
        $select = [
            'rop.asin as asin',
            'ro.id as rma_id',
            'ro.from_customer_order_id as from_customer_order_id',
            'ro.rma_order_id as rma_order_id',
            'ro.cancel_rma as cancel_status',
            'rop.rma_type as rma_type',
            'rop.order_product_id',
            'ctc.screenname as store',
            'ro.seller_id as seller_id',
            'csr.sales_order_id as sales_order_id',
            'cso.order_id as customer_order_id',
            'ro.order_id as b2b_order_id',
            'rop.item_code as item_code',
            'ro.create_time as created_time',
            'ifnull(ro.update_time,ro.create_time) as create_time',
            'ro.seller_status as seller_status',
            'ro.admin_status as admin_status',
            'rop.product_id as product_id',
            'ro.memo as memo',
            'rop.apply_refund_amount as apply_refund_amount',
            'rop.actual_refund_amount as actual_refund_amount',
            'ro.buyer_id as buyer_id',
            'ro.order_type as order_type',
            'rop.status_refund',
            'rop.status_reshipment',
            'rop.quantity',
            'rop.reason_id',
            'rop.comments',
            'rop.refund_type',
            'ro.processed_date',
            'rop.coupon_amount',
            'rop.campaign_amount',
            'o.delivery_type'
        ];
        $result = $this->orm->table('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'ro.id', '=', 'rop.rma_id')
            ->leftJoin('tb_sys_customer_sales_reorder as csr', 'csr.rma_id', '=', 'ro.id')
            ->leftJoin('tb_sys_customer_sales_order as cso', 'cso.id', '=', 'csr.sales_order_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ro.seller_id', '=', 'ctc.customer_id')
            ->leftJoin('oc_order as o', 'o.order_id', '=', 'ro.order_id')
            ->where('ro.id', $rmaId)
            ->selectRaw(implode(',', $select))
            ->first();
        if (5 == $result->refund_type){
            $result->refund_type_str = 'Line Of Credit / Virtual Pay';
            $rmaVP = $this->orm->table('oc_virtual_pay_record')
                ->where(['relation_id'=>$rmaId, 'type'=>2])
                ->value('amount');//实际退到虚拟账户的金额
            $result->actual_refund_amount_vp = $this->currency->format($rmaVP, session('currency'));
        }elseif (4 == $result->refund_type){
            $result->refund_type_str = 'Virtual Pay';
        }elseif (1 == $result->refund_type){
            $result->refund_type_str = 'Line Of Credit';
        }else{
            $result->refund_type_str = '';
        }

        return $result;
    }

    public function getRmaOrderFile($rmaId, $type)
    {
        $result = $this->orm::table('oc_yzc_rma_file')
            ->where(
                [
                    ["rma_id", $rmaId],
                    ['type', $type]
                ])
            ->get();
        return $result;
    }

    /**
     * 判断RMA订单图片是否存在
     *
     * @param $rmaId ： ram_id
     * @param $type : 1:buyer上传的附件2：seller上传的附件
     * @return bool
     */
    public function checkRmaOrderFileExist($rmaId, $type)
    {
        $result = $this->orm::table('oc_yzc_rma_file')
            ->where(
                [
                    ["rma_id", $rmaId],
                    ['type', $type]
                ])
            ->exists();

        return $result;
    }

    public function getRmaOrderFileById($rmaFileId)
    {
        $result = $this->orm::table('oc_yzc_rma_file')
            ->where("id", $rmaFileId)
            ->first();
        return $result;
    }

    public function getRmaOrderProduct($rmaId)
    {
        $result = $this->orm::table('oc_yzc_rma_order_product')
            ->where('rma_id', $rmaId)
            ->get();
        return $result;
    }

    public function getHeaderAndLineId($customer_order_id, $item_code, $buyer_id)
    {
        $sql = "SELECT cso.`id` AS sales_order_id, csol.`id` AS sales_order_line_id,cso.order_status FROM `tb_sys_customer_sales_order` cso LEFT JOIN `tb_sys_customer_sales_order_line` csol ON cso.`id` = csol.`header_id` WHERE cso.`order_id` = '" . $customer_order_id . "' AND csol.`item_code` = '" . $item_code . "' AND cso.buyer_id=" . $buyer_id;
        return $this->db->query($sql)->row;
    }

    public function getOrderProductById($orderProductId, $isEuropeCountry = null)
    {
        if (!$isEuropeCountry) {
            $sql = "SELECT
                  op.price as price,
                  op.service_fee_per as service_fee_per,
                    op.poundage / op.`quantity` AS unit_poundage,
                  opq.amount_price_per as quotePrice,
                  opq.amount_price_per,
                  opq.amount_service_fee_per,
				  opq.discount_price,
				  opq.origin_price,
				  op.quantity,
				  op.freight_per,
				  op.package_fee,
				  op.freight_difference_per,
                  op.coupon_amount,
                  op.campaign_amount
                FROM
                  oc_order_product op
                  LEFT JOIN oc_product_quote opq on (opq.order_id = op.order_id and opq.product_id = op.product_id)
                WHERE op.`order_product_id` =" . (int)$orderProductId;
        } else {
            $sql = "SELECT
                  op.price as price,
                  op.service_fee_per as service_fee_per,
                    op.poundage / op.`quantity` AS unit_poundage,
                    opq.amount_price_per,
                    opq.amount_service_fee_per,
                  (opq.amount_price_per+opq.amount_service_fee_per) as quotePrice,
				  opq.discount_price,
				  opq.origin_price,
				  op.quantity,
				   op.freight_per,
				  op.package_fee,
                  op.coupon_amount,
                  op.campaign_amount
                FROM
                  oc_order_product op
                  LEFT JOIN oc_product_quote opq on (opq.order_id = op.order_id and opq.product_id = op.product_id)
                WHERE op.`order_product_id` =" . (int)$orderProductId;
        }
        $result = $this->db->query($sql)->row;
        return $result;
    }

    public function getRmaReasonById($rmaReasonId)
    {
        $result = $this->orm::table('oc_yzc_rma_reason')
            ->where('reason_id', $rmaReasonId)
            ->first();
        return $result;
    }

    /**
     * 获取重发单信息
     * @param int $rmaId rma id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getReorderByRmaId($rmaId)
    {
        return CustomerSalesReorder::query()
            ->where('rma_id', $rmaId)
            ->first();
    }

    public function getReorderLineByReorderId($reorderId)
    {
        $result = $this->orm::table('tb_sys_customer_sales_reorder_line')
            ->where('reorder_header_id', $reorderId)
            ->get();
        return obj2array($result);
    }

    public function cancelRmaOrder($rmaId)
    {
        $rmaOrder = $this->orm->table('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'ro.id', '=', 'rop.rma_id')
            ->select('ro.seller_status', 'rop.status_refund', 'rop.status_reshipment')
            ->where('ro.id', $rmaId)->first();
        if (!($rmaOrder->seller_status == 1 || ($rmaOrder->seller_status == 2 && ($rmaOrder->status_refund == 2 || $rmaOrder->status_reshipment == 2)))) {
            $result = array(
                'error' => 'RMA Status is changed, can not be canceled.'
            );
            return $result;
        }
        $this->orm::table('oc_yzc_rma_order')
            ->where('id', $rmaId)
            ->update(['cancel_rma' => 1]);
        $reorders = $this->db->query('SELECT csr.id FROM `tb_sys_customer_sales_reorder` csr WHERE csr.`rma_id` = ' . $rmaId)->rows;
        if ($reorders) {
            foreach ($reorders as $reorder) {
                $this->db->query('UPDATE tb_sys_customer_sales_reorder csr SET csr.order_status = 16 WHERE csr.id = ' . $reorder['id']);
                $this->db->query('UPDATE tb_sys_customer_sales_reorder_line csrl SET csrl.item_status = 8 WHERE csrl.reorder_header_id = ' . $reorder['id']);
            }
        }
    }

    /**
     * 获取议价价格
     */
    public function getQuotePrice($order_id, $product_id)
    {
        $sql = "SELECT origin_price,discount_price,price,amount_price_per,amount_service_fee_per FROM oc_product_quote WHERE order_id=" . $order_id . " AND product_id = " . $product_id;
        $result = $this->db->query($sql)->row;
        return $result;
    }

    public function getRmaIdTemp()
    {
        $date = date('Y-m-d', time());
        $sql = "SELECT COUNT(ro.`id`) as total FROM `oc_yzc_rma_id_temp` ro WHERE ro.`create_time` LIKE '" . $date . "%'";
        $total = $this->orm::select($sql)[0]->total;
        $total = $total + 1;
        if ($total > 0 && $total < 10) {
            $sequence = "000" . $total;
        } else if ($total >= 10 && $total < 100) {
            $sequence = "00" . $total;
        } else if ($total >= 100 && $total < 1000) {
            $sequence = "0" . $total;
        } else {
            $sequence = $total;
        }
        $rma_order_id = date('Ymd', time()) . $sequence;
        //插入rma_id_temp
        $rmaIdTemp = array(
            "rma_order_id" => $rma_order_id,
            "create_time" => date('Y-m-d H:i:s', time())
        );
        // 防止并发
        $rma_exists = $this->orm
            ->table('oc_yzc_rma_id_temp')
            ->where('rma_order_id', $rma_order_id)
            ->where('create_time', '>=', date('Y-m-d') . ' 00:00:00')
            ->first();
        if ($rma_exists) {
            return $this->getRmaIdTemp();
        }
        $id = $this->orm::table('oc_yzc_rma_id_temp')->insertGetId($rmaIdTemp);
        return $this->orm::table('oc_yzc_rma_id_temp')->where('id', $id)->first();
    }

    /**
     * 重发单数量
     * @param $filter_data
     * @param $flag
     * @return mixed
     */
    public function getReshipmentInfoCount($filter_data, $flag = false)
    {
        $sql = "SELECT count(1) as total,GROUP_CONCAT(csr_id) as id_str FROM (SELECT
               ro.id,
               csr.id as csr_id
            FROM
                tb_sys_customer_sales_reorder csr
            LEFT JOIN tb_sys_customer_sales_reorder_line csrl ON csrl.reorder_header_id = csr.id
            LEFT JOIN oc_yzc_rma_order ro ON ro.id = csr.rma_id
            LEFT JOIN oc_yzc_rma_order_product rop ON rop.rma_id = ro.id
            LEFT JOIN tb_sys_dictionary tsd on tsd.DicCategory = 'CUSTOMER_ORDER_STATUS' and tsd.DicKey=csr.order_status
            LEFT JOIN oc_customerpartner_to_customer ctc on ctc.customer_id=ro.seller_id
            LEFT JOIN oc_yzc_rma_reason yrr on yrr.reason_id = rop.reason_id
            WHERE 1=1
            ";
        if (isset($filter_data['filter_reshipment_id']) && trim($filter_data['filter_reshipment_id']) != '') {
            $sql .= " AND csr.reorder_id LIKE '%" . $this->db->escape(trim($filter_data['filter_reshipment_id'])) . "%'";
        }
        if (isset($filter_data['filter_seller_status']) && trim($filter_data['filter_seller_status']) != -1) {
            $sql .= " AND rop.status_reshipment =" . $this->db->escape(trim($filter_data['filter_seller_status']));
        }
        if (isset($filter_data['filter_item_code']) && trim($filter_data['filter_item_code']) != '') {
            $sql .= " AND csrl.item_code LIKE '%" . $this->db->escape(trim($filter_data['filter_item_code'])) . "%'";
        }
        if (isset($filter_data['filter_sales_order_id']) && trim($filter_data['filter_sales_order_id']) != '') {
            $sql .= " AND ro.from_customer_order_id  LIKE '%" . $this->db->escape(trim($filter_data['filter_sales_order_id'])) . "%'";
        }

        if (isset($filter_data['filter_order_status']) && trim($filter_data['filter_order_status']) != '') {
            $sql .= " AND csr.order_status =" . $this->db->escape(trim($filter_data['filter_order_status']));
        }
        if (isset($filter_data['filter_store']) && trim($filter_data['filter_store']) != '') {
            $sql .= " AND ctc.customer_id =" . $this->db->escape(trim($filter_data['filter_store']));
        }
        if (isset($filter_data['filter_rma_id']) && trim($filter_data['filter_rma_id']) != '') {
            $sql .= " AND ro.rma_order_id LIKE '%" . $this->db->escape(trim($filter_data['filter_rma_id'])) . "%'";
        }

        if (isset($filter_data['filter_applyDateFrom']) && trim($filter_data['filter_applyDateFrom']) != '') {
            $sql .= " AND ro.create_time >= '" . $this->db->escape(trim($filter_data['filter_applyDateFrom'])) . "'";
        }
        if (isset($filter_data['filter_applyDateTo']) && trim($filter_data['filter_applyDateTo']) != '') {
            $sql .= " AND ro.create_time <= '" . $this->db->escape(trim($filter_data['filter_applyDateTo'])) . "'";
        }
        $sql .= " AND ro.buyer_id = " . $this->customer->getId();
        $sql .= " GROUP BY csr.id ";
        $sql .= " ORDER BY ro.id DESC ) t";
        $query = $this->db->query($sql);
        if ($flag == false) {
            return $query->row['total'];
        } else {
            $res['total'] = $query->row['total'];
            $res['id_str'] = $query->row['id_str'];
            return $res;
        }

    }

    public function getReshipmentInfoById($id_str)
    {

        $sql = "SELECT
                ro.seller_id,
                rop.rma_id,
                csr.reorder_id,
                ro.from_customer_order_id,
                ro.rma_order_id,
                csrl.item_code as sku,
                csrl.qty as line_qty,
                csrl.id as line_id,
                 GROUP_CONCAT(csrl.product_id) as product_ids,
                 GROUP_CONCAT(csrl.qty) as product_qtys,
                GROUP_CONCAT(
                    CONCAT(
                        csrl.item_code,
                        '[',
                        csrl.qty,
                        ']'
                    )
                ) as item_code,
                (
                    SELECT
                        GROUP_CONCAT(TrackingNumber separator ';')
                    FROM
                        tb_sys_customer_sales_order_tracking sot
                    LEFT JOIN tb_sys_carriers sc on sc.CarrierID = sot.LogisticeId
                    WHERE
                        sot.SalesOrderId = csr.reorder_id
                    AND sot.SalerOrderLineId = csrl.id
                    GROUP BY
                        sot.SalesOrderId
                ) as TrackingNumber,
            (
                SELECT
                        GROUP_CONCAT(sc.CarrierName)
                    FROM
                        tb_sys_customer_sales_order_tracking sot
                    LEFT JOIN tb_sys_carriers sc on sc.CarrierID = sot.LogisticeId
                    WHERE
                        sot.SalesOrderId = csr.reorder_id
                    AND sot.SalerOrderLineId = csrl.id
                    GROUP BY
                        sot.SalesOrderId
            ) as CarrierName,
            tsd.DicValue,
            csr.create_time,
            ctc.screenname,
            case when rop.status_reshipment = 1 then 'Agree'
                when rop.status_reshipment = 2 then 'Refuse'
                 when rop.status_reshipment = 0 then ''
                 end as status_reshipment,
            CONCAT(csr.ship_name,' | ',csr.ship_address,',',csr.ship_city,',',csr.ship_state,',',csr.ship_zip_code,',',csr.ship_country,' | ',csr.ship_phone,' | ',csr.email) as shipInfo,
            yrr.reason,
            rop.comments,
			rop.seller_reshipment_comments,
			csr.ship_name,
            csr.ship_address,
            csr.ship_city,
            csr.ship_state,
            csr.ship_zip_code,
            csr.ship_country,
            csr.ship_phone,
            csr.email,
			csr.order_status
            FROM
                tb_sys_customer_sales_reorder csr
            LEFT JOIN tb_sys_customer_sales_reorder_line csrl ON csrl.reorder_header_id = csr.id
            LEFT JOIN oc_yzc_rma_order ro ON ro.id = csr.rma_id
            LEFT JOIN oc_yzc_rma_order_product rop ON rop.rma_id = ro.id
            LEFT JOIN tb_sys_dictionary tsd on tsd.DicCategory = 'CUSTOMER_ORDER_STATUS' and tsd.DicKey=csr.order_status
            LEFT JOIN oc_customerpartner_to_customer ctc on ctc.customer_id=ro.seller_id
            LEFT JOIN oc_yzc_rma_reason yrr on yrr.reason_id = rop.reason_id
            WHERE 1=1
            ";
        $sql .= " AND csr.id in (" . $id_str . ")";
        $sql .= " AND ro.buyer_id = " . $this->customer->getId();
        $sql .= " GROUP BY csr.id ";
        $sql .= " ORDER BY ro.id DESC";
        $query = $this->db->query($sql);
        return $query->rows;

    }

    /**
     * 获取重发单数据
     * @param $filter_data
     * @return mixed
     */
    public function getReshipmentInfo($filter_data)
    {
        $sql = "SELECT
                ro.seller_id,
                rop.rma_id,
                csr.reorder_id,
                ro.from_customer_order_id,
                ro.seller_status,
                tso.id as customer_order_id,
                ro.rma_order_id,
                ro.order_type,
                ro.cancel_rma as cancel_status,
                csrl.item_code as sku,
                csrl.qty as line_qty,
                csrl.id as line_id,
                oop.type_id,
                 GROUP_CONCAT(csrl.product_id) as product_ids,
                 GROUP_CONCAT(csrl.qty) as product_qtys,
                GROUP_CONCAT(
                    CONCAT(
                        csrl.item_code,
                        'x',
                        csrl.qty
                    )
                ) as item_code,
                (
                    SELECT
                        GROUP_CONCAT(TrackingNumber separator ';')
                    FROM
                        tb_sys_customer_sales_order_tracking sot
                    LEFT JOIN tb_sys_carriers sc on sc.CarrierID = sot.LogisticeId
                    WHERE
                        sot.SalesOrderId = csr.reorder_id
                    AND sot.SalerOrderLineId = csrl.id
                    GROUP BY
                        sot.SalesOrderId
                ) as TrackingNumber,
            (
                SELECT
                        GROUP_CONCAT(sc.CarrierName)
                    FROM
                        tb_sys_customer_sales_order_tracking sot
                    LEFT JOIN tb_sys_carriers sc on sc.CarrierID = sot.LogisticeId
                    WHERE
                        sot.SalesOrderId = csr.reorder_id
                    AND sot.SalerOrderLineId = csrl.id
                    GROUP BY
                        sot.SalesOrderId
            ) as CarrierName,
            tsd.DicValue,
            ro.create_time,
            ctc.screenname,
            case when rop.status_reshipment = 1 then 'Agree'
                when rop.status_reshipment = 2 then 'Refuse'
                 when rop.status_reshipment = 0 then ''
                 end as status_reshipment,
            CONCAT(csr.ship_name,' | ',csr.ship_address,',',csr.ship_city,',',csr.ship_state,',',csr.ship_zip_code,',',csr.ship_country,' | ',csr.ship_phone,' | ',csr.email) as shipInfo,
            yrr.reason,
            rop.comments,
			rop.seller_reshipment_comments,
			csr.ship_name,
            csr.ship_address,
            csr.ship_city,
            csr.ship_state,
            csr.ship_zip_code,
            csr.ship_phone,
            csr.email,
            csr.ship_country,
			csr.order_status
            FROM
                tb_sys_customer_sales_reorder csr
            LEFT JOIN tb_sys_customer_sales_reorder_line csrl ON csrl.reorder_header_id = csr.id
            LEFT JOIN oc_yzc_rma_order ro ON ro.id = csr.rma_id
            LEFT JOIN tb_sys_customer_sales_order tso ON tso.order_id = ro.from_customer_order_id and tso.buyer_id = ro.buyer_id
            LEFT JOIN oc_yzc_rma_order_product rop ON rop.rma_id = ro.id
            LEFT JOIN oc_order_product oop ON oop.order_id = ro.order_id  AND rop.product_id = oop.product_id
            LEFT JOIN tb_sys_dictionary tsd on tsd.DicCategory = 'CUSTOMER_ORDER_STATUS' and tsd.DicKey=csr.order_status
            LEFT JOIN oc_customerpartner_to_customer ctc on ctc.customer_id=ro.seller_id
            LEFT JOIN oc_yzc_rma_reason yrr on yrr.reason_id = rop.reason_id
            WHERE 1=1
            ";
        if (isset($filter_data['filter_reshipment_id']) && trim($filter_data['filter_reshipment_id']) != '') {
            $sql .= " AND csr.reorder_id LIKE '%" . $this->db->escape(trim($filter_data['filter_reshipment_id'])) . "%'";
        }
        if (isset($filter_data['filter_seller_status']) && trim($filter_data['filter_seller_status']) != -1) {
            $sql .= " AND rop.status_reshipment =" . $this->db->escape(trim($filter_data['filter_seller_status']));
        }
        if (isset($filter_data['filter_item_code']) && trim($filter_data['filter_item_code']) != '') {
            $sql .= " AND csrl.item_code LIKE '%" . $this->db->escape(trim($filter_data['filter_item_code'])) . "%'";
        }
        if (isset($filter_data['filter_sales_order_id']) && trim($filter_data['filter_sales_order_id']) != '') {
            $sql .= " AND ro.from_customer_order_id  LIKE '%" . $this->db->escape(trim($filter_data['filter_sales_order_id'])) . "%'";
        }

        if (isset($filter_data['filter_order_status']) && trim($filter_data['filter_order_status']) != '') {
            $sql .= " AND csr.order_status =" . $this->db->escape(trim($filter_data['filter_order_status']));
        }
        if (isset($filter_data['filter_store']) && trim($filter_data['filter_store']) != '') {
            $sql .= " AND ctc.customer_id =" . $this->db->escape(trim($filter_data['filter_store']));
        }
        if (isset($filter_data['filter_rma_id']) && trim($filter_data['filter_rma_id']) != '') {
            $sql .= " AND ro.rma_order_id LIKE '%" . $this->db->escape(trim($filter_data['filter_rma_id'])) . "%'";
        }

        if (isset($filter_data['filter_applyDateFrom']) && trim($filter_data['filter_applyDateFrom']) != '') {
            $sql .= " AND ro.create_time >= '" . $this->db->escape(trim($filter_data['filter_applyDateFrom'])) . "'";
        }
        if (isset($filter_data['filter_applyDateTo']) && trim($filter_data['filter_applyDateTo']) != '') {
            $sql .= " AND ro.create_time <= '" . $this->db->escape(trim($filter_data['filter_applyDateTo'])) . "'";
        }
        $sql .= " AND ro.buyer_id = " . $this->customer->getId();
        $sql .= " GROUP BY csr.id ";
        $sql .= " ORDER BY ro.id DESC";
        if (isset($filter_data['page_num']) || isset($filter_data['page_limit'])) {
            $sql .= " LIMIT " . (int)$filter_data['page_num'] . "," . (int)$filter_data['page_limit'];
        }
        $query = $this->db->query($sql);
        return $query->rows;
    }

    /**
     * [getReshipmentTrackingInfo description] reshipment tracking 改写
     * @param $data
     * @return array
     */
    public function getReshipmentTrackingInfo($data)
    {
        $allRes = [];
        $this->load->model('account/customer_order_import');
        foreach ($data as $key => $value) {
            //首先
            $product_list = explode(',', $value['product_ids']);
            $qty_list = explode(',', $value['product_qtys']);
            foreach ($product_list as $ks => $vs) {
                //产品有多个的情形
                //14370 【BUG】RMA Management
                $item_code = $this->orm->table(DB_PREFIX . 'product as ts')->where('ts.product_id', $vs)->orderBy('ts.product_id', 'desc')->value('ts.sku');
                if ($item_code == null) {
                    $comboInfo = null;
                } else {
                    $comboInfo = $this->orm->table('tb_sys_product_set_info as s')->where('p.product_id', $vs)->
                    leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 's.product_id')->
                    leftJoin(DB_PREFIX . 'product as pc', 'pc.product_id', '=', 's.set_product_id')->
                    whereNotNull('s.set_product_id')->select('p.product_id', 's.set_product_id', 's.qty', 'pc.sku')->get();
                    $comboInfo = obj2array($comboInfo);
                }
                if ($comboInfo) {
                    $length = count($comboInfo);
                    foreach ($comboInfo as $k => $v) {
                        //首先获取tacking_number
                        $mapTrackingInfo['k.SalerOrderLineId'] = $value['line_id'];
                        $mapTrackingInfo['k.SalesOrderId'] = $value['reorder_id'];
                        $mapTrackingInfo['k.parent_sku'] = $item_code;
                        $mapTrackingInfo['k.ShipSku'] = $v['sku'];
                        $mapTrackingInfo['k.status'] = 1;
                        $tracking_info = $this->orm->table('tb_sys_customer_sales_order_tracking as k')
                            ->leftJoin('tb_sys_carriers as c', 'c.CarrierID', '=', 'k.LogisticeId')
                            ->where($mapTrackingInfo)->select('c.CarrierName as carrier_name', 'k.TrackingNumber as tracking_number', 'k.status', 'k.ShipDeliveryDate')->orderBy('k.status', 'desc')->get();
                        unset($mapTrackingInfo);
                        $tracking_info = obj2array($tracking_info);
                        $tracking_info = $this->model_account_customer_order_import->dealTrackingNumberToShow($tracking_info, 1);
                        $data[$key]['tracking_number'] = $tracking_info['tracking_number'];
                        $data[$key]['carrier_name'] = $tracking_info['carrier_name'];
                        $data[$key]['tracking_status'] = $tracking_info['status'];
                        $data[$key]['ShipDeliveryDate'] = $tracking_info['ShipDeliveryDate'];
                        $data[$key]['child_sku'] = $v['sku'];
                        $data[$key]['all_qty'] = $v['qty'] * $qty_list[$ks];
                        $data[$key]['child_qty'] = $v['qty'];
                        $data[$key]['sku'] = $item_code;
                        $data[$key]['line_qty'] = $qty_list[$ks];
                        $allRes[] = $data[$key];
                    }
                } else {
                    //获取tracking_number
                    $mapTrackingInfo['k.SalerOrderLineId'] = $value['line_id'];
                    $mapTrackingInfo['k.SalesOrderId'] = $value['reorder_id'];
                    $mapTrackingInfo['k.ShipSku'] = $item_code;
                    $mapTrackingInfo['k.status'] = 1;
                    $tracking_info = $this->orm->table('tb_sys_customer_sales_order_tracking as k')
                        ->leftJoin('tb_sys_carriers as c', 'c.CarrierID', '=', 'k.LogisticeId')
                        ->where($mapTrackingInfo)->
                        select('c.CarrierName as carrier_name', 'k.TrackingNumber as tracking_number', 'k.status', 'k.ShipDeliveryDate')
                        ->orderBy('k.status', 'desc')->get();
                    $tracking_info = obj2array($tracking_info);
                    //一个处理tracking_info 的方法
                    $tracking_info = $this->model_account_customer_order_import->dealTrackingNumberToShow($tracking_info, 1);
                    unset($mapTrackingInfo);
                    $data[$key]['tracking_number'] = $tracking_info['tracking_number'];
                    $data[$key]['carrier_name'] = $tracking_info['carrier_name'];
                    $data[$key]['tracking_status'] = $tracking_info['status'];
                    $data[$key]['ShipDeliveryDate'] = $tracking_info['ShipDeliveryDate'];
                    $data[$key]['child_sku'] = null;
                    $data[$key]['all_qty'] = null;
                    $data[$key]['child_qty'] = null;
                    $data[$key]['sku'] = $item_code;
                    $data[$key]['line_qty'] = $qty_list[$ks];
                    $allRes[] = $data[$key];
                }

            }
        }
        return $allRes;

    }

    /**
     * [getReorderLinesTracking description] 获取line表信息
     * @param array $data
     * @param string $order_id 重发单订单ID
     * @return array
     * @throws Exception
     */
    public function getReorderLinesTracking($data, $order_id)
    {
        $allRes = [];
        $this->load->model('tool/image');
        $this->load->model('catalog/product');
        $this->load->model('account/customer_order_import');
        foreach ($data as $key => $value) {
            $tag_array = $this->model_catalog_product->getProductSpecificTag($value['product_id']);
            $detail_tags = array();
            if (isset($tag_array)) {
                foreach ($tag_array as $tag) {
                    if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                        //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                        $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                        $detail_tags[] = '<img data-toggle="tooltip"  class="' . $tag['class_style'] . '"  title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                    }
                }
            }
            $data['tag'] = $detail_tags;
            $item_code = $this->orm->table(DB_PREFIX . 'product as ts')->where('ts.sku', $value['item_code'])->where('ts.is_deleted', 0)->orderBy('ts.product_id', 'desc')->value('ts.product_id');
            if ($item_code == null) {
                $comboInfo = null;
            } else {
                $comboInfo = $this->orm->table('tb_sys_product_set_info as s')->where('p.product_id', $item_code)->
                leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 's.product_id')->
                leftJoin(DB_PREFIX . 'product as pc', 'pc.product_id', '=', 's.set_product_id')->
                whereNotNull('s.set_product_id')->select('p.product_id', 's.set_product_id', 's.qty', 'pc.sku')->get();
                $comboInfo = obj2array($comboInfo);
            }
            if ($comboInfo) {
                $length = count($comboInfo);
                foreach ($comboInfo as $k => $v) {
                    //首先获取tacking_number
                    $mapTrackingInfo['k.SalerOrderLineId'] = $value['id'];
                    $mapTrackingInfo['k.SalesOrderId'] = $order_id;
                    $mapTrackingInfo['k.parent_sku'] = $value['item_code'];
                    $mapTrackingInfo['k.ShipSku'] = $v['sku'];
                    //$mapTrackingInfo['k.status'] = 1;
                    $tracking_info = $this->orm->table('tb_sys_customer_sales_order_tracking as k')
                        ->leftJoin('tb_sys_carriers as c', 'c.CarrierID', '=', 'k.LogisticeId')
                        ->where($mapTrackingInfo)->select('c.CarrierName as carrier_name', 'k.TrackingNumber as tracking_number', 'k.status', 'k.ShipDeliveryDate')->orderBy('k.status', 'desc')->get();
                    unset($mapTrackingInfo);
                    $tracking_info = obj2array($tracking_info);
                    $tracking_info = $this->model_account_customer_order_import->dealTrackingNumberToShow($tracking_info, 1);
                    $data[$key]['tracking_number'] = $tracking_info['tracking_number'];
                    //英国订单,物流单号为JD开头,显示Carrier是Yodel
                    if( $this->customer->getCountryId() == 222 && 'JD' == substr($tracking_info['tracking_number'][0],0,2) && in_array($tracking_info['carrier_name'][0],CHANGE_CARRIER_NAME) ) {
                        $data[$key]['carrier_name'] = ['Yodel'];
                    }elseif ($this->customer->getCountryId() == 222  && in_array($tracking_info['carrier_name'][0],CHANGE_CARRIER_NAME) ){
                        $data[$key]['carrier_name'] = ['WHISTL'];
                    } else{
                        $data[$key]['carrier_name'] = $tracking_info['carrier_name'];
                    }
                    $data[$key]['tracking_status'] = $tracking_info['status'];
                    $data[$key]['ShipDeliveryDate'] = $tracking_info['ShipDeliveryDate'];
                    $data[$key]['child_sku'] = $v['sku'];
                    $data[$key]['all_qty'] = $v['qty'] * $value['qty'];
                    $data[$key]['child_qty'] = $v['qty'];
                    if ($k == 0) {
                        $data[$key]['cross_row'] = $length;
                    } else {
                        unset($data[$key]['cross_row']);
                    }
                    $allRes[] = $data[$key];
                }
            } else {
                //获取tracking_number
                $mapTrackingInfo['k.SalerOrderLineId'] = $value['id'];
                $mapTrackingInfo['k.ShipSku'] = $value['item_code'];
                $mapTrackingInfo['k.SalesOrderId'] = $order_id;
                //$mapTrackingInfo['k.status'] = 1;
                $tracking_info = $this->orm->table('tb_sys_customer_sales_order_tracking as k')
                    ->leftJoin('tb_sys_carriers as c', 'c.CarrierID', '=', 'k.LogisticeId')
                    ->where($mapTrackingInfo)->
                    select('c.CarrierName as carrier_name', 'k.TrackingNumber as tracking_number', 'k.status', 'k.ShipDeliveryDate')
                    ->orderBy('k.status', 'desc')->get();
                $tracking_info = obj2array($tracking_info);
                //一个处理tracking_info 的方法
                $tracking_info = $this->model_account_customer_order_import->dealTrackingNumberToShow($tracking_info, 1);
                unset($mapTrackingInfo);
                $data[$key]['tracking_number'] = $tracking_info['tracking_number'];
                //英国订单,物流单号为JD开头,显示Carrier是Yodel
                if( $this->customer->getCountryId() == 222 && isset($tracking_info['tracking_number'][0]) && 'JD' == substr($tracking_info['tracking_number'][0],0,2) && in_array($tracking_info['carrier_name'][0],CHANGE_CARRIER_NAME) ) {
                    $data[$key]['carrier_name'] = ['Yodel'];
                }elseif ($this->customer->getCountryId() == 222 && isset($tracking_info['tracking_number'][0])  && in_array($tracking_info['carrier_name'][0],CHANGE_CARRIER_NAME) ){
                    $data[$key]['carrier_name'] = ['WHISTL'];
                } else{
                    $data[$key]['carrier_name'] = $tracking_info['carrier_name'];
                }
                $data[$key]['tracking_status'] = $tracking_info['status'];
                $data[$key]['ShipDeliveryDate'] = $tracking_info['ShipDeliveryDate'];
                $data[$key]['cross_row'] = 1;
                $data[$key]['child_sku'] = null;
                $data[$key]['all_qty'] = null;
                $data[$key]['child_qty'] = null;
                $allRes[] = $data[$key];
            }
        }
        return $allRes;


    }

    /**
     * 获取RMA订单的运单号数据（仿照正常订单的获取方式）
     *
     * @param string $order_id 重发单订单ID
     * @param int $buyer_id
     * @return array
     */
    public function getRmaTrackingNumber($order_id, $buyer_id)
    {
        $sql = "SELECT
                  sot.TrackingNumber AS trackingNo,
                  car.carrierName AS carrierName,
                  sot.status
                FROM
                tb_sys_customer_sales_reorder csro
                INNER JOIN tb_sys_customer_sales_reorder_line csrl
                ON csro.`id` = csrl.`reorder_header_id`
                  INNER JOIN tb_sys_customer_sales_order_tracking sot
                    ON sot.SalerOrderLineId = csrl.id AND sot.`SalesOrderId` = csro.`reorder_id`
                  INNER JOIN tb_sys_carriers car
                    ON car.carrierID = sot.LogisticeId
                WHERE csro.`reorder_id` = '" . $this->db->escape($order_id) . "' AND csro.`buyer_id` = " . (int)$buyer_id . " ORDER BY sot.status DESC,sot.Id ASC";
        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * 返金数量
     * @param $filter_data
     * @return mixed
     */
    public function getRefundInfoCount($filter_data)
    {
        $sql = "SELECT
                 count(1) as total
                FROM
                    oc_yzc_rma_order yro
                LEFT JOIN oc_yzc_rma_order_product rop ON rop.rma_id = yro.id
                LEFT JOIN oc_customerpartner_to_customer ctc ON ctc.customer_id = yro.seller_id
                LEFT JOIN oc_product op ON op.product_id = rop.product_id
                LEFT JOIN oc_order_product oop ON oop.order_id = yro.order_id
                AND rop.product_id = oop.product_id
                LEFT JOIN tb_sys_customer_sales_order cso ON cso.order_id = yro.from_customer_order_id and cso.buyer_id=yro.buyer_id
                LEFT JOIN tb_sys_order_associated soa ON soa.sales_order_id = cso.id
                AND soa.order_product_id = oop.order_product_id
                WHERE
                    rop.rma_type != 1 ";
        if (isset($filter_data['filter_purchase_order_id']) && trim($filter_data['filter_purchase_order_id']) != '') {
            $sql .= " AND yro.order_id LIKE '%" . $this->db->escape(trim($filter_data['filter_purchase_order_id'])) . "%'";
        }
        if (isset($filter_data['filter_refund_seller_status']) && trim($filter_data['filter_refund_seller_status']) != -1) {
            $sql .= " AND rop.status_refund =" . $this->db->escape(trim($filter_data['filter_refund_seller_status']));
        }
        if (isset($filter_data['filter_refund_sales_order_id']) && trim($filter_data['filter_refund_sales_order_id']) != '') {
            $sql .= " AND yro.from_customer_order_id LIKE '%" . $this->db->escape(trim($filter_data['filter_refund_sales_order_id'])) . "%'";
        }
        if (isset($filter_data['filter_refund_store']) && trim($filter_data['filter_refund_store']) != '') {
            $sql .= " AND ctc.customer_id =" . $this->db->escape(trim($filter_data['filter_refund_store']));
        }
        if (isset($filter_data['filter_refund_rma_id']) && trim($filter_data['filter_refund_rma_id']) != '') {
            $sql .= " AND yro.rma_order_id LIKE '%" . $this->db->escape(trim($filter_data['filter_refund_rma_id'])) . "%'";
        }

        if (isset($filter_data['filter_refund_applyDateFrom']) && trim($filter_data['filter_refund_applyDateFrom']) != '') {
            $sql .= " AND yro.create_time >= '" . $this->db->escape(trim($filter_data['filter_refund_applyDateFrom'])) . "'";
        }
        if (isset($filter_data['filter_refund_applyDateTo']) && trim($filter_data['filter_refund_applyDateTo']) != '') {
            $sql .= " AND yro.create_time <= '" . $this->db->escape(trim($filter_data['filter_refund_applyDateTo'])) . "'";
        }
        $sql .= " AND yro.buyer_id = " . $this->customer->getId();
        $sql .= " ORDER BY yro.id DESC";
        $query = $this->db->query($sql);
        return $query->row['total'];
    }

    /**
     * 获取返金数据
     * @param $filter_data
     * @return mixed
     */
    public function getRefundInfo($filter_data)
    {
        $sql = "SELECT
                    rop.order_product_id,
                    yro.seller_id,
                    rop.rma_id,
                    rop.product_id,
                    rop.coupon_amount,
                    rop.campaign_amount,
                    yro.order_id,
                    cso.id as customer_order_id,
                    yro.from_customer_order_id,
                    yro.rma_order_id,
                    yro.order_type,
                    yro.cancel_rma as cancel_status,
                    op.sku,
                    o.delivery_type,
                    CASE
                WHEN yro.order_type = 2 THEN
                    rop.quantity
                WHEN  cso.order_status = ".CustomerSalesOrderStatus::COMPLETED." THEN
                    rop.quantity
                ELSE
                    soa.qty
                END AS qty,
                 rop.apply_refund_amount,
                 rop.actual_refund_amount,
                 rop.comments,
                 rop.seller_refund_comments,
                 yro.create_time,
                 yro.seller_status,
                 ctc.screenname,
                 opd.name,
                 yrr.reason,
                 oop.type_id,
                 oop.agreement_id,
                case when rop.status_refund=1 then 'Agree' when rop.status_refund=2 then 'Refuse' else '' end as statusRefund,
                case when rop.refund_type = 1 then 'Line Of Credit' when rop.refund_type = 3 then 'Credit Card' else '' end as refundType
                FROM
                    oc_yzc_rma_order yro
                left join oc_order as o on yro.order_id=o.order_id
                LEFT JOIN oc_yzc_rma_order_product rop ON rop.rma_id = yro.id
                LEFT JOIN oc_customerpartner_to_customer ctc ON ctc.customer_id = yro.seller_id
                LEFT JOIN oc_product op ON op.product_id = rop.product_id
                LEFT JOIN oc_product_description opd ON opd.product_id=op.product_id
                LEFT JOIN oc_order_product oop ON oop.order_id = yro.order_id
                AND rop.product_id = oop.product_id
                LEFT JOIN tb_sys_customer_sales_order cso ON cso.order_id = yro.from_customer_order_id and cso.buyer_id=yro.buyer_id
                LEFT JOIN tb_sys_order_associated soa ON soa.sales_order_id = cso.id
                AND soa.order_product_id = oop.order_product_id
                 LEFT JOIN oc_yzc_rma_reason yrr on yrr.reason_id = rop.reason_id
                WHERE
                    rop.rma_type != 1 ";
        if (isset($filter_data['filter_purchase_order_id']) && trim($filter_data['filter_purchase_order_id']) != '') {
            $sql .= " AND yro.order_id LIKE '%" . $this->db->escape(trim($filter_data['filter_purchase_order_id'])) . "%'";
        }
        if (isset($filter_data['filter_refund_seller_status']) && trim($filter_data['filter_refund_seller_status']) != -1) {
            $sql .= " AND rop.status_refund =" . $this->db->escape(trim($filter_data['filter_refund_seller_status']));
        }
        if (isset($filter_data['filter_refund_sales_order_id']) && trim($filter_data['filter_refund_sales_order_id']) != '') {
            $sql .= " AND yro.from_customer_order_id LIKE '%" . $this->db->escape(trim($filter_data['filter_refund_sales_order_id'])) . "%'";
        }
        if (isset($filter_data['filter_refund_store']) && trim($filter_data['filter_refund_store']) != '') {
            $sql .= " AND ctc.customer_id =" . $this->db->escape(trim($filter_data['filter_refund_store']));
        }
        if (isset($filter_data['filter_refund_rma_id']) && trim($filter_data['filter_refund_rma_id']) != '') {
            $sql .= " AND yro.rma_order_id LIKE '%" . $this->db->escape(trim($filter_data['filter_refund_rma_id'])) . "%'";
        }

        if (isset($filter_data['filter_refund_applyDateFrom']) && trim($filter_data['filter_refund_applyDateFrom']) != '') {
            $sql .= " AND yro.create_time >= '" . $this->db->escape(trim($filter_data['filter_refund_applyDateFrom'])) . "'";
        }
        if (isset($filter_data['filter_refund_applyDateTo']) && trim($filter_data['filter_refund_applyDateTo']) != '') {
            $sql .= " AND yro.create_time <= '" . $this->db->escape(trim($filter_data['filter_refund_applyDateTo'])) . "'";
        }
        $sql .= " AND yro.buyer_id = " . $this->customer->getId();
        $sql .= " ORDER BY yro.id DESC";
        if (isset($filter_data['page_num']) || isset($filter_data['page_limit'])) {
            $sql .= " LIMIT " . (int)$filter_data['page_num'] . "," . (int)$filter_data['page_limit'];
        }
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getRmaInfo($rma_order_id)
    {
        $result = $this->orm->table('vw_rma_order_info as roi')
            ->select('roi.*')
            ->where('roi.rma_order_id', '=', $rma_order_id)
            ->first();
        return obj2array($result);
    }

    public function deleteRMAImage($id)
    {
        $this->db->query("delete from oc_yzc_rma_file where id=" . $id);
    }

    /**
     * 更新rma信息
     * @param string $rmaOrderId rma_order_id
     * @param array $rmaOrder 要更新的rma信息
     * @return bool
     */
    public function updateRmaOrder($rmaOrderId, $rmaOrder): bool
    {
        $rmaResult = $this->orm->table('oc_yzc_rma_order')
            ->where('rma_order_id', '=', $rmaOrderId)
            ->select()
            ->first();
        $rmaResultArray = obj2array($rmaResult);
        $this->orm->table('oc_yzc_rma_order_history')->insert($rmaResultArray);
        return (bool)$this->orm->table('oc_yzc_rma_order')
            ->where('rma_order_id', '=', $rmaOrderId)
            ->update($rmaOrder);
    }

    /**
     * 更新rma product信息
     * @param int $rmaId rma id
     * @param array $rmaOrderProduct 需要更新的信息
     * @return bool
     */
    public function updateRmaOrderProduct($rmaId, $rmaOrderProduct):bool
    {
        $rmaProduct = $this->orm
            ->table('oc_yzc_rma_order_product')
            ->where('rma_id', '=', $rmaId)
            ->first();
        $this->orm
            ->table('oc_yzc_rma_order_product_history')
            ->insert(obj2array($rmaProduct));
        return (bool)$this->orm->table('oc_yzc_rma_order_product')
            ->where('rma_id', '=', $rmaId)
            ->update($rmaOrderProduct);
    }

    /**
     * 更新重发单信息
     * @param array $customerSalesReorder
     * @param int $rmaId rma id
     */
    public function updateReOrder($customerSalesReorder, $rmaId)
    {
        $this->orm
            ->table('tb_sys_customer_sales_reorder')
            ->where('rma_id', '=', $rmaId)
            ->update($customerSalesReorder);
        $reorder = $this->orm
            ->table('tb_sys_customer_sales_reorder')
            ->where('rma_id', '=', $rmaId)
            ->first();
        $this->orm
            ->table('tb_sys_customer_sales_reorder_history')
            ->insert(obj2array($reorder));
    }

    /**
     * 更新重发单明细信息
     * @param array $customerSalesReorderLine
     * @param int $headerId
     */
    public function updateReOrderLine($customerSalesReorderLine, $headerId)
    {
        $this->orm
            ->table('tb_sys_customer_sales_reorder_line')
            ->where('reorder_header_id', '=', $headerId)
            ->update($customerSalesReorderLine);
        $reorderLine = $this->orm
            ->table('tb_sys_customer_sales_reorder_line as csrl')
            ->where('csrl.reorder_header_id', '=', $headerId)
            ->select('csrl.*')
            ->first();
        $this->orm
            ->table('tb_sys_customer_sales_reorder_line_history')
            ->insert(obj2array($reorderLine));
    }

    public function checkProcessingRmaByOrderId($customer_id, $from_order_id)
    {
        $result = $this->orm->table('oc_yzc_rma_order as ro')
            ->where([
                ['ro.buyer_id', '=', $customer_id],
                ['ro.from_customer_order_id', '=', $from_order_id]
            ])
            ->whereRaw('(ro.seller_status <>2 and ro.cancel_rma <>1)')
            ->count();
        return $result;
    }

    /**
     * 获取订单的关联数量
     * @param int $order_id oc_order表的order_id
     * @param int $product_id
     * @return object|null
     * @author xxl
     */
    public function getAssociateOrderCount($order_id, $product_id)
    {
        $result = $this->orm->table('oc_order_product as oop')
            ->leftJoin("tb_sys_order_associated as soa", "oop.order_product_id", "=", "soa.order_product_id")
            ->whereRaw("oop.order_id=" . $order_id . " and oop.product_id=" . $product_id)
            ->selectRaw("ifnull(sum(soa.qty),0) as assQty,oop.quantity")
            ->first();
        return $result;
    }

    public function deleteReorder($rmaId)
    {
        //删除reorder
        $this->orm->getConnection()->getPdo()->exec("DELETE csrl from tb_sys_customer_sales_reorder_line csrl
            LEFT JOIN tb_sys_customer_sales_reorder csr on csr.id=csrl.reorder_header_id where csr.rma_id=" . $rmaId);
        $this->orm->getConnection()->getPdo()->exec("DELETE from tb_sys_customer_sales_reorder where rma_id =" . $rmaId);
    }

    public function getMarginInfoByRmaId($rmaId)
    {
        $map = [
            'yrop.rma_id' => $rmaId,
            'oop.type_id' => 2, // margin 2 rebate 1 详见oc_setting
        ];
        $list = $this->orm->table(DB_PREFIX . 'yzc_rma_order_product as yrop')
            ->leftJoin(DB_PREFIX . 'order_product as oop', 'oop.order_product_id', '=', 'yrop.order_product_id')
            ->leftJoin('tb_sys_margin_agreement as a', 'a.id', '=', 'oop.agreement_id')
            ->leftJoin('tb_sys_margin_process as s', 's.margin_id', '=', 'a.id')
            ->where($map)
            ->selectRaw('s.advance_order_id,s.margin_agreement_id,a.deposit_per,a.price,s.margin_id')
            ->get()
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        foreach ($list as $key => &$value) {
            // 获取此agreement_id下的主履约人
            $mapCommon = [
                'agreement_type' => $this->config->get('common_performer_type_margin_spot'),
                'agreement_id' => $value['margin_id'],
                'is_signed' => 1,
            ];
            $buyer_id = $this->orm->table(DB_PREFIX . 'agreement_common_performer')->where($mapCommon)->value('buyer_id');
            $value['purchase_order_link'] = str_ireplace('&amp;', '&', $this->url->link('account/order/purchaseOrderInfo', '&order_id=' . $value['advance_order_id'] . '&buyer_id=' . $buyer_id, true));
            $value['margin_order_link'] = str_ireplace('&amp;', '&', $this->url->link('account/product_quotes/margin/detail_list', '&id=' . $value['margin_id'], true));
            $value['deposit_per_show'] = $this->currency->format($value['deposit_per'], session('currency'));
            $value['price_show'] = $this->currency->format($value['price'], session('currency'));
            $value['icon'] = sprintf(TRANSACTION_TYPE_ICON[2], $value['margin_agreement_id']);
            $value['url_margin'] = $this->url->link('account/product_quotes/margin/detail_list', 'id=' . $value['margin_id'], true);
        }
        return current($list);
    }


    public function getFutureMarginInfoByRmaId($rmaId)
    {
        $map = [
            'yrop.rma_id' => $rmaId,
            'oop.type_id' => 3, // margin 2 rebate 1  future 3详见oc_setting
        ];
        $list = $this->orm->table(DB_PREFIX . 'yzc_rma_order_product as yrop')
            ->leftJoin(DB_PREFIX . 'order_product as oop', 'oop.order_product_id', '=', 'yrop.order_product_id')
            ->leftJoin(DB_PREFIX . 'futures_margin_agreement as a', 'a.id', '=', 'oop.agreement_id')
            ->leftJoin(DB_PREFIX . 'futures_margin_delivery as s', 's.agreement_id', '=', 'a.id')
            ->leftJoin(DB_PREFIX . 'futures_margin_process as mp', 'mp.agreement_id', '=', 'a.id')
            ->where($map)
            ->selectRaw('mp.advance_order_id,a.contract_id,a.agreement_no,s.last_unit_price,a.unit_price,s.agreement_id')
            ->get()
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        foreach ($list as $key => &$value) {
            $value['purchase_order_link'] = str_ireplace('&amp;', '&', $this->url->link('account/order/purchaseOrderInfo', '&order_id=' . $value['advance_order_id'], true));
            if($value['contract_id']){
                //期货保证金二期
                $future_link = str_ireplace('&amp;', '&', $this->url->link('account/product_quotes/futures/buyerFuturesBidDetail', '&id=' . $value['agreement_id'], true));
            } else {
                //期货保证金一期
                $future_link = str_ireplace('&amp;', '&', $this->url->link('account/product_quotes/futures/detail', '&id=' . $value['agreement_id'], true));
            }
            $value['future_margin_order_link'] = $future_link;
            $value['deposit_per_show'] = $this->currency->format($value['unit_price'] - $value['last_unit_price'], session('currency'));
            $value['deposit_per'] = sprintf('%.2f', $value['unit_price'] - $value['last_unit_price']);
            $value['price_show'] = $this->currency->format($value['unit_price'], session('currency'));
            $value['icon'] = sprintf(TRANSACTION_TYPE_ICON[3], $value['agreement_no']);
        }
        unset($value);
        return current($list);
    }

    /**
     * [getMarginInfoByOrderIdAndProductId description]
     * @param int $order_id oc_order表的order_id
     * @param int $product_id
     * @return array
     */
    public function getMarginInfoByOrderIdAndProductId($order_id, $product_id)
    {
        $map = [
            'oop.order_id' => $order_id,
            'oop.product_id' => $product_id,
            'oop.type_id' => 2, // margin 2 rebate 1 详见oc_setting
        ];
        $list = $this->orm->table(DB_PREFIX . 'order_product as oop')
            ->leftJoin('tb_sys_margin_agreement as a', 'a.id', '=', 'oop.agreement_id')
            ->leftJoin('tb_sys_margin_process as s', 's.margin_id', '=', 'a.id')
            ->where($map)
            ->selectRaw('s.advance_order_id,s.margin_agreement_id,a.deposit_per,a.price,s.margin_id')
            ->get()
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        foreach ($list as $key => &$value) {
            $value['purchase_order_link'] = str_ireplace('&amp;', '&', $this->url->link('account/order/purchaseOrderInfo', '&order_id=' . $value['advance_order_id'], true));
            $value['margin_order_link'] = str_ireplace('&amp;', '&', $this->url->link('account/product_quotes/margin/detail_list', '&id=' . $value['margin_id'], true));
            $value['deposit_per_show'] = $this->currency->format($value['deposit_per'], session('currency'));
            $value['price_show'] = $this->currency->format($value['price'], session('currency'));
            $value['icon'] = TRANSACTION_TYPE_ICON[2];
        }
        return current($list);
    }

    public function getFutureMarginInfoByOrderIdAndProductId($order_id, $product_id)
    {
        $map = [
            'oop.order_id' => $order_id,
            'oop.product_id' => $product_id,
            'oop.type_id' => 3, // margin 2 rebate 1 详见oc_setting
        ];
        $list = $this->orm->table(DB_PREFIX . 'order_product as oop')
            ->leftJoin(DB_PREFIX . 'futures_margin_agreement as a', 'a.id', '=', 'oop.agreement_id')
            ->leftJoin(DB_PREFIX . 'futures_margin_delivery as s', 's.agreement_id', '=', 'a.id')
            ->leftJoin(DB_PREFIX . 'futures_margin_process as mp', 'mp.agreement_id', '=', 'a.id')
            ->where($map)
            ->selectRaw('mp.advance_order_id,a.agreement_no,s.last_unit_price,a.unit_price,s.agreement_id')
            ->get()
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        return current($list);
    }

    /**
     * 判断是否为包销产品
     * @param int $product_id
     * @return bool
     * @author xxl
     */
    public function checkMarginProduct($product_id)
    {
        $countNum = $this->orm->table('tb_underwriting_shop_product_mapping')
            ->where('underwriting_product_id', '=', $product_id)
            ->count();
        return $countNum > 0;
    }

    /**
     * 判断是否为有保证金合同的包销产品
     * @param int $order_id oc_order表的order_id
     * @param int $product_id
     * @return bool
     * @throws Exception
     * @author xxl
     */
    public function checkMarginProductHaveProcess($order_id, $product_id)
    {
        $this->load->model('account/customerpartner/margin_order');
        $res = $this->model_account_customerpartner_margin_order
            ->getAgreementInfoByOrderProduct((int)$order_id, (int)$product_id);
        if ($res === null) return false;
        return true;
    }

    /**
     * 根据保证金尾款的product_id获取保证金头款的采购信息
     * @param int $order_product_id 采购订单明细主键
     * @return array
     * @throws
     * @author xxl
     */
    public function getMarginAdvanceOrderInfo($order_product_id)
    {
        // 逻辑变更，尾款产品不是唯一的导致
        $this->load->model('account/customerpartner/margin_order');
        $order_product_info = $this->orm->table('oc_order_product')
            ->where('order_product_id', $order_product_id)->first();
        $agree_info = $this->model_account_customerpartner_margin_order->getAgreementInfoByOrderProduct(
            $order_product_info->order_id, $order_product_info->product_id
        );
        $result = $this->orm->table('tb_sys_margin_process as smp')
            ->leftJoin('tb_sys_margin_agreement as sma', 'smp.margin_id', '=', 'sma.id')
            ->leftJoin('oc_order_product as oop', [['oop.order_id', '=', 'smp.advance_order_id'], ['oop.product_id', '=', 'smp.advance_product_id']])
            ->select('sma.deposit_per')
            ->selectRaw('smp.advance_product_id,smp.advance_order_id,sma.num,sma.money,sma.price as originalUnitPrice,oop.price,oop.service_fee_per,oop.poundage')
            ->where(['smp.margin_id' => $agree_info['id']])
            ->first();

        return obj2array($result);
    }

    /**
     * [getFutureMarginAdvanceOrderInfo description] 根据协议id 获取头款的信息
     * @param int $agreement_id 各个交易类型设计的协议记录主键ID
     * @return mixed
     */
    public function getFutureMarginAdvanceOrderInfo($agreement_id)
    {

        $list = $this->orm->table(DB_PREFIX . 'futures_margin_process as mp')
            ->crossJoin(DB_PREFIX . 'order_product as oop', [['oop.agreement_id', '=', 'mp.agreement_id'], ['oop.product_id', '=', 'mp.advance_product_id']])
            ->leftJoin(DB_PREFIX . 'futures_margin_agreement as a', 'a.id', '=', 'oop.agreement_id')
            ->leftJoin(DB_PREFIX . 'futures_margin_delivery as s', 's.agreement_id', '=', 'a.id')
            ->where('oop.agreement_id', $agreement_id)
            ->selectRaw('mp.advance_product_id,a.num,mp.advance_order_id,oop.price,oop.service_fee_per,oop.poundage,s.last_unit_price,a.unit_price as originalUnitPrice,oop.poundage')
            ->get()
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        return current($list);
    }

    /**
     * 保证金的价格展示
     * @param int $product_id
     * @param int $quantity
     * @param int $orderProductId 采购订单明细主键
     * @return array
     * @author xxl
     */
    public function getMarginPriceInfo($product_id, $quantity, $orderProductId)
    {
        $isEuropeCountry = $this->customer->isEurope();
        $orderProduct = $this->getOrderProductById($orderProductId, $isEuropeCountry);
        $advanceOrderInfo = $this->getMarginAdvanceOrderInfo($orderProductId);
        //运费：(运费+打包费)
        $freight_per = $orderProduct['freight_per'] + $orderProduct['package_fee'];
        $freightPerCurrency = $this->currency->format($freight_per, session('currency'));
        //单价:总单价(保证金头款单价+保证金尾款单价)
        //尾款单价
        $restUnitPrice = $orderProduct['price'];
        $restUnitPriceCurrency = $this->currency->format($restUnitPrice, session('currency'));
        //头款单价
        $advanceUnitPrice = $advanceOrderInfo['deposit_per'] ?? 0;
        $advanceUnitPriceCurrency = $this->currency->format($advanceUnitPrice, session('currency'));
        //总单价
        $unitMarginPriceShow = $this->currency->format($restUnitPrice, session('currency'));
        $unitPrice = $restUnitPrice + $advanceUnitPrice;
        $unitPriceCurrency = $this->currency->format($unitPrice, session('currency'));
        $unitPriceShow = $unitPriceCurrency . "<br/>" . "(" . $advanceUnitPriceCurrency . "+" . $restUnitPriceCurrency . ")";
        //服务费：总服务费(保证金投款服务费+保证金尾款服务费),避免误差，由原始总价反推
        $originalUnitPrice = $advanceOrderInfo['originalUnitPrice'] ?? 0;
        $serviceFeePer = $originalUnitPrice - $unitPrice;
        $serviceFeePerCurrency = $this->currency->format($serviceFeePer, session('currency'));
        //直接取头款商品时候的单个服务费
        //$advanceServiceFeePer = $advanceOrderInfo['service_fee_per'];
        $advanceServiceFeePer = 0;
        $advanceServiceFeePerCurrency = $this->currency->format($advanceServiceFeePer, session('currency'));
        $restServiceFeePer = $orderProduct['service_fee_per'];
        $restServiceFeePerCurrency = $this->currency->format($restServiceFeePer, session('currency'));
        //$serviceFeeShow = $serviceFeePerCurrency."<br/>"."(".$advanceServiceFeePerCurrency."+".$restServiceFeePerCurrency.")";
        $serviceFeeShow = $serviceFeePerCurrency;
        //手续费(总手续费(保证金头款手续费+保证金尾款手续费)),
        $advancePoundagePer = !empty($advanceOrderInfo['num']) ? round($advanceOrderInfo['poundage'] / $advanceOrderInfo['num'], 2) : 0;
        $advancePoundagePerCurrency = $this->currency->format($advancePoundagePer, session('currency'));
        $restPoundagePer = $orderProduct['unit_poundage'];
        $restPoundagePerCurrency = $this->currency->format($restPoundagePer, session('currency'));
        $poundagePer = $advancePoundagePer + $restPoundagePer;
        $poundagePerCurrency = $this->currency->format($poundagePer, session('currency'));
        if ($poundagePer == 0) {
            $poundageShow = $poundagePerCurrency;
        } else {
            $poundageShow = $poundagePerCurrency . "<br/>" . "(" . $advancePoundagePerCurrency . "+" . $restPoundagePerCurrency . ")";
        }
        //总价 产品总价(保证金头款总价+保证金尾款总价+手续费总价)
        //头款总价 = （头款单价+头款服务费+头款手续费）*数量
        $advanceTotal = ($advanceUnitPrice + $advanceServiceFeePer) * $quantity;
        $advanceTotalCurrency = $this->currency->format($advanceTotal, session('currency'));
        //尾款总价 = （尾款单价+尾款服务费+尾款手续费）*数量
        //尾款总价 = （尾款单价+尾款服务费+尾款手续费）*数量
        $restTotal = ($restUnitPrice + $restServiceFeePer) * $quantity;
        $restTotalCurrency = $this->currency->format($restTotal, session('currency'));
        $freight = $freight_per * $quantity;
        $freightCurrency = $this->currency->format($freight, session('currency'));
        $total = $advanceTotal + $restTotal + $freight_per * $quantity;
        $totalCurrency = $this->currency->format($total, session('currency'));
        $totalShow = $totalCurrency . "<br/>" . "(" . $advanceTotalCurrency . "+" . $restTotalCurrency . "+" . $freightCurrency . ")";

        $resultArray = array(
            'unitPrice' => $unitPriceShow,
            'unitMarginPrice' => $unitMarginPriceShow,
            'advanceUnitPrice' => $advanceUnitPriceCurrency,
            'serviceFee' => $serviceFeeShow,
            'restServiceFee' => $restServiceFeePerCurrency,
            'poundage' => $poundageShow,
            'total' => $totalShow,
            'totalMargin' => $totalCurrency,
            'totalPrice' => $total,
            'transactionFee' => $poundagePer,
            'restTotal' => $restTotal,
            'advanceOrderId' => $advanceOrderInfo['advance_order_id'] ?? 0,
            'advanceProductId' => $advanceOrderInfo['advance_product_id'] ?? 0,
            'num' => $advanceOrderInfo['num'] ?? 0,
            'freight' => $freightPerCurrency,
            'advancePrice' => $advanceTotalCurrency,
            'resetPrice' => $restTotalCurrency,
            // add
            'advance_unit_price' => $advanceUnitPrice,
            'rest_unit_price' => $restUnitPrice,
            'freight_unit_price' => $freight_per,
            'poundage_per' => $poundagePer,
            'service_fee_per' => $serviceFeePer,
        );
        return $resultArray;
    }

    /**
     * [getFutureMarginPriceInfo description]
     * @param int $agreement_id
     * @param int $quantity
     * @param int $orderProductId 采购订单明细主键
     * @return array
     */
    public function getFutureMarginPriceInfo($agreement_id, $quantity, $orderProductId)
    {
        $isEuropeCountry = $this->customer->isEurope();
        $orderProduct = $this->getOrderProductById($orderProductId, $isEuropeCountry);
        $advanceOrderInfo = $this->getFutureMarginAdvanceOrderInfo($agreement_id);
        //运费：(运费+打包费)
        $freight_per = $orderProduct['freight_per'] + $orderProduct['package_fee'];
        $freightPerCurrency = $this->currency->format($freight_per, session('currency'));
        //单价:总单价(保证金头款单价+保证金尾款单价)
        //尾款单价
        $restUnitPrice = $orderProduct['price'];
        $restUnitPriceCurrency = $this->currency->format($restUnitPrice, session('currency'));
        //头款单价
        $advanceUnitPrice = $advanceOrderInfo['originalUnitPrice'] - $advanceOrderInfo['last_unit_price'];
        $advanceUnitPriceCurrency = $this->currency->format($advanceUnitPrice, session('currency'));
        //总单价
        $unitPrice = $restUnitPrice + $advanceUnitPrice;
        $unitPriceCurrency = $this->currency->format($unitPrice, session('currency'));
        $unitFutureMarginPriceShow = $this->currency->format($restUnitPrice, session('currency'));
        $unitPriceShow = $unitPriceCurrency . "<br/>" . "(" . $advanceUnitPriceCurrency . "+" . $restUnitPriceCurrency . ")";
        //服务费：总服务费(保证金投款服务费+保证金尾款服务费),避免误差，由原始总价反推
        $originalUnitPrice = $advanceOrderInfo['originalUnitPrice'];
        $serviceFeePer = $originalUnitPrice - $unitPrice;
        $serviceFeePerCurrency = $this->currency->format($serviceFeePer, session('currency'));
        $advanceServiceFeePer = 0;
        $advanceServiceFeePerCurrency = $this->currency->format($advanceServiceFeePer, session('currency'));
        $restServiceFeePer = $orderProduct['service_fee_per'];
        $restServiceFeePerCurrency = $this->currency->format($restServiceFeePer, session('currency'));
        //$serviceFeeShow = $serviceFeePerCurrency."<br/>"."(".$advanceServiceFeePerCurrency."+".$restServiceFeePerCurrency.")";
        $serviceFeeShow = $serviceFeePerCurrency;
        //手续费(总手续费(保证金头款手续费+保证金尾款手续费)),
        $advancePoundagePer = round($advanceOrderInfo['poundage'] / $advanceOrderInfo['num'], 2);
        $advancePoundagePerCurrency = $this->currency->format($advancePoundagePer, session('currency'));
        $restPoundagePer = $orderProduct['unit_poundage'];
        $restPoundagePerCurrency = $this->currency->format($restPoundagePer, session('currency'));
        $poundagePer = $advancePoundagePer + $restPoundagePer;
        $poundagePerCurrency = $this->currency->format($poundagePer, session('currency'));
        if ($poundagePer == 0) {
            $poundageShow = $poundagePerCurrency;
        } else {
            $poundageShow = $poundagePerCurrency . "<br/>" . "(" . $advancePoundagePerCurrency . "+" . $restPoundagePerCurrency . ")";
        }
        //总价 产品总价(保证金头款总价+保证金尾款总价+手续费总价)
        //头款总价 = （头款单价+头款服务费+头款手续费）*数量
        $advanceTotal = ($advanceUnitPrice + $advanceServiceFeePer) * $quantity;
        $advanceTotalCurrency = $this->currency->format($advanceTotal, session('currency'));
        //尾款总价 = （尾款单价+尾款服务费+尾款手续费）*数量
        $restTotal = ($restUnitPrice + $restServiceFeePer) * $quantity;
        $restTotalCurrency = $this->currency->format($restTotal, session('currency'));
        $freight = $freight_per * $quantity;
        $freightCurrency = $this->currency->format($freight, session('currency'));
        $total = $advanceTotal + $restTotal + $freight_per * $quantity;
        $totalCurrency = $this->currency->format($total, session('currency'));
        $totalShow = $totalCurrency . "<br/>" . "(" . $advanceTotalCurrency . "+" . $restTotalCurrency . "+" . $freightCurrency . ")";
        $resultArray = [
            'unitPrice' => $unitPriceShow,
            'unitFutureMarginPrice' => $unitFutureMarginPriceShow,
            'advanceUnitPrice' => $advanceUnitPriceCurrency,
            'serviceFee' => $serviceFeeShow,
            'poundage' => $poundageShow,
            'total' => $totalShow,
            'totalPrice' => $total,
            'totalFutureMargin' => $totalCurrency,
            'transactionFee' => $poundagePer,
            'restTotal' => $restTotal,
            'advanceOrderId' => $advanceOrderInfo['advance_order_id'],
            'advanceProductId' => $advanceOrderInfo['advance_product_id'],
            'num' => $advanceOrderInfo['num'],
            'freight' => $freightPerCurrency,
            'advancePrice' => $advanceTotalCurrency,
            'resetPrice' => $restTotalCurrency,
            // add
            'advance_unit_price' => $advanceUnitPrice,
            'rest_unit_price' => $restUnitPrice,
            'freight_unit_price' => $freight_per,
            'poundage_per' => $poundagePer,
            'service_fee_per' => $serviceFeePer,
        ];
        return $resultArray;
    }


    public function checkMarginAdvanceProduct($product_id)
    {
        $countNum = $this->orm->table('tb_sys_margin_process')
            ->where('advance_product_id', '=', $product_id)
            ->count();
        return $countNum > 0;
    }

    /**
     * 销售订单的RMA修改,如果有未处理的相同order_id的RMA不允许被修改
     * @param $sale_order_id
     * @param int $buyer_id
     * @param int $seller_id
     * @param $rma_id
     * @return int
     * @author  xxl
     */
    public function countNoProcessedRMABySaleOrderId($sale_order_id, $buyer_id, $seller_id, $rma_id)
    {
        return $this->orm->table('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product as rop', 'ro.id', '=', 'rop.rma_id')
            ->where([
                ['ro.from_customer_order_id', '=', $sale_order_id],
                ['ro.buyer_id', '=', $buyer_id],
                ['ro.seller_id', '=', $seller_id],
                ['ro.seller_status', '<>', '2'],
                ['ro.cancel_rma', '=', 0],
                ['ro.id', '<>', $rma_id]
                //    ['rop.status_refund','<>',2]
            ])
            ->count();
    }

    public function get_agreement_product($data)
    {
        if (!$data) {
            return array();
        }
        $res = $this->orm->table('tb_sys_margin_agreement as ma')
            ->leftJoin('tb_sys_margin_process as mp', 'ma.id', '=', 'mp.margin_id')
            ->leftJoin('oc_product as op', 'mp.advance_product_id', '=', 'op.product_id')
            ->where([
                ['mp.advance_order_id', '=', $data['orderId']],
                ['op.sku', '=', $data['item_code']]
            ]);
        return $res->count();
    }

    public function checkCwfRmaByOrderId($customer_id, $customer_order_id)
    {
        $result = $this->orm->table('tb_sys_customer_sales_order as cso')
            ->leftJoin('tb_sys_order_associated as soa', 'soa.sales_order_id', '=', 'cso.id')
            ->leftJoin('oc_order as oo', 'oo.order_id', '=', 'soa.order_id')
            ->where([
                ['cso.buyer_id', '=', $customer_id],
                ['cso.order_id', '=', $customer_order_id]
            ])
            ->whereRaw('oo.delivery_type = '.OrderDeliveryType::CWF.' and cso.order_status <>'.CustomerSalesOrderStatus::CANCELED)
            ->count();
        return $result;
    }

    public function getRmaOrderByIdIn($rmaId = array()) {
        if (!empty($rmaId)) {
           return $this->orm->table('oc_yzc_rma_order')
                ->whereIn('id', $rmaId)->get();
        }
        return null;
    }


}
