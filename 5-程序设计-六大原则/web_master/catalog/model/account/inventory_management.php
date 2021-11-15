<?php

use App\Enums\SalesOrder\CustomerSalesOrderLineItemStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\Warehouse\ReceiptOrderStatus;

/**
 * Class ModelAccountInventoryManagement
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelToolImage $model_tool_image
 */
class ModelAccountInventoryManagement extends Model
{
    public $DEPOSIT_SELLERS;

    public function __construct($registry)
    {
        parent::__construct($registry);
        //包销店铺
        if ($this->config->get('customer_config_deposit_seller') != null) {
            $this->DEPOSIT_SELLERS = $this->config->get('customer_config_deposit_seller');
        } else {
            $this->DEPOSIT_SELLERS = '694,696,746,908,907';
        }
        //service店铺   （现在不区分service店铺和其他店铺了）
//       $this->ADMIN_SELLERS = '340';
    }

    public function getReceiptsOrderById($id)
    {
        $sql = "SELECT * FROM `tb_sys_receipts_order` sro WHERE sro.`receive_order_id` = " . $id;
        return $this->db->query($sql)->row;
    }

    public function getReceiptsOrderDetailByHeaderId($id)
    {
        $sql = "SELECT * FROM `tb_sys_receipts_order_detail` sro WHERE sro.`receive_order_id` = " . $id;
        return $this->db->query($sql)->rows;
    }

