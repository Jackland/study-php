<?php

use App\Enums\FeeOrder\FeeOrderStatus;
use App\Enums\FeeOrder\StorageFeeEndType;
use App\Enums\Margin\MarginAgreementStatus;
use App\Enums\Pay\PayCode;
use App\Enums\Pay\VirtualPayType;
use App\Enums\Product\ProductTransactionType;
use App\Logging\Logger;
use App\Models\Customer\Customer;
use App\Models\FeeOrder\FeeOrderAgreementBalance;
use App\Models\Margin\MarginAgreement;
use App\Models\Margin\MarginProcess;
use App\Repositories\FeeOrder\StorageFeeRepository;
use App\Services\FeeOrder\FeeOrderService;
use App\Services\FeeOrder\StorageFeeCalculateService;
use App\Services\FeeOrder\StorageFeeService;

class ControllerApiStorageFee extends ControllerApiBase
{
    protected $calculateService;
    protected $storageFeeRepository;

    public function __construct(
        Registry $registry,
        StorageFeeCalculateService $calculateService,
        StorageFeeRepository $storageFeeRepository
    )
    {
        parent::__construct($registry);
        $this->calculateService = $calculateService;
        $this->storageFeeRepository = $storageFeeRepository;
    }

    // 按日计算
    public function calculateByDay()
    {
        set_time_limit(0);

        $validator = $this->request->validate([
            'c' => 'required|numeric',
            'd' => 'required|date',
            'f' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }

        $date = $this->request->get('d');
        $force = $this->request->get('f');
        $country = $this->request->get('c');

        try {
            $hasCalculated = $this->storageFeeRepository->checkHasCalculatedByCountry($country, $date);
            $newCalculated = false;
            if ($force || !$hasCalculated) {
                $this->calculateService->calculateByCountryOneDay($country, $date);
                $newCalculated = true;
            }
        } catch (Throwable $e) {
            Logger::storageFee([__CLASS__, __FUNCTION__, '接口失败', $e->getMessage()], 'error');
            Logger::storageFee($e, 'error');
            Logger::alarm('仓租接口调用失败', [__CLASS__, $e->getMessage(), '详见日志']);
            return $this->jsonFailed('接口调用失败');
        }

        return $this->jsonSuccess([
            'has_calculated' => $hasCalculated,
            'new_calculated' => $newCalculated,
        ]);
    }

    // 按采购单计算
    public function calculateByOrder()
    {
        set_time_limit(0);

        $validator = $this->request->validate([
            'o' => 'required|numeric',
            'd' => 'required|date',
            'f' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }

        $orderId = $this->request->get('o');
        $date = $this->request->get('d');
        $force = $this->request->get('f');

        try {
            $hasCalculated = $this->storageFeeRepository->checkHasCalculatedByOrder($orderId);
            $newCalculated = false;
            if ($force || !$hasCalculated) {
                $this->calculateService->calculateByOrder($orderId, $date);
                $newCalculated = true;
            }
        } catch (Throwable $e) {
            Logger::storageFee([__CLASS__, __FUNCTION__, '接口失败', $e->getMessage()], 'error');
            Logger::storageFee($e, 'error');
            Logger::alarm('仓租接口调用失败', [__CLASS__, $e->getMessage(), '详见日志']);
            return $this->jsonFailed('api error');
        }

        return $this->jsonSuccess([
            'has_calculated' => $hasCalculated,
            'new_calculated' => $newCalculated,
        ]);
    }

    // 按协议计算
    public function calculateByAgreement()
    {
        set_time_limit(0);

        $validator = $this->request->validate([
            'a' => 'required|numeric',
            'd' => 'required|date',
            't' => 'required|numeric',
            'f' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }

        $agreementId = $this->request->get('a');
        $date = $this->request->get('d');
        $type = $this->request->get('t');
        $force = $this->request->get('f');

        try {
            $hasCalculated = $this->storageFeeRepository->checkHasCalculatedByAgreement($type, $agreementId);
            $newCalculated = false;
            if ($force || !$hasCalculated) {
                $this->calculateService->calculateByAgreement($type, $agreementId, $date);
                $newCalculated = true;
            }
        } catch (Throwable $e) {
            Logger::storageFee([__CLASS__, __FUNCTION__, '接口失败', $e->getMessage()], 'error');
            Logger::storageFee($e, 'error');
            Logger::alarm('仓租接口调用失败', [__CLASS__, $e->getMessage(), '详见日志']);
            return $this->jsonFailed('api error');
        }

        return $this->jsonSuccess([
            'has_calculated' => $hasCalculated,
            'new_calculated' => $newCalculated,
        ]);
    }

    // 现货协议到期，未购买的尾款需要付仓租
    public function payMarginStorageFee()
    {
        $validator = $this->request->validate([
            'agreement_id' => 'required|numeric',
            'qty' => 'required|numeric|min:1',
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }
        $agreementId = $this->request->get('agreement_id');
        $qty = $this->request->get('qty');
        $db = db()->getConnection();
        $success = true;
        $errorMsg = '';
        try {
            $db->beginTransaction();
            Logger::storageFee("-------------现货到期支付仓租开始({$agreementId}:{$qty})---------------");
            // 先获取头款的支付方式
            $marginAgreement = MarginAgreement::findOrFail($agreementId);
            if ($marginAgreement->status != MarginAgreementStatus::TERMINATED) {
                // 协议必须到期
                throw new Exception('现货支付仓租失败，协议未违约');
            }
            $marginProcess = MarginProcess::query()->where('margin_id', $agreementId)->first();
            if (!$marginProcess || !($marginProcess->advanceOrder)) {
                throw new Exception('现货支付仓租失败，无法获取头款订单');
            }
            $buyerId = $marginAgreement->buyer_id;
            $customer = Customer::query()->findOrFail($buyerId);
            // 获取协议剩余尾款库存
            $storageFees = app(StorageFeeRepository::class)
                ->getAgreementRestStorageFee(ProductTransactionType::MARGIN, $agreementId, $qty);
            if ($storageFees->isEmpty()) {
                throw new Exception('现货支付仓租失败，仓租不存在');
            }
            Logger::storageFee('仓租', 'info', [
                Logger::CONTEXT_VAR_DUMPER => $storageFees->toArray(),
            ]);
            // 创建费用单
            $feeOrder = app(FeeOrderService::class)->createMarginFeeOrder($buyerId, $marginProcess->advanceOrder->order_id, $storageFees);
            if (!$feeOrder) {
                throw new Exception('现货支付仓租失败，创建费用单失败');
            }
            Logger::storageFee('费用单', 'info', [
                Logger::CONTEXT_VAR_DUMPER => $feeOrder->toArray(),
            ]);
            if ($feeOrder->fee_total > 0) {
                // 支付费用单
                // 根据头款订单的支付方式获取本次需要使用的支付方式,除了虚拟支付，都用余额支付
                $paymentCode = $marginProcess->advanceOrder->payment_code == PayCode::PAY_VIRTUAL ? PayCode::PAY_VIRTUAL : PayCode::PAY_LINE_OF_CREDIT;
                $paymentMethod = PayCode::getDescription($paymentCode);
                // 余额够或者使用虚拟支付
                /** @var \ModelCheckoutOrder $modelCheckoutOrder */
                $modelCheckoutOrder = load()->model('checkout/order');
                // 修改费用单支付方式
                $feeOrderIds = [$feeOrder->id];
                $modelCheckoutOrder->updateFeeOrderPayment($feeOrderIds, $paymentCode, $paymentMethod);
                // 支付逻辑
                if ($paymentCode == PayCode::PAY_LINE_OF_CREDIT) {
                    if ($customer->line_of_credit < $feeOrder->fee_total) {
                        // 余额不足写入销售订单
                        $errorMsg = "余额不足,需要:{$feeOrder->fee_total},当前:{$customer->line_of_credit}";
                        Logger::storageFee($errorMsg);
                        // 能扣多少扣多少
                        $feeOrder->update(['balance' => $customer->line_of_credit]);
                        // 不足的记账，记录少扣的钱
                        FeeOrderAgreementBalance::create([
                            'agreement_id' => $agreementId,
                            'fee_order_id' => $feeOrder->id,
                            'balance' => $customer->line_of_credit,
                            'need_pay' => $feeOrder->fee_total,
                            'buyer_id' => $buyerId,
                            'seller_id' => $marginAgreement->seller_id
                        ]);
                    }
                    $modelCheckoutOrder->payByLineOfCredit(null, $feeOrderIds, $buyerId);
                } else {
                    /** @var \ModelAccountBalanceVirtualPayRecord $modelAccountBalanceVirtualPayRecord */
                    $modelAccountBalanceVirtualPayRecord = load()->model('account/balance/virtual_pay_record');
                    $modelAccountBalanceVirtualPayRecord->insertData($buyerId, $feeOrder->id, $feeOrder->fee_total, VirtualPayType::STORAGE_FEE);
                }
            }
            // 支付成功，标记订单为已完成
            app(FeeOrderService::class)->changeFeeOrderStatus($feeOrder, FeeOrderStatus::COMPLETE);
            // 将仓租完结
            app(StorageFeeService::class)->completeByStorageFeeIds($storageFees->pluck('id')->toArray(), StorageFeeEndType::MARGIN_TERMINATED);
           $db->commit();
        } catch (\Exception $exception) {
            $db->rollBack();
            $success = false;
            $errorMsg = $exception->getMessage();
            Logger::storageFee($exception->getMessage());
        }
        Logger::storageFee('-------------现货到期支付仓租结束---------------');
        if ($success) {
            return $this->jsonSuccess('支付成功');
        } else {
            return $this->jsonFailed($errorMsg);
        }

    }
}
