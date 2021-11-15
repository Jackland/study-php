<?php

namespace App\Repositories\Message;

use App\Components\RemoteApi;
use App\Components\Storage\StorageCloud;
use App\Enums\Common\YesNoEnum;
use App\Enums\Message\MsgCommonWordsStatus;
use App\Enums\Message\MsgCommonWordsTypeCustomerType;
use App\Enums\Message\MsgMode;
use App\Enums\Message\MsgReceiveSendType;
use App\Enums\Message\MsgReceiveType;
use App\Models\Customer\CustomerComplaintBox;
use App\Models\CustomerPartner\BuyerGroup;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Message\Msg;
use App\Models\Message\MsgCommonWordsType;
use App\Models\Message\MsgCustomerExt;
use App\Models\Message\MsgReceive;
use App\Services\Message\MessageService;
use Framework\Exception\Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

class MessageDetailRepository
{
    /**
     * 获取消息详情
     * @param int $msgId
     * @param int $customerId
     * @return array
     * @throws Exception
     */
    public function getMessageDetail(int $msgId, int $customerId): array
    {
        /** @var Msg $msg */
        $msg = Msg::queryRead()->with('content')->find($msgId);
        if (!$msg || !$msg->content) {
            throw new Exception('', 404);
        }

        if ($msg->sender_id == $customerId) {
            if ($msg->msg_mode == MsgMode::PRIVATE_CHAT) {
                // 私聊
                return $this->getChatTypeMsgDetail($msg, $msg->receives->first(), $customerId);
            }
            // 群发
            return $this->getMassChatTypeMsgDetail($msg, $customerId);
        }

        /** @var MsgReceive $receiveMsg */
        $receiveMsg = $msg->receives()->where('receiver_id', $customerId)->first();
        if (!$receiveMsg) {
            throw new Exception('', 404);
        }

        // 收件箱 改为已读, 涉及的相关收件全部都需变为已读
        MsgReceive::query()->alias('r')
            ->joinRelations('msg as s')
            ->where('r.receiver_id', $customerId)
            ->where('s.sender_id', $msg->sender_id)
            ->where('s.root_msg_id', $msg->root_msg_id)
            ->where('r.is_read', YesNoEnum::NO)
            ->update(['r.is_read' => YesNoEnum::YES]);

        if ($receiveMsg->send_type == MsgReceiveSendType::SYSTEM) {
            return $this->getNoticeTypeMsgDetail($msg, $receiveMsg);
        }

        return $this->getChatTypeMsgDetail($msg, $receiveMsg, $customerId);
    }

    /**
     * 获取群聊式 对话站内信详情
     * @param Msg $msg
     * @param int $customerId
     * @return array
     */
    private function getMassChatTypeMsgDetail(Msg $msg, int $customerId): array
    {
        $receivers = $msg->receives;
        /** @var MsgReceive $firstReceive */
        $firstReceive = $receivers->first();

        $data['customer_id'] = $customerId;
        $data['readonly'] = true;
        $data['header'] = "Dialogue with " . ($firstReceive->receiver->seller ? $firstReceive->receiver->seller->screenname : $firstReceive->receiver->nickname) . ' (' . $receivers->count() . ')';
        $data['body'] = [
            [
                'msg_id' => $msg->id,
                'subject' => app(MessageService::class)->replaceMsgTitle($msg->title, $msg->msg_type, $msg->content->content),
                'post_time' => $msg->create_time,
                'content' => app(MessageService::class)->replaceMsgContent($msg->content->content, $msg->msg_type),
                'sender_id' => $msg->sender_id,
                'delete_status' => $msg->delete_status,
                'files' => $this->getAttach($msg->content->attach, $msg->content->attach_id),
            ]
        ];

        return ['chat', $data];
    }

    /**
     * 获取通知类型的消息详情（详情页面就一条站内信不需回复的）
     * @param Msg $msg
     * @param MsgReceive $msgReceive
     * @return array
     */
    private function getNoticeTypeMsgDetail(Msg $msg, MsgReceive $msgReceive): array
    {
        $data = [
            'msg_id' => $msg->id,
            'msg_receive_id' => $msgReceive->id,
            'subject' => app(MessageService::class)->replaceMsgTitle($msg->title, $msg->msg_type, $msg->content->content),
            'post_time' => $msgReceive->create_time,
            'content' => app(MessageService::class)->replaceMsgContent($msg->content->content, $msg->msg_type),
            'sender' => 'SYSTEM',
            'is_send_email' => app(MessageRepository::class)->isSendEmail($msg, $msgReceive->receiver_id),
            'receiver_id' => $msgReceive->receiver_id,
            'delete_status' => $msgReceive->delete_status,
        ];

        return ['notice', $data];
    }

