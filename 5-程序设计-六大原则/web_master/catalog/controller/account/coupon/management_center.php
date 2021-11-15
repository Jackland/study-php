<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Helper\CountryHelper;
use App\Services\Marketing\CouponCenterService;
use App\Enums\Marketing\CouponTemplateAuditStatus;
use App\Enums\Marketing\CouponTemplateStatus;
use App\Models\Marketing\Coupon;
use App\Enums\Marketing\CouponTemplateBuyerScope;
use App\Models\Order\Order;
use App\Enums\Order\OcOrderStatus;
use App\Enums\Marketing\CouponTemplateType;
use App\Enums\Marketing\CouponStatus;
use App\Repositories\Marketing\CouponCenterRepository;
use App\Models\Marketing\CouponTemplate;
use Carbon\Carbon;
use App\Components\Locker;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Buyer优惠券类
 * Class ControllerAccountCouponMyCoupon
 */
class ControllerAccountCouponManagementCenter extends AuthBuyerController
{
    protected $couponCenterService; // CouponCenterService
    private $countryId;
    private $customerId;

    public function __construct(Registry $registry, CouponCenterService $couponCenterService)
    {
        parent::__construct($registry);
        $this->countryId = $this->customer->getCountryId();
        $this->customerId = $this->customer->getId();

        $this->load->language('account/coupon/coupon_center');

        $this->couponCenterService = $couponCenterService;
    }

    /**
     * 我的优惠券中心
     *
     * @return string
     */
    public function index()
    {
        //设置title
        $this->document->setTitle($this->language->get('text_my_coupon_heading_title'));
        $data['breadcrumbs'] = [
            [
                'href' => $this->url->to(['common/home']),
                'text' => $this->language->get('text_home')
            ],
            [
                'href' => 'javascription:void(0)',
                'text' => $this->language->get('text_my_coupon_link_title')

            ]
        ];

        $CouponCenterRepo = app(CouponCenterRepository::class);
        $data['available_num'] = $CouponCenterRepo->getMyCouponNumByStatus($this->customerId, CouponStatus::UNUSED);
        $data['used_num'] = $CouponCenterRepo->getMyCouponNumByStatus($this->customerId, CouponStatus::USED);
        $data['expired_num'] = $CouponCenterRepo->getMyCouponNumByStatus($this->customerId, CouponStatus::INVALID);
        $data['tab'] = $this->request->get('tab', 1);

        return $this->render('account/coupon/my_coupon_index', $data, [
            'footer' => 'common/footer',
            'header' => 'common/header',
            'content_top' => 'common/content_top',
            'content_bottom' => 'common/content_bottom'
        ]);
    }

    /**
     * 获取我的优惠券列表
     *
     * @return string
     */
    public function getMyCouponList()
    {
        $validation = $this->request->validate([
            'page' => 'int|min:1',
            'page_limit' => 'int|min:1',
            'status' => 'required|int|in:' . CouponStatus::UNUSED . ',' . CouponStatus::USED . ',' . CouponStatus::INVALID
        ]);
        if ($validation->fails()) {
            return $this->render('account/coupon/my_coupon_temp');
        }

        $status = $this->request->get('status');
        $data['page'] = $this->request->get('page', 1);
        $data['page_limit'] = $this->request->get('page_limit', 20);

        $couponCenterRepo = app(CouponCenterRepository::class);
        $data['coupon_list'] = $couponCenterRepo->getMyCouponList($this->customerId, $status, $data['page'], $data['page_limit']);

        foreach ($data['coupon_list'] as $item) {
            $item->setAttribute('effective_time_format', $item->effective_time->setTimezone(CountryHelper::getTimezone($this->countryId))->format('Y/m/d H:i:s'));
            $item->setAttribute('expiration_time_format', $item->expiration_time->setTimezone(CountryHelper::getTimezone($this->countryId))->format('Y/m/d H:i:s'));
            $item->order_amount = ceil($item->order_amount) == $item->order_amount ? (int)$item->order_amount : number_format($item->order_amount, 2, '.', ',');
            $item->setAttribute('denomination_int', (int)$item->denomination);
            $item->setAttribute('denomination_deci', ceil($item->denomination) == $item->denomination ? '' : substr($item->denomination, -2));
        }

        // 分页信息
        $data['country_monetary_unit'] = $this->currency->getSymbolLeft($this->session->get('currency')) ?: $this->currency->getSymbolRight($this->session->get('currency'));
        $data['total'] = $couponCenterRepo->getMyCouponNumByStatus($this->customerId, $status);
        $data['total_pages'] = ceil($data['total']/$data['page_limit']);

        $data['coupon_status'] = $status;
        return $this->render('account/coupon/my_coupon_temp', $data);
    }

