<?php

use App\Enums\Customer\CustomerScoreDimension;

/**
 * Class ModelCustomerpartnerSellerCenterIndex
 *
 * @property ModelCustomerpartnerRmaManagement $model_customerpartner_rma_management
 */
class ModelCustomerpartnerSellerCenterIndex extends Model
{
    const CUSTOMER_SELLER = 1;          //seller用户
    const CUSTOMER_BUYER = 2;           //buyer用户

    /**
     * seller的账户经理
     * @param int $customer_id
     * @return array
     */
    public function accountManager(int $customer_id): array
    {
        $builder = $this->orm->table(DB_PREFIX . 'buyer_to_seller as b')
            ->join(DB_PREFIX . 'customer as c', 'c.customer_id', '=', 'b.buyer_id')
            ->join('oc_sys_user_to_customer as  sutc', 'sutc.account_manager_id', '=', 'c.customer_id')
            ->join('tb_sys_user as su', 'sutc.user_id', '=', 'su.id')
            ->leftJoin('tb_upload_file as uf', 'su.picture_id', '=', 'uf.id')
            ->select([
                'su.username',
                'su.email',
                'su.mobile_phone',
                'uf.path'
            ])
            ->where([
                ['b.seller_id', '=', $customer_id],
                ['c.customer_group_id', '=', 14]
            ])
            ->first();
        $result = obj2array($builder);
        return $result;
    }

    /**
     * 退货同意率
     * @param array $seller_id_list
     * @return array
     */
    public function returnApprovalRate(array $seller_id_list): array
    {

        //加索引seller_id
        $res = $this->orm->table(DB_PREFIX . 'yzc_rma_order as ro')
            ->whereIn('ro.seller_id', $seller_id_list)
            ->groupBy('ro.seller_id')
            ->selectRaw('seller_id,count(*) as amount')
            ->get()
            ->keyBy('seller_id')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();

        $approval = $this->orm->table(DB_PREFIX . 'yzc_rma_order_product as op')
            ->leftJoin(DB_PREFIX . 'yzc_rma_order as ro', 'ro.id', '=', 'op.rma_id')
            ->whereIn('ro.seller_id', $seller_id_list)
            ->where('ro.seller_status', 2)
            ->where(function ($query) {
                //rma_type RMA类型 1:仅重发;2:仅退款;3:即退款又重发
                //status_refund 返金状态 0:初始状态;1:同意;2:拒绝
                //status_reshipment 重发状态 0:初始;1:同意;2:拒绝
                $query->where([['rma_type', '=', 3], ['status_refund', '=', 1], ['status_reshipment', '=', 1]])
                    ->orWhere([['rma_type', '=', 2], ['status_refund', '=', 1]])
                    ->orWhere([['rma_type', '=', 1], ['status_reshipment', '=', 1]]);
            })
            ->groupBy('ro.seller_id')
            ->selectRaw('seller_id,count(*) as amount')
            ->get()
            ->keyBy('seller_id')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        $ret = [];
        foreach ($res as $key => $value) {
            if ($value['amount']) {
                if (isset($approval[$key])) {
                    $getApproval = $approval[$key]['amount'];
                } else {
                    $getApproval = 0;
                }
                $ret[$key] = sprintf('%.2f', $getApproval * 100 / $value['amount']);
            } else {
                $ret[$key] = 0;
            }
        }
        return $ret;
    }

    /**
     * 促销活动
     * @param int $country_id
     * @param int $limit
     * @return array
     */
    public function marketingCampaign(int $country_id, int $limit): array
    {
        $result = [];
        $date_now = date('Y-m-d H:i:s', time());
        $builder = $this->orm->table(DB_PREFIX . 'marketing_campaign as m')
            ->select([
                'm.id',
                'm.name',
                'm.seller_activity_name',
                'm.effective_time',
                'm.expiration_time',
            ])
            ->where([
                ['m.country_id', '=', $country_id],
                ['m.apply_start_time', '<', $date_now],
                ['m.apply_end_time', '>', $date_now],
                ['m.is_release', '=', 1],
                ['m.type', '!=', 1],
            ]);
        //总记录
        $result['total'] = intval($builder->count());
        //分页
        $list = $builder->orderBy('m.apply_start_time', 'desc')
            ->limit($limit)
            ->get();
        $result['list'] = obj2array($list);
        return $result;
    }


