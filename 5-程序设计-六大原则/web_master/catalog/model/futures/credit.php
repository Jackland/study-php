<?php

namespace Catalog\model\futures;

use Illuminate\Database\Capsule\Manager as DB;

class credit
{
    protected static $table = 'oc_seller_credit';
    static $page_size = 15;

    public static function getCredit($seller_id)
    {
        return DB::table(self::$table)
            ->where(['seller_id' => $seller_id])
            ->first();
    }

    public static function getBillList($seller_id, $page, $filter, $page_size = 15)
    {
        $build = DB::table('oc_seller_credit_bill')
            ->where(['seller_id' => $seller_id])
            ->when(isset($filter['serial_no']) && trim($filter['serial_no']), function ($query) use ($filter) {
                return $query->where('serial_no', 'like', '%' . trim($filter['serial_no']) . '%');
            })
            ->when(isset($filter['type']) && $filter['type'], function ($query) use ($filter) {
                return $query->where('type', $filter['type']);
            })
            ->when(isset($filter['date_from']) && !empty($filter['date_from']), function ($query) use ($filter) {
                return $query->where('create_time', '>=', $filter['date_from']);
            })
            ->when(isset($filter['date_to']) && !empty($filter['date_to']), function ($query) use ($filter) {
                return $query->where('create_time', '<=', $filter['date_to'] . ' 23:59:59');
            })
            ->orderBy('id', 'desc');
        return [
            'total' => $build->count(),
            'data'  => obj2array($build->forPage($page, $page_size)->get())
        ];
    }

    public static function getLastBill($seller_id)
    {
        return DB::table('oc_seller_credit_bill')
            ->where([
                'seller_id' => $seller_id,
            ])
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * 判断信用是否在有效期
     * @param int $seller_id
     * @return bool
     */
    public static function isCreditUnExpire($seller_id)
    {
        $credit = self::getCredit($seller_id);
        $now = time();
        if ($credit) {
            if ($credit->credit_start_time < $now && ($credit->credit_end_time > $now || $credit->credit_end_time == -1))
                return true;
        }
        return false;
    }

    /**
     *获取seller授信余额
     * @param int $sell_id
     * @return int|mixed
     */
    public static function getLineOfCredit($sell_id)
    {
        if (self::isCreditUnExpire($sell_id)) {
            return self::getLastBill($sell_id)->current_balance;
        }
        return 0;
    }

    public static function addCreditBill($data)
    {
        $data['serial_no'] = date('Ymd') . mt_rand(100000, 999999);
        return DB::table('oc_seller_credit_bill')
            ->insert($data);
    }


    /**
     * 添加授信账单
     * @param int $seller_id
     * @param $amount
     * @param int $type 类型1为支出，2为收入
     * @param string $introduce
     * @return bool
     */
    public static function insertCreditBill($seller_id, $amount, $type = 1, $introduce = '')
    {
        $credit = DB::table('oc_seller_credit_bill')
            ->where([
                'seller_id' => $seller_id,
            ])
            ->orderBy('id', 'DESC')
            ->lockForUpdate()
            ->first();
        $data['seller_id'] = $seller_id;
        $data['amount'] = $amount;
        $data['type'] = $type;
        if ($type == 1) {
            $data['current_balance'] = bcsub($credit->current_balance, $amount,2);
            $data['introduce'] = empty($introduce) ? 'Pay future goods deposit' : $introduce;
        } else {
            $data['current_balance'] = bcadd($credit->current_balance, $amount,2);
            $data['introduce'] = empty($introduce) ? 'Return future goods deposit' : $introduce;
        }
        return self::addCreditBill($data);
    }
}
