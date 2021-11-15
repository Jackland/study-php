<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Enums\Common\YesNoEnum;
use App\Models\SellerBill\SellerBillType;
use App\Repositories\SellerBill\SettlementRepository;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\SellerBill\SettlementStatusAndSettleType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Enums\SellerBill\SellerBillSettlementStatus;
use App\Enums\SellerBill\SellerBillSettleType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ControllerAccountSellerBillBillList extends AuthSellerController
{
    protected $settlementRepo;

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

    // 结算列表
    public function index()
    {
        $this->document->setTitle(__('结算列表', [], 'catalog/document'));
        $data = $this->framework();

        $data['settlement_status'] = SettlementStatusAndSettleType::getViewItems();
        $data['settlement_list'] = $this->settlementRepo->getAllSettlementCycle($this->customer->getId());
        $data['is_onsite_seller'] = $this->customer->isGigaOnsiteSeller(); // 是否Onsite seller

        return $this->render('account/seller_bill/list', $data);
    }

    // 获取结算周期
    public function getSettlementCycle()
    {
        $list = $this->settlementRepo->getAllSettlementCycle($this->customer->getId());

        return $this->jsonSuccess($list);
    }

    // 获取结算列表
    public function getSettlementList()
    {
        $cycleId = $this->request->post('bill_id', '');
        $cycleNo = $this->request->post('bill_no', '');
        $cycleStatus = $this->request->post('status', '');
        $page = $this->request->post('page', 1);
        $pageSize = $this->request->post('page_size', 20);

        $list = $this->settlementRepo->getSettlementList($this->customer->getId(), $cycleId, $cycleNo, $cycleStatus, $this->customer->isGigaOnsiteSeller(), $page, $pageSize);
        $data['rows'] = $this->dealSettlementData($list);
        $data['total'] = $this->settlementRepo->getSettlementCount($this->customer->getId(), $cycleId, $cycleNo, $cycleStatus);

        return $this->json($data);
    }

    // 获取结算费用趋势
    public function getSettlementTrend()
    {
        $type = $this->request->post('type', 1); // 获取趋势周期 1:3个月 2:6个月
        $list = [];
        if ($type == 1) {
            $startDate = Carbon::now()->subMonth(2)->format('Y-m-01 00:00:00');
        } else {
            $startDate = Carbon::now()->subMonth(5)->format('Y-m-01 00:00:00');
        }
        list($billList, $totalList) = $this->settlementRepo->getBillTotalList($this->customer->getId(), $startDate, $this->customer->isGigaOnsiteSeller());
        if (! $billList) {
            return $this->jsonSuccess($list);
        }
        $typeList = SellerBillType::where('status', YesNoEnum::YES)->get()->toArray();
        $typeArr = array_combine(array_column($typeList, 'type_id'), $typeList);
        $billArr = array_combine(array_column($billList, 'id'), $billList);

        // 子项金额叠加
        foreach ($totalList as $value) {
            if (! isset($list[$value['header_id']]['list'][$typeArr[$value['type_id']]['parent_type_id']])) {
                $list[$value['header_id']]['list'][$typeArr[$value['type_id']]['parent_type_id']] = $typeArr[$typeArr[$value['type_id']]['parent_type_id']];
                $list[$value['header_id']]['list'][$typeArr[$value['type_id']]['parent_type_id']]['amount_total'] = 0;
            }
            $list[$value['header_id']]['list'][$typeArr[$value['type_id']]['parent_type_id']]['amount_total'] += $value['value'];
        }

        // 格式化数据输出
        foreach ($list as $key => &$item) {
            $item['date_format'] = $this->settlementRepo->formatSettlementDate($billArr[$key]['start_date'], $billArr[$key]['end_date']);
            $item['date_sub_format'] = Carbon::createFromFormat('Y-m-d H:i:s', $billArr[$key]['start_date'])->toDateString() . ' - ' .
                Carbon::createFromFormat('Y-m-d H:i:s', $billArr[$key]['end_date'])->toDateString();
            $item['ending_balance'] = $billArr[$key]['total'];
            foreach ($item['list'] as $kk => &$value) {
                $value['item_name'] = __($value['code'], [], 'controller/bill');
                $value['amount_total_format'] = $this->currency->formatCurrencyPrice($value['amount_total'], $this->session->get('currency'));
            }
            $sort = array_column($item['list'], 'sort'); // 内层费用项排序
            array_multisort($sort, SORT_ASC, $item['list']);
            $item['ending_balance_format'] = $this->currency->formatCurrencyPrice($item['ending_balance'], $this->session->get('currency'));
            $item['ending_balance_name'] = __choice('settlement_end_balance', $billArr[$key]['program_code'], [], 'controller/bill');
            $item['status_format'] = SettlementStatusAndSettleType::transitionStatusAndType($billArr[$key]['settlement_status'], $billArr[$key]['settle_type']);
            $item['frozen_total_format'] = $this->currency->formatCurrencyPrice($billArr[$key]['frozen_total'], $this->session->get('currency'));
            $item['last_frozen_format'] = $this->currency->formatCurrencyPrice($billArr[$key]['last_frozen'] ?? 0, $this->session->get('currency'));
        }
        $dateSubFormatSort = array_column($list, 'date_sub_format');  // 外层周期排序
        array_multisort($dateSubFormatSort, SORT_ASC, $list);

        return $this->jsonSuccess(['list' => $list]);
    }

    // 下载
    public function download()
    {
        $cycleId = $this->request->get('bill_id', '');
        $cycleNo = $this->request->get('bill_no', '');
        $cycleStatus = $this->request->get('status', '');

        $list = $this->settlementRepo->getSettlementList($this->customer->getId(), $cycleId, $cycleNo, $cycleStatus, $this->customer->isGigaOnsiteSeller(),1, 5000);
        if ($list->isEmpty()) {
            $this->redirect($this->isNotSellerRedirect)->send();
        }
        $list = $this->dealSettlementData($list);

        $head = [
            __('结算单编号', [], 'controller/bill'),
            __('结算周期', [], 'controller/bill'),
            __('期末金额', [], 'controller/bill'),
            __('结算金额', [], 'controller/bill'),
            __('账单确认结算时间', [], 'controller/bill'),
            __('结算单状态', [], 'controller/bill')
        ];
        // Onsite Seller 需要展示对应的冻结金额
        if ($this->customer->isGigaOnsiteSeller()) {
            array_splice($head, 2, 0, __('本期订单冻结金额', [], 'controller/bill') . '(' . __('本期剩余冻结金额', [], 'controller/bill') . ')');
        }

        $downloadDataArr = [];
        foreach ($list as $item) {
            $temp = [
                $item->serial_number . "\t",
                $item->date_format,
                $item->total_format,
                $item->settlement_total_format,
                empty($item->confirm_date) ? '--' : $item->confirm_date,
                $item->status_format
            ];
            // Onsite Seller 需要展示对应的冻结金额
            if ($this->customer->isGigaOnsiteSeller()) {
                array_splice($temp, 2, 0, "{$item->frozen_total_format} ({$item->last_frozen_format})");
            }
            $downloadDataArr[] = $temp;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('结算列表', [], 'controller/bill'));

        array_unshift($downloadDataArr, $head);
        $sheet->fromArray($downloadDataArr);
        $writer = IOFactory::createWriter($spreadsheet, 'Xls');
        $downloadFileName = __('结算列表', [], 'controller/bill') . '_' . time() . '.xls';
        return $this->response->streamDownload(
            function () use ($writer) {
                $writer->save('php://output');
            }, $downloadFileName, ['Content-Type' => 'application/vnd.ms-excel']
        );
    }

    /**
     * 结算单数据处理
     *
     * @param Collection $list 结算单列表
     * @return Collection
     */
    private function dealSettlementData(Collection $list)
    {
        if ($list->isEmpty()) {
            return $list;
        }
        foreach ($list as $key => &$value) {
            if (in_array($value->settlement_status, SellerBillSettlementStatus::getAllGoingStatus())) {
                $settlementTotalFormat = __('N/A', [], 'controller/bill');
            } elseif ($value->settlement_status == SellerBillSettlementStatus::ALREADY_SETTLED && $value->settle_type == SellerBillSettleType::SWITCH_BALANCE) {
                // 如果本期结算单余额已结算&转期初余额 -> 则取当前结算单后面一期结算单的对应的输入的 台支付卖家应收款/供应商支付平台欠款金额+供应商支付的欠款利息 金额
                if ($key == 0) {
                    $lastSettlementInfo = $this->settlementRepo->getSettlementInfo($this->customer->getId(), $value->start_date->toDateTimeString());
                } else {
                    $lastSettlementInfo = $list[$key - 1];
                }
                $settlementTotalFormat = $this->currency->formatCurrencyPrice(empty($lastSettlementInfo->check_total) ? 0 : $lastSettlementInfo->check_total, $this->session->get('currency'));
            } else {
                $settlementTotalFormat = $this->currency->formatCurrencyPrice($value->actual_settlement, $this->session->get('currency'));
            }
            $value->setAttribute('settlement_total_format', $settlementTotalFormat);
            $value->setAttribute('total_format', $this->currency->formatCurrencyPrice($value->total, $this->session->get('currency')));
            $value->setAttribute('status_format', SettlementStatusAndSettleType::transitionStatusAndType($value->settlement_status, $value->settle_type));
            $value->setAttribute('date_format', $this->settlementRepo->formatSettlementDate($value->start_date, $value->end_date));
            $value->setAttribute('frozen_total_format', $this->currency->formatCurrencyPrice($value->frozen_total, $this->session->get('currency')));
            if ($this->customer->isGigaOnsiteSeller()) {
                $value->setAttribute('last_frozen_format', $this->currency->formatCurrencyPrice($value->frozen[0]->last_frozen ?? 0, $this->session->get('currency')));
            }
        }

        return $list;
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
}
