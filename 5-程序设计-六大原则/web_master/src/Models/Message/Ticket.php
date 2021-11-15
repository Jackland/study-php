<?php

namespace App\Models\Message;

use App\Models\Admin\User;
use Framework\Model\EloquentModel;

/**
 * App\Models\Message\Ticket
 *
 * @property int $id
 * @property string $ticket_id 用户Submit a Ticket后自动生成，yyyyMMdd+六位数（从000001开始自增）
 * @property int $create_customer_id oc_customer表主键
 * @property bool $submit_ticket_for oc_ticket_category表 1:RMA Management, 2:Sales Order Management, 3:Others
 * @property bool $ticket_type oc_ticket_category表 4:RMA Arbitration, 5:Cancel Sales Order, 3:Others
 * @property string $rma_id oc_yzc_rma_order表rma_order_id字段
 * @property int $rma_key oc_yzc_rma_order表主键id
 * @property string $sales_order_id tb_sys_customer_sales_order表order_id字段
 * @property int $sales_order_key tb_sys_customer_sales_order表主键id
 * @property string $sales_item_code tb_sys_customer_sales_order_line表item_code
 * @property string $tracking_number tb_sys_customer_sales_order_tracking表TrackingNumber 用户在这自行输入
 * @property bool $processing_method oc_ticket_category表 6:Apply for RMA, 7:Keep in Stock
 * @property bool $status 1:待领取：用户新提交/新回复的Tickets, 2:待处理：已被人领取，但是还没有进行回复，没有标记处理状态, 3:已处理：已被人领取，且已经进行回复，回复时标记了已处理, 4.处理中：已被人领取，且已经进行回复，但还需进一步回复，回复时标记了处理中, 5.忽略：已被人领取，但没有进行回复，在详情页面标记忽略，表示无需处理
 * @property int $process_admin_id 处理人
 * @property \Illuminate\Support\Carbon $date_added 添加时间
 * @property \Illuminate\Support\Carbon $date_modified 最后更新时间
 * @property bool $customer_is_read 来源于ticket_message表的is_read值
 * @property bool $admin_is_read 来源于ticket_message表的is_read值
 * @property string $role
 * @property bool|null $delay_flag 延时处理标记
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\Ticket newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\Ticket newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\Ticket query()
 * @mixin \Eloquent
 * @property-read \App\Models\Message\Ticket $sysUser 消息对应的处理人
 * @property string $safeguard_claim_no oc_safeguard_claim.claim_no
 * @property string|null $operate_log 操作日志: json格式
 * @property string $safeguard_claim_no oc_safeguard_claim.claim_no
 */
class Ticket extends EloquentModel
{
    protected $table = 'oc_ticket';

    protected $dates = [
        'date_added',
        'date_modified'
    ];

    protected $fillable = [
        'id',
        'ticket_id',
        'create_customer_id',
        'submit_ticket_for',
        'ticket_type',
        'rma_id',
        'rma_key',
        'sales_order_id',
        'sales_order_key',
        'sales_item_code',
        'tracking_number',
        'processing_method',
        'status',
        'process_admin_id',
        'date_added',
        'date_modified',
        'customer_is_read',
        'admin_is_read',
        'role',
        'delay_flag'
    ];

    public function sysUser()
    {
        return $this->belongsTo(User::class, 'process_admin_id');
    }
}
