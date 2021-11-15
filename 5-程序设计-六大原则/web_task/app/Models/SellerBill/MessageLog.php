<?php

namespace App\Models\SellerBill;

use App\Jobs\SendMail;
use App\Models\Customer\Customer;
use App\Models\Message\Message;
use App\Models\Receipt\ReceiptsOrder;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MessageLog extends Model
{
    protected $table = 'tb_seller_bill_message_log';
    public $timestamps = false;

    public static function savelog($customer_id, $item, $count, $disable = 0)
    {
        $data['count'] = $count;
        $data['current_balance'] = $item->current_balance;
        $data['collateral_value'] = $item->collateral_value;
        $data['customer_id'] = $item->customer_id;
        $data['bill_ratio'] = round(abs($item->ratio) * 100, 2);
        $data['disable_account'] = $disable;
        $count = self::where(['customer_id' => $customer_id])->count();
        if ($count) {
            $mo = self::where(['customer_id' => $customer_id]);
            if ($disable) {
                $account_status = Customer::getCustomerStatus($customer_id);
                $disable_account = $mo->value('disable_account');
                if (!$disable_account || $account_status) {
                    $data['update_time'] = date('Y-m-d H:i:s');
                }
            } else {
                $data['update_time'] = date('Y-m-d H:i:s');
            }
            $mo->update($data);
        } else {
            self::insert($data);
        }
    }

    public static function getAllLog()
    {
        return self::all()->keyBy('customer_id')->toArray();
    }

    public static function handleMessage()
    {
        $collection = SellerCollateral::getSellerCollateral();
        if ($collection->isEmpty()) {
            return;
        }
        // 获取所有的已发送的站内信记录
        $log = MessageLog::getAllLog();
        $bill = SellerBill::getLastSellerBill();

        foreach ($collection as $item) {
            // 货值为0是时，当100%处理
            if ($item->collateral_value <= 0) {
                $item->ratio = 1;
            }
            // 供应商的账单金额为负数，且10个月时间没有交易，距离前10个月的5个自然之前开始发送
            $last_order = DB::table('oc_customerpartner_to_order')
                ->select('date_added')
                ->where(['customer_id' => $item->customer_id, 'order_product_status' => 5])
                ->orderBy('id', 'DESC')
                ->first();
            if ($last_order) {
                $timestamp = strtotime("-10 month");
                $last_order_timestamp = strtotime($last_order->date_added);
                if ($last_order_timestamp < ($timestamp - 5 * 24 * 3600)) {
                    self::messageTemplateF($bill, $item, $log);
                    continue;
                }
            }

            // 如果当天结算金额没有变动且比率 < 90%,不发消息
            if (isset($log[$item->customer_id]) && // 已在log表有记录，已发送过站内信
                $log[$item->customer_id]['current_balance'] == $item->current_balance && // 结算值＝前一天结算值
                $log[$item->customer_id]['collateral_value'] == $item->collateral_value && //抵押物值＝前一天抵押物值
                bccomp(abs($item->ratio), 0.9, 4) === -1  //　比率 < 90%
            ) {
                continue;
            }
            // 当前结算周期账单欠款金额达到抵押物大于等于80%，小于90%
            if (bccomp(abs($item->ratio), 0.8, 4) != -1 && bccomp(abs($item->ratio), 0.9, 4) === -1) {
                self::messageTemplateA($bill, $item, $log);
                // 当前结算周期账单欠款金额达到抵押物大于等于90%，小于100%
            } else if (bccomp(abs($item->ratio), 0.9, 4) != -1 && bccomp(abs($item->ratio), 1, 4) === -1) {
                if (isset($log[$item->customer_id]) && $log[$item->customer_id]['count'] >= 5) {
                    self::messageTemplateCD($bill, $item, $log, 1);
                } else {
                    self::messageTemplateB($bill, $item, $log);
                }
                // 当前结算周期账单欠款金额达到抵押物大于等于100%
            } else if (bccomp(abs($item->ratio), 1, 4) != -1) {
                self::messageTemplateCD($bill, $item, $log);
            }

        }


    }


    public static function messageTemplateA($bill, $item, &$log)
    {
        $subject = 'Notice of debt from ' . substr($bill->start_date, 0, 10) . ' to ' . substr($bill->end_date, 0, 10);
        $year = substr($bill->start_date, 0, 4);
        $month = substr($bill->start_date, 5, 2);
        $template = '<p>Dear Giga Cloud Marketplace Supplier %s：</p>
<p>In reference to the signed contractual agreement with the Giga Cloud Logistics B2B Marketplace, your company\'s store on the Marketplace：%s has accumulated an expense total of <strong>  %.2f </strong>  dollars for the month of [' . $month . ',' . $year . '], consisting of fees that include but are not limited to: freight, packing , storage, etc. Please remit payment as soon as possible, thank you.</p>
<p style="text-align: right">The Giga Cloud Logistics B2B marketplace</p>
<p style="text-align: right">' .  date('Y-m-d') . '</p>';

        $content = sprintf($template, $item->screenname, $item->logistics_customer_name, $item->current_balance);
        Message::addSystemMessage('invoice', $subject, $content, $item->customer_id);
        self::savelog($item->customer_id, $item, 1);
    }

    public static function messageTemplateB($bill, $item, &$log)
    {
        // 发送次数要<=6
        if (isset($log[$item->customer_id]) && $log[$item->customer_id]['count'] >= 6) {
            return;
        }
        $year = substr($bill->start_date, 0, 4);
        $month = substr($bill->start_date, 5, 2);
        $subject = 'Notice of debt from '.substr($bill->start_date, 0, 10) . ' to ' . substr($bill->end_date, 0, 10) ;
        $template = '<p>Dear Giga Cloud Logistics supplier %s：</p>
<p>In reference the signed contractual agreement with the Giga Cloud Logistics B2B Marketplace, your company\'s store on the Marketplace our B2B marketplace %s has accumulated an expense total of<strong>  %.2f </strong> dollars for the month of [' . $month . ',' . $year . '], consisting of fees that include but are not limited to: freight, packing, storage, etc. Please remit payment to the pre-approved account within 5 natural days. 
If you fail to make your payment in time, the Marketplace has right to dispose of all your goods (including but not limited to selling, auction, etc.) without compensation. Please note that if the aforementioned goods have a registered a brand, Giga Cloud Logistics will obtain a disposition right, giving full authorization to dispose of said goods. We appreciate your cooperation.</p>
<p style="text-align: right">The Giga Cloud Logistics</p>
<p style="text-align: right">' .  date('Y-m-d') . '</p>';

        $content = sprintf($template, $item->screenname, $item->logistics_customer_name, $item->current_balance);
        Message::addSystemMessage('invoice', $subject, $content, $item->customer_id);
        $count = 1;
        // 前一天的比率在90%-100%, 则次数累加
        if (isset($log[$item->customer_id]) && bccomp($log[$item->customer_id]['bill_ratio'], 90, 4) != -1 && bccomp($log[$item->customer_id]['bill_ratio'], 100) == -1) {
            $count = $log[$item->customer_id]['count'] + 1;
        }
        self::savelog($item->customer_id, $item, $count);
    }

    public static function messageTemplateCD($bill, $item, &$log, $flag = 0)
    {
        $subject = 'Notice of debt from '.substr($bill->start_date, 0, 10) . ' to ' . substr($bill->end_date, 0, 10) ;
        if ($flag == 1) {
            $str = ' As your debt is still not less than 90% of the collateral,';
        } else {
            $str = 'As your debt has reached 100% of the collateral, ';
        }
        $year = substr($bill->start_date, 0, 4);
        $month = substr($bill->start_date, 5, 2);
        $content = '<p>Dear Giga Cloud Logistics supplier  ' . $item->screenname . '：</p>
<p> In reference the signed contractual agreement with the Giga Cloud Logistics B2B Marketplace, your company\'s store on the Marketplace our B2B marketplace ' . $item->logistics_customer_name . ' has accumulated an expense total of  <strong>' . $item->current_balance . '  </strong> dollars for the month of [' . $month . ',' . $year . '],
consisting of fees that include but are not limited to: freight, packing, storage, etc. </p>
<p>' . $str . ' the marketplace will be suspending your B2B account: ' . $item->email . ', effective immediately and disposing all of your goods (including but not limited to selling, auction, etc.) without compensation.  Please note that if the aforementioned goods have a registered a brand, Giga Cloud Logistics will obtain a disposition right, giving full authorization to dispose of said goods. We appreciate your cooperation.</p>
<p style="text-align: right">The Giga Cloud Logistics B2B marketplace</p>
<p style="text-align: right">' .  date('Y-m-d') . '</p>';
        Message::addSystemMessage('invoice', $subject, $content, $item->customer_id);
        $count = 1;
        if (isset($log[$item->customer_id])) {
            $count = $log[$item->customer_id]['count'] + 1;
        }

        // 102253 由于欠款导致店铺关闭的名单”站内信过滤有入库单但未入库且金额为负的Seller
//        $disable = ReceiptsOrder::getNotStockReceiptOrderCount($item->customer_id) ? 0 : 1;
        $disable = 1;
        self::savelog($item->customer_id, $item, $count, $disable);
        Customer::disableAccount($item->customer_id);
    }

    public static function messageTemplateF($bill, $item, &$log)
    {
        if (isset($log[$item->customer_id]) && $log[$item->customer_id]['count'] >= 5) {
            Customer::disableAccount($item->customer_id);
            // 102253 由于欠款导致店铺关闭的名单”站内信过滤有入库单但未入库且金额为负的Seller
//            $disable = ReceiptsOrder::getNotStockReceiptOrderCount($item->customer_id) ? 0 : 1;
            $disable = 1;
            self::savelog($item->customer_id, $item, $log[$item->customer_id]['count'] + 1, $disable);
            return;
        }
        $year = substr($bill->start_date, 0, 4);
        $month = substr($bill->start_date, 5, 2);
        $subject = 'Notice of debt from '.substr($bill->start_date, 0, 10) . ' to ' . substr($bill->end_date, 0, 10) ;
        $template = '<p>Dear Giga Cloud Logistics supplier %s：</p>
<p>In reference the signed contractual agreement with the Giga Cloud Logistics B2B Marketplace, your company\'s store on the Marketplace our B2B marketplace %s has accumulated an expense total of   <strong> %.2f </strong> dollars for the month of [' . $month . ',' . $year . '],  consisting of fees that include but are not limited to: freight, packing, storage, etc. 
 Our system has detected that your account has not been active in making transactions with the Marketplace, therefore the marketplace will be suspending your B2B account: ' . $item->logistics_customer_name . ' effective immediately and disposing all of your goods (including but not limited to selling, auction, etc.) without compensation.  Please note that if the aforementioned goods have a registered a brand, Giga Cloud Logistics will obtain a disposition right, giving full authorization to dispose of said goods. We appreciate your cooperation.</p>
<p style="text-align: right">The Giga Cloud Logistics B2B marketplace</p>
<p style="text-align: right">' . date('Y-m-d') .'</p>';

        $content = sprintf($template, $item->screenname, $item->logistics_customer_name, $item->current_balance, $item->nickname);
        Message::addSystemMessage('invoice', $subject, $content, $item->customer_id);
        $count = 1;
        if (isset($log[$item->customer_id])) {
            $count = $log[$item->customer_id]['count'] + 1;
        }
        self::savelog($item->customer_id, $item, $count, 0);
    }

    public static function sendMail()
    {
        $res = DB::table('tb_seller_bill_message_log as l')
            ->select(DB::raw('l.current_balance,c.logistics_customer_name,cp.screenname,l.bill_ratio'))
            ->leftjoin('oc_customer as c', 'c.customer_id', '=', 'l.customer_id')
            ->leftjoin('oc_customerpartner_to_customer as cp', 'cp.customer_id', '=', 'l.customer_id')
            ->where('l.update_time', '>', date('Y-m-d H:i:s', strtotime('-1 minute')))
            ->where(['l.disable_account' => 1])
            ->get();
        if ($res->isEmpty()) {
            $data['subject'] = '由于欠款导致店铺关闭的名单-无记录';
            $data['body'] = '此次无由于欠款导致店铺关闭的Seller';
        } else {
            $data['body'] = '<table border="1" cellspacing="0" cellpadding="0" style="margin: auto;"><tr><th>客户号</th><th>店铺名称</th><th>欠款金额</th><th>欠款/抵押物的比例</th></tr>';
            foreach ($res as $item) {
                $data['body'] .= "<tr><td>{$item->logistics_customer_name}</td><td>{$item->screenname}</td><td>{$item->current_balance}</td><td>{$item->bill_ratio}%</td></tr>";
            }
            $data['body'] .= '</table>';
            $data['subject'] = '由于欠款导致店铺关闭的名单';
        }
        $list = Setting::getConfig('close_stores_email_to');
        if ($list) {
            $list = json_decode($list,true);
            foreach ($list as $item) {
                $data['to'] = $item;
                SendMail::dispatch($data);
            }
        }
    }


}