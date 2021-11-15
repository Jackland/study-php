<?php

namespace App\Console\Commands\MarketingCampaign;

use App\Models\MarketingCampaign\MarketingCampaign;
use App\Models\Message\Message;
use Illuminate\Console\Command;

class NotifySeller extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:mc-seller';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '促销活动报名开始后通知Seller';

    /**
     * @var $model
     */
    private $model;

    /**
     * @var array $country_sellers
     */
    private $country_sellers;

    /**
     *
     * @var string $url_mc_apply_list
     */
    private $url_mc_apply_page;

    /**
     * @var array $result
     */
    private $result;

    private $time_zone = [
        '223' => 'America/Los_Angeles',
        '222' => 'Europe/London',
        '107' => 'Asia/Tokyo',
        '81' => 'Europe/Berlin'
    ];

    private $country_time = [
        '223' => 'Pacific Time',
        '222' => 'London time',
        '107' => 'Tokyo time',
        '81' => 'Berlin time'
    ];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->model = new MarketingCampaign();
        $this->url_mc_apply_page = (config('app.b2b_url') ?: 'https://b2b.gigacloudlogistics.com/index.php?route=')
            . 'customerpartner/marketing_campaign/detail&id=';
    }

    /**
     * Execute the console command.
     *
     * @throws \Exception
     */
    public function handle()
    {
        $activityObjs = $this->model->getUnNotifyActivity();
        foreach ($activityObjs as $activityObj) {
            $this->result[$activityObj->country_id][$activityObj->id] = 0;
            $this->sendMessageToSellers($activityObj);
            $this->model->setNoticed($activityObj->id);
        }

        // Out information
        if (empty($this->result)) {
            $out_str = date('Y-m-d H:i:s') . ' notify:mc-seller: no notify.';
        } else {
            $out_str = date('Y-m-d H:i:s') . ' notify:mc-seller:';
            foreach ($this->result as $country_id => $act_num) {
                $temp = [];
                foreach ($act_num as $act_id => $num) {
                    $temp[] = $act_id . ':' . $num;
                }
                $out_str .= $country_id . '(' . implode(',', $temp) . ') ';
            }
        }
        echo $out_str . PHP_EOL;
    }

    /**
     * @param object $activity
     * @throws \Exception
     */
    public function sendMessageToSellers($activity)
    {
        if (!isset($this->country_sellers[$activity->country_id])) {
            $this->country_sellers[$activity->country_id] = $this->model->getSellersByCountry($activity->country_id);
        }
        $Message = new Message();
        $title = '[Promotion]' . ($activity->seller_activity_name);
        $content = $this->getMessageContent($activity);

        foreach ($this->country_sellers[$activity->country_id] as $seller) {
            $Message->addSystemMessage('other', $title, $content, $seller->customer_id);
            $this->result[$activity->country_id][$activity->id]++;
        }
    }

    /**
     * @param object $activity
     * @return string
     * @throws \Exception
     */
    public function getMessageContent($activity)
    {
        $this_url_mc_apply_page = $this->url_mc_apply_page . $activity->id;

        $top_img_url = config('app.b2b_host') . 'image/email/202006/top.jpg';
        $logo_img_url = config('app.b2b_host') . 'image/email/202006/GIGALogo.png';
        $activity_time = $this->exchangeTime($activity->effective_time, $activity->country_id, 'F j')
            . ' - ' . $this->exchangeTime($activity->expiration_time, $activity->country_id, 'F j');
        $activity_time = strtoupper($activity_time);
        $apply_end_time = $this->exchangeTime($activity->apply_end_time, $activity->country_id, 'F j, gA');
        $apply_end_time = strtoupper($apply_end_time) . ' ' . $this->country_time[$activity->country_id];
        return <<<HTML
    <style>
        p{ margin: 0; }
        .oppo-inner-top{ text-align: center; padding: 40px 0; background: url("{$top_img_url}") no-repeat; background-size: cover; }
        .title-top{ font-size: 36px; font-weight: bolder; color: #fff; }
        .sub-title{ font-size: 22px; color: #fff; margin: 10px 0 40px 0; }
        .oppo-main-container{ width:60%; margin: 0 auto; }
        .oppo-inner-top img{ width: 100%; }
        .oppo-inner-con{ padding: 30px; text-align: center; background-color: white; }
        .top-title{ font-size: 18px; }
        .top-title p{ font-size: 20px; color: #183464; font-weight: 600; padding-bottom: 10px; border-bottom: 1px solid #888; margin-bottom: 10px; text-align: left; }
        .oppo-logo{ margin: 25px; }
        .june{ font-weight: 600; margin: 30px; color: #353535; font-size: 18px }
        .june-desc{ color: #353535!important; font-size: 16px!important;text-align: -webkit-auto;}
        .june span{ color: #353535!important; }
        .june-title{ color: #000; font-weight: bolder; margin: 30px; font-size: 22px; }
        .promotion{ color: #000; font-weight: bolder; margin: 40px; font-size: 26px } 
        .my-register-btn{ 
            color: rgba(255,255,255,0.85)!important; background: #FA6400; font-size: 22px; 
            padding: 12px 46px; outline:none; border: 1px solid #FA6400; box-shadow: 5px 5px 5px rgba(0,0,0,.3); 
            text-decoration: none!important; 
        } 
        .my-register-btn:hover,.my-register-btn:focus,.my-register-btn:active{ color: #fff; cursor: pointer; }
        .june.mrt10{ margin-top: 10px; }
    </style>
<div class="oppo-main-container">
    <div class="oppo-inner-top">
        <p class="title-top">PROMOTION OPPORTUNITY</p>
        <p class="sub-title">Limited Slots Available</p>
        <a href="{$this_url_mc_apply_page}" style="display: inline-block; background: #FA6400; color: rgba(255,255,255,0.85); font-size: 26px; padding: 10px 20px; text-decoration: none;">RESERVE YOUR SLOT</a>
    </div>
    <div class="oppo-inner-con">
        <div class="top-title"> <p>Sign your store up for our upcoming Marketplace promotion and gain instant exposure to thousands of Buyers.</p> </div>
        <div class="oppo-logo"> <img src="{$logo_img_url}" alt="Logo"/> </div>
        <div class="june" style="color: #000"> The following promotion is now open for registration: </div>
        <div class="promotion"> {$activity->seller_activity_name} </div>
        <div class="june june-desc"> {$activity->description} </div>
        <div class="june-title" style="margin-bottom: 10px"> Promotion Period </div>
        <div class="june mrt10"> {$activity_time} </div >
        <div class="june-title" style="margin-bottom: 10px"> Registration Closes </div>
        <div class="june mrt10"> {$apply_end_time} </div>
        <div> <a href="{$this_url_mc_apply_page}" class="btn my-register-btn">REGISTER NOW</a> </div>
    </div>
</div>
HTML;
    }

    /**
     * 其他时区转为 太平洋时区
     *
     * @param string $time
     * @param int $country_id
     * @param string $format
     * @return string
     * @throws \Exception
     */
    private function exchangeTime($time, $country_id, $format = 'Y-m-d H:i:s'): string
    {
        if (!in_array($country_id, array_keys($this->time_zone))) {
            return $time;
        }

        if ($country_id == 223) {
            return date($format, strtotime($time));
        }

        return (new \DateTime($time, new \DateTimeZone($this->time_zone[223])))
            ->setTimezone(new \DateTimeZone($this->time_zone[$country_id]))
            ->format($format);
    }
}
