<?php
//这是buyer的语言包
$_['heading_title_my'] = 'Margin List';
$_['heading_title_margin_bid'] = 'Margin Bid';
$_['heading_title_margin_bids'] = 'Margin Bids';
$_['heading_title_margin_details'] = 'Margin Details';
$_['heading_title_margin_details_long'] = 'Margin Details of %s';
$_['text_account']     = 'Account';
$_['text_bid_list']    = 'Complex Transactions List';
//Text
$_['text_error_add_cart'] = 'Add Margin Product Failed!';
$_['text_reapplied'] = 'Re-apply';

$_['text_add_success'] = 'Your application has been successfully sent to seller.';
$_['error_no_product'] = 'no such product';
$_['error_under_stock'] =  '%s is not available in the desired quantity or not in stock!';
$_['error_product_invalid'] = 'product invalid';
$_['error_date_updated'] = 'The margin agreement has been updated, please refresh the page to check.';
$_['page_error']='Submitting failed. Please refresh the page and try again.';

$_['text_futures_tip'] = "This Margin Agreement has been transferred from a Future Goods Agreement. Click to view the original Future Goods Agreement";

$_['text_cancel_confirm'] = ' Select ‘OK’ to confirm you would like to cancel this margin agreement.';
$_['text_cancel_success'] = 'Canceled successfully';
$_['text_cancel_error'] = 'Canceled failed.';

$_['text_terminate_confirm'] = "Your remaining deposit for product quantities that were not received will <span style='font-weight:bold;text-decoration: underline'>not</span> be refunded after you terminate this agreement. Are you sure you would like to continue with termination?";
$_['text_terminate_placeholder'] = 'Please enter your reason for termination';
$_['text_terminate_success'] = 'Terminated successfully';
$_['text_terminate_error'] = 'Terminated failed.';

$_['text_failed_success'] = 'Your processing results have been fed back to Seller.';
$_['text_processing_failed_success'] = 'Your processing results have been fed back to Buyer.';
$_['text_failed_rma_error'] = "This agreement contains RMA requests that haven't been responded.<br>Please contact with seller to argue.";
$_['text_failed_error'] = 'Request failed.';

$_['text_ignore_confirm'] = 'Select ‘OK’ to confirm you would not like to move forward with this margin ageement.';
$_['text_ignore_success'] = 'Margin process ended.';
$_['text_ignore_error'] = 'Ignored failed.';

$_['text_performer_add_confirm'] = 'Are you sure you would like to add a partner to this margin agreement (Agreement ID xxxxx)?';
$_['text_performer_add_success'] = 'Success';
$_['text_performer_add_error'] = 'Failed';

$_['text_wait_process']     = 'WAITING FOR REVIEW';
$_['text_wait_deposit_pay'] = 'WAITING FOR MARGIN PAYMENT';
$_['text_due_soon']         = 'DUE SOON';
$_['text_termination_request']         = 'Processing Margin Agreement';

$_['tip_wait_process']     = 'Applied, Pending, Time Out, Rejected and Back Order margin agreement waiting for review.';//'Applied/Pending/Time Out<br>/Rejected/Back Order';
$_['tip_wait_deposit_pay'] = 'Agreements waiting for margin payment.';//'Approved';
$_['tip_due_soon']         = 'The agreement is due in 7 days.';//'Sold且协议结束日期距离当前时间为7个自然日';
$_['tip_count_termination_request'] = 'When the Seller or Buyer begins to process an Agreement Termination Request that is waiting to be Approved or Denied by the Buyer or Selelr after receiving the deposit.';
$_['tip_margin_spot_price'] = 'This price does not include fulfillment fee.';
$_['tip_margin_spot_agreement_money'] = 'This price does not include fulfillment fee.';
$_['tip_termination_request'] = 'Respond to this agreement <br>termination request.';


$_['column_margin_agreement_day'] = 'Agreement Days';//现货保证金协议天数
$_['column_margin_completed_qty'] = 'Current Completed Quantity';//现货保证金协议完成数量
$_['column_margin_qty'] = 'Agreement Quantity';//现货保证金协议数量
$_['column_margin_unit_price'] = 'Agreement Unit Price';//现货保证金协议专享价
$_['column_margin_agreement_money'] = 'Agreement Amount';//现货保证金协议金额
$_['column_margin_order_id'] = 'Margin Order ID';//定金订单号


$_['tip_add_partner_pending'] = 'Add Partner-Pending';//待审批
$_['tip_add_partner_approved'] = 'Add Partner-Approved';//审批通过
$_['tip_add_partner_rejected'] = 'Add Partner-Rejected';//审批不通过

