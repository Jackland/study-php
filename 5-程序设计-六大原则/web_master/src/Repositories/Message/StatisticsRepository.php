<?php

namespace App\Repositories\Message;

use App\Enums\Common\YesNoEnum;
use App\Enums\Message\MsgDeleteStatus;
use App\Enums\Message\MsgReceiveSendType;
use App\Enums\Message\MsgReceiveType;
use App\Enums\Message\MsgType;
use App\Helper\CountryHelper;
use App\Models\Customer\Customer;
use App\Models\Message\Msg;
use App\Models\Message\MsgReceive;
use Carbon\Carbon;

/**
 * 消息统计需要使用从库 read
 *
 * Class StatisticsRepository
 * @package App\Repositories\Message
 */
class StatisticsRepository
{
    /**
     * 获取某个用户收件箱中来自其他用户消息的未读数量
     * @param int $customerId
     * @return int
     */
    public function getCustomerInboxFromUserUnreadCount(int $customerId): int
    {
        return MsgReceive::queryRead()
            ->where('send_type', MsgReceiveSendType::USER)
            ->where('receiver_id', $customerId)
            ->where('delete_status', MsgDeleteStatus::NOT_DELETED)
            ->where('is_read', YesNoEnum::NO)
            ->count('id');
    }

    /**
     * 获取某个用户收件箱中来自系统消息的未读多种主类型数量
     * @param int $customerId
     * @return array
     */
    public function getCustomerInboxFromSystemUnreadMainTypesCount(int $customerId): array
    {
        return MsgReceive::queryRead()->alias('r')
            ->joinRelations('msg as s')
            ->where('r.send_type', MsgReceiveSendType::SYSTEM)
            ->where('r.receiver_id', $customerId)
            ->where('r.delete_status', MsgDeleteStatus::NOT_DELETED)
            ->where('r.is_read', YesNoEnum::NO)
            ->selectRaw("LEFT(s.msg_type, 1) as t, count(s.id) as count")
            ->groupBy(["t"])
            ->get()
            ->map(function ($v) {
                return ['type' => $v->t . '00', 'count'=> $v->count];
            })
            ->pluck('count', 'type')
            ->toArray();
    }

    /**
     * 获取某个用户收件箱中来自系统消息的未读数量
     * @param int $customerId
     * @return int
     */
    public function getCustomerInboxFromSystemUnreadCount(int $customerId): int
    {
        return MsgReceive::queryRead()
            ->where('send_type', MsgReceiveSendType::SYSTEM)
            ->where('receiver_id', $customerId)
            ->where('delete_status', MsgDeleteStatus::NOT_DELETED)
            ->where('is_read', YesNoEnum::NO)
            ->count();
    }

    /**
     * 获取用户收件箱中来自平台小秘书的未读消息数
     *
     * @param int $customerId
     * @return int
     */
    public function getCustomerInboxFromGigaGenieUnreadCount(int $customerId) : int
    {
        return MsgReceive::queryRead()
            ->where('send_type', MsgReceiveSendType::PLATFORM_SECRETARY)
            ->where('receiver_id', $customerId)
            ->where('delete_status', MsgDeleteStatus::NOT_DELETED)
            ->where('is_read', YesNoEnum::NO)
            ->count();
    }

    /**
     * 获取用户当天（当前国别）主动发送站内信的次数
     * @param int $customerId
     * @return int
     */
    public function getCustomerTodayNewMsgCount(int $customerId): int
    {
        $countryId = Customer::queryRead()->where('customer_id', $customerId)->value('country_id');
        if ($countryId == AMERICAN_COUNTRY_ID) {
            $beginDate = Carbon::today()->toDateTimeString();
        } else {
            $countryStr = CountryHelper::getCountryCodeById($countryId, 'USA');
            $fromZone = CountryHelper::getTimezoneByCode('USA');
            $toZone = CountryHelper::getTimezone($countryId);
            $otherCountryTodayDate = dateFormat($fromZone, $toZone, Carbon::now()->toDateTimeString(), 'Y-m-d 00:00:00');
            $beginDate = changeInputByZone($otherCountryTodayDate, $countryStr);
        }

        return Msg::queryRead()
            ->where('sender_id', $customerId)
            ->where('receive_type', MsgReceiveType::USER)
            ->where('msg_type', MsgType::NORMAL)
            ->where('parent_msg_id', 0)
            ->where('create_time', '>=', $beginDate)
            ->count();
    }

    /**
     * 获取某个用户收件箱中来自系统消息某个具体类型的未读数量
     *
     * @param int $customerId
     * @param string $msgType 消息类型（101）
     * @return int
     */
    public function getCustomerInboxFromSomeTypeSystemUnreadCount(int $customerId, $msgType): int
    {
        return MsgReceive::queryRead()->alias('r')
            ->joinRelations('msg as s')
            ->where('r.send_type', MsgReceiveSendType::SYSTEM)
            ->where('r.receiver_id', $customerId)
            ->where('r.delete_status', MsgDeleteStatus::NOT_DELETED)
            ->where('r.is_read', YesNoEnum::NO)
            ->where('s.msg_type', $msgType)
            ->count();
    }

    /**
     * 获取用户收件箱中所有类型未读消息数
     *
     * @param int $customerId
     * @return int
     */
    public function getCustomerInboxUnreadCount(int $customerId) : int
    {
        return MsgReceive::queryRead()
            ->where('receiver_id', $customerId)
            ->where('delete_status', MsgDeleteStatus::NOT_DELETED)
            ->where('is_read', YesNoEnum::NO)
            ->count();
    }
}
