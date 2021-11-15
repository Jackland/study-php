<?php

use App\Enums\Common\YesNoEnum;
use App\Enums\Search\ComplexTransactions;
use App\Helper\CountryHelper;
use App\Logging\Logger;
use App\Repositories\Product\Search\SearchParam;
use Illuminate\Database\Capsule\Manager as DB;
use App\Enums\Warehouse\ReceiptOrderStatus;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Class ModelCatalogSearch
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelExtensionModuleProductShow $model_extension_module_product_show
 * @property ModelExtensionModuleProductHome $model_extension_module_product_home
 * @property ModelCustomerpartnerStoreRate $model_customerpartner_store_rate
 * @property ModelToolSphinx $model_tool_sphinx
 */
class ModelCatalogSearch extends Model
{
    //public $table;
    const STATUS_ACTIVE = 1; //有效
    const STATUS_INACTIVE = 0; //无效
    const ADVERT_PAGE = 3;
    const ADVERT_FIRST = 3; //无效
    const ADVERT_LIMIT = 20; //无效
    const COUNTRY_ENG = [
        'JPN' => 107,
        'GBR' => 222,
        'DEU' => 81,
        'USA' => 223,
    ];

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        //$this->table = DB_PREFIX . 'product';
    }

    /**
     * 获取因精细化不可的商品ID
     * @param int $customerId
     * @param int $sellerId
     * @return array
     */
    public function unSeeProductId($customerId, $sellerId = 0)
    {

        $notIn = $this->orm->table('oc_customerpartner_product_group_link as pgl')
            ->leftjoin('oc_delicacy_management_group as dmg', 'dmg.product_group_id', 'pgl.product_group_id')
            ->leftjoin('oc_customerpartner_buyer_group_link as bgl', 'bgl.buyer_group_id', 'dmg.buyer_group_id')
            ->where([
                'bgl.status' => 1,
                'pgl.status' => 1,
                'dmg.status' => 1,
                'bgl.buyer_id' => $customerId
            ])
            ->when($sellerId, function ($q) use ($sellerId) {
                return $q->where('dmg.seller_id', $sellerId);
            })
            ->pluck('pgl.product_id')
            ->toArray();

        $notIn2 = $this->orm->table('oc_delicacy_management')
            ->where([
                'buyer_id' => $customerId,
                'product_display' => 0
            ])
            ->when($sellerId, function ($q) use ($sellerId) {
                return $q->where('seller_id', $sellerId);
            })
            ->pluck('product_id')
            ->toArray();

        $notIn = array_unique(array_merge($notIn, $notIn2));
        return $notIn;
    }

    /**
     * 获取已建立联系的seller ID
     * @param int $customerId
     * @return array
     */
    public function mySellersIdList($customerId)
    {
        return $this->orm->table('oc_buyer_to_seller')
            ->where('buyer_id', $customerId)
            ->where('buy_status', 1)
            ->where('buyer_control_status', 1)
            ->where('seller_control_status', 1)
            ->pluck('seller_id')
            ->toArray();
    }

    /**
     * 获取已收藏订阅的商品ID
     * @param int $customerId
     * @return array
     */
    public function productIdWishList($customerId)
    {
        return $this->orm->table('oc_customer_wishlist')
            ->where('customer_id', $customerId)
            ->pluck('product_id')
            ->toArray();

    }

    /**
     * 获取子分类ID
     * @param int $categoryId
     * @return array
     */
    public function childrenCategories($categoryId)
    {
        return $this->orm->table('oc_category_path')
            ->where('path_id', $categoryId)
            ->pluck('category_id')
            ->toArray();
    }

    /**
     * 新品
     * @return array
     */
    public function newProductIdList()
    {

        $t = date('Y-m-d H:i:s', strtotime('-45 day'));
        $list = $this->orm->table('oc_product as p')
            ->leftjoin('tb_sys_receipts_order_detail AS rod', 'rod.product_id', 'p.product_id')
            ->leftjoin('tb_sys_receipts_order as ro', 'ro.receive_order_id', 'rod.receive_order_id')
            ->where('p.date_added', '>=',$t)
            ->where(function ($q){
                return $q->where(function ($q){
                    return $q->where('ro.status', ReceiptOrderStatus::TO_BE_RECEIVED)
                        ->where('ro.expected_date', '>', date('Y-m-d H:i:s'))
                        ->where('rod.expected_qty', '!=', null);
                })
                    ->orWhere('ro.status', ReceiptOrderStatus::RECEIVED);
            })
            ->pluck('p.product_id')
            ->toArray();

        return $list;
    }

    /**
     * 已下载产品ID
     * @param int $customerId
     * @return array
     */
    public function downloadProductIdList($customerId)
    {
        $list = $this->orm->table('tb_sys_product_package_info')
            ->where('customer_id', $customerId)
            ->pluck('product_id')
            ->toArray();

        return $list;
    }

    /**
     * 已购买产品ID
     * @param int $customerId
     * @return array
     */
    public function purchaseProductIdList($customerId)
    {
        return $this->orm->table('oc_order as o')
            ->leftJoin('oc_order_product as op', 'o.order_id', 'op.order_id')
            ->where('o.customer_id', $customerId)
            ->pluck('op.product_id')
            ->toArray();
    }

    /**
     * N-95 商品搜索
     * @param array $filterData
     * @param int $customerId
     * @param int $isPartner
     * @param array $relevanceData
     * @return array
     * @throws Exception
     */
    public function search($filterData = [], $customerId = 0, $isPartner = 0, $relevanceData = [])
    {
        $this->load->model('extension/module/product_home');
        $product_id_list = array_column($relevanceData['products'],'productId');
        if($product_id_list){
            $ret = $this->model_extension_module_product_home->getHomeProductInfo($product_id_list,$customerId);
            $ret = array_column($ret,null,'product_id');
        }else{
            $ret = [];
        }
        $product_data = [];
        foreach ($relevanceData['products'] as $key => $value) {
            if(isset($ret[$value['productId']]) && $ret[$value['productId']]['unsee'] == 0){
                $product_data[$value['productId']] = $ret[$value['productId']];
                $product_data[$value['productId']]['sponsored'] = $value['sponsored'];
            } else{
                continue;
            }

            if ($this->config->get('module_marketplace_status') && !$product_data[$value['productId']]) {
                unset($product_data[$value['productId']]);
            }
        }
        // 根据page limit 和最后的两个给到角标
        $tag_key = $this->getAdvertProductKey();
        if ($filterData['sort'] == 'p.sort_order') {
            $count = ($filterData['page'] - 1) * $filterData['limit'];
            foreach ($product_data as $key => &$value) {
                $count++;
                if (in_array($count, $tag_key) && $value['sponsored']) {
                    $value['advertTag'] = 1;
                } else {
                    $value['advertTag'] = 0;
                }
            }
        }

        return $product_data;
    }

    /**
     * @param int $seller_id
     * @return array
     * @throws Exception
     */
    public function getSellerRateInfo($seller_id)
    {
        $seller_info = $this->orm->table('oc_customerpartner_to_customer')
            ->where('customer_id', $seller_id)
            ->first();
        $this->load->model('customerpartner/store_rate');
        $this->load->model('extension/module/product_home');
        $seller_return_approval_rate = $this->model_extension_module_product_home->returnApprovalRate([$seller_id]);
        //店铺退返率标签
        $store_return_rate_mark = $this->model_customerpartner_store_rate->returnsMarkByRate($seller_info->returns_rate);
        //店铺回复率标签
        $store_response_rate_mark = $this->model_customerpartner_store_rate->responseMarkByRate($seller_info->response_rate);
        return [
            'return_approval_rate' => $seller_return_approval_rate[$seller_id] ?? '',
            'store_response_rate_mark' => $store_response_rate_mark,
            'store_return_rate_mark' => $store_return_rate_mark,
        ];
    }

    public function getAdvertProductKey()
    {
        $tag_first = self::ADVERT_PAGE;
        $tag_key = [];
        for ($i = 0; $i < self::ADVERT_FIRST; $i++) {
            $tag_key[] = $i * self::ADVERT_LIMIT + $tag_first;
            $tag_key[] = $i * self::ADVERT_LIMIT + $tag_first + 1;
            $tag_key[] = ($i + 1) * self::ADVERT_LIMIT - 1;
            $tag_key[] = ($i + 1) * self::ADVERT_LIMIT;
        }
        return $tag_key;
    }

    /**
     * [searchRelevanceProductId description] 发送请求给java请求默认情况下的查询条件的数据
     * @param array $filterData
     * @param int $customerId
     * @param mixed $scene
     * @param string $logTitle
     * @return mixed
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @see http://t1.b2b.orsd.tech:8088/doc.html#/uid-generator/%E4%BA%A7%E5%93%81%E6%9F%A5%E8%AF%A2%E6%8E%A7%E5%88%B6%E5%B1%82/searchProductUsingPOST
     */
    public function searchRelevanceProductId($filterData = [], $customerId = 0, $scene = SearchParam::SCENE[0], $logTitle = '')
    {
        $searchParam = new SearchParam($scene, []);
        $params = $searchParam->getData($filterData,$customerId);
        $loggerTime = micro_time();
        Logger::search('search api调用开始:' . $loggerTime . ($logTitle ? ' ' . $logTitle : ''));
        Logger::search($params);
        try {
            $client = HttpClient::create();
            $response = $client->request('POST', URL_ES, [
                'json' => $params,
                'timeout' => 5,
            ]);
            $data = $response->toArray(false);
            Logger::search('search api调用结束.' . $loggerTime . '-' . micro_time());
            Logger::search('---' . json_encode($data) . '---');

            if (isset($data) && isset($data['code']) && $data['code'] == 200) {
                return $data['data'];
            }

            return false;
        } catch (Exception $e) {
            Logger::search($e->getMessage(),'error');
            return false;
        }
    }

    public function getParamsOfSearch($data)
    {
        $arrRemoveNull = ['min_price', 'max_price', 'min_quantity', 'max_quantity'];
        foreach ($arrRemoveNull as $key => $value) {
            $data[$value] = (!array_key_exists($value, $data) || trim($data[$value]) === '') ? null : trim($data[$value]);
        }
        if (isset($data['qty_status']) && $data['qty_status'] !== '') {
            if ($data['min_quantity'] == '') {
                $data['min_quantity'] = 1;
            }
        }

        $arrStatus = ['img_status', 'download_status', 'wish_status', 'purchase_status', 'relation_status'];
        $compare = [
            0 => null,
            1 => true,
            2 => false,
            3 => null,
        ];
        foreach ($arrStatus as $key => $value) {
            if (array_key_exists($value, $data) && isset($compare[$data[$value]])) {
                $data[$value] = $compare[$data[$value]];
            } else {
                $data[$value] = null;
            }
        }

        // complexTrades
        $data['complexTrades'] = [];
        if ($data['rebates']) {
            $data['complexTrades'][] = ComplexTransactions::REBATE;
        }
        if ($data['margin']) {
            $data['complexTrades'][] = ComplexTransactions::MARGIN;
        }
        if ($data['futures']) {
            $data['complexTrades'][] = ComplexTransactions::FUTURE;
        }
        //inventory
        if(!isset($data['whId'])){
            $data['whId'] = [];
        }

        // order by
        $data['orderBy'] = strtolower($data['order']) == 'desc' ? 'desc' : 'asc';
        // sort type
        $sortArr = [
            0 => 'p.sort_order',//默认排序
            1 => 'p.price',//价格排序
            2 => 'p.quantity',//上架数量排
            3 => 'pas.quantity',//总销量排序
            4 => 'p.date_added',//产品上架时间
            5 => 'return_rate',//退返率排序
            6 => 'marketingDiscount',//限时限量折扣排序
            7 => 'marketingPrice',//限时限量的价格
            8 => 'marketingQty',//限时限量库存排序
            9 => 'marketingExpirationTime',//失效时间排序，当前时间和9一起传
        ];
        $data['sortType'] = array_search($data['sort'],$sortArr) ?? 0;
        // country id
        $data['countryId'] = CountryHelper::getCountryByCode($data['country']);
        // 返还广告位
        // 只有默认排序才展示广告位
        if(
            (isset($data['seller_id']) && $data['seller_id'] != 0)
            || ($data['sortType'] != 0)
        )
        {
            $data['sponsored'] = [];
        } else {
            $data['sponsored'] = $this->getCurrentAdvertProductKey();
        }

        return $data;

    }

    public function getCurrentAdvertProductKey($page = 1, $limit = 60)
    {
        $exists = $this->orm->table('tb_advertisement_site_config')
            ->where([
                'status' => YesNoEnum::YES,
                'advertisement_site' => YesNoEnum::YES,
            ])
            ->exists();
        if (!$exists) {
            return [];
        }
        $sponsored = $this->getAdvertProductKey();

        $max = $page * $limit;
        $min = ($page - 1) * $limit;
        $ret = [];
        foreach ($sponsored as $key => $value) {
            if ($value > $min && $value <= $max) {
                $ret[] = $value - $min;
            }
        }
        return $ret;
    }
    /*
     * @deprecated 废弃 所以搜索走es接口调用
     * @param seller_id 店铺ID
     * @param search 关键词
     * @param category_id 类别
     * @param sub_category 子类别
     * @param min_price 价格区间最小值
     * @param max_price  价格区间最大值
     * @param min_quantity 库存区间最小值
     * @param max_quantity 库存区间最大值
     * @param download_status 0全部 1已下载 2未下载
     * @param wish_status 0全部 1已收藏订阅 2未收藏订阅
     * @param purchase_status 0全部 1购买过 2未购买
     * @param relation_status 0全部 1建立过 2未建立
     * @param img_status 0全部 1图片完整（大于等于7张）
     * @param sort 以此字段排序 'p.price', 'p.quantity', 'pas.quantity', 'p.date_added', 'return_rate'
     * @param order 正序ASC 逆序DESC
     * @param start
     * @param limit
     * @param country 国别
     * */
    public function searchProductId($filterData = [], $customerId = 0, $isPartner = 0, $limit = 0)
    {
        $notIn = $seller = $downloadList = [];
        if ($customerId) {
            if (!$isPartner) {//buyer
                $notIn = $this->unSeeProductId($customerId, $filterData['seller_id'] ?? 0);
                $seller = $this->mySellersIdList($customerId);
            }

            if (isset($filterData['wish_status']) && 2 == $filterData['wish_status']) {//筛选未收藏订阅的
                $wishList = $this->productIdWishList($customerId);
                $notIn = array_merge($notIn, $wishList);
            }
        }

        $in = $pIn = [];
        $complexTransactionsType = '';
        $complexTransactionsNo = 0;
        $this->load->model('tool/sphinx');
        if (isset($filterData['rebates']) && $filterData['rebates']) {
            $complexTransactionsType .= 'REBATE_';
        }

        if (isset($filterData['margin']) && $filterData['margin']) {
            $complexTransactionsType .= 'MARGIN_';
        }

        if (isset($filterData['futures']) && $filterData['futures']) {
            $complexTransactionsType .= 'FUTURE_';
        }
        if ($complexTransactionsType) {
            $complexTransactionsType = trim($complexTransactionsType, '_');
            $complexTransactionsNo = array_search($complexTransactionsType, ComplexTransactions::getViewItems()) ?? 0;
        }
        $whId = [];
        if (isset($filterData['whId']) && $filterData['whId']) {
            $whId = $filterData['whId'];
        }

        if (!empty($filterData['search'])) {
            // 需要判断是否有仓库和复杂交易
            $pIn = $this->model_tool_sphinx->getSearchProductId(trim($filterData['search']),
                [
                    'complexTransactionsNo' => $complexTransactionsNo,
                    'whId' => $whId,
                ]
            );

            if ($pIn) {
                $in = array_diff($pIn, $notIn);
            }
        } else {
            // 需要取到复杂交易里面的product_id
            $productList = [];
            $pComplex = $this->model_tool_sphinx->getComplexTransactionsProductId($complexTransactionsNo);
            $pWhId = $this->model_tool_sphinx->getInventoryProductId($whId);

            if ($pComplex) {
                $productList[] = $pComplex;
            }

            if ($pWhId) {
                $productList[] = $pWhId;
            }

            $pIn = $this->model_tool_sphinx->getIntersectArr($productList);
            if ($pIn) {
                $in = array_diff($pIn, $notIn);
            }

        }
        if ($customerId && isset($filterData['download_status']) && $filterData['download_status']) {
            $downloadList = $this->downloadProductIdList($customerId);
            if (1 == $filterData['download_status']) {
                $in = array_intersect($in, $downloadList);
            } elseif (2 == $filterData['download_status']) {
                $in = array_diff($in, $downloadList);
                $notIn = array_merge($notIn, $downloadList);
            }
        }
        if ($customerId && isset($filterData['purchase_status']) && $filterData['purchase_status']) {
            $purchaseList = $this->purchaseProductIdList($customerId);
            if (1 == $filterData['purchase_status']) {
                $in = array_intersect($in, $purchaseList);
            } elseif (2 == $filterData['purchase_status']) {
                $in = array_diff($in, $purchaseList);
                $notIn = array_merge($notIn, $purchaseList);
            }
        }
        $newProductIdArr = $this->newProductIdList();//新品
        if ($newProductIdArr && $notIn) {
            $newProductIdArr = array_diff($newProductIdArr, $notIn);
        }
        if ($newProductIdArr && $in) {
            $newProductIdArr = array_intersect($newProductIdArr, $in);
        }

        $select = [
            'p.product_id',
            'p.sku',
            'p.status',
            'p.buyer_flag',
            'case when p.quantity>0 then 0 else 1 end as qty_status',
            'case when c.customer_group_id in (17,18,19,20) then 1 else 0 end as group_status',//未发布seller排在后面
        ];
        if ($newProductIdArr) {
            $select[] = 'case when p.product_id in (' . implode(',', $newProductIdArr) . ') then 0 else 1 end as new_product';//新品排在前面
        }
        if ($seller && !$isPartner) {
            //$select[] = 'case when b2s.seller_id in ('.implode(',', $seller).') then 0 else 1 end as contact';
            $select[] = 'case when c2p.customer_id in (' . implode(',', $seller) . ') then 0 else 1 end as contact';
        } else {
            $select[] = '1 as contact';
        }
        if (isset($filterData['img_status']) && $filterData['img_status']) {
            $select[] = 'count(pi.product_package_image_id) as img_count';
        }
        if (isset($filterData['sort']) && 'return_rate' == $filterData['sort']) {
            $select[] = 'if(cron.return_rate, cron.return_rate, 0.00) as return_rate';
            //return rate 为N/A 的排在后面
            $select[] = 'case when cron.purchase_num > 10 then 0 else 1 end as rrp_sort';
        }

        if (!isset($filterData['start']) || $filterData['start'] < 0) {
            $filterData['start'] = 0;
        }
        if (!isset($filterData['limit']) || $filterData['limit'] < 1) {
            $filterData['limit'] = 20;
        }

        $categoryIdArr = [];
        if (!empty($filterData['category_id'])) {
            $categoryIdArr = $this->childrenCategories($filterData['category_id']);
            $query = $this->orm->table('oc_product_to_category as p2c')
                ->leftjoin('oc_product as p', 'p2c.product_id', 'p.product_id');
        } else {
            $query = $this->orm->table('oc_product as p');
        }

        $query = $query->leftjoin('oc_customerpartner_to_product as c2p', 'p.product_id', 'c2p.product_id')
            ->leftjoin('oc_customer as c', 'c.customer_id', 'c2p.customer_id');
        /*        if (!$isPartner){
                    $query = $query->leftjoin('oc_buyer_to_seller as b2s', 'b2s.seller_id', 'c2p.customer_id');
                }*/
        $query = $query->leftjoin('oc_product_description as pd', 'p.product_id', 'pd.product_id')
            ->leftjoin('oc_product_to_store as p2s', 'p.product_id', 'p2s.product_id')
            ->leftjoin('oc_country as cou', 'cou.country_id', 'c.country_id')
            ->leftjoin('oc_customerpartner_to_customer as c2c', 'c2c.customer_id', 'c2p.customer_id');

        if (isset($filterData['download_status']) && !in_array($filterData['download_status'], [0, 3])) {
            $query = $query->leftjoin('tb_sys_product_package_info as ppi', ['p.product_id' => 'ppi.product_id']);
        }

        if (isset($filterData['wish_status']) && 1 == $filterData['wish_status']) {//筛选已收藏订阅
            $query = $query->leftjoin('oc_customer_wishlist as cw', ['p.product_id' => 'cw.product_id']);
        }

        if (isset($filterData['purchase_status']) && !in_array($filterData['purchase_status'], [0, 3])) {//是否购买过
            $query = $query->leftjoin('oc_order_product as op', ['p.product_id' => 'op.product_id'])
                ->leftjoin('oc_order as o', ['o.order_id' => 'op.order_id']);
        }

        if (isset($filterData['img_status']) && $filterData['img_status']) {//商品图片完整
            $query = $query->leftjoin('oc_product_package_image as pi', ['p.product_id' => 'pi.product_id']);
        }

        if (isset($filterData['sort']) && ('pas.quantity' == $filterData['sort'] || 'return_rate' == $filterData['sort'])) {//销量参与排序
            $query = $query->leftjoin('tb_sys_product_all_sales as pas', ['p.product_id' => 'pas.product_id']);
        }

        if (isset($filterData['sort']) && 'return_rate' == $filterData['sort']) {//商品退返率参与排序
            $query = $query->leftjoin('oc_product_crontab as cron', ['cron.product_id' => 'p.product_id']);
        }

        $query = $query->select(DB::raw(implode(',', $select)))
            ->where([
                'p.status' => 1,//上架
                'p.is_deleted' => 0,//已删除的商品不能被搜索到
                'p.buyer_flag' => 1,//允许单独售卖的商品方可被搜索到
                'pd.language_id' => (int)$this->config->get('config_language_id'),
                'p2s.store_id' => (int)$this->config->get('config_store_id'),
                'c2c.show' => 1,//是否显示该Seller
                'c.status' => 1,//有效店铺的商品方可被搜索到
            ])
            // 只有普通商品能被搜索
            ->whereIn('p.product_type', [0])
            ->when($notIn && empty($in), function ($q) use ($notIn) {
                return $q->whereNotIn('p.product_id', $notIn);
            })
            ->when($customerId && isset($filterData['relation_status']), function ($q) use ($filterData, $customerId, $isPartner, $seller) {

                if ($isPartner) {//seller筛选是否建立联系的卖家商品
                    if (1 == $filterData['relation_status']) {
                        $q = $q->where('c2p.customer_id', $customerId);
                    } elseif (2 == $filterData['relation_status']) {
                        $q = $q->where('c2p.customer_id', '!=', $customerId);
                    }
                } else {//buyer筛选是否建立联系的卖家商品
                    if (1 == $filterData['relation_status']) {
                        $q = $q->whereIn('c2p.customer_id', $seller);
                    } elseif (2 == $filterData['relation_status']) {
                        $q = $q->whereNotIn('c2p.customer_id', $seller);
                    }
                }

                return $q;
            })
            //店铺主页搜索商品
            ->when(isset($filterData['seller_id']) && $filterData['seller_id'], function ($q) use ($filterData) {

                $q = $q->where('c2p.customer_id', $filterData['seller_id']);
                return $q;
            })
            ->where('p.date_available', '<=', date('Y-m-d H:i:s'))
            ->when(!empty($filterData['country']), function ($q) use ($filterData) {
                return $q->where('cou.iso_code_3', $filterData['country']);
            })
            ->when(!empty($filterData['country_id']), function ($q) use ($filterData) {
                return $q->where('cou.country_id', $filterData['country_id']);
            })
            ->when(!empty($categoryIdArr), function ($q) use ($categoryIdArr) {
                return $q->whereIn('p2c.category_id', $categoryIdArr);
            })
            ->when((!empty($filterData['search']) || $complexTransactionsType || $whId), function ($q) use ($in, $pIn, $filterData) {
                $filterData['search'] = escape_like_str($filterData['search']);
                if (empty($pIn)) {
                    return $q->where(function ($qq) use ($filterData) {

                        $qq = $qq->when($filterData['search'], function ($q1) use ($filterData) {

                            $words = explode(' ', trim(preg_replace('/\s+/', ' ', $filterData['search'])));
                            $q1->when($words, function ($q2) use ($words) {
                                foreach ($words as $word) {
                                    $q2 = $q2->where('pd.name', 'like', "%" . $this->db->escape($word) . "%");
                                }
                            });

                            return $q1;
                        });

                        $qq = $qq->when($filterData['search'], function ($q1) use ($filterData) {
                            $q1 = $q1->orWhere('p.sku', 'like', "%" . $this->db->escape(utf8_strtoupper($filterData['search'])) . "%")
                                ->orWhere('p.mpn', 'like', "%" . $this->db->escape(utf8_strtoupper($filterData['search'])) . "%")
                                ->orWhere('p.model', utf8_strtoupper($filterData['search']))
                                ->orWhere('p.upc', $filterData['search']);
                            return $q1;
                        });
                        return $qq;
                    });
                } else {
                    return $q->whereIn('p.product_id', $in);
                }
            })
            ->when($filterData['manufacturer_id'] ?? 0, function ($q) use ($filterData) {
                return $q = $q->where('p.manufacturer_id', (int)$filterData['manufacturer_id']);
            })
            ->when(isset($filterData['min_price']) && $filterData['min_price'] != '', function ($q) use ($filterData) {
                return $q = $q->where('p.price', '>=', sprintf('%.2f', $filterData['min_price']));
            })
            ->when(isset($filterData['max_price']) && $filterData['max_price'] != '', function ($q) use ($filterData) {
                return $q = $q->where('p.price', '<=', $filterData['max_price']);
            })
            ->when(isset($filterData['min_quantity']) && $filterData['min_quantity'] != '', function ($q) use ($filterData) {
                return $q = $q->where('c2p.quantity', '>=', $filterData['min_quantity']);
            })
            ->when(isset($filterData['max_quantity']) && $filterData['max_quantity'] != '', function ($q) use ($filterData) {
                return $q = $q->where('c2p.quantity', '<=', $filterData['max_quantity']);
            })
            ->when(isset($filterData['qty_status']) && $filterData['qty_status'] != '', function ($q) {
                return $q = $q->where('p.quantity', '>', 0);
            })
            ->when(isset($filterData['wish_status']) && 1 == $filterData['wish_status'], function ($q) use ($filterData, $customerId) {
                if (1 == $filterData['wish_status']) {//已收藏订阅
                    return $q = $q->where('cw.customer_id', $customerId);
                } else {
                    return $q;
                }
            })
            ->when(isset($filterData['download_status']) && (!in_array($filterData['download_status'], [0, 3])), function ($q) use ($filterData, $customerId) {
                if (1 == $filterData['download_status']) {//已下载
                    return $q = $q->where('ppi.customer_id', $customerId);
                } else {//筛选未下载
                    return $q = $q->where(function ($q1) use ($customerId) {
                        return $q1 = $q1->where('ppi.customer_id', null)
                            ->orWhere('ppi.customer_id', '!=', $customerId);
                    });
                }
            })
            ->when(isset($filterData['purchase_status']) && (!in_array($filterData['purchase_status'], [0, 3])), function ($q) use ($filterData, $customerId) {
                if (1 == $filterData['purchase_status']) {//购买过
                    return $q = $q->where('o.customer_id', $customerId);
                } else {//未购买过
                    return $q = $q->where(function ($q1) use ($customerId) {
                        return $q1 = $q1->where('o.customer_id', '!=', $customerId)
                            ->orWhere('o.customer_id', null);
                    });
                }
            })
            ->groupBy('p.product_id')
            ->when(isset($filterData['img_status']) && $filterData['img_status'], function ($q) use ($filterData) {
                return $q = $q->having('img_count', '>=', 7);
            })
            ->orderBy('group_status')//未发布seller排在后面
            ->orderBy('contact');

        $sortArr = ['p.price', 'p.quantity', 'pas.quantity', 'p.date_added', 'return_rate'];//价格、库存、销量、入库时间、退返品率
        if (isset($filterData['sort']) && in_array($filterData['sort'], $sortArr)) {
            if ($filterData['sort'] == 'return_rate') {
                $query->orderBy('rrp_sort');
            }
            $query->orderBy($filterData['sort'], $filterData['order']);
        }
        if ($newProductIdArr) {
            $query->orderBy('new_product');//新品排在前面,不考虑是否有库存
        }

        $query->orderBy('qty_status')//库存大于0的排在前面
        ->orderBy('p.part_flag')//非配件排在配件前面
        ->orderBy('p.product_id', 'desc');

        if ($limit) {//分页查询
            $query->offset($filterData['start'])
                ->limit($filterData['limit']);
        }
        $list = $query->get();
        $product_id_list = [];
        foreach ($list as $item) {
            $product_id_list[] = $item->product_id;
        }
        return [
            'total' => count($list),
            'product_id_list' => $product_id_list
        ];
    }


    /**
     * @param int $sellerId
     * @param bool $isValidProduct 是否有效的产品 判断产品的 status， buyer_flag, is_deleted
     * @param int $buyerId
     * @return array
     */
    public function sellerCategories($sellerId, $isValidProduct = false, $buyerId = 0)
    {
        if (!empty($buyerId)) {
            $noDisplayProductId = $this->cart->buyerNoDisplayProductIdsByBuyerIdAndSellerId($buyerId, $sellerId);
        }
        $categoryIdArrQuery = db('oc_customerpartner_to_product as c2p')
            ->leftJoin('oc_product_to_category as p2c', 'p2c.product_id', '=', 'c2p.product_id')
            ->where('c2p.customer_id', $sellerId)
            ->join(DB_PREFIX . 'product as op', 'c2p.product_id', '=', 'op.product_id');
        if (isset($noDisplayProductId) && !empty($noDisplayProductId)) {
            $categoryIdArrQuery->whereNotIn('c2p.product_id', $noDisplayProductId);
        }
        if ($isValidProduct) {
            $categoryIdArrQuery->where('op.buyer_flag', 1)
                ->where('op.is_deleted', 0)
                ->where('op.status', 1);
        }
        $categoryIdArr = $categoryIdArrQuery
            ->groupBy(['p2c.category_id'])
            ->pluck('p2c.category_id')
            ->toArray();

        $categoryArr = $this->orm->table('oc_category_path')
            ->whereIn('category_id', $categoryIdArr)
            ->get();
        $categoryArr = obj2array($categoryArr);
        $categoryIdArr = array_unique(array_merge(array_column($categoryArr, 'category_id'), array_column($categoryArr, 'path_id')));
        $categoryNameList = $this->getCategoryNameList($categoryIdArr);

        $category = [];
        $parentIdArr0 = [];
        foreach ($categoryArr as $k => $v) {
            if (0 == $v['level']) {
                if (!isset($category[$v['path_id']])) {
                    $category[$v['path_id']] = [
                        'category_id' => $v['path_id'],
                        'name' => $categoryNameList[$v['path_id']] ?? '',
                    ];
                }
                if ($v['category_id'] != $v['path_id']) {
                    $parentIdArr0[$v['category_id']] = $v['path_id'];
                }
            }
        }

        $parentIdArr1 = array_keys($parentIdArr0);
        foreach ($categoryArr as $k => $v) {
            if (in_array($v['category_id'], $parentIdArr1) && 1 == $v['level']) {
                $parentIdArr2[$v['category_id']] = $v['path_id'];
                if (!isset($category[$parentIdArr0[$v['category_id']]]['children'][$v['path_id']])) {
                    $category[$parentIdArr0[$v['category_id']]]['children'][$v['path_id']] = [
                        'category_id' => $v['path_id'],
                        'name' => $categoryNameList[$v['path_id']] ?? '',
                    ];
                }
                if ($v['category_id'] != $v['path_id']) {
                    $category[$parentIdArr0[$v['category_id']]]['children'][$v['path_id']]['children'][$v['category_id']] = [
                        'category_id' => $v['category_id'],
                        'name' => $categoryNameList[$v['category_id']] ?? '',
                    ];
                }
            }
        }

        return $category;
    }

    public function getHotWordsList()
    {
        return $this->orm->table('tb_search_setting')
            ->where([
                'status' => self::STATUS_ACTIVE,
                //'is_home_show' =>self::STATUS_ACTIVE,
                'country' => self::COUNTRY_ENG[$this->session->get('country')]
            ])
            ->orderBy('sort', 'asc')
            ->select()
            ->get();
    }

    public function searchFeedBackInsert($param)
    {
        return $this->orm->table('tb_customer_search_feedback')->insertGetId($param);
    }

    public function getCategoryNameList($categoryIdArr)
    {
        return $this->orm->table('oc_category_description')
            ->whereIn('category_id', $categoryIdArr)
            ->pluck('name', 'category_id')
            ->toArray();
    }
}
