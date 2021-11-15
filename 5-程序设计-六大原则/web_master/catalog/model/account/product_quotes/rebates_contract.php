<?php

use App\Components\Storage\StorageCloud;
use App\Components\UniqueGenerator\Enums\ServiceEnum;
use App\Enums\Order\OcOrderStatus;
use App\Enums\Pay\PayCode;
use App\Helper\CountryHelper;
use App\Models\Customer\Customer;
use App\Models\Rebate\RebateAgreementTemplateItem;
use App\Repositories\Common\SerialNumberRepository;
use App\Widgets\VATToolTipWidget;
use Carbon\Carbon;


/**
 * Class ModelAccountProductQuotesRebatesContract
 * @property ModelAccountBalanceVirtualPayRecord $model_account_balance_virtual_pay_record
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCustomerPartnerDelicacyManagement $model_CustomerPartner_DelicacyManagement
 * @property ModelExtensionModuleProductShow $model_extension_module_product_show
 * @property ModelMessageMessage $model_message_message
 * @property ModelToolImage $model_tool_image
 * */
class ModelAccountProductQuotesRebatesContract extends Model
{
    function getProductInformationByProductId($product_Id)
    {
        $sql = "SELECT p.`product_id`,ctp.`customer_id` AS seller_id,p.`quantity`
                FROM oc_product p INNER JOIN oc_customerpartner_to_product ctp ON p.`product_id` = ctp.`product_id`
                WHERE p.`product_id` = " . (int)$product_Id;
        return $this->db->query($sql)->row;
    }

    function saveRebatesContract($data)
    {
        $sql = "INSERT INTO `tb_sys_rebate_contract` (
                  `contract_id`,
                  `buyer_id`,
                  `seller_id`,
                  `product_id`,
                  `day`,
                  `qty`,
                  `price`,
                  `rebates_amount`,
                  `rebates_price`,
                  `limit_price`,
                  `effect_time`,
                  `expire_time`,
                  `clauses_id`,
                  `status`,
                  `memo`,
                  `create_time`,
                  `create_username`,
                  `update_time`,
                  `update_username`,
                  `program_code`
                )
                VALUES
                  (
                    CONCAT(DATE_FORMAT(NOW(),'%Y%m%d'),LPAD((SELECT AUTO_INCREMENT FROM information_schema.`TABLES` WHERE TABLE_SCHEMA='" . DB_DATABASE . "' AND TABLE_NAME='tb_sys_rebate_contract'),6,0)),
                    '" . $data['buyer_id'] . "',
                    '" . $data['seller_id'] . "',
                    '" . $data['product_id'] . "',
                    '" . $data['day'] . "',
                    '" . $data['qty'] . "',
                    '" . $data['price'] . "',
                    '" . $data['rebates_amount'] . "',
                    '" . $data['rebates_price'] . "',
                    '" . $data['limit_price'] . "',
                    NULL,
                    NULL,
                    '" . $data['clauses_id'] . "',
                    '" . $data['status'] . "',
                    '" . $data['memo'] . "',
                    NOW(),
                    '" . $data['buyer_id'] . "',
                    NOW(),
                    '" . $data['buyer_id'] . "',
                    '" . $data['program_code'] . "'
                  )";