    /**
     * RMA待处理数量
     * @param int $seller_id 用户id
     * @return int
     * @throws Exception
     */
    public function getNoHandleRmaCount(int $seller_id): int
    {
        $this->load->model('customerpartner/rma_management');
        return $this->model_customerpartner_rma_management->getNoHandleRmaCount($seller_id);
    }

    /**
     * 产品下载量
     * @param int $seller_id
     * @param string $start_date 开始时间
     * @return int
     */
    public function productDownload(int $seller_id, string $start_date): int
    {
        return (int)$this->orm->table('tb_sys_product_package_info as ppi')
            ->join('oc_customerpartner_to_product as ctp', 'ppi.product_id', '=', 'ctp.product_id')
            ->where([
                ['ctp.customer_id', '=', $seller_id],
                ['ppi.CreateTime', '>=', $start_date]
            ])->count();
    }

    /**
     * 产品销量排名
     * @param int $customer_id
     * @param array $data
     * @return array
     */
    public function productSaleRank(int $customer_id, array $data): array
    {
        $builder = $this->orm->table('oc_customerpartner_to_order as cto')
            ->join('oc_product as p', 'cto.product_id', '=', 'p.product_id')
            ->join('oc_product_description as pd', 'cto.product_id', '=', 'pd.product_id')
            ->leftJoin('oc_order_product as op', 'cto.order_product_id', '=', 'op.order_product_id')
            ->leftJoin('oc_customerpartner_to_product as c2p', 'c2p.product_id', '=', 'p.product_id')
            ->select([
                'p.product_id',
                'p.sku',
                'p.quantity as qty',
                'cto.quantity',
                'pd.name',
            ])
            ->selectRaw("SUM(cto.quantity) as sale_sum")
            ->where('cto.customer_id', '=', $customer_id)
            ->where('c2p.customer_id', '=', $customer_id)
            ->where('p.product_type', '=', 0) // 销售排行过滤补运费商品
            ->where('p.buyer_flag', '=', 1)
            ->where('p.part_flag', '=', 0)
            ->where('p.is_deleted', '=', 0);
        if ($data['filter_date_from'] && isset($data['filter_date_from'])) {
            $builder = $builder->where('cto.date_added', '>=', $this->db->escape($data['filter_date_from']));
        }
        $builder = $builder->groupBy('cto.product_id');
        if ($data['filter_order'] && isset($data['filter_order'])) {
            $builder = $builder->orderBy('sale_sum', $data['filter_order']);
        }

        if ($data['filter_limit'] && isset($data['filter_limit'])) {
            $builder = $builder->forPage($data['filter_page'], $data['filter_limit']);
        }
        $builder = $builder->orderBy('p.product_id', 'DESC')
            ->get();
        return obj2array($builder);
    }

    /**
     * @param string $table 表名
     * @param array $map 查询条件
     * @return array performance_score评分 score_task_number执行定时任务批次
     */
    public function getUserNowScoreTaskNumberEffective(string $table, array $map): array
    {
        static $res = [];
        if (isset($res[$table])) {
            $new_score_task_builder = $res[$table];
        } else {
            //获取当前最新执行任务时期
            $new_score_task_builder = $this->orm->table($table)
                ->orderBy('score_task_number', 'desc')
                ->limit(1)
                ->value('score_task_number');
            $res[$table] = $new_score_task_builder;
        }

        $new_score_task_number = obj2array($new_score_task_builder);
        //存在最新的执行任务时期
        if ($new_score_task_number) {
            //根据最新的评分批次查询自己的总分数
            $builder = $this->orm->table($table)
                ->where($map)
                ->where('score_task_number', $new_score_task_number)
                ->first(['performance_score', 'score_task_number']);
            $task_info = obj2array($builder);
            //有评分
            if ($task_info) {
                return $task_info;
            }
        }
        return [];
    }

    /**
     * 获取seller有效评分总值与评分定时任务的批次
     * @param int $customer_id
     * @return array performance_score评分 score_task_number执行定时任务批次
     */
    public function getSellerNowScoreTaskNumberEffective(int $customer_id): array
    {
        $table = 'oc_customerpartner_to_customer';
        $map = [];
        $map['customer_id'] = $customer_id;
        return $this->getUserNowScoreTaskNumberEffective($table, $map);
    }

    /**
     * 获取buyer有效评分总值与评分定时任务的批次
     * @param int $customer_id
     * @return array performance_score评分 score_task_number执行定时任务批次
     */
    public function getBuyerNowScoreTaskNumberEffective(int $customer_id): array
    {
        $table = 'oc_buyer';
        $map = [];
        $map['buyer_id'] = intval($customer_id);
        return $this->getUserNowScoreTaskNumberEffective($table, $map);
    }

