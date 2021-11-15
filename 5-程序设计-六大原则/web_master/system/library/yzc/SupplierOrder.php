<?php

namespace Yzc;
class SupplierOrder
{
    /**
     * 自增主键
     * @var $id
     */
    private $id;

    /**
     * oc_order.order_id
     * @var $source_header_id
     */
    private $source_header_id;

    /**
     * 订单模式
     * @var $order_mode
     */
    private $order_mode;

    /**
     * BuyerId
     * @var $buyer_id
     */
    private $buyer_id;

    /**
     * SellerId
     * @var $seller_id
     */
    private $seller_id;

    /**
     * 供应商订单号
     * @var $supplier_order_id
     */
    private $supplier_order_id;

    /**
     * 在供应商网站上的下单时间
     * @var $purchase_date
     */
    private $purchase_date;

    /**
     * 订单总金额
     * @var $purchase_total
     */
    private $purchase_total;

    /**
     * 订单发票总金额
     * @var $purchase_invoice_total
     */
    private $purchase_invoice_total;

    /**
     * 订单取货凭证文件URL
     * @var $purchase_file_url
     */
    private $purchase_file_url;

    /**
     * 订单取货凭证文件存放路径
     * @var $purchase_file_path
     */
    private $purchase_file_path;

    /**
     * 订单状态
     * @var $purchase_status
     */
    private $purchase_status;

    /**
     * 订单取货时间
     * @var $purchase_pickup_date
     */
    private $purchase_pickup_date;

    /**
     * 订单收货时间
     * @var $purchase_receive_date
     */
    private $purchase_receive_date;

    /**
     * 取货方式
     * @var $pickup_method
     */
    private $pickup_method;

    /**
     * 卡车编号
     * @var $truck_number
     */
    private $truck_number;

    /**
     * 取货地址ID
     * @var $address_id
     */
    private $address_id;

    /**
     * 备注
     * @var $memo
     */
    private $memo;

    /**
     * 创建者
     * @var $create_user_name
     */
    private $create_user_name;

    /**
     * 创建时间
     * @var $create_time
     */
    private $create_time;

    /**
     * 更新者
     * @var $update_user_name
     */
    private $update_user_name;

    /**
     * 更新时间
     * @var $update_time
     */
    private $update_time;

    /**
     * 程序号
     * @var $program_code
     */
    private $program_code;

    private $supplier_order_lines = array();



    public function __get($name)
    {
        return $this->$name;
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
    }
}