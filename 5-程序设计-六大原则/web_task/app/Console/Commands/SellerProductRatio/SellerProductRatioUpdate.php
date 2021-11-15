<?php

namespace App\Console\Commands\SellerProductRatio;

use App\Traits\CommandLoggerTrait;
use Illuminate\Console\Command;
use Throwable;

class SellerProductRatioUpdate extends Command
{
    use CommandLoggerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sellerProductRatio:update 
    {dateTime? : 时间(不指定就是当前时间)}
    {sellerId? : 店铺ID(不指定就是所有店铺)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'seller 价格比例更新';

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
        $apiUrl = config('app.b2b_url') . 'api/seller_product_ratio/updateRatio';
        // 如果不传seller id 查询所有
        $dateTime = $this->argument('dateTime');
        $sellerId = $this->argument('sellerId');
        if($dateTime){
            $dateTime = urlencode(str_replace('+', ' ', $dateTime));
            $apiUrl .= "&date_time={$dateTime}";
        }
        if ($sellerId) {
            $apiUrl .= "&seller_id={$sellerId}";
        }
        $this->logger(['request' => $apiUrl]);
        try {
            $context = [
                'http' => [
                    'timeout' => 30 * 60,
                ]
            ];
            $response = file_get_contents($apiUrl, false, stream_context_create($context));
        } catch (Throwable $e) {
            $this->logger(['error' => $e->getMessage()], 'error');
            throw $e;
        }
        $this->logger(['response' => $response]);
    }
}
