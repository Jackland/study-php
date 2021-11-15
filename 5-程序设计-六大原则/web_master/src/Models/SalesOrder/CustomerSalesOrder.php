<?php

namespace App\Models\SalesOrder;

use App\Enums\FeeOrder\FeeOrderFeeType;
use App\Enums\SalesOrder\CustomerSalesOrderLineItemStatus;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Buyer\Buyer;
use App\Models\Customer\Customer;
use App\Models\Link\OrderAssociated;
use App\Models\Safeguard\SafeguardSalesOrderErrorLog;
use Framework\Model\EloquentModel;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use Illuminate\Database\Eloquent\Collection;

/**
 * App\Models\SalesOrder\CustomerSalesOrder
 *
 * @property int $id 自增主键
 * @property string $yzc_order_id 云资产订单ID
 * @property string $order_id 订单ID(csv文件导入列内容)
 * @property string $order_date 订单日期
 * @property string|null $email 顾客邮箱地址
 * @property string $ship_name 收货人姓名
 * @property string $ship_address1 收货地址1
 * @property string|null $ship_address2 收货地址2
 * @property string $ship_city 收货城市
 * @property string $ship_state 收货州
 * @property string $ship_zip_code 收货邮编
 * @property string|null $ship_country 收货国家
 * @property string|null $ship_phone 收货人电话
 * @property string|null $ship_method 发货方式
 * @property string|null $ship_service_level 快递服务
 * @property string|null $ship_company 运输公司
 * @property string|null $bill_name 付款人
 * @property string|null $bill_address 付款人地址
 * @property string|null $bill_city 付款人城市
 * @property string|null $bill_state 付款人州
 * @property string|null $bill_zip_code 付款人邮编
 * @property string|null $bill_country 付款人国家
 * @property string|null $orders_from 销售渠道
 * @property string|null $discount_amount 总折扣
 * @property string|null $tax_amount 总税费
 * @property string|null $order_total 订单总价
 * @property string|null $payment_method 支付方式
 * @property string $store_name OMD店铺名称
 * @property int $store_id OMD店铺ID
 * @property int $buyer_id BuyerID
 * @property int $line_count 明细条数记录
 * @property string|null $customer_comments 顾客的备注
 * @property int $update_temp_id 临时表ID记录字段
 * @property string $run_id 导入时分秒时间
 * @property string $order_status 订单的状态 1 new order （销售单和采购单暂未发货） 2 BP （销售单和采购单绑定）
 * @property int $order_mode 订单模式（区分Flatfair和普通模式）
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property string|null $create_time 创建时间
 * @property string|null $update_user_name 更新者
 * @property string|null $update_time 更新时间
 * @property string|null $program_code 程序号
 * @property string|null $sell_manager 销售经理姓名
 * @property int|null $auto_buy_result 自动购买结果码。0:成功;1:获取token失败;2:添加购物车失败;3:下单购买失败;4：校验库存失败，库存不足购买失败;5:重新购买扣减库存时，发生异常
 * @property int|null $error_type 自动购买流程是否需要后续重试操作的结果码。0:完成，不需要重试;1:需要重新购买;2:需要重新组织结果发送给OMD;
 * @property int|null $ltl_process_status 超大件处理标志
 * @property string|null $ltl_process_time 超大件订单处理时间
 * @property string|null $shipped_date 只有日本国别账号显示此字段 希望到货日期
 * @property int|null $external_store_id 取自自动购买订单临时表的buyerid字段，表示订单在OMD或者在库系统的店铺id
 * @property string|null $external_store_name 取自自动购买订单临时表的external_store_name字段，表示订单在OMD或者在库系统的店铺名称
 * @property int|null $import_mode 0 普通订单 4 dropship 5 wayfair 0 will call 下的普通模式
 * @property string|null $bol_path amazon,wayfair,walmart bol 文件路径
 * @property string|null $bol_create_time amazon,wayfair,walmart bol 创建时间
 * @property int|null $bol_create_id amazon,wayfair,walmart bol 创建人
 * @property int $sales_agent_status 代运营业务订单处理状态，0为待处理，1为待购买，2为待入库，3为已收货
 * @property string|null $sales_agent_update_user 最新一次更新代运营订单进度的帐号
 * @property string|null $sales_agent_update_time 最新一次更新代运营订单进度的时间
 * @property int|null $logistics_order_calculate_status 纯物流订单重新计算费用标志： 1 需要重新计算 0 不需要重新计算
 * @property int $address_tips 销售订单地址提示
 * @property string|null $platform_store_id 销售平台店铺ID
 * @property int|null $address_change_tips 更改地址有问题未确认0已确认1默认null
 * @property int|null $is_car_hire 0 未约车 1已约车
 * @property int|null $is_international 是否为国际单，0：非国际单，1：国际单
 * @property \Illuminate\Database\Eloquent\Collection|CustomerSalesOrderLine[] $lines 库存绑定关系
 * @property CustomerSalesOrderFile $file
 * @property \Illuminate\Database\Eloquent\Collection|OrderAssociated[] $orderAssociates 库存绑定关系
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesOrder newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesOrder newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SalesOrder\CustomerSalesOrder query()
 * @mixin \Eloquent
 * @property-read int|null $lines_count
 * @property-read int|null $order_associates_count
 * @property bool|null $zy_download_flag 是否下载 0 未下载 1 已下载
 * @property string|null $zy_download_user 下载账号
 * @property string|null $zy_download_time 下载时间
 * @property bool|null $delivery_to_fba 是否送货到FBA仓库(欧洲以及日本FBA送仓) 0:否,1:是
 * @property string|null $sales_chanel 销售平台
 * @property string|null $pay_account_number 付款账号
 * @property bool|null $import_type 导入类型 0：手动导入Import 1: API推单API
 * @property bool|null $buy_lable_flag 自动购买lable 0不需要，1需要
 * @property string|null $ship_state_name 收货州name
 * @property string|null $bill_state_name 付款人州name
 * @property-read \App\Models\Buyer\Buyer $buyer
 * @property-read \App\Models\Customer\Customer $buyerCustomer
 * @property-read \App\Models\SalesOrder\CustomerSalesOrderPickUp $pickUp
 * @property-read SafeguardSalesOrderErrorLog[]|Collection $safeguardSalesOrderErrorLog
 * @property string $delivery_address_info
 * @property string $order_status_show
 * @property int|null $attach_id 附件ID 对应tb_file_upload_detail menu_id 字段
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\FeeOrder\FeeOrder[] $feeOrders
 * @property-read int|null $fee_orders_count
 * @property-read int|null $safeguard_sales_order_error_log_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\SalesOrder\CustomerSalesOrderLine[] $linesNoDelete
 * @property-read int|null $lines_no_delete_count
 * @property string $to_be_paid_time 订单状态变为to be paid时间
 */
