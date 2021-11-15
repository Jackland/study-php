<?php

namespace App\Catalog\Forms\CustomerPartner\Marketing;

use App\Helper\StringHelper;
use App\Models\Marketing\MarketingDiscount;
use App\Services\Marketing\MarketingDiscountService;
use Carbon\Carbon;
use App\Helper\CountryHelper;
use Framework\Model\RequestForm\RequestForm;

class MarketingDiscountForm extends RequestForm
{
    public $discount_id = null;
    public $event_name;
    public $discount;
    public $product_scope;
    public $buyer_scope;
    public $buyer_ids = '';
    public $effective_time;
    public $expiration_time;

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        $timeZone = CountryHelper::getTimezone(customer()->getCountryId());
        $postData = $this->request->post();

        return [
            'discount_id' => [function ($attribute, $value, $fail) {
                $postDiscountId = (int)$value;
                if ($postDiscountId > 0) {
                    $existCheck = MarketingDiscount::query()->where('seller_id', customer()->getId())->where('id', (int)$value)->exists();
                    if (!$existCheck) {
                        return $fail(__('当前活动不可编辑', [], 'catalog/view/customerpartner/marketing_campaign/discount'));
                    }
                }
            }],
            'event_name' => ['required', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen(trim($value));
                if ($stringCharactersLen == 0) {
                    return $fail(__('请输入活动名称', [], 'catalog/view/customerpartner/marketing_campaign/discount'));
                }
                $characterLimit = 200;
                if ($stringCharactersLen > $characterLimit) {
                    return $fail(__('活动名称不能超过200字', [], 'catalog/view/customerpartner/marketing_campaign/discount'));
                }
                return true;
            }],
            'discount' => 'required|integer|min:1|max:70',
            'product_scope' => 'required|integer',
            'buyer_scope' => 'required|integer|in:-1,1',
            'buyer_ids' => 'required_if:buyer_scope,1',
            'effective_time' => ['required', function ($attribute, $value, $fail) use ($timeZone) {
                $currentDay = Carbon::now()->timezone($timeZone)->subDays(1)->startOfDay()->toDateTimeString();
                //都用当前国别时间判断
                $cvalue = Carbon::parse($value)->timezone($timeZone)->toDateTimeString();
                if ($currentDay > $cvalue) {
                    $fail(__('开始时间不可早于当前时间', [], 'catalog/view/customerpartner/marketing_campaign/discount'));
                }
                return;
            }],
            'expiration_time' => ['required', function ($attribute, $value, $fail) use ($timeZone, $postData) {
                $startDay = Carbon::parse($postData['effective_time'])->timezone($timeZone)->toDateTimeString();
                $endDay = Carbon::parse($value)->timezone($timeZone)->toDateTimeString();
                if ($startDay >= $endDay) {
                    $fail(__('开始时间必须要小于结束时间', [], 'catalog/view/customerpartner/marketing_campaign/discount'));
                }
                return;
            }],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getAttributeLabels(): array
    {
        return [
            'event_name' => 'Event Name',
            'discount' => 'Price Discount',
            'product_scope' => 'Items Covered',
            'buyer_scope' => 'Applicable Buyers',
            'buyer_ids' => 'Applicable Buyers',
            'effective_time' => 'Discount Validity',
            'expiration_time' => 'Discount Validity',
        ];
    }

    protected function getRuleMessages(): array
    {
        return [
            'required' => ':attribute can not be left blank.',
            'integer' => ':attribute must be a interger.',
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
     */
    public function save()
    {
        if (!$this->isValidated()) {
            return [
                'code' => 0,
                'msg' => $this->getFirstError(),
            ];
        }

        try {
            dbTransaction(function () {
                app(MarketingDiscountService::class)
                    ->storeDiscountInfo($this->discount_id, $this->event_name, $this->discount, $this->buyer_scope, $this->buyer_ids, $this->effective_time, $this->expiration_time);
            });
            return [
                'code' => 200,
                'msg' => __('提交成功', [], 'catalog/view/customerpartner/marketing_campaign/discount'),
            ];

        } catch (\Throwable $e) {
            return [
                'code' => 0,
                'msg' => $e->getMessage(),
            ];
        }
    }

}
