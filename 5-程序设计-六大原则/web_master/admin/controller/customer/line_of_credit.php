<?php

use App\Enums\Charge\ChargeType;
use App\Enums\CreditLine\SysCreditLintPlatformType;
use App\Logging\Logger;
use App\Models\FeeOrder\FeeOrder;
use App\Models\Safeguard\SafeguardClaim;
use App\Repositories\Pay\PlatformTypeRepository;
use Psr\Log\LogLevel;
use App\Helper\StringHelper;

/**
 * Class ControllerCustomerLineOfCredit
 *
 * @property ModelCustomerLineOfCredit $model_customer_line_of_credit
 * @property ModelCustomerRecharge $model_customer_recharge
 */
class ControllerCustomerLineOfCredit extends Controller
{

    const CHARGE_TYPE = [
        1 => 'Limit Recharge',
        3 => 'Refund Recharge',
        4 => 'Rebate Recharge',
        5 => 'Margin Refund',
        6 => 'Limit Recharge',
        7 => 'Airwallex Recharge',
        8 => 'Wire transfer',
        9 => 'Payoneer',
        10=> 'Futures Refund',
    ];

    public function index()
    {
        $this->load->language('customer/line_of_credit');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('customer/line_of_credit');

        $this->getList();
    }

    protected function getList()
    {
        if (isset($this->request->get['name'])) {
            $name = $this->request->get['name'];
        } else {
            $name = '';
        }
        if (isset($this->request->get['email'])) {
            $email = $this->request->get['email'];
        } else {
            $email = '';
        }
        if (isset($this->request->get['status'])) {
            $status = $this->request->get['status'];
        } else {
            $status = '';
        }
        if (isset($this->request->get['role'])) {
            $role = $this->request->get['role'];
        } else {
            $role = '';
        }
        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . session('user_token'), true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('customer/line_of_credit', 'user_token=' . session('user_token'), true)
        );
        // 每页显示数目
        if (isset($this->request->get['page'])) {
            $page_num = $this->request->get['page'];
        } else {
            $page_num = 1;
        }
        $data['page_num'] = $page_num;
        if (isset($this->request->get['page_limit'])) {
            $page_limit = $this->request->get['page_limit'];
        } else {
            $page_limit = 20;
        }
        $data['page_limit'] = $page_limit;
        $filter_data = array(
            'name' => $name,
            'email' => $email,
            'status' => $status,
            'role' => $role,
            'start' => ($page - 1) * $page_limit,
            'limit' => $page_limit
        );

        $customer_total = $this->model_customer_line_of_credit->getTotalCustomers($filter_data);

        $results = $this->model_customer_line_of_credit->getCustomers($filter_data);

        foreach ($results as $result) {
            $data['customers'][] = array(
                'id' => $result['customer_id'],
                'name' => $result['name'],
                'email' => $result['email'],
                'user_number' => $result['user_number'],
                'country_name' => $result['country_name'],
                'currency_code' => $result['currency_code'],
                'total' => $this->model_customer_line_of_credit->getLineOfCreditAmendantRecords($result['customer_id']),
                'status' => ($result['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled')),
                'balance' => floor(bcmul($result['line_of_credit'], 100)) / 100,
                'showDetail' => $this->url->link('customer/line_of_credit/showDetail', '&customerId = ' . $result['customer_id'] . '&user_token=' . session('user_token'), true)
            );
        }

        $data['user_token'] = session('user_token');

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

        $url = '';

