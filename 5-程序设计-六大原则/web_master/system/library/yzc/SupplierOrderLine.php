<?php


namespace Yzc;


class SupplierOrderLine
{
    /**
     * 自增主键
     * @var $id
     */
    private $id;

    /**
     * tb_sys_supplier_order.id
     * @var $order_header_id
     */
    private $order_header_id;

    /**
     * oc_order.order_id
     * @var $source_header_id
     */
    private $source_header_id;

    /**
     * oc_customerpartner_to_order.id
     * @var $source_line_id
     */
    private $source_line_id;

    /**
     * oc_product.mpn
     * @var $mpn
     */
    private $mpn;

    /**
     * oc_product.id
     * @var $mpn_id
     */
    private $mpn_id;

    /**
     * item单价
     * @var $item_price
     */
    private $item_price;

    /**
     * 购买的ITEM描述
     * @var $item_description
     */
    private $item_description;

    /**
     * 购买的ITEM数量
     * @var $item_qty
     */
    private $item_qty;

    /**
     * Item状态
     * @var $item_status
     */
    private $item_status;

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
     * Item备注
     * @var $item_memo
     */
    private $item_memo;

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
    public function __construct(array $customerSalesOrderLine)
    {
        $this->mpn = $customerSalesOrderLine['item_code'];
        $this->mpn_id = $customerSalesOrderLine['product_id'];
        $this->item_price = $customerSalesOrderLine['item_price'];
        $this->item_description = $customerSalesOrderLine['product_name'];
        $this->item_qty = $customerSalesOrderLine['qty'];
        $this->item_status = "1";
        $this->item_memo = $customerSalesOrderLine['line_comments'];
        $this->create_user_name = $customerSalesOrderLine['create_user_name'];
        $this->create_time = $customerSalesOrderLine['create_time'];
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