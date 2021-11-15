<?php

namespace Yzc;
class CustomerSalesOrderLine
{
    /**
     * 自增主键
     * @var $id
     */
    private $id;

    /**
     * 临时表ID(csv文件导入时temp表主键)
     * @var $temp_id
     */
    private $temp_id;

    /**
     * 订单头表ID(Order表主键)
     * @var $header_id
     */
    private $header_id;

    /**
     * 订单明细编号(明细在同一订单中的编号)
     * @var $line_item_number
     */
    private $line_item_number;

    /**
     * 产品名称
     * @var $product_name
     */
    private $product_name;

    /**
     * 销售数量
     * @var $qty
     */
    private $qty;

    /**
     * 销售单价
     * @var $item_price
     */
    private $item_price;

    /**
     * 单个产品折扣
     * @var $item_unit_discount
     */
    private $item_unit_discount;

    /**
     * 单个产品税费
     * @var $item_tax
     */
    private $item_tax;

    /**
     * ItemCode 对应oc_product mpn 或 sku
     * @var $item_code
     */
    private $item_code;

    /**
     * 发货SkuId(oc_product.product_id)
     * @var $product_id
     */
    private $product_id;

    /**
     * AsinCode
     * @var $alt_item_id
     */
    private $alt_item_id;

    /**
     * 运费
     * @var $ship_amount
     */
    private $ship_amount;

    /**
     * 订单导入时先为制造商id，后为制造商icon id
     * @var $image_id
     */
    private $image_id;
    /**
     * 订单明细的备注
     * @var $line_comments
     */
    private $line_comments;

    /**
     * 卖家Id
     * @var $seller_id
     */
    private $seller_id;

    /**
     * 导入时分秒时间
     * @var $run_id
     */
    private $run_id;

    /**
     * 订单明细的状态
     * @var $item_status
     */
    private $item_status;

    /**
     * 备注
     * @var $memo
     */
    private $memo;

    /**
     * 是否已同步，未同步为null，已同步为1
     * @var $is_exported
     */
    private $is_exported = null;

    /**
     * 同步时间
     * @var $exported_time
     */
    private $exported_time = null;

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

    public function __construct(array $orderTemp)
    {
        $this->temp_id = $orderTemp['id'];
        $this->line_item_number = $orderTemp['line_item_number'];
        $this->product_name = $orderTemp['product_name'];
        $this->qty = $orderTemp['qty'];
        $this->item_price = $orderTemp['item_price'];
        $this->item_unit_discount = $orderTemp['item_unit_discount'];
        $this->item_tax = $orderTemp['item_tax'];
        $this->item_code = $orderTemp['item_code'];
        $this->alt_item_id = $orderTemp['alt_item_id'];
        $this->ship_amount = $orderTemp['ship_amount'];
        $this->line_comments = $orderTemp['customer_comments'];
        $this->image_id = $orderTemp['brand_id'];
        $this->seller_id = $orderTemp['seller_id'];
        $this->run_id = $orderTemp['run_id'];
        $this->item_status = 1;
        $this->create_user_name = $orderTemp['create_user_name'];
        $this->create_time = $orderTemp['create_time'];
        $this->program_code = PROGRAM_CODE;
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