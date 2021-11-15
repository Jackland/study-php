<?php

namespace Catalog\model\account\seller_bill;

use App\Models\SellerBill\SellerAccountInfo;
use Illuminate\Database\Capsule\Manager as DB;

class account_info
{
    const CORPORATE_ACCOUNT = 1 ; //对公
    const PRIVATE_ACCOUNT   = 2 ; //对私
    const PAYONEER_ACCOUNT  = 3 ; //p卡

    const ACCOUNT_STATUS_ENABLED  = 1 ; //启用
    const ACCOUNT_STATUS_DISABLED = 0 ;  //禁用

    protected static $table = 'tb_sys_seller_account_info';

    protected static $related_apply_table = 'tb_sys_seller_account_apply';

    public static function insert($data)
    {
        return DB::table(self::$table)->insertGetId($data);
    }

    public static function update($id, $data)
    {
        return DB::table(self::$table)->where('id', $id)->update($data);
    }

    public static function getInfoBySellerId($seller_id)
    {
        return SellerAccountInfo::query()->where('seller_id', $seller_id)->where('is_deleted',0)->where('status', 1)->first();
    }
    public static function getInfoById($id)
    {
         return SellerAccountInfo::query()->where('id', $id)->where('is_deleted',0)->first();
    }

    public static function getInfoByCondition($where)
    {
        return SellerAccountInfo::query()->where($where)->where('is_deleted',0)->first();
    }
    public static function getFile($header_id)
    {
        return DB::table('tb_sys_seller_account_file')
            ->where('header_id', $header_id)
            ->where(['delete_flag' => 0])
            ->get();
    }

    /**
     * 获取账户启用状态
     *
     * @param $accountStatus
     *
     * @return array|string
     */
    public static function calculateAccountStatus($accountStatus = 0)
    {
        $accountStatusAll = [
            self::ACCOUNT_STATUS_ENABLED => 'Enable',
            self::ACCOUNT_STATUS_DISABLED => 'Disable',
        ];
        return isset($accountStatusAll[$accountStatus]) ? $accountStatusAll[$accountStatus] : 'Unknown';
    }

    /**
     * 获取账户类型
     *
     * @param $accountTypeId
     *
     * @return array|string
     */
    public static function calculateAccountType($accountTypeId = 1)
    {
        $accountTypes = [
            self::CORPORATE_ACCOUNT => 'Corporate Account',
            self::PRIVATE_ACCOUNT => 'Private Account',
            self::PAYONEER_ACCOUNT => 'Payoneer',
        ];
        return isset($accountTypes[$accountTypeId]) ? $accountTypes[$accountTypeId] : 'Unknown';
    }

    /**
     * 获取seller账户列表
     *
     * @param  int   $sellerId
     * @param  int   $page
     * @param  int   $limit
     * @param  array $condition
     *
     * @return array
     */
    public static function calculateSellerAccountList($sellerId = 0, $page = 1, $limit = 8, $condition = [])
    {
        $result = ['list' => [], 'total' => 0];

        if (empty($sellerId)) {
            return $result;
        }

        $builder = DB::table(self::$table . ' as ai')
            ->leftJoin('tb_sys_seller_account_apply as apy', 'ai.apply_id', '=', 'apy.id')
            ->where('ai.seller_id', $sellerId)
            ->where('is_deleted', 0);

        $total_no_page = $builder->count();
        if (isset($condition['display_disable']) && $condition['display_disable'] == 0) {
            $builder->where('ai.status', 1);
        }

        $total = $builder->count();
        $list = $builder->offset(($page - 1) * $limit)
            ->limit($limit)
            ->orderByRaw('ai.status desc,update_time desc')
            ->selectRaw('ai.*,apy.status as check_status')
            ->get()
            ->map(function ($item) {
                $item->address = app('db-aes')->decrypt($item->address);
                $item->company = app('db-aes')->decrypt($item->company);
                $item->tel = app('db-aes')->decrypt($item->tel);
                $item->tel_bak = app('db-aes')->decrypt($item->tel_bak);
                $item->p_email = app('db-aes')->decrypt($item->p_email);
                $item->bank_account_info = $item->account_type == 1 ? self::calculateBankFormat($item->bank_account) : [];
                $item->p_account_info = $item->account_type == 3 ? self::calculateEmailFormat($item->p_email) : [];
                $item->del_show_msg = self::calculateDelInfo($item);
                $item->check_status = intval($item->check_status);
                $item->verdict_format = account_apply::calculateAccountApplyStatus($item->check_status);
                return (array)$item;
            })
            ->toArray();

        $result['list'] = $list;
        $result['total'] = $total;
        $result['total_no_page'] = $total_no_page;

        return $result;

    }

