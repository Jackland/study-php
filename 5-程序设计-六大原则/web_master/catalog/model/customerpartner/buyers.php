<?php

use App\Enums\Message\MsgCustomerExtLanguageType;
use Illuminate\Database\Query\Builder;
/**
 * Class ModelCustomerpartnerBuyers
 * @property ModelMessageMessage $model_message_message
 *
 * User: 李磊
 * Date: 2018/10/29
 * Time: 12:32
 * Description: Buyers Model 层
 */
class ModelCustomerpartnerBuyers extends Model {
    public $table = 'oc_buyer_to_seller';

    public function getBuyersByCustomerId($customer_id, $filter_data)
    {
        $sql = "SELECT bts.id,bts.buyer_id,cc.customer_id,bts.`buy_status`,bts.`price_status`,bts.`discount`,bts.`remark`,bts.`add_time`,bts.`seller_control_status`,bts.`buyer_control_status`,
cc.nickname,cc.user_number, CONCAT(cc.nickname,'(',cc.user_number,')') AS buyer_name,cc.`email` AS buyer_email , cc.customer_group_id,
bg.id as buyer_group_id , bg.name as buyer_group_name ,bg.is_default
FROM `". DB_PREFIX . "buyer_to_seller` bts
LEFT JOIN `" . DB_PREFIX . "customer` cc ON bts.`buyer_id` = cc.`customer_id`
left join `oc_customerpartner_buyer_group_link` as bgl on bgl.buyer_id = bts.buyer_id and bgl.seller_id = $customer_id  and bgl.status =1
left join `oc_customerpartner_buyer_group` as bg on bg.id = bgl.buyer_group_id and bg.status = 1
WHERE cc.status = 1 and bts.`seller_id` = " . $customer_id;
        if (isset($filter_data["filter_buyer_name"]) && $filter_data["filter_buyer_name"] != "") {
            $sql .= " AND ( cc.`nickname` LIKE '%" . $this->db->escape($filter_data["filter_buyer_name"]) . "%' OR cc.user_number LIKE '%" . $this->db->escape($filter_data["filter_buyer_name"]) . "%')";
        }
        if (isset($filter_data["filter_buyer_email"]) && $filter_data["filter_buyer_email"] != "") {
            $sql .= " AND (cc.`email` LIKE '%" . $filter_data["filter_buyer_email"] . "%') ";
        }
        if (isset($filter_data["filter_status"]) && $filter_data["filter_status"] != "") {
            $sql .= " AND (bts.`seller_control_status` = " . (int)$filter_data["filter_status"] . ") ";
        }
        if (isset($filter_data["filter_date_added_from"]) && !empty($filter_data["filter_date_added_from"])) {
            $sql .= " AND (bts.`add_time` >= '" . $filter_data["filter_date_added_from"] . "') ";
        }

        if (isset($filter_data["filter_date_added_to"]) && !empty($filter_data["filter_date_added_to"])) {
            $sql .= " AND (bts.`add_time` <= '" . $filter_data["filter_date_added_to"] . "') ";
        }

        if (isset($filter_data['filter_buyer_group_id']) && !empty($filter_data['filter_buyer_group_id'])) {
            $sql .= " AND bgl.buyer_group_id = " . (int)$filter_data['filter_buyer_group_id'] . " ";
            $sql .= " AND bg.id = " . (int)$filter_data['filter_buyer_group_id'] . " ";
        }
        $sql .= " ORDER BY " . $filter_data["sort"] . " " . $filter_data["order"];
        if (isset($filter_data['page_num']) && $filter_data['page_limit']) {
            $sql .= " LIMIT " . (($filter_data['page_num'] - 1) * $filter_data['page_limit']) . "," . $filter_data['page_limit'];
        }
        return $this->db->query($sql);
    }

