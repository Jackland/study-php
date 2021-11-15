<?php

namespace App\Models\Future;

use App\Models\Credit\CreditBill;
use App\Models\Product\Product;
use DateTime;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use App\Models\Message\Message;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Product\ProductLock;
use App\Models\Future\FuturesContractMarginPayRecord;

class Agreement extends Model
{
    const FUTURES_FINISH_DAY = 7;
    protected $table = 'oc_futures_margin_agreement';
    public $timestamps = false;
    protected $connection = 'mysql_proxy';
    const FUTURE_DELIVERY = 1;
    const FUTURE_BUYER_PAID = 2;
    const AGREEMENT_STATUS = [
        1   => ['name'=>'Applied', 'color'=>'#FA6400'],
        2   => ['name'=>'Pending', 'color'=>'#FA6400'],
        3   => ['name'=>'Approved', 'color'=>'#4B7902'],
        4   => ['name'=>'Rejected', 'color'=>'#D9001B'],
        5   => ['name'=>'Canceled', 'color'=>'#AAAAAA'],
        6   => ['name'=>'Time Out', 'color'=>'#AAAAAA'],
        7   => ['name'=>'Deposit Received', 'color'=>'#2D57A9'], //Sold 改为Deposit Received buyer 支付完成期货保证金头款
        8   => ['name'=>'Ignore', 'color'=>'#AAAAAA'],
    ];
    const COUNTRY_TIME_ZONES = [
        223 => 'America/Los_Angeles',
        222 => 'Europe/London',
        107 => 'Asia/Tokyo',
        81 => 'Europe/Berlin'
    ];
    const CURRENCY = [
        223 => '$%s',
        222 => '%s€',
        107 => '￥%s',
        81  => '£%s',
    ];

    const JAPAN_COUNTRY_ID = 107;

    const DELIVERY_STATUS = [
        1   => ['name'=>'Forward Delivery', 'color'=>'#FA6400'],//等待交付产品，[待入仓]
        2   => ['name'=>'Back Order', 'color'=>'#AAAAAA'],//Seller未成功交付产品
        3   => ['name'=>'Being Processed', 'color'=>'#4B7902'],//Seller成功交付产品等待Buyer选择交付方式
        4   => ['name'=>'Terminated', 'color'=>'#AAAAAA'],//Buyer未根据条款履行协议内容
        5   => ['name'=>'Processing', 'color'=>'#4B7902'],//选择交割方式待Seller处理的协议
        6   => ['name'=>'To be Paid', 'color'=>'#4B7902'],//Seller同意Buyer的交割形式，[已入仓]
        7   => ['name'=>'Being Processed', 'color'=>'#4B7902'],//Seller拒绝Buyer的交割形式
        8   => ['name'=>'Completed', 'color'=>'#333333'],//已完成交割的协议，[已交割]
    ];
    const CREDIT_TYPE = [
        1 => 'buyer期货保证金返还' ,
        2 => 'buyer期货保证金 seller 违约部分',
    ];

    public static function setTimeOutAgreement()
    {
        $time = date('Y-m-d H:i:s', strtotime('- 1 day'));
        $agreements = self::select('id', 'seller_id', 'agreement_status')
            ->whereIn('agreement_status', [1, 2, 3])
            ->where('update_time', '<', $time)
            ->where('contract_id','=',0) // 期货老版数据
            ->get();
        if ($agreements->isEmpty()) {
            return;
        }
        $agreement_ids = $agreements->pluck('id')->toArray();
        try {
            \DB::beginTransaction();
            // 设置协议为超时状态
            self::whereIn('id', $agreement_ids)->update(['agreement_status' => 6]);
            // 找到集合中agreement_status为3,即seller同意协议.
            $approved_ids = $agreements->where('agreement_status', 3)->toArray();
            if ($approved_ids) {
                // 此时已生成期货头款商品.
                // 找到协议对应的定金头款产品id
                $advance_product_ids = MarginProcess::getMarginProductIds($agreement_ids);
                // Product List中将此产品置为下架以及废弃状态
                Product::updateByProductIds($advance_product_ids, ['is_deleted' => 1, 'status' => 0]);
                // 此时seller期货保证金已付
                // 返还seller保证金
                MarginPayRecord::backFutureMargin($approved_ids);
            }
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error($e);
        }
    }