    /**
     * 领取优惠券
     *
     * @return JsonResponse
     */
    public function drawCoupon()
    {
        $couponIdValidation = $this->request->validate(['couponId' => 'required|int|min:1']);
        if ($couponIdValidation->fails()) {
            return $this->json(['msg' => $this->language->get('text_request_error'), 'status' => 0]);
        }

        $couponId = $this->request->post('couponId');
        $lock = Locker::couponDraw($couponId .'_'.$this->customerId, 5);
        if (!$lock->acquire()) {
            // 提示未获取到锁
            return $this->json(['msg' => $this->language->get('text_request_frequently'), 'status' => 0]);
        }

        $msg = '';
        $continueDrawStatus = 1; // 可以继续领取
        try {
            // 对优惠券的有效性判断，分别给予不同提示
            $couponTemplateInfo = CouponTemplate::find($couponId);
            $nowDate = date('Y-m-d H:i:s');
            // 非法请求
            if (!$couponTemplateInfo || $couponTemplateInfo->audit_status != CouponTemplateAuditStatus::PASS || $couponTemplateInfo->status != CouponTemplateStatus::START || $couponTemplateInfo->type != CouponTemplateType::BUYER_DRAW || $couponTemplateInfo->country_id != $this->countryId) {
                return $this->json(['msg' => $this->language->get('text_request_error'), 'status' => 0]);
            }
            // 优惠券已经无效
            if ($couponTemplateInfo->grant_start_time > $nowDate || $couponTemplateInfo->grant_end_time < $nowDate) {
                return $this->json(['msg' => $this->language->get('text_coupon_invalid_message'), 'status' => 2]); // 返回status-->2 优惠券发放完毕 用于前端对应修改样式
            }
            // 达到该优惠券领取上限
            $buyerCouponNum = Coupon::where('customer_id', $this->customerId)->where('coupon_template_id', $couponId)->count();
            if ($buyerCouponNum >= $couponTemplateInfo->per_limit) {
                return $this->json(['msg' => $this->language->get('text_already_received_message'), 'status' => 3]); // 优惠券领取达到上限
            }
            // 优惠券已经发放完毕
            if ($couponTemplateInfo->qty < 1 || $couponTemplateInfo->remain_qty < 1) {
                return $this->json(['msg' => $this->language->get('text_coupon_redemption_limit_message'), 'status' => 2]);
            }
            // 判断该优惠券是否有新老用户限制 -- 是否具有状态Completed的订单
            if ($couponTemplateInfo->buyer_scope != CouponTemplateBuyerScope::ALL_BUYER) {
                $orderStatus = Order::where('customer_id', $this->customerId)->where('order_status_id', OcOrderStatus::COMPLETED)->first();
                // 限制新用户才可以领取
                if ($couponTemplateInfo->buyer_scope == CouponTemplateBuyerScope::ONLY_NEW_BUYER) {
                    if ($orderStatus) {
                        return $this->json(['msg' => $this->language->get('text_get_failure_not_new_user_message'), 'status' => 0]);
                    }
                } else {
                    // 限制老用户才可以领取
                    if (!$orderStatus) {
                        return $this->json(['msg' => $this->language->get('text_get_failure_not_old_user_message'), 'status' => 0]);
                    }
                }
            }

            $result = $this->couponCenterService->drawCoupon($couponId, $this->customerId, $couponTemplateInfo);

            if (!$result) {
                return $this->json(['msg' => $this->language->get('text_coupon_invalid_message'), 'status' => 0]);
            }
            $coupon = Coupon::find($result);
            $invalidTimeStr = Carbon::now(date_default_timezone_get())->setTimeFromTimeString($coupon->expiration_time)->setTimezone(CountryHelper::getTimezone($this->countryId))->format('M d,Y');
            // 领取成功提示
            $amount_format = ceil($couponTemplateInfo->denomination) == $couponTemplateInfo->denomination ? (int)$couponTemplateInfo->denomination : $couponTemplateInfo->denomination;
            $country_monetary_unit = $this->currency->getSymbolLeft($this->session->get('currency')) ?: $this->currency->getSymbolRight($this->session->get('currency'));
            $msg = sprintf($this->language->get('text_get_success_message'), $country_monetary_unit . $amount_format, $invalidTimeStr);
            // 领取数量判断
            if ($buyerCouponNum + 1 >= $couponTemplateInfo->per_limit) {
                $continueDrawStatus = 4; // 领取达到上限
            }
        } finally {
            $lock->release(); // 释放锁
        }

        return $this->json(['msg' => $msg, 'status' => $continueDrawStatus]);
    }


}