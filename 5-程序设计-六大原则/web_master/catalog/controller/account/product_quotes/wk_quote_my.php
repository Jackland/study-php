<?php

use App\Catalog\Controllers\AuthController;
use App\Catalog\Search\Margin\MarginAgreementSearch;
use App\Enums\Spot\SpotProductQuoteStatus;
use App\Helper\CountryHelper;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\Product\ProductPriceRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Repositories\Order\OrderRepository;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Class ControllerAccountProductQuoteswkquotemy
 */
class ControllerAccountProductQuoteswkquotemy extends AuthController
{
    /*
    for status 0 - Applied
    status 1 - Approve
    status 2 - Rejected
    status 3 - Sold
    status 4 - Time out
    status 5 - Canceled
    */

    /**
     * @var array
     */
    private $error = array();

    /**
     * @var array
     */
    private $data = array();

    /**
     * @var mixed|null
     */
    protected $customer_id = null;

    /**
     * 四舍五入精度
     * 日本不保留小数位，其他国家保留两位
     *
     * @var int $precision
     */
    private $precision;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        if ($this->customer->isPartner()) {
            $this->redirect(['account/customerpartner/wk_quotes_admin'])->send();
        }

        $this->customer_id = $this->customer->getId();
        $this->precision = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0 : 2;

