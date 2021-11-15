<?php

namespace Yzc;


class SysReceiveLine
{

    /**
     * 自增主键
     * @var $id
     */
    private $id;

    /**
     * tb_sys_receive.id
     * @var $receive_id
     */
    private $receive_id;

    /**
     * tb_sys_receive.buyer_id
     * @var $buyer_id
     */
    private $buyer_id;

    /**
     * OC_ORDER.order_id
     * @var $oc_order_id
     */
    private $oc_order_id;

    /**
     * oc_customerpartner_to_order.id
     * @var $oc_partner_order_id
     */
    private $oc_partner_order_id;

    /**
     * @var $source_code
     */
    private $source_code;

    /**
     * 交易类型：1表示销售订单
     * @var $transaction_type
     */
    private $transaction_type;

    /**
     * oc_customerpartner_to_order.product_id
     * @var $product_id
     */
    private $product_id;

    /**
     * oc_customerpartner_to_order.quantity
     * @var $receive_qty
     */
    private $receive_qty;

    /**
     * oc_customerpartner_to_order.price
     * @var $unit_price
     */
    private $unit_price;

    /**
     * @var $tax
     */
    private $tax;

    /**
     * @var
     */
    private $wh_id;

    /**
     * SellerId
     * @var $seller_id
     */
    private $seller_id;
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

    // 构造器
    public function __construct()
    {
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