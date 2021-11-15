<?php

namespace App\Models\Future;

use App\Models\Credit\CreditBill;
use Illuminate\Database\Eloquent\Model;

class MarginPayRecord extends Model
{
    protected $table = 'oc_futures_margin_pay_record';
    public $timestamps = false;
    protected $connection = 'mysql_proxy';

    public static function updatePayRecord($agreement_ids, $data)
    {
        return self::whereIn('agreement_id', $agreement_ids)
            ->update($data);
    }

    public static function getPayRecordsByIds($agreement_ids)
    {
        return self::whereIn('agreement_id', $agreement_ids)
            ->get();
    }


    /**
     * 返还seller期货保证金：退回授信额度，修改status状态为完成，释放状态
     * @param $agreement_ids
     */

    public static function backFutureMargin($agreement_ids)
    {
        $records = self::getPayRecordsByIds($agreement_ids);
        foreach ($records as $item) {
            // 授信额度类型
            if (1 == $item->type && $item->status == 0) {
                // 退回授信额度
                CreditBill::addCreditBill($item->customer_id, $item->amount, 2);
            }
        }
        self::updatePayRecord($agreement_ids, ['status' => 1]); // 修改status状态为完成，释放状态
    }

    /**
     *
     * 扣押seller期货保证金：退回授信额度，不修改status状态等待seller账单脚本扣除保证金
     * @param $agreement_ids
     */
    public static function withholdFutureMargin($agreement_ids)
    {
        $records = self::getPayRecordsByIds($agreement_ids);
        self::updatePayRecord($agreement_ids, ['update_time' => date('Y=m-d H:i:s')]);
        foreach ($records as $item) {
            // 授信额度类型
            if (1 == $item->type && $item->status == 0) {
                // 退回授信额度
                CreditBill::addCreditBill($item->customer_id, $item->amount, 2);
            }
            // 有效提单类型
            if (2 == $item->type && $item->status == 0) {
                self::where('agreement_id', $item->agreement_id)
                    ->update(['status' => 1]);
            }
        }
    }


}