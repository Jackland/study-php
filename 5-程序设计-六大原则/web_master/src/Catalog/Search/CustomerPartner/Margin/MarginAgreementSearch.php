<?php

namespace App\Catalog\Search\CustomerPartner\Margin;


use App\Enums\Margin\MarginAgreementStatus;
use App\Models\Margin\MarginAgreement;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Sort;
use Framework\Model\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

class MarginAgreementSearch
{
    use SearchModelTrait;

    private $customerId;
    //支持的查询条件
    private $searchAttributes = [
        'filter_id' => '',
        'filter_buyer_name' => '',
        'filter_status' => '',
        'filter_sku_mpn' => '',
        'filter_date_from' => '',
        'filter_date_to' => '',
        'hot_map' => '',
    ];

    public function __construct($customerId)
    {
        $this->customerId = intval($customerId);
    }

    /**
     * @param $params
     * @param bool $isDownload
     */
    public function search($params, $isDownload = false)
    {
        $this->loadAttributes($params);
        $query = $this->buildQuery();
        $dataProvider = new QueryDataProvider($query);
        if (!$isDownload) {
            $dataProvider->setSort(new Sort([
                'defaultOrder' => ['update_time' => SORT_DESC],
                'alwaysOrder' => ['id' => SORT_ASC],
                'rules' => [
                    'update_time' => 'a.update_time',
                    'id' => 'a.agreement_id',
                ],
            ]));
        } else {
            $dataProvider->switchSort(false);
            $dataProvider->switchPaginator(false);
            $query->orderByDesc('a.update_time');
        }

        return $dataProvider;
    }

    public function getStatisticsNumber($params)
    {
        $this->loadAttributes($params);
        return $this->buildNumberQuery();
    }

