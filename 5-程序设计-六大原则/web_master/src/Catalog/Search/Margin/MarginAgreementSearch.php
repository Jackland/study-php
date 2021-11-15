<?php

namespace App\Catalog\Search\Margin;

use App\Logging\Logger;
use App\Models\Margin\MarginAgreement;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Sort;
use App\Enums\Margin\MarginAgreementStatus;

class MarginAgreementSearch
{
    use SearchModelTrait;

    private $customerId;
    private $searchAttributes = [
        'filter_margin_agreement_id' => '',
        'filter_margin_status' => '',
        'filter_margin_item_code' => '',
        'filter_margin_store' => '',
        'filter_margin_date_from' => '',
        'filter_margin_date_to' => '',
        'filter_margin_hot_map' => '',
    ];

    public function __construct($customerId)
    {
        $this->customerId = $customerId;
    }

    /**
     * @param $params
     * @param bool $isDownload
     * @return QueryDataProvider
     * @throws \Framework\Exception\InvalidConfigException
     */
    public function search($params, $isDownload = false)
    {
        $this->loadAttributes($params);
        $query = $this->buildQuery();
        $dataProvider = new QueryDataProvider($query);
        if (!$isDownload) {
            $dataProvider->setSort(new Sort([
                'defaultOrder' => ['last_modified' => SORT_DESC],
                'rules' => [
                    'last_modified' => 'a.update_time',
                    'effect_time' => 'a.effect_time',
                ],
            ]));
           $dataProvider->setPaginator(['defaultPageSize' => 10]); // 'pageSizeParam' =>'page_limit_new'
        } else {
            $dataProvider->switchSort(false);
            $dataProvider->switchPaginator(false);
            $query->orderByDesc('a.update_time');
        }
        return $dataProvider;
    }

    public function getStatisticsNumber($params, $caltotal = false)
    {
        $this->loadAttributes($params);
        return $this->buildNumberQuery($caltotal);
    }

