<?php

use App\Enums\Common\YesNoEnum;
use App\Enums\Product\ProductStatus;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Product\ProductType;
use App\Enums\Order\OcOrderStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\YzcRmaOrder\RmaType;
use App\Models\Customer\CustomerExts;
use App\Models\Order\Order;
use App\Models\Order\OrderProduct;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

/**
 * Class ModelAccountOrder
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountNotification $model_account_notification
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCheckoutCart $model_checkout_cart
 * @property ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 *
 */
class ModelAccountOrder extends Model {
	public function getOrder($order_id, $buyer_id=null) {
        if (is_null($buyer_id)) {
            $buyer_id = (int)$this->customer->getId();
        }
		$order_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "' AND customer_id = '" . $buyer_id ."'");

		if ($order_query->num_rows) {
			$country_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE country_id = '" . (int)$order_query->row['payment_country_id'] . "'");

			if ($country_query->num_rows) {
				$payment_iso_code_2 = $country_query->row['iso_code_2'];
				$payment_iso_code_3 = $country_query->row['iso_code_3'];
			} else {
				$payment_iso_code_2 = '';
				$payment_iso_code_3 = '';
			}

			$zone_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE zone_id = '" . (int)$order_query->row['payment_zone_id'] . "'");

			if ($zone_query->num_rows) {
				$payment_zone_code = $zone_query->row['code'];
			} else {
				$payment_zone_code = '';
			}

			$country_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE country_id = '" . (int)$order_query->row['shipping_country_id'] . "'");

			if ($country_query->num_rows) {
				$shipping_iso_code_2 = $country_query->row['iso_code_2'];
				$shipping_iso_code_3 = $country_query->row['iso_code_3'];
			} else {
				$shipping_iso_code_2 = '';
				$shipping_iso_code_3 = '';
			}

			$zone_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone` WHERE zone_id = '" . (int)$order_query->row['shipping_zone_id'] . "'");

			if ($zone_query->num_rows) {
				$shipping_zone_code = $zone_query->row['code'];
			} else {
				$shipping_zone_code = '';
			}

			return array(
				'order_id'                => $order_query->row['order_id'],
				'delivery_type'           => $order_query->row['delivery_type'],
				'invoice_no'              => $order_query->row['invoice_no'],
				'invoice_prefix'          => $order_query->row['invoice_prefix'],
				'store_id'                => $order_query->row['store_id'],
				'store_name'              => $order_query->row['store_name'],
				'store_url'               => $order_query->row['store_url'],
				'customer_id'             => $order_query->row['customer_id'],
				'firstname'               => $order_query->row['firstname'],
				'lastname'                => $order_query->row['lastname'],
				'telephone'               => $order_query->row['telephone'],
				'email'                   => $order_query->row['email'],
				'payment_firstname'       => $order_query->row['payment_firstname'],
				'payment_lastname'        => $order_query->row['payment_lastname'],
				'payment_company'         => $order_query->row['payment_company'],
				'payment_address_1'       => $order_query->row['payment_address_1'],
				'payment_address_2'       => $order_query->row['payment_address_2'],
				'payment_postcode'        => $order_query->row['payment_postcode'],
				'payment_city'            => $order_query->row['payment_city'],
				'payment_zone_id'         => $order_query->row['payment_zone_id'],
				'payment_zone'            => $order_query->row['payment_zone'],
				'payment_zone_code'       => $payment_zone_code,
				'payment_country_id'      => $order_query->row['payment_country_id'],
				'payment_country'         => $order_query->row['payment_country'],
				'payment_iso_code_2'      => $payment_iso_code_2,
				'payment_iso_code_3'      => $payment_iso_code_3,
				'payment_address_format'  => $order_query->row['payment_address_format'],
				'payment_method'          => $order_query->row['payment_method'],
				'shipping_firstname'      => $order_query->row['shipping_firstname'],
				'shipping_lastname'       => $order_query->row['shipping_lastname'],
				'shipping_company'        => $order_query->row['shipping_company'],
				'shipping_address_1'      => $order_query->row['shipping_address_1'],
				'shipping_address_2'      => $order_query->row['shipping_address_2'],
				'shipping_postcode'       => $order_query->row['shipping_postcode'],
				'shipping_city'           => $order_query->row['shipping_city'],
				'shipping_zone_id'        => $order_query->row['shipping_zone_id'],
				'shipping_zone'           => $order_query->row['shipping_zone'],
				'shipping_zone_code'      => $shipping_zone_code,
				'shipping_country_id'     => $order_query->row['shipping_country_id'],
				'shipping_country'        => $order_query->row['shipping_country'],
				'shipping_iso_code_2'     => $shipping_iso_code_2,
				'shipping_iso_code_3'     => $shipping_iso_code_3,
				'shipping_address_format' => $order_query->row['shipping_address_format'],
				'shipping_method'         => $order_query->row['shipping_method'],
				'comment'                 => $order_query->row['comment'],
				'total'                   => $order_query->row['total'],
				'order_status_id'         => $order_query->row['order_status_id'],
				'language_id'             => $order_query->row['language_id'],
				'currency_id'             => $order_query->row['currency_id'],
				'currency_code'           => $order_query->row['currency_code'],
				'currency_value'          => $order_query->row['currency_value'],
				'date_modified'           => $order_query->row['date_modified'],
				'date_added'              => $order_query->row['date_added'],
				'ip'                      => $order_query->row['ip']
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
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");

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
		$query = $this->db->query("SELECT date_added, os.name AS status, oh.comment, oh.notify FROM " . DB_PREFIX . "order_history oh LEFT JOIN " . DB_PREFIX . "order_status os ON oh.order_status_id = os.order_status_id WHERE oh.order_id = '" . (int)$order_id . "' AND os.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY oh.date_added");

		return $query->rows;
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
        $query = $this->orm->table(DB_PREFIX.'order as oco');
        $map = [
            ['oco.customer_id' ,'=',$this->customer->getId()] ,
            ['oco.order_status_id' ,'=',OcOrderStatus::COMPLETED] ,
            ['oco.store_id' ,'=',(int)$this->config->get('config_store_id')] ,
            ['os.language_id','=',(int)$this->config->get('config_language_id')],
        ];
        if(isset($param['filter_orderDate_from'])){
            $timeList[] = $param['filter_orderDate_from'];
        }else{
            //这里可以写为最初成功时间
            $timeList[] = date('Y-m-d 00:00:00',0);
        }
        if(isset($param['filter_orderDate_to'])){
            $timeList[] = $param['filter_orderDate_to'];
        }else{
            $timeList[] = date('Y-m-d 23:59:59',time());
        }
        if(isset($param['filter_orderId'])){
            $orderId = trim($param['filter_orderId']);
            $map[] = ['oco.order_id','like',"%{$orderId}%"];
        }
        if(isset($param['filter_item_code'])){
            $item_code = trim($param['filter_item_code']);
            $map[] = ['op.sku','like',"%{$item_code}%"];
        }
        if (!isset($param['filter_include_all_refund'])) {
            $res = $this->getAllRefundOrderIds($this->customer->getId());
            $res = count($res) > 0 ? $res : [0];
            $query->whereNotIn('oco.order_id', $res);
        }
        if (isset($param['filter_order_status'])) {
            $map[] = ['oco.order_status_id','=',$param['filter_order_status']];
        }
        $query->where($map)
            ->whereBetween('oco.date_added', $timeList)
            ->leftJoin(DB_PREFIX . 'order_status as os', 'os.order_status_id', '=', 'oco.order_status_id')
            ->leftJoin(DB_PREFIX . 'order_product as p', 'oco.order_id', '=', 'p.order_id')
            ->leftJoin(DB_PREFIX . 'product as op', 'op.product_id', '=', 'p.product_id')
            ->groupBy('oco.order_id')
            ->orderBy('oco.order_id', 'desc')
            ->select('oco.order_id');

        return $query->get()->count();
    }

    /**
     * [getPurchaseOrderDetails description]
     * @param $param
     * @param $page
     * @param int $perPage
     * @return array
     */
    public function getPurchaseOrderDetails($param,$page,$perPage = 25){
        $query = $this->orm->table(DB_PREFIX.'order as oco');
        $map = [
            ['oco.customer_id' ,'=',$this->customer->getId()] ,
            ['oco.order_status_id' ,'=',OcOrderStatus::COMPLETED] ,
            ['oco.store_id' ,'=',(int)$this->config->get('config_store_id')] ,
            ['os.language_id','=',(int)$this->config->get('config_language_id')],
        ];
        if(isset($param['filter_orderDate_from'])){
            $timeList[] = $param['filter_orderDate_from'];
        }else{
            //这里可以写为最初成功时间
            $timeList[] = date('Y-m-d 00:00:00',0);
        }
        if(isset($param['filter_orderDate_to'])){
            $timeList[] = $param['filter_orderDate_to'];
        }else{
            $timeList[] = date('Y-m-d 23:59:59',time());
        }
        if(isset($param['filter_orderId'])){
            $orderId = trim($param['filter_orderId']);
            $map[] = ['oco.order_id','like',"%{$orderId}%"];
        }
        if(isset($param['filter_item_code'])){
            $item_code = trim($param['filter_item_code']);
            $map[] = ['op.sku','like',"%{$item_code}%"];
        }
        if (!isset($param['filter_include_all_refund'])) {
            $res = $this->getAllRefundOrderIds($this->customer->getId());
            $res = count($res) > 0 ? $res : [0];
            $query->whereNotIn('oco.order_id', $res);
        }
        if (isset($param['filter_order_status'])) {
            $map[] = ['oco.order_status_id','=',$param['filter_order_status']];
        }
        if(isset($param['sort_order_date'])){
            $default_column = 'oco.date_added';
            $default_sort = $param['sort_order_date'];

        }else{
            $default_column = 'oco.order_id';
            $default_sort = 'desc';
        }

        $res = $query->where($map)
            ->whereBetween('oco.date_added', $timeList)
            ->leftJoin(DB_PREFIX . 'order_status as os', 'os.order_status_id', '=', 'oco.order_status_id')
            ->leftJoin(DB_PREFIX . 'order_product as p', 'oco.order_id', '=', 'p.order_id')
            ->leftJoin(DB_PREFIX . 'product as op', 'op.product_id', '=', 'p.product_id')
            ->forPage($page, $perPage)
            ->groupBy('oco.order_id')
            ->orderBy($default_column, $default_sort)
            ->select('oco.order_id','oco.delivery_type', 'oco.date_added', 'oco.total', 'oco.currency_code', 'oco.currency_value', 'os.name as order_status_name', 'oco.payment_method')->selectRaw('group_concat(op.sku) as sku,group_concat(p.quantity) as qty,group_concat(p.product_id) as product_id')
            ->get();

        return obj2array($res);
    }

    /**
     * [getPurchaseOrderProductInfo description]
     * @param int $order_id oc_order表的order_id
     * @return array
     */
    public function getPurchaseOrderProductInfo($order_id)
    {
        $ret = $this->orm->table(DB_PREFIX . 'order_product as oop')
            ->leftJoin(DB_PREFIX . 'product as op', 'op.product_id', '=', 'oop.product_id')
            ->where('oop.order_id',$order_id)
            ->selectRaw('oop.product_id,oop.type_id,oop.agreement_id,op.sku,oop.quantity as qty')
            ->get()
            ->map(function($v){
                return (array)$v;
            })
            ->toArray();
        return $ret;
    }

    /**
     * [getFutureMarginInfo description]
     * @param int $agreement_id
     * @return array
     */
    public function getFutureMarginInfo($agreement_id)
    {
        $ret = $this->orm->table(DB_PREFIX.'futures_margin_agreement as a')
            ->leftJoin(DB_PREFIX . 'futures_margin_process as p', 'a.id', '=', 'p.agreement_id')
            ->where('a.id',$agreement_id)
            ->selectRaw('a.contract_id,a.agreement_no,a.id,p.advance_product_id,p.advance_order_id')
            ->get()
            ->map(function($v){
                return (array)$v;
            })
            ->toArray();
        return current($ret);
    }

    public function getMarginInfo($agreement_id)
    {
        $ret = $this->orm->table('tb_sys_margin_agreement as m')
            ->where('m.id',$agreement_id)
            ->selectRaw('m.agreement_id,m.id')
            ->get()
            ->map(function($v){
                return (array)$v;
            })
            ->toArray();
        return current($ret);
    }

    public function getRebateAgreementCode($agreement_id)
    {
       return db('oc_rebate_agreement as m')
            ->where('m.id',$agreement_id)
            ->value('agreement_code');

    }

    /**
     * 获取全部退款order id
     * @param int $customer_id 用于前台用户 所以为buyer_id
     * @param bool $returnArray
     * @return array|string
     * 参考
     * @see ModelAccountCustomerpartner::getAllRefundOrderId()
     */
    public function getAllRefundOrderIds(int $customer_id, bool $returnArray = true)
    {
        static $retData = [];
        $key = $customer_id . ($returnArray ? '1' : '0');
        if (isset($retData[$key])) return $retData[$key];
        $distinctRmaOrder = $this->orm
            ->table('oc_yzc_rma_order as ro')
            ->distinct()
            ->select('ro.order_id')
            ->where('ro.buyer_id', $customer_id);
        $soaQuery = $this->orm
            ->table('tb_sys_order_associated AS soa')
            ->select(['soa.sales_order_id', 'soa.order_id', 'soa.product_id'])
            ->addSelect(new Expression('sum(soa.qty) AS qty'))
            ->where('soa.buyer_id', $customer_id)
            ->whereIn('soa.order_id', $distinctRmaOrder)
            ->groupBy(['soa.sales_order_id', 'soa.order_id', 'soa.product_id']);
        // 采购单RMA
        $mainQuery1 = $this->orm
            ->table('oc_yzc_rma_order as ro')
            ->select(['ro.order_id', 'ro.id AS rma_id'])
            ->addSelect(new Expression('sum(rop.quantity) AS qty'))
            ->leftJoin('oc_yzc_rma_order_product AS rop', ['ro.id' => 'rop.rma_id'])
            ->groupBy('ro.order_id')
            ->where([
                'ro.buyer_id' => $customer_id,
                'ro.seller_status' => 2,
                'ro.cancel_rma' => 0,
                'rop.status_refund' => 1,
                'ro.order_type' => RmaType::PURCHASE_ORDER
            ]);
        // 销售单RMA
        $mainQuery2Main = db('oc_yzc_rma_order as ro')
            ->select(['cso.id as sales_order_id', 'ro.order_id', 'rop.product_id', 'rop.rma_id'])
            ->leftJoin('oc_yzc_rma_order_product AS rop', ['ro.id' => 'rop.rma_id'])
            ->leftJoin('tb_sys_customer_sales_order as cso', ['cso.order_id' => 'ro.from_customer_order_id'])
            ->where([
                'ro.buyer_id' => $customer_id,
                'ro.seller_status' => 2,
                'ro.cancel_rma' => 0,
                'rop.status_refund' => 1,
                'cso.order_status' => CustomerSalesOrderStatus::CANCELED,
                'ro.order_type' => RmaType::SALES_ORDER,
            ])
            ->groupBy(['cso.id', 'ro.order_id', 'rop.product_id']);
        $mainQuery2 = db(new Expression('(' . get_complete_sql($mainQuery2Main) . ') as mq'))
            ->select(['mq.order_id', 'mq.rma_id'])
            ->addSelect(new Expression('sum(oso.qty) AS qty'))
            ->leftJoin(
                new Expression('(' . get_complete_sql($soaQuery) . ') as oso'),
                function (JoinClause $j) {
                    $j->on('mq.order_id', '=', 'oso.order_id');
                    $j->on('mq.sales_order_id', '=', 'oso.sales_order_id');
                    $j->on('mq.product_id', '=', 'oso.product_id');
                }
            )
            ->groupBy(['mq.order_id']);
        $mainQuery = $mainQuery1->union($mainQuery2);
        $mainQuery = $this->orm
            ->table(new Expression('(' . get_complete_sql($mainQuery) . ') as main_o'))
            ->select(['main_o.order_id'])
            ->addSelect(new Expression('sum( main_o.qty ) AS qty'))
            ->groupBy(['main_o.order_id']);
        $subQuery = $this->orm
            ->table('oc_customerpartner_to_order AS cto')
            ->select('cto.order_id')
            ->addSelect(new Expression('sum( cto.quantity ) AS qty'))
            ->whereIn('cto.order_id', $distinctRmaOrder)
            ->groupBy('cto.order_id');
        $query = $this->orm
            ->table(new Expression('(' . get_complete_sql($mainQuery) . ') as rma_o'))
            ->select('rma_o.order_id')
            ->leftJoin(
                new Expression('(' . get_complete_sql($subQuery) . ') as or_o'),
                ['rma_o.order_id' => 'or_o.order_id']
            )
            ->whereRaw('rma_o.qty = or_o.qty');
        if ($returnArray) {
            $ret = $query->get()->pluck('order_id')->toArray();
        } else {
            $ret = get_complete_sql($query);
        }
        $retData[$key] = $ret;

        return $ret;
    }

    /**
     * [getPurchaseOrderFilterData description] 获取导出csv数据的
     * @param array $param
     * @param int $customer_id
     * @param int $country_id
     * @return array
     * @throws Exception
     */
    public function getPurchaseOrderFilterData(array $param, int $customer_id, int $country_id)
    {
        load()->model('account/customerpartner');
        $query = db(DB_PREFIX . 'order as oco');
        $map = [
            ['oco.customer_id', '=', $customer_id],
        ];
        if (isset($param['filter_orderDate_from']) && $param['filter_orderDate_from']) {
            $timeList[] = $param['filter_orderDate_from'] . ' 00:00:00';
        } else {
            //这里可以写为最初成功时间
            $timeList[] = '2018-01-01 00:00:00';
        }
        if (isset($param['filter_orderDate_to']) && $param['filter_orderDate_to']) {
            $timeList[] = $param['filter_orderDate_to'] . ' 23:59:59';
        } else {
            $timeList[] = date('Y-m-d 23:59:59', time());
        }
        // 开始时间和结束时间至少有一个的时候才加上时间查询，否则没必要再根据这个添加查询。
        if (isset($param['filter_orderDate_from']) || isset($param['filter_orderDate_to'])) {
            $query->whereBetween('oco.date_added', $timeList);
        }
        if (isset($param['filter_PurchaseOrderId']) && !empty($param['filter_PurchaseOrderId'])) {
            $orderId = trim($param['filter_PurchaseOrderId']);
            $map[] = ['oco.order_id', 'like', "%{$orderId}%"];
        }
        if (isset($param['filter_item_code']) && !empty($param['filter_item_code'])) {
            /**
             * 此处重复连 oc_order_product、oc_product 两次 是为了符合条件的order的查找操作,
             * 不是为了查询出和这个表中的字段
             *
             */
            //            $query->leftJoin('oc_order_product as op1', 'op1.order_id', '=', 'oco.order_id')
            //                ->leftJoin('oc_product as p1', 'p1.product_id', '=', 'op1.product_id');
            $item_code = trim($param['filter_item_code']);
            $map[] = ['op.sku', 'like', "%{$item_code}%"];
        }
        if (!isset($param['filter_include_returns'])) {
            $res = $this->getAllRefundOrderIds($this->customer->getId());
            $res = count($res) > 0 ? $res : [0];
            $query->whereNotIn('oco.order_id', $res);
        }

        //关联销售单
        if (isset($param['filter_associatedOrder']) && in_array($param['filter_associatedOrder'], [1, 2, 3])) {
            $res_2 = $this->getAllAssociatedOrder($this->customer->getId(), $param['filter_associatedOrder']);
            $res_2 = count($res_2) > 0 ? $res_2 : [0];
            $query->whereIn('oco.order_id', $res_2);
        }

        if (isset($param['filter_orderStatus']) && $param['filter_orderStatus'] != -1) {
            $map[] = ['oco.order_status_id', '=', $param['filter_orderStatus']];
        }
        $query->where($map)
            ->leftJoin(DB_PREFIX . 'order_status as os', 'os.order_status_id', '=', 'oco.order_status_id')
            ->leftJoin(DB_PREFIX . 'order_product as p', 'oco.order_id', '=', 'p.order_id')
            ->leftJoin(DB_PREFIX . 'product_description as pd', 'pd.product_id', '=', 'p.product_id')
            ->leftJoin(DB_PREFIX . 'product as op', 'op.product_id', '=', 'p.product_id')
            ->leftJoin('tb_sys_order_associated as a', function ($join) {
                $join->on('oco.order_id', '=', 'a.order_id')->on('p.product_id', '=', 'a.product_id');
            })
            //店铺
            ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'a.sales_order_id')
            ->leftJoin(DB_PREFIX . 'product_quote as pq', function ($join) {
                $join->on('pq.order_id', '=', 'oco.order_id')->on('pq.product_id', '=', 'p.product_id');
            })
            //精细化价格
            ->leftJoin(DB_PREFIX . 'customerpartner_to_product as tp', function ($join) {
                $join->on('tp.product_id', '=', 'p.product_id');
            })
            ->leftJoin(DB_PREFIX . 'customerpartner_to_customer as c', 'c.customer_id', '=', 'tp.customer_id')
            //店铺
            ->leftJoin(DB_PREFIX . 'customerpartner_to_order as cto', function ($join) {
                $join->on('cto.order_id', '=', 'oco.order_id')->on('cto.product_id', '=', 'p.product_id');
            })
            ->groupBy('oco.order_id', 'op.product_id')
            ->orderBy('oco.order_id', 'asc')
            ->orderBy('p.order_product_id', 'asc')
            ->select(
                'oco.order_id as purchase_order_id',
                'oco.date_added',
                'oco.total',
                'oco.currency_code',
                'oco.currency_value',
                'os.name as order_status_name',
                'oco.payment_method',
                'c.screenname',
                'op.sku as item_code',
                'pd.name as product_name',
                'p.order_product_id',
                'p.quantity',
                'p.price as unit_price',
                'p.service_fee',
                'p.service_fee_per',
                'p.poundage',
                'pq.price as pq_price',
                'pq.amount_price_per',
                'pq.amount_service_fee_per',
                'pq.amount',
                'p.product_id', 'p.freight_per',
                'p.package_fee',
                'p.service_fee_per',
                'p.freight_difference_per',
                'cto.customer_id AS seller_id',
                'p.type_id',
                'p.agreement_id',
                'p.product_id',
                'p.coupon_amount',
                'p.campaign_amount'
            )
            ->selectRaw('group_concat(o.order_id) as order_id')
            ->selectRaw("IF(p.discount IS NULL, '', 100-p.discount) AS discountShow");

        return $query
            ->get()
            ->map(function ($item) use ($country_id) {
                $item = (array)$item;
                if ($country_id == JAPAN_COUNTRY_ID) {
                    $item['poundage'] = round($item['poundage']);
                }
                //获取已完成的rma退款的 可能存在部分rma
                $item['rma_list'] = $this->model_account_customerpartner->getSellerAgreeRmaOrderInfo(
                    $item['purchase_order_id'],
                    $item['seller_id'],
                    $item['order_product_id']
                );
                // 获取采购订单的order type
                $item['order_type'] = $this->model_account_customerpartner->getPurchaseOrderType(
                    $item['type_id'],
                    $item['agreement_id'],
                    $item['product_id']
                    );
                return (array)$item;
            })
            ->toArray();

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
     * @param int $order_id oc_order表的order_id
     * @param int $product_id
     * @return array
     */
    public function getRmaHistories($order_id,$product_id){
        $sql = "SELECT
                    ro.id,
                    rr.reason,
                    ro.create_time,
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
                    op.sku,oop.`name`,oo.order_id,oo.date_added,oop.quantity,oop.product_id,oo.customer_id as buyer_id,ctp.customer_id as seller_id,oop.order_product_id,oop.agreement_id,oop.type_id
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
     * @param int $order_id oc_order表的order_id
     * @param int $product_id
     * @param int|null $rmaId
     * @return array
     */
    public function  getNoBindingOrderInfo($order_id,$product_id,$rmaId =null){
        $sql = "SELECT
                    ifnull(t.qty,0) as bindingQty,
                    (oop.quantity - ifnull(t.qty,0)-IFNULL(t2.qty,0)) AS nobindingQty,
                    if(opq.price is null,oop.price,opq.price) as price,
                  round((oop.quantity - ifnull(t.qty,0)-IFNULL(t2.qty,0)) * (
                      oop.service_fee_per
                    ),2) as service_fee,
                    round((oop.quantity - ifnull(t.qty,0)-IFNULL(t2.qty,0)) * (
                        oop.poundage / oop.quantity
                    ),2) as poundage,
                    oc.country_id,
                    oop.freight_per,
                    oop.package_fee,
                    oop.service_fee_per,
                    oop.quantity as allQty,
                    oop.coupon_amount,
                    oop.campaign_amount,
                    ifnull(opq.amount_price_per,0) as amount_price_per,
                    ifnull(opq.amount_service_fee_per,0) as amount_service_fee_per
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
                        WHERE soa.order_id={$order_id} and soa.product_id={$product_id}
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
     * @param int $order_id oc_order表的order_id
     * @param int $product_id
     * @return array
     */
    public function getBindingSalesOrder($order_id,$product_id){
        $sql = 'SELECT
                    cso.order_id,cso.create_time,soa.qty,soa.coupon_amount,soa.campaign_amount
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


    public function getSellerQuoteServiceAmount($order_id, $seller_id)
    {
        return $this->orm->table('oc_product_quote as pq')
            ->where('order_id', $order_id)
            ->whereExists(function ($query) use ($seller_id) {
                $query->select('ctp.product_id')
                    ->from('oc_customerpartner_to_product as ctp')
                    ->where('ctp.customer_id', $seller_id)
                    ->whereRaw('ctp.product_id = pq.product_id');
            })
            ->sum('pq.amount_service_fee_per');
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
     * @param int $order_id oc_order表的order_id
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

    /**
     * 欧洲未绑定的采购订单信息
     * @param int $order_id oc_order表的order_id
     * @param int $product_id
     * @param int|null $rmaId
     * @return array
     */
    public function getNoBindingOrderInfoEurope($order_id,$product_id,$rmaId =null){
        $europe_freight_product_list = json_decode($this->config->get('europe_freight_product_id'));
        $product_str = implode(',',$europe_freight_product_list);
        $sql = "SELECT
                   ifnull(t.qty, 0) AS bindingQty,
                     (
                        oop.quantity - ifnull(t.qty, 0) - IFNULL(t2.qty, 0)
                    ) AS nobindingQty,
                     oop.price AS price,
                    oop.quantity as allQty,
                    IF (
                        opq.price IS NULL,
                        0,
                        (
                            opq.amount_price_per + opq.amount_service_fee_per
                        ) * (
                            oop.quantity - ifnull(t.qty, 0) - IFNULL(t2.qty, 0)
                        )
                    ) AS quoteAmount,
                     round(
                        (
                            oop.quantity - ifnull(t.qty, 0) - IFNULL(t2.qty, 0)
                        ) * (
                            oop.service_fee_per
                        ),
                        2
                    ) AS service_fee,
                     round(
                        (
                            oop.quantity - ifnull(t.qty, 0) - IFNULL(t2.qty, 0)
                        ) * (oop.poundage / oop.quantity),
                        2
                    ) AS poundage,
                     oc.country_id,
                    oop.service_fee_per,
                    oop.freight_per,
                    oop.package_fee,
                    oop.coupon_amount,
                    oop.campaign_amount,
                    ifnull(opq.amount_price_per,0) as amount_price_per,
                    ifnull(opq.amount_service_fee_per,0) as amount_service_fee_per
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
                  and soa.product_id not in ({$product_str})
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
     * 判断 当前订单是否是采购订单 且是否可以return
     *
     * @param int $order_id
     * @return bool
     */
    public function checkCWFIsRMA($order_id)
    {
        $status = $this->orm->table('oc_order_cloud_logistics')
            ->where('order_id', $order_id)
            ->value('cwf_status');
        return (int)$status === 16;
    }

    /**
     * @param int $order_id
     * @return mixed
     */
    public function getTotalFreightFromOrderTotal($order_id)
    {
        return $this->orm->table('oc_order_total')
            ->where([
                ['order_id', '=', $order_id],
                ['code', '=', 'freight']
            ])
            ->value('value');
    }


    public function getPurchaseInfo($customer_id, array $data = [])
    {
        $ret = $this->queryPurchaseOrder(...func_get_args());
        if (isset($data['page']) && isset($data['page_limit'])) {
            $ret->forPage(($data['page'] ?? 1), ($data['page_limit'] ?? 20));
        }
        return $ret->groupBy(['oo.order_id'])
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->toArray();
    }

    public function getPurchaseInfoTotal(int $customer_id, array $data = []){
        $ret = $this->queryPurchaseOrder(...func_get_args());
        return $ret->groupBy(['oo.order_id'])
            ->get()->count();
    }
    /**r
     * @param int $customer_id
     * @param array $data
     * @param string $limit_date 时效时间，起始时间
     * @return Builder
     */
    private function queryPurchaseOrder(int $customer_id, array $data = []): Builder
    {
        $co = new Collection($data);

        $sql = $this->orm
            ->table('oc_order as oo')
            ->select('oo.order_id', 'oo.date_added', 'oo.order_status_id')
            ->leftJoin('oc_order_product as oop', ['oop.order_id' => 'oo.order_id'])
            ->leftJoin(
                'oc_product as op',
                ['op.product_id' => 'oop.product_id']
            );
        $sql = $sql->where(['oo.customer_id' => $customer_id]);
        if (isset($data['filter_include_returns']) && $data['filter_include_returns'] == '-1') {
            $res = $this->getAllRefundOrderIds($this->customer->getId());
            $res = count($res) > 0 ? $res : [0];
            $sql->whereNotIn('oo.order_id', $res);
        }
        //关联销售单查询
        if (isset($data['filter_associatedOrder']) && in_array($data['filter_associatedOrder'],[1,2,3])) {
            $res_2 = $this->getAllAssociatedOrder($this->customer->getId(),$data['filter_associatedOrder']);
            $res_2 = count($res_2) > 0 ? $res_2 : [0];
            $sql->whereIn('oo.order_id', $res_2);
        }
        return $sql->when(!empty($co->get('filter_PurchaseOrderId')), function (Builder $q) use ($co) {
            $q->where('oo.order_id', 'like', '%' . $co->get('filter_PurchaseOrderId') . '%');
        })
            ->when(($co->get('filter_orderStatus') != -1),
                function (Builder $q) use ($co) {
                    $q->where('oo.order_status_id', $co->get('filter_orderStatus'));
                }
            )
            ->when(!empty($co->get('filter_item_code')), function (Builder $q) use ($co) {
                $q->where('op.sku', 'like', '%' . $co->get('filter_item_code') . '%');
            })
            ->when(!empty($co->get('filter_orderDate_from')), function (Builder $q) use ($co) {
                $q->where('oo.date_added', '>=', $co->get('filter_orderDate_from'));
            })
            ->when(!empty($co->get('filter_orderDate_to')), function (Builder $q) use ($co) {
                $q->where('oo.date_added', '<=', $co->get('filter_orderDate_to'));
            })
            ->orderBy('oo.order_id', 'desc');
    }

    public function getEuropeFrightTag($product_id)
    {
        $europe_freight_product_list = json_decode($this->config->get('europe_freight_product_id'));
        if(in_array($product_id,$europe_freight_product_list)){
            return true;
        }
        return false;
    }

    public function getPurchaseOrderFreightTag($order_id_list,$line_id)
    {
        $europe_freight_product_list = json_decode($this->config->get('europe_freight_product_id'));
        return $this->orm->table('oc_order_product as oop')
                    ->leftJoin('tb_sys_order_associated as soa','soa.order_product_id','=','oop.order_product_id')
                    ->where('soa.sales_order_line_id','=',$line_id)
                    ->whereIn('oop.order_id',$order_id_list)
                    ->whereIn('oop.product_id',$europe_freight_product_list)
                    ->selectRaw('oop.order_id,oop.order_product_id')
                    ->get()
                    ->keyBy('order_id')
                    ->map(function ($v){
                        return (array)$v;
                    })
                    ->toArray();
    }

    public function getPurchaseOrder(array $orderIdArr)
    {
        return $this->orm->table('oc_order_product as oop')
            ->leftJoin('oc_order as oo', 'oo.order_id', '=', 'oop.order_id')
            ->leftJoin('oc_order_quote as oq', [['oq.order_id', '=', 'oop.order_id'], ['oq.product_id', '=', 'oop.product_id']])
            ->leftJoin('oc_product_quote as opq', [['opq.order_id', '=', 'oop.order_id'], ['opq.product_id', '=', 'oop.product_id']])
            ->leftJoin('oc_product as op', 'op.product_id', '=', 'oop.product_id')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'op.product_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            ->whereIn('oop.order_id', $orderIdArr)
            ->select('oq.product_id as quote_product_id', 'oq.amount_data', 'oo.date_added', 'oo.order_status_id', 'oop.order_id', 'oop.name', 'oop.model', 'oop.price', 'oop.freight_per', 'oop.quantity', 'op.sku', 'op.image', 'ctp.customer_id', 'oop.service_fee_per', 'oop.package_fee', 'oop.product_id', 'oo.delivery_type', 'oop.type_id', 'oop.agreement_id', 'ctp.customer_id as seller_id', 'ctc.screenname', 'op.product_type', 'opq.amount', 'opq.amount_price_per', 'opq.amount_service_fee_per', 'oop.coupon_amount', 'oop.campaign_amount')
            ->selectRaw('opq.price as quote_price')
            ->orderBy('oo.order_id', 'desc')
            ->orderBy('oop.order_product_id', 'asc')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
    }

    public function judgeSalesOrderStatus($order_id,$customer_id)
    {
        //检测预绑定订单是否有销售订单对应的
        $exists = $this->orm->table('tb_sys_order_associated_pre as p')
            ->leftJoin('tb_sys_customer_sales_order as o','o.id','=','p.sales_order_id')
            ->where([
                ['p.buyer_id','=',$customer_id],
                ['p.order_id','=',$order_id],
                ['o.order_status','<>',CustomerSalesOrderStatus::TO_BE_PAID], // 1 to be paid
            ])
            ->exists();
        if($exists) {
            return ['error' => 1];
        }
        return ['error'=> 0];

    }

    /**
     * 校验订单的商品状态
     *
     * @param int $orderId 订单号
     * @param int $customerId 用户ID
     *
     * @return int 不为1都是错误
     *           0 商品不存在,被删除、下架、精细化不可见等
     * @throws Exception
     */
    public function checkOrderProductStatus(int $orderId,int $customerId)
    {
        $order = Order::where('order_id', $orderId)->first();
        $productIds = $order->orderProducts->pluck('product_id')->toArray();

        /** @var ModelCustomerPartnerDelicacyManagement $delicacyModel */
        $delicacyModel = load()->model('customerpartner/DelicacyManagement');
        $displayProduct = $delicacyModel->checkIsDisplay_batch($productIds, $customerId);
        if (count($productIds) != count($displayProduct)) {
            return 0;
        }
        return 1;
    }

    /**
     * #33925 优化提示语，增对于单个多个的情况，区分产品不可用和加购类型等
     * @param int $orderId 采购订单ID
     * @param int $customerId
     * @return array
     * @throws Throwable
     */
    public function addReorderProductIntoCart($orderId, $customerId): array
    {
        $orderProducts = OrderProduct::queryRead()
            ->with(['product', 'order', 'customerPartnerToProduct', 'customerPartnerToProduct.customer'])
            ->where('order_id', $orderId)
            ->get();

        $stockBlackList = $this->getStockBlackList();
        $europeFreightProductIds = json_decode($this->config->get('europe_freight_product_id'));
        /** @var ModelCheckoutCart $modelCheckoutCart */
        $modelCheckoutCart = load()->model('checkout/cart');

        $unavailableProducts = []; // 产品不可用或seller已闭店
        $errorTypeProducts = []; // 加购类型不对
        $europeFreightProducts = []; // 补运费产品
        $addCartOrderProducts = []; // 可成功加购的
        $stockBlackOrderProducts = []; // seller不支持囤货的
        foreach ($orderProducts as $orderProduct) {
            /** @var OrderProduct $orderProduct */
            if (in_array($orderProduct->product_id, $europeFreightProductIds)) {
                $europeFreightProducts[] = $orderProduct->product;
                continue;
            }
            if (in_array($orderProduct->customerPartnerToProduct->customer_id, $stockBlackList)) {
                $stockBlackOrderProducts[] = $orderProduct;
                continue;
            }

            if ($orderProduct->product->status != ProductStatus::ON_SALE ||
                $orderProduct->product->product_type != ProductType::NORMAL ||
                $orderProduct->product->buyer_flag != YesNoEnum::YES ||
                $orderProduct->customerPartnerToProduct->customer->status != YesNoEnum::YES
            ) {
                $unavailableProducts[] = $orderProduct->product;
                continue;
            }

            // 如果复杂交易已完成/购买完，以normal方式加入购物车
            if ($orderProduct->type_id != ProductTransactionType::NORMAL) {
                if (!$modelCheckoutCart->verifyReorderStatus([
                    'type_id' => $orderProduct->type_id,
                    'agreement_id' => $orderProduct->agreement_id,
                    'product_id' => $orderProduct->product,
                ], $customerId)) {
                    $orderProduct->type_id = ProductTransactionType::NORMAL;
                    $orderProduct->agreement_id = null;
                }
            }

            if ($modelCheckoutCart->verifyProductAdd($orderProduct->product_id, $orderProduct->type_id , $orderProduct->agreement_id)) {
                $errorTypeProducts[] = $orderProduct->product;
                continue;
            }

            $addCartOrderProducts[] = $orderProduct;
        }

        // 订单为单产品，没有可加入购物车的产品时
        if ($orderProducts->count() == 1) {
            if (!empty($unavailableProducts)) {
                return ['error' => 1, 'msg' => 'This product is unavailable.'];
            }
            if (!empty($errorTypeProducts)) {
                return ['error' => 1, 'msg' => 'This product already exists in the shopping cart for an alternative transaction type.'];
            }
            if (!empty($stockBlackOrderProducts)) {
                return ['error' => 1, 'msg' => "This product cannot be directly purchased for stock up. You must first upload a sales order and pay for the items, then ship the items immediately."];
            }
            if (!empty($europeFreightProducts)) {
                return ['error' => 1, 'msg' => 'Unable to add the additional shipping fee item to the shopping cart.'];
            }
        }
        // 判断订单下全部是一种错误类型的
        if (count($stockBlackOrderProducts) == $orderProducts->count()) {
            return ['error' => 1, 'msg' => "All products cannot be directly purchased for stock up. You must first upload a sales order and pay for the items, then ship the items immediately."];
        }
        if (count($europeFreightProducts) == $orderProducts->count()) {
            return ['error' => 1, 'msg' => 'Unable to add the additional shipping fee item to the shopping cart.'];
        }
        if (count($errorTypeProducts) == $orderProducts->count()) {
            return ['error' => 1, 'msg' => 'Products already exist in the shopping cart for an alternative transaction type.'];
        }
        if (count($unavailableProducts) == $orderProducts->count()) {
            return ['error' => 1, 'msg' => 'All products are unavailable.'];
        }

        try {
            dbTransaction(function () use ($addCartOrderProducts, $modelCheckoutCart) {
                foreach ($addCartOrderProducts as $orderProduct) {
                    /** @var OrderProduct $orderProduct */
                    $modelCheckoutCart->add(
                        $orderProduct->product_id,
                        $orderProduct->quantity,
                        [],
                        0,
                        $orderProduct->type_id,
                        $orderProduct->agreement_id,
                        $orderProduct->order->delivery_type);
                }
            });
        } catch (\Exception $e) {
            return ['error' => 1, 'msg' => 'Add failed. Please try again.'];
        }

        $msg = [];
        if (!empty($addCartOrderProducts)) {
            $addCartOrderProductSkus = collect($addCartOrderProducts)->pluck('product.sku');
            $msg['add_cart'] = $addCartOrderProductSkus->join(',') . ' ' . ($addCartOrderProductSkus->count() > 1 ? 'have' : 'has') . ' been successfully added to the cart. ';
            if (empty($unavailableProducts) && empty($stockBlackOrderProducts) && empty($errorTypeProducts)) {
                $msg['add_cart'] = 'Successfully added to cart.';
            }
        }

        if (!empty($stockBlackOrderProducts)) {
            $msg['stock_black'] = collect($stockBlackOrderProducts)->pluck('product.sku')->join(',') . ' cannot be directly purchased for stock up. You must first upload a sales order and pay for the items, then ship the items immediately.';
            if (empty($addCartOrderProducts) && empty($errorTypeProducts) && empty($unavailableProducts)) {
                $msg['stock_black'] = 'All regular product(s) cannot be directly purchased for stock up. You must first upload a sales order and pay for the items, then ship the items immediately.  ';
            }
        }

        if (!empty($errorTypeProducts)) {
            $errorTypeProductSkus = collect($errorTypeProducts)->pluck('sku');
            $msg['error_type'] = $errorTypeProductSkus->join(',') . ' already ' . ($errorTypeProductSkus->count() > 1 ? 'exist' : 'exists') . ' in the shopping cart for an alternative transaction type.';
            if (empty($addCartOrderProducts) && empty($stockBlackOrderProducts) && empty($unavailableProducts)) {
                $msg['error_type'] = 'Product(s) already exist in the shopping cart for an alternative transaction type.';
            }
        }

        if (!empty($unavailableProducts)) {
            $unavailableProductSkus = collect($unavailableProducts)->pluck('sku');
            $msg['unavailable'] = $unavailableProductSkus->join(',') . ' ' . ($unavailableProductSkus->count() > 1 ? 'are' : 'is') . ' unavailable.';
            if (empty($addCartOrderProducts) && empty($stockBlackOrderProducts) && empty($errorTypeProducts)) {
                $msg['unavailable'] = 'All regular product(s) are unavailable. ';
            }
        }

        if (!empty($europeFreightProducts)) {
            $msg['europe_freight'] = 'Additional shipping fee item cannot be added to the cart.';
            if (empty($errorTypeProducts) && empty($unavailableProducts) && empty($stockBlackOrderProducts)) {
                $msg['europe_freight'] = 'Unable to add the additional shipping fee item to the shopping cart.';
            }
        }

        $error = 2;
        if (!isset($msg['error_type']) && !isset($msg['unavailable'])) {
            $error = 0;
        }
        if (!isset($msg['add_cart'])) {
            $error = 1;
        }
        return ['error' => $error, 'msg' => implode('<br/>', $msg)];
    }

    /**
     * 获取不能囤货的seller名单
     * @return array
     */
    private function getStockBlackList(): array
    {
        $stockBlackList = CustomerExts::query()->alias('ce')
            ->leftJoinRelations(['customerpartnerToProduct as ctp'])
            ->leftJoin('oc_customer as c', 'ctp.customer_id', 'c.customer_id')
            ->where('c.country_id', customer()->getCountryId())
            ->where('ce.not_support_store_goods', YesNoEnum::YES)
            ->select('ce.customer_id')
            ->pluck('customer_id')
            ->toArray();
        if (customer()->isCollectionFromDomicile()) {
            $stockBlackList = [];
        }

        return $stockBlackList;
    }

    public function getTotalTransaction($order_id){
        return $this->orm->table('oc_order_total')
            ->where([['order_id','=',$order_id],['code','=','poundage']])
            ->value('value');
    }

    public function getBxStore()
    {
        $bxStores = $this->orm->table('oc_setting as os')
            ->where('os.key', '=', 'config_customer_group_ignore_check')
            ->value('os.value');
        return obj2array($bxStores);
    }

    public function checkMarginProduct($purchaseOrder)
    {
        $result = $this->orm->table("tb_sys_margin_process as smp")
            ->leftJoin("tb_sys_margin_agreement as sma", "smp.margin_id", "=", "sma.id")
            ->select("sma.product_id", "sma.num","smp.margin_id")
            ->where("smp.advance_product_id", "=", $purchaseOrder['product_id'])
            ->first();
        return obj2array($result);
    }

    public function checkRestMarginProduct($purchaseOrder)
    {
        $result = $this->orm->table("tb_sys_margin_process as smp")
            ->leftJoin("tb_sys_margin_agreement as sma", "smp.margin_id", "=", "sma.id")
            ->leftJoin('oc_customerpartner_to_product  as otp','otp.product_id','=','smp.rest_product_id')
            ->select("otp.customer_id as seller_id","sma.product_id", "sma.num",'smp.margin_id')
            ->where([
                "smp.rest_product_id" => $purchaseOrder['product_id'],
                "smp.margin_id" => $purchaseOrder['agreement_id'],
            ])
            ->first();
        return obj2array($result);
    }


    //货期尾款商品
    public function checkRestFuturesProduct($purchaseOrder)
    {
        $result = $this->orm->table("oc_futures_margin_agreement as fa")
            ->leftJoin("oc_futures_margin_delivery as fd", "fa.id", "=", "fd.agreement_id")
            ->select('fd.agreement_id','fa.product_id', 'fa.seller_id', 'fa.buyer_id')
            ->where([
                "fa.agreement_status"   => 7,
                "fd.delivery_status"    => 6,
                "fa.product_id"         => $purchaseOrder['product_id'],
                "fd.agreement_id"       => $purchaseOrder['agreement_id'],
            ])
            ->first();
        return obj2array($result);
    }

    //期货头款商品
    public function checkFuturesAdvanceProduct($purchaseOrder)
    {
        $result = $this->orm->table("oc_futures_margin_process as fp")
            ->leftJoin("oc_futures_margin_agreement as fa", "fa.id", "=", "fp.agreement_id")
            ->select("fp.advance_product_id", "fp.agreement_id")
            ->where("fp.advance_product_id", "=", $purchaseOrder['product_id'])
            ->where('fa.agreement_status', "=", 3)
            ->where('fp.process_status', "=", 1)
            ->first();
        return obj2array($result);
    }

    /**
     * [rebackMarginSuffixStore description] 更新保证金尾款库存
     * @param int $product_id
     * @param $num
     */
    public function rebackMarginSuffixStore($product_id, $num)
    {
        //返还上架数量
        // 同步更改子sku所属的其他combo的上架库存数量。
        //返还产品上架数量
        $this->orm->table('oc_product')
            ->where('product_id','=',$product_id)
            ->increment('quantity',$num);
        $this->orm->table('oc_customerpartner_to_product')
            ->where('product_id','=',$product_id)
            ->increment('quantity',$num);
        $setProductInfoArr = $this->orm->table('tb_sys_product_set_info as psi')
            ->where('psi.product_id', $product_id)
            ->whereNotNull('psi.set_product_id')
            ->select('psi.set_product_id', 'psi.set_mpn', 'psi.qty')
            ->get()
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        foreach ($setProductInfoArr as $setProductInfo) {

            // 同步新增SKU的库存
            $this->orm->table('oc_product')
                ->where('product_id','=',$setProductInfo['set_product_id'])
                ->increment('quantity',$num* $setProductInfo['qty']);
            $this->orm->table('oc_customerpartner_to_product')
                ->where('product_id','=',$setProductInfo['set_product_id'])
                ->increment('quantity',$num* $setProductInfo['qty']);
            // 同步更改子sku所属的其他combo的上架库存数量。
            $this->updateOtherMarginComboQuantity($setProductInfo['set_product_id'], $product_id);
        }
        /**
         * 如果当前产品是 其他combo 的组成，则需要同步修改之
         */
        $this->updateOtherMarginComboQuantity($product_id, 0);
    }

    public function updateOtherMarginComboQuantity($product_id, $filter_combo_id = 0)
    {
        $result = $this->orm->table('tb_sys_product_set_info as psi')
            ->whereRaw("psi.set_product_id = " . $product_id . " and psi.product_id !=" . $filter_combo_id)
            ->select('psi.product_id')
            ->get();
        $otherCombos = obj2array($result);
        foreach ($otherCombos as $combo) {
            // 获取该combo之下的所有sku 的库存、组成combo的比例qty
            $sonSkuResult = $this->orm->table('tb_sys_product_set_info as psi')
                ->whereRaw("psi.product_id=" . $combo['product_id'] . " and psi.set_product_id is not null")
                ->selectRaw("psi.set_product_id, psi.qty, (select sum(onhand_qty) from tb_sys_batch where product_id = psi.set_product_id) as quantity")
                ->get();
            $sonSkuArr = obj2array($sonSkuResult);
            $tempArr = [];
            foreach ($sonSkuArr as $son) {
                //舍去法取整，获取当前sku 最高可以组成几个combo品
                $tempArr[] = floor($son['quantity'] / $son['qty']);
            }
            // 根据木桶效应，可以组成combo的最大数量取决于 其中sku组成的最小值
            $maxQuantity = !empty($tempArr) ? min($tempArr) : 0;
            $comboOnShelfResult = $this->orm->table('oc_product AS op')
                ->where('op.product_id', '=', $combo['product_id'])
                ->select('op.quantity')
                ->lockForUpdate()
                ->first();
            $comboOnShelfResult = obj2array($comboOnShelfResult);
            if ($maxQuantity <= ($comboOnShelfResult['quantity'] ?? 0)) {
                $this->orm->table('oc_product')
                    ->where([['product_id','=', $combo['product_id']],['subtract','=',1]])
                    ->update(['quantity'=>$maxQuantity]);
                $this->orm->table('oc_customerpartner_to_product')
                    ->where('product_id','=', $combo['product_id'])
                    ->update(['quantity'=>$maxQuantity]);
            }
        }
    }

    public function deleteMarginProductLock($agreement_id)
    {
        $futuresToMargin = $this->futuresToMargin($agreement_id);
        if (!$futuresToMargin){//期货转现货订单未支付成功，不解除库存锁定
            $ids = $this->orm->table("oc_product_lock")
                ->where([
                    'agreement_id' => $agreement_id,
                    'type_id' => 2, //margin type
                ])
                ->get(['id']);
            $ids = obj2array($ids);
            $ids=array_column($ids,'id');
            if ($ids) {
                $this->orm->table("oc_product_lock_log")->whereIn('product_lock_id', $ids)->delete();
            }
            $this->orm->table("oc_product_lock")
                ->where([
                    'agreement_id' => $agreement_id,
                    'type_id' => 2, //margin type
                ])->delete();
        }

        $this->orm->table("oc_agreement_common_performer")
            ->where([
                'agreement_id' => $agreement_id,
                'agreement_type' => 0, //margin type
            ])->delete();

    }

    //是否是期货转现货
    public function futuresToMargin($marginId)
    {
        return $this->orm->table('oc_futures_margin_delivery')
            ->where([
                'margin_agreement_id'   => $marginId
            ])
            ->value('agreement_id');
    }

    public function marginStoreReback($product_id, $qty)
    {
        $this->orm->table('oc_product')
            ->where('product_id','=',$product_id)
            ->increment('quantity',$qty);
        $this->orm->table('oc_customerpartner_to_product')
            ->where('product_id','=',$product_id)
            ->increment('quantity',$qty);
    }

    public function updateMarginProductLock($agreement_id, $num, $order_id)
    {
        $list = db('oc_product_lock')
            ->where(['agreement_id' => $agreement_id, 'type_id' => 2,])//margin type
            ->get()
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();

        foreach ($list as $item) {
            $set_qty = $item['set_qty'];
            db('oc_product_lock')->where('id', $item['id'])->increment('qty', $set_qty * $num);
            //删除log
            db('oc_product_lock_log')
                ->where('product_lock_id', $item['id'])
                ->where('transaction_id', $order_id)
                ->delete();
        }
    }

    //退还上架库存
    public function reback_stock_ground($margin,$order){
        //获取协议状态
        $margin_info = $this->orm->table('tb_sys_margin_agreement')
            ->where('id','=',$margin['margin_id'])
            ->select('status')->first();
        if(in_array($margin_info->status,[9,10])){    //margin 已经取消
            // 抹平lock 中数据
            $this->clear_product_lock($margin['margin_id'],$order['quantity'],$order['order_id']);
            // 返还上架库存
            $this->rebackMarginSuffixStore($order['product_id'], $order['quantity']);
        }
    }

    public function clear_product_lock($agreement_id,$num,$order_id){
        $list = $this->orm->table("oc_product_lock")
            ->where([
                'agreement_id' => $agreement_id,
                'type_id' => 2, //margin type
            ])
            ->get()
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();
        $length = count($list);
        if ($length == 1) {
            $this->orm->table("oc_product_lock")
                ->where([
                    'agreement_id' => $agreement_id,
                    'type_id' => 2, //margin type
                ])
                ->decrement('qty', $num);
            $this->orm->table('oc_product_lock_log')
                ->insert(array(
                    'product_lock_id'=>$list[0]['id'],
                    'qty'=>$num*-1,
                    'change_type'=>3,
                    'transaction_id'=>$order_id,
                    'memo'=>'yzc_task_work订单退返',
                    'create_user_name'=>'yzc_task_work',
                    'create_time'=>date('Y-m-d H:i:s',time())
                ));
        } else {
            foreach ($list as $item) {
                $set_qty = $item['set_qty'];
                $this->orm->table("oc_product_lock")
                    ->where('id', $item['id'])
                    ->decrement('qty', $set_qty * $num);
                //添加log
                $this->orm->table('oc_product_lock_log')
                    ->insert(array(
                        'product_lock_id'=>$item['id'],
                        'qty'=>$set_qty * $num*-1,
                        'change_type'=>3,
                        'transaction_id'=>$order_id,
                        'memo'=>'yzc_task_work订单退返',
                        'create_user_name'=>'yzc_task_work',
                        'create_time'=>date('Y-m-d H:i:s',time())
                    ));
            }
        }
    }

    public function getPreDeliveryLines($order_product_id)
    {
        $deliveryLines = $this->orm->table('tb_sys_seller_delivery_pre_line as dpl')
            ->where('dpl.order_product_id', '=', $order_product_id)
            ->selectRaw('dpl.id,dpl.product_id,dpl.batch_id,dpl.qty')
            ->get();
        return json_decode($deliveryLines, true);
    }

    public function reback_batch($preDeliveryLine){
        // 返还批次库存
        $this->orm->table('tb_sys_batch')
            ->where('batch_id','=',$preDeliveryLine['batch_id'])
            ->increment('onhand_qty',$preDeliveryLine['qty']);
        //设置预出库表的这状态
        $this->orm->table('tb_sys_seller_delivery_pre_line')
            ->where('id','=',$preDeliveryLine['id'])
            ->update(['status'=>0]);
    }

    //期货头款库存恢复
    public function updateFuturesAdvanceProductStock($productId)
    {
        $this->orm->table('oc_product')
            ->where('product_id','=', $productId)
            ->where('product_type', '=', 2)
            ->update(['quantity'=>1]);
        $this->orm->table('oc_customerpartner_to_product')
            ->where([
                'product_id'    => $productId
            ])
            ->update(['quantity'=>1]);
    }

    /**
     * 反库存
     * @param array $purchaseOrder
     * @param array $preDeliveryLine
     * @param bool $outFlag 外部店铺标志
     */
    public function rebackStock($purchaseOrder, $preDeliveryLine, $outFlag)
    {
        // 返还批次库存
        $this->orm->table('tb_sys_batch')
            ->where('batch_id','=',$preDeliveryLine['batch_id'])
            ->increment('onhand_qty',$preDeliveryLine['qty']);
        if (!$outFlag) {
            //内部店铺
            $syncResult = $this->orm->table('oc_product as op')
                ->where('op.product_id', '=', $preDeliveryLine['product_id'])
                ->selectRaw("ifnull(sync_qty_date,'2018-01-01 00:00:00') as sync_qty_date")
                ->lockForUpdate()
                ->first();
            $sync_qty_date = $syncResult->sync_qty_date;
            $date_added = strtotime($purchaseOrder['date_added']);
            if ($date_added > strtotime($sync_qty_date)) {
                //返还产品上架数量
                $this->orm->table('oc_product')
                    ->where('product_id','=',$preDeliveryLine['product_id'])
                    ->increment('quantity',$preDeliveryLine['qty']);
                $this->orm->table('oc_customerpartner_to_product')
                    ->where('product_id','=',$preDeliveryLine['product_id'])
                    ->increment('quantity',$preDeliveryLine['qty']);
            }
        } else {
            //外部店铺
            //返还产品上架数量
            $this->orm->table('oc_product')
                ->where('product_id','=',$preDeliveryLine['product_id'])
                ->increment('quantity',$preDeliveryLine['qty']);
            $this->orm->table('oc_customerpartner_to_product')
                ->where('product_id','=',$preDeliveryLine['product_id'])
                ->increment('quantity',$preDeliveryLine['qty']);
        }

        // 同步更改子sku所属的其他combo的上架库存数量。
        $this->updateOtherComboQuantity($preDeliveryLine['product_id']);
        //设置预出库表的这状态
        $this->orm->table('tb_sys_seller_delivery_pre_line')
            ->where('id','=',$preDeliveryLine['id'])
            ->update(['status'=>0]);
    }

    public function updateOtherComboQuantity($product_id, $filter_combo_id = 0)
    {
        $result = $this->orm->table('tb_sys_product_set_info as psi')
            ->whereRaw("psi.set_product_id = " . $product_id . " and psi.product_id !=" . $filter_combo_id)
            ->select('psi.product_id')
            ->get();
        $otherCombos = obj2array($result);
        foreach ($otherCombos as $combo) {
            // 获取该combo之下的所有sku 的库存、组成combo的比例qty
            $sonSkuResult = $this->orm->table('tb_sys_product_set_info as psi')
                ->whereRaw("psi.product_id=" . $combo['product_id'] . " and psi.set_product_id is not null")
                ->selectRaw("psi.set_product_id, psi.qty, (select sum(onhand_qty) from tb_sys_batch where product_id = psi.set_product_id) as quantity")
                ->get();
            $sonSkuArr = obj2array($sonSkuResult);
            $tempArr = [];
            foreach ($sonSkuArr as $son) {
                //舍去法取整，获取当前sku 最高可以组成几个combo品
                $tempArr[] = floor($son['quantity'] / $son['qty']);
            }
            // 根据木桶效应，可以组成combo的最大数量取决于 其中sku组成的最小值
            $maxQuantity = !empty($tempArr) ? min($tempArr) : 0;
            $comboOnShelfResult = $this->orm->table('oc_product AS op')
                ->where('op.product_id', '=', $combo['product_id'])
                ->select('op.quantity')
                ->lockForUpdate()
                ->first();
            $comboOnShelfResult = obj2array($comboOnShelfResult);
            if ($maxQuantity <= ($comboOnShelfResult['quantity'] ?? 0)) {
                $this->orm->table('oc_product')
                    ->where([['product_id','=',$combo['product_id']],['subtract','=',1]])
                    ->update(['quantity'=>$maxQuantity]);
                $this->orm->table('oc_customerpartner_to_product')
                    ->where('product_id','=',$combo['product_id'])
                    ->update(['quantity'=>$maxQuantity]);
            }
        }
    }

    /**
     * combo品的库存退回
     * @param int $product_id
     * @param int $quantity
     */
    public function rebackComboProduct($product_id, $quantity)
    {
        $this->orm->table('oc_product')
            ->where('product_id','=',$product_id)
            ->increment('quantity',$quantity);
        $this->orm->table('oc_customerpartner_to_product')
            ->where('product_id','=',$product_id)
            ->increment('quantity',$quantity);
    }

    public function getNoCancelPurchaseOrderLine($order_id)
    {
        //获取该采购订单的明细
        $unCompleteLineResult = $this->orm->table('oc_order as oo')
            ->leftjoin('oc_order_product as oop', 'oop.order_id', '=', 'oo.order_id')
            ->leftjoin('oc_product as op', 'op.product_id', '=', 'oop.product_id')
            ->leftjoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'op.product_id')
            ->leftjoin('oc_customer as oc', 'oc.customer_id', '=', 'ctp.customer_id')
            ->where([['oo.order_id', '=', $order_id],['oo.order_status_id','=',OcOrderStatus::TO_BE_PAID]])
            ->selectRaw('oo.order_id,oo.payment_code,oop.order_product_id,oop.product_id,
            oop.quantity,oc.customer_id,oc.accounting_type,op.combo_flag,oo.date_added,oop.type_id,oop.agreement_id')
            ->get();
        $unCompleteOrderLineInfo = json_decode($unCompleteLineResult, true);
        return $unCompleteOrderLineInfo;
    }

    public function cancelPurchaseOrder($order_id)
    {
        $this->orm->table("oc_order as oo")
            ->where('oo.order_id', '=', $order_id)
            ->update(['oo.order_status_id' => OcOrderStatus::CANCELED]);
    }

    /**
     * 获取关联订单信息
     *
     * @param  int  $customer_id
     * @param  int  $search_type 1:完全绑定  2:未绑定  3:部分绑定
     * @return array
     */
    public function getAllAssociatedOrder($customer_id , $search_type = 1) :array
    {
        if (empty($customer_id) || empty($search_type) || !in_array($search_type,[1,2,3])) {
            return [] ;
        }

        //计算采购订单RMA
        $phurse_builder = $this->orm->table('oc_yzc_rma_order as t1')
            ->leftJoin('oc_yzc_rma_order_product as t2','t1.id','=','t2.rma_id')
            ->selectRaw("t1.order_id,IFNULL(sum(t2.quantity),0) as all_phurse_back_qty")
            ->where(['t1.buyer_id' => $customer_id , 't1.order_type' => 2 ,'t2.status_refund' => 1 ,'t1.seller_status' => 2 ,'t1.cancel_rma' => 0])
            ->whereIn('t2.rma_type',[2,3])
            ->groupBy('t1.order_id');

        //计算销售订单RMA  分2步
        $sales_builder_first = $this->orm->table('oc_yzc_rma_order as t1')
            ->leftJoin('oc_yzc_rma_order_product as t2','t1.id','=','t2.rma_id')
            ->leftJoin('tb_sys_customer_sales_order as t3','t1.from_customer_order_id','=','t3.order_id')
            ->leftJoin('tb_sys_order_associated as t4', function ($join) {
                $join->on('t3.id', '=', 't4.sales_order_id')
                    ->on('t2.order_product_id', '=', 't4.order_product_id');
            })
            ->selectRaw("t1.order_id,t1.from_customer_order_id,t3.id as sales_order_id,t2.order_product_id,t4.qty")
            ->where(['t1.buyer_id' => $customer_id ,'t1.order_type' => 1 ,'t2.status_refund' => 1 ,'t1.seller_status' => 2 ,'t1.cancel_rma' => 0])
            ->where('t3.order_status' , '=',CustomerSalesOrderStatus::CANCELED)
            ->groupBy("t1.order_id")
            ->groupBy("t1.from_customer_order_id")
            ->groupBy("t2.order_product_id");

        $sales_builder_second = $this->orm->table( new Expression('(' . get_complete_sql($sales_builder_first) . ') AS sa'))
            ->selectRaw("sa.order_id,ifnull(sum(sa.qty),0) as all_sales_back_qty")
            ->groupBy('sa.order_id');

        //销售单整体总数量
        $query = $this->orm->table('tb_sys_order_associated as oa')
            ->where('oa.buyer_id',$customer_id)
            ->selectRaw('oa.order_id,sum(ifnull( oa.qty, 0 )) as all_sales_qty')
            ->groupBy('oa.order_id');

        $builder = $this->orm->table('oc_order as oc')
            ->leftJoin('oc_order_product as op', 'oc.order_id', '=', 'op.order_id')
            ->leftJoin('oc_product as pt','op.product_id','=','pt.product_id')
            ->leftJoin(
                new Expression('(' . get_complete_sql($query) . ') AS oso'),
                'oso.order_id', '=', 'oc.order_id'
            )->leftJoin(
                new Expression('(' . get_complete_sql($phurse_builder) . ') AS ps'),
                'ps.order_id', '=', 'oc.order_id'
            )->leftJoin(
                new Expression('(' . get_complete_sql($sales_builder_second) . ') AS ss'),
                'ss.order_id', '=', 'oc.order_id'
            )
            ->selectRaw("oc.order_id,sum(op.quantity) - ifnull(ps.all_phurse_back_qty,0) as all_lefted_phurse_qty,ifnull(oso.all_sales_qty,0) -ifnull(ss.all_sales_back_qty,0) as all_lefted_sales_qty")
            ->where('oc.customer_id', $customer_id)
            ->whereNotIn('pt.product_type',[1,2]) //排除头款订单
            ->groupBy('oc.order_id') ;

        $order_list = $builder->get();

        switch ($search_type){
            case 1: //全关联
                $order_list = $order_list->filter(function ($item){
                    return (($item->all_lefted_phurse_qty > 0) && ($item->all_lefted_phurse_qty == $item->all_lefted_sales_qty)) ;
                });
                break;
            case 2://未关联
                $order_list = $order_list->filter(function ($item){
                    return ($item->all_lefted_sales_qty == 0)  ; // && $item->all_lefted_phurse_qty > 0  数量问题
                });
                break;
            case 3://部分关联
                $order_list = $order_list->filter(function ($item){
                    return ($item->all_lefted_sales_qty > 0) && ($item->all_lefted_phurse_qty > $item->all_lefted_sales_qty) ;
                });
                break;
            default :
                break;
        }

        $list = $order_list->toArray();

        return empty($list) ? [] : array_column($list,'order_id') ;
    }


}
