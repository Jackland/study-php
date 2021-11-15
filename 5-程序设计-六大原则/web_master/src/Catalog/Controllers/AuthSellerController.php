<?php

namespace App\Catalog\Controllers;

use Registry;

class AuthSellerController extends AuthController
{
    protected $isNotSellerRedirect = ['account/account'];

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        if (!$this->customer->isPartner()) {
            $this->redirect($this->isNotSellerRedirect)->send();
        }
    }
}
