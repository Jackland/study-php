<?php

namespace App\Widgets;

use App\Repositories\Customer\CustomerRepository;
use Framework\Widget\Widget;

class PhoneNeedVerifyNoticeWidget extends Widget
{
    const SESSION_KEY_NOT_NOTICE = '__PHONE_VERIFY_NOTICE_HIDE_';

    public function run()
    {
        if (!static::isNeedNotice()) {
            return '';
        }

        return $this->getView()->render('@widgets/phone_need_verify_notice', [
            'can_close' => self::canClose(),
        ]);
    }

    public static function isNeedNotice(): bool
    {
        if (!customer()->isLogged()) {
            return false;
        }
        if (self::canClose() && session()->get(self::SESSION_KEY_NOT_NOTICE . customer()->getId()) === true) {
            return false;
        }
        if (in_array(request('route'), [
            'account/phone/verify', // 手机号验证页面
            'account/service_agreement', // 服务协议页面
            'account/service_agreement/index', // 服务协议页面
        ])) {
            return false;
        }
        $customer = customer()->getModel();
        if (!app(CustomerRepository::class)->isPhoneNeedVerify($customer)) {
            return false;
        }
        return true;
    }

    public static function canClose(): bool
    {
        // 上线后是不允许关闭的
        // 开发模式下为了便捷允许关闭
        return OC_DEBUG;
    }

    public static function rememberNotNotice()
    {
        if (!customer()->isLogged()) {
            return;
        }
        if (!self::canClose()) {
            // 不可关闭时不做关闭一次后记录
            return;
        }
        session()->set(self::SESSION_KEY_NOT_NOTICE . customer()->getId(), true);
    }

    public static function clearNotNoticeRemember()
    {
        if (!customer() || !customer()->isLogged()) {
            return;
        }
        session()->remove(self::SESSION_KEY_NOT_NOTICE . customer()->getId());
    }
}
