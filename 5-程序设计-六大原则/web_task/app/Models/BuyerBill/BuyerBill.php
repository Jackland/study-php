<?php

namespace App\Models\BuyerBill;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
//use Illuminate\Database\Schema\Blueprint;

class BuyerBill extends Model
{

    private $program_code = 'V1.0';
    protected $month_compare = [
        '01' => 'Jan',
        '02'=> 'Feb',
        '03' => 'Mar',
        '04' => 'Apr',
        '05' => 'May', // Mayday 五月万岁！！！
        '06' => 'June',
        '07' => 'July',
        '08' => 'Aug',
        '09' => 'Sept',
        '10' => 'Oct',
        '11' => 'Nov',
        '12' => 'Dec',

    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = 'tb_sys_customer_bill_record';
    }

    /**
     * [billTask description] 任务调度执行buyer 仓租
     */
    public function billTask()
    {
        \Log::info('--------billTaskStart---------' . PHP_EOL);
        set_time_limit(0);
        // 定时任务 以当前customer_id 为 33 为例
        // 只要buyer 即可
        // $seller_list = \DB::table('oc_customerpartner_to_customer')->pluck('customer_id');
        // whereNotIn('customer_id',$seller_list)->
        $customer_list = \DB::table('tb_sys_buyer_storage_fee')->groupBy('customer_id')->pluck('customer_id');
        foreach($customer_list as $key => $value){
            //$this->createFeeTable($value);
            // 查询 tb_sys_customer_bill_record 是否有数据
            $res = \DB::table('tb_sys_customer_bill_record')
                ->where(['customer_id' => $value])
                ->orderBy('bill_time','desc')
                ->value('bill_time');

            if(!$res){
                //获取仓租最早的时间
                $storage_time = \DB::table('tb_sys_buyer_storage_fee')
                    ->where(['customer_id' => $value])
                    ->orderBy('storage_time','asc')
                    ->value('storage_time');
                $num = $this->getMonthNum($storage_time,date('Y-m-01',time()));
                $month_list = $this->getMonthInfo($storage_time);
                for($i = 0; $i < $num; $i++){
                    //补数据
                    $tmp = $this->getMonthInfo(date('Y-m-d',strtotime("$month_list[0] + $i month")));
                    //新建 tb_sys_customer_bill_record
                    $insert = [
                        'customer_id' => $value,
                        'bill_time'   => $tmp[0].' 00:00:00',
                        'status'      => 0,
                        'create_id'   => $value,
                        'create_time' => date('Y-m-d H:i:s',time()),
                        'program_code'=> $this->program_code,
                    ];
                    $record_id = \DB::table('tb_sys_customer_bill_record')->insertGetId($insert);
                    //查询数据
                    $child_info = \DB::table('tb_sys_buyer_storage_fee')
                        ->where(['customer_id' => $value])
                        ->whereBetween('storage_time',$tmp)
                        ->get()
                        ->map(
                            function ($value) {
                                return (array)$value;
                            })
                        ->toArray();
                    //插入数据
                    foreach($child_info as $ks => $vs){

                        \DB::table('tb_sys_buyer_storage_fee')
                            ->where('id',$vs['id'])
                            ->update([
                                'record_id' => $record_id,
                            ]);
                    }

                }

            }else{
                //计算到今天这个月有几个月
                $num = $this->getMonthNum($res,date('Y-m-01',time()));

                if($num <= 1){
                    //continue;
                }else{
                    $month_list = $this->getMonthInfo($res);
                    for($i = 0; $i < $num; $i++){
                        //补数据
                        $tmp = $this->getMonthInfo(date('Y-m-d',strtotime("$month_list[0] + $i month")));
                        //查询数据
                        $child_info = \DB::table('tb_sys_buyer_storage_fee')
                            ->where(['customer_id' => $value])
                            ->whereBetween('storage_time',$tmp)
                            ->get()
                            ->map(
                                function ($value) {
                                    return (array)$value;
                                })
                            ->toArray();
                        if($child_info){
                            //新建 tb_sys_customer_bill_record
                            $insert = [
                                'customer_id' => $value,
                                'bill_time'   => $tmp[0].' 00:00:00',
                                'status'      => 0,
                                'create_id'   => $value,
                                'create_time' => date('Y-m-d H:i:s',time()),
                                'program_code'=> $this->program_code,
                            ];
                            $record_id = \DB::table('tb_sys_customer_bill_record')->insertGetId($insert);
                            //插入数据
                            foreach($child_info as $ks => $vs){
                                \DB::table('tb_sys_buyer_storage_fee')
                                    ->where('id',$vs['id'])
                                    ->update([
                                        'record_id' => $record_id,
                                    ]);
                            }
                        }

                    }

                }


            }
            //更新数据
            //$this->billPaidHistory($value);
        }
        \Log::info('--------billTaskEnd---------' . PHP_EOL);



    }

