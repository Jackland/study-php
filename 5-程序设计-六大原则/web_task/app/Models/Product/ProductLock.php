<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProductLock extends Model
{
    protected $table = 'oc_product_lock';

    public static function getProductLockByAgreementIds($agreement_ids)
    {
        return self::select('agreement_id', 'qty', 'set_qty')
            ->whereIn('agreement_id', $agreement_ids)
            ->where('type_id', 3)
            ->get();

    }
}