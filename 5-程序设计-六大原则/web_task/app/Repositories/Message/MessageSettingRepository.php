<?php

namespace App\Repositories\Message;

use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Message\MessageSettting;
use App\Models\Setting;

class MessageSettingRepository
{
    private $defaultEmailSetting;

    public function __construct()
    {
        $this->defaultEmailSetting = Setting::getConfig('default_email_setting');
    }

    /**
     * 格式化 email_setting 字段
     * @param string|null $emailSetting
     * @param $isSeller
     * @return array
     */
    public function formatEmailSettingAttribute(?string $emailSetting, $isSeller)
    {
        if ($emailSetting) {
            return json_decode($emailSetting, true);
        }
        $default = json_decode($this->defaultEmailSetting, true) ?: [];
        if ($isSeller) {
            $default['platform']['policy'] = 0;
            $default['platform']['product'] = 0;
            $default['platform']['system'] = 0;
            $default['sendmail']['default_email'] = 0;
        }
        return $default;
    }

    /**
     * 格式化 other_email 字段
     * @param string|null $otherEmail
     * @return array
     */
    public function formatOtherEmailAttribute(?string $otherEmail)
    {
        if ($otherEmail) {
            return json_decode($otherEmail, true) ?: [];
        }
        return [];
    }

    /**
     * 根据 customerIds 获取邮件设置信息(other_email, email_setting)
     * @param array $customerIds
     * @return array
     */
    public function getEmailSettingByCustomerIds(array $customerIds): array
    {
        $customerIdSettingMap = MessageSettting::query()->whereIn('customer_id', $customerIds)->get()->keyBy('customer_id');
        $sellerCustomerIds = CustomerPartnerToCustomer::query()->whereIn('customer_id', $customerIds)->pluck('customer_id')->toArray();

        $customerIdEmailSettingMap = [];
        foreach ($customerIds as $customerId) {
            /** @var MessageSettting $model */
            $model = $customerIdSettingMap->get($customerId);
            if (is_null($model)) {
                $customerIdEmailSettingMap[$customerId]['email_setting'] = $this->formatEmailSettingAttribute(null, in_array($customerId, $sellerCustomerIds));
                $customerIdEmailSettingMap[$customerId]['other_email'] = $this->formatOtherEmailAttribute(null);
            } else {
                $customerIdEmailSettingMap[$customerId]['email_setting'] = $this->formatEmailSettingAttribute($model->email_setting, in_array($customerId, $sellerCustomerIds));
                $customerIdEmailSettingMap[$customerId]['other_email'] = $this->formatOtherEmailAttribute($model->other_email);
            }
        }

        return $customerIdEmailSettingMap;
    }
}
