<?php

class ControllerAccountCustomerpartnerFooter extends Controller
{
    public function index()
    {
        $data['notification_window'] = $this->load->controller('common/notification_window');
        // 站内信的弹窗通知
        $data['message_window'] = $this->load->controller('common/message_window');

        // 右侧悬浮按钮控制
        $route = request('route', 'common/home');
        // 是否显示联系客服
        $data['is_show_customer_service'] = false;
        if (in_array($route, ['account/customerpartner/rma_management'])) {
            $data['is_show_customer_service'] = true;
        }

        return $this->load->view('account/customerpartner/footer', $data);
    }
}
