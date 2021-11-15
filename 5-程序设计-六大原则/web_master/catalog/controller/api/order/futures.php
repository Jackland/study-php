<?php

use App\Helper\CountryHelper;
use App\Models\Margin\MarginAgreement;
use Carbon\Carbon;
/**
 * Class ControllerApiOrderFutures
 * @property ModelFuturesAgreement $model_futures_agreement
 * @property ModelCommonProduct $model_common_product
 * @property ModelCatalogFuturesProductLock $model_catalog_futures_product_lock
* @property ModelAccountProductQuotesMarginContract $model_account_product_quotes_margin_contract
* @property ModelAccountProductQuotesMarginAgreement $model_account_product_quotes_margin_agreement
 */
class ControllerApiOrderFutures extends ControllerApiBase{
    const LOG_TYPE = [
        1=>'buyer 无需bid 直接 Approved',
        2=>'buyer Applied -> Pending',
        3=>'seller Applied -> Approved',
        4=>'seller Applied -> Rejected',
        5=>'buyer Applied -> Canceled',
        6=>'buyer Applied -> Time Out',
        7=>'buyer Applied -> Deposit Received',
        8=>'buyer Applied -> Ignore',
        9=>'seller 提前交付',
        10=>'seller 提前取消交付 seller申请终止',
        11=>'seller 提前取消交付 buyer通过申请',
        12=>'seller 提前取消交付 buyer拒绝通过申请',
        13=>'seller 当天交付',
        14=>'待入仓 buyer申请终止',
        15=>'待入仓 buyer申请终止 seller 审核通过',
        16=>'待入仓 buyer申请终止 seller 审核不通过',
        17=>'待入仓 buyer主动终止',
        18=>'交割完成',
        19=>'已入仓 seller 取消申请',
        20=>'已入仓 buyer 取消申请',
        21=>'已入仓 buyer 取消申请 seller 审核通过',
        22=>'已入仓 buyer 取消申请  seller 审核不通过',
        23=>'seller 申诉',
        24=>'seller to be paid  过期前  协商终止协议',
        25=>'seller to be paid  过期当天  终止',
        26=>'seller to be paid  过期后  终止',
        27=>'提前交付 System同意',
        28=>'提前交付 System拒绝',
        29=>'申诉 System同意',
        30=>'申诉 System拒绝',
        31=>'系统判断time out',
        32=>'系统判断seller time out backorder',
        33=>'系统判断buyer  time out 保证金头款没付',
        34=>'系统判断buyer 期货尾款完成 退还seller保证金',
        35=>'buyer Applied -> Applied',//期货二期，buyer编辑协议
        36=>'buyer Pending -> Applied',//期货二期，buyer编辑协议
        37=>'buyer Approved -> Applied',//期货二期，buyer编辑协议
        38=>'系统判断buyer 期货尾款未完成 退还seller保证金',
        39=>'buyer Rejected -> Ignore',
        40=>'buyer to be paid  过期当天  终止',
        41=>'buyer to be paid  过期后  终止',
        42=>'已入仓 seller提交协商终止协议申请，buyer 申通通过',//对应 19 seller的操作，buyer的处理结果
        43=>'已入仓 seller提交协商终止协议申请，buyer 申通不通过',//对应 19 seller的操作，buyer的处理结果
        44=>'buyer Pending -> Canceled',//期货二期，buyer取消协议
        45=>'buyer Approved -> Canceled',//期货二期，buyer取消协议
        46=>'buyer Time out -> Ignore',
        47=>'buyer 付期货头款 -> 待入仓',
        48=>'seller Pending -> Approved',//期货其二，接上面2，Seller的处理结果。
        49=>'seller Pending -> Rejected',//期货其二，接上面2，Seller的处理结果。
        50=>'Agreement buyer支付转保证金补足款不为0 To be paid -> Completed',
        51=>'Agreement buyer支付转保证金补足款为0 To be paid -> Completed',
    ];

