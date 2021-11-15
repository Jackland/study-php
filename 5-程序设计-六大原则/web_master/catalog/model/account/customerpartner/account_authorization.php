<?php
/**
 * Created by PHPSTORM
 * User: yaopengfei
 * Date: 2020/7/15
 * Time: 上午11:05
 */

/**
 * Class ModelAccountCustomerpartnerAccountAuthorization
 */
class ModelAccountCustomerpartnerAccountAuthorization extends Model
{
    /**
     * @param int $sellerId
     * @return array
     */
    public function authorizesBySellerId(int $sellerId)
    {
        $result = $this->orm->table(DB_PREFIX . 'seller_account_manager_authorizes as sama')
            ->join(DB_PREFIX . 'customer as c', 'sama.account_manager_id', '=', 'c.customer_id')
            ->where('sama.seller_id', $sellerId)
            ->where('sama.is_deleted', 0)
            ->orderBy('sama.id', 'desc')
            ->get(['sama.*', 'c.firstname', 'c.lastname']);

        return obj2array($result);
    }

    /**
     * @param int $id
     * @return array
     */
    public function authorizeById(int $id)
    {
        $result = $this->orm->table(DB_PREFIX . 'seller_account_manager_authorizes as sama')
            ->join(DB_PREFIX . 'customer as c', 'sama.account_manager_id', '=', 'c.customer_id')
            ->where('sama.id', $id)
            ->where('sama.is_deleted', 0)
            ->select(['sama.*', 'c.firstname', 'c.lastname'])
            ->first();

        return obj2array($result);
    }

    /**
     * @param int $sellerId
     * @return array
     */
    public function accountManagerBySellerId(int $sellerId)
    {
        $result = $this->orm->table(DB_PREFIX . 'customer as c')
            ->join(DB_PREFIX . 'buyer_to_seller as bts', 'c.customer_id', '=', 'bts.buyer_id')
            ->where('bts.seller_id', $sellerId)
            ->where('c.customer_group_id', 14)
            ->select(['c.*'])
            ->first();

        return obj2array($result);
    }

    /**
     * @param int $sellerId
     * @param array $status
     * @return array
     */
    public function authorizeBySellerIdAndStatus(int $sellerId, array $status)
    {
        $result = $this->orm->table(DB_PREFIX . 'seller_account_manager_authorizes')
            ->where('seller_id', $sellerId)
            ->whereIn('status', $status)
            ->where('is_deleted', 0)
            ->first();

        return obj2array($result);
    }

    /**
     * @param int $sellerId
     * @param int $accountManagerId
     * @param array $permissions
     * @return bool
     */
    public function insertAuthorize(int $sellerId, int $accountManagerId, array $permissions)
    {
        return $this->orm->table(DB_PREFIX . 'seller_account_manager_authorizes')->insert([
            'seller_id' => $sellerId,
            'account_manager_id' => $accountManagerId,
            'permissions' => json_encode($permissions),
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param int $id
     * @param array $data
     * @return int
     */
    public function updateAuthorize(int $id, array $data)
    {
        $data['update_time'] = date('Y-m-d H:i:s');

        return $this->orm->table(DB_PREFIX . 'seller_account_manager_authorizes')
            ->where('id', $id)
            ->update($data);
    }

    /**
     * @param int $sellerId
     * @param int $accountManagerId
     * @return array
     */
    public function authorizedBySellerIdAccountManagerId(int $sellerId, int $accountManagerId)
    {
        $result = $this->orm->table(DB_PREFIX . 'seller_account_manager_authorizes')
            ->where('seller_id', $sellerId)
            ->where('account_manager_id', $accountManagerId)
            ->where('status', 2)
            ->where('is_deleted', 0)
            ->first();

        return obj2array($result);
    }
}