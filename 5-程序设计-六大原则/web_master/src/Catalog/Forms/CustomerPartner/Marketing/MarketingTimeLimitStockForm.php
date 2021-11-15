<?php

namespace App\Catalog\Forms\CustomerPartner\Marketing;

use App\Enums\Common\YesNoEnum;
use App\Enums\Marketing\MarketingTimeLimitConfig;
use App\Enums\Marketing\MarketingTimeLimitStatus;
use App\Helper\LangHelper;
use App\Logging\Logger;
use App\Models\Marketing\MarketingTimeLimit;
use App\Repositories\Marketing\MarketingTimeLimitDiscountRepository;
use App\Services\Marketing\MarketingTimeLimitDiscountService;
use Framework\Model\RequestForm\RequestForm;

class MarketingTimeLimitStockForm extends RequestForm
{
    public $time_limit_id = null;
    public $products = [];

    private $return_not_stock_skus = [];
    private $return_not_stock_code = 0;

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        $postData = $this->request->post();
        return [
            'time_limit_id' => ['required', 'integer', function ($attribute, $value, $fail) {
                $timeLimitDetail = MarketingTimeLimit::query()
                    ->where('seller_id', customer()->getId())
                    ->where('id', (int)$value)
                    ->where('is_del', YesNoEnum::NO)
                    ->first();
                if (empty($timeLimitDetail)) {
                    return $fail(__('当前活动不存在', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
                }
                $currentStatus = app(MarketingTimeLimitDiscountRepository::class)->getTimeLimitDiscountEffectiveStatus($timeLimitDetail);
                if ($currentStatus != MarketingTimeLimitStatus::ACTIVE) {
                    return $fail(__('补充活动库存失败', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
                }
                return true;
            }],
            'products' => ['required', 'array', function ($attribute, $value, $fail) use ($postData) {
                $postProducts = collect($value)->filter(function ($item) {
                    return $item['qty'] > 0;
                })->toArray();

                if (!is_array($postProducts) || empty($postProducts)) {
                    return $fail(__('请至少选择一个产品', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
                }
                if (count($postProducts) > 40) {
                    return $fail(__('产品数量不可超过40个', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
                }

                $returnProductInfos = app(MarketingTimeLimitDiscountRepository::class)->calculateTimeLimitDiscountIncrInfo($postData['time_limit_id'], customer()->getId());
                // 校验库存
                $maxIncrStockProducts = $returnProductInfos['max_incr_products'];
                foreach ($postProducts as $postProduct) {
                    $maxQty = $maxIncrStockProducts[$postProduct['product_id']]['max_stock_qty'] ?? 0;
                    if ($postProduct['qty'] > $maxQty) {
                        $this->return_not_stock_skus[] = $maxIncrStockProducts[$postProduct['product_id']]['sku'] ?? '';
                    }
                }

                if (!empty($this->return_not_stock_skus)) {
                    $this->return_not_stock_code = MarketingTimeLimitConfig::NOT_ENOUGH_STOCK_CODE;
                    return $fail(implode(',', $this->return_not_stock_skus));  // 库存不足
                }

                $this->products = $postProducts;
                return true;
            }],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getAttributeLabels(): array
    {
        return [
            'products' => 'The Products',
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
            if (!empty($this->return_not_stock_skus)) {
                $code = $this->return_not_stock_code;
                $isChinese = LangHelper::isChinese();
                $skus = $isChinese ? implode('、', $this->return_not_stock_skus) : implode(' and ', $this->return_not_stock_skus);
                if (count($this->return_not_stock_skus) > 1) {
                    $message = __(':skus等产品库存不足，无法补充库存', ['skus' => $skus], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount');
                } else {
                    $message = __(':skus产品库存不足，无法补充库存', ['skus' => $skus], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount');
                }
            } else {
                $code = 0;
                $message = $this->getFirstError();
            }
            return [
                'code' => $code,
                'msg' => $message,
            ];
        }

        try {
            dbTransaction(function (){
                app(MarketingTimeLimitDiscountService::class)->incrTimeLimitStockQty($this->time_limit_id, $this->products);
            });
            return [
                'code' => 200,
                'msg' => __('提交成功', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'),
            ];

        } catch (\Throwable $e) {
            Logger::timeLimitDiscount('补充活动库存出错：' . $e->getMessage());
            return [
                'code' => 0,
                'msg' => __('操作失败，请重试2', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'),
            ];
        }
    }

}
