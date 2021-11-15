<?php

namespace App\Models\SellerBill;

use App\Models\Product\Product;
use Framework\Model\EloquentModel;

/**
 * App\Models\SellerBill\SellerBillDetail
 *
 * @property int $id 自增主键
 * @property int|null $header_id 头表ID
 * @property int|null $seller_id SellerId
 * @property int|null $bill_type_id 结款类型
 * @property int|null $type 类型 0：订单（议价）【采购订单页面】\r\n1：重发【RMA 页面】\r\n2：订单返金【RMA 页面】\r\n3：取消重发返金【RMA 页面】\r\n4：返点返金 【特殊服务费用录入 关联】\r\n5：仓储费\r\n6：其他
 * @property string $total 总金额
 * @property string|null $freight 运费
 * @property string|null $service_fee 服务费
 * @property string|null $package_fee 打包费
 * @property string $coupon_amount 优惠券折扣
 * @property string $campaign_amount 活动满减金额
 * @property string|null $campaign_id 促销活动编号，主键ID，以逗号分隔
 * @property int|null $quantity 相关业务的商品数量
 * @property string|null $platform_fee 平台费
 * @property int|null $order_id 订单ID
 * @property int|null $rma_id RMA ID
 * @property int|null $special_id 特殊费用ID
 * @property int $pay_record_id oc_futures_margin_pay_record.id:期货保证金seller账单ID
 * @property int|null $product_id 产品ID
 * @property string|null $item_code ItemCode
 * @property int|null $is_margin 是否是保证金产品
 * @property string|null $agreement_id 保证金协议编号
 * @property string|null $future_agreement_no 期货保证金协议编号
 * @property int|null $future_margin_id 期货保证金协议主键ID
 * @property int|null $future_contract_id oc_futures_contract.id合约ID
 * @property int|null $rebate_id 返点协议主键ID
 * @property string|null $apInvoice_num 发票编号，用于财务系统同步
 * @property \Illuminate\Support\Carbon|null $produce_date 发生时间
 * @property int|null $file_menu_id 原始附件文件menuId
 * @property int $frozen_flag 金额冻结状态  无需冻结:0  已冻结:1  已解冻:2
 * @property \Illuminate\Support\Carbon|null $frozen_date 冻结时间
 * @property int|null $order_settle_type 订单分类的交易结算及解冻类型 tb_seller_bill_order_settle_type.id
 * @property \Illuminate\Support\Carbon|null $frozen_release_date 解冻时间
 * @property string|null $remark 账单项备注
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillDetail newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillDetail newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SellerBill\SellerBillDetail query()
 * @mixin \Eloquent
 * @property bool $frozen_flag 金额冻结状态  无需冻结:0  已冻结:1  已解冻:2
 * @property string|null $frozen_date 冻结时间
 * @property int|null $order_settle_type 订单分类的交易结算及解冻类型 tb_seller_bill_order_settle_type.id
 * @property string|null $frozen_release_date 解冻时间
 */
class SellerBillDetail extends EloquentModel
{
    protected $table = 'tb_seller_bill_detail';

    protected $dates = [
        'produce_date',
        'frozen_date',
        'frozen_release_date',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'header_id',
        'seller_id',
        'bill_type_id',
        'type',
        'total',
        'freight',
        'service_fee',
        'package_fee',
        'coupon_amount',
        'campaign_amount',
        'campaign_id',
        'quantity',
        'platform_fee',
        'order_id',
        'rma_id',
        'special_id',
        'pay_record_id',
        'product_id',
        'item_code',
        'is_margin',
        'agreement_id',
        'future_agreement_no',
        'future_margin_id',
        'future_contract_id',
        'rebate_id',
        'apInvoice_num',
        'produce_date',
        'file_menu_id',
        'frozen_flag',
        'frozen_date',
        'order_settle_type',
        'frozen_release_date',
        'remark',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function sellerBill()
    {
        return $this->belongsTo(SellerBill::class, 'header_id');
    }

    public function  frozen()
    {
        return $this->hasOne(SellerBillFrozen::class, 'bill_detail_id');
    }
}
