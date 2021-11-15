<?php

use App\Enums\Order\OcOrderStatus;

/**
 * @property ModelAccountOrder $model_account_order
 * @property ModelAccountOrderForGiga $model_account_order_for_giga
 * @property ModelAccountProductQuotesMargin $model_account_product_quotes_margin
 * @property ModelCatalogProduct $model_account_product
 * @property ModelToolImage $model_tool_image
 * @property ModelToolUpload $model_tool_upload
 */
class ControllerAccountOrderForGiga extends Controller {
	public function index() {
	    $this->load->model('account/order_for_giga');
        $this->load->model('catalog/product');
        $this->load->model('account/product_quotes/margin');
        $this->load->model('tool/image');

		if (!$this->customer->isLogged()) {
			session()->set('redirect', $this->url->link('account/order', '', true));

			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->language('account/order');

		$this->document->setTitle($this->language->get('heading_title'));

        $url = '';
        $param = [];

        if (isset($this->request->get['filter_orderDate_from']) && $this->request->get['filter_orderDate_from'] !='') {
            $data['filter_orderDate_from'] = $this->request->get['filter_orderDate_from'];
            $param['filter_orderDate_from'] = $this->request->get['filter_orderDate_from'];
            $url .= '&filter_orderDate_from=' . $this->request->get['filter_orderDate_from'];
        }
        if (isset($this->request->get['filter_orderDate_to']) && $this->request->get['filter_orderDate_to'] != ''){
            $data['filter_orderDate_to'] = $this->request->get['filter_orderDate_to'];
            $param['filter_orderDate_to'] = $this->request->get['filter_orderDate_to'];
            $url .= '&filter_orderDate_to=' . $this->request->get['filter_orderDate_to'];
        }

        if (isset($this->request->get['filter_orderId'])) {
            $data['filter_orderId'] = $this->request->get['filter_orderId'];
            $param['filter_orderId'] = $this->request->get['filter_orderId'];
            $url .= '&filter_orderId=' . $this->request->get['filter_orderId'];
        }

        if (isset($this->request->get['filter_item_code'])) {
            $data['filter_item_code'] = $this->request->get['filter_item_code'];
            $param['filter_item_code'] = $this->request->get['filter_item_code'];
            $url .= '&filter_item_code=' . $this->request->get['filter_item_code'];
        }
        if(isset($this->request->get['sort_order_date'])){
            $param['sort_order_date'] = $this->request->get['sort_order_date'];
            if($this->request->get['sort_order_date'] == 'asc'){
                $sort = 'desc';
            }else{
                $sort = 'asc';
            }
            $url_sort = $url;
            $url_sort .=  $url_sort.'&sort_order_date=' . $sort;
            $url .= '&sort_order_date=' . $this->request->get['sort_order_date'];
            $data['sort'] = $this->request->get['sort_order_date'];
        }else{
            $url_sort = $url;

        }

        $page = $this->request->get['page'] ?? 1;
        $perPage = 15;
        $total = $this->model_account_order_for_giga->getPurchaseOrderTotal($param);
        $result = $this->model_account_order_for_giga->getPurchaseOrderDetails($param,$page,$perPage);

        foreach($result as $key => $value){

            //订单中的保证金产品
            $arrMarginProduct = $this->model_account_product_quotes_margin->getMarginProductByOrderID($value['order_id']);

            //计算标签
            $sku_details = explode(',',$value['sku']);
            $qty_details = explode(',',$value['qty']);
            foreach($sku_details as $k => $v){
                $tmp[$k]['sku'] = $v;
                $tmp[$k]['qty'] = $qty_details[$k];
                $tmp[$k]['sku'] = $sku_details[$k];
                $tag_array = $this->model_catalog_product->getProductSpecificTag($v);
                $tags = array();
                if(isset($tag_array)){
                    foreach ($tag_array as $tag){
                        if(isset($tag['origin_icon']) && !empty($tag['origin_icon'])){
                            //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                            $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                            $tags[] = '<img data-toggle="tooltip"  class="'.$tag['class_style']. '" title="'.$tag['description']. '" style="padding-left: 1px" src="'.$img_url.'">';
                        }
                    }
                }
                $tmp[$k]['tag'] = $tags;

                //是否为现货保证金产品
                $is_margin  = array_key_exists($v, $arrMarginProduct) ? 1 : 0;
                $tip_margin = '';
                $url_margin = '';
                if ($is_margin) {
                    $tip_margin = 'Click to view the margin agreement details for agreement ID ' . $arrMarginProduct[$v]['margin_agreement_id'] . '.';
                    $url_margin = $this->url->link('account/product_quotes/margin/detail_list', 'id=' . $arrMarginProduct[$v]['margin_id'], true);
                }
                $tmp[$k]['is_margin']  = $is_margin;
                $tmp[$k]['tip_margin'] = $tip_margin;
                $tmp[$k]['url_margin'] = $url_margin;
            }
            $result[$key]['view'] = $this->url->link('account/order_for_giga/purchaseOrderInfo', 'order_id=' . $value['order_id'], true);
            $result[$key]['item_code_list'] = $tmp;
            $result[$key]['total'] = $this->currency->formatCurrencyPrice($result[$key]['total'],session('currency'));
            unset($tmp);
        }
        $data['orders'] = $result;
        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $perPage;
        $pagination->url = $this->url->link('account/order_for_giga' . $url, '&page={page}', true);
        $data['pagination'] = $pagination->render();
        $url_sort = str_replace('&amp;', '&', $this->url->link('account/order_for_giga' . $url_sort, '&page='.$page, true));
        $data['url_sort'] = $url_sort;


        $data['results'] = sprintf($this->language->get('text_pagination'),($total) ? (($page - 1) * $perPage) + 1 : 0, ((($page - 1) * $perPage) > ($total - $perPage)) ? $total : ((($page - 1) * $perPage) + $perPage), $total, ceil($total / $perPage) );

		$data['continue'] = $this->url->link('account/account', '', true);

		$data['header'] = $this->load->controller('common/header_for_giga');
        $data['query'] = $this->url->link('extension/payment/umf_pay/query', '', true);
//        if(isset($this->session->data['umf_payment'])){
//            $data['unset_payment'] = $this->url->link('extension/payment/umf_pay/unsetPayment', '', true);
//            $data['umf_payment'] = session('umf_payment');
//        }
		$this->response->setOutput($this->load->view('account/order_list_for_giga', $data));
	}

    /**
     * [purchaseOrderFilterByCsv description] 销售订单根据条件搜索导出
     */
	public function  purchaseOrderFilterByCsv(){

        $param = [];
        if (isset($this->request->get['filter_orderDate_from']) && $this->request->get['filter_orderDate_from'] !='') {
            $param['filter_orderDate_from'] = $this->request->get['filter_orderDate_from'];
        }
        if (isset($this->request->get['filter_orderDate_to']) && $this->request->get['filter_orderDate_to'] != ''){
            $param['filter_orderDate_to'] = $this->request->get['filter_orderDate_to'];
        }

        if (isset($this->request->get['filter_orderId'])) {
            $param['filter_orderId'] = $this->request->get['filter_orderId'];
        }
        if (isset($this->request->get['filter_item_code'])) {
            $param['filter_item_code'] = $this->request->get['filter_item_code'];
        }
        $this->load->model('account/order_for_giga');
        $results = $this->model_account_order_for_giga->getPurchaseOrderFilterData($param);

        // Download
        $fileName = 'OrderList' . date('Ymd') . '.csv';
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        echo chr(239) . chr(187) . chr(191);
        $fp = fopen('php://output', 'a');
        $header = [
            'Purchase Order ID',
            'Supplier',
            'Item Code',
            'Product Name',
            'Purchase Quantity',
            'Unit Price',
            'Transaction Fee',
            'Total Amount',
            'Payment Method',
            'Purchase Date',
        ];
        fputcsv($fp, $header);

        if(empty($results)){
            fputcsv($fp, ['No records.']);
        }
        $totalPurchaseQty = 0;
        $totalAmount = 0;
        foreach ($results as $result) {
            $content = [
                $result['Purchase Order ID'],
                $result['Supplier'],
                $result['Item Code'],
                $result['Product Name'],
                $result['Purchase Quantity'],
                $result['Unit Price'],
                0,
                $result['Total Amount'],
                'Line Of Credit',
                $result['Purchase Date'],
            ];
            $totalPurchaseQty = $totalPurchaseQty + $result['Purchase Quantity'];
            $totalAmount = $totalAmount + $result['Total Amount'];
            fputcsv($fp, $content);
        }
        $content = [''];
        fputcsv($fp, $content);
        $content = ['','','','Total Purchase Quantity:',$totalPurchaseQty,'','Total Amount:',$totalAmount];
        fputcsv($fp, $content);
        $meta = stream_get_meta_data($fp);
        if (!$meta['seekable']) {
            $new_data = fopen('php://temp', 'r+');
            stream_copy_to_stream($fp, $new_data);
            rewind($new_data);
            $fp = $new_data;
        } else {
            rewind($fp);
        }
        $output = stream_get_contents($fp);
        fclose($fp);
        return $output;

//        $this->load->model('tool/csv');
//        $filename = 'PurchaseReport'.date('Ymd',time()).'.csv';
//        $this->model_tool_csv->getPurchaseOrderFilterCsv($filename,$result);

    }

    /**
     * [purchaseOrderInfo description]
     * @throws Exception
     */
    public function purchaseOrderInfo(){
	    $country_id = $this->customer->getCountryId();
        $this->load->language('account/order');
        $this->document->setTitle('Purchase Order Details');
        if (isset($this->request->get['order_id'])) {
            $order_id = $this->request->get['order_id'];
        } else {
            $order_id = 0;
        }
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/order/purchaseOrderInfo', 'order_id=' . $order_id, true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $this->load->model('account/product_quotes/margin');
        $this->load->model('account/order_for_giga');
        $order_info = $this->model_account_order_for_giga->getOrder($order_id);

        $preOrderId = $this->model_account_order_for_giga->getPreOrderIds($order_id);

        if($preOrderId['po_number']){
            $data['pre_order_id'] = $this->url->link('account/order_for_giga/purchaseOrderInfo', 'order_id=' . $preOrderId['po_number'], true);
        }

        $nextOrderId = $this->model_account_order_for_giga->getNextOrderIds($order_id);

        if($nextOrderId['po_number']){
            $data['next_order_id'] = $this->url->link('account/order_for_giga/purchaseOrderInfo', 'order_id=' . $nextOrderId['po_number'], true);
        }

        //end xxli
        if ($order_info) {
            //该订单中的现货保证金产品
            $arrMarginProduct = $this->model_account_product_quotes_margin->getMarginProductByOrderID($order_id);

            if (isset($this->session->data['error'])) {
                $data['error_warning'] = session('error');

                $this->session->remove('error');
            } else {
                $data['error_warning'] = '';
            }

            if (isset($this->session->data['success'])) {
                $data['success'] = session('success');

                $this->session->remove('success');
            } else {
                $data['success'] = '';
            }

            $data['order_id'] = $this->request->get['order_id'];

            $data['date_added'] = date($this->language->get('datetime_format'), strtotime($order_info['date_added']));

            $this->load->model('catalog/product');
            $this->load->model('tool/upload');
            $this->load->model('tool/image');


            // Products
            $data['products'] = array();
            $products = $this->model_account_order_for_giga->getOrderProducts($this->request->get['order_id']);
            //获取 sub-toal
            $sub_total = 0;
            $supplier_code = '';
            foreach ($products as $product) {
                $sub_total = $sub_total + $product['org_price'] * $product['receive_qty'];
                $supplier_code = $product['supplier_code'];

                //是否为现货保证金产品
                $is_margin  = array_key_exists($product['product_id'], $arrMarginProduct) ? 1 : 0;
                $tip_margin = '';
                $url_margin = '';
                if ($is_margin) {
                    $tip_margin = 'Click to view the margin agreement details for agreement ID ' . $arrMarginProduct[$product['product_id']]['margin_agreement_id'] . '.';
                    $url_margin = $this->url->link('account/product_quotes/margin/detail_list', 'id=' . $arrMarginProduct[$product['product_id']]['margin_id'], true);
                }

                $data['products'][] = array(
                    'sku_code'     => $product['sku_code'],
                    'price'     => $this->currency->formatCurrencyPrice($product['org_price'],session('currency')),
                    'quantity'     => $product['receive_qty'],
                    'product_name' => $product['product_name'],
                    'total'     => $this->currency->formatCurrencyPrice($product['detail_amount'],session('currency')),
                    'poundage'     => $this->currency->formatCurrencyPrice(0,session('currency')),
                    'color'     => $product['color'],
                    'length'     => $product['length'],
                    'width'     => $product['width'],
                    'height'     => $product['height'],
                    'weight'     => $product['weight'],
                    'is_margin'  => $is_margin,
                    'tip_margin' => $tip_margin,
                    'url_margin' => $url_margin,
                );
            }

            $data['sub_total']  = $this->currency->formatCurrencyPrice($sub_total, session('currency'));
            $data['total_transaction']  = $this->currency->formatCurrencyPrice(0, session('currency'));
            $data['total_price']  = $this->currency->formatCurrencyPrice($sub_total, session('currency'));
            $data['supplier_code']  = $supplier_code;

            $data['comment'] = htmlspecialchars(nl2br(''));

            // History
            $data['histories'] = array();

            $results = $this->model_account_order_for_giga->getOrderHistories($this->request->get['order_id']);

            foreach ($results as $result) {
                $data['histories'][] = array(
                    'date_added' => date($this->language->get('datetime_format'), strtotime($result['date_added'])),
                    'status'     => 'Completed',
                    'comment'    => ''
                );
            }

            $data['continue'] = $this->url->link('account/order_for_giga', '', true);
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['header'] = $this->load->controller('common/header_for_giga');
            $this->response->setOutput($this->load->view('account/purchase_order_info_for_giga', $data));
        } else {
            return new \Framework\Action\Action('error/not_found');
        }
    }


	public function info() {
		$this->load->language('account/order');
		if (isset($this->request->get['order_id'])) {
			$order_id = $this->request->get['order_id'];
		} else {
			$order_id = 0;
		}

		if (!$this->customer->isLogged()) {
			session()->set('redirect', $this->url->link('account/order/info', 'order_id=' . $order_id, true));

			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->model('account/order');

		$order_info = $this->model_account_order->getOrder($order_id);
        if($order_info['order_status_id']==OcOrderStatus::COMPLETED){
            $data['can_review'] = true;
        }else{
            $data['can_review'] = false;
        }
        //end xxli
		if ($order_info) {
			$this->document->setTitle($this->language->get('text_order'));

			$url = '';

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$data['breadcrumbs'] = array();

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home')
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_account'),
				'href' => $this->url->link('account/account', '', true)
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('account/order', $url, true)
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_order'),
				'href' => $this->url->link('account/order/info', 'order_id=' . $this->request->get['order_id'] . $url, true)
			);


            // marketplace
            $this->load->model('account/customerpartner');
            $data['button_order_detail'] = $this->language->get('button_order_detail');
            $data['text_tracking'] = $this->language->get('text_tracking');
            $data['module_marketplace_status'] = $this->config->get('module_marketplace_status');
            // marketplace

			if (isset($this->session->data['error'])) {
				$data['error_warning'] = session('error');

				$this->session->remove('error');
			} else {
				$data['error_warning'] = '';
			}

			if (isset($this->session->data['success'])) {
				$data['success'] = session('success');

				$this->session->remove('success');
			} else {
				$data['success'] = '';
			}

			if ($order_info['invoice_no']) {
				$data['invoice_no'] = $order_info['invoice_prefix'] . $order_info['invoice_no'];
			} else {
				$data['invoice_no'] = '';
			}

			$data['order_id'] = $this->request->get['order_id'];
			$data['date_added'] = date($this->language->get('datetime_format'), strtotime($order_info['date_added']));

			if ($order_info['payment_address_format']) {
				$format = $order_info['payment_address_format'];
			} else {
				$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
			}

			$find = array(
				'{firstname}',
				'{lastname}',
				'{company}',
				'{address_1}',
				'{address_2}',
				'{city}',
				'{postcode}',
				'{zone}',
				'{zone_code}',
				'{country}'
			);

			$replace = array(
				'firstname' => $order_info['payment_firstname'],
				'lastname'  => $order_info['payment_lastname'],
				'company'   => $order_info['payment_company'],
				'address_1' => $order_info['payment_address_1'],
				'address_2' => $order_info['payment_address_2'],
				'city'      => $order_info['payment_city'],
				'postcode'  => $order_info['payment_postcode'],
				'zone'      => $order_info['payment_zone'],
				'zone_code' => $order_info['payment_zone_code'],
				'country'   => $order_info['payment_country']
			);

			$data['payment_address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

			$data['payment_method'] = $order_info['payment_method'];

            if($order_info['payment_method'] == 'Line Of Credit' && $this->customer->getAdditionalFlag() == 1){
                $data['payment_method'] = 'Line Of Credit(+1%)';
            }

			if ($order_info['shipping_address_format']) {
				$format = $order_info['shipping_address_format'];
			} else {
				$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
			}

			$find = array(
				'{firstname}',
				'{lastname}',
				'{company}',
				'{address_1}',
				'{address_2}',
				'{city}',
				'{postcode}',
				'{zone}',
				'{zone_code}',
				'{country}'
			);

			$replace = array(
				'firstname' => $order_info['shipping_firstname'],
				'lastname'  => $order_info['shipping_lastname'],
				'company'   => $order_info['shipping_company'],
				'address_1' => $order_info['shipping_address_1'],
				'address_2' => $order_info['shipping_address_2'],
				'city'      => $order_info['shipping_city'],
				'postcode'  => $order_info['shipping_postcode'],
				'zone'      => $order_info['shipping_zone'],
				'zone_code' => $order_info['shipping_zone_code'],
				'country'   => $order_info['shipping_country']
			);

			$data['shipping_address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

			$data['shipping_method'] = $order_info['shipping_method'];

			$this->load->model('catalog/product');
			$this->load->model('tool/upload');
            $this->load->model('tool/image');

			// Products
			$data['products'] = array();

			$products = $this->model_account_order->getOrderProducts($this->request->get['order_id']);

			foreach ($products as $product) {
				$option_data = array();

				$options = $this->model_account_order->getOrderOptions($this->request->get['order_id'], $product['order_product_id']);

				foreach ($options as $option) {
					if ($option['type'] != 'file') {
						$value = $option['value'];
					} else {
						$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

						if ($upload_info) {
							$value = $upload_info['name'];
						} else {
							$value = '';
						}
					}

					$option_data[] = array(
						'name'  => $option['name'],
						'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
					);
				}

				$product_info = $this->model_catalog_product->getProductForOrderHistory($product['product_id']);

				//add by xxli 获取产品的评价信息

                $customerName = $this->model_account_order->getCustomerName($product_info['customer_id']);

                $reviewResult = $this->model_account_order->getReviewInfo($this->request->get['order_id'],$product['order_product_id']);


                //end

				if ($product_info) {
					$reorder = $this->url->link('account/order/reorder', 'order_id=' . $order_id . '&order_product_id=' . $product['order_product_id'], true);
				} else {
					$reorder = '';
				}

                $tag_array = $this->model_catalog_product->getProductSpecificTag($product['product_id']);
                $tags = array();
                if(isset($tag_array)){
                    foreach ($tag_array as $tag){
                        if(isset($tag['origin_icon']) && !empty($tag['origin_icon'])){
                            //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                            $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                            $tags[] = '<img data-toggle="tooltip"  class="'.$tag['class_style']. '"   title="'.$tag['description']. '" style="padding-left: 1px" src="'.$img_url.'">';
                        }
                    }
                }

				$data['products'][] = array(
					'name'     => $product['name'],
					'model'    => $product['model'],
					'option'   => $option_data,
					'quantity' => $product['quantity'],
					'price'    => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
					'total'    => $this->currency->format(round($product['price'],2)*$product['quantity'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $order_info['currency_code'], $order_info['currency_value']),
					'reorder'  => $reorder,
                           'mpn' => $product_info['mpn'],
                           'sku' => $product_info['sku'],
                           // marketplace
                          'order_detail'   => $this->url->link('account/customerpartner/order_detail', 'order_id=' . $order_info['order_id'] . '&product_id=' . $product['product_id'], true),
                          'order_id' => $product['order_id'],
                          'product_id' => $product['product_id'],
                           // marketplace

					'return'   => $this->url->link('account/rma/purchaseorderrma/add', 'order_id=' . $order_info['order_id'] . '&product_id=' . $product['product_id'], true),

                    // add by xxli reviewInfo
                    'customerName' => $customerName['screenname'],
                    'order_product_id' =>$product['order_product_id'],
                    'customer_id' =>$product_info['customer_id'],
                    'tag'       => $tags
                    // end xxli
				);
			}

			// Voucher
			$data['vouchers'] = array();

			$vouchers = $this->model_account_order->getOrderVouchers($this->request->get['order_id']);

			foreach ($vouchers as $voucher) {
				$data['vouchers'][] = array(
					'description' => $voucher['description'],
					'amount'      => $this->currency->format($voucher['amount'], $order_info['currency_code'], $order_info['currency_value'])
				);
			}

			// Totals
			$data['totals'] = array();

			$totals = $this->model_account_order->getOrderTotals($this->request->get['order_id']);

			foreach ($totals as $total) {
                if($total['title']=='Quote Discount'){
                    $data['totals'][] = array(
                        'title' => 'Total Quote Discount',
                        'text' => $this->currency->format($total['value'], session('currency')),
                    );
                }elseif ($total['title']=='Service Fee'){
                    $data['totals'][] = array(
                        'title' => 'Total Service Fee',
                        'text' => $this->currency->format($total['value'], session('currency')),
                    );
                }elseif ($total['title']=='Poundage'){
                    $data['totals'][] = array(
                        'title' => 'Total Transaction Fee',
                        'text' => $this->currency->format($total['value'], session('currency')),
                    );
                }else {
                    $data['totals'][] = array(
                        'title' => $total['title'],
                        'text' => $this->currency->format($total['value'], session('currency')),
                    );
                }
			}

			$data['comment'] = htmlspecialchars(nl2br($order_info['comment']));

			// History
			$data['histories'] = array();

			$results = $this->model_account_order->getOrderHistories($this->request->get['order_id']);

			foreach ($results as $result) {
				$data['histories'][] = array(
					'date_added' => date($this->language->get('datetime_format'), strtotime($result['date_added'])),
					'status'     => $result['status'],
					'comment'    => $result['notify'] ? nl2br($result['comment']) : ''
				);
			}

			$data['continue'] = $this->url->link('account/order', '', true);
			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');


			$this->response->setOutput($this->load->view('account/order_info_for_giga', $data));
		} else {
			return new \Framework\Action\Action('error/not_found');
		}
	}

	public function reorder() {
		$this->load->language('account/order');

		if (isset($this->request->get['order_id'])) {

			$order_id = $this->request->get['order_id'];
		} else {
			$order_id = 0;
		}

		$this->load->model('account/order');

		$order_info = $this->model_account_order->getOrder($order_id);

		if ($order_info) {
			if (isset($this->request->get['order_product_id'])) {
				$order_product_id = $this->request->get['order_product_id'];
			} else {
				$order_product_id = 0;
			}

			$order_product_info = $this->model_account_order->getOrderProduct($order_id, $order_product_id);

			if ($order_product_info) {
				$this->load->model('catalog/product');

				$product_info = $this->model_catalog_product->getProduct($order_product_info['product_id']);

				if ($product_info) {
					$option_data = array();

					$order_options = $this->model_account_order->getOrderOptions($order_product_info['order_id'], $order_product_id);

					foreach ($order_options as $order_option) {
						if ($order_option['type'] == 'select' || $order_option['type'] == 'radio' || $order_option['type'] == 'image') {
							$option_data[$order_option['product_option_id']] = $order_option['product_option_value_id'];
						} elseif ($order_option['type'] == 'checkbox') {
							$option_data[$order_option['product_option_id']][] = $order_option['product_option_value_id'];
						} elseif ($order_option['type'] == 'text' || $order_option['type'] == 'textarea' || $order_option['type'] == 'date' || $order_option['type'] == 'datetime' || $order_option['type'] == 'time') {
							$option_data[$order_option['product_option_id']] = $order_option['value'];
						} elseif ($order_option['type'] == 'file') {
							$option_data[$order_option['product_option_id']] = $this->encryption->encrypt($this->config->get('config_encryption'), $order_option['value']);
						}
					}

					$this->cart->add($order_product_info['product_id'], $order_product_info['quantity'], $option_data);

					session()->set('success', sprintf($this->language->get('text_success'), $this->url->link('product/product', 'product_id=' . $product_info['product_id']), $product_info['name'], $this->url->link('checkout/cart')));

					$this->session->remove('shipping_method');
					$this->session->remove('shipping_methods');
					$this->session->remove('payment_method');
					$this->session->remove('payment_methods');
				} else {
					session()->set('error', sprintf($this->language->get('error_reorder'), $order_product_info['name']));
				}
			}
		}

		$this->response->redirect($this->url->link('account/order/purchaseOrderInfo', 'order_id=' . $order_id));
	}

	//add by xxli
    public function write() {
        $this->load->language('product/product');
        $this->load->model('account/order');
        $json = array();

        if (request()->isMethod('POST')) {
            if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 25)) {
                $json['error'] = $this->language->get('error_name');
            }

            if ((utf8_strlen($this->request->post['text']) < 25) || (utf8_strlen($this->request->post['text']) > 1000)) {
                $json['error'] = $this->language->get('error_text');
            }

            if (empty($this->request->post['rating']) || $this->request->post['rating'] < 0 || $this->request->post['rating'] > 5) {
                $json['error'] = $this->language->get('error_rating');
            }
            if (empty($this->request->post['seller_rating']) || $this->request->post['seller_rating'] < 0 || $this->request->post['seller_rating'] > 5) {
                $json['error'] = $this->language->get('error_rating');
            }
            $order_info = $this->model_account_order->getOrder($this->request->post['order_id']);
            $run_id = time();
            $end_date = strtotime("+3 month",strtotime($order_info['date_added']));
            if($run_id>$end_date){
                $json['error'] = 'Review orders for three months only.';
            }
            if(isset($this->request->post['review_id']) && $this->request->post['review_id']!='') {
                if($this->request->post['buyer_review_number']>=3){
                    $json['error'] = 'Review can only be modified 3 times.';
                }
            }
            // Captcha
            if ($this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('review', (array)$this->config->get('config_captcha_page'))) {
                $captcha = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha') . '/validate');

                if ($captcha) {
                    $json['error'] = $captcha;
                }
            }

            if (!isset($json['error'])) {
                $this->load->model('catalog/review');
                if(isset($this->request->post['review_id']) && $this->request->post['review_id']!='') {
                    $review_id=$this->request->post['review_id'];
                    $this->model_account_order->editReview($this->request->post['product_id'],$review_id, $this->request->post);
                    //上传附件
                    $files = $this->request->files;
                    $run_id = time();
                    $index=0;
                    foreach ($files as $key => $result) {
                        if($result['error'] == '0'){
                            $index++;
                            if (!file_exists(DIR_REVIEW_FILE)) {
                                mkdir(DIR_REVIEW_FILE, 0777, true);
                            }
                            if (!file_exists(DIR_REVIEW_FILE . $review_id)) {
                                mkdir(DIR_REVIEW_FILE . $review_id, 0777, true);
                            }
                            $splitStr = explode('.', $result['name']);
                            $file_type = $splitStr[count($splitStr) - 1];
                            $file_path = DIR_REVIEW_FILE . $review_id . "/" . $run_id . '_'.$index . '.' . $file_type;
                            move_uploaded_file($result['tmp_name'], $file_path);
                            $filePath = $review_id . "/" . $run_id . '_' . $index . '.' . $file_type;
                            $fileName = $run_id . '_' . $index . '.' . $file_type;
                            $this->model_account_order->addReviewFile($review_id,$filePath,$fileName,$this->customer->getId());
                        }
                    }
                }else{
                    $review_id = $this->model_account_order->addReview($this->request->post['product_id'], $this->request->post);
                    //上传附件
                    $files = $this->request->files;
                    $run_id = time();
                    $index=0;
                    foreach ($files as $key => $result) {
                        if($result['error'] == '0'){
                            $index++;
                            if (!file_exists(DIR_REVIEW_FILE)) {
                                mkdir(DIR_REVIEW_FILE, 0777, true);
                            }
                            if (!file_exists(DIR_REVIEW_FILE . $review_id)) {
                                mkdir(DIR_REVIEW_FILE . $review_id, 0777, true);
                            }
                            $splitStr = explode('.', $result['name']);
                            $file_type = $splitStr[count($splitStr) - 1];
                            $file_path = DIR_REVIEW_FILE . $review_id . "/" . $run_id . '_'.$index . '.' . $file_type;
                            move_uploaded_file($result['tmp_name'], $file_path);
                            $filePath = $review_id . "/" . $run_id . '_' . $index . '.' . $file_type;
                            $fileName = $run_id . '_' . $index . '.' . $file_type;
                            $this->model_account_order->addReviewFile($review_id,$filePath,$fileName,$this->customer->getId());
                        }
                    }
                }

                $json['success'] = $this->language->get('text_success');
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }


    public function reviewInfo() {
        if (!$this->customer->isLogged()) {
            $json['error'] = true;
            $json['url'] =  $this->url->link('account/login');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
        }

        $firstName = $this->customer->getFirstname();
        $lastName = $this->customer->getLastname();
        $this->load->model('account/order');
        $order_id = $this->request->request['orderId'];
        $order_product_id = $this->request->request['orderProductId'];
        $product_id = $this->request->request['productId'];
        $reviewResult = $this->model_account_order->getReviewInfo($order_id,$order_product_id);
        $nickName = $this->customer->getNickName();
        if($reviewResult){
            $json['edit'] = true;
            $json['review_id'] = $reviewResult['review_id'];
            $json['author'] = $nickName;
            $json['text'] = $reviewResult['text'];
            $json['rating'] = $reviewResult['rating'];
            $json['seller_rating'] = $reviewResult['seller_rating'];
            $json['product_id'] = $product_id;
            $json['order_id'] = $order_id;
            $json['order_product_id'] = $order_product_id;
            $json['buyer_review_number'] = $reviewResult['buyer_review_number'];
            $json['seller_review_number'] = $reviewResult['seller_review_number'];
            $files = $this->model_account_order->getReviewFile($reviewResult['review_id']);

            $index = 0;
            foreach ($files as $file){
                $json['img'.$index] = 'storage/reviewFiles/'.$file['path'];
                $index++;
            }
        }else{
            $json['add'] = true;
            $json['author'] = $nickName;
            $json['product_id'] = $product_id;
            $json['order_id'] = $order_id;
            $json['order_product_id'] = $order_product_id;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function deleteFiles(){
        $this->load->model('account/order');
        $filePath = $this->request->request['path'];
        $this->model_account_order->deleteFiles($filePath);
        //删除服务器文件
        unlink(DIR_REVIEW_FILE.$this->request->request['path']);
    }

    private function getOrderPoundage($mapProduct)
    {
        $new_res = $this->orm->table(DB_PREFIX . 'order as oco')->
        leftJoin(DB_PREFIX . 'order_product as op', function ($join) {
            $join->on('op.order_id', '=', 'oco.order_id');
        })->
        leftJoin('tb_sys_order_associated as as', function ($join) {
            $join->on('as.order_id', '=', 'oco.order_id')->on('as.product_id', '=', 'op.product_id');
        })->
        leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'as.sales_order_id')->

        leftJoin(DB_PREFIX . 'product_quote as pq', function ($join) {
            $join->on('pq.order_id', '=', 'oco.order_id')->on('pq.product_id', '=', 'op.product_id');
        })
            //->leftJoin(DB_PREFIX.'yzc_rma_order as r',function ($join){
            //    $join->on('r.order_id','=','as.order_id')->on('r.buyer_id','=','oco.customer_id');
            //})
            //->leftJoin(DB_PREFIX.'yzc_rma_order_product as rp',function ($join){
            //    $join->on('rp.rma_id','=','r.id')->on('rp.product_id','=','as.product_id');
            //})
            ->where($mapProduct)->groupBy('as.order_id', 'as.product_id')->select('pq.price as pq_price', 'op.poundage', 'op.service_fee')
            ->selectRaw('group_concat( distinct o.order_id) as order_id_list,group_concat( distinct o.id) as oid_list')->first();
        $new_res = obj2array($new_res);
        return $new_res;
    }

    private function getOrderRma($mapRma){
        $rma_info = $this->orm->table(DB_PREFIX.'yzc_rma_order as r')
            ->leftJoin(DB_PREFIX.'yzc_rma_order_product as rp','rp.rma_id','=','r.id')->where($mapRma)->
            selectRaw('group_concat( distinct r.rma_order_id) as rma_id_list,group_concat( distinct r.id) as rid_list')->first();
        $rma_info = obj2array($rma_info);
        return $rma_info;
    }
}
