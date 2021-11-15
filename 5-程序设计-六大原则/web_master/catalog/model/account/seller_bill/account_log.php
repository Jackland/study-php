<?php

namespace Catalog\model\account\seller_bill;

use Illuminate\Database\Capsule\Manager as DB;

class account_log
{
    const CHANGE_TYPE_ADD = 1; //新增
    const CHANGE_TYPE_EDIT = 2; //编辑
    const CHANGE_TYPE_APPROVAL = 3; //审批
    const CHANGE_TYPE_DELETE = 4; //删除

    protected static $table = 'tb_sys_seller_account_log';

    /**
     * 获取变更操作类型
     *
     * @param $changeType
     *
     * @return array|string
     */
    public static function calculateAccountApplyStatus($changeType = 1)
    {
        $change_types = [
            self::CHANGE_TYPE_ADD => 'Create',
            self::CHANGE_TYPE_EDIT => 'Edit',
            self::CHANGE_TYPE_APPROVAL => 'Verdict',
            self::CHANGE_TYPE_DELETE => 'Delete',
        ];

        return isset($change_types[$changeType]) ? $change_types[$changeType] : 'Unknown';
    }

    /**
     * 插入数据
     *
     * @param  array $data
     *
     * @return int
     */
    public static function insert($data)
    {
        return DB::table(self::$table)->insertGetId($data);
    }

    /**
     * 获取日志列表
     *
     * @param  int $sellerId
     * @param  int $page
     * @param  int $limit
     *
     * @return array
     */
    public static function calculateSellerLogList($sellerId = 0, $page = 1, $limit = 8)
    {
        $result = ['list' => [], 'total' => 0];

        if (empty($sellerId)) {
            return $result;
        }

        $total = DB::table(self::$table)
            ->where('seller_id', $sellerId)->count();

        $list = DB::table(self::$table)
            ->where('seller_id', $sellerId)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->orderByRaw('create_time desc')
            ->get()
            ->map(function ($item) {
                $item->change_type = self::calculateAccountApplyStatus($item->change_type);
                $item->account_type = account_info::calculateAccountType($item->account_type);
                $item->account_status = self::calculateAccountStatusInfo($item->account_status_old, $item->account_status_new);
                $item->apply_status = self::calculateApplyStatusInfo($item->apply_status_old, $item->apply_status_new);
                $item->operate_from = $item->marketplace_flag == 0 ? 'Seller' : 'Marketplace';
                return (array)$item;
            })
            ->toArray();

        $result['list'] = $list;
        $result['total'] = $total;

        return $result;

    }

    /**
     * 组织账户状态，日志中展示
     *
     * @param  int $oldAccountStatus
     * @param  int $newAccountStatus
     *
     * @return string
     */
    public static function calculateAccountStatusInfo($oldAccountStatus, $newAccountStatus)
    {
        $oldAccountStatus = intval($oldAccountStatus);
        $newAccountStatus = intval($newAccountStatus);
        if ($oldAccountStatus >= 0 && $newAccountStatus >= 0) {
            if ($oldAccountStatus == $newAccountStatus) {
                $result = account_info::calculateAccountStatus($oldAccountStatus);
            } else {
                $result = account_info::calculateAccountStatus($oldAccountStatus) . ' → ' .
                    account_info::calculateAccountStatus($newAccountStatus);
            }
        } elseif ($oldAccountStatus >= 0) {
            $result = account_info::calculateAccountStatus($oldAccountStatus);
        } elseif ($newAccountStatus >= 0) {
            $result = account_info::calculateAccountStatus($newAccountStatus);
        } else {
            $result = 'N/A';
        }
        return $result;
    }

    /**
     * 组织审核状态，日志中展示
     *
     * @param  int $oldApplyStatus
     * @param  int $newApplyStatus
     *
     * @return string
     */
    public static function calculateApplyStatusInfo($oldApplyStatus, $newApplyStatus)
    {
        $oldApplyStatus = intval($oldApplyStatus);
        $newApplyStatus = intval($newApplyStatus);
        if ($oldApplyStatus >= 0 && $newApplyStatus >= 0) {
            if ($oldApplyStatus == $newApplyStatus) {
                $result = account_apply::calculateAccountApplyStatus($oldApplyStatus);
            } else {
                $result = account_apply::calculateAccountApplyStatus($oldApplyStatus) . ' → ' .
                    account_apply::calculateAccountApplyStatus($newApplyStatus);
            }
        } elseif ($oldApplyStatus >= 0) {
            $result = account_apply::calculateAccountApplyStatus($oldApplyStatus);
        } elseif ($newApplyStatus >= 0) {
            $result = account_apply::calculateAccountApplyStatus($newApplyStatus);
        } else {
            $result = 'N/A';
        }
        return $result;
    }

}
