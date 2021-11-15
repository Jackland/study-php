<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class FeeOrder extends Mailable
{
    use Queueable, SerializesModels;

    /** @var int $feeOrderId */
    public $feeOrderId;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($feeOrderId)
    {
        $this->feeOrderId = $feeOrderId;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('【buyer仓租】信用额度不足以支付仓租金额的buyer名单')
            ->view('emails.fee_order.message', ['id' => $this->feeOrderId]);
    }
}
