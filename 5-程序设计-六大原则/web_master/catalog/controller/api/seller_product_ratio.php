<?php

use App\Enums\Common\YesNoEnum;
use App\Enums\Country\Country;
use App\Enums\Product\ProductLogType;
use App\Enums\SalesOrder\CustomerSalesOrderMode;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Logging\Logger;
use App\Models\Product\Product;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use App\Models\SellerAsset\SellerAsset;
use App\Models\SellerAsset\SellerAssetAlarmLineSetting;
use App\Repositories\Product\ProductRepository;
use App\Repositories\SellerAsset\SellerAssetRepository;
use App\Services\Product\ProductLogService;
use App\Services\Seller\SellerProductRatioService;
use Carbon\Carbon;

class ControllerApiSellerProductRatio extends ControllerApiBase
{
    protected $sellerProductRatioService;

    public function __construct(
        Registry $registry,
        SellerProductRatioService $sellerProductRatioService
    )
    {
        parent::__construct($registry);
        $this->sellerProductRatioService = $sellerProductRatioService;
    }

    public function updateRatio()
    {
        set_time_limit(0);
        $validator = $this->request->validate([
            'date_time' => 'nullable|date',
            'seller_id' => 'nullable|numeric',
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }
        $dateTime = $this->request->get('date_time');
        $sellerId = $this->request->get('seller_id',0);
        $db = db()->getConnection();
        $db->beginTransaction();
        try {
            $updateData =  $this->sellerProductRatioService->dealWithSellerProductRatioTakeEffect($dateTime, $sellerId);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            Logger::error([__CLASS__, __FUNCTION__, '接口失败', $e->getMessage()], 'error');
            Logger::error($e, 'error');
            return $this->jsonFailed('接口调用失败:' . $e->getMessage());
        }
        return $this->jsonSuccess(['seller_list' => $updateData->pluck('seller_id')->toArray()]);
    }

}
