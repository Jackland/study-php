<?php

use App\Catalog\Controllers\AuthSellerController;
use Framework\DataProvider\Paginator;

/**
 * Class ControllerCustomerpartnerMarketingCampaignHistory
 * @property ModelCustomerpartnerMarketingCampaignHistory model_customerpartner_marketing_campaign_history
 */
class ControllerCustomerpartnerMarketingCampaignHistory extends AuthSellerController
{
    const MCR_STATUS_NAME = [
        '1' => 'Pending',//待审核
        '2' => 'Approved',//同意
        '3' => 'Rejected',//拒绝
        '4' => 'Canceled',//取消
    ];

    public $precision;
    public $symbol;
    public $crumbs;

    public function __construct($registry)
    {
        parent::__construct($registry);
        if (!$this->customer->isLogged()) {
            if (is_ajax()) {
                $this->response->returnJson(['redirect' => $this->url->link('account/login')]);
            } else {
                session()->set('redirect', $this->url->link('account/account', '', true));
                $this->response->redirect($this->url->link('account/login', '', true));
            }
        }
        if (!$this->customer->isPartner()) {
            $this->response->redirect($this->url->link('account/account', '', true));
        }


        //初始化添加样式
        $this->document->addStyle('catalog/view/javascript/product/element-ui.css');
        $this->document->addScript('catalog/view/javascript/product/element-ui.js');
        //初始化時自動加載
        $this->load->language('customerpartner/marketing_campaign/history');
        $this->load->model('customerpartner/marketing_campaign/history');
        //处理类变量
        $this->precision = $this->currency->getDecimalPlace($this->session->data['currency']);
        $this->symbol = $this->currency->getSymbolLeft($this->session->data['currency']);
        if (empty($this->symbol)) {
            $this->symbol = $this->currency->getSymbolRight($this->session->data['currency']);
        }
        if (empty($this->symbol)) {
            $this->symbol = '$';
        }
    }

    public function index()
    {
        $this->document->setTitle($this->language->get('heading_title'));
        $data = array();
        $session = $this->session->data;
        $data['success'] = $session['success'] ?? '';
        if (isset($session['success'])) {
            $this->session->remove('success');
        }
        $data['error_warning'] = $session['error_warning'] ?? '';
        if (isset($session['error_warning'])) {
            $this->session->remove('error_warning');
        }

        $customer_id = $this->customer->getId();
        $country_id = $this->customer->getCountryId();
        $page = $this->request->get('page', 1);
        $page_limit = $this->request->get('page_limit', 15);
        $filter_data = [
            'customer_id' => $customer_id,
            'country_id' => $country_id,
            'start' => ($page - 1) * $page_limit,
            'limit' => $page_limit,
        ];
        $template_total = $this->model_customerpartner_marketing_campaign_history->getmarketingTotal($filter_data);
        $template_list = $this->model_customerpartner_marketing_campaign_history->getmarketingList($filter_data);
        //将数据库得来的数据进行整理成页面数据
        if ($template_list) {
            $mcr_id_arr = [];//seller活动申请表主键 mcr
            $mc_id_arr = [];//活动表主键 mc
            foreach ($template_list as $key => $value) {
                $mcr_id_arr[] = $value['id'];
                $mc_id_arr [$value['mc_id']] = $value['mc_id'];
            }
            //seller活动申请表的产品
            $template_product_key = $this->model_customerpartner_marketing_campaign_history->getmarketingRequestProductList($mcr_id_arr);
            //每个活动中，已同意参加的seller数量；key=活动ID，value=同意的seller数量
            $agree_count_key = $this->model_customerpartner_marketing_campaign_history->getAgreeNumGroupByCampaign($mc_id_arr);
            foreach ($template_list as $key => $value) {
                $value['name'] = $value['seller_activity_name'] ?? $value['name'];
                $value['products'] = isset($template_product_key[$value['id']]) ? $template_product_key[$value['id']] : [];
                $value['products_registered'] = count($value['products']);
                $value['reject_reason'] = trim($value['reject_reason']);
                $value['status_name'] = $this->model_customerpartner_marketing_campaign_history->getStatusName($value);
                $value['approval_status_name'] = self::MCR_STATUS_NAME[$value['status']];
                //如果审核通过，计算审核通过的商品数量
                $value['approval_product_num'] = 0;
                if ($value['status'] == 2) {
                    $approval_product_status_list = array_count_values(array_column($value['products'], 'approval_status'))[2] ?? 0;
                    $value['approval_product_num'] = $approval_product_status_list ?? 0;
                }

                $agree_num = isset($agree_count_key[$value['mc_id']]) ? $agree_count_key[$value['mc_id']] : 0;
                $value['is_can_cancel'] = $this->model_customerpartner_marketing_campaign_history->isCanCancel($value)['ret'];
                $value['is_can_reapplied'] = $this->model_customerpartner_marketing_campaign_history->isCanReapplied($value, $agree_num)['ret'];

                $value['url_cancel'] = $this->url->link('customerpartner/marketing_campaign/history/cancel', 'id=' . $value['id'], true);
                $value['url_reapplied'] = str_replace('&amp;', '&', $this->url->link('customerpartner/marketing_campaign/request', 'id=' . $value['mc_id'] . '&request_id=' . $value['id'], true));

                $template_list[$key] = $value;
            }

            $data['margin_templates'] = $template_list;
        }
        $data['start_no'] = ($page - 1) * $page_limit + 1;
        //默认为展开状态
        $data['expand_status'] = $this->request->get('expand_status', 'collapse');
        //合并成tab了，老的分页用不了，使用dataprovider里面提供的分页器
        $newPaginator = new Paginator();
        $newPaginator->setTotalCount($template_total);
        $data['paginator'] = $newPaginator;
        $url = '';
        $data['url_reload'] = $this->url->link('customerpartner/marketing_campaign/history', '' . $url . '&page={page}', true);
        $data['app_version'] = APP_VERSION;

        return $this->render('customerpartner/marketing_campaign/history', $data);
    }

    //取消
    public function cancel()
    {
        $id = (int)$this->request->post('id', 0);
        $seller_id = $this->customer->getId();
        if ($id < 1) {
            $json['error'] = 'Please refresh the page.';
            goto end;
        }
        if ($seller_id < 1) {
            $json['error'] = 'Please refresh the page.';
            goto end;
        }

        //活动申请 基本信息
        $RequestInfo = $this->model_customerpartner_marketing_campaign_history->getRequestInfo($id);
        if (!$RequestInfo) {
            $json['error'] = 'Data not exist!';
            goto end;
        }

        $ret = $this->model_customerpartner_marketing_campaign_history->isCanCancel($RequestInfo);
        if ($ret['ret'] == 0) {
            $json['error'] = $ret['msg'];
            goto end;
        }

        $this->model_customerpartner_marketing_campaign_history->cancel($id);
        $json['success'] = $this->language->get('text_cancel_success');

        end:
        $this->response->returnJson($json);
    }
}
