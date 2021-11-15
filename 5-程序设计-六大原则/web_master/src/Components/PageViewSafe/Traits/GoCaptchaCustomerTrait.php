<?php

namespace App\Components\PageViewSafe\Traits;

use Framework\Helper\StringHelper;

trait GoCaptchaCustomerTrait
{
    /**
     * 当 $goCaptchaWhenTrigger 为 true 时，这些 customerId 不跳，优先判断 goCaptchaWhiteListIps
     * @see isNeedGoCaptcha
     * @var array
     */
    protected $goCaptchaWhiteListCustomerIds = [];
    /**
     * 当 $goCaptchaWhenTrigger 为 false 时，这些 customerId 任然会跳，优先判断 goCaptchaBlackListIps
     * @see isNeedGoCaptcha
     * @var array
     */
    protected $goCaptchaBlackListCustomerIds = [];

    /**
     * @param int $customerId
     * @return bool
     */
    protected function isNeedGoCaptchaContainCustomer(int $customerId): bool
    {
        $ip = $this->request->getUserIp();

        if ($this->goCaptchaWhenTrigger) {
            foreach ($this->goCaptchaWhiteListIps as $pattern) {
                if (StringHelper::matchWildcard($pattern, $ip)) {
                    return false;
                }
            }
            if (in_array($customerId, $this->goCaptchaWhiteListCustomerIds)) {
                return false;
            }
            return true;
        }

        foreach ($this->goCaptchaBlackListIps as $pattern) {
            if (StringHelper::matchWildcard($pattern, $ip)) {
                return true;
            }
        }
        if (in_array($customerId, $this->goCaptchaBlackListCustomerIds)) {
            return true;
        }

        return false;
    }
}