    /**
     * [setTimeOutFutureAgreement description] 期货二期更改time out 需要发站内信
     */
    public static function setTimeOutFutureAgreement()
    {
        $time = date('Y-m-d H:i:s', strtotime('- 1 day'));
        $agreements = self::select('id', 'seller_id', 'agreement_status')
            ->whereIn('agreement_status', [1, 2, 3])
            ->where('update_time', '<', $time)
            ->where('contract_id','<>',0) // 期货老版数据
            ->get();
        if ($agreements->isEmpty()) {
            return;
        }
        $agreement_ids = $agreements->pluck('id')->toArray();
        try {
            \DB::beginTransaction();
            // 找到集合中agreement_status为3,即seller同意协议.
            $approved_ids = $agreements->where('agreement_status', 3)->toArray();
            if ($approved_ids) {
                // 此时已生成期货头款商品.
                // 找到协议对应的定金头款产品id
                $advance_product_ids = MarginProcess::getMarginProductIds($agreement_ids);
                // Product List中将此产品置为下架以及废弃状态
                Product::updateByProductIds($advance_product_ids, ['is_deleted' => 1, 'status' => 0]);
                // 此时seller期货保证金已付
                self::updateSellerPayRecord($approved_ids);
            }
            // 发送站内信 buyer seller 部分
            // log记录
            self::updateSellerInfo($agreement_ids);
            // 设置协议为超时状态
            self::whereIn('id', $agreement_ids)->update([
                'agreement_status' => 6,
                'is_lock'=> 0
            ]);
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error($e);
        }
    }

    public static function getAgreementInfo($ids)
    {
        return \DB::table('oc_futures_margin_agreement as a')
            ->select('a.*','d.*','a.id','a.update_time', 'd.id as delivery_id','d.update_time as de_update_time','p.sku','p.mpn')
            ->addSelect(['bc.customer_group_id','bc.country_id','bc.nickname','bc.user_number'])
            ->leftJoin('oc_futures_margin_delivery as d','a.id','d.agreement_id')
            ->leftJoin('oc_customer as bc','a.buyer_id' ,'=', 'bc.customer_id')
            ->leftJoin('oc_product as p','a.product_id' ,'=', 'p.product_id')
            ->whereIn('a.id', $ids)
            ->get();
    }


    /**
     * [updateSellerInfo description] 需要发站内信和生成log
     * @param $ids
     */
    public static function updateSellerInfo($ids)
    {
        $collection = self::getAgreementInfo($ids);
        foreach($collection as $key => $value){
            //发送站内信
            // 分别给buyer 和 seller 发
            $condition_seller['from'] = 0;
            $condition_seller['to'] = $value->seller_id;
            $condition_seller['country_id'] = $value->country_id;
            $condition_seller['status'] = null;
            $condition_seller['communication_type'] = 1;
            $condition_seller['info'] = $value;
            self::addFuturesAgreementCommunication($value->id,1,$condition_seller);
            $condition_buyer['from'] = 0;
            $condition_buyer['to'] = $value->buyer_id;
            $condition_buyer['country_id'] = $value->country_id;
            $condition_buyer['status'] = null;
            $condition_buyer['communication_type'] = 2;
            $condition_buyer['info'] = $value;
            self::addFuturesAgreementCommunication($value->id,2,$condition_buyer);
            //生成log
            $log['info'] = [
                'agreement_id' => $value->id,
                'customer_id' =>  0,
                'type' => 31,
                'operator' =>  'System',
            ];
            $log['agreement_status'] = [$value->agreement_status, 6];
            $log['delivery_status'] = [0,0];
            self::addAgreementLog($log['info'],
                $log['agreement_status'],
                $log['delivery_status']
            );
        }
    }