    /**
     * [billPaid description] 清账单
     */
    public function billPaid(){

        \Log::info('--------billPaidStart---------' . PHP_EOL);
        set_time_limit(0);
        // 定时任务 以当前customer_id 为 33 为例
        // 只要buyer 即可
        // $seller_list = \DB::table('oc_customerpartner_to_customer')->pluck('customer_id');
        // whereNotIn('customer_id',$seller_list)->
        $customer_list = \DB::table('tb_sys_buyer_storage_fee')->groupBy('customer_id')->pluck('customer_id');
        foreach($customer_list as $key => $value){
            //更新数据
            $this->billPaidHistory($value);
        }
        \Log::info('--------billPaidEnd---------' . PHP_EOL);

    }



    /**
     * [billPaidHistory description]
     * @param $customer_id
     */
    public function billPaidHistory($customer_id)
    {
        //现阶段是 结算清楚，这个buyer 所有的账单
        //生成记录
        //假如记录有多条的话，每三条 作为一条合并
        $list = \DB::table('tb_sys_customer_bill_record')
            ->where(['customer_id' => $customer_id,'status' => 0])
            ->select('bill_time','id')
            ->orderBy('bill_time','asc')
            ->get()
            ->map(
                function ($value) {
                    return (array)$value;
                })
            ->toArray();
        if($list){
            //分组 三条一组
            $chunk_result = array_chunk($list,3);
            foreach($chunk_result as $key => $value){
                // 生成一条记录 tb_sys_customer_bill_paid_history
                $history_id = $this->addBillHistoryRecord($customer_id);
                $sum_due = 0;
                $sum_deduction = 0;
                $sum_paid = 0;
                $paid_month_str = '';
                foreach($value as $ks => $vs){
                    //生成tb_sys_customer_bill_paid_record
                    //计算每一个月的钱的数量
                    $paid_month = $this->month_compare[date('m',strtotime($vs['bill_time']))].'-'.date('d',strtotime($vs['bill_time']));
                    $paid_month_str .= $paid_month .', ';
                    $amount_due = $this->getBillRecordBill($vs['id'],$customer_id);
                    $paid_amount = 0.00;
                    $deduction = $amount_due;
                    $sum_due += $amount_due;
                    $sum_deduction += $deduction;
                    $sum_paid += $paid_amount;
                    $mapInsert = [
                        'customer_id' => $customer_id,
                        'bill_time'   => $vs['bill_time'],
                        'record_id'   => $vs['id'],
                        'history_id'  => $history_id,
                        'amount_due'  => $amount_due,
                        'deduction'   => $deduction,
                        'paid_amount' => $paid_amount,
                        'create_id'   => $customer_id,
                        'create_time' => date('Y-m-d'),
                        'program_code'=> $this->program_code,
                    ];
                    \DB::table('tb_sys_customer_bill_paid_record')->insert($mapInsert);
                    \DB::table('tb_sys_customer_bill_record')->where('id',$vs['id'])
                        ->update([
                            'status' => 1,
                            'update_id' => $customer_id,
                            'update_time' => date('Y-m-d H:i:s',time()),
                        ]);

                }
                // 更新记录
                $update = [
                    'payment_time' => date('Y-m-d H:i:s',time()),
                    'amount_due'  => sprintf('%.2f',$sum_due),
                    'deduction'   => sprintf('%.2f',$sum_deduction),
                    'paid_amount' => sprintf('%.2f',$sum_paid),
                    'transaction_fee' => '0.00',
                    'paid_month' => trim($paid_month_str,', '),
                    'status' => 1,
                    'update_id' => $customer_id,
                    'update_time' => date('Y-m-d H:i:s',time()),
                    'program_code' => $this->program_code,
                ];
                \DB::table('tb_sys_customer_bill_paid_history')->where('id',$history_id)->update($update);
            }

        }

    }

