<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Class ControllerAccountCustomerpartnerFutures
 * @property ModelAccountCustomerpartnerFutures $model_account_customerpartner_futures
 * @property ModelCommonProduct $model_common_product
 */
class ControllerAccountCustomerpartnerFutures extends Controller
{
    protected $data = [];

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
    }

    /**
     * 期货模板列表
     * @deprecated 第一版已作废
     */
    public function index()
    {
        //region #5221 产品经理要求，期货模板一期的页面为 Not Found
        $this->document->setTitle('Not Found');
        $this->data['continue'] = $this->url->link('common/home');
        $this->response->setStatusCode(404);
        $this->data['content_top'] = $this->load->controller('common/content_top');
        $this->data['content_bottom'] = $this->load->controller('common/content_bottom');
        $this->data['footer'] = $this->load->controller('common/footer');
        $this->data['header'] = $this->load->controller('common/header');
        $this->data['heading_title'] = 'The page you requested cannot be found!';
        $this->data['text_error'] = 'The page you requested cannot be found!';
        $this->response->setOutput($this->load->view('error/not_found', $this->data));
        //endregion

        //$this->language->load('account/customerpartner/futures');
        //$this->document->setTitle($this->language->get('text_futures'));
        //$this->document->addStyle('catalog/view/javascript/product/element-ui.css');
        //$this->document->addScript('catalog/view/javascript/product/element-ui.js');
        //$this->document->addScript('catalog/view/javascript/layer/layer.js');
        //$this->data['breadcrumbs'] = [
        //    [
        //        'text' => $this->language->get('text_home'),
        //        'href' => $this->url->link('common/home')
        //    ],
        //    [
        //        'text' => $this->language->get('seller_dashboard'),
        //        'href' => $this->url->link('customerpartner/seller_center/index', '', true),
        //        'separator' => $this->language->get('text_separator')
        //    ],
        //    [
        //        'text' => $this->language->get('text_market_business'),
        //        'href' => 'javascript:void(0);',
        //        'separator' => $this->language->get('text_separator')
        //    ],
        //    [
        //        'text' => $this->language->get('text_futures_list'),
        //        'href' => $this->url->link('account/customerpartner/futures'),
        //        'separator' => $this->language->get('text_separator')
        //    ]
        //];
        //$this->addOthers();
        //$this->resolveRequest();
        //$this->data['futures_create_url'] = $this->url->link('account/customerpartner/futures/create');
        //$this->data['delete_url'] = $this->url->link('account/customerpartner/futures/delete');
        //$this->data['ban_url'] = $this->url->link('account/customerpartner/futures/ban');
        //$this->data['recovery_url'] = $this->url->link('account/customerpartner/futures/recovery');
        //$this->data['currency_symbol_left'] = $this->currency->getSymbolLeft(session('currency'));
        //$this->data['currency_symbol_right'] = $this->currency->getSymbolRight(session('currency'));
        //$this->response->setOutput($this->load->view('account/customerpartner/futures/index', $this->data));
    }

    // 创建模板列表
    public function create()
    {
        //region #5221 产品经理要求，期货模板一期的页面为 Not Found
        $this->document->setTitle('Not Found');
        $this->data['continue'] = $this->url->link('common/home');
        $this->response->setStatusCode(404);
        $this->data['content_top'] = $this->load->controller('common/content_top');
        $this->data['content_bottom'] = $this->load->controller('common/content_bottom');
        $this->data['footer'] = $this->load->controller('common/footer');
        $this->data['header'] = $this->load->controller('common/header');
        $this->data['heading_title'] = 'The page you requested cannot be found!';
        $this->data['text_error'] = 'The page you requested cannot be found!';
        $this->response->setOutput($this->load->view('error/not_found', $this->data));
        //endregion

        //$this->language->load('account/customerpartner/futures');
        //$this->load->model('account/customerpartner/futures');
        //$template_id = $this->request->request['template_id'] ?? 0;
        //$this->document->setTitle($this->language->get(
        //    $template_id > 0
        //        ? 'text_edit_futures'
        //        : 'text_add_futures'
        //));
        //$template_info = $this->model_account_customerpartner_futures->getFuturesTemplateInfoByTemplateId(
        //    (int)$this->customer->getId(),
        //    $template_id
        //);
        //if ($template_id > 0 && !$template_info) {
        //    $this->response->redirect($this->url->link('account/customerpartner/futures/create'));
        //}
        //// 校验template_id 的合法性
        //$this->data['breadcrumbs'] = [
        //    [
        //        'text' => $this->language->get('text_home'),
        //        'href' => $this->url->link('common/home')
        //    ],
        //    [
        //        'text' => $this->language->get('seller_dashboard'),
        //        'href' => $this->url->link('customerpartner/seller_center/index', '', true),
        //        'separator' => $this->language->get('text_separator')
        //    ],
        //    [
        //        'text' => $this->language->get('text_market_business'),
        //        'href' => 'javascript:void(0);',
        //        'separator' => $this->language->get('text_separator')
        //    ],
        //    [
        //        'text' => $this->language->get('text_futures_list'),
        //        'href' => $this->url->link('account/customerpartner/futures'),
        //        'separator' => $this->language->get('text_separator')
        //    ],
        //    [
        //        'text' => $template_id > 0 ? $this->language->get('text_edit_futures') : $this->language->get('text_add_futures'),
        //        'href' => $template_id > 0
        //            ? $this->url->link('account/customerpartner/futures/create', ['template_id' => $template_id])
        //            : $this->url->link('account/customerpartner/futures/create'),
        //        'separator' => $this->language->get('text_separator')
        //    ]
        //];
        //$this->addOthers();
        //$this->data['query_product_url'] = $this->url->link('account/customerpartner/futures/getProducts');
        //$this->data['get_receipt_url'] = $this->url->link('account/customerpartner/futures/getReceipts');
        //$this->data['store_product_url'] = $this->url->link('account/customerpartner/futures/store');
        //$this->data['get_futures_url'] = $this->url->link('account/customerpartner/futures/getFutures');
        //$this->data['get_settlement_day_url'] = $this->url->link('account/customerpartner/futures/getSettlementDay');
        //$this->data['go_back_url'] = $this->url->link('account/customerpartner/futures');
        //$this->data['pay_amount_url'] = $this->url->link('customerpartner/credit_manage');
        //$this->data['get_alarm_price_url'] = $this->url->link('account/customerpartner/futures/getAlarmPrice');
        //// 有效提单
        //$this->data['pay_order_url'] = $this->url->link('account/inbound_management&filter_inboundOrderStatus=3');
        //// 应收款
        //$this->data['pay_money_url'] = $this->url->link('account/seller_bill/bill');
        //$this->data['is_japan'] = $this->customer->isJapan() ? 1 : 0;
        //$this->data['is_edit'] = $template_id > 0 ? 1 : 0;
        //$this->data['is_inner'] = $this->customer->isInnerAccount() ? 1 : 0;
        //$this->data['product_id'] = $template_info ? $template_info['product_id'] : 0;
        //$this->data['currency_symbol_left'] = $this->currency->getSymbolLeft(session('currency'));
        //$this->data['currency_symbol_right'] = $this->currency->getSymbolRight(session('currency'));
        //$this->response->setOutput($this->load->view('account/customerpartner/futures/create', $this->data));
    }

    // region 前端api接口

    // 保存模板
    public function store()
    {
        $request = json_decode(file_get_contents('php://input'), true);
        $this->load->model('account/customerpartner/futures');
        $ret = $this->model_account_customerpartner_futures->saveFuturesTemplate($request);
        $this->response->returnJson(['code' => $ret ? 0 : 1]);
    }

    // 禁用模板
    public function ban()
    {
        $request = $this->request->request;
        $id = $request['id'] ?? 0;
        $this->load->model('account/customerpartner/futures');
        $ret = $this->model_account_customerpartner_futures->banFutures(
            (int)$this->customer->getId(),
            $id
        );
        $this->response->returnJson(['code' => $ret ? 0 : 1]);
    }

    // 恢复模板
    public function recovery()
    {
        $request = $this->request->request;
        $id = $request['id'] ?? 0;
        $this->load->model('account/customerpartner/futures');
        $ret = $this->model_account_customerpartner_futures->recoveryFutures(
            (int)$this->customer->getId(),
            $id
        );
        $this->response->returnJson(['code' => $ret ? 0 : 1]);
    }

    public function delete()
    {
        $request = $this->request->request;
        $id = $request['id'] ?? 0;
        $this->load->model('account/customerpartner/futures');
        $ret = $this->model_account_customerpartner_futures->deleteFutures(
            (int)$this->customer->getId(),
            $id
        );
        $this->response->returnJson(['code' => $ret ? 0 : 1]);
    }

    // 获取入库单
    public function getReceipts()
    {
        $co = new Collection(json_decode(file_get_contents('php://input'), true));
        $this->load->model('account/customerpartner/futures');
        $data = $this->model_account_customerpartner_futures->getReceiptOrderByProductId($co->get('product_id', 0));
        // 对data 时间进行由近到远排序
        usort($data, function ($a, $b) {
            $a_time = abs(strtotime($a['expected_date']) - time());
            $b_time = abs(strtotime($b['expected_date']) - time());
            if ($a_time == $b_time) return 0;
            return $a_time < $b_time ? -1 : 1;
        });
        // 处理data 分析出入库时间(最小值 和 最大值)
        $time_arr = [];
        $min_day = 1;
        $max_day = 90;
        foreach ($data as $val) {
            if (!empty($val['expected_date'])) {
                $expected_date = strtotime($val['expected_date']);
                if ($expected_date > time() && !in_array($expected_date, $time_arr)) {
                    $time_arr[] = ceil(($expected_date - time()) / (24 * 60 * 60));
                }
            }
        }
        sort($time_arr);
        if (count($time_arr) > 0) {
            $min_day = $time_arr[0];
            $max_day = $time_arr[count($time_arr) - 1];
            $min_day = min($min_day, 90);
            $max_day = min($max_day, 90);
        }
        $this->response->returnJson(['data' => $data, 'min_day' => $min_day, 'max_day' => $max_day]);
    }

    /**
     *获取查询商品
     */
    public function getQueryProduct()
    {
        $co = new Collection(json_decode(file_get_contents('php://input'), true));
        $name = $co->get('product_name', '');
        if (empty($name)) {
            $this->response->returnJson(['code' => 1, 'msg' => 'product name must be not empty']);
        }
        list($name,) = explode('/', trim($name));
        $ret = $this->orm
            ->table(DB_PREFIX . 'product as p')
            ->join('oc_customerpartner_to_product as ctp', ['ctp.product_id' => 'p.product_Id'])
            ->leftJoin(DB_PREFIX . 'product_description as pd', ['pd.product_id' => 'p.product_id'])
            ->select([
                'p.product_id', 'p.mpn', 'p.sku', 'pd.name', 'p.price',
            ])
            ->where([
                'p.status' => 1,
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1,
                'ctp.customer_id' => $this->customer->getId(),
            ])
            ->whereIn('p.product_type',[0,3])
            ->where(function (Builder $q) use ($name) {
                $q->orWhere('p.mpn', $name);
                $q->orWhere('p.sku', $name);
            })
            ->first();
        if (!$ret) {
            $this->response->returnJson(['code' => 1, 'msg' => 'Cannot find the matched item by this MPN or Item Code or, this item is not available, has been discarded or cannot be sold separately.']);
        } else {
            $this->response->returnJson(['code' => 0, 'msg' => '', 'data' => (array)$ret]);
        }
    }

    /**
     * 获取关联商品的api
     */
    public function getProducts()
    {
        $co = new Collection(json_decode(file_get_contents('php://input'), true));
        $pageSize = $co->get('page_size', 5);
        $currentPage = $co->get('page', 1);
        /** @var Builder $query */
        $query = $this->orm
            ->table(DB_PREFIX . 'product as p')
            ->select([
                'p.product_id', 'p.mpn', 'p.sku', 'pd.name', 'p.price',
            ])
            ->join(DB_PREFIX . 'customerpartner_to_product as c2p', ['c2p.product_id' => 'p.product_id'])
            ->leftJoin(DB_PREFIX . 'product_description as pd', ['pd.product_id' => 'p.product_id'])
            ->where([
                'p.status' => 1,
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1,
                'c2p.customer_id' => (int)$this->customer->getId(),
            ])->whereIn('p.product_type',[0,3])
            ->when(
                !empty(trim($co->get('filter_search'))),
                function (Builder $q) use ($co) {
                    $q->where(function (Builder $q) use ($co) {
                        $filter = htmlspecialchars(trim($co->get('filter_search')));
                        $q->orWhere('p.mpn', 'like', "%{$filter}%")
                            ->orWhere('p.sku', 'like', "%{$filter}%");
                    });
                }
            )
            ->when(
                !empty($co->get('product_id')),
                function (Builder $q) use ($co) {
                    $q->whereNotIn('p.product_id', $co->get('product_id'));
                }
            )
            ->orderBy('p.product_id', 'desc');
        $total = $query->count();
        if ($total <= ($currentPage - 1) * $pageSize) $currentPage = 1;
        $this->load->model('account/customerpartner/futures');
        /** @var Collection $res */
        $res = $query->forPage($currentPage, $pageSize)->get();
        $res = $res->map(function ($item) {
            $row = get_object_vars($item);
            $row['name'] = htmlspecialchars_decode($row['name']);
            // 获取期货模板详情
            $row['futures_detail'] = $data = $this->model_account_customerpartner_futures->getFuturesByProductId(
                $this->customer->getId(),
                $row['product_id']
            );
            // 表示是否有期货数据
            $row['is_edit'] = !empty($data) ? 1 : 0;
            return $row;
        });
        $this->response->returnJson(['data' => $res->toArray(), 'total' => $total, 'page' => $currentPage]);
    }

    // 获取期货模板
    public function getFutures()
    {
        $co = new Collection(json_decode(file_get_contents('php://input'), true));
        $product_id = $co->get('product_id', 0);
        $this->load->model('account/customerpartner/futures');
        $info = $this->model_account_customerpartner_futures->getFuturesTemplateInfoByProductId(
            (int)$this->customer->getId(),
            (int)$product_id
        );
        $this->response->returnJson(['data' => $info ?: [], 'code' => $info ? 0 : 1]);
    }

    public function getAlarmPrice()
    {
        $co = new Collection(json_decode(file_get_contents('php://input'), true));
        $product_id = $co->get('product_id', 0);
        $this->load->model('common/product');
        $alarm_price = $this->model_common_product->getAlarmPrice($product_id);
        $this->response->returnJson((string)$alarm_price);
    }

    // 获取结算日
    public function getSettlementDay()
    {
        $this->response->returnJson(['data' => $this->config->get('liquidation_day'), 'code' => 0]);
    }

    // endregion

    private function addOthers()
    {
        $this->data['separate_view'] = true;
        $this->data['column_left'] = '';
        $this->data['column_right'] = '';
        $this->data['content_top'] = '';
        $this->data['content_bottom'] = '';
        $this->data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
        $this->data['margin'] = "margin-left: 18%";
        $this->data['footer'] = $this->load->controller('account/customerpartner/footer');
        $this->data['header'] = $this->load->controller('account/customerpartner/header');
    }

    // 处理列表
    private function resolveRequest()
    {
        $request = $this->request->request;
        $this->load->model('account/customerpartner/futures');
        $filter = [];
        $url = '';
        $filter_sku_mpn = $request['filter_sku_mpn'] ?? null;
        if ($filter_sku_mpn) {
            $filter['filter_sku_mpn'] = $filter_sku_mpn;
            $url .= '&filter_sku_mpn=' . $filter_sku_mpn;
        }
        $filter_page = $request['page'] ?? 1;
        if ($filter_page) {
            $filter['page'] = $filter_page;
        }
        $filter_page_limit = $request['page_limit'] ?? 10;
        if ($filter_page_limit) {
            $filter['page_limit'] = $filter_page_limit;
            $url .= '&page_limit=' . $filter_sku_mpn;
        }
        $total = $this->model_account_customerpartner_futures->getFuturesTotal(
            (int)$this->customer->getId(),
            $filter
        );
        $list = $this->model_account_customerpartner_futures->getFuturesList(
            (int)$this->customer->getId(),
            $filter
        );
        bcscale($this->customer->isJapan() ? 0 : 2);
        array_walk($list, function (&$item) {
            if ($item['min_expected_storage_days'] == $item['max_expected_storage_days']) {
                $item['day_format'] = $item['min_expected_storage_days'] . ' Days';
            } else {
                $item['day_format'] = $item['min_expected_storage_days'] . ' ~ ' . $item['max_expected_storage_days'] . ' Days';
            }
            // product url
            $item['product_url'] = $this->url->link('product/product', ['product_id' => $item['product_id']]);
            // 平台清算日
            $item['liquidation_day'] = $this->config->get('liquidation_day');
            // 价格
            $item['price_format'] = $this->formatPrice($item['price']);
            // items
            $items = $item['items'];
            $newItems = [];
            foreach ($items as $val) {
                $val['sell_qty'] = $val['min_num'] == $val['max_num']
                    ? $val['min_num']
                    : $val['min_num'] . ' ~ ' . $val['max_num'];
                $val['price_format'] = $this->formatPrice($val['exclusive_price']);
                // 计算订金
                $buyer_pay_ratio = bcdiv($item['buyer_payment_ratio'], 100, 2);
                $m_price = round(bcmul($val['exclusive_price'], $buyer_pay_ratio, 4), 2);
                $min_margin_price = $this->formatPrice(bcmul($val['min_num'], $m_price));
                $max_margin_price = $this->formatPrice(bcmul($val['max_num'], $m_price));
                $val['margin_format'] = bccomp($min_margin_price, $max_margin_price) === 0
                    ? $min_margin_price
                    : $min_margin_price . ' ~ ' . $max_margin_price;
                // 尾款
                $val['balance_format'] = $this->formatPrice(bcsub($val['exclusive_price'], $m_price));
                // 协议
                $min_margin_price = $this->formatPrice(bcmul($val['min_num'], $val['exclusive_price']));
                $max_margin_price = $this->formatPrice(bcmul($val['max_num'], $val['exclusive_price']));
                $val['agree_format'] = bccomp($min_margin_price, $max_margin_price) === 0
                    ? $min_margin_price
                    : $min_margin_price . ' ~ ' . $max_margin_price;
                $val['is_default_format'] = $val['is_default'] ? 'Yes' : 'No';
                $newItems[] = $val;
            }
            $item['items'] = $newItems;
        });
        $this->data['list'] = $list;
        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $filter_page;
        $pagination->limit = $filter_page_limit;
        $pagination->url = $this->url->link('account/customerpartner/futures', $url . '&page={page}');
        $this->data['form_search_url'] = $this->url->link(
            'account/customerpartner/futures',
            ['page_limit' => $filter_page_limit]
        );
        $this->data['pagination'] = $pagination->render();
        $this->data['request'] = $request;
    }

    /**
     * 格式化价格
     * @param $price
     * @return string
     */
    private function formatPrice($price)
    {
        return $this->customer->isJapan()
            ? number_format($price, 0, '.', '')
            : number_format($price, 2, '.', '');
    }
}
