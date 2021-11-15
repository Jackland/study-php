<?php

namespace App\Console\Commands\BuyerSeller;

use App\Models\Buyer\BuyerSellerRecommend;
use App\Models\Message\StationLetter;
use App\Models\Message\StationLetterCustomer;
use App\Models\Message\StationLetterObject;
use Illuminate\Console\Command;
use Throwable;

class Recommend extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'buyerSeller:recommend {country : 指定国家，如：223}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'buyer seller 推荐';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $country = $this->argument('country');
        if (!is_numeric($country) || $country <= 0) {
            $this->error('country 国家必须是大于0 数字');
            return;
        }

        $apiUrl = config('app.b2b_url') . 'api/buyer_seller/recommend&' . http_build_query([
                'c' => (int)$country,
            ]);
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
        $data = json_decode($response, true);
        if ($data['data']['batchDate'] !== false) {
            // 推荐成功发送站内信
            $this->sendLetter($data['data']['batchDate']);
            $this->logger(['send letter' => 'over']);
        }
    }

    protected function sendLetter(string $batchDate)
    {
        $recommends = BuyerSellerRecommend::query()
            ->with([
                'buyerCustomer',
                'seller'
            ])
            ->where('created_at', $batchDate)
            ->get()
            ->groupBy('buyer_id');
        $datetime = date('Y-m-d H:i:s');
        $messageSettingUrl = config('app.b2b_url') . 'message/setting';
        $storeUrl = config('app.b2b_url') . 'customerpartner/profile&id=';
        $templateContent = file_get_contents(__DIR__ . '/letter_content.html');
        foreach ($recommends as $buyerId => $buyerRecommends) {
            $storeNames = $buyerRecommends->map(function (BuyerSellerRecommend $item) use ($storeUrl) {
                return "<b><u><a href='{$storeUrl}{$item->seller->customer_id}' target='_blank'>{$item->seller->screenname}</a></u></b>";
            });
            $storeNamesCN = implode('、', $storeNames->toArray());
            $storeNamesEN = implode('&nbsp;&nbsp;&nbsp;&nbsp;', $storeNames->toArray());
            /** @var BuyerSellerRecommend $recommend */
            $recommend = $buyerRecommends[0]; // 一定存在

            $content = strtr($templateContent, [
                '{{storeNamesCN}}' => $storeNamesCN,
                '{{storeNamesEN}}' => $storeNamesEN,
                '{{messageSettingUrl}}' => $messageSettingUrl,
                '{{dateEN}}' => $recommend->created_at->format('F j, Y'),
                '{{dateCN}}' => $recommend->created_at->format('Y/m/j'),
            ]);
            // 保存站内信
            $data = [
                'status' => 1, // 因为是立即发送，因此直接标记为已发送
                'title' => 'Complimentary Seller Recommendations by Giga Cloud Marketplace for You 平台已为您推荐了匹配的店铺',
                'type' => 5, // 5其他
                'content' => $content,
                'is_send_all' => 0, // 非发送全部
                'send_object' => $this->getLetterSendObject($buyerRecommends[0]->buyerCustomer->country_id), // 发送对象
                'is_send_immediately' => 1, // 立即发送
                'send_time' => $datetime,
                'create_user_name' => 'b2b',
                'create_time' => $datetime,
                'update_user_name' => 'b2b',
                'update_time' => $datetime,
            ];
            $id = StationLetter::query()->insertGetId($data);
            // 站内信发送给谁
            $data = [
                'letter_id' => $id,
                'customer_id' => $buyerId,
                'create_user_name' => 'b2b',
                'create_time' => $datetime,
                'update_user_name' => 'b2b',
                'update_time' => $datetime,
            ];
            StationLetterCustomer::query()->insert($data);
            // 发送站内信邮件
            StationLetter::sendStationLetterEmail($id);
        }
    }

    private $_stationLetterObjectMap = false;

    protected function getLetterSendObject($buyerCountryId)
    {
        if ($this->_stationLetterObjectMap === false) {
            $this->_stationLetterObjectMap = StationLetterObject::query()
                ->where('identity', 2) // 外部buyer
                ->get()
                ->pluck('id', 'country_id');
        }
        return $this->_stationLetterObjectMap[$buyerCountryId] ?? 6; // 6表示外部-buyer(全部)
    }

    protected function logger($msg, $type = 'info')
    {
        $msg = is_array($msg) ? json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $msg;
        $this->$type(date('Y-m-d H:i:s') . ': ' . $msg);
    }
}
