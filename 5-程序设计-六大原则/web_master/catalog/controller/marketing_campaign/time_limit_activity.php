<?php

use App\Catalog\Controllers\BaseController;
use App\Enums\Marketing\MarketingTimeLimitStatus;
use App\Models\Marketing\MarketingTimeLimit;
use App\Services\Marketing\MarketingTimeLimitDiscountService;
use Carbon\Carbon;

class ControllerMarketingCampaignTimeLimitActivity extends BaseController
{

    function __construct(Registry $registry)
    {
        parent::__construct($registry);
    }

    // 限时活动  buyer && seller
    public function index()
    {
        $timeLimitId = $this->request->get('id', 0);
        $token = $this->request->get('time_token', '');
        $checkToken = app(MarketingTimeLimitDiscountService::class)->generateToken($timeLimitId);
        $this->load->language('marketing_campaign/activity');

        $timeLimitDiscount = MarketingTimeLimit::query()->find($timeLimitId);

        $data = $this->initPage();
        $data['continue'] = $this->url->link('common/home');

        $this->response->setStatusCode(404);

        if (empty($timeLimitDiscount) || $token != $checkToken) {
            $data['text_error'] = '<h1>The page you requested cannot be found!</h1>';
            return $this->response->setOutput($this->load->view('error/not_found', $data));
        }

        if ($timeLimitDiscount->status == MarketingTimeLimitStatus::STOPED) {
            $data['text_error'] = $this->language->get('text_error');
            return $this->response->setOutput($this->load->view('error/not_found', $data));
        }
        $currentTime = Carbon::now()->toDateTimeString();
        $redirectActiveUrl = url(['account/marketing_time_limit/sellerOnSale', 'id' => $timeLimitDiscount->seller_id, 'timeLimitId'=>$timeLimitId, 'time_token'=>$token]);
        $redirectPreHotUrl = url(['account/marketing_time_limit/sellerWillSale', 'id' => $timeLimitDiscount->seller_id, 'timeLimitId'=>$timeLimitId, 'time_token'=>$token]);

        // 0 过期&非预热未开启&预热没到时间  1：正在进行  2：预热待开启
        $status = 0;
        if ($timeLimitDiscount->effective_time > $currentTime) {
            if ($timeLimitDiscount->pre_hot == 1) {
                //预热期  开始时间往前推24H
                $preHotBeginTime = $timeLimitDiscount->effective_time->subHours(24)->toDateTimeString();
                if ($preHotBeginTime <= $currentTime) {
                    $status = 2;
                }
            }
        } elseif ($timeLimitDiscount->effective_time <= $currentTime && $timeLimitDiscount->expiration_time >= $currentTime) {
            $status = 1;
        }

        if ($status == 0) {
            $data['text_error'] = $this->language->get('text_error');
            return $this->response->setOutput($this->load->view('error/not_found', $data));
        }

        $response = response();

        $url = $status == 1 ? $redirectActiveUrl : $redirectPreHotUrl;

        return $response->redirectTo($url);

    }

    public function initPage()
    {
        $data = [
            'column_left' => $this->load->controller('common/column_left'),
            'column_right' => $this->load->controller('common/column_right'),
            'content_top' => $this->load->controller('common/content_top'),
            'content_bottom' => $this->load->controller('common/content_bottom'),
            'footer' => $this->load->controller('common/footer'),
            'header' => $this->load->controller('common/header'),
            'heading_title' => '',
        ];

        return $data;
    }

}
