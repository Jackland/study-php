<?php

use App\Enums\Message\MsgReceiveType;
use App\Models\Message\Msg;

/**
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelToolImage $model_tool_image
 * @property ModelAccountCustomerpartnerColumnLeft $model_account_customerpartner_column_left
 *
 * Class ControllerAccountCustomerpartnerColumnLeft
 *
 * @property ModelNoticeNotice $model_notice_notice
 * @property ModelStationLetterStationLetter $model_station_letter_station_letter
 */
class ControllerAccountCustomerpartnerColumnLeft extends Controller
{
    public function index()
    {
        $data['firstname'] = '';
        $data['lastname'] = '';
        $data['screenname'] = '';
        $data['image'] = '';
        $data['default_logo_url'] = '/image/catalog/Logo/yzc_logo_45x45.png';

        if ($this->config->get('module_marketplace_status') && $this->config->get('marketplace_allowed_account_menu')) {
            $this->load->model('account/customerpartner');
            $route = $this->request->request['route'];
            // message center栏目的其他页面
            $messageCenterRoutes = [
                'customerpartner/message_center/my_message/system',
                'customerpartner/message_center/my_message/notice',
                'customerpartner/message_center/my_message/trash',
                'customerpartner/message_center/my_message/setting',
                'customerpartner/message_center/notice/detail',
            ];
            if (in_array($route, $messageCenterRoutes)) {
                $route = 'customerpartner/message_center/my_message/buyers';
            }

            if ($route == 'customerpartner/message_center/message/detail') {
                $route = 'customerpartner/message_center/my_message/buyers';
                $requestFrom = request('from', '');
                if ($requestFrom == 'giga') {
                    $route = 'customerpartner/message_center/platform_secretary';
                }
                if (($msgId = request('msg_id', '')) && empty($requestFrom)) {
                    /** @var Msg $message */
                    $message = Msg::queryRead()->find($msgId);
                    if ($message->sender_id == -1 || ($message->sender_id > 0 && $message->receive_type == MsgReceiveType::PLATFORM_SECRETARY)) {
                        $route = 'customerpartner/message_center/platform_secretary';
                    }
                }
            }

            // 导入商品相关
            $importProductRouteArr = [
                'account/customerpartner/product_management/product/importProducts',
                'account/customerpartner/product_management/product/importProductsRecords'
            ];
            if (in_array($route, $importProductRouteArr)) {
                $route = 'customerpartner/product/lists/index';
            }


            // 店铺活动页面
            $storeRoutes = [
                'customerpartner/marketing_campaign/time_limit_discount/add',
                'customerpartner/marketing_campaign/time_limit_discount/editView',
            ];
            if (in_array($route, $storeRoutes)) {
                $route = 'customerpartner/marketing_campaign/discount_tab/index';
            }

            $menu = $this->url->link($route, '', true);
            $data['menuLink'] = $menu;

            $sellerProfile = $this->model_account_customerpartner->getProfile();
            if (isset($sellerProfile['firstname']) && $sellerProfile['firstname']) {
                $data['firstname'] = $sellerProfile['firstname'];
            }
            if (isset($sellerProfile['lastname']) && $sellerProfile['lastname']) {
                $data['lastname'] = $sellerProfile['lastname'];
            }
            if (isset($sellerProfile['screenname']) && $sellerProfile['screenname']) {
                $data['screenname'] = $sellerProfile['screenname'];
            }
            $this->load->model('tool/image');
            if (isset($sellerProfile['avatar']) && $sellerProfile['avatar']) {
                $data['image'] = $this->model_tool_image->resize($sellerProfile['avatar'], 45, 45);
            } else {
                $data['image'] = '';
            }

            $data['seller_center_link']=$this->url->link('customerpartner/seller_center/index','',true);
        }

        $this->load->model('account/customerpartner/column_left');
        $data['menus'] = $this->model_account_customerpartner_column_left->menus();


        return $this->load->view('account/customerpartner/column_left', $data);
    }
}
