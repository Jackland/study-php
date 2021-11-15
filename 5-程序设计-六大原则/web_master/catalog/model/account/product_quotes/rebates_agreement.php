<?php

/**
 * Class ModelAccountProductQuotesRebatesAgreement
 * @property ModelCustomerPartnerDelicacyManagement $model_CustomerPartner_DelicacyManagement
 */
class ModelAccountProductQuotesRebatesAgreement extends Model
{
    /**
     * 返回四期，返点合同表的基础数据
     *
     * @param int $agreement_id
     * @return array
     * @author zhousuyang 更新于返点四期
     */
    function getRebatesContractDetailByAgreementId($agreement_id)
    {
        $sql   = "SELECT * FROM oc_rebate_agreement WHERE id=" . intval($agreement_id);
        $query = $this->db->query($sql);
        return $query->row;
    }


    /**
     * 统计区域
     * @param $data
     * @return int
     */
    public function getCountMap($data)
    {
        if (isset($data)) {
                $sql = "SELECT
        COUNT(ra.id) AS cnt
    FROM
        `oc_rebate_agreement` ra
        LEFT JOIN oc_rebate_agreement_template rat ON rat.id = ra.agreement_template_id
        LEFT JOIN oc_customer c ON ra.`buyer_id` = c.`customer_id`
        LEFT JOIN oc_customerpartner_to_customer ctc ON ctc.customer_id=ra.seller_id ";

            $implode = array();
            $params = [];
            if (isset($data['buyer_id'])) {
                $implode[] = "ra.`buyer_id` = " . (int)$data['buyer_id'];
            }

            if (isset($data['seller_id'])) {
                $implode[] = "ra.`seller_id` = " . (int)$data['seller_id'];
            }

            if (isset($data['contract_id'])) {
                $implode[] = ' ra.`agreement_code` like ?';
                $params[] = "%{$data['contract_id']}%";
            }

            if (isset($data['filter_store_name']) && !is_null($data['filter_store_name'])) {
                $implode[] = "LCASE(ctc.`screenname`) LIKE ?";
                $params[] = '%' . $this->db->escape(utf8_strtolower($data['filter_store_name'])) . '%';
            }

            if (isset($data['filter_buyer_name']) && !is_null($data['filter_buyer_name'])) {
                $implode[] = ' (c.nickname like ? or c.user_number like ?)';
                $params[] = "%{$data['filter_buyer_name']}%";
                $params[] = "%{$data['filter_buyer_name']}%";
            }

            if (isset($data['filter_sku_mpn']) && !empty($data['filter_sku_mpn'])) {
                $implode[] = " rat.items LIKE ?";
                $params[] = "%{$data['filter_sku_mpn']}%";
            }

            if (!empty($data['filter_date_from'])) {
                $implode[] = "ra.`update_time` >= '" . $this->db->escape(utf8_strtolower($data['filter_date_from'])) . "'";
            }
            if (!empty($data['filter_date_to'])) {
                $implode[] = "ra.`update_time` <= '" . $this->db->escape(utf8_strtolower($data['filter_date_to'])) . "'";
            }


            switch ($data['status_condition']){
                case 'pending':
                    $implode[] = "ra.status=1";
                    break;
                case 'rejected':
                    $implode[] = "ra.status=2";
                    break;
                case 'active':
                    $implode[] = "ra.rebate_result=1";
                    break;
                case 'due_soon':
                    $implode[] = "ra.rebate_result=2";
                    break;
                case 'fulfilled':
                    $implode[] = "ra.rebate_result=5";
                    break;
                case 'processing':
                    $implode[] = "ra.rebate_result=6";
                    break;
                case 'rebate_paid':
                    $implode[] = "ra.rebate_result=7";
                    break;
                case 'rebate_declined':
                    $implode[] = "ra.rebate_result=8";
                    break;
                case 'failed':
                    $implode[] = "ra.rebate_result=4";
                    break;
                default:
                    break;
            }

            if (!empty($implode)) {
                $sql .= " WHERE " . implode(" AND ", $implode);
            }

            $query = $this->db->query($sql, $params);
            return intval($query->row['cnt']);
        } else {
            return 0;
        }
    }