    public static function updateSellerPayRecord($ids)
    {
        $collection = \DB::table('oc_futures_margin_delivery as d')
            ->leftJoin('oc_futures_margin_agreement as a', 'a.id', '=', 'd.agreement_id')
            ->leftJoin('oc_customer as c', 'c.customer_id', 'a.buyer_id')
            ->whereIn('a.id', $ids)
            ->where('a.is_bid', 1)
            ->select(
                'a.id',
                'a.seller_id',
                'a.buyer_id',
                'a.unit_price',
                'a.seller_payment_ratio',
                'a.num',
                'a.contract_id',
                'c.country_id',
                'a.agreement_status'
            )
            ->get();
        $futuresContractMarginPayRecord = new FuturesContractMarginPayRecord();
        $operator = 'System';
        foreach($collection as $key => $value){
            // 同意的需要返还seller合约保证金
            $contract_info = Contract::firstPayRecordContracts($value->seller_id,[$value->contract_id]);
            if($value->agreement_status == 3 && $contract_info[0]['status'] == 4){
                $point = $value->country_id == self::JAPAN_COUNTRY_ID ? 0 : 2;
                $amount = round($value->unit_price * $value->seller_payment_ratio / 100, $point) * $value->num;
                //AgreementMargin::sellerBackFutureMargin($value->seller_id,$value->id,$amount,$contract_info[0]['pay_type']);
                if ($amount > 0 && $contract_info[0]['pay_type'] == 1){
                    CreditBill::addCreditBill($value->seller_id, $amount, 2);
                }

                //更新期货合约余额 判断协议是否使用了合约中抵押物的金额
                $collateralBalance = AgreementMargin::updateContractBalance($value->contract_id, $amount, $contract_info[0]['pay_type']);

                if ($collateralBalance > 0) {
                    if ($amount - $collateralBalance > 0) {
                        $futuresContractMarginPayRecord->sellerBackFutureContractMargin(
                            $value->seller_id,
                            $value->contract_id,
                            $contract_info[0]['pay_type'],
                            $amount - $collateralBalance,
                            $operator
                        );
                    }

                    $futuresContractMarginPayRecord->sellerBackFutureContractMargin(
                        $value->seller_id,
                        $value->contract_id,
                        FuturesContractMarginPayRecord::SELLER_COLLATERAL,
                        $collateralBalance,
                        $operator
                    );
                } else {
                    $futuresContractMarginPayRecord->sellerBackFutureContractMargin(
                        $value->seller_id,
                        $value->contract_id,
                        $contract_info[0]['pay_type'],
                        $amount,
                        $operator
                    );
                }
            }
        }
    }

    public static function sendDailyMessageByCountryId($country_id,$type)
    {
        switch ($type){
            case 1:
                $time = date('Y-m-d H:i:s', strtotime('- 7 day'));
                $current_time = date('Y-m-d H:i:s');
                $collection = \DB::table('oc_futures_margin_delivery as d')
                    ->leftJoin('oc_futures_margin_agreement as a', 'a.id', '=', 'd.agreement_id')
                    ->where([
                        ['bc.country_id','=',$country_id],
                        ['a.agreement_status','=',7],
                        ['d.delivery_status','=',1],
                        ['a.expected_delivery_date','>=',$time],
                        ['a.contract_id','<>',0],  // 老协议不发站内信
                    ])
                    ->select('a.*','d.*','a.id','a.update_time', 'd.id as delivery_id','d.update_time as de_update_time','p.sku','p.mpn')
                    ->addSelect(['bc.customer_group_id','bc.country_id','bc.nickname','bc.user_number'])
                    ->leftJoin('oc_customer as bc','a.buyer_id' ,'=', 'bc.customer_id')
                    ->leftJoin('oc_product as p','a.product_id' ,'=', 'p.product_id')
                    ->get();
                foreach($collection as $key => $value){
                    $days = self::getLeftDay($value->expected_delivery_date,$value->country_id);
                    if($days > 0 && $days <= 7){
                        $condition_seller['from'] = 0;
                        $condition_seller['to'] = $value->seller_id;
                        $condition_seller['country_id'] = $value->country_id;
                        $condition_seller['status'] = null;
                        $condition_seller['communication_type'] = 5;
                        $condition_seller['info'] = $value;
                        self::addFuturesAgreementCommunication($value->id,5,$condition_seller);
                    }
                }
                break;
            case 2:
                $time = date('Y-m-d H:i:s', strtotime('- 7 day'));
                $yesterday_timestamp = date('Y-m-d H:i:s',strtotime('- 1 day'));
                $collection = \DB::table('oc_futures_margin_delivery as d')
                    ->leftJoin('oc_futures_margin_agreement as a', 'a.id', '=', 'd.agreement_id')
                    ->where([
                        ['bc.country_id','=',$country_id],
                        ['a.agreement_status','=',7],
                        ['d.delivery_status','=',6],
                        ['d.delivery_type','=',1],
                        ['d.confirm_delivery_date','>=',$time],
                        ['d.confirm_delivery_date','<=',$yesterday_timestamp],
                        ['a.contract_id','<>',0],  // 老协议不发站内信
                    ])
                    ->select('a.*','d.*','a.id','a.update_time', 'd.id as delivery_id','d.update_time as de_update_time','p.sku','p.mpn')
                    ->addSelect(['bc.customer_group_id','bc.country_id','bc.nickname','bc.user_number'])
                    ->leftJoin('oc_customer as bc','a.buyer_id' ,'=', 'bc.customer_id')
                    ->leftJoin('oc_product as p','a.product_id' ,'=', 'p.product_id')
                    ->get();
                foreach($collection as $key => $value){
                    $days = self::getConfirmLeftDay($value->confirm_delivery_date,$value->country_id);
                    if($days > 1){
                        $condition_seller['from'] = 0;
                        $condition_seller['to'] = $value->buyer_id;
                        $condition_seller['country_id'] = $value->country_id;
                        $condition_seller['status'] = null;
                        $condition_seller['communication_type'] = 6;
                        $condition_seller['info'] = $value;
                        self::addFuturesAgreementCommunication($value->id,6,$condition_seller);
                    }
                }
                break;
        }

    }