    /**
     * 组织一行卡格式格式
     *
     * @param  string $bankAccount
     *
     * @return array
     */
    public static function calculateBankFormat($bankAccount = '')
    {
        $subBankAccount = mb_substr($bankAccount, -4);
        $bankAccountInfo = [
            'last_four_bank_num' => $subBankAccount,
            'bank_account_format' => '**** **** ' . $subBankAccount,
            'account_type_format' => self::calculateAccountType(1),
        ];
        return $bankAccountInfo;
    }

    /**
     * 组织邮箱格式
     *
     * @param  string $email
     *
     * @return array
     */
    public static function calculateEmailFormat($email = '')
    {
        if (empty($email)) {
            return [
                'front_four_p_num' => '',
                'p_email_format' => '' . '****' . substr($email, strpos($email, '@')),
                'account_type_format' => self::calculateAccountType(3),
            ];
        }

        $postfix = substr($email, strpos($email, '@'));
        $length = utf8_strlen($email) - utf8_strlen($postfix);
        $length_new = min($length, 4);

        return [
            'front_four_p_num' => substr($email, 0, $length_new),
            'p_email_format' => substr($email, 0, $length_new) . '****' . $postfix,
            'account_type_format' => self::calculateAccountType(3),
        ];
    }

    /**
     * 组织删除信息
     *
     * @param  object $item
     *
     * @return string
     */
    public static function calculateDelInfo($item)
    {
        $del_info = 'Are you sure you want to delete the collection account ?';
        switch ($item->account_type) {
            case self::CORPORATE_ACCOUNT:
                $sub_info = mb_substr($item->bank_account, -4);
                $del_info = 'Are you sure you want to delete the collection account ending in ' . $sub_info . ' ?';
                break;
            case self::PAYONEER_ACCOUNT:
                $sub_info = self::calculateEmailFormat($item->p_email)['front_four_p_num'];
                $del_info = 'Are you sure you want to delete the Payoneer account starting with ' . $sub_info . ' ?';
                break;
        }

        return $del_info;

    }

    /**
     * 获取账户信息 检验
     *
     * @param  int $accountId
     * @param  int $sellerId
     *
     * @return array
     */
    public static function checkAccountInfo($accountId, $sellerId)
    {
        $account_info = DB::table(self::$table)
            ->where('id', '=', $accountId)
            ->where('seller_id', '=', $sellerId)
            ->where('is_deleted', '=', 0)
            ->first();

        return obj2array($account_info);
    }

    /**
     * 编辑账户信息
     *
     * @param  int   $accountId
     * @param  array $saveData
     *
     * @return bool
     */
    public static function editAccountInfo($accountId = 0, $saveData = [])
    {
        if (empty($accountId || empty($saveData))) {
            return false;
        }
        return DB::table(self::$table)->where('id', $accountId)->update($saveData);
    }

    /**
     * 删除seller账户
     *
     * @param  int $accountId
     *
     * @return bool
     */
    public static function deleteSellerAccount($accountId = 0)
    {
        return DB::table(self::$table)->where('id', $accountId)->update(['is_deleted' => 1]);
    }

    /**
     * 记录申请审核
     *
     * @param  array $data
     *
     * @return bool|integer
     */
    public static function doAccountApply($data)
    {
        $apply_data = [
            'account_id' => $data['account_id'],
            'create_username' => $data['create_username'],
            'create_time' => date('Y-m-d H:i:s'),
            'update_username' => $data['create_username'],
            'update_time' => date('Y-m-d H:i:s'),
            'reason' => $data['reason'] ?? '',
        ];
        try {
            DB::connection()->beginTransaction();
            $row_id = DB::table(self::$related_apply_table)->insertGetId($apply_data);
            $updateData = [
                'apply_id' => $row_id,
                'update_user_name' => $data['create_username'],
                'update_time' => date('Y-m-d H:i:s'),
            ];
            DB::table(self::$table)->where('id', $apply_data['account_id'])->update($updateData);
            DB::connection()->commit();
            return $row_id;
        } catch (\Exception $e) {
            DB::connection()->rollBack();
            return false;
        }
    }

}
