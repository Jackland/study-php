<?php

/**
 * @property ModelCustomerpartnerPartner $model_customerpartner_partner
 * @property ModelInvitationcodeInvitationcode $model_invitationcode_invitationcode
 */
class ControllerInvitationcodeCreateInvitationCode extends Controller
{
    public function index()
    {
        $this->load->language('invitationcode/invitationcode');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model("invitationcode/invitationcode");

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('invitationcode/create_invitationcode', 'user_token=' . session('user_token'), true)
        );

        if (isset($this->request->get['customerName'])) {
            $customerName = $this->request->get['customerName'];
        }
        if (isset($customerName) && $customerName != "") {
            $customerId = $this->model_invitationcode_invitationcode->getCustomerId($customerName);

            if (isset($customerId)) {
                $strs = "QWERTYUIOPASDFGHJKLZXCVBNM1234567890qwertyuiopasdfghjklzxcvbnm";
                $str = substr(str_shuffle($strs), mt_rand(0, strlen($strs) - 11), 8);
                $data['invitationcode'] = date("ymdHis", time()) . $str;
                $this->model_invitationcode_invitationcode->saveInvitationcode($data['invitationcode'], $customerId);
            } else {
                $data['error_message'] = "* Please enter an account manager name";
            }
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['user_token'] = session('user_token');
        if (isset($this->request->get['customerName'])) {
            $data['filter_name'] = $this->request->get['customerName'];
        }

        $this->response->setOutput($this->load->view('invitationcode/create_invitationcode', $data));
    }

    public function autocomplete()
    {
        $json = array();

        if (isset($this->request->get['filter_name']) || isset($this->request->get['filter_email'])) {

            $this->load->model('customerpartner/partner');

            if (isset($this->request->get['filter_name'])) {
                $filter_name = $this->request->get['filter_name'];
            } else {
                $filter_name = '';
            }

            if (isset($this->request->get['filter_view'])) {
                $filter_view = $this->request->get['filter_view'];
            } else {
                $filter_view = 0;
            }

            if (isset($this->request->get['filter_category'])) {
                $filter_category = $this->request->get['filter_category'];
            } else {
                $filter_category = 0;
            }

            if (isset($this->request->get['filter_email'])) {
                $filter_email = $this->request->get['filter_email'];
            } else {
                $filter_email = '';
            }

            if (isset($this->request->get['limit'])) {
                $limit = $this->request->get['limit'];
            } else {
                $limit = 20;
            }

            $data = array(
                'filter_name' => $filter_name,
                'filter_all' => $filter_view,
                'filter_category' => $filter_category,
                'filter_email' => $filter_email,
                'filter_customer_group_id' => 14,
                'start' => 0,
                'limit' => $limit
            );

            $results = $this->model_customerpartner_partner->getCustomers($data);

            foreach ($results as $result) {

                $json[] = array(
                    'id' => $result['customer_id'],
                    'name' => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
                    'email' => $result['email'],
                );
            }
        }

        $this->response->setOutput(json_encode($json));
    }
}
