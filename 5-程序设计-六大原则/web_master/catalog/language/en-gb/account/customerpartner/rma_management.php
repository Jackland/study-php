<?php
// Heading
$_['heading_title'] = 'RMA Management';
$_['heading_seller_center'] = 'Seller Central';

// Text
$_['text_success'] = 'Success: You have modified reviews!';
$_['text_list'] = 'RMA List';
$_['text_add'] = 'Add Review';
$_['text_edit'] = 'Edit Review';
$_['text_filter'] = 'Filter';

// Column
$_['column_no'] = 'No.';
$_['column_buyer'] = 'Name';
$_['column_rma_id'] = 'RMA ID';
$_['column_rma_date'] = 'RMA Date';
$_['column_item_code'] = 'Item Code';
$_['column_mpn'] = 'MPN';
$_['column_apply_for_reshipment'] = 'Applied for Reshipment';
$_['column_apply_for_refound'] = 'Applied for Refund';
$_['column_status'] = 'Status';
$_['column_processed_date'] = 'Processed Date';
$_['column_process'] = 'Process';

// Entry
$_['entry_rma_id'] = 'RMA ID';
$_['entry_rma_date'] = 'RMA Date';
$_['entry_mpn'] = 'MPN';
$_['entry_status'] = 'RMA Status';
$_['entry_processed_date'] = 'Processed Date';
$_['entry_item_name'] = 'Item Code';
$_['entry_nick_name'] = 'Name';
$_['entry_apply_for_reshipment'] = 'Applied for Reshipment';
$_['entry_apply_for_refound'] = 'Applied for Refund';
$_['entry_mpn_sku'] = 'MPN/Item Code';
$_['entry_order_id'] = 'Order ID';
$_['entry_reshipment_id'] = 'Reshipment ID';
$_['entry_reshipment_status'] = 'Reshipment Order Status';


// Help
$_['help_product'] = '(Autocomplete)';

// Error
$_['error_permission'] = 'Warning: You do not have permission to modify reviews!';
$_['error_product'] = 'Product required!';
$_['error_author'] = 'Author must be between 3 and 64 characters!';
$_['error_text'] = 'Review Text must be at least 1 character!';
$_['error_rating'] = 'Review rating required!';

// Text
$_['text_productlist'] = 'Your Order List';
$_['text_become_seller'] = 'For Become Seller inform Admin';
$_['text_account'] = 'Seller Central';
$_['text_order'] = 'Order Information';
$_['text_order_detail'] = 'Order Details';
$_['text_invoice_no'] = 'Invoice No.:';
$_['text_orderid'] = 'Order ID';
$_['text_status'] = 'Status:';
$_['text_date_added'] = 'Date Added';
$_['text_customer'] = 'Customer:';
$_['text_shipping_address'] = 'Shipping Address';
$_['text_shipping_method'] = 'Shipping Method';
$_['text_payment_address'] = 'Payment Address';
$_['text_payment_method'] = 'Payment Method';
$_['text_margin_id'] = 'Agreement ID';
$_['text_products'] = 'Products:';
$_['text_total'] = 'Total:';
$_['text_comment'] = 'Order Comments';
$_['text_history'] = 'Order History';
$_['text_paid'] = 'Paid';
$_['text_not_paid'] = 'Not Paid';
$_['text_success'] = 'You have successfully saved tracking for product(s).';
$_['text_empty'] = 'You have not made any previous orders!';
$_['text_success_history'] = 'Success: Order History has been added succesfully !';

$_['text_error'] = 'The order you requested could not be found!';

$_['text_wait'] = 'Please Wait!';

$_['text_rma_id'] = 'RMA ID';
$_['text_item_code'] = 'Item Code';
$_['text_mpn'] = 'MPN';
$_['text_order_from'] = 'OrderFrom';
$_['text_asin'] = 'ASIN';
$_['text_defective_number'] = 'The number of Defective Products';
$_['text_reason'] = 'Reason';


// Column
$_['column_name'] = 'Product Name';
$_['column_model'] = 'Model';
$_['column_sku'] = 'Item Code';
$_['column_mpn'] = 'MPN';
$_['column_quantity'] = 'Quantity';
$_['column_price'] = 'Price';
$_['column_total'] = 'Total';
$_['column_transaction_status'] = 'Transaction Status';
$_['column_action'] = 'Action';
$_['column_date_added'] = 'Date Added';
$_['column_status'] = 'Status';
$_['column_tracking_no'] = 'Tracking Number';
// $_['column_cancel_order']   = 'Cancel Order';
$_['column_comment'] = 'Comment';
$_['column_seller_order_status'] = 'Order Status';

// Tips
$_['tip_drop_shipping_logo'] = 'Dropshipping Buyer';
$_['tip_home_pickup_logo'] = 'Pick up Buyer';

$_['tip_rebate_seller'] = 'Maximum amount you can refund:%s';
//该产品参与的返点协议此时正在生效，还没有到期
$_['msg_rebate_process_seller'] = 'Buyer is in the rebate agreement of this product. If you agree to refund, the quantity returned successfully will not be counted into the rebate quantity any more.';

//该产品参与的返点协议已到期并达成，Seller已经同意返点给Buyer（也就是说系统已经返点给Buyer）
$_['tip_rebate_over_seller'] = 'Maximum amount you can refund on each unit:%s';
$_['msg_rebate_over_seller'] = 'In this purchase order detail, %s products has been involved in the rebate and total transaction is %s.
Among these products, %s of them have been approved and already got a rebate of %s in total.
Therefore, each of the rest products can refund %s.';

//该产品参与的返点协议已到期并达成，但Buyer还没有申请返点
$_['msg_rebate_no_request_seller'] = 'Buyer has participated in this rebate agreement, achieved the agreed requirements and the rebate should be %s.However,the buyer hasn\'t request for rebate.
If you offer a full refund,the buyer may get a repeated amount of refund.';

//该产品参与的返点协议已到期并达成，Buyer申请了返点但Seller还没有同意
$_['msg_rebate_request_seller'] = 'Buyer is requesting for %s rebate of this product. You haven\'t responded. If you agree to offer a full refund, buyer may get a repeated amount of refund.';

//该产品参与的返点协议已到期并达成，Buyer申请了返点但Seller拒绝
$_['msg_rebate_request_reject_seller'] = 'The buyer has requested a rebate of %s to this product and you have rejected. If you offer a full refund,the buyer request the rebate again and you agreed,the buyer may 
get a repeated amount of refund.';

$_['tag_description'] = '<img data-toggle="tooltip" class="%s" title="%s" style="padding-left: 1px" src="%s">';

$_['tip_waiting_period'] = 'If the RMA request is not responded to within 7 days, the Marketplace will process the submitted request automatically. If you disagree with any resolution issued, please contact the Marketplace at the RMA Resolution Center .';


