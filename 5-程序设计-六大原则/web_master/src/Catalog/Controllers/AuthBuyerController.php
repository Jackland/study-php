<?php

namespace App\Catalog\Controllers;

// 必须是 buyer 身份登录的
use Registry;

class AuthBuyerController extends AuthController
{
    protected $isNotBuyerRedirect = ['common/home'];

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        if ($this->customer->isPartner()) {
            $this->url->remember();
            $this->redirect($this->isNotBuyerRedirect)->send();
        }
    }
}