        $this->db->query($sql);
        $contract_key = $this->db->getLastId();
        $sql_msg = "INSERT INTO `tb_sys_rebate_message` (
                      `contract_key`,
                      `writer`,
                      `message`,
                      `create_time`,
                      `memo`
                    )
                    VALUES
                      (
                        '" . $contract_key . "',
                        '" . $data['buyer_id'] . "',
                        '" . $data['message'] . "',
                        NOW(),
                        ''
                      )";
        $this->db->query($sql_msg);
        return $contract_key;
    }

    /**
     * 返回返点合同表的基础数据
     *
     * @param int $agreement_id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|null|object
     */
    function getRebatesContractDetailByAgreementId($agreement_id)
    {
        $query_obj = $this->orm->table('tb_sys_rebate_contract')->where('contract_id',intval($agreement_id))->first();
        return $query_obj;
    }
    /**
     * 查询模板总数
     *
     * @param $data
     * @return mixed
     */
    public function getRebatesContractTotal($data)
    {
        if (isset($data)) {
            $sql = "SELECT
                      COUNT(*) AS cnt
                    FROM
                      `tb_sys_rebate_contract` rc
                      INNER JOIN oc_product p
                        ON rc.`product_id` = p.`product_id`
                      INNER JOIN oc_customerpartner_to_product ctc
                        ON p.`product_id` = ctp.`product_id`
                      INNER JOIN oc_customerpartner_to_customer ctc
                        ON ctc.`customer_id` = ctp.`customer_id`
                      INNER JOIN oc_customer c
                        ON rc.`buyer_id` = c.`customer_id` ";
            $implode = array();

            if (isset($data['buyer_id'])) {
                $implode[] = "rc.`buyer_id` = " . (int)$data['buyer_id'];
            }

            if (isset($data['seller_id'])) {
                $implode[] = "rc.`seller_id` = " . (int)$data['seller_id'];
            }

            if (isset($data['contract_id'])) {
                $implode[] = " rc.`contract_id` like '%" . $data['contract_id'] . "%'";
            }

            if (isset($data['filter_store_name']) && !is_null($data['filter_store_name'])) {
                $implode[] = "LCASE(ctc.`screenname`) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_store_name'])) . "%'";
            }

            if (isset($data['filter_buyer_name']) && !is_null($data['filter_buyer_name'])) {
                $implode[] = " (c.nickname like '%" . $data['filter_buyer_name'] . "%' or c.user_number like '%" . $data['filter_buyer_name'] . "%')";
            }

            if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
                $implode[] = "rc.`status` = " . (int)$data['filter_status'];
            }

            if (isset($data['filter_sku_mpn']) && !empty($data['filter_sku_mpn'])) {
                $implode[] = " (p.sku like '%" . $data['filter_sku_mpn'] . "%' or p.mpn like '%" . $data['filter_sku_mpn'] . "%')";
            }

            if (!empty($data['filter_date_from'])) {
                $implode[] = "rc.`update_time` >= '" . $this->db->escape(utf8_strtolower($data['filter_date_from'])) . "'";
            }
            if (!empty($data['filter_date_to'])) {
                $implode[] = "rc.`update_time` <= '" . $this->db->escape(utf8_strtolower($data['filter_date_to'])) . "'";
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
    public function getRebatesContractDisplay($data)
    {
        $this->checkAndUpdateRebateTimeout();
        if (isset($data)) {
            $sql = "SELECT
                      rc.`contract_id`,
                      p.`product_id`,
                      p.`sku`,
                      p.`mpn`,
                      p.`image`,
                      p.`freight`,
                      ctc.`screenname`,
                      c.`nickname`,
                      c.`customer_group_id`,
                      c.`user_number`,
                      c.`customer_id`,
                      c.`customer_group_id`,
                      rc.`seller_id`,
                      rc.`day`,
                      rc.`qty`,
                      rc.`price`,
                      rc.`rebates_discount`,
                      rc.`rebates_amount`,
                      rc.`rebates_price`,
                      rc.`status`,
                      rc.`update_time`
                    FROM
                      `tb_sys_rebate_contract` rc
                      INNER JOIN oc_product p
                        ON rc.`product_id` = p.`product_id`
                      INNER JOIN oc_customerpartner_to_product ctc
                        ON p.`product_id` = ctp.`product_id`
                      INNER JOIN oc_customerpartner_to_customer ctc
                        ON ctc.`customer_id` = ctp.`customer_id`
                      LEFT JOIN oc_customer c
                        ON rc.`buyer_id` = c.`customer_id` ";

            $implode = array();

            if (isset($data['buyer_id'])) {
                $implode[] = "rc.`buyer_id` = " . (int)$data['buyer_id'];
            }

            if (isset($data['seller_id'])) {
                $implode[] = "rc.`seller_id` = " . (int)$data['seller_id'];
            }

            if (isset($data['contract_id'])) {
                $implode[] = " rc.`contract_id` like '%" . $data['contract_id'] . "%'";
            }

            if (isset($data['filter_store_name']) && !is_null($data['filter_store_name'])) {
                $implode[] = "LCASE(ctc.`screenname`) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_store_name'])) . "%'";
            }

            if (isset($data['filter_buyer_name']) && !is_null($data['filter_buyer_name'])) {
                $implode[] = " (c.nickname like '%" . $data['filter_buyer_name'] . "%' or c.user_number like '%" . $data['filter_buyer_name'] . "%')";
            }

            if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
                $implode[] = "rc.`status` = " . (int)$data['filter_status'];
            }

            if (isset($data['filter_sku_mpn']) && !empty($data['filter_sku_mpn'])) {
                $implode[] = " (p.sku like '%" . $data['filter_sku_mpn'] . "%' or p.mpn like '%" . $data['filter_sku_mpn'] . "%')";
            }

            if (!empty($data['filter_date_from'])) {
                $implode[] = "rc.`update_time` >= '" . $this->db->escape(utf8_strtolower($data['filter_date_from'])) . "'";
            }
            if (!empty($data['filter_date_to'])) {
                $implode[] = "rc.`update_time` <= '" . $this->db->escape(utf8_strtolower($data['filter_date_to'])) . "'";
            }

            if (!empty($implode)) {
                $sql .= " WHERE " . implode(" AND ", $implode);
            }

            $sort_data = array(
                'rc.`update_time`',
                'c.nickname',
                'rc.status',
                'ctc.screenname'
            );

            if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
                $sql .= " ORDER BY " . $data['sort'];
            } else {
                $sql .= " ORDER BY rc.`update_time`";
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
     * 根据合同编号查找合同详情页的数据
     *
     * @param $contract_id
     * @return array
     */
    public function getRebatesContractDetailDisplay($contract_id)
    {
        $this->checkAndUpdateRebateTimeout();
        $sql = "SELECT
                  rc.`contract_id`,
                  rc.`product_id`,
                  rc.`status`,
                  rc.`update_time`,
                  rc.`effect_time`,
                  rc.`expire_time`,
                  rc.`day`,
                  rc.`qty`,
                  rc.`price`,
                  rc.`rebates_discount`,
                  rc.`rebates_amount`,
                  rc.`rebates_price`,
                  rc.`price_limit_percent`,
                  rc.`limit_price`,
                  rc.`limit_qty`,
                  p.`sku`,
                  p.`mpn`,
                  p.`image`,
                  ctc.`screenname` AS store_name,
                  c.`customer_id`,
                  c.`nickname`,
                  c.`user_number`,
                  c.`customer_group_id`,
                  c.`customer_id` AS buyer_id,
                  ctc.`customer_id` AS seller_id,
                  p.`product_id`,
                  pd.`name` AS product_name
                FROM
                  tb_sys_rebate_contract rc
                  INNER JOIN oc_product p
                    ON rc.`product_id` = p.`product_id`
                  INNER JOIN oc_customerpartner_to_product ctp
                    ON p.`product_id` = ctp.`product_id`
                  INNER JOIN oc_customerpartner_to_customer ctc
                    ON ctc.`customer_id` = ctp.`customer_id`
                  LEFT JOIN oc_product_description pd
                    ON p.`product_id` = pd.`product_id`
                  LEFT JOIN oc_customer c
                    ON rc.`buyer_id` = c.`customer_id`
                WHERE rc.`contract_id` = '" . $this->db->escape($contract_id) . "'";
        return $this->db->query($sql)->row;
    }

    /**
     * [getRebatesAgreementItem description]
     * @param int $agreement_id
     * @return array
     */
    public function  getRebatesAgreementItem($agreement_id){
        $map['ra.id'] = $agreement_id;
        $ret  = $this->orm->table(DB_PREFIX.'rebate_agreement_item as rai')
            ->leftJoin(DB_PREFIX.'rebate_agreement as ra','ra.id','=','rai.agreement_id')
            ->where($map)
            ->selectRaw('ra.*,rai.product_id,rai.template_price')
            ->get()
            ->map(function ($value){
                return (array)$value;
            })
            ->toArray();
        return $ret;
    }

    /**
     * @param $agreementId
     * @param $productIds
     * @return RebateAgreementTemplateItem[]|\Illuminate\Support\Collection
     */
    public function getRebateTemplateItems($agreementId, $productIds)
    {
        if (!$agreementId || empty($productIds)) {
            return collect();
        }

        $templateId = db('oc_rebate_agreement_template as t')
            ->join('oc_rebate_agreement as a', 't.id', '=', 'a.agreement_template_id')
            ->where('a.id', $agreementId)
            ->value('rebate_template_id');
        if (empty($templateId)) {
            return collect();
        }

        return RebateAgreementTemplateItem::query()->alias('i')
            ->joinRelations('rebateAgreementTemplate as t')
            ->where('t.rebate_template_id', $templateId)
            ->whereIn('i.product_id', $productIds)
            ->select('i.*')
            ->get()
            ->keyBy('product_id');
    }

    public function cancelRebatesContract($customer_id, $contract_id)
    {
        $sql = "UPDATE `tb_sys_rebate_contract` SET `status` = 0 WHERE contract_id = '" . $contract_id . "' AND buyer_id = " . (int)$customer_id;
        $this->db->query($sql);
    }

    /**
     * 获取消息记录，按时间升序查询
     *
     * @param $contract_id
     * @return array
     */
    public function getRebatesContractMessage($contract_id)
    {
        $sql = "SELECT
                  rm.`writer`,
                  rm.`message`,
                  rm.`create_time`,
                  c.`user_number`,
                  c.`nickname`,
                  ctc.`is_partner`,
                  ctc.`screenname`
                FROM
                  `tb_sys_rebate_contract` rc
                  INNER JOIN `tb_sys_rebate_message` rm
                    ON rc.`id` = rm.`contract_key`
                  INNER JOIN oc_customer c
                    ON c.`customer_id` = rm.`writer`
                  LEFT JOIN oc_customerpartner_to_customer ctc
                    ON ctc.`customer_id` = c.`customer_id`
                WHERE rc.`contract_id` = '" . $this->db->escape($contract_id) . "' ORDER BY rm.`create_time` ASC";
        return $this->db->query($sql)->rows;
    }
    public function getRebatesAgreementMessageByMessageId($message_id)
    {
        $list = $this->orm->table(DB_PREFIX.'rebate_message as m')
            ->leftJoin(DB_PREFIX.'customer as c','c.customer_id','=','m.writer' )
            ->leftJoin(DB_PREFIX.'customerpartner_to_customer as ctc','ctc.customer_id','=','c.customer_id')
            ->where('m.id',$message_id)
            ->select('m.writer','m.message','m.create_time', 'c.user_number',
                'c.nickname',
                'ctc.is_partner',
                'ctc.screenname')
            ->orderBy('m.create_time','asc')
            ->get()
            ->map(function ($value){
                return (array)$value;
            })
            ->toArray();
        return current($list);
    }
    public function getRebatesAgreementMessage($agreement_id)
    {
        $list = $this->orm->table(DB_PREFIX.'rebate_message as m')
            ->leftJoin(DB_PREFIX.'customer as c','c.customer_id','=','m.writer' )
            ->leftJoin(DB_PREFIX.'customerpartner_to_customer as ctc','ctc.customer_id','=','c.customer_id')
            ->where('m.agreement_id',$agreement_id)
            ->select('m.writer','m.message','m.create_time', 'c.user_number',
                'c.nickname',
                'ctc.is_partner',
                'ctc.screenname')
            ->orderBy('m.create_time','asc')
            ->get()
            ->map(function ($value){
                return (array)$value;
            })
            ->toArray();
        return $list;
    }

    /**
     * 根据messageId查找消息
     * @param int $messageId
     * @return array
     */
    public function getRebatesContractMessageByMessageId($messageId)
    {
        $sql = "SELECT
                  rm.`writer`,
                  rm.`message`,
                  rm.`create_time`,
                  c.`user_number`,
                  c.`nickname`,
                  ctc.`is_partner`,
                  ctc.`screenname`
                FROM
                   `tb_sys_rebate_message` rm
                  INNER JOIN oc_customer c
                    ON c.`customer_id` = rm.`writer`
                  LEFT JOIN oc_customerpartner_to_customer ctc
                    ON ctc.`customer_id` = c.`customer_id`
                WHERE rm.`id` = '" . (int)$messageId . "'";
        return $this->db->query($sql)->row;
    }

    public function saveRebatesContractMessage($data)
    {
        if (isset($data)) {
            $sql = "INSERT INTO `tb_sys_rebate_message` (
                      `contract_key`,
                      `writer`,
                      `message`,
                      `create_time`,
                      `memo`
                    )
                    VALUES
                      (
                        (SELECT id FROM tb_sys_rebate_contract WHERE contract_id = '" . $data['contract_id'] . "'),
                        '" . $data['customer_id'] . "',
                        '" . $data['msg'] . "',
                        '" . $data['date'] . "',
                        ''
                      )";
            $this->db->query($sql);
            return $this->db->getLastId();
        }
    }

    public function saveRebatesAgreementMessage($data)
    {
        return $this->orm->table(DB_PREFIX.'rebate_message')->insertGetId($data);
    }

    /**
     * 更新合同状态和生效时间
     *
     * @param $data
     * @return void
     */
    public function updateRebatesContractStatus($data)
    {
        if (!empty($data)) {
            $sql = "UPDATE
                  `tb_sys_rebate_contract`
                SET
                  `status` = " . $data['status'] . ",
                  update_username = '" . $data['update_username'] . "',
                  update_time = '" . $data['update_time'] . "'";
            if (isset($data['effect_time'])) {
                $sql .= ",effect_time = '" . $data['effect_time'] . "'";
            }
            if (isset($data['effect_time'])) {
                $sql .= ",expire_time = '" . $data['expire_time'] . "'";
            }
            $sql .= "WHERE contract_id = '" . $data['contract_id'] . "'";
            $this->db->query($sql);
        }
    }

    /**
     * [updateRebatesAgreementStatus description]
     * @param $data
     */
    public function updateRebatesAgreementStatus($data){
        $map['id'] = $data['id'];
        unset($data['id']);
        $this->orm->table(DB_PREFIX.'rebate_agreement')->where($map)->update($data);
    }


    /**
     * 检查pending状态的返点合同，如果创建时间超过了一天，需要更新为超时状态。
     *
     * 注意：因为要实时展示更新超时状态，目前没有用定时器或者其他定时任务之类的实现方法。
     * 目前以在buyer或者seller等用户查询合同信息时进行先更新后查询返回结果。
     * 所以，如果后续要开发与查询返点合同相关的需求，需要执行此方法判断更新超时状态。
     *
     * @author chenyang 2019-09-23
     * @param null $contract_id
     * @return int
     */
    public function checkAndUpdateRebateTimeout($contract_id = null)
    {
        $sql = "UPDATE
                  tb_sys_rebate_contract
                SET
                  `status` = 4,
                  `update_time` = NOW(),
                  `update_username` = 'update_username'
                WHERE `status` = 1
                  AND TIMESTAMPDIFF(DAY, create_time, NOW()) > 0";
        if (isset($contract_id)) {
            $sql .= " AND contract_id = '" . $contract_id . "'";
        }
        $query = $this->db->query($sql);
        return $query->num_rows;
    }

    /**
     * [checkAndUpdateRebateAgreementTimeout description]
     * @param int|null $agreement_id
     * @return int
     */
    public function checkAndUpdateRebateAgreementTimeout($agreement_id = null){
        $map = [
            'status' => 1,
        ];
        if($agreement_id){
            $map['id'] = $agreement_id;
        }
        $mapTime  = [
            ['create_time','<', date('Y-m-d H:i:s',time()-86400)]
        ];
        $ret  = $this->orm->table(DB_PREFIX.'rebate_agreement')
            ->where($map)
            ->where($mapTime)
            ->update([
                'status'=> 4,
                'update_time'=> date('Y-m-d H:i:s',time()),
                'update_user_name'=> 'update_user_name',
            ]);

        return $ret;
    }

    /**
     * 获取目前生效合同销售的数量情况
     *
     * @param int $product_id
     * @return array
     */
    public function getApprovedRebateContractSoldQty($product_id)
    {
        $sql = "SELECT
                  p.`quantity`,
                  SUM(op.`quantity`) AS sold_qty,
                  rc.`qty` AS contract_qty
                FROM
                  oc_order o
                  INNER JOIN oc_order_product op
                    ON o.`order_id` = op.`order_id`
                  RIGHT JOIN tb_sys_rebate_contract rc
                    ON rc.`buyer_id` = o.`customer_id`
                    AND rc.`product_id` = op.`product_id`
                  INNER JOIN oc_product p
                    ON p.`product_id` = rc.`product_id`
                  LEFT JOIN oc_product_quote pq
                    ON pq.`product_id` = op.`product_id`
                    AND pq.`customer_id` = o.`customer_id`
                WHERE rc.`product_id` = " . (int)$product_id . "
                  AND (
                    o.`order_status_id` = ".OcOrderStatus::COMPLETED."
                    OR o.`order_status_id` IS NULL
                  )
                  AND rc.`status` = 3
                  AND rc.`effect_time` < NOW()
                  AND rc.`expire_time` > NOW()
                  AND (
                    rc.`effect_time` < o.`date_added`
                    OR o.`date_added` IS NULL
                  )
                  AND (
                    rc.`expire_time` > o.`date_added`
                    OR o.`date_added` IS NULL
                  )
                  AND (
                    pq.`order_id` = 0
                    OR pq.`order_id` IS NULL
                  )
                GROUP BY p.`product_id`,
                  rc.`id`,
                  rc.`buyer_id`";
        return $this->db->query($sql)->rows;
    }


    /**
     * seller收到的处于Pending状态的返点申请个数
     * 返点四期
     * @param int $seller_id
     * @return int
     */
    public function rebatesAppliedCount($seller_id)
    {
        $seller_id = (int)$seller_id;
        $sql = "SELECT COUNT(*) AS cnt
    FROM oc_rebate_agreement
    WHERE seller_id={$seller_id}
        AND (`status`=1 OR rebate_result=6)";
        $query = $this->db->query($sql);
        return intval($query->row['cnt']);
    }

    /*
     * 返点返金 余额、虚拟账户
     * */
    public function rebatePayMethod($agreementId)
    {
        $lc = $this->orm->table('tb_sys_credit_line_amendment_record')
            ->where(['type_id'=>4, 'header_id'=>$agreementId])
            ->where('customer_id', $this->customer->getId())
            ->exists();
        $vp = $this->orm->table('oc_virtual_pay_record')
            ->where(['type'=>3, 'relation_id'=>$agreementId])
            ->where('customer_id', $this->customer->getId())
            ->exists();
        $refundTyp = [];
        if ($lc){
            $refundTyp[] = 'Line Of Credit';
        }
        if ($vp){
            $refundTyp[] = 'Virtual Pay';
        }

        return implode(' / ', $refundTyp);
    }


    /**
     * [getRebatesRequestList description]
     * @param bool $is_partner
     * @param int $agreement_id oc_rebate_agreement.id
     * @return array
     * @throws Exception
     */
    public function getRebatesRequestList($is_partner,$agreement_id){
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $base_info = $this->getAgreementInfo($agreement_id);
        //获取agreement 中详细的产品
        $product_list = $this->orm->table(DB_PREFIX.'rebate_agreement_item as ai')
            ->leftJoin(DB_PREFIX.'rebate_agreement_template_item as ati','ai.agreement_template_item_id','=','ati.id')
            ->leftJoin(DB_PREFIX.'product as p','p.product_id','=','ai.product_id')
            ->leftJoin(DB_PREFIX.'product_description as pd','pd.product_id','=','p.product_id')
            ->where('agreement_id',$agreement_id)
            ->selectRaw('ai.*,ati.price as t_template_price,ati.rebate_amount as t_rebate_amount
            ,ati.min_sell_price as t_min_sell_price,p.image,pd.name,p.sku,p.mpn')
            ->get()
            ->map(function ($value){
                return (array)$value;
            })
            ->toArray();
        $base_info['all_rebate_amount'] = 0;
        $base_info['all_count'] = 0;
        foreach($product_list as $key => &$value){
            $product_list[$key]['image_show'] = $this->model_tool_image->resize($value['image'], 40, 40);
            $product_list[$key]['tag_array'] = $this->model_catalog_product->getProductTagHtmlForThumb($value['product_id']);
            $product_list[$key]['product_link'] = $this->url->link('product/product',['product_id'=> $value['product_id']]);
        }

        $order = $this->getRebatesMoneyForBuyer($agreement_id);

        $base_info['all_count'] = $order['all_count'];
        $base_info['all_rebate_amount'] = $order['all_rebate_amount'];
        $base_info['all_rebate_amount_show'] =  $order['all_rebate_amount_show'];
        // 获取购买产品的实际数量
        // 获取购买产品可以返点的金额
        // 获取request的详情
        $request_list = $this->getRequestDetails($agreement_id);
        if($is_partner){
            $base_info['request_tips_show'] = 0;
        }
        if($base_info['rebate_result'] == 5){

            $base_info['request_tips_show'] = 1;

        }elseif($base_info['rebate_result'] == 6 && count($request_list) == 1 && $request_list[0]['process_status'] == 6){
            //申请了而且只有一次
            $base_info['request_tips_show'] = 1;
        }
        $ret['request_list'] = $request_list;
        $ret['order_list'] = $order['order_list'];
        $ret['base_info']  = $base_info;
        $ret['product_list']  = $product_list;
        return $ret;
    }

    /**
     * [getRequestDetails description]
     * @param int $agreement_id
     * @return array
     */
    public function getRequestDetails($agreement_id){
        $map['agreement_id'] = $agreement_id;
        $list = $this->orm->table(DB_PREFIX.'rebate_agreement_request')
            ->where($map)
            ->orderBy('create_time','desc')
            ->get()
            ->map(function ($value){
                return (array)$value;
            })
            ->toArray();
        if($list){

            foreach($list as $key => $value){
                $list[$key]['rebate_again'] = 0;
                $mapRequest['request_id'] = $value['id'];
                $img_list = $this->orm->table(DB_PREFIX.'rebate_agreement_request_file')
                    ->where($mapRequest)
                    ->get()
                    ->map(function ($v){
                        if($v->path){
                            // 需要处理数据库里存储的path
                            $v->path = StorageCloud::rebateRequest()->getUrl(StorageCloud::rebateRequest()->getRelativePath($v->path));
                        }
                        return (array)$v;
                    })
                    ->toArray();
                if($key == 0){
                    if($value['process_status'] == 8){
                        $list[$key]['rebate_again'] = 1;
                    }
                }


                $list[$key]['img_list'] = $img_list;
            }
        }



        return $list;

    }

    /**
     * [canSeeRebateRequest description]
     * @param int $agreement_id
     * @return bool
     */
    public function canSeeRebateRequest($agreement_id){
        $map['id'] = $agreement_id;
        $rebate_result = $this->orm->table(DB_PREFIX.'rebate_agreement')->where($map)->value('rebate_result');
        if($rebate_result >= 5){
            return true;
        }
        return false;
    }

    public function canSeeRebateTransaction($agreement_id){
        $map['id'] = $agreement_id;
        $result = $this->orm->table(DB_PREFIX.'rebate_agreement')->where($map)->value('status');
        if($result == 3){
            return true;
        }
        return false;
    }

    public function rebateAgreementCode($agreement_id){
        $map['id'] = $agreement_id;
        return $this->orm->table(DB_PREFIX.'rebate_agreement')->where($map)->value('agreement_code');

    }

    /**
     * [getRebatesAgreementList description]
     * @param bool $is_partner
     * @param int $agreement_id oc_rebate_agreement.id
     * @return array
     * @throws Exception
     */
    public function getRebatesAgreementList($is_partner,$agreement_id){
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $base_info = $this->getAgreementInfo($agreement_id);
        $template_info = $this->orm->table(DB_PREFIX.'rebate_agreement_template')
            ->where('id',$base_info['agreement_template_id'])
            ->select()
            ->get()
            ->map(function ($value){
                return (array)$value;
            })
            ->toArray();
        $template_info = current($template_info);

        $verify_column = [
            'day',
            'qty',
        ];
        if($is_partner){
            $language = $this->language->get('text_rebates_seller_agreement_diff');
            $verify_tips = $this->language->get('text_rebates_seller_times');
            $verify_invalid_language = $this->language->get('text_rebates_seller_invalid');
        }else{
            $language = $this->language->get('text_rebates_agreement_diff');
            $verify_tips = $this->language->get('text_rebates_times');
            $verify_invalid_language = $this->language->get('text_rebates_invalid');
        }

        foreach($verify_column as $key => $value){
            if(sprintf('%.2f',$base_info[$value]) != sprintf('%.2f',$template_info[$value])){
                $base_info[$value.'_tips'] =  sprintf($language,$template_info[$value]);
            }else{
                $base_info[$value.'_tips'] = null;
            }
        }

        //获取agreement 中详细的产品
        //agreement_rebate_template_id 感觉不太对
        $product_list = $this->orm->table(DB_PREFIX.'rebate_agreement_template_item as ati')
            ->rightJoin(DB_PREFIX.'rebate_agreement_item as ai',function ($join){
                $join->on('ai.agreement_template_item_id','=','ati.agreement_rebate_template_id')->on('ai.product_id','=','ati.product_id');
            })
            ->leftJoin(DB_PREFIX.'product as p','p.product_id','=','ai.product_id')
            ->leftJoin(DB_PREFIX.'product_description as pd','pd.product_id','=','p.product_id')
            ->where('ai.agreement_id',$base_info['id'])
            ->selectRaw('ai.*,ati.price as t_template_price,ati.rebate_amount as t_rebate_amount,ati.product_id as t_product_id
            ,ati.min_sell_price as t_min_sell_price,p.image,pd.name,p.sku,p.mpn')
            ->get()
            ->map(function ($value){
                return (array)$value;
            })
            ->toArray();


        $verify_product_column = [
            'rebate_amount',
            'min_sell_price',
            'template_price',
        ];

        foreach($product_list as $key => &$value){
            $product_list[$key]['image_show'] = $this->model_tool_image->resize($value['image'], 40, 40);
            $product_list[$key]['tag_array'] = $this->model_catalog_product->getProductTagHtmlForThumb($value['t_product_id']);
            $product_list[$key]['product_link'] = str_replace('&amp;', '&', $this->url->link('product/product','product_id='.$value['t_product_id']));
            foreach($verify_product_column as $ks => $vs){
                if(sprintf('%.4f',$value[$vs]) != sprintf('%.4f',$value['t_'.$vs])){
                    $value[$vs.'_tips'] =  sprintf($language,$value['t_'.$vs]);
                }else{
                    $value[$vs.'_tips'] = null;
                }

                $value[$vs.'_show'] =  $this->currency->format($value[$vs], $this->session->data['currency']);

            }

            $value['buyer_price_show'] = $this->currency->format($value['template_price'] - $value['rebate_amount'], $this->session->data['currency']);
            // 验证是否已经返点达成两次了
            $verify_res = $this->verifyRebateIsOverTimes($value['t_product_id'],$base_info);
            if(!$verify_res){
                $value['is_show'] = 0;
                $value['verify_tips'] = $verify_tips;
            }else{
                $value['is_show'] = 1;
                $value['verify_tips'] = null;
            }
            // 验证Buyer正在生效或Pending的别的返点协议中包含某些产品，这里不可以同时参加
            $verify_valid = $this->verifyRebateValidProduct($value['t_product_id'],$base_info);
            if($verify_valid){
                $value['is_show'] = 0;
                $value['verify_tips'] = sprintf($verify_invalid_language,$verify_valid);
            }
            if($value['rebate_amount'] == null && $value['min_sell_price'] == null && $value['template_price'] == null){
                $value['is_show'] = 0;
                if(!$verify_res){
                    $value['verify_tips'] = $verify_tips;
                }elseif($verify_valid){
                    $value['verify_tips'] = sprintf($verify_invalid_language,$verify_valid);
                }else{
                    $value['verify_tips'] = null;
                }
            }else{
                $value['is_show'] = 1;
            }

        }
        $ret['base_info']  = $base_info;
        $ret['product_list']  = $product_list;
        return $ret;



    }


    public function verifyRebateValidProduct($product_id,$data){
        $agreement_id = $data['id'];
        $buyer_id = $data['buyer_id'];
        //
        $map = [
            ['a.buyer_id','=',$buyer_id],
            ['a.id','!=',$agreement_id],
            ['ai.product_id','=',$product_id],
        ];
        $ret = $this->orm->table(DB_PREFIX.'rebate_agreement as a')
            ->leftJoin(DB_PREFIX.'rebate_agreement_item as ai','ai.agreement_id','=','a.id')
            ->where($map)->whereIn('a.status',[1,3])->whereIn('a.rebate_result',[0,1,2])->value('a.agreement_code');
        return $ret;

    }

    /**
     * [verifyRebateIsOverTimes description]是否超过两次
     * @param int $product_id
     * @param array $data
     * @return bool
     */
    public function verifyRebateIsOverTimes($product_id,$data){
        $agreement_id = $data['id'];
        $buyer_id = $data['buyer_id'];
        $map = [
            ['buyer_id','=',$buyer_id],
            ['agreement_id','!=',$agreement_id],
            ['product_id','=',$product_id],
        ];
        // 获取每个buyer对于指定产品申请的其他返点协议数量
        $count = db(DB_PREFIX.'rebate_agreement_product')
            ->distinct()
            ->select(['buyer_id','agreement_id','product_id',])
            ->where($map)
            ->get()
            ->count();
        if($count >= REBATE_TIMES){
            return false;
        }
        return true;

    }

    /**
     * [getRebatesTransactionList description]
     * @param bool $is_partner
     * @param int $agreement_id oc_rebate_agreement.id
     * @param bool $isEurope
     * @param string $column
     * @param string $sort
     * @return array
     * @throws Exception
     */
    public function getRebatesTransactionList($is_partner,$agreement_id,$isEurope,$column = 'time',$sort = 'asc'){
        // is_partner 无关紧要
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $base_info = $this->getAgreementInfo($agreement_id);

        $base_info['total_purchased'] = 0;
        $base_info['total_returned'] = 0;

        $map = [
          ['rao.agreement_id','=',$agreement_id],
        ];
        if($column == 'time'){
            $order_info = $this->orm->table(DB_PREFIX.'rebate_agreement_order as rao')
                ->leftjoin(DB_PREFIX.'yzc_rma_order as ro','ro.id','=','rao.rma_id')
                ->leftJoin(DB_PREFIX.'order as o','o.order_id','=','rao.order_id')
                ->where($map)
                ->selectRaw('o.delivery_type,rao.*,ifnull(ro.processed_date,o.date_added) as sort_time')
                ->orderByRaw('ifnull(ro.processed_date,o.date_added) '.$sort)
                ->get()
                ->map(
                    function ($value) {
                        return (array)$value;
                    })
                ->toArray();

        }else{

            $order_info = $this->orm->table(DB_PREFIX.'rebate_agreement_order as rao')
                ->leftJoin(DB_PREFIX.'product as p','p.product_id','=','rao.product_id')
                ->where($map)
                ->orderBy('p.sku',$sort)
                ->select()
                ->get()
                ->map(
                    function ($value) {
                        return (array)$value;
                    })
                ->toArray();
        }


        if($order_info){
            foreach($order_info as $key => $value){
                if($value['type'] == 1){
                    //采购订单
                    //根据订单明细 获取 order_id product 单价 服务费 service fee  total
                    $mapOrder = [
                        ['op.order_id','=',$value['order_id']],
                        ['op.order_product_id','=',$value['order_product_id']],
                    ];
                    $base_info['total_purchased'] += $value['qty'];
                    $order_info[$key]['extra'] = $this->getOrderDetails($mapOrder,$isEurope,$value['qty']);
                }elseif($value['type'] == 2){
                    // rma
                    $mapRma = [
                        ['rop.rma_id','=',$value['rma_id']],
                        ['rop.id','=',$value['rma_product_id']],
                    ];
                    $rma_info = $this->orm->table(DB_PREFIX.'yzc_rma_order_product as rop')
                        ->leftjoin(DB_PREFIX.'yzc_rma_order as ro','ro.id','=','rop.rma_id')
                        ->leftJoin(DB_PREFIX.'product as p','p.product_id','=','rop.product_id')
                        ->leftJoin(DB_PREFIX.'product_description as pd','pd.product_id','=','p.product_id')
                        ->where($mapRma)
                        ->select('ro.processed_date as sort_time','rop.quantity','rop.actual_refund_amount','ro.rma_order_id','p.image','p.sku','p.mpn','p.product_id','pd.name')
                        ->get()
                        ->map(
                            function ($value) {
                                return (array)$value;
                            })
                        ->toArray();
                    $country = session('country', 'USA');
                    $fromZone = CountryHelper::getTimezoneByCode('USA');
                    $toZone = CountryHelper::getTimezoneByCode($country);
                    // rma 单条记录
                    foreach($rma_info as $ks => $vs){
                        $rma_info[$ks]['tag_array'] = $this->model_catalog_product->getProductTagHtmlForThumb($vs['product_id']);
                        $rma_info[$ks]['image_show'] = $this->model_tool_image->resize($vs['image'], 40, 40);
                        $rma_info[$ks]['product_link'] = str_replace('&amp;', '&', $this->url->link('product/product','product_id='.$vs['product_id']));
                        //根据isPartner来区分
                        if($is_partner){
                            $rma_info[$ks]['order_link'] = str_replace('&amp;', '&', $this->url->link('account/customerpartner/rma_management/rmaInfo','rmaId='.$value['rma_id']));
                        }else{
                            $rma_info[$ks]['order_link'] = str_replace('&amp;', '&', $this->url->link('account/rma_order_detail','rma_id='.$value['rma_id']));
                        }
                        $rma_info[$ks]['actual_refund_amount_show'] = $this->currency->format($vs['actual_refund_amount'], $this->session->data['currency']);
                        $vs['sort_time'] = dateFormat($fromZone, $toZone, $vs['sort_time'],'Y-m-d H:i:s');
                        $rma_info[$ks]['sort_time_list'] = explode(' ',$vs['sort_time']);
                    }
                    $base_info['total_returned'] += $value['qty'];
                    $order_info[$key]['extra'] = current($rma_info);


                }
            }

        }
        if($base_info['total_purchased'] - $base_info['total_returned'] > $base_info['qty']){
            $base_info['remaining_quantity'] = 0;
        }else{
            $base_info['remaining_quantity'] = $base_info['qty']- ($base_info['total_purchased'] - $base_info['total_returned']);
        }
        $ret['base_info']  = $base_info;
        $ret['order_info'] = $order_info;
        return $ret;


    }


    public function getAgreementInfo($agreement_id){
        $this->load->model('extension/module/product_show');
        /** @var ModelExtensionModuleProductShow $productShowModel */
        $productShowModel = $this->model_extension_module_product_show;
        $map = [
            ['ra.id','=',$agreement_id],
            ['d_s.DicCategory','=','REBATE_AGREEMENT_STATUS'],
            ['d_r.DicCategory','=','REBATE_RESULT_STATUS'],
        ];
        $ret = $this->orm->table(DB_PREFIX.'rebate_agreement as ra')
            ->leftjoin('tb_sys_dictionary as d_s','d_s.DicKey','=','ra.status')
            ->leftjoin('tb_sys_dictionary as d_r','d_r.DicKey','=','ra.rebate_result')
            ->where($map)
            ->selectRaw('ra.*,d_s.DicValue as s_value,d_r.DicValue as r_value')
            ->get()
            ->map(
                function ($value) {
                    return (array)$value;
                })
            ->toArray();

        $buyerIds = array_column($ret, 'buyer_id');
        $buyerCustomerModelMap = Customer::query()->with('buyer')->whereIn('customer_id', $buyerIds)->get()->keyBy('customer_id');

        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        foreach($ret as $key => &$value){
            $value['effect_time_show'] = dateFormat($fromZone, $toZone, $value['effect_time'],'Y-m-d');
            $value['expire_time_show'] = dateFormat($fromZone, $toZone, $value['expire_time'],'Y-m-d');
            //获取buyer上门取货还是一件代发
            $value['isCollectionFromDomicile'] = $productShowModel->get_is_collection_from_domicile($value['buyer_id']);
            /** @var Customer $buyer */
            $buyer = $buyerCustomerModelMap->get($value['buyer_id']);
            $value['ex_vat'] = VATToolTipWidget::widget(['customer' => $buyer, 'is_show_vat' => true])->render();
            $value['nickname'] = $buyer->nickname;
            $value['user_number'] = $buyer->user_number;
            $value['buyer_group_id'] = $buyer->customer_group_id;
            //获取seller 店铺名称
            $value['screenname'] = $this->orm->table(DB_PREFIX.'customerpartner_to_customer')
                ->where('customer_id',$value['seller_id'])->value('screenname');

        }
        return current($ret);

    }

    /**
     * [getOrderDetails description]
     * @param array $mapOrder
     * @param bool $isEurope
     * @param int $qty
     * @return array
     * @throws Exception
     */
    public function getOrderDetails($mapOrder,$isEurope,$qty){
        $order_info = $this->orm->table(DB_PREFIX.'order_product as op')
            ->leftJoin(DB_PREFIX.'order as o','o.order_id','=','op.order_id')
            ->leftJoin(DB_PREFIX.'product_quote as pq',function ($join){
                $join->on('pq.order_id','=','op.order_id')->on('pq.product_id','=','op.product_id');
            })
            ->leftJoin(DB_PREFIX.'product as p','p.product_id','=','op.product_id')
            ->leftJoin(DB_PREFIX.'product_description as pd','pd.product_id','=','p.product_id')
            ->where($mapOrder)
            ->select(
                'o.order_id','o.date_added as sort_time','o.delivery_type','pd.name','op.price as op_price','pq.price as pq_price','op.poundage','op.quantity as op_quantity','op.service_fee_per','op.freight_per','op.base_freight','op.overweight_surcharge','op.package_fee','pq.amount_price_per','pq.amount_service_fee_per','p.image','p.sku','p.mpn','p.product_id'
            )
            ->get()
            ->map(
                function ($value) {
                    return (array)$value;
                })
            ->toArray();
        if($order_info){
            $country = session('country', 'USA');
            $fromZone = CountryHelper::getTimezoneByCode('USA');
            $toZone = CountryHelper::getTimezoneByCode($country);
            foreach($order_info as $key => $value){
                $order_info[$key]['unit_price'] =  $value['op_price'] - $value['amount_price_per'];
                if ($isEurope) {
                    $service_fee_per = $value['service_fee_per'];
                    //获取discount后的 真正的service fee
                    $service_fee_total_pre = ($service_fee_per - (float)$value['amount_service_fee_per']);

                }else{

                    $service_fee_total_pre  = 0;
                }

                $order_info[$key]['service_fee'] = sprintf('%.2f',$service_fee_total_pre);
                $order_info[$key]['service_fee_show'] = $this->currency->formatCurrencyPrice( $service_fee_total_pre, $this->session->get('currency'));

                $freight = $order_info[$key]['freight_per'] + $order_info[$key]['package_fee'];

                $order_info[$key]['package_fee_tips_show'] = $this->currency->formatCurrencyPrice($order_info[$key]['package_fee'], $this->session->get('currency'));
                $order_info[$key]['freight_tips_show'] = $this->currency->formatCurrencyPrice($order_info[$key]['freight_per'], $this->session->get('currency'));

                $order_info[$key]['freight_show'] = $this->currency->formatCurrencyPrice($freight, $this->session->get('currency'));
                $order_info[$key]['base_freight_show'] = $this->currency->formatCurrencyPrice($value['base_freight'], $this->session->get('currency'));
                $order_info[$key]['overweight_surcharge_show'] = $this->currency->formatCurrencyPrice($value['overweight_surcharge'], $this->session->get('currency'));
                $order_info[$key]['base_freight'] = $this->currency->formatCurrencyPrice($value['base_freight'], $this->session->get('currency'));
                $order_info[$key]['unit_price_show'] = $this->currency->formatCurrencyPrice( $order_info[$key]['unit_price'], $this->session->get('currency'));
                $order_info[$key]['total_price'] = sprintf('%.2f',($order_info[$key]['unit_price']+$freight)*$qty + $value['service_fee_per']*$qty);
                $order_info[$key]['total_price_show'] = $this->currency->formatCurrencyPrice( $order_info[$key]['total_price'], $this->session->get('currency'));
                $order_info[$key]['tag_array'] = $this->model_catalog_product->getProductTagHtmlForThumb($value['product_id']);
                $order_info[$key]['image_show'] = $this->model_tool_image->resize($value['image'], 40, 40);

                $value['sort_time'] = dateFormat($fromZone, $toZone, $value['sort_time'],'Y-m-d H:i:s');

                $order_info[$key]['sort_time_list'] = explode(' ',$value['sort_time']);
                $order_info[$key]['product_link'] = str_replace('&amp;', '&', $this->url->link('product/product','product_id='.$value['product_id']));
                //根据isPartner来区分
                $order_info[$key]['tag_array'] = $this->model_catalog_product->getProductTagHtmlForThumb($value['product_id']);
                if($this->customer->isPartner()){
                    $order_info[$key]['order_link'] = str_replace('&amp;', '&', $this->url->link('account/customerpartner/orderinfo','order_id='.$value['order_id']));
                }else{
                    $order_info[$key]['order_link'] = str_replace('&amp;', '&', $this->url->link('account/order/purchaseOrderInfo','order_id='.$value['order_id']));
                }

            }
        }

        return current($order_info);


    }

    /**
     * [setRebatesAgreementTerminate description]
     * @param int $agreement_id oc_rebate_agreement.id
     * @throws Exception
     */
    public function setRebatesAgreementTerminate($agreement_id){
        $base_info = $this->orm->table(DB_PREFIX.'rebate_agreement')
            ->where('id',$agreement_id)->select('*')->first();
        $seller_id=$base_info->seller_id;
        $buyer_id =$base_info->buyer_id;


        $map['id'] = $agreement_id;
        $map['status'] = 3;
        $mapSave = [
            'update_user_name' => $this->customer->getId(),
            'update_time'      => date('Y-m-d H:i:s',time()),
            'expire_time'      => date('Y-m-d H:i:s',time()),
            'rebate_result'    => 3,
        ];
        $num_rows = $this->orm->table(DB_PREFIX.'rebate_agreement')->where($map)->whereIn('rebate_result', [1,2])->update($mapSave);


        if($num_rows){
            //协议中的产品
            $sql            = "SELECT product_id FROM oc_rebate_agreement_item WHERE agreement_id={$agreement_id}";
            $product_id_arr = $this->db->query($sql)->rows;
            $product_id_arr = array_column($product_id_arr, 'product_id');
            $product_id_str = implode(',', $product_id_arr);
            if ($product_id_str) {
                $sql         = "SELECT id FROM 	oc_delicacy_management WHERE seller_id={$seller_id} AND buyer_id={$buyer_id} AND product_id IN ({$product_id_str})";
                $dm_ids_arrs = $this->db->query($sql)->rows;
                $dm_ids      = array_column($dm_ids_arrs, 'id');
                if ($dm_ids) {
                    //精细化删除
                    $this->load->model('CustomerPartner/DelicacyManagement');
                    $this->model_CustomerPartner_DelicacyManagement->batchRemove($dm_ids, $seller_id);
                }
            }
        }
    }

    /**
     * [rebateAgreementRequestAdd description] request 请求
     * @param array $posts
     * @param $files
     */
    public function rebateAgreementRequestAdd($posts, $files){
        //1. request 表
        //2. file
        //3. agreement 更新状态
        $agreement_id = $posts['agreement_id'];
        $request = [
            'agreement_id' => $agreement_id,
            'buyer_id'     => $this->customer->getId(),
            'comments'     => $posts['comments'],
            'process_status' => 6,
            'create_user_name' => $this->customer->getId(),
            'create_time'      => date('Y-m-d H:i:s',time()),
            //'update_user_name' => $this->customer->getId(),
            //'update_time'      => date('Y-m-d H:i:s',time()),
            'program_code'     => PROGRAM_CODE,
        ];

        $request_id = $this->orm->table(DB_PREFIX.'rebate_agreement_request')->insertGetId($request);
        if($request_id){
            //更新img
            //保存文件，重命名， 放到对应文件夹
            // 插入数据库
            $files = $this->request->file('files');
            $dataString =  date('Y-m-d', time());
            foreach($files as $items){
                $ext = $items->getClientOriginalExtension();
                $fileName = date('YmdHis', time()) . '_' . token(20) . '.'. $ext;
                $path = StorageCloud::rebateRequest()->writeFile($items, $dataString,$fileName);
                $arr = [
                    'file_type' => '.' . $ext,
                    'file_name' => $items->getClientOriginalName(),
                    'path' => '/' . $path,
                    'create_time' => Carbon::now(),
                    'request_id' => $request_id,

                ];
                $this->orm->table(DB_PREFIX.'rebate_agreement_request_file')->insert($arr);
            }
        }
        $map['id'] = $agreement_id;
        $mapSave = [
            'update_user_name' => $this->customer->getId(),
            'update_time'      => date('Y-m-d H:i:s',time()),
            'rebate_result'    => 6,
        ];
        $this->orm->table(DB_PREFIX.'rebate_agreement')->where($map)->update($mapSave);
        //对话存入
        $message_data = [
            'writer' => $this->customer->getId(),
            'agreement_id' => $posts['agreement_id'],
            'message' => $posts['comments'],
            'create_time' => date("Y-m-d H:i:s", time())
        ];
        $this->saveRebatesAgreementMessage($message_data);



    }

    public function rebateAgreementRequestSellerConfirm($posts){
        //1. request 表
        //2. agreement 更新状态
        $request_id = $posts['request_id'];
        $type = $posts['type'];
        if($type == 1){
            $process_status = 7;
        }else{
            $process_status = 8;
        }
        $request = [
            'update_user_name' => $this->customer->getId(),
            'update_time'      => date('Y-m-d H:i:s',time()),
            'reback'           => $posts['comments'],
            'process_status'   => $process_status
        ];

        $this->orm->table(DB_PREFIX.'rebate_agreement_request')->where('id',$request_id)->update($request);
        $map['id'] = $posts['agreement_id'];
        $mapSave = [
            'update_user_name' => $this->customer->getId(),
            'update_time'      => date('Y-m-d H:i:s',time()),
            'rebate_result'    => $process_status,
        ];
        $this->orm->table(DB_PREFIX.'rebate_agreement')->where($map)->update($mapSave);
        //对话存入
        $message_data = [
            'writer' => $this->customer->getId(),
            'agreement_id' => $posts['agreement_id'],
            'message' => $posts['comments'],
            'create_time' => date("Y-m-d H:i:s", time())
        ];
        $this->saveRebatesAgreementMessage($message_data);
        if($type == 1){
            //seller 同意的话需要返钱给buyer
            //这里需要重新计算buyer可以拿到的钱
            $buyerId = $this->getBuyerId($posts['agreement_id']);
            $innerAutoBuyerAttr1 = $this->customer->innerAutoBuyAttr1ByBuyerId($buyerId);
            if ($innerAutoBuyerAttr1){//内部采销异体自动购买账号
                $money = $this->rebatesMoneyVP($posts['agreement_id']);
                $money_info = $money['line_of_credit'];
                $vp_info = $money['virtual_pay'];
            }else{
                $money_info = $this->getRebatesMoneyForBuyer($posts['agreement_id']);
                $vp_info = [];
            }
            if ($money_info && $money_info['all_rebate_amount']){
                $this->updateNewCredit($money_info,$posts['agreement_id']);
                $this->updateAgreementProduct($money_info, $posts['agreement_id']);
            }
            if ($vp_info){
                $this->load->model('account/balance/virtual_pay_record');
                $this->model_account_balance_virtual_pay_record->insertData($buyerId,$posts['agreement_id'],$vp_info['all_rebate_amount'],3);
                $this->updateAgreementProduct($vp_info, $posts['agreement_id']);
            }

        }

    }

    public function getBuyerId($agreement_id)
    {
        return $this->orm->table('oc_rebate_agreement')->where('id', $agreement_id)->value('buyer_id');
    }

    /**
     * [getRebatesMoneyForBuyer description]
     * @param int $agreement_id oc_rebate_agreement.id
     * @return array
     * @throws Exception
     */
    public function getRebatesMoneyForBuyer($agreement_id){
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $mapOrder['o.agreement_id'] = $agreement_id;
        $order_info = $this->orm->table(DB_PREFIX.'rebate_agreement_order as o')
            ->leftJoin(DB_PREFIX.'rebate_agreement_item as i','o.item_id' ,'=','i.id')
            ->leftJoin(DB_PREFIX.'product as p','p.product_id','=','i.product_id')
            ->leftJoin(DB_PREFIX.'product_description as pd','pd.product_id','=','p.product_id')
            ->where($mapOrder)
            ->select('o.qty','o.type','i.rebate_amount','o.create_time','p.image','pd.name','p.sku','p.mpn','p.product_id','o.item_id','o.order_id')
            ->orderBy('o.create_time','asc')
            ->get()
            ->map(function ($value){
                return (array)$value;
            })
            ->toArray();
        $base_info = $this->orm->table(DB_PREFIX.'rebate_agreement')
            ->where('id',$agreement_id)->select('qty','buyer_id')->first();
        $verify_qty = $base_info->qty;

        $order = [];
        foreach ($order_info as $item) {
            $item['image_show'] = $this->model_tool_image->resize($item['image'], 40, 40);
            $item['tag_array'] = $this->model_catalog_product->getProductTagHtmlForThumb($item['product_id']);
            $item['product_link'] = str_replace('&amp;', '&', $this->url->link('product/product','product_id='.$item['product_id']));
            if(isset($order[$item['order_id']][$item['item_id']])){
                if($item['type'] == 1){
                    $order[$item['order_id']][$item['item_id']]['qty'] += $item['qty'];
                }else{
                    $order[$item['order_id']][$item['item_id']]['qty'] -= $item['qty'];
                }
            }else{
                $tmp = $item;
                if($item['type'] == 1){
                    $tmp['qty'] = 0 +  $item['qty'];
                }else{
                    $tmp['qty'] = 0 - $item['qty'];
                }
                $order[$item['order_id']][$item['item_id']] = $tmp;
            }

        }

        // 按create_time时间排序
        //foreach ($order as $key => $row) {
        //    $time_add[$key] = $row['create_time'];
        //}
        //array_multisort($time_add, SORT_ASC, $order);
        ksort($order);
        $new_order = [];
        foreach($order as $key => $value){
            foreach($value as $ks => $vs){
                $new_order[] = $vs;
            }
        }
        $order = $new_order;
        // 按create_time时间排序

        $count = 0;
        $ret_next = [];
        $new_order_exist = [];
        $all_count = 0;
        $all_rebate_amount = 0;
        foreach($order as $key => $value){
            $all_count += $value['qty'];
        }
        foreach($order as $key => $value){
            if($verify_qty < ($count + $value['qty'])){
               $order[$key]['qty'] = $verify_qty - $count;
               if($order[$key]['qty'] != 0 ){
                   $ret_next[] = $order[$key];
               }
               break;
            }else{
                $count += $value['qty'];
                $ret_next[] = $value;
            }

        }


        //相同sku的数量相加
        foreach($ret_next as $key => $value){
            if(isset($new_order_exist[$value['item_id']])){
                $new_order_exist[$value['item_id']]['qty'] += $value['qty'];
            }else{
                $new_order_exist[$value['item_id']] = $value;
            }
        }
        $ret = array_values($new_order_exist);

        foreach($ret as $key => $value){
            $ret[$key]['rebate_amount_all'] = $value['rebate_amount']*$value['qty'];
            $ret[$key]['rebate_amount_all_show'] = $this->currency->format( $ret[$key]['rebate_amount_all'], $this->session->data['currency']);
            $ret[$key]['rebate_amount_show'] = $this->currency->format( $value['rebate_amount'], $this->session->data['currency']);
            $all_rebate_amount += $ret[$key]['rebate_amount_all'];
        }

        $all_rebate_amount_show = $this->currency->format( $all_rebate_amount, $this->session->data['currency']);


        $final['order_list'] = $ret;
        $final['all_count'] = $all_count;
        $final['all_rebate_amount'] = $all_rebate_amount;
        $final['all_rebate_amount_show'] = $all_rebate_amount_show;
        $final['buyer_id'] = $base_info->buyer_id;
        return $final;



    }

    //返金涉及虚拟支付时使用
    public function rebatesMoneyVP($agreement_id)
    {
        $select = [
            'r.agreement_id',
            'r.product_id',
            'r.qty',//采购时代表采购的数量，rma的时候代表退的数量
            'r.order_id',
            'r.order_product_id',
            'r.rma_id',
            'r.rma_product_id',
            'o.payment_code',
            'i.rebate_amount'
        ];
        $info = $this->orm->table('oc_rebate_agreement_order as r')
            ->leftJoin('oc_order as o', 'o.order_id', '=', 'r.order_id')
            ->leftJoin('oc_rebate_agreement_item as i', ['i.agreement_id'=>'r.agreement_id', 'i.product_id'=>'r.product_id'])
            ->where('r.agreement_id', $agreement_id)
            ->selectRaw(implode(',', $select))
            ->orderBy('r.id')
            ->get();
        $info = obj2array($info);
        $rebate = $this->orm->table('oc_rebate_agreement')
            ->where('id', $agreement_id)
            ->select('qty','buyer_id')
            ->first();
        if (empty($rebate) || empty($info)){
            return [];
        }

        $rebateNum = $rebate->qty;
        $buyerId = $rebate->buyer_id;
        $orderNum = 0;
        $vp = $lc = [];
        $orderProductIdArr = [];
        foreach ($info as $k=>$v)
        {
            if (isset($orderProductIdArr[$v['order_product_id']])){
                if ($v['rma_id']){//剔除RMA的订单
                    $orderProductIdArr[$v['order_product_id']] -= $v['qty'];
                }
            }else{
                $orderProductIdArr[$v['order_product_id']] = $v['qty'];
            }
        }
        foreach ($info as $k1=>$v1)
        {
            if ($orderNum >= $rebateNum){
                break;
            }
            if (0 == $orderProductIdArr[$v1['order_product_id']]){
                continue;
            }
            $num = $rebateNum - $orderNum - $orderProductIdArr[$v1['order_product_id']];
            if ($num < 0){
                $v1['r_qty'] = $rebateNum - $orderNum;
            }else{
                $v1['r_qty'] = $orderProductIdArr[$v1['order_product_id']];
            }

            if (PayCode::PAY_VIRTUAL == $v1['payment_code']){
                $vp[] = $v1;//返到虚拟账户的
            }else{
                $lc[] = $v1;//返到余额的
            }
            $orderNum += $v1['r_qty'];
        }
        $vpm = 0;
        $lcm = 0;
        foreach ($vp as $k1=>$v1)
        {
            $vpm += $v1['r_qty'] * $v1['rebate_amount'];
        }
        foreach ($lc as $k2=> $v2)
        {
            $lcm += $v2['r_qty'] * $v2['rebate_amount'];
        }
        $vpMoney = [
            'buyer_id'  => $buyerId,
            'all_rebate_amount' => $vpm,
            'order_list'    => $vp
        ];
        $lcMoney = [
            'buyer_id'  => $buyerId,
            'all_rebate_amount' => $lcm,
            'order_list'    => $lc
        ];

        return ['virtual_pay'=>$vpMoney, 'line_of_credit'=>$lcMoney];
    }


    /**
     * [updateNewCredit description]
     * @param array $money_info
     * @param int $agreement_id oc_rebate_agreement.id
     * @throws Exception
     */
    public function updateNewCredit($money_info, $agreement_id)
    {
        $db = $this->orm->getConnection();
        try {
            $db->beginTransaction();
            $line_of_credit = $db->table(DB_PREFIX . 'customer')
                ->where('customer_id', $money_info['buyer_id'])->value('line_of_credit');
            $line_of_credit = round($line_of_credit, 4);
            $new_line_of_credit = round($line_of_credit + $money_info['all_rebate_amount'], 4);
            $serialNumber = SerialNumberRepository::getDateSerialNumber(ServiceEnum::AMENDMENT_RECORD_NO);
            $mapInsert = [
                'serial_number' => $serialNumber,
                'customer_id' => $money_info['buyer_id'],
                'old_line_of_credit' => $line_of_credit,
                'new_line_of_credit' => $new_line_of_credit,
                'date_added' => date('Y-m-d H:i:s', time()),
                'operator_id' => $this->customer->getId(),
                'type_id' => 4,
                'memo' => 'seller rebate',
                'header_id' => $agreement_id
            ];

            $db->table('tb_sys_credit_line_amendment_record')->insertGetId($mapInsert);
            $db->table(DB_PREFIX . 'customer')
                ->where('customer_id', $money_info['buyer_id'])->update(['line_of_credit' => $new_line_of_credit]);
        } catch (Exception $exception) {
            $message = 'Rebate Recharge failed: buyer_id[' . ($money_info['buyer_id'] ?? 'undefined') . ']';
            $message .= ',old_line_of_credit[' . ($line_of_credit ?? 'undefined') . ']';
            $message .= ',new_line_of_credit[' . ($new_line_of_credit ?? 'undefined') . ']';
            $message .= ',error code:' . $exception->getCode();
            $message .= ',error message:' . $exception->getMessage();
            $this->log->write($message);
            $db->rollBack();
        }
        $db->commit();

    }

    public function updateAgreementProduct($money_info, $agreement_id)
    {
        // 更新oc_rebate_agreement_product
        $productInsert = [];
        foreach ($money_info['order_list'] as $key => $value) {
            $productInsert[] = [
                'agreement_id' => $agreement_id,
                'create_user_name' => $this->customer->getId(),
                'create_time' => date('Y-m-d H:i:s', time()),
                'product_id' => $value['product_id'],
                'program_code' => PROGRAM_CODE,
                'buyer_id' => $money_info['buyer_id'],
            ];
        }
        $this->orm->table(DB_PREFIX . 'rebate_agreement_product')->insert($productInsert);
    }

    /**
     * [verifyAgreementStock description]
     * @param int $agreement_id
     * @return bool
     */
    public function verifyAgreementStock($agreement_id){
        // Buyer最多可以Bid的数量x公式变更为：
        //假设：A产品当前上架库存是50，B是30，C是10.Buyer此次Bid的产品是B和C。
        //那么，第一步：分别找到所有正在生效的（Rebate Result 为 Active和Due Soon）返点协议包含B和包含C的（不是同时包含）【假设有两个协议，协议1中包含A和B产品，共Bid了30个，协议2包含B和C产品，共Bid了50个】
        //第二步，统计这些协议中还需购买的B和C分别是多少，用协议数量-已购买协议中产品的数量（没有退返的），再按协议中产品数量平均分每个产品还需购买多少（也就是B或C还需购买多少）。【假设协议1的Buyer已经购买了A10个，B 0个，协议2的Buyer已经购买了C20个，B 0个，那么协议1中A和B分别还需购买10个（（30-10）/2），协议2中B和C分别还需购买15个（（50-20）/2）（上述的z值），除不开的情况下四舍五入，最后一个用总数减前面的和】
        //第三步，用当前B和C的产品上架库存减去还需购买的数量，相加的和乘以80%就是这个Buyer最多可以Bid的数量。【现在B的库存扣减掉其他协议还需购买的数量后剩30-10-15=5个，C还剩10-15=-5个，相加等于0个，乘以80%也是0，则Buyer最多可Bid 0个，如果相加等于负数，则Buyer最多可Bid的还是0个】
        // 1  获取当前所有协议的产品，以及bid 总数
        //获取agreement 中详细的产品p.quantity
        $base_info = $this->orm->table(DB_PREFIX.'rebate_agreement')->where('id',$agreement_id)->first();
        $base_info = obj2array($base_info);
        $product_list = $this->orm->table(DB_PREFIX.'rebate_agreement_item as ai')
            ->leftJoin(DB_PREFIX.'product as p','p.product_id','=','ai.product_id')
            ->where('agreement_id',$agreement_id)
            ->selectRaw('ai.*,p.quantity')
            ->get()
            ->map(function ($value){
                return (array)$value;
            })
            ->toArray();

        foreach($product_list as $key => &$value){

            $verify_res = $this->verifyRebateIsOverTimes($value['product_id'],$base_info);
            if(!$verify_res){
                unset($product_list[$key]);
            }
            // 验证Buyer正在生效或Pending的别的返点协议中包含某些产品，这里不可以同时参加
            $verify_valid = $this->verifyRebateValidProduct($value['product_id'],$base_info);
            if($verify_valid){
                unset($product_list[$key]);
            }

        }
        // 获取的有效的协议产品
        $sum = 0;
        $product_list =array_values($product_list);
        foreach($product_list as $ks => $vs){
            //
            $quantity = $this->getRebateAgreementLeftQuantity($vs['product_id'],$base_info);
            $sum += $vs['quantity'] - $quantity;

        }
        if($sum < 0){
            $sum = 0;
        }
        if($sum*0.8 - $base_info['qty'] >= 0){
            return true;
        }

        return false;

    }


    public function getRebateAgreementLeftQuantity($product_id,$data){
        //找到所有该产品且生效agreement seller
        $map = [
            ['a.seller_id','=',$data['seller_id']],
            ['a.status','=',3],
            ['ai.product_id','=',$product_id],
            ['a.id','!=',$data['id']],
        ];
        $product_list = $this->orm->table(DB_PREFIX.'rebate_agreement_item as ai')
            ->leftJoin(DB_PREFIX.'product as p','p.product_id','=','ai.product_id')
            ->leftJoin(DB_PREFIX.'rebate_agreement as a','a.id','=','ai.agreement_id')
            ->where($map)
            ->whereIn('a.rebate_result',[1,2])
            ->selectRaw('ai.*,p.quantity,a.qty,a.id as a_id')
            ->get()
            ->map(function ($value){
                return (array)$value;
            })
            ->toArray();
        $sum = 0;
        foreach($product_list as $key => $value){
            //这里对应的是 一个agreement 一个产品
            // 需要获取 1. q.qty 2. 有效的产品list 3.已经完成的数量
            $agreement_id = $value['a_id'];
            $order_info  = $this->getRebateOrderInfo($agreement_id,$value['product_id']);
            if($order_info['valid_product_count'] == 0){
                $left_count = 0;
            }else{
                $left_count = ($value['qty'] - ($order_info['total_purchased'] - $order_info['total_returned']))/$order_info['valid_product_count'];
                $left_count = floor($left_count);
            }
            $sum += $left_count;

        }

        return $sum;

    }


    public function getRebateOrderInfo($agreement_id,$product_id){
        $base_info = $this->orm->table(DB_PREFIX.'rebate_agreement')->where('id',$agreement_id)->first();
        $base_info = obj2array($base_info);
        $product_list = $this->orm->table(DB_PREFIX.'rebate_agreement_item as ai')
            ->leftJoin(DB_PREFIX.'product as p','p.product_id','=','ai.product_id')
            ->where('agreement_id',$agreement_id)
            ->selectRaw('ai.*,p.quantity')
            ->get()
            ->map(function ($value){
                return (array)$value;
            })
            ->toArray();

        foreach($product_list as $key => &$value){

            $verify_res = $this->verifyRebateIsOverTimes($value['product_id'],$base_info);
            if(!$verify_res){
                if($value['product_id'] == $product_id){
                    $product_list = null;
                    break;
                }
                unset($product_list[$key]);
            }
            // 验证Buyer正在生效或Pending的别的返点协议中包含某些产品，这里不可以同时参加
            $verify_valid = $this->verifyRebateValidProduct($value['product_id'],$base_info);
            if($verify_valid){
                if($value['product_id'] == $product_id){
                    $product_list = null;
                    break;
                }
                unset($product_list[$key]);
            }

        }

        $valid_product_count = count($product_list);

        $mapRao = [
            ['rao.agreement_id','=',$agreement_id],
        ];


        $order_info = $this->orm->table(DB_PREFIX.'rebate_agreement_order as rao')
            ->leftJoin(DB_PREFIX.'product as p','p.product_id','=','rao.product_id')
            ->where($mapRao)
            ->select()
            ->get()
            ->map(
                function ($value) {
                    return (array)$value;
                })
            ->toArray();
        $total_purchased = 0;
        $total_returned = 0;
        if($order_info){
            foreach($order_info as $key => $value){
                if($value['type'] == 1){
                    //采购订单
                    //根据订单明细 获取 order_id product 单价 服务费 service fee  total

                    $total_purchased += $value['qty'];

                }elseif($value['type'] == 2){
                    // rma
                    $total_returned += $value['qty'];

                }
            }

        }

        $ret['valid_product_count'] = $valid_product_count;
        $ret['total_purchased'] = $total_purchased;
        $ret['total_returned'] = $total_returned;
        return $ret;

    }


    public function setTemplateOfCommunication($agreement_id,$message_text = null,$type){
        $pre = 'Rebate-';
        $subject = '';
        $message = '';
        $received_id = '';
        $base_info = $this->getAgreementInfo($agreement_id);
        if($type == 0 || $type == 1){
            $received_id = $base_info['buyer_id'];

            $product_list = $this->orm->table(DB_PREFIX.'rebate_agreement_item as ai')
                ->leftJoin(DB_PREFIX.'product as p','p.product_id','=','ai.product_id')
                ->leftJoin(DB_PREFIX.'product_description as pd','pd.product_id','=','p.product_id')
                ->where('agreement_id',$agreement_id)
                ->selectRaw('ai.*,p.image,pd.name,p.sku,p.mpn')
                ->get()
                ->map(function ($value){
                    return (array)$value;
                })
                ->toArray();
            $product_str = '';
            $product_url_str = '';
            foreach($product_list as $key => &$value){

                //$verify_res = $this->verifyRebateIsOverTimes($value['product_id'],$base_info);
                //if(!$verify_res){
                //    unset($product_list[$key]);
                //    continue;
                //}
                // 验证Buyer正在生效或Pending的别的返点协议中包含某些产品，这里不可以同时参加
                //$verify_valid = $this->verifyRebateValidProduct($value['product_id'],$base_info);
                //if($verify_valid){
                //    unset($product_list[$key]);
                //    continue;
                //}
                $product_list[$key]['product_link'] = str_replace('&amp;', '&', $this->url->link('product/product','product_id='.$value['product_id']));
                $product_str .= $value['sku'].',';
                $product_url_str .= '<a target="_blank" href="' . str_replace('&amp;', '&',$this->url->link('product/product', 'product_id=' . $value['product_id']) ). '">' . $value['sku'] . '</a>,';
            }
            $product_str = trim($product_str,',');
            $product_url_str = trim($product_url_str,',');
            if($type == 1){
                $subject = $pre.'Approved: '.$base_info['screenname'].' has approved your rebate bid request : #'.$base_info['agreement_code'];
                $message = '<table   border="0" cellspacing="0" cellpadding="0">';
                $message .= '<tr><th align="left">Agreement ID:&nbsp;</th><td style="width: 650px">
                      <a target="_blank" href="' . str_replace('&amp;', '&',$this->url->link('account/product_quotes/rebates_contract/rebatesAgreementList', 'agreement_id=' . $agreement_id )). '">' .$base_info['agreement_code']. '</a>
                      </td></tr>';
                $message .= '<tr><th align="left">Store:&nbsp;</th><td style="width: 650px">
                      '.$base_info['screenname'].'
                      </td></tr>';
                $message .= '<tr><th align="left">Products:&nbsp;</th><td style="width: 650px">
                      '. $product_url_str.'
                      </td></tr></table>';



            }else{
                $subject = $pre.'Rejected: '.$base_info['screenname'].' has rejected your rebate bid request : #'.$base_info['agreement_code'];
                $message = '<table   border="0" cellspacing="0" cellpadding="0">';
                $message .= '<tr><th align="left">Agreement ID:&nbsp;</th><td style="width: 650px">
                      <a  target="_blank" href="' . str_replace('&amp;', '&',$this->url->link('account/product_quotes/rebates_contract/rebatesAgreementList', 'agreement_id=' . $agreement_id )). '">' .$base_info['agreement_code']. '</a>
                      </td></tr>';
                $message .= '<tr><th align="left">Store:&nbsp;</th><td style="width: 650px">
                      '.$base_info['screenname'].'
                      </td></tr>';
                $message .= '<tr><th align="left">Products:&nbsp;</th><td style="width: 650px">
                      '. $product_url_str.'
                      </td></tr>';
                $message .= '<tr><th align="left">Reason for rejection:&nbsp;</th><td style="width: 650px">
                      '.$message_text.'
                      </td></tr></table>';
            }



        }elseif ($type == 2 || $type == 3){

            $received_id = $base_info['buyer_id'];

            $product_list = $this->orm->table(DB_PREFIX.'rebate_agreement_item as ai')
                ->leftJoin(DB_PREFIX.'product as p','p.product_id','=','ai.product_id')
                ->leftJoin(DB_PREFIX.'product_description as pd','pd.product_id','=','p.product_id')
                ->where('agreement_id',$agreement_id)
                ->selectRaw('ai.*,p.image,pd.name,p.sku,p.mpn')
                ->get()
                ->map(function ($value){
                    return (array)$value;
                })
                ->toArray();

            $product_url_str = '';
            foreach($product_list as $key => &$value){
                //$verify_res = $this->verifyRebateIsOverTimes($value['product_id'],$base_info);
                //if(!$verify_res){
                //    unset($product_list[$key]);
                //    continue;
                //}
                //// 验证Buyer正在生效或Pending的别的返点协议中包含某些产品，这里不可以同时参加
                //$verify_valid = $this->verifyRebateValidProduct($value['product_id'],$base_info);
                //if($verify_valid){
                //    unset($product_list[$key]);
                //    continue;
                //}
                $product_list[$key]['product_link'] = str_replace('&amp;', '&', $this->url->link('product/product','product_id='.$value['product_id']));

                $product_url_str .= '<a target="_blank" href="' . str_replace('&amp;', '&',$this->url->link('product/product', 'product_id=' . $value['product_id']) ). '">' . $value['sku'] . '</a>,';
            }
            $product_url_str = trim($product_url_str,',');

            if($type ==2){
                // seller agree
                $order = $this->getRebatesMoneyForBuyer($agreement_id);
                $subject = $pre.'Rebate Paid: '.$base_info['screenname'].' has approved to pay '.$order['all_rebate_amount_show'].' rebate and the system has recharged to your line of credit: #'.$base_info['agreement_code'];
                $message = '<table   border="0" cellspacing="0" cellpadding="0">';
                $message .= '<tr><th align="left">Agreement ID:&nbsp;</th><td style="width: 650px">
                      <a target="_blank" href="' . str_replace('&amp;', '&',$this->url->link('account/product_quotes/rebates_contract/rebatesAgreementList', 'agreement_id=' . $agreement_id )). '">' .$base_info['agreement_code']. '</a>
                      </td></tr>';
                $message .= '<tr><th align="left">Store:&nbsp;</th><td style="width: 650px">
                      '.$base_info['screenname'].'
                      </td></tr>';
                $message .= '<tr><th align="left">Products:&nbsp;</th><td style="width: 650px">
                      '. $product_url_str.'
                      </td></tr>';
                $message .= '<tr><th align="left">Agreement Term:&nbsp;</th><td style="width: 650px">
                      '. $base_info['effect_time'].' - '. $base_info['expire_time'].' ( '.$base_info['day'].'Days )
                      </td></tr>';

                $message .= '<tr><th align="left">Quantity:&nbsp;</th><td style="width: 650px">
                      '.$order['all_count'].'/'.$base_info['qty'].'
                      </td></tr>';

                $message .= '<tr><th align="left">Rebate Amount:&nbsp;</th><td style="width: 650px">
                      '.$order['all_rebate_amount_show'].' ( system has recharged to your line of credit )
                      </td></tr></table>';


            }elseif($type == 3){
                // seller reject
                $order = $this->getRebatesMoneyForBuyer($agreement_id);
                $subject = $pre.'Rebate Declined: '.$base_info['screenname'].' has declined the rebate payment request, you can request again: #'.$base_info['agreement_code'];
                $message = '<table   border="0" cellspacing="0" cellpadding="0">';
                $message .= '<tr><th align="left">Agreement ID:&nbsp;</th><td style="width: 650px">
                      <a target="_blank" href="' . str_replace('&amp;', '&',$this->url->link('account/product_quotes/rebates_contract/rebatesAgreementList', 'agreement_id=' . $agreement_id )). '">' .$base_info['agreement_code']. '</a>
                      </td></tr>';
                $message .= '<tr><th align="left">Store:&nbsp;</th><td style="width: 650px">
                      '.$base_info['screenname'].'
                      </td></tr>';
                $message .= '<tr><th align="left">Products:&nbsp;</th><td style="width: 650px">
                      '. $product_url_str.'
                      </td></tr>';
                $message .= '<tr><th align="left">Agreement Term:&nbsp;</th><td style="width: 650px">
                      '. $base_info['effect_time'].' - '. $base_info['expire_time'].' ( '.$base_info['day'].'Days )
                      </td></tr>';

                $message .= '<tr><th align="left">Quantity:&nbsp;</th><td style="width: 650px">
                      '.$order['all_count'].'/'.$base_info['qty'].'
                      </td></tr>';

                $message .= '<tr><th align="left">Rebate Amount:&nbsp;</th><td style="width: 650px">
                      '.$order['all_rebate_amount_show'].'
                      </td></tr>';
                $message .= '<tr><th align="left">Reason for being declined:&nbsp;</th><td style="width: 650px">
                      '.$message_text.'
                      </td></tr></table>';
            }

        }elseif($type == 4){
            $received_id = $base_info['seller_id'];
            $product_list = $this->orm->table(DB_PREFIX.'rebate_agreement_item as ai')
                ->leftJoin(DB_PREFIX.'product as p','p.product_id','=','ai.product_id')
                ->leftJoin(DB_PREFIX.'product_description as pd','pd.product_id','=','p.product_id')
                ->where('agreement_id',$agreement_id)
                ->selectRaw('ai.*,p.image,pd.name,p.sku,p.mpn')
                ->get()
                ->map(function ($value){
                    return (array)$value;
                })
                ->toArray();

            $product_url_str = '';
            foreach($product_list as $key => &$value){

                //$verify_res = $this->verifyRebateIsOverTimes($value['product_id'],$base_info);
                //if(!$verify_res){
                //    unset($product_list[$key]);
                //    continue;
                //}
                //// 验证Buyer正在生效或Pending的别的返点协议中包含某些产品，这里不可以同时参加
                //$verify_valid = $this->verifyRebateValidProduct($value['product_id'],$base_info);
                //if($verify_valid){
                //    unset($product_list[$key]);
                //    continue;
                //}

                $product_list[$key]['product_link'] = str_replace('&amp;', '&', $this->url->link('product/product','product_id='.$value['product_id']));

                $product_url_str .= '<a target="_blank" href="' . str_replace('&amp;', '&',$this->url->link('product/product', 'product_id=' . $value['product_id']) ). '">' . $value['sku'] . '</a>('.$value['mpn'].'),';


            }
            $product_url_str = trim($product_url_str,',');

            $subject = $pre.'Terminated: '.$base_info['nickname'].'('.$base_info['user_number'].')'.' terminated the rebate agreement : #'.$base_info['agreement_code'];
            $order = $this->getRebatesMoneyForBuyer($agreement_id);
            $message = '<table   border="0" cellspacing="0" cellpadding="0">';
            $message .= '<tr><th align="left">Agreement ID:&nbsp;</th><td style="width: 650px">
                      <a target="_blank" href="' . str_replace('&amp;', '&',$this->url->link('account/product_quotes/rebates_contract/rebatesAgreementList', 'agreement_id=' . $agreement_id )). '">' .$base_info['agreement_code']. '</a>
                      </td></tr>';
            $message .= '<tr><th align="left">Buyer:&nbsp;</th><td style="width: 650px">
                      '.$base_info['nickname'].'('.$base_info['user_number'].')
                      </td></tr>';
            $message .= '<tr><th align="left">Products:&nbsp;</th><td style="width: 650px">
                      '.$product_url_str.'
                      </td></tr>';
            $message .= '<tr><th align="left">Agreement Term:&nbsp;</th><td style="width: 650px">
                      '. $base_info['effect_time'].' - '. $base_info['expire_time'].' ( '.$base_info['day'].'Days )
                      </td></tr>';

            $message .= '<tr><th align="left">Quantity:&nbsp;</th><td style="width: 650px">
                      '.$order['all_count'].'/'.$base_info['qty'].'
                      </td></tr>';
            $message .= '<tr><th align="left">Reason for termination:&nbsp;</th><td style="width: 650px">
                      '.$message_text.'
                      </td></tr></table>';
        }elseif($type == 5){
            // buyer 申请返点
            $received_id = $base_info['seller_id'];
            $product_list = $this->orm->table(DB_PREFIX.'rebate_agreement_item as ai')
                ->leftJoin(DB_PREFIX.'product as p','p.product_id','=','ai.product_id')
                ->leftJoin(DB_PREFIX.'product_description as pd','pd.product_id','=','p.product_id')
                ->where('agreement_id',$agreement_id)
                ->selectRaw('ai.*,p.image,pd.name,p.sku,p.mpn')
                ->get()
                ->map(function ($value){
                    return (array)$value;
                })
                ->toArray();

            $product_url_str = '';
            foreach($product_list as $key => &$value){
                //$verify_res = $this->verifyRebateIsOverTimes($value['product_id'],$base_info);
                //if(!$verify_res){
                //    unset($product_list[$key]);
                //    continue;
                //}
                //// 验证Buyer正在生效或Pending的别的返点协议中包含某些产品，这里不可以同时参加
                //$verify_valid = $this->verifyRebateValidProduct($value['product_id'],$base_info);
                //if($verify_valid){
                //    unset($product_list[$key]);
                //    continue;
                //}

                $product_list[$key]['product_link'] = str_replace('&amp;', '&', $this->url->link('product/product','product_id='.$value['product_id']));

                $product_url_str .= '<a target="_blank" href="' . str_replace('&amp;', '&',$this->url->link('product/product', 'product_id=' . $value['product_id']) ). '">' . $value['sku'] . '</a>('.$value['mpn'].'),';
            }
            $product_url_str = trim($product_url_str,',');
            $order = $this->getRebatesMoneyForBuyer($agreement_id);
            $subject = $pre.'New rebate request: '.$base_info['nickname'].'('.$base_info['user_number'].')'.' has accomplished the rebate agreement and requested '.$order['all_rebate_amount_show'].' rebate to you: #'.$base_info['agreement_code'];
            $message = '<table   border="0" cellspacing="0" cellpadding="0">';
            $message .= '<tr><th align="left">Please respond to the rebate request&nbsp;</th><td style="width: 650px">
                      </td></tr>';
            $message .= '<tr><th align="left">Agreement ID:&nbsp;</th><td style="width: 650px">
                      <a target="_blank" href="' . str_replace('&amp;', '&',$this->url->link('account/product_quotes/rebates_contract/rebatesAgreementList', 'agreement_id=' . $agreement_id )). '">' .$base_info['agreement_code']. '</a>
                      </td></tr>';
            $message .= '<tr><th align="left">Buyer:&nbsp;</th><td style="width: 650px">
                      '.$base_info['nickname'].'('.$base_info['user_number'].')
                      </td></tr>';
            $message .= '<tr><th align="left">Products:&nbsp;</th><td style="width: 650px">
                      '.$product_url_str.'
                      </td></tr>';
            $message .= '<tr><th align="left">Agreement Term:&nbsp;</th><td style="width: 650px">
                      '. $base_info['effect_time'].' - '. $base_info['expire_time'].' ( '.$base_info['day'].'Days )
                      </td></tr>';

            $message .= '<tr><th align="left">Quantity:&nbsp;</th><td style="width: 650px">
                      '.$order['all_count'].'/'.$base_info['qty'].'
                      </td></tr>';
            $message .= '<tr><th align="left">Rebate Amount to Pay:&nbsp;</th><td style="width: 650px">
                      '.$order['all_rebate_amount_show'].'
                      </td></tr></table>';
        }

        $ret['subject'] = $subject;
        $ret['message'] = $message;
        $ret['received_id']  = $received_id;
        return $ret;


    }

    /**
     * [addRebatesAgreementCommunication description] seller reject 1 seller agree 2 agree rebate 3 reject rebate 4 terminate 5 buyer申请
     * @param int $agreement_id
     * @param null $message_text
     * @param int $type
     * @throws Exception
     */
    public function addRebatesAgreementCommunication($agreement_id,$message_text = null,$type){
        $ret = $this->setTemplateOfCommunication($agreement_id,$message_text,$type);
        $this->load->model('message/message');
        $this->model_message_message->addSystemMessageToBuyer('bid_rebates',$ret['subject'],$ret['message'],$ret['received_id']);

    }


}
