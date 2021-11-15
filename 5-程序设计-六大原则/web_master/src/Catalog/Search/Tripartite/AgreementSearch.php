<?php
/**
 * Created by AgreementSearch.php
 * User: fuyunnan
 * Date: 2021/7/2
 * Time: 10:26
 */

namespace App\Catalog\Search\Tripartite;

use App\Enums\Tripartite\TripartiteAgreementRequestStatus;
use App\Enums\Tripartite\TripartiteAgreementStatus;
use App\Models\Tripartite\TripartiteAgreement;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;

class AgreementSearch
{
    use SearchModelTrait;

    private $customerId;
    private $searchAttributes = [
        'title' => '',
        'screenname' => '',
        'status' => ''
    ];

    /**
     * AgreementSearch constructor.
     * @param int $customerId
     */
    public function __construct($customerId)
    {
        $this->customerId = $customerId;
    }

    /**
     * @param array $condition
     * @throws \Framework\Exception\InvalidConfigException
     */
    public function search($condition)
    {
        $this->loadAttributes($condition);
        $query = $this->buildQuery($condition);
        $dataProvider = new QueryDataProvider($query);
        $dataProvider->setPaginator(['defaultPageSize' => 10]);
        return $dataProvider;
    }

    /**
     * description:
     * @param array $condition
     * @return TripartiteAgreement|\Framework\Model\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder
     * @throws
     */
    protected function buildQuery($condition)
    {
        return TripartiteAgreement::query()
            ->where(['buyer_id' => $this->customerId, 'is_deleted' => 0])
            ->whereHas('seller', function ($q) use ($condition) {
                if (isset($condition['screenname']) && $condition['screenname']) {
                    $q->where('screenname', 'like', '%' . trim($condition['screenname']) . '%');
                }
            })
            ->with(['requests' => function ($q) {
                $q->where('status', TripartiteAgreementRequestStatus::PENDING);
            }])
            ->with(['seller:customer_id,screenname'])
            ->where(function ($q) use ($condition) {
                if (isset($condition['title']) && $condition['title']) {
                    $q->where('title', 'like', '%' . trim($condition['title']) . '%');
                }
                if (isset($condition['status']) && $condition['status']) {
                    $q->where('status', $condition['status']);
                }
            })
            ->orderByRaw('FIELD (`status`, ' . join(',', TripartiteAgreementStatus::buyerOrderStatus()) . ') ASC')
            ->orderByDesc('create_time');

    }
}
