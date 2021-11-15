<?php

use App\Helper\CountryHelper;

/**
 * Class ControllerAccountCustomerpartnerProductGroup
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountCustomerpartnerProductGroup $model_Account_Customerpartner_ProductGroup
 * @property ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 */
class ControllerAccountCustomerpartnerProductGroup extends Controller
{
    /**
     * @var ModelAccountCustomerpartnerProductGroup $model
     */
    protected $model;


    private $customer_id = null;
    private $isPartner = false;

    /**
     * ControllerAccountCustomerpartnerProductGroup constructor.
     * @param $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->load->model('Account/Customerpartner/ProductGroup');
        $this->model = $this->model_Account_Customerpartner_ProductGroup;

        $this->customer_id = $this->customer->getId();
        $this->load->model('account/customerpartner');
        $this->isPartner = $this->model_account_customerpartner->chkIsPartner();
        if (empty($this->customer_id) || !$this->isPartner) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->language('account/customerpartner/product_group');
    }

//region common
    private function checkPOST()
    {
        if (!request()->isMethod('POST')) {
            $response = [
                'error' => 1,
                'msg' => 'Bad Method!',
                'jump_url' => ''
            ];
            $this->returnJson($response);
        }
    }

    private function returnJson($response)
    {
        $this->response->returnJson($response);
    }
//endregion

//region page
    public function index()
    {
        $data = $this->load->language('account/customerpartner/product_group');
        $this->document->addScript("catalog/view/javascript/layer/layer.js");
        $this->document->setTitle($this->language->get('heading_title'));

        $data['heading_title'] = $this->language->get('heading_title');
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('heading_product_information'),
                'href' => 'javascript:void(0);'
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('account/customerpartner/productgroup', '', true)
            ]
        ];

        $data['load_group_id'] = get_value_or_default($this->request->get, 'group_id');

        // tips 时区跟随当前国别
        $country_times = [
            'DEU' => 'Berlin',
            'JPN' => 'Tokyo',
            'GBR' => 'London',
            'USA' => 'Pacific'
        ];
        if (in_array(session('country'), array_keys($country_times))) {
            $data['tip_update_time'] = str_replace('_current_country_', $country_times[session('country')], $this->language->get('tip_update_time'));
        }

        /**
         * url
         */
        $data['url_page_add_group'] = $this->url->link('account/customerpartner/productgroup/addgroup', '', true);
        $data['url_get_list'] = $this->url->link('account/customerpartner/productgroup/getList', '', true);
        $data['url_update'] = $this->url->link('account/customerpartner/productgroup/update', '', true);
        $data['url_remove'] = $this->url->link('account/customerpartner/productgroup/remove', '', true);
        $data['url_remove_link'] = $this->url->link('account/customerpartner/productgroup/linkDelete', '', true);
        $data['url_get_link_list'] = $this->url->link('account/customerpartner/productgroup/getLinkList', '', true);
        $data['url_get_product_by_group'] = $this->url->link('account/customerpartner/productgroup/getProductExceptGroup', '', true);
        $data['url_add_link'] = $this->url->link('account/customerpartner/productgroup/linkAdd', '', true);
        $data['url_image_prefix'] = HTTPS_SERVER . 'image/';
        $data['url_product_page'] = $this->url->link('product/product&product_id=', '', true);
        $data['url_download'] = $this->url->link('account/customerpartner/productgroup/download', '', true);

