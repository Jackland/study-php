<?php

namespace App\Console\Commands;

use App\Models\SellerBill\MessageLog;
use Illuminate\Console\Command;
use  App\Models\Message\Message;

class SellerBill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seller:bill';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'seller bill send message';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        echo date("Y-m-d H:i:s", time()) . ' ------bill-message-start------' . PHP_EOL;
        MessageLog::handleMessage();
        MessageLog::sendMail(); // 发送邮件给管理员
        echo date("Y-m-d H:i:s", time()) . ' ------bill-message-end------' . PHP_EOL;
    }
}
