<?php


namespace App\Catalog\Forms\Seller;


use App\Enums\Country\Country;
use App\Helper\CountryHelper;
use App\Models\Seller\SellerProductRatio;
use App\Models\Seller\SellerProductRatioLog;
use App\Repositories\Seller\SellerProductRatioRepository;
use Carbon\Carbon;
use Framework\Model\RequestForm\RequestForm;

class SellerProductRatioForm extends RequestForm
{
    public $product_ratio;
    public $effective_time;

    protected function getRules(): array
    {
        // 计算对应时区明天对应的美国时间，选择的司机必须大于等于该时间
        $afterDate = Carbon::tomorrow($this->getCustomerTimezone()) // 获取对应时区明天的0点
        ->setTimezone(CountryHelper::getTimezone(Country::AMERICAN))// 转换成美国时间
        ->toDateTimeString();
        return [
            'product_ratio' => 'required|numeric|between:0,1',// 不为空的数字，必须大于等于0和小于等于1
            'effective_time' => 'nullable|date|after_or_equal:' . $afterDate,// 可以为空，格式为日期或者日期时间，必须大于等于明天
        ];
    }

    protected function getAttributeLabels(): array
    {
        return [
            'product_ratio' => 'Product Value Ratio',
            'effective_time' => 'Effective Date',
        ];
    }

    protected function getRuleMessages(): array
    {
        return [
            'effective_time.after_or_equal' => 'The Effective Date must be a date after or equal to tomorrow.'
        ];
    }

    /**
     * 保存
     *
     * @return array ['success'=>true|false,'error'=>'xxxx']
     * @throws \Throwable
     */
    public function save(): array
    {
        if (!$this->isValidated()) {
            // 校验不通过返回错误信息
            return [
                'success' => false,
                'error' => $this->getFirstError(),
            ];
        }
        $sellerId = customer()->getId();
        $nowDate = Carbon::now();
        $sellerProductRatioRepository = app(SellerProductRatioRepository::class);
        // 查询之前是否设置过
        $oldRatio = $sellerProductRatioRepository->getProductRatioBySellerId($sellerId);
        $productRatio = $this->request->attributes->get('product_ratio');
        $effectiveTime = $this->request->attributes->get('effective_time');
        if ($effectiveTime) {
            // 将时间格式化
            $effectiveTime = Carbon::parse($effectiveTime)->format('Y-m-d H:00:00');
            // 如果传了生效时间，则是定时生效
            // 只更新待生效数据，当前生效数据不动
            $ratioData = [
                'product_ratio_next' => $productRatio,
                'effective_time_next' => $effectiveTime,
            ];
        } else {
            // 没有传生效时间，则是立即生效
            // 立即生效清空待生效的数据
            $ratioData = [
                'product_ratio' => $productRatio,
                'effective_time' => $nowDate,
                'product_ratio_next' => null,
                'effective_time_next' => null,
            ];
        }
        // 日志数据
        $logData = [
            'create_time' => $nowDate
        ];
        if ($oldRatio) {
            // 如果是更新，备份历史数据
            $logData['old_config'] = $this->initRatioConfigData($oldRatio);
        } else {
            // 需要新增，增加点基础数据
            $ratioData = array_merge($ratioData, [
                'seller_id' => $sellerId,
                'create_time' => $nowDate
            ]);
            if (!isset($ratioData['product_ratio'])) {
                // 新建的时候如果没有设置立即生效，当前生效的值设置成默认值
                $ratioData['product_ratio'] = $sellerProductRatioRepository->getCountryDefaultRatio(customer()->getCountryId());
                $ratioData['effective_time'] = $sellerProductRatioRepository->getCountryDefaultEffectiveTime(customer()->getCountryId());
            }
        }
        // 开始处理数据库操作
        dbTransaction(function () use ($oldRatio, $ratioData, $logData) {
            if ($oldRatio) {
                // 存在则修改
                $newRatio = clone $oldRatio;
                $newRatio->update($ratioData);
            } else {
                // 不存在新增
                $newRatio = SellerProductRatio::query()->create($ratioData);
            }
            $logData['new_config'] = $this->initRatioConfigData($newRatio);
            // 记录日志
            $newRatio->logs()->save(new SellerProductRatioLog($logData));
        });

        return [
            'success' => true
        ];
    }

    /**
     * 生成插入到seller_product_ratio_log 的config数据
     *
     * @param SellerProductRatio $productRatio
     * @return false|string
     */
    private function initRatioConfigData(SellerProductRatio $productRatio)
    {
        if ($productRatio->effective_time_next) {
            // 如果存在待生效数据
            return json_encode([
                'product_ratio' => $productRatio->product_ratio_next,
                'service_ratio' => $productRatio->service_ratio_next,
                'effective_time' => $productRatio->effective_time_next->toDateTimeString()
            ]);
        } else {
            return json_encode([
                'product_ratio' => $productRatio->product_ratio,
                'service_ratio' => $productRatio->service_ratio,
                'effective_time' => $productRatio->effective_time->toDateTimeString()
            ]);
        }
    }

    /**
     * @return string|null
     */
    private function getCustomerTimezone(): ?string
    {
        return CountryHelper::getTimezone(customer()->getCountryId());
    }
}
