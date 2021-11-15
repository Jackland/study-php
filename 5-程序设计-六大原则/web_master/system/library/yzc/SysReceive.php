<?php

namespace Yzc;


class SysReceive
{
    /**
     * 自增主键
     * @var $id
     */
    private $id;

    /**
     * BuyerId
     * @var $buyer_id
     */
    private $buyer_id;

    /**
     * oc_order.order_id
     * @var $source_header_id
     */
    private $source_header_id;

    /**
     * 来源说明（默认为null）
     * @var $source_code
     */
    private $source_code;

    /**
     * 交易类型：1：销售订单
     * @var $transaction_type
     */
    private $transaction_type;

    /**
     * OC_ORDER_TOTAL.SubTotal
     * @var $sub_total
     */
    private $sub_total;

    /**
     * Flat Shipping Rate
     * @var $flat_shipping_rate
     */
    private $flat_shipping_rate;

    /**
     * Eco Tax (-2.00)
     * @var $eco_tax
     */
    private $eco_tax;

    /**
     * VAT (20%)
     * @var $vat
     */
    private $vat;

    /**
     * Total
     * @var $total
     */
    private $total;

    /**
     * 仓库ID
     * @var $wh_id;
     */
    private $wh_id;

    /**
     * 明细条数记录
     * @var $line_count
     */
    private $line_count;

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