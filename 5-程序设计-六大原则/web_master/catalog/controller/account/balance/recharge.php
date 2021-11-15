<?php

use App\Catalog\Controllers\AuthController;
use App\Components\RemoteApi;
use App\Components\UniqueGenerator\Enums\ServiceEnum;
use App\Helper\CountryHelper;
use App\Repositories\Common\SerialNumberRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use App\Components\Storage\StorageCloud;

/**
 * Class ControllerAccountBalanceRecharge
 * Date: 2020/6/10
 * @author: LiLei
 * @property ModelAccountBalanceRecharge $model_account_balance_recharge
 * @property ModelLocalisationCurrency $model_localisation_currency
 * @property ModelAccountCustomer $model_account_customer
 */
class ControllerAccountBalanceRecharge extends AuthController
{

    const PROOF_SIZE = 30 * 1024 * 1024;   //上传文件大小 30M
    const PROOF_TYPE = ['image/png', 'image/jpeg', 'image/jpg'];    //文件类型
    const PROOF_EXTENSION_TYPE = ['png', 'jpeg', 'jpg'];    //文件后缀判断
    const RECHARGE_PROOFS_FILE_PATH = DIR_STORAGE . 'upload/recharge/proofs';//凭证上传根目录

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

    public function index()
    {
        $this->load->model('account/customerpartner');
        $this->load->model('account/balance/recharge');
        $this->load->model('account/customer');
        $this->load->model('localisation/currency');
        $this->load->language('account/balance');

        $this->document->setTitle($this->language->get('heading_title_recharge'));
        $data['breadcrumbs'] = $this->getBreadcrumbs(['home', [
            'text' => $this->language->get('text_account'),
            'href' => $this->url->link('account/account', '', true)
        ]]);

        // 判断用户有无开通Airwallex账户
        $buyerAirwallexInfo = $this->model_account_balance_recharge->getBuyerAirwallexInfo($this->customer_id);
        if (!isset($buyerAirwallexInfo->airwallex_id)) {
            // 没有Airwallex账户
            $data['haveAirwallexAccount'] = false;
            // 定义airwallexIdentifier
            $airwallexIdentifier = $this->customer_id . '_' . $this->customer->getUserNumber();
            $data['airwallexRegisterUrl'] = AIRWALLEX_REGISTER_URL . $airwallexIdentifier;
            if ($buyerAirwallexInfo->airwallex_identifier == '0') {
                // 初始化Identifier
                $this->model_account_balance_recharge->saveAirwallexIdentifier($this->customer_id, $airwallexIdentifier);
            } else {
                // 说明已经生成了 airwallex_identifier,但是还未绑定 - 调用java接口，拉取一次最新信息，目前仅做一次拉取最新信息
                RemoteApi::airwallex()->updateAirwallexBindInfo($airwallexIdentifier);
            }
            // 判断有无提交审核材料
            $applyData = $this->model_account_balance_recharge->getAirwallexBindApply($this->customer_id);
            if ($applyData == null) {
                $data['airwallexRegisterApplyFlag'] = true;
            } else {
                $data['airwallexRegisterApplyFlag'] = false;
            }
        } else {
            $data['haveAirwallexAccount'] = true;
            // 获取账户余额
            $airwallexAccountBalance = $this->customer->getAirwallexAccountBalance($buyerAirwallexInfo->airwallex_id);
//            $data['airwallexAccountBalanceVal'] = $airwallexAccountBalance;
//            $data['airwallexAccountBalance'] = $this->currency->format($airwallexAccountBalance, session('currency'));
            $data['currencySymbolRight'] = $this->currency->getSymbolRight($this->session->get('currency'));
            $data['currencySymbolLeft'] = $this->currency->getSymbolLeft($this->session->get('currency'));
            if (JAPAN_COUNTRY_ID == $this->customer->getCountryId()) {
                $data['isJp'] = true;
            } else {
                $data['isJp'] = false;
            }
            // 获取Buyer Account信息
            $data['buyerAccount'] = $this->customer->getUserNumber() . ', ' . $this->customer->getEmail() . ', ' . $this->customer->getFirstName() . ' ' . $this->customer->getLastName();
        }
        //充值buyer的信息
        $data['customer_data'] = $this->model_account_customer->getRechargeBuyerList('', $this->customer->getId(), true);
        //支持打款的币种
        $currencies = $this->model_localisation_currency->getCurrenciesNoCache();
        unset($currencies['UUU']);//没有人民币
        foreach ($currencies as &$currency) {
            //带上对美元的汇率
            $currency['rate'] = json_encode($this->model_localisation_currency->getExchangeRate($currency['code']));
        }
        $data['currencies'] = $currencies;
        $data['commissions_json'] = json_encode($this->model_account_balance_recharge->getCommissions());
        $data['commissions'] = $this->model_account_balance_recharge->getCommissions();
        //上传图片相关参数
        $data['upload_recharge_proof_url'] = $this->url->link("account/balance/recharge/uploadRechargeProof");
        $data['upload_recharge_proof_max_size'] = self::PROOF_SIZE;
        $data['upload_recharge_proof_types'] = json_encode(self::PROOF_TYPE);
        //保存链接
        $data['apply_save_url'] = $this->url->link("account/balance/recharge/rechargeApply");
        //充值指导链接
        $data['recharge_instructions_url'] = $this->url->link('information/information',
            ['information_id' => $this->config->get('adding_account_help_id')]);

        return $this->render('account/balance/recharge_apply', $data, [
            'header' => 'common/header',
            'footer' => 'common/footer',
            'column_left' => 'common/column_left',
            'column_right' => 'common/column_right',
            'content_top' => 'common/content_top',
            'content_bottom' => 'common/content_bottom',
        ]);
    }

