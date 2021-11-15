<?php

use App\Repositories\Seller\SellerRepository;

/**
 * Class ControllerAccountCustomerpartnerMargin
 * @property ModelAccountCustomerpartnerMargin $model_account_customerpartner_margin
 * @property ModelCatalogInformation $model_catalog_information
 * @property ModelCommonProduct $model_common_product
 */
class ControllerAccountCustomerpartnerMargin extends Controller
{
    const COUNTRY_JAPAN = 107;

    public $precision;
    public $symbol;
    public $crumbs;

    public function __construct($registry)
    {
        //判断登录情况
        parent::__construct($registry);
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/account', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        if (!$this->customer->isPartner()) {
            $this->response->redirect($this->url->link('account/account', '', true));
        }
        //初始化添加样式
        $this->document->addStyle('catalog/view/javascript/product/element-ui.css');
        $this->document->addScript('catalog/view/javascript/product/element-ui.js');
        //初始化时自动加载
        $this->load->language('account/customerpartner/margin');
        $this->load->model('account/customerpartner/margin');
        $this->load->model('common/product');
        //处理类变量
        $this->precision =$this->currency->getDecimalPlace($this->session->data['currency']);
        $this->symbol = $this->currency->getSymbolLeft($this->session->data['currency']);
        if (empty($this->symbol)) {
            $this->symbol = $this->currency->getSymbolRight($this->session->data['currency']);
        }
        if (empty($this->symbol)) {
            $this->symbol = '$';
        }

