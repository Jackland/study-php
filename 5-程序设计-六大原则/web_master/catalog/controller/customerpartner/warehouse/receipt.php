<?php

use App\Catalog\Forms\Warehouse\ShippingOrderBookForm;
use App\Enums\Warehouse\ReceiptsOrderProgramCode;
use App\Helper\LangHelper;
use App\Catalog\Controllers\AuthSellerController;
use App\Enums\Warehouse\ReceiptsOrderHistoryType;
use App\Repositories\SpecialFee\SpecialFeeRepository;
use App\Repositories\Warehouse\ReceiptRepository;
use App\Services\Warehouse\ReceiptService;
use App\Repositories\Product\ProductRepository;
use App\Enums\Warehouse\ReceiptOrderStatus;
use App\Enums\Warehouse\ReceiptShipping;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @property ModelToolExcel $model_tool_excel
 */
class ControllerCustomerpartnerWarehouseReceipt extends AuthSellerController
{
    const LIMIT_HSCODE_LENGTH = 10; // HsCode 数字部分长度限制
    const LIMIT_HSCODE_TOTAL_LENGTH = 30; // HsCode 总长度限制
    const LIMIT_301_HSCODE_LENGTH = 20; // 301-HsCode 数字部分长度限制
    const LIMIT_301_HSCODE_TOTAL_LENGTH = 30; // 301-HsCode 总长度限制
    const LIMIT_ETD_SECONDS = 3600 * 24 * 4; //ETD+3天(后3天）

    private $customerId;
    private $receiptRepos;
    private $receiptService;

    public function __construct(Registry $registry, ReceiptRepository $receiptRepos, ReceiptService $receiptService)
    {
        parent::__construct($registry);
        $this->receiptRepos = $receiptRepos;
        $this->receiptService = $receiptService;
        $this->customerId = $this->customer->getId();

        if ($this->customer->getCountryId() != AMERICAN_COUNTRY_ID) {
            // 非O类美国Seller 不能操作
            return $this->redirect(['account/account'])->send();
        }
    }

    /**
     * 入库单托书检查
     *
     * @param ShippingOrderBookForm $form
     * @return JsonResponse
     */
    public function checkShippingOrderBook(ShippingOrderBookForm $form)
    {
        $result = $form->checkDataFormat();
        if ($result === true) {
            return $this->jsonSuccess();
        }

        return $this->jsonFailed($result['msg'] ?? '');
    }

    public function index()
    {
        $this->load->language('account/account');
        $this->load->language('customerpartner/warehouse/receipt');
        $data = $this->framework();
        //入库单状态
        $data['statusList'] = ReceiptOrderStatus::getViewItems();
        //去掉已废弃
        unset($data['statusList'][ReceiptOrderStatus::ABANDONED]);
        //运输方式
        $data['ShippingList'] = ReceiptShipping::getViewItems();
        //筛选条件
        $filter = [
            'filter_receiving_order_number' => $this->request->get('filter_receiving_order_number', ''),
            'filter_container_code' => $this->request->get('filter_container_code', ''),
            'filter_status' => $this->request->get('filter_status', ''),
            'filter_received_from' => $this->request->get('filter_received_from', ''),
            'filter_received_to' => $this->request->get('filter_received_to', ''),
            'filter_shipping_way' => $this->request->get('filter_shipping_way', ''),
            'page' => $this->request->get('page', 1),
            'limit' => $this->request->get('page_limit', 10)
        ];
        $data = array_merge($data, $filter);
        //查询字段
        $filter['column'] = [
            'receive_order_id',
            'receive_number',
            'shipping_way',
            'apply_date',
            'container_code',
            'status',
            'receive_date',
            'etd_date',
            'etd_date_start',
            'etd_date_end',
            'program_code',
        ];
        //查询数据
        $listAndCount = app(ReceiptRepository::class)->getReceiptOrderListAndCount($filter, $this->customerId);
        //处理数据
        $num = (($filter['page'] - 1) * $filter['limit']) + 1;
        if ($listAndCount['receiptOrderList'] && count($listAndCount['receiptOrderList']) > 0) {
            foreach ($listAndCount['receiptOrderList'] as $key => &$val) {
                $val['num'] = $num++;
                //已废弃当做已取消
                $val['showStatus'] = $val['status'];
                if ($val['showStatus'] == ReceiptOrderStatus::ABANDONED) {
                    $val['showStatus'] = ReceiptOrderStatus::CANCEL;
                }

                $val['canCancel'] = false;
                $val['canEditShow'] = false;
                $val['disableEdit'] = false;
                $val['doCancelShowConfirm'] = false;
                $val['confirmShipping'] = false;
                if (trim($val['program_code']) == '1.0') {//program_code:1.0  编辑等不考虑
                    continue;
                }

                //取消按钮
                if (!in_array($val['status'], ReceiptOrderStatus::disableCancelStatus())) {
                    $val['canCancel'] = true;
                }

                //正常编辑按钮
                $val['canEditShow'] = true;
                if (in_array($val['status'], ReceiptOrderStatus::disableEditStatus())) {
                    $val['canEditShow'] = false;
                } else {
                    //ETD+3显示禁用编辑按钮，并有提示语
                    if (isset($val['etd_date']) && trim($val['etd_date']) && (strtotime($val['etd_date']) + self::LIMIT_ETD_SECONDS) < time()) {
                        $val['disableEdit'] = true;
                        $val['canEditShow'] = false;
                    }
                }

                //操作取消按钮时，是否取消待确认提示
                if (in_array($val['status'], ReceiptOrderStatus::doCancelShowConfirmStatus())) {
                    $val['doCancelShowConfirm'] = true;
                }

                //发船确认
                if ($val['status'] == ReceiptOrderStatus::DIVIDED && $val['shipping_way'] == ReceiptShipping::MY_SELF && !empty($val['etd_date_start']) && !empty($val['etd_date_end'])) {
                    $val['confirmShipping'] = true;
                }
            }
        }
        $data = array_merge($data, $listAndCount);
        //分页
        $data = array_merge($data, $this->setPanination($data['receiptOrderCount'], $data['page'], $data['limit']));
        //是否确认入库商品包装规范
        $data['isReceiptsRemind'] = app(ReceiptRepository::class)->existConfirmReceiptsProductPacking($this->customerId);
        $data['isChinese'] = LangHelper::isChinese();
        return $this->render('customerpartner/warehouse/receipt_list', $data);
    }

