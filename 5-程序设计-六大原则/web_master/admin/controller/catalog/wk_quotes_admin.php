<?php

/**
 * @property ModelCatalogwkquotesadmin $model_catalog_wk_quotes_admin
 * @property ModelToolImage $model_tool_image
 */
class ControllerCatalogwkquotesadmin extends Controller
{

    private $error = array();
    private $data = array();

    public function index()
    {
        $this->language->load('catalog/wk_quotes_admin');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/wk_quotes_admin');

        $this->getList();
    }


    protected function getList()
    {

        if (isset($this->request->get['filter_id'])) {
            $filter_id = $this->request->get['filter_id'];
        } else {
            $filter_id = null;
        }

        if (isset($this->request->get['filter_customer'])) {
            $filter_customer = $this->request->get['filter_customer'];
        } else {
            $filter_customer = null;
        }

        if (isset($this->request->get['filter_product'])) {
            $filter_product = $this->request->get['filter_product'];
        } else {
            $filter_product = null;
        }

        if (isset($this->request->get['filter_qty'])) {
            $filter_qty = $this->request->get['filter_qty'];
        } else {
            $filter_qty = null;
        }

        if (isset($this->request->get['filter_status'])) {
            $filter_status = $this->request->get['filter_status'];
        } else {
            $filter_status = null;
        }

        if (isset($this->request->get['filter_price'])) {
            $filter_price = $this->request->get['filter_price'];
        } else {
            $filter_price = null;
        }

        if (isset($this->request->get['filter_date'])) {
            $filter_date = $this->request->get['filter_date'];
        } else {
            $filter_date = null;
        }

        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'name';
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

        $data = array(
         'filter_id'                => $filter_id,
         'filter_qty'                  => $filter_qty,
         'filter_customer'            => $filter_customer,
         'filter_product'            => $filter_product,
         'filter_price'             => $filter_price,
         'filter_status'               => $filter_status,
         'filter_date'              => $filter_date,
         'sort'                     => $sort,
         'order'                    => $order,
         'start'                    => ($page - 1) * $this->config->get('config_limit_admin'),
         'limit'                    => $this->config->get('config_limit_admin'),
        );

        $url = '';

        if (isset($this->request->get['filter_id'])) {
            $url .= '&filter_id=' . $this->request->get['filter_id'];
        }

        if (isset($this->request->get['filter_customer'])) {
            $url .= '&filter_customer=' . urlencode(html_entity_decode($this->request->get['filter_customer'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_product'])) {
            $url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_qty'])) {
            $url .= '&filter_qty=' . $this->request->get['filter_qty'];
        }

        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }

        if (isset($this->request->get['filter_price'])) {
            $url .= '&filter_price=' . $this->request->get['filter_price'];
        }

        if (isset($this->request->get['filter_date'])) {
            $url .= '&filter_date=' . urlencode(html_entity_decode($this->request->get['filter_date'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }

        if ($order == 'ASC') {
            $url .= '&order=ASC';
        } else {
            $url .= '&order=DESC';
        }

        $data['sort_id'] = $this->url->link('catalog/wk_quotes_admin', 'user_token=' . session('user_token'). '&sort=pq.id'.$url, true);
        $data['sort_customer'] = $this->url->link('catalog/wk_quotes_admin', 'user_token=' . session('user_token'). '&sort=c.firstname'.$url, true);
        $data['sort_qty'] = $this->url->link('catalog/wk_quotes_admin', 'user_token=' . session('user_token') . '&sort=pq.quantity' . $url, true);
        $data['sort_product'] = $this->url->link('catalog/wk_quotes_admin', 'user_token=' . session('user_token'). '&sort=pq.product_id'.$url, true);
        $data['sort_price'] = $this->url->link('catalog/wk_quotes_admin', 'user_token=' . session('user_token') . '&sort=pq.price' . $url, true);
        $data['sort_status'] = $this->url->link('catalog/wk_quotes_admin', 'user_token=' . session('user_token'). '&sort=pq.status'.$url, true);
        $data['sort_date'] = $this->url->link('catalog/wk_quotes_admin', 'user_token=' . session('user_token') . '&sort=pq.date_added' . $url, true);

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        $this->language->load('catalog/wk_quotes_admin');

          $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
               'text'      => $this->language->get('text_home'),
         'href'      => $this->url->link('common/dashboard', 'user_token=' . session('user_token').$url, true),
              'separator' => false
        );

        $data['breadcrumbs'][] = array(
               'text'      => $this->language->get('heading_title'),
         'href'      => $this->url->link('catalog/wk_quotes_admin', 'user_token=' . session('user_token').$url, true),
              'separator' => ' :: '
        );

        $result_total = $this->model_catalog_wk_quotes_admin->viewtotalentry($data);

        $results = $this->model_catalog_wk_quotes_admin->viewtotal($data);

        $data['result_quotelist'] = array();

        foreach ($results as $result) {

            $action = $actiondelete = array();

            $action = array(
             'text' => $this->language->get('text_edit'),
             'href' => $this->url->link('catalog/wk_quotes_admin/update', 'user_token=' . session('user_token') .'&id=' . $result['id'], true)
            );

            $result['status_text'] = $this->language->get('text_status_'.$result['status']);

            $data['result_quotelist'][] = array(
             'selected' => false,
             'id' => $result['id'],
             'customer_name' => $result['customer_name'],
             'email' => $result['email'],
             'name' => $result['name'],
             'product_id' => $result['product_id'],
             'href' => $this->url->link('product/product&product_id='.$result['product_id']),
             'quantity' => $result['quantity'],
             'message' => substr(utf8_decode($result['message']), 0, 35),
             'price' => $this->currency->format($result['price'], $this->config->get('config_currency')),
             'baseprice' => $this->currency->format($result['baseprice'], $this->config->get('config_currency')),
             'status' => $result['status'],
             'status_text' => $result['status_text'],
             'date'   => $result['date_added'],
             'action'     => $action,
             'actiondelete' => $actiondelete,
            );

        }

        $data['quote_status'] = array(
            0 => $this->language->get('text_status_0'),
            1 => $this->language->get('text_status_1'),
            2 => $this->language->get('text_status_2'),
            3 => $this->language->get('text_status_3'),
            4 => $this->language->get('text_status_4'),
            5 => $this->language->get('text_status_5'),
        );


         $data['user_token'] = session('user_token');

        $data['delete'] = $this->url->link('catalog/wk_quotes_admin/delete', 'user_token=' . session('user_token'), true);

        $data['action'] = $this->url->link('catalog/wk_quotes_admin&user_token=' . session('user_token'));

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

        $pagination = new Pagination();
        $pagination->total = $result_total;
        $pagination->page = $page;
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->text = $this->language->get('text_pagination');
        $pagination->url = $this->url->link('catalog/wk_quotes_admin', 'user_token=' . session('user_token') . $url . '&page={page}', true);

        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf($this->language->get('text_pagination'), ($result_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($result_total - $this->config->get('config_limit_admin'))) ? $result_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $result_total, ceil($result_total / $this->config->get('config_limit_admin')));

        $data['sort'] = $sort;
        $data['order'] = $order;
        $data['filter_id'] = $filter_id;
        $data['filter_qty'] = $filter_qty;
        $data['filter_customer'] = $filter_customer;
        $data['filter_product'] = $filter_product;
        $data['filter_price'] = $filter_price;
        $data['filter_status'] = $filter_status;
        $data['filter_date'] = $filter_date;

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $data['column_left'] = $this->load->controller('common/column_left');

        $this->response->setOutput($this->load->view('catalog/wk_quotes_admin', $data));

    }

    public function update()
    {

        $this->language->load('catalog/wk_quotes_admin');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/wk_quotes_admin');

        $data['heading_title']=$this->language->get('heading_title');

        if ((request()->isMethod('POST')) && $this->validateForm()) {
              $this->model_catalog_wk_quotes_admin->updatebyid($this->request->post);

              session()->set('localStorage_tab', 'tab-messages');

              session()->set('success', $this->language->get('text_success_update'));

              $this->response->redirect($this->url->link('catalog/wk_quotes_admin/update&id='.$this->request->post['quote_id'], 'user_token=' . session('user_token'), true));

        }

        $this->getForm();

    }


    protected function getForm()
    {

        if (isset($this->request->get['id'])) {
            $id = $this->request->get['id'];
        } else {
            $id = 0;
        }

        if (isset($this->request->get['filter_name'])) {
            $filter_name = $this->request->get['filter_name'];
        } else {
            $filter_name = null;
        }

        if (isset($this->request->get['filter_message'])) {
            $filter_message = $this->request->get['filter_message'];
        } else {
            $filter_message = null;
        }

        if (isset($this->request->get['filter_date'])) {
            $filter_date = $this->request->get['filter_date'];
        } else {
            $filter_date = null;
        }

        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
            session()->set('localStorage_tab', 'tab-messages');
        } else {
            $sort = 'name';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'ASC';
        }

        if (isset($this->request->get['page'])) {
            session()->set('localStorage_tab', 'tab-messages');
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $data = array(
         'filter_name'              => $filter_name,
         'filter_message'           => $filter_message,
         'filter_date'              => $filter_date,
         'filter_id'                   => $id,
         'sort'                     => $sort,
         'order'                    => $order,
         'start'                    => ($page - 1) * $this->config->get('config_limit_admin'),
         'limit'                    => $this->config->get('config_limit_admin')
        );

        $url = '';

        if (isset($this->request->get['filter_name'])) {
            $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_message'])) {
            $url .= '&filter_message=' . urlencode(html_entity_decode($this->request->get['filter_message'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_date'])) {
            $url .= '&filter_date=' . urlencode(html_entity_decode($this->request->get['filter_date'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }

        if ($order == 'ASC') {
            $url .= '&order=DESC';
        } else {
            $url .= '&order=ASC';
        }

        $url .= '&id=' . $id;

        $data['sort_name'] = $this->url->link('catalog/wk_quotes_admin/update', 'user_token=' . session('user_token'). '&sort=pqm.writer'.$url, true);
        $data['sort_message'] = $this->url->link('catalog/wk_quotes_admin/update', 'user_token=' . session('user_token') . '&sort=pqm.message' . $url, true);
        $data['sort_date'] = $this->url->link('catalog/wk_quotes_admin/update', 'user_token=' . session('user_token') . '&sort=pqm.date' . $url, true);

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        $this->language->load('catalog/wk_quotes_admin');
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
               'text'      => $this->language->get('text_home'),
         'href'      => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true),
              'separator' => false
        );

        $data['breadcrumbs'][] = array(
               'text'      => $this->language->get('heading_title'),
         'href'      => $this->url->link('catalog/wk_quotes_admin', 'user_token=' . session('user_token'), true),
              'separator' => ' :: '
        );

        $data['save'] = $this->url->link('catalog/wk_quotes_admin/update', 'user_token=' . session('user_token'), true);

        $data['back'] = $this->url->link('catalog/wk_quotes_admin', 'user_token=' . session('user_token'), true);

        $this->load->model('catalog/wk_quotes_admin');
        $this->load->model('tool/image');

        $data['admin_name'] = $this->config->get('total_wk_pro_quote_name');

        $data['results_message'] = $this->model_catalog_wk_quotes_admin->viewtotalMessageBy($data);
        $results_message_total = $this->model_catalog_wk_quotes_admin->viewtotalNoMessageBy($data);

        $data['result_quoteadmin'] = array();

        $result = $this->model_catalog_wk_quotes_admin->viewQuoteByid($id);

        if ($result) {

            $result['status_text'] = $this->language->get('text_status_'.$result['status']);

            $product = unserialize(base64_decode($result['product_key']));

            if (!empty($product['option'])) {
                $options = $this->model_catalog_wk_quotes_admin->getProductOptions($product['option'], $result['product_id']);
            } else {
                $options = array();
            }

            if ($result['image']) {
                $image = $this->model_tool_image->resize($result['image'], 200, 200);
            } else {
                $image = $this->model_tool_image->resize('placeholder.png', 200, 200);
            }

            $priceStr = '';
            $origin_price = $this->currency->format($result['origin_price'], $this->config->get('config_currency'));
            if ($result['discount'] !== 1) {
                $discount_price = $this->currency->format($result['discount_price'], $this->config->get('config_currency'));
                $priceStr = $discount_price . ' ( Original Price: ' . $origin_price . ', Discount: ' . $result['discount'] . ' ) ';
            }

            $data['result_quoteadmin'] = array(
                'id' => $result['id'],
                'email' => $result['email'],
                'customer_name' => $result['customer_name'],
                'customer_id' => $result['customer_id'],
                'seller_name' => $result['seller_name'],
                'seller_id' => $result['seller_id'],
                'product_name' => $result['name'],
                'product_id' => $result['product_id'],
                'product_href' => $this->url->link('catalog/product&user_token=' . session('user_token') . '&filter_name=' . $result['name']),
                'order_id' => $result['order_id'],
                'date_used' => $result['date_used'],
                'orderhref' => $this->url->link('sale/order/info&user_token=' . session('user_token') . '&order_id=' . $result['order_id']),
                'options' => $options,
                'image' => $image,
                'quantity' => $result['quantity'],
                'message' => $result['message'],
                'price' => $this->currency->format($result['price'], $this->config->get('config_currency')),
                'amount' => $this->currency->format($result['amount'], $this->config->get('config_currency')),
                'status' => $result['status'],
                'status_text' => $result['status_text'],
                'baseprice' => $priceStr ? $priceStr : $origin_price,
            );
        }


        $data['quote_status'] = array(
            0 => $this->language->get('text_status_0'),
            1 => $this->language->get('text_status_1'),
            2 => $this->language->get('text_status_2'),
            3 => $this->language->get('text_status_3'),
            4 => $this->language->get('text_status_4'),
            5 => $this->language->get('text_status_5'),
        );

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

        if (isset($this->session->data['localStorage_tab'])) {
            $data['localStorage_tab'] = session('localStorage_tab');
            $this->session->remove('localStorage_tab');
        } else {
            $data['localStorage_tab'] = '';
        }

        $url = '';

        if (isset($this->request->get['filter_name'])) {
            $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_message'])) {
            $url .= '&filter_message=' . urlencode(html_entity_decode($this->request->get['filter_message'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_date'])) {
            $url .= '&filter_date=' . urlencode(html_entity_decode($this->request->get['filter_date'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        $url .= '&id=' . $id;

        $pagination = new Pagination();
        $pagination->total = $results_message_total;
        $pagination->page = $page;
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->text = $this->language->get('text_pagination');
        $pagination->url = $this->url->link('catalog/wk_quotes_admin/update', 'user_token=' . session('user_token') . $url . '&page={page}', true);

        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($results_message_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($results_message_total - $this->config->get('config_limit_admin'))) ? $results_message_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $results_message_total, ceil($results_message_total / $this->config->get('config_limit_admin')));

        $data['sort'] = $sort;
        $data['order'] = $order;
        $data['filter_name'] = $filter_name;
        $data['filter_message'] = $filter_message;
        $data['filter_date'] = $filter_date;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('catalog/wk_quotes_admin_details', $data));

    }

    public function delete()
    {

        $this->language->load('catalog/wk_quotes_admin');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/wk_quotes_admin');

        if (isset($this->request->post['selected']) && $this->validateDelete()) {
            foreach ($this->request->post['selected'] as $id) {
                $this->model_catalog_wk_quotes_admin->deleteentry($id);
            }

              session()->set('success', $this->language->get('text_success'));

              $url='';

            if (isset($this->request->get['page'])) {
                $url .= '&page=' . $this->request->get['page'];
            }

              $this->response->redirect($this->url->link('catalog/wk_quotes_admin', 'user_token=' . session('user_token') . $url, true));
        }

        $this->getList();
    }

    private function validateForm()
    {
        if (!$this->user->hasPermission('modify', 'catalog/wk_quotes_admin')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if ($this->error && !isset($this->error['warning'])) {
            $this->error['warning'] = $this->language->get('error_warning');
        }

        if (!$this->error) {
            return true;
        } else {
            return false;
        }
    }

    protected function validateDelete()
    {
        if (!$this->user->hasPermission('modify', 'catalog/wk_quotes_admin')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->error) {
              return true;
        } else {
              return false;
        }
    }

}
?>
