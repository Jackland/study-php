<?php

namespace App\Models\Future;

use App\Models\Customer\Customer;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model;

/**
 * @property  int $status １售卖中，２禁用，３已售卖完成，４合约终止
 * @property  \DateTime $update_time
 * @property  \DateTime $delivery_date
 * @property  float $available_balance
 * @property  float $collateral_balance
 * @property  int $seller_id
 * @property  int $id
 * @property  string $contract_no
 * @property  Customer $seller
 * @property  FuturesContractMarginPayRecord $firstFuturesContractMarginPayRecord
 *
 * Class Contract
 * @package App\Models\Future
 */
class Contract extends Model
{
    const STATUS_TERMINATE = 4;

    protected $table = 'oc_futures_contract';
    public $timestamps = false;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function seller()
    {
        return $this->belongsTo(Customer::class, 'seller_id', 'customer_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne|\Illuminate\Database\Query\Builder
     */
    public function firstFuturesContractMarginPayRecord()
    {
        return $this->hasOne(FuturesContractMarginPayRecord::class)->oldest('id');
    }

    /**
     * 首次新建合约的保证金支付记录
     * @param int $sellerId
     * @param array $contractIds
     * @return array
     */
    public static function firstPayRecordContracts(int $sellerId, array $contractIds)
    {
        return \DB::table('oc_futures_contract as c')
            ->leftJoin('oc_futures_contract_margin_pay_record as r', [['c.id', '=', 'r.contract_id'], ['c.seller_id', '=', 'r.customer_id']])
            ->where('c.seller_id', $sellerId)
            ->whereIn('c.id', $contractIds)
            ->groupBy(['c.id'])
            ->select(['c.*', 'r.type as pay_type'])
            ->get()
            ->map(function ($item) {
                // 第一笔合约支付是抵押物的也是使用应收款方式支付
                if ($item->pay_type == FuturesContractMarginPayRecord::SELLER_COLLATERAL) {
                    $item->pay_type = FuturesContractMarginPayRecord::SELLER_BILL;
                }
                return (array)$item;
            })
            ->toArray();
    }

    /**
     * 获取所有交付超时的合约
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function deliveryTimeoutContracts()
    {
        return Contract::query()
            ->with('firstFuturesContractMarginPayRecord')
            ->with('seller')
            ->where('is_deleted', 0)
            ->whereIn('status', [1, 2, 3])
            ->where('delivery_date', '<', Carbon::now()->addDay(1)->toDateTimeString())
            ->get();
    }
}