    /**
     * 获取对话站内信详情
     * @param Msg $msg
     * @param MsgReceive $msgReceive
     * @param int $customerId
     * @return array
     */
    private function getChatTypeMsgDetail(Msg $msg, MsgReceive $msgReceive, int $customerId): array
    {
        $data['customer_id'] = $customerId;
        $data['readonly'] = false;
        $data['is_send_email'] = app(MessageRepository::class)->isSendEmail($msg, $customerId);
        $data['subject'] = 'Re: ' . $msg->title;

        if ($msg->sender_id == $customerId) {
            $data['receiver_id'] = $msgReceive->receiver_id;
            if ($msgReceive->receiver_id == Msg::PLATFORM_SECRETARY_SENDER_OR_RECEIVER_ID) {
                $data['header'] = 'Messages from Giga Help Desk';
                $data['chat_username'] = 'Giga Help Desk';
                $data['chat_user_number'] = '';
            } else {
                $name = $msgReceive->receiver->seller ? $msgReceive->receiver->seller->screenname : $msgReceive->receiver->nickname;
                $data['header'] = 'Dialogue with ' . $name;
                $data['chat_username'] = $name;
                $data['chat_user_number'] = $msgReceive->receiver->user_number;
                $data['chat_object_avatar'] = $msgReceive->receiver->seller && $msgReceive->receiver->seller->avatar && StorageCloud::image()->fileExists($msgReceive->receiver->seller->avatar) ?
                    StorageCloud::image()->getUrl($msgReceive->receiver->seller->avatar, ['check-exist' => false, 'w' => 100, 'h' => 100,]) : '';
            }
        } else {
            $data['receiver_id'] = $msg->sender_id;
            if ($msg->sender_id == Msg::PLATFORM_SECRETARY_SENDER_OR_RECEIVER_ID) {
                $data['header'] = 'Messages from Giga Help Desk';
                $data['chat_username'] = 'Giga Help Desk';
                $data['chat_user_number'] = '';
            } else {
                $name = $msg->sender->seller ? $msg->sender->seller->screenname : $msg->sender->nickname;
                $data['header'] = 'Dialogue with ' . $name;
                $data['chat_username'] = $name;
                $data['chat_user_number'] = $msg->sender->user_number;
                $data['chat_object_avatar'] = $msg->sender->seller && $msg->sender->seller->avatar && StorageCloud::image()->fileExists($msg->sender->seller->avatar) ?
                    StorageCloud::image()->getUrl($msg->sender->seller->avatar, ['check-exist' => false, 'w' => 100, 'h' => 100,]) : '';
            }
        }

        $messages = $this->getRootMessagesFromSenderIdToReceiverId($msg->root_msg_id, $msg->sender_id, $msgReceive->receiver_id);
        $messageIds = $messages->pluck('msg_id')->toArray();

        // 获取站内信是否举报 平台小组手的站内信, 没有举报
        $complainedMsgIds = [];
        if ($msg->sender_id != Msg::PLATFORM_SECRETARY_SENDER_OR_RECEIVER_ID && $msgReceive->receiver_id != Msg::PLATFORM_SECRETARY_SENDER_OR_RECEIVER_ID) {
            $complainedMsgIds = CustomerComplaintBox::queryRead()
                ->whereIn('msg_id', $messageIds)
                ->where('is_deleted', YesNoEnum::NO)
                ->where('complainant_id', $customerId)
                ->pluck('msg_id')
                ->toArray();
        }

        $data['body'] = $messages->map(function ($v) use ($complainedMsgIds) {
            if (in_array($v->msg_id, $complainedMsgIds)) {
                $v->is_complained = true;
            } else {
                $v->is_complained = false;
            }

            $v->files = $this->getAttach($v->attach, $v->attach_id);

            return $v;
        })->toArray();

        // 兼容建立管理的操作
        $data['is_build_relation'] = false;
        if (count($data['body']) == 1 && $data['body'][0]->status == 100 && $partner = CustomerPartnerToCustomer::query()->where('customer_id', $customerId)->first()) {
            $data['is_build_relation'] = true;
            $data['store_name'] = $partner->screenname;
            $data['buyer_groups'] = $this->getBuyerGroups($customerId);
        }

        $data['words_type'] = MsgCommonWordsType::queryRead()
            ->with(['words' => function ($query) { $query->where('status', MsgCommonWordsStatus::PUBLISHED)->orderByDesc('id'); }])
            ->whereIn('customer_type', MsgCommonWordsTypeCustomerType::getTypesByCustomer())
            ->where('is_deleted', YesNoEnum::NO)
            ->orderByDesc('sort')
            ->get();
        $data['is_clicked_common_words'] = MsgCustomerExt::queryRead()->where('customer_id', $data['customer_id'])->value('common_words_description') ?: 0;

        // 上传文件的配置
        $uploadFileExt = app(MessageRepository::class)->uploadFileExt();

        return ['chat', array_merge($data, $uploadFileExt)];
    }

