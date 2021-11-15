<?php

namespace App\Repositories\Message;

use App\Enums\Message\MsgType;
use App\Models\Customer\Customer;

class MessageRepository
{
    /**
     * 获取某些用户能发送邮件的邮箱地址
     * @param int $msgType
     * @param array $customerIds
     * @return array
     */
    public function getSendEmails(int $msgType, array $customerIds): array
    {
        $customerIdEmailSettingMap = app(MessageSettingRepository::class)->getEmailSettingByCustomerIds($customerIds);
        $customerIdEmailMap = Customer::query()->whereIn('customer_id', $customerIds)->pluck('email', 'customer_id')->toArray();

        $emails = [];
        foreach ($customerIds as $customerId) {
            $emailSetting = $customerIdEmailSettingMap[$customerId]['email_setting'] ?? [];
            $otherEmail = $customerIdEmailSettingMap[$customerId]['other_email'] ?? [];
            if (!$this->isSetEmail($msgType, $emailSetting)) {
                continue;
            }

            if ($emailSetting['sendmail']['default_email'] && !empty($customerIdEmailMap[$customerId])) {
                $emails[] = $customerIdEmailMap[$customerId];
            }

            if ($otherEmail) {
                if (isset($otherEmail[0])) {
                    $emails[] = $otherEmail[0];
                }
                if (isset($otherEmail[1])) {
                    $emails[] = $otherEmail[1];
                }
            }
        }

        return array_unique($emails);
    }

    /**
     * @param int $msgType
     * @param array $emailSetting
     * @return bool
     */
    private function isSetEmail(int $msgType, array $emailSetting): bool
    {
        $sendMail = $emailSetting['sendmail'] ?? [];
        $emailSettingSystem = $emailSetting['system'] ?? [];
        $emailSettingStore = $emailSetting['store'] ?? [];

        if (empty($sendMail['default_email']) && empty($sendMail['other_email'])) {
            return false;
        }

        if ($msgType == 0 && !empty($emailSettingStore)) {
            return true;
        }

        if ($msgType >= MsgType::PRODUCT && $msgType < MsgType::RMA && !empty($emailSettingSystem['product'])) {
            return true;
        }
        if ($msgType >= MsgType::RMA && $msgType < MsgType::BID && !empty($emailSettingSystem['rma'])) {
            return true;
        }
        if ($msgType >= MsgType::BID && $msgType < MsgType::ORDER && !empty($emailSettingSystem['bid'])) {
            return true;
        }
        if ($msgType >= MsgType::ORDER && $msgType < MsgType::OTHER && !empty($emailSettingSystem['order'])) {
            return true;
        }
        if ($msgType >= MsgType::OTHER && $msgType < MsgType::INVOICE && !empty($emailSettingSystem['other'])) {
            return true;
        }

        return false;
    }
}