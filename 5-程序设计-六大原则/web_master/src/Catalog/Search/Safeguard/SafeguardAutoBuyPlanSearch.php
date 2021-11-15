<?php

namespace App\Catalog\Search\Safeguard;

use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use App\Models\Safeguard\SafeguardAutoBuyPlan;
use Illuminate\Database\Capsule\Manager as DB;

class SafeguardAutoBuyPlanSearch
{
    use SearchModelTrait;

    private $customerId;

    public function __construct($customerId)
    {
        $this->customerId = $customerId;
    }

    public function search()
    {
        $query = $this->buildQuery();
        $dataProvider = new QueryDataProvider($query);
        $dataProvider->switchPaginator(false);
        $query->orderByDesc('sabp.update_time');
        return $dataProvider;
    }

    protected function buildQuery()
    {
        return SafeguardAutoBuyPlan::query()->alias('sabp')
            ->with(['planDetails' => function ($q) {
                $q->orderBy('effective_time', 'ASC')->orderBy(DB::Raw("case when expiration_time is null then '9999-12-31 23:59:59' else expiration_time end"), 'ASC');
            }])
            ->select(['sabp.*'])
            ->where('sabp.buyer_id', '=', $this->customerId);
    }
}
