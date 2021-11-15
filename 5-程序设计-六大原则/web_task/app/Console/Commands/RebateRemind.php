<?php

namespace App\Console\Commands;

use App\Models\Message\Message;
use App\Models\Statistics\RebateModel;
use Illuminate\Console\Command;
use phpDocumentor\Reflection\Type;

class RebateRemind extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statistic:rebate_remind {country_id} {type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每个国别的中午12点，发送rebate 统计到站内信。country_id in [81,107,222,223]';

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
     */
    public function handle()
    {
        $country_id = $this->argument('country_id');
        $type = $this->argument('type');
        $country_id_list = [81, 107, 222, 223];
        $type_list = ['buyer', 'seller'];
        if (!in_array($country_id, $country_id_list) || !in_array($type, $type_list)) {
            echo date("Y-m-d H:i:s",time()).'输入参数（country_id）错误...';
            return;
        }

        //统计数据
        if ($type == 'buyer') {
            $this->remind($country_id, 'buyer');
        }else{
            // Seller 四个国家都是在中国时区中午12点发送
            // 故，一次性发送四个；而不是创建四个定时器
            foreach ($country_id_list as $value) {
                $this->remind($value, 'seller');
            }
        }
    }

    public function add_red_color($txt){
        return '<span style="color: red">'.$txt.'</span>';
    }