    /**
     * 评分(详细包含各项分值、各项分值平均分、各项分值与平均分的比较)
     * @param int $customer_id
     * @param int $country_id
     * @param int $type 1：seller 2:buyer
     * @return array
     */
    public function comprehensiveSellerData(int $customer_id, int $country_id, int $type): array
    {
        $comprehensive = [];
        $temp = [];
        $task_info = [];
        //seller评分明细
        if ($type == self::CUSTOMER_SELLER) {
            $temp = CustomerScoreDimension::sellerDimension();
            $task_info = $this->getSellerNowScoreTaskNumberEffective(intval($customer_id));
        }
        //buyer评分明细
        if ($type == self::CUSTOMER_BUYER) {
            $temp = CustomerScoreDimension::BuyerDimension();
            $task_info = $this->getBuyerNowScoreTaskNumberEffective(intval($customer_id));
        }
        if (!$task_info) {
            return $comprehensive;
        }

        //判断这个维度是否存在个维度分值
        $exists = $this->orm->table('tb_customer_score')
            ->where([
                ['customer_id', '=', intval($customer_id)],
                ['task_number', '=', $task_info['score_task_number']]
            ])
            ->whereIn('dimension_id', $temp)
            ->exists();
        if (!$exists) {
            return $comprehensive;
        }
        foreach ($temp as $value) {
            $score = $this->oneRatingDimensionScore($customer_id, $value, $task_info['score_task_number']);
            //平均分
            $avg_score = $this->oneRatingDimensionAvgScore($country_id, $value, $task_info['score_task_number']);
            //产品质量满分（总分值）
            $full_score = $this->calculateOneItemFullScore($value);
            $avg_score = number_format(round($avg_score['avg_score'], 4), 4) ?? 0;
            $comprehensive[$value] = [
                'title' => CustomerScoreDimension::getDescription($value),
                'score' => number_format(round($score['score'], 2), 2) ?? 0,
                'avg_score' => $avg_score,
                'compare' => $this->compareSoreAndAvg($score['score'] ?? 0, $avg_score),
                'full_score' => $full_score['sum_score'] ?? 0,
            ];
        }
        return $comprehensive;
    }

    /**
     * 获取某个批次下某个具体评分维度的分值
     * @param int $customer_id
     * @param int $dimension_id 评分维度ID
     * @param string $task_number 评分任务执行的批次编号
     * @return array
     */
    public function oneRatingDimensionScore(int $customer_id, int $dimension_id, string $task_number): array
    {
        $builder = $this->orm->table('tb_customer_score')
            ->where([
                ['customer_id', '=', $customer_id],
                ['dimension_id', '=', $dimension_id],
                ['task_number', '=', $task_number]
            ])
            ->first(['score']);
        return obj2array($builder);
    }

    /**
     * 获取某个批次下某个具体评分维度的平均分值
     * @param int $country_id
     * @param int $dimension_id 评分维度ID
     * @param string $dimension_id 评分任务执行的批次编号
     * @return array
     */
    public function oneRatingDimensionAvgScore(int $country_id, int $dimension_id, string $task_number): array
    {
        $builder = $this->orm->table('tb_customer_score')
            ->where([
                ['country_id', '=', $country_id],
                ['dimension_id', '=', $dimension_id],
                ['task_number', '=', $task_number]
            ])
            ->selectRaw("avg(score) as avg_score")
            ->first();
        return obj2array($builder);
    }

    /**
     * 两个数相比较
     * @param float $score
     * @param float $avg_score
     * @return int
     */
    public function compareSoreAndAvg(float $score, float $avg_score): int
    {
        if ($score > $avg_score) {
            return 2;
        } else if ($score == $avg_score) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * 某大项下面的各小项总和
     * @param int $dimension_id
     * @return array 一位数组(key:sum_score,value：总和)
     */
    public function calculateOneItemFullScore(int $dimension_id): array
    {
        $builder = $this->orm->table('tb_customer_score_dimension as tcsd')
            ->join('tb_customer_score_formula as tcsf', 'tcsd.dimension_id', '=', 'tcsf.dimension_id')
            ->where([
                ['tcsd.parent_dimension_id', '=', $dimension_id]
            ])
            ->selectRaw("sum(tcsf.max_score) as sum_score")
            ->first();
        return obj2array($builder);
    }
}
