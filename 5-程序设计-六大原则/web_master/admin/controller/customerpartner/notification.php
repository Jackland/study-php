<?php

/**
 * @property ModelCustomerpartnerNotification $model_customerpartner_notification
 * @property ModelLocalisationOrderStatus $model_localisation_order_status
 * @property ModelSaleOrder $model_order_status
 * @property ModelSaleReturn $model_order_return
 * @property ModelSettingSetting $model_setting_setting
 */
class ControllerCustomerpartnerNotification extends Controller {

	public function index() {

		$data = array();

		$data = array_merge($data, $this->load->language('customerpartner/notification'));

		$this->load->model('customerpartner/notification');

		$this->load->model('localisation/order_status');

		$this->load->model('sale/order');

		$this->load->model('sale/return');

		$this->document->setTitle($data['heading_title']);

		$data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $data['text_home'],
            'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true),
            'separator' => false
        );

        $data['breadcrumbs'][] = array(
            'text' => $data['heading_title'],
            'href' => $this->url->link('customerpartner/notification', 'user_token=' . session('user_token'), true),
            'separator' => false
        );

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = session('success');
			$this->session->remove('success');
		} else {
			$data['success'] = '';
		}

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		foreach ($data['order_statuses'] as $key => $value) {
		  $data['order_statuses'][$key]['total'] = $this->model_customerpartner_notification->getTotalActivity(array($value['order_status_id']));
		}

		$data['notification_filter'] = $this->config->get('marketplace_notification_filter');

		$data['return_total'] = $this->model_customerpartner_notification->getTotalActivity(array('return'));

		$data['selected'] = array();

		if (isset($this->request->get['options']) && $this->request->get['options']) {
				$data['selected'] = explode(',',$this->request->get['options']);

				foreach ($data['selected'] as $key => $value) {
					$data['selected'][$key] = $this->db->escape($value);
				}
		}

		$data['all_notifications'] = $this->model_customerpartner_notification->getActivityCount();

		$all_notifications_total = $this->model_customerpartner_notification->getTotalActivity($data['selected']);

		$data['seller_notifications'] = array();

		$seller_notifications = $this->model_customerpartner_notification->getActivity($data['selected']);

		if ($seller_notifications) {
			foreach ($seller_notifications as $key => $seller_notification) {

				$date_diff = (array)(new DateTime($seller_notification['date_added']))->diff(new DateTime());

				if (isset($date_diff['y']) && $date_diff['y']) {
					$seller_notification['date_added'] = $date_diff['y'].' year(s)';
				} elseif (isset($date_diff['m']) && $date_diff['m']) {
					$seller_notification['date_added'] = $date_diff['m'].' month(s)';
				} elseif (isset($date_diff['d']) && $date_diff['d']) {
					$seller_notification['date_added'] = $date_diff['d'].' day(s)';
				} elseif (isset($date_diff['h']) && $date_diff['h']) {
					$seller_notification['date_added'] = $date_diff['h'].' hour(s)';
				} elseif (isset($date_diff['i']) && $date_diff['i']) {
					$seller_notification['date_added'] = $date_diff['i'].' minute(s)';
				}else {
					$seller_notification['date_added'] = $date_diff['s'].' second(s)';
				}

				if ($seller_notification['key'] == 'order_account') {
					$seller_notification['data'] = json_decode($seller_notification['data'],1);

					$data['seller_notifications'][] = sprintf($this->language->get('text_order_add'),session('user_token'),$seller_notification['data']['order_id'],$seller_notification['data']['order_id'],$seller_notification['data']['name'],$seller_notification['date_added']);
				} elseif ($seller_notification['key'] == 'return_account') {
					$seller_notification['data'] = json_decode($seller_notification['data'],1);

					$order_id = $this->model_customerpartner_notification->getReturnOrderId($seller_notification['data']['return_id']);
					if ($order_id) {
						$data['seller_notifications'][] = sprintf($this->language->get('text_order_return'),$seller_notification['data']['name'],session('user_token'),$order_id['order_id'],$seller_notification['data']['return_id'],$order_id['product'],$seller_notification['date_added']);
					}
				} elseif ($seller_notification['key'] == 'order_status') {
					$seller_notification['data'] = json_decode($seller_notification['data'],1);

					$status = $this->model_localisation_order_status->getOrderStatus($seller_notification['data']['status']);
					if ($status) {
						if (empty($data['selected']) || in_array('all',$data['selected'])) {
						  $data['seller_notifications'][] = sprintf($this->language->get('text_order_status'),session('user_token'),$seller_notification['data']['order_id'],$seller_notification['data']['order_id'],$status['name'],$seller_notification['date_added']);
						} else {
						  if (is_array($data['notification_filter']) && $data['notification_filter']) {
						    foreach ($data['notification_filter'] as $key => $value) {
						      if (in_array($value, $data['selected']) && $seller_notification['data']['status'] == $value) {
						        $data['seller_notifications'][] = sprintf($this->language->get('text_order_status'),session('user_token'),$seller_notification['data']['order_id'],$seller_notification['data']['order_id'],$status['name'],$seller_notification['date_added']);
						      }
						    }
						  }
						}
					}
				}
			}
		}

		$data['seller_product_reviews'] = array();

		$seller_product_reviews = $this->model_customerpartner_notification->getProductActivity();

		$seller_product_reviews_total = $this->model_customerpartner_notification->getProductActivityTotal();

		if ($seller_product_reviews) {
			foreach ($seller_product_reviews as $key => $seller_product_review) {

				$date_diff = (array)(new DateTime($seller_product_review['date_added']))->diff(new DateTime());

				if (isset($date_diff['y']) && $date_diff['y']) {
					$seller_product_review['date_added'] = $date_diff['y'].' year(s)';
				} elseif (isset($date_diff['m']) && $date_diff['m']) {
					$seller_product_review['date_added'] = $date_diff['m'].' month(s)';
				} elseif (isset($date_diff['d']) && $date_diff['d']) {
					$seller_product_review['date_added'] = $date_diff['d'].' day(s)';
				} elseif (isset($date_diff['h']) && $date_diff['h']) {
					$seller_product_review['date_added'] = $date_diff['h'].' hour(s)';
				} elseif (isset($date_diff['i']) && $date_diff['i']) {
					$seller_product_review['date_added'] = $date_diff['i'].' minute(s)';
				}else {
					$seller_product_review['date_added'] = $date_diff['s'].' second(s)';
				}

				$seller_product_review['data'] = json_decode($seller_product_review['data'],1);
				if ($seller_product_review['key'] == 'product_review') {
					$data['seller_product_reviews'][] = sprintf($this->language->get('text_product_review'),$seller_product_review['id'],$seller_product_review['data']['author'],session('user_token'),$seller_product_review['data']['product_name'],$seller_product_review['data']['product_name'],$seller_product_review['date_added']);
				} elseif($seller_product_review['key'] == 'product_stock') {
					$data['seller_product_reviews'][] = sprintf($this->language->get('text_product_stock'),session('user_token'),$seller_product_review['data']['product_name'],$seller_product_review['data']['product_name'],$seller_product_review['date_added']);
				} elseif ($seller_product_review['key'] == 'product_approve') {
					$data['seller_product_reviews'][] = sprintf($this->language->get('text_product_approve'),session('user_token'),$seller_product_review['data']['product_name'],$seller_product_review['data']['product_name'],$seller_product_review['date_added']);
				}
			}
		}

		$data['seller_reviews'] = array();

		$seller_reviews = $this->model_customerpartner_notification->getSellerReviews();

		$seller_reviews_total = $this->model_customerpartner_notification->getSellerReviewsTotal();

		if ($seller_reviews) {
			foreach ($seller_reviews as $key => $seller_review) {

				$date_diff = (array)(new DateTime($seller_review['createdate']))->diff(new DateTime());

				if (isset($date_diff['y']) && $date_diff['y']) {
					$seller_review['createdate'] = $date_diff['y'].' year(s)';
				} elseif (isset($date_diff['m']) && $date_diff['m']) {
					$seller_review['createdate'] = $date_diff['m'].' month(s)';
				} elseif (isset($date_diff['d']) && $date_diff['d']) {
					$seller_review['createdate'] = $date_diff['d'].' day(s)';
				} elseif (isset($date_diff['h']) && $date_diff['h']) {
					$seller_review['createdate'] = $date_diff['h'].' hour(s)';
				} elseif (isset($date_diff['i']) && $date_diff['i']) {
					$seller_review['createdate'] = $date_diff['i'].' minute(s)';
				}else {
					$seller_review['createdate'] = $date_diff['s'].' second(s)';
				}

					$data['seller_reviews'][] = sprintf($this->language->get('text_seller_review'),$seller_review['id'],session('user_token'),$seller_review['name'],$seller_review['name'],$seller_review['createdate']);
			}
		}

		$categories = $this->model_customerpartner_notification->getCategoryActivity();

		$categories_total = $this->model_customerpartner_notification->getCategoryActivityTotal();

		if ($categories) {
		  foreach ($categories as $key => $category) {

		    $date_diff = (array)(new DateTime($category['date_added']))->diff(new DateTime());

		    if (isset($date_diff['y']) && $date_diff['y']) {
		      $category['date_added'] = $date_diff['y'].' year(s)';
		    } elseif (isset($date_diff['m']) && $date_diff['m']) {
		      $category['date_added'] = $date_diff['m'].' month(s)';
		    } elseif (isset($date_diff['d']) && $date_diff['d']) {
		      $category['date_added'] = $date_diff['d'].' day(s)';
		    } elseif (isset($date_diff['h']) && $date_diff['h']) {
		      $category['date_added'] = $date_diff['h'].' hour(s)';
		    } elseif (isset($date_diff['i']) && $date_diff['i']) {
		      $category['date_added'] = $date_diff['i'].' minute(s)';
		    }else {
		      $category['date_added'] = $date_diff['s'].' second(s)';
		    }
		    $category['data'] = json_decode($category['data'],1);

		    if (isset($category['data']['category_name']) && $category['data']['category_name']) {
		      $data['seller_reviews'][] = sprintf($this->language->get('text_category_approve'),session('user_token'),$category['data']['category_name'],$category['date_added']);
		    }
		  }
		}

		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}

		if (isset($this->request->get['page_product'])) {
			$page_product = $this->request->get['page_product'];
		} else {
			$page_product = 1;
		}

		if (isset($this->request->get['page_seller'])) {
			$page_seller = $this->request->get['page_seller'];
		} else {
			$page_seller = 1;
		}

		$data['page'] = $page;

		$url = '';

		if (isset($this->request->get['options'])) {
			$url = '&options='.$this->request->get['options'];
		}

		//Pagination For Order Tab
		$pagination = new Pagination();
		$pagination->total = $all_notifications_total;
		$pagination->page = $page;
		$pagination->limit = 10;
		$pagination->url = $this->url->link('customerpartner/notification','user_token=' . session('user_token').$url.'&page={page}&page_product='.$page_product.'&page_seller='.$page_seller, true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($all_notifications_total) ? (($page - 1) * 10) + 1 : 0, ((($page - 1) * 10) > ($all_notifications_total - 10)) ? $all_notifications_total : ((($page - 1) * 10) + 10), $all_notifications_total, ceil($all_notifications_total / 10));

		//Pagination For Product Tab
		$pagination_product = new Pagination();
		$pagination_product->total = $seller_product_reviews_total;
		$pagination_product->page = $page_product;
		$pagination_product->limit = 10;
		$pagination_product->url = $this->url->link('customerpartner/notification','user_token=' . session('user_token').$url.'&page_product={page}&page='.$page.'&page_seller='.$page_seller . '&tab=product', true);

		$data['pagination_product'] = $pagination_product->render();

		$data['results_product'] = sprintf($this->language->get('text_pagination'), ($seller_product_reviews_total) ? (($page - 1) * 10) + 1 : 0, ((($page - 1) * 10) > ($seller_product_reviews_total - 10)) ? $seller_product_reviews_total : ((($page - 1) * 10) + 10), $seller_product_reviews_total, ceil($seller_product_reviews_total / 10));

		$seller_reviews_total = $seller_reviews_total + $categories_total;

		//Pagination For Seller Tab
		$pagination_seller = new Pagination();
		$pagination_seller->total = $seller_reviews_total;
		$pagination_seller->page = $page_seller;
		$pagination_seller->limit = 10;
		$pagination_seller->url = $this->url->link('customerpartner/notification','user_token=' . session('user_token').$url.'&page_seller={page}&page='.$page.'&page_product='.$page_product . '&tab=seller', true);

		$data['pagination_seller'] = $pagination_seller->render();

		$data['results_seller'] = sprintf($this->language->get('text_pagination'), ($seller_reviews_total) ? (($page - 1) * 10) + 1 : 0, ((($page - 1) * 10) > ($seller_reviews_total - 10)) ? $seller_reviews_total : ((($page - 1) * 10) + 10), $seller_reviews_total, ceil($seller_reviews_total / 10));

		$data['user_token'] = session('user_token');

		$data['back'] = $this->url->link('common/dashboard','user_token=' . session('user_token'), true);

		$data['header'] = $this->load->controller('common/header');

		$data['column_left'] = $this->load->controller('common/column_left');

		$data['footer'] = $this->load->controller('common/footer');

		if (isset($this->request->get['tab']) && $this->request->get['tab']) {
			$data['tab'] = $this->request->get['tab'];
		} else {
			$data['tab'] = 'order';
		}

		$this->load->model('setting/setting');

		$marketplace_notification_viewed = $this->model_customerpartner_notification->getActivityCount() + $seller_product_reviews_total + $seller_reviews_total;

		$this->model_setting_setting->editSetting('marketplace_notification', array('marketplace_notification_viewed' => $marketplace_notification_viewed));

		$this->response->setOutput($this->load->view('customerpartner/notification', $data));
	}

	/**
	 * [notifications is used to get notification view that will be display in the header]
	 * @return [view] [notification view for header]
	 */
	public function notifications()	{

		if ((request()->isMethod('POST'))) {
			if (
				isset($this->session->data['user_token']) &&
				$this->config->get('module_marketplace_status') &&
				$this->db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '".DB_DATABASE."' AND (TABLE_NAME = '".DB_PREFIX."seller_activity' || TABLE_NAME = '".DB_PREFIX."mp_customer_activity')")->row
			) {

				$data = array_merge($this->load->language('customerpartner/notification'));

				$this->load->model('customerpartner/notification');
				$this->load->model('localisation/order_status');
				$this->load->model('sale/order');
				$this->load->model('sale/return');

				//key: order_status   status:2
				$data['processing_status_total'] = $this->model_customerpartner_notification->getTotalActivity(array('2'));

				//key: order_status   status:5
				$data['complete_status_total'] = $this->model_customerpartner_notification->getTotalActivity(array('5'));

				//key: return_account
				$data['return_total'] = $this->model_customerpartner_notification->getTotalActivity(array('return'));

				$data['view_all'] = $this->url->link('customerpartner/notification','user_token='.session('user_token'),true);

				$data['seller_notifications'] = array();
				if(isset($this->request->server['HTTP_REFERER']) && $this->request->server['HTTP_REFERER'] && strpos($this->request->server['HTTP_REFERER'],'module/journal2') !== false) {
					$data['journal_page'] = true;
				}
				$seller_notifications = $this->model_customerpartner_notification->getActivity(array(),3);

				if ($seller_notifications) {
					foreach ($seller_notifications as $key => $seller_notification) {

						$date_diff = (array)(new DateTime($seller_notification['date_added']))->diff(new DateTime());

						if (isset($date_diff['y']) && $date_diff['y']) {
							$seller_notification['date_added'] = $date_diff['y'].' year(s)';
						} elseif (isset($date_diff['m']) && $date_diff['m']) {
							$seller_notification['date_added'] = $date_diff['m'].' month(s)';
						} elseif (isset($date_diff['d']) && $date_diff['d']) {
							$seller_notification['date_added'] = $date_diff['d'].' day(s)';
						} elseif (isset($date_diff['h']) && $date_diff['h']) {
							$seller_notification['date_added'] = $date_diff['h'].' hour(s)';
						} elseif (isset($date_diff['i']) && $date_diff['i']) {
							$seller_notification['date_added'] = $date_diff['i'].' minute(s)';
						}else {
							$seller_notification['date_added'] = $date_diff['s'].' second(s)';
						}

						if ($seller_notification['key'] == 'order_account') {
							$seller_notification['data'] = json_decode($seller_notification['data'],1);
							$data['seller_notifications'][] = sprintf($this->language->get('text_order_add_mp'),session('user_token'),$seller_notification['data']['order_id'],$seller_notification['data']['order_id'],$seller_notification['data']['name'],$seller_notification['date_added']);
						} elseif ($seller_notification['key'] == 'return_account') {
							$seller_notification['data'] = json_decode($seller_notification['data'],1);
							$order_id = $this->model_customerpartner_notification->getReturnOrderId($seller_notification['data']['return_id']);
							if ($order_id) {
								$data['seller_notifications'][] = sprintf($this->language->get('text_order_return_mp'),$seller_notification['data']['name'],session('user_token'),$order_id['order_id'],$seller_notification['data']['return_id'],$order_id['product'],$seller_notification['date_added']);
							}
						} elseif ($seller_notification['key'] == 'order_status') {
							$seller_notification['data'] = json_decode($seller_notification['data'],1);
							$status = $this->model_localisation_order_status->getOrderStatus($seller_notification['data']['status']);
							$data['seller_notifications'][] = sprintf($this->language->get('text_order_status_mp'),session('user_token'),$seller_notification['data']['order_id'],$seller_notification['data']['order_id'],$status['name'],$seller_notification['date_added']);
						}
					}
				}

				$data['seller_product_reviews'] = array();

				$data['product_stock_total'] = $this->model_customerpartner_notification->getProductStockTotal();

				$data['review_total'] = $this->model_customerpartner_notification->getReviewTotal();

				$data['approval_total'] = $this->model_customerpartner_notification->getApprovalTotal();

				$seller_product_reviews = $this->model_customerpartner_notification->getProductActivity(array(),3);

				$data['product_review_total'] = $this->model_customerpartner_notification->getProductActivityTotal();

				if ($seller_product_reviews) {
					foreach ($seller_product_reviews as $key => $seller_product_review) {

						$date_diff = (array)(new DateTime($seller_product_review['date_added']))->diff(new DateTime());

						if (isset($date_diff['y']) && $date_diff['y']) {
							$seller_product_review['date_added'] = $date_diff['y'].' year(s)';
						} elseif (isset($date_diff['m']) && $date_diff['m']) {
							$seller_product_review['date_added'] = $date_diff['m'].' month(s)';
						} elseif (isset($date_diff['d']) && $date_diff['d']) {
							$seller_product_review['date_added'] = $date_diff['d'].' day(s)';
						} elseif (isset($date_diff['h']) && $date_diff['h']) {
							$seller_product_review['date_added'] = $date_diff['h'].' hour(s)';
						} elseif (isset($date_diff['i']) && $date_diff['i']) {
							$seller_product_review['date_added'] = $date_diff['i'].' minute(s)';
						}else {
							$seller_product_review['date_added'] = $date_diff['s'].' second(s)';
						}

						$seller_product_review['data'] = json_decode($seller_product_review['data'],1);
						if ($seller_product_review['key'] == 'product_review') {
							$data['seller_product_reviews'][] = sprintf($this->language->get('text_product_review_mp'),$seller_product_review['id'],$seller_product_review['data']['author'],session('user_token'),$seller_product_review['data']['product_name'],$seller_product_review['data']['product_name'],$seller_product_review['date_added']);
						} elseif($seller_product_review['key'] == 'product_stock') {
							$data['seller_product_reviews'][] = sprintf($this->language->get('text_product_stock'),session('user_token'),$seller_product_review['data']['product_name'],$seller_product_review['data']['product_name'],$seller_product_review['date_added']);
						} elseif ($seller_product_review['key'] == 'product_approve') {
							$data['seller_product_reviews'][] = sprintf($this->language->get('text_product_approve'),session('user_token'),$seller_product_review['data']['product_name'],$seller_product_review['data']['product_name'],$seller_product_review['date_added']);
						}
					}
				}

				$data['seller_reviews'] = array();

				$seller_reviews = $this->model_customerpartner_notification->getSellerReviews(array(),3);

				$data['seller_review_total'] = $this->model_customerpartner_notification->getSellerReviewsTotal();

				if ($seller_reviews) {
					foreach ($seller_reviews as $key => $seller_review) {

						$date_diff = (array)(new DateTime($seller_review['createdate']))->diff(new DateTime());

						if (isset($date_diff['y']) && $date_diff['y']) {
							$seller_review['createdate'] = $date_diff['y'].' year(s)';
						} elseif (isset($date_diff['m']) && $date_diff['m']) {
							$seller_review['createdate'] = $date_diff['m'].' month(s)';
						} elseif (isset($date_diff['d']) && $date_diff['d']) {
							$seller_review['createdate'] = $date_diff['d'].' day(s)';
						} elseif (isset($date_diff['h']) && $date_diff['h']) {
							$seller_review['createdate'] = $date_diff['h'].' hour(s)';
						} elseif (isset($date_diff['i']) && $date_diff['i']) {
							$seller_review['createdate'] = $date_diff['i'].' minute(s)';
						}else {
							$seller_review['createdate'] = $date_diff['s'].' second(s)';
						}

						$data['seller_reviews'][] =
							sprintf($this->language->get('text_seller_review_mp'),$seller_review['id'],session('user_token'),$seller_review['name'],$seller_review['name'],$seller_review['createdate']);
					}
				}

				$categories = $this->model_customerpartner_notification->getCategoryActivity();

				$categories_total = $this->model_customerpartner_notification->getCategoryActivityTotal();

				if ($categories) {
				  foreach ($categories as $key => $category) {

				    $date_diff = (array)(new DateTime($category['date_added']))->diff(new DateTime());

				    if (isset($date_diff['y']) && $date_diff['y']) {
				      $category['date_added'] = $date_diff['y'].' year(s)';
				    } elseif (isset($date_diff['m']) && $date_diff['m']) {
				      $category['date_added'] = $date_diff['m'].' month(s)';
				    } elseif (isset($date_diff['d']) && $date_diff['d']) {
				      $category['date_added'] = $date_diff['d'].' day(s)';
				    } elseif (isset($date_diff['h']) && $date_diff['h']) {
				      $category['date_added'] = $date_diff['h'].' hour(s)';
				    } elseif (isset($date_diff['i']) && $date_diff['i']) {
				      $category['date_added'] = $date_diff['i'].' minute(s)';
				    }else {
				      $category['date_added'] = $date_diff['s'].' second(s)';
				    }
				    $category['data'] = json_decode($category['data'],1);

				    if (isset($category['data']['category_name']) && $category['data']['category_name']) {
				      $data['seller_reviews'][] = sprintf($this->language->get('text_category_approve'),session('user_token'),$category['data']['category_name'],$category['date_added']);
				    }
				  }
				}

				$data['alerts'] = $this->model_customerpartner_notification->getActivityCount() + $data['product_review_total'] + $data['seller_review_total'] + $categories_total;

				if ($this->config->get('marketplace_notification_viewed')) {
					$data['alerts'] = $data['alerts'] - $this->config->get('marketplace_notification_viewed');
					if ($data['alerts'] < 0) {
						$data['alerts'] = 0;
					}
				}

				$this->response->addHeader('Content-Type: application/json');

				$this->response->setOutput(json_encode($this->load->view('customerpartner/notification_header', $data)));
			}
		} else {
			$this->response->redirect($this->url->link('common/dashboard','user_token=' . session('user_token'),true));
		}
	}
}
?>
