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
use Carbon\Carbon;

class ControllerApiSellerAsset extends ControllerApiBase
{
    protected $sellerAssetRepository;
    protected $storageFeeRepository;

    public function __construct(
        Registry $registry,
        SellerAssetRepository $sellerAssetRepository
    )
    {
        parent::__construct($registry);
        $this->sellerAssetRepository = $sellerAssetRepository;
    }

    public function getAlarmSeller()
    {
        set_time_limit(0);

        $validator = $this->request->validate([
            'seller_id' => 'nullable|numeric',
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }
        $assetAlarmLineSwitch = configDB('asset_alarm_line_switch');
        if (!$assetAlarmLineSwitch) {
            return $this->jsonFailed('警报未开启:asset_alarm_line_switch = 0');
        }
        $sellerId = $this->request->get('seller_id',0);
        try {
            // 获取报警等级区间，目前只有美国
            $alarmSetting = SellerAssetAlarmLineSetting::query()->where('country_id', Country::AMERICAN)
                ->first();
            if (!$alarmSetting) {
                throw new Exception('警报线未设置');
            }
            $sellers = [];
            if ($sellerId) {
                //指定seller返回指定seller的数据
                if ($sellerData = $this->sellerAssetRepository->getAssetInfo($sellerId)) {
                    $sellers[] = $sellerData;
                } else {
                    throw new Exception('没有查到指定seller的数据');
                }
            } else {
                // 未指定seller，返回所有seller的数据
                $sellers = $this->sellerAssetRepository->getAllAlarmSeller(Country::AMERICAN);
            }
            /*
            特别说明：这里的一级报警相当于后台管理系统配置的二级报警，
                    二级报警相当于后台的一级报警，
                    三级就是资产为负数的报警,
            */
            $data = [
                // 未触发报警的
                'alarm_0' => [
                    'level' => 0,
                    'min' => $alarmSetting->first_alarm_line_max,
                    'max' => 999999999,
                    'seller_ids' => [],
                    'reset_seller_ids' => []
                ],
                // 触发第一级报警
                'alarm_1' => [
                    'level' => 1,
                    'min' => $alarmSetting->first_alarm_line_min,
                    'max' => $alarmSetting->first_alarm_line_max,
                    'seller_ids' => [],
                    'reset_seller_ids' => []
                ],
                // 触发第二级报警的
                'alarm_2' => [
                    'level' => 2,
                    'min' => $alarmSetting->second_alarm_line_min,
                    'max' => $alarmSetting->second_alarm_line_max,
                    'seller_ids' => [],
                    'reset_seller_ids' => []
                ],
                // 触发第三级报警的（资产为0）
                'alarm_3' => [
                    'level' => 3,
                    'min' => -1,
                    'max' => $alarmSetting->second_alarm_line_min,
                    'seller_ids' => [],
                    'reset_seller_ids' => []
                ],
            ];
            foreach ($sellers as $seller) {
                $sellerTotalAssets = $this->sellerAssetRepository->getTotalAssets($seller->customer_id, false);
                $alarmLevel = 0;
                if ($sellerTotalAssets >= $alarmSetting->first_alarm_line_max) {
                    // 未触发报警
                    $alarmLevel = 0;
                } elseif ($sellerTotalAssets >= $alarmSetting->first_alarm_line_min && $sellerTotalAssets < $alarmSetting->first_alarm_line_max) {
                    // 在第一级警报线区间
                    $alarmLevel = 1;
                } elseif ($sellerTotalAssets >= $alarmSetting->second_alarm_line_min && $sellerTotalAssets < $alarmSetting->second_alarm_line_max) {
                    // 在第二级警报线区间，这里没有判断大于二级警报线，是防止负数的情况
                    $alarmLevel = 2;
                } elseif ($sellerTotalAssets < $alarmSetting->second_alarm_line_min){
                    // 资产小于0
                    $alarmLevel = 3;
                }
                if ($seller->alarm_level == $alarmLevel) {
                    // 现在的报警等级=计算后的报警等级,不做处理
                    continue;
                }
                if ($seller->alarm_level < $alarmLevel) {
                    // 现在的报警等级<计算后的报警等级，发送报警
                    $data['alarm_' . $alarmLevel]['seller_ids'][] = $seller->customer_id;
                } else {
                    $data['alarm_' . $alarmLevel]['reset_seller_ids'][] = $seller->customer_id;
                }
            }
            dbTransaction(function () use ($data) {
                foreach ($data as $datum) {
                    $updateSellerIds = array_merge($datum['seller_ids'], $datum['reset_seller_ids']);
                    if (!empty($updateSellerIds)) {
                        SellerAsset::query()->whereIn('customer_id', $updateSellerIds)
                            ->update([
                                'alarm_level' => $datum['level']
                            ]);
                    }
                }

                if (!empty($data['alarm_3']['seller_ids'])) {
                    $productList = Product::query()->alias('p')
                        ->leftJoinRelations('customerPartnerToProduct as cp')
                        ->whereIn('cp.customer_id', $data['alarm_3']['seller_ids'])
                        ->where('p.status', YesNoEnum::YES)
                        ->get(['p.*']);
                    //下架seller的商品
                    Product::query()->alias('p')
                        ->leftJoinRelations('customerPartnerToProduct as cp')
                        ->whereIn('cp.customer_id', $data['alarm_3']['seller_ids'])
                        ->where('p.status', YesNoEnum::YES)
                        ->update(['p.status' => YesNoEnum::NO]);
                    app(ProductLogService::class)->addLog(
                        $productList,
                        ProductLogType::OFF_SHELF,
                        'Seller资产不足',
                        ['status' => YesNoEnum::YES],
                        ['status' => YesNoEnum::NO],
                        'admin'
                    );
                    Logger::addEditProduct('资产风控产品下架，下架seller:[' . implode(',', $data['alarm_3']['seller_ids']) . ']'
                        . '下架产品:' . $productList->implode('product_id', ',') . ']'
                        , 'info');
                }
            });
        } catch (Throwable $e) {
            Logger::error([__CLASS__, __FUNCTION__, '接口失败', $e->getMessage()], 'error');
            Logger::error($e, 'error');
            return $this->jsonFailed('接口调用失败');
        }

        return $this->jsonSuccess($data);
    }
}
