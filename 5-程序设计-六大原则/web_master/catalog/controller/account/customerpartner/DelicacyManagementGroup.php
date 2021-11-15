<?php

use App\Helper\CountryHelper;
use App\Models\Customer\Customer;
use App\Widgets\VATToolTipWidget;

/**
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountCustomerpartnerBuyerGroup $model_Account_Customerpartner_BuyerGroup
 * @property ModelAccountCustomerpartnerProductGroup $model_Account_Customerpartner_ProductGroup
 * @property ModelCustomerPartnerDelicacyManagement $model_CustomerPartner_DelicacyManagement
 * @property ModelCustomerPartnerDelicacyManagementGroup $model_customerpartner_DelicacyManagementGroup
 */
class ControllerAccountCustomerPartnerDelicacyManagementGroup extends Controller
{
    private $customer_id = null;
    private $isPartner = false;

    /**
     * @var ModelCustomerPartnerDelicacyManagementGroup $model ;
     */
    private $model;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->customer_id = $this->customer->getId();
        $this->load->model('account/customerpartner');
        $this->isPartner = $this->model_account_customerpartner->chkIsPartner();
        if (empty($this->customer_id) || !$this->isPartner) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->model('customerpartner/DelicacyManagementGroup');
        $this->model = $this->model_customerpartner_DelicacyManagementGroup;

        $this->load->language('account/customerpartner/delicacy_management_group');
    }

//region Common
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

    /**
     * Page Index
     */
    public function index()
    {
        $data = $this->load->language('account/customerpartner/delicacy_management_group');
        $this->document->addScript("catalog/view/javascript/layer/layer.js");
        $this->document->setTitle($this->language->get('heading_title'));

        $data['heading_title'] = $this->language->get('heading_title');
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('heading_parent_title'),
                'href' => 'javascript:void(0);'
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('account/customerpartner/delicacymanagementgroup', '', true)
            ]
        ];

        // Url
        $data['url_get_products'] = $this->url->link('account/customerpartner/ProductGroup/getAllProductsAndGroups', '', true);
        $data['url_get_product_groups'] = $this->url->link('account/customerpartner/ProductGroup/getAllList', '', true);

        $data['url_get_buyers_by_product'] = $this->url->link('account/customerpartner/DelicacyManagementGroup/getBuyersByProduct', '', true);
        $data['url_get_buyer_groups_by_product'] = $this->url->link('account/customerpartner/DelicacyManagementGroup/getBuyerGroupsByProduct', '', true);

        $data['url_get_buyers_by_product_group'] = $this->url->link('account/customerpartner/DelicacyManagementGroup/getBuyersByProductGroup', '', true);
        $data['url_get_buyer_groups_by_product_group'] = $this->url->link('account/customerpartner/DelicacyManagementGroup/getBuyerGroupsByProductGroup', '', true);

        $data['url_get_unlink_buyer_groups_by_product_group'] = $this->url->link('account/customerpartner/DelicacyManagementGroup/getUnlinkBuyerGroupsByProductGroup', '', true);
        $data['url_get_unlink_buyers_by_product'] = $this->url->link('account/customerpartner/DelicacyManagementGroup/getUnlinkBuyersByProduct', '', true);

        $data['url_link_buyer_groups_by_pg'] = $this->url->link('account/customerpartner/DelicacyManagementGroup/linkBuyerGroupsByProductGroup', '', true);
        $data['url_link_buyers_by_p'] = $this->url->link('account/customerpartner/DelicacyManagement/batchAddSetInvisibleByBuyers', '', true);

        $data['url_product_page'] = $this->url->link('product/product&product_id=', '', true);
        $data['url_buyer_group_page'] = $this->url->link('account/customerpartner/buyergroup&group_id=', '', true);
        $data['url_product_group_page'] = $this->url->link('account/customerpartner/productgroup&group_id=', '', true);

        $data['url_batch_delete'] = $this->url->link('account/customerpartner/DelicacyManagementGroup/deleteByDMGIDs', '', true);
        $data['url_batch_delete_delicacy_manage'] = $this->url->link('account/customerpartner/delicacymanagement/batchRemove', '', true);

        $data['url_download'] = $this->url->link('account/customerpartner/DelicacyManagementGroup/download', '', true);
        $data['url_help'] = $this->url->link('information/information&information_id=61', '', true);

        // tips 时区跟随当前国别
        $country_times = [
            'DEU' => 'Berlin',
            'JPN' => 'Tokyo',
            'GBR' => 'London',
            'USA' => 'Pacific'
        ];
        if (in_array($this->session->data['country'] ?? '', array_keys($country_times))) {
            $data['tip_us_time'] = str_replace('_current_country_', $country_times[$this->session->data['country']], $this->language->get('tip_us_time'));
            $data['tip_p_bgs_create_date'] = str_replace('_current_country_', $country_times[$this->session->data['country']], $this->language->get('tip_p_bgs_create_date'));
            $data['tip_pg_bgs_create_date'] = str_replace('_current_country_', $country_times[$this->session->data['country']], $this->language->get('tip_pg_bgs_create_date'));
        }

        // Common of Page
        if ($this->config->get('marketplace_separate_view')
            && isset($this->session->data['marketplace_separate_view'])
            && $this->session->data['marketplace_separate_view'] == 'separate'
        ) {
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
        $this->response->setOutput($this->load->view('account/customerpartner/delicacy_management_group', $data));
    }

