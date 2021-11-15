<?php

use App\Components\Storage\StorageCloud;

/**
 * @property ModelCatalogInformation $model_catalog_information
 * @property ModelExtensionModuleShipmentTime $model_extension_module_shipment_time
 * @property ModelLocalisationCountry $model_localisation_country
 */
class ControllerInformationInformation extends Controller
{
    private $data = array();
    private $cur_info_id;
    private $output;
    private $format = array(
        'ident' => 10,
        'space' => 15,
        'fontsize' => 22,
        'id_prefix' => 'info_ofni_'
    );

    public function index()
    {
        if ($this->config->get('module_information_status')) {
            foreach (['ident', 'space', 'fontsize', 'symbol1', 'symbol2', 'symbol3'] as $item) {
                if (!empty($this->config->get('module_information_' . $item))) {
                    $this->format[$item] = $this->config->get('module_information_' . $item);
                }
            }
        }
        $this->load->language('information/information');

        $this->load->model('catalog/information');

        if (isset($this->request->get['shipmentTime'])) {
            $data['breadcrumbs'][] = array(
                'text' => 'Shipment Notice',
                'href' => $this->url->link('information/information', 'shipmentTime=1', true)
            );
            $moduleShipmentTimeStatus = $this->config->get('module_shipment_time_status');
            if ($moduleShipmentTimeStatus) {
                // 获取当前国家
                $countryCode = session('country');
                // 获取国家ID
                $this->load->model('localisation/country');
                $country = $this->model_localisation_country->getCountryByCode2($countryCode);
                // 获取countryId
                $countryId = $country['country_id'];
                // 获取countryId对应的shipment time
                $this->load->model('extension/module/shipment_time');
                $shipmentTimePage = $this->model_extension_module_shipment_time->getShipmentTime($countryId);
                $data['heading_title'] = 'About Shipment Notice';
                $this->document->setTitle('Shipment Notice');
                $data['module_shipment_time_status'] = $moduleShipmentTimeStatus;
                $data['description'] = html_entity_decode($shipmentTimePage->page_description, ENT_QUOTES, 'UTF-8');
                $data['continue'] = $this->url->link('account/customer_order');

                $data['column_left'] = $this->load->controller('common/column_left');
                $data['column_right'] = $this->load->controller('common/column_right');
                $data['content_top'] = $this->load->controller('common/content_top');
                $data['content_bottom'] = $this->load->controller('common/content_bottom');
                $data['footer'] = $this->load->controller('common/footer');
                $data['header'] = $this->load->controller('common/header');
                $this->response->setOutput($this->load->view('information/information_notice', $data));
            }
        } else {
            //面包屑
            $this->data['breadcrumbs'] = array();

            $this->data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home')
            );
            $this->data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_help_center'),
                'href' => $this->url->link('information/information')
            );
            $this->cur_info_id = $this->request->get['information_id']  ?? 0;
            $this->informationList();
            if ( $this->cur_info_id > 0){
                $this->informationDetail();
                $this->data['cur_info_id'] = $this->cur_info_id;
            }
            $this->framework();
            $this->response->setOutput($this->load->view($this->output, $this->data));
        }
    }

    public function agree()
    {
        $this->load->model('catalog/information');

        if (isset($this->request->get['information_id'])) {
            $information_id = (int)$this->request->get['information_id'];
        } else {
            $information_id = 0;
        }

        $output = '';

        $information_info = $this->model_catalog_information->getInformation($information_id);

        if ($information_info) {
            $output .= html_entity_decode($information_info['description'], ENT_QUOTES, 'UTF-8') . "\n";
        }

        $this->response->setOutput($output);
    }

    public function framework(): void
    {
        $this->data['column_left'] = $this->load->controller('common/column_left');
        $this->data['column_right'] = $this->load->controller('common/column_right');
        $this->data['content_top'] = $this->load->controller('common/content_top');
        $this->data['content_bottom'] = $this->load->controller('common/content_bottom');
        $this->data['footer'] = $this->load->controller('common/footer');
        $this->data['header'] = $this->load->controller('common/header');
        $this->data['continue'] = $this->url->link('common/home');
    }

    public function errorPage()
    {

        $this->document->setTitle($this->language->get('text_error'));

        $this->data['heading_title'] = $this->language->get('text_error');

        $this->data['text_error'] = $this->language->get('text_error');

        $this->response->setStatusCode(404);
        $this->output = 'error/not_found';
    }

    public function informationList()
    {
        $info_rows = $this->model_catalog_information->getInformations();
        if ($this->customer->isLogged()) {
            $cus_country_id = $this->customer->getCountryId();
            foreach ($info_rows as $idx => $row) {
                //根据用户国别过滤
                $ctys = explode(',', $row['country']);
                if (!in_array($cus_country_id, $ctys)) {
                    unset($info_rows[$idx]);
                }
            }
        }
        $this->createTree($info_rows);
        $this->data['tree_html'] = $this->html;
        $this->document->setTitle($this->language->get('text_help_center'));
        $this->output = 'information/information';
    }

    /**
     * @param $cus_country_id
     */
    public function informationDetail()
    {
        $cus_country_id = $this->customer->getCountryId();
        $information_id = (int)$this->request->get['information_id'];
        $information_info = $this->model_catalog_information->getInformation($information_id);
        if (empty($information_info)) {
            $this->errorPage();
            return;
        }
        if ($this->customer->isLogged() && !in_array($cus_country_id, explode(',', $information_info['country']))) {
            $this->errorPage();
            return;
        }

        $this->document->setTitle($information_info['meta_title']);
        $this->document->setDescription($information_info['meta_description']);
        $this->document->setKeywords($information_info['meta_keyword']);

        $this->data['heading_title'] = $information_info['title'];

        if (isset($information_info['file_path']) && !empty($information_info['file_path']) && $information_info['is_link']) {
            $this->data['fileName'] = basename($information_info['file_path']);
            $this->data['fileUrl'] = $this->url->link('information/information/downloadInformationFile', 'informationId=' . $information_id);
        }
        $this->data['description'] = html_entity_decode($information_info['description'], ENT_QUOTES, 'UTF-8');
        $this->output = 'information/information';
    }

    // region api
    public function resolveDetailInfo()
    {
        $cus_country_id = $this->customer->getCountryId();
        $information_id = (int)$this->request->get['information_id'];
        $this->load->model('catalog/information');
        $information_info = $this->model_catalog_information->getInformation($information_id);
        if (empty($information_info)) {
            $this->errorPage();
            return;
        }
        if ($this->customer->isLogged() && !in_array($cus_country_id, explode(',', $information_info['country']))) {
            $this->errorPage();
            return;
        }
        $data['heading_title'] = $information_info['title'];

        if (isset($information_info['file_path']) && !empty($information_info['file_path']) && $information_info['is_link']) {
            $data['fileName'] = basename($information_info['file_path']);
            $data['fileUrl'] = $this->url->link('information/information/downloadInformationFile', 'informationId=' . $information_id);
        }
        $data['description'] = html_entity_decode($information_info['description'], ENT_QUOTES, 'UTF-8');
        $this->response->setOutput($this->load->view('information/detailinfo', $data));
    }

    // endregion

    public function createTree($nodes)
    {
        $root = array('information_id' => -1);
        $this->recursive($root, $nodes, 0);
        return $root;
    }

    private $html;

    public function recursive(&$parent_node, $sub_nodes, $depth)
    {
        $info_id = $this->cur_info_id;
        if ($parent_node['information_id'] == -1) {
            foreach ($sub_nodes as $k => $node) {
                if ($node['parent_id'] == $parent_node['information_id']) {
                    $parent_node['sub'][] = &$sub_nodes[$k];
                    $this->html .= '<div>';
                    $this->recursive($sub_nodes[$k], $sub_nodes, $depth + 1);
                    $this->html .= '</div>';
                    //按sort排序
                    array_multisort(array_column($parent_node['sub'], 'sort_order'), SORT_DESC, $parent_node['sub']);
                }
            }
            return $parent_node;
        }
        //生成html
        $temp_id = $this->format['id_prefix'] . $parent_node['information_id'];
        $href = '';
        $active = '';
        $hasSub = false; // 是否有子项目
        $hasSubActive = false; // 子项目中是否有被选中的
        foreach ($sub_nodes as $k => $node) {
            if ($node['parent_id'] == $parent_node['information_id']) {
                $hasSub = true;
                if ($node['information_id'] == $info_id) {
                    $hasSubActive = true;
                }
            }
        }
        if ($parent_node['is_link']) {
            $href = $this->url->link('information/information/resolveDetailInfo', 'information_id=' . $parent_node['information_id']);
        }
        if ($info_id == $parent_node['information_id']){
            $active = 'active';
        }
        $expandInfo = $hasSubActive ? 'true' : 'false';
        $this->html .= "<a class=\"btn {$active}\" role=\"button\" data-href=\"{$href}\" data-toggle=\"collapse\" data-target=\"#{$temp_id}\" aria-expanded=\"{$expandInfo}\">";
        $title = "<span>" . $parent_node['title'] . "</span>";
        $this->html .= $title;
        if ($hasSub) {
            $toggle_info = $hasSubActive ? 'toggle-icon-info' : '';
            $this->html .= "<span class=\"{$toggle_info}\" style=\"transition: all 0.3s ease-in-out;\"><i class=\"giga icon-sidebar-unfold\"></i></span>";
        }
        $this->html .= "</a>";
        if ($hasSub) {
            $collapse_info = $hasSubActive ? 'in' : '';
            $this->html .= "  <div class=\"collapse {$collapse_info}\" id=\"{$temp_id}\"><div class=\"well\">";
        }
        foreach ($sub_nodes as $k => $node) {
            if ($node['parent_id'] == $parent_node['information_id']) {
                $parent_node['sub'][] = &$sub_nodes[$k];
                $this->recursive($sub_nodes[$k], $sub_nodes, $depth + 1);
                //按sort排序
                array_multisort(array_column($parent_node['sub'], 'sort_order'), SORT_DESC, $parent_node['sub']);
            }
        }
        if ($hasSub) {
            $this->html .= "</div></div>";
        }

        return $parent_node;
    }

    public function downloadInformationFile()
    {
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('information/information', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $id = $this->request->get['informationId'];
        $filePath = $this->db->query("SELECT file_path FROM oc_information WHERE information_id = " . (int)$id)->row['file_path'];
        if (isset($filePath) && !empty($filePath)) {
            /**
             *迁移到oss下载
             */
           return StorageCloud::storage()->browserDownload($filePath);
        }
        return  'Error: Could not find file!';
    }
}
