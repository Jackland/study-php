<?php

use App\Helper\CountryHelper;

/**
 * @property  ModelAccountCustomerpartnerBuyerGroup $model_Account_Customerpartner_BuyerGroup
 * @property  ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 */
class ControllerAccountCustomerpartnerBuyerGroup extends Controller
{
    /**
     * @var ModelAccountCustomerpartnerBuyerGroup $model
     */
    protected $model;


    private $customer_id = null;
    private $isPartner = false;

    /**
     * ControllerAccountCustomerpartnerBuyerGroup constructor.
     * @param $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->load->model('Account/Customerpartner/BuyerGroup');
        $this->model = $this->model_Account_Customerpartner_BuyerGroup;

        $this->customer_id = $this->customer->getId();
        $this->load->model('account/customerpartner');
        $this->isPartner = $this->model_account_customerpartner->chkIsPartner();
        if (empty($this->customer_id) || !$this->isPartner) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->language('account/customerpartner/buyer_group');
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
    /**
     * Group List Page.
     */
    public function index()
    {
        $data = $this->load->language('account/customerpartner/buyer_group');
        $this->document->addScript("catalog/view/javascript/layer/layer.js");
        $this->document->setTitle($this->language->get('heading_title'));

        $data['heading_title'] = $this->language->get('heading_title');
        $data['breadcrumbs'] = [
            [
                'text' => $data['heading_parent_title'],
                'href' => 'javascript:void(0);'
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('account/customerpartner/buyergroup', '', true)
            ]
        ];

        $data['load_group_id'] = get_value_or_default($this->request->get, 'group_id');

        /**
         * url
         */
        $data['url_page_add_group'] = $this->url->link('account/customerpartner/buyergroup/addgroup', '', true);
        $data['url_get_list'] = $this->url->link('account/customerpartner/buyergroup/getList', '', true);
        $data['url_update'] = $this->url->link('account/customerpartner/buyergroup/update', '', true);
        $data['url_remove'] = $this->url->link('account/customerpartner/buyergroup/remove', '', true);
        $data['url_remove_link'] = $this->url->link('account/customerpartner/buyergroup/linkDelete', '', true);
        $data['url_get_link_list'] = $this->url->link('account/customerpartner/buyergroup/getLinkList', '', true);
        $data['url_get_buyer_by_group'] = $this->url->link('account/customerpartner/buyergroup/getBuyers', '', true);
        $data['url_add_link'] = $this->url->link('account/customerpartner/Buyergroup/linkAdd', '', true);
        $data['url_buyer_page'] = $this->url->link('buyer/buyer&buyer_id=', '', true);
        $data['url_download'] = $this->url->link('account/customerpartner/buyergroup/download', '', true);

        // tips 时区跟随当前国别
        $country_times = [
            'DEU' => 'Berlin',
            'JPN' => 'Tokyo',
            'GBR' => 'London',
            'USA' => 'Pacific'
        ];
        if (in_array($this->session->data['country'], array_keys($country_times))) {
            $data['tip_update_time'] = str_replace('_current_country_', $country_times[$this->session->data['country']], $this->language->get('tip_update_time'));
        }

        // Common of Page
        if ($this->config->get('marketplace_separate_view')
            && isset($this->session->data['marketplace_separate_view'])
            && $this->session->data['marketplace_separate_view'] == 'separate') {
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
        $this->response->setOutput($this->load->view('account/customerpartner/buyer_group', $data));
    }

    /**
     * Add Group Page.
     */
    public function addGroup()
    {
        $data = $this->load->language('account/customerpartner/buyer_group');
        $this->document->addScript("catalog/view/javascript/layer/layer.js");
        $this->document->setTitle($this->language->get('heading_title_add_group'));

        $data['heading_title'] = $this->language->get('heading_title_add_group');
        $data['breadcrumbs'] = [
            [
                'text' => $data['heading_parent_title'],
                'href' => 'javascript:void(0);'
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('account/customerpartner/buyergroup', '', true)
            ],
            [
                'text' => $this->language->get('heading_title_add_group'),
                'href' => $this->url->link('account/customerpartner/buyergroup/addgroup', '', true)
            ]
        ];

        /**
         * Url
         */
        $data['url_add'] = $this->url->link('account/customerpartner/buyergroup/add', '', true);
        $data['url_page_back'] = $this->url->link('account/customerpartner/buyergroup', '', true);
        $data['url_get_buyer_list'] = $this->url->link('account/customerpartner/buyergroup/getBuyers', '', true);

        // tips 时区跟随当前国别
        $country_times = [
            'DEU' => 'Berlin',
            'JPN' => 'Tokyo',
            'GBR' => 'London',
            'USA' => 'Pacific'
        ];
        if (in_array($this->session->data['country'], array_keys($country_times))) {
            $data['tip_update_time'] = str_replace('_current_country_', $country_times[$this->session->data['country']], $this->language->get('tip_update_time'));
        }

        // Common of Page
        if ($this->config->get('marketplace_separate_view')
            && isset($this->session->data['marketplace_separate_view'])
            && $this->session->data['marketplace_separate_view'] == 'separate') {
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
        $this->response->setOutput($this->load->view('account/customerpartner/buyer_group_add', $data));
    }
//endregion

//region group

    /**
     * 验证 是否存在默认分组
     */
    public function checkHasDefaultGroup()
    {
        $response = $this->model->checkHasDefault($this->customer_id);
        $response = [
            'error' => 0,
            'msg' => 'Success!',
            'data' => ['has_default_group' => $response]
        ];
        $this->returnJson($response);
    }

    /**
     * 获取尚未 加入分组的buyer
     */
    public function getBuyers()
    {
        trim_strings($this->request->get);
        $search_str = get_value_or_default($this->request->get, 'search_str', null);
        $results = $this->model->getBuyerInfoBySeller($this->customer_id, $search_str);
        $num = 1;
        foreach ($results as &$result) {
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

        if (!isset_and_not_empty($this->request->post, 'buyers') || !is_array($this->request->post['buyers'])) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_buyers_empty'),
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
        foreach ($this->request->post['buyers'] as $buyer) {
            if (is_numeric(trim($buyer))) {
                $ids[] = trim($buyer);
            }
        }

        if (!$this->model->checkBuyerIDs($this->customer_id, $ids)) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_choose_buyer_again'),
            ];
            $this->returnJson($response);
        }

        $input = [
            'name' => $this->request->post['name'],
            'description' => get_value_or_default($this->request->post, 'description', ''),
            'buyers' => $ids,
            'seller_id' => $this->customer_id,
            'is_default' => (isset($this->request->post['is_default']) && $this->request->post['is_default'] == 1) ? 1 : 0,
        ];
        $this->model->addGroup($input);
        $response = [
            'error' => 0,
            'msg' => 'Success!',
        ];
        $this->returnJson($response);
    }

    /**
     * 获取 group list
     */
    public function getList()
    {
        trim_strings($this->request->get);

        $input = [];
        $input['page'] = get_value_or_default($this->request->get, 'page', 1);
        $input['pageSize'] = get_value_or_default($this->request->get, 'pageSize', 20);
        isset_and_not_empty($this->request->get, 'name') && $input['name'] = $this->request->get['name'];
        isset_and_not_empty($this->request->get, 'nickname') && $input['nickname'] = $this->request->get['nickname'];

        $results = $this->model->list($input, $this->customer_id);
        $num = ($input['page'] - 1) * $input['pageSize'] + 1;
        foreach ($results['data'] as &$datum) {
            $datum->num = $num++;
        }
        $this->returnJson(['total' => $results['total'], 'rows' => $results['data']]);
    }

    /**
     * 更新分组信息
     */
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
        $this->request->post['is_default'] = isset($this->request->post['is_default']) && $this->request->post['is_default'] ? 1 : 0;
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
                get_value_or_default($this->request->get, 'nickname', null)
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

        if (!isset_and_not_empty($this->request->post, 'group_id')
            || !$this->model->checkGroupIsExist($this->customer_id, $this->request->post['group_id'])
        ) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        if (!isset_and_not_empty($this->request->post, 'buyers') || !is_array($this->request->post['buyers'])) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        if (!$this->model->checkBuyerIDs($this->customer_id, $this->request->post['buyers'])) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        $activeBuyers = $this->model->getActiveBuyersByGroup($this->customer_id, $this->request->post['group_id']);

        $diff_buyers = array_diff($this->request->post['buyers'], $activeBuyers);

        // 如果 待添加的buyers 和 buyer group 关联的 product groups 下面的products 有建立精细化管理，需要删除
        $linkedProducts = $this->model->getLinkedProductsByGroup($this->customer_id, $this->request->post['group_id']);
        if (!empty($linkedProducts)) {
            $this->load->model('customerpartner/DelicacyManagement');
            $this->model_customerpartner_DelicacyManagement->batchRemoveByProductsAndBuyers($linkedProducts, $this->request->post['buyers'], $this->customer_id);
        }

        $this->model->addLink($this->customer_id, $this->request->post['group_id'], $diff_buyers);

        $response = [
            'error' => 0,
            'msg' => 'Success!',
        ];
        $this->returnJson($response);
    }

