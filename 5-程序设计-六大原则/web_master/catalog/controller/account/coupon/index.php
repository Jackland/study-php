<?php

use App\Catalog\Controllers\BaseController;
use App\Helper\CountryHelper;
use App\Repositories\Marketing\CouponCenterRepository;
use App\Enums\Marketing\CouponTemplateBuyerScope;
use Carbon\Carbon;

/**
 * 优惠券中心
 * Class ControllerAccountCouponIndex
 */
class ControllerAccountCouponIndex extends BaseController
{
    private $countryId;
    private $customerId;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->countryId = $this->customer->getCountryId() ?: CountryHelper::getCountryByCode($this->session->get('country'));
        $this->customerId = $this->customer->getId() ?: 0;

        /**
         * 过滤Seller身份 -- 只允许未登入、或者已登录身份为Buyer的用户访问
         */
        if ($this->customerId) {
            $isSeller = $this->customer->isPartner();
            if ($isSeller) {
                $this->redirect(['common/home'])->send();
            }
        }

        $this->load->language('account/coupon/coupon_center');
    }

    /**
     * 优惠券中心 -- 空页
     *
     * @return string
     */
    public function index()
    {
        $this->document->setTitle($this->language->get('text_heading_title'));

        $data['breadcrumbs'] = [
            [
                'href' => $this->url->to('common/home'),
                'text' => $this->language->get('text_home')
            ],
        ];

        $data['is_login'] = $this->customer->isLogged();
        return $this->render('account/coupon/index', $data, [
            'footer' => 'common/footer',
            'header' => 'common/header',
            'content_top' => 'common/content_top',
            'content_bottom' => 'common/content_bottom'
        ]);
    }

    /**
     * 获取优惠券列表
     *
     * @return string
     */
    public function getCouponList()
    {
        $validation = $this->request->validate(['page' => 'int|min:1', 'page_limit' => 'int|min:1']);
        if ($validation->fails()) {
            return $this->render('account/coupon/coupon_temp');
        }

        $page = $this->request->post('page', 1);
        $pageSize = $this->request->post('pageSize', 20);
        $couponCenterRepo = app(CouponCenterRepository::class);
        $couponList = $couponCenterRepo->getValidCouponTemplateList($this->countryId, $this->customerId, $page, $pageSize);

        // 进行优惠券过期时间处理
        foreach ($couponList as $item) {
            // 如果当前优惠券的过期时间是 天（多少天内有效） 则转换为对应过期时间点
            if ($item->expiration_days) {
                $item->setAttribute('effective_time_format', Carbon::now(CountryHelper::getTimezone($this->countryId))->format('Y/m/d 00:00:00'));
                $item->setAttribute('expiration_time_format', Carbon::now(CountryHelper::getTimezone($this->countryId))->addDay($item->expiration_days - 1)->format('Y/m/d 23:59:59')); // 取用户所在国别时间-到天
            } else {
                $item->setAttribute('effective_time_format', $item->effective_time->setTimezone(CountryHelper::getTimezone($this->countryId))->format('Y/m/d H:i:s'));
                $item->setAttribute('expiration_time_format', $item->expiration_time->setTimezone(CountryHelper::getTimezone($this->countryId))->format('Y/m/d H:i:s'));
            }

            $item->buyer_scope = CouponTemplateBuyerScope::getViewItemByBuyerScope($item->buyer_scope);
            $item->order_amount = ceil($item->order_amount) == $item->order_amount ? (int)$item->order_amount : number_format($item->order_amount, 2, '.', ',');
            $item->setAttribute('country_monetary_unit', $this->currency->getSymbolLeft($this->session->get('currency')) ?: $this->currency->getSymbolRight($this->session->get('currency')));
            $item->setAttribute('denomination_int', (int)$item->denomination);
            $item->setAttribute('denomination_deci', ceil($item->denomination) == $item->denomination ? '' : substr($item->denomination, -2));
        }

        $result['list'] = $couponList;

        return $this->render('account/coupon/coupon_temp', $result);
    }

}
