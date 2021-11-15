<?php

namespace App\Repositories\Safeguard;

use App\Components\Traits\RequestCachedDataTrait;
use App\Enums\Common\YesNoEnum;
use App\Models\Customer\Customer;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Safeguard\SafeguardBuyerGroup;
use App\Models\Safeguard\SafeguardConfig;
use App\Models\Safeguard\SafeguardConfigCountry;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Repositories\Buyer\BuyerRepository;
use Illuminate\Support\Collection;

class SafeguardConfigRepository
{
    use RequestCachedDataTrait;

    protected $country_id;

    public function __construct()
    {
        $this->country_id = customer()->getCountryId();
    }

    /**
     * 获取（检测）某个国家下某个配置 是否生效
     * @param int $countryId
     * @param int $configId
     * @return integer|null
     */
    public function checkConfigIsEffective(int $countryId, int $configId)
    {
        return SafeguardConfigCountry::query()
            ->where('safeguard_config_id', $configId)
            ->where('country_id', $countryId)
            ->value('status');
    }

    /**
     * 获取当前用户已有的保单的保障服务
     * @param int $customerId
     * @param int $countryId
     * @return SafeguardConfig[]|Collection
     */
    public function getOneselfAlreadySafeguard(int $customerId,int $countryId)
    {
        return SafeguardConfigCountry::query()->alias('scc')
            ->leftJoin('oc_safeguard_bill AS sb', 'sb.safeguard_config_rid', '=', 'scc.safeguard_config_rid')
            ->leftJoin('oc_safeguard_config AS sc', 'scc.safeguard_config_id', '=', 'sc.id')
            ->where('sb.buyer_id', '=', $customerId)
            ->where('scc.country_id', '=', $countryId)
            ->select(['sc.id', 'sc.title', 'sb.safeguard_config_rid'])
            ->groupBy(['sc.id'])
            ->orderBy('sc.create_time')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->safeguard_config_rid => [
                    'title' => $item->title,
                    'last_safeguard_config_id' => $item->id,
                ]];
            });
    }

    /**
     * 获取buy可用的保障服务
     * @param $buyId
     * @return SafeguardConfigCountry[]
     */
    public function getBuyerConfigs($buyId)
    {
        $configs = SafeguardConfigCountry::query()
            ->with(['config'])
            ->where('country_id', $this->country_id)
            ->where('status', YesNoEnum::YES)
            ->orderBy('safeguard_config_rid','ASC')
            ->get();
        if ($configs->isEmpty()) {
            return [];
        }
        $data = [];
        $businessType = app(BuyerRepository::class)->getTypeById($buyId);
        $accountAttribute = Customer::query()->where('customer_id', $buyId)->value('accounting_type');
        $accountType = Customer::query()->where('customer_id', $buyId)->value('account_attributes');
        foreach ($configs as $key => $config) {
            $status = [];
            $buyerScope = json_decode($config->config->buyer_scope, true);
            //账号作用范围，空代表全部buyer
            if (!$buyerScope) {
                $data[] = $config;
                continue;
            }
            $type = $buyerScope['type'];
            if (!empty($buyerScope['scope']['business_type']) && in_array($businessType, $buyerScope['scope']['business_type'])) {
                $status[$key][] = 1;
                if ($type == 'or') {
                    $data[] = $config;
                    continue;
                }
            }
            if (!empty($buyerScope['scope']['account_attribute']) && in_array($accountAttribute, $buyerScope['scope']['account_attribute'])) {
                $status[$key][] = 1;
                if ($type == 'or') {
                    $data[] = $config;
                    continue;
                }
            }
            if (!empty($buyerScope['scope']['account_type']) && in_array($accountType, $buyerScope['scope']['account_type'])) {
                $status[$key][] = 1;
                if ($type == 'or') {
                    $data[] = $config;
                    continue;
                }
            }
            // 新逻辑
            if (isset($buyerScope['scope']['buyer_account']) && !empty($buyerScope['scope']['buyer_account'])) {
                $isContains = $buyerScope['scope']['buyer_account']['is_contains'] ?? 1;
                $isContains = (int)$isContains;
                $buyerGroupIds = $buyerScope['scope']['buyer_account']['buyer_group_ids'] ?: [];
                $buyerIds = $buyerScope['scope']['buyer_account']['buyer_ids'] ?: [];
                $allBuyerIds = app(SafeguardConfigRepository::class)->getSafeguardConfigBuyersByGroupIds($buyerGroupIds, $buyerIds);

                if (!empty($allBuyerIds)) {
                    // or 走到这 说明上面三种都不满足
                    if ($type == 'or') {
                        if ($isContains == 0) { // 不包含
                            if (!in_array($buyId, $allBuyerIds)) {
                                $data[] = $config;
                            }
                        } else {
                            if (in_array($buyId, $allBuyerIds)) {
                                $data[] = $config;
                            }
                        }
                        continue;
                    }
                    // and 情况 ; 和or互斥 目前就or and 这2种
                    if ($isContains == 0) {
                        if (in_array($buyId, $allBuyerIds)) {
                            continue;
                        }
                    } else {
                        if (!in_array($buyId, $allBuyerIds)) {
                            continue;
                        }
                    }
                    $status[$key][] = 1;
                }
            }

            if ($type == 'and' && !empty($status[$key]) && count(array_filter($buyerScope['scope'])) == count($status[$key])) {
                $data[] = $config;
            }
        }
        return $data;
    }

    /**
     * 获取费用的保障服务配置详情
     *
     * @param FeeOrder|int $feeOrder
     * @param int $countryId
     *
     * @return array 详情看内部details变量
     */
    public function getConfigDetailsByFeeOrder($feeOrder, $countryId)
    {
        if (!($feeOrder instanceof FeeOrder)) {
            $feeOrder = FeeOrder::with(['safeguardDetails', 'safeguardDetails.safeguardConfig'])->find($feeOrder);
        }
        $details = [];
        if ($feeOrder && $feeOrder->safeguardDetails->isNotEmpty()) {
            // 获取最新的保障服务名称
            foreach ($feeOrder->safeguardDetails as $safeguardDetail) {
                if (!$safeguardDetail->safeguardConfig) {
                    continue;
                }
                $configDetail = app(SafeguardConfigRepository::class)->geiNewestConfig($safeguardDetail->safeguardConfig->rid, $countryId);
                $details[] = [
                    'details_id' => $safeguardDetail->id,  // 保障服务ID
                    'safeguard_config_id' => $safeguardDetail->safeguard_config_id, // 保障服务ID
                    'safeguard_title' => optional($configDetail)->title,// 保障服务类型（名称）
                    'order_base_amount' => $safeguardDetail->order_base_amount, // 保障服务基数
                    'service_rate' => $safeguardDetail->safeguardConfig->service_rate * 100 . '%',// 保障服务费费率
                    'safeguard_fee' => $safeguardDetail->safeguard_fee// 保障服务费总金额
                ];
            }
        }
        return $details;
    }

    /**
     * 判断销售订单是否可以购买指定的保障服务
     *
     * @param CustomerSalesOrder|int $salesOrder
     * @param array $safeguardConfigIds
     * @return array [success=>bool,can_buy=>array,cant_buy=>array] 第一个参数是是否可以，第二个是其中可以买的，第三个参数是不可以购买的保障服务
     */
    public function checkCanBuySafeguardBuSalesOrder($salesOrder, array $safeguardConfigIds): array
    {
        $res = [
            'success' => true,
            'buyer_can_buy' => [],
            'can_buy' => [],
            'cant_buy' => [],
        ];
        if (!($salesOrder instanceof CustomerSalesOrder)) {
            $salesOrder = CustomerSalesOrder::find($salesOrder);
        }
        $buyerConfigs = collect($this->getBuyerConfigs($salesOrder->buyer_id));
        if ($buyerConfigs->isEmpty()) {
            $res['success'] = false;
            $res['cant_buy'] = $safeguardConfigIds;
            return $res;
        }
        $res['buyer_can_buy'] = $buyerConfigs->pluck('safeguard_config_id')->toArray();// buyer所有可买的
        $salesOrder->load(['lines']);
        //$totalProductCount = $salesOrder->lines->sum('qty');
        $totalProductCount = $salesOrder->lines->max('qty'); //业务变更：订单中单个sku数量超过多少，不允许buyer购买保障服务
        foreach ($safeguardConfigIds as $safeguardConfigId) {
            // 先判断服务buyer是否可以购买
            /** @var SafeguardConfigCountry $configData */
            $configData = $buyerConfigs->where('safeguard_config_id', $safeguardConfigId)->first();
            if (!$configData || !($configData->config)) {
                $res['success'] = false;
                $res['cant_buy']['not_exist'][] = $safeguardConfigId;
                continue;
            }
            // 在判断商品数量是否满足条件
            if ($configData->config->order_product_max !== -1 && $totalProductCount > $configData->config->order_product_max) {
                $res['success'] = false;
                $res['cant_buy']['product_limit'][] = $safeguardConfigId;
                continue;
            }
            $res['can_buy'][] = $safeguardConfigId;
        }
        return $res;
    }

    /**
     * 获取同一rid最新的保障服务配置
     *
     * @param int $configRid
     * @param int $countryId buyer country id
     * @return SafeguardConfig|null
     */
    public function geiNewestConfig($configRid, $countryId)
    {
        if (!$configRid || !$countryId) {
            return null;
        }
        $cacheKey = [__CLASS__, __FUNCTION__, $configRid, $countryId];
        if ($configDetail = $this->getRequestCachedData($cacheKey)) {
            return $configDetail;
        }
        $configCountryDetail = SafeguardConfigCountry::query()
            ->with('config')
            ->where('country_id', $countryId)
            ->where('safeguard_config_rid', $configRid)
            ->orderByDesc('id')
            ->first();
        $configDetail = optional($configCountryDetail)->config;
        $this->setRequestCachedData($cacheKey, $configDetail);
        return $configDetail;
    }

    /**
     * 获取分组下 buyerids
     * @param array $groupIds
     * @param array $buyerIds
     * @return array
     */
    public function getSafeguardConfigBuyersByGroupIds(array $groupIds, array $buyerIds = [])
    {
        if (empty($groupIds) && empty($buyerIds)) {
            return [];
        }
        $allBuyerIds = [];
        if ($groupIds) {
            SafeguardBuyerGroup::query()
                ->whereIn('id', $groupIds)
                ->where('is_deleted', YesNoEnum::NO)
                ->get()
                ->map(function ($item) use (&$allBuyerIds) {
                    $allBuyerIds = array_merge($allBuyerIds, explode(',', $item->buyer_ids));
                });
        }

        return array_unique(array_merge($allBuyerIds, $buyerIds));
    }

}