        if (isset($this->request->get['name'])) {
            $url .= '&name=' . urlencode(html_entity_decode($this->request->get['name'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['email'])) {
            $url .= '&email=' . urlencode(html_entity_decode($this->request->get['email'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['status'])) {
            $url .= '&status=' . $this->request->get['status'];
        }

        if (isset($this->request->get['role'])) {
            $url .= '&role=' . $this->request->get['role'];
        }

        $pagination = new Pagination();
        $pagination->total = $customer_total;
        $pagination->page = $page;
        $pagination->limit = $page_limit;
        $pagination->url = $this->url->link('customer/line_of_credit', 'user_token=' . session('user_token') . $url . '&page={page}', true);

        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf(
            $this->language->get('text_pagination'),
            ($customer_total) ? (($page - 1) * $page_limit) + 1 : 0,
            ((($page - 1) * $page_limit) > ($customer_total - $page_limit))
                ? $customer_total
                : ((($page - 1) * $page_limit) + $page_limit),
            $customer_total,
            ceil($customer_total / $page_limit)
        );

        $platformTypeRepository = app(PlatformTypeRepository::class);

        // 获取收款账号和付款账号
        $data['companyAccountList'] = json_encode($this->model_customer_line_of_credit->getCompanyAccountList());
        $data['creditGetNonCapitalList'] = $platformTypeRepository->getCreditLinePlatformTypeMap(SysCreditLintPlatformType::COLLECTION_TYPE, SysCreditLintPlatformType::ACCOUNT_TYPE_NON_CAPITAL, SysCreditLintPlatformType::STATUS_ENABLE);
        $data['creditGetPreChargeList'] = $platformTypeRepository->getCreditLinePlatformTypeMap(SysCreditLintPlatformType::COLLECTION_TYPE, SysCreditLintPlatformType::ACCOUNT_TYPE_PRE_CHARGE, SysCreditLintPlatformType::STATUS_ENABLE);
        $data['creditPayList'] = $platformTypeRepository->getCreditLinePlatformTypeMap(SysCreditLintPlatformType::PAYMENT_TYPE, SysCreditLintPlatformType::ACCOUNT_TYPE_NON_CAPITAL, SysCreditLintPlatformType::STATUS_ENABLE);

        $data['name'] = $name;
        $data['email'] = $email;
        $data['status'] = $status;
        $data['role'] = $role;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('customer/line_of_credit_list', $data));
    }

    public function showDetail()
    {
        $this->load->language('customer/line_of_credit');

        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('customer/line_of_credit');
        $this->load->model('customer/recharge');

        $page = $this->request->get('page', 1);
        $page_limit = $this->request->get('page_limit', 20);

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', ['user_token' => $this->session->get('user_token')])
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('customer/line_of_credit', ['user_token' => $this->session->get('user_token')])
        );

        $data['cancel'] = $this->url->link('customer/line_of_credit', ['user_token' => $this->session->get('user_token')]);

        $customerId = $this->request->get['customerId_'];

        $filter_data = array(
            'customer_id' => $this->request->get['customerId_'],
            'start' => ($page - 1) * $page_limit,
            'limit' => $page_limit
        );

        $amendant_record_total = $this->model_customer_line_of_credit->getLineOfCreditAmendantRecords($customerId);

        $amendant_records = $this->model_customer_line_of_credit->getLineOfCreditAmendantRecordRow($filter_data);

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

        $pagination = new Pagination();
        $pagination->total = $amendant_record_total;
        $pagination->page = $page;
        $pagination->limit = $page_limit;
        $pagination->url = $this->url->link('customer/line_of_credit/showDetail', '&page={page}&customerId_=' . $customerId . '&user_token=' . session('user_token'), true);

        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf(
            $this->language->get('text_pagination'),
            ($amendant_record_total) ? (($page - 1) * $page_limit) + 1 : 0,
            ((($page - 1) * $page_limit) > ($amendant_record_total - $page_limit))
                ? $amendant_record_total : ((($page - 1) * $page_limit) + $page_limit),
            $amendant_record_total,
            ceil($amendant_record_total / $page_limit)
        );

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        $data['separate_column_left'] = '';

        $pCardAndWireIds = array();
        foreach ($amendant_records as $amendant_record) {
            if ($amendant_record['type_id'] == ChargeType::PAY_WIRE_TRANSFER || $amendant_record['type_id'] == ChargeType::PAY_PAYONEER || $amendant_record['type_id'] == ChargeType::PAY_PINGPONG) {
                $pCardAndWireIds[] = $amendant_record['header_id'];
            }
        }

        //获取P-Card与Wire transfer的信息oc_recharge_apply
        $rechargeApplyOrders = $this->model_customer_recharge->getRechargeApplyInfoById($pCardAndWireIds);
        if (isset($rechargeApplyOrders)) {
            $rechargeApplyMap = [];
            foreach ($rechargeApplyOrders as $rechargeApply) {
                $rechargeApplyMap[$rechargeApply['id']] = $rechargeApply;
            }
        }

        foreach ($amendant_records as $amendant_record) {
            if ($amendant_record['operator_id'] > 0) {
                $operatorId = $this->model_customer_line_of_credit->getUserNameById($amendant_record['operator_id'], $amendant_record['type_id']);
            } else {
                $operatorId = 'B2B GIGACLOUDLOGISTICS';
            }
            if ($amendant_record['type_id'] == ChargeType::PAY_REBATE_CHARGE) {
                $rebate_info = $this->model_customer_recharge->getRebateInfo((int)$amendant_record['header_id']);
                $amendant_record['memo'] = "返点协议：" . ($rebate_info['agreement_code'] ?? '');
            } elseif ($amendant_record['type_id'] == ChargeType::PAY_MARGIN_REFUND) {
                $margin_info = $this->model_customer_recharge->getMarginInfo((int)$amendant_record['header_id']);
                $amendant_record['memo'] = "现货保证金返金：" . ($margin_info['agreement_id'] ?? '');
                $operatorId='B2B GIGACLOUDLOGISTICS';
            } elseif ($amendant_record['type_id'] == ChargeType::PAY_WIRE_TRANSFER || $amendant_record['type_id'] == ChargeType::PAY_PAYONEER || $amendant_record['type_id'] == ChargeType::PAY_PINGPONG) {
                if (isset($rechargeApplyMap[$amendant_record['header_id']])) {
                    $amendant_record['memo'] = 'Application from B2B <br/>Deposit Funds ID:' . $rechargeApplyMap[$amendant_record['header_id']]['serial_number'];
                    $operatorId = $rechargeApplyMap[$amendant_record['header_id']]['firstname'] . $rechargeApplyMap[$amendant_record['header_id']]['lastname'] . ',' . $rechargeApplyMap[$amendant_record['header_id']]['country'];
                }
            } elseif (in_array($amendant_record['type_id'], [ChargeType::PAY_FEE_ORDER, ChargeType::PAY_SAFEGUARD, ChargeType::REFUND_SAFEGUARD, ChargeType::REFUND_STORAGE_FEE])) {
                $feeOrder = FeeOrder::query()->find($amendant_record['header_id']);
                $amendant_record['memo'] = "Charges Order ID:" . ($feeOrder ? $feeOrder->order_no : '');
                $operatorId = 'B2B GIGACLOUDLOGISTICS';
            } elseif ($amendant_record['type_id'] == ChargeType::RECHARGE_SAFEGUARD) {
                $claim = SafeguardClaim::query()->find($amendant_record['header_id']);
                $amendant_record['memo'] = "Claim of Protection Service. Claim Order ID: {$claim->claim_no}";
                $operatorId = 'B2B GIGACLOUDLOGISTICS';
            }
            $data['amendantRecords'][] = array(
                'serialNumber' => $amendant_record['serial_number'],
                'newLineOfCredit' => floor(bcmul($amendant_record['new_line_of_credit'], 100)) / 100,
                'oldLineOfCredit' => floor(bcmul($amendant_record['old_line_of_credit'], 100)) / 100,
                'rechargePrice' => round(floor(bcmul($amendant_record['new_line_of_credit'], 100)) / 100 - floor(bcmul($amendant_record['old_line_of_credit'], 100)) / 100, 2),
                'addDate' => $amendant_record['date_added'],
                'currency_name' => $amendant_record['currency_name'],
                'memo' => $amendant_record['memo'],
                'type' => ChargeType::getDescription($amendant_record['type_id']),
                'operatorId' => $operatorId
            );
        }

        $this->response->setOutput($this->load->view('customer/line_of_credit_detail_list', $data));
    }

    public function update()
    {
        $this->load->language('customer/line_of_credit');
        $this->load->model('customer/line_of_credit');
        // 加载Model
        $json = [
            'error' => 0,
            'msg'   =>'',
        ];
        $input = $this->request->attributes;
        $recharge = $input->get('recharge');
        $customer_id = $input->get('customer_id');
        $check = $input->get('check');
        $company_account_id = $input->get('company_account_id');
        $platform_date = $input->get('platform_date');
        $platform_get_type_id = $input->get('platform_get_type_id','-1');
        $platform_pay_type_id = $input->get('platform_pay_type_id','-1');
        $fee_number = trim($input->get('fee_number', NULL));
        $third_trade_number = trim($input->get('third_trade_number', NULL));
        $pay_exchange_rate = trim($input->get('pay_exchange_rate', NULL));
        $platformSecondTypeId = trim($this->request->post('platform_second_type_id', '-1'));

        // 跳转的url
        $json['load'] = $this->url->link('customer/line_of_credit',
                            [
                                'name'=>$input->get('name',''),
                                'user_token'=>$input->get('user_token',''),
                                'email'=>$input->get('email',''),
                                'status'=>$input->get('status',''),
                                'role'=>$input->get('role',''),
                                'page_num'=>$input->get('page',1),
                                'page_limit'=>$input->get('page_limit',20),
                        ]);

        $old_balance = $this->customer->getLineOfCreditBySeller($customer_id);
        $memo = $input->get('memo');
        $operatorId = $this->user->getId();
        $update_data = [
            "customerId" => $customer_id,
            "balance" => $recharge + $old_balance,
            "oldBalance" => $old_balance,
            'operatorId' => $operatorId,
            "memo" => $memo
        ];
        // 选择了是之后需要把数据存储
        if($check){
            $update_data['company_account_id'] = $company_account_id;
            $update_data['platform_date'] = $platform_date;
        }else{
            if($platform_get_type_id != '-1'){
                $update_data['platform_get_type_id'] = $platform_get_type_id;
                $update_data['fee_number'] = $fee_number;
                $update_data['third_trade_number'] = $third_trade_number;
            }

            if($platform_pay_type_id != '-1'){
                $update_data['platform_pay_type_id'] = $platform_pay_type_id;
                $update_data['pay_exchange_rate'] = $pay_exchange_rate;
            }
            if ($platformSecondTypeId != '-1') {
                $update_data['platform_second_type_id'] = $platformSecondTypeId;
                $update_data['fee_number'] = $fee_number;
            }
        }

        if ($recharge < 0) {
            $update_data['typeId'] = 6;
        }
        //dd($update_data['platform_date']);

        if(($recharge + $old_balance) < 0){
            $json['msg'] = $this->language->get('error_save_line_of_credit');
            $json['error'] = 1;
            return $this->response->json($json);
        }
        try {
            $this->db->beginTransaction();
            $this->model_customer_line_of_credit->updateCustomerInfo($update_data);
            $this->model_customer_line_of_credit->saveAmendantRecord($update_data);
            $this->db->commit();
        } catch (Exception $e) {
            Logger::app($e, LogLevel::ERROR);
            Logger::app((array)$input, LogLevel::ERROR);
            Logger::app('Limit Recharge 报错');
            $this->db->rollback();
            $json['msg'] = $this->language->get('error_save_line_of_credit');
            $json['error'] = 1;
            return $this->response->json($json);
        }

        return $this->response->json($json);
    }
}
