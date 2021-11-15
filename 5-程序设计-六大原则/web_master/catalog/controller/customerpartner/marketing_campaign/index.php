<?php

use App\Catalog\Controllers\AuthSellerController;
use Framework\DataProvider\Paginator;

/**
 * Class ControllerCustomerpartnerMarketingCampaignIndex
 * @property ModelAccountCustomerpartnerMargin $model_customerpartner_marketing_campaign_index
 */
class ControllerCustomerpartnerMarketingCampaignIndex extends AuthSellerController
{
    /**
     * @var ModelCustomerpartnerMarketingCampaignIndex $model
     */
    public $model = null;

    public function __construct($registry)
    {
        parent::__construct($registry);
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/account', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        if (!$this->customer->isPartner()) {
            $this->response->redirect($this->url->link('account/account', '', true));
        }

        //初始化添加样式
        $this->document->addStyle('catalog/view/javascript/product/element-ui.css');
        $this->document->addScript('catalog/view/javascript/product/element-ui.js');

        $this->load->language('customerpartner/marketing_campaign/index');
        $this->load->model('customerpartner/marketing_campaign/index');
        $this->model = $this->model_customerpartner_marketing_campaign_index;
    }

    //母tab
    public function activity()
    {
        $this->load->model('customerpartner/marketing_campaign/history');
        //页面有99+转换 这儿直接返回数字即可
        $data['history_number'] = $this->model_customerpartner_marketing_campaign_history->getNoticeNumber(customer()->getId());

        return $this->render('customerpartner/marketing_campaign/activity', $data, 'seller');
    }

    //子tab
    public function index()
    {
        $this->document->setTitle($this->language->get('heading_title'));
        $data = [];
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
            'start' => $page,
            'limit' => $page_limit,
        ];
        $total_and_list = $this->model->getMarketingData($filter_data);
        $template_total = $total_and_list['total'];
        $template_list = $total_and_list['list'];
        //将数据库得来的数据进行整理成页面数据
        if ($template_list) {
            foreach ($template_list as $key => $value) {
                $template_list[$key]['status'] = $this->model->judgeStatus(intval($filter_data['customer_id']), intval($value['id']), intval($value['seller_num']));
                $template_list[$key]['deadline'] = $this->model->countDown($value['apply_end_time']);
                //  $template_list[$key]['sign'] = $this->url->link('customerpartner/marketing_campaign/request', 'id=' . $value['id'], true);
                $template_list[$key]['detail'] = $this->url->link('customerpartner/marketing_campaign/detail', 'id=' . $value['id'], true);
                $tpl['cate_name'] = $this->model->getCateName($value['require_category'], $total_and_list['cate_name_id_list']);
                $tpl['pro_stock'] = intval($value['require_pro_min_stock']);
                $tpl['pro_start_time'] = $value['require_pro_start_time'];
                $tpl['pro_end_time'] = $value['require_pro_end_time'];
                $tpl['pro_seller'] = $value['seller_num'];
                $tpl['pro_num_per'] = $value['product_num_per'];
                $template_list[$key]['name'] = $value['seller_activity_name'] ?? $value['name'];
                $template_list[$key]['tpl'][] = $tpl;
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

        return $this->render('customerpartner/marketing_campaign/index', $data);
    }

}
