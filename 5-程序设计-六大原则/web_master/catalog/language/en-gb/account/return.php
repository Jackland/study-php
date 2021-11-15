<?php
// Heading
$_['heading_title']      = 'RMA';

// Text
$_['text_account']       = 'Account';
$_['text_return']        = 'Return Information';
$_['text_return_detail'] = 'Return Details';
$_['text_description']   = 'Please complete the form below to request an RMA number.';
$_['text_order']         = 'Order Information';
$_['text_product']       = 'Product Information';
$_['text_reason']        = 'Reason for Return';
$_['text_message']       = '<p>Thank you for submitting your return request. Your request has been sent to the relevant department for processing.</p><p> You will be notified via email as to the status of your request.</p>';
$_['text_return_id']     = 'Return ID:';
$_['text_order_id']      = 'Order ID:';
$_['text_date_ordered']  = 'Order Date:';
$_['text_status']        = 'Status:';
$_['text_date_added']    = 'Date Added:';
$_['text_comment']       = 'Return Comments';
$_['text_history']       = 'Return History';
$_['text_empty']         = 'You have not made any previous returns!';
$_['text_agree']         = 'I have read and agree to the <a href="%s" class="agree"><b>%s</b></a>';

// Column
$_['column_return_id']   = 'Return ID';
$_['column_order_id']    = 'Order ID';
$_['column_status']      = 'Status';
$_['column_date_added']  = 'Date Added';
$_['column_customer']    = 'Customer';
$_['column_product']     = 'Product Name';
$_['column_model']       = 'Model';
$_['column_quantity']    = 'Quantity';
$_['column_price']       = 'Price';
$_['column_opened']      = 'Opened';
$_['column_comment']     = 'Comment';
$_['column_reason']      = 'Reason';
$_['column_action']      = 'Action';

// Entry
$_['entry_order_id']     = 'Order ID';
$_['entry_date_ordered'] = 'Order Date';
$_['entry_firstname']    = 'First Name';
$_['entry_lastname']     = 'Last Name';
$_['entry_email']        = 'Email';
$_['entry_telephone']    = 'Telephone';
$_['entry_product']      = 'Product Name';
$_['entry_model']        = 'Product Code';
$_['entry_quantity']     = 'Quantity';
$_['entry_reason']       = 'Reason for Return';
$_['entry_opened']       = 'Product is opened';
$_['entry_fault_detail'] = 'Faulty or other details';

// Error
$_['text_error']         = 'The returns you requested could not be found!';
$_['error_order_id']     = 'Order ID required!';
$_['error_firstname']    = 'First Name must be between 1 and 32 characters!';
$_['error_lastname']     = 'Last Name must be between 1 and 32 characters!';
$_['error_email']        = 'Email Address does not appear to be valid!';
$_['error_telephone']    = 'Telephone must be between 3 and 32 characters!';
$_['error_product']      = 'Product Name must be greater than 3 and less than 255 characters!';
$_['error_model']        = 'Product Model must be greater than 3 and less than 64 characters!';
$_['error_reason']       = 'You must select a return product reason!';
$_['error_agree']        = 'Warning: You must agree to the %s!';

$_['text_rma_history']          = 'RMA History';
$_['column_apply_date']        = 'Applied Date';
$_['column_rma_id']        = 'RMA ID';
$_['column_rma_type']        = 'RMA TYPE';
$_['column_apply_type']        = 'Applied Type';

$_['tip_rebate'] = 'Maximum refund you can request:%s';
//该产品参与的返点协议此时正在生效，还没有到期
$_['msg_rebate_process'] = 'You are in the rebate agreement of this product.
If your request return for this product,the quantity returned successfully will not be counted into the rebate agreement';

//该产品参与的返点协议已到期并达成，Seller已经同意返点给Buyer（也就是说系统已经返点给Buyer）
$_['tip_rebate_over'] = 'Maximum refund you can request on each unit:%s';
$_['msg_rebate_over'] = 'In this purchase order detail, %s products has been involved in the rebate and total transaction is %s. 
Among these products, %s of them have been approved and already got a rebate of %s in total. 
Therefore, each of the rest products can refund %s. ';

//该产品参与的返点协议已到期并达成，但Buyer还没有申请返点
$_['msg_rebate_no_request'] = 'You have participated the rebate agreement of this product and achieved the agreed requirements.
The rebate should be %s.However,you haven\'t request the rebate. Seller can only offer you a refund of %s excluding the rebate.';

//该产品参与的返点协议已到期并达成，Buyer申请了返点但Seller还没有同意
$_['msg_rebate_request'] = 'You are requesting for a rebate of %s.
If you request a full refund now, only %s excluding rebate will be refunded to you once the seller agreed your rebate request.';

//该产品参与的返点协议已到期并达成，Buyer申请了返点但Seller拒绝
$_['msg_rebate_request_reject'] = 'You are requesting for a rebate of %s.
If you request a full refund now, only %s excluding rebate will be refunded to you once the seller agreed your rebate request.';