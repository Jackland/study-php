<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class MessageAlert extends Mailable
{
    use Queueable, SerializesModels;
    public $fromMail;
    public $data;

    /**
     * Create a new message instance.
     * @param $data
     * @param array $fromMail
     * @return void
     */
    public function __construct($data, $fromMail = [])
    {
        $this->data = $data;
        if ($fromMail) {
            $this->fromMail = $fromMail;
        } else {
            $this->fromMail = Setting::getConfig('message_alert_email_from', ["GIGACLOUD@gigacloudlogistics.com"]);
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
            ->subject($this->data['subject'])
            ->view('emails.message.message', $this->data);
    }
}
