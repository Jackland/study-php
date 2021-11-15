<?php

namespace App\Repositories\Safeguard;

use App\Enums\Safeguard\SafeguardClaimAuditType;
use App\Enums\Safeguard\SafeguardClaimStatus;
use App\Models\Safeguard\SafeguardBill;
use App\Models\Safeguard\SafeguardClaim;
use App\Models\Safeguard\SafeguardClaimAudit;
use App\Models\Safeguard\SafeguardClaimDetail;
use App\Models\Safeguard\SafeguardClaimReason;
use App\Enums\Common\YesNoEnum;
use App\Models\Safeguard\SafeguardConfigCountry;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use App\Models\Track\TrackingFacts;
use App\Repositories\Dictionary\DictionaryRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Rma\RamRepository;
use App\Repositories\SalesOrder\CustomerSalesOrderRepository;
use App\Services\Safeguard\SafeguardClaimService;
use Carbon\Carbon;
use Exception;
use Framework\Cache\Cache;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use App\Models\Track\CustomerSalesOrderTracking;
use App\Enums\Track\TrackStatus;
use App\Components\Storage\StorageCloud;
use App\Enums\Safeguard\SafeguardClaimConfig;
use App\Helper\CountryHelper;

class SafeguardClaimRepository
{
    /**
     * 获取申请过理赔的服务类型
     * @return SafeguardClaim[]|Collection
     */
    public function getClaimedSafeguardConfigs()
    {
        $configRids = SafeguardClaim::query()->alias('a')
            ->leftJoin('oc_safeguard_bill as b', 'a.safeguard_bill_id', '=', 'b.id')
            ->where('b.country_id', customer()->getCountryId())
            ->where('a.buyer_id', customer()->getId())
            ->groupBy('b.safeguard_config_rid')
            ->pluck('b.safeguard_config_rid')
            ->toArray();

        if (empty($configRids)) {
            return [];
        }
        $result = [];
        foreach ($configRids as $configRid) {
            $configDetail = app(SafeguardConfigRepository::class)->geiNewestConfig($configRid, customer()->getCountryId());
            if ($configDetail) {
                $result[$configRid] = [
                    'title' => $configDetail->title,
                    'last_safeguard_config_id' => $configDetail->id,
                ];
            }
        }

        return $result;
    }

    /**获取理赔原因
     * @param int $configType
     * @param Cache $cache
     * @return SafeguardClaim[]|Collection
     */
    public function getSafeguardClaimReasons(int $configType)
    {
        $cacheKey = [__CLASS__, __FUNCTION__, 'reasonType', $configType];
        $cache = cache();
        if ($cache->has($cacheKey)) {
            return $cache->get($cacheKey);
        }

        $reasons = SafeguardClaimReason::query()
            ->where('config_type', $configType)
            ->where('is_deleted', YesNoEnum::NO)
            ->get()
            ->toArray();
        if ($reasons) {
            $cache->set($cacheKey, $reasons, SafeguardClaimConfig::PLATFORM_OR_REASON_TTL);
        }

        return $reasons;
    }

    /**
     * 获取理赔信息
     * @param int $salesOrderId
     * @return CustomerSalesOrderLine[]|Collection
     */
    public function getClaimSalesOrderInfos(int $billId, int $salesOrderId)
    {
        $salesOrderInfo = CustomerSalesOrder::query()->find($salesOrderId);
        $salesOrderLines = CustomerSalesOrderLine::query()->alias('line')
            ->where('line.header_id', $salesOrderId)
            ->select(['line.id as line_id', 'line.header_id', 'line.item_code'])
            ->get();

        foreach ($salesOrderLines as $key => $salesOrderLine) {
            $salesOrderAssociatedInfos = app(CustomerSalesOrderRepository::class)->getPurchasesListBySalesOrderId($salesOrderId, $salesOrderLine->line_id);
            if (empty($salesOrderAssociatedInfos['list'])) {
                unset($salesOrderLines[$key]);
                continue;
            }
            //是否可以申请理赔
            $checkCanApplyClaim = app(SafeguardClaimService::class)->checkCanApplyClaim($billId, $salesOrderLine->line_id, $salesOrderLine->item_code);
            $salesOrderLine->can_apply = $checkCanApplyClaim;
            //运单号信息
            $salesOrderLine->trackings = app(SafeguardClaimRepository::class)->getTrackingNumbersWithFormat($salesOrderInfo->order_id, $salesOrderLine->line_id, $salesOrderLine->item_code);
            $productIds = array_column($salesOrderAssociatedInfos['list'], 'product_id');
            $productIds = array_filter(array_unique($productIds));
            if ($productIds) {
                $currentProductId = max($productIds); // //随机取product_id，直接取max，保证每次取的都一样
                $productInfo = app(ProductRepository::class)->getProductInfoByProductId($currentProductId);
                $productInfo['full_image'] = StorageCloud::image()->getUrl($productInfo['image'], ['w' => 60, 'h' => 60]);
                $salesOrderLine->product_info = $productInfo;
                $salesOrderLine->qty = array_sum(array_column($salesOrderAssociatedInfos['list'], 'qty'));
                $salesOrderLine->can_claim_qty = array_sum(array_column($salesOrderAssociatedInfos['list'], 'qty')) - $checkCanApplyClaim['claim_qty'];
                $salesOrderLine->total_price = array_sum(array_column($salesOrderAssociatedInfos['list'], 'total_amount'));
            }
        }

        return $salesOrderLines;
    }

