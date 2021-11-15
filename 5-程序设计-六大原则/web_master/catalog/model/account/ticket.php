<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;

/**
 * Class ModelAccountTicket
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCommonProduct $model_common_product
 */
class ModelAccountTicket extends Model
{
    public static $ticket_type = [
        '1' => 'RMA Management',
        '2' => 'Sales Order Management',
        '3' => 'Others',
    ];


    public function getVersion()
    {
        $query = $this->db->query("select version()");
        return $query->row;
    }


    public function categoryKeyList($role = 'buyer')
    {
        $sql     = "SELECT c.* FROM oc_ticket_category AS c INNER JOIN oc_ticket_category_role AS cr ON c.category_id = cr.category_id WHERE c.is_deleted=0 AND cr.role='{$role}' ORDER BY parent_id ASC, level ASC, sort_order ASC";
        $query   = $this->db->query($sql);
        $results = [];
        foreach ($query->rows as $value) {
            $results[$value['category_id']] = $value;
        }
        return $results;
    }


    public function categoryGroupList($role = 'buyer')
    {
        //德国81 日本107 英国222 美国223
        $country_id  = intval($this->customer->getCountryId());



        $sql     = "SELECT
	c.*
FROM
	oc_ticket_category AS c
	INNER JOIN oc_ticket_category_role AS cr ON c.category_id = cr.category_id
WHERE
	c.is_deleted = 0
	AND cr.role = '{$role}'
ORDER BY
	parent_id ASC,
	`level` ASC,
	sort_order ASC";
        $query   = $this->db->query($sql);
        $results = [];

        //$tree = $this->demo($results, 0, 0);
        //$tree = [];

        foreach ($query->rows as $value) {

            if(!$this->customer->isCollectionFromDomicile()){
                if($value['category_id'] == 18){
                    continue;
                }
            }

            //N-1062
            if($country_id != 223){
                //Intercept Sales Order 类型只有美国用户可见
                if($value['category_id'] == 5){
                    continue;
                }
                //101962 New Parts Retrieval and Reshipment 只有美国用户可见
                if($value['category_id'] == 20){
                    continue;
                }
            }
            //注意这里有字母s, 如果在前端想使用key，请在前端去掉字母s
            $results[$value['parent_id']] [$value['category_id']] = $value;
        }
        return $results;
    }


    /**
     * 参考自categoryGroupList，唯一不同的地方是key值有字母s结尾，便于转成JSON对象时保留顺序
     * @param string $role
     * @return array
     */
    public function categoryGroupListKeyStr($role = 'buyer')
    {
        //德国81 日本107 英国222 美国223
        $country_id  = intval($this->customer->getCountryId());



        $sql     = "SELECT
	c.*
FROM
	oc_ticket_category AS c
	INNER JOIN oc_ticket_category_role AS cr ON c.category_id = cr.category_id
WHERE
	c.is_deleted = 0
	AND cr.role = '{$role}'
ORDER BY
	parent_id ASC,
	`level` ASC,
	sort_order ASC";
        $query   = $this->db->query($sql);
        $results = [];

        //$tree = $this->demo($results, 0, 0);
        //$tree = [];

        foreach ($query->rows as $value) {
            $value['category_id'] = $value['category_id'] . 's';

            if(!$this->customer->isCollectionFromDomicile()){
                if($value['category_id'] == 18){
                    continue;
                }
            }
            //N-1062
            if($country_id != 223){
                //Intercept Sales Order 类型只有美国用户可见
                if($value['category_id'] == 5){
                    continue;
                }
                //101962 New Parts Retrieval and Reshipment 只有美国用户可见
                if($value['category_id'] == 20){
                    continue;
                }
            }
            //注意这里有字母s, 如果在前端想使用key，请在前端去掉字母s
            $results[$value['parent_id'].'s'] [$value['category_id']] = $value;
        }
        return $results;
    }

    public function demo($arr, $id, $level)
    {
        $list = array();
        foreach ($arr as $k => $v) {
            if ($v['parent_id'] == $id) {
                $v['level']              = $level;
                $son                     = $this->demo($arr, $v['category_id'], $level + 1);
                $v['son']                = !$son ? (object)[] : $son;
                $list[$v['category_id']] = $v;
            }
        }
        return $list;
    }


