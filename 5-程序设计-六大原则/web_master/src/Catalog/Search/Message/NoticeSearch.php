<?php

namespace App\Catalog\Search\Message;

use App\Enums\Common\YesNoEnum;
use App\Models\Message\Notice;
use App\Models\Message\StationLetterCustomer;
use Carbon\Carbon;
use Framework\DataProvider\Paginator;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Sort;

class NoticeSearch
{
    use SearchModelTrait;

    private $receiverId;

    private $searchAttributes = [
        'filter_type_id' => '',
        'filter_child_type_id' => '',
        'filter_subject' => '',
        'filter_post_time_from' => '',
        'filter_post_time_to' => '',
        'filter_read_status' => '',
        'filter_mark_status' => '',
        'filter_delete_status' => '',
    ];

    /**
     * @param $params
     * @return mixed
     * @throws \Framework\Exception\InvalidConfigException
     */
    public function get($params)
    {
        $this->loadAttributes($params);

        $query = $this->buildQuery();

        $dataProvider = new QueryDataProvider($query);

        $dataProvider->setSort(new Sort([
            'defaultOrder' => ['publish_date' => SORT_DESC],
            'rules' => [
                'publish_date' => 'publish_date',
            ],
        ]));

        $dataProvider->setPaginator(new Paginator([
            'defaultPageSize' => 10,
        ]));

        $data['total'] = $dataProvider->getTotalCount();
        $data['list'] = $dataProvider->getList();
        $data['paginator'] = $dataProvider->getPaginator();
        $data['sort'] = $dataProvider->getSort();
        $data['search'] = $this->getSearchData();

        return $data;
    }

    /**
     * @param $params
     * @return array
     */
    public function getNoticeIdAndMode($params): array
    {
        $this->loadAttributes($params);
        $query = $this->buildQuery();
        return $query->get(['notice_id as id', 'data_model as type'])->toArray();
    }

