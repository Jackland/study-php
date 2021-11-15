<?php

use App\Models\Buyer\Buyer;
use App\Repositories\Customer\CustomerTipRepository;
use App\Repositories\Message\StationLetterRepository;

/**
 * @property ModelNoticeNotice $model_notice_notice
 */
class ControllerCommonNotificationWindow extends Controller {
    /**
     * 首次登陆的通知弹窗
     * @return string
     * @throws Exception
     */
    public function index() {
        $this->load->model('account/guide');
        $country_id = $this->customer->getCountryId();
        $customerId = $this->customer->getId();
        $isPartner = $this->customer->isPartner();
        $identity = $isPartner ? '1' : '0';
        $data['can_show'] = false;
        if ($this->customer->isLogged() && get_value_or_default($_COOKIE,'login_flag','')==1) {
//            美国buyer的订单通知
//            if($country_id==223 && !$isPartner){
//                $buyerOrderData = $this->model_account_guide->getBuyerOrderData($this->customer->getId(), $country_id);
//                //验证是否 canshow
//                if ($buyerOrderData['count_order'] > 0
//                    || $buyerOrderData['ship_order_qty'] > 0
//                    || $buyerOrderData['unship_order_qty'] > 0
//                    || $buyerOrderData['order_amount'] > 0
//                ) {
//                    $data['buyerOrderData'] = [
//                        'count_order' => $buyerOrderData['count_order'],
//                        'ship_order_qty' => $buyerOrderData['ship_order_qty'],
//                        'unship_order_qty' => $buyerOrderData['unship_order_qty'],
//                        'order_amount' => $buyerOrderData['order_amount'],
//                    ];
//                    $data['can_show'] = true;
//                }
//            }
            //展示最新公告
            $this->load->model('notice/notice');
            //100297 更换显示逻辑
            $new_notice = $this->model_notice_notice->listLoginRemind($customerId, $country_id, $identity)->toArray();
            // 获取对应需要弹窗的通知
            $stationLetterRepo = app(StationLetterRepository::class);
            $letter = $stationLetterRepo->getPopUpLetters($this->customer->getId())->toArray();
            if ($letter) {
                $new_notice = array_merge($new_notice, $letter);
            }
            //是否有新公告
            if($new_notice){
                $data['can_show'] = true;
                $data['new_notice'] = $new_notice;
            }
        }
        if ($this->request->server['HTTPS']) {
            $server = $this->config->get('config_ssl');
        } else {
            $server = $this->config->get('config_url');
        }
        // 设置非谷歌浏览器提示图标
        $data['chrome_icon'] = $server . 'image/catalog/not-chrome-notice.png';
        $data['country'] = $this->customer->getCountryId()?:0;

        //#3152 卖家和买家登录首页 弹窗刷新
        $this_route =  $this->request->get('route','');
        $data['refresh_notification'] = false;
        if ($this_route){
            $buyer_seller_dashboard = ['account/buyer_central' , 'customerpartner/seller_center/index' ];
            if (in_array($this_route ,$buyer_seller_dashboard)){
                $data['refresh_notification'] = true ;
            }
        }

        return $this->load->view('common/notification_window', $data);
	}

}