    //页面屏幕左侧居中位置，是否显示【Submit a Ticket】按钮
    public function isShowSubmitButton()
    {
        $route  = isset($this->request->get['route']) ? $this->request->get['route'] : '';
        $result = stripos($route, 'account/ticket');
        if ($result === false) {// 非Ticket页面
            return true;
        }
        return false;
    }


    public function createTicketID($id)
    {
        $prefix = date("Ymd");
        $suffix = str_pad($id, 6, '0', STR_PAD_LEFT);
        return $prefix . $suffix;
    }


    public function getTicketInfoById($id = 0)
    {
        $sql   = 'SELECT * FROM ' . DB_PREFIX . 'ticket WHERE id=' . $id;
        $query = $this->db->query($sql);
        return $query->row;
    }

    public function getTicketInfoByRmaid($rma_id = '')
    {
        $customer_id = intval($this->customer->getId());

        $sql   = "SELECT * FROM " . DB_PREFIX . "ticket WHERE rma_id='{$rma_id}' AND create_customer_id={$customer_id} order by id DESC";
        $query = $this->db->query($sql);
        return $query->row;
    }

    public function getTicketInfoBySalesorderid($sales_order_id = '')
    {
        $customer_id = intval($this->customer->getId());

        $sql   = "SELECT * FROM " . DB_PREFIX . "ticket WHERE sales_order_id='{$sales_order_id}' AND create_customer_id={$customer_id}";
        $query = $this->db->query($sql);
        return $query->row;
    }


    /**
     * 每个账号每种Ticket Type，每个ID(RMA ID / Sales Order ID，后面可能还会有其他的单据ID)只能提交一次Ticket，在Submit时验证该ID是否已提交过该类型的Ticket，如果已经提交过，则弹窗提示
     * @param $data
     * @return bool
     */
    public function isSubmited($data)
    {
        $customer_id = intval($this->customer->getId());

        //101301 除了向平台索赔(2->19)的Request Type，
        //其他的分类都不限制处理完成的ID不能重复。但是未处理完成的request所有分类都不能重复提交。
        $model = $this->orm->table(DB_PREFIX . "ticket")
            ->where('create_customer_id', $customer_id);
        if ($data['submit_ticket_for'] == 2 && $data['ticket_type'] == 19) {
            //101301 需求
            $model = $model->where('submit_ticket_for', $data['submit_ticket_for'])
                ->where('ticket_type', $data['ticket_type'])
                ->where('sales_order_id', $data['sales_order_id'])
                ->where('sales_item_code', $data['sales_item_code']);
        } elseif (!empty($data['rma_id'])) {
            $model = $model->where('submit_ticket_for', $data['submit_ticket_for'])
                ->where('ticket_type', $data['ticket_type'])
                ->where('rma_id', $data['rma_id'])
                ->whereNotIn('status', [3, 5]);
        } elseif (!empty($data['sales_order_id'])) {
            $model = $model->where('submit_ticket_for', $data['submit_ticket_for'])
                ->where('ticket_type', $data['ticket_type'])
                ->where('sales_order_id', $data['sales_order_id'])
                ->whereNotIn('status', [3, 5]);
        } else {
            return [];
        }
        $query = $model->first();

        return $query;
    }