//region select

    /**
     * 根据 product 获取buyers
     */
    public function getBuyersByProduct()
    {
        if (!isset_and_not_empty($this->request->get, 'product_id')) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_choose_product_first'),
            ];
            $this->returnJson($response);
        }
        $input = [
            'product_id' => $this->request->get['product_id'],
            'seller_id' => $this->customer_id,
        ];
        $results = $this->model->getBuyersByProduct($input);
        $num = 1;

        $buyerIds = collect($results)->pluck('buyer_id');
        $buyerCustomerModelMap = Customer::query()->with('buyer')->whereIn('customer_id', $buyerIds)->get()->keyBy('customer_id');
        foreach ($results as &$result) {
            $result->num = $num++;
            $result->visibility = 'Invisible';
            $result->nickname .= '(' . $result->user_number . ')';
            if (in_array($result->customer_group_id, COLLECTION_FROM_DOMICILE)) {
                $result->is_home_pickup = true;
            }else{
                $result->is_home_pickup = false;
            }
            $result->ex_vat = VATToolTipWidget::widget(['customer' => $buyerCustomerModelMap->get($result->buyer_id), 'is_show_vat' => false])->render();
        }
        $this->returnJson(['rows' => $results]);

    }

    /**
     * 根据 product 获取 buyer_groups
     */
    public function getBuyerGroupsByProduct()
    {
        if (!isset_and_not_empty($this->request->get, 'product_id')) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_choose_product_first'),
            ];
            $this->returnJson($response);
        }
        $input = [
            'product_id' => $this->request->get['product_id'],
            'seller_id' => $this->customer_id,
        ];
        $results = $this->model->getBuyerGroupsByProduct($input);
        $num = 1;
        foreach ($results as &$result) {
            $result->num = $num++;
            $result->visibility = 'Invisible';
        }
        $this->returnJson(['rows' => $results]);
    }

    /**
     * 根据 product group 获取 buyers列表
     * 注：无分页，前端做分页。
     */
    public function getBuyersByProductGroup()
    {
        if (!isset_and_not_empty($this->request->get, 'product_group_id')) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_choose_product_group_first'),
            ];
            $this->returnJson($response);
        }
        $input = [
            'product_group_id' => $this->request->get['product_group_id'],
            'seller_id' => $this->customer_id,
        ];
        $results = $this->model->getBuyersByProductGroup($input);
        $num = 1;

        $buyerIds = collect($results)->pluck('buyer_id');
        $buyerCustomerModelMap = Customer::query()->with('buyer')->whereIn('customer_id', $buyerIds)->get()->keyBy('customer_id');
        foreach ($results as &$result) {
            $result->num = $num++;
            $result->visibility = 'Invisible';
            $result->nickname .= '(' . $result->user_number . ')';

            if (in_array($result->customer_group_id, COLLECTION_FROM_DOMICILE)) {
                $result->is_home_pickup = true;
            }else{
                $result->is_home_pickup = false;
            }
            $result->ex_vat = VATToolTipWidget::widget(['customer' => $buyerCustomerModelMap->get($result->buyer_id), 'is_show_vat' => false])->render();
        }
        $this->returnJson(['rows' => $results]);
    }


    /**
     * 根据 product group 获取 buyer group 列表
     * 注：无分页，前端做分页。
     */
    public function getBuyerGroupsByProductGroup()
    {
        if (!isset_and_not_empty($this->request->get, 'product_group_id')) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_choose_product_group_first'),
            ];
            $this->returnJson($response);
        }
        $input = [
            'product_group_id' => $this->request->get['product_group_id'],
            'seller_id' => $this->customer_id,
        ];
        $results = $this->model->getBuyerGroupsByProductGroup($input);
        $num = 1;
        foreach ($results as &$result) {
            $result->num = $num++;
            $result->visibility = 'Invisible';
        }
        $this->returnJson(['rows' => $results]);
    }

    /**
     * 根据 product group 获取尚未关联的 buyer group
     */
    public function getUnlinkBuyerGroupsByProductGroup()
    {
        if (!isset_and_not_empty($this->request->get, 'product_group_id')) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_choose_product_group_first'),
            ];
            $this->returnJson($response);
        }

        $results = $this->model->getUnlinkBuyerGroupsByProductGroup($this->customer_id, $this->request->get['product_group_id']);
        $num = 1;
        foreach ($results as &$result) {
            $result->num = $num++;
            $result->visibility = 'Invisible';
        }
        $this->returnJson(['rows' => $results]);
    }

    /**
     * 根据 product 获取尚未建立关联的buyers
     */
    public function getUnlinkBuyersByProduct()
    {
        if (!isset_and_not_empty($this->request->get, 'product_id')) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_choose_product_first'),
            ];
            $this->returnJson($response);
        }

        $results = $this->model->getLinkBuyerIDsByProduct($this->customer_id, $this->request->get['product_id']);
        $num = 1;

        $buyerIds = collect($results)->pluck('buyer_id');
        $buyerCustomerModelMap = Customer::query()->with('buyer')->whereIn('customer_id', $buyerIds)->get()->keyBy('customer_id');
        foreach ($results as &$result) {
            $result->num = $num++;
            $result->nickname .= '(' . $result->user_number . ')';
            if (in_array($result->customer_group_id, COLLECTION_FROM_DOMICILE)) {
                $result->is_home_pickup = true;
            }else{
                $result->is_home_pickup = false;
            }
            $result->ex_vat = VATToolTipWidget::widget(['customer' => $buyerCustomerModelMap->get($result->buyer_id), 'is_show_vat' => false])->render();
        }
        $this->returnJson(['rows' => $results]);
    }

