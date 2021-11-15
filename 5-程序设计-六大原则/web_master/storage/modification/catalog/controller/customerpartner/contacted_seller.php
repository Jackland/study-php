<?php

use App\Catalog\Controllers\AuthController;
use App\Helper\CountryHelper;
use App\Repositories\Seller\SellerRepository;
use Illuminate\Support\Carbon;

/**
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelToolCsv $model_tool_csv
 * @property ModelCustomerpartnerSellerCenterIndex $model_customerpartner_seller_center_index
 * @property ModelCustomerpartnerMaster $model_customerpartner_master
 * @property ModelToolImage $model_tool_image
 */
class ControllerCustomerpartnerContactedSeller extends AuthController {

    private $error = array();
    private $data = array();

    public function index() {

        $this->data = array_merge($this->data, $this->load->language('customerpartner/sell'));
        $this->document->setTitle($this->language->get('heading_title_contacted'));
        $this->document->addStyle('catalog/view/theme/default/stylesheet/MP/sell.css?v=' . APP_VERSION);

        $this->load->model('tool/image');
        $this->load->model('customerpartner/master');

        $this->data['text_compare'] = sprintf($this->language->get('text_compare'), (isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0));
        $this->data['compare'] = $this->url->link('product/compare');
        $this->data['sell_header'] = $this->language->get('heading_title_contacted');
        $this->data['showpartners'] = $this->config->get('marketplace_showpartners');
        $this->data['showproducts'] = $this->config->get('marketplace_showproducts');

        $this->data['tabs'] = array();
        $marketplace_tab = $this->config->get('marketplace_tab');
        if(isset($marketplace_tab['heading']) AND $marketplace_tab['heading']){
            ksort($marketplace_tab['heading']);
            ksort($marketplace_tab['description']);
            foreach ($marketplace_tab['heading'] as $key => $value) {
                $text = $marketplace_tab['description'][$key][$this->config->get('config_language_id')];
                $text = trim(html_entity_decode($text));
                $this->data['tabs'][] = array(
                    'id' => $key,
                    'hrefValue' => $value[$this->config->get('config_language_id')],
                    'description' => $text,
                );
            }
        }
        $page = request('page', 1);
        $limit = request('page_limit', 10);
        //mysql LIKE 字段(实体、%等)的接收、转义
        $store_name = request('store_name', '');
        if ($store_name == base64_encode(base64_decode($store_name))){
            $store_name = base64_decode($store_name);
        }
        $this->data['store_name'] = $store_name = htmlentities(trim($store_name));
        $store_name=strtr($store_name,array('%'=>'\%', '_'=>'\_', '\\'=>'\\\\'));

        $customer_id = $this->customer->getId();
        $partners = $this->model_customerpartner_master->getSellerHotProductsByCostumerId($customer_id, ($page - 1) * $limit, $limit, $store_name);

        //获取购物去过的店铺：条件是可以买、是卖家
        $seller_id_rows = $this->orm->table('oc_buyer_to_seller AS bts')
            ->leftJoin('oc_customer AS c','bts.seller_id','=','c.customer_id')
            ->leftJoin('oc_customerpartner_to_customer AS c2c', 'c.customer_id','=','c2c.customer_id')
            ->select(['c.customer_id'])
            ->whereRaw("bts.buyer_control_status=1 AND bts.seller_control_status=1 AND bts.buyer_id={$customer_id} AND c2c.is_partner=1 AND c.status=1")
            ->where(function ($query) use ($store_name) {
                strlen($store_name) && $query->where('c2c.screenname', 'LIKE', "%$store_name%");
            })
            ->orderBy('bts.last_transaction_time', 'desc')
            ->orderBy('bts.id', 'desc')
            ->get()
            ->toArray();
        if($seller_id_rows){
            $seller_ids = array_column($seller_id_rows, 'customer_id');
            $total_items = $this->orm->table('oc_buyer_to_seller AS bts')
                ->leftJoin('oc_customer AS c','bts.seller_id','=','c.customer_id')
                ->leftJoin('oc_customerpartner_to_customer AS c2c', 'c.customer_id','=','c2c.customer_id')
                ->where([
                    'bts.buyer_control_status'      => 1,
                    'bts.seller_control_status'     => 1,
                    'bts.buyer_id'                  => $customer_id,
                    'c2c.is_partner'                => 1,
                ])
                ->whereRaw('c.customer_id IN (' .implode(',', $seller_ids).')')
                ->where(function ($query) use ($store_name) {
                    strlen($store_name) && $query->where('c2c.screenname', 'LIKE', "%$store_name%");
                })
                ->count();
        }else{
            $total_items = 0;
        }
        $pagination = new Pagination();
        $pagination->total = $total_items;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->url->link('customerpartner/contacted_seller', empty($store_name) ?'page={page}' :'page={page}&store_name='.base64_encode(htmlspecialchars($store_name)), true);
        $this->data['pagination'] = $pagination->render();
        unset($seller_id_rows, $seller_ids, $pagination);

        $this->data['partners'] = array();
        $this->data['partners_length'] = count($partners);
        $this->data['my_seller_flag'] = 1;

        if (!empty($partners)) {
            session()->set('myseller_to_csv', implode(',', array_column($partners, 'customer_id')));
        } else {
            session()->set('myseller_to_csv', null);
        }

        //判断是否参与评分
        if (ENV_DROPSHIP_YZCM == 'dev_35') {
            $information_id = 131;
        } elseif (ENV_DROPSHIP_YZCM == 'dev_17') {
            $information_id = 130;
        } elseif (ENV_DROPSHIP_YZCM == 'pro') {
            $information_id = 133;
        } else {
            $information_id = 133;
        }
        $sellerRepository = app(SellerRepository::class);
        $this->load->model('customerpartner/seller_center/index');
        foreach ($partners as $result) {
            if ($result['avatar']) {
                $image = $this->model_tool_image->resize($result['avatar'], 110, 110);
            } else if ($result['companybanner']) {
                $image = $this->model_tool_image->resize($result['companybanner'], 110, 110);
            } else if ($result['companybanner'] == 'removed') {
                $image = '';
            } else if ($this->config->get('marketplace_default_image_name')) {
                $image = $this->model_tool_image->resize($this->config->get('marketplace_default_image_name'), 110, 110);
            } else {
                $image = $this->model_tool_image->resize('no_image.png', 110, 110);
            }
            if (empty($image)) {
                $image = $this->model_tool_image->resize('no_image.png', 110, 110);
            }

            $comprehensive = ['seller_show' => 0];
            $isOutNewSeller = $sellerRepository->isOutNewSeller($result['customer_id'], 3);
            $task_info = $this->model_customerpartner_seller_center_index->getSellerNowScoreTaskNumberEffective($result['customer_id']);
            if ($isOutNewSeller && !isset($task_info['performance_score'])) {
                $newSellerScore = true;//评分显示 new seller
                $comprehensive = ['seller_show' => 1];
            } else {
                $newSellerScore = false;
                if ($task_info) {
                    $comprehensive = [
                        'seller_show' => 1,
                        'total' => isset($task_info['performance_score']) ? number_format(round($task_info['performance_score'], 2), 2) : '0',
                        'url' => $this->url->link('information/information', ['information_id' => $information_id])
                    ];
                }
            }
            /**
             * 精细化管理 店铺产品总数
             */
            $this->data['partners'][] = array(
                'customer_id' 		=> $result['customer_id'],
                //'name' 		  		=> $result['firstname'].' '.$result['lastname'],
                'screenname'		=> $result['screenname'],
                'companyname' 		=> $result['companyname'],
                'backgroundcolor' 		=> $result['backgroundcolor'],
                //'country'  	  		=> $result['country'],
                'sellerHref'  		=> $this->url->to(['seller_store/home', 'id' => $result['customer_id']]),
                'sellerProductsHref' => $this->url->to(['seller_store/products', 'id' => $result['customer_id']]),
                'thumb'       		=> $image,
                'contactSellerLink' =>  $result['contactSellerLink'],
                'total_products'    => $result['total'],
                'products'          => $result['products'],
                'main_category' =>  $result['main_category'],
                'response_rate' =>  $result['response_rate'],
                //'return_rate' =>  $result['return_rate'],
                //'return_rate_str' =>  $result['return_rate_str'],
                'store_return_rate_mark' =>  $result['store_return_rate_mark'],
                'store_response_rate_mark' =>  $result['store_response_rate_mark'],
                'return_approval_rate' =>  $result['return_approval_rate'],
                'comprehensive' => $comprehensive,
                'new_seller_score' => $newSellerScore,
            );
        }

        // add by LiLei 判断用户是否登录
        $this->data['isLogin'] = customer()->isLogged();
        if ($this->config->get('marketplace_seller_info_hide')) {
            $this->data['showpartners'] = false;
            $this->data['showpartnerdetails'] = false;
        } else {
            $this->data['showpartnerdetails'] = true;
        }

        $this->data['login'] = $this->url->link('account/login', '', true);
        $this->data['column_left'] = $this->load->controller('common/column_left');
        $this->data['column_right'] = $this->load->controller('common/column_right');
        $this->data['content_top'] = $this->load->controller('common/content_top');
        $this->data['content_bottom'] = $this->load->controller('common/content_bottom');
        $this->data['footer'] = $this->load->controller('common/footer');
        $this->data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('customerpartner/sell', $this->data));
    }

    /**
     * 处理20210430batch download临时问题处理
     * @throws Exception
     */
    public function downloadSellersCsv()
    {
        //验证是否有下载权限
        ini_set('memory_limit', -1);
        $customerId = customer()->getId();
        // 判断是否为 buyer 非buyer用户 直接无法获取下载权限
        if (!$customerId || customer()->isPartner()) {
            return $this->response->redirectTo(url('common/home'));
        }
        $sellers_id_str = request('sellers_id_str');
        if (empty($sellers_id_str)) {
            return $this->response->json(['error' => 'select null']);
        }
        $this->load->model('catalog/product');
        $this->load->model('tool/csv');
        $data = $this->model_catalog_product->getProductCategoryInfo($sellers_id_str, $customerId, true);
        $time = Carbon::now()->setTimezone(CountryHelper::getTimezone(customer()->getCountryId()))->format('YmdHis');
        $filename = 'SellersProductsInfo_' . $time . ".csv";
        $this->model_tool_csv->getProductCategoryCsv($filename, $data);
    }

    public function wkmpregistation(){

        $this->load->model('customerpartner/master');

        $json = array();

        if(isset($this->request->post['shop'])){
            $data = urldecode(html_entity_decode($this->request->post['shop'], ENT_QUOTES, 'UTF-8'));
            if($this->model_customerpartner_master->getShopData($data)){
                $json['error'] = true;
            }else{
                $json['success'] = true;
            }
        }
        $this->response->setOutput(json_encode($json));
    }

	public function sellerToCsv(){
	    set_time_limit(0);
        $post = $this->request->post;
        //验证是否有下载权限
        $custom_id =  $this->customer->getId();
        // 判断是否为 buyer 非buyer用户 直接无法获取下载权限
        $this->load->model('account/customerpartner');
        $isPartner  = $this->customer->isPartner();;
        if(null == $custom_id || true  == $isPartner){
            $this->response->redirect($this->url->link('common/home'));
            exit;
        }
        $type = $post['type'];
        $seller_str = isset($this->request->post['seller_str'])?$this->request->post['seller_str']:null;
        if($type == 0 && null != $seller_str){
            $all_total_str = $seller_str;
        }else{

            if(session('myseller_to_csv')){
                $all_total_str = session('myseller_to_csv');
            }else{
                echo "<script>window.location.href=document.referrer; </script>";
                exit;
            }

        }
        $this->load->model('catalog/product');
        $data = $this->model_catalog_product->getProductCategoryInfoByMySeller($all_total_str,$custom_id,true);
        //获取csv$seller_flag
        $this->load->model('tool/csv');
        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis",time()),'YmdHis');
        //12591 end
        $filename = 'MySellers_'.$time.".csv";
        $this->model_tool_csv->getProductCategoryCsvByMySeller($filename,$data);


    }


}
?>
