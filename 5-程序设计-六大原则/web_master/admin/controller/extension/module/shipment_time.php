<?php

use App\Components\Storage\StorageCloud;

/**
 * Class ControllerExtensionModuleShipmentTime
 * @property ModelExtensionModuleShipmentTime $model_extension_module_shipment_time
 * @property ModelLocalisationCountry $model_localisation_country
 * @property ModelSettingSetting $model_setting_setting
 */
class ControllerExtensionModuleShipmentTime extends Controller
{
    public function index()
    {
        $data = array();
        // 加载language和model
        $this->load->language('extension/module/shipment_time');
        $this->load->model('extension/module/shipment_time');
        $this->load->model('localisation/country');
        $this->load->model('setting/setting');

        $this->document->setTitle($this->language->get('heading_title'));
        // breadcrumbs
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/shipment_time', 'user_token=' . session('user_token'), true)
        );
        $data['action'] = $this->url->link('extension/module/shipment_time', 'user_token=' . session('user_token'), true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=module', true);
        // 获取可用国籍
        $data['countries'] = $this->model_localisation_country->getShowCountries();

        if (isset($this->request->post['module_shipment_time_status'])) {
            $data['module_shipment_time_status'] = $this->request->post['module_shipment_time_status'];
        } else {
            $data['module_shipment_time_status'] = $this->config->get('module_shipment_time_status');
        }
        // 是否是保存刷新
        if (isset($this->request->get['saveSuccess'])) {
            $data['saveSuccess'] = true;
        }
        $userToken = session('user_token');
        $data['user_token'] = $userToken;
        // 获取shipmentTimePage
        $shipmentTimeObj = $this->model_extension_module_shipment_time->getShipmentTime();
        $shipmentTimeArr = array();
        foreach ($shipmentTimeObj as $shipmentTime) {
            $url = null;
            if ($shipmentTime->file_path != null) {
                $url = $this->url->link('extension/module/shipment_time/download', 'country_id=' . $shipmentTime->country_id . '&user_token=' . $userToken, true);
            }
            $shipmentTimeArr[] = array(
                'id' => $shipmentTime->id,
                'country_id' => $shipmentTime->country_id,
                'page_description' => $shipmentTime->page_description,
                'title' => $shipmentTime->title,
                'file_path' => $shipmentTime->file_path,
                'file_name' => $shipmentTime->file_name,
                'url' => $url
            );
        }
        $data['shipmentTimeArr'] = $shipmentTimeArr;
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/module/shipment_time', $data));
    }

    public function deleteFile()
    {
        $this->load->model('extension/module/shipment_time');
        if (isset($this->request->get['countryId'])) {
            $countryId = $this->request->get['countryId'];
            $this->model_extension_module_shipment_time->deleteShipmentFile($countryId);
        }
        $json = array('success' => true);
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * 下载订单模板文件
     */
    public function download()
    {
        $this->load->model('extension/module/shipment_time');
        // 获取shipmentTime对象
        if (isset($this->request->get['country_id'])) {
            $country_id = $this->request->get['country_id'];
            $shipmentTime = $this->model_extension_module_shipment_time->getShipmentTimeById($country_id);
            if ($shipmentTime) {
                return StorageCloud::shipmentFile()->browserDownload($shipmentTime->file_path);
            }
        } else {
            exit('Error: Could not find file!');
        }
    }

    public function save()
    {
        // 加载language和model
        $this->load->language('extension/module/shipment_time');
        $this->load->model('extension/module/shipment_time');
        $this->load->model('localisation/country');
        $this->load->model('setting/setting');
        $userToken = session('user_token');
        // 获取可用国籍
        $countries = $this->model_localisation_country->getShowCountries();
        $dataArray = array();
        $customerId = $this->customer->getId();
        foreach ($countries as $country) {
            $country_id = $country['country_id'];
            // 保存文件上传
            $filePath = null;
            $fileName = null;
            $fileUpdate = false;

            if (isset($this->request->files['shipmentTimeFile_' . $country_id]['error'])
                && $this->request->files['shipmentTimeFile_' . $country_id]['error'] == 0
            ) {
                /** @var Symfony\Component\HttpFoundation\File\UploadedFile $fileInfo */
                $fileInfo = $this->request->file('shipmentTimeFile_' . $country_id);

                // 有文件
                $fileUpdate = true;
                // 上传RMA文件，以用户ID进行分类
                $run_id = time() . "_" . $country_id;
                $fileName = $fileInfo->getClientOriginalName();
                $fileType = $fileInfo->getClientOriginalExtension();
                $realFilePath = $run_id . "." . $fileType;
                $filePath = StorageCloud::shipmentFile()->writeFile($fileInfo, $country_id,$realFilePath);

            }
            $dataArray[] = array(
                'country_id' => $country_id,
                'page_description' => $this->request->post['shipmentTimePage_' . $country_id] == null ? "" : $this->request->post['shipmentTimePage_' . $country_id],
                'title' => $this->request->post['shipmentTimeTitle_' . $country_id] == null ? "" : $this->request->post['shipmentTimeTitle_' . $country_id],
                'file_path' => $filePath == null ? null :  StorageCloud::shipmentFile()->getRelativePath($filePath),
                'file_name' => $fileName == null ? null : $fileName,
                'create_user_name' => $customerId,
                'create_time' => date('Y-m-d H:i:s', time()),
                'update_user_name' => $customerId,
                'update_time' => date('Y-m-d H:i:s', time()),
                'file_update' => $fileUpdate
            );
        }
        $this->model_extension_module_shipment_time->saveOrUpdateShipmentTime($dataArray);
        // 保存setting
        $this->load->model('setting/setting');
        $status = $this->request->post['module_shipment_time_status'];
        $setting = array(
            'module_shipment_time_status' => $status
        );
        $this->model_setting_setting->editSetting('module_shipment_time', $setting);
        // 返回json
        $url = $this->url->link('extension/module/shipment_time', true);
        $json = array('success' => true, 'url' => $url);
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function install()
    {
        // 安装Shipment Time 模块
        // 加载language和model
        $this->load->language('extension/module/shipment_time');
        $this->load->model('extension/module/shipment_time');
        // 创建表结构
        $this->model_extension_module_shipment_time->createTableShipmentTime();
    }

    public function uninstall()
    {
        // 卸载Shipment Time 模块
        // 加载language和model
        $this->load->language('extension/module/shipment_time');
        $this->load->model('extension/module/shipment_time');
        $this->load->model('setting/setting');
        $setting = array(
            'module_shipment_time_status' => 0
        );
        $this->model_setting_setting->editSetting('module_shipment_time', $setting);
        // 移除表结构
        $this->model_extension_module_shipment_time->dropTableShipmentTime();
    }
}
