<?php
/**
 * Created by PhpStorm.
 * User: Lu.Chen
 * Date: 2019/11/18
 * Time: 14:59
 */

namespace App\Models\Message;

use Illuminate\Database\Eloquent\Model;

class QuoteMsg extends Model
{
    const INTERVAL = 5;//min
    const QUOTE_APPLY = 0;//议价申请状态
    const QUOTE_APPROVED = 1;//议价申请通过
    const QUOTE_TIME_OUT = 4;//议价申请超时
    const QUOTE_CANCELED = 5;//议价取消
    protected $connection = 'mysql_proxy';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    /*
     * 议价/阶梯价格 超过24h未处理 每隔5分钟执行一次
     * */
    public function applyTimeOut()
    {

        $t1 = strtotime('-1 day');
        $t2 = strtotime('-' . self::INTERVAL . ' min', $t1);
        //近期有沟通的 需排除掉
        $notInId = \DB::connection('mysql_proxy')
            ->table('oc_product_quote_message')
            ->where('date', '>', date('Y-m-d H:i:s', $t1))
            ->groupby('quote_id')
            ->pluck('quote_id');

        $quoteInfo = \DB::connection('mysql_proxy')
            ->table('oc_product_quote as pq')
            ->leftjoin('oc_product as p', 'pq.product_id', 'p.product_id')
            ->leftjoin('oc_customerpartner_to_product AS cp', 'cp.product_id', 'pq.product_id')
            ->leftjoin('oc_customerpartner_to_customer AS cc', 'cc.customer_id', 'cp.customer_id')
            ->where('pq.date_added', '>=', date('Y-m-d H:i:s', $t2))
            ->where('pq.date_added', '<', date('Y-m-d H:i:s', $t1))
            ->where('pq.status', self::QUOTE_APPLY)
            ->whereNotIn('pq.id', $notInId)
            ->select('pq.id', 'pq.customer_id as buyer_id', 'pq.product_id', 'p.sku', 'p.mpn', 'cc.screenname', 'cp.customer_id as seller_id')
            ->get()
            ->toArray();
        if (empty($quoteInfo)) {
            return;
        }

        foreach ($quoteInfo as $k => $v) {
            $v = (array)$v;
            //最后一条消息的发送者
            $lastMessage = \DB::connection('mysql_proxy')
                ->table('oc_product_quote_message')
                ->where('quote_id', $v['id'])
                ->orderBy('id')
                ->value('writer');
            if (empty($lastMessage) || $lastMessage == $v['buyer_id']) {
                $reason = "The seller hasn't processed within 24 hours";
            } else {
                $reason = "The buyer hasn't processed within 24 hours";
            }

            $this->timeOutMsgToBuyer($v, $reason);
            $this->timeOutMsgToSeller($v, $reason);
        }
    }

    public function timeOutMsgToBuyer($data, $reason)
    {
        $url = config('app.b2b_url') . 'account/product_quotes/wk_quote_my/view&id=' . $data['id'] . '&product_id=' . $data['product_id'];
        $storeUrl = config('app.b2b_url') . 'customerpartner/profile&id=' . $data['seller_id'];

        $subject = 'The spot price quote ID ' . $data['id'] . ' has timed out';
        $message = '<table   border="0" cellspacing="0" cellpadding="0">';
        $message .= '<tr><th align="left">Quote ID:&nbsp</th><td style="width: 650px">
                          <a href="' . $url . '">' . $data['id'] . '</a>
                     </td></tr> ';
        $message .= '<tr><th align="left">Store:&nbsp</th><td style="width: 650px">
                         <a href="' . $storeUrl . '">' . $data['screenname'] . '</a></td></tr>';
        $message .= '<tr><th align="left">Item Code:&nbsp</th><td style="width: 650px">' . $data['sku'] . '</td></tr>';
        $message .= '<tr><th align="left">Timeout reason:&nbsp</th><td style="width: 650px">' . $reason . '</td></tr>';

        $message .= '</table>';

        $m = new Message();
        $m->addSystemMessage('bid', $subject, $message, $data['buyer_id']);
    }

