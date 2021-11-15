<?php

use App\Catalog\Controllers\AuthController;

class ControllerMessageSetting extends AuthController
{
    public function index()
    {
        if (customer()->isPartner()) {
            return $this->redirect('customerpartner/message_center/my_message/setting');
        } else {
            return $this->redirect('account/message_center/setting');
        }
    }
}
