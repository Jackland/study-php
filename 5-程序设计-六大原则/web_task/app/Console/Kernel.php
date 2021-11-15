<?php

namespace App\Console;

use App\Console\Commands\OnlineStatistic;
use App\Helpers\CountryHelper;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

/**
 * Class Kernel
 * @package App\Console
 */
class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        OnlineStatistic::class,
    ];

    /**
     * @todo 1. 后续新加的定时任务,也要在 scheduleNormal 这个function里面注册(并不执行,只做记录备份)
     * @todo 2. 同时 在xxl-job上添加定时任务 http://8.210.152.140:8080/xxl-job-admin/
     * @todo 3. 此方法注释,并不执行,且对应的系统级的 crontab 也已禁用.
     *
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     * @see https://learnku.com/docs/laravel/5.5/scheduling/1325#schedule-frequency-options 执行时间/频率设置
     */
    final protected function schedule(Schedule $schedule): void
    {
        // 迁移到新的服务器(8.210.152.140)
//        if (config('app.schedule.normal')) {
//            $this->scheduleNormal($schedule);
//        }
//        // 依旧在原服务器执行
//        if (config('app.schedule.origin')) {
//            $this->scheduleOrigin($schedule);
//        }
    }

    /**
     * @param Schedule $schedule
     * @return void
     */
    final private function scheduleNormal(Schedule $schedule): void
    {
        $schedule->command('statistic:online seller')->dailyAt('17:14')->appendOutputTo(storage_path('logs/console.log'));
        $schedule->command('statistic:online buyer')->dailyAt('17:14')->appendOutputTo(storage_path('logs/console.log'));
        $schedule->command('clear:invalid_delicacy_management')->hourlyAt('23')->appendOutputTo(storage_path('logs/console.log'));
        $schedule->command('rma:no-reason')->monthlyOn(1, "02:00")->appendOutputTo(storage_path('logs/console.log'));
        $schedule->command('purchaseOrder:cancel')->cron("*/1 * * * *")->appendOutputTo(storage_path('logs/console.log'));

        //销售订单出库 美国时间16时
        $schedule->command('send:message sales_order')->dailyAt('16:00')->appendOutputTo(storage_path('logs/message.log'));

        //议价 每五分钟
        $schedule->command('send:message bid')->cron("*/5 * * * *")->appendOutputTo(storage_path('logs/message.log'));

        //保证金定时任务
        $schedule->command('margin:online approve_timeout')->hourly()->appendOutputTo(storage_path('logs/console.log'));
        $schedule->command('margin:online deposit_pay_timeout')->hourly()->appendOutputTo(storage_path('logs/console.log'));
        $schedule->command('margin:online dispatch_message')->hourly()->appendOutputTo(storage_path('logs/console.log'));
        $schedule->command('margin:online sold_will_expire')->dailyAt('00:20')->appendOutputTo(storage_path('logs/console.log'));
        $schedule->command('margin:online sold_expire')->hourly()->appendOutputTo(storage_path('logs/console.log'));
        //统计用户画像
        $schedule->command('statistic:userportrait')->dailyAt('00:30')->appendOutputTo(storage_path('logs/user_portrait.log'));

        // 仓租二期仓租费计算
        $storageFeeCountries = [
            81, // 德国
            107, // 日本
            222, // 英国
            223, // 美国
        ];
        foreach ($storageFeeCountries as $country) {
            $schedule->command("storageFee:calculate-country {$country}")->dailyAt('00:00')->timezone(CountryHelper::getTimezone($country))
                ->appendOutputTo(storage_path('logs/storage_fee.log'));
        }

        //产品价格变化率，当前时刻正在生效的原价:14天内改价的峰值
        $schedule->command('product:ChangePriceRate')->hourlyAt(30)->appendOutputTo(storage_path('logs/console.log'));
        $schedule->command('product:ChangePriceRateAll')->dailyAt('05:00')->appendOutputTo(storage_path('logs/console.log'));

        // rebate 状态值变化
        $schedule->command('statistic:rebate')->everyTenMinutes()->appendOutputTo(storage_path('logs/rebate.log'));

        //每天中午12点发送统计邮件   // 81:德 107:日 222:英 223:美
        //Buyer
        $schedule->command('statistic:rebate_remind 81 buyer')->timezone('Europe/Berlin')->dailyAt('12:00')->appendOutputTo(storage_path('logs/rebate_remind.log'));
        $schedule->command('statistic:rebate_remind 107 buyer')->timezone('Asia/Tokyo')->dailyAt('12:00')->appendOutputTo(storage_path('logs/rebate_remind.log'));
        $schedule->command('statistic:rebate_remind 222 buyer')->timezone('Europe/London')->dailyAt('12:00')->appendOutputTo(storage_path('logs/rebate_remind.log'));
        $schedule->command('statistic:rebate_remind 223 buyer')->timezone('America/New_York')->dailyAt('12:00')->appendOutputTo(storage_path('logs/rebate_remind.log'));

        //Seller 中国时间中午12点 四个国家一起执行
        $schedule->command('statistic:rebate_remind 223 seller')->timezone('Asia/Shanghai')->dailyAt('12:00')->appendOutputTo(storage_path('logs/rebate_remind.log'));

        //统计近30天产品售卖量
        $schedule->command('statistic:sell_count')->dailyAt('00:40')->appendOutputTo(storage_path('logs/sell_count.log'));

        $schedule->command('statistic:rebate-recharge 1')->timezone('Asia/Shanghai')->monthlyOn(3, '09:03')->appendOutputTo(storage_path('logs/rebate-recharge.log'));
        $schedule->command('statistic:rebate-recharge 2')->timezone('Asia/Shanghai')->monthlyOn(17, '09:03')->appendOutputTo(storage_path('logs/rebate-recharge.log'));

        //期货 超时检查
        $schedule->command('future:agreement')->cron("*/5 * * * *")->appendOutputTo(storage_path('logs/future.log'));

        //期货二期超时检查
        $schedule->command('future:agreement-action')->cron("*/5 * * * *")->appendOutputTo(storage_path('logs/future.log'));

        $schedule->command('future:daily-message 81')->timezone('Europe/Berlin')->dailyAt('12:00')->appendOutputTo(storage_path('logs/future.log'));
        $schedule->command('future:daily-message 107')->timezone('Asia/Tokyo')->dailyAt('12:00')->appendOutputTo(storage_path('logs/future.log'));
        $schedule->command('future:daily-message 222')->timezone('Europe/London')->dailyAt('12:00')->appendOutputTo(storage_path('logs/future.log'));
        $schedule->command('future:daily-message 223')->timezone('America/New_York')->dailyAt('12:00')->appendOutputTo(storage_path('logs/future.log'));

        $schedule->command('future:contract')->cron("0 */1 * * *")->appendOutputTo(storage_path('logs/future.log'));

        //商品退返率 每日更新一次
        $schedule->command('returnRate:update')->dailyAt('06:40')->appendOutputTo(storage_path('logs/console.log'));

        //店铺90天内的回复率
        $schedule->command('seller:ResponseRate')->dailyAt('07:40')->appendOutputTo(storage_path('logs/console.log'));

        //每天中午12点 销售单乱码 通过站内信提醒Buyer   // 81:德 107:日 222:英 223:美
        $schedule->command('sales_order:sales_order_messy_code_remind 81 buyer')->timezone('Europe/Berlin')->dailyAt('12:00')->appendOutputTo(storage_path('logs/sales_order_remind.log'));
        $schedule->command('sales_order:sales_order_messy_code_remind 107 buyer')->timezone('Asia/Tokyo')->dailyAt('12:00')->appendOutputTo(storage_path('logs/sales_order_remind.log'));
        $schedule->command('sales_order:sales_order_messy_code_remind 222 buyer')->timezone('Europe/London')->dailyAt('12:00')->appendOutputTo(storage_path('logs/sales_order_remind.log'));
        $schedule->command('sales_order:sales_order_messy_code_remind 223 buyer')->timezone('America/Los_Angeles')->dailyAt('12:00')->appendOutputTo(storage_path('logs/sales_order_remind.log'));


        //产品90天内的采购单金额
        $schedule->command('product:OrderMoney')->timezone('America/Los_Angeles')->dailyAt('05:40')->appendOutputTo(storage_path('logs/order_money.log'));

        /**
         * seller账单
         * 发送站内信 结算日每月1日和每月16日,美国时间3点
         */
        $schedule->command('seller:bill')->cron("0 3 1,16 * *")->appendOutputTo(storage_path('logs/seller-bill.log'));

        /**
         * seller账单
         * 非结算日,美国时间01:25
         */
        $schedule->command('seller:bill')->dailyAt("01:25")
            ->skip(function () {
                if (in_array(date('d'), [1, 16])) {
                    return true;
                } else {
                    return false;
                }
            })
            ->appendOutputTo(storage_path('logs/seller-bill.log'));


        // 促销：通知 Seller 活动的报名时间已开始
        $schedule->command('notify:mc-seller')->cron("3,18,33,48 * * * *")->appendOutputTo(storage_path('logs/mc-notice.log'));

        //上门取货销售单 to be paid -> on hold
        $schedule->command('update:salesOrderPickUpFromToBePaidToOnHold 81')->timezone('Europe/Berlin')->dailyAt('00:00')->appendOutputTo(storage_path('logs/sales-order.log'));
        $schedule->command('update:salesOrderPickUpFromToBePaidToOnHold 107')->timezone('Asia/Tokyo')->dailyAt('00:00')->appendOutputTo(storage_path('logs/sales-order.log'));
        $schedule->command('update:salesOrderPickUpFromToBePaidToOnHold 222')->timezone('Europe/London')->dailyAt('00:00')->appendOutputTo(storage_path('logs/sales-order.log'));
        $schedule->command('update:salesOrderPickUpFromToBePaidToOnHold 223')->timezone('America/Los_Angeles')->dailyAt('00:00')->appendOutputTo(storage_path('logs/sales-order.log'));
        //一件代发销售单 on hold 文件
        $schedule->command('update:salesOrder')->cron("0 */24 * * *")->appendOutputTo(storage_path('logs/sales-order.log'));

        //rma  超时检查
        $schedule->command('rma:timeout')->dailyAt('01:00')->appendOutputTo(storage_path('logs/rma-timeout.log'));

        $schedule->command('tracking:uploadPre')->hourlyAt('05')->appendOutputTo(storage_path('logs/tracking.log'));
        $schedule->command('tracking:upload')->hourlyAt('35')->appendOutputTo(storage_path('logs/tracking.log'));

        // 费用单自动取消
        $schedule->command('feeOrder:cancel');
        //5分钟 执行
        $schedule->command('order:monitor')->cron("* */8 * * *")->appendOutputTo(storage_path('logs/console.log'));

        // 定时发送消息， 每分钟执行一次
        $schedule->command('message:send-msg')->everyMinute()->appendOutputTo(storage_path('logs/console.log'));

        /**
         * buyer seller 推荐
         * 每月 1 号和 16 号
         * 所有国家都按照太平洋时间的6点执行，该时间需要在 JAVA 定时任务处的评分计算的后面，目前 JAVA 那边是 5 点
         */
        $buyerSellerRecommendCountries = [
            81, // 德国
            222, // 英国
            223, // 美国
        ];
        foreach ($buyerSellerRecommendCountries as $country) {
            $schedule->command("buyerSeller:recommend {$country}")->dailyAt('06:00')->twiceMonthly(1, 16)->appendOutputTo(storage_path('logs/buyer_seller_recommend.log'));
        }

        //seller资产监控报警
        $schedule->command('sellerAsset:alarm')
            ->everyFiveMinutes()
            ->appendOutputTo(storage_path('logs/seller-asset.log'));

        // 入库单：通知 Seller 填写集装箱信息
        $schedule->command('notify:receipts-order-seller')->dailyAt('01:00')->appendOutputTo(storage_path('logs/receipts-order-notify.log'));


        // 自动购买配置即将到期
        $safeguardAutoBuyCountries = [
            81, // 德国
            107, // 日本
            222, // 英国
            223, // 美国
        ];
        foreach ($safeguardAutoBuyCountries as $country) {
            $schedule->command("safeguard:auto-buy-plan-expired {$country}")->dailyAt('00:00')->timezone(CountryHelper::getTimezone($country))
                ->appendOutputTo(storage_path('logs/safeguard:auto-buy-plan-expired.log'));
        }

        // 采销协议
        $schedule->command('tripartite-agreement:active')->hourly()->appendOutputTo(storage_path(storage_path('logs/tripartite-agreement.log')));
        $schedule->command('tripartite-agreement:terminate')->hourly()->appendOutputTo(storage_path(storage_path('logs/tripartite-agreement.log')));
        $schedule->command('tripartite-agreement-request:reject')->everyMinute()->appendOutputTo(storage_path(storage_path('logs/tripartite-agreement.log')));
        $schedule->command('tripartite-agreement:cancel')->hourly()->appendOutputTo(storage_path(storage_path('logs/tripartite-agreement.log')));
        $schedule->command('tripartite-agreement-request:cancel')->hourly()->appendOutputTo(storage_path(storage_path('logs/tripartite-agreement.log')));

        // 释放buyer囤货预锁
        $schedule->command('release:buyer-inventory-pre-lock')->cron("*/1 * * * *")->appendOutputTo(storage_path('logs/console.log'));
    }

    /**
     * @param Schedule $schedule
     */
    final private function scheduleOrigin(Schedule $schedule): void
    {
        // 打包文件下载后缓存文件自动删除
        $schedule->command('clear:disk')
            ->hourlyAt('24')
            ->appendOutputTo(storage_path('logs/product_package.log'));

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    final protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
