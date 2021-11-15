<?php
//-----------------------------------------
// Author: Qphoria@gmail.com
// Web: http://www.opencartguru.com/
//-----------------------------------------
use App\Enums\Pay\PayCode;
use App\Logging\Logger;
use App\Repositories\FeeOrder\FeeOrderRepository;

/**
 * @property ModelCheckoutOrder $model_checkout_order
 * @property ModelLocalisationCountry $model_localisation_country
 * @property ModelLocalisationCurrency $model_localisation_currency
 * @property ModelLocalisationZone $model_localisation_zone
 **/
class ControllerExtensionPaymentCybersourceSop extends Controller {
    const NEED_TO_PAY = 101;
    const PURCHASE_ORDER_TYPE = 1;
    const FEE_ORDER_TYPE = 2;

    private $feeOrderRepository;
    public function __construct(Registry $registry, FeeOrderRepository $feeOrderRepository)
    {
        parent::__construct($registry);
        $this->feeOrderRepository = $feeOrderRepository;
    }
	public function index() {

        $order_id = session('order_id');
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);
        $data['need_pay'] = $order_info['total']>0;
        $data['toSuccessUrl'] = $this->url->link('checkout/success', '', true);

		# Generic Init
		$extension_type 			= 'extension/payment';
		$classname 					= str_replace('vq2-' . basename(DIR_APPLICATION) . '_' . strtolower(get_parent_class($this)) . '_' . $extension_type . '_', '', basename(__FILE__, '.php'));
		$data['classname'] 			= $classname;
		$data 						= array_merge($data, $this->load->language($extension_type . '/' . $classname));

		# Error Check
		$data['error'] = (isset($this->session->data['error'])) ? $this->session->data['error'] : NULL;
		$this->session->remove('error');

		# Common fields
		$data['testmode'] 			= $this->config->get($classname . '_test');

		# Form Fields
		$data['action'] 			= 'index.php?route='.$extension_type.'/'.$classname.'/send';
		$data['form_method'] 		= 'post';
		$data['fields']   			= array();
		$data['button_continue']	= $this->language->get('button_continue');

		### START SPECIFIC DATA ###

		// Device Fingerprint javascript fields
		$data['orgid'] = trim($this->config->get($classname . '_orgid'));
		$data['dfid'] = session_id();
		$data['merchid'] = trim($this->config->get($classname . '_merchid'));

		# Data Fields array - Could be included from external file
        $card_types['visa'] 		= 'Visa';
        $card_types['mastercard'] 	= 'MasterCard';
        $card_types['amex'] 		= 'American Express';
        $card_types['discover'] 	= 'Discover';
//debug-zhang

        $this->load->model('localisation/country');
        $countries = $this->model_localisation_country->getCountries();
        $country_options = array();
        foreach ($countries as $item){
            $country_options[$item['iso_code_2']] = $item['name'];
        }
		$data['fields'][] = array(
			'entry'			=> $this->language->get('entry_card_type'),
			'type'			=> 'select',
			'name'			=> 'card_type',
			'value'			=> 'mastercard',
			'param'			=> 'style="width:200px;display:inline-block;"',
			'required'		=> '1',
			'options'		=> $card_types,
			'help'			=> '',
		);

		$data['fields'][] = array(
			'entry'			=> $this->language->get('entry_card_name'),
			'type'			=> 'text',
			'placeholder' 	=> 'First Last',
			'name'			=> 'card_name',
			'value'			=> 'QIN GE',
			'size'			=> '50',
			'param'			=> 'style="width:200px;"',
			'required'		=> '1',
			'validate'  	=> ''
		);

		$data['fields'][] = array(
			'entry'			=> $this->language->get('entry_card_num'),
			'type'			=> 'text',
			'placeholder' 	=> 'xxxx-xxxx-xxxx-xxxx',
			'name'			=> 'card_num',
			'value'			=> '379125398111003',
//			'value'			=> '5176369940285568',
			'size'			=> '50',
			'param'			=> 'style="width:200px;"',
			'required'		=> '1',
			'validate'  	=> 'creditcard'
		);

        $data['fields'][] = array(
            'entry'			=> $this->language->get('entry_card_cvv'),
            'type'			=> 'text',
            'placeholder' 	=> '3 to 4 digit code',
            'name'			=> 'card_cvv',
			'value'			=> '8500',
//			'value'			=> '584',
            'size'			=> '50',
            'param'			=> 'style="width:95px;"',
        );

		$months = array();
		for($i=1;$i<=12;$i++) {
			$months[sprintf("%02d", $i)] = sprintf("%02d", $i);
		}

		$data['fields'][] = array(
			'entry'			=> $this->language->get('entry_card_exp'),
			'type'			=> 'select',
			'name'			=> 'card_mon',
			'value'			=> '12',
			'param'			=> 'style="width:95px;display:inline-block;"',
			'required'		=> '1',
			'no_close'		=> '1',
			'options'		=> $months,
			'help'			=> '/',
		);

		$years = array();
		for($i=0;$i<=10;$i++) {
			$years[date('Y', strtotime('+'.$i.'year'))] = date('Y', strtotime('+'.$i.'year'));
		}

		$data['fields'][] = array(
			'entry'			=> '/',
			'type'			=> 'select',
			'name'			=> 'card_year',
			'value'			=> '2022',
			'param'			=> 'style="width:95px;display:inline-block;"',
			'required'		=> '1',
			'no_open'		=> '1',
			'options'		=> $years,
			'validate'		=> 'expiry'
		);

		$data['fields'][] = array(
			'entry'			=> 'Bill to address country:',
			'type'			=> 'select',
			'placeholder' 	=> 'Bill to address country',
			'name'			=> 'bill_to_address_country',
			'value'			=> 'US',
			'size'			=> '',
            'param'			=> 'onchange="country(this)" style="width:200px;display:inline-block;"',
//			'required'		=> '1',
            'options'		=> $country_options,
		);
		$data['fields'][] = array(
			'entry'			=> 'Bill to address State/Region/Province:',
			'type'			=> 'select',
			'placeholder' 	=> 'Bill to address state',
			'name'			=> 'bill_to_address_state',
			'value'			=> 'CA',
			'size'			=> '',
            'param'			=> 'style="width:200px;display:inline-block;"',
//			'required'		=> '1',
            'options'		=> array(),
		);
		$data['fields'][] = array(
			'entry'			=> 'Bill to address city:',
			'type'			=> 'text',
			'placeholder' 	=> 'Bill to address city',
			'name'			=> 'bill_to_address_city',
			'value'			=> 'San Rafael',
			'size'			=> '',
            'param'			=> 'style="width:200px;display:inline-block;"',
//			'required'		=> '1',
		);
		$data['fields'][] = array(
			'entry'			=> 'Bill to address:',
			'type'			=> 'text',
			'placeholder' 	=> 'Bill to address',
			'name'			=> 'bill_to_address_line1',
			'value'			=> '744 Estancia Way',
			'size'			=> '',
			'param'			=> 'style="width:500px;"',
//			'required'		=> '1',
		);
		$data['fields'][] = array(
			'entry'			=> 'Bill to address postal code:',
			'type'			=> 'text',
			'placeholder' 	=> 'Bill to address postal code',
			'name'			=> 'bill_to_address_postal_code',
			'value'			=> '94903',
            'size'			=> '50',
            'param'			=> 'style="width:200px;"',
//			'required'		=> '1',
		);
		$data['fields'][] = array(
			'entry'			=> 'Save card info',
			'type'			=> 'checkbox',
			'name'			=> 'save_card_info',
		);
		### END SPECIFIC DATA ###