//endregion

//region Add

    /**
     * 添加 buyer groups 和 product group的关联
     */
    public function linkBuyerGroupsByProductGroup()
    {
        $this->checkPOST();
        trim_strings($this->request->post);

        if (!isset_and_not_empty($this->request->post, 'product_group_id')) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_choose_product_group_first'),
            ];
            $this->returnJson($response);
        }

        if (!isset_and_not_empty($this->request->post, 'buyer_groups') || !is_array($this->request->post['buyer_groups'])) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_choose_buyer_group_at_lease'),
            ];
            $this->returnJson($response);
        }

        array_walk($this->request->post['buyer_groups'], function ($buyer_group) {
            if (!is_numeric($buyer_group)) {
                $response = [
                    'error' => 1,
                    'msg' => $this->language->get('error_common'),
                ];
                $this->returnJson($response);
            }
        });

        if (!$this->model->checkBuyerGroups($this->customer_id, $this->request->post['buyer_groups'])) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        /**
         * 1.分别获取 buyer_ids 和 product_ids
         * 2.根据1 获取dm_id
         * 3.删除这些dm
         */
        $this->load->model('Account/Customerpartner/BuyerGroup');
        $this->load->model('Account/Customerpartner/ProductGroup');

        $dm_ids = $this->model->getDelicacyManagementsByPsAndBs(
            $this->customer_id,
            $this->model_Account_Customerpartner_BuyerGroup->getBuyerIDsByBuyerGroups(
                $this->customer_id,
                $this->request->post['buyer_groups']
            ),
            $this->model_Account_Customerpartner_ProductGroup->getProductIDsByProductGroups(
                $this->customer_id,
                [$this->request->post['product_group_id']]
            )
        );

        $this->load->model('CustomerPartner/DelicacyManagement');
        $this->model_CustomerPartner_DelicacyManagement->batchRemove(array_unique($dm_ids), $this->customer_id);

        // 添加 product组和buyer组的关系
        $this->model->addGroups($this->customer_id, [$this->request->post['product_group_id']], $this->request->post['buyer_groups']);

        $response = [
            'error' => 0,
            'msg' => 'Success!',
        ];
        $this->returnJson($response);
    }

