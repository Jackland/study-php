<?php

namespace App\Console\Commands;

use App\Models\Margin\MarginProcess;
use App\Models\Margin\ProductLock;
use App\Models\Margin\SendMarginMessage;
use App\Traits\CommandLoggerTrait;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Margin\MarginAgreementLog;
use App\Models\Margin\MarginAgreementStatus;
use Throwable;

class MarginTask extends Command
{
    use CommandLoggerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'margin:online {type?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '保证金相关定时任务的集合';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $type = $this->argument('type');
        $types = ['approve_timeout', 'deposit_pay_timeout', 'sold_will_expire', 'dispatch_message', 'sold_expire'];
        $type = (empty($type) || !in_array($type, $types)) ? 'approve_timeout' : $type;
        switch ($type) {
            case 'approve_timeout':
                $this->approveTimeout();
                break;
            case 'deposit_pay_timeout':
                $this->depositPayTimeout();
                break;
            case 'sold_will_expire':
                $this->soldWillExpire();
                break;
            case 'dispatch_message':
                $this->sendDispatchRecordMessage();
                break;
            case 'sold_expire':
                $this->soldExpire();
                break;
            default:
                break;
        }
        return;
    }

    /**
     * seller审批超时定时任务处理
     */
    public function approveTimeout()
    {
        $marginProcess = new MarginProcess();
        $results = $marginProcess->getApproveTimeoutMarginDetail();
        if (count($results)) {
            $message_sender = new SendMarginMessage();
            foreach ($results as $result) {
                //保证金合同更新超时
                $update = [];
                if (!empty($result->id)) {
                    $update['status'] = 5;
                    $update['update_user'] = '0';
                    $update['update_time'] = date('Y-m-d H:i:s', time());
                    $affectRows = $marginProcess->updateMarginInformation($result->id, $update);
                    if ($affectRows) {
                        //现货四期 协议变更日志记录
                        if ($result->status == 1) {
                            $logType = MarginAgreementStatus::APPLIED_TO_FAILED;
                            $content = MarginAgreementStatus::getDescription(MarginAgreementStatus::APPLIED) . '->'
                                . MarginAgreementStatus::getDescription(MarginAgreementStatus::TIME_OUT);
                        } else {
                            $logType = MarginAgreementStatus::PENDING_TO_FAILED;
                            $content = MarginAgreementStatus::getDescription(MarginAgreementStatus::PENDING) . '->'
                                . MarginAgreementStatus::getDescription(MarginAgreementStatus::TIME_OUT);
                        }
                        MarginAgreementLog::query()->firstOrCreate(['agreement_id' => $result->id, 'type' => $logType],
                            [
                                'customer_id' => 0,
                                'content' => json_encode(['agreement_status' => $content]),
                                'operator' => 'system',
                                'create_time' => Carbon::now(),
                                'update_time' => Carbon::now(),
                            ]);

                        Log::info('margin timeout status update success(approve_timeout):' . json_encode($update) . PHP_EOL);
                        //发送合同超时通知
                        $send_flag = $message_sender->sendApproveTimeoutToSeller($result);
                        if ($send_flag) {
                            Log::info('margin timeout message to seller success(approve_timeout):' . json_encode($result) . PHP_EOL);
                        } else {
                            Log::info('margin timeout message to seller failed(approve_timeout):' . json_encode($result) . PHP_EOL);
                        }
                        $send_flag = $message_sender->sendApproveTimeoutToBuyer($result);
                        if ($send_flag) {
                            Log::info('margin timeout message to buyer success(approve_timeout):' . json_encode($result) . PHP_EOL);
                        } else {
                            Log::info('margin timeout message to buyer failed(approve_timeout):' . json_encode($result) . PHP_EOL);
                        }
                    }
                }
            }
            echo date('Y-m-d H:i:s') . ' margin:online approve_timeout success' . PHP_EOL;
        }
    }

    /**
     * buyer支付订金超时定时任务处理
     */
    public function depositPayTimeout()
    {
        $marginProcess = new MarginProcess();
        $results = $marginProcess->getDepositPayTimeoutMarginDetail();
        if (count($results)) {
            $message_sender = new SendMarginMessage();
            $ProductLock = new ProductLock();
            foreach ($results as $result) {
                //保证金合同更新超时
                $update = [];
                if (!empty($result->id) && !empty($result->advance_product_id)) {
                    $update['status'] = 5;
                    $update['update_user'] = '0';
                    $update['update_time'] = date('Y-m-d H:i:s', time());
                    $affectRows = $marginProcess->updateMarginInformation($result->id, $update);
                    if ($affectRows) {
                        //现货四期 协议变更日志记录
                        $logType = MarginAgreementStatus::ADVANCED_PRODUCT_PAY_FAILED;
                        $content = MarginAgreementStatus::getDescription(MarginAgreementStatus::APPROVED) . '->'
                            . MarginAgreementStatus::getDescription(MarginAgreementStatus::TIME_OUT);

                        MarginAgreementLog::query()->firstOrCreate(['agreement_id' => $result->id, 'type' => $logType],
                            [
                                'customer_id' => 0,
                                'content' => json_encode(['agreement_status' => $content]),
                                'operator' => 'system',
                                'create_time' => Carbon::now(),
                                'update_time' => Carbon::now(),
                            ]);

                        Log::info('margin timeout status update success(deposit_pay_timeout):'
                            . 'margin_id:' . $result->id
                            . 'param:' . json_encode($update) . PHP_EOL);
                        //发送合同超时通知
                        $send_flag = $message_sender->sendDepositPayTimeoutToSeller($result);
                        if ($send_flag) {
                            Log::info('margin timeout message to seller success(deposit_pay_timeout):' . json_encode($result) . PHP_EOL);
                        } else {
                            Log::info('margin timeout message to seller failed(deposit_pay_timeout):' . json_encode($result) . PHP_EOL);
                        }
                        $send_flag = $message_sender->sendDepositPayTimeoutToBuyer($result);
                        if ($send_flag) {
                            Log::info('margin timeout message to buyer success(deposit_pay_timeout):' . json_encode($result) . PHP_EOL);
                        } else {
                            Log::info('margin timeout message to buyer failed(deposit_pay_timeout):' . json_encode($result) . PHP_EOL);
                        }
                    }
                    //下线订金商品
                    $update = [];
                    $update['status'] = 0;
                    $update['quantity'] = 0;
                    $update['is_deleted'] = 1;
                    $affectRows = $marginProcess->updateMarginProduct($result->advance_product_id, $update);
                    if ($affectRows) {
                        Log::info('margin deposit product update success(deposit_pay_timeout):'
                            . 'advance_product_id:' . $result->advance_product_id
                            . 'param:' . json_encode($update) . PHP_EOL);
                    }
                }
                $futureId = $marginProcess->future2Margin($result->id);
                if ($futureId) {
                    $ProductLock->TailOut($result->id, $result->num, $futureId, ProductLock::LOCK_TAIL_CANCEL);
                }

            }
            echo date('Y-m-d H:i:s') . ' margin:online deposit_pay_timeout success' . PHP_EOL;
        }
    }

    /**
     * buyer尾款采购不足，协议即将到期定时任务处理
     */
    public function soldWillExpire()
    {
        $marginProcess = new MarginProcess();
        $results = $marginProcess->getSoldWillExpireMarginDetail();
        if (count($results)) {
            $message_sender = new SendMarginMessage();
            foreach ($results as $result) {
                $result->completed_qty = $marginProcess->getMarginCompletedCount($result->id, $result->seller_id);
                //发送合同超时通知
                $send_flag = $message_sender->sendSoldWillExpireToSeller($result);
                if ($send_flag) {
                    Log::info('margin sold will expire message to seller success(sold_will_expire):' . json_encode($result) . PHP_EOL);
                } else {
                    Log::info('margin sold will expire message to seller failed(sold_will_expire):' . json_encode($result) . PHP_EOL);
                }
                $send_flag = $message_sender->sendSoldWillExpireToBuyer($result);
                if ($send_flag) {
                    Log::info('margin sold will expire message to buyer success(sold_will_expire):' . json_encode($result) . PHP_EOL);
                } else {
                    Log::info('margin sold will expire message to buyer failed(sold_will_expire):' . json_encode($result) . PHP_EOL);
                }
            }
            echo date('Y-m-d H:i:s') . ' margin:online sold_will_expire success' . PHP_EOL;
        }
    }

    /**
     * buyer支付订金超时定时任务处理--调货回原店铺
     */
    public function sendDispatchRecordMessage()
    {
        $marginProcess = new MarginProcess();
        $results = $marginProcess->getDispatchRecord();
        if (count($results)) {
            $message_sender = new SendMarginMessage();
            foreach ($results as $result) {
                //发送合同超时通知
                $send_flag_s = $message_sender->sendDispatchMessageToSeller($result);
                if ($send_flag_s) {
                    Log::info('margin dispatch message to seller success(dispatch_message):' . json_encode($result) . PHP_EOL);
                } else {
                    Log::info('margin dispatch message to seller failed(dispatch_message):' . json_encode($result) . PHP_EOL);
                }
                $send_flag_b = $message_sender->sendDispatchMessageToBuyer($result);
                if ($send_flag_b) {
                    Log::info('margin dispatch message to buyer success(dispatch_message):' . json_encode($result) . PHP_EOL);
                } else {
                    Log::info('margin dispatch message to buyer failed(dispatch_message):' . json_encode($result) . PHP_EOL);
                }
                if ($send_flag_s && $send_flag_b) {
                    $update['message_send'] = 1;
                    $marginProcess->updateMarginDispatch($result->dispatch_id, $update);
                }
            }
            echo date('Y-m-d H:i:s') . ' margin:online dispatch_message success' . PHP_EOL;
        }
    }

    /**
     * 协议到达完成日期后，协议数量未完成，则判定Buyer违约, 释放协议的Seller锁定库存
     * @throws \Exception
     */
    private function soldExpire()
    {
        $marginProcess = new MarginProcess();
        $soleExpireAgreements = $marginProcess->getSoleExpireAgreements();
        if ($soleExpireAgreements->isEmpty()) {
            return;
        }

        $message_sender = new SendMarginMessage();
        foreach ($soleExpireAgreements as $soleExpireAgreement) {
            // 确认不会在处理任务时，buyer完成协议
            if (DB::table('tb_sys_margin_agreement')
                ->where('id', $soleExpireAgreement->id)
                ->where('status', 6)
                ->where('expire_time', '<', date('Y-m-d H:i:s', time()))
                ->doesntExist()) {
                continue;
            }

            DB::beginTransaction();
            // 退库存
            $qty = 0;
            try {
                // 更新协议状态
                $marginProcess->updateMarginInformation($soleExpireAgreement->id, [
                    'status' => 10, // buyer违约
                    'update_user' => '0',
                    'update_time' => date('Y-m-d H:i:s', time()),
                ]);

                //现货四期 协议变更日志记录
                $logType = MarginAgreementStatus::BUYER_FAILED;
                $content = MarginAgreementStatus::getDescription(MarginAgreementStatus::TO_BE_PAID) . '->'
                    . MarginAgreementStatus::getDescription(MarginAgreementStatus::DEFAULT);

                MarginAgreementLog::query()->firstOrCreate(['agreement_id' => $soleExpireAgreement->id, 'type' => $logType],
                    [
                        'customer_id' => 0,
                        'content' => json_encode(['agreement_status' => $content]),
                        'operator' => 'system',
                        'create_time' => Carbon::now(),
                        'update_time' => Carbon::now(),
                    ]);

                //  写入消息
                DB::table('tb_sys_margin_message')->insert([
                    'margin_agreement_id' => $soleExpireAgreement->id,
                    'customer_id' => -1, // 展示为System
                    'message' => "The margin agreement ({$soleExpireAgreement->agreement_id}) has been defaulted.",
                    'create_time' => date('Y-m-d H:i:s'),
                    'memo' => 'Agreement Defaulted',
                ]);

                if ($soleExpireAgreement->combo_flag == 0) {
                    $qty = DB::table('oc_product_lock')->where([
                        ['product_id', '=', $soleExpireAgreement->product_id],
                        ['type_id', '=', 2],
                        ['agreement_id', $soleExpireAgreement->id],
                    ])->value('qty');
                } else {
                    $productLock = DB::table('oc_product_lock')->where([
                        ['parent_product_id', '=', $soleExpireAgreement->product_id],
                        ['type_id', '=', 2],
                        ['agreement_id', $soleExpireAgreement->id],
                    ])->first();
                    if ($productLock) {
                        $qty = intval(bcdiv($productLock->qty, $productLock->set_qty));
                    }
                }
                if ($qty > 0) {
                    ProductLock::TailOut($soleExpireAgreement->id, $qty, $soleExpireAgreement->id, ProductLock::LOCK_TAIL_CANCEL);
                }

                $soleExpireAgreement->completed_qty = $marginProcess->getMarginCompletedCount($soleExpireAgreement->id, $soleExpireAgreement->seller_id);
                $message_sender->sendSoldExpireToBuyer($soleExpireAgreement);

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                echo "The margin agreement ({$soleExpireAgreement->agreement_id}) has been defaulted result: ERROR " . $e->getMessage() . PHP_EOL;
            }
            // 处理支付仓租
            if ($qty > 0) {
                $apiUrl = config('app.b2b_url') . 'api/storage_fee/payMarginStorageFee';
                $requestData = [
                    'agreement_id' => $soleExpireAgreement->id,
                    'qty' => $qty
                ];
                $apiUrl .= '&' . http_build_query($requestData);
                $this->logger(['request' => $apiUrl]);
                try {
                    $context = [
                        'http' => [
                            'timeout' => 30 * 60,
                        ]
                    ];
                    $response = file_get_contents($apiUrl, false, stream_context_create($context));
                } catch (Throwable $e) {
                    $this->logger(['error' => $e->getMessage()], 'error');
                    continue;
                }
                $this->logger(['response' => $response]);
            }
        }
        echo date('Y-m-d H:i:s') . ' margin:online sold_expire success' . PHP_EOL;
    }

}
