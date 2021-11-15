<?php

use App\Catalog\Controllers\AuthController;
use App\Catalog\Enums\Stock\DiscrepancyInvoiceType;
use App\Catalog\Enums\Stock\DiscrepancyReason;
use App\Catalog\Enums\Stock\DiscrepancyRecordType;
use App\Catalog\Enums\Stock\StockBlockReasonTypeEnum;
use App\Catalog\Enums\Stock\StockBlockTypeEnum;
use App\Catalog\Enums\Stock\StockSearchTypeEnum;
use App\Catalog\Search\Stock\DiscrepancySearch;
use App\Catalog\Search\Stock\StockSearch;
use App\Enums\Product\ProductLockType;
use App\Helper\CountryHelper;
use App\Logging\Logger;
use App\Models\CWF\OrderCloudLogistics;
use App\Models\Delivery\BuyerProductLock;
use App\Models\Link\OrderAssociated;
use App\Models\Link\OrderAssociatedPre;
use App\Models\Product\Product;
use App\Models\Rma\YzcRmaOrder;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesReorder;
use App\Repositories\FeeOrder\StorageFeeRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Stock\StockManagementRepository;
use Carbon\Carbon;
use Framework\DataProvider\QueryBuilderDataProvider;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Class ControllerAccountStockManagement
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountStockManagement extends AuthController
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->load->language('account/stock/management');
    }

    /**
     * 库存管理主页
     *
     * @return string
     */
    public function index()
    {
        $title = $this->language->get('heading_title');//需要多语言化
        $this->document->setTitle($title);
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => url('common/home')
        ];
        $data['breadcrumbs'][] = [
            'text' => $title,
            'href' => url('account/stock/management'),
        ];
        $data['tab_id'] = 'tab_stock';//tab_discrepancy or tab_stock
        $data['stock_url'] = url(['account/stock/management/stockTab', 'filter_stock_type' => StockSearchTypeEnum::AVAILABLE_QTY]);
        $data['discrepancy_url'] = url('account/stock/management/discrepancyTab');
        $data['storage_fee_description_id'] = app(StorageFeeRepository::class)->getStorageFeeDescriptionId($this->customer->getCountryId());
        return $this->render('account/stock/management_index', $data, [
            'column_left' => 'common/column_left',
            'column_right' => 'common/column_right',
            'content_top' => 'common/content_top',
            'content_bottom' => 'common/content_bottom',
            'footer' => 'common/footer',
            'header' => 'common/header',
        ]);
    }

    /**
     * 库存查询tab页
     */
    public function stockTab()
    {
        $data = [];
        $this->load->model('tool/image');
        $repo = app(StockManagementRepository::class);
        $search = new StockSearch($this->customer->getId());
        $query = $this->request->query->all();
        if (isset($query['filter_item_code']) && !empty($query['filter_item_code'])) {
            //替换掉中文逗号
            $query['filter_item_code'] = str_replace('，', ',', $query['filter_item_code']);
            $filterItemCode = explode(',', $query['filter_item_code']);
            foreach ($filterItemCode as &$itemCode) {
                //去除所有前后空格
                $itemCode = trim($itemCode);
            }
            unset($itemCode);
            $filterItemCode = array_unique($filterItemCode);
            //剔除空值
            $filterItemCode = array_filter($filterItemCode);
            $query['filter_item_code_arr'] = $filterItemCode;
        }
        $dataProvider = $search->search($query);
        $list = $dataProvider->getList();
        // 先计算出超过30天的product
        $productIds = $list->pluck('product_id')->toArray();
        $stockOverDays = configDB('stock_management_over_day', 30);
        $overDays = $repo->checkProductOverSpecialDays($productIds, $stockOverDays);
        $list = $list->each(function ($item) use ($repo, $overDays) {
            $product = Product::find($item->product_id);
            $item->store = $product->customerPartner->store;
            if (!property_exists($item, 'agreementQty')) {
                $item->agreementQty = $repo->getContractQty($item->product_id);
            }
            if (!property_exists($item, 'blockQty')) {
                $item->blockQty = (int)($item->onhandQty - $item->availableQty);
                //有出现负数的情况，修正数据
                $item->blockQty = $item->blockQty >= 0 ? $item->blockQty : 0;
            }
            // 时间超过30天处理 如果判断是0体积产品或者配件 也不需要展示
            $item->is_over_30 = $repo->checkProductShowAlert($item->product_id) && ($overDays[$item->product_id] ?? false);
            return $item;
        });
        $data['list'] = $list;
        $data['list_empty_reason'] = $list->count() === 0 ? $search->checkBuyHistory() : null;
        $data['sort'] = $dataProvider->getSort();
        $data['sort_url'] = request('sort');
        $data['paginator'] = $dataProvider->getPaginator();
        $data['total_info'] = $search->getTotal();
        $data['request'] = $search->getSearchData();
        $data['stock_url'] = url('account/stock/management/stockTab');
        $data['stock_search_enum'] = StockSearchTypeEnum::getViewItems();
        $data['currency'] = session('currency');
        $data['stock_management_over_day'] = configDB('stock_management_over_day');
        return $this->render('account/stock/stock_tab', $data);
    }

    /**
     * 入出库流水页
     *
     * @return string
     * @throws \Framework\Exception\InvalidConfigException
     */
    public function discrepancyTab(ProductRepository $productRepository)
    {
        $query = $this->request->query->all();
        $export = request('export', 0);
        $this->load->language('common/cwf');
        //列表数据
        $dataSearch = new DiscrepancySearch($this->customer->getId());
        $dataProvider = $dataSearch->search($query, $export);
        if ($export) { //下载
            try {
                if (empty($query['filter_created_month'])) {
                    return $this->jsonFailed($this->language->get('text_please_select_a_month'));
                }
                set_time_limit(0);
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle($this->language->get('text_discrepancy_export_file_name'));
                $sheet->fromArray($this->formatExportDiscrepancyList($dataProvider));
                $writer = IOFactory::createWriter($spreadsheet, 'Xls');
                //导出
                return $this->response->streamDownload(
                    function () use ($writer) {
                        $writer->save('php://output');
                    }, $this->language->get('text_discrepancy_export_file_name') . Carbon::now()->format('Ymd') . '.xls', ['Content-Type' => 'application/vnd.ms-excel']
                );
            } catch (Exception $e) {
                Logger::error($e);
                return $this->response->redirectTo(url('error/not_found'));
            }
        }
        $list = $dataProvider->getList();
        $productInfos = $productRepository->getProductInfoByProductId($list->pluck('product_id')->toArray());
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        $list->map(function ($item) use ($productInfos, $isCollectionFromDomicile) {
            $item->image = $productInfos[$item->product_id]['image'] ?? null;
            $item->tags = $productInfos[$item->product_id]['tags'] ?? '';
            $item->type_desc = DiscrepancyRecordType::getDescription($item->type);
            $item->reason_desc = DiscrepancyReason::getDescription($item->reason);
            //构造订单详情URL
            if ($item->reason == DiscrepancyReason::PURCHASE_ORDER) {
                $item->order_url = url(['account/order/purchaseOrderInfo', 'order_id' => $item->order_id]);
            } elseif ($item->reason == DiscrepancyReason::CWF_ORDER) {
                //云送仓订单
                $item->order_url = url(['Account/Sales_Order/CloudWholesaleFulfillment/info', 'id' => $item->id]);
            } elseif (
                in_array($item->reason, [
                    DiscrepancyReason::SALES_ORDER,
                    DiscrepancyReason::SOLD_BUT_NOT_SHIPPED,
                    DiscrepancyReason::BLOCKED_CANCELED_SALES_ORDER,
                    DiscrepancyReason::BLOCKED_ASR,
                    DiscrepancyReason::BLOCKED_PENDING_CHARGES
                ])
            ) {
                if ($isCollectionFromDomicile) {
                    $item->order_url = url(['account/customer_order/customerOrderSalesOrderDetails', 'id' => $item->id]);
                } else {
                    $item->order_url = url(['account/sales_order/sales_order_management/customerOrderSalesOrderDetails', 'id' => $item->id]);
                }
            } elseif (
                in_array($item->reason,
                    [
                        DiscrepancyReason::RESHIPMENT_INBOUND, DiscrepancyReason::RMA_REFUND,
                        DiscrepancyReason::RESHIPMENT_OUTBOUND, DiscrepancyReason::BLOCKED_APPLYING_RMA,
                        DiscrepancyReason::BLOCKED_CANCELED_RMA_ORDER, DiscrepancyReason::RMA_BUT_NOT_SHIPPED
                    ])
            ) {
                $item->order_url = url(['account/rma_order_detail', 'rma_id' => $item->id]);
            } elseif ($item->reason == DiscrepancyReason::BUYER_SALES_ORDER_PRE_LOCK) {
                $salesOrder = CustomerSalesOrder::query()->where('order_id', $item->order_id)->first();
                $item->order_url = $this->getSalesOrderUrl($salesOrder);
            } else {
                $item->order_url = '';
            }
            $item->invoice_type_desc = DiscrepancyInvoiceType::getDescription(DiscrepancyReason::getDiscrepancyInvoiceType($item->reason), $this->language->get('text_na'));
            $item->is_cwf = $item->reason == DiscrepancyReason::PURCHASE_ORDER && $item->delivery_type == 2;
        });
        $data['total'] = $dataProvider->getTotalCount(); // 总计
        $data['list'] = $list;  // 列表
        $data['paginator'] = $dataProvider->getPaginator();
        $data['sort'] = $dataProvider->getSort();
        $data['search'] = $dataSearch->getSearchData();
        $data['is_search'] = $dataSearch->isSearch();
        //样式控制
        $data['record_colors'] = [
            1 => 'org-color',
            2 => 'green-color',
            3 => 'brown-color',
        ];
        $data['discrepancy_record_type'] = DiscrepancyRecordType::getViewItems();
        //总计
        $totalSearch = new DiscrepancySearch($this->customer->getId());
        $data['total_list'] = $totalSearch->total($query);
        return $this->render('account/stock/discrepancy_tab', $data);
    }

    /**
     * 格式化入出库流水导出数据
     *
     * @param QueryBuilderDataProvider $dataProvider
     * @return array
     */
    private function formatExportDiscrepancyList(QueryBuilderDataProvider $dataProvider)
    {
        $excelDataList = [];
        $titleItemCode = $this->language->get('text_item_code');
        $titleSeller = $this->language->get('text_seller');
        $titleDiscrepancyType = $this->language->get('text_discrepancy_type');
        $titleCreationTime = $this->language->get('text_creation_time');
        $titleQuantity = $this->language->get('text_quantity');
        $titleDiscrepancyReason = $this->language->get('text_discrepancy_reason');
        $titleInvoiceType = $this->language->get('text_invoice_type');
        $titleInvoiceId = $this->language->get('text_invoice_id');
        $textNa = $this->language->get('text_na');
        $country = session('country');
        foreach ($dataProvider->getListWithCursor() as $item) {
            $excelDataList[] = [
                $titleItemCode => strtoupper($item->sku),
                $titleSeller => html_entity_decode($item->screenname),
                $titleDiscrepancyType => DiscrepancyRecordType::getDescription($item->type),
                $titleCreationTime => $item->creation_time ?
                    Carbon::parse($item->creation_time)->setTimezone(CountryHelper::getTimezoneByCode($country))->toDateTimeString() : '',
                $titleQuantity => ($item->type == 1 ? '+' : '-') . $item->quantity,
                $titleDiscrepancyReason => DiscrepancyReason::getDescription($item->reason),
                $titleInvoiceType => DiscrepancyInvoiceType::getDescription(DiscrepancyReason::getDiscrepancyInvoiceType($item->reason), $textNa),
                $titleInvoiceId => $item->order_id ? $item->order_id . "\t" : $textNa,
            ];
        }
        if (empty($excelDataList)) {
            $excelDataList[] = [
                $titleItemCode => $this->language->get('text_no_data_found'),
                $titleSeller => '',
                $titleDiscrepancyType => '',
                $titleCreationTime => '',
                $titleQuantity => '',
                $titleDiscrepancyReason => '',
                $titleInvoiceType => '',
                $titleInvoiceId => '',
            ];
        }
        // 获取表头
        $headers = array_keys($excelDataList[0]);
        array_unshift($excelDataList, $headers);
        return $excelDataList;
    }

    /**
     * 库存详情
     *
     * @return string
     */
    public function detail()
    {
        $product_id = request('product_id');
        $data['product'] = $product = Product::find($product_id);
        list($volume, $size) = app(StorageFeeRepository::class)->calculateProductVolume($product);
        $data['size'] = $size;
        $data['volume'] = $volume;
        $data['qty_info'] = explode(',', request('params', ''));
        //tab_available or tab_contract or tab_locking
        $data['tab_id'] = request('tab_id', 'tab_available');
        $data['available_url'] = url('account/stock/management/detailAvailable');
        $data['contract_url'] = url('account/stock/management/detailContract');
        $data['locking_url'] = url('account/stock/management/detailLocking');
        return $this->render('account/stock/detail', $data, [
            'header' => 'common/header',
        ]);
    }

    // region download stock tab
    public function stockDownload()
    {
        set_time_limit(0);// 脚本执行时间无限
        $spreadsheet = new Spreadsheet();
        $productIds = $this->downloadStockMain($spreadsheet);
        $this->downloadStockMain($spreadsheet);
        $this->downloadAvailableQty($spreadsheet, $productIds);
        $this->downloadAgreementQty($spreadsheet, $productIds);
        $this->downloadBlockQty($spreadsheet, $productIds);
        $spreadsheet->setActiveSheetIndex(0);
        $writer = IOFactory::createWriter($spreadsheet, 'Xls');
        $date = date('Ymd', strtotime(currentZoneDate(session(), date('Y-m-d H:i:s'))));
        return $this->response->streamDownload(
            function () use ($writer) {
                $writer->save('php://output');
            }, 'Inventory report' . $date . '.xls', ['Content-Type' => 'application/vnd.ms-excel']
        );
    }

    // 库存总表页面
    private function downloadStockMain(Spreadsheet $spreadsheet)
    {
        $spreadsheet->setActiveSheetIndex(0);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inventory summary');
        $sheet->freezePane('A2');
        $sheet->getStyle('F')->getNumberFormat()->setFormatCode($this->customer->isJapan() ? '0' : '0.00');
        $search = new StockSearch($this->customer->getId());
        $query = $this->request->query->all();
        if (isset($query['filter_item_code']) && !empty($query['filter_item_code'])) {
            //替换掉中文逗号
            $query['filter_item_code'] = str_replace('，', ',', $query['filter_item_code']);
            $filterItemCode = explode(',', $query['filter_item_code']);
            foreach ($filterItemCode as &$itemCode) {
                //去除所有前后空格
                $itemCode = trim($itemCode);
            }
            unset($itemCode);
            $filterItemCode = array_unique($filterItemCode);
            //剔除空值
            $filterItemCode = array_filter($filterItemCode);
            $query['filter_item_code_arr'] = $filterItemCode;
        }
        $dataProvider = $search->search($query, true);
        $currencySymbol = $this->currency->getSymbolLeft(session('currency'))
            ?: $this->currency->getSymbolRight(session('currency'));
        $headers = [
            'Item Code', 'Seller', 'Available QTY', 'Agreement QTY',
            'Blocked QTY', "Storage Fee($currencySymbol)"
        ];
        $list = $dataProvider->getList();
        $productIds = $list->pluck('product_id')->toArray();
        $totalQty = $search->getTotal();
        $list = $list->map(function ($item) {
            $product = Product::query()->find($item->product_id);
            $item->store = html_entity_decode($product->customerPartner->store->screenname);
            // 排除出现负数的情况
            $item->blockQty = $item->blockQty >= 0 ? $item->blockQty : 0;
            return [
                $item->sku, $item->store, (string)$item->availableQty, (string)$item->agreementQty,
                (string)$item->blockQty, $item->feeTotal,
            ];
        })->toArray();
        if (empty($list)) {
            $list = [['No records.']];
        }
        $lastRow = ['Total', '', $totalQty['a_qty'], $totalQty['m_qty'], $totalQty['l_qty'], $totalQty['w_fee']];
        array_unshift($list, $headers);
        array_push($list, $lastRow);
        $sheet->fromArray($list, null, 'A1');
        return $productIds;
    }

    private function downloadAvailableQty(Spreadsheet $spreadsheet, array $productIds)
    {
        $repo = app(StockManagementRepository::class);
        $spreadsheet->createSheet();
        $spreadsheet->setActiveSheetIndex(count($spreadsheet->getAllSheets()) - 1);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Available Inventory report');
        $sheet->freezePane('A2');
        $sheet->getStyle('H')->getNumberFormat()->setFormatCode($this->customer->isJapan() ? '0' : '0.00');
        $list = [];
        if (!empty($productIds)) {
            $currencySymbol = $this->currency->getSymbolLeft(session('currency'))
                ?: $this->currency->getSymbolRight(session('currency'));
            $headers = [
                'Item Code', 'Seller', 'Volume(m³)', 'Purchase Order ID',
                'Receive Time', 'Available QTY', 'Days in Inventory', "Storage Fee to be Paid($currencySymbol)"
            ];
            $query = $repo->buildProductCostListQuery(null, null, $productIds);
            $totalAvailableQty = 0;
            $totalFee = 0;
            $storageFeeRepo = app(StorageFeeRepository::class);
            foreach ($query->cursor() as $item) {
                $product_id = $item->product_id;
                $product = Product::query()->find($product_id);
                list($volume,) = $storageFeeRepo->calculateProductVolume($product);
                $storageFeeInfo = $repo->getStorageFee($item->order_id, $item->product_id, $item->availableQty);
                $diffDays = $repo->getInventoryDays($item->create_time, customer()->getCountryId(), $item->type_id, $item->agreement_id);
                $temp = [
                    $item->sku, $product->customerPartner->store->screenname,
                    (string)$volume, $item->order_id,
                    currentZoneDate(session(), $item->create_time), $item->availableQty,
                    (string)$diffDays, (string)($storageFeeInfo ? $storageFeeInfo->feeTotal : 0),
                ];
                $totalAvailableQty += $item->availableQty;
                $totalFee += $storageFeeInfo ? $storageFeeInfo->feeTotal : 0;
                $list[] = $temp;
            }
            $lastRow = ['Total', '', '', '', '', $totalAvailableQty, '', $totalFee];
        }
        // 如果为空 则删除该sheet
        if (empty($list)) {
            $spreadsheet->removeSheetByIndex(
                $spreadsheet->getIndex($spreadsheet->getSheetByName('Available Inventory report'))
            );
            return;
        }
        array_unshift($list, $headers);
        array_push($list, $lastRow);
        $sheet->fromArray($list, null, 'A1');
    }

    private function downloadAgreementQty(Spreadsheet $spreadsheet, array $productIds)
    {
        $spreadsheet->createSheet();
        $spreadsheet->setActiveSheetIndex(count($spreadsheet->getAllSheets()) - 1);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Agreement Inventory report');
        $sheet->freezePane('A2');
        if (!empty($productIds)) {
            $list = $this->formatExportContractList($productIds);
        }
        // 如果为空 则删除该sheet
        if (empty($list)) {
            $spreadsheet->removeSheetByIndex(
                $spreadsheet->getIndex($spreadsheet->getSheetByName('Agreement Inventory report'))
            );
            return;
        }
        $sheet->fromArray($list, null, 'A1');
    }

    private function downloadBlockQty(Spreadsheet $spreadsheet, array $productIds)
    {
        $spreadsheet->createSheet();
        $spreadsheet->setActiveSheetIndex(count($spreadsheet->getAllSheets()) - 1);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Blocked Inventory report');
        $sheet->freezePane('A2');
        $sheet->getStyle('H')->getNumberFormat()->setFormatCode($this->customer->isJapan() ? '0' : '0.00');
        $list = [];
        if (!empty($productIds)) {
            $currencySymbol = $this->currency->getSymbolLeft(session('currency'))
                ?: $this->currency->getSymbolRight(session('currency'));
            $headers = [
                'Item Code', 'Seller', 'Volume(m³)', 'Block Reason', 'Type',
                'Order ID', 'Blocked QTY', "Storage Fee to be Paid($currencySymbol)",
            ];
            $totalQty = 0;
            $totalFee = 0;
            $query = app(StockManagementRepository::class)->buildBlockListQuery(null, null, $productIds);
            $tempList = $query->get()->map(function ($item) {
                return $this->resolveLockDetailInfo($item);
            });
            //重新排序
            $newList = $tempList->toArray();
            $productIdArr = $tempList->pluck('product_id')->toArray();
            $reasonArr = $tempList->pluck('block_type')->toArray();
            $needPayArr = $tempList->pluck('need_pay')->toArray();
            $qtyArr = $tempList->pluck('qty')->toArray();
            array_multisort(
                $productIdArr, SORT_DESC, $reasonArr, SORT_ASC,
                $needPayArr, SORT_DESC, $qtyArr, SORT_DESC,
                $newList
            );
            $storageFeeRepo = app(StorageFeeRepository::class);
            foreach ($newList as $item) {
                $product_id = $item->product_id;
                $product = Product::query()->find($product_id);
                list($volume,) = $storageFeeRepo->calculateProductVolume($product);
                $temp = [
                    $product->sku, $product->customerPartner->store->screenname, (string)$volume,
                    StockBlockTypeEnum::getDescription($item->block_type),
                    StockBlockReasonTypeEnum::getDescription($item->reason_type),
                    $item->relate_id . "\t", $item->qty, (string)($item->need_pay < 0 ? 0 : $item->need_pay)
                ];
                $list[] = $temp;
                $totalQty += $item->qty;
                $totalFee += $item->need_pay < 0 ? 0 : $item->need_pay;
            }
            $lastRow = ['Total', '', '', '', '', '', $totalQty, $totalFee];
        }
        // 如果为空 则删除该sheet
        if (empty($list)) {
            $spreadsheet->removeSheetByIndex(
                $spreadsheet->getIndex($spreadsheet->getSheetByName('Blocked Inventory report'))
            );
            return;
        }
        array_unshift($list, $headers);
        array_push($list, $lastRow);
        $sheet->fromArray($list, null, 'A1');
    }

    // endregion

    /**
     * 可用库存
     *
     * @return string
     */
    public function detailAvailable()
    {
        $data = [];
        $repo = app(StockManagementRepository::class);
        $productId = request('product_id');
        $query = app(StockManagementRepository::class)
            ->buildProductCostListQuery(null, null, [$productId]);
        $list = $query->get();
        $isOverDays = false;
        $stockOverDays = configDB('stock_management_over_day', 30);
        $list = $list->map(function ($item) use ($repo, $stockOverDays, &$isOverDays) {
            // 计算天数 判断是否超过30天
            $diffDays = $repo->getInventoryDays($item->create_time, customer()->getCountryId(), $item->type_id, $item->agreement_id);
            $item->days = $diffDays;
            $item->is_over_days = bccomp($item->days, $stockOverDays) > 0;
            $isOverDays = $isOverDays || $item->is_over_days;
            $storageFeeInfo = $repo->getStorageFee($item->order_id, $item->product_id, $item->availableQty);
            $item->need_pay = 0;
            $item->paid = 0;
            $item->fee_detail_range = [];
            if ($storageFeeInfo) {
                $item->need_pay = $storageFeeInfo->feeTotal;
                $item->paid = $storageFeeInfo->feePaid;
                $item->storage_fee_ids = $storageFeeInfo->ids;
                $item->fee_detail_range = app(StorageFeeRepository::class)->getFeeDetailRange($storageFeeInfo, $item->availableQty);
            }
            return $item;
        });
        // 先按在库天数倒序，再按可用数量倒序
        $data['list'] = $list;
        $data['is_over_days'] = $isOverDays;
        $data['currency'] = session('currency');
        $data['currency_symbol'] = $this->currency->getSymbolLeft($data['currency']) . $this->currency->getSymbolRight($data['currency']);
        $data['stock_management_over_day'] = configDB('stock_management_over_day');
        return $this->render('account/stock/detail_available', $data);
    }

    /**
     * 合约中库存
     *
     * @return string
     */
    public function detailContract()
    {
        $productId = request('product_id', 0);
        $data = [];
        $data['list'] = app(StockManagementRepository::class)->getContractsStockByProductId($this->customer->getId(), $productId);
        $data['currency'] = session('currency');
        $data['currency_symbol'] = $this->currency->getSymbolLeft($data['currency']) . $this->currency->getSymbolRight($data['currency']);
        return $this->render('account/stock/detail_contract', $data);
    }

    /**
     * 获取格式化后的合约中库存导出数据
     * 返回数据直接作为导出数据使用
     *
     * @param array $productIds
     * @return array
     */
    private function formatExportContractList(array $productIds = [])
    {
        $list = app(StockManagementRepository::class)->getContractsStockByProductId($this->customer->getId(), $productIds);
        if ($list->isEmpty()) {
            return [];
        }
        $excelDataList = [];
        $currencySymbol = $this->currency->getSymbolLeft(session('currency'));
        $titleItemCode = $this->language->get('text_item_code');
        $titleSeller = $this->language->get('text_seller');
        $titleAgreementType = $this->language->get('text_agreement_type');
        $titleAgreementId = $this->language->get('text_agreement_id');
        $titlePendingPurchaseQuantity = $this->language->get('text_pending_purchase_quantity');
        $titleAgreementStatus = $this->language->get('text_agreement_status');
        $titleDaysLeft = $this->language->get('text_days_left');
        $titleStorageFee = "Storage Fee to be Paid($currencySymbol)";
        $totalQty = 0;
        foreach ($list as $item) {
            $totalQty += $item['pending_purchase_quantity'];
            $excelDataList[] = [
                $titleItemCode => $item['item_code'],
                $titleSeller => html_entity_decode($item['screenname']),
                $titleAgreementType => $item['type_desc'],
                $titleAgreementId => $item['agreement_no'] . "\t",
                $titlePendingPurchaseQuantity => $item['pending_purchase_quantity'],
                $titleAgreementStatus => $item['agreement_status_desc'],
                $titleDaysLeft => (string)$item['diff_day'],
                $titleStorageFee => $item['type_id'] == ProductLockType::MARGIN ? $item['need_pay'] : 'N/A'
            ];
        }
        //获取表头
        $headers = array_keys($excelDataList[0]);
        //插入表头
        array_unshift($excelDataList, $headers);
        //增加total行
        array_push($excelDataList, [
            $this->language->get('text_total'),
            '',
            '',
            '',
            $totalQty,
            '',
            '',
        ]);
        return $excelDataList;
    }

    /**
     * 锁定中库存
     *
     * @return string
     */
    public function detailLocking()
    {
        $productId = request('product_id', 0);
        $repo = app(StockManagementRepository::class);
        $data = [];
        $list = $repo->buildBlockListQuery(null, null, (array)$productId)->get();
        $list = $list->map(function ($item) {
            return $this->resolveLockDetailInfo($item);
        });
        //重新排序
        $newList = $list->toArray();
        $reasonArr = $list->pluck('block_type')->toArray();
        $needPayArr = $list->pluck('need_pay')->toArray();
        $qtyArr = $list->pluck('qty')->toArray();
        array_multisort($reasonArr, SORT_ASC, $needPayArr, SORT_DESC, $qtyArr, SORT_DESC, $newList);
        $data['list'] = $newList;
        $data['block_type_enum'] = StockBlockTypeEnum::getViewItems();
        $data['block_reason_enum'] = StockBlockReasonTypeEnum::getViewItems();
        $data['currency'] = session('currency');
        $data['currency_symbol'] = $this->currency->getSymbolLeft($data['currency']) . $this->currency->getSymbolRight($data['currency']);
        return $this->render('account/stock/detail_locking', $data);
    }

    private function resolveLockDetailInfo($item)
    {
        $repo = app(StockManagementRepository::class);
        $repoStorage = app(StorageFeeRepository::class);
        $reasonType = $item->reason_type;
        switch ($reasonType) {
            case StockBlockReasonTypeEnum::CANCEL_NOT_APPLY_RMA:
            {
                $associate = OrderAssociated::with(['customerSalesOrder'])->find($item->associate_id);
                $storageFeeInfo = $repo->getSalesOrderStorageFee($item->associate_id);
                $item->need_pay = 0;
                $item->fee_detail_range = [];
                if ($storageFeeInfo) {
                    $item->need_pay = $storageFeeInfo->feeTotal;
                    $item->storage_fee_ids = $storageFeeInfo->ids;
                    $item->paid = $storageFeeInfo->feePaid;
                    $item->fee_detail_range = $repoStorage->getFeeDetailRange($storageFeeInfo, $associate->qty);
                }
                $item->relate_id = $associate->customerSalesOrder->order_id;
                $item->relate_url = $this->getSalesOrderUrl($associate->customerSalesOrder);
                break;
            }
            case StockBlockReasonTypeEnum::APPLY_RMA_NOT_AGREE_SALES_ORDER:
            {
                // 预估仓租费
                $associate = OrderAssociated::query()->find($item->associate_id);
                $storageFeeInfo = $repo->getSalesOrderStorageFee($item->associate_id);
                $item->need_pay = 0;
                $item->fee_detail_range = [];
                if ($storageFeeInfo) {
                    $item->need_pay = $storageFeeInfo->feeTotal;
                    $item->storage_fee_ids = $storageFeeInfo->ids;
                    $item->paid = $storageFeeInfo->feePaid;
                    $item->fee_detail_range = $repoStorage->getFeeDetailRange($storageFeeInfo, $associate->qty);
                }
                $rma = $repo->getApplyRmaNotAgree((int)$item->associate_id);
                $item->relate_id = $rma->rma_order_id;
                $item->relate_url = url(['account/rma_order_detail', 'rma_id' => $rma->id]);
                break;
            }
            case StockBlockReasonTypeEnum::APPLY_RMA_NOT_AGREE_PURCHASE_ORDER:
            {
                $rma = YzcRmaOrder::query()->find($item->relate_id);
                $rmaProduct = $rma->yzcRmaOrderProduct;
                // 预估仓租费
                $storageFeeInfo = $repo->getStorageFee($rma->order_id, $rmaProduct->product_id, $rmaProduct->quantity);
                $item->need_pay = 0;
                $item->fee_detail_range = [];
                if ($storageFeeInfo) {
                    $item->need_pay = $storageFeeInfo->feeTotal;
                    $item->storage_fee_ids = $storageFeeInfo->ids;
                    $item->paid = $storageFeeInfo->feePaid;
                    $item->fee_detail_range = $repoStorage->getFeeDetailRange($storageFeeInfo, $rma->yzcRmaOrderProduct->quantity);
                }
                $item->relate_id = $rma->rma_order_id;
                $item->relate_url = url(['account/rma_order_detail', 'rma_id' => $rma->id]);
                break;
            }
            case StockBlockReasonTypeEnum::WAIT_PAY_FEE_ORDER: // 上门取货的费用待支付状态 其实已经绑定了 所以可以查到绑定数据
            {
                $associate = OrderAssociated::with(['customerSalesOrder'])->find($item->associate_id);
                // 预估仓租费
                $storageFeeInfo = $repo->getSalesOrderStorageFee($item->associate_id);
                $item->need_pay = 0;
                $item->fee_detail_range = [];
                if ($storageFeeInfo) {
                    $item->need_pay = $storageFeeInfo->feeTotal;
                    $item->storage_fee_ids = $storageFeeInfo->ids;
                    $item->paid = $storageFeeInfo->feePaid;
                    $item->fee_detail_range = $repoStorage->getFeeDetailRange($storageFeeInfo, $associate->qty);
                }
                $item->relate_url = $this->getSalesOrderUrl($associate->customerSalesOrder);
                break;
            }
            case StockBlockReasonTypeEnum::ASR_WAIT_PAY:
            {
                $associate = OrderAssociated::with(['customerSalesOrder'])->find($item->associate_id);
                $item->relate_url = $this->getSalesOrderUrl($associate->customerSalesOrder);
                // 预估仓租费
                $storageFeeInfo = $repo->getStorageFee($associate->order_id, $associate->product_id, $associate->qty);
                $item->need_pay = 0;
                $item->fee_detail_range = [];
                if ($storageFeeInfo) {
                    $item->need_pay = $storageFeeInfo->feeTotal;
                    $item->storage_fee_ids = $storageFeeInfo->ids;
                    $item->paid = $storageFeeInfo->feePaid;
                    $item->fee_detail_range = $repoStorage->getFeeDetailRange($storageFeeInfo, $associate->qty);
                }
                break;
            }
            case StockBlockReasonTypeEnum::SELL_NOT_SHIPPED_SALES_ORDER:
            {
                $associate = OrderAssociated::with(['customerSalesOrder'])->find($item->associate_id);
                $item->relate_url = $this->getSalesOrderUrl($associate->customerSalesOrder);
                $item->need_pay = -1;//-1代表N/A
                break;
            }
            case StockBlockReasonTypeEnum::SELL_NOT_SHIPPED_RESHIPPED_ORDER:
            {
                $salesReOrder = CustomerSalesReorder::query()
                    ->where(['reorder_id' => $item->relate_id, 'buyer_id' => $this->customer->getId(),])
                    ->first();
                $item->relate_url = url(['account/rma_order_detail', 'rma_id' => $salesReOrder->rma_id]);
                $item->need_pay = -1;//-1代表N/A
                break;
            }
            case StockBlockReasonTypeEnum::INVENTORY_LOSS:
            case StockBlockReasonTypeEnum::INVENTORY_REDUCTION:
            {
                $item->relate_url = '';
                $item->need_pay = -1;//-1代表N/A
                $item->relate_id = '-';
                break;
            }
            case StockBlockReasonTypeEnum::INVENTORY_PRE_LOCK_SALES_ORDER:
            {
                $buyerProductLock = BuyerProductLock::query()->find($item->relate_id);
                $orderAssociatedPre = OrderAssociatedPre::query()->where('id', $buyerProductLock->foreign_key)->first();

                // 预估仓租费
                $storageFeeInfo = $repo->getStorageFee($orderAssociatedPre->order_id, $orderAssociatedPre->product_id, $orderAssociatedPre->qty);
                $item->need_pay = 0;
                $item->fee_detail_range = [];
                if ($storageFeeInfo) {
                    $item->need_pay = $storageFeeInfo->feeTotal;
                    $item->storage_fee_ids = $storageFeeInfo->ids;
                    $item->paid = $storageFeeInfo->feePaid;
                    $item->fee_detail_range = $repoStorage->getFeeDetailRange($storageFeeInfo, $orderAssociatedPre->qty);
                }

                $item->relate_id = $orderAssociatedPre->salesOrder->order_id;
                $item->relate_url = $this->getSalesOrderUrl($orderAssociatedPre->salesOrder);
            }
        }
        return $item;
    }

    /**
     * 生成对应销售单 url
     * @param CustomerSalesOrder $salesOrder
     * @return string
     */
    private function getSalesOrderUrl(CustomerSalesOrder $salesOrder = null): string
    {
        if (empty($salesOrder)) return '';
        // cwf order
        $cwfOrder = OrderCloudLogistics::query()->where('sales_order_id', $salesOrder->id)->first();
        if ($cwfOrder) {
            return url(['Account/Sales_Order/CloudWholesaleFulfillment/info', 'id' => $cwfOrder->id]);
        }
        // sales order
        if ($this->customer->isCollectionFromDomicile()) {
            return url(['account/customer_order/customerOrderSalesOrderDetails', 'id' => $salesOrder->id]);
        } else {
            return url(['account/sales_order/sales_order_management/customerOrderSalesOrderDetails', 'id' => $salesOrder->id]);
        }
    }
}
