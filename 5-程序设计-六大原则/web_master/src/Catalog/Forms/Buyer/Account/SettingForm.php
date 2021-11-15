<?php

namespace App\Catalog\Forms\Buyer\Account;

use App\Models\Buyer\Buyer;
use App\Models\Setting\MessageSetting;
use Framework\Model\RequestForm\RequestForm;

class SettingForm extends RequestForm
{
    public $selector_cellphone;
    public $selector_wechat;
    public $selector_qq;
    public $selector_country_id;
    public $is_in_seller_recommend;
    public $selector_name;

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        return [
            'selector_wechat' => [function ($attribute, $value, $fail) {
                if ((bool)$value) {
                    $str = preg_match('/^[_a-zA-Z0-9]{1,20}+$/isu', $value);
                    if ((bool)$str === false) {
                        $fail('The wechat may not be greater than 1-20 characters.');
                        return;
                    }
                }
            }],
            'selector_qq' => [function ($attribute, $value, $fail) {
                if ((bool)$value) {
                    $str = preg_match('/^\d{1,20}$/isu', $value);
                    if ((bool)$str === false) {
                        $fail('The qq may not be greater than 1-20 characters.');
                        return;
                    }
                }
            }],
            'selector_name' => [function ($attribute, $value, $fail) {
                if ((bool)$value) {
                    if (strlen($value) < 1 || strlen($value) > 50) {
                        $fail('The selector_name may not be greater than 1-50 characters.');
                        return;
                    }
                }
            }],

            'selector_country_id' => 'required|int',
            'is_in_seller_recommend' => 'required|int',
            'selector_cellphone' => 'required|string|max:50|min:3',//手机号
        ];
    }


    /**
     * @return string[]
     */
    protected function getRuleMessages(): array
    {
        return [
            'selector_cellphone.*' => 'Please enter a phone number between 3-20 digits.'
        ];
    }


    /**
     * @inheritDoc
     */
    protected function getAutoLoadRequestData()
    {
        return $this->request->post();
    }

    /**
     * @return array
     * @throws \Throwable
     */
    public function save()
    {
        $customerId = customer()->getId();
        $post = $this->getAutoLoadRequestData();
        if (!$this->isValidated()) {
            // 校验不通过返回错误信息
            return [
                'code' => 0,
                'area' =>$this->getValidator()->errors(),
                'msg' => $this->getFirstError() ?? 'Request error.',
            ];
        }

        $updateTemp = [
            'selector_country_id' => $post['selector_country_id'],
            'selector_name' => $post['selector_name'] ?? '',
            'selector_qq' => $post['selector_qq'] ?? '',
            'selector_wechat' => $post['selector_wechat'] ?? '',
            'selector_cellphone' => $post['selector_cellphone'] ?? ''
        ];
        return dbTransaction(function () use ($updateTemp, $post, $customerId) {
            try {
                Buyer::query()->where('buyer_id', $customerId)->update($updateTemp);
                MessageSetting::query()->updateOrInsert(['customer_id' => $customerId], [
                    'customer_id' => $customerId,
                    'is_in_seller_recommend' => (int)$post['is_in_seller_recommend']
                ]);
                return [
                    'code' => 200,
                    'msg' => 'Successfully.',
                ];
            } catch (\Exception $e) {
                return [
                    'code' => 0,
                    'msg' => $e->getMessage()
                ];
            }
        });

    }
}