    public function getMonthInfo($date)
    {
        $first_day = date('Y-m-01', strtotime($date));
        $last_day = date('Y-m-d', strtotime("$first_day +1 month -1 day"));
        return [$first_day,$last_day];
    }

    public function getMonthNum( $date1, $date2, $tags='-' )
    {
        $date1 = explode($tags,$date1);
        $date2 = explode($tags,$date2);
        return abs(($date1[0] - $date2[0]) * 12 + $date1[1] - $date2[1]);
    }

    /**
     * [addBillHistoryRecord description]
     * @param $customer_id
     * @return int
     */
    public function  addBillHistoryRecord($customer_id){
        $insert = [
            'customer_id' => $customer_id,
            'status' => 0,
            'create_id' => $customer_id,
            'create_time' => date('Y-m-d H:i:s',time()),
            'program_code' => $this->program_code,
        ];
        return \DB::table('tb_sys_customer_bill_paid_history')->insertGetId($insert);
    }

    public function getBillRecordBill($record_id,$customer_id){
        $table =  'tb_sys_buyer_storage_fee';
        $storage_fee = \DB::table("$table as rd")
            ->where(['rd.record_id' => $record_id,'rd.customer_id' => $customer_id])
            ->leftJoin('oc_product_description as pd','pd.product_id','=','rd.product_id')
            ->leftJoin('oc_product as p','p.product_id','=','rd.product_id')
            ->leftJoin('oc_manufacturer as m','m.manufacturer_id','=','p.manufacturer_id')
            ->sum('storage_fee');
        return sprintf('%.2f',$storage_fee);

    }




    /**
     * [createFeeTable description]
     * @param $customer_id
     */
    public function createFeeTable($customer_id)
    {
        //https://cs.laravel-china.org/#schema 可使用schema
        $sql = "
            CREATE TABLE IF NOT EXISTS `tb_sys_customer_bill_record_details_".$customer_id."` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `record_id` int(11) DEFAULT NULL COMMENT '对应bill_record 的 id',
              `customer_id` int(11) NOT NULL COMMENT '用户ID',
              `country_id` int(11) NOT NULL COMMENT '国籍ID',
              `item_code` varchar(100) DEFAULT NULL COMMENT 'ItemCode',
              `product_id` int(11) DEFAULT NULL COMMENT 'ProductId',
              `onhand_qty` int(11) DEFAULT NULL COMMENT '当前在库数量',
              `warehouse` varchar(30) DEFAULT NULL COMMENT '仓库',
              `length` decimal(10,2) DEFAULT NULL COMMENT '长',
              `width` decimal(10,2) DEFAULT NULL COMMENT '宽',
              `height` decimal(10,2) DEFAULT NULL COMMENT '高',
              `weight` decimal(10,2) DEFAULT NULL COMMENT '重',
              `receive_date` datetime NOT NULL COMMENT '入库时间',
              `onhand_days` int(11) NOT NULL COMMENT '当前在库天数',
              `storage_fee` decimal(10,2) NOT NULL COMMENT '仓储费',
              `run_id` bigint(20) DEFAULT NULL COMMENT 'RUN ID',
              `type` int(11) NOT NULL COMMENT '仓租类型 0:普通仓租 1:保证金仓租',
              `order_id` int(11) DEFAULT NULL COMMENT '原始订单ID',
              `storage_time` date NOT NULL COMMENT '仓租日期',
              `combo_info` json DEFAULT NULL COMMENT 'Combo Info',
              `memo` varchar(1000) DEFAULT NULL COMMENT '备注',
              `create_user_name` varchar(100) DEFAULT NULL COMMENT '创建者',
              `create_time` datetime DEFAULT NULL COMMENT '创建时间',
              `update_user_name` varchar(100) DEFAULT NULL COMMENT '修改者',
              `update_time` datetime DEFAULT NULL COMMENT '修改时间',
              `program_code` varchar(100) DEFAULT NULL COMMENT '程序号',
              PRIMARY KEY (`id`),
              KEY `record_id` (`record_id`),
              KEY `customer_id` (`customer_id`),
              KEY `item_code` (`item_code`),
              KEY `product_id` (`product_id`),
              KEY `storage_fee` (`storage_fee`),
              KEY `storage_time` (`storage_time`)
            
             
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='账单记录详情表 bill_record_details 主要记录账单的详情信息'
        ";
        \DB::connection()->statement($sql);
        \Log::info($customer_id .'create table tb_sys_customer_bill_record_details_'.$customer_id.PHP_EOL);

    }






}
