<?php

/**
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelCustomerpartnerReview $model_customerpartner_review
 */
class ControllerAccountCustomerpartnerReview extends Controller {
	private $error = array();

	public function index() {
		$data = $this->load->language('account/customerpartner/review');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('customerpartner/review');

		$this->getList($data);
	}

	public function edit() {
		$data = $this->load->language('account/customerpartner/review');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('customerpartner/review');

		if ((request()->isMethod('POST')) && $this->validateForm()) {
			$this->model_customerpartner_review->addReview($this->request->post);

			session()->set('success', $this->language->get('text_success'));

			$url = '';

			if (isset($this->request->get['filter_customer'])) {
				$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['filter_createdate'])) {
				$url .= '&filter_createdate=' . $this->request->get['filter_createdate'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('account/customerpartner/review', $url, true));
		}

		$this->getForm($data);
	}

	public function delete() {
		$data = $this->load->language('account/customerpartner/review');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('customerpartner/review');

		if (isset($this->request->post['selected']) && $this->config->get('marketplace_sellereditreview')) {
			foreach ($this->request->post['selected'] as $review_id) {
				$this->model_customerpartner_review->deleteReview($review_id);
			}

			session()->set('success', $this->language->get('text_success'));

			$url = '';

			if (isset($this->request->get['filter_customer'])) {
				$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['filter_createdate'])) {
				$url .= '&filter_createdate=' . $this->request->get['filter_createdate'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('account/customerpartner/review', $url, true));
		}

		$this->getList($data);
	}

	protected function getList($data) {

		$this->load->model('account/customerpartner');

		$data['chkIsPartner'] = $this->model_account_customerpartner->chkIsPartner();

		if(!$data['chkIsPartner'] || (isset($this->session->data['marketplace_seller_mode']) && !$this->session->data['marketplace_seller_mode']))
		$this->response->redirect($this->url->link('account/account', '', true));

		$data['marketplace_sellereditreview'] = $this->config->get('marketplace_sellereditreview');

		if (isset($this->request->get['filter_customer'])) {
			$filter_customer = $this->request->get['filter_customer'];
		} else {
			$filter_customer = null;
		}

		if (isset($this->request->get['filter_status'])) {
			$filter_status = $this->request->get['filter_status'];
		} else {
			$filter_status = null;
		}

		if (isset($this->request->get['filter_createdate'])) {
			$filter_createdate = $this->request->get['filter_createdate'];
		} else {
			$filter_createdate = null;
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'c2f.createdate';
			$order = 'DESC';
		}

		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}
		$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment.min.js');
		$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment/moment-with-locales.min.js');
		$this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
		$this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');
		$this->document->addStyle('catalog/view/theme/default/stylesheet/MP/sell.css?v=' . APP_VERSION);


		$url = '';

