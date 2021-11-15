<?php

use App\Enums\Order\OcOrderStatus;

/**
 * Class ModelAccountBillSalesPurchaseBill
 * Created by IntelliJ IDEA.
 * User: xxl
 * Date: 2019/8/13
 * Time: 20:27
 */
class ModelAccountBillSalesPurchaseBill extends Model
{

    /**
     * 获取销售订单状态
     * @author xxl
     * @return array
     */
    public function getSalesOrderStatus($customer_id)
    {
        $result = db('oc_customer as oc')
            ->leftJoin('oc_customer_group_description as od','oc.customer_group_id','=','od.customer_group_id')
            ->where('oc.customer_id','=',$customer_id)
            ->whereIn('od.name',ALL_DROPSHIP)
            ->count();
        $whereSql = " tsd.DicCategory = 'CUSTOMER_ORDER_STATUS' ";
        if($result>0){
            $whereSql .= " and tsd.DicKey not in(8) ";
        }else{
            $whereSql .= " and tsd.DicKey not in(8,129) ";
        }
        $result = db('tb_sys_dictionary as tsd')
            ->selectRaw("IF(tsd.DicKey=1, 'To Be Paid', tsd.DicValue) AS DicValue")
            ->addSelect('tsd.DicKey')
            ->whereRaw($whereSql)
            ->get();
        return obj2array($result);
    }

