<?php

namespace App\Catalog\Forms\Message;

use App\Enums\Common\YesNoEnum;
use App\Enums\Message\MsgCommonWordsTypeCustomerType;
use App\Helper\StringHelper;
use App\Models\Message\MsgCommonWordsSuggest;
use App\Models\Message\MsgCommonWordsType;
use Framework\Exception\Exception;
use Framework\Model\RequestForm\RequestForm;

class CommonWordsForm  extends RequestForm
{
    public $types;
    public $content;

    /**
     * 验证
     * @return array
     */
    protected function getRules(): array
    {
        $strTypes = MsgCommonWordsType::query()
            ->whereIn('customer_type', MsgCommonWordsTypeCustomerType::getTypesByCustomer())
            ->where('is_deleted', YesNoEnum::NO)
            ->pluck('id')
            ->join(',');

        return [
            'types' => 'required|array',
            'types.*' => 'required|in:' . $strTypes,
            'content' => ['required', function ($attribute, $value, $fail) {
                if (!empty($value) && StringHelper::stringCharactersLen($value) > 500) {
                    $fail('content must be less than 500 characters.');
                }
            }],
        ];
    }

    /**
     * 保存
     * @return bool
     * @throws Exception
     */
    public function save()
    {
        if (!$this->isValidated()) {
            throw new Exception($this->getFirstError());
        }

        return MsgCommonWordsSuggest::query()->insert([
            'customer_id' => customer()->getId(),
            'type_ids' => join(',', $this->types),
            'content' => $this->content,
        ]);
    }
}
