<?php
use App\Logging\Logger;
use App\Repositories\Bd\AccountManagerRepository;
use App\Repositories\Warehouse\WarehouseRepository;

/**
 * Class ControllerProductSearch
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountSearch $model_account_search
 * @property ModelCatalogCategory $model_catalog_category
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelCatalogSearch $model_catalog_search
 * @property ModelCatalogSearchClickRecord $model_catalog_search_click_record
 * @property ModelExtensionModuleProductCategory $model_extension_module_product_category
 * @property ModelToolCsv $model_tool_csv
 * @property ModelToolImage $model_tool_image
 */
class ControllerProductSearch extends Controller {

    public function index() {
        $this->load->language('product/search');
        $this->load->model('catalog/category');
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $this->load->model('catalog/search');

        trim_strings($this->request->get);
        $customer_id = 0;
        if ($this->customer->isLogged()) {
            $customer_id = $this->customer->getId();
        }
        $isPartner  = $this->customer->isPartner();

        // 添加搜索日志
        $this->model_catalog_product->addSearchRecord($customer_id, $this->request->get('search', ''));
        if ($this->request->get('click', '')) {
            $this->load->model('catalog/search_click_record');
            $this->model_catalog_search_click_record->saveRecord($this->request->get('click'), $this->request->get('order', ''));
        }
        $request_search = isset($this->request->get['search']) ? (trim(mb_substr(htmlspecialchars_decode($this->request->get['search']), 0, 100))) : null;
        $filter_data['search'] = isset($request_search)?htmlentities(htmlspecialchars_decode($request_search)):'';
        $filter_data['category_id'] = intval($this->request->get('category_id', 0));
        $filter_data['min_price'] = $this->request->get('min_price', '');
        $filter_data['max_price'] = $this->request->get('max_price', '');
        $filter_data['min_quantity'] = $this->request->get('min_quantity', '');
        $filter_data['max_quantity'] = $this->request->get('max_quantity', '');
        $filter_data['qty_status'] = $this->request->get('qty_status', '');
        $filter_data['download_status'] = intval($this->request->get('download_status', 0));
        $filter_data['wish_status'] = intval($this->request->get('wish_status', 0));
        $filter_data['purchase_status'] = intval($this->request->get('purchase_status', 0));
        $filter_data['relation_status'] = intval($this->request->get('relation_status', 0));
        $filter_data['img_status'] = intval($this->request->get('img_status', 0));

        //新增复杂交易类型的查询
        $filter_data['rebates'] = $this->request->get('rebates',false);
        $filter_data['margin'] = $this->request->get('margin',false);
        $filter_data['futures'] = $this->request->get('futures',false);
        //新增仓库库存的查询
        $filter_data['whId'] = $this->request->get('whId','') == '' ? [] : explode(',',$this->request->get('whId'));
        $search_tag = intval($this->request->get('search_tag', 0));
        $tag = $this->request->get('tag', '');

        $filter_data['sort'] = $this->request->get('sort', 'p.sort_order');
        $filter_data['order'] = $this->request->get('order', 'desc');
        $filter_data['page'] = intval($this->request->get('page', 1));
        $filter_data['limit'] = intval($this->request->get('limit', 20));
        $filter_data['start'] = ($filter_data['page'] - 1) * $filter_data['limit'];
        $filter_data['country'] = $this->session->get('country', 'USA');

        $data['is_partner'] = $isPartner;
        if( $customer_id && false == $isPartner){
            $data['download_csv_privilege'] = 1;
        }else{
            $data['download_csv_privilege'] = 0;
        }
        $data['heading_title'] = $this->language->get('heading_title');
        if (isset($request_search)) {
            $data['heading_title'] = $this->language->get('heading_title') .  ' - ' . urlencode(html_entity_decode($request_search, ENT_QUOTES, 'UTF-8'));
            $this->document->setTitle($this->language->get('heading_title') .  ' - ' . urlencode(html_entity_decode($request_search, ENT_QUOTES, 'UTF-8')));

        } elseif ($tag) {
            $this->document->setTitle($this->language->get('heading_title') .  ' - ' . $this->language->get('heading_tag') . $tag);

        } else {
            $this->document->setTitle($this->language->get('heading_title'));
        }

        $data['text_compare'] = sprintf($this->language->get('text_compare'), count($this->session->get('compare', [])));

        $data['compare'] = url()->to(['product/compare']);

        $data['products'] = [];
        $categoryIds = null;
        $productIdList = [];
        $product_total = 0;
        if (isset($filter_data['search'])
            || $filter_data['category_id']
            || $filter_data['max_price'] != ''
            || $filter_data['min_price'] != ''
            || $filter_data['max_quantity'] != ''
            || $filter_data['min_quantity'] != '')
        {
            // 默认搜索为sort_order
            try {
                $tmp = $this->model_catalog_search->searchRelevanceProductId($filter_data, $customer_id);
            } catch (Exception $e) {
                Logger::app($e);
                $tmp = null;
            }

            if($tmp){
                $product_total = $tmp['total'];
                $product_total_str = implode(',',$tmp['allProductIds']);
                $results = $this->model_catalog_search->search($filter_data,$customer_id,$isPartner,$tmp);
                $data['products'] = array_values($results);
                $categoryIds = $tmp['categoryIds'];
            }else{
                $product_total_str = '';
                $data['products'] = null;
            }

            if ($filter_data['search'] != ''
                && configDB('config_customer_search'))
            {
                $this->load->model('account/search');

                $search_data = array(
                    'keyword'       => $filter_data['search'],
                    'category_id'   => $filter_data['category_id'],
                    'sub_category'  => $filter_data['sub_category'],
                    'description'   => $filter_data['description'],
                    'products'      => $product_total,
                    'customer_id'   => $customer_id,
                    'ip'            => $this->request->serverBag->get('REMOTE_ADDR', ''),
                );

                $this->model_account_search->addSearch($search_data);
            }
        }

        // 3 Level Category Search
        $this->load->model('extension/module/product_category');
        $categories = $this->model_extension_module_product_category->getCategoryById( $filter_data['category_id'],$productIdList,$categoryIds);
        $data['categories'] = $categories;
        $category_all_list = [];
        $category_name = 'All';
        foreach ($categories as $id1 => $category1){
            if($filter_data['category_id'] == $category1['self_id']){
                $category_name = $category1['name'];
            }
            $category_all_list[$category1['self_id']] =  $category1;
            foreach ($category1['children'] ?? [] as $id2 => $category2){
                $category_all_list[$category2['self_id']] =  $category2;
                if($filter_data['category_id'] == $category2['self_id']){
                    $category_name = $category2['name'];
                }
                foreach ($category2['children'] ?? [] as $id3 => $category3){
                    $category_all_list[$category3['self_id']] =  $category3;
                    if($filter_data['category_id'] == $category3['self_id']){
                        $category_name = $category3['name'];
                    }
                }
            }
        }

        if(isset($category_all_list[$filter_data['category_id']])){
            $pid_all = $category_all_list[$filter_data['category_id']];
            $info = explode('_',$pid_all['all_pid']);
            foreach($info as $key => $value){
                $data['breadcrumbs'][] = [
                    'text' => $category_all_list[$value]['name'],
                    'href' => $category_all_list[$value]['href'],
                ];
            }
        }

        $url = '';
        if (isset($this->request->get['search'])) {
            $url .= '&search=' . urlencode(html_entity_decode($request_search, ENT_QUOTES, 'UTF-8'));
        }
        if (isset($this->request->get['tag'])) {
            $url .= '&tag=' . urlencode(html_entity_decode($this->request->get['tag'], ENT_QUOTES, 'UTF-8'));
        }
        if (isset($this->request->get['category_id'])) {
            $url .= '&category_id=' . $this->request->get['category_id'];
        }
        if (isset($this->request->get['sub_category'])) {
            $url .= '&sub_category=' . $this->request->get['sub_category'];
        }
        if (isset($this->request->get['min_price'])){
            $url .= '&min_price='.$filter_data['min_price'];
        }
        if (isset($this->request->get['max_price'])){
            $url .= '&max_price='.$filter_data['max_price'];
        }
        if (isset($this->request->get['min_quantity'])){
            $url .= '&min_quantity='.$filter_data['min_quantity'];
        }
        if (isset($this->request->get['max_quantity'])){
            $url .= '&max_quantity='.$filter_data['max_quantity'];
        }

        // 后续加的
        $searchCondition = [
            'rebates',
            'margin',
            'futures',
            'whId',
        ];
        array_map(function ($item) use (&$url) {
         if($this->request->get($item,false)){
              $url .= "&{$item}=".$this->request->get($item);
          }
        }, $searchCondition);

        $mainUrl = $url;

        if (isset($this->request->get['download_status'])) {
            $url .= '&download_status=' . $filter_data['download_status'];
        }
        if (isset($this->request->get['wish_status'])) {
            $url .= '&wish_status=' . $filter_data['wish_status'];
        }
        if (isset($this->request->get['purchase_status'])) {
            $url .= '&purchase_status=' . $filter_data['purchase_status'];
        }
        if (isset($this->request->get['relation_status'])) {
            $url .= '&relation_status=' . $filter_data['relation_status'];
        }
        if (isset($this->request->get['img_status'])){
            $url .= '&img_status=' . $filter_data['img_status'];
        }
        if (isset($this->request->get['qty_status'])){
            $url .= '&qty_status=' . $filter_data['qty_status'];
        }
        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }
        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }
        $data['limits'] = array();
        $limits = [20,40,60,100];
        foreach($limits as $value) {
            $data['limits'][] = array(
                'text'  => $value,
                'value' => $value,
                'href'  => $this->url->link('product/search', $url . '&limit=' . $value)
            );
        }

        if (isset($this->request->get['limit'])) {
            $mainUrl .= '&limit=' . $this->request->get['limit'];
            $url .= '&limit=' . $this->request->get['limit'];
        }
        $pagination = new Pagination();
        $pagination->total = $product_total;
        $pagination->page = $filter_data['page'];
        $pagination->limit = $filter_data['limit'];
        $pagination->limit_key = 'limit';
        $pagination->pageList = $limits;
        $pagination->url = $this->url->link('product/search', $url . '&page={page}');

        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'),
            ($product_total) ? (($filter_data['page'] - 1) * $filter_data['limit']) + 1 : 0,
            ((($filter_data['page'] - 1) * $filter_data['limit']) > ($product_total - $filter_data['limit'])) ? $product_total : ((($filter_data['page'] - 1) * $filter_data['limit']) + $filter_data['limit']),
            $product_total, ceil($product_total / $filter_data['limit']));

        $data = array_merge($data, $filter_data);
        $data['main_url'] = url()->to(['product/search']);

        $data['symbol_left'] = $this->currency->getSymbolLeft($this->session->get('currency'));
        $data['symbol_right'] = $this->currency->getSymbolRight($this->session->get('currency'));
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $data['products_total'] = $product_total;
        if(isset($product_total_str)){
            $data['products_to_csv'] = url()->to(['product/category/all_products_info', 'path' => ($request_search ?? '')]);
            $this->session->data['search_' . ($request_search ?? '')]['product_total_str'] = $product_total_str;
            $data['products_to_wish'] = url()->to(['account/wishlist/batchAdd', 'path' => ($request_search ?? '')]);
        }
        $data['isLogin'] = $customer_id ? true : false;
        $data['login'] = url()->to(['account/login']);
        $data['app_version'] = APP_VERSION;
        /*
         * 仓库筛选条件，仅美国本土上门取货账号支持该筛选条件
         * 美国本土Buyer：招商经理为美国的BD，即招商经理表中区域信息为美国；
        */
        $accountManagerRepo = app(AccountManagerRepository::class);
        $data['isWarehouseProductDistribution'] = false;
        if($this->customer->isCollectionFromDomicile()
            && $accountManagerRepo->isAmericanBd($customer_id)
        ){
            $data['warehouseList'] = app(WarehouseRepository::class)::getActiveAmericanWarehouse();
            $data['isWarehouseProductDistribution'] = true;
        }
        // 没有结果的时候出现
        if($data['products_total'] || !$data['search'] || $search_tag){
            return $this->response->setOutput($this->load->view('product/search', $data));
        }else{
            $data['logged_redirect_url'] = $this->request->serverBag->get('HTTP_HOST').$this->request->serverBag->get('REQUEST_URI');
            return $this->response->setOutput($this->load->view('product/no_result', $data));
        }
    }

    public function searchLog()
    {
        $currentDay = date('Y-m-d',time());
        $location = aliases('@runtime/logs/search/'.'search-'.$currentDay.'.log');
        $ret = file_get_contents($location);
        $s = explode('---',$ret);
        $string = trim($s[count($s) - 2]);
        return $this->response->json($string);


    }

    public function searchFeedBack()
    {
        $this->load->model('catalog/search');
        $keyword = $this->request->input->get('keyword');
        $content = $this->request->input->get('content');
        $json = [
            'error' => 0,
            'msg' => '',
            'redirect'=> '',
        ];
        $param = [
            'customer_id' => $this->customer->getId() ?: 0,
            'keyword' => $keyword,
            'content' => $content,
            'ip'      =>get_ip(),
            'program_code' => PROGRAM_CODE,
        ];
        try {
            $this->orm->getConnection()->beginTransaction();
            $this->model_catalog_search->searchFeedBackInsert($param);
            $json['redirect'] = url()->to(['product/search']);
            $json['msg'] = 'save successfully.';
            $this->orm->getConnection()->commit();
        }catch (Exception $e) {
            Logger::app('searchFeedBack 反馈出错', \Psr\Log\LogLevel::ERROR);
            Logger::app($e, \Psr\Log\LogLevel::ERROR);
            Logger::app(json_encode($this->request->input), \Psr\Log\LogLevel::ERROR);
            $json['msg'] = 'something wrong happened.';
            $json['error'] = 1;
            $this->orm->getConnection()->rollBack();
        }
        $this->response->json($json);
    }


    /**
     * 处理20210430batch download临时问题处理
     * [all_products_info description] 将查询条件获取
     * @throws Exception
     */
    public function all_products_info(){
        //验证是否有下载权限
        $custom_id =  $this->customer->getId();
        // 判断是否为 buyer 非buyer用户 直接无法获取下载权限
        $isPartner  = $this->customer->isPartner();
        if(null == $custom_id || true  == $isPartner){
            return $this->response->redirectTo(url()->to(['common/home']));
        }
        $type = $this->request->get('type');
        $product_str = $this->request->get('product_str');
        if($type == 0 && null != $product_str){
            $product_total_str = $product_str;
        }else{

            if($this->session->data[$this->request->get['path']]['product_total_str']){
                $product_total_str = $this->session->data[$this->request->get['path']]['product_total_str'];
            }else{
                echo "<script>window.location.href=document.referrer; </script>";
                exit;
            }

        }
        $this->load->model('catalog/product');
        $data = $this->model_catalog_product->getProductCategoryInfoByMySeller($product_total_str,$custom_id);
        //获取csv
        $this->load->model('tool/csv');
        $filename = 'ProductsInfo_'.date("YmdHis",time()).".csv";
        $this->model_tool_csv->getProductCategoryCsvByMySeller($filename,$data);

    }

    /**
     * [all_products_info description] 将查询条件获取
     * @throws Exception
     */
    public function all_products_infoBk(){
        //验证是否有下载权限
        $custom_id =  $this->customer->getId();
        // 判断是否为 buyer 非buyer用户 直接无法获取下载权限
        $isPartner  = $this->customer->isPartner();
        if(null == $custom_id || true  == $isPartner){
            return $this->response->redirectTo(url()->to(['common/home']));
        }
        $type = $this->request->get('type');
        $product_str = $this->request->get('product_str');
        if($type == 0 && null != $product_str){
            $product_total_str = $product_str;
        }else{

            if($this->session->data[$this->request->get['path']]['product_total_str']){
                $product_total_str = $this->session->data[$this->request->get['path']]['product_total_str'];
            }else{
                echo "<script>window.location.href=document.referrer; </script>";
                exit;
            }

        }
        $this->load->model('catalog/product');
        $data = $this->model_catalog_product->getProductCategoryInfo($product_total_str,$custom_id);
        //获取csv
        $this->load->model('tool/csv');
        $filename = 'ProductsInfo_'.date("YmdHis",time()).".csv";
        $this->model_tool_csv->getProductCategoryCsv($filename,$data);

    }


}