		if (isset($this->request->get['filter_customer'])) {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['filter_createdate'])) {
			$url .= '&filter_createdate=' . $this->request->get['filter_createdate'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $data['text_home'],
			'href' => $this->url->link('common/dashboard', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $data['heading_title'],
			'href' => $this->url->link('account/customerpartner/review', $url, true)
		);

		$data['delete'] = $this->url->link('account/customerpartner/review/delete', $url, true);

		$data['reviews'] = array();

		$filter_data = array(
			'filter_customer'   => $filter_customer,
			'filter_status'     => $filter_status,
			'filter_createdate' => $filter_createdate,
			'sort'              => $sort,
			'order'             => $order,
			'start'             => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'             => $this->config->get('config_limit_admin')
		);

		$review_total = $this->model_customerpartner_review->getTotalReviews($filter_data);

		$results = $this->model_customerpartner_review->getReviews($filter_data);

		foreach ($results as $result) {
			$data['reviews'][] = array(
				'review_id'        => $result['id'],
				'customer_name'    => $result['customer_name'],
				'status'           => ($result['status']) ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'createdate'       => date($this->language->get('date_format_short'), strtotime($result['createdate'])),
				'edit'             => $this->url->link('account/customerpartner/review/edit', 'review_id=' . $result['id'] . $url, true)
			);
		}

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

		if (isset($this->request->post['selected'])) {
			$data['selected'] = (array)$this->request->post['selected'];
		} else {
			$data['selected'] = array();
		}

		$url = '';

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['sort_customer'] = $this->url->link('account/customerpartner/review', 'sort=c2f.customer_id' . $url, true);
		$data['sort_status'] = $this->url->link('account/customerpartner/review', 'sort=c2f.status' . $url, true);
		$data['sort_createdate'] = $this->url->link('account/customerpartner/review', 'sort=c2f.createdate' . $url, true);

		$url = '';

		if (isset($this->request->get['filter_customer'])) {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['filter_createdate'])) {
			$url .= '&filter_createdate=' . $this->request->get['filter_createdate'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $review_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('account/customerpartner/review', $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($review_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($review_total - $this->config->get('config_limit_admin'))) ? $review_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $review_total, ceil($review_total / $this->config->get('config_limit_admin')));

		$data['filter_customer'] = $filter_customer;
		$data['filter_status'] = $filter_status;
		$data['filter_createdate'] = $filter_createdate;

		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$data['separate_view'] = false;

		$data['separate_column_left'] = '';

		if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
		  $data['separate_view'] = true;
		  $data['column_left'] = '';
		  $data['column_right'] = '';
		  $data['content_top'] = '';
		  $data['content_bottom'] = '';
		  $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');

		  $data['footer'] = $this->load->controller('account/customerpartner/footer');
		  $data['header'] = $this->load->controller('account/customerpartner/header');
		}

		$this->response->setOutput($this->load->view('account/customerpartner/review_list', $data));
	}

	protected function getForm($data) {

		if (!isset($this->request->get['review_id']) || !(int)$this->request->get['review_id']) {
			$this->response->redirect($this->url->link('account/customerpartner/review', '', true));
		}

		$this->load->model('account/customerpartner');

		$data['chkIsPartner'] = $this->model_account_customerpartner->chkIsPartner();

		$data['marketplace_sellereditreview'] = $this->config->get('marketplace_sellereditreview');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['customer'])) {
			$data['error_customer'] = $this->error['customer'];
		} else {
			$data['error_customer'] = '';
		}

		if (isset($this->error['text'])) {
			$data['error_text'] = $this->error['text'];
		} else {
			$data['error_text'] = '';
		}

		$url = '';

		if (isset($this->request->get['filter_customer'])) {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['filter_createdate'])) {
			$url .= '&filter_createdate=' . $this->request->get['filter_createdate'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('account/customerpartner/review', $url, true)
		);

		$data['action'] = $this->url->link('account/customerpartner/review/edit', 'review_id=' . $this->request->get['review_id'] . $url, true);

		$data['cancel'] = $this->url->link('account/customerpartner/review', $url, true);

		if (isset($this->request->get['review_id']) && (!request()->isMethod('POST'))) {
			$review_info = $this->model_customerpartner_review->getReview($this->request->get['review_id']);

			$review_fields = $this->model_customerpartner_review->getAllReviewFields();

			if ($review_fields) {
				foreach ($review_fields as $key => $value) {
					$attribute_value = $this->model_customerpartner_review->getReviewAttributeValue($this->request->get['review_id'],$value['field_id']);
					if (isset($attribute_value['field_value']) && $attribute_value['field_value']) {
						$data['review_attributes'][$value['field_id']] = $attribute_value['field_value'];
					}
				}
			}
		}

		if (isset($this->request->post['customer_id'])) {
			$data['customer_id'] = $this->request->post['customer_id'];
		} elseif (!empty($review_info)) {
			$data['customer_id'] = $review_info['customer_id'];
		} else {
			$data['customer_id'] = '';
		}

		if (isset($this->request->post['customer'])) {
			$data['customer'] = $this->request->post['customer'];
		} elseif (!empty($review_info)) {
			$data['customer'] = $review_info['customer_name'];
		} else {
			$data['customer'] = '';
		}

		if (isset($this->request->post['text'])) {
			$data['text'] = $this->request->post['text'];
		} elseif (!empty($review_info)) {
			$data['text'] = $review_info['review'];
		} else {
			$data['text'] = '';
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($review_info)) {
			$data['status'] = $review_info['status'];
		} else {
			$data['status'] = '';
		}

		$data['review_fields'] = $this->model_customerpartner_review->getAllReviewFields();

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$data['separate_view'] = false;

		$data['separate_column_left'] = '';

		if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
		  $data['separate_view'] = true;
		  $data['column_left'] = '';
		  $data['column_right'] = '';
		  $data['content_top'] = '';
		  $data['content_bottom'] = '';
		  $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');

		  $data['footer'] = $this->load->controller('account/customerpartner/footer');
		  $data['header'] = $this->load->controller('account/customerpartner/header');
		}

		$this->response->setOutput($this->load->view('account/customerpartner/review_form', $data));
	}

	protected function validateForm() {
		if (!$this->request->post['customer_id']) {
			$this->error['customer'] = $this->language->get('error_customer');
		}

		if (!$this->config->get('marketplace_sellereditreview')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (utf8_strlen($this->request->post['text']) < 1) {
			$this->error['text'] = $this->language->get('error_text');
		}

		return !$this->error;
	}

	public function autocomplete() {

		$json = array();

		if (isset($this->request->get['filter_customer'])) {

			$this->load->model('customerpartner/review');

			if (isset($this->request->get['filter_customer'])) {
				$filter_customer = $this->request->get['filter_customer'];
			} else {
				$filter_customer = '';
			}

			if (isset($this->request->get['customer_id'])) {
				$filter_customer_id = $this->request->get['customer_id'];
			} else {
				$filter_customer_id = '';
			}

			$data = array(
				'filter_customer'         => $filter_customer,
				'filter_customer_id'      => $filter_customer_id,
			);

			$results = $this->model_customerpartner_review->getCustomers($data);

			foreach ($results as $result) {

				$json[] = array(
					'customer_id' 		 => $result['customer_id'],
					'name'       => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
				);
			}
		}

		$this->response->setOutput(json_encode($json));
	}
}