    /**
     * @return mixed
     */
    protected function buildQuery()
    {
        return MarginAgreement::query()->alias('a')
            ->leftJoinRelations(['marginStatus as s', 'buyer as buyer', 'product as p', 'process as mp'])
            ->leftJoin('tb_sys_margin_order_relation as mor', 'mor.margin_process_id', '=', 'mp.id')
            ->leftJoin('tb_sys_margin_performer_apply as mpa', function (JoinClause $join) {
                $join->on('mpa.agreement_id', '=', 'a.id')
                    ->whereIn('mpa.check_result', [0, 1])
                    ->whereIn('mpa.seller_approval_status', [0, 1]);
            })
            ->where('a.seller_id', '=', $this->customerId)
            ->when($this->searchAttributes['filter_id'] != '', function (Builder $q) {
                $q->where('a.agreement_id', 'like', '%' . $this->searchAttributes['filter_id'] . '%');
            })
            ->when($this->searchAttributes['filter_sku_mpn'] != '', function (Builder $q) {
                $q->where(function (Builder $qq) {
                    $qq->where('p.sku', 'like', '%' . $this->searchAttributes['filter_sku_mpn'] . '%')
                        ->orWhere('p.mpn', 'like', '%' . $this->searchAttributes['filter_sku_mpn'] . '%');
                });
            })
            ->when($this->searchAttributes['filter_buyer_name'] != '', function (Builder $q) {
                $q->where(function (Builder $qq) {
                    $qq->where('buyer.nickname', 'like', '%' . $this->searchAttributes['filter_buyer_name'] . '%')
                        ->orWhere('buyer.user_number', 'like', '%' . $this->searchAttributes['filter_buyer_name'] . '%');
                });
            })
            ->when($this->searchAttributes['filter_date_from'] != '', function (Builder $q) {
                $q->where('a.update_time', '>=', $this->searchAttributes['filter_date_from']);
            })
            ->when($this->searchAttributes['filter_date_to'] != '', function (Builder $q) {
                $q->where('a.update_time', '<=', $this->searchAttributes['filter_date_to']);
            })
            ->when(isset($this->searchAttributes['filter_status']), function (Builder $q) {
                $statusFlag = false;
                $status = $this->searchAttributes['filter_status'];
                if (!$status) {
                    $q->whereIn('a.status', [
                        MarginAgreementStatus::APPLIED,
                        MarginAgreementStatus::PENDING,
                        MarginAgreementStatus::APPROVED,
                        MarginAgreementStatus::TIME_OUT
                    ])->where('a.is_bid', '=', 1);
                } else {
                    if (in_array($status, [
                        MarginAgreementStatus::APPLIED,
                        MarginAgreementStatus::PENDING,
                        MarginAgreementStatus::APPROVED,
                        MarginAgreementStatus::TIME_OUT,
                    ])) {
                        $statusFlag = true;
                        $q->where('a.status', '=', $this->searchAttributes['filter_status'])
                            ->where('a.is_bid', '=', 1);
                    }

                    if ($this->searchAttributes['filter_status'] > 0 && !$statusFlag) {
                        $q->where('a.status', '=', $this->searchAttributes['filter_status']);
                    }

                    if ($this->searchAttributes['filter_status'] == -1) {
                        $q->where('a.buyer_ignore', '=', 1);
                    }
                }
            })
            ->when($this->searchAttributes['hot_map'] != '', function (Builder $q) {
                switch ($this->searchAttributes['hot_map']) {
                    case 'wait_process':
                        $q->whereIn('a.status', [MarginAgreementStatus::APPLIED, MarginAgreementStatus::PENDING,])
                            ->where('a.is_bid', '=', 1);
                        break;
                    case 'wait_deposit_pay':
                        $q->where('a.status', '=', MarginAgreementStatus::APPROVED)
                            ->where('a.is_bid', '=', 1);
                        break;
                    case 'due_soon':
                        $q->whereRaw('a.`status` =6 AND TIMESTAMPDIFF(DAY, NOW(), a.expire_time) < 7  AND TIMESTAMPDIFF(DAY, NOW(), a.expire_time) >= 0 and expire_time>=NOW()');
                        break;
                    case 'termination_request':
                        $q->whereRaw('((a.`status` =6 AND a.termination_request = 1 AND a.expire_time>=NOW()) OR (a.`status` =6 AND mpa.check_result = 0 AND mpa.seller_approval_status =0 AND a.expire_time>=NOW()))');
                        break;
                    default:
                        break;
                }
            })
            ->selectRaw("
             a.*
            , GROUP_CONCAT(mor.rest_order_id) AS rest_order_ids
            ,s.`name` AS status_name
            ,s.color AS status_color
            ,buyer.nickname
            ,buyer.user_number
            ,buyer.`customer_group_id`
            ,p.sku
            ,p.mpn
            ,p.image
            ,mp.advance_product_id
            ,mp.advance_order_id
            ,mp.rest_product_id
            ,IFNULL(SUM(mor.purchase_quantity), 0) AS sum_purchase_qty
            ,COUNT(mpa.id) AS count_performer")
            ->groupBy('a.id');
    }

    protected function buildNumberQuery()
    {
        $date = date('Y-m-d H:i:s');
        $baseQuery = MarginAgreement::query()->alias('a')
            ->leftJoinRelations(['buyer as buyer', 'product as p'])
            ->leftJoin('tb_sys_margin_performer_apply as mpa', function (JoinClause $join) {
                $join->on('mpa.agreement_id', '=', 'a.id')
                    ->whereIn('mpa.check_result', [0, 1])
                    ->whereIn('mpa.seller_approval_status', [0, 1]);
            })
            ->where('a.seller_id', '=', $this->customerId)
            ->when($this->searchAttributes['filter_id'] != '', function (Builder $q) {
                $q->where('a.agreement_id', 'like', '%' . $this->searchAttributes['filter_id'] . '%');
            })
            ->when($this->searchAttributes['filter_sku_mpn'] != '', function (Builder $q) {
                $q->where(function (Builder $qq) {
                    $qq->where('p.sku', 'like', '%' . $this->searchAttributes['filter_sku_mpn'] . '%')
                        ->orWhere('p.mpn', 'like', '%' . $this->searchAttributes['filter_sku_mpn'] . '%');
                });
            })
            ->when($this->searchAttributes['filter_buyer_name'] != '', function (Builder $q) {
                $q->where(function (Builder $qq) {
                    $qq->where('buyer.nickname', 'like', '%' . $this->searchAttributes['filter_buyer_name'] . '%')
                        ->orWhere('buyer.user_number', 'like', '%' . $this->searchAttributes['filter_buyer_name'] . '%');
                });
            })
            ->when($this->searchAttributes['filter_date_from'] != '', function (Builder $q) {
                $q->where('a.update_time', '>=', $this->searchAttributes['filter_date_from']);
            })
            ->when($this->searchAttributes['filter_date_to'] != '', function (Builder $q) {
                $q->where('a.update_time', '<=', $this->searchAttributes['filter_date_to']);
            })
            ->when(isset($this->searchAttributes['filter_status']), function (Builder $q) {
                $statusFlag = false;
                $status = $this->searchAttributes['filter_status'];
                if (!$status) {
                    $q->whereIn('a.status', [
                        MarginAgreementStatus::APPLIED,
                        MarginAgreementStatus::PENDING,
                        MarginAgreementStatus::APPROVED,
                        MarginAgreementStatus::TIME_OUT
                    ])->where('a.is_bid', '=', 1);
                } else {
                    if (in_array($status, [
                        MarginAgreementStatus::APPLIED,
                        MarginAgreementStatus::PENDING,
                        MarginAgreementStatus::APPROVED,
                        MarginAgreementStatus::TIME_OUT,
                    ])) {
                        $statusFlag = true;
                        $q->where('a.status', '=', $this->searchAttributes['filter_status'])
                            ->where('a.is_bid', '=', 1);
                    }

                    if ($this->searchAttributes['filter_status'] > 0 && !$statusFlag) {
                        $q->where('a.status', '=', $this->searchAttributes['filter_status']);
                    }

                    if ($this->searchAttributes['filter_status'] == -1) {
                        $q->where('a.buyer_ignore', '=', 1);
                    }
                }
            });

        //待处理数量 hot_map == wait_process
        $countWaitProcess = (clone $baseQuery)->whereIn('a.status', [MarginAgreementStatus::APPLIED, MarginAgreementStatus::PENDING])
            ->where('a.is_bid', '=', 1)
            ->count(['a.id']);
        //待支付定金数量 hot_map == wait_deposit_pay
        $countWaitDepositPay = (clone $baseQuery)->where('a.status', '=', MarginAgreementStatus::APPROVED)
            ->where('a.is_bid', '=', 1)
            ->count(['a.id']);
        //即将到期数量 hot_map == due_soon 当前国别时间距离协议结束日期7天的To be paid尾款数据
        $countExpiredNumber = (clone $baseQuery)->where('a.status', '=', MarginAgreementStatus::SOLD)
            ->whereRaw('TIMESTAMPDIFF(DAY, NOW(), a.expire_time) < 7  AND TIMESTAMPDIFF(DAY, NOW(), a.expire_time) >=0 and a.expire_time>=NOW()')
            ->count(['a.id']);
        //待支付尾款 hot_map = sold
        $countSold = (clone $baseQuery)->where('a.status', '=', MarginAgreementStatus::SOLD)
            ->where('a.expire_time', '>', $date)
            ->count(['a.id']);
        return [
            'count_wait_process'=>(int)$countWaitProcess,
            'count_wait_deposit_pay'=>(int)$countWaitDepositPay,
            'count_due_soon'=>(int)$countExpiredNumber,
            'count_sold'=>(int)$countSold,
        ];
    }
}
