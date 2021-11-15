<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Repositories\Futures\AgreementRepository;
use App\Repositories\SellerAsset\SellerAssetRepository;

/**
 * Seller 资产管理页面
 *
 * Class ControllerAccountCustomerpartnerAssetsManage
 */
class ControllerAccountCustomerpartnerAssetsManage extends AuthSellerController
{
    // 资产管理首页
    public function index()
    {
        $data = [];
        $this->setDocumentInfo(__('资产管理', [], 'catalog/seller_menu'));
        $data['breadcrumbs'] = $this->getBreadcrumbs([
            ['text' => __('店铺管理', [], 'catalog/seller_menu'), 'href' => 'javascript:void(0)'],
            'current'
        ]);
        $sellerAssetRepository = app(SellerAssetRepository::class);
        // 总资产
        $data['totalAssets'] = $sellerAssetRepository->getTotalAssets($this->customer->getId(), false);
        // 账单资产信息
        $data['billingAssets'] =  $sellerAssetRepository->getBillingAssets($this->customer->getId());
        // 预计应收款
        $data['estimatedAccounts'] = $sellerAssetRepository->getEstimatedAccounts($this->customer->getId(), false);
        // 资产管理信息
        $data['assets'] = $sellerAssetRepository->getAssetInfo($this->customer->getId());
        //
        list($sellerIncomeAccount, $sellerExpendAccount) = app(AgreementRepository::class)->getSellerCollateralAmount($this->customer->getId());
        $data['sellerCollateralAmount'] = $sellerIncomeAccount - $sellerExpendAccount;
        // 纯物流运费
        $data['pureLogisticsFreight'] = $sellerAssetRepository->getPureLogisticsFreight($this->customer->getId(), CustomerSalesOrderStatus::BEING_PROCESSED);
        // 纯物流抵押物减值金额（BP）
        $data['pureLogisticsCollateralValueInBP'] = $sellerAssetRepository->getPureLogisticsCollateralValueInBP($this->customer->getId());
        $data['currency'] = $this->session->get('currency');
        return $this->render('account/customerpartner/assets_manage/index', $data,[
            'separate_column_left' => 'account/customerpartner/column_left',
            'header' => 'account/customerpartner/header',
            'footer' => 'account/customerpartner/footer',
        ]);
    }
}
