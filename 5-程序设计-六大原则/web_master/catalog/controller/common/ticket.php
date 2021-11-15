<?php

/**
 * @property ModelAccountTicket $model_account_ticket
 */
class ControllerCommonTicket extends Controller
{
    public function index()
    {
        $this->load->language('account/ticket');
        $this->load->model('account/ticket');

        $information_id = 0;
        $data = [];
        if ($this->customer->isPartner()) {
            $role = 'seller';
            $information_id = intval($this->config->get('message_ticket_guide_seller'));
        } else {
            $role = 'buyer';
            $information_id = intval($this->config->get('message_ticket_guide'));
        }
        $ticketCategoryGroupList = $this->model_account_ticket->categoryGroupListKeyStr($role);

        $data['guide_url'] = $this->url->link('information/information', 'information_id=' . $information_id, true);

        $data['ticketCategoryGroupList']       = !$ticketCategoryGroupList ? '{}' : json_encode($ticketCategoryGroupList);
        // 以下可移除
        //$data['ticketIsShowSubmitButton'] = $this->model_account_ticket->isShowSubmitButton();


        return $this->load->view('common/ticket', $data);
    }

    /**
     * 新建Ticket页,样式更新了
     */
    public function sendPage()
    {
        $this->load->language('account/ticket');

        /** @var ModelAccountTicket $modelAccountTicket */
        $modelAccountTicket = load()->model('account/ticket');

        $data = [];
        if ($this->customer->isPartner()) {
            $role = 'seller';
            $informationId = intval($this->config->get('message_ticket_guide_seller'));
        } else {
            $role = 'buyer';
            $informationId = intval($this->config->get('message_ticket_guide'));
        }

        $ticketCategoryGroupList = $modelAccountTicket->categoryGroupListKeyStr($role);
        $data['guide_url'] = url()->to(['information/information', 'information_id' => $informationId]);
        $data['ticketCategoryGroupList'] = !$ticketCategoryGroupList ? '{}' : json_encode($ticketCategoryGroupList);

        return $this->render('account/message_center/ticket/send_page', $data);
    }
}
