<?php


namespace App\Models\Credit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreditBill extends Model
{
    protected $table = 'oc_seller_credit_bill';
    public $timestamps = false;


    public static function addCreditBill($sell_id, $amount, $type = 1)
    {
        $credit = DB::table('oc_seller_credit_bill')
            ->where([
                'seller_id' => $sell_id,
            ])
            ->orderBy('id', 'DESC')
            ->lockForUpdate()
            ->first();
        $data['seller_id'] = $sell_id;
        $data['amount'] = $amount;
        $data['type'] = $type;
        if ($type == 1) {
            $data['current_balance'] = bcsub($credit->current_balance, $amount, 2);
            $data['introduce'] = 'Pay future goods deposit';
        } else {
            $data['current_balance'] = bcadd($credit->current_balance, $amount, 2);
            $data['introduce'] = 'Return future goods deposit';
        }
        self::insertCreditBill($data);
    }

    public static function insertCreditBill($data)
    {
        $data['serial_no'] = date('Ymd') . mt_rand(100000, 999999);
        return self::insert($data);
    }


    public static function getLastBill($seller_id)
    {
        return self::where([
            'seller_id' => $seller_id,
        ])
            ->orderBy('id', 'DESC')
            ->first();
    }
}