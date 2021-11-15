<?php

namespace App\Services\SalesOrder\AutoPurchase;

use App\Components\Traits\RequestCachedDataTrait;
use App\Enums\Common\YesNoEnum;
use App\Enums\FeeOrder\FeeOrderStatus;
use App\Enums\Pay\PayCode;
use App\Enums\Pay\VirtualPayType;
use App\Enums\Safeguard\SafeguardSalesOrderErrorLogType;
use App\Logging\Logger;
use App\Models\Customer\Customer;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Safeguard\SafeguardAutoBuyPlan;
use App\Models\Safeguard\SafeguardAutoBuyPlanDetail;
use App\Models\Safeguard\SafeguardConfig;
use App\Models\Safeguard\SafeguardConfigCountry;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Repositories\FeeOrder\FeeOrderRepository;
use App\Repositories\Safeguard\SafeguardAutoBuyPlanRepository;
use App\Repositories\Safeguard\SafeguardConfigRepository;
use App\Repositories\SalesOrder\AutoBuyRepository;
use App\Services\FeeOrder\FeeOrderService;
use App\Services\Safeguard\SafeguardSalesOrderErrorLogService;
use Exception;
use Illuminate\Support\Collection;
use Throwable;

/**
 * 自动购买 购买保障服务
 *
 * Class SafeguardComponent
 * @package App\Services\SalesOrder\AutoPurchase
 */
class SafeguardComponent
{
    use RequestCachedDataTrait;

    protected $salesOrderId;

    public function __construct(int $salesOrderId)
    {
        $this->salesOrderId = $salesOrderId;
    }

    /**
     * 7. 自动购买保障服务逻辑：
     * （V1.2）对于自动购买账号，系统自动投保时首先看当前用户是否有正在生效未终止的自动投保方案
     * 如果有，再看自动投保方案的最晚终止时间是什么，是否已经是过去的时间。
     * （V1.2）如果不是过去的时间再看当前时间是否处于某段时间段内。
     * 如果在，当前要购买保障服务的订单是否在方案中设置的订单范围内
     * 如果是，看用户配置的自动购买的保障服务当前对这个订单是否可购买：即对当前用户可见/可购买且当前订单也能达到投保条件
     * 满足全部条件，就可以为订单购买保障服务了！在系统自动购买商品成功后自动扣除保费，生成费用单和保单。
     * 如果出现不够扣的情况，不像仓租一样记账，因为这个金额可能会比较多，默认不购买保障服务，也就是不投保。会在销售订单列表标识出来哪些没有购买成功。（V1.2）也不生成费用单和保单。
     * 仓租功能涉及自动购买的逻辑要确认一下是不是还有bug，因为自动购买保障服务和自动支付仓租的逻辑很像，保费是一定不能不收的，不收的话会损失很多钱！
     */
    /**
     * @return array|string[]
     * @throws Exception
     */
    /**
     * @return bool
     * @throws Exception
     */
    public function handle(): bool
    {
        $logStr = "自动购买保障服务{$this->salesOrderId}";
        Logger::autoPurchase($logStr . '开始购买');
        $salesOrder = CustomerSalesOrder::find($this->salesOrderId);
        // 获取可购买的保障服务
        $activePlans = $this->getActivePlan($salesOrder->buyer_id);
        if ($activePlans->isEmpty()) {
            Logger::autoPurchase($logStr . '失败:没有可用的自动购买配置');
            return false;
        }
        $db = db()->getConnection();
        $db->beginTransaction();
        try {
            // 购买
            $this->buySafeguardFeeOrder($salesOrder, $activePlans);
            $db->commit();
            Logger::autoPurchase($logStr . '成功');
            return true;
        } catch (Throwable $throwable) {
            // 如果失败，记录失败原因
            $db->rollBack();
            if ($throwable->getCode() === 101) {
                app(SafeguardSalesOrderErrorLogService::class)
                    ->addLog($salesOrder->id, SafeguardSalesOrderErrorLogType::INSUFFICIENT_BALANCE, $throwable->getMessage());
            }
            Logger::autoPurchase($logStr . '失败:' . $throwable->getMessage());
            return false;
        }
    }

