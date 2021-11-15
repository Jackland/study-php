<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Catalog\Forms\Seller\SellerProductRatioForm;
use App\Repositories\Seller\SellerProductRatioRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * 欧洲Seller设置产品价格比例页面
 *
 * Class ControllerCustomerpartnerProductSellerProductRatio
 */
class ControllerCustomerpartnerProductSellerProductRatio extends AuthSellerController
{
    public function index()
    {
        $data = [];
        $sellerId = customer()->getId();
        $countryId = customer()->getCountryId();
        $data['ratio_data'] = app(SellerProductRatioRepository::class)->getSellerProductRatio($sellerId, $countryId)->toArray();
        $data['currency'] = $this->session->get('currency');
        $data['currency_symbol'] = $this->currency->getSymbolLeft($data['currency']) . $this->currency->getSymbolRight($data['currency']);
        return $this->render('customerpartner/product/seller_product_ratio/index', $data, 'seller');
    }

    public function getLogs()
    {
        $sellerId = customer()->getId();
        $countryId = customer()->getCountryId();
        $list = app(SellerProductRatioRepository::class)->getLogsBySeller($sellerId, $countryId);
        return $this->jsonSuccess(['list' => $list]);
    }

    /**
     * 保存
     *
     * @param SellerProductRatioForm $form
     *
     * @return JsonResponse
     * @throws Throwable
     */
    public function save(SellerProductRatioForm $form)
    {
        $data = $form->save();
        if ($data['success']) {
            return $this->jsonSuccess($data);
        } else {
            return $this->jsonFailed($data['error']);
        }
    }
}