class CustomerSalesOrder extends EloquentModel
{
    public const CREATED_AT = 'create_time';
    public const UPDATED_AT = 'update_time';
    protected $table = 'tb_sys_customer_sales_order';

    protected $appends = [
        'delivery_address_info',
        'order_status_show',
        'ship_address1',
        'ship_name',
        'email',
        'ship_phone',
        'ship_city',
        'bill_address',
        'bill_name',
        'bill_city',
    ];

    protected $fillable = [
        'yzc_order_id',
        'order_id',
        'order_date',
        'email',
        'ship_name',
        'ship_address1',
        'ship_address2',
        'ship_city',
        'ship_state',
        'ship_zip_code',
        'ship_country',
        'ship_phone',
        'ship_method',
        'ship_service_level',
        'ship_company',
        'bill_name',
        'bill_address',
        'bill_city',
        'bill_state',
        'bill_zip_code',
        'bill_country',
        'orders_from',
        'discount_amount',
        'tax_amount',
        'order_total',
        'payment_method',
        'store_name',
        'store_id',
        'buyer_id',
        'line_count',
        'customer_comments',
        'update_temp_id',
        'run_id',
        'order_status',
        'order_mode',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
        'sell_manager',
        'auto_buy_result',
        'error_type',
        'ltl_process_status',
        'ltl_process_time',
        'shipped_date',
        'external_store_id',
        'external_store_name',
        'import_mode',
        'bol_path',
        'bol_create_time',
        'bol_create_id',
        'sales_agent_status',
        'sales_agent_update_user',
        'sales_agent_update_time',
        'logistics_order_calculate_status',
        'address_tips',
        'platform_store_id',
        'address_change_tips',
        'is_car_hire',
        'is_international',
    ];