    /**
     * 获取双方聊天的所有消息
     * @param int $rootMsgId
     * @param int $senderId
     * @param int $receiverId
     * @return Collection
     */
    public function getRootMessagesFromSenderIdToReceiverId(int $rootMsgId, int $senderId, int $receiverId): Collection
    {
        $sendMsgQuery = $this->getRootMessagesFromSenderIdToReceiverIdQuery($rootMsgId, $senderId, $receiverId);
        $receiverMsgQuery = $this->getRootMessagesFromSenderIdToReceiverIdQuery($rootMsgId, $receiverId, $senderId);

        return $sendMsgQuery->union($receiverMsgQuery)
            ->orderBy('post_time')
            ->get();
    }

    /**
     * @param int $rootMsgId
     * @param int $senderId
     * @param int $receiverId
     * @return Builder|\Framework\Model\Eloquent\Query\Builder
     */
    private function getRootMessagesFromSenderIdToReceiverIdQuery(int $rootMsgId, int $senderId, int $receiverId)
    {
        return Msg::queryRead()->alias('s')
            ->joinRelations('receives as r')
            ->joinRelations('content as c')
            ->where('s.sender_id', $senderId)
            ->where('r.receiver_id', $receiverId)
            ->where('s.receive_type', $receiverId == Msg::PLATFORM_SECRETARY_SENDER_OR_RECEIVER_ID ? MsgReceiveType::PLATFORM_SECRETARY : MsgReceiveType::USER)
            ->where('s.root_msg_id', $rootMsgId)
            ->select([
                's.id as msg_id',
                's.title as subject',
                'r.create_time as post_time',
                's.sender_id',
                'r.receiver_id',
                'r.delete_status as r_delete_status',
                's.delete_status as s_delete_status',
                'c.content',
                'c.attach',
                'c.attach_id',
                's.status',
            ])
            ->getQuery();
    }

    /**
     * 获取附件
     * @param string $attach
     * @param int $attachId
     * @return array
     */
    private function getAttach(string $attach, int $attachId): array
    {
        $files = [];
        if (!empty($attach)) {
            $attach = json_decode($attach, true);
            foreach ($attach as $item) {
                $files[] = [
                    'url' => url(['message/seller/download', 'filename' => $item['filename'], 'maskname' => $item['mask']]),
                    'name' => $item['mask'],
                ];
            }
        }
        if (!empty($attachId)) {
            $menus = RemoteApi::file()->getByMenuId($attachId);
            foreach ($menus as $menu) {
                $files[] = [
                    'url' => $menu->downloadUrl,
                    'name' => $menu->fileName,
                ];
            }
        }

        return $files;
    }

    /**
     * @param int $sellerId
     * @return array
     */
    private function getBuyerGroups(int $sellerId): array
    {
        $groups = BuyerGroup::queryRead()->where('seller_id', $sellerId)->where('status', YesNoEnum::YES)->select(['id', 'name', 'is_default'])->get();

        $buyerGroupProductGroupMap = db('oc_delicacy_management_group as dmg')
            ->join('oc_customerpartner_product_group as pg', 'pg.id', '=', 'dmg.product_group_id')
            ->select(['pg.name', 'dmg.buyer_group_id'])
            ->selectRaw('count(dmg.product_group_id) as total')
            ->where([
                ['dmg.seller_id', '=', $sellerId],
                ['dmg.status', '=', YesNoEnum::YES],
                ['pg.seller_id', '=', $sellerId],
                ['pg.status', '=', YesNoEnum::YES]
            ])
            ->whereIn('dmg.buyer_group_id', $groups->pluck('id')->toArray())
            ->groupBy(['dmg.buyer_group_id'])
            ->get()
            ->keyBy('buyer_group_id');

        return $groups->map(function ($v) use ($buyerGroupProductGroupMap) {
            $productGroup = $buyerGroupProductGroupMap->get($v->id, []);

            if ($productGroup) {
                $v->product_group_name = $productGroup->name;
                $v->product_group_num = $productGroup->total;
            } else {
                $v->product_group_name = '';
                $v->product_group_num = 0;
            }

            return $v;
        })->toArray();
    }
}
