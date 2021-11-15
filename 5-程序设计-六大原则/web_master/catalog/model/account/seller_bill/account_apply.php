<?php

namespace Catalog\model\account\seller_bill;

use Illuminate\Database\Capsule\Manager as DB;

class account_apply
{

    const APPLY_STATUS_PENDING = 0 ; //pending
    const APPLY_STATUS_APPROVED = 1 ;  //approved
    const APPLY_STATUS_REJECT = 2 ;  //reject

    const APPLY_STATUS = [
        'Pending',
        'Approved',
        'Rejected',
    ];

    protected static $table = 'tb_sys_seller_account_apply';

    /**
     * 获取申请类型
     *
     * @param $apply_status
     * @return array|string
     */
    public static function calculateAccountApplyStatus($apply_status = 1)
    {
        $account_apply_status = [
            self::APPLY_STATUS_PENDING => 'Pending',
            self::APPLY_STATUS_APPROVED => 'Approved',
            self::APPLY_STATUS_REJECT => 'Rejected',
        ];

        return isset($account_apply_status[$apply_status]) ? $account_apply_status[$apply_status] : 'Unknown';
    }

    public static function insert($data)
    {
        return DB::table(self::$table)->insertGetId($data);
    }

    public static function update($id, $data)
    {
        return DB::table(self::$table)->where('id', $id)->update($data);
    }

    public static function getApplyById($id)
    {
        return DB::table(self::$table)->where('id', $id)->first();
    }

    public static function getPendingApplyByAccountId($accountId)
    {
        return DB::table(self::$table)->where(['account_id' => $accountId, 'status' => 0])->first();
    }
}
