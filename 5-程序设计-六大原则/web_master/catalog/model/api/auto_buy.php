<?php

use App\Enums\SalesOrder\CustomerSalesOrderStatus;

/**
 * Created by PhpStorm.
 * User: Lu.Chen
 * Date: 2020/5/28
 * Time: 16:28
 */

class ModelApiAutoBuy extends Model{

    public function checkSalesOrder($buyerId,$lineId)
    {
        return $this->orm->table('tb_sys_customer_sales_order_line as line')
            ->leftJoin('tb_sys_customer_sales_order as so', 'so.id', '=', 'line.header_id')
            ->where([
                'so.buyer_id'   => $buyerId,
                'line.id'       => $lineId,
                'so.order_status'   => CustomerSalesOrderStatus::TO_BE_PAID
            ])
            ->exists();
    }

    //购物车添加成功后需要更新sales_line_id对应的销售订单明细的ItemCode为此次加入购物车生效的SKU
    public function updateSalesItemCode($sales_line_id,$item_code)
    {
        $this->orm->table('tb_sys_customer_sales_order_line')
            ->where('id',$sales_line_id)
            ->update(['item_code'=> $item_code]);
    }


}
