<?php

/**
 * @deprecated 已弃用
 *
 * Class ControllerCustomerpartnerContactBuyer
 */
class ControllerCustomerpartnerContactBuyer extends Controller
{
    public function index(){
        //allowed extension
        $extension = $this->config->get('module_wk_communication_type');
        $extensions = explode(',', $extension);
        $data['extension'] = $extensions;
        $data['max'] = $this->config->get('module_wk_communication_max');
        $data['size'] = $this->config->get('module_wk_communication_size');
        $data['size_mb'] = round($data['size']/1024,2).'MB';
        $data['type'] = explode(",", $this->config->get('module_wk_communication_type'));
        $data['text_from'] = 'From';
        $data['text_subject'] = 'Subject';
        $data['text_loading'] = 'Loading...';
        $data['from'] = $this->customer->getEmail();
        $data['communication_action'] = '';
        $data['mail_action'] = $this->url->link('account/customerpartner/sendquery/sendSMTPMail', '', true);
        return $this->load->view("customerpartner/contact_buyer",$data);
    }
}
