<?php

namespace App\Services\Message;

use App\Enums\Common\YesNoEnum;
use App\Enums\Message\TicketStatus;
use App\Logging\Logger;
use App\Models\Message\Ticket;
use App\Models\Message\TicketMessage;

class TicketService
{
    /**
     * @param array $data 消息内容数组
     * @param Ticket $ticketInfo 原主消息信息
     * @return bool
     * @throws \Exception -- 回滚方法可能抛出异常，不予处理
     */
    public function reply($data, $ticketInfo)
    {
        try {
            db()->getConnection()->beginTransaction();
            $nowDate = date('Y-m-d H:i:s');

            // 插入回复内容
            $insertData['ticket_id'] = $data['ticket_id'];
            $insertData['create_customer_id'] = $data['create_customer_id'];
            $insertData['description'] = $data['description'];
            $insertData['attachments'] = $data['attachments'];
            $insertData['date_added'] = $nowDate;

            $insertRes = TicketMessage::insert($insertData);
            if (! $insertRes) {
                throw new \Exception('插入消息内容发生错误:' . json_encode($insertData));
            }

            $updateData = [];
            if ($ticketInfo->user_status === YesNoEnum::NO && in_array($ticketInfo->status, TicketStatus::getResetProcessAdminStatus())) {
                $updateData['status'] = TicketStatus::WAIT_RECEIVE;
                $updateData['process_admin_id'] = 0;
                $updateData['delay_flag'] = YesNoEnum::NO;
            } elseif (in_array($ticketInfo->status, TicketStatus::getToBeingProcessedStatus())) {
                $updateData['status'] = TicketStatus::WAIT_PROCESS;
                $updateData['delay_flag'] = YesNoEnum::NO;
            } elseif ($ticketInfo->status == TicketStatus::BEING_PROCESSED && $ticketInfo->delay_flag != YesNoEnum::NO) {
                $updateData['delay_flag'] = YesNoEnum::NO;
            }
            if ($updateData) {
                $updateData['date_modified'] = $nowDate;

                $updateRes = Ticket::where('id', $data['ticket_id'])
                    ->where('create_customer_id', $data['create_customer_id'])
                    ->update($updateData);
                if (! $updateRes) {
                    throw new \Exception('更新主消息状态信息发生错误:' . json_encode($updateData));
                }
            }

            db()->getConnection()->commit();
        } catch (\Exception $e) {
            db()->getConnection()->rollBack();

            Logger::error('Ticket消息回复发生错误：' . $e->getMessage());
            return false;
        }

        return true;
    }
}