    public function rechargeDetail()
    {
        $data[] = array();
        $this->load->model('account/customerpartner');
        $this->load->model('account/balance/recharge');
        $this->load->model('localisation/currency');
        $this->load->language('account/balance');
        $this->document->setTitle($this->language->get('detail_heading_title'));
        $data['breadcrumbs'] = $this->getBreadcrumbs(['home', [
            'text' => $this->language->get('text_account'),
            'href' => $this->url->link('account/account', '', true)
        ]]);
        $serialNumber = $this->request->get('serialNumber', 0);
        // 获取Buyer Account信息
        $data['buyerAccount'] = $this->customer->getUserNumber() . ', ' . $this->customer->getEmail() . ', ' . $this->customer->getFirstName() . ' ' . $this->customer->getLastName();
        $data['currencySymbolRight'] = $this->currency->getSymbolRight($this->session->get('currency'));
        $data['currencySymbolLeft'] = $this->currency->getSymbolLeft($this->session->get('currency'));
        // 根据BuyerId和serialNumber查询申请结果
        $rechargeApply = $this->model_account_balance_recharge->getRechargeApply($serialNumber, $this->customer_id);
        $backUrl = $this->request->get('url', null);
        $data['backUrl'] = str_replace('&amp;', '&', $this->url->link('account/balance/buyer_balance', 'tab=2&rechargeUrl=' . urlencode($backUrl), true));
        $data['serialNumber'] = $serialNumber;
        $data['amount'] = round($rechargeApply->amount, $this->precision);
        $data['rechargeStatus'] = $rechargeApply->apply_status;
        $data['apply_date'] = $rechargeApply->apply_date;
        $data['update_time'] = $rechargeApply->update_time;
        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $this->response->setOutput($this->load->view('account/balance/recharge_apply_airwallex_detail', $data));
    }

    //recharge详情 目前只用于p卡和电汇，其他方式用请阅读源码
    public function rechargeDetailOther()
    {
        //参数接收
        $serialNumber = $this->request->get('serial_number', 0);
        $rechargeItemId = $this->request->get('recharge_item_id', 0);
        $customerId = $this->customer->getId();

        $this->load->model('account/balance/recharge');
        $this->load->model('account/customer');
        $this->load->model('localisation/currency');
        $this->load->language('account/balance');

        $this->document->setTitle($this->language->get('detail_heading_title'));

        $data[] = array();
        $data['breadcrumbs'] = $this->getBreadcrumbs(['home', [
            'text' => $this->language->get('text_account'),
            'href' => $this->url->link('account/account', '', true)
        ]]);
        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $data['backUrl'] = str_replace('&amp;', '&', $this->url->link('account/balance/buyer_balance', 'tab=2', true));

        $data['recharge'] = $this->model_account_balance_recharge->getRechargeBySerialNumber($serialNumber);
        if (empty($data['recharge'])) {
            //数据不存在
            $data['continue'] = $data['backUrl'];
            $this->document->setTitle($this->language->get('recharge_record_does_not_exist'));
            $data['heading_title'] = $this->language->get('recharge_record_does_not_exist');
            return $this->response->setOutput($this->load->view('error/not_found', $data));
        }
        $data['recharge_item'] = $this->model_account_balance_recharge->getRechargeItem($data['recharge']->id, $rechargeItemId);
        if (empty($data['recharge_item'])) {
            $data['continue'] = $data['backUrl'];
            $this->document->setTitle($this->language->get('recharge_record_does_not_exist'));
            $data['heading_title'] = $this->language->get('recharge_record_does_not_exist');
            return $this->response->setOutput($this->load->view('error/not_found', $data));
        }
        //判断订单不属于该用户
        if ($data['recharge']->buyer_id != $customerId && $data['recharge_item']->buyer_id != $customerId) {
            $data['continue'] = $data['backUrl'];
            $this->document->setTitle($this->language->get('recharge_record_does_not_exist'));
            $data['heading_title'] = $this->language->get('recharge_record_does_not_exist');
            return $this->response->setOutput($this->load->view('error/not_found', $data));
        }
        //充值方式
        $rechargeMethodsDicCategory = 'RECHARGE_METHODS';
        $rechargeMethodsArr = $this->model_account_balance_recharge->getDicCategory($rechargeMethodsDicCategory);
        $data['recharge']->recharge_method_str = $rechargeMethodsArr[$data['recharge']->recharge_method] ?? '';

        $currencies = $this->model_localisation_currency->getCurrenciesNoCache();
        //获取凭证
        $data['proofs'] = $this->model_account_balance_recharge->getRechargeProofs($data['recharge']->id);
        //获取充值buyer的信息
        $data['recharge']->buyer = $this->model_account_customer->getRechargeBuyerList('', $data['recharge']->buyer_id, true);
        //获取充值币种的符号信息
        $data['recharge']->currency_data = $currencies[$data['recharge']->currency];
        //获取被充值账户的信息
        $data['recharge_item']->buyer = $this->model_account_customer->getRechargeBuyerList('', $data['recharge_item']->buyer_id, true);
        //处理充值金额和到账金额的精度
        //充值金额
        $data['recharge']->amount = round($data['recharge']->amount, $this->currency->getDecimalPlace($data['recharge']->currency));
        //到账金额：预计到账和实际到账都一起转换
        $data['recharge_item']->recharge_amount = round($data['recharge_item']->recharge_amount, $this->currency->getDecimalPlace($data['recharge']->currency));
        $data['recharge_item']->expect_amount = round($data['recharge_item']->expect_amount, $this->currency->getDecimalPlace($data['recharge_item']->account_currency_code));
        $data['recharge_item']->actual_amount = round($data['recharge_item']->actual_amount, $this->currency->getDecimalPlace($data['recharge_item']->account_currency_code));

        //处理汇率
        $data['recharge_item']->recharge_exchange_rate = sprintf("%.3f", $data['recharge_item']->recharge_exchange_rate);
        $data['recharge_item']->actual_exchange_rate = sprintf("%.3f", $data['recharge_item']->actual_exchange_rate);

        //充值指导链接
        $data['recharge_instructions_url'] = $this->url->link('information/information',
            ['information_id' => $this->config->get('adding_account_help_id')]);
        $data['commissions'] = $this->model_account_balance_recharge->getCommissions();

        $this->response->setOutput($this->load->view('account/balance/recharge_apply_other_detail', $data));
    }

