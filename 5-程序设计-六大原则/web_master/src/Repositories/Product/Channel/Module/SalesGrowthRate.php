<?php

namespace App\Repositories\Product\Channel\Module;

use App\Enums\Product\Channel\ProductChannelDataType;
use App\Enums\Product\ProductType;
use App\Helper\CountryHelper;
use App\Logging\Logger;
use App\Models\Product\ProductCrontab;
use Psr\SimpleCache\InvalidArgumentException;

class SalesGrowthRate extends BaseInfo
{

    /**
     * @param array $param
     * @return array
     * @throws InvalidArgumentException
     */
    public function getData(array $param): array
    {
        $pageLimit = $this->getShowNum();
        $this->productIds = $this->getSalesGrowthRateProductIds($pageLimit);
        return [
            'type' => ProductChannelDataType::PRODUCT,
            'data' => $this->productIds,
            'productIds' => $this->productIds,
        ];
    }

    /**
     * @param int $pageLimit
     * @return array
     * @throws InvalidArgumentException
     */
    private function getSalesGrowthRateProductIds(int $pageLimit): array
    {
        //取值范围： 近7天环比销售额增长率最高的产品
        $salesGrowthRate = ProductCrontab::query()->alias('pc')
            ->joinRelations(['customerPartnerToProduct as ctp', 'product as p'])
            ->leftJoin('oc_customer as c', 'c.customer_id', 'ctp.customer_id')
            ->where([
                'p.status' => 1,
                'p.buyer_flag' => 1,
                'p.product_type' => ProductType::NORMAL,
                'c.country_id' => CountryHelper::getCountryByCode(session()->get('country')),
                'c.status' => 1,
            ])
            ->where([
                ['p.quantity','>' ,0],
                ['p.part_flag','=' ,0],
            ])
            //->whereNotIn('p.product_id', $this->channelRepository->getUnavailableProductIds())
            ->whereNotIn('p.product_id', $this->channelRepository->delicacyManagementProductId((int)customer()->getId()))
            ->whereIn('c.customer_id', $this->channelRepository->getAvailableSellerId())
            ->whereRaw('round((pc.amount_14 - pc.amount_7)) > 0 and pc.amount_7 > 0 and ifnull(((pc.amount_7 - (pc.amount_14 - pc.amount_7))/pc.amount_7),0) > 0')
            ->whereNotNull('ctp.customer_id')
            ->whereNotNull('p.product_id')
            ->selectRaw('ifnull(((pc.amount_7 - (pc.amount_14 - pc.amount_7))/pc.amount_7),0) as rate,pc.product_id,ctp.customer_id')
            ->orderByRaw('rate desc')
            ->limit(100);
        //logger::channelProducts(get_complete_sql($salesGrowthRate), 'notice');
        $ret = [];
        $retProductIds = [];
        foreach ($salesGrowthRate->cursor() as $item) {
            if (isset($ret[$item->customer_id]) && count($ret[$item->customer_id]) >= 2) {
                continue;
            } else {
                $ret[$item->customer_id][] = $item->product_id;
                $retProductIds[] = $item->product_id;
                if (count($retProductIds) >= $pageLimit) {
                    return $retProductIds;
                }
            }
        }
        return $salesGrowthRate->take($pageLimit)->pluck('product_id')->toArray();

    }
}