    /**
     * 获取已申请的理赔信息
     * @param int $claimId
     * @return array
     */
    public function getAppliedClaimInfoByClaimId(int $claimId)
    {
        $claimDetails = SafeguardClaimDetail::query()->alias('a')
            ->with(['trackings'])
            ->leftJoin('oc_safeguard_claim as b', 'a.claim_id', '=', 'b.id')
            ->leftJoin('tb_sys_customer_sales_order as c', 'a.sale_order_id', '=', 'c.id')
            ->where(['a.claim_id' => $claimId, 'b.buyer_id' => customer()->getId()])
            ->select(['a.*', 'c.order_id'])
            ->get();

        foreach ($claimDetails as $claimDetail) {
            /** @var SafeguardClaimDetail $claimDetail */
            $claimDetail->product_info = app(ProductRepository::class)->getProductInfoByProductId($claimDetail->product_id);
            $claimDetail->tracking_infos = $this->formatTrackingInfo($claimDetail->trackings->toArray(), $claimDetail->order_id);
            $claimDetail->tracking_infos_str = implode(',', array_column($claimDetail->trackings->toArray(), 'tracking_number'));
        }
        return $claimDetails;
    }

    /**
     * 到期预警，针对资料打回待完善理赔单[列表页和详情页都调用，改时候注意]
     * @param $claimStatus
     * @param $auditCreateTime
     * @param int $advanceDays
     * @return array
     */
    public function getClaimDaysLeftWarning($claimStatus, $auditCreateTime, $advanceDays = 7): array
    {
        $daysLeft = ['show_page' => false, 'show_days' => false, 'days' => 0, 'seconds' => 0];

        if ($claimStatus == SafeguardClaimStatus::CLAIM_BACKED) {
            $timeZone = CountryHelper::getTimezone(customer()->getCountryId());
            $lastDate = Carbon::parse($auditCreateTime)->timezone($timeZone)->addDay($advanceDays)->endOfDay()->timestamp;
            $days = (int)floor(($lastDate - Carbon::now()->timezone($timeZone)->timestamp) / 86400);
            if ($days <= $advanceDays && $days >= 1) {
                $daysLeft = ['show_page' => true, 'show_days' => true, 'days' => $days, 'second' => 0];
            } else {
                $lastSecond = $lastDate - Carbon::now()->timezone($timeZone)->timestamp;
                $daysLeft = ['show_page' => true, 'show_days' => false, 'days' => 0, 'second' => max($lastSecond, 0)];
            }
            //过期了，程序仍然没有把状态处理成理赔失败时候，做个兼容
            if (!$daysLeft['show_days'] && $daysLeft['second'] <= 0) {
                $daysLeft['show_page'] = false;
            }
        }

        return $daysLeft;
    }

    /**
     * 获取上次申请理赔的销售平台
     * @return string
     */
    public function getLastClaimSalesPlatform(): string
    {
        $allSalesPlatForm = app(DictionaryRepository::class)->getSalePlatform();

        $lastSalesPlatForm = SafeguardClaimAudit::query()
            ->where(['buyer_id' => customer()->getId(), 'role_id' => YesNoEnum::NO])
            ->whereNotNull('sales_platform')
            ->orderByDesc('create_time')
            ->first();

        if ($lastSalesPlatForm && $allSalesPlatForm && in_array($lastSalesPlatForm->sales_platform, array_column($allSalesPlatForm, 'DicValue'))) {
            return $lastSalesPlatForm->sales_platform;
        }

        return '';
    }

