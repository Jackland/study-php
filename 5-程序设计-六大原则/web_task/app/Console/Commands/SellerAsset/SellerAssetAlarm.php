<?php

namespace App\Console\Commands\SellerAsset;

use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Message\Message;
use App\Traits\CommandLoggerTrait;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class SellerAssetAlarm extends Command
{
    use CommandLoggerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sellerAsset:alarm {sellerId? : 店铺ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'seller资产监控报警';

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
     * @throws \Exception
     */
    public function handle()
    {
        $apiUrl = config('app.b2b_url') . 'api/seller_asset/getAlarmSeller';
        // 如果不传seller id 查询所有
        $sellerId = $this->argument('sellerId');
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
        $response= json_decode($response, true);
        if ($response['code'] != 200 || empty($response['data'])) {
            $this->logger(['info' => '无须提醒'], 'info');
            return;
        }
        $data = $response['data'];
        //获取所有seller的名称
        $allSellerIds = array_merge($data['alarm_1']['seller_ids'], $data['alarm_2']['seller_ids'], $data['alarm_3']['seller_ids']);
        if (empty($allSellerIds)) {
            $this->logger(['info' => '无须提醒'], 'info');
            return;
        }
        $allSellers = CustomerPartnerToCustomer::whereIn('customer_id', $allSellerIds)->get();
        $sellerStoreNameList = [];
        foreach ($allSellers as $seller) {
            $sellerStoreNameList[$seller->customer_id] = $seller->screenname;
        }
        $subject = "资产金额不足$%s通知";
        $body = "<p>Dear Giga Cloud Logistics supplier 【%s】:</p><br><p>您的资产剩余金额" .
            "不足【$%s】，当资产金额低于0时，根据您与平台签订的合同协议，平台有权下架您的在售商品并且无偿处置（包括但不限于销售，拍卖" .
            "等），为了您的经营活动不受影响（如果您有纯物流业务，资产不足可能会影响您的订单发货），您可以通过向平台支付欠款或者新增抵押物的方式来增" .
            "加您的资产金额。</p><br><br><p align='right'>The Giga Cloud Logistics B2B Marketplace</p><br><p align='right'>%s</p>";
        $messageType = 600;
        $timeStr =  Carbon::now()->toDateString();
        if (!empty($data['alarm_1']['seller_ids'])) {
            $toSubject = sprintf($subject, $data['alarm_1']['max']);
            foreach ($data['alarm_1']['seller_ids'] as $sellerId) {
                $toBody = sprintf($body,
                    $sellerStoreNameList[$sellerId] ?? '',
                    $data['alarm_1']['max'],
                    $timeStr
                );
                Message::addSystemMessage('invoice', $toSubject, $toBody, $sellerId);
            }
        }
        if (!empty($data['alarm_2']['seller_ids'])) {
            $toSubject = sprintf($subject, $data['alarm_2']['max']);
            foreach ($data['alarm_2']['seller_ids'] as $sellerId) {
                $toBody = sprintf($body,
                    $sellerStoreNameList[$sellerId] ?? '',
                    $data['alarm_2']['max'],
                    $timeStr
                );
                Message::addSystemMessage('invoice', $toSubject, $toBody, $sellerId);
            }
        }
        if (!empty($data['alarm_3']['seller_ids'])) {
            // 三级是资产变为0的提醒，内容和二级一样
            $toSubject = sprintf($subject, $data['alarm_2']['max']);
            foreach ($data['alarm_3']['seller_ids'] as $sellerId) {
                $toBody = sprintf($body,
                    $sellerStoreNameList[$sellerId] ?? '',
                    $data['alarm_2']['max'],
                    $timeStr
                );
                Message::addSystemMessage('invoice', $toSubject, $toBody, $sellerId);
            }
        }
    }
}
