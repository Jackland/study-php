<?php

/**
 * @property ModelSettingExtension $model_setting_extension
 * @property ModelUserUserGroup $model_user_user_group
 */
class ControllerExtensionExtensionCaptcha extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/extension/captcha');

		$this->load->model('setting/extension');

		$this->getList();
	}

	public function install() {
		$this->load->language('extension/extension/captcha');

		$this->load->model('setting/extension');

		if ($this->validate()) {
			$this->model_setting_extension->install('captcha', $this->request->get['extension']);

			$this->load->model('user/user_group');

			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/captcha/' . $this->request->get['extension']);
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/captcha/' . $this->request->get['extension']);

			// Compatibility
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'captcha/' . $this->request->get['extension']);
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'captcha/' . $this->request->get['extension']);

			// Call install method if it exsits
			$this->load->controller('extension/captcha/' . $this->request->get['extension'] . '/install');

			session()->set('success', $this->language->get('text_success'));
		}

		$this->getList();
	}

	public function uninstall() {
		$this->load->language('extension/extension/captcha');

		$this->load->model('setting/extension');

		if ($this->validate()) {
			$this->model_setting_extension->uninstall('captcha', $this->request->get['extension']);

			// Call uninstall method if it exsits
			$this->load->controller('extension/captcha/' . $this->request->get['extension'] . '/uninstall');

			session()->set('success', $this->language->get('text_success'));
		}

		$this->getList();
	}

	protected function getList() {
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

		$extensions = $this->model_setting_extension->getInstalled('captcha');

		foreach ($extensions as $key => $value) {
			if (!is_file(DIR_APPLICATION . 'controller/extension/captcha/' . $value . '.php') && !is_file(DIR_APPLICATION . 'controller/captcha/' . $value . '.php')) {
				$this->model_setting_extension->uninstall('captcha', $value);

				unset($extensions[$key]);
			}
		}

		$data['extensions'] = array();

		// Compatibility code for old extension folders
		$files = glob(DIR_APPLICATION . 'controller/extension/captcha/*.php');

		if ($files) {
			foreach ($files as $file) {
				$extension = basename($file, '.php');

				$this->load->language('extension/captcha/' . $extension, 'extension');

				$data['extensions'][] = array(
					'name'      => $this->language->get('extension')->get('heading_title') . (($extension == $this->config->get('config_captcha')) ? $this->language->get('text_default') : null),
					'status'    => $this->config->get('captcha_' . $extension . '_status') ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
					'install'   => $this->url->link('extension/extension/captcha/install', 'user_token=' . session('user_token') . '&extension=' . $extension, true),
					'uninstall' => $this->url->link('extension/extension/captcha/uninstall', 'user_token=' . session('user_token') . '&extension=' . $extension, true),
					'installed' => in_array($extension, $extensions),
					'edit'      => $this->url->link('extension/captcha/' . $extension, 'user_token=' . session('user_token'), true)
				);
			}
		}

		$this->response->setOutput($this->load->view('extension/extension/captcha', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/extension/captcha')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}