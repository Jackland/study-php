<?php

use App\Logging\Logger;

/**
 * @property ModelACCOUNTAPI $model_account_api
 * */
class ControllerApiLogin extends Controller {

    const START_API_LOGIN = '----API LOGIN START----';
    const END_API_LOGIN = '----API LOGIN END----';
	public function index() {

        // java 传参 username & key
        Logger::order(static::START_API_LOGIN, 'info', [
            Logger::CONTEXT_WEB_SERVER_VARS => ['_POST', '_GET'],
        ]);

		$this->load->language('api/login');

		$json = array();

		$this->load->model('account/api');

		// Login with API Key
		$api_info = $this->model_account_api->login($this->request->post['username'], $this->request->post['key']);

		if ($api_info) {
			// TODO Check if IP is allowed
			$ip_data = [];

			$results = $this->model_account_api->getApiIps($api_info['api_id']);

			foreach ($results as $result) {
				$ip_data[] = trim($result['ip']);
			}

			if (!in_array($this->request->server['REMOTE_ADDR'], $ip_data)) {
				$json['error']['ip'] = sprintf($this->language->get('error_ip'), request()->serverBag->get('REMOTE_ADDR'));
			}

			if (!$json) {
				$json['success'] = $this->language->get('text_success');

				$session = session();
				$session->start();

				$this->model_account_api->addApiSession($api_info['api_id'], $session->getId(), request()->serverBag->get('REMOTE_ADDR'));

				$session->set('api_id', $api_info['api_id']);

				// Create Token
				$json['api_token'] = $session->getId();
			} else {
				$json['error']['key'] = $this->language->get('error_key');
			}
		}

		// 记录返回成功还是失败的
        Logger::order([static::END_API_LOGIN, 'info',
            Logger::CONTEXT_VAR_DUMPER => ['json' => $json ],
        ]);// 按照可视化形式输出
		$this->response->json($json);
	}
}
