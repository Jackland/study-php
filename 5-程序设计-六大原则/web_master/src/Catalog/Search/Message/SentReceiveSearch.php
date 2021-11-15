<?php

namespace App\Catalog\Search\Message;

use App\Models\Customer\Customer;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Message\MsgReceive;
use Framework\DataProvider\Paginator;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Sort;

class SentReceiveSearch
{
    use SearchModelTrait;

    private $senderId;
    private $msgId;
    private $isSeller;

    private $searchAttributes = [
        'filter_receiver' => '',
    ];

    /**
     * SentReceiveSearch constructor.
     * @param int $senderId
     * @param int $msgId
     */
    public function __construct(int $senderId, int $msgId)
    {
        $this->senderId = $senderId;
        $this->msgId = $msgId;
        $this->isSeller = customer()->isPartner();
    }

    /**
     * @param $params
     * @return array
     * @throws \Framework\Exception\InvalidConfigException
     */
    public function get($params)
    {
        $this->loadAttributes($params);

        $query = $this->buildQuery();

        $dataProvider = new QueryDataProvider($query);

        $dataProvider->setSort(new Sort([
            'defaultOrder' => ['id' => SORT_DESC],
            'rules' => [
                'id' => 'id',
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
     * @return \Framework\Model\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder
     */
    protected function buildQuery()
    {
        $query = MsgReceive::queryRead()->with(['receiver', 'receiver.seller'])->where('msg_id', request('msg_id'));
        if (trim($this->searchAttributes['filter_receiver'])) {
            if (!$this->isSeller) {
                $receiverIds = CustomerPartnerToCustomer::query()->where('screenname', 'like', '%' . $this->searchAttributes['filter_receiver'] . '%')->pluck('customer_id')->toArray();
            } else {
                $receiverIds = Customer::query()->where(function ($q) {
                    $q->where('nickname', 'like', '%' . trim($this->searchAttributes['filter_receiver']) . '%')->orWhere('user_number', trim($this->searchAttributes['filter_receiver']));
                })->pluck('customer_id')->toArray();
            }
            $query = $query->whereIn('receiver_id', $receiverIds);
        }

        return $query;
    }
}
