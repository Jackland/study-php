<?php

namespace App\Catalog\Forms\Message\Notice;

use App\Models\Message\Notice;
use App\Models\Message\NoticePlaceholder;
use Framework\Exception\Exception;
use Framework\Model\RequestForm\RequestForm;
use Illuminate\Support\Collection;

class SureForm extends RequestForm
{
    public $notices;

    /**
     * 验证
     * @return array
     */
    protected function getRules(): array
    {
        return [
            'notices' => 'required|array',
            'notices.*.id' => 'required|integer',
            'notices.*.type' => 'required|in:notice',
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

        $noticesTypeIdsMap = (new Collection($this->notices))->groupBy('type')->toArray();
        $noticesIds = isset($noticesTypeIdsMap['notice']) ? array_column($noticesTypeIdsMap['notice'], 'id') : [];

        if (empty($noticesIds)) {
            return;
        }

        $notices = Notice::query()->whereIn('id', $noticesIds)->get(['id','make_sure_status']);
        $insertData = [];
        foreach ($notices as $notice) {
            $res = NoticePlaceholder::query()->where('notice_id', $notice->id)
                ->where('customer_id', $customerId)
                ->update(['is_read' => 1, 'make_sure_status' => $notice->make_sure_status, 'update_time' => date('Y-m-d H:i:s')]);

            if (!$res) {
                $data = [
                    'notice_id' => $notice->id,
                    'customer_id' => $customerId,
                    'is_read' => 1,//已确认也是已读
                    'make_sure_status' => $notice->make_sure_status,//标记为已确认
                    'create_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s'),
                ];
                $insertData[] = $data;
            }
        }

        if (!empty($insertData)) {
            NoticePlaceholder::query()->insert($insertData);
        }
    }
}
