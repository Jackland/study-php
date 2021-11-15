<?php

use App\Enums\Buyer\BuyerType;
use App\Enums\Message\MsgCustomerExtLanguageType;
use App\Helper\CountryHelper;
use App\Models\Customer\Customer;
use App\Models\Buyer\Buyer;
use App\Models\Message\MsgCustomerExt;
use App\Widgets\VATToolTipWidget;
use App\Repositories\Buyer\BuyerToSellerRepository;




/**
 * Class ControllerAccountCustomerpartnerBuyerManagementList
 *
 * @property ModelAccountCustomerpartnerBuyerGroup $model_account_customerpartner_BuyerGroup
 * @property ModelCustomerpartnerBuyers $model_customerpartner_buyers
 * @property ModelCustomerPartnerDelicacyManagement $model_customerpartner_DelicacyManagement
 * @property ModelCustomerpartnerSellerCenterIndex model_customerpartner_seller_center_index
 */
class ControllerAccountCustomerpartnerBuyerManagementList extends Controller
{
    private $customer_id = null;

    /**
     * 是否为seller
     * @var bool $isPartner
     */
    private $isPartner = false;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->customer_id = $this->customer->getId();
        $this->isPartner = $this->customer->isPartner();
        if (empty($this->customer_id) || !$this->isPartner) {
            $this->response->redirect($this->url->link('account/login', '', true));
        }
    }

    public function index()
    {
        $data = $this->load->language('account/customerpartner/buyer_management/list');
        $this->document->addScript("catalog/view/javascript/layer/layer.js");

        $this->document->setTitle($this->language->get('heading_title'));
        $data['chkIsPartner'] = $this->isPartner;

        $data['breadcrumbs'] = [
            [
                'text' => $data['heading_parent_title'],
                'href' => 'javascript:void(0);'
            ],
            [
                'text' => $data['heading_title'],
                'href' => $this->url->link('account/customerpartner/buyer_management/list', '', true)
            ]
        ];

        $this->load->model('customerpartner/buyers');
        $data['url_page_active'] = $this->url->link('account/customerpartner/buyer_management/list/activePage', '', true);
        $data['url_page_inactive'] = $this->url->link('account/customerpartner/buyer_management/list/inactivePage', '', true);


        if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
            $data['separate_view'] = true;
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        } else {
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['column_right'] = $this->load->controller('common/column_right');
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');
            $data['separate_view'] = false;
            $data['separate_column_left'] = '';
        }

        $this->response->setOutput($this->load->view('account/customerpartner/buyer_management/list_index', $data));
    }

    public function activePage()
    {
        $data = $this->load->language('account/customerpartner/buyer_management/list');

        // 获取 buyer group
        $this->load->model('account/customerpartner/BuyerGroup');
        $data['buyer_groups'] = $this->model_account_customerpartner_BuyerGroup->getGroupsForSelect($this->customer_id);
        $data['json_buyer_groups'] = json_encode($data['buyer_groups']);

        $data['url_page_active'] = $this->url->link('account/customerpartner/buyer_management/list/activePage', '', true);
        $data['url_list_active'] = $this->url->link('account/customerpartner/buyer_management/list/getActiveList', '', true);
        $data['url_delicacy_management'] = $this->url->link('account/customerpartner/delicacymanagement', '', true);
        $data['url_download'] = $this->url->link('account/customerpartner/buyer_management/list/download', '', true);
        //用户画像
//        $data['styles'][]='catalog/view/javascript/layer/layer.js';
//        $data['scripts'][]='catalog/view/javascript/layer/layer.js';
        $data['styles'][]='catalog/view/javascript/user_portrait/user_portrait.css?v=' . APP_VERSION;
        $data['scripts'][]='catalog/view/javascript/user_portrait/user_portrait.js?v=' . APP_VERSION;
        $data['customer_id'] = $this->customer_id;
        //能否显示联系人电话
        $data['can_show_contacts_phone'] = (int)!customer()->isGigaOnsiteSeller();

        $this->response->setOutput($this->load->view('account/customerpartner/buyer_management/list_active', $data));
    }

    public function inactivePage()
    {
        $data = $this->load->language('account/customerpartner/buyer_management/list');
        $data['url_page_inactive'] = $this->url->link('account/customerpartner/buyer_management/list/inactivePage', '', true);
        $data['url_list_inactive'] = $this->url->link('account/customerpartner/buyer_management/list/getInactiveList', '', true);

        $data['url_recovery'] = $this->url->link('account/customerpartner/buyer_management/list/recovery', '', true);
        $data['url_batch_recovery'] = $this->url->link('account/customerpartner/buyer_management/list/batchRecovery', '', true);
        //能否显示联系人电话
        $data['can_show_contacts_phone'] = (int)!customer()->isGigaOnsiteSeller();
        $this->response->setOutput($this->load->view('account/customerpartner/buyer_management/list_inactive', $data));
    }


    public function getActiveList()
    {
        trim_strings($this->request->get);
        $input = $this->request->get;
        $input['seller_id'] = $this->customer_id;
        $input['page'] = intval(get_value_or_default($this->request->get, 'page', 1));
        $input['pageSize'] = intval(get_value_or_default($this->request->get, 'pageSize', 20));
        $this->load->model('customerpartner/buyers');
        $results = $this->model_customerpartner_buyers->getList($input, true);
        $num = ($input['page']-1)*$input['pageSize'];

        $buyerIds = $results['data']->pluck('buyer_id')->toArray();
        $languageBuyerIdMap = MsgCustomerExt::query()->whereIn('customer_id', $buyerIds)->pluck('language_type', 'customer_id')->toArray();
        $buyerCustomerModelMap = Customer::query()->with('buyer')->whereIn('customer_id', $buyerIds)->get()->keyBy('customer_id');

        //获取当前buyer 配置的联系人电话
        $phoneData = Buyer::query()
            ->with(['telephone_country_code:id,code'])
            ->whereIn('buyer_id', $buyerIds)
            ->get(['contacts_phone', 'buyer_id', 'contacts_country_id', 'contacts_open_status'])
            ->keyBy('buyer_id')
            ->toArray();

        foreach ($results['data'] as &$result) {
            $exitsOrder = app(BuyerToSellerRepository::class)->getLastCompleteTransactionOrderDate($input['seller_id'],$result->buyer_id);

            $result->num = ++$num;
            $result->is_home_pickup = in_array($result->customer_group_id, COLLECTION_FROM_DOMICILE) ? true : false;
            $result->money_of_transaction = $this->currency->formatCurrencyPrice($result->money_of_transaction, $this->session->data['currency']);
            $result->contacts_phone_info = null;
            //buyer开启推送给seller
            if (!customer()->isGigaOnsiteSeller() && (bool)$exitsOrder && $phoneData[$result->buyer_id]['contacts_open_status'] === BuyerType::CONTACTS_OPEN_STATUS) {
                $result->contacts_phone_info = $phoneData[$result->buyer_id];
            }

            $result->language = MsgCustomerExtLanguageType::getViewItems()[$languageBuyerIdMap[$result->buyer_id] ?? 0];
            $result->ex_vat = VATToolTipWidget::widget(['customer' => $buyerCustomerModelMap->get($result->buyer_id), 'is_show_vat' => false])->render();
        }
        $this->response->returnJson(['total' => $results['total'], 'rows' => $results['data']]);
    }

    public function getInactiveList()
    {
        trim_strings($this->request->get);
        $input = $this->request->get;
        $input['seller_id'] = $this->customer_id;
        $input['page'] = get_value_or_default($this->request->get, 'page', 1);
        $input['pageSize'] = get_value_or_default($this->request->get, 'pageSize', 20);
        $this->load->model('customerpartner/buyers');
        $results = $this->model_customerpartner_buyers->getList($input, false);
        $num = ($input['page']-1)*$input['pageSize'];

        $buyerIds = $results['data']->pluck('buyer_id')->toArray();
        $buyerCustomerModelMap = Customer::query()->with('buyer')->whereIn('customer_id', $buyerIds)->get()->keyBy('customer_id');
        foreach ($results['data'] as &$result) {
            $result->num = ++$num;
            $result->is_home_pickup = in_array($result->customer_group_id, COLLECTION_FROM_DOMICILE) ? true : false;
            $result->money_of_transaction = $this->currency->formatCurrencyPrice($result->money_of_transaction, $this->session->data['currency']);
            $result->ex_vat = VATToolTipWidget::widget(['customer' => $buyerCustomerModelMap->get($result->buyer_id), 'is_show_vat' => false])->render();
        }
        $this->response->returnJson(['total' => $results['total'], 'rows' => $results['data']]);
    }

    /**
     * 获取buyer的基本属性
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function getBuyersInfo()
    {
        /** @var ModelCustomerpartnerBuyers $modelCustomerPartnerBuyers */
        $modelCustomerPartnerBuyers = load()->model('customerpartner/buyers');
        $input = $this->request->input->all();
        $input['seller_id'] = $this->customer_id;
        $results = $modelCustomerPartnerBuyers->getList($input, $input['is_active'], true);

        /** @var \Illuminate\Support\Collection $buyers */
        $buyers = $results['data'];
        $buyersInfo = $buyers->map(function ($v) {
            return [
                'buyer_id' => $v->buyer_id,
                'nickname' => $v->nickname,
                'user_number' => $v->user_number,
            ];
        });
        return $this->jsonSuccess($buyersInfo);
    }

    /**
     * 逻辑：
     * A.如果解除关联关系
     *   1.需要删除 oc_delicacy_management 记录
     *   2.删除 buyer_group_link 记录
     * B.未解除关联关系
     *   1.如果添加分组
     */
    public function update()
    {
        $this->checkPOST();
        trim_strings($this->request->post);
        // 加载Model
        $this->load->model('customerpartner/buyers');

        if (!isset_and_not_empty($this->request->post, 'id')) {
            $response = [
                'error' => 5,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        $coop_status_seller = $this->request->post["coop_status_seller"] ?? 1;
        $update_data = array(
            "id" => $this->request->post["id"],
            "seller_control_status" => $coop_status_seller,
            "discount" => 1,
            "remark" => get_value_or_default($this->request->post, 'remark', ''),
        );
        $buyerInfo = $this->model_customerpartner_buyers->getSingle($update_data['id']);

        if (empty($buyerInfo)) {
            $response = [
                'error' => 5,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        $this->load->model('account/customerpartner/BuyerGroup');
        // 14086 库存订阅列表中的产品上下架提醒
        // 判断是否更改buyer 的状态
        $res = $this->model_customerpartner_buyers->verifyBuyerStatus($this->request->post['id'], $coop_status_seller);
        if ($res) {
            $buyer_id = $buyerInfo->buyer_id;
            $seller_id = $this->customer->getId();
            //获取buyer_id 和 seller_id 需要知道关联的产品
            $this->model_customerpartner_buyers->sendProductionInfoToBuyer($buyer_id, $seller_id, $coop_status_seller);
        }

        // 更改了状态则发站内信
        /**
         * 解除关系
         * 1.删除 delicacy_management
         * 2.移除分组
         *
         * 未解除关系
         * 1.如果有分组 则更改
         * 2.如果无分组 则删除
         */
        if ($update_data['seller_control_status'] == 0) {   //解除关系
            // 1.删除 delicacy_management
            $this->load->model('customerpartner/DelicacyManagement');
            $this->model_customerpartner_DelicacyManagement->batchRemoveByBuyer([$buyerInfo->buyer_id], $this->customer_id);

            // 2.移除分组
            $this->model_account_customerpartner_BuyerGroup->linkDeleteByBuyer($this->customer_id, $buyerInfo->buyer_id);
        } else {  // 未解除关系
            // 1.如果有分组 则更改
            if (isset_and_not_empty($this->request->post, 'buyer_group_id')) {
                // 验证分组是否存在
                if (!$this->model_account_customerpartner_BuyerGroup->checkGroupIsExist(
                    $this->customer_id,
                    $this->request->post['buyer_group_id'])
                ) {
                    $response = [
                        'error' => 5,
                        'msg' => $this->language->get('error_common'),
                    ];
                    $this->returnJson($response);
                }
                // 更改分组
                $this->model_account_customerpartner_BuyerGroup->updateGroupLinkByBuyer(
                    $this->customer_id,
                    $buyerInfo->buyer_id,
                    $this->request->post['buyer_group_id']
                );
            } else {  //2.如果无分组 则删除
                $this->model_account_customerpartner_BuyerGroup->linkDeleteByBuyer($this->customer_id, $buyerInfo->buyer_id);
            }
        }

        $this->model_customerpartner_buyers->updateBuyerInfo($update_data);
        $response = [
            'error' => 0,
            'msg' => 'Success'
        ];
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($response));
    }

    /**
     * @var int type 类型 1-> set discount; 2-> set status:enable ; 3-> set status:disable
     * @var int ids  批量操作ID 的字符串 如： 2_3_4
     * @var int discount 折扣 (只有在type=1的情况下才有)
     */
    public function batchUpdate()
    {
        $error = [];
        $customer_id = $this->customer->getId();

        $lanArr = $this->load->language('account/customerpartner/buyer_management/list');
        isset($this->request->post) && trim_strings($this->request->post);
        isset($this->request->post['ids']) && $this->request->post['ids'] = trim($this->request->post['ids'], '_');
        if (!isset($this->request->post['ids']) || empty($this->request->post['ids'])) {
            $error[] = $lanArr['error_choose_checkboxes'];
        }

        if (!isset($this->request->post['type']) || !in_array($this->request->post['type'], [1, 2, 3])) {
            $error[] = $lanArr['error_try_again'];
        }

        if ($this->request->post['type'] == 1) {
            if (!isset($this->request->post['discount']) || empty($this->request->post['discount']) || (float)$this->request->post['discount'] < 0 || (float)$this->request->post['discount'] > 1) {
                $error[] = $lanArr['error_save_discount'];
            }
        }

        if (empty($error)) {
            $this->load->model('customerpartner/buyers');
            $update_data = [
                'seller_id' => $customer_id,
                'idArr' => explode('_', $this->request->post['ids'])
            ];
            if ($this->request->post['type'] == 2) {
                $coop_status_seller = 1;
            } elseif ($this->request->post['type'] == 3) {
                $coop_status_seller = 0;
            }
            // 14086 库存订阅列表中的产品上下架提醒
            // 判断是否更改buyer 的状态
            if (isset($coop_status_seller)) {
                foreach ($update_data['idArr'] as $key => $value) {
                    $res = $this->model_customerpartner_buyers->verifyBuyerStatus($value, $coop_status_seller);
                    if ($res) {
                        $buyer_id = $res;
                        //获取buyer_id 和 seller_id 需要知道关联的产品
                        $this->model_customerpartner_buyers->sendProductionInfoToBuyer($buyer_id, $customer_id, $coop_status_seller);
                    }
                }
            }
            if ($this->request->post['type'] == 1) {
                $update_data['update'] = [
                    'discount' => $this->request->post['discount']
                ];
            } elseif ($this->request->post['type'] == 3) {
                $update_data['update'] = [
                    'seller_control_status' => 0
                ];
                // 同时需要删除delicacy_management表 & 从分组中移除
                $buyerIDArr = $this->model_customerpartner_buyers->getBuyerID($update_data['idArr']);
                $this->load->model('customerpartner/DelicacyManagement');
                if (!empty($buyerIDArr)) {
                    $this->load->model('account/customerpartner/BuyerGroup');
                    $this->model_account_customerpartner_BuyerGroup->batchDeleteLink($this->customer_id, $buyerIDArr);
                    $this->model_customerpartner_DelicacyManagement->batchRemoveByBuyer($buyerIDArr, $customer_id);
                }
            } else {
                $update_data['update'] = [
                    'seller_control_status' => 1
                ];
            }
            $this->model_customerpartner_buyers->batchUpdate($update_data);
            $responseData = [
                'code' => 1,
                'msg' => 'Success!'
            ];
        } else {
            $responseData = [
                'code' => 0,
                'msg' => $error[0]
            ];
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($responseData));
    }

    public function recovery()
    {
        $this->checkPOST();
        trim_strings($this->request->post);

        if (!isset_and_not_empty($this->request->post, 'id')) {
            $response = [
                'error' => 5,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        $this->load->model('customerpartner/buyers');
        $this->model_customerpartner_buyers->recovery($this->request->post['id'],$this->customer_id);
        $response = [
            'error' => 0,
            'msg' => 'Success'
        ];
        $this->response->returnJson($response);
    }

    public function batchRecovery()
    {
        $this->checkPOST();
        trim_strings($this->request->post);

        if (!isset_and_not_empty($this->request->post, 'ids') || !is_array($this->request->post['ids'])) {
            $response = [
                'error' => 5,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }

        $this->load->model('customerpartner/buyers');
        $this->model_customerpartner_buyers->batchRecovery($this->request->post['ids'],$this->customer_id);
        $response = [
            'error' => 0,
            'msg' => 'Success'
        ];
        $this->response->returnJson($response);
    }

    /**
     * 逻辑：
     * 1.如果移除分组
     *      从 oc_customerpartner_buyer_group_link 中删除
     * 2.如果更改分组
     *    a.原来无分组
     *          如果该分组已和 产品分组 关联，则需要删除之前参与的精细化管理
     *          如果 未关联，则加入分组
     *    b.原来有分组
     *          更改分组即可
     *
     * @var int ids  批量操作ID 的字符串 如： 2_3_4
     */
    public function batchSetGroup()
    {
        $this->checkPOST();
        trim_strings($this->request->post);

        $this->load->model('customerpartner/buyers');
        isset($this->request->post['ids']) && $this->request->post['ids'] = trim($this->request->post['ids'], '_');
        if (!isset($this->request->post['ids']) || empty($this->request->post['ids'])) {
            $response = [
                'error' => 5,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }
        $buyerIDArr = $this->model_customerpartner_buyers->getBuyerID(explode('_', $this->request->post['ids']));
        $buyer_group_id = get_value_or_default($this->request->post, 'buyer_group_id', 0);

        $this->load->model('account/customerpartner/BuyerGroup');

        if ($buyer_group_id == 0) {
            if (!empty($buyerIDArr)) {
                $this->model_account_customerpartner_BuyerGroup->batchDeleteLink($this->customer_id, $buyerIDArr);
            }
        } else {
            $is_delete_dm = $this->model_account_customerpartner_BuyerGroup->checkIsDelicacyManagementGroupByBGID($this->customer_id, $buyer_group_id);
            foreach ($buyerIDArr as $buyerID) {
                $this->model_account_customerpartner_BuyerGroup->updateGroupLinkByBuyer($this->customer_id, $buyerID, $buyer_group_id, $is_delete_dm);
            }
        }
        $response = [
            'error' => 0,
            'msg' => 'Success'
        ];
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($response));

    }

    private function get_status_show($data)
    {
        $this->load->language('common/user_portrait');
        switch ($data) {
            case 1:
                $tmp = $this->language->get('high');
                break;
            case 2:
                $tmp =  $this->language->get('moderate');
                break;
            case 3:
                $tmp =  $this->language->get('low');
                break;
            default:
                $tmp =  $this->language->get('NA');
                break;
        }
        return $tmp;
    }

    private function formatMonthSalesCount($quantity)
    {
        switch (true) {
            case $quantity > 10000:
                $formantQuantity = '10000+';
                break;
            case  $quantity > 1000:
                $formantQuantity = '1000+';
                break;
            case  $quantity > 100:
                $formantQuantity = '100+';
                break;
            case  $quantity > 0:
                $formantQuantity = 'Less than 100';
                break;
            default:
                $formantQuantity = '0';
        }

        return $formantQuantity;
    }

    public function download()
    {
        $this->load->language('account/customerpartner/buyer_management/list');

        $filter_data = [];
        trim_strings($this->request->get);
        isset_and_not_empty($this->request->get, 'filter_name') && $filter_data['filter_name'] = $this->request->get['filter_name'];
        isset_and_not_empty($this->request->get, 'filter_buyer_group_id') && $filter_data['filter_buyer_group_id'] = $this->request->get['filter_buyer_group_id'];
        isset_and_not_empty($this->request->get, 'filter_date_from') && $filter_data['filter_date_from'] = $this->request->get['filter_date_from'];
        isset_and_not_empty($this->request->get, 'filter_date_to') && $filter_data['filter_date_to'] = $this->request->get['filter_date_to'];
        isset_and_not_empty($this->request->get, 'filter_language') && $filter_data['filter_language'] = $this->request->get['filter_language'];
        $filter_data['seller_id'] = $this->customer_id;

        $this->load->model('customerpartner/buyers');
        $results = $this->model_customerpartner_buyers->getList($filter_data,true,true);

        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis", time()), 'Ymd');
        //12591 end
        $fileName = 'Buyers-' . $time . '.csv';
        $header = [
            'Name',
            'Buyer Number',
            $this->language->get('text_download_buyer_type'),
            $this->language->get('text_download_comprehensive_mark'),
            $this->language->get('text_download_product_quantity'),
            $this->language->get('text_download_transaction'),
            $this->language->get('text_download_complex_complete'),
            $this->language->get('text_download_rma_rate'),
            $this->language->get('text_download_main_category'),
            $this->language->get('column_buyer_group'),
            str_replace('<br>', '', $this->language->get('column_number_of_transaction')),
            str_replace('<br>', '', $this->language->get('column_money_of_transaction')),
            str_replace('<br>', '', $this->language->get('column_column_last_transaction_time')),
            'Remark',
            'Status',
        ];

        $this->load->model('common/user_portrait');

        $content = array();
        foreach ($results['data'] as $result) {
            if (strtotime($result->first_order_date) < strtotime('2010-1-1')) {
                $first_order_month = 'N/A';
            } else {
                $diff = time() - strtotime($result->first_order_date);
                if ($diff < 30 * 24 * 3600) {
                    $first_order_month = 'less than one month';
                } else {   //>=30天
                    $days = $diff / (3600 * 24);
                    $month = (int)($days / 30);
                    if ($days % 30 >= 15) {
                        $month++;
                    }
                    $first_order_month = $month .' month(s) ago';
                }
            }

            $categoryNames = [];
            if (!empty($result->main_category_id)) {
                $categoryNames = $this->model_common_user_portrait->getPortraitCategoryName($result->main_category_id);
                $categoryNames = array_map(function ($item) {
                    return str_replace('&amp;', '&', $item);
                }, $categoryNames);
            }
            //评分
            $this->load->model('customerpartner/seller_center/index');
            $task_info=$this->model_customerpartner_seller_center_index->getBuyerNowScoreTaskNumberEffective($result->buyer_id);

            $content[] = array(
                html_entity_decode($result->nickname),
                "\t" . $result->user_number . "\t",
                in_array($result->customer_group_id,COLLECTION_FROM_DOMICILE) ? $this->language->get('tip_home_pickup_logo') : $this->language->get('tip_drop_shipping_logo'),
                isset($task_info['performance_score']) ? number_format(round($task_info['performance_score'],2),2) : '',
                (time() - strtotime($result->registration_date) < 30 * 24 * 3600) ? 'N/A' : $this->formatMonthSalesCount($result->monthly_sales_count),
                $first_order_month,
                $this->get_status_show($result->complex_complete_rate),
                $this->get_status_show($result->return_rate),
                join(" > ", $categoryNames),
                html_entity_decode($result->buyer_group_name) . ($result->is_default ? '(Default)' : ''),
                $result->number_of_transaction,
                $result->money_of_transaction,
                $result->last_transaction_time == '0000-01-01 00:00:00' ? 'N/A' : $result->last_transaction_time,
                "\t" . html_entity_decode($result->remark) . "\t",
                $result->status ? 'Active' : 'Inactive',
            );
        }

        //12591 B2B记录各国别用户的操作时间
        outputCsv($fileName, $header, $content, $this->session);
        //12591 end
    }

    private function checkPOST()
    {
        if (!request()->isMethod('POST')) {
            $response = [
                'error' => 5,
                'msg' => $this->language->get('error_common'),
            ];
            $this->returnJson($response);
        }
    }

    private function returnJson($response)
    {
        $this->response->returnJson($response);
    }
}
