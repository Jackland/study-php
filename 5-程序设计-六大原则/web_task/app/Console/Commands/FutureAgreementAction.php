<?php

namespace App\Console\Commands;

use App\Models\Future\Agreement;
use App\Models\Future\Apply;
use App\Models\Future\MarginDelivery;
use Illuminate\Console\Command;

class FutureAgreementAction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'future:agreement-action';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'future agreement time out';

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
        echo date("Y-m-d H:i:s",time()).' ------future-start------' . PHP_EOL;
        Agreement::setTimeOutFutureAgreement();// 协议超时
        MarginDelivery::stockMarginTimeOut(); // Buyer24小时内未支付现货保证金
        Apply::applyTimeOut(); //seller 提交的申请一直没有同意直到最后一天需要被置为拒绝
        Apply::appealApplyTimeOut(); //seller 提交的申诉申请一直没有同意直到最后一天需要被置为拒绝
        Agreement::FutureAgreementCompletedPayRecord(); // 转期货7天之后completed
        Agreement::FutureAgreementUncompletedPayRecord(); //转期货7天之后不能买 过期了
        echo date("Y-m-d H:i:s",time()).' ------future-end------' . PHP_EOL;
    }
}
