<?php

use App\Enums\Customer\CustomerAccountingType;
use App\Enums\SellerBill\SellerBillBuyerStorageFeeOrderType;
use App\Logging\Logger;
use App\Models\SellerBill\SellerBillBuyerStorage;
use App\Repositories\SellerBill\SettlementRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Catalog\Controllers\AuthSellerController;
use App\Enums\SellerBill\SellerBillProgramCode;
use App\Enums\SellerBill\SellerBillTypeCode;
use App\Components\Storage\StorageCloud;
use App\Enums\SellerBill\SellerBillSettlementStatus;
use Illuminate\Support\Carbon;
use App\Models\SellerBill\SellerBillStorage;
use App\Models\SellerBill\SellerBillInterestDetail;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\SellerBill\SellerBill;
use App\Models\SellerBill\SellerBillDetail;
use App\Enums\SellerBill\SellerBillDetailFrozenFlag;
use Framework\DataProvider\Paginator;
use App\Models\SellerBill\SellerBillFrozenRelease;
use App\Repositories\SellerBill\DealSettlement\DealSettlement;

/**
 * @property ModelAccountSellerBillBillDetail $model_account_seller_bill_bill_detail
 * Class ControllerAccountSellerBillBillDetail
 */
class ControllerAccountSellerBillBillDetail extends AuthSellerController
{
    private $sellerId;
    private $settlementRepo;
    public $data = [];
    public $tempFile = [];

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        // Seller账单目前权限仅限：美国 & (外部Seller核算账户||美国本土核算||配置的固定账号)
        if (!($this->customer->isUSA()
            && ($this->customer->isOuterAccount()
                || in_array($this->customer->getId(), SHOW_BILLING_MANAGEMENT_SELLER)
                || $this->customer->getAccountType() == CustomerAccountingType::AMERICA_NATIVE))) {
            $this->redirect($this->isNotSellerRedirect)->send();
        }

