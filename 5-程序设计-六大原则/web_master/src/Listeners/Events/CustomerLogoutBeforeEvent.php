<?php

namespace App\Listeners\Events;

use Cart\Customer;

class CustomerLogoutBeforeEvent
{
    public $customer;

    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
    }
}