    public function getBuyersTotalByCustomerId($customer_id, $filter_data)
    {
        $sql = "SELECT COUNT(*) AS total
FROM `". DB_PREFIX . "buyer_to_seller` bts
LEFT JOIN `". DB_PREFIX . "customer` sc ON bts.`seller_id` = sc.`customer_id`
LEFT JOIN `" . DB_PREFIX . "customer` cc ON bts.`buyer_id` = cc.`customer_id`
left join `oc_customerpartner_buyer_group_link` as bgl on bgl.buyer_id = bts.buyer_id and bgl.seller_id = $customer_id  and bgl.status =1
left join `oc_customerpartner_buyer_group` as bg on bg.id = bgl.buyer_group_id and bg.status = 1
WHERE cc.status = 1 and bts.`seller_id` = " . $customer_id;
        if (isset($filter_data["filter_buyer_name"]) && $filter_data["filter_buyer_name"] != "") {
            $sql .= " AND ( cc.`nickname` LIKE '%" . $this->db->escape($filter_data["filter_buyer_name"]) . "%' OR cc.user_number LIKE '%" . $this->db->escape($filter_data["filter_buyer_name"]) . "%')";
        }
        if (isset($filter_data["filter_buyer_email"]) && $filter_data["filter_buyer_email"] != "") {
            $sql .= " AND (cc.`email` LIKE '%" . $filter_data["filter_buyer_email"] . "%') ";
        }
        if (isset($filter_data["filter_status"]) && $filter_data["filter_status"] != "") {
            $sql .= " AND (bts.`seller_control_status` = " . (int)$filter_data["filter_status"] . ") ";
        }

        if (isset($filter_data["filter_date_added_from"]) && !empty($filter_data["filter_date_added_from"])) {
            $sql .= " AND (bts.`add_time` >= '" . $filter_data["filter_date_added_from"] . "') ";
        }

        if (isset($filter_data["filter_date_added_to"]) && !empty($filter_data["filter_date_added_to"])) {
            $sql .= " AND (bts.`add_time` <= '" . $filter_data["filter_date_added_to"] . "') ";
        }

        if (isset($filter_data['filter_buyer_group_id']) && !empty($filter_data['filter_buyer_group_id'])) {
            $sql .= " AND bgl.buyer_group_id = " . (int)$filter_data['filter_buyer_group_id'] . " ";
            $sql .= " AND bg.id = " . (int)$filter_data['filter_buyer_group_id'] . " ";
        }
        return $this->db->query($sql)->row['total'];
    }

    public function updateBuyerInfo($updateDate)
    {
        $sql = "UPDATE `" . DB_PREFIX . "buyer_to_seller` bts SET bts.`seller_control_status` = " . $updateDate['seller_control_status'] . ", bts.`discount` = " . $updateDate['discount'] . ", bts.`remark` = '" . $updateDate['remark'] . "' WHERE bts.`id` = " . $updateDate['id'];
        $this->db->query($sql);
    }

    public function batchUpdate($data)
    {
        $this->orm->table($this->table)
            ->where('seller_id', $data['seller_id'])
            ->whereIn('id', $data['idArr'])
            ->update($data['update']);
    }

    /**
     * 获取buyer_id
     *
     * @param array $ids
     * @return \Illuminate\Support\Collection|array
     */
    public function getBuyerID($ids)
    {
        return $this->orm->table($this->table)
            ->whereIn('id', $ids)
            ->pluck('buyer_id');
    }

    /**
     * @param int $id
     * @return \Illuminate\Database\Query\Builder|mixed
     */
    public function getSingle($id)
    {
        return $this->orm->table($this->table)->find($id, ['buyer_id']);
    }

    /**
     * [verifyBuyerStatus description]
     * @param int $id
     * @param int $status
     * @return int|bool
     */
    public function verifyBuyerStatus($id,$status){
        $query_info = $this->orm->table(DB_PREFIX.'buyer_to_seller as bts')
            ->where('bts.id',$id)->select('seller_control_status','buyer_id')->first();
        if($query_info->seller_control_status == (int)$status){
            return false;
        }
        return $query_info->buyer_id;
    }

