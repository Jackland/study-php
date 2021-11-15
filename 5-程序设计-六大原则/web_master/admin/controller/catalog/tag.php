<?php

/**
 * @property ModelCatalogTag $model_catalog_tag
 * @property ModelToolImage $model_tool_image
 */
class ControllerCatalogTag extends Controller
{
    public function index()
    {
        $this->load->language('catalog/tag');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/tag');

        $this->getList();
    }

    protected function getList()
    {
        if (isset($this->session->data['success'])) {
            $data['success'] = session('success');
            $this->session->remove('success');
        } else {
            $data['success'] = '';
        }

        $has_error = false;
        //总体错误
        if (isset($this->session->data['warning'])) {
            $data['error_warning'] = session('warning');
            $this->session->remove('warning');
            $has_error = true;
        } else {
            $data['error_warning'] = '';
        }

        //详细标签的错误
        if (isset($this->session->data['tag_value_error'])) {
            $data['error_tag_value'] = session('tag_value_error');
            $this->session->remove('tag_value_error');
            $has_error = true;
        } else {
            $data['error_tag_value'] = array();
        }

        $url = '';

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('catalog/tag', 'user_token=' . session('user_token') . $url, true)
        );
        $this->load->model('tool/image');
        if (isset($this->session->data['promotion_temp']) && $has_error) {
            $promotions = session('promotion_temp');
            $this->session->remove('promotion_temp');
        } else {
            $promotions = $this->model_catalog_tag->getAllPromotions();
        }

        foreach ($promotions as $promotion_value) {
            if (is_file(DIR_IMAGE . $promotion_value['image'])) {
                $image = $promotion_value['image'];
                $thumb = $promotion_value['image'];
            } else {
                $image = '';
                $thumb = 'no_image.png';
            }

            $data['promotions'][] = array(
                'promotions_id'            => $promotion_value['promotions_id'],
                'name'                     => $promotion_value['name'],
                'image'                    => $image,
                'thumb'                    => $this->model_tool_image->resize($thumb, 35, 35),
                'sort_order'               => $promotion_value['sort_order'],
                'link'                     => $promotion_value['link'],
                'self_support'             => $promotion_value['self_support'],
                'promotions_status'        => $promotion_value['promotions_status']
            );
        }

        $data['no_image_thumb'] = $this->model_tool_image->resize('no_image.png', 35, 35);
        $data['placeholder'] = $data['no_image_thumb'];

        $data['action'] = $this->url->link('catalog/tag/save', 'user_token=' . session('user_token'), true);
        $data['cancel'] = $this->url->link('catalog/tag', 'user_token=' . session('user_token'), true);

        $select_list = array();
        $select_list[] = array(
            'value'     =>     0,
            'text'      =>     'Disable'
        );
        $select_list[] = array(
            'value'     =>     1,
            'text'      =>     'Enable'
        );
        $data['select_option'] = $select_list;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('catalog/tag', $data));
    }

    public function save(){
        if (!$this->user->hasPermission('modify', 'catalog/tag')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        $this->load->language('catalog/tag');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/tag');

        $post_data = $this->request->post;
        if ((request()->isMethod('POST')) && $this->validateForm($post_data)) {
            $this->model_catalog_tag->savePromotions($post_data);

            session()->set('success', $this->language->get('text_save_success'));
        }

        $this->response->redirect($this->url->link('catalog/tag', 'user_token=' . session('user_token'), true));
    }

    protected function validateForm($post_data) {
        $error = array();
        if (!$this->user->hasPermission('modify', 'catalog/tag')) {
            $error['warning'] = $this->language->get('error_permission');
        }elseif (isset($post_data['tag_value'])) {
            $tag_name_array = array();
            foreach ($post_data['tag_value'] as $tag_value_id => $tag_value) {
                if (in_array($tag_value['name'], $tag_name_array)) {
                    $error['tag_value_error'][$tag_value_id] = $this->language->get('error_tag_repeat');
                } else if ((utf8_strlen($tag_value['name']) < 1) || (utf8_strlen($tag_value['name']) > 200)) {
                    $error['tag_value_error'][$tag_value_id] = $this->language->get('error_tag_name');
                } else if ((utf8_strlen($tag_value['image']) < 1) || (utf8_strlen($tag_value['image']) > 250)) {
                    $error['tag_value_error'][$tag_value_id] = $this->language->get('error_tag_image');
                } else if (utf8_strlen($tag_value['link']) > 250) {
                    $error['tag_value_error'][$tag_value_id] = $this->language->get('error_tag_link');
                } else if ((utf8_strlen($tag_value['sort_order']) < 1) || !is_numeric($tag_value['sort_order'])) {
                    $error['tag_value_error'][$tag_value_id] = $this->language->get('error_tag_sort');
                } else if (($tag_value['self_support'] != '1') && ($tag_value['self_support'] != 0)) {
                    $error['tag_value_error'][$tag_value_id] = $this->language->get('error_tag_self');
                } else if (($tag_value['promotions_status'] != '1') && ($tag_value['promotions_status'] != 0)) {
                    $error['tag_value_error'][$tag_value_id] = $this->language->get('error_tag_status');
                } else {
                    $tag_name_array[] = $tag_value['name'];
                }
            }
        }

        if(!empty($error)){
            if(isset($error['warning'])){
                session()->set('warning', $error['warning']);
            }elseif ($error['tag_value_error']){
                session()->set('tag_value_error', $error['tag_value_error']);
            }
        }
        session()->set('promotion_temp', $post_data['tag_value']);
        return !$error;
    }
}