<?php

use App\Enums\Product\ProductTransactionType;
use Illuminate\Database\Query\Builder;

/**
 *
 * @property ModelAccountCustomer $model_account_customer
 * @property ModelAccountProductQuotesMail $model_account_product_quotes_mail
 * @property ModelCustomerpartnerBargain $model_customerpartner_bargain
 * @property ModelMessageMessage $model_message_message
 *
 * Class ModelAccountProductQuoteswkproductquotes
 */
class ModelAccountProductQuoteswkproductquotes extends Model
{

    /**
     * 四舍五入精度
     * 日本不保留小数位，其他国家保留两位
     *
     * @var int $precision
     */
    private $precision;
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->table = 'oc_product_quote';
        $this->precision = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0 : 2;
    }

    public function insertQuoteData($data)
    {
        $agreementNo = currentZoneDate($this->session, date('Ymd'), 'Ymd') . rand(100000, 999999);
        $this->db->query(
            "INSERT INTO " . DB_PREFIX . "product_quote SET
                                    agreement_no = '".$agreementNo."',
                                    customer_id = '".$this->customer->getId()."',
                                    product_id = '".(int)$data['product_id']."',
                                    product_key = '".$this->db->escape($data['key'])."',
                                    quantity = '".(int)$data['quote_quantity']."',
                                    message = '".$this->db->escape(nl2br($data['quote_message']))."',
                                    price = '".(float)$data['quote_price']."',
                                    date_added = NOW(),
                                    origin_price=".$data['origin_price'].",
                                    discount=".$data['discount'].",
                                    discount_price=".$data['discount_price'].",
                                    status = 0"
        );
        $data['quote_id'] = $this->db->getLastId();

        $this->load->model('account/product_quotes/mail');
        $product_name = $this->db->query("SELECT name FROM ".DB_PREFIX."product_description WHERE product_id ='".(int)$data['product_id']."'")->row;
        $data['product_name'] = $product_name['name'];
        $this->model_account_product_quotes_mail->mail($data,'generate_quote_to_admin');
        $seller_id = $this->db->query("SELECT * FROM ".DB_PREFIX."customerpartner_to_product WHERE product_id='".(int)$data['product_id']."'")->row;

        if(!empty($seller_id)) {
            //议价申请提交成功后 给seller发送system消息
            $this->addCommunication($data['quote_id'], $seller_id['customer_id'], $data);

            $seller_email = $this->db->query("SELECT email  FROM ".DB_PREFIX."customer WHERE customer_id ='".$seller_id['customer_id']."'")->row;
            $this->model_account_product_quotes_mail->mail($data,'generate_quote_to_seller',$seller_email['email']);
        }
        $this->model_account_product_quotes_mail->mail($data, 'generate_quote_to_customer');
    }

    public function updateQuoteData($quote_id,$data)
    {
        // 修改 product_quote
        $this->db->query(
            "UPDATE " . DB_PREFIX . "product_quote SET
                                    quantity = " . (int)$data['quote_quantity'] . ",
                                    price = '" . (float)$data['quote_price'] . "'
                                    WHERE id = " . (int)$quote_id . " AND customer_id = " . $this->customer->getId()
        );

        // 添加message
        $this->orm::table(DB_PREFIX . "product_quote_message")
            ->insert([
                'quote_id' => (int)$quote_id,
                'writer' => $this->customer->getId(),
                'message' => nl2br($data['message']),
                'date' => date('Y-m-d H:i:s'),
            ]);
        $data['quote_id'] = $quote_id;
        $this->load->model('account/product_quotes/mail');
        $this->model_account_product_quotes_mail->mail($data, 'message_to_admin');
        $sellerData = $this->db->query("SELECT customer_id FROM " . DB_PREFIX . "customerpartner_to_product WHERE product_id=" . (int)$this->request->get['product_id'])->row;
        if (!empty($sellerData)) {
            $seller_email = $this->db->query("SELECT firstname,lastname ,email  FROM " . DB_PREFIX . "customer WHERE customer_id =" . $sellerData['customer_id'])->row;
            $this->load->model('account/product_quotes/mail');
            $data['seller_name'] = $seller_email['firstname'] . ' ' . $seller_email['lastname'];
            $this->model_account_product_quotes_mail->mail($data, 'message_to_seller', $seller_email['email']);
        }
    }

    public function addQuoteMessage($quote_id,$data)
    {
        $this->db->query(
            "INSERT INTO " . DB_PREFIX . "product_quote_message SET
                                    quote_id = '" . (int)$quote_id . "',
                                    writer = '" . $this->customer->getId() . "',
                                    message = '" . $this->db->escape(nl2br($data['message'])) . "',
                                    date = NOW()"
        );
        $data['quote_id'] = $quote_id;
        $this->load->model('account/product_quotes/mail');
        $this->model_account_product_quotes_mail->mail($data, 'message_to_admin');
        $sellerData = $this->db->query("SELECT customer_id FROM " . DB_PREFIX . "customerpartner_to_product WHERE product_id=" . (int)$this->request->get['product_id'])->row;
        if (!empty($sellerData)) {
            $seller_email = $this->db->query("SELECT firstname,lastname ,email  FROM " . DB_PREFIX . "customer WHERE customer_id =" . (int)$sellerData['customer_id'])->row;
            $this->load->model('account/product_quotes/mail');
            $data['seller_name'] = $seller_email['firstname'] . ' ' . $seller_email['lastname'];
            $this->model_account_product_quotes_mail->mail($data, 'message_to_seller', $seller_email['email']);
        }
    }


    public function getProductQuotes($data)
    {

        $sql = "SELECT pd.name,pd.product_id,pq.*,p.price as baseprice,p.image,p.sku,p.mpn,pq.agreement_no,cc.screenname FROM " . DB_PREFIX . "product_quote pq LEFT JOIN " . DB_PREFIX . "customer c ON (c.customer_id = pq.customer_id) LEFT JOIN " . DB_PREFIX . "product_description pd ON (pd.product_id = pq.product_id) LEFT JOIN " . DB_PREFIX . "product p ON (p.product_id = pq.product_id) LEFT JOIN " . DB_PREFIX . "customerpartner_to_product cp ON (cp.product_id=p.product_id) LEFT JOIN " . DB_PREFIX . "customerpartner_to_customer cc ON (cc.customer_id=cp.customer_id) WHERE pq.customer_id = '".(int)$this->customer->getId()."'AND pd.language_id = '".$this->config->get('config_language_id')."'";
        $implode = array();

        if (!is_null($data['filter_id'])) {
            $implode[] = "((pq.agreement_no is not null and pq.agreement_no like '%" . (int)$data['filter_id'] . "%') or (pq.agreement_no is null and pq.id like '%" . (int)$data['filter_id'] . "%'))";
        }

        if (!is_null($data['filter_product'])) {
            $implode[] = "LCASE(pd.name) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_product'])) . "%'";
        }

        if (!is_null($data['filter_status'])) {
            $implode[] = "pq.status = " . (int)$data['filter_status'];
        }

        if (!is_null($data['filter_sku_mpn'])) {
            $implode[] = " (p.sku like '%" . $data['filter_sku_mpn'] . "%' or p.mpn like '%" . $data['filter_sku_mpn'] . "%')";
        }

        if (!is_null($data['filter_date_from'])) {
            $implode[] = "pq.date_added >= '" . $this->db->escape(utf8_strtolower($data['filter_date_from'])) . "'";
        }
        if (!is_null($data['filter_date_to'])) {
            $implode[] = "pq.date_added <= '" . $this->db->escape(utf8_strtolower($data['filter_date_to'])) . "'";
        }

        if ($implode) {
            $sql .= " AND " . implode(" AND ", $implode);
        }

        $sort_data = array(
         'pq.id',
         'pq.product_id',
         'pd.name',
         'pq.price',
         'pq.status',
         'pq.quantity',
         'pq.date_added',
         'p.sku'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY pq.id";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
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

        $result = $this->db->query($sql);

        return $result->rows;
    }

    public function getProductQuotesTotal($data)
    {

        $sql = "SELECT count(*) as total
FROM " . DB_PREFIX . "product_quote pq
LEFT JOIN " . DB_PREFIX . "customer c ON (c.customer_id = pq.customer_id)
LEFT JOIN " . DB_PREFIX . "product_description pd ON (pd.product_id = pq.product_id)
LEFT JOIN " . DB_PREFIX . "product p ON (p.product_id = pq.product_id)
WHERE pq.customer_id = ".(int)$this->customer->getId()." AND pd.language_id = '".$this->config->get('config_language_id')."'";

        $implode = array();

        if (!is_null($data['filter_id'])) {
            $implode[] = "((pq.agreement_no is not null and pq.agreement_no like '%" . (int)$data['filter_id'] . "%') or (pq.agreement_no is null and pq.id like '%" . (int)$data['filter_id'] . "%'))";
        }

        if (!is_null($data['filter_product'])) {
            $implode[] = "LCASE(pd.name) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_product'])) . "%'";
        }

        if (!is_null($data['filter_status'])) {
            $implode[] = "pq.status = " . (int)$data['filter_status'];
        }

        if (!is_null($data['filter_sku_mpn'])) {
            $implode[] = " (p.sku like '%" . $data['filter_sku_mpn'] . "%' or p.mpn like '%" . $data['filter_sku_mpn'] . "%')";
        }

        if (!is_null($data['filter_date_from'])) {
            $implode[] = "pq.date_added >= '" . $this->db->escape(utf8_strtolower($data['filter_date_from'])) . "'";
        }
        if (!is_null($data['filter_date_to'])) {
            $implode[] = "pq.date_added <= '" . $this->db->escape(utf8_strtolower($data['filter_date_to'])) . "'";
        }

        if ($implode) {
            $sql .= " AND " . implode(" AND ", $implode);
        }

        $result = $this->db->query($sql);

        return $result->row['total'];
    }

    public function getProductQuoteDetail($id)
    {
        $sql = "SELECT pd.name,pd.product_id,pq.*,p.freight,p.price as baseprice,dm.current_price as dm_price,p.image,p.sku
FROM " . DB_PREFIX . "product_quote pq
LEFT JOIN " . DB_PREFIX . "customer c ON (c.customer_id = pq.customer_id)
LEFT JOIN " . DB_PREFIX . "product_description pd ON (pd.product_id = pq.product_id)
LEFT JOIN " . DB_PREFIX . "product p ON (p.product_id = pq.product_id)
left join oc_delicacy_management as dm on (dm.buyer_id = pq.customer_id and dm.product_id = pq.product_id AND dm.expiration_time>NOW())
WHERE pq.customer_id = " . (int)$this->customer->getId() . " AND p.status=1 AND pd.language_id = '" . $this->config->get('config_language_id') . "'
AND pq.id = " . (int)$id."
AND (dm.product_display=1 OR dm.product_display is NULL )
AND NOT EXISTS (
	SELECT dmg.id FROM oc_delicacy_management_group AS dmg
		JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.buyer_group_id = dmg.buyer_group_id )
		JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.product_group_id = dmg.product_group_id )
	WHERE
		bgl.buyer_id = pq.customer_id   AND pgl.product_id = pd.product_id
		AND dmg.status=1 and bgl.status=1 and pgl.status=1
)";
        return $this->db->query($sql)->row;
    }

    /**
     * 用于 buyer 查看议价单历史
     *
     * @param int $id
     * @return array
     */
    public function getProductQuoteDetailForHistory($id)
    {
        $sql = "SELECT pd.name,pd.product_id,pq.*,IFNULL(dm.current_price,p.price) as baseprice,dm.product_display,p.freight,p.image,p.sku,ctp.customer_id as seller_id
FROM " . DB_PREFIX . "product_quote pq
LEFT JOIN " . DB_PREFIX . "customerpartner_to_product ctp ON (ctp.product_id = pq.product_id)
LEFT JOIN " . DB_PREFIX . "customer c ON (c.customer_id = pq.customer_id)
LEFT JOIN " . DB_PREFIX . "product_description pd ON (pd.product_id = pq.product_id)
LEFT JOIN " . DB_PREFIX . "product p ON (p.product_id = pq.product_id)
left join oc_delicacy_management as dm on (dm.buyer_id = pq.customer_id and dm.product_id = pq.product_id AND dm.expiration_time>NOW() AND dm.product_display=1)
WHERE pq.customer_id = " . (int)$this->customer->getId() . "  AND pd.language_id = '" . $this->config->get('config_language_id') . "'
AND pq.id = " . (int)$id;

        return $this->db->query($sql)->row;
    }

    public function getQuoteMessage($id,$start,$limit)
    {
        return $this->db->query("SELECT * FROM " . DB_PREFIX . "product_quote_message WHERE quote_id = ".(int)$id." ORDER BY id DESC LIMIT  " . (int)$start . "," . (int)$limit)->rows;
    }

    public function getTotalQuoteMessage($id)
    {
        return $this->db->query("SELECT count(*) as total FROM " . DB_PREFIX . "product_quote_message WHERE quote_id = ".(int)$id)->row['total'];
    }

    public function getProductOptions($options,$product_id)
    {
        $option_price = 0;
        $option_points = 0;
        $option_weight = 0;

        $option_data = array();

        foreach ($options as $product_option_id => $value) {
            $option_query = $this->db->query("SELECT po.product_option_id, po.option_id, od.name, o.type FROM " . DB_PREFIX . "product_option po LEFT JOIN `" . DB_PREFIX . "option` o ON (po.option_id = o.option_id) LEFT JOIN " . DB_PREFIX . "option_description od ON (o.option_id = od.option_id) WHERE po.product_option_id = '" . (int)$product_option_id . "' AND po.product_id = '" . (int)$product_id . "' AND od.language_id = '" . (int)$this->config->get('config_language_id') . "'");

            if ($option_query->num_rows) {
                if ($option_query->row['type'] == 'select' || $option_query->row['type'] == 'radio' || $option_query->row['type'] == 'image') {
                    $option_value_query = $this->db->query("SELECT pov.option_value_id, ovd.name, pov.quantity, pov.subtract, pov.price, pov.price_prefix, pov.points, pov.points_prefix, pov.weight, pov.weight_prefix FROM " . DB_PREFIX . "product_option_value pov LEFT JOIN " . DB_PREFIX . "option_value ov ON (pov.option_value_id = ov.option_value_id) LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE pov.product_option_value_id = '" . (int)$value . "' AND pov.product_option_id = '" . (int)$product_option_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

                    if ($option_value_query->num_rows) {
                        if ($option_value_query->row['price_prefix'] == '+') {
                            $option_price += $option_value_query->row['price'];
                        } elseif ($option_value_query->row['price_prefix'] == '-') {
                            $option_price -= $option_value_query->row['price'];
                        }

                        if ($option_value_query->row['points_prefix'] == '+') {
                            $option_points += $option_value_query->row['points'];
                        } elseif ($option_value_query->row['points_prefix'] == '-') {
                            $option_points -= $option_value_query->row['points'];
                        }

                        if ($option_value_query->row['weight_prefix'] == '+') {
                            $option_weight += $option_value_query->row['weight'];
                        } elseif ($option_value_query->row['weight_prefix'] == '-') {
                            $option_weight -= $option_value_query->row['weight'];
                        }
                        $option_data[] = array(
                         'product_option_id'       => $product_option_id,
                         'product_option_value_id' => $value,
                         'option_id'               => $option_query->row['option_id'],
                         'option_value_id'         => $option_value_query->row['option_value_id'],
                         'name'                    => $option_query->row['name'],
                         'value'                   => $option_value_query->row['name'],
                         'type'                    => $option_query->row['type'],
                         'quantity'                => $option_value_query->row['quantity'],
                         'subtract'                => $option_value_query->row['subtract'],
                         'price'                   => $option_value_query->row['price'],
                         'price_prefix'            => $option_value_query->row['price_prefix'],
                         'points'                  => $option_value_query->row['points'],
                         'points_prefix'           => $option_value_query->row['points_prefix'],
                         'weight'                  => $option_value_query->row['weight'],
                         'weight_prefix'           => $option_value_query->row['weight_prefix']
                        );
                    }
                } elseif ($option_query->row['type'] == 'checkbox' && is_array($value)) {
                    foreach ($value as $product_option_value_id) {
                        $option_value_query = $this->db->query("SELECT pov.option_value_id, ovd.name, pov.quantity, pov.subtract, pov.price, pov.price_prefix, pov.points, pov.points_prefix, pov.weight, pov.weight_prefix FROM " . DB_PREFIX . "product_option_value pov LEFT JOIN " . DB_PREFIX . "option_value ov ON (pov.option_value_id = ov.option_value_id) LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE pov.product_option_value_id = '" . (int)$product_option_value_id . "' AND pov.product_option_id = '" . (int)$product_option_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'");

                        if ($option_value_query->num_rows) {
                            if ($option_value_query->row['price_prefix'] == '+') {
                                $option_price += $option_value_query->row['price'];
                            } elseif ($option_value_query->row['price_prefix'] == '-') {
                                $option_price -= $option_value_query->row['price'];
                            }

                            if ($option_value_query->row['points_prefix'] == '+') {
                                $option_points += $option_value_query->row['points'];
                            } elseif ($option_value_query->row['points_prefix'] == '-') {
                                $option_points -= $option_value_query->row['points'];
                            }

                            if ($option_value_query->row['weight_prefix'] == '+') {
                                $option_weight += $option_value_query->row['weight'];
                            } elseif ($option_value_query->row['weight_prefix'] == '-') {
                                $option_weight -= $option_value_query->row['weight'];
                            }
                            $option_data[] = array(
                             'product_option_id'       => $product_option_id,
                             'product_option_value_id' => $product_option_value_id,
                             'option_id'               => $option_query->row['option_id'],
                             'option_value_id'         => $option_value_query->row['option_value_id'],
                             'name'                    => $option_query->row['name'],
                             'value'                   => $option_value_query->row['name'],
                             'type'                    => $option_query->row['type'],
                             'quantity'                => $option_value_query->row['quantity'],
                             'subtract'                => $option_value_query->row['subtract'],
                             'price'                   => $option_value_query->row['price'],
                             'price_prefix'            => $option_value_query->row['price_prefix'],
                             'points'                  => $option_value_query->row['points'],
                             'points_prefix'           => $option_value_query->row['points_prefix'],
                             'weight'                  => $option_value_query->row['weight'],
                             'weight_prefix'           => $option_value_query->row['weight_prefix']
                            );
                        }
                    }
                } elseif ($option_query->row['type'] == 'text' || $option_query->row['type'] == 'textarea' || $option_query->row['type'] == 'file' || $option_query->row['type'] == 'date' || $option_query->row['type'] == 'datetime' || $option_query->row['type'] == 'time') {
                    $option_data[] = array(
                     'product_option_id'       => $product_option_id,
                     'product_option_value_id' => '',
                     'option_id'               => $option_query->row['option_id'],
                     'option_value_id'         => '',
                     'name'                    => $option_query->row['name'],
                     'value'                   => $value,
                     'type'                    => $option_query->row['type'],
                     'quantity'                => '',
                     'subtract'                => '',
                     'price'                   => '',
                     'price_prefix'            => '',
                     'points'                  => '',
                     'points_prefix'           => '',
                     'weight'                  => '',
                     'weight_prefix'           => ''
                    );
                }
            }
        }

        return $option_data;
    }

    public function deleteentry($id)
    {
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_quote WHERE id=".(int)$id." AND customer_id = ".$this->customer->getId());
    }



    public function viewProductPrice($id)
    {

        $sql ="SELECT price FROM " . DB_PREFIX . "product WHERE product_id='$id' ";
        $result=$this->db->query($sql);
        return $result->row;

    }

    public function checkforVouchor($var)
    {
        $sql ="SELECT product_id,customer_id,quantity FROM " . DB_PREFIX . "wk_product_quote WHERE var='$var'";
        $result=$this->db->query($sql);
        return $result->row;
    }

    public function checkforCouponId($code)
    {
        $sql ="SELECT coupon_id FROM " . DB_PREFIX . "coupon WHERE code = '$code'";
        $result=$this->db->query($sql);
        return $result->row;
    }

    /**
     * 更新议价使用时间和orderID
     * @param array $data ['status','order_id','quote_id']
     * @author lester.you
     */
    public function updateQuote($data)
    {
        if (isset($data['order_id']) && !empty($data['order_id'])) {
//            $orderProductObj = $this->orm::table('oc_order_product')
//                ->select(['price', 'total','quantity'])
//                ->where([
//                    ['order_id', $data['order_id']],
//                    ['product_id', $data['product_id']]
//                ])
//                ->first();
            $productQuoteObj = $this->orm::table(DB_PREFIX . 'product_quote')
                ->select(['price','quantity','discount_price'])
                ->where('id', (int)$data['quote_id'])
                ->first();
            $amount = round($productQuoteObj->discount_price * $productQuoteObj->quantity - $productQuoteObj->price * $productQuoteObj->quantity,2);
            $update=[
                'order_id' => (int)$data['order_id'],
                'date_used' => date('Y-m-d H:i:s'),
                'amount' => $amount
            ];
        }
        $update['status'] = (int)$data['status'];
        $this->orm::table(DB_PREFIX . 'product_quote')
            ->where('id', (int)$data['quote_id'])
            ->update($update);

//        $this->db->query("UPDATE " . DB_PREFIX . "product_quote SET status = '".(int)$data['status']."' WHERE id = '".(int)$data['quote_id']."'");
    }

    /**
     * @param int $order_id
     */
    public function updateQuoteByOrder($order_id)
    {
        $objs = $this->orm->table('oc_order_quote as oq')
            ->join('oc_product_quote as pq', 'pq.id', '=', 'oq.quote_id')
            ->select(['pq.id as quote_id', 'pq.price','pq.origin_price', 'pq.quantity', 'pq.discount_price','oq.amount_data'])
            ->where([
                ['oq.order_id', $order_id],
                ['pq.status', '<>', 3]
            ])
            ->get();
        foreach ($objs as $obj) {
            $amount_data = empty($obj->amount_data) ? [] : json_decode($obj->amount_data,true);
            if (!empty($amount_data)) {
                $amount = $amount_data['amount_total'];
                $amount_price_per = $amount_data['amount_price_per'];
                $amount_service_fee_per = $amount_data['amount_service_fee_per'];
            }else{
                $amount_price_per = bcsub($obj->discount_price - $obj->price, $this->precision);
                $amount = round($amount_price_per * $obj->quantity, $this->precision);
//                $amount = round($obj->discount_price  - $obj->price * $obj->quantity, 2);
                $amount_service_fee_per = 0;
            }
            $update = [
//                'price' => ($obj->origin_price - $amount_price_per - $amount_service_fee_per),
                'order_id' => $order_id,
                'date_used' => date('Y-m-d H:i:s'),
                'amount' => $amount,
                'status' => 3,
                'amount_price_per' => $amount_price_per,
                'amount_service_fee_per' => $amount_service_fee_per,
            ];
            $this->orm->table('oc_product_quote')
                ->where('id', $obj->quote_id)
                ->update($update);
        }
    }

    /**
     * 获取配置的该产品最小数量
     *
     * @param int $product_id
     * @return int
     * @throws Exception
     */
    public function getMinQuantity($product_id)
    {
        $sellerObj = $this->orm->table(DB_PREFIX . 'customerpartner_to_product')
            ->select('customer_id')
            ->where('product_id', $product_id)
            ->first();
        if (empty($sellerObj) || empty($sellerObj->customer_id)) {
            return $this->config->get('total_wk_pro_quote_seller_quantity');
        }
        $obj = $this->orm->table('oc_wk_pro_quote')
            ->select(['quantity', 'product_ids', 'status'])
            ->where([
                ['seller_id', $sellerObj->customer_id],
            ])
            ->first();
        if (empty($obj)) {
            return $this->config->get('total_wk_pro_quote_seller_quantity');
        } else {
            if ($obj->status == 1) {    //所有产品均可议价
                return $obj->quantity;
            } else {    //固定产品议价
                $this->load->model('customerpartner/bargain');
                /** @var ModelCustomerpartnerBargain $mcb */
                $mcb = $this->model_customerpartner_bargain;
                $products = $mcb->getBargainProductIds($sellerObj->customer_id);
                return in_array($product_id, $products) ? $obj->quantity : 0;
            }
        }
    }

    public function cancel($id, $customer_id)
    {
        $this->orm->table('oc_product_quote')
            ->where([
                ['id', $id],
                ['customer_id', $customer_id],
                ['status', 0],
            ])
            ->update(['status' => 5]);
        $this->addCommunication($id);
    }

    public function addOrderQuote($order_id, $quote_id, $buyer_id,$amount_data,$product_id)
    {
        $this->orm->table('oc_order_quote')->insert([
            'order_id'=>$order_id,
            'quote_id'=>$quote_id,
            'buyer_id'=>$buyer_id,
            'product_id'=>$product_id,
            'add_time' => date('Y-m-d H:i:s'),
            'amount_data'=>json_encode($amount_data)
        ]);
    }


    //提交/取消 议价申请后 给seller发一条system消息
    public function addCommunication($quote_id, $seller_id=0, $data='')
    {
        $this->load->model('account/customer');
        $nickname = $this->model_account_customer->getCustomerNicknameAndNumber($this->customer->getId());

        $message = '<table   border="0" cellspacing="0" cellpadding="0">';
        $message .= '<tr><th align="left">Quote ID:&nbsp</th><td style="width: 650px">
                     <a href="' . $this->url->link('account/customerpartner/wk_quotes_admin/update', '&id=' . $quote_id) . '">'.$quote_id.'</a></td></tr> ';
        $message .= '<tr><th align="left">Name:&nbsp</th><td style="width: 650px">'.$nickname.'</td></tr>';
        if ($data){//提交申请
            $product = $this->orm->table('oc_product')->where('product_id', $data['product_id'])->select('sku','mpn')->first();

            $subject = $nickname.' has submitted a spot price bid request to '.$product->sku.': #'.$quote_id;
            $message .= '<tr><th align="left">Item Code/MPN:&nbsp</th><td style="width: 650px">'.$product->sku.' / '.$product->mpn.'</td></tr>';
            $message .= '<tr><th align="left">Requested product quantity:&nbsp</th><td style="width: 650px">' .$data['quote_quantity']. '</td></tr>';
            $message .= '<tr><th align="left">Requested Price Per Unit:&nbsp</th><td style="width: 650px">' .$data['quote_price']. '</td></tr>';
        }else{//取消申请
            $product_id = $this->orm->table('oc_product_quote')->where('id',$quote_id)->value('product_id');
            $product = $this->orm->table('oc_product')->where('product_id', $product_id)->select('sku','mpn')->first();
            $seller_id = $this->orm->table('oc_customerpartner_to_product')->where('product_id', $product_id)->value('customer_id');

            $subject = 'The spot price quote ID '.$quote_id.' has been canceled';

            $message .= '<tr><th align="left">Item Code/MPN:&nbsp</th><td style="width: 650px">'.$product->sku.' / '.$product->mpn.'</td></tr>';
            $message .= '<tr><th align="left">Timeout reason:&nbsp</th><td style="width: 650px">The buyer made the cancellation.</td></tr>';
        }
        $message .= '</table>';

        $this->load->model('message/message');
        $this->model_message_message->addSystemMessageToBuyer('bid',$subject,$message,$seller_id);
    }

    //seller收到的处于Applied状态的议价申请个数
    public function quoteAppliedCount($seller_id)
    {
        $count = $this->orm->table('oc_product_quote as pq')
            ->leftjoin('oc_customerpartner_to_product as cp', 'cp.product_id', 'pq.product_id')
            ->where('cp.customer_id', $seller_id)
            ->where('pq.status', 0)
            ->count();
        return $count;
    }

    /**
     * 某个buyer的议价协议某些状态下的数量
     * @param int $buyerId
     * @param array $status
     * @return int
     */
    public function buyerQuoteAgreementsSomeStatusCount($buyerId, $status = [])
    {
        return $this->orm->connection('read')->table('oc_product_quote')
            ->where('customer_id', $buyerId)
            ->when(!empty($status), function ($q) use ($status) {
                $q->whereIn('status', $status);
            })
            ->count();
    }

    //验证议价是否属于custom
    public function check_quota($quota_id)
    {
        $quota_id = (int)$quota_id;
        return $this->db->query("select pq.status from " . DB_PREFIX . "product_quote as pq left join " . DB_PREFIX . "customerpartner_to_product as cp on pq.product_id=cp.product_id where pq.id=$quota_id")->row;
    }

    /**
     * 获取 一条议价信息
     *
     * @param int $quote_id
     * @param int $buyer_id
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getSingleDataForCheck($quote_id, $buyer_id)
    {
        return $this->orm->table($this->table)
            ->select(['status', 'update_time', 'id'])
            ->where([
                ['id', '=', $quote_id],
                ['customer_id', '=', $buyer_id]
            ])
            ->first();
    }

    /**
     * 购物车是否存在议价协议
     * @param int $customerId
     * @param int $productId
     * @param int $agreementId
     * @param int $deliveryType
     * @return bool
     */
    public function cartExistSpotAgreement($customerId, $productId, $agreementId, $deliveryType)
    {
       return $this->orm->table(DB_PREFIX.'cart')
            ->where('api_id', ($this->session->has('api_id') ? $this->session->get('api_id') : 0))
            ->where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->where('type_id', ProductTransactionType::SPOT)
            ->where('agreement_id', $agreementId)
            ->where('delivery_type', $deliveryType)
            ->exists();
    }
}
