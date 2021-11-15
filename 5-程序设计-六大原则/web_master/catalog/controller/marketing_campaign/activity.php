<?php

/**
 * Class ControllerMarketingCampaignActivity
 * Buyer 促销页面
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelExtensionModuleProductShow $model_extension_module_product_show
 * @property ModelExtensionModuleProductHome $model_extension_module_product_home
 * @property ModelMarketingCampaignActivity $model_marketing_campaign_activity
 */
class ControllerMarketingCampaignActivity extends Controller
{
    private $page_list = 16;
    private $country_map = [
        'JPN'  => 107,
        'GBR'  => 222,
        'DEU'  => 81,
        'USA'  => 223
    ];
    private $country_id_code = [
        81  => 'DEU',
        107 => 'JPN',
        222 => 'GBR',
        223 => 'USA'
    ];

    function __construct(Registry $registry)
    {
        parent::__construct($registry);

        $this->load->model('marketing_campaign/activity');
    }


    /**
     * @throws ReflectionException
     * 路由：index.php?route=marketing_campaign/activity/index
     */
    public function index()
    {
        $this->load->language('marketing_campaign/activity');
        $this->load->model('catalog/product');
        $this->load->model('extension/module/product_show');



        $id           = get_value_or_default($this->request->get, 'id', 0);
        $code         = get_value_or_default($this->request->get, 'code', '');
        $category_id  = get_value_or_default($this->request->get, 'category_id', 0);
        $country_code = session('country');
        $country_id   = isset($this->country_map[$country_code]) ? $this->country_map[$country_code] : 223;
        $customFields = $this->customer->getId();


        $param               = [];
        $param['id']         = $id;//B2B后台Admin 的参数
        $param['code']       = $code;
        $param['country_id'] = $country_id;



        $info = $this->verify($param);
        if ($id > 0) {
            $customFields = $this->customer->getId();
        }



        $data = [];
        $data['is_partner'] = $this->customer->isPartner();
        $data['isLogin']    = $this->customer->isLogged();
        $data['login']      = $this->url->link('account/login', '', true);
        if (!$info) {
            //404
            $this->document->setTitle($this->language->get('heading_title'));


            $data['column_left']    = $this->load->controller('common/column_left');
            $data['column_right']   = $this->load->controller('common/column_right');
            $data['content_top']    = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['footer']         = $this->load->controller('common/footer');
            $data['header']         = $this->load->controller('common/header');

            $data['heading_title']  = '';
            $data['text_error']     = $this->language->get('text_error');
            $data['continue']       = $this->url->link('common/home');

            $this->response->setStatusCode(404);

            $this->response->setOutput($this->load->view('error/not_found', $data));
        } else {
            $this->document->setTitle($this->language->get('heading_title'));

            //顶部图片
            $data['top_image_url'] = $info['image_url'] ?? null;
            //活动名称
            $data['top_title'] = $info['name'];
            // background_color
            $data['background_color'] = $info['background_color'];
            //获取预期入库的商品时间和数量
            $receipt_array = $this->model_catalog_product->getReceptionProduct();


            $data['promotions'] = [];//主打产品
            if ($info['products']) {
                $this->load->model('extension/module/product_home');
                $productIds = array_column($info['products'],'product_id');
                $list = $this->model_extension_module_product_home->getHomeProductInfo($productIds,$customFields);
                $list = array_column($list,null,'product_id');

                foreach ($info['products'] as $value) {
                    $product_id           = $value['product_id'];
                    $temp                 = $list[$value['product_id']] ?? $this->model_extension_module_product_show->getIdealProductInfo($product_id, $customFields, $receipt_array);;
                    $data['promotions'][] = $temp;
                }
            }



            //活动产品的分类
            $categories   = [];
            $categories[] = ['category_id' => 0, 'name' => 'All', 'cnt' => 0,];
            $tmp_others = ['category_id' => -1, 'name' => 'Others', 'cnt' => 0];
            $results    = $this->model_marketing_campaign_activity->getCategoriesById($info['id']);
            //Tab筛选：All Categories+活动产品的一级分类（产品数量>=2的分类）+Others（产品数量<2的产品归到此类，若无此分类不显示）
            unset($value);
            foreach ($results as $key => $value) {
                if ($value['category_id'] == 0) {
                    continue;
                }
                if ($value['category_id'] == -1) {//Others分类
                    $tmp_others['cnt'] += $value['cnt'];
                    unset($value);
                    continue;
                }
                if ($value['cnt'] < 2) {
                    $tmp_others['cnt'] += $value['cnt'];
                    unset($value);
                    continue;
                }
                $categories[] = ['category_id' => $value['category_id'], 'name' => $value['name'], 'cnt' => $value['cnt']];
            }
            unset($value);
            if (count($categories)> 1 && $tmp_others['cnt'] > 0) {//如果存在Others分类，则要显示
                $categories[] = $tmp_others;
            }


            $data['category_id'] = $category_id;
            $data['categories']  = $categories;
            $url_param = '';
            if ($id > 0) {
                $url_param .= '&id=' . $id;
            }
            $data['url_column'] = str_replace('&amp;', '&', $this->url->link('marketing_campaign/activity/column', 'code=' . $code . $url_param, true));


            $data['symbol_left']    = $this->currency->getSymbolLeft($this->session->data['currency']);
            $data['symbol_right']   = $this->currency->getSymbolRight($this->session->data['currency']);
            $data['column_left']    = $this->load->controller('common/column_left');
            $data['column_right']   = $this->load->controller('common/column_right');
            $data['content_top']    = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['footer']         = $this->load->controller('common/footer');
            $data['header']         = $this->load->controller('common/header');
            $data['app_version']    = APP_VERSION;
            $this->response->setOutput($this->load->view('marketing_campaign/activity/index', $data));
        }
    }


