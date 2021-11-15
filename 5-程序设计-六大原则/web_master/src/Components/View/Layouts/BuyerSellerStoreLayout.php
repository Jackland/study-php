<?php

namespace App\Components\View\Layouts;

use App\Catalog\Search\MarketingTimeLimit\SellerListOnSaleSearch;
use App\Catalog\Search\MarketingTimeLimit\SellerListWillSaleSearch;
use App\Enums\Common\YesNoEnum;
use App\Enums\Customer\CustomerAccountingType;
use App\Helper\CountryHelper;
use App\Models\Customer\Customer;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Marketing\MarketingDiscount;
use App\Models\Seller\SellerStore;
use App\Repositories\Buyer\BuyerToSellerRepository;
use App\Repositories\Marketing\MarketingDiscountRepository;
use App\Repositories\Seller\SellerRepository;
use App\Services\Marketing\MarketingTimeLimitDiscountService;
use Carbon\Carbon;
use Framework\Exception\Http\NotFoundException;
use Framework\Exception\InvalidConfigException;
use Illuminate\Database\Query\Expression;
use ModelCatalogSearch;
use ModelCustomerpartnerSellerCenterIndex;

class BuyerSellerStoreLayout implements LayoutInterface
{
    /**
     * @inheritDoc
     * @return array
     * @throws InvalidConfigException
     * @throws NotFoundException
     * @throws \Throwable
     */
    public function getParams(): array
    {
        return [
            'header' => load()->controller('common/header'),
            'top' => $this->renderTop(),
            'footer' => load()->controller('common/footer'),
        ];
    }

    /**
     * @return string
     * @throws InvalidConfigException
     * @throws NotFoundException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function renderTop()
    {
        $sellerId = request('id');
        $search = request('search');
        if (!$sellerId) {
            throw new InvalidConfigException('未知的 sellerID: ' . $sellerId);
        }
        $seller = CustomerPartnerToCustomer::query()->alias('c2c')
            ->leftJoinRelations(['customer as cus'])
            ->select('c2c.*')
            ->selectRaw(new Expression('TRIM(CONCAT(cus.firstname, cus.lastname)) AS store_code'))
            ->where('c2c.customer_id', $sellerId)
            ->first();
        if (!$seller) {
            throw new NotFoundException('未知的 sellerID: ' . $sellerId);
        }

        $currentRoute = request('route');
        $sellerStore = SellerStore::query()->where('seller_id', $sellerId)->first();
        $buyerId = (int)customer()->getId();
        $country = session('country', 'USA');
        $countryId = CountryHelper::getCountryByCode($country);
        //限时限量活动 Store Navigation Bar Menu
        $isStoreNavShow = app(SellerListOnSaleSearch::class, ['customerId' => $buyerId, 'countryId' => $countryId])->isStoreNavShow($sellerId);
        //店铺菜单限时限量活动标识变量
        $isMarketingTime = false;
        //店铺菜单限时限量活动即将开始
        $timeLimitNextId = false;
        $timeLimitNextId = app(SellerListWillSaleSearch::class, ['customerId' => $buyerId, 'countryId' => $countryId])->getNextPreHotId($sellerId);
        if ($isStoreNavShow) {
            $result = app(SellerListOnSaleSearch::class, ['customerId' => $buyerId, 'countryId' => $countryId])->searchES(['id' => $sellerId]);
            $isMarketingTime = (bool)$result['total'];
        }


        $menus = [];
        $menus[] = [
            'name' => 'Store Home',
            'url' => url(['seller_store/home', 'id' => $sellerId]),
            'active' => $currentRoute === 'seller_store/home',
            'visible' => view()->getShared('must_show_store_home_menu', false) || ($sellerStore && !!$sellerStore->store_home_json),
        ];
        $menus[] = [
            'name' => 'Products',
            'url' => url(['seller_store/products', 'id' => $sellerId]),
            'active' => $currentRoute === 'seller_store/products',
            'visible' => true,
        ];
        if ($isStoreNavShow) {
            if ($isMarketingTime) {
                $menus[] = [
                    'name' => 'Limited Sales Promotions',
                    'url' => url(['account/marketing_time_limit/sellerOnSale', 'id' => $sellerId]),
                    'active' => in_array($currentRoute, ['account/marketing_time_limit/sellerOnSale']),
                    'visible' => true,
                    'isMarketingTime' => 1,
                ];
            }
        }
        if ($timeLimitNextId) {
            $menus[] = [
                'name' => 'Next Deals',
                'url' => url(['account/marketing_time_limit/sellerWillSale', 'id' => $sellerId, 'timeLimitId' => $timeLimitNextId, 'time_token' => app(MarketingTimeLimitDiscountService::class)->generateToken($timeLimitNextId)]),
                'active' => in_array($currentRoute, ['account/marketing_time_limit/sellerWillSale']),
                'visible' => true,
            ];
        }
        $menus[] = [
            'name' => 'Store Profile',
            'url' => url(['seller_store/introduction', 'id' => $sellerId]),
            'active' => $currentRoute === 'seller_store/introduction',
            'visible' => view()->getShared('must_show_store_introduction_menu', false) || ($sellerStore && !!$sellerStore->store_introduction_json),
        ];


        $data = [
            'seller' => $seller,
            'search' => $search,
            'score' => 0,
            'comprehensive' => [],
            'return_info' => [],
            'has_store_home' => $sellerStore && !!$sellerStore->store_home_json,
            'needContactSeller' => false,
            'isMarketingTime' => (int)$isMarketingTime,
            'menus' => $menus,
        ];

        // 退换货等数据
        /** @var ModelCatalogSearch $modelCatalogSearch */
        $modelCatalogSearch = load()->model('catalog/search');
        $data['return_info'] = $modelCatalogSearch->getSellerRateInfo($sellerId);