    /**
     * 下载文件
     */
    public function downLoadFile()
    {
        $filePath = $this->request->get('filePath', '');
        $fileName = $this->request->get('fileName', '');

        if (! $filePath || ! $fileName) {
            return $this->redirect(['account/balance/buyer_balance'])->send();
        }

        return StorageCloud::root()->browserDownload($filePath, $fileName);
    }

    /**
     * 获取子订单的申请号
     */
    public function brotherSerialNumber()
    {
        $apply_id = $this->request->post('apply_id');
        $this->load->model('account/balance/recharge');
        $serial_number_arr = $this->model_account_balance_recharge->brotherSerialNumber($apply_id);
        $json = [
            'error' => 0,
            'info' => $serial_number_arr
        ];
        return $this->json($json);
    }

    /**
     * 删除申请
     * @throws Exception
     */
    public function applyDelete()
    {
        $this->load->model('account/balance/recharge');
        $this->load->language('account/balance');
        $apply_id = $this->request->post('apply_id');
        //判断状态是否都是applied
        $serial_number_status = $this->model_account_balance_recharge->brotherSerialNumberNotStatus($apply_id, 1);
        if ($serial_number_status > 0) {
            return $this->jsonFailed($this->language->get('delete_recharge_order_error'));
        } else {
            //软删除
            $result = $this->model_account_balance_recharge->applyDelete($apply_id);
            if ($result > 0) {
                return $this->jsonSuccess([], $this->language->get('delete_recharge_order_success'));
            }
        }
        return $this->jsonFailed($this->language->get('delete_recharge_order_failed'));
    }

