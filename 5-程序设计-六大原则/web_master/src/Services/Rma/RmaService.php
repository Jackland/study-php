<?php

namespace App\Services\Rma;

use App\Components\UniqueGenerator\Enums\ServiceEnum;
use App\Enums\FeeOrder\FeeOrderStatus;
use App\Enums\Pay\PayCode;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\YzcRmaOrder\RmaRefundStatus;
use App\Enums\YzcRmaOrder\RmaStatus;
use App\Models\FeeOrder\FeeOrder;
use App\Models\FeeOrder\FeeOrderRmaBalance;
use App\Models\Pay\LineOfCreditRecord;
use App\Repositories\Common\SerialNumberRepository;
use App\Services\FeeOrder\FeeOrderService;
use App\Services\FeeOrder\StorageFeeService;
use Carbon\Carbon;
use Framework\App;
use Framework\Exception\Exception;
use ModelAccountBalanceRecharge;
use ModelAccountBalanceVirtualPayRecord;
use App\Enums\YzcRmaOrder\RmaType;

class RmaService
{
    /**
     * @var string $country
     */
    private $country;

    public function __construct()
    {
        $this->country = session('country', 'USA');
    }

    /**
     *  rma申请返金
     * @param int $rmaId
     * @return null
     * @throws \Exception
     */
    public function applyStorageFee(int $rmaId)
    {
        bcscale(2);
        $feeOrderId = app(FeeOrderService::class)->createRmaFeeOrder($rmaId);
        if (!$feeOrderId) {
            return null;
        }
        $feeOrder = FeeOrder::find($feeOrderId);
        // 支付仓租
        app(FeeOrderService::class)
            ->changeFeeOrderStatus(
                $feeOrder,
                FeeOrderStatus::COMPLETE,
                function (FeeOrder $feeOrder) use ($rmaId) {
                    // 完成仓租明细
                    app(StorageFeeService::class)->completeByRMA($feeOrder);
                    // 支付逻辑确认
                    if ($feeOrder->fee_total == 0) {
                        // 对于0费用单需要隐藏该费用单的存在
                        // 由于未rma的0费用 这边的支付方式是没有意义的
                        // why:因为就没有发生支付
                        $feeOrder->is_show = 0;
                        $feeOrder->save();
                        return;
                    }
                    // 付款方式
                    $payType = $this->refundType($rmaId);
                    switch ($payType) {
                        case 4:
                        {
                            $this->virtualPay($feeOrder);    // 虚拟支付
                            break;
                        }
                        case 1:
                        {
                            $this->lineOfCreditPay($feeOrder);  // 信用额度支付
                        }
                    }
                }
            );
    }

    /**
     * 费用单虚拟支付 只是单纯的记录功能
     * @param FeeOrder $feeOrder
     * @throws \Exception
     */
    private function virtualPay(FeeOrder $feeOrder)
    {
        /** @var ModelAccountBalanceVirtualPayRecord $model */
        $model = load()->model('account/balance/virtual_pay_record');
        $model->insertData($feeOrder->buyer_id, $feeOrder->id, $feeOrder->fee_total, 4);
        // 更新feeOrder
        $feeOrder->payment_code = PayCode::PAY_VIRTUAL;
        $feeOrder->payment_method = PayCode::getDescriptionWithPoundage($feeOrder->payment_code);
        $feeOrder->save();
    }

    /**
     * 信用额度支付
     * @param FeeOrder $feeOrder
     * @throws \Exception
     */
    private function lineOfCreditPay(FeeOrder $feeOrder)
    {
        bcscale(2);
        /** @var ModelAccountBalanceRecharge $model */
        $model = load()->model('account/balance/recharge');
        // buyer现在的信用额度
        $buyer = $feeOrder->buyer;
        $lineOfCreditNow = (float)$buyer->line_of_credit;
        // buyer需要支付的仓租费
        $plusFee = (float)$feeOrder->fee_total;
        $cRes = bccomp($lineOfCreditNow, $plusFee);
        if ($cRes === -1) {
            // 信用额度不够扣时不扣信用额度，仅记录 oc_fee_order_rma_balance，然后发送邮件
            $feeBalance = new FeeOrderRmaBalance();
            $feeBalance->rma_id = $feeOrder->order_id;
            $feeBalance->fee_order_id = $feeOrder->id;
            $feeBalance->balance = 0;
            $feeBalance->need_pay = $feeOrder->fee_total;
            $feeBalance->buyer_id = $feeOrder->buyer_id;
            $feeBalance->seller_id = $feeOrder->orderInfo->seller_id;
            $feeBalance->save();
            // 更新feeOrder
            $feeOrder->balance = 0;
            $feeOrder->payment_code = PayCode::PAY_LINE_OF_CREDIT;
            $feeOrder->payment_method = PayCode::getDescriptionWithPoundage($feeOrder->payment_code);
            $feeOrder->save();
            // 只发送邮件 不考虑其他 不校验是否发生错误
            post_url(URL_TASK_WORK . 'api/feeOrder/balance', ['id' => $feeOrder->id]);
        } else {
            $newLineOfCredit = round($lineOfCreditNow - $plusFee, 2);
            // 更新feeOrder
            $feeOrder->balance = $plusFee;
            $feeOrder->payment_code = PayCode::PAY_LINE_OF_CREDIT;
            $feeOrder->payment_method = PayCode::getDescriptionWithPoundage( $feeOrder->payment_code);
            $feeOrder->save();
            // 更新用户表 信用额度减少
            $buyer->line_of_credit = $newLineOfCredit;
            $buyer->save();
            // 信用额度记录表插入一条记录
            $lineOfCreditRecord = new LineOfCreditRecord();
            $lineOfCreditRecord->serial_number = SerialNumberRepository::getDateSerialNumber(ServiceEnum::AMENDMENT_RECORD_NO);
            $lineOfCreditRecord->customer_id = $feeOrder->buyer_id;
            $lineOfCreditRecord->old_line_of_credit = $lineOfCreditNow;
            $lineOfCreditRecord->new_line_of_credit = $newLineOfCredit;
            $lineOfCreditRecord->date_added = Carbon::now();
            $lineOfCreditRecord->operator_id = 0;
            $lineOfCreditRecord->type_id = 11; // 11 表示仓租费用
            $lineOfCreditRecord->header_id = $feeOrder->id;
            $lineOfCreditRecord->memo = ' Storage Fee, for Expense ID: ' . $feeOrder->order_no;
            $lineOfCreditRecord->save();
        }
    }

    /**
     * 退款路径 CL 1、退到余额，4、退到虚拟账户
     * @param int $rmaId
     * @return int
     */
    private function refundType(int $rmaId)
    {
        $payment = App::orm()->table('oc_yzc_rma_order as r')
            ->leftJoin('oc_order as o', 'o.order_id', '=', 'r.order_id')
            ->where('id', '=', $rmaId)
            ->value('payment_code');
        if (PayCode::PAY_VIRTUAL == $payment) {
            return 4;
        } else {
            return 1;
        }
    }

}
