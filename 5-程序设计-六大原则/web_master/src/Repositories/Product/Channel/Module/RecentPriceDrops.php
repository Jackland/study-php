<?php

namespace App\Repositories\Product\Channel\Module;

use App\Enums\Product\Channel\ProductChannelDataType;
use App\Enums\Product\ProductType;
use App\Models\Product\ProductCrontab;
use Carbon\Carbon;
use Psr\SimpleCache\InvalidArgumentException;

class RecentPriceDrops extends BaseInfo
{

    /**
     * @param array $param
     * @return array
     * @throws InvalidArgumentException
     */
    public function getData(array $param): array
    {
        $pageLimit = $this->getShowNum();
        $this->productIds = $this->getRecentPriceDropsProductIds($pageLimit);
        return [
            'type' => ProductChannelDataType::PRODUCT,
            'data' => $this->productIds,
            'productIds' => $this->productIds,
        ];
    }

    /**
     *  近2天内降价金额在固定金额以上最大的前10个产品；固定金额：美国/英国/德国1货币值，日本100日元；
     * @param $pageLimit
     * @return array
     * @throws InvalidArgumentException
     */
    private function getRecentPriceDropsProductIds($pageLimit): array
    {
        $priceList = ProductCrontab::query()->alias('pc')
            ->leftJoinRelations(['product as p'])
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', 'p.product_id')
            ->leftJoin('oc_customer as c', 'c.customer_id', 'ctp.customer_id')
            ->whereIn('c.customer_id', $this->channelRepository->getAvailableSellerId())
            ->where([
                'p.status' => 1,
                'p.buyer_flag' => 1,
                'c.country_id' => AMERICAN_COUNTRY_ID,
                'p.product_type' => ProductType::NORMAL,
                'c.status' => 1,
            ])
            ->where([
                ['p.quantity','>' ,0],
                ['p.part_flag','=' ,0],
            ])
            //->whereNotIn('p.product_id', $this->channelRepository->getUnavailableProductIds())
            ->whereNotIn('p.product_id', $this->channelRepository->delicacyManagementProductId((int)customer()->getId()))
            ->where('pc.drop_price_2','>',1) // 大于固定金额以上
            ->where('pc.seller_price_time', '>', Carbon::now()->subDays(2)->format('Y-m-d H:i:s'))
            ->select(['p.product_id'])
            ->selectRaw('pc.drop_price_2')
            ->orderBy('pc.drop_price_2', 'desc')
            ->get();
        $ret = [];
        if ($priceList->isNotEmpty()) {
            foreach ($priceList as $item) {
                if (isset($ret[$item->product_id])) {
                    continue;
                } else {
                    $ret[$item->product_id] = true;
                    if (count($ret) >= $pageLimit) {
                        return array_keys($ret);
                    }
                }
            }
        }

        return $priceList->take($pageLimit)->pluck('product_id')->toArray();
    }
}