    const COUNTRY_INFO = [
        81 =>'DEU',
        107=>'JPN',
        222=>'GBR',
        223=>'USA',
    ];
    public function index()
    {
        $structure = [
            'agreementId' => '',
            'warehouseApplyId' => '',
            'approvalStatus' => '',
            'remark' => '',
            'updateTime'=>'',
            'operator'=>'',
            'buyerRemark'=>'',
            'receiveCustomerId'=>'',
        ];
        $this->setRequestDataStructure($structure);
        $input = $this->getParsedJson(0);
        if(isset($input['result_code'])){
            $this->response->failed($input['result_message']);
        }
        $this->load->model('futures/agreement');
        // 提前交付同意
        $post = $input;
        $agreement = $this->model_futures_agreement->getAgreementById($post['agreementId']);
        // 校验是否能够通过
        $this->checkFuturesAgreement($agreement,$post,$agreement->country_id);
        try {
            $this->orm->getConnection()->beginTransaction();
            // updateApply
            $record = [
                'update_time'=> date('Y-m-d H:i:s'),
                'status'  => $post['approvalStatus'],
            ];
            $this->model_futures_agreement->updateFutureApply($post['warehouseApplyId'],$record);
            $message = [
                'agreement_id'=> $post['agreementId'],
                'customer_id' => $post['operator'],
                'create_user_name' => $post['operator'],
                'create_time' => date('Y-m-d H:i:s'),
                'apply_id'    => $post['warehouseApplyId'],
                'message'     => $post['remark'],
            ];
            $this->model_futures_agreement->addMessage($message);
            if($post['approvalStatus'] == 1){
                $applyInfo = $this->model_futures_agreement->getLastCustomerApplyInfo($agreement->seller_id, $post['agreementId'], [$post['approvalStatus']]);
                if ($applyInfo->apply_type == 1 && array_key_exists('buyerRemark', $post)) {
                    //$applyInfo->apply_type == 1 提前交付
                    $message = [
                        'agreement_id' => $post['agreementId'],
                        'customer_id'  => $post['operator'],
                        'create_user_name' => $post['operator'],
                        'create_time' => date('Y-m-d H:i:s'),
                        'apply_id'    => $post['warehouseApplyId'],
                        'message'     => $post['buyerRemark'],//Buyer专属备注
                        'receive_customer_id' => $post['receiveCustomerId'],
                    ];
                    $this->model_futures_agreement->addMessage($message);
                }
                $data = [
                    'update_time'=> date('Y-m-d H:i:s'),
                    'delivery_status'=> 6,  // To be paid
                    'confirm_delivery_date'=> date('Y-m-d H:i:s'),
                    'delivery_date'=> date('Y-m-d H:i:s'),
                ];
                $this->load->model('common/product');
                if (
                !$this->model_common_product->checkProductQtyIsAvailable(
                    (int)$agreement->product_id,
                    (int)$agreement->num)
                ) {
                    throw new Exception('Low stock quantity.');
                }
                // 如果是转现货交割则生成现货协议和现货头款产品
                if (in_array($agreement->delivery_type, [2, 3]) && !$agreement->margin_agreement_id) {
                    // 生成现货协议
                    $margin_agreement = $this->addNewMarginAgreement($agreement,$agreement->country_id);
                    // 生成现货头款产品
                    $product_id_new = $this->model_futures_agreement->copyMarginProduct($margin_agreement, 1);
                    // 创建现货保证金记录
                    $this->addMarginProcess($margin_agreement, $product_id_new,$agreement->seller_id);

                    // 更新期货交割表
                    $data['margin_agreement_id'] = $margin_agreement['agreement_id'];
                    // delivery 更新
                    $this->model_futures_agreement->updateDelivery($post['agreementId'],$data);
                    $this->load->model('catalog/futures_product_lock');
                    $this->model_catalog_futures_product_lock->TailIn($agreement->id, $agreement->num, $agreement->id, 0);
                    $this->model_catalog_futures_product_lock->TailOut($agreement->id, $agreement->num, $agreement->id, 6);
                    $orderId = $this->model_futures_agreement->autoFutureToMarginCompleted($agreement->id);
                }else{
                    // 期货的只要校验库存已经锁库存就可以了
                    $this->load->model('catalog/futures/product_lock');
                    $this->model_catalog_futures_product_lock->TailIn($agreement->id, $agreement->num, $agreement->id, 0);
                    // 需要更新delivery表中的关于期货部分的数量
                    $this->model_futures_agreement->updateDelivery($post['agreementId'],$data);
                }

                $log = [
                    'agreement_id' => $post['agreementId'],
                    'customer_id'  => $post['operator'],
                    'type'         => 27,
                    'operator'     => $post['operator'],
                ];
            }else{
                $data = [
                    'update_time'=> date('Y-m-d H:i:s'),
                    'delivery_status'=> 1,
                ];
                $log = [
                    'agreement_id' => $post['agreementId'],
                    'customer_id'  => $post['operator'],
                    'type'         => 28,
                    'operator'     => $post['operator'],
                ];
                // delivery 更新
                $this->model_futures_agreement->updateDelivery($post['agreementId'],$data);
            }
            // 更新log
            $this->model_futures_agreement->addAgreementLog($log,
                [$agreement->agreement_status,$agreement->agreement_status],
                [$agreement->delivery_status,$data['delivery_status']]
            );
            $this->orm->getConnection()->commit();
            if (isset($orderId)) {
                $this->model_futures_agreement->autoFutureToMarginCompletedAfterCommit($orderId);
            }
            $this->response->success(['confirm_delivery_date'=>$data['confirm_delivery_date']],'success');
        } catch (Exception $e) {
            $this->log->write($this->request->post);
            $this->log->write($e);
            $this->log->write('提前交付失败！');
            $this->orm->getConnection()->rollBack();
            $this->response->failed($e->getMessage());
        }

    }

