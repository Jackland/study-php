<?php

namespace App\Console\Commands\StorageFee;

use App\Helpers\CountryHelper;
use App\Traits\CommandLoggerTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class CalculateByCountry extends Command
{
    use CommandLoggerTrait;

    /**
     * @var string
     */
    protected $signature = 'storageFee:calculate-country
                            {country : 指定国家，如：223}
                            {--d|date=today : 指定日期，默认为今天，如：2020-10-12}
                            {--t|timezone= : 指定时区，默认会根据国家自动计算，如：Europe/Berlin}
                            {--f|force : 若该日期已经计算过，使用该参数删除已经计算的并重新计算}
                            ';

    /**
     * @var string
     */
    protected $description = '按国家计算每日仓租';

    public function handle()
    {
        $country = $this->argument('country');
        if (!is_numeric($country) || $country <= 0) {
            $this->error('country 国家必须是大于0 数字');
            return;
        }
        $carbon = Carbon::now();
        $timezone = $this->option('timezone');
        if (!$timezone) {
            $timezone = CountryHelper::getTimezone($country);
        }
        if ($timezone) {
            $carbon->setTimezone($timezone);
        }
        $date = $this->option('date');
        if ($date === 'today') {
            $date = $carbon->format('Y-m-d');
        } else {
            if ($date !== (new Carbon($date))->format('Y-m-d')) {
                $this->error('date 日期格式错误');
                return;
            }
        }
        $force = (bool)$this->option('force');

        $apiUrl = config('app.b2b_url') . 'api/storage_fee/calculateByDay&' . http_build_query([
                'd' => $date,
                'c' => (int)$country,
                'f' => (int)$force,
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
    }
}