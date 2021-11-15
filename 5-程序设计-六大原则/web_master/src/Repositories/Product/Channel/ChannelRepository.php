<?php

namespace App\Repositories\Product\Channel;

use App\Components\Storage\StorageCloud;
use App\Components\Traits\RequestCachedDataTrait;
use App\Enums\Common\CountryEnum;
use App\Enums\Product\Channel\CacheTimeToLive;
use App\Enums\Product\ProductType;
use App\Enums\Warehouse\ReceiptOrderStatus;
use App\Helper\CountryHelper;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\CustomerPartner\ProductGroupLink;
use App\Models\DelicacyManagement\DelicacyManagement;
use App\Models\Futures\FuturesContract;
use App\Models\Link\ProductToCategory;
use App\Models\Margin\MarginTemplate;
use App\Models\Product\Product;
use App\Models\Rebate\RebateAgreementTemplateItem;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\Seller\SellerRepository;
use Exception;
use Framework\Helper\ArrayHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use ModelCommonCategory;
use Psr\SimpleCache\InvalidArgumentException;

class ChannelRepository
{
    use RequestCachedDataTrait;
    /**
     * 获取当前用户精细化不可见产品
     * @param int $customerId
     * @return array
     * @throws InvalidArgumentException
     */
    public function delicacyManagementProductId(int $customerId): array
    {

        if (!$customerId) {
            return [];
        }

        $cacheKey = [__CLASS__, __FUNCTION__, $customerId];
        if (cache()->has($cacheKey)) {
            return cache($cacheKey) ?? [];
        }

        $notIn = ProductGroupLink::query()->alias('pgl')
            ->leftjoin('oc_delicacy_management_group as dmg', 'dmg.product_group_id', 'pgl.product_group_id')
            ->leftjoin('oc_customerpartner_buyer_group_link as bgl', 'bgl.buyer_group_id', 'dmg.buyer_group_id')
            ->where([
                'bgl.status' => 1,
                'pgl.status' => 1,
                'dmg.status' => 1,
                'bgl.buyer_id' => $customerId
            ])
            ->pluck('pgl.product_id')
            ->toArray();

        $notIn2 = DelicacyManagement::query()
            ->where([
                'buyer_id' => $customerId,
                'product_display' => 0
            ])
            ->pluck('product_id')
            ->toArray();

        cache()->set($cacheKey, array_unique(array_merge($notIn, $notIn2)), CacheTimeToLive::FIVE_MINUTES);
        return cache($cacheKey);
    }

    /**
     * 获取seller店铺的信息
     * @param array $storeIds
     * @param array $config 配置
     * @return Collection $items = [
     *                $sellerId => [
     *                              'id' => $sellerId,
     *                              'name' => '',
     *                              'hasStoreAudit' => '' ,
     *                              'isNativeAmerican'=> true ,
     *                              'agreements' => []
     *                           ]
     *               ]
     * @throws Exception
     */
    public function getBaseStoreInfos(array $storeIds, array $config = []): Collection
    {
        if (empty($storeIds)) {
            return collect();
        }
        $config = ArrayHelper::merge(['hasRate' => false,], $config);
        $customerRepo = app(CustomerRepository::class);
        $sellerRepo = app(SellerRepository::class);
        $complexTransaction = $this->getComplexTransactionBySellerIds($storeIds);
        return CustomerPartnerToCustomer::with(['customer', 'sellerStore'])
            ->find($storeIds)
            ->keyBy('customer_id')
            ->map(function (CustomerPartnerToCustomer $ctc) use ($customerRepo, $sellerRepo, $config , $complexTransaction) {
                $ret = [
                    'id' => $ctc->customer_id,
                    'name' => html_entity_decode($ctc->screenname),
                    'hasStoreAudit' => $ctc->sellerStore && $ctc->sellerStore->store_home_json,
                    'score' => $ctc->performance_score,
                    'isNativeAmerican' => $customerRepo->checkSellerAccountManagerCountry($ctc->customer_id, CountryEnum::AMERICA),
                    'isOutNewSeller' => $sellerRepo->isOutNewSeller($ctc->customer_id),
                    'agreements' => [],
                    'avatar' => StorageCloud::image()->getUrl($ctc->avatar, ['w' => 50, 'h' => 50,'check-exist' => false])
                ];
                if ($config['hasRate']) {
                    // 退返率
                    $ret['return_rate'] = $ctc->returns_rate;
                    $ret['return_rate_str'] = $ctc->return_rate_str;
                    // 消息回复率
                    $ret['response_rate'] = $ctc->response_rate;
                    $ret['response_rate_str'] = $ctc->response_rate_str;;
                    // 退返率
                    $ret['return_approval_rate'] = $ctc->return_approval_rate;
                    $ret['return_approval_rate_str'] = $ctc->return_approval_rate_str;
                }
                // 协议
                if (in_array($ctc->customer_id,$complexTransaction['has_rebates'])) {
                    $ret['agreements'][] = 'Rebates';
                }
                if (in_array($ctc->customer_id,$complexTransaction['has_margin'])) {
                    $ret['agreements'][] = 'Margin';
                }
                if (in_array($ctc->customer_id,$complexTransaction['has_futures'])) {
                    $ret['agreements'][] = 'Futures';
                }

                return $ret;
            });
    }