    public static function addAgreementLog($data,$agreement_status,$delivery_status)
    {
        $delivery_status_pre = $delivery_status[0] == 0 ? 'N/A':self::DELIVERY_STATUS[$delivery_status[0]]['name'];
        $delivery_status_suf = $delivery_status[1] == 0 ? 'N/A':self::DELIVERY_STATUS[$delivery_status[1]]['name'];
        $data['content'] = json_encode([
            'delivery_status' => $delivery_status_pre .' -> '. $delivery_status_suf,
            'agreement_status'=> self::AGREEMENT_STATUS[$agreement_status[0]]['name'] .' -> '. self::AGREEMENT_STATUS[$agreement_status[1]]['name'],
        ]);
        \DB::table('oc_futures_agreement_log')->insert($data);
    }

    public static function addFuturesAgreementCommunication($agreement_id,$type,$condition)
    {
        $ret = self::setTemplateOfCommunication(...func_get_args());
        $message_sender = new Message();
        $message_sender->addSystemMessage('bid_futures',$ret['subject'],$ret['message'],$ret['received_id']);
    }

    public static function setTemplateOfCommunication($agreement_id,$type,$condition)
    {
        $subject = '';
        $message = '';
        $received_id = $condition['to'];
        if ($condition['info']->country_id == self::JAPAN_COUNTRY_ID){
            $format = '%d';
            $precision = 0;
        }else{
            $format = '%.2f';
            $precision = 2;
        }
        $condition['info']->screenname = \DB::table('oc_customerpartner_to_customer')
            ->where('customer_id',$condition['info']->seller_id)->value('screenname');
        $contract_no = \DB::table('oc_futures_contract')
            ->where('id',$condition['info']->contract_id)->value('contract_no');
        $message_header = '<table  border="0" cellspacing="0" cellpadding="0">';
        $message_seller_agreement = '<tr>
                                <th align="left">Future Goods Agreement ID:&nbsp;</th>
                                <td style="max-width: 600px">
                                    <a target="_blank"
                                         href="'. config('app.b2b_url') . 'account/product_quotes/futures/sellerFuturesBidDetail&id='. $agreement_id . '">'
                                    .$condition['info']->agreement_no.
                                    '</a>
                                </td></tr>';
        $message_seller_contract = '<tr>
                                <th align="left">Future Goods Contract ID:&nbsp;</th>
                                <td style="max-width: 600px">
                                     <a target="_blank"
                                        href="' . config('app.b2b_url').'account/customerpartner/future/contract/tab&id=' .$condition['info']->contract_id . '">'
                                    .$contract_no.
                                    '</a>
                                 </td></tr>';
        $message_buyer_name = '<tr>
                                <th align="left">Name:&nbsp;</th>
                                <td style="max-width: 600px">
                                 '.$condition['info']->nickname.'('.$condition['info']->user_number.')
                                </td></tr>';
        $message_item_code_mpn = '<tr>
                                <th align="left">Item Code/MPN:&nbsp;</th>
                                <td style="max-width: 600px">
                                 '. $condition['info']->sku.'/'.$condition['info']->mpn.'
                                </td></tr>';
        $message_delivery_date = '<tr>
                                <th align="left">Delivery Date:&nbsp;</th>
                                <td style="max-width: 600px">
                                 '. $condition['info']->expected_delivery_date.'
                                </td></tr>';
        $message_buyer_agreement = '<tr>
                                <th align="left">Future Goods Agreement ID:&nbsp;</th>
                                <td style="max-width: 600px">
                                    <a target="_blank"
                                         href="'. config('app.b2b_url') . 'account/product_quotes/futures/buyerFuturesBidDetail&id='. $agreement_id . '">'
                                    .$condition['info']->agreement_no.
                                    '</a>
                                </td></tr>';
        $message_store = '<tr>
                                <th align="left">Store:&nbsp;</th>
                                <td style="max-width: 600px">
                                     <a target="_blank"
                                             href="' . config('app.b2b_url') .'customerpartner/profile&id=' .$condition['info']->seller_id . '">'
                                    .$condition['info']->screenname.
                                    '</a>
                                 </td></tr>';
        $message_item_code = '<tr>
                                <th align="left">Item Code:&nbsp;</th>
                                <td style="max-width: 600px">
                                 '. $condition['info']->sku.'
                                </td></tr>';
        $message_agreement_num = '<tr>
                                <th align="left">Agreement Quantity:&nbsp;</th>
                                <td style="max-width: 600px">
                                 '. $condition['info']->num.'
                                </td></tr>';
        $message_agreement_unit_price = '<tr>
                                <th align="left">Agreement Price:&nbsp;</th>
                                <td style="max-width: 600px">
                                 '.sprintf(self::CURRENCY[$condition['info']->country_id],sprintf($format, round($condition['info']->unit_price, $precision))) .'
                                </td></tr>';
        $message_item_code = '<tr>
                                <th align="left">Item Code:&nbsp;</th>
                                <td style="max-width: 600px">
                                 '. $condition['info']->sku.'
                                </td></tr>';
        $message_footer = '</table>';
        switch ($type){
            case 1:
                // 超时未处理期货协议，向Buyer&Seller发送期货超时的站内信（Bid）
                // Applied、Pending、Approved24小时未处理 协议状态变为超时 发送给seller
                //$subject .= '期货协议ID为'.$condition['info']->agreement_no.'的申请已超时';
                $subject .= 'The request of the future goods agreement ('.$condition['info']->agreement_no.') timed out. ';
                $message .= $message_header;
                $message .= $message_seller_agreement;
                $message .= $message_seller_contract;
                $message .= $message_buyer_name;
                $message .= $message_item_code_mpn;
                $message .= $message_delivery_date;
                $message .= $message_footer;
                break;
            case 2:
                // 超时未处理期货协议，向Buyer&Seller发送期货超时的站内信（Bid）
                // Applied、Pending、Approved24小时未处理 协议状态变为超时 发送给buyer
                //$subject .= '期货协议ID为'.$condition['info']->agreement_no.'的申请已超时';
                $subject .= 'The request of the future goods agreement ('.$condition['info']->agreement_no.') timed out. ';
                $message .= $message_header;
                $message .= $message_buyer_agreement;
                $message .= $message_store;
                $message .= $message_item_code;
                $message .= $message_delivery_date;
                $message .= $message_footer;
                break;
            case 3:
                // 交货日期后，平台判定Seller库存不足无法交付导致协议违约
                //$subject .= '期货协议ID为'.$condition['info']->agreement_no.'的期货协议交付失败';
                $subject .= 'The delivery of future goods agreement ('.$condition['info']->agreement_no.') has been failed.';
                $message .= $message_header;
                $message .=  $message_buyer_agreement;
                $message .= $message_store;
                $message .= $message_delivery_date;
                $message .= '<tr>
                                <th align="left">Delivery Result:&nbsp;</th>
                                <td style="max-width: 600px">
                                 Delivery Failed
                                </td></tr>';
                $message .= '<tr>
                                <th align="left">Fail Reason:&nbsp;</th>
                                <td style="max-width: 600px">
                                 Seller failed to deliver due to timeout 
                                </td></tr>';
                $message .= $message_footer;
                break;
            case 4:
                // 交货日期后，平台判定Seller库存不足无法交付导致协议违约
                //$subject .= '期货协议ID为'.$condition['info']->agreement_no.'的期货协议交付失败';
                $subject .= 'The delivery of future goods agreement ('.$condition['info']->agreement_no.') has been failed.';
                $message .= $message_header;
                $message .= $message_seller_agreement;
                $message .= $message_buyer_name;
                $message .= $message_item_code_mpn;
                $message .= $message_delivery_date;
                $message .= '<tr>
                                <th align="left">Delivery Result:&nbsp;</th>
                                <td style="max-width: 600px">
                                 Delivery Failed
                                </td></tr>';
                $message .= '<tr>
                                <th align="left">Fail Reason:&nbsp;</th>
                                <td style="max-width: 600px">
                                 Seller failed to deliver due to timeout 
                                </td></tr>';
                $message .= $message_footer;
                break;
            case 5:
                //交货日期开始倒计时直至交货成功/失败，以天为单位，每天一封
                //$subject .= '请及时交付期货协议ID为'.$condition['info']->agreement_no.'的期货协议';
                $subject .= 'Please complete the delivery of future goods agreement ('.$condition['info']->agreement_no.') in time. ';
                $message .= $message_header;
                $message .= $message_seller_agreement;
                $message .= $message_buyer_name;
                $message .= $message_item_code_mpn;
                $message .= $message_delivery_date;
                $message .= '<tr>
                                <th align="left">Note:&nbsp;</th>
                                <td style="max-width: 600px">
                                 <span style="color:red;font-weight: bold">The delivery date of the future goods agreement will be due soon, please complete the delivery in time. For the agreement not delivered in time, the seller will be flagged for breaching the agreement. If that happens Seller\'s collateral in this agreement will be paid to the Buyer, and the Marketplace will charge relevant Marketplace Fee. </span>
                                </td></tr>';
                $message .= $message_footer;
                break;
            case 6:
                // 交割方式为支付尾款，Buyer开始支付尾款开始直至Buyer未履约前一天，以天未单位，每天一封
                //$subject .= '请及时交付期货协议ID为'.$condition['info']->agreement_no.'的期货协议';
                $subject .= 'Please complete the settlement of  future goods agreement ('.$condition['info']->agreement_no.') in time.';
                $message .= $message_header;
                $message .= $message_buyer_agreement;
                $message .= $message_store;
                $message .= $message_item_code;
                $message .= $message_delivery_date;
                $message .= '<tr>
                                <th align="left">Note:&nbsp;</th>
                                <td style="max-width: 600px">
                                 Buyer has to pay the due amount of future goods products within 7 natural days after the delivery is completed. If the due amount isn\'t paid in time, Buyer will be flagged for breaching the agreement. 
                                </td></tr>';
                $message .= $message_footer;
                break;
            case 7:
                //交货后，平台判定Buyer协议未履约
                //$subject .= '请及时交付期货协议ID为'.$condition['info']->agreement_no.'的期货协议';
                $subject .= 'Please complete the delivery of future goods agreement ('.$condition['info']->agreement_no.') in time. ';
                $message .= $message_header;
                $message .= $message_buyer_agreement;
                $message .= $message_store;
                $message .= $message_item_code;
                $message .= $message_delivery_date;
                $message .= '<tr>
                                <th align="left">Note:&nbsp;</th>
                                <td style="max-width: 600px">
                                    If the due amount is not paid within 7 natural days after the delivery date, then Buyer\'s collateral of remaining products will be paid to the Seller.
Buyer has to pay the difference between the future goods collateral and margin deposit for transferring to margin bid . If the difference is not paid with 24 hours after the delivery date, then Buyer\'s collateral of agreement quantity will be paid to the Seller.
                                </td></tr>';
                $message .= $message_footer;
                break;
            case 8:
                // seller 提交的申请一直没有同意直到最后一天需要被置为拒绝
                $subject .= 'The request to deliver ahead of time for future goods agreement ('.$condition['info']->agreement_no.') has been rejected. ';
                $message .= $message_header;
                $message .= $message_seller_agreement;
                $message .= $message_seller_contract;
                $message .= $message_buyer_name;
                $message .= $message_item_code_mpn;
                $message .= $message_delivery_date;
                $message .= $message_agreement_num;
                $message .= $message_agreement_unit_price;

        }
        $ret['subject'] = $subject;
        $ret['message'] = $message;
        $ret['received_id']  = $received_id;
        return $ret;

    }

