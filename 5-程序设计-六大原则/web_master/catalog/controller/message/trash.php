<?php

use App\Catalog\Controllers\AuthController;

class ControllerMessageTrash extends AuthController
{
    public function index()
    {
        if (customer()->isPartner()) {
            return $this->redirect('customerpartner/message_center/my_message/trash');
        } else {
            return $this->redirect('account/message_center/system/trash');
        }
    }
}