    //　入库单添加页
    public function add()
    {
        $this->load->language('customerpartner/warehouse/receipt');
        $data = $this->framework('add');
        $data['departures'] = $this->receiptRepos->getDepartureList();
        $data['containers'] = $this->receiptRepos->getContainerSizeList();
        $data['shipping_date'] = $this->receiptRepos->getShippingDate();
        $data['self_shipping_date'] = $this->receiptRepos->getShippingDate(true);
        $data['delivery_date'] = $this->receiptRepos->getDeliveryDate();
        $data['expected_warehouse_date'] = $this->receiptRepos->getExpectedWarehouseDate();
        $data['shipping_methods'] = ReceiptShipping::getViewItems();
        $data['is_usa'] = $this->customer->getCountryId() === AMERICAN_COUNTRY_ID;
        return $this->render('customerpartner/warehouse/receipt_apply', $data);
    }

    //　入库单详情页
    public function view()
    {
        $this->load->language('customerpartner/warehouse/receipt');
        $id = $this->request->get('receive_order_id');
        if (empty($id)) {
            return $this->redirect('error/not_found');
        }
        $pageType = $this->request->get('page_type', 'view');
        $type = $pageType == 'view' ? 'view' : 'edit';
        $data = $this->framework($type);

        $data['receipt'] = $this->receiptRepos->getReceiptById($this->customerId, $id);
        if (! $data['receipt']) {
            $this->redirect(['customerpartner/warehouse/receipt'])->send();
        }

        $data['page_type'] = $pageType;
        $data['departures'] = $this->receiptRepos->getDepartureList();
        $data['containers'] = $this->receiptRepos->getContainerSizeList();
        $data['shipping_date'] = $this->receiptRepos->getShippingDate();
        $data['self_shipping_date'] = $this->receiptRepos->getShippingDate(true);
        $data['delivery_date'] = $this->receiptRepos->getDeliveryDate();
        $data['expected_warehouse_date'] = $this->receiptRepos->getExpectedWarehouseDate();
        $data['receipt_order_history'] = $this->receiptRepos->getReceiptsOrderHistory($id);
        $data['receipt']['total_qty'] =  $data['receipt']->receiptDetails->sum('expected_qty');
        $data['tariff_files'] = app(SpecialFeeRepository::class)->getSpecFeeFilesByReceiveOrderId($id);
        $data['shipping_methods'] = ReceiptShipping::getViewItems();
        $data['is_usa'] = $this->customer->getCountryId() === AMERICAN_COUNTRY_ID;
        $data['receipt']['status_show'] = is_null($data['receipt']['status']) ? null : ReceiptOrderStatus::getDescription($data['receipt']['status']);
        $data['receipt']['departure_show'] = is_null($data['receipt']['shipping_way']) ? null : ReceiptShipping::getDescription($data['receipt']['shipping_way']);
        $canEdit = true;
        // 不显示按钮(ETD+3入库单、不可编辑状态 )
        if ($data['receipt']->etd_date && (strtotime($data['receipt']->etd_date) + 3600 * 24 * 4) < time()
            || in_array($data['receipt']->status, ReceiptOrderStatus::disableEditStatus())) {
            $canEdit = false;
        }
        $data['canEdit'] = $canEdit;
        return $this->render('customerpartner/warehouse/receipt_view', $data);
    }

