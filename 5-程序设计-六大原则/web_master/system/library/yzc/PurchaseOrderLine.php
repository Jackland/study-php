<?php
/**
 * Created by IntelliJ IDEA.
 * User: lilei
 * Date: 2018/11/24
 * Time: 18:09
 */

namespace Yzc;


class PurchaseOrderLine
{
    private $id;

    private $header_id;

    private $supplier_order_header_id;

    private $supplier_order_line_id;

    private $sku_id;

    private $qty;

    private $purchase_line_status;

    private $purchase_pickup_date;

    private $purchase_receive_date;

    private $pickup_method;

    private $purchase_memo;

    private $memo;

    private $create_user_name;

    private $create_time;

    private $update_user_name;

    private $update_time;

    private $program_code;

    public function __get($name)
    {
        return $this->$name;
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
    }
}