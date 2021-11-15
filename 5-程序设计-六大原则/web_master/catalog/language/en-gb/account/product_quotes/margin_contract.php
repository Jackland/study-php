<?php
//这是seller的语言包
$_['text_account'] = 'Account';
$_['text_seller_center'] = 'Seller Central';
$_['heading_parent_title'] = 'Product Bidding';
$_['heading_title_list'] = 'Complex Transactions List';
$_['margin_detail_title'] = 'Margin Details';

$_['tab_margin_agreement'] = 'Details of Margin Agreement';
$_['tab_margin_voucher'] = 'Margin Payment Receipt';
$_['tab_margin_check'] = 'Margin Transaction Details';

$_['tab_agreement_detail'] = 'Agreement Details';
//$_['tab_payment_receipt'] = 'Payment Receipt';
$_['tab_payment_receipt'] = 'Invoice';
$_['tab_transaction_details'] = 'Transaction Details';
//column
$_['column_agreement_id'] = 'Agreement ID';
$_['column_status'] = 'Status';
$_['column_sku_mpn'] = 'Item Code / MPN';
$_['column_item_code'] = 'Item Code';
$_['column_mpn'] = 'MPN';
$_['column_buyer_name'] = 'Name';
$_['column_date_from'] = 'Applied Date From';
$_['column_date_to'] = 'Applied Date To';
$_['column_data_modify']='LASTMODITIED DATE RANGE';
$_['column_marginnum_and_agreenum']='Current Completed Quantity<br>/Agreement Quantity';
$_['column_personal_price']='Agreement Unit Price';
$_['column_personal_price_tips']='This price does not include fulfillment fee.';
$_['column_agreement_price']='Agreement Amount';
$_['column_agreement_price_tips']='This price does not include fulfillment fee.';
$_['column_modify']='Last Modified';

$_['column_day'] = 'Days of Agreement';
$_['column_qty'] = 'Agreement QTY';
$_['column_price'] = 'Agreement Unit Price';
$_['column_margin_amount_sum'] = 'Margin Total Product Value';
$_['column_applied_date'] = 'Applied Date';
$_['column_action'] = 'Action';

$_['column_number_seq'] = 'No.';
$_['column_product_name'] = 'Product Name';
$_['column_quantity'] = 'Qty';
$_['column_unit_price'] = 'Unit Price';
$_['column_quote_discount_price'] = 'Discounted amount per unit';
$_['column_service_fee'] = 'Service Fee Per Unit';
$_['column_total_price'] = 'Total Price';
$_['column_rma_id'] = 'RMA ID';

$_['column_purchase_order'] = 'Purchase order ID';
$_['column_purchase_date'] = 'Purchase Date';
$_['column_purchase_qty'] = 'Quantity';

//btn
$_['btn_filter'] = 'Filter';
$_['btn_view'] = 'View';
$_['btn_approve'] = 'Approve this margin agreement';
$_['btn_reject'] = 'Reject this margin agreement';
$_['btn_submit'] = 'Submit';
$_['btn_back'] = 'Go back';
$_['button_back'] = 'Go Back';

//text
$_['text_status_1'] = 'Applied';
$_['text_status_2'] = 'Pending';
$_['text_status_3'] = 'Approved';
$_['text_status_4'] = 'Rejected';
$_['text_status_5'] = 'Time Out';
$_['text_status_6'] = 'Sold';
$_['text_status_7'] = 'Canceled';
$_['text_status_8'] = 'Completed';

$_['text_approve_confirm'] = 'Are you sure you want to approve this margin agreement?';
$_['text_reject_confirm'] = 'Are you sure you want to reject this margin agreement?';
$_['text_pick_date'] 		  = 'Pick a date...';
$_['text_enter_buyer_name']   = 'Enter buyer name';

//label
$_['label_margin'] = 'Margin';

$_['label_margin_validity'] = 'Margin Validity (PST): ';
$_['label_message'] = 'Message';
$_['label_messages'] = 'Messages';
$_['label_process_result'] = 'Processing Result';
$_['label_processed_result'] = 'Processed Result';

$_['label_order_details'] = 'Order Details';
$_['label_order_id'] = 'Order ID:';
$_['label_order_status'] = 'Order Status:';
$_['label_order_date'] = 'Order Date:';
$_['label_order_buyer'] = 'Name:';
$_['label_item_list'] = 'Item List';
$_['label_sub_total'] = 'Sub-total';
$_['label_total_price'] = 'Total Price';
$_['label_detail'] = 'Details';