    /**
     * [sendProductionInfoToBuyer description] 根据buyer_id 和 seller_id 发站内信
     * @param int $buyer_id
     * @param int $seller_id
     * @param int $flag
     * @throws Exception
     */
    public function sendProductionInfoToBuyer($buyer_id,$seller_id,$flag = 0){
        //1. 查询订阅此产品的buyer 且buyer seller 建立了联系
        $map = [
            ['bts.seller_id','=',$seller_id],
            ['ctp.customer_id','=',$seller_id],
            ['bts.buyer_id','=',$buyer_id],
        ];
        $res = $this->orm->table(DB_PREFIX.'customer_wishlist as cw')
            ->leftJoin(DB_PREFIX.'buyer_to_seller as bts','bts.buyer_id','=','cw.customer_id')
            ->
            leftJoin('vw_delicacy_management as dm',function ($join){
                $join->on('dm.buyer_id', '=', 'bts.buyer_id')->on('dm.product_id','=','cw.product_id')->whereRaw("dm.expiration_time > '".date('Y-m-d H:i:s',time())."' COLLATE utf8mb4_general_ci");
            })->
            leftJoin(DB_PREFIX.'product as p','p.product_id','=','cw.product_id')->
            leftJoin(DB_PREFIX.'customerpartner_to_product as ctp','p.product_id','=','ctp.product_id')->
            leftJoin('oc_product_description as pd','pd.product_id','=','p.product_id')->
            where($map)->where(function ($query) {
                $query->where('dm.product_display',1)->orWhereNull('dm.id');
            })->select('bts.buyer_id','bts.seller_id','p.sku','pd.name','p.product_id','p.quantity','bts.discount')->selectRaw('round(ifnull(dm.price,p.price),2) as price')->get();
        $res = obj2array($res);
        $this->load->model('message/message');

        if($flag == 0){
            // 下架
            foreach($res as $key => $value){
                $title = 'Product Unavailable Alert (Item code: '.$value['sku'].')';
                $message = $this->getSendMessageTemplate($flag,$value);

                //$this->communication->saveCommunication($title, $message, $value['buyer_id'],$seller_id,  0);
                //新消息中心 From System
                $this->model_message_message->addSystemMessageToBuyer('product_status',$title,$message,$value['buyer_id']);
            }

        }else{
            //上架
            foreach($res as $key => $value){
                $title = 'Product Available Alert (Item code: '.$value['sku'].')';
                $message = $this->getSendMessageTemplate($flag,$value);

                //$this->communication->saveCommunication($title, $message,$value['buyer_id'], $seller_id, 0);

                //新消息中心 From System
                $this->model_message_message->addSystemMessageToBuyer('product_status',$title,$message,$value['buyer_id']);

            }
        }


    }

    /**
     * [getSendMessageTemplate description]
     * @param int $flag
     * @param array $product_info
     * @return string
     */
    public function getSendMessageTemplate($flag,$product_info){

        if($flag == 0){
            $message = '<table   border="0" cellspacing="0" cellpadding="0" >';
            $message .= '<tr><th align="left">Item Code:&nbsp;</th><td>' . $product_info['sku'] . '</td></tr> ';
            $message .= '<tr><th align="left">Product Name:&nbsp;</th><td>' . $product_info['name']  . '</td></tr>';
            $message .= '<tr><th align="left">Product Status:&nbsp;</th><td>Unavailable</td></tr></table>';
        }else{
            $country_id = $this->customer->getCountryId();
            $product_info['price'] = $product_info['price']*$product_info['discount'];
            if($country_id == 107){
                $product_info['price'] = (int)$product_info['price'];
            }else{
                $product_info['price'] = sprintf('%.2f',$product_info['price']);
            }
            $message = '<table   border="0" cellspacing="0" cellpadding="0" >';
            $message .= '<tr><th align="left">Item Code:&nbsp;</th><td>' . $product_info['sku'] . '</td></tr> ';
            $message .= '<tr><th align="left">Product Name:&nbsp;</th><td>' . $product_info['name'] . '</td></tr>';
            $message .= '<tr><th align="left">Product Status:&nbsp;</th><td>Available</td></tr>';
            $message .= '<tr><th align="left">Unit Price:&nbsp;</th><td>' . $product_info['price'] . '</td></tr>';
            $message .= '<tr><th align="left">Available Quantity:&nbsp;</th><td>' . $product_info['quantity'] . '</td></tr></table>';
        }
        return $message;
    }

