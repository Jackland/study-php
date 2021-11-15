<?php

use App\Catalog\Controllers\AuthController;

class ControllerMessageSystem extends AuthController
{
    public function index()
    {
        if (customer()->isPartner()) {
            return $this->redirect('customerpartner/message_center/my_message/system');
        } else {
            return $this->redirect('account/message_center/system');
        }
    }
}
