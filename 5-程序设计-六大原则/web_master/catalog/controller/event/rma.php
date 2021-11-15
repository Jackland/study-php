<?php

/**
 * Class ControllerEventRma
 * @property ModelCustomerpartnerRmaManagement $model_customerpartner_rma_management
 * @property ModelAccountNotification $model_account_notification
 */
class ControllerEventRma extends Controller
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->load->model('customerpartner/rma_management');
    }

    /**
     * rma的前置动作
     * @see ControllerAccountCustomerpartnerRmaManagement::rmaInfo()
     */
    public function rma_before()
    {
        // 判断是不是保证金的rma 若是 则跳转到对应的url
        $rmaId = (int)$this->request->request['rmaId'];
        $is_margin = $this->model_customerpartner_rma_management->checkIsMarginRma($rmaId);
        // 判断是不是现货保证金的rma
        if ($is_margin) {
            $this->response->redirect(
                $this->url->link(
                    'account/customerpartner/rma_management/margin_rma_info',
                    ['rmaId' => $rmaId]
                )
            );
        }
        // 判断是不是期货保证金的rma
        $is_futures = $this->model_customerpartner_rma_management->checkIsFuturesRma($rmaId);
        if ($is_futures) {
            $this->response->redirect(
                $this->url->link(
                    'account/customerpartner/rma_management/futures_rma_info',
                    ['rmaId' => $rmaId]
                )
            );
        }
    }

    /**
     * 保证金rma info的前置动作
     * @see ControllerAccountCustomerpartnerRmaManagement::margin_rma_info()
     */
    public function margin_rma_before(&$route, &$args)
    {
        // 判断是不是保证金的rma 不是 则返回列表
        $ramId = (int)$this->request->request['rmaId'];
        $is_margin = $this->model_customerpartner_rma_management->checkIsMarginRma($ramId);
        if (!$is_margin) {
            $this->response->redirect($this->url->link('account/customerpartner/rma_management'));
        }
        $this->rma($route, $args);
    }

    /**
     * 期货rma info的前置
     */
    public function futures_rma_before(&$route, &$args)
    {
        // 判断是不是期货保证金的rma 不是 则返回列表
        $rmaId = (int)$this->request->request['rmaId'];
        $is_futures = $this->model_customerpartner_rma_management->checkIsFuturesRma($rmaId);
        if (!$is_futures) {
            $this->response->redirect($this->url->link('account/customerpartner/rma_management'));
        }
        $this->rma($route, $args);
    }

    /**
     * 通用的rma前置信息
     */
    public function rma(&$route, &$args)
    {
        $ramId = $this->request->request['rmaId'];
        $this->load->model('account/notification');
        $this->load->model('customerpartner/rma_management');
        //判断rma是否还是这个seller
        $customer_id = $this->customer->getId();
        $result = $this->model_customerpartner_rma_management->getSellerId((int)$ramId);
        if ($customer_id != $result->seller_id && $customer_id != $result->original_seller_id) {
            $this->response->redirect($this->url->link('account/customerpartner/rma_management'));
        }
        // rma notification
        if ($ramId > 0) {
            $this->model_account_notification->setRmaIsReadById($ramId);
        }
        //校验rma是否cancel
        $cancel_rma = $this->db->query("select cancel_rma from oc_yzc_rma_order where id =" . $ramId)->row['cancel_rma'];
        if ($cancel_rma == 1) {
            $this->response->redirect($this->url->link('account/customerpartner/rma_management', $this->resolveRequestUrl()));
        }
    }

    /**
     * @return string
     * user：wangjinxin
     * date：2020/3/25 11:53
     */
    private function resolveRequestUrl()
    {
        $url = '';
        if (isset($this->request->get['filter_product'])) {
            $url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
        }
        if (isset($this->request->get['filter_author'])) {
            $url .= '&filter_author=' . urlencode(html_entity_decode($this->request->get['filter_author'], ENT_QUOTES, 'UTF-8'));
        }
        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }
        if (isset($this->request->get['filter_date_added'])) {
            $url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
        }
        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }
        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }
        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }
        return $url;
    }
}