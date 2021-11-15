<?php

namespace App\Repositories\Setting;

use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Setting\MessageSetting;

class MessageSettingRepository
{
    /**
     * 根据 customer 获取设置信息
     * @param int|null $customerId 为 null 时将返回默认配置
     * @param array $format [需要格式化的键 => 格式化后保存的键]
     * @param bool $newIfNotExist 不存在时new一个
     * @return MessageSetting|null
     */
    public function getByCustomerId(?int $customerId, $format = [], $newIfNotExist = true)
    {
        if ($customerId) {
            $query = MessageSetting::query()->where('customer_id', $customerId);
            $model = $newIfNotExist ? $query->firstOrNew([]) : $query->first();
        } else {
            $model = new MessageSetting();
        }
        if ($model && !$model->exists) {
            // new 出来的部分数据进行初始化
            $model->is_in_seller_recommend = 1;
            $model->setting = json_encode($this->formatSettingAttribute(null));
            $model->email_setting = json_encode($this->formatEmailSettingAttribute(null, customer()->isPartner()));
            $model->other_email = json_encode($this->formatOtherEmailAttribute(null));
        }
        if ($model && $format) {
            if (array_key_exists('setting', $format)) {
                $model[$format['setting']] = $this->formatSettingAttribute($model->setting);
            }
            if (array_key_exists('email_setting', $format)) {
                $model[$format['email_setting']] = $this->formatEmailSettingAttribute($model->email_setting, customer()->isPartner());
            }
            if (array_key_exists('other_email', $format)) {
                $model[$format['other_email']] = $this->formatOtherEmailAttribute($model->other_email);
            }
        }
        return $model;
    }

    /**
     * 格式化 setting 字段
     * @param string|null $setting
     * @return array
     */
    public function formatSettingAttribute(?string $setting)
    {
        $result = ['store' => 1, 'system' => 0, 'platformNotice' => 0, 'station_letter' => 1, 'intervalTime' => 900];
        if ($setting) {
            $result = array_merge($result, json_decode($setting, true) ?: []);
        }
        return $result;
    }

    /**
     * 格式化 email_setting 字段
     * @param string|null $emailSetting
     * @param bool $isSeller
     * @return array
     */
    public function formatEmailSettingAttribute(?string $emailSetting, $isSeller)
    {
        $default = json_decode(configDB('default_email_setting'), true) ?: [];
        if ($emailSetting) {
            $setting = json_decode($emailSetting, true);
            // 遍历默认数据，如果用户设置中没有包含默认的设置，则将默认值放入返回数据中
            foreach ($default as $defaultKey => $defaultItem) {
                if (!array_key_exists($defaultKey, $setting)) {
                    // 大分类不存在
                    $setting[$defaultKey] = $defaultItem;
                    continue;
                }
                // 如果大分类是数组，继续遍历子项
                if (is_array($defaultItem)) {
                    // 如果是数组再遍历里面的item是否存在
                    foreach ($defaultItem as $itemKey => $itemValue) {
                        if (!array_key_exists($itemKey, $setting[$defaultKey])) {
                            // 子项内不存在，则放入默认值
                            $setting[$defaultKey][$itemKey] = $itemValue;
                        }
                    }
                }
            }
            return $setting;
        }
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
        $customerIdSettingMap = MessageSetting::query()->whereIn('customer_id', $customerIds)->get()->keyBy('customer_id');
        $sellerCustomerIds = CustomerPartnerToCustomer::query()->whereIn('customer_id', $customerIds)->pluck('customer_id')->toArray();

        $customerIdEmailSettingMap = [];
        foreach ($customerIds as $customerId) {
            /** @var MessageSetting $model */
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

    /**
     * description:获取基础配置信息
     * @param int $customerId
     * @param array $field
     * @return object
     */
    public function getMsgSettingByIdData(int $customerId, array $field = ['*'])
    {
        return MessageSetting::query()
            ->select($field)
            ->where('customer_id', $customerId)
            ->first();
    }
}
