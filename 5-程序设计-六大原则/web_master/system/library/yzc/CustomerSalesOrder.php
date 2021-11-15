<?php

namespace Yzc;
use App\Enums\SalesOrder\CustomerSalesOrderMode;

class CustomerSalesOrder
{
    /**
     * 自增主键
     * @var $id
     */
    private $id;

    /**
     * 云资产订单ID
     * @var $yzc_order_id
     */
    private $yzc_order_id;

    /**
     * 订单ID(csv文件导入列内容)
     * @var $order_id
     */
    private $order_id;

    /**
     * 订单日期
     * @var $order_date
     */
    private $order_date;

    /**
     * 邮箱地址
     * @var $email
     */
    private $email;

    /**
     * 收货人姓名
     * @var $ship_name
     */
    private $ship_name;

    /**
     * 收货地址1
     * @var $ship_address1
     */
    private $ship_address1;

    /**
     * 收货地址2
     * @var $ship_address2
     */
    private $ship_address2;

    /**
     * 收货城市
     * @var $ship_city
     */
    private $ship_city;

    /**
     * 收货州
     * @var $ship_state
     */
    private $ship_state;

    /**
     * 收货邮编
     * @var $ship_zip_code
     */
    private $ship_zip_code;

    /**
     * 收货国家
     * @var $ship_country
     */
    private $ship_country;

    /**
     * 收货人电话
     * @var $ship_phone
     */
    private $ship_phone;

    /**
     * 发货方式
     * @var $ship_method
     */
    private $ship_method;
    /**
     * @var $ship_date
     */
    private $shipped_date;

    /**
     * 快递服务
     * @var $ship_service_level
     */
    private $ship_service_level;

    /**
     * 到货公司
     * @var $ship_company
     */
    private $ship_company;

    /**
     * 付款人
     * @var $bill_name
     */
    private $bill_name;

    /**
     * 付款人地址
     * @var $bill_address
     */
    private $bill_address;

    /**
     * 付款人城市
     * @var $bill_city
     */
    private $bill_city;

    /**
     * 付款人州
     * @var $bill_state
     */
    private $bill_state;

    /**
     * 付款人邮编
     * @var $bill_zip_code
     */
    private $bill_zip_code;

    /**
     * 付款人国籍
     * @var $bill_country
     */
    private $bill_country;

    /**
     * 销售渠道
     * @var $orders_from
     */
    private $orders_from;

    /**
     * 总折扣
     * @var $discount_amount
     */
    private $discount_amount;

    /**
     * 总税费
     * @var $tax_amount
     */
    private $tax_amount;

    /**
     * 订单总价
     * @var $order_total
     */
    private $order_total;

    /**
     * 支付方式
     * @var $payment_method
     */
    private $payment_method;

    /**
     * OMD店铺名称
     * @var $store_name
     */
    private $store_name;

    /**
     * OMD店铺ID
     * @var $store_id
     */
    private $store_id;

    /**
     * BuyerId
     * @var $buyer_id
     */
    private $buyer_id;

    /**
     * 明细条数记录
     * @var $line_count
     */
    private $line_count;

    /**
     * 顾客的备注
     * @var $customer_comments
     */
    private $customer_comments;

    /**
     * 临时表ID记录字段
     * @var $update_temp_id
     */
    private $update_temp_id;

    /**
     * 导入时分秒时间
     * @var $run_id
     */
    private $run_id;

    /**
     * 订单状态
     * @var $order_status
     */
    private $order_status;

    /**
     * 订单模式
     * @var $order_mode
     */
    private $order_mode;

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

    private $customer_sales_order_lines = array();

    // 构造器
    public function __construct(array $orderTemp, $order_mode = CustomerSalesOrderMode::DROP_SHIPPING)
    {
        $this->order_id = $orderTemp['order_id'];
        $this->order_date = $orderTemp['order_date'];
        $this->email = $orderTemp['email'];
        $this->ship_name = $orderTemp['ship_name'];
        $this->shipped_date = $orderTemp['shipped_date'];
        $this->ship_address1 = $orderTemp['ship_address1'];
        $this->ship_address2 = $orderTemp['ship_address2'];
        $this->ship_city = $orderTemp['ship_city'];
        $this->ship_state = $orderTemp['ship_state'];
        $this->ship_zip_code = $orderTemp['ship_zip_code'];
        $this->ship_country = $orderTemp['ship_country'];
        $this->ship_phone = $orderTemp['ship_phone'];
        $this->ship_method = $orderTemp['ship_method'];
        $this->ship_service_level = $orderTemp['ship_service_level'];
        $this->ship_company = $orderTemp['ship_company'];
        $this->bill_name = $orderTemp['bill_name'];
        $this->bill_address = $orderTemp['bill_address'];
        $this->bill_city = $orderTemp['bill_city'];
        $this->bill_state = $orderTemp['bill_state'];
        $this->bill_zip_code = $orderTemp['bill_zip_code'];
        $this->bill_country = $orderTemp['bill_country'];
        $this->orders_from = $orderTemp['orders_from'];
        $this->discount_amount = $orderTemp['discount_amount'];
        $this->tax_amount = $orderTemp['tax_amount'];
        $this->order_total = $orderTemp['order_total'];
        $this->payment_method = $orderTemp['payment_method'];
        $this->store_name = 'yzc';
        $this->store_id = 888;
        $this->buyer_id = $orderTemp['buyer_id'];
        $this->customer_comments = $orderTemp['customer_comments'];
        $this->run_id = $orderTemp['run_id'];
        $this->order_status = 1;
        $this->order_mode = $order_mode;
        $this->create_user_name = $orderTemp['create_user_name'];
        $this->create_time = $orderTemp['create_time'];
        $this->program_code = $orderTemp['program_code'];
    }

    public function __get($name)
    {
        return $this->$name;
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
    }
}
