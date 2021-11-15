<?php
/**
 * Created by PHPSTORM.
 * User: yaopengfei
 * Date: 2020/8/18
 * Time: 16:23
 */

namespace App\Models\Future;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property  int $type
 *
 * Class FuturesContractMarginPayRecord
 * @package App\Models\Future
 */
class FuturesContractMarginPayRecord extends Model
{
    const LINE_OF_CREDIT = 1;   // 授信额度
    const SELLER_BILL = 3; // 应收款
    const SELLER_COLLATERAL = 4; // 抵押物

    protected $table = 'oc_futures_contract_margin_pay_record';
    public $timestamps = false;

    /**
     * 外部seller支付类型
     * @return int[]
     */
    public static function oAccountingPayType()
    {
        return [self::SELLER_BILL, self::SELLER_COLLATERAL];
    }

    /**
     * 退还seller合约保证金
     * @param int $sellerId
     * @param int $contractId
     * @param int $payType
     * @param $amount
     * @param string $operator
     * @return bool
     */
    public function sellerBackFutureContractMargin(int $sellerId, int $contractId, int $payType, $amount, string $operator)
    {
        $billType = 2;
        return $this->insertPayRecord($billType, ...func_get_args());
    }

    /**
     * @param int $billType
     * @param int $sellerId
     * @param int $contractId
     * @param int $payType
     * @param $amount
     * @param string $operator
     * @return bool
     */
    private function insertPayRecord(int $billType, int $sellerId, int $contractId, int $payType, $amount, string $operator)
    {
        $data = [
            'contract_id' => $contractId,
            'customer_id' => $sellerId,
            'type' => $payType,
            'amount' => $amount,
            'bill_type' => $billType,
            'bill_status' => $payType == self::SELLER_BILL ? 0 : 1,
            'create_time' => Carbon::now(),
            'update_time' => Carbon::now(),
            'operator' => $operator,
        ];

        return FuturesContractMarginPayRecord::query()->insert($data);
    }
}