    //　入库单添加接口
    public function doAdd()
    {
        $this->load->language('customerpartner/warehouse/receipt');
        $customerId = $this->customer->getId();
        $data = $this->request->post();
        $data['status'] = $this->request->post('status', 1);
        $validationStatus = $this->importValidate();
        if ($this->importValidate() !== true) {
            return $this->jsonFailed($validationStatus);
        }
        if ($data['status'] != ReceiptOrderStatus::TO_SUBMIT && ($checkProduct = $this->checkProducts()) !== true) {
            return $this->jsonFailed($checkProduct);
        }

        // 委托海运需要入库单托书校验
        if ($data['shipping_way'] == ReceiptShipping::ENTRUSTED_SHIPPING) {
            $bookForm = app(ShippingOrderBookForm::class);
            $checkRes = $bookForm->checkDataFormat();
            if ($checkRes !== true) {
                return $this->jsonFailed(__('托书数据格式不正确，请检查后提交', [], 'controller/receipt'));
            }
            $data['bookData']['shipping_list'] = $bookForm->shipping_list;
            $data['bookData']['special_product_type'] = $bookForm->special_product_type;
        }

        $data['currency'] = $this->currency;
        if (! empty($data['products'])) {
            foreach ($data['products'] as &$item) {
                $item['expected_qty'] = !empty($item['expected_qty']) ? $item['expected_qty'] : null;
                $item['hscode'] = !empty($item['hscode']) ? $item['hscode'] : '';
                $item['301_hscode'] = !empty($item['301_hscode']) ? $item['301_hscode'] : '';
            }
        }

        // seller申请入库单的时候没有任何一个入库单同步到海运系统，则入库单提交成功之后状态为【运营顾问确认中】，此时入库单不同步到海运系统
        if ($data['status'] == ReceiptOrderStatus::APPLIED && !app(ReceiptRepository::class)->existSynchronizedReceivesBySellerId($customerId)) {
            $data['status'] = ReceiptOrderStatus::ACCOUNT_MANAGER_REVIEWING;
        }

        if ($receiveNumber = $this->receiptService->addReceipt($customerId, $data)) {
            $msg = __('receipt_save_success', ['code' => $receiveNumber], 'controller/receipt');
            if ($data['status'] == ReceiptOrderStatus::APPLIED) {
                if ($data['shipping_way'] == ReceiptShipping::MY_SELF) {
                    $msg = __('receipt_self_apply_success', ['code' => $receiveNumber], 'controller/receipt');
                } elseif ($data['shipping_way'] == ReceiptShipping::ENTRUSTED_SHIPPING) {
                    $msg = __('receipt_no_self_apply_success', ['code' => $receiveNumber], 'controller/receipt');
                } else {
                    $msg = __('receipt_b2b_local_apply_success', ['code' => $receiveNumber], 'controller/receipt');
                }
            } elseif ($data['status'] == ReceiptOrderStatus::ACCOUNT_MANAGER_REVIEWING) {
                $msg = __('receipt_account_manager_reviewing_success', ['code' => $receiveNumber], 'controller/receipt');
            }
            return $this->jsonSuccess([], $msg);
        }
        return $this->jsonFailed(__('receipt_save_failed', [], 'controller/receipt'));
    }

    // 入库单编辑接口
    public function doEdit()
    {
        $this->load->language('customerpartner/warehouse/receipt');
        $customerId = $this->customer->getId();
        $data = $this->request->post();
        $receipt = $this->receiptRepos->getReceiptById($this->customerId, $data['receive_order_id']);
        if (!$receipt) {
            return $this->jsonFailed(__('receipt_save_failed', [], 'controller/receipt'));
        }
        //如果页面状态与数据库中的状态不一致，不能进行任何操作
        if ($receipt->status != $data['originStatus']) {
            return $this->jsonFailed(__('receipt_disable_edit', [], 'controller/receipt'));
        }
        unset($data['originStatus']);

        // ETD+3入库单不可以编辑
        if ($receipt->etd_date && (strtotime($receipt->etd_date) + 3600 * 24 * 4) < time()) {
            return $this->jsonFailed(__('receipt_disable_etd3', [], 'controller/receipt'));
        }

        //不可以编辑
        if (in_array($receipt->status, ReceiptOrderStatus::disableEditStatus())) {
            return $this->jsonFailed(__('receipt_disable_edit', [], 'controller/receipt'));
        }

        // V3版本入库单，托书校验 提交之后托书不可编辑
        if ($data['shipping_way'] == ReceiptShipping::ENTRUSTED_SHIPPING &&
            ($receipt->program_code == ReceiptsOrderProgramCode::PROGRAM_V_3 ||
                ($receipt->program_code != ReceiptsOrderProgramCode::PROGRAM_V_3 && $receipt->status == ReceiptOrderStatus::TO_SUBMIT))) {
            $bookForm = app(ShippingOrderBookForm::class);
            $checkRes = $bookForm->checkDataFormat();
            if ($checkRes !== true) {
                return $this->jsonFailed(__('托书数据格式不正确，请检查后提交', [], 'controller/receipt'));
            }
            $data['bookData']['shipping_list'] = $bookForm->shipping_list;
            $data['bookData']['special_product_type'] = $bookForm->special_product_type;
        }

        //非待收货校验
        if ($receipt->status != ReceiptOrderStatus::TO_BE_RECEIVED) {
            $validationStatus = $this->importValidate(!isset($data['status']) ? 'edit' : '');
            if ($validationStatus !== true) {
                return $this->jsonFailed($validationStatus);
            }
        }

        $data['status'] = $data['status'] ?? $receipt->status;
        if ($receipt->status == ReceiptOrderStatus::ACCOUNT_MANAGER_REVIEWING) {
            $data['status'] = $receipt->status;
        }

        if ($data['status'] != ReceiptOrderStatus::TO_SUBMIT && ($checkProduct = $this->checkProducts()) !== true) {
            return $this->jsonFailed($checkProduct);
        }

        // seller申请入库单的时候没有任何一个入库单同步到海运系统，则入库单提交成功之后状态为【运营顾问确认中】，此时入库单不同步到海运系统
        if ($data['status'] == ReceiptOrderStatus::APPLIED && $receipt->status == ReceiptOrderStatus::TO_SUBMIT && !app(ReceiptRepository::class)->existSynchronizedReceivesBySellerId($customerId)) {
            $data['status'] = ReceiptOrderStatus::ACCOUNT_MANAGER_REVIEWING;
        }

        $res = $this->receiptService->updateReceipt($customerId, $data);
        if ($res !== false) {
            $msg = __('receipt_edit_success', [], 'controller/receipt');
            if ($res === 'disable_edit') {
                return $this->jsonFailed(__('receipt_disable_edit', [], 'controller/receipt'));
            }
            if ($res === 'disable_etd3') {
                return $this->jsonFailed(__('receipt_disable_etd3', [], 'controller/receipt'));
            }
            if ($res === 'edit_pending') {
                $msg = __('receipt_reDivision_success', [], 'controller/receipt');
            }
            if ($data['status'] == ReceiptOrderStatus::APPLIED && $receipt->status == ReceiptOrderStatus::TO_SUBMIT) {
                if ($data['shipping_way'] == ReceiptShipping::MY_SELF) {
                    $msg = __('receipt_self_apply_success', ['code' => $receipt->receive_number], 'controller/receipt');
                } elseif ($data['shipping_way'] == ReceiptShipping::ENTRUSTED_SHIPPING) {
                    $msg = __('receipt_no_self_apply_success', ['code' => $receipt->receive_number], 'controller/receipt');
                } else {
                    $msg = __('receipt_b2b_local_apply_success', ['code' => $receipt->receive_number], 'controller/receipt');
                }
            }
            if ($receipt->status == ReceiptOrderStatus::TO_SUBMIT && $data['status'] == ReceiptOrderStatus::ACCOUNT_MANAGER_REVIEWING) {
                $msg = __('receipt_account_manager_reviewing_success', ['code' => $receipt->receive_number], 'controller/receipt');
            }
            return $this->jsonSuccess([], $msg);
        }
        return $this->jsonFailed(__('receipt_save_failed', [], 'controller/receipt'));
    }

