<?php

use App\Helper\CountryHelper;
use App\Repositories\Seller\SellerRepository;
use Cart\Currency;

/**
 * Class ControllerAccountCustomerpartnerRebates
 *
 * @property ModelAccountCustomerpartnerRebates $model_account_customerpartner_rebates'
 * @property ModelCatalogInformation $model_catalog_information
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCommonProduct $model_common_product
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountCustomerpartnerRebates extends Controller
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/customerpartner/rebates/edit_tpl&id=342', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        if (!$this->customer->isPartner()) {
            $this->response->redirect($this->url->link('account/account', '', true));
        }
    }

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

//        $this->getList();
        $this->get_list();
    }

    // 返点4期---返点模板列表
    private function get_list(){
        //import
        $this->load->language('account/customerpartner/rebates');
        $this->load->model('account/customerpartner/rebates');
        $this->document->setTitle($this->language->get('heading_title'));
        $customer_id = $this->customer->getId();
        $data = array();
        $data['language']=$this->load->language('account/customerpartner/rebates');
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
                'text' => $this->language->get('heading_parent_title'),
                'href' => 'javascript:void(0);'
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('account/customerpartner/rebates', '', true)
            ]
        ];

        $filter_sku_mpn = trim($this->request->get('sku_mpn', null));

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }
        if (isset($this->request->get['page_limit'])) {
            $page_limit = $this->request->get['page_limit'];
        } else {
            $page_limit = 10;
        }
        $filter_data = [
            'customer_id' => $customer_id,
            'filter_sku_mpn' => $filter_sku_mpn,
            'start' => ($page - 1) * $page_limit,
            'limit' => $page_limit
        ];
        $data['start_no'] = ($page - 1) * $page_limit + 1;

        $template_total = $this->model_account_customerpartner_rebates->get_rebates_template_total($filter_data);
//        $template_total = $this->model_account_customerpartner_rebates->getRebatesTemplateTotal($filter_data);
        $template_source = $this->model_account_customerpartner_rebates->get_rebates_template_display($filter_data);
//        $template_source = $this->model_account_customerpartner_rebates->getRebatesTemplateDisplay($filter_data);

        $template_ids = array_column($template_source,'id');
        $used_nums = $this->model_account_customerpartner_rebates->listAgreementUsedNumber($template_ids, $this->customer->getId());

        foreach ($template_source as $k=>&$v){
            if(count($v['child'])==1){
                $v['exclusive_price']=$this->currency->formatCurrencyPrice($v['child'][0]['price'],session('currency'));
            }else{
                $price_list=array_column($v['child'],'price');
                $min_price=min($price_list);
                $max_price=max($price_list);
                $v['exclusive_price']=($min_price==$max_price)?$this->currency->formatCurrencyPrice($max_price,session('currency')):$this->currency->formatCurrencyPrice($min_price,session('currency')).'-'.$this->currency->formatCurrencyPrice($max_price,session('currency'));
            }
            $tmp_rebate=explode('_',$v['item_rebates']);
            if($v['rebate_type']==0){//百分比
                // 如果为单个数字 可能是百分比，如果不是数字，一定是价格区间
                if(count($tmp_rebate)==1){
                    $is_rebate=true;    //是比例
                    foreach ($v['child'] as $tmp_product){
                        if(abs(round($tmp_product['price']*$v['rebate_value']/100,2)-$tmp_product['rebate_amount'])>0.01){
                            $is_rebate=false;    //是金额
                        }
                    }
                    if($is_rebate){
                        $v['rebates']=$tmp_rebate[0].'%';
                    }else{
                        $v['rebates']=$this->currency->formatCurrencyPrice($tmp_rebate[0],session('currency'));
                    }
                }else{
                    $v['rebates']=$this->currency->formatCurrencyPrice(min($tmp_rebate),session('currency')).' - '.$this->currency->formatCurrencyPrice(max($tmp_rebate),session('currency'));
                }
            }else{  //金额
                $v['rebates']=(count($tmp_rebate)==1)?$this->currency->formatCurrencyPrice($tmp_rebate[0],session('currency')):$this->currency->formatCurrencyPrice(min($tmp_rebate),session('currency')).' - '.$this->currency->formatCurrencyPrice(max($tmp_rebate),session('currency'));
            }
            $tmp=array(
                'item'=>array(),
                'item_mpn'=>array()
            );
            foreach ($v['child'] as $kk=>&$vv){
                $vv['image']=$this->check_pic($vv['image']);
                $vv['price']=$this->currency->formatCurrencyPrice($vv['price'],session('currency'));
                $vv['rebate_amount']=$this->currency->formatCurrencyPrice($vv['rebate_amount'],session('currency'));
                $vv['min_sell_price']=$this->currency->formatCurrencyPrice($vv['min_sell_price'],session('currency'));
                $vv['product_url']=$this->url->link('product/product', 'product_id=' . $vv['product_id'] . "&product_token=" . session('product_token'));
                $tmp['item'][]=$vv['sku'];
                $tmp['item_mpn'][]=$vv['sku'].'('.$vv['mpn'].')';
            }
            $v['show_items']=implode(',', $tmp['item']);
            $v['show_items_more']=reset( $tmp['item']).((count($tmp['item'])>1)?(' and '.(count($tmp['item'])-1).' more items'):'');
            $v['show_items_mpn']=implode(',', $tmp['item_mpn']);
            $v['available_places'] = $v['limit_num'] < 0 ? 'No limit' : (($used_nums[$v['id']] ?? 0) . '/' . $v['limit_num']);
        }

        $data['rebates_templates'] = $template_source;
        $pagination = new Pagination();
        $pagination->total = $template_total;
        $pagination->page = $page;
        $pagination->limit = $page_limit;
        $pagination->url = $this->url->link('account/customerpartner/rebates', '&page={page}', true);

        $data['add_action'] = $this->url->link('account/customerpartner/rebates/add', '', true);
        $data['delete_action'] = $this->url->link('account/customerpartner/rebates/delete', '', true);
        $data['download_action'] = $this->url->link('account/customerpartner/rebates/downloadTemplates', '', true);

        $data['filter_sku_mpn'] = $filter_sku_mpn;
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($template_total) ? (($page - 1) * $page_limit) + 1 : 0, ((($page - 1) * $page_limit) > ($template_total - $page_limit)) ? $template_total : ((($page - 1) * $page_limit) + $page_limit), $template_total, ceil($template_total / $page_limit));

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
        $this->response->setOutput($this->load->view('account/customerpartner/rebates', $data));
    }


    //删除返点模板
    public function del_tpl(){
        $this->load->model('account/customerpartner/rebates');
        $id=$this->request->get['id'];
        $type=$this->request->get['type'];
        if($type=='on'){
            $id_list=[$id];
        }else{
            $id_list=explode(',',$id);
        }
        $this->model_account_customerpartner_rebates->del_tpl($id_list,$this->customer->getId());
        $this->response->redirect('index.php?route=account/customerpartner/rebates');
    }

    public function add()
    {
        $this->load->language('account/customerpartner/rebates');
        $this->load->model('account/customerpartner/rebates');

        $this->document->setTitle($this->language->get('heading_title_add'));
        $session = $this->session->data;
        $data = array();
        $data['heading_title'] = $this->language->get("heading_title_add");

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('account/customerpartner/rebates', '', true)
            ],
            [
                'text' => $this->language->get('heading_title_add'),
                'href' => $this->url->link('account/customerpartner/rebates/add', '', true)
            ]
        ];

        $symbol = $this->currency->getSymbolLeft($session['currency']);
        if (empty($symbol)) {
            $symbol = $this->currency->getSymbolRight($session['currency']);
        }
        if (empty($symbol)) {
            $symbol = '$';
        }
        $data['symbol']=$symbol;
        $decimal_place=$this->currency->getDecimalPlace($session['currency']);
        $data['decimal_place']=$decimal_place;
        $data['save_action'] = $this->url->link('account/customerpartner/rebates/add', '', true);
        $data['back_action'] = $this->url->link('account/customerpartner/rebates', '', true);
        $data['auto_action'] = $this->url->link('account/customerpartner/rebates/autoCompleteSku', '', true);
        $this->load->model('catalog/information');
        $information_info = $this->model_catalog_information->getInformation($this->config->get('rebates_seller'));
        if (!empty($information_info)) {
            $data['clause_url'] = $this->url->link('information/information', 'information_id=' . $information_info['information_id'], true);
            $data['clause_title'] = $information_info['title'];
        }
        $data['used_num'] = 0;
        $data['place_limit'] = -1;
        $data['is_non_inner_account'] = $this->customer->isNonInnerAccount();
        $data['tpl']['child'] = [];
        $data['product_price_proportion'] = PRODUCT_PRICE_PROPORTION;
        // 是否显示云送仓提醒
        $data['is_show_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();

        $data['separate_view'] = true;
        $data['column_left'] = '';
        $data['column_right'] = '';
        $data['content_top'] = '';
        $data['content_bottom'] = '';
        $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
        $data['footer'] = $this->load->controller('account/customerpartner/footer');
        $data['header'] = $this->load->controller('account/customerpartner/header');
        $data['language']=$this->load->language('account/customerpartner/rebates');
        $this->response->setOutput($this->load->view('account/customerpartner/rebates_form', $data));
    }

    /***********************
     * 返点四期添加
     * add by zjg
     * 2020年1月20日
     **********************/

    // 获取autocomplete 的sku
    public function get_sku_autocomplete(){
        $code = trim($this->request->post('code', ''));
        $customer_id=$this->customer->getId();
        $this->load->model('account/customerpartner/rebates');
        $res=$this->model_account_customerpartner_rebates->get_sku_autocomplete($code,$customer_id);
        $this->response->returnJson($res);
    }

    //获取产品的属性
    public function get_product_info()
    {
        $code = trim($this->request->get('code', ''));
        $customer_id = $this->customer->getId();
        $this->load->model('common/product');
        $this->load->model('account/customerpartner/rebates');
        $res = $this->model_account_customerpartner_rebates->get_product_info($code, $customer_id);
        //查找color
        $this->load->model('catalog/product');
        $mcp = $this->model_catalog_product;
        $colors = $mcp->getProductOptionValueByProductIds(array_column($res, 'associate_product_id'), 13, $this->customer->getId());
        $decimal_place = $this->currency->getDecimalPlace(session('currency'));
        foreach ($res as $k => &$v) {
            $v['price'] = round($v['price'], $decimal_place);
            $v['price_show'] = $this->currency->formatCurrencyPrice($v['price'], session('currency'));
            $v['image'] = $this->check_pic($v['image']);
            $v['color'] = $colors[$v['associate_product_id']] ?? '';
            $v['freight_package_fee'] = bcadd($v['freight'] ?: 0, $v['package_fee'] ?: 0, $decimal_place);
            $v['product_url'] = $this->url->link('product/product', 'product_id=' . $v['associate_product_id'] . "&product_token=" . session('product_token'), true);
            $v['alarm_price'] = $this->model_common_product->getAlarmPrice($v['associate_product_id']);
        }
        $this->response->returnJson($res);
    }

    //edit 和 copy 情况下 使用模板里的数据
    public function get_rebate_product_info()
    {
        $tpl_id = $this->request->get['code'];
        $data = ['id' => $tpl_id];
        $this->load->model('common/product');
        $this->load->model('account/customerpartner/rebates');
        $template_source = $this->model_account_customerpartner_rebates->get_rebates_template_display($data);
        $rebate_product_id = array_column($template_source[0]['child'], 'product_id');
        $this->load->model('catalog/product');
        $mcp = $this->model_catalog_product;
        $colors = $mcp->getProductOptionValueByProductIds($rebate_product_id, 13, $this->customer->getId());
        $rtn = array();
        foreach ($template_source[0]['child'] as $k => $v) {
            $rtn[] = array(
                'associate_product_id' => $v['product_id'],
                'sku' => $v['sku'],
                'mpn' => $v['mpn'],
                'quantity' => $v['quantity'],
                'curr_price' => $v['curr_price'],
                'price' => $v['price'],
                'image' => $this->check_pic($v['image']),
                'price_show' => $this->currency->formatCurrencyPrice($v['price'], session('currency')),
                'curr_price_show' => $this->currency->formatCurrencyPrice($v['curr_price'], session('currency')),
                'color' => $colors[$v['product_id']] ?? '',
                'product_url' => $this->url->link('product/product', 'product_id=' . $v['product_id'] . "", true),
                'freight_package_fee' => $this->model_account_customerpartner_rebates->getProductFreightAndPackageFee($v['product_id']),
                'alarm_price' => $this->model_common_product->getAlarmPrice($v['product_id']),
            );
        }
        $this->response->returnJson($rtn);
    }

    //check   pic 是否存在----带图片缩放
    public function check_pic($path,$width=400,$height=400){
        //图片缩放
        $this->load->model('tool/image');
        if ($path) {
            $image = $this->model_tool_image->resize($path, 30, 30);
        } else {
            $image = $this->model_tool_image->resize('placeholder.png', 30, 30);
        }
        return $image;
    }

    /**
     * 提交返点模板数据
     * @throws Exception
     */
    public function set_rebate_data(){     //新增和修改
        $this->load->language('account/customerpartner/rebates');
        $rebate_data=$this->request->post;
        //数据校验....合法性和重复
        $param_error=array();
        if(!isset($rebate_data['day'])||!is_numeric($rebate_data['day'])||$rebate_data['day']<=0){
            $param_error[]=$this->language->get('error_sold_day');
        }
        if(!isset($rebate_data['min_quantity'])||!is_numeric($rebate_data['min_quantity'])||$rebate_data['min_quantity']<=0){
            $param_error[]=$this->language->get('error_sold_qty_new');
        }
        if(!isset($rebate_data['rebate'])||!in_array($rebate_data['rebate'],array('rate','amount'))){
            $param_error[]=$this->language->get('error_select_rebate');
        }
        if($rebate_data['rebate']=='rate'){    //百分比
            if(!isset($rebate_data['rebate_val'])||!is_numeric($rebate_data['rebate_val'])||$rebate_data['rebate_val']<=0||$rebate_data['rebate_val']>=100){
                $param_error[]=$this->language->get('error_rabate_value_rate');
            }
        }else{
            if(!isset($rebate_data['rebate_val'])||!is_numeric($rebate_data['rebate_val'])||$rebate_data['rebate_val']<=0){
                $param_error[]=$this->language->get('error_rabate_value_amount');
            }
        }

        if (!isset($rebate_data['place_limit']) || !is_numeric($rebate_data['place_limit']) || (int)$rebate_data['place_limit'] != $rebate_data['place_limit']) {
            $param_error[] = $this->language->get('error_place_limit');
        }

        if(!isset($rebate_data['table'])||!$rebate_data['table']){
            $param_error[]=$this->language->get('error_select_product');
        }
        foreach ($rebate_data['table'] as $k=>$v){
            if(!isset($v['product_id'])||!is_numeric($v['product_id'])||$v['product_id']<=0){
                $param_error[]=$this->language->get('error_cannt_find_product');
            }
            //校验产品是否是自己的
            //参数校验
            if(!isset($v['exc_price'])||!is_numeric($v['exc_price'])||$v['exc_price']<=0){
                $param_error[]=$this->language->get('error_new_exclusive');
            }
            if(!isset($v['rabate_price'])||!is_numeric($v['rabate_price'])||$v['rabate_price']<=0||$v['rabate_price']>=$v['exc_price']){
                $param_error[]=$this->language->get('error_new_rebate');
            }
            if(!isset($v['min_price'])||!is_numeric($v['min_price'])||$v['min_price']<0){
                $param_error[]=$this->language->get('error_new_limit_qty');
            }
        }
        if ($param_error){
            $this->response->returnJson(array('status'=>0,'msg'=>'','data'=>$param_error));
        }
        //业务处理
        $this->load->model('account/customerpartner/rebates');
        if(!$rebate_data['id']){     //id为0  insert
            //检测返点模板是否超过三个
            $counts=$this->model_account_customerpartner_rebates->get_rebate_count(array_column($rebate_data['table'],'product_id'));
            $count_gt_3=array();
            foreach ($counts as $k=>$v){
                if($v['num']>=3){
                    $count_gt_3[$v['product_id']]=$v;
                }
            }
            if(count($count_gt_3)){
                $this->response->returnJson(array('status'=>0,'data'=>array(sprintf($this->language->get('error_tpl_three'),implode(',',array_column($count_gt_3,'sku'))))));
            }
            //业务逻辑
            //获取自增id
            $res=$this->model_account_customerpartner_rebates->get_tpl_id(DB_PREFIX.'rebate_template','rebate_template_id',6);
            if(!$res){
                $increment_id=1;
            }else{
                $increment_id=$res['curr_increment_id']+1;
            }
            $increment_id=sprintf('%06d',$increment_id);
            //组织数据
            //模板item信息
            $tpl_items=array();
            $tpl_items_data=array();
            foreach ($rebate_data['table'] as $k=>$v){
                $tpl_items[]=$v['item_code'].':'.$v['mpn'];
                $tpl_items_data[]=array(
                    'template_id'=>'',
                    'product_id'=>$v['product_id'],
                    'price'=>$v['exc_price'],
                    'rebate_amount'=>$v['rabate_price'],
                    'rest_price'=>round(($v['exc_price'] - $v['rabate_price']),2), // rebate模板中记录rest_price用于排序
                    'min_sell_price'=>$v['min_price'],
                    'create_time'=>date('Y-m-d H:i:s',time()),
                    'update_time'=>date('Y-m-d H:i:s',time())
                );
            }
            $tpl_items=implode(',',$tpl_items);
            //模板信息
            $item_price_list=array_column($tpl_items_data,'price');
            $db_item_price=(min($item_price_list)==max($item_price_list))?min($item_price_list):min($item_price_list).'_'.max($item_price_list);
            $item_rebates_list=array_column($tpl_items_data,'rebate_amount');
            if($rebate_data['rebate']=='rate'){    //百分比形式
                //判断是否修改过值
                $change_flag=false;
                foreach ($item_rebates_list as $k=>$v){
                    if(abs(round($item_price_list[$k]*$rebate_data['rebate_val']/100,2)-$v)>0.01){    //rount 和js tofixed 的差异 ，判断两个值<=0.01,则认定相同
                        $change_flag=true;
                    }
                }
                if($change_flag){    //修改过  取金额小值到大值
                    $db_item_rebate=(min($item_rebates_list)==max($item_rebates_list))?min($item_rebates_list):min($item_rebates_list).'_'.max($item_rebates_list);
                }else{   // 没有修改，取百分比
                    $db_item_rebate=$rebate_data['rebate_val'];
                }
            }else{    // 金额
                $db_item_rebate=(min($item_rebates_list)==max($item_rebates_list))?min($item_rebates_list):min($item_rebates_list).'_'.max($item_rebates_list);
            }
            $tpl_data=array(
                'rebate_template_id'=>date('Ymd',time()).$increment_id,
                'seller_id'=>$this->customer->getId(),
                'day'=>$rebate_data['day'],
                'qty'=>$rebate_data['min_quantity'],
                'rebate_type'=>($rebate_data['rebate']=='rate')?0:1,
                'rebate_value'=>$rebate_data['rebate_val'],
                'limit_num'=>$rebate_data['place_limit'],
                'search_product'=>$rebate_data['product_val'],
                'items'=>$tpl_items,
                'item_num'=>count($rebate_data['table']),
                'item_price'=>$db_item_price,
                'item_rebates'=>$db_item_rebate,
                'create_time'=>date('Y-m-d H:i:s',time()),
                'update_time'=>date('Y-m-d H:i:s',time())
            );
            //save
            $set_status=$this->model_account_customerpartner_rebates->set_tpl($tpl_data,$tpl_items_data);
        }else{     //id 存在 修改数据
            //获取db中模板数据
            $data=array(
                'id'=>$rebate_data['id']
            );
            $tpl_db=$this->model_account_customerpartner_rebates->get_rebates_template_display($data);
            $tpl_db=reset($tpl_db);   //取第一个
            //检测返点模板是否超过三个--抛出自己
            $this->load->model('account/customerpartner/rebates');
            $counts=$this->model_account_customerpartner_rebates->get_rebate_count(array_column($rebate_data['table'],'product_id'));
            $count_gt_3=array();
            $product_self=array_column($tpl_db['child'],'product_id');
            foreach ($counts as $k=>&$v){
                if(in_array($v['product_id'],$product_self)){  //抛出自己
                    $v['num']--;
                }
                if($v['num']>=3){
                    $count_gt_3[$v['product_id']]=$v;
                }
            }
            if(count($count_gt_3)){
                $this->response->returnJson(array('status'=>0,'data'=>array(sprintf($this->language->get('error_tpl_three'),implode(',',array_column($count_gt_3,'sku'))))));
            }
            //数据整理
            $db_bid_product=$tpl_db['child'];
            $db_bid_product=array_combine(array_column($db_bid_product,'product_id'),$db_bid_product);
            $set_product=$rebate_data['table'];
            $set_product=array_combine(array_column($set_product,'product_id'),$set_product);
            //需要删除的
            $delete=array_diff_key($db_bid_product,$set_product);
            //需要增加的
            $add=array_diff_key($set_product,$db_bid_product);
            //需要修改的
            $update=array_intersect_key($set_product,$db_bid_product);
            $add_list=array();
            $update_list=array();
            $delete_list=array();
            if($delete){
                $delete_list=array_column($delete,'id');
            }
            $curr_time=date('Y-m-d H:i:s',time());
            //处理 tpl item 数据
            if($add){
                foreach ($add as $k=>$v){
                    $add_list[]=array(
                        'template_id'=>$tpl_db['id'],
                        'product_id'=>$v['product_id'],
                        'price'=>$v['exc_price'],
                        'rebate_amount'=>$v['rabate_price'],
                        'rest_price' =>round(($v['exc_price'] - $v['rabate_price']),2), // 增加冗余字段进行排序
                        'min_sell_price'=>$v['min_price'],
                        'memo'=>'',
                        'create_time'=>$curr_time,
                        'update_time'=>$curr_time
                    );
                }
            }
            if($update){
                foreach ($update as $k=>$v){
                    $update_list[]=array(
                        'id'=>$db_bid_product[$v['product_id']]['id'],
                        'data'=>array(
                            'price'=>$v['exc_price'],
                            'rebate_amount'=>$v['rabate_price'],
                            'rest_price' =>round(($v['exc_price'] - $v['rabate_price']),2), // 增加冗余字段进行排序
                            'min_sell_price'=>$v['min_price'],
                            'memo'=>'',
                            'create_time'=>$curr_time,
                            'update_time'=>$curr_time
                        )
                    );
                }
            }
            //处理tpl 数据
            $tpl_items=array();
            foreach ($rebate_data['table'] as $k=>$v){
                $tpl_items[]=$v['item_code'].':'.$v['mpn'];
            }
            $tpl_items=implode(',',$tpl_items);
            $item_price_list=array_column($rebate_data['table'],'exc_price');
            $db_item_price=(min($item_price_list)==max($item_price_list))?min($item_price_list):min($item_price_list).'_'.max($item_price_list);
            $item_rebates_list=array_column($rebate_data['table'],'rabate_price');
            if($rebate_data['rebate']=='rate'){    //百分比形式
                //判断是否修改过值
                $change_flag=false;
                foreach ($item_rebates_list as $k=>$v){
                    if(abs(round($item_price_list[$k]*$rebate_data['rebate_val']/100,2)-$v)>0.01){    //rount 和js tofixed 的差异 ，判断两个值<=0.01,则认定相同
                        $change_flag=true;
                    }
                }
                if($change_flag){    //修改过  取金额小值到大值
                    $db_item_rebate=(min($item_rebates_list)==max($item_rebates_list))?min($item_rebates_list):min($item_rebates_list).'_'.max($item_rebates_list);
                }else{   // 没有修改，取百分比
                    $db_item_rebate=$rebate_data['rebate_val'];
                }
            }else{    // 金额
                $db_item_rebate=(min($item_rebates_list)==max($item_rebates_list))?min($item_rebates_list):min($item_rebates_list).'_'.max($item_rebates_list);
            }
            $tpl_modify=array(
                'id'=>$tpl_db['id'],
                'data'=>array(
                    'day'=>$rebate_data['day'],
                    'qty'=>$rebate_data['min_quantity'],
                    'rebate_type'=>($rebate_data['rebate']=='rate')?0:1,
                    'rebate_value'=>$rebate_data['rebate_val'],
                    'limit_num'=>$rebate_data['place_limit'],
                    'search_product'=>$rebate_data['product_val'],
                    'items'=>$tpl_items,
                    'item_num'=>count($rebate_data['table']),
                    'item_price'=>$db_item_price,
                    'item_rebates'=>$db_item_rebate,
                    'create_time'=>$curr_time,
                    'update_time'=>$curr_time
                )
            );
            //入库
            $set_status=$this->model_account_customerpartner_rebates->modify_tpl(array(
                'add'=>$add_list,
                'update'=>$update_list,
                'delete'=>$delete_list,
                'tpl'=>$tpl_modify
            ));

        }
        if($set_status){
            $this->response->returnJson(array('status'=>1,'data'=>$set_status));
        }else{
            $this->response->returnJson(array('status'=>1,'data'=>array($this->language->get('db_error'))));
        }
    }

    //模板编辑
    public function edit_tpl(){
        $this->load->language('account/customerpartner/rebates');
        $this->load->model('account/customerpartner/rebates');
        $this->document->setTitle($this->language->get('heading_title_edit'));
        $session = $this->session->data;
        $id = $this->request->get['id'];

        $data = array();
        $data['tpl_id']=$id;
        $data['language']= $this->load->language('account/customerpartner/rebates');
        $data['heading_title'] = $this->language->get("heading_title_edit");
        $data['curr_page']='modify';
        //导航条
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home', '', true)
            ],
            [
                'text' =>  $this->language->get('Seller Central'),
                'href' => $this->url->link('customerpartner/seller_center/index', '', true),
            ],
            [
                'text' => $this->language->get('heading_parent_title'),
                'href' => 'javascript:void(0);',
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('account/customerpartner/rebates', '', true)
            ],
            [
                'text' => $this->language->get('heading_title_edit'),
                'href' => $this->url->link('account/customerpartner/rebates/edit', 'id=' . $id, true)
            ]
        ];
        //页面主体框架
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

        $this->load->model('catalog/information');
        $information_info = $this->model_catalog_information->getInformation($this->config->get('rebates_seller'));

        if (!empty($information_info)) {
            $data['clause_url'] = $this->url->link('information/information', 'information_id=' . $information_info['information_id'], true);
            $data['clause_title'] = $information_info['title'];
        }

        //符号和位数
        $symbol = $this->currency->getSymbolLeft($session['currency']);
        if (empty($symbol)) {
            $symbol = $this->currency->getSymbolRight($session['currency']);
        }
        if (empty($symbol)) {
            $symbol = '$';
        }
        $data['symbol']=$symbol;
        $decimal_place=$this->currency->getDecimalPlace($session['currency']);
        $data['decimal_place']=$decimal_place;
        // 获取模板数据
        $filter_data = [
            'customer_id' => $this->customer->getId(),
            'id'=>$id
        ];
        $tpl = $this->model_account_customerpartner_rebates->get_rebates_template_display($filter_data);
        $tpl=reset($tpl);
        //检查search_product是否在child中
        $child_sku_list=array_column($tpl['child'],'sku');
        if(!in_array($tpl['search_product'],$child_sku_list)){
            $tpl['search_product']=$child_sku_list[0];
        }
        $tpl['child']=array_combine(array_column($tpl['child'],'product_id'),$tpl['child']);
        $data['tpl']=$tpl;
        $data['default_select']=json_encode(array_column($tpl['child'],'product_id'));    //初始化选中的product
        $data['place_limit'] = $tpl['limit_num'];
        $data['used_num'] = $this->model_account_customerpartner_rebates->getAgreementUsedNumber($id,$this->customer->getId());
        $data['is_non_inner_account'] = $this->customer->isNonInnerAccount();
        $data['product_price_proportion'] = PRODUCT_PRICE_PROPORTION;
        // 是否显示云送仓提醒
        $data['is_show_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();
        $this->response->setOutput($this->load->view('account/customerpartner/rebates_form', $data));
    }

    //复制模板
    public function copy_tpl(){
        $this->load->language('account/customerpartner/rebates');
        $this->load->model('account/customerpartner/rebates');

        $this->document->setTitle($this->language->get('heading_title_copy'));
        $session = $this->session->data;
        $id = $this->request->get['id'];
        $data = array();
        $data['tpl_id']=$id;
        $data['language']= $this->load->language('account/customerpartner/rebates');
        $data['heading_title'] = $this->language->get("heading_title_copy");
        $data['curr_page']='copy';
        //导航条
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home', '', true)
            ],
            [
                'text' => $this->language->get('Seller Central'),
                'href' => $this->url->link('customerpartner/seller_center/index', '', true),
            ],
            [
                'text' => $this->language->get('heading_parent_title'),
                'href' => 'javascript:void(0);',
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('account/customerpartner/rebates', '', true)
            ],
            [
                'text' => $this->language->get('heading_title_copy'),
                'href' => $this->url->link('account/customerpartner/rebates/copy_tpl', 'id=' . $id, true)
            ]
        ];
        //页面主体框架
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

        $this->load->model('catalog/information');
        $information_info = $this->model_catalog_information->getInformation($this->config->get('rebates_seller'));

        if (!empty($information_info)) {
            $data['clause_url'] = $this->url->link('information/information', 'information_id=' . $information_info['information_id'], true);
            $data['clause_title'] = $information_info['title'];
        }

        //符号和位数
        $symbol = $this->currency->getSymbolLeft($session['currency']);
        if (empty($symbol)) {
            $symbol = $this->currency->getSymbolRight($session['currency']);
        }
        if (empty($symbol)) {
            $symbol = '$';
        }
        $data['symbol']=$symbol;
        $decimal_place=$this->currency->getDecimalPlace($session['currency']);
        $data['decimal_place']=$decimal_place;

        // 获取模板数据
        $filter_data = [
            'customer_id' => $this->customer->getId(),
            'id'=>$id
        ];
        $tpl = $this->model_account_customerpartner_rebates->get_rebates_template_display($filter_data);
        $tpl=reset($tpl);
        //检查search_product是否在child中
        $child_sku_list=array_column($tpl['child'],'sku');
        if(!in_array($tpl['search_product'],$child_sku_list)){
            $tpl['search_product']=$child_sku_list[0];
        }
        //强制将id重置成0   插入是如果是0 新增，如果携带id为修改
        $tpl['id']=0;
        $tpl['child']=array_combine(array_column($tpl['child'],'product_id'),$tpl['child']);
        $data['tpl']=$tpl;
        $data['default_select']=json_encode(array_column($tpl['child'],'product_id'));    //初始化选中的product
        // place limit
        $data['place_limit'] = $tpl['limit_num'];
        $data['used_num'] = -1; // 默认 no limit
        // 价格校验
        $data['is_non_inner_account'] = $this->customer->isNonInnerAccount();
        // 是否显示云送仓提醒
        $data['is_show_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();

        $this->response->setOutput($this->load->view('account/customerpartner/rebates_form', $data));
    }



    /********************end **********************/

    /**
     * 新增和修改操作的保存逻辑
     *
     * @param int $operator_code   1:新增 2:修改
     * @param int $customer_id
     * @param $data
     * @throws Exception
     */
    private function save($operator_code, $customer_id, $data)
    {

        $data['price'] = $data['input_exclusive_price'];
        if ($data['discount_type']){//0:返金金额按比例计算，1：固定返金金额
            $data['discount_amount'] = $data['input_discount'];
            $data['discount'] = 0;
        }else{
            $data['discount_amount'] = 0;
            $data['discount'] = $data['input_discount'] / 100;
        }

        $data['status'] = 1;
        $data['input_qty_limit'] = 0;//N-96 返点三期 去掉最小销量限制
        $data['price_limit_percent'] = 0;//N-96 返点三期 改对外最低售价按百分比计算为seller指定对外最低售价

        $this->load->model('account/customerpartner/rebates');
        if ($operator_code == 1) {

            $add_id = $this->model_account_customerpartner_rebates->saveDebatesTemplate($customer_id, $data);

            $last = $this->model_account_customerpartner_rebates->ForTemplateLog($add_id);
            $this->model_account_customerpartner_rebates->saveLog($add_id,[],$last,$this->customer->getId());

        } else if ($operator_code == 2) {
            $before = $this->model_account_customerpartner_rebates->ForTemplateLog($data['id']);
            $this->model_account_customerpartner_rebates->updateRebatesTemplate($customer_id, $data);
            $last = $this->model_account_customerpartner_rebates->ForTemplateLog($data['id']);
            $this->model_account_customerpartner_rebates->saveLog($data['id'],$before,$last,$this->customer->getId());
        }
    }

    /**
     * 匹配商品，联想sku，mpn,校验商品是否超过3个模板
     *
     * @throws Exception
     */
    public function matchProduct()
    {
        $this->load->language('account/customerpartner/rebates');
        $mpn = $this->request->post['mpn_sku'];
        $record_pid = $this->request->post['record_pid'];
        $customer_id = $this->customer->getId();
        $country_id = $this->customer->getCountryId();
        $data = array();
        if (!isset($customer_id) || !isset($mpn)) {
            $error = $this->language->get('error_invalid_request');
        } else {
            $this->load->model('account/customerpartner/rebates');
            $products = $this->model_account_customerpartner_rebates->getProductInformationForPromotion($customer_id, $mpn);
            if (empty($products)) {
                $error = $this->language->get('error_no_product');
            } else {
                //取第一个
                $product = current($products);
                $is_original = false;
                if($product['product_id'] == $record_pid){
                    $is_original = true;
                }
                $template_count = $this->model_account_customerpartner_rebates->countRebateTemplateEffective($product['product_id']);
                if (!$is_original && $template_count['cnt'] >= 3) {
                    $error = sprintf($this->language->get('error_template_too_much'), $mpn);
                } else {
                    $price = $product['price'];
                    $freight = $product['freight'];
                    if($country_id == 107){
                        $price = round($price);
                    }
                    $success = array(
                        'product_id' => $product['product_id'],
                        'sku' => $product['sku'],
                        'mpn' => $product['mpn'],
                        'quantity' => $product['quantity'],
                        'price' => $price,
                        'freight' => $freight,
                        'price_format' => $this->currency->format($price, session('currency'))
                    );
                    $data['success'] = $success;
                }
            }
        }
        if (isset($error)) {
            $data['error'] = $error;
        }
        $this->response->setOutput(json_encode($data));
    }

    /*
     * 下载模板
     * */
    public function downloadTemplates()
    {
        $this->load->language('account/customerpartner/rebates');
        $this->load->model('account/customerpartner/rebates');

        $customer_id = $this->customer->getId();
        $filter_sku_mpn = trim($this->request->get('sku_mpn', null));

        $filter_data = [
            'customer_id' => $customer_id,
            'filter_sku_mpn' => $filter_sku_mpn
        ];
        $template_source = $this->model_account_customerpartner_rebates->get_rebates_template_display($filter_data);
        foreach ($template_source as $k=>&$v){
            if(count($v['child'])==1){
                $v['exclusive_price']=$this->currency->formatCurrencyPrice($v['child'][0]['price'],session('currency'));
            }else{
                $price_list=array_column($v['child'],'price');
                $min_price=min($price_list);
                $max_price=max($price_list);
                $v['exclusive_price']=($min_price==$max_price)?$this->currency->formatCurrencyPrice($max_price,session('currency')):$this->currency->formatCurrencyPrice($min_price,session('currency')).'-'.$this->currency->formatCurrencyPrice($max_price,session('currency'));
            }
            $tmp_rebate=explode('_',$v['item_rebates']);
            if($v['rebate_type']==0){//百分比
                $v['rebates']=(count($tmp_rebate)==1)?$tmp_rebate[0].'%':min($tmp_rebate).'% - '.max($tmp_rebate).'%';
            }else{  //金额
                $v['rebates']=(count($tmp_rebate)==1)?$this->currency->formatCurrencyPrice($tmp_rebate[0],session('currency')):$this->currency->formatCurrencyPrice(min($tmp_rebate),session('currency')).' - '.$this->currency->formatCurrencyPrice(max($tmp_rebate),session('currency'));
            }
        }

        //B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("Ymd", time()), 'Ymd');
        $fileName = "Rebates Templates" . $time . ".csv";
        /**
         * head 头顺序
         */
        $head = [
            "Template ID",
            'Item Code (MPN)',
            'Days',
            'Min. Total Selling Quantity',
            'Exclusive Price',
            'Rebate / Unit',
            'Last Modified'
        ];
        $content = [];
        if (!empty($template_source)) {
            $templates = [];
//            foreach ($template_source as $key => $item) {
//                $item = obj2array($item);
//                $templates[$key] = $item;
//
//                if ($item['discount_type']) {
//                    $templates[$key]['discount_display'] = $this->currency->format($item['discount_amount'], session('currency'));
//                } else {
//                    $templates[$key]['discount_display'] = $item['discount'] * 100 . '%';
//                }
//                if ('0.00' == $item['price_limit']) {
//                    $price_limit = round($item['price'] * $item['price_limit_percent'], 2);
//                } else {
//                    $price_limit = $item['price_limit'];
//                }
//                $templates[$key]['price_limit_display'] = $this->currency->format($price_limit, session('currency'));
//
//            }
            foreach ($template_source as $detail) {
                $item_list=explode(',', $detail['items']);
                $rtn_item_list=array();
                foreach ($item_list as $vv){
                    $sub_list=explode(":",$vv);
                    $rtn_item_list[]=$sub_list[0].'('.$sub_list[1].')';
                }
                $rtn_items=implode(',',$rtn_item_list);
                $content[] = [
                    "\t".$detail['rebate_template_id'],
                    $rtn_items,//$detail['items'],
                    $detail['day'],
                    $detail['qty'],
                    $detail['exclusive_price'],
                    $detail['rebates'],
                    $detail['update_time'],
                ];
            }
            //B2B记录各国别用户的操作时间
            outputCsv($fileName,$head,$content,$this->session);
        }else{
            //B2B记录各国别用户的操作时间
            outputCsv($fileName,$head,$content,$this->session);
        }

    }

    /*
     * 商品sku mpn 联想
     * */
    public function autoCompleteSku()
    {
        $this->load->model('account/customerpartner/rebates');
        $sku = $this->request->get['sku'];

        $list = $this->model_account_customerpartner_rebates->autoCompleteSku($sku,$this->customer->getId());

        $this->response->setOutput(json_encode($list));
    }
}
