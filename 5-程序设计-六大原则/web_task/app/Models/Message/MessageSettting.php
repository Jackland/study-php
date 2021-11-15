<?php

namespace App\Models\Message;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $customer_id
 * @property string $setting 站内信弹窗提醒设置
 * @property string $email_setting 站内信邮件通知设置
 * @property string $other_email 其他邮箱
 * Class MessageSettting
 * @package App\Models\Message
 */
class MessageSettting extends Model
{
    protected $table = 'oc_message_setting';
    protected $connection = 'mysql_proxy';

    /**
     * 获取站内信弹窗设置
     * @param $customerId
     * @return array|mixed
     */
    public static function getMessageSettingByCustomerId($customerId)
    {
        $setting = self::where('customer_id', $customerId)
            ->value('setting');
        if ($setting) {
            $setting = json_decode($setting, true);
            if (!isset($setting['intervalTime'])) {
                $setting['intervalTime'] = 900;
            }
            return $setting;
        } else {
            // 设置默认值
            return ['store' => 1, 'system' => 1, 'platformNotice' => 1, 'intervalTime' => 900];
        }
    }


    /**
     * 获取站内信邮箱提醒
     * @param $customerId
     * @return array|mixed
     */
    public static function getMessageEmailSettingByCustomerId($customerId)
    {
        $setting = self::select(['email_setting', 'other_email'])
            ->where('customer_id', $customerId)
            ->first();
        $data = [];
        if ($setting) {
            $data['email_setting'] = json_decode($setting->email_setting, true);
            $data['other_email'] = json_decode($setting->other_email, true);
        } else {
            // 获取默认值
            $data['email_setting'] = json_decode(Setting::getConfig('default_email_setting'), true);
            $data['other_email'] = [];
        }
        return $data;
    }
}
