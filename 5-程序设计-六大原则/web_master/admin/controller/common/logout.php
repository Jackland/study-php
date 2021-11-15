<?php
class ControllerCommonLogout extends Controller {
	public function index() {
		$this->user->logout();

		$this->session->remove('user_token');

		$this->response->redirect($this->url->link('common/login', '', true));
	}
}