    /**
     * 删除分组的关联
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

//endregion

    public function download()
    {
        trim_strings($this->request->get);

        $input = [];
        isset_and_not_empty($this->request->get, 'name') && $input['name'] = $this->request->get['name'];
        isset_and_not_empty($this->request->get, 'nickname') && $input['nickname'] = $this->request->get['nickname'];
        isset_and_not_empty($this->request->get, 'group_id') && $input['group_id'] = $this->request->get['group_id'];

        $results = $this->model->getDownloadList($input, $this->customer_id);
        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis",time()),'YmdHis');
        //12591 end
        $fileName = (isset_and_not_empty($this->request->get, 'group_id')
                ? get_value_or_default($results[0] ?? [], 'group_name', 'BuyerGroups')
                : 'BuyerGroups')
            . '_' . $time . '.csv';
        $header = [
            'No.',
            'Buyer Group Name',
            'Description',
            'Is Default',
            'Nickname',
            'Buyer Number',
            'Establish Contact Date'
        ];

        $num = 1;
        $content = array();
        foreach ($results as $key=>$result) {
            $content[$key] = [
                $num++,
                $result->group_name,
                html_entity_decode($result->group_description),
                $result->is_default?'Yes':'No',
                $result->nickname,
                $result->user_number,
                date('Y-m-d',strtotime($result->add_time))
            ];
        }
        //12591 B2B记录各国别用户的操作时间
        outputCsv($fileName,$header,$content,$this->session);
        //12591 end
    }
}
