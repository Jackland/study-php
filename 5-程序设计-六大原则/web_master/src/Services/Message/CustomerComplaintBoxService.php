<?php

namespace App\Services\Message;

use App\Enums\Message\CustomerComplaintBoxType;
use App\Models\Customer\CustomerComplaintBox;

class CustomerComplaintBoxService
{
    /**
     * 新增投诉
     *
     * @param int $complainantId 投诉人ID
     * @param int $respondentId 被投诉人ID
     * @param string $reason 投诉理由
     * @param int $msgId 投诉类型为投诉消息时的 消息ID
     * @param int $type 被投诉类型 1消息 2用户
     * @return bool
     */
    public function addComplain(int $complainantId, int $respondentId, $reason = '', int $msgId = 0, $type = CustomerComplaintBoxType::SELLER)
    {
        if ($type == CustomerComplaintBoxType::MESSAGE) {
            if ($msgId < 1) {
                return false;
            }
            $data['msg_id'] = $msgId;
        }

        $data['complainant_id'] = $complainantId;
        $data['respondent_id'] = $respondentId;
        $data['type'] = $type;
        $data['reason'] = $reason;

        return CustomerComplaintBox::create($data);
    }
}