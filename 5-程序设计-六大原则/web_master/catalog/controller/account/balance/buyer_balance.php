<?php

use App\Catalog\Controllers\AuthController;
use App\Helper\CountryHelper;
use App\Helper\CurrencyHelper;
use App\Models\FeeOrder\FeeOrder;
use App\Enums\Charge\ChargeType;
use App\Models\Safeguard\SafeguardClaim;

/**
 * @property ModelAccountCustomerpartner $model_account_customerpartner
 * @property ModelAccountBalanceVirtualPayRecord $model_account_balance_virtual_pay_record
 * @property ModelAccountRmaManagement $model_account_rma_management
 * @property ModelAccountBalanceRecharge $model_account_balance_recharge
 * @property ModelFuturesAgreement $model_futures_agreement
 */

class ControllerAccountBalanceBuyerBalance extends AuthController
{
    private $precision;
    private $customer_id;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->customer_id = $this->customer->getId();
        $this->precision = $this->customer->getCountryId() == JAPAN_COUNTRY_ID ? 0 : 2;
        if (!$this->customer->isLogged()) {
            session()->set('redirect', $this->url->link('account/balance/buyer_balance', '', true));
            $this->response->redirect($this->url->link('account/login', '', true));
        }
    }

    //从原\catalog\controller\customerpartner\creditline_amendment_record.php 迁过来的
    public function balanceMenu()
    {
        $this->load->model('account/customerpartner');

        $filter_data = array(
            'customerId'=>$this->customer->getId()
        );

        // Gift Voucher
        $recharge = round($this->model_account_customerpartner->getTotalAmountOfRecharge($filter_data),2);
        if(strripos($recharge,".")){
            $data['recharge1']=substr($recharge,0,strripos($recharge,"."));
            $data['recharge2']=trim(mb_substr($recharge,strripos($recharge,".")));
        }else{
            $data['recharge1']=$recharge;
            if (empty($this->customer->getCountryId()) || $this->customer->getCountryId() != JAPAN_COUNTRY_ID) {
                $data['recharge2']=".00";
            }
        }

        $amount = round($this->model_account_customerpartner->getTotalConsumptionAmount($filter_data),2);
        if(strripos($amount,".")){
            $data['amount1']=substr($amount,0,strripos($amount,"."));
            $data['amount2']=trim(mb_substr($amount,strripos($amount,".")));
        }else{
            $data['amount1'] = $amount;
            if (empty($this->customer->getCountryId()) || $this->customer->getCountryId() != JAPAN_COUNTRY_ID) {
                $data['amount2']= ".00";
            }
        }
        $symbol_left = $this->currency->getSymbolLeft($this->session->get('currency'));
        $symbol_right = $this->currency->getSymbolRight($this->session->get('currency'));
        if($symbol_left){
            $data['recharge3'] = $symbol_left;
            $data['amount3'] = $symbol_left;
        }
        if($symbol_right){
            $data['recharge3'] = $symbol_right;
            $data['amount3'] = $symbol_right;
        }

        $data['showDetail'] = $this->url->link('account/balance/buyer_balance', '', true);;
        $data['rechargeUrl'] = $this->url->link('account/balance/recharge', '', true);;

        $data['line_of_credit'] = $this->currency->formatCurrencyPrice($this->customer->getLineOfCredit(), $this->session->data['currency']);

        return $this->load->view('account/balance/balance_menu', $data);
    }

    public function index()
    {
        $tab = $this->request->get('tab', 0);
        $this->load->model('account/customerpartner');
        $this->load->language('account/balance');

        $this->document->setTitle($this->language->get('list_heading_title'));

        $data['breadcrumbs'] = $this->getBreadcrumbs(['home', [
            'text' => $this->language->get('text_account'),
            'href' => $this->url->link('account/account', '', true)
        ]]);

        //获取账户余额
        $data['balance'] = $this->currency->formatCurrencyPrice($this->customer->getLineOfCredit(), $this->session->get('currency'));
        $data['recharge_apply'] =  $this->url->link('account/balance/recharge', '', true);
        $data['innerAutoBuyAttr1'] = $this->customer->innerAutoBuyAttr1();//自动购买 采销异体
        $data['tab'] = $tab;
        $rechargeUrl = '';
        if (isset($this->request->get['rechargeUrl'])) {
            $rechargeUrl = $this->request->get['rechargeUrl'];
            $rechargeUrl = encrypt($rechargeUrl, 'D', 'RECHARGE_BACK_URL');
        }
        if ($rechargeUrl != '') {
            $data['bidRechargeUrl'] = str_replace('&amp;', '&', $rechargeUrl);
        } else {
            $data['bidRechargeUrl'] = null;
        }

        //充值流程说明页
        $data['recharge_instructions_url'] = $this->url->link('information/information',
            ['information_id' => $this->config->get('adding_account_help_id')]);
        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $this->response->setOutput($this->load->view('account/balance/balance_list', $data));
    }

    public function tab_account_balance()
    {

        $data = [];
        $this->load->model('account/customerpartner');
        $this->load->model('account/rma_management');
        $this->load->model('account/balance/recharge');
        $this->load->language('customerpartner/creditline_amendment_record');

        $filterType = (int)$this->request->get('filterType', 3);
        $timeSpace = (int)$this->request->get('timeSpace', 4);
        $timeFrom =$this->request->get('timeFrom', null);
        $timeTo = $this->request->get('timeTo', null);
        $page_num = (int)$this->request->get('page_num', 1);

        $data['page_num'] = $page_num;
        // 每页显示数目
        $page_limit = $this->request->get('page_limit', 20);
        $customerId = $this->customer->getId();
        $user_token = $this->session->get('user_token', '');
        $filter_data = array(
            'start'         => ($page_num - 1) * $page_limit,
            'limit'         => $page_limit,
            'filterType'    => $filterType,
            'timeSpace'     => $timeSpace,
            'timeFrom'      => $timeFrom,
            'timeTo'        => $timeTo,
            'customerId'    => $customerId
        );
        //获取总记录数
        $amendant_record_total = $this->model_account_customerpartner->getCreditlineAmendmentRecordCount($filter_data);
        //获取所有记录明细
        $amendant_records = $this->model_account_customerpartner->getCreditlineAmendmentRecordRow($filter_data);

        //获取总充值金额
        $data['revenue'] = $this->currency->formatCurrencyPrice($this->model_account_customerpartner->getTotalAmountOfRecharge($filter_data), $this->session->get('currency'));
        //获取总支出金额
        $data['payment'] = $this->currency->formatCurrencyPrice($this->model_account_customerpartner->getTotalConsumptionAmount($filter_data), $this->session->get('currency'));
        //获取账户余额
        $data['balance'] = $this->currency->formatCurrencyPrice($this->customer->getLineOfCredit(), $this->session->get('currency'));


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
        $data['page_limit'] = $page_limit;

        //分页
        $total_pages = ceil($amendant_record_total / $page_limit);
        $data['total_pages'] = $total_pages;
        $data['page_num'] = $page_num;
        $data['total'] = $amendant_record_total;
        $data['page_limit'] = $page_limit;
        $data['results'] = sprintf($this->language->get('text_pagination'), ($amendant_record_total) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($amendant_record_total - $page_limit)) ? $amendant_record_total : ((($page_num - 1) * $page_limit) + $page_limit), $amendant_record_total, $total_pages);

        $data['TimeFrom'] = $timeFrom;
        $data['TimeTo'] = $timeTo;
        $data['filterType'] = $filterType;
        $data['timeSpace'] = $timeSpace;
        $data['currency'] = session('currency');
        $rmaIds = array();
        $pCardAndWireIds = array();
        foreach ($amendant_records as $amendant_record) {
            if ($amendant_record['type_id'] == ChargeType::PAY_REFUND_CHARGE) {
                $rmaIds[] = $amendant_record['header_id'];
            }
            if ($amendant_record['type_id'] == ChargeType::PAY_WIRE_TRANSFER || $amendant_record['type_id'] == ChargeType::PAY_PAYONEER || $amendant_record['type_id'] == ChargeType::PAY_PINGPONG) {
                $pCardAndWireIds[] = $amendant_record['header_id'];
            }
        }
        // 根据RMAId查询RMA信息
        $rmaOrders = $this->model_account_rma_management->getRmaOrderByIdIn($rmaIds);
        if (isset($rmaOrders)) {
            foreach ($rmaOrders as $rmaOrder) {
                $rmaOrderMap[$rmaOrder->id] = $rmaOrder;
            }
        }
        //获取P-Card与Wire transfer的信息oc_recharge_apply
        $rechargeApplyOrders = $this->model_account_balance_recharge->getRechargeApplyInfo($pCardAndWireIds);
        if (isset($rechargeApplyOrders)) {
            $rechargeApplyMap = [];
            foreach ($rechargeApplyOrders as $rechargeApply) {
                $rechargeApplyMap[$rechargeApply['id']] = $rechargeApply;
            }
        }

        if(is_array($amendant_records)){
            $backUrl = $this->url->link('account/balance/recharge/tab_recharge', '', true);
            $backUrl = encrypt($backUrl, 'E', 'RECHARGE_BACK_URL');
            $backUrl = urlencode($backUrl);
            $decimalPlaceArr = CurrencyHelper::getCurrencyConfig();
            $decimalPlace = $decimalPlaceArr[$data['currency']]['decimal_place'];
            foreach ($amendant_records as $amendant_record) {
                $url = null;
                $msg = null;
                if ($amendant_record['type_id'] == ChargeType::PAY_LIMIT_CHARGE || $amendant_record['type_id'] == ChargeType::PAY_LIMIT_RECHARGE) {
                    $msg = $amendant_record['memo'];
                } else if ($amendant_record['type_id'] == ChargeType::PAY_CREDIT_CONSUMPTION) {
                    $msg = 'Order ID ' . $amendant_record['header_id'];
                    $url = $this->url->link('account/order/purchaseOrderInfo', '&order_id='. $amendant_record['header_id']);
                } else if ($amendant_record['type_id'] == ChargeType::PAY_REFUND_CHARGE) {
                    if (isset($rmaOrderMap[$amendant_record['header_id']])) {
                        $msg = 'RMA ID ' . $rmaOrderMap[$amendant_record['header_id']]->rma_order_id ;
                        $url = $this->url->link('account/rma_order_detail', '&rma_id='.$amendant_record['header_id']);
                    }
                } else if ($amendant_record['type_id'] == ChargeType::PAY_WIRE_TRANSFER || $amendant_record['type_id'] == ChargeType::PAY_PAYONEER || $amendant_record['type_id'] == ChargeType::PAY_PINGPONG) {
                    if (isset($rechargeApplyMap[$amendant_record['header_id']])) {
                        $msg = 'Deposit Funds ID ' . $rechargeApplyMap[$amendant_record['header_id']]['serial_number'];
                        $detailParams = [
                            'serial_number' => $rechargeApplyMap[$amendant_record['header_id']]['serial_number'],
                            'recharge_item_id' => $amendant_record['header_id'],
                            'url' => $backUrl
                        ];
                        $url = $this->url->link('account/balance/recharge/rechargeDetailOther',
                            http_build_query($detailParams), true);
                    }
                } elseif ($amendant_record['type_id'] == ChargeType::PAY_FUTURES_REFUND) {
                    $this->load->model('futures/agreement');
                    $msg = 'Futures Agreement ID (' . $this->model_futures_agreement->getAgreementById($amendant_record['header_id'])->agreement_no . ')';
                    $url = $this->url->link('account/product_quotes/futures/buyerFuturesBidDetail', '&id=' . $amendant_record['header_id']);
                } elseif (in_array($amendant_record['type_id'], [ChargeType::PAY_FEE_ORDER, ChargeType::PAY_SAFEGUARD, ChargeType::REFUND_SAFEGUARD, ChargeType::REFUND_STORAGE_FEE])) {
                    $feeOrder = FeeOrder::find($amendant_record['header_id']);
                    if ($feeOrder) {
                        $msg = "Charges Order ID:" . $feeOrder->order_no;
                        $url = $this->url->link('account/order', ['filter_fee_order_no' => $feeOrder->order_no, '#' => 'tab_fee_order']);
                    }
                } elseif ($amendant_record['type_id'] == ChargeType::RECHARGE_SAFEGUARD) {
                    $claim = SafeguardClaim::query()->find($amendant_record['header_id']);
                    $msg = "Claim of Protection Service. Claim Order ID: {$claim->claim_no}";
                    $url = $this->url->link('account/safeguard/claim/claimDetail', ['claim_id' => $claim->id]);
                }
                $data['amendantRecords'][] = array(
                    'id'=>$amendant_record['id'],
                    'serialNumber'=>$amendant_record['serial_number'],
                    'date_added'=>$amendant_record['date_added'],
                    'revenue' => in_array($amendant_record['type_id'], ChargeType::getRevenueTypes()) ? '+ ' . sprintf("%.{$decimalPlace}f", round($amendant_record['balance'] - $amendant_record['old_line_of_credit'], $this->precision)): '',
                    'payment' => in_array($amendant_record['type_id'], ChargeType::getPaymentTypes()) ? '- ' . sprintf("%.{$decimalPlace}f", round($amendant_record['old_line_of_credit'] - $amendant_record['balance'], $this->precision)) : '',
                    'balance'=>sprintf("%.{$decimalPlace}f", round($amendant_record['balance'], $this->precision)),
                    'remark' => ChargeType::getDescription($amendant_record['type_id']),
                    'msg' => $msg,
                    'url' => $url
                );
            }
        }
        $this->response->setOutput($this->load->view('account/balance/list_account_balance', $data));
    }

    public function tab_virtual_payment()
    {

        $this->load->model('account/balance/virtual_pay_record');
        $type = $this->request->get('filterType', 0);
        $timeSpace = $this->request->get('timeSpace', 4);
        $timeFrom = $this->request->get('timeFrom', null);
        $timeTo = $this->request->get('timeTo', null);
        $page_num = $this->request->get('page_num', 1);
        $page_limit = $this->request->get('page_limit', 20);

        $customerId = $this->customer->getId();
        $filter_data = array(
            'start'         => ($page_num - 1) * $page_limit,
            'limit'         => $page_limit,
            'type'          => $type,
            'timeSpace'     => $timeSpace,
            'timeFrom'      => $timeFrom,
            'timeTo'        => $timeTo,
            'customer_id'   => $customerId
        );

        $record = $this->model_account_balance_virtual_pay_record->searchRecord($filter_data);
        $total = $record['total'];
        $data['record_list'] = $record['record_list'];
        //分页
        $total_pages = ceil($total / $page_limit);
        $data['total_pages'] = $total_pages;

        $data['page_num'] = $page_num;
        $data['total'] = $total;
        $data['page_limit'] = $page_limit;
        $data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($total - $page_limit)) ? $total : ((($page_num - 1) * $page_limit) + $page_limit), $total, $total_pages);
        $data['type'] = $type;
        $data['TimeFrom'] = $timeFrom;
        $data['TimeTo'] = $timeTo;
        $data['timeSpace'] = $timeSpace;

        $this->response->setOutput($this->load->view('account/balance/list_virtual_payment', $data));
    }

    public function downloadAccountBalance()
    {
        set_time_limit(0);
        $this->load->model('account/customerpartner');
        $this->load->model('account/rma_management');
        $this->load->model('account/balance/recharge');
        $this->load->language('customerpartner/creditline_amendment_record');

        $filterType = $this->request->get('filterType', 3);
        $timeSpace = $this->request->get('timeSpace', 4);
        $timeFrom = $this->request->get('timeFrom', null);
        $timeTo = $this->request->get('timeTo', null);

        $filter_data = array(
            'filterType'    => $filterType,
            'timeSpace'     => $timeSpace,
            'timeFrom'  => $timeFrom,
            'timeTo'    => $timeTo,
            'customerId'=>$this->customer->getId()
        );

        //获取所有记录明细
        $amendant_records = $this->model_account_customerpartner->getCreditlineAmendmentRecordRow($filter_data);
        //12591 B2B记录各国别用户的操作时间
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis",time()),'YmdHis');
        //12591 end
        $fileName = "Creditdetails" . $time . ".xls";
        $head = array('Record Number', 'Date', 'Method', 'Remark', 'Received Credits', 'Payments', 'Balance', 'Currency');
        $rmaIds = array();
        $pCardAndWireIds = array();
        foreach ($amendant_records as $amendant_record) {
            if ($amendant_record['type_id'] == ChargeType::PAY_REFUND_CHARGE) {
                $rmaIds[] = $amendant_record['header_id'];
            }
            if ($amendant_record['type_id'] == ChargeType::PAY_WIRE_TRANSFER || $amendant_record['type_id'] == ChargeType::PAY_PAYONEER || $amendant_record['type_id'] == ChargeType::PAY_PINGPONG) {
                $pCardAndWireIds[] = $amendant_record['header_id'];
            }
        }
        // 根据RMAId查询RMA信息
        $rmaOrders = $this->model_account_rma_management->getRmaOrderByIdIn($rmaIds);
        if (isset($rmaOrders)) {
            foreach ($rmaOrders as $rmaOrder) {
                $rmaOrderMap[$rmaOrder->id] = $rmaOrder;
            }
        }

        //获取P-Card与Wire transfer的信息oc_recharge_apply
        $rechargeApplyOrders = $this->model_account_balance_recharge->getRechargeApplyInfo($pCardAndWireIds);
        if (isset($rechargeApplyOrders)) {
            $rechargeApplyMap = [];
            foreach ($rechargeApplyOrders as $rechargeApply) {
                $rechargeApplyMap[$rechargeApply['id']] = $rechargeApply;
            }
        }

        if(is_array($amendant_records) && isset($amendant_records) && !empty($amendant_records)){
            $timezone = CountryHelper::getTimezone($this->customer->getCountryId(), 'America/Los_Angeles');
            $decimalPlaceArr = CurrencyHelper::getCurrencyConfig();
            $decimalPlace = $decimalPlaceArr[session('currency')]['decimal_place'];
            foreach ($amendant_records as $amendant_record) {
                $msg = null;
                if ($amendant_record['type_id'] == ChargeType::PAY_LIMIT_CHARGE || $amendant_record['type_id'] == ChargeType::PAY_LIMIT_RECHARGE) {
                    $msg = $amendant_record['memo'];
                } else if ($amendant_record['type_id'] == ChargeType::PAY_CREDIT_CONSUMPTION) {
                    $msg = 'Order ID:' . $amendant_record['header_id'];
                } else if ($amendant_record['type_id'] == ChargeType::PAY_REFUND_CHARGE) {
                    if (isset($rmaOrderMap[$amendant_record['header_id']])) {
                        $msg = 'RMA ID:' . $rmaOrderMap[$amendant_record['header_id']]->rma_order_id;
                    }
                } else if ($amendant_record['type_id'] == ChargeType::PAY_WIRE_TRANSFER || $amendant_record['type_id'] == ChargeType::PAY_PAYONEER || $amendant_record['type_id'] == ChargeType::PAY_PINGPONG) {
                    if (isset($rechargeApplyMap[$amendant_record['header_id']])) {
                        $msg = 'Deposit Funds ID:' . $rechargeApplyMap[$amendant_record['header_id']]['serial_number'];
                    }
                } else if ($amendant_record['type_id'] == ChargeType::PAY_FUTURES_REFUND) {
                    $this->load->model('futures/agreement');
                    $msg = 'Futures Agreement ID (' . $this->model_futures_agreement->getAgreementById($amendant_record['header_id'])->agreement_no . ')';
                } elseif (in_array($amendant_record['type_id'], [ChargeType::PAY_FEE_ORDER, ChargeType::PAY_SAFEGUARD, ChargeType::REFUND_SAFEGUARD, ChargeType::REFUND_STORAGE_FEE])) {
                    $feeOrder = FeeOrder::find($amendant_record['header_id']);
                    if ($feeOrder) {
                        $msg = "Charges Order ID: {$feeOrder->order_no}";
                    }
                } elseif ($amendant_record['type_id'] == ChargeType::RECHARGE_SAFEGUARD) {
                    $claim = SafeguardClaim::query()->find($amendant_record['header_id']);
                    $msg = "Claim of Protection Service. Claim Order ID: {$claim->claim_no}";
                }
                $content[] = array(
                    "\t".$amendant_record['serial_number'],
                    $amendant_record['date_added'] ? \Carbon\Carbon::parse($amendant_record['date_added'])->setTimezone($timezone)->toDateTimeString() : '--',
                    ChargeType::getDescription($amendant_record['type_id']),
                    "\t" . $msg,
                    in_array($amendant_record['type_id'], ChargeType::getRevenueTypes()) ? '+ ' . sprintf("%.{$decimalPlace}f", round($amendant_record['balance'] - $amendant_record['old_line_of_credit'], $this->precision)) : '',
                    in_array($amendant_record['type_id'], ChargeType::getPaymentTypes()) ? '- ' . sprintf("%.{$decimalPlace}f", round($amendant_record['old_line_of_credit'] - $amendant_record['balance'], $this->precision)) : '',
                    sprintf("%.{$decimalPlace}f", round($amendant_record['balance'], $this->precision)). ' ',
                    session('currency')
                );
            }
            $content[] = array('','','','','','Current Balance',sprintf("%.{$decimalPlace}f", $this->customer->getLineOfCredit()) . ' ');
            //12591 B2B记录各国别用户的操作时间
            outputExcel($fileName,$head,$content,$this->session);
            //12591 end
        }else{
            $content[] = array($this->language->get('error_no_record'));
            //12591 B2B记录各国别用户的操作时间
            outputExcel($fileName,$head,$content,$this->session);
            //12591 end
        }
    }

    public function downloadVirtualPayment()
    {
        set_time_limit(0);
        $this->load->model('account/balance/virtual_pay_record');
        $type = $this->request->get('filterType', 0);
        $timeSpace = $this->request->get('timeSpace', 4);
        $timeFrom = $this->request->get('timeFrom', null);
        $timeTo = $this->request->get('timeTo', null);

        $customerId = $this->customer->getId();
        $filter_data = array(
            'type'          => $type,
            'timeSpace'     => $timeSpace,
            'timeFrom'      => $timeFrom,
            'timeTo'        => $timeTo,
            'customer_id'   => $customerId
        );

        $record = $this->model_account_balance_virtual_pay_record->searchRecord($filter_data);

        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis",time()),'YmdHis');
        $fileName = "VirtualPaymentDetails" . $time . ".xls";
        $head = array('Record Number', 'Date', 'Method|Remark', 'ID', 'Received Credits', 'Payments');

        $content =[];
        if($record['total']){
            foreach ($record['record_list'] as $record) {
                $method = $record['method'];
                $msg = '';
                switch ($record['type']){
                    case 1:
                    {
                        $msg = 'Order ID:' . $record['relation_id'];
                        break;
                    }
                    case 2:
                    {
                        $msg = 'RMA ID:' . $record['rma_order_id'];
                        break;
                    }
                    case 3:
                    {
                        $msg = 'Agreement ID:' . $record['agreement_code'];
                        break;
                    }
                    case 4:
                    case 5:
                    case 6:
                    {
                        $feeOrder = FeeOrder::find($record['relation_id']);
                        if ($feeOrder) {
                            $msg = 'Charges Order ID:' . $feeOrder->order_no;
                        }
                        break;
                    }
                    case 7:
                    {
                        $claim = SafeguardClaim::query()->find($record['relation_id']);
                        if ($claim) {
                            $msg = 'Claim of Protection Service. Claim Order ID:' . $claim->claim_no;
                        }
                        break;
                    }
                }
                $content[] = array(
                    "\t".$record['serial_number'],
                    $record['create_time'],
                    $method,
                    $msg,
                    $record['revenue'],
                    $record['payment'],
                );
            }
            outputExcel($fileName,$head,$content,$this->session);

        }else{
            $content[] = ['No record.'];
            outputExcel($fileName,$head,$content,$this->session);
        }
    }

}
