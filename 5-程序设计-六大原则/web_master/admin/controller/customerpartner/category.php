<?php

/**
 * @property ModelCatalogCategory $model_catalog_category
 * @property ModelCustomerpartnerCategory $model_customerpartner_category
 * @property ModelCustomerpartnerPartner $model_customerpartner_partner
 * @property ModelToolImage $model_tool_image
 */
class ControllerCustomerpartnerCategory extends Controller
{
    private $error = array();
    private $data = array();

    public function index()
    {
        $data = array_merge($this->load->language('customerpartner/category'));

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('customerpartner/category');

        $this->load->model('catalog/category');

        if (isset($this->request->get['filter_seller'])) {
            $filter_seller = $this->request->get['filter_seller'];
        } else {
            $filter_seller = null;
        }

        if (isset($this->request->get['filter_category'])) {
            $filter_category = $this->request->get['filter_category'];
        } else {
            $filter_category = null;
        }

        if (isset($this->request->get['filter_category_id'])) {
            $filter_category_id = $this->request->get['filter_category_id'];
        } else {
            $filter_category_id = null;
        }

        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'pd.name';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'ASC';
        }

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $url = '';

        if (isset($this->request->get['filter_seller'])) {
            $url .= '&filter_seller=' . urlencode(html_entity_decode($this->request->get['filter_seller'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_category'])) {
            $url .= '&filter_category=' . urlencode(html_entity_decode($this->request->get['filter_category'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_category_id'])) {
            $url .= '&filter_category_id=' . urlencode(html_entity_decode($this->request->get['filter_category_id'], ENT_QUOTES, 'UTF-8'));
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
               'text'      => $this->language->get('text_home'),
            'href'      => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true),
              'separator' => false
        );

        $data['breadcrumbs'][] = array(
               'text'      => $this->language->get('heading_title'),
            'href'      => $this->url->link('customerpartner/category', 'user_token=' . session('user_token') . $url, true),
              'separator' => ' :: '
        );

        $data['insert'] = $this->url->link('customerpartner/category/add', 'user_token=' . session('user_token') . $url . '&mpcheck=1', true);

        $data['delete'] = $this->url->link('customerpartner/category/delete', 'user_token=' . session('user_token') . $url, true);

        $data['categories'] = array();

        $filterData = array(
            'filter_seller'      => $filter_seller,
            'filter_category' => $filter_category,
            'filter_category_id' => $filter_category_id,
            'sort'            => $sort,
            'order'           => $order,
            'start'           => ($page - 1) * $this->config->get('config_limit_admin'),
            'limit'           => $this->config->get('config_limit_admin')
        );

        $this->load->model('tool/image');

        $this->load->model('customerpartner/partner');

        $data['partners'] = $this->model_customerpartner_partner->getCustomers();

        $category_total = $this->model_customerpartner_category->getTotalSellerCategories($filterData);

        $results = $this->model_customerpartner_category->getSellerCategories($filterData);

        if ($results) {
            foreach ($results as $result) {
                $data['categories'][] = array(
                    'category_id' => $result['category_id'],
                    'seller_id' => $result['seller_id'],
                    'name'       => $result['name'],
                    'selected'   => isset($this->request->post['selected']) && in_array($result['seller_id'], $this->request->post['selected']),
                    'action'    => $this->url->link('customerpartner/category/add', 'user_token=' . session('user_token') . '&seller_id=' . $result['seller_id'], true)
                );
            }
        }

        $data['user_token'] = session('user_token');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } elseif (isset($this->session->data['warning']) && session('warning')) {
            $data['error_warning'] = session('warning');
            $this->session->remove('warning');
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = session('success');

            $this->session->remove('success');
        } else {
            $data['success'] = '';
        }

        $url = '';

        if (isset($this->request->get['filter_seller'])) {
            $url .= '&filter_seller=' . urlencode(html_entity_decode($this->request->get['filter_seller'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_category'])) {
            $url .= '&filter_category=' . urlencode(html_entity_decode($this->request->get['filter_category'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_category_id'])) {
            $url .= '&filter_category_id=' . urlencode(html_entity_decode($this->request->get['filter_category_id'], ENT_QUOTES, 'UTF-8'));
        }

        if ($order == 'ASC') {
            $url .= '&order=DESC';
        } else {
            $url .= '&order=ASC';
        }

        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }

        $data['sort_seller_name'] = $this->url->link('customerpartner/category', 'user_token=' . session('user_token') . '&sort=c.seller_id' . $url, true);

        $url = '';

        if (isset($this->request->get['filter_seller'])) {
            $url .= '&filter_seller=' . urlencode(html_entity_decode($this->request->get['filter_seller'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_category'])) {
            $url .= '&filter_category=' . urlencode(html_entity_decode($this->request->get['filter_category'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_category_id'])) {
            $url .= '&filter_category_id=' . urlencode(html_entity_decode($this->request->get['filter_category_id'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        $pagination = new Pagination();
        $pagination->total = $category_total;
        $pagination->page = $page;
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->text = $this->language->get('text_pagination');
        $pagination->url = $this->url->link('customerpartner/category', 'user_token=' . session('user_token') . $url . '&page={page}', true);

        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($category_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($category_total - $this->config->get('config_limit_admin'))) ? $category_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $category_total, ceil($category_total / $this->config->get('config_limit_admin')));

        $data['filter_seller'] = $filter_seller;

        $data['filter_category'] = $filter_category;

        $data['filter_category_id'] = $filter_category_id;

        $data['sort'] = $sort;

        $data['header'] = $this->load->controller('common/header');

        $data['footer'] = $this->load->controller('common/footer');

        $data['column_left'] = $this->load->controller('common/column_left');

        $this->response->setOutput($this->load->view('customerpartner/category_list', $data));
    }

    public function add()
    {
        $data = array_merge($this->load->language('customerpartner/category'));

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('customerpartner/category');

        $this->load->model('catalog/category');

        if ((request()->isMethod('POST')) && $this->validateForm()) {
            $this->model_customerpartner_category->addCategory($this->request->post);

            session()->set('success', $this->language->get('text_success'));

            $this->response->redirect($this->url->link('customerpartner/category', 'user_token=' . session('user_token'), true));
        }

        $data['user_token'] = session('user_token');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $url = '';

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
               'text'      => $this->language->get('text_home'),
            'href'      => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true),
              'separator' => false
        );

        $data['breadcrumbs'][] = array(
               'text'      => $this->language->get('heading_title'),
            'href'      => $this->url->link('customerpartner/category', 'user_token=' . session('user_token') . $url, true),
              'separator' => ' :: '
        );

        if (!request()->isMethod('POST') && isset($this->request->get['seller_id']) && $this->request->get['seller_id']) {
            $seller_name = $this->model_customerpartner_category->getSellerName($this->request->get['seller_id']);

            $data['sellers'][] = array(
                'seller_id' => $this->request->get['seller_id'],
                'name'        => isset($seller_name['name']) && $seller_name['name'] ? $seller_name['name'] : '',
            );

						$seller_category = $this->model_customerpartner_category->getSellerCategory($this->request->get['seller_id']);

						if (isset($seller_category['category_id']) && $seller_category['category_id']) {
						    $data['product_categories'] = array();

						    foreach (explode(',', $seller_category['category_id']) as $key => $value) {
						        $category_details = $this->model_catalog_category->getCategory($value);

						        $data['product_categories'][] = array(
						            'category_id' => $value,
						            'name'          => isset($category_details['name']) && $category_details['name'] ? $category_details['name'] : 'All',
						        );
						    }
						}
        }

        $data['cancel'] = $this->url->link('customerpartner/category', 'user_token=' . session('user_token') . $url, true);

        $data['save'] = $this->url->link('customerpartner/category/add', 'user_token=' . session('user_token') . $url, true);

        $data['header'] = $this->load->controller('common/header');

        $data['footer'] = $this->load->controller('common/footer');

        $data['column_left'] = $this->load->controller('common/column_left');

        $this->response->setOutput($this->load->view('customerpartner/category_form', $data));
    }

    public function delete()
    {
        $this->load->language('customerpartner/category');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('customerpartner/category');

        if ($this->validateDelete()) {
            $this->model_customerpartner_category->deleteCategory(implode(',', $this->request->post['selected']));

            session()->set('success', $this->language->get('text_success_delete'));
        }

        $this->index();
    }

    private function validateDelete()
    {
        if (!$this->user->hasPermission('modify', 'customerpartner/category')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!isset($this->request->post['selected']) || !$this->request->post['selected']) {
            session()->set('warning', $this->language->get('error_delete'));
            return false;
        }

        if (!$this->error) {
            return true;
        } else {
            return false;
        }
    }

    private function validateForm()
    {
        if (!$this->user->hasPermission('modify', 'customerpartner/category')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!isset($this->request->post['seller_ids']) || !isset($this->request->post['product_category']) || !$this->request->post['seller_ids'] || !$this->request->post['product_category']) {
            $this->error['warning'] = $this->language->get('error_field');
        }

        if (!$this->error) {
            return true;
        } else {
            return false;
        }
    }

    public function autocomplete()
    {
        $json = array();

        if (isset($this->request->get['filter_name'])) {
            $allowed_categories = '';
            if (isset($this->request->post['allowed_categories']) && $this->request->post['allowed_categories']) {
                foreach ($this->request->post['allowed_categories'] as $categories) {
                    $allowed_categories .= ','. $categories;
                }

                if ($allowed_categories) {
                    $allowed_categories = ltrim($allowed_categories, ',');
                }
            }

            $this->load->model('customerpartner/partner');

            $filter_data = array(
                'filter_name' => $this->request->get['filter_name'],
                'sort'        => 'name',
                'order'       => 'ASC',
                'start'       => 0,
                'limit'       => 5
            );

            $results = $this->model_customerpartner_partner->getCategories($filter_data, $allowed_categories);

            $json[] = array(
                'category_id' => 0,
                'name'        => 'All'
            );

            foreach ($results as $result) {
                $json[] = array(
                    'category_id' => $result['category_id'],
                    'name'        => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8'))
                );
            }
        }

        $sort_order = array();

        foreach ($json as $key => $value) {
            $sort_order[$key] = $value['name'];
        }

        array_multisort($sort_order, SORT_ASC, $json);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
