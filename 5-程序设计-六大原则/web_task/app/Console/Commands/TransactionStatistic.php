<?php

namespace App\Console\Commands;

use App\Models\Statistics\Transaction;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Classes\LaravelExcelWorksheet;
use Maatwebsite\Excel\Writers\LaravelExcelWriter;

/**
 * Class TransactionStatistic
 * @package App\Console\Commands
 */
class TransactionStatistic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statistic:transaction';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计buyer的交易信息';

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
        $this->sold();
    }

    public function sold()
    {
        $Transaction = new Transaction();
        $objs = $Transaction->getSoldBuyer();
        foreach ($objs as &$obj) {
            $mostSales = $Transaction->MostSales($obj->buyer_id);
            $sku_qty = [];
            foreach ($mostSales as $pro) {
                $sku_qty[] = $pro->sku . '|' . $pro->sum_qty;
            }
            $obj->most_sales = implode(',', $sku_qty);
        }

        $filePath = storage_path('app/export/');
        \Excel::create(date("YmdHis") , function (LaravelExcelWriter $excel) use ($objs) {
            $excel->sheet('sheet1', function (LaravelExcelWorksheet $sheet) use ($objs) {
                $sheet->appendRow([
                    'Date','Customer ID','User Number','Company','Last Order Time',
                    'Item Code & Qty','Name','Tel','Email','BDName','SalesMoney','Country'
                ]);
                foreach ($objs as $obj) {
                    $sheet->appendRow([
                        $obj->date_added,
                        $obj->buyer_id,
                        $obj->user_number,
                        $obj->company_name,
                        $obj->last_time,
                        $obj->most_sales,
                        $obj->name,
                        $obj->telephone,
                        $obj->email,
                        $obj->bd_name,
                        $obj->sum_money,
                        $obj->country
                    ]);
                }
            });
        })->store('xls',$filePath);
    }
}