    /**
     * 获取SalesAndPurchaseBill的总数
     * @author xxl
     * @param $param ,$customer_id
     * @return \Illuminate\Support\Collection
     */
    public function getSaleAndPurchaseBillsTotal($filter_data, $customer_id)
    {

        //查询条件拼接
        $whereStr = "soa.id is not null and sd.DicCategory = 'CUSTOMER_ORDER_STATUS' and cso.buyer_id=" . $customer_id;
        if (isset($filter_data['filter_sales_order_id']) && trim($filter_data['filter_sales_order_id']) != '') {
            $whereStr .= " AND cso.order_id LIKE '%" . $this->db->escape(trim($filter_data['filter_sales_order_id'])) . "%'";
        }
        if (isset($filter_data['filter_sales_order_status']) && trim($filter_data['filter_sales_order_status']) != '') {
            $whereStr .= " AND cso.order_status =" . $this->db->escape(trim($filter_data['filter_sales_order_status']));
        }
        if (isset($filter_data['filter_create_time']) && trim($filter_data['filter_create_time']) != '') {
            $whereStr .= " AND cso.create_time >='" . $this->db->escape(trim($filter_data['filter_create_time']))."'";
        }
        if (isset($filter_data['filter_create_time_end']) && trim($filter_data['filter_create_time_end']) != '') {
            $whereStr .= " AND cso.create_time <='" . $this->db->escape(trim($filter_data['filter_create_time_end']))."'";
        }
        if (isset($filter_data['filter_purchase_order_id']) && trim($filter_data['filter_purchase_order_id']) != '') {
            $whereStr .= " AND oo.order_id  LIKE '%" . $this->db->escape(trim($filter_data['filter_purchase_order_id'])) . "%'";
        }
        if (isset($filter_data['filter_store']) && trim($filter_data['filter_store']) != '') {
            $whereStr .= " AND ctc.customer_id  =" . $this->db->escape(trim($filter_data['filter_store']));
        }
        if (isset($filter_data['filter_purchase_order_date']) && trim($filter_data['filter_purchase_order_date']) != '') {
            $whereStr .= " AND oo.date_added  >='" . $this->db->escape(trim($filter_data['filter_purchase_order_date']))."'";
        }
        if (isset($filter_data['filter_purchase_order_date_end']) && trim($filter_data['filter_purchase_order_date_end']) != '') {
            $whereStr .= " AND oo.date_added  <='" . $this->db->escape(trim($filter_data['filter_purchase_order_date_end']))."'";
        }
        if (isset($filter_data['filter_item_code']) && trim($filter_data['filter_item_code']) != '') {
            $whereStr .= " AND csol.item_code  LIKE '%" . $this->db->escape(trim($filter_data['filter_item_code'])) . "%'";
        }
        $result = $this->orm->table('tb_sys_customer_sales_order as cso')
            ->leftJoin('tb_sys_customer_sales_order_line as csol', 'cso.id', '=', 'csol.header_id')
            ->leftJoin('tb_sys_order_associated as soa', 'soa.sales_order_line_id', '=', 'csol.id')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'soa.seller_id')
            ->leftJoin('oc_order as oo', 'oo.order_id', '=', 'soa.order_id')
            ->leftJoin('oc_order_product as oop', 'oop.order_product_id', '=', 'soa.order_product_id')
            ->leftJoin('oc_product_quote as opq', [['opq.order_id', '=', 'soa.order_id'], ['opq.product_id', '=', 'soa.product_id']])
            ->leftJoin('tb_sys_dictionary as sd', 'sd.DicKey', '=', 'cso.order_status')
            ->whereRaw($whereStr)
            ->count();
        return $result;
    }


    /**
     * * 获取SalesAndPurchaseBill
     * @author xxl
     * @param array $filter_data
     * @param int $customer_id
     * @return \Illuminate\Support\Collection
     */
    public function getSaleAndPurchaseBills($filter_data, $customer_id)
    {

        //查询条件拼接
        $whereStr = "soa.id is not null and sd.DicCategory = 'CUSTOMER_ORDER_STATUS' and cso.buyer_id=" . $customer_id;
        if (isset($filter_data['filter_sales_order_id']) && trim($filter_data['filter_sales_order_id']) != '') {
            $whereStr .= " AND cso.order_id LIKE '%" . $this->db->escape(trim($filter_data['filter_sales_order_id'])) . "%'";
        }
        if (isset($filter_data['filter_sales_order_status']) && trim($filter_data['filter_sales_order_status']) != '') {
            $whereStr .= " AND cso.order_status =" . $this->db->escape(trim($filter_data['filter_sales_order_status']));
        }
        if (isset($filter_data['filter_create_time']) && trim($filter_data['filter_create_time']) != '') {
            $whereStr .= " AND cso.create_time >='" . $this->db->escape(trim($filter_data['filter_create_time']))."'";
        }
        if (isset($filter_data['filter_create_time_end']) && trim($filter_data['filter_create_time_end']) != '') {
            $whereStr .= " AND cso.create_time <='" . $this->db->escape(trim($filter_data['filter_create_time_end']))."'";
        }
        if (isset($filter_data['filter_purchase_order_id']) && trim($filter_data['filter_purchase_order_id']) != '') {
            $whereStr .= " AND oo.order_id  LIKE '%" . $this->db->escape(trim($filter_data['filter_purchase_order_id'])) . "%'";
        }
        if (isset($filter_data['filter_store']) && trim($filter_data['filter_store']) != '') {
            $whereStr .= " AND ctc.customer_id  =" . $this->db->escape(trim($filter_data['filter_store']));
        }
        if (isset($filter_data['filter_purchase_order_date']) && trim($filter_data['filter_purchase_order_date']) != '') {
            $whereStr .= " AND oo.date_added  >='" . $this->db->escape(trim($filter_data['filter_purchase_order_date']))."'";
        }
        if (isset($filter_data['filter_purchase_order_date_end']) && trim($filter_data['filter_purchase_order_date_end']) != '') {
            $whereStr .= " AND oo.date_added  <='" . $this->db->escape(trim($filter_data['filter_purchase_order_date_end']))."'";
        }
        if (isset($filter_data['filter_item_code']) && trim($filter_data['filter_item_code']) != '') {
            $whereStr .= " AND csol.item_code  LIKE '%" . $this->db->escape(trim($filter_data['filter_item_code'])) . "%'";
        }
//        if (isset($filter_data['filter_order_type']) && trim($filter_data['filter_order_type']) != '') {
//            if($filter_data['filter_order_type'] == 1){
//                $whereStr .= " AND soa.id  =" . $this->db->escape(trim($filter_data['filter_order_type']));
//            }else{
//                $whereStr .= " AND soa.id  =" . $this->db->escape(trim($filter_data['filter_order_type']));
//            }
//        }

        //查询列拼接
        $selectStr = "soa.product_id,cso.order_id as sales_order_id,cso.ship_name,cso.ship_address1,cso.ship_city,cso.ship_state,cso.ship_country,cso.ship_zip_code,soa.coupon_amount,soa.campaign_amount,
        CONCAT(cso.ship_address1,',',cso.ship_city,',',cso.ship_state,',',cso.ship_zip_code,',',cso.ship_country) as ship_address,
        cso.create_time,csol.item_code,soa.qty,ifnull(opq.price,oop.price) as price,oop.service_fee_per as service_fee_per,(oop.poundage/oop.quantity) as transaction_fee,
        ctc.screenname,soa.order_id,oo.date_modified,oo.delivery_type
        ,IF(sd.DicKey=1,'To Be Paid',sd.DicValue) AS DicValue
        ,if(opq.price is null,false,true) as quoteFlag,oop.price as op_price,opq.amount_price_per,opq.amount_service_fee_per,oop.freight_per,oop.package_fee,oop.freight_difference_per";
        $builder = $this->orm->table('tb_sys_customer_sales_order as cso')
            ->leftJoin('tb_sys_customer_sales_order_line as csol', 'cso.id', '=', 'csol.header_id')
            ->leftJoin('tb_sys_order_associated as soa', 'soa.sales_order_line_id', '=', 'csol.id')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'soa.seller_id')
            ->leftJoin('oc_order as oo', 'oo.order_id', '=', 'soa.order_id')
            ->leftJoin('oc_order_product as oop', 'oop.order_product_id', '=', 'soa.order_product_id')
            ->leftJoin('oc_product_quote as opq', [['opq.order_id', '=', 'soa.order_id'], ['opq.product_id', '=', 'soa.product_id']])
            ->leftJoin('tb_sys_dictionary as sd', 'sd.DicKey', '=', 'cso.order_status')
            ->whereRaw($whereStr)
            ->selectRaw($selectStr)
            ->orderBy('cso.create_time','desc');
        isset($filter_data['page_num'])
        && isset($filter_data['page_limit'])
        && $builder->forPage($filter_data['page_num'], $filter_data['page_limit']);
        $result = $builder->get();
        foreach ($result as $item) {
            $item->ship_address = app('db-aes')->decrypt($item->ship_address1) . ',' . app('db-aes')->decrypt($item->ship_city) . ',' . $item->ship_state . ',' . $item->ship_country;
        }
        return obj2array($result);
    }


    /**
     * 获取未绑定的销售订单
     * @param $filter_data
     * @param int $customer_id
     * @return int
     */
    public function getSaleBillsTotal($filter_data, $customer_id)
    {

        //查询条件拼接
        $whereStr = "soa.id is  null and  sd.DicCategory = 'CUSTOMER_ORDER_STATUS' and cso.buyer_id=" . $customer_id;
        if (isset($filter_data['filter_sales_order_id']) && trim($filter_data['filter_sales_order_id']) != '') {
            $whereStr .= " AND cso.order_id LIKE '%" . $this->db->escape(trim($filter_data['filter_sales_order_id'])) . "%'";
        }
        if (isset($filter_data['filter_sales_order_status']) && trim($filter_data['filter_sales_order_status']) != '') {
            $whereStr .= " AND cso.order_status =" . $this->db->escape(trim($filter_data['filter_sales_order_status']));
        }
        if (isset($filter_data['filter_create_time']) && trim($filter_data['filter_create_time']) != '') {
            $whereStr .= " AND cso.create_time >='" . $this->db->escape(trim($filter_data['filter_create_time']))."'";
        }
        if (isset($filter_data['filter_create_time_end']) && trim($filter_data['filter_create_time_end']) != '') {
            $whereStr .= " AND cso.create_time <='" . $this->db->escape(trim($filter_data['filter_create_time_end']))."'";
        }
        if (isset($filter_data['filter_item_code']) && trim($filter_data['filter_item_code']) != '') {
            $whereStr .= " AND csol.item_code  LIKE '%" . $this->db->escape(trim($filter_data['filter_item_code'])) . "%'";
        }
        $result = $this->orm->table('tb_sys_customer_sales_order as cso')
            ->leftJoin('tb_sys_customer_sales_order_line as csol', 'cso.id', '=', 'csol.header_id')
            ->leftJoin('tb_sys_order_associated as soa','soa.sales_order_line_id','=','csol.id')
            ->leftJoin('tb_sys_dictionary as sd', 'sd.DicKey', '=', 'cso.order_status')
            ->whereRaw($whereStr)
            ->count();
        return $result;
    }

    /**
     * 销售订单未绑定的查询
     * @param array $filter_data
     * @param int $customer_id
     * @return array
     */
    public function getSaleBills($filter_data, $customer_id)
    {
        //查询条件拼接
        $whereStr = "soa.id is  null and sd.DicCategory = 'CUSTOMER_ORDER_STATUS' and cso.buyer_id=" . $customer_id;
        if (isset($filter_data['filter_sales_order_id']) && trim($filter_data['filter_sales_order_id']) != '') {
            $whereStr .= " AND cso.order_id LIKE '%" . $this->db->escape(trim($filter_data['filter_sales_order_id'])) . "%'";
        }
        if (isset($filter_data['filter_sales_order_status']) && trim($filter_data['filter_sales_order_status']) != '') {
            $whereStr .= " AND cso.order_status =" . $this->db->escape(trim($filter_data['filter_sales_order_status']));
        }
        if (isset($filter_data['filter_create_time']) && trim($filter_data['filter_create_time']) != '') {
            $whereStr .= " AND cso.create_time >='" . $this->db->escape(trim($filter_data['filter_create_time']))."'";
        }
        if (isset($filter_data['filter_create_time_end']) && trim($filter_data['filter_create_time_end']) != '') {
            $whereStr .= " AND cso.create_time <='" . $this->db->escape(trim($filter_data['filter_create_time_end']))."'";
        }
        if (isset($filter_data['filter_item_code']) && trim($filter_data['filter_item_code']) != '') {
            $whereStr .= " AND csol.item_code  LIKE '%" . $this->db->escape(trim($filter_data['filter_item_code'])) . "%'";
        }

        //查询列拼接
        $selectStr = "csol.id,cso.order_id as sales_order_id,cso.ship_name,csol.qty,soa.coupon_amount,soa.campaign_amount,
        cso.ship_address1,cso.ship_city,cso.ship_state,cso.ship_zip_code,cso.ship_country,
        cso.create_time,csol.item_code
        ,IF(sd.DicKey=1,'To Be Paid',sd.DicValue) AS DicValue";
        $builder = $this->orm->table('tb_sys_customer_sales_order as cso')
            ->leftJoin('tb_sys_customer_sales_order_line as csol', 'cso.id', '=', 'csol.header_id')
            ->leftJoin('tb_sys_order_associated as soa','soa.sales_order_line_id','=','csol.id')
            ->leftJoin('tb_sys_dictionary as sd', 'sd.DicKey', '=', 'cso.order_status')
            ->whereRaw($whereStr)
            ->selectRaw($selectStr)
            ->orderBy('cso.create_time');
        isset($filter_data['page_num'])
        && isset($filter_data['page_limit'])
        && $builder->forPage($filter_data['page_num'], $filter_data['page_limit']);
        $result = $builder->get();
        foreach ($result as $item) {
            $item->ship_address = app('db-aes')->decrypt($item->ship_address1) . ',' . app('db-aes')->decrypt($item->ship_city) . ',' . $item->ship_state . ',' . $item->ship_country;
        }
        return obj2array($result);
    }


    public function getPurchaseBillsTotal($filter_data, $customer_id)
    {
        $whereStr = "oo.customer_id=".$customer_id." and (soa.id is null or  soa.qty <> oop.quantity) and oo.order_status_id=".OcOrderStatus::COMPLETED;
        if (isset($filter_data['filter_purchase_order_id']) && trim($filter_data['filter_purchase_order_id']) != '') {
            $whereStr .= " AND oo.order_id  LIKE '%" . $this->db->escape(trim($filter_data['filter_purchase_order_id'])) . "%'";
        }
        if (isset($filter_data['filter_store']) && trim($filter_data['filter_store']) != '') {
            $whereStr .= " AND ctc.customer_id  =" . $this->db->escape(trim($filter_data['filter_store']));
        }
        if (isset($filter_data['filter_purchase_order_date']) && trim($filter_data['filter_purchase_order_date']) != '') {
            $whereStr .= " AND oo.date_added  >='" . $this->db->escape(trim($filter_data['filter_purchase_order_date']))."'";
        }
        if (isset($filter_data['filter_purchase_order_date_end']) && trim($filter_data['filter_purchase_order_date_end']) != '') {
            $whereStr .= " AND oo.date_added  <='" . $this->db->escape(trim($filter_data['filter_purchase_order_date_end']))."'";
        }
        if (isset($filter_data['filter_item_code']) && trim($filter_data['filter_item_code']) != '') {
            $whereStr .= " AND op.sku  LIKE '%" . $this->db->escape(trim($filter_data['filter_item_code'])) . "%'";
        }

        $selectStr = 'oop.quantity-IFNULL(sum(soa.qty),0) as quantity';//参考 getPurchaseBills
        $result = $this->orm->table('oc_order as oo')
            ->leftJoin('oc_order_product as oop', 'oop.order_id', '=', 'oo.order_id')
            ->leftJoin('oc_product as op','op.product_id','=','oop.product_id')
            ->leftJoin('tb_sys_order_associated as soa','soa.order_product_id','=','oop.order_product_id')
            ->leftJoin('oc_customerpartner_to_product as ctp','ctp.product_id','=','op.product_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc','ctc.customer_id','=','ctp.customer_id')
            ->leftJoin('oc_product_quote as opq', [['opq.order_id', '=', 'oop.order_id'], ['opq.product_id', '=', 'oop.product_id']])
            ->whereRaw($whereStr)
            ->selectRaw($selectStr)
            ->groupBy(['oop.product_id', 'oo.order_id'])
            ->get();
        $result = count($result);
        return $result;
    }

    public function getPurchaseBills($filter_data, $customer_id)
    {
        //查询条件拼接
        $whereStr = "oo.customer_id=".$customer_id." and (soa.id is null or  soa.qty <> oop.quantity) and oo.order_status_id=".OcOrderStatus::COMPLETED;
        if (isset($filter_data['filter_purchase_order_id']) && trim($filter_data['filter_purchase_order_id']) != '') {
            $whereStr .= " AND oo.order_id  LIKE '%" . $this->db->escape(trim($filter_data['filter_purchase_order_id'])) . "%'";
        }
        if (isset($filter_data['filter_store']) && trim($filter_data['filter_store']) != '') {
            $whereStr .= " AND ctc.customer_id  =" . $this->db->escape(trim($filter_data['filter_store']));
        }
        if (isset($filter_data['filter_purchase_order_date']) && trim($filter_data['filter_purchase_order_date']) != '') {
            $whereStr .= " AND oo.date_added  >='" . $this->db->escape(trim($filter_data['filter_purchase_order_date']))."'";
        }
        if (isset($filter_data['filter_purchase_order_date_end']) && trim($filter_data['filter_purchase_order_date_end']) != '') {
            $whereStr .= " AND oo.date_added  <='" . $this->db->escape(trim($filter_data['filter_purchase_order_date_end']))."'";
        }
        if (isset($filter_data['filter_item_code']) && trim($filter_data['filter_item_code']) != '') {
            $whereStr .= " AND op.sku  LIKE '%" . $this->db->escape(trim($filter_data['filter_item_code'])) . "%'";
        }

        //查询列拼接
        $selectStr = "oop.product_id,ifnull(opq.price,oop.price) as price,oop.service_fee_per as service_fee_per,(oop.poundage/oop.quantity) as transaction_fee,oop.coupon_amount,oop.campaign_amount,
        ctc.screenname,oo.order_id,oo.delivery_type,oo.date_modified,op.sku as item_code,oop.quantity-IFNULL(sum(soa.qty),0) as quantity,if(opq.price is null,false,true) as quoteFlag,oop.price as op_price,opq.amount_price_per,opq.amount_service_fee_per,oop.freight_per,oop.package_fee,oop.freight_difference_per";
        $builder = $this->orm->table('oc_order as oo')
            ->leftJoin('oc_order_product as oop', 'oop.order_id', '=', 'oo.order_id')
            ->leftJoin('oc_product as op','op.product_id','=','oop.product_id')
            ->leftJoin('tb_sys_order_associated as soa','soa.order_product_id','=','oop.order_product_id')
            ->leftJoin('oc_customerpartner_to_product as ctp','ctp.product_id','=','op.product_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc','ctc.customer_id','=','ctp.customer_id')
            ->leftJoin('oc_product_quote as opq', [['opq.order_id', '=', 'oop.order_id'], ['opq.product_id', '=', 'oop.product_id']])
            ->whereRaw($whereStr)
            ->selectRaw($selectStr)
            ->groupBy(['oop.product_id', 'oo.order_id'])
            ->orderBy('oo.date_added');
        isset($filter_data['page_num'])
        && isset($filter_data['page_limit'])
        && $builder->forPage($filter_data['page_num'], $filter_data['page_limit']);
        $result = $builder->get();
        return obj2array($result);
    }

    public function  getBuyerCode($customer_id){
        $result = $this->orm->table('oc_customer as oc')
            ->selectRaw("oc.user_number as buyerCode")
            ->where('oc.customer_id','=',$customer_id)
            ->first();
        return $result->buyerCode;
    }

}
