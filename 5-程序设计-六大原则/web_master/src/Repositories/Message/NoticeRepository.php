<?php

namespace App\Repositories\Message;

use App\Enums\Common\YesNoEnum;
use App\Models\Customer\Customer;
use App\Models\Message\Notice;
use App\Models\Message\NoticePlaceholder;
use App\Repositories\Setting\MessageSettingRepository;
use Illuminate\Database\Eloquent\Builder;

class NoticeRepository
{
    /**
     * 获取用户未读通知数
     *
     * @param int $customerId 用户ID
     * @param int $countryId 用户国别ID
     * @param int $identity 用户身份 0:Buyer 1:Seller
     * @return int
     */
    public function getNewNoticeCount(int $customerId, int $countryId, int $identity = 0)
    {
        $subSql = NoticePlaceholder::where('customer_id', $customerId)
            ->where('is_del', YesNoEnum::YES)
            ->pluck('notice_id');
        return Notice::queryRead()->alias('n')
            ->leftJoinRelations(['toObject as to'])
            ->leftJoin('tb_sys_notice_object as no', 'to.notice_object_id', 'no.id')
            ->leftJoin('tb_sys_notice_placeholder as p', function ($join) use ($customerId) {
                $join->on('p.notice_id', '=', 'n.id')->where(function ($query) use ($customerId) {
                    $query->where('p.customer_id', $customerId)->orWhereNull('p.customer_id');
                });
            })
            ->whereIn('no.country_id', [0, $countryId])
            ->where('no.identity', $identity)
            ->where('n.publish_status', YesNoEnum::YES)
            ->where('n.publish_date', '<=', date('Y-m-d H:i:s'))
            ->where(function (Builder $q) {
                $q->where('p.is_read', 0)
                    ->orWhereNull('p.is_read');
            })
            ->whereNotIn('n.id', $subSql)
            ->selectRaw('count(DISTINCT(n.id)) as num')
            ->value('num');
    }

    /**
     * 是否需要发送邮件
     * @param int $noticeType
     * @param int $customerId
     * @return bool
     */
    public function isSendEmail(int $noticeType, int $customerId): bool
    {
        $emailSetting = app(MessageSettingRepository::class)->getEmailSettingByCustomerIds([$customerId])[$customerId];

        return $this->isSetEmail($noticeType, $emailSetting['email_setting']);
    }

    /**
     * @param int $noticeType
     * @param $emailSetting
     * @return bool
     */
    private function isSetEmail(int $noticeType, $emailSetting): bool
    {
        $sendMail = $emailSetting['sendmail'] ?? [];
        $emailSettingPlatform = $emailSetting['platform'] ?? [];

        if (empty($sendMail['default_email']) && empty($sendMail['other_email'])) {
            return false;
        }

        if ($noticeType == 1 && !empty($emailSettingPlatform['product'])) {
            return true;
        }
        if ($noticeType == 2 && !empty($emailSettingPlatform['system'])) {
            return true;
        }
        if ($noticeType == 3 && !empty($emailSettingPlatform['policy'])) {
            return true;
        }
        if ($noticeType == 4 && !empty($emailSettingPlatform['holiday'])) {
            return true;
        }
        if ($noticeType == 5 && !empty($emailSettingPlatform['logistics'])) {
            return true;
        }
        if ($noticeType == 6 && !empty($emailSettingPlatform['other'])) {
            return true;
        }

        return false;
    }

    /**
     * 获取某些用户能发送邮件的邮箱地址
     * @param int $noticeType
     * @param array $customerIds
     * @return array
     */
    public function getSendEmails(int $noticeType, array $customerIds): array
    {
        $customerIdEmailSettingMap = app(MessageSettingRepository::class)->getEmailSettingByCustomerIds($customerIds);
        $customerIdEmailMap = Customer::query()->whereIn('customer_id', $customerIds)->pluck('email', 'customer_id')->toArray();

        $emails = [];
        foreach ($customerIds as $customerId) {
            $emailSetting = $customerIdEmailSettingMap[$customerId]['email_setting'] ?? [];
            $otherEmail = $customerIdEmailSettingMap[$customerId]['other_email'] ?? [];
            if (!$this->isSetEmail($noticeType, $emailSetting)) {
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
}
