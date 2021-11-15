<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Catalog\Forms\CustomerPartner\Marketing\MarketingTimeLimitDiscountForm;
use App\Catalog\Forms\CustomerPartner\Marketing\MarketingTimeLimitStockForm;
use App\Catalog\Search\CustomerPartner\Marketing\MarketingTimeLimitDiscountSearch;
use App\Enums\Marketing\MarketingTimeLimitProductStatus;
use App\Enums\Marketing\MarketingTimeLimitStatus;
use App\Helper\CountryHelper;
use App\Helper\MoneyHelper;
use App\Logging\Logger;
use App\Models\Marketing\MarketingTimeLimit;
use App\Enums\Common\YesNoEnum;
use App\Models\Marketing\MarketingTimeLimitProduct;
use App\Repositories\Marketing\MarketingTimeLimitDiscountRepository;
use App\Repositories\Seller\SellerClientCustomerRepository;
use App\Services\Marketing\MarketingTimeLimitDiscountService;
use Carbon\Carbon;

class ControllerCustomerpartnerMarketingCampaignTimeLimitDiscount extends AuthSellerController
{
    public $customerId;
    public $transactionTypeList = [];
    public $precision;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->customerId = customer()->getId();
        $this->transactionTypeList = app(MarketingTimeLimitDiscountRepository::class)->getTransactionTypeList();
        $this->precision = customer()->isJapan() ? 0 : 2;
    }

    public function index()
    {
        $data = [];
        $search = new MarketingTimeLimitDiscountSearch(customer()->getId());
        $dataProvider = $search->search($this->request->query->all());
        $discountList = $dataProvider->getList();

        // 循环处理即可，因为正在进行中只有一个
        /** @var MarketingTimeLimit $item */
        foreach ($discountList as $item) {
            $item->setAttribute('can_update_stop', app(MarketingTimeLimitDiscountService::class)->calculateTimeLimitStatus($item) ? 1 : 0);
            $item->setAttribute('low_stock_num', 0);
            if ($item->effective_status == MarketingTimeLimitStatus::ACTIVE) {
                $lowStockNum = MarketingTimeLimitProduct::query()
                    ->where('status', MarketingTimeLimitProductStatus::NO_RELEASE)
                    ->where('head_id', $item->id)
                    ->where('qty', '<', $item->low_qty)
                    ->count();
                $item->setAttribute('low_stock_num', $lowStockNum);
            }
        }

        $data['list'] = $discountList;
        $data['total'] = $dataProvider->getTotalCount();
        $data['paginator'] = $dataProvider->getPaginator();
        $data['sort'] = $dataProvider->getSort();
        $data['search'] = $search->getSearchData();
        $data['show_create_entrance'] = $this->checkPermission() ? 1 : 0;
        $data['discount_status_list'] = MarketingTimeLimitStatus::getViewItems();

        return $this->render('customerpartner/marketing_campaign/discount/limited_sales_promotions', $data);
    }

    // 新增&编辑
    public function add()
    {
        if (!$this->checkPermission()) {
            return $this->sendErrorPage(url('customerpartner/marketing_campaign/discount_tab/index#limitedSalesPromtions'));
        }
        $data = [];
        $timeLimitId = (int)$this->request->get('time_limit_id', 0);
        if ($timeLimitId == 0) {
            $data['transaction_type_list'] = $this->transactionTypeList;
            $data['country_id'] = customer()->getCountryId();
        } else {
            $data = $this->getDiscountData($timeLimitId, 1);
            if (is_string($data) && !is_array($data)) {
                return $this->sendErrorPage($data);
            }
        }

        return $this->render('customerpartner/marketing_campaign/discount/components/limited_sales_promotions_add', $data, 'seller');
    }

    // 查看
    public function editView()
    {
        $timeLimitId = (int)$this->request->get('time_limit_id', 0);
        $baseData = $this->getDiscountData($timeLimitId, 2);
        if (is_string($baseData) && !is_array($baseData)) {
            return $this->sendErrorPage($baseData);
        }
        $data = $baseData;

        return $this->render('customerpartner/marketing_campaign/discount/components/limited_sales_promotions_view', $data, 'seller');
    }

    // 新增 && 编辑 post
    public function store(MarketingTimeLimitDiscountForm $timeLimitForm)
    {
        if (!$this->checkPermission()) {
            return $this->jsonFailed(__('暂无权限', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
        }
        $result = $timeLimitForm->save();

        return $result['code'] == 200 ? $this->jsonSuccess([], $result['msg']) : $this->jsonFailed($result['msg']);
    }

    // 摘自店铺装修(不复用店铺管理的)
    public function products()
    {
        $keywords = $this->request->post('keywords', '');
        $finalProducts = app(MarketingTimeLimitDiscountRepository::class)->getSearchProducts([], $keywords, $this->customerId);

        return $this->jsonSuccess(['products' => $finalProducts['products']]);
    }

    // 停止活动
    public function stop()
    {
        $timeLimitId = (int)$this->request->post('time_limit_id', 0);
        $timeLimitDetail = $this->getBaseInfo($timeLimitId);
        if (empty($timeLimitDetail)) {
            return $this->jsonFailed(__('操作失败，请重试', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
        }
        if (!app(MarketingTimeLimitDiscountService::class)->calculateTimeLimitStatus($timeLimitDetail)) {
            return $this->jsonFailed(__('操作失败，请重试', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
        }
        try {
            dbTransaction(function () use ($timeLimitId) {
                MarketingTimeLimit::query()
                    ->where('id', $timeLimitId)
                    ->update(['status' => MarketingTimeLimitStatus::STOPED]);
                MarketingTimeLimitProduct::query()
                    ->where('head_id', $timeLimitId)
                    ->update(['status' => MarketingTimeLimitProductStatus::RELEASED]);
            });
        } catch (\Throwable $e) {
            Logger::error($e->getMessage());
            return $this->jsonFailed(__('操作失败，请重试', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
        }

        return $this->jsonSuccess([], __('操作成功', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
    }

    // 补充活动库存
    public function incrDiscountStockQty(MarketingTimeLimitStockForm $timeLimitStockForm)
    {
        $result = $timeLimitStockForm->save();
        return $result['code'] == 200 ? $this->jsonSuccess([], $result['msg']) : $this->jsonFailed($result['msg'], [], $result['code']);
    }

    // 释放活动某个商品库存
    public function releaseDiscountStockQty()
    {
        if (!$this->checkPermission()) {
            return $this->jsonFailed(__('暂无权限', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
        }

        $timeLimitId = (int)$this->request->post('time_limit_id', 0);
        $productId = (int)$this->request->post('product_id', 0);

        $res = app(MarketingTimeLimitDiscountService::class)->releaseTimeLimitProductStockQty($timeLimitId, $productId);
        if ($res !== false) {
            return $this->jsonSuccess([], __('操作成功', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
        }
        return $this->jsonFailed(__('操作失败，请重试', [], 'catalog/view/customerpartner/marketing_campaign/time_limit_discount'));
    }

    // 404
    private function sendErrorPage(string $url)
    {
        $this->response->setStatusCode(404);
        $data['continue'] = $url;
        $data['text_error'] = $data['heading_title'] = 'The page you requested cannot be found!';

        return $this->render('error/not_found', $data, 'home');
    }

    // 通用数据
    private function getDiscountData(int $timeLimitId, $type = 1)
    {
        $timeLimitDiscount = $this->getBaseInfo($timeLimitId, 1);
        if (empty($timeLimitDiscount)) {
            return url('customerpartner/marketing_campaign/discount_tab/index#limitedSalesPromtions');
        }

        //转成对应国别时间，js里面有直接取值
        if ($type == 1) {
            $timeZone = CountryHelper::getTimezone(customer()->getCountryId());
            $timeLimitDiscount->effective_time = Carbon::parse($timeLimitDiscount->effective_time)->timezone($timeZone)->toDateTimeString();
            $timeLimitDiscount->expiration_time = Carbon::parse($timeLimitDiscount->expiration_time)->timezone($timeZone)->toDateTimeString();
        }

        $tToken = app(MarketingTimeLimitDiscountService::class)->generateToken($timeLimitId);
        $timeLimitDiscount->setAttribute('discount_link', url(['marketing_campaign/time_limit_activity', 'id' => $timeLimitId, 'time_token' => $tToken]));
        $productIds = $timeLimitDiscount->products->pluck('product_id')->toArray();
        if ($productIds) {
            $productsInfos = app(MarketingTimeLimitDiscountRepository::class)->getSearchProducts($productIds, '', $this->customerId);
            $keyProductsInfos = $productsInfos['key_products'];
            /** @var MarketingTimeLimitProduct $productLimit */
            foreach ($timeLimitDiscount->products as $productLimit) {
                $productLimit->setAttribute('image', $keyProductsInfos[$productLimit->product_id]['image'] ?? '');
                $productLimit->setAttribute('name', $keyProductsInfos[$productLimit->product_id]['name'] ?? '');
                $productLimit->setAttribute('sku', $keyProductsInfos[$productLimit->product_id]['sku'] ?? '');
                $productLimit->setAttribute('mpn', $keyProductsInfos[$productLimit->product_id]['mpn'] ?? '');
                $productLimit->setAttribute('tags', $keyProductsInfos[$productLimit->product_id]['tags'] ?? '');
                $productLimit->setAttribute('ninety_days_average_price', $keyProductsInfos[$productLimit->product_id]['ninety_days_average_price'] ?? '');
                $productLimit->setAttribute('currency', session('currency'));
                $productLimit->setAttribute('quantity', $keyProductsInfos[$productLimit->product_id]['qty'] ?? 0);
                $productLimit->setAttribute('freight', $keyProductsInfos[$productLimit->product_id]['freight'] ?? 0);
                $productLimit->setAttribute('unavaliable', $keyProductsInfos[$productLimit->product_id]['unavaliable'] ?? 0);
                // 这儿是商品的当前公开价
                $productLimit->setAttribute('price', $keyProductsInfos[$productLimit->product_id]['price'] ?? '');
                //计算折后价
                $productLimit->setAttribute('after_discount_price', MoneyHelper::upperAmount($productLimit->price * $productLimit->discount / 100, $this->precision));
                // 活动剩余库存  活动上架的-减去活动锁定的 ; 大军说订单生成了，活动失效停止啥的 还会走活动，这个地方只能所有的都用qty-lockedQty
                $productLimit->setAttribute('qty', max($productLimit->qty - $productLimit->lockedQty(), 0));

                if ($timeLimitDiscount->effective_status == MarketingTimeLimitStatus::ACTIVE) {
                    // 当前上架库存 - 剩余活动库存   此字段是可补充活动库存的最大值
                    $currentQty = max($productLimit->quantity - $productLimit->qty, 0);
                    $productLimit->setAttribute('quantity', $currentQty);
                }
            }
        }

        $data['discount_detail'] = $timeLimitDiscount;
        $data['country_id'] = customer()->getCountryId();
        $data['transaction_type_list'] = $this->transactionTypeList;

        return $data;
    }

    // 基础信息
    private function getBaseInfo(int $timeLimitId, int $needProduct = 0)
    {
        if (empty($timeLimitId)) {
            return false;
        }

        return MarketingTimeLimit::query()
            ->when($needProduct == 1, function ($q) {
                $q->with('products');
            })
            ->where('id', $timeLimitId)
            ->where('is_del', YesNoEnum::NO)
            ->where('seller_id', customer()->getId())
            ->first();
    }

    //权限
    private function checkPermission()
    {
        return app(SellerClientCustomerRepository::class)->checkPermission(customer()->getId());
    }

}
