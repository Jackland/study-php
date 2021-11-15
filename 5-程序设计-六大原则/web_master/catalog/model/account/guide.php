<?php

use App\Enums\SalesOrder\CustomerSalesOrderStatus;

/**
 * Class ModelAccountGuide
 */
class ModelAccountGuide extends Model
{
    /**
     * [getBuyerNewOrderAmount description] 获取new order 订单
     * @param int $customer_id
     * @param int $country_id
     * @return int
     */
    public function  getBuyerNewOrderAmount($customer_id,$country_id)
    {
        //100841 不论国别，均提醒NewOrder数量
        $map = [
            ['o.order_status','=',CustomerSalesOrderStatus::TO_BE_PAID], // new_order
            ['o.buyer_id','=',$customer_id],
        ];
        return $this->orm->table('tb_sys_customer_sales_order as o')->where($map)->count('id');
    }

    /**
     * [getBuyerOrderData description]
     * @param int $customer_id
     * @param int $country_id
     * @return array
     */
    public function getBuyerOrderData($customer_id,$country_id)
    {
        $res = [];
        if($country_id == 223){
        //现在仅仅针对于美国

            $mapCount = [
                ['o.buyer_id','=',$customer_id],
            ];
            //前一天的现在
            $time_list[] = date('Y-m-d H:i:s',time()-86400);
            $time_list[] = date('Y-m-d H:i:s',time());
            //导入的订单数量
            $countOrder = $this->orm->table('tb_sys_customer_sales_order as o')->
                where($mapCount)->whereBetween('o.create_time',$time_list)->count('id');
            //
            $mapShipOrder = [
                ['o.buyer_id','=',$customer_id],
                ['o.order_status','=',CustomerSalesOrderStatus::COMPLETED], // 32 completed
            ];
            $shipOrderQty = $this->orm->table('tb_sys_customer_sales_order as o')
                ->leftJoin('tb_sys_customer_sales_order_line as l','l.header_id','=','o.id')
                ->where($mapShipOrder)->whereBetween('o.create_time',$time_list)->sum('l.qty');
            //昨日导入订单未发货数量
            $mapUnshipOrder = [
                ['o.buyer_id','=',$customer_id],
                ['o.order_status','=',CustomerSalesOrderStatus::BEING_PROCESSED], // 2 being processed
            ];
            $unshipOrderQty = $this->orm->table('tb_sys_customer_sales_order as o')
                ->leftJoin('tb_sys_customer_sales_order_line as l','l.header_id','=','o.id')
                ->where($mapUnshipOrder)->whereBetween('o.create_time',$time_list)->sum('l.qty');
            $res['count_order'] = $countOrder;
            $res['ship_order_qty'] = $shipOrderQty;
            $res['unship_order_qty'] = $unshipOrderQty;
            $res['order_amount'] = $this->getBuyerNewOrderAmount($customer_id,$country_id);
        }else{
            $res['count_order'] = 0;
            $res['ship_order_qty'] = 0;
            $res['unship_order_qty'] = 0;
            $res['order_amount'] = 0;
        }
        return $res;
    }


}