    /**
     * 查询模板合同总数
     * @param $data
     * @return mixed
     */
    public function getRebatesContractTotal($data)
    {
        if (isset($data)) {
                $sql = "SELECT COUNT(*) cnt
    FROM (
    SELECT
        COUNT(ra.id) AS cnt
    FROM
        `oc_rebate_agreement` ra
        LEFT JOIN oc_rebate_agreement_template rat ON rat.id = ra.agreement_template_id
        LEFT JOIN oc_customer c ON ra.`buyer_id` = c.`customer_id`
        LEFT JOIN oc_customerpartner_to_customer ctc ON ctc.customer_id=ra.seller_id ";
            $implode = array();
            $params = [];
            if (isset($data['buyer_id'])) {
                $implode[] = "ra.`buyer_id` = " . (int)$data['buyer_id'];
            }

            if (isset($data['seller_id'])) {
                $implode[] = "ra.`seller_id` = " . (int)$data['seller_id'];
            }

            if (isset($data['contract_id'])) {
                $implode[] = ' ra.`agreement_code` like ?';
                $params[] = "%{$data['contract_id']}%";
            }

            if (isset($data['filter_store_name']) && !is_null($data['filter_store_name'])) {
                $implode[] = 'LCASE(ctc.`screenname`) LIKE ?';
                $params[] = '%' . $this->db->escape(utf8_strtolower($data['filter_store_name'])) . '%';
            }

            if (isset($data['filter_buyer_name']) && !is_null($data['filter_buyer_name'])) {
                $implode[] = ' (c.nickname like ? or c.user_number like ?)';
                $params[] = "%{$data['filter_buyer_name']}%";
                $params[] = "%{$data['filter_buyer_name']}%";
            }

            if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
                $implode[] = "ra.`status` = " . (int)$data['filter_status'];
            }

            if (isset($data['filter_result_status']) && !is_null($data['filter_result_status'])) {
                $implode[] = "ra.`rebate_result` = " . (int)$data['filter_result_status'];
            }

            if (isset($data['filter_sku_mpn']) && !empty($data['filter_sku_mpn'])) {
                $implode[] = ' rat.items LIKE ?';
                $params[] = "%{$data['filter_sku_mpn']}%";
            }

            if (!empty($data['filter_date_from'])) {
                $implode[] = "ra.`update_time` >= '" . $this->db->escape(utf8_strtolower($data['filter_date_from'])) . "'";
            }
            if (!empty($data['filter_date_to'])) {
                $implode[] = "ra.`update_time` <= '" . $this->db->escape(utf8_strtolower($data['filter_date_to'])) . "'";
            }

            if (!empty($implode)) {
                $sql .= " WHERE " . implode(" AND ", $implode);
            }

            $sql .= ' GROUP BY ra.id ';
            $sql .= ') tmp';

            $query = $this->db->query($sql, $params);
            return intval($query->row['cnt']);
        }
    }

