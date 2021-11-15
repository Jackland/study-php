<?php

namespace App\Repositories\Futures;

use App\Enums\Future\FuturesMarginPayRecordBillType;
use App\Enums\Future\FuturesMarginPayRecordType;
use App\Enums\Margin\MarginAgreementPayRecordType;
use App\Logging\Logger;
use App\Models\Futures\FuturesContract;
use App\Models\Futures\FuturesContractMarginPayRecord;
use App\Repositories\Margin\MarginRepository;
use Carbon\Carbon;
use Catalog\model\futures\credit;
use ModelFuturesAgreement;
use ModelFuturesContract;

class ContractRepository
{
    /**
     * 获取某个seller当前抵押物金额(在库和在途)
     * @param int $sellerId
     * @return int|mixed
     */
    public function getSellerCollateralAmount(int $sellerId)
    {
        $collateral = db('tb_sys_seller_collateral_value')->where('customer_id', $sellerId)->first();
        if (empty($collateral)) {
            return 0;
        }

        return $collateral->collateral_value + $collateral->shipping_value;
    }

    /**
     * 获取某个seller当前可使用的抵押物金额
     * @param int $sellerId
     * @return mixed
     */
    public function getSellerAvailableCollateralAmount(int $sellerId)
    {
        $collateralAmount = $this->getSellerCollateralAmount($sellerId);

        $todayUsedCollateralAmount = FuturesContractMarginPayRecord::query()
            ->where('type', FuturesMarginPayRecordType::SELLER_COLLATERAL)
            ->where('bill_type', FuturesMarginPayRecordBillType::EXPEND)
            ->where('customer_id', $sellerId)
            ->where('create_time', '>=', Carbon::today()->format('Y-m-d 00:00:00'))
            ->sum('amount');

        return max([0, $collateralAmount - $todayUsedCollateralAmount]);
    }

    /**
     * 查询seller所在期货合约的复杂交易
     * @param int $sellerId
     * @return array
     */
    public function getFuturesProductsBySellerId(int $sellerId):array
    {
        return FuturesContract::query()
            ->where([
                'status' => 1,
                'is_deleted' => 0,
                'seller_id' => $sellerId,
            ])
            ->groupBy('product_id')
            ->pluck('product_id')
            ->toArray();
    }

    /**
     * 获取 Seller生成期货保证金合约|Onsite Seller现货头款保证金 可用金额
     *  按账户类型获取对应的可以金额，目前只处理了 内部账户|外部账户|Onsite Seller账户
     *      a.外部账户|Onsite Seller  -> 取 应收款+抵押物
     *      b.内部账户 -> 取 授信额度
     *      c.其他账户类型暂未处理 -> 0
     *
     * @param int $sellerId SellerID
     * @param int $accountType 账户类型
     * @return float|int
     */
    public function getSellerActiveAmount(int $sellerId, int $accountType)
    {
        $amount = 0;
        try {
            /** @var ModelFuturesContract $modelFuturesContract */
            $modelFuturesContract = load()->model('futures/contract');
            /** @var ModelFuturesAgreement $modelFuturesAgreement */
            $modelFuturesAgreement = load()->model('futures/agreement');

            $disableContractUncheckApplyContracts = $modelFuturesContract->getDisableContractUncheckApplyContracts($sellerId); // 审核中未生效的合约
            $disableContractUncheckApplyContractIds = array_column($disableContractUncheckApplyContracts, 'id');
            $disableContractUncheckApplyContractAmount = array_sum(array_column($disableContractUncheckApplyContracts, 'amount'));

            switch ($accountType) {
                // 外部账户 || Onsite Seller 取应收款+抵押物 金额
                case 6:
                case 2:
                    $allAmount = $modelFuturesAgreement->getSellerBill($sellerId);
                    $futureMarginUnfinishedAmount = $modelFuturesAgreement->getMarginPayRecordSum($sellerId, 3);
                    $futureContractMarginUnfinishedAmount = $modelFuturesContract->getUnfinishedPayRecordAmount($sellerId, 3);
                    $totalExpandAmount = $modelFuturesContract->totalExpandAmountByContractIds($disableContractUncheckApplyContractIds, $sellerId, [FuturesMarginPayRecordType::SELLER_BILL, FuturesMarginPayRecordType::SELLER_COLLATERAL]);
                    $disableContractUncheckApplyAmount = $disableContractUncheckApplyContractAmount > $totalExpandAmount ? $disableContractUncheckApplyContractAmount - $totalExpandAmount : 0;

                    // 未完成的现货协议保证金金额
                    $marginUnfinishedAmount = app(MarginRepository::class)->getUnfinishedPayRecordAmount($sellerId);

                    // 当前可使用的抵押物金额
                    $collateralAmount = $this->getSellerAvailableCollateralAmount($sellerId);

                    // 可使用金额 = 账单金额 - 合约未完成的金额 - 协议未完成的金额 - 禁用合约未审核的金额 + 的抵押物金额 - 现货保证金未入账金额
                    $amount = $allAmount - $futureMarginUnfinishedAmount - $futureContractMarginUnfinishedAmount - $disableContractUncheckApplyAmount + $collateralAmount - $marginUnfinishedAmount;
                    break;
                case 1:
                    $totalExpandAmount = $modelFuturesContract->totalExpandAmountByContractIds($disableContractUncheckApplyContractIds, $sellerId, 1);
                    $disableContractUncheckApplyAmount = $disableContractUncheckApplyContractAmount > $totalExpandAmount ? $disableContractUncheckApplyContractAmount - $totalExpandAmount : 0;

                    $amount = credit::getLineOfCredit($sellerId) - $disableContractUncheckApplyAmount;
                    break;
                default:
                    $amount = 0;
            }
        } catch (\Exception $e) {
            Logger::error('取Seller账户可用金额发生错误：' . $e->getMessage());
        }

        return $amount;
    }
}
