<?php


use App\Components\UniqueGenerator\Enums\ServiceEnum;
use App\Enums\Common\YesNoEnum;
use App\Enums\Country\Country;
use App\Enums\Future\FuturesVersion;
use App\Enums\Margin\MarginAgreementStatus;
use App\Enums\Margin\MarginProcessStatus;
use App\Enums\Order\OcOrderStatus;
use App\Enums\Product\ProductLockType;
use App\Enums\Product\ProductStatus;
use App\Enums\Product\ProductTransactionType;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Helper\CountryHelper;
use App\Logging\Logger;
use App\Models\Margin\MarginAgreement;
use App\Models\Margin\MarginOrderRelation;
use App\Models\Margin\MarginProcess;
use App\Models\Order\OrderProduct;
use App\Models\Product\Product;
use App\Models\Product\ProductLock;
use App\Models\Margin\MarginPerformerApply;
use App\Repositories\Common\SerialNumberRepository;
use App\Repositories\Buyer\BuyerToSellerRepository;
use App\Repositories\ProductLock\ProductLockRepository;
use Catalog\model\futures\agreementMargin;
use Catalog\model\futures\credit;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Carbon;
use App\Services\Margin\MarginService;
use App\Enums\Margin\MarginAgreementLogType;

/**
 * Class ModelAccountProductQuotesMargin
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountProductQuotesMargin $model_account_product_quotes_margin
 * @property ModelAccountProductQuotesMarginContract $model_account_product_quotes_margin_contract
 * @property ModelCatalogMarginProductLock $model_catalog_margin_product_lock
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 * @property ModelCheckoutOrder $model_checkout_order
 * @property ModelFuturesContract $model_futures_contract
 * @property ModelMessageMessage $model_message_message
 */
class ModelAccountProductQuotesMargin extends Model
{

    const transaction_type_margin = 2;

    public function marginStatusList()
    {
        $language_id = $this->config->get('config_language_id');
        $sql = "SELECT
          margin_agreement_status_id AS id,
          `name`,
          color
        FROM
          tb_sys_margin_agreement_status
        WHERE language_id = {$language_id} ";

        $sql .= ' order by sort asc ';

        $query = $this->db->query($sql);
        return $query->rows;
    }


    public function total($param = [])
    {
        $customer_id = $this->customer->getId();
        $agreement_id = $param['agreement_id'];
        $sku = $param['sku'];
        $status = $param['status'];
        $store = $param['store'];
        $date_from = $param['date_from'];
        $date_to = $param['date_to'];
        $hot_map = $param['hot_map'];


        $condition_buyer = " (a.buyer_id ={$customer_id} OR acp.buyer_id={$customer_id}) ";
        $condition = '';
        if ($agreement_id) {
            $condition .= " AND a.agreement_id LIKE '%{$agreement_id}%'";
        }
        if ($sku) {
            $condition .= " AND p.sku LIKE '%{$sku}%'";
        }

        $statusFlag = false;
        if (!$status) {
            $condition .= ' AND ((a.`status` in (1,2,3,5) AND a.is_bid = 1 ) or (a.`status` not in (1,2,3,5)))';
        } else {
            if (in_array($status, [
                MarginAgreementStatus::APPLIED,
                MarginAgreementStatus::PENDING,
                MarginAgreementStatus::APPROVED,
                MarginAgreementStatus::TIME_OUT,
            ])) {
                $statusFlag = true;
                $condition .= ' AND a.`status`=' . $status . ' AND a.is_bid = 1 ';
            }

            if ($status > 0 & !$statusFlag) {
                $condition .= ' AND a.`status`=' . $status;
            }

            if (in_array($status,
                [MarginAgreementStatus::REJECTED,
                    MarginAgreementStatus::TIME_OUT,
                    MarginAgreementStatus::BACK_ORDER])
            ) {
                $condition .= ' AND a.`buyer_ignore`=0 ';
            }

            if ($status == -1) {
                $condition .= ' AND a.`buyer_ignore`=1 ';
            }

        }

        switch ($hot_map) {
            case 'wait_process':
                $condition_buyer = ' TRUE ';
                $condition .= "
                AND ((a.buyer_id ={$customer_id} OR acp.buyer_id={$customer_id}) AND  ((a.`status` IN (4,9) AND a.buyer_ignore=0) or (a.`status` IN (1,2,5) AND a.buyer_ignore=0 and a.is_bid = 1)))";
                break;
            case 'wait_deposit_pay':
                $condition .= ' AND a.`status` =3 AND a.`is_bid`= 1 ';
                break;
            case 'due_soon':
                $condition .= ' AND a.`status` =6
                AND TIMESTAMPDIFF(DAY, NOW(), a.expire_time) < 7
                AND TIMESTAMPDIFF(DAY, NOW(), a.expire_time) >= 0
                AND a.expire_time>=NOW() ';
                break;
            case 'termination_request':
                $condition .= ' AND a.`status` =6
                AND a.termination_request = 1
                AND a.expire_time>=NOW() ';
                break;
            default :
                break;
        }
        if ($store) {
            $condition .= " AND c2c.screenname LIKE '%{$store}%'";
        }
        if ($date_from) {
            $condition .= " AND a.update_time>='$date_from'";
        }
        if ($date_to) {
            $condition .= " AND a.update_time<='$date_to'";
        }

        $sql = "
    SELECT
      COUNT(a.id) AS total
    FROM
        tb_sys_margin_agreement AS a
    LEFT JOIN tb_sys_margin_agreement_status AS s
        ON a.`status` = s.margin_agreement_status_id
    LEFT JOIN oc_customerpartner_to_customer AS c2c
        ON a.seller_id = c2c.customer_id
    LEFT JOIN oc_product AS p
        ON a.product_id = p.product_id
    LEFT JOIN tb_sys_margin_process AS mp
        ON a.id = mp.margin_id
    LEFT JOIN oc_agreement_common_performer AS acp
        ON acp.agreement_id=a.id
        AND acp.agreement_type=0
        AND acp.product_id=a.product_id
        AND acp.buyer_id={$customer_id}
    WHERE $condition_buyer" . $condition;

        $query = $this->db->query($sql);
        return $query->row['total'];
    }


    public function lists($param = [])
    {
        $customer_id = $this->customer->getId();
        $agreement_id = $param['agreement_id'];
        $sku = $param['sku'];
        $status = $param['status'];
        $store = $param['store'];
        $date_from = $param['date_from'];
        $date_to = $param['date_to'];
        $hot_map = $param['hot_map'];


        $condition_buyer = " (a.buyer_id ={$customer_id} OR acp.buyer_id={$customer_id}) ";
        $condition = '';
        if ($agreement_id) {
            $condition .= " AND a.agreement_id LIKE '%{$agreement_id}%'";
        }
        if ($sku) {
            $condition .= " AND p.sku LIKE '%{$sku}%'";
        }
        $statusFlag = false;
        if (!$status) {
            $condition .= ' AND ((a.`status` in (1,2,3,5) AND a.is_bid = 1 ) or (a.`status` not in (1,2,3,5)))';
        } else {
            if (in_array($status, [
                MarginAgreementStatus::APPLIED,
                MarginAgreementStatus::PENDING,
                MarginAgreementStatus::APPROVED,
                MarginAgreementStatus::TIME_OUT,
            ])) {
                $statusFlag = true;
                $condition .= ' AND a.`status`=' . $status . ' AND a.is_bid = 1 ';
            }

            if ($status > 0 & !$statusFlag) {
                $condition .= ' AND a.`status`=' . $status;
            }

            if (in_array($status,
                [MarginAgreementStatus::REJECTED,
                    MarginAgreementStatus::TIME_OUT,
                    MarginAgreementStatus::BACK_ORDER])
            ) {
                $condition .= ' AND a.`buyer_ignore`=0 ';
            }

            if ($status == -1) {
                $condition .= ' AND a.`buyer_ignore`=1 ';
            }

        }
        switch ($hot_map) {
            case 'wait_process':
                $condition_buyer = ' TRUE ';
                $condition .= "
                AND ((a.buyer_id ={$customer_id} OR acp.buyer_id={$customer_id}) AND  ((a.`status` IN (4,9) AND a.buyer_ignore=0) or (a.`status` IN (1,2,5) AND a.buyer_ignore=0 and a.is_bid = 1)))";
                break;
            case 'wait_deposit_pay':
                $condition .= ' AND a.`status` =3 and is_bid = 1 ';
                break;
            case 'due_soon':
                $condition .= ' AND a.`status` =6
                AND TIMESTAMPDIFF(DAY, NOW(), a.expire_time) < 7
                AND TIMESTAMPDIFF(DAY, NOW(), a.expire_time) >= 0
                AND a.expire_time>=NOW() ';
                break;
            case 'termination_request':
                $condition .= ' AND a.`status` =6
                AND a.termination_request = 1
                AND a.expire_time>=NOW() ';
                break;
            default :
                break;
        }
        if ($store) {
            $condition .= " AND c2c.screenname LIKE '%{$store}%'";
        }
        if ($date_from) {
            $condition .= " AND a.update_time>='$date_from'";
        }
        if ($date_to) {
            $condition .= " AND a.update_time<='$date_to'";
        }


        $sort = '';
        $sort_data = array(
            'a.agreement_id',
            'a.update_time',
        );
        if (isset($param['sort_value']) && in_array($param['sort_value'], $sort_data)) {
            $sort .= " ORDER BY " . $param['sort_value'];
        } else {
            $sort .= " ORDER BY a.`id`";
        }
        if (isset($param['order']) && (strtoupper($param['order']) == 'ASC')) {
            $sort .= " ASC";
        } else {
            $sort .= " DESC";
        }


        //分页
        $limit = '';
        if (isset($param['page_num']) && isset($param['page_limit']) && isset($param['page_limit'])) {
            $limit = " LIMIT " . ($param['page_num'] - 1) * $param['page_limit'] . ',' . $param['page_limit'];
        }


        $sql = "
    SELECT
        a.*
        , GROUP_CONCAT(DISTINCT mor.rest_order_id) AS rest_order_ids
        ,s.`name` AS status_name
        ,s.color AS status_color
        ,c2c.screenname
        ,buyer_customer.user_number
        ,p.sku
        ,mp.advance_product_id
        ,mp.rest_product_id
        ,mp.advance_order_id
        ,IFNULL(SUM(mor.purchase_quantity), 0) AS sum_purchase_qty
        ,COUNT(DISTINCT mpa.id) AS count_performer
        ,c2p.customer_id AS advance_seller_id
        ,acp.is_signed
    FROM
        tb_sys_margin_agreement AS a
    LEFT JOIN tb_sys_margin_agreement_status AS s
        ON a.`status` = s.margin_agreement_status_id
    LEFT JOIN oc_customerpartner_to_customer AS c2c
        ON a.seller_id = c2c.customer_id
    LEFT JOIN oc_customer AS buyer_customer
        ON buyer_customer.customer_id=a.buyer_id
    LEFT JOIN oc_product AS p
        ON a.product_id = p.product_id
    LEFT JOIN tb_sys_margin_process AS mp
        ON a.id = mp.margin_id
    LEFT JOIN tb_sys_margin_order_relation AS mor
        ON mor.margin_process_id=mp.id
    LEFT JOIN tb_sys_margin_performer_apply AS mpa
        ON mpa.agreement_id=a.id AND mpa.check_result IN (0,1) AND mpa.seller_approval_status IN (0,1)
    LEFT JOIN oc_customerpartner_to_product AS c2p
        ON c2p.product_id=mp.advance_product_id
    LEFT JOIN oc_agreement_common_performer AS acp
        ON acp.agreement_id=a.id
        AND acp.agreement_type=0
        AND acp.product_id=a.product_id
        AND acp.buyer_id={$customer_id}
    WHERE $condition_buyer" . $condition . ' GROUP BY a.id ' . $sort . $limit;

        $query = $this->db->query($sql);
        return $query->rows;
    }