    /**
     * 查询返点合同表格展示需要的数据，分页查询
     *
     * @param $data
     * @return array
     */
    public function getRebatesContractDisplay($data)
    {
        $this->checkAndUpdateRebateTimeout();
        if (isset($data)) {
            $sql = "SELECT
        ra.id,
        ra.agreement_code,
        ra.agreement_template_id,
        ra.seller_id,
        ra.buyer_id,
        ra.`day`,
        TIMESTAMPDIFF(DAY, NOW(), ra.expire_time) AS 'day_left',
        ra.qty,
        ra.effect_time,
        ra.expire_time,
        ra.clauses_id,
        ra.`status`,
        ra.create_time,
        ra.update_time,
        ra.rebate_result,
        COUNT(rai.id) AS p_count, p.sku, p.mpn, p.product_id, p.image,
        GROUP_CONCAT(p.sku) AS 'group_sku',
        GROUP_CONCAT(p.mpn) AS 'group_mpn',
        (SELECT SUM(rao.qty) FROM oc_rebate_agreement_order AS rao WHERE rao.agreement_id=ra.id AND rao.type=1) AS 'sum_purchased_qty',
        (SELECT SUM(rao2.qty) FROM oc_rebate_agreement_order AS rao2 WHERE rao2.agreement_id=ra.id AND rao2.type=2) AS 'sum_returned_qty',
        rat.rebate_type,
        rat.rebate_value,
        rat.items,
        rat.item_num,
        rat.item_price,
        rat.item_rebates,
        ctc.screenname,
        c.customer_group_id,
        c.nickname,
        c.user_number
    FROM
        `oc_rebate_agreement` ra
        LEFT JOIN oc_rebate_agreement_item AS rai ON rai.agreement_id=ra.id
        LEFT JOIN oc_product AS p ON p.product_id=rai.product_id
        LEFT JOIN oc_rebate_agreement_template rat ON rat.id = ra.agreement_template_id
        LEFT JOIN oc_customer c ON ra.`buyer_id` = c.`customer_id`
        LEFT JOIN oc_customerpartner_to_customer ctc ON ctc.customer_id=ra.seller_id ";

            $implode = array();
            $params = [];
            if (isset($data['buyer_id'])) {
                $implode[] = "ra.`buyer_id` = " . (int)$data['buyer_id'];
            }

            if (isset($data['seller_id'])) {
                $implode[] = "ra.`seller_id` = " . (int)$data['seller_id'];
            }

            if (isset($data['contract_id'])) {
                $implode[] = ' ra.`agreement_code` like ?';
                $params[] = "%{$data['contract_id']}%";
            }

            if (isset($data['filter_store_name']) && !is_null($data['filter_store_name'])) {
                $implode[] = 'LCASE(ctc.`screenname`) LIKE ?';
                $params[] = '%' . $this->db->escape(utf8_strtolower($data['filter_store_name'])) . '%';
            }

            if (isset($data['filter_buyer_name']) && !is_null($data['filter_buyer_name'])) {
                $implode[]  =' (c.nickname like ? or c.user_number like ?)';
                $params[] = "%{$data['filter_buyer_name']}%";
                $params[] = "%{$data['filter_buyer_name']}%";
            }

            if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
                $implode[] = "ra.`status` = " . (int)$data['filter_status'];
            }

            if (isset($data['filter_result_status']) && !is_null($data['filter_result_status'])) {
                $implode[] = "ra.`rebate_result` = " . (int)$data['filter_result_status'];
            }

            if (isset($data['filter_sku_mpn']) && !empty($data['filter_sku_mpn'])) {
                if($this->customer->isPartner()){
                    $implode[] = ' (p.sku LIKE ? or p.mpn LIKE ?)';
                    $params[] = "%{$data['filter_sku_mpn']}%";
                    $params[] = "%{$data['filter_sku_mpn']}%";
                } else {
                    $implode[] = ' p.sku LIKE :filter_sku_mpn';
                    $params[] = "%{$data['filter_sku_mpn']}%";
                }
            }

            if (!empty($data['filter_date_from'])) {
                $implode[] = "ra.`update_time` >= '" . $this->db->escape(utf8_strtolower($data['filter_date_from'])) . "'";
            }
            if (!empty($data['filter_date_to'])) {
                $implode[] = "ra.`update_time` <= '" . $this->db->escape(utf8_strtolower($data['filter_date_to'])) . "'";
            }

            if (!empty($implode)) {
                $sql .= " WHERE " . implode(" AND ", $implode);
            }

            $sort_data = array(
                'rat.items',
                'ra.status',
                'ra.`update_time`',
                'c.nickname',
                'ra.rebate_result',
                'ctc.screenname'
            );

            $sql .= ' GROUP BY ra.id ';

            if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
                $sql .= " ORDER BY " . $data['sort'];
            } else {
                $sql .= " ORDER BY ra.`update_time`";
            }