    /**
     * @param array $input
     * @param bool $isActive 是否查询建立关联的
     * @param bool $isDownload 是否是下载
     * @return mixed
     */
    public function getList($input, $isActive = true, $isDownload = false)
    {
        $builder = $this->orm->table('oc_buyer_to_seller as bts')
            ->join('oc_customer as b', 'b.customer_id', '=', 'bts.buyer_id')
            ->when($isActive, function ($join) use ($input) {
                $join->leftJoin('oc_customerpartner_buyer_group_link as bgl', function ($query) use ($input) {
                    $query->on('bgl.buyer_id', '=', 'bts.buyer_id')
                        ->where([
                            ['bgl.seller_id', '=', $input['seller_id']],
                            ['bgl.status', '=', 1]
                        ]);
                })
                    ->leftJoin('oc_customerpartner_buyer_group as bg', function ($query) use ($input) {
                        $query->on('bg.id', '=', 'bgl.buyer_group_id')
                            ->where([
                                ['bg.seller_id', '=', $input['seller_id']],
                                ['bg.status', '=', 1]
                            ]);
                    });
            })
            ->select(['b.customer_id as buyer_id', 'b.nickname', 'bts.id','b.user_number'])
            ->when($isDownload, function (Builder $query)  {
                $query->leftJoin('oc_buyer_user_portrait as bup', 'bts.buyer_id', '=', 'bup.buyer_id')
                    ->addSelect(['bup.monthly_sales_count','bup.return_rate','bup.complex_complete_rate','bup.first_order_date','bup.registration_date', 'bup.main_category_id']);
            })
            ->where([
                ['bts.seller_id', '=', $input['seller_id']],
                ['bts.seller_control_status', '=', $isActive ? 1 : 0],
                ['b.status', '=', 1]
            ])
            ->when(isset($input['filter_name']) && !empty($input['filter_name']), function (Builder $query) use ($input) {
                $query->where(function (Builder $q) use ($input) {
                    $q->where('b.nickname', 'like', '%' . $input['filter_name'] . '%')
                        ->orWhere('b.user_number', 'like', '%' . $input['filter_name'] . '%');
                });
            })
            ->when($isActive && isset($input['filter_buyer_group_id']) && !empty($input['filter_buyer_group_id']), function (Builder $query) use ($input) {
                $query->where('bg.id', '=', $input['filter_buyer_group_id']);
            })
            ->when(isset($input['filter_date_from']) && !empty($input['filter_date_from']), function (Builder $query) use ($input) {
                $query->where('bts.last_transaction_time', '>=', $input['filter_date_from']);
            })
            ->when(isset($input['filter_language']) && $input['filter_language'] != '', function (Builder $query) use ($input) {
                $query->leftJoin('oc_msg_customer_ext as mce', 'mce.customer_id', '=', 'b.customer_id')
                    ->where(function ($q) use ($input) {
                        if ($input['filter_language'] == MsgCustomerExtLanguageType::NOT_LIMIT) {
                            $q->where('mce.language_type', $input['filter_language'])->orWhereNull('mce.language_type');
                        } else {
                            $q->where('mce.language_type', $input['filter_language']);
                        }
                    });
            })
            ->when(isset($input['filter_date_to']) && !empty($input['filter_date_to']), function (Builder $query) use ($input) {
                $query->where([
                    ['bts.last_transaction_time', '<=', $input['filter_date_to']],
                    ['bts.last_transaction_time', '>', '1979-01-01 00:00:01']
                ]);
            });
        if (! empty($input['filter_is_all_select']) && $input['filter_is_all_select'] == 1) {
            $results['data'] = $builder
                ->orderBy('bts.last_transaction_time', 'DESC')
                ->orderBy('bts.id', 'DESC')
                ->get();
        } else {
            if (!$isDownload) {
                $results['total'] = $builder->count();
            }
            $results['data'] = $builder
                ->addSelect([
                    'b.customer_group_id',
                    'bts.last_transaction_time', 'bts.number_of_transaction', 'bts.money_of_transaction',
                    'bts.remark', 'bts.seller_control_status as status'
                ])
                ->when($isActive, function (Builder $query) {
                    $query->addSelect([
                        'bg.id as buyer_group_id', 'bg.name as buyer_group_name', 'bg.is_default',
                    ]);
                })
                ->when(!$isDownload, function (Builder $query) use ($input) {
                    $query->forPage($input['page'], $input['pageSize']);
                })
                ->orderBy('bts.last_transaction_time', 'DESC')
                ->orderBy('bts.id', 'DESC')
                ->get()
                ->map(function ($v){
                    // 此处last_transaction_time sql中默认值为1970-01-01 00:00:00
                    if($v->last_transaction_time == '1970-01-01 00:00:00'){
                        $v->last_transaction_time = 'N/A';
                    }
                    return $v;
                });
        }

        return $results;
    }

