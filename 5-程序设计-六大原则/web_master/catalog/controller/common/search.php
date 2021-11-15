<?php

use App\Helper\RouteHelper;

/**
 * Class ControllerCommonSearch
 * @property ModelCatalogSearch $model_catalog_search
 */
class ControllerCommonSearch extends Controller
{
    public function index()
    {
        $this->load->language('common/search');
        $data['text_search'] = $this->language->get('text_search');
        $data['search'] = $this->request->get('search', '');
        $data['is_seller_store'] = RouteHelper::isCurrentMatchGroup('storeHome');
        $data['seller_id'] = $data['is_seller_store'] ? request('id') : null;
        $data['hotWords'] = [];

        if (!$data['is_seller_store']) {
            // 非店铺主页内才需要显示热词
            $this->load->model('catalog/search');
            $data['hotWords'] = $this->model_catalog_search->getHotWordsList();
        }

        return $this->load->view('common/search', $data);
    }
}