    /**
     * @param int $order_id
     * @param int $product_id
     * @return int
     */
    public function getRmaQtyPurchaseAndSales($order_id, $product_id)
    {
        $order_id = intval($order_id);
        $product_id = intval($product_id);
        //采购单 和 对应的销售单
        $sql = "SELECT oa.order_id, oa.sales_order_id, oa.sales_order_line_id, oa.qty, oa.product_id, oa.seller_id, oa.buyer_id
    FROM oc_order As o
    LEFT JOIN tb_sys_order_associated AS oa ON oa.order_id=o.order_id AND oa.product_id={$product_id}
    WHERE o.order_id={$order_id}";

        $rows = $this->db->query($sql)->rows;
        $rows[] = [
            'order_id' => $order_id,
            'product_id' => $product_id,
            'buyer_id' => null,
            'sales_order_id' => null,
        ];


        //采购单 和 对应的销售单 的退货数量
        $qty_total = 0;
        foreach ($rows as $vvv) {
            if ($vvv['order_id']) {//避免销售单是空数组
                $qty = $this->getMarginRmaQty($order_id, $product_id, $vvv['buyer_id'], $vvv['sales_order_id']);
                $qty_total += $qty;
            }
        }

        return $qty_total;
    }


    public function getMarginTransactionDetailsInfo($param)
    {
        $margin_agreement_id = $param['margin_agreement_id'];
        if (!$margin_agreement_id) {
            return [];
        }

        $builder = $this->orm->table('oc_order_product as oop')
            ->join('tb_sys_margin_process as mp', [['mp.rest_product_id', '=', 'oop.product_id'], ['oop.agreement_id', '=', 'mp.margin_id']])
            ->leftJoin('tb_sys_margin_order_relation AS mor', [['mor.margin_process_id', '=', 'mp.id'], ['oop.order_id', '=', 'mor.rest_order_id']])
            ->leftJoin('tb_sys_order_associated as oa', [['oa.order_product_id', '=', 'oop.order_product_id'], ['oa.order_id', '=', 'mor.rest_order_id']])
            ->where([
                'oop.agreement_id' => $margin_agreement_id,
                'oop.type_id' => self::transaction_type_margin,
            ])
            ->whereNotNull('mor.rest_order_id');
        $count = $builder->count();
        $ret = $builder
            ->leftJoin('oc_product as p', 'p.product_id', '=', 'oop.product_id')
            ->leftJoin('oc_order as oo', 'oo.order_id', '=', 'oop.order_id')
            ->leftJoin('tb_sys_customer_sales_order as cso', 'cso.id', '=', 'oa.sales_order_id')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customerpartner_to_customer as ctc', 'ctc.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'ctc.customer_id')
            ->selectRaw('CONCAT(IFNULL(mor.rest_order_id, 0),IFNULL(cso.id, 0),IFNULL(cso.order_id,0)) AS g_id,
                      cso.`id` AS sales_header_id,
                      cso.`order_id` AS sales_order_id,
                      ctc.`screenname` AS store_name,
                      mor.rest_order_id,
                      mor.create_time,
                      p.sku,
                      SUM(oop.quantity) AS purchase_quantity,
                      oa.qty AS sales_quantity,
                      p.`product_id`,
                      mp.rest_product_id,
                      oo.customer_id AS buyer_id,
                      oo.delivery_type,
                      c.user_number')
            ->forPage($param['page_num'], $param['page_limit'])
            ->orderBy('mor.create_time', 'desc')
            ->orderBy('cso.id', 'desc')
            ->groupBy('g_id')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        return [
            'count' => $count,
            'ret' => $ret,
        ];


    }

    /**
     *
     * @param int $order_id oc_order表主键  oc_order.order_id==oc_yzc_rma_order.order_id
     * @param int $product_id
     * @param int $buyer_id
     * @param int|null $sales_order_id 销售订单主键
     * @return int
     */
    public function getRmaQty($order_id, $product_id, $buyer_id, $sales_order_id = null)
    {
        //销售单的RMA数量
        if (!is_null($sales_order_id)) {
            $sql = "SELECT oa.qty
    FROM oc_yzc_rma_order AS ro
    LEFT JOIN oc_yzc_rma_order_product AS rop ON rop.rma_id=ro.id
    LEFT JOIN tb_sys_customer_sales_order AS cso ON cso.order_id=ro.from_customer_order_id AND ro.buyer_id=cso.buyer_id
    LEFT JOIN tb_sys_order_associated AS oa ON oa.order_id=ro.order_id AND oa.sales_order_id=cso.id AND oa.product_id=rop.product_id
    LEFT JOIN tb_sys_margin_order_relation AS mor ON mor.rest_order_id=ro.order_id
    LEFT JOIN tb_sys_margin_process AS mp ON mp.id=mor.margin_process_id
    LEFT JOIN tb_sys_margin_agreement AS ma ON ma.id=mp.margin_id
    WHERE
    ro.order_id={$order_id}
    AND cso.id ={$sales_order_id}
    AND cso.order_status = ".CustomerSalesOrderStatus::CANCELED."
    AND ro.seller_status=2
    AND ro.cancel_rma=0
    AND ro.order_type=1
    AND rop.product_id={$product_id}
    AND rop.status_refund=1
    AND ma.expire_time >= ro.create_time
    AND ma.`status` IN (6)";
        } else {
            //采购单的RMA数量
            $sql = "SELECT rop.quantity AS qty
    FROM oc_yzc_rma_order AS ro
    LEFT JOIN oc_yzc_rma_order_product AS rop ON rop.rma_id=ro.id
    LEFT JOIN tb_sys_margin_order_relation AS mor ON mor.rest_order_id=ro.order_id
    LEFT JOIN tb_sys_margin_process AS mp ON mp.id=mor.margin_process_id
    LEFT JOIN tb_sys_margin_agreement AS ma ON ma.id=mp.margin_id
    WHERE
    ro.order_id={$order_id}
    AND ro.seller_status=2
    AND ro.cancel_rma=0
    AND ro.order_type=2
    AND rop.product_id={$product_id}
    AND rop.status_refund=1
    AND ma.expire_time >= ro.create_time
    AND ma.`status` IN (6)";
        }

        $qty = $this->db->query($sql)->row['qty'] ?? 0;
        return intval($qty);
    }


    /**
     *
     * @param int $order_id oc_order表主键  oc_order.order_id==oc_yzc_rma_order.order_id
     * @param int $product_id
     * @param int $buyer_id
     * @param int|null $sales_order_id 销售订单主键
     * @return int
     */
    public function getMarginRmaQty($order_id, $product_id, $buyer_id = 0, $sales_order_id = null)
    {
        //协议期间内
        //无关联的采购单RMA--Seller同意，减去
        //Cancel销售单RMA--Seller同意，减去
        //完成的销售案RMA--Seller同意，不减

        //销售单的RMA数量
        if (!is_null($sales_order_id)) {
            $sql = "SELECT oa.qty
    FROM oc_yzc_rma_order AS ro
    LEFT JOIN oc_yzc_rma_order_product AS rop ON rop.rma_id=ro.id
    LEFT JOIN tb_sys_customer_sales_order AS cso ON cso.order_id=ro.from_customer_order_id
    LEFT JOIN tb_sys_order_associated AS oa ON oa.order_id=ro.order_id AND oa.sales_order_id=cso.id AND oa.product_id=rop.product_id
    LEFT JOIN tb_sys_margin_order_relation AS mor ON mor.rest_order_id=ro.order_id
    LEFT JOIN tb_sys_margin_process AS mp ON mp.id=mor.margin_process_id
    LEFT JOIN tb_sys_margin_agreement AS ma ON ma.id=mp.margin_id
    WHERE
    ro.order_id={$order_id}
    AND cso.id ={$sales_order_id}
    AND cso.order_status=".CustomerSalesOrderStatus::CANCELED."
    AND ro.seller_status=2
    AND ro.cancel_rma=0
    AND ro.order_type=1
    AND rop.product_id={$product_id}
    AND rop.status_refund=1
    AND ma.expire_time >= ro.create_time
    AND ma.`status` IN (6)";
        } else {
            //采购单的RMA数量
            $sql = "SELECT rop.quantity AS qty
    FROM oc_yzc_rma_order AS ro
    LEFT JOIN oc_yzc_rma_order_product AS rop ON rop.rma_id=ro.id
    LEFT JOIN tb_sys_margin_order_relation AS mor ON mor.rest_order_id=ro.order_id
    LEFT JOIN tb_sys_margin_process AS mp ON mp.id=mor.margin_process_id
    LEFT JOIN tb_sys_margin_agreement AS ma ON ma.id=mp.margin_id
    WHERE
    ro.order_id={$order_id}
    AND ro.seller_status=2
    AND ro.cancel_rma=0
    AND ro.order_type=2
    AND rop.product_id={$product_id}
    AND rop.status_refund=1
    AND ma.expire_time >= ro.create_time
    AND ma.`status` IN (6)";
        }

        $ret = $this->db->query($sql)->row;
        $qty = empty($ret) ? 0 : $ret['qty'];
        return intval($qty);
    }


    public function getInfo($id = 0, $buyerId = null)
    {
        $id = intval($id);
        $customer_id = empty($buyerId) ? $this->customer->getId() : intval($buyerId);

        $sql = "SELECT
        a.*,
        s.`name` AS status_name,
        s.color AS status_color,
        c2c.screenname,
        p.sku,
        p.mpn,
        p.quantity,
        p.combo_flag,
        p.buyer_flag
        ,mp.advance_product_id
        ,mp.rest_product_id
        ,COUNT(DISTINCT mpa.id) AS count_performer
        ,c2p.customer_id AS advance_seller_id
    FROM
        tb_sys_margin_agreement AS a
        LEFT JOIN tb_sys_margin_agreement_status AS s ON a.`status` = s.margin_agreement_status_id
        LEFT JOIN oc_customerpartner_to_customer AS c2c ON a.seller_id = c2c.customer_id
        LEFT JOIN oc_product AS p ON a.product_id=p.product_id
        LEFT JOIN tb_sys_margin_process AS mp ON a.id = mp.margin_id
        LEFT JOIN oc_customerpartner_to_product AS c2p ON c2p.product_id=mp.advance_product_id
        LEFT JOIN tb_sys_margin_performer_apply AS mpa ON mpa.agreement_id=a.id AND mpa.check_result IN (0,1) AND mpa.seller_approval_status IN (0,1)
        LEFT JOIN oc_agreement_common_performer AS acp
            ON acp.agreement_id=a.id
            AND acp.agreement_type=0
            AND acp.product_id=a.product_id
            AND acp.buyer_id=" . intval($customer_id) . "
    WHERE
        a.id=" . intval($id) . "
        AND (a.buyer_id=" . intval($customer_id) . " OR acp.buyer_id=" . intval($customer_id) . ")
        GROUP BY a.id";

        $query = $this->db->query($sql);
        return $query->row;
    }

