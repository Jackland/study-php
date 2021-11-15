<?php

namespace App\Models\SpecialFee;

use Framework\Model\EloquentModel;

/**
 * App\Models\SpecialServiceFee
 *
 * @property int $id 主键ID
 * @property int|null $customer_id 供应商客户号
 * @property \Illuminate\Support\Carbon|null $accounting_cycle_start 结算周期开始时间
 * @property \Illuminate\Support\Carbon|null $accounting_cycle_end 结算周期结束时间
 * @property int|null $service_fee_category 特殊费用分类
 * @property int|null $service_project 服务项目
 * @property string|null $fee_number 费用编号
 * @property string|null $amount_collected 特殊费用总金额
 * @property string|null $annex1 附件1文件名
 * @property string|null $annex_path1 附件1服务器文件名
 * @property string|null $annex2 附件2文件名
 * @property string|null $annex_path2 附件2服务器文件名
 * @property string|null $annex3 附件3文件名
 * @property string|null $annex_path3 附件3服务器文件名
 * @property string|null $annex4 附件4文件名
 * @property string|null $annex_path4 附件4服务器文件名
 * @property string|null $annex5 附件5文件名
 * @property string|null $annex_path5 附件5服务器文件名
 * @property string|null $charge_detail 收费明细
 * @property string|null $remark 特殊费用备注
 * @property string|null $enter_user_name 录入人
 * @property \Illuminate\Support\Carbon|null $enter_time 录入时间
 * @property string|null $file_ids
 * @property string|null $memo 备注
 * @property string|null $create_user_name 创建者
 * @property \Illuminate\Support\Carbon|null $create_time 创建时间
 * @property string|null $update_user_name 修改者
 * @property \Illuminate\Support\Carbon|null $update_time 修改时间
 * @property string|null $program_code 程序号
 * @property string|null $company_code 货代公司code
 * @property string|null $receive_number 入库单号
 * @property string|null $rate_number 税单号
 * @property int|null $receive_order_id 入库单头表
 * @property float|null $currency_rate 兑换美元的汇率
 * @property string|null $expense_number 关联的附件编号
 * @property int|null $annexl_menu_id 附件文件menu_id
 * @property int|null $inventory_id Seller库存调整主键
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SpecialFee\SpecialServiceFee newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SpecialFee\SpecialServiceFee newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\SpecialFee\SpecialServiceFee query()
 * @mixin \Eloquent
 */
class SpecialServiceFee extends EloquentModel
{
    protected $table = 'tb_special_service_fee';

    protected $dates = [
        'accounting_cycle_start',
        'accounting_cycle_end',
        'enter_time',
        'create_time',
        'update_time',
    ];

    protected $fillable = [
        'customer_id',
        'accounting_cycle_start',
        'accounting_cycle_end',
        'service_fee_category',
        'service_project',
        'fee_number',
        'amount_collected',
        'annex1',
        'annex_path1',
        'annex2',
        'annex_path2',
        'annex3',
        'annex_path3',
        'annex4',
        'annex_path4',
        'annex5',
        'annex_path5',
        'charge_detail',
        'remark',
        'enter_user_name',
        'enter_time',
        'file_ids',
        'memo',
        'create_user_name',
        'create_time',
        'update_user_name',
        'update_time',
        'program_code',
        'company_code',
        'receive_number',
        'rate_number',
        'receive_order_id',
    ];
}