    // 更新入库单的发船信息
    public function doAddShipLaunch()
    {
        $this->load->language('customerpartner/warehouse/receipt');
        $customerId = $this->customer->getId();
        $data = $this->request->post();
        $res = $this->receiptService->updateShipLaunch($customerId, $data);
        if ($res !== false) {
            return $this->jsonSuccess([], __('receipt_add_ship_success', [], 'controller/receipt'));
        }
        return $this->jsonFailed(__('receipt_disable_edit', [], 'controller/receipt'));
    }

    // 判断是否修改
    public function checkDataIsChanged()
    {
        $customerId = $this->customer->getId();
        $data = $this->request->post();
        $receipt = app(ReceiptRepository::class)->getReceiptByReceiveOrderId($customerId, $data['receive_order_id']);
        $res = $this->receiptService->addReceiptsOrderHistory($receipt->toArray(), $data, ReceiptsOrderHistoryType::PENDING ,false);
        if ($res) {
            return $this->jsonSuccess();
        }
        return $this->jsonFailed();
    }

    // 获取产品列表的api
    public function getProducts()
    {
        list($codeOrMpnFilter,) = explode('/', trim($this->request->get('code_mpn', '')));
        $products = app(ProductRepository::class)->getSellerProducts($this->customer->getId(), $codeOrMpnFilter, 8);
        return $this->jsonSuccess($products);
    }

    // 导入入库单模板页面
    public function importTemp()
    {
        // 海运头程
        $data['shippingList'] = ReceiptShipping::getViewItems();
        // 集装箱尺寸
        $data['containerSizeList'] = $this->receiptRepos->getContainerSizeList();
        // 启运港
        $data['departureList'] = $this->receiptRepos->getDepartureList();
        // 期望船期
        $data['shippingDateList'] = $this->receiptRepos->getShippingDate();
        // 客户自发期望船期
        $data['selfShippingDateList'] = $this->receiptRepos->getShippingDate(true);
        // 期望发货时间
        $data['deliverDateList'] = $this->receiptRepos->getDeliveryDate();

        $this->render('customerpartner/warehouse/import_receipt_temp', $data);
    }

    // 入库单导入模板文件-下载
    public function downloadTemplateFile()
    {
        $file = DIR_DOWNLOAD . "Incoming Shipment Import Template.xls";
        return $this->response->download($file);
    }

    /**
     * 导入入库单
     *
     * @return Symfony\Component\HttpFoundation\JsonResponse
     * @throws Exception
     */
    public function import()
    {
        $this->load->language('customerpartner/warehouse/receipt');
        // 基本信息校验
        $validationStatus = $this->importValidate();
        if ($validationStatus !== true) {
            return $this->jsonFailed($validationStatus);
        }
        // 上传文件校验
        $importData = $this->checkImportData();
        if (!is_array($importData)) {
            return $this->jsonFailed($importData);
        }

        $data['shipping_way'] = $this->request->post('shipping_way');
        $data['container_size'] = $this->request->post('container_size');
        $data['port_start'] = $this->request->post('port_start');
        $data['expected_shipping_date_start'] = $this->request->post('expected_shipping_date_start', '');
        $data['expected_shipping_date_end'] = $this->request->post('expected_shipping_date_end', '');
        $data['bookData'] = json_decode(html_entity_decode(str_replace("&nbsp;", "", $this->request->post('bookData'))), true);

        $data['last_status'] = $this->request->post('status');
        $data['status'] = $this->request->post('status');
        $data['products'] = $importData;
        $data['sku_count'] = count($importData);

        // 委托海运需要入库单托书校验
        if ($data['shipping_way'] == ReceiptShipping::ENTRUSTED_SHIPPING) {
            $bookForm = app(ShippingOrderBookForm::class);
            $checkRes = $bookForm->checkDataFormat();
            if ($checkRes !== true) {
                return $this->jsonFailed(__('托书数据格式不正确，请检查后提交', [], 'controller/receipt'));
            }
            $data['bookData']['shipping_list'] = $bookForm->shipping_list;
            $data['bookData']['special_product_type'] = $bookForm->special_product_type;
        }

        // seller申请入库单的时候没有任何一个入库单同步到海运系统，则入库单提交成功之后状态为【运营顾问确认中】，此时入库单不同步到海运系统
        if ($data['status'] == ReceiptOrderStatus::APPLIED && !app(ReceiptRepository::class)->existSynchronizedReceivesBySellerId($this->customerId)) {
            $data['status'] = ReceiptOrderStatus::ACCOUNT_MANAGER_REVIEWING;
        }
        $ReceiptService = app(ReceiptService::class);
        $addResult = $ReceiptService->addReceipt($this->customerId, $data);

        if ($addResult) {
            if ($data['status'] == ReceiptOrderStatus::TO_SUBMIT) {
                $returnMsg = __('text_import_save_success', ['no' => $addResult], 'controller/receipt');
            } elseif ($data['status'] == ReceiptOrderStatus::ACCOUNT_MANAGER_REVIEWING) {
                $returnMsg = __('receipt_account_manager_reviewing_success', ['code' => $addResult], 'controller/receipt');
            } elseif ($data['shipping_way'] == ReceiptShipping::ENTRUSTED_SHIPPING) {
                $returnMsg = __('text_import_submit_giga_success', ['no' => $addResult], 'controller/receipt');
            } elseif ($data['shipping_way'] == ReceiptShipping::MY_SELF) {
                $returnMsg = __('text_import_submit_success', ['no' => $addResult], 'controller/receipt');
            } else {
                $returnMsg = __('text_import_submit_local_success', ['no' => $addResult], 'controller/receipt');
            }

            return $this->jsonSuccess([], $returnMsg);
        }

        return $this->jsonFailed(__('text_error_import', [], 'controller/receipt'));
    }

