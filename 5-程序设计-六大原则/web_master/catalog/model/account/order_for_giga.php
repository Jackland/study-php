<?php

/**
 * Class ModelAccountOrderForGiga
 * @property ModelAccountNotification $model_account_notification
 * @property ModelCatalogProduct $model_catalog_product
 */
class ModelAccountOrderForGiga extends Model {
	public function getOrder($order_id) {
		$order_query = $this->db->query("SELECT * FROM `tb_sys_supplier_batch_detail` WHERE po_number = '" . $order_id . "' AND buyer_id = '" . (int)$this->customer->getId() . "' AND order_status_id = '5'");

		if ($order_query->num_rows) {

			return array(
				'order_id'                => $order_query->row['po_number'],
				'sku_code'              => $order_query->row['sku_code'],
				'price'          => $order_query->row['org_price'],
				'quantity'                => $order_query->row['receive_qty'],
				'org_amount'              => $order_query->row['org_amount'],
				'product_name'               => $order_query->row['product_name'],
				'color'             => $order_query->row['color'],
				'length'               => $order_query->row['length'],
				'width'                => $order_query->row['width'],
				'height'               => $order_query->row['height'],
				'weight'                   => $order_query->row['weight'],
				'org_amount'       => $order_query->row['org_amount'],
                'date_added'       => $order_query->row['create_time']
			);
		} else {
			return false;
		}
	}

	public function getOrders($start = 0, $limit = 20) {
		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 1;
		}

		$query = $this->db->query("SELECT o.order_id, o.firstname, o.lastname,CONCAT(cus.nickname,'(',cus.user_number,')') as nickname, os.name as status, o.date_added, o.total, o.currency_code, o.currency_value FROM `" . DB_PREFIX . "order` o LEFT JOIN " . DB_PREFIX . "order_status os ON (o.order_status_id = os.order_status_id) LEFT JOIN " . DB_PREFIX . "customer cus ON o.customer_id = cus.customer_id WHERE o.customer_id = '" . (int)$this->customer->getId() . "' AND o.order_status_id > '0' AND o.store_id = '" . (int)$this->config->get('config_store_id') . "' AND os.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY o.order_id DESC LIMIT " . (int)$start . "," . (int)$limit);

