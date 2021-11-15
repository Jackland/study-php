<?php
/**
 * Created by PHPSTORM.
 * User: yaopengfei
 * Date: 2020/8/18
 * Time: 15:17
 */

namespace App\Console\Commands;

use App\Models\Credit\CreditBill;
use App\Models\Future\Agreement;
use App\Models\Future\AgreementMargin;
use App\Models\Future\Contract;
use App\Models\Future\FuturesContractLog;
use App\Models\Future\FuturesContractMarginPayRecord;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FutureContract extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'future:contract';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'future contract delivery timeout';

    /**
     * @var Contract
     */
    protected $contract;

    /**
     * @var FuturesContractMarginPayRecord
     */
    protected $futuresContractMarginPayRecord;

    /**
     * @var FuturesContractLog
     */
    protected $futuresContractLog;

    /**
     * Create a new command instance.
     *
     * @param Contract $contract
     * @param FuturesContractMarginPayRecord $futuresContractMarginPayRecord
     * @param FuturesContractLog $futuresContractLog
     */
    public function __construct(Contract $contract, FuturesContractMarginPayRecord $futuresContractMarginPayRecord, FuturesContractLog $futuresContractLog)
    {
        parent::__construct();

        $this->contract = $contract;
        $this->futuresContractMarginPayRecord = $futuresContractMarginPayRecord;
        $this->futuresContractLog = $futuresContractLog;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        echo date("Y-m-d H:i:s", time()) . ' ------exec-start------' . PHP_EOL;

        $deliveryTimeoutContracts = $this->contract->deliveryTimeoutContracts();

        foreach ($deliveryTimeoutContracts as $deliveryTimeoutContract) {
            /** @var Contract $deliveryTimeoutContract */

            $day = Agreement::getLeftDay($deliveryTimeoutContract->delivery_date, $deliveryTimeoutContract->seller->country_id);
            if ($day > 0) {
                continue;
            }

            $firstFuturesContractMarginPayRecord = $deliveryTimeoutContract->firstFuturesContractMarginPayRecord;
            if (!empty($firstFuturesContractMarginPayRecord) && $firstFuturesContractMarginPayRecord->type == FuturesContractMarginPayRecord::SELLER_COLLATERAL) {
                $firstFuturesContractMarginPayRecord->type = FuturesContractMarginPayRecord::SELLER_BILL;
            }
            $operator = 'System';

            $amount =  AgreementMargin::agreementSellerUnitAmountByContractIdAndStatus($deliveryTimeoutContract->id, [3], $deliveryTimeoutContract->seller->country_id == Agreement::JAPAN_COUNTRY_ID ? 0 : 2);
            $backAmount = $deliveryTimeoutContract->available_balance - $amount;

            try {
                \DB::beginTransaction();
                $collateralBalance = 0;
                if ($backAmount > 0 && !empty($firstFuturesContractMarginPayRecord)) {
                    if ($firstFuturesContractMarginPayRecord->type == 1) {
                        CreditBill::addCreditBill($deliveryTimeoutContract->seller_id, $backAmount, 2);
                    }

                    // 处理应收款中包含抵押物的情况
                    if ($deliveryTimeoutContract->collateral_balance > 0 && $firstFuturesContractMarginPayRecord->type == FuturesContractMarginPayRecord::SELLER_BILL) {
                        // 合约中剩余应收款的金额
                        $billAmount = $deliveryTimeoutContract->available_balance - $deliveryTimeoutContract->collateral_balance;
                        // 实际可退应收款的金额
                        $backRealAmount = $backAmount > $billAmount ? $billAmount : $backAmount;
                        // 需要退的抵押物金额
                        $backCollateralBalance = $backAmount - $backRealAmount;
                        // 合约剩余抵押物金额
                        $collateralBalance = $deliveryTimeoutContract->collateral_balance  - $backCollateralBalance;
                        // 应收款实际退还金额
                        $backAmount = $backRealAmount;
                        if ($backCollateralBalance > 0) {
                            $this->futuresContractMarginPayRecord->sellerBackFutureContractMargin(
                                $deliveryTimeoutContract->seller_id,
                                $deliveryTimeoutContract->id,
                                FuturesContractMarginPayRecord::SELLER_COLLATERAL,
                                $backCollateralBalance,
                                $operator
                            );
                        }
                    }

                    if ($backAmount > 0) {
                        $this->futuresContractMarginPayRecord->sellerBackFutureContractMargin(
                            $deliveryTimeoutContract->seller_id,
                            $deliveryTimeoutContract->id,
                            $firstFuturesContractMarginPayRecord->type,
                            $backAmount,
                            $operator
                        );
                    }
                }

                $this->futuresContractLog->insertLog(
                    $deliveryTimeoutContract,
                    4,
                    FuturesContractLog::formatContractLogContent(['status' => 4], $deliveryTimeoutContract),
                    $operator
                );

                $deliveryTimeoutContract->status = Contract::STATUS_TERMINATE;
                if ($deliveryTimeoutContract->available_balance > 0) {
                    $deliveryTimeoutContract->available_balance = $amount;
                    $deliveryTimeoutContract->collateral_balance = $collateralBalance;
                }
                $deliveryTimeoutContract->update_time = Carbon::now();
                $deliveryTimeoutContract->save();

                \DB::commit();
                echo $deliveryTimeoutContract->contract_no . " success\n";
            } catch (\Exception $e) {
                \DB::rollBack();
                echo $deliveryTimeoutContract->contract_no . ' error: ' . $e->getMessage() . "\n";
                \Log::error($e);
            }
        }

        $handleAgreementDeliveryTimeoutUrl = config('app.b2b_url') . 'api/future/agreement_delivery_timeout';
        $result = file_get_contents($handleAgreementDeliveryTimeoutUrl);
        \Log::info($result);

        echo date("Y-m-d H:i:s", time()) . ' ------exec-end------' . PHP_EOL;
    }
}