<?php

return [
    'listen' => [
        // EventClass => [ListenerClass, ListenerClass]
        \Framework\Http\Events\ResponseBeforeSend::class => [
            \App\Listeners\ResponseBeforeSendListener::class,
        ],
        \App\Listeners\Events\SendMsgMailEvent::class => [
            \App\Listeners\SendMsgMailListener::class,
        ],
        \Illuminate\Console\Events\CommandStarting::class => [
            \App\Listeners\CommandStartingListener::class,
        ],
    ],
    'subscribe' => [
        // SubscribeClass
        \App\Listeners\DatabaseExecEventSubscriber::class,
        \App\Listeners\TimelineEventSubscriber::class,
        \App\Listeners\CustomerLogoutEventSubscriber::class,
    ],
];
