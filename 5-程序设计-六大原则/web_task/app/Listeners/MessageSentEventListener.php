<?php

namespace App\Listeners;

use App\Helpers\LoggerHelper;
use Illuminate\Mail\Events\MessageSent;

class MessageSentEventListener
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
     * @param MessageSent $event
     * @return void
     */
    public function handle(MessageSent $event)
    {
        LoggerHelper::logEmail([
            'end' => [
                'subject' => $event->message->getSubject(),
            ],
        ]);
    }
}