    public function getBuyerProductCostCount($filter_data)
    {
        $sql = "SELECT
                  COUNT(*) AS total
                FROM
                  (SELECT
                    p.`sku`,
                    SUM(cd.`onhand_qty`) AS stock_quantity
                  FROM
                    `tb_sys_cost_detail` cd
                     LEFT JOIN oc_product p
                    ON cd.sku_id = p.`product_id`
                  WHERE cd.`buyer_id` = " . $filter_data['customer_id'] . "
                  GROUP BY p.`sku`";
        if (isset($filter_data['stockNumberFlag'])) {
            if ($filter_data['stockNumberFlag'] == 0) {
                $sql .= "HAVING stock_quantity > 0";
            }
        }
        $sql .= ") t
                WHERE 1 = 1";
        if (isset($filter_data['sku'])) {
            $sql .= " AND t.`sku` LIKE '%" . $this->db->escape($filter_data['sku']) . "%'";
        }
        return $this->db->query($sql)->row['total'];
    }

    public function getBuyerProductCost($filter_data)
    {
        $sql = "select * from (select sum(scd.onhand_qty) as onhandQty,sum(scd.original_qty) as originalQty,op.sku,op.image,op.product_id
                from tb_sys_cost_detail as scd
                LEFT JOIN oc_product as op ON op.product_id=scd.sku_id
                where scd.`buyer_id` = " . $filter_data['customer_id'];
        if (isset($filter_data['sku'])) {
            $sql .= " AND op.`sku` LIKE '%" . $this->db->escape($filter_data['sku']) . "%'";
        }
        if (isset($filter_data['mpn'])) {
            $sql .= " AND op.`mpn` LIKE '%" . $this->db->escape($filter_data['mpn']) . "%'";
        }
        $sql .= " group by op.sku) temp where 1=1 ";
        if(isset($filter_data['stockNumberFlag'])){
            if ($filter_data['stockNumberFlag'] == 0) {
                $sql .= " and temp.onhandQty>0";
            }
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

    public function getSoldQuantityByProductId($product_id, $customer_id)
    {
        $sql = "SELECT SUM(cto.`quantity`) AS quantity FROM `oc_customerpartner_to_order` cto WHERE cto.`customer_id` = " . $customer_id . " AND cto.`product_id` = " . $product_id;
        return $this->db->query($sql)->row['quantity'];
    }

    /**
     * 获取已售未发的商品数量信息
     * @param int $product_id
     * @param int $customer_id
     * @return
     */
    public function getSoldOutCount($item_code, $customer_id)
    {
        $sql = "SELECT sum(temp.qty) as qty FROM(SELECT
                    cso.`id` as header_id,
                    csol.`id` as line_id,
                    'sales order' as type,
                    cso.`order_status`,
                    csol.`item_status`,
                    SUM(csol.`qty`) AS qty
                FROM
                    `tb_sys_customer_sales_order` cso
                LEFT JOIN `tb_sys_customer_sales_order_line` csol ON cso.`id` = csol.`header_id`
                WHERE
                    cso.`buyer_id` = ".$customer_id."
                AND cso.`order_status` = ".CustomerSalesOrderStatus::BEING_PROCESSED."
                AND csol.`item_status` IN (".CustomerSalesOrderLineItemStatus::PENDING.", ".CustomerSalesOrderLineItemStatus::SHIPPING.")
                AND csol.`item_code` = '".$item_code."'
                GROUP BY
                    csol.`item_code`
                UNION
                (SELECT
                    csr.`id` as header_id,
                    csrl.`id` as line_id,
                    'reshipment order' as type,
                    csr.`order_status`,
                    csrl.`item_status`,
                    SUM(csrl.`qty`) AS qty
                FROM
                    `tb_sys_customer_sales_reorder` csr
                LEFT JOIN `tb_sys_customer_sales_reorder_line` csrl ON csr.`id` = csrl.reorder_header_id
                WHERE
                    csr.`buyer_id` = ".$customer_id."
                AND csr.`order_status` = ".CustomerSalesOrderStatus::BEING_PROCESSED."
                AND csrl.`item_status` IN (1, 2)
                AND csrl.`item_code` = '".$item_code."'
                GROUP BY
                    csrl.`item_code`)) temp";
        return $this->db->query($sql)->row;
    }

    /**
     * 获取已发未减的商品信息数据
     * @param int $product_id
     * @param int $customer_id
     * @return mixed
     */
    public function getNotReducedCount($product_id, $customer_id)
    {
        $sql = "SELECT cso.`id`,csol.`id`,cso.`order_status`,csol.`item_status`,SUM(csol.`qty`)) AS qty ,csol.`product_id` FROM `tb_sys_customer_sales_order` cso LEFT JOIN `tb_sys_customer_sales_order_line` csol ON cso.`id` = csol.`header_id` WHERE cso.`buyer_id` = " . $customer_id . " AND cso.`order_status` = 2 AND csol.`item_status` = 2 AND csol.`product_id` = " . $product_id . " GROUP BY csol.`product_id`";
        return $this->db->query($sql)->row;
    }

    public function getProductById($product_id, $customer_id)
    {
        return $this->db->query('SELECT p.* FROM  oc_product p LEFT JOIN oc_customerpartner_to_product c2p ON c2p.product_id=p.product_id
  WHERE p.product_id='.$product_id.' AND c2p.customer_id='.$customer_id)->row;
    }
    public function getProductsById($product_id, $customer_id)
    {
        $sql = "SELECT * FROM `oc_customerpartner_to_order` cto LEFT JOIN `oc_product` p ON cto.`product_id` = p.`product_id` LEFT JOIN `oc_product_description` pd ON p.`product_id` = pd.`product_id` WHERE cto.`product_id` = " . $product_id . " AND cto.`customer_id` = " . $customer_id;
        return $this->db->query($sql)->row;
    }

    public function getProductInfo($product_id) {
        $sql = "SELECT * FROM `oc_product` p LEFT JOIN `oc_product_description` pd ON p.`product_id` = pd.`product_id` WHERE p.`product_id` = " . $product_id;
        return $this->db->query($sql)->row;
    }

    public function getStorageRecord($filter_data)
    {
        $sql = "SELECT
                  *
                FROM
                  (SELECT
                    cd.`id`,
                    cd.`create_time`,
                    cd.`seller_id`,
                    ctc.`screenname`,
                    '0' AS 'type',
                    p.`price`,
                    cd.`original_qty` AS qty,
                    cd.`memo`
                  FROM
                    `tb_sys_cost_detail` cd
                    LEFT JOIN `oc_product` p
                      ON cd.`sku_id` = p.`product_id`
                    LEFT JOIN `oc_customerpartner_to_customer` ctc
                      ON cd.`seller_id` = ctc.`customer_id`
                  WHERE cd.`buyer_id` = " . $filter_data['customer_id'] . "
                    AND cd.`sku_id` = " . $filter_data['product_id'] . "
                  UNION
                  ALL
                  SELECT
                    sdl.`Id`,
                    sdl.`create_time`,
                    cd.`seller_id`,
                    ctc.`screenname`,
                    '1'  AS 'type',
                    p.`price`,
                    sdl.`DeliveryQty` AS qty,
                    sdl.`Memo`
                  FROM
                    `tb_sys_delivery_line` sdl
                    LEFT JOIN `tb_sys_cost_detail` cd
                      ON sdl.`CostId` = cd.`id`
                    LEFT JOIN `oc_product` p
                      ON cd.`sku_id` = p.`product_id`
                    LEFT JOIN `oc_customerpartner_to_customer` ctc
                      ON cd.`seller_id` = ctc.`customer_id`
                  WHERE cd.`buyer_id` = " . $filter_data['customer_id'] . "
                    AND sdl.`ProductId` =  " . $filter_data['product_id'] . ") t
                WHERE 1 = 1";
        if (isset($filter_data['type'])) {
            $sql .= " AND t.`type` = '" . $this->db->escape($filter_data['type']) . "'";
        }
        if (isset($filter_data['createTimeStart'])) {
            $sql .= " AND t.`create_time` >= '" . $this->db->escape($filter_data['createTimeStart']) . "'";
        }
        if (isset($filter_data['createTimeEnd'])) {
            $sql .= " AND t.`create_time` <= '" . $this->db->escape($filter_data['createTimeEnd']) . "'";
        }
        return $this->db->query($sql)->rows;
    }

    public function getProducts($filter_data)
    {
        $this->load->model('catalog/product');
        $this->load->model('tool/image');

        $this->db->escapeParams($filter_data);
        $sql = "SELECT p.product_id
FROM oc_customerpartner_to_product ctp
LEFT JOIN oc_product p ON ctp.product_id = p.product_id
LEFT JOIN oc_product_description pd ON p.product_id = pd.product_id
WHERE ctp.customer_id = " . $filter_data['customer_id'];
        if (isset($filter_data['mpn']) && $filter_data['mpn']!='') {
            $sql .= " AND (p.mpn LIKE '%" . $filter_data['mpn'] . "%'
 or p.sku like '%{$filter_data['mpn']}%')";
        }
        $product_ids = $this->db->query($sql)->rows;
        $product_ids = array_column($product_ids, 'product_id');
        //产品库存
        $product_stock = [];
        foreach ($product_ids as $pd_index => $product_id) {
            $stock_query = $this->model_catalog_product->queryStockByProductId($product_id);
            //Contains 0 pro...复选框 (勾选 1,不勾选 0,默认勾选)不勾选时过滤无库存的产品
            if (isset($filter_data['stockNumberFlag']) && $filter_data['stockNumberFlag'] == "0") {
                if ($stock_query['total_onhand_qty'] <= 0) {
                    unset($product_ids[$pd_index]);
                    continue;
                }
            }
            $product_stock[$product_id]['total_onhand_qty'] = $stock_query['total_onhand_qty'];
            $product_stock[$product_id]['total_original_qty'] = $stock_query['total_original_qty'];
        }

        if (empty($product_ids)) {
            $products = [];
            $countNum = 0;
            return [$products, $countNum];
        }

        $countNum = count($product_ids);
        $sql = "SELECT p.product_id,p.image,p.sku,p.mpn,pd.name
FROM oc_customerpartner_to_product ctp
LEFT JOIN oc_product p ON ctp.product_id = p.product_id
LEFT JOIN oc_product_description pd ON p.product_id = pd.product_id
where p.product_id in (" . implode(',', $product_ids) . ") ";
        if (isset($filter_data['start']) && isset($filter_data['limit'])) {
            $sql .= " LIMIT " . (int)$filter_data['start'] . "," . (int)$filter_data['limit'];
        }
        $products = $this->db->query($sql)->rows;
        // 获取预计下次到岗日期以及数量
        $expectedProductInfos = $this->getExpectedProductInfo($product_ids);
        $expectedProductMap = array();
        foreach ($expectedProductInfos as $expectedProduct) {
            $expectedProductMap[$expectedProduct['product_id']] = $expectedProduct;
        }

        $loopIndex = $filter_data['start'] + 1;
        foreach ($products as $pd_idx => $product) {
            //序号
            $products[$pd_idx]['loopIndex'] = $loopIndex++;
            $product_id = $product['product_id'];
            if (array_key_exists($product_id, $product_stock)) {
                $products[$pd_idx]['total_onhand_qty'] = $product_stock[$product_id]['total_onhand_qty'];
                $products[$pd_idx]['total_original_qty'] = $product_stock[$product_id]['total_original_qty'];
                $total_out_qty = $product_stock[$product_id]['total_original_qty'] - $product_stock[$product_id]['total_onhand_qty'];
                $products[$pd_idx]['total_out_qty'] = $total_out_qty < 0 ? 0 : $total_out_qty;
            }
            $tag_array = $this->model_catalog_product->getProductSpecificTag($product_id);
            $tags = array();
            $tag_str = '';
            if (isset($tag_array)) {
                foreach ($tag_array as $tag) {
                    if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                        //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                        $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                        $tags[] = '<img data-toggle="tooltip"  class="' . $tag['class_style'] . '" title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                        $tag_str .= '(' . $tag['description'] . ')';
                    }
                }
            }
            $products[$pd_idx]['tag'] = $tags;
            $products[$pd_idx]['tag_str'] = $tag_str;

            //预计下次到岗日期以及数量
            if (isset($expectedProductMap[$product_id])) {
                $products[$pd_idx]['expected_date'] = date("Y-m-d", strtotime($expectedProductMap[$product_id]['expected_date']));
                $products[$pd_idx]['expected_qty'] = $expectedProductMap[$product_id]['expected_qty'];
            }
        }
        return [$products, $countNum];
    }

    public function getBatchStockInfo($filter_data, $customer_id, $product_id)
    {
        $sql = $this->createBatchStockSql($filter_data, $customer_id, $product_id);
        if (isset($filter_data['start']) && isset($filter_data['limit'])) {
            $sql .= " LIMIT " . (int)$filter_data['start'] . "," . (int)$filter_data['limit'];
        }
        $batchStocks = $this->db->query($sql)->rows;
        $loopIndex = $filter_data['start'] + 1;
        foreach ($batchStocks as $i => $batchStock) {
            //序号
            $batchStocks[$i]['loopIndex'] = $loopIndex++;
            if($batchStocks[$i]['stock_in_type'] == 'RECEIVING_ORDER'){
                //RECEIVING_ORDER
                $batchStocks[$i]['order_id_href'] = $this->url->link('account/inbound_management&filter_inboundOrderNumber=' . $batchStock['receive_number'], '', true);
            }elseif ($batchStocks[$i]['stock_in_type'] == 'RMA') {
                //RMA
                $batchStocks[$i]['order_id_href'] = $this->url->link('account/customerpartner/rma_management/rmaInfo&rmaId=' . $batchStock['rma_id'], '', true);
            }

            $batchStocks[$i]['receive_date'] = date('Y-m-d', strtotime($batchStock['receive_date']));
            $batchStocks[$i]['apply_date'] = date('Y-m-d', strtotime($batchStock['apply_date']));
        }
        return $batchStocks;

    }

    public function createBatchStockSql($filter_data = [], $customer_id, $product_id)
    {

        $sql = "SELECT * from (";
        $sql .= "SELECT
     CASE WHEN b.source_code IN ('退库收货','退货入库','退货收货','退返品退货收货')  OR b.rma_id IS NOT NULL
                    THEN 'RMA'
               WHEN c2p.customer_id IN (".$this->DEPOSIT_SELLERS.")
                    THEN 'DEPOSIT'
               WHEN b.source_code='B2B_TRANSFER'
                    THEN 'INCREASE_INVENTORY'
               ELSE 'RECEIVING_ORDER'
      END  AS stock_in_type,
      CASE WHEN b.source_code IN ('退库收货','退货入库','退货收货','退返品退货收货')  OR b.rma_id IS NOT NULL
                    THEN rma.rma_order_id
             WHEN c2p.customer_id IN (".$this->DEPOSIT_SELLERS.")
                        THEN null
            WHEN b.source_code='B2B_TRANSFER'
                        THEN null
            ELSE ro.receive_number
      END  AS order_id_name,
b.*,rma.rma_order_id,
DATEDIFF(NOW(),b.receive_date) AS in_stock_days,
ro.receive_number, ro.container_code, ro.apply_date
FROM tb_sys_batch b
LEFT JOIN tb_sys_receipts_order ro  ON b.receipts_order_id = ro.receive_order_id
LEFT JOIN  oc_customerpartner_to_product c2p  ON c2p.product_id=b.product_id
left join oc_yzc_rma_order rma on rma.id=b.rma_id
WHERE b.customer_id = " . $customer_id .
            " AND b.product_id = " . $product_id;

        if (isset($filter_data['containerNumber']) && $filter_data['containerNumber']!='') {
            $sql .= " AND ro.container_code LIKE '%" . $filter_data['containerNumber'] . "%'";
        }
        if (isset($filter_data['receiptDateStart']) && $filter_data['receiptDateStart']!='') {
            $sql .= " AND b.receive_date >= '" . $filter_data['receiptDateStart'] . " 00:00:00'";
        }
        if (isset($filter_data['receiptDateEnd']) && $filter_data['receiptDateEnd']!='') {
            $sql .= " AND b.receive_date <= '" . $filter_data['receiptDateEnd'] . " 23:59:59'";
        }
//        if (!is_null($filter_data['inStockDaysStart']) || !is_null($filter_data['inStockDaysEnd'])) {
//            $sql .= " HAVING ";
//            if (!is_null($filter_data['inStockDaysStart'])) {
//                $sql .= " in_stock_days >= " . (int)$filter_data['inStockDaysStart'];
//            }
//            if (!is_null($filter_data['inStockDaysEnd'])) {
//                if (!is_null($filter_data['inStockDaysStart'])) {
//                    $sql .= " AND in_stock_days <= " . (int)$filter_data['inStockDaysEnd'];
//                } else {
//                    $sql .= " in_stock_days <= " . (int)$filter_data['inStockDaysEnd'];
//                }
//            }
//        }
        $sql .= ') a where 1';
        //入库类型
        if (isset($filter_data['stockInType']) && $filter_data['stockInType']!='') {
            $sql .= " and a.stock_in_type = '" . $filter_data['stockInType'] . "'";
        }
        //order_id
        if (isset($filter_data['receivingOrderNumber']) && $filter_data['receivingOrderNumber']!='') {
            $sql .= " AND a.order_id_name  LIKE '%" . $filter_data['receivingOrderNumber'] . "%'";
        }
        return $sql;
    }

    public function getBatchStockInfoCount($filter_data, $customer_id, $product_id)
    {
        $sql = " SELECT COUNT(*) AS total FROM ( ";
        $sql .= $this->createBatchStockSql($filter_data, $customer_id, $product_id);
        $sql .= ") t";
        return $this->db->query($sql)->row['total'];
    }

    public function getExpectedProductInfo($product_ids = array()) {
        if (!empty($product_ids)) {
            $sql = "SELECT DISTINCT
                      (rod2.`product_id`),
                      rod2.`expected_qty`,
                      ro2.`expected_date`
                    FROM
                      `tb_sys_receipts_order` ro2,
                      `tb_sys_receipts_order_detail` rod2,
                      (SELECT
                        rod.`product_id`,
                        MIN(ro.`expected_date`) AS expected_date
                      FROM
                        `tb_sys_receipts_order` ro
                        LEFT JOIN `tb_sys_receipts_order_detail` rod
                          ON ro.`receive_order_id` = rod.`receive_order_id`
                      WHERE ro.`status` = " . ReceiptOrderStatus::TO_BE_RECEIVED . " AND ro.`expected_date` > NOW()
                      GROUP BY rod.`product_id`) t
                    WHERE rod2.`product_id` = t.product_id
                      AND ro2.`expected_date` = t.expected_date
                      AND ro2.`receive_order_id` = rod2.`receive_order_id` AND t.product_id IN (".implode(',',$product_ids). ")  ORDER BY rod2.`product_id`";
            return $this->db->query($sql)->rows;
        } else {
            return [];
        }
    }

    public function getSellerDeliveryLine($batch_id, $filter_data)
    {
        $this->db->escapeParams($filter_data);
        $sql = $this->createSellerDeliveryLineSql($batch_id, $filter_data);
        if (isset($filter_data['start']) && isset($filter_data['limit'])) {
            $sql .= " LIMIT " . (int)$filter_data['start'] . "," . (int)$filter_data['limit'];
        }
        $out_stocks = $this->db->query($sql)->rows;
        $loopIndex = $filter_data['start'] + 1;
        if (!empty($out_stocks)) {
            foreach ($out_stocks as $index => $out_stock) {
                //序号
                $out_stocks[$index]['loopIndex'] = $loopIndex++;
                if ($out_stocks[$index]['stock_out_type'] == 'PURCHASE_ORDER') {
                    //采购订单

                } elseif ($out_stocks[$index]['stock_out_type'] == 'RMA') {
                    //RMA
                    $out_stocks[$index]['order_id_href'] = $this->url->link('account/customerpartner/rma_management/rmaInfo&rmaId=' . $out_stock['rma_id'], '', true);
                }

                //采购订单价格
                if ($out_stock['stock_out_type'] == 'PURCHASE_ORDER') {
                    $order_product_sql = 'select op.*,quo.price as quotePrice
from oc_order_product op  left join oc_product_quote quo
        on quo.order_id=op.order_id and quo.product_id=op.product_id
where op.order_id=' . $out_stock['order_id'] ;
                    //对于combo品  delivery_line表存的是子product_id  order_product存的是父product_id
                    $set_info = $this->db->query('select ps.product_id from tb_sys_product_set_info ps where ps.set_product_id= '.$out_stock['product_id'])->rows;
                    if(empty($set_info)){
                        //非combo品
                        $order_product_sql .= ' and op.product_id = ' . $out_stock['product_id'];
                    }else{
                        //combo品
                        $order_product_sql .= ' and op.product_id in (' . implode(',',array_column($set_info,'product_id')) . ') ';
                    }
                    $order_product = $this->db->query($order_product_sql)->row;
                    if (!empty($order_product)) {
                        if (isset($order_product['quotePrice'])) {
                            //议价 单价
                            $price = round((double)$order_product['quotePrice'], 2);
                        } else {
                            //order_product单价
                            $price = $order_product['price'];
                        }
                        //(服务费+手续费)/数量
                        $otherPrice = round(($order_product['service_fee'] + $order_product['poundage']) / $order_product['quantity'], 2);
                        $price = round($price + $otherPrice, 2);
                        $out_stocks[$index]['price'] = $price;
                    }

                    if(isset($out_stocks[$index]['price']) && !$out_stocks[$index]['price']){
                        //查询order_id
                        $tmp = $this->orm->table('tb_sys_customer_sales_order as o')
                            ->leftJoin('tb_sys_customer_sales_order_line as l','l.header_id','=','o.id')
                            ->where([
                                'o.id'=>$out_stock['order_id'],
                                'l.item_code'=>$out_stock['sku'],
                            ])
                            ->select('o.order_id','l.item_price')
                            ->first();
                        $out_stocks[$index]['price'] = sprintf('%.2f',$tmp->item_price);
                        $out_stocks[$index]['order_id_name'] = $tmp->order_id;
                        $out_stocks[$index]['order_id_href'] = $this->url->link('account/customerpartner/sales_order_list/salesOrderDetails&id=' . $out_stock['order_id'], '', true);
                    }else{
                        $out_stocks[$index]['order_id_href'] = $this->url->link('account/customerpartner/orderinfo&order_id=' . $out_stock['order_id'], '', true);
                    }
                }
            }
        }
        return $out_stocks;
    }

    public function getSellerDeliveryLineCount($batch_id, $filter_data)
    {
        $this->db->escapeParams($filter_data);
        $sql = 'select count(*) as total from (';
        $sql .= $this->createSellerDeliveryLineSql($batch_id, $filter_data);
        $sql .= ') _t';
        return $this->db->query($sql)->row['total'];
    }

    /**
     * @param $batch_id
     * @param $filter_data
     * @return array
     */
    public function createSellerDeliveryLineSql($batch_id, $filter_data)
    {
        $sql = "select * from (";
        $sql .= "select
 CASE  WHEN dl.type=2
            THEN 'RMA'
        WHEN dl.type=3 and dl.order_id is null
            THEN 'REDUCE_INVENTORY'
        WHEN dl.type=1 and c2p.customer_id IN (".$this->DEPOSIT_SELLERS.")
            THEN 'DEPOSIT'
        ELSE 'PURCHASE_ORDER'
END AS stock_out_type,
CASE   WHEN dl.type=2
            THEN rma.rma_order_id
       WHEN dl.type=3 and dl.order_id is null
            THEN null
       WHEN dl.type=1 and c2p.customer_id IN (".$this->DEPOSIT_SELLERS.")
            THEN null
       ELSE dl.order_id
END AS order_id_name,
dl.id as delivery_id,dl.product_id,rma.rma_order_id,rma.id as rma_id ,
dl.batch_id,dl.order_id,dl.qty,p.sku,p.mpn,pd.name,c.delivery_type,
CONCAT(oc.firstname,' ',oc.lastname) as buyerName,
CONCAT(oc.nickname,'(',oc.user_number,')') as nickname,
dl.CreateTime
from tb_sys_seller_delivery_line dl
LEFT JOIN oc_product p on p.product_id =dl.product_id
LEFT JOIN oc_product_description pd on pd.product_id = p.product_id
LEFT JOIN oc_order c on dl.order_id =c.order_id
LEFT JOIN oc_customer oc on oc.customer_id = dl.buyer_id
LEFT JOIN  oc_customerpartner_to_product c2p  ON c2p.product_id=dl.product_id
left join oc_yzc_rma_order rma on rma.id=dl.rma_id
where dl.batch_id = '$batch_id'
 and dl.qty>0 ";
        if (isset($filter_data['orderDateStart']) && $filter_data['orderDateStart']!='') {
            $sql .= " AND DATE_FORMAT(dl.`CreateTime`,'%Y-%m-%d') >= '" . $filter_data['orderDateStart'] . "'";
        }
        if (isset($filter_data['orderDateEnd']) && $filter_data['orderDateEnd']!='') {
            $sql .= " AND DATE_FORMAT(dl.`CreateTime`,'%Y-%m-%d') <= '" . $filter_data['orderDateEnd'] . "'";
        }

        $sql .= ") _t where 1 ";
        if (isset($filter_data['nickname']) && $filter_data['nickname']!='') {
            $sql .= " AND _t.nickname like '%" . $filter_data['nickname'] . "%'";
        }
        if (isset($filter_data['stockOutType']) && $filter_data['stockOutType']!='') {
            $sql .= " AND _t.stock_out_type ='" . $filter_data['stockOutType'] . "'";
        }
        if (isset($filter_data['order_id']) && $filter_data['order_id']!='') {
            $sql .= " AND _t.order_id_name LIKE '%" . $filter_data['order_id'] . "%'";
        }
        return $sql;
    }

    /**
     * 累计出库数量
     * @author xxl
     * @param int $product_id
     * @param int $customer_id
     * @return \Illuminate\Support\Collection
     */
    public function getOutStockQty($item_code,$customer_id){
        $result = $this->orm->table('tb_sys_delivery_line as sdl')
            ->leftJoin('tb_sys_cost_detail as scd','scd.id','=','sdl.CostId')
            ->leftJoin('oc_product as op','op.product_id','=','scd.sku_id')
            ->whereRaw("scd.buyer_id=".$customer_id." and op.sku='".$item_code."'")
            ->selectRaw("ifnull(sum(DeliveryQty),0) as outStockQty")
            ->first();
        return obj2array($result);
    }

    /**
     * 获取可用库存数
     * @author xxl
     * @param $item_code
     * @param int $customer_id
     * @return mixed
     */
    public function  getProductCostBySku($item_code,$customer_id){
        $sql = "SELECT
                op.`sku`,
                sum(ifnull(scd.original_qty,0)) as orginalQty,
                sum(ifnull(t.qty,0)) as qty,
                sum(
                    ifnull((
                        SELECT
                            sum(qty)
                        FROM
                            tb_sys_order_associated
                        WHERE
                            order_product_id = ocp.order_product_id
                            AND buyer_id=scd.buyer_id
                    ),0)
                ) AS associatedQty
            FROM
                `tb_sys_cost_detail` scd
            LEFT JOIN tb_sys_receive_line srl ON srl.id = scd.source_line_id
            LEFT JOIN oc_product op ON op.product_id = scd.sku_id
            LEFT JOIN oc_order_product ocp ON (
                ocp.order_id = srl.oc_order_id
                AND scd.sku_id = ocp.product_id
            )
            LEFT JOIN (
                SELECT
                    ro.order_id,
                    rop.product_id,
                    sum(rop.quantity) AS qty
                FROM
                    oc_yzc_rma_order ro
                LEFT JOIN oc_yzc_rma_order_product rop ON rop.rma_id = ro.id
                	WHERE
                        ro.buyer_id = ".$customer_id."
                    AND ro.cancel_rma = 0
                    AND status_refund <> 2
                    AND ro.order_type = 2
                    GROUP BY
                        rop.product_id,
                        ro.order_id
                ) t ON t.product_id = scd.sku_id
                AND srl.oc_order_id = t.order_id";
        $sql .=" WHERE scd.type = 1 and scd.`buyer_id` = ".$customer_id." AND scd.`onhand_qty` > 0 AND op.sku='".$item_code."' GROUP BY op.sku ";
        $costResult = $this->db->query($sql)->row;
        return $costResult;
    }

    public function getSoldOutOrder($item_code,$customer_id){
        $europeFreightProduct = str_replace('[','(',$this->config->get("europe_freight_product_id"));
        $europeFreightProduct = str_replace(']',')',$europeFreightProduct);
        $sql = "SELECT soa.seller_id,cso.id,cso.`order_id`,sum(case when soa.seller_id not in".SERVICE_STORE." then soa.qty else 0 end) as qty, case when ocl.id is not null then 'cwf_order' else 'sales_order' end as order_type,ocl.id as ocl_id,
                CASE when max(oo.date_modified)>cso.create_time then max(oo.date_modified) else cso.create_time end as checkoutTime
                FROM `tb_sys_customer_sales_order` cso LEFT JOIN `tb_sys_customer_sales_order_line` csol ON cso.`id` = csol.`header_id`
                LEFT JOIN tb_sys_order_associated soa ON soa.sales_order_line_id=csol.id AND soa.buyer_id=cso.buyer_id
                LEFT JOIN oc_order oo on oo.order_id=soa.order_id
                LEFT JOIN oc_order_cloud_logistics ocl on ocl.sales_order_id = cso.id
                WHERE  cso.`buyer_id` = " . $customer_id . " AND cso.`order_status` = 2  AND csol.`item_code` = '" . $item_code . "' and soa.product_id not in".$europeFreightProduct."  group by csol.id";
        $sql .= " union(
                SELECT ro.seller_id,ro.id,csr.`reorder_id` as order_id,csrl.qty,'reshipment_order' as order_type, 'null' as ocl_id,
                csr.create_time as checkoutTime
                FROM `tb_sys_customer_sales_reorder` csr LEFT JOIN `tb_sys_customer_sales_reorder_line` csrl ON csr.`id` = csrl.`reorder_header_id`
                LEFT JOIN oc_yzc_rma_order ro on ro.id= csr.rma_id
                WHERE csr.`buyer_id` = " . $customer_id . " AND csr.`order_status` = 2  AND csrl.`item_code` = '" . $item_code . "'
                )";
        return $this->db->query($sql)->rows;
    }

