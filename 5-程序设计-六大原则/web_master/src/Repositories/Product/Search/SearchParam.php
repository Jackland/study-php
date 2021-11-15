<?php

namespace App\Repositories\Product\Search;

use App\Enums\Search\ComplexTransactions;
use App\Helper\CountryHelper;
use ModelCatalogSearch;

class SearchParam
{
    const SORT_TYPE =  [
        0 => 'p.sort_order',//默认排序
        1 => 'p.price',//价格排序
        2 => 'p.quantity',//上架数量排
        3 => 'pas.quantity',//总销量排序
        4 => 'p.date_added',//产品上架时间
        5 => 'return_rate',//退返率排序
        6 => 'marketingDiscount',//限时限量折扣排序
        7 => 'marketingPrice',//限时限量的价格
        8 => 'marketingQty',//限时限量库存排序
        9 => 'marketingExpirationTime',//限时限量失效时间排序
    ];

    const SCENE = [
        'b2b-search',
        'b2b-marketing_time_limit',//限时限量活动
        'shopify-app'
    ];
    // 必填项 有默认值
    private $sortType;
    private $orderBy;
    private $sponsored = [];
    private $pageNumber;
    private $pageSize;
    private $customerId;
    private $countryId;
    private $complexTrades;
    private $whIds;

    // 非必填项，无默认值
    /**
     * 搜索内容
     * @var
     */
    private $keyword;
    /**
     * 最小价格
     * @var
     */
    private $priceBoundMin;
    /**
     * 最大价格
     * @var
     */
    private $priceBoundMax;
    /**
     * 最小数量
     * @var
     */
    private $quantityBoundMin;
    /**
     * 最大数量
     * @var
     */
    private $quantityBoundMax;
    /**
     * 折扣价格下限
     * @var
     */
    private $discountPriceBoundMin;
    /**
     * 折扣价格上限
     * @var
     */
    private $discountPriceBoundMax;
    /**
     * 限时限量活动的产品状态
     * @var
     */
    private $marketingProductStatus;
    /**
     * 活动库存下限
     * @var
     */
    private $marketingQtyMin;
    /**
     * 活动库存上限
     * @var
     */
    private $marketingQtyMax;
    /**
     * 限时限量活动的请求时间 太平洋时区，格式:'年月日T时分秒'
     * @var
     */
    private $marketingTime;
    /**
     * 商品活动时间范围 单位/小时
     * 活动范围时间 与 原活动时间 只能传一个
     * 活动时间范围marketingTimeRange如果2小时内的就传2
     * 活动范围时间marketingTimeStart或marketingTimeEnd有值 商品活动时间范围marketingTimeRange必填
     * @var
     */
    private $marketingTimeRange;
    /**
     * 商品活动范围开始时间
     * @var 
     */
    private $marketingTimeStart;
    /**
     * 商品活动范围结束时间
     * @var 
     */
    private $marketingTimeEnd;
    /**
     * 限时限量活动开始前24小时预热展示
     * @var 
     */
    private $marketingPreHot;
    /**
     * 图片
     * @var
     */
    private $completeImages;
    /**
     * download
     * @var
     */
    private $resourcePackageDownloaded;
    /**
     * 心愿单
     * @var
     */
    private $saved;
    /**
     * 是否购买过
     * @var
     */
    private $purchased;
    /**
     * 是否建立联系
     * @var
     */
    private $sellerConnected;
    /**
     * seller id
     * @var
     */
    private $sellerId;
    /**
     * 分类
     * @var
     */
    private $category;

    /**
     * 构建映射关系，方便匹配数据
     * @var array
     */
    private $mapping;
    /**
     * 是否有广告位
     * @var
     */
    private $isSponsored;

    public function setIsSponsored(bool $is = true)
    {
        $this->isSponsored = $is;
    }

    public function getIsSponsored()
    {
        return $this->isSponsored;
    }

    private $scene;

    public function __construct($scene = self::SCENE[0],$config = [])
    {
        $this->mapping = $this->setMapping($config);
        $this->scene = $scene;
        $this->setIsSponsored();
    }

