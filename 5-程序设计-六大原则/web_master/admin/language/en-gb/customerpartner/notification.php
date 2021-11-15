<?php
// Heading
$_['heading_title'] = 'Notifications';

// Text
$_['text_no_notification'] = 'No Notification Found';
$_['text_view_all'] = 'View All';
$_['text_account'] = 'Seller Central';
$_['text_notification_information'] = 'View Notification';
$_['text_processing_status'] = 'Processing';
$_['text_complete_status'] = 'Completed';
$_['text_return'] = 'Returns';
$_['text_all_notification'] = 'All Notifications';
$_['text_notifications'] = 'Notifications';
$_['text_order'] = 'Order: ';
$_['text_product'] = 'Products: ';
$_['text_entry_seller'] = 'Seller: ';
$_['text_stock'] = 'Out of stock';
$_['text_approval'] = 'Approval';
$_['text_entry_review'] = 'Reviews';
$_['text_order_add'] = 'New order: <a href="index.php?route=sale/order/info&user_token=%s&order_id=%s" target="_blank">#%s</a> has been placed by <b>%s</b> <br/> <b>%s ago</b>';
$_['text_order_return'] = '<b>%s</b> has requested for order return: <a href="index.php?route=sale/return&user_token=%s&filter_order_id=%s" target="_blank">#%s</a> for product <b>%s</b> <br/> <b>%s ago</b>';
$_['text_product_review'] = 'New review: #%s has been placed by <b>%s</b> For product <a href="index.php?route=catalog/review&user_token=%s&filter_product=%s" target="_blank">#%s</a> <br/> <b>%s ago</b>';

$_['text_product_stock'] = '<a href="index.php?route=catalog/product&user_token=%s&filter_name=%s" target="_blank"><b>%s</b></a> out of stock - <b>%s ago</b>';

$_['text_product_approve'] = '<a href="index.php?route=catalog/product&user_token=%s&filter_name=%s" target="_blank"><b>%s</b></a> has been approved - <b>%s ago</b>';

$_['text_category_approve'] = 'Category: <a href="index.php?route=customerpartner/sellercategory&token=%s" target="_blank"><b>%s</b></a> has been approved - <b>%s ago</b>';

$_['text_seller_review'] = 'New review: #%s has been placed by <a href="index.php?route=customerpartner/review&user_token=%s&filter_customer=%s" target="_blank"><b>%s</b></a> <br/> <b>%s ago</b>';
$_['text_order_status'] = 'Order: <a href="index.php?route=sale/order/info&user_token=%s&order_id=%s" target="_blank">#%s</a> status has been changed to <b>%s</b> <br/> <b>%s  ago</b>';

$_['text_order_add_mp'] = 'New order: <a href="index.php?route=sale/order/info&user_token=%s&order_id=%s" target="_blank">#%s</a> has been placed by <b>%s</b> - <b>%s ago</b>';
$_['text_order_return_mp'] = '<b>%s</b> has requested for order return: <a href="index.php?route=sale/return&user_token=%s&filter_order_id=%s" target="_blank">#%s</a> for product <b>%s</b> - <b>%s ago</b>';
$_['text_product_review_mp'] = 'New review: #%s has been placed by <b>%s</b> For product <a href="index.php?route=catalog/review&user_token=%s&filter_product=%s" target="_blank">#%s</a> - <b>%s ago</b>';
$_['text_seller_review_mp'] = 'New review: #%s has been placed by <a href="index.php?route=customerpartner/review&user_token=%s&filter_customer=%s" target="_blank"><b>%s</b></a> - <b>%s ago</b>';
$_['text_order_status_mp'] = 'Order: <a href="index.php?route=sale/order/info&user_token=%s&order_id=%s" target="_blank">#%s</a> status has been changed to <b>%s</b> - <b>%s ago</b>';

//tab
$_['tab_order'] = 'Order';
$_['tab_product'] = 'Product';
$_['tab_seller'] = 'Seller';

//warning
$_['error_warning_authenticate'] = 'Warning: You are not authorised to view this page, Please contact to site administrator!';
$_['error_permission'] = 'Warning: You do not have permission to modify notifications!';
?>
