<?php

use App\Components\Storage\StorageCloud;
use App\Models\Customer\Customer;
use App\Models\Rebate\RebateAgreementTemplateItem;
use App\Repositories\Product\ProductPriceRepository;
use App\Widgets\VATToolTipWidget;

/**
 * Class ControllerAccountProductQuotesRebatesContract
 *
 * @property ModelCommonProduct $model_common_product
 * @property ModelMessageMessage $model_message_message
 * @property ModelAccountCustomerpartnerRebates $model_account_customerpartner_rebates
 * @property ModelAccountNotification $model_account_notification
 * @property ModelAccountProductQuotesRebatesAgreement $model_account_product_quotes_rebates_agreement
 * @property ModelAccountProductQuotesRebatesContract $model_account_product_quotes_rebates_contract
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountProductQuotesRebatesContract extends Controller
{
    private $customer_id;
    private $country_id;
    private $isPartner;
    /**
     * @var ModelAccountProductQuotesRebatesContract $model
     */
    private $model;

    public function __construct($registry)
    {
        parent::__construct($registry);
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/account', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $this->customer_id = $this->customer->getId();
        $this->isPartner = $this->customer->isPartner();
        $this->country_id = $this->customer->getCountryId();
        $this->load->model('common/product');
        $this->load->model('account/product_quotes/rebates_contract');
        $this->model = $this->model_account_product_quotes_rebates_contract;
        $this->load->language('account/product_quotes/rebates_contract');
        $this->load->language('common/cwf');
    }
    // 列表
    public function index()
    {
        if ($this->isPartner) {
            $this->getSellerTab();
        } else {
            $this->getBuyerTab();
        }
    }


    /**
     * @throws Exception
     */
    public function getBuyerTab()
    {
        trim_strings($this->request->get);
        if (isset($this->request->get['filter_id'])) {
            $filter_id = $this->request->get['filter_id'];
        } else {
            $filter_id = null;
        }

        if (isset($this->request->get['filter_store_name'])) {
            $filter_store_name = $this->request->get['filter_store_name'];
        } else {
            $filter_store_name = null;
        }

        if (isset($this->request->get['filter_status'])) {
            $filter_status = $this->request->get['filter_status'];
        } else {
            $filter_status = null;
        }

        if (isset($this->request->get['filter_result_status'])) {
            $filter_result_status = $this->request->get['filter_result_status'];
        } else {
            $filter_result_status = null;
        }

        if (isset($this->request->get['filter_sku_mpn'])) {
            $filter_sku_mpn = $this->request->get['filter_sku_mpn'];
        } else {
            $filter_sku_mpn = null;
        }

        if (isset($this->request->get['filter_date_from'])) {
            $filter_date_from = $this->request->get['filter_date_from'];
        } else {
            $filter_date_from = null;
        }
        if (isset($this->request->get['filter_date_to'])) {
            $filter_date_to = $this->request->get['filter_date_to'];
        } else {
            $filter_date_to = null;
        }

        if (isset($this->request->get['sort'])) {
            $sort_key = $this->request->get['sort'];
            switch ($sort_key) {
                case 'item_code':
                    $sort_value = 'rat.items';
                    break;
                case 'store_name':
                    $sort_value = 'ctc.screenname';
                    break;
                case 'status':
                    $sort_value = 'ra.status';
                    break;
                case 'rebate_result':
                    $sort_value = 'ra.rebate_result';
                    break;
                case 'modify_date':
                    $sort_value = 'ra.`update_time`';
                    break;
                default:
                    $sort_value = 'ra.`update_time`';
            }
        } else {
            $sort_key   = 'modify_date';
            $sort_value = 'ra.`update_time`';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'DESC';
        }

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }
        $limit       = get_value_or_default($this->request->request, 'page_limit', 15);
        $filter_data = array(
            'buyer_id'             => $this->customer->getId(),
            'contract_id'          => $filter_id,
            'filter_store_name'    => $filter_store_name,
            'filter_sku_mpn'       => $filter_sku_mpn,
            'filter_status'        => $filter_status,
            'filter_result_status' => $filter_result_status,
            'filter_date_from'     => $filter_date_from,
            'filter_date_to'       => $filter_date_to,
            'sort'                 => $sort_value,
            'order'                => $order,
            'start'                => ($page - 1) * $limit,
            'limit'                => $limit,
        );

        $url = $this->url->link('account/product_quotes/rebates_contract', '', true);

        if (isset($this->request->get['filter_id'])) {
            $url .= '&filter_id=' . $this->request->get['filter_id'];
        }

        if (isset($this->request->get['filter_store_name'])) {
            $url .= '&filter_store_name=' . urlencode(html_entity_decode($this->request->get['filter_store_name'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }

        if (isset($this->request->get['filter_result_status'])) {
            $url .= '&filter_result_status=' . $this->request->get['filter_result_status'];
        }

        if (isset($this->request->get['filter_sku_mpn'])) {
            $url .= '&filter_sku_mpn=' . $this->request->get['filter_sku_mpn'];
        }

        if (isset($this->request->get['filter_date_from'])) {
            $url .= '&filter_date_from=' . urlencode(html_entity_decode(changeOutPutByZone($this->request->get['filter_date_from'], $this->session), ENT_QUOTES, 'UTF-8'));
        }
        if (isset($this->request->get['filter_date_to'])) {
            $url .= '&filter_date_to=' . urlencode(html_entity_decode(changeOutPutByZone($this->request->get['filter_date_to'], $this->session), ENT_QUOTES, 'UTF-8'));
        }

        $tableUrl = $url;   //用于table的header部分切换正逆序
        if ($order == 'ASC') {
            $url      .= '&order=ASC';
            $tableUrl .= '&order=DESC';
        } else {
            $url      .= '&order=DESC';
            $tableUrl .= '&order=ASC';
        }

        //表格排序url
        $data['sort_item_code']  = $tableUrl . '&sort=item_code';
        $data['sort_store_name']  = $tableUrl . '&sort=store_name';
        $data['sort_status']      = $tableUrl . '&sort=status';
        $data['sort_rebate_result']= $tableUrl . '&sort=rebate_result';
        $data['sort_modify_date'] = $tableUrl . '&sort=modify_date';

        $url .= '&sort=' . $sort_key;

        $this->language->load('account/product_quotes/rebates_contract');
        $this->load->model('account/product_quotes/rebates_agreement');
        $this->load->model('tool/image');

        $results     = $this->model_account_product_quotes_rebates_agreement->getRebatesContractDisplay($filter_data);
        $resultTotal = $this->model_account_product_quotes_rebates_agreement->getRebatesContractTotal($filter_data);

        $data['rebates_contracts'] = array();
        foreach ($results as $result) {
            $result = $this->model_account_product_quotes_rebates_agreement->formatRebatesContractList($result);

            $action = array(
                'text' => $this->language->get('btn_view'),
                'href' => $this->url->link('account/product_quotes/rebates_contract/rebatesAgreementList', '&agreement_id=' . $result['id'] . '&act=view')
            );

            $actionCancel = array(
                'text' => $this->language->get('btn_cancel'),
                'href' => $this->url->link('account/product_quotes/rebates_contract/cancel')
            );
            $actionTerminate = [
                'text' => $this->language->get('btn_terminate'),
                'href' => $this->url->link('account/product_quotes/rebates_contract/terminate')
            ];
            $actionEdit = [
                'text' => $this->language->get('btn_edit'),
                'href' => $this->url->link('account/product_quotes/rebates_contract/rebatesAgreementList', 'agreement_id=' . $result['id'])
            ];

            $result['agreement_status_name'] = $this->language->get('text_status_' . $result['status']);
            $result['rebate_result_name']    = $this->language->get('text_result_status_' . $result['rebate_result']);

            if ($result['image'] && StorageCloud::image()->fileExists($result['image'])) {
                $image = $this->model_tool_image->resize($result['image'], 30, 30);
            } else {
                $image = $this->model_tool_image->resize('placeholder.png', 30, 30);
            }

            $data['rebates_contracts'][] = array(
                'id'                    => $result['id'],
                'agreement_code'        => $result['agreement_code'],
                'items_sku'             => $result['items_sku'],
                'items_more'             => $result['items_more'],
                'product_link'          => isset($result['product_id']) ? $this->url->link('product/product', ['product_id' => $result['product_id']]) : 'javascript:void(0)',
                'store_name'            => $result['screenname'],
                'store_link'            => $this->url->link('customerpartner/profile', 'id=' . $result['seller_id'], true),
                'image'                 => $image,
                'day'                   => $result['day'],
                'day_left'              => $result['day_left'],
                'purchased_qty'         => $result['purchased_qty'],
                'qty'                   => $result['qty'],
                'status'                => $result['status'],
                'rebate_result'         => $result['rebate_result'],
                'agreement_status_name' => $result['agreement_status_name'],
                'rebate_result_name'    => $result['rebate_result_name'],
                'update_time'           => $result['update_time'],
                'update_day'            => $result['update_day'],
                'update_hour'           => $result['update_hour'],
                'action'                => $action,
                'action_cancel'         => $actionCancel,
                'action_terminate'      => $actionTerminate,
                'action_edit'           => $actionEdit
            );
        }

        //统计区域。列表查询会更新状态，所以统计区域要放到列表查询的下面
        $filter_data['status_condition'] = 'rejected';
        $data['count_rejected']          = $this->model_account_product_quotes_rebates_agreement->getCountMap($filter_data);
        $filter_data['status_condition'] = 'active';
        $data['count_active']            = $this->model_account_product_quotes_rebates_agreement->getCountMap($filter_data);
        $filter_data['status_condition'] = 'due_soon';
        $data['count_due_soon']          = $this->model_account_product_quotes_rebates_agreement->getCountMap($filter_data);
        $filter_data['status_condition'] = 'fulfilled';
        $data['count_fulfilled']         = $this->model_account_product_quotes_rebates_agreement->getCountMap($filter_data);
        $filter_data['status_condition'] = 'processing';
        $data['count_processing']        = $this->model_account_product_quotes_rebates_agreement->getCountMap($filter_data);
        $filter_data['status_condition'] = 'rebate_paid';
        $data['count_rebate_paid']       = $this->model_account_product_quotes_rebates_agreement->getCountMap($filter_data);
        $filter_data['status_condition'] = 'rebate_declined';
        $data['count_rebate_declined']   = $this->model_account_product_quotes_rebates_agreement->getCountMap($filter_data);
        $filter_data['status_condition'] = 'failed';
        $data['count_failed']            = $this->model_account_product_quotes_rebates_agreement->getCountMap($filter_data);

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = session('success');
            $this->session->remove('success');
        } else {
            $data['success'] = '';
        }

        //agreement status
        $data['quote_status']  = array(
            0 => $this->language->get('text_status_0'),//Canceled
            1 => $this->language->get('text_status_1'),//Pending
            2 => $this->language->get('text_status_2'),//Rejected
            3 => $this->language->get('text_status_3'),//Approved
            4 => $this->language->get('text_status_4')//Time out
        );
        $data['result_status'] = [
            1 => $this->language->get('text_result_status_1'),//Active
            5 => $this->language->get('text_result_status_5'),//Fulfilled
            4 => $this->language->get('text_result_status_4'),//Failed
            3 => $this->language->get('text_result_status_3'),//Terminated
            2 => $this->language->get('text_result_status_2'),//Due Soon
            6 => $this->language->get('text_result_status_6'),//Processing
            7 => $this->language->get('text_result_status_7'),//Rebate Paid
            8 => $this->language->get('text_result_status_8'),//Rebate Declined
            -1 => $this->language->get('text_result_status_h'),//Historical Data
        ];
        if ($filter_status == 2) {
            $data['status_condition'] = 'rejected';
        }
        if ($filter_result_status == 1) {
            $data['status_condition'] = 'active';
        }
        if ($filter_result_status == 2) {
            $data['status_condition'] = 'due_soon';
        }
        if ($filter_result_status == 5) {
            $data['status_condition'] = 'fulfilled';
        }
        if ($filter_result_status == 6) {
            $data['status_condition'] = 'processing';
        }
        if ($filter_result_status == 7) {
            $data['status_condition'] = 'rebate_paid';
        }
        if ($filter_result_status == 8) {
            $data['status_condition'] = 'rebate_declined';
        }
        if ($filter_result_status == 4) {
            $data['status_condition'] = 'failed';
        }

        $data['action']                = $this->url->link('account/product_quotes/rebates_contract', '', true);
        $data['action_download_buyer'] = $this->url->link('account/product_quotes/rebates_contract/download_buyer', '', true);

        $data['total_pages']    = ceil($resultTotal / $limit);
        $data['page_num']       = $page;
        $data['total']          = $resultTotal;
        $data['page_limit']     = $limit;
        $data['pagination_url'] = $url;

        $data['results'] = sprintf(
            $this->language->get('text_pagination'),
            ($resultTotal) ? (($page - 1) * $limit) + 1 : 0,
            ((($page - 1) * $limit) > ($resultTotal - $limit)) ? $resultTotal : ((($page - 1) * $limit) + $limit),
            $resultTotal,
            ceil($resultTotal / $limit)
        );

        $data['sort']                 = $sort_key;
        $data['order']                = $order;
        $data['contract_id']          = $filter_id;
        $data['filter_store_name']    = $filter_store_name;
        $data['filter_sku_mpn']       = $filter_sku_mpn;
        $data['filter_status']        = $filter_status;
        $data['filter_result_status'] = $filter_result_status;
        $data['filter_date_from']     = $filter_date_from;
        $data['filter_date_to']       = $filter_date_to;
        $data['continue']             = $this->url->link('common/home');

        $this->response->setOutput($this->load->view('account/product_quotes/tab_rebates', $data));
    }

    public function getSellerTab()
    {

        $this->language->load('account/product_quotes/rebates_contract');
        $this->load->model('account/product_quotes/rebates_agreement');
        $this->load->model('tool/image');

        trim_strings($this->request->get);
        if (isset($this->request->get['filter_id'])) {
            $filter_id = $this->request->get['filter_id'];
        } else {
            $filter_id = null;
        }

        if (isset($this->request->get['filter_buyer_name'])) {
            $filter_buyer_name = $this->request->get['filter_buyer_name'];
        } else {
            $filter_buyer_name = null;
        }


        if (isset($this->request->get['filter_status'])) {
            $filter_status = $this->request->get['filter_status'];
        } else {
            $filter_status = null;
        }

        if (isset($this->request->get['filter_result_status'])) {
            $filter_result_status = $this->request->get['filter_result_status'];
        } else {
            $filter_result_status = null;
        }

        if (isset($this->request->get['filter_sku_mpn'])) {
            $filter_sku_mpn = $this->request->get['filter_sku_mpn'];
        } else {
            $filter_sku_mpn = null;
        }

        if (isset($this->request->get['filter_date_from'])) {
            $filter_date_from = $this->request->get['filter_date_from'];
        } else {
            $filter_date_from = null;
        }
        if (isset($this->request->get['filter_date_to'])) {
            $filter_date_to = $this->request->get['filter_date_to'];
        } else {
            $filter_date_to = null;
        }

        if (isset($this->request->get['sort'])) {
            if ('nickname' == $this->request->get['sort']) {
                $sort = 'c.' . $this->request->get['sort'];
            } elseif ('item_code' == $this->request->get['sort']) {
                $sort = 'rat.items';
            } else {
                $sort = 'ra.' . $this->request->get['sort'];
            }
        } else {
            $sort = 'ra.`update_time`';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        }else {
            $order = 'DESC';
        }

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $limit = get_value_or_default($this->request->request, 'page_limit', 15);

        $filter_data = array(
            'seller_id' => $this->customer->getId(),
            'contract_id' => $filter_id,
            'filter_buyer_name' => $filter_buyer_name,
            'filter_sku_mpn' => $filter_sku_mpn,
            'filter_status' => $filter_status,
            'filter_result_status' => $filter_result_status,
            'filter_date_from' => $filter_date_from,
            'filter_date_to' => $filter_date_to,
            'sort' => $sort,
            'order' => $order,
            'start' => ($page - 1) * $limit,
            'limit' => $limit,
        );

        $url = $this->url->link('account/product_quotes/rebates_contract', '', true);

        if (isset($this->request->get['filter_id'])) {
            $url .= '&filter_id=' . $this->request->get['filter_id'];
        }

        if (isset($this->request->get['filter_buyer_name'])) {
            $url .= '&filter_buyer_name=' . urlencode(html_entity_decode($this->request->get['filter_buyer_name'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }

        if (isset($this->request->get['filter_result_status'])) {
            $url .= '&filter_result_status=' . $this->request->get['filter_result_status'];
        }

        if (isset($this->request->get['filter_sku_mpn'])) {
            $url .= '&filter_sku_mpn=' . $this->request->get['filter_sku_mpn'];
        }

        if (isset($this->request->get['filter_date_from'])) {
            $url .= '&filter_date_from=' . urlencode(html_entity_decode(changeOutPutByZone($this->request->get['filter_date_from'], $this->session), ENT_QUOTES, 'UTF-8'));
        }
        if (isset($this->request->get['filter_date_to'])) {
            $url .= '&filter_date_to=' . urlencode(html_entity_decode(changeOutPutByZone($this->request->get['filter_date_to'], $this->session), ENT_QUOTES, 'UTF-8'));
        }
        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
            if (isset($this->request->get['order'])) {
                $url .= '&order=' . $this->request->get['order'];
            }
        }



        $results = $this->model_account_product_quotes_rebates_agreement->getRebatesContractDisplay($filter_data);
        $resultTotal = $this->model_account_product_quotes_rebates_agreement->getRebatesContractTotal($filter_data);


        $data['order_new'] = 'DESC' == strtoupper($order) ? 'ASC':'DESC';
        $data['order']     = 'ASC'  == strtoupper($order) ? 'ASC':'DESC';
        $data['sort'] = isset($this->request->get['sort'])?$this->request->get['sort']:'update_time';
        $data['rebates_contracts'] = array();

        $buyerIds = array_column($results, 'buyer_id');
        $buyerCustomerModelMap = Customer::query()->with('buyer')->whereIn('customer_id', $buyerIds)->get()->keyBy('customer_id');
        foreach ($results as $result) {
            $result = $this->model_account_product_quotes_rebates_agreement->formatRebatesContractList($result);

            $action = array(
                'text' => $this->language->get('btn_view'),
                'href' => $this->url->link('account/product_quotes/rebates_contract/rebatesAgreementList', '&agreement_id=' . $result['id'] . '&act=view')
            );

            $result['status_text'] = $this->language->get('text_status_' . $result['status']);

            if ($result['image'] && StorageCloud::image()->fileExists($result['image'])) {
                $image = $this->model_tool_image->resize($result['image'], 30, 30);
            } else {
                $image = $this->model_tool_image->resize('placeholder.png', 30, 30);
            }

            $is_home_pickup = in_array($result['customer_group_id'], COLLECTION_FROM_DOMICILE);

            $data['rebates_contracts'][] = array(
                'id' => $result['id'],
                'agreement_code' => $result['agreement_code'],
                'product_id' => $result['product_id'],
                'product_url'   => $this->url->link('product/product', '&product_id=' . $result['product_id']),
                'items_first' => $result['items_first'],
                'items_more' => $result['items_more'],
                'buyer_id'=>$result['buyer_id'],
                'nickname' => $result['nickname'] ,
                'user_number' => $result['user_number'] ,
                'is_home_pickup' => $is_home_pickup,
                'image' => $image,
                'day' => $result['day'],
                'day_left' => $result['day_left'],
                'purchased_qty' => $result['purchased_qty'],
                'qty' => $result['qty'],
                'status' => $result['status'],
                'rebate_result' => $result['rebate_result'],
                'status_text' => $result['status_text'],
                'update_time' => $result['update_time'],
                'update_day'=>$result['update_day'],
                'update_hour'=>$result['update_hour'],
                'action' => $action,
                'ex_vat' => VATToolTipWidget::widget(['customer' => $buyerCustomerModelMap->get($result['buyer_id']), 'is_show_vat' => true])->render(),
            );
        }


        //统计区域。列表查询会更新状态，所以统计区域要放到列表查询的下面
        $filter_data['status_condition'] = 'pending';
        $data['count_pending']           = $this->model_account_product_quotes_rebates_agreement->getCountMap($filter_data);
        $filter_data['status_condition'] = 'active';
        $data['count_active']            = $this->model_account_product_quotes_rebates_agreement->getCountMap($filter_data);
        $filter_data['status_condition'] = 'due_soon';
        $data['count_due_soon']          = $this->model_account_product_quotes_rebates_agreement->getCountMap($filter_data);
        $filter_data['status_condition'] = 'fulfilled';
        $data['count_fulfilled']         = $this->model_account_product_quotes_rebates_agreement->getCountMap($filter_data);
        $filter_data['status_condition'] = 'processing';
        $data['count_processing']        = $this->model_account_product_quotes_rebates_agreement->getCountMap($filter_data);
        $filter_data['status_condition'] = 'rebate_paid';
        $data['count_rebate_paid']       = $this->model_account_product_quotes_rebates_agreement->getCountMap($filter_data);
        $filter_data['status_condition'] = 'failed';
        $data['count_failed']            = $this->model_account_product_quotes_rebates_agreement->getCountMap($filter_data);


        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = session('success');
            $this->session->remove('success');
        } else {
            $data['success'] = '';
        }
        //agreement status
        $data['quote_status'] = array(
            0 => $this->language->get('text_status_0'),//Canceled
            1 => $this->language->get('text_status_1'),//Pending
            2 => $this->language->get('text_status_2'),//Rejected
            3 => $this->language->get('text_status_3'),//Approved
            4 => $this->language->get('text_status_4')//Time out
        );
        $data['result_status']=[
            1=>$this->language->get('text_result_status_1'),//Active
            5=>$this->language->get('text_result_status_5'),//Fulfilled
            4=>$this->language->get('text_result_status_4'),//Failed
            3=>$this->language->get('text_result_status_3'),//Terminated
            2=>$this->language->get('text_result_status_2'),//Due Soon
            6=>$this->language->get('text_result_status_6'),//Processing
            7=>$this->language->get('text_result_status_7'),//Rebate Paid
            8=>$this->language->get('text_result_status_8'),//Rebate Declined
            -1 => $this->language->get('text_result_status_h'),//Historical Data
        ];
        if($filter_status == 1){
            $data['status_condition'] = 'pending';
        }
        if($filter_result_status == 1){
            $data['status_condition'] = 'active';
        }
        if($filter_result_status == 2){
            $data['status_condition'] = 'due_soon';
        }
        if($filter_result_status == 5){
            $data['status_condition'] = 'fulfilled';
        }
        if($filter_result_status == 6){
            $data['status_condition'] = 'processing';
        }
        if($filter_result_status == 7){
            $data['status_condition'] = 'rebate_paid';
        }
        if($filter_result_status == 4){
            $data['status_condition'] = 'failed';
        }

        $data['action'] = $this->url->link('account/product_quotes/rebates_contract', '', true);
        $data['action_download'] = $this->url->link('account/product_quotes/rebates_contract/download', '', true);

        $data['total_pages'] = ceil($resultTotal / $limit);
        $data['page_num'] = $page;
        $data['total'] = $resultTotal;
        $data['page_limit'] = $limit;
        $data['pagination_url'] = $url;

        $data['results'] = sprintf(
            $this->language->get('text_pagination'),
            ($resultTotal) ? (($page - 1) * $limit) + 1 : 0,
            ((($page - 1) * $limit) > ($resultTotal - $limit)) ? $resultTotal : ((($page - 1) * $limit) + $limit),
            $resultTotal,
            ceil($resultTotal / $limit)
        );

        $data['contract_id'] = $filter_id;
        $data['filter_buyer_name'] = $filter_buyer_name;
        $data['filter_sku_mpn'] = $filter_sku_mpn;
        $data['filter_status'] = $filter_status;
        $data['filter_result_status'] = $filter_result_status;
        $data['filter_date_from'] = $filter_date_from;
        $data['filter_date_to'] = $filter_date_to;

        $this->response->setOutput($this->load->view('account/customerpartner/tab_rebates_seller', $data));
    }


    /**
     * seller 下载功能，筛选条件的代码参考getSellerTab()方法
     * @throws Exception
     */
    public function download()
    {
        $this->language->load('account/product_quotes/rebates_contract');
        $this->load->model('account/product_quotes/rebates_agreement');
        $this->load->model('tool/image');

        trim_strings($this->request->get);
        if (isset($this->request->get['filter_id'])) {
            $filter_id = $this->request->get['filter_id'];
        } else {
            $filter_id = null;
        }

        if (isset($this->request->get['filter_buyer_name'])) {
            $filter_buyer_name = $this->request->get['filter_buyer_name'];
        } else {
            $filter_buyer_name = null;
        }

        if (isset($this->request->get['filter_status'])) {
            $filter_status = $this->request->get['filter_status'];
        } else {
            $filter_status = null;
        }

        if (isset($this->request->get['filter_result_status'])) {
            $filter_result_status = $this->request->get['filter_result_status'];
        } else {
            $filter_result_status = null;
        }

        if (isset($this->request->get['filter_sku_mpn'])) {
            $filter_sku_mpn = $this->request->get['filter_sku_mpn'];
        } else {
            $filter_sku_mpn = null;
        }

        if (isset($this->request->get['filter_date_from'])) {
            $filter_date_from = $this->request->get['filter_date_from'];
        } else {
            $filter_date_from = null;
        }
        if (isset($this->request->get['filter_date_to'])) {
            $filter_date_to = $this->request->get['filter_date_to'];
        } else {
            $filter_date_to = null;
        }

        if (isset($this->request->get['sort'])) {
            if ('nickname' == $this->request->get['sort']) {
                $sort = 'c.' . $this->request->get['sort'];
            } else {
                $sort = 'rc.' . $this->request->get['sort'];
            }
        } else {
            $sort = 'rc.`update_time`';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'DESC';
        }



        $filter_data = array(
            'seller_id'            => $this->customer->getId(),
            'contract_id'          => $filter_id,
            'filter_buyer_name'    => $filter_buyer_name,
            'filter_sku_mpn'       => $filter_sku_mpn,
            'filter_status'        => $filter_status,
            'filter_result_status' => $filter_result_status,
            'filter_date_from'     => $filter_date_from,
            'filter_date_to'       => $filter_date_to,
            'sort'                 => $sort,
            'order'                => $order,
        );



        $results = $this->model_account_product_quotes_rebates_agreement->getRebatesContractDisplay($filter_data);


        $time     = date('Ymd');
        $fileName = 'Rebate Bid List ' . $time . '.csv';
        $columns  = [
            'Agreement ID'                => 'agreement_code',
            'Item Code (MPN)'             => 'items_sku_mpn_str',
            'Days'                        => 'day',
            'Time of Effect'              => 'effect_time',
            'Time of Failure'             => 'expire_time',
            'Min. Total Selling Quantity' => 'qty',
            'Purchased QTY'               => 'purchased_qty',
            'Remaining QTY'               => 'remaining_qty',
            'Agreement Status'            => 'agreement_status_name',
            'Rebate Result'               => 'result_status_name',
            'Buyer Name'                  => 'buyer_name',
            'Last Modified'               => 'update_time',
        ];
        $temp     = [];

        foreach ($results as $key => $result) {
            $result = $this->model_account_product_quotes_rebates_agreement->formatRebatesContractDownload($result);

            $result['agreement_status_name'] = $this->language->get('text_status_' . $result['status']);
            $result['result_status_name']    = $this->language->get('text_result_status_' . $result['rebate_result']);

            $temp[] = [
                'agreement_code'        => "\t" . $result['agreement_code'],
                'items_sku_mpn_str'     => $result['items_sku_mpn_str'],
                'day'                   => $result['day'],
                'effect_time'           => $result['effect_time'],
                'expire_time'           => $result['expire_time'],
                'qty'                   => $result['qty'],
                'purchased_qty'         => $result['purchased_qty'],
                'remaining_qty'         => $result['remaining_qty'],
                'agreement_status_name' => $result['agreement_status_name'],
                'result_status_name'    => $result['result_status_name'],
                'buyer_name'            => $result['buyer_name'],
                'update_time'           => $result['update_time'],
            ];
        }

        outputCsv($fileName, array_keys($columns), $temp, $this->session);
    }


    /**
     * buyer 下载功能，筛选条件的代码参考getSellerTab()方法
     */
    public function download_buyer()
    {
        $this->language->load('account/product_quotes/rebates_contract');
        $this->load->model('account/product_quotes/rebates_agreement');
        $this->load->model('tool/image');

        trim_strings($this->request->get);
        if (isset($this->request->get['filter_id'])) {
            $filter_id = $this->request->get['filter_id'];
        } else {
            $filter_id = null;
        }

        if (isset($this->request->get['filter_buyer_name'])) {
            $filter_buyer_name = $this->request->get['filter_buyer_name'];
        } else {
            $filter_buyer_name = null;
        }

        if (isset($this->request->get['filter_status'])) {
            $filter_status = $this->request->get['filter_status'];
        } else {
            $filter_status = null;
        }

        if (isset($this->request->get['filter_result_status'])) {
            $filter_result_status = $this->request->get['filter_result_status'];
        } else {
            $filter_result_status = null;
        }

        if (isset($this->request->get['filter_sku_mpn'])) {
            $filter_sku_mpn = $this->request->get['filter_sku_mpn'];
        } else {
            $filter_sku_mpn = null;
        }

        if (isset($this->request->get['filter_date_from'])) {
            $filter_date_from = $this->request->get['filter_date_from'];
        } else {
            $filter_date_from = null;
        }
        if (isset($this->request->get['filter_date_to'])) {
            $filter_date_to = $this->request->get['filter_date_to'];
        } else {
            $filter_date_to = null;
        }

        if (isset($this->request->get['sort'])) {
            if ('nickname' == $this->request->get['sort']) {
                $sort = 'c.' . $this->request->get['sort'];
            } else {
                $sort = 'rc.' . $this->request->get['sort'];
            }
        } else {
            $sort = 'rc.`update_time`';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'DESC';
        }



        $filter_data = array(
            'buyer_id'             => $this->customer->getId(),
            'contract_id'          => $filter_id,
            'filter_buyer_name'    => $filter_buyer_name,
            'filter_sku_mpn'       => $filter_sku_mpn,
            'filter_status'        => $filter_status,
            'filter_result_status' => $filter_result_status,
            'filter_date_from'     => $filter_date_from,
            'filter_date_to'       => $filter_date_to,
            'sort'                 => $sort,
            'order'                => $order,
        );



        $results = $this->model_account_product_quotes_rebates_agreement->getRebatesContractDisplay($filter_data);


        $time     = date('Ymd');
        $fileName = 'Rebate Bid List ' . $time . '.csv';
        $columns  = [
            'Agreement ID'                => 'agreement_code',
            'Item Code'                   => 'items_sku_str',
            'Days'                        => 'day',
            'Time of Effect'              => 'effect_time',
            'Time of Failure'             => 'expire_time',
            'Min. Total Selling Quantity' => 'qty',
            'Purchased QTY'               => 'purchased_qty',
            'Remaining QTY'               => 'remaining_qty',
            'Agreement Status'            => 'agreement_status_name',
            'Rebate Result'               => 'result_status_name',
            'Store'                       => 'screenname',
            'Last Modified'               => 'update_time',
        ];
        $temp     = [];

        foreach ($results as $key => $result) {
            $result = $this->model_account_product_quotes_rebates_agreement->formatRebatesContractDownload($result);

            $result['agreement_status_name'] = $this->language->get('text_status_' . $result['status']);
            $result['result_status_name']    = $this->language->get('text_result_status_' . $result['rebate_result']);

            $temp[] = [
                'agreement_code'        => "\t" . $result['agreement_code'],
                'items_sku_str'         => $result['items_sku_str'],
                'day'                   => $result['day'],
                'effect_time'           => $result['effect_time'],
                'expire_time'           => $result['expire_time'],
                'qty'                   => $result['qty'],
                'purchased_qty'         => $result['purchased_qty'],
                'remaining_qty'         => $result['remaining_qty'],
                'agreement_status_name' => $result['agreement_status_name'],
                'result_status_name'    => $result['result_status_name'],
                'screenname'            => html_entity_decode($result['screenname']),
                'update_time'           => $result['update_time'],
            ];
        }

        outputCsv($fileName, array_keys($columns), $temp, $this->session);
    }


    public function addContract()
    {
        $post_data = $this->request->post;
        $customer_id = $this->customer->getId();
        if (!$this->customer->isLogged() || !isset($customer_id) || !isset($post_data)) {
            session()->set('redirect', $this->url->link('account/customer_order', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $this->load->model('account/product_quotes/rebates_contract');
        $this->load->model('account/customerpartner/rebates');
        $this->load->language('account/product_quotes/rebates_contract');
        $product_info = $this->model_account_product_quotes_rebates_contract->getProductInformationByProductId($post_data['input_rebates_product_id']);
        $can_bid_rebates = $this->model_account_customerpartner_rebates->checkRebatesProcessing($customer_id, $post_data['input_rebates_product_id']);

        $json = array();
        if (empty($product_info)) {
            $json['error'] = $this->language->get("error_no_product");
        } elseif (intval($product_info['quantity'] * 0.8) < intval($post_data['input_rebates_qty'])) {
            $json['error'] = $this->language->get("error_sold_qty");
        } elseif (!$can_bid_rebates) {
            $this->load->language('account/customerpartner/rebates');
            $json['error'] = $this->language->get("error_rebates_exist");
        } else {
            $contract = array(
                'buyer_id' => $customer_id,
                'seller_id' => $product_info['seller_id'],
                'product_id' => $product_info['product_id'],
                'day' => $post_data['input_rebates_day'],
                'qty' => $post_data['input_rebates_qty'],
                'price' => $post_data['input_rebates_price'],
                'rebates_amount' => $post_data['input_rebates_discount_amount'],
                'rebates_price' => $post_data['input_rebates_discount_price'],
                'limit_price' => $post_data['input_rebates_price_limit'],
                'clauses_id' => $this->config->get('rebates_information_id_buyer'),
                'message' => $post_data['input_rebates_message'],
                'status' => 1,
                'memo' => '',
                'create_username' => $customer_id,
                'program_code' => 'V1.0'
            );
            $contract_id = $this->model_account_product_quotes_rebates_contract->saveRebatesContract($contract);

            if (isset($contract_id)) {
                $this->load->model('account/notification');
                /** @var ModelAccountNotification $modelAccountNotification */
                $modelAccountNotification = $this->model_account_notification;
                // 消息提醒
                $modelAccountNotification->addBidActivity('rebates', $contract_id);
                $json['success'] = $this->language->get("text_add_success");
            } else {
                $json['error'] = $this->language->get("error_place_bid");
            }
        }
        $this->response->setOutput(json_encode($json));
    }

    public function view()
    {
        $contract_id = $this->request->get['contract_id'];
        $customer_id = $this->customer->getId();
        if (!$this->customer->isLogged() || !isset($contract_id)) {
            session()->set('redirect', $this->url->link('account/product_quotes/rebates_contract/view&contract_id=' . $contract_id));
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        //bid notification
        $this->load->model('account/notification');
        /** @var ModelAccountNotification $modelAccountNotification */
        $modelAccountNotification = $this->model_account_notification;
        if(isset($this->request->get['activity_id'])){
            $modelAccountNotification->setBidIsReadByAcId($this->request->get['activity_id']);
        }elseif (isset($contract_id)){
            $modelAccountNotification->setBidIsReadByAgreementId($contract_id);
        }

        $this->load->model('account/product_quotes/rebates_contract');
        $this->load->language('account/product_quotes/rebates_contract');

        $this->document->setTitle($this->language->get('heading_title'));
        $data = array();
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home'),
            'separator' => false
        );
        if ($this->customer->isPartner()) {
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_seller_center'),
                'href' => 'javascript:void(0);',
                'separator' => $this->language->get('text_separator')
            );
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('heading_title_my'),
                'href' => $this->url->link('account/customerpartner/wk_quotes_admin'),
                'separator' => $this->language->get('text_separator')
            );
            $data['breadcrumbs'][] = array(
                'text' => 'Rebates Bid',
                'href' => $this->url->link('account/customerpartner/wk_quotes_admin'),
                'separator' => $this->language->get('text_separator')
            );
        } else {

            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('heading_title_my'),
                'href' => $this->url->link('account/product_quotes/wk_quote_my'),
                'separator' => $this->language->get('text_separator')
            );

            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('heading_rebates_bid'),
                'href' => $this->url->link('account/product_quotes/wk_quote_my'),
                'separator' => $this->language->get('text_separator')
            );
        }
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_rebate_details'),
            'href' => $this->url->link('account/product_quotes/rebates_contract/view&contract_id=' . $contract_id),
            'separator' => $this->language->get('text_separator')
        );

        $data['customer_id'] = $customer_id;
        $contract_detail = $this->model_account_product_quotes_rebates_contract->getRebatesContractDetailDisplay($contract_id);
        if (!empty($contract_detail)) {
            $this->load->model('tool/image');
            if (is_file(DIR_IMAGE . $contract_detail['image'])) {
                $image = $this->model_tool_image->resize($contract_detail['image'], 30, 30);
            } else {
                $image = $this->model_tool_image->resize('no_image.png', 30, 30);
            }
            $buyer_name = $contract_detail['nickname'] . ' (' . $contract_detail['user_number'] . ')';//括号前要有空格
            $data['text_confirm_approve'] = sprintf($this->language->get('text_confirm_approve'), $buyer_name);
            $data['contract_detail'] = array(
                'contractId' => $contract_detail['contract_id'],
                'product_id' => $contract_detail['product_id'],
                'status' => $contract_detail['status'],
                'status_text' => $this->language->get("text_status_" . $contract_detail['status']),
                'update_time' => $contract_detail['update_time'],
                'effect_time' => $contract_detail['effect_time'],
                'expire_time' => $contract_detail['expire_time'],
                'day' => $contract_detail['day'],
                'qty' => $contract_detail['qty'],
                'price' => $this->currency->formatCurrencyPrice($contract_detail['price'], $this->session->data['currency']),
                'rebates_discount' => $contract_detail['rebates_discount'] * 100 . '%',
                'rebates_amount' => $this->currency->formatCurrencyPrice($contract_detail['rebates_amount'], $this->session->data['currency']),
                'rebates_price' => $this->currency->formatCurrencyPrice($contract_detail['rebates_price'], $this->session->data['currency']),
                'price_limit_percent' => $contract_detail['price_limit_percent'] * 100 . '%',
                'limit_price' => $this->currency->formatCurrencyPrice($contract_detail['limit_price'], $this->session->data['currency']),
                'limit_qty' => $contract_detail['limit_qty'],
                'sku' => $contract_detail['sku'],
                'mpn' => $contract_detail['mpn'],
                'image' => $image,
                'buyer_id'=>$contract_detail['customer_id'],
                'buyer_name' => $buyer_name,
                'is_home_pickup' => in_array($contract_detail['customer_group_id'],COLLECTION_FROM_DOMICILE),
                'seller_id' => $contract_detail['seller_id'],
                'store_name' => $contract_detail['store_name'],
                'store_link' => $this->url->link('customerpartner/profile', 'id=' . $contract_detail['seller_id'], true),
                'contact_link' => $this->url->link('customerpartner/profile', 'id=' . $contract_detail['seller_id'] . '&contact=1', true),
                'product_link' => $this->url->link('product/product', 'product_id=' . $contract_detail['product_id'], true),
                'product_name' => $contract_detail['product_name']
            );
        }

        $messages = $this->model_account_product_quotes_rebates_contract->getRebatesContractMessage($contract_id);
        if (!empty($messages)) {
            foreach ($messages as $item) {
                if ($item['is_partner'] == 1) {
                    $name = $item['screenname'];
                } else {
                    $name = $item['nickname'] . ' (' . $item['user_number'] . ')';//括号前要有空格
                }
                $message = array(
                    'writer_id' => $item['writer'],
                    'message' => $item['message'],
                    'name' => $name,
                    'msg_date' => $item['create_time']
                );
                $data['messages'][] = $message;
            }
        }

        $data['heading_title'] = sprintf($this->language->get('heading_title_long'), $contract_id);
        $data['refresh_action'] = $this->url->link('account/product_quotes/rebates_contract/view&contract_id=' . $contract_id);
        $data['send_message_action'] = $this->url->link('account/product_quotes/rebates_contract/addMessage');

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');

        if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
            $data['separate_view'] = true;
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $data['margin'] = "margin-left: 18%";
            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        }

        //用户画像
        $data['styles'][]='catalog/view/javascript/layer/theme/default/layer.css';
        $data['scripts'][]='catalog/view/javascript/layer/layer.js';
        $data['styles'][]='catalog/view/javascript/user_portrait/user_portrait.css?v=' . APP_VERSION;
        $data['scripts'][]='catalog/view/javascript/user_portrait/user_portrait.js?v=' . APP_VERSION;

        if ($this->customer->isPartner()) {
            $this->response->setOutput($this->load->view('account/customerpartner/view_rebates_contract_admin', $data));
        } else {
            $this->response->setOutput($this->load->view('account/product_quotes/view_rebates_contract', $data));
        }
    }


    /**
     * 返点四期
     * @throws Exception
     */
    public function cancel()
    {
        $this->load->language('account/product_quotes/rebates_contract');
        $this->load->model('account/product_quotes/rebates_agreement');
        $contract_id = intval(get_value_or_default($this->request->post, 'contract_id', 0));
        $customer_id = $this->customer->getId();
        $reason      = trim(get_value_or_default($this->request->post, 'reason', ''));



        $json = array();
        if ($this->customer->isPartner()) {
            $json['error'] = 'You are not buyer';
            echo json_encode($json);
            die;
        }
        if (utf8_strlen($reason) < 1) {
            $json['error'] = 'REASON can not be left blank';
            echo json_encode($json);
            die;
        }
        if (utf8_strlen($reason) > 2000) {
            $json['error'] = 'REASON can not be more than 2000 characters';
            echo json_encode($json);
            die;
        }



        if (isset($this->request->post['contract_id']) AND isset($customer_id)) {
            $row_num   = $this->model_account_product_quotes_rebates_agreement->checkAndUpdateRebateTimeout($contract_id);
            $agreement = $this->model_account_product_quotes_rebates_agreement->getRebatesContractDetailByAgreementId($contract_id);
            if ($row_num > 0) {
                $json['error'] = $this->language->get("error_timeout");
            } elseif (!isset($agreement) || $agreement['status'] != 1) {
                $json['error'] = $this->language->get("error_not_pending");
            } else {
                $this->model_account_product_quotes_rebates_agreement->cancelRebatesContract($customer_id, $contract_id, $reason);
                $json['success'] = $this->language->get('text_cancel_success');
            }
        } else {
            $json['error'] = $this->language->get('error_contract_cancel');
        }
        $this->response->setOutput(json_encode($json));
    }


    /**
     * 返点四期
     */
    public function terminate()
    {
        $this->load->language('account/product_quotes/rebates_contract');
        $this->load->model('account/product_quotes/rebates_agreement');
        $agreement_id = $contract_id = intval(get_value_or_default($this->request->post, 'contract_id', 0));
        $customer_id = $this->customer->getId();
        $reason      = trim(get_value_or_default($this->request->post, 'reason', ''));



        $json = array();
        if ($this->customer->isPartner()) {
            $json['error'] = 'You are not buyer';
            echo json_encode($json);
            die;
        }
        if (utf8_strlen($reason) < 1) {
            $json['error'] = 'REASON can not be left blank';
            echo json_encode($json);
            die;
        }
        if (utf8_strlen($reason) > 2000) {
            $json['error'] = 'REASON can not be more than 2000 characters';
            echo json_encode($json);
            die;
        }




        if (isset($this->request->post['contract_id']) AND isset($customer_id)) {
            $row_num   = $this->model_account_product_quotes_rebates_agreement->checkAndUpdateRebateTimeout($contract_id);
            $agreement = $this->model_account_product_quotes_rebates_agreement->getRebatesContractDetailByAgreementId($contract_id);
            $seller_id = intval($agreement['seller_id']);

            if ($row_num > 0) {
                $json['error'] = $this->language->get("error_timeout");
            } elseif ($agreement && $agreement['status'] == 3 && in_array($agreement['rebate_result'], [1, 2])) {
                $num_rows = $this->model_account_product_quotes_rebates_agreement->terminateRebatesContract($seller_id, $customer_id, $contract_id, $reason);
                $this->model->addRebatesAgreementCommunication($agreement_id,$reason,4);//发站内信
                $json['success'] = $this->language->get('text_terminate_success');
            } else {
                $json['error'] = $this->language->get("error_not_terminate");
            }
        } else {
            $json['error'] = $this->language->get('error_contract_cancel');
        }
        $this->response->setOutput(json_encode($json));
    }

    public function addMessage()
    {
        $post_data = $this->request->post;
        $customer_id = $this->customer->getId();
        if (!$this->customer->isLogged() || !isset($post_data['message']) || !isset($post_data['contract_id'])) {
            session()->set('redirect', $this->url->link('account/product_quotes/rebates_contract', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $this->load->model('account/product_quotes/rebates_contract');
        $this->load->language('account/product_quotes/rebates_contract');
        $json = array();

        //校验最新是否超时
        $row_num = $this->model_account_product_quotes_rebates_contract->checkAndUpdateRebateTimeout($post_data['contract_id']);
        if ($row_num > 0) {
            $json['error'] = $this->language->get("error_timeout");
        }
        if (isset($json['error'])) {
            return $this->response->setOutput(json_encode($json));
        }

        $message_data = array(
            'customer_id' => $customer_id,
            'contract_id' => $post_data['contract_id'],
            'msg' => $post_data['message'],
            'date' => date("Y-m-d H:i:s", time())
        );

        //如果是seller需要更新合同状态
        if ($this->customer->isPartner() && isset($post_data['contract_status'])) {
            $update_data = array();
            if ($post_data['contract_status'] == 3) {
                $current_available_qty = 0;
                $sold_qty = 0;
                $contract_used_qty = 0;

                $sold_history = $this->model_account_product_quotes_rebates_contract->getApprovedRebateContractSoldQty($post_data['product_id']);
                $product_info = $this->model_account_product_quotes_rebates_contract->getProductInformationByProductId($post_data['product_id']);

                if (!empty($product_info)) {
                    $current_available_qty = intval($product_info['quantity']);
                }
                if (!empty($sold_history)) {
                    foreach ($sold_history as $his) {
                        $sold_qty += $his['sold_qty'];
                        $contract_used_qty += $his['contract_qty'];
                    }
                }
                if ($current_available_qty + $sold_qty - $contract_used_qty < $post_data['contract_qty']) {
                    $json['error'] = $this->language->get("error_no_stock");
                    return $this->response->setOutput(json_encode($json));
                }

                $day = $post_data['contract_day'];
                $current_time = time();
                $date_added = strtotime('+' . $day . ' day', $current_time);
                $date_added_day = strtotime(date('Y-m-d', $date_added));

                $expire_stamp = $date_added;
                if ($date_added != $date_added_day) {
                    $expire_stamp = strtotime('+1 day', $date_added_day);
                }
                $update_data['effect_time'] = date("Y-m-d H:i:s", $current_time);
                $update_data['expire_time'] = date("Y-m-d", $expire_stamp);
                $update_data['status'] = $post_data['contract_status'];
                $update_data['contract_id'] = $post_data['contract_id'];
                $update_data['update_username'] = $this->customer->getId();
                $update_data['update_time'] = date("Y-m-d H:i:s", $current_time);
            } else {
                $update_data['status'] = $post_data['contract_status'];
                $update_data['contract_id'] = $post_data['contract_id'];
                $update_data['update_username'] = $this->customer->getId();
                $update_data['update_time'] = date("Y-m-d H:i:s", time());
            }

            if ($post_data['contract_status'] == 3) {
                //设置精细化价格
                $contract_detail = $this->model_account_product_quotes_rebates_contract->getRebatesContractDetailDisplay($post_data['contract_id']);
                $this->load->model('customerpartner/DelicacyManagement');
                $delicacyData = array(
                    'seller_id' => $contract_detail['seller_id'],
                    'buyer_id' => $contract_detail['buyer_id'],
                    'product_id' => $contract_detail['product_id'],
                    'delicacy_price' => $contract_detail['price'],
                    'effective_time' => $update_data['effect_time'],
                    'expiration_time' => $update_data['expire_time']
                );

                $delicacy_success = $this->model_customerpartner_DelicacyManagement->addOrUpdate($delicacyData);
                if (!$delicacy_success){
                    $json['error'] = $this->language->get("error_delicacy_fail");
                    return $this->response->setOutput(json_encode($json));
                }
            }

            $this->model_account_product_quotes_rebates_contract->updateRebatesContractStatus($update_data);
            $this->addRebatesCommunication($post_data['contract_id'],$post_data['message']);
        }
        $message_id = $this->model_account_product_quotes_rebates_contract->saveRebatesContractMessage($message_data);
        $new_msg = $this->model_account_product_quotes_rebates_contract->getRebatesContractMessageByMessageId($message_id);

        $message = array();
        if (isset($new_msg)) {
            if ($new_msg['is_partner'] == 1) {
                $name = $new_msg['screenname'];
            } else {
                $name = $new_msg['nickname'] . '(' . $new_msg['user_number'] . ')';
            }
            $message = array(
                'writer_id' => $new_msg['writer'],
                'message' => $new_msg['message'],
                'name' => $name,
                'msg_date' => $new_msg['create_time']
            );
        }

        if (!empty($message)) {
            $json['success'] = $message;
        }

        $this->response->setOutput(json_encode($json));
    }

    /**
     * [addRebateMessage description] buyer 在agreement Pending状态下可以发信息给seller
     */
    public function addRebateMessage()
    {
        $post_data = $this->request->post;
        //if(isset($this->session->data[$this->customer_id.'_rebate_agreement_token'])){
        //    if($this->session->data[$this->customer_id.'_rebate_agreement_token'] == $post_data['token']){
        //        $json['error'] = $this->language->get("error_double_click");
        //        $this->response->returnJson($json);
        //    }else{
        //        $this->session->data[$this->customer_id.'_rebate_agreement_token'] = $post_data['token'];
        //    }
        //}else{
        //    $this->session->data[$this->customer_id.'_rebate_agreement_token'] = $post_data['token'];
        //}
        $post_data['message'] = trim($post_data['message']);
        $customer_id = $this->customer_id;
        if ( !isset($post_data['message']) || !isset($post_data['agreement_id'])) {
            session()->set('redirect', $this->url->link('account/product_quotes/rebates_contract', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        $json = [];

        //校验最新是否超时
        $agreement_id = $this->model->checkAndUpdateRebateAgreementTimeout($post_data['agreement_id']);
        if ($agreement_id) {
            $json['error'] = $this->language->get("error_timeout");
            $json['is_refresh'] = 1;
        }
        if (isset($json['error'])) {
            $this->response->returnJson($json);
        }

        $message_data = [
            'writer' => $customer_id,
            'agreement_id' => $post_data['agreement_id'],
            'message' => $post_data['message'],
            'create_time' => date("Y-m-d H:i:s", time())
        ];
        //如果是seller需要更新合同状态
        if ($this->isPartner && isset($post_data['status'])) {
            $update_data = [];
            if ($post_data['status'] == 3) {

                // 校验 名额显示 是否满足 （available place/limit）
                $this->load->model('account/customerpartner/rebates');
                $canBidInPlaceLimit = $this->model_account_customerpartner_rebates->checkTemplateCanBidByAgreementID($post_data['agreement_id'],$customer_id);
                if (!$canBidInPlaceLimit) {
                    $json['error'] = $this->language->get("error_0_unused_num_seller");
                    $this->response->returnJson($json);
                }

                //新版的返点协议需要校验库存
                $stock_ret = $this->model->verifyAgreementStock($post_data['agreement_id']);
                if(!$stock_ret){
                    $json['error'] = $this->language->get("error_no_stock");
                    $this->response->returnJson($json);
                }


                $day = $post_data['day'];
                $current_time = time();
                $date_added = strtotime('+' . $day . ' day', $current_time);
                $update_data['effect_time'] = date("Y-m-d H:i:s", $current_time);
                $update_data['expire_time'] = date("Y-m-d H:i:s", $date_added);
                $update_data['status'] = $post_data['status'];
                $update_data['id'] = $post_data['agreement_id'];
                $update_data['update_user_name'] = $this->customer_id;
                $update_data['rebate_result'] = 1;
                $update_data['update_time'] = date("Y-m-d H:i:s", $current_time);
            } else {
                $update_data['status'] = $post_data['status'];
                $update_data['id'] = $post_data['agreement_id'];
                $update_data['update_user_name'] = $this->customer_id;
                $update_data['update_time'] = date("Y-m-d H:i:s", time());
            }
            // 设置精细化价格 ??
            if ($post_data['status'] == 3) {
                //设置精细化价格
                //这里是多个有效产品都要添加 一旦失败都要回滚
                $agreement_detail = $this->model->getRebatesAgreementItem($post_data['agreement_id']);
                $this->load->model('customerpartner/DelicacyManagement');
                $this->orm->getConnection()->beginTransaction();

                // 获取模板价格
                $productIdItemMapCollect = collect();
                if (!empty($agreement_detail)) {
                    $agreementProductIds = array_column($agreement_detail, 'product_id');
                    $agreementId = $agreement_detail[0]['id'] ?? 0;
                    $productIdItemMapCollect = $this->model->getRebateTemplateItems($agreementId, $agreementProductIds);
                }

                try {

                    foreach($agreement_detail as $key => $value){
                        // 判断价格
                        $delicacyPrice = $value['template_price'];
                        if ($templateItem = $productIdItemMapCollect->get($value['product_id'], '')) {
                            /** @var RebateAgreementTemplateItem $templateItem */
                            if ($delicacyPrice != $templateItem->price) {
                                //#31737 商品详情页返点审核针对于精细化价格处理
                                $delicacyPrice = $templateItem->price;
                            }
                        }

                        $delicacyData = [
                            'seller_id' => $value['seller_id'],
                            'buyer_id'  =>  $value['buyer_id'],
                            'product_id' => $value['product_id'],
                            'delicacy_price' => $delicacyPrice,
                            'effective_time' => $update_data['effect_time'],
                            'expiration_time' => $update_data['expire_time']
                        ];

                        $delicacy_success = $this->model_customerpartner_DelicacyManagement->addOrUpdate($delicacyData);
                        if (!$delicacy_success){
                            $this->orm->getConnection()->rollBack();
                            $json['error'] = $this->language->get("error_delicacy_fail");
                            $this->response->returnJson($json);
                        }

                    }

                    $this->orm->getConnection()->commit();

                }catch (Exception $e) {
                    $this->orm->getConnection()->rollBack();
                    $json['error'] = $this->language->get("error_delicacy_fail");
                    $this->response->returnJson($json);
                }




            }
            // 更新协议状态
            $this->model->updateRebatesAgreementStatus($update_data);
            // 站内信
            // 3 同意
            // 1 拒绝
            if($post_data['status'] == 3){
                $type = 1;
            }else{
                $type = 0;
            }
            $this->model->addRebatesAgreementCommunication($post_data['agreement_id'],$post_data['message'],$type);
        }
        $message_id = $this->model->saveRebatesAgreementMessage($message_data);
        $new_msg = $this->model->getRebatesAgreementMessageByMessageId($message_id);
        $message = [];
        if (isset($new_msg)) {
            if ($new_msg['is_partner'] == 1) {
                $name = $new_msg['screenname'];
            } else {
                $name = $new_msg['nickname'] . '(' . $new_msg['user_number'] . ')';
            }
            $message = array(
                'writer_id' => $new_msg['writer'],
                'message' => $new_msg['message'],
                'name' => $name,
                'msg_date' => $new_msg['create_time']
            );
        }

        if (!empty($message)) {
            $json['success'] = $message;
        }

        $this->response->returnJson($json);
    }

    private function addRebatesCommunication($contract_id,$message_text = null)
    {
        $this->load->model('account/product_quotes/rebates_contract');
        $this->load->model('customerpartner/rma_management');
        $contract_detail = $this->model_account_product_quotes_rebates_contract->getRebatesContractDetailDisplay($contract_id);
        if (isset($contract_detail) && !empty($contract_detail)) {
            $status_text = '';
            switch ($contract_detail['status']){
                case 0:$status_text = 'Canceled';break;
                case 1:$status_text = 'Pending';break;
                case 2:$status_text = 'Rejected';break;
                case 3:$status_text = 'Approved';break;
                case 4:$status_text = 'Time out';break;
                default:  break;
            }
            $subject = 'BID application result of ' . $contract_detail['sku'] . ': ' . $status_text;
            $message = '<table   border="0" cellspacing="0" cellpadding="0">';
            $message .= '<tr><th align="left">Item Code:&nbsp;</th><td style="width: 650px">
                          <a href="' . $this->url->link('product/product', 'product_id=' . $contract_detail['product_id']) . '">' . $contract_detail['sku'] . '</a>
                          </td></tr> ';
            $message .= '<tr><th align="left">Agreement ID:&nbsp;</th><td style="width: 650px">
                          <a href="' . $this->url->link('account/product_quotes/rebates_contract/view', 'contract_id=' . $contract_detail['contract_id']) . '">' . $contract_detail['contract_id'] . '</a>
                          </td></tr>';
            $message .= '<tr><th align="left">Store Name:&nbsp;</th><td style="width: 650px">
                          <a href="' . $this->url->link('customerpartner/profile', 'id=' . $contract_detail['seller_id']) . '">' . $contract_detail['store_name'] . '</a>
                          </td></tr>';
            $message .= '<tr><th align="left">Processing Result:&nbsp;</th><td style="width: 650px">' . $status_text . ' (' . $message_text . ')</td></tr></table>';

            //$this->communication->saveCommunication($subject, $message, $contract_detail['buyer_id'], $contract_detail['seller_id'], 0);

            $this->load->model('message/message');
            $this->model_message_message->addSystemMessageToBuyer('bid_rebates',$subject,$message,$contract_detail['buyer_id']);

        }
    }


    //public function addRebatesAgreementCommunication()
    //{
    //    //$agreement_id,$message_text = null,$type
    //    $agreement_id = 1;
    //    $message_text = null;
    //    $type = 5;
    //    $ret = $this->model->setTemplateOfCommunication($agreement_id,$message_text,$type);
    //    dd($ret);
    //    exit;
    //
    //    $this->load->model('message/message');
    //    $this->model_message_message->addSystemMessageToBuyer('bid_rebates',$ret['subject'],$ret['message'],$ret['received_id']);
    //
    //
    //}

    /**
     * [rebatesAgreementList description] 返点四期详情 buyer seller 同
     */
    public function rebatesAgreementList(){
        $act          = get_value_or_default($this->request->get, 'act', '');
        $agreement_id = get_value_or_default($this->request->get, 'agreement_id', 1);
        $this->document->setTitle($this->language->get('heading_title'));
        $data = [];
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home'),
            'separator' => false
        );
        if ($this->isPartner) {
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_seller_center'),
                'href' => 'javascript:void(0);',
                'separator' => $this->language->get('text_separator')
            );
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('heading_title_my'),
                'href' => $this->url->link('account/customerpartner/wk_quotes_admin'),
                'separator' => $this->language->get('text_separator')
            );
            $data['breadcrumbs'][] = array(
                'text' => 'Rebates Bid',
                'href' => $this->url->link('account/customerpartner/wk_quotes_admin&tab=rebates'),
                'separator' => $this->language->get('text_separator')
            );
        } else {
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('heading_title_my'),
                'href' => $this->url->link('account/product_quotes/wk_quote_my', "&tab=1"),
                'separator' => $this->language->get('text_separator')
            );
        }
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_rebate_details'),
            'href' => $this->url->link('account/product_quotes/rebates_contract/rebatesAgreementList&agreement_id=' . $agreement_id),
            'separator' => $this->language->get('text_separator')
        );

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');

        // seller 拥有左侧边栏
        if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
            $data['separate_view'] = true;
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $data['margin'] = "margin-left: 18%";
            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        }
        $data['is_partner']      =  $this->isPartner;
        $data['request_url']     =  str_replace('&amp;', '&', $this->url->link('account/product_quotes/rebates_contract/rebatesAgreementRequestList','agreement_id=' . $agreement_id . '&act=' . $act));
        $data['agreement_url']   =  str_replace('&amp;', '&', $this->url->link('account/product_quotes/rebates_contract/rebatesAgreementAgreementList','agreement_id=' . $agreement_id));
        $data['transaction_url'] =  str_replace('&amp;', '&', $this->url->link('account/product_quotes/rebates_contract/rebatesAgreementTransactionList','agreement_id=' . $agreement_id));
        //用户画像
        //验证是否可见request
        $data['request_show'] = $this->model->canSeeRebateRequest($agreement_id);
        $data['transaction_show'] = $this->model->canSeeRebateTransaction($agreement_id);
        $data['agreement_code'] = $this->model->rebateAgreementCode($agreement_id);


        if($this->isPartner){
            $this->response->setOutput($this->load->view('account/product_quotes/rebates_agreement_seller_list', $data));
        }else{
            $this->response->setOutput($this->load->view('account/product_quotes/rebates_agreement_list', $data));
        }


    }

    /**
     * [rebatesAgreementRequestList description] rebates下的Request
     */
    public function rebatesAgreementRequestList(){
        $act          = get_value_or_default($this->request->get, 'act', 'edit');
        $agreement_id = get_value_or_default($this->request->get, 'agreement_id', 1);
        $data['act']          = $act;
        $data['agreement_id'] = $agreement_id;
        $data['list'] = $this->model->getRebatesRequestList($this->isPartner,$agreement_id);
        $data['rebateMethod'] = $this->model->rebatePayMethod($agreement_id);

        $data['request_url'] =  str_replace('&amp;', '&', $this->url->link('account/product_quotes/rebates_contract/rebatesAgreementRequestList','agreement_id=' . $agreement_id));
        $data['agreement_url'] =  str_replace('&amp;', '&', $this->url->link('account/product_quotes/rebates_contract/rebatesAgreementAgreementList','agreement_id=' . $agreement_id));
        if($this->isPartner){
            $data['request_tips'] = sprintf($this->language->get('text_rebates_seller_request_tips'),$data['list']['base_info']['agreement_code'],$data['list']['base_info']['nickname'].'('.$data['list']['base_info']['user_number'].')');
            $data['confirm_url'] =  str_replace('&amp;', '&', $this->url->link('account/product_quotes/rebates_contract/rebatesAgreementConfirm','agreement_id=' . $agreement_id));
            $this->response->setOutput($this->load->view('account/product_quotes/rebates_agreement_seller_request_list', $data));
        }else{
            $data['request_tips'] = sprintf($this->language->get('text_rebates_request_tips'), $data['list']['base_info']['agreement_code'],$data['list']['base_info']['screenname']);
            $data['file_upload_url'] =  str_replace('&amp;', '&', $this->url->link('account/product_quotes/rebates_contract/rebatesAgreementFileUpload','agreement_id=' . $agreement_id));
            $this->response->setOutput($this->load->view('account/product_quotes/rebates_agreement_request_list', $data));
        }
    }

    /**
     * [rebatesAgreementAgreementList description] rebates下的Agreement
     */
    public function rebatesAgreementAgreementList(){
        $agreement_id = get_value_or_default($this->request->get, 'agreement_id', 1);
        $data['agreement_id'] = $agreement_id;
        $data['list'] = $this->model->getRebatesAgreementList($this->isPartner,$agreement_id);
        $messages = $this->model->getRebatesAgreementMessage($agreement_id);
        if (!empty($messages)) {
            foreach ($messages as $item) {
                if ($item['is_partner'] == 1) {
                    $name = $item['screenname'];
                } else {
                    $name = $item['nickname'] . ' (' . $item['user_number'] . ')';//括号前要有空格
                }
                $message = [
                    'writer_id' => $item['writer'],
                    'message' => $item['message'],
                    'name' => $name,
                    'msg_date' => $item['create_time']
                ];
                $data['messages'][] = $message;
            }
        }
        $data['customer_id'] = $this->customer_id;
        $data['send_message_action'] = $this->url->link('account/product_quotes/rebates_contract/addRebateMessage');
        $data['token'] = token(10);
        $data['refresh_action'] = str_replace('&amp;', '&', $this->url->link('account/product_quotes/rebates_contract/rebatesAgreementAgreementList&agreement_id=' . $agreement_id));
        if($this->isPartner){
            $buyer_name =  $data['list']['base_info']['nickname'] . ' (' . $data['list']['base_info']['user_number'] . ')';//括号前要有空格
            $data['text_confirm_approve'] = sprintf($this->language->get('text_confirm_approve'), addslashes($buyer_name));
            // 价格报警设置
            $data['need_alarm'] = 0;
            $this->response->setOutput($this->load->view('account/product_quotes/rebates_agreement_seller_agreement_list', $data));
        }else{
            $data['terminate_action'] = str_replace('&amp;', '&', $this->url->link('account/product_quotes/rebates_contract/setRebatesAgreementTerminate&agreement_id=' . $agreement_id));
            $this->response->setOutput($this->load->view('account/product_quotes/rebates_agreement_agreement_list', $data));
        }

    }

    public function setRebatesAgreementTerminate(){
        $post_data = $this->request->post;
        //把message 存入到seller和buyer对话之中
        $message_data = [
            'writer' => $this->customer_id,
            'agreement_id' => $post_data['agreement_id'],
            'message' => $post_data['message'],
            'create_time' => date("Y-m-d H:i:s", time())
        ];
        $message_id = $this->model->saveRebatesAgreementMessage($message_data);
        $this->model->setRebatesAgreementTerminate($post_data['agreement_id']);
        $this->model->addRebatesAgreementCommunication($post_data['agreement_id'],$post_data['message'],4);
        $json['success'] = $message_id;
        $this->response->returnJson($json);
    }

    /**
     * [rebatesAgreementTransactionList description] rebates下的Transaction
     */
    public function rebatesAgreementTransactionList(){
        $agreement_id = $this->request->get('agreement_id',1);
        $column = $this->request->get('column','time');
        $sort = $this->request->get('sort','asc');
        $data['agreement_id'] = $agreement_id;
        $data['isEurope'] = in_array($this->customer->getCountryId(), EUROPE_COUNTRY_ID) ? true : false;
        $data['list']   = $this->model->getRebatesTransactionList($this->isPartner,$agreement_id,$data['isEurope'],$column,$sort);
        $data['column'] = $column;
        $data['sort']   = $sort;
        $data['service_type'] = SERVICE_TYPE;
        if($this->isPartner){
            $this->response->setOutput($this->load->view('account/product_quotes/rebates_agreement_seller_transaction_list', $data));
        }else{
            $this->response->setOutput($this->load->view('account/product_quotes/rebates_agreement_transaction_list', $data));
        }


    }

    /**
     * [rebatesAgreementFileUpload description] 文件上传的路径
     */
    public function rebatesAgreementFileUpload(){
        $agreement_id = get_value_or_default($this->request->get, 'agreement_id', 1);
        $data['agreement_id'] = $agreement_id;
        $data['submit_action'] = str_replace('&amp;', '&', $this->url->link('account/product_quotes/rebates_contract/rebatesAgreementRequestAdd&agreement_id=' . $agreement_id));
        $this->response->setOutput($this->load->view('account/product_quotes/rebates_agreement_file_upload', $data));
    }

    public function rebatesAgreementRequestAdd(){
        $post = $this->request->post;
        $files = $this->request->files;
        $this->model->rebateAgreementRequestAdd($post,$files);
        $this->model->addRebatesAgreementCommunication($post['agreement_id'],$post['comments'],5);
        $responseData = [
            'code' => 1,
            'msg' => $this->language->get('text_rebates_request_add_success'),
        ];
        $this->response->returnJson($responseData);

    }

    public function rebatesAgreementConfirm(){
        $agreement_id = get_value_or_default($this->request->get, 'agreement_id', 1);
        $amount = get_value_or_default($this->request->get, 'amount', 0);
        $data['request_id'] = get_value_or_default($this->request->get, 'request_id', 0);
        $data['amount_show'] = $this->currency->format($amount, $this->session->data['currency']);
        $type = get_value_or_default($this->request->get, 'type', 1);
        $data['agreement_id'] = $agreement_id;
        $data['type'] = $type;
        $data['seller_action'] = $this->url->link('account/product_quotes/rebates_contract/rebatesAgreementRequestSellerConfirm');
        $this->response->setOutput($this->load->view('account/product_quotes/rebates_agreement_confirm', $data));

    }

    public function rebatesAgreementRequestSellerConfirm(){

        $post = $this->request->post;
        $this->model->rebateAgreementRequestSellerConfirm($post);
        if($post['type'] == 1){
            $this->model->addRebatesAgreementCommunication($post['agreement_id'],$post['comments'],2);
        }else{
            $this->model->addRebatesAgreementCommunication($post['agreement_id'],$post['comments'],3);
        }
        $responseData = [
            'code' => 1,
            'msg' => $this->language->get('text_rebates_request_add_success'),
        ];
        $this->response->returnJson($responseData);

    }


}
