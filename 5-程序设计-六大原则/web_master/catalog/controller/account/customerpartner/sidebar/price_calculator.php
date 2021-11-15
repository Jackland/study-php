<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Catalog\Forms\Calculator\OceanFreightCalculateForm;
use App\Catalog\Forms\Calculator\PriceCalculator;
use App\Catalog\Forms\Calculator\ReceiptCheckForm;
use App\Catalog\Forms\Calculator\ReceiptSearchForm;
use App\Models\Product\Product;

class ControllerAccountCustomerpartnerSidebarPriceCalculator extends AuthSellerController
{
    public function index()
    {
        $this->document->setTitle(__('定价计算器', [], 'common'));
        $data['countryId'] = customer()->getCountryId();
        $data['customerId'] = customer()->getId();
        $data['currency'] = session('currency');
        $data['symbolLeft'] = $this->currency->getSymbolLeft($data['currency']);
        $data['symbolRight'] = $this->currency->getSymbolRight($data['currency']);
        return $this->render('account/customerpartner/sidebar/price_calculator/index', $data, 'seller');
    }

    // region api
    // 通过sku校验商品
    public function checkSku()
    {
        $sku = request('sku', '');
        $customerId = request('customerId', '');
        if (empty($customerId) || empty($sku)) {
            return $this->jsonFailed(__('ItemCode不存在', [], 'account/sidebar/price_calculator'));
        }
        $ctp = Product::query()->alias('p')
            ->with(['customerPartner'])
            ->where(function ($q) use ($sku) {
                $q->orWhere('p.sku', $sku);
                $q->orWhere('p.mpn', $sku);
            })
            ->get()
            ->filter(function (Product $product) use ($customerId) {
                return $product->customerPartner->customer_id == $customerId;
            })
            ->first();
        if (empty($ctp)) {
            return $this->jsonFailed(__('ItemCode不存在', [], 'account/sidebar/price_calculator'));
        }
        return $this->jsonSuccess(['productId' => $ctp->product_id,]);
    }

    // 获取仓租费,运费打包费接口
    public function getFee(PriceCalculator $form)
    {
        if ($form->getFirstError()) {
            return $this->jsonFailed($form->getFirstError());
        }
        $ret = $form->getData();
        if (is_string($ret)) {
            return $this->jsonFailed($ret);
        }
        return $this->jsonSuccess($ret);
    }

    // 获取运单接口
    public function getReceiptOrderList(ReceiptSearchForm $form)
    {
        if ($form->getFirstError()) {
            return $this->jsonFailed($form->getFirstError());
        }
        return $this->jsonSuccess($form->getData());
    }

    // 校验运单号是否存在
    public function checkReceiptOrderExist(ReceiptCheckForm $form)
    {
        if ($form->getFirstError()) {
            return $this->jsonFailed($form->getFirstError());
        }
        $res = $form->getData();
        if (empty($res)) {
            return $this->jsonFailed(__('入库单号不存在', [], 'account/sidebar/price_calculator'));
        }
        return $this->jsonSuccess($res);
    }

    // 获取海运费接口
    public function getOceanFreight(OceanFreightCalculateForm $form)
    {
        if ($form->getFirstError()) {
            return $this->jsonFailed($form->getFirstError());
        }
        return $this->jsonSuccess($form->getData());
    }

    // 获取seller平台费比例
    public function getPlatformFeeRatio()
    {
        $sellerId = request('customerId', '');
        if (empty($sellerId)) {
            return $this->jsonFailed(__('用户不存在', [], 'account/sidebar/price_calculator'));
        }
        $ratio = db('tb_platform_fee_seller_ratio')
            ->where('seller_id', $sellerId)
            ->orderByDesc('id')
            ->value('ratio');
        return $this->jsonSuccess(['ratio' => (float)($ratio ?: 0.05)]);
    }
    // end region
}
