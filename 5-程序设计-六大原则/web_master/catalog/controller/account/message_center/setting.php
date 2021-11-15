<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Repositories\Setting\MessageSettingRepository;

/**
 * 消息设置
 *
 * Class ControllerAccountMessageCenterSetting
 * @property ModelMessageMessageSetting $model_message_messageSetting
 */
class ControllerAccountMessageCenterSetting extends AuthBuyerController
{
    private $customerId;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->customerId = $this->customer->getId();
        $this->load->language('account/message_center/setting');
    }

    public function index()
    {
        $messageSettingRepo = app(MessageSettingRepository::class);
        $messageSetting = $messageSettingRepo->getByCustomerId($this->customer->getId(), [
            'setting' => 'setting_formatted',
            'email_setting' => 'email_setting_formatted',
            'other_email' => 'other_email_formatted',
        ]);
        $data['messageSetting'] = $messageSetting;
        $data['email'] = $this->customer->getEmail();
        $data['message_column'] = $this->load->controller('account/message_center/column_left');

        return $this->render('account/message_center/setting/setting', $data, 'buyer');
    }

    /**
     * 修改保存设置
     */
    public function saveSetting()
    {
        $data['is_in_seller_recommend'] = $this->request->post('is_in_seller_recommend', 1);
        $data['setting'] = $this->request->post('setting', '') ? json_encode($this->request->post('setting', ''), true) : '';
        $data['email_setting'] = $this->request->post('email_setting', '') ? json_encode($this->request->post('email_setting', ''), true) : '';
        $data['other_email'] = $this->request->post('other_email', '') ? json_encode($this->request->post('other_email', ''), true) : '';

        /** @var ModelMessageMessageSetting $modelMessageSetting */
        $modelMessageSetting = load()->model('message/messageSetting');
        $res = $modelMessageSetting->saveSetting($this->customerId, $data);

        return $this->json($res);
    }
}