    public function getMessage($id)
    {
        $sql = "SELECT
        m.*,
        c2c.screenname,
        c2c.is_partner,
        CONCAT(c.firstname,' ',c.lastname) AS fullname
    FROM
        tb_sys_margin_message AS m
        LEFT JOIN oc_customerpartner_to_customer AS c2c ON m.customer_id = c2c.customer_id
        LEFT JOIN oc_customer AS c ON c.customer_id=m.customer_id
    WHERE
        m.margin_agreement_id =" . intval($id);

        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getInfoDetail($id)
    {
        $info = $this->getInfo($id);
        if ($info) {
            $message = $this->getMessage($id);
            $info['message'] = $message;
            return $info;
        } else {
            return [];
        }
    }


    //根据头款订单id 得到尾款产品id
    public function getRestProductByAdvanceOrderId($advance_order_id)
    {
        $sql = "SELECT
      *
    FROM
      tb_sys_margin_process
    WHERE advance_order_id ={$advance_order_id}";

        $query = $this->db->query($sql);
        return $query->row;
    }


    public function editMarginAgreement($data)
    {
        $id = $data['id'];
        $customer_id = intval($this->customer->getId());
        $price = $data['price'];
        $day = $data['day'];
        $num = $data['num'];
        $deposit_per = $data['deposit_per'];
        $money = $data['money'];
        $status = $data['status'];
        $actionother = $data['actionother'];
        $rest_price = round(($price - $deposit_per), 2);
        $set = '';
        if ($actionother == 'reapplied') {
            $set .= 'buyer_ignore=0,';
        }

        $sql = "UPDATE
      tb_sys_margin_agreement
    SET
      price = {$price},
      `day` = {$day},
      num = {$num},
      money = {$money},
      deposit_per = {$deposit_per},
      rest_price = {$rest_price},
      status = {$status},
      {$set}
      update_user = {$customer_id},
      update_time = NOW()
    WHERE id = {$id}
      AND buyer_id = {$customer_id}";
        $this->db->query($sql);


        $margin_agreement_id = intval($data['id']);
        $customer_id = intval($this->customer->getId());
        $message = $data['message'];

        $sql = "INSERT INTO tb_sys_margin_message SET margin_agreement_id = {$margin_agreement_id},
    customer_id = {$customer_id},
    message = '{$message}',
    create_time = NOW()";
        $this->db->query($sql);
    }

    /**
     * @param int $margin_agreement_id
     * @return array
     */
    public function getMarginProcessByMarginId($margin_agreement_id = 0)
    {
        $sql = "SELECT
      mp.*, ma.agreement_id AS ma_aid
    FROM
      tb_sys_margin_process AS mp
      RIGHT JOIN tb_sys_margin_agreement AS ma
        ON mp.margin_id = ma.id
    WHERE ma.id = {$margin_agreement_id}";
        $query = $this->db->query($sql);
        return $query->row;
    }


    //判断 订单是否为保证金订单
    public function isMargin($order_id)
    {
        $sql = "SELECT COUNT(*) AS cnt FROM tb_sys_margin_process WHERE advance_order_id={$order_id}";
        $row = $this->db->query($sql)->row;
        $cnt = intval($row['cnt']);

        $sql = "SELECT COUNT(*) AS cnt FROM tb_sys_margin_order_relation WHERE rest_order_id={$order_id}";
        $row = $this->db->query($sql)->row;
        $cnt += intval($row['cnt']);
        return $cnt;
    }

    //查询buyer的所有涉及的采购订单号
    public function getMarginOrderIdByBuyerId($buyer_id)
    {
        $sql = "SELECT
                  mp.`advance_order_id`,
                  mor.`rest_order_id`
                FROM
                  tb_sys_margin_agreement ma
                  LEFT JOIN tb_sys_margin_process mp
                    ON ma.`id` = mp.`margin_id`
                  LEFT JOIN tb_sys_margin_order_relation mor
                    ON mp.`id` = mor.`margin_process_id`
                WHERE ma.`buyer_id` = " . (int)$buyer_id;

        return $this->db->query($sql)->rows;
    }

    /**
     * [marginDealAfterPay description] 保证金产品头款尾款的
     * @param array $orderInfo
     * @param int $customerCountryId
     * @return bool
     * @throws Exception
     */
    public function marginDealAfterPay($orderInfo, $customerCountryId)
    {
        $orderId = $orderInfo['order_id'];
        $buyerId = intval($orderInfo['customer_id']);

        $orderProducts = OrderProduct::query()
            ->with(['customerPartnerToProduct'])
            ->where('order_id', $orderId)
            ->where('type_id', '<>', ProductTransactionType::FUTURE)
            ->get();
        $margin_seller_array = configDB('config_customer_group_ignore_check', []);
        foreach ($orderProducts as $orderProduct) {
            $productId = $orderProduct->product_id;
            //根据 product_id 判断是否在保证金店铺，历史保证金历史数据需要走原逻辑
            //--------------------------------------------------------------------------
            $productSellerId = $orderProduct->customerPartnerToProduct->customer_id;
            $marginPreFlag = in_array($productSellerId,$margin_seller_array);// 历史保证金尾款数据
            $marginProcess = null;
            if ($orderProduct->type_id == ProductTransactionType::MARGIN) {
                // 新版现货
                $marginProcess = MarginProcess::query()
                    ->where('margin_id', '=', $orderProduct->agreement_id)
                    ->where(function ($query) use ($productId) {
                        $query->where('advance_product_id', '=', $productId)
                            ->orWhere('rest_product_id', '=', $productId);
                    })
                    ->first();
            } elseif ($marginPreFlag == true) {
                // 老版现货
                $marginProcess = MarginProcess::query()
                    ->where('rest_product_id', '=', $productId)
                    ->first();
            }
            if ($marginProcess) {
                $btsQuery = app(BuyerToSellerRepository::class)->getBuyerToSeller($buyerId, $productSellerId);
                $buyerToSellerData = [];// 需要更新buyer to seller表的数据
                $now = Carbon::now()->toDateTimeString();
                $marginAgreementId = $marginProcess->margin_id;
                // 订单对应的协议
                $marginAgreement = MarginAgreement::find($marginAgreementId);
                if ($marginProcess->advance_product_id == $productId) {
                    //头款
                    //--------------------------------------------------------------------------
                    //更新保证金协议生效时间、失效时间
                    //--------------------------------------------------------------------------
                    //失效时间为对应国别的23时59分59秒
                    $expire_time = Carbon::now()->addDays($marginAgreement->day)
                        ->setTimezone(CountryHelper::getTimezone($customerCountryId))->setTime(23, 59, 59)
                        ->setTimezone(CountryHelper::getTimezone(Country::AMERICAN))
                        ->toDateTimeString();

                    $marginAgreement->update([
                        'status' => MarginAgreementStatus::SOLD,// 付款完成后的变化, Agreement Status = Sold
                        'effect_time' => $now,//更新有效日期
                        'expire_time' => $expire_time,
                        'update_time' => $now
                    ]);

                    //现货四期，记录协议状态变更
                    app(MarginService::class)->insertMarginAgreementLog([
                        'from_status' => MarginAgreementStatus::APPROVED,
                        'to_status' => MarginAgreementStatus::SOLD,
                        'agreement_id' => $marginAgreementId,
                        'log_type' => MarginAgreementLogType::APPROVED_TO_TO_BE_PAID,
                        'operator' => customer()->getNickName(),
                        'customer_id' => customer()->getId(),
                    ]);

                    //--------------------------------------------------------------------------
                    //查询保证金协议
                    //--------------------------------------------------------------------------
                    $restProductId = $marginAgreement->product_id;
                    //--------------------------------------------------------------------------
                    // 更新尾款，更新tb_sys_margin_process
                    //--------------------------------------------------------------------------
                    $marginProcess->update([
                        'advance_order_id' => $orderId,
                        'rest_product_id' => $restProductId,
                        'process_status' => MarginProcessStatus::ADVANCE_PRODUCT_SUCCESS,
                        'update_time' => $now
                    ]);

                    if ($btsQuery) {
                        //取最新的时间
                        $last_transaction_time = $orderInfo['date_added'] ?? $btsQuery->last_transaction_time ?? date("Y-m-d H:i:s");
                        if ($last_transaction_time < $btsQuery->last_transaction_time) {
                            $last_transaction_time = $btsQuery->last_transaction_time;
                        }
                        $number_of_transaction = ($orderProduct->price + $orderProduct->service_fee_per) * $orderProduct->quantity;
                        $buyerToSellerData = [
                            'number_of_transaction' => new Expression('number_of_transaction + 1'),
                            'money_of_transaction' => new Expression("money_of_transaction + {$number_of_transaction}"),
                            'last_transaction_time' => $last_transaction_time,
                        ];
                    }
                    //下架头款产品
                    Product::query()
                        ->where('product_id', $productId)
                        ->update([
                            'status' => ProductStatus::OFF_SALE,
                            'is_deleted' => YesNoEnum::YES
                        ]);
                    // 低库存提醒
                    /** @var ModelCheckoutOrder $modelCheckoutOrder */
                    $modelCheckoutOrder = load()->model('checkout/order');
                    // 低库存提醒
                    $modelCheckoutOrder->addSystemMessageAboutProductStock($restProductId);
                    // 期货二期 交割方式为转现货保证金，Buyer支付转现货保证金后，平台退还Seller支付的保证金
                    $this->sellerBackFutureMargin($marginAgreement['id'], $customerCountryId);
                } elseif ($marginProcess->rest_product_id == $productId) {
                    //尾款
                    // 待插入订单关联表的数据
                    $marginOrderRelationData = [
                        'margin_process_id' => $marginProcess->id,
                        'rest_order_id' => $orderId,
                        'purchase_quantity' => $orderProduct->quantity,
                        'create_time' => $now,
                        'create_username' => $buyerId
                    ];
                    // 需要更新process的数据
                    $marginProcessData = [
                        'process_status' => MarginProcessStatus::TO_BE_PAID,
                        'update_time' => $now,
                        'update_username' => $buyerId
                    ];
                    $restProductId = $marginProcess->rest_product_id;
                    if ($marginPreFlag) {
                        // 旧的现货
                        // 查询上架库存和已经完成的库存
                        $restQty = intval(Product::query()
                            ->where('product_id', '=', $restProductId)
                            ->value('quantity'));
                        $tail_qty = intval(MarginOrderRelation::query()
                            ->where('margin_process_id', '=', $marginProcess->id)
                            ->sum('purchase_quantity'));
                        if ($restQty === 0 && $tail_qty >= $marginAgreement->num) {
                            //所有尾款商品销售完成
                            // 协议状态更新为完成
                            $marginProcessData['process_status'] = MarginProcessStatus::COMPLETED;
                            // 商品下架
                            Product::query()
                                ->where('product_id', $restProductId)
                                ->update([
                                    'status' => ProductStatus::OFF_SALE,
                                ]);
                        }
                    } else {
                        // 新版需要多记录product_id
                        $marginOrderRelationData['product_id'] = $restProductId;
                        // 验证保证金产品是否已经全部卖完了
                        $productLock = ProductLock::query()
                            ->where('agreement_id', $marginAgreementId)
                            ->where('type_id', ProductLockType::MARGIN)
                            ->first();
                        if ($productLock && $productLock->qty == 0) {
                            // 查询 oc_order 不是完成状态的
                            // 统计rma已完成的quantity
                            $havingStockList = OrderProduct::query()->alias('oop')
                                ->leftJoinRelations('order as oo')
                                ->where('oop.agreement_id', '=', $marginAgreementId)
                                ->where('oop.type_id', '=', ProductTransactionType::MARGIN)
                                ->where('oop.product_id', '=', $productId)
                                ->whereIn('oo.order_status_id', [OcOrderStatus::COMPLETED, OcOrderStatus::CHARGEBACK])
                                ->get(['oop.quantity', 'oop.order_id', 'oop.order_product_id'])
                                ->toArray();
                            /** @var ModelAccountCustomerpartner $modelAccountCustomerPartner */
                            $modelAccountCustomerPartner = load()->model('account/customerpartner');
                            $havingStockQty = 0;
                            $rmaQty = 0;
                            foreach ($havingStockList as $vl) {
                                $s = $modelAccountCustomerPartner->getRmaStockQtyByOrderId($vl['order_id'], $vl['order_product_id']);
                                $rmaQty += $s;
                                $havingStockQty += $vl['quantity'];
                            }
                            $originQty = $productLock->set_qty > 0 ? $productLock->origin_qty / $productLock->set_qty : 0;
                            if ($originQty <= $havingStockQty - $rmaQty) {// 等于换成了小于等于，防止超卖后就无法完成
                                $marginProcessData['process_status'] = MarginProcessStatus::COMPLETED;
                                //现货四期，记录协议状态变更
                                app(MarginService::class)->insertMarginAgreementLog([
                                    'from_status' => MarginAgreementStatus::SOLD,
                                    'to_status' => MarginAgreementStatus::COMPLETED,
                                    'agreement_id' => $marginAgreementId,
                                    'log_type' => MarginAgreementLogType::TO_BE_PAID_TO_COMPLETED,
                                    'operator' => customer()->getNickName(),
                                    'customer_id' => customer()->getId(),
                                ]);
                            }
                        }
                    }
                    $marginProcess->update($marginProcessData);
                    MarginOrderRelation::insert($marginOrderRelationData);
                    if ($marginProcessData['process_status'] === MarginProcessStatus::COMPLETED) {
                        // 更新状态
                        $marginAgreement->update([
                            'status' => MarginAgreementStatus::COMPLETED,
                            'update_time' => $now,
                        ]);
                        // 发送站内信,单独捕获异常，发送站内信不能影响支付
                        try {
                            $this->sendMarginCompleteToSeller($marginAgreement->toArray());
                            $this->sendMarginCompleteToBuyer($marginAgreement->toArray());
                        } catch (Exception $e) {
                            Logger::app($e->getMessage());
                        }
                    }
                    if ($btsQuery) {
                        $number_of_transaction = ($orderProduct->price + $orderProduct->service_fee_per) * $orderProduct->quantity;
                        $buyerToSellerData = [
                            'money_of_transaction' => new Expression("money_of_transaction + {$number_of_transaction}"),
                        ];
                    }
                }
                // 更新buyer_to_seller数据
                if ($btsQuery && !empty($buyerToSellerData)) {
                    $btsQuery->update($buyerToSellerData);
                }
            }
        }

        return true;
    }

    /**
     * 期货二期 交割方式为转现货保证金，Buyer支付转现货保证金后，平台退还Seller支付的保证金
     * @param int $marginAgreementId
     * @param int $countryId
     * @throws Exception
     */
    private function sellerBackFutureMargin(int $marginAgreementId, $countryId)
    {
        $futureId = $this->isFuturesToMargin($marginAgreementId);
        if (!$futureId) {
            return;
        }

        $futuresMarginAgreement = $this->orm->table('oc_futures_margin_agreement')->where('id', $futureId)->first();
        if ($futuresMarginAgreement->contract_id == 0) {
            return;
        }
        if ($futuresMarginAgreement->version == FuturesVersion::VERSION) {
            return;
        }

        $this->load->model('futures/contract');
        $contracts = $this->model_futures_contract->firstPayRecordContracts($futuresMarginAgreement->seller_id, [$futuresMarginAgreement->contract_id]);
        if (empty($contracts)) {
            return;
        }

        $amount = round($futuresMarginAgreement->unit_price * $futuresMarginAgreement->seller_payment_ratio * 0.01, $countryId == JAPAN_COUNTRY_ID ? 0 : 2) * $futuresMarginAgreement->num;
        $futuresMarginAgreementPayType = $contracts[0]['pay_type'];

        $billStatus = 0;
        if ($futuresMarginAgreementPayType == 1) {
            credit::insertCreditBill($futuresMarginAgreement->seller_id, $amount, 2);
            $billStatus = 1;
        }

        agreementMargin::sellerBackFutureMargin($futuresMarginAgreement->seller_id, $futuresMarginAgreement->id, $amount, $futuresMarginAgreementPayType, $billStatus);
    }

    /**
     * 是否为保证金头款商品
     * @param int $product_id
     * @return int
     */
    public function isMarginAdvanceProduct($product_id)
    {
        $sql = "SELECT * FROM tb_sys_margin_process WHERE advance_product_id={$product_id}";
        $query = $this->db->query($sql);
        return $query->num_rows;
    }


    /**
     * 保证金协议头款，付款后
     *
     * @deprecated 不要用这个了，用 $this->marginDealAfterPay()
     * @param $order_info
     * @return bool
     * @throws Exception
     */
    public function afterPay($order_info)
    {
        $order_id = $order_info['order_id'];

        $sql = "SELECT * FROM oc_order_product WHERE order_id={$order_id}";
        $query = $this->db->query($sql);
        $orderProducts = $query->rows;
        foreach ($orderProducts as $orderProduct) {
            $product_id = $orderProduct['product_id'];


            //$marginAdvanceNum = $this->db->query(" select count(1) as num from tb_sys_margin_process where advance_product_id =".$orderProduct['product_id'])->row;
            //$marginRestNum = $this->db->query(" select count(1) as num from tb_sys_margin_process where rest_product_id =".$orderProduct['product_id'])->row;

            $sql = "SELECT * FROM tb_sys_margin_process WHERE advance_product_id ={$product_id} OR rest_product_id={$product_id}";
            $query = $this->db->query($sql);
            $marginProcess = $query->row;
            if ($marginProcess) {
                if ($marginProcess['advance_product_id'] == $product_id) {
                    //是保证金协议的订单
                    $ret = $this->processAvdanceProduct($order_info, $marginProcess);
                    if ($ret === false) {
                        throw new \Exception($this->session->data['orderMarginStock']);
                        //return $ret;
                    } else {
                        //订金商品购买完成，需要下架
                        $sql = "UPDATE oc_product SET `status` = 0 WHERE product_id={$product_id}";
                        $this->db->query($sql);
                    }
                }
                if ($marginProcess['rest_product_id'] == $product_id) {
                    //订单产品表
                    //if 保证金尾款产品  tb_sys_margin_process.rest_product_id
                    //验证所有尾款产品是否售完
                    //记录尾款订单关联
                    $ret = $this->processRestProduct($order_info, $marginProcess, $orderProduct);
                    if ($ret === false) {
                        throw new \Exception("记录尾款订单关联 失败");
                        //return $ret;
                    }
                }
            }

        }
        return true;
    }


    /**
     * 购买保证金尾款产品，并处理
     * @deprecated 废弃，逻辑合并到了$this->marginDealAfterPay()内
     * @param $order_info
     * @param $marginProcess
     * @param $orderProduct
     * @return bool
     */
    public function processRestProduct($order_info, $marginProcess, $orderProduct)
    {
        //margin_process表
        //验证尾款产品数量 与 协议中产品数量 关系，更新 margin_process表status
        //记录尾款订单关联
        $order_id = intval($order_info['order_id']);
        $buyer_id = intval($order_info['customer_id']);
        $rest_product_id = intval($marginProcess['rest_product_id']);
        $margin_process_id = intval($marginProcess['id']);
        $margin_agreement_id = intval($marginProcess['margin_id']);
        $quantity = intval($orderProduct['quantity']);//采购数量
        //-----------------------------------------------------------------------------------------
        // 查询tb_sys_margin_agreement 信息
        //-----------------------------------------------------------------------------------------
        $sql = "SELECT ";
        $sql .= "* FROM tb_sys_margin_agreement WHERE id={$margin_agreement_id}";
        $query = $this->db->query($sql);
        $marginAgreement = $query->row;
        //-----------------------------------------------------------------------------------------
        // 记录尾款订单关联
        //-----------------------------------------------------------------------------------------
        $sql = "INSERT ";
        $sql .= "INTO tb_sys_margin_order_relation
            SET margin_process_id={$margin_process_id},
            rest_order_id={$order_id},
            purchase_quantity={$quantity},
            create_time=NOW(),
            create_username='{$buyer_id}'";
        $this->db->query($sql);

        /**
         * N-84
         * 添加 buyer 和seller的交易信息(oc_buyer_to_seller)
         * 注：尾款只算金额，不算次数和交易时间
         */
        $btsSql = "SELECT ";
        $btsSql .= "id FROM oc_buyer_to_seller  WHERE buyer_id = {$marginAgreement['buyer_id']}  AND seller_id={$marginAgreement['seller_id']} limit 1";
        $btsQuery = $this->db->query($btsSql);
        if ($btsQuery->num_rows > 0) {
            $orderProductSql = "select ";
            $orderProductSql .= "price,service_fee_per,quantity from oc_order_product where order_id={$order_id} and product_id={$rest_product_id} limit 1";
            $orderProductQuery = $this->db->query($orderProductSql);
            if ($orderProductQuery->num_rows > 0) {
                $op = $orderProductQuery->row;
                $number_of_transaction = ($op['price'] + $op['service_fee_per']) * $op['quantity'];
                $btsUpdateSql = "update ";
                $btsUpdateSql .= "`oc_buyer_to_seller`
                      set `money_of_transaction` = `money_of_transaction`+{$number_of_transaction}
                      where `id` = {$btsQuery->row['id']} limit 1";
                $this->db->query($btsUpdateSql);
            }
        }
        // End of N-84 by lester.you
        //-----------------------------------------------------------------------------------------
        // 查询上架库存和已经完成的库存
        //-----------------------------------------------------------------------------------------
        $sql = "SELECT ";
        $sql .= "quantity FROM oc_product WHERE product_id = {$rest_product_id}";
        $rest_qty = intval($this->db->query($sql)->row['quantity']);
        $sql = "SELECT ";
        $sql .= "SUM(mor.`purchase_quantity`) AS tail_qty
              FROM `tb_sys_margin_order_relation` mor
              WHERE mor.`margin_process_id` = {$margin_process_id}";
        $tail_qty = intval($this->db->query($sql)->row['tail_qty']);

        if ($rest_qty === 0 && $tail_qty >= $marginAgreement['num']) {
            //所有尾款商品销售完成
            $sql_complete = "UPDATE ";
            $sql_complete .= "tb_sys_margin_agreement
                            SET
                              `status` = 8,
                              update_time = NOW()
                            WHERE id = {$margin_agreement_id}";
            $this->db->query($sql_complete);
            // 商品下架
            $sql_complete = "UPDATE ";
            $sql_complete .= "oc_product
                            SET
                              `status` = 0
                            WHERE product_id = {$rest_product_id}";
            $this->db->query($sql_complete);
            $sql = "UPDATE ";
            $sql .= "tb_sys_margin_process
                    SET
                      process_status = 4,
                      update_time = NOW(),
                      update_username = '{$buyer_id}'
                    WHERE id ={$margin_process_id}";

            //-----------------------------------------------------------------------------------------
            // 发送站内信,单独捕获异常，发送站内信不能影响支付
            //-----------------------------------------------------------------------------------------
            try {
                $this->sendMarginCompleteToSeller($marginAgreement);
                $this->sendMarginCompleteToBuyer($marginAgreement);
            } catch (Exception $e) {
                $this->log->write($e->getMessage());
            }

        } else {
            //-----------------------------------------------------------------------------------------
            // 尾款商品支付分销中
            //-----------------------------------------------------------------------------------------
            $sql = "UPDATE ";
            $sql .= "tb_sys_margin_process
                    SET
                      process_status = 3,
                      update_time = NOW(),
                      update_username = '{$buyer_id}'
                    WHERE id ={$margin_process_id}";
        }
        //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
        $this->db->query($sql);

        return true;
    }


    /**
     * 购买保证金头款产品，并处理
     * @param array $order_info oc_order
     * @param
     * @return bool
     */
    public function processAvdanceProduct($order_info, $marginProcess)
    {
//        1.复制尾款产品（80%价格）2.batch调货,oc_product修改上架数量，oc_customerpartner_to_product上架数量3.tb_sys_margin_process，tb_underwriting_shop_product_mapping，oc_delicacy_management,oc_buyer_to_seller

        $order_id = $order_info['order_id'];


        $margin_agreement_id = intval($marginProcess['margin_id']);
        $buyer_id = intval($order_info['customer_id']);


        //付款完成后的变化, Agreement Status = Sold
        $sql = "UPDATE
  tb_sys_margin_agreement
SET
  `status` = 6,
  update_time = NOW()
WHERE id = {$margin_agreement_id}";
        $this->db->query($sql);

        $sql = "SELECT * FROM tb_sys_margin_agreement WHERE id={$margin_agreement_id}";
        //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
        $query = $this->db->query($sql);
        $marginAgreement = $query->row;//订单对应的协议
        $seller_id = intval($marginAgreement['seller_id']);
        $product_id = intval($marginAgreement['product_id']);

        $day = intval($marginAgreement['day']);

        $effect_time = date('Y-m-d H:i:s', time());
        $expire_time = date('Y-m-d 23:59:59', strtotime("+" . $day . " day"));

        /**
         * N-84
         * 添加 buyer 和seller的交易信息(oc_buyer_to_seller)
         */
        $btsSql = "SELECT id,last_transaction_time FROM oc_buyer_to_seller  WHERE buyer_id = {$marginAgreement['buyer_id']}  AND seller_id={$marginAgreement['seller_id']} limit 1";
        $btsQuery = $this->db->query($btsSql);
        if ($btsQuery->num_rows > 0) {
            $orderSql = "select date_added from oc_order where order_id={$order_id} limit 1";
            $orderQuery = $this->db->query($orderSql);
            $orderProductSql = "select price,service_fee_per,quantity from oc_order_product where order_id={$order_id} and product_id={$marginProcess['advance_product_id']} limit 1";
            $orderProductQuery = $this->db->query($orderProductSql);
            if ($orderProductQuery->num_rows > 0) {
                $op = $orderProductQuery->row;
                //取最新的时间
                $last_transaction_time = $orderQuery->row['date_added'] ?? $btsQuery->row['last_transaction_time'] ?? date("Y-m-d H:i:s");
                if ($last_transaction_time < $btsQuery->row['last_transaction_time']) {
                    $last_transaction_time = $btsQuery->row['last_transaction_time'];
                }
                $number_of_transaction = ($op['price'] + $op['service_fee_per']) * $op['quantity'];
                $btsUpdateSql = "update oc_buyer_to_seller
set number_of_transaction=number_of_transaction+1,
money_of_transaction = money_of_transaction +{$number_of_transaction} ,
last_transaction_time = '{$last_transaction_time}'
where id = {$btsQuery->row['id']} limit 1";
                $this->db->query($btsUpdateSql);
            }
        }
        // End of N-84 by lester.you


        //判断产品为内部店铺还是外部店铺
        $sql = "SELECT * FROM oc_customer WHERE customer_id={$seller_id}";
        //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
        $query = $this->db->query($sql);
        $seller = $query->row;
        $country_id = intval($seller['country_id']);//81 Germany, 107 Japan, 222 UK, 223 US
        $accounting_type = intval($seller['accounting_type']);//1 内部, 2 外部
        //DX_B@oristand.com              908
        //nxb@gigacloudlogistics.com     746
        //UX_B@oristand.com              907
        //bxo@gigacloudlogistics.com     696
        //bxw@gigacloudlogistics.com     694
        $seller_id_new = 0;
        switch ($country_id) {
            case 81:
                $sql = "SELECT * FROM oc_customer WHERE email='DX_B@oristand.com' AND status=1";
                break;
            case 107:
                $sql = "SELECT * FROM oc_customer WHERE email='nxb@gigacloudlogistics.com' AND status=1";
                break;
            case 222:
                $sql = "SELECT * FROM oc_customer WHERE email='UX_B@oristand.com' AND status=1";
                break;
            case 223:
                if ($accounting_type == 1) {
                    $sql = "SELECT * FROM oc_customer WHERE email='bxo@gigacloudlogistics.com' AND status=1";
                } elseif ($accounting_type == 2) {
                    $sql = "SELECT * FROM oc_customer WHERE email='bxw@gigacloudlogistics.com' AND status=1";
                } else {
                    //账号类型 Null
                    //库存不足
                    session()->set('orderMarginStock', 'notFull保证金调货-账号类型有误.File=' . __FILE__ . '.line=' . __LINE__);
                    return false;
                }
                break;
            default:
                //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . '店铺不存在', FILE_APPEND);//todo log
                //库存不足
                session()->set('orderMarginStock', 'notFull保证金调货-国家不对.File=' . __FILE__ . '.line=' . __LINE__);
                return false;
                break;
        }
        //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
        $query = $this->db->query($sql);
        $seller_new = $query->row;
        $seller_id_new = intval($seller_new['customer_id']);


        //判断此Buyer是否与保证金店铺建立管理，若未建立关联，则自动建立关联
        $sql = "SELECT * FROM oc_buyer_to_seller WHERE buyer_id={$buyer_id} AND seller_id={$seller_id_new}";
        $query = $this->db->query($sql);
        $buyer2seller = $query->row;
        if (!$buyer2seller) {
            //$this->load->model('customer/customer');
            //$this->model_customer_customer->addSellersToSeller($buyer_id, [$seller_id]);
            $sql = "INSERT INTO `" . DB_PREFIX . "buyer_to_seller` (
  buyer_id,
  seller_id,
  buy_status,
  price_status,
  buyer_control_status,
  seller_control_status,
  discount
)
SELECT
  " . $buyer_id . ",
  customer_id,
  1,
  1,
  1,
  1,
  1
FROM
  `oc_customer` c
WHERE c.`customer_id`=" . $seller_id_new;
            //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
            $this->db->query($sql);
        } else {
            $sql = "UPDATE
  oc_buyer_to_seller
SET
  buy_status = 1,
  price_status = 1,
  buyer_control_status = 1,
  seller_control_status = 1
WHERE id =" . $buyer2seller['id'];
            //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
            $this->db->query($sql);
        }


        // 保证金协议中原有的产品
        $sql = "SELECT * FROM oc_product WHERE product_id={$product_id}";
        $query = $this->db->query($sql);
        $product_info = $query->row;
        $sku = $product_info['sku'];
        $combo_flag = $product_info['combo_flag'];
        //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log


        //通知在库系统
        $sql = "SELECT
  ocm.*,
  CONCAT(
    oc.`firstname`,
    ' ',
    oc.`lastname`
  ) AS `customer_name`,
  country.`iso_code_2` AS country,
  IF(os2s.`id`, 1, 0) AS inside_flag
FROM
  tb_sys_b2b_osj_customer_map AS ocm
  LEFT JOIN oc_customer AS oc
    ON ocm.`b2b_customer_id` = oc.`customer_id`
  LEFT JOIN tb_sys_outer_storeid_to_sellerid AS os2s
    ON ocm.`b2b_customer_id` = os2s.`seller_id`
  LEFT JOIN oc_country AS country
    ON oc.`country_id` = country.`country_id`
WHERE ocm.`status` = 1
  AND (
    ocm.b2b_customer_id = {$seller_id}
    OR ocm.b2b_customer_id = {$seller_id_new}
  )";
        //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
        $query = $this->db->query($sql);
        $tmp = $query->rows;
        $b2b_osj_customer = [];
        $toWareParam = [];
        $toWareParamTmp = [];
        if ($tmp) {
            foreach ($tmp as $key => $value) {
                $b2b_osj_customer[$value['b2b_customer_id']] = $value;
            }
            $toWareParamTmp['BUYER_ID'] = isset($b2b_osj_customer[$seller_id_new]) ? $b2b_osj_customer[$seller_id_new]['osj_customer_id'] : 0;
            $toWareParamTmp['BUYER_NAME'] = isset($b2b_osj_customer[$seller_id_new]) ? $b2b_osj_customer[$seller_id_new]['customer_name'] : '';
            $toWareParamTmp['SELLER_ID'] = isset($b2b_osj_customer[$seller_id]) ? $b2b_osj_customer[$seller_id]['osj_customer_id'] : 0;
            $toWareParamTmp['SELLER_NAME'] = isset($b2b_osj_customer[$seller_id]) ? $b2b_osj_customer[$seller_id]['customer_name'] : '';
            $toWareParamTmp['OSJ_STORE_ID'] = isset($b2b_osj_customer[$seller_id]) ? $b2b_osj_customer[$seller_id]['osj_customer_id'] : 0;//调出者
            $toWareParamTmp['OSJ_STORE_CODE'] = isset($b2b_osj_customer[$seller_id]) ? $b2b_osj_customer[$seller_id]['osj_customer_code'] : 0;
            $toWareParamTmp['COUNTRY'] = isset($b2b_osj_customer[$seller_id_new]) ? $b2b_osj_customer[$seller_id_new]['country'] : '';
            $toWareParamTmp['FLAG'] = isset($b2b_osj_customer[$seller_id_new]) ? $b2b_osj_customer[$seller_id_new]['inside_flag'] : '';
            $toWareParamTmp['BUY_DATE'] = $effect_time;
            $toWareParamTmp['BX_FLAG'] = 1;//包销店铺，要传1
            //SKU_CODE
            //ORIGINAL_QTY
            //PRICE
        }


        //基础价格设置到最大，仅仅可购买的商品buyer通过精细化设置真正的价格
        $no_margin_max_price = 9999999;
        //复制产品
        $this->load->model('catalog/product');
        if ($combo_flag) {
            //组合产品
            $num = $marginAgreement['num'];
            $priceNew = round($marginAgreement['price'] - $marginAgreement['deposit_per'], 2);
            $param = [
                'product_id' => $product_id,
                'num' => $num,
                'price' => $no_margin_max_price,
                'seller_id' => $seller_id_new
            ];
            $this->load->model('catalog/product');
            $product_id_new = $this->model_catalog_product->copyProductMargin($product_id, $param);//新的组合产品id


            //c2p
            $sql = "SELECT * FROM oc_customerpartner_to_product WHERE customer_id={$seller_id_new} AND product_id={$product_id_new}";
            $query = $this->db->query($sql);
            //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
            $c2p = $query->row;
            if (!$c2p) {
                $sql = "INSERT INTO oc_customerpartner_to_product SET customer_id = {$seller_id_new},
product_id = {$product_id_new},
price = {$no_margin_max_price},
seller_price = {$no_margin_max_price},
quantity = {$num}";
                //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
                $this->db->query($sql);
            }
            //product_mapping
            $sql = "INSERT INTO tb_underwriting_shop_product_mapping SET underwriting_seller_id = {$seller_id_new},
original_seller_id = {$seller_id},
item_code = '{$sku}',
underwriting_product_id = {$product_id_new},
original_product_id = {$product_id},
create_time = NOW()";
            //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
            $this->db->query($sql);
            //tag
            $sql = "SELECT * FROM oc_product_to_tag WHERE product_id={$product_id}";
            //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
            $query = $this->db->query($sql);
            $product_to_tags = $query->rows;
            if (!empty($product_to_tags)) {
                foreach ($product_to_tags as $product_to_tag) {
                    $is_sync_tag = ($product_to_tag['tag_id'] == 1) ? 0 : $product_to_tag['is_sync_tag'];
                    $product_to_tag['product_id'] = $product_id_new;
                    $product_to_tag['is_sync_tag'] = $is_sync_tag;
                    $product_to_tag['create_user_name'] = $seller_id_new;
                    $product_to_tag['update_user_name'] = $seller_id_new;
                    $product_to_tag['create_time'] = $effect_time;
                    $product_to_tag['program_code'] = 'MARGIN';
                    $this->orm->table('oc_product_to_tag')->insert($product_to_tag);
                }
            }


            //新产品设置精细化
            $input = [
                'seller_id' => $seller_id_new,
                'buyer_id' => $buyer_id,
                'product_id' => $product_id_new,
                'delicacy_price' => $priceNew,
                'effective_time' => $effect_time,
                'expiration_time' => $expire_time,
            ];
            $this->load->model('customerpartner/DelicacyManagement');
            $this->model_customerpartner_DelicacyManagement->addOrUpdate($input);


            //子产品
            $sql = "SELECT * FROM tb_sys_product_set_info WHERE product_id={$product_id}";
            //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
            $query = $this->db->query($sql);
            $product_set_list = $query->rows;
            foreach ($product_set_list as $key => $set_product) {

                $set_product_id = $set_product['set_product_id'];
                $set_num = bcmul(intval($set_product['qty']), $num);


                //复制 子产品
                $param = [
                    'product_id' => $set_product_id,
                    'num' => $set_num,
                    'seller_id' => $seller_id_new
                ];
                //新的子产品
                $set_product_id_new = $this->model_catalog_product->copyProductMargin($set_product_id, $param);//新的子产品id
                $sql = "SELECT * FROM oc_product WHERE product_id={$set_product_id_new}";
                //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
                $query = $this->db->query($sql);
                $set_product_info = $query->row;
                if ($set_product_info['status'] == 0) {
                    //保证金的子sku必须是上架的
                    $sql = "UPDATE oc_product set status = 1 WHERE product_id={$set_product_id_new}";
                    $this->db->query($sql);
                }
                $set_priceNew = $set_product_info['price'];
                $set_sku = $set_product_info['sku'];


                //c2p
                $sql = "SELECT * FROM oc_customerpartner_to_product WHERE customer_id={$seller_id_new} AND product_id={$set_product_id_new}";
                //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
                $query = $this->db->query($sql);
                $c2p = $query->row;
                if (!$c2p) {
                    $sql = "INSERT INTO oc_customerpartner_to_product SET customer_id = {$seller_id_new},
product_id = {$set_product_id_new},
price = {$set_priceNew},
seller_price = {$set_priceNew},
quantity = {$set_num}";
                    //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
                    $this->db->query($sql);
                }
                //product_mapping
                $sql = "INSERT INTO tb_underwriting_shop_product_mapping SET underwriting_seller_id = {$seller_id_new},
original_seller_id = {$seller_id},
item_code = '{$set_sku}',
underwriting_product_id = {$set_product_id_new},
original_product_id = {$set_product_id},
create_time = NOW()";
                //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
                $this->db->query($sql);
                //tag
                $sql = "SELECT * FROM oc_product_to_tag WHERE product_id={$set_product_id}";
                $query = $this->db->query($sql);
                $product_to_tags = $query->rows;
                if (!empty($product_to_tags)) {
                    foreach ($product_to_tags as $product_to_tag) {
                        $is_sync_tag = ($product_to_tag['tag_id'] == 1) ? 0 : $product_to_tag['is_sync_tag'];
                        $product_to_tag['product_id'] = $set_product_id_new;
                        $product_to_tag['is_sync_tag'] = $is_sync_tag;
                        $product_to_tag['create_user_name'] = $seller_id_new;
                        $product_to_tag['update_user_name'] = $seller_id_new;
                        $product_to_tag['create_time'] = $effect_time;
                        $product_to_tag['program_code'] = 'MARGIN';
                        $this->orm->table('oc_product_to_tag')->insert($product_to_tag);
                    }
                }


                //新产品设置精细化
                $input = [
                    'seller_id' => $seller_id_new,
                    'buyer_id' => $buyer_id,
                    'product_id' => $set_product_id_new,
                    'delicacy_price' => $set_priceNew,
                    'effective_time' => $effect_time,
                    'expiration_time' => $expire_time,
                ];
                $this->load->model('customerpartner/DelicacyManagement');
                $this->model_customerpartner_DelicacyManagement->addOrUpdate($input);


                $preDeliverySql = "select dpl.*,op.sku,op.mpn,op.danger_flag from tb_sys_seller_delivery_pre_line dpl
                                    left join oc_order_product oop on dpl.order_product_id = oop.order_product_id
                                    left join oc_product op on op.product_id = dpl.product_id
                                    where oop.order_id = {$order_id} and oop.product_id = {$marginProcess['advance_product_id']} and type=2 and dpl.product_id = {$set_product_id}";
                //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $preDeliverySql, FILE_APPEND);//todo log
                $preDeliveryInfos = $this->db->query($preDeliverySql)->rows;
                foreach ($preDeliveryInfos as $preDeliveryInfo) {
                    $toWareParamTmp['B2B_COST_ID'] = $this->batchOutAndIn($preDeliveryInfo, $preDeliveryInfo['qty'], $set_product_id_new, $seller_id_new);//处理调出单、调入单、调货日志
                    $toWareParamTmp['SKU_CODE'] = $set_sku;
                    $toWareParamTmp['ORIGINAL_QTY'] = $preDeliveryInfo['qty'];
                    $toWareParamTmp['PRICE'] = $set_priceNew;
                    $toWareParam[] = $toWareParamTmp;
                }


                //组合商品关系表
                $keyVal = $set_product;
                unset($keyVal['id']);
                $keyVal['seller_id'] = $seller_id_new;
                $keyVal['product_id'] = $product_id_new;
                $keyVal['set_product_id'] = $set_product_id_new;
                $this->orm->table('tb_sys_product_set_info')->insert($keyVal);
            }
        } else {
            //非组合产品
            $num = $marginAgreement['num'];
            $priceNew = round($marginAgreement['price'] - $marginAgreement['deposit_per'], 2);
            $param = [
                'product_id' => $product_id,
                'num' => $num,
                'price' => $no_margin_max_price,
                'seller_id' => $seller_id_new
            ];
            $product_id_new = $this->model_catalog_product->copyProductMargin($product_id, $param);

            //c2p
            $sql = "SELECT * FROM oc_customerpartner_to_product WHERE customer_id={$seller_id_new} AND product_id={$product_id_new}";
            //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
            $query = $this->db->query($sql);
            $c2p = $query->row;
            if (!$c2p) {
                $sql = "INSERT INTO oc_customerpartner_to_product SET customer_id = {$seller_id_new},
product_id = {$product_id_new},
price = {$no_margin_max_price},
seller_price = {$no_margin_max_price},
quantity = {$num}";
                //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
                $this->db->query($sql);
            }
            //product_mapping
            $sql = "INSERT INTO tb_underwriting_shop_product_mapping SET underwriting_seller_id = {$seller_id_new},
original_seller_id = {$seller_id},
item_code = '{$sku}',
underwriting_product_id = {$product_id_new},
original_product_id = {$product_id},
create_time = NOW()";
            //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
            $this->db->query($sql);
            //tag
            $sql = "SELECT * FROM oc_product_to_tag WHERE product_id={$product_id}";
            $query = $this->db->query($sql);
            $product_to_tags = $query->rows;
            if (!empty($product_to_tags)) {
                foreach ($product_to_tags as $product_to_tag) {
                    $is_sync_tag = ($product_to_tag['tag_id'] == 1) ? 0 : $product_to_tag['is_sync_tag'];
                    $product_to_tag['product_id'] = $product_id_new;
                    $product_to_tag['is_sync_tag'] = $is_sync_tag;
                    $product_to_tag['create_user_name'] = $seller_id_new;
                    $product_to_tag['update_user_name'] = $seller_id_new;
                    $product_to_tag['create_time'] = $effect_time;
                    $product_to_tag['program_code'] = 'MARGIN';
                    $this->orm->table('oc_product_to_tag')->insert($product_to_tag);
                }
            }


            //新产品设置精细化
            $input = [
                'seller_id' => $seller_id_new,
                'buyer_id' => $buyer_id,
                'product_id' => $product_id_new,
                'delicacy_price' => $priceNew,
                'effective_time' => $effect_time,
                'expiration_time' => $expire_time,
            ];
            $this->load->model('customerpartner/DelicacyManagement');
            $this->model_customerpartner_DelicacyManagement->addOrUpdate($input);


            $preDeliverySql = "select dpl.*,op.sku,op.mpn,op.danger_flag from tb_sys_seller_delivery_pre_line dpl
                                    left join oc_order_product oop on dpl.order_product_id = oop.order_product_id
                                    left join oc_product op on op.product_id = dpl.product_id
                                    where oop.order_id = {$order_id} and oop.product_id = {$marginProcess['advance_product_id']} and type=2 and dpl.product_id = {$product_id}";
            //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $preDeliverySql, FILE_APPEND);//todo log
            $preDeliveryInfos = $this->db->query($preDeliverySql)->rows;
            foreach ($preDeliveryInfos as $preDeliveryInfo) {
                $toWareParamTmp['B2B_COST_ID'] = $this->batchOutAndIn($preDeliveryInfo, $preDeliveryInfo['qty'], $product_id_new, $seller_id_new);//处理调出单、调入单、调货日志
                $toWareParamTmp['SKU_CODE'] = $sku;
                $toWareParamTmp['ORIGINAL_QTY'] = $preDeliveryInfo['qty'];
                $toWareParamTmp['PRICE'] = $priceNew;
                $toWareParam[] = $toWareParamTmp;
            }
        }


        //更新保证金协议生效时间、失效时间
        $sql = "UPDATE
  tb_sys_margin_agreement
SET
  effect_time = '{$effect_time}',
  expire_time = '{$expire_time}',
  update_time = '{$effect_time}'
WHERE id = {$margin_agreement_id}";
        //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
        $this->db->query($sql);


        $sql = "UPDATE
  tb_sys_margin_process
SET
  advance_order_id = {$order_id},
  rest_product_id = {$product_id_new},
  process_status = 2,
  update_time = '{$effect_time}'
WHERE margin_id = {$margin_agreement_id}";
        $this->db->query($sql);
        //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log


        //内部店铺调货，通知在库系统
        if ($accounting_type == 1 && $toWareParam) {
            $this->noticeWare($toWareParam);
        }


        return true;
    }