    /**
     * 获取运单号信息并组织特定数据格式
     * @param string $salesOrderId
     * @param int $salesOrderLineId
     * @param string $itemCode
     * @return array
     */
    public function getTrackingNumbersWithFormat(string $salesOrderId, int $salesOrderLineId, string $itemCode)
    {
        $trackingInfos = $this->getTrackingNumbersFormat($salesOrderId, $salesOrderLineId, $itemCode);
        if ($trackingInfos) {
            $trackingInfos = $this->formatTrackingInfo($trackingInfos, $salesOrderId);
        }

        return $trackingInfos;
    }

    /**
     * 获取审核数据（可展示给buyer的审核数据）
     * @param int $claimId
     * @return array
     */
    public function getClaimAuditList(int $claimId)
    {
        $claimInfo = SafeguardClaim::query()->find($claimId);

        $query = SafeguardClaimAudit::query()->alias('a')
            ->with(['attachs'])
            ->where('a.claim_id', $claimId)
            ->whereIn('a.status', SafeguardClaimAuditType::getBuyerHandleStatus());

        if (in_array($claimInfo->status, [SafeguardClaimStatus::CLAIM_SUCCEED, SafeguardClaimStatus::CLAIM_FAILED])) {
            $query->orWhere('a.id', $claimInfo->audit_id);
        }

        $claimAuditList = $query
            ->orderByDesc('a.id')//id顺序即代表创建时间顺序
            ->select(['a.*', 'a.status as claim_audit_status'])
            ->get()
            ->toArray();

        $finalClaimAuditList = array_filter($claimAuditList);
        /** @var SafeguardClaim $claimInfo */
        $result = collect($finalClaimAuditList)->map(function ($item) use ($claimInfo) {
            //这儿是展示平台的处理状态
            if ($item['claim_audit_status'] == SafeguardClaimAuditType::AUDIT_APPROVED && $claimInfo->status == SafeguardClaimStatus::CLAIM_SUCCEED) {
                //理赔成功,需要调取客服所填的信息和附件
                $kfAuditInfo = SafeguardClaimAudit::query()->alias('a')
                    ->where('claim_id', $claimInfo->id)
                    ->where('role_id', SafeguardClaimConfig::HANDLE_KF_ROLE_ID)
                    ->where('status', SafeguardClaimAuditType::AUDIT_APPROVED)
                    ->orderByDesc('id')->with(['attachs'])
                    ->select(['a.*', 'a.status as claim_audit_status'])
                    ->first();
                if ($kfAuditInfo) {
                    $oldItem = $item;
                    $item = obj2array($kfAuditInfo);
                    $item['create_time'] = $oldItem['create_time'];
                } else {
                    $item['content'] = ''; //容错
                    $item['attachs'] = [];
                }
                $item['claim_audit_status_name'] = SafeguardClaimStatus::getDescription(SafeguardClaimStatus::CLAIM_SUCCEED); //'理赔成功';
            } elseif ($item['claim_audit_status'] == SafeguardClaimAuditType::AUDIT_BACKED) {
                $item['claim_audit_status_name'] = SafeguardClaimStatus::getDescription(SafeguardClaimStatus::CLAIM_BACKED); //'资料待完善';
            } elseif (in_array($item['claim_audit_status'], [SafeguardClaimAuditType::AUDIT_REJECTED_SYS, SafeguardClaimAuditType::AUDIT_REJECTED]) && $claimInfo->status == SafeguardClaimStatus::CLAIM_FAILED) {
                $item['claim_audit_status_name'] = SafeguardClaimStatus::getDescription(SafeguardClaimStatus::CLAIM_FAILED); //理赔失败';
            } else {
                $item['claim_audit_status_name'] = 'Unknown';
            }
            $item['payment_method'] = $claimInfo->payment_method ? $claimInfo->payment_method : 'N/A';
            $item['paid_time'] = $claimInfo->paid_time ? $claimInfo->paid_time->toDateTimeString() : 'N/A';

            return $item;
        })->toArray();

        //$lastOperatorFrom  最后一个操作来源  -1：系统  0：用户  1：运营人员
        $showGigaInfo = $lastOperatorFrom = 0;
        if ($result) {
            $lastAudit = current($result);
            if ($lastAudit['role_id'] == YesNoEnum::NO) {
                $showGigaInfo = 1;
            } elseif ($lastAudit['role_id'] == -1) {
                $lastOperatorFrom = -1;
            } elseif ($lastAudit['role_id'] > YesNoEnum::NO) {
                $lastOperatorFrom = 1;
            }
        }

        return [
            'show_giga_info' => $showGigaInfo,
            'last_operator_from' => $lastOperatorFrom,
            'claim_audit_list' => $result,
        ];
    }

