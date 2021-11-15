<?php

/**
 * @property ModelCatalogCategory $model_catalog_category
 * @property ModelCatalogProduct $model_catalog_product
 */
class ControllerExtensionModuleCategory extends Controller {
    public function index() {
        $this->load->language('extension/module/category');

        if (isset($this->request->get['path'])) {
            $parts = explode('_', (string)$this->request->get['path']);
        } else {
            $parts = array();
        }

        if (isset($parts[0])) {
            $data['category_id'] = $parts[0];
        } else {
            $data['category_id'] = 0;
        }

        if (isset($parts[1])) {
            $data['child_id'] = $parts[1];
        } else {
            $data['child_id'] = 0;
        }

        isset($this->request->get['min_price']) && $data['min_price'] = $this->request->get['min_price'];
        isset($this->request->get['max_price']) && $data['max_price'] = $this->request->get['max_price'];
        isset($this->request->get['min_quantity']) && $data['min_quantity'] = $this->request->get['min_quantity'];
        isset($this->request->get['max_quantity']) && $data['max_quantity'] = $this->request->get['max_quantity'];

        $this->load->model('catalog/category');

        $this->load->model('catalog/product');

        $data['categories'] = array();

        $categories = $this->model_catalog_category->getCategories(0);

        foreach ($categories as $category) {
            $children_data = array();

            if ($category['category_id'] == $data['category_id']) {
                $children = $this->model_catalog_category->getCategories($category['category_id']);

                foreach($children as $child) {
                    $filter_data = array('filter_category_id' => $child['category_id'], 'filter_sub_category' => true);

                    $children_data[] = array(
                        'category_id' => $child['category_id'],
                        'name' => $child['name'] . ($this->config->get('config_product_count') ? ' (' . $this->model_catalog_product->getTotalProducts($filter_data) . ')' : ''),
                        'href' => $this->url->link('product/category', 'path=' . $category['category_id'] . '_' . $child['category_id'])
                    );
                }
            }

            $filter_data = array(
                'filter_category_id'  => $category['category_id'],
                'filter_sub_category' => true
            );

            $data['categories'][] = array(
                'category_id' => $category['category_id'],
                'name'        => $category['name'] . ($this->config->get('config_product_count') ? ' (' . $this->model_catalog_product->getTotalProducts($filter_data) . ')' : ''),
                'children'    => $children_data,
                'href'        => $this->url->link('product/category', 'path=' . $category['category_id'])
            );
        }

        return $this->load->view('extension/module/category', $data);
    }

    /**
     * 分类商品列表
     * @return array
     * @throws Exception
     * @author zhousuyang
     */
    public function getListShort()
    {
        $this->load->language('extension/module/category');

        if (isset($this->request->get['path'])) {
            $parts = explode('_', (string)$this->request->get['path']);
        } else {
            $parts = array();
        }

        if (isset($parts[0])) {
            $data['category_id'] = $parts[0];
        } else {
            $data['category_id'] = 0;
        }

        if (isset($parts[1])) {
            $data['child_id'] = $parts[1];
        } else {
            $data['child_id'] = 0;
        }

        isset($this->request->get['min_price']) && $data['min_price'] = $this->request->get['min_price'];
        isset($this->request->get['max_price']) && $data['max_price'] = $this->request->get['max_price'];
        isset($this->request->get['min_quantity']) && $data['min_quantity'] = $this->request->get['min_quantity'];
        isset($this->request->get['max_quantity']) && $data['max_quantity'] = $this->request->get['max_quantity'];

        $this->load->model('catalog/category');

        $this->load->model('catalog/product');

        $data['categories'] = array();

        $categories = $this->model_catalog_category->getCategories(0);

        foreach ($categories as $category) {
            $children_data = array();

            if ($category['category_id'] == $data['category_id']) {
                $children = $this->model_catalog_category->getCategories($category['category_id']);

                foreach($children as $child) {
                    $filter_data = array('filter_category_id' => $child['category_id'], 'filter_sub_category' => true);
                    $pagecss = '';
                    if ($child['category_id'] == $data['child_id']) {
                        $pagecss = 'mp-active';
                    }
                    $children_data[] = array(
                        'category_id' => $child['category_id'],
                        'name' => $child['name'] . ($this->config->get('config_product_count') ? ' (' . $this->model_catalog_product->getTotalProducts($filter_data) . ')' : ''),
                        'href' => $this->url->link('product/category', 'path=' . $category['category_id'] . '_' . $child['category_id']),
                        'pagecss'=>$pagecss
                    );
                }
            }

            $filter_data = array(
                'filter_category_id'  => $category['category_id'],
                'filter_sub_category' => true
            );
            $pagecss = '';
            if ($category['category_id'] == $data['category_id']) {
                $pagecss = 'mp-active';
            }
            $data['categories'][] = array(
                'category_id' => $category['category_id'],
                'name'        => $category['name'] . ($this->config->get('config_product_count') ? ' (' . $this->model_catalog_product->getTotalProducts($filter_data) . ')' : ''),
                'children'    => $children_data,
                'href'        => $this->url->link('product/category', 'path=' . $category['category_id']),
                'pagecss'     => $pagecss
            );
        }

        return $data['categories'];
    }
}