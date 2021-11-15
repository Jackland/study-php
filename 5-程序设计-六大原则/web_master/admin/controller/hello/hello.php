<?php
/**
 * Created by IntelliJ IDEA.
 * User: 李磊
 * Date: 2018/10/13
 * Time: 16:05
 */
class ControllerHelloHello extends Controller {
    public function index() {
        $this->document->setTitle("Hello World");

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
        );

        $data['breadcrumbs'][] = array(
            'text' => "HelloWorld",
            'href' => $this->url->link('hello/hello', 'user_token=' . session('user_token'), true)
        );
        $data["demo"] = "Hello World<br/>Demo Hello";
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('hello/hello', $data));
    }
}