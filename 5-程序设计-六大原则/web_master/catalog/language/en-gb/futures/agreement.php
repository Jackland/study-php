<?php

$_['text_head_title'] = 'Future Goods Details';
$_['test_detail_title'] = 'Future Goods Agreement Details';
$_['text_detail_title'] = 'Future Goods Details';

$_['column_agreement_no'] = 'Agreement ID';
$_['column_store']     = 'Store';
$_['column_item_code'] = 'Item Code';
$_['column_agreement_status'] = 'Agreement Status';
$_['column_store'] = 'Store';
$_['column_name'] = 'Name';
$_['column_delivery_status'] = 'Delivery Status';
$_['column_date_from_to'] = 'Last Modified Date Range';
$_['column_last_modified'] = 'Last Modified Time';
$_['column_status'] = 'Status';
$_['column_qty_of_agreement'] = 'Quantity of Agreement';
$_['column_purchased_qty'] = 'Purchased Quantity';
$_['column_agreement_qty'] = 'Agreement Quantity';
$_['column_unit_price'] = 'Unit Price of Agreement';
$_['column_deposit_of_agreement']= 'Agreement Deposit';
$_['column_last_unit_price']= 'Agreement Final Payment Unit Price';
$_['column_amount_of_agreement'] = 'Agreement Amount';
$_['column_delivery_date'] = 'Delivery Date';
$_['column_delivery_type'] = ' Settlement Method';
$_['column_item_code_seller'] = 'Item Code/MPN';
$_['column_currency'] = 'Currency';

$_['text_to_be_processed'] = 'To be processed';
$_['text_to_be_delivered'] = 'To be delivered';//等待入仓
$_['text_to_be_approval'] = 'WAITING FOR APPROVAL';//待审批
$_['text_for_the_delivery']= 'WAITING FOR SETTLEMENT';
$_['text_to_be_paid']      = 'To be paid';
$_['text_to_be_expired']      = 'To be expired';
$_['text_waiting_for_review']   = 'WAITING FOR REVIEW';//待处理的协议
$_['text_waiting_for_approval'] = 'WAITING FOR APPROVAL';//待审批的协议
$_['text_waiting_for_delivery'] = 'BEFORE ARRIVAL AT WAREHOUSE';//等待入仓的协议（原WAITING FOR DELIVERY）
$_['text_waiting_for_payment']  = 'WAITING FOR PAYMENT';//等待交割（原WAITING FOR PAYMENT）
$_['text_due_soon']             = 'DUE SOON';//即将到期
$_['tip_text_waiting_for_review']   = 'Waiting to be reviewed.';//待处理的协议
$_['tip_text_waiting_for_delivery'] = 'Waiting to arrive at the warehouse.';//等待入仓的协议
$_['tip_text_waiting_for_payment']  = 'Buyer needs to pay the amount due for the agreement, or pay the difference between the future goods and margin deposit in order to transfer to margin transaction.';//等待交割
$_['tip_text_due_soon']             = 'Delivery date will arrive soon.';//即将到期
$_['tip_text_to_be_processed_buyer'] = 'Applied, pending, rejected, time out, back order future goods agreement waiting for review.';
$_['tip_text_to_be_delivered'] = 'Products can only be delivered to the buyer after they have been entered into the warehouse.';
$_['tip_text_for_the_delivery']= 'Agreements waiting for selecting settlement methods.';
$_['tip_text_to_be_paid']      = 'Agreements waiting for paying future goods deposit, future goods final payment, and deposit after transfering to margin goods.';

$_['column_seller_center'] = 'Seller Central';
$_['column_bid_list'] = 'Complex Transactions List';
$_['column_product_bidding'] = 'Product Bidding';
$_['column_future_bid'] = 'Future Goods Bid';
$_['column_future_detail'] = 'Future Goods Details';

$_['tip_cancel'] = 'Are you sure you want to cancel this future goods agreement?';
$_['tip_ignore'] = 'Are you sure you want to ignore this future goods agreement?';
$_['tip_terminated'] = 'Do you confirm to terminate this future goods agreement?';

$_['tip_delivery_date'] = 'This date is expected delivery date before the product is delivered. This date is actual delivery date after the product is delivered.';
$_['tip_delivery_type'] = 'Final payment, transfered to margin goods payment and combined payment.';
$_['tip_delivery_type2'] = 'Buyer\'s method of delivery.';
$_['tip_combined_payment'] = 'Combination of future goods final payment and margin goods payment.';

