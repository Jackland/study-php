<?php

namespace App\Repositories\Product\Channel\Module;

use App\Enums\Product\Channel\ProductChannelDataType;
use App\Helper\CountryHelper;
use App\Models\Customer\CustomerScoreSub;
use App\Models\Product\Product;
use Exception;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Carbon;
use Psr\SimpleCache\InvalidArgumentException;

class WellStocked extends BaseInfo
{
    private $buyerId;
    private $countryId;

    public function __construct()
    {
        parent::__construct();
        $this->buyerId = (int)customer()->getId();
        $this->countryId = (int)CountryHelper::getCountryByCode(session('country'));
    }

    /**
     * @param array $param
     * @return array
     * @throws Exception
     */
    public function getData(array $param): array
    {
        // 货源稳定的Store为货源最为稳定的前3个Store，根据Seller的断货率评分取值；
        $sellerIds = $param['sellerIds'] ?? $this->getSellerIds();
        if (empty($sellerIds)) {
            return [
                'type' => ProductChannelDataType::STORES,
                'data' => [],
                'productIds' => $this->productIds,
                'is_end' => 0,
            ];
        }
        // 这里采取20个seller的union是为了避免一个seller产品过多导致速度慢
        $dmProductIds = $this->channelRepository->delicacyManagementProductId($this->buyerId);
        $prevData = $param['prevData'] ?? [];
        $query = array_reduce($sellerIds, function ($val, $sellerId) use ($prevData, $dmProductIds) {
            $query = Product::query()->alias('p')
                ->select(['p.product_id', 'c.customer_id', 'p.quantity'])
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
                    'p.part_flag' => 0,
                    'c.status' => 1,
                    'c.country_id' => $this->countryId,
                    'c.customer_id' => $sellerId
                ])
                ->when(!empty($dmProductIds), function ($q) use ($dmProductIds) {
                    $q->whereNotIn('p.product_id', $dmProductIds);
                })
                ->when(
                    in_array($sellerId, $prevData),
                    function ($q) {
                        $q->where('p.quantity', '>=', 0);
                        $q->orderByDesc('p.quantity');
                        $q->selectRaw('p.quantity as sort_quantity');
                    },
                    function ($q) {
                        $q->where('p.quantity', '>', 10);
                        $q->selectRaw('ifnull(sps.quantity_30,0) as sort_quantity');
                    }
                )
                ->orderByDesc('sps.quantity_30')
                ->groupBy('p.product_id')
                ->limit(10);
            if (!$val) {
                return $query;
            } else {
                return $val->union($query);
            }
        }, null);

        // 排序 去重 实现同款不同色的去重方案
        $query = db(new Expression('(' . get_complete_sql($query) . ') as t'))
            ->orderByDesc('t.customer_id')
            ->orderByDesc('t.sort_quantity')
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
        // 实现方式 不足3个 把条件约束
        if (!isset($param['prevData'])) {
            $prevData = [];
            foreach ($sellerIds as $sellerId) {
                $tempProductIds = $res->has($sellerId)
                    ? $res->get($sellerId)->pluck('product_id')->toArray()
                    : [];
                if (count($tempProductIds) < 3) {
                    $prevData[] = $sellerId;
                }
            }
            if (!empty($prevData)) {
                $param['prevData'] = $prevData;
                $param['sellerIds'] = $sellerIds;
                return $this->getData($param);
            }
        }

        // 批量获取store 信息
        $storeInfos = $this->channelRepository->getBaseStoreInfos($sellerIds);
        $tempData = [];
        foreach ($sellerIds as $sellerId) {
            $tempData[] = [
                'store_info' => $storeInfos->get($sellerId, []),
                'productIds' => isset($res) && $res->get($sellerId)
                    ? $res->get($sellerId)->pluck('product_id')->toArray()
                    : [],
            ];
        }
        return [
            'type' => ProductChannelDataType::STORES,
            'data' => $tempData,
            'productIds' => $this->productIds,
            'is_end' => 0,
        ];
    }

    /**
     * 确定货源稳定的3个seller
     * @throws InvalidArgumentException
     */
    private function getSellerIds(): array
    {
        $dmProductIds = $this->channelRepository->delicacyManagementProductId($this->buyerId);
        // 获取以半年总计12个周期的评分数据
        // 实现方法 由于对于周期的执行时间均为每个月的1号 16号 因此回退到半年前的某个时间点 1号的0点 或者 16号的0点
        $nowTime = Carbon::now()->subMonths(6);
        $scoreTaskNumberPrefix = $nowTime->format('Ym');
        $scoreTaskNumberCur = $nowTime->format('Ymd');
        if ($scoreTaskNumberCur >= $scoreTaskNumberPrefix . '01' && $scoreTaskNumberCur < $scoreTaskNumberPrefix . '16') {
            $scoreTaskNumber = $scoreTaskNumberPrefix . '01';
        } else {
            $scoreTaskNumber = $scoreTaskNumberPrefix . '16';
        }
        $startTime = Carbon::createFromFormat('Ymd', $scoreTaskNumber)->startOfDay()->format('Y-m-d H:i:s');
        return CustomerScoreSub::query()->alias('css')
            ->select('css.customer_id')
            ->selectRaw('sum(css.score) as sum_score')
            ->leftJoin('oc_customer as c', 'css.customer_id', '=', 'c.customer_id')
            ->whereIn('c.customer_id', $this->channelRepository->getAvailableSellerId())
            ->whereExists(function ($q) use ($dmProductIds) { // 存在有效产品
                $q->select('*')
                    ->from('oc_customerpartner_to_product as ctp_t')
                    ->leftJoin('oc_product as p_t', 'ctp_t.product_id', '=', 'p_t.product_id')
                    ->whereRaw('ctp_t.customer_id = c.customer_id')
                    ->where([
                        'p_t.status' => 1,
                        'p_t.is_deleted' => 0,
                        'p_t.buyer_flag' => 1,
                        'p_t.product_type' => 0,
                        'p_t.part_flag' => 0,
                    ])
                    ->when(!empty($dmProductIds), function ($q) use ($dmProductIds) {
                        $q->whereNotIn('p_t.product_id', $dmProductIds);
                    });
            })
            ->where([
                'c.status' => 1,
                'c.country_id' => $this->countryId,
                'css.dimension_id' => 8
            ])
            ->where('css.create_time', '>=', $startTime)
            ->groupBy(['css.customer_id',])
            ->orderByDesc('sum_score')
            ->orderByDesc('css.customer_id')
            ->limit($this->getShowNum())
            ->get()
            ->pluck('customer_id')
            ->toArray();
    }
}