//endregion

//region Delete
    /**
     * 根据 dmg_ids删除
     */
    public function deleteByDMGIDs()
    {
        $this->checkPOST();
        trim_strings($this->request->post);

        if (
            !isset_and_not_empty($this->request->post, 'dmg_ids')
            || !is_array($this->request->post['dmg_ids'])
            || !$this->model->checkDMGIDsExisted($this->customer_id, $this->request->post['dmg_ids'])
        ) {
            $response = [
                'error' => 1,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        $this->model->deleteByDMGIDs($this->customer_id, $this->request->post['dmg_ids']);
        $response = [
            'error' => 0,
            'msg' => 'Success!',
        ];
        $this->returnJson($response);
    }
//endregion

//region Download

    /**
     * Download
     */
    public function download()
    {
        switch (get_value_or_default($this->request->get, 'type', 'pg_bgs')) {
            case 'pg_bgs':
                $this->downloadByPgBgs();
                break;
            case 'pg_bs':
                $this->downloadByPgBs();
                break;
            case 'p_bgs':
            case 'p_bs':
                $this->downloadByPBgs();
                break;
            default:
                $this->downloadByPgBgs();
                break;
        }
    }

    private function downloadByPgBgs()
    {

        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis",time()),'Ymd');
        //12591 end
        $fileName = 'InvisibleProductsSetting(Product Groups)' . $time . '.csv';
        $header = [
            'Product Group',
            'Description',
            'Buyer Group',
            'Name(User Number)',
            'Remark',
            'Visibility',
            'Creation Date'
        ];
        $results = $this->model->getDataByPgBg($this->customer_id);
        $content = array();
        foreach ($results as $key=> $result) {
            $content[$key] = [
                html_entity_decode($result->product_group_name),
                html_entity_decode($result->product_group_description),
                html_entity_decode($result->buyer_group_name),
                html_entity_decode($result->nickname) . '(' . $result->user_number . ')',
                html_entity_decode($result->remark),
                'Invisible',
                $result->add_time
            ];
        }
        //12591 B2B记录各国别用户的操作时间
        outputCsv($fileName,$header,$content,$this->session);
        //12591 end
    }

    private function downloadByPgBs()
    {
        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis",time()),'Ymd');
        //12591 end
        $fileName = 'InvisibleProductsSetting(Product Groups)' . $time . '.csv';
        $header = [
            'Product Group',
            'Description',
            'Name(User Number)',
            'Remark',
            'Buyer Group',
            'Visibility',
            'Creation Date'
        ];

        $results = $this->model->getDataByPgBg($this->customer_id);

        $content = array();
        foreach ($results as $key=> $result) {
            $content[$key] = [
                html_entity_decode($result->product_group_name),
                html_entity_decode($result->product_group_description),
                html_entity_decode($result->nickname) . '(' . $result->user_number . ')',
                html_entity_decode($result->remark),
                html_entity_decode($result->buyer_group_name),
                'Invisible',
                $result->add_time
            ];
        }
        //12591 B2B记录各国别用户的操作时间
        outputCsv($fileName,$header,$content,$this->session);
        //12591 end
    }

    private function downloadByPBgs()
    {
        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis",time()),'Ymd');
        //12591 end
        $fileName = 'InvisibleProductsSetting(Products)' .$time . '.csv';
        $header = [
            'Item Code',
            'MPN',
            'Product Name',
            'Product Group',
            'Name(User Number)',
            'Remark',
            'Buyer Group',
            'Visibility',
            'Creation Date'
        ];
        $results = $this->model->getDataByPBg($this->customer_id);
        $content = array();
        foreach ($results as $key=> $result) {
            $content[$key] = [
                $result->sku,
                $result->mpn,
                html_entity_decode($result->product_name),
                html_entity_decode($result->product_group_name??''),
                html_entity_decode($result->nickname) . '(' . $result->user_number . ')',
                html_entity_decode($result->remark),
                html_entity_decode($result->buyer_group_name),
                'Invisible',
                $result->add_time
            ];
        }
        //12591 B2B记录各国别用户的操作时间
        outputCsv($fileName,$header,$content,$this->session);
        //12591 end
    }

//endregion
}
