<?php

namespace App\Repositories\Product\ProductChannel;

use App\Helpers\EloquentHelper;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FeatureStoresRepository
{
    const COUNTRY_ID = [223, 222, 81, 107];

    public function getFeatureStoresSellerIds(): array
    {
        $nowTime = Carbon::now();
        $scoreTaskNumberPrefix = $nowTime->format('Ym');
        $scoreTaskNumberCur = $nowTime->format('Ymd');
        if ($scoreTaskNumberCur >= $scoreTaskNumberPrefix . '01' && $scoreTaskNumberCur < $scoreTaskNumberPrefix . '16') {
            $scoreTaskNumber = $scoreTaskNumberPrefix . '01';
        } else {
            $scoreTaskNumber = $scoreTaskNumberPrefix . '16';
        }
        $ret = [];
        $newStoresRepository = new NewStoresRepository();
        foreach (self::COUNTRY_ID as $countryId) {
            $query = DB::connection('mysql_proxy')->table('oc_customer as c')
                ->select(['c.customer_id',])
                ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.customer_id', '=', 'c.customer_id')
                ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'c.customer_id')
                ->leftJoin('oc_product as p', 'p.product_id', '=', 'ctp.product_id')
                ->leftJoin('oc_product_exts as ope', 'ope.product_id', '=', 'p.product_id')
                ->where(['c.status' => 1, 'c.country_id' => $countryId,])
                ->whereIn('c.customer_id', $newStoresRepository->getAvailableSellerId($countryId))
                ->where('ctc.score_task_number', $scoreTaskNumber)
                ->when(
                    $countryId == 223,
                    function ($q) {
                        $q->where('ctc.performance_score', '>', 80);// 美国店铺评分 > 80
                    },
                    function ($q) {
                        $q->where('ctc.performance_score', '>', 75);// 其他国家店铺评分 > 75
                    }
                )
                ->whereExists(function ($q) {             // 退返品率 > 8
                    $q->select('*')
                        ->from('tb_customer_score_sub as css')
                        ->leftJoin('tb_customer_score_dimension as csd', 'csd.dimension_id', '=', 'css.dimension_id')
                        ->whereRaw('css.customer_id = c.customer_id')
                        ->where('csd.code', 'seller_product_rma_rate')
                        ->where('css.id', function ($q) {
                            $q->selectRaw('max(css_t.id)')
                                ->from('tb_customer_score_sub as css_t')
                                ->whereRaw('css_t.customer_id = c.customer_id')
                                ->where('css_t.dimension_id', 9);
                        })
                        ->where('css.score', '>', 6);
                })
                ->whereExists(function ($q) {             //  断货率  4
                    $q->select('*')
                        ->from('tb_customer_score_sub as css')
                        ->leftJoin('tb_customer_score_dimension as csd', 'csd.dimension_id', '=', 'css.dimension_id')
                        ->whereRaw('css.customer_id = c.customer_id')
                        ->where('csd.code', 'seller_product_sold_out_rate')
                        ->where('css.id', function ($q) {
                            $q->selectRaw('max(css_t.id)')
                                ->from('tb_customer_score_sub as css_t')
                                ->whereRaw('css_t.customer_id = c.customer_id')
                                ->where('css_t.dimension_id', 8);
                        })
                        ->where('css.score', '>', 3);
                })
                ->whereExists(function ($q) { // 产品数量 > 10
                    $q->select('*')
                        ->from('oc_customerpartner_to_product as ctp_t')
                        ->leftJoin('oc_product as p_t', 'ctp_t.product_id', '=', 'p_t.product_id')
                        ->whereRaw('ctp_t.customer_id = c.customer_id')
                        ->where([
                            'p_t.status' => 1,
                            'p_t.is_deleted' => 0,
                            'p_t.buyer_flag' => 1,
                            'p_t.part_flag' => 0,
                            'p_t.product_type' => 0,
                        ])
                        ->havingRaw('count(p_t.product_id) > 10')
                        ->groupBy('ctp_t.customer_id');
                })
                ->where(function ($q) {
                    $q->orWhereExists(function ($q) {
                        $q->select('*')
                            ->from('tb_sys_margin_template as mt')
                            ->leftJoin('oc_product as p', 'p.product_id', '=', 'mt.product_id')
                            ->whereRaw('mt.seller_id = c.customer_id')
                            ->where([
                                'p.status' => 1,
                                'p.is_deleted' => 0,
                                'p.buyer_flag' => 1,
                                'mt.is_del' => 0,
                            ]);
                    });
                    $q->orWhereExists(function ($q) {
                        $q->select('*')
                            ->from('oc_futures_contract as fc')
                            ->leftJoin('oc_product as p', 'p.product_id', '=', 'fc.product_id')
                            ->whereRaw('fc.seller_id = c.customer_id')
                            ->where([
                                'p.status' => 1,
                                'p.is_deleted' => 0,
                                'p.buyer_flag' => 1,
                                'fc.is_deleted' => 0,
                                'fc.status' => 1,
                            ]);
                    });
                    $q->orWhereExists(function ($q) {
                        $q->select('*')
                            ->from('oc_rebate_template_item as rti')
                            ->leftjoin('oc_rebate_template as rt', 'rti.template_id', '=', 'rt.id')
                            ->leftJoin('oc_product as p', 'p.product_id', '=', 'rti.product_id')
                            ->whereRaw('rt.seller_id = c.customer_id')
                            ->where([
                                'p.status' => 1,
                                'p.is_deleted' => 0,
                                'p.buyer_flag' => 1,
                                'rt.is_deleted' => 0,
                                'rti.is_deleted' => 0,
                            ]);
                    });
                })
                ->orderByDesc('ctc.performance_score')
                ->orderBy('c.customer_id')
                ->groupBy(['c.customer_id']);
            $sellerIds = $query->pluck('c.customer_id')->toArray();
            $tempData = [];
            if($sellerIds){
                $res = $this->getFeatureStoresProductIds($sellerIds, $countryId);
                foreach ($sellerIds as $sellerId) {
                    $tmpProductIds = isset($res) && $res->get($sellerId)
                        ? $res->get($sellerId)->pluck('product_id')->toArray()
                        : [];
                    if ($tmpProductIds) {
                        $tempData[] = [
                            'id' => $sellerId,
                            'productIds' => $tmpProductIds,
                        ];
                    }
                }
            }
            $ret[$countryId] = json_encode($tempData);

        }
        return $ret;
    }

    public function getFeatureStoresProductIds(array $sellerIds, int $countryId)
    {
        // 这里采取12个seller的union是为了避免一个seller产品过多导致速度慢
        $query = array_reduce($sellerIds, function ($val, $sellerId) use ($countryId) {
            $query = DB::connection('mysql_proxy')->table('oc_product as p')
                ->select(['p.product_id', 'c.customer_id',])
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
                    'c.country_id' => $countryId,
                    'c.customer_id' => $sellerId
                ]);

            // 上架库存>10的3个产品（若不足去除该条件）
            $tempQuery = (clone $query)
                ->addSelect(new Expression('1 as quantity'))
                ->where('p.quantity', '>', 10)
                ->orderByDesc('sps.quantity_30')
                ->groupBy(['p.product_id'])
                ->limit(20);

            $tempQuery = DB::connection('mysql_proxy')->table(DB::raw('(' . EloquentHelper::getCompleteSql($tempQuery) . ') as t'))->groupBy(['t.pmd5']);
            $tempCount = DB::connection('mysql_proxy')->table(DB::raw('(' . EloquentHelper::getCompleteSql($tempQuery) . ') as t'))->count();
            if ($tempCount >= 3) {
                $query = $tempQuery;
            } else {
                $query = $query
                    ->addSelect('p.quantity')
                    ->orderByDesc('p.quantity')
                    ->orderByDesc('sps.quantity_30')
                    ->groupBy(['p.product_id'])
                    ->limit(20);
            }
            if (!$val) {
                return $query;
            } else {
                return $val->union($query);
            }
        }, null);
        // 排序 去重 实现同款不同色的去重方案
        $query = DB::connection('mysql_proxy')->table(DB::raw('(' . EloquentHelper::getCompleteSql($query) . ') as t'))
            ->orderByDesc('t.customer_id')
            ->orderByDesc('t.quantity')
            ->orderByDesc('t.quantity_30')
            ->orderByDesc('t.product_id')
            ->groupBy(['t.pmd5']);
        // 使用变量的方式获取前3个 注意:这里的customer_id一定要先排序，否则答案将会不准
        $query = DB::connection('mysql_proxy')->table(DB::raw('(' . EloquentHelper::getCompleteSql($query) . ') as t'))
            ->select('*')
            ->selectRaw('@ns_count := IF(@ns_customer_id= customer_id, @ns_count + 1, 1) as rank')
            ->selectRaw('@ns_customer_id := customer_id');
        $res = DB::connection('mysql_proxy')->table(DB::raw('(' . EloquentHelper::getCompleteSql($query) . ') as t'))
            ->where('t.rank', '<=', 20)
            ->get();
        // 按照customer id进行分组 后面用到
        return $res->groupBy('customer_id');
    }
}