<?php

use App\Catalog\Controllers\BaseController;
use App\Models\Customer\Customer;
use Framework\Exception\Http\NotFoundException;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ControllerSellerStoreProducts extends BaseController
{
    /**
     * 店铺产品列表页
     * @return RedirectResponse
     * @throws NotFoundException
     * @throws Throwable
     */
    public function index()
    {
        $sellerId = (int)request('id');
        if (!$sellerId) {
            return $this->redirect(['common/home']);
        }
        $seller = Customer::query()->find($sellerId);
        if (!$seller) {
            throw new NotFoundException('未知的 sellerID: ' . $sellerId);
        }
        /**
         * @see ControllerCustomerpartnerProfile::index()
         */
        return load()->controller('customerpartner/profile', [
            'from_seller_store_products' => true,
            'id' => $sellerId,
        ]);
    }
}
