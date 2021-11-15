<?php

/**
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountManufacturer $model_account_manufacturer
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelLocalisationLanguage $model_localisation_language
 * @property ModelSettingStore $model_setting_store
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountManufacturer extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('account/manufacturer');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('account/manufacturer');

		$this->getList();
	}

	public function add() {
		$this->load->language('account/manufacturer');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('account/manufacturer');



		if ((request()->isMethod('POST')) && $this->validateForm()) {
            $files = $this->request->files;
            // 运行时间
            $run_id = time();
            // 登录用户ID
            $customer_id = $this->customer->getId();

            $this->load->model('account/customerpartner');
            //判断登录用户为buyer还是seller
            $data['chkIsPartner'] = $this->model_account_customerpartner->chkIsPartner();
            if(!file_exists(DIR_BRAND)){
                mkdir(DIR_BRAND,0777,true);
            }
            //原文件名
            $file_name = $files['brandImage']['name'];
            //上传文件名
            $splitStr = explode('.', $file_name);
            $file_type = $splitStr[count($splitStr) - 1];
            $file_name_new = 'brand/'.$run_id.'_1.'.$file_type;
            $file_path = DIR_BRAND.'/'.$run_id.'_1.'.$file_type;
            move_uploaded_file($files['brandImage']['tmp_name'], $file_path);

            $tableData = array(
                "customer_id" => $customer_id,
                "brand_name" =>$this->request->request['name'],
                "brand_file_path" => $file_name_new,
                "can_brand" => 0,
                "is_partner" => $data['chkIsPartner']
            );
			$this->model_account_manufacturer->addManufacturer($tableData);

			session()->set('success', $this->language->get('text_add_success'));

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('account/manufacturer',  $url, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('account/manufacturer');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('account/manufacturer');

		if ((request()->isMethod('POST')) && $this->validateFormEdit()) {

            $files = $this->request->files;
            // 运行时间
            $run_id = time();
            // 登录用户ID
            $customer_id = $this->customer->getId();

            if($files['brandImage']['error']=='4'){
                $tableData = array(
                    "name" =>$this->request->request['name'],
                );
                $this->model_account_manufacturer->editManufacturer($this->request->get['manufacturer_id'], $tableData);
            }else{
                $this->load->model('account/customerpartner');
                //判断登录用户为buyer还是seller
                $data['chkIsPartner'] = $this->model_account_customerpartner->chkIsPartner();
                if(!file_exists(DIR_BRAND)){
                    mkdir(DIR_BRAND,0777,true);
                }
                //原文件名
                $file_name = $files['brandImage']['name'];
                //上传文件名
                $splitStr = explode('.', $file_name);
                $file_type = $splitStr[count($splitStr) - 1];
                $file_name_new = 'brand/'.$run_id.'_1.'.$file_type;
                $file_path = DIR_BRAND.'/'.$run_id.'_1.'.$file_type;
                move_uploaded_file($files['brandImage']['tmp_name'], $file_path);

                $tableData = array(
                    "customer_id" => $customer_id,
                    "name" =>$this->request->request['name'],
                    "image" => $file_name_new,
                    "can_brand" => 0,
                    "is_partner" => $data['chkIsPartner']
                );
                $this->model_account_manufacturer->editManufacturer($this->request->get['manufacturer_id'], $tableData);
            }



			session()->set('success', $this->language->get('text_edit_success'));

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('account/manufacturer',  $url, true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('account/manufacturer');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('account/manufacturer');

        if(isset($this->request->get['manufacturer_id'])){
            $manufacturerId = $this->request->get['manufacturer_id'];
            $this->model_account_manufacturer->deleteManufacturer($manufacturerId);
            session()->set('success', $this->language->get('text_delete_single_success'));
            $url = '';

            if (isset($this->request->get['sort'])) {
                $url .= '&sort=' . $this->request->get['sort'];
            }

            if (isset($this->request->get['order'])) {
                $url .= '&order=' . $this->request->get['order'];
            }

            if (isset($this->request->get['page'])) {
                $url .= '&page=' . $this->request->get['page'];
            }

            $this->response->redirect($this->url->link('account/manufacturer',  $url, true));

            $this->getList();
        }

//		if (isset($this->request->post['selected']) && $this->validateDelete()) {
        /*if (isset($this->request->post['selected'])) {
			foreach ($this->request->post['selected'] as $manufacturer_id) {
				$this->model_account_manufacturer->deleteManufacturer($manufacturer_id);
			}

            $delete_count = count($this->request->post['selected'],COUNT_NORMAL);
            if($delete_count > 1){
                session()->set('success', $this->language->get('text_delete_multi_success'));
            }else{
                session()->set('success', $this->language->get('text_delete_single_success'));
            }

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('account/manufacturer', $url, true));
		}

		$this->getList();*/
	}

    /*public function showDetail(){

        $this->load->language('account/manufacturer');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('account/manufacturer');

        $manufacturerId = $this->request->get['manufacturer_id'];

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $url = '';

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', '', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('account/manufacturer', $url, true)
        );

        $data['cancel'] = $this->url->link('account/manufacturer', $url, true);

        $filter_data = array(
            'customer_id' => $this->customer->getId(),
            'start' => ($page - 1) * $this->config->get('config_limit_admin'),
            'limit' => $this->config->get('config_limit_admin'),
            'manufacturerId' =>$manufacturerId
        );

        $product_total = $this->model_account_manufacturer->getTotalManufacturerProductInfo($manufacturerId);

        $results = $this->model_account_manufacturer->getManufacturerProductInfo($filter_data);

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

        $pagination = new Pagination();
        $pagination->total = $product_total;
        $pagination->page = $page;
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->url = $this->url->link('account/manufacturer/showDetail', '&page={page}&manufacturer_id='.$manufacturerId, true);

        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($product_total - $this->config->get('config_limit_admin'))) ? $product_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $product_total, ceil($product_total / $this->config->get('config_limit_admin')));

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['footer'] = $this->load->controller('common/footer');

        foreach ($results AS $result){
            $this->load->model('tool/image');
            if (!(count(explode('http', $result['image'])) > 1)) {
                if (is_file(DIR_IMAGE . $result['image'])) {
                    $image = $this->model_tool_image->resize($result['image']);
                } else {
                    $image = $this->model_tool_image->resize('no_image.png');
                }
                $result['image'] = $image;
            }
            $data['products'][] = array(
                'sku' => $result['sku'],
                'mpn' => $result['mpn'],
                'image'=>$result['image'],
                'name'=>$result['name'],
                'status'=>$result['status']
            );
        }

        $this->response->setOutput($this->load->view('account/customerpartner/manufacturer_product', $data));
    }*/

	protected function getList() {
		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'name';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

//		$data['breadcrumbs'][] = array(
//			'text' => $this->language->get('text_account'),
//			'href' => $this->url->link('account/account')
//		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('account/manufacturer',  $url, true)
		);

		$data['add'] = $this->url->link('account/manufacturer/add',  $url, true);
		$data['delete'] = $this->url->link('account/manufacturer/delete',   $url, true);

		$data['manufacturers'] = array();
        $page_limit = get_value_or_default($this->request->request,'page_limit',$this->config->get('config_limit_admin'));
		$filter_data = array(
		    'customer_id' => $this->customer->getId(),
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $page_limit,
			'limit' => $page_limit
		);

		$manufacturer_total = $this->model_account_manufacturer->getTotalManufacturers($this->customer->getId());

		$results = $this->model_account_manufacturer->getManufacturers($filter_data);

		foreach ($results as $result) {
			$data['manufacturers'][] = array(
				'manufacturer_id' => $result['manufacturer_id'],
				'name'            => $result['name'],
				/*'sort_order'      => $result['sort_order'],*/
                /*'oem'      => $result['can_brand'],*/
                /*'total'           =>$this->model_account_manufacturer->getManufacturerProductCount($result['manufacturer_id']),*/
				'edit'            => $this->url->link('account/manufacturer/edit', '&manufacturer_id=' . $result['manufacturer_id'] . $url, true),
                'delete'            => $this->url->link('account/manufacturer/delete', '&manufacturer_id=' . $result['manufacturer_id'] . $url, true)
                /*'showDetail'     =>$this->url->link('account/manufacturer/showDetail', '&manufacturer_id=' . $result['manufacturer_id'] . $url, true)*/
			);
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

		if (isset($this->request->post['selected'])) {
			$data['selected'] = (array)$this->request->post['selected'];
		} else {
			$data['selected'] = array();
		}

		$url = '';

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['sort_name'] = $this->url->link('account/manufacturer',  '&sort=name' . $url, true);
		$data['sort_manufacturer_id'] = $this->url->link('account/manufacturer', '&sort=manufacturer_id' . $url, true);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $manufacturer_total;
		$pagination->page = $page;
		$pagination->limit = $page_limit;
		$pagination->url = $this->url->link('account/manufacturer', $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($manufacturer_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($manufacturer_total - $this->config->get('config_limit_admin'))) ? $manufacturer_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $manufacturer_total, ceil($manufacturer_total / $this->config->get('config_limit_admin')));

		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('account/manufacturer_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['manufacturer_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		} else {
			$data['error_name'] = '';
		}

		if (isset($this->error['keyword'])) {
			$data['error_keyword'] = $this->error['keyword'];
		} else {
			$data['error_keyword'] = '';
		}

        if (isset($this->error['image'])) {
            $data['error_image'] = $this->error['image'];
        } else {
            $data['error_image'] = '';
        }

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home',  true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('account/manufacturer',  $url, true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $data['text_form'],
            'href' => ''
        );
		if (!isset($this->request->get['manufacturer_id'])) {
			$data['action'] = $this->url->link('account/manufacturer/add',  $url, true);
		} else {
			$data['action'] = $this->url->link('account/manufacturer/edit','&manufacturer_id=' . $this->request->get['manufacturer_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('account/manufacturer', $url, true);

		if (isset($this->request->get['manufacturer_id'])) {
			$manufacturer_info = $this->model_account_manufacturer->getManufacturer($this->request->get['manufacturer_id']);
		}


		if (isset($this->request->post['name'])) {
			$data['name'] = $this->request->post['name'];
		} elseif (!empty($manufacturer_info)) {
			$data['name'] = $manufacturer_info['name'];
		} else {
			$data['name'] = '';
		}

		$this->load->model('setting/store');

		$data['stores'] = array();

		$data['stores'][] = array(
			'store_id' => 0,
			'name'     => $this->language->get('text_default')
		);

		$stores = $this->model_setting_store->getStores();

		foreach ($stores as $store) {
			$data['stores'][] = array(
				'store_id' => $store['store_id'],
				'name'     => $store['name']
			);
		}

		if (isset($this->request->post['manufacturer_store'])) {
			$data['manufacturer_store'] = $this->request->post['manufacturer_store'];
		} elseif (isset($this->request->get['manufacturer_id'])) {
			$data['manufacturer_store'] = $this->model_account_manufacturer->getManufacturerStores($this->request->get['manufacturer_id']);
		} else {
			$data['manufacturer_store'] = array(0);
		}

		if (isset($this->request->post['image'])) {
			$data['image'] = $this->request->post['image'];
		} elseif (!empty($manufacturer_info)) {
			$data['image'] = $manufacturer_info['image'];
		} else {
			$data['image'] = '';
		}

		$this->load->model('tool/image');

		if (isset($this->request->post['image']) && is_file(DIR_IMAGE . $this->request->post['image'])) {
			$data['thumb'] =$this->request->post['image'];
		} elseif (!empty($manufacturer_info) && is_file(DIR_IMAGE . $manufacturer_info['image'])) {
			$data['thumb'] = 'image/'.$manufacturer_info['image'];
		} else {
			$data['thumb'] = $this->model_tool_image->resize('no_image.png', 132, 102);
		}

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 132, 102);

		if (isset($this->request->post['sort_order'])) {
			$data['sort_order'] = $this->request->post['sort_order'];
		} elseif (!empty($manufacturer_info)) {
			$data['sort_order'] = $manufacturer_info['sort_order'];
		} else {
			$data['sort_order'] = '';
		}

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['manufacturer_seo_url'])) {
			$data['manufacturer_seo_url'] = $this->request->post['manufacturer_seo_url'];
		} elseif (isset($this->request->get['manufacturer_id'])) {
			$data['manufacturer_seo_url'] = $this->model_account_manufacturer->getManufacturerSeoUrls($this->request->get['manufacturer_id']);
		} else {
			$data['manufacturer_seo_url'] = array();
		}

		$data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('account/manufacturer_form', $data));
	}

	protected function validateForm() {
//		if (!$this->user->hasPermission('modify', 'account/manufacturer')) {
//			$this->error['warning'] = $this->language->get('error_permission');
//		}

		if ((utf8_strlen($this->request->post['name']) < 1) || (utf8_strlen($this->request->post['name']) > 64)) {
			$this->error['name'] = $this->language->get('error_name');
		}
            if ($this->request->files['brandImage']['error'] == '4') {
                $this->error['image'] = $this->language->get('error_image_need');
            }

//		if ($this->request->post['manufacturer_seo_url']) {
//			$this->load->model('design/seo_url');
//
//			foreach ($this->request->post['manufacturer_seo_url'] as $store_id => $language) {
//				foreach ($language as $language_id => $keyword) {
//					if (!empty($keyword)) {
//						if (count(array_keys($language, $keyword)) > 1) {
//							$this->error['keyword'][$store_id][$language_id] = $this->language->get('error_unique');
//						}
//
//						$seo_urls = $this->model_design_seo_url->getSeoUrlsByKeyword($keyword);
//
//						foreach ($seo_urls as $seo_url) {
//							if (($seo_url['store_id'] == $store_id) && (!isset($this->request->get['manufacturer_id']) || (($seo_url['query'] != 'manufacturer_id=' . $this->request->get['manufacturer_id'])))) {
//								$this->error['keyword'][$store_id][$language_id] = $this->language->get('error_keyword');
//							}
//						}
//					}
//				}
//			}
//		}

		return !$this->error;
	}

	protected function validateDelete() {
//		if (!$this->user->hasPermission('modify', 'account/manufacturer')) {
//			$this->error['warning'] = $this->language->get('error_permission');
//		}

		$this->load->model('catalog/product');

		foreach ($this->request->post['selected'] as $manufacturer_id) {
			$product_total = $this->model_catalog_product->getTotalProductsByManufacturerId($manufacturer_id);

			if ($product_total) {
				$this->error['warning'] = sprintf($this->language->get('error_product'), $product_total);
			}
		}

		return !$this->error;
	}

	public function autocomplete() {
		$json = array();

		if (isset($this->request->get['filter_name'])) {
			$this->load->model('account/manufacturer');

			$filter_data = array(
				'filter_name' => $this->request->get['filter_name'],
				'start'       => 0,
				'limit'       => 5
			);

			$results = $this->model_account_manufacturer->getManufacturers($filter_data);

			foreach ($results as $result) {
				$json[] = array(
					'manufacturer_id' => $result['manufacturer_id'],
					'name'            => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8'))
				);
			}
		}

		$sort_order = array();

		foreach ($json as $key => $value) {
			$sort_order[$key] = $value['name'];
		}

		array_multisort($sort_order, SORT_ASC, $json);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

    protected function validateFormEdit() {
//		if (!$this->user->hasPermission('modify', 'account/manufacturer')) {
//			$this->error['warning'] = $this->language->get('error_permission');
//		}

        if ((utf8_strlen($this->request->post['name']) < 1) || (utf8_strlen($this->request->post['name']) > 64)) {
            $this->error['name'] = $this->language->get('error_name');
        }

//		if ($this->request->post['manufacturer_seo_url']) {
//			$this->load->model('design/seo_url');
//
//			foreach ($this->request->post['manufacturer_seo_url'] as $store_id => $language) {
//				foreach ($language as $language_id => $keyword) {
//					if (!empty($keyword)) {
//						if (count(array_keys($language, $keyword)) > 1) {
//							$this->error['keyword'][$store_id][$language_id] = $this->language->get('error_unique');
//						}
//
//						$seo_urls = $this->model_design_seo_url->getSeoUrlsByKeyword($keyword);
//
//						foreach ($seo_urls as $seo_url) {
//							if (($seo_url['store_id'] == $store_id) && (!isset($this->request->get['manufacturer_id']) || (($seo_url['query'] != 'manufacturer_id=' . $this->request->get['manufacturer_id'])))) {
//								$this->error['keyword'][$store_id][$language_id] = $this->language->get('error_keyword');
//							}
//						}
//					}
//				}
//			}
//		}

        return !$this->error;
    }
}