    //减少原产品数量
    public function decProductQty($product_id, $decName)
    {
        $sql = "UPDATE oc_product SET quantity=quantity-$decName WHERE product_id=$product_id";
        $this->db->query($sql);
        $sql = "UPDATE oc_customerpartner_to_product SET quantity=quantity-$decName WHERE product_id=$product_id";
        $this->db->query($sql);
    }


    /**
     * 处理调出单、调入单、调货日志
     * @param $batch  预减batch批次库存 tb_sys_seller_delivery_pre_line
     * @param $decNum 调货数量
     * @param int $product_id_new 新产品id
     * @param $seler_id_new
     * @return 批次入库
     */
    private function batchOutAndIn($batch, $decNum, $product_id_new, $seler_id_new)
    {
        $batch_id = $batch['batch_id'];
        //增加出库单
        $product_id = $batch['product_id'];
        $seller_id = $batch['seller_id'];
        $warehouse = $batch['warehouse'] ? $batch['warehouse'] : 'Cloud Warehouse';
        $sku = $batch['sku'];
        $mpn = $batch['mpn'];
        $dangerFlag = $batch['danger_flag'] ?? 0;
        $time = date('Y-m-d H:i:s', time());
        $sql = "INSERT INTO tb_sys_seller_delivery_line
    SET product_id={$product_id},
batch_id={$batch_id},
qty={$decNum},
warehouse='{$warehouse}',
seller_id={$seller_id},
Memo='调拨出库 {$time} zhousuyang product_id:{$product_id} -> {$product_id_new}',
CreateTime='{$time}',
UpdateTime='{$time}',
danger_flag={$dangerFlag},
type=3";
        //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
        $this->db->query($sql);
        //增加入库记录
        $sql = "INSERT INTO tb_sys_batch
SET batch_number='TRANSFER',
source_code='B2B_TRANSFER',
receipts_order_id=0,
receipts_order_line_id=0,
sku='{$sku}',
mpn='{$mpn}',
product_id={$product_id_new},
original_qty={$decNum},
onhand_qty={$decNum},
warehouse='{$warehouse}',
remark='调拨入库 {$time} zhousuyang {$product_id} -> {$product_id_new} qty={$decNum}',
customer_id={$seler_id_new},
receive_date='{$time}',
source_batch_id=0,
create_time='$time',
to_transfer=0";
        //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
        $this->db->query($sql);
        $batch_id_new = $this->db->getLastId();
        //记录调库日志
        $sql = "INSERT INTO tb_transfer_batch
SET from_batch_id={$batch_id},
to_batch_id={$batch_id_new},
from_product_id={$product_id},
to_product_id={$product_id_new},
from_customer_id={$seller_id},
to_customer_id={$seler_id_new},
qty={$decNum},
transfer_time='{$time}',
create_user_name='zhousuyang',
create_time='{$time}',
update_time='{$time}'";
        //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
        $this->db->query($sql);


        return $batch_id_new;
    }