        // 评分数据
        if (customer()->isLogged()) {
            /** @var ModelCustomerpartnerSellerCenterIndex $modelCustomerPartnerSellerCenterIndex */
            $modelCustomerPartnerSellerCenterIndex = load()->model('customerpartner/seller_center/index');
            $taskInfo = $modelCustomerPartnerSellerCenterIndex->getSellerNowScoreTaskNumberEffective($sellerId);
            if (!isset($taskInfo['performance_score'])) {
                // 无评分 且 在3个月内是外部新seller
                if (app(SellerRepository::class)->isOutNewSeller($sellerId, 3)) {
                    $data['score'] = 'New Seller';
                }
            } else {
                $score = number_format(round($taskInfo['performance_score'], 2), 2);
                $data['score'] = $score;
                $data['comprehensive'] = $modelCustomerPartnerSellerCenterIndex->comprehensiveSellerData($sellerId, CountryHelper::getCurrentId(), 1);
            }
        }

        // 是否需要建立联系
        $customer = customer();
        if (!$customer->isLogged()) {
            // 未登录需要
            $data['needContactSeller'] = true;
        } elseif ($customer->isPartner()) {
            // seller 不需要
            $data['needContactSeller'] = false;
        } else {
            // buyer 检查是否已建立联系，未建立联系的需要
            $data['needContactSeller'] = !app(BuyerToSellerRepository::class)->isConnected($sellerId, $customer->getId());
        }

        //大客户折扣
        $data['big_client_discount'] = 0;
        if (customer()->isLogged()) {
            if ($sellerId != customer()->getId()) {
                $data['big_client_discount'] = app(MarketingDiscountRepository::class)->getBuyerDiscountInfo(0, $sellerId, customer()->getId());
            } else {
                $currentTime = Carbon::now();
                $discount = MarketingDiscount::query()
                    ->where('is_del', YesNoEnum::NO)
                    ->where('seller_id', $sellerId)
                    ->where('effective_time', '<', $currentTime)
                    ->where('expiration_time', '>', $currentTime)
                    ->where('buyer_scope', -1)
                    ->min('discount');
                $data['big_client_discount'] = !$discount ? 0 : $discount;
            }
        }
        // 校验是不是giga onsite seller
        $data['is_giga_onsite'] = Customer::find($sellerId)->accounting_type == CustomerAccountingType::GIGA_ONSIDE;

        return view()->render('layouts/buyer_seller_store/top', $data);
    }
}
