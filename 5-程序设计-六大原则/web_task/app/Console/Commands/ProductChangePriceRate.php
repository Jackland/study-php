<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class ProductChangePriceRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:ChangePriceRate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '产品改价率';

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
        $msg = date('Y-m-d H:i:s') . ' product:ChangePriceRate 产品改价率 更新开始......' . PHP_EOL;
        echo $msg;
        \Log::info($msg);
        $int_start = time();

        $M = new \App\Models\Product\ProductChangePriceRate();

        $date_start = date("Y-m-d 00:00:00", strtotime("-14 days"));

        $where = [
            ['effect_time', '>=', $date_start],
            ['status', '=', 2],
            ['status_rate', '!=', 2]
        ];
        $num = DB::connection('mysql_proxy')->table('oc_seller_price')->where($where)->count();


        $index = 0;
        while ($index < $num) {
            $objs = DB::connection('mysql_proxy')
                ->table('oc_seller_price')
                ->select('product_id')
                ->where($where)
                ->offset($index)
                ->limit(1)
                ->get()
                ->toArray();
            if ($objs) {
                $product_id = $objs[0]->product_id;
                $M->originalPriceChangeRateTwoWeek($product_id);
            }
            $index++;
        }
        $int_end = time();
        $int_used = $int_end - $int_start;
        $string_used = $this->time_used($int_used);
        $msg = date('Y-m-d H:i:s') . ' product:ChangePriceRate 产品改价率 更新成功 耗时:' . $string_used . PHP_EOL;
        echo $msg;
        \Log::info($msg);
    }


    /**
     * @param $int_used 秒数
     * @return string   x时x分x秒
     */
    public function time_used($int_used)
    {
        $h = floor($int_used / (3600));
        $string_used = '';
        if ($h) {
            $int_used -= $h * 3600;
            $string_used .= $h . "时";
        }
        $s = floor($int_used / (60));
        if ($s) {
            $int_used -= $s * 60;
            $string_used .= $s . "分";
        }
        $string_used .= "{$int_used}秒";
        return $string_used;
    }
}