$_['tip_delivery_qty'] = 'Qty must be a positive number and cannot exceed the quantity in agreement. ';
$_['tip_delivery_qty_1'] = 'Product quantity to purchase in future goods final payment must equal to product quantity in future goods agreement.';
$_['tip_delivery_qty_2'] = 'Product quantity requested for margin goods transfer must equal to product quantity in future goods agreement.';
$_['tip_delivery_qty_count'] = 'The sum of product quantity in future goods final payment and product quantity requested for transfering to margin goods should equal to the quantity in future goods agreement.';
$_['tip_delivery_price'] = 'Unit Price of Agreement only allows to have maximum two decimal places.';
$_['tip_delivery_price_japan'] = 'Unit Price of Agreement shall be a positive integer.';
$_['tip_delivery_price_too_few'] = 'Unit Price of Agreement should round off up to 2 decimal place and not be less than 0.';
$_['tip_delivery_margin_price_too_much'] = 'The margin agreement unit price of products transfered from future goods to margin goods cannot be greater than current product unit price. ';
$_['tip_delivery_margin_price'] = 'The unit price of products tansfered from future goods to margin goods cannot be lower than product unit price in future goods agreement.';
$_['tip_out_delivery_days_seller'] = 'Alert: Order has not arrived on agreed delivery date. You are at risk of being flagged for breaching contract terms, please complete delivery as soon as possible.';
$_['tip_out_delivery_days_buyer'] = 'Seller has been prompted to complete overdue delivery immediately. Please contact the seller regarding the status of your delivery.';
$_['tip_in_delivery_days'] = ' Your order will arrive for delivery in %s days.';
$_['tip_price_of_goods_value'] = 'Price of Goods Value';
$_['tip_total_amount_of_goods_value'] = 'Total Amount of Goods Value';
$_['tip_replinished_difference_for_margin_bid_less']    = 'The difference between the future goods collateral and margin deposit should be replenished when the collateral percentage of future goods is less than the deposit proportion of margin goods';
$_['tip_replinished_difference_for_margin_bid_greater'] = "The difference between the future goods collateral and margin deposit don't need to be replenished when the collateral percentage of future goods is greater or equal to the deposit proportion of margin goods";
$_['tip_replinished_difference_for_margin_bid'] = "Replenished difference amount between the future goods and margin deposit.";
$_['tip_days_of_margin_bid'] = 'After successfully transferred to margin transaction, the due amount of margin transaction should be paid within 30 days.';

$_['text_select_delivery_method'] = 'Select a future goods delivery method';
$_['text_final_payment'] = 'Proceed with the future goods final payment';
$_['text_final_qty'] = 'Quantity in final payment';
$_['text_freight']  = 'Freight cost';
$_['text_final_amount'] = 'Amount to pay';
$_['text_transfer_to_margin'] = 'Transfer to margin goods';
$_['text_margin_qty'] = 'Quantity to transfer';
$_['text_margin_price'] = 'Margin agreement unit price';
$_['text_margin_add_deposit'] = 'Additional cost for margin goods transfer';
$_['text_margin_final_price'] = 'Margin agreement final payment unit price ';
$_['text_margin_amount'] = 'Margin agreement total amount';
$_['text_margin_term'] = 'Margin agreement term';
$_['text_agreement_price'] = 'Agreement Price';//协议单价
$_['text_agreement_qty'] = 'Agreement Quantity';//协议数量
$_['text_collateral_totals'] = 'Collateral Totals';//协议定金
$_['text_deposit_amount'] = 'Deposit Amount';//协议定金
$_['text_last_modified_time'] = 'Last Modified Time';
$_['text_time_last_modified'] = 'Time Last Modified';
$_['text_payment_method'] = 'Payment Method';
$_['text_payment_method_detail'] = 'Payment Method Detail';//交割方式明细
$_['text_unsold_quantity'] = 'Unsold Quantity';//协议未完成数量
$_['text_sold_quantity'] = 'Sold Quantity';//协议已完成数量