    public function getData(array $filterData, int $customerId = 0): array
    {
        $this->customerId = $customerId;
        $this->dealData(...func_get_args());
        $params = [
            'sortType' => $this->sortType, //
            'orderBy' => $this->orderBy, //
            'sponsored' => $this->sponsored,
            'pageNumber' => $this->pageNumber,
            'pageSize' => $this->pageSize,
            'customerId' => $this->customerId,
            'countryId' => $this->countryId,
            'complexTrades' => $this->complexTrades,
            'whIds' => $this->whIds, //筛选仓库
        ];
        isset($this->keyword) && $this->keyword != null && $params['keyword'] = $this->keyword; // 最小价格
        // 价格 & 数量
        $this->priceBoundMin !== null && $params['priceBoundMin'] = $this->priceBoundMin; // 最小价格
        $this->priceBoundMax !== null && $params['priceBoundMax'] = $this->priceBoundMax; // 最大价格
        $this->quantityBoundMin !== null && $params['quantityBoundMin'] = $this->quantityBoundMin;  // 最小数量
        $this->quantityBoundMax !== null && $params['quantityBoundMax'] = $this->quantityBoundMax;  // 最大数量
        $this->discountPriceBoundMin !== null && $params['discountPriceBoundMin'] = $this->discountPriceBoundMin; // 折扣价格下限
        $this->discountPriceBoundMax !== null && $params['discountPriceBoundMax'] = $this->discountPriceBoundMax; // 折扣价格上限
        $this->marketingQtyMin !== null && $params['marketingQtyMin'] = $this->marketingQtyMin;  // 活动库存下限
        $this->marketingQtyMax !== null && $params['marketingQtyMax'] = $this->marketingQtyMax;  // 活动库存上限
        $this->marketingTime !== null && $params['marketingTime'] = $this->marketingTime;        //限时限量活动的请求时间
        $this->marketingTimeRange !== null && $params['marketingTimeRange'] = $this->marketingTimeRange;//商品活动时间范围 单位/小时
        $this->marketingTimeStart !== null && $params['marketingTimeStart'] = $this->marketingTimeStart;//商品活动范围开始时间
        $this->marketingTimeEnd !== null && $params['marketingTimeEnd'] = $this->marketingTimeEnd;      //商品活动范围结束时间
        $this->marketingPreHot !== null && $params['marketingPreHot'] = $this->marketingPreHot;
        
        // 状态
        $this->completeImages !== null && $params['completeImages'] = $this->completeImages;  // 图片
        $this->resourcePackageDownloaded !== null && $params['resourcePackageDownloaded'] = $this->resourcePackageDownloaded;  // download
        $this->saved !== null && $params['saved'] = $this->saved;  // 心愿单
        $this->purchased !== null && $params['purchased'] = $this->purchased;  // 是否购买过
        $this->sellerConnected !== null && $params['sellerConnected'] = $this->sellerConnected;   // 是否建立联系

        // seller
        isset($this->sellerId) && $params['sellerId'] = $this->sellerId;  // seller id
        isset($this->marketingProductStatus) && $params['marketingProductStatus'] = $this->marketingProductStatus;  // 限时限量活动的产品状态
        isset($this->category) && $this->category !== 0 && $params['category'] = $this->category;  // 分类

        return $params;
    }

    public function dealData(array $filterData)
    {
        // 处理最小最大价格，最小最大库存
        $this->dealMinAndMaxColumn($filterData);
        // 处理搜索状态
        $this->dealStatusColumn($filterData);
        // 处理复杂交易
        $this->dealComplexTradesColumn($filterData);
        // inventory
        if (!isset($filterData['whId'])) {
            $this->whIds = [];
        } else {
            $this->whIds = $filterData['whId'];
        }
        // order by
        $this->orderBy = strtolower($filterData['order']) == 'desc' ? 'desc' : 'asc';
        // sort by
        $sortArr = self::SORT_TYPE;
        $this->sortType = array_search($filterData['sort'], $sortArr) ?? 0;
        $this->countryId = CountryHelper::getCountryByCode($filterData['country']);
        $this->setsponsored($filterData);

        $this->pageNumber = $filterData['page'];
        $this->pageSize = $filterData['limit'];
        $filterData['search'] != null && $this->keyword = $filterData['search'];
        isset($filterData['seller_id']) && $this->sellerId = $filterData['seller_id'];
        isset($filterData['marketingProductStatus'])  && $this->marketingProductStatus = $filterData['marketingProductStatus'];  // 限时限量活动的产品状态
        isset($filterData['marketingTime'])  && $this->marketingTime = $filterData['marketingTime'];  // 限时限量活动的请求时间
        isset($filterData['marketingTimeRange']) && $this->marketingTimeRange = $filterData['marketingTimeRange'];//商品活动时间范围 单位/小时
        isset($filterData['marketingTimeStart']) && $this->marketingTimeStart = $filterData['marketingTimeStart'];//商品活动范围开始时间
        isset($filterData['marketingTimeEnd']) && $this->marketingTimeEnd = $filterData['marketingTimeEnd'];//商品活动范围结束时间
        isset($filterData['marketingPreHot']) && $this->marketingPreHot = $filterData['marketingPreHot'];
        $filterData['category_id'] !== 0 && $this->category = $filterData['category_id'];  // 分类

    }

