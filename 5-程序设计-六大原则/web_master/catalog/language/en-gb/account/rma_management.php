<?php
/**
 * User: lilei
 * Date: 2019/2/21
 * Time: 14:41
 */
// Heading
$_['heading_title'] = 'RMA Management';

// Text
$_['text_account'] = 'Account';
$_['text_rma_management'] = 'RMA Management';
$_['text_create_rma'] = 'Create RMA';
$_['text_edit_rma'] = 'Edit RMA';
$_['text_rma_detail'] = 'RMA Detail';
$_['text_order_id'] = 'Order ID';
$_['text_order_from'] = 'Order From';
$_['text_order_date'] = 'Order Date';
$_['text_order_status'] = 'Order Status';
$_['text_ship_to'] = 'Ship To';
$_['text_email'] = 'Email';
$_['text_phone'] = 'Phone';
$_['text_address'] = 'Address';
$_['text_store'] = 'Store';
$_['text_item_code'] = 'Item Code';
$_['text_description'] = 'Description';
$_['text_brand'] = 'Brand';
$_['text_unit_price'] = 'Unit Price';
$_['text_quantity'] = 'Quantity';
$_['text_item_status'] = 'Item Status';
$_['text_salesperson'] = 'Salesperson';
$_['text_action'] = 'Action';
$_['text_action'] = 'Action';
$_['text_sales_order_id'] = 'Sales Order ID';


// Error
$_['error_enter_ship_to_name'] = "Please enter the receiver name.";
$_['error_enter_ship_to_email'] = "Please enter the receiver's email address.";
$_['error_enter_ship_to_country'] = "Please enter the receiver's country.";
$_['error_enter_ship_to_country_us'] = "Receiver's Country must be 'US'.";
$_['error_enter_ship_to_city'] = "Please enter the receiver's city.";
$_['error_enter_ship_to_qty'] = "Please enter the quantity of the products to be shipped.";
$_['error_enter_ship_to_qty_max'] = "The number of inputs is incorrect!";
$_['error_enter_ship_to_phone'] = "Please enter the receiver's telephone number.";
$_['error_enter_ship_to_postal_code'] = "Please enter the receiver's zip code.";
$_['error_enter_ship_to_postal_code_us'] = "The receiver's zip code field must Include only numbers,-.";
$_['error_enter_ship_to_state'] = "Please enter the receiver's state/region.";
$_['error_enter_ship_to_address'] = "Please enter the receiver's address.";
$_['error_enter_reason'] = "Please select the RMA reason.";
$_['error_enter_rma_qty'] = "Please enter the RMA quantity.";
$_['error_enter_asin'] = "Please enter the ASIN.";
$_['error_enter_ship_to_qty_more_than_rmaqty'] = "Retransmission quantity is less than the number of applications!";
$_['error_enter_upload_img'] = "File upload can not be empty.";

// ToolTip
$_['image_tip'] = 'Need to provide a product image with the order number of the sales platform, or a product image that can be proved to be purchased on the Giga Cloud, the customer\'s email reflecting the problem, the picture of the bad product.';
$_['text_fedex_homepage'] = 'https://www.fedex.com/apps/fedextrack/?action=track&ascend_header=1&clienttype=dotcom&cntry_code=us&language=english&tracknumbers=';
$_['text_void_track'] = '(Invalid)';
$_['error_margin_not_retrun'] = 'Margin Item can not be returned!';

$_['tip_rebate'] = 'Maximum refund you can request:%s';
//该产品参与的返点协议此时正在生效，还没有到期
$_['msg_rebate_process'] = 'You are in the rebate agreement of this product.
If your request return for this product,the quantity returned successfully will not be counted into the rebate agreement';

//该产品参与的返点协议已到期并达成，Seller已经同意返点给Buyer（也就是说系统已经返点给Buyer）
$_['tip_rebate_over'] = 'Maximum refund you can request on each unit:%s';
$_['msg_rebate_over'] = ' In this purchase order detail, %s products has been involved in the rebate and total transaction is %s.
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

$_['tip_rmd_count_down'] = 'If the RMA is not responded within 7 days after it has been submitted, the Marketplace will process the request automatically and issue a resolution. If you disagree with said resolution, you may contact the Marketplace in the RMA Resolution Center.';
$_['tip_rmd_count_down2'] = 'After submitting your request, the Giga Cloud Marketplace will send a notification to the Seller asking for their prompt response.';
$_['tip_cwf_notice'] = 'Please Note: This is a Cloud Wholesale Fulfillment order, for RMA application of non product problems, seller may not approve.';

// rma创建
$_['error_sales_rma_exist'] = 'This sales order has an unprocessed return request, which can only be filled out again after processed.';
$_['error_purchase_rma_exist'] = 'This purchase order has an unprocessed return request, which can only be filled out again after processed.';
$_['error_full_sales'] = 'Only canceled cloud wholesale fulfillment orders can submit an RMA request.';
$_['error_max_return'] = 'This order has reached the maximum return limit.You can check the rma management.';
$_['error_order_status'] = 'Only canceled or completed sales order can apply rma.';
$_['error_CWF_RMA'] = 'Only canceled or delivered cloud wholesale fulfillment orders can submit an RMA request.';
$_['error_sales_order_rma'] = 'No more RMAs can be applied for this Item Code.  If you do need to apply for an RMA again, please contact the Marketplace Customer Service.';
$_['error_fedex_service_fee'] = 'FedEX Signature Service Fee can not be returned.';

// 仓租费
$_['tip_storage_fee'] = 'Storage fees incurred before the seller agreed the RMA will be deducted by Marketplace after the seller agreed the RMA. (Currently incurred: %s)';
$_['tip_margin_storage_fee'] = 'For products returned in the Margin Agreement due payment period, no storage fee will be charged.';

