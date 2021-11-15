<?php

use App\Enums\Margin\MarginAgreementStatus;
use App\Enums\Order\OcOrderStatus;
use App\Repositories\ProductLock\ProductLockRepository;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;

/**
 * Class ModelAccountProductQuotesMarginContract
 * @property ModelAccountProductQuotesMarginContract $model_account_product_quotes_margin_contract
 */
class ModelAccountProductQuotesMarginContract extends Model
{
    const APPLIED = 1;    // 提交保证金申请的状态
    const PENDING = 2;    // Seller点击了保证金协议申请进行查看
    const APPROVED = 3;   // Seller同意保证金申请
    const REJECTED = 4;   // Seller拒绝保证金申请
    const TIME_OUT = 5;   // 后台管理系统设置了保证金模板的申请有效期（天），超过有效期，协议Seller未处理，则此条协议过期
    const SOLD = 6;       // Buyer完成购买保证金链接后，协议状态
    const CANCELED = 7;   // applied状态下,buyer取消保证金协议
    const COMPLETED = 8;  // 协议完成

    /**
     * 查询模板总数
     *
     * @param $data
     * @return mixed
     */
    public function getMarginContractTotal($data)
    {
        if (isset($data)) {
            $sql = "SELECT
                      COUNT(*) AS cnt
                    FROM
                      `tb_sys_margin_agreement` ma
                      INNER JOIN oc_product p
                        ON p.`product_id` = ma.`product_id`
                      INNER JOIN oc_customer buyer
                        ON buyer.`customer_id` = ma.`buyer_id`
                      LEFT JOIN `tb_sys_margin_agreement_status` mas
                        ON ma.`status` = mas.`margin_agreement_status_id` ";
            $implode = array();

            if (isset($data['buyer_id'])) {
                $implode[] = "ma.`buyer_id` = " . (int)$data['buyer_id'];
            }

            if (isset($data['seller_id'])) {
                $implode[] = "ma.`seller_id` = " . (int)$data['seller_id'];
            }

            if (isset($data['contract_id'])) {
                $implode[] = "ma.`agreement_id` like '%" . (int)$data['contract_id'] . "%'";
            }

            if (isset($data['filter_buyer_name']) && !is_null($data['filter_buyer_name'])) {
                $implode[] = " (buyer.nickname like '%" . $data['filter_buyer_name'] . "%' or buyer.user_number like '%" . $data['filter_buyer_name'] . "%')";
            }

            if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
                $implode[] = "ma.`status` = " . (int)$data['filter_status'];
            }

            if (isset($data['filter_sku_mpn']) && !empty($data['filter_sku_mpn'])) {
                $implode[] = " (p.sku like '%" . $data['filter_sku_mpn'] . "%' or p.mpn like '%" . $data['filter_sku_mpn'] . "%')";
            }

            if (!empty($data['filter_date_from'])) {
                $implode[] = "ma.`effect_time` >= '" . $this->db->escape(utf8_strtolower($data['filter_date_from'])) . "'";
            }
            if (!empty($data['filter_date_to'])) {
                $implode[] = "ma.`effect_time` <= '" . $this->db->escape(utf8_strtolower($data['filter_date_to'])) . "'";
            }

            if (!empty($implode)) {
                $sql .= " WHERE " . implode(" AND ", $implode);
            }
            $query = $this->db->query($sql);
            return $query->row['cnt'];
        }
    }

    /**
     * 查询返点模板表格展示需要的数据，分页查询
     *
     * @param $data
     * @return array
     */
    public function getMarginContractDisplay($data)
    {
        if (isset($data)) {
            $sql = "SELECT
                      ma.`agreement_id`,
                      ma.`day`,
                      ma.`num`,
                      ma.`price` AS unit_price,
                      ma.`money` AS sum_price,
                      ma.`create_time` AS applied_time,
                      ma.`update_time` AS update_time,
                      ma.`status`,
                      mas.`name` AS status_name,
                      mas.`color` AS status_color,
                      p.`sku`,
                      p.`mpn`,
                      buyer.`customer_id` AS buyer_id,
                      buyer.`nickname`,
                      buyer.`user_number`,
                      buyer.`customer_group_id`
                    FROM
                      `tb_sys_margin_agreement` ma
                      INNER JOIN oc_product p
                        ON p.`product_id` = ma.`product_id`
                      INNER JOIN oc_customer buyer
                        ON buyer.`customer_id` = ma.`buyer_id`
                      LEFT JOIN `tb_sys_margin_agreement_status` mas
                        ON ma.`status` = mas.`margin_agreement_status_id` ";

            $implode = array();

            if (isset($data['buyer_id'])) {
                $implode[] = "ma.`buyer_id` = " . (int)$data['buyer_id'];
            }

            if (isset($data['seller_id'])) {
                $implode[] = "ma.`seller_id` = " . (int)$data['seller_id'];
            }

            if (isset($data['contract_id'])) {
                $implode[] = "ma.`agreement_id` like '%" . (int)$data['contract_id'] . "%'";
            }

            if (isset($data['filter_buyer_name']) && !is_null($data['filter_buyer_name'])) {
                $implode[] = " (buyer.nickname like '%" . $data['filter_buyer_name'] . "%' or buyer.user_number like '%" . $data['filter_buyer_name'] . "%')";
            }

            if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
                $implode[] = "ma.`status` = " . (int)$data['filter_status'];
            }

            if (isset($data['filter_sku_mpn']) && !empty($data['filter_sku_mpn'])) {
                $implode[] = " (p.sku like '%" . $data['filter_sku_mpn'] . "%' or p.mpn like '%" . $data['filter_sku_mpn'] . "%')";
            }

            if (!empty($data['filter_date_from'])) {
                $implode[] = "ma.`create_time` >= '" . $this->db->escape(utf8_strtolower($data['filter_date_from'])) . "'";
            }
            if (!empty($data['filter_date_to'])) {
                $implode[] = "ma.`create_time` <= '" . $this->db->escape(utf8_strtolower($data['filter_date_to'])) . "'";
            }

            if (!empty($implode)) {
                $sql .= " WHERE " . implode(" AND ", $implode);
            }

            $sort_data = array(
                'ma.`update_time`'
            );

            if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
                $sql .= " ORDER BY " . $data['sort'];
            } else {
                $sql .= " ORDER BY ma.`create_time`";
            }

            if (isset($data['order']) && ($data['order'] == 'ASC')) {
                $sql .= " ASC";
            } else {
                $sql .= " DESC";
            }

            if (isset($data['start']) || isset($data['limit'])) {
                if ($data['start'] < 0) {
                    $data['start'] = 0;
                }

                if ($data['limit'] < 1) {
                    $data['limit'] = 20;
                }

                $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
            }
            $query = $this->db->query($sql);
            return $query->rows;
        }
    }

    /**
     * 获取协议详情信息
     * @param int $id
     *
     * @return array|null
     */
    public function getMarginInfoById($id)
    {
        $margin = $this->orm->table('tb_sys_margin_agreement AS a')
            ->leftJoin('tb_sys_margin_agreement_status AS s', 'a.status', '=', 's.margin_agreement_status_id')
            ->leftJoin('oc_customer as buyer', 'buyer.customer_id', '=', 'a.buyer_id')
            ->leftJoin('oc_product AS p', 'a.product_id', '=', 'p.product_id')
            ->leftJoin('tb_sys_margin_process AS mp', 'a.id', '=', 'mp.margin_id')
            ->leftJoin('tb_sys_margin_order_relation AS mor', 'mor.margin_process_id', '=', 'mp.id')
            ->leftJoin('tb_sys_margin_performer_apply AS mpa', function ($join) {
                $join->on('mpa.agreement_id', '=', 'a.id')
                    ->whereIn('mpa.check_result', [0, 1]);
            })
            ->where('a.id', $id)
            ->first([
                        'a.*',
                        DB::raw("GROUP_CONCAT(mor.rest_order_id) AS rest_order_ids"),
                        "s.name AS status_name",
                        "s.color AS status_color",
                        "buyer.customer_id AS buyer_id",
                        "buyer.nickname",
                        "buyer.user_number",
                        "buyer.customer_group_id",
                        "p.sku",
                        "p.mpn",
                        "mp.advance_product_id",
                        "mp.advance_order_id",
                        "mp.rest_product_id",
                        DB::raw("IFNULL(SUM(mor.purchase_quantity),0) AS sum_purchase_qty"),
                        DB::raw("COUNT(mpa.id) AS count_performer")
                    ]);
        return $margin ? obj2array($margin) : null;
    }

    //获取产品所在的店铺 ---用于判断尾款产品是否在保证金店铺
    public function get_store_seller_id($product_id_list){
        $res=$this->orm->table(DB_PREFIX.'customerpartner_to_product')
            ->whereIn('product_id',$product_id_list)
            ->select(['product_id','customer_id'])
            ->get();
        return obj2array($res);
    }

    // 获取尾款产品的数量
    public function get_rest_product_sell_num($product_agreement_list)
    {
        $product_id_list = array_column($product_agreement_list, 'product_id');
        // 获取product id 中是否有combo
        $combo_res = $this->orm->table('tb_sys_product_set_info')
            ->whereIn('product_id', $product_id_list)
            ->select(['product_id', 'set_product_id'])
            ->get();
        $combo_res = obj2array($combo_res);
        //取combo中的一个子sku
        $map = array_combine(array_column($combo_res, 'product_id'), array_column($combo_res, 'set_product_id'));
        //使用子sku替换product_id_list 中的combo sku
        $new_product_agreement_list = $product_agreement_list;
        foreach ($new_product_agreement_list as &$val) {
            if (isset($map[$val['product_id']])) {
                $val['product_id'] = $map[$val['product_id']];
            }
        }
        //查询lock_log 表统计数量
        $sell_num = $this->orm->table(DB_PREFIX . 'product_lock_log as log')
            ->leftJoin(DB_PREFIX . 'product_lock as lock', 'lock.id', '=', 'log.product_lock_id')
            ->leftJoin(DB_PREFIX . 'order as ord', function (Builder $query) {
                $query->whereRaw('log.transaction_id=ord.order_id')
                    ->where('log.change_type', '=', 1);
            })
            ->where(function (Builder $query) {
                $query->where([
                    ['log.change_type', '=', 1],
                    ['ord.order_status_id', '=', OcOrderStatus::COMPLETED]
                ])->orWhere('log.change_type', '=', 2);
            })
            ->where(function (Builder $query) use ($new_product_agreement_list) {
                foreach ($new_product_agreement_list as $val) {
                    $query->orWhere([
                        ['lock.product_id', '=', $val['product_id']],
                        ['lock.agreement_id', '=', $val['agreement_id']]
                    ]);
                }
            })
            ->select(['lock.agreement_id', 'lock.product_id', 'lock.set_qty', 'lock.parent_product_id'])
            ->selectRaw('SUM(log.qty) as total')
            ->groupBy(['agreement_id', 'product_id'])
            ->get();
        $sell_num = obj2array($sell_num);
        $rtn = array();
        foreach ($sell_num as $k => $v) {
            //一个sku 要么是combo  要么不是combo ，不会存在不同协议中的sku有的是combo ，有的不是的情况
            if ($v['product_id'] != $v['parent_product_id']) {    //将子sku 替换回combo的sku
                $rtn[$v['agreement_id']][$v['parent_product_id']] = abs($v['total']) / $v['set_qty'];
            } else {
                $rtn[$v['agreement_id']][$v['product_id']] = abs($v['total']) / $v['set_qty'];
            }

        }
        return $rtn;
    }

    /**
     * 待处理数量
     * @return int
     * @version 现货保证金三期
     */
    // 现货保证金三期---copy from \yzc\catalog\model\account\product_quotes\margin
    public function countWaitProcess(){
        $customer_id = intval($this->customer->getId());
        $sql = "SELECT ";
        $sql .= "COUNT(id) AS cnt
            FROM tb_sys_margin_agreement
            WHERE seller_id={$customer_id}
            AND `status` IN (1,2) AND `is_bid` = 1";
        $query = $this->db->query($sql);

        return intval($query->row['cnt']);
    }

    /**
     * 待支付定金数量
     * @return int
     * @version 现货保证金三期
     */
    // 现货保证金三期---copy from \yzc\catalog\model\account\product_quotes\margin
    public function countWaitDepositPay(){
        $customer_id = intval($this->customer->getId());
        $sql = "SELECT ";
        $sql .= "COUNT(id) AS cnt
        FROM tb_sys_margin_agreement
        WHERE seller_id={$customer_id} AND `status`=3 AND `is_bid` = 1";

        $query = $this->db->query($sql);

        return intval($query->row['cnt']);
    }

    /**
     * 即将到期数量
     * @return int
     * @version 现货保证金三期
     */
    // 现货保证金三期---copy from \yzc\catalog\model\account\product_quotes\margin
    public function countDueSoon(){
        $customer_id = intval($this->customer->getId());

        $sql = "SELECT COUNT(id) AS cnt
        FROM tb_sys_margin_agreement
        WHERE seller_id={$customer_id} AND `status`=6 AND TIMESTAMPDIFF(DAY, NOW(), expire_time) < 7  AND TIMESTAMPDIFF(DAY, NOW(), expire_time) >=0 and expire_time>=NOW()";

        $query = $this->db->query($sql);
        $count_mwp = intval($query->row['cnt']);

        return $count_mwp;
    }

    /**
     * 查询未处理协商取消协议请求的数量
     * @param int $sellerId seller id
     *
     * @return int
     */
    public function countTerminationRequest($sellerId)
    {
        if (!$sellerId) {
            return 0;
        }
        //buyer或者seller申请取消协议的数量
        $date = date('Y-m-d H:i:s');
        $terminationRequestCount = $this->orm->table('tb_sys_margin_agreement as ma')
            ->leftJoin('tb_sys_margin_performer_apply as mpa', function ($join) {
                $join->on('ma.id', '=', 'mpa.agreement_id')
                    ->where('mpa.check_result', 0)
                    ->where('mpa.seller_approval_status', 0);
            })
            ->where(function ($query) use ($sellerId,$date) {
                $query->where(function ($query1) use ($sellerId,$date) {
                    $query1 ->where('seller_id', $sellerId)
                        ->where('ma.status', 6)
                        ->where('ma.termination_request', 1)
                        ->where('ma.expire_time', '>', $date);
                });
                $query->orWhere(function ($query1) use ($sellerId, $date) {
                    $query1->where('ma.seller_id', $sellerId)
                        ->where('ma.status', 6)
                        ->where('ma.expire_time', '>', date('Y-m-d H:i:s'))
                        ->whereNotNull('mpa.id');
                });
            })
            ->count();
        return $terminationRequestCount;
    }


    /**
     * 更新保证金合同状态  -- seller执行
     * @param int $seller_id
     * @param string $contract_id
     * @param int $status_code
     * @return bool
     */
    public function updateMarginContractStatus($seller_id, $contract_id, $status_code)
    {
        $res = $this->orm
            ->table('tb_sys_margin_agreement')
            ->where([
                'agreement_id' => $contract_id,
                'seller_id' => (int)$seller_id,
            ])
            ->update([
                'status' => $status_code,
                'update_time' => Carbon::now(),
                'update_user' => (int)$this->customer->getId(),
            ]);
        return (bool)$res;
    }

    // 获取margin 信息
    public function getInfo($id = 0)
    {
        $customer_id = $this->customer->getId();

        $sql = "SELECT
        a.*,
        s.`name` AS status_name,
        s.color AS status_color,
        c2c.screenname,
        p.sku,
        p.mpn,
        p.quantity
    FROM
        tb_sys_margin_agreement AS a
        LEFT JOIN tb_sys_margin_agreement_status AS s ON a.`status` = s.margin_agreement_status_id
        LEFT JOIN oc_customerpartner_to_customer AS c2c ON a.seller_id = c2c.customer_id
        LEFT JOIN oc_product AS p ON a.product_id=p.product_id
    WHERE
        a.id={$id} AND
        a.seller_id ={$customer_id}";
        $query = $this->db->query($sql);
        return $query->row;
    }

    /**
     * 取消保证金协议
     *
     * @param int|null $margin_id
     * @param string|null $margin_agreement_id
     * @param int|null $seller_id
     * @param int|null $buyer_id
     * @param array $marginInfo
     * @return bool
     * @throws Exception
     */
    public function cancelMarginAgreement($margin_id, $margin_agreement_id, $seller_id, $buyer_id, $marginInfo=[])
    {
        if (!isset($seller_id) && !isset($buyer_id)) {
            return false;
        }
        if (!isset($margin_id) && !isset($margin_agreement_id)) {
            return false;
        }
        $where_claus = [];
        $check_claus = [];
        if (isset($margin_id)) {
            $where_claus[] = ['id', $margin_id];
            $check_claus[] = ['margin_id', $margin_id];
        }
        if (isset($margin_agreement_id)) {
            $where_claus[] = ['agreement_id', $margin_agreement_id];
            $check_claus[] = ['margin_agreement_id', $margin_agreement_id];
        }

        //查询订金product_id
        if ($marginInfo['advance_product_id']) {
            $advance_product_id = $marginInfo['advance_product_id'];
        }

        if (isset($seller_id)) {
            $where_claus[] = ['seller_id', $seller_id];
        }
        if (isset($buyer_id)) {
            $where_claus[] = ['buyer_id', $buyer_id];
        }

        $data = [
            'status' => 7,
            'update_time' => date('Y-m-d H:i:s', time())
        ];
        //更新合同状态
        $this->orm->table('tb_sys_margin_agreement')->where($where_claus)->update($data);

        if (isset($advance_product_id) && $advance_product_id) {
            $data = [
                'p.status' => 0,
                'p.quantity' => 0,
                'p.is_deleted' => 1,
                'ctp.quantity' => 0
            ];
            //更新商品状态
            $this->orm->table('oc_product as p')
                ->join('oc_customerpartner_to_product as ctp', 'p.product_id', '=', 'ctp.product_id')
                ->where('p.product_id', '=', $advance_product_id)
                ->update($data);
        }

        //N-624 退还库存
        if ($marginInfo) {
            app(ProductLockRepository::class)->releaseMarginLockQty($margin_id, 3);
        }

        return true;
    }

    /**
     * 保证金审批通过，创建保证金进程记录
     *
     * @param $data
     * @return bool
     */
    public function addMarginProcess($data)
    {
        return $this->orm->table('tb_sys_margin_process')->insert($data);
    }

    /**
     * 根据协议编号获取协议详细信息
     * @param string|null $agreement_id tb_sys_margin_agreement.agreement_id
     * @param int|null $seller_id tb_sys_margin_agreement.seller_id
     * @param int|null $agreement_key tb_sys_margin_agreement.id
     * @return array
     */
    public function getMarginAgreementDetail($agreement_id = null, $seller_id = null, $agreement_key = null)
    {
        if (isset($agreement_id) || isset($agreement_key)) {
            $sql = "SELECT
                      ma.`id`,
                      ma.`agreement_id`,
                      ma.`seller_id`,
                      ctc.screenname AS seller_name,
                      ma.`day`,
                      ma.`num`,
                      ma.`price` AS unit_price,
                      ma.`money` AS sum_price,
                      ma.`create_time` AS applied_time,
                      ma.`update_time` AS update_time,
                      ma.`status`,
                      ma.`effect_time`,
                      ma.`expire_time`,
                      mas.`name` AS status_name,
                      mas.`color` AS status_color,
                      p.`product_id`,
                      p.`sku`,
                      p.`mpn`,
                      p.`quantity` AS available_qty,
                      buyer.`customer_id` AS buyer_id,
                      buyer.`nickname`,
                      buyer.`user_number`,
                      buyer.`customer_group_id`
                    FROM
                      `tb_sys_margin_agreement` ma
                      INNER JOIN oc_product p
                        ON p.`product_id` = ma.`product_id`
                      INNER JOIN oc_customer buyer
                        ON buyer.`customer_id` = ma.`buyer_id`
                      INNER JOIN oc_customerpartner_to_customer ctc
                        ON ctc.customer_id = ma.seller_id
                      LEFT JOIN `tb_sys_margin_agreement_status` mas
                        ON ma.`status` = mas.`margin_agreement_status_id`
                    WHERE 1=1";
            if (isset($agreement_id)) {
                $sql .= " AND ma.`agreement_id` = '" . $agreement_id . "'";
            }
            if (isset($agreement_key)) {
                $sql .= " AND ma.`id` = '" . $agreement_key . "'";
            }
            if (isset($seller_id)) {
                $sql .= " AND ma.seller_id = " . $seller_id;
            }
//            echo $sql;die();
            return $this->db->query($sql)->row;
        }
    }


    /**
     * 获取共同履约人
     * @param int $agreement_id
     * @return array
     */
    public function get_common_performer($agreement_id){
        $res=$this->orm->table(DB_PREFIX.'agreement_common_performer as acp')
            ->leftJoin(DB_PREFIX.'customer as c','c.customer_id', '=', 'acp.buyer_id')
            ->where([
                ['acp.agreement_id','=',$agreement_id],
                ['acp.agreement_type','=',0]
            ])
            ->select(['acp.buyer_id','acp.is_signed','c.nickname','c.user_number','c.customer_group_id'])
            ->get();
        return obj2array($res);
    }

    /**
     * 根据协议编号获取保证金缴纳证明信息
     *
     * @param string $agreement_id tb_sys_margin_agreement.agreement_id
     * @param int $sellerId 非必填
     * @return array
     * @see app(MarginRepository::class)->getMarginCheckDetail($agreementIdList); 可以更改调用此方法 ps 传参需要更改
     */
    public function getMarginCheckDetail($agreement_id, $sellerId=0)
    {
        if (isset($agreement_id)) {
            $sql = "SELECT
                      mp.`margin_agreement_id` AS agreement_id,
                      ctp.customer_id AS seller_id,
                      buyer.`customer_id` AS buyer_id,
                      buyer.`nickname` AS buyer_nickname,
                      buyer.`user_number` AS buyer_user_number,
                      buyer.`customer_group_id`,
                      p.`sku`,
                      p.`mpn`,
                      pd.`name` AS product_name,
                      mor.`rest_order_id`,
                      o.`total` AS op_total,
                      o.`order_status_id`,
                      o.`date_added` AS purchase_date,
                      o.delivery_type,
                      op.`price` AS unit_price,
                      op.service_fee_per,
                      op.`order_product_id`,
                      op.`product_id`,
                      op.`quantity`,
                      op.`tax`,
                      op.freight_per,
                      op.package_fee,
                      op.coupon_amount,
                      op.campaign_amount,
                      ifnull(op.freight_per,0)+ifnull(op.package_fee,0) as freight_per_unit,
                      round(op.price,2)*op.quantity c2oprice
                    FROM
                      `tb_sys_margin_process` mp
                      INNER JOIN `tb_sys_margin_order_relation` mor
                        ON mp.`id` = mor.`margin_process_id`
                      INNER JOIN oc_order o
                        ON o.`order_id` = mor.`rest_order_id`
                      INNER JOIN oc_order_product op
                        ON op.`order_id` = o.`order_id` AND mp.`rest_product_id` = op.`product_id`
                      LEFT JOIN oc_product p
                        ON p.`product_id` = op.`product_id`
                      LEFT JOIN oc_product_description pd
                        ON pd.`product_id` = op.`product_id`
                      LEFT JOIN oc_customer buyer
                        ON buyer.`customer_id` = o.`customer_id`
                      LEFT JOIN oc_customerpartner_to_product ctp
                        ON ctp.product_id = op.product_id
                    WHERE mp.`margin_agreement_id` = '" . $agreement_id . "'";
            if (intval($sellerId) > 0) {
                $sql .= ' AND ctp.customer_id=' . $sellerId;
            }
            return $this->db->query($sql)->rows;
        }
    }

    /**
     * 获取保证金协议进度信息
     *
     * @param string $agreement_id tb_sys_margin_agreement.agreement_id
     * @return array
     */
    public function getMarginProcessDetail($agreement_id)
    {
        static $agree_info = [];
        if (isset($agree_info[$agreement_id])) {
            return $agree_info[$agreement_id];
        }
        $sql = "SELECT ma.seller_id,ma.buyer_id,p.`product_id` AS original_product_id,p.`sku` AS original_sku,p.`mpn` AS original_mpn,dp.`sku` AS deposit_sku,mp.*
                  FROM `tb_sys_margin_process` mp INNER JOIN `tb_sys_margin_agreement` ma ON ma.id = mp.margin_id
                  INNER JOIN oc_product p ON p.`product_id` = ma.`product_id`
                  INNER JOIN oc_product dp ON dp.`product_id` = mp.`advance_product_id`
                  WHERE mp.`margin_agreement_id` = '" . $agreement_id . "'";
        $agree_info[$agreement_id] = $this->db->query($sql)->row;

        return  $agree_info[$agreement_id];
    }

    /**
     * 批量获取保证金协议进度信息
     * @param array $agreement_ids tb_sys_margin_agreement.agreement_id
     * @return Generator|null
     */
    public function getMarginProcessDetailByAgreementIds($agreement_ids)
    {
        if (empty($agreement_ids)) {
            return null;
        }
        return $this->orm->table('tb_sys_margin_process as mp')
            ->select([
                'ma.seller_id', 'ma.buyer_id', 'p.product_id AS original_product_id',
                'p.sku AS original_sku', 'p.mpn AS original_mpn', 'dp.sku AS deposit_sku', 'mp.*'
            ])
            ->join('tb_sys_margin_agreement as ma', 'ma.id', '=', 'mp.margin_id')
            ->join('oc_product as p', 'p.product_id', '=', 'ma.product_id')
            ->join('oc_product as dp', 'dp.product_id', '=', 'mp.advance_product_id')
            ->whereIn('mp.margin_agreement_id', (array)$agreement_ids)
            ->cursor();
    }

    /**
     * 根据协议主键获取保证金协议进度信息
     *
     * @param int $margin_id 协议主键
     * @return array
     */
    public function getMarginProcessDetailByMarginId($margin_id)
    {
        $margin_id = intval($margin_id);
        $sql = "SELECT ma.seller_id,ctc.screenname as original_seller_name,ma.buyer_id,p.`product_id` AS original_product_id,p.`sku` AS original_sku,p.`mpn` AS original_mpn,mp.* FROM `tb_sys_margin_process` mp
                  INNER JOIN `tb_sys_margin_agreement` ma ON ma.id = mp.margin_id
                  INNER JOIN oc_product p ON p.`product_id` = ma.`product_id`
                  LEFT JOIN oc_customerpartner_to_customer ctc ON ctc.customer_id = ma.seller_id
                  WHERE mp.`margin_id` = '" . $margin_id . "'";
        return $this->db->query($sql)->row;
    }

    /**
     * 获取消息记录，按时间升序查询
     *
     * @param int $agreement_key
     * @return array
     */
    public function getMarginMessage($agreement_key)
    {
        $sql = "SELECT
                  mm.`customer_id` AS writer_id,
                  mm.`message`,
                  mm.`create_time` AS msg_date,
                  c.`user_number`,
                  c.`nickname`,
                  c.`customer_group_id`,
                  ctc.`is_partner`,
                  ctc.`screenname`
                FROM
                  `tb_sys_margin_agreement` ma
                  INNER JOIN `tb_sys_margin_message` mm
                    ON ma.`id` = mm.`margin_agreement_id`
                  LEFT JOIN oc_customer c
                    ON c.`customer_id` = mm.`customer_id`
                  LEFT JOIN oc_customerpartner_to_customer ctc
                    ON ctc.`customer_id` = c.`customer_id`
                WHERE ma.`id` = " . (int)$agreement_key . "
                ORDER BY ma.`create_time` ASC ";
        return $this->db->query($sql)->rows;
    }

    public function saveMarginMessage($data)
    {
        if (isset($data)) {
            $sql = "INSERT INTO `tb_sys_margin_message` (
                      `margin_agreement_id`,
                      `customer_id`,
                      `message`,
                      `create_time`,
                      `memo`
                    )
                    VALUES
                      (
                        (SELECT id FROM tb_sys_margin_agreement WHERE agreement_id = '" . $data['agreement_id'] . "'),
                        '" . $data['customer_id'] . "',
                        '" . $data['msg'] . "',
                        '" . $data['date'] . "',
                        ''
                      )";
            $this->db->query($sql);
            return $this->db->getLastId();
        }
    }

    /**
     * @param string $agreement_id
     * @return bool
     * user：wangjinxin
     * date：2020/3/11 15:38
     * @throws Exception
     */
    public function approveMargin($agreement_id): bool
    {
        $ret = true;
        $con = $this->orm->getConnection();
        try {
            $con->beginTransaction();
            // 获取协议信息
            $agree_info = $con
                ->table('tb_sys_margin_agreement')
                ->where('agreement_id', $agreement_id)
                ->first();
            if (!$agree_info) {
                throw new Exception(dprintf(
                    'File:{0} Line:{1} Can not find agreement info relate to agreement id: {2}'
                    , __FILE__, __LINE__, $agreement_id
                ));
            }
            $seller_id = $agree_info->seller_id;
            $this->load->model('account/product_quotes/margin_contract');
            $this->model_account_product_quotes_margin_contract
                ->updateMarginContractStatus($seller_id, $agreement_id, static::APPROVED);
            //将buyer添加到履约人
//            $common_performer=array(
//                'agreement_type'=>0,
//                'agreement_id'=>$agree_info->id,
//                'product_id'=>$agree_info->product_id,
//                'buyer_id'=>$agree_info->buyer_id,
//                'is_signed'=>1,
//                'create_user_name'=>$this->customer->getId(),
//                'create_time'=>date('Y-m-d H:i:s',time())
//            );
//            $con->table(DB_PREFIX.'agreement_common_performer')->insert($common_performer);
            $con->commit();
        } catch (Exception $e) {
            $con->rollback();
            $ret = false;
        }

        return $ret;
    }


    /**
     * 和返点的超时机制一样
     *
     * 检查pending状态的返点合同，如果创建时间超过了一天，需要更新为超时状态。
     *
     * 注意：因为要实时展示更新超时状态，目前没有用定时器或者其他定时任务之类的实现方法。
     * 目前以在buyer或者seller等用户查询合同信息时进行先更新后查询返回结果。
     * 所以，如果后续要开发与查询保证金协议相关的需求，需要执行此方法判断更新超时状态。
     * @param null $agreement_id
     * @return int
     * @deprecated 已使用定时任务更新超时状态，此方法废弃 2019/11/27
     * @author chenyang 2019-09-27
     */
    public function checkAndUpdateMarginTimeout($agreement_id = null)
    {
        $sql = "UPDATE
                  tb_sys_margin_agreement
                SET
                  `status` = 5,
                  `update_time` = NOW(),
                  `update_user` = '0'
                WHERE (`status` = 1 OR `status` = 2)
                  AND TIMESTAMPDIFF(DAY, update_time, NOW()) >= `period_of_application`";
        if (isset($agreement_id)) {
            $sql .= " AND agreement_id = '" . $agreement_id . "'";
        }
        $query = $this->db->query($sql);
        return $query->num_rows;
    }

    /**
     * 根据采购订单号，查询相关联的保证金协议信息
     * @param int $order_id oc_order表的order_id
     * @param int|null $seller_id
     * @param bool $fuzzy_check 是否启用order_id的模糊匹配
     * @return array
     */
    public function getSellerMarginByOrderId($order_id, $seller_id = null, $fuzzy_check = false)
    {
        if (empty($order_id) && empty($seller_id)) {
            return null;
        }
        $sql = "SELECT
                  ma.*,
                  p.sku AS originalSku,
                  p.mpn AS originalMpn,
                  mor.`rest_order_id`,
                  mp.advance_order_id
                FROM
                  `tb_sys_margin_agreement` ma
                  INNER JOIN oc_product p
                    ON p.product_id = ma.product_id
                  LEFT JOIN `tb_sys_margin_process` mp
                    ON ma.`id` = mp.`margin_id`
                  LEFT JOIN tb_sys_margin_order_relation mor
                    ON mp.`id` = mor.`margin_process_id`
                WHERE 1=1 ";

        if (!empty($seller_id)) {
            $sql .= " AND ma.seller_id = " . (int)$seller_id;
        }
        if (!empty($order_id)) {
            if ($fuzzy_check) {
                $sql .= " AND (mp.`advance_order_id` LIKE '%" . (int)$order_id . "%' OR mor.`rest_order_id` LIKE '%" . (int)$order_id . "%')";
            } else {
                $sql .= " AND (mp.`advance_order_id` = '" . (int)$order_id . "' OR mor.`rest_order_id` = '" . (int)$order_id . "')";
            }
        }
        return $this->db->query($sql)->rows;
    }

    /**
     * 根据指定用户（国别信息）获得保证金需求的服务店铺sellerid
     *
     * @param int $original_seller
     * @return bool
     */
    public function getMarginServiceStoreId($original_seller)
    {
        $sql = "SELECT * FROM oc_customer WHERE customer_id= " . intval($original_seller);
        $query = $this->db->query($sql);
        $seller = $query->row;
        if(!$seller){
            return 0;
        }
        $country_id = intval($seller['country_id']);//81 Germany, 107 Japan, 222 UK, 223 US
        switch ($country_id) {
            case 81:
                $sql = "SELECT * FROM oc_customer WHERE email='DE-SERVICE@oristand.com'";
                break;
            case 107:
                $sql = "SELECT * FROM oc_customer WHERE email='servicejp@gigacloudlogistics.com'";
                break;
            case 222:
                $sql = "SELECT * FROM oc_customer WHERE email='serviceuk@gigacloudlogistics.com'";
                break;
            case 223:
                $sql = "SELECT * FROM oc_customer WHERE email='service@gigacloudlogistics.com'";
                break;
            default:
                return null;
                break;
        }
        $query = $this->db->query($sql);
        $seller_new = $query->row;
        $seller_id_new = intval($seller_new['customer_id']);
        return $seller_id_new;
    }

    /**
     * 根据指定用户（国别信息）获得保证金需求的包销店铺sellerid
     *
     * @param int $original_seller
     * @return bool
     */
    public
    function getMarginBxStoreId($original_seller)
    {
        $sql = "SELECT * FROM oc_customer WHERE customer_id=" . intval($original_seller);
        $query = $this->db->query($sql);
        $seller = $query->row;
        if(!$seller){
            return 0;
        }
        $country_id = intval($seller['country_id']);//81 Germany, 107 Japan, 222 UK, 223 US
        $accounting_type = intval($seller['accounting_type']);//1 内部, 2 外部
        switch ($country_id) {
            case 81:
                $sql = "SELECT * FROM oc_customer WHERE email='DX_B@oristand.com'";
                break;
            case 107:
                $sql = "SELECT * FROM oc_customer WHERE email='nxb@gigacloudlogistics.com'";
                break;
            case 222:
                $sql = "SELECT * FROM oc_customer WHERE email='UX_B@oristand.com'";
                break;
            case 223:
                //美国区分内外店铺
                if ($accounting_type == 1) {
                    $sql = "SELECT * FROM oc_customer WHERE email='bxo@gigacloudlogistics.com'";
                } elseif ($accounting_type == 2) {
                    $sql = "SELECT * FROM oc_customer WHERE email='bxw@gigacloudlogistics.com'";
                }
                break;
            default:
                return false;
                break;
        }
        $query = $this->db->query($sql);
        $seller_new = $query->row;
        return intval($seller_new['customer_id'] ?? 0);

    }

    /**
     * 查询某个seller的所有保证金采购订单号
     *
     * @param int $seller_id  签订合同的seller
     * @return array
     */
    public
    function getMarginOrderIdBySellerId($seller_id)
    {
        $ret = [];
        if (!isset($seller_id)) {
            return $ret;
        }
        $sql = "SELECT
                  mp.`advance_order_id`,
                  mor.`rest_order_id`
                FROM
                  tb_sys_margin_agreement ma
                  INNER JOIN tb_sys_margin_process mp
                    ON mp.`margin_id` = ma.`id`
                  LEFT JOIN tb_sys_margin_order_relation mor
                    ON mor.`margin_process_id` = mp.`id`
                WHERE ma.`seller_id` = " . (int)$seller_id . "
                  AND mp.`advance_order_id` IS NOT NULL ";
        $rows = $this->db->query($sql)->rows;
        if (!empty($rows)) {
            foreach ($rows as $row) {
                if (isset($row['advance_order_id']) && !in_array($row['advance_order_id'], $ret)) {
                    $ret[] = $row['advance_order_id'];
                }
                if (isset($row['rest_order_id']) && !in_array($row['rest_order_id'], $ret)) {
                    $ret[] = $row['rest_order_id'];
                }
            }
        }
        return $ret;
    }

    public function getMarginBxStoreIdByCountryId($country_id)
    {
        $country_id = intval($country_id);//81 Germany, 107 Japan, 222 UK, 223 US
        switch ($country_id) {
            case 81:
                $sql = "SELECT customer_id FROM oc_customer WHERE email='DX_B@oristand.com' AND status=1";
                break;
            case 107:
                $sql = "SELECT customer_id FROM oc_customer WHERE email='nxb@gigacloudlogistics.com' AND status=1";
                break;
            case 222:
                $sql = "SELECT customer_id FROM oc_customer WHERE email='UX_B@oristand.com' AND status=1";
                break;
            case 223:
                //美国区分内外店铺
                $sql = "SELECT customer_id FROM oc_customer WHERE (email='bxo@gigacloudlogistics.com' OR email='bxw@gigacloudlogistics.com') AND status=1";
                break;
            default:
                return false;
                break;
        }
        $rows = $this->db->query($sql)->rows;
        $seller_ids = array();
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $seller_ids[] = $row['customer_id'];
            }
        }
        return $seller_ids;
    }

    /**
     * 转换seller的sku为存在的保证金sku;
     * 原SKU->订金SKU
     *
     * @param int $customer_id
     * @param string $sku_mpn
     * @param int|null $advance_order_id
     * @return array
     */
    public function convertSku2MarginSku($customer_id, $sku_mpn, $advance_order_id = null)
    {
        if (isset($customer_id) && isset($sku_mpn)) {
            $sql = "SELECT DISTINCT
                  ap.`sku`
                FROM
                  tb_sys_margin_agreement ma
                  INNER JOIN oc_product p
                    ON ma.`product_id` = p.`product_id`
                  INNER JOIN tb_sys_margin_process mp
                    ON mp.`margin_id` = ma.`id`
                  INNER JOIN oc_product ap
                    ON ap.`product_id` = mp.`advance_product_id`
                WHERE ma.`seller_id` = " . (int)$customer_id . "
                  AND (
                    p.`sku` LIKE '%" . $this->db->escape($sku_mpn) . "%'
                    OR p.`mpn` LIKE '%" . $this->db->escape($sku_mpn) . "%'
                  )";
            if (isset($advance_order_id)) {
                $sql .= " AND mp.advance_order_id = " . (int)$advance_order_id;
            }
            $rows = $this->db->query($sql)->rows;
            if (!empty($rows)) {
                $ret = [];
                foreach ($rows as $row) {
                    $ret[] = $row['sku'];
                }
                return $ret;
            }
        }
    }

    /**
     * 查找buyer的保证金尾款商品的相应信息
     *
     * @param int $buyer_id
     * @param string|null $item_code
     * @param int|null $product_id
     * @return array
     */
    public function getMarginProductForBuyer($buyer_id,$item_code = null,$product_id = null){
        $sql = "SELECT
                  ma.`agreement_id`,
                  ma.`status` AS margin_status,
                  IF(ma.`expire_time` > NOW() OR (ma.`expire_time` IS NULL AND TIMESTAMPDIFF(DAY, ma.create_time, NOW()) < `period_of_application`), TRUE, FALSE) AS is_valid,
                  ma.`effect_time`,
                  ma.`expire_time`,
                  p.`product_id`,
                  p.`quantity`,
                  p.`status` AS product_status
                FROM
                  tb_sys_margin_agreement ma
                  INNER JOIN tb_sys_margin_process mp
                    ON ma.`id` = mp.`margin_id`
                  INNER JOIN oc_product p
                    ON (p.`product_id` = mp.`rest_product_id` OR p.`product_id` = mp.`advance_product_id`)
                WHERE ma.`buyer_id` = " . (int)$buyer_id;
        if (!empty($item_code)) {
            $sql .= " AND p.`sku` = '" . $this->db->escape($item_code) . "' ";
        }
        if (!empty($product_id)) {
            $sql .= " AND p.`product_id` = '" . (int)$product_id . "' ";
        }
        $rows = $this->db->query($sql)->rows;
        if (!empty($rows)) {
            $ret = [];
            foreach ($rows as $row) {
                $ret[$row['product_id']] = [
                    'agreement_id' => $row['agreement_id'],
                    'margin_status' => $row['margin_status'],
                    'margin_is_valid' => $row['is_valid'],
                    'qty' => $row['quantity'],
                    'product_status' => $row['product_status'],
                    'effect_time' => $row['effect_time'],
                    'expire_time' => $row['expire_time']
                ];
            }
            return $ret;
        }
    }

   //seller收到的处于Applied、Pending状态的保证金申请个数
    //2020年3月17日
    //modify by zjg
    //添加3和6状态  Approved，Sold
    public function marginAppliedCount($seller_id)
    {
        return $this->orm->table('tb_sys_margin_agreement')
            ->where(function (Builder $query) {
                $query->where(function (Builder $sub_query) {
                        $sub_query->whereIn('status', [1, 2, 3])
                            ->where('is_bid', 1);
                    })
                    ->orWhere(function (Builder $sub_query) {
                        $sub_query->where('status', '=', 6)
                            ->whereRaw('TIMESTAMPDIFF(DAY, NOW(), expire_time) < 7 and TIMESTAMPDIFF(DAY, NOW(), expire_time) >=0 and expire_time>=NOW()');
                    })
                    ->orWhere(function (Builder $sub_query) {
                        $sub_query->where('status', 6)
                            ->where('termination_request', 1)
                            ->where('expire_time', '>', date('Y-m-d H:i:s'));
                    });
            })
            ->where('seller_id', $seller_id)
            ->count();
    }


    public function get_product_seller_id($product_id)
    {
        $res = $this->orm->table(DB_PREFIX . 'customerpartner_to_product')
            ->where('product_id', '=', $product_id)
            ->select(['customer_id'])->first();
        return $res ? obj2array($res) : null;
    }


    //获取当前store的图标
    public function get_avatar($seller_id){
        $images=$this->orm->table(DB_PREFIX.'customerpartner_to_customer')
            ->where('customer_id','=',$seller_id)
            ->select(['avatar'])
            ->first();
        $images=obj2array($images);
        return $images['avatar'];

    }

    /**
     * 获取保证金订单的合伙人申请
     * @param string $agreementId
     * @param bool $isFail 是否获取失败的
     *
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null null就是不存在，如果存在会返回对象[]
     */
    public function getPerformerApply($agreementId, $isFail = false)
    {
        //同一时间只可能有一个审核请求，所以就不做其他考虑了
        $model = $this->orm->table('tb_sys_margin_performer_apply as mpa')
            ->leftJoin(DB_PREFIX . 'customer as c', 'c.customer_id', '=', 'mpa.performer_buyer_id')
            ->where('mpa.agreement_id', $agreementId)->orderBy('id','desc');

        if ($isFail) {
            $model = $model->whereIn('mpa.check_result', [0, 1, 2])
                ->whereIn('mpa.seller_approval_status', [0, 1, 2]);
        } else {
            $model = $model->whereIn('mpa.check_result', [0, 1])//平台未审核和已审核过的
            ->whereIn('mpa.seller_approval_status', [0, 1]);//seller审核和审核通过的
        }
        $res = $model->first([
                                  'mpa.id',
                                  'mpa.performer_buyer_id',
                                  'mpa.check_result',
                                  'mpa.seller_approval_status',
                                  'c.nickname',
                                  'c.user_number',
                                  'c.customer_group_id'
                              ]);
        return $res;
    }

    /**
     * seller审核共同履约人
     * 这里只做审核，其他验证请外面验证完
     * @see MarginService::setAgreementAuditPerformer()
     * @version 现货保证金三期
     * @param int $performerApplyId tb_sys_margin_performer_apply主键id
     * @param int $status           审核状态 1-通过 2-不通过
     * @param array $marginInfo     协议信息 必须包含seeler id 和主键id
     *
     * @return bool
     */
    public function auditPerformer($performerApplyId,$status,$marginInfo)
    {
        if (!in_array($status, [1, 2])) {
            return false;
        }
        $this->orm->getConnection()->beginTransaction();
        try {
            $res = $this->orm->table('tb_sys_margin_performer_apply')
                ->where('id', $performerApplyId)
                ->update([
                             'seller_approval_status' => $status,
                             'seller_approval_time'   => date('Y-m-d H:i:s')
                         ]);
            if (!$res) {
                throw new \Exception();
            } else {
                //发送消息
                if ($status == 1) {

                    $reason = 'Seller has approved the Add a Partner request, Marketplace approval currently pending.';
                } else {
                    $reason = 'Seller has denied the Add a Partner request.';
                }
                $data = [
                    'margin_agreement_id' => $marginInfo['id'],
                    'customer_id'         => $marginInfo['seller_id'],
                    'message'             => $reason,
                    'create_time'         => date('Y-m-d H:i:s'),
                    'memo'                => 'Seller Audit Performer Request',
                ];
                $this->orm->table('tb_sys_margin_message')
                    ->insert($data);
            }
            $this->orm->getConnection()->commit();
        } catch (\Exception $exception) {
            $this->orm->getConnection()->rollBack();
            $res = false;
        }


        return boolval($res);
    }
}
