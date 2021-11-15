<?php

use App\Helper\ModuleHelper;
use Framework\Exception\Http\NotFoundException;
use Framework\Exception\Http\UnauthorizedException;

class ControllerErrorNotFound extends Controller {
	public function index($exception = null) {
	    if ($exception) {
	        if ($exception instanceof UnauthorizedException) {
	            $this->url->remember();
	            return $this->redirect(ModuleHelper::isInAdmin() ? 'common/login' : 'account/login');
            }
	        $throw = true;
	        if ($exception instanceof NotFoundException) {
                $throw = false;
            }
	        if ($throw) {
                // 存在异常时直接抛出，会由 ErrorHandler 自动根据环境处理错误显示
                throw $exception;
            }
        }

		$this->load->language('error/not_found');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		if (isset($this->request->get['route'])) {
			$url_data = $this->request->get;

			unset($url_data['_route_']);

			$route = $url_data['route'];

			unset($url_data['route']);

            $page_type = 'default';
			if(isset($url_data['type'])){
                $page_type = $url_data['type'];

                $data['page_type'] = $page_type;

                unset($url_data['type']);
            }

			$url = '';

			if ($url_data) {
				$url = '&' . urldecode(http_build_query($url_data, '', '&'));
			}

			if($page_type == 'seller_profile'){
                $data['breadcrumbs'][] = array(
                    'text' => $this->language->get('heading_title_seller_profile'),
                    'href' => $this->url->link($route, $url, $this->request->server['HTTPS'])
                );
            }else{
                $data['breadcrumbs'][] = array(
                    'text' => $this->language->get('heading_title'),
                    'href' => $this->url->link($route, $url, $this->request->server['HTTPS'])
                );
            }
		}

		$data['continue'] = $this->url->link('common/home');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setStatusCode(404);

		return $this->render('error/not_found', $data);
	}
}
