<?php
// Heading
//$_['heading_title']                   = 'Notifications';

// Text
$_['text_no_notification'] = 'No Notification Found';
$_['text_view_all_notificatins'] = 'View All Notifications';
$_['text_view_all'] = 'View All';
$_['text_account'] = 'Seller Central';
$_['text_notification_information'] = 'View Notifications';
$_['text_processing_status'] = 'Processing';
$_['text_complete_status'] = 'Complete';
$_['text_return'] = 'Returns';
$_['text_all_notification'] = 'All Notifications';
$_['text_close'] = 'Close';
$_['text_notifications'] = 'Notifications';
$_['text_order'] = 'Order: ';
$_['text_product'] = 'Product: ';
$_['text_rma'] = 'RMA: ';
$_['text_bid'] = 'BID: ';
$_['text_entry_seller'] = 'Seller: ';
$_['text_stock'] = 'Out of stock';
$_['text_approval'] = 'Approval';
$_['text_entry_review'] = 'Reviews';
$_['text_years'] = 'year(s)';
$_['text_months'] = 'month(s)';
$_['text_days'] = 'day(s)';
$_['text_hours'] = 'hour(s)';
$_['text_minutes'] = 'minute(s)';
$_['text_seconds'] = 'second(s)';
$_['text_order_add'] = 'New order: <a href="index.php?route=account/customerpartner/orderinfo&order_id=%s" target="_blank">#%s</a> has been placed by <b>%s</b> - <b>%s ago</b>';
$_['text_order_return'] = '<b>%s</b> has requested for order return: <a href="index.php?route=account/customerpartner/orderinfo&order_id=%s" target="_blank">#%s</a> for product <b>%s</b> <br/> <b>%s ago</b>';
$_['text_product_review'] = 'New review: #%s has been placed by <b>%s</b> For product <a href="index.php?route=product/product&product_id=%s" target="_blank">#%s</a> <br/> <b>%s</b>';

$_['text_product_stock'] = <<<HTML
   <a href="index.php?route=product/product&product_id=%s" target="_blank" title="%s">
     %s
   </a> is out of stock - <b>%s ago</b>;
HTML;

$_['text_product_approve'] = '<a href="index.php?route=product/product&product_id=%s" target="_blank"><b>%s</b></a> has been approved - <b>%s ago</b>';

$_['text_category_approve'] = 'Category: <a href="index.php?route=account/customerpartner/category&filter_name=%s" target="_blank"><b>%s</b></a> has been approved - <b>%s ago</b>';

$_['text_seller_review'] = 'New review: #%s has been placed by <a href="index.php?route=customerpartner/profile&id=%s" target="_blank"><b>%s</b></a> <br/> <b>%s ago</b>';
$_['text_order_status'] = 'Order: <a href="index.php?route=account/customerpartner/orderinfo&order_id=%s" target="_blank">#%s</a> status has been changed to <b>%s</b> <b>%s ago</b>';

$_['text_order_add_mp'] = 'New order: <a href="index.php?route=account/customerpartner/orderinfo&order_id=%s" target="_blank">#%s</a> has been placed by <b>%s</b> - <b>%s ago</b>';
$_['text_order_return_mp'] = '<b>%s</b> has requested for order return: <a href="index.php?route=account/customerpartner/orderinfo&order_id=%s" target="_blank">#%s</a> for product <b>%s</b> - <b>%s ago</b>';
$_['text_product_review_mp'] = 'New review: #%s has been placed by <b>%s</b> For product <a href="index.php?route=product/product&product_id=%s" target="_blank">#%s</a> - <b>%s ago</b>';
$_['text_seller_review_mp'] = 'New review: #%s has been placed by <a href="index.php?route=customerpartner/profile&id=%s" target="_blank"><b>%s</b></a> - <b>%s ago</b>';
$_['text_order_status_mp'] = 'Order: <a href="index.php?route=account/customerpartner/orderinfo&order_id=%s" target="_blank">#%s</a> status has been changed to <b>%s</b> - <b>%s ago</b>';
$_['text_rma_add_mp'] = <<<HTML
<b>New Application for RMA:</b><a href="%s" target="_blank">#%s</a> has been placed by <b>%s</b> - %s ago%s
HTML;
$_['text_bid_add_mp'] = <<<HTML
<b>%s</b> has submitted a BID request to <a href="%s" target="_blank">%s</a>: <a href="%s">#%s</a> - %s ago%s
HTML;

//tab
$_['tab_order'] = 'Order';
$_['tab_product'] = 'Product';
$_['tab_seller'] = 'Seller';
$_['tab_rma'] = 'RMA';
$_['tab_bid'] = 'BID';

$_['success_read_all'] = 'Set the current page records as read successfully!';

//warning
$_['error_warning_authenticate'] = 'Warning: You are not authorised to view this page, Please contact to site administrator!';
?>