    public $timestamps = [
        'to_be_paid_time'
    ];

    public function buyer()
    {
        return $this->belongsTo(Customer::class, 'buyer_id', 'customer_id');
    }

    public function buyerCustomer()
    {
        return $this->hasOne(Customer::class, 'customer_id', 'buyer_id');
    }

    public function lines()
    {
        return $this->hasMany(CustomerSalesOrderLine::class, 'header_id');
    }

    public function linesNoDelete()
    {
        return $this->hasMany(CustomerSalesOrderLine::class, 'header_id')
            ->where('item_status', '!=', CustomerSalesOrderLineItemStatus::DELETED);
    }

    public function file()
    {
        return $this->hasOne(CustomerSalesOrderFile::class, 'order_id');
    }

    public function orderAssociates()
    {
        return $this->hasMany(OrderAssociated::class, 'sales_order_id');
    }

    public function feeOrders()
    {
        return $this->hasMany(FeeOrder::class, 'order_id')->where('order_type', FeeOrderFeeType::STORAGE);
    }

    /**
     * 购买保障服务错误日志
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function safeguardSalesOrderErrorLog()
    {
        return $this->hasMany(SafeguardSalesOrderErrorLog::class, 'sales_order_id');
    }

    public function pickUp()
    {
        return $this->hasOne(CustomerSalesOrderPickUp::class, 'sales_order_id');
    }

    public function getOrderStatusShowAttribute()
    {
        if (isset($this->attributes['order_status'])) {
            return CustomerSalesOrderStatus::getDescription($this->attributes['order_status'],'Unknown');
        }

        return 'Unknown';
    }

    //组织收货信息，有的地址不存在，逐个判断
    public function getDeliveryAddressInfoAttribute()
    {
        $deliveryAddressInfo = $this->attributes['ship_name'] ?? '';

        if (isset($this->attributes['ship_phone']) && !empty($this->attributes['ship_phone'])) {
            $deliveryAddressInfo .= ",{$this->attributes['ship_phone']}";
        }
        if (isset($this->attributes['ship_address1']) && !empty($this->attributes['ship_address1'])) {
            $deliveryAddressInfo .= ",{$this->attributes['ship_address1']}";
        }
        if (isset($this->attributes['ship_address2']) && !empty($this->attributes['ship_address2'])) {
            $deliveryAddressInfo .= ",{$this->attributes['ship_address2']}";
        }
        if (isset($this->attributes['ship_city']) && !empty($this->attributes['ship_city'])) {
            $deliveryAddressInfo .= ",{$this->attributes['ship_city']}";
        }
        if (isset($this->attributes['ship_state']) && !empty($this->attributes['ship_state'])) {
            $deliveryAddressInfo .= ",{$this->attributes['ship_state']}";
        }
        if (isset($this->attributes['ship_zip_code']) && !empty($this->attributes['ship_zip_code'])) {
            $deliveryAddressInfo .= ",{$this->attributes['ship_zip_code']}";
        }
        if (isset($this->attributes['ship_country']) && !empty($this->attributes['ship_country'])) {
            $deliveryAddressInfo .= ",{$this->attributes['ship_country']}";
        }

        return $deliveryAddressInfo;
    }

    public function getShipAddress1Attribute()
    {
        return app('db-aes')->decrypt($this->attributes['ship_address1'] ?? null);
    }

    public function getShipAddress2Attribute()
    {
        return app('db-aes')->decrypt($this->attributes['ship_address2'] ?? null);
    }

    public function getShipNameAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['ship_name'] ?? null);
    }

    public function getEmailAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['email'] ?? null);
    }

    public function getShipCityAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['ship_city'] ?? null);
    }

    public function getShipPhoneAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['ship_phone'] ?? null);
    }

    public function getBillAddressAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['bill_address'] ?? null);
    }

    public function getBillNameAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['bill_name'] ?? null);
    }

    public function getBillCityAttribute()
    {
        return app('db-aes')->decrypt($this->attributes['bill_city'] ?? null);
    }
}