    /**
     * 理赔计算器采购总金额信息
     * @param array $appliedData
     * @return array
     * @see https://ones.ai/wiki/#/team/8wP6mUy7/space/7a6Hof7n/page/JMvqfmun
     * @throws Exception
     */
    public function getClaimCalculatorData(array $appliedData, int $bill_id): array
    {
        $billDetail = SafeguardBill::query()->find($bill_id);

        $result['base_info'] = [
            'service_rate' => $billDetail->safeguardConfig->service_rate,
            'coverage_rate' => $billDetail->safeguardConfig->coverage_rate,
        ];

        $lineInfos = collect($appliedData)->keyBy('line_id')->toArray();
        if (empty($appliedData) || empty($lineInfos)) {
            $result['list'] = [];
            return $result;
        }

        $salesOrderLineIds = array_column($lineInfos, 'line_id');
        $salesOrderLines = CustomerSalesOrderLine::query()->alias('line')
            ->whereIn('line.id', $salesOrderLineIds)
            ->select(['line.id as line_id', 'line.header_id', 'line.item_code'])
            ->get();

        foreach ($salesOrderLines as $salesOrderLine) {
            $salesOrderAssociatedInfos = app(CustomerSalesOrderRepository::class)->getPurchasesListBySalesOrderId($salesOrderLine->header_id, $salesOrderLine->line_id);
            $priceInfos = [];
            foreach ($salesOrderAssociatedInfos['list'] as $associated) {
                $priceInfos[$associated['order_product_id']] =
                    [
                        'order_id' => $associated['order_id'],
                        'order_product_id' => $associated['order_product_id'],
                        'origin_qty' => $associated['qty'],
                        'per_average_amount' => round($associated['total_amount'] / max($associated['qty'], 1), customer()->isJapan() ? 0 : 2),
                    ];
            }

            $appliedNumber = $lineInfos[$salesOrderLine->line_id]['applied_num'] ?? 0;
            if ($priceInfos) {
                $priceInfos = collect($priceInfos)->sortByDesc('per_average_amount')->all();
            }
            $salesOrderLine->applied_num = $appliedNumber;

            $finalMaxPrice = 0;
            $orderProductIds = [];
            foreach ($priceInfos as $priceInfo) {
                if ($priceInfo['origin_qty'] >= $appliedNumber) {
                    $finalMaxPrice = bcadd($finalMaxPrice,$priceInfo['per_average_amount'] * $appliedNumber,customer()->isJapan() ? 0 : 2);
                    $orderProductIds[] = $priceInfo['order_product_id'];
                    break;
                } else {
                    $finalMaxPrice = bcadd($finalMaxPrice,$priceInfo['per_average_amount'] * $priceInfo['origin_qty'],customer()->isJapan() ? 0 : 2);
                    $appliedNumber -= $priceInfo['origin_qty'];
                    $orderProductIds[] = $priceInfo['order_product_id'];
                    continue;
                }
            }

            $salesOrderLine->final_max_price = $finalMaxPrice;
            $rmaAmount = app(RamRepository::class)->getPurchaseOrderRmaAmount($orderProductIds);
            $salesOrderLine->rma_amount = customer()->isJapan() ? (int)$rmaAmount : $rmaAmount;
        }

        $result['list'] = collect($salesOrderLines)->keyBy('line_id')->toArray();

        return $result;
    }

    /**
     * 获取运单号信息 不具复用性
     * @param string $salesOrderId
     * @param int $salesOrderLineId
     * @return array
     */
    private function getTrackingNumbersFormat(string $salesOrderId, int $salesOrderLineId, string $itemCode)
    {
        $trackings = CustomerSalesOrderTracking::query()->alias('a')
            ->with(['carrier'])
            ->where('a.SalesOrderId', $salesOrderId)
            ->where('a.SalerOrderLineId', $salesOrderLineId)
            ->where('a.status', YesNoEnum::YES)
            ->where(function ($query) use ($itemCode) {
                /** @var Builder $query */
                $query->orWhere('a.ShipSku', $itemCode);
                $query->orWhere('a.parent_sku', $itemCode);
            })
            ->orderBy('a.update_time')
            ->select(['a.*'])
            ->get();

        $result = [];
        if ($trackings->isEmpty()) {
            return $result;
        }
        /** @var CustomerSalesOrderTracking $tracking */
        foreach ($trackings as $tracking) {
            $tempTrackNumbers = explode(',', $tracking->TrackingNumber); //可能是,拼接起来的
            foreach ($tempTrackNumbers as $tempTrackNumber) {
                $result[$tempTrackNumber] = [
                    'tracking_number' => $tempTrackNumber,
                    'carrier_id' => $tracking->LogisticeId,
                    'carrier' => $tracking->carrier->CarrierName,
                ];
            }
        }

        return $result;
    }