//message html template
$_['margin_advance_pay_subject'] = 'A New Margin Agreement (ID: %s) from Buyer %s';
$_['margin_advance_pay_content'] = '<table border="0" cellspacing="0" cellpadding="0">
   <tbody>
    <tr>
     <th align="left" style="width: 170px">Agreement ID:&nbsp;</th>
     <td><a href="%s">%s</a></td>
    </tr>
    <tr>
     <th align="left">Buyer Name:&nbsp;</th>
     <td>%s</td>
    </tr>
    <tr>
     <th align="left">Item Code/MPN:&nbsp;</th>
     <td>%s</td>
    </tr>
    <tr>
     <th align="left">Days of Agreement:&nbsp;</th>
     <td>%s</td>
    </tr>
    <tr>
     <th align="left">Quantity of Agreement:&nbsp;</th>
     <td>%s</td>
    </tr>
    <tr>
     <th align="left">Unit Price of Agreement:&nbsp;</th>
     <td>%s</td>
    </tr>
    <tr>
     <th align="left">Margin Deposit ID:&nbsp;</th>
     <td><a href="%s">%s</a></td>
    </tr>
    <tr>
     <th align="left">Total Margin Deposit:&nbsp;</th>
     <td>%s</td>
    </tr>
    <tr>
     <th align="left">Payment Time:&nbsp;</th>
     <td>%s</td>
    </tr>
   </tbody>
  </table>';

$_['margin_apply_subject'] = '%s has submitted a margin bid request to %s: #%s';
$_['margin_apply_content'] = '<table border="0" cellspacing="0" cellpadding="0">
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
     <th align="left">Unit Price of Agreement:&nbsp;</th>
     <td>%s</td>
    </tr>
    <tr>
     <th align="left">Days of Agreement:&nbsp;</th>
     <td>%s</td>
    </tr>
   </tbody>
  </table>';

$_['margin_cancel_subject'] = 'The margin agreement ID %s has been canceled.';
$_['margin_cancel_content'] = '<table border="0" cellspacing="0" cellpadding="0">
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
     <th align="left">Cancel reason:&nbsp;</th>
     <td>The buyer made the cancellation.</td>
    </tr>
   </tbody>
  </table>';

$_['margin_terminate_subject'] = 'The margin agreement ID %s has been terminated.';
$_['margin_terminate_content'] = '<table border="0" cellspacing="0" cellpadding="0">
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
     <th align="left">Terminate reason:&nbsp;</th>
     <td>The buyer made the termination.</td>
    </tr>
   </tbody>
  </table>';

$_['confirm_terminate_request'] = 'Are you sure to submit the request to terminate the agreement with seller?';
$_['confirm_success'] = 'Request of agreement termination has been submitted to seller.';
$_['text_margin_terminate_request'] = 'Termination from seller';
$_['text_count_down'] = 'Disabled Countdown';

$_['err_no_product']='Failed! The product record can not be found.';
$_['err_rma']='This agreement contains RMA requests that haven\'t been responded.<br> Please contact with seller to argue.' ;
$_['save_success']='This operation succeeds.';
$_['save_failed']='This operation fails.';
$_['reason_title'] = "Reason";
$_['reason_more_max'] = "Reason can not be more than 2000 characters.";
$_['reason_can_not_left_blank'] = "Reason cannot be left blank.";
$_['terminate_success'] = "The margin agreement (Agreement ID %s) has been terminated successfully.";
$_['title_confirm'] = 'Confirm';

//现货4期
$_['error_margin_buyers_no_binded'] = 'You have not yet linked with this User (Email / User Number: %s) and are unable to submit your add a partner request.';
$_['error_margin_buyers_not_buyer'] = 'You are not buyer.';
$_['error_margin_performer_code_require'] = 'Please Enter email or buyer code.';
$_['error_margin_performer_not_exist'] = 'Cannot find the account %s, please enter a right partner account.';
$_['error_margin_performer_disabled'] = '%s is a disabled acount, please enter a valid partner account.';
$_['error_margin_performer_illegal'] = 'The current account cannot be added as an agreement partner of the  margin agreement.';
$_['tips_future_to_margin_agreement'] = 'This Margin Agreement has been transferred from a Future Goods Agreement. Click to view the original Future Goods Agreement.';

// Onsite Seller
$_['error_onsite_seller_active_amount'] = 'There are risks in this seller\'s account, and the Margin transactions are not available. If you have any questions or concerns, you may contact the Marketplace customer service.';
