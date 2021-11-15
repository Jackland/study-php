<?php

namespace App\Catalog\Search\Message;

use App\Enums\Message\CustomerComplaintBoxStatus;
use App\Enums\Message\CustomerComplaintBoxType;
use Framework\DataProvider\Paginator;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use App\Models\Customer\CustomerComplaintBox;

class ComplaintBoxSearch
{
    use SearchModelTrait;

    private $customerId;

    private $searchAttributes = [
        'status' => ''
    ];

    public function __construct(int $customerId)
    {
        $this->customerId = $customerId;
    }

    public function get($params)
    {
        $this->loadAttributes($params);
        $query = $this->buildQuery();

        $dataProvider = new QueryDataProvider($query);

        $dataProvider->setPaginator(new Paginator([
            'defaultPageSize' => 10,
        ]));

        $data['total'] = $dataProvider->getTotalCount();
        $data['paginator'] = $dataProvider->getPaginator();
        $data['list'] = $this->formatList($dataProvider->getList(), $data['paginator']);
        $data['search'] = $this->getSearchData();

        return $data;
    }

    /**
     * 格式话返回数据
     *
     * @param $list
     * @param $paginator
     * @return array
     */
    private function formatList($list, $paginator)
    {
        $data = [];
        $no = ($paginator->getPage() - 1) * $paginator->getPageSize();
        foreach ($list as $item) {
            $data[] = [
                'no' => ++$no,
                'reason' => $item->reason,
                'id' => $item->id,
                'msg_id' => $item->msg_id,
                'type' => $item->type,
                'type_format' => CustomerComplaintBoxType::getDescription($item->type),
                'status_format' => CustomerComplaintBoxStatus::getDescription($item->status),
                'screenname' => $item->screenname,
                'create_time' => $item->create_time
            ];
        }

        return $data;
    }

    protected function buildQuery()
    {
        $query = CustomerComplaintBox::queryRead()->alias('ccb')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ccb.respondent_id', 'ctc.customer_id')
            ->where('ccb.complainant_id', $this->customerId)
            ->select(['ccb.*', 'ctc.screenname']);

        if ($this->searchAttributes['status']) {
            $query->where('ccb.status', $this->searchAttributes['status']);
        }

        return $query;
    }
}