<?php

class ControllerInformationTicket extends Controller
{
    public function index()
    {
        $this->load->language('information/information');

        $this->document->setTitle('Ticket System Guide');//<title>About Ticket</title>

        $data = [];
        // 面包屑导航
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_help_center'),
            'href' => $this->url->link('information/information')
        );

        $data['heading_title'] = 'Ticket System Guide';
        $data['description'] = '<p>沁园春·雪</p><p></p><p>北国风光，</p><p>千里冰封，</p><p>万里雪飘。</p><p></p><p>在一个工作日内给答复</p><p>在退返品处理过程中，买卖双方产生争议无法达成一致时，可申请平台仲裁。裁决意见出具后，买卖双方需配合执行。</p><p>当销售订单无法取消时，平台卖家可通过Tickets联系平台协助处理。</p>';

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $this->response->setOutput($this->load->view('information/ticket', $data));
    }
}