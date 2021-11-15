<?php

namespace App\Catalog\Forms\Message;

use App\Models\Message\Msg;
use App\Models\Message\MsgReceive;
use Framework\Exception\Exception;
use Framework\Model\RequestForm\RequestForm;

class DeletedForm extends RequestForm
{
    public $tab_type;
    public $ids;
    public $delete_status;

    /**
     * 验证
     * @return array
     */
    protected function getRules(): array
    {
        return [
            'tab_type' => 'required|in:sent,inbox',
            'ids' => 'required',
            'delete_status' => 'required|in:0,1,2'
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
                Msg::query()->where('sender_id', $customerId)->whereIn('id', $ids)->update(['delete_status' => $this->delete_status]);
                break;
            case 'inbox':
                MsgReceive::query()->where('receiver_id', $customerId)->whereIn('id', $ids)->update(['delete_status' => $this->delete_status]);
                break;
        }
    }
}
