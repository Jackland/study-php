<?php

use App\Enums\Charge\ChargeType;
use App\Enums\FeeOrder\FeeOrderFeeType;
use App\Enums\Order\OcOrderStatus;
use App\Enums\Order\OrderDeliveryType;
use App\Enums\Pay\PayCode;
use App\Enums\SalesOrder\CustomerSalesOrderLineItemStatus;
use App\Enums\SalesOrder\CustomerSalesOrderMode;
use App\Exception\SalesOrder\AssociatedException;
use App\Components\Locker;
use App\Enums\FeeOrder\FeeOrderStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Logging\Logger;
use App\Models\Customer\Customer;
use App\Models\CWF\CloudWholesaleFulfillmentFileExplain;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Link\OrderAssociated;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use App\Repositories\FeeOrder\FeeOrderRepository;
use App\Repositories\FeeOrder\StorageFeeRepository;
use App\Repositories\SalesOrder\AutoBuyRepository;
use App\Services\CWF\CloudWholesaleFulfillmentService;
use App\Services\FeeOrder\FeeOrderService;
use App\Services\FeeOrder\StorageFeeService;
use App\Services\Margin\MarginService;
use App\Services\Marketing\MarketingTimeLimitDiscountService;
use App\Services\Order\OrderService;
use App\Services\SellerAsset\SellerAssetService;
use App\Services\Stock\BuyerStockService;
use Illuminate\Database\Capsule\Manager as DB;
use App\Services\Marketing\PlatformBillService;
use App\Services\Marketing\CouponService;
use Yzc\SysCostDetail;
use Yzc\SysReceive;
use Yzc\SysReceiveLine;
use Illuminate\Database\Query\Expression;
/**
 * Class ModelCheckoutOrder
 *
 * @property ModelCommonProduct $model_common_product
 * @property ModelCheckoutSuccess $model_checkout_success
 * @property ModelAccountCustomerOrderImport $model_account_customer_order_import
 * @property ModelAccountProductQuotesMargin $model_account_product_quotes_margin
 * @property ModelFuturesAgreement $model_futures_agreement
 * @property ModelAccountProductQuoteswkproductquotes $model_account_product_quotes_wk_product_quotes
 * @property ModelCheckoutPay $model_checkout_pay
 * @property ModelMessageMessage $model_message_message
 * @property ModelToolSort $model_tool_sort
 */
class ModelCheckoutOrder extends Model
{
    /**
     * [addOrder description]  oc_order 和 oc_order_product中新增了transaction_type 以及agreement_id
     * @param $data
     * @param null $saleOrderId
     * @return int
     * @throws Exception
     */
    public function addOrder($data, $saleOrderId = null)
    {
        $auto_buy = false;
        $customerCountry = $this->customer->getCountryId();
        if (isset($saleOrderId)) {
            //设置$saleOrderId参数，自动购买
            $auto_buy = true;
            $country_query = $this->db->query("SELECT country_id FROM oc_customer WHERE customer_id = " . (int)$data['customer_id']);
            if (isset($country_query->row['country_id']) && !empty($country_query->row['country_id'])) {
                $customerCountry = $country_query->row['country_id'];
            }
        }

        //默认的delivery_type =0
        //delivery_type = 2 批量的一个处理流程
        $delivery_type = isset($this->session->data['delivery_type']) ? $this->session->data['delivery_type'] : (OrderDeliveryType::DROP_SHIPPING);
        $cloud_logistics_id = ($delivery_type == 2 && isset($this->session->data['cwf_id'])) ? $this->session->data['cwf_id'] : '';
        $isBatchCwf = false;
        if(isset($data['cwf_file_upload_id'])
            && $data['cwf_file_upload_id']
            && $delivery_type == 2)
        {
            $cloud_logistics_id = null;
            $isBatchCwf = true;
        }

        //插入oc_order表
        $now = date('Y-m-d H:i:s');
        $orderData = [
            'invoice_no' => 0,
            'invoice_prefix' => $data['invoice_prefix'],
            'store_id' => (int)$data['store_id'],
            'store_name' => $data['store_name'],
            'store_url' => $data['store_url'],
            'customer_id' => (int)$data['customer_id'],
            'customer_group_id' => (int)$data['customer_group_id'],
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'email' => $data['email'],
            'telephone' => $data['telephone'],
            'custom_field' => isset($data['custom_field']) ? json_encode($data['custom_field']) : '',
            'payment_firstname' => isset($data['payment_firstname']) ? $data['payment_firstname'] : '',
            'payment_lastname' => isset($data['payment_lastname']) ? $data['payment_lastname'] : '',
            'payment_company' => isset($data['payment_company']) ? $data['payment_company'] : '',
            'payment_address_1' => isset($data['payment_address_1']) ? $data['payment_address_1'] : '',
            'payment_address_2' => isset($data['payment_address_2']) ? $data['payment_address_2'] : '',
            'payment_city' => isset($data['payment_city']) ? $data['payment_city'] : '',
            'payment_postcode' => isset($data['payment_postcode']) ? $data['payment_postcode'] : '',
            'payment_country_id' => isset($data['payment_country_id']) ? $data['payment_country_id'] : 0,
            'payment_country' => isset($data['payment_country']) ? $data['payment_country'] : '',
            'payment_zone_id' => isset($data['payment_zone_id']) ? $data['payment_zone_id'] : 0,
            'payment_zone' => isset($data['payment_zone']) ? $data['payment_zone'] : '',
            'payment_address_format' => isset($data['payment_address_format']) ? $data['payment_address_format'] : '',
            'payment_custom_field' => isset($data['payment_custom_field']) ? json_encode($data['payment_custom_field']) : '[]',
            'payment_method' => isset($data['payment_method']) ? $data['payment_method'] : '',
            'payment_code' => isset($data['payment_code']) ? $data['payment_code'] : '',
            'shipping_firstname' => isset($data['shipping_firstname']) ? $data['shipping_firstname'] : '',
            'shipping_lastname' => isset($data['shipping_lastname']) ? $data['shipping_lastname'] : '',
            'shipping_company' => isset($data['shipping_company']) ? $data['shipping_company'] : '',
            'shipping_address_1' => isset($data['shipping_address_1']) ? $data['shipping_address_1'] : '',
            'shipping_address_2' => isset($data['shipping_address_2']) ? $data['shipping_address_2'] : '',
            'shipping_postcode' => isset($data['shipping_postcode']) ? $data['shipping_postcode'] : '',
            'shipping_city' => isset($data['shipping_city']) ? $data['shipping_city'] : '',
            'shipping_zone_id' => isset($data['shipping_zone_id']) ? $data['shipping_zone_id'] : 0,
            'shipping_zone' => isset($data['shipping_zone']) ? $data['shipping_zone'] : '',
            'shipping_country_id' => isset($data['shipping_country_id']) ? $data['shipping_country_id'] : 0,
            'shipping_country' => isset($data['shipping_country']) ? $data['shipping_country'] : '',
            'shipping_address_format' => isset($data['shipping_address_format']) ? $data['shipping_address_format'] : '',
            'shipping_custom_field' => isset($data['shipping_custom_field']) ? json_encode($data['shipping_custom_field']) : '',
            'shipping_method' => isset($data['shipping_method']) ? $data['shipping_method'] : '',
            'shipping_code' => isset($data['shipping_code']) ? $data['shipping_code'] : '',
            'comment' => (string)isset($data['comment']) ? $data['comment'] : '',
            'total' => (float)$data['total'],
            'order_status_id' => 0,
            'affiliate_id' => 0,
            'commission' => 0,
            'language_id' => (int)$data['language_id'],
            'currency_id' => (int)$data['currency_id'],
            'currency_code' => $data['currency_code'],
            'currency_value' => (float)$data['currency_value'],
            'ip' => $data['ip'],
            'forwarded_ip' => $data['forwarded_ip'],
            'user_agent' => $data['user_agent'],
            'accept_language' => $data['accept_language'],
            'date_added' => $now,
            'date_modified' => $now,
            'current_currency_value' => isset($data['current_currency_value']) ?
                $data['current_currency_value'] : $this->session->data['currency'],
            'delivery_type' => $delivery_type,
            'cloud_logistics_id' => $cloud_logistics_id
        ];
        $order_id = $this->orm->table(DB_PREFIX . 'order')
            ->insertGetId($orderData);
        if($isBatchCwf){
            CloudWholesaleFulfillmentFileExplain::where('cwf_file_upload_id', $data['cwf_file_upload_id'])
                ->update([
                    'order_id' => $order_id
                ]);
            Logger::cloudWholesaleFulfillment("cwf_file_upload_id:{$data['cwf_file_upload_id']}更新order_id：{$order_id}", 'info');
        }

        $service_fee_split_country = $this->config->get('total_service_fee_split_country');
        //服务费拆分的国家， 为空:德国&英国  'all':所有 ，   'xx,xx,xxx':个别国家
        if ($service_fee_split_country == 'all') {
            $is_need_split = true;
        } else {
            if (empty($service_fee_split_country)) {
                //为空:德国&英国
                $service_fee_split_country = [222, 81];
            } else {
                $service_fee_split_country = explode(',', $service_fee_split_country);
            }
            $is_need_split = in_array($customerCountry, $service_fee_split_country);
        }
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        // Products
        if (isset($data['products'])) {
            //云送仓的订单先计算订单总体积,总打包费
            $volumeAll = 0;
            $packageFeeAll = 0;
            //订单产品总数
            $quantityAll = 0;
            $freightAll = 0;
            if ($this->session->data['delivery_type'] == 2) {
                foreach ($data['products'] as $product) {
                    $volumeAll += $product['volume_inch'] * $product['quantity'];
                    $quantityAll += $product['quantity'];
                    $packageFeeAll += $product['package_fee'] * $product['quantity'];
                    $freightAll += $product['freight_per'] * $product['quantity'];
                }
            }
            $orderLineToCombo = array();

            $this->load->model('account/product_quotes/wk_product_quotes');
            foreach ($data['products'] as $product) {
                if ($isCollectionFromDomicile) {
                    $freight_per = 0.00;
                    $baseFreight = 0.00;
                    $overweightSurcharge = 0.00;
                } else {
                    $freight_per = $product['freight_per'];
                    $baseFreight = $product['base_freight'];
                    $overweightSurcharge = $product['overweight_surcharge'];
                }
                //过滤oc_order_product.name \n\t字符
                $search = array("\n", "\r", "\t");
                $replace = array("", "", "");
                $product['name'] = str_replace($search, $replace, $product['name']);
                $orderProductData = [
                    'order_id' => $order_id,
                    'product_id' => $product['product_id'],
                    'type_id' => $product['type_id'],
                    'agreement_id' => $product['agreement_id'],
                    'name' => $product['name'],
                    'model' => $product['model'],
                    'quantity' => $product['quantity'],
                    'price' => (float)$product['price'],
                    'total' => (float)$product['total'],
                    'service_fee' => (double)$product['serviceFee'],
                    'tax' => (float)$product['tax'],
                    'reward' => (int)($product['reward'] ?? 0),
                    'package_fee' => (float)$product['package_fee'],
                    'coupon_amount' => (float)$product['coupon_amount'],
                    'campaign_amount' => (float)$product['campaign_amount'],
                    'discount' => $product['discount'] ?? null,
                    'discount_price' => $product['discount_price'] ?? null,
                    'danger_flag' => $product['danger_flag'] ?? 0,
                    'is_pure_logistics' => $product['is_pure_logistics'] ?? 0,
                ];
                if ($is_need_split) {
                    $orderProductData['service_fee_per'] = (double)$product['serviceFeePer'];
                }

                // Totals
                $orderTotal = 0;
                $orderPoundage = 0;
                $orderBalance = 0;
                $orderFreight = 0;
                $productTotal = $product['total'];
                if (isset($this->session->data['quote_product'])) {
                    foreach ($this->session->data['quote_product'] as $quote_key => $quote) {
                        $expire_time = isset($this->session->data['quote_' . $quote['quote_id']]['expire_time']) ? $this->session->data['quote_' . $quote['quote_id']]['expire_time'] : 0;
                        if ($product['cart_id'] == $quote_key && (int)$product['quantity'] == (int)$quote['quantity'] && time() > $expire_time) {
                            //订单议价对应订单,议价议价超时时间
                            $this->session->data['quote_' . $quote['quote_id']]['order_id'] = $order_id;
                            $this->session->data['quote_' . $quote['quote_id']]['expire_time'] = strtotime($now) + $this->config->get('expire_time') * 60;
                            $productTotal = $quote['quote_price'] * $product['quantity'];
                        }
                    }
                }

                if (isset($data['totals'])) {
                    foreach ($data['totals'] as $total) {
                        if ($total['code'] == 'total') {
                            $orderTotal = $total['value'];
                        }
                        if ($total['code'] == 'poundage') {
                            $orderPoundage = $total['value'];
                        }
                        if ($total['code'] == 'balance') {
                            $orderBalance = $total['value'];
                        }
                        if ($total['code'] == 'freight') {
                            $orderFreight = $total['value'];
                        }
                    }
                    $freightDiff = 0;//运费差额，只有云送仓的体积小于CLOUD_LOGISTICS_VOLUME_LOWER才会有
                    if ($isCollectionFromDomicile) {
                        $freightAllProduct = ($product['package_fee']) * $product['quantity'];
                    } else {
                        if ($this->session->data['delivery_type'] == 2 && $volumeAll < CLOUD_LOGISTICS_VOLUME_LOWER) {
                            $freightDiff = ($orderFreight - $packageFeeAll - $freightAll) / $quantityAll;
                            $freightAllProduct = ($product['freight_per'] + $product['package_fee'] + $freightDiff) * $product['quantity'];
                        } else {
                            $freightAllProduct = ($product['freight_per'] + $product['package_fee']) * $product['quantity'];
                        }
                    }
                    $_temp = ($orderTotal - $orderPoundage - $orderBalance) == 0 ? 1 : ($orderTotal - $orderPoundage - $orderBalance);
                    $poundage = $this->up6down4(($productTotal + $freightAllProduct) / ($_temp) * $orderPoundage, 2);

                    $orderProductData['poundage'] = $poundage;
                    $orderProductData['freight_difference_per'] = $freightDiff;
                    $orderProductData['freight_per'] = $freight_per + $freightDiff;
                    $orderProductData['base_freight'] = $baseFreight;
                    $orderProductData['overweight_surcharge'] = $overweightSurcharge;

                }

                $order_product_id = $this->orm->table(DB_PREFIX . 'order_product')
                    ->insertGetId($orderProductData);
                // #29414限时限量活动记录订单信息
                app(MarketingTimeLimitDiscountService::class)->addTimeLimitProductLog($product['discount_info'], $order_id, $order_product_id, $orderProductData);
                foreach ($product['option'] as $option) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "order_option SET order_id = '" . (int)$order_id . "', order_product_id = '" . (int)$order_product_id . "', product_option_id = '" . (int)$option['product_option_id'] . "', product_option_value_id = '" . (int)$option['product_option_value_id'] . "', name = '" . $this->db->escape($option['name']) . "', `value` = '" . $this->db->escape($option['value']) . "', `type` = '" . $this->db->escape($option['type']) . "'");
                }

                //记录采购订单的基础数据信息
                $this->recordOrderProductInfo($order_product_id, $product);

//                if ($auto_buy) {
//                    //插入采购订单和销售订单关系;加上绑定关系的过滤，过滤掉在yzcm使用绑定库存的明细记录，防止重复记录tb_sys_order_combo映射
//                    $orderQuery = $this->db->query("SELECT p.sku,ctl.id AS lineId,ctl.image_id AS imageId,ctp.customer_id AS sellerId,ctl.qty,oa.qty as bindQty,ctl.combo_info
//                    FROM oc_product p INNER JOIN oc_customerpartner_to_product ctp ON p.product_id = ctp.product_id
//                    INNER JOIN tb_sys_customer_sales_order_line ctl ON p.sku = ctl.item_code
//                    LEFT JOIN tb_sys_order_associated oa ON oa.sales_order_id = ctl.header_id AND oa.sales_order_line_id = ctl.id
//                    WHERE p.product_id = " . (int)$product['product_id'] . " AND ctl.header_id = " . (int)$saleOrderId);
//                    $combo_info_before = null;
//                    if ($orderQuery->num_rows) {
//                        foreach ($orderQuery->rows as $saleOrderInfo) {
//                            //combo关系记录
//                            $buy_qty = intval($saleOrderInfo['qty']) - intval($saleOrderInfo['bindQty']);
//                            if (isset($product['subProducts']) && !empty($product['subProducts']) && $buy_qty > 0) {
//                                $lineComboArray = $orderLineToCombo[$saleOrderInfo['lineId']] ?? [];
//                                $comboMap = array($saleOrderInfo['sku'] => $buy_qty);
//                                foreach ($product['subProducts'] as $combo) {
//                                    $comboMap[$combo['sub_mpn']] = (int)$combo['sub_qty'];
//                                }
//                                $lineComboArray[] = $comboMap;
//                                $orderLineToCombo[$saleOrderInfo['lineId']] = $lineComboArray;
//                            }
//                            $combo_info_before = $saleOrderInfo['combo_info'];
//                            if (!empty($combo_info_before)) {
//                                $before_combo_map = json_decode($combo_info_before, true);
//                                if ($before_combo_map) {
//                                    $orderLineToCombo[$saleOrderInfo['lineId']] = array_merge($orderLineToCombo[$saleOrderInfo['lineId']], $before_combo_map);
//                                }
//                            }
//                        }
//                    }
//                }
            }

