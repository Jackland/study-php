<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Repositories\Marketing\MarketingTimeLimitDiscountRepository;

class ControllerCustomerpartnerMarketingCampaignDiscountTab extends AuthSellerController
{
    //折扣母tab
    public function index()
    {
        // 正在进行中的活动才有低库存概念
        $lowStockNum = app(MarketingTimeLimitDiscountRepository::class)->getEffectiveTimeLimitLowQtyNumber(customer()->getId());

        $data = [
            'low_stock_product_number' => $lowStockNum
        ];

        return $this->render('customerpartner/marketing_campaign/discount/index', $data, 'seller');
    }
}
