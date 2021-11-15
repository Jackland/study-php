<?php

namespace App\Console\Commands;

use App\Models\Message\Message;
use Enqueue\AmqpLib\Tests\Spec\AmqpBasicConsumeBreakOnFalseTest;
use Illuminate\Console\Command;
use App\Models\Statistics\RebateModel;

class Rebate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statistic:rebate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'rebate脚本:1.超时状态，2.到期7天，3.rebate是否完成';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //1.超时状态
        //2.到期7天
        //3.rebate是否完成
        echo date("Y-m-d H:i:s",time()).'rebate... start' . PHP_EOL;
        $this->agreement_timeout();
        $this->agreement_expire_7_day();
        $this->check_agreement_full();
        echo date("Y-m-d H:i:s",time()).'rebate... end' . PHP_EOL;
    }

    //修改超时状态
    public function agreement_timeout()
    {
        $agreement_timeout_info = RebateModel::get_agreement_timeout();
        if ($agreement_timeout_info) {
            $agreement_timeout_id_list = array_column($agreement_timeout_info, 'id');
            $set_res = RebateModel::set_agreement_timeout($agreement_timeout_id_list);
            if ($set_res) {    //修改成功
                //1 输出日志
                foreach ($agreement_timeout_info as $K => $v) {
                    echo date("Y-m-d H:i:s",time()).'rebate timeout-- id:' . $v['id'] . ',code:' . $v['agreement_code'] . ' timeout' . PHP_EOL;
                }
                //2 发送站内信
                //？？？
            }
        }
    }

    //判断协议是否还有七天到期
    public function agreement_expire_7_day()
    {
        $agreement_expire = RebateModel::get_agreement_expire_7_day();
        if ($agreement_expire) {
            $agreement_expire_id_list = array_column($agreement_expire, 'id');
            $set_expire_status = RebateModel::set_expire_status($agreement_expire_id_list);
            if ($set_expire_status) {   //修改成功
                //1 输出日志
                foreach ($agreement_expire as $K => $v) {
                    echo date("Y-m-d H:i:s",time()).'rebate expire 7 days-- id:' . $v['id'] . ',code:' . $v['agreement_code'] . ' expire after 7 days' . PHP_EOL;
                }
                //2 发送站内信

            }
        }
    }

    //协议到期--判断是否完成
    public function check_agreement_full()
    {
        //获取需要检查的agreement
        $agreement_list = RebateModel::get_already_expire_agreement();
        if ($agreement_list) {
            //检验是否完成
            $agreement_id_list = array_column($agreement_list, 'id');
            $sell_num = RebateModel::seller_num($agreement_id_list);
            $ful = array(); //存储完成的id
            $fail = array();  //存储失败的id
            $agreement_list=array_combine(array_column($agreement_list,'id'),$agreement_list);
            foreach ($agreement_list as $k => $v) {
                if (isset($sell_num[$v['id']]) && $v['qty'] <= $sell_num[$v['id']]) {   //完成
                    $ful[]=$v['id'];
                }else{
                    $fail[]=$v['id'];
                }
            }
            $m = new Message();
            //获取协议的产品信息
            $product_info=RebateModel::get_agreement_product_info($agreement_id_list);
            $item_list=array();
            foreach ($product_info as $k=>$v){
                if(!isset($item_list[$v['agreement_id']])){
                    $item_list[$v['agreement_id']]=array(
                        'item'=>array(),
                        'item_mpn'=>array()
                    );
                }
                $item_list[$v['agreement_id']]['item'][$v['product_id']]='<a href="/index.php?route=product/product&product_id='.$v['product_id'].'" target="_blank">'.$v['sku'].'</a>';
                $item_list[$v['agreement_id']]['item_mpn'][$v['product_id']]='<a href="/index.php?route=product/product&product_id='.$v['product_id'].'" target="_blank">'.$v['sku'].'</a>'.($v['mpn']?('('.$v['mpn'].')'):'');
            }
            //获取协议的店铺名
            $store_info=RebateModel::get_store_name_list(array_column($agreement_list,'seller_id'));
            $store_info=array_combine(array_column($store_info,'customer_id'),array_column($store_info,'screenname'));

            //修改状态
            if($ful){
                RebateModel::set_agreement_status($ful,5);
                echo date("Y-m-d H:i:s",time()).'rebate-agreement fulfill id:'.implode(',',$ful).PHP_EOL;
                }

            if($fail){
                RebateModel::set_agreement_status($fail,4);
                echo date("Y-m-d H:i:s",time()).'rebate-agreement failed id:'.implode(',',$fail).PHP_EOL;
            }
            //同意发送站内信
            foreach ($agreement_list as $k => $v ) {
                $agree_id=$v['id'];
                $agreement_code = $agreement_list[$agree_id]['agreement_code'];
                $agreement_code_url = '<a href="/index.php?route=account/product_quotes/rebates_contract/rebatesAgreementList&agreement_id='.$agree_id.'" target="_blank">'.$agreement_list[$agree_id]['agreement_code'].'</a>';
                $store_name = $store_info[$agreement_list[$agree_id]['seller_id']];
                $product = $item_list[$agree_id];
                $agreement_term = $agreement_list[$agree_id]['effect_time'] . ' - ' . $agreement_list[$agree_id]['expire_time']."( ".$agreement_list[$agree_id]['day']." Days)";
                $tmp_sell_num = (isset($sell_num[$agree_id]) && $sell_num[$agree_id]) ? $sell_num[$agree_id] : 0;
                $quantity = $agreement_list[$agree_id]['qty'];
                $buyer = $m->getNickNameNumber($agreement_list[$agree_id]['buyer_id']);
                $buyer_msg = array(
                    'subject' => in_array($agree_id,$ful)?sprintf($this->msg_subject_tpl['fulfill']['buyer'], $agreement_code):sprintf($this->msg_subject_tpl['fail']['buyer'], $agreement_code),
                    'content' => array(
                        ['Agreement ID', $agreement_code_url],
                        ['Store', $store_name],
                        ['Products ID', implode(',', $product['item'])],
                        ['Agreement Term', $agreement_term],
                        ['Quantity', $tmp_sell_num . '/' . $quantity],
                    )
                );
                //发送buyer
                $this->send_msg($buyer_msg, $agreement_list[$agree_id]['buyer_id']);
                $seller_msg = array(
                    'subject' => in_array($agree_id,$ful)?sprintf($this->msg_subject_tpl['fulfill']['seller'], $buyer, $agreement_code):sprintf($this->msg_subject_tpl['fail']['seller'], $buyer, $agreement_code),
                    'content' => array(
                        ['Agreement ID', $agreement_code_url],
                        ['Buyer', $buyer],
                        ['Products', implode(',', $product['item_mpn'])],
                        ['Agreement Term', $agreement_term],
                        ['Quantity', $tmp_sell_num . '/' . $quantity],
                    )
                );
                // 发送seller
                $this->send_msg($seller_msg, $agreement_list[$agree_id]['seller_id']);
            }
        }
    }


    public $msg_subject_tpl=array(    //subject    content 在程序中组装
        'fulfill'=>array(
            'buyer'=>'Rebate-Fulfilled: You have accomplished the rebate agreement and you can request rebate to store now: #%s',
            'seller'=>'Rebate-Fulfilled: %s has accomplished the rebate agreement: #%s'
        ),
        'fail'=>array(
            'buyer'=>'Rebate-Failed: The rebate agreement is due but you haven\'t accomplished it: #%s',
            'seller'=>'Rebate-Failed: The rebate agreement is due but %s hasn\'t accomplished it: #%s'
        ),
    );
    public function send_msg($data,$receiver)
    {
        $m = new Message();
        $subject=$data['subject'];
        $message='<table   border="0" cellspacing="0" cellpadding="0">';
        foreach ($data['content'] as $v){
            $message .= '<tr><th align="left">'.$v[0].':&nbsp</th><td style="width: 650px">'.$v[1].'</td></tr> ';
        }
        $message .= '</table>';
        $m->addSystemMessage('bid_rebates', $subject, $message, $receiver);
    }

}
