<?php

use App\Components\Storage\StorageCloud;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Repositories\Common\HomeRepository;

/**
 * @property ModelDesignBanner $model_design_banner
 * @property ModelLocalisationCountry $model_localisation_country
 * @property ModelLocalisationLanguage $model_localisation_language
 * @property ModelToolImage $model_tool_image
 */
class ControllerDesignBanner extends Controller {
	private $error = array();

    /**
     * @throws Exception
     */
	public function index() {
		$this->load->language('design/banner');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/banner');

		$this->getList();
	}

    /**
     * @return RedirectResponse
     * @throws Exception
     */
	public function add() {
		$this->load->language('design/banner');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/banner');

		if ((request()->isMethod('POST')) && $this->validateForm()) {
			$this->model_design_banner->addBanner($this->request->post);

			session()->set('success', $this->language->get('text_success'));

			$url = '';

			if (request()->query->has('sort')) {
				$url .= '&sort=' . request()->query->get('sort');
			}

			if (request()->query->has('order')) {
				$url .= '&order=' . request()->query->get('order');
			}

			if (request()->query->has('page')) {
				$url .= '&page=' . request()->query->get('page');
			}

            return response()->redirectTo($this->url->link('design/banner', 'user_token=' . session()->get('user_token') . $url, true));
		}

		$this->getForm();
	}

    /**
     * @return RedirectResponse
     * @throws Exception
     */
	public function edit() {

		$this->load->language('design/banner');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('design/banner');

		if ((request()->isMethod('POST')) && $this->validateForm()) {
			$this->model_design_banner->editBanner($this->request->get['banner_id'], $this->request->post);
			//banner 移除缓存
            $cacheKey = HomeRepository::getBannerCacheKey();
            if(request('banner_id') == 7){
                foreach($cacheKey as $key => $value){
                   cache()->delete($value);
                }
            }

			session()->set('success', $this->language->get('text_success'));

			$url = '';

			if (request()->query->has('sort')) {
				$url .= '&sort=' . request()->query->get('sort');
			}

			if (request()->query->has('order')) {
				$url .= '&order=' . request()->query->get('order');
			}

			if (request()->query->has('page')) {
				$url .= '&page=' . request()->query->get('page');
			}

			$this->response->redirect($this->url->link('design/banner', 'user_token=' . session('user_token') . $url, true));
		}

		$this->getForm();
	}

    /**
     * @return RedirectResponse
     * @throws Exception
     */
	public function delete() {
		$this->load->language('design/banner');

		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('design/banner');
		if (request()->input->has('selected') && $this->validateDelete()) {
			foreach (request()->input->get('selected') as $banner_id) {
				$this->model_design_banner->deleteBanner($banner_id);
			}

			session()->set('success', $this->language->get('text_success'));

			$url = '';

			if (request()->query->has('sort')) {
				$url .= '&sort=' . request()->query->get('sort');
			}

			if (request()->query->has('order')) {
				$url .= '&order=' . request()->query->get('order');
			}

			if (request()->query->has('page')) {
				$url .= '&page=' . request()->query->get('page');
			}

			$this->response->redirect($this->url->link('design/banner', 'user_token=' . session('user_token') . $url, true));
		}

		$this->getList();
	}

