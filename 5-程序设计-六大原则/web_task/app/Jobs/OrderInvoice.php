<?php

namespace App\Jobs;

use App\Enums\Message\MsgMsgType;
use App\Helpers\LoggerHelper;
use App\Models\Message\Msg;
use App\Services\Message\MessageService;
use App\Services\Order\OrderInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\HttpClient\HttpClient;

class OrderInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $timeout = 1800;
    public $sleep = 60;
    public $buyerId;

    public function __construct($data = '')
    {
        $this->buyerId = $data;
    }

    public function handle()
    {
        LoggerHelper::logOrderInvoice('Invoice-开始:' . $this->buyerId, 'info');

        app(OrderInvoiceService::class)->generate($this->buyerId);

        LoggerHelper::logOrderInvoice('Invoice-结束:' . $this->buyerId, 'info');
    }

}