        //面包屑导航
        $this->crumbs = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home', '', true)
            ],
            [
                'text' => $this->language->get('heading_seller_center'),
                'href' => $this->url->link('customerpartner/seller_center/index', '', true)
            ],
            [
                'text' => $this->language->get('heading_parent_title'),
                'href' => 'javascript:void(0);'
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('account/customerpartner/margin', '', true)
            ]
        ];
    }

    public function page_data(){
        //页面框架数据
        $page_data=array();
        $page_data['column_left'] = $this->load->controller('common/column_left');
        $page_data['column_right'] = $this->load->controller('common/column_right');
        $page_data['content_top'] = $this->load->controller('common/content_top');
        $page_data['content_bottom'] = $this->load->controller('common/content_bottom');
        $page_data['footer'] = $this->load->controller('common/footer');
        $page_data['header'] = $this->load->controller('common/header');
        $page_data['separate_view'] = false;
        $page_data['separate_column_left'] = '';
        if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
            $page_data['separate_view'] = true;
            $page_data['column_left'] = '';
            $page_data['column_right'] = '';
            $page_data['content_top'] = '';
            $page_data['content_bottom'] = '';
            $page_data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $page_data['footer'] = $this->load->controller('account/customerpartner/footer');
            $page_data['header'] = $this->load->controller('account/customerpartner/header');
        }
        return $page_data;
    }

    public function format_num($val){
        return sprintf('%.'.$this->precision.'f',round($val,$this->precision));
    }

    // 列表

    /**
     * @deprecated
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function index()
    {
        return $this->redirect('customerpartner/margin/contract');

        {    //加载模块
            //需要替换title
            $this->document->setTitle($this->language->get('heading_title'));
        }
        {    //页面信息
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
            $data['breadcrumbs'] = $this->crumbs;
            //页面框架数据
            $data=array_merge($data,$this->page_data());
        }
        {    //获取数据
            $customer_id = $this->customer->getId();
            $filter_sku_mpn = trim($this->request->get('sku_mpn', null));
            $page = isset($this->request->get['page'])?$this->request->get['page']:1;
            $page_limit = isset($this->request->get['page_limit'])?$this->request->get['page_limit']:15;
            $order = isset($this->request->get['order'])?$this->request->get['order']:'DESC';

            $filter_data = [
                'customer_id' => $customer_id,
                'filter_sku_mpn' => $filter_sku_mpn,
                'start' => ($page - 1) * $page_limit,
                'limit' => $page_limit,
                'order'=>$order
            ];
        }
        {   //校验数据

        }
        {   //处理数据
            $template_total = $this->model_account_customerpartner_margin->getMarginTemplateTotal_new($filter_data);
            $template_source = $this->model_account_customerpartner_margin->getMarginTemplateDisplay_new($filter_data);
            if (!empty($template_source)) {
                $precision =$this->precision;
                $symbol=$this->symbol;
                foreach ($template_source as $key => &$item) {
                    foreach ($item['tpl'] as $k=>&$v){
                        $item['price']=sprintf('%.'.$precision.'f',round( $item['price'],$precision));
                        $v['price']=sprintf('%.'.$precision.'f',round( $v['price'],$precision));
                        //先计算单个折扣后的价格，四舍五入，再计算总价格
                        $product_discount_per=round($v['price']*$item['margin_rate']/100,$precision);
                        if($v['min']==$v['max']){
                            $v['min_max']=$v['min'];
                            $v['earnest_money']= $this->format_num($v['min']*$product_discount_per);
                            $v['agreement_amount']= $this->format_num($v['min']*$v['price']);
                        }else{
                            $v['min_max']=$v['min'].'-'.$v['max'];
                            $v['earnest_money']= $this->format_num($v['min']*$product_discount_per).'-'.$this->format_num($v['max']*$product_discount_per);
                            $v['agreement_amount']=$this->format_num($v['min']*$v['price']).'-'.$this->format_num($v['max']*$v['price']);
                        }
                        $v['tail_per']=sprintf('%.'.$precision.'f',round($v['price']*(1-$item['margin_rate']/100),$precision));
                    }
                    $tmp = array_merge($item,[
                        'edit'      => $this->url->link('account/customerpartner/margin/edit', 'product_id=' .$item['product_id'], true),
                        'delete'    => $this->url->link('account/customerpartner/margin/delete', 'product_id=' .$item['product_id'], true),
                    ]);
                    $tmp['product_url']=$this->url->link('product/product', 'product_id=' . $item['product_id'] . "&product_token=" . session('product_token'));
                    $data['margin_templates'][] = $tmp;
                }
            }
        }
        {   //页面赋值处理
            $data['start_no'] = ($page - 1) * $page_limit + 1;
            //默认为展开状态
            $data['expand_status']=isset($this->request->get['expand_status'])?$this->request->get['expand_status']:'collapse';
            //批量删除
            $data['multiple_delete_url']= $this->url->link('account/customerpartner/margin/delete');
            //分页
            $page_size = get_value_or_default($this->request->request, 'page_limit', $page_limit);
            $pagination = new Pagination();
            $pagination->total = $template_total;
            $pagination->page = $page;
            $pagination->limit = $page_size;
            $url = '';
            if (isset($this->request->get['sku_mpn'])) {
                $url .= '&sku_mpn=' . urlencode(html_entity_decode($this->request->get['sku_mpn'], ENT_QUOTES, 'UTF-8'));
            }
            $pagination->url = $this->url->link('account/customerpartner/margin', '' . $url . '&page={page}', true);
            $tmp_order=($order=='ASC')?'DESC':'ASC';
            $data['order_pic']=$order;
            $data['sort_name']= $this->url->link('account/customerpartner/margin', '' . $url . '&page='.$page.'&page_limit='.$page_size.'&order='.$tmp_order, true);
            $data['add_action'] = $this->url->link('account/customerpartner/margin/add', '', true);
            $data['list_url']   = $this->url->link('account/customerpartner/margin', '', true);
            $data['download_url']   = $this->url->link('account/customerpartner/margin/download', '', true);
            $data['filter_sku_mpn'] = $filter_sku_mpn;
            $data['pagination'] = $pagination->render();
            $data['results'] = sprintf($this->language->get('text_pagination'),
                ($template_total) ? (($page - 1) * $page_size) + 1 : 0,
                ((($page - 1) * $page_size) > ($template_total - $page_size)) ? $template_total : ((($page - 1) * $page_size) + $page_size),
                $template_total, ceil($template_total / $page_size));
            //替换label的货币符号
            $data['column_price_special'] = sprintf($this->language->get("column_price_special"), $this->symbol);
            $data['column_amount'] = sprintf($this->language->get("column_amount"), $this->symbol);
        }
        //输出
        $this->response->setOutput($this->load->view('account/customerpartner/margin', $data));
    }

    //下载

    /**
     * @deprecated
     */
    public function download(){
        $filter_sku_mpn = trim($this->request->get('sku_mpn', null));
        $filter_data = [
            'customer_id' => $this->customer->getId(),
            'filter_sku_mpn' => $filter_sku_mpn,
            'order'=>'DESC'
        ];
        $template_source = $this->model_account_customerpartner_margin->getMarginTemplateDisplay_new($filter_data);

        //展示---小数点位数
        $precision = $this->precision;
        //货币符号
        $symbol = $this->symbol;

        //csv 数据处理
        $head=array('Item Code','MPN','Current Unit Price('.$symbol.'/Unit)','Margin Rate','Selling Quantity','Days to Purchase Order','Custom Price('.$symbol.'/Unit)','Margin','Final Payment Unit Price','Agreement amount ','Default','Time Modified');

        $body=array();
        foreach ($template_source as $k=>$v){
            foreach ($v['tpl'] as $kk=>$vv){
                //先计算单个折扣后的价格，四舍五入，再计算总价格
                $product_discount_per=round($vv['price']*$v['margin_rate']/100,$precision);
                if($vv['min']==$vv['max']){
                    $sell_qty=$vv['min'];
                    $margin=sprintf('%.'.$precision.'f',round($vv['min']*$product_discount_per,$precision));
                    $agreement_amount=sprintf('%.'.$precision.'f',round($vv['min']*$vv['price'],$precision));
                }else{
                    $sell_qty=($vv['min'].'-'.$vv['max']);
                    $margin=sprintf('%.'.$precision.'f',round($vv['min']*$product_discount_per,$precision)).'-'.sprintf('%.'.$precision.'f',round($vv['max']*$product_discount_per,$precision));
                    $agreement_amount=sprintf('%.'.$precision.'f',round($vv['min']*$vv['price'],$precision)).'-'.sprintf('%.'.$precision.'f',round($vv['max']*$vv['price'],$precision));
                }
                $body[]=array(
                    "\t".$v['sku'],
                    $v['mpn'],
                    sprintf('%.'.$precision.'f',round( $v['price'],$precision)),
                    $v['margin_rate'].'%',
                    "\t".$sell_qty,
                    $vv['day'],
                    "\t".sprintf('%.'.$precision.'f',round( $vv['price'],$precision)),
                    "\t".$margin,
                    "\t".sprintf('%.'.$precision.'f',round($vv['price']*(1-$v['margin_rate']/100),$precision)),
                    "\t".$agreement_amount,
                    ($vv['is_dafault'])?'Yes':'No',
                    $v['update_time']
                );
            }
        }
        //输出
        $fileName='marginofferings_'.date('Ymd',time()).'.csv';
        outputCsv($fileName,$head,$body,$this->session);
    }


    public function add()
    {
        $customer_id = $this->customer->getId();
        if ((request()->isMethod('POST'))) {
            $post_data = $this->request->post;
            $product_id = $post_data['product_id'];
            $product_info = $this->get_product_info($product_id);
            {   //数据合法性校验
                if (!isset($post_data['product_id']) || !$post_data['product_id']) {    //非法参数--没有product_id
                    $this->response->returnJson(array('error' => 2, 'msg' => $this->language->get('notice_productid_error')));   //$this->language->get('error_form_submit')
                }
                if (!isset($post_data['margin_template']) || !$post_data['margin_template']) {  //非法参数--没有获取到margin template（没有比例）
                    $this->response->returnJson(array('error' => 2, 'msg' => $this->language->get('notice_no_tpl')));   //
                }
                if (!isset($post_data['tpl']) || !$post_data['tpl']) {    //非法参数--没有获取到模板参数
                    $this->response->returnJson(array('error' => 2, 'msg' => $this->language->get('notice_no_tpl')));  //$this->language->get('error_form_submit')
                }
                foreach ($post_data['tpl'] as $k => $v) {
                    if (!isset($v['row_min']) || $v['row_min'] < 5 || !$v['row_min']) {     //table错误  --最小值错误
                        $this->response->returnJson(array('error' => 2, 'msg' => $this->language->get('error_min_num')));
                    }
                    if (!isset($v['row_max']) || !$v['row_max'] || $v['row_max'] < $v['row_min']) {   //table错误  --最大值错误
                        $this->response->returnJson(array('error' => 2, 'msg' => $this->language->get('error_min_max')));
                    }
                    if (!isset($v['row_exc']) || $v['row_exc'] < 0) {   //table错误  --价格错误
                        $this->response->returnJson(array('error' => 2, 'msg' => $this->language->get('error_price')));
                    }
                    if (!isset($v['row_day']) || $v['row_day'] <= 0 || $v['row_day'] > 120) {     //table错误  --day错误
                        $this->response->returnJson(array('error' => 2, 'msg' => $this->language->get('error_day')));
                    }
                }
                //比例发生变化
                $row_max_max = max(array_column($post_data['tpl'], 'row_max'));
                if ($row_max_max > $product_info['quantity']) {    // 库存发生变化--现有的数量不足
                    $this->response->returnJson(array('error' => 2, 'msg' => $this->language->get('notice_qty_change')));   //$this->language->get('error_form_submit')
                }
                $row_exc_max = max(array_column($post_data['tpl'], 'row_exc'));
                if ($row_exc_max > round($product_info['price'] * 1.2, $this->precision)) {     // 价格发生变化 -- 模板价格大于现有价格
                    $this->response->returnJson(array('error' => 2, 'msg' => $this->language->get('notice_price_change')));   //$this->language->get('error_form_submit')
                }
            }
            foreach ($post_data['tpl'] as $k => $v) {
                $data[] = [
                    'id' => is_numeric($v['row_id']) ? $v['row_id'] : 0,
                    'bond_template_id' => is_string($post_data['margin_template']) ? $post_data['margin_template'] : 0,
                    'product_id' => $post_data['product_id'],
                    'min_num' => (int)$v['row_min'],
                    'max_num' => (int)$v['row_max'],
                    'price' => $v['row_exc'],
                    'payment_ratio' => sprintf('%.2f', trim($post_data['margin_rate'], '%')),
                    'day' => $v['row_day'],
                    'is_default' => $v['row_is_default']
                ];
            }
            if (!empty($data)) {
                $res = $this->save($customer_id, $data);
                $this->response->returnJson(array('error' => 0, 'msg' => $this->language->get('save_success')));
            }
            $this->response->returnJson(array('error' => 1, 'msg' => $this->language->get('save_failed')));
        } else {
            $this->document->setTitle($this->language->get('heading_title_add'));
            $data = array();
            $data['curr_action'] = 'add';
            $data['heading_title'] = $this->language->get("heading_title_add");
            $data['breadcrumbs'] = array_merge($this->crumbs, [[
                'text' => $this->language->get('heading_title_add'),
                'href' => $this->url->link('account/customerpartner/margin/add', '', true)
            ]]);

            $data['bond_template'] = $this->model_account_customerpartner_margin->getBondTemplateList($this->config->get('bond_template_module_id'));

            $this->load->model('catalog/information');
            $information_info = $this->model_catalog_information->getInformation($this->config->get('margin_information_id_seller'));
            if (!empty($information_info)) {
                $data['clause_url'] = $this->url->link('information/information', 'information_id=' . $information_info['information_id'], true);
                $data['clause_title'] = $information_info['title'];
            }

            $data['save_action'] = $this->url->link('account/customerpartner/margin/add', '', true);

            //替换label的货币符号
            $symbol = $this->symbol;
            $data['precision'] = $this->precision;
            $data['column_form_amount'] = sprintf($this->language->get("column_form_amount"), $symbol);
            $data['column_form_price'] = sprintf($this->language->get("column_form_price"), $symbol);
            $data['column_form_ex_price'] = sprintf($this->language->get("column_form_ex_price"), $symbol);
            //页面框架数据
            $data = array_merge($data, $this->page_data());
            $data['is_outer'] = $this->customer->isNonInnerAccount() ? 1 : 0;
            $data['alarm_price'] = 0;
            // 是否显示云送仓提醒
            $data['is_show_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();
            $this->response->setOutput($this->load->view('account/customerpartner/margin_form', $data));
        }
    }

    //现货保证金三期新增
    public function edit(){
        $customer_id = $this->customer->getId();
        $this->document->setTitle($this->language->get('heading_title_edit'));

        $product_id = $this->request->get['product_id'];
        if (!isset($product_id)) {
            $this->index();
        }

        $data = array();
        $data['curr_action']='edit';
        $data['heading_title'] = $this->language->get("heading_title_edit");

        //导航数据
        $data['breadcrumbs'] = array_merge($this->crumbs,[
            [
                'text' => $this->language->get('heading_title_edit'),
                'href' => $this->url->link('account/customerpartner/margin/edit', 'product_id=' .$product_id, true)
            ]
        ]);
        //页面框架数据
        $data=array_merge($data,$this->page_data());
        //标题替换
//        $data['header']=str_replace( '<title></title>','<title>'.$this->document->getTitle().'</title>',$data['header']);

        {   //用户协议
            $this->load->model('catalog/information');
            $information_info = $this->model_catalog_information->getInformation($this->config->get('margin_information_id_seller'));
            if (!empty($information_info)) {
                $data['clause_url'] = $this->url->link('information/information', 'information_id=' . $information_info['information_id'], true);
                $data['clause_title'] = $information_info['title'];
            }
        }

        $symbol = $this->symbol;
        $data['bond_template'] = $this->model_account_customerpartner_margin->getBondTemplateList($this->config->get('bond_template_module_id'));
        $template = $this->model_account_customerpartner_margin->getProductTemplate($customer_id,$product_id);
        $data['sku']=reset($template['template_list'])['sku'];

        $data['save_action'] = $this->url->link('account/customerpartner/margin/add', '', true);

        //替换label的货币符号
        $data['precision'] =$this->precision;
        $data['column_form_amount'] = sprintf($this->language->get("column_form_amount"), $symbol);
        $data['column_form_price'] = sprintf($this->language->get("column_form_price"), $symbol);
        $data['column_form_ex_price'] = sprintf($this->language->get("column_form_ex_price"), $symbol);
        $data['alarm_price'] = $this->model_common_product->getAlarmPrice($product_id);
        $data['is_outer'] = $this->customer->isNonInnerAccount() ? 1 : 0;
        // 是否显示云送仓提醒
        $data['is_show_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();

        $this->response->setOutput($this->load->view('account/customerpartner/margin_form', $data));
    }

    /**
     * @deprecated
     */
    public function delete()
    {
        $product_id = $this->request->post['product_id'];
        $customer_id = $this->customer->getId();
        $res = false;
        if (isset($customer_id)) {
            if (!is_array($product_id)) {
                $product_id_list = [$product_id];
            } else {
                $product_id_list = $product_id;
            }
            if($product_id_list){
                $res = $this->model_account_customerpartner_margin->delete_templater_from_productid($customer_id, $product_id_list);
            }
        }
        //统一变量格式
        $rtn = $res ? 1 : 0;
        $this->response->setOutput(json_encode($rtn));

    }

    /**
     * 新增和修改操作的保存逻辑
     *
     * @param int $customer_id
     * @param $data
     * @return bool
     * @throws Exception
     */
    private function save($customer_id, $data)
    {
        $flag = $this->model_account_customerpartner_margin->saveMarginTemplate($customer_id,$data);
        return $flag;
    }


    /**
     * 匹配商品，联想sku，mpn
     *
     * @throws Exception
     */
    public function matchProduct()
    {
        $this->load->language('account/customerpartner/margin');
        $mpn = $this->request->post['mpn_sku'];
        $customer_id = $this->customer->getId();
        $country_id = $this->customer->getCountryId();
        $data = array();
        if (!isset($customer_id) || !isset($mpn)) {
            $error = $this->language->get('error_invalid_request');
        } else {
            $products = $this->model_account_customerpartner_margin->getProductInformationForPromotion($customer_id, $mpn);
            if (empty($products)) {
                $error = $this->language->get('error_no_product');
            } else {
                //取第一个
                $product = current($products);
                if (self::COUNTRY_JAPAN == $country_id){
                    $format = '%d';
                    $ws = 0;
                    $product['price'] = round($product['price']);
                }else{
                    $format = '%.2f';
                    $ws = 2;
                }
                $template = $this->model_account_customerpartner_margin->getProductTemplate($customer_id, $product['product_id']);

                if (!empty($template)){
                    foreach ($template['template_list'] as $k => $v){
                        $deposit_per = round($v['price'] * $v['payment_ratio'] / 100, $ws);
                        $min = sprintf($format, $v['min_num'] * $deposit_per);
                        $max = sprintf($format, $v['max_num'] * $deposit_per);

                        $amount_text = $min.' ~ '.$max;
                        if($min == $max){
                            $amount_text = $min;
                        }

                        $template['template_list'][$k]['i']         = $k;
                        $template['template_list'][$k]['price']     = sprintf($format, round($v['price'],2),$ws);
                        $template['template_list'][$k]['amount_text'] = $amount_text;
                        $template['template_list'][$k]['payment_ratio'] = $v['payment_ratio'].'%';
                        $template['template_list'][$k]['edit'] = false;
                    }
                    $count = count($template['template_list']);
                    $success = [
                        'product_id'        => $product['product_id'],
                        'bond_template_id'  => $template['template_list'][0]['bond_template_id'],
                        'margin_template'   => $template['margin_template'],
                        'payment_ratio'     => $template['template_list'][0]['payment_ratio'],
                        'mpn'           => $product['mpn'],
                        'sku'           => $product['sku'],
                        'price'         => $product['price'],
                        'quantity'      => $product['quantity'],
                        'alarm_price' => $this->model_common_product->getAlarmPrice($product['product_id']),
                    ];
                    $success['template'] = $template['template_list'];

                }else{
                    $success = array(
                        'product_id' => $product['product_id'],
                        'sku' => $product['sku'],
                        'mpn' => $product['mpn'],
                        'quantity' => $product['quantity'],
                        'price' => $product['price'],
                        'price_format' => $this->currency->format($product['price'], $this->session->data['currency']),
                        'alarm_price' => $this->model_common_product->getAlarmPrice($product['product_id']),
                    );
                }
                $data['success'] = $success;
            }

        }
        if (isset($error)) {
            $data['error'] = $error;
        }
        $this->response->setOutput(json_encode($data));
    }


    /**
     * 现货保证金添加
     * zjg
     * 2020年3月12日15:22:47
     */

    //  用于替换productInfo
    public function get_product_info($product_id){
        return $this->model_account_customerpartner_margin->getProductById($product_id);
    }

    //autocomplete
    public function get_sku_autocomplete(){
        $code=$this->request->post['code'];
        $customer_id=$this->customer->getId();
        $res=$this->model_account_customerpartner_margin->get_sku_autocomplete($code,$customer_id);
        $this->response->returnJson($res);
    }



}
