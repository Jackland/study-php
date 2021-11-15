<?php

namespace App\Listeners\Events;

use App\Models\Customer\Customer;

class CustomerLoginSuccessEvent
{
    const PASSWORD_NO_PASSWORD = 1;
    const PASSWORD_MD5 = 2;
    const PASSWORD_HASH = 3;

    public $passwordCheckType;
    public $customer;

    public function __construct(int $passwordCheckType, Customer $customer)
    {
        $this->passwordCheckType = $passwordCheckType;
        $this->customer = $customer;
    }
}