    public function downloadExcel()
    {
        $this->load->model('account/balance/recharge');
        $this->load->language('account/balance');
        $timeFrom = $this->request->get('timeFrom', null);
        $timeTo = $this->request->get('timeTo', null);
        $rechargeStatus = $this->request->get('recharge_status', 0);
        $customerId = $this->customer->getId();
        $filter_data = array(
            'rechargeStatus' => $rechargeStatus,
            'timeFrom' => $timeFrom,
            'timeTo' => $timeTo,
            'customer_id' => $customerId
        );
        $record = $this->model_account_balance_recharge->searchRecord($filter_data);
        // 组装数据
        $info = array();
        $rechargeMethodsDicCategory = 'RECHARGE_METHODS';
        $rechargeApplyStatusDicCategory = 'RECHARGE_APPLY_STATUS';
        $rechargeMethodsArr = $this->model_account_balance_recharge->getDicCategory($rechargeMethodsDicCategory);
        $rechargeApplyStatusArr = $this->model_account_balance_recharge->getDicCategory($rechargeApplyStatusDicCategory);
        // 处理数据
        foreach ($record['record_list'] as $recordList) {
            $recharge_method = $recordList['recharge_method'];
            $recordList['rechargeMethod'] = $rechargeMethodsArr[$recharge_method];
            $recordList['react_amount'] = '';
            // Airwallex支付方式充值
            if ($recharge_method == 'airwallex') {
                $recordList['serial_number'] = $recordList['parent_serial_number'];
                $apply_status = $recordList['apply_status'];
                $recordList['applyStatus'] = $rechargeApplyStatusArr[$apply_status];
                $recordList['recharge_amount'] = $this->currency->format($recordList['amount'], $recordList['currency']);
                if ($apply_status == '4') {
                    $recordList['applyStatus'] = 'Completed';
                    //审核通过，显示审核过的金额
                    $recordList['react_amount'] = $this->currency->format($recordList['amount'], $recordList['currency']);
                } elseif ($apply_status == '3') {
                    $recordList['applyStatus'] = 'Rejected';
                } else {
                    $recordList['applyStatus'] = $rechargeApplyStatusArr[$apply_status];
                }

            } else {
                $recordList['firstname'] = $recordList['recharge_item_firstname'];
                $recordList['lastname'] = $recordList['recharge_item_lastname'];
                $recordList['country'] = $recordList['recharge_item_country'];
                $recordList['email'] = $recordList['recharge_item_email'];
                $apply_status = $recordList['status'];
                $recordList['applyStatus'] = $rechargeApplyStatusArr[$apply_status];
                $recordList['recharge_amount'] = $this->currency->format($recordList['recharge_amount'], $recordList['recharge_currency_code']);

                if ($apply_status == '4') {
                    //审核通过，显示审核过的金额
                    $recordList['react_amount'] = $this->currency->format($recordList['actual_amount'], $recordList['account_currency_code']);
                    $recordList['applyStatus'] = 'Completed';
                } elseif ($apply_status == '3') {
                    $recordList['applyStatus'] = 'Rejected';
                } else {
                    $recordList['applyStatus'] = $rechargeApplyStatusArr[$apply_status];
                }
            }

            $info[] = array(
                'serial_number' => $recordList['serial_number'],
                'exchange_hour' => changeOutPutByZone($recordList['apply_date'], $this->session),
                'method' => $recordList['rechargeMethod'],
                'account' => $recordList['firstname'] . $recordList['lastname'] . ', ' . $recordList['country'] . ', ' . $recordList['email'],
                'recharge_amount' => $recordList['recharge_amount'],
                'actual_amount' => $recordList['react_amount'],
                'status' => $recordList['applyStatus']
            );
        }

        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $time = dateFormat($fromZone, $toZone, date("YmdHis", time()), 'YmdHis');
        $fileName = "Recharge" . $time . ".xlsx";

        $spresadsheet = new Spreadsheet();
        $spresadsheet->setActiveSheetIndex(0);
        $sheet = $spresadsheet->getActiveSheet();

        $sheet->setTitle('Recharge');
        if (count($info) > 0) {
            //设置第一行小标题
            $k = 1;
            // 字体加粗
            $sheet->getStyle('A1:F1')->getFont()->setBold(true);
            $styleArray = array(
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER, // 水平居中
                    'vertical' => Alignment::VERTICAL_CENTER // 垂直居中
                ]
            );
            $sheet->setCellValue('A' . $k, 'Record Number')
                ->getStyle('A1:A' . (count($info) + 1))->getNumberFormat()->applyFromArray([
                    'formatCode' => NumberFormat::FORMAT_NUMBER // 不以科学计数法显示数字
                ]);
            $sheet->getStyle('A1:A' . (count($info) + 1))->applyFromArray($styleArray);
            $sheet->getColumnDimension('A')->setWidth(20); // 设置列宽

            $sheet->setCellValue('B' . $k, 'Date');
            $sheet->getStyle('B1:B' . (count($info) + 1))->applyFromArray($styleArray);
            $sheet->getColumnDimension('B')->setWidth(20); // 设置列宽

            $sheet->setCellValue('C' . $k, 'Method');
            $sheet->getStyle('C1:C' . (count($info) + 1))->applyFromArray($styleArray);
            $sheet->getColumnDimension('C')->setWidth(15); // 设置列宽

            $sheet->setCellValue('D' . $k, 'Account');
            $sheet->getColumnDimension('D')->setWidth(40); // 设置列宽

            $sheet->setCellValue('E' . $k, $this->language->get('title_application_amount'));
            $sheet->getStyle('E1:E' . (count($info) + 1))->applyFromArray($styleArray);
            $sheet->getColumnDimension('E')->setWidth(30); // 设置列宽

            $sheet->setCellValue('F' . $k, $this->language->get('title_actual_amount'));
            $sheet->getStyle('F1:F' . (count($info) + 1))->applyFromArray($styleArray);
            $sheet->getColumnDimension('F')->setWidth(30); // 设置列宽

            $sheet->setCellValue('G' . $k, 'Status');
            $sheet->getStyle('G1:G' . (count($info) + 1))->applyFromArray($styleArray);
            $sheet->getColumnDimension('G')->setWidth(12); // 设置列宽
            // 查询数据
            $sheet->fromArray($info, null, 'A2');
        } else {
            $sheet->fromArray(['No Record.'], null, 'A1');
        }
        ob_end_clean();
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
        $writer = IOFactory::createWriter($spresadsheet, 'Xlsx');
        //注意createWriter($spreadsheet, 'Xls') 第二个参数首字母必须大写
        $writer->save('php://output');
    }

    public function tab_recharge()
    {
        $data[] = array();
        $this->load->model('account/balance/recharge');
        $this->load->language('account/balance');
        $url = '';
        $timeFrom = $this->request->get('timeFrom', null);
        $url .= 'timeFrom=' . $timeFrom;
        $timeTo = $this->request->get('timeTo', null);
        $url .= '&timeTo=' . $timeTo;
        $rechargeStatus = $this->request->get('recharge_status', 0);
        $url .= '&recharge_status=' . $rechargeStatus;
        $page_num = $this->request->get('page_num', 1);
        $url .= '&page_num=' . $page_num;
        $page_limit = $this->request->get('page_limit', 20);
        $url .= '&page_limit=' . $page_limit;
        $customerId = $this->customer->getId();
        $filter_data = array(
            'start' => ($page_num - 1) * $page_limit,
            'limit' => $page_limit,
            'rechargeStatus' => $rechargeStatus,
            'timeFrom' => $timeFrom,
            'timeTo' => $timeTo,
            'customer_id' => $customerId
        );
        $record = $this->model_account_balance_recharge->searchRecord($filter_data);
        $rechargeMethodsDicCategory = 'RECHARGE_METHODS';
        $rechargeApplyStatusDicCategory = 'RECHARGE_APPLY_STATUS';
        $rechargeMethodsArr = $this->model_account_balance_recharge->getDicCategory($rechargeMethodsDicCategory);
        $rechargeApplyStatusArr = $this->model_account_balance_recharge->getDicCategory($rechargeApplyStatusDicCategory);
        $total = $record['total'];
        // 处理数据
        $backUrl = $this->url->link('account/balance/recharge/tab_recharge', $url, true);
        $backUrl = encrypt($backUrl, 'E', 'RECHARGE_BACK_URL');
        $backUrl = urlencode($backUrl);
        foreach ($record['record_list'] as &$recordList) {
            $recharge_method = $recordList['recharge_method'];
            $recordList['rechargeMethod'] = $rechargeMethodsArr[$recharge_method];
            $recordList['payment_voucher_arr'] = [];
            $recordList['delete'] = 0;
            // Airwallex支付方式充值
            if ($recharge_method == 'airwallex') {
                $recordList['serial_number'] = $recordList['parent_serial_number'];
                $apply_status = $recordList['apply_status'];
                if ($apply_status == '4') {
                    $recordList['applyStatus'] = 'Completed';
                } elseif ($apply_status == '3') {
                    $recordList['applyStatus'] = 'Rejected';
                } else {
                    $recordList['applyStatus'] = $rechargeApplyStatusArr[$apply_status];
                }
                $recordList['detailUrl'] = $this->url->link('account/balance/recharge/rechargeDetail', 'serialNumber=' . $recordList['serial_number'] . '&url=' . $backUrl, true);
                $recordList['money'] = $this->currency->format($recordList['amount'], $recordList['currency']);
            } else { //P卡与电汇
                $recordList['firstname'] = $recordList['recharge_item_firstname'];
                $recordList['lastname'] = $recordList['recharge_item_lastname'];
                $recordList['country'] = $recordList['recharge_item_country'];
                $recordList['email'] = $recordList['recharge_item_email'];
                //打款凭证
                $payment_voucher_arr = $this->model_account_balance_recharge->paymentVoucher($recordList['recharge_apply_id']);
                $temp = [];
                foreach ($payment_voucher_arr as $v) {
                    $file_name = str_replace(strrchr($v['orig_name'], "."), "", $v['orig_name']);;
                    $temp['name'] = mb_substr($file_name, 0, 5) . '...' . $v['suffix'];
                    $temp['path'] = StorageCloud::root()->getImageUrl($v['path']);
                    $recordList['payment_voucher_arr'][] = $temp;
                }
                $apply_status = $recordList['status'];
                $recordList['money'] = $this->currency->format($recordList['recharge_amount'], $recordList['recharge_currency_code']);
                if ($apply_status == '4') {
                    //审核通过，显示审核过的金额
                    $recordList['money'] = $this->currency->format($recordList['actual_amount'], $recordList['account_currency_code']);
                    $recordList['applyStatus'] = 'Completed';
                } elseif ($apply_status == '3') {
                    $recordList['applyStatus'] = 'Rejected';
                } else if ($apply_status == '1') {
                    $serial_number_status = $this->model_account_balance_recharge->brotherSerialNumberNotStatus($recordList['recharge_apply_id'], $apply_status);
                    if ($serial_number_status == 0) {
                        $recordList['delete'] = 1;
                    }
                    $recordList['applyStatus'] = $rechargeApplyStatusArr[$apply_status];
                } else {
                    $recordList['applyStatus'] = $rechargeApplyStatusArr[$apply_status];
                }
                //同一批次申请的关联申请单都是Applied状态下显示查看+删除,其他状态显示查看
                $detailParams = [
                    'serial_number' => $recordList['parent_serial_number'],
                    'recharge_item_id' => $recordList['recharge_item_id'],
                    'url' => $backUrl
                ];
                $recordList['detailUrl'] = $this->url->link('account/balance/recharge/rechargeDetailOther',
                    http_build_query($detailParams), true);
            }

        }
        $data['record_list'] = $record['record_list'];
        //分页
        $total_pages = ceil($total / $page_limit);
        $data['total_pages'] = $total_pages;
        $data['page_num'] = $page_num;
        $data['total'] = $total;
        $data['page_limit'] = $page_limit;
        $data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page_num - 1) * $page_limit) + 1 : 0, ((($page_num - 1) * $page_limit) > ($total - $page_limit)) ? $total : ((($page_num - 1) * $page_limit) + $page_limit), $total, $total_pages);
        // 查询条件
        $data['TimeFrom'] = $timeFrom;
        $data['TimeTo'] = $timeTo;
        $data['status_rc'] = $rechargeStatus;
        $data['recharge_apply_status_arr'] = $rechargeApplyStatusArr;
        $this->response->setOutput($this->load->view('account/balance/list_recharge', $data));
    }

    public function airwallexAddAccount()
    {
        $json = array();
        $accountEmail = $this->request->post('airwallexAccount');
        if (empty($accountEmail)) {
            $json['success'] = 'false';
            $json['msg'] = 'Please Fill Email/Phone Number';
        } else {
            $buyerId = $this->customer_id;
            $this->load->model('account/balance/recharge');
            $this->model_account_balance_recharge->saveAirwallexBindApply($buyerId, $accountEmail);
            $json['success'] = 'true';
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * 充值请求申请
     */
    public function rechargeApply()
    {
        // 加载Model
        $this->load->model("account/balance/recharge");
        $this->load->model('localisation/currency');
        $this->load->model('account/customer');
        $this->load->language('account/balance');
        // 获取交易序列号
        $serialNumber = SerialNumberRepository::getDateSerialNumber(ServiceEnum::RECHARGE_APPLY_NO);
        // 获取请求参数
        // 充值方式
        $rechargeMethod = $this->request->post('rechargeMethod');
        // 充值金额
        $amount = floatval($this->request->post('rechargeAmount'));
        $rechargeMethodsDicCategory = 'RECHARGE_METHODS';
        $rechargeMethodsArr = $this->model_account_balance_recharge->getDicCategory($rechargeMethodsDicCategory);
        $rechargeMethodsData = [];
        $i = 1;
        foreach ($rechargeMethodsArr as $key => $method) {
            $rechargeMethodsData[$i++] = $key;
        }
        if (!array_key_exists($rechargeMethod, $rechargeMethodsData)) {
            return $this->jsonFailed($this->language->get('recharge_method_does_not_exist'));
        }
        if ($rechargeMethod == '1') {
            // Airwallex 充值信用额度
            // 检查Buyer余额是否充足
            $buyerAirwallexInfo = $this->model_account_balance_recharge->getBuyerAirwallexInfo($this->customer_id);
//            $airwallexAccountBalance = $this->customer->getAirwallexAccountBalance($buyerAirwallexInfo->airwallex_id);
//            if ($amount > $airwallexAccountBalance) {
//                $json['code'] = 101;
//                $json['msg'] = 'More than available balance';
//            }
            // 组装数据插入表oc_recharge_apply
            $rechargeApply = array(
                'serial_number' => $serialNumber,
                'recharge_method' => 'airwallex',
                'amount' => $amount,
                'currency' => $this->session->get('currency'),
                'buyer_id' => $this->customer_id,
                'apply_status' => '1'
            );
            //需要在明细表里生成一条数据
            //查询币种对应的id
            $currencies = $this->model_localisation_currency->getCurrenciesNoCache();
            $currencies = array_column($currencies, 'currency_id', 'code');
            $rechargeCurrencyId = $currencies[$rechargeApply['currency']];//充值币种ID
            $items[] = [
                'serial_number' => $serialNumber,
                'status' => 0,//airwallex订单的状态存在主表
                'buyer_id' => $this->customer_id,
                'commission' => 0,
                'recharge_amount' => $amount,
                'recharge_currency_id' => $rechargeCurrencyId,
                'recharge_exchange_rate' => 1,
                'expect_amount' => $amount,
                'account_currency_id' => $rechargeCurrencyId,
                'actual_amount' => $amount,
                'actual_exchange_rate' => 1,
            ];
            $db = $this->orm->getConnection();
            $rechargeApplyId = $db->transaction(function () use ($rechargeApply, $items) {
                //保存主订单
                $rechargeApplyId = $this->model_account_balance_recharge->saveRechargeApply($rechargeApply);
                //保存明细
                $this->model_account_balance_recharge->saveApplyItem($rechargeApplyId, $items);
                return $rechargeApplyId;
            });
            $timestamp = time();
            $bodyData = array(
                'buyerId' => $this->customer_id,
                'serialNumber' => $serialNumber,
                'amount' => $amount,
                'rechargeApplyId' => $rechargeApplyId
            );
            $postData = json_encode($bodyData);
            // 密钥，java数据验证所用
            $secret = 'whsec_u1bclo_hIq6W_Gs0zwBbpEOE0F1dE_lv';
            $signature = hash_hmac('sha256', $timestamp . $postData, $secret);
            $header = array("x-timestamp:$timestamp", "x-signature:$signature", "Content-type:application/json;charset='utf-8'", "Accept:application/json");
            $resData = post_url(URL_YZCM . '/api/airwallex/rechargeOrder', $postData, $header);
            $resData = json_decode($resData);
            if ($resData->code != 200) {
                $json['code'] = 500;
                $json['success'] = false;
                $json['msg'] = $resData->msg;
                if (strpos(json_decode($resData->msg)->code, 'Invalid amount') !== false) {
                    $json['msg'] = 'Insufficient fund!';
                }
                if (strpos(json_decode($resData->msg)->code, 'insufficient_fund') !== false) {
                    $json['msg'] = 'Insufficient fund!';
                }
                $json['data'] = $resData->data;
            } else {
                $json['code'] = 200;
                $json['success'] = true;
                $json['msg'] = $resData->msg;
                $json['data'] = $resData->data;
                $json['redirect'] = $this->url->link('account/balance/buyer_balance', 'tab=2', true);
            }
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
        } elseif (in_array($rechargeMethod, [2, 3, 4])) {
            //电汇和P卡和PingPong
            //获取数据
            $customerId = $this->customer->getId();
            $proofs = $this->request->post('proofs');//凭证数据
            $items = $this->request->post('items');//明细数据
            $rechargeCurrency = strtoupper($this->request->post('recharge_currency'));//充值币种
            //region校验
            if (empty($amount)) {
                return $this->jsonFailed($this->language->get('recharge_amount_is_required'));
            }
            if (empty($rechargeCurrency)) {
                return $this->jsonFailed($this->language->get('not_select_currency'));
            }
            //查询币种对应的id
            $currencyList = $this->model_localisation_currency->getCurrenciesNoCache();
            $currencies = array_column($currencyList, 'currency_id', 'code');
            //校验基础数据
            if (!array_key_exists($rechargeCurrency, $currencies)) {
                return $this->jsonFailed($this->language->get('recharge_currency_does_not_exist'));
            }
            $rechargeCurrencyId = $currencies[$rechargeCurrency];//充值币种ID
            if ($currencyList[$rechargeCurrency]['decimal_place'] == 0) {
                //小数后没有的必须要是整数
                if (!preg_match("/^[1-9][0-9]*$/", $amount)) {
                    return $this->jsonFailed($this->language->get('incorrect_recharge_amount'));
                }
            }
            //region校验凭证数据
            //必填
            if (empty($proofs) || !is_array($proofs)) {
                return $this->jsonFailed($this->language->get('please_upload_proofs'));
            }
            if (count($proofs) > 5) {
                //凭证最多上传5张
                return $this->jsonFailed(sprintf($this->language->get('upload_proofs_max'), 5));
            }
            //这里就不校验文件类型和大小了
            foreach ($proofs as $key => $proof) {
                if (empty($proof['path']) || empty($proof['name']) || empty($proof['suffix']) || empty($proof['size']) || empty($proof['mime_type']) || empty($proof['orig_name'])) {
                    return $this->jsonFailed(sprintf($this->language->get('proof_data_error'), $key + 1));
                }
            }
            //校验凭证数据---END
            //校验明细数据---START
            if (empty($items)) {
                return $this->jsonFailed($this->language->get('recharge_data_cannot_be_empty'));
            }
            //计算手续费-(P卡与PingPong有)
            $commission = 0;
            if ($rechargeMethod == 3 || $rechargeMethod == 4) {
                $commission = $this->model_account_balance_recharge->calculateCommission($amount, $rechargeCurrency);
            }

            //校验buyer是否有重复数据
            $buyerIds = array_column($items, 'buyer_id');
            if (count($buyerIds) != count(array_unique($buyerIds))) {
                return $this->jsonFailed($this->language->get('buyer_data_cannot_be_repeated'));
            }
            //校验其余用户是否存在
            foreach ($buyerIds as $buyerId) {
                if (empty($buyerId)) {
                    return $this->jsonFailed($this->language->get('buyer_cannot_be_empty'));
                }
            }
            $buyers = $this->model_account_customer->findCustomerNameByCustomerIds(implode(',', $buyerIds));
            if (count($buyers) != count($buyerIds)) {
                return $this->jsonFailed($this->language->get('buyer_data_error'));
            }
            //校验分摊的金额是否等于总金额
            $totalRechargeAmount = array_sum(array_column($items, 'recharge_amount'));
            if (bccomp($totalRechargeAmount, $amount, 2) !== 0) {
                return $this->jsonFailed($this->language->get('incorrect_recharge_amount'));
            }
            //取出所有汇率，下一步校验用
            $exchangeRates = $this->model_localisation_currency->getExchangeRate($rechargeCurrency);
            $isBrother = count($items) > 1;
            foreach ($items as $key => &$item) {
                $num = $key + 1;
                //这里不校验金额会不会大于总金额，因为上面校验了总金额是否相等
                if ($item['recharge_amount'] <= 0) {
                    return $this->jsonFailed($this->language->get('recharge_amount_greater_equal'));
                }
                if ($key == 0) {
                    //第一位必须是自己
                    //校验手续费相关
                    if ($item['buyer_id'] != $customerId) {
                        return $this->jsonFailed($this->language->get('recharge_must_yourself'));
                    }
                    if ($item['commission'] != $commission) {
                        return $this->jsonFailed($this->language->get('commission_error'));
                    }
                    //转换成美元计算
                    if (bccomp($item['recharge_amount'] * $exchangeRates['USD'], $commission, 2) === -1) {
                        return $this->jsonFailed($this->language->get('recharge_amount_less_commission'));
                    }
                }
                //校验币种是否正确
                $item['account_currency'] = strtoupper($item['account_currency']);
                if (!array_key_exists($item['account_currency'], $currencies)) {
                    return $this->jsonFailed(sprintf($this->language->get('items_recharge_currency_does_not_exist'), $num));
                }
                $item['account_currency_id'] = $currencies[$item['account_currency']];
                //校验充值金额是否正确
                if ($currencyList[$rechargeCurrency]['decimal_place'] == 0) {
                    //小数后没有的必须要是整数
                    if (!preg_match("/^[1-9][0-9]*$/", $item['recharge_amount'])) {
                        return $this->jsonFailed($this->language->get('incorrect_recharge_amount'));
                    }
                }
                //比对汇率，汇率一样，
                if (bccomp($item['recharge_exchange_rate'], $exchangeRates[$item['account_currency']], 2) !== 0) {
                    return $this->jsonFailed(sprintf($this->language->get('currency_exchange_rate_has_changed'), $item['account_currency']));
                }
                //预计到账金额校验
                $expectAmount = $this->model_account_balance_recharge->calculateExpectAmount($item['recharge_amount'], $rechargeCurrency,
                    $item['account_currency'], $item['commission']);

                if (bccomp($item['expect_amount'], $expectAmount['expect_amount'], 2) !== 0) {
                    return $this->jsonFailed($this->language->get('expect_amount_error'));
                }
                //生成订单编号
                $item['serial_number'] = $this->model_account_balance_recharge->getApplyItemSerialNumber();
                $item['recharge_currency_id'] = $rechargeCurrencyId;
                $item['is_brother'] = $isBrother;
            }
            //endregion校验明细数据
            //endregion校验
            //region 拼装数据
            $applyData = [
                'serial_number' => $serialNumber,
                'recharge_method' => $rechargeMethodsData[$rechargeMethod],
                'amount' => $amount,
                'currency' => $rechargeCurrency,
                'buyer_id' => $customerId,
                'apply_status' => '1'
            ];
            //endregion 拼装数据

            //保存数据---START
            $db = $this->orm->getConnection();
            $db->transaction(function () use ($customerId, $applyData, $proofs, $items) {
                //保存主订单
                $rechargeApplyId = $this->model_account_balance_recharge->saveRechargeApply($applyData);
                //保存凭证
                $this->model_account_balance_recharge->saveApplyProofs($rechargeApplyId, $customerId, $proofs);
                //保存明细
                $this->model_account_balance_recharge->saveApplyItem($rechargeApplyId, $items);
            });
            //保存数据---END
            //返回数据
            return $this->jsonSuccess($this->language->get('recharge_apply_success'));
        }
    }

    /**
     * 测试完删除
     *
     * 计算充值手续费
     *
     * @throws Exception
     */
    public function calculateCommissionDel()
    {
        $this->load->model("account/balance/recharge");
        $this->load->model('localisation/currency');
        $this->load->language('account/balance');
        $amount = $this->request->post['recharge_amount'];
        $rechargeCurrency = strtoupper($this->request->post['recharge_currency']);//充值币种
        //基础数据校验---START
        //校验金额
        if (empty($amount) || $amount <= 0) {
            $this->response->failed($this->language->get('incorrect_recharge_amount'));
        }
        //校验币种
        $currencies = $this->model_localisation_currency->getCurrenciesNoCache();
        if (!array_key_exists($rechargeCurrency, $currencies)) {
            $this->response->failed($this->language->get('recharge_currency_does_not_exist'));
        }
        //基础数据校验---END
        //获取汇率
        $commission = $this->model_account_balance_recharge->calculateCommission($amount, $rechargeCurrency);
        if ($commission === false) {
            $this->response->failed($this->language->get('commission_calculation_failed'));
        } else {
            $this->response->success(['commission' => $commission], $this->language->get('request_success'));
        }
    }

    /**
     * 测试完删除
     * 计算预计到账金额
     */
    public function calculateExpectAmountDel()
    {
        $this->load->model("account/balance/recharge");
        $this->load->model('localisation/currency');
        $this->load->language('account/balance');
        $rechargeAmount = $this->request->post['recharge_amount'];
        $rechargeCurrency = strtoupper($this->request->post['recharge_currency']);//充值币种
        $accountCurrency = strtoupper($this->request->post['account_currency']);//到账币种
        $commission = $this->request->post['commission'] ?? 0;//手续费
        //基础数据校验---START
        //校验币种
        $currencies = $this->model_localisation_currency->getCurrenciesNoCache();
        if (!array_key_exists($rechargeCurrency, $currencies)) {
            $this->response->failed($this->language->get('recharge_currency_does_not_exist'));
        }
        if (!array_key_exists($accountCurrency, $currencies)) {
            $this->response->failed($this->language->get('account_currency_does_not_exist'));
        }
        if ($commission < 0) {
            $this->response->failed($this->language->get('commission_error'));
        }
        //基础数据校验---END
        //计算预计到账金额
        $return = $this->model_account_balance_recharge->calculateExpectAmount($rechargeAmount, $rechargeCurrency,
            $accountCurrency, $commission);
        if ($return['expect_exchange_rate'] == 0 || $return['expect_amount'] == 0) {
            $this->response->failed($this->language->get('calculate_estimated_amount_failed'), $return);
        }
        $this->response->success($return, $this->language->get('request_success'));
    }

    //搜索获取充值用户的信息
    public function getRechargeBuyerList()
    {
        $this->load->model("account/customer");
        $this->load->language('account/balance');
        $keyword = trim($this->request->get('keyword'));
        $buyerId = $this->customer->getId();
        $list = $this->model_account_customer->getRechargeBuyerList($keyword, $buyerId);
        $this->response->success($list, $this->language->get('request_success'));
    }

    //上传凭证
    public function uploadRechargeProof()
    {
        $this->load->language('account/balance');
        //http referer
        //必须是account/balance/recharge页面请求的
        if (!isset($this->request->server['HTTP_REFERER']) || !strstr($this->request->server['HTTP_REFERER'], 'account/balance/recharge')) {
           return $this->jsonFailed($this->language->get('upload_error'));
        }
        //接收数据
        $file = $this->request->file('file'); // $_FILE 对象

        //判断是否成功
        if (!$file || !$file->isValid()) {
            return $this->jsonFailed($this->language->get('error_file_upload'), [], 100);
        }
        //文件大小要小于 30m
        if ((ceil($file->getSize() / 1024 / 1024) > self::PROOF_SIZE)) {
            return $this->jsonFailed($this->language->get('upload_proof_error'), [], 102);
        }

        //类型判断 后缀判断
        if (!(in_array($file->getMimeType(), self::PROOF_TYPE)) ||
            !in_array($file->getClientOriginalExtension(), self::PROOF_EXTENSION_TYPE)) {
            return $this->jsonFailed($this->language->get('upload_proof_error'), [], 102);
        }
        //处理
        $explode_file_name = explode('.', $file->getClientOriginalName());
        $real_file_ext = explode('.', $file->getClientOriginalName())[count($explode_file_name) - 1];
        $new_file_name = $this->createFileName($real_file_ext);

        $buyerId = $this->customer->getId();
        $ossPath = StorageCloud::upload()->writeFile($file, 'recharge/proofs/' . $buyerId, $new_file_name);
        //返回数据
        $successUpload = [
            'path' => $ossPath,//文件相对路径
            'name' => $new_file_name,//新的文件名
            'suffix' => $real_file_ext,//文件后缀
            'size' => $file->getSize(),//文件大小kb
            'mime_type' => $file->getClientMimeType(),//文件类型
            'orig_name' => $file->getClientOriginalName(),//文件上传时的名字
            'file_url' => StorageCloud::upload()->getUrl('recharge/proofs/' . $buyerId . '/' . $new_file_name)//文件URL
        ];
        return $this->jsonSuccess($successUpload, $this->language->get('request_success'));
    }

    //生成不同的文件名
    private function createFileName($ext)
    {
        $save_file_name = 'file_' . time() . '_' . rand(1000, 9999) . '.' . $ext;;
        $file_path = self::RECHARGE_PROOFS_FILE_PATH . '/' . ($this->customer->getId()) . '/' . $save_file_name;
        if (file_exists($file_path)) {
            $save_file_name = $this->create_file_name($ext);
        }
        return $save_file_name;
    }

    /**
     * 获取上传文件的url
     *
     * @param string $path 文件相对路径
     *
     * @return string
     */
    public function getUploadFileUrl($path)
    {
        if ($this->request->server['HTTPS']) {
            return $this->config->get('config_ssl') . $path;
        } else {
            return $this->config->get('config_url') . $path;
        }
    }

}