    public function getBuyStorageRecord($filter_data){

        $inStockRecord="SELECT
                            scd.seller_id,
                            op.sku,
                            oo.delivery_type,
                            CASE 	WHEN scd.rma_id IS NULL THEN
                                    oo.date_modified
                            ELSE
                                scd.create_time
                            END AS creationTime,
                            'Receiving' AS type,
                            ctc.screenname,
                            scd.original_qty AS quantity,
                            CASE
                        WHEN scd.rma_id IS NULL THEN
                            'Purchase Order'
                        ELSE
                            'RMA(Reshipment)'
                        END AS reason,
                        ifnull(
                            srl.oc_order_id,
                            yro.rma_order_id
                        ) AS orderID,
                         CASE  WHEN scd.rma_id IS NULL THEN scd.id
		                ELSE yro.id END AS id
                    FROM
                        `tb_sys_cost_detail` AS `scd`
                    LEFT JOIN `tb_sys_receive_line` AS `srl` ON `srl`.`id` = `scd`.`source_line_id`
                    LEFT JOIN oc_order oo on oo.order_id=srl.oc_order_id AND oo.customer_id=scd.buyer_id
                    LEFT JOIN `oc_product` AS `op` ON `op`.`product_id` = `scd`.`sku_id`
                    LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `op`.`product_id`
                    LEFT JOIN `oc_customerpartner_to_customer` AS `ctc` ON `ctc`.`customer_id` = `ctp`.`customer_id`
                    LEFT JOIN `oc_yzc_rma_order` AS `yro` ON `yro`.`id` = `scd`.`rma_id` AND yro.buyer_id=scd.buyer_id";
        $inStockRecord .=" where scd.buyer_id = ".$filter_data['customer_id']." AND op.sku = '".$filter_data['item_code']."'";

        $outStockSaleRecord = "SELECT
                                soa.seller_id,
                                    csol.item_code AS sku,
                                    0,
                                    CASE
                                WHEN max(oo.date_modified) > cso.create_time THEN
                                    max(oo.date_modified)
                                ELSE
                                    cso.create_time
                                END AS creationTime,
                                CASE WHEN cso.order_status=".CustomerSalesOrderStatus::COMPLETED." then 'Dispatch'
                                 ELSE 'Blocked' END AS type,
                                ctc.screenname,
                                sum(case when soa.seller_id not in".SERVICE_STORE." then soa.qty else 0 end) AS quantity,
                                    case when cl.id is not null then 'CWF Order'
                                 WHEN cso.order_status=".CustomerSalesOrderStatus::COMPLETED." THEN 'Sales Order'
                                 WHEN cso.order_status=".CustomerSalesOrderStatus::BEING_PROCESSED." THEN 'Sold but not Shipped'
                                  WHEN cso.order_status=".CustomerSalesOrderStatus::ASR_TO_BE_PAID." THEN 'Blocked(ASR)'
                                   WHEN cso.order_status=".CustomerSalesOrderStatus::PENDING_CHARGES." THEN 'Blocked(Pending Charges)'
                                   END AS reason,
                                   CASE WHEN cso.order_status=".CustomerSalesOrderStatus::COMPLETED." THEN  cso.order_id
                                 WHEN cso.order_status=".CustomerSalesOrderStatus::BEING_PROCESSED." THEN  cso.order_id
                                  WHEN cso.order_status=".CustomerSalesOrderStatus::ASR_TO_BE_PAID." THEN  cso.order_id
                                  WHEN cso.order_status=".CustomerSalesOrderStatus::PENDING_CHARGES." THEN cso.order_id
                                   END  AS orderID,
                                   CASE
                                   WHEN cl.id is not null THEN cl.id
                                   WHEN cso.order_status=".CustomerSalesOrderStatus::COMPLETED." THEN cso.id
                                 WHEN cso.order_status=".CustomerSalesOrderStatus::BEING_PROCESSED." THEN  cso.id
                                  WHEN cso.order_status=".CustomerSalesOrderStatus::ASR_TO_BE_PAID." THEN  cso.id
                                   WHEN cso.order_status=".CustomerSalesOrderStatus::PENDING_CHARGES." THEN  cso.id
                                   END AS id
                            FROM
                                `tb_sys_customer_sales_order` AS `cso`
                            LEFT JOIN `tb_sys_customer_sales_order_line` AS `csol` ON `cso`.`id` = `csol`.`header_id`
                            LEFT JOIN `tb_sys_order_associated` AS `soa` ON `soa`.`sales_order_line_id` = `csol`.`id`
                            LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `soa`.`product_id`
                            LEFT JOIN `oc_customerpartner_to_customer` AS `ctc` ON `ctc`.`customer_id` = `ctp`.`customer_id`
                            LEFT JOIN  oc_order_cloud_logistics as cl ON cl.order_id = soa.order_id
                            LEFT JOIN `oc_order` AS `oo` ON `oo`.`order_id` = `soa`.`order_id`";
        $outStockSaleRecord .= " where soa.id is not null and cso.buyer_id = ".$filter_data['customer_id']." AND csol.item_code = '".$filter_data['item_code']."'";
        $europeFreightProduct = str_replace('[','(',$this->config->get("europe_freight_product_id"));
        $europeFreightProduct = str_replace(']',')',$europeFreightProduct);
        $outStockSaleRecord .=" and soa.product_id not in ".$europeFreightProduct;
//                                 and soa.seller_id not in".SERVICE_STORE;
        if (isset($filter_data['type']) && $filter_data['type'] == 3) {
            $outStockSaleRecord .=" and cso.order_status in(".CustomerSalesOrderStatus::BEING_PROCESSED.",".CustomerSalesOrderStatus::PENDING_CHARGES.",".CustomerSalesOrderStatus::ASR_TO_BE_PAID.") group by csol.id ";
        }else if(isset($filter_data['type']) && $filter_data['type'] == 2){
            $outStockSaleRecord .=" and cso.order_status = ".CustomerSalesOrderStatus::COMPLETED." group by csol.id ";
        }else{
            $outStockSaleRecord .=" and cso.order_status in(".CustomerSalesOrderStatus::BEING_PROCESSED.",".CustomerSalesOrderStatus::COMPLETED.",".CustomerSalesOrderStatus::PENDING_CHARGES.",".CustomerSalesOrderStatus::ASR_TO_BE_PAID.") group by csol.id ";
        }

        $outStockReshipmentRecord = "SELECT
                                        yro.seller_id,
                                        csrl.item_code AS sku,
                                        0,
                                        scd.create_time,
                                  CASE WHEN csr.order_status=32 then 'Dispatch'
                                  ELSE 'Blocked' END AS type,
                                        ctc.screenname,
                                        csrl.qty AS quantity,
                                         CASE WHEN csr.order_status=32 THEN 'RMA(Reshipment)'
                                      WHEN csr.order_status=2 THEN 'RMA but not Shipped'
                                       ELSE 'Blocked(Canceled RMA Order)' END AS reason,
                                        yro.rma_order_id AS orderID,
                                        yro.id as id
                                    FROM
                                        `tb_sys_customer_sales_reorder` AS `csr`
                                    LEFT JOIN `tb_sys_customer_sales_reorder_line` AS `csrl` ON `csrl`.`reorder_header_id` = `csr`.`id`
                                    LEFT JOIN `oc_yzc_rma_order` AS `yro` ON `yro`.`id` = `csr`.`rma_id`
                                    LEFT JOIN `tb_sys_cost_detail` AS `scd` ON `scd`.`rma_id` = `csr`.`rma_id` and scd.sku_id=csrl.product_id
                                    LEFT JOIN `oc_product` AS `op` ON `op`.`product_id` = `scd`.`sku_id`
                                    LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `op`.`product_id`
                                    LEFT JOIN `oc_customerpartner_to_customer` AS `ctc` ON `ctc`.`customer_id` = `ctp`.`customer_id`";
        $outStockReshipmentRecord .=" where csr.buyer_id = ".$filter_data['customer_id']." AND csrl.item_code = '".
            $filter_data['item_code']."'";
        if (isset($filter_data['type']) && $filter_data['type'] == 3) {
            $outStockReshipmentRecord .=" and csr.order_status = 2 ";
        }else if(isset($filter_data['type']) && $filter_data['type'] == 2){
            $outStockReshipmentRecord .=" and csr.order_status = 32";
        }else{
            $outStockReshipmentRecord .=" and csr.order_status in(2,32)";
        }

        $outStockRefundRecord = "SELECT
                                    yro.seller_id,
                                    op.sku,
                                    0,
                                    yro.create_time AS creationTime,
                                    CASE WHEN rop.status_refund=1 then 'Dispatch'
                                  ELSE 'Blocked' END AS type,
                                    ctc.screenname,
                                    rop.quantity,
                                  CASE WHEN rop.status_refund=1 then 'RMA(Refund)'
                                  ELSE 'Blocked(Applying RMA)' END AS reason,
                                    yro.rma_order_id AS orderID,
                                    yro.id as id
                                FROM
                                    `oc_yzc_rma_order` AS `yro`
                                LEFT JOIN `oc_yzc_rma_order_product` AS `rop` ON `rop`.`rma_id` = `yro`.`id`
                                LEFT JOIN `oc_product` AS `op` ON `op`.`product_id` = `rop`.`product_id`
                                LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `op`.`product_id`
                                LEFT JOIN `oc_customerpartner_to_customer` AS `ctc` ON `ctc`.`customer_id` = `ctp`.`customer_id`";
        $outStockRefundRecord .=" where yro.order_type=2  AND yro.cancel_rma = 0 and yro.buyer_id = ".$filter_data['customer_id']." AND op.sku = '".$filter_data['item_code']."'";
        if (isset($filter_data['type']) && $filter_data['type'] == 3) {
            $outStockRefundRecord .=" and rop.status_refund =0 ";
        }else if(isset($filter_data['type']) && $filter_data['type'] == 2){
            $outStockRefundRecord .=" and rop.status_refund =1 ";
        }else{
            $outStockRefundRecord .=" and rop.status_refund<>2 ";
        }

        //cancel销售订单的返金出库
        $outStockSalesRefundRecord = "SELECT
                                    soa.seller_id,
                                    csol.item_code AS sku,
                                    0,
                                    CASE
                                WHEN max(oo.date_modified) > cso.create_time THEN
                                    max(oo.date_modified)
                                ELSE
                                    cso.create_time
                                END AS creationTime,
                                CASE WHEN roi.status_refund=1  then 'Dispatch'
                                 ELSE 'Blocked' END AS type,
                                ctc.screenname,
                                case when soa.seller_id not in".SERVICE_STORE."  THEN sum(soa.qty) else 0 end AS quantity,
                                 CASE WHEN roi.status_refund=1 THEN 'RMA(Refund)'
                                 WHEN roi.rma_id is null or roi.status_refund=2 THEN 'Blocked(Canceled Sales Order)'
                                  ELSE 'Blocked(Applying RMA)' END AS reason,
                                  CASE WHEN roi.rma_id is null or roi.status_refund=2 THEN cso.order_id
                                  ELSE roi.rma_order_id  END  AS orderID,
                                  CASE WHEN roi.status_refund <> 2 THEN  roi.rma_id
                                  ELSE cso.id END AS id
                            FROM
                                `tb_sys_customer_sales_order` AS `cso`
                            LEFT JOIN `tb_sys_customer_sales_order_line` AS `csol` ON `cso`.`id` = `csol`.`header_id`
                            LEFT JOIN `tb_sys_order_associated` AS `soa` ON `soa`.`sales_order_line_id` = `csol`.`id` AND soa.buyer_id=cso.buyer_id
                            LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `soa`.`product_id`
                            LEFT JOIN `oc_customerpartner_to_customer` AS `ctc` ON `ctc`.`customer_id` = `ctp`.`customer_id`
                            LEFT JOIN  vw_rma_order_info AS  roi ON  (roi.from_customer_order_id=cso.order_id AND roi.buyer_id=cso.buyer_id AND roi.product_id=soa.product_id AND roi.cancel_status = 0 and roi.status_refund<>2)
                            LEFT JOIN `oc_order` AS `oo` ON `oo`.`order_id` = `soa`.`order_id`";
        $outStockSalesRefundRecord .= " where  soa.id is not null and cso.buyer_id = ".$filter_data['customer_id']." AND csol.item_code = '".$filter_data['item_code']."'";
        $outStockSalesRefundRecord .= " and (SELECT
                                            count(1)
                                        FROM
                                            vw_rma_order_info a
                                        WHERE
                                            a.from_customer_order_id = roi.from_customer_order_id
                                        AND a.status_refund = 1
                                        and a.processed_date < if(roi.processed_date is null,'9999-01-01',roi.processed_date)) = 0";
        if (isset($filter_data['type']) && $filter_data['type'] == 3) {
            $outStockSalesRefundRecord .=" and cso.order_status =".CustomerSalesOrderStatus::CANCELED." and (roi.status_refund <> 1 or roi.status_refund is null) group by csol.id,roi.rma_id ";
        }else if(isset($filter_data['type']) && $filter_data['type'] == 2){
            $outStockSalesRefundRecord .=" and cso.order_status = ".CustomerSalesOrderStatus::CANCELED." and roi.status_refund =1 group by csol.id,roi.rma_id ";
        }else{
            $outStockSalesRefundRecord .=" and cso.order_status =".CustomerSalesOrderStatus::CANCELED." group by csol.id,roi.rma_id ";
        }

