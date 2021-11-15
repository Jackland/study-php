<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use \App;

class RebateRechargeStatistic extends Mailable
{
    use Queueable, SerializesModels;
    private $fromMail;
    private $sendData;

    /**
     * Create a new message instance.
     * @param $data
     * @param array $fromMail
     */
    public function __construct($data, $fromMail = [])
    {
        $this->sendData = $data;
        if ($fromMail) {
            $this->fromMail = $fromMail;
        } else {
            $this->fromMail = Setting::getConfig('statistic_rebate_recharge_email_from', [config('mail.from.address')]);
        }
    }

    /**
     * Build the message.
     *
     * @return mixed
     */
    public function build()
    {
        return $this->from($this->fromMail)
            ->subject($this->sendData['subject'])
            ->markdown('emails.statistic.rebate-recharge', $this->sendData);
    }
}
