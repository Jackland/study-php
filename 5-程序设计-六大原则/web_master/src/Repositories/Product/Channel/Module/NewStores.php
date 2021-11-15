<?php

namespace App\Repositories\Product\Channel\Module;

use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Product\Channel\ProductChannelDataType;
use App\Helper\CountryHelper;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Product\Channel\HomePageConfig;
use App\Models\Product\Product;
use Exception;
use Illuminate\Database\Query\Expression;
use Psr\SimpleCache\InvalidArgumentException;

class NewStores extends BaseInfo
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

    public function __construct()
    {
        parent::__construct();
        $this->buyerId = (int)customer()->getId();
        $this->countryId = (int)CountryHelper::getCountryByCode(session('country'));
    }

    /**
     * 获取数据
     * @param array $param
     * @return array
     * @throws Exception
     */
    public function getData(array $param): array
    {
        // new store 复用逻辑 new arrivals 取前三个
        // New store 频道页中  取值范围：取产品首次入库时间在60天内暂未进行评分标记为New Seller的店铺，任意随机选取20条 展示；
        [$sellerIds, $isEnd] = $this->getNewSellerIds();
        if (empty($sellerIds)) {
            return [
                'type' => ProductChannelDataType::STORES,
                'data' => [],
                'productIds' => $this->productIds,
                'is_end' => 1,
            ];
        }
        if ($this->showProductInfo) {
            $dmProductIds = $this->channelRepository->delicacyManagementProductId($this->buyerId);
            // 这里采取多个seller的union是为了避免一个seller产品过多导致速度慢
            $query = array_reduce($sellerIds, function ($val, $sellerId) use ($dmProductIds) {
                $query = Product::query()->alias('p')
                    ->select(['p.product_id', 'c.customer_id'])
                    ->selectRaw('ifnull(sps.quantity_30,0) as quantity_30')
                    ->selectRaw('md5(group_concat(ifnull(pa.associate_product_id,p.product_id) order by pa.associate_product_id asc)) as pmd5')
                    ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
                    ->leftJoin('oc_product_exts as ope', 'ope.product_id', '=', 'p.product_id')
                    ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
                    ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'ctp.customer_id')
                    ->leftJoin('oc_product_associate as pa', 'pa.product_id', '=', 'p.product_id')
                    ->leftJoin('tb_sys_product_sales as sps', 'sps.product_id', '=', 'p.product_id')
                    ->where([
                        'p.status' => 1,
                        'p.is_deleted' => 0,
                        'p.buyer_flag' => 1,
                        'p.product_type' => 0,
                        'c.status' => 1,
                        'p.part_flag' => 0,
                        'c.country_id' => $this->countryId,
                        'c.customer_id' => $sellerId
                    ])
                    ->when(!empty($dmProductIds), function ($q) use ($dmProductIds) {
                        $q->whereNotIn('p.product_id', $dmProductIds);
                    });
                // 上架库存>10的3个产品（若不足去除该条件）
                $tempQuery = (clone $query)
                    ->addSelect(new Expression('1 as quantity'))
                    ->where('p.quantity', '>', 10)
                    ->orderByDesc('sps.quantity_30')
                    ->groupBy('p.product_id')
                    ->limit(10);
                $tempQuery = db(new Expression('(' . get_complete_sql($tempQuery) . ') as t'))->groupBy(['t.pmd5']);
                $tempCount = db(new Expression('(' . get_complete_sql($tempQuery) . ') as t'))->count();
                if ($tempCount >= 3) {
                    $query = $tempQuery;
                } else {
                    $query = $query
                        ->addSelect('p.quantity')
                        ->orderByDesc('p.quantity')
                        ->orderByDesc('sps.quantity_30')
                        ->groupBy('p.product_id')
                        ->limit(10);
                }
                if (!$val) {
                    return $query;
                } else {
                    return $val->union($query);
                }
            }, null);

            // 排序 去重 实现同款不同色的去重方案
            $query = db(new Expression('(' . get_complete_sql($query) . ') as t'))
                ->orderByDesc('t.customer_id')
                ->orderByDesc('t.quantity')
                ->orderByDesc('t.quantity_30')
                ->orderByDesc('t.product_id')
                ->groupBy(['t.pmd5']);
            // 使用变量的方式获取前3个 注意:这里的customer_id一定要先排序，否则答案将会不准
            $query = db(new Expression('(' . get_complete_sql($query) . ') as t'))
                ->select('*')
                ->selectRaw('@ns_count := IF(@ns_customer_id= customer_id, @ns_count + 1, 1) as rank')
                ->selectRaw('@ns_customer_id := customer_id');
            $res = db(new Expression('(' . get_complete_sql($query) . ') as t'))
                ->where('t.rank', '<=', 3)
                ->get();
            // 获取所有产品id
            $this->productIds = $res->pluck('product_id')->toArray();
            // 按照customer id进行分组 后面用到
            $res = $res->groupBy('customer_id');
        }
        // 批量获取store 信息
        $tempData = [];
        if ($this->showStoreInfo) {
            $storeInfos = $this->channelRepository->getBaseStoreInfos($sellerIds);
            foreach ($sellerIds as $sellerId) {
                $tmpProductIds = isset($res) && $res->get($sellerId)
                    ? $res->get($sellerId)->pluck('product_id')->toArray()
                    : [];
                if ($tmpProductIds) {
                    $tempData[] = [
                        'store_info' => $storeInfos->get($sellerId, []),
                        'productIds' => $tmpProductIds,
                    ];
                }
            }
        } else {
            foreach ($sellerIds as $sellerId) {
                $tempData[] = [
                    'store_info' => ['id' => $sellerId],
                    'productIds' => isset($res) && $res->get($sellerId)
                        ? $res->get($sellerId)->pluck('product_id')->toArray()
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
     * 确定21个new seller
     */
    public function getNewSellerIds(): array
    {
        $data = HomePageConfig::query()
            ->where(['type_id' => \App\Enums\Product\Channel\HomePageConfig::NEW_STORE,
                'country_id' => CountryHelper::getCountryByCode(session()->get('country'))
            ])
            ->value('content');
        $data = collect(json_decode($data));
        $total = count($data);
        $ret = $data->take($this->getShowNum())->toArray();
        $isEnd = $total == count($ret) ? 1 : 0;
        return [$ret, $isEnd];
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