    /**
     * [getLeftDay description]
     * @param string $expected_delivery_date 待入仓之前获取天数
     * @param int $country_id
     * @return int
     */
    public static function getLeftDay($expected_delivery_date,$country_id){
        $start_time = date('Y-m-d H:i:s',time());
        $current_date = self::dateFormat(self::COUNTRY_TIME_ZONES[223],self::COUNTRY_TIME_ZONES[$country_id],  $start_time);
        $expected_delivery_date = substr($expected_delivery_date, 0, 10).' 23:59:59';//因为合约表的交货日期，存的是对应国别的日期，所以不用转换时区。因为协议表预计交货日期，存的是对应国别的日期，所以不用转换时区。
        $days = intval(ceil((strtotime($expected_delivery_date)- strtotime($current_date))/86400));
        if($days <= 0){
            return 0;
        }else{
            return $days;
        }
    }

    public static  function getConfirmLeftDay($confirm_delivery_date,$country_id)
    {
        // 当前时间太平洋时间转成当前国别的时间
        $current_date = self::dateFormat(self::COUNTRY_TIME_ZONES[223],self::COUNTRY_TIME_ZONES[$country_id],  date('Y-m-d H:i:s'));
        $confirm_delivery_date = self::dateFormat(self::COUNTRY_TIME_ZONES[223],self::COUNTRY_TIME_ZONES[$country_id],  date('Y-m-d H:i:s',strtotime($confirm_delivery_date) + self::FUTURES_FINISH_DAY*86400));
        $confirm_delivery_date = substr($confirm_delivery_date, 0, 10).' 23:59:59';
        $days = intval(ceil((strtotime($confirm_delivery_date)- strtotime($current_date))/86400));
        //
        if($days > self::FUTURES_FINISH_DAY){
            return self::FUTURES_FINISH_DAY;
        }elseif($days <= 0){
            return 0;
        }else{
            return $days;
        }

    }

