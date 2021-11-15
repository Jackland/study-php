<?php

namespace App\Repositories\Seller\SellerInfo;

use App\Models\Customer\Customer;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Repositories\Seller\SellerRepository;
use ModelCustomerpartnerSellerCenterIndex;

class BaseInfo
{
    // 外部不可调用整个对象，如果需要，请定义单独的属性字段供外部直接获取属性
    private $customer;
    private $seller;

    // 外部可访问的属性和方法定义成 public
    /**
     * customer_id
     * @var int
     */
    public $id;
    /**
     * 店铺名
     * @var string
     */
    public $store_name;
    /**
     * 店铺 code
     * @var string
     */
    public $store_code;
    /**
     * seller 名
     * @var string
     */
    public $customer_name;
    /**
     * 国家
     * @var int
     */
    public $country_id;

    public function __construct(Customer $customer, CustomerPartnerToCustomer $seller)
    {
        $this->customer = $customer;
        $this->seller = $seller;

        $this->id = $this->customer->customer_id;
        $this->store_name = $this->seller->screenname;
        $this->store_code = $this->customer->full_name;
        $this->customer_name = $this->store_code;
        $this->country_id = $this->customer->country_id;
    }

    /**
     * 店铺 logo
     * @param int|null $width
     * @param int|null $height
     * @return string
     */
    public function getStoreLogo(?int $width = null, ?int $height = null): string
    {
        return $this->seller->imageSolve(['w' => $width, 'h' => $height])->avatar_show;
    }

    /**
     * 获取评分信息
     * @param array $config
     * @return array{score: string, comprehensive: array}
     */
    public function getGiGaIndexInfo(array $config = []): array
    {
        $config = array_merge([
            'default_score' => 0, // 默认分值
            'check_is_new_outer_seller' => true, // 检查是否是新外部 seller，
            'new_outer_seller_month' => 3, // 新外部 seller 的定义，几个月内
            'new_outer_seller_show' => 'New Seller', // 新外部 seller 的显示
            'comprehensive' => true, // 完整的信息
        ], $config);
        $data = [
            'score' => $config['default_score'],
            'comprehensive' => [],
        ];

        if ($score = $this->seller->valid_performance_score) {
            $data['score'] = number_format(round($score, 2), 2);
        }
        $isNewOuterSeller = false;
        if (!$data['score'] && $config['check_is_new_outer_seller']) {
            if (app(SellerRepository::class)->isOutNewSeller($this->customer->customer_id, $config['new_outer_seller_month'])) {
                // 无评分 且 在3个月内是外部新seller
                $isNewOuterSeller = true;
                $data['score'] = $config['new_outer_seller_show'];
            }
        }
        if ($config['comprehensive'] && !$isNewOuterSeller) {
            /** @var ModelCustomerpartnerSellerCenterIndex $modelCustomerPartnerSellerCenterIndex */
            $modelCustomerPartnerSellerCenterIndex = load()->model('customerpartner/seller_center/index');
            $data['comprehensive'] = array_values($modelCustomerPartnerSellerCenterIndex->comprehensiveSellerData($this->customer->customer_id, $this->customer->country_id, 1));
        }

        return $data;
    }

    /**
     * 获取评分的分值
     * @return string
     */
    public function getGiGaIndexScore(): string
    {
        $info = $this->getGiGaIndexInfo([
            'comprehensive' => false
        ]);
        return $info['score'];
    }

    /**
     * 获取退返品信息
     * @return array{return_rate: array, return_approval_rate: string, response_rate: string}
     */
    public function getSellerPerformance(): array
    {
        return [
            'return_rate' => [
                'value' => $this->seller->returns_rate,
                'value_show' => $this->seller->return_rate_str,
                'na_tip' => 'The volume of total units sold is not enough for judging return rate.'
            ],
            'return_approval_rate' => [
                'value' => $this->seller->return_approval_rate,
                'value_show' => $this->seller->return_approval_rate_str,
                'na_tip' => 'No RMA records.'
            ],
            'response_rate' => [
                'value' => $this->seller->response_rate,
                'value_show' => $this->seller->response_rate_str,
                'na_tip' => ''
            ],
        ];
    }

    /**
     * 获取B2B店铺首页地址
     * @return string
     */
    public function getB2BUrl(): string
    {
        return url(['seller_store/products', 'id' => $this->id]);
    }
}
