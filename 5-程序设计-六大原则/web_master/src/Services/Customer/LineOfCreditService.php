<?php

namespace App\Services\Customer;

use App\Components\UniqueGenerator\Enums\ServiceEnum;
use App\Enums\Charge\ChargeType;
use App\Models\Customer\Customer;
use App\Models\Pay\LineOfCreditRecord;
use App\Repositories\Common\SerialNumberRepository;
use Carbon\Carbon;
use ModelAccountBalanceRecharge;

class LineOfCreditService
{
    /**
     * 增加用户信用额度
     *
     * 方法未使用事务，如果使用请在外层套上事务
     *
     * @param int $customerId
     * @param float $lineOfCredit 金额必须大于0
     * @param int $type 类型，参考ChargeType::getRevenueTypes()
     * @param int $headerId 关联ID
     * @param string $memo 备注
     * @return bool
     * @throws \Exception
     */
    public function addLineOfCredit(int $customerId,float $lineOfCredit,int $type,int  $headerId = 0, $memo = '')
    {
        if (!in_array($type, ChargeType::getRevenueTypes())) {
            return false;
        }
        if ($lineOfCredit <= 0) {
            // 小于等于0不继续
            return false;
        }
        $oldLineOfCredit = $this->getLineOfCreditLockForUpdate($customerId);
        $newLineOfCredit = $oldLineOfCredit + $lineOfCredit;
        Customer::where('customer_id', $customerId)->update(['line_of_credit' => $newLineOfCredit]);
        $this->createLineOfCreditRecord($customerId, $oldLineOfCredit, $newLineOfCredit, $type, $headerId, $memo);
        return true;
    }

    /**
     * 扣减用户信用额度
     *
     * 方法未使用事务，如果使用请在外层套上事务
     *
     * @param int $customerId
     * @param float $lineOfCredit 金额必须大于0
     * @param int $type 类型，参考ChargeType::getPaymentTypes()
     * @param int $headerId 关联ID
     * @param string $memo 备注
     * @return bool
     * @throws \Exception
     */
    public function subLineOfCredit(int $customerId,float $lineOfCredit,int $type,int  $headerId = 0, $memo = '')
    {
        if (!in_array($type, ChargeType::getPaymentTypes())) {
            return false;
        }
        if ($lineOfCredit <= 0) {
            // 小于等于0不继续
            return false;
        }
        $oldLineOfCredit = $this->getLineOfCreditLockForUpdate($customerId);
        if (bccomp($oldLineOfCredit, $lineOfCredit) === -1) {
            // 余额小于需要扣的钱
            return false;
        }
        $newLineOfCredit = $oldLineOfCredit - $lineOfCredit;
        Customer::where('customer_id', $customerId)->update(['line_of_credit' => $newLineOfCredit]);
        $this->createLineOfCreditRecord($customerId, $oldLineOfCredit, $newLineOfCredit, $type, $headerId, $memo);
        return true;
    }

    /**
     * @param int $customerId
     * @param float $oldLineOfCredit
     * @param float $newLineOfCredit
     * @param int $type
     * @param int $headerId
     * @param string $memo
     * @return LineOfCreditRecord
     * @throws \Exception
     */
    private function createLineOfCreditRecord(int $customerId, float $oldLineOfCredit, float $newLineOfCredit, int $type, int $headerId = 0, string $memo = '')
    {
        /** @var ModelAccountBalanceRecharge $model */
        $model = load()->model('account/balance/recharge');
        $lineOfCreditRecord = [
            'serial_number' => SerialNumberRepository::getDateSerialNumber(ServiceEnum::AMENDMENT_RECORD_NO),
            'customer_id' => $customerId,
            'old_line_of_credit' => $oldLineOfCredit,
            'new_line_of_credit' => $newLineOfCredit,
            'date_added' => Carbon::now(),
            'operator_id' => 0,
            'type_id' => $type,
            'header_id' => $headerId,
            'memo' => $memo,
        ];
        return LineOfCreditRecord::create($lineOfCreditRecord);
    }

    /**
     * 获取用户余额，注意这里面会加一个update锁，不应该用于其他地方获取用户余额
     *
     * @param int $customerId
     * @return float
     */
    private function getLineOfCreditLockForUpdate($customerId)
    {
        $lineOfCredit = Customer::where('customer_id', '=', $customerId)
            ->lockForUpdate()
            ->value('line_of_credit');
        return floatval($lineOfCredit ?? 0);
    }
}
