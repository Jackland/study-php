<?php

namespace App\Models\Rebate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Class OldTemplate
 * @package App\Models\Rebate
 */
class OldTemplate extends Model
{

    protected $table = 'tb_sys_rebate_template';

    /**
     * @return \Illuminate\Support\Collection
     */
    public function list()
    {
        return DB::table($this->table . ' as t')
            ->join('oc_product as p', 'p.product_id', '=', 't.product_id')
            ->selectRaw('t.*,p.sku,p.mpn,p.status as p_status,p.buyer_flag,p.is_deleted as p_is_deleted')
            ->get();
    }

    /**
     * @param $product_id
     * @return Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getTemplateByProductID($product_id)
    {
        $count = DB::table($this->table)
            ->where('product_id', '=', $product_id)
            ->count();
        if ($count != 1) {
            return null;
        } else {
            return DB::table($this->table)
                ->where('product_id', '=', $product_id)
                ->first();
        }
    }
}
