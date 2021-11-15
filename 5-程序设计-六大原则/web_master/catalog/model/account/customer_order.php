<?php

use App\Enums\ModifyLog\CommonOrderActionStatus;
use App\Enums\Pay\PayCode;
use App\Enums\SalesOrder\CustomerSalesOrderLineItemStatus;
use App\Enums\SalesOrder\CustomerSalesOrderMode;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\Customer\CustomerAccountingType;

/**
 * Class ModelAccountCustomerOrder
 */

use App\Enums\SalesOrder\HomePickImportMode;
use App\Enums\SalesOrder\HomePickLabelReviewStatus;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Services\SalesOrder\SalesOrderService;
use Catalog\model\account\sales_order\SalesOrderManagement;

class ModelAccountCustomerOrder extends Model {
    const OTHER_PLATFORM_START_NUM = 4;
    const PLATFORM_TO_IMPORT_MODE = [
        1 => 4,
        2 => 5,
        3 => 7,
        4 => 6,
        5 => 6,
        6 => 6,
        7 => 6,
        8 => 8,
    ];
    protected $sales_model;
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->sales_model =  new SalesOrderManagement($registry);
    }
    public function getOrders($start = 0, $limit = 20) {
        if ($start < 0) {
            $start = 0;
        }

        if ($limit < 1) {
            $limit = 1;
        }

        $query = $this->db->query("SELECT * FROM tb_sys_customer_sales_order cso WHERE cso.`BuyerID` = '".(int)$this->customer->getId()."' ORDER BY cso.`Id` ASC LIMIT " . (int)$start . "," . (int)$limit);

        return $query->rows;
    }

    public function getTotalOrders(){
        $query = $this->db->query("SELECT COUNT(*) AS total FROM tb_sys_customer_sales_order WHERE BuyerID = '" . (int)$this->customer->getId() . "'");
        return $query->row['total'];
    }

    public function getOrderTrack($order_id){
        $query = $this->db->query("SELECT * FROM tb_sys_customer_sales_order_tracking WHERE OrderId = '" . $order_id . "' ORDER BY Id");

        if($query->num_rows){
            return $query-rows;
        }else{
            return false;
        }
    }

    public function queryOrderNum($data = [], $flag = false)
    {
        //排序
        $order_str = '';
        $sort_data = array(
            'c.order_id',
            'c.create_time',
            'c.order_status'
        );
        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $order_str .= " ORDER BY " . $data['sort'];
        } else {
            $order_str .= " ORDER BY c.order_id";
        }
        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $order_str .= " DESC";
        } else {
            $order_str .= " ASC";
        }


        //MySQL最多只能返回长度为102400的字符串
        $sql = "SELECT  DISTINCT c.id
                    FROM  tb_sys_customer_sales_order c ";

        //更改filter_import_mode -1 不查询  1,2,3 查询import mode
        if (isset($data['filter_import_mode']) && $data['filter_import_mode'] >= self::OTHER_PLATFORM_START_NUM) {
           $sql .= ' left join tb_sys_customer_sales_order_other_temp as oot on oot.sales_order_id = c.order_id and c.buyer_id = oot.buyer_id';
        }

        if(isset($data['label_tag']) && $data['label_tag']){
            $sql .= ' inner join tb_sys_customer_sales_order_label_review as lr on lr.order_id = c.id and c.buyer_id = lr.buyer_id and lr.status ='.HomePickLabelReviewStatus::REJECTED .' and c.order_status <> '. CustomerSalesOrderStatus::CANCELED ;
        }

        if ($flag == true) {
            if ($data['filter_cancel_not_applied_rma'] == 0) {
                $sql .= "
                    LEFT JOIN (
                        SELECT * FROM tb_sys_customer_order_modify_log WHERE id IN (
                            SELECT MAX(id) FROM tb_sys_customer_order_modify_log GROUP BY header_id
                        )
                    ) AS coml ON coml.header_id=c.id
                        AND coml.order_id=c.order_id
                        AND coml.order_type=1
                        AND coml.`status`=2
                        AND coml.modified_record LIKE '%Cancelled%'
                        AND (coml.before_record LIKE '%Being Processed%' OR coml.before_record LIKE '%On Hold%')
                    LEFT JOIN (
                        SELECT  header_id,connection_relation FROM tb_sys_customer_sales_order_cancel WHERE id IN
                        (
                            SELECT MAX(id) FROM tb_sys_customer_sales_order_cancel GROUP BY header_id ORDER BY id
                        )
                    ) AS cancel ON cancel.header_id=c.id ";
            }
        }
        //上段SQL的解释：
        //销售订单表tb_sys_customer_sales_order的修改日志，
        //如果是B2B Buyer修改，则更新日志记录在表tb_sys_customer_order_modify_log(新增)中；
        //如果是B2B管理后台修改，则更新日志记录在表tb_sys_customer_sales_order的字段memo(更新)中 和 表tb_sys_customer_sales_order_cancel(新增)中

        if (isset($data['filter_pick_up_status']) && $data['filter_pick_up_status'] != '-1') {
            $sql .= ' left join tb_sys_customer_sales_order_pick_up as pu on pu.sales_order_id = c.id';
        }

        //tracking number 和 sku 都是关联表的 所以一旦发生都需要联表
        if ($data['filter_tracking_number'] == 2) {
            if (isset($data['filter_delivery_status']) && $data['filter_delivery_status'] != -1) {
                $sql .= ' left join tb_sys_customer_sales_order_tracking as t on t.SalesOrderId = c.order_id ';
                $sql .= ' left join tb_tracking_facts as fact on fact.sales_order_id = t.SalesOrderId ';
            }
        } elseif ($data['filter_tracking_number'] == 1 || $data['filter_tracking_number'] == 0) {
            $sql .= ' left join tb_sys_customer_sales_order_tracking as t on t.SalesOrderId = c.order_id ';
            if (isset($data['filter_delivery_status']) && $data['filter_delivery_status'] != -1) {
                $sql .= ' left join tb_tracking_facts as fact on fact.sales_order_id = t.SalesOrderId ';
            }
        }
        if (isset($data['filter_item_code']) && $data['filter_item_code'] != null && $data['filter_item_code'] != '') {
            $sql .= ' left join tb_sys_customer_sales_order_line as l on l.header_id = c.id ';
        }
        $sql .= ' WHERE 1=1 ';

        if ($flag == true) {
            if ($data['filter_cancel_not_applied_rma'] == 0) {
                $sql .= "
                    AND c.id NOT IN (
                        SELECT id FROM tb_sys_customer_sales_order WHERE order_status=".CustomerSalesOrderStatus::CANCELED." AND buyer_id=" . $data['customer_id'] . " AND memo LIKE '%1->16%'
                    )
                    AND NOT EXISTS (
                        SELECT ro.id FROM oc_yzc_rma_order ro
                        WHERE ro.from_customer_order_id=c.order_id
                            AND ro.order_type=1
                            AND ro.buyer_id=c.buyer_id
                    )";
            }
        }

        if (isset($data['tracking_privilege']) && $data['tracking_privilege']) {
            if ($data['filter_tracking_number'] == 1) {
                $sql .= ' And t.TrackingNumber is not null and c.order_status =' . CustomerSalesOrderStatus::COMPLETED;
            } elseif ($data['filter_tracking_number'] == 0) {
                $sql .= ' And (t.TrackingNumber is null or (t.TrackingNumber is not null and c.order_status != '.CustomerSalesOrderStatus::COMPLETED.')) ';
            }
        } else {
            if (isset($data['filter_tracking_number']) && $data['filter_tracking_number'] == 1) {
                $sql .= ' And t.TrackingNumber is not null';
            } elseif ($data['filter_tracking_number'] == 0) {
                $sql .= ' And t.TrackingNumber is null';
            }
        }

        if (isset($data['filter_item_code']) && $data['filter_item_code'] != null && $data['filter_item_code'] != '') {
            $sql .= " And l.item_code like '%" . trim($data['filter_item_code']) . "%'";
        }


        $implode = array();

        if (isset($data['filter_orderId']) && !is_null($data['filter_orderId'])) {
            $implode[] = "c.order_id like '%" . $data['filter_orderId'] . "%'";
        }

        if (isset($data['filter_orderStatus']) && !is_null($data['filter_orderStatus'])) {
            $implode[] = " c.order_status = '" . (int)$data['filter_orderStatus'] . "'";
        }

        if (isset($data['filter_orderDate_from']) && !empty($data['filter_orderDate_from'])) {
            $implode[] = " c.create_time >= '" . $this->db->escape($data['filter_orderDate_from']) . "'";
        }

        if (isset($data['filter_orderDate_to']) && !empty($data['filter_orderDate_to'])) {
            $implode[] = " c.create_time <= '" . $this->db->escape($data['filter_orderDate_to']) . "'";
        }

        if (isset($data['filter_delivery_status']) && $data['filter_delivery_status'] != -1) {
            $implode[] = " fact.carrier_status = " . (int)$data['filter_delivery_status'];
        }

        if(isset($data['filter_import_mode'])
            && $data['filter_import_mode'] == HomePickImportMode::IMPORT_MODE_WALMART)
        {
            $implode[] = " ((c.import_mode = ".HomePickImportMode::IMPORT_MODE_NORMAL." ) or ( c.import_mode = ".HomePickImportMode::US_OTHER." and oot.other_platform_id = '" . (int)$data['filter_import_mode'] . "')) ";
        }else{
            if (isset($data['filter_import_mode']) && isset(self::PLATFORM_TO_IMPORT_MODE[$data['filter_import_mode']])) {
                $implode[] = " c.import_mode = '" . self::PLATFORM_TO_IMPORT_MODE[$data['filter_import_mode']]. "'";
            }

            if (isset($data['filter_import_mode'])
                && $data['filter_import_mode'] >= self::OTHER_PLATFORM_START_NUM
                && self::PLATFORM_TO_IMPORT_MODE[$data['filter_import_mode']] != HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP
            )
            {
                $implode[] = " oot.other_platform_id = '" . (int)$data['filter_import_mode'] . "'";
            }

        }
        $implode[] = " c.buyer_id = " . $data['customer_id'];

        if ($implode) {
            $sql .= " AND " . implode(" AND ", $implode);
        }

        // 过滤掉云送仓订单
        if (isset($data['delivery_type']) && in_array($data['delivery_type'], [0, 1])) {
            $sql .= " AND not exists( select cl.sales_order_id from oc_order_cloud_logistics as cl where cl.sales_order_id = c.id )";
        }

        if (isset($data['filter_cancel_not_applied_rma']) && $data['filter_cancel_not_applied_rma'] == 0) {
            $sql .= " AND (coml.remove_bind=0 OR cancel.connection_relation=1) ";
        }
        if (isset($data['filter_pick_up_status']) && $data['filter_pick_up_status'] != '-1') {
            $sql .= " AND pu.pick_up_status='" . $data['filter_pick_up_status'] . "'";
        }
        $sql .= $order_str;
        $query = $this->db->query($sql);
        $ids = array_column($query->rows, 'id');
        if ($flag == true) {
            $res['idStr'] = implode(',', $ids);
            $res['total'] = count($ids);
            return $res;
        }
        return count($ids);
    }

    public function  queryOrders($data = array(),$idStr = null) {

        $sql = "select c.*,oot.other_platform_id,group_concat(oco.date_modified) as `date_modified`
                    ,ro.id AS rma_key
                    ,ro.rma_order_id
                    ,coml.before_record
                    ,coml.remove_bind
                    ,lr.status as lr_status
                    ,pu.pick_up_status
                    ,pu.apply_date as pick_up_apply_date
                    from `tb_sys_customer_sales_order` c ";
        //13854 【需求】Sales Order Management功能中将OrderData 更新为订单导入时间，增加付款完成时间列
        //oc_order中的
        $sql .= " left join tb_sys_order_associated as a on a.sales_order_id = c.id";
        $sql .= " LEFT JOIN oc_order AS oco ON a.order_id = oco.order_id";
        //tracking number 和 sku 都是关联表的 所以一旦发生都需要联表
        $sql .= ' left join tb_sys_customer_sales_order_other_temp as oot on oot.sales_order_id = c.order_id and c.buyer_id = oot.buyer_id';
        $sql .= ' left join tb_sys_customer_sales_order_label_review as lr on lr.order_id = c.id';
        $sql .= ' left join tb_sys_customer_sales_order_pick_up as pu on pu.sales_order_id = c.id';

        $condition_sub = '';
        if ($idStr) {
            $condition_sub = ' WHERE header_id IN (' . $idStr . ') ';
        }
        $sql .= "
            LEFT JOIN oc_yzc_rma_order ro ON ro.from_customer_order_id=c.order_id
                AND ro.order_type=1
                AND ro.buyer_id=c.buyer_id
            LEFT JOIN (
                SELECT * FROM tb_sys_customer_order_modify_log WHERE id IN (
                    SELECT MAX(id) FROM tb_sys_customer_order_modify_log {$condition_sub} GROUP BY header_id
                )
            ) AS coml ON coml.header_id=c.id
                AND coml.order_id=c.order_id
                AND coml.order_type=1
                AND coml.`status`=2 ";
        if ($data['filter_cancel_not_applied_rma'] == 0) {
            $sql.="
                LEFT JOIN (
                    SELECT  header_id,connection_relation FROM tb_sys_customer_sales_order_cancel WHERE id IN
                    (
                        SELECT MAX(id) FROM tb_sys_customer_sales_order_cancel GROUP BY header_id ORDER BY id
                    )
                ) AS cancel ON cancel.header_id=c.id ";
        }


        if($idStr !=  null){
            //如果销售单主键不为空，则进行指定查询；
            $sql .= ' where c.id in('.$idStr.') ';


            //分组、排序、不用分页，因为传入的订单ID有限
            $sort_data = array(
                'c.order_id',
                'c.create_time',
                'c.order_status'
            );
            $sql .= ' group by c.id ';
            if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
                $sql .= " ORDER BY " . $data['sort'];
            } else {
                $sql .= " ORDER BY c.order_id";
            }
            if (isset($data['order']) && ($data['order'] == 'DESC')) {
                $sql .= " DESC";
            } else {
                $sql .= " ASC";
            }
        }else{
            //如果销售单主键为空，则用完整的SQL再查一次；
            if($data['filter_tracking_number'] == 2){
                if (isset($data['filter_delivery_status']) && $data['filter_delivery_status'] != -1) {
                    $sql .= ' left join tb_sys_customer_sales_order_tracking as t on t.SalesOrderId = c.order_id ';
                    $sql .= ' left join tb_tracking_facts as fact on fact.sales_order_id = t.SalesOrderId ';
                }
            } elseif ($data['filter_tracking_number'] == 1 || $data['filter_tracking_number'] == 0){
                $sql .= ' left join tb_sys_customer_sales_order_tracking as t on t.SalesOrderId = c.order_id ';
                if (isset($data['filter_delivery_status']) && $data['filter_delivery_status'] != -1) {
                    $sql .= ' left join tb_tracking_facts as fact on fact.sales_order_id = t.SalesOrderId ';
                }
            }
            //物流状态
            if (isset($data['filter_delivery_status']) && $data['filter_delivery_status'] != -1) {
                $sql .= ' left join tb_tracking_facts as fact on fact.sales_order_id = c.order_id ';
            }
            if($data['filter_item_code'] != null && $data['filter_item_code'] != ''){
                $sql .= ' left join tb_sys_customer_sales_order_line as l on l.header_id = c.id ';
            }
            $sql .= ' WHERE 1=1 ';

            if ($data['filter_cancel_not_applied_rma'] == 0) {
                $sql .= "
                    AND c.id NOT IN (
                        SELECT id FROM tb_sys_customer_sales_order WHERE order_status=".CustomerSalesOrderStatus::CANCELED." AND buyer_id=".$data['customer_id']." AND memo LIKE '%1->16%'
                    )
                    AND NOT EXISTS (
                        SELECT ro.id FROM oc_yzc_rma_order ro
                        WHERE ro.from_customer_order_id=c.order_id
                            AND ro.order_type=1
                            AND ro.buyer_id=c.buyer_id
                    ) ";
            }

            if($data['filter_item_code'] != null && $data['filter_item_code'] != ''){
                $sql .= " And l.item_code like '%" . trim($data['filter_item_code']). "%'";
            }

            if($data['tracking_privilege']){
                if($data['filter_tracking_number'] == 1){
                    $sql .= ' And t.TrackingNumber is not null and c.order_status = ' . CustomerSalesOrderStatus::COMPLETED;
                } elseif ($data['filter_tracking_number'] == 0 ){
                    $sql .= ' And (t.TrackingNumber is null or (t.TrackingNumber is not null and c.order_status != ' . CustomerSalesOrderStatus::COMPLETED . ')) ';
                }
            }else{
                if($data['filter_tracking_number'] == 1){
                    $sql .= ' And t.TrackingNumber is not null';
                } elseif ($data['filter_tracking_number'] == 0 ){
                    $sql .= ' And t.TrackingNumber is null';
                }
            }

            $implode = array();

            if (isset($data['filter_orderId']) && !is_null($data['filter_orderId'])) {
                $data['filter_orderId'] = htmlspecialchars_decode($data['filter_orderId']);
                $implode[] = " c.order_id like '%" . $data['filter_orderId'] . "%'";
            }

            if (isset($data['filter_orderStatus']) && !is_null($data['filter_orderStatus'])) {
                $implode[] = " c.order_status = '" . (int)$data['filter_orderStatus'] . "'";
            }

            if (!empty($data['filter_orderDate_from'])) {
                $implode[] = " c.create_time >= '" . $this->db->escape($data['filter_orderDate_from']) . "'";
            }

            if (!empty($data['filter_orderDate_to'])) {
                $implode[] = " c.create_time <= '" . $this->db->escape($data['filter_orderDate_to']) . "'";
            }

            if (isset($data['filter_delivery_status']) && $data['filter_delivery_status'] != -1) {
                $implode[] = " fact.carrier_status = " . (int)$data['filter_delivery_status'];
            }

            if(isset($data['filter_import_mode'])
                && $data['filter_import_mode'] == HomePickImportMode::IMPORT_MODE_WALMART)
            {
                $implode[] = " ((c.import_mode = ".HomePickImportMode::IMPORT_MODE_NORMAL." ) or ( c.import_mode = ".HomePickImportMode::US_OTHER." and oot.other_platform_id = '" . (int)$data['filter_import_mode'] . "')) ";
            }else{
                if (isset($data['filter_import_mode']) && isset(self::PLATFORM_TO_IMPORT_MODE[$data['filter_import_mode']])) {
                    $implode[] = " c.import_mode = '" . self::PLATFORM_TO_IMPORT_MODE[$data['filter_import_mode']]. "'";
                }

                if (isset($data['filter_import_mode'])
                    && $data['filter_import_mode'] >= self::OTHER_PLATFORM_START_NUM
                    && self::PLATFORM_TO_IMPORT_MODE[$data['filter_import_mode']] != HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP
                )
                {
                    $implode[] = " oot.other_platform_id = '" . (int)$data['filter_import_mode'] . "'";
                }

            }

            // 过滤掉云送仓订单
            if (isset($data['delivery_type'])&&in_array($data['delivery_type'],[0,1])) {
                $sql .=" AND not exists( select cl.sales_order_id from oc_order_cloud_logistics as cl where cl.sales_order_id = c.id )";
            }

            if ($data['filter_cancel_not_applied_rma'] == 0) {
                $sql .= " AND (coml.remove_bind=0 OR cancel.connection_relation=1) ";
            }

            if (isset($data['filter_pick_up_status']) && $data['filter_pick_up_status'] != '-1') {
                $sql .= " AND pu.pick_up_status='" . $data['filter_pick_up_status'] . "'";
            }

            $implode[] = "c.buyer_id = " . $data['customer_id'];
            if ($implode) {
                $sql .= " AND " . implode(" AND ", $implode);
            }


            //分组、排序、分页
            $sort_data = array(
                'c.order_id',
                'c.create_time',
                'c.order_status'
            );
            $sql .= ' group by c.id ';
            if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
                $sql .= " ORDER BY " . $data['sort'];
            } else {
                $sql .= " ORDER BY c.order_id";
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
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function queryOrderForUpdate($data = array()) {

        $sql = "SELECT c.order_id AS orderId,c.ship_method AS shipMethod,sol.item_code AS itemCode,sol.qty AS qty,GROUP_CONCAT(DISTINCT tsc.CarrierName separator ';') AS carrierName,GROUP_CONCAT(sot.TrackingNumber separator ';') AS trackingNumber,GROUP_CONCAT(DISTINCT DATE_FORMAT(sot.ShipDeliveryDate,'%Y-%m-%d %H:%i:%s') separator ';') AS ShipDate ";
        $sql .= "FROM `tb_sys_customer_sales_order` c,tb_sys_customer_sales_order_line sol ";
        $sql .= "LEFT JOIN tb_sys_customer_sales_order_tracking sot ON sol.id=sot.salerOrderLineId ";
        $sql .= "LEFT JOIN tb_sys_carriers tsc ON sot.logisticeId=tsc.carrierId ";
        $sql .= "WHERE c.id=sol.header_id ";

        if($data['filter_tracking_number'] == 1){
            $sql .= ' And sot.TrackingNumber is not null';
        } elseif ($data['filter_tracking_number'] == 0 ){
            $sql .= ' And sot.TrackingNumber is null';
        }

        if($data['filter_item_code'] != null && $data['filter_item_code'] != ''){
            $sql .= " And sol.item_code like '%" . trim($data['filter_item_code']). "%'";
        }

        $implode = array();

        if (isset($data['filter_orderId']) && !is_null($data['filter_orderId'])) {
            $implode[] = "c.order_id like '%" . $data['filter_orderId'] . "%'";
        }

        if (isset($data['filter_orderStatus']) && !is_null($data['filter_orderStatus'])) {
            $implode[] = "c.order_status = '" . (int)$data['filter_orderStatus'] . "'";
        }

        if (!empty($data['filter_orderDate_from'])) {
            $implode[] = " DATE(c.create_time) >= DATE('" . $this->db->escape($data['filter_orderDate_from']) . "')";
        }

        if (!empty($data['filter_orderDate_to'])) {
            $implode[] = " DATE(c.create_time) <= DATE('" . $this->db->escape($data['filter_orderDate_to']) . "')";
        }
        $implode[] = "c.buyer_id = " . $data['customer_id'];
        if ($implode) {
            $sql .= " AND " . implode(" AND ", $implode);
        }

        $sort_data = array(
            'c.order_id',
            'c.order_date',
            'c.order_status'
        );

        $sql .= " GROUP BY sol.id ";

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY c.order_id";
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

    public function queryOrderByOrderId($orderId){
        return $this->db->query('SELECT * FROM `tb_sys_customer_sales_order` cso WHERE cso.`id` = ' . intval($orderId))->rows;
    }

    /**
     * [getDropShipNewOrderStatusOrders description]
     * @param int $customer_id
     * @param array $param
     * @return array
     */

    public function getDropShipNewOrderStatusOrders($customer_id,$param){
        $map = [
          ['cso.buyer_id','=' , $customer_id ],
          ['cso.order_status','=' , CustomerSalesOrderStatus::TO_BE_PAID ],
          //['cso.order_mode','=' , CustomerSalesOrderMode::PICK_UP ],
        ];
        if(isset($param['filter_buyer_orderId']) && $param['filter_buyer_orderId'] != '' ){
            $map[] = ['cso.order_id','like',"%{$param['filter_buyer_orderId']}%"];
        }
        if(isset($param['filter_buyer_item_code']) && $param['filter_buyer_item_code'] != '' ){
            $map[] = ['csol.item_code','like',"%{$param['filter_buyer_item_code']}%"];
        }
        $data = $this->orm->table('tb_sys_customer_sales_order as cso')->
                leftJoin('tb_sys_customer_sales_order_line as csol','cso.id','=','csol.header_id')
                ->where($map)->get();
        return obj2array($data);


    }

    public function getDropShipAllStatusOrders($customer_id,$param,$order_status){
        $map = [
            ['cso.buyer_id','=' , $customer_id ],
            ['cso.order_status','=' , $order_status ]
            //['cso.order_mode','=' , CustomerSalesOrderMode::PICK_UP ],
        ];
        if(isset($param['filter_buyer_orderId']) && $param['filter_buyer_orderId'] != '' ){
            $map[] = ['cso.order_id','like',"%{$param['filter_buyer_orderId']}%"];
        }
        if(isset($param['filter_buyer_item_code']) && $param['filter_buyer_item_code'] != '' ){
            $map[] = ['csol.item_code','like',"%{$param['filter_buyer_item_code']}%"];
        }
        $data = CustomerSalesOrder::query()->alias('cso')
            ->leftJoinRelations(['lines as csol'])
            ->where($map)
            ->orderBy('cso.run_id','desc')
            ->orderBy('csol.id')
            ->get();
        return obj2array($data);


    }

    public function getNewOrderStatusOrders($customer_id,$param)
    {
        $map = [
            ['cso.buyer_id','=' , $customer_id ],
            ['cso.order_status','=' , CustomerSalesOrderStatus::TO_BE_PAID ],
            //['cso.order_mode','!=' , CustomerSalesOrderMode::PICK_UP ],
        ];
        if(isset($param['filter_buyer_orderId']) && $param['filter_buyer_orderId'] != ''){
            $map[] = ['cso.order_id','like',"%{$param['filter_buyer_orderId']}%"];
        }
        if(isset($param['filter_buyer_item_code'])  && $param['filter_buyer_item_code'] != ''){
            $map[] = ['csol.item_code','like',"%{$param['filter_buyer_item_code']}%"];
        }

        $data = $this->orm->table('tb_sys_customer_sales_order as cso')->
        leftJoin('tb_sys_customer_sales_order_line as csol','cso.id','=','csol.header_id')
            ->where($map)->get();
        return obj2array($data);
        //return $this->db->query('SELECT * FROM `tb_sys_customer_sales_order` cso LEFT JOIN `tb_sys_customer_sales_order_line` csol ON cso.`id` = csol.`header_id` WHERE cso.`buyer_id` = ' . $customer_id . ' AND cso.`order_status` =  1')->rows;
    }

    /**
     * [getAllStatusOrders description]
     * @param int $customer_id
     * @param $param
     * @return array
     */
    public function getAllStatusOrders($customer_id,$param,$order_status)
    {
        $map = [
            ['cso.buyer_id','=' , $customer_id ],
            ['cso.order_status','=' , $order_status ]
            //['cso.order_mode','!=' , CustomerSalesOrderMode::PICK_UP ],
        ];
        if(isset($param['filter_buyer_orderId']) && $param['filter_buyer_orderId'] != ''){
            $map[] = ['cso.order_id','like',"%{$param['filter_buyer_orderId']}%"];
        }
        if(isset($param['filter_buyer_item_code'])  && $param['filter_buyer_item_code'] != ''){
            $map[] = ['csol.item_code','like',"%{$param['filter_buyer_item_code']}%"];
        }

        $data =  CustomerSalesOrder::query()->alias('cso')
            ->leftJoinRelations(['lines as csol'])
            ->where($map)
            ->orderBy('cso.run_id','desc')
            ->orderBy('csol.id','asc')
            ->get();
        return obj2array($data);
        //return $this->db->query('SELECT * FROM `tb_sys_customer_sales_order` cso LEFT JOIN `tb_sys_customer_sales_order_line` csol ON cso.`id` = csol.`header_id` WHERE cso.`buyer_id` = ' . $customer_id . ' AND cso.`order_status` =  1')->rows;
    }

    /**
     * [getDropShipLTLCheckStatusOrders description]
     * @param int $customer_id
     * @param $param
     * @return array
     */
    public function getDropShipLTLCheckStatusOrders($customer_id,$param){
        $map = [
            ['cso.buyer_id','=' , $customer_id ],
            ['cso.order_status','=' , CustomerSalesOrderStatus::LTL_CHECK ],
            //['cso.order_mode','=' , CustomerSalesOrderMode::PICK_UP ],
        ];
        if(isset($param['filter_buyer_orderId']) && $param['filter_buyer_orderId'] != '' ){
            $map[] = ['cso.order_id','like',"%{$param['filter_buyer_orderId']}%"];
        }
        if(isset($param['filter_buyer_item_code'])  && $param['filter_buyer_item_code'] != ''){
            $map[] = ['csol.item_code','like',"%{$param['filter_buyer_item_code']}%"];
        }

        $data = $this->orm->table('tb_sys_customer_sales_order as cso')->
        leftJoin('tb_sys_customer_sales_order_line as csol','cso.id','=','csol.header_id')
            ->where($map)->get();
        return obj2array($data);

    }

    public function getLTLCheckStatusOrders($customer_id,$param)
    {

        $map = [
            ['cso.buyer_id','=' , $customer_id ],
            ['cso.order_status','=' , CustomerSalesOrderStatus::LTL_CHECK ],
           // ['cso.order_mode','!=' , CustomerSalesOrderMode::PICK_UP ],
        ];
        if(isset($param['filter_buyer_orderId']) && $param['filter_buyer_orderId'] != ''){
            $map[] = ['cso.order_id','like',"%{$param['filter_buyer_orderId']}%"];
        }
        if(isset($param['filter_buyer_item_code']) && $param['filter_buyer_item_code'] != ''){
            $map[] = ['csol.item_code','like',"%{$param['filter_buyer_item_code']}%"];
        }


        $data = $this->orm->table('tb_sys_customer_sales_order as cso')->
        leftJoin('tb_sys_customer_sales_order_line as csol','cso.id','=','csol.header_id')
            ->where($map)->get();
        return obj2array($data);
        //return $this->db->query('SELECT * FROM `tb_sys_customer_sales_order` cso LEFT JOIN `tb_sys_customer_sales_order_line` csol ON cso.`id` = csol.`header_id` WHERE cso.`buyer_id` = ' . $customer_id . ' AND cso.`order_status` =  64')->rows;
    }

    /**
     * 获取对应订单line的tracking信息
     * @param int $id tb_sys_customer_sales_order_tracking的主键id
     * @param string $order_id tb_sys_customer_sales_order_tracking的order_id字段
     * @return array
     */
    public function getTrackingNumber($id,$order_id){
        $sql = 'select ';
        $sql .="ordline.qty,
                trac.parent_sku,
                trac.ShipSku,
                trac.TrackingNumber as trackingNo,
                if(car.carrierName='Truck',trac.ServiceLevelId,car.carrierName) AS carrierName,
                trac.status
                from tb_sys_customer_sales_order_line ordline
                INNER join tb_sys_customer_sales_order_tracking trac on trac.SalerOrderLineId = ordline.id
                INNER JOIN tb_sys_carriers car on car.carrierID = trac.LogisticeId
                where trac.SalesOrderId='".$order_id."' and ordline.header_id = ".(int)$id." ORDER BY trac.status DESC,trac.Id ASC";
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function cancelOrder($id){
        $result = $this->db->query("SELECT order_status FROM tb_sys_customer_sales_order WHERE id=".$this->db->escape($id))->row;
        if($result['order_status'] == CustomerSalesOrderStatus::TO_BE_PAID) {
            $this->db->query("UPDATE tb_sys_customer_sales_order SET order_status = ".CustomerSalesOrderStatus::CANCELED." where id =" . $this->db->escape($id));
            $this->db->query("UPDATE tb_sys_customer_sales_order_line SET item_status = ".CustomerSalesOrderLineItemStatus::CANCELED." where header_id =" . $this->db->escape($id));
        }
    }

    /**
     * 校验当前订单是否符合取消的条件
     * @param int $id 订单主键ID
     * @param int $order_id 订单
     * @param bool $is_auto_buyer 是否是自动购买
     * @param boolean|null $isCollectionFromDomicile
     * @return bool 是否允许取消
     */
    public function checkOrderCanBeCanceled($id, $order_id,$is_auto_buyer,$isCollectionFromDomicile = null)
    {
        //dropship 业务需要排除
        //获取usa dropship的group_id
        $default_group = [1,15];  //本来就有这个分组
        $hompick_group = COLLECTION_FROM_DOMICILE;
        $bp_cancel_group = array_merge($default_group,$hompick_group);
        $bp_cancel_group_str = implode(',',$bp_cancel_group);
        $countryCode = session('country');
        if ($countryCode != 'USA') {
            // 校验是否是欧洲上门取货，状态 new order check label
            if ($isCollectionFromDomicile && !$is_auto_buyer) {
                $canCancel = CustomerSalesOrder::query()->alias('cso')
                    ->select('cso.id')
                    ->where('cso.id', $id)
                    ->whereIn('cso.order_status', [CustomerSalesOrderStatus::TO_BE_PAID, CustomerSalesOrderStatus::ON_HOLD, CustomerSalesOrderStatus::CHECK_LABEL])
                    ->exists();
                return $canCancel;
            }
            //N-1039
            //非美国buyer，则按原有的规则
            $canCancel = CustomerSalesOrder::query()->alias('cso')
                ->join('tb_sys_customer_sales_order_line AS csol', 'cso.id', '=', 'csol.header_id')
                ->select('cso.id')
                ->where('cso.id', '=', $id)
                ->where('cso.order_id', '=', $order_id)
                ->whereIn('cso.order_status', [CustomerSalesOrderStatus::TO_BE_PAID, CustomerSalesOrderStatus::ON_HOLD])
                ->where('csol.item_status', '<>', CustomerSalesOrderLineItemStatus::SHIPPED)
                ->exists();
            return $canCancel;
        } else {
            //N-1039
            //美国buyer
            $canCancel = CustomerSalesOrder::query()->alias('cso')
                ->join('tb_sys_customer_sales_order_line AS csol', 'cso.id', '=', 'csol.header_id')
                ->select('cso.id')
                ->where('cso.id', '=', $id)
                ->where('cso.order_id', '=', $order_id)
                ->whereIn('cso.order_status', [CustomerSalesOrderStatus::TO_BE_PAID, CustomerSalesOrderStatus::BEING_PROCESSED, CustomerSalesOrderStatus::ON_HOLD, CustomerSalesOrderStatus::LTL_CHECK, CustomerSalesOrderStatus::ASR_TO_BE_PAID, CustomerSalesOrderStatus::CHECK_LABEL])
                ->where('csol.item_status', '<>', CustomerSalesOrderLineItemStatus::SHIPPED)
                ->exists();
            return $canCancel;
        }
    }

    /**
     * 校验当前订单是否正在执行取消操作
     * @param int $header_id 订单主键ID
     * @param int $process_code 操作类型，操作码 1:修改发货信息,2:修改SKU,3:取消订单
     * @param int|null $line_id
     * @return bool 是否正在取消
     */
    public function checkIsProcessing($header_id, $process_code, $line_id = null)
    {
        $builder = db('tb_sys_customer_order_modify_log')
            ->select('id')
            ->where('header_id', '=', (int)$header_id)
            ->where('process_code', '=', (int)$process_code)
            ->where('status', '=', CommonOrderActionStatus::PENDING);
        if (isset($line_id)) {
            $builder->where('line_id', '=', $line_id);
        }
        return $builder->exists();
    }

    /**
     * 查询上一次失败的结果日志
     * @param 操作类型，操作码 1:修改发货信息,2:修改SKU,3:取消订单
     * @param int $id   订单主键ID
     * @param $line_id
     * @return array
     */
    public function getLastFailureLog($process_code,$id,$line_id = null)
    {
        if($process_code == 1 || $process_code == 3){
            $process_code = '1,3';
        }
        if(isset($line_id)){
            $sql = "SELECT oml.status,oml.create_time AS operation_time,oml.process_code,oml.before_record AS previous_status,oml.modified_record AS target_status,oml.fail_reason FROM tb_sys_customer_order_modify_log oml WHERE oml.header_id = " . (int)$id . " AND oml.line_id = " . (int)$line_id . " AND oml.process_code IN (" . $process_code . ") ORDER BY id DESC LIMIT 1";
        }else{
            $sql = "SELECT oml.status,oml.create_time AS operation_time,oml.process_code,oml.before_record AS previous_status,oml.modified_record AS target_status,oml.fail_reason FROM tb_sys_customer_order_modify_log oml WHERE oml.header_id = " . (int)$id . " AND oml.process_code IN (" . $process_code . ") ORDER BY id DESC LIMIT 1";
        }
        $query = $this->db->query($sql);
        if(isset($query->row['status']) && $query->row['status'] == 3){
            return $query->rows;
        }
    }

    /**
     * 保存订单变更的操作日志
     * @param $data
     * @return int
     */
    public function saveSalesOrderModifyRecord($data)
    {
        if (isset($data)) {
/*            $sql = "INSERT INTO tb_sys_customer_order_modify_log (process_code,status,run_id,fail_reason,before_record,modified_record,header_id,order_id,line_id,order_type,remove_bind,create_time,update_time) VALUES ";
            $sql .= "(";
            $sql .= $data['process_code'] . ",";
            $sql .= $data['status'] . ",";
            $sql .= "'" . $data['run_id'] . "',";
            $sql .= "'',";
            $sql .= "'" . $data['before_record'] . "',";
            $sql .= "'" . $data['modified_record'] . "',";
            $sql .= $data['header_id'] . ",";
            $sql .= "'" . $data['order_id'] . "',";
            $sql .= (isset($data['line_id']) ? $data['line_id'] : "null") . ",";
            $sql .= $data['order_type'] . ",";
            $sql .= $data['remove_bind'] . ",";
            $sql .= "'" . $data['create_time'] ."',";
            $sql .= "NOW()";
            $sql .= ")";
            $this->db->query($sql);
            return $this->db->getLastId();*/

            $data['update_time'] = date('Y-m-d H:i:s');
            $id = $this->orm->table('tb_sys_customer_order_modify_log')
                ->insertGetId($data);
            return $id;
        }


    }

    /**
     * 更新销售订单的联动操作日志
     *
     * @param $log_id
     * @param $new_status
     * @param $fail_reason
     */
    public function updateSalesOrderModifyLog($log_id, $new_status, $fail_reason)
    {
        $sql = 'UPDATE tb_sys_customer_order_modify_log oml SET `status` = ' . (int)$new_status . ',fail_reason = \'' . $this->db->escape($fail_reason) . '\',update_time = NOW()' . ' WHERE id = ' . (int)$log_id;
        $this->db->query($sql);
    }

    /**
     * 检查此订单是否正在执行订单同步程序
     *
     * @param int $header_id
     * @return bool
     */
    public function checkOrderIsSyncing($header_id)
    {
        $sql = 'SELECT COUNT(*) as cnt FROM `tb_sys_customer_sales_order` tbo INNER JOIN `tb_sys_customer_sales_order_line` tbl ON tbo.`id` = tbl.`header_id`
                WHERE tbo.`id` = ' . (int)$header_id . ' AND tbl.`is_exported` IN (2,3);';
        $query = $this->db->query($sql);
        $bool = true;
        $row = $query->row;
        if(isset($row) && $row['cnt'] == 0){
            $bool = false;
        }
        return $bool;
    }

    /**
     * 检查此订单是否同步过,giga_onsite 和 omd  至少同步过一个，因为is_exported 是omd和giga onsite专用,这只是第一重验证
     * 备注：这个地方，写法暂时不换成app(xxx)这些写法，这块的逻辑，很有可能会连同上面的3个方法逻辑一起更改，而且判断顺序都先后严格限制!!
     * @param int $header_id
     * @return bool
     */
    public function checkOrderIsSyncingWithTwoSystem($headerId)
    {
        $sql = 'SELECT COUNT(*) as cnt FROM `tb_sys_customer_sales_order` tbo INNER JOIN `tb_sys_customer_sales_order_line` tbl ON tbo.`id` = tbl.`header_id`
                WHERE tbo.`id` = ' . (int)$headerId . ' AND tbl.`is_exported` IN (1,2,3);';
        $query = $this->db->query($sql);
        $bool = true; //同步过
        $row = $query->row;
        if (isset($row) && $row['cnt'] == 0) {
            $bool = false; //没有同步过
        }
        return $bool;
    }

    /**
     * 判断订单是否已经在业务上存在于OMD，依据成功导入和自动购买标志判断。
     *
     * @param int $header_id
     * @return bool
     */
    public function checkOrderShouldInOmd($header_id){
        $sql = "SELECT COUNT(*) AS cnt FROM tb_sys_customer_sales_order cso INNER JOIN tb_sys_customer_sales_order_line csol
                ON cso.id = csol.header_id WHERE (csol.is_exported = 1 OR csol.program_code = 'OMD SYN') AND cso.id = " . (int)$header_id;
        $query = $this->db->query($sql);
        $bool = true;
        $row = $query->row;
        if(isset($row) && $row['cnt'] == 0){
            $bool = false;
        }
        return $bool;
    }

    /**
     * 判断onsite订单是否存在，此方法调用之前，is_exported=2和3已经被拦截了
     * 备注：这个地方，写法暂时不换成app(xxx)这些写法，这块的逻辑，很有可能会连同上面的3个方法逻辑一起更改，而且判断顺序都先后严格限制!!
     * 备注：这个方法，方法体和上面一样，但是功能不一样，如果使用同一个命名，业务上会造成歧义，慎用！
     * @param $headerId
     * @return bool
     */
    public function checkOrderShouldInGigaOnsite($headerId)
    {
        $sql = "SELECT COUNT(*) AS cnt FROM tb_sys_customer_sales_order cso INNER JOIN tb_sys_customer_sales_order_line csol
                ON cso.id = csol.header_id WHERE (csol.is_exported = 1 ) AND cso.id = " . (int)$headerId;
        $query = $this->db->query($sql);
        $bool = true;
        $row = $query->row;
        if(isset($row) && $row['cnt'] == 0){
            $bool = false;
        }
        return $bool;
    }

    /**
     * 校验当前订单是否允许进行SKU修改操作
     * @param int $line_id
     * @param string $sku
     * @param int $buyer_id
     * @return bool
     */
    public function checkLineSkuCanChange($line_id, $sku, $buyer_id)
    {
        if(isset($line_id) && isset($sku)){
            //$query = $this->db->query("SELECT IF(COUNT(*)>0,TRUE,FALSE) AS sku_change FROM tb_sys_customer_sales_order tbo INNER JOIN tb_sys_customer_sales_order_line tbl ON tbo.id = tbl.header_id INNER JOIN oc_customer cus ON cus.customer_id = tbo.buyer_id INNER JOIN oc_product p ON p.sku = tbl.item_code WHERE cus.country_id = 223 AND tbo.order_status = 1 AND p.combo_flag  = 0 AND tbl.id = " . $line_id . " AND tbl.item_code = '" . $sku . "' AND tbo.buyer_id = " . $buyer_id);
            // N-130 欧洲+日本New Order状态下增加修改地址和修改ItemCode功能
            $query = $this->db->query("
                        SELECT IF(COUNT(*)>0,TRUE,FALSE) AS sku_change
                        FROM tb_sys_customer_sales_order tbo
                        INNER JOIN tb_sys_customer_sales_order_line tbl ON tbo.id = tbl.header_id
                        INNER JOIN oc_customer cus ON cus.customer_id = tbo.buyer_id
                        INNER JOIN oc_product p ON p.sku = tbl.item_code
                        WHERE
                            tbo.order_status = " . CustomerSalesOrderStatus::TO_BE_PAID . "
                        AND (( cus.country_id = 223 AND p.combo_flag  = 0) OR cus.country_id != 223)
                        AND tbl.id = " . $line_id . "
                        AND tbl.item_code = '" . $sku . "'
                        AND tbo.buyer_id = " . $buyer_id);
            $sku_change = false;
            if (isset($query->rows)) {
                $row = $query->row;
                if ($row['sku_change']) {
                    $sku_change = true;
                }
            }
            return $sku_change;
        }
    }

    /**
     * 检查订单是否允许修改发货信息
     * @param int $id 订单主键ID
     * @param bool|int $is_auto_buyer
     * @return bool
     */
    public function checkShippingCanChange($id,$is_auto_buyer)
    {
        //$query = $this->db->query("SELECT IF(COUNT(*) > 0,TRUE,FALSE) AS canChange FROM tb_sys_customer_sales_order cso INNER JOIN oc_customer c ON cso.buyer_id = c.customer_id INNER JOIN tb_sys_customer_sales_order_line csol ON cso.id = csol.header_id WHERE cso.order_status IN (1,2,4,128) AND c.country_id = 223 AND cso.id = " .(int)$id);
        // N-130 欧洲+日本New Order状态下增加修改地址和修改ItemCode功能
        $type = $this->customer->getAccountType(); // 1 内部  ，2 外部
        $country_id = $this->customer->getCountryId();
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        if($isCollectionFromDomicile && in_array($country_id,EUROPE_COUNTRY_ID) && !$is_auto_buyer){
            // 上门取货欧洲不允许更改地址
            return false;
        }
        $query = $this->db->query(
            "SELECT IF(COUNT(*) > 0,TRUE,FALSE) AS canChange
                FROM tb_sys_customer_sales_order cso
                INNER JOIN oc_customer c ON cso.buyer_id = c.customer_id
                INNER JOIN tb_sys_customer_sales_order_line csol ON cso.id = csol.header_id
                WHERE (
                       (cso.order_status IN (".CustomerSalesOrderStatus::TO_BE_PAID.",".CustomerSalesOrderStatus::BEING_PROCESSED.",".CustomerSalesOrderStatus::ON_HOLD.",".CustomerSalesOrderStatus::ASR_TO_BE_PAID.") AND c.country_id = 223)
                    OR (cso.order_status = ".CustomerSalesOrderStatus::TO_BE_PAID." AND c.country_id != 223 )
                    )
                AND cso.id = " .(int)$id);
        $can_change = false;

        if (isset($query->rows) && $query->num_rows == 1) {
            $row = $query->row;
            $bool = $row['canChange'];
            if ($bool) {
                $can_change = true;
            }
        }
        if($country_id != 223 && $type != 2){
            $can_change = false;
        }
        return $can_change;
    }

    /**
     * 获取用户当前SKU的购买的总库存数量
     * @param int $buyer_id
     * @param string $sku
     * @return int
     */
    public function getBuyerSumStockForSku($buyer_id, $sku)
    {
        $query = $this->db->query("SELECT SUM(cd.original_qty) AS sum_qty FROM oc_product p INNER JOIN tb_sys_cost_detail cd ON p.product_id = cd.sku_id WHERE cd.type=1 AND p.sku = '" . $sku . "' AND cd.buyer_id = " . $buyer_id);
        $qty = 0;
        if (isset($query->rows)) {
            $row = $query->row;
            if (isset($row['sum_qty'])) {
                $qty = $row['sum_qty'];
            }
        }
        return $qty;
    }

    /**
     * 获取用户已经消耗的（即已经添加了绑定关系的数量）
     * @param int $buyer_id
     * @param string $sku
     * @return int
     */
    public function getBuyerSumUsedStockForSku($buyer_id, $sku)
    {
        $query = $this->db->query("SELECT SUM(oa.qty) AS sum_qty FROM tb_sys_order_associated oa INNER JOIN oc_product p ON p.product_id = oa.product_id WHERE oa.buyer_id = " . $buyer_id . " AND p.sku = '" . $sku . "'");
        $qty = 0;
        if (isset($query->rows)) {
            $row = $query->row;
            if (isset($row['sum_qty'])) {
                $qty = $row['sum_qty'];
            }
        }
        return $qty;
    }

    /**
     * 获取本条明细的购买数量
     * @param $lineId
     * @return int
     */
    public function getOrderLineSkuQty($lineId){
        $query = $this->db->query("SELECT qty FROM tb_sys_customer_sales_order_line WHERE id = " .$lineId);
        $qty = 0;
        if (isset($query->rows)) {
            $row = $query->row;
            if (isset($row['qty'])) {
                $qty = $row['qty'];
            }
        }
        return $qty;
    }

    /**
     * 以订单明细ID获取订单信息
     * @param $line_id
     * @return mixed
     */
    public function getCurrentOrderInfo($line_id){
        $query = $this->db->query("SELECT cso.id AS header_id,cso.order_id,cso.ship_name,cso.ship_address1,cso.ship_city,cso.ship_state,cso.ship_zip_code,cso.ship_country,cso.ship_phone,cso.ship_method,cso.ship_company,cso.customer_comments,csol.id AS line_id,csol.line_item_number,csol.item_code,csol.qty,csol.program_code FROM tb_sys_customer_sales_order cso INNER JOIN tb_sys_customer_sales_order_line csol ON cso.id = csol.header_id WHERE csol.id = ".$line_id);
        return $query->rows;
    }

    /**
     * 根据订单头表ID获取订单信息
     * @param int $header_id
     * @return
     */
    public function getCurrentOrderInfoByHeaderId($header_id){
        $query = $this->db->query("SELECT cso.id AS header_id,cso.order_id,cso.order_status,cso.ship_name,cso.email,cso.ship_phone,cso.ship_address1,cso.ship_city,cso.ship_state,cso.ship_zip_code,cso.ship_country,cso.customer_comments,cso.program_code FROM tb_sys_customer_sales_order cso WHERE cso.id = ".(int)$header_id);
        return $query->rows;
    }

    /**
     * 根据订单主键ID查询OMD对应的storeId
     * @param int $header_id
     * @return null
     */
    public function getOmdStoreId($header_id){
        $query = $this->db->query("SELECT DISTINCT
                                          CASE
                                            WHEN cso.program_code = 'OMD SYN'
                                            THEN temp.buyer_id
                                            WHEN cso.program_code = 'B2B SYN'
                                            THEN 888
                                            WHEN gd.name = 'B2B-WillCall'
                                            THEN 212
                                            WHEN gd.name = 'US-DropShip-Buyer'
                                            THEN 205
                                            ELSE 888
                                          END AS store_id
                                        FROM
                                          tb_sys_customer_sales_order cso
                                          INNER JOIN oc_customer cus
                                            ON cus.customer_id = cso.buyer_id
                                          LEFT JOIN oc_customer_group_description gd
                                            ON cus.customer_group_id = gd.customer_group_id
                                          LEFT JOIN tb_sys_customer_sales_order_temp temp
                                            ON cso.order_id = temp.order_id
                                            AND cso.run_id = temp.run_id
                                        WHERE cso.id = ".(int)$header_id);
        $store_id = null;
        if (isset($query->rows)) {
            $row = $query->row;
            if (isset($row['store_id'])) {
                $store_id = $row['store_id'];
            }
        }
        return $store_id;
    }

    //此方法正常应该废弃掉了，调用此方法的地方均已废弃（取消订单，批量取消订单）
    public function getDropshipOmdStoreId($header_id){
        $query = $this->db->query("SELECT DISTINCT
                                          CASE
                                            WHEN cso.program_code = 'OMD SYN'
                                            THEN temp.buyer_id
                                            WHEN gd.name = 'B2B-Wayfair'
                                            THEN 212
                                            WHEN gd.name = 'US-DropShip-Buyer'
                                            THEN 205
                                            ELSE 888
                                          END AS store_id
                                        FROM
                                          tb_sys_customer_sales_dropship_temp temp
                                          INNER JOIN tb_sys_customer_sales_order cso
                                            ON cso.order_id = temp.order_id
                                            AND cso.run_id = temp.run_id
                                          INNER JOIN oc_customer cus
                                            ON cus.customer_id = cso.buyer_id
                                          INNER JOIN oc_customer_group_description gd
                                            ON cus.customer_group_id = gd.customer_group_id
                                        WHERE cso.id =".(int)$header_id);
        $store_id = null;
        if (isset($query->rows)) {
            $row = $query->row;
            if (isset($row['store_id'])) {
                $store_id = $row['store_id'];
            }
        }
        return $store_id;
    }


    /**
     * 检查本sku是否存在且不存在combo的情况
     * @param string $sku
     * @return int  1：sku未录入商品，2：sku有combo品设置，3：sku是超大件
     */
    public function checkProductExistAndNotCombo($sku)
    {
        $exist_query = $this->db->query("SELECT COUNT(*) AS exist_num FROM oc_product p WHERE p.sku = '" . $this->db->escape($sku) . "'");
        if(isset($exist_query->row) && $exist_query->row['exist_num'] == 0){
            return 1;
        }

        $combo_query = $this->db->query("SELECT COUNT(*) AS combo_num FROM oc_product p WHERE p.sku = '" . $this->db->escape($sku) . "' AND p.combo_flag = 1");
        if(isset($combo_query->row) && $combo_query->row['combo_num'] > 0){
            return 2;
        }

        $oversize_query = $this->db->query("SELECT COUNT(*) AS ltl_num FROM oc_product_to_tag ptt INNER JOIN oc_product p ON p.product_id = ptt.product_id WHERE p.sku = '" . $this->db->escape($sku) . "' and ptt.tag_id=1");
        if(isset($oversize_query->row) && $oversize_query->row['ltl_num'] > 0){
            return 3;
        }
    }

    /**
     * [checkProductExistInfo description] 根据sku和国别验证是否
     * @param string $sku
     * @param int $country_id
     * @return array|boolean
     */
    public function checkProductExistInfo($sku,$country_id){
        $map = [
          ['p.sku','=',$sku],
          ['c.country_id','=',$country_id],
        ];
        $res = $this->orm->table(DB_PREFIX.'product as p')
            ->leftJoin(DB_PREFIX.'customerpartner_to_product as ctp','p.product_id','=','ctp.product_id')
            ->leftJoin(DB_PREFIX.'customer as c','c.customer_id','=','ctp.customer_id')
            ->where($map)
            ->select('p.product_id','p.sku','c.status as c_status','p.buyer_flag','p.status as product_status','p.combo_flag')->first();
        $res = obj2array($res);
        if($res){
            $product_id = $res['product_id'];
            $mapTag = [
                ['product_id','=',$product_id],
                ['tag_id','=',1],
            ];
            $res['is_ltl'] = $this->orm->table(DB_PREFIX.'product_to_tag')->where($mapTag)->exists();
            return $res;
        }else{
            return false;
        }


    }

    /**
     * [changeSkuForUpdateOrderStatus description]
     * @param string $sku
     * @param int $country_id
     * @param string $order_id
     */
    public function changeSkuForUpdateOrderStatus($sku,$country_id,$order_id){
        $map = [
            ['p.sku','=',$sku],
            ['c.country_id','=',$country_id],
        ];
        $res = $this->orm->table(DB_PREFIX.'product as p')
            ->leftJoin(DB_PREFIX.'customerpartner_to_product as ctp','p.product_id','=','ctp.product_id')
            ->leftJoin(DB_PREFIX.'customer as c','c.customer_id','=','ctp.customer_id')
            ->where($map)
            ->select('p.product_id','p.sku','c.status as c_status','p.buyer_flag','p.status as product_status','p.combo_flag')->first();
        $res = obj2array($res);
        if($res){
            $product_id = $res['product_id'];
            $mapTag = [
                ['product_id','=',$product_id],
                ['tag_id','=',1],
            ];
            $is_ltl = $this->orm->table(DB_PREFIX.'product_to_tag')->where($mapTag)->exists();
            if($is_ltl){
                $this->orm->table('tb_sys_customer_sales_order')->where([
                    'order_id' => $order_id,
                    'buyer_id' => $this->customer->getId(),
                ])->update([
                    'order_status'=> CustomerSalesOrderStatus::LTL_CHECK,
                    'update_user_name'=> $this->customer->getId(),
                    'update_time'=> date('Y-m-d H:i:s',time()),
                ]);
            }
        }

    }

    /*
     * 销售订单和采购订单绑定后 更改销售订单状态为 BP
     *
     * */
    public function changeSalesOrderStatus($headerId,$shipMethod='')
    {
        $bindQty = $this->orm->table('tb_sys_order_associated')->where('sales_order_id',$headerId)->sum('qty');
        $salesQty = $this->orm->table('tb_sys_customer_sales_order_line')->where('header_id',$headerId)->sum('qty');

        if ($bindQty == $salesQty && $salesQty > 0){
            if ($shipMethod == 'ASR'){
                $orderStatus = CustomerSalesOrderStatus::ASR_TO_BE_PAID;
            }else{
                $orderStatus = CustomerSalesOrderStatus::BEING_PROCESSED;
            }

            $this->orm->table('tb_sys_customer_sales_order')
                ->where('id', $headerId)
                ->where('order_status', CustomerSalesOrderStatus::TO_BE_PAID)
                ->update([
                    'order_status'=> $orderStatus,
                    'update_user_name'=> $this->customer->getId(),
                    'update_time'=> date('Y-m-d H:i:s',time()),
                ]);
        }
    }

    /*
     * 申请了RMA未被拒的数量
     * */
    public function getBuyerSumRmaForSku($buyerId,$sku)
    {
        $qty = $this->orm->table('oc_yzc_rma_order as ro')
            ->leftjoin('oc_yzc_rma_order_product as rop', 'ro.id', 'rop.rma_id')
            ->where('ro.buyer_id', $buyerId)
            ->where('ro.cancel_rma', 0)
            ->where('ro.order_type', 2)
            ->where('rop.status_refund', '!=', 2)
            ->where('rop.item_code', $sku)
            ->sum('rop.quantity');

        return $qty;
    }

    /*
     * 校验可否关联采购订单和销售订单
     * */
    public function checkAssociateOrder($header_id, $buyer_id)
    {
        $lineDetail = $this->orm->table('tb_sys_customer_sales_order_line')
            ->where('header_id', $header_id)
            ->pluck('qty', 'item_code');

        foreach ($lineDetail as $sku=>$qty){
            $stock_qty = $this->getBuyerSumStockForSku($buyer_id,$sku);
            $used_qty = $rma_qty = 0;
            if ($stock_qty){
                $used_qty = $this->getBuyerSumUsedStockForSku($buyer_id,$sku);
                $rma_qty = $this->getBuyerSumRmaForSku($buyer_id,$sku);
            }
            $available_qty = $stock_qty - $used_qty - $rma_qty;
            if ($available_qty < $qty){//可用库存不足
                return false;
            }
        }

        return true;
    }

    /*
     * 获取line详情
     * */
    public function getLineIdListByHeaderId($header_id)
    {
        $lineIdList = $this->orm->table('tb_sys_customer_sales_order_line')
            ->where('header_id', $header_id)
            ->pluck('item_code','id')
            ->toArray();

        return $lineIdList;
    }


    /**
     * 取消new order订单
     * @param int $id
     * @return bool|stdClass
     */
    public function cancelOrderFromNewOrder($id){
        $sql = "UPDATE tb_sys_customer_sales_order cso,tb_sys_customer_sales_order_line csol SET cso.order_status = ".CustomerSalesOrderStatus::CANCELED.",cso.update_time = NOW(),cso.update_user_name = '{$this->customer->getId()}',csol.item_status = ".CustomerSalesOrderLineItemStatus::CANCELED." WHERE cso.id = csol.header_id AND cso.id = " . (int)$id;
        return $this->db->query($sql);
    }

    /**
     * 修改销售订单明细的sku
     *
     * @param $line_id
     * @param $old_sku
     * @param $new_sku
     * @return bool|stdClass
     */
    public function changeSalesOrderLineSku($line_id, $old_sku, $new_sku)
    {
        $sql = "UPDATE tb_sys_customer_sales_order_line SET item_code = '" . $this->db->escape($new_sku) . "' WHERE id = " . (int)$line_id . " AND item_code = '" . $this->db->escape($old_sku) . "'";
        return $this->db->query($sql);
    }

    /**
     * 修改订单的发货信息
     *
     * @param int $header_id
     * @param $data
     * @return bool
     */
    public function changeSalesOrderShippingInformation($header_id, $data)
    {
        $salesOrder = CustomerSalesOrder::find($header_id);
        $isInternational = app(SalesOrderService::class)
            ->checkIsInternationalSalesOrder($salesOrder->buyer->country_id, $data['country']);
        $sql = "update
                  tb_sys_customer_sales_order cso
                set
                  cso.`ship_name` = '" . $this->db->escape($data['name']) . "',
                  cso.`bill_name` = '" . $this->db->escape($data['name']) . "',
                  cso.`email` = '" . $this->db->escape($data['email']) . "',
                  cso.`ship_phone` = '" . $this->db->escape($data['phone']) . "',
                  cso.`ship_address1` = '" . $this->db->escape($data['address']) . "',
                  cso.`bill_address` = '" . $this->db->escape($data['address']) . "',
                  cso.`ship_city` = '" . $this->db->escape($data['city']) . "',
                  cso.`bill_city` = '" . $this->db->escape($data['city']) . "',
                  cso.`ship_state` = '" . $this->db->escape($data['state']) . "',
                  cso.`bill_state` = '" . $this->db->escape($data['state']) . "',
                  cso.`ship_zip_code` = '" . $this->db->escape($data['code']) . "',
                  cso.`bill_zip_code` = '" . $this->db->escape($data['code']) . "',
                  cso.`ship_country` = '" . $this->db->escape($data['country']) . "',
                  cso.`bill_country` = '" . $this->db->escape($data['country']) . "',
                  cso.`customer_comments` = '" . $this->db->escape($data['comments']) . "',
                  cso.`is_international` = '" . ($isInternational ? 1 : 0) . "'
                where cso.`id` = " . (int)$header_id;
        return $this->db->query($sql);
    }

    /**
     * 移除销售订单的库存绑定关系
     * @param int $header_id
     * @return bool|stdClass
     */
    public function removeSalesOrderBind($header_id)
    {
        $deletedAt = date('Y-m-d H:i:s');
        $deletedUser = $this->customer->getFirstName() . $this->customer->getLastName();
        $recordsSql = "INSERT INTO tb_sys_order_associated_deleted_record (`id`,`sales_order_id`,`sales_order_line_id`,`order_id`,`order_product_id`,`qty`,`product_id`,`seller_id`,`buyer_id`,`pre_id`,`image_id`,`Memo`,`CreateUserName`,`CreateTime`,`UpdateUserName`,`UpdateTime`,`ProgramCode`,`created_time`,`created_user_name`) (SELECT `id`,`sales_order_id`,`sales_order_line_id`,`order_id`,`order_product_id`,`qty`,`product_id`,`seller_id`,`buyer_id`,`pre_id`,`image_id`,`Memo`,`CreateUserName`,`CreateTime`,`UpdateUserName`,`UpdateTime`,`ProgramCode`,'{$deletedAt}','{$deletedUser}' FROM tb_sys_order_associated WHERE sales_order_id = {$header_id})";
        $sql = "DELETE FROM tb_sys_order_associated WHERE sales_order_id = " . (int)$header_id;
        return $this->db->query($recordsSql) && $this->db->query($sql);
    }

     //    3150 废弃 改用 $this->>customer->getCustomerExt(1);
     //    /**
     //     * 检验是否是自动购买buyer
     //     * @param int $customer_id
     //     * @return boolean
     //     */
     //    public function checkAutoBuyer($customer_id){
     //        $selfBuyer = $this->orm->table('tb_sys_outer_storeid_to_sellerid')
     //            ->where(['self_buyer_id' =>$customer_id])
     //            ->exists();//自营
     //        if (!$selfBuyer) {
     //            return false;
     //        }else{
     //            //验证是否是dropship
     //            $mapGroupName['name'] = 'US-DropShip-Buyer';
     //            $default_group_id = $this->orm->table(DB_PREFIX.'customer_group_description')
     //                ->where($mapGroupName)
     //                ->limit(1)
     //                ->value('customer_group_id');
     //            if($this->customer->getGroupId() == $default_group_id){
     //                return false;
     //            }
     //            return true;
     //        }
     //    }

    /**
     * 根据销售订单明细查看销售订单的状态
     * @author xxl
     * @param int $orderLineId
     * @return mixed
     */
    public function getOrderStatusByOrderLineId($orderLineId){
        $result = $this->orm->table('tb_sys_customer_sales_order as cso')
            ->leftJoin('tb_sys_customer_sales_order_line as csol','csol.header_id','=','cso.id')
            ->where('csol.id','=',$orderLineId)
            ->select('cso.order_status')
            ->first();
        return $result->order_status;
    }

    /**
     * 检查销售订单是否包含超大件订单
     * @param int $header_id
     * @return bool TRUE:含超大件 false：不含超大件
     */
    public function checkOrderContainsOversizeProduct($header_id){
        $sql = "SELECT COUNT(*) AS cnt FROM tb_sys_customer_sales_order cso
                INNER JOIN tb_sys_customer_sales_order_line csol
                  ON cso.id = csol.header_id
                INNER JOIN oc_product p
                  ON p.`sku` = csol.`item_code`
                INNER JOIN oc_product_to_tag ptt
                  ON ptt.`product_id` = p.`product_id`
                WHERE ptt.`tag_id` = " . $this->config->get('tag_id_oversize') . " AND cso.id = " . (int)$header_id;
        $query = $this->db->query($sql);
        $bool = true;
        $row = $query->row;
        if(isset($row) && $row['cnt'] == 0){
            $bool = false;
        }
        return $bool;
    }


    /*
     * 获取订单是否有取消失败的情况
     * */
    public function hasFailureRecord($headerIdArr)
    {
        $failId = $this->orm->table('tb_sys_customer_order_modify_log')
            ->whereIn('header_id', $headerIdArr)
            //->whereIn('process_code', [1,3])
            ->where('process_code', 3)
            ->pluck('header_id');

        return obj2array($failId);
    }

    /*
     * 获取sales order信息
     * */
    public function getSalesOrderInfo($idArr)
    {
        $info = $this->orm->table('tb_sys_customer_sales_order')
            ->whereIn('id', $idArr)
            ->select('id','order_id','order_date','order_status','order_mode')
            ->get();

        return obj2array($info);
    }

    public function getSalesOrderBuyerInfo($order_key){
        $info = $this->orm->table('tb_sys_customer_sales_order as cso')
            ->join('oc_customer as cus','cus.customer_id' ,'=' , 'cso.buyer_id')
            ->where('cso.id', '=', $order_key)
            ->select('cso.order_id','cus.customer_id','cus.customer_group_id','cus.country_id')
            ->first();
        return obj2array($info);
    }


    /**
     * 销售订单状态说明
     * @param int $customer_sales_order_id 表tb_sys_customer_sales_order主键
     * @return string
     */
    public function getSalesOrderStatusLabel(int $customer_sales_order_id)
    {
        $customer_sales_order_id = intval($customer_sales_order_id);
        $sql   = "select
        c.id
        ,c.memo
        ,ro.id AS rma_key
        ,ro.rma_order_id
        ,coml.before_record
        ,coml.modified_record
        ,coml.remove_bind
    from `tb_sys_customer_sales_order` c
    left join tb_sys_order_associated as a on a.sales_order_id = c.id
    LEFT JOIN oc_order AS oco ON a.order_id = oco.order_id
    LEFT JOIN oc_yzc_rma_order ro ON ro.from_customer_order_id=c.order_id AND ro.order_type=1 AND ro.buyer_id=c.buyer_id
    LEFT JOIN (
        SELECT * FROM tb_sys_customer_order_modify_log WHERE id IN (
            SELECT MAX(id)
            FROM tb_sys_customer_order_modify_log
            WHERE header_id={$customer_sales_order_id}
            GROUP BY header_id
        )
    ) AS coml ON coml.header_id=c.id
        AND coml.order_id=c.order_id
        AND coml.order_type=1
        AND coml.`status`=2
    where c.id ={$customer_sales_order_id}
    group by c.id
    ORDER BY c.order_id ASC ";
        $query = $this->db->query($sql);

        if ($query->row) {
            return $this->formatSalesOrderStatusLabel($query->row);
        }
        return '';
    }


    /**
     * @param $item  ['before_record'=>'', 'remove_bind'=>'', 'rma_key', 'rma_order_id']
     * @return string
     */
    public function formatSalesOrderStatusLabel($item){
        $order_status_label = '';


        $memo = $item['memo'];
        $before_record      = $item['before_record'];
        if($before_record){
            if (stripos($before_record, 'New Order') !== false || stripos($before_record, 'To Be Paid') !== false) {
                $order_status_label = '';
            } elseif (stripos($before_record, CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::BEING_PROCESSED)) !== false
                || stripos($before_record, CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::ON_HOLD)) !== false
                || stripos($before_record, CustomerSalesOrderStatus::getDescription(CustomerSalesOrderStatus::PENDING_CHARGES)) !== false
            ) {
                if ($item['remove_bind'] == 0) {
                    //选择了Apply for RMA
                    if( !$item['rma_key'] ){
                        //还没有申请RMA
                        $order_status_label='<div style="color:#FA6400">Not applied RMA</div>';
                    } else {
                        //已经申请了RMA
                        $href = $this->url->link('account/rma_order_detail', 'rma_id=' . $item['rma_key'], true);
                        $order_status_label='<div style="color:#666666">RMA: <a target="_blank" href="'.$href.'" style="color:#189BD5">'.$item['rma_order_id'].'</a></div>';
                    }
                } elseif ($item['remove_bind'] == 1) {
                    //选择了Keep in stock
                    $order_status_label = '<div>Keep in stock</div>';
                }
            }
        } else {
            //B2B管理系统.connection_relation保留销采订单的关联关系 == Buyer Apply for RMA
            //B2B管理系统.connection_relation解除销采订单的关联关系 == Buyer Keep IN Stock
            $cancelLog = $this->customerOrderCancelLogOne(intval($item['id']));
            if (stripos($memo, '1->16') !== false) {
                $order_status_label = '';
            } elseif (stripos($memo, '2->16') !== false || stripos($memo, '4->16') !== false) {
                if ($cancelLog) {
                    if ( $cancelLog['connection_relation'] == 1) {
                        //Apply for RMA
                        if( !$item['rma_key'] ){
                            //还没有申请RMA
                            $order_status_label='<div style="color:#FA6400">Not applied RMA</div>';
                        } else {
                            //已经申请了RMA
                            $href = $this->url->link('account/rma_order_detail', 'rma_id=' . $item['rma_key'], true);
                            $order_status_label='<div style="color:#666666">RMA: <a target="_blank" href="'.$href.'" style="color:#189BD5">'.$item['rma_order_id'].'</a></div>';
                        }
                    } elseif ($cancelLog['connection_relation'] == 0) {
                        //解除--Keep in stock
                        $order_status_label = '<div>Keep in stock</div>';
                    }
                }
            }
        }
        return $order_status_label;
    }


    /**
     * B2B管理系统 对 销售单的操作日志，
     * 记录在表tb_sys_customer_sales_order的meno字段和 表tb_sys_customer_sales_order_cancel
     * @param int $customer_order_id 表tb_sys_customer_sales_order主键
     * @return array
     * @date N-1104
     */
    public function customerOrderCancelLogOne($customer_order_id)
    {
        $sql = "SELECT * FROM tb_sys_customer_sales_order_cancel WHERE header_id ={$customer_order_id} ORDER BY id DESC LIMIT 1";
        return $this->db->query($sql)->row;
    }

    //销售订单绑定的采购订单是否使用了虚拟支付
    public function hasVirtualPayment($salesOrderId)
    {
        return $this->orm->table('tb_sys_order_associated as ass')
            ->leftJoin('oc_order as o', 'ass.order_id', '=', 'o.order_id')
            ->where([
                'ass.sales_order_id'    => $salesOrderId,
                'o.payment_code'        => PayCode::PAY_VIRTUAL
            ])
            ->exists();
    }


    /**
     * 根据 customer_sales_order 的id 查询 sku 与 qty ，如果是combo 将对应的子sku查询出
     * @param int $id
     * @param int $customer_id
     * @return array
     */
    public function getSkuAndQtyBySalesOrderId(int $id,int $customer_id):array
    {
        $sku = [];
        $builder = $this->orm->table('tb_sys_customer_sales_order as o')
                    ->leftJoin('tb_sys_customer_sales_order_line as ol','o.id','=','ol.header_id')
                    ->leftJoin('oc_product as p','p.sku','=','ol.item_code')
                    ->select([
                        'o.order_id',
                        'p.product_id',
                        'p.combo_flag',
                        'ol.qty',
                        'ol.item_code',
                        'ol.id'
                    ])
                    ->where([
                        [ 'o.id','=',$id],
                        [ 'o.buyer_id','=',$customer_id],
                    ])
                    ->groupBy('ol.id')
                    ->get()
                    ->map(function ($v){
                        return (array)$v;
                    })
                    ->toArray();
        foreach($builder as $key => $value){
            if($value['combo_flag']){
                $value['product_id'] = $this->sales_model->getFirstProductId($value['item_code'],$customer_id);
                $builder_combo=$this->orm->table('tb_sys_product_set_info as psi')
                    ->leftJoin('oc_product as p','psi.set_product_id','=','p.product_id')
                    ->select(['p.sku','psi.qty'])
                    ->where('psi.product_id','=',$value['product_id'])
                    ->get();
                foreach( $builder_combo as $k=> $v){
                    $sku[$v->sku] = intval($v->qty*$value['qty']);
                }
            }else{
                $sku[$value['item_code']] = $value['qty'];
            }
        }

        return [
            'sku'=> $sku,
            'order_id'=> $builder[0]['order_id'],
        ];
    }




    /**
     *是否存在中源已下载的订单[已弃用]
     * @param array $id_arr
     * @return bool $zy_upload_order
     */
    public function salesOrderIsZyUpload(array $id_arr):bool
    {
        $zy_upload_order=$this->orm->table('tb_sys_customer_sales_order')
            ->select(['order_id'])
            ->whereIN('id', $id_arr)
            ->where('zy_download_flag','=','1')
            ->exists();
        return $zy_upload_order;
    }


    /**
     * 根据id获取tag信息
     *
     */
    public function getTagInfoByTagId($tag_id)
    {
        return $this->orm->table('oc_tag')
            ->where('tag_id',$tag_id)
            ->first();
    }

}