// Tips
$_['tip_drop_shipping_logo'] = 'Dropshipping Buyer';
$_['tip_home_pickup_logo'] = 'Pick up Buyer';

//success
$_['text_update_success'] = 'Success: You have updated the status of margin agreement.';
$_['text_approve_success'] = 'The message is sent and the agreement status is updated successfully.';
$_['text_approve_error']='Approve margin agreement failed,please contact Platform Customer Service (Send Email: service@gigacloudlogistics.com).';
$_['text_msg_send_success'] = 'The message is finished successfully.';
$_['success_saved_seller_msg'] = 'The message is sent and the agreement status is updated successfully.';

//error
$_['error_update_fail'] = 'Failed! Status of margin agreement update failure.';
$_['error_approve_fail'] = 'Failed! The message is not sent and the agreement status is updated failure.';
$_['error_msg_send_fail'] = 'The message is not sent successfully.';
$_['error_msg_large'] = 'The message text is too long.';
$_['error_msg_empty'] = 'Cannot send an empty message.';
$_['error_timeout'] = 'This agreement has not been processed in validity period. It has been updated to the status of ‘Time out’. Please refresh the page to review.';
$_['error_no_stock'] = 'Low stock quantity - unable to fulfill the bid quantity request. Please contact the Buyer and ask them to resubmit their new margin transaction request with a lower quantity.';
$_['error_date_updated'] = 'The margin agreement has been updated, please refresh the page to check.';


//message html template
$_['margin_approve_subject'] = 'Margin bid application result of %s：%s(Agreement ID:%s)';
$_['margin_approve_content'] = '<table border="0" cellspacing="0" cellpadding="0">
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
     <th align="left">Status:&nbsp;</th>
     <td>%s</td>
    </tr>
   </tbody>
  </table>';

// 现货保证金三期添加
$_['text_wait_process']     = 'WAITING FOR REVIEW';
$_['text_wait_deposit_pay'] = 'WAITING FOR MARGIN PAYMENT';
$_['text_due_soon']         = 'DUE SOON';
$_['text_termination_request'] = 'Processing Margin Agreement';

$_['tip_wait_process']     = 'Seller has\'t responded to the bid request from buyer.';
$_['tip_wait_deposit_pay'] = 'Agreements waiting for margin payment.';
$_['tip_due_soon']         = 'The agreement is due in 7 days.';
$_['tip_count_termination_request'] = 'When the Buyer or Seller submits an Agreement Termination Request after paying the deposit while the request is still waiting to be Approved or Denied by the Seller or Buyer；The Buyer has submitted an Add a Partner request to the agreement which is still waiting to be processed by seller.';
$_['to_margin_template']   ='Click to view current offering';


$_['error_login']='Please refresh the page.';
$_['not_seller']='You are not seller.';
$_['not_post']='Please refresh the page.';
$_['date_error_marginid']='Please refresh the page.';
$_['error_reason_less']='REASON can not be left blank.';
$_['error_reason_more']='REASON can not be more than 2000 characters.';
$_['error_status']='Status of margin agreement has been changed. This agreement cannot be terminated.';
$_['action_error']='Canceled failed.';
$_['action_success']='Success';

$_['text_futures_tip'] = "This is a future goods agreement. Click to view the agreement details.";


$_['confirm_terminate_request']='Are you sure to submit the request to terminate the agreement with buyer?';
$_['confirm_success']='Request of agreement termination has been submitted to buyer.';


$_['err_no_product']='Failed! The product record can not be found.';
$_['err_rma']='This agreement contains RMA requests that haven\'t been responded.<br> Please respond to these requests first before submitting the agreement termination request.' ;
$_['save_success']='This operation succeeds.';
$_['save_failed']='This operation fails.';

$_['tip_termination_request'] = 'Respond to this agreement <br>termination request.';

$_['tip_audit_performer_request'] = 'Approval Status';
$_['btn_audit_performer_request'] = 'Approval of partner';

$_['text_count_down'] = 'Disabled Countdown';
$_['text_margin_terminate_request'] = 'Termination from buyer';
$_['title_confirm'] = 'Confirm';

$_['tip_add_partner_pending'] = 'Add Partner-Pending';//待审批
$_['tip_add_partner_approved'] = 'Add Partner-Approved';//审批通过
$_['tip_add_partner_rejected'] = 'Add Partner-Rejected';//审批不通过
