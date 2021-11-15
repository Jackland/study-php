<?php

namespace App\Catalog\Search\MarketingTimeLimit;

use App\Enums\Common\YesNoEnum;
use App\Enums\Marketing\MarketingTimeLimitProductStatus;
use App\Models\Marketing\MarketingTimeLimit;
use Carbon\Carbon;
use Framework\DataProvider\QueryDataProvider;
use Framework\DataProvider\SearchModelTrait;
use Framework\DataProvider\Sort;
use Illuminate\Database\Query\Expression;

class HomeListAllSearch
{
    use SearchModelTrait;

    private $customerId;
    private $countryId;
    private $searchAttributes = [
        'category_id' => '0',
        'sort' => 'discount',
        'order' => 'ASC',
        'page' => '1',
        'page_limit' => '20',
        'country' => '',
        'min_price' => '',
        'max_price' => '',
        'min_quantity' => '1',
        'max_quantity' => '',
    ];

    public function __construct($customerId, $countryId)
    {
        $this->customerId = $customerId;
        $this->countryId = $countryId;
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
                'defaultOrder' => ['discount' => SORT_ASC],
                'rules' => [
                    'discount' => 'mp.discount',
                    'price' => 'mp.price',
                ],
            ]));
            $dataProvider->setPaginator(['defaultPageSize' => 9]); // 'pageSizeParam' =>'page_limit_new'
        } else {
            $dataProvider->switchSort(false);
            $dataProvider->switchPaginator(false);
            $query->orderByDesc('a.update_time');
        }
        return $dataProvider;
    }

    protected function buildQuery()
    {
        $now = request('now', Carbon::now()->toDateTimeString());
        return MarketingTimeLimit::query()->alias('m')
            ->leftJoinRelations(['products as mp'])
            ->leftjoin('oc_customer as seller', 'm.seller_id', '=', 'seller.customer_id')
            ->where('m.effective_time', '<=', $now)
            ->where('m.expiration_time', '>=', $now)
            ->where('m.is_del', '=', YesNoEnum::NO)
            ->where('m.status', '=', 1)
            ->where('mp.qty', '>', 0)
            ->where('mp.status', '=', MarketingTimeLimitProductStatus::NO_RELEASE)
            ->where('seller.country_id', '=', $this->countryId)
            ->select(['m.id', 'mp.product_id', 'mp.discount', 'mp.price', 'mp.qty'])
            ->selectRaw(new Expression('ROUND(100-mp.discount) AS discountShow'));
    }
}
