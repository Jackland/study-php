<?php

namespace App\Console\Commands;

use App\Models\Rma\NoReasonRma;
use App\Models\Statistics\SellCountModel;
use Illuminate\Console\Command;

class RmaTimeOut extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rma:timeout';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'rma timeout';

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
        echo date("Y-m-d H:i:s",time()).' ------rma-timeout-start------' . PHP_EOL;
        NoReasonRma::rmaTimeOut();
        echo date("Y-m-d H:i:s",time()).' ------rma-timeout-end------' . PHP_EOL;
    }
}
