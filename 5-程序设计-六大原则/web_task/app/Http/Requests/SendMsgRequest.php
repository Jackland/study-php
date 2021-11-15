<?php

namespace App\Http\Requests;

use App\Helpers\LoggerHelper;
use App\Models\Customer\Customer;
use App\Services\Message\MessageService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * @property int $sender_id
 * @property int|array $receiver_ids
 * @property string $subject
 * @property string $body
 * @property int $is_send
 * @property int $attach_id
 * @property int $operation_id
 * @property string $send_time
 * @property int $receiver_group_ids
 * @property int $parent_msg_id
 *
 * Class SendMsgRequest
 * @package App\Http\Requests
 */
class SendMsgRequest extends FormRequest
{
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
            'sender_id' => 'required|integer',
            'receiver_ids' => 'required',
            'subject' => 'required|filled|max:255',
            'body' => 'required|filled',
            'is_send' => ['required', Rule::in([0, 1])],
            'attach_id' => 'integer',
            'operation_id' => 'integer',
            'receiver_group_ids' => '',
            'send_time' => 'required_if:is_send,0',
            'parent_msg_id' => 'integer',
        ];
    }

    /**
     * @param Validator $validator
     * @throws HttpResponseException
     */
    public function withValidator(Validator $validator)
    {
        $request = $validator->valid();

        if ($request['sender_id'] != MessageService::PLATFORM_SECRETARY && Customer::query()->where('customer_id', $request['sender_id'])->doesntExist()) {
            LoggerHelper::logSendMessage(['发送消息，发送人有误' => $request['sender_id']]);
            $this->responseReturn('发送人有误');
        }

        if (is_int($request['receiver_ids']) && $request['receiver_ids'] != MessageService::PLATFORM_SECRETARY && Customer::query()->where('customer_id', $request['receiver_ids'])->doesntExist()) {
            LoggerHelper::logSendMessage(['发送消息，接收人有误' => $request['receiver_ids']]);
            $this->responseReturn('接收人有误');
        }

        if (is_array($request['receiver_ids'])) {
            if (count($request['receiver_ids']) == 1 && $request['receiver_ids'][0] != MessageService::PLATFORM_SECRETARY && Customer::query()->where('customer_id', $request['receiver_ids'][0])->doesntExist()) {
                LoggerHelper::logSendMessage(['发送消息，接收人有误' => $request['receiver_ids']]);
                $this->responseReturn('接收人有误');
            }

            if (count(array_unique($request['receiver_ids'])) != Customer::query()->whereIn('customer_id', $request['receiver_ids'])->count()) {
                LoggerHelper::logSendMessage(['发送消息，接收人有误' => $request['receiver_ids']]);
                $this->responseReturn('接收人有误');
            }
        }

        if ($request['is_send'] == 0 && empty($request['send_time'])) {
            LoggerHelper::logSendMessage(['发送消息，发送时间有误' => $request['send_time']]);
            $this->responseReturn('请选择发送时间');
        }
    }

    /**
     * @param Validator $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        $error = $validator->errors()->first();

        LoggerHelper::logSendMessage(['发送消息，数据格式有误' => $validator->errors()->all()]);
        LoggerHelper::logSendMessage(['发送消息，错误数据' => $validator->invalid()]);

        $this->responseReturn($error);
    }

    /**
     * @param string $msg
     */
    private function responseReturn(string $msg)
    {
        throw new HttpResponseException(response()->json(['message' => $msg, 'code' => 0])->setEncodingOptions(JSON_UNESCAPED_UNICODE));
    }
}