        // Common of Page
        if ($this->config->get('marketplace_separate_view')
            && isset($this->session->data['marketplace_separate_view'])
            && session('marketplace_separate_view') == 'separate') {
            $data['separate_view'] = true;
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        } else {
            $data['separate_view'] = false;
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['column_right'] = $this->load->controller('common/column_right');
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['separate_column_left'] = '';
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');
        }
        $this->response->setOutput($this->load->view('account/customerpartner/product_group', $data));
    }

    public function addGroup()
    {
        $data = $this->load->language('account/customerpartner/product_group');
        $this->document->addScript("catalog/view/javascript/layer/layer.js");
        $this->document->setTitle($this->language->get('heading_title_add_group'));

        $data['heading_title'] = $this->language->get('heading_title_add_group');
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('heading_product_information'),
                'href' => 'javascript:void(0);'
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('account/customerpartner/productgroup', '', true)
            ]
        ];

        /**
         * Url
         */
        $data['url_add'] = $this->url->link('account/customerpartner/productgroup/add', '', true);
        $data['url_page_back'] = $this->url->link('account/customerpartner/productgroup', '', true);
        $data['url_product_page'] = $this->url->link('product/product&product_id=', '', true);

        // Common of Page
        if ($this->config->get('marketplace_separate_view')
            && isset($this->session->data['marketplace_separate_view'])
            && session('marketplace_separate_view') == 'separate') {
            $data['separate_view'] = true;
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        } else {
            $data['separate_view'] = false;
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['column_right'] = $this->load->controller('common/column_right');
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['separate_column_left'] = '';
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');
        }
        $this->response->setOutput($this->load->view('account/customerpartner/product_group_add', $data));
    }
//endregion

//region group

    /**
     * 获取 所有的已上架并可以独立售卖的product
     */
    public function getProducts()
    {
        trim_strings($this->request->get);
        $sku_or_mpn = get_value_or_default($this->request->get, 'search_str', null);
        $results = $this->model->getProductInfoBySeller($this->customer_id, $sku_or_mpn);
        $num = 1;
        foreach ($results as &$result) {
            $result->name = strlen($result->name) > 100 ? (mb_substr($result->name, 0, 100) . '...') : $result->name;
            $result->num = $num++;
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($results));
    }

    /**
     * 添加分组
     */
    public function add()
    {
        $this->checkPOST();
        trim_strings($this->request->post);

        if (!isset_and_not_empty($this->request->post, 'name')) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_name_empty'),
            ];
            $this->returnJson($response);
        }

        if (!isset_and_not_empty($this->request->post, 'products') || !is_array($this->request->post['products'])) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_products_empty'),
            ];
            $this->returnJson($response);
        }

        if ($this->model->checkIsExistedByName($this->customer_id, $this->request->post['name'])) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_name_duplication'),
            ];
            $this->returnJson($response);
        }

        $ids = [];
        foreach ($this->request->post['products'] as $product) {
            if (is_numeric(trim($product))) {
                $ids[] = trim($product);
            }
        }

        if (!$this->model->checkProductIDs($this->customer_id, $ids)) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_choose_product_again'),
            ];
            $this->returnJson($response);
        }

        $input = [
            'name' => $this->request->post['name'],
            'description' => get_value_or_default($this->request->post, 'description', ''),
            'products' => $ids,
            'seller_id' => $this->customer_id
        ];
        $this->model->addGroup($input);
        $response = [
            'error' => 0,
            'msg' => 'Success!',
        ];
        $this->returnJson($response);
    }

    public function getList()
    {
        trim_strings($this->request->get);

        $input = [];
        $input['page'] = get_value_or_default($this->request->get, 'page', 1);
        $input['pageSize'] = get_value_or_default($this->request->get, 'pageSize', 20);
        isset_and_not_empty($this->request->get, 'name') && $input['name'] = $this->request->get['name'];
        isset_and_not_empty($this->request->get, 'sku_mpn') && $input['sku_mpn'] = $this->request->get['sku_mpn'];

        $results = $this->model->list($input, $this->customer_id);
        $num = ($input['page'] - 1) * $input['pageSize'] + 1;
        foreach ($results['data'] as &$datum) {
            $datum->num = $num++;
        }
        $this->returnJson(['total' => $results['total'], 'rows' => $results['data']]);
    }

    /**
     * 获取所有的 group
     */
    public function getAllList()
    {
        trim_strings($this->request->get);
        $input = [];
        isset_and_not_empty($this->request->get, 'name') && $input['name'] = $this->request->get['name'];
        $results = $this->model->getAllListAndNoPage($input, $this->customer_id);
        $num = 1;
        foreach ($results as &$datum) {
            $datum->num = $num++;
        }
        $this->returnJson($results);
    }

    public function getAllProductsAndGroups()
    {
        $results = $this->model->getProductInfoBySeller($this->customer_id, null);
        $products = [];
        foreach ($results as $result) {
            $products[] = $result->product_id;
        }

        $group_product_objs = $this->model->getGroupByProducts($this->customer_id, $products);

        $productID_group_arr = [];

        foreach ($group_product_objs as $group_product_obj) {
            $productID_group_arr[$group_product_obj->product_id][] = [
                'group_id' => $group_product_obj->group_id,
                'group_name' => $group_product_obj->name
            ];
        }

        $num = 1;
        foreach ($results as &$result) {
            $result->groups = get_value_or_default($productID_group_arr, $result->product_id, []);
            $result->name = strlen($result->name) > 100 ? (mb_substr($result->name, 0, 100) . '...') : $result->name;
            $result->num = $num++;
        }

        $this->returnJson($results);
    }

    public function update()
    {
        $this->checkPOST();
        trim_strings($this->request->post);
        if (!isset_and_not_empty($this->request->post, 'id')) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        if (!isset_and_not_empty($this->request->post, 'name')) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_name_empty'),
            ];
            $this->returnJson($response);
        }

        if (!$this->model->checkGroupIsExist($this->customer_id, $this->request->post['id'])) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        if ($this->model->checkIsExistedByName($this->customer_id, $this->request->post['name'], $this->request->post['id'])) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_name_duplication'),
            ];
            $this->returnJson($response);
        }

        $this->request->post['seller_id'] = $this->customer_id;
        $this->model->updateGroup($this->request->post);

        $result = $this->model->getSingleGroupInfo($this->request->post['id']);
        $response = [
            'error' => 0,
            'msg' => 'Success!',
            'data' => $result
        ];
        $this->returnJson($response);
    }

    /**
     * 删除分组
     * 注：为逻辑删除
     */
    public function remove()
    {
        $this->checkPOST();
        trim_strings($this->request->post);
        if (!isset_and_not_empty($this->request->post, 'group_id')
            || !$this->model->checkGroupIsExist($this->customer_id, $this->request->post['group_id'])
        ) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        $this->model->deleteGroup($this->customer_id, $this->request->post['group_id']);

        $response = [
            'error' => 0,
            'msg' => 'Success!',
        ];
        $this->returnJson($response);

    }