    /**
     * 入库单导入-提交表单基本校验
     *
     * @param null $scene
     * @return bool|string
     */
    private function importValidate($scene = null)
    {
        $shippingDateStr = $this->request->post('expected_shipping_date_start') . '~' . $this->request->post('expected_shipping_date_end');
        $portStart = $this->request->post('port_start', '');
        $validationErrorMsg = [
            'required' => __('带*号为必填项', [], 'controller/receipt')
        ];
        $rules = $this->getRules($scene);
        $validation = $this->request->validate($rules, $validationErrorMsg);
        if ($validation->fails()) {
            return $validation->errors()->first();
        }

        // 根据 海运头程 检测 期望船期或者预计发货时间、起运港或起运城市
        if ($this->request->post('shipping_way') == ReceiptShipping::B2B_LOCAL) { // 海运方式：B2B Local
            // 起运城市验证
            if (empty(trim($portStart)) || mb_strlen(trim($portStart)) > 50) {
                return __('无效的起运城市', [], 'controller/receipt');
            }

            // 期望发货时间验证
            $checkDateList = $this->receiptRepos->getDeliveryDate(); // 期望发货时间
            $dateErrorMsg = __('无效的期望发货日期', [], 'controller/receipt');
        } else { // 海运方式：委托海运操作 | 客户自发
            // 起运港验证
            $departureArr = $this->receiptRepos->getDepartureList()->toArray(); // 起运港
            if (!in_array($portStart, $departureArr)) {
                return __('无效的起运港', [], 'controller/receipt');
            }

            // 期望船期验证
            if ($this->request->post('shipping_way') == ReceiptShipping::MY_SELF) {
                $checkDateList = $this->receiptRepos->getShippingDate(true); // 期望船期范围
            } else {
                $checkDateList = $this->receiptRepos->getShippingDate(); // 期望船期范围
            }

            $dateErrorMsg = __('无效的期望船期', [], 'controller/receipt');
        }
        $checkDateArr = [];
        foreach ($checkDateList as $checkDate) {
            $checkDateArr[] = $checkDate['start'] . '~' . $checkDate['end'];
        }
        if ($scene != 'edit' && !in_array($shippingDateStr, $checkDateArr)) {
            return $dateErrorMsg;
        }

        return true;
    }

    public function getRules($scene)
    {
        // 海运头程
        $shippingArr = ReceiptShipping::getValues();
        // 集装箱尺寸
        $containerSizeArr = $this->receiptRepos->getContainerSizeList()->toArray();
        if ($scene == 'edit') {
            return [
                'shipping_way' => [
                    Rule::in($shippingArr)
                ],
                'container_size' => [
                    Rule::in($containerSizeArr)
                ]
            ];
        } else {
            return [
                'shipping_way' => [
                    'required',
                    Rule::in($shippingArr)
                ],
                'container_size' => [
                    'required',
                    Rule::in($containerSizeArr)
                ],
                'status' => [
                    'required',
                    Rule::in(ReceiptOrderStatus::canAddStatus())
                ]
            ];
        }
    }

    // 入库单明细验证
    protected function checkProducts()
    {
        $products = $this->request->post('products', []);
        if ($this->request->post('status') == ReceiptOrderStatus::APPLIED && empty($products)) {
            return $this->language->get('text_error_received_detail_empty');
        }
        foreach ($products as $key => $item) {
            $errorLine = $key + 1;
            if (!$item['expected_qty']) {
                return sprintf($this->language->get('text_error_import_qty_empty'), $errorLine);
            }
            if (!$this->request->post('shipping_way') == ReceiptShipping::B2B_LOCAL) {
                if (!$item['hscode']) {
                    return sprintf($this->language->get('text_error_import_hscode_empty'), $errorLine);
                }
                // HSCode 其数值部分为10位的数字（中间可以穿插非数字字符） 且 总长度不能超过表结构中长度30字符
                $hsCodeNumer = preg_replace('/\D+/', '', $item['hscode']);
                if (mb_strlen($hsCodeNumer, 'utf-8') != self::LIMIT_HSCODE_LENGTH || mb_strlen($item['hscode'], 'utf-8') > self::LIMIT_HSCODE_TOTAL_LENGTH) {
                    return sprintf($this->language->get('text_error_import_hscode'), $errorLine);
                }
                // 301 HSCode 总长度
                if ($item['301_hscode'] && mb_strlen($item['301_hscode'], 'utf-8') > self::LIMIT_301_HSCODE_TOTAL_LENGTH) {
                    return sprintf($this->language->get('text_error_import_301_hscode'), $errorLine);
                }
            }
            // 入库数量取值只能为 1-9999 之间的数字
            if (!is_numeric($item['expected_qty']) || $item['expected_qty'] < 1 || $item['expected_qty'] > 9999) {
                return sprintf($this->language->get('text_error_import_qty'), $errorLine);
            }

        }
        return true;
    }

