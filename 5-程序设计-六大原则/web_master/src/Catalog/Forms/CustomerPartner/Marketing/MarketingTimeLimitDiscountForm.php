<?php

namespace App\Catalog\Forms\CustomerPartner\Marketing;

use App\Enums\Common\YesNoEnum;
use App\Enums\Product\ProductTransactionType;
use App\Helper\LangHelper;
use App\Helper\StringHelper;
use App\Logging\Logger;
use App\Models\Marketing\MarketingTimeLimit;
use App\Services\Marketing\MarketingTimeLimitDiscountService;
use Carbon\Carbon;
use App\Helper\CountryHelper;
use Framework\Model\RequestForm\RequestForm;

class MarketingTimeLimitDiscountForm extends RequestForm
{
    public $time_limit_id = null;
    public $name;
    public $transaction_type;
    public $low_qty;
    public $store_nav_show;
    public $pre_hot;
    public $effective_time;
    public $expiration_time;
    public $products = [];

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        $timeZone = CountryHelper::getTimezone(customer()->getCountryId());
        $postData = $this->request->post();
        $allowedTransactionTypes = ProductTransactionType::getMarketingTimeLimitTransactionType();
        return [
            'name' => ['required', 'string', function ($attribute, $value, $fail) {
                $stringCharactersLen = StringHelper::stringCharactersLen(trim($value));
                if ($stringCharactersLen == 0) {
                    return $fail(__('请输入活动名称', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
                }
                $characterLimit = 200;
                if ($stringCharactersLen > $characterLimit) {
                    return $fail(__('活动名称不能超过200字', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
                }
                return true;
            }],
            'effective_time' => ['required', function ($attribute, $value, $fail) use ($timeZone) {
                $currentDay = Carbon::now()->timezone($timeZone)->subDays(1)->startOfDay()->toDateTimeString();
                //都用当前国别时间判断
                $cvalue = Carbon::parse($value)->timezone($timeZone)->toDateTimeString();
                if ($currentDay > $cvalue) {
                    return $fail(__('开始时间不可早于当前时间', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
                }
                return true;
            }],
            'expiration_time' => ['required', function ($attribute, $value, $fail) use ($timeZone, $postData) {
                $startDay = Carbon::parse($postData['effective_time'])->timezone($timeZone)->toDateTimeString();
                $endDay = Carbon::parse($value)->timezone($timeZone)->toDateTimeString();
                if ($startDay >= $endDay) {
                    return $fail(__('开始时间必须要小于结束时间', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
                }
                $eventExist = app(MarketingTimeLimitDiscountService::class)->calculateTimeLimitTimePeriodRepeat($startDay, $endDay, $postData['time_limit_id'] ?? 0);
                if ($eventExist) {
                    return $fail(__('活动时间与已有活动时间冲突', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
                }
                return true;
            }],
            'time_limit_id' => [function ($attribute, $value, $fail) {
                $timeLimitId = (int)$value;
                if ($timeLimitId > 0) {
                    $timeLimitDetail = MarketingTimeLimit::query()
                        ->where('seller_id', customer()->getId())
                        ->where('id', (int)$value)
                        ->where('is_del', YesNoEnum::NO)
                        ->first();
                    if (empty($timeLimitDetail)) {
                        return $fail(__('当前活动不可编辑', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
                    }
                    //是否可用编辑
                    if (!app(MarketingTimeLimitDiscountService::class)->calculateTimeLimitStatus($timeLimitDetail)) {
                        return $fail(__('当前活动不可编辑', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
                    }
                }
                return true;
            }],
            'transaction_type' => ['required', 'string', function ($attribute, $value, $fail) use ($allowedTransactionTypes) {
                if ($value !== '-1') {
                    $transactionTypes = explode(',', $value);
                    if (empty($transactionTypes)) {
                        return $fail(__('交易方式错误', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
                    }
                    if (array_diff($transactionTypes, $allowedTransactionTypes)) {
                        return $fail(__('交易方式错误', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
                    }
                }
                return true;
            }],
            'low_qty' => 'required|integer|min:1|max:999',
            'store_nav_show' => 'required|integer|in:0,1',
            'pre_hot' => 'required|integer|in:0,1',
            'products' => ['required', 'array', function ($attribute, $value, $fail) {
                if (!is_array($value) || empty($value)) {
                    return $fail(__('请至少选择一个产品', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
                }
                if (count($value) > 40) {
                    return $fail(__('产品数量不可超过40个', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
                }
                return true;
            }],
            'products.*.product_id' => 'required|integer',
            'products.*.origin_qty' => 'required|integer',
            'products.*.discount' => 'required|integer|min:1|max:99',
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getAttributeLabels(): array
    {
        return [
            'name' => 'The Event Name',
            'effective_time' => 'Duration',
            'expiration_time' => 'Duration',
            'low_qty' => 'Minimum Purchase QTY',
            'products' => 'The Products',
            'store_nav_show' => 'Store Navigation Bar Menu',
            'pre_hot' => 'Prepare 24h Before Promotion Starts',
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

        $postToData = [
            'time_limit_id' => $this->time_limit_id,
            'name' => $this->name,
            'transaction_type' => $this->transaction_type,
            'low_qty' => $this->low_qty,
            'store_nav_show' => $this->store_nav_show,
            'pre_hot' => $this->pre_hot,
            'effective_time' => $this->effective_time,
            'expiration_time' => $this->expiration_time,
            'products' => $this->products,
        ];

        try {
            dbTransaction(function () use ($postToData) {
                app(MarketingTimeLimitDiscountService::class)->storeTimeLimitDiscountInfo($postToData);
            });
            return [
                'code' => 200,
                'msg' => __('提交成功', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'),
            ];

        } catch (\Throwable $e) {
            $message = __('操作失败，请重试2',[],'catalog/view/customerpartner/marketing_campaign/time_limit_discount');
            $isChinese = LangHelper::isChinese();
            switch ($e->getCode()) {
                case 0:
                    Logger::timeLimitDiscount($e->getMessage());
                    break;
                case 801:
                    $skus = $e->getMessage();
                    $skus = $isChinese ? $skus : implode(' and ', explode('、', $skus));
                    $message = __('产品:skus已失效，请移除', ['skus' => $skus], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount');
                    break;
                case 802:
                    $skus = $e->getMessage();
                    $skus = $isChinese ? $skus : implode(' and ', explode('、', $skus));
                    $message = __('产品:skus活动库存超出当前上架库存，请重新设置', ['skus' => $skus], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount');
                    break;
            }

            return [
                'code' => 0,
                'msg' => $message
            ];
        }
    }

}
