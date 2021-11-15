<?php

use App\Models\ServiceAgreement\AgreementVersion;
use App\Repositories\Message\MessageRepository;
use App\Repositories\ServiceAgreement\ServiceAgreementRepository;
use Carbon\Carbon;

/**
 * Class ControllerCommonFooter
 * @property ModelToolOnline $model_tool_online
 */
class ControllerCommonFooter extends Controller
{
    const SESSION_KEY_MESSAGE_NOTICE_START_TIME = 'session_key_message_notice_start_time';

    public function index($data = [])
    {
        $is_show_notice = $data['is_show_notice'] ?? true;
        $is_show_message = $data['is_show_message'] ?? true;
        $this->load->language('common/footer');
        $data['logged'] = $this->customer->isLogged();
        $data['store_info'] = [
            'email' => $this->config->get('config_email'),
            'address' => $this->config->get('config_address'),
            'powered_by' => $this->config->get('config_powered_by'),
        ];

        // #35603 平台底部terms and conditions取值调整
        $information = app(ServiceAgreementRepository::class)
            ->getLastAgreementVersion(AgreementVersion::AGREEMENT_ID_BY_CUSTOMER_LOGIN, 0);
        $informationId = $information ? $information->information_id : 113;
        $data['terms_and_conditions_link'] = url(['information/information', 'information_id' => $informationId]);

        //region Whos Online
        if ($this->config->get('config_customer_online')) {
            $this->load->model('tool/online');

            $ip = request()->serverBag->get('REMOTE_ADDR', '');

            if (isset($this->request->server['HTTP_HOST']) && isset($this->request->server['REQUEST_URI'])) {
                $url = ($this->request->server['HTTPS'] ? 'https://' : 'http://') . $this->request->server['HTTP_HOST'] . $this->request->server['REQUEST_URI'];
            } else {
                $url = '';
            }

            $referer = request()->serverBag->get('HTTP_REFERER', '');

            $this->model_tool_online->addOnline($ip, $this->customer->getId(), $url, $referer);
        }
        //endregion

        $data['scripts'] = $this->document->getScripts('footer');

        if ($is_show_notice) {
            $data['notification_window'] = $this->load->controller('common/notification_window');
        }
        // 站内信的弹窗通知
        if ($is_show_message) {
            $data['message_window'] = $this->load->controller('common/message_window');
        }

        // 悬浮图标的显示隐藏控制
        $route = request('route', 'common/home');
        // 是否显示运费
        $data['is_show_tracking_shipment'] = false;
        if (in_array($route, ['account/buyer_central'])) {
            if ($this->customer->getCountryId() == AMERICAN_COUNTRY_ID && !$this->customer->isCollectionFromDomicile()) {
                $data['is_show_tracking_shipment'] = true;
            }
        }

        // 判断是否展示右边栏目
        $hideRightSuspension = [
            'account/phone/verify', // 手机号验证页面
            'account/service_agreement', // 服务协议页面
            'account/service_agreement/index', // 服务协议页面
        ];
        $data['is_show_right_suspension'] = true;
        if (in_array($route, $hideRightSuspension)) {
            $data['is_show_right_suspension'] = false;
        }

        // 判断是否展示customer_service窗口
        $hideCustomerService = [
            'product/product',
            'seller_store/home',
            'seller_store/introduction',
            'seller_store/products',
            'customerpartner/profile',
        ];
        $data['is_show_customer_service_window'] = true;
        if (in_array($route, $hideCustomerService)) {
            $data['is_show_customer_service_window'] = false;
        }

        if ($data['logged']) {
            $data['is_seller'] = $this->customer->isPartner();
            $data['send_page'] = '';
            // 页面不是buyer的ticket页面时需要展示
            if (!in_array($route, ['account/message_center/ticket','account/message_center/ticket/index', 'account/message_center/ticket/view'])) {
                $data['send_page'] = $this->load->controller('common/ticket/sendPage');
            }
            $data['latest_msg'] = $this->getLatestMessageByCustomer();
        }

        //endregion
        return $this->load->view('common/footer', $data);
    }

    /**
     * @return array
     */
    private function getLatestMessageByCustomer(): array
    {
        if (!customer()->isLogged()) {
            return [];
        }
        $startTime = session(self::SESSION_KEY_MESSAGE_NOTICE_START_TIME, '');
        $lastHourTime = Carbon::now()->subHour()->toDateTimeString();

        if (empty($startTime) || $startTime < $lastHourTime) {
            $startTime = $lastHourTime;
        }

        $msg = app(MessageRepository::class)->getLatestMessageByCustomer(customer()->getModel(), $startTime);
        if ($msg) {
            session()->set(self::SESSION_KEY_MESSAGE_NOTICE_START_TIME, $msg['create_time']);
        }

        return $msg;
    }

}
