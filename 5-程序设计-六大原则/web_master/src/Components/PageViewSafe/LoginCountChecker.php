<?php

namespace App\Components\PageViewSafe;

use App\Components\PageViewSafe\Traits\GoCaptchaCustomerTrait;
use App\Listeners\Events\CustomerLoginSuccessEvent;
use App\Logging\LogChannel;
use Cart\Customer;
use Framework\Cache\Cache;
use Framework\Helper\StringHelper;
use Framework\Http\Request;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;

class LoginCountChecker extends BaseChecker
{
    use GoCaptchaCustomerTrait;

    protected $logChannel = LogChannel::SAFE_CHECKER_LOGIN_COUNT;
    protected $cacheKeyPrefix = 'safeCheckerLoginCount.';

    protected $dispatcher;

    /**
     * 不计登录次数的 ip
     * @var array
     */
    public $whiteListIps = [];
    /**
     * 登录次数限制，配置为5，则登录成功第6次会跳验证码
     * @var array|int[]
     */
    public $limitCount = [
        'global' => 5, // 针对所有人
        //1 => 2, // 针对 1 这个用户配置数量
    ];
    /**
     * 统计登录次数的缓存时间
     * @var int
     */
    public $counterCacheTime = 12 * 3600;
    /**
     * 需要验证时缓存时间
     * @var int
     */
    public $shouldVerifyCacheTime = 3600;

    public function __construct(Customer $customer, Request $request, Cache $cache, Dispatcher $dispatcher, LoggerInterface $logger = null, $config = [])
    {
        parent::__construct($customer, $request, $cache, $logger, $config);

        $this->dispatcher = $dispatcher;
    }

    /**
     * @inheritDoc
     */
    protected function doCheck()
    {
        // 监听登录成功事件，登录成功后次数 +1
        $this->dispatcher->listen(CustomerLoginSuccessEvent::class, function (CustomerLoginSuccessEvent $event) {
            if ($event->passwordCheckType === CustomerLoginSuccessEvent::PASSWORD_NO_PASSWORD) {
                // 从 admin 登录的不计次
                return;
            }
            $customerId = $event->customer->customer_id;
            $ip = $this->request->getUserIp();
            // 在白名单中的不计次数
            foreach ($this->whiteListIps as $pattern) {
                if (StringHelper::matchWildcard($pattern, $ip)) {
                    return;
                }
            }
            // 计次
            $count = $this->cache->increment($this->getLoginCountCacheKey($customerId), 1, $this->counterCacheTime);
            $limitCount = $this->getLimitCount($customerId);
            if ($count > $limitCount) {
                // 触发之后访问下个页面时触发跳验证码
                $this->cache->set($this->getShouldVerifyCacheKey($customerId), 1, $this->shouldVerifyCacheTime);
                $this->writeTriggerLog(['customerId' => $customerId, 'count' => $count, 'limitCount' => $limitCount]);
            }
        });

        if (!$this->customer->isLogged()) {
            // 未登录的直接过
            return true;
        }
        $customerId = $this->customer->getId();
        if (!$this->shouldVerify($customerId)) {
            return true;
        }
        $this->writeTriggerLog(['should verify']);

        return $customerId;
    }

    /**
     * @inheritDoc
     */
    protected function isNeedGoCaptcha(): bool
    {
        return $this->isNeedGoCaptchaContainCustomer($this->customer->getId());
    }

    /**
     * @inheritDoc
     */
    public function pass($data)
    {
        $this->removeShouldVerify($data);
    }

    private function getLoginCountCacheKey(int $customerId)
    {
        return [__CLASS__, __FUNCTION__, $customerId];
    }

    private function getShouldVerifyCacheKey(int $customerId)
    {
        // 以 session 为基础，防止同一个帐号多处登录时一个帐号验证过其他的也验证过的情况
        return [__CLASS__, __FUNCTION__, $customerId, session()->getId()];
    }

    private function shouldVerify(int $customerId): bool
    {
        if ($this->cache->get($this->getShouldVerifyCacheKey($customerId), false)) {
            // 需要验证时再次检查是否超次数，因为存在需要验证后，修改了限制次数的可能
            $count = $this->cache->getIncrementCount($this->getLoginCountCacheKey($customerId), 0);
            $limitCount = $this->getLimitCount($customerId);
            if ($count <= $limitCount) {
                // 如果没超，删除需要验证的缓存
                $this->writeTriggerLog(['type' => 'should delete verify', 'count' => $count, 'limitCount' => $limitCount], 'info');
                $this->cache->delete($this->getShouldVerifyCacheKey($customerId));
                return false;
            }
            return true;
        }

        return false;
    }

    private function removeShouldVerify(int $customerId)
    {
        $this->cache->delete($this->getShouldVerifyCacheKey($customerId));
    }

    private function getLimitCount(int $customerId): int
    {
        if (isset($this->limitCount[$customerId])) {
            return $this->limitCount[$customerId];
        }
        return $this->limitCount['global'] ?? 5;
    }
}
