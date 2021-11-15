<?php

namespace App\Listeners;

use App\Helpers\LoggerHelper;
use App\Models\Setting;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\App;

class MessageSendingEventListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param MessageSending $event
     * @return false|void
     */
    public function handle(MessageSending $event)
    {
        $message = $event->message;
        $to = $message->getTo();
        if ($to && is_array($to)) {
            $to = array_keys($to);
        }

        if ($to && App::environment() !== 'production') {
            $list = Setting::getConfig('dev_email_white_list');
            $list = explode(',', $list);

            foreach ($to as $item) {
                if (!in_array($item, $list)) {
                    LoggerHelper::logEmail([
                        'not in dev_email_white_list' => [
                            'subject' => $message->getSubject(),
                            'to' => $item,
                        ]
                    ], 'warning');
                    return false;
                }
            }
        }

        LoggerHelper::logEmail([
            'start' => [
                'subject' => $message->getSubject(),
                'to' => $to,
            ],
        ]);
    }
}