    /**
     * [FutureAgreementCompletedPayRecord description] 查找已经completed还没有退还seller的
     */
    public static function FutureAgreementCompletedPayRecord()
    {
        // 查找 大于7天
        // 当前国别23：59：59 + 7天
        $time = date('Y-m-d H:i:s', strtotime('- 7 day'));
        $collection = \DB::table('oc_futures_margin_agreement as a')
            ->select('a.*','d.*','a.id','a.update_time', 'd.id as delivery_id','d.update_time as de_update_time','p.sku','p.mpn')
            ->addSelect(['bc.customer_group_id','bc.country_id','bc.nickname','bc.user_number'])
            ->leftJoin('oc_futures_margin_delivery as d','a.id','d.agreement_id')
            ->leftJoin('oc_customer as bc','a.buyer_id' ,'=', 'bc.customer_id')
            ->leftJoin('oc_product as p','a.product_id' ,'=', 'p.product_id')
            ->whereNotIn('a.id',function (Builder $query){
                $query->select('pr.agreement_id')->from('oc_futures_agreement_margin_pay_record as pr')->where([
                    'pr.flow_type' => 2
                ]);
            })
            ->where([
                ['d.delivery_status','=',8],
                ['a.agreement_status','=', 7],
                ['a.contract_id','<>',0 ],
                ['d.confirm_delivery_date', '<', $time]
            ])
            ->groupBy('a.id')
            ->get();
        try {
            \DB::beginTransaction();
            $ids = [];
            foreach($collection as $key => $value){
                // 验证有没有过期
                $days = self::getConfirmLeftDay($value->confirm_delivery_date,$value->country_id);
                if($days <= 0){
                    $ids[] = $value->id;
                    // 记录log
                    $log['info'] = [
                        'agreement_id' => $value->id,
                        'customer_id' =>  0,
                        'type' => 34, //
                        'operator' =>  'System',
                    ];
                    $log['agreement_status'] = [$value->agreement_status, $value->agreement_status];
                    $log['delivery_status'] = [$value->delivery_status,4];
                    Agreement::addAgreementLog($log['info'],
                        $log['agreement_status'],
                        $log['delivery_status']
                    );
                    // 更新update time
                    // 2.修改状态为seller
                    // 退钱
                    MarginDelivery::chargeMoney(3,$value);
                }

            }
            if($ids){
                \DB::table('oc_futures_margin_delivery')->whereIn('agreement_id', $ids)
                    ->update(['update_time' => date('Y-m-d H:i:s')]);
            }
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error($e);
        }
    }

