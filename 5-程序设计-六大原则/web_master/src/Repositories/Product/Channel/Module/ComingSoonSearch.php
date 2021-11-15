<?php

namespace App\Repositories\Product\Channel\Module;

use App\Enums\Product\Channel\ChannelType;
use App\Enums\Product\Channel\ProductChannelDataType;
use App\Enums\Warehouse\ReceiptOrderStatus;
use App\Helper\CountryHelper;
use App\Models\Product\Channel\ChannelParamConfig;
use App\Models\Product\Product;
use Illuminate\Database\Query\Expression;
use ModelCommonCategory;
use Psr\SimpleCache\InvalidArgumentException;

class ComingSoonSearch extends BaseInfo
{

    private $buyerId;
    private $countryId;

    public function __construct()
    {
        parent::__construct();
        $this->buyerId = (int)customer()->getId();
        $this->countryId = (int)CountryHelper::getCountryByCode(session('country'));
    }

    /**
     * @inheritDoc
     * @throws InvalidArgumentException
     */
    function getData(array $param): array
    {
        $categoryId = (int)$param['category_id'];
        $page = (int)$param['page'];
        $pageLimit = (int)$this->getShowNum();
        [$this->productIds, $isEnd] = $this->getList($categoryId, $page, $pageLimit);
        return [
            'type' => ProductChannelDataType::PRODUCT,
            'data' => $this->productIds,
            'productIds' => $this->productIds,
            'is_end' => $isEnd,
        ];
    }

    /**
     * 搜索排序值*权重+即将到货参数*权重
     * @param int $categoryId
     * @param $page
     * @param $pageLimit
     * @return array
     * @throws InvalidArgumentException
     */
    private function getList($categoryId, $page, $pageLimit): array
    {
        $dmProductIds = $this->channelRepository->delicacyManagementProductId($this->buyerId);
        $comingSoonChannelParam = ChannelParamConfig::query()
            ->with(['channelParamConfigValue'])
            ->where(['name' => 'Coming Soon'])
            ->first();
        $channelParams = $comingSoonChannelParam->channelParamConfigValue->pluck('param_value', 'param_name')->toArray();
        ['搜索排序值' => $searchSortValue, '即将到货参数' => $comingSoonParam] = $channelParams;

        $query = Product::query()->alias('p')
            ->select(['c.customer_id', 'p.product_id'])
            ->selectRaw('md5(group_concat(ifnull(pa.associate_product_id,p.product_id) order by pa.associate_product_id asc)) as pmd5')
            ->selectRaw("(ifnull(pwc.custom_weight,0)*$searchSortValue+(1-if(datediff(ro.expected_date,now()) <= 30,datediff(ro.expected_date,now()),30)/30)*$comingSoonParam) as sort")
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_product_associate as pa', 'pa.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('tb_sys_receipts_order_detail as rod', 'rod.product_id', '=', 'p.product_id')
            ->leftJoin('tb_sys_receipts_order as ro', 'ro.receive_order_id', '=', 'rod.receive_order_id')
            ->leftJoin('tb_product_weight_config as pwc', 'pwc.product_id', '=', 'p.product_id')
            ->whereIn('c.customer_id', $this->channelRepository->getAvailableSellerId())
            ->when($categoryId > 0, function ($q) use ($categoryId) {
                /** @var ModelCommonCategory $cateModel */
                $cateModel = load()->model('common/category');
                $q->leftJoin('oc_product_to_category as ptc', 'ptc.product_id', '=', 'p.product_id');
                $q->whereIn('ptc.category_id', array_column($cateModel->getSonCategories($categoryId), 'category_id'));
            })
            ->when($categoryId == -1, function ($q) use ($categoryId) {
                $key =  $this->channelRepository->getChannelCategoryCacheKey(ChannelType::COMING_SOON);
                if (!cache()->has($key) || !is_array(cache($key))) {
                    $this->channelRepository->comingSoonCategory($this->buyerId, $this->countryId, ChannelType::COMING_SOON);
                }
                $categoryIdList = cache($key);
                $q->leftJoin('oc_product_to_category as ptc', 'ptc.product_id', '=', 'p.product_id');
                $q->where(function ($q) use ($categoryIdList) {
                    $q->orWhereIn('ptc.category_id', $categoryIdList);
                    $q->orWhereNull('ptc.category_id');
                });
            })
            ->when(!empty($dmProductIds), function ($q) use ($dmProductIds) {
                $q->whereNotIn('p.product_id', $dmProductIds);
            })
            ->where([
                'p.status' => 1,
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1,
                'p.product_type' => 0,
                'p.part_flag' => 0,
                'c.status' => 1,
                'c.country_id' => $this->countryId,
                'ro.status' => ReceiptOrderStatus::TO_BE_RECEIVED,
                'p.quantity' => 0,
            ])
            ->whereNotNull('ro.expected_date')
            ->whereNotNull('rod.expected_qty')
            ->whereRaw('ro.expected_date  > NOW()')
            ->groupBy(['p.product_id'])
            ->orderByRaw("ifnull(pwc.custom_weight,0)*$searchSortValue+(1-if(datediff(ro.expected_date,now()) <= 30,datediff(ro.expected_date,now()),30)/30)*$comingSoonParam desc");
        // 排序 去重 实现的去重方案
        $query = db(new Expression('(' . get_complete_sql($query) . ') as t'))->groupBy(['t.pmd5']);
        $query = $query->orderBy('t.customer_id')->orderByDesc('t.sort');
        $query = db(new Expression('(' . get_complete_sql($query) . ') as t'))
            ->select('*')
            ->selectRaw('@ns_count := IF(@ns_customer_id= customer_id, @ns_count + 1, 1) as rank')
            ->selectRaw('@ns_customer_id := customer_id');
        $query = db(new Expression('(' . get_complete_sql($query) . ') as t'))
            ->where('t.rank', '<=', 20);
        $query = db(new Expression('(' . get_complete_sql($query) . ') as t'))
            ->orderByDesc('t.sort')
            ->orderByDesc('t.product_id');
        $total = db(new Expression('(' . get_complete_sql($query) . ') as t'))->count();
        $isEnd = $page * $pageLimit > $total ? 1 : 0;
        $res = $query->forPage($page, $pageLimit)->pluck('t.product_id')->toArray();

        return [$res, $isEnd];
    }
}