    public function timeOutMsgToSeller($data, $reason)
    {
        $m = new Message();

        $nickName = $m->getNickNameNumber($data['buyer_id']);
        $url = config('app.b2b_url') . 'account/customerpartner/wk_quotes_admin/update&id=' . $data['id'];
        $subject = 'The spot price quote ID ' . $data['id'] . ' has timed out';
        $message = '<table   border="0" cellspacing="0" cellpadding="0">';
        $message .= '<tr><th align="left">Quote ID:&nbsp</th><td style="width: 650px">
                          <a href="' . $url . '">' . $data['id'] . '</a></td></tr> ';
        $message .= '<tr><th align="left">Name:&nbsp</th><td style="width: 650px">' . $nickName . '</td></tr>';
        $message .= '<tr><th align="left">Item Code/MPN:&nbsp</th><td style="width: 650px">' . $data['sku'] . ' / ' . $data['mpn'] . '</td></tr>';
        $message .= '<tr><th align="left">Timeout reason:&nbsp</th><td style="width: 650px">' . $reason . '</td></tr>';

        $message .= '</table>';

        $m->addSystemMessage('bid', $subject, $message, $data['seller_id']);
    }


    /*
     * seller同意申请后buyer24小时内未付款
     * */
    public function buyTimeOut()
    {
        $m = new Message();
        $t1 = strtotime('-1 day');
        $t2 = strtotime('-' . self::INTERVAL . ' min', $t1);
        $quoteArr = \DB::connection('mysql_proxy')
            ->table('oc_product_quote as pq')
            ->leftjoin('oc_product as p', 'pq.product_id', 'p.product_id')
            ->leftjoin('oc_customerpartner_to_product AS cp', 'cp.product_id', 'pq.product_id')
            ->leftjoin('oc_customerpartner_to_customer AS cc', 'cc.customer_id', 'cp.customer_id')
            ->where('pq.date_approved', '>', date('Y-m-d H:i:s', $t2))
            ->where('pq.date_approved', '<', date('Y-m-d H:i:s', $t1))
            ->where('pq.status', self::QUOTE_APPROVED)
            ->where('pq.order_id', '0')
            ->select('pq.id', 'pq.customer_id as buyer_id', 'pq.product_id', 'p.sku', 'p.mpn', 'cc.screenname', 'cp.customer_id as seller_id')
            ->get()
            ->toArray();
        if (empty($quoteArr)) {
            return;
        }
        $quoteIdList = array_column($quoteArr, 'id');
        \DB::connection('mysql_proxy')
            ->table('oc_product_quote')
            ->whereIn('id', $quoteIdList)
            ->where('status', self::QUOTE_APPROVED)
            ->update([
                'status' => self::QUOTE_TIME_OUT
            ]);

        $reason = "The buyer hasn't paid within 24 hours.";
        foreach ($quoteArr as $quote) {
            $subject = 'The spot price quote ID ' . $quote->id . ' has timed out';

            //发送给seller
            $nickName = $m->getNickNameNumber($quote->buyer_id);
            $url = config('app.b2b_url') . 'account/customerpartner/wk_quotes_admin/update&id=' . $quote->id;

            $message1 = '<table   border="0" cellspacing="0" cellpadding="0">';
            $message1 .= '<tr><th align="left">Quote ID:&nbsp</th><td style="width: 650px">
                          <a href="' . $url . '">' . $quote->id . '</a></td></tr> ';
            $message1 .= '<tr><th align="left">Name:&nbsp</th><td style="width: 650px">' . $nickName . '</td></tr>';
            $message1 .= '<tr><th align="left">Item Code/MPN:&nbsp</th><td style="width: 650px">' . $quote->sku . ' / ' . $quote->mpn . '</td></tr>';
            $message1 .= '<tr><th align="left">Timeout reason:&nbsp</th><td style="width: 650px">' . $reason . '</td></tr>';
            $message1 .= '</table>';
            $m->addSystemMessage('bid', $subject, $message1, $quote->seller_id);

            //发送给buyer
            $url = config('app.b2b_url') . 'account/product_quotes/wk_quote_my/view&id=' . $quote->id . '&product_id=' . $quote->product_id;
            $storeUrl = config('app.b2b_url') . 'customerpartner/profile&id=' . $quote->seller_id;

            $message2 = '<table   border="0" cellspacing="0" cellpadding="0">';
            $message2 .= '<tr><th align="left">Quote ID:&nbsp</th><td style="width: 650px">
                          <a href="' . $url . '">' . $quote->id . '</a>
                     </td></tr> ';
            $message2 .= '<tr><th align="left">Store:&nbsp</th><td style="width: 650px">
                            <a href="' . $storeUrl . '">' . $quote->screenname . '</a></td></tr>';
            $message2 .= '<tr><th align="left">Item Code:&nbsp</th><td style="width: 650px">' . $quote->sku . '</td></tr>';
            $message2 .= '<tr><th align="left">Timeout reason:&nbsp</th><td style="width: 650px">' . $reason . '</td></tr>';
            $message2 .= '</table>';
            $m->addSystemMessage('bid', $subject, $message2, $quote->buyer_id);
        }

    }


}