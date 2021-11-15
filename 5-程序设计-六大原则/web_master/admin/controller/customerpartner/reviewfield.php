<?php

/**
 * @property ModelCustomerpartnerReview $model_customerpartner_review
 */
class ControllerCustomerpartnerReviewfield extends Controller {
	private $error = array();

	public function index() {
		$data = $this->load->language('customerpartner/reviewfield');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('customerpartner/review');

		$this->getList($data);
	}

	public function add() {
		$data = $this->load->language('customerpartner/reviewfield');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('customerpartner/review');

		if ((request()->isMethod('POST')) && $this->validateForm()) {

			$this->model_customerpartner_review->addReviewField($this->request->post);

			session()->set('success', $this->language->get('text_success'));

			$url = '';

			if (isset($this->request->get['filter_field'])) {
				$url .= '&filter_field=' . urlencode(html_entity_decode($this->request->get['filter_field'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('customerpartner/reviewfield', 'user_token=' . session('user_token') . $url, true));
		}

		$this->getForm($data);
	}

	public function edit() {
		$data = $this->load->language('customerpartner/reviewfield');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('customerpartner/review');

		if ((request()->isMethod('POST')) && $this->validateForm()) {
			$this->model_customerpartner_review->editReviewField($this->request->post,$this->request->get['reviewfield_id']);

			session()->set('success', $this->language->get('text_success'));

			$url = '';

			if (isset($this->request->get['filter_field'])) {
				$url .= '&filter_field=' . urlencode(html_entity_decode($this->request->get['filter_field'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('customerpartner/reviewfield', 'user_token=' . session('user_token') . $url, true));
		}

		$this->getForm($data);
	}

	public function delete() {
		$data = $this->load->language('customerpartner/reviewfield');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('customerpartner/review');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $reviewfield_id) {
				$this->model_customerpartner_review->deleteReviewField($reviewfield_id);
			}

			session()->set('success', $this->language->get('text_success'));

			$url = '';

			if (isset($this->request->get['filter_field'])) {
				$url .= '&filter_field=' . urlencode(html_entity_decode($this->request->get['filter_field'], ENT_QUOTES, 'UTF-8'));
			}

			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('customerpartner/reviewfield', 'user_token=' . session('user_token') . $url, true));
		}

		$this->getList($data);
	}

	protected function getList($data) {

		if (isset($this->request->get['filter_field'])) {
			$filter_field = $this->request->get['filter_field'];
		} else {
			$filter_field = null;
		}

		if (isset($this->request->get['filter_status'])) {
			$filter_status = $this->request->get['filter_status'];
		} else {
			$filter_status = null;
		}

		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['filter_field'])) {
			$url .= '&filter_field=' . urlencode(html_entity_decode($this->request->get['filter_field'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $data['text_home'],
			'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $data['heading_title'],
			'href' => $this->url->link('customerpartner/reviewfield', 'user_token=' . session('user_token') . $url, true)
		);

		$data['add'] = $this->url->link('customerpartner/reviewfield/add', 'user_token=' . session('user_token') . $url, true);
		$data['delete'] = $this->url->link('customerpartner/reviewfield/delete', 'user_token=' . session('user_token') . $url, true);

		$data['reviewfields'] = array();

		$filter_data = array(
			'filter_field'   => $filter_field,
			'filter_status'     => $filter_status,
			'start'             => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'             => $this->config->get('config_limit_admin')
		);

		$reviewfield_total = $this->model_customerpartner_review->getTotalReviewFields($filter_data);

		$results = $this->model_customerpartner_review->getReviewFields($filter_data);

		foreach ($results as $result) {
			$data['reviewfields'][] = array(
				'reviewfield_id'   => $result['field_id'],
				'field_name'   					 => $result['field_name'],
				'status'           => ($result['field_status']) ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
				'edit'             => $this->url->link('customerpartner/reviewfield/edit', 'user_token=' . session('user_token') . '&reviewfield_id=' . $result['field_id'] . $url, true)
			);
		}

		$data['user_token'] = session('user_token');

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

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$url = '';

		if (isset($this->request->get['filter_field'])) {
			$url .= '&filter_field=' . urlencode(html_entity_decode($this->request->get['filter_field'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		$pagination = new Pagination();
		$pagination->total = $reviewfield_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('customerpartner/reviewfield', 'user_token=' . session('user_token') . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($reviewfield_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($reviewfield_total - $this->config->get('config_limit_admin'))) ? $reviewfield_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $reviewfield_total, ceil($reviewfield_total / $this->config->get('config_limit_admin')));

		$data['filter_field'] = $filter_field;
		$data['filter_status'] = $filter_status;
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('customerpartner/reviewfield_list', $data));
	}

	protected function getForm($data) {
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['field'])) {
			$data['error_field'] = $this->error['field'];
		} else {
			$data['error_field'] = '';
		}

		$url = '';

		if (isset($this->request->get['filter_field'])) {
			$url .= '&filter_field=' . urlencode(html_entity_decode($this->request->get['filter_field'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('customerpartner/reviewfield', 'user_token=' . session('user_token') . $url, true)
		);

		if (!isset($this->request->get['reviewfield_id'])) {
			$data['action'] = $this->url->link('customerpartner/reviewfield/add', 'user_token=' . session('user_token') . $url, true);
		} else {
			$data['action'] = $this->url->link('customerpartner/reviewfield/edit', 'user_token=' . session('user_token') . '&reviewfield_id=' . $this->request->get['reviewfield_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('customerpartner/reviewfield', 'user_token=' . session('user_token') . $url, true);

		if (isset($this->request->get['reviewfield_id']) && (!request()->isMethod('POST'))) {
			$reviewfield_info = $this->model_customerpartner_review->getReviewField($this->request->get['reviewfield_id']);
		}

		$data['user_token'] = session('user_token');

		if (isset($this->request->post['field_name'])) {
			$data['field_name'] = $this->request->post['field_name'];
		} elseif (!empty($reviewfield_info)) {
			$data['field_name'] = $reviewfield_info['field_name'];
		} else {
			$data['field_name'] = '';
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($reviewfield_info)) {
			$data['status'] = $reviewfield_info['field_status'];
		} else {
			$data['status'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('customerpartner/reviewfield_form', $data));
	}

	protected function validateForm() {

		if (!$this->user->hasPermission('modify', 'customerpartner/reviewfield')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!trim($this->request->post['field_name'])) {
			$this->error['field'] = $this->language->get('error_field');
			$this->request->post['field_name'] = '';
		} else {
			if ($this->model_customerpartner_review->getReviewFieldByName($this->request->post['field_name'])) {
				$this->error['field'] = $this->language->get('error_name');
			}
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'customerpartner/reviewfield')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		return !$this->error;
	}
}