        $this->setLanguages(['account/product_quotes/wk_product_quotes']);
    }

    /**
     * bid list 的 tab页面
     * @param ModelFuturesAgreement $modelFuturesAgreement
     * @param ModelAccountProductQuotesMargin $modelAccountProductQuotesMargin
     * @param ModelAccountProductQuoteswkproductquotes $modelAccountProductQuoteswkproductquotes
     * @return string
     */
    public function index(ModelFuturesAgreement $modelFuturesAgreement, ModelAccountProductQuotesMargin $modelAccountProductQuotesMargin, ModelAccountProductQuoteswkproductquotes $modelAccountProductQuoteswkproductquotes)
    {
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['success'] = '';
        if ($this->session->has('success')) {
            $data['success'] = $this->session->get('success');
            $this->session->remove('success');
        }

        $this->setDocumentInfo($this->language->get('heading_title_my'));
        $tab = $this->request->query->get('tab', 0);

        $data['quote_enable'] = false;
        if (!empty($this->customer->getCountryId()) && in_array($this->customer->getCountryId(), QUOTE_ENABLE_COUNTRY)) {
            $data['quote_enable'] = true;
        }
        if (0 == $tab && $data['quote_enable'] == false) {
            $tab = 1;
        }

        $data['tab'] = $tab;
        $data['no'] = request('no');
        //$count_mwp = $modelAccountProductQuotesMargin->getTabMarkCount();
        $marginTotal = (new MarginAgreementSearch(customer()->getId()))->getStatisticsNumber([],true);
        $count_mwp =  $marginTotal['total_number'];
        $futures_num = $modelFuturesAgreement->totalForBuyer($this->customer->getId());
        $spotNum = $modelAccountProductQuoteswkproductquotes->buyerQuoteAgreementsSomeStatusCount($this->customer_id, [0, 1]);
        $data['margin_tab_mark_count'] = $count_mwp > 99 ? '99+' : ($count_mwp > 0 ? (int)$count_mwp : '');
        $data['futures_num'] = $futures_num > 99 ? '99+' : ($futures_num > 0 ? (int)$futures_num : '');
        $data['spot_num'] = $spotNum > 99 ? '99+' : ($spotNum > 0 ? $spotNum : '');

        return $this->render('account/product_quotes/list_quotes', $data, 'buyer');
    }

    /**
     * bid list 的 spot price列表
     * @param ModelAccountProductQuoteswkproductquotes $modelAccountProductQuoteswkproductquotes
     * @param ModelToolImage $modelToolImage
     * @return string
     */
    public function getQuotesList(ModelAccountProductQuoteswkproductquotes $modelAccountProductQuoteswkproductquotes, ModelToolImage $modelToolImage)
    {
        $this->document->addScript('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js');
        $this->document->addStyle('catalog/view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css');

        $filter_id = trim($this->request->query->get('filter_id', null));
        $filter_product = trim($this->request->query->get('filter_product', null));
        $filter_status = $this->request->query->get('filter_status', null);
        $filter_sku_mpn = trim($this->request->query->get('filter_sku_mpn', null));
        $filter_date_from = $this->request->query->get('filter_date_from', null);
        $filter_date_to = $this->request->query->get('filter_date_to', null);
        $sort = $this->request->query->get('sort', 'name');
        $order = $this->request->query->get('order', 'DESC');
        $page = $this->request->query->get('page', 1);
        $limit = $this->request->query->get('page_limit', 15);
        0 == $limit && $limit = 15;

        $data = array(
            'filter_id' => $filter_id,
            'filter_product' => $filter_product,
            'filter_sku_mpn' => $filter_sku_mpn,
            'filter_status' => $filter_status,
            'filter_date_from' => $filter_date_from,
            'filter_date_to' => $filter_date_to,
            'sort' => $sort,
            'order' => $order,
            'start' => ($page - 1) * $limit,
            'limit' => $limit,
        );

        $url = $this->url->to('account/product_quotes/wk_quote_my/getQuotesList');
        $url .= !is_null($filter_id) ? "&filter_id={$filter_id}" : '';
        $url .= !is_null($filter_product) ? ("&filter_product=" . urlencode(html_entity_decode($filter_product, ENT_QUOTES, 'UTF-8'))) : '';
        $url .= !is_null($filter_status) ? "&filter_status={$filter_status}" : '';
        $url .= !is_null($filter_sku_mpn) ? "&filter_sku_mpn={$filter_sku_mpn}" : '';
        $url .= !is_null($filter_date_from) ? ("&filter_date_from=" . urlencode(html_entity_decode(changeOutPutByZone($filter_date_from, $this->session), ENT_QUOTES, 'UTF-8'))) : '';
        $url .= !is_null($filter_date_to) ? ("&filter_date_to=" . urlencode(html_entity_decode(changeOutPutByZone($filter_date_to, $this->session), ENT_QUOTES, 'UTF-8'))) : '';

        //用于table的header部分切换正逆序
        $tableUrl = $url;
        if ($order == 'ASC') {
            $url .= '&order=ASC';
            $tableUrl .= '&order=DESC';
        } else {
            $url .= '&order=DESC';
            $tableUrl .= '&order=ASC';
        }
        $data['sort_id'] = $tableUrl . '&sort=pd.id';
        $data['sort_qty'] = $tableUrl . '&sort=pd.quantity';
        $data['sort_sku'] = $tableUrl . '&sort=pd.sku';
        $data['sort_product'] = $tableUrl . '&sort=pd.name';
        $data['sort_price'] = $tableUrl . '&sort=pd.price';
        $data['sort_status'] = $tableUrl . '&sort=pd.status';
        $data['sort_date'] = $tableUrl . '&sort=pd.date_added';

        $url .= !empty($sort) ? "&sort={$sort}" : '';

        $results = $modelAccountProductQuoteswkproductquotes->getProductQuotes($data);
        $resultTotal = $modelAccountProductQuoteswkproductquotes->getProductQuotesTotal($data);
        $data['result_quotelist'] = array();
        $unsupportStockMap = app(CustomerRepository::class)->getUnsupportStockData(array_column($results,'product_id'));
        foreach ($results as $result) {
            $action = array(
                'text' => $this->language->get('text_view'),
                'href' => $this->url->link('account/product_quotes/wk_quote_my/view', '&product_id=' . $result['product_id'] . '&id=' . $result['id'])
            );

            $actionCancel = array(
                'text' => $this->language->get('text_cancel'),
            );

            $result['status_text'] = $this->language->get('text_status_' . $result['status']);

            if ($result['image']) {
                $image = $modelToolImage->resize($result['image'], 30, 30);
            } else {
                $image = $modelToolImage->resize('placeholder.png', 30, 30);
            }

            $data['result_quotelist'][] = array(
                'id' => $result['id'],
                'agreement_no' => empty($result['agreement_no']) ? $result['id'] : $result['agreement_no'],
                'name' => $result['name'],
                'unsupport_stock' => in_array($result['product_id'],$unsupportStockMap),
                'product_id' => $result['product_id'],
                'image' => $image,
                'href' => $this->url->link('product/product&product_id=' . $result['product_id']),
                'quantity' => $result['quantity'],
                'message' => substr(utf8_decode($result['message']), 0, 35),
                'price' => $this->currency->format($result['price'], $this->session->get('currency')),
                'baseprice' => $this->currency->format($result['baseprice'], $this->session->get('currency')),
                'status' => $result['status'],
                'status_text' => $result['status_text'],
                'date' => $result['date_added'],
                'action' => $action,
                'action_cancel' => $actionCancel,
                'sku' => $result['sku'],
                'mpn' => $result['mpn'],
                'update_time' => $result['update_time'],
                'screenname' => $result['screenname'],
            );

        }

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['success'] = '';
        if ($this->session->has('success')) {
            $data['success'] = $this->session->get('success');
            $this->session->remove('success');
        }

        $data['quote_status'] = array(
            0 => $this->language->get('text_status_0'),
            1 => $this->language->get('text_status_1'),
            2 => $this->language->get('text_status_2'),
            3 => $this->language->get('text_status_3'),
            4 => $this->language->get('text_status_4'),
            5 => $this->language->get('text_status_5'),
        );

        $data['action'] = $this->url->link('account/product_quotes/wk_quote_my/getQuotesList', '', true);

        $data['back'] = $this->url->link('account/account');
        $data['addToCartQuote'] = $this->url->link('account/product_quotes/wk_quote_my/addToCartQuote');
        $data['cancel_url'] = $this->url->link('account/product_quotes/wk_quote_my/cancel', '', true);

        $data['total_pages'] = ceil($resultTotal / $limit);
        $data['page_num'] = $page;
        $data['total'] = $resultTotal;
        $data['page_limit'] = $limit;
        $data['pagination_url'] = $url;
        $data['sort'] = $sort;
        $data['order'] = $order;
        $data['filter_id'] = $filter_id;
        $data['filter_product'] = $filter_product;
        $data['filter_sku_mpn'] = $filter_sku_mpn;
        $data['filter_status'] = $filter_status;
        $data['filter_date_from'] = $filter_date_from;
        $data['filter_date_to'] = $filter_date_to;
        $data['continue'] = $this->url->to('common/home');
        $data['is_home_pickup'] = $this->customer->isCollectionFromDomicile();
        $data['is_USA'] = $this->customer->isUSA();

        return $this->render('account/product_quotes/tab_quotes', $data, [
            'header' => 'common/header',
            'footer' => 'common/footer',
            'column_left' => 'common/column_left',
            'column_right' => 'common/column_right',
            'content_top' => 'common/content_top',
            'content_bottom' => 'common/content_bottom',
        ]);
    }

    /**
     * 议价下载
     * @param ModelAccountProductQuoteswkproductquotes $modelAccountProductQuoteswkproductquotes
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function download(ModelAccountProductQuoteswkproductquotes $modelAccountProductQuoteswkproductquotes)
    {
        $filterArr = [
            'filter_id' => trim($this->request->get('filter_id', null)),
            'filter_product' => trim($this->request->get('filter_product', null)),
            'filter_status' => $this->request->get('filter_status', null),
            'filter_sku_mpn' => trim($this->request->get('filter_sku_mpn', null)),
            'filter_date_from' => $this->request->get('filter_date_from', null),
            'filter_date_to' => $this->request->get('filter_date_to', null),
            'sort' => $this->request->get('filter_date_to', 'name'),
            'order' => $this->request->get('order', 'DESC'),
        ];
        $results = $modelAccountProductQuoteswkproductquotes->getProductQuotes($filterArr);

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
            'Agreement ID', 'Store', 'Item Code', 'Days', 'Time of Effect', 'Time of Failure', 'Quantity Requested', 'Agreement Requested Price Per Unit',
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
                $val['screenname'],
                $val['sku'],
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

    /**
     * spot price details页面
     * @param ModelAccountProductQuoteswkproductquotes $modelAccountProductQuoteswkproductquotes
     * @param ModelToolImage $modelToolImage
     * @return string|RedirectResponse
     * @throws Exception
     */
    public function view(ModelAccountProductQuoteswkproductquotes $modelAccountProductQuoteswkproductquotes, ModelToolImage $modelToolImage)
    {
        $data['error_access'] = false;
        $quote_id = $this->request->query->get('id', 0);
        $data['error_access'] = empty($quote_id);
        $product_id = $this->request->query->get('product_id', 0);
        $data['error_access'] = empty($product_id);

        $filter_id = $this->request->query->get('filter_id', null);
        $filter_product = $this->request->query->get('filter_product', null);
        $filter_qty = $this->request->query->get('filter_qty', null);
        $filter_status = $this->request->query->get('filter_status', null);
        $filter_price = $this->request->query->get('filter_price', null);
        $filter_date = $this->request->query->get('filter_date', null);
        $sort = $this->request->query->get('sort', 'name');
        $order = $this->request->query->get('order', 'ASC');
        $page = $this->request->query->get('page', 1);
        $limit = 1000;

        $url = '';
        $url .= !is_null($filter_id) ? "&filter_id={$filter_id}" : '';
        $url .= !is_null($filter_product) ? ("&filter_id=" . urlencode(html_entity_decode($filter_product, ENT_QUOTES, 'UTF-8'))) : '';
        $url .= !is_null($filter_qty) ? "&filter_qty={$filter_qty}" : '';
        $url .= !is_null($filter_status) ? "&filter_status={$filter_status}" : '';
        $url .= !is_null($filter_price) ? "&filter_price={$filter_price}" : '';
        $url .= !is_null($filter_date) ? ("&filter_date=" . urlencode(html_entity_decode($filter_date, ENT_QUOTES, 'UTF-8'))) : '';
        $url .= !empty($page) ? "&page={$page}" : '';
        $url .= $this->request->query->has('pageMessage') ? ("&pageMessage=" . $this->request->query->get('pageMessage')) : '';
        $url .= $order == 'ASC' ? '&order=DESC' : '&order=ASC';

        $data['sort_id'] = $this->url->link('account/product_quotes/wk_quote_my', '' . '&sort=pq.id' . $url, true);
        $data['sort_qty'] = $this->url->link('account/product_quotes/wk_quote_my', '' . '&sort=pq.quantity' . $url, true);
        $data['sort_product'] = $this->url->link('account/product_quotes/wk_quote_my', '' . '&sort=pq.product_id' . $url, true);
        $data['sort_price'] = $this->url->link('account/product_quotes/wk_quote_my', '' . '&sort=pq.price' . $url, true);
        $data['sort_status'] = $this->url->link('account/product_quotes/wk_quote_my', '' . '&sort=pq.status' . $url, true);
        $data['sort_date'] = $this->url->link('account/product_quotes/wk_quote_my', '' . '&sort=pq.date_added' . $url, true);

        $url .= !empty($sort) ? "&sort={$sort}" : '';

        $pageMessage = $page;
        $start = ($pageMessage - 1) * $limit;

        $this->document->addStyle('catalog/view/theme/default/stylesheet/product_quote/style.css?v=' . APP_VERSION);

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home' . $url),
                'separator' => false
            ],
            [
                'text' => $this->language->get('heading_title_my'),
                'href' => $this->url->link('account/product_quotes/wk_quote_my' . $url),
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('heading_title_quote'),
                'href' => $this->url->link('account/product_quotes/wk_quote_my/view&product_id=' . $product_id . '&id=' . $quote_id . $url),
                'separator' => $this->language->get('text_separator')
            ]
        ];

        $result = $modelAccountProductQuoteswkproductquotes->getProductQuoteDetailForHistory($quote_id);
        //$minQuantity = $modelAccountProductQuoteswkproductquotes->getMinQuantity($product_id);
        $minQuantity = 1;
        $maxQuantity = $this->orm->table(DB_PREFIX . 'customerpartner_to_product')->where('product_id', $product_id)->value('quantity');

        $data['heading_title'] = sprintf($this->language->get('heading_title_quote_detail_long'), $result['agreement_no'] ?? $quote_id);
        $this->setDocumentInfo($data['heading_title']);

        $data['seller_min_quantity'] = $minQuantity;
        $data['seller_max_quantity'] = intval($maxQuantity);

        $data['is_japan'] = $this->customer->getCountryId() == JAPAN_COUNTRY_ID;
        if (($this->request->serverBag->get('REQUEST_METHOD') == 'POST') && $quote_id) {
            if ($this->request->input->get('message') && $result) {
                $isRedirect = false;

                $updateTime = $this->request->input->get('update_time', null);
                if (empty($updateTime) || $updateTime != $result['update_time']) {
                    $this->session->set('error', $this->language->get('error_page_updated'));
                    return $this->response->redirectTo($this->url->to('account/product_quotes/wk_quote_my/view&id=' . $quote_id . '&product_id=' . $product_id));
                }

                if ($this->request->input->has('quote_edit')) {
                    //验证 buyer 修改的议价数量最小值是否小于 seller配置的最小值
                    // 2019/11/6 wangjinxin 删除对于最小议价数量的校验
                    //2019年12月24日 add by zjg   添加对状态的校验
                    $quote_status = $modelAccountProductQuoteswkproductquotes->check_quota($quote_id);
                    if ($quote_status['status'] == 0) {
                        $modelAccountProductQuoteswkproductquotes->updateQuoteData($quote_id, $this->request->input->all());
                        $isRedirect = true;
                    } else {
                        $this->session->set('error', $this->language->get('error_warning_quota_change'));
                    }

                } else {
                    $modelAccountProductQuoteswkproductquotes->addQuoteMessage($quote_id, $this->request->input->all());
                    $isRedirect = true;
                }

                //如果跳转，则跳转到成功页面
                if ($isRedirect) {
                    $this->session->set('success', $this->language->get('text_success_message'));
                    return $this->response->redirectTo($this->url->to('account/product_quotes/wk_quote_my/view&id=' . $quote_id . '&product_id=' . $product_id));
                }
            }

        }

        $data['result'] = array();
        if ($result) {
            // #31737 议价详情页原价处理
            $result['baseprice'] = app(ProductPriceRepository::class)->getProductActualPriceByBuyer($result['seller_id'], customer()->getModel(), $result['baseprice']);
            $result['agreement_no'] = $result['agreement_no'] ?? $result['id'];
            $result['status_text'] = $this->language->get('text_status_' . $result['status']);
            $result['href'] = $this->url->link('product/product&product_id=' . $result['product_id']);
            $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
            if ($isCollectionFromDomicile && !$result['product_display']) {
                $result['baseprice'] = sprintf('%.2f', round($result['baseprice'], 2));
                if ($result['baseprice'] < 0) {
                    $result['baseprice'] = 0;
                }
            }
            //该原价取折扣价 oc_product_quote.discount_price
            $result['baseprice'] = $this->currency->format($result['baseprice'], $this->session->get('currency'));
            $result['amount'] = $this->currency->format($result['amount'], $this->session->get('currency'));
            $result['orderhref'] = $this->url->link('account/order/purchaseOrderInfo&order_id=' . $result['order_id']);
            $result['message'] = html_entity_decode($result['message']);

            $product = unserialize(base64_decode($result['product_key']));

            if (!empty($product['option'])) {
                $result['option'] = $modelAccountProductQuoteswkproductquotes->getProductOptions($product['option'], $result['product_id']);
            } else {
                $result['option'] = array();
            }

            if ($result['image']) {
                $result['image'] = $modelToolImage->resize($result['image'], 200, 200);
            } else {
                $result['image'] = $modelToolImage->resize('placeholder.png', 200, 200);
            }

            $result['messages'] = $modelAccountProductQuoteswkproductquotes->getQuoteMessage($quote_id, $start, $limit);
        } else {
            $data['error_access'] = true;
        }
        $data['result'] = $result;
        $data['admin_name'] = $this->config->get('total_wk_pro_quote_name');

        if (isset($this->error['warning']) || $this->session->has('error')) {
            if (isset($this->error['warning'])) {
                $data['error_warning'] = $this->error['warning'];
            } else {
                $data['error_warning'] = $this->session->get('error');
                $this->session->remove('error');
            }
        } else {
            $data['error_warning'] = '';
        }

        if ($this->session->has('success')) {
            $data['success'] = $this->session->get('success');
            $this->session->remove('success');
        } else {
            $data['success'] = '';
        }
        $data['customer_id'] = $this->customer->getId();
        $data['action'] = $this->url->link('account/product_quotes/wk_quote_my/view&id=' . $quote_id . '&product_id=' . $product_id);
        $data['back'] = $this->url->link('account/product_quotes/wk_quote_my');

        return $this->render('account/product_quotes/view_quote', $data, [
            'header' => 'common/header',
            'footer' => 'common/footer',
            'column_left' => 'common/column_left',
            'column_right' => 'common/column_right',
            'content_top' => 'common/content_top',
            'content_bottom' => 'common/content_bottom',
        ]);

    }

    /**
     * 删除议价协议
     * @param ModelAccountProductQuoteswkproductquotes $modelAccountProductQuoteswkproductquotes
     * @return RedirectResponse
     */
    public function delete(ModelAccountProductQuoteswkproductquotes $modelAccountProductQuoteswkproductquotes)
    {
        if ($this->request->query->has('id') and $this->customer->getId()) {
            $modelAccountProductQuoteswkproductquotes->deleteentry($this->request->query->get('id'));
            $this->session->set('success', $this->language->get('text_success_delete'));
        }

        return $this->response->redirectTo($this->url->link('account/product_quotes/wk_quote_my'));
    }

    /**
     * buyer 取消议价
     * 注： 只有在状态为 0(pending) 时才能取消.
     * @param ModelAccountProductQuoteswkproductquotes $modelAccountProductQuoteswkproductquotes
     * @return JsonResponse
     */
    public function cancel(ModelAccountProductQuoteswkproductquotes $modelAccountProductQuoteswkproductquotes)
    {

        $json = [];
        if ($this->request->serverBag->get('REQUEST_METHOD') == 'POST') {
            if ($this->request->input->has('id') and $this->customer->getId()) {

                $updateTime = $this->request->input->get('update_time', null);
                if (empty($updateTime)) {
                    $json['error'] = $this->language->get('error_page_updated');
                    return $this->response->json($json);
                }

                $info = $modelAccountProductQuoteswkproductquotes->getSingleDataForCheck($this->request->input->get('id'), $this->customer->getId());
                if (!isset_and_not_empty($info, 'update_time') || $info->update_time != $updateTime) {
                    $json['error'] = $this->language->get('error_page_updated');
                    return $this->response->json($json);
                }

                $modelAccountProductQuoteswkproductquotes->cancel($this->request->input->get('id'), $this->customer->getID());
                $json['success'] = $this->language->get('text_success_cancel');
            }
        }

        return $this->response->json($json);
    }

    /**
     * 添加进购物车
     * @param ModelAccountProductQuoteswkproductquotes $modelAccountProductQuoteswkproductquotes
     * @param ModelCheckoutCart $modelCheckoutCart
     * @return JsonResponse
     * @throws Exception
     */
    public function addToCartQuote(ModelAccountProductQuoteswkproductquotes $modelAccountProductQuoteswkproductquotes, ModelCheckoutCart $modelCheckoutCart)
    {
        $json = array();
        if ($this->request->serverBag->get('REQUEST_METHOD') == 'POST') {
            $quote_id = $this->request->input->get('id');

            if (!$this->request->input->has('type') || !in_array($this->request->input->get('type'), [0, 1, 2])) {
                $json['error'] = $this->language->get('error_warning_quota_change');
                return $this->response->json($json);
            }

            $deliveryType = $this->request->input->get('type');

            if ($this->customer->isCollectionFromDomicile() && $deliveryType != 1) {
                $json['error'] = $this->language->get('error_warning_quota_change');
                return $this->response->json($json);
            }
            // 只有 美国 才有 云送仓
            if ($this->customer->isUSA() && !$this->customer->isCollectionFromDomicile() && !in_array($deliveryType, [0, 2])) {
                $json['error'] = $this->language->get('error_warning_quota_change');
                return $this->response->json($json);
            }

            $result = $modelAccountProductQuoteswkproductquotes->getProductQuoteDetail($quote_id);
            if (empty($result)) {
                $json['error'] = $this->language->get('text_error_add_to_cart');
                return $this->response->json($json);
            }

            $this->setLanguages('checkout/cart');
            $count = $modelCheckoutCart->verifyProductAdd($result['product_id'], 4, $result['id'], $deliveryType);
            if ($count) {
                $json['error'] = $this->language->get('error_transaction_add_cart');
                return $this->response->json($json);
            }

            $result['baseprice'] = round($result['baseprice'], $this->precision);
            $product = unserialize(base64_decode($result['product_key']));
            $option_data = array();

            $result['option'] = array();
            if (!empty($product['option'])) {
                $result['option'] = $modelAccountProductQuoteswkproductquotes->getProductOptions($product['option'], $result['product_id']);
            }

            foreach ($result['option'] as $option) {
                if ($option['type'] == 'select' || $option['type'] == 'radio' || $option['type'] == 'image') {
                    $option_data[$option['product_option_id']] = $option['product_option_value_id'];
                } elseif ($option['type'] == 'checkbox') {
                    $option_data[$option['product_option_id']][] = $option['product_option_value_id'];
                } elseif ($option['type'] == 'text' || $option['type'] == 'textarea' || $option['type'] == 'date' || $option['type'] == 'datetime' || $option['type'] == 'time') {
                    $option_data[$option['product_option_id']] = $option['value'];
                } elseif ($option['type'] == 'file') {
                    $option_data[$option['product_option_id']] = $this->encryption->encrypt($option['value']);
                }
            }

            if ($result and $result['status'] == '1') {
                // 判断议价单有效期
                $timeOutDays = empty($this->config->get('total_wk_pro_quote_time')) ? 999 : $this->config->get('total_wk_pro_quote_time');
                if (strtotime($result['date_approved']) + $timeOutDays * 86400 >= time()) {
                    if ($modelAccountProductQuoteswkproductquotes->cartExistSpotAgreement($this->customer_id, $result['product_id'], $result['id'], $deliveryType)) {
                        $json['error'] = $this->language->get('error_transaction_add_cart_exist');
                        return $this->response->json($json);
                    }

                    // 先计算该buyer的discount之后的价格
                    $cart_id = $modelCheckoutCart->add($result['product_id'], $result['quantity'], $option_data, 0, 4, $result['id'], $deliveryType);

                    if (!isset($this->session->data['cart'][$cart_id])) {
                        $this->session->data['cart'][$cart_id] = (int)$result['quantity'];
                    } else {
                        $this->session->data['cart'][$cart_id] += (int)$result['quantity'];
                    }
                    $json['success'] = $this->language->get('text_success_add_to_cart_' . $deliveryType);
                } else {
                    //议价单已超时，更改为超时状态
                    $modelAccountProductQuoteswkproductquotes->updateQuote(['quote_id' => $quote_id, 'status' => 4]);
                    $json['error'] = $this->language->get('text_error_add_cart_timeout');
                    $json['timeout_html'] = '<span>' . $this->language->get('text_status_4') . '</span>';
                }
            } else {
                $json['error'] = $this->language->get('text_error_add_to_cart');
            }
        }
        return $this->response->json($json);
    }

}

?>
