<?php

namespace App\Models\SellerBill;

use Illuminate\Database\Eloquent\Model;

class SellerBill extends Model
{
    protected $table = 'tb_seller_bill';

    public static function getLastSellerBill()
    {
        return self::select('start_date', 'end_date')
            ->orderBy('id', 'DESC')
            ->first();
    }


}