
<?php

/**
 * @property ModelExtensionModulewkcommunication $model_extension_module_wk_communication
 * @property ModelSettingSetting $model_setting_setting
 */
class ControllerExtensionModuleWkCommunication extends Controller
{
	public function install(){
		$this->load->model('extension/module/wk_communication');

		$this->model_extension_module_wk_communication->createTableCommunication();
	}

	public function index(){
		$this->load->language('extension/module/wk_communication');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if ((request()->isMethod('POST')) && $this->validate()) {
			$this->model_setting_setting->editSetting('module_wk_communication', $this->request->post);
			session()->set('success', $this->language->get('text_success'));

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=module', true));
		}
		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$config_array = array(
			'module_wk_communication_status',
			'module_wk_communication_type',
			'module_wk_communication_size',
			'module_wk_communication_max',
			'module_wk_communication_bcc',
			'module_wk_communication_keywords',
			'module_wk_communication_search',
			'module_wk_communication_period',
			'module_wk_communication_white_list',
			'module_wk_communication_white_list_email',
			);
		foreach ($config_array as $value) {
				if (isset($this->request->post[$value])) {
					$data[$value] = $this->request->post[$value];
				} elseif ($this->config->get($value)) {
					$data[$value] = $this->config->get($value);
				} else {
					$data[$value] = null;
				}
			}
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
       		'text'      => $this->language->get('heading_title'),
					'href'      => $this->url->link('extension/module/wk_communication', 'user_token=' . session('user_token'), true),
   		);

		$data['action'] = $this->url->link('extension/module/wk_communication', 'user_token=' . session('user_token'), true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . session('user_token') . '&type=module', true);

		$data['header'] = $this->load->Controller('common/header');

		$data['column_left'] = $this->load->Controller('common/column_left');
		$data['footer'] = $this->load->Controller('common/footer');

		$data['upload_max_filesize'] = preg_replace ('/M/','',ini_get('upload_max_filesize')) * 1024;

		$this->response->setOutput($this->load->view('extension/module/wk_communication',$data));

	}
	/**
	 * For validate the user.
	 * @return [type] [description]
	 */
	protected function validate() {
		$warning = null;
		if (!$this->user->hasPermission('modify', 'extension/module/wk_communication')) {
//			$this->error['warning'] = $this->language->get('error_permission');
            $warning = $this->language->get('error_permission');
		}
		$file_size = $this->request->post['module_wk_communication_size'];
		if ($file_size) {
            $upload_max_filesize = preg_replace ('/M/','',ini_get('upload_max_filesize')) * 1024;
			if($file_size<0 || $file_size >  $upload_max_filesize){
                $warning = $this->language->get('entry_size') . " must be > 0 and < $upload_max_filesize";
			}
		}
		$file_max = $this->request->post['module_wk_communication_max'];
		if($file_max){
			$max_file_uploads = ini_get('max_file_uploads');
			if($file_max<0 || $file_max >  $max_file_uploads){
            	$warning = $this->language->get('entry_max') . " must be >0 and < $max_file_uploads";
			}
		}
		if($warning){
        	$this->error = array('warning'=> $warning);
		}
		return !$warning;
	}
	public function uninstall() {
		$this->load->model('extension/module/wk_communication');
		$this->model_extension_module_wk_communication->deleteTableCommunication();
	}

}
?>
