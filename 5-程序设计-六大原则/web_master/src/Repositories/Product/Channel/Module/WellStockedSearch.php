<?php

namespace App\Repositories\Product\Channel\Module;

use App\Enums\Product\Channel\CacheTimeToLive;
use App\Enums\Product\Channel\ChannelType;
use App\Enums\Product\Channel\ProductChannelDataType;
use App\Helper\CountryHelper;
use App\Models\Product\Product;
use Illuminate\Database\Query\Expression;
use ModelCommonCategory;
use Psr\SimpleCache\InvalidArgumentException;

class WellStockedSearch extends BaseInfo
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
    public function getData(array $param): array
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
     * 首次入库60天以上，库存>30，近14天售卖2个以上，近14天可售卖天数>30的产品
     * 可售卖天数=可售库存量/近14天销售量均值（即销售速度）
     * 排序 搜索排序值*权重
     * @param int $categoryId
     * @param int $page
     * @param int $pageLimit
     * @return array
     * @throws InvalidArgumentException
     */
    private function getList(int $categoryId, int $page, int $pageLimit): array
    {
        $dmProductIds = $this->channelRepository->delicacyManagementProductId($this->buyerId);
        $query = Product::query()->alias('p')
            ->select('p.product_id')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_product_exts as ope', 'ope.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('tb_sys_product_sales as sps', 'sps.product_id', '=', 'p.product_id')
            ->leftJoin('tb_product_weight_config as pwc', 'pwc.product_id', '=', 'p.product_id')
            ->where([
                'p.status' => 1,
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1,
                'p.product_type' => 0,
                'p.part_flag' => 0,
                'c.status' => 1,
                'c.country_id' => $this->countryId,
            ])
            ->whereIn('c.customer_id', $this->channelRepository->getAvailableSellerId())
            ->when($categoryId > 0, function ($q) use ($categoryId) {
                /** @var ModelCommonCategory $cateModel */
                $cateModel = load()->model('common/category');
                $q->leftJoin('oc_product_to_category as ptc', 'ptc.product_id', '=', 'p.product_id');
                $q->whereIn('ptc.category_id', array_column($cateModel->getSonCategories($categoryId), 'category_id'));
            })
            ->when($categoryId == -1, function ($q) use ($categoryId) {
                $key = $this->channelRepository->getChannelCategoryCacheKey(ChannelType::WELL_STOCKED);
                if (!cache()->has($key) || !is_array(cache($key))) {
                    $this->channelRepository->wellStockedCategory($this->buyerId, $this->countryId, ChannelType::WELL_STOCKED);
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
            ->where('p.quantity', '>', 30)
            ->where('sps.quantity_14', '>', 2)
            ->whereRaw('ope.receive_date  < DATE_SUB(NOW(), INTERVAL 60 DAY)')
            ->whereRaw('(p.quantity/(sps.quantity_14/14)) > 30')
            ->orderByDesc('pwc.custom_weight')
            ->orderByDesc('p.product_id')
            ->groupBy('p.product_id');
        $total = db(new Expression('(' . get_complete_sql($query) . ') as t'))->count();
        $isEnd = $page * $pageLimit > $total ? 1 : 0;
        $res = $query->forPage($page, $pageLimit)->pluck('p.product_id')->toArray();
        return [$res, $isEnd];
    }

    /**
     * @param int $number
     * @param int $cacheRefresh
     * @return array
     * @throws InvalidArgumentException
     */
    public function getWellStockProductIds(int $number, int $cacheRefresh = 0): array
    {
        $cacheKey = [__CLASS__, __FUNCTION__, customer()->getId(), session()->get('country')];
        if (cache()->has($cacheKey) && $cacheRefresh == 0) {
            return cache($cacheKey);
        }
        $dmProductIds = $this->channelRepository->delicacyManagementProductId($this->buyerId);
        $query = Product::query()->alias('p')
            ->select(['p.product_id', 'c.customer_id',])
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_product_exts as ope', 'ope.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('tb_sys_product_sales as sps', 'sps.product_id', '=', 'p.product_id')
            ->leftJoin('tb_product_weight_config as pwc', 'pwc.product_id', '=', 'p.product_id')
            ->where([
                'p.status' => 1,
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1,
                'p.product_type' => 0,
                'p.part_flag' => 0,
                'c.status' => 1,
                'c.country_id' => $this->countryId,
            ])
            ->when(!empty($dmProductIds), function ($q) use ($dmProductIds) {
                $q->whereNotIn('p.product_id', $dmProductIds);
            })
            ->whereIn('c.customer_id', $this->channelRepository->getAvailableSellerId())
            ->where('p.quantity', '>', 30)
            ->where('sps.quantity_14', '>', 2)
            ->havingRaw('min(ope.receive_date)  < DATE_SUB(NOW(), INTERVAL 60 DAY)')
            ->whereRaw('(p.quantity/(sps.quantity_14/14)) > 30')
            ->orderByDesc('pwc.custom_weight')
            ->orderByDesc('p.product_id')
            ->groupBy(['p.product_id']);
        $total = db(new Expression('(' . get_complete_sql((clone $query)->groupBy('p.product_id')) . ') as t'))->count();
        $res = [];
        foreach ($query->cursor() as $item) {
            $customerId = $item->customer_id;
            $productId = $item->product_id;
            if (!array_key_exists($customerId, $res)) {
                $res[$customerId] = $productId;
                if (count($res) >= $number) {
                    break;
                }
            }
        }

        $res = array_values($res);
        if (count($res) < $number) {
            $res = $query
                ->limit($number)
                ->pluck('p.product_id')
                ->toArray();
        }

        $fin = [
            'type' => ProductChannelDataType::PRODUCT,
            'data' => $res,
            'productIds' => $res,
            'is_end' => $total <= count($res) ? 1 : 0,
        ];
        cache()->set($cacheKey, $fin, CacheTimeToLive::FIFTEEN_MINUTES);
        return $fin;
    }
}
