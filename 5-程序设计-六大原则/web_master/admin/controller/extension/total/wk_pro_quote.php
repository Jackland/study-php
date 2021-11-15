<?php

/**
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCatalogwkquotesadmin $model_catalog_wk_quotes_admin
 * @property ModelCustomerCustomerGroup $model_customer_customer_group
 * @property ModelSettingSetting $model_setting_setting
 */
class ControllerExtensionTotalwkproquote extends Controller
{

    private $error = array();
    private $data = array();

    public function install()
    {
        $this->load->model('catalog/wk_quotes_admin');
        $this->model_catalog_wk_quotes_admin->createTableQuote();
    }

    public function index()
    {
        $this->load->language('extension/total/wk_pro_quote');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if ((request()->isMethod('POST')) && $this->validate()) {
            $this->model_setting_setting->editSetting('total_wk_pro_quote', $this->request->post);

            session()->set('success', $this->language->get('text_success'));

            $this->response->redirect($this->url->link('extension/total/wk_pro_quote', 'user_token=' . session('user_token'), true));

            	$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=total', true));
        }
        //CONFIG
        $config_data = array(
         'total_wk_pro_quote_status',
         'total_wk_pro_quote_product_status',
         'total_wk_pro_quote_quantity',
         'total_wk_pro_quote_name',
         'total_wk_pro_quote_email',
         'total_wk_pro_quote_worked_quantity',
         'total_wk_pro_quote_time',
         'total_wk_pro_quote_customer',
         'total_wk_pro_quote_products',
         'total_wk_pro_quote_sort_order',
         'total_wk_pro_quote_seller_add',
         'total_wk_pro_quote_seller_quantity',
         'total_wk_pro_quote_seller_product_status'
        );

        foreach ($config_data as $conf) {
            if (isset($this->request->post[$conf])) {
                $data[$conf] = $this->request->post[$conf];
            } else {
                $data[$conf] = $this->config->get($conf);
            }
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

        $data['breadcrumbs'] = array();

    		$data['breadcrumbs'][] = array(
    			'text' => $this->language->get('text_home'),
    			'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
    		);

    		$data['breadcrumbs'][] = array(
    			'text' => $this->language->get('text_extension'),
    			'href' => $this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=total', true)
    		);

    		$data['breadcrumbs'][] = array(
    			'text' => $this->language->get('heading_title'),
    			'href' => $this->url->link('extension/total/wk_pro_quote', 'user_token=' . session('user_token'), true)
    		);

        $data['action'] = $this->url->link('extension/total/wk_pro_quote', 'user_token=' . session('user_token'), true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=total', true);

        $data['manage_quote'] = $this->url->link('catalog/wk_quotes_admin', 'user_token=' . session('user_token'), true);

        $this->load->model('customer/customer_group');
        $data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();

        $this->load->model('catalog/product');

        if ($data['total_wk_pro_quote_products']) {
            foreach ($data['total_wk_pro_quote_products'] as $key => $product_id) {
                   $product_info = $this->model_catalog_product->getProduct($product_id);

                if ($product_info) {
                    $data['total_wk_pro_quote_products'][$key] = array(
                     'product_id' => $product_info['product_id'],
                     'name'       => $product_info['name']
                    );
                }
            }
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/total/wk_pro_quote', $data));

    }

    private function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/total/wk_pro_quote')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->error) {
            return true;
        } else {
            return false;
        }
    }
    public function uninstall()
    {
        $this->load->model('catalog/wk_quotes_admin');
        $this->model_catalog_wk_quotes_admin->deleteTableQuote();
    }
}
?>