            if (isset($data['order']) && (strtoupper($data['order']) == 'ASC')) {
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
            $query = $this->db->query($sql, $params);
            return $query->rows;
        }
    }


    /**
     * 列表页的格式化
     * @param $row
     * @return array
     */
    public function formatRebatesContractList($row)
    {
        //product_id
        $row['items_first'] = $row['sku'].'('.$row['mpn'].')';//Seller页面
        $items_count        = intval($row['p_count']);
        //item_sku
        $row['items_sku']    = $row['sku'];//buyer页面
        // x more items
        $items_more = '';
        if ($items_count > 1) {
            $items_more = ($items_count - 1) . ' more items';
        }
        $row['items_more'] = $items_more;
        //day_left
        $day_left = '';
        $day_left_tmp = $row['day_left'];
        if ($day_left_tmp > 0) {
            $day_left_tmp += 1;
            if ($day_left_tmp > $row['day']) {
                $day_left_tmp = $row['day'];
            }
            $day_left = $day_left_tmp . ' days left';
        } elseif ($day_left_tmp == 0) {
            $day_left = '1 day left';
        }
        $row['day_left'] = $day_left;
        //purchased_qty
        $row['purchased_qty'] = max(intval($row['sum_purchased_qty']) - intval($row['sum_returned_qty']), 0);
        //Last Modified
        $new_timezone       = changeOutPutByZone($row['update_time'], $this->session);
        $row['update_day']  = substr($new_timezone, 0, 10);
        $row['update_hour'] = substr($new_timezone, 11);

        return $row;
    }


    /**
     * 下载功能的格式化
     * @param $row
     * @return array
     */
    public function formatRebatesContractDownload($row)
    {
        //items_sku
        $items_sku_arr     = explode(',', $row['group_sku']);
        $items_mpn_arr     = explode(',', $row['group_mpn']);
        $items_sku_mpn_arr = [];
        foreach ($items_sku_arr as $index => $value) {
            $items_sku_mpn_arr[] = $items_sku_arr[$index] . '(' . $items_mpn_arr[$index] . ')';
        }
        $row['items_sku_str']     = $row['group_sku'];
        $row['items_sku_mpn_str'] = implode(',', $items_sku_mpn_arr);
        //purchased_qty
        $row['purchased_qty'] = intval($row['sum_purchased_qty']) - intval($row['sum_returned_qty']);
        //Remaining QTY
        $row['remaining_qty'] = max(intval($row['qty']) - intval($row['purchased_qty']), 0);
        //Buyer Name
        $row['buyer_name'] = $row['nickname'] . '(' . $row['user_number'] . ')';

        return $row;
    }


    /**
     * 返点四期
     * @param int $buyer_id
     * @param int $contract_id
     * @param string $reason
     */
    function cancelRebatesContract($buyer_id, $contract_id, $reason = '')
    {
        $time = date('Y-m-d H:i:s');

        $sql   = "UPDATE oc_rebate_agreement
    SET `status`=0,
      update_user_name='{$buyer_id}',
      update_time='{$time}'
    WHERE id={$contract_id}
      AND buyer_id={$buyer_id}
      AND `status`=1";
        $query = $this->db->query($sql);


        if ($query->num_rows) {
            $data = [
                'agreement_id' => $contract_id,
                'writer'       => $buyer_id,
                'message'      => $reason,
                'create_time'  => $time,
                'memo'         => 'Buyer Cancel',
            ];
            $this->orm->table('oc_rebate_message')->insert($data);
        }
    }


    /**
     * 返点四期
     * @param int $seller_id
     * @param int $buyer_id
     * @param int $contract_id 协议主键
     * @param string $reason
     * @return int 更新的协议数量
     * @throws Exception
     */
    function terminateRebatesContract($seller_id, $buyer_id, $contract_id, $reason = '')
    {
        $time = date('Y-m-d H:i:s');

        $sql   = "UPDATE oc_rebate_agreement
    SET `rebate_result`=3,
      update_user_name='{$buyer_id}',
      update_time='{$time}',
      expire_time='{$time}'
    WHERE id={$contract_id}
      AND buyer_id={$buyer_id}
      AND `status`=3
      AND (rebate_result=1 OR rebate_result=2)";
        $query = $this->db->query($sql);


        if ($query->num_rows) {
            $data = [
                'agreement_id' => $contract_id,
                'writer'       => $buyer_id,
                'message'      => $reason,
                'create_time'  => $time,
                'memo'         => 'Buyer Terminate',
            ];
            $this->orm->table('oc_rebate_message')->insert($data);


            //协议中的产品
            $sql            = "SELECT product_id FROM oc_rebate_agreement_item WHERE agreement_id={$contract_id}";
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

        return $query->num_rows;
    }


    /**
     * 检查pending状态的返点合同，如果创建时间超过了一天，需要更新为超时状态。
     *
     * 注意：因为要实时展示更新超时状态，目前没有用定时器或者其他定时任务之类的实现方法。
     * 目前以在buyer或者seller等用户查询合同信息时进行先更新后查询返回结果。
     * 所以，如果后续要开发与查询返点合同相关的需求，需要执行此方法判断更新超时状态。
     *
     * @param int|null $agreement_id
     * @return int
     */
    public function checkAndUpdateRebateTimeout($agreement_id = null)
    {
        return 0;//由定时任务实现
        $sql = "UPDATE oc_rebate_agreement
    SET `status`=4,
      `update_time`=NOW(),
      `update_user_name` = 'checkAndUpdateRebateTimeout()'
    WHERE `status`=1
        AND TIMESTAMPDIFF(DAY,create_time,NOW()) > 0";
        if (isset($agreement_id)) {
            $sql .= " AND id = '" . $agreement_id . "'";
        }
        $query = $this->db->query($sql);


        //协议将在七天内到期，协议到期前七天系统自动将Rebate Result置为Due Soon，直到协议达成/失败。
        //N-496 返点四期
        $sql = "UPDATE oc_rebate_agreement
    SET rebate_result=2,
        update_time=NOW(),
        `update_user_name` = 'checkAndUpdateRebateTimeout()'
    WHERE rebate_result IN (1)
        AND TIMESTAMPDIFF(DAY, NOW(), expire_time) < 7";
        $queryResult = $this->db->query($sql);

        return $query->num_rows;
    }


    /**
     * @param int $order_id
     * @param int $product_id
     * @return bool
     */
    public function checkIsRebate($order_id, $product_id)
    {
        return $this->orm->table('oc_rebate_agreement_order')
            ->where([
                ['order_id', '=', $order_id],
                ['product_id', '=', $product_id],
                ['type', '=', 1]
            ])
            ->exists();
    }

    public function getRebateAgreementId($order_id, $product_id)
    {
        return db('oc_rebate_agreement_order')
            ->where([
                ['order_id', '=', $order_id],
                ['product_id', '=', $product_id],
                ['type', '=', 1]
            ])
            ->value('agreement_id');
    }


    /**
     * 根据采购订单，查询有效期内的返点协议基本信息
     * @param int $order_id
     * @return array
     */
    public function getRebateProductByOrderID(int $order_id)
    {
        $order_id = intval($order_id);
        $sql = "SELECT a.id, a.id AS rebate_id, a.buyer_id, a.seller_id, ao.product_id
    FROM oc_rebate_agreement AS a
    LEFT JOIN oc_rebate_agreement_item AS ai ON ai.agreement_id=a.id
    LEFT JOIN oc_rebate_agreement_order AS ao ON ao.agreement_id=a.id
    WHERE
    ao.order_id={$order_id}
    AND ao.product_id=ai.product_id
    AND ao.type=1
    AND ai.is_delete=0
    AND a.effect_time<=NOW()
    AND a.expire_time>=NOW()
    AND a.`status`=3
    AND a.rebate_result IN (1,2)";


        $rows = $this->db->query($sql)->rows;

        $arr_product = [];
        if ($rows) {
            $arr_product = array_column($rows, null, 'product_id');
        }

        return $arr_product;
    }

}