    private function verify($data)
    {
        if (!isset($data['code'])) {
            return [];
        }
        if (!isset($data['country_id'])) {
            return [];
        }

        $id         = $data['id'];
        $code       = $data['code'];
        $country_id = $data['country_id'];


        //查询code是否存再，是否在有效期内
        $info = $this->model_marketing_campaign_activity->getValidMarketingCampaignInfoByCode($code);
        if (!$info) {
            return [];
        }
        if ($id > 0) {
            //B2B后台Admin
            if ($id != $info['id']) {
                return [];
            }

            $this->customer->logout();//换成游客身份
            $this->session->set('country', $this->country_id_code[$info['country_id']] ?? 'USA'); // 重置国别
        } else {
            //非B2B后台Admin
            if($info['is_release'] != 1){
                return [];
            }

            $time_now = date('Y-m-d H:i:s');
            if ($time_now < $info['effective_time'] || $info['expiration_time'] <= $time_now) {
                //不在生效时间内
                return [];
            }

            if ($this->customer->isLogged()) {
                if($info['country_id'] != $country_id){
                    return [];
                }
            } else {
                // 未登录时重置国别
                $this->session->set('country', $this->country_id_code[$info['country_id']] ?? 'USA'); // 重置国别
            }
        }


        //主打产品
        $info['products'] = $this->model_marketing_campaign_activity->getMarketingCampaignProductById($info['id']);

        return $info;
    }


    private function verifyColumn($data)
    {
        if (!isset($data['code'])) {
            return [];
        }
        if (!isset($data['country_id'])) {
            return [];
        }

        $id         = $data['id'];
        $code       = $data['code'];
        $country_id = $data['country_id'];


        //查询code是否存再，是否在有效期内
        $info = $this->model_marketing_campaign_activity->getValidMarketingCampaignInfoByCode($code);
        if (!$info) {
            return [];
        }
        if ($id > 0) {
            //B2B后台Admin
            if ($id != $info['id']) {
                return [];
            }

            $this->customer->logout();//换成游客身份
            $this->session->data['country'] = isset($this->country_id_code[$info['country_id']])?$this->country_id_code[$info['country_id']]:'USA';//重置国别
        } else {
            //非B2B后台Admin
            if($info['is_release'] != 1){
                return [];
            }


            if($info['country_id'] != $country_id){
                return [];
            }


            $time_now = date('Y-m-d H:i:s');
            if ($time_now < $info['effective_time'] || $info['expiration_time'] <= $time_now) {
                //不在生效时间内
                return [];
            }
        }



        return $info;
    }


    /**
     * 促销页 非主打产品列表 Ajax请求，返回HTML
     * @throws ReflectionException
     */
    public function column()
    {
        $is_end = 1;
        $list   = [];


        $id          = get_value_or_default($this->request->get, 'id', 0);
        $code        = get_value_or_default($this->request->get, 'code', '');
        $category_id = get_value_or_default($this->request->get, 'category_id', 0);
        $page        = intval($this->request->get['page'] ?? 1);
        $page_limit  = intval(get_value_or_default($this->request->get, 'page_limit', $this->page_list));

        $page       = $page < 1 ? 1 : $page;
        $page_limit = $page_limit < 1 ? $this->page_list : $page_limit;


        $customFields = intval($this->customer->getId());
        $country_code = session('country');
        $country_id   = isset($this->country_map[$country_code]) ? $this->country_map[$country_code] : 223;


        $param               = [];
        $param['id']         = $id;
        $param['code']       = $code;
        $param['country_id'] = $country_id;
        $info                = $this->verifyColumn($param);
        if ($id > 0) {//可能被换成游客身份，重新获取身份
            $customFields = intval($this->customer->getId());
            $country_code = session('country');
            $country_id   = isset($this->country_map[$country_code]) ? $this->country_map[$country_code] : 223;
        }


        if (!$info) {
            goto end;
        }
        $id = $info['id'];


        $this->load->model('catalog/product');
        $this->load->model('extension/module/product_home');

        $products = $this->model_marketing_campaign_activity->getColumnById($id, $country_id, $category_id, $page, $page_limit);

        if ($products) {
            $productIds = array_column($products,'product_id');
            $list = $this->model_extension_module_product_home->getHomeProductInfo($productIds,$customFields);
            $is_end = 0;
            if (count($products) < $page_limit) {
                $is_end = 1;
            }
        }
        goto end;


        end:
        $htmls = $this->load->controller('marketing_campaign/activity/column_product', $list);
        $data  = [
            'is_end' => $is_end,
            'category_id'=> (int)$category_id,
            'htmls'  => $htmls,
        ];


        $this->response->returnJson($data);
    }


    /**
     * 产品卡片
     * @param array $products
     * @return string
     * @throws Exception
     */
    public function column_product($products)
    {
        $data['products'] = $products;

        $isPartner    = $this->customer->isPartner();

        $data['is_column']      = 1;
        $data['is_partner']     = $isPartner;
        $data['isLogin']        = $this->customer->isLogged();
        $data['login']          = $this->url->link('account/login', '', true);
        $data['products_total'] = 0;


        return $this->load->view('marketing_campaign/activity/column_product', $data);
    }
}
