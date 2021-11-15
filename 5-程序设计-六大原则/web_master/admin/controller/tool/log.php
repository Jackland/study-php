<?php
class ControllerToolLog extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('tool/log');

		$this->document->setTitle($this->language->get('heading_title'));

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = session('error');

			$this->session->remove('error');
		} elseif (isset($this->error['warning'])) {
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

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('tool/log', 'user_token=' . session('user_token'), true)
		);

		$data['download'] = $this->url->link('tool/log/download', 'user_token=' . session('user_token'), true);
		$data['clear'] = $this->url->link('tool/log/clear', 'user_token=' . session('user_token'), true);

		$data['log'] = '';

		$file = DIR_LOGS . $this->config->get('config_error_filename');

		if (file_exists($file)) {
			$size = filesize($file);

			if ($size >= 5242880) {
				$suffix = array(
					'B',
					'KB',
					'MB',
					'GB',
					'TB',
					'PB',
					'EB',
					'ZB',
					'YB'
				);

				$i = 0;

				while (($size / 1024) > 1) {
					$size = $size / 1024;
					$i++;
				}

				$data['error_warning'] = sprintf($this->language->get('error_warning'), basename($file), round(substr($size, 0, strpos($size, '.') + 4), 2) . $suffix[$i]);
			} else {
				$data['log'] = file_get_contents($file, FILE_USE_INCLUDE_PATH, null);
			}
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('tool/log', $data));
	}

	public function download() {
		$this->load->language('tool/log');

		$file = DIR_LOGS . $this->config->get('config_error_filename');

		if (file_exists($file) && filesize($file) > 0) {
			$this->response->addheader('Pragma: public');
			$this->response->addheader('Expires: 0');
			$this->response->addheader('Content-Description: File Transfer');
			$this->response->addheader('Content-Type: application/octet-stream');
			$this->response->addheader('Content-Disposition: attachment; filename="' . $this->config->get('config_name') . '_' . date('Y-m-d_H-i-s', time()) . '_error.log"');
			$this->response->addheader('Content-Transfer-Encoding: binary');

			$this->response->setOutput(file_get_contents($file, FILE_USE_INCLUDE_PATH, null));
		} else {
			session()->set('error', sprintf($this->language->get('error_warning'), basename($file), '0B'));

			$this->response->redirect($this->url->link('tool/log', 'user_token=' . session('user_token'), true));
		}
	}

	public function clear() {
		$this->load->language('tool/log');

		if (!$this->user->hasPermission('modify', 'tool/log')) {
			session()->set('error', $this->language->get('error_permission'));
		} else {
			$file = DIR_LOGS . $this->config->get('config_error_filename');

			$handle = fopen($file, 'w+');

			fclose($handle);

			session()->set('success', $this->language->get('text_success'));
		}

		$this->response->redirect($this->url->link('tool/log', 'user_token=' . session('user_token'), true));
	}
}