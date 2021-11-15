<?php

/**
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelToolImage $model_tool_image
 */
class ControllerCustomerpartnerAutocomplete extends Controller {

	private $error = array();

	public function manufacturer() {
        $json = array();

        $this->load->model('account/customerpartner');
        // 登录用户ID
        $customer_id = $this->customer->getId();
        $data = array(
            'customer_id' => $customer_id,
            'filter_name' => $this->request->get['filter_name'] ?? '',
            'start' => 0,
            'limit' => 20
        );

        /** @var ModelAccountCustomerpartner $mac */
        $mac = $this->model_account_customerpartner;
        $results = $mac->getManufacturers($data);

        foreach ($results as $result) {
            $json[] = array(
                'manufacturer_id' => $result['manufacturer_id'],
                'name' => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8'))
            );
        }

        $sort_order = array();

        foreach ($json as $key => $value) {
            $sort_order[$key] = $value['name'];
        }

        array_multisort($sort_order, SORT_ASC, $json);

        $this->response->setOutput(json_encode($json));
	}

	public function category() {
		$json = array();

		if (isset($this->request->get['filter_name'])) {
			$this->load->model('account/customerpartner');

			$data = array(
				'filter_name' => $this->request->get['filter_name'],
				'start'       => 0,
				'limit'       => 20
			);

			$results = $this->model_account_customerpartner->getCategories($data);

			foreach ($results as $result) {
				$json[] = array(
					'category_id' => $result['category_id'],
					'name'        => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8'))
				);
			}
		}

		$sort_order = array();

		foreach ($json as $key => $value) {
			$sort_order[$key] = $value['name'];
		}

		array_multisort($sort_order, SORT_ASC, $json);

		$this->response->setOutput(json_encode($json));
	}

	public function filter() {
		$json = array();

		if (isset($this->request->get['filter_name'])) {
			$this->load->model('account/customerpartner');

			$data = array(
				'filter_name' => $this->request->get['filter_name'],
				'start'       => 0,
				'limit'       => 20
			);

			$filters = $this->model_account_customerpartner->getFilters($data);

			foreach ($filters as $filter) {
				$json[] = array(
					'filter_id' => $filter['filter_id'],
					'name'      => strip_tags(html_entity_decode($filter['group'] . ' &gt; ' . $filter['name'], ENT_QUOTES, 'UTF-8'))
				);
			}
		}

		$sort_order = array();

		foreach ($json as $key => $value) {
			$sort_order[$key] = $value['name'];
		}

		array_multisort($sort_order, SORT_ASC, $json);

		$this->response->setOutput(json_encode($json));
	}