    public static function FutureAgreementUncompletedPayRecord()
    {
        // 查找 大于7天
        $time = date('Y-m-d H:i:s', strtotime('- 7 day'));
        $build = \DB::table('oc_futures_margin_agreement as a')
            ->select('a.*','d.*','a.id','a.update_time', 'd.id as delivery_id','d.update_time as de_update_time','p.sku','p.mpn')
            ->addSelect(['bc.customer_group_id','bc.country_id','bc.nickname','bc.user_number'])
            ->leftJoin('oc_futures_margin_delivery as d','a.id','d.agreement_id')
            ->leftJoin('oc_customer as bc','a.buyer_id' ,'=', 'bc.customer_id')
            ->leftJoin('oc_product as p','a.product_id' ,'=', 'p.product_id')
            ->where([
                ['d.delivery_status','=',6],
                ['a.agreement_status','=', 7],
                ['a.contract_id','<>',0 ],
                ['d.confirm_delivery_date', '<', $time]
            ]);
        $collection = $build->get();
        $ProductLock = new FuturesProductLock();
        try {
            \DB::beginTransaction();
            $ids = [];
            foreach($collection as $key => $value){
                // 验证有没有过期
                $days = self::getConfirmLeftDay($value->confirm_delivery_date,$value->country_id);
                if($days <= 0){
                    // 记录log
                    $ids[] = $value->id;
                    $log['info'] = [
                        'agreement_id' => $value->id,
                        'customer_id' =>  -1,
                        'type' => 38, //
                        'operator' =>  'System',
                    ];
                    $log['agreement_status'] = [$value->agreement_status, $value->agreement_status];
                    $log['delivery_status'] = [$value->delivery_status,4];
                    Agreement::addAgreementLog($log['info'],
                        $log['agreement_status'],
                        $log['delivery_status']
                    );
                    // 更新update time
                    $condition_buyer['from'] = 0;
                    $condition_buyer['to'] = $value->seller_id;
                    $condition_buyer['country_id'] = $value->country_id;
                    $condition_buyer['status'] = null;
                    $condition_buyer['communication_type'] = 7;
                    $condition_buyer['info'] = $value;
                    self::addFuturesAgreementCommunication($value->id,7,$condition_buyer);
                    // 2.修改状态为buyer违约
                    // 退钱
                    MarginDelivery::chargeMoney(3,$value);
                }

            }
            if($ids){
                // 期货尾款未完成的库存需要退还
                // 2. 释放库存
                $locked_agreement = ProductLock::getProductLockByAgreementIds($ids)->keyBy('agreement_id')->toArray();
                foreach ($ids as $id) {
                    if (isset($locked_agreement[$id]['qty'])) {
                        $qty = $locked_agreement[$id]['qty'] / $locked_agreement[$id]['set_qty'];
                        $ProductLock->TailOut($id, $qty, $id, FuturesProductLock::LOCK_TAIL_TIMEOUT);
                    }
                }
                \DB::table('oc_futures_margin_delivery')->whereIn('agreement_id', $ids)
                    ->update(
                        [
                            'update_time' => date('Y-m-d H:i:s'),
                            'delivery_status'=> 4
                        ]
                    );
            }
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error($e);
        }
    }

