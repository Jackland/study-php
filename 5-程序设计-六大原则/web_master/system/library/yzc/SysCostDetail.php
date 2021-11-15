<?php

namespace Yzc;


class SysCostDetail
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
     * tb_sys_receive_line.id
     * @var $source_line_id
     */
    private $source_line_id;

    /**
     * 来源说明：NUS表示非美国本土购买
     *          US表示美国本土购买
     * @var $source_code
     */
    private $source_code;

    /**
     * tb_sys_receive_line.product_id
     * @var $sku_id
     */
    private $sku_id;

    /**
     * 起始数量等于OriginalQty，随着库存的不断扣减而发生变化
     * @var $onhand_qty
     */
    private $onhand_qty;

    /**
     * 会计成本(预留字段)
     * @var $unit_cost
     */
    private $unit_cost;

    /**
     * 店铺成本(预留字段)
     * @var $unit_cost_store
     */
    private $unit_cost_store;

    /**
     * 初始入库数量
     * @var $original_qty
     */
    private $original_qty;

    /**
     * 运费(预留字段)
     * @var $freight
     */
    private $freight;

    /**
     * 其他成本(预留字段)
     * @var $other_charge
     */
    private $other_charge;

    /**
     * tb_sys_receive_line.seller_id
     * @var $seller_id
     */
    private $seller_id;

    /**
     * 仓库ID(预留字段)
     * @var $wh_id
     */
    private $wh_id;

    /**
     * 调出批次ID(预留字段)
     * @var $last_cost_id
     */
    private $last_cost_id;

    /**
     * 源批次ID(预留字段)
     * @var $start_cost_id
     */
    private $start_cost_id;

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