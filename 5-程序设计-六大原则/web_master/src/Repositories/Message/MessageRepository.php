<?php

namespace App\Repositories\Message;

use App\Enums\Common\YesNoEnum;
use App\Enums\Message\MsgCustomerExtLanguageType;
use App\Enums\Message\MsgReceiveDeleteStatus;
use App\Enums\Message\MsgReceiveSendType;
use App\Enums\Message\MsgType;
use App\Helper\StringHelper;
use App\Models\Customer\Customer;
use App\Models\Message\Msg;
use App\Models\Message\MsgCustomerExt;
use App\Models\Message\MsgReceive;
use App\Models\Message\Notice;
use App\Models\Message\NoticePlaceholder;
use App\Models\Message\StationLetterCustomer;
use App\Models\Setting\MessageSetting;
use App\Repositories\Setting\MessageSettingRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MessageRepository
{
    const PLATFORM_SECRETARY_ID = -1; // 平台小助手标识ID
    const SYSTEM_ID = 0; // 系统标识ID

    /**
     * 是否需要发送邮件
     * @param Msg $msg
     * @param int $customerId
     * @return bool
     */
    public function isSendEmail(Msg $msg, int $customerId): bool
    {
        $emailSetting = app(MessageSettingRepository::class)->getEmailSettingByCustomerIds([$customerId])[$customerId];

        return $this->isSetEmail($msg->msg_type, $emailSetting['email_setting']);

    }

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

    /**
     * 判断用户是否可发送站内信
     * @param int $customerId
     * @param bool $isSeller
     * @return bool
     */
    public function checkCustomerNewMsg(int $customerId, bool $isSeller = true): bool
    {
        $oneDayNewMsgLimitCount = $isSeller ? (int)configDB('seller_day_send_max_count', 0) : (int)configDB('buyer_day_send_max_count', 0);
        if ($oneDayNewMsgLimitCount == 0) {
            return true;
        }

        $oneDayNewMsgCount = app(StatisticsRepository::class)->getCustomerTodayNewMsgCount($customerId);

        return $oneDayNewMsgLimitCount > $oneDayNewMsgCount;
    }

    /**
     * 用户剩余发送站内信次数， -1为不限制
     * @param int $customerId
     * @param bool $isSeller
     * @return int
     */
    public function getTodayRemainSendCount(int $customerId, bool $isSeller = true): int
    {
        $oneDayNewMsgLimitCount = $isSeller ? (int)configDB('seller_day_send_max_count', 0) : (int)configDB('buyer_day_send_max_count', 0);
        if ($oneDayNewMsgLimitCount == 0) {
            return -1;
        }

        $oneDayNewMsgCount = app(StatisticsRepository::class)->getCustomerTodayNewMsgCount($customerId);

        return max($oneDayNewMsgLimitCount - $oneDayNewMsgCount, 0);
    }

    /**
     * 获取站内信的语言类型和可接收的用户
     * @param array $customerIds
     * @param string $subject
     * @param string $content
     * @return array
     */
    public function getMsgLanguageAndReceiverIds(array $customerIds, string $subject, string $content): array
    {
        $extensionsCustomerIds = MsgCustomerExt::queryRead()->whereIn('customer_id', $customerIds)->pluck('customer_id')->toArray();
        $diffCustomerIds = array_diff($customerIds, $extensionsCustomerIds);
        $inserts = [];
        foreach ($diffCustomerIds as $diffCustomerId) {
            $inserts[] = [
                'customer_id' => $diffCustomerId,
            ];
        }
        MsgCustomerExt::query()->insert($inserts);

        $languageTypeBuyerIds =  MsgCustomerExt::queryRead()->whereIn('customer_id', $customerIds)->get()->groupBy('language_type');
        /** @var Collection $noLimitExt */
        $noLimitExt = $languageTypeBuyerIds->get(MsgCustomerExtLanguageType::NOT_LIMIT, new Collection());
        /** @var Collection $chineseExt */
        $chineseExt = $languageTypeBuyerIds->get(MsgCustomerExtLanguageType::CHINESE, new Collection());
        /** @var Collection $englishExt */
        $englishExt = $languageTypeBuyerIds->get(MsgCustomerExtLanguageType::ENGLISH, new Collection());

        // 包含中文的就算中文的站内信，其他为英文站内信
        if (!StringHelper::stringIncludeChinese($subject) && !StringHelper::stringIncludeChinese(strip_tags($content))) {
            $languageType = 'English';
            $receiverIds = $noLimitExt->merge($englishExt)->pluck('customer_id')->toArray();
        } else {
            $languageType = 'Chinese';
            $receiverIds = $noLimitExt->merge($chineseExt)->pluck('customer_id')->toArray();
        }

        return [$languageType, $receiverIds];
    }

    /**
     * 发送方和接收方是否是同一个国家
     * @param int $senderId
     * @param array $receiverIds
     * @return bool
     */
    public function isSameCountryFromSenderToReceivers(int $senderId, array $receiverIds): bool
    {
        /** @var Customer $sender */
        $sender = Customer::queryRead()->find($senderId);

        if (count($receiverIds) == Customer::queryRead()->whereIn('customer_id', $receiverIds)->where('country_id', $sender->country_id)->count()) {
            return true;
        }

        return false;
    }

    /**
     * 上传文件配置
     * @return array
     */
    public function uploadFileExt(): array
    {
        $extensions = explode(',', configDB('module_wk_communication_type', ''));

        $extensions = array_unique(array_merge($extensions, ['xls', 'xlsx', 'doc', 'docx']));
        $max = configDB('module_wk_communication_max', 5);
        $size = configDB('module_wk_communication_size', 8192);

        return [
            'extension' => $extensions,
            'max' => $max,
            'size' => $size,
        ];
    }

    /**
     * 获取过去24小时内出去来自Seller的未读消息列表
     *  具体站内信类型包括订阅消息，小秘书、System Alerts、System Notifications and Announcements
     *
     * @param int $customerId 用户ID
     * @param int $countryId 国家ID
     * @return array
     */
    public function getSlideShowUnreadList(int $customerId, int $countryId)
    {
        $nowDate = Carbon::now()->subDay(1)->toDateTimeString();
        // 取24小时内所有排除来自Seller的未读消息
        $unreadMsg = MsgReceive::queryRead()->alias('mr')
            ->leftJoinRelations('msg as m')
            ->where('mr.receiver_id', $customerId)
            ->where('mr.is_read', YesNoEnum::NO)
            ->where('mr.delete_status', MsgReceiveDeleteStatus::NOT_DELETED)
            ->where('mr.create_time', '>=', $nowDate)
            ->whereIn('mr.send_type', [MsgReceiveSendType::PLATFORM_SECRETARY, MsgReceiveSendType::SYSTEM])
            ->select(['m.id', 'm.title', 'mr.create_time'])
            ->get()
            ->toArray();
        // 取24小时内所有未读通知
        $unreadLetter = StationLetterCustomer::queryRead()->alias('slc')
            ->leftJoinRelations('stationLetter as sl')
            ->where('sl.status', YesNoEnum::YES)
            ->where('sl.is_delete', YesNoEnum::NO)
            ->where('slc.customer_id', $customerId)
            ->where('slc.is_read', YesNoEnum::NO)
            ->where('slc.is_delete', YesNoEnum::NO)
            ->where('slc.create_time', '>=', $nowDate)
            ->select(['sl.id', 'sl.title', 'slc.create_time'])
            ->get()
            ->toArray();
        // 取24小时内所有未读公告
        $subSql = NoticePlaceholder::where('customer_id', $customerId)
            ->where(function ($q) {
                return $q->where('is_del', YesNoEnum::YES)
                    ->orWhere(function ($q) {
                        return $q->where('is_read', YesNoEnum::YES)
                            ->where('is_del', YesNoEnum::NO);
                    });
            })->pluck('notice_id');
        $unreadNotice =  Notice::queryRead()->alias('n')
            ->leftJoin('tb_sys_notice_to_object as to', 'to.notice_id', 'n.id')
            ->leftJoin('tb_sys_notice_object as no', 'to.notice_object_id', 'no.id')
            ->whereIn('no.country_id', [0, $countryId])
            ->where('no.identity', 0)
            ->where('n.publish_status', YesNoEnum::YES)
            ->where('n.publish_date', '<=', date('Y-m-d H:i:s'))
            ->where('n.publish_date', '>=', $nowDate)
            ->selectRaw('DISTINCT(n.id) as id,n.title,n.publish_date as create_time')
            ->whereNotIn('n.id', $subSql)
            ->get()
            ->toArray();
        $list = array_merge($unreadMsg, $unreadLetter, $unreadNotice);
        $createTime = array_column($list, 'create_time');
        array_multisort($createTime, SORT_DESC, $list);

        return $list;
    }

    /**
     * 获取最新一条消息（根据用户现在的配置来）
     * @param Customer $customer
     * @param $startTime
     * @return array
     */
    public function getLatestMessageByCustomer(Customer $customer, $startTime): array
    {
        $messageSetting = app(MessageSettingRepository::class)->getByCustomerId($customer->customer_id, ['setting' => 'setting_formatted']);
        $messageSettings = $messageSetting['setting_formatted'] ?? '';

        $msgBuilder = MsgReceive::queryRead()
            ->where('receiver_id', $customer->customer_id)
            ->where('is_read', YesNoEnum::NO)
            ->where('delete_status', YesNoEnum::NO)
            ->where('create_time', '>', $startTime)
            ->select(['msg_id', 'create_time'])
            ->orderByDesc('create_time');

        $messages = [];
        // 平台小组手
        if ($lastPlatformSecretaryMsg = (clone $msgBuilder)->where('send_type', MsgReceiveSendType::PLATFORM_SECRETARY)->first()) {
            $messages[] = $lastPlatformSecretaryMsg->toArray();
        }
        // seller/buyer
        if (!empty($messageSettings['store']) && $lastStoreMsg = (clone $msgBuilder)->where('send_type', MsgReceiveSendType::USER)->first()) {
            $messages[] = $lastStoreMsg->toArray();
        }
        // system
        if (!empty($messageSettings['system']) && $lastSystemMsg = (clone $msgBuilder)->where('send_type', MsgReceiveSendType::SYSTEM)->first()) {
            $messages[] = $lastSystemMsg->toArray();
        }
        $lastMsg = [];
        $lastCreateTime = $startTime;
        if (!empty($messages)) {
            $createTime = array_column($messages, 'create_time');
            array_multisort($createTime, SORT_DESC, $messages);
            $lastMsg = $messages[0];
            $lastCreateTime = $lastMsg['create_time'];
            $lastMsg['title'] = Msg::queryRead()->where('id', $lastMsg['msg_id'])->value('title');
            $lastMsg['url'] = !$customer->is_partner ? url()->to(['account/message_center/message/detail', 'msg_id' => $lastMsg['msg_id']]) : url()->to(['customerpartner/message_center/message/detail', 'msg_id' => $lastMsg['msg_id']]);
        }

        // 公告
        if (!empty($messageSettings['platformNotice'])) {
            $subSql = NoticePlaceholder::where('customer_id', $customer->customer_id)
                ->where(function ($q) {
                    return $q->where('is_del', YesNoEnum::YES)
                        ->orWhere(function ($q) {
                            return $q->where('is_read', YesNoEnum::YES)
                                ->where('is_del', YesNoEnum::NO);
                        });
                })->pluck('notice_id');
            $lastNotice = Notice::queryRead()->alias('n')
                ->leftJoin('tb_sys_notice_to_object as to', 'to.notice_id', 'n.id')
                ->leftJoin('tb_sys_notice_object as no', 'to.notice_object_id', 'no.id')
                ->whereIn('no.country_id', [0, $customer->country_id])
                ->where('no.identity', 0)
                ->where('n.publish_status', YesNoEnum::YES)
                ->where('n.publish_date', '>', $lastCreateTime)
                ->selectRaw('DISTINCT(n.id) as msg_id,n.title,n.publish_date as create_time')
                ->whereNotIn('n.id', $subSql)
                ->orderByDesc('n.publish_date')
                ->first();
            if ($lastNotice) {
                $lastMsg = $lastNotice->toArray();
                $lastCreateTime = $lastMsg['create_time'];
                if ($customer->is_partner) {
                    $lastMsg['url'] = url(['customerpartner/message_center/notice/detail', 'notice_id' => $lastMsg['msg_id'], 'type' => 'notice']);
                } else {
                    $lastMsg['url']= url(['account/message_center/platform_notice/view', 'notice_id' => $lastMsg['msg_id'], 'type' => 'notice']);
                }
            }
        }

        // 通知
        if (!empty($messageSettings['station_letter'])) {
            $stationLetter = StationLetterCustomer::queryRead()->alias('slc')
                ->leftJoinRelations('stationLetter as sl')
                ->where('sl.status', YesNoEnum::YES)
                ->where('sl.is_delete', YesNoEnum::NO)
                ->where('slc.customer_id', $customer->customer_id)
                ->where('slc.is_read', YesNoEnum::NO)
                ->where('slc.is_delete', YesNoEnum::NO)
                ->where('slc.create_time', '>', $lastCreateTime)
                ->select(['sl.id as msg_id', 'sl.title', 'slc.create_time'])
                ->orderByDesc('slc.create_time')
                ->first();
            if ($stationLetter) {
                $lastMsg = $stationLetter->toArray();
                if ($customer->is_partner) {
                    $lastMsg['url'] = url(['customerpartner/message_center/notice/detail', 'notice_id' => $lastMsg['msg_id'], 'type' => 'station_letter']);
                } else {
                    $lastMsg['url']= url(['account/message_center/platform_notice/view', 'notice_id' => $lastMsg['msg_id'], 'type' => 'station_letter']);
                }
            }
        }

        return $lastMsg;
    }
}