    private function getComplexTransactionBySellerIds(array $sellerIds): array
    {
        $has_margin = MarginTemplate::query()->alias('mt')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'mt.product_id')
            ->where([
                'p.status' => 1,
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1,
                'mt.is_del' => 0,
            ])
            ->whereIn('mt.seller_id', $sellerIds)
            ->distinct()
            ->pluck('seller_id')
            ->toArray();

        $has_futures = FuturesContract::query()->alias('fc')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'fc.product_id')
            ->where([
                'p.status' => 1,
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1,
                'fc.is_deleted' => 0,
                'fc.status' => 1,
            ])
            ->whereIn('fc.seller_id', $sellerIds)
            ->distinct()
            ->pluck('seller_id')
            ->toArray();

        $has_rebates = RebateAgreementTemplateItem::query()->alias('rti')
            ->leftjoin('oc_rebate_template as rt', 'rti.template_id', '=', 'rt.id')
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'rti.product_id')
            ->where([
                'p.status' => 1,
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1,
                'rt.is_deleted' => 0,
                'rti.is_deleted' => 0,
            ])
            ->whereIn('rt.seller_id', $sellerIds)
            ->distinct()
            ->pluck('seller_id')
            ->toArray();

        return compact('has_futures','has_margin','has_rebates');
    }

    /**
     * 获取货源稳定分类
     * 首次入库60天以上，上架库存>30，近14天售卖2个以上，近14天可售卖天数>30的产品
     * 可售卖天数=可售库存量/近14天销售量均值（即销售速度）
     * @param int $buyerId
     * @param int $countryId
     * @param string $type
     * @return array
     * @throws InvalidArgumentException
     */
    public function wellStockedCategory(int $buyerId, int $countryId, string $type): array
    {
        $dmProductIds = $this->delicacyManagementProductId($buyerId);
        $res = db('oc_product as p')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_product_exts as ope', 'ope.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('tb_sys_product_sales as sps', 'sps.product_id', '=', 'p.product_id')
            ->leftJoin('oc_product_to_category as ptc', 'ptc.product_id', '=', 'p.product_id')
            ->where([
                'p.status' => 1,
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1,
                'p.product_type' => 0,
                'p.part_flag' => 0,
                'c.status' => 1,
                'c.country_id' => $countryId,
            ])
            ->when(!empty($dmProductIds), function ($q) use ($dmProductIds) {
                $q->whereNotIn('p.product_id', $dmProductIds);
            })
            ->whereIn('c.customer_id', $this->getAvailableSellerId())
            ->where('p.quantity', '>', 30)
            ->where('sps.quantity_14', '>', 2)
            ->whereRaw('ope.receive_date  < DATE_SUB(NOW(), INTERVAL 60 DAY)')
            ->whereRaw('(p.quantity/(sps.quantity_14/14)) > 30')
            ->whereNotNull('ptc.category_id')
            ->groupBy(['ptc.category_id', 'p.product_id'])
            ->select(['ptc.category_id', 'p.product_id'])
            ->get()
            ->map(function ($item) {
                return get_object_vars($item);
            })
            ->toArray();
        return $this->getChannelCategory($res, $type);
    }

    /**
     * @param int $buyerId
     * @param int $countryId
     * @param string $type
     * @return array
     * @throws InvalidArgumentException
     */
    public function comingSoonCategory(int $buyerId, int $countryId, string $type): array
    {
        $dmProductIds = $this->delicacyManagementProductId($buyerId);
        $res = db('oc_product as p')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('tb_sys_receipts_order_detail as rod', 'rod.product_id', '=', 'p.product_id')
            ->leftJoin('tb_sys_receipts_order as ro', 'ro.receive_order_id', '=', 'rod.receive_order_id')
            ->leftJoin('oc_product_to_category as ptc', 'ptc.product_id', '=', 'p.product_id')
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
                'c.country_id' => $countryId,
                'ro.status' => ReceiptOrderStatus::TO_BE_RECEIVED,
                'p.quantity' => 0,
            ])
            ->whereIn('c.customer_id', $this->getAvailableSellerId())
            ->whereNotNull('ro.expected_date')
            ->whereNotNull('rod.expected_qty')
            ->whereRaw('ro.expected_date  > NOW()')
            ->whereNotNull('ptc.category_id')
            ->groupBy(['ptc.category_id', 'p.product_id'])
            ->select(['ptc.category_id', 'p.product_id'])
            ->get()
            ->map(function ($item) {
                return get_object_vars($item);
            })
            ->toArray();
        return $this->getChannelCategory($res, $type);
    }

    /**
     * 获取当前国别有效的customerId
     * @return array
     * @throws InvalidArgumentException
     */
    public function getAvailableSellerId(): array
    {
        //        1、店铺限制：
        //（1）Unused分组店铺：
        //①US-Seller-Unused
        //②UK-Seller-Unused
        //③DE-Seller-Unused
        //④JP-Seller-Unused
        //（2）首页隐藏店铺：
        //①保证金店铺：
        //-bxw@gigacloudlogistics.com(外部产品)？
        //    -bxo@gigacloudlogistics.com
        //    -nxb@gigacloudlogistics.com
        //    -UX_B@oristand.com
        //    -DX_B@oristand.com
        //②服务店铺
        //美：service@gigacloudlogistics.com
        //英：serviceuk@gigacloudlogistics.com
        //德：DE-SERVICE@oristand.com
        //日：servicejp@gigacloudlogistics.com
        //（3）测试店铺：oc_customer，accounting_type=3
        //（4）服务店铺：oc_customer，accounting_type=4
        //（5）oc_customerpartner_to_customer表，show=1
        //（6）oc_product_to_store表，store_id=0
        //（7）状态为关店的店铺

        return $this->requestCachedData([__CLASS__, __FUNCTION__, CountryHelper::getCountryByCode(session()->get('country'))], function () {
            $unAvailableStoreId = explode(',', implode(',', HOME_HIDE_CUSTOMER));
            return  CustomerPartnerToCustomer::query()->alias('ctc')
                ->leftJoinRelations(['customer as c'])
                ->whereNotIn('c.customer_group_id', HOME_HIDE_CUSTOMER_GROUP)
                ->whereNotIn('c.accounting_type', HOME_HIDE_ACCOUNTING_TYPE)
                ->where('c.status', 1)
                ->where('ctc.show', 1)
                ->where('c.country_id', CountryHelper::getCountryByCode(session()->get('country')))
                ->whereNotIn('c.customer_id', $unAvailableStoreId)
                ->select('c.customer_id')
                ->get()
                ->pluck('customer_id')
                ->toArray();
        });
    }

    /**
     * @param array $data [['category_id',=> 'xxx','product_id'=> 'xxxx']]
     * @param string $type
     * @return array
     * @throws Exception|InvalidArgumentException
     */
    public function getChannelCategory(array $data, string $type): array
    {
        $productLimit = 2;
        $categories[] = ['category_id' => 0, 'name' => 'All', 'amount' => count(array_unique(array_column($data, 'product_id')))];
        $tmpOthers = ['category_id' => -1, 'name' => 'Others',];
        /** @var ModelCommonCategory $cateModel */
        $cateModel = load()->model('common/category');
        // 记录当前已经处理过的数据信息
        $ret = [];
        $parentInfo = [];
        $hadOthers = 0;
        foreach ($data as $items) {
            if ($items['category_id'] == -1) {
                $hadOthers = 1;
            }

            if (isset($parentInfo[$items['category_id']])) {
                $cateInfo = $parentInfo[$items['category_id']];
            } else {
                $cateInfo = $cateModel->getParentCategories($items['category_id']);
                $parentInfo[$items['category_id']] = $cateInfo;
            }
            if (empty($cateInfo)) continue;
            $parentCateInfo = current($cateInfo);
            if ($parentCateInfo['parent_id'] == 0 && isset($ret[$parentCateInfo['category_id']]['category'])
                && !in_array($items['product_id'], $ret[$parentCateInfo['category_id']]['product'])
            ) {
                $ret[$parentCateInfo['category_id']]['category'][] = $items['category_id'];
                $ret[$parentCateInfo['category_id']]['product'][] = $items['product_id'];
                if (count(array_unique($ret[$parentCateInfo['category_id']]['product'])) == $productLimit) {
                    $categories[] = ['category_id' => $parentCateInfo['category_id'], 'name' => $parentCateInfo['name']];
                }
            } else {
                $ret[$parentCateInfo['category_id']]['category'][] = $items['category_id'];
                $ret[$parentCateInfo['category_id']]['product'][] = $items['product_id'];
            }
        }

        $searchCategory = [];
        foreach ($ret as $value) {
            if (count(array_unique($value['product'])) < $productLimit) {
                $hadOthers = 1;
                foreach ($value['category'] as $v) {
                    $searchCategory[] = $v;
                }
            }
        }
        if ($hadOthers) {
            foreach ($parentInfo as $key => $items) {
                if (!$items) {
                    $searchCategory[] = $key;
                }
            }
            if (count($categories) > 1) {
                $categories[] = $tmpOthers;
            }
        }
        // 针对于
        $key = $this->getChannelCategoryCacheKey($type);
        cache()->set($key, $searchCategory, CacheTimeToLive::ONE_MINUTE);
        return $categories;

    }

    /**
     * 限时限量活动频道页 的 产品分类
     * @param int $countryId
     * @param array $productIdList
     * @return array
     * @throws Exception|InvalidArgumentException
     */
    public function getChannelCategoryForMarketingTimeLimitByCountryId($countryId = 223, $productIdList = []): array
    {
        if (count($productIdList) < 1) {
            return [];
        }
        $cacheKey = 'MARKETING_TIME_LIMIT_COUNTRY_ID_' . $countryId;
        if (cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        $builder = ProductToCategory::query()->alias('ptc')
            ->whereIn('ptc.product_id', $productIdList)
            ->selectRaw('ptc.category_id,ptc.product_id');
        $allCategoryList = $builder->get()->toArray();
        $hasCategoryProducts = $builder->pluck('product_id')->toArray();
        $diff = array_diff($productIdList, $hasCategoryProducts);
        foreach ($diff as $key => $value) {
            $allCategoryList[] = [
                'category_id' => -1,
                'product_id' => $value,
            ];
        }
        // $allCategoryList = [['category_id',=> 'xxx','product_id'=> 'xxxx']]
        $productLimit = 2;
        $categories[] = ['category_id' => 0, 'name' => 'All', 'amount' => count($productIdList)];
        $tmpOthers = ['category_id' => -1, 'name' => 'Others',];
        /** @var ModelCommonCategory $cateModel */
        $cateModel = load()->model('common/category');
        // 记录当前已经处理过的数据信息
        $ret = [];
        $parentInfo = [];
        $hadOthers = 0;
        foreach ($allCategoryList as $items) {
            if ($items['category_id'] == -1) {
                $hadOthers = 1;
            }
            if (isset($parentInfo[$items['category_id']])) {
                $cateInfo = $parentInfo[$items['category_id']];
            } else {
                $cateInfo = $cateModel->getParentCategories($items['category_id']);
                $parentInfo[$items['category_id']] = $cateInfo;
            }
            if (empty($cateInfo)) continue;
            $parentCateInfo = current($cateInfo);
            if ($parentCateInfo['parent_id'] == 0 && isset($ret[$parentCateInfo['category_id']]['category'])
                && !in_array($items['product_id'], $ret[$parentCateInfo['category_id']]['product'])
            ) {
                $ret[$parentCateInfo['category_id']]['category'][] = $items['category_id'];
                $ret[$parentCateInfo['category_id']]['product'][] = $items['product_id'];
                if (count(array_unique($ret[$parentCateInfo['category_id']]['product'])) == $productLimit) {
                    $categories[] = ['category_id' => $parentCateInfo['category_id'], 'name' => $parentCateInfo['name']];
                }
            } else {
                $ret[$parentCateInfo['category_id']]['category'][] = $items['category_id'];
                $ret[$parentCateInfo['category_id']]['product'][] = $items['product_id'];
            }
        }

        if ($hadOthers) {
            if (count($categories) > 0) {
                $categories[] = $tmpOthers;
            }
        }
        cache()->set($cacheKey, $categories, 60);
        return $categories;

    }

    public function getChannelCategoryCacheKey($type): array
    {
        return [session('country'),customer()->getId(),$type];
    }



    /**
     * 16460中新增产品限制条件
     * @param int $quantity
     * @return array
     * @throws InvalidArgumentException
     */
    public function getUnavailableProductIds($quantity = 0): array
    {
        // 获取精细化不可见的产品
        $dmProductIds = $this->delicacyManagementProductId((int)customer()->getId());
        // 产品库存限制无在库库存 or onhand_qty <= 0
        $cacheKey = [__CLASS__, __FUNCTION__, $quantity];
        if (cache()->has($cacheKey)) {
            return cache($cacheKey) ?? [];
        }
        $storeProductIds = Product::query()->alias('p')
            ->where(function (Builder $q) use ($quantity) {
                $q->orWhere('p.quantity', '<=', $quantity);
                $q->orWhere('p.part_flag', '=', 1);

                return $q;
            })
            ->where([
                'p.status' => 1,
                'p.buyer_flag' => 1,
                'p.product_type' => ProductType::NORMAL,
            ])
            ->groupBy(['p.product_id'])
            ->selectRaw('p.product_id,p.quantity,p.part_flag')
            ->pluck('product_id')
            ->toArray();

        $ret = array_merge($dmProductIds, $storeProductIds);
        cache()->set($cacheKey, $ret, CacheTimeToLive::FIVE_MINUTES);
        return cache($cacheKey);
    }
}
