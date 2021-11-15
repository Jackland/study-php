<?php

/**
 * seller 账单总单
 *
 * @property ModelAccountSellerBillBillmodel $model_account_seller_bill_billmodel
 */
use App\Catalog\Controllers\AuthSellerController;
use App\Enums\Common\YesNoEnum;
use App\Enums\SellerBill\SellerBillProgramCode;
use App\Models\SellerBill\SellerBillFile;
use App\Models\SellerBill\SellerBillTotal;
use App\Models\SellerBill\SellerBillType;
use App\Repositories\SellerBill\SettlementRepository;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\SellerBill\SellerAccountInfoAccountType;
use App\Helper\CountryHelper;
use App\Enums\SellerBill\SellerBillSettlementStatus;
use App\Enums\SellerBill\SellerBillTypeCode;
use App\Repositories\SellerBill\DealSettlement\DealSettlement;

/**
 * @property ModelAccountSellerBillBillmodel $model_account_seller_bill_billmodel
 */
class ControllerAccountSellerBillBill extends AuthSellerController
{
    protected $settlementRepo;
    private $data = array();

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

        $this->settlementRepo = app(SettlementRepository::class);
    }

    public function index()
    {
        $this->document->setTitle(__('结算总单', [], 'catalog/document'));
        $data = $this->framework();
        $data['bill_id'] = $this->request->get('bill_id', '');

        return $this->render('account/seller_bill/total', $data);
    }

    // 获取结算单信息
    public function getTotalInfo()
    {
        $billId = $this->request->post('bill_id', '');
        if (! $billId) {
            return $this->jsonFailed(__('无效请求', [], 'controller/bill'));
        }

        $billInfo = $this->settlementRepo->getBillInfo($billId, $this->customer->getId(), $this->customer->isGigaOnsiteSeller());
        if (! $billInfo) {
            return $this->jsonFailed(__('无效请求', [], 'controller/bill'));
        }

        $dealSettlement = app(DealSettlement::class);
        // 格式化展示数据
        empty($billInfo->bank_account) || empty($billInfo->account_type) ? $billInfo->setAttribute('bank_account', 'N/A') : $billInfo->setAttribute('bank_account', $dealSettlement->formatAccountInfo($billInfo->account_type, $billInfo->bank_account));
        empty($billInfo->company) && $billInfo->setAttribute('company', 'N/A');
        $billInfo->setAttribute('date_format', $this->settlementRepo->formatSettlementDate($billInfo->start_date, $billInfo->end_date));
        empty($billInfo->account_type) ? $billInfo->setAttribute('account_type', 'N/A') : $billInfo->account_type = SellerAccountInfoAccountType::getViewItems()[$billInfo->account_type];
        $billInfo->setAttribute('progress_bar', [
            [
                'date_format' => $billInfo->start_date->format('Y-m-d'),
                'status_name' => __('正在进行', [], 'controller/bill'),
                'is_select' => true
            ],
            [
                'date_format' => $billInfo->end_date->addSecond()->format('Y-m-d'),
                'status_name' => __('结算中', [], 'controller/bill'),
                'is_select' => in_array($billInfo->settlement_status, [SellerBillSettlementStatus::IN_THE_SETTLEMENT, SellerBillSettlementStatus::ALREADY_SETTLED]) ? true : false
            ],
            [
                'date_format' => empty($billInfo->confirm_date) ? $billInfo->end_date->addDays(7)->format('Y-m-d') : $billInfo->confirm_date->format('Y-m-d'),
                'status_name' => __('已结算', [], 'controller/bill'),
                'is_select' => $billInfo->settlement_status == SellerBillSettlementStatus::ALREADY_SETTLED ? true : false
            ]
        ]);
        $billInfo->setAttribute('country_name', CountryHelper::getCountryNameById($this->customer->getCountryId()));
        $billInfo->setAttribute('currency_unit', CountryHelper::getCurrencyUnitNameById($this->customer->getCountryId()));
        $endingBalance = $this->getFormatEndBanlance($billInfo);
        $totalArr = $this->getFormatTotalList($billId);

        // 获取后台上传水单文件
        $files = SellerBillFile::where('seller_bill_id', $billId)
            ->select('id', 'file_name', 'file_path')
            ->get();
        $billInfo->setAttribute('files', $files);
        $data = [
            'bill_info' => $billInfo,
            'ending_balance' => $endingBalance,
            'total_list' => $totalArr
        ];
        // Onsite Seller 需要展示对应的冻结金额
        if ($this->customer->isGigaOnsiteSeller()) {
            $data['frozen_total_format'] = $this->currency->formatCurrencyPrice($billInfo->frozen_total, $this->session->get('currency'));
            $data['last_frozen_format'] = $this->currency->formatCurrencyPrice($billInfo->last_frozen, $this->session->get('currency'));
        }

        return $this->jsonSuccess($data);
    }

    // 下载PDF
    public function downloadPDF()
    {
        $billId = $this->request->get('bill_id', '');
        $showZero = $this->request->get('showZero', false);
        if (! $billId) {
            $this->redirect($this->isNotSellerRedirect)->send();
        }
        $billInfo = $this->settlementRepo->getBillInfo($billId, $this->customer->getId(), $this->customer->isGigaOnsiteSeller());
        if (! $billInfo) {
            $this->redirect($this->isNotSellerRedirect)->send();
        }

        // create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('oristand');
        $pdf->SetTitle(__('结算单', [], 'controller/bill'));
        $pdf->SetSubject('TCPDF Tutorial');
        $pdf->SetKeywords('TCPDF, PDF, PHP');

        $pdf->SetFont('stsongstdlight', 'B', 10);
        $pdf->AddPage();
        //设置分页
        $pdf->SetAutoPageBreak(TRUE, 25);
        // 设置默认等宽字体
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        // 设置行高
        $pdf->setCellHeightRatio(1.2);
        // 设置左、上、右的间距
        $pdf->SetMargins('8', '8', '8');
        // 设置调整因子以将像素转换为用户单位
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        $data['logistics_customer_name'] = $billInfo->logistics_customer_name ? $billInfo->logistics_customer_name : $billInfo->firstname . $billInfo->lastname;
        $settleFormat = $billInfo->start_date->format('Y-m-d H:i:s T') . '-' . $billInfo->end_date->format('Y-m-d H:i:s T');
        $data['date_format'] = $settleFormat . '(' . SellerBillSettlementStatus::getDescription($billInfo->settlement_status) . ')';
        $data['ending_balance'] = $this->getFormatEndBanlance($billInfo);
        $data['total_list'] = $this->getFormatTotalList($billId);
        // Onsite Seller 需要展示对应的冻结金额
        if ($this->customer->isGigaOnsiteSeller() && ! in_array(strtoupper($billInfo->program_code), [SellerBillProgramCode::V1, SellerBillProgramCode::V2])) {
            $data['frozen_total_format'] = $this->currency->formatCurrencyPrice($billInfo->frozen_total, $this->session->get('currency'));
            $data['last_frozen_format'] = $this->currency->formatCurrencyPrice($billInfo->last_frozen, $this->session->get('currency'));
        }

        // 需要过滤0的项目值
        if ($showZero) {
            foreach ($data['total_list'] as &$item) {
                foreach ($item['item_list'] as $kk => $ii) {
                    if ($ii['amount_total'] == 0) {
                        unset($item['item_list'][$kk]);
                    }
                }
            }
        }

        // output the HTML content
        $html = $this->load->view('account/seller_bill/download_file', $data);
        $pdf->writeHTML($html, true, true, true, 0, '');

        //Close and output PDF document
        $pdf->Output($data['logistics_customer_name'] . '_' . $billInfo->start_date->toDateString() . '-' . $billInfo->end_date->toDateString() . '.pdf', 'D');
    }

    /**
     * 获取格式化结算单总额
     *
     * @param $billInfo 结算单信息
     * @return array
     */
    private function getFormatEndBanlance($billInfo)
    {
        return [
            'item_name' => __choice('settlement_end_balance', $billInfo->program_code, [], 'controller/bill'),
            'item_sub_name' => __choice('settlement_end_balance_sub', $billInfo->program_code, [], 'controller/bill'),
            'amount_total' => $billInfo->total,
            'amount_total_format' => $this->currency->formatCurrencyPrice($billInfo->total, $this->session->get('currency'))
        ];
    }

    /**
     * 获取拼装结算单类目
     *
     * @param int $billId 结算单ID
     * @return array
     */
    private function getFormatTotalList($billId)
    {
        $totalArr = [];
        if (! $billId) {
            return $totalArr;
        }
        $totalList = SellerBillTotal::where('header_id', $billId)->get();
        $typeList = SellerBillType::where('status', YesNoEnum::YES)->get()->toArray();
        $typeArr = array_combine(array_column($typeList, 'type_id'), $typeList);
        $codeArr = array_combine(array_column($typeList, 'code'), $typeList);

        foreach ($totalList as $item) {
            if (! isset($totalArr[$typeArr[$item->type_id]['parent_type_id']])) {
                $totalArr[$typeArr[$item->type_id]['parent_type_id']] = [
                    'amount_total' => 0,
                    'type_id' => $typeArr[$item->type_id]['parent_type_id'],
                    'item_name' => __($typeArr[$typeArr[$item->type_id]['parent_type_id']]['code'], [], 'controller/bill'),
                    'sort' => $typeArr[$typeArr[$item->type_id]['parent_type_id']]['sort'],
                    'type_name' => __choice('subtotal_se', $item->program_code, [], 'controller/bill'),
                    'is_jump' => in_array($typeArr[$item->type_id]['parent_type_id'], array_column(SellerBillTypeCode::getBillTypeByVersion($item->program_code), 'value')) ? 1 : 0,
                    'item_list' => []
                ];
            }
            $totalArr[$typeArr[$item->type_id]['parent_type_id']]['amount_total'] = bcadd($totalArr[$typeArr[$item->type_id]['parent_type_id']]['amount_total'], $item->value, 2);

            // 如果是 海运费||物流费  分别判断是否存在对应的 海运费返点||运费返点
            $itemName = __($item->code, [], 'controller/bill');
            if (in_array($item->code, [SellerBillTypeCode::V3_LOGISTIC, SellerBillTypeCode::V3_SEA_FREIGHT])) {
                $detailInfo = $this->settlementRepo->getDetailInfoByCode($this->customer->getId(), $item->header_id, [$codeArr[SellerBillTypeCode::V3_SEA_FREIGHT_DETAIL_REBATE]['type_id'], $codeArr[SellerBillTypeCode::V3_FREIGHT_REBATE]['type_id']]);
                if ($detailInfo->isNotEmpty()) {
                    foreach ($detailInfo as $detail) {
                        $formatMoney = $this->currency->formatCurrencyPrice($detail->total, $this->session->get('currency'));
                        if ($typeArr[$typeArr[$detail->type]['parent_type_id']]['code'] == $item->code) {
                            $itemName .= __('（含返点:num）', ['num' => $formatMoney], 'controller/bill');
                        }
                    }
                }
            }

            $totalArr[$typeArr[$item->type_id]['parent_type_id']]['item_list'][] = [
                'item_name' => $itemName,
                'amount_total' => $item->value,
                'amount_total_format' => $this->currency->formatCurrencyPrice($item->value, $this->session->get('currency')),
                'sort' => $typeArr[$item->type_id]['sort']
            ];
        }
        foreach ($totalArr as &$item) {
            $item['amount_total_format'] = $this->currency->formatCurrencyPrice($item['amount_total'], $this->session->get('currency'));

            // 二级类目排序
            $sort = array_column($item['item_list'], 'sort');
            array_multisort($sort, SORT_ASC, $item['item_list']);
        }

        // 一级类目排序
        $totalArr = array_values($totalArr);
        $sort = array_column($totalArr, 'sort');
        array_multisort($sort, SORT_ASC, $totalArr);

        return $totalArr;
    }

    private function framework()
    {
        $breadcrumbs = $this->getBreadcrumbs([
            [
                'text' => __('账单管理', [], 'catalog/document'),
                'href' => 'javascript:void(0)'
            ],
            [
                'text' => __('结算总单', [], 'catalog/document'),
                'href' => 'javascript:void(0)'
            ]
        ]);
        return [
            'breadcrumbs' => $breadcrumbs,
            'separate_column_left' => $this->load->controller('account/customerpartner/column_left'),
            'footer' => $this->load->controller('account/customerpartner/footer'),
            'header' => $this->load->controller('account/customerpartner/header')
        ];
    }

    public function applySettlement()
    {
        $id = $this->request->post['id'];
        $settlement_status = $this->orm->table('tb_seller_bill')
            ->where(['id' => $id])
            ->value('settlement_status');
        if ($settlement_status == 2) {
            $this->response->failed('The Application for Settlement document has been processed, it cannot be submitted again.');
        }
        $res = $this->orm->table('tb_seller_bill')
            ->where(['id' => $id])
            ->update(['settle_apply' => 1]);
        if($res){
            $this->response->success([],'Application for settlement submitted successfully.');
        }
        $this->response->failed('The settlement document has been confirmed, so the application for settlement cannot be submitted.');
    }

    /**
     * 总账单页面
     */
    public function bill_total($bill_id = 0)
    {
        //获取language文件
//        $this->language->data=array();
        $language=$this->language->load('account/seller_bill/bill');
        $this->load->model('account/seller_bill/billmodel');
        //bill_id 必须未数字
        if (!is_numeric($bill_id)) {
            return 'error';
        }
        //计算默认时间段
        if (!$bill_id) {
            $bill_id_res = $this->model_account_seller_bill_billmodel->get_near_bill_id($this->customer->getId());
            if (!$bill_id_res) {  //没有账单信息
                return 'error';
            }
            $bill_id = $bill_id_res[0]['id'];
        }
        //获取账单列表
        $bill_list_res = $this->model_account_seller_bill_billmodel->get_seller_bill_list($this->customer->getId());
        if (!$bill_list_res) { //没有账单信息
            return 'error';
        }
        //获取账单数据
        $bill_info_res = $this->model_account_seller_bill_billmodel->get_seller_bill($this->customer->getId(), $bill_id);
        //获取账单total数据
        $bill_detail_info_res = $this->model_account_seller_bill_billmodel->get_total_seller_bill($bill_id);
        if (!$bill_info_res || !$bill_detail_info_res) {  //没有账单信息或账单信息不全
            return 'error';
        }
        //处理数据
        // 时间列表
        //结算状态 0:正在进行中 1:结算中 2:已结算
        array_walk($bill_list_res, function (&$v, $k) use ($language) {
            $v['start_date'] .= ' ' . $this->show_date($v['start_date']);
            $v['end_date'] .= ' ' . $this->show_date($v['end_date']);
            $v['status_info']= $language['settlement_processed_info'][$v['settlement_status']];
        });
        //总体
        $bill_info_res = $bill_info_res[0];
        $bill_info_res['start_date'] .= ' ' . $this->show_date($bill_info_res['start_date']);
        $bill_info_res['end_date'] .= ' ' . $this->show_date($bill_info_res['end_date']);
        $bill_info_res['settlement_date'] .= ' ' . $this->show_date($bill_info_res['settlement_date']);
        $bill_info_res['reserve_val'] = $bill_info_res['reserve'];
        $bill_info_res['reserve'] = $this->currency->formatCurrencyPrice($bill_info_res['reserve'], $this->session->data['currency']);
        $bill_info_res['previous_reserve_val'] = $bill_info_res['previous_reserve'];
        $bill_info_res['previous_reserve'] = $this->currency->formatCurrencyPrice($bill_info_res['previous_reserve'], $this->session->data['currency']);
        $bill_info_res['total_val'] = $bill_info_res['total'];
        $bill_info_res['total'] = $this->currency->formatCurrencyPrice($bill_info_res['total'], $this->session->data['currency']);
        $bill_info_res['settlement_val'] = $bill_info_res['settlement'];
        $bill_info_res['settlement'] = $this->currency->formatCurrencyPrice($bill_info_res['settlement'], $this->session->data['currency']);
        $bill_info_res['reserve_url']='index.php?route=account/seller_bill/bill_detail/detail_info&bill_id='.$bill_id;
        $bill_info_res['pdf_url']='index.php?route=account/seller_bill/bill/bill_total_pdf&bill_id='.$bill_id;

        // 账单表格上方的计算时间和金额
        if ($bill_info_res['settlement_status'] == 0) {   //正在进行
            if ($bill_info_res['total_val'] < 0) {
                $bill_info_res['start_bill_overview'] = $language['start_bill_overview'][0][1];
            }
        } elseif ($bill_info_res['settlement_status'] == 1) {  //结算中
            if ($bill_info_res['total_val'] < 0) {
                $bill_info_res['start_bill_overview'] = $language['start_bill_overview'][1][1];
            }
        }
        $bill_info_res['start_bill_overview']=str_replace(' ','&nbsp;',$bill_info_res['start_bill_overview'] ?? '');
        //各项的详细
        foreach ($bill_detail_info_res as $type_k => &$type_v) {
            $url_parttern=array(
                'route'=>'account/seller_bill/bill_detail',
                'filter_settlement_cycle'=>$bill_id,
                'filter_settlement_item'=>$type_v['type_id']
            );
            $type_v['total'] = $this->currency->formatCurrencyPrice($type_v['total'], $this->session->data['currency']);
            $type_v['detail_url']='index.php?'.http_build_query($url_parttern);
            foreach ($type_v['child'] as $detail_k => &$detail_v) {
                $detail_v['value'] = $this->currency->formatCurrencyPrice($detail_v['value'], $this->session->data['currency']);
            }
        }

        $bill_data = array();
        $bill_data['list'] = $bill_list_res;     //周期列表
        $default_key = array_search($bill_id, array_column($bill_list_res, 'id'));
        $bill_data['show_default'] = $bill_list_res[$default_key];     //默认显示
        $bill_data['show_default_id'] = $bill_list_res[$default_key]['id'];
        // 前一笔   数据倒序   +1
        $show_prev = isset($bill_list_res[$default_key + 1]) ? $bill_list_res[$default_key + 1] : '';   //前一个--必须未索引数组  --已经倒序
        $show_next = isset($bill_list_res[$default_key -1]) ? $bill_list_res[$default_key - 1] : '';     //后一个
        $bill_data['show_prev_url'] = $show_prev ? 'index.php?route=account/seller_bill/bill&bill_id=' . $show_prev['id'] : '';
        $bill_data['show_next_url'] = $show_next ? 'index.php?route=account/seller_bill/bill&bill_id=' . $show_next['id'] : '';

        $bill_data['info'] = $bill_info_res;    //总体（时间，期初等）
        $bill_data['detail'] = $bill_detail_info_res;    //各项的详细
        $bill_data['bill_file'] = $this->model_account_seller_bill_billmodel->getBillFile($bill_id)->toArray();    //各项的详细

        $bill_data['language']=$language;
        // 帮助中心url
        $bill_data['help_center_url'] = $this->url->link('information/information', ['information_id' => 119]);
        return $bill_data;
    }

    public function bill_total_pdf()
    {
        $bill_id = get_value_or_default($this->request->get,'bill_id', 0);
        $logistics_customer_name = $this->orm->table('oc_customer')
            ->where('customer_id', $this->customer->getId())
            ->value('logistics_customer_name');
        //数据校验

        //生成pdf
//        require_once("tcpdf.php");

        // create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('oristand');
        $pdf->SetTitle('Invoices');
        $pdf->SetSubject('TCPDF Tutorial');
        $pdf->SetKeywords('TCPDF, PDF, PHP');

        $pdf->SetFont('stsongstdlight', 'B', 10);
        $pdf->AddPage();
        //设置分页
        $pdf->SetAutoPageBreak(TRUE, 25);
        // 设置默认等宽字体
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        // 设置行高
        $pdf->setCellHeightRatio(1.2);
        // 设置左、上、右的间距
        $pdf->SetMargins('8', '8', '8');
        // 设置调整因子以将像素转换为用户单位
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $data=$this->bill_total($bill_id);
        $data['logistics_customer_name']=$logistics_customer_name;

        // output the HTML content
        $html = $this->load->view('account/seller_bill/bill_pdf', $data);
        $pdf->writeHTML($html, true, true, true, 0, '');

        //Close and output PDF document
        $pdf->Output($logistics_customer_name . 'invoice' . time() . '.pdf', 'D');

    }

    /**
     * 修改显示时间，判断时令
     * @param $date
     */
   /* public function show_date($date)
    {
        $y = date('Y', strtotime($date));
        //当年的夏时令
        //起点：当年的3月1好 +1周 + （7-（3月1号周几）-1）+2h   =夏时令的起点
        $time_3_1 = strtotime("$y-3-1 00:00:00");
        $add_day = (7 - date('N', $time_3_1));
        $start_summer = strtotime("$y-3-1 00:00:00 +1 week $add_day days +2hours");
        //夏时令的截止点
        //截止点：当年11月1号 +（7-（11月1号星期几）-1）+ 2h -1s  =夏时令的截止点
        $time_11_1 = strtotime("$y-11-1 00:00:00");
        $add_day = (7 - date('N', $time_11_1));
        $end_summer = strtotime("$y-11-1 00:00:00 $add_day days +2hours -1 seconds");
        //计算时令
        $input_time = strtotime($date);
        if ($end_summer > $input_time && $input_time >= $start_summer) {  //处于夏时令
            return 'PDT';
        } else {
            return 'PST';
        }
    }*/


    /**
     * 下载文件
     */
    public function downloadFile()
    {
        // 判断用户是否登录
        $file = $this->orm->table('tb_sys_seller_bill_file')
            ->where(['id' => $this->request->get['file_id']])
            ->first();
        if(!$file){
            exit('Error: Could not find file !');
        }
        $fileDir = $this->orm
                ->table('tb_sys_setup')
                ->where('parameter_key', 'SELLER_BILL_PATH')
                ->value('parameter_value') ?? '';
        $filename = $file->file_name;
        $fillpath = $fileDir . $file->file_path;
        if (!headers_sent()) {
            if (file_exists($fillpath)) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . filesize($fillpath));
                if (ob_get_level()) {
                    ob_end_clean();
                }
                readfile($fillpath, 'rb');
                exit();
            } else {
                exit('Error: Could not find file ' . $fillpath . '!');
            }
        } else {
            exit('Error: Headers already sent out!');
        }
    }
}