    //TODO 通知在库系统
    public function noticeWare($param)
    {
        $postData = [];
        $postData['apiKey'] = OSJ_POST_API_KEY;//
        $postData['postValue'] = json_encode($param);//[{},{}......]


        //在请求在库系统之前，批次库存先更新成2；如果在库系统详情success，则更新成1
        $batch_ids_arr = array_column($param, 'B2B_COST_ID');
        if ($batch_ids_arr) {
            $batch_ids_str = implode(',', $batch_ids_arr);
            if ($batch_ids_str) {
                $sql = "UPDATE
  tb_sys_batch
SET
  to_transfer = 2
WHERE batch_id IN ({$batch_ids_str})";
                $this->db->query($sql);
            }
        }


        $headers = null;
        $opt = ["CURLOPT_TIMEOUT" => 10];


        //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . var_export($param, true), FILE_APPEND);//todo log
        //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $url, FILE_APPEND);//todo log
        //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $postData['apiKey'], FILE_APPEND);//todo log
        //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $postData['postValue'], FILE_APPEND);//todo log

        $ret = post_url(OSJ_POST_URL, $postData, $headers, $opt);//{"REPEAT_IDS":"","FAIL_IDS":"1,","SUCCESS_IDS":""}
        //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $ret, FILE_APPEND);//todo log
        $retArr = json_decode($ret, true);
        if ($retArr === null) {
            return false;
        }

        if (strlen($retArr['SUCCESS_IDS'])) {
            //success
            $batch_ids = trim($retArr['SUCCESS_IDS'], ',');
            if ($batch_ids) {
                $sql = "UPDATE
  tb_sys_batch
SET
  to_transfer = 1
WHERE batch_id IN ({$batch_ids})";
                //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
                $this->db->query($sql);
            }
        } elseif (strlen($retArr['FAIL_IDS'])) {
            //fail
            $batch_ids = trim($retArr['FAIL_IDS'], ',');
            if ($batch_ids) {
                $sql = "UPDATE
  tb_sys_batch
SET
  to_transfer = 2
WHERE batch_id IN ({$batch_ids})";
                //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);//todo log
                $this->db->query($sql);
            }
        }


        return true;
    }

