<?php

namespace App\Catalog\Forms\Message\Notice;

use App\Models\Message\NoticePlaceholder;
use App\Models\Message\StationLetterCustomer;
use Carbon\Carbon;
use Framework\Exception\Exception;
use Framework\Model\RequestForm\RequestForm;
use Illuminate\Database\Eloquent\Collection;

class MarkedForm extends RequestForm
{
    private $data;

    public $notices;
    public $is_marked;

    public function __construct($data = [])
    {
        parent::__construct();
        $this->data = $data;
    }

    /**
     * 验证
     * @return array
     */
    protected function getRules(): array
    {
        return [
            'notices' => 'required|array',
            'notices.*.id' => 'required|integer',
            'notices.*.type' => 'required|in:station_letter,notice',
            'is_marked' => 'required|in:0,1'
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getAutoLoadRequestData()
    {
        return $this->data ?: $this->request->attributes->all();
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
        $letterIds = isset($noticesTypeIdsMap['station_letter']) ? array_column($noticesTypeIdsMap['station_letter'], 'id') : [];

        if (!empty($noticesIds)) {
            $existNoticeIds = NoticePlaceholder::query()->whereIn('notice_id', $noticesIds)->where('customer_id', $customerId)->pluck('notice_id')->toArray();

            $insertNoticeIds = array_filter(array_unique(array_diff($noticesIds, $existNoticeIds)));
            $insertNotices = [];
            foreach ($insertNoticeIds as $insertNoticeId) {
                $insertNotices[] = [
                    'notice_id' => $insertNoticeId,
                    'customer_id' => $customerId,
                    'is_read' => 0,
                    'create_time' => Carbon::now()->toDateTimeString(),
                    'update_time' => Carbon::now()->toDateTimeString(),
                    'is_marked' => 0,
                    'is_del' => 0
                ];
            }
            if (!empty($insertNotices)) {
                NoticePlaceholder::query()->insert($insertNotices);
            }

            NoticePlaceholder::query()->whereIn('notice_id', $noticesIds)->where('customer_id', $customerId)
                ->update(['is_marked' => $this->is_marked, 'update_time' => Carbon::now()->toDateTimeString()]);
        }

        if (!empty($letterIds)) {
            StationLetterCustomer::query()->where('customer_id', $customerId)->whereIn('letter_id', $letterIds)
                ->update(['is_marked' => $this->is_marked]);
        }

    }
}
