<?php

namespace App\Models\SellerBill;


use App\Models\Message\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class SellerCollateral extends Model
{
    protected $table = 'tb_sys_seller_collateral_value';

    public static function getSellerCollateral()
    {
        return DB::table('tb_sys_seller_collateral_value as cv')
            ->select(DB::raw('cv.current_balance,(cv.collateral_value+cv.shipping_value) as collateral_value,cv.customer_id,(cv.current_balance/(cv.collateral_value+cv.shipping_value)) as ratio,c.nickname,c.email,c.logistics_customer_name,cp.screenname'))
            ->leftjoin('oc_customer as c', 'c.customer_id', '=', 'cv.customer_id')
            ->leftjoin('oc_customerpartner_to_customer as cp', 'cp.customer_id', '=', 'cv.customer_id')
            ->where('cv.current_balance', '<', 0)
            ->get();
    }



}