    /**
     * 二次重组运单号信息
     * @param array $trackingInfos
     * @param string $salesOrderId
     * @return array
     */
    private function formatTrackingInfo(array $trackingInfos, string $salesOrderId)
    {
        $trackingNumbers = array_column($trackingInfos, 'tracking_number');
        $trackingFacts = TrackingFacts::query()
            ->where('status', YesNoEnum::YES)
            ->where('sales_order_id', $salesOrderId)
            ->whereIn('tracking_number', $trackingNumbers)
            ->orderByDesc('update_time')
            ->get()
            ->keyBy('tracking_number')
            ->toArray();

        foreach ($trackingInfos as $key => &$trackingInfo) {
            if (isset($trackingFacts[$trackingInfo['tracking_number']])) {
                $trackingInfo['carrier_status'] = $trackingFacts[$trackingInfo['tracking_number']]['carrier_status'];
            } else {
                $trackingInfo['carrier_status'] = 0; //未查到有效物流状态
            }
            $trackingInfo['carrier_status_name'] = TrackStatus::getDescription($trackingInfo['carrier_status'], 'N/A');
            if ($trackingInfo['carrier_status'] == 0) {
                $trackingInfo['show_info'] = $trackingInfo['tracking_number'] . '(' . $trackingInfo['carrier'] . ')';
            } else {
                $trackingInfo['show_info'] = $trackingInfo['tracking_number'] . '(' . $trackingInfo['carrier'] . ') - ' . $trackingInfo['carrier_status_name'];
            }
        }

        return $trackingInfos;
    }

    /**
     * 判断保单是否有理赔成功过的理赔单
     * 如果传的数组，有一个成功的就返回true
     * @param array|int $safeguardId
     * @return bool
     */
    public function checkBillIsClaimSuccessful($safeguardId): bool
    {
        $safeguardClaimQuery = SafeguardClaim::query();
        if (is_array($safeguardId)) {
            $safeguardClaimQuery->whereIn('safeguard_bill_id', $safeguardId);
        } else {
            $safeguardClaimQuery->where('safeguard_bill_id', $safeguardId);
        }
        $safeguardClaimQuery->where('status', SafeguardClaimStatus::CLAIM_SUCCEED);
        return $safeguardClaimQuery->exists();
    }

    /**
     * 获取保单当前步骤
     * @param SafeguardClaim|null|mixed $claimDetail
     * @return array
     */
    public function getClaimStepInfos(?SafeguardClaim $claimDetail)
    {
        $result = [
            'first_step' => 1,
            'second_step' => 1,
            'third_step' => 1,
            'third_result' => 1,
        ];
        if (empty($claimDetail)) {
            $result['second_step'] = $result['third_step'] = $result['third_result'] = 0;
            return $result;
        }
        if ($claimDetail->status == SafeguardClaimStatus::CLAIM_SUCCEED) {
            return $result;
        }
        if ($claimDetail->status == SafeguardClaimStatus::CLAIM_FAILED) {
            $result['third_result'] = 0;
        } else {
            $result['third_step'] = $result['third_result'] = 0;
        }

        return $result;
    }

    /**
     * 根据claim no获取buyer的所有理赔单
     *
     * @param int $buyerId
     * @param string $claimNo
     * @return SafeguardClaim[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getListByClaimNo(int $buyerId, string $claimNo)
    {
        return SafeguardClaim::query()->where('buyer_id', $buyerId)
            ->where('claim_no', 'like', "%{$claimNo}%")
            ->get();
    }

    /**
     * 根据销售单id查询每个明细已申请成功或者正在申请中的明细数量
     *
     * @param int $orderId
     * @return CustomerSalesOrderLine[]|\Framework\Model\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|Builder[]|Collection
     */
    public function getSalesOrderLineSumClaimNumber(int $orderId)
    {
        return CustomerSalesOrderLine::query()->alias('line')
            ->leftJoin('oc_safeguard_claim_detail as detail', 'line.id', '=', 'detail.sale_order_line_id')
            ->leftJoin('oc_safeguard_claim as claim', 'detail.claim_id', '=', 'claim.id')
            ->where('line.header_id', $orderId)
            ->whereIn('claim.status', [SafeguardClaimStatus::CLAIM_IN_PROGRESS, SafeguardClaimStatus::CLAIM_SUCCEED, SafeguardClaimStatus::CLAIM_BACKED])
            ->select(['line.id', 'line.item_code', 'line.qty as qty', new Expression('sum(detail.qty) as claim_qty')])
            ->groupBy('line.id')
            ->get();
    }
}
