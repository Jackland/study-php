<?php

namespace App\Repositories\Product\Channel\Module;

use App\Enums\Product\Channel\ProductChannelDataType;
use App\Enums\Product\ProductType;
use App\Helper\CountryHelper;
use App\Logging\Logger;
use App\Models\Product\ProductExts;
use Carbon\Carbon;
use Psr\SimpleCache\InvalidArgumentException;

class HotNew extends BaseInfo
{
    /**
     * 获取new arrivals 中 hotNew产品信息
     * @param array $param
     * @return array
     * @throws InvalidArgumentException
     */
    public function getData(array $param): array
    {
        // 近3天内（含今天）新到货的产品，多于10个展示前10个（2行）
        $pageLimit = $this->getShowNum();
        $this->productIds = $this->getHotNewProductIds($pageLimit);
        return [
            'type' => ProductChannelDataType::PRODUCT,
            'data' => $this->productIds,
            'productIds' => $this->productIds,
        ];
    }

    /**
     * 近3天内（含今天）新到货的产品，多于10个展示前10个（2行），若无不展示该项,每个店铺最多展示2款产品
     * @param int $pageLimit
     * @return array
     * @throws InvalidArgumentException
     */
    private function getHotNewProductIds(int $pageLimit): array
    {
        $builder = ProductExts::query()->alias('pexts')
            ->joinRelations(['customerPartnerToProduct as ctp', 'product as p'])
            ->leftJoin('oc_customer as c', 'c.customer_id', 'ctp.customer_id')
            ->whereBetween('pexts.receive_date',
                [
                    Carbon::now()->subDays(3)->format('Y-m-d H:i:s'),
                    Carbon::now()->format('Y-m-d H:i:s')
                ]
            )
            ->where([
                'p.status' => 1,
                'p.buyer_flag' => 1,
                'p.product_type' => ProductType::NORMAL,
                'c.status' => 1,
                'c.country_id' => CountryHelper::getCountryByCode(session()->get('country')),
            ])
            ->where([
                ['p.quantity','>' ,0],
                ['p.part_flag','=' ,0],
            ])
            //->whereNotIn('p.product_id', $this->channelRepository->getUnavailableProductIds())
            ->whereNotIn('p.product_id', $this->channelRepository->delicacyManagementProductId((int)customer()->getId()))
            ->whereIn('c.customer_id', $this->channelRepository->getAvailableSellerId())
            ->whereNotNull('ctp.customer_id')
            ->whereNotNull('p.product_id')
            ->select(['ctp.customer_id', 'pexts.product_id', 'pexts.receive_date'])
            ->orderBy('pexts.receive_date', 'desc')
            ->limit(50); // 10取2 最坏情况 50取10 5个seller
        //logger::channelProducts(get_complete_sql($builder), 'notice');
        $hotNewList =  $builder->get();
        $ret = [];
        $retProductIds = [];
        if ($hotNewList) {
            foreach ($hotNewList as $item) {
                if (isset($ret[$item->customer_id]) && count($ret[$item->customer_id]) >= 2) {
                    continue;
                } else {
                    $ret[$item->customer_id][] = $item->product_id;
                    $retProductIds[] = $item->product_id;
                    if (count($retProductIds) >= $pageLimit) {
                        logger::channelProducts(['hot new', 'info',
                            logger::CONTEXT_VAR_DUMPER => [
                                'country_id' => CountryHelper::getCountryByCode(session()->get('country')),
                                'product_ids' => $hotNewList->take($pageLimit)->pluck('product_id')->toArray(),
                            ], // 按照可视化形式输出
                        ]);
                        return $retProductIds;
                    }
                }
            }
        }

        logger::channelProducts(['hot new', 'error',
            logger::CONTEXT_VAR_DUMPER => [
                'country_id' => CountryHelper::getCountryByCode(session()->get('country')),
                'product_ids' => $hotNewList->take($pageLimit)->pluck('product_id')->toArray(),
            ], // 按照可视化形式输出
        ]);
        return $hotNewList->take($pageLimit)->pluck('product_id')->toArray();
    }
}
