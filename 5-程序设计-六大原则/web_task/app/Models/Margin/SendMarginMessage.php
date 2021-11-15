<?php

namespace App\Models\Margin;

use App\Models\Message\Message;
use Illuminate\Database\Eloquent\Model;

class SendMarginMessage extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    /**
     * 向seller发送审批超时的站内信
     *
     * @param $margin_detail
     * @return bool|string
     */
    public function sendApproveTimeoutToSeller($margin_detail)
    {
        if (empty($margin_detail->agreement_id) || empty($margin_detail->id)) {
            return false;
        }
        $subject = 'The margin agreement ID %s has timed out.';
        $content = '<table border="0" cellspacing="0" cellpadding="0">
                       <tbody>
                        <tr>
                         <th align="left" style="width: 170px">Agreement ID:&nbsp;</th>
                         <td><a href="%s">%s</a></td>
                        </tr> 
                        <tr>
                         <th align="left">Name:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">ItemCode/MPN:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Timeout reason:&nbsp;</th>
                         <td>The seller hasn\'t processed within %s day(s).</td>
                        </tr>
                       </tbody>
                      </table>';
        $margin_process = new MarginProcess();
        $agreement_detail = $margin_process->getMarginAgreementDetail($margin_detail->agreement_id, null, $margin_detail->id);
        $subject = sprintf($subject, $agreement_detail->agreement_id);
        $content = sprintf($content,
            config('app.b2b_url') . 'account/product_quotes/margin_contract/view&agreement_id=' . $agreement_detail->agreement_id,
            $agreement_detail->agreement_id,
            $agreement_detail->nickname . ' (' . $agreement_detail->user_number . ') ',
            $agreement_detail->sku . '/' . $agreement_detail->mpn,
            $agreement_detail->period_of_application
        );
        $message_sender = new Message();
        $send_flag = $message_sender->addSystemMessage('bid_margin', $subject, $content, $agreement_detail->seller_id);
        return $send_flag;
    }

    /**
     * 向buyer发送审批超时的站内信
     *
     * @param $margin_detail
     * @return bool|string
     */
    public function sendApproveTimeoutToBuyer($margin_detail)
    {
        if (empty($margin_detail->agreement_id) || empty($margin_detail->id)) {
            return false;
        }
        $subject = 'The margin agreement ID %s has timed out.';
        $content = '<table border="0" cellspacing="0" cellpadding="0">
                       <tbody>
                        <tr>
                         <th align="left" style="width: 170px">Agreement ID:&nbsp;</th>
                         <td><a href="%s">%s</a></td>
                        </tr> 
                        <tr>
                         <th align="left">Store:&nbsp;</th>
                         <td><a href="%s">%s</a></td>
                        </tr>
                        <tr>
                         <th align="left">ItemCode:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Timeout reason:&nbsp;</th>
                         <td>The seller hasn\'t processed within %s day(s).</td>
                        </tr>
                       </tbody>
                      </table>';
        $margin_process = new MarginProcess();
        $agreement_detail = $margin_process->getMarginAgreementDetail($margin_detail->agreement_id, null, $margin_detail->id);
        $subject = sprintf($subject, $agreement_detail->agreement_id);
        $content = sprintf($content,
            config('app.b2b_url') . 'account/product_quotes/margin/detail_list&id=' . $agreement_detail->id,
            $agreement_detail->agreement_id,
            config('app.b2b_url') . 'customerpartner/profile&id=' . $agreement_detail->seller_id,
            $agreement_detail->seller_name,
            $agreement_detail->sku,
            $agreement_detail->period_of_application
        );
        $message_sender = new Message();
        $send_flag = $message_sender->addSystemMessage('bid_margin', $subject, $content, $agreement_detail->buyer_id);
        return $send_flag;
    }

    /**
     * 向seller发送订金支付超时的站内信
     *
     * @param $margin_detail
     * @return bool|string
     */
    public function sendDepositPayTimeoutToSeller($margin_detail)
    {
        if (empty($margin_detail->agreement_id) || empty($margin_detail->id)) {
            return false;
        }
        $subject = 'The margin agreement ID %s has timed out.';
        $content = '<table border="0" cellspacing="0" cellpadding="0">
                       <tbody>
                        <tr>
                         <th align="left" style="width: 170px">Agreement ID:&nbsp;</th>
                         <td><a href="%s">%s</a></td>
                        </tr> 
                        <tr>
                         <th align="left">Name:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">ItemCode/MPN:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Timeout reason:&nbsp;</th>
                         <td>The buyer hasn\'t paid on the margin deposit link within 24 hours.</td>
                        </tr>
                       </tbody>
                      </table>';
        $margin_process = new MarginProcess();
        $agreement_detail = $margin_process->getMarginAgreementDetail($margin_detail->agreement_id, null, $margin_detail->id);
        $subject = sprintf($subject, $agreement_detail->agreement_id);
        $content = sprintf($content,
            config('app.b2b_url') . 'account/product_quotes/margin_contract/view&agreement_id=' . $agreement_detail->agreement_id,
            $agreement_detail->agreement_id,
            $agreement_detail->nickname . ' (' . $agreement_detail->user_number . ') ',
            $agreement_detail->sku . '/' . $agreement_detail->mpn
        );
        $message_sender = new Message();
        $send_flag = $message_sender->addSystemMessage('bid_margin', $subject, $content, $agreement_detail->seller_id);
        return $send_flag;
    }

    /**
     * 向buyer发送订金支付超时的站内信
     *
     * @param $margin_detail
     * @return bool|string
     */
    public function sendDepositPayTimeoutToBuyer($margin_detail)
    {
        if (empty($margin_detail->agreement_id) || empty($margin_detail->id)) {
            return false;
        }
        $subject = 'The margin agreement ID %s has timed out.';
        $content = '<table border="0" cellspacing="0" cellpadding="0">
                       <tbody>
                        <tr>
                         <th align="left" style="width: 170px">Agreement ID:&nbsp;</th>
                         <td><a href="%s">%s</a></td>
                        </tr> 
                        <tr>
                         <th align="left">Store:&nbsp;</th>
                         <td><a href="%s">%s</a></td>
                        </tr>
                        <tr>
                         <th align="left">ItemCode:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Timeout reason:&nbsp;</th>
                         <td>The buyer hasn\'t paid on the margin deposit link within 24 hours.</td>
                        </tr>
                       </tbody>
                      </table>';
        $margin_process = new MarginProcess();
        $agreement_detail = $margin_process->getMarginAgreementDetail($margin_detail->agreement_id, null, $margin_detail->id);
        $subject = sprintf($subject, $agreement_detail->agreement_id);
        $content = sprintf($content,
            config('app.b2b_url') . 'account/product_quotes/margin/detail_list&id=' . $agreement_detail->id,
            $agreement_detail->agreement_id,
            config('app.b2b_url') . 'customerpartner/profile&id=' . $agreement_detail->seller_id,
            $agreement_detail->seller_name,
            $agreement_detail->sku
        );
        $message_sender = new Message();
        $send_flag = $message_sender->addSystemMessage('bid_margin', $subject, $content, $agreement_detail->buyer_id);
        return $send_flag;
    }

    /**
     * 向seller发送协议即将过期的站内信
     *
     * @param $margin_detail
     * @return bool|string
     */
    public function sendSoldWillExpireToSeller($margin_detail)
    {
        if (empty($margin_detail)) {
            return false;
        }
        $subject = 'The margin agreement ID %s will expire soon. (Margin Validity:%s~%s)';
        $content = '<table border="0" cellspacing="0" cellpadding="0">
                       <tbody>
                        <tr>
                         <th align="left" style="width: 170px">Agreement ID:&nbsp;</th>
                         <td><a href="%s">%s</a></td>
                        </tr> 
                        <tr>
                         <th align="left">Name:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">ItemCode/MPN:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Quantity of Agreement:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Number of uncompleted agreements:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                       </tbody>
                      </table>';
        $subject = sprintf($subject, $margin_detail->agreement_id, $margin_detail->effect_time, $margin_detail->expire_time);
        $content = sprintf($content,
           'index.php?route=account/product_quotes/margin_contract/view&agreement_id=' . $margin_detail->agreement_id,
            $margin_detail->agreement_id,
            $margin_detail->nickname . ' (' . $margin_detail->user_number . ') ',
            $margin_detail->sku . '/' . $margin_detail->mpn,
            $margin_detail->num,
            $margin_detail->num-$margin_detail->completed_qty
        );
        $message_sender = new Message();
        $send_flag = $message_sender->addSystemMessage('bid_margin', $subject, $content, $margin_detail->seller_id);
        return $send_flag;
    }

    /**
     * 现货协议buyer超时违约
     * @param $margin_detail
     * @return bool|string
     * @throws \Exception
     */
    public function sendSoldExpireToBuyer($margin_detail)
    {
        if (empty($margin_detail)) {
            return false;
        }
        $subject = 'The margin agreement (%s) has been defaulted.';
        $content = '<table border="0" cellspacing="0" cellpadding="0">
                       <tbody>
                        <tr>
                         <th align="left" style="width: 170px">Margin Agreement ID:&nbsp;</th>
                         <td><a href="%s">%s</a></td>
                        </tr> 
                        <tr>
                         <th align="left">Store:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Item Code:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Unsold Quantity:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                       </tbody>
                      </table>';
        $subject = sprintf($subject, $margin_detail->agreement_id);
        $content = sprintf($content,
            'index.php?route=account/product_quotes/margin/detail_list&id=' . $margin_detail->id,
            $margin_detail->agreement_id,
            $margin_detail->screenname,
            $margin_detail->sku,
            $margin_detail->num-$margin_detail->completed_qty
        );
        $message_sender = new Message();
        return $message_sender->addSystemMessage('bid_margin', $subject, $content, $margin_detail->buyer_id);
    }

    /**
     * 向buyer发送即将过期的站内信
     *
     * @param $margin_detail
     * @return bool|string
     */
    public function sendSoldWillExpireToBuyer($margin_detail)
    {
        if (empty($margin_detail)) {
            return false;
        }
        $subject = 'The margin agreement ID %s will expire soon. (Margin Validity:%s~%s)';
        $content = '<table border="0" cellspacing="0" cellpadding="0">
                       <tbody>
                        <tr>
                         <th align="left" style="width: 170px">Agreement ID:&nbsp;</th>
                         <td><a href="%s">%s</a></td>
                        </tr> 
                        <tr>
                         <th align="left">Store:&nbsp;</th>
                         <td><a href="%s">%s</a></td>
                        </tr>
                        <tr>
                         <th align="left">ItemCode:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Quantity of Agreement:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Number of uncompleted agreements:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                       </tbody>
                      </table>';
        $subject = sprintf($subject, $margin_detail->agreement_id, $margin_detail->effect_time, $margin_detail->expire_time);
        $content = sprintf($content,
            'index.php?route=account/product_quotes/margin/detail_list&id=' . $margin_detail->id,
            $margin_detail->agreement_id,
            'index.php?route=customerpartner/profile&id=' . $margin_detail->seller_id,
            $margin_detail->screenname,
            $margin_detail->sku,
            $margin_detail->num,
            $margin_detail->num-$margin_detail->completed_qty
        );
        $message_sender = new Message();
        $send_flag = $message_sender->addSystemMessage('bid_margin', $subject, $content, $margin_detail->buyer_id);
        return $send_flag;
    }

    /**
     * 向seller发送调货成功的站内信
     *
     * @param $margin_detail
     * @return bool|string
     */
    public function sendDispatchMessageToSeller($margin_detail)
    {
        if (empty($margin_detail)) {
            return false;
        }
        if ($margin_detail->adjustment_reason == 1) {
            $reason = 'buyer reason';
        } elseif ($margin_detail->adjustment_reason == 2) {
            $reason = 'seller reason';
        } else {
            $reason = 'Other';
        }
        $subject = 'The margin agreement no. %s has been completed.';
        $content = '<span>Didn\'t fulfilled. The platform has transfer the unfulfilled inventory to the store signed the agreement.</span>
                    <table border="0" cellspacing="0" cellpadding="0">
                       <tbody>
                        <tr>
                         <th align="left" style="width: 260px">Store Name:&nbsp;</th>
                         <td>%s</td>
                        </tr> 
                        <tr>
                         <th align="left">ItemCode/MPN:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Quantity of Agreement:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Number of uncompleted agreements:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Number of goods to be transferred:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Transfer time:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Transfer reason:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Note:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                       </tbody>
                      </table>';
        $subject = sprintf($subject, $margin_detail->agreement_id);
        $content = sprintf($content,
            $margin_detail->screenname,
            $margin_detail->sku . '/' . $margin_detail->mpn,
            $margin_detail->margin_num,
            $margin_detail->unaccomplished_num,
            $margin_detail->adjust_num,
            $margin_detail->dispatch_time,
            $reason,
            $margin_detail->remark
        );
        $message_sender = new Message();
        $send_flag = $message_sender->addSystemMessage('bid_margin', $subject, $content, $margin_detail->seller_id);
        return $send_flag;
    }

    /**
     * 向buyer发送调货成功的站内信
     *
     * @param $margin_detail
     * @return bool|string
     */
    public function sendDispatchMessageToBuyer($margin_detail)
    {
        if (empty($margin_detail)) {
            return false;
        }
        if ($margin_detail->adjustment_reason == 1) {
            $reason = 'buyer reason';
        } elseif ($margin_detail->adjustment_reason == 2) {
            $reason = 'seller reason';
        } else {
            $reason = 'Other';
        }
        $subject = 'The margin agreement no. %s has been completed.';
        $content = '<span>Didn\'t fulfilled. The platform has transfer the unfulfilled inventory to the store signed the agreement.</span>
                    <table border="0" cellspacing="0" cellpadding="0">
                       <tbody>
                        <tr>
                         <th align="left" style="width: 260px">Store Name:&nbsp;</th>
                         <td>%s</td>
                        </tr> 
                        <tr>
                         <th align="left">ItemCode/MPN:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Quantity of Agreement:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Number of uncompleted agreements:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Number of goods to be transferred:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Transfer time:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Transfer reason:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                        <tr>
                         <th align="left">Note:&nbsp;</th>
                         <td>%s</td>
                        </tr>
                       </tbody>
                      </table>';
        $subject = sprintf($subject, $margin_detail->agreement_id);
        $content = sprintf($content,
            $margin_detail->screenname,
            $margin_detail->sku,
            $margin_detail->margin_num,
            $margin_detail->unaccomplished_num,
            $margin_detail->adjust_num,
            $margin_detail->dispatch_time,
            $reason,
            $margin_detail->remark
        );
        $message_sender = new Message();
        $send_flag = $message_sender->addSystemMessage('bid_margin', $subject, $content, $margin_detail->buyer_id);
        return $send_flag;
    }
}
