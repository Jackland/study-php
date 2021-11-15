<?php

use Illuminate\Support\Collection;

/**
 * @property ModelAccountCustomerpartnerMarketBusiness $model_account_customerpartner_market_business
 */
class ControllerAccountCustomerpartnerMarketBusiness extends Controller
{
    // 列表
    public function index()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/account', '', true));

            $this->response->redirect($this->url->link('account/login', '', true));
        }
        if (!$this->customer->isPartner()) {
            $this->response->redirect($this->url->link('account/account', '', true));
        }

        $this->load->language('account/customerpartner/market_business');
        $this->document->setTitle($this->language->get('heading_title_MS'));
        if(request()->isMethod('POST')){
            $this->save();
        }else{
            $this->getList();
        }
    }

    protected function getList()
    {
        $this->load->model('account/customerpartner/market_business');
        $customer_id = $this->customer->getId();
        $data = array();
        $session = $this->session->data;
        $data['success'] = $session['success'] ?? '';
        if (isset($session['success'])) {
            $this->session->remove('success');
        }
        $data['error_warning'] = $session['error_warning'] ?? '';
        if (isset($session['error_warning'])) {
            $this->session->remove('error_warning');
        }
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home', '', true)
            ],
            [
                'text' => $this->language->get('heading_seller_center'),
                'href' => $this->url->link('customerpartner/seller_center/index', '', true)
            ],
            [
                'text' => $this->language->get('heading_title_MB'),
                'href' => 'javascript:void(0);'
            ],
            [
                'text' => $this->language->get('heading_title_MS'),
                'href' => $this->url->link('account/customerpartner/market_business', '', true)
            ],
        ];


        $promotion_array = $this->model_account_customerpartner_market_business->getPromotions();
        $seller_connect_promotion = $this->model_account_customerpartner_market_business->getAllMarketBusinessTag($customer_id);

        $promotions = array();
        $scp = array();
        $promotion_data = array();
        $unavailable_promotion = array();
        if (!empty($promotion_array)) {
            foreach ($promotion_array as $promotion) {
                $promotions[$promotion['promotions_id']] = $promotion;
            }
        }
        if (!empty($seller_connect_promotion)) {
            foreach ($seller_connect_promotion as $seller_promotion) {
                $scp[$seller_promotion['promotions_id']] = $seller_promotion;
            }
        }

        if (!empty($promotions)) {
            foreach ($promotions as $promotion_id => $promotion) {
                if (isset($scp[$promotion_id])) {
                    //已存在seller关联项
                    $status_control = $scp[$promotion_id]['promotions_status'] && $scp[$promotion_id]['self_support'];
                    $self_control = $scp[$promotion_id]['self_support'];
                    $temp = array(
                        'promotion_id' => $promotion_id,
                        'promotion_name' => $scp[$promotion_id]['tag_name'],
                        'promotion_status' => $status_control,
                        'seller_id' => $customer_id,
                        'seller_status' => $scp[$promotion_id]['status'],
                        'edit' => $this->url->link('account/customerpartner/market_business/edit', 'promotion_id=' . $promotion_id, true)
                    );
                } else {
                    //需要补充关联项
                    $status_control = $promotion['promotions_status'] && $promotion['self_support'];
                    $self_control = $promotion['self_support'];
                    $temp = array(
                        'promotion_id' => $promotion_id,
                        'promotion_name' => $promotion['name'],
                        'promotion_status' => $status_control,
                        'seller_id' => $customer_id,
                        'seller_status' => 0,
                        'edit' => $this->url->link('account/customerpartner/market_business/edit', 'promotion_id=' . $promotion_id, true)
                    );
                }
                if($self_control){
                    if ($status_control) {
                        $promotion_data[] = $temp;
                    } else {
                        $unavailable_promotion[] = $temp;
                    }
                }
            }
            $promotion_data = array_merge($promotion_data, $unavailable_promotion);
        }
        $this->model_account_customerpartner_market_business->saveSellerPromotionRelation($promotion_data);
        $data['promotion_relation'] = $promotion_data;

        $select_list = array();
        $select_list[] = array(
            'value' => 0,
            'text' => 'Disable'
        );
        $select_list[] = array(
            'value' => 1,
            'text' => 'Enable'
        );
        $data['select_option'] = $select_list;

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['separate_view'] = false;
        $data['separate_column_left'] = '';
        if ($this->config->get('marketplace_separate_view') && isset($session['marketplace_separate_view']) && $session['marketplace_separate_view'] == 'separate') {
            $data['separate_view'] = true;
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        }
        $this->response->setOutput($this->load->view('account/customerpartner/market_business', $data));
    }

    public function edit(){
        $data = array();
        $promotion_id = $this->request->get['promotion_id'];
        $this->load->language('account/customerpartner/market_business');
        $this->load->model('account/customerpartner/market_business');
        $this->document->setTitle($this->language->get('heading_title_MS'));
        $customer_id = $this->customer->getId();
        $session = $this->session->data;
        $data['success'] = $session['success'] ?? '';
        if (isset($session['success'])) {
            $this->session->remove('success');
        }
        $data['error_warning'] = $session['error_warning'] ?? '';
        if (isset($session['error_warning'])) {
            $this->session->remove('error_warning');
        }
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home', '', true)
            ],
            [
                'text' => $this->language->get('heading_seller_center'),
                'href' => $this->url->link('customerpartner/seller_center/index', '', true)
            ],
            [
                'text' => $this->language->get('heading_title_MB'),
                'href' => 'javascript:void(0);'
            ],
            [
                'text' => $this->language->get('heading_title_MS'),
                'href' => $this->url->link('account/customerpartner/market_business', '', true)
            ],
        ];

        $data['back_action'] = $this->url->link('account/customerpartner/market_business', '', true);
        $data['save_action'] = $this->url->link('account/customerpartner/market_business', '',true);
        $market_description = $this->model_account_customerpartner_market_business->getMarketDescription($customer_id,$promotion_id);
        $promotion_array = $this->model_account_customerpartner_market_business->getSellerPromotions($promotion_id,$customer_id);

        $data['seller_status'] = 0;
        if(!empty($promotion_array)){
            $promotion = current($promotion_array);
            $data['seller_status'] = (int)$promotion['status'];
        }

        $data['promotion_values'] = $market_description;
        $data['promotion_id'] = $promotion_id;

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['separate_view'] = false;
        $data['separate_column_left'] = '';
        if ($this->config->get('marketplace_separate_view') && isset($session['marketplace_separate_view']) && $session['marketplace_separate_view'] == 'separate') {
            $data['separate_view'] = true;
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        }
        $this->response->setOutput($this->load->view('account/customerpartner/market_business_form', $data));
    }

    public function save(){
        $this->load->language('account/customerpartner/market_business');
        $this->load->model('account/customerpartner/market_business');
        $this->document->setTitle($this->language->get('heading_title_MS'));
        $post_data = $this->request->post;
        $customer_id = $this->customer->getId();
        if ((request()->isMethod('POST')) && $this->validateForm($post_data) && isset($customer_id)) {
            $this->model_account_customerpartner_market_business->savePromotionDescription($customer_id, $post_data);

            session()->set('success', $this->language->get('text_marketing_description_save_success'));
        }

        $this->response->redirect($this->url->link('account/customerpartner/market_business', 'user_token=' . session('user_token'), true));
    }

    public function matchProduct(){
        $this->load->language('account/customerpartner/market_business');
        $mpn = $this->request->post['mpn'];
        $customer_id = $this->customer->getId();
        $data =array();
        if(!isset($customer_id) || !isset($mpn)){
            $error = $this->language->get('error_invalid_request');
        }else {
            $this->load->model('account/customerpartner/market_business');
            $products = $this->model_account_customerpartner_market_business->getProductInformationForPromotion($customer_id,$mpn);
            if(empty($products)){
                $error = $this->language->get('error_no_product');
            }else{
                //取第一个
                $product = current($products);
                $success = array(
                    'product_id'   => $product['product_id'],
                    'item_code'    => $product['sku']
                );
                $data['success'] = $success;
            }
        }
        if(isset($error)){
            $data['error'] = $error;
        }
        $this->response->setOutput(json_encode($data));
    }

    public function validateForm($post_data){
        if (isset($post_data['promotion_value'])) {
            if ($post_data['seller_market_status'] != 0 && $post_data['seller_market_status'] != 1){
                $error = $this->language->get('error_invalid_request');
            }
            if (!isset($post_data['promotion_id'])){
                $error = $this->language->get('error_invalid_request');
            }
            foreach ($post_data['promotion_value'] as $index => $tag_value) {
                if (!isset($tag_value['product_id'])) {
                    $error = $this->language->get('error_invalid_request');
                } else if (utf8_strlen($tag_value['description']) > 5000) {
                    $error = $this->language->get('error_max_description');
                }
            }
        }

        if(isset($error)){
            session()->set('error_warning', $error);
            return false;
        }
        return true;
    }
}