		return $query->rows;
	}

	public function getOrderProduct($order_id, $order_product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '" . (int)$order_product_id . "'");

		return $query->row;
	}

	public function getOrderProducts($order_id) {
		$query = $this->db->query("SELECT * FROM tb_sys_supplier_batch_detail WHERE po_number = '" . $order_id . "'");

		return $query->rows;
	}

	public function getOrderOptions($order_id, $order_product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_option WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '" . (int)$order_product_id . "'");

		return $query->rows;
	}

	public function getOrderVouchers($order_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_voucher` WHERE order_id = '" . (int)$order_id . "'");

		return $query->rows;
	}

	public function getOrderTotals($order_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . (int)$order_id . "' ORDER BY sort_order");

		return $query->rows;
	}

	public function getOrderHistories($order_id) {
		$query = $this->db->query("SELECT distinct create_time as date_added FROM tb_sys_supplier_batch_detail oh WHERE oh.po_number = '" . $order_id . "'");

		return $query->rows;
	}

    public function getPreOrderIds($order_id) {
	    //上一页
        $query = $this->db->query("select distinct po_number from tb_sys_supplier_batch_detail where buyer_id='" . (int)$this->customer->getId() . "' and po_number> '" . $order_id . "' order by po_number limit 1");

        if(isset($query->rows[0])){
            return $query->rows[0];
        }

    }

    public function getNextOrderIds($order_id) {
        //下一页
        $query = $this->db->query("select distinct po_number from tb_sys_supplier_batch_detail where buyer_id='" . (int)$this->customer->getId() . "' and po_number< '" . $order_id . "' order by po_number desc limit 1");

        if(isset($query->rows[0])){
            return $query->rows[0];
        }
    }

	public function getTotalOrders() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "order` o WHERE customer_id = '" . (int)$this->customer->getId() . "' AND o.order_status_id > '0' AND o.store_id = '" . (int)$this->config->get('config_store_id') . "'");

		return $query->row['total'];
	}

    /**
     * [getPurchaseOrderTotal description]
     * @param $param
     * @return int
     */
    public function getPurchaseOrderTotal($param){
        $map = [
            ['buyer_id' ,'=',$this->customer->getId()] ,
            ['order_status_id' ,'=',5] ,
//            ['oco.store_id' ,'=',(int)$this->config->get('config_store_id')] ,
//            ['os.language_id','=',(int)$this->config->get('config_language_id')],
        ];
        if(isset($param['filter_orderDate_from'])){
            $timeList[] = $param['filter_orderDate_from']. ' 00:00:00';
        }else{
            //这里可以写为最初成功时间
            $timeList[] = date('Y-m-d 00:00:00',0);
        }
        if(isset($param['filter_orderDate_to'])){
            $timeList[] = $param['filter_orderDate_to']. ' 23:59:59';
        }else{
            $timeList[] = date('Y-m-d 23:59:59',time());
        }
        if(isset($param['filter_orderId'])){
            $orderId = trim($param['filter_orderId']);
            $map[] = ['po_number','like',"%{$orderId}%"];
        }
        if(isset($param['filter_item_code'])){
            $item_code = trim($param['filter_item_code']);
            $map[] = ['sku_code','like',"%{$item_code}%"];
        }

        $res = $this->orm->table('tb_sys_supplier_batch_detail')->where($map)->whereBetween('create_time',$timeList)->
            distinct(true)->
            select('po_number')->get();

        return count(obj2array($res));

    }

    /**
     * [getPurchaseOrderDetails description]
     * @param $param
     * @param $page
     * @param int $perPage
     * @return array
     */
    public function getPurchaseOrderDetails($param,$page,$perPage = 25){
        $map = [
            ['oco.buyer_id' ,'=',$this->customer->getId()] ,
            ['oco.order_status_id' ,'=',5] ,
        ];
        if(isset($param['filter_orderDate_from'])){
            $timeList[] = $param['filter_orderDate_from'].' 00:00:00';
        }else{
            //这里可以写为最初成功时间
            $timeList[] = date('Y-m-d 00:00:00',0);
        }
        if(isset($param['filter_orderDate_to'])){
            $timeList[] = $param['filter_orderDate_to'].' 23:59:59';
        }else{
            $timeList[] = date('Y-m-d 23:59:59',time());
        }
        if(isset($param['filter_orderId'])){
            $orderId = trim($param['filter_orderId']);
            $map[] = ['oco.po_number','like',"%{$orderId}%"];
        }
        if(isset($param['filter_item_code'])){
            $item_code = trim($param['filter_item_code']);
            $map[] = ['oco.sku_code','like',"%{$item_code}%"];
        }
        if(isset($param['sort_order_date'])){
            $default_column = 'oco.create_time';
            $default_sort = $param['sort_order_date'];

        }else{
            $default_column = 'oco.po_number';
            $default_sort = 'desc';
        }
        $res = $this->orm->table('tb_sys_supplier_batch_detail as oco')
            ->where($map)
            ->whereBetween('oco.create_time',$timeList)
            ->select('oco.po_number as order_id','oco.create_time as date_added')
            ->selectRaw('group_concat(oco.sku_code) as sku')
            ->selectRaw('"Completed" as order_status_name')
            ->selectRaw('"line of credit" as payment_method')
            ->selectRaw('group_concat(oco.receive_qty) as qty')
            ->selectRaw('sum(oco.org_price*oco.receive_qty) as total')
            ->forPage($page,$perPage)
            ->groupBy('oco.po_number')
            ->orderBy($default_column,$default_sort)
            ->get();
        return obj2array($res);

    }

    /**
     * [getPurchaseOrderFilterData description] 获取导出csv数据的
     * @param $param
     * @return array
     */
    public function getPurchaseOrderFilterData($param){
        $map = [
            ['oco.buyer_id' ,'=',$this->customer->getId()] ,
            ['oco.order_status_id' ,'=',5] ,
        ];
        if(isset($param['filter_orderDate_from'])){
            $timeList[] = $param['filter_orderDate_from'].' 00:00:00';
        }else{
            //这里可以写为最初成功时间
            $timeList[] = date('Y-m-d 00:00:00',0);
        }
        if(isset($param['filter_orderDate_to'])){
            $timeList[] = $param['filter_orderDate_to'].' 23:59:59';
        }else{
            $timeList[] = date('Y-m-d 23:59:59',time());
        }
        if(isset($param['filter_orderId'])){
            $orderId = trim($param['filter_orderId']);
            $map[] = ['oco.po_number','like',"%{$orderId}%"];
        }
        if(isset($param['filter_item_code'])){
            $item_code = trim($param['filter_item_code']);
            $map[] = ['oco.sku_code','like',"%{$item_code}%"];
        }
        if(isset($param['sort_order_date'])){
            $default_column = 'oco.create_time';
            $default_sort = $param['sort_order_date'];
        }else{
            $default_column = 'oco.po_number';
            $default_sort = 'desc';
        }
        $res = $this->orm->table('tb_sys_supplier_batch_detail as oco')
            ->where($map)
            ->whereBetween('oco.create_time',$timeList)
            ->select('oco.po_number as Purchase Order ID','oco.supplier_code as Supplier','oco.sku_code as Item Code','oco.product_name as Product Name',
                'oco.receive_qty as Purchase Quantity','oco.org_price as Unit Price','oco.detail_amount as Total Amount','oco.create_time as Purchase Date')
            ->selectRaw('"line of credit" as payment_method')
            ->selectRaw('"0" as transaction_fee')
            ->orderBy('oco.po_number')
            ->orderBy($default_column,$default_sort)
            ->get();
        return obj2array($res);

    }

	public function getTotalOrderProductsByOrderId($order_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");

		return $query->row['total'];
	}

	public function getTotalOrderVouchersByOrderId($order_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "order_voucher` WHERE order_id = '" . (int)$order_id . "'");

		return $query->row['total'];
	}

	//add by xxli
	public function getReviewInfo($order_id,$order_product_id){
	    $sql = "select review_id,author,text,rating,seller_rating,buyer_review_number,seller_review_number from oc_review where order_id = ".(int)$order_id." and order_product_id = ".(int)$order_product_id;
        $query = $this->db->query($sql);
        return $query->row;
	}

	public function getReviewFile($review_id){
        $sql = "select path,file_name from oc_review_file where review_id = ".(int)$review_id;
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getCustomerName($custmer_id){
        $sql = "select screenname from oc_customerpartner_to_customer where customer_id = ".(int)$custmer_id;
        $query = $this->db->query($sql);
        return $query->row;
    }

    public function addReview($product_id, $data) {
        $this->db->query("INSERT INTO " . DB_PREFIX . "review SET author = '" . $this->db->escape($data['name']) . "', customer_id = '" . (int)$this->customer->getId() . "', product_id = '" . (int)$product_id . "', text = '" . $this->db->escape($data['text']) . "', rating = '" . (int)$data['rating'] . "',seller_rating='".(int)$data['seller_rating']."',order_id = '".(int)$data['order_id']."',order_product_id = '".(int)$data['order_product_id']."',seller_id = '".(int)$data['seller_id']."', date_added = NOW()");

        $review_id = $this->db->getLastId();

        $this->load->model('account/notification');
        $this->model_account_notification->productReviewActivity($product_id, $review_id, $data['name']);


        if (in_array('review', (array)$this->config->get('config_mail_alert'))) {
            $this->load->language('mail/review');
            $this->load->model('catalog/product');

            $product_info = $this->model_catalog_product->getProduct($product_id);

            $subject = sprintf($this->language->get('text_subject'), html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));

            $message  = $this->language->get('text_waiting') . "\n";
            $message .= sprintf($this->language->get('text_product'), html_entity_decode($product_info['name'], ENT_QUOTES, 'UTF-8')) . "\n";
            $message .= sprintf($this->language->get('text_reviewer'), html_entity_decode($data['name'], ENT_QUOTES, 'UTF-8')) . "\n";
            $message .= sprintf($this->language->get('text_rating'), $data['rating']) . "\n";
            $message .= $this->language->get('text_review') . "\n";
            $message .= html_entity_decode($data['text'], ENT_QUOTES, 'UTF-8') . "\n\n";

//            $mail = new Mail($this->config->get('config_mail_engine'));
//            $mail->parameter = $this->config->get('config_mail_parameter');
//            $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
//            $mail->smtp_username = $this->config->get('config_mail_smtp_username');
//            $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
//            $mail->smtp_port = $this->config->get('config_mail_smtp_port');
//            $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');
//
//            $mail->setTo($this->config->get('config_email'));
//            $mail->setFrom($this->config->get('config_email'));
//            $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
//            $mail->setSubject($subject);
//            $mail->setText($message);
//            $mail->send();

            // Send to additional alert emails
//            $emails = explode(',', $this->config->get('config_mail_alert_email'));
//
//            foreach ($emails as $email) {
//                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
//                    $mail->setTo($email);
//                    $mail->send();
//                }
//            }
        }

        return $review_id;
    }

    public function editReview($product_id,$review_id,$data){
        $this->db->query("UPDATE " . DB_PREFIX . "review SET author = '" . $this->db->escape($data['name']) . "', customer_id = '" . (int)$this->customer->getId() . "', text = '" . $this->db->escape($data['text']) . "', rating = '" . (int)$data['rating'] . "',seller_rating='".(int)$data['seller_rating']."', date_modified = NOW(),buyer_review_number = buyer_review_number+1 where review_id = '".(int)$review_id."'");


        $this->load->model('account/notification');
        $this->model_account_notification->productReviewActivity($product_id, $review_id, $data['name']);


        if (in_array('review', (array)$this->config->get('config_mail_alert'))) {
            $this->load->language('mail/review');
            $this->load->model('catalog/product');

            $product_info = $this->model_catalog_product->getProduct($product_id);

            $subject = sprintf($this->language->get('text_subject'), html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));

            $message  = $this->language->get('text_waiting') . "\n";
            $message .= sprintf($this->language->get('text_product'), html_entity_decode($product_info['name'], ENT_QUOTES, 'UTF-8')) . "\n";
            $message .= sprintf($this->language->get('text_reviewer'), html_entity_decode($data['name'], ENT_QUOTES, 'UTF-8')) . "\n";
            $message .= sprintf($this->language->get('text_rating'), $data['rating']) . "\n";
            $message .= $this->language->get('text_review') . "\n";
            $message .= html_entity_decode($data['text'], ENT_QUOTES, 'UTF-8') . "\n\n";

//            $mail = new Mail($this->config->get('config_mail_engine'));
//            $mail->parameter = $this->config->get('config_mail_parameter');
//            $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
//            $mail->smtp_username = $this->config->get('config_mail_smtp_username');
//            $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
//            $mail->smtp_port = $this->config->get('config_mail_smtp_port');
//            $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');
//
//            $mail->setTo($this->config->get('config_email'));
//            $mail->setFrom($this->config->get('config_email'));
//            $mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
//            $mail->setSubject($subject);
//            $mail->setText($message);
//            $mail->send();

            // Send to additional alert emails
//            $emails = explode(',', $this->config->get('config_mail_alert_email'));

//            foreach ($emails as $email) {
//                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
//                    $mail->setTo($email);
//                    $mail->send();
//                }
//            }
        }

    }

    public function addReviewFile($review_id,$filePath,$fileName,$customerId){
	   $sql = "insert into oc_review_file set review_id = '".(int)$review_id."',path='".$this->db->escape($filePath)."',file_name='".$this->db->escape($fileName)."',create_date=NOW(),create_person='".$this->db->escape($customerId)."'";
        $this->db->query($sql);
	}

	public function deleteFiles($filePath){
	    $sql = "delete from oc_review_file where path = '".$this->db->escape($filePath)."'";
        $this->db->query($sql);
    }

    //end xxli

    /**
     * 获取采购订单对应的RMA订单
     * @param int $order_id
     * @param int $product_id
     * @return array
     */
    public function getRmaHistories($order_id,$product_id){
        $sql = "SELECT
                    ro.id,
                    rr.reason,
                    ifnull(ro.update_time,ro.create_time) as create_time,
                    ro.rma_order_id,
                    case when rop.rma_type = 1 then 'Reshipment'
                    when rop.rma_type = 2 then 'Refund'
                    else 'Reshipment+Refund'
                    end as rma_type,
                    case when ro.order_type = 1 then 'Sales Order RMA'
                        when ro.order_type = 2 then 'Purchase Order RMA'
                        end as order_type,
					case when ro.cancel_rma =1 THEN 'Canceled'
					else rs.`name` end as name
                FROM
                    oc_yzc_rma_order ro
                LEFT JOIN oc_yzc_rma_order_product rop ON rop.rma_id = ro.id
                LEFT JOIN oc_yzc_rma_status rs on rs.status_id = ro.seller_status
                LEFT JOIN  oc_yzc_rma_reason rr on rr.reason_id = rop.reason_id
                WHERE
                    ro.order_id = ".$order_id." and rop.product_id=".$product_id." order by ro.create_time desc";
        return $this->db->query($sql)->rows;
    }

    public function  getOrderDetail($order_id,$product_id){
        $sql="SELECT
                    op.sku,oop.`name`,oo.order_id,oo.date_added,oop.quantity,oop.product_id,oo.customer_id as buyer_id,ctp.customer_id as seller_id,oop.order_product_id
                FROM
                    oc_order oo
                LEFT JOIN oc_order_product oop ON oop.order_id = oo.order_id
                LEFT JOIN oc_product op ON op.product_id = oop.product_id
                LEFT JOIN oc_customerpartner_to_product ctp ON ctp.product_id=op.product_id";
        $sql .=" WHERE oo.order_id=".$order_id." AND oop.product_id=".$product_id;
        return $this->db->query($sql)->row;
    }

    /**
     * 获取未绑定订单信息
     * @param int $order_id
     * @param int $product_id
     * @return array
     */
    public function  getNoBindingOrderInfo($order_id,$product_id,$rmaId =null){
        $sql = "SELECT
                    ifnull(t.qty,0) as bindingQty,
                    (oop.quantity - ifnull(t.qty,0)-IFNULL(t2.qty,0)) AS nobindingQty,
                    if(opq.price is null,oop.price,opq.price) as price,
                  round((oop.quantity - ifnull(t.qty,0)-IFNULL(t2.qty,0)) * (
                        oop.service_fee / oop.quantity
                    ),2) as service_fee,
                    round((oop.quantity - ifnull(t.qty,0)-IFNULL(t2.qty,0)) * (
                        oop.poundage / oop.quantity
                    ),2) as poundage,
                    oc.country_id
                FROM
                    oc_order oo
                LEFT JOIN oc_order_product oop ON oop.order_id = oo.order_id
                LEFT JOIN (
                    SELECT
                        sum(soa.qty) AS qty,
                        soa.order_id,
                        soa.order_product_id
                    FROM
                        tb_sys_order_associated soa
                   where soa.order_id={$order_id} and soa.product_id={$product_id}
                ) t ON t.order_id = oop.order_id
                AND t.order_product_id = oop.order_product_id
							LEFT JOIN (
                    SELECT
                        sum(rop.quantity) AS qty,
                        ro.order_id,
                        rop.order_product_id
                    FROM
                        oc_yzc_rma_order ro
										LEFT JOIN oc_yzc_rma_order_product rop on rop.rma_id=ro.id
										where ro.cancel_rma = 0 and rop.status_refund <>2 and ro.order_type=2";
                    if($rmaId !=null){
                        $sql .=" and ro.id !=".$rmaId;
                    }
                  $sql.= " GROUP BY
                        ro.order_id,
                        rop.order_product_id
                ) t2 ON t2.order_id = oop.order_id
                AND t2.order_product_id = oop.order_product_id
				LEFT JOIN oc_product_quote opq on opq.order_id = oop.order_id and opq.product_id=oop.product_id
				LEFT JOIN  oc_customerpartner_to_product cto on cto.product_id = oop.product_id
				LEFT JOIN oc_customer oc on oc.customer_id = cto.customer_id
                WHERE oop.order_id=".$order_id." and oop.product_id = ".$product_id;
        return $this->db->query($sql)->row;
    }

    /**
     * @param int $order_id
     * @param int $product_id
     * @return array
     */
    public function getBindingSalesOrder($order_id,$product_id){
        $sql = 'SELECT
                    cso.order_id,cso.create_time,soa.qty
                FROM
                    tb_sys_order_associated soa
                LEFT JOIN tb_sys_customer_sales_order cso ON cso.id = soa.sales_order_id
                LEFT JOIN tb_sys_customer_sales_order_line csol on csol.id = soa.sales_order_line_id
                WHERE soa.order_id ='.$order_id." and soa.product_id = ".$product_id;
        return $this->db->query($sql)->rows;
    }

    public function  getRmaReason(){
        $sql ="select * from oc_yzc_rma_reason where rma_type = 2 ";
        return $this->db->query($sql)->rows;
    }

    /**
     * 根据 order_id 和 seller_id 查询该订单对于该 seller_id 的议价总折扣
     *
     * @param int $order_id
     * @param int $seller_id
     * @return int mixed
     */
    public function getSellerQuoteAmount($order_id, $seller_id)
    {
        return $this->orm->table('oc_product_quote as pq')
            ->where('order_id', $order_id)
            ->whereExists(function ($query) use ($seller_id) {
                $query->select('ctp.product_id')
                    ->from('oc_customerpartner_to_product as ctp')
                    ->where('ctp.customer_id', $seller_id)
                    ->whereRaw('ctp.product_id = pq.product_id');
            })
            ->sum('pq.amount');
    }

    /**
     * 修改rma时的rma的状态
     * @author xxl
     * @param $rmaId
     * @return mixed
     */
    public function getSellerStatusByRmaId($rmaId){
        $result = $this->orm->table('oc_yzc_rma_order as yro')
            ->where('yro.id','=',$rmaId)
            ->select('yro.seller_status')
            ->first();
        return $result->seller_status;

    }

    /**
     * @param int $order_id
     * @param int $seller_id
     * @param bool $is_service_fee
     * @return float
     */
    public function getSellerQuoteAmountServiceFee($order_id, $seller_id, $is_service_fee = false)
    {
        $objs = $this->orm->table('oc_product_quote as pq')
            ->where('order_id', $order_id)
            ->whereExists(function ($query) use ($seller_id) {
                $query->select('ctp.product_id')
                    ->from('oc_customerpartner_to_product as ctp')
                    ->where('ctp.customer_id', $seller_id)
                    ->whereRaw('ctp.product_id = pq.product_id');
            })
            ->select([
                'pq.amount', 'pq.quantity', 'pq.amount_price_per', 'pq.amount_service_fee_per'
            ])
            ->get();
        $amount = 0;
        if ($is_service_fee) {
            foreach ($objs as $obj) {
                $amount = bcadd(bcmul($obj->amount_service_fee_per, $obj->quantity, 2), $amount, 2);
            }
        } else {
            foreach ($objs as $obj) {
                $amount = bcadd(bcmul($obj->amount_price_per, $obj->quantity, 2), $amount, 2);
            }
        }
        return $amount;
    }
}