    public static function addFutureApply($data)
    {
        return \DB::table('oc_futures_agreement_apply')
            ->insertGetId($data);
    }

    /**
     * 时区转换
     *
     * 支持： 'Y-m-d H:i:s' 'Y-m-d H' 这两种格式转换
     *
     * @param string $from_zone 原始时区
     * @param string $to_zone 目标时区
     * @param string $input_date 待转换的日期字符串
     * @param string $output_format 输出的日期格式
     * @param string $input_format 输入的日期格式
     * @return string
     * @since 适配不同的时间格式 2020-3-27 17:34:50 by Lester.You
     */
    public static function dateFormat($from_zone, $to_zone, $input_date, $output_format = 'Y-m-d H:i:s', $input_format = '')
    {
        $analysis_formats = [
            'Y-m-d H:i:s',
            'Y-m-d H'
        ];

        !empty($input_format) && $analysis_formats = array_unique(array_merge([$input_format], $analysis_formats));

        $datetime = false;
        foreach ($analysis_formats as $analysis_format) {
            if ($datetime = DateTime::createFromFormat($analysis_format, $input_date, new DateTimeZone($from_zone))) {
                break;
            }
        }

        $output_date = $input_date;
        if ($datetime) {
            $output_date = $datetime->setTimezone(new DateTimeZone($to_zone))
                ->format($output_format);
        }
        return $output_date;
    }

    public static function addCreditRecord($agreement_info,$amount,$type = 1)
    {
        $line_of_credit = \DB::table( 'oc_customer')
            ->where('customer_id', $agreement_info->buyer_id)->value('line_of_credit');
        $line_of_credit = round($line_of_credit, 4);
        $new_line_of_credit = round($line_of_credit + $amount, 4);
        if(in_array($type,[1,2])){
            $serialNumber = date('YmdHis').str_pad(random_int(0,99999),5,'0',STR_PAD_LEFT);
            $mapInsert = [
                'serial_number' => $serialNumber,
                'customer_id' => $agreement_info->buyer_id,
                'old_line_of_credit' => $line_of_credit,
                'new_line_of_credit' => $new_line_of_credit,
                'date_added' => date('Y-m-d H:i:s'),
                'operator_id' => $agreement_info->seller_id,
                'type_id' => 10,
                'memo' => self::CREDIT_TYPE[$type], //根据type 不同产生的
                'header_id' => $agreement_info->id
            ];
            \DB::table('tb_sys_credit_line_amendment_record')->insertGetId($mapInsert);
            \DB::table('oc_customer')
                ->where('customer_id', $agreement_info->buyer_id)->update(['line_of_credit' => $new_line_of_credit]);
        }
    }



}