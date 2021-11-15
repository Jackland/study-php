<?php

namespace App\Models\Setting;

use App\Enums\Message\MessageTaskMsgType;
use App\Models\Customer\Customer;
use Framework\Model\EloquentModel;

/**
 * App\Models\Setting\MessageSetting
 *
 * @property int $id
 * @property int $customer_id
 * @property string $setting 站内信弹窗提醒设置
 * @property string $email_setting 站内信邮件通知设置
 * @property string $other_email 其他邮箱
 * @property int $is_in_seller_recommend 是否参与系统推荐
 * @property string $create_time 创建时间
 * @property-read \App\Models\Customer\Customer $customer
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Setting\MessageSetting newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Setting\MessageSetting newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Setting\MessageSetting query()
 * @mixin \Eloquent
 */
class MessageSetting extends EloquentModel
{
    protected $table = 'oc_message_setting';

    protected $fillable = [
        'customer_id',
        'setting',
        'email_setting',
        'other_email',
        'is_in_seller_recommend',
        'create_time',
    ];

    public function customer()
    {
        return $this->hasOne(Customer::class, 'customer_id', 'customer_id');
    }
}
