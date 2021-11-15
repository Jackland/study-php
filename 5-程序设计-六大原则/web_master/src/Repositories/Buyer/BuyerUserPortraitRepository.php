<?php

namespace App\Repositories\Buyer;

use App\Enums\Buyer\BuyerUserPortraitComplexCompleteRate;
use App\Enums\Buyer\BuyerUserPortraitReturnRate;
use App\Models\Buyer\BuyerUserPortrait;
use App\Repositories\Product\CategoryRepository;

class BuyerUserPortraitRepository
{
    /**
     * 格式化用户画像数据
     * @param BuyerUserPortrait|array $userPortrait
     * @param array $formatAttributes 指定需要格式化的数据 [原userPortrait的键 => 格式化过后的键]
     * @return array
     * 参考原逻辑重新整理
     * @see ControllerCommonUserPortrait::get_user_portrait_data()
     */
    public function formatUserPortrait($userPortrait, array $formatAttributes)
    {
        $formatted = [];

        if (!$formatAttributes) {
            $formatAttributes = array_keys($userPortrait->getAttributes());
        }
        // 近30天销售
        if (array_key_exists('monthly_sales_count', $formatAttributes)) {
            $key = $formatAttributes['monthly_sales_count'];
            $formatted[$key] = (function ($userPortrait) {
                if (isset($userPortrait['registration_date'])) {
                    if (time() - strtotime($userPortrait['registration_date']) < 30 * 86400) {
                        return __('N/A', [], 'common');
                    }
                }
                $moreThanMap = [
                    10000 => '10000+',
                    1000 => '1000+',
                    100 => '100+',
                    0 => __('Less than :num', ['num' => 100], 'repositories/buyer'),
                ];
                foreach ($moreThanMap as $max => $text) {
                    if ($userPortrait['monthly_sales_count'] > $max) {
                        return $text;
                    }
                }
                return 0;
            })($userPortrait);
        }
        // 退返品率
        if (array_key_exists('return_rate', $formatAttributes)) {
            $key = $formatAttributes['return_rate'];
            $formatted[$key] = BuyerUserPortraitReturnRate::getDescription($userPortrait['return_rate'], __('N/A', [], 'common'));
        }
        // 复杂交易度
        if (array_key_exists('complex_complete_rate', $formatAttributes)) {
            $key = $formatAttributes['complex_complete_rate'];
            $formatted[$key] = BuyerUserPortraitComplexCompleteRate::getDescription($userPortrait['complex_complete_rate'], __('N/A', [], 'common'));
        }
        // 主营品类
        if (array_key_exists('main_category_id', $formatAttributes)) {
            $key = $formatAttributes['main_category_id'];
            $mainCategory = __('N/A', [], 'common');
            if ($userPortrait['main_category_id']) {
                $mainCategory = app(CategoryRepository::class)->getFullPathName($userPortrait['main_category_id']) ?: __('N/A', [], 'common');
            }
            $formatted[$key] = $mainCategory;
        }
        // 首单交易时间
        if (array_key_exists('first_order_date', $formatAttributes)) {
            $key = $formatAttributes['first_order_date'];
            $formatted[$key] = (function ($date) {
                if (!$date) {
                    return __('N/A', [], 'common');
                }
                if (strtotime($date) < strtotime('2010-01-01')) {
                    return __('N/A', [], 'common');
                }
                $diff = time() - strtotime($date);
                if ($diff < 30 * 86400) {
                    return __('less than one month', [], 'repositories/buyer');
                }
                $days = $diff / 86400;
                $month = (int)($days / 30);
                if ($days % 30 >= 15) {
                    $month++;
                }
                return __(':num month(s) ago', ['num' => $month], 'repositories/buyer');
            })($userPortrait['first_order_date']);
        }

        return $formatted;
    }
}
