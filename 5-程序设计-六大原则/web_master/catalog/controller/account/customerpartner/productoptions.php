<?php

use Illuminate\Support\Collection;

/**
 * @property ModelAccountCustomerpartnerProductOptions $model_account_customerpartner_product_options
 * @property ModelLocalisationLanguage $model_localisation_language
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountCustomerpartnerProductOptions extends Controller
{
    const CACHE_POST_DATA_KEY = 'cache_post_data_key';

    protected $data = [];

    protected $error = [];

    /** @var ModelAccountCustomerpartnerProductOptions $modelProductOptions */
    protected $modelProductOptions;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->load->model('account/customerpartner/product_options');
        $this->modelProductOptions = $this->model_account_customerpartner_product_options;
    }

    // 列表
    public function index()
    {
        $this->data = [];
        $session = $this->session->data;
        $this->load->language('account/customerpartner/product_options');
        $this->document->setTitle($this->language->get('heading_title_doc'));
        $this->loadOtherPage();
        $this->data['success'] = $session['success'] ?? '';
        if (isset($session['success'])) {
            $this->session->remove('success');
        }
        $this->data['error_warning'] = $session['error_warning'] ?? '';
        if (isset($session['error_warning'])) {
            $this->session->remove('error_warning');
        }
        $this->getList();
    }

    // 编辑
    public function edit()
    {
        $this->data = [];
        $session = $this->session->data;
        // 语言包
        $this->load->language('account/customerpartner/product_options');
        $this->document->setTitle($this->language->get('heading_title_doc'));
        $this->document->addScript('catalog/view/javascript/layer/layer.js');
        $this->loadOtherPage();
        $this->data['success'] = $session['success'] ?? '';
        if (isset($session['success'])) {
            $this->session->remove('success');
        }
        $this->data['error_warning'] = $session['error_warning'] ?? '';
        if (isset($session['error_warning'])) {
            $this->session->remove('error_warning');
        }
        if (isset($session['option_value'])) {
            $this->data['error_option_value'] = $session['option_value'];
            $this->session->remove('option_value');
        } else {
            $this->data['error_option_value'] = [];
        }
        $this->getOptionLists();

    }

    // 更新
    public function update()
    {
        $this->data = [];
        $this->load->language('account/customerpartner/product_options');
        $c_id = $this->customer->getId();
        $optionId = $this->request->get['option_id'];
        if (!$c_id || !$optionId) {
            $this->response->redirect($this->url->link('error/not_found'));
        }
        $data = $this->request->post;
        if ($this->validateForm()) {
            $res = $this->modelProductOptions->editOptions($c_id, $optionId, $data);
            if (!$res) {
                session()->set('error_warning', $this->language->get('error_msg'));
            } else {
                session()->set('success', $this->language->get('success_msg'));
            }
        } else {
            session()->set('option_value', $this->error['option_value'] ?? []);
            isset($data['option_value']) && $this->cache->set(static::CACHE_POST_DATA_KEY, $data['option_value']);
        }

        // 无论更新失败与否都跳转到编辑页面
        $this->response->redirect(
            $this->url->link(
                'account/customerpartner/productoptions/edit',
                [
                    'user_token' => $this->session->data['user_token'] ?? '',
                    'option_id' => $this->request->get['option_id']
                ]
            )
        );
    }

    // ajax 校验
    public function checkOption()
    {
        $this->response->addHeader('Content-Type: application/json');
        $get = new Collection($this->request->post);
        $customerId = (int)$this->customer->getId();
        $optionId = (int)$get->get('o_id', 0);
        $optionValueId = (int)$get->get('v_id', 0);
        if (!$customerId || !$optionId || !$optionValueId) {
            $this->response->setOutput(json_encode([]));
            return;
        }
        $res = $this->modelProductOptions->checkCustomerOptionValue($customerId, $optionId, $optionValueId);

        $this->response->setOutput(json_encode($res ?: []));
    }

    // 自动完成 商品添加修改时候使用
    public function autocomplete()
    {
        $this->response->addHeader('Content-Type: application/json');
        $get = new Collection($this->request->get);
        $customerId = (int)$this->customer->getId();
        $res = $this->modelProductOptions->getAutoCompleteOptionList($customerId, 13, $get->all());

        $this->response->setOutput(json_encode($res));
    }

    // 自动完成 属性添加时候使用
    public function autoOptionComplete()
    {
        $this->response->addHeader('Content-Type: application/json');
        $get = new Collection($this->request->get);
        $res = $this->modelProductOptions->getAutoCompleteOriginOptionList(13, $get->all());

        $this->response->setOutput(json_encode($res));
    }

    // 这段代码用来更新用户曾经选定的product
    // 里面的color数据到oc_customer_option 中
    public function refreshOptions()
    {
        if (!$this->customer->isPartner()) {
            $this->response->redirect($this->url->link('error/not_found'));
        }
        $c_id = $this->customer->getId();
        $this->modelProductOptions->refreshOptionsByCustomerId((int)$c_id);

        $this->response->redirect(
            $this->url->link(
                'account/customerpartner/productoptions/edit',
                [
                    'user_token' => $this->session->data['user_token'],
                    'option_id' => $this->request->get['option_id']
                ]
            )
        );
    }

    // 初始化
    public function refreshAllOptions()
    {
        $this->modelProductOptions->refreshTotalOptions();
    }

    protected function getList()
    {
        $data = [];
        $get = new Collection($this->request->get);
        $post = new Collection($this->request->post);
        $session = new Collection($this->session->data);
        $sort = $get->get('sort', 'o.sort_desc');
        $order = strtoupper($get->get('order', 'ASC'));
        $page = $get->get('page', 1);
        $page_limit = $get->get('page_limit', $this->config->get('config_limit_admin'));

        // url变成数组 后续使用 http_build_query 组合
        $url = ['user_token' => $session->get('user_token')];
        $get->has('sort') && $url['sort'] = $get->get('sort');
        $get->has('order') && $url['order'] = $get->get('order');
        $get->has('page') && $url['page'] = $get->get('page');

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home', $url),
            ],
            [
                'text' => $this->language->get('heading_title_account'),
                'href' =>  $this->url->link('customerpartner/seller_center/index', '', true),
            ],
            [
                'text' => $this->language->get('heading_total_title'),
                'href' => 'javascript:void(0);'
            ],
            [
                'text' => $this->language->get('heading_title_doc'),
                'href' => $this->url->link('account/customerpartner/productoptions', $url)
            ]
        ];
        $data['delete'] = $this->url->link('account/customerpartner/productoptions/delete', $url);

        $filter_data = [
            'sort' => $sort,
            'order' => $order,
            'page' => $page,
            'perPage' => $page_limit
        ];
        $option_total = $this->modelProductOptions->getTotalOptions();
        $results = $this->modelProductOptions->getOptions($filter_data);
        foreach ($results as $k => $result) {
            $results[$k]['edit'] = $this->url->link(
                'account/customerpartner/productoptions/edit',
                array_merge($url, ['option_id' => $result['option_id']])
            );
        }
        $data['options'] = $results;
        // 选择项目
        $data['selected'] = $post->get('selected', []);

        $url = [];
        $url['order'] = $order == 'ASC' ? 'DESC' : 'ASC';
        if ($get->has($page)) {
            $url['page'] = $get->get('page');
        }
        $data['sort_name'] = $this->url->link(
            'account/customerpartner/productoptions',
            array_merge(['user_token' => $session->get('user_token'), 'sort' => 'od.name'], $url)
        );
        $data['sort_sort_order'] = $this->url->link(
            'account/customerpartner/productoptions',
            array_merge(['user_token' => $session->get('user_token'), 'sort' => 'o.sort_order'], $url)
        );

        $url = [];
        $get->has('sort') && $url['sort'] = $get->get('sort');
        $get->has('sort') && $url['order'] = $get->get('order');

        $pagination = new Pagination();
        $pagination->total = $option_total;
        $pagination->page = $page;
        $pagination->limit = $page_limit;
        $pagination->url = $this->url->link(
            'account/customerpartner/productoptions',
            array_merge(['user_token' => $session->get('user_token'), 'page' => '{page}'], $url)
        );
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf(
            $this->language->get('text_pagination'),
            ($option_total)
                ? (($page - 1) * $page_limit) + 1
                : 0,
            ((($page - 1) * $page_limit) > ($option_total - $page_limit))
                ? $option_total
                : ((($page - 1) * $page_limit) + $page_limit),
            $option_total,
            ceil($option_total / $page_limit)
        );
        $data['sort'] = $sort;
        $data['order'] = $order;

        $this->data = array_merge($this->data, $data);
        $this->response->setOutput($this->load->view('account/customerpartner/options/list', $this->data));
    }

    protected function getOptionLists()
    {
        $data = [];
        $get = new Collection($this->request->get);
        $session = new Collection($this->session->data);
        $optionInfo = $this->modelProductOptions->getOptionDetail((int)$get->get('option_id'));
        if (!$optionInfo) {
            $this->response->redirect($this->url->link('error/not_fount'));
        }
        $data['option_id'] = $optionInfo['option_id'];
        $data['option_name'] = $optionInfo['name'];
        $data['delete_action'] = html_entity_decode(
            $this->url->link(
                'account/customerpartner/productoptions/checkOption',
                [
                    'user_token' => $session->get('user_token'),
                ]
            )
        );
        $url = [];
        $get->has('sort') && $url['sort'] = $get->get('sort');
        $get->has('order') && $url['order'] = $get->get('order');
        $get->has('page') && $url['page'] = $get->get('page');
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home', $url),
            ],
            [
                'text' => $this->language->get('heading_title_account'),
                'href' => 'javascript:void(0);'
            ],
            [
                'text' => $this->language->get('heading_total_title'),
                'href' => 'javascript:void(0);'
            ],
            [
                'text' => $this->language->get('heading_title_doc'),
                'href' => $this->url->link('account/customerpartner/productoptions', $url)
            ]
        ];
        // 返回 和 保存地址
        $data['back_action'] = $this->url->link(
            'account/customerpartner/productoptions',
            array_merge(['user_token' => $session->get('user_token'), 'option_id' => $get->get('option_id')], $url)
        );
        $data['save_action'] = $this->url->link(
            'account/customerpartner/productoptions/update',
            array_merge(['user_token' => $session->get('user_token'), 'option_id' => $get->get('option_id')], $url)
        );
        $data['sync_action'] = $this->url->link(
            'account/customerpartner/productoptions/refreshOptions',
            array_merge(['user_token' => $session->get('user_token'), 'option_id' => $get->get('option_id')], $url)
        );
        // 语言
        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages();
        // 所有的option列表
        if ($cacheData = $this->cache->get(static::CACHE_POST_DATA_KEY)) {
            $data['option_values'] = $cacheData;
            $this->cache->delete(static::CACHE_POST_DATA_KEY);
        } else {
            $results = $this->modelProductOptions->getOptionsList($optionInfo['option_id'], (int)$this->customer->getId());
            foreach ($results as $k => $res) {
                if (is_file(DIR_IMAGE . $res['image'])) {
                    $image = $res['image'];
                    $thumb = $res['image'];
                } else {
                    $image = '';
                    $thumb = 'no_image.png';
                }
                $results[$k]['image'] = $image;
                $results[$k]['thumb'] = $this->model_tool_image->resize($thumb, 100, 100);
            }
            $data['option_values'] = $results;
        }
        $data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);
        $this->data = array_merge($this->data, $data);
        $this->response->setOutput($this->load->view('account/customerpartner/options/edit', $this->data));
    }

    protected function loadOtherPage()
    {
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['separate_view'] = false;
        $data['separate_column_left'] = '';
        if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && $this->session->data['marketplace_separate_view'] == 'separate') {
            $data['separate_view'] = true;
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        }

        $this->data = array_merge($this->data, $data);
    }

    protected function validateForm()
    {
        if (isset($this->request->post['option_value'])) {
            foreach ($this->request->post['option_value'] as $option_value_id => $option_value) {
                foreach ($option_value['option_value_description'] as $language_id => $option_value_description) {
                    if (empty($option_value_description['name'])) {
                        $this->error['option_value'][$option_value_id][$language_id] = $this->language->get('error_empty_option_value');
                        continue;
                    }
                    if ((utf8_strlen($option_value_description['name']) < 1) || (utf8_strlen($option_value_description['name']) > 128)) {
                        $this->error['option_value'][$option_value_id][$language_id] = $this->language->get('error_option_value');
                        continue;
                    }
                }
            }
        }
        return !$this->error;
    }


}