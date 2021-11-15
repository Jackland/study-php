<?php

use App\Enums\Spot\SpotProductQuoteStatus;
use App\Helper\CountryHelper;
use App\Models\Customer\Customer;
use App\Repositories\Margin\AgreementRepository;
use App\Repositories\Order\OrderRepository;
use App\Repositories\Seller\SellerRepository;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use App\Widgets\VATToolTipWidget;

/**
 * Class ControllerAccountCustomerpartnerwkquotesadmin
 * @property ModelAccountwkquotesadmin $model_account_wk_quotes_admin
 * @property ModelCommonProduct $model_common_product
 * @property ModelAccountProductQuotesMarginContract $model_account_product_quotes_margin_contract
 * @property ModelFuturesAgreement $model_futures_agreement
 * @property ModelAccountProductQuoteswkproductquotes $model_account_product_quotes_wk_product_quotes
 * @property ModelAccountProductQuotesRebatesContract $model_account_product_quotes_rebates_contract
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountCustomerpartnerwkquotesadmin extends Controller
{

    private $error = array();
    private $data = array();

    public function __construct($registry)
    {
        parent::__construct($registry);
        if (!$this->customer->isLogged() || !$this->customer->isPartner()) {
            session()->set('redirect', $this->url->link('account/customerpartner/orderlist', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
        $this->language->load('account/customerpartner/wk_quotes_admin');
        $this->language->load('account/product_quotes/wk_product_quotes');
        $this->load->model('account/wk_quotes_admin');

        // 如果是 buyer 则跳转到buyer的bid list
        if (!$this->customer->isPartner()) {
            $this->response->redirect($this->url->link('account/product_quotes/wk_quote_my', '', true));
        }
    }

    public function index()
    {
        $data['chkIsPartner'] = $this->customer->isPartner();

        trim_strings($this->request->get);

        $tab = get_value_or_default($this->request->get, 'tab', null);
        $data['newtab'] = get_value_or_default($this->request->get, 'newtab', null);
        $filter_buyer_name = get_value_or_default($this->request->get, 'filter_buyer_name', null);
        $data['tab'] = $tab;
        $data['filter_buyer_name'] = $filter_buyer_name;

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


        $data['quote_enable'] = false;
        if (!empty($this->customer->getCountryId()) && in_array($this->customer->getCountryId(), QUOTE_ENABLE_COUNTRY)) {
            $data['quote_enable'] = true;
        }

        $this->load->model('account/product_quotes/wk_product_quotes');
        $this->load->model('account/product_quotes/margin_contract');
        $this->load->model('account/product_quotes/rebates_contract');
        $this->load->model('account/product_quotes/rebates_contract');
        $this->load->model('futures/agreement');
        $spot_num = $this->model_account_product_quotes_wk_product_quotes->quoteAppliedCount($this->customer->getId());
        $rebates_num = $this->model_account_product_quotes_rebates_contract->rebatesAppliedCount($this->customer->getId());
        $margin_num = app(AgreementRepository::class)->sellerMarginBidsHotspotCount(customer()->getId());
        $future_num = $this->model_futures_agreement->sellerAgreementTotal($this->customer->getId());
        //现货保证金三期添加  显示99+
        if ($margin_num > 99) {
            $margin_num = '99+';
        } elseif ($margin_num < 1) {
            $margin_num = '';
        } else {
            $margin_num = strval($margin_num);
        }

        $data['spot_extra'] = dprintf(
            '<span class="tip-num" style="{style}">{count}</span>',
            [
                'style' => $spot_num <= 0
                    ? 'visibility: hidden;'
                    : '',
                'count' => $spot_num,
            ]
        );
        $data['rebates_extra'] = dprintf(
            '<span class="tip-num" style="{style}">{count}</span>',
            [
                'style' => $rebates_num <= 0
                    ? 'visibility: hidden;'
                    : '',
                'count' => $rebates_num,
            ]
        );
        $data['margin_extra'] = dprintf(
            '<span class="tip-num" style="{style}">{count}</span>',
            [
                'style' => $margin_num <= 0
                    ? 'visibility: hidden;'
                    : '',
                'count' => $margin_num,
            ]
        );
        $data['future_extra'] = dprintf(
            '<span class="tip-num" style="{style}">{count}</span>',
            [
                'style' => $future_num <= 0
                    ? 'visibility: hidden;'
                    : '',
                'count' => $future_num,
            ]
        );

        return $this->render('account/customerpartner/list_quotes_admin', $data, 'seller');
    }

    /**
     * 移除筛选条件 filter_price / filter_qty
     */
    public function getList()
    {
        if (!$this->customer->isLogged() || !$this->customer->isPartner()) {
            session()->set('redirect', $this->url->link('account/customerpartner/orderlist', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }

        if (isset($this->request->get['filter_id'])) {
            $filter_id = $this->request->get['filter_id'];
        } else {
            $filter_id = null;
        }

        $filter_customer = trim($this->request->get('filter_customer', null));

        if (isset($this->request->get['filter_product'])) {
            $filter_product = $this->request->get['filter_product'];
        } else {
            $filter_product = null;
        }

        if (isset($this->request->get['filter_status'])) {
            $filter_status = $this->request->get['filter_status'];
        } else {
            $filter_status = null;
        }

        if (isset($this->request->get['filter_date_from'])) {
            $filter_date_from = $this->request->get['filter_date_from'];
        } else {
            $filter_date_from = null;
        }
        if (isset($this->request->get['filter_date_to'])) {
            $filter_date_to = $this->request->get['filter_date_to'];
        } else {
            $filter_date_to = null;
        }
        $filter_sku_mpn = trim($this->request->get('filter_sku_mpn', null));

        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'name';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'DESC';
        }

        if (isset($this->request->get['page'])) {
            $page = (int)$this->request->get['page'];
        } else {
            $page = 1;
        }
        $page = $page > 0 ? $page : 1;

        $page_limit = (int)get_value_or_default($this->request->request, 'page_limit', 15);
        $page_limit = $page_limit > 0 ? $page_limit : 15;

        $data = array(
            'filter_id' => $filter_id,
            'filter_customer' => $filter_customer,
            'filter_product' => trim($filter_product),
            'filter_status' => $filter_status,
            'filter_date_from' => $filter_date_from,
            'filter_date_to' => $filter_date_to,
            'filter_sku_mpn' => $filter_sku_mpn,
            'sort' => $sort,
            'order' => $order,
            'start' => ($page - 1) * $page_limit,
            'limit' => $page_limit,
        );

        $url = '';

        if (isset($this->request->get['filter_id'])) {
            $url .= '&filter_id=' . $this->request->get['filter_id'];
        }

        if (isset($this->request->get['filter_customer'])) {
            $url .= '&filter_customer=' . urlencode(html_entity_decode(trim($this->request->get['filter_customer']), ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_product'])) {
            $url .= '&filter_product=' . urlencode(html_entity_decode(trim($this->request->get['filter_product']), ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }

        if (isset($this->request->get['filter_date_from'])) {
            $url .= '&filter_date_from=' . urlencode(html_entity_decode(changeOutPutByZone($this->request->get['filter_date_from'], $this->session), ENT_QUOTES, 'UTF-8'));
        }
        if (isset($this->request->get['filter_date_to'])) {
            $url .= '&filter_date_to=' . urlencode(html_entity_decode(changeOutPutByZone($this->request->get['filter_date_to'], $this->session), ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_sku_mpn'])) {
            $url .= '&filter_sku_mpn=' . urlencode(html_entity_decode($this->request->get['filter_sku_mpn'], ENT_QUOTES, 'UTF-8'));
        }

        $tableUrl = $url;   //用于table的header部分切换正逆序
        if ($order == 'ASC') {
            $url .= '&order=ASC';
            $tableUrl .= '&order=DESC';
        } else {
            $url .= '&order=DESC';
            $tableUrl .= '&order=ASC';
        }

        $data['sort_id'] = $this->url->link('account/customerpartner/wk_quotes_admin/getList', '&sort=pq.id' . $tableUrl, true);
        $data['sort_customer'] = $this->url->link('account/customerpartner/wk_quotes_admin/getList', '&sort=c.nickname' . $tableUrl, true);
        $data['sort_qty'] = $this->url->link('account/customerpartner/wk_quotes_admin/getList', '&sort=pq.quantity' . $tableUrl, true);
        $data['sort_product'] = $this->url->link('account/customerpartner/wk_quotes_admin/getList', '&sort=pd.name' . $tableUrl, true);
        $data['sort_price'] = $this->url->link('account/customerpartner/wk_quotes_admin/getList', '&sort=pq.price' . $tableUrl, $url);
        $data['sort_status'] = $this->url->link('account/customerpartner/wk_quotes_admin/getList', '&sort=pq.status' . $tableUrl, true);
        $data['sort_date'] = $this->url->link('account/customerpartner/wk_quotes_admin/getList', '&sort=pq.date_added' . $tableUrl, true);

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        $data['heading_title_list'] = $this->language->get('heading_title_list');
        $data['entry_name'] = $this->language->get('entry_name');

        $data['button_insert'] = $this->language->get('button_insert');
        $data['button_delete'] = $this->language->get('button_delete');
        $data['button_save'] = $this->language->get('button_save');

        //user
        $data['button_save'] = $this->language->get('button_save');
        $data['button_continue'] = $this->language->get('button_continue');
        $data['button_filter'] = $this->language->get('button_filter');
        $data['button_clrfilter'] = $this->language->get('button_clrfilter');

        $data['text_id'] = $this->language->get('text_id');
        $data['text_list'] = $this->language->get('text_list');
        $data['text_base_price'] = $this->language->get('text_base_price');
        $data['text_quotes_price'] = $this->language->get('text_quotes_price');
        $data['text_quantity'] = $this->language->get('text_quantity');
        $data['text_ask'] = $this->language->get('text_ask');
        $data['text_date'] = $this->language->get('text_date');
        $data['text_status'] = $this->language->get('text_status');
        $data['text_product_name'] = $this->language->get('text_product_name');
        $data['text_customer_name'] = $this->language->get('text_customer_name');
        $data['text_confirm'] = $this->language->get('text_r_u_sure');

        $data['text_action'] = $this->language->get('text_action');
        $data['text_no_recored'] = $this->language->get('text_no_recored');

        $result_total = $this->model_account_wk_quotes_admin->viewtotalentry($data);

        $results = $this->model_account_wk_quotes_admin->viewtotal($data);

        $data['result_quotelist'] = array();

        $customerIds = array_column($results, 'customer_id');
        $buyerCustomerModelMap = Customer::query()->with('buyer')->whereIn('customer_id', $customerIds)->get()->keyBy('customer_id');

        foreach ($results as $result) {

            $actiondelete = array();

            $action = array(
                'text' => $this->language->get('text_edit'),
                'href' => $this->url->link('account/customerpartner/wk_quotes_admin/update', '&id=' . $result['id'])
            );

            $result['status_text'] = $this->language->get('text_status_' . $result['status']);

            $data['result_quotelist'][] = array(
                'selected' => false,
                'id' => $result['id'],
                'customer_id' => $result['customer_id'],
                'customer_name' => $result['customer_name'],
                'is_home_pickup' => in_array($result['customer_group_id'], COLLECTION_FROM_DOMICILE),
                'email' => $result['email'],
                'name' => $result['name'],
                'product_id' => $result['product_id'],
                'href' => $this->url->link('product/product&product_id=' . $result['product_id']),
                'quantity' => $result['quantity'],
                'message' => substr(utf8_decode($result['message']), 0, 35),
                'price' => $this->currency->formatCurrencyPrice($result['price'], session('currency')),
                'baseprice' => $this->currency->formatCurrencyPrice($result['baseprice'], session('currency')),
                'status' => $result['status'],
                'status_text' => $result['status_text'],
                'date' => $result['date_added'],
                'action' => $action,
                'actiondelete' => $actiondelete,
                'sku' => $result['sku'],
                'mpn' => $result['mpn'],
                'agreement_no' => empty($result['agreement_no']) ? $result['id'] : $result['agreement_no'],
                'ex_vat' => VATToolTipWidget::widget(['customer' => $buyerCustomerModelMap->get($result['customer_id']), 'is_show_vat' => true])->render(),
            );
        }

        $data['quote_status'] = array(
            0 => $this->language->get('text_status_0'),
            1 => $this->language->get('text_status_1'),
            2 => $this->language->get('text_status_2'),
            3 => $this->language->get('text_status_3'),
            4 => $this->language->get('text_status_4'),
            5 => $this->language->get('text_status_5'),
        );

        $data['delete'] = $this->url->link('account/customerpartner/wk_quotes_admin/delete');
        $data['action'] = $this->url->link('account/customerpartner/wk_quotes_admin/getList');

        $data['total_pages'] = ceil($result_total / $page_limit);
        $data['page_num'] = $page;
        $data['pagination_url'] = $this->url->to(['account/customerpartner/wk_quotes_admin/getList']) . $url;
        $data['total_num'] = $result_total;
        $data['page_limit'] = $page_limit;

        $data['results'] = sprintf($this->language->get('text_pagination'), ($result_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($result_total - $this->config->get('config_limit_admin'))) ? $result_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $result_total, ceil($result_total / $this->config->get('config_limit_admin')));

        $data['sort'] = $sort;
        $data['order'] = $order;
        $data['filter_id'] = $filter_id;
        $data['filter_customer'] = $filter_customer;
        $data['filter_product'] = $filter_product;
        $data['filter_status'] = $filter_status;
        $data['filter_date_from'] = $filter_date_from;
        $data['filter_date_to'] = $filter_date_to;
        $data['filter_sku_mpn'] = $filter_sku_mpn;
        //        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');

        //用户画像
        $data['styles'][] = 'catalog/view/javascript/layer/theme/default/layer.css';
        $data['scripts'][] = 'catalog/view/javascript/layer/layer.js';
        $data['styles'][] = 'catalog/view/javascript/user_portrait/user_portrait.css?v=' . APP_VERSION;
        $data['scripts'][] = 'catalog/view/javascript/user_portrait/user_portrait.js?v=' . APP_VERSION;

        $this->response->setOutput($this->load->view('account/customerpartner/tab_quotes_admin', $data));

    }

    /**
     * 议价下载
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function download()
    {
        $filter = [
            'filter_id' => $this->request->get('filter_id', null),
            'filter_customer' => trim($this->request->get('filter_customer', null)),
            'filter_product' => trim($this->request->get('filter_product', null)),
            'filter_status' => $this->request->get('filter_status', null),
            'filter_date_from' => $this->request->get('filter_date_from', null),
            'filter_date_to' => $this->request->get('filter_date_to', null),
            'filter_sku_mpn' => trim($this->request->get('filter_sku_mpn', null)),
            'sort' => $this->request->get('sort', 'name'),
            'order' => $this->request->get('order', 'DESC'),
        ];
        $results = $this->model_account_wk_quotes_admin->viewtotal($filter);
        $spreadsheet = new Spreadsheet();
        $spreadsheet->setActiveSheetIndex(0);
        $sheet = $spreadsheet->getActiveSheet();
        $fileName = 'Spot Price Bids_' . date('Ymd');
        $sheet->setTitle($fileName);
        // 字体加粗
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        //宽度
        $sheet->getDefaultColumnDimension()->setWidth(20);
        //头部
        $headArr = [
            'Agreement ID', 'Buyer Name', 'Item Code(MPN)', 'Days', 'Time of Effect', 'Time of Failure', 'Requested product quantity', 'Agreement Requested Price Per Unit',
            'Agreement Amount', 'Purchased QTY', 'Remaining QTY', 'Agreement Status', 'Last Modified Date',
        ];
        $sheet->fromArray($headArr, null, 'A1');
        //居中显示
        $styleArray = array(
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER, // 水平居中
                'vertical' => Alignment::VERTICAL_CENTER // 垂直居中
            ]
        );
        //无数据
        if (empty($results)) {
            $sheet->fromArray(['No Records!'], null, 'A2');
            $sheet->mergeCells('A2:M2');
            $sheet->getStyle('A1:M2')->applyFromArray($styleArray);
        }

        //议价协议购买的数量
        $purchased_qty_arr = app(OrderRepository::class)->getQuotePurchasedQty(array_unique(array_column($results, 'id')));
        //内容
        $data = [];
        foreach ($results as $key => $val) {
            //seller审核通过时间
            $val['date_approved'] = $val['date_approved'] ? $val['date_approved'] == '1000-01-01 01:01:01' ? '' : $val['date_approved'] : '';
            $purchased_qty = isset($purchased_qty_arr[$val['id']]) ? $purchased_qty_arr[$val['id']] : 0;
            $remaining_qty = $val['quantity'] - $purchased_qty;
            $data[$key] = [
                empty($val['agreement_no']) ? $val['id'] : $val['agreement_no'],
                $val['customer_name'],
                $val['sku'] . '(' . $val['mpn'] . ')',
                '1',//产品说议价协议的有效期是1天
                $val['date_approved'] ? Carbon::parse($val['date_approved'])->timezone(CountryHelper::getTimezone($this->customer->getCountryId()))->toDateTimeString() : '',//议价协议生效的日期（取seller审核通过的时间）
                $val['date_approved'] ? Carbon::parse($val['date_approved'])->timezone(CountryHelper::getTimezone($this->customer->getCountryId()))->addDay(1)->toDateTimeString() : '',//生效时间+1天，计算出实效时间
                $val['quantity'],
                $this->currency->format($val['price'], $this->session->get('currency')),
                $this->currency->format($val['quantity'] * $val['price'], $this->session->get('currency')),
                $purchased_qty > 0 ? $purchased_qty : '0',
                $remaining_qty > 0 ? $remaining_qty : '0',
                SpotProductQuoteStatus::getDescription($val['status']),
                $val['date_added'] ? Carbon::parse($val['date_added'])->timezone(CountryHelper::getTimezone($this->customer->getCountryId()))->toDateTimeString() : '',
            ];
        }
        $sheet->fromArray($data, null, 'A2');
        $total = count($data) + 1;
        $sheet->getStyle('A1:A' . $total)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER)->applyFromArray($styleArray);
        $sheet->getStyle('B1:M' . $total)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT)->applyFromArray($styleArray);

        ob_end_clean();
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $fileName . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
    }

    public function update()
    {
        $this->language->load('account/customerpartner/wk_quotes_admin');
        $this->document->setTitle($this->language->get('heading_title_list'));
        $this->load->model('account/wk_quotes_admin');
        $data['heading_title_list'] = $this->language->get('heading_title_list');
        if ((request()->isMethod('POST'))) {
            //add by zjg 2019年12月17日16:46:07
            //需求 N-507
            //校验是否为申请状态，不是申请状态不允许议价
            $quote_info = $this->model_account_wk_quotes_admin->get_quote_info($this->request->post['quote_id']);
            if (empty($quote_info)) {
                $this->response->redirect($this->url->link('error/not_found'));
            }
            $quote_status = $quote_info['status'];
            if ($quote_status != 0) {
                if ($quote_status == 1) {
                    session()->set('error', $this->language->get('error_warning'));
                } elseif ($quote_status == 2) {
                    session()->set('error', $this->language->get('error_reject'));
                } elseif ($quote_status == 3) {
                    session()->set('error', $this->language->get('error_sold'));
                } else {
                    session()->set('error', $this->language->get('error_cancel'));
                }

                $this->response->redirect($this->url->link('account/customerpartner/wk_quotes_admin/update&id=' . $this->request->post['quote_id']));
            }

            if (!isset_and_not_empty($this->request->post, 'update_time') || $this->request->post['update_time'] != $quote_info['update_time']) {
                session()->set('error', $this->language->get('error_page_updated'));
                $this->session->data['update_quote_data'] = [
                    'message' => trim($this->request->post('message', '')),
                ];
                if (isset($this->request->post['status'])) {
                    $this->session->data['update_quote_data']['status'] = $this->request->post['status'];
                }
                $this->response->redirect($this->url->link('account/customerpartner/wk_quotes_admin/update&id=' . $this->request->post['quote_id']));
            }

            $this->model_account_wk_quotes_admin->updatebyid($this->request->post);

            session()->set('success', $this->language->get('text_success_update'));

            $this->response->redirect($this->url->link('account/customerpartner/wk_quotes_admin/update&id=' . $this->request->post['quote_id']));
        }
        $this->getForm();

    }

    protected function getForm()
    {
        if (isset($this->request->get['id'])) {
            $id = $this->request->get['id'];
        } else {
            $id = 0;
        }

        if (isset($this->request->get['filter_name'])) {
            $filter_name = $this->request->get['filter_name'];
        } else {
            $filter_name = null;
        }

        if (isset($this->request->get['filter_message'])) {
            $filter_message = $this->request->get['filter_message'];
        } else {
            $filter_message = null;
        }

        if (isset($this->request->get['filter_date_from'])) {
            $filter_date_from = $this->request->get['filter_date_from'];
        } else {
            $filter_date_from = null;
        }
        if (isset($this->request->get['filter_date_to'])) {
            $filter_date_to = $this->request->get['filter_date_to'];
        } else {
            $filter_date_to = null;
        }

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

        $page_limit = get_value_or_default(
            $this->request->request,
            'page_limit',
            $this->config->get('config_limit_admin')
        );
        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $tab = request()->get('tab', 'tab-general');
        if (!in_array($tab, ['tab-general', 'tab-messages'])) {
            $tab = 'tab-general';
        }

        $filterData = array(
            'filter_name' => $filter_name,
            'filter_message' => $filter_message,
            'filter_date_from' => $filter_date_from,
            'filter_date_to' => $filter_date_to,
            'filter_id' => $id,
            'sort' => $sort,
            'order' => $order,
            'start' => ($page - 1) * $page_limit,
            'limit' => $page_limit
        );

        $url = '';

        if (isset($this->request->get['filter_name'])) {
            $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_message'])) {
            $url .= '&filter_message=' . urlencode(html_entity_decode($this->request->get['filter_message'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_date_from'])) {
            $url .= '&filter_date_from=' . urlencode(html_entity_decode(changeOutPutByZone($this->request->get['filter_date_from'], $this->session), ENT_QUOTES, 'UTF-8'));
        }
        if (isset($this->request->get['filter_date_to'])) {
            $url .= '&filter_date_to=' . urlencode(html_entity_decode(changeOutPutByZone($this->request->get['filter_date_to'], $this->session), ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }

        if ($order == 'ASC') {
            $url .= '&order=DESC';
        } else {
            $url .= '&order=ASC';
        }

        $url .= '&id=' . $id;

        $data['sort_name'] = $this->url->link('account/customerpartner/wk_quotes_admin/update&tab=tab-messages&sort=pqm.writer' . $url);
        $data['sort_message'] = $this->url->link('account/customerpartner/wk_quotes_admin/update&tab=tab-messages&sort=pqm.message' . $url);
        $data['sort_date'] = $this->url->link('account/customerpartner/wk_quotes_admin/update&tab=tab-messages&sort=pqm.date' . $url);

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        $this->language->load('account/customerpartner/wk_quotes_admin');

        $data['button_back'] = $this->language->get('button_back');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_alert'] = $this->language->get('button_alert');
        $data['button_table'] = $this->language->get('button_table');
        $data['button_filter'] = $this->language->get('button_filter');
        $data['button_clrfilter'] = $this->language->get('button_clrfilter');

        $data['tab_quote'] = $this->language->get('tab_quote');
        $data['text_message'] = $this->language->get('text_message');
        $data['text_details'] = $this->language->get('text_details');
        $data['text_id'] = $this->language->get('text_id');
        $data['text_send_message'] = $this->language->get('text_send_message');
        $data['text_base_price'] = $this->language->get('text_base_price');
        $data['text_quotes_price'] = $this->language->get('text_quotes_price');
        $data['text_quantity'] = $this->language->get('text_quantity');
        $data['text_ask'] = $this->language->get('text_ask');
        $data['text_date_from'] = $this->language->get('text_date_from');
        $data['text_date_to'] = $this->language->get('text_date_to');
        $data['text_status'] = $this->language->get('text_status');
        $data['text_customer_name'] = $this->language->get('text_customer_name');
        $data['text_product_name'] = $this->language->get('text_product_name');
        $data['text_no_recored'] = $this->language->get('text_no_recored');

        $data['text_order_id'] = $this->language->get('text_order_id');
        $data['text_date_used'] = $this->language->get('text_date_used');
        $data['text_discount'] = $this->language->get('text_discount');
        $data['text_sold_info'] = $this->language->get('text_sold_info');

        $data['text_me'] = $this->language->get('text_me');
        $data['text_option'] = $this->language->get('text_option');
        $data['text_option_name'] = $this->language->get('text_option_name');
        $data['text_option_price'] = $this->language->get('text_option_price');
        $data['text_option_value'] = $this->language->get('text_option_value');
        $data['text_edit_quote'] = $this->language->get('text_edit_quote');

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('Seller Central'),
            'href' => $this->url->link('customerpartner/seller_center/index', '', true),
            'separator' => $this->language->get('text_separator')
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title_list'),
            'href' => $this->url->link('account/customerpartner/wk_quotes_admin', '', true),
            'separator' => $this->language->get('text_separator')
        );
        $data['breadcrumbs'][] = array(
            'text' => 'Spot Price Bid',
            'href' => $this->url->link('account/customerpartner/wk_quotes_admin' . $url),
            'separator' => $this->language->get('text_separator')
        );
        $data['breadcrumbs'][] = array(
            'text' => 'Spot Price Details',
            'href' => $this->url->link('account/customerpartner/wk_quotes_admin' . $url),
            'separator' => $this->language->get('text_separator')
        );

        $data['save'] = $this->url->link('account/customerpartner/wk_quotes_admin/update');

        $data['back'] = $this->url->link('account/customerpartner/wk_quotes_admin');

        $this->load->model('account/wk_quotes_admin');
        $this->load->model('tool/image');

        $data['admin_name'] = $this->config->get('total_wk_pro_quote_name');

        $data['result_quoteadmin'] = array();
        //add by zjg  2019年12月18日13:43:47
        //添加消息是否属于个人验证

        $quota_costomer = $this->model_account_wk_quotes_admin->check_quota($id);
        if (!$quota_costomer || $quota_costomer['customer_id'] != $this->customer->getId()) {    //不属于该用户返回跳not found 页面
            $this->response->redirect($this->url->link('error/not_found'));
        } else {
            $data['results_message'] = $this->model_account_wk_quotes_admin->viewtotalMessageBy($filterData);
            $results_message_total = $this->model_account_wk_quotes_admin->viewtotalNoMessageBy($filterData);
            $result = $this->model_account_wk_quotes_admin->viewQuoteByid($id);
        }

        $data['heading_title_view'] = sprintf($this->language->get('heading_title_view'), $result['agreement_no'] ?? $id);

        if ($result) {

            $result['status_text'] = $this->language->get('text_status_' . $result['status']);

            $product = unserialize(base64_decode($result['product_key']));

            if (!empty($product['option'])) {
                $options = $this->model_account_wk_quotes_admin->getProductOptions($product['option'], $result['product_id']);
            } else {
                $options = array();
            }

            if ($result['image']) {
                $image = $this->model_tool_image->resize($result['image'], 200, 200);
            } else {
                $image = $this->model_tool_image->resize('placeholder.png', 200, 200);
            }

            $origin_price = $this->currency->formatCurrencyPrice($result['origin_price'], session('currency'));

            $data['result_quoteadmin'] = array(
                'id' => $result['id'],
                'agreement_id' => $result['agreement_no'] ?: $result['id'],
                'email' => $result['email'],
                'customer_id' => $result['customer_id'],
                'customer_name' => $result['customer_name'],
                'is_home_pickup' => in_array($result['customer_group_id'], COLLECTION_FROM_DOMICILE),
                'product_name' => $result['name'],
                'product_id' => $result['product_id'],
                'product_href' => $this->url->link('product/product&product_id=' . $result['product_id']),
                'order_id' => $result['order_id'],
                'date_used' => $result['date_used'],
                'orderhref' => $this->url->link('account/customerpartner/orderinfo&order_id=' . $result['order_id']),
                'options' => $options,
                'image' => $image,
                'quantity' => $result['quantity'],
                'message' => $result['message'],
                'price' => $this->currency->formatCurrencyPrice($result['price'], session('currency')),
                'amount' => $this->currency->formatCurrencyPrice($result['amount'], session('currency')),
                'status' => $result['status'],
                'status_text' => $result['status_text'],
                'baseprice' => $origin_price,
                'update_time' => $result['update_time'],
                'ex_vat' => VATToolTipWidget::widget(['customer' => Customer::query()->find($result['customer_id']), 'is_show_vat' => true])->render(),
            );
        }

        $data['is_check_price_freight'] = 0;
        if ($this->customer->isNonInnerAccount() && !in_array($result['customer_group_id'], COLLECTION_FROM_DOMICILE)) {
            $this->load->model('common/product');
            $alarm_price = $this->model_common_product->getAlarmPrice((int)$result['product_id']);
            if (bccomp($result['price'], $alarm_price, 4) === -1) {
                $data['is_check_price_freight'] = 1;
            }
        }

        switch ($result['status']) {
            case 0:
                $data['quote_status'] = [
                    0 => $this->language->get('text_status_0'),
                    1 => $this->language->get('text_status_1'),
                    2 => $this->language->get('text_status_2'),
                ];
                break;
            default:
                if (in_array($result['status'], [1, 2, 3, 4, 5])) {
                    $data['quote_status'] = [
                        $result['status'] => $this->language->get('text_status_' . $result['status'])
                    ];
                }
                break;
        }

        if (isset($this->error['warning']) || isset($this->session->data['error'])) {
            if (isset($this->session->data['error'])) {
                $data['error_warning'] = session('error');
                $this->session->remove('error');
            } else {
                $data['error_warning'] = $this->error['warning'];
            }
        } else {
            $data['error_warning'] = '';
        }


        if (isset($this->session->data['success'])) {
            $data['success'] = session('success');
            $this->session->remove('success');
        } else {
            $data['success'] = '';
        }

        if (isset($this->session->data['update_quote_data'])) {
            $data['update_quote_data'] = session('update_quote_data');
            $this->session->remove('update_quote_data');
        } else {
            $data['update_quote_data'] = '';
        }

        $url = '';
        if (isset($this->request->get['filter_name'])) {
            $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_message'])) {
            $url .= '&filter_message=' . urlencode(html_entity_decode($this->request->get['filter_message'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_date'])) {
            $url .= '&filter_date=' . urlencode(html_entity_decode($this->request->get['filter_date'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        $url .= '&id=' . $id;

        $data['seller_id'] = $this->customer->getId();

        //        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/moment.js');
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');
        //用户画像
        $this->document->addStyle('catalog/view/javascript/layer/theme/default/layer.css');
        $this->document->addScript('catalog/view/javascript/layer/layer.js');
        $this->document->addStyle('catalog/view/javascript/user_portrait/user_portrait.css?v=' . APP_VERSION);
        $this->document->addScript('catalog/view/javascript/user_portrait/user_portrait.js?v=' . APP_VERSION);

        $pagination = new Pagination();
        $pagination->total = $results_message_total;
        $pagination->page = $page;
        $pagination->limit = $page_limit;
        $pagination->text = $this->language->get('text_pagination');
        $pagination->url = $this->url->link('account/customerpartner/wk_quotes_admin/update', $url . '&page={page}&tab=tab-messages');

        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($results_message_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($results_message_total - $this->config->get('config_limit_admin'))) ? $results_message_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $results_message_total, ceil($results_message_total / $this->config->get('config_limit_admin')));

        $data['sort'] = $sort;
        $data['order'] = $order;
        $data['filter_name'] = $filter_name;
        $data['filter_message'] = $filter_message;
        $data['filter_date_from'] = $filter_date_from;
        $data['filter_date_to'] = $filter_date_to;
        $data['tab'] = $tab;

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        if ($this->config->get('marketplace_separate_view') && isset($this->session->data['marketplace_separate_view']) && session('marketplace_separate_view') == 'separate') {
            $data['separate_view'] = true;
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $data['margin'] = "margin-left: 18%";
            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        }
        // 是否显示云送仓提醒
        $data['is_show_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();

        $this->response->setOutput($this->load->view('account/customerpartner/wk_quotes_admin_details', $data));

    }

    public function delete()
    {

        $this->language->load('account/customerpartner/wk_quotes_admin');

        $this->document->setTitle($this->language->get('heading_title_list'));

        $this->load->model('account/wk_quotes_admin');

        if (isset($this->request->post['selected'])) {
            foreach ($this->request->post['selected'] as $id) {
                $this->model_account_wk_quotes_admin->deleteentry($id);
            }

            session()->set('success', $this->language->get('text_success'));

            $url = '';

            if (isset($this->request->get['page'])) {
                $url .= '&page=' . $this->request->get['page'];
            }

            $this->response->redirect($this->url->link('account/customerpartner/wk_quotes_admin', $url));
        }

        $this->getList();
    }
}
