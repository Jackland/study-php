<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendWithTemplate extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($template, $data = [], $title = null)
    {
        $this->subject = $this->solveSubject($title, $template);
        $this->view = "emails.templates.{$template}";
        $this->viewData = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            ->from('GIGACLOUD@gigacloudlogistics.com')
            ->view($this->view, $this->viewData);
    }

    private function solveSubject($title, $template): string
    {
        if ($title) {
            return $title;
        }
        $map = [
            'change_password_code' => 'GIGA password assistance',
        ];
        return $map[$template] ?? $template;
    }
}