    /**
     * 入库单导入-上传文件-数据校验
     *
     * @return array|string
     * @throws Exception
     */
    private function checkImportData()
    {
        $validationErrorMsg = [
            'receiptFile.extension' => __('text_invalid_file_extension', [], 'controller/receipt')
        ];
        $validation = $this->request->validate([
            'receiptFile' => [
                'required',
                'extension:xls,xlsx'
            ]
        ], $validationErrorMsg);
        if ($validation->fails()) {
            return $validation->errors()->first();
        }

        // 检测上传的ExceL文件字段信息
        $this->load->model('tool/excel');
        $excelData = $this->model_tool_excel->getExcelData($this->request->filesBag->get('receiptFile')->getRealPath());
        $targetExcelHead = ['*MPN', '*Estimated QTY'];

        if (count($excelData) < 2 || !$this->model_tool_excel->checkExcelFirstLine($excelData[0], $targetExcelHead)) {
            return __('text_error_import_excel_data', [], 'controller/receipt');
        }

        unset($excelData[0]);
        $itemCodeArr = array_column($excelData, 0);

        // 导入的itemCode不能包含：combo产品、头款产品、补运费产品、无效产品
        $productRepo = app(ProductRepository::class);
        $products = $productRepo->getSellerNormalProductsBySkus($this->customerId, $itemCodeArr)->toArray();
        $productsArr = [];
        foreach ($products as $item) {
            $productsArr[$item['mpn']] = $item;
        }
        $targetProducts = [];
        $repetitionCheck = [];
        foreach ($excelData as $key => $item) {
            $errorLine = $key + 1;
            $receiptProduct = [];
            $receiptProduct['mpn'] = trim($item[0]);
            if (in_array($receiptProduct['mpn'], $repetitionCheck)) { // 上面已经出现（重复的MPN）
                return __('text_error_import_repeat_data', ['line' => $errorLine, 'mpn' => $receiptProduct['mpn']], 'controller/receipt');
            }
            $repetitionCheck[] = $receiptProduct['mpn'];
            $receiptProduct['expected_qty'] = trim($item[1]);

            if ($receiptProduct['mpn'] === '') {
                return __('text_error_import_mpn_empty', ['line' => $errorLine], 'controller/receipt');
            }
            if ($receiptProduct['expected_qty'] === '') {
                return __('text_error_import_qty_empty', ['line' => $errorLine], 'controller/receipt');
            }

            // 入库数量取值只能为 1-9999 之间的数字
            if (!is_numeric($receiptProduct['expected_qty']) || $receiptProduct['expected_qty'] != floor($receiptProduct['expected_qty']) || $receiptProduct['expected_qty'] < 1 || $receiptProduct['expected_qty'] > 9999) {
                return __('text_error_import_qty', ['line' => $errorLine], 'controller/receipt');
            }

            // mpn 未查找到
            if (!isset($productsArr[$receiptProduct['mpn']])) {
                return __('text_error_import_mpn', ['line' => $errorLine, 'mpn' => $receiptProduct['mpn']], 'controller/receipt');
            }

            unset($productsArr[$receiptProduct['mpn']]['name']);
            unset($productsArr[$receiptProduct['mpn']]['image']);
            $targetProducts[] = array_merge($receiptProduct, $productsArr[$receiptProduct['mpn']]);
        }

        return $targetProducts;
    }

    private function framework($scene = '')
    {
        $this->setDocumentInfo(__('入库管理', [], 'catalog/document'));
        if ($scene == 'add') {
            $this->setDocumentInfo(__('入库单申请', [], 'catalog/document'));
        } elseif ($scene == 'view') {
            $this->setDocumentInfo(__('入库单详情', [], 'catalog/document'));
        } elseif ($scene == 'edit') {
            $this->setDocumentInfo(__('编辑入库单', [], 'catalog/document'));
        }


        $breadcrumbs = $this->getBreadcrumbs([
            [
                'text' => __('入库管理', [], 'catalog/document'),
                'href' => 'javascript:void(0)'
            ],
            'current',
        ]);
        return [
            'breadcrumbs' => $breadcrumbs,
            'separate_column_left' => $this->load->controller('account/customerpartner/column_left'),
            'footer' => $this->load->controller('account/customerpartner/footer'),
            'header' => $this->load->controller('account/customerpartner/header'),
        ];
    }

    /**
     * 模糊搜索显示入库单号
     * @return JsonResponse
     */
    public function autoComplete()
    {
        //筛选条件
        $filter = [
            'filter_receiving_order_number' => $this->request->get('filter_receiving_order_number', ''),
            'page' => 1,
            'limit' => 5
        ];
        //查询字段
        $filter['column'] = ['receive_number'];
        //查询数据
        $listAndCount = app(ReceiptRepository::class)->getReceiptOrderListAndCount($filter, $this->customerId);
        if ($listAndCount['receiptOrderCount'] > 0) {
            return $this->jsonSuccess($listAndCount['receiptOrderList']);
        }
        return $this->jsonFailed();
    }