    /**
     * @param int $id
     * @param int $tab
     * @return \Framework\Model\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getNoticeById(int $id, int $tab)
    {
        $this->searchAttributes['filter_type_id'] = $tab;
        $query = $this->buildQuery();

        if ($tab == 1) {
            return $query->where('n.id', $id)->first();
        }

        if ($tab == 2) {
            return $query->where('slc.letter_id', $id)->first();
        }
    }


    /**
     * @return \Framework\Model\Eloquent\Builder
     */
    protected function buildQuery()
    {
        $customerId = customer()->getId();

        // 公告
        if ($this->searchAttributes['filter_type_id'] != 2) {
            $countryId = customer()->getCountryId();
            $identity = customer()->isPartner() ? 1 : 0;

            $noticeQuery = Notice::queryRead()->alias('n')
                ->leftJoin('tb_sys_dictionary as dic', function ($join) {
                    $join->on('dic.dicKey', '=', 'n.type_id')->whereRaw("dic.dicCategory in ('PLAT_NOTICE_TYPE_DELETE', 'PLAT_NOTICE_TYPE')");
                })
                ->leftJoin('tb_sys_notice_to_object as n2o', 'n.id', '=', 'n2o.notice_id')
                ->leftJoin('tb_sys_notice_object as o', 'o.id', '=', 'n2o.notice_object_id')
                ->leftJoin('tb_sys_notice_placeholder as p', function ($join) use ($customerId) {
                    $join->on('p.notice_id', '=', 'n.id')->where(function ($query) use ($customerId) {
                        $query->where('p.customer_id', $customerId)->orWhereNull('p.customer_id');
                    });
                })
                ->where('n.publish_status', 1)
                ->where('o.identity', $identity)
                ->whereIn('o.country_id', [$countryId, 0])
                ->where('n.publish_date', '<=', Carbon::now()->toDateTimeString())
                ->when($this->searchAttributes['filter_delete_status'] !== '', function ($q) {
                    if ($this->searchAttributes['filter_delete_status'] == 0) {
                        $q->where(function ($query) {
                            $query->whereNull('p.is_del')->orWhere('p.is_del', 0);
                        });
                    } else {
                        $q->orWhere('p.is_del', 1);
                    }
                })
                ->when(trim($this->searchAttributes['filter_subject']) !== '', function ($q) {
                    $q->where('n.title', 'like', '%' . trim($this->searchAttributes['filter_subject']) . '%');
                })
                ->when($this->searchAttributes['filter_post_time_from'] !== '', function ($q) {
                    $q->where('n.publish_date', '>=', $this->searchAttributes['filter_post_time_from']);
                })
                ->when($this->searchAttributes['filter_post_time_to'] !== '', function ($q) {
                    $q->where('n.publish_date', '<', $this->searchAttributes['filter_post_time_to']);
                })
                ->when($this->searchAttributes['filter_child_type_id'] !== '', function ($q) {
                    $q->where('n.type_id', $this->searchAttributes['filter_child_type_id']);
                })
                ->when($this->searchAttributes['filter_read_status'] !== '', function ($q) {
                    if ($this->searchAttributes['filter_read_status'] == 1) {
                        $q->where('p.is_read', 1);

                    } else {
                        $q->where(function ($query) {
                            $query->whereNull('p.is_read')->orWhere('p.is_read', 0);
                        });
                    }
                })
                ->when($this->searchAttributes['filter_mark_status'] !== '', function ($q) {
                    if ($this->searchAttributes['filter_mark_status'] == 1) {
                        $q->where('p.is_marked', 1);

                    } else {
                        $q->where(function ($query) {
                            $query->whereNull('p.is_marked')->orWhere('p.is_marked', 0);
                        });
                    }
                })
                ->groupBy(['n.id'])
                ->selectRaw('
                    n.id as notice_id,
                    case when p.is_read is null then 0 else p.is_read end as is_read,
                    case when p.is_marked is null then 0 else p.is_marked end as is_marked,
                    n.type_id,
                    n.title,
                    n.top_status,
                    n.publish_date,
                    n.effective_time,
                    n.content,
                    dic.dicValue as type_name,
                    n.make_sure_status,
                    case when p.make_sure_status is null then 0 else p.make_sure_status end as p_make_sure_status,
                    "0" as message_type,
                    "notice" as data_model,
                    case when p.is_del is null then 0 else p.is_del end as delete_status
                ');
        }

        // 通知
        if ($this->searchAttributes['filter_type_id'] != 1) {
            $stationLetterCustomerQuery = StationLetterCustomer::queryRead()->alias('slc')
                ->leftJoinRelations('stationLetter as sl')
                ->leftJoin('tb_sys_dictionary as sd', function ($join) {
                    $join->on('sl.type', '=', 'sd.DicKey')->where('sd.DicCategory', '=', 'STATION_LETTER_TYPE');
                })
                ->where('slc.customer_id', $customerId)
                ->where('sl.status', YesNoEnum::YES)
                ->where('sl.is_delete', YesNoEnum::NO)
                ->when($this->searchAttributes['filter_delete_status'] !== '', function ($q) {
                    $q->where('slc.is_delete',  $this->searchAttributes['filter_delete_status']);
                })
                ->when(trim($this->searchAttributes['filter_subject']) !== '', function ($q) {
                    $q->where('sl.title', 'like', '%' . trim($this->searchAttributes['filter_subject']) . '%');
                })
                ->when($this->searchAttributes['filter_post_time_from'] !== '', function ($q) {
                    $q->where('sl.send_time', '>=', $this->searchAttributes['filter_post_time_from']);
                })
                ->when($this->searchAttributes['filter_post_time_to'] !== '', function ($q) {
                    $q->where('sl.send_time', '<', $this->searchAttributes['filter_post_time_to']);
                })
                ->when($this->searchAttributes['filter_child_type_id'] !== '', function ($q) {
                    $q->where('sl.type', $this->searchAttributes['filter_child_type_id']);
                })
                ->when($this->searchAttributes['filter_read_status'] !== '', function ($q) {
                    $q->where('slc.is_read', $this->searchAttributes['filter_read_status']);
                })
                ->when($this->searchAttributes['filter_mark_status'] !== '', function ($q) {
                    $q->where('slc.is_marked', $this->searchAttributes['filter_mark_status']);
                })
                ->selectRaw('
                    slc.letter_id as notice_id,
                    slc.is_read as is_read,
                    slc.is_marked as is_marked,
                    sl.type as type_id,
                    sl.title,
                    "0" as top_status,
                    sl.send_time as publish_date,
                    "0" as effective_time,
                    sl.content as content,
                    sd.DicValue as type_name,
                    "0" as make_sure_status,
                    "0" as p_make_sure_status,
                    "1" as message_type,
                    "station_letter" as data_model,
                    slc.is_delete as delete_status
                ');
        }

        if (isset($noticeQuery) && isset($stationLetterCustomerQuery)) {
            return $noticeQuery->union($stationLetterCustomerQuery);
        }

        if (isset($noticeQuery) && !isset($stationLetterCustomerQuery)) {
            return $noticeQuery;
        }

        if (!isset($noticeQuery) && isset($stationLetterCustomerQuery)) {
            return $stationLetterCustomerQuery;
        }
    }
}
