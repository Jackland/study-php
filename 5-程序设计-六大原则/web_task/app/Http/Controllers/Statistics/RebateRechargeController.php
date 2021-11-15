<?php

namespace App\Http\Controllers\Statistics;

use App\Mail\RebateRechargeStatistic;
use App\Models\Currency;
use App\Models\Rebate\Agreement;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

/**
 * @deprecated 测试时使用的
 */
class RebateRechargeController extends Controller
{
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
     * @return RebateRechargeStatistic
     */
    public function first()
    {
        $Agreement = new Agreement();
        $current_month_stamp = strtotime(date('Y-m-01'));
        $last_month_last_day_stamp = strtotime('-1 day', $current_month_stamp);
        $start_time_str = date('Y-m-16 00:00:00', $last_month_last_day_stamp);
        $end_time_str = date('Y-m-d 23:59:59', $last_month_last_day_stamp);
        $objs = $Agreement->getRebateRecharge($start_time_str, $end_time_str,[0]);
        $header = [
            'Country',
            'Seller Customer Name（ID）',
            'Store Name',
            'Buyer Customer Name（ID）',
            'Agreement ID',
            'Rebate Amount',
            'Operation Time'
        ];
        $content = [];
        foreach ($objs as $obj) {
            $content[] = [
                $this->country_ids[$obj->country_id],
                $obj->seller_firstname . $obj->seller_lastname . '(' . $obj->seller_user_number . ')',
                $obj->screenname,
                $obj->buyer_firstname . $obj->buyer_lastname . '(' . $obj->buyer_user_number . ')',
                $obj->agreement_code,
                Currency::format($obj->new_line_of_credit - $obj->old_line_of_credit, $obj->country_id),
                $obj->date_added
            ];
        }
        $pre = '【返点充值明细】';
        $subject ='B2B平台Seller返点充值明细【'  . date('Ymd', strtotime($start_time_str)) . '-' . date('Ymd', strtotime($end_time_str)) . '】';
        $title = $subject . '(US Time)<br><span style="color:red;">(统计区间与返点时间都为美国时间；美国外部供应商返点数据系统已自动计入账单, 不再需要财务手动录入)</span>';
        $data = [
            'subject' => $pre . $subject,
            'title' => $title,
        ];
        if (count($objs)) {
            $data['header'] = $header;
            $data['content'] = $content;
            echo date('Y-m-d H:i:s') . ' statistic:rebate-recharge 发送成功' . PHP_EOL;
        } else {
            $data['header'] = ['此期间没有产生返点充值数据'];
            $data['content'] = [];
            echo date('Y-m-d H:i:s') . ' statistic:rebate-recharge 无数据' . PHP_EOL;
        }

        return new RebateRechargeStatistic($data);
    }

    /**
     * @return RebateRechargeStatistic
     */
    public function last()
    {
        $Agreement = new Agreement();

        $start_time_str = date('Y-m-01 00:00:00');
        $end_time_str = date('Y-m-29 23:59:59');
        $objs = $Agreement->getRebateRecharge($start_time_str, $end_time_str,[0]);

        $header = [
            'Country',
            'Seller Type',
            'Seller Customer Name（ID）',
            'Store Name',
            'Buyer Customer Name（ID）',
            'Agreement ID',
            'Rebate Amount',
            'Operation Time(US Time)'
        ];
        $content = [];
        foreach ($objs as $obj) {
            $content[] = [
                $this->country_ids[$obj->country_id],
                $obj->accounting_type == 2 ? 'Outer Account' : 'Inner Account',
                $obj->seller_firstname . $obj->seller_lastname . '(' . $obj->seller_user_number . ')',
                $obj->screenname,
                $obj->buyer_firstname . $obj->buyer_lastname . '(' . $obj->buyer_user_number . ')',
                $obj->agreement_code,
                Currency::format($obj->new_line_of_credit - $obj->old_line_of_credit, $obj->country_id),
                $obj->date_added
            ];
        }
        $pre = '【返点充值明细】';
        $subject = 'B2B平台Seller返点充值明细【'  . date('Ymd', strtotime($start_time_str)) . '-' . date('Ymd', strtotime($end_time_str)) . '】';
        $title = $subject . '(US Time)<br><span style="color:red;">(统计区间与返点时间都为美国时间；美国外部供应商返点数据系统已自动计入账单, 不再需要财务手动录入)</span>';
        $data = [
            'subject' => $pre . $subject,
            'title' => $title,
        ];
        if (count($objs)) {
            $data['header'] = $header;
            $data['content'] = $content;
        } else {
            $data['header'] = ['此期间没有产生返点充值数据'];
            $data['content'] = [];
        }

        return new RebateRechargeStatistic($data);
    }


    //public function test($type = 1)
    //{
    //    Artisan::call('statistic:rebate-recharge',['type'=> $type ]);
    //    echo 'success';
    //}



}
