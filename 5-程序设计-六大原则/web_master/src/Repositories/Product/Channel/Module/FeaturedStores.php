<?php

namespace App\Repositories\Product\Channel\Module;

use App\Enums\Product\Channel\ProductChannelDataType;
use App\Helper\CountryHelper;
use App\Models\Product\Channel\HomePageConfig;
use App\Models\Product\Product;
use Exception;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Psr\SimpleCache\InvalidArgumentException;

class FeaturedStores extends BaseInfo
{
    private $buyerId;
    private $countryId;

    /**
     * 有些地方只是约束获取store信息 不需要产品信息
     * 设定这个有利于加快速度
     * @var bool
     */
    private $showProductInfo = true;
    private $showStoreInfo = true;

    private $dmProductIds;

    public function __construct()
    {
        parent::__construct();
        $this->buyerId = (int)customer()->getId();
        $this->countryId = (int)CountryHelper::getCountryByCode(session('country'));
        $this->dmProductIds = $this->channelRepository->delicacyManagementProductId($this->buyerId);
    }

    /**
     * @param array $param
     * @return array
     * @throws InvalidArgumentException
     */
    public function getData(array $param): array
    {
        $page = (int)($param['page'] ?? 1);
        $pageLimit = $this->getShowNum() ?? 12;
        $eachNumber = (int)($param['each_number'] ?? 3);
        [$sellerIds, $isEnd, $data] = $this->getSellerIds($page, $pageLimit);
        if (empty($sellerIds)) {
            return [
                'type' => ProductChannelDataType::STORES,
                'data' => [],
                'productIds' => $this->productIds,
                'is_end' => 1,
            ];
        }
        if ($this->isShowProductInfo()) {
            // 这里采取12个seller的union是为了避免一个seller产品过多导致速度慢
            $data = $data->whereIn('id', $sellerIds);
            foreach ($data as $key => $items) {
                $tmpProductIds = [];
                foreach ($items->productIds as $productId) {
                    if (!in_array($productId, $this->dmProductIds) && count($tmpProductIds) < $eachNumber) {
                        $tmpProductIds[] = $productId;
                    }
                }
                $items->productIds = $tmpProductIds;

            }
        }
        // 批量获取store 信息
        $tempData = [];
        $storeInfos = $this->channelRepository->getBaseStoreInfos($sellerIds, $param);
        // productId 无数据的时候排除在外
        foreach ($sellerIds as $sellerId) {
            $tmpProductIds = isset($data) && $data->where('id', $sellerId)
                ? $data->where('id', $sellerId)->first()->productIds
                : [];

            if ($tmpProductIds && count($tempData) < $this->getShowNum()) {
                $tempData[] = [
                    'store_info' => $storeInfos->get($sellerId, []),
                    'productIds' => $tmpProductIds,
                ];
                $this->productIds = array_merge($this->productIds, $tmpProductIds);
            }
        }

        return [
            'type' => ProductChannelDataType::STORES,
            'data' => $tempData,
            'productIds' => $this->productIds,
            'is_end' => $isEnd,
        ];
    }

    public function getHomePageFeatureStoresData(array $param): array
    {
        $page = 1;

        // 首页保证数据正确取两倍的店铺数量
        $pageLimit = $this->showStoreInfo ? $this->getShowNum() * 2 : $this->getShowNum();
        $eachNumber = (int)($param['each_number'] ?? 3);
        [$sellerIds, $isEnd, $data] = $this->getSellerIds($page, $pageLimit);
        if (empty($sellerIds) || !$sellerIds) {
            return [
                'type' => ProductChannelDataType::STORES,
                'data' => [],
                'productIds' => $this->productIds,
                'is_end' => 1,
            ];
        }
        if ($this->isShowProductInfo()) {
            // 这里采取12个seller的union是为了避免一个seller产品过多导致速度慢
            $data = $data->whereIn('id', $sellerIds);
            $sellerIds = [];
            foreach ($data as $key => $items) {
                $tmpProductIds = [];
                foreach ($items->productIds as $productId) {
                    if (!in_array($productId, $this->dmProductIds) && count($tmpProductIds) < $eachNumber) {
                        $tmpProductIds[] = $productId;
                    }
                }
                $items->productIds = $tmpProductIds;
                if (count($tmpProductIds) == $eachNumber && count($sellerIds) < ($this->getShowNum() + 1)) {
                    $sellerIds[] = $items->id;
                }

            }
        }
        // 批量获取store 信息
        $tempData = [];
        if ($this->showStoreInfo) {
            $storeInfos = $this->channelRepository->getBaseStoreInfos($sellerIds, $param);
            // productId 无数据的时候排除在外
            foreach ($sellerIds as $sellerId) {
                $tmpProductIds = isset($data) && $data->where('id', $sellerId)
                    ? $data->where('id', $sellerId)->first()->productIds
                    : [];

                if ($tmpProductIds
                    && count($tempData) < $this->getShowNum()
                    && count($tmpProductIds) == $eachNumber) {
                    $tempData[] = [
                        'store_info' => $storeInfos->get($sellerId, []),
                        'productIds' => $tmpProductIds,
                    ];
                    $this->productIds = array_merge($this->productIds, $tmpProductIds);
                }
            }

            if ($isEnd == 1
                && count($sellerIds) > $this->getShowNum()
                && count($tempData) == $this->getShowNum()
            ) {
                $isEnd = 0;
            }
        } else {
            foreach ($sellerIds as $sellerId) {
                $tempData[] = [
                    'store_info' => ['id' => $sellerId],
                    'productIds' => isset($data) && $data->where('id', $sellerId)
                        ? $data->where('id', $sellerId)->first()->productIds
                        : [],
                ];
            }
        }

        return [
            'type' => ProductChannelDataType::STORES,
            'data' => $tempData,
            'productIds' => $this->productIds,
            'is_end' => $isEnd,
        ];
    }

    /**
     * @param int $page
     * @param int $pageLimit
     * @return array
     */
    public function getSellerIds(int $page, int $pageLimit): array
    {
        $data = HomePageConfig::query()
            ->where(['type_id' => \App\Enums\Product\Channel\HomePageConfig::FEATURE_STORE,
                'country_id' => CountryHelper::getCountryByCode(session()->get('country'))
            ])
            ->value('content');
        $data = collect(json_decode($data));
        $total = count($data);
        $isEnd = $page * $pageLimit < $total ? 0 : 1;
        $res = $data->forPage($page, $pageLimit)->pluck('id')->toArray();
        return [$res, $isEnd, $data];
    }

    /**
     * @return bool
     */
    public function isShowProductInfo(): bool
    {
        return $this->showProductInfo;
    }

    /**
     * @param bool $showProductInfo
     */
    public function setShowProductInfo(bool $showProductInfo): void
    {
        $this->showProductInfo = $showProductInfo;
    }

    /**
     * @param bool $showStoreInfo
     */
    public function setShowStoreInfo(bool $showStoreInfo): void
    {
        $this->showStoreInfo = $showStoreInfo;
    }
}
