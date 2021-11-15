<?php

namespace Catalog\model\futures;

use Illuminate\Database\Capsule\Manager as DB;

class creditApply
{
    protected static $table = 'oc_seller_credit_apply';

    public static function saveCreditApply($data)
    {
        $data['type_id'] = json_encode($data['type_id']);
        $model = DB::table(self::$table);
        $count = $model->where(['credit_number' => $data['credit_number']])->count();
        if ($count) {
            $model->where(['credit_number' => $data['credit_number']])->update($data);
        } else {
            $model->insert($data);
        }
    }

    public static function getCreditApply($seller_id, $where = [])
    {
        return DB::table(self::$table)
            ->where(['seller_id' => $seller_id])
            ->when($where, function ($query) use ($where) {
                return $query->where($where);
            })
            ->orderBy('id', 'DESC')
            ->first();
    }

    public static function hasCreditApply($seller_id)
    {
        return DB::table(self::$table)
            ->where(['seller_id' => $seller_id, 'type_id' => '[1]'])
            ->where('status', '!=', 3)
            ->first();
    }

    public static function getCreditAttach($attach)
    {
        return DB::table('oc_file_upload')
            ->whereIn('file_upload_id', $attach)
            ->get();
    }

    public static function getAttach($path)
    {
        return DB::table('oc_file_upload')
            ->where('path', $path)
            ->get();
    }

    public static function getApplyOperate($credit_apply_id)
    {
        return DB::table('oc_seller_credit_apply_operate')
            ->where(['credit_apply_id' => $credit_apply_id])
            ->orderBy('id', 'DESC')
            ->get();
    }
}