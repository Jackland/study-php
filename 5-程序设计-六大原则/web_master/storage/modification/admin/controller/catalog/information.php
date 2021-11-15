<?php
use  App\Components\Storage\StorageCloud;
use App\Helper\SummernoteHtmlEncodeHelper;

class ControllerCatalogInformation extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->language('catalog/information');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/information');

        $this->getList();
    }

    public function add()
    {
        $this->load->language('catalog/information');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/information');

        if ((request()->isMethod('POST')) && $this->validateForm()) {
            $information_id = $this->model_catalog_information->addInformation($this->request->post);

            if ($file = $this->request->file('upload_file')) {
                $file_path = 'information' . '/' . $information_id;
                StorageCloud::storage()->writeFile($file, $file_path, $file->getClientOriginalName());
                $this->model_catalog_information->saveInformationFile($information_id, $file_path. '/'.$file->getClientOriginalName());
            }

            session()->set('success', $this->language->get('text_save_success'));

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


            if ($this->config->get('module_marketplace_status') && isset($this->request->get['mpcheck'])) {
                $this->response->redirect($this->url->link('customerpartner/information', 'user_token=' . session('user_token') . $url, true));
            } else {
                $this->response->redirect($this->url->link('catalog/information', 'user_token=' . session('user_token') . $url, true));
            }

        }

        $this->getForm();
    }

    public function edit()
    {
        $this->load->language('catalog/information');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/information');

        if ((request()->isMethod('POST')) && $this->validateForm()) {
            $this->model_catalog_information->editInformation($this->request->get['information_id'], $this->request->post);

            $file = $this->request->file('upload_file');
            if ($file) {
                $file_path = 'information' . '/' . $this->request->get['information_id'];
                StorageCloud::storage()->writeFile($file, $file_path, $file->getClientOriginalName());
                $this->model_catalog_information->saveInformationFile($this->request->get['information_id'], $file_path. '/'.$file->getClientOriginalName());
            }
            session()->set('success', $this->language->get('text_success'));

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


            if ($this->config->get('module_marketplace_status') && isset($this->request->get['mpcheck'])) {
                $this->response->redirect($this->url->link('customerpartner/information', 'user_token=' . session('user_token') . $url, true));
            } else {
                $this->response->redirect($this->url->link('catalog/information', 'user_token=' . session('user_token') . $url, true));
            }

        }

        $this->getForm();
    }

    public function delete()
    {
        $this->load->language('catalog/information');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/information');

        if (isset($this->request->post['selected']) && $this->validateDelete()) {
            foreach ($this->request->post['selected'] as $information_id) {
                $this->model_catalog_information->deleteInformation($information_id);
                $this->model_catalog_information->deleteSubInformation(array($information_id));
            }

            session()->set('success', $this->language->get('text_success'));

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


            if ($this->config->get('module_marketplace_status') && isset($this->request->get['mpcheck'])) {
                $this->response->redirect($this->url->link('customerpartner/information', 'user_token=' . session('user_token') . $url, true));
            } else {
                $this->response->redirect($this->url->link('catalog/information', 'user_token=' . session('user_token') . $url, true));
            }

        }

        $this->getList();
    }

    protected function getList()
    {
        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'id.title';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'ASC';
        }

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
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
            'href' => $this->url->link('catalog/information', 'user_token=' . session('user_token') . $url, true)
        );

        $data['add'] = $this->url->link('catalog/information/add', 'user_token=' . session('user_token') . $url, true);
        $data['delete'] = $this->url->link('catalog/information/delete', 'user_token=' . session('user_token') . $url, true);

        $data['informations'] = array();

        $filter_data = array(
            'sort' => $sort,
            'order' => $order,
            'start' => ($page - 1) * $this->config->get('config_limit_admin'),
            'limit' => $this->config->get('config_limit_admin')
        );

        $information_total = $this->model_catalog_information->getTotalInformations();

        $results = $this->model_catalog_information->getInformations($filter_data);
        $country_dic = $this->model_catalog_information->getCountryDic('country_id', 'iso_code_3');
        foreach ($results as $result) {
            $info = array(
                'information_id' => $result['information_id'],
                'title' => $result['title'],
                'sort_order' => $result['sort_order'],
                'is_link' => $result['is_link'],
                'role' => $result['role'],
                'edit' => $this->url->link('catalog/information/edit', 'user_token=' . session('user_token') . '&information_id=' . $result['information_id'] . $url, true)
            );
            //父元素
            $parent_info = $this->model_catalog_information->getParentInfo($result['parent_id']);
            $title_array = array();
            if (!empty($parent_info)) {
                foreach ($parent_info as $p) {
                    $title_array[] = $p['meta_title'];
                }
            }
            $title_array[] = $result['meta_title'];
            $info['parent_meta_title'] = implode(' > ', $title_array);

            //国别
            $cty_names = array();
            if (!empty($result['country'])) {
                $info_cty_ids = explode(',', $result['country']);
                foreach ($info_cty_ids as $cty_id) {
                    $cty_names[] = $country_dic[$cty_id];
                }
            }
            $info['country'] = implode(',', $cty_names);
            $data['informations'][] = $info;
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = session('success');

            $this->session->remove('success');
        } else {
            $data['success'] = '';
        }

        if (isset($this->request->post['selected'])) {
            $data['selected'] = (array)$this->request->post['selected'];
        } else {
            $data['selected'] = array();
        }

        $url = '';

        if ($order == 'ASC') {
            $url .= '&order=DESC';
        } else {
            $url .= '&order=ASC';
        }

        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }

        $data['sort_title'] = $this->url->link('catalog/information', 'user_token=' . session('user_token') . '&sort=id.title' . $url, true);
        $data['sort_sort_order'] = $this->url->link('catalog/information', 'user_token=' . session('user_token') . '&sort=i.sort_order' . $url, true);

        $url = '';

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        $pagination = new Pagination();
        $pagination->total = $information_total;
        $pagination->page = $page;
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->url = $this->url->link('catalog/information', 'user_token=' . session('user_token') . $url . '&page={page}', true);

        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf($this->language->get('text_pagination'), ($information_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($information_total - $this->config->get('config_limit_admin'))) ? $information_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $information_total, ceil($information_total / $this->config->get('config_limit_admin')));

        $data['sort'] = $sort;
        $data['order'] = $order;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('catalog/information_list', $data));
    }

    protected function getForm()
    {
        $data['text_form'] = !isset($this->request->get['information_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
        if (isset($this->error['focus_data_page'])) {
            $data['focus_data_page'] = $this->error['focus_data_page'];
        }
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['title'])) {
            $data['error_title'] = $this->error['title'];
        } else {
            $data['error_title'] = array();
        }

        if (isset($this->error['description'])) {
            $data['error_description'] = $this->error['description'];
        } else {
            $data['error_description'] = array();
        }

        if (isset($this->error['meta_title'])) {
            $data['error_meta_title'] = $this->error['meta_title'];
        } else {
            $data['error_meta_title'] = array();
        }

        if (isset($this->error['keyword'])) {
            $data['error_keyword'] = $this->error['keyword'];
        } else {
            $data['error_keyword'] = '';
        }
        if (isset($this->error['meta_keyword'])) {
            $data['error_meta_keyword'] = $this->error['meta_keyword'];
        } else {
            $data['error_meta_keyword'] = '';
        }
        if (isset($this->error['meta_description'])) {
            $data['error_meta_description'] = $this->error['meta_description'];
        } else {
            $data['error_meta_description'] = array();
        }
        if (isset($this->error['sort_order'])) {
            $data['error_sort_order'] = $this->error['sort_order'];
        } else {
            $data['error_sort_order'] = '';
        }
        if (isset($this->error['roles'])) {
            $data['error_roles'] = $this->error['roles'];
        } else {
            $data['error_roles'] = '';
        }
        if (isset($this->error['error_file'])) {
            $data['error_file'] = $this->error['error_file'];
        } else {
            $data['error_file'] = '';
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
            'href' => $this->url->link('catalog/information', 'user_token=' . session('user_token') . $url, true)
        );

        if (!isset($this->request->get['information_id'])) {
            $data['action'] = $this->url->link('catalog/information/add', 'user_token=' . session('user_token') . $url, true);
        } else {

            if (isset($this->request->get['mpcheck']) && $this->request->get['mpcheck']) {
                $data['action'] = $this->url->link('catalog/information/edit', 'user_token=' . session('user_token') . '&mpcheck=1&information_id=' . $this->request->get['information_id'] . $url, true);
            } else {
                $data['action'] = $this->url->link('catalog/information/edit', 'user_token=' . session('user_token') . '&information_id=' . $this->request->get['information_id'] . $url, true);
            }

        }

        $data['cancel'] = $this->url->link('catalog/information', 'user_token=' . session('user_token') . $url, true);

        if (isset($this->request->get['information_id']) && (!request()->isMethod('POST'))) {
            $information_info = $this->model_catalog_information->getInformation($this->request->get['information_id']);
            $information_info['country'] = explode(',', $information_info['country']);
            $parent_infos = $this->model_catalog_information->getParentInfo($information_info['parent_id']);
            if (!empty($parent_infos)) {
                $parent_info = array_last($parent_infos);
                if (isset($parent_info['meta_title'])) {
                    $information_info['parent_meta_title'] = $parent_info['meta_title'];
                }
                if (isset($parent_info['country'])) {
                    $data['parent_country'] = $parent_info['country'];
                }
                if (isset($parent_info['role'])) {
                    $data['parent_role'] = $parent_info['role'];
                }
            }
            if (isset($information_info['file_path']) && !empty($information_info['file_path'])) {
                $data['file_name'] = basename($information_info['file_path']);
            }
            if (isset($information_info['role']) && !empty($information_info['role'])) {
                $roles = explode(',', $information_info['role']);
                $data['roles'] = $roles;
            }
        }

        if (isset($this->request->get['information_id'])) {
            $data['information_id'] = $this->request->get['information_id'];
        }

        $data['user_token'] = session('user_token');

        $this->load->model('localisation/language');

        $data['languages'] = $this->model_localisation_language->getLanguages();

        if (isset($this->request->post['information_description'])) {
            $data['information_description'] = array_map(function ($item) {
                $item['description'] = SummernoteHtmlEncodeHelper::decode($item['description']);
                return $item;
            }, $this->request->post['information_description']);
        } elseif (isset($this->request->get['information_id'])) {
            $data['information_description'] = $this->model_catalog_information->getInformationDescriptions($this->request->get['information_id']);
        } else {
            $data['information_description'] = array();
        }

        $this->load->model('setting/store');

        $data['stores'] = array();

        $data['stores'][] = array(
            'store_id' => 0,
            'name' => $this->language->get('text_default')
        );

        $stores = $this->model_setting_store->getStores();

        foreach ($stores as $store) {
            $data['stores'][] = array(
                'store_id' => $store['store_id'],
                'name' => $store['name']
            );
        }

        if (isset($this->request->post['information_store'])) {
            $data['information_store'] = $this->request->post['information_store'];
        } elseif (isset($this->request->get['information_id'])) {
            $data['information_store'] = $this->model_catalog_information->getInformationStores($this->request->get['information_id']);
        } else {
            $data['information_store'] = array(0);
        }

        if (isset($this->request->post['status'])) {
            $data['status'] = $this->request->post['status'];
        } elseif (!empty($information_info)) {
            $data['status'] = $information_info['status'];
        } else {
            $data['status'] = true;
        }

        if (isset($this->request->post['sort_order'])) {
            $data['sort_order'] = $this->request->post['sort_order'];
        } elseif (!empty($information_info)) {
            $data['sort_order'] = $information_info['sort_order'];
        } else {
            $data['sort_order'] = '';
        }

        if (isset($this->request->post['information_seo_url'])) {
            $data['information_seo_url'] = $this->request->post['information_seo_url'];
        } elseif (isset($this->request->get['information_id'])) {
            $data['information_seo_url'] = $this->model_catalog_information->getInformationSeoUrls($this->request->get['information_id']);
        } else {
            $data['information_seo_url'] = array();
        }

        if (isset($this->request->post['information_layout'])) {
            $data['information_layout'] = $this->request->post['information_layout'];
        } elseif (isset($this->request->get['information_id'])) {
            $data['information_layout'] = $this->model_catalog_information->getInformationLayouts($this->request->get['information_id']);
        } else {
            $data['information_layout'] = array();
        }

        if (isset($this->request->post['country'])) {
            $data['country'] = $this->request->post['country'];
        } elseif (!empty($information_info)) {
            $data['country'] = $information_info['country'];
        }

        if (isset($this->request->post['parent_meta_title'])) {
            $data['parent_meta_title'] = $this->request->post['parent_meta_title'];
        } elseif (isset($information_info['parent_meta_title'])) {
            $data['parent_meta_title'] = $information_info['parent_meta_title'];
        }
        if (isset($this->request->post['is_link'])) {
            $data['is_link'] = $this->request->post['is_link'];
        } elseif (isset($information_info['is_link'])) {
            $data['is_link'] = $information_info['is_link'];
        }

        if (isset($this->request->get['information_id']) && !isset($information_info)) {
            $information_info = $this->model_catalog_information->getInformation($this->request->get['information_id']);
            $data['file_name'] = $information_info['file_path'];
        }

        $this->load->model('design/layout');

        $data['layouts'] = $this->model_design_layout->getLayouts();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        //帮助中心国别
        $data['countrys'] = $this->model_catalog_information->getCountrys();
        $data['search_action'] = $this->url->link('catalog/information/searchInformation', '', true) . '&user_token=' . session('user_token');
        $this->response->setOutput($this->load->view('catalog/information_form', $data));
    }

    protected function validateForm()
    {
        if (!$this->user->hasPermission('modify', 'catalog/information')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        if (empty($this->request->post['roles'])) {
            $this->error['roles'] = $this->language->get('error_roles');
        }
        foreach ($this->request->post['information_description'] as $language_id => $value) {
            if ((utf8_strlen($value['title']) < 1) || (utf8_strlen($value['title']) > 64)) {
                $this->error['title'][$language_id] = $this->language->get('error_title');
            }

            if ((utf8_strlen($value['meta_title']) < 1) || (utf8_strlen($value['meta_title']) > 64)) {
                $this->error['meta_title'][$language_id] = $this->language->get('error_meta_title');
            }
            if (!empty($value['meta_description']) && (utf8_strlen($value['meta_description']) > 255)) {
                $this->error['meta_description'][$language_id] = $this->language->get('error_meta_description');
            }
            if (!empty($value['meta_keyword']) && (utf8_strlen($value['meta_keyword']) > 64)) {
                $this->error['meta_keyword'][$language_id] = $this->language->get('error_meta_keyword');
            }
        }

        if (!empty($this->request->post['sort_order'])) {
            $sort_order = (int)$this->request->post['sort_order'];
            if ($sort_order < 0 || $sort_order > 999) {
                $this->error['error_sort_order'] = $this->language->get('error_sort_order');
            }
        }
        if (isset($_FILES["upload_file"]) && !empty($_FILES["upload_file"])) {
            if ($_FILES['upload_file']['error'] == 0) {
                if ($_FILES["upload_file"]['type'] != 'application/pdf') {
                    $this->error['error_file'] = $this->language->get('error_file_extension');
                }
            } elseif ($_FILES['upload_file']['error'] == 4 && $this->request->post['status'] == 1) {
                $this->error['error_file'] = $this->language->get('error_file_necessary');
            } elseif ($_FILES['upload_file']['error'] != 0 && $_FILES['upload_file']['error'] != 4) {
                $this->error['error_file'] = $this->language->get('error_file_other');
            }
        }

        if (sizeof($this->error) == 1 && isset($this->error['roles'])) {
            $this->error['focus_data_page'] = true;
        }
        if ($this->error && !isset($this->error['warning'])) {
            $this->error['warning'] = $this->language->get('error_warning');
        }

        return !$this->error;
    }

    protected function validateDelete()
    {
        if (!$this->user->hasPermission('modify', 'catalog/information')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        $this->load->model('setting/store');

        foreach ($this->request->post['selected'] as $information_id) {
            if ($this->config->get('config_account_id') == $information_id) {
                $this->error['warning'] = $this->language->get('error_account');
            }

            if ($this->config->get('config_checkout_id') == $information_id) {
                $this->error['warning'] = $this->language->get('error_checkout');
            }

            if ($this->config->get('config_affiliate_id') == $information_id) {
                $this->error['warning'] = $this->language->get('error_affiliate');
            }

            if ($this->config->get('config_return_id') == $information_id) {
                $this->error['warning'] = $this->language->get('error_return');
            }

            $store_total = $this->model_setting_store->getTotalStoresByInformationId($information_id);

            if ($store_total) {
                $this->error['warning'] = sprintf($this->language->get('error_store'), $store_total);
            }
        }

        return !$this->error;
    }


    /**
     * description:迁移oss时-废弃
     * author: fuyunnan
     * @param
     * @return void
     * @deprecated
     * Date: 2021/6/16
     */
    private function saveHelpCenterFile($information_id, $file_array)
    {
        return false;
        if (!isset($information_id)) {
            return;
        }
        $file_path = 'information' . '/' . $information_id . '/' . $file_array['name'];
        $save_path = str_replace('\\', '/', DIR_STORAGE . $file_path);
        if (!is_dir(dirname($save_path))) {
            mkdir(dirname($save_path), 0777, true);
        } else {
            $dh = opendir(dirname($save_path));
            while ($file = readdir($dh)) {
                if ($file != "." && $file != "..") {
                    $full_path = dirname($save_path) . "/" . $file;
                    if (!is_dir($full_path)) {
                        unlink($full_path);
                    } else {
                        rmdir($full_path);
                    }
                }
            }
            closedir($dh);
        }
        move_uploaded_file($file_array['tmp_name'], $save_path);
        $this->model_catalog_information->saveInformationFile($information_id, $file_path);
    }

    public function deleteInformationFile()
    {
        $this->load->model('catalog/information');
        $information_id = $this->request->get['informationId'];
        $filePath = $this->db->query("SELECT file_path FROM oc_information WHERE information_id = " . (int)$information_id)->row['file_path'];
        if (is_file(DIR_STORAGE . $filePath)) {
            unlink(DIR_STORAGE . $filePath);
        }
        $this->model_catalog_information->saveInformationFile($information_id, null);
        $json = array('success' => true);
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function searchInformation()
    {
        $search = $this->request->get['search'];
        $information_id = $this->request->get['information_id'];
        $this->load->model('catalog/information');


        $sql = "select i.country,i.role,d.meta_title
from oc_information_description d left join oc_information i
on i.information_id = d.information_id
where d.meta_title like '%$search%'  ";
        if (!empty($information_id)) {
            //将子节点和自身排除
            $exlude_ids = $this->model_catalog_information->getSubInformationIds($information_id);
            $exlude_ids[] = $information_id;
            $sql .= " and d.information_id not in (" . implode(',', $exlude_ids) . ") ";
        }
        $sql .= " limit 20 ";
        $rows = $this->db->query($sql)->rows;
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($rows));
    }
}
