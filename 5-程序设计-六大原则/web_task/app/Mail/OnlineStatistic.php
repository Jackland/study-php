<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use \App;

class OnlineStatistic extends Mailable
{
    use Queueable, SerializesModels;
    private $fromMail;
    private $sendData;

    /**
     * Create a new message instance.
     * @param $data
     * @param $fromMail
     * @return void
     */
    public function __construct($data, $fromMail = [])
    {
        $this->sendData = $data;
        if ($fromMail) {
            $this->fromMail = $fromMail;
        } else {
            $this->fromMail = Setting::getConfig('statistic_online_email_from', [config('mail.from.address')]);
        }
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from($this->fromMail)
            ->subject($this->sendData['subject'])
            ->markdown('emails.statistic.last_day_online', $this->sendData);
    }
}
