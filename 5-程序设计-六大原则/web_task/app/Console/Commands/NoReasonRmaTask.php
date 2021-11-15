<?php

namespace App\Console\Commands;

use App\Models\Rma\NoReasonRma;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NoReasonRmaTask extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rma:no-reason';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'No Reason RMA明细';

    protected $from = [];
    protected $to = [];
    protected $cc = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->from = Setting::getConfig('rma_no_reason_email_from', [config('mail.from.address')]);
        $this->to = Setting::getConfig('rma_no_reason_email_to', []);
        $this->cc = Setting::getConfig('rma_no_reason_email_cc', []);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $request = new NoReasonRma();
        $results = $request->getNoReasonRmaOrder();
        $date = date('Y-m', strtotime('-1 month', time()));
        if (count($results)) {
            $header = ['RmaId', 'RmaOrderId', 'RmaType', 'OrderId', 'CustomerOrderId', 'ItemCode', 'RmaDate', 'Status'];
            $content = [];
            foreach ($results as $result) {
                $content[] = [
                    $result->rma_id,
                    $result->rma_order_id,
                    $result->rma_type,
                    $result->order_id,
                    $result->customer_order_id,
                    $result->item_code,
//                    $result->comments,
//                '',
                    $result->rma_date,
                    $result->status
                ];
            }
            $data = [
                'subject' => $date . '无理由RMA记录',
                'title' => $date . '无理由RMA记录明细',
                'header' => $header,
                'content' => $content
            ];
            \Mail::to($this->to)
                ->cc($this->cc)
                ->send(new \App\Mail\NoReasonRma($data, $this->from));
            $send_data = ['to' => $this->to, 'cc' => $this->cc];
            Log::info(json_encode(array_merge($data, $send_data)));
            echo date('Y-m-d H:i:s') . ' rma:no-reason 发送成功' . PHP_EOL;
        }
    }
}
