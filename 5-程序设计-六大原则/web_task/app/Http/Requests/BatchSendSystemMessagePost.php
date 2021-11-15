<?php

namespace App\Http\Requests;

use App\Enums\Message\MsgMsgType;
use App\Helpers\LoggerHelper;
use App\Models\Customer\Customer;
use App\Rules\StrToArrUnique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchSendSystemMessagePost extends FormRequest
{
    public $message;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'list' => 'required|array',
            'list.*.subject' => 'required|filled|max:255',
            'list.*.body' => 'required|filled',
            'list.*.msg_type' => [
                'required',
                'filled',
                Rule::in(MsgMsgType::getAllTypeKeys()),
            ],
            'list.*.is_send_email' => [
                'filled',
                Rule::in([0, 1])
            ],
            'list.*.receiver_id' => [
                'required',
                'filled',
                new StrToArrUnique,
            ],
            'list.*.attach_id' => 'integer',
            'list.*.is_sent' => [
                'integer',
                Rule::in([0, 1])
            ],
            'list.*.operation_id' => 'integer',
            'list.*.send_time' => 'date'
        ];
    }


    /**
     * @param \Illuminate\Validation\Validator $validator
     *
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                LoggerHelper::logSystemMessage(['批量发送消息，数据格式有误' => $validator->errors()->all()], 'error');
                LoggerHelper::logSystemMessage(['批量发送消息，错误数据' => $validator->invalid()], 'error');
            } else {
                $this->checkCustomerId($validator->valid());
            }
        });
    }

    /**
     * 校验接收者ID
     *
     * @param array $data
     */
    private function checkCustomerId($data)
    {
        $data = $data['list'];

        // 校验接受者ID
        $receiverIds = array_unique(explode(',', implode(',', array_column($data, 'receiver_id'))));
        $existsReceiverCount = Customer::whereIn('customer_id', $receiverIds)->count();
        if ($existsReceiverCount != count($receiverIds)) {
            LoggerHelper::logSystemMessage(['批量发送消息，存在非法接受者ID' => $receiverIds], 'error');
            $this->message = 'Send messages in batches, there is an illegal recipient ID.';
            return;
        }

    }
}