    private function setSponsored(array $filterData)
    {
        // 返还广告位
        // 只有默认排序才展示广告位
        switch ($this->scene){
            case self::SCENE[1]:
                $this->sponsored = [];
                break;
            default:
                if (
                (
                    (isset($filterData['seller_id']) && $filterData['seller_id'] != 0)
                    || ($this->sortType != 0)
                )
                ) {
                    $this->sponsored = [];
                } else {
                    /** @var ModelCatalogSearch $catalogSearch */
                    $catalogSearch = load()->model('catalog/search');
                    $this->sponsored = $catalogSearch->getCurrentAdvertProductKey();
                }
        }
    }

    private function dealMinAndMaxColumn($data)
    {
        $column = ['min_price', 'max_price', 'min_quantity', 'max_quantity','discountPriceBoundMin','discountPriceBoundMax','marketingQtyMin','marketingQtyMax'];
        foreach ($column as $items) {
            $this->{$this->mapping[$items]} = null;
            if (isset($data[$items]) && $data[$items]) {
                $this->{$this->mapping[$items]} = trim($data[$items]);
            }
        }

        if (isset($data['qty_status']) && $data['qty_status'] !== '' && $data['qty_status'] != 0) {
            if ($this->quantityBoundMin == '') {
                $this->quantityBoundMin = 1;
            }
        }

    }

    private function dealStatusColumn($data)
    {
        $column = ['img_status', 'download_status', 'wish_status', 'purchase_status', 'relation_status'];
        $compare = [
            0 => null,
            1 => true,
            2 => false,
            3 => null,
        ];
        foreach ($column as $items) {
            $this->{$this->mapping[$items]} = null;
            if (isset($data[$items])) {
                if (isset($compare[$data[$items]]) && in_array($compare[$data[$items]], [true, false])) {
                    $this->{$this->mapping[$items]} = $compare[$data[$items]];
                }
            }
        }
    }

    private function dealComplexTradesColumn($data)
    {
        $this->complexTrades = [];
        if (isset($data['rebates']) && $data['rebates']) {
            $this->complexTrades[] = ComplexTransactions::REBATE;
        }
        if (isset($data['margin']) && $data['margin']) {
            $this->complexTrades[] = ComplexTransactions::MARGIN;
        }
        if (isset($data['futures']) && $data['futures']) {
            $this->complexTrades[] = ComplexTransactions::FUTURE;
        }
    }

    public function setMapping(array $config): array
    {
        return array_merge([
            'min_price' => 'priceBoundMin',
            'max_price' => 'priceBoundMax',
            'min_quantity' => 'quantityBoundMin',
            'max_quantity' => 'quantityBoundMax',
            'discountPriceBoundMin' => 'discountPriceBoundMin',
            'discountPriceBoundMax' => 'discountPriceBoundMax',
            'marketingProductStatus' => 'marketingProductStatus',
            'marketingQtyMin' => 'marketingQtyMin',
            'marketingQtyMax' => 'marketingQtyMax',
            'img_status' => 'completeImages',
            'download_status' => 'resourcePackageDownloaded',
            'wish_status' => 'saved',
            'purchase_status' => 'purchased',
            'relation_status' => 'sellerConnected',
            'whId' => 'whIds',
            'order' => 'orderBy',
            'sort' => 'sortType',
            'country' => 'countryId',
            'seller_id' => 'sellerId',
            'search' => 'keyword',
            'category_id' => 'category',
            'limit' => 'pageSize',
            'page' => 'pageNumber',
        ], $config);
    }

}
