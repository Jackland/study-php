<?php
class ControllerCommonLogin extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('common/login');

		$this->document->setTitle($this->language->get('heading_title'));
        /**
         * @see User::isLogged()
         */
		if ($this->user->isLogged() && isset($this->request->get['user_token']) && ($this->request->get['user_token'] == session('user_token'))) {
			$this->response->redirect($this->url->link('common/dashboard', 'user_token=' . session('user_token'), true));
		}

		if ((request()->isMethod('POST')) && $this->validate()) {
			session()->set('user_token', token(32));

			if (isset($this->request->post['redirect']) && (strpos($this->request->post['redirect'], HTTP_SERVER) === 0 || strpos($this->request->post['redirect'], HTTPS_SERVER) === 0)) {
                // Debug Log.
                if (isset($_REQUEST['DEBUG']) && $_REQUEST['DEBUG'] == 1) {
                    $this->response->redirect($this->request->post['redirect'] . '&DEBUG=1&user_token=' . session('user_token'));
                }else{
                    $this->response->redirect($this->request->post['redirect'] . '&user_token=' . session('user_token'));
                }
                //End of log.
			} else {
				$this->response->redirect($this->url->link('common/dashboard', 'user_token=' . session('user_token'), true));
			}
		}

		if ((isset($this->session->data['user_token']) && !isset($this->request->get['user_token'])) || ((isset($this->request->get['user_token']) && (isset($this->session->data['user_token']) && ($this->request->get['user_token'] != $this->session->data['user_token']))))) {
			$this->error['warning'] = $this->language->get('error_token');
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

		$data['action'] = $this->url->link('common/login', '', true);

		if (isset($this->request->post['username'])) {
			$data['username'] = $this->request->post['username'];
		} else {
			$data['username'] = '';
		}

		if (isset($this->request->post['password'])) {
			$data['password'] = $this->request->post['password'];
		} else {
			$data['password'] = '';
		}

		if (isset($this->request->get['route'])) {
			$route = $this->request->get['route'];

			unset($this->request->get['route']);
			unset($this->request->get['user_token']);

			$url = '';

			if ($this->request->get) {
				$url .= http_build_query($this->request->get);
			}

			$data['redirect'] = $this->url->link($route, $url, true);
		} else {
			$data['redirect'] = '';
		}

		if ($this->config->get('config_password')) {
			$data['forgotten'] = $this->url->link('common/forgotten', '', true);
		} else {
			$data['forgotten'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['footer'] = $this->load->controller('common/footer');
        if (isset($_REQUEST['DEBUG']) && $_REQUEST['DEBUG'] == 1) {
            echo __FILE__.' Line:'.__LINE__.' Cookie:'.json_encode($_COOKIE);
        }
		$this->response->setOutput($this->load->view('common/login', $data));
	}

	protected function validate() {
        /**
         * @see User::login()
         */
		if (!isset($this->request->post['username']) || !isset($this->request->post['password']) || !$this->user->login($this->request->post['username'], html_entity_decode($this->request->post['password'], ENT_QUOTES, 'UTF-8'))) {
			$this->error['warning'] = $this->language->get('error_login');
		}

		return !$this->error;
	}
}
