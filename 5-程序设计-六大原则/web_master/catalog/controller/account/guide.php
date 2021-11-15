<?php

use App\Catalog\Controllers\AuthController;

class ControllerAccountGuide extends AuthController
{
    public function index(ModelAccountRmaManagement $modelAccountRmaManagement, ModelAccountGuide $modelAccountGuide)
    {
        $chkIsPartner = $this->customer->isPartner();
        if ($chkIsPartner || $this->session->has('marketplace_seller_mode') && !$this->session->get('marketplace_seller_mode')) {
            return $this->redirect(['account/account']);
        }

        return $this->redirect('account/buyer_central');

        $this->setDocumentInfo();
        $this->setLanguages('account/guide');
        $data['breadcrumbs'] = $this->getBreadcrumbs();

        $customerId = $this->customer->getId();

        //13587 【需求】Buyer登录平台后，增加未处理订单等消息提醒
        $isPartner = $this->customer->isPartner();
        $countryId = $this->customer->getCountryId();
        //针对Buyer开发，OrderHistory增加下载表格查看销售报告功能 12909
        if (!$isPartner) {
            $num = $modelAccountGuide->getBuyerNewOrderAmount($customerId, $countryId);
            if ($num != 0) {
                $data['order_display'] = 1;
                $data['order_amount'] = $num > 99 ? '99+' : $num;
            } else {
                $data['order_display'] = 0;
                $data['order_amount'] = 0;
            }
        } else {
            $data['order_amount'] = 0;
            $data['order_display'] = 0;
        }

        // 已提交未处理的rma数量 100065
        $countRMA = $modelAccountRmaManagement->getRmaOrderInfoCount(['seller_status' => [1, 3]]);
        if ($countRMA > 99) {
            $countRMA = '99+';
        } elseif ($countRMA < 1) {
            $countRMA = '';
        }
        $data['unresolved_rma_count'] = strval($countRMA);

        $data['url_inventory_to_be_paid'] = $this->url->to(['account/customer_order', 'action' => 'guide']);

        return $this->render('account/guide', $data, [
            'footer' => 'common/footer',
            'header' => 'common/header',
            'column_left' => 'common/column_left',
            'column_notice' => 'information/notice/column_notice',
        ]);
    }
}
