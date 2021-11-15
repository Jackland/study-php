<?php

/**
 * Class ControllerAccountCustomerpartnerProductfreight
 * @property ModelAccountCustomerpartnerProductList $model_account_customerpartner_productlist
 * @property ModelCatalogTag $model_catalog_tag
 */
class ControllerAccountCustomerpartnerProductfreight extends Controller
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/account','',true));
            return;
        }
    }

    private $data = array();

    public function index()
    {
        $this->language->load('account/customerpartner/productfreight');
        $this->document->setTitle($this->language->get('page_title'));

        //top
        $this->data['breadcrumbs'] = array();

        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('banner_2'),
            'href' => 'javascript:void(0);',
            'separator' => $this->language->get('text_separator')
        );
        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('banner_3'),
            'href' => 'javascript:void(0);',
            'separator' => $this->language->get('text_separator')
        );
        if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
            $this->data['separate_view'] = true;
            $this->data['column_left'] = '';
            $this->data['column_right'] = '';
            $this->data['content_top'] = '';
            $this->data['content_bottom'] = '';
            $this->data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $this->data['margin'] = "margin-left: 18%";
            $this->data['footer'] = $this->load->controller('account/customerpartner/footer');
            $this->data['header'] = $this->load->controller('account/customerpartner/header');
        }
        $this->data['language'] = $this->language->load('account/customerpartner/productfreight');
        // get fee
        $this->data['get_fee_url'] = 'index.php?route=account/customerpartner/productfreight/get_fee';
        $this->data['autocomplete_url'] = 'index.php?route=account/customerpartner/productfreight/autocomplete_mpn';
        $this->data['freight_download'] = 'index.php?route=account/customerpartner/productfreight/freight_download';
        $this->data['is_usa'] = $this->customer->isUSA();
        if ($this->customer->isUSA()) {
            $this->data['language']['table_column_5'] = $this->data['language']['table_column_5_ds'];
        }

        $this->response->setOutput($this->load->view('account/customerpartner/productfreight', $this->data));
    }

    // 获取运费和打包费
    public function get_fee()
    {
        $code = trim($this->request->get('code', ''));
        $page = get_value_or_default($this->request->get, 'page', 1);
        $per_page = get_value_or_default($this->request->get, 'per_page', 15);
        if (!is_numeric($page)) {
            $page = 1;
        }
        if (!is_numeric($per_page)) {
            $per_page = 15;
        }
        $this->load->model('account/customerpartner/productlist');
        $this->load->model('catalog/tag');
        $res = $this->model_account_customerpartner_productlist->get_fee($this->customer->getId(), $code, $page, $per_page);

        $all_tags = $this->model_catalog_tag->get_all_tags();
        //费用添加$
        foreach ($res['rows'] as $k => &$v) {
            $v['freight'] = $v['freight']?$this->currency->formatCurrencyPrice($v['freight'], $this->session->data['currency']):'N/A';
            $v['package_fee_h'] = $v['package_fee_h'] ? $this->currency->formatCurrencyPrice($v['package_fee_h'], $this->session->data['currency']) : 'N/A';
            $v['package_fee_d'] = $v['package_fee_d'] ? $this->currency->formatCurrencyPrice($v['package_fee_d'], $this->session->data['currency']) : 'N/A';
            $v['tag_id'] = empty($v['tag_id']) ? $v['tag_id'] :explode(',', $v['tag_id']);
            $v['tags_html'] = '';
            if ($v['tag_id'] && $all_tags){
                foreach ($v['tag_id'] as $tag) {
                    if (isset($all_tags[$tag])){
                        $v['tags_html'] .= $all_tags[$tag]['image_icon_url'];
                    }
                }
            }
        }
        $this->response->returnJson($res);
    }

    //获取item code
    public function autocomplete_mpn()
    {
        $code = trim($this->request->post('code', ''));  //传入的输入部分
        $this->load->model('account/customerpartner/productlist');
        $res = $this->model_account_customerpartner_productlist->autocomplete_mpn($this->customer->getId(), $code);
        $this->response->returnJson($res);
    }

    //seller productlist  运费查询下载
    public function freight_download()
    {
        $this->language->load('account/customerpartner/productfreight');
        $code = trim($this->request->get('code', ''));  //传入的输入部分
        $this->load->model('account/customerpartner/productlist');
        $res = $this->model_account_customerpartner_productlist->get_fee_all($this->customer->getId(), $code);
        $csv_data = array();
        foreach ($res as $k => $v) {
            $temp = [
                $k + 1,
                $v['sku'],
                $v['mpn'],
                $v['freight'] ? $this->currency->formatCurrencyPrice($v['freight'], $this->session->data['currency']) : 'N/A',
                $v['package_fee_d'] ? $this->currency->formatCurrencyPrice($v['package_fee_d'], $this->session->data['currency']) : 'N/A',
            ];
            $this->customer->isUSA() && $temp[] = $v['package_fee_h'] ? $this->currency->formatCurrencyPrice($v['package_fee_h'], $this->session->data['currency']) : 'N/A';
            $csv_data[] = $temp;
        }
        unset($res);
        $head = [
            $this->language->get('table_column_1'),
            $this->language->get('table_column_2'),
            $this->language->get('table_column_3'),
            $this->language->get('table_column_4'),
            $this->language->get($this->customer->isUSA() ? 'table_column_5_ds' : 'table_column_5'),
        ];
        $this->customer->isUSA() && $head[] = $this->language->get('table_column_6');
        outputCsv($this->language->get('page_title') . '_' . time() . '.csv', $head, $csv_data, $this->session);
    }
}
