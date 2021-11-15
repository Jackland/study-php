<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Statistics\RequestRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Class OnlineStatistic
 * @package App\Console\Commands
 */
class OnlineStatistic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statistic:online {type?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计前一天登录的供应商/买家';

    protected $from = [];
    protected $to = [];
    protected $outCc = [];
    protected $innerCc = [];
    protected $cc = [];
    protected $ccBuyer = [];


    private $managers = [
        159 => '陈博文;王倩',
        158 => '高文华;李琦',
        1796 => '陈洁',
    ];
    // 高级客户经理id
    const HIGH_MANAGE_IDS = [4174];

    const ACCOUNT_MANAGE = 14;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->from = Setting::getConfig('statistic_online_email_from', [config('mail.from.address')]);
        $this->to = Setting::getConfig('statistic_online_email_to', []);
        $this->outCc = Setting::getConfig('statistic_online_email_outCc', []);
        $this->innerCc = Setting::getConfig('statistic_online_email_innerCc', []);
        $this->cc = Setting::getConfig('statistic_online_email_cc', []);
        $this->ccBuyer = Setting::getConfig('statistic_online_email_cc_buyer', []);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $type = $this->argument('type');
        $types = ['innerSeller','outSeller', 'buyer'];
        $type = empty($type) || !in_array($type, $types) ? 'buyer' : $type;
        $this->$type();
        return;
    }

    public function seller(): array
    {
        $RequestRecord = new RequestRecord();
        $results = $RequestRecord->getLastDayOnlineForSeller();
        //$managers = $RequestRecord->getAccountsByGroupId(static::ACCOUNT_MANAGE);

        $outContent = [];
        $innerContent = [];
        if (count($results)) {
            foreach ($results as $result) {

                $linkedManagerNames = $RequestRecord->getAccountManager($result->customer_id, static::ACCOUNT_MANAGE);
                $linkedManagerNames = array_unique(array_filter($linkedManagerNames));
                if($result->accounting_type == 2){
                    $outContent[] = [
                        $result->firstname.$result->lastname,
                        $result->screenname,
                        $result->country_name,
                        empty($linkedManagerNames) ? '' : implode(';', $linkedManagerNames),
                    ];
                }

                if($result->accounting_type == 1){
                    $innerContent[] = [
                        $result->firstname.$result->lastname,
                        $result->screenname,
                        $result->country_name,
                        //empty($linkedManagerNames) ? '' : implode(';', $linkedManagerNames),
                    ];
                }
            }
        }

        return [
            'outContent' => $outContent,
            'innerContent' =>$innerContent,
        ];
    }

    private function outSeller()
    {
        $sellerData = $this->seller();
        if(!$sellerData['outContent']){
            return;
        }
        $header = ['Seller Code', '店铺名称', '国别', '运营顾问'];
        $data = [
            'subject' => '每日不在线外部供应商名单',
            'title' => date('Y-m-d', strtotime("-1 day")) . '（US Time）每日不在线外部供应商名单',
            'header' => $header,
            'content' => $sellerData['outContent'],
        ];
        \Mail::to($this->to)
            ->cc($this->outCc)
            ->send(new \App\Mail\OnlineStatistic($data, $this->from));
        $send_data = ['to' => $this->to, 'cc' => $this->outCc];
        Log::info(json_encode(array_merge($data, $send_data)));
        echo date('Y-m-d H:i:s') . ' statistic:online seller 外部发送成功' . PHP_EOL;
    }


    private function innerSeller()
    {
        $sellerData = $this->seller();
        if(!$sellerData['innerContent']){
            return;
        }
        $header = ['Seller Code', '店铺名称', '国别'];
        $data = [
            'subject' => '每日不在线内部供应商名单',
            'title' => date('Y-m-d', strtotime("-1 day")) . '（US Time）每日不在线内部供应商名单',
            'header' => $header,
            'content' => $sellerData['innerContent'],
        ];
        \Mail::to($this->to)
            ->cc($this->innerCc)
            ->send(new \App\Mail\OnlineStatistic($data, $this->from));
        $send_data = ['to' => $this->to, 'cc' => $this->innerCc];
        Log::info(json_encode(array_merge($data, $send_data)));
        echo date('Y-m-d H:i:s') . ' statistic:online seller 内部发送成功' . PHP_EOL;
    }

    /**
     * buyer 多抄送给 buyerManager@sz.oristand.com
     */
    public function buyer()
    {
        $this->cc = array_unique(array_merge($this->cc, $this->ccBuyer));

        $RequestRecord = new RequestRecord();
        $results = $RequestRecord->getLastDayOnlineForBuyer();
        if (count($results)) {
            $header = ['UserName', '内/外部', 'Country', '招商BD'];
            $content = [];
            foreach ($results as $result) {
                $content[] = [
                    $result->firstname . $result->lastname,
                    $result->accounting_type == 1 ? '内部' : '外部',
                    $result->country_name,
                    $result->bd_id ? ($result->bd_firstname . ' ' . $result->bd_lastname) : '',
                ];
            }
            $data = [
                'subject' => '每日在线平台买家名单',
                'title' => date('Y-m-d', strtotime("-1 day")) . '（US Time）在线的平台买家列表',
                'header' => $header,
                'content' => $content,
            ];
            \Mail::to($this->to)
                ->cc($this->cc)
                ->send(new \App\Mail\OnlineStatistic($data, $this->from));
            $send_data = ['to' => $this->to, 'cc' => $this->cc];
            Log::info(json_encode(array_merge($data, $send_data)));
            echo date('Y-m-d H:i:s') . ' statistic:online buyer 发送成功' . PHP_EOL;
        }
    }
}
