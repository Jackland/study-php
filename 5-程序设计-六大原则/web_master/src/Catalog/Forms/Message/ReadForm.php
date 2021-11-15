<?php

namespace App\Catalog\Forms\Message;

use App\Models\Message\MsgReceive;
use Framework\Exception\Exception;
use Framework\Model\RequestForm\RequestForm;

class ReadForm  extends RequestForm
{
    public $tab_type;
    public $ids;
    public $is_read;

    /**
     * 验证
     * @return array
     */
    protected function getRules(): array
    {
        return [
            'tab_type' => 'required|in:sent,inbox',
            'ids' => 'required',
            'is_read' => 'required|in:0,1'
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
        $ids = explode(',', $this->ids);
        switch (request('tab_type')) {
            case 'sent':
                break;
            case 'inbox':
                MsgReceive::query()->where('receiver_id', $customerId)->whereIn('id', $ids)->update(['is_read' => $this->is_read]);
                break;
        }
    }
}