//endregion

//region link

    public function getLinkList()
    {
        trim_strings($this->request->get);
        if (!isset_and_not_empty($this->request->get, 'id')) {
            $results = [];
        } else {
            $results = $this->model->getLinkList(
                $this->customer_id,
                $this->request->get['id'],
                get_value_or_default($this->request->get, 'sku_mpn', null)
            );
        }
        $response = [
            'error' => 0,
            'msg' => 'Success!',
            'data' => $results
        ];
        $this->returnJson($response);
    }

    public function linkAdd()
    {
        $this->checkPOST();
        trim_strings($this->request->post);

        // Check param: group_id
        if (!isset_and_not_empty($this->request->post, 'group_id')
            || !$this->model->checkGroupIsExist($this->customer_id, $this->request->post['group_id'])
        ) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        // Check param: products
        if (!isset_and_not_empty($this->request->post, 'products') || !is_array($this->request->post['products'])) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        // Check: 这些 product 是否属于 当前 seller
        if (!$this->model->checkProductIDs($this->customer_id, $this->request->post['products'])) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        // 获取 已加入当前分组的 product
        $activeProducts = $this->model->getActiveProductsByGroup($this->customer_id, $this->request->post['group_id']);

        // 去除已加入当前分组的就product
        $diff_products = array_diff($this->request->post['products'], $activeProducts);

        // 如果 待添加的products 和 product group 关联的 buyer groups 下面的buyers 有建立精细化管理，需要删除
        $linkedBuyers = $this->model->getLinkedBuyersByGroup($this->customer_id, $this->request->post['group_id']);
        if (!empty($linkedBuyers)) {
            $this->load->model('customerpartner/DelicacyManagement');
            $this->model_customerpartner_DelicacyManagement->batchRemoveByProductsAndBuyers($this->request->post['products'], $linkedBuyers, $this->customer_id);
        }

        $this->model->addLink($this->customer_id, $this->request->post['group_id'], $diff_products);

        $response = [
            'error' => 0,
            'msg' => 'Success!',
        ];
        $this->returnJson($response);
    }

    /**
     *
     */
    public function linkDelete()
    {
        trim_strings($this->request->get);
        $this->checkPOST();
        if (!isset_and_not_empty($this->request->post, 'id')) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        $this->model->linkDelete($this->customer_id, $this->request->post['id']);
        $response = [
            'error' => 0,
            'msg' => 'Success!',
        ];
        $this->returnJson($response);
    }

    /**
     * 获取 尚未添加到group的其他product
     */
    public function getProductExceptGroup()
    {
        trim_strings($this->request->get);

        $results = $this->model->getProductByGroup(
            $this->customer_id,
            get_value_or_default($this->request->get, 'group_id')
        );

        $products = [];
        foreach ($results as $result) {
            $products[] = $result->product_id;
        }

        $group_product_objs = $this->model->getGroupByProducts($this->customer_id, $products);

        $productID_group_arr = [];

        foreach ($group_product_objs as $group_product_obj) {
            $productID_group_arr[$group_product_obj->product_id][] = [
                'group_id' => $group_product_obj->group_id,
                'group_name' => $group_product_obj->name
            ];
        }

        $num = 1;
        foreach ($results as &$result) {
            $result->groups = get_value_or_default($productID_group_arr, $result->product_id, []);
            $result->name = strlen($result->name) > 100 ? (mb_substr($result->name, 0, 100) . '...') : $result->name;
            $result->num = $num++;
        }

        $this->returnJson($results);
    }