        if (isset($filter_data['type'])) {
            if($filter_data['type'] == 1){
                $sql = "select * from (".$inStockRecord.") as temp";
            }else{
                $sql = "select * from (".$outStockRefundRecord.' UNION ALL '.$outStockReshipmentRecord.' UNION ALL '.$outStockSaleRecord.' UNION ALL '.$outStockSalesRefundRecord.") as temp";
            }
        }else{
            $sql = "select * from (".$inStockRecord."UNION ALL ".$outStockRefundRecord." UNION ALL ".$outStockReshipmentRecord." UNION ALL ".$outStockSaleRecord." UNION ALL ".$outStockSalesRefundRecord.") as temp";
        }
        $sql .= " WHERE 1=1 AND temp.seller_id not in".SERVICE_STORE;
        if (!empty($filter_data['createTimeStart'])) {
            $sql .= " AND  DATE_FORMAT(temp.creationTime,'%Y-%m-%d %H:%i:%s') >= '" . $filter_data['createTimeStart'] . "'";
        }
        if (!empty($filter_data['createTimeEnd'])) {
            $sql .= " AND  DATE_FORMAT(temp.creationTime,'%Y-%m-%d %H:%i:%s') <= '" . $filter_data['createTimeEnd'] . "'";
        }
        $sql .=" order by creationTime,type desc ";
        if (isset($filter_data['page_num']) || isset($filter_data['page_limit'])) {
            $sql .= " LIMIT " . (int)($filter_data['page_num']-1)*$filter_data['page_limit'] . "," . (int)$filter_data['page_limit'];
        }
        return $this->db->query($sql)->rows;
    }

    public function getBuyStorageRecordCount($filter_data){

        $inStockRecord="SELECT
                            op.sku,
                            CASE 	WHEN scd.rma_id IS NULL THEN
                                    oo.date_modified
                            ELSE
                                scd.create_time
                            END AS creationTime,
                            'Receiving' AS type,
                            ctc.screenname,
                            scd.original_qty AS quantity,
                            CASE
                        WHEN scd.rma_id IS NULL THEN
                            'Purchase Order'
                        ELSE
                            'RMA(Reshipment)'
                        END AS reason,
                        ifnull(
                            srl.oc_order_id,
                            yro.rma_order_id
                        ) AS orderID,
                         CASE  WHEN scd.rma_id IS NULL THEN scd.id
		                ELSE yro.id END AS id
                    FROM
                        `tb_sys_cost_detail` AS `scd`
                    LEFT JOIN `tb_sys_receive_line` AS `srl` ON `srl`.`id` = `scd`.`source_line_id`
                    LEFT JOIN oc_order oo on oo.order_id=srl.oc_order_id
                    LEFT JOIN `oc_product` AS `op` ON `op`.`product_id` = `scd`.`sku_id`
                    LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `op`.`product_id`
                    LEFT JOIN `oc_customerpartner_to_customer` AS `ctc` ON `ctc`.`customer_id` = `ctp`.`customer_id`
                    LEFT JOIN `oc_yzc_rma_order` AS `yro` ON `yro`.`id` = `scd`.`rma_id`";
        $inStockRecord .="where scd.buyer_id = ".$filter_data['customer_id']." AND op.sku = '".$filter_data['item_code']."'";

        $outStockSaleRecord = "SELECT
                                    csol.item_code AS sku,
                                    CASE
                                WHEN max(oo.date_modified) > cso.create_time THEN
                                    max(oo.date_modified)
                                ELSE
                                    cso.create_time
                                END AS creationTime,
                                CASE WHEN cso.order_status=".CustomerSalesOrderStatus::COMPLETED." then 'Dispatch'
                                 ELSE 'Blocked' END AS type,
                                ctc.screenname,
                                sum(case when soa.seller_id not in".SERVICE_STORE." then soa.qty else 0 end) AS quantity,
                                case when cl.id is not null then 'CWF Order'
                                 WHEN cso.order_status=".CustomerSalesOrderStatus::COMPLETED." THEN 'Sales Order'
                                 WHEN cso.order_status=".CustomerSalesOrderStatus::BEING_PROCESSED." THEN 'Sold but not Shipped'
                                  WHEN cso.order_status=".CustomerSalesOrderStatus::ASR_TO_BE_PAID." THEN 'Blocked(ASR)'
                                   END AS reason,
                                   CASE WHEN cso.order_status=".CustomerSalesOrderStatus::COMPLETED." THEN  cso.order_id
                                 WHEN cso.order_status=".CustomerSalesOrderStatus::BEING_PROCESSED." THEN  cso.order_id
                                  WHEN cso.order_status=".CustomerSalesOrderStatus::ASR_TO_BE_PAID." THEN  cso.order_id
                                   END  AS orderID,
                                   CASE
                                   WHEN cl.id is not null THEN cl.id
                                   WHEN cso.order_status=".CustomerSalesOrderStatus::COMPLETED." THEN cso.id
                                 WHEN cso.order_status=".CustomerSalesOrderStatus::BEING_PROCESSED." THEN  cso.id
                                  WHEN cso.order_status=".CustomerSalesOrderStatus::ASR_TO_BE_PAID." THEN  cso.id
                                   END AS id
                            FROM
                                `tb_sys_customer_sales_order` AS `cso`
                            LEFT JOIN `tb_sys_customer_sales_order_line` AS `csol` ON `cso`.`id` = `csol`.`header_id`
                            LEFT JOIN `tb_sys_order_associated` AS `soa` ON `soa`.`sales_order_line_id` = `csol`.`id`
                            LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `soa`.`product_id`
                            LEFT JOIN `oc_customerpartner_to_customer` AS `ctc` ON `ctc`.`customer_id` = `ctp`.`customer_id`
                            LEFT JOIN  oc_order_cloud_logistics as cl ON cl.order_id = soa.order_id
                            LEFT JOIN `oc_order` AS `oo` ON `oo`.`order_id` = `soa`.`order_id`";
        $outStockSaleRecord .= " where  soa.id is not null and cso.buyer_id = ".$filter_data['customer_id']." AND csol.item_code = '".$filter_data['item_code']."'";
//                                 and soa.seller_id not in".SERVICE_STORE;
        if (isset($filter_data['type']) && $filter_data['type'] == 3) {
            $outStockSaleRecord .=" and cso.order_status in(".CustomerSalesOrderStatus::BEING_PROCESSED.",".CustomerSalesOrderStatus::ASR_TO_BE_PAID.") group by csol.id ";
        }else if(isset($filter_data['type']) && $filter_data['type'] == 2){
            $outStockSaleRecord .=" and cso.order_status = ".CustomerSalesOrderStatus::COMPLETED."  group by csol.id ";
        }else{
            $outStockSaleRecord .=" and cso.order_status in(".CustomerSalesOrderStatus::BEING_PROCESSED.",".CustomerSalesOrderStatus::COMPLETED.",".CustomerSalesOrderStatus::ASR_TO_BE_PAID.") group by csol.id ";
        }


        $outStockReshipmentRecord = "SELECT
                                        csrl.item_code AS sku,
                                        scd.create_time,
                                  CASE WHEN csr.order_status=32 then 'Dispatch'
                                  ELSE 'Blocked' END AS type,
                                        ctc.screenname,
                                        csrl.qty AS quantity,
                                         CASE WHEN csr.order_status=32 THEN 'RMA(Reshipment)'
                                      WHEN csr.order_status=2 THEN 'RMA but not Shipped'
                                       ELSE 'Blocked(Canceled RMA Order)' END AS reason,
                                        yro.rma_order_id AS orderID,
                                        yro.id as id
                                    FROM
                                        `tb_sys_customer_sales_reorder` AS `csr`
                                    LEFT JOIN `tb_sys_customer_sales_reorder_line` AS `csrl` ON `csrl`.`reorder_header_id` = `csr`.`id`
                                    LEFT JOIN `oc_yzc_rma_order` AS `yro` ON `yro`.`id` = `csr`.`rma_id`
                                    LEFT JOIN `tb_sys_cost_detail` AS `scd` ON `scd`.`rma_id` = `csr`.`rma_id` and scd.sku_id=csrl.product_id
                                    LEFT JOIN `oc_product` AS `op` ON `op`.`product_id` = `scd`.`sku_id`
                                    LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `op`.`product_id`
                                    LEFT JOIN `oc_customerpartner_to_customer` AS `ctc` ON `ctc`.`customer_id` = `ctp`.`customer_id`";
        $outStockReshipmentRecord .=" where csr.buyer_id = ".$filter_data['customer_id']." AND csrl.item_code = '".
            $filter_data['item_code']."'";
        if (isset($filter_data['type']) && $filter_data['type'] == 3) {
            $outStockReshipmentRecord .=" and csr.order_status = 2 ";
        }else if(isset($filter_data['type']) && $filter_data['type'] == 2){
            $outStockReshipmentRecord .=" and csr.order_status = 32";
        }else{
            $outStockReshipmentRecord .=" and csr.order_status in(2,32)";
        }

        $outStockRefundRecord = "SELECT
                                    op.sku,
                                    yro.create_time AS creationTime,
                                    CASE WHEN rop.status_refund=1 then 'Dispatch'
                                  ELSE 'Blocked' END AS type,
                                    ctc.screenname,
                                    rop.quantity,
                                  CASE WHEN rop.status_refund=1 then 'RMA(Refund)'
                                  ELSE 'Blocked(Applying RMA)' END AS reason,
                                    yro.rma_order_id AS orderID,
                                    yro.id as id
                                FROM
                                    `oc_yzc_rma_order` AS `yro`
                                LEFT JOIN `oc_yzc_rma_order_product` AS `rop` ON `rop`.`rma_id` = `yro`.`id`
                                LEFT JOIN `oc_product` AS `op` ON `op`.`product_id` = `rop`.`product_id`
                                LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `op`.`product_id`
                                LEFT JOIN `oc_customerpartner_to_customer` AS `ctc` ON `ctc`.`customer_id` = `ctp`.`customer_id`";
        $outStockRefundRecord .=" where yro.order_type=2 AND yro.cancel_rma = 0 and yro.buyer_id = ".$filter_data['customer_id']." AND op.sku = '".$filter_data['item_code']."'";
        if (isset($filter_data['type']) && $filter_data['type'] == 3) {
            $outStockRefundRecord .=" and rop.status_refund =0 ";
        }else if(isset($filter_data['type']) && $filter_data['type'] == 2){
            $outStockRefundRecord .=" and rop.status_refund =1 ";
        }else{
            $outStockRefundRecord .=" and rop.status_refund<>2 ";
        }
        //cancel销售订单的返金出库
        $outStockSalesRefundRecord = "SELECT
                                    csol.item_code AS sku,
                                    CASE
                                WHEN max(oo.date_modified) > cso.create_time THEN
                                    max(oo.date_modified)
                                ELSE
                                    cso.create_time
                                END AS creationTime,
                                CASE WHEN roi.status_refund=1  then 'Dispatch'
                                 ELSE 'Blocked' END AS type,
                                ctc.screenname,
                                case when soa.seller_id not in".SERVICE_STORE." then sum(soa.qty) else 0 end AS quantity,
                                 CASE WHEN roi.status_refund=1 THEN 'RMA(Refund)'
                                 WHEN roi.rma_id is null or roi.status_refund=2 THEN 'Blocked(Canceled Sales Order)'
                                  ELSE 'Blocked(Applying RMA)' END AS reason,
                                  CASE WHEN roi.rma_id is null or roi.status_refund=2 THEN cso.order_id
                                  ELSE roi.rma_order_id  END  AS orderID,
                                  CASE WHEN roi.status_refund <> 2 THEN  roi.rma_id
                                  ELSE cso.id END AS id
                            FROM
                                `tb_sys_customer_sales_order` AS `cso`
                            LEFT JOIN `tb_sys_customer_sales_order_line` AS `csol` ON `cso`.`id` = `csol`.`header_id`
                            LEFT JOIN `tb_sys_order_associated` AS `soa` ON `soa`.`sales_order_line_id` = `csol`.`id`
                            LEFT JOIN `oc_customerpartner_to_product` AS `ctp` ON `ctp`.`product_id` = `soa`.`product_id`
                            LEFT JOIN `oc_customerpartner_to_customer` AS `ctc` ON `ctc`.`customer_id` = `ctp`.`customer_id`
                            LEFT JOIN  vw_rma_order_info AS  roi ON  (roi.from_customer_order_id=cso.order_id AND roi.buyer_id=cso.buyer_id AND roi.product_id=soa.product_id AND roi.cancel_status = 0 and roi.status_refund<>2)
                            LEFT JOIN `oc_order` AS `oo` ON `oo`.`order_id` = `soa`.`order_id`";
        $outStockSalesRefundRecord .= " where  soa.id is not null and cso.buyer_id = ".$filter_data['customer_id']." AND csol.item_code = '".$filter_data['item_code']."'";
        $outStockSalesRefundRecord .= "and (SELECT
                                            count(1)
                                        FROM
                                            vw_rma_order_info a
                                        WHERE
                                            a.from_customer_order_id = roi.from_customer_order_id
                                        AND a.status_refund = 1
                                        and a.processed_date < if(roi.processed_date is null,'9999-01-01',roi.processed_date)) = 0";
        if (isset($filter_data['type']) && $filter_data['type'] == 3) {
            $outStockSalesRefundRecord .=" and cso.order_status =".CustomerSalesOrderStatus::CANCELED." and (roi.status_refund <> 1 or roi.status_refund is null) group by csol.id,roi.rma_id ";
        }else if(isset($filter_data['type']) && $filter_data['type'] == 2){
            $outStockSalesRefundRecord .=" and cso.order_status = ".CustomerSalesOrderStatus::CANCELED." and roi.status_refund =1 group by csol.id,roi.rma_id ";
        }else{
            $outStockSalesRefundRecord .=" and cso.order_status =".CustomerSalesOrderStatus::CANCELED." group by csol.id,roi.rma_id ";
        }

        if (isset($filter_data['type'])) {
            if($filter_data['type'] == 1){
                $sql = "select COUNT(*) as total from (".$inStockRecord.") as temp";
            }else{
                $sql = "select COUNT(*) as total from (".$outStockRefundRecord.' UNION ALL '.$outStockReshipmentRecord.' UNION ALL '.$outStockSaleRecord.'UNION ALL '.$outStockSalesRefundRecord.") as temp";
            }
        }else{
            $sql = "select COUNT(*) as total from (".$inStockRecord."UNION ALL ".$outStockRefundRecord." UNION ALL ".$outStockReshipmentRecord." UNION ALL ".$outStockSaleRecord."UNION ALL ".$outStockSalesRefundRecord.") as temp";
        }
        $sql .= " WHERE 1=1 ";
        if (!empty($filter_data['createTimeStart'])) {
            $sql .= " AND  DATE_FORMAT(temp.creationTime,'%Y-%m-%d %H:%i:%s') >= '" . $filter_data['createTimeStart'] . "'";
        }
        if (!empty($filter_data['createTimeEnd'])) {
            $sql .= " AND  DATE_FORMAT(temp.creationTime,'%Y-%m-%d %H:%i:%s') <= '" . $filter_data['createTimeEnd'] . "'";
        }
        $result =  $this->db->query($sql)->row;
        return $result['total'];
    }

}
