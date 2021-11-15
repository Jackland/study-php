<?php

use App\Components\Storage\StorageCloud;

/**
 * @property ModelCustomerpartnerMarketingcampaignRequest $model_customerpartner_Marketing_campaign_Request
 * @property ModelToolImage $model_tool_image
 */
class ControllerCustomerpartnerMarketingCampaignRequest extends Controller
{
    /**
     * @var ModelCustomerpartnerMarketingcampaignRequest $model
     */
    private $model;

    /**
     * @var array $data
     */
    private $data;

    /**
     * @var int $precision
     */
    private $precision;

    /**
     * ControllerCustomerpartnerMarketingCampaignRequest constructor.
     * @param Registry $registry
     * @throws Exception
     */
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        if (!$this->customer->isLogged() || !$this->customer->isPartner()) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->model('customerpartner/Marketing_campaign/Request');
        $this->model = $this->model_customerpartner_Marketing_campaign_Request;
        $this->data = $this->load->language('customerpartner/Marketing_campaign/request');

        $this->precision = $this->customer->isJapan() ? 0 : 2;
    }

    /**
     * @throws Exception
     */
    public function index()
    {
        trim_strings($this->request->get);

        if (!isset_and_not_empty($this->request->get, 'id')) {
            $this->response->redirect($this->url->link('error/not_found', '', true));
            return;
        }

        $activityObj = $this->model->getActivityInfo($this->request->get['id'], $this->customer->getCountryId());
        if (empty($activityObj)) {
            $this->indexError();
            return;
        }

        $this->data['id'] = $this->request->get['id'];

        $this->data['activity_title'] = $activityObj->seller_activity_name ?? $activityObj->name;
        $this->document->setTitle($this->language->get('heading_title'));
        $this->data['url_save'] = $this->url->link('customerpartner/marketing_campaign/request/save', '', true);
        $this->data['url_page_back'] = $this->url->link('customerpartner/marketing_campaign/index/activity#proEvents', '', true);
        $this->data['url_page_history'] = $this->url->link('customerpartner/marketing_campaign/history', '', true);

        //验证当前是否已申请( 待审核 & 已同意 )
        if ($this->model->checkHasRequest($this->request->get['id'], $this->customer->getId())) {
            $this->data['error_message'] = $this->language->get('error_reapply');
        }

        if ($activityObj->type == 1) {
            $this->indexBanner($activityObj);
        } else {
            $this->indexNormal($activityObj);
        }
    }

    /**
     * Banner类申请
     *
     * @param object $activityObj
     * @throws Exception
     * @return void
     */
    private function indexBanner($activityObj)
    {
        $this->document->addScript('catalog/view/javascript/layui/layui.all.js');
        $this->data['url_banner_upload'] = $this->url->link('customerpartner/marketing_campaign/request/upload', '', true);
        $this->data['url_seller_store'] = $this->url->link('customerpartner/profile', 'id=' . $this->customer->getId(), true);
        $this->data['reapply_data'] = [];
        $this->data['banner_setting'] = $this->model->getBannerSetting();
        if (isset_and_not_empty($this->request->get, 'request_id')) {
            $oldRequestObj = $this->model->getBannerRequestForReapply(
                $this->request->get['id'],
                $this->request->get['request_id'],
                $this->customer->getId()
            );
            if (empty($oldRequestObj)) {
                $this->data['error_message'] = $this->language->get('error_reapply');
            } else {
                $this->data['url_banner_url'] = $oldRequestObj->banner_url;
                $this->data['banner_description'] = $oldRequestObj->banner_description;
                $this->data['banner_image'] = $oldRequestObj->banner_image;
                $this->data['banner_image_uri'] = StorageCloud::upload()->getUrl($oldRequestObj->banner_image, ['w' => round($this->data['banner_setting']['width'] / 3), 'h' => round($this->data['banner_setting']['height'] / 3)]);
                $this->session->data['marketing_campaign_' . $this->request->get['id']] = $oldRequestObj->banner_image;
            }
        }
        $this->data['banner_setting']['t_width'] = round($this->data['banner_setting']['width'] / 3);
        $this->data['banner_setting']['t_height'] = round($this->data['banner_setting']['height'] / 3);

        $this->loadCommon();

        $this->response->setOutput($this->load->view('customerpartner/marketing_campaign/request_banner', $this->data));
    }

    /**
     * 非Banner类申请
     *
     * @param $activityObj
     * @throws Exception
     */
    private function indexNormal($activityObj)
    {
        $this->data['url_get_products'] = $this->url->link('customerpartner/marketing_campaign/request/getCanApplyProducts&id=' . $this->request->get['id'], '', true);
        $this->loadCommon();
        $this->data['product_num_per'] = $activityObj->product_num_per;
        $this->data['error_max_product'] = str_replace('_x_', $this->data['product_num_per'], $this->language->get('error_max_product'));
        if (isset_and_not_empty($this->request->get, 'request_id')) {
            $oldProductIDs = $this->model->getNormalRequestForReapply(
                $this->request->get['id'],
                $this->request->get['request_id'],
                $this->customer->getId()
            );
            if (empty($oldProductIDs)) {
                $this->data['error_message'] = $this->language->get('error_reapply');
            } else {
                $this->data['old_product_ids'] = implode(',', $oldProductIDs);
            }
        }

        $this->response->setOutput($this->load->view('customerpartner/marketing_campaign/request_normal', $this->data));
    }

    private function indexError()
    {
        $this->loadCommon();
        $this->data['url_list'] = $this->url->link('customerpartner/marketing_campaign/index/activity#proEvents', '', true);
        $this->data['error_message'] = $this->language->get('error_not_in_activity');
        $this->response->setOutput($this->load->view('customerpartner/marketing_campaign/request_error', $this->data));
    }

    /**
     * @throws Exception
     */
    public function getCanApplyProducts()
    {
        if (!isset_and_not_empty($this->request->get, 'id')) {
            $result = [
                'code' => 500,
                'msg' => $this->language->get('error_timeout'),
            ];
            $this->response->returnJson($result);
        }

        $activityObj = $this->model->getActivityInfo($this->request->get['id'], $this->customer->getCountryId());
        if (empty($activityObj)) {
            $result = [
                'code' => 500,
                'msg' => $this->language->get('error_timeout'),
            ];
            $this->response->returnJson($result);
        }

        $filterData = [];
        if (!empty($activityObj->require_category)) {
            $filterData['require_category_arr'] = explode(',', $activityObj->require_category);
        }
        $filterData['require_pro_min_stock'] = $activityObj->require_pro_min_stock;
        if (!empty($activityObj->require_pro_start_time) && $activityObj->require_pro_start_time != '1970-01-01 00:00:00') {
            $filterData['require_pro_start_time'] = $activityObj->require_pro_start_time;
        }
        if (!empty($activityObj->require_pro_end_time) && $activityObj->require_pro_end_time != '9999-12-31 23:59:59') {
            $filterData['require_pro_end_time'] = $activityObj->require_pro_end_time;
        }

        $objs = $this->model->getCanApplyProducts($this->customer->getId(), $filterData);
        $this->load->model('tool/image');
        foreach ($objs as &$obj) {
            $obj->image = $this->model_tool_image->resize($obj->image, 36, 36);
            $obj->price = round($obj->price, $this->precision);
            $obj->price_str = $this->currency->formatCurrencyPrice($obj->price, $this->session->data['currency']);
        }
        $result = [
            'code' => 200,
            'rows' => $objs,
        ];
        $this->response->returnJson($result);
    }

    private function loadCommon()
    {
        $this->data['breadcrumbs'] = [
//            [
//                'text' => $this->language->get('text_home'),
//                'href' => $this->url->link('common/home', '', true)
//            ],
//            [
//                'text' => $this->language->get('bc_seller_center'),
//                'href' => $this->url->link('customerpartner/seller_center/index', '', true)
//            ],
            [
                'text' => $this->language->get('bc_promotions'),
                'href' => 'javascript:void(0);'
            ],
            [
                'text' => $this->language->get('bc_promotion_list'),
                'href' => $this->url->link('customerpartner/marketing_campaign/index/activity#proEvents', '', true)
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('customerpartner/marketing_campaign/request', '&id=' . $this->request->get['id'], true)
            ]
        ];

        // Common of Page
        $this->data['separate_view'] = true;
        $this->data['column_left'] = '';
        $this->data['column_right'] = '';
        $this->data['content_top'] = '';
        $this->data['content_bottom'] = '';
        $this->data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
        $this->data['footer'] = $this->load->controller('account/customerpartner/footer');
        $this->data['header'] = $this->load->controller('account/customerpartner/header');
    }

    /**
     * Banner 上传图片专用
     * 图片路径为 storage/upload/marketing_campaign/customer_id/
     * 缓存图片路径为 storage/upload/cache/marketing_campaign/customer_id/
     */
    public function upload()
    {
        if (!isset_and_not_empty($this->request->post, 'id')) {
            $result = [
                'code' => 500,
                'msg' => $this->language->get('error_timeout'),
            ];
            $this->response->returnJson($result);
        }

        $allow_type = ['image/png' => 'png', 'image/jpg' => 'jpg', 'image/jpeg' => 'jpeg'];
        $allow_size = 30;   // 单位M
        if (empty($this->request->files['file'])) {
            $result = [
                'code' => 1,
                'msg' => 'Please select a image file',
            ];
            $this->response->returnJson($result);
        }

        // check the file type
        if (!isset($this->request->files['file']['tmp_name'])
            || empty($this->request->files['file']['type'])
            || !in_array($this->request->files['file']['type'], array_keys($allow_type))
        ) {
            $result = [
                'code' => 1,
                'msg' => $this->language->get('error_file_type'),
            ];
            $this->response->returnJson($result);
        }

        // check the file extension
        if (!isset_and_not_empty($this->request->files['file'], 'name')) {
            $result = [
                'code' => 1,
                'msg' => $this->language->get('error_file_type'),
            ];
            $this->response->returnJson($result);
        }
        $path_info = pathinfo($this->request->files['file']['name']);
        if (!isset($path_info['extension']) || !in_array(strtolower($path_info['extension']), $allow_type)) {
            $result = [
                'code' => 1,
                'msg' => $this->language->get('error_file_type'),
                'file' => $this->request->files['file']
            ];
            $this->response->returnJson($result);
        }

        // check file size
        if (empty($this->request->files['file']['size']) || $this->request->files['file']['size'] > $allow_size * 1024 * 1024) {
            $result = [
                'code' => 1,
                'msg' => $this->language->get('error_file_size'),
            ];
            $this->response->returnJson($result);
        }

        $content = file_get_contents($this->request->files['file']['tmp_name']);

        // check the file content
        if (preg_match('/\<\?php/i', $content)) {
            $result = [
                'code' => 1,
                'msg' => $this->language->get('error_file_type'),
            ];
            $this->response->returnJson($result);
        }

        $banner_setting = $this->model->getBannerSetting();
        $new_file_name_no_ext = token(33);  // 文件名称
        $new_file_name = $new_file_name_no_ext . '.' . $path_info['extension'];
        $new_file_path_prefix = 'marketing_campaign/' . $this->customer->getId() . '/';
        $new_file_relative_path = $new_file_path_prefix . $new_file_name;
        StorageCloud::upload()->writeFile(request()->filesBag->get('file'), $new_file_path_prefix, $new_file_name);

        $this->session->data['marketing_campaign_' . $this->request->post['id']] = $new_file_relative_path;

        $result = [
            'code' => 200,
            'msg' => $this->language->get('text_success'),
            'data' => [
                'file_url' => StorageCloud::upload()->getUrl($new_file_relative_path),
                'cache_file_url' => StorageCloud::upload()->getUrl($new_file_relative_path, ['w' => round($banner_setting['width'] / 3), 'h' => round($banner_setting['height'] / 3)]),
                'relative_path' => $new_file_relative_path,
            ]
        ];
        $this->response->returnJson($result);
    }

    /**
     * 添加申请
     */
    public function save()
    {
        trim_strings($this->request->post);
        if (!isset_and_not_empty($this->request->post, 'id')) {
            $result = [
                'code' => 500,
                'msg' => $this->language->get('error_timeout'),
            ];
            $this->response->returnJson($result);
        }

        $activityObj = $this->model->getActivityInfo($this->request->post['id'], $this->customer->getCountryId());
        if (empty($activityObj)) {
            $result = [
                'code' => 500,
                'msg' => $this->language->get('error_timeout'),
            ];
            $this->response->returnJson($result);
        }

        // 验证报名人数是否已达最大
        if ($activityObj->seller_num <= $this->model->countRequest($this->request->post['id'])) {
            $result = [
                'code' => 1,
                'msg' => $this->language->get('error_max_apply_num'),
            ];
            $this->response->returnJson($result);
        }

        //验证当前是否已申请( 待审核 & 已同意 )
        if ($this->model->checkHasRequest($this->request->post['id'], $this->customer->getId())) {
            $result = [
                'code' => 1,
                'msg' => $this->language->get('error_reapply'),
            ];
            $this->response->returnJson($result);
        }

        if ($activityObj->type == 1) {
            $this->saveBanner($activityObj);
        } else {
            $this->saveNormal($activityObj);
        }
    }

    /**
     * Banner类型的添加
     *
     * @param $activityObj
     */
    private function saveBanner($activityObj)
    {
        // check url
        if (!isset_and_not_empty($this->request->post, 'url')) {
            $result = [
                'code' => 1,
                'msg' => $this->language->get('error_banner_empty_url'),
            ];
            $this->response->returnJson($result);
        }

        // check upload image
        if (!isset_and_not_empty($this->session->data, 'marketing_campaign_' . $this->request->post['id'])
            || !isset_and_not_empty($this->request->post, 'image')
            || $this->session->data['marketing_campaign_' . $this->request->post['id']] != $this->request->post['image']
        ) {
            $result = [
                'code' => 500,
                'msg' => $this->language->get('error_timeout'),
            ];
            $this->response->returnJson($result);
        }

        // check description
        if (!isset_and_not_empty($this->request->post, 'description')) {
            $result = [
                'code' => 1,
                'msg' => $this->language->get('error_banner_empty_description'),
            ];
            $this->response->returnJson($result);
        }

        $keyVal = [
            'mc_id' => $this->request->post['id'],
            'seller_id' => $this->customer->getId(),
            'banner_url' => $this->request->post['url'],
            'banner_image' => $this->session->data['marketing_campaign_' . $this->request->post['id']],
            'banner_description' => $this->request->post['description'],
        ];
        if ($this->model->saveBanner($keyVal)) {
            $result = [
                'code' => 200,
                'msg' => $this->language->get('text_success'),
            ];
        } else {
            $result = [
                'code' => 500,
                'msg' => $this->language->get('error_timeout'),
            ];
        }
        $this->response->returnJson($result);
    }

    /**
     * @param object $activityObj
     */
    private function saveNormal($activityObj)
    {
        if (!isset_and_not_empty($this->request->post, 'product_ids')) {
            $result = [
                'code' => 1,
                'msg' => $this->language->get('error_no_select'),
            ];
            $this->response->returnJson($result);
        }

        $product_ids = $this->request->post['product_ids'];

        $filterData = [];
        if (!empty($activityObj->require_category)) {
            $filterData['require_category_arr'] = explode(',', $activityObj->require_category);
        }
        $filterData['require_pro_min_stock'] = $activityObj->require_pro_min_stock;
        if (!empty($activityObj->require_pro_start_time) && $activityObj->require_pro_start_time != '1970-01-01 00:00:00') {
            $filterData['require_pro_start_time'] = $activityObj->require_pro_start_time;
        }
        if (!empty($activityObj->require_pro_end_time) && $activityObj->require_pro_end_time != '9999-12-31 23:59:59') {
            $filterData['require_pro_end_time'] = $activityObj->require_pro_end_time;
        }

        if (!$this->model->checkRequestProducts($this->customer->getId(), $filterData, $product_ids)) {
            $result = [
                'code' => 500,
                'msg' => $this->language->get('error_timeout1'),
            ];
            $this->response->returnJson($result);
        }
        $keyVal = [
            'mc_id' => $this->request->post['id'],
            'seller_id' => $this->customer->getId(),
            'product_ids' => $product_ids
        ];
        if ($this->model->saveNormal($keyVal)) {
            $result = [
                'code' => 200,
                'msg' => $this->language->get('text_success'),
            ];
        } else {
            $result = [
                'code' => 500,
                'msg' => $this->language->get('error_timeout2'),
            ];
        }
        $this->response->returnJson($result);
    }
}