    /**
     * 支付头款时，检查原商品库存数量
     * @param int $margin_agreement_id
     * @return int
     * @throws Exception
     */
    public function checkStockNum($margin_agreement_id)
    {
        $margin_agreement_id = intval($margin_agreement_id);
        $sql = "SELECT * FROM tb_sys_margin_agreement WHERE id={$margin_agreement_id}";
        $query = $this->db->query($sql);
        $marginAgreement = $query->row;//订单对应的协议
        if (!$marginAgreement) {
            return 0;
        }
        $product_id = intval($marginAgreement['product_id']);

        // 保证金协议中原有的产品
        $sql = "SELECT * FROM oc_product WHERE product_id={$product_id}";
        $query = $this->db->query($sql);
        $product_info = $query->row;
        $combo_flag = $product_info['combo_flag'];


        $this->load->model('catalog/product');
        $stock = $this->model_catalog_product->getComboProductAvailableAmount($product_id, $combo_flag, 1);

        return intval($stock);
    }

    public function checkStockNumByCart()
    {
        $returnData = [];
        //购物车
        $products = $this->cart->getProducts();
        $list = [];
        $product_id_arr = [];
        foreach ($products as $value) {
            $list[$value['product_id']] = $value;
            $product_id_arr[] = $value['product_id'];
        }

        if ($products) {
            $product_id_str = implode(',', $product_id_arr);
            if ($product_id_str) {
                $sql = "SELECT
  mp.advance_product_id,
  ma.`product_id`,
  ma.`num`,
  p.`combo_flag`
FROM
  tb_sys_margin_process AS mp
  INNER JOIN tb_sys_margin_agreement AS ma
    ON mp.`margin_id` = ma.`id`
  INNER JOIN oc_product AS p
    ON ma.`product_id` = p.`product_id`
WHERE mp.`advance_product_id` IN ({$product_id_str})";
                $query = $this->db->query($sql);

                if ($query->rows) {

                    $this->load->model('catalog/product');
                    foreach ($query->rows as $key => $value) {
                        //判数量
                        $advance_product_id = $value['advance_product_id'];
                        $product_id = $value['product_id'];
                        $combo_flag = $value['combo_flag'];
                        $num = $value['num'];
                        $stock = $this->model_catalog_product->getComboProductAvailableAmount($product_id, $combo_flag);
                        if ($num > $stock) {
                            //库存不足
                            $returnData[$advance_product_id] = [
                                'stock' => $stock,
                                'name' => $list[$advance_product_id]['name'],
                                'product_id' => $advance_product_id,
                                'sku' => $list[$advance_product_id]['sku']
                            ];
                        }
                    }
                }
            }
        }
        if ($returnData) {
            return ['ret' => 0, 'data' => $returnData];
        } else {
            return ['ret' => 1, 'data' => $returnData];
        }
    }

