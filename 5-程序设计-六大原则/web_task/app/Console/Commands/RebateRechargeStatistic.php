<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\Rebate\Agreement;
use App\Models\Setting;
use Illuminate\Console\Command;
use App\Mail\RebateRechargeStatistic as Mailer;

/**
 * Class RebateRechargeStatistic
 * @package App\Console\Commands
 */
class RebateRechargeStatistic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statistic:rebate-recharge {type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计返点协议完成后充值的金额';

    protected $model;

    /**
     * 排除掉的店铺
     *
     * @var array $no_in_groups
     */
    protected $no_in_groups;

    /**
     * @var array $country_ids
     */
    protected $country_ids = [
        81 => '德国',
        107 => '日本',
        222 => '英国',
        223 => '美国'
    ];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->model = new Agreement();
        $this->no_in_groups = [
            config('app.test_customer_group_id'),
        ];
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->argument('type');

        if (!in_array($type, [1, 2])) {
            $type = 1;
        }
        /**
         * 1: 上月16号00:00:00 ~ 上月最后一天23:59:59
         * 2：本月 1号00:00:00 ~ 本月15号 23:59:59
         */
        if ($type == 1) {
            $current_month_stamp = strtotime(date('Y-m-01'));
            $last_month_last_day_stamp = strtotime('-1 day', $current_month_stamp);
            $start_time_str = date('Y-m-16 00:00:00', $last_month_last_day_stamp);
            $end_time_str = date('Y-m-d 23:59:59', $last_month_last_day_stamp);
        } else {
            $start_time_str = date('Y-m-01 00:00:00');
            $end_time_str = date('Y-m-15 23:59:59');
        }
        $this->sendEmail($start_time_str, $end_time_str);
    }

    /**
     * @param $start_time
     * @param $end_time
     * @return mixed|void
     */
    private function sendEmail($start_time, $end_time)
    {
        $objs = $this->model->getRebateRecharge($start_time, $end_time, $this->no_in_groups);

        $header = [
            'Country',
            'Seller Type',
            'Seller Customer Name(Number)',
            'Store Name',
            'Buyer Customer Name(Number)',
            'Automatic Purchase Buyer',
            'Agreement ID',
            'Rebate Amount',
            'Operation Time(US Time)'
        ];
        $content = [];
        $ignore_id = [];    // 用于忽略重复数据
        foreach ($objs as $obj) {
            if (in_array($obj->id, $ignore_id)) {
                continue;
            }
            $ignore_id[] = $obj->id;

            $money = 0;
            if ($obj->new_line_of_credit || $obj->old_line_of_credit) {
                $money += $obj->new_line_of_credit - $obj->old_line_of_credit;
            }
            if ($obj->amount) {
                $money += $obj->amount;
            }

            $content[] = [
                $this->country_ids[$obj->country_id],
                $obj->accounting_type == 2 ? 'Outer Account' : 'Inner Account',
                $obj->seller_firstname . $obj->seller_lastname . '(' . $obj->seller_user_number . ')',
                $obj->screenname,
                $obj->buyer_firstname . $obj->buyer_lastname . '(' . $obj->buyer_user_number . ')',
                empty($obj->self_buyer_id) ? 'N' : 'Y',
                $obj->agreement_code,
                Currency::format($money, $obj->country_id),
                $obj->date_added
            ];
        }

        $date = date('Ymd', strtotime($start_time)) . '-' . date('Ymd', strtotime($end_time));
        $pre = '【返点充值明细】';
        $subject = 'B2B平台Seller返点充值明细【' . $date . '】';
        $title = $subject . '(US Time)<br><span style="color:red;">(统计区间与返点时间都为美国时间；美国外部供应商返点数据系统已自动计入账单, 不再需要财务手动录入)</span>';
        $data = [
            'subject' => $pre . $subject,
            'title' => $title,
        ];
        if (count($objs)) {
            $data['header'] = $header;
            $data['content'] = $content;
            echo date('Y-m-d H:i:s') . ' statistic:rebate-recharge ' . ' [' . $date . ']发送成功' . PHP_EOL;
        } else {
            $data['header'] = ['此期间没有产生返点充值数据'];
            $data['content'] = [];
            echo date('Y-m-d H:i:s') . ' statistic:rebate-recharge ' . ' [' . $date . ']无数据' . PHP_EOL;
        }

        $to = array_filter(explode(',', Setting::getConfig('statistic_rebate_recharge_email_to') ?: ''));
        if (!$to) {
            return;
        }
        $mail = \Mail::to($to);
        $cc = array_filter(explode(',', Setting::getConfig('statistic_rebate_recharge_email_cc') ?: ''));
        if ($cc) {
            $mail = $mail->cc($cc);
        }
        $mail->send(new Mailer($data));
        $send_data = ['to' => $to, 'cc' => $cc];
        \Log::info(json_encode(array_merge($data, $send_data)));
    }


}