    public function checkFuturesAgreement($agreement,$post,$country_id)
    {
        if($agreement->update_time != $post['updateTime'] && $agreement->de_update_time != $post['updateTime'] ){
            return $this->response->failed('The status of agreement has been changed. Please reload the page.');
        }

        if (!$agreement || !in_array($agreement->delivery_status, [1])) {
            return $this->response->failed('The status of agreement has been changed. Please reload the page..');
        }

        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezone($country_id);
        $current_date = dateFormat($fromZone,$toZone,  date('Y-m-d H:i:s',time()));
        $expected_delivery_date = dateFormat($fromZone,$toZone, date('Y-m-d',strtotime($agreement->expected_delivery_date) ));
        $expected_delivery_date = substr($expected_delivery_date, 0, 10).' 23:59:59';
        $days = intval(ceil((strtotime($expected_delivery_date)- strtotime($current_date))/86400));
        if($days <= 0){
            return $this->response->failed('The page expires, please refresh and try again.');
        }
    }



    public function addNewMarginAgreement($future,$country_id)
    {
        $this->load->model('account/product_quotes/margin_agreement');
        $this->load->language('account/product_quotes/margin');
        $product_info = $this->model_account_product_quotes_margin_agreement->getProductInformationByProductId($future->product_id);
        if (empty($product_info)) {
            throw new \Exception($this->language->get("error_no_product"));
        }
        //        if ($product_info['quantity'] < $future->margin_apply_num) {
        //            throw new \Exception($this->language->get("error_under_stock"));
        //        }
        if ($product_info['status'] == 0 || $product_info['is_deleted'] == 1 || $product_info['buyer_flag'] == 0) {
            throw new \Exception($this->language->get("error_product_invalid"));
        }
        if (JAPAN_COUNTRY_ID == $country_id) {
            $precision = 0;
        } else {
            $precision = 2;
        }
        if($future->buyer_payment_ratio > 20){
            $margin_payment_ratio = $future->buyer_payment_ratio/100;
        }else{
            $margin_payment_ratio = MARGIN_PAYMENT_RATIO;
        }
        $agreement_id = date('Ymd') . rand(100000, 999999);
        $data = [
            'agreement_id'          => $agreement_id,
            'seller_id'             => $future->seller_id,
            'buyer_id'              => $future->buyer_id,
            'product_id'            => $product_info['product_id'],
            'clauses_id'            => 1,
            'price'                 => $future->margin_unit_price,
            'payment_ratio'         =>  $margin_payment_ratio* 100,
            'day'                   => $future->margin_days,
            'num'                   => $future->margin_apply_num,
            'money'                 => $future->margin_deposit_amount,
            'deposit_per'           => round($future->margin_unit_price * $margin_payment_ratio , $precision),
            'status'                => 3,
            'period_of_application' => 1,
            'create_user'           => $future->buyer_id,
            'create_time'           => date('Y-m-d H:i:s'),
            'update_time'           => date('Y-m-d H:i:s'),
            'program_code'          => MarginAgreement::PROGRAM_CODE_V4, //现货保证金四期
        ];
        $back['agreement_id'] = $this->model_futures_agreement->saveMarginAgreement($data);
        if ($back['agreement_id']) {
            $data_t = [
                'margin_agreement_id' => $back['agreement_id'],
                'customer_id'         => $data['buyer_id'],
                'message'             => 'Transfered to margin goods payment',
                'create_time'         => date('Y-m-d H:i:s'),
            ];
            $this->orm->table('tb_sys_margin_message')->insert($data_t);
        }
        $back['agreement_no'] = $agreement_id;
        $back['seller_id'] = $future->seller_id;
        $back['product_id'] = $future->product_id;
        $back['price_new'] = $future->margin_deposit_amount;
        return $back;
    }

    private function addMarginProcess($data, $product_id,$seller_id)
    {
        $margin_process = [
            'margin_id' =>$data['agreement_id'],
            'margin_agreement_id' => $data['agreement_no'],
            'advance_product_id' => $product_id,
            'process_status' => 1,
            'create_time' => Carbon::now(),
            'create_username' => $seller_id,
            'program_code' => 'V1.0'
        ];
        $this->load->model('account/product_quotes/margin_contract');
        $this->model_account_product_quotes_margin_contract->addMarginProcess($margin_process);
    }


}