    /**
     * 分页
     * @param int $total
     * @param int $page
     * @param int $pageSize
     */
    private function setPanination(int $total, int $page, int $pageSize)
    {
        $data = [];
        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $pageSize;
        $pagination->url = $this->url->link('customerpartner/warehouse/receipt', '' . $this->getUrlParam() . '&page={page}', true);

        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page - 1) * $pageSize) + 1 : 0, ((($page - 1) * $pageSize) > ($total - $pageSize)) ? $total : ((($page - 1) * $pageSize) + $pageSize), $total, ceil($total / $pageSize));
        return $data;
    }

    private function getUrlParam()
    {
        $urlParam = [];
        $filter_object = [
            'filter_receiving_order_number',
            'filter_container_code',
            'filter_status',
            'filter_received_from',
            'filter_received_to',
            'filter_shipping_way',
        ];
        foreach ($filter_object as $item) {
            $getItem = $this->request->get($item);
            if (isset($getItem)) {
                $urlParam[$item] = $getItem;
            }
        }
        return http_build_query($urlParam);
    }

    //下载入库单
    public function download()
    {
        $this->load->language('customerpartner/warehouse/receipt');
        //筛选条件与查询字段
        $filter = [
            'filter_receiving_order_number' => $this->request->get('filter_receiving_order_number', ''),
            'filter_container_code' => $this->request->get('filter_container_code', ''),
            'filter_status' => $this->request->get('filter_status', ''),
            'filter_received_from' => $this->request->get('filter_received_from', ''),
            'filter_received_to' => $this->request->get('filter_received_to', ''),
            'filter_shipping_way' => $this->request->get('filter_shipping_way', ''),
            'column' => [
                'receive_number',
                'shipping_way',
                'container_code',
                'status',
                'receive_date',
                'apply_date',
            ]
        ];
        //查询数据
        $listAndCount = app(ReceiptRepository::class)->getReceiptOrderListAndCount($filter, $this->customerId);

        $spresadsheet = new Spreadsheet();
        $spresadsheet->setActiveSheetIndex(0);
        $sheet = $spresadsheet->getActiveSheet();
        $sheet->setTitle(__('入库单列表', [], 'catalog/document'));
        $fileName = __('入库单列表', [], 'catalog/document') . ".xlsx";

        // 字体加粗
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        //宽度
        $sheet->getDefaultColumnDimension()->setWidth(25);

        // 填充数据
        $sheet->setCellValue('A1', __('入库单号', [], 'catalog/view/customerpartner/warehouse/receipt_list'));
        $sheet->setCellValue('B1', __('运输方式', [], 'catalog/view/customerpartner/warehouse/receipt_list'));
        $sheet->setCellValue('C1', __('集装箱号', [], 'catalog/view/customerpartner/warehouse/receipt_list'));
        $sheet->setCellValue('D1', __('状态', [], 'catalog/view/customerpartner/warehouse/receipt_list'));
        $sheet->setCellValue('E1', __('收货日期', [], 'catalog/view/customerpartner/warehouse/receipt_list'));
        $sheet->setCellValue('F1', __('申请日期', [], 'catalog/view/customerpartner/warehouse/receipt_list'));
        //居中显示
        $styleArray = array(
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER, // 水平居中
                'vertical' => Alignment::VERTICAL_CENTER // 垂直居中
            ]
        );

        $info = $listAndCount['receiptOrderList'];
        if (count($info) > 0) {
            //入库单状态 与运输方式 处理值
            $statusList = ReceiptOrderStatus::getViewItems();
            $ShippingList = ReceiptShipping::getViewItems();
            foreach ($info as &$val) {
                $val['shipping_way'] = $ShippingList[$val['shipping_way']] ?? '';
                //已废弃也显示已取消
                if ($val['status'] == ReceiptOrderStatus::ABANDONED) {
                    $val['status'] = ReceiptOrderStatus::CANCEL;
                }
                $val['status'] = $statusList[$val['status']];
                $val['apply_date'] = $val['apply_sort_date'];
                unset($val['apply_sort_date']);
            }
            $sheet->fromArray($info, null, 'A2');
            $sheet->getStyle('A1:F' . (count($info) + 1))->applyFromArray($styleArray);
        } else {
            $sheet->fromArray([__('无记录', [], 'catalog/view/customerpartner/warehouse/receipt_list')], null, 'A2');
            $sheet->mergeCells('A2:F2');
            $sheet->getStyle('A1:F2')->applyFromArray($styleArray);
        }
        ob_end_clean();
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
        $writer = IOFactory::createWriter($spresadsheet, 'Xlsx');
        $writer->save('php://output');
    }

    //下载入库单明细
    public function downloadDetail()
    {
        $receive_order_id = $this->request->get('receive_order_id', '');
        $receipt = app(ReceiptRepository::class)->getReceiptById($this->customerId, $receive_order_id);
        $info = obj2array($receipt);

        $fileName = 'Incoming Shipment Detail.xlsx';
        $spresadsheet = new Spreadsheet();
        $spresadsheet->setActiveSheetIndex(0);
        $sheet = $spresadsheet->getActiveSheet();
        $sheet->getDefaultColumnDimension()->setWidth(17);
        $sheet->setTitle('Incoming Shipment Detail');

        if ($info) {
            if ($info['container_code']) {
                $fileName = $info['container_code'] . '.xlsx';
            }

            //合并单元格
            $sheet->mergeCells('F1:I1');
            $sheet->mergeCells('K1:L1');
            $sheet->mergeCells('G2:I2');
            //居中样式
            $styleArray = array(
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER, // 水平居中
                    'vertical' => Alignment::VERTICAL_CENTER // 垂直居中
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN //细边框
                    ]
                ]
            );
            //处理并填充数据
            $sheet->setCellValue('A1', 'Cont.NO')->getStyle('A1')->getFont()->setBold(true);
            $sheet->setCellValue('C1', 'Cont.Size')->getStyle('C1')->getFont()->setBold(true);
            $sheet->setCellValue('E1', 'Entry No.')->getStyle('E1')->getFont()->setBold(true);
            $sheet->setCellValue('J1', 'Date')->getStyle('J1')->getFont()->setBold(true);
            $sheet->setCellValue('M1', 'Warehouse')->getStyle('M1')->getFont()->setBold(true);
            $sheet->setCellValue('B1', $info['container_code'] ?? '')->getStyle('B1')->getFont()->getColor()->setARGB(Color::COLOR_RED);
            $sheet->setCellValue('D1', $info['container_size'] ?? '')->getStyle('D1')->getFont()->getColor()->setARGB(Color::COLOR_RED);
            $sheet->setCellValue('F1', $info['receive_number'] ?? '')->getStyle('F1')->getFont()->getColor()->setARGB(Color::COLOR_RED);
            $sheet->setCellValue('K1',  date('Y/m/d'))->getStyle('K1')->getFont()->getColor()->setARGB(Color::COLOR_RED);
            $sheet->setCellValue('N1', $info['area_warehouse'] ?? 'Cloud Warehouse')->getStyle('N1')->getFont()->getColor()->setARGB(Color::COLOR_RED);

            $sheet->setCellValue('A2', 'Description');
            $sheet->setCellValue('B2', 'Customer SKU');
            $sheet->setCellValue('C2', 'SKU CODE');
            $sheet->setCellValue('D2', 'PCS/CTN');
            $sheet->setCellValue('E2', 'CTNS');
            $sheet->setCellValue('F2', 'Order Quantity');
            if ($this->customer->getCountryId() == AMERICAN_COUNTRY_ID) {//美国国别
                $sheet->setCellValue('G2', 'Volume(Inch)');
                $sheet->setCellValue('J2', 'Weight(Lbs)');
            } else {
                $sheet->setCellValue('G2', 'Volume(CM)');
                $sheet->setCellValue('J2', 'Weight(KG)');
            }
            $sheet->setCellValue('K2', 'QTY/Pallet');
            $sheet->setCellValue('L2', 'Carton Damage');
            $sheet->setCellValue('M2', 'Commodity Damage');
            $sheet->setCellValue('N2', 'ActualQuantity');
            if ($info['receipt_details']) {
                //处理与填充商品数据
                $productData = [];
                $estimatedQuantityCount = 0;
                foreach ($info['receipt_details'] as $key => $val) {
                    $productData[$key] = [
                        html_entity_decode($val['product_desc']['name']) ?? '',
                        $val['mpn'] ?? '',
                        $val['sku'] ?? '',
                        1,
                        intval($val['expected_qty']),
                        intval($val['expected_qty']),
                        doubleval($val['length']),
                        doubleval($val['width']),
                        doubleval($val['height']),
                        doubleval($val['weight']),
                        '',
                        '',
                        '',
                        ''
                    ];
                    $estimatedQuantityCount += intval($val['expected_qty']);
                }
                $sheet->fromArray($productData, null, 'A3');
                $sheet->setCellValue('D' . (count($productData) + 3), 'Total');
                $sheet->setCellValue('E' . (count($productData) + 3), $estimatedQuantityCount);
                $sheet->setCellValue('F' . (count($productData) + 3), $estimatedQuantityCount);
                $sheet->getStyle('A1:N' . (count($productData) + 3))->applyFromArray($styleArray);
            } else {
                $sheet->mergeCells('A3:N3');
                $sheet->fromArray(['No records.'], null, 'A3');
                $sheet->getStyle('A1:N3')->applyFromArray($styleArray);
            }
        } else {
            $sheet->fromArray(['No records.'], null, 'A1');
        }

        ob_end_clean();
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
        $writer = IOFactory::createWriter($spresadsheet, 'Xlsx');
        $writer->save('php://output');
    }

    //取消入库单
    public function cancelReceiptOrder()
    {
        $this->load->language('customerpartner/warehouse/receipt');
        $receive_order_id = intval(trim($this->request->get('receive_order_id', '')));
        $receipt = app(ReceiptRepository::class)->getReceiptByReceiveOrderId($this->customerId, $receive_order_id);
        if (!isset($receipt) || empty($receipt)) {
            return $this->jsonFailed(__('入库单取消失败', [], 'controller/receipt'));
        }
        //不能取消
        if (in_array($receipt->status, ReceiptOrderStatus::disableCancelStatus())) {
            return $this->jsonFailed(__('当入库单状态为已取消、已收货、待收货时，入库单不能取消', [], 'controller/receipt'));
        }
        //取消
        if (app(ReceiptService::class)->cancelReceiptStatus(intval($this->customerId), $receive_order_id)) {
            if (in_array($receipt->status, [ReceiptOrderStatus::APPLIED ,ReceiptOrderStatus::TO_SUBMIT, ReceiptOrderStatus::ACCOUNT_MANAGER_REVIEWING])) {
                return $this->jsonSuccess(__('入库单取消成功，状态更新为已取消', [], 'controller/receipt'));
            }
            return $this->jsonSuccess(__('入库单取消申请已提交，后续信息请关注平台站内信或者查看入库单详情！', [], 'controller/receipt'));
        }
        return $this->jsonFailed(__('入库单取消失败', [], 'controller/receipt'));
    }

    //添加 用户 确认入库商品包装规范说明
    public function addReceiptsProductPacking()
    {
        $this->load->language('customerpartner/warehouse/receipt');
        if (app(ReceiptService::class)->addReceiptsProductPacking($this->customerId)) {
            return $this->jsonSuccess();
        }
        return $this->jsonFailed();
    }
}
