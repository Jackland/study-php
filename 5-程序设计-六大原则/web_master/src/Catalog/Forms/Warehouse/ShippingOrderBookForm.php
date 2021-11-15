<?php

namespace App\Catalog\Forms\Warehouse;

use App\Enums\Common\YesNoEnum;
use App\Enums\Warehouse\ShippingOrderBookSpecialProductType;
use App\Enums\Warehouse\ShippingOrderBookTermsOfDelivery;
use App\Helper\StringHelper;
use App\Helper\SummernoteHtmlEncodeHelper;
use Framework\Model\RequestForm\RequestForm;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class ShippingOrderBookForm extends RequestForm
{
    public $company_name;
    public $address;
    public $contacts;
    public $contact_number;
    public $consignee;
    public $notify_party;
    public $is_self_bond;
    public $bond_title;
    public $bond_address;
    public $bond_cin;
    public $marks_numbers;
    public $container_load;
    public $shipping_list;
    public $terms_of_delivery;
    public $is_use_trailer;
    public $trailer_address;
    public $trailer_contact;
    public $special_product_type;
    public $remark;

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        return [
            'company_name' => [
                'required',
                'regex:/^[^\x{4e00}-\x{9fa5}\x{3040}-\x{309f}]+$/u',
                function ($attribute, $value, $fail) {
                    if (! $this->checkStringLength($value, 200)) {
                        $fail($this->getErrorMsg($attribute));
                    }
                }
            ],
            'address' => [
                'required',
                'regex:/^[^\x{4e00}-\x{9fa5}\x{3040}-\x{309f}]+$/u',
                function ($attribute, $value, $fail) {
                    if (! $this->checkStringLength($value, 500)) {
                        $fail($this->getErrorMsg($attribute));
                    }
                }
            ],
            'contacts' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (! $this->checkStringLength($value, 50)) {
                        $fail($this->getErrorMsg($attribute));
                    }
                }
            ],
            'contact_number' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (! $this->checkStringLength($value, 20)) {
                        $fail($this->getErrorMsg($attribute));
                    }
                }
            ],
            'consignee' => [
                function ($attribute, $value, $fail) {
                    if (! empty($value)) {
                        if (! $this->checkStringLength($value, 200)) {
                            $fail($this->getErrorMsg($attribute));
                        }
                    }
                }
            ],
            'notify_party' => [
                function ($attribute, $value, $fail) {
                    if (! empty($value)) {
                        if (! $this->checkStringLength($value, 200)) {
                            $fail($this->getErrorMsg($attribute));
                        }
                    }
                }
            ],
            'is_self_bond' => [
                'required',
                Rule::in([YesNoEnum::NO, YesNoEnum::YES])
            ],
            'bond_title' => [
                'required_if:is_self_bond,1',
                function ($attribute, $value, $fail) {
                    if ($this->is_self_bond == YesNoEnum::YES && ! preg_match('/^[^\x{4e00}-\x{9fa5}\x{3040}-\x{309f}]+$/u', $value)) {
                        $fail($this->getErrorMsg($attribute));
                    }
                }
            ],
            'bond_address' => [
                'required_if:is_self_bond,1',
                function ($attribute, $value, $fail) {
                    if ($this->is_self_bond == YesNoEnum::YES && ! preg_match('/^[^\x{4e00}-\x{9fa5}\x{3040}-\x{309f}]+$/u', $value)) {
                        $fail($this->getErrorMsg($attribute));
                    }
                }
            ],
            'bond_cin' => [
                'required_if:is_self_bond,1',
                function ($attribute, $value, $fail) {
                    if ($this->is_self_bond == YesNoEnum::YES && ! $this->checkStringLength($value, 30)) {
                        $fail($this->getErrorMsg($attribute));
                    }
                }
            ],
            'marks_numbers' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (! $this->checkStringLength($value, 200)) {
                        $fail($this->getErrorMsg($attribute));
                    }
                }
            ],
            'container_load' => 'required|int|min:1|max:99999',
            'shipping_list' => [
                'required',
                'array',
                function ($attribute, $value, $fail) {
                    $result = $this->checkShippingList($value);
                    if ($result !== true) {
                        $fail($result);
                    }
                }
            ],
            'terms_of_delivery' => [
                'required',
                Rule::in(ShippingOrderBookTermsOfDelivery::getValues())
            ],
            'is_use_trailer' => [
                'required',
                Rule::in(YesNoEnum::getValues())
            ],
            'trailer_address' => [
                'required_if:is_use_trailer,1',
                function ($attribute, $value, $fail) {
                    if ($this->is_use_trailer == YesNoEnum::YES && ! $this->checkStringLength($value, 500)) {
                        $fail($this->getErrorMsg($attribute));
                    }
                }
            ],
            'trailer_contact' => [
                'required_if:is_use_trailer,1',
                function ($attribute, $value, $fail) {
                    if ($this->is_use_trailer == YesNoEnum::YES && !$this->checkStringLength($value, 200)) {
                        $fail($this->getErrorMsg($attribute));
                    }
                }
            ],
            'special_product_type' => [
                function ($attribute, $value, $fail) {
                    if (isset($value) && $value !== '') {
                        $value = explode(',', trim($value, ','));
                        foreach ($value as $v) {
                            if (! in_array($v, ShippingOrderBookSpecialProductType::getValues())) {
                                $fail($this->getErrorMsg($attribute));
                                return;
                            }
                        }
                    }
                }
            ],
            'remark' => [
                function ($attribute, $value, $fail) {
                    if (! empty($value) && !$this->checkStringLength($value, 200)) {
                        $fail($this->getErrorMsg($attribute));
                    }
                }
            ]
        ];
    }

    /**
     * 获取错误信息
     *
     * @param $attribute
     * @return mixed|string
     */
    private function getErrorMsg($attribute)
    {
        $msg = $this->getRuleMessages();
        if (isset($msg[$attribute . '.*'])) {
            return $msg[$attribute . '.*'];
        }

        return '';
    }

    /**
     * @inheritDoc
     */
    protected function getRuleMessages(): array
    {
        return [
            'company_name.*' => ':attribute 只允许使用英文字母、数字、符号和空格，并且长度在200个字符以内',
            'address.*' => ':attribute 只允许使用英文字母、数字、符号和空格，并且长度在500个字符以内',
            'contacts.*' => ':attribute 必填且为50个字符.',
            'contact_number.*' => ':attribute 必填且为20个字符.',
            'consignee.*' => ':attribute 长度只能为200个字符.',
            'notify_party.*' => ':attribute 长度只能为200个字符.',
            'is_self_bond.*' => ':attribute 无效.',
            'bond_title.*' => ':attribute 只允许使用英文字母、数字、符号和空格，并且长度在200个字符以内',
            'bond_address.*' => ':attribute 只允许使用英文字母、数字、符号和空格，并且长度在500个字符以内',
            'bond_cin.*' => ':attribute 长度只能为30个字符.',
            'marks_numbers.*' => ':attribute 长度只能为200个字符.',
            'container_load.*' => ':attribute 只能为1-9999之间的整数',
            'shipping_list.*' => ':attribute 必须填写.',
            'terms_of_delivery.*' => ':attribute 无效.',
            'is_use_trailer.*' => ':attribute 无效.',
            'trailer_address.*' => ':attribute 数据格式不正确.',
            'trailer_contact.*' => ':attribute 数据格式不正确.',
            'special_product_type.*' => ':attribute 数据格式不正确.',
            'remark.*' => ':attribute 长度只能为200个字符.'
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getAttributeLabels(): array
    {
        return [
            'company_name' => 'Company Name',
            'address' => 'Address',
            'contacts' => 'Contact Person\'s Name',
            'contact_number' => 'Phone Number',
            'consignee' => 'Consignee',
            'notify_party' => 'Notify Party',
            'is_self_bond' => 'Self-owned Bond',
            'bond_title' => 'Title',
            'bond_address' => 'Address',
            'bond_cin' => 'CBP Identification Number',
            'marks_numbers' => 'Marks & Numbers',
            'container_load' => 'Number of Containers',
            'shipping_list' => 'Shipping Information',
            'terms_of_delivery' => 'Terms of Delivery',
            'is_use_trailer' => 'Use of Trailer Service',
            'trailer_address' => 'Container Stuffing Location',
            'trailer_contact' => 'Contact Number for Trailer',
            'special_product_type' => 'Special Product Instructions',
            'remark' => 'Remarks',
        ];
    }

    /**
     * 检查表单数据
     *
     * @return array|bool
     */
    public function checkDataFormat()
    {
        if (! $this->isValidated()) {
            return [
                'code' => 0,
                'msg' => $this->getFirstError(),
            ];
        }

        $this->special_product_type = trim($this->special_product_type, ',');
        $this->shipping_list = json_encode($this->shipping_list, true);

        return true;
    }

    /**
     * 判断字符串是否符合长度（英文算一个字符，中文算2个字符）
     *
     * @param $str 需要判断的字符串
     * @param int $length 限制的长度
     * @return bool
     */
    private function checkStringLength($str, int $length)
    {
        $realLength = StringHelper::stringCharactersLen($str);

        if ($realLength > $length) {
            return false;
        }

        return true;
    }

    /**
     * 判断Shipping Information列表的每项数据
     *
     * @param $list
     * @return bool|string
     */
    private function checkShippingList($list)
    {
        $listCount = count($list);
        if ($listCount < 1) {
            return '必须要Shipping Information信息';
        }

        if ($listCount > 10) {
            return 'Shipping Information信息,最多支持10条';
        }

        foreach ($list as $item) {
            // 描述
            if (! isset($item['description']) || ! preg_match('/^[^\x{4e00}-\x{9fa5}\x{3040}-\x{309f}]+$/u', $item['description']) || ! $this->checkStringLength($item['description'], 200)) {
                return 'Description 必须填写且为200个字符';
            }
            // HS CODE
            if (! isset($item['hscode'])) {
                return 'HS CODE Please enter a 10-digit number';
            }
            $hsCodeNumer = preg_replace('/\D+/', '', $item['hscode']);
            if (mb_strlen($hsCodeNumer, 'utf-8') != 10 || mb_strlen($item['hscode'], 'utf-8') > 30) {
                return 'HS CODE Please enter a 10-digit number';
            }
            // 数量
            if (! isset($item['qty']) || ! is_numeric($item['qty']) || floor($item['qty']) != $item['qty'] || $item['qty'] < 1 || $item['qty'] > 99999) {
                return 'QTY&PACKING Please enter an integer between 1 and 99999';
            }
            // 重量
            if (! isset($item['weight']) || ! is_numeric($item['weight']) || ! preg_match('/^[0-9]{1,5}\.[0-9]{2}$/', $item['weight']) || $item['weight'] < 0.01 || $item['weight'] > 25000) {
                return 'G.W.(KGS) Please enter a number between 0.01 and 25000';
            }
            // 体积
            if (! isset($item['volume']) || ! is_numeric($item['volume']) || ! preg_match('/^[0-9]{1,3}\.[0-9]{2}$/', $item['volume'])  || $item['volume'] < 0.01 || $item['volume'] > 100) {
                return 'MEASUREMENT(CBM) Please enter a number between 0.01 and 100';
            }

            return true;
        }
    }

    /**
     * @inheritDoc
     */
    protected function getAutoLoadRequestData()
    {
        if ($this->request->get('route') == 'customerpartner/warehouse/receipt/checkShippingOrderBook') { // 检测接口
            $data = $this->request->post();
        } else if ($this->request->get('route') == 'customerpartner/warehouse/receipt/import') {//导入入口
            $data = json_decode(html_entity_decode(str_replace("&nbsp;", "", $this->request->post('bookData'))), true);
        } else { // 入库单保存时作为数据项校验
            $data = $this->request->post('bookData');
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $kk => $vv) {
                    if (is_array($vv)) {
                        foreach ($vv as $kkk => $vvv) {
                            $data[$key][$kk][$kkk] = SummernoteHtmlEncodeHelper::decode(trim($vvv), true);
                        }
                    } else {
                        $data[$key][$kk] = SummernoteHtmlEncodeHelper::decode(trim($vv), true);
                    }
                }
            } else {
                $data[$key] = SummernoteHtmlEncodeHelper::decode(trim($value), true);
            }
        }

        return $data;
    }

}
