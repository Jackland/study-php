<?php

class ControllerStartupCustomer extends Controller
{
    public function index()
    {
        $customer = new Cart\Customer($this->registry);
        $this->registry->set('customer', $customer);
    }
}