    /**
     * 查询保证金的商品回调记录
     *
     * @param int $margin_id 保证金协议主键
     * @return array
     */
    public function getMarginDispatchBackRecord($margin_id)
    {
        $sql = "SELECT
                  ma.`margin_id`,
                  ma.`in_seller_id`,
                  ma.`out_seller_id`,
                  mp.`rest_product_id`,
                  p.`sku`,
                  mp.`rest_product_id`,
                  ma.`unaccomplished_num`,
                  ma.`adjust_num`,
                  ma.`adjustment_reason`,
                  ma.`create_time`
                FROM
                  `tb_sys_margin_adjustment` ma
                  LEFT JOIN tb_sys_margin_process mp
                    ON mp.`margin_id` = ma.`margin_id`
                  LEFT JOIN oc_product p
                    ON p.`product_id` = mp.`rest_product_id`
                WHERE ma.`margin_id` = " . (int)$margin_id;
        return $this->db->query($sql)->rows;
    }

    /**
     * 向seller发送订金支付超时的站内信
     *
     * @param $margin_detail tb_sys_margin_agreement表的全体数据字段
     * @throws Exception
     */
    public function sendMarginCompleteToSeller($margin_detail)
    {
        if (empty($margin_detail['agreement_id']) || empty($margin_detail['buyer_id']) || empty($margin_detail['num']) || empty($margin_detail['day'])) {
            return;
        }
        $subject = sprintf('The margin agreement no. %s has been completed.', $margin_detail['agreement_id']);
        $content = sprintf('<div>Fulfilled. The sales volume has reached %s units within %s days as agreed.</div>', $margin_detail['num'], $margin_detail['day']);
        $this->load->model('message/message');
        $this->model_message_message->addSystemMessageToBuyer('bid_margin', $subject, $content, $margin_detail['seller_id']);
    }

    /**
     * 向buyer发送订金支付超时的站内信
     *
     * @param $margin_detail
     * @throws Exception
     */
    public function sendMarginCompleteToBuyer($margin_detail)
    {
        if (empty($margin_detail['agreement_id']) || empty($margin_detail['buyer_id']) || empty($margin_detail['num']) || empty($margin_detail['day'])) {
            return;
        }
        $subject = sprintf('The margin agreement no. %s has been completed.', $margin_detail['agreement_id']);
        $content = sprintf('<div>Fulfilled. The sales volume has reached %s units within %s days as agreed.</div>', $margin_detail['num'], $margin_detail['day']);
        $this->load->model('message/message');
        $this->model_message_message->addSystemMessageToBuyer('bid_margin', $subject, $content, $margin_detail['buyer_id']);
    }

    /**
     * @param int $margin_id
     * @return array|\Illuminate\Support\Collection|null
     */
    public function getMarginStorageRecord($margin_id)
    {
        if (empty($margin_id)) {
            return null;
        }

        $ret = $this->orm->table('tb_sys_margin_agreement as ma')
            ->join('tb_sys_margin_process as mp', 'ma.id', '=', 'mp.margin_id')
            ->join('oc_product as p', 'p.product_id', '=', 'mp.rest_product_id')
            ->leftJoin('tb_sys_buyer_storage_fee as bsf', [['bsf.customer_id', 'ma.buyer_id'], ['mp.rest_product_id', 'bsf.product_id']])
            ->select('ma.product_id as margin_product_id', 'p.product_id as rest_product_id',
                'p.sku', 'p.length', 'p.width', 'p.height', 'p.combo_flag', 'bsf.onhand_qty', 'bsf.storage_time', 'bsf.storage_fee')
            ->where([
                ['ma.id', $margin_id]
            ])
            ->orderBy('bsf.id', 'DESC')
            ->get();

        if (!empty($ret)) {
            return $ret->toArray();
        } else {
            return null;
        }
    }

    /**
     * 查询combo品的子sku的尺寸
     *
     * @param int $product_id
     * @return array|null
     */
    public function getComboProductSubDimension($product_id)
    {
        if (empty($product_id)) {
            return null;
        }

        $ret = $this->orm->table('oc_product as p')
            ->join('tb_sys_product_set_info as psi', 'p.product_id', '=', 'psi.set_product_id')
            ->select('p.length', 'p.width', 'p.height', 'p.weight')
            ->where([
                ['psi.product_id', $product_id]
            ])
            ->get();

        if (!empty($ret)) {
            return obj2array($ret);
        } else {
            return null;
        }
    }


    /**
     * Tab标签的待处理的现货保证金协议数量
     * @return int
     */
    public function getTabMarkCount()
    {
        $buyerId = $this->customer->getId();
        $count_wp = $this->countWaitProcess();
        $count_dp = $this->countWaitDepositPay();
        $count_ds = $this->countDueSoon();
        $count_tr = $this->countTerminationRequest($buyerId);
        $count_mwp = $count_wp + $count_dp + $count_ds + $count_tr;
        return $count_mwp;
    }

    /**
     * 待处理数量
     * @return int
     */
    public function countWaitProcess()
    {
        $customer_id = intval($this->customer->getId());

        $sql = "
    SELECT COUNT(id) AS cnt
    FROM tb_sys_margin_agreement
    WHERE buyer_id={$customer_id}
    AND ((`status` IN (4,9) AND buyer_ignore=0) or (`status` IN (1,2,5) AND buyer_ignore=0 and is_bid = 1))";

        $query = $this->db->connection('read')->query($sql);

        $count_mwp = intval($query->row['cnt']);

        return $count_mwp;
    }

    /**
     * 待支付定金数量
     * @return int
     */
    public function countWaitDepositPay()
    {
        $customer_id = intval($this->customer->getId());

        $sql = "SELECT COUNT(id) AS cnt
        FROM tb_sys_margin_agreement
        WHERE buyer_id={$customer_id} AND `status`=3 and `is_bid` = 1";

        $query = $this->db->connection('read')->query($sql);

        $count_mwp = intval($query->row['cnt']);

        return $count_mwp;
    }

    /**
     * 即将到期数量
     * @return int
     */
    public function countDueSoon()
    {
        $customer_id = intval($this->customer->getId());

        $sql = "SELECT COUNT(a.id) AS cnt
        FROM tb_sys_margin_agreement AS a
        LEFT JOIN oc_agreement_common_performer AS acp
            ON acp.agreement_id=a.id
            AND acp.agreement_type=0
            AND acp.product_id=a.product_id
            AND acp.buyer_id={$customer_id}
        WHERE (a.buyer_id={$customer_id} OR acp.buyer_id={$customer_id})
            AND a.`status`=6
            AND TIMESTAMPDIFF(DAY, NOW(), a.expire_time) < 7
            AND TIMESTAMPDIFF(DAY, NOW(), a.expire_time) >= 0
            AND a.expire_time>=NOW() ";

        $query = $this->db->connection('read')->query($sql);

        $count_mwp = intval($query->row['cnt']);

        return $count_mwp;
    }


    /**
     * 查询未处理协商取消协议请求的数量
     *
     * @param int $buyerId buyerId
     *
     * @return int
     */
    public function countTerminationRequest($buyerId)
    {
        if (!$buyerId) {
            return 0;
        }
        return $this->orm->connection('read')->table('tb_sys_margin_agreement as a')
            ->leftJoin('oc_agreement_common_performer as acp', function ($join) use ($buyerId) {
                $join->on('acp.agreement_id', '=', 'a.id')
                    ->where('acp.agreement_type', 0)
                    ->whereColumn('acp.product_id', 'a.product_id')
                    ->where('acp.buyer_id', $buyerId);
            })
            ->where(function ($query) use ($buyerId) {
                $query->where('a.buyer_id', $buyerId)
                    ->orWhere('acp.buyer_id', $buyerId);
            })
            ->where('a.status', 6)
            ->where('a.termination_request', 1)
            ->where('a.expire_time', '>', date('Y-m-d H:i:s'))
            ->count();
    }