        $this->sellerId = $this->customer->getId();
        $this->load->model('account/seller_bill/bill_detail');
        $this->settlementRepo = app(SettlementRepository::class);
    }

    // 账单明细
    public function index()
    {
        $this->document->setTitle(__('结算明细', [], 'catalog/document'));
        $data = $this->framework();
        $data['is_onsite_seller'] = $this->customer->isGigaOnsiteSeller(); // 是否Onsite seller

        return $this->render('account/seller_bill/detail', $data);
    }

    // 结算单类型
    public function getSettlementType()
    {
        $version = $this->request->post('version', '');
        $typeArr = SellerBillTypeCode::getBillTypeByVersion($version);

        return $this->jsonSuccess($typeArr);
    }

    // 获取明细列表
    public function getList()
    {
        $version = $this->request->post('version', '');
        if (strtoupper($version) != SellerBillProgramCode::V3) {
            $data = $this->getListOld();
        } else {
            $data = $this->getListNew();
        }

        return $this->json($data);
    }

    /**
     * 结算单三期
     *
     * @return array
     */
    private function getListNew()
    {
        $param['page'] = $this->request->post('page', 1);
        $param['pageSize'] = $this->request->post('per_page', 20);
        $param['billId'] = $this->request->post('filter_settlement_cycle', '');
        $param['typeId'] = $this->request->post('filter_settlement_item', '');
        $param['version'] = $this->request->post('version', '');
        $param['start_date'] = $this->request->post('start_date', '');
        $param['end_date'] = $this->request->post('end_date', '');
        $param['costNo'] = str_replace(['%', '_'], ['\%', '\_'],trim($this->request->post('cost_no', '')));
        $param['sort'] = $this->request->post('order', 'asc');
        $param['frozen_status'] = $this->request->post('frozen_status', '');
        if (! $param['billId'] || ! in_array($param['typeId'], array_column(SellerBillTypeCode::getBillTypeByVersion($param['version']), 'value'))) {
            return [];
        }

        $rows = $this->settlementRepo->getSettlementDetailList($this->customer->getId(), $param);
        $total = $this->settlementRepo->getSettlementDetailTotal($this->customer->getId(), $param);

        return compact('total', 'rows');
    }

    // 下载表格对应数据
    public function download()
    {
        $version = $this->request->get('version', '');
        if (strtoupper($version) != SellerBillProgramCode::V3) {
            $data = $this->downloadOld();
        } else {
            $data = $this->downloadNew();
        }

        return $data;
    }

    // 下载某项明细数据文件
    public function downloadDetail()
    {
        $detailId = $this->request->get('detail_id', '');
        $fileDetailId = $this->request->get('menu_detail_id', '');

        list($billDetail, $fileList) = $this->settlementRepo->getDetailFileList($this->customer->getId(), $detailId);
        if (! $billDetail || ($fileDetailId && ! $fileList)) {
            $this->redirect($this->isNotSellerRedirect)->send();
        }
        $fileDetailIds = array_column($fileList, 'id');
        if ($fileDetailId && in_array($fileDetailId, $fileDetailIds)) { // 存在后台录入--直接取OSS的文件下载
            foreach ($fileList as $item) {
                if ($item['id'] == $fileDetailId) {
                    if ($item['file_path'] && $item['file_name']) {
                       return StorageCloud::root()->browserDownload($item['file_path'], $item['file_name']);
                    }
                    break;
                }
            }
        } elseif (in_array($billDetail['code'], SellerBillTypeCode::getAutoCalcTypes()) && $billDetail['settlement_status'] != SellerBillSettlementStatus::GOING) { // 物流费、仓租费、欠款利息
            if ($billDetail['code'] == SellerBillTypeCode::V3_LOGISTIC_DETAIL) { // 物流费
                $fileArr = $this->settlementRepo->getFileList([$billDetail['file_menu_id']])->toArray();
                if ($fileArr && $fileArr[0]['file_path'] && $fileArr[0]['file_name']) {
                   return StorageCloud::root()->browserDownload($fileArr[0]['file_path'], $fileArr[0]['file_name']);
                }
            } else {
                $fileName = $this->customer->getFirstName() . $this->customer->getLastName() . '-' . __($billDetail['code'], [], 'controller/bill') .
                    '(' . Carbon::createFromFormat('Y-m-d H:i:s', $billDetail['start_date'])->toDateString() . '-' .
                    Carbon::createFromFormat('Y-m-d H:i:s', $billDetail['end_date'])->toDateString() .')';
                if ($billDetail['code'] == SellerBillTypeCode::V3_STORAGE_DETAIL) { // 仓租费
                    // Onsite Seller 仓租费下载
                    if ($this->customer->isGigaOnsiteSeller()) {
                        $this->downloadOnsiteSellerStorageFile($billDetail['bill_id'], $fileName);
                    } else {
                        $this->downloadStorageFile($billDetail['bill_id'], $fileName);
                    }
                } else { // 欠款利息
                    $this->downloadInterestFile($billDetail['bill_id'], $fileName);
                }
            }
        }

        $this->redirect($this->isNotSellerRedirect)->send();
    }

    /**
     * 结算单三期下载
     *
     * @return array|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    private function downloadNew()
    {
        $param['page'] = 1;
        $param['pageSize'] = 50000;
        $param['billId'] = $this->request->get('filter_settlement_cycle', '');
        $param['typeId'] = $this->request->get('filter_settlement_item', '');
        $param['version'] = $this->request->get('version', '');
        $param['start_date'] = $this->request->get('start_date', '');
        $param['end_date'] = $this->request->get('end_date', '');
        $param['costNo'] = str_replace(['%', '_'], ['\%', '\_'],trim($this->request->get('cost_no', '')));
        $param['sort'] = $this->request->get('order', 'asc');
        $param['frozen_status'] = $this->request->get('frozen_status', '');

        if (! $param['billId'] || ! in_array($param['typeId'], array_column(SellerBillTypeCode::getBillTypeByVersion($param['version']), 'value'))) {
            $this->redirect($this->isNotSellerRedirect)->send();
        }
        $typeArr = array_column(SellerBillTypeCode::getBillTypeByVersion($param['version']), 'code', 'value');
        $code = $typeArr[$param['typeId']];

        list($header, $fileName) = $this->getDownloadHeadAndFileName($code);
        $list = $this->settlementRepo->getSettlementDetailList($this->customer->getId(), $param);

        $data = [];
        if ($code == SellerBillTypeCode::V3_ORDER) {
            foreach ($list as $item) {
                $res = [];
                $res[] = $item['date_format']['day'] . ' ' . $item['date_format']['time'];
                $typeDesc = ! empty($item['type_format']['desc']) ? "({$item['type_format']['desc']})" : '';
                $res[] = $item['type_format']['name'] . $typeDesc;
                $noDesc = ! empty($item['no']['no_desc']) ? "({$item['no']['no_desc']})" : '';
                $res[] = $item['no']['no'] .  $noDesc . "\t";
                $res[] = $item['item_code'];
                $res[] = $item['show_quantity'];
                $res[] = $item['price_value'];
                $res[] = $item['package_fee_format'];
                $res[] = $item['fright_format'];
                $res[] = $item['total_format'];
                // Onsite Seller 需要额外展示对应信息
                if ($this->customer->isGigaOnsiteSeller()) {
                    $res[] = $item['surplus_frozen_total_format'];
                    $res[] = $item['frozen_flag'] == SellerBillDetailFrozenFlag::FROZEN ? __('是', [], 'controller/bill') : __('否', [], 'controller/bill');
                    $res[] = $item['frozen_flag'] == SellerBillDetailFrozenFlag::UNFROZEN ? $item['total_desc'] : '';
                    $res[] = $item['frozen_flag'] == SellerBillDetailFrozenFlag::UNFROZEN ? $item['unfrozen_type'] : '';
                }

                $data[] = $res;
            }
        } else {
            foreach ($list as $item) {
                $res = [];
                $res[] = $item['date_format']['day'] . ' ' . $item['date_format']['time'];
                $typeDesc = ! empty($item['type_format']['desc']) ? "({$item['type_format']['desc']})" : '';
                $res[] = $item['type_format']['name'] . $typeDesc;
                $res[] = $item['no']['no'] . "\t";
                $res[] = implode(',', array_column($item['file_list'], 'name'));
                $res[] = $item['cost_detail'];
                $res[] = $item['remark'];
                $res[] = $item['total_format'];

                $data[] = $res;
            }
        }
        array_unshift($data, $header);

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle($fileName);
            $sheet->fromArray($data);
            $writer = IOFactory::createWriter($spreadsheet, 'Xls');
            $downloadFileName = $fileName . '_' . time() . '.xls';
            return $this->response->streamDownload(
                function () use ($writer) {
                    $writer->save('php://output');
                }, $downloadFileName, ['Content-Type' => 'application/vnd.ms-excel']
            );
        } catch (Exception $e) {
            Logger::error('结算单三期下载错误：' . $e->getMessage());
            $this->redirect($this->isNotSellerRedirect)->send();
        }
    }

    /**
     * 获取下载文件表格头和文件名
     *
     * @param string code 结算单类目Code
     * @return array
     */
    private function getDownloadHeadAndFileName($code)
    {
        $orderHeader = [
            __('发生时间', [], 'controller/bill'),
            __('订单项类型', [], 'controller/bill'),
            __('费用编号', [], 'controller/bill'),
            __('Item Code', [], 'controller/bill'),
            __('数量', [], 'controller/bill'),
            __('单件货值', [], 'controller/bill'),
            __('单件打包费', [], 'controller/bill'),
            __('单件运费', [], 'controller/bill'),
            __('小计', [], 'controller/bill')
        ];
        // Onsite Seller 需要额外展示对应信息
        if ($this->customer->isGigaOnsiteSeller()) {
            $onsiteColunm = [
                __('剩余冻结金额', [], 'controller/bill'),
                __('订单是否冻结中', [], 'controller/bill'),
                __('可结算时间', [], 'controller/bill'),
                __('可结算时间类型', [], 'controller/bill'),
            ];
            $orderHeader = array_merge($orderHeader, $onsiteColunm);
        }

        $otherHeader = [
            __('发生时间', [], 'controller/bill'),
            __('订单项类型', [], 'controller/bill'),
            __('费用编号', [], 'controller/bill'),
            __('附件', [], 'controller/bill'),
            __('收费明细', [], 'controller/bill'),
            __('备注', [], 'controller/bill'),
            __('发生金额', [], 'controller/bill')
        ];

        $header = $otherHeader;
        switch ($code) {
            case SellerBillTypeCode::V3_ORDER:
                $header = $orderHeader;
                $fileName = __('订单', [], 'controller/bill');
                break;
            case SellerBillTypeCode::V3_DISBURSEMENT:
                $header[1] = __('垫付费用项类型', [], 'controller/bill');
                $fileName = __('垫付费用', [], 'controller/bill');
                break;
            case SellerBillTypeCode::V3_PLATFORM:
                $header[1] = __('平台费类型', [], 'controller/bill');
                $fileName = __('平台费', [], 'controller/bill');
                break;
            case SellerBillTypeCode::V3_OTHER:
                $header[1] = __('费用项类型', [], 'controller/bill');
                $fileName = __('费用项', [], 'controller/bill');
                break;
            default:
                $fileName = '';
                break;
        }

        return [$header, $fileName];
    }

    private function framework()
    {
        $breadcrumbs = $this->getBreadcrumbs([
            [
                'text' => __('账单管理', [], 'catalog/document'),
                'href' => 'javascript:void(0)'
            ],
            'current',
        ]);
        return [
            'breadcrumbs' => $breadcrumbs,
            'separate_column_left' => $this->load->controller('account/customerpartner/column_left'),
            'footer' => $this->load->controller('account/customerpartner/footer'),
            'header' => $this->load->controller('account/customerpartner/header')
        ];
    }

    public function __destruct()
    {
        foreach ($this->tempFile as $file) {
            is_file($file) && @unlink($file);
        }
    }

    public function detail_info()
    {
        $this->language->load('account/seller_bill/bill');
        $this->document->setTitle($this->language->get('text_bill_detail'));
        $this->document->addScript('catalog/view/javascript/layer/layer.js');
        $this->addBeadCrumbs();
        $this->addOthers();
        $bill_info = $this->orm
            ->table('tb_seller_bill')
            ->where('id', $this->request->request['bill_id'] ?? 0)
            ->where('seller_id', (int)$this->customer->getId())
            ->first();
        if (!$bill_info) {
            $this->response->redirect($this->url->link('error/not_found'));
        }
        $bill_info->reserve_format = $this->currency->formatCurrencyPrice(
            $bill_info->reserve,
            $this->session->data['currency']
        );
        $this->data['bill'] = get_object_vars($bill_info);
        $this->response->setOutput($this->load->view('account/seller_bill/detail/detail_info', $this->data));
    }

    // 下载特殊费用上传的附件
    public function download_spec_fee()
    {
        $spec_ids = $this->request->request['spec_ids'] ?? null;
        $fileDir = $this->orm
                ->table('tb_sys_setup')
                ->where('parameter_key', 'SPECIAL_FEE_PATH')
                ->value('parameter_value') ?? '';
        $fileDir = rtrim($fileDir, '\\\/');
        $file_fields = [
            ['annex1', 'annex_path1'], ['annex2', 'annex_path2'],
            ['annex3', 'annex_path3'], ['annex4', 'annex_path4'],
            ['annex5', 'annex_path5'],
        ];
        $info = $this->model_account_seller_bill_bill_detail->getSpecFee((int)$spec_ids);
        if (empty($info)) {
            $this->response->redirect($this->url->link('error/not_found'));
        }
        $temp = [];
        foreach ($file_fields as $f) {
            if ($info[$f[1]]) {
                $temp[] = ['file_path' => $fileDir . DIRECTORY_SEPARATOR . $info[$f[1]], 'file_name' => $info[$f[0]]];
            }
        }
        $down_file_name = $info['service_project_english'] . date('YmdHis') . '.zip';
        $down_file_name = str_replace(['\\', '/', ' ', '(', ')'], '_', $down_file_name);
        $down_path = generateZipFile($down_file_name, $temp);
        if (false !== $down_path) {
            header('Content-Type: application/octet-stream');
            header('Content-Description: File Transfer');
            header('Content-Disposition: attachment; filename="' . $down_file_name . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($down_path));
            readfile($down_path, 'rb');
            $this->tempFile[] = $down_path;
        } else {
            $this->response->redirect($this->url->link('error/not_found'));
        }
    }

    //下载特定费用指定文件
    public function download_patch()
    {
        $id = $this->request->request['id'] ?? 0;
        if (!isset($this->request->request['file_path'])) {
            $spec_info = $this->orm->table('tb_special_service_fee_file')->where('id', $id)->first();
            if (!$spec_info) {
                $this->response->redirect($this->url->link('error/not_found'));
            }
            $fileDir = $this->orm
                    ->table('tb_sys_setup')
                    ->where('parameter_key', 'SPECIAL_FEE_PATH')
                    ->value('parameter_value') ?? '';
            $fileDir = str_replace(['\\', '\/'], DIRECTORY_SEPARATOR, rtrim($fileDir, '\\\/'));
            $filePath = $fileDir . DIRECTORY_SEPARATOR . $spec_info->file_path;
            $file_name = $spec_info->file_name;
        } else {
            $fileDir = $this->orm
                    ->table('tb_sys_setup')
                    ->where('parameter_key', 'SELLER_BILL_LOGISTIC_FILE_PREFIX')
                    ->value('parameter_value') ?? '';
            $filePath = $fileDir . $this->request->request['file_path'];
            $file_name = substr($filePath, strrpos($filePath, '/') + 1);
        }
        if (!is_file($filePath)) {
            $this->response->redirect($this->url->link('error/not_found'));
        }
        ob_end_clean();//解决乱码
        header('Content-Type: application/octet-stream');
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath, 'rb');
    }

    private function downloadOld()
    {
        $request = $this->request->get();
        $this->language->load('account/seller_bill/bill');
        $rows = $this->model_account_seller_bill_bill_detail->queryBillDetailList($this->customer->getId(), $request);
        $relateArr = [];
        $fileName = '';
        switch ($request['filter_settlement_item']) {
            case 1:
            case 32:
            case 33:
            {
                $fileName = 'order';
                $relateArr = [
                    'produce_date' => $this->language->get('column_produce_date'),
                    'type_name' => $this->language->get('column_bill_type'),
                    'ord_num' => $this->language->get('column_order_num'),
                    'relate_order_num_s' => $this->language->get('column_relate_order_num'),
                    'item_code' => 'Item Code',
                    'mpn_s' => 'MPN',
                    'quantity' => $this->language->get('column_quantity'),
                ];
                if ($request['filter_settlement_item'] == 32) {
                    $fileName = 'reserve';
                    $relateArr=array_merge($relateArr, ['product_total' => $this->language->get('column_product_total')]);
                    $relateArr['logistics_fee']=$this->language->get('column_buyer_freight');
                }
                if ($request['filter_settlement_item'] == 33) {
                    $fileName = 'payment';
                    $relateArr=array_merge($relateArr, ['product_total' => $this->language->get('column_product_total')]);
                    $relateArr['logistics_fee']=$this->language->get('column_back_freight');
                }
                if($request['filter_settlement_item'] == 1){
                    $relateArr['logistics_fee']=$this->language->get('column_freight');
                }
                $relateArr['total']=$this->language->get('column_total');
                break;
            }
            case 2:
            {
                $fileName = 'refund';
                $relateArr = [
                    'produce_date' => $this->language->get('column_produce_date'),
                    'type_name_d' => $this->language->get('column_bill_type'),
                    'type_name_son' => $this->language->get('column_bill_type_son'),
                    'ord_num' => $this->language->get('column_order_num'),
                    'item_code' => 'Item Code',
                    'mpn_s' => 'MPN',
                    'quantity' => $this->language->get('column_quantity'),
                    'logistics_fee' => $this->language->get('column_freight'),
                    'total' => $this->language->get('column_total'),
                ];

                break;
            }
            case 3:
            case 34:
            case 36:
            {
                $fileName = 'other';
                $relateArr = [
                    'produce_date' => $this->language->get('column_produce_date'),
                    'type_name_d' => $this->language->get('column_bill_type'),
                    'type_name_son' => $this->language->get('column_bill_type_son'),
                    'ord_num' => $this->language->get('column_order_num'),
                    'total' => $this->language->get('column_total'),
                ];
                break;
            }
            default :
                break;
        }
        $headerArr = array_values($relateArr);
        $keys = array_keys($relateArr);
        $outputArr = [];
        foreach ($rows as $row) {
            $temp = [];
            foreach ($keys as $key) {
                $temp[] = "\t" . strip_tags($row[$key] ?? '');
            }
            array_push($outputArr, $temp);
        }
        array_unshift($outputArr,$headerArr);
        $this->downExcel($outputArr,  'invoice_detail_' . $fileName . '_' . date('YmdHis') . '.xls');
    }

    // region api
    private function getListOld()
    {
        $this->language->load('account/seller_bill/bill');
        $request = $this->request->post();
        $total = $this->model_account_seller_bill_bill_detail->queryBillDetailTotal((int)$this->customer->getId(), $request);
        $rows = $this->model_account_seller_bill_bill_detail->queryBillDetailList($this->customer->getId(), $request);

        return compact('total', 'rows');
    }

    // endregion api

    private function addBeadCrumbs()
    {
        $this->data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home')
            ],
            [
                'text' => $this->language->get('seller_dashboard'),
                'href' => $this->url->link('customerpartner/seller_center/index', '', true),
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('billing_management'),
                'href' => 'javascript:void(0);',
                'separator' => $this->language->get('text_separator')
            ],
            [
                'text' => $this->language->get('text_bill_detail'),
                'href' => 'javascript:void(0);',
                'separator' => $this->language->get('text_separator')
            ]
        ];
    }

    public function downloadPlatform(){
        $bill_id = $this->request->request['bill_id'];
        $file_name = $this->request->request['file_name'];
        $list = $this->orm->table('tb_seller_bill_platform_detail')
            ->where(['bill_id' => $bill_id])
            ->get();
        if ($list->isEmpty()) {
            $this->response->redirect($this->url->link('error/not_found'));
        }
        $list_a = $list->where('special_rate_type', 0)->all();
        $list_b = $list->where('special_rate_type', 1)->all();
        $customer_id = $this->customer->getId();
        $bill = $this->orm->table('tb_seller_bill')->select('start_date', 'end_date')->where(['id' => $bill_id])->first();
        $logistics_customer_name = $this->orm->table('oc_customer')->where('customer_id', $customer_id)->value('logistics_customer_name');
        $screenname = $this->orm->table('oc_customerpartner_to_customer')->where('customer_id', $customer_id)->value('screenname');
        $data = [];
        foreach ($list_a as $item) {
            $data[] = [
                $item->order_id,
                $item->order_date,
                $logistics_customer_name,
                $screenname,
                $item->value_price,
            ];
        }

        $header = ['PurchaseOrderId', 'PurchaseOrderDate', 'SourceSellerCode', 'SourceSellerName', 'Total value of goods'];
        $row = count($list_a) + 2;
        array_unshift($data, $header);
        $date = [substr($bill->start_date,0,10).'-'.substr($bill->end_date,0,10), null, null, null, null];
        array_unshift($data, $date);
        array_push($data, [null, null, null, 'Subtotal', "=SUM(E3:E{$row})"]);

        if ($list_b) {
            array_push($data, array_pad([], 5, null));
            array_push($data, ['Transaction amounts over $5000 will be charged a 1% Marketplace fee', null, null, null, null]);
            foreach ($list_b as $item) {
                $data[] = [
                    $item->order_id,
                    $item->order_date,
                    $logistics_customer_name,
                    $screenname,
                    $item->value_price,
                ];
            }
            $row2 = $row + 4;
            $row3 = $row + count($list_b) + 3;
            array_push($data, [null, null, null, 'Subtotal', "=SUM(E{$row2}:E{$row3})"]);
        }

        array_push($data, array_pad([], 5, null));
        array_push($data, [null, null, 'Sales volume(Commodity price)', 'Charge proportion', 'Marketplace fee']);
        $list2 = $this->orm->table('tb_seller_bill_platform_summary')
            ->select('ladder_start','summary_title','ladder_end','rate','cost')
            ->where(['bill_id' => $bill_id])
            ->get();
        $total = 0;
        foreach ($list2 as $key => $item) {
            $total += $item->cost;
            array_push($data, [null, null, $item->summary_title, $item->rate, $item->cost]);
        }
        array_push($data, [null, null, null, 'Subtotal', $total]);
        $this->downExcel($data, $file_name.'.xls');
    }

    // 仓租费下载
    public function downloadStorage()
    {
        $billId = $this->request->get('bill_id', '');
        $fileName = $this->request->get('file_name', '');

        if (! $billId || ! $fileName) {
            $this->redirect(['error/not_found'])->send();
        }

        $this->downloadStorageFile($billId, $fileName);
    }

    /**
     * 仓租费文件生成下载
     *
     * @param int $billId 结算单ID
     * @param string $fileName 文件名
     */
    private function downloadStorageFile($billId, $fileName)
    {
        $list = SellerBillStorage::where('bill_id', $billId)->get();

        $logistics_customer_name = $this->customer->getLogisticsCustomerName();
        $data = [];
        foreach ($list as $key => $item) {
            $data[$key][] = $logistics_customer_name;
            $data[$key][] = $item->screenname;
            $data[$key][] = $item->batch_number;
            $data[$key][] = $item->receive_date->toDateTimeString();
            $data[$key][] = $item->sku;
            $data[$key][] = $item->length;
            $data[$key][] = $item->width;
            $data[$key][] = $item->height;
            $data[$key][] = $item->volume;
            $data[$key][] = $item->onhand_days;
            $data[$key][] = $item->storage_type;
            $data[$key][] = $item->cost_per_day.' ';
            $data[$key][] = $item->storage_time->toDateTimeString();
            $data[$key][] = $item->onhand_qty;
            $data[$key][] = $item->volume_total;
            $data[$key][] = $item->storage_fee;
        }
        $header = ['Seller code', 'Screenname', 'Batch number', 'Warehouse arrival date', 'ItemCode', 'Length(inch)', 'Width(inch)', 'Height(inch)', 'Volume(ft³)', 'Current days in stock', 'Warehouse rent type', 'Warehouse rental fee/ft³ per day', 'Warehouse rent due date', 'Current stock count', 'Total volume', 'New Warehouse rental fee subtotal'];
        array_unshift($data, $header);
        $row = array_pad([], 14, null);
        $row[14] = 'Subtotal';
        $row[15] = '=SUM(P2:P' . ($list->count() + 1) . ')';
        array_push($data, $row);
        $this->downExcel($data, $fileName.'.xls');
    }

    // Onsite Seller 下载仓租费
    public function downloadOnsiteSellerStorageFile($billId, $fileName)
    {
        $list = $this->settlementRepo->getOnsiteSellerStorageList($billId);
        $header = ['Create Time', 'Buyer Name(Number)', 'Charge Order ID', 'Related Order ID', 'Item Code', 'Item Volume(m³)', 'Current Day In Stock', 'Storage Fee Payable', 'Paid Storage Fee', 'Payment Time'];
        $data = [];
        if ($list->isNotEmpty()) {
            foreach ($list as $item) {
                $data[] = [
                    $item->fee_create_time,
                    $item->nickname . "({$item->user_number})",
                    $item->order_no,
                    $item->fee_order_type == SellerBillBuyerStorageFeeOrderType::SALES_ORDER ? "Sales Order({$item->sales_order_no})" : "RMA({$item->rma_no})",
                    $item->item_code,
                    $item->volume,
                    $item->days,
                    $item->fee_total,
                    $item->fee_paid,
                    $item->payment_time
                ];
            }
        }
        array_unshift($data, $header);
        $row = array_pad([], 7, null);
        $row[14] = 'Subtotal';
        $row[15] = '=SUM(I2:I' . ($list->count() + 1) . ')';
        array_push($data, $row);
        $this->downExcel($data, $fileName.'.xls');
    }

    // 欠款利息金融费下载
    public function downloadInterest(){
        $billId = $this->request->get('bill_id', '');
        $fileName = $this->request->get('file_name', '');

        if (! $billId || ! $fileName) {
            $this->redirect(['error/not_found'])->send();
        }

        $this->downloadInterestFile($billId, $fileName);
    }

    /**
     * 欠款利息金融费文件生成下载
     *
     * @param int $billId 结算单ID
     * @param string $fileName 文件名
     */
    private function downloadInterestFile($billId, $fileName)
    {
        $list = SellerBillInterestDetail::where('bill_id', $billId)->get();
        if ($list->isEmpty()) {
            $this->redirect(['error/not_found'])->send();
        }
        $bill = SellerBill::where('id', $billId)->first();
        $logistics_customer_name = $this->customer->getLogisticsCustomerName();
        $screenname = CustomerPartnerToCustomer::where('customer_id', $this->customer->getId())->value('screenname');
        foreach ($list as $key=>$item){
            $data[$key][]=abs($item->arrears_principal);
            $data[$key][]=$item->create_time->toDateTimeString();
            $data[$key][]=$item->arrears_days;
            $data[$key][]=$logistics_customer_name;
            $data[$key][]=$screenname;
            $data[$key][]=abs($item->arrears_interest);
        }
        $row = array_pad([], 4, null);
        $row[4] = 'Subtotal';
        $row[5] = round(array_sum(array_column($data, 5)), 2);
        $header = ['Principal', 'Time', 'Days', 'SellerCode', 'SellerName','Interest'];
        array_unshift($data, $header);
        $date = [$bill->start_date->toDateString().'-'.$bill->end_date->toDateString(), null, null, null, null];
        array_unshift($data, $date);

        array_push($data, $row);
        array_push($data, array_pad([], 6, null));
        array_push($data, [null, null, null, null, 'Annual interest rate', 'supply chain overhead fee']);
        array_push($data, [null, null, null, null, '12%', $row[5]]);
        array_push($data, [null, null, null, null, 'Subtotal', $row[5]]);
        $this->downExcel($data, $fileName.'.xls');
    }

    // 获取冻结明细对应的解冻列表
    public function getFrozenList()
    {
        $billDetailId = $this->request->get('detail_id', '');

        $rows = [];
        $total = 0;
        // 存在对应账单明细，并且状态是冻结中的
        if ($billDetailId) {
            $paginator = new Paginator(['defaultPageSize' => 20]);
            $rows = $this->settlementRepo->getFrozenList($this->sellerId, $billDetailId, $paginator);
            $total = SellerBillFrozenRelease::where('frozen_detail_id', $billDetailId)->where('seller_id', $this->sellerId)->count();
        }

        return $this->json(compact('total', 'rows'));
    }

    public function downExcel($data, $file_name)
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->setActiveSheetIndex(0);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getStyle ('O')->getNumberFormat()->setFormatCode("0.00");
        $sheet->setTitle('Sheet1')->fromArray($data, null, 'A1');
        ob_end_clean();//解决乱码
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $file_name . '"');
        header('Cache-Control: max-age=0');
        $writer = IOFactory::createWriter($spreadsheet, 'Xls');
        $writer->save('php://output');
    }

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
}
