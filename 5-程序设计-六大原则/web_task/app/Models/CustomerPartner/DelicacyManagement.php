<?php

namespace App\Models\CustomerPartner;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DelicacyManagement extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = 'oc_delicacy_management';
    }

    /**
     * @return array
     */
    private function getKeyVal()
    {
        return [
            'origin_id' => ['column' => 'id', 'is_real_value' => 0],
            'seller_id' => ['column' => 'seller_id', 'is_real_value' => 0],
            'buyer_id' => ['column' => 'buyer_id', 'is_real_value' => 0],
            'product_id' => ['column' => 'product_id', 'is_real_value' => 0],
            'current_price' => ['column' => 'current_price', 'is_real_value' => 0],
            'product_display' => ['column' => 'product_display', 'is_real_value' => 0],
            'price' => ['column' => 'price', 'is_real_value' => 0],
            'effective_time' => ['column' => 'effective_time', 'is_real_value' => 0],
            'expiration_time' => ['column' => 'expiration_time', 'is_real_value' => 0],
            'origin_add_time' => ['column' => 'add_time', 'is_real_value' => 0],
            'add_time' => ['column' => date('Y-m-d H:i:s'), 'is_real_value' => 1]
        ];
    }

    public function getInvalidData($limit)
    {
        return DB::table($this->table . ' as dm')
            ->join('oc_product as p', 'p.product_id', '=', 'dm.product_id')
            ->join('oc_customer as c', 'c.customer_id', '=', 'dm.buyer_id')
            ->join('oc_buyer_to_seller as bts', [['bts.buyer_id', 'dm.buyer_id'], ['bts.seller_id', 'dm.seller_id']])
            ->orWhere('c.status', '<>', 1)
            ->orWhere('p.status', '<>', 1)
            ->orWhere('p.buyer_flag', '<>', 1)
            ->orWhere('p.is_deleted', '<>', 0)
            ->orWhere('bts.seller_control_status', '<>', 1)
            ->limit($limit ?? 100)
            ->pluck('dm.id')
            ->toArray();
    }

    public function batchDeleteByIDs($ids)
    {
        $ids = array_unique($ids);
        if (empty($ids)) {
            return;
        }

        $keyValArr = [];
        $delicacyIDArr = [];
        $objs = DB::table($this->table)
            ->whereIn('id', $ids)
            ->get(['*']);

        $temp = [
            'type' => 2,
        ];
        foreach ($objs as $item) {
            foreach ($this->getKeyVal() as $_k => $_v) {
                $temp[$_k] = $_v['is_real_value'] ? $_v['column'] : $item->{$_v['column']};
            }

            $keyValArr[] = $temp;
            $delicacyIDArr[] = $item->id;
        }
        DB::table($this->table)
            ->whereIn('id', $delicacyIDArr)
            ->delete();
        DB::table('oc_delicacy_management_history')
            ->insert($keyValArr);
    }
}
