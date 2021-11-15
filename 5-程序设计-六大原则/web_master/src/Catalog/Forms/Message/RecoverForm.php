<?php

namespace App\Catalog\Forms\Message;

use App\Enums\Common\YesNoEnum;
use App\Enums\Message\MsgDeleteStatus;
use App\Models\Message\Msg;
use App\Models\Message\MsgReceive;
use App\Models\Message\NoticePlaceholder;
use App\Models\Message\StationLetterCustomer;
use Carbon\Carbon;
use Framework\Exception\Exception;
use Framework\Model\RequestForm\RequestForm;

class RecoverForm  extends RequestForm
{
    public $trash_list;

    /**
     * 验证
     * @return array
     */
    protected function getRules(): array
    {
        return [
            'trash_list' => 'required|array',
        ];
    }

    /**
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        if (!$this->isValidated()) {
            throw new Exception($this->getFirstError());
        }

        $customerId = customer()->getId();

        $noticeIds = [];
        $receiveMsgIds = [];
        $sendMsgIds = [];
        $stationLetterIds = [];
        foreach ($this->trash_list as $value) {
            list($type, $id) = explode(',', $value);
            switch ($type) {
                case 'notice':
                    $noticeIds[] = $id;
                    break;
                case 'receive_message':
                    $receiveMsgIds[] = $id;
                    break;
                case 'send_message':
                    $sendMsgIds[] = $id;
                    break;
                case 'station_letter':
                    $stationLetterIds[] = $id;
                    break;
            }
        }

        if (!empty($noticeIds)) {
            NoticePlaceholder::query()
                ->whereIn('notice_id', $noticeIds)
                ->where('customer_id', $customerId)
                ->update([
                    'is_marked' => YesNoEnum::NO,
                    'is_del' => YesNoEnum::NO,
                    'update_time' => Carbon::now()->toDateTimeString(),
                ]);
        }

        if (!empty($receiveMsgIds)) {
            MsgReceive::query()
                ->whereIn('id', $receiveMsgIds)
                ->where('receiver_id', $customerId)
                ->update([
                    'is_marked' => YesNoEnum::NO,
                    'delete_status' => MsgDeleteStatus::NOT_DELETED,
                ]);
        }

        if (!empty($sendMsgIds)) {
            Msg::query()
                ->whereIn('id', $sendMsgIds)
                ->where('sender_id', $customerId)
                ->update([
                    'is_marked' => YesNoEnum::NO,
                    'delete_status' => MsgDeleteStatus::NOT_DELETED,
                ]);
        }

        if (!empty($stationLetterIds)) {
            StationLetterCustomer::query()
                ->whereIn('letter_id', $stationLetterIds)
                ->where('customer_id', $customerId)
                ->update([
                    'is_marked' => YesNoEnum::NO,
                    'is_delete' => YesNoEnum::NO,
                    'update_time' => Carbon::now()->toDateTimeString(),
                ]);
        }
    }
}
