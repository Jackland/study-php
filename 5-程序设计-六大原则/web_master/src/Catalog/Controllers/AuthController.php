<?php

namespace App\Catalog\Controllers;

use Registry;

// 需要登录的
class AuthController extends BaseController
{
    protected $noLoginRedirect = ['account/login'];

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        if (!$this->customer->isLogged()) {
            $this->url->remember();
            $this->redirect($this->noLoginRedirect)->send();
        }
    }
}