    /**
     *
     * @param int $id 现货保证金主键
     * @return bool
     */
    public function ignoreAgreement($id)
    {
        $customer_id = intval($this->customer->getId());

        $info = $this->getInfo($id);
        if ($info) {
            //4Rejected 5Time Out 9Back Order
            if (in_array($info['status'], [4, 5, 9])) {
                $time = date('Y-m-d H:i:s', time());
                $sql = "UPDATE tb_sys_margin_agreement SET `buyer_ignore`=1, update_time='{$time}' WHERE id={$id} AND buyer_id={$customer_id} AND `status` IN (4,5,9)";
                $query = $this->db->query($sql);
                if ($query->num_rows) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * [updateNewCredit description]
     * @param $money_info
     * @param int $agreement_id
     */
    public function updateNewCredit($money_info, $agreement_id)
    {
        $db = $this->orm->getConnection();
        $line_of_credit = $db->table(DB_PREFIX . 'customer')
            ->where('customer_id', $money_info['buyer_id'])->value('line_of_credit');
        $line_of_credit = round($line_of_credit, 4);
        $new_line_of_credit = round($line_of_credit + $money_info['all_amount'], 4);
        $serialNumber = SerialNumberRepository::getDateSerialNumber(ServiceEnum::AMENDMENT_RECORD_NO);
        $mapInsert = [
            'serial_number' => $serialNumber,
            'customer_id' => $money_info['buyer_id'],
            'old_line_of_credit' => $line_of_credit,
            'new_line_of_credit' => $new_line_of_credit,
            'date_added' => date('Y-m-d H:i:s', time()),
            'operator_id' => 1,
            'type_id' => 5,
            'memo' => '现货返金',
            'header_id' => $agreement_id
        ];

        $db->table('tb_sys_credit_line_amendment_record')->insertGetId($mapInsert);
        $db->table(DB_PREFIX . 'customer')
            ->where('customer_id', $money_info['buyer_id'])->update(['line_of_credit' => $new_line_of_credit]);
    }


    /**
     * 添加共同履约人
     * @param array $margin_info   保证金基本信息
     * @param array $customer_info 共同履行的信息
     * @param string|null $reason 理由
     * @throws Exception
     */
    public function performerAdd($margin_info, $customer_info, $reason)
    {
        $this->orm->getConnection()->beginTransaction();
        try {
            $time = date('Y-m-d H:i:s');
            $data = [
                'agreement_id' => $margin_info['id'],/*主键*/
                'performer_buyer_id' => $customer_info['customer_id'],
                'reason' => $reason,
                'check_result' => 0,
                'create_user_name' => $this->customer->getId(),
                'create_time' => $time,
                'update_time' => $time,
                'program_code' => MarginPerformerApply::PROGRAM_CODE_V2,
            ];
            $res = $this->orm->table('tb_sys_margin_performer_apply')->insert($data);
            if (!$res) {
                throw new \Exception();
            } else {
                //增加消息
                $data = [
                    'margin_agreement_id' => $margin_info['id'],
                    'customer_id' => $margin_info['buyer_id'],
                    'message' => "An Add a Partner request has been submitted.",
                    'create_time' => date('Y-m-d H:i:s'),
                    'memo' => 'Buyer Add Performer Request',
                ];
                $this->orm->table('tb_sys_margin_message')
                    ->insert($data);
            }
            $this->orm->getConnection()->commit();
        } catch (\Exception $exception) {
            $this->orm->getConnection()->rollBack();
            $res = false;
        }
    }


    /**
     * 已弃用
     * @date 20200325
     * 检测是否允许取消现货保证金
     * @param int $margin_id tb_sys_margin_agreement表主键
     * @return array
     */
    public function checkCanCancel($margin_id)
    {
        $ret = ['ret' => 0, 'msg' => 'Data Error!'];

        $customer_id = $this->customer->getId();

        $sql = "SELECT ma.id,ma.`status`, ma.seller_id, c2p.customer_id AS advance_seller_id
    FROM tb_sys_margin_agreement AS ma
    INNER JOIN tb_sys_margin_process AS mp ON mp.margin_id=ma.id
    INNER JOIN oc_customerpartner_to_product c2p ON c2p.product_id=mp.advance_product_id
    WHERE ma.id ={$margin_id} AND buyer_id={$customer_id}";


        $row = $this->db->query($sql)->row;

        if (!$row) {
            return ['ret' => 0, 'msg' => 'Data Error'];
        }

        //1Applied 3Approved
        if (!in_array($row['status'], [1, 3])) {
            return ['ret' => 0, 'msg' => 'Status of margin agreement has been changed. <br>This agreement cannot be canceled.'];
        }

        if ($row['seller_id'] != $row['advance_seller_id']) {
            return ['ret' => 0, 'msg' => 'Data Error!'];
        }

        return ['ret' => 1, 'msg' => 'Success'];
    }

    /*
     * 是否是期货转现货
     *
     * */
    public function isFuturesToMargin($marginId)
    {
        return $this->orm->table('oc_futures_margin_delivery')
            ->where('margin_agreement_id', $marginId)
            ->where('delivery_type', '!=', 1)
            ->value('agreement_id as futures_id');
    }


    /*
     * 是否是期货转现货
    */
    public function isFuturesToMarginVersion($marginId)
    {
        $quyer = $this->orm->table('oc_futures_margin_delivery AS fd')
            ->leftJoin('oc_futures_margin_agreement as fa', 'fa.id', '=', 'fd.agreement_id')
            ->where('fd.margin_agreement_id', intval($marginId))
            ->where('fd.delivery_type', '!=', 1)
            ->select(['fd.agreement_id as futures_id', 'fa.contract_id']);
        $result = $quyer->first();

        return obj2array($result);
    }

    /*
     * 是否是期货转现货
    */
    public function isFuturesToMarginMoreVersion($marginIdList = [])
    {
        if (!$marginIdList) {
            return [];
        }
        $quyer = $this->orm->table('oc_futures_margin_delivery AS fd')
            ->leftJoin('oc_futures_margin_agreement as fa', 'fa.id', '=', 'fd.agreement_id')
            ->whereIn('fd.margin_agreement_id', $marginIdList)
            ->where('fd.delivery_type', '!=', 1)
            ->select(['fd.margin_agreement_id', 'fd.agreement_id as futures_id', 'fa.contract_id']);
        $result = $quyer->get();
        $result = obj2array($result);
        $result = array_column($result, null, 'margin_agreement_id');

        return $result;
    }


    /**
     * 是否允许取消
     * @param array $value
     * @return array
     */
    public function isCanCancel($value)
    {
        $customer_id = $this->customer->getId();
        if ($value['buyer_id'] != $customer_id) {
            return ['ret' => 0, 'msg' => 'No Access, no permission'];
        }
        if (!in_array($value['status'], [1])) { // [1, 3]
            return ['ret' => 0, 'msg' => 'Status of margin agreement has been changed. <br>This agreement cannot be canceled.'];
        }
        if ($this->isFuturesToMargin($value['id'])) {
            return ['ret' => 0, 'msg' => 'No Access, no permission'];
        }

        return ['ret' => 1, 'msg' => 'OK'];
    }


    /**
     * 是否允许添加共同履约人
     * @param array $value
     * @return array
     */
    public function isCanPerformerAdd($value)
    {
        $date = date('Y-m-d H:i:s');

        if ($date < $value['effect_time'] || $date > $value['expire_time']) {
            return ['ret' => 0, 'msg' => 'The margin agreement has expired. <br>A partner cannot be added to this margin agreement.'];
        }

        $customer_id = $this->customer->getId();
        if ($value['buyer_id'] != $customer_id) {
            return ['ret' => 0, 'msg' => 'No Access, no permission'];
        }
        if ($value['advance_seller_id'] > 0 && $value['seller_id'] != $value['advance_seller_id']) {
            return ['ret' => 0, 'msg' => 'No Access, status of margin agreement has been changed.'];
        }
        if ($value['count_performer'] > 0) {
            return ['ret' => 0, 'msg' => 'No Access, has already been added to be your agreement partner sucessfully. <br>Please don not add again.'];
        }
        if (!in_array($value['status'], [6])) {
            return ['ret' => 0, 'msg' => 'Status of margin agreement has been changed. <br>A partner cannot be added to this margin agreement.'];
        }
        return ['ret' => 1, 'msg' => 'OK'];
    }

    /**
     * 是否允许添加至购物车
     * @param array $value
     * @return array
     */
    public function isCanCart($value)
    {
        if (!in_array($value['status'], [3, 6])) {
            return ['ret' => 0, 'msg' => 'Status of margin agreement has been changed. <br>This agreement cannot be add cart.'];
        }
        if ($value['status'] == 3) {
            $futures = $this->isFuturesToMargin($value['id']);
            if ($futures) {
                return ['ret' => 0, 'msg' => '这是一条期货转现货协议'];
            }
        }
        return ['ret' => 1, 'msg' => 'OK'];
    }

    /**
     * 是否允许重新申请
     * @param array $value
     * @return array
     */
    public function isCanReapplied($value)
    {
        $customer_id = $this->customer->getId();
        if (intval($value['advance_product_id']) > 0) {
            //生成头款产品则不允许重新申请
            return ['ret' => 0, 'msg' => 'No Access'];
        }
        if ($value['buyer_id'] != $customer_id) {
            return ['ret' => 0, 'msg' => 'No Access, no permission'];
        }
        if ($value['advance_seller_id'] > 0 && $value['seller_id'] != $value['advance_seller_id']) {
            return ['ret' => 0, 'msg' => 'No Access, status of margin agreement has been changed.'];
        }
        //4 Rejected、7 Canceled、5 Time Out
        if (!in_array($value['status'], [4, 7, 5])) {
            return ['ret' => 0, 'msg' => 'Status of margin agreement has been changed. <br>This agreement cannot be reapplied.'];
        }
        return ['ret' => 1, 'msg' => 'OK'];
    }

    /**
     * 获取从履约人
     * @param int $margin_id tb_sys_margin_agreement表主键
     * @return array
     */
    public function getSubPerformerList($margin_id)
    {
        $margin_id = intval($margin_id);
        $sql = "SELECT buyer_id FROM oc_agreement_common_performer WHERE agreement_type=0 AND agreement_id={$margin_id} AND is_signed=0";

        $results = $this->db->query($sql)->rows;
        $arr_buyer_id = array_column($results, 'buyer_id');
        return $arr_buyer_id;
    }


    /**
     * 获取主履约人
     * @param int $margin_id tb_sys_margin_agreement表主键
     * @return int
     */
    public function getMasterPerformerList($margin_id)
    {
        $margin_id = intval($margin_id);
        $sql = "SELECT buyer_id FROM oc_agreement_common_performer WHERE agreement_type=0 AND agreement_id={$margin_id} AND is_signed=1";

        $results = $this->db->query($sql)->row;
        return intval($results['buyer_id']);
    }


    /**
     * 某采购单中的保证金产品
     * @param int $order_id 采购订单id oc_order表主键
     * @return array
     */
    public function getMarginProductByOrderID($order_id)
    {
        $sql = "
    SELECT mp.advance_product_id AS product_id, mp.margin_id, mp.margin_agreement_id, 'advance' AS 'type'
    FROM tb_sys_margin_process AS mp
    WHERE mp.advance_order_id={$order_id}
    UNION
    SELECT mp.rest_product_id AS product_id, mp.margin_id, mp.margin_agreement_id, 'rest' AS 'type'
    FROM tb_sys_margin_order_relation AS mor
    LEFT JOIN tb_sys_margin_process AS mp ON mp.id=mor.margin_process_id
    WHERE mor.rest_order_id={$order_id}";
        $rows = $this->db->query($sql)->rows;

        $arr_product = [];
        if ($rows) {
            $arr_product = array_column($rows, null, 'product_id');
        }

        return $arr_product;
    }

    /**
     * 验证 改 sku 是否还有保证金现货模板
     *
     * @param int $product_id
     * @return bool
     */
    public function checkProductIsActiveInMarginTemplate($product_id)
    {
        return $this->orm->table('tb_sys_margin_template')
            ->where([
                ['product_id', $product_id],
                ['is_del', 0]
            ])
            ->exists();
    }

    /**
     * 到期预警
     * @param array $marginAgreement
     * @param int $advanceDays 提前天数
     * @return array
     */
    public function daysLeftWarning($marginAgreement, $advanceDays = 7)
    {
        $daysLeft = [
            'is_show' => false,
            'days' => 0,
        ];
        if (empty($marginAgreement['expire_time']) || $marginAgreement['status'] != 6) {
            return $daysLeft;
        }

        $days = ceil((strtotime($marginAgreement['expire_time']) - time()) / 86400);
        if ($days <= $advanceDays && $days > 0) {
            $daysLeft = [
                'is_show' => true,
                'days' => $days,
            ];
        }

        return $daysLeft;
    }

    /**
     * 获取倒计时
     * @param array $marginAgreement
     * @param int $advanceSeconds 提前秒数
     * @return array
     */
    public function countDownByStatus($marginAgreement, $advanceSeconds = 3600)
    {
        $statusCountDown = [
            'is_show' => false,
            'minute' => 0,
            'second' => 0
        ];

        if (!in_array($marginAgreement['status'], [1, 2, 3])) {
            return $statusCountDown;
        }

        $startTime = $marginAgreement['update_time'];

        $residueTime = strtotime($startTime) + $marginAgreement['period_of_application'] * 86400 - time();
        if ($residueTime >= $advanceSeconds) {
            return $statusCountDown;
        }

        $statusCountDown['is_show'] = true;

        if ($residueTime < 0) {
            return $statusCountDown;
        }

        $statusCountDown['minute'] = floor($residueTime / 60);
        $statusCountDown['second'] = $residueTime % 60;

        return $statusCountDown;
    }
}
