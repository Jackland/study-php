<?php

use App\Components\Storage\StorageCloud;
use App\Enums\Product\ProductTransactionType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Helper\AddressHelper;
use App\Helper\StringHelper;
use App\Repositories\Order\CountryStateRepository;
use App\Repositories\Setup\SetupRepository;

define('CWF_FILE_PATH', DIR_STORAGE . 'upload/cwf');

/**
 * @property ModelCatalogInformation $model_catalog_information
 * @property ModelCheckoutCwfInfo $model_checkout_cwf_info
 * @property ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 * @property ModelExtensionModulePrice $model_extension_module_price
 * @property ModelToolImage $model_tool_image
 */
class ControllerCheckoutCwfInfo extends Controller
{

    const ATTACHMENT_SIZE = 30 * 1024 * 1024;   //批量上传的文件
    const OTHER_TYPE = array('application/pdf');   //label   team lift
    const ATTACHMENT_TYPE=array('application/pdf','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','image/png','image/jpeg','image/jpg');    //attachment

    const SALT='123ccvsd23s3!3@@#^';

    protected $model;
    protected $modelPreOrder;


    public function __construct(Registry $registry,ModelCheckoutPreOrder $modelCheckoutPreOrder)
    {
        parent::__construct($registry);
        $this->modelPreOrder = $modelCheckoutPreOrder;
        // 验证是否登录
        if (!$this->customer->isLogged()) {
            $this->session->data['redirect'] = $this->url->link('account/account', '', true);
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        if ($this->customer->isPartner()) {   //只能buyer使用
            $this->response->redirect($this->url->link('account/account', '', true));
        }
        // 验证是否是美国用户
        if(!$this->customer->has_cwf_freight()){
            $this->response->redirect($this->url->link('account/account', '', true));
        }
        //测试店铺，服务和保证金店铺
//        if($this->customer->is_test_store()){
//            $this->response->redirect($this->url->link('account/account', '', true));
//        }
        //注册model
        $this->load->model('checkout/cwf_info');
        $this->model = $this->model_checkout_cwf_info;
        //language
        $this->load->language('checkout/cwf_info');
    }

    public function show_common_page_data(){
        //页面数据
        return  array(
            'column_left' => $this->load->controller('common/column_left'),
            'column_right' => $this->load->controller('common/column_right'),
            'content_top' => $this->load->controller('common/content_top'),
            'content_bottom' => $this->load->controller('common/content_bottom'),
            'footer' => $this->load->controller('common/footer'),
            'header' => $this->load->controller('common/header'),
        );
    }

    //校验图片
    public function check_pic($path, $width = 40, $height = 40)
    {
        //图片缩放
        $this->load->model('tool/image');
        if (!$path) {
            $image = $this->model_tool_image->resize('placeholder.png', $width, $height);
        } else {
            $image = $this->model_tool_image->resize($path, $width, $height);
            if (!$image) {
                $image = $this->model_tool_image->resize('placeholder.png', $width, $height);
            }
        }
        return $image;
    }
    //获取pdf 的url
    public function get_pdf_url($pdf_path){
        if ($this->request->server['HTTPS']) {
            return $this->config->get('config_ssl') . 'storage/upload/' . $pdf_path;
        } else {
            return $this->config->get('config_url') . 'storage/upload/' . $pdf_path;
        }
    }

    //返回数据格式
    function rtn_data($code, $msg = '', $data = array())
    {
        return array(
            'status' => $code,
            'msg' => $msg,
            'data' => $data
        );
    }

    // 云送仓信息填写

    public function index()
    {

        $this->session->data['show_cwf']=1;
        $this->document->setTitle($this->language->get('heading_title'));
        //数据
        $data = array();
        $data = array_merge($data, $this->show_common_page_data());
        //页面参数
        $data['attachment_size'] = self::ATTACHMENT_SIZE;
        $data['other_type'] = self::OTHER_TYPE;
        $data['attachment_type'] = self::ATTACHMENT_TYPE;
        //获取zone数据
        $data['zone'] = $this->model->get_zone($this->customer->getCountryId());
        $zone = array_column($data['zone'], 'name', 'code');
        $states = app(CountryStateRepository::class)->getUsaSupportState();
        foreach ($states as $key => $state) {
            if (!isset($state, $zone)) {
                unset($states[$key]);
                continue;
            }
            $states[$key] = $zone[$state];
        }
        $data['zone'] = $states;
        //获取购物车
        $cartIdStr = get_value_or_default($this->request->get, 'cart_id_str', '');
        $data['cart_id_str'] = $cartIdStr;
        $data['buy_now_data'] = $this->request->attributes->get('buy_now_data', '');
        $originalProducts = $this->modelPreOrder->getPreOrderCache($data['cart_id_str'], $data['buy_now_data']);
        if (!$originalProducts) {
            return $this->response->redirectTo($this->url->link('checkout/cart', '', true));
        }
        $customerId = $this->customer->getId();
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        $countryId = $this->customer->getCountryId();
        $data['cart'] = $this->modelPreOrder->handleProductsData($originalProducts, $customerId, 2, $isCollectionFromDomicile, $countryId);

        //校验session
//        if(isset($this->session->data['old_session_id'])){
//            if($this->session->data['old_session_id']!=$this->session->getId()){   //sesseion id 一样
//                echo '<pre>';
//                print_r('页面被刷新了');
//                echo '<pre>';
//                die();
//            }
//        }else{
//            $this->session->data['old_session_id']=$this->session->getId();
//        }
        //数据校验   --检查购物车是否为空，是否包含特殊产品（保证金头款）
        if(empty($data['cart'])){   //购物车为空
            $this->response->redirect($this->url->link('checkout/cart', '', true));
        }

        //检查余额的使用
        $this->session->remove('useBalanceToZero');
        if ($this->config->get('total_balance_status')) {
            if (isset($this->request->post['balanceValue'])) {
                $balance = $this->request->post['balanceValue'];
                // 获取当前用户信用额度，判断传递的使用余额是否合法
                $lineOfCredit = $this->customer->getLineOfCredit();
                $this->session->data['delivery_type'] = 2;
                if (bccomp($balance, $this->cart->getQuoteTotal(), 2) > 0) {
                    $this->session->data['error'] = 'The product price has changed, please checkout again.';
                    $this->response->redirect($this->url->link('checkout/cart'));
                }
                $this->session->remove('delivery_type');
                if (bccomp($balance, $lineOfCredit, 2) > 0) {
                    // 支付的余额大于实际信用额度，页面重定向
                    $this->response->redirect($this->url->link('checkout/cart'));
                    // 余额大于订单总金额
                } else {
                    // 保存Session信息
                    $this->session->data['balanceValue'] = $balance;
                    $this->session->data['useBalance'] = $balance;
                    $this->load->model('buyer/buyer_common');
                    $total = (double)$this->model_buyer_buyer_common->getCartTotal();
                    if ($total == 0) {
                        //使用组合支付且价格扣减为0
                        $this->session->data['useBalance'] = 0;
                        $this->session->data['useBalanceToZero'] = true;
                    }
                }

            }
        } else {
            $this->session->data['useBalance'] = 0;
        }
        //默认超重值，超过的系统判定为超重
        $defaultMaxWeight = $this->config->get('cwf_team_lift_max_weight');
        $defaultMaxWeight = $defaultMaxWeight ? intval($defaultMaxWeight) : 50;
        //数据处理
        //获取购物车中所有商品运费信息
        $cartProductIds = array_column($data['cart'],'product_id');
        $productFreight = $this->freight->getFreightAndPackageFeeByProducts($cartProductIds);
        foreach ($data['cart'] as $k => &$v) {
            $v['cart_id'] = !empty($v['cart_id']) ? $v['cart_id'] : $k;
            //校验是否是普通商品
            if($v['product_type']!=0 && $v['product_type']!=3){
                $this->response->redirect($this->url->link('account/account', '', true));
                break;
            }
            //是否存在服务店铺、保证金店铺的产品
            if(in_array($v['seller_id'],array(340,491,631,838))){
                $this->response->redirect($this->url->link('account/account', '', true));
                break;
            }
            //处理数据
            $v['image'] = $v['image'] ? $this->check_pic($v['image'], 40, 40) : '';
            //加token 锁住产品信息
            $v['token']= md5($v['cart_id'].$v['product_id'].$v['sku'].$v['seller_id'].$v['quantity'].self::SALT);
            //是否默认选中 team lift
            $volume = 0;
            $v['total_weight'] = round($v['weight'],2);//实际重量
            if ($v['combo_flag']) {
                //组合
                foreach ($productFreight[$v['product_id']] as $freight) {
                    //102497 换成立方英尺
                    $volume += ($freight['volume_inch'] * $freight['qty']);
                }
                //组合不送fba，所以重量设置成0，且不显示
                $v['total_weight'] = 0;
                $v['total_weight_show'] = '--';
            } else {
                //非组合
                $volume = ($productFreight[$v['product_id']]['volume_inch'] ?? 0);
                $v['total_weight_show'] = $v['total_weight'] . ' ' . $this->language->get('weight_class');//加上符号
            }
            $v['team_lift_status'] = intval($v['total_weight'] > $defaultMaxWeight);//team lift 默认选中状态
            $v['total_volume'] = $volume;//总体积
            $v['total_volume_show'] = $v['total_volume'] . '' . $this->language->get('volume_class');//加上符号
        }
        //info id
        $data['cwf_info_id']=$this->config->get('cwf_help_id');
        //md5串，用于保存时校验购物车是否发生变化
        $cart_list= array_column($data['cart'],'cart_id');
        sort($cart_list);
        $data['cart_info']=md5(implode('-',$cart_list).self::SALT);
        //md5串，用于保存时校验购物车是否发生变化(product 和数量)
        $product_num_str='';
        $product_price_str='';
        $product_type_str='';
        $cart_info=array_combine(array_column($data['cart'],'cart_id'),$data['cart']);
        ksort($cart_info);
        foreach ($data['cart'] as $cart_k=>$cart_v){
            $product_num_str.=$cart_v['cart_id'].'-'.$cart_v['product_id'].'-'.$cart_v['quantity'].self::SALT;
            $product_price_str.=$cart_v['cart_id'].'-'.$cart_v['product_id'].'-'.$cart_v['price'].self::SALT;
            $product_type_str.=$cart_v['cart_id'].'-'.$cart_v['product_id'].'-'.$cart_v['transaction_type'].self::SALT;
        }
        $data['cart_num_info']=md5($product_num_str);
        $data['cart_price_info']=md5($product_price_str);
        $data['cart_type_info']=md5($product_type_str);
        $data['createOrderUrl'] = $this->url->link('checkout/confirm/createOrder');
        $data['toPayUrl'] = $this->url->link('checkout/confirm/toPay');
        //获取information信息
        //有可能会变的
        $informationId = $this->config->get('cwf_help_information_id');
        $informationId = $informationId ? intval($informationId) : 130;
        $this->load->model('catalog/information');
        $informationInfo = $this->model_catalog_information->getInformation($informationId);
        $data['information_title'] = $informationInfo['meta_title'] ?? 'Cloud Wholesale Fulfillment – Description of Service-Buyer';//这里默认值是因为测试环境没有130，占位用
        $data['information_url'] = $this->url->link('information/information&information_id=' . $informationId);

        //将购物车数据json格式化后再转义，否则碰到字符串里存在单双引号会导致前端报错
        $data['cart_json'] = addslashes(json_encode($data['cart']));

        $this->response->setOutput($this->load->view('checkout/cwf_info', $data));
    }

    //生成不同的文件名
    public function create_file_name($ext)
    {
        $save_file_name = 'file_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $file_path =  'cwf/' . ($this->customer->getId()) . '/' . $save_file_name;
        if (StorageCloud::upload()->fileExists($file_path)) {
            $save_file_name = $this->create_file_name($ext);
        }
        return $save_file_name;
    }

    //上传
    public function upload()
    {
        //http referer
        if(!isset($this->request->server['HTTP_REFERER'])||!strstr($this->request->server['HTTP_REFERER'],'checkout/cwf_info')){
            $this->response->returnJson($this->rtn_data(0, '', array(array('code' => 106,'file_name' =>'', 'msg' => $this->language->get('upload_error')))));
        }
        //接收数据
        $file = $this->request->files['file'];
        $file_flag = $this->request->post['flag'];    //上传标志--用于区分是哪里上传
        if (!$file) {
            $this->response->returnJson($this->rtn_data(0, '', array(array('code' => 100,'file_name' =>'', 'msg' =>$this->language->get('upload_error')))));
        }
        $file_key = array_keys($file);
        $err = array();
        $success_upload = array();
        foreach ($file['name'] as $key => $name) {
            $tmp_file_info = array_combine($file_key, array_column($file, $key));
            //校验
            if ($tmp_file_info['error']) {
                $err[] = array(
                    'code' => 101,
                    'file_name' => $tmp_file_info['name'],
                    'msg' => $this->language->get('upload_error'),
                );
                continue;
            }
            if ($tmp_file_info['size'] > self::ATTACHMENT_SIZE) {
                $err[] = array(
                    'code' => 102,
                    'file_name' => $tmp_file_info['name'],
                    'msg' => $file_flag. (($file_flag=='Attachments')?$this->language->get('upload_attachment_error'):$this->language->get('upload_size_type_error')),
                );
                continue;
            }
            if($file_flag=='Attachments'){    //attachment
                if (!(in_array($tmp_file_info['type'], self::ATTACHMENT_TYPE))) {
                    $err[] = array(
                        'code' => 103,
                        'file_name' => $tmp_file_info['name'],
                        'msg' => $this->language->get('upload_attachment_error'),
                    );
                    continue;
                }
            }else{    //label , team lift
                if (!(in_array($tmp_file_info['type'], self::OTHER_TYPE))) {
                    $err[] = array(
                        'code' => 103,
                        'file_name' => $tmp_file_info['name'],
                        'msg' =>$file_flag. $this->language->get('upload_size_type_error'),
                    );
                    continue;
                }
            }

            //处理
            $explode_file_name= explode('.', $tmp_file_info['name']);
//            $real_file_name = explode('.', $tmp_file_info['name'])[0];
            $real_file_ext = explode('.', $tmp_file_info['name'])[count($explode_file_name)-1];
            $new_file_name = $this->create_file_name($real_file_ext);

            StorageCloud::upload()->writeFile(new UploadedFile($tmp_file_info['tmp_name'], $tmp_file_info['name']), 'cwf/' . ($this->customer->getId()), $new_file_name);

            //返回数据
            $success_upload[] = array(
                'old_file_name' => $tmp_file_info['name'],
                'new_file_name' => $new_file_name,
                'file_type' => $real_file_ext,
                'new_file_path' => 'cwf/' . ($this->customer->getId()) . '/' . $new_file_name,
                'file_url' => StorageCloud::upload()->getUrl('cwf/' . ($this->customer->getId()) . '/' . $new_file_name),
            );
        }
        if ($err) {
            $this->response->returnJson($this->rtn_data(0, $this->language->get('upload_failed'), $err));
        }
        $this->response->returnJson($this->rtn_data(1, $this->language->get('upload_success'), $success_upload));
    }

    //订单详情页数据保存
    public function save_cwf_info()
    {
        $data = $this->request->post;
        //后台校验
        //获取购物车数据
        $cartIdStr=$this->request->post('cart_id_str', '');
        $cartIdArr = explode(',', $this->request->post('cart_id_str', ''));
        $originalProducts = $this->modelPreOrder->getPreOrderCache($cartIdStr,  $this->request->attributes->get('buy_now_data', ''));
        if (!$originalProducts) {
            return $this->jsonFailed($this->language->get('save_failed'));
        }
        $customerId = $this->customer->getId();
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        $countryId = $this->customer->getCountryId();
        $cart_info =  $this->modelPreOrder->handleProductsData($originalProducts, $customerId, 2, $isCollectionFromDomicile, $countryId);


        if ($cartIdStr) {
            $cart_info = array_combine(array_column($cart_info, 'cart_id'), $cart_info);
        }
        if(!$cart_info || count($cart_info) != count($cartIdArr)){   //没有获取到购物车信息
            $this->response->returnJson($this->rtn_data(0, $this->language->get('check_error_cart_item_data_change'),['cart']));
        }
        //校验购物车是否发生变化---增删product
        $cart_list= array_column($cart_info,'cart_id');
        sort($cart_list);
        $new_cart_info=md5(implode('-',$cart_list).self::SALT);
        if(empty($this->request->post('buy_now_data', '')) && $new_cart_info!=$data['cart_info']){
            $this->response->returnJson($this->rtn_data(0, $this->language->get('check_error_cart_item_data_change'),['cart']));
        }

        //校验产品价格是否发生变化
        ksort($cart_info);
        if ($cartIdStr) {
            $product_price_str = '';
            $product_num_str = '';
            $product_type_str = '';
            foreach ($cart_info as $cart_k => $cart_v) {
                $product_price_str .= $cart_v['cart_id'] . '-' . $cart_v['product_id'] . '-' . $cart_v['price'] . self::SALT;
                $product_num_str .= $cart_v['cart_id'] . '-' . $cart_v['product_id'] . '-' . $cart_v['quantity'] . self::SALT;
                $product_type_str .= $cart_v['cart_id'] . '-' . $cart_v['product_id'] . '-' . $cart_v['type_id'] . self::SALT;
            }
            if (md5($product_price_str) != $data['cart_price_info']) {
                $this->response->returnJson($this->rtn_data(0, $this->language->get('check_error_cart_item_data_change'), ['cart']));
            }
            if (md5($product_num_str) != $data['cart_num_info']) {
                $this->response->returnJson($this->rtn_data(0, $this->language->get('check_error_cart_item_data_change'), ['cart']));
            }
            if (md5($product_type_str) != $data['cart_type_info']) {
                $this->response->returnJson($this->rtn_data(0, $this->language->get('check_error_cart_item_data_change'), ['cart']));
            }
        }
        // 校验购物车内的数据
        $this->load->model('customerpartner/DelicacyManagement');
        $delicacy_model = $this->model_customerpartner_DelicacyManagement;
        $delicacy_info=$delicacy_model->checkIsDisplay_batch(array_column($cart_info,'product_id'),$this->customer->getId());
//        $quantity_list=$this->model->get_quantity(array_column($cart_info,'product_id'));
        $cwf_freight=$this->freight->getFreightAndPackageFeeByProducts(array_column($cart_info,'product_id'));
        $this->load->model('extension/module/price');
        $priceModel = $this->model_extension_module_price;
        $is_fba=($data['server_type']!='Others');
        foreach ($cart_info as $k=>$v){
            $transactionQtyResults = $priceModel->getProductPriceInfo($v['product_id'],$this->customer->getId());
            if($v['type_id'] == ProductTransactionType::NORMAL){
                $quantity = $transactionQtyResults['base_info']['quantity'];
            }elseif ($v['type_id'] == ProductTransactionType::REBATE){
                $quantity = $transactionQtyResults['base_info']['quantity'];
            }elseif ($v['type_id'] == ProductTransactionType::MARGIN){
                foreach ($transactionQtyResults['transaction_type'] as $transactionQty){
                    if($transactionQty['type'] == ProductTransactionType::MARGIN && $v['agreement_code'] == $transactionQty['agreement_code']){
                        $quantity = $transactionQty['left_qty'];
                        break;
                    }
                }
            }elseif ($v['type_id'] == ProductTransactionType::FUTURE){
                foreach ($transactionQtyResults['transaction_type'] as $transactionQty){
                    if($transactionQty['type'] == ProductTransactionType::FUTURE && $v['agreement_code'] == $transactionQty['agreement_code']){
                        $quantity = $transactionQty['left_qty'];
                        break;
                    }
                }
            }elseif ($v['type_id'] == ProductTransactionType::SPOT) {
                foreach ($transactionQtyResults['transaction_type'] as $transactionQty) {
                    if ($transactionQty['type'] == ProductTransactionType::SPOT && $v['agreement_code'] == $transactionQty['agreement_code']) {
                        $quantity = $transactionQty['left_qty'];
                        break;
                    }
                }
            }
            //上架且可独立售卖且Buyer可见
            if(!in_array($v['product_id'],$delicacy_info)){
                $this->response->returnJson($this->rtn_data(0, sprintf($this->language->get('check_error_available'),$v['sku']),['cart']));
            }
            //库存数是否充足
            if($v['quantity']>$quantity){
                $this->response->returnJson($this->rtn_data(0, sprintf($this->language->get('check_error_quantity'),$v['sku']),['cart']));
            }
            //尺寸运费信息是否存在
            if($v['combo_flag']){
                //云送仓不能发组合商品
                $is_fba && $this->response->returnJson($this->rtn_data(0, sprintf($this->language->get('check_error_cart_item_combo'),$v['sku']),['cart']));
                foreach ($cwf_freight[$v['product_id']] as $fre_k=>$fre_v){
                    if($fre_v['volume_inch']==0||$fre_v['freight']==0){
                        $this->response->returnJson($this->rtn_data(0, sprintf($this->language->get('check_error_size_error'),$v['sku']),['cart']));
                    }
                }
            }else{
                if(!isset($cwf_freight[$v['product_id']])||$cwf_freight[$v['product_id']]['volume_inch']==0||$cwf_freight[$v['product_id']]['freight']==0){
                    $this->response->returnJson($this->rtn_data(0, sprintf($this->language->get('check_error_size_error'),$v['sku']),['cart']));
                }
            }
            //1363 购物车的类型是否是云送仓的
            if($v['delivery_type'] != 2) {
                $this->response->returnJson($this->rtn_data(0, $this->language->get('check_error_cart_item_data_change'),['cart']));
            }
            //1363 购物车商品店铺下架、商品下架、精细化不可见不能购买
//            if ($cartIdStr) {
//                $can_buy = $v['buyer_flag'] && $v['status'] && $v['store_status'] && !$v['fine_cannot_buy'] ? 1 : 0;
//                if (!$can_buy) {
//                    return $this->response->json($this->rtn_data(0, $this->language->get('check_error_cart_item_data_change'), ['cart']));
//                }
//            }
        }
        if ($countryId == AMERICAN_COUNTRY_ID) {
            $address = $data['address'] ?? '';
            if (empty($address) || (StringHelper::stringCharactersLen($address) > $this->config->get('config_b2b_address_len_us2'))) {
                return $this->response->json($this->rtn_data(0, sprintf($this->language->get('check_error_street_address'), $this->config->get('config_b2b_address_len_us2'))));
            }

            if (AddressHelper::isPoBox($address)) {
                return $this->response->json($this->rtn_data(0, 'Street Address in P.O.BOX doesn\'t support delivery.', ['address']));
            }

            if (AddressHelper::isRemoteRegion($data['state'] ?? '')) {
                return $this->response->json($this->rtn_data(0, 'Street Address in PR, AK, HI, GU, AA, AE, AP doesn\'t support delivery,Please see the instructions', ['state']));
            }
        }
        //1.参数校验
        !in_array($data['server_type'], array('Amazon FBA warehouse', 'Others')) && $this->response->returnJson($this->rtn_data(0, sprintf($this->language->get('check_error_common_err'),'server_type')));
        !(isset($data['city']) && $data['city']) && $this->response->returnJson($this->rtn_data(0,  sprintf($this->language->get('check_error_txt_common'),$this->language->get('table_address'),1,50)));
        !(isset($data['state']) && $data['state'] && $data['state']!='Please Select State') && $this->response->returnJson($this->rtn_data(0,  sprintf($this->language->get('check_error_txt_common'),$this->language->get('table_city'),1,50)));
        !(isset($data['zip']) && $data['zip']) && $this->response->returnJson($this->rtn_data(0,  sprintf($this->language->get('check_error_txt_common'),$this->language->get('table_zip_code'),1,50)));
        !(isset($data['united']) && $data['united']) && $this->response->returnJson($this->rtn_data(0,  sprintf($this->language->get('check_error_txt_common'),$this->language->get('table_united_states'),1,50)));
        if($is_fba){
            !(isset($data['shipment_id']) && $data['shipment_id']) && $this->response->returnJson($this->rtn_data(0, sprintf($this->language->get('check_error_txt_common'),$this->language->get('bill_info_shipment'),1,50)));
            !(isset($data['amazon_red_id']) && $data['amazon_red_id']) && $this->response->returnJson($this->rtn_data(0, sprintf($this->language->get('check_error_txt_common'),$this->language->get('bill_info_amazon_ref'),1,50)));
            //FBA Warehouse Code
            !(isset($data['fba_warehouse_code']) && $data['fba_warehouse_code']) && $this->response->returnJson($this->rtn_data(0, sprintf($this->language->get('check_error_txt_common'),$this->language->get('fba_warehouse_code'),1,100)));
            //Amazon Reference Number
            !(isset($data['fba_amazon_reference_number']) && $data['fba_amazon_reference_number']) && $this->response->returnJson($this->rtn_data(0, sprintf($this->language->get('check_error_txt_common'),$this->language->get('fba_amazon_reference_number'),1,50)));
            //!(isset($data['amazon_po_id']) && $data['amazon_po_id']) && $this->response->returnJson($this->rtn_data(0, '参数-amazon_po_id 错误'));
            //pallet label
            !(isset($data['pallet_label_file']) && $data['pallet_label_file']) && $this->response->returnJson($this->rtn_data(0, $this->language->get('check_error_pallet_label')));
            !(isset($data['pallet_label_file']['file_name']) && $data['pallet_label_file']['file_name']) && $this->response->returnJson($this->rtn_data(0, $this->language->get('check_error_pallet_label')));
            !(isset($data['pallet_label_file']['file_new_name']) && $data['pallet_label_file']['file_new_name']) && $this->response->returnJson($this->rtn_data(0, $this->language->get('check_error_pallet_label')));
            !(isset($data['pallet_label_file']['file_type']) && $data['pallet_label_file']['file_type']) && $this->response->returnJson($this->rtn_data(0, $this->language->get('check_error_pallet_label')));
            !(isset($data['pallet_label_file']['file_new_path']) && $data['pallet_label_file']['file_new_path']) && $this->response->returnJson($this->rtn_data(0, $this->language->get('check_error_pallet_label')));
            //101843 运送仓三期 默认读取用户的信息
            $recipient = $this->customer->getUserNumber();
            $data['recipient'] = $recipient ? $recipient : '-';
            $data['phone'] = $this->customer->getTelephone() ? $this->customer->getTelephone() : '-';
            $data['email'] = $this->customer->getEmail() ? $this->customer->getEmail() : '-';
            //FBA 固定yes
            $data['hasLoadingDock'] = 'Yes';
            //table信息
            (!isset($data['table'])||count($data['table'])==0) && $this->response->returnJson($this->rtn_data(0, sprintf($this->language->get('check_error_common_err'),'table')));
            if($cartIdStr){

            $i=0;   //计数器
            foreach ($cart_info as $k=>$v){
                $cart_qty[$v['cart_id']]=array(
                    'quantity'=>$v['quantity'],
                    'new_token'=>md5($v['cart_id'].$v['product_id'].$v['sku'].$v['seller_id'].$v['quantity'].self::SALT)
                );
            }
            foreach ($data['table'] as $k=>$v){
                $i++;
                //校验token
                if(md5($v['cart_id'].$v['product_id'].$v['sku'].$v['seller_id'].$v['qty'].self::SALT)!=$v['token']){
                    $this->response->returnJson($this->rtn_data(0, "LINE$i:".sprintf($this->language->get('check_error_common_err'),'table')));
                }
                !(isset($v['product_id']) && $v['product_id']) && $this->response->returnJson($this->rtn_data(0, sprintf($this->language->get('check_error_common_err_line'),$i,'product_id')));
                !(isset($v['seller_id']) && $v['seller_id']) && $this->response->returnJson($this->rtn_data(0, sprintf($this->language->get('check_error_common_err_line'),$i,'seller_id')));
                !(isset($v['sku']) && $v['sku']) && $this->response->returnJson($this->rtn_data(0, sprintf($this->language->get('check_error_common_err_line'),$i,'sku')));
                !(isset($v['mer_sku']) && $v['mer_sku']) && $this->response->returnJson($this->rtn_data(0, "LINE$i:".sprintf($this->language->get('check_error_txt_common'),$this->language->get('bill_table_head_mer_sku'),0,50)));
                !(isset($v['fn_sku']) && $v['fn_sku']) && $this->response->returnJson($this->rtn_data(0, "LINE$i:".sprintf($this->language->get('check_error_txt_common'),$this->language->get('bill_table_head_fn_sku'),0,50)));
                !(isset($v['qty']) && $v['qty']) && $this->response->returnJson($this->rtn_data(0, sprintf($this->language->get('check_error_common_err_line'),$i,'QTY')));
                //购物车数量变动
                (!$cart_info[$v['cart_id']]['quantity']) && $this->response->returnJson($this->rtn_data(0, $this->language->get('check_error_cart_item_change')));
                ($v['qty']!=$cart_info[$v['cart_id']]['quantity']) && $this->response->returnJson($this->rtn_data(0,  sprintf($this->language->get('check_error_cart_change'),$i,$cart_info[$v['cart_id']]['quantity']),array('table','qty',$cart_qty)));
                !(isset($v['package_file_info']) && $v['package_file_info']) && $this->response->returnJson($this->rtn_data(0, "LINE$i:".$this->language->get('check_error_load_package')));
                !(isset($v['product_file_info']) && $v['product_file_info']) && $this->response->returnJson($this->rtn_data(0, "LINE$i:".$this->language->get('check_error_load_product')));
            }
            //安全校验
            if(count($data['table'])!=count($cart_info)){
                $this->response->returnJson($this->rtn_data(0, $this->language->get('check_error_cart_item_change')));
            }
            $cart_id_list=array_column($cart_info,'cart_id');
            $post_id_list=array_column($data['table'],'cart_id');
            if(array_diff($cart_id_list,$post_id_list)){
                $this->response->returnJson($this->rtn_data(0, $this->language->get('check_error_cart_item_change')));
            }
            }

        }else{
            //101843 运送仓三期 这三个字段只有非fba才要用户填写
            !in_array($data['hasLoadingDock'], array('Yes', 'No')) && $this->response->returnJson($this->rtn_data(0,  sprintf($this->language->get('check_error_common_err'),'hasLoadingDock')));
            !(isset($data['recipient']) && $data['recipient']) && $this->response->returnJson($this->rtn_data(0, sprintf($this->language->get('check_error_txt_common'),$this->language->get('table_recipient'),1,100)));
            !(isset($data['phone']) && $data['phone']) && $this->response->returnJson($this->rtn_data(0, sprintf($this->language->get('check_error_txt_common'),$this->language->get('table_phone'),1,30)));
            !(isset($data['email']) && $data['email'] && filter_var($data['email'], FILTER_VALIDATE_EMAIL)) && $this->response->returnJson($this->rtn_data(0,  $this->language->get('check_error_email')));
            //校验产品的数量是否发生变化
            $product_num_str='';
//            $cart_info=array_combine(array_column($data['cart'],'cart_id'),$data['cart']);
            ksort($cart_info);
            foreach ($cart_info as $k => $v){
                $product_num_str.=$v['cart_id'].'-'.$v['product_id'].'-'.$v['quantity'].self::SALT;  //用于校验产品是否发生变化
                $data['table'][]=array(
                    'product_id'=>$v['product_id'],
                    'item_code'=>$v['sku'],
                    'seller_id'=>$v['seller_id'],
                    'qty'=>$v['quantity']
                );
            }
        }
        //comments and attachments
//        !(isset($data['comments']) && $data['comments']) && $this->response->returnJson($this->rtn_data(0, '参数-comments 错误'));
//        !(isset($data['attachments']) && $data['attachments']) && $this->response->returnJson($this->rtn_data(0, '参数-attachments 错误'));
        //保存
        $res = $this->model->save_cwf_info($data);
        if($res[0]){    //success
            $this->response->returnJson($this->rtn_data(1, $this->language->get('save_success')));
        }else{    //fail
            $this->response->returnJson($this->rtn_data(0, $this->language->get('save_failed')));
        }
    }

    /**
     * 获取运送仓手册
     */
    public function cloudGuide()
    {
        $buyerId = $this->customer->getId();
        $guide = $this->model->checkCloudGuideByBuyer($buyerId);
        $this->response->returnJson($guide);
    }

    /**
     * 设置运送仓手册已读
     */
    public function readCloudGuide()
    {
        $buyerId = $this->customer->getId();
        $this->model->readCloudGuide($buyerId);
        $this->response->returnJson(['status' => true]);
    }
}