            //更新销售订单combo json字段
            if (!empty($orderLineToCombo)) {
                $keys = array_keys($orderLineToCombo);
                foreach ($keys as $lineId) {
                    $comboMap = $orderLineToCombo[$lineId];
                    $this->db->query("UPDATE tb_sys_customer_sales_order_line SET combo_info = '" . json_encode($comboMap) . "' WHERE id = " . $lineId);
                }
            }
        }

        // Gift Voucher
        $this->load->model('extension/total/voucher');

        // Vouchers
        if (isset($data['vouchers'])) {
            foreach ($data['vouchers'] as $voucher) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "order_voucher SET order_id = '" . (int)$order_id . "', description = '" . $this->db->escape($voucher['description']) . "', code = '" . $this->db->escape($voucher['code']) . "', from_name = '" . $this->db->escape($voucher['from_name']) . "', from_email = '" . $this->db->escape($voucher['from_email']) . "', to_name = '" . $this->db->escape($voucher['to_name']) . "', to_email = '" . $this->db->escape($voucher['to_email']) . "', voucher_theme_id = '" . (int)$voucher['voucher_theme_id'] . "', message = '" . $this->db->escape($voucher['message']) . "', amount = '" . (float)$voucher['amount'] . "'");

                $order_voucher_id = $this->db->getLastId();

                $voucher_id = $this->model_extension_total_voucher->addVoucher($order_id, $voucher);

                $this->db->query("UPDATE " . DB_PREFIX . "order_voucher SET voucher_id = '" . (int)$voucher_id . "' WHERE order_voucher_id = '" . (int)$order_voucher_id . "'");
            }
        }

        // Totals
        if (isset($data['totals'])) {
            foreach ($data['totals'] as $total) {
                $this->orm->table(DB_PREFIX . 'order_total')
                    ->insert([
                        'order_id' => $order_id,
                        'code' => $total['code'],
                        'title' => $total['title'],
                        'value' => (float)$total['value'],
                        'sort_order' => $total['sort_order']
                    ]);
            }
        }

        return $order_id;
    }

    /**
     * 四舍六入
     * @param $num
     * @param $n
     * @return float|int
     */
    function up6down4($num, $n)
    {
        $pow = pow(10, $n);
        $con_a = floor(round($num * $pow * 10, 1));
        $con_b = floor(round($num * $pow, 1));
        $con_c = ($num * $pow * 10);
        $len = strlen(str_replace('.', '', $con_c)) - strlen($con_a);

        //舍去位为5 && 舍去位后无有效数字 && 舍去位前一位是偶数 ->不进位
        if (($con_a % 5 == 0) && bccomp($con_a, $con_c, ($len)) == 0 && ($con_b % 2 == 0)) {
            return floor($num * $pow) / $pow;
        } else {//四舍五入
            return round($num, $n);
        }
    }

    /**
     *
     * @deprecated by chenyang 2019/10/22 自动购买的订单处理逻辑，整合至普通的采购流程
     * @param $data
     * @param $saleOrderId
     * @return int
     */
    public function apiAddOrder($data, $saleOrderId)
    {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "order` SET invoice_prefix = '" . $this->db->escape($data['invoice_prefix']) . "', store_id = '" . (int)$data['store_id'] . "', store_name = '" . $this->db->escape($data['store_name']) . "', store_url = '" . $this->db->escape($data['store_url']) . "', customer_id = '" . (int)$data['customer_id'] . "', customer_group_id = '" . (int)$data['customer_group_id'] . "', firstname = '" . $this->db->escape($data['firstname']) . "', lastname = '" . $this->db->escape($data['lastname']) . "', email = '" . $this->db->escape($data['email']) . "', telephone = '" . $this->db->escape($data['telephone']) . "', custom_field = '" . $this->db->escape(isset($data['custom_field']) ? json_encode($data['custom_field']) : '') . "', payment_firstname = '" . $this->db->escape($data['payment_firstname']) . "', payment_lastname = '" . $this->db->escape($data['payment_lastname']) . "', payment_company = '" . $this->db->escape($data['payment_company']) . "', payment_address_1 = '" . $this->db->escape($data['payment_address_1']) . "', payment_address_2 = '" . $this->db->escape($data['payment_address_2']) . "', payment_city = '" . $this->db->escape($data['payment_city']) . "', payment_postcode = '" . $this->db->escape($data['payment_postcode']) . "', payment_country = '" . $this->db->escape($data['payment_country']) . "', payment_country_id = '" . (int)$data['payment_country_id'] . "', payment_zone = '" . $this->db->escape($data['payment_zone']) . "', payment_zone_id = '" . (int)$data['payment_zone_id'] . "', payment_address_format = '" . $this->db->escape($data['payment_address_format']) . "', payment_custom_field = '" . $this->db->escape(isset($data['payment_custom_field']) ? json_encode($data['payment_custom_field']) : '') . "', payment_method = '" . $this->db->escape($data['payment_method']) . "', payment_code = '" . $this->db->escape($data['payment_code']) . "', shipping_firstname = '" . $this->db->escape($data['shipping_firstname']) . "', shipping_lastname = '" . $this->db->escape($data['shipping_lastname']) . "', shipping_company = '" . $this->db->escape($data['shipping_company']) . "', shipping_address_1 = '" . $this->db->escape($data['shipping_address_1']) . "', shipping_address_2 = '" . $this->db->escape($data['shipping_address_2']) . "', shipping_city = '" . $this->db->escape($data['shipping_city']) . "', shipping_postcode = '" . $this->db->escape($data['shipping_postcode']) . "', shipping_country = '" . $this->db->escape($data['shipping_country']) . "', shipping_country_id = '" . (int)$data['shipping_country_id'] . "', shipping_zone = '" . $this->db->escape($data['shipping_zone']) . "', shipping_zone_id = '" . (int)$data['shipping_zone_id'] . "', shipping_address_format = '" . $this->db->escape($data['shipping_address_format']) . "', shipping_custom_field = '" . $this->db->escape(isset($data['shipping_custom_field']) ? json_encode($data['shipping_custom_field']) : '') . "', shipping_method = '" . $this->db->escape($data['shipping_method']) . "', shipping_code = '" . $this->db->escape($data['shipping_code']) . "', comment = '" . $this->db->escape($data['comment']) . "', total = '" . (float)$data['total'] . "', affiliate_id = '" . (int)$data['affiliate_id'] . "', commission = '" . (float)$data['commission'] . "', marketing_id = '" . (int)$data['marketing_id'] . "', tracking = '" . $this->db->escape($data['tracking']) . "', language_id = '" . (int)$data['language_id'] . "', currency_id = '" . (int)$data['currency_id'] . "', currency_code = '" . $this->db->escape($data['currency_code']) . "', currency_value = '" . (float)$data['currency_value'] . "',current_currency_value='" . (float)$data['current_currency_value'] . "', ip = '" . $this->db->escape($data['ip']) . "', forwarded_ip = '" . $this->db->escape($data['forwarded_ip']) . "', user_agent = '" . $this->db->escape($data['user_agent']) . "', accept_language = '" . $this->db->escape($data['accept_language']) . "', date_added = NOW(), date_modified = NOW()");

        $order_id = $this->db->getLastId();

        // Products
        if (isset($data['products'])) {
            $orderLineToCombo = array();

            foreach ($data['products'] as $product) {
                $sql = "INSERT INTO " . DB_PREFIX . "order_product SET order_id = '" . (int)$order_id . "', product_id = '" . (int)$product['product_id'] . "', name = '" . $this->db->escape($product['name']) . "', model = '" . $this->db->escape($product['model']) . "', quantity = '" . (int)$product['quantity'] . "', price = '" . (float)$product['price'] . "', total = '" . (float)$product['total'] . "',service_fee = '" . (double)$product['serviceFee'] . "',service_fee_per = '" . (double)$product['serviceFeePer'] . "', tax = '" . (float)$product['tax'] . "', reward = '" . (int)$product['reward'] . "'";

                // Totals
                if (isset($data['totals'])) {
                    foreach ($data['totals'] as $total) {
                        if ($total['code'] == 'sub_total') {
                            $subTotal = $total['value'];
                        }
                    }

                    foreach ($data['totals'] as $total) {
                        if ($total['code'] == 'poundage') {
                            $poundage = $this->up6down4($product['total'] / $subTotal * $total['value'], 2);
                            $sql .= ",poundage = '" . $poundage . "'";
                        }
                    }
                }

                $this->db->query($sql);

                $order_product_id = $this->db->getLastId();

                foreach ($product['option'] as $option) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "order_option SET order_id = '" . (int)$order_id . "', order_product_id = '" . (int)$order_product_id . "', product_option_id = '" . (int)$option['product_option_id'] . "', product_option_value_id = '" . (int)$option['product_option_value_id'] . "', name = '" . $this->db->escape($option['name']) . "', `value` = '" . $this->db->escape($option['value']) . "', `type` = '" . $this->db->escape($option['type']) . "'");
                }

                $this->recordPurchaseAccountingBaseData($order_product_id);

                //插入采购订单和销售订单关系;加上绑定关系的过滤，过滤掉在yzcm使用绑定库存的明细记录，防止重复记录tb_sys_order_combo映射
                $orderQuery = $this->db->query("SELECT p.sku,ctl.id AS lineId,ctl.image_id AS imageId,ctp.customer_id AS sellerId,ctl.qty,oa.qty as bindQty,ctl.combo_info
                    FROM oc_product p INNER JOIN oc_customerpartner_to_product ctp ON p.product_id = ctp.product_id
                    INNER JOIN tb_sys_customer_sales_order_line ctl ON p.sku = ctl.item_code
                    LEFT JOIN tb_sys_order_associated oa ON oa.sales_order_id = ctl.header_id AND oa.sales_order_line_id = ctl.id
                    WHERE p.product_id = " . (int)$product['product_id'] . " AND ctl.header_id = " . (int)$saleOrderId);
                $combo_info_before = null;
                if ($orderQuery->num_rows) {
                    foreach ($orderQuery->rows as $saleOrderInfo) {
                        //combo关系记录
                        $buy_qty = intval($saleOrderInfo['qty']) - intval($saleOrderInfo['bindQty']);
                        if (isset($product['subProducts']) && !empty($product['subProducts']) && $buy_qty > 0) {
                            $lineComboArray = $orderLineToCombo[$saleOrderInfo['lineId']];
                            $comboMap = array($saleOrderInfo['sku'] => $buy_qty);
                            foreach ($product['subProducts'] as $combo) {
                                $this->db->query("INSERT INTO tb_sys_order_combo SET product_id = " . (int)$product['product_id'] . ",item_code = '" . $saleOrderInfo['sku'] . "',order_id = " . (int)$order_id . ",order_product_id = " . (int)$order_product_id . ",set_product_id = " . (int)$combo['sub_productId'] . ",set_item_code = '" . $combo['sub_mpn'] . "',qty = " . (int)$combo['sub_qty']);
                                $comboMap[$combo['sub_mpn']] = (int)$combo['sub_qty'];
                            }
                            $lineComboArray[] = $comboMap;
                            $orderLineToCombo[$saleOrderInfo['lineId']] = $lineComboArray;
                        }
                        $combo_info_before = $saleOrderInfo['combo_info'];
                        if (!empty($combo_info_before)) {
                            $before_combo_map = json_decode($combo_info_before);
                            if ($before_combo_map) {
                                array_push($orderLineToCombo, $before_combo_map);
                            }
                        }
                    }
                }
            }

            //更新销售订单combo json字段
            if (!empty($orderLineToCombo)) {
                $keys = array_keys($orderLineToCombo);
                foreach ($keys as $lineId) {
                    $comboMap = $orderLineToCombo[$lineId];
                    $this->db->query("UPDATE tb_sys_customer_sales_order_line SET combo_info = '" . json_encode($comboMap) . "' WHERE id = " . $lineId);
                }
            }
        }

        // Gift Voucher
        $this->load->model('extension/total/voucher');

        // Vouchers
        if (isset($data['vouchers'])) {
            foreach ($data['vouchers'] as $voucher) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "order_voucher SET order_id = '" . (int)$order_id . "', description = '" . $this->db->escape($voucher['description']) . "', code = '" . $this->db->escape($voucher['code']) . "', from_name = '" . $this->db->escape($voucher['from_name']) . "', from_email = '" . $this->db->escape($voucher['from_email']) . "', to_name = '" . $this->db->escape($voucher['to_name']) . "', to_email = '" . $this->db->escape($voucher['to_email']) . "', voucher_theme_id = '" . (int)$voucher['voucher_theme_id'] . "', message = '" . $this->db->escape($voucher['message']) . "', amount = '" . (float)$voucher['amount'] . "'");

                $order_voucher_id = $this->db->getLastId();

                $voucher_id = $this->model_extension_total_voucher->addVoucher($order_id, $voucher);

                $this->db->query("UPDATE " . DB_PREFIX . "order_voucher SET voucher_id = '" . (int)$voucher_id . "' WHERE order_voucher_id = '" . (int)$order_voucher_id . "'");
            }
        }

        // Totals
        if (isset($data['totals'])) {
            foreach ($data['totals'] as $total) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "order_total SET order_id = '" . (int)$order_id . "', code = '" . $this->db->escape($total['code']) . "', title = '" . $this->db->escape($total['title']) . "', `value` = '" . (float)$total['value'] . "', sort_order = '" . (int)$total['sort_order'] . "'");
            }
        }

        return $order_id;
    }

    public function editOrder($order_id, $data)
    {
        // Void the order first
        $this->addOrderHistory($order_id, 0);

        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET invoice_prefix = '" . $this->db->escape($data['invoice_prefix']) . "', store_id = '" . (int)$data['store_id'] . "', store_name = '" . $this->db->escape($data['store_name']) . "', store_url = '" . $this->db->escape($data['store_url']) . "', customer_id = '" . (int)$data['customer_id'] . "', customer_group_id = '" . (int)$data['customer_group_id'] . "', firstname = '" . $this->db->escape($data['firstname']) . "', lastname = '" . $this->db->escape($data['lastname']) . "', email = '" . $this->db->escape($data['email']) . "', telephone = '" . $this->db->escape($data['telephone']) . "', custom_field = '" . $this->db->escape(json_encode($data['custom_field'])) . "', payment_firstname = '" . $this->db->escape($data['payment_firstname']) . "', payment_lastname = '" . $this->db->escape($data['payment_lastname']) . "', payment_company = '" . $this->db->escape($data['payment_company']) . "', payment_address_1 = '" . $this->db->escape($data['payment_address_1']) . "', payment_address_2 = '" . $this->db->escape($data['payment_address_2']) . "', payment_city = '" . $this->db->escape($data['payment_city']) . "', payment_postcode = '" . $this->db->escape($data['payment_postcode']) . "', payment_country = '" . $this->db->escape($data['payment_country']) . "', payment_country_id = '" . (int)$data['payment_country_id'] . "', payment_zone = '" . $this->db->escape($data['payment_zone']) . "', payment_zone_id = '" . (int)$data['payment_zone_id'] . "', payment_address_format = '" . $this->db->escape($data['payment_address_format']) . "', payment_custom_field = '" . $this->db->escape(json_encode($data['payment_custom_field'])) . "', payment_method = '" . $this->db->escape($data['payment_method']) . "', payment_code = '" . $this->db->escape($data['payment_code']) . "', shipping_firstname = '" . $this->db->escape($data['shipping_firstname']) . "', shipping_lastname = '" . $this->db->escape($data['shipping_lastname']) . "', shipping_company = '" . $this->db->escape($data['shipping_company']) . "', shipping_address_1 = '" . $this->db->escape($data['shipping_address_1']) . "', shipping_address_2 = '" . $this->db->escape($data['shipping_address_2']) . "', shipping_city = '" . $this->db->escape($data['shipping_city']) . "', shipping_postcode = '" . $this->db->escape($data['shipping_postcode']) . "', shipping_country = '" . $this->db->escape($data['shipping_country']) . "', shipping_country_id = '" . (int)$data['shipping_country_id'] . "', shipping_zone = '" . $this->db->escape($data['shipping_zone']) . "', shipping_zone_id = '" . (int)$data['shipping_zone_id'] . "', shipping_address_format = '" . $this->db->escape($data['shipping_address_format']) . "', shipping_custom_field = '" . $this->db->escape(json_encode($data['shipping_custom_field'])) . "', shipping_method = '" . $this->db->escape($data['shipping_method']) . "', shipping_code = '" . $this->db->escape($data['shipping_code']) . "', comment = '" . $this->db->escape($data['comment']) . "', total = '" . (float)$data['total'] . "', affiliate_id = '" . (int)$data['affiliate_id'] . "', commission = '" . (float)$data['commission'] . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");

        $this->db->query("DELETE FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");
        $this->db->query("DELETE FROM " . DB_PREFIX . "order_option WHERE order_id = '" . (int)$order_id . "'");

        // Products
        if (isset($data['products'])) {
            foreach ($data['products'] as $product) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "order_product SET order_id = '" . (int)$order_id . "', product_id = '" . (int)$product['product_id'] . "', name = '" . $this->db->escape($product['name']) . "', model = '" . $this->db->escape($product['model']) . "', quantity = '" . (int)$product['quantity'] . "', price = '" . (float)$product['price'] . "', total = '" . (float)$product['total'] . "', tax = '" . (float)$product['tax'] . "', reward = '" . (int)$product['reward'] . "'");

                $order_product_id = $this->db->getLastId();

                foreach ($product['option'] as $option) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "order_option SET order_id = '" . (int)$order_id . "', order_product_id = '" . (int)$order_product_id . "', product_option_id = '" . (int)$option['product_option_id'] . "', product_option_value_id = '" . (int)$option['product_option_value_id'] . "', name = '" . $this->db->escape($option['name']) . "', `value` = '" . $this->db->escape($option['value']) . "', `type` = '" . $this->db->escape($option['type']) . "'");
                }
            }
        }

        // Gift Voucher
        $this->load->model('extension/total/voucher');

        $this->model_extension_total_voucher->disableVoucher($order_id);

        // Vouchers
        $this->db->query("DELETE FROM " . DB_PREFIX . "order_voucher WHERE order_id = '" . (int)$order_id . "'");

        if (isset($data['vouchers'])) {
            foreach ($data['vouchers'] as $voucher) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "order_voucher SET order_id = '" . (int)$order_id . "', description = '" . $this->db->escape($voucher['description']) . "', code = '" . $this->db->escape($voucher['code']) . "', from_name = '" . $this->db->escape($voucher['from_name']) . "', from_email = '" . $this->db->escape($voucher['from_email']) . "', to_name = '" . $this->db->escape($voucher['to_name']) . "', to_email = '" . $this->db->escape($voucher['to_email']) . "', voucher_theme_id = '" . (int)$voucher['voucher_theme_id'] . "', message = '" . $this->db->escape($voucher['message']) . "', amount = '" . (float)$voucher['amount'] . "'");

                $order_voucher_id = $this->db->getLastId();

                $voucher_id = $this->model_extension_total_voucher->addVoucher($order_id, $voucher);

                $this->db->query("UPDATE " . DB_PREFIX . "order_voucher SET voucher_id = '" . (int)$voucher_id . "' WHERE order_voucher_id = '" . (int)$order_voucher_id . "'");
            }
        }

        // Totals
        $this->db->query("DELETE FROM " . DB_PREFIX . "order_total WHERE order_id = '" . (int)$order_id . "'");

        if (isset($data['totals'])) {
            foreach ($data['totals'] as $total) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "order_total SET order_id = '" . (int)$order_id . "', code = '" . $this->db->escape($total['code']) . "', title = '" . $this->db->escape($total['title']) . "', `value` = '" . (float)$total['value'] . "', sort_order = '" . (int)$total['sort_order'] . "'");
            }
        }
    }

    public function deleteOrder($order_id)
    {
        // Void the order first
        $this->addOrderHistory($order_id, 0);

        $this->db->query("DELETE FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "order_product` WHERE order_id = '" . (int)$order_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "order_option` WHERE order_id = '" . (int)$order_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "order_voucher` WHERE order_id = '" . (int)$order_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . (int)$order_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "order_history` WHERE order_id = '" . (int)$order_id . "'");
        $this->db->query("DELETE `or`, ort FROM `" . DB_PREFIX . "order_recurring` `or`, `" . DB_PREFIX . "order_recurring_transaction` `ort` WHERE order_id = '" . (int)$order_id . "' AND ort.order_recurring_id = `or`.order_recurring_id");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "customer_transaction` WHERE order_id = '" . (int)$order_id . "'");

        // Gift Voucher
        $this->load->model('extension/total/voucher');

        $this->model_extension_total_voucher->disableVoucher($order_id);
    }

    public function getOrder($order_id)
    {
        $order_query = $this->db->query("SELECT *, (SELECT os.name FROM `" . DB_PREFIX . "order_status` os WHERE os.order_status_id = o.order_status_id AND os.language_id = o.language_id) AS order_status FROM `" . DB_PREFIX . "order` o WHERE o.order_id = '" . (int)$order_id . "'");

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

            $this->load->model('localisation/language');

            $language_info = $this->model_localisation_language->getLanguage($order_query->row['language_id']);

            if ($language_info) {
                $language_code = $language_info['code'];
            } else {
                $language_code = $this->config->get('config_language');
            }

            return array(
                'order_id' => $order_query->row['order_id'],
                'invoice_no' => $order_query->row['invoice_no'],
                'invoice_prefix' => $order_query->row['invoice_prefix'],
                'store_id' => $order_query->row['store_id'],
                'store_name' => $order_query->row['store_name'],
                'store_url' => $order_query->row['store_url'],
                'customer_id' => $order_query->row['customer_id'],
                'firstname' => $order_query->row['firstname'],
                'lastname' => $order_query->row['lastname'],
                'email' => $order_query->row['email'],
                'telephone' => $order_query->row['telephone'],
                'custom_field' => json_decode($order_query->row['custom_field'], true),
                'payment_firstname' => $order_query->row['payment_firstname'],
                'payment_lastname' => $order_query->row['payment_lastname'],
                'payment_company' => $order_query->row['payment_company'],
                'payment_address_1' => $order_query->row['payment_address_1'],
                'payment_address_2' => $order_query->row['payment_address_2'],
                'payment_postcode' => $order_query->row['payment_postcode'],
                'payment_city' => $order_query->row['payment_city'],
                'payment_zone_id' => $order_query->row['payment_zone_id'],
                'payment_zone' => $order_query->row['payment_zone'],
                'payment_zone_code' => $payment_zone_code,
                'payment_country_id' => $order_query->row['payment_country_id'],
                'payment_country' => $order_query->row['payment_country'],
                'payment_iso_code_2' => $payment_iso_code_2,
                'payment_iso_code_3' => $payment_iso_code_3,
                'payment_address_format' => $order_query->row['payment_address_format'],
                'payment_custom_field' => json_decode($order_query->row['payment_custom_field'], true),
                'payment_method' => $order_query->row['payment_method'],
                'payment_code' => $order_query->row['payment_code'],
                'shipping_firstname' => $order_query->row['shipping_firstname'],
                'shipping_lastname' => $order_query->row['shipping_lastname'],
                'shipping_company' => $order_query->row['shipping_company'],
                'shipping_address_1' => $order_query->row['shipping_address_1'],
                'shipping_address_2' => $order_query->row['shipping_address_2'],
                'shipping_postcode' => $order_query->row['shipping_postcode'],
                'shipping_city' => $order_query->row['shipping_city'],
                'shipping_zone_id' => $order_query->row['shipping_zone_id'],
                'shipping_zone' => $order_query->row['shipping_zone'],
                'shipping_zone_code' => $shipping_zone_code,
                'shipping_country_id' => $order_query->row['shipping_country_id'],
                'shipping_country' => $order_query->row['shipping_country'],
                'shipping_iso_code_2' => $shipping_iso_code_2,
                'shipping_iso_code_3' => $shipping_iso_code_3,
                'shipping_address_format' => $order_query->row['shipping_address_format'],
                'shipping_custom_field' => json_decode($order_query->row['shipping_custom_field'], true),
                'shipping_method' => $order_query->row['shipping_method'],
                'shipping_code' => $order_query->row['shipping_code'],
                'comment' => $order_query->row['comment'],
                'total' => $order_query->row['total'],
                'order_status_id' => $order_query->row['order_status_id'],
                'order_status' => $order_query->row['order_status'],
                'affiliate_id' => $order_query->row['affiliate_id'],
                'commission' => $order_query->row['commission'],
                'language_id' => $order_query->row['language_id'],
                'language_code' => $language_code,
                'currency_id' => $order_query->row['currency_id'],
                'currency_code' => $order_query->row['currency_code'],
                'currency_value' => $order_query->row['currency_value'],
                'ip' => $order_query->row['ip'],
                'forwarded_ip' => $order_query->row['forwarded_ip'],
                'user_agent' => $order_query->row['user_agent'],
                'accept_language' => $order_query->row['accept_language'],
                'date_added' => $order_query->row['date_added'],
                'date_modified' => $order_query->row['date_modified'],
                'delivery_type' => $order_query->row['delivery_type']
            );
        } else {
            return false;
        }
    }

    public function getOrderProducts($order_id)
    {
        $orderProductInfo = $this->orm->table("oc_order_product as oop")
            ->leftJoin('oc_order_quote as ooq', [['ooq.order_id', '=', 'oop.order_id'], ['ooq.product_id', '=', 'oop.product_id']])
            ->leftJoin('oc_product_quote as opq', [['ooq.quote_id', '=', 'opq.id'], ['opq.product_id', '=', 'oop.product_id']])
            ->selectRaw('if(opq.price is null,oop.price+oop.service_fee_per,opq.price) as price,oop.freight_per,oop.package_fee,oop.product_id,oop.quantity,oop.name')
            ->where('oop.order_id', '=', $order_id)
//            ->whereNotIn('oop.product_id',$sellerProductId)
            ->get();
        return obj2array($orderProductInfo);
    }

    public function getOrderOptions($order_id, $order_product_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_option WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '" . (int)$order_product_id . "'");

        return $query->rows;
    }

    public function getOrderVouchers($order_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_voucher WHERE order_id = '" . (int)$order_id . "'");

        return $query->rows;
    }

    public function getOrderTotals($order_id)
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . (int)$order_id . "' ORDER BY sort_order ASC");

        return $query->rows;
    }

    /**
     * 采购订单完成后的数据刷新，更新订单状态，发送通知，增加销售订单的绑定关系等操作
     *
     * @param int|null $order_id 采购订单号
     * @param array $feeOrderIdArr
     * @param int $order_status_id 采购订单状态码
     * @param string $comment 客户留言
     * @param bool $notify
     * @param bool $override
     * @param null $sale_order_id 如果设置了值，则表示是指定了销售订单进行绑定关系绑定，目前只有自动购买使用此流程
     * @throws AssociatedException
     */
    public function addOrderHistoryByYzcModel($order_id, $feeOrderIdArr, $order_status_id, $comment = '', $notify = false, $override = false, $sale_order_id = null)
    {
       load()->model('account/customer_order_import');
        $customerId = null;
        if(!empty($order_id)){
            $customerId = $this->orm->table('oc_order')
                ->where('order_id','=',$order_id)
                ->value('customer_id');
        }
        if(!empty($feeOrderIdArr)){
            $feeOrderInfos = app(FeeOrderRepository::class)->findFeeOrderInfo($feeOrderIdArr);
            $customerId = $feeOrderInfos[0]['buyer_id'];
        }
        $customer = $customerId ? Customer::find($customerId) : null;
        $lock = Locker::order($customerId, 5);
        if ($lock->acquire(true)) {
            // 根据订单Id获取订单
            $order_info = $this->getOrder($order_id);
            if ($order_info) {
                // Fraud Detection
                $this->load->model('account/customer');
                // 更改订单状态
                $this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = '" . (int)$order_status_id . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");
                // 插入OrderHistory
                $this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$order_id . "', order_status_id = '" . (int)$order_status_id . "', notify = '" . (int)$notify . "', comment = '" . $this->db->escape($comment) . "', date_added = NOW()");

                //保证金相关订单 5订单完成
                if ($order_status_id == OcOrderStatus::COMPLETED) {
                    //保证金
                    $this->load->model('account/product_quotes/margin');
                    $this->model_account_product_quotes_margin->marginDealAfterPay($order_info, $customer->country_id);

                    //期货
                    $this->load->model('futures/agreement');
                    $this->model_futures_agreement->handleFuturesProcess($order_info['order_id']);
                }
                /**
                 * Marketplace Code Starts here
                 */
                $order_status_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_status WHERE order_status_id = '" . (int)$order_status_id . "' AND language_id = '" . (int)$order_info['language_id'] . "'");

                if ($order_status_query->num_rows) {
                    $order_status = $order_status_query->row['name'];
                } else {
                    $order_status = '';
                }

                if ($this->config->get('module_marketplace_status')) {
                    $this->load->model('account/customerpartnerorder');
                    $this->model_account_customerpartnerorder->customerpartner($order_info, $order_status, $comment, $order_status_id);
                }
                /**
                 * combo 产品添加购买时的关系
                 */
//            $this->load->model('account/customer_order_import');
                $this->load->model('account/deliverySignature');
//            $this->model_account_customer_order_import->associateComboProduct($order_id);


                /**
                 * 自营商品扣减库存
                 */
                // 根据orderId查询是否购买了自营商品
                $selfOperatedOrder = $this->model_account_customerpartnerorder->getSellerOrderInfo($order_id, 5)->rows;
                if ($selfOperatedOrder) {
                    // 定义SysReceive
                    $sysReceive = new SysReceive();
                    $sysReceive->buyer_id = $order_info['customer_id'];
                    $sysReceive->source_header_id = $order_id;
                    $sysReceive->transaction_type = 1;
                    $sysReceive->create_user_name = $order_info['customer_id'];
                    $sysReceive->program_code = PROGRAM_CODE;
                    $sysReceive->line_count = count($selfOperatedOrder);
                    $sysReceiveLineArr = array();
                    $subTotal = 0;
                    $this->load->model('account/notification');
                    //受影响的产品
                    $affectProduct = array();
                    foreach ($selfOperatedOrder as $selfOperatedProduct) {

                        //add by xxl 扣减seller的批次库存,增加seller的出库记录
//                    $this->updateAndSaveSellerBatch($selfOperatedProduct, $order_info);
                        //根据预扣库存,增加seller出库记录
                        $this->addSellerDeliveryInfo($selfOperatedProduct);
                        //end xxl

                        /*
                         * 发送低库存提醒
                         * $affectProduct受影响的产品
                         */
                        array_push($affectProduct, $selfOperatedProduct['product_id']);
                        //查询该产品combo组成
                        $comboInfos = $this->getComboInfo($selfOperatedProduct['product_id']);
                        foreach ($comboInfos as $comboInfo) {
                            array_push($affectProduct, $comboInfo['set_product_id']);
                        }
                        //查询tb_sys_seller_delivery_pre_line_back获取受影响的库存
                        $deliveryLineBackInfos = $this->getAffectProduct($selfOperatedProduct['order_product_id']);
                        foreach ($deliveryLineBackInfos as $deliveryLineBackInfo) {
                            array_push($affectProduct, $deliveryLineBackInfo['product_id']);
                        }

                        // 扣减上架库存
//                    $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = (quantity - " . (int)$selfOperatedProduct['quantity'] . ") WHERE product_id = " . (int)$selfOperatedProduct['product_id'] . " AND subtract = '1'");
//                    $this->db->query("UPDATE " . DB_PREFIX . "customerpartner_to_product SET quantity = quantity-" . (int)$selfOperatedProduct['quantity'] . " WHERE product_id = " . (int)$selfOperatedProduct['product_id']);

                        // 添加 低库存提醒
                        //$this->model_account_notification->productStockActivity($selfOperatedProduct['product_id']);
                        //扣减库存的操作在生成订单时完成，当时已发出低库存提醒
                        //$this->addSystemMessageAboutProductStock($selfOperatedProduct['product_id']);

                        $order_options = $this->model_checkout_order->getOrderOptions($order_id, $selfOperatedProduct['product_id']);
                        foreach ($order_options as $order_option) {
                            $this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET quantity = (quantity - " . (int)$selfOperatedProduct['quantity'] . ") WHERE product_option_value_id = '" . (int)$order_option['product_option_value_id'] . "' AND subtract = '1'");
                        }

                        /**
                         * 1.同步扣减子SKU的上架库存数量
                         *     注: 如果在库数量 小于 上架数量, 则修改上架数量为最大可上架数量; 反之, 不变
                         * 2.同步更改子sku所属的其他combo的上架库存数量。
                         *     注: 如果在库数量 小于 上架数量, 则修改上架数量为最大可上架数量; 反之, 不变
                         */
//                    $setProductInfoArr = $this->db->query("select set_product_id,set_mpn,qty from tb_sys_product_set_info where product_id=" . $selfOperatedProduct['product_id'] . " and set_product_id is not null")->rows;
//                    foreach ($setProductInfoArr as $setProductInfo) {
//                        // 如果 set_product_id 为 空/NULL/0 则跳过，不处理。(未关联产品ID)
//                        if (!isset($setProductInfo['set_product_id']) || empty($setProductInfo['set_product_id'])) {
//                            continue;
//                        }
//
//                        // 同步扣减子SKU的库存
//                        $setProductInStockQuantity = $this->getProductInStockQuantity($setProductInfo['set_product_id']);
//                        $setProductOnShelfQuantity = $this->getProductOnSelfQuantity($setProductInfo['set_product_id']);
//                        // 如果在库数量 小于 上架数量, 则修改上架数量为最大可上架数量
//                        if ($setProductInStockQuantity < $setProductOnShelfQuantity) {
//                            $this->setProductOnShelfQuantity($setProductInfo['set_product_id'], $setProductInStockQuantity);
//                        }
//
//                        // 添加子 sku 的 notification 低库存提醒
//                        $this->model_account_notification->productStockActivity((int)$setProductInfo['set_product_id']);
//
//                        // 同步更改子sku所属的其他combo的上架库存数量。
//                        $this->updateOtherComboQuantity($setProductInfo['set_product_id'], $selfOperatedProduct['product_id']);
//                    }
//
//                    /**
//                     * 如果当前产品是 其他combo 的组成，则需要同步修改之
//                     */
//                    $this->updateOtherComboQuantity($selfOperatedProduct['product_id']);

                        $subTotal += (double)$selfOperatedProduct['price'];

                        $sysReceiveLine = new SysReceiveLine();
                        $sysReceiveLine->buyer_id = $order_info['customer_id'];
                        $sysReceiveLine->oc_order_id = $order_id;
                        $sysReceiveLine->oc_partner_order_id = $selfOperatedProduct['id'];
                        $sysReceiveLine->transaction_type = 1;
                        $sysReceiveLine->product_id = $selfOperatedProduct['product_id'];
                        $sysReceiveLine->receive_qty = $selfOperatedProduct['quantity'];
                        $sysReceiveLine->unit_price = ((double)$selfOperatedProduct['price'] / (int)$selfOperatedProduct['quantity']);
                        $sysReceiveLine->seller_id = $selfOperatedProduct['customer_id'];
                        $sysReceiveLine->create_user_name = $order_info['customer_id'];
                        $sysReceiveLine->program_code = PROGRAM_CODE;

                        //验证头款产品
                        $sql = 'SELECT ';
                        $sql .= 'margin_agreement_id from tb_sys_margin_process where advance_product_id = ' . $selfOperatedProduct['product_id'];
                        $margin_info = $this->db->query($sql)->row;
                        $margin_agreement_id = $margin_info['margin_agreement_id'] ?? 0;

                        //是否是期货头款
                        $advanceFutures = $this->model_futures_agreement->isFuturesAdvanceProduct($selfOperatedProduct['product_id']);
                        if (!$margin_agreement_id && !$advanceFutures) {
                            $sysReceiveLineArr[] = $sysReceiveLine;
                        }

                        //库存订阅提醒
//                    $this->load->model('account/wishlist');
//                    $this->model_account_wishlist->addCommunication($selfOperatedProduct['product_id']);
                    }
                    array_unique($affectProduct);
                    // 减去seller在库抵押物值
                    app(SellerAssetService::class)->subCollateralValueByOrder($order_id);

                    $this->load->model('account/wishlist');
                    foreach ($affectProduct as $product) {
                        $this->model_account_wishlist->addCommunication($product);
                    }
                    $sysReceive->sub_total = $subTotal;
                    $sysReceive->total = $subTotal;
                    // 插入sysReceive
                    // 验证有无明细，有明细则插入
                    if (count($sysReceiveLineArr)) {
                        $receiveId = $this->saveReceive($sysReceive);
                    }
                    $bind_cost_id = array();
                    foreach ($sysReceiveLineArr as $sysReceiveLine) {
                        $sysReceiveLine->receive_id = $receiveId;
                        $receiveLineId = $this->saveReceiveLine($sysReceiveLine);
                        // 收货记录
                        $sysCostDetail = new SysCostDetail();
                        $sysCostDetail->buyer_id = $sysReceiveLine->buyer_id;
                        $sysCostDetail->source_line_id = $receiveLineId;
                        $sysCostDetail->source_code = "NUS";
                        $sysCostDetail->sku_id = $sysReceiveLine->product_id;
                        $sysCostDetail->onhand_qty = $sysReceiveLine->receive_qty;
                        $sysCostDetail->original_qty = $sysReceiveLine->receive_qty;
                        $sysCostDetail->seller_id = $sysReceiveLine->seller_id;
                        $sysCostDetail->create_user_name = $sysReceiveLine->create_user_name;
                        $sysCostDetail->program_code = PROGRAM_CODE;
                        $cost_id = $this->saveCostDetail($sysCostDetail);

                        $sku_query = $this->db->query("SELECT p.sku FROM oc_product p WHERE p.product_id = " . (int)$sysReceiveLine->product_id);
                        $row = $sku_query->row;
                        $oc_order_query = $this->db->query("SELECT cto.order_id AS oc_order_id,cto.order_product_id AS oc_order_product_id FROM oc_customerpartner_to_order cto WHERE cto.id = " . (int)$sysReceiveLine->oc_partner_order_id);
                        $oc_row = $oc_order_query->row;
                        $bind_cost_id[] = array(
                            'oc_order_id' => $oc_row['oc_order_id'],
                            'order_product_id' => $oc_row['oc_order_product_id'],
                            'sku' => $row['sku'],
                            'cost_id' => $cost_id,
                            'qty' => $sysReceiveLine->receive_qty,
                            'product_id' => $sysReceiveLine->product_id,
                            'seller_id' => $sysReceiveLine->seller_id,
                            'buyer_id' => $sysReceiveLine->buyer_id
                        );
                    }

                    // 入仓租
                    app(StorageFeeService::class)->createByOrder($order_id);
                    // 运输方式
                    //默认的delivery_type =0
                    $delivery_type = isset($order_info['delivery_type']) ? $order_info['delivery_type'] : (OrderDeliveryType::DROP_SHIPPING);
                    $asr_expect_list = array();
                    if (isset($sale_order_id)) {
                        //设置了销售订单主键ID，制订了绑定关系的绑定对象
                        $orderAssociatedIds = $this->autoBuyBind($bind_cost_id, $sale_order_id);
                        //根据绑定的销售单与采购单的关系，绑定仓租
                        app(StorageFeeService::class)->bindByOrderAssociated($orderAssociatedIds);
                        //更新comboInfo
                        $this->updateComboInfo($sale_order_id);
                        //更新自动购买的订单的状态为BP
                        $this->model_account_customer_order_import->updateCustomerSalesOrder($sale_order_id);
                    } else if ($delivery_type != 2) {
                        //如果有预绑定的数据
                        $associatedRecords = $this->getOrderAssociatedRecord($order_id, $order_info['customer_id']);
                        //上门取货的buyer走以前的自动绑定逻辑
                        $customer_group_id = $this->commonFunction->getCustomerGroupId($order_info['customer_id']);
                        $isCollectionFromDomicile = in_array($customer_group_id, COLLECTION_FROM_DOMICILE);
                        $payment_code = $order_info['payment_code'];
                        if ($associatedRecords && count($associatedRecords) > 0) {
                            $canAssociatedArr = $this->associatedOrderByRecord($associatedRecords, $order_id);
                            $canNotAssociateArr = [];
                            if (!$isCollectionFromDomicile) {
                                // 上门取货不校验欧洲补运费
                                $canNotAssociateArr = $this->checkOrderCanAssociated(array_column($canAssociatedArr, 'sales_order_line_id'), $order_id);
                            }
                            $this->associatedSalesOrderAndUpdateStatus($canAssociatedArr, $canNotAssociateArr);

                            // 释放销售订单囤货预绑定
                            $canAssociatedIds = array_filter(array_unique(array_column($canAssociatedArr, 'sales_order_id')));
                            app(BuyerStockService::class)->releaseInventoryLockBySalesOrderPreAssociated($canAssociatedIds, (int)$order_info['customer_id']);
                        } else if (!$isCollectionFromDomicile && $payment_code == PayCode::PAY_VIRTUAL) {
                            // 检查更新订单状态
                            $productCostMap = $this->customer->getProductCostMap($order_info['customer_id']);
                            $productCostMapTemp = $productCostMap;
                            // 获取全部新订单
                            $bp_orders = $this->db->query("SELECT cso.order_id,csol.* FROM `tb_sys_customer_sales_order` cso LEFT JOIN `tb_sys_customer_sales_order_line` csol ON cso.`id` = csol.`header_id` WHERE cso.`buyer_id` = " . $order_info['customer_id'] . " AND cso.`order_status` = ".CustomerSalesOrderStatus::TO_BE_PAID." AND csol.item_status = ".CustomerSalesOrderLineItemStatus::PENDING." order by cso.run_id desc,csol.id desc")->rows;
                            $headerCustomerSalesOrderMap = array();
                            if ($bp_orders && count($bp_orders)) {
                                foreach ($bp_orders as $bp_order) {
                                    if (isset($headerCustomerSalesOrderMap[$bp_order['header_id']])) {
                                        $headerCustomerSalesOrderMap[$bp_order['header_id']][] = $bp_order;
                                    } else {
                                        $headerCustomerSalesOrderMap[$bp_order['header_id']] = array();
                                        $headerCustomerSalesOrderMap[$bp_order['header_id']][] = $bp_order;
                                    }
                                }
                                $keys = array_keys($headerCustomerSalesOrderMap);
                                $costQtyArr = array();
                                foreach ($keys as $key) {
                                    $lineDatas = $headerCustomerSalesOrderMap[$key];
                                    foreach ($lineDatas as $lineData) {
                                        $itemCode = strtoupper($lineData['item_code']);
//                                $sellerId = $lineData['seller_id'];
                                        $oc_products = $this->model_account_customer_order_import->findProductByItemCodeAndSellerId($itemCode, $order_info['customer_id'])->rows;
                                        if ($oc_products) {
                                            $cost_qty = 0;
                                            foreach ($oc_products as $oc_product) {
                                                $productId = $oc_product['product_id'];
                                                if (isset($productCostMap[$productId])) {
                                                    // 有库存
                                                    $cost_qty = $cost_qty + $productCostMap[$productId];
                                                }
                                            }
                                            $costQtyArr[$itemCode] = $cost_qty;
                                        }
                                    }
                                }
                                $boundSalesOrderIds = []; // 已绑定的销售单id和编号['id' => , 'order_id' => ]
                                foreach ($keys as $key) {
                                    $lineDatas = $headerCustomerSalesOrderMap[$key];
                                    $count = count($lineDatas);
                                    $index = 0;
                                    //edit by xxli
                                    foreach ($lineDatas as $lineData) {
                                        $lineItemCode = strtoupper($lineData['item_code']);
                                        if (isset($costQtyArr[$lineItemCode])) {
                                            $cost_qty = $costQtyArr[$lineItemCode];
                                            if ($cost_qty > 0) {
                                                if ($cost_qty >= intval($lineData['qty'])) {
                                                    // 减去已售未发
                                                    $cost_qty = $cost_qty - intval($lineData['qty']);
                                                    $costQtyArr[$lineItemCode] = $cost_qty;
                                                    $index++;
                                                }
                                            }
                                        }
                                    }
                                    $orderAssociatedIdsAll = [];
                                    if ($count == $index) {
                                        $productCostMapTemp = $productCostMap;

										$salesOrder = $this->orm->table('tb_sys_customer_sales_order')
	                                        ->where('order_status', CustomerSalesOrderStatus::TO_BE_PAID)
	                                        ->where('id', $key)
	                                        ->lockForUpdate()
	                                        ->first();
	                                    if (empty($salesOrder) && $payment_code == PayCode::PAY_VIRTUAL) {
	                                        $boundSalesOrderIds[] = [
	                                            'id' => $key,
	                                            'order' => $lineDatas[0]['order_id'],
	                                        ];
	                                        continue;
	                                    }

                                        //销售订单与采购订单强绑定
                                        foreach ($lineDatas as $lineData) {
                                            //查询tb_sys_cost_detail 所有item_code的批次库存
                                            $orderAssociatedIds = $this->model_account_customer_order_import->associateOrder($order_id, $lineData['id'], $lineData['item_code']);
                                            $orderAssociatedIdsAll = array_merge($orderAssociatedIdsAll, $orderAssociatedIds);
                                            //更新订单明细表的combo_info
                                            $this->model_account_customer_order_import->updateCustomerSalesOrderLine($key, $lineData['id']);

                                        }
                                        // 更新订单状态
                                        $this->model_account_customer_order_import->updateCustomerSalesOrder($key);
                                        //根据绑定的销售单与采购单的关系，绑定仓租
                                        app(StorageFeeService::class)->bindByOrderAssociated($orderAssociatedIdsAll);
                                        // 上门取货订单改为费用待支付状态由于新版上门取货导单优化删除了 #24701
                                        $asr_expect_list[] = $key;
                                    } else {
                                        $productCostMap = $productCostMapTemp;
                                    }
                                }
                                if (!empty($boundSalesOrderIds)) {
	                                if (count($boundSalesOrderIds) > 1) {
	                                    throw new AssociatedException('The status of this sales order has been updated, the current action is now not allowed. ', 410);
	                                }
	                                $boundSalesOrderId = $boundSalesOrderIds[0];
	                                throw new AssociatedException('The status of this sales order has been updated, the current action is now not allowed. ', 410, $boundSalesOrderId['id'], $boundSalesOrderId['order']);
	                            }
                            }
                        }
                        //获取待支付签收服务费的订单
                        // 获取全部新订单
                        $asr_orders = $this->db->query("SELECT csol.* FROM `tb_sys_customer_sales_order` cso LEFT JOIN `tb_sys_customer_sales_order_line` csol ON cso.`id` = csol.`header_id` WHERE cso.`buyer_id` = " . $order_info['customer_id'] . " AND cso.`order_status` = 128 ORDER BY cso.id ASC")->rows;
                        $headerToItemCodeMap = array();
                        if ($asr_orders && count($asr_orders)) {
                            $this->load->model('account/customer_order_import');
                            foreach ($asr_orders as $asr_order) {
                                if (isset($headerToItemCodeMap[$asr_order['header_id']])) {
                                    $str = $headerToItemCodeMap[$asr_order['header_id']] . ',' . '<' . $asr_order['item_code'] . ' × ' . $asr_order['qty'] . '>';
                                    $headerToItemCodeMap[$asr_order['header_id']] = $str;
                                } else {
                                    $headerToItemCodeMap[$asr_order['header_id']] = array();
                                    $str = '<' . $asr_order['item_code'] . ' × ' . $asr_order['qty'] . '>';
                                    $headerToItemCodeMap[$asr_order['header_id']] = $str;
                                }
                            }
                            $asr_keys = array_keys($headerToItemCodeMap);
                            $asr_keys = array_diff($asr_keys, $asr_expect_list);
                            $costQtyArr = array();
                            /**
                             * 签收服务费的SKU
                             */
                            $itemCode = $this->config->get('signature_service_us_sku');
                            $us_signature_service_pid = $this->config->get('signature_service_us_product_id');
                            //既然需要强顺序绑定采购订单，那么库存也要只计算采购订单的库存。$productCostMap
                            $productCostMap = $this->customer->getProductCostMap($order_info['customer_id'], $order_id);
                            if (isset($productCostMap[$us_signature_service_pid])) {
                                // 有库存
                                $cost_qty = $productCostMap[$us_signature_service_pid];
                                $costQtyArr[$itemCode] = $cost_qty;
                            }

                            foreach ($asr_keys as $key) {
                                $package_qty_array = $this->model_account_deliverySignature->getOrderPackageQtyInfo($key, $order_info['customer_id']);

                                $all_done = true;
                                $sum_asr_qty = 0;
                                if (isset($costQtyArr[$itemCode]) && !empty($package_qty_array)) {
                                    foreach ($package_qty_array as $line_id => $qty) {
                                        $cost_qty = $costQtyArr[$itemCode];
                                        if ($cost_qty > 0) {
                                            if ($cost_qty >= intval($qty)) {
                                                // 减去已售未发
                                                $cost_qty = $cost_qty - intval($qty);
                                                $costQtyArr[$itemCode] = $cost_qty;
                                                $sum_asr_qty += intval($qty);
                                            } else {
                                                $all_done = false;
                                            }
                                        } else {
                                            $all_done = false;
                                        }
                                    }
                                } else {
                                    $all_done = false;
                                }

                                if ($all_done) {
                                    foreach (array_keys($package_qty_array) as $line_id) {
                                        //销售订单与采购订单强绑定(签收服务费虚拟商品的绑定)
                                        $this->model_account_deliverySignature->associateOrderWithASR($key, $line_id, $package_qty_array[$line_id], $this->session->data['customer_id'], $order_id, $itemCode);
                                    }
                                    //更新销售订单的comments
                                    $ds_product = $this->model_account_deliverySignature->getDeliverySignatureProduct($this->customer->getCountryId());
                                    $format_cost = $this->currency->format(floatval($ds_product['price']) * intval($sum_asr_qty), $this->session->data['currency']);
                                    $comments = 'Total ASR service fee for ' . $headerToItemCodeMap[$key] . ' is ' . $format_cost . '.';
                                    $this->db->query("UPDATE oc_order SET COMMENT = '" . $this->db->escape($comments) . "' WHERE order_id = " . (int)$order_id);
                                    // 更新订单状态
                                    $this->model_account_customer_order_import->updateCustomerSalesOrder($key);
                                }
                            }
                        }
                    } else {
                        //云送仓的处理
                        $this->cloudLogistics($order_id);
                    }
                }

                /**
                 * 议价
                 */
                if ($order_status_id == OcOrderStatus::COMPLETED) {
                    $this->load->model('account/product_quotes/wk_product_quotes');
                    $this->model_account_product_quotes_wk_product_quotes->updateQuoteByOrder($order_id);
                }

                /**
                 * N-84
                 * 添加 buyer 和seller的交易信息 (oc_buyer_to_seller)
                 */
                $this->load->model('customerpartner/buyers');
                $this->model_customerpartner_buyers->updateTransactionByOrder($order_id);
                //End of N-84 by lester.you

                /**
                 * Marketplace Code Ends here
                 */
                $this->cache->delete('product');

                // 更改供应商的表状态
                $this->db->query("UPDATE " . DB_PREFIX . "customerpartner_to_order
            SET paid_status = 1,order_product_status = $order_status_id
            WHERE order_id = '" . (int)$order_id . "'");
            }
            // 仅支付费用单绑定关联关系非上门取货
            // order_id 为空的情况为囤货不需要产生采购单,通过sale_order_id来确认只有自动购买的订单才需要更新订单状态
            // associatedOrderByFeeOrder($feeOrderIdArr) 中 根据tb_sys_order_associated_pre来查询，
            if (empty($order_id) && !empty($feeOrderIdArr)) {
                $feeOrderInfos = app(FeeOrderRepository::class)->findFeeOrderInfo($feeOrderIdArr);
                $customerId = $feeOrderInfos[0]['buyer_id'];
                $customer_group_id = $this->commonFunction->getCustomerGroupId($customerId);
                $isCollectionFromDomicile = in_array($customer_group_id, COLLECTION_FROM_DOMICILE);
                if (!$isCollectionFromDomicile) {
                    if($sale_order_id){
                        // 自动购买一件代发 ，判断associate中的数据是否已经绑定完成
                        $associateRet = app(AutoBuyRepository::class)->checkSalesOrderAssociate([$sale_order_id]);
                        if(isset($associateRet[$sale_order_id]) && $associateRet[$sale_order_id]){
                            $this->model_account_customer_order_import->updateCustomerSalesOrder($sale_order_id);
                        }
                    }else{
                        //仅支付费用单（非上门取货）
                        $this->associatedOrderByFeeOrder($feeOrderIdArr);
                    }
                } else {
                    //仅支付费用单（上门取货）
                    $salesOrderIdArr = array_unique(array_column($feeOrderInfos, 'order_id'));
                    $associatedExists = OrderAssociated::query()->whereIn('sales_order_id',$salesOrderIdArr)->exists();
                    if ($associatedExists) {
                        // 旧版上门取货是先绑定在支付费用单，所以只要修改状态
                        $this->updateSalesOrderStatusByFeeOrder($salesOrderIdArr);
                    } else {
                        // 新版上门取货不会先绑定再付仓租，需要处理绑定关系和仓租逻辑
                        $this->associatedOrderByFeeOrder($feeOrderIdArr);
                    }
                }

            }

            // 费用单变更订单状态
            $feeOrderService = app(FeeOrderService::class);
            foreach ($feeOrderIdArr as $feeOrderId) {
                $feeOrderService->changeFeeOrderStatus($feeOrderId, FeeOrderStatus::COMPLETE);
            }

            // 新增了一张表  tb_sys_product_all_sales
            load()->model('tool/sort');
            $this->model_tool_sort->setProductAllsalesQuantity($order_id);

            $this->addSystemMessageAboutOrderPay($order_id, $order_info['customer_id']);

            //采购订单购买完成后 清理购物车
            load()->model('checkout/success');
            load()->model('checkout/pre_order');
            // 如果order_id_*不是空,代表不是buy now，需要清理购物车
            if ($this->cache->get('order_id_' . $order_id) || $this->session->get('api_id') == 1) {
                $this->model_checkout_success->clearCartByOrderId($order_id);
                if ($this->cache->has('order_id_' . $order_id)) {
                    $this->cache->delete('order_id_' . $order_id);
                }
            }
            // 如果使用了优惠券,平台账单记一笔支出
            app(PlatformBillService::class)->addBill($order_id);
            // 如果需要赠送优惠券,支付完赠送优惠券
            app(CouponService::class)->giftCoupon($order_id,$customerId);
            // 订单支付完扣减限时限购的活动库存
            app(MarketingTimeLimitDiscountService::class)->decrementTimeLimitProductQty($order_id);

            // 对于Onsite Seller现货头款需要扣除Onsite Seller的现货协议保证金（记账）
            app(MarginService::class)->insertMarginAgreementDeposit($order_id);
            // 发送支付成功消息通知
            $this->model_checkout_success->paySuccessMessage($order_id, $customer);
            //解锁
            $lock->release();
        }
    }

    /**
     * addOrderHistoryByYzcModel 方法事务提交后执行
     * @param int|null $orderId
     */
    public function addOrderHistoryByYzcModelAfterCommit($orderId)
    {
        //采购订单购买完成后续处理
        $this->commonFunction->rabbitMqProducer("purchase_exchange", "purchaseQueue", "purchaseOrder", array("order_id" => $orderId));
    }

    public function autoBuyBind($bind_cost_id, $saleOrderId)
    {
        if (!isset($bind_cost_id) || empty($bind_cost_id)) {
            return;
        }

        //插入采购订单和销售订单关系
        $sql = "SELECT  tbl.id AS lineId,
                        tbl.item_code AS sku,
                        tbl.image_id AS imageId,
                        tbl.qty AS line_qty,
                        SUM(oa.qty) AS bind_qty
                FROM tb_sys_customer_sales_order_line tbl
                LEFT JOIN tb_sys_order_associated oa ON oa.sales_order_id = tbl.header_id AND oa.sales_order_line_id = tbl.id
                WHERE tbl.header_id = " . (int)$saleOrderId . " GROUP BY tbl.id";
        $orderQuery = $this->db->query($sql);
        $orderAssociatedIds = [];
        if ($orderQuery->num_rows) {
            foreach ($orderQuery->rows as $saleOrderInfo) {
                $remainQty = intval($saleOrderInfo['line_qty']);//销售订单所需数量
                $bind_qty = intval($saleOrderInfo['bind_qty']);
                if ($remainQty <= $bind_qty) {
                    continue;
                } else {
                    $remainQty = $remainQty - $bind_qty;
                }
                foreach ($bind_cost_id as $key => $cost_record) {
                    if ($cost_record['sku'] == $saleOrderInfo['sku'] && $cost_record['qty'] > 0) {
                        $reduceQty = min($remainQty, intval($cost_record['qty']));
                        $remainQty = $remainQty - $reduceQty;

                        $cost_record['qty'] = $cost_record['qty'] - $reduceQty;
                        $bind_cost_id[$key] = $cost_record;

                        $discountsAmount = app(OrderService::class)->orderProductWillAssociateDiscountsAmount(intval($cost_record['order_product_id']), intval($reduceQty), $this->customer->isJapan() ? 0 : 2);
                        $this->db->query("INSERT INTO tb_sys_order_associated SET sales_order_id = " . (int)$saleOrderId . ",sales_order_line_id = " . (int)$saleOrderInfo['lineId'] . ",order_id = " . (int)$cost_record['oc_order_id'] . ",order_product_id = " . (int)$cost_record['order_product_id'] . ",qty = " . (int)$reduceQty . ",product_id = " . (int)$cost_record['product_id'] . ",seller_id = " . (int)$cost_record['seller_id'] . ",buyer_id = " . (int)$cost_record['buyer_id'] . ",image_id = " . (int)$saleOrderInfo['imageId'] . ",CreateUserName = 'auto_buy'" . ",CreateTime = NOW()" . ",coupon_amount = {$discountsAmount['coupon_amount']}" . ",campaign_amount= {$discountsAmount['campaign_amount']}");
                        $orderAssociatedIds[] = $this->db->getLastId();
                        if ($remainQty == 0) {
                            break;
                        }
                    }
                }
            }
        }
        return $orderAssociatedIds;
    }

    public function saveReceive($sysReceive)
    {
        $sql = "INSERT INTO `tb_sys_receive` (buyer_id, source_header_id, transaction_type, sub_total, total, line_count, create_user_name, create_time, program_code) VALUES (";
        $sql .= $sysReceive->buyer_id . ",";
        $sql .= $sysReceive->source_header_id . ",";
        $sql .= "'" . $sysReceive->source_header_id . "',";
        $sql .= $sysReceive->sub_total . ",";
        $sql .= $sysReceive->total . ",";
        $sql .= $sysReceive->line_count . ",";
        $sql .= "'" . $sysReceive->create_user_name . "',";
        $sql .= "NOW(),";
        $sql .= "'" . $sysReceive->program_code . "')";
        $this->db->query($sql);
        return $this->db->getLastId();
    }

    public function saveReceiveLine($sysReceiveLine)
    {
        $sql = "INSERT INTO `tb_sys_receive_line` (receive_id, buyer_id, oc_order_id, oc_partner_order_id, product_id, receive_qty, unit_price, seller_id, create_user_name, create_time, program_code) VALUES (";
        $sql .= $sysReceiveLine->receive_id . ",";
        $sql .= $sysReceiveLine->buyer_id . ",";
        $sql .= $sysReceiveLine->oc_order_id . ",";
        $sql .= $sysReceiveLine->oc_partner_order_id . ",";
        $sql .= $sysReceiveLine->product_id . ",";
        $sql .= $sysReceiveLine->receive_qty . ",";
        $sql .= $sysReceiveLine->unit_price . ",";
        $sql .= $sysReceiveLine->seller_id . ",";
        $sql .= "'" . $sysReceiveLine->create_user_name . "',";
        $sql .= "NOW(),";
        $sql .= "'" . $sysReceiveLine->program_code . "')";
        $this->db->query($sql);
        return $this->db->getLastId();
    }

    public function saveCostDetail($sysCostDetail)
    {
        $sql = "INSERT INTO `tb_sys_cost_detail` (buyer_id, source_line_id, source_code, sku_id, onhand_qty, original_qty, seller_id, create_user_name, create_time, program_code) VALUES (";
        $sql .= $sysCostDetail->buyer_id . ",";
        $sql .= $sysCostDetail->source_line_id . ",";
        $sql .= "'" . $sysCostDetail->source_code . "',";
        $sql .= $sysCostDetail->sku_id . ",";
        $sql .= $sysCostDetail->onhand_qty . ",";
        $sql .= $sysCostDetail->original_qty . ",";
        $sql .= $sysCostDetail->seller_id . ",";
        $sql .= "'" . $sysCostDetail->create_user_name . "',";
        $sql .= "NOW(),";
        $sql .= "'" . $sysCostDetail->program_code . "')";
        $this->db->query($sql);
        return $this->db->getLastId();
    }

    public function addOrderHistory($order_id, $order_status_id, $comment = '', $notify = false, $override = false)
    {
        $order_info = $this->getOrder($order_id);

        if ($order_info) {
            // Fraud Detection
            $this->load->model('account/customer');


            /**
             * Marketplace Code Starts here
             */
            if ($this->config->get('module_marketplace_status')) {
                $toAdmin = false;
                if (isset($comment) && $comment) {
                    $get_comment = explode('___', $comment);
                    if ($get_comment[0] == 'wk_admin_comment') {
                        $comment = ($get_comment[1]);
                        $toAdmin = true;
                        $this->config->set('config_email', $this->customer->getEmail());
                        if ($this->config->get('marketplaceadminmail')) {
                            $order_info['email'] = $this->config->get('marketplace_adminmail');
                        } else {
                            $order_info['email'] = $this->config->get('config_email');
                        }
                    }
                }
            }
            /**
             * Marketplace Code Ends here
             */

            $customer_info = $this->model_account_customer->getCustomer($order_info['customer_id']);

            if ($customer_info && $customer_info['safe']) {
                $safe = true;
            } else {
                $safe = false;
            }

            // Only do the fraud check if the customer is not on the safe list and the order status is changing into the complete or process order status
            if (!$safe && !$override && in_array($order_status_id, array_merge($this->config->get('config_processing_status'), $this->config->get('config_complete_status')))) {
                // Anti-Fraud
                $this->load->model('setting/extension');

                $extensions = $this->model_setting_extension->getExtensions('fraud');

                foreach ($extensions as $extension) {
                    if ($this->config->get('fraud_' . $extension['code'] . '_status')) {
                        $this->load->model('extension/fraud/' . $extension['code']);

                        if (property_exists($this->{'model_extension_fraud_' . $extension['code']}, 'check')) {
                            $fraud_status_id = $this->{'model_extension_fraud_' . $extension['code']}->check($order_info);

                            if ($fraud_status_id) {
                                $order_status_id = $fraud_status_id;
                            }
                        }
                    }
                }
            }

            // If current order status is not processing or complete but new status is processing or complete then commence completing the order
            if (!in_array($order_info['order_status_id'], array_merge($this->config->get('config_processing_status'), $this->config->get('config_complete_status'))) && in_array($order_status_id, array_merge($this->config->get('config_processing_status'), $this->config->get('config_complete_status')))) {
                // Redeem coupon, vouchers and reward points
                $order_totals = $this->getOrderTotals($order_id);

                foreach ($order_totals as $order_total) {
                    $this->load->model('extension/total/' . $order_total['code']);

                    if (property_exists($this->{'model_extension_total_' . $order_total['code']}, 'confirm')) {
                        // Confirm coupon, vouchers and reward points
                        $fraud_status_id = $this->{'model_extension_total_' . $order_total['code']}->confirm($order_info, $order_total);

                        // If the balance on the coupon, vouchers and reward points is not enough to cover the transaction or has already been used then the fraud order status is returned.
                        if ($fraud_status_id) {
                            $order_status_id = $fraud_status_id;
                        }
                    }
                }

                // Stock subtraction
                $order_products = $this->getOrderProducts($order_id);

                foreach ($order_products as $order_product) {
                    $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_id = '" . (int)$order_product['product_id'] . "' AND subtract = '1'");

                    $this->load->model('account/notification');
                    //$this->model_account_notification->productStockActivity($order_product['product_id']);
                    $this->addSystemMessageAboutProductStock($order_product['product_id']);

                    $order_options = $this->getOrderOptions($order_id, $order_product['order_product_id']);

                    foreach ($order_options as $order_option) {
                        $this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_option_value_id = '" . (int)$order_option['product_option_value_id'] . "' AND subtract = '1'");
                    }
                }

                // Add commission if sale is linked to affiliate referral.
                if ($order_info['affiliate_id'] && $this->config->get('config_affiliate_auto')) {
                    $this->load->model('account/customer');

                    if (!$this->model_account_customer->getTotalTransactionsByOrderId($order_id)) {
                        $this->model_account_customer->addTransaction($order_info['affiliate_id'], $this->language->get('text_order_id') . ' #' . $order_id, $order_info['commission'], $order_id);
                    }
                }
            }

            // Update the DB with the new statuses
            $this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = '" . (int)$order_status_id . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");

            $this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$order_id . "', order_status_id = '" . (int)$order_status_id . "', notify = '" . (int)$notify . "', comment = '" . $this->db->escape($comment) . "', date_added = NOW()");

            // If old order status is the processing or complete status but new status is not then commence restock, and remove coupon, voucher and reward history
            if (in_array($order_info['order_status_id'], array_merge($this->config->get('config_processing_status'), $this->config->get('config_complete_status'))) && !in_array($order_status_id, array_merge($this->config->get('config_processing_status'), $this->config->get('config_complete_status')))) {
                // Restock
                $order_products = $this->getOrderProducts($order_id);

                foreach ($order_products as $order_product) {
                    $this->db->query("UPDATE `" . DB_PREFIX . "product` SET quantity = (quantity + " . (int)$order_product['quantity'] . ") WHERE product_id = '" . (int)$order_product['product_id'] . "' AND subtract = '1'");

                    $order_options = $this->getOrderOptions($order_id, $order_product['order_product_id']);

                    foreach ($order_options as $order_option) {
                        $this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET quantity = (quantity + " . (int)$order_product['quantity'] . ") WHERE product_option_value_id = '" . (int)$order_option['product_option_value_id'] . "' AND subtract = '1'");
                    }
                }

                // Remove coupon, vouchers and reward points history
                $order_totals = $this->getOrderTotals($order_id);

                foreach ($order_totals as $order_total) {
                    $this->load->model('extension/total/' . $order_total['code']);

                    if (property_exists($this->{'model_extension_total_' . $order_total['code']}, 'unconfirm')) {
                        $this->{'model_extension_total_' . $order_total['code']}->unconfirm($order_id);
                    }
                }

                // Remove commission if sale is linked to affiliate referral.
                if ($order_info['affiliate_id']) {
                    $this->load->model('account/customer');

                    $this->model_account_customer->deleteTransactionByOrderId($order_id);
                }
            }


            /**
             * Marketplace Code Starts here
             */

            $order_status_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_status WHERE order_status_id = '" . (int)$order_status_id . "' AND language_id = '" . (int)$order_info['language_id'] . "'");

            if ($order_status_query->num_rows) {
                $order_status = $order_status_query->row['name'];
            } else {
                $order_status = '';
            }

            if ($this->config->get('module_marketplace_status')) {
                $this->load->model('account/customerpartnerorder');
                $this->model_account_customerpartnerorder->customerpartner($order_info, $order_status, $comment, $order_status_id);
            }
            /**
             * Marketplace Code Ends here
             */


            if ($order_id && $order_status_id && $order_info['order_status_id']) {
                $this->load->model('account/notification');
                $activity_data = array(
                    'id' => $order_info['customer_id'],
                    'status' => $order_status_id,
                    'order_id' => $order_id
                );
                //$this->model_account_notification->addActivity('order_status', $activity_data);
                $this->addSystemMessageAboutOrderStatus($activity_data);
            }

            $this->cache->delete('product');
        }
    }

    public function processingOrderCompleted($order_id, $order_data)
    {
        $now = date("Y-m-d H:i:s", time());
        // 获得OpenCart订单
        $yzcOrder = $this->getOrder($order_id);
        $sellerProductMap = array();
        if ($order_data) {
            // 更改订单状态为Complete
            $order_status_id = OcOrderStatus::COMPLETED;
            $this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = '" . (int)$order_status_id . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");
            // 更改OrderHistory
            $this->db->query("UPDATE " . DB_PREFIX . "order_history SET order_status_id = '" . (int)$order_status_id . "'WHERE order_id = '" . (int)$order_id . "'");
            // 更改供应商的表状态
            $this->db->query("UPDATE " . DB_PREFIX . "customerpartner_to_order SET paid_status = 1,order_product_status = 5 WHERE order_id = '" . (int)$order_id . "'");
            foreach ($order_data as $data) {
                if (isset($data['platForm']) && $data['platForm'] == "7") {
                    continue;
                }
                $status = $data['status'];
                if ($status != 1) {
                    //错误状态
                    continue;
                }
                $supplierOrder = array(
                    // 云资产OrderId
                    "SourceHeaderId" => $order_id,
                    // BuyerId
                    "BuyerId" => $yzcOrder['customer_id'],
                    // 平台订单号
                    "SupplierOrderId" => $data['platOrderNo'],
                    // 购买日期
                    "PurchaseDate" => $yzcOrder['date_added'],
                    // SourceId暂时存放PurchaseFileUrl
                    "PurchaseFileUrl" => $data['sourceId'],
                    // PDF文件存放路径
                    "PurchaseFilePath" => $data['filePath'],
                    // 订单状态
                    "PurchaseStatus" => $data['status'],
                    // 创建者
                    "CreateUserName" => "system",
                    // 创建时间
                    "CreateTime" => $now,
                    // 程序号
                    "ProgramCode" => PROGRAM_CODE,
                );
                // 供应商下单的购物车列表
                $cartInfo = $data['cartInfo'];
                // 爬虫接口平台号
                $platform = $cartInfo["platForm"];
                switch ($platform) {
                    case "1":
                        // Coaster
                        $supplierOrder['SellerId'] = "1";
                        break;
                    case "2":
                        // Poundex
                        $supplierOrder['SellerId'] = "3";
                        break;
                    case "3":
                        // FOA
                        $supplierOrder['SellerId'] = "2";
                        break;
                    case "4":
                        // ACME
                        $supplierOrder['SellerId'] = "4";
                        break;
                    case "5":
                        // Ashley
                        $supplierOrder['SellerId'] = "14";
                        break;
                    case "6":
                        // Modway
                        $supplierOrder['SellerId'] = "15";
                        break;
                }
                // 采购订单总金额
                $supplierOrder['PurchaseTotal'] = doubleval($cartInfo['totalPrice']);
                $supplierOrder['PurchaseInvoiceTotal'] = doubleval($cartInfo['totalPrice']);
                // 获取该订单对应的seller订单
                $sellerOrderProductArr = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customerpartner_to_order` cto WHERE cto.`order_id` = " . $order_id . " AND cto.`customer_id` = " . $supplierOrder['SellerId'])->rows;
                $sellerOrderProductMap = array();
                foreach ($sellerOrderProductArr as $sellerOrderProduct) {
                    $product = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product` p WHERE p.`product_id` =" . $sellerOrderProduct['product_id'])->row;
                    // 更改
                    // 订单MPN对应的产品和卖家订单对象
                    $sellerOrderProductMap[$product['mpn']] = array(
                        "product" => $product,
                        "customerpartner_to_order" => $sellerOrderProduct
                    );
                }
                // 插入供应商订单头表tb_sys_supplier_order
                $supplierOrder = $this->saveSupplierOrder($supplierOrder);
                // 供应商下单的产品列表
                $prpductLs = $cartInfo['prpductLs'];
                foreach ($prpductLs as $pro) {
                    // itemCode MPN
                    $itemCode = trim($pro['itemCode']);
                    $backOrder = $pro['backOrder'];
                    $product = $sellerOrderProductMap[$itemCode]['product'];
                    $product_id = $product['product_id'];
                    $sellerProductMap[$product_id] = array(
                        "backOrder" => $backOrder,
                        "qty" => $pro['qty']
                    );
                    // 获取oc_customerpartner_to_order
                    $customerpartnerToOrder = $sellerOrderProductMap[$itemCode]['customerpartner_to_order'];
                    // supplierOrderLine
                    $supplierOrderLine = array(
                        // 头表ID
                        "OrderHeaderId" => $supplierOrder['id'],
                        // 云资产OrderId
                        "SourceHeaderId" => $order_id,
                        // Seller订单表Id
                        "SourceLineId" => $customerpartnerToOrder['id'],
                        // Mpn
                        "Mpn" => $itemCode,
                        // MpnId
                        "MpnId" => $product_id,
                        // Item单价
                        "ItemPrice" => $pro['price'],
                        // 购买的Item描述
                        "ItemDescription" => $pro['itemName'],
                        // 购买的Item数量
                        "ItemQty" => $pro['qty'],
                        // Item状态
                        "ItemStatus" => $pro['backOrder'],
                        "CreateUserName" => "system",
                        "CreateTime" => $now,
                        "ProgramCode" => PROGRAM_CODE
                    );
                    // 插入供应商明细表tb_sys_supplier_order_line
                    $this->saveSupplierOrderLine($supplierOrderLine);
                }
            }

            // 获取订单的自营商品
            $this->load->model('account/customerpartnerorder');
            // 根据orderId查询是否购买了自营商品
            $selfOperatedOrder = $this->model_account_customerpartnerorder->getSellerOrderInfo($order_id, 5)->rows;
            $selfProductMap = array();
            if ($selfOperatedOrder && count($selfOperatedOrder)) {
                foreach ($selfOperatedOrder as $item) {
                    $selfProductMap[$item['product_id']] = true;
                }
            }
            // 扣减库存
            $order_products = $this->model_checkout_order->getOrderProducts($order_id);
            foreach ($order_products as $order_product) {
                if (isset($selfProductMap[$order_product['product_id']])) {
                    continue;
                }
                $sellerProduct = $sellerProductMap[$order_product['product_id']];
                if ($sellerProduct['backOrder'] == false) {
                    $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_id = '" . (int)$order_product['product_id'] . "' AND subtract = '1'");

                    //$this->load->model('account/notification');
                    //$this->model_account_notification->productStockActivity($order_product['product_id']);
                    $this->addSystemMessageAboutProductStock($order_product['product_id']);


                    $order_options = $this->model_checkout_order->getOrderOptions($order_id, $order_product['order_product_id']);

                    foreach ($order_options as $order_option) {
                        $this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_option_value_id = '" . (int)$order_option['product_option_value_id'] . "' AND subtract = '1'");
                    }
                }
            }
            // 扣减供应商库存
            $order_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");
            foreach ($order_product_query->rows as $product) {
                if (isset($selfProductMap[$product['product_id']])) {
                    continue;
                }
                $prsql = '';
                $mpSellers = $this->db->query("SELECT c.email,c.customer_id,p.product_id,p.subtract,c2c.commission FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "customerpartner_to_product c2p ON (p.product_id = c2p.product_id) LEFT JOIN " . DB_PREFIX . "customer c ON (c2p.customer_id = c.customer_id) LEFT JOIN " . DB_PREFIX . "customerpartner_to_customer c2c ON (c2c.customer_id = c2p.customer_id) WHERE p.product_id = '" . (int)$product['product_id'] . "' $prsql ORDER BY c2p.id ASC ")->row;
                if ($mpSellers['subtract']) {
                    // 更新Seller产品库存
                    $sellerProduct = $sellerProductMap[$mpSellers['product_id']];
                    if ($sellerProduct['backOrder'] == false) {
                        $this->db->query("UPDATE " . DB_PREFIX . "customerpartner_to_product SET quantity = quantity-'" . (int)$product['quantity'] . "' WHERE product_id = '" . (int)$mpSellers['product_id'] . "' AND customer_id = '" . (int)$mpSellers['customer_id'] . "'");
                    }
                }
            }
            // 获取该订单的全部供应商采购订单
            $supplierOrders = $this->db->query("SELECT * FROM `tb_sys_supplier_order` sso WHERE sso.`source_header_id` = " . $order_id)->rows;
            foreach ($supplierOrders as $supplier_order) {
                $sql = "INSERT INTO `tb_sys_purchase_order`(source_header_id, order_mode , buyer_id, seller_id, purchase_status, create_user_name, create_time, program_code) VALUES(";
                $sql .= $supplier_order['source_header_id'] . ",";
                $sql .= "0,";
                $sql .= $supplier_order['buyer_id'] . ",";
                $sql .= $supplier_order['seller_id'] . ",";
                $sql .= "1,";
                $sql .= "'" . $supplier_order['create_user_name'] . "',";
                $sql .= "'" . $supplier_order['create_time'] . "',";
                $sql .= "'" . $supplier_order['program_code'] . "')";
                $this->db->query($sql);
                $sql = "SELECT * FROM `tb_sys_purchase_order` po WHERE po.`source_header_id` =  " . $supplier_order['source_header_id'];
                $purchaseOrder = $this->db->query($sql)->row;
                // 获取该订单的全部供应商采购订单明细
                $supplierOrderLines = $this->db->query("SELECT * FROM `tb_sys_supplier_order_line` sol WHERE sol.source_header_id = " . $order_id . " AND sol.order_header_id = " . $supplier_order['id'])->rows;
                foreach ($supplierOrderLines as $supplier_order_line) {
                    $sql = "INSERT INTO `tb_sys_purchase_order_line` (header_id, supplier_order_header_id, supplier_order_line_id, sku_id, qty, purchase_line_status, create_user_name, create_time, program_code) VALUES (";
                    $sql .= $purchaseOrder["id"] . ",";
                    $sql .= $supplier_order["id"] . ",";
                    $sql .= $supplier_order_line["id"] . ",";
                    $sql .= $supplier_order_line["mpn_id"] . ",";
                    $sql .= $supplier_order_line["item_qty"] . ",";
                    $sql .= 1 . ",";
                    $sql .= "'" . $supplier_order_line['create_user_name'] . "',";
                    $sql .= "'" . $supplier_order_line['create_time'] . "',";
                    $sql .= "'" . $supplier_order_line['program_code'] . "')";
                    $this->db->query($sql);
                }
            }
        }
    }

    public function processingUmfOrderCompleted($data)
    {
        $orderId = $data['order_id'];
        $feeOrderIdArr = $data['fee_order_arr'];
        $customerId = $data['customer_id'];
        $paymentMethod = $data['payment_method'];
        $paymentInfoId = $data['payment_id'];
        $result = $this->dealPaymentCallback($orderId, $feeOrderIdArr, $customerId, $paymentMethod,$paymentInfoId);
        return $result;
    }

    public function completeCyberSourceOrder($data)
    {
        $orderId = $data['order_id'];
        $feeOrderIdArr = $data['fee_order_arr'];
        $customerId = $data['customer_id'];
        $paymentMethod = $data['payment_method'];
        $paymentInfoId = $data['payment_id'];
        $result = $this->dealPaymentCallback($orderId, $feeOrderIdArr, $customerId, $paymentMethod,$paymentInfoId);
        return $result;
    }


    //扣减信用额度 废弃
    public function balanceChangeLineOfCredit($order_id, $feeOrderIdArr)
    {
        $order_total_query = $this->db->query("SELECT * FROM oc_order_total WHERE order_id = $order_id")->rows;
        $total = 0;
        foreach ($order_total_query as $row) {
            if ($row['code'] == 'balance') {
                $balance = $row['value'];
            } elseif ($row['code'] == 'total') {
                $total = $row['value'];
            }
        }
        if (!isset($balance)) {
            return;
        } else {
            $result = $this->db->query("SELECT TRUNCATE(line_of_credit,2) as credit,oo.customer_id FROM oc_order oo LEFT JOIN oc_customer oc ON oc.customer_id=oo.customer_id WHERE oo.order_id=" . $order_id)->row;
            $lineOfCredit = isset($result['credit']) ? $result['credit'] : 0;
            $buyerId = isset($result['customer_id']) ? $result['customer_id'] : 0;
            //balance为负数
            $creditChange = $lineOfCredit + $balance;
            $this->changeLineOfCredit($creditChange, $buyerId);

            $this->load->model('extension/payment/line_of_credit');
            $updateDate = array(
                'customerId' => $buyerId,
                'oldBalance' => $lineOfCredit,
                'balance' => $creditChange,
                'operatorId' => $buyerId,
                'typeId' => 2,
                'orderId' => $order_id
            );
            $this->model_extension_payment_line_of_credit->saveAmendantRecord($updateDate);
        }
    }

    //支付信用额度 2020-05-19 CL
    public function payByLineOfCredit($orderId, $feeOrderIdArr, $customerId)
    {
        $this->load->model('extension/payment/line_of_credit');
        //信用额度扣减(获取订单total)
        $lineOfCreditInfo = $this->orm->table('oc_customer')
            ->where('customer_id', '=', $customerId)
            ->select('line_of_credit')
            ->lockForUpdate()
            ->first();
        $lineOfCredit = empty($lineOfCreditInfo->line_of_credit) ? 0 : $lineOfCreditInfo->line_of_credit;
        $buyerId = $customerId;
        if (!empty($orderId)) {
            $balance = $this->orm->table('oc_order_total')
                ->where([
                    'order_id' => $orderId,
                    'code' => 'balance'
                ])
                ->value('value');

            $purchaseOrderTotal = abs($balance);
            if ($purchaseOrderTotal > 0) {
                $creditChange = $lineOfCredit - $purchaseOrderTotal;
                $updateDate = array(
                    'customerId' => $buyerId,
                    'oldBalance' => $lineOfCredit,
                    'balance' => $creditChange,
                    'operatorId' => $buyerId,
                    'typeId' => 2,
                    'orderId' => $orderId
                );
                $lineOfCredit = $creditChange;
                $this->model_extension_payment_line_of_credit->saveAmendantRecord($updateDate);
            }
        }

        //费用单
        $feeOrderTotal = 0;
        if (!empty($feeOrderIdArr)) {
            $feeOrderInfos = app(FeeOrderRepository::class)->findFeeOrderInfo($feeOrderIdArr);
            foreach ($feeOrderInfos as $feeOrderInfo) {
                $feeOrderTotal += $feeOrderInfo['balance'];
                if ($feeOrderInfo['balance'] > 0) {
                    $creditChange = $lineOfCredit - $feeOrderInfo['balance'];
                    $updateDate = array(
                        'customerId' => $buyerId,
                        'oldBalance' => $lineOfCredit,
                        'balance' => $creditChange,
                        'operatorId' => $buyerId,
                         // 支付类型需要根据费用单类型修改
                        'typeId' => $feeOrderInfo['fee_type'] === FeeOrderFeeType::STORAGE ? ChargeType::PAY_FEE_ORDER : ChargeType::PAY_SAFEGUARD,
                        'orderId' => $feeOrderInfo['id']
                    );
                    $this->model_extension_payment_line_of_credit->saveAmendantRecord($updateDate);
                    $lineOfCredit = $creditChange;
                }
            }
        }
        $this->changeLineOfCredit($lineOfCredit, $buyerId);
    }

    public function saveSupplierOrder($supplier_order)
    {
        if ($supplier_order) {
            $sql = "INSERT INTO `tb_sys_supplier_order`(source_header_id, buyer_id, seller_id, supplier_order_id, purchase_date, purchase_total, purchase_invoice_total, purchase_file_url, purchase_file_path, create_user_name, create_time, program_code) VALUES (";
            $sql .= $supplier_order['SourceHeaderId'] . ",";
            $sql .= $supplier_order['BuyerId'] . ",";
            $sql .= $supplier_order['SellerId'] . ",";
            $sql .= "'" . $supplier_order['SupplierOrderId'] . "',";
            $sql .= "'" . $supplier_order['PurchaseDate'] . "',";
            $sql .= $supplier_order['PurchaseTotal'] . ",";
            $sql .= $supplier_order['PurchaseInvoiceTotal'] . ",";
            $sql .= "'" . $supplier_order['PurchaseFileUrl'] . "',";
            $sql .= "'" . $supplier_order['PurchaseFilePath'] . "',";
            $sql .= "'" . $supplier_order['CreateUserName'] . "',";
            $sql .= "'" . $supplier_order['CreateTime'] . "',";
            $sql .= "'" . $supplier_order['ProgramCode'] . "')";
            $this->db->query($sql);
            $sql = "SELECT * FROM `tb_sys_supplier_order` so WHERE so.`source_header_id` = " . $supplier_order['SourceHeaderId'] . " AND so.`buyer_id` = " . $supplier_order['BuyerId'] . " AND so.`seller_id` = " . $supplier_order['SellerId'];
            return $this->db->query($sql)->row;
        }
    }

    public function saveSupplierOrderLine($supplier_order_line)
    {
        if ($supplier_order_line) {
            $sql = "INSERT INTO `tb_sys_supplier_order_line`(order_header_id, source_header_id, source_line_id, mpn, mpn_id, item_price, item_description, item_qty, item_status, create_user_name, create_time, program_code) VALUES (";
            $sql .= $supplier_order_line['OrderHeaderId'] . ",";
            $sql .= $supplier_order_line['SourceHeaderId'] . ",";
            $sql .= $supplier_order_line['SourceLineId'] . ",";
            $sql .= "'" . $supplier_order_line['Mpn'] . "',";
            $sql .= $supplier_order_line['MpnId'] . ",";
            $sql .= $supplier_order_line['ItemPrice'] . ",";
            $sql .= "'" . $supplier_order_line['ItemDescription'] . "',";
            $sql .= $supplier_order_line['ItemQty'] . ",";
            $sql .= "'" . ($supplier_order_line['ItemStatus'] ? "Normal" : "BackOrder") . "',";
            $sql .= "'" . $supplier_order_line['CreateUserName'] . "',";
            $sql .= "'" . $supplier_order_line['CreateTime'] . "',";
            $sql .= "'" . $supplier_order_line['ProgramCode'] . "')";
            $this->db->query($sql);
        }
    }

    public function changeLineOfCredit($lineOfCredit, $buyer_id)
    {
        $sql = "update oc_customer set line_of_credit = '" . $lineOfCredit . "' where customer_id = " . $buyer_id;
        $this->db->query($sql);
    }

    public function changeLineOfCreditWithBuyerId($lineOfCredit, $buyer_id)
    {
        $sql = "update oc_customer set line_of_credit = '" . $lineOfCredit . "' where customer_id = " . $buyer_id;
        $this->db->query($sql);
    }

    public function updateAndSaveSellerBatch($selfOperatedProduct, $order_info)
    {
        $productModel = Product::query()->where('product_id', $selfOperatedProduct['product_id'])->select(['combo_flag', 'danger_flag'])->first();
        $combo_flag = $productModel->combo_flag;
        $dangerFlag = $productModel->danger_flag;
        //判断是否为combo
        if ($combo_flag == 1) {
            //获取combo的子产品
            $comboProducts = $this->db->query("select tspsi.set_product_id,tspsi.qty from tb_sys_product_set_info tspsi where tspsi.product_id = " . $selfOperatedProduct['product_id'])->rows;
            $setProductIdDangerFlagMap = Product::query()->whereIn('product_id', array_column($comboProducts, 'set_product_id'))->pluck('danger_flag', 'product_id')->toArray();
            foreach ($comboProducts as $comboProduct) {
                $dangerFlag = $setProductIdDangerFlagMap[$comboProduct['set_product_id']] ?? 0;
                $buyerQty = $selfOperatedProduct['quantity'];
                $seller_batchs = $this->db->query("SELECT batch_id,onhand_qty,warehouse  from tb_sys_batch  where onhand_qty >0 and product_id = " . $comboProduct['set_product_id'])->rows;
                $buyerQty = $buyerQty * $comboProduct['qty'];
                foreach ($seller_batchs as $batch) {
                    // 如果当前batch数量不满足购买数量，则一次性扣除完。
                    if ($buyerQty > $batch['onhand_qty']) {
                        $buyerQty = $buyerQty - $batch['onhand_qty'];
                        $this->db->query("update tb_sys_batch set onhand_qty = 0 where batch_id=" . $batch['batch_id']);
                        $sql = "insert into tb_sys_seller_delivery_line (order_id,order_product_id,product_id,batch_id,qty,warehouse,seller_id,buyer_id,CreateUserName,CreateTime,danger_flag,ProgramCode) VALUES (";
                        $sql .= $selfOperatedProduct['order_id'] . ",";
                        $sql .= $selfOperatedProduct['order_product_id'] . ",";
                        $sql .= $comboProduct['set_product_id'] . ",";
                        $sql .= $batch['batch_id'] . ",";
                        $sql .= $batch['onhand_qty'] . ",";
                        $sql .= "'" . $batch['warehouse'] . "',";
                        $sql .= $selfOperatedProduct['customer_id'] . ",";
                        $sql .= $order_info['customer_id'] . ",";
                        $sql .= $order_info['customer_id'] . ",";
                        $sql .= "NOW(),";
                        $sql .= $dangerFlag . ",";
                        $sql .= "'" . PROGRAM_CODE . "')";
                        $this->db->query($sql);
                    } else {
                        // 当前batch满足购买数量，则只扣除购买的数量之后，break退出循环。
                        $leftQty = $batch['onhand_qty'] - $buyerQty;
                        $this->db->query("update tb_sys_batch set onhand_qty = " . $leftQty . " where batch_id=" . $batch['batch_id']);
                        $sql = "insert into tb_sys_seller_delivery_line (order_id,order_product_id,product_id,batch_id,qty,warehouse,seller_id,buyer_id,CreateUserName,CreateTime,danger_flag,ProgramCode) VALUES (";
                        $sql .= $selfOperatedProduct['order_id'] . ",";
                        $sql .= $selfOperatedProduct['order_product_id'] . ",";
                        $sql .= $comboProduct['set_product_id'] . ",";
                        $sql .= $batch['batch_id'] . ",";
                        $sql .= $buyerQty . ",";
                        $sql .= "'" . $batch['warehouse'] . "',";
                        $sql .= $selfOperatedProduct['customer_id'] . ",";
                        $sql .= $order_info['customer_id'] . ",";
                        $sql .= $order_info['customer_id'] . ",";
                        $sql .= "NOW(),";
                        $sql .= $dangerFlag . ",";
                        $sql .= "'" . PROGRAM_CODE . "')";
                        $this->db->query($sql);
                        break;
                    }
                }
            }
        } else {
            $seller_batchs = $this->db->query("SELECT batch_id,onhand_qty,warehouse from tb_sys_batch  where onhand_qty >0 and product_id = " . $selfOperatedProduct['product_id'])->rows;
            $buyerQty = $selfOperatedProduct['quantity'];
            foreach ($seller_batchs as $batch) {
                if ($buyerQty > $batch['onhand_qty']) {
                    $buyerQty = $buyerQty - $batch['onhand_qty'];
                    $this->db->query("update tb_sys_batch set onhand_qty = 0 where batch_id=" . $batch['batch_id']);
                    $sql = "insert into tb_sys_seller_delivery_line (order_id,order_product_id,product_id,batch_id,qty,warehouse,seller_id,buyer_id,CreateUserName,CreateTime,danger_flag,ProgramCode) VALUES (";
                    $sql .= $selfOperatedProduct['order_id'] . ",";
                    $sql .= $selfOperatedProduct['order_product_id'] . ",";
                    $sql .= $selfOperatedProduct['product_id'] . ",";
                    $sql .= $batch['batch_id'] . ",";
                    $sql .= $batch['onhand_qty'] . ",";
                    $sql .= "'" . $batch['warehouse'] . "',";
                    $sql .= $selfOperatedProduct['customer_id'] . ",";
                    $sql .= $order_info['customer_id'] . ",";
                    $sql .= $order_info['customer_id'] . ",";
                    $sql .= "NOW(),";
                    $sql .= $dangerFlag . ",";
                    $sql .= "'" . PROGRAM_CODE . "')";
                    $this->db->query($sql);
                } else {
                    $leftQty = $batch['onhand_qty'] - $buyerQty;
                    $this->db->query("update tb_sys_batch set onhand_qty = " . $leftQty . " where batch_id=" . $batch['batch_id']);
                    $sql = "insert into tb_sys_seller_delivery_line (order_id,order_product_id,product_id,batch_id,qty,warehouse,seller_id,buyer_id,CreateUserName,CreateTime,danger_flag,ProgramCode) VALUES (";
                    $sql .= $selfOperatedProduct['order_id'] . ",";
                    $sql .= $selfOperatedProduct['order_product_id'] . ",";
                    $sql .= $selfOperatedProduct['product_id'] . ",";
                    $sql .= $batch['batch_id'] . ",";
                    $sql .= $buyerQty . ",";
                    $sql .= "'" . $batch['warehouse'] . "',";
                    $sql .= $selfOperatedProduct['customer_id'] . ",";
                    $sql .= $order_info['customer_id'] . ",";
                    $sql .= $order_info['customer_id'] . ",";
                    $sql .= "NOW(),";
                    $sql .= $dangerFlag . ",";
                    $sql .= "'" . PROGRAM_CODE . "')";
                    $this->db->query($sql);
                    break;
                }
            }
        }

    }

    /**
     * 获取产品在库库存的数量
     *
     * @param int $product_id
     * @return int
     */
    public function getProductInStockQuantity($product_id)
    {
        $data = $this->db->query("select sum(onhand_qty) as quantity from tb_sys_batch where product_id = " . (int)$product_id . " limit 1")->row;
        $lockData = $this->db->query("select sum(qty) as lock_qty from oc_product_lock where product_id =" . (int)$product_id . " limit 1")->row;
        $quantity = (int)($data['quantity'] ?? 0);
        $lock_qty = (int)($lockData['lock_qty'] ?? 0);
        return max(($quantity - $lock_qty), 0);
    }

    /**
     * 获取产品上架库存的数量
     *
     * @param int $product_id
     * @return int
     */
    public function getProductOnSelfQuantity($product_id)
    {
        $data = $this->db->query("select quantity from oc_product where product_id=" . (int)$product_id . " limit 1")->row;
        return (int)($data['quantity'] ?? 0);
    }

    /**
     * 设置产品上架库存数量
     *
     * @param int $product_id
     * @param $quantity
     */
    public function setProductOnShelfQuantity($product_id, $quantity)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = " . $quantity . " WHERE product_id = " . (int)$product_id . " AND subtract = 1");
        $this->db->query("UPDATE " . DB_PREFIX . "customerpartner_to_product SET quantity =" . $quantity . " WHERE product_id = " . (int)$product_id);
    }

    /**
     * 更新 产品所属的 combo品的库存数量
     *
     * @param int $product_id 产品ID
     * @param int $filter_combo_id 排除的combo的ID
     * @throws Exception
     */
    public function updateOtherComboQuantity($product_id, $filter_combo_id = 0, $order_id = null)
    {
        $this->load->model('common/product');
        $sql = "select product_id from tb_sys_product_set_info where set_product_id=" . (int)$product_id;
        $filter_combo_id && $sql .= " and product_id !=" . (int)$filter_combo_id;
        $otherCombos = $this->db->query($sql)->rows;
        foreach ($otherCombos as $combo) {
            $product_id = (int)$combo['product_id'];
            $maxQuantity = $this->model_common_product->getProductAvailableQuantity($product_id);
            $comboOnShelfQuantity = $this->getProductOnSelfQuantity($combo['product_id']);
            if ($maxQuantity < $comboOnShelfQuantity) {
                $this->setProductOnShelfQuantity($combo['product_id'], $maxQuantity);
                //将受影响的其他combo,上架数量变化记录
                $orderInfo = $this->getOrderInfo($order_id, $filter_combo_id);
                $this->comboProductStockPreLineBack($orderInfo, $comboOnShelfQuantity - $maxQuantity, $combo['product_id']);
            }
            //如果库存低于 设定的低库存 提醒数量，则需要添加 notification 提醒
            if ((int)$this->config->get('marketplace_low_stock_quantity') > $maxQuantity) {
                $this->addSystemMessageAboutProductStock((int)$combo['product_id']);
            }
        }
    }

    /**
     * 获取二级密码
     * @param int $customer_id
     * @return \Illuminate\Database\Eloquent\Model|null|object|static
     */
    public function getSecondPassowrd($customer_id)
    {
        $password = $this->orm->table('tb_sys_auto_buy_password')->where([
            'customer_id' => $customer_id
        ])->first();
        return $password;
    }

    /**
     * 支付回调失败后发送消息
     * @param int $customer_id BuyerId
     * @param int $order_id
     * @param string $pay_method
     * @param string $error_msg
     */
    public function sendErrorMsg($customer_id, $order_id, $pay_method, $error_msg)
    {
        $mail_subject = '【重要】' . $pay_method . '支付回调失败！';
        $mail_body = "<br><h3>$mail_subject</h3></a><hr>
<table   border='0' cellspacing='0' cellpadding='0' >
<tr><th align='left'>环境:</th><td>" . HTTPS_SERVER . "</td></tr>
<tr><th align='left'>用户ID:</th><td>$customer_id</td></tr>
<tr><th align='left'>order_id:</th><td>$order_id</td></tr>
<tr><th align='left'>pay_method:</th><td>$pay_method</td></tr>
</table><br>error_msg:   $error_msg";
        //payment_fail_send_mail:  true或null 时发邮件
        //     只有为false时  才不发邮件
        $mail_enable = configDB('payment_fail_mail_enable');
        $mail_to = configDB('payment_fail_mail_to', 'b2b_it_team@oristand.com');
        $mail_cc = configDB('payment_fail_mail_cc');
        if (!empty($mail_enable) || $mail_enable || $mail_to) {
            $mail = new Phpmail();
            $mail->subject = $mail_subject;
            $mail->to = $mail_to;
            if (!empty($mail_cc)) {
                $mail->cc = explode(',', $mail_cc);
            }
            $mail->body = $mail_body;
            $mail->send(true);
        }
    }


    /**
     * @param int $orderId
     * @param array $feeOrderIdArr
     * @param int $customerId
     * @param string $paymentMethod
     * @param int $paymentInfoId
     * @return array|void
     */
    public function dealPaymentCallback($orderId, $feeOrderIdArr, $customerId, $paymentMethod,$paymentInfoId)
    {
        $success = false;
        $completeStatus = 5;
        $tryTime = $this->config->get('payment_fail_try_time');
        if (empty($tryTime)) {
            $tryTime = 3;
        }
        $errorMsg = '';
        for ($i = 0; $i < $tryTime && !$success; $i++) {
            try {
                // 开启事务
                $this->db->beginTransaction();
                if (!empty($orderId)) {
                    $orderStatusRow = $this->db->query("SELECT  order_status_id FROM oc_order WHERE order_id={$orderId} FOR UPDATE")->row;
                    if ($orderStatusRow['order_status_id'] == $completeStatus) {
                        $this->db->commit();
                        return;
                    }
                }
                if (!empty($feeOrderIdArr)) {
                    $feeOrderInfos = app(FeeOrderRepository::class)->findFeeOrderInfo($feeOrderIdArr);
                    if ($feeOrderInfos[0]['status'] == $completeStatus) {
                        $this->db->commit();
                        return;
                    }
                }
                $this->callBackUpdateOrder($orderId, $feeOrderIdArr, $paymentMethod, $paymentInfoId);
                //addOrderHistory 清购物车逻辑已移至addOrderHistoryByYzcModel
                $this->addOrderHistoryByYzcModel($orderId, $feeOrderIdArr, $completeStatus);
                $this->payByLineOfCredit($orderId, $feeOrderIdArr, $customerId);//组合支付的时候
                //记录第三方支付的收支流水
                $thirdPaymentData = [
                    'payment_method' => $paymentMethod,
                    'customer_id' => $customerId,
                    'payment_id' => $paymentInfoId
                ];
                $this->thirdPaymentRecord($thirdPaymentData);
                $this->db->commit();
                $this->addOrderHistoryByYzcModelAfterCommit($orderId);
                $success = true;
            } catch (Exception $e) {
                //费用单变更状态异常
                if ($e->getCode() != 231) {
                    $this->db->rollback();
                    $errorMsg = $e->getMessage();
                }
            }
        }
        if (!$success) {
            $msg = "[dealPaymentCallback]" . $paymentMethod . "连续失败" . $i . "次customer_id:" . $customerId . ",order_id:" . $orderId . "errorMsg:" . $errorMsg;
            Logger::error($msg);
            $this->sendErrorMsg($customerId, $orderId, $paymentMethod, $msg);

            $fail_msg = $this->config->get('payment_fail_msg');
            if (empty($fail_msg)) {
                $fail_msg = 'Payment failed, we will deal with it as soon as possible. If you have any questions, please contact us.';
            }
            return array('success' => false, 'msg' => $fail_msg);
        }
        return array('success' => true);
    }

    /**
     * 生成采购订单之后,记录采购的核算基础数据
     *
     * @param int $order_product_id 采购订单明细主键
     * @author chenyang
     */
    public function recordPurchaseAccountingBaseData($order_product_id)
    {
        //查询当前应用的物流报价模板
        $sql_logistic = "SELECT lq.id FROM tb_logistics_quote lq WHERE lq.status = 1 AND lq.effect_date < NOW() AND IFNULL(lq.expire_date,'9999-12-31') > NOW() ORDER BY lq.id DESC LIMIT 1";
        $logistic_query = $this->db->query($sql_logistic);
        $logistic_id = null;
        if (isset($logistic_query->row['id'])) {
            $logistic_id = $logistic_query->row['id'];
        }

        //查询基础数据
        $sql = "SELECT
                  o.order_id,
                  op.order_product_id,
                  op.quantity,
                  p.product_id,
                  p.sku,
                  p.length,
                  p.width,
                  p.height,
                  p.weight,
                  p.combo_flag,
                  IFNULL(ptt.tag_id,0) AS oversize_flag,
                  p.weight_class_id,
                  p.length_class_id,
                  psi.set_product_id,
                  sub_p.sku AS sub_sku,
                  sub_p.length AS sub_length,
                  sub_p.width AS sub_width,
                  sub_p.height AS sub_height,
                  sub_p.weight AS sub_weight,
                  psi.qty AS sub_qty
                FROM
                  oc_order o
                  INNER JOIN oc_order_product op
                    ON o.order_id = op.order_id
                  INNER JOIN oc_product p
                    ON op.product_id = p.product_id
                  LEFT JOIN oc_product_to_tag ptt
                    ON ptt.product_id = p.product_id AND ptt.tag_id = 1
                  LEFT JOIN tb_sys_product_set_info psi
                    ON psi.product_id = p.product_id
                  LEFT JOIN oc_product sub_p
                    ON sub_p.product_id = psi.set_product_id
                WHERE op.order_product_id = " . (int)$order_product_id;

        $query = $this->db->query($sql);
        if (isset($query->rows)) {
            foreach ($query->rows as $row) {
                $base_array = array(
                    'order_id' => $row['order_id'],
                    'order_product_id' => $row['order_product_id'],
                    'quantity' => $row['quantity'],
                    'product_id' => $row['product_id'],
                    'logistic_id' => $logistic_id,
                    'sku' => $row['sku'],
                    'length' => round($row['length'], 2),
                    'width' => round($row['width'], 2),
                    'height' => round($row['height'], 2),
                    'weight' => round($row['weight'], 2),
                    'combo_flag' => $row['combo_flag'],
                    'oversize_flag' => $row['oversize_flag'],
                    'weight_class_id' => $row['weight_class_id'],
                    'length_class_id' => $row['length_class_id']
                );
                if (!empty($row['set_product_id']) && isset($row['quantity'])) {
                    $combo_detail_array[] = array(
                        'set_product_id' => $row['set_product_id'],
                        'sub_sku' => $row['sub_sku'],
                        'sub_length' => round($row['sub_length'], 2),
                        'sub_width' => round($row['sub_width'], 2),
                        'sub_height' => round($row['sub_height'], 2),
                        'sub_weight' => round($row['sub_weight'], 2),
                        'sub_qty' => intval($row['sub_qty']) * intval($row['quantity'])
                    );
                }
            }
            if (isset($base_array)) {
                //插入base表
                $insert_base_sql = "INSERT INTO `tb_sys_purchase_accounting_base` (
                                  `oc_order_id`,
                                  `oc_order_product_id`,
                                  `product_id`,
                                  `item_code`,
                                  `qty`,
                                  `logistics_id`,
                                  `length_class_id`,
                                  `weight_class_id`,
                                  `length`,
                                  `width`,
                                  `height`,
                                  `weight`,
                                  `combo_flag`,
                                  `ltl_flag`,
                                  `memo`,
                                  `create_time`,
                                  `create_username`,
                                  `update_time`,
                                  `update_username`,
                                  `program_code`
                                )
                                VALUES
                                  (
                                    '" . $base_array['order_id'] . "',
                                    '" . $base_array['order_product_id'] . "',
                                    '" . $base_array['product_id'] . "',
                                    '" . $base_array['sku'] . "',
                                    '" . $base_array['quantity'] . "',
                                    '" . $base_array['logistic_id'] . "',
                                    '" . $base_array['length_class_id'] . "',
                                    '" . $base_array['weight_class_id'] . "',
                                    '" . $base_array['length'] . "',
                                    '" . $base_array['width'] . "',
                                    '" . $base_array['height'] . "',
                                    '" . $base_array['weight'] . "',
                                    '" . $base_array['combo_flag'] . "',
                                    '" . $base_array['oversize_flag'] . "',
                                    '',
                                    NOW(),
                                    'system',
                                    NULL,
                                    NULL,
                                    'V1.0'
                                  )";
                $this->db->query($insert_base_sql);
                //插入combo明细数据表
                if (!empty($combo_detail_array)) {
                    $base_id = $this->db->getLastId();
                    $index = 1;
                    $insert_detail_sql = "INSERT INTO `tb_sys_purchase_accounting_detail` (
                                              `accounting_base_id`,
                                              `product_id`,
                                              `item_code`,
                                              `qty`,
                                              `sub_length`,
                                              `sub_width`,
                                              `sub_height`,
                                              `sub_weight`,
                                              `memo`,
                                              `create_time`,
                                              `create_username`,
                                              `update_time`,
                                              `update_username`,
                                              `program_code`
                                            )VALUES";
                    $value_sql = "";
                    foreach ($combo_detail_array as $detail) {
                        if ($index !== 1) {
                            $value_sql .= ",";
                        }
                        $index++;
                        $value_sql .= "(
                                        '" . $base_id . "',
                                        '" . $detail['set_product_id'] . "',
                                        '" . $detail['sub_sku'] . "',
                                        '" . $detail['sub_qty'] . "',
                                        '" . $detail['sub_length'] . "',
                                        '" . $detail['sub_width'] . "',
                                        '" . $detail['sub_height'] . "',
                                        '" . $detail['sub_weight'] . "',
                                        '',
                                        NOW(),
                                        'system',
                                        NULL,
                                        NULL,
                                        'V1.0'
                                      )";
                    }
                    if (!empty($value_sql)) {
                        $insert_detail_sql .= $value_sql;
                        $this->db->query($insert_detail_sql);
                    }
                }
            }
        }
    }


    /* 获取保证金产品对应的协议中约定的产品数量 */
    public function getMarginProductQty($order_id)
    {

        $sql = "select product_id,quantity from oc_order_product WHERE order_id = $order_id";
        $ret = $this->db->query($sql);
        if (!$ret->num_rows) {
            return [];
        }
        $margin_product = $this->arrKeyValue($ret->rows, 'product_id', 'quantity');
        $product_ids = array_keys($margin_product);

        $sql1 = "select margin_id,margin_agreement_id,advance_product_id
                from tb_sys_margin_process
                WHERE advance_product_id IN (" . implode(',', $product_ids) . ")";

        $ret1 = $this->db->query($sql1);
        if (!$ret1->num_rows) {
            return [];
        }
        $margin_process = $this->arrKeyValue($ret1->rows, 'margin_id');
        $agreement_ids = array_keys($margin_process);

        $sql2 = "select id,product_id,num from tb_sys_margin_agreement WHERE id in (" . implode(',', $agreement_ids) . ')';
        $ret2 = $this->db->query($sql2);
        if (!$ret2->num_rows) {
            return [];
        }
        $agreement = $this->arrKeyValue($ret2->rows, 'id');

        foreach ($agreement as $aId => $v) {
            $agreement[$aId]['num'] = $v['num'] * $margin_product[$margin_process[$aId]['advance_product_id']];// 协议数量 * 保证金产品份数
        }

        return $this->arrKeyValue($agreement, 'product_id', 'num');

    }

    public function arrKeyValue($arr, $key, $value = '')
    {
        $retArr = [];
        foreach ($arr as $k => $v) {
            if ($value) {
                $retArr[$v[$key]] = $v[$value];
            } else {
                $retArr[$v[$key]] = $v;
            }
        }

        return $retArr;
    }

    /**
     * 生成订单时预扣除库存
     * @param int $order_id oc_order表的order_id
     * @throws Exception
     * @author xxl
     */
    public function withHoldStock($order_id)
    {

        /**
         * combo 产品添加购买时的关系
         */
        $this->load->model('account/customer_order_import');
        $this->load->model('catalog/margin_product_lock');
        $this->load->model('catalog/futures_product_lock');
        $this->load->model('common/product');

        /** @var ModelCatalogMarginProductLock $productLock */
        $productLock = $this->model_catalog_margin_product_lock;
        $futuresProductLock = $this->model_catalog_futures_product_lock;
        $this->model_account_customer_order_import->associateComboProduct($order_id);

        //1.获取该订单的明细产品
        $orderProducts = $this->db->query("SELECT oop.agreement_id,oop.type_id,oo.order_id,oo.customer_id,oop.order_product_id,oop.product_id,oop.quantity,ctp.customer_id as seller_id FROM " . DB_PREFIX . "order oo
                        LEFT JOIN " . DB_PREFIX . "order_product  oop on oo.order_id=oop.order_id
                        LEFT JOIN " . DB_PREFIX . "customerpartner_to_product ctp on ctp.product_id=oop.product_id
                        WHERE oo.order_id = " . $order_id)->rows;
        if ($orderProducts) {
            foreach ($orderProducts as $orderProduct) {
                //保证金原始店铺的库存扣减
                $product_id = $orderProduct['product_id'];
                $marginProduct = $this->db->query("SELECT sma.id,sma.product_id,sma.num,sma.seller_id,sma.buyer_id FROM tb_sys_margin_process smp
                                        LEFT JOIN tb_sys_margin_agreement sma ON smp.margin_id = sma.id
                                        WHERE smp.advance_product_id = $product_id")->row;
                // 保证金新版本尾款 期货尾款
//                $sql = "SELECT agreement_id,type_id from oc_order_product where order_id = " . $order_id ." and product_id=".$product_id;
//                $cart_info = $this->db->query($sql)->row;

                $transaction_type = $orderProduct['type_id'];
                $cart_agreement_id = $orderProduct['agreement_id'];
                $marginRestProduct = [];//现货尾款
                $futuresRestProduct = [];//期货尾款
                if ($cart_agreement_id && 2 == $transaction_type) {
                    $marginRestProduct = $this->db->query("SELECT sma.id,sma.product_id,sma.num,sma.seller_id,sma.buyer_id
                                            FROM tb_sys_margin_process smp
                                            LEFT JOIN tb_sys_margin_agreement sma ON smp.margin_id = sma.id
                                            WHERE smp.rest_product_id = $product_id and sma.id = $cart_agreement_id")->row;
                }
                if ($cart_agreement_id && 3 == $transaction_type) {
                    $fSql = "select fa.* from oc_futures_margin_agreement as fa
                              LEFT JOIN oc_futures_margin_delivery as fd ON fa.id = fd.agreement_id
                              WHERE fa.agreement_status = 7 AND fd.delivery_type != 2 AND fd.delivery_status=6
                              AND fa.id = {$cart_agreement_id} AND fa.product_id = {$product_id}";
                    $futuresRestProduct = $this->db->query($fSql)->row;
                    //期货二期
                    $fSql = "select id from oc_futures_margin_agreement WHERE contract_id!=0 AND  agreement_status = 3 AND id = {$cart_agreement_id}";
                    $futuresProduct = $this->db->query($fSql)->row;
                    // 期货头款，期货二期，锁定期货合约数量
                    $futuresProduct && $this->orm->table('oc_futures_margin_agreement')->where('id', $cart_agreement_id)->update(['is_lock' => 1]);
                }

                if (!empty($marginProduct)) {

                    $marginData = [
                        "product_id" => $marginProduct['product_id'],
                        "agreement_id" => $marginProduct['id'],
                        "quantity" => $marginProduct['num'],
                        "seller_id" => $marginProduct['seller_id'],
                        "customer_id" => $marginProduct['buyer_id'],
                        "order_id" => $orderProduct['order_id'],
                        "order_product_id" => $orderProduct['order_product_id']
                    ];
                    $fmSql = "select fd.id from oc_futures_margin_delivery as fd
                              LEFT JOIN tb_sys_margin_process as mp ON fd.margin_agreement_id = mp.margin_id
                              WHERE fd.margin_agreement_id={$cart_agreement_id} AND mp.advance_product_id={$product_id}
                              AND fd.delivery_type != 1 AND fd.delivery_status = 6";
                    $futures2margin = $this->db->query($fmSql)->row;

                    //$this->holdMarginOrderBatch($marginData);
                    //保证金头款产品预减库存流程
                    //1.更改上架以及combo影响的产品库存
                    //2.oc_order_lock表插入保证金表数据
                    //3.更改头款商品上架库存以及combo影响的产品库存
                    //$this->updateOnshelfQuantity($marginProduct['product_id'],$marginProduct['num']);
                    if (!$futures2margin) {//期货转现货 锁定尾款商品库存发生在确认交割方式时，此处不需要重复锁定
                        $productLock->TailIn($marginProduct['id'], $marginProduct['num'], $order_id, 0);
                    }
                    $this->insertMarginProductLock($marginData);
                } elseif (!empty($marginRestProduct)) {
                    //保证金尾款
                    //1.oc_order_lock表插入保证金表数据
                    // 保证金这块同样改变
                    $this->holdSellerBatch($orderProduct);
                    /** @see ControllerEventProductStock::lockAfter() */
                    $productLock->TailOut($cart_agreement_id, $orderProduct['quantity'], $order_id, 1);
                } elseif (!empty($futuresRestProduct)) {
                    //期货尾款
                    $this->holdSellerBatch($orderProduct);
                    $futuresProductLock->TailOut($cart_agreement_id, $orderProduct['quantity'], $order_id, 1);
                } else {
                    //add by xxl 扣减seller的批次库存,增加seller的预出库记录
                    $this->holdSellerBatch($orderProduct);
                    //end xxl
                }
                //验证是否是尾款商品，尾款不需要上下架库存
                if (empty($marginRestProduct) && empty($futuresRestProduct)) {
                    $this->updateOnshelfQuantity($orderProduct['product_id'], $orderProduct['quantity'], $orderProduct);
                    // 即使是普通商品的购买 也需要考虑在库库存和保证金店铺相应的关系
                    // 防止超卖 wangjinxin
                    $this->model_common_product->updateProductOnShelfQuantity($orderProduct['product_id']);
                }
            }
        }
    }

    /**
     * [insertMarginProductLock description] 增加履约人
     * @param $data
     */
    public function insertMarginProductLock($data)
    {
        //$sql = "SELECT ";
        //$sql .= "set_product_id,set_mpn,qty from tb_sys_product_set_info where product_id=" . $data['product_id'] . " and set_product_id is not null";
        //$setProductInfoArr = $this->db->query($sql)->rows;
        //if($setProductInfoArr){
        //    foreach ($setProductInfoArr as $setProductInfo) {
        //        $set_product_id = $setProductInfo['set_product_id'];
        //        $sql_child = "insert into ";
        //        $sql_child .=  DB_PREFIX."product_lock (product_id,seller_id,agreement_id,type_id,origin_qty,qty
        //            ,parent_product_id,set_qty,memo,create_user_name,create_time) value";
        //        $sql_child .="(".$set_product_id.",".$data['seller_id'].",".$data['agreement_id'].",2"
        //        .",".$data['quantity'].",".$data['quantity'].",".$data['product_id'].",".$setProductInfo['qty'].",'margin lock',"
        //        .$data['customer_id'].",'".date('Y-m-d H:i:s',time())."')";
        //        $this->db->query($sql_child);
        //    }
        //} else{
        //    $sql_child = "insert into ";
        //    $sql_child .=  DB_PREFIX."product_lock (product_id,seller_id,agreement_id,type_id,origin_qty,qty
        //                    ,parent_product_id,set_qty,memo,create_user_name,create_time) value";
        //    $sql_child .="(".$data['product_id'].",".$data['seller_id'].",".$data['agreement_id'].",2"
        //        .",".$data['quantity'].",".$data['quantity'].",".$data['product_id'].",1,'margin lock',"
        //    .$data['customer_id'].",'".date('Y-m-d H:i:s',time())."')";
        //    $this->db->query($sql_child);
        //}
        //--------------------------------------------------------------------------
        // 增加履约人
        //--------------------------------------------------------------------------
        $sql = "INSERT ";
        $sql .= "INTO oc_agreement_common_performer
            SET agreement_id={$data['agreement_id']},
            product_id={$data['product_id']},
            agreement_type = {$this->config->get('common_performer_type_margin_spot')},
            is_signed=1,
            create_time=NOW(),
            create_user_name='{$data['customer_id']}',
            buyer_id='{$data['customer_id']}'";
        $this->db->query($sql);


    }

    /**
     * seller 库存预出库
     * @author xxl
     * @param $selfOperatedProduct
     * @param $order_info
     */
    public function holdSellerBatch($orderProduct)
    {
        $this->load->model('account/notification');
        //判断是否为combo
        $result = $this->db->query("SELECT combo_flag,product_type from oc_product  where product_id = " . $orderProduct['product_id'])->row;
        $combo_flag = $result['combo_flag'];
        $product_type = $result['product_type'];
        //普通商品走预出库逻辑
        if ($product_type == 0) {
            if ($combo_flag == 1) {
                //获取combo的子产品
                $comboProducts = $this->db->query("select tspsi.set_product_id,tspsi.qty from tb_sys_product_set_info tspsi where tspsi.product_id = " . $orderProduct['product_id'])->rows;
                foreach ($comboProducts as $comboProduct) {
                    $buyerQty = $orderProduct['quantity'];
                    $seller_batchs = $this->db->query("SELECT batch_id,onhand_qty,warehouse  from tb_sys_batch  where onhand_qty >0 and product_id = " . $comboProduct['set_product_id'])->rows;
                    $buyerQty = $buyerQty * $comboProduct['qty'];
                    foreach ($seller_batchs as $batch) {
                        // 如果当前batch数量不满足购买数量，则一次性扣除完。
                        if ($buyerQty > $batch['onhand_qty']) {
                            $buyerQty = $buyerQty - $batch['onhand_qty'];
                            $this->db->query("update tb_sys_batch set onhand_qty = 0 where batch_id=" . $batch['batch_id']);
                            $sql = "insert into tb_sys_seller_delivery_pre_line (order_id,order_product_id,product_id,batch_id,qty,warehouse,seller_id,buyer_id,CreateUserName,CreateTime,ProgramCode) VALUES (";
                            $sql .= $orderProduct['order_id'] . ",";
                            $sql .= $orderProduct['order_product_id'] . ",";
                            $sql .= $comboProduct['set_product_id'] . ",";
                            $sql .= $batch['batch_id'] . ",";
                            $sql .= $batch['onhand_qty'] . ",";
                            $sql .= "'" . $batch['warehouse'] . "',";
                            $sql .= $orderProduct['seller_id'] . ",";
                            $sql .= $orderProduct['customer_id'] . ",";
                            $sql .= $orderProduct['customer_id'] . ",";
                            $sql .= "NOW(),";
                            $sql .= "'" . PROGRAM_CODE . "')";
                            $this->db->query($sql);
                        } else {
                            // 当前batch满足购买数量，则只扣除购买的数量之后，break退出循环。
                            $leftQty = $batch['onhand_qty'] - $buyerQty;
                            $this->db->query("update tb_sys_batch set onhand_qty = " . $leftQty . " where batch_id=" . $batch['batch_id']);
                            $sql = "insert into tb_sys_seller_delivery_pre_line (order_id,order_product_id,product_id,batch_id,qty,warehouse,seller_id,buyer_id,CreateUserName,CreateTime,ProgramCode) VALUES (";
                            $sql .= $orderProduct['order_id'] . ",";
                            $sql .= $orderProduct['order_product_id'] . ",";
                            $sql .= $comboProduct['set_product_id'] . ",";
                            $sql .= $batch['batch_id'] . ",";
                            $sql .= $buyerQty . ",";
                            $sql .= "'" . $batch['warehouse'] . "',";
                            $sql .= $orderProduct['seller_id'] . ",";
                            $sql .= $orderProduct['customer_id'] . ",";
                            $sql .= $orderProduct['customer_id'] . ",";
                            $sql .= "NOW(),";
                            $sql .= "'" . PROGRAM_CODE . "')";
                            $this->db->query($sql);
                            $buyerQty = 0;
                            break;
                        }

                    }
                    if ($buyerQty > 0) {
                        $msg = "order_id = " . $orderProduct['order_id'] . ",product_id=" . $comboProduct['set_product_id'] . "库存不足";
                        Logger::error($msg);
                        throw new Exception('products are not available with your desired quantity or not in stock.', 999);
                    }
                }
            } else {
                $seller_batchs = $this->db->query("SELECT batch_id,onhand_qty,warehouse from tb_sys_batch  where onhand_qty >0 and product_id = " . $orderProduct['product_id'])->rows;
                $buyerQty = $orderProduct['quantity'];
                foreach ($seller_batchs as $batch) {
                    if ($buyerQty > $batch['onhand_qty']) {
                        $buyerQty = $buyerQty - $batch['onhand_qty'];
                        $this->db->query("update tb_sys_batch set onhand_qty = 0 where batch_id=" . $batch['batch_id']);
                        $sql = "insert into tb_sys_seller_delivery_pre_line (order_id,order_product_id,product_id,batch_id,qty,warehouse,seller_id,buyer_id,CreateUserName,CreateTime,ProgramCode) VALUES (";
                        $sql .= $orderProduct['order_id'] . ",";
                        $sql .= $orderProduct['order_product_id'] . ",";
                        $sql .= $orderProduct['product_id'] . ",";
                        $sql .= $batch['batch_id'] . ",";
                        $sql .= $batch['onhand_qty'] . ",";
                        $sql .= "'" . $batch['warehouse'] . "',";
                        $sql .= $orderProduct['seller_id'] . ",";
                        $sql .= $orderProduct['customer_id'] . ",";
                        $sql .= $orderProduct['customer_id'] . ",";
                        $sql .= "NOW(),";
                        $sql .= "'" . PROGRAM_CODE . "')";
                        $this->db->query($sql);
                    } else {
                        $leftQty = $batch['onhand_qty'] - $buyerQty;
                        $this->db->query("update tb_sys_batch set onhand_qty = " . $leftQty . " where batch_id=" . $batch['batch_id']);
                        $sql = "insert into tb_sys_seller_delivery_pre_line (order_id,order_product_id,product_id,batch_id,qty,warehouse,seller_id,buyer_id,CreateUserName,CreateTime,ProgramCode) VALUES (";
                        $sql .= $orderProduct['order_id'] . ",";
                        $sql .= $orderProduct['order_product_id'] . ",";
                        $sql .= $orderProduct['product_id'] . ",";
                        $sql .= $batch['batch_id'] . ",";
                        $sql .= $buyerQty . ",";
                        $sql .= "'" . $batch['warehouse'] . "',";
                        $sql .= $orderProduct['seller_id'] . ",";
                        $sql .= $orderProduct['customer_id'] . ",";
                        $sql .= $orderProduct['customer_id'] . ",";
                        $sql .= "NOW(),";
                        $sql .= "'" . PROGRAM_CODE . "')";
                        $this->db->query($sql);
                        $buyerQty = 0;
                        break;
                    }
                }
                if ($buyerQty > 0) {
                    $msg = "order_id = " . $orderProduct['order_id'] . ",product_id=" . $orderProduct['product_id'] . "库存不足";
                    Logger::error($msg);
                    throw new Exception('products are not available with your desired quantity or not in stock.', 999);
                }
            }
        }

        //库存订阅提醒
//        $this->load->model('account/wishlist');
//        $this->model_account_wishlist->addCommunication($orderProduct['product_id']);

    }

    /**
     * 获取订单的超时时间
     * @param int $order_id
     * @return float|int
     */
    public function checkOrderExpire($order_id)
    {
        $result = $this->db->query('SELECT date_added FROM oc_order where order_id = ' . $order_id)->row;
        //获取订单失效时间
        $date_add = $result['date_added'];
        $intervalTime = (time() - strtotime($date_add)) / 60;
        return $intervalTime;
    }

    /**
     * @author xxl
     * 库存回退并cancel采购订单
     */
    public function cancelPurchaseOrderAndReturnStock($order_id)
    {
        try {
            //内部店铺退回上架库存在库库存（根据batchId判断批次库存有没有更新）,外部店铺(包销店铺)上架库存和在库库存都回退,设置预出库表的状态,cancel采购订单
            $orderInfos = $this->db->query("select oop.product_id,oop.order_id,oop.order_product_id,oop.quantity,
            oc.customer_id,oc.accounting_type,op.combo_flag,oo.date_added
            FROM oc_order_product oop
            LEFT JOIN oc_order oo ON oo.order_id = oop.order_id
            LEFT JOIN oc_product op ON op.product_id=oop.product_id
            LEFT JOIN oc_customerpartner_to_product ctp ON  ctp.product_id=oop.product_id
            LEFT JOIN oc_customer oc ON oc.customer_id=ctp.customer_id
            WHERE oop.order_id = " . $order_id)->rows;

            foreach ($orderInfos as $orderInfo) {
                //获取预出库明细
                $preDeliveryLines = $this->db->query("SELECT id,product_id,batch_id,qty FROM tb_sys_seller_delivery_pre_line WHERE order_product_id = " . $orderInfo['order_product_id'])->rows;
                if (count($preDeliveryLines) > 0) {
                    //外部店铺或者包销店铺退库存处理
                    if (in_array($orderInfo['customer_id'], $this->config->get('config_customer_group_ignore_check')) || $orderInfo['accounting_type'] == 2) {
                        //判断是否为combo品
                        if ($orderInfo['combo_flag'] == 1) {
                            foreach ($preDeliveryLines as $preDeliveryLine) {
                                //返还批次库存
                                $this->db->query("update tb_sys_batch set onhand_qty = onhand_qty+" . $preDeliveryLine['qty'] . " where batch_id = " . $preDeliveryLine['batch_id']);
                                //返还产品上架数量
                                $this->db->query("update oc_product set quantity = quantity +" . $preDeliveryLine['qty'] . " where product_id =" . $preDeliveryLine['product_id']);
                                $this->db->query("update oc_customerpartner_to_product set quantity = quantity +" . $preDeliveryLine['qty'] . " where product_id =" . $preDeliveryLine['product_id']);
                                // 同步更改子sku所属的其他combo的上架库存数量。
                                $this->updateOtherComboQuantity($preDeliveryLine['product_id']);
                                //设置预出库表的这状态
                                $this->db->query("update tb_sys_seller_delivery_pre_line set status = 0 where id=" . $preDeliveryLine['id']);
                            }
                        } else {
                            //非combo品
                            foreach ($preDeliveryLines as $preDeliveryLine) {
                                //返还批次库存
                                $this->db->query("update tb_sys_batch set onhand_qty = onhand_qty+" . $preDeliveryLine['qty'] . " where batch_id = " . $preDeliveryLine['batch_id']);
                                //返还产品上架数量
                                $this->db->query("update oc_product set quantity = quantity +" . $preDeliveryLine['qty'] . " where product_id =" . $preDeliveryLine['product_id']);
                                $this->db->query("update oc_customerpartner_to_product set quantity = quantity +" . $preDeliveryLine['qty'] . " where product_id =" . $preDeliveryLine['product_id']);
                                //设置预出库表的这状态
                                $this->db->query("update tb_sys_seller_delivery_pre_line set status = 0 where id=" . $preDeliveryLine['id']);
                            }
                        }

                    } else {
                        //内部店铺的cancel采购订单出库
                        //判断是否为combo品
                        if ($orderInfo['combo_flag'] == 1) {
                            if ($orderInfo['combo_flag'] == 1) {
                                foreach ($preDeliveryLines as $preDeliveryLine) {
                                    //返还批次库存
                                    $this->db->query("update tb_sys_batch set onhand_qty = onhand_qty+" . $preDeliveryLine['qty'] . " where batch_id = " . $preDeliveryLine['batch_id']);
                                    $sync_qty_date = $this->db->query("select ifnull(sync_qty_date,'2018-01-01 00:00:00') as sync_qty_date from oc_product where product_id=" . $preDeliveryLine['product_id'] . "  for update")->row['sync_qty_date'];
                                    $date_added = strtotime($orderInfo['date_added']);
                                    if ($date_added > strtotime($sync_qty_date)) {
                                        //返还产品上架数量
                                        $this->db->query("update oc_product set quantity = quantity +" . $preDeliveryLine['qty'] . " where product_id =" . $preDeliveryLine['product_id']);
                                        $this->db->query("update oc_customerpartner_to_product set quantity = quantity +" . $preDeliveryLine['qty'] . " where product_id =" . $preDeliveryLine['product_id']);
                                        // 同步更改子sku所属的其他combo的上架库存数量。
                                        $this->updateOtherComboQuantity($preDeliveryLine['product_id']);
                                    }
                                    //设置预出库表的这状态
                                    $this->db->query("update tb_sys_seller_delivery_pre_line set status = 0 where id=" . $preDeliveryLine['id']);
                                }
                            } else {
                                foreach ($preDeliveryLines as $preDeliveryLine) {
                                    //返还批次库存
                                    $this->db->query("update tb_sys_batch set onhand_qty = onhand_qty+" . $preDeliveryLine['qty'] . " where batch_id = " . $preDeliveryLine['batch_id']);
                                    $sync_qty_date = $this->db->query("select ifnull(sync_qty_date,'2018-01-01 00:00:00') as sync_qty_date from oc_product where product_id=" . $preDeliveryLine['product_id'] . "  for update")->row['sync_qty_date'];
                                    $date_added = strtotime($orderInfo['date_added']);
                                    if ($date_added > strtotime($sync_qty_date)) {
                                        //返还产品上架数量
                                        $this->db->query("update oc_product set quantity = quantity +" . $preDeliveryLine['qty'] . " where product_id =" . $preDeliveryLine['product_id']);
                                        $this->db->query("update oc_customerpartner_to_product set quantity = quantity +" . $preDeliveryLine['qty'] . " where product_id =" . $preDeliveryLine['product_id']);
                                    }
                                    //设置预出库表的这状态
                                    $this->db->query("update tb_sys_seller_delivery_pre_line set status = 0 where id=" . $preDeliveryLine['id']);
                                }
                            }
                        }
                    }
                } else {
                    $msg = "[采购订单超时返还库存错误]：采购订单明细：" . $orderInfo['order_product_id'] . ",未找到对应预出库记录";
                    $this->log->write($msg);
                }
            }

            //cancel采购订单
            $this->db->query("update oc_order set order_status_id = ".OcOrderStatus::CANCELED." WHERE  order_id=" . $order_id);
        } catch (Exception $e) {
            //记录日志
            $this->log->write("采购订单超时反库存错误" . $order_id . ",error" . $e->getMessage());
            throw new Exception("采购订单超时反库存错误" . $order_id);
        }

    }

    /**
     * 获取采购订单的状态
     * @param int $order_id
     * @return array
     */
    public function getOrderStatusByOrderId($order_id)
    {
        $result = $this->db->query('select order_status_id,date_added from oc_order where order_id=' . $order_id . ' for update')->row;
        return $result;
    }

    public function addSellerDeliveryInfo($selfOperatedProduct)
    {
        //判断是否为combo
        $productModel = Product::query()->where('product_id', $selfOperatedProduct['product_id'])->select(['combo_flag', 'danger_flag'])->first();
        $combo_flag = $productModel->combo_flag;
        $dangerFlag = $productModel->danger_flag;
        if ($combo_flag == 1) {
            $this->db->query("insert into tb_sys_seller_delivery_line ( order_id,order_product_id,product_id,batch_id,qty,warehouse,seller_id,buyer_id,Memo,CreateUserName,CreateTime,UpdateUserName,UpdateTime,ProgramCode,danger_flag)
        SELECT dpl.order_id,dpl.order_product_id,dpl.product_id,dpl.batch_id,dpl.qty,dpl.warehouse,dpl.seller_id,dpl.buyer_id,dpl.Memo,dpl.CreateUserName,dpl.CreateTime,dpl.UpdateUserName,dpl.UpdateTime,dpl.ProgramCode,soc.danger_flag
        from tb_sys_order_combo soc left join tb_sys_seller_delivery_pre_line dpl ON soc.order_product_id = dpl.order_product_id and soc.set_product_id = dpl.product_id
        where dpl.status = 1 and soc.order_id = " . $selfOperatedProduct['order_id'] . " and soc.product_id =" . $selfOperatedProduct['product_id']);
        } else {
            $this->db->query("insert into tb_sys_seller_delivery_line ( order_id,order_product_id,product_id,batch_id,qty,warehouse,seller_id,buyer_id,Memo,CreateUserName,CreateTime,UpdateUserName,UpdateTime,ProgramCode,danger_flag)
        SELECT order_id,order_product_id,product_id,batch_id,qty,warehouse,seller_id,buyer_id,Memo,CreateUserName,CreateTime,UpdateUserName,UpdateTime,ProgramCode,{$dangerFlag}
        from tb_sys_seller_delivery_pre_line where status = 1 and order_id = " . $selfOperatedProduct['order_id'] . " and product_id =" . $selfOperatedProduct['product_id'] . " and order_product_id = {$selfOperatedProduct['order_product_id']}");
        }
    }

    //低库存时 给seller发送一条system消息
    public function addSystemMessageAboutProductStock($productId)
    {
        // 防止多次发送同样的提醒
        static $send_product_ids = [];
        if (in_array($productId, $send_product_ids)) {
            return;
        }
        $sql = "select quantity,sku,mpn,product_type from oc_product WHERE product_id=$productId ";
        $product = $this->db->query($sql);
        if ($product->row['product_type'] != 0) {
            // 如果 不是普通商品 直接返回
            return;
        }
        // 获取锁定库存 上架库存
        $this->load->model('catalog/margin_product_lock');
        $this->load->model('common/product');
        // 锁定库存
        $lock_qty = (int)$this->model_catalog_margin_product_lock->getProductMarginQty((int)$productId);
        // 在库库存
        $inStoreQty = (int)$this->model_common_product->getProductInStockQuantity((int)$productId);
        if (
            isset($product->row['quantity'])
            && (int)$this->config->get('marketplace_low_stock_quantity') >= $product->row['quantity']
        ) {
            $sellerId = $this->orm->table('oc_customerpartner_to_product')->where('product_id', $productId)->value('customer_id');

            // #6774 系统发送站内信通知前需校验此前12小时内是否发送该Item的低库存报警站内信，如果发送过，则系统不再通知 key: sku . buyerId . 'Low Inventory Alert'
            $redisKey = $product->row['sku'] . $sellerId . \App\Models\Message\Msg::KEY_LOW_INVENTORY_ALERT;
            if (!app('redis')->exists($redisKey)) {
                $subject = 'Item code:' . $product->row['sku'] . ' Low Stock Alert';
                $message = '<table   border="0" cellspacing="0" cellpadding="0">';
                $message .= '<tr><th align="left">Item Code/MPN:&nbsp</th><td style="width: 650px">
                          <a href="' . $this->url->link('product/product', '&product_id=' . $productId) . '">' . $product->row['sku'] . '/' . $product->row['mpn'] . '</a>
                          </td></tr> ';
                $message .= '<tr><th align="left">In-Stock Quantity:&nbsp</th><td style="width: 650px">' . $inStoreQty . '</td></tr>';
                $message .= '<tr><th align="left">Locked Inventory:&nbsp</th><td style="width: 650px">'
                    . $lock_qty
                    . '[Locked quantity in margin agreement]</td></tr>';
                $message .= '<tr><th align="left">Available Quantity:&nbsp</th><td style="width: 650px">' . $product->row['quantity'] . '</td></tr>';

                $message .= '</table>';
                $this->load->model('message/message');
                $this->model_message_message->addSystemMessageToBuyer('product_inventory', $subject, $message, $sellerId);

                app('redis')->setex($redisKey, 43200, 1);
            }
        }
        $send_product_ids[] = $productId;
    }

    /*
     * 给对应的seller发一条system消息
     * @param Array data 包含 id:买家ID，status：订单状态，order_id：订单id
     *
     * */
    public function addSystemMessageAboutOrderStatus($data)
    {

        $this->load->model('message/message');
        $this->load->model('account/customer');
        if (empty($data['id']) && isset($data['customer_id'])) {
            $data['id'] = $data['customer_id'];
        }
        $nickname = $this->model_account_customer->getCustomerNicknameAndNumber($data['id']);

        $subject = 'New order: #' . $data['order_id'] . ' has been placed by <b>' . $nickname . '</b>';
        $message = '<table   border="0" cellspacing="0" cellpadding="0">';
        $message .= '<tr><th align="left">Order ID:&nbsp</th><td style="width: 650px">
                          <a href="' . $this->url->link('account/customerpartner/orderinfo', '&order_id=' . $data['order_id']) . '">' . $data['order_id'] . '</a>
                          </td></tr> ';
        $message .= '<tr><th align="left">Name:&nbsp</th><td style="width: 650px">' . $nickname . '</td></tr>';
        $message .= '</table>';

        $orderProduct = $this->orm->table('oc_order_product as op')
            ->leftJoin('oc_customerpartner_to_product as cp', 'op.product_id', 'cp.product_id')
            ->leftJoin('oc_product as p', 'p.product_id', 'op.product_id')
            ->where('order_id', $data['order_id'])
            ->select('op.product_id', 'op.quantity', 'p.sku', 'p.mpn', 'cp.customer_id')
            ->get();
        $orderProduct = obj2array($orderProduct);
        foreach ($orderProduct as $k => $v) {
            $seller[$v['customer_id']][] = $v;
        }
        if (empty($seller)) {
            return;
        }

        $message .= '<table style="margin-top: 10px;width: 80%" border="1px" class="table table-bordered table-hover">
                            <thead><tr><td class="text-center">Item Code/MPN</td>
                                <td class="text-center">Quantity</td></tr></thead><tbody>';
        foreach ($seller as $sellerId => $value) {
            $messageStr = $message;
            foreach ($value as $kk => $vv) {
                $messageStr .= '<tr><td class="text-center">' . $vv['sku'] . ' / ' . $vv['mpn'] . '</td><td class="text-center">' . $vv['quantity'] . '</td></tr>';
            }

            $messageStr .= '</tbody></table>';

            $this->model_message_message->addSystemMessageToBuyer('order_status', $subject, $messageStr, $sellerId);
        }

    }


    /*
     * 给买家发送一条采购订单已完成支付的system消息
     *
     * */
    public function addSystemMessageAboutOrderPay($orderId, $customerId)
    {

        $balance = $this->orm->table('oc_order_total')
            ->where(['order_id' => $orderId, 'code' => 'balance'])
            ->value('title');

        $payment = $this->orm->table('oc_order')
            ->where('order_id', $orderId)
            ->value('payment_method');
        if ($balance) {
            $payment .= ' + ' . $balance;
        }

        $subject = 'Purchase order ID ' . $orderId . ' has been completed';
        $message = '<table   border="0" cellspacing="0" cellpadding="0">';
        $message .= '<tr><th align="left">Purchase Order ID:&nbsp</th><td style="width: 650px">
                          <a href="' . $this->url->link('account/order/purchaseOrderInfo', '&order_id=' . $orderId) . '">' . $orderId . '</a>
                          </td></tr> ';
        $message .= '<tr><th align="left">Payment Method:&nbsp</th><td style="width: 650px">' . $payment . '</td></tr>';
        $message .= '</table>';


        $orderProduct = $this->orm->table('oc_order_product as op')
            ->leftJoin('oc_product as p', 'p.product_id', 'op.product_id')
            ->where('order_id', $orderId)
            ->select('op.product_id', 'op.quantity', 'p.sku')
            ->get();
        $orderProduct = obj2array($orderProduct);

        if (empty($orderProduct)) {
            return;
        }

        $message .= '<table style="margin-top: 10px;width: 80%" border="1px" class="table table-bordered table-hover">
                            <thead><tr><td class="text-center">Item Code</td>
                                <td class="text-center">Quantity</td></tr></thead><tbody>';
        foreach ($orderProduct as $key => $value) {

            $message .= '<tr><td class="text-center">' . $value['sku'] . '</td><td class="text-center">' . $value['quantity'] . '</td></tr>';
        }

        $message .= '</tbody></table>';

        $this->load->model('message/message');
        $this->model_message_message->addSystemMessageToBuyer('purchase_order', $subject, $message, $customerId);
    }

    /**
     *  保证金库存预出库
     * @author xxl
     * @param $data
     * @throws Exception
     */
    public function holdMarginOrderBatch($data)
    {
        $this->load->model('account/notification');
        //判断是否为combo
        $combo_flag = $this->db->query("SELECT combo_flag from oc_product  where product_id = " . $data['product_id'])->row['combo_flag'];
        if ($combo_flag == 1) {
            //获取combo的子产品
            $comboProducts = $this->db->query("select tspsi.set_product_id,tspsi.qty from tb_sys_product_set_info tspsi where tspsi.product_id = " . $data['product_id'])->rows;
            foreach ($comboProducts as $comboProduct) {
                $buyerQty = $data['quantity'] * $comboProduct['qty'];
                $this->marginBatchOut($data['seller_id'], $comboProduct['set_product_id'], $buyerQty, $data);
            }
        } else {
            $this->marginBatchOut($data['seller_id'], $data['product_id'], $data['quantity'], $data);
        }
        //库存订阅提醒
        $this->load->model('account/wishlist');
//        $this->model_account_wishlist->addCommunication($data['product_id']);

    }

    /**
     * 扣减上架库存
     * @param int $product_id
     * @param int $quantity
     * @param $orderProduct
     * @param bool $is_margin
     * @throws Exception
     * @author xxl
     */
    public function updateOnshelfQuantity($product_id, $quantity, $orderProduct, $is_margin = false)
    {
        $this->load->model('account/wishlist');
        /** @var ModelCommonProduct $productModel */
        $productModel = load()->model('common/product');
        // 扣减上架库存
        $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = (quantity - " . (int)$quantity . ") WHERE product_id = " . (int)$product_id . " AND subtract = '1'");
        $this->db->query("UPDATE " . DB_PREFIX . "customerpartner_to_product SET quantity = quantity-" . (int)$quantity . " WHERE product_id = " . (int)$product_id);

        $onshelfQty = (int)db('oc_product')->where('product_id', $product_id)->value('quantity');
        if ($onshelfQty < 0) {
            $msg = "order_id = " . $orderProduct['order_id'] . ",product_id=" . $orderProduct['product_id'] . "库存不足";
            Logger::error($msg);
            throw new Exception("Products [{$product_id}] are not available with your desired quantity or not in stock.", 999);
        }
        // 添加 低库存提醒
        $this->addSystemMessageAboutProductStock($product_id);

        /**
         * 1.同步扣减子SKU的上架库存数量
         *     注: 如果在库数量 小于 上架数量, 则修改上架数量为最大可上架数量; 反之, 不变
         * 2.同步更改子sku所属的其他combo的上架库存数量。
         *     注: 如果在库数量 小于 上架数量, 则修改上架数量为最大可上架数量; 反之, 不变
         */
        $setProductInfoArr = $this->db->query("select set_product_id,set_mpn,qty from tb_sys_product_set_info where product_id=" . $product_id . " and set_product_id is not null")->rows;
        foreach ($setProductInfoArr as $setProductInfo) {
            // 如果 set_product_id 为 空/NULL/0 则跳过，不处理。(未关联产品ID)
            if (!isset($setProductInfo['set_product_id']) || empty($setProductInfo['set_product_id'])) {
                continue;
            }
            // 同步扣减子SKU的库存
            $setProductInStockQuantity = $productModel->getProductAvailableQuantity($setProductInfo['set_product_id']);
            $setProductOnShelfQuantity = $this->getProductOnSelfQuantity($setProductInfo['set_product_id']);
            $beforeOnShelfQuantity = $setProductOnShelfQuantity;
            // 如果在库数量 小于 上架数量, 则修改上架数量为最大可上架数量
            if ($setProductInStockQuantity < $setProductOnShelfQuantity) {
                $this->setProductOnShelfQuantity($setProductInfo['set_product_id'], $setProductInStockQuantity);
            }

            // 添加子 sku 的 notification 低库存提醒
            $this->addSystemMessageAboutProductStock((int)$setProductInfo['set_product_id']);
            // 同步更改子sku所属的其他combo的上架库存数量。
            if ($is_margin) {
                $this->updateOtherComboQuantity($setProductInfo['set_product_id'], $orderProduct['product_id'], $orderProduct['order_id']);
            } else {
                $this->updateOtherComboQuantity($setProductInfo['set_product_id'], $product_id, $orderProduct['order_id']);
            }
            //N-475 未支付订单退还库存时，子SKU应该退还的数量
            $this->childProductStockPreLineBack($orderProduct, $setProductInfo, $beforeOnShelfQuantity);
            //end N-475
        }

        /**
         * 如果当前产品是 其他combo 的组成，则需要同步修改之
         */
        if ($is_margin) {
            $this->updateOtherComboQuantity($product_id, $orderProduct['product_id'], $orderProduct['order_id']);
        } else {
            $this->updateOtherComboQuantity($product_id, $product_id, $orderProduct['order_id']);
        }

        //库存订阅提醒
//        $this->model_account_wishlist->addCommunication($product_id);
    }

    /**
     * 保证金产品，从原始店铺调出货
     * @param int $seller_id
     * @param int $product_id
     * @param int $quantity 保证金调货数量
     * @param $data
     * @throws Exception
     */
    public function marginBatchOut($seller_id, $product_id, $quantity, $data)
    {
        //处理调出单、调入单、调货日志
        $sql = "SELECT
              *
            FROM
              tb_sys_batch sb
            WHERE sb.customer_id = {$seller_id}
              AND sb.product_id = {$product_id}
              AND sb.onhand_qty > 0
            ORDER BY sb.onhand_qty DESC ";
        $query = $this->db->query($sql);
        //file_put_contents('testlog.txt', PHP_EOL . PHP_EOL . __LINE__ . PHP_EOL . $sql, FILE_APPEND);
        $batchs = $query->rows;
        //判断当前库存是否充足
        $num_tmp = 0;
        foreach ($batchs as &$value) {
            $num_tmp += $value['onhand_qty'];
            if ($num_tmp >= $quantity) {//数量充足则跳出循环
                break;
            }
        }
        if ($num_tmp < $quantity) {
            //库存不足
            $errorMsg = 'notFull保证金调货-库存不足.产品product_id=' . $product_id . '.在库总数=' . $num_tmp . '.要调总数=' . $quantity . '.File=' . __FILE__ . '.line=' . __LINE__;
            throw new Exception($errorMsg);
        } else {
            unset($value, $num_tmp, $num_tmp_out);
            $num_tmp = 0;//累计每一条批次库存的总数
            $is_break = false;
            foreach ($batchs as &$value) {
                $num_tmp_out = 0;//每一条批次库存中，实际要调出的数量
                $num_tmp += $value['onhand_qty'];
                if ($num_tmp == $quantity) {
                    $num_tmp_out = $value['onhand_qty'];
                    $is_break = true;
                } elseif ($num_tmp > $quantity) {
                    $num_tmp_out = $quantity - ($num_tmp - $value['onhand_qty']);
                    $is_break = true;
                } else {
                    //$num_tmp < $set_num
                    $num_tmp_out = $value['onhand_qty'];
                    $is_break = false;
                }
                $this->batchOut($value, $num_tmp_out, $data);//保证金店铺批次库存扣减，生成预出库记录
                if ($is_break) {
                    break;
                }
            }
            unset($value, $num_tmp, $num_tmp_out);
        }
    }

    /**
     * 扣减保证金原店铺的产品，生成预出库记录
     * @param $batch
     * @param $decNum
     * @param $data
     */
    private function batchOut($batch, $decNum, $data)
    {
        $batch_id = $batch['batch_id'];
        //减少在库数量
        $sql = "UPDATE tb_sys_batch
        SET onhand_qty=(onhand_qty-{$decNum})
        WHERE batch_id={$batch_id} AND onhand_qty-{$decNum}>=0";
        $this->db->query($sql);
        //生成预出库记录
        $order_id = $data['order_id'];
        $order_product_id = $data['order_product_id'];
        $product_id = $batch['product_id'];
        $batch_id = $batch['batch_id'];
        $onhand_qty = $batch['onhand_qty'];
        $warehouse = $batch['warehouse'];
        $seller_id = $data['seller_id'];
        $buyer_id = $data['customer_id'];
        $version_id = "'" . PROGRAM_CODE . "'";
        $sql = "insert into tb_sys_seller_delivery_pre_line
                (order_id,order_product_id,product_id,batch_id,qty,warehouse,seller_id,buyer_id,Memo,CreateUserName,CreateTime,ProgramCode,type)
                VALUES (
                $order_id,
                $order_product_id,
                $product_id,
                $batch_id,
                $decNum,
                '$warehouse',
                $seller_id,
                $buyer_id,
                '保证金原店铺预出库',
                $buyer_id,
                NOW(),
                $version_id,
                2
                )";
        $this->db->query($sql);
    }

    /**
     * 未支付订单退还库存时，子SKU应该退还的数量
     * @param $orderProduct
     * @param $setProductInfo
     * @param int $beforeOnShelfQuantity    原上架库存数量
     */
    public function childProductStockPreLineBack($orderProduct, $setProductInfo, $beforeOnShelfQuantity)
    {
        $afterOnShelfQuantity = $this->getProductOnSelfQuantity($setProductInfo['set_product_id']);
        $back_on_qty = $beforeOnShelfQuantity - $afterOnShelfQuantity;    //未支付订单，应该退还的上架库存数量
        $numChildWillOut = $setProductInfo['qty'] * $orderProduct['quantity'];//子产品，应该出库的上架库存数量
        if ($back_on_qty != 0) {
            $sql = "INSERT INTO tb_sys_seller_delivery_pre_line_back SET
order_id = " . $orderProduct['order_id'] . ",
order_product_id = " . $orderProduct['order_product_id'] . ",
product_id = " . $setProductInfo['set_product_id'] . ",
back_on_qty = {$back_on_qty},
CreateUserName = '" . $orderProduct['customer_id'] . "',
CreateTime = NOW( ),
ProgramCode = '" . PROGRAM_CODE . "'";
            $this->db->query($sql);
        }
    }

    public function comboProductStockPreLineBack($order, $quantity, $product_id)
    {
        $sql = "INSERT INTO tb_sys_seller_delivery_pre_line_back SET
                        order_id = " . $order['order_id'] . ",
                        order_product_id = " . $order['order_product_id'] . ",
                        product_id = " . $product_id . ",
                        back_on_qty = {$quantity},
                        CreateUserName = '" . $order['customer_id'] . "',
                        CreateTime = NOW( ),
                        ProgramCode = '" . PROGRAM_CODE . "'";
        $this->db->query($sql);
    }

    /**
     * 获取订单明细信息
     * @param int $order_id
     * @param int $product_id
     * @return array
     */
    public function getOrderInfo($order_id, $product_id)
    {
        $orderProduct = $this->db->query("SELECT oo.order_id,oo.customer_id,oop.order_product_id,oop.product_id,oop.quantity,ctp.customer_id as seller_id FROM " . DB_PREFIX . "order oo
                        LEFT JOIN " . DB_PREFIX . "order_product  oop on oo.order_id=oop.order_id
                        LEFT JOIN " . DB_PREFIX . "customerpartner_to_product ctp on ctp.product_id=oop.product_id
                        WHERE oo.order_id = " . $order_id . " and oop.product_id=" . $product_id)->row;
        return $orderProduct;
    }


    /**
     * 获取受影响的产品
     * @param $order_product_id
     * @return array
     */
    private function getAffectProduct($order_product_id)
    {
        $sql = "select * from tb_sys_seller_delivery_pre_line_back where order_product_id =" . $order_product_id;
        return $this->db->query($sql)->rows;
    }

    private function getComboInfo($product_id)
    {
        $sql = "select * from tb_sys_product_set_info where product_id =" . $product_id;
        return $this->db->query($sql)->rows;
    }

    /**
     * 生成订单时记录订单产品的基本信息
     * @param int $order_product_id
     * @param array $product
     * @author xxl
     */
    private function recordOrderProductInfo($order_product_id, $product)
    {

        //查询基础数据
        $sql = "SELECT
                  oop.order_id,
                  oop.order_product_id,
                  oop.quantity,
                  op.product_id,
                  op.sku,
                  op.length,
                  op.width,
                  op.height,
                  op.weight,
                  op.weight_kg,
                  op.length_cm,
                  op.width_cm,
                  op.height_cm,
                  op.combo_flag,
                  op.freight
                FROM
                  oc_order_product as oop
                LEFT JOIN
                  oc_product as op ON oop.product_id=op.product_id
                  WHERE oop.order_product_id =" . (int)$order_product_id;
        $query = $this->db->query($sql);
        $length_rate = $this->db->query("select * from oc_length_class where length_class_id = 3")->row['value'];
        if (isset($query->rows)) {
            foreach ($query->rows as $row) {
                $oversize_flag = $this->orm
                    ->table('oc_product_to_tag')
                    ->where(['product_id' => $row['product_id'], 'tag_id' => 1])
                    ->first();
                $base_array = array(
                    'order_id' => $row['order_id'],
                    'order_product_id' => $row['order_product_id'],
                    'product_id' => $row['product_id'],
                    'item_code' => $row['sku'],
                    'qty' => $row['quantity'],
                    'length_inch' => round($row['length'], 2),
                    'width_inch' => round($row['width'], 2),
                    'height_inch' => round($row['height'], 2),
                    'weight_lbs' => round($row['weight'], 2),
                    'length_cm' => round($row['length_cm'] == 0 ? $row['length'] / $length_rate : $row['length_cm'], 2),
                    'width_cm' => round($row['width_cm'] == 0 ? $row['width'] / $length_rate : $row['width_cm'], 2),
                    'height_cm' => round($row['height_cm'] == 0 ? $row['height'] / $length_rate : $row['height_cm'], 2),
                    'weight_kg' => round($row['weight_kg'], 2),
                    'freight' => $row['freight'],
                    'combo_flag' => $row['combo_flag'],
                    'ltl_flag' => $oversize_flag ? 1 : 0,
                    'create_user_name' => 'purchase_order',
                    'volume' => $product['volume'] ?? 0,
                    'volume_inch' => $product['volume_inch'] ?? 0,
                );
                if (isset($base_array)) {
                    //插入base表
                    $insert_base_sql = "INSERT INTO `oc_order_product_info` (
                                  `order_id`,
                                  `order_product_id`,
                                  `product_id`,
                                  `item_code`,
                                  `qty`,
                                  `length_inch`,
                                  `width_inch`,
                                  `height_inch`,
                                  `weight_lbs`,
                                  `length_cm`,
                                  `width_cm`,
                                  `height_cm`,
                                  `weight_kg`,
                                  `combo_flag`,
                                  `ltl_flag`,
                                  `volume`,
                                  `volume_inch`,
                                  `memo`,
                                  `create_time`,
                                  `create_user_name`,
                                  `update_time`,
                                  `update_user_name`,
                                  `program_code`,
                                  `freight`
                                )
                                VALUES
                                  (
                                    '" . $base_array['order_id'] . "',
                                    '" . $base_array['order_product_id'] . "',
                                    '" . $base_array['product_id'] . "',
                                    '" . $base_array['item_code'] . "',
                                    '" . $base_array['qty'] . "',
                                    '" . $base_array['length_inch'] . "',
                                    '" . $base_array['width_inch'] . "',
                                    '" . $base_array['height_inch'] . "',
                                    '" . $base_array['weight_lbs'] . "',
                                    '" . $base_array['length_cm'] . "',
                                    '" . $base_array['width_cm'] . "',
                                    '" . $base_array['height_cm'] . "',
                                    '" . $base_array['weight_kg'] . "',
                                    '" . $base_array['combo_flag'] . "',
                                    '" . $base_array['ltl_flag'] . "',
                                    '" . $base_array['volume'] . "',
                                    '" . $base_array['volume_inch'] . "',
                                    '',
                                    NOW(),
                                    '" . $base_array['create_user_name'] . "',
                                    NULL,
                                    NULL,
                                    'V1.0',
                                    '" . $base_array['freight'] . "'
                                  )";
                    $this->db->query($insert_base_sql);
                    $order_product_info_id = $this->db->getLastId();
                }

                //如果是combo品,记录set品信息
                if ($row['combo_flag'] == 1) {
                    $setSql = "SELECT
                              op.product_id,
                              op.sku,
                              op.length,
                              op.width,
                              op.height,
                              op.weight,
                              op.weight_kg,
                              op.length_cm,
                              op.width_cm,
                              op.height_cm,
                              op.freight,
                              tspsi.qty
                            FROM tb_sys_product_set_info as tspsi
                            LEFT JOIN oc_product as op ON op.product_id = tspsi.set_product_id
                            WHERE tspsi.product_id = " . $row['product_id'];
                    $setResults = $this->db->query($setSql)->rows;
                    if (count($setResults) > 0) {
                        foreach ($setResults as $setInfo) {
                            //获取子sku的体积
                            $productInfo = $this->freight->getFreightAndPackageFeeByProducts(array($setInfo['product_id']));
                            $productVolume = $productInfo[$setInfo['product_id']]['volume'];
                            $productVolumeInch = $productInfo[$setInfo['product_id']]['volume_inch'];
                            $insert_set_sql = "INSERT INTO `oc_order_product_set_info`
                                  (`order_product_info_id`,
                                  `set_product_id`,
                                  `item_code`,
                                  `qty`,
                                  `length_inch`,
                                  `width_inch`,
                                  `height_inch`,
                                  `weight_lbs`,
                                  `length_cm`,
                                  `width_cm`,
                                  `height_cm`,
                                  `weight_kg`,
                                  `volume`,
                                  `volume_inch`,
                                  `memo`,
                                  `create_time`,
                                  `create_user_name`,
                                  `update_time`,
                                  `update_user_name`,
                                  `program_code`,
                                  `freight`
                                )
                                VALUES
                                  (
                                    '" . $order_product_info_id . "',
                                    '" . $setInfo['product_id'] . "',
                                    '" . $setInfo['sku'] . "',
                                    '" . $setInfo['qty'] * $base_array['qty'] . "',
                                    '" . $setInfo['length'] . "',
                                    '" . $setInfo['width'] . "',
                                    '" . $setInfo['height'] . "',
                                    '" . $setInfo['weight'] . "',
                                    '" . round($setInfo['length_cm'] == 0 ? $setInfo['length'] / $length_rate : $setInfo['length_cm'], 2) . "',
                                    '" . round($setInfo['width_cm'] == 0 ? $setInfo['width'] / $length_rate : $setInfo['width_cm'], 2) . "',
                                    '" . round($setInfo['height_cm'] == 0 ? $setInfo['height'] / $length_rate : $setInfo['height_cm'], 2) . "',
                                    '" . $setInfo['weight_kg'] . "',
                                    '" . $productVolume . "',
                                    '" . $productVolumeInch . "',
                                    '',
                                    NOW(),
                                    '" . $base_array['create_user_name'] . "',
                                    NULL,
                                    NULL,
                                    'V1.0',
                                    '" . $setInfo['freight'] . "'
                                  )";
                            $this->db->query($insert_set_sql);
                        }
                    }
                }
            }
        }
    }

    /**
     * 云送仓的处理流程
     * @param int $order_id oc_order表的order_id
     * @throws Exception
     */
    private function cloudLogistics($order_id)
    {
        // 判断是否为批量云送仓的订单 ,如果是的情况需要批量更新所有的信息
        $exists = CloudWholesaleFulfillmentFileExplain::where('order_id',$order_id)->exists();
        if($exists){
            app(CloudWholesaleFulfillmentService::class)->updateCWFBatchInfo($order_id);
            return;
        }
        //1.生成tb_sys_customer_sales_order
        //2.生成tb_sys_customer_sales_order_line
        //3.生成绑定关系tb_sys_order_associate
        //4.oc_order_cloud_logistics,的order_id，sales_order_id填写
        $this->load->model('account/customer_order_import');
        $cloudLogisticInfo = $this->db->query("SELECT
            ocl.id,ocl.buyer_id,ocl.recipient,ocl.phone,ocl.email,ocl.address,ocl.country,ocl.city,ocl.state,ocl.zip_code,CASE WHEN ocl.service_type=0 THEN 'OTHER' ELSE 'FBA' END as shipment_method
            FROM oc_order oo
            LEFT JOIN oc_order_cloud_logistics ocl ON oo.cloud_logistics_id = ocl.id
            WHERE
            oo.order_id =" . $order_id
        )->row;
        $yzc_order_id_number = $this->sequence->getYzcOrderIdNumber();
        $tb_order_id = $this->sequence->getCloudLogisticsOrderIdNumber();
        $yzc_order_id_number++;
        $tb_order_id++;
        $order_id_tb = "CWF-" . $tb_order_id;
        $now = date("Y-m-d H:i:s", time());
        $run_id = time();
        $cloudLogistic = array(
            'order_id' => $order_id_tb,
            'order_date' => $now,
            'email' => app('db-aes')->decrypt($cloudLogisticInfo['email']),
            'ship_name' => app('db-aes')->decrypt($cloudLogisticInfo['recipient']),
            'shipped_date' => $now,
            'ship_address1' => app('db-aes')->decrypt($cloudLogisticInfo['address']),
            'ship_address2' => '',
            'ship_city' => app('db-aes')->decrypt($cloudLogisticInfo['city']),
            'ship_state' => $cloudLogisticInfo['state'],
            'ship_zip_code' => $cloudLogisticInfo['zip_code'],
            'ship_country' => $cloudLogisticInfo['country'],
            'ship_phone' => app('db-aes')->decrypt($cloudLogisticInfo['phone']),
            'ship_method' => $cloudLogisticInfo['shipment_method'],
            'ship_service_level' => '',
            'ship_company' => '',
            'bill_name' => app('db-aes')->decrypt($cloudLogisticInfo['recipient']),
            'bill_address' => app('db-aes')->decrypt($cloudLogisticInfo['address']),
            'bill_city' => app('db-aes')->decrypt($cloudLogisticInfo['city']),
            'bill_state' => $cloudLogisticInfo['state'],
            'bill_zip_code' => $cloudLogisticInfo['zip_code'],
            'bill_country' => $cloudLogisticInfo['country'],
            'orders_from' => '',
            'discount_amount' => '',
            'tax_amount' => '',
            'order_total' => '',
            'payment_method' => '',
            'buyer_id' => $cloudLogisticInfo['buyer_id'],
            'create_user_name' => $cloudLogisticInfo['buyer_id'],
            'create_time' => $now,
            'program_code' => PROGRAM_CODE,
            'customer_comments' => '',
            'run_id' => $run_id
        );
        // 订单头表数据
        //order_mode=4 云送仓的业务
        $customerSalesOrder = new Yzc\CustomerSalesOrder($cloudLogistic, CustomerSalesOrderMode::CLOUD_DELIVERY);
        $customerSalesOrder->yzc_order_id = "YC-" . $yzc_order_id_number;
        $customerSalesOrder->line_count = 1;
        $customerSalesOrder->run_id = $run_id;
        $this->sequence->updateYzcOrderIdNumber($yzc_order_id_number);
        $this->sequence->updateCloudLogisticsOrderIdNumber($tb_order_id);
        // 插入头表数据
        $customerSalesOrderArr[$order_id_tb] = $customerSalesOrder;
        $this->model_account_customer_order_import->saveCustomerSalesOrders($customerSalesOrderArr);
        //查询订单明细
        $cloudLogisticItems = $this->db->query("SELECT
            ocl.id,opd.name,cli.qty,cli.item_code,cli.seller_id,ocl.buyer_id,ctp.customer_id,cli.product_id
            FROM oc_order oo
            LEFT JOIN oc_order_cloud_logistics ocl ON oo.cloud_logistics_id = ocl.id
            LEFT JOIN oc_order_cloud_logistics_item cli ON cli.cloud_logistics_id = ocl.id
            LEFT JOIN oc_product op ON op.product_id = cli.product_id
            LEFT JOIN oc_customerpartner_to_product ctp ON ctp.product_id=op.product_id
            LEFT JOIN oc_product_description opd on opd.product_id=op.product_id
            WHERE
            oo.order_id ='" . $order_id . "'"
        )->rows;
        //查询header_id
        // 获取上步插入的头表数据
        $header_id = $this->model_account_customer_order_import->getCustomerSalesIdByOrderId($order_id_tb, $cloudLogisticInfo['buyer_id']);
        $customerSalesOrderLines = array();
        foreach ($cloudLogisticItems as $key => $cloudLogisticItem) {
            $orderLineTemp = array(
                'id' => $cloudLogisticItem['id'],
                'line_item_number' => $key + 1,
                'product_name' => $cloudLogisticItem['name'],
                'qty' => $cloudLogisticItem['qty'],
                'item_price' => null,
                'item_unit_discount' => null,
                'item_tax' => null,
                'item_code' => $cloudLogisticItem['item_code'],
                'alt_item_id' => null,
                'ship_amount' => null,
                'customer_comments' => null,
                'brand_id' => null,
                'seller_id' => $cloudLogisticItem['customer_id'],
                'run_id' => $run_id,
                'create_user_name' => $cloudLogisticItem['buyer_id'],
                'create_time' => $now
            );
            $customerSalesOrderLine = new Yzc\CustomerSalesOrderLine($orderLineTemp);
            // 插入明细表
            $customerSalesOrderLine->header_id = $header_id;
            $customerSalesOrderLine->product_id = $cloudLogisticItem['product_id'];
            $customerSalesOrderLines[] = $customerSalesOrderLine;
        }
        $this->model_account_customer_order_import->saveCustomerSalesOrderLine($customerSalesOrderLines);
        //更新 tb_sys_customer_sales_order_line的is_exported,exported_time,is_synchroed,synchroed_time,将明细设置成已同步
        $this->model_account_customer_order_import->updateCustomerSalesOrderLineIsExported($header_id);
        //采购订单与生成的销售订单绑定
        $orderAssociatedIds = $this->model_account_customer_order_import->associateOrderForCWF($order_id, $header_id);
        //更改订单的状态
        $this->model_account_customer_order_import->updateCustomerSalesOrder($header_id);
        //更新oc_order_cloud_logistics
        $this->model_account_customer_order_import->updateOrderCloudLogistics($order_id, $header_id, $cloudLogisticInfo['id']);

        //根据绑定的销售单与采购单的关系，绑定仓租
        app(StorageFeeService::class)->bindByOrderAssociated($orderAssociatedIds);
    }

    //获取订单基础信息
    public function orderBaseInfo($orderId)
    {
        $buyer_id = $this->customer->getId();
        $info = $this->orm->table(DB_PREFIX . 'order')
            ->where([['order_id', $orderId], ['customer_id', $buyer_id]])
            ->select('customer_id', 'payment_method', 'payment_code', 'total', 'order_status_id', 'date_added')
            ->lockForUpdate()
            ->first();
        return obj2array($info);
    }

    public function getCreditTotal($orderId)
    {
        //信用额度扣减(获取订单total)
        $total = $this->orm->table('oc_order_total')
            ->where([
                'order_id' => $orderId,
                'code' => 'total'
            ])
            ->value('value');

        $balance = $this->orm->table('oc_order_total')
            ->where([
                'order_id' => $orderId,
                'code' => 'balance'
            ])
            ->value('value');

        $creditChange = $total - $balance;
        return $creditChange;
    }

    public function getPaymentInfo($orderId, $orderType)
    {
        $paymentInfos = $this->orm->table('tb_payment_info as tpi')
            ->leftJoin('tb_payment_info_detail as pid', 'pid.header_id', '=', 'tpi.id')
            ->where([
                'pid.order_id' => $orderId,
                'pid.order_type' => $orderType
            ])
            ->select('tpi.id','tpi.pay_id', 'tpi.user_id', 'tpi.order_id','tpi.status', 'tpi.pay_method')
            ->distinct('tpi.pay_id')
            ->get();
        return $paymentInfos;
    }

    public function getOrderLineInfo($orderId)
    {
        $info = $this->orm->table(DB_PREFIX . 'order_product as oop')
            ->where('oop.order_id', $orderId)
            ->select('customer_id', 'payment_method', 'payment_code', 'total', 'order_status_id', 'date_added')
            ->lockForUpdate()
            ->first();
        return obj2array($info);
    }

    /**
     * 支付回调后修改采购订单信息
     * @param int $order_id
     * @param string $payment_method
     * @param string $umf_order_id
     * @throws Exception
     * @author xxl
     */
    public function modifiedOrderInfo($order_id, $payment_method, $umf_order_id)
    {
        //修改采购订单信息,
        //oc_order_total.balance,
        //oc_order_total.poundage,
        //oc_order_total.total,
        //oc_order.total,
        //oc_order.payment_code
        //oc_order.payment_method
        //oc_order.comment
        $paymentInfo = $this->orm->table('tb_payment_info as tpi')
            ->leftJoin('oc_customer as oc', 'tpi.user_id', '=', 'oc.customer_id')
            ->where('tpi.order_id', '=', $umf_order_id)
            ->select('tpi.total_yzc', 'tpi.comment', 'oc.country_id')
            ->first();
        $paymentInfo = obj2array($paymentInfo);
        if (!empty($paymentInfo)) {
            //根据支付的信息修改订单信息
            $balance = $this->orm->table('oc_order_total')
                ->where([
                    'order_id' => $order_id,
                    'code' => 'balance'
                ])
                ->value('value');
            $poundage = $this->orm->table('oc_order_total')
                ->where([
                    'order_id' => $order_id,
                    'code' => 'poundage'
                ])
                ->value('value');
            $total = $this->orm->table('oc_order_total')
                ->where([
                    'order_id' => $order_id,
                    'code' => 'total'
                ])
                ->value('value');
            $balance = isset($balance) ? $balance : 0;
            $poundage = isset($poundage) ? $poundage : 0;
            $total = isset($total) ? $total : 0;
            $total = $total - $balance - $poundage;
            $total_yzc = (isset($paymentInfo['total_yzc']) ? $paymentInfo['total_yzc'] : 0);
            $country_id = isset($paymentInfo['country_id']) ? $paymentInfo['country_id'] : 223;
            switch ($payment_method) {
                case "umf_pay":
                    $total_yzc = $country_id == 107 ? round($total_yzc / 1.0051, 0) : round($total_yzc / 1.0051, 2);
                    break;
                case "wechat_pay":
                    $total_yzc = $country_id == 107 ? round($total_yzc / 1.0081, 0) : round($total_yzc / 1.0081, 2);
                    break;
            }
            $balance = $total - $total_yzc;
            $data = array();
            $data['order_id'] = $order_id;
            $data['payment_code'] = $payment_method;
            $data['balance'] = $balance;
            $data['comment'] = isset($paymentInfo['comment']) ? $paymentInfo['comment'] : '';
            $this->load->model('checkout/pay');
            $this->model_checkout_pay->deleteOrderPaymentInfo($order_id);
            $this->model_checkout_pay->modifiedOrder($data);
        } else {
            //没有查到对应的付款记录
            $msg = '采购订单号:' . $order_id . ',商户流水号:' . $umf_order_id . ',没有找到对应的支付记录.';
            throw new Exception($msg);
        }

    }

    public function getOrderAssociatedRecord($order_id, $buyer_id)
    {
        $run_id = $this->orm->table('tb_sys_order_associated_pre')
            ->where('order_id', '=', $order_id)
            ->value('run_id');
        $associateRecords = $this->orm->table('tb_sys_order_associated_pre as ap')
            ->leftJoin('tb_sys_customer_sales_order as cso','cso.id','=','ap.sales_order_id')
            ->leftJoin('oc_order as oo','oo.order_id','=','ap.order_id')
            ->where([['ap.run_id','=',$run_id],['cso.order_status','=',CustomerSalesOrderStatus::TO_BE_PAID],['cso.buyer_id','=',$buyer_id],['oo.order_status_id','=',OcOrderStatus::COMPLETED]])
            ->select('ap.sales_order_id','ap.sales_order_line_id','ap.order_id','ap.order_product_id',
                'ap.qty','ap.product_id','ap.seller_id','ap.buyer_id','ap.CreateUserName','ap.ProgramCode','ap.id as pre_id')
            //->groupBy(['ap.sales_order_line_id','ap.order_product_id']) //轩哥说这个有问题，而且确实有问题，会导致pre表写入associated写入不全，即出现status=0的情况，故注释掉
            ->lockForUpdate()
            ->get();

        return obj2array($associateRecords);
    }

    public function checkAssociatedOrderByRecord($associatedArr, $orderId)
    {
        //校验采购订单库存
        $needCheckStockArr = [];
        foreach ($associatedArr as $associated) {
            if ($associated['order_id'] != $orderId) {
                $purchaseOrderProductId = $associated['order_product_id'];
                $qty = $associated['qty'];
                $needCheckStockArr[$purchaseOrderProductId]['order_product_id'] = $associated['order_product_id'];
                $needCheckStockArr[$purchaseOrderProductId]['qty'] = isset($needCheckStockArr[$purchaseOrderProductId]['qty']) ? $needCheckStockArr[$purchaseOrderProductId]['qty'] + $qty : $qty;
            }
        }
        $canAssociatedArr = [];
        if (!empty($needCheckStockArr)) {
            $needCheckOrderProductId = array_keys($needCheckStockArr);
            $purchaseQtyArr = $this->getPurchaseQty($needCheckOrderProductId);
            $associatedQtyArr = $this->getAssocaitedQty($needCheckOrderProductId);
            $rmaQtyArr = $this->getRMAQty($needCheckOrderProductId);
            $canAssociatedOrderProductId = [];
            foreach ($needCheckStockArr as $orderProductId => $needCheckStock) {
                $buyQty = isset($purchaseQtyArr[$orderProductId]['quantity']) ? $purchaseQtyArr[$orderProductId]['quantity'] : 0;
                $associatedQty = isset($associatedQtyArr[$orderProductId]['quantity']) ? $associatedQtyArr[$orderProductId]['quantity'] : 0;
                $rmaQty = isset($rmaQtyArr[$orderProductId]['quantity']) ? $rmaQtyArr[$orderProductId]['quantity'] : 0;
                $needQty = $needCheckStock['qty'];
                $leftQty = $buyQty - $associatedQty - $rmaQty;
                if ($leftQty >= $needQty) {
                    $canAssociatedOrderProductId[] = $orderProductId;
                }
            }
            $canNotAssociatedArr = [];
            foreach ($associatedArr as $associated) {
                $orderProductId = $associated['order_product_id'];
                if (!in_array($orderProductId, $canAssociatedOrderProductId) && $associated['order_id'] != $orderId) {
                    $canNotAssociatedArr[] = $associated['sales_order_id'];
                }
            }
            foreach ($associatedArr as $associated) {
                $salesOrderId = $associated['sales_order_id'];
                if (!in_array($salesOrderId, $canNotAssociatedArr)) {
                    $canAssociatedArr[] = $associated;
                }
            }
        } else {
            $canAssociatedArr = $associatedArr;
        }
        return $canAssociatedArr;
    }

    public function updateAssociatedRecord(array $associatedRecordId)
    {
        $this->orm->table('tb_sys_order_associated_pre')
            ->whereIn('id', $associatedRecordId)
            ->update(['status' => 1]);
    }

    public function updatePayment($payId)
    {
        $this->orm->table("tb_payment_info")
            ->where('pay_id', '=', $payId)
            ->update(
                ['status' => 201]
            );
    }

    /**
     * 自动购买comboinfo更新，其他勿用
     * @param $saleOrderId
     * @throws Exception
     */
    public function updateComboInfo($saleOrderId)
    {
        $salesOrderLines = CustomerSalesOrderLine::query()->where('header_id', $saleOrderId)->get();

        /** @var ModelAccountCustomerOrderImport $modelCustomerOrderImport */
        $modelCustomerOrderImport = load()->model('account/customer_order_import');
        foreach ($salesOrderLines as $salesOrderLine) {
            /** @var CustomerSalesOrderLine $salesOrderLine */
            // 自动购买如果销售订单全部走囤货，已执行了更新操作，防止因补运费或者费用单走此逻辑，重复更新，即过滤
            if (!empty($salesOrderLine->combo_info)) {
                continue;
            }
            // 更新订单明细信息
            $modelCustomerOrderImport->updateCustomerSalesOrderLine($salesOrderLine->header_id, $salesOrderLine->id);
        }
    }

    /**
     * 校验欧洲补运费
     *
     * @param $lineIdArr
     * @param $order_id
     * @return array
     */
    public function checkOrderCanAssociated($lineIdArr, $order_id)
    {
        $run_id = $this->orm->table('tb_sys_order_associated_pre')
            ->where('order_id', '=', $order_id)
            ->value('run_id');
        $sellerProductId = json_decode($this->config->get('europe_freight_product_id'), true);
        $lineInfos = $this->orm->table('tb_sys_order_associated_pre as oap')
            ->leftJoin('tb_sys_customer_sales_order as cso', 'cso.id', '=', 'oap.sales_order_id')
            ->leftJoin('tb_sys_customer_sales_order_line as csol', 'csol.id', '=', 'oap.sales_order_line_id')
            ->leftJoin('oc_customer as oc', 'oc.customer_id', '=', 'cso.buyer_id')
            ->leftJoin('oc_country as cou', 'cou.country_id', '=', 'oc.country_id')
            ->selectRaw('distinct(sales_order_line_id) as lineId,oap.qty,cso.ship_country,cso.ship_zip_code,cou.iso_code_3 as country_name,oap.product_id,oap.id,oap.sales_order_id,oap.order_product_id')
            ->whereIn('oap.sales_order_line_id', $lineIdArr)
            ->where('oap.run_id', '=', $run_id)
            ->get()
            ->toArray();
        $freightNeedArr = [];
        $freightBuyArr = [];
        foreach ($lineInfos as $lineInfo) {
            $lineId = $lineInfo->lineId;
            $productId = $lineInfo->product_id;
            if (!in_array($productId, $sellerProductId)) {
                $europeFreight = [];
                $europeFreight[$lineId]['order_product_id'] = $lineInfo->order_product_id;
                $europeFreight[$lineId]['product_id'] = $productId;
                $europeFreight[$lineId]['from'] = $lineInfo->country_name;
                $europeFreight[$lineId]['to'] = $lineInfo->ship_country;
                $europeFreight[$lineId]['zip_code'] = $lineInfo->ship_zip_code;
                $europeFreight[$lineId]['qty'] = $lineInfo->qty;
                $europeFreight[$lineId]['line_id'] = $lineId;
                $freightQty = $this->checkFreightResult($europeFreight, $lineId);
                $freightNeedArr[$lineId] = isset($freightNeedArr[$lineId]) ? $freightNeedArr[$lineId] + $freightQty : $freightQty;
            } else {
                //实际采购的库存
                $buyerQty = $lineInfo->qty;
                $freightBuyArr[$lineId] = isset($freightBuyArr[$lineId]) ? $freightBuyArr[$lineId] + $buyerQty : $buyerQty;
            }
        }
        $canAssociateArr = [];
        foreach ($freightNeedArr as $lineId => $needQty) {
            $buyerQty = isset($freightBuyArr[$lineId]) ? $freightBuyArr[$lineId] : 0;
            if ($needQty == $buyerQty) {
                $canAssociateArr[] = $lineId;
            }
        }
        $canNotAssociateArr = [];
        foreach ($lineInfos as $lineInfo) {
            $lineId = $lineInfo->lineId;
            if (!in_array($lineId, $canAssociateArr)) {
                $canNotAssociateArr[] = $lineInfo->sales_order_id;
            }
        }
        return $canNotAssociateArr;
    }

    public function checkFreightResult($europeFreight, $lineId)
    {
        $this->load->model('extension/module/europe_freight');
        /** @var ModelExtensionModuleEuropeFreight $europeFreight */
        $europe_freight_model = $this->model_extension_module_europe_freight;
        $freightQty = 0;
        $freightInfo = $europe_freight_model->getFreight($europeFreight);
        switch ($freightInfo[0]['code']) {
            case 200:
                $freight = ceil($freightInfo[0]['freight']) * $europeFreight[$lineId]['qty'];
                $freightQty = (int)$freight / 1;
                break;
            default:

        }
        return $freightQty;
    }

    public function getPurchaseQty($orderProductIdArr)
    {
        return $this->orm->table('oc_order_product as oop')
            ->whereIn('oop.order_product_id', $orderProductIdArr)
            ->select('oop.order_product_id', 'oop.quantity')
            ->get()
            ->keyBy('order_product_id')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
    }

    public function getAssocaitedQty($orderProductIdArr)
    {
        return $this->orm->table('tb_sys_order_associated as soa')
            ->whereIn('soa.order_product_id', $orderProductIdArr)
            ->groupBy('soa.order_product_id')
            ->selectRaw('soa.order_product_id,sum(soa.qty) as quantity')
            ->get()
            ->keyBy('order_product_id')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
    }

    public function getRMAQty($orderProductIdArr)
    {
        $rmaQty = $this->orm->table('oc_yzc_rma_order_product as rp')
            ->leftJoin('oc_yzc_rma_order as r', 'r.id', '=', 'rp.rma_id')
            ->whereIn('rp.order_product_id', $orderProductIdArr)
            ->where([
                ['r.cancel_rma', '=', 0],
                ['r.order_type', '=', 2],
                ['rp.status_refund', '<>', 2]

            ])
            ->groupBy('rp.order_product_id')
            ->selectRaw('rp.order_product_id,sum(rp.quantity) as quantity')
            ->get()
            ->keyBy('order_product_id')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        return $rmaQty;
    }

    public function getOrderQuoteInfo($orderId)
    {
        $quoteIdArr = $this->orm->table('oc_order_quote')
            ->where('order_id', '=', $orderId)
            ->selectRaw('group_concat(quote_id) as quote_id')
            ->groupBy('order_id')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        if (empty($quoteIdArr)) {
            return [];
        }
        $quoteIdArr = explode(',', $quoteIdArr[0]['quote_id']);
        $quoteInfos = $this->orm->table('oc_product_quote')
            ->whereIn('id', $quoteIdArr)
            ->select('product_id', 'price', 'quantity')
            ->get()
            ->keyBy('product_id')
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        return $quoteInfos;
    }

    public function associatedOrderByRecord($associatedArr, $orderId)
    {
        //校验采购订单库存
        $needCheckStockArr = [];
        foreach ($associatedArr as $associated) {
            if ($associated['order_id'] != $orderId) {
                $purchaseOrderProductId = $associated['order_product_id'];
                $qty = $associated['qty'];
                $needCheckStockArr[$purchaseOrderProductId]['order_product_id'] = $associated['order_product_id'];
                $needCheckStockArr[$purchaseOrderProductId]['qty'] = isset($needCheckStockArr[$purchaseOrderProductId]['qty']) ? $needCheckStockArr[$purchaseOrderProductId]['qty'] + $qty : $qty;
            }
        }
        $canAssociatedArr = [];
        if (!empty($needCheckStockArr)) {
            $needCheckOrderProductId = array_keys($needCheckStockArr);
            $purchaseQtyArr = $this->getPurchaseQty($needCheckOrderProductId);
            $associatedQtyArr = $this->getAssocaitedQty($needCheckOrderProductId);
            $rmaQtyArr = $this->getRMAQty($needCheckOrderProductId);
            $canAssociatedOrderProductId = [];
            foreach ($needCheckStockArr as $orderProductId => $needCheckStock) {
                $buyQty = isset($purchaseQtyArr[$orderProductId]['quantity']) ? $purchaseQtyArr[$orderProductId]['quantity'] : 0;
                $associatedQty = isset($associatedQtyArr[$orderProductId]['quantity']) ? $associatedQtyArr[$orderProductId]['quantity'] : 0;
                $rmaQty = isset($rmaQtyArr[$orderProductId]['quantity']) ? $rmaQtyArr[$orderProductId]['quantity'] : 0;
                $needQty = $needCheckStock['qty'];
                $leftQty = $buyQty - $associatedQty - $rmaQty;
                if ($leftQty >= $needQty) {
                    $canAssociatedOrderProductId[] = $orderProductId;
                }
            }
            $canNotAssociatedArr = [];
            foreach ($associatedArr as $associated) {
                $orderProductId = $associated['order_product_id'];
                if (!in_array($orderProductId, $canAssociatedOrderProductId) && $associated['order_id'] != $orderId) {
                    $canNotAssociatedArr[] = $associated['sales_order_id'];
                }
            }
            foreach ($associatedArr as $associated) {
                $salesOrderId = $associated['sales_order_id'];
                if (!in_array($salesOrderId, $canNotAssociatedArr)) {
                    $canAssociatedArr[] = $associated;
                }
            }
        } else {
            $canAssociatedArr = $associatedArr;
        }
        return $canAssociatedArr;
    }

    /**
     * @Author xxl
     * @Description 插入payment_info表
     * @Date 15:49 2020/10/14
     * @Param array $paymentInfoData
     * @return int 主键
     **/
    public function savePaymentInfo($paymentInfoData)
    {
        return $this->orm->table("tb_payment_info")
            ->insertGetId($paymentInfoData);
    }

    /**
     * @Author xxl
     * @Description 插入payment_info_detail表
     * @Date 17:19 2020/10/14
     * @Param array $paymentInfoDetailData
     **/
    public function savePaymentInfoDetail($paymentInfoDetailData)
    {
        $this->orm->table("tb_payment_info_detail")
            ->insert($paymentInfoDetailData);
    }

    /**
     * @Author xxl
     * @Description 单条插入关联关系，返回主键
     * @Date 10:18 2020/10/15
     * @Param array $canAssociated
     * @return int id
     **/
    public function saveAssociatedOrder($canAssociated)
    {
        return $this->orm->table('tb_sys_order_associated')
            ->insertGetId($canAssociated);
    }

    public function feeToPayCheckAndUpdateOrderStatus($salesOrderId)
    {
        //保障服务-所有匹配完库存的非自动购买上门取货订单，匹配完库存后订单状态都变为Pending Charges
//        $needPayFlag = app(StorageFeeRepository::class)->checkBoundSalesOrderNeedPay($salesOrderId);
//        if ($needPayFlag) {
            $this->orm->table('tb_sys_customer_sales_order')
                ->where('id', '=', $salesOrderId)
                ->update([
                    'order_status' => CustomerSalesOrderStatus::PENDING_CHARGES
                ]);
            return $salesOrderId;
//        }
//        return null;
    }

    /**
     * @Author xxl
     * @Description 根据费用单绑定销售订单和采购订单
     * @Date 14:06 2020/10/15
     * @param $feeOrderArr
     * @throws Exception
     */
    public function associatedOrderByFeeOrder($feeOrderArr)
    {
        $feeOrderInfo = app(FeeOrderRepository::class)->findFeeOrderInfo($feeOrderArr);
        if (count($feeOrderInfo) > 0) {
            $runId = $feeOrderInfo[0]['purchase_run_id'];
            $buyerId = $feeOrderInfo[0]['buyer_id'];
            if (!$runId) {
                throw new Exception('Order Error!');
            }
            $associateRecords = $this->orm->table('tb_sys_order_associated_pre as ap')
                ->leftJoin('tb_sys_customer_sales_order as cso', 'cso.id', '=', 'ap.sales_order_id')
                ->leftJoin('oc_order as oo', 'oo.order_id', '=', 'ap.order_id')
                ->where([['ap.run_id', '=', $runId], ['cso.order_status', '=', CustomerSalesOrderStatus::TO_BE_PAID], ['cso.buyer_id', '=', $buyerId], ['oo.order_status_id', '=', OcOrderStatus::COMPLETED]])
                ->select('ap.sales_order_id', 'ap.sales_order_line_id', 'ap.order_id', 'ap.order_product_id',
                    'ap.qty', 'ap.product_id', 'ap.seller_id', 'ap.buyer_id', 'ap.CreateUserName', 'ap.ProgramCode', 'ap.id as pre_id')
                ->lockForUpdate()
                ->get();
            $canAssociatedArr = $this->associatedOrderByRecord(obj2array($associateRecords), null);
            $canNotAssociateArr = [];
            if(!(customer()->isCollectionFromDomicile())){
                $canNotAssociateArr = $this->checkOrderCanAssociated(array_column($canAssociatedArr,'sales_order_line_id'),null);
            }
            $this->associatedSalesOrderAndUpdateStatus($canAssociatedArr,$canNotAssociateArr);

            // 释放销售订单囤货预绑定
            $canAssociatedIds = array_filter(array_unique(array_column($canAssociatedArr, 'sales_order_id')));
            app(BuyerStockService::class)->releaseInventoryLockBySalesOrderPreAssociated($canAssociatedIds, (int)$buyerId);
        }
    }

    /**
     * @Author xxl
     * @Description 绑定销售订单更新销售订单状态
     * @date 17:43 2020/10/15
     * @param array $canAssociatedArr
     * @throws Exception
     */
    public function associatedSalesOrderAndUpdateStatus($canAssociatedArr,$canNotAssociateArr)
    {
        $this->load->model('account/customer_order_import');
        $associatedRecordId = [];
        $sales_order_id_arr = [];
        //销售单与采购单的关系表主键
        $orderAssociatedIds = [];
        //查询销售订单明细数量
        $salesOrderLineNeedQty = $this->getSalesOrderLineNeedAssociatedQty($canAssociatedArr);
        $salesOrderLineUseQty = [];
        $needChangeLineArr = [];
        $sellerProductId = json_decode($this->config->get('europe_freight_product_id'),true);
        foreach ($canAssociatedArr as $associatedRecord) {
            $sales_order_id = $associatedRecord['sales_order_id'];
            if (!in_array($sales_order_id,$canNotAssociateArr)) {
                $salesOrderLineId = $associatedRecord['sales_order_line_id'];
                $salesOrderLineUseQty[$salesOrderLineId] = isset($salesOrderLineUseQty[$salesOrderLineId])? $salesOrderLineUseQty[$salesOrderLineId]+$associatedRecord['qty']:$associatedRecord['qty'];
                if($salesOrderLineUseQty[$salesOrderLineId]<= $salesOrderLineNeedQty[$salesOrderLineId]) {
                    $discountsAmount = app(OrderService::class)->orderProductWillAssociateDiscountsAmount(intval($associatedRecord['order_product_id']), intval($associatedRecord['qty']), $this->customer->isJapan() ? 0 : 2);
                    $associatedRecord = array_merge($associatedRecord, $discountsAmount);
                    $orderAssociatedIds[] = $this->saveAssociatedOrder($associatedRecord);
                }
                $sales_order_line_id = $associatedRecord['sales_order_line_id'];
                $associatedProductId = $associatedRecord['product_id'];
                array_push($sales_order_id_arr, $sales_order_id);
                if(!in_array($associatedProductId,$sellerProductId)) {
                    $needChangeLineArr[] = [
                        'sales_order_id' => $sales_order_id,
                        'sales_order_line_id' => $sales_order_line_id
                    ];
                }
                $associatedRecordId[] = $associatedRecord['pre_id'];
            }
        }
        $needChangeLineArr = $this->unique_arr($needChangeLineArr);
        foreach ($needChangeLineArr as $needChangeLine){
            $salesOrderId = $needChangeLine['sales_order_id'];
            $salesOrderLineId = $needChangeLine['sales_order_line_id'];
            $this->model_account_customer_order_import->updateCustomerSalesOrderLine($salesOrderId, $salesOrderLineId);
        }
        // 更新订单状态
        $sales_order_id_arr = array_unique($sales_order_id_arr);
        foreach ($sales_order_id_arr as $sales_order_id) {
            $this->model_account_customer_order_import->updateCustomerSalesOrder($sales_order_id);
        }
        //更新预绑定的状态
        $this->updateAssociatedRecord($associatedRecordId);
        //根据绑定的销售单与采购单的关系，绑定仓租
        app(StorageFeeService::class)->bindByOrderAssociated($orderAssociatedIds);
    }

    public function getAssociateIdBySalesOrderId($salesOrderId)
    {
        $result = $this->orm->table('tb_sys_order_associated')
            ->where('sales_order_id', '=', $salesOrderId)
            ->select('id')
            ->get();
        return $result->pluck('id')->toArray();

    }

    /**
     * @Author xxl
     * @Description 获取第三方支付明细
     * @Date 17:03 2020/10/21
     * @param $paymentInfoId
     * @return array
     */
    public function getPaymentInfoDetails($paymentInfoId)
    {
        $data = [];
        $paymentInfoDatails = $this->orm->table('tb_payment_info as pi')
            ->leftJoin('tb_payment_info_detail as pid', 'pid.header_id', '=', 'pi.id')
            ->select('pi.pay_method', 'pid.order_type', 'pid.order_id', 'pi.user_id','pi.id')
            ->where('pi.id', '=', $paymentInfoId)
            ->get()
            ->toArray();
        $feeOrderIdArr = [];
        foreach ($paymentInfoDatails as $paymentInfoDatails) {
            $data['payment_method'] = $paymentInfoDatails->pay_method;
            $data['payment_id'] = $paymentInfoDatails->id;
            $data['customer_id'] = $paymentInfoDatails->user_id;
            if ($paymentInfoDatails->order_type == 1) {
                $data['order_id'] = $paymentInfoDatails->order_id;
            }
            if ($paymentInfoDatails->order_type == 2) {
                array_push($feeOrderIdArr, $paymentInfoDatails->order_id);
            }
        }
        $data['fee_order_arr'] = $feeOrderIdArr;
        return $data;
    }

    /**
     * @Author xxl
     * @Description 费用单更新销售订单状态
     * @Date 9:48 2020/10/28
     * @param $salesOrderIdArr
     */
    public function updateSalesOrderStatusByFeeOrder($salesOrderIdArr)
    {
        $this->load->model('account/customer_order_import');
        foreach ($salesOrderIdArr as $salesOrderId) {
            $this->model_account_customer_order_import->updateCustomerSalesOrder($salesOrderId);
        }
    }

    /**
     * 修改费用单支付方式
     *
     * @param $feeOrderIdArr
     * @param $paymentCode
     * @param $paymentMethod
     */
    public function updateFeeOrderPayment($feeOrderIdArr, $paymentCode, $paymentMethod)
    {
        $feeOrderData = [
            'payment_method' => $paymentMethod,
            'payment_code' => $paymentCode,
        ];
        if ($paymentCode === PayCode::PAY_LINE_OF_CREDIT) {
            $feeOrderData['balance'] = new Expression('fee_total');
        }
        app(FeeOrderService::class)->batchUpdateFeeOrderInfo($feeOrderIdArr, $feeOrderData);
    }

    public function getOrderProductsExcludeFreightProductId($order_id)
    {
        $sellerProductId = json_decode($this->config->get('europe_freight_product_id'),true);
        $orderProductInfo = $this->orm->table("oc_order_product as oop")
            ->leftJoin('oc_order_quote as ooq',[['ooq.order_id','=','oop.order_id'],['ooq.product_id','=','oop.product_id']])
            ->leftJoin('oc_product_quote as opq',[['ooq.quote_id','=','opq.id'],['opq.product_id','=','oop.product_id']])
            ->selectRaw('if(opq.price is null,oop.price+oop.service_fee_per,opq.price) as price,oop.freight_per,oop.package_fee,oop.product_id,oop.quantity,oop.name')
            ->where('oop.order_id','=',$order_id)
            ->whereNotIn('oop.product_id',$sellerProductId)
            ->get();
        return obj2array($orderProductInfo);
    }

    /**
     * @Author xxl
     * @Description 添加第三方支付流水
     * @Date 13:34 2020/10/23
     * @param $paymentData
     * @return void
     */
    public function thirdPaymentRecord($paymentData)
    {
        $paymentId = $paymentData['payment_id'];
        $amountYzc = $this->orm->table('tb_payment_info')
            ->where('id','=',$paymentId)
            ->value('total_yzc');
        $createTime = date("Y-m-d H:i:s", time());
        $paymentInData = [
            'payment_id' => $paymentId,
            'payment_method' => $paymentData['payment_method'],
            'customer_id' => $paymentData['customer_id'],
            'amount_yzc' => $amountYzc,
            'record_type' => 1,
            'create_time' => $createTime,
            'create_person' => $paymentData['customer_id']
        ];
        $paymentOutData = [
            'payment_id' => $paymentId,
            'payment_method' => $paymentData['payment_method'],
            'customer_id' => $paymentData['customer_id'],
            'amount_yzc' => $amountYzc,
            'record_type' => 2,
            'create_time' => $createTime,
            'create_person' => $paymentData['customer_id']
        ];
        $this->orm->table('tb_third_payment_record')
            ->insert($paymentInData);
        $this->orm->table('tb_third_payment_record')
            ->insert($paymentOutData);
    }

    public function getSalesOrderLineNeedAssociatedQty($canAssociatedArr)
    {
        $lineIdArr = array_column($canAssociatedArr,'sales_order_line_id');
        return $this->orm->table('tb_sys_customer_sales_order_line')
            ->whereIn('id',$lineIdArr)
            ->select('id','qty')
            ->get()
            ->keyBy('id')
            ->map(function ($v){
                return (array)$v;
            })
            ->toArray();
    }

    //二维数组去掉重复值

    function unique_arr($array2D,$stkeep=false,$ndformat=true){

        $joinstr='+++++';

        // 判断是否保留一级数组键 (一级数组键可以为非数字)

        if($stkeep) $stArr = array_keys($array2D);

        // 判断是否保留二级数组键 (所有二级数组键必须相同)

        if($ndformat) $ndArr = array_keys(end($array2D));

        //降维,也可以用implode,将一维数组转换为用逗号连接的字符串

        foreach ($array2D as $v){

            $v = join($joinstr,$v);

            $temp[] = $v;

        }

        //去掉重复的字符串,也就是重复的一维数组

        $temp = array_unique($temp);

        //再将拆开的数组重新组装

        foreach ($temp as $k => $v){

            if($stkeep) $k = $stArr[$k];

            if($ndformat){

                $tempArr = explode($joinstr,$v);

                foreach($tempArr as $ndkey => $ndval) $output[$k][$ndArr[$ndkey]] = $ndval;

            }

            else $output[$k] = explode($joinstr,$v);

        }

        return $output;

    }

    /**
     * @author xxl
     * @description 支付回调后更新订单信息
     * @date 1:08 2020/12/22
     * @param int $orderId 采购订单ID
     * @param $feeOrderIdArr
     * @param $paymentMethod
     * @param $paymentInfoId
     * @throws Exception
     */
    private function callBackUpdateOrder($orderId, $feeOrderIdArr, $paymentMethod, $paymentInfoId)
    {
        $this->load->model('checkout/pay');
        $purchaseOrderTotal = 0;
        $feeOrderTotal = 0;
        $feeOrderRepo = app(FeeOrderRepository::class);
        $this->model_checkout_pay->deleteOrderPaymentInfo($orderId);
        $this->model_checkout_pay->deleteFeeOrderPaymentInfo($feeOrderIdArr);
        if (!empty($orderId)) {
            $purchaseOrderTotal = $this->model_checkout_pay->getOrderTotal($orderId);
        }
        if (!empty($feeOrderIdArr)) {
            $feeOrderTotal = $feeOrderRepo->findFeeOrderTotal($feeOrderIdArr);
        }
        $orderTotal = $purchaseOrderTotal + $feeOrderTotal;
        $paymentInfo = $this->orm->table('tb_payment_info')
            ->where('id', '=', $paymentInfoId)
            ->select('balance', 'comment')
            ->first();
        $balance = isset($paymentInfo->balance) ? $paymentInfo->balance : 0;
        $comment = isset($paymentInfo->comment) ? $paymentInfo->comment : '';
        $paymentMethodTotal = (float)$orderTotal - (float)$balance;
        $totalPoundage = 0;
        if ($purchaseOrderTotal > 0) {
            // 有销售单时取销售单的时间
            PayCode::setPoundageCalculateTime(Order::find($orderId)->date_added);
        } elseif ($feeOrderTotal > 0 && count($feeOrderIdArr) > 0) {
            // 纯费用单支付取费用单的时间
            PayCode::setPoundageCalculateTime(FeeOrder::find($feeOrderIdArr[0])->created_at);
        }
        $poundagePercent = PayCode::getPoundage($paymentMethod);
        if ($poundagePercent > 0) {
            $totalPoundage = $this->customer->getCountryId()== 107 ? round($paymentMethodTotal*$poundagePercent,0):round($paymentMethodTotal*$poundagePercent,2);
        }

        //修改支付的订单信息
        $payData = [
            'order_id' => $orderId,
            'fee_order_id' => $feeOrderIdArr,
            'balance' => $balance,
            'payment_code' => $paymentMethod,
            'totalPoundage' => $totalPoundage,
            'comment' => $comment
        ];

        //修改商品订单信息，更新信用额度使用,已经手续费
        if (!empty($orderId)) {
            $payData = $this->modifiedOrder($payData,$purchaseOrderTotal);
        }
        //费用订单信息，更新信用额度使用,已经手续费
        if (!empty($feeOrderIdArr)) {
            $this->modifiedFeeOrder($payData);
        }

    }

    private function modifiedOrder($payData,$purchaseOrderTotal){
        $this->load->model('checkout/pay');
        $order_id =$payData['order_id'];
        $payment_code = $payData['payment_code'];
        $balance = $payData['balance'];
        $comment = $payData['comment'];
        $totalPoundage = $payData['totalPoundage'];
        $payment_method = PayCode::getDescriptionWithPoundage($payment_code);
        $payMethod = [
            'payment_firstname'     =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['firstname'] ?? '' : '',
            'payment_lastname'      =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['lastname']?? '' : '',
            'payment_company'       =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['company']?? '' : '',
            'payment_address_1'     =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['address_1']?? '' : '',
            'payment_address_2'     =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['address_2']?? '' : '',
            'payment_city'          =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['city']?? '' : '',
            'payment_postcode'      =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['postcode']?? '' : '',
            'payment_country_id'    =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['country_id']?? '' : '',
            'payment_country'       =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['country']?? '' : '',
            'payment_zone_id'       =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['zone_id']?? '' : '',
            'payment_zone'          =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['zone']?? '' : '',
            'payment_address_format'=>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['address_format']?? '' : '',
            'payment_custom_field'  =>  isset($this->session->data['payment_address']) ? $this->session->data['payment_address']['custom_field'] ?? [] : [],
            'payment_method'        => $payment_method,
            'payment_code'          => $payment_code,
            'date_modified'         => date('Y-m-d H:i:s')
        ];
        $this->model_checkout_pay->updatePayMethod($order_id,$payMethod);

        //修改order_total
        $balance = min($balance,$purchaseOrderTotal);
        $data = array(
            'balance' => $balance,
            'payment_method' => $payment_code,
            'order_total' => $purchaseOrderTotal
        );
        PayCode::setPoundageCalculateTime(Order::find($order_id)->date_added);
        $poundage = $this->getPoundage($data);
        //
        $totalArr = array(
            'balance' => $balance,
            'order_total' => $purchaseOrderTotal,
            'order_id' => $order_id,
            'poundage' => $poundage
        );
        $this->model_checkout_pay->updateOrderTotal($totalArr);

        $this->model_checkout_pay->updateOrderProduct($order_id,$poundage);

        //update comment
        $this->orm->table('oc_order')
            ->where([
                'order_id' => $order_id
            ])
            ->update(
                ['comment'=>$comment]
            );
        $payData['balance'] = $payData['balance'] - $balance;
        $payData['totalPoundage'] = $totalPoundage - $poundage;
        return $payData;
    }

    private function modifiedFeeOrder($data)
    {
        $feeOrderId =$data['fee_order_id'];
        $payment_code = $data['payment_code'];
        $balance = $data['balance'];
        $comment = $data['comment'];
        $poundage = $data['totalPoundage'];

        $feeOrderInfos = app(FeeOrderRepository::class)->findFeeOrderInfo($feeOrderId);
        $feeOrderNums = count($feeOrderInfos);
        $index = 1;
        $poundageUse = 0;
        $feeOrderTotal = array_sum(array_column($feeOrderInfos, 'fee_total'));
        $feeOrderService = app(FeeOrderService::class);
        foreach ($feeOrderInfos as $feeOrderInfo){
            $feeOrderPoundage = 0;
            if ($poundageUse < $poundage) {
                if ($index == $feeOrderNums) {
                    $feeOrderPoundage = $poundage - $poundageUse;
                } else {
                    if ($feeOrderInfo['fee_total'] > 0 && $feeOrderTotal > 0) {
                        $feeOrderPoundage = round(($feeOrderInfo['fee_total'] / $feeOrderTotal) * $poundage, 2);
                        $poundageUse += $feeOrderPoundage;
                    }
                }
            }
            $feeData = [
                'id' => $feeOrderInfo['id'],
                'balance' => min($feeOrderInfo['fee_total'],$balance),
                'poundage' => $feeOrderPoundage,
                'comment' =>  $comment,
                'payment_code' => $payment_code,
                'payment_method' => PayCode::getDescriptionWithPoundage($payment_code),
            ];
            $feeOrderService->updateFeeOrderInfo($feeData);
            $balance = $balance - min($feeOrderInfo['fee_total'],$balance);
            $index++;
        }
    }

    private function getPoundage($data){
        $balance = $data['balance'];
        $payment_method = $data['payment_method'];
        $order_total =  $data['order_total'];
        //扣除使用余额的剩余订单金额
        $payment_method_total = (float)$order_total - (float)$balance;
        $payment_poundage = 0;
        $poundagePercent = PayCode::getPoundage($payment_method);
        if ($poundagePercent > 0) {
            $payment_poundage = $this->customer->getCountryId()== 107 ? round($payment_method_total*$poundagePercent,0):round($payment_method_total*$poundagePercent,2);
        }

        //总服务费
        $poundage = $payment_poundage;
        return $poundage;
    }
}
