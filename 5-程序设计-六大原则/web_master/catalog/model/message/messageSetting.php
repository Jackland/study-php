<?php

use App\Repositories\Setting\MessageSettingRepository;

/**
 * Class ModelMessageMessageSetting
 */
class ModelMessageMessageSetting extends Model
{
    function saveSetting($customerId, $data)
    {
        $bulid = $this->orm->table(DB_PREFIX . 'message_setting');
        $res = $bulid->where('customer_id', $customerId)
            ->first();
        if ($res) {
            $bulid->where('customer_id', $customerId)
                ->update($data);
            return true;
        } else {
            $data['customer_id'] = $customerId;
            return $bulid->insert($data);
        }
    }


    /**
     * @var array
     */
    private $_messageSettingCache = [];

    protected function getByCustomer($customerId)
    {
        $customerId = $customerId ?: null;
        if (!$customerId || !isset($this->_messageSettingCache[$customerId])) {
            $setting = app(MessageSettingRepository::class)->getByCustomerId($customerId, [
                'setting' => 'setting_formatted',
                'email_setting' => 'email_setting_formatted',
                'other_email' => 'other_email_formatted',
            ], true);
            if (!$customerId) {
                // customerId 为空时不缓存
                return $setting;
            }
            $this->_messageSettingCache[$customerId] = $setting;
        }

        return $this->_messageSettingCache[$customerId];
    }

        /**
     * 获取站内信弹窗设置
     * @param int $customerId
     * @return array|mixed
     * @deprecated 使用 App\Repositories\Setting\MessageSettingRepository 代替查询
     */
    function getMessageSettingByCustomerId($customerId)
    {
        return $this->getByCustomer($customerId)['setting_formatted'];
    }
}
