<?php

namespace app\Console\Commands\Safeguard;

use App\Helpers\CountryHelper;
use Illuminate\Console\Command;
use App\Models\Safeguard\AutoBuyPlan;
use Illuminate\Support\Carbon;

class AutoBuyPlanExpired extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'safeguard:auto-buy-plan-expired {country}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动购买配置即将过期通知seller';

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
     * @throws \Exception
     */
    public function handle()
    {
        $country = $this->argument('country');
        if (!is_numeric($country) || $country <= 0) {
            $this->error('country 国家必须是大于0 数字');
            return;
        }
        $expiredPreSevenDate = Carbon::now()->addDay(6)->timezone(CountryHelper::getTimezone($country))->toDateString() . ' 23:59:59';
        $expiredNowDate = Carbon::now()->timezone(CountryHelper::getTimezone($country))->toDateString() . ' 23:59:59';
        echo date("Y-m-d H:i:s", time()) . ' ------safeguard-auto-plan-seller-start------' . PHP_EOL;
        AutoBuyPlan::getExpiredPlanByCountryAndDate($country, $expiredNowDate, $expiredPreSevenDate);
        echo date("Y-m-d H:i:s", time()) . ' ------safeguard-auto-plan-seller-end------' . PHP_EOL;
    }
}