<?php

namespace App\Components\UniqueGenerator;

use App\Components\UniqueGenerator\Traits\DatabaseCheckTrait;
use App\Components\UniqueGenerator\Traits\PrefixTrait;
use App\Components\UniqueGenerator\Traits\RandomTrait;
use App\Helper\CountryHelper;
use Carbon\Carbon;
use RuntimeException;

/**
 * 按照天生成
 */
class DateGenerator
{
    use PrefixTrait;
    use RandomTrait;
    use DatabaseCheckTrait;

    private $redis;
    protected $shortYear = true; // 默认为 2 位年
    protected $timezone = 'auto'; // 自动根据当前session中的国家切换，如果没有默认为系统时区

    public function __construct()
    {
        $this->redis = app('redis');
    }

    /**
     * 使用 4 位年，默认为 2 位
     * @return $this
     */
    public function fullYear(): self
    {
        $this->shortYear = false;

        return $this;
    }

    /**
     * 指定国别
     * @param int $countryID 国家id，如美国 223
     * @return $this
     */
    public function country(int $countryID): self
    {
        $this->timezone = CountryHelper::getTimezone($countryID);

        return $this;
    }

    private $_maxLoop = 100;

    /**
     * 生成随机数
     * @return string
     */
    public function random(): string
    {
        $this->_maxLoop--;
        if ($this->_maxLoop <= 0) {
            throw new RuntimeException('Reach MAX loop!');
        }

        $this->checkServiceMust();

        $redis = $this->redis;
        $key = $this->getRedisKey(__FUNCTION__);

        $random = $this->getRandomInt();
        if ($redis->sadd($key, $random) === 0) {
            // redis 中重复
            return $this->random();
        }
        $redis->expireat($key, $this->getRedisExpireAt());

        $result = $this->buildResult($random);
        if ($this->checkDatabase && $this->checkDatabaseExist($result)) {
            // db 中重复
            return $this->random();
        }
        return $result;
    }

    /**
     * 生成递增值
     * @param int $from
     * @return string
     */
    public function increase($from = 0): string
    {
        $this->checkServiceMust();

        $redis = $this->redis;
        $key = $this->getRedisKey(__FUNCTION__);

        if ($from !== 0) {
            if (!$redis->get($key)) {
                $redis->set($key, $from);
            }
        }
        $current = $redis->incr($key);
        $redis->expireat($key, $this->getRedisExpireAt());

        $result = $this->buildResult($current);
        if ($this->checkDatabase && $this->checkDatabaseExist($result)) {
            return $this->increase();
        }
        return $result;
    }

    /**
     * redis 的 key
     * @param string $fnName
     * @return string
     */
    protected function getRedisKey(string $fnName): string
    {
        return 'unique_generator:' . $fnName . ':' . $this->getTodayDate() . ':' . $this->service;
    }

    private $_today;

    /**
     * 获取今日日期
     * @return string
     */
    protected function getTodayDate(): string
    {
        if (!$this->_today) {
            if ($this->timezone === 'auto') {
                if ($countryCode = session('country')) {
                    $this->timezone = CountryHelper::getTimezoneByCode($countryCode);
                } else {
                    $this->timezone = null;
                }
            }
            $this->_today = Carbon::now($this->timezone)
                ->format($this->shortYear ? 'ymd' : 'Ymd');
        }

        return $this->_today;
    }

    /**
     * 获取 redis 键过期时间
     * @return int
     */
    protected function getRedisExpireAt(): int
    {
        return Carbon::now()
            ->addDay(3) // +N天，因为存在时区问题，因此不能直接取明天
            ->hour(0) // 0 时
            ->minute(5) // 5分，保留一定时间，安全保证
            //->second() // 不重置秒，秒级误差无所谓
            ->getTimestamp();
    }

    /**
     * @param int $number
     * @return string
     */
    protected function buildResult(int $number): string
    {
        return $this->prefix . $this->getTodayDate() . $this->padLeftDigit($number);
    }
}