    protected function buildQuery()
    {
        return MarginAgreement::query()->alias('a')
            ->leftJoin('oc_customerpartner_to_customer as c2c', 'a.seller_id', '=', 'c2c.customer_id')
            ->leftJoin('oc_product as p', 'a.product_id', '=', 'p.product_id')
            ->leftJoin('tb_sys_margin_process as mp', 'a.id', '=', 'mp.margin_id')
            ->leftJoin('tb_sys_margin_order_relation as mor', 'mor.margin_process_id', '=', 'mp.id')
            ->leftJoin('tb_sys_margin_performer_apply as mpa', function ($join) {
                $join->on('mpa.agreement_id', '=', 'a.id')
                    ->where(function ($query) {
                        $query->whereIn('mpa.check_result', [0, 1]);
                        $query->whereIn('mpa.seller_approval_status', [0, 1]);
                    });
            })
            ->leftJoin('oc_customerpartner_to_product as c2p', 'c2p.product_id', '=', 'mp.advance_product_id')
            ->leftJoin('oc_agreement_common_performer as acp', function ($join) {
                $join->on('acp.agreement_id', '=', 'a.id')
                    ->where(function ($query) {
                        $query->where('acp.agreement_type', 0);
                        $query->on('acp.product_id', '=', 'a.product_id');
                        $query->where('acp.buyer_id', '=', $this->customerId);
                    });
            })
            ->whereRaw("(a.buyer_id ={$this->customerId} OR acp.buyer_id={$this->customerId})")
            ->when(trim($this->searchAttributes['filter_margin_agreement_id']), function ($q) {
                $q->where('a.agreement_id', 'like', '%' . $this->searchAttributes['filter_margin_agreement_id'] . '%');
            })
            ->when(!empty($this->searchAttributes['filter_margin_item_code']), function ($q) {
                $q->where(function ($q) {
                    $q->where('p.sku', 'like', "%{$this->searchAttributes['filter_margin_item_code']}%")
                        ->orWhere('p.mpn', 'like', '%' . $this->searchAttributes['filter_margin_item_code'] . '%');
                });
            })
            ->when(trim($this->searchAttributes['filter_margin_store']), function ($q) {
                $q->where('c2c.screenname', 'like', '%' . $this->searchAttributes['filter_margin_store'] . '%');
            })
            ->when($this->searchAttributes['filter_margin_date_from'], function ($q) {
                $q->where('a.effect_time', '>=', $this->searchAttributes['filter_margin_date_from']);
            })
            ->when($this->searchAttributes['filter_margin_date_to'], function ($q) {
                $q->where('a.expire_time', '<=', $this->searchAttributes['filter_margin_date_to']);
            })
            ->when(isset($this->searchAttributes['filter_margin_status']), function ($q) {
                $statusFlag = false;
                $filterMarginStatus = $this->searchAttributes['filter_margin_status'];
                $checkStatus = MarginAgreementStatus::getFrontNeedStatus(); // 1 2 3 5
                if ($filterMarginStatus == 0) {
                    $q->where(function ($query) use ($checkStatus) {
                        $query->whereNotIn('a.status', $checkStatus)
                            ->orWhere((function ($query2) use ($checkStatus) {
                                $query2->whereIn('a.status', $checkStatus)
                                    ->where('a.is_bid', 1);
                            }));
                    });
                } else {
                    if (in_array($filterMarginStatus, $checkStatus)) {
                        $statusFlag = true;
                    }
                    if ($filterMarginStatus > 0 & !$statusFlag) {
                        $q->where('a.status', $filterMarginStatus);
                    } else {
                        $q->where('a.status', $filterMarginStatus)->where('a.is_bid', 1);
                    }
                    if (in_array($filterMarginStatus, [
                        MarginAgreementStatus::REJECTED,
                        MarginAgreementStatus::TIME_OUT,
                        MarginAgreementStatus::BACK_ORDER])
                    ) {
                        $q->where('a.buyer_ignore', 0);
                    }
                    if ($filterMarginStatus == -1) {
                        $q->where('a.buyer_ignore', 1);
                    }
                }
            })
            ->when(!empty($this->searchAttributes['filter_margin_hot_map'] && $this->searchAttributes['filter_margin_hot_map'] !== 'all'), function ($q) {
                $filterMargiHotMap = trim($this->searchAttributes['filter_margin_hot_map']);
                switch ($filterMargiHotMap) {
                    case 'tb_processed':
                        $row = MarginAgreementStatus::APPLIED . ',' . MarginAgreementStatus::PENDING;
                        $q->whereRaw(" a.status in ($row)"); //appiled + pending
                        break;
                    case 'tb_paid_margin':
                        $q->whereRaw(' a.status = ' . MarginAgreementStatus::APPROVED . ' '); //Approved
                        break;
                    case 'tb_paid_due':
                        $q->whereRaw('a.status = ' . MarginAgreementStatus::SOLD . ' '); //to be paid
                        break;
                    case 'tb_expired':
                        $q->whereRaw('a.status = ' . MarginAgreementStatus::SOLD . ' 
                        and TIMESTAMPDIFF(DAY, NOW(), a.expire_time) < 7
                        and TIMESTAMPDIFF(DAY, NOW(), a.expire_time) >= 0
                        and a.expire_time >= NOW() '); //  当前国别时间距离协议结束日期7天的To be paid尾款数据
                        break;
                    default :
                        break;

                }
            })
            ->selectRaw("
            a.*
            , GROUP_CONCAT(DISTINCT mor.rest_order_id) AS rest_order_ids
            ,c2c.screenname
            ,c2c.customer_id as seller_store_id
            ,p.sku
            ,p.image as product_image
            ,mp.advance_product_id
            ,mp.rest_product_id
            ,mp.advance_order_id
            ,IFNULL(SUM(mor.purchase_quantity), 0) AS sum_purchase_qty
            ,COUNT(DISTINCT mpa.id) AS count_performer
            ,c2p.customer_id AS advance_seller_id
            ,acp.is_signed")
            ->groupBy('a.id');
    }

    protected function buildNumberQuery($caltotal = false)
    {
        $baseQuery = MarginAgreement::query()->alias('a')
            ->leftJoin('oc_product as p', 'a.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customerpartner_to_customer as c2c', 'a.seller_id', '=', 'c2c.customer_id')
            ->leftJoin('oc_agreement_common_performer as acp', function ($join) {
                $join->on('acp.agreement_id', '=', 'a.id')
                    ->where(function ($query) {
                        $query->where('acp.agreement_type', 0);
                        $query->on('acp.product_id', 'a.product_id');
                        $query->where('acp.buyer_id', '=', $this->customerId);
                    });
            })
            ->whereRaw("(a.buyer_id ={$this->customerId} or acp.buyer_id={$this->customerId})")
            ->when(trim($this->searchAttributes['filter_margin_agreement_id'] && !$caltotal), function ($q) {
                $q->where('a.agreement_id', 'like', '%' . $this->searchAttributes['filter_margin_agreement_id'] . '%');
            })
            ->when(!empty($this->searchAttributes['filter_margin_item_code']) && !$caltotal, function ($q) {
                $q->where(function ($q) {
                    $q->where('p.sku', 'like', '%' . $this->searchAttributes['filter_margin_item_code'] . '%')
                        ->orWhere('p.mpn', 'like', '%' . $this->searchAttributes['filter_margin_item_code'] . '%');
                });
            })
            ->when(trim($this->searchAttributes['filter_margin_store'] && !$caltotal), function ($q) {
                $q->where('c2c.screenname', 'like', '%' . $this->searchAttributes['filter_margin_store'] . '%');
            })
            ->when($this->searchAttributes['filter_margin_date_from'] && !$caltotal, function ($q) {
                $q->where('a.effect_time', '>=', $this->searchAttributes['filter_margin_date_from']);
            })
            ->when($this->searchAttributes['filter_margin_date_to'] && !$caltotal, function ($q) {
                $q->where('a.expire_time', '<=', $this->searchAttributes['filter_margin_date_to']);
            })
            ->when(isset($this->searchAttributes['filter_margin_status']), function ($q) use ($caltotal) {
                $statusFlag = false;
                $filterMarginStatus = $this->searchAttributes['filter_margin_status'];
                $checkStatus = MarginAgreementStatus::getFrontNeedStatus(); // 1 2 3 5
                if ($filterMarginStatus == 0 || $caltotal) {
                    $q->where(function ($query) use ($checkStatus) {
                        $query->whereNotIn('a.status', $checkStatus)
                            ->orWhere((function ($query2) use ($checkStatus) {
                                $query2->whereIn('a.status', $checkStatus)
                                    ->where('a.is_bid', 1);
                            }));
                    });
                } else {
                    if (in_array($filterMarginStatus, $checkStatus)) {
                        $statusFlag = true;
                    }
                    if ($filterMarginStatus > 0 & !$statusFlag) {
                        $q->where('a.status', $filterMarginStatus);
                    } else {
                        $q->where('a.status', $filterMarginStatus)->where('a.is_bid', 1);
                    }
                    if (in_array($filterMarginStatus, [
                        MarginAgreementStatus::REJECTED,
                        MarginAgreementStatus::TIME_OUT,
                        MarginAgreementStatus::BACK_ORDER])
                    ) {
                        $q->where('a.buyer_ignore', 0);
                    }
                    if ($filterMarginStatus == -1) {
                        $q->where('a.buyer_ignore', 1);
                    }
                }
            });

        $baseRow = MarginAgreementStatus::APPLIED . ',' . MarginAgreementStatus::PENDING;
        $tbProcessedNumber = (clone $baseQuery)->whereRaw(" a.status in ($baseRow) ")->count();
        $tbMarginNumber = (clone $baseQuery)->whereRaw(' a.status = ' . MarginAgreementStatus::APPROVED)->count();
        $tbDueNumber = (clone $baseQuery)->whereRaw(' a.status = ' . MarginAgreementStatus::SOLD)->count();
        $tbExpiredNumber = (clone $baseQuery)->whereRaw(" a.status =  " . MarginAgreementStatus::SOLD .
            " and TIMESTAMPDIFF(DAY, NOW(), a.expire_time) < 7
              and TIMESTAMPDIFF(DAY, NOW(), a.expire_time) >= 0
              and a.expire_time >= NOW() ")
            ->count();

        return [
            'tb_processed_number' => $tbProcessedNumber,
            'tb_margin_number' => $tbMarginNumber,
            'tb_due_number' => $tbDueNumber,
            'tb_expired_number' => $tbExpiredNumber,
            'total_number' => $tbProcessedNumber + $tbMarginNumber + $tbDueNumber + $tbExpiredNumber,
        ];
    }

}