$_['text_buyer_cancel_agreement']  = 'Buyer canceled the agreement.';
$_['text_cancel_content']  = 'Are you sure you want to cancel the future goods agreement (Agreement ID %s) ?';
$_['text_cancel_success']  = 'The future goods agreement (Agreement ID %s) has been canceled successfully.';
$_['text_cancel_failed']   = 'The status of agreement has been changed.';
$_['text_ignore_success']  = 'The future goods agreement (Agreement ID %s) has been ignored successfully.';
$_['text_future_negotiated_terminate_content']  = '确定向Seller发起协议ID为%s的协商终止协议的申请吗?<br>Seller一旦同意终止协议协商申请，期货保证金将会退回至您的账号，请注意查收。';
$_['text_future_negotiated_terminate_success']  = '已向Seller发起协商终止协议的申请，等待Seller处理';
$_['text_reason']                     = 'Reason';
$_['text_reason_can_not_left_blank']  = 'Reason cannot be left blank.';
$_['text_reason_more_max']            = 'Reason can not be more than 2000 characters.';
$_['text_buyer_termination_content']  = '一旦终止期货协议，无法退回已经支付的期货保证金。确定终止协议ID为%s的期货协议吗?';
$_['text_buyer_termination_success']  = '已成功终止协议';
$_['text_seller_appeal_success']  = 'The request has been sent to the Marketplace and is now being processed. Please check the status of your request for the latest updates.';
$_['text_seller_appeal_failed']  = 'The request was unable to be sent. Please try again.';
$_['text_seller_appeal_exp']  = 'The status of the Future Goods Agreement has changed, this claim request can no longer be submitted.';
$_['text_seller_appeal_files_failed']  = 'File size exceeds limit, please re-upload again.File size limit: Image files less than 20M, and PDF files less than 20M.';
$_['text_current_expired']  = 'The current page has expired, please refresh and try again.';

$_['text_future_buyer_process_seller_termination_content']  = 'Seller向您发起了协商终止协议申请，原因如下：%s &nbsp;请审批。';
$_['text_future_buyer_process_seller_termination_approved'] = '已同意Buyer发起协议ID为%s的终止协议申请。';
$_['text_future_buyer_process_seller_termination_reject']   = '已拒绝Buyer发起协议ID为%s的终止协议申请。';

$_['text_future_seller_apply_early_delivery_remark']   = 'The request to deliver ahead of time has been successfully submitted to Marketplace, reason: %s';
$_['text_future_seller_apply_early_delivery_msg']   = 'The request to deliver ahead of time has been successfully submitted to Marketplace, please check back for updates on the status of your request.';
$_['text_future_seller_apply_delivery_msg']   = 'Delivery for the Future Goods Agreement (Agreement ID: %s) has been successfully completed.';
$_['text_future_seller_apply_delivery_remark']   = 'The delivery for the %s PC(S) in the future goods agreement (Agreement ID: %s) has been completed successfully.';
$_['text_future_seller_contract_amount_error']   = 'Seller cannot approve this Future Goods Agreement request due to insufficient estimated deposit totals for the Future Goods Contract (ID %s). You must pay additional deposit for the Future Goods Contract, otherwise you would not be able to approve this Future Goods Agreement request.';

$_['futures_bid_agree_text'] = 'I’ve read and agree the terms of this agreements.';

$_['error_futures_bid_qty'] = 'The Quantity of Agreement shall not exceed 9999 and not less than 5.';
$_['error_futures_can_buy_qty'] = 'The Agreement Quantity shall not exceed %max_qty and not less than %min_qty.';
$_['error_futures_bid_price'] = 'Unit Price of Agreement only allows to have maximum two decimal places.';
$_['error_futures_bid_price_japan'] = 'Unit Price of Agreement shall be a positive integer.';
$_['error_futures_bid_price_too_few'] = 'Unit Price of Agreement should round off up to 2 decimal place and not be less than 0.';
$_['error_futures_bid_price_too_much'] = 'The unit price of agreement cannot be greater than current product unit price.';
$_['error_futures_bid_message'] = 'Message can not be empty or more than 2000 characters.';
$_['error_futures_agree_clause'] = 'Terms must be checked';
$_['error_futures_edit_price']      = 'Please enter a number greater than 0 or less than 9999999.99.';
$_['error_futures_edit_price_japan'] = 'Please enter a number greater than 0 or less than 999999999.';
