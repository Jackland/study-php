<?php

namespace Yzc;


class PurchaseOrder
{
    /**
     * 自增主键
     * @var $id
     */
    private $id;

    /**
     * @var $source_header_id
     */
    private $source_header_id;

    private $order_mode;

    private $purchase_pickup_date;

    private $purchase_receive_date;

    private $pickup_method;

    private $seller_id;

    private $truck_number;

    private $purchase_status;

    private $purchase_memo;

    private $list_is_printed;

    private $voucher_is_printed;

    private $address_id;

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