    public function add($data)
    {
        $rma_key         = isset($data['rma_key']) ? $data['rma_key'] : 0;
        $sales_order_key = isset($data['sales_order_key']) ? $data['sales_order_key'] : 0;
        if($this->customer->isPartner()){
            $role='seller';
        }else{
            $role='buyer';
        }
        try {
            $this->db->beginTransaction(); // 开启事务
            $this->db->query("INSERT INTO " . DB_PREFIX . "ticket SET create_customer_id = '" . (int)$data['create_customer_id'] . "',
         submit_ticket_for= '" . $data['submit_ticket_for'] . "',
         ticket_type='" . $data['ticket_type'] . "',
         rma_id='" . $data['rma_id'] . "',
         rma_key='" . $rma_key . "',
         sales_order_id='" . $data['sales_order_id'] . "',
         sales_order_key='" . $sales_order_key . "',
         processing_method='" . $data['processing_method'] . "',
         sales_item_code='{$data['sales_item_code']}',
         tracking_number='{$data['tracking_number']}',
         safeguard_claim_no='{$data['safeguard_claim_no']}',
         status=1,
         date_added=NOW(),
         date_modified=NOW(),
         customer_is_read=1,
         role='{$role}'");

            $new_id = $this->db->getLastId();

            //更新ticket_id字段
            $field_ticket_id = $this->createTicketID($new_id);
            $sql             = "UPDATE " . DB_PREFIX . "ticket SET ticket_id='{$field_ticket_id}' WHERE id={$new_id}";
            $this->db->query($sql);

            $this->db->query("INSERT INTO " . DB_PREFIX . "ticket_message SET ticket_id='" .
                $new_id . "',
            create_customer_id='" . (int)$data['create_customer_id'] . "',
            description='" . $data['description'] . "',
            attachments='" . $data['attachments'] . "',
            date_added=NOW(),
            is_read=0");
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollback(); // 执行失败，事务回滚
            //$this->log->write($e->getMessage());

            return ['ret' => 0, 'msg' => 'Failed'];
        }

        return ['ret' => 1, 'msg' => 'OK'];
    }

    /**
     * @param $data
     * @param array $resultInfo 已存在的ticket记录
     * @return array
     */
    public function reply($data, $resultInfo = [])
    {
        $ticket_id = intval($data['ticket_id']);//主键id

        try {
            $this->db->beginTransaction(); // 开启事务

            $this->db->query("INSERT INTO " . DB_PREFIX . "ticket_message SET ticket_id='" .
                $ticket_id . "',
            create_customer_id='" . (int)$data['create_customer_id'] . "',
            description='" . $data['description'] . "',
            attachments='" . $data['attachments'] . "',
            date_added=NOW()");


            if (in_array($resultInfo['status'], [3, 5])) {
                $sql = "UPDATE oc_ticket SET `status` = 2, date_modified = NOW(), delay_flag=0
                WHERE id = {$ticket_id} AND create_customer_id = " . (int)$data['create_customer_id'];
                $this->db->query($sql);
            } elseif (in_array($resultInfo['status'], [4])) {
                $sql = "UPDATE oc_ticket SET date_modified = NOW(), delay_flag=0
                WHERE id = {$ticket_id} AND create_customer_id = " . (int)$data['create_customer_id'];
                $this->db->query($sql);
            }

            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollback(); // 执行失败，事务回滚
            //$this->log->write($e->getMessage());

            return ['ret' => 0, 'msg' => 'Failed'];
        }

        return ['ret' => 1, 'msg' => 'OK'];
    }

    public function unreadCount($data = array())
    {
        $customer_id = intval($this->customer->getId());

        $sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "ticket WHERE create_customer_id={$customer_id} AND customer_is_read=0";


        $query = $this->db->query($sql);

        return intval($query->row['total']);
    }


    public function getRamOrderInfoByRmaid($rma_order_id = '')
    {
        $buyer_id = intval($this->customer->getId());

        $sql    = "SELECT id,seller_id FROM " . DB_PREFIX . "yzc_rma_order WHERE rma_order_id='{$rma_order_id}'";
        $query  = $this->db->query($sql);
        $result = $query->row;
        return $result;
    }

    /**
     * 判断seller下oma id是否存在
     *
     * @param        $sellerId
     * @param string $rma_order_id
     *
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getSellerRamOrderInfoByRmaid($sellerId, $rma_order_id = '')
    {
        $result = $this->orm->table(DB_PREFIX . "yzc_rma_order")
            ->where('seller_id', $sellerId)
            ->where('rma_order_id', $rma_order_id)
            ->first(['id', 'seller_id']);
        return $result;
    }


    public function getSalesOrderInfoByRmaid($orderid = '')
    {
        $buyer_id = intval($this->customer->getId());

        $sql    = "SELECT id, order_id FROM tb_sys_customer_sales_order WHERE buyer_id={$buyer_id} AND order_id='{$orderid}'";
        $query  = $this->db->query($sql);
        $result = $query->row;
        return $result;
    }


    public function autocompletermaid($rmaid = '')
    {
        $customerId = intval($this->customer->getId());
        if($this->customer->isPartner()){
            $sql    = "SELECT id,rma_order_id FROM " . DB_PREFIX . "yzc_rma_order WHERE rma_order_id LIKE '{$rmaid}%' AND seller_id={$customerId} ORDER BY id DESC limit 0, 20";
        } else{
            $sql    = "SELECT id,rma_order_id FROM " . DB_PREFIX . "yzc_rma_order WHERE rma_order_id LIKE '{$rmaid}%' AND buyer_id={$customerId} ORDER BY id DESC limit 0, 20";
        }

        $query  = $this->db->query($sql);
        $result = $query->rows;
        return $result;
    }

    public function autocompleteorderid($orderid = '')
    {
        $buyer_id = intval($this->customer->getId());

        $sql    = "SELECT id, order_id FROM tb_sys_customer_sales_order WHERE buyer_id={$buyer_id} AND order_id LIKE '%{$orderid}%' ORDER BY id DESC limit 0,20";
        $query  = $this->db->query($sql);
        $result = $query->rows;
        return $result;
    }

    /**
     * 根据orderId查询order的item code
     *
     * @param string $customerId
     * @param string $orderId
     *
     * @return \Illuminate\Support\Collection [item_code]
     */
    public function getItemCodeListByOrderId($customerId, $orderId)
    {
        $itemCodeModel = $this->orm->table('tb_sys_customer_sales_order_line as l')
            ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
            ->where('o.order_id', $orderId)
            ->where('o.buyer_id', $customerId)
            ->select('l.item_code');
        return $itemCodeModel->get();
    }

    /**
     * 根据item code 查询seller下的所有item code
     *
     * @param int $sellerId
     * @param $itemCode
     *
     * @return \Illuminate\Support\Collection
     */
    public function getItemCodeListBySeller($sellerId,$itemCode)
    {
        $itemCodeModel = $this->orm->table('oc_customerpartner_to_product as cp')
            ->leftJoin('oc_product as p', 'cp.product_id', '=', 'p.product_id')
            ->where('cp.customer_id', $sellerId)
            ->where('p.sku','like', "%{$itemCode}%")
            ->select('p.sku as item_code');
        return $itemCodeModel->get();
    }

    /**
     * @param int $id ticket主键
     */
    public function updateReadTime($id)
    {
        $id = intval($id);

        $sql   = "UPDATE " . DB_PREFIX . "ticket SET date_modified=NOW(), customer_is_read=1 WHERE id={$id}";
        $query = $this->db->query($sql);

        $sql   = "UPDATE " . DB_PREFIX . "ticket_message SET date_read=NOW(), is_read=1 WHERE ticket_id={$id} AND create_admin_id>0";
        $query = $this->db->query($sql);
    }


    //某Ticket下所有的Message，不分页
    public function ticketMessaes($id)
    {
        $id          = intval($id);
        $customer_id = intval($this->customer->getId());

        $sql    = "SELECT * FROM " . DB_PREFIX . "ticket_message WHERE ticket_id={$id} ORDER BY id DESC";
        $query  = $this->db->query($sql);
        $result = $query->rows;
        return $result;
    }

    public function getTicketsByRmaId($rma_no, $customer_id)
    {
        $res = $this->orm->table('oc_ticket as ot')
            ->select('tm.*')
            ->leftJoin('oc_ticket_message as tm', 'ot.id', 'tm.ticket_id')
            ->where('ot.rma_id', $rma_no)
            ->where('tm.create_customer_id',$customer_id)
            ->orderBy('tm.id', 'DESC')
            ->get();
        return obj2array($res);
    }


    //获取所有管理员
    public function getUsers()
    {
        $sql   = "SELECT user_id,user_group_id,firstname,lastname FROM `" . DB_PREFIX . "user`";
        $query = $this->db->query($sql);

        return $query->rows;
    }


    //Tickets 页面 各种数量
    public function clusterCount($param = [])
    {
        $customer_id       = intval($this->customer->getId());
        $submit_ticket_for = isset($param['submit_ticket_for']) ? $param['submit_ticket_for'] : 0;


        $isPartner = $this->customer->isPartner();
        if($isPartner){
            $role='seller';
        } else {
            $role='buyer';
        }


        $categoryGroupList = $this->categoryGroupList($role);



        //submit_ticket_for
        $submit_ticket_for_counts = [];//ticket分类表中所有的 key=ticket_type, value=count
        $resultsKey               = []; //ticket表中已存在的   key=ticket_type, value=count
        $submit_ticket_for_noread = []; //ticket分类表中所有的 key=ticket_type, value=是否有未读
        foreach ($categoryGroupList[0] as $key => $value) {
            $submit_ticket_for_counts[$value['category_id']] = 0;
            $submit_ticket_for_noread[$value['category_id']] = 0;
        }
        if ($submit_ticket_for_counts) {
            $sql     = "SELECT COUNT(id) AS counts, submit_ticket_for, MIN(customer_is_read) AS customer_is_read FROM oc_ticket WHERE create_customer_id={$customer_id}  GROUP BY submit_ticket_for";
            $query   = $this->db->query($sql);
            $results = $query->rows;
            foreach ($results as $value) {
                $resultsKey[$value['submit_ticket_for']]               = $value['counts'];
                $submit_ticket_for_noread[$value['submit_ticket_for']] = intval(!$value['customer_is_read']);
            }
        }
        foreach ($submit_ticket_for_counts as $key => $value) {
            $data['submit_ticket_for_count_' . $key] = isset($resultsKey[$key]) ? intval($resultsKey[$key]) : 0;
        }
        foreach ($submit_ticket_for_noread as $key => $value) {
            $data['submit_ticket_for_noread_' . $key] = $value;
        }


        //ticket_type
        $ticket_type_counts = []; //ticket分类表中所有的 key=ticket_type, value=count
        $resultsKey         = []; //ticket表中已存在的   key=ticket_type, value=count
        $ticket_type_noread = []; //ticket分类表中所有的 key=ticket_type, value=是否有未读
        if (isset($submit_ticket_for) && $categoryGroupList[$submit_ticket_for]) {
            foreach ($categoryGroupList[$submit_ticket_for] as $key => $value) {
                $ticket_type_counts[$key] = 0;
                $ticket_type_noread[$key] = 0;
            }
        }
        if ($ticket_type_counts) {
            $sql     = "SELECT COUNT(id) AS counts, ticket_type, MIN( customer_is_read ) AS customer_is_read FROM oc_ticket WHERE create_customer_id={$customer_id} AND submit_ticket_for={$submit_ticket_for} GROUP BY ticket_type";
            $query   = $this->db->query($sql);
            $results = $query->rows;
            foreach ($results as $value) {
                $resultsKey[$value['ticket_type']]         = $value['counts'];
                $ticket_type_noread[$value['ticket_type']] = intval(!$value['customer_is_read']);
            }
        }
        foreach ($ticket_type_counts as $key => $value) {
            $data['ticket_type_count_' . $key] = isset($resultsKey[$key]) ? intval($resultsKey[$key]) : 0;
        }
        foreach ($ticket_type_noread as $key => $value) {
            $data['ticket_type_noread_' . $key] = $value;
        }


        //New Reply
        $data['new_reply_count'] = 0;
        if ($submit_ticket_for) {
            $sql     = "SELECT COUNT(id) AS counts FROM oc_ticket WHERE create_customer_id={$customer_id} AND submit_ticket_for={$submit_ticket_for} AND customer_is_read=0";
            $query   = $this->db->query($sql);
            $results = $query->row;

            $data['new_reply_count'] = isset($results['counts']) ? intval($results['counts']) : 0;
        }


        //All Tickets
        $data['all_tickets_count'] = 0;
        if ($submit_ticket_for) {
            $sql     = "SELECT COUNT(id) AS counts FROM oc_ticket WHERE create_customer_id={$customer_id} AND submit_ticket_for={$submit_ticket_for}";
            $query   = $this->db->query($sql);
            $results = $query->row;

            $data['all_tickets_count'] = isset($results['counts']) ? intval($results['counts']) : 0;
        }


        return $data;

    }


    public function noReadCount($customer_id)
    {
        return $this->orm->table('oc_ticket')
            ->selectRaw('submit_ticket_for, count(*) as total ')
            ->where(['create_customer_id' => $customer_id, 'customer_is_read' => 0])
            ->groupBy('submit_ticket_for')
            ->get()->keyBy('submit_ticket_for');
    }


    public function searchTotal($param = [])
    {
        $customer_id       = intval($this->customer->getId());
        $submit_ticket_for = $param['submit_ticket_for'];
        $right             = $param['right'];
        $wd                = addslashes($param['wd']);

        $condition = " create_customer_id={$customer_id}";
        $condition .= " AND submit_ticket_for=" . $submit_ticket_for;

        if ($right == 'newreply') {
            $condition .= " AND customer_is_read=0";
        } elseif ($right == 'alltickets') {
            // no condition
        } else {
            $condition .= " AND ticket_type=" . ((int)$right);
        }

        if ($wd) {
            if ($submit_ticket_for == 1) {
                $condition .= " AND ((ticket_id LIKE '%{$wd}%') OR (rma_id LIKE '%{$wd}%'))";
            } elseif ($submit_ticket_for == 2) {
                if ($right == 5) {
                    $condition .= " AND ((ticket_id LIKE '%{$wd}%') OR (sales_order_id LIKE '%{$wd}%'))";
                } else {
                    $condition .= " AND ((ticket_id LIKE '%{$wd}%') OR (sales_order_id LIKE '%{$wd}%') OR (sales_item_code LIKE '%{$wd}%'))";
                }
            } elseif ($submit_ticket_for == 9){
                $condition .= " AND (ticket_id LIKE '%{$wd}%' OR rma_id LIKE '%{$wd}%' OR sales_item_code LIKE '%{$wd}%')";
            } else {
                $condition .= " AND ticket_id LIKE '%{$wd}%'";
            }
        }

        $sql   = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "ticket WHERE " . $condition;
        $query = $this->db->query($sql);
        return $query->row['total'];
    }


    public function searchLists($param = [])
    {
        $customer_id       = intval($this->customer->getId());
        $submit_ticket_for = $param['submit_ticket_for'];
        $right             = $param['right'];
        $wd                = $param['wd'];
        $sort              = $param['sort'];//字段
        $order             = $param['order'];//升序降序



        $maxIdSqlObj = $this->orm->table(DB_PREFIX . 'ticket_message')
            ->selectRaw('MAX(id) as id')
            ->where('create_customer_id', $customer_id)
            ->groupBy('ticket_id');
        $maxIdSql = get_complete_sql($maxIdSqlObj);
        $subSql = $this->orm->table(DB_PREFIX . 'ticket_message')
            ->whereRaw("id in ({$maxIdSql})")
            ->select('id', 'ticket_id', 'create_admin_id', 'date_added')
            ->toSql();

        $listSql = $this->orm->table(DB_PREFIX . 'ticket as t')
            ->leftJoin(new Expression("($subSql) as tm"), 't.id', '=', 'tm.ticket_id')
            ->selectRaw('t.*,tm.create_admin_id,tm.date_added as modi_date,IF(tm.create_admin_id>0,0,1) as modi_status')
            ->where('t.create_customer_id', $customer_id)
            ->where('t.submit_ticket_for', $submit_ticket_for);

        if ($right == 'newreply') {
            $listSql->where('t.customer_is_read', 0);
        } elseif ($right == 'alltickets') {
            // no condition
        } else {
            $listSql->where('t.ticket_type', ((int)$right));
        }
        if ($wd) {
            if ($submit_ticket_for == 1) {
                $listSql->where(function ($query) use ($wd) {
                    $query->where('t.ticket_id', 'like', "%{$wd}%")
                        ->orWhere('t.rma_id', 'like', "%{$wd}%");
                });
            } elseif ($submit_ticket_for == 2) {
                if ($right == 5) {
                    $listSql->where(function ($query) use ($wd) {
                        $query->where('t.ticket_id', 'like', "%{$wd}%")
                            ->orWhere('t.sales_order_id', 'like', "%{$wd}%");
                    });
                } else {
                    $listSql->where(function ($query) use ($wd) {
                       $query->where('t.ticket_id', 'like', "%{$wd}%")
                           ->orWhere('t.sales_order_id', 'like', "%{$wd}%")
                           ->orWhere('t.sales_item_code', 'like', "%{$wd}%");
                    });
                }
            } elseif ($submit_ticket_for == 9){
                $listSql->where(function ($query) use ($wd) {
                    $query->where('t.ticket_id', 'like', "%{$wd}%")
                        ->orWhere('t.rma_id', 'like', "%{$wd}%")
                        ->orWhere('t.sales_item_code', 'like', "%{$wd}%");
                });
            } elseif ($submit_ticket_for == 21 && $right) {
                $listSql->where(function ($query) use ($wd) {
                    $query->where('t.ticket_id', 'like', "%{$wd}%")
                        ->orWhere('t.safeguard_claim_no', 'like', "%{$wd}%");
                });
            } else {
                $listSql->where('t.ticket_id', 'like', "%{$wd}%");
            }
        }
        //排序
        if ($sort == 'customer_is_read') {
            $listSql->orderBy('modi_status', $order)->orderBy('tm.date_added', 'desc')->orderBy('t.id', 'desc');
        } elseif ($sort == 'date_modified') {
            $listSql->orderBy('tm.date_added', $order)->orderBy('modi_status', 'asc');
        } else {
            $listSql->orderBy('modi_status', 'asc')->orderBy('tm.date_added', 'desc')->orderBy('id', 'desc');
        }

        //分页
        !isset($param['page_num']) && $param['page_num'] = 1;
        $listSql->offset(($param['page_num'] - 1) * $param['page_limit'])->limit($param['page_limit']);

        $list = obj2array($listSql->get());

        return $list;
    }

    public function checkRmaId($rma_id){
        $result = $this->db->query("select rop.status_reshipment from oc_yzc_rma_order ro
        LEFT JOIN oc_yzc_rma_order_product rop ON rop.rma_id= ro.id where ro.rma_order_id='".$rma_id."' and ro.buyer_id = ".$this->customer->getId())->row;
        return $result['status_reshipment'] ?? 0;
    }

    /**
     * 判断指定item code是否在seller下
     *
     * @param int $sellerId
     * @param $itemCode
     *
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     * 如果存在 会返回{product_id,item_code,quantity,instock_qty} quantity 是库存 instock_qty是在库库存
     */
    public function checkSellerItemCode($sellerId,$itemCode)
    {
        $itemCodeModel = $this->orm->table('oc_customerpartner_to_product as cp')
            ->leftJoin('oc_product as p', 'cp.product_id', '=', 'p.product_id')
            ->where('cp.customer_id', $sellerId)->where('p.sku', "{$itemCode}")
            ->select('p.product_id','p.sku as item_code','p.quantity')->first();
        if ($itemCodeModel) {
            $this->load->model('catalog/product');
            $this->load->model('common/product');
            $stock_num = $this->model_catalog_product->queryStockByProductId($itemCodeModel->product_id)['total_onhand_qty'];
            $lockQty = $this->model_common_product->getProductLockQty($itemCodeModel->product_id);
            //需要减去锁定库存
            $itemCodeModel->instock_qty = $stock_num - $lockQty;
        }
        return $itemCodeModel;
    }

    /**
     * 获取seller下指定rma id的status_reshipment
     * 成功会返回 status_reshipment重发状态0：初始1同意2拒绝
     * 失败会返回false
     * 注意判断要用===false
     *
     * @param int $sellerId
     * @param $rma_id
     *
     * @return bool|mixed
     */
    public function checkSellerRmaId($sellerId,$rma_id){
        $result = $this->orm->table('oc_yzc_rma_order as ro')
            ->leftJoin('oc_yzc_rma_order_product rop', 'rop.rma_id', '=', 'ro.id')
            ->where('ro.rma_order_id', $rma_id)
            ->where('ro.seller_id', $sellerId)
            ->first(['rop.status_reshipment']);
        return $result->status_reshipment ?? false;
    }


    /**
     * 根据订单获取订单状态
     * @param string $order_id
     * @param int $customer_id
     * @return mixed
     */
    public function getOrderStatusBySaleOrderId($order_id,$customer_id){
        $order = $this->orm->table('tb_sys_customer_sales_order')
            ->where('order_id', $order_id)
            ->where('buyer_id', $customer_id)
            ->first(['order_status']);
        return $order->order_status ?? false;
    }
}