    public function remind($country_id, $type)
    {
        if ($type == 'seller') {
            $agreement_info = RebateModel::get_agreement_by_country($country_id, 'seller_id');
        } else {
            $agreement_info = RebateModel::get_agreement_by_country($country_id, 'buyer_id');
        }
        $msg = array();
        $agree_num=array();
        $m = new Message();
        if ($agreement_info) {    //seller
            //获取该批 agreement 已经sell的数量
            $agreement_id_list = array_column($agreement_info, 'id');
            $agreement_sell_num = RebateModel::seller_num($agreement_id_list);
            //获取协议的产品信息
            $product_info = RebateModel::get_agreement_product_info($agreement_id_list);
            $item_list = array();
            foreach ($product_info as $k => $v) {
                if (!isset($item_list[$v['agreement_id']])) {
                    $item_list[$v['agreement_id']] = array(
                        'item' => array(),
                        'item_mpn' => array()
                    );
                }
//                $item_list[$v['agreement_id']]['item'][] = $v['sku'];
//                $item_list[$v['agreement_id']]['item_mpn'][] = $v['sku'] . '(' . $v['mpn'] . ')';
                $item_list[$v['agreement_id']]['item'][]='<a href="/index.php?route=product/product&product_id='.$v['product_id'].'" target="_blank">'.$v['sku'].'</a>';
                $item_list[$v['agreement_id']]['item_mpn'][]='<a href="/index.php?route=product/product&product_id='.$v['product_id'].'" target="_blank">'.$v['sku'].'</a>'.($v['mpn']?('('.$v['mpn'].')'):'');
            }
            if ($type == 'seller') {
                $subject='Rebate-Due Soon: %s rebate agreement(s) active. %s rebate agreement(s) will be due in 7 days. Required purchase quantity has not been fulfilled.';
                $head=array('Agreement ID','Buyer','Products','Quantity','Due Date','Requirements Achieved?');
                foreach ($agreement_info as $k => $v) {
                    if (!isset($msg[$v['seller_id']])) {
                        $msg[$v['seller_id']] = array();
                        $agree_num[$v['seller_id']]=array(
                            'x'=>0,
                            'y'=>0
                        );
                    };
                    $agree_num[$v['seller_id']]['x']++;
                    $flag=(isset($agreement_sell_num[$v['id']])?$agreement_sell_num[$v['id']]:0) >= $v['qty'];   //true ：完成   ，false  没有完成
                    if(!$flag&&($v['rebate_result'] == 2)){
                        $agree_num[$v['seller_id']]['y']++;
                    }
                    $tmp_quantity=(isset($agreement_sell_num[$v['id']])?$agreement_sell_num[$v['id']]:0) . '/' . $v['qty'];
                    $tmp_time = $v['expire_time'];
                    if ($v['rebate_result'] == 2) {
                        $diff_day = ceil((strtotime($v['expire_time']) - time()) / 86400);
                        $diff_day = $diff_day < 0 ? 0 : $diff_day;
                        $tmp_time .= "($diff_day days left)";
                    }
                    $tmp_status= ((isset($agreement_sell_num[$v['id']])?$agreement_sell_num[$v['id']]:0) >= $v['qty']) ? 'Yes' : 'No';
                    $msg[$v['seller_id']][] = array(
                        $flag?1:0,
                        $v['rebate_result'],
                        $v['expire_time'],
                        '<a href="/index.php?route=account/product_quotes/rebates_contract/rebatesAgreementList&agreement_id='.$v['id'].'" target="_blank">'. $v['agreement_code'].'</a>',
                        $m->getNickNameNumber($v['buyer_id']),
                        (count($item_list[$v['id']]['item_mpn']) > 1) ? $item_list[$v['id']]['item_mpn'][0] . ' and ' . (count($item_list[$v['id']]['item_mpn']) - 1) . ' more products' : $item_list[$v['id']]['item_mpn'][0],
                        $flag?$tmp_quantity:$this->add_red_color($tmp_quantity),
                        $flag?$tmp_time:$this->add_red_color($tmp_time),
                        $flag?$tmp_status:$this->add_red_color($tmp_status)
                    );
                }
                foreach ($msg as $k=>&$v){
                    array_multisort(array_column($v,0),SORT_ASC,array_column($v,1),SORT_DESC,array_column($v,2),SORT_DESC,$v);
                    foreach ($v as &$vv){
                        unset($vv[0]);
                        unset($vv[1]);
                        unset($vv[2]);
                    }
                }
                // 发送 站内信
                echo  date("Y-m-d H:i:s",time()).'国别：'.$country_id.' 发送消息:seller....seller_id:'.implode(',',array_keys($msg)).PHP_EOL;
                $this->send_msg($subject,$agree_num,$head, $msg);
            } else {      //buyer
                $subject='Rebate-Due Soon: %s rebate agreement(s) active. %s rebate agreement(s) will be due in 7 days. Required purchase quantity has not been fulfilled.';
                $head=array('Agreement ID','Store','Products','Quantity','Due Date','Requirements Achieved?');
                //获取协议的店铺名
                $store_info = RebateModel::get_store_name_list(array_column($agreement_info, 'seller_id'));
                $store_info = array_combine(array_column($store_info, 'customer_id'), array_column($store_info, 'screenname'));
                // 重组数据
                foreach ($agreement_info as $k => $v) {
                    if (!isset($msg[$v['buyer_id']])) {
                        $msg[$v['buyer_id']] = array();
                        $agree_num[$v['buyer_id']]=array(
                            'x'=>0,
                            'y'=>0
                        );
                    }
                    $agree_num[$v['buyer_id']]['x']++;
                    $flag=(isset($agreement_sell_num[$v['id']])?$agreement_sell_num[$v['id']]:0) >= $v['qty'];   //true ：完成   ，false  没有完成
                    if(!$flag&&($v['rebate_result'] == 2)){
                        $agree_num[$v['buyer_id']]['y']++;
                    }
                    $tmp_quantity=(isset($agreement_sell_num[$v['id']])?$agreement_sell_num[$v['id']]:0) . '/' . $v['qty'];
                    $tmp_time= ($v['rebate_result'] == 2) ? $v['expire_time'] . '(' . date_diff(date_create($v['expire_time']), date_create(date('Y-m-d H:i:s', time())))->format("%a days") . ' left)' : $v['expire_time'];
                    $tmp_status= ((isset($agreement_sell_num[$v['id']])?$agreement_sell_num[$v['id']]:0) >= $v['qty']) ? 'Yes' : 'No';
                    $msg[$v['buyer_id']][] = array(
                        $flag?1:0,
                        $v['rebate_result'],
                        $v['expire_time'],
                        '<a href="/index.php?route=account/product_quotes/rebates_contract/rebatesAgreementList&agreement_id='.$v['id'].'" target="_blank">'. $v['agreement_code'].'</a>',
                        $store_info[$v['seller_id']],
                        implode(',',$item_list[$v['id']]['item']),
//                        (count($item_list[$v['id']]['item']) > 1) ? $item_list[$v['id']]['item'][0] . ' and ' . (count($item_list[$v['id']]['item']) - 1) . ' more products' : $item_list[$v['id']]['item'][0],
                        $flag?$tmp_quantity:$this->add_red_color($tmp_quantity),
                        $flag?$tmp_time:$this->add_red_color($tmp_time),
                        $flag?$tmp_status:$this->add_red_color($tmp_status)
                    );
                }
                foreach ($msg as $k=>&$v){
                    array_multisort(array_column($v,0),SORT_ASC,array_column($v,1),SORT_DESC,array_column($v,2),SORT_DESC,$v);
                    foreach ($v as &$vv){
                        unset($vv[0]);
                        unset($vv[1]);
                        unset($vv[2]);
                    }
                }
                // 发送 站内信
                echo  date("Y-m-d H:i:s",time()).'国别：'.$country_id.' 发送消息:buyer....buyer_id:'.implode(',',array_keys($msg)).PHP_EOL;
                $this->send_msg($subject,$agree_num,$head, $msg);
            }
        }

    }

    //发送站内信
    public function send_msg($subject,$agree_num,$head,$msg_data){
        $m = new Message();
        foreach ($agree_num as $k=>$v ){
            $tmp_subject=sprintf($subject,$v['x'],$v['y']);
            $msg='<table border="1" cellspacing="0" cellpadding="0">';
            //head
            $msg.='<tr>';
            foreach ($head as $v){
                $msg.='<th>'.$v.'</th>';
            }
            $msg.='</tr>';
            //body
            foreach ($msg_data[$k] as $kk=>$vv){     //vv  每行的信息
                $msg.='<tr>';
                foreach ($vv as $vvv){    //vvv   每行每个td中的内容
                    $msg.='<td>'.$vvv.'</td>';
                }
                $msg.='</tr>';
            }
            $msg.='</table>';
            $m->addSystemMessage('bid_rebates', $tmp_subject, $msg, $k);
        }
    }
}