	protected function getList() {
        $sort = request()->get('sort', 'name');
        $order = request()->get('order', 'ASC');
        $page = (int)request()->get('page', 1);
        $limit = (int)request()->get('page_limit', $this->config->get('config_limit_admin'));

		$url = '';

		if (request()->query->has('sort')) {
			$url .= '&sort=' . request()->query->get('sort');
		}

		if (request()->query->has('order')) {
			$url .= '&order=' . request()->query->get('order');
		}

		if (request()->query->has('page')) {
			$url .= '&page=' . request()->query->get('page');
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('design/banner', 'user_token=' . session('user_token') . $url, true)
		);

		$data['add'] = $this->url->link('design/banner/add', 'user_token=' . session('user_token') . $url, true);
		$data['delete'] = $this->url->link('design/banner/delete', 'user_token=' . session('user_token') . $url, true);

		$data['banners'] = array();

		$filter_data = array(
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $limit,
			'limit' => $limit
		);

		$banner_total = $this->model_design_banner->getTotalBanners();

		$results = $this->model_design_banner->getBanners($filter_data);

		foreach ($results as $result) {
			$data['banners'][] = array(
				'banner_id' => $result['banner_id'],
				'name'      => $result['name'],
				'status'    => ($result['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled')),
				'edit'      => $this->url->link('design/banner/edit', 'user_token=' . session('user_token') . '&banner_id=' . $result['banner_id'] . $url, true)
			);
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = session('success');

            session()->remove('success');
		} else {
			$data['success'] = '';
		}

        $data['selected'] = (array)request()->input->get('selected', array());

		$url = '';

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (request()->query->has('page')) {
			$url .= '&page=' . request()->query->get('page');
		}

		$data['sort_name'] = $this->url->link('design/banner', 'user_token=' . session('user_token') . '&sort=name' . $url, true);
		$data['sort_status'] = $this->url->link('design/banner', 'user_token=' . session('user_token') . '&sort=status' . $url, true);

		$url = '';

		if (request()->query->has('sort')) {
			$url .= '&sort=' . request()->query->get('sort');
		}

		if (request()->query->has('order')) {
			$url .= '&order=' . request()->query->get('order');
		}

		$pagination = new Pagination();
		$pagination->total = $banner_total;
		$pagination->page = $page;
        $pagination->limit = $limit;
		$pagination->url = $this->url->link('design/banner', 'user_token=' . session('user_token') . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($banner_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($banner_total - $this->config->get('config_limit_admin'))) ? $banner_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $banner_total, ceil($banner_total / $this->config->get('config_limit_admin')));

		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('design/banner_list', $data));
	}

    /**
     * @throws Exception
     */
	protected function getForm() {

		$data['text_form'] = !request()->query->get('banner_id') ? $this->language->get('text_add') : $this->language->get('text_edit');

        $data['error_warning'] = '';
        if (!empty($this->error)) {
            if (isset($this->error['warning'])) {
                $data['error_warning'] = $this->error['warning'];
            }else{
                $data['error_warning'] = $this->language->get('error_warning');
            }
        }

		if (isset($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		} else {
			$data['error_name'] = '';
		}

		if (isset($this->error['banner_image'])) {
			$data['error_banner_image'] = $this->error['banner_image'];
		} else {
			$data['error_banner_image'] = array();
		}
		if (isset($this->error['banner_link'])) {
			$data['error_banner_link'] = $this->error['banner_link'];
		} else {
			$data['error_banner_link'] = array();
		}
		if (isset($this->error['banner_title'])) {
			$data['error_banner_title'] = $this->error['banner_title'];
		} else {
			$data['error_banner_title'] = array();
		}
		if (isset($this->error['banner_sort_order'])) {
			$data['error_banner_sort_order'] = $this->error['banner_sort_order'];
		} else {
			$data['error_banner_sort_order'] = array();
		}

		$url = '';

		if (request()->query->has('sort')) {
			$url .= '&sort=' . request()->query->get('sort');
		}

		if (request()->query->has('order')) {
			$url .= '&order=' . request()->query->get('order');
		}

		if (request()->query->has('page')) {
			$url .= '&page=' . request()->query->get('page');
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('design/banner', 'user_token=' . session('user_token') . $url, true)
		);

		if (!isset($this->request->get['banner_id'])) {
			$data['action'] = $this->url->link('design/banner/add', 'user_token=' . session('user_token') . $url, true);
		} else {
			$data['action'] = $this->url->link('design/banner/edit', 'user_token=' . session('user_token') . '&banner_id=' . request()->query->getInt('banner_id') . $url, true);
		}

		$data['cancel'] = $this->url->link('design/banner', 'user_token=' . session('user_token') . $url, true);

		if (isset($this->request->get['banner_id']) && (!request()->isMethod('POST'))) {
			$banner_info = $this->model_design_banner->getBanner($this->request->get['banner_id']);
		}

		$data['user_token'] = session('user_token');

		if (request()->input->has('name')) {
			$data['name'] = request()->input->get('name');
		} elseif (!empty($banner_info)) {
			$data['name'] = $banner_info['name'];
		} else {
			$data['name'] = '';
		}

		if (request()->input->has('status')) {
			$data['status'] = request()->input->get('status');
		} elseif (!empty($banner_info)) {
			$data['status'] = $banner_info['status'];
		} else {
			$data['status'] = true;
		}

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		$this->load->model('tool/image');

		if (request()->input->has('banner_image')) {
			$banner_images = request()->input->get('banner_image');
		} elseif (request()->query->has('banner_id')) {
		    $param=array();
		    foreach ($_GET as $k=>$v){
		        if(strpos($k,'filter_')===0){
		            $data[$k] = $v;
                    $param[$k]=$v;
                }
            }
			$banner_images = $this->model_design_banner->getBannerImages(request()->query->getInt('banner_id'),$param);
		} else {
			$banner_images = array();
		}

		$data['banner_images'] = array();

		foreach ($banner_images as $key => $value) {
			foreach ($value as $banner_image) {
				if ($banner_image['image']) {
					$image = $banner_image['image'];
					$thumb = $banner_image['image'];
				} else {
					$image = '';
					$thumb = 'no_image.png';
				}

                $data['banner_images'][$key][] = array(
                    'banner_image_id' => $banner_image['banner_image_id'],
                    'title' => $banner_image['title'],
                    'link' => $banner_image['link'],
                    'image' => $image,
                    'thumb' => StorageCloud::image()->getUrl($thumb, ['w' => 100, 'h' => 100]),
                    'sort_order' => $banner_image['sort_order'],
                    'status' => $banner_image['status'],
                    'country_id' => $banner_image['country_id']
                );
			}
		}

        $this->load->model('localisation/country');

        $data['countrys'] = $this->model_localisation_country->getSupportCountrys();

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);
		$current = $this->url->link('design/banner/edit', '', true).'&user_token=' . session('user_token');
		$remove_url = $this->url->link('design/banner/remove', '', true).'&user_token=' . session('user_token');
		if(isset($_GET['banner_id'])){
            $current .= '&banner_id='.request()->query->getInt('banner_id');
        }
		$data['current'] = $current;
		$data['remove_url'] = $remove_url;
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('design/banner_form', $data));
	}

    /**
     * @throws Exception
     */
	public function remove() {
        $this->load->model('design/banner');
        $this->model_design_banner->deleteBannerImage(request()->query->getInt('banner_image_id'));
    }

    /**
     * @return bool
     */
	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'design/banner')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ((utf8_strlen(request()->input->get('name')) < 3) || (utf8_strlen(request()->input->get('name')) > 64)) {
			$this->error['name'] = $this->language->get('error_name');
		}

		if (request()->input->has('banner_image')) {
			foreach (request()->input->get('banner_image') as $language_id => $value) {
				foreach ($value as $banner_image_id => $banner_image) {
                    if (!empty($banner_image['sort_order'])) {
                        $sort_order = (int)$banner_image['sort_order'];
                        if ($sort_order < 1 || $sort_order > 999) {
                            $this->error['banner_sort_order'][$language_id][$banner_image_id] = $this->language->get('error_sort_order');
                        }
                    }
				    if(empty($banner_image['link'])){
						$this->error['banner_link'][$language_id][$banner_image_id] = $this->language->get('entry_link').$this->language->get('error_empty');
                    }
				    if(empty($banner_image['image'])){
						$this->error['banner_image'][$language_id][$banner_image_id] = $this->language->get('entry_image').$this->language->get('error_empty');
                    }
				    if(empty($banner_image['country_id'])){
						$this->error['banner_country'][$language_id][$banner_image_id] = $this->language->get('entry_country').$this->language->get('error_empty');
                    }
					if ((utf8_strlen($banner_image['title']) < 2) || (utf8_strlen($banner_image['title']) > 64)) {
						$this->error['banner_title'][$language_id][$banner_image_id] = $this->language->get('error_title');
					}
				}
			}
		}

		return !$this->error;
	}

    /**
     * @return bool
     */
	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'design/banner')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
