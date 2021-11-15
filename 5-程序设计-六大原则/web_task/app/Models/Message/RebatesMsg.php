<?php
/**
 * Created by PhpStorm.
 * User: Lu.Chen
 * Date: 2019/11/19
 * Time: 13:07
 */
namespace App\Models\Message;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RebatesMsg
 * @deprecated 返点四期 已重写。 By Lester.you
 * @package App\Models\Message
 */
class RebatesMsg extends Model
{
    const REBATE_APPROVED = 3;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    /*
     * 到期提醒:协议还有7个自然日到达协议完成时间，每个自然日美国时间0点发送一次提醒
     *
     * */
    public function expirationReminder()
    {
        $t = strtotime('+7 day');
        $rebateContract = \DB::table('tb_sys_rebate_contract as r')
            ->leftjoin('oc_customerpartner_to_customer as c', 'c.customer_id', 'r.seller_id')
            ->where('r.status', self::REBATE_APPROVED)
            ->where('r.expire_time', '<', date('Y-m-d H:i:s', $t))
            ->where('r.expire_time', '>', date('Y-m-d H:i:s'))
            ->select('r.contract_id','r.buyer_id','r.seller_id','r.qty','r.product_id','r.effect_time','r.expire_time','c.screenname')
            ->get();
        //print_r($rebateContract);die;

        $product = [];
        foreach ($rebateContract as $k=>$v)
        {
            $quantity = \DB::table('oc_order_product as op')
                ->leftjoin('oc_order as o', 'o.order_id', 'op.order_id')
                ->where('o.order_status_id', 5)
                ->where(['op.product_id'=>$v->product_id, 'o.customer_id'=>$v->buyer_id])
                ->sum('op.quantity');

            //未达到协议约定数量
            if ($quantity < $v->qty){
                if (!isset($product[$v->product_id])){
                    $product[$v->product_id] = \DB::table('oc_product')
                        ->where('product_id', $v->product_id)
                        ->select('sku','mpn')
                        ->first();
                }
                $this->msgToBuyer($v, $product[$v->product_id]);
                $this->msgToSeller($v, $product[$v->product_id]);
            }
        }

    }

    public function msgToSeller($v, $product)
    {
        $m = new Message();
        $subject = 'The rebate agreement ID '.$v->contract_id.' will expire soon. (Rebates Validity:' .$v->effect_time.'~'. $v->expire_time . ')';
        $nickName = $m->getNickNameNumber($v->buyer_id);
        $url = config('app.b2b_url').'account/product_quotes/rebates_contract/view&contract_id='.$v->contract_id;

        $message = '<table   border="0" cellspacing="0" cellpadding="0">';
        $message .= '<tr><th align="left">Agreement ID:&nbsp</th><td style="width: 650px">
                          <a href="' . $url . '">'.$v->contract_id.'</a></td></tr> ';
        $message .= '<tr><th align="left">Name:&nbsp</th><td style="width: 650px">'.$nickName.'</td></tr>';
        $message .= '<tr><th align="left">Item Code/MPN:&nbsp</th><td style="width: 650px">'.$product->sku.' / '.$product->mpn.'</td></tr>';
        $message .= '<tr><th align="left">Minimum Selling Quantity:&nbsp</th><td style="width: 650px">' .$v->qty. '</td></tr>';
        $message .= '</table>';

        $m->addSystemMessage('bid_rebates', $subject, $message, $v->seller_id);
    }

    public function msgToBuyer($v, $product)
    {
        $m = new Message();
        $subject = 'The rebate agreement ID '.$v->contract_id.' will expire soon. (Rebates Validity:' .$v->effect_time.'~'. $v->expire_time . ')';
        $url = config('app.b2b_url').'account/product_quotes/rebates_contract/view&contract_id='.$v->contract_id;
        $storeUrl = config('app.b2b_url').'customerpartner/profile&id='.$v->seller_id;

        $message = '<table   border="0" cellspacing="0" cellpadding="0">';
        $message .= '<tr><th align="left">Agreement ID:&nbsp</th><td style="width: 650px">
                          <a href="' . $url . '">'.$v->contract_id.'</a></td></tr> ';
        $message .= '<tr><th align="left">Store:&nbsp</th><td style="width: 650px">
                          <a href="' . $storeUrl . '">'.$v->screenname.'</a></td></tr>';
        $message .= '<tr><th align="left">Item Code:&nbsp</th><td style="width: 650px">'.$product->sku.'</td></tr>';
        $message .= '<tr><th align="left">Minimum Selling Quantity:&nbsp</th><td style="width: 650px">' .$v->qty. '</td></tr>';
        $message .= '</table>';

        $m->addSystemMessage('bid_rebates', $subject, $message, $v->buyer_id);
    }

}