        //读取保存的信用卡
        $card_row = $this->db->query('select * from oc_credit_card where status=1 and customer_id = '.$this->customer->getId())->row;
        if($card_row){
            //解密
            $sec_key = $card_row['sec_key'];
            foreach ($card_row as $db_key => $db_value) {
                $decode_value = openssl_decrypt($db_value, 'AES-128-ECB', $sec_key);
                if($db_key=='card_expiry_date'){
                    $split = explode('-',$decode_value);
                    $card_row['card_mon'] = $split[0];
                    $card_row['card_year'] = $split[1];
                }else{
                    $card_row[$db_key] = $decode_value;
                }
                unset($decode_value);
            }

            foreach ($card_row as $db_key => $db_value) {
                foreach ($data['fields'] as $field_index => $field_value) {
                    if ($field_value['name'] == $db_key) {
                        $data['fields'][$field_index]['value'] = $db_value;
                        break;
                    }
                    if ($db_key == 'bill_to_address_state') {
                        $data['selected_state'] = $db_value;
                    }
                    if($field_value['name'] == 'save_card_info'){
                        $data['fields'][$field_index]['value'] = true;
                    }
                }
            }


        }
        return $this->load->view($extension_type . '/'. $classname, $data);
	}

	public function send() {

        $json = array();
		# Generic Init
		$extension_type = 'extension/payment';
		$classname = str_replace('vq2-' . basename(DIR_APPLICATION) . '_' . strtolower(get_parent_class($this)) . '_' . $extension_type . '_', '', basename(__FILE__, '.php'));
		$conf_pref = "payment_$classname";
		$data['classname'] = $classname;
		$data = array_merge($data, $this->load->language($extension_type . '/' . $classname));

		# Order Info
		$this->load->model('checkout/order');
		$orderId = session('order_id');
		$sessionId = $this->session->getId();
		$order_info = $this->model_checkout_order->getOrder($orderId);
        $orderResult = $this->model_checkout_order->getOrderStatusByOrderId($orderId);
        $order_status = $orderResult['order_status_id'];

		//获取订单失效时间
        $intervalTime = $this->model_checkout_order->checkOrderExpire($this->session->data['order_id']);
        if($intervalTime>$this->config->get('expire_time')){
            // 更新订单状态,预扣库存回退
            if($order_status != 7){
                $this->model_checkout_order->cancelPurchaseOrderAndReturnStock($orderId);
            }
            $json['status'] = 4;
            $json["msg"] = "The order has expired, please buy again!";
            $json['redirect'] = $this->url->link('checkout/checkout');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }
        if($order_info['total']==0){
            //订单金额为0 直接支付成功
            $this->log->write('订单金额为0 cybersouce直接支付成功  [customer]'.$this->customer->getId().',[order_id]'.$orderId);
            $this->load->model('checkout/order');
            $result = $this->model_checkout_order->completeCyberSourceOrder($orderId, $sessionId,null);
            if($result['success']){
                $json['status']=5;
            }else{
                $json['error'] =$result['msg'];
            }
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return ;
        }
		# Common URL Values
		$callbackurl 	= $this->url->link($extension_type . '/' . $classname . '/callback', '', 'SSL');
		$cancelurl 		= $this->url->link('checkout/checkout', '', 'SSL');
		$successurl 	= $this->url->link('checkout/success','&k='.$sessionId.'&o='.$orderId);
		$declineurl 	= $this->url->link('checkout/checkout', '', 'SSL');


		### START SPECIFIC DATA ###

		# Check for supported currency, otherwise convert
		$supported_currencies = $this->config->get($conf_pref . '_supported_currencies');
        $this->load->model('localisation/currency');
        $currency = $this->model_localisation_currency->getCurrencyByBuyerId($this->customer->getId());
		if (!in_array($currency['code'], $supported_currencies)) {
            $json['error'] = 'Unsupported Currency!';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
		}

		$amount = str_replace(array(','), '', $this->currency->format($order_info['total'], $currency['code'], FALSE, FALSE));

		# Card Check
		$errornumber = '';
		$errortext = '';
		if (!$this->checkCreditCard ($_POST['card_num'], $_POST['card_type'], $_POST['card_cvv'], $_POST['card_mon'], $_POST['card_year'], $errornumber, $errortext)) {
			$json['error'] = $errortext;
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		$this->load->model('localisation/country');
		$store_country_info = $this->model_localisation_country->getCountry($this->config->get('config_country_id'));
		$store_country_iso_3 = $store_country_info['iso_code_3'];

		$subDigits = 0; // 0 means all 4 or 2 means just the last 2

		### START SPECIFIC DATA ###
        $cardTypeNumber = array(
            'visa'		=> '001',
            'mastercard'=> '002',
            'amex'		=> '003',
            'discover'	=> '004'
        );
		$params = array();
		$sign_field_array = ['access_key',
            'profile_id',
            'transaction_uuid',
            'override_custom_cancel_page',
            'override_custom_receipt_page',
            'signed_field_names',
            'unsigned_field_names',
            'signed_date_time',
            'locale',
            'transaction_type',
            'reference_number',
            'amount',
            'currency',
            'payment_method',
            'card_type',
            'card_number',
            'card_expiry_date',
            'bill_to_forename',
            'bill_to_surname',
            'bill_to_email',
            'bill_to_address_line1',
            'bill_to_address_city',
            'bill_to_address_state',
            'bill_to_address_country',
            'bill_to_address_postal_code',
            'customer_ip_address',
            'device_fingerprint_id',
            'merchant_defined_data1',
        ];

		$params['access_key'] 			= trim($this->config->get($conf_pref . '_mid'));
		$params['profile_id'] 			= trim($this->config->get($conf_pref . '_profile_id'));
		$params['transaction_uuid'] 	= uniqid();
        $params['override_custom_cancel_page'] = $cancelurl;
        $params['override_custom_receipt_page'] = $callbackurl;
        $params['signed_field_names']=null;
        $params['unsigned_field_names'] = '';
        $params['signed_date_time']		= gmdate("Y-m-d\TH:i:s\Z", strtotime("0 hours"));
		$params['locale'] 				= 'en';
		$params['transaction_type'] 	= ($this->config->get($conf_pref . '_txntype') ? 'sale' : 'authorization');
		$params['reference_number'] 	= 'b2b#'.$order_info['order_id'];
		$params['amount'] 				= $amount;
		$params['currency'] 			= $currency['code'];
//		$params['override_custom_cancel_page'] = $cancelurl;
//		$params['override_custom_receipt_page'] = $callbackurl;
		$params['payment_method'] 		= 'card';
//		$params['card_name']	 		= $this->request->post['card_name'];
		$params['card_type']	 		= $cardTypeNumber[$this->request->post['card_type']];;
		$params['card_number'] 			= preg_replace('/[^0-9]/', '', $this->request->post['card_num']);
		$params['card_expiry_date']		= $this->request->post['card_mon'] .'-'. substr($this->request->post['card_year'], $subDigits);
        $card_name_arr = preg_split("/\s+/",trim($this->request->post['card_name']));
		$params['bill_to_forename'] 	= $card_name_arr[0];
 		$params['bill_to_surname'] 		= $card_name_arr[1];
 		$params['bill_to_email'] 		= $order_info['email'];
// 		$params['bill_to_email'] 		= '296765062@qq.com';
 		$params['bill_to_address_line1'] = $this->request->post['bill_to_address_line1'];
 		$params['bill_to_address_city'] = $this->request->post['bill_to_address_city'];
 		$params['bill_to_address_state'] = $this->request->post['bill_to_address_state'];
 		$params['bill_to_address_country'] = $this->request->post['bill_to_address_country'];
 		$params['bill_to_address_postal_code'] = $this->request->post['bill_to_address_postal_code'];
		$params['customer_ip_address'] = $order_info['ip'] == '::1'?'127.0.0.1':$order_info['ip'];
		$params['device_fingerprint_id'] = $sessionId;
		$params['merchant_defined_data1'] = '';  //自定义  payment表ID
        if(isset($this->request->post['card_cvv'])){
            $sign_field_array[] = 'card_cvn';
            $params['card_cvn'] 		= preg_replace('/[^0-9]/', '', $this->request->post['card_cvv']);
        }

        $params['signed_field_names']=implode(",", $sign_field_array);

        //保存卡信息
        $this->saveCardInfo($sessionId, $params, $card_name_arr);

        $customer_id = $this->customer->getId();
        //存到payment_info表
        $paymentMethodCybersourceSop = PayCode::PAY_CREDIT_CARD;
        $payment_sql = "insert into tb_payment_info set user_id='$customer_id',total_yzc='$amount',currency_yzc='{$currency['code']}',
order_id_yzc='{$order_info['order_id']}',pay_method='{$paymentMethodCybersourceSop}',card_number='{$params['card_number']}',status=101,add_date=NOW()";
        $this->db->query($payment_sql);
        $params['merchant_defined_data1'] = $this->db->getLastId();
		require(DIR_SYSTEM . '../catalog/controller/extension/payment/' . $classname . '.class.php');
		if ($this->config->get($conf_pref . '_debug')) {
 			$payclass = New $classname(DIR_LOGS);
 		} else {
 			$payclass = New $classname();
		}

		$params['signature'] = $payclass->sign($params, trim($this->config->get($conf_pref . '_key')));

 		if ($this->config->get($conf_pref . '_test')) {
 			$params['test'] = 'true';
		}

 		//$result = $payclass->sendPayment($params);
 		$result = $payclass->buildOutput($params);

		// Unset some params before logging:
        $last4num = substr($params['card_number'],-4);
		$params['card_number'] = 'xxxxxxxxxxxx'.$last4num;
		$params['card_expiry_date'] = 'xxxx';
		$params['card_cvn'] = 'xxx';
		$logData = "cyber支付发送请求  order_id=".$order_info['order_id']."  customer_id=".$customer_id
                    ."\r\nRequest: " . print_r($params,1) . "\r\nResponse: " . print_r($result,1) . "\r\n";
		if ($this->config->get($conf_pref . '_debug')) {
		    file_put_contents(DIR_LOGS . $classname . '_debug.txt', $logData);
		}
        $this->log->write($logData);
		$json = array();

		$json['html'] = $result;

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
		//echo $result;
		//exit;
	}


	public function callback()
    {
        Logger::error('[cyber回调]'.json_encode($_REQUEST,true));

        if (!isset($_REQUEST['req_device_fingerprint_id'])) {
            if (!$this->customer->isLogged()) {
                return 'req_device_fingerprint_id MUST set';
            }
        } else {
            $this->session->start($_REQUEST['req_device_fingerprint_id']);
        }
        $sessionId = $this->session->getId();

        # Generic Init
		$extension_type 			= 'extension/payment';
		$classname 					= str_replace('vq2-' . basename(DIR_APPLICATION) . '_' . strtolower(get_parent_class($this)) . '_' . $extension_type . '_', '', basename(__FILE__, '.php'));
		$data['classname'] 			= $classname;
		$data 						= array_merge($data, $this->load->language($extension_type . '/' . $classname));

		// Debug
		if ($this->config->get($classname . '_debug')) { file_put_contents(DIR_LOGS . $classname . '_debug.txt', __FUNCTION__ . "\r\n$classname GET: " . print_r($_GET,1) . "\r\n" . "$classname POST: " . print_r($_POST,1) . "\r\n", FILE_APPEND); }

		$this->load->model('checkout/order');

		if (!empty($_REQUEST['req_reference_number'])) {
			$orderIdStr = explode("_", $_REQUEST['req_reference_number']);
            $orderId = explode('@',str_replace("b2b#",'',$orderIdStr[0]));
			$purchaseOrderId = $orderId[0];
            $feeOrderId = $orderId[1] == 0?[]:explode(',',$orderId[1]);
		} else {
            $purchaseOrderId = 0;
            $feeOrderId = [];
		}
		$purchaseOrderInfo = $this->model_checkout_order->getOrder($purchaseOrderId);
        $feeOrderInfos = $this->feeOrderRepository->findFeeOrderInfo($feeOrderId);
        //保存payment_info
        $pay_result = json_encode($_REQUEST);
		if(isset($_REQUEST['req_merchant_defined_data1'])){
            $payment_id = $_REQUEST['req_merchant_defined_data1'];
            $transactionId = $_REQUEST['transaction_id'] ?? null; // 入参就没有这个字段
            $this->db->query("update tb_payment_info set  status=200,update_date=NOW() ,pay_result=:pay_result ,order_id='{$transactionId}',message='callback' where id=$payment_id",
                array(':pay_result'=>$pay_result));
        }

		// If there is no order info then fail.
		if (!$purchaseOrderInfo && empty($feeOrderId)) {
            $error_no_order = $this->language->get('error_no_order');
			$this->session->set('error',$error_no_order);
            if (isset($payment_id)) {
                $this->db->query("update tb_payment_info set  message='$error_no_order' where id=$payment_id");
            }
			$this->fail();
		}

		$message = isset($_REQUEST['message']) ? $_REQUEST['message'] : '';

		// If we get a successful response back...
		if (isset($_REQUEST['decision'])) {
			switch ($_REQUEST['decision']) {
				case 'ACCEPT':
                if (isset($payment_id)) {
                    $this->db->query("update tb_payment_info set  status=201, message='success' where id=$payment_id");
                }
                if(!empty($purchaseOrderId)){
                    $customerId = $purchaseOrderInfo['customer_id'];
                }else{
                    $customerId = $feeOrderInfos[0]['buyer_id'];
                }
                $data = [
                    'order_id'=>$purchaseOrderId,
                    'fee_order_arr' =>$feeOrderId,
                    'payment_method' => PayCode::PAY_CREDIT_CARD,
                    'customer_id' =>$customerId,
                    'payment_id' => isset($payment_id) ? $payment_id : 0
                ];
                $this->model_checkout_order->completeCyberSourceOrder($data);
                    $successurl =   $this->url->link('checkout/success','&k='.$sessionId.'&o='.$purchaseOrderId.'&f='.implode(',',$feeOrderId));
				// Mijo Support
				if (strpos(DIR_SYSTEM, 'mijo') !== false) {
					$successurl = str_replace('route', 'option=com_mijoshop&format=raw&tmpl=component&route', $successurl);
				}
				return $this->response->redirectTo($successurl);

				case 'CANCEL':
                    if (isset($payment_id)) {
                        $this->db->query("update tb_payment_info set  message='CANCEL' where id=$payment_id");
                    }
					$this->session->set('error',$this->language->get('error_canceled'));
					break;
				default:
                    $msg = $this->getErrorMsg($_REQUEST['reason_code']);
                    if($msg==null) $msg =$message;
					$this->session->set('error',$msg);
                    if (isset($payment_id)) {
                        $this->db->query("update tb_payment_info set  message='$msg' where id=$payment_id");
                    }
			}
		} else {
			session()->set('error', $this->language->get('error_deal_fail'));
		}
		Logger::error("$classname: ERROR for order id: $purchaseOrderId :: " . $this->session->get('error'));
        $this->fail();
		### END SPECIFIC DATA ###

	}

    private function getErrorMsg($code)
    {
        $msg=null;
        switch ($code) {
            case '102':
            case '101':
                $msg = $this->language->get('error_card_info');
                break;
            case '104':
                $msg = $this->language->get('error_repeat');
                break;
            case '150':
                $msg = $this->language->get('error_system_failure');
                break;
            case '151':
                $msg = $this->language->get('error_timeout');
                break;
            case '200':
                $msg = $this->language->get('error_avs');
                break;
            case '202':
                $msg = $this->language->get('error_expire');
                break;
            case '204':
                $msg = $this->language->get('error_balance_not_enough');
                break;
            case '205':
                $msg = $this->language->get('error_card_lost');
                break;
            default:
                $msg = $this->language->get('error_deal_fail');
        }
        return $msg;
    }

	private function fail($msg = false) {
		$store_url = ($this->config->get('config_ssl') ? (is_numeric($this->config->get('config_ssl'))) ? str_replace('http', 'https', $this->config->get('config_url')) : $this->config->get('config_ssl') : $this->config->get('config_url'));
		if (!$msg) { $msg = (!empty($this->session->data['error']) ? $this->session->data['error'] : 'Unknown Error'); }
		if (method_exists($this->document, 'addBreadcrumb')) { //1.4.x
			$this->redirect((isset($this->session->data['guest'])) ? ($store_url . 'index.php?route=checkout/guest_step_3') : ($store_url . 'index.php?route=checkout/confirm'));
		} else {
			echo '<html><head><script type="text/javascript">';
			echo 'parent.location="' . ($store_url  . 'index.php?route=checkout/checkout') . '";';
			echo '</script></head></html>';
		}
		exit;
	}

	private function checkCreditCard ($cardnumber, $cardtype, $cvv, $expMon, $expYear, &$errornumber, &$errortext) {

		// Define the cards we support. You may add additional card types.

		//  Name:      As in the selection box of the form - must be same as user's
		//  Length:    List of possible valid lengths of the card number for the card
		//  prefixes:  List of possible prefixes for the card
		//  cvv_length:  Valid cvv code length for the card
		//  luhn Boolean to say whether there is a check digit

		// Don't forget - all but the last array definition needs a comma separator!

		$cards = array(
			array ('name' => 'amex',
				  'length' => '15',
				  'prefixes' => '34,37',
				  'cvv_length' => '4',
				  'luhn' => true
				 ),
			array ('name' => 'diners',
				  'length' => '14,16',
				  'prefixes' => '36,38,54,55',
				  'cvv_length' => '3',
				  'luhn' => true
				 ),
			array ('name' => 'discover',
				  'length' => '16',
				  'prefixes' => '6011,622,64,65',
				  'cvv_length' => '3',
				  'luhn' => true
				 ),
			array ('name' => 'jcb',
				  'length' => '16',
				  'prefixes' => '35',
				  'cvv_length' => '3',
				  'luhn' => true
				 ),
			array ('name' => 'maestro',
				  'length' => '12,13,14,15,16,18,19',
				  'prefixes' => '5018,5020,5038,6304,6759,6761,6762,6763',
				  'cvv_length' => '3',
				  'luhn' => true
				 ),
			array ('name' => 'mastercard',
				  'length' => '16',
				  'prefixes' => '51,52,53,54,55',
				  'cvv_length' => '3',
				  'luhn' => true
				 ),
			array ('name' => 'solo',
				  'length' => '16,18,19',
				  'prefixes' => '6334,6767',
				  'cvv_length' => '3',
				  'luhn' => true
				 ),
			array ('name' => 'switch',
				  'length' => '16,18,19',
				  'prefixes' => '4903,4905,4911,4936,564182,633110,6333,6759',
				  'cvv_length' => '3',
				  'luhn' => true
				 ),
			array ('name' => 'visa',
				  'length' => '16',
				  'prefixes' => '4',
				  'cvv_length' => '3',
				  'luhn' => true
				 ),
			array ('name' => 'visa_electron',
				  'length' => '16',
				  'prefixes' => '417500,4917,4913,4508,4844',
				  'cvv_length' => '3',
				  'luhn' => true
				 ),
			array ('name' => 'laser',
				  'length' => '16,17,18,19',
				  'prefixes' => '6304,6706,6771,6709',
				  'cvv_length' => '3',
				  'luhn' => true
				 )
		);


		$ccErrorNo = 0;
		$ccErrors[0] = $this->language->get('error_card_type');
		$ccErrors[1] = $this->language->get('error_card_num');
		$ccErrors[2] = $this->language->get('error_card_cvv');
		$ccErrors[3] = $this->language->get('error_card_exp');

		// Establish card type
		$cardType = -1;
		for ($i=0; $i<sizeof($cards); $i++) {

			// See if it is this card (ignoring the case of the string)
			if (strtolower($cardtype) == strtolower($cards[$i]['name'])) {
				$cardType = $i;
				break;
			}
		}

		// If card type not found, report an error
		if ($cardType == -1) {
			$errornumber = 0;
			$errortext = $ccErrors[$errornumber];
			return false;
		}

		// Ensure that the user has provided a credit card number
		if (strlen($cardnumber) == 0)  {
			$errornumber = 1;
			$errortext = $ccErrors[$errornumber];
			return false;
		}

		// Remove any spaces from the credit card number
		$cardNo = str_replace (array(' ', '-'), '', $cardnumber);

		// Check that the number is numeric and of the right sort of length.
		if (!preg_match("/^[0-9]{13,19}$/", $cardNo))  {
			$errornumber = 1;
			$errortext = $ccErrors[$errornumber];
			return false;
		}

		// Remove any spaces or non-numerics from the expiry date fields
		$expMon = preg_replace('/[^0-9]/', '', $expMon);
		$expYear = preg_replace('/[^0-9]/', '', $expYear);

		// Check expiry length
		if (strlen($expMon) != 2 || strlen($expYear) != 4) {
			$errornumber = 3;
			$errortext = $ccErrors[$errornumber];
			return false;
		}

		// Check the expiry date
		/* Get timestamp of midnight on day after expiration month. */
		$exp_ts = mktime(0, 0, 0, $expMon + 1, 1, $expYear);

		$cur_ts = time();
		/* Don't validate for dates more than 10 years in future. */
		$max_ts = $cur_ts + (10 * 365 * 24 * 60 * 60);

		if ($exp_ts < $cur_ts || $exp_ts > $max_ts) {
			$errornumber = 3;
			$errortext = $ccErrors[$errornumber];
			return false;
		}

		// Now check the modulus 10 check digit - if required
		if ($cards[$cardType]['luhn']) {
			$checksum = 0;                                  // running checksum total
			$mychar = "";                                   // next char to process
			$j = 1;                                         // takes value of 1 or 2

			// Process each digit one by one starting at the right
			for ($i = strlen($cardNo) - 1; $i >= 0; $i--) {

				// Extract the next digit and multiply by 1 or 2 on alternative digits.
				$calc = $cardNo{$i} * $j;

				// If the result is in two digits add 1 to the checksum total
				if ($calc > 9) {
					$checksum = $checksum + 1;
					$calc = $calc - 10;
				}

				// Add the units element to the checksum total
				$checksum = $checksum + $calc;

				// Switch the value of j
				if ($j ==1) {$j = 2;} else {$j = 1;};
			}

			// All done - if checksum is divisible by 10, it is a valid modulus 10.
			// If not, report an error.
			if ($checksum % 10 != 0) {
				$errornumber = 1;
				$errortext = $ccErrors[$errornumber];
				return false;
			}
		}

		// The following are the card-specific checks we undertake.

		// Load an array with the valid prefixes for this card
		$prefix = explode(',', $cards[$cardType]['prefixes']);

		// Now see if any of them match what we have in the card number
		$PrefixValid = false;
		for ($i=0; $i<sizeof($prefix); $i++) {
			$exp = '/^' . $prefix[$i] . '/';
			if (preg_match($exp,$cardNo)) {
				$PrefixValid = true;
				break;
			}
		}

		// If it isn't a valid prefix there's no point at looking at the length
		if (!$PrefixValid) {
			$errornumber = 1;
			$errortext = $ccErrors[$errornumber];
			return false;
		}

		// See if the length is valid for this card
		$LengthValid = false;
		$lengths = explode(',', $cards[$cardType]['length']);
		for ($j=0; $j<sizeof($lengths); $j++) {
			if (strlen($cardNo) == $lengths[$j]) {
				$LengthValid = true;
				break;
			}
		}

		// See if all is OK by seeing if the length was valid.
		if (!$LengthValid) {
			$errornumber = 1;
			$errortext = $ccErrors[$errornumber];
			return false;
		};

		$cvv_length = $cards[$cardType]['cvv_length'];
		if (strlen($cvv)!=0 && strlen($cvv) != $cvv_length) {
			$errornumber = 2;
			$errortext = $ccErrors[$errornumber];
			return false;
		}

		// The credit card is in the required format.
		return true;
	}
    public function country() {
        $json = array();

        $this->load->model('localisation/country');

        $country_info = $this->model_localisation_country->getCountryByCode($this->request->get['code']);

        if ($country_info) {
            $this->load->model('localisation/zone');

            $json = array(
                'code'        => $country_info['iso_code_2'],
                'name'              => $country_info['name'],
                'postcode_required' => $country_info['postcode_required'],
                'zone'              => $this->model_localisation_zone->getZonesByCountryId($country_info['country_id']),
                'status'            => $country_info['status']
            );
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * @param $sessionId
     * @param array $params
     * @param $card_name_arr
     */
    public function saveCardInfo($sessionId, array $params, $card_name_arr)
    {
        $encode_method = 'AES-128-ECB';
//        $card_row = $this->db->query('select * from oc_credit_card where customer_id = ' . $this->customer->getId())->row;
//        if ($card_row) {
//            $card_sql = " update ";
//        } else {
//            $card_sql = " insert into ";
//        }
        $card_sql = " insert into ";
        $customer_id = $this->customer->getId();
        $card_param = array(
            ':customer_id' => $customer_id,
            ':sec_key' => $sessionId,
            ':card_number' => openssl_encrypt($params['card_number'], $encode_method, $sessionId),
            ':card_name' => openssl_encrypt($card_name_arr[0] . ' ' . $card_name_arr[1], $encode_method, $sessionId),
            ':card_type' => openssl_encrypt($this->request->post['card_type'], $encode_method, $sessionId),
            ':card_expiry_date' => openssl_encrypt($params['card_expiry_date'], $encode_method, $sessionId),
            ':bill_to_address_line1' => openssl_encrypt($params['bill_to_address_line1'], $encode_method, $sessionId),
            ':bill_to_address_city' => openssl_encrypt($params['bill_to_address_city'], $encode_method, $sessionId),
            ':bill_to_address_state' => openssl_encrypt($params['bill_to_address_state'], $encode_method, $sessionId),
            ':bill_to_address_country' => openssl_encrypt($params['bill_to_address_country'], $encode_method, $sessionId),
            ':bill_to_address_postal_code' => openssl_encrypt($params['bill_to_address_postal_code'], $encode_method, $sessionId),
//            ':card_number' => $params['card_number'],
//            ':card_name' => $card_name_arr[0] . '@#@' . $card_name_arr[1],
//            ':card_type' => $this->request->post['card_type'],
//            ':card_expiry_date' => $params['card_expiry_date'],
//            ':bill_to_address_line1' => $params['bill_to_address_line1'],
//            ':bill_to_address_city' => $params['bill_to_address_city'],
//            ':bill_to_address_state' => $params['bill_to_address_state'],
//            ':bill_to_address_country' => $params['bill_to_address_country'],
//            ':bill_to_address_postal_code' => $params['bill_to_address_postal_code'],
            ':status' => ($this->request->post['save_card_info'] ?? '') == 'on' ? 1 : 0,
        );
        $card_sql .= " oc_credit_card set customer_id =:customer_id,sec_key=:sec_key, card_type=:card_type,card_number=:card_number,card_name=:card_name,card_expiry_date=:card_expiry_date, bill_to_address_line1=:bill_to_address_line1,bill_to_address_city=:bill_to_address_city, bill_to_address_state=:bill_to_address_state,bill_to_address_country=:bill_to_address_country, bill_to_address_postal_code=:bill_to_address_postal_code,status=:status ";
//        if ($card_row) {
//            $card_param[':card_id'] = $card_row['card_id'];
//            $card_sql .= " where card_id=:card_id ";
//        }
        $this->db->query($card_sql, $card_param);
    }

    private function sendNew($data) {

        $json = array();
        # Generic Init
        $extension_type = 'extension/payment';
        $classname = str_replace('vq2-' . basename(DIR_APPLICATION) . '_' . strtolower(get_parent_class($this)) . '_' . $extension_type . '_', '', basename(__FILE__, '.php'));
        $conf_pref = "payment_$classname";
        $data['classname'] = $classname;
        $data = array_merge($data, $this->load->language($extension_type . '/' . $classname));

        # Order Info
        $this->load->model('checkout/order');
        $orderId = $data['order_id'];
        $feeOrderId = isset($data['fee_order_id'])?$data['fee_order_id']:0;
        $balance = isset($data['balance'])?$data['balance']:0;
        $comment = isset($data['comment'])?$data['comment']:0;
        $feeOrderInfos = $this->feeOrderRepository->findFeeOrderInfo($feeOrderId);
        $customer_id = $this->customer->getId();
        //商品单订单最终金额
        $purchaseOrderTotal = 0;
        //商品订单使用余额
        $purchaseOrderBalance = 0;
        //商品订单手续费
        $purchasePoundage = 0;
        //订单货值金额(货值+服务费+运费)
        $productTotal = 0;
        if(!empty($orderId)){
            //商品单订单金额明细
            $purchaseOrderTotalQuery = $this->orm->table('oc_order_total')
                ->select('code','value')
                ->where('order_id','=',$orderId)
                ->get();
            $purchaseOrderTotalQuery = obj2array($purchaseOrderTotalQuery);
            foreach ($purchaseOrderTotalQuery as $row) {
                if ($row['code'] == 'total') {
                    $purchaseOrderTotal += $row['value'];
                }else if($row['code'] == 'balance'){
                    $purchaseOrderBalance += $row['value'];
                }else if($row['code'] == 'poundage'){
                    $purchasePoundage += $row['value'];
                }else{
                    $productTotal += $row['value'];
                }
            }
        }
        //费用单订单金额汇总
        $feeOrderTotal = 0;
        $feeOrderBalance = 0;

        $feeOrderPoundage = 0;

        foreach ($feeOrderInfos as $feeOrderInfo){
            $feeOrderTotal += $feeOrderInfo['fee_total'];
            $feeOrderBalance += $feeOrderInfo['balance'];
            $feeOrderPoundage += $feeOrderInfo['poundage'];
        }

        //商品单和费用单总值
        $orderTotalYzc = $purchaseOrderTotal+$feeOrderTotal+$feeOrderPoundage-$feeOrderBalance;
        $sessionId = $this->session->getId();
        if($orderTotalYzc == 0){
            //订单金额为0 直接支付成功
            Logger::app('订单金额为0 cybersouce直接支付成功  [customer]'.$this->customer->getId().',订单信息'.$data);
            $this->load->model('checkout/order');
            $data = [
                'order_id'=>$orderId,
                'fee_order_arr' =>$feeOrderId,
                'payment_method' => PayCode::PAY_CREDIT_CARD,
                'customer_id' =>$customer_id,
                'payment_id' =>  0
            ];
            $result = $this->model_checkout_order->completeCyberSourceOrder($data);
            if($result['success']){
                $json['status']=1;
            }else{
                $json['status'] = 5;
                $json['error'] =$result['msg'];
            }
            return $json;
        }
        # Common URL Values
        $callbackurl 	= $this->url->link($extension_type . '/' . $classname . '/callback', '', 'SSL');
        $cancelurl 		= $this->url->link('checkout/checkout', '', 'SSL');
        $successurl 	= $this->url->link('checkout/success','&k='.$sessionId.'&o='.$orderId.'&f='.implode(',',$feeOrderId));
        $declineurl 	= $this->url->link('checkout/checkout', '', 'SSL');


        ### START SPECIFIC DATA ###

        # Check for supported currency, otherwise convert
        $supported_currencies = $this->config->get($conf_pref . '_supported_currencies');
        $this->load->model('localisation/currency');
        $currency = $this->model_localisation_currency->getCurrencyByBuyerId($this->customer->getId());
        if (!in_array($currency['code'], $supported_currencies)) {
            $json['status'] = 5;
            $json['error'] = 'Unsupported Currency!';
            return $json;
        }

        $amount = str_replace(array(','), '', $this->currency->format($orderTotalYzc, $currency['code'], FALSE, FALSE));

        # Card Check
        $errornumber = '';
        $errortext = '';
        if (!$this->checkCreditCard ($_POST['card_num'], $_POST['card_type'], $_POST['card_cvv'], $_POST['card_mon'], $_POST['card_year'], $errornumber, $errortext)) {
            $json['status'] = 5;
            $json['error'] = $errortext;
            return $json;
        }

        $this->load->model('localisation/country');
        $store_country_info = $this->model_localisation_country->getCountry($this->config->get('config_country_id'));
        $store_country_iso_3 = $store_country_info['iso_code_3'];

        $subDigits = 0; // 0 means all 4 or 2 means just the last 2

        ### START SPECIFIC DATA ###
        $cardTypeNumber = array(
            'visa'		=> '001',
            'mastercard'=> '002',
            'amex'		=> '003',
            'discover'	=> '004'
        );
        $params = array();
        $sign_field_array = ['access_key',
            'profile_id',
            'transaction_uuid',
            'override_custom_cancel_page',
            'override_custom_receipt_page',
            'signed_field_names',
            'unsigned_field_names',
            'signed_date_time',
            'locale',
            'transaction_type',
            'reference_number',
            'amount',
            'currency',
            'payment_method',
            'card_type',
            'card_number',
            'card_expiry_date',
            'bill_to_forename',
            'bill_to_surname',
            'bill_to_email',
            'bill_to_address_line1',
            'bill_to_address_city',
            'bill_to_address_state',
            'bill_to_address_country',
            'bill_to_address_postal_code',
            'customer_ip_address',
            'device_fingerprint_id',
            'merchant_defined_data1',
        ];

        $params['access_key'] 			= trim($this->config->get($conf_pref . '_mid'));
        $params['profile_id'] 			= trim($this->config->get($conf_pref . '_profile_id'));
        $params['transaction_uuid'] 	= uniqid();
        $params['override_custom_cancel_page'] = $cancelurl;
        $params['override_custom_receipt_page'] = $callbackurl;
        $params['signed_field_names']=null;
        $params['unsigned_field_names'] = '';
        $params['signed_date_time']		= gmdate("Y-m-d\TH:i:s\Z", strtotime("0 hours"));
        $params['locale'] 				= 'en';
        $params['transaction_type'] 	= ($this->config->get($conf_pref . '_txntype') ? 'sale' : 'authorization');
        $params['reference_number'] 	= 'b2b#'.$orderId.'@'.implode(',',$feeOrderId);
        $params['amount'] 				= $amount;
        $params['currency'] 			= $currency['code'];
//		$params['override_custom_cancel_page'] = $cancelurl;
//		$params['override_custom_receipt_page'] = $callbackurl;
        $params['payment_method'] 		= 'card';
//		$params['card_name']	 		= $this->request->post['card_name'];
        $params['card_type']	 		= $cardTypeNumber[$this->request->post['card_type']];;
        $params['card_number'] 			= preg_replace('/[^0-9]/', '', $this->request->post['card_num']);
        $params['card_expiry_date']		= $this->request->post['card_mon'] .'-'. substr($this->request->post['card_year'], $subDigits);
        $card_name_arr = preg_split("/\s+/",trim($this->request->post['card_name']));
        $params['bill_to_forename'] 	= $card_name_arr[0];
        $params['bill_to_surname'] 		= $card_name_arr[1];
        $params['bill_to_email'] 		= $this->customer->getEmail();
// 		$params['bill_to_email'] 		= '296765062@qq.com';
        $params['bill_to_address_line1'] = $this->request->input->get('bill_to_address_line1');
        $params['bill_to_address_city'] = $this->request->input->get('bill_to_address_city');
        $params['bill_to_address_state'] = $this->request->input->get('bill_to_address_state');
        $params['bill_to_address_country'] = $this->request->input->get('bill_to_address_country');
        $params['bill_to_address_postal_code'] = $this->request->input->get('bill_to_address_postal_code');
        $params['customer_ip_address'] = $this->customer->getIp() == '::1'?'127.0.0.1':$this->customer->getIp();
        $params['device_fingerprint_id'] = $sessionId;
        $params['merchant_defined_data1'] = '';  //自定义  payment表ID
        if($this->request->input->get('card_cvv')){
            $sign_field_array[] = 'card_cvn';
            $params['card_cvn'] 		= preg_replace('/[^0-9]/', '', $this->request->post['card_cvv']);
        }

        $params['signed_field_names']=implode(",", $sign_field_array);

        //保存卡信息
        $this->saveCardInfo($sessionId, $params, $card_name_arr);

        //存到payment_info表
//        $payment_sql = "insert into tb_payment_info set user_id='$customer_id',total_yzc='$amount',currency_yzc='{$currency['code']}',
//order_id_yzc='{$order_info['order_id']}',pay_method='cybersource_sop',card_number='{$params['card_number']}',status=101,add_date=NOW()";
//        $this->db->query($payment_sql);
        $createTime = date('Y-m-d H:i:s');
        //拼装PaymentInfoData
        $paymentInfaoData = [
            "user_id" => $customer_id,
            "total_yzc" => $amount,
            "currency_yzc" => $currency['code'],
            "pay_method" => PayCode::PAY_CREDIT_CARD,
            "card_number" => $params['card_number'],
            "status" => self::NEED_TO_PAY,
            "add_date" => $createTime,
            "balance" => $balance,
            "comment" => $comment
        ];
        $paymentInfoHeaderId = $this->model_checkout_order->savePaymentInfo($paymentInfaoData);
        $paymenttInfoDetailData = [];
        if (!empty($orderId)) {
            $paymenttInfoDetailData[] = [
                "header_id" => $paymentInfoHeaderId,
                "order_type" => self::PURCHASE_ORDER_TYPE,
                "order_id" => $orderId,
                "create_user" => $customer_id,
                "create_time" => $createTime
            ];
        }
        if(!empty($feeOrderId)){
            foreach ($feeOrderId as $OrderId){
                $paymenttInfoDetailData[] = [
                    "header_id" => $paymentInfoHeaderId,
                    "order_type" => self::FEE_ORDER_TYPE,
                    "order_id" => $OrderId,
                    "create_user" => $customer_id,
                    "create_time" => $createTime
                ];
            }
        }
        $this->model_checkout_order->savePaymentInfoDetail($paymenttInfoDetailData);
        $params['merchant_defined_data1'] = $paymentInfoHeaderId;
        require(DIR_SYSTEM . '../catalog/controller/extension/payment/' . $classname . '.class.php');
        if ($this->config->get($conf_pref . '_debug')) {
            $payclass = New $classname(DIR_LOGS);
        } else {
            $payclass = New $classname();
        }

        $params['signature'] = $payclass->sign($params, trim($this->config->get($conf_pref . '_key')));

        if ($this->config->get($conf_pref . '_test')) {
            $params['test'] = 'true';
        }

        //$result = $payclass->sendPayment($params);
        $result = $payclass->buildOutput($params);

        // Unset some params before logging:
        $last4num = substr($params['card_number'],-4);
        $params['card_number'] = 'xxxxxxxxxxxx'.$last4num;
        $params['card_expiry_date'] = 'xxxx';
        $params['card_cvn'] = 'xxx';
        $logData = "cyber支付发送请求,data=".json_encode($data)."  customer_id=".$customer_id
            ."\r\nRequest: " . print_r($params,1) . "\r\nResponse: " . print_r($result,1) . "\r\n";
        if ($this->config->get($conf_pref . '_debug')) {
            file_put_contents(DIR_LOGS . $classname . '_debug.txt', $logData);
        }
        Logger::error($logData);
        $json = array();

        $json['html'] = $result;
        $json['status'] = 1;

        return $json;
    }

    public function cybersourceForm($data) {

        $orderId = $data['order_id'];
        $feeOrderIdArr = $data['fee_order_id'];
        $this->load->model('checkout/order');
        $feeOrderTotal = $this->feeOrderRepository->findFeeOrderTotal($feeOrderIdArr);
        $purchaseOrderTotal = $this->model_checkout_order->getCreditTotal($orderId);
        $creditPayTotal = $purchaseOrderTotal+$feeOrderTotal;
        $data['need_pay'] = $creditPayTotal>0;
        $data['toSuccessUrl'] = $this->url->link('checkout/success', '', true);

        # Generic Init
        $extension_type 			= 'extension/payment';
        $classname 					= str_replace('vq2-' . basename(DIR_APPLICATION) . '_' . strtolower(get_parent_class($this)) . '_' . $extension_type . '_', '', basename(__FILE__, '.php'));
        $data['classname'] 			= $classname;
        $data 						= array_merge($data, $this->load->language($extension_type . '/' . $classname));

        # Error Check
        $data['error'] = (isset($this->session->data['error'])) ? $this->session->data['error'] : NULL;
        $this->session->remove('error');

        # Common fields
        $data['testmode'] 			= $this->config->get($classname . '_test');

        # Form Fields
        $data['action'] 			= 'index.php?route='.$extension_type.'/'.$classname.'/send';
        $data['form_method'] 		= 'post';
        $data['fields']   			= array();
        $data['button_continue']	= $this->language->get('button_continue');

        ### START SPECIFIC DATA ###

        // Device Fingerprint javascript fields
        $data['orgid'] = trim($this->config->get($classname . '_orgid'));
        $data['dfid'] = session_id();
        $data['merchid'] = trim($this->config->get($classname . '_merchid'));

        # Data Fields array - Could be included from external file
        $card_types['visa'] 		= 'Visa';
        $card_types['mastercard'] 	= 'MasterCard';
        $card_types['amex'] 		= 'American Express';
        $card_types['discover'] 	= 'Discover';
//debug-zhang

        $this->load->model('localisation/country');
        $countries = $this->model_localisation_country->getCountries();
        $country_options = array();
        foreach ($countries as $item){
            $country_options[$item['iso_code_2']] = $item['name'];
        }
        $data['fields'][] = array(
            'entry'			=> $this->language->get('entry_card_type'),
            'type'			=> 'select',
            'name'			=> 'card_type',
//			'value'			=> 'amex',
            'required'		=> '1',
            'options'		=> $card_types,
            'help'			=> '',
        );

        $data['fields'][] = array(
            'entry'			=> $this->language->get('entry_card_name'),
            'type'			=> 'text',
            'placeholder' 	=> 'First Last',
            'name'			=> 'card_name',
//			'value'			=> 'QIN GE',
            'size'			=> '50',
            'required'		=> '1',
            'validate'  	=> ''
        );

        $data['fields'][] = array(
            'entry'			=> $this->language->get('entry_card_num'),
            'type'			=> 'text',
            'placeholder' 	=> 'xxxx-xxxx-xxxx-xxxx',
            'name'			=> 'card_num',
//			'value'			=> '379127267081002',
//			'value'			=> '5176369940285568',
            'size'			=> '50',
            'required'		=> '1',
            'validate'  	=> 'creditcard'
        );

        $data['fields'][] = array(
            'entry'			=> $this->language->get('entry_card_cvv'),
            'type'			=> 'text',
            'placeholder' 	=> '3 to 4 digit code',
            'name'			=> 'card_cvv',
//			'value'			=> '4166',
//			'value'			=> '8500',
            'size'			=> '50',
        );

        $months = array();
        for($i=1;$i<=12;$i++) {
            $months[sprintf("%02d", $i)] = sprintf("%02d", $i);
        }

        $data['fields'][] = array(
            'entry'			=> $this->language->get('entry_card_exp'),
            'type'			=> 'select',
            'name'			=> 'card_mon',
//			'value'			=> '12',
            'required'		=> '1',
            'no_close'		=> '1',
            'param'			=> 'style="width:163px;display:inline-block;"',
            'options'		=> $months,
            'help'			=> '/',
        );

        $years = array();
        for($i=0;$i<=10;$i++) {
            $years[date('Y', strtotime('+'.$i.'year'))] = date('Y', strtotime('+'.$i.'year'));
        }

        $data['fields'][] = array(
            'entry'			=> '/',
            'type'			=> 'select',
            'name'			=> 'card_year',
//			'value'			=> '2022',
            'required'		=> '1',
            'no_open'		=> '1',
            'param'			=> 'style="width:163px;display:inline-block;"',
            'options'		=> $years,
            'validate'		=> 'expiry'
        );

        $data['fields'][] = array(
            'entry'			=> 'Bill to address country:',
            'type'			=> 'select',
            'placeholder' 	=> 'Bill to address country',
            'name'			=> 'bill_to_address_country',
//			'value'			=> 'US',
            'size'			=> '',
            'param'			=> 'onchange="country(this)"',
//			'required'		=> '1',
            'options'		=> $country_options,
        );
        $data['fields'][] = array(
            'entry'			=> 'Bill to address State/Region/Province:',
            'type'			=> 'select',
            'placeholder' 	=> 'Bill to address state',
            'name'			=> 'bill_to_address_state',
//			'value'			=> 'CA',
            'size'			=> '',
//			'required'		=> '1',
            'options'		=> array(),
        );
        $data['fields'][] = array(
            'entry'			=> 'Bill to address city:',
            'type'			=> 'text',
            'placeholder' 	=> 'Bill to address city',
            'name'			=> 'bill_to_address_city',
//			'value'			=> 'CA',
            'size'			=> '',
//			'required'		=> '1',
        );
        $data['fields'][] = array(
            'entry'			=> 'Bill to address:',
            'type'			=> 'text',
            'placeholder' 	=> 'Bill to address',
            'name'			=> 'bill_to_address_line1',
//			'value'			=> '744 Estancia Way San Rafael',
            'size'			=> '',
//			'required'		=> '1',
        );
        $data['fields'][] = array(
            'entry'			=> 'Bill to address postal code:',
            'type'			=> 'text',
            'placeholder' 	=> 'Bill to address postal code',
            'name'			=> 'bill_to_address_postal_code',
//			'value'			=> '94903',
            'size'			=> '50',
//			'required'		=> '1',
        );
        $data['fields'][] = array(
            'entry'			=> 'Save card info',
            'type'			=> 'checkbox',
            'name'			=> 'save_card_info',
        );
        ### END SPECIFIC DATA ###

        //读取保存的信用卡
        $card_rows = $this->db->query('select * from oc_credit_card where status=1 and customer_id = '.$this->customer->getId())->rows;
        //
        $cartInfoOption = array();
        $this->load->model('localisation/zone');
        $this->load->model('localisation/country');
        $cardInfo = [];
        if($card_rows){
            foreach ($card_rows as $card_row) {
                //解密
                $sec_key = $card_row['sec_key'];
                foreach ($card_row as $db_key => $db_value) {
                    if($db_key != 'card_id') {
                        $decode_value = openssl_decrypt($db_value, 'AES-128-ECB', $sec_key);
                    }else{
                        $decode_value = $db_value;
                    }
                    if ($db_key == 'card_expiry_date') {
                        $split = explode('-', $decode_value);
                        $card_row['card_mon'] = $split[0];
                        $card_row['card_year'] = $split[1];
                    } else {
                        $card_row[$db_key] = $decode_value;
                    }
                    unset($decode_value);
                }
                $card_row['card_num'] = $card_row['card_number'];
                $country_info = $this->model_localisation_country->getCountryByCode($card_row['bill_to_address_country']);
                $zone = $this->model_localisation_zone->getZonesByCountryId($country_info['country_id']);
                $card_row['zone'] = $zone;
                $cartInfoOption[$card_row['card_id']] = $card_row['card_type'].$card_row['card_number'];
                $cardInfo[$card_row['card_id']] = $card_row;
//                array_push($cartInfoOption,array($card_row['card_id']=>$optionName));
//                foreach ($card_row as $db_key => $db_value) {
//                    foreach ($data['fields'] as $field_index => $field_value) {
//                        if ($field_value['name'] == $db_key) {
//                            $data['fields'][$field_index]['value'] = $db_value;
//                            break;
//                        }
//                        if ($db_key == 'bill_to_address_state') {
//                            $data['selected_state'] = $db_value;
//                        }
//                        if ($field_value['name'] == 'save_card_info') {
//                            $data['fields'][$field_index]['value'] = true;
//                        }
//                    }
//                }
            }

        }
        $data['cartInfoOption'] = count($cartInfoOption)>0?$cartInfoOption:false;
        $data['cardInfo'] = json_encode($cardInfo);
        return $this->load->view('extension/payment/cybersource_sop_new', $data);
    }

    public function createOrder($data)
    {
            try {
                $this->db->beginTransaction();
                $orderId = isset($data['order_id']) ? $data['order_id'] : 0;
                //费用单订单号
                $feeOrderId = isset($data['fee_order_id']) ? $data['fee_order_id'] : 0;
                if($orderId == 0 && empty($feeOrderId)){
                    $json['status'] = 5;
                    $json['error'] = 'Payment failed, we will deal with it as soon as possible. If you have any questions, please contact us.';
                    Logger::error('订单支付失败,商品单和费用单订单号为空,订单号信息' . json_encode($data));
                    return $json;
                }
                $this->load->model('checkout/order');
                $orderInfo = $this->model_checkout_order->getOrder($orderId);
                $orderProducts = $this->model_checkout_order->getOrderProducts($orderId);
                $feeOrderInfos = $this->feeOrderRepository->findFeeOrderInfo($feeOrderId);
                if(empty($orderInfo) && empty($orderProducts) && empty($feeOrderInfos)){
                    $json['status'] = 5;
                    $json['error'] = 'Payment failed, we will deal with it as soon as possible. If you have any questions, please contact us.';
                    Logger::error('订单支付失败,未查询到订单信息,订单号信息' . json_encode($data));
                    return $json;
                }
                $json = $this->sendNew($data);
                if ($json['status'] == 5) {
                    Logger::error('订单支付失败，订单号信息' . json_encode($data));
                    $this->db->rollback();
                }
                $this->db->commit();
                return $json;
            } catch (Exception $e) {
                Logger::error('订单支付失败，错误信息' . $e);
                $this->db->rollback();
            }
    }
}
?>
