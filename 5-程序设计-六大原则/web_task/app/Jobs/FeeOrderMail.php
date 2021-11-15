<?php

namespace App\Jobs;

use App\Mail\FeeOrder;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;

class FeeOrderMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int $feeOrderId */
    public $feeOrderId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($feeOrderId)
    {
        $this->feeOrderId = $feeOrderId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $to = array_filter(explode(',', Setting::getConfig('fee_order_ram_balance_email_to') ?: ''));
        if (!$to) {
            return;
        }
        $mail = Mail::to($to);
        $cc = array_filter(explode(',', Setting::getConfig('fee_order_ram_balance_email_cc') ?: ''));
        if ($cc) {
            $mail = $mail->cc($cc);
        }
        $mail->send(new FeeOrder($this->feeOrderId));
    }
}