//endregion

    public function download()
    {
        trim_strings($this->request->get);

        $input = [];
        isset_and_not_empty($this->request->get, 'name') && $input['name'] = $this->request->get['name'];
        isset_and_not_empty($this->request->get, 'sku_mpn') && $input['sku_mpn'] = $this->request->get['sku_mpn'];
        isset_and_not_empty($this->request->get, 'group_id') && $input['group_id'] = $this->request->get['group_id'];

        $results = $this->model->getDownloadList($input, $this->customer_id);
        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis",time()),'YmdHis');
        //12591 end
        $fileName = (isset_and_not_empty($this->request->get, 'group_id')
                ? get_value_or_default($results[0] ?? [], 'group_name', 'ProductGroups')
                : 'ProductGroups')
            . '_' . $time . '.csv';
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        echo chr(239) . chr(187) . chr(191);
        $fp = fopen('php://output', 'a');
        $header = [
            'Product Group Name',
            'Description',
            'Item Code',
            'MPN',
            'Product Name',
        ];

        foreach ($header as $i => $v) {
            // CSV的Excel支持GBK编码，一定要转换，否则乱码
            $header [$i] = iconv('utf-8', 'gbk', $v);
        }
        fputcsv($fp, $header);

        if (empty($results)) {
            fputcsv($fp, ['No Records.']);
        }
        foreach ($results as $result) {
            $content = [
                $result->group_name,
                html_entity_decode($result->group_description),
                $result->sku,
                $result->mpn,
                html_entity_decode($result->product_name),
            ];
            fputcsv($fp, $content);
        }
        $meta = stream_get_meta_data($fp);
        if (!$meta['seekable']) {
            $new_data = fopen('php://temp', 'r+');
            stream_copy_to_stream($fp, $new_data);
            rewind($new_data);
            $fp = $new_data;
        } else {
            rewind($fp);
        }
        $output = stream_get_contents($fp);
        fclose($fp);
        return $output;
    }
}
