<?php

namespace App\Listeners;

use App\Listeners\Events\CustomerLogoutBeforeEvent;
use App\Widgets\PhoneNeedVerifyNoticeWidget;
use Illuminate\Contracts\Events\Dispatcher;

class CustomerLogoutEventSubscriber
{
    public function subscribe(Dispatcher $dispatcher)
    {
        $dispatcher->listen(CustomerLogoutBeforeEvent::class, function () {
            PhoneNeedVerifyNoticeWidget::clearNotNoticeRemember();
        });
    }
}
