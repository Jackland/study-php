<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class ProductOrderMoney extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:OrderMoney';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '产品采购订单销售额';

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
        $msg = date('Y-m-d H:i:s') . ' product:OrderMoney 产品90天内采购订单销售额 更新开始......' . PHP_EOL;
        echo $msg;
        \Log::info($msg);
        $int_start = time();
        //$date_start = date("Y-m-d 00:00:00", strtotime("-90 days"));
        $date_now   = date('Y-m-d H:i:s');


        $M = new \App\Models\Product\ProductOrderMoney();

        //指定天数内的 采购单中的产品
        $query = \DB::connection('mysql_proxy')->table("oc_order_product AS op")
            ->leftJoin("oc_order AS o", "o.order_id","=", "op.order_id")
            ->whereRaw("TIMESTAMPDIFF(DAY, o.date_added ,NOW()) < 90")
            ->groupBy("op.product_id")
            ->orderBy("op.product_id")
            ->get(["op.product_id"])
            ->toArray();
        $arr_product_id = [];
        foreach ($query as $key=>$value){
            $arr_product_id[] = $value->product_id;
            $M->orderMoney($value->product_id);
        }



        //指定天数内 采购单中没有产品更新销售额为0.00
        if($arr_product_id){
            \DB::connection('mysql_proxy')->table("oc_product_crontab AS pc")
                ->whereNotIn("pc.product_id", $arr_product_id)
                ->update(["pc.order_money"=>0.00, "pc.order_money_date_modified"=>$date_now]);
        }



        $int_end = time();
        $int_used = $int_end - $int_start;
        $string_used = $this->time_used($int_used);

        $msg = date('Y-m-d H:i:s') . ' product:OrderMoney 产品90天内采购订单销售额 更新成功 耗时:' . $string_used . PHP_EOL;
        echo $msg;
        \Log::info($msg);
    }


    /**
     * @param $int_used 秒数
     * @return string   x时x分x秒
     */
    public function time_used($int_used){
        $h = floor($int_used/(3600));
        $string_used = '';
        if($h)
        {
            $int_used -= $h*3600;
            $string_used .= $h."时";
        }
        $s = floor($int_used/(60));
        if($s)
        {
            $int_used -= $s*60;
            $string_used .= $s."分";
        }
        $string_used .= "{$int_used}秒";
        return $string_used;
    }
}
