<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Repositories\Customer\CustomerRepository;

/**
 * Class ControllerAccountEdit
 * @property ModelAccountCustomer $model_account_customer
 * @property ModelAccountCustomField $model_account_custom_field
 */
class ControllerAccountEdit extends AuthBuyerController
{
    public function index()
    {
        $this->load->language('account/edit');
        $customerModel = clone customer()->getModel();
        $this->load->model('account/customer');
        $data = [
            'customer' => $customerModel,
            'can_change_phone' => app(CustomerRepository::class)->isPhoneCanChange(customer(), true),
        ];
        return $this->render('account/edit', $data, 'buyer');
    }
}