	public function product() {
		$json = array();

		if (isset($this->request->get['filter_name']) || isset($this->request->get['filter_mpn'])|| isset($this->request->get['filter_sku']) || isset($this->request->get['filter_category_id'])) {
			$this->load->model('account/customerpartner');
			$this->load->model('catalog/product');

			if (isset($this->request->get['filter_name'])) {
				$filter_name = $this->request->get['filter_name'];
			} else {
				$filter_name = '';
			}

			if (isset($this->request->get['filter_mpn'])) {
				$filter_mpn = $this->request->get['filter_mpn'];
			} else {
				$filter_mpn = '';
			}

            if (isset($this->request->get['filter_sku'])) {
                $filter_sku = $this->request->get['filter_sku'];
            } else {
                $filter_sku = '';
            }

            if (isset($this->request->get['filter_model'])) {
                $filter_model = $this->request->get['filter_model'];
            } else {
                $filter_model = '';
            }

			if (isset($this->request->get['limit'])) {
				$limit = $this->request->get['limit'];
			} else {
				$limit = 20;
			}

			$data = array(
				'filter_name'  => $filter_name,
                'filter_model' => $filter_model,
				'start'        => 0,
				'limit'        => $limit,
				'filter_mpn' => $filter_mpn,
                'filter_sku' => $filter_sku
			);

			$results = $this->model_account_customerpartner->getProductsSeller($data);
			foreach ($results as $result) {
			    if(!$result['product_id']){
			        continue;
                }
				$option_data = array();

				$product_options = $this->model_catalog_product->getProductOptions($result['product_id']);

				foreach ($product_options as $product_option) {
					$option_info = $this->model_account_customerpartner->getOption($product_option['option_id']);

					if ($option_info) {
						$product_option_value_data = array();

						foreach ($product_option['product_option_value'] as $product_option_value) {
							$option_value_info = $this->model_account_customerpartner->getOptionValue($product_option_value['option_value_id']);

							if ($option_value_info) {
								$product_option_value_data[] = array(
									'product_option_value_id' => $product_option_value['product_option_value_id'],
									'option_value_id'         => $product_option_value['option_value_id'],
									'name'                    => $option_value_info['name'],
									'price'                   => (float)$product_option_value['price'] ? $this->currency->format($product_option_value['price'], $this->config->get('config_currency')) : false,
									'price_prefix'            => $product_option_value['price_prefix']
								);
							}
						}

						$option_data[] = array(
							'product_option_id'    => $product_option['product_option_id'],
							'product_option_value' => $product_option_value_data,
							'option_id'            => $product_option['option_id'],
							'name'                 => $option_info['name'],
							'type'                 => $option_info['type'],
							'value'                => $product_option['value'],
							'required'             => $product_option['required']
						);
					}
				}

				$json[] = array(
					'product_id' => $result['product_id'],
					'name'       => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
					'model'      => $result['model'],
					'option'     => $option_data,
					'price'      => $result['price'],
					'mpn'		  =>$result['mpn'],
                    'sku'		  =>$result['sku'],
					'brand'	  =>  $result['brand'] ?? '',
				);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

    public function productInfo()
    {
        $filterData['filter_name'] = $this->request->get['filter_name'] ?? '';
        $customerID = $this->customer->getId();
        $this->load->model('account/customerpartner');
        $results = $this->model_account_customerpartner->getProductsBySeller($customerID,$filterData);
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($results));
	}

	public function attribute() {
		$json = array();

		if (isset($this->request->get['filter_name'])) {
			$this->load->model('account/customerpartner');

			$data = array(
				'filter_name' => $this->request->get['filter_name'],
				'start'       => 0,
				'limit'       => 20
			);

			$json = array();

			$results = $this->model_account_customerpartner->getAttributes($data);

			foreach ($results as $result) {
				$json[] = array(
					'attribute_id'    => $result['attribute_id'],
					'name'            => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
					'attribute_group' => $result['attribute_group']
				);
			}
		}

		$sort_order = array();

		foreach ($json as $key => $value) {
			$sort_order[$key] = $value['name'];
		}

		array_multisort($sort_order, SORT_ASC, $json);

		$this->response->setOutput(json_encode($json));
	}

	public function option() {
		$json = array();

		if (isset($this->request->get['filter_name'])) {

			$this->load->language('account/customerpartner/addproduct');

			$this->load->model('account/customerpartner');

			$this->load->model('tool/image');

			$data = array(
				'filter_name' => $this->request->get['filter_name'],
				'start'       => 0,
				'limit'       => 20
			);

			$options = $this->model_account_customerpartner->getOptions($data);

			foreach ($options as $option) {
				$option_value_data = array();

				if ($option['type'] == 'select' || $option['type'] == 'radio' || $option['type'] == 'checkbox' || $option['type'] == 'image') {
					$option_values = $this->model_account_customerpartner->getOptionValues($option['option_id']);

					foreach ($option_values as $option_value) {
						if ($option_value['image'] && file_exists(DIR_IMAGE . $option_value['image'])) {
							$image = $this->model_tool_image->resize($option_value['image'], 50, 50);
						} else {
							$image = '';
						}

						$option_value_data[] = array(
							'option_value_id' => $option_value['option_value_id'],
							'name'            => html_entity_decode($option_value['name'], ENT_QUOTES, 'UTF-8'),
							'image'           => $image
						);
					}

					$sort_order = array();

					foreach ($option_value_data as $key => $value) {
						$sort_order[$key] = $value['name'];
					}

					array_multisort($sort_order, SORT_ASC, $option_value_data);
				}

				$type = '';

				if ($option['type'] == 'select' || $option['type'] == 'radio' || $option['type'] == 'checkbox' || $option['type'] == 'image') {
					$type = $this->language->get('text_choose');
				}

				if ($option['type'] == 'text' || $option['type'] == 'textarea') {
					$type = $this->language->get('text_input');
				}

				if ($option['type'] == 'file') {
					$type = $this->language->get('text_file');
				}

				if ($option['type'] == 'date' || $option['type'] == 'datetime' || $option['type'] == 'time') {
					$type = $this->language->get('text_date');
				}

				$json[] = array(
					'option_id'    => $option['option_id'],
					'name'         => strip_tags(html_entity_decode($option['name'], ENT_QUOTES, 'UTF-8')),
					'category'     => $type,
					'type'         => $option['type'],
					'option_value' => $option_value_data
				);
			}
		}

		$sort_order = array();

		foreach ($json as $key => $value) {
			$sort_order[$key] = $value['name'];
		}

		array_multisort($sort_order, SORT_ASC, $json);

		$this->response->setOutput(json_encode($json));
	}

	public function download() {
		$json = array();

		if (isset($this->request->get['filter_name'])) {
			$this->load->model('account/customerpartner');

			$data = array(
				'filter_name' => $this->request->get['filter_name'],
				'start'       => 0,
				'limit'       => 20
			);

			$results = $this->model_account_customerpartner->getDownloads($data);

			foreach ($results as $result) {
				$json[] = array(
					'download_id' => $result['download_id'],
					'name'        => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8'))
				);
			}
		}

		$sort_order = array();

		foreach ($json as $key => $value) {
			$sort_order[$key] = $value['name'];
		}

		array_multisort($sort_order, SORT_ASC, $json);

		$this->response->setOutput(json_encode($json));
	}

	public function categoryAttribute() {
		$json = array();

		if (isset($this->request->get['category_id']) && $this->request->get['category_id']) {
			$this->load->model('account/customerpartner');

			$results = $this->model_account_customerpartner->getCategoryAttribute($this->request->get['category_id']);

			if ($results) {
				foreach ($results as $result) {
					$json[] = array(
						'attribute_id'    => $result['attribute_id'],
						'name'            => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
						'attribute_group' => $result['attribute_group']
					);
				}
			}
		}

		$sort_order = array();

		foreach ($json as $key => $value) {
			$sort_order[$key] = $value['name'];
		}

		array_multisort($sort_order, SORT_ASC, $json);

		$this->response->setOutput(json_encode($json));
	}
}
?>
