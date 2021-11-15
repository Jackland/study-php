<?php

namespace App\Enums\Pay;

use Carbon\Carbon;
use Framework\Enum\BaseEnum;

/**
 * 支付方式code
 * Class PayCode
 *
 * @package App\Enums\Pay
 */
class PayCode extends BaseEnum
{
    const PAY_WECHAT = 'wechat_pay'; // 微信支付
    const PAY_UMF = 'umf_pay'; // 联动支付
    const PAY_LINE_OF_CREDIT = 'line_of_credit';  // 信用额度支付
    const PAY_VIRTUAL = 'virtual_pay'; // 虚拟支付
    const PAY_CREDIT_CARD = 'cybersource_sop'; // 信用卡支付
    const PAY_COD = 'cod'; // 已废弃

    public static function getViewItems()
    {
        return [
            static::PAY_WECHAT => 'WeChat Pay',
            static::PAY_UMF => 'UnionPay',
            static::PAY_LINE_OF_CREDIT => 'Line Of Credit',
            static::PAY_VIRTUAL => 'Virtual Pay',
            static::PAY_CREDIT_CARD => 'Credit Card',
            static::PAY_COD => 'Cod',
        ];
    }

    /**
     * @var null
     */
    private static $_poundageCalculateTime = null;

    /**
     * 设置费率计算的时间点
     * 注意该方法为全局设置，调用后会影响后续所有调用 getPoundage 的点
     * 这样处理的原因是调用 getPoundage 的点过于分散，且很多地方无法立即取到订单的创建时间
     * 提供该方法的原因是：费率的切换按照订单创建时间来控制，比如3点调整费率，3点前的费率按旧的算，3点后的按新的算
     * @param $time
     */
    public static function setPoundageCalculateTime($time)
    {
        if ($time === null) {
            $time = time();
        } elseif ($time instanceof Carbon) {
            $time = $time->getTimestamp();
        } elseif (is_string($time)) {
            $time = strtotime($time);
        }

        static::$_poundageCalculateTime = $time;
    }

    /**
     * 获取费率
     * @param string $code
     * @return float
     */
    public static function getPoundage(string $code): float
    {
        // 定时调整逻辑，代码不删除，防止后期还有需要定时处理的
//        $time = static::$_poundageCalculateTime !== null ? static::$_poundageCalculateTime : time();
//        $isChange = $time >= strtotime('2021-06-03 00:00:00'); // 定时调整费率
        $map = [
            static::PAY_WECHAT => 0.0131,
            static::PAY_UMF => 0.0101,
            static::PAY_LINE_OF_CREDIT => customer()->isLogged() && customer()->getAdditionalFlag() == 1 ? 0.01 : 0,
            static::PAY_VIRTUAL => 0,
            static::PAY_CREDIT_CARD => 0.028,
            static::PAY_COD => 0,
        ];
        return $map[$code] ?? 0;
    }

    /**
     * 获取带费率的描述
     * @param string $code
     * @param string $unknown
     * @return string
     */
    public static function getDescriptionWithPoundage(string $code, $unknown = ''): string
    {
        $desc = static::getDescription($code, null);
        if ($desc === null) {
            return $unknown;
        }
        $poundage = static::getPoundage($code);
        if ($poundage > 0) {
            $desc .= ' (+' . ($poundage * 100) . '%)';
        }
        return $desc;
    }

    /**
     * 获取支持的支付方式
     * @return array
     */
    public static function getSupportedPayCodes(): array
    {
        // 注意排序
        return [
            static::PAY_LINE_OF_CREDIT,
            static::PAY_UMF,
            static::PAY_WECHAT,
            static::PAY_CREDIT_CARD,
        ];
    }
}