    /**
     * @param CustomerSalesOrder $salesOrder
     * @param Collection $activePlans
     * @return bool
     * @throws \Exception code(101)是余额不足
     */
    private function buySafeguardFeeOrder(CustomerSalesOrder $salesOrder, $activePlans)
    {
        if ($activePlans->isEmpty()) {
            throw new \Exception('未选择购买保障服务');
        }
        $safeguardConfigIds = [];
        foreach ($activePlans as $activePlan) {
            if ($activePlan->safeguard_config_id) {
                $safeguardConfigIds = array_merge($safeguardConfigIds, explode(',', $activePlan->safeguard_config_id));
            }
        }
        $safeguardConfigIds = array_filter(array_unique($safeguardConfigIds));
        if (empty($safeguardConfigIds)) {
            throw new \Exception('方案内没有选择保障服务,方案detail id:' . $activePlans->implode('id', ','));
        }
        $customer = Customer::query()->findOrFail($salesOrder->buyer_id);
        // 查询出该保障服务最新的config id
        $configRids = SafeguardConfig::query()->whereIn('id',$safeguardConfigIds)->get(['rid'])->pluck('rid')->toArray();
        $safeguardConfigIds = SafeguardConfigCountry::query()->whereIn('safeguard_config_rid', $configRids)
            ->where('country_id', $customer->country_id)
            ->where('status', YesNoEnum::YES)
            ->get(['safeguard_config_id'])
            ->pluck('safeguard_config_id')->toArray();
        // 判断是否可以购买
        $canBuyConfigs = app(SafeguardConfigRepository::class)->checkCanBuySafeguardBuSalesOrder($salesOrder, $safeguardConfigIds);
        Logger::autoPurchase(json_encode($canBuyConfigs));
        if (empty($canBuyConfigs['can_buy'])) {
            throw new \Exception('当前方案内的保障服务均不可购买');
        }
        $safeguardConfigIds = $canBuyConfigs['can_buy'];
        // 获取费用单总金额以及明细数据
        $safeguardConfigList = SafeguardConfig::query()->whereIn('id', $safeguardConfigIds)->get();

        list($feeTotal) = app(FeeOrderRepository::class)->calculateSafeguardFeeOrderData($salesOrder, $safeguardConfigList);
        if ($feeTotal === false) {
            throw new \Exception('费用单金额计算失败');
        }
        // 获取支付方式
        list($paymentCode,$paymentMethod) = app(AutoBuyRepository::class)->getPaymentCodeAndMethod();
        if ($feeTotal > 0 && $paymentCode === PayCode::PAY_LINE_OF_CREDIT) {
            // 费用大于0且使用余额支付
            // 判断用户余额是否够扣
            $credit = $customer->line_of_credit;
            if ($credit < $feeTotal) {
                // 余额不足写入销售订单
                throw new Exception("余额不足,需要:{$feeTotal},当前:{$credit}", 101);
            }
        }
        // 余额充足创建费用单
        $feeOrderRunId = app(FeeOrderService::class)->generateFeeOrderRunId();
        $feeOrderData = app(FeeOrderService::class)->createSafeguardFeeOrder([$salesOrder->id => $safeguardConfigIds], $feeOrderRunId);
        if (!empty($feeOrderData['need_pay_fee_order_list'])) {
            $feeOrderId = $feeOrderData['need_pay_fee_order_list'][0];// 一个销售单只会有一个费用单
            Logger::autoPurchase('自动购买保障服务开始付款，费用单ID：' . $feeOrderId);
            // 费用单支付
            /** @var \ModelCheckoutOrder $modelCheckoutOrder */
            $modelCheckoutOrder = load()->model('checkout/order');
            // 修改费用单支付方式
            $feeOrder = FeeOrder::query()->findOrFail($feeOrderId);
            $modelCheckoutOrder->updateFeeOrderPayment($feeOrderData['need_pay_fee_order_list'], $paymentCode, $paymentMethod);
            // 支付逻辑
            if (PayCode::PAY_VIRTUAL == $paymentCode) {
                /** @var \ModelAccountBalanceVirtualPayRecord $modelAccountBalanceVirtualPayRecord */
                $modelAccountBalanceVirtualPayRecord = load()->model('account/balance/virtual_pay_record');
                $modelAccountBalanceVirtualPayRecord->insertData($salesOrder->buyer_id, $feeOrder->id, $feeOrder->fee_total, VirtualPayType::SAFEGUARD_PAY);
            } else {
                // 组合支付的时候
                $modelCheckoutOrder->payByLineOfCredit(null, $feeOrderData['need_pay_fee_order_list'], $salesOrder->buyer_id);
            }
            app(FeeOrderService::class)->changeFeeOrderStatus($feeOrderId, FeeOrderStatus::COMPLETE);
        } else {
            Logger::autoPurchase('自动购买保障服务无需支付，费用单信息：' . json_encode($feeOrderData['fee_order_list']));
        }
        return true;
    }

    /**
     * @param int $customerId
     * @return SafeguardAutoBuyPlanDetail[]|Collection
     */
    private function getActivePlan(int $customerId)
    {
        $cacheKey = [__CLASS__, __FUNCTION__, $customerId];
        $activePlans = $this->getRequestCachedData($cacheKey);
        if ($activePlans !== null) {
            return $activePlans;
        }
        $activePlans = app(SafeguardAutoBuyPlanRepository::class)->getAvailablePlan($customerId);
        if ($activePlans) {
            $this->setRequestCachedData($cacheKey, $activePlans);
        }
        return $activePlans;
    }
}