    /**
     * seller 恢复其与buyer的关联
     * @param int $id
     * @param int $seller_id
     */
    public function recovery($id,$seller_id)
    {
        $this->orm->table('oc_buyer_to_seller')
            ->where([
                ['id', '=', $id],
                ['seller_control_status', '=', 0],
                ['seller_id','=',$seller_id]
            ])
            ->update(['seller_control_status' => 1]);
    }

    /**
     * seller 批量恢复其与buyer的关联
     *
     * @param array $ids
     * @param int $seller_id
     */
    public function batchRecovery($ids,$seller_id)
    {
        $this->orm->table('oc_buyer_to_seller')
            ->where([
                ['seller_control_status', '=', 0],
                ['seller_id','=',$seller_id]
            ])
            ->whereIn('id',$ids)
            ->update(['seller_control_status' => 1]);
    }

    public function updateTransaction($seller_id,$buyer_id,$data)
    {
        $oldObj = $this->orm->table($this->table)
            ->where([
                ['seller_id', '=', $seller_id],
                ['buyer_id', '=', $buyer_id]
            ])
            ->first(['id', 'last_transaction_time', 'number_of_transaction', 'money_of_transaction']);
        if (!empty($oldObj)) {
            if (isset($data['number_of_transaction']) && !empty((int)$data['number_of_transaction'])) {
                $update['number_of_transaction'] = $oldObj->number_of_transaction + (int)$data['number_of_transaction'];
            }

            isset_and_not_empty($data, 'money_of_transaction')
            && $update['money_of_transaction'] = $oldObj->money_of_transaction + (int)$data['money_of_transaction'];

            if (!empty($data['last_transaction_time']) && $data['last_transaction_time'] > $oldObj->last_transaction_time) {
                $update['last_transaction_time'] = $data['last_transaction_time'];
            }

            !empty($update) && $this->orm->table($this->table)
                ->where('id', $oldObj->id)
                ->update($update);
        }
    }

    /**
     * 更新交易信息
     *
     * N-84
     *
     * @param int $order_id
     */
    public function updateTransactionByOrder($order_id)
    {
        if (empty($order_id)) {
            return;
        }
        $sql = "SELECT
	bts.id AS bts_id, op.price, op.service_fee_per, op.quantity, pq.amount,o.date_added,bts.last_transaction_time
FROM oc_order AS o
	JOIN oc_order_product AS op ON op.order_id = o.order_id
	JOIN oc_customerpartner_to_product AS ctp ON ctp.product_id = op.product_id
	LEFT JOIN oc_product_quote AS pq ON pq.order_id = o.order_id  AND pq.product_id = op.product_id
	JOIN oc_buyer_to_seller AS bts ON bts.seller_id = ctp.customer_id  AND bts.buyer_id = o.customer_id
WHERE
	o.order_id = {$order_id}";

        $rows = $this->db->query($sql)->rows;
        $temp = [];
        foreach ($rows as $row) {
            $temp[$row['bts_id']]['times'] = 1;
            $temp[$row['bts_id']]['order_time'] = $row['date_added'] > $row['last_transaction_time'] ? $row['date_added'] : $row['last_transaction_time'];
            $temp[$row['bts_id']]['money'] = ($temp[$row['bts_id']]['money'] ?? 0)
                + ($row['price'] + $row['service_fee_per']) * $row['quantity']
                - ($row['amount'] ?: 0);
        }

        foreach ($temp as $bts_id=> $bts) {
            $sql = "UPDATE `oc_buyer_to_seller`
SET `number_of_transaction` = `number_of_transaction` + 1,
`money_of_transaction` = `money_of_transaction` + {$bts['money']},
last_transaction_time = '".$bts['order_time']."'
WHERE `id` = {$bts_id} limit 1";
            $this->db->query($sql);
        }
    }
}
