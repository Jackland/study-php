<?php

// 平台期货政策说明
$_['text_policy_title'] = 'Policy of future goods on Marketplace';
$_['text_policy_acknowledged'] = 'I acknowledged the policy of future goods transaction on the B2B Marketplace.';
$_['text_policy_button'] = 'Confirm';
$_['text_policy_button_msg'] = 'Please check the box to agree to the above terms and conditions for the B2B Marketplace Future Goods Transaction Policy for continue.';

// 产品详情
$_['text_product_detail_image'] = 'Image';
$_['text_product_detail_item_code'] = 'Item Code(MPN)';
$_['text_product_detail_name'] = 'Product Name';
$_['text_product_detail_historical_price'] = 'Historical Price';
$_['text_product_detail_current_price'] = 'Current Price';
$_['text_product_detail_receiving_order'] = 'Is there a valid Incoming Shipment?';
$_['text_product_detail_historical_price_title'] = 'Historical price of this Item Code during the past 180 days.';
$_['text_product_detail_receiving_order_notice'] = 'The estimated time of arrival and estimated receiving time may be inaccurate due to force majeure, they are for reference only.';
$_['text_product_detail_receiving_order_table_number'] = "Receiving Order<br>Number";
$_['text_product_detail_receiving_order_table_quantity'] = "Estimated<br>Receiving Quantity";
$_['text_product_detail_receiving_order_table_arrival_time'] = "ETA";
$_['text_product_detail_receiving_order_table_receiving_time'] = "Estimated<br>receiving time";

// 紫色问号提示
$_['text_notice_historical_price'] = 'Historical price of this Item Code during the past 180 days.';
$_['text_notice_current_price'] = 'Current price of this Item Code, Spot Price, Rebate Price and Margin Price.';
$_['text_notice_receiving_order'] = 'The Incoming Shipment for commodities in transportation which have not arrived the warehouse.';
$_['text_notice_contract_status'] = 'Item Status of Future Goods Contract.';
$_['text_notice_is_bid'] = 'Does this  Future Goods Contract accept a bid requested by the Buyer. If no, then Buyer can only choose the settlement method and unit price stipulated in the contract to purchase the Future Goods Contract.';
$_['text_notice_delivery_date'] = 'Delivery date is the date when total quantity available for this contract received by the warehouse and ready for sale. When setting the delivery date, it is necessary to consider that the estimated receiving time of the items on receiving order should be no ealier than %s days after the estimated time of arrival.';
$_['text_notice_quantity'] = 'The quantity of future goods commodities that should arrive the warehouse within the delivery date.';
$_['text_notice_min_quantity'] = "Buyer can immediately commit to purchase the future goods commodities when the purchased quantity is greater or equal to the  minimum quantity for a single agreement without requiring the Seller's approval.";
$_['text_notice_payment_method'] = 'The settlement methods Buyer can choose to fulfill the Future Goods Agreement.';
$_['text_notice_deposit_percentage'] = 'The deposit percentage which should be paid by Seller/Buyer.';
$_['text_notice_deposit_amount'] = "Seller's deposit will be deducted by the Marketplace in advance for fulfilling the Future Goods Contract.";

// 新增合约
$_['text_form_contract_no'] = 'Contract ID';
$_['text_form_status'] = 'Contract Status';
$_['text_form_bid'] = 'Does this contract accept bid?';
$_['text_form_delivery_date'] = 'Delivery Date';
$_['text_form_total_quantity'] = 'Total Quantity Available for This Contract';
$_['text_form_min_quantity'] = 'The Minimum Quantity for a Single Agreement';
$_['text_form_payment_method'] = 'Settlement Method Option ';
$_['text_form_deposit_percentage'] = 'Deposit Percentage';
$_['text_form_deposit_amount'] = 'Estimated Deposit Totals';
$_['text_form_available_total_balance'] = 'Available Total Balance';
$_['text_form_available_remaining_balance'] = 'Available Total Credit';
$_['text_form_price'] = 'Contract price for Option ';

$_['text_contract_status_active'] = 'Active';
$_['text_contract_status_disabled'] = 'Disabled';
$_['text_contract_status_sold_out'] = 'Sold Out';
$_['text_contract_status_terminated'] = 'Terminated';

$_['text_contract_method_balance_amount'] = 'Direct Settlement';
$_['text_contract_method_change_margin'] = 'Transfer to Margin Transaction';
$_['text_form_total_quantity_preview'] = 'Total Quantity Available';

$_['text_alert_reminder'] = 'Reminder';
$_['text_alert_yes'] = 'Yes';
$_['text_alert_no'] = 'No';
$_['text_alert_cancel'] = 'Cancel';
$_['text_alert_confirm'] = 'Confirm';
$_['text_alert_ok'] = 'OK';
$_['text_alert_delivery_date_exist'] = 'A Future Goods Contract with Item Code %s1 and delivery date %s2 already exists. Do you want to view or edit?';
$_['text_alert_delivery_date_than_some_days'] = 'The Marketplace only support the Future Goods Contract with the delivery date within %s3 days. The Future Goods Contract( ID %s1) with Item Code %s2 has a scheduled delivery date greater than %s4 days, please modify it.';

$_['text_verify_delivery_percentage_error'] = 'The deposit percentage is allowed to be within %s1% to %s2% and your number is beyond this range. Please check and fill in again.';
$_['text_verify_delivery_date_later_than_today_error'] = 'The delivery date cannot be set before the current date.';
$_['text_verify_delivery_date_error'] = 'The format of delivery date is incorrect, please fix.';
$_['text_verify_delivery_date_required'] = 'The delivery date is required.';
$_['text_verify_total_quantity_required'] = 'The total quantity available for this contract is required.';
$_['text_verify_total_quantity_characters_error'] = 'Please enter an integer number greater than 0 or less than 9999.';
$_['text_verify_total_quantity_error'] = 'The format of total quantity available for this contract filled is incorrect, please fix.';
$_['text_verify_min_quantity_required'] = 'The minimum quantity for a single agreement is required.';
$_['text_verify_min_quantity_error'] = 'The format of minimum quantity for a single agreement filled is incorrect, please fix.';
$_['text_verify_min_quantity_characters_error'] = 'Please enter an integer number greater than 0 or less than total quantity available.';
$_['text_verify_min_quantity_characters_error_2'] = 'The minimum quantity for a single agreement must be greater than zero and smaller or equal to the remaining available quantity for upcoming agreements.';
$_['text_verify_deposit_percentage_required'] = 'The deposit percentage is required.';
$_['text_verify_deposit_percentage_format_error'] = 'The format of deposit percentage filled is incorrect, please fix.';
$_['text_verify_price_required'] = 'The contract price for option is required.';
$_['text_verify_price_error'] = 'The format of contract price for option filled is incorrect, please fix.';
$_['text_verify_price_characters_japan_error'] = 'Please enter a number greater than 0 or less than 999999999.';
$_['text_verify_price_characters_not_japan_error'] = 'Please enter a number greater than 0 or less than 9999999.99.';
$_['text_verify_total_balance_error'] = 'The Future Goods Contract cannot be submitted because of insufficient total balance.';
$_['text_verify_remaining_balance_error'] = 'The Future Goods Contract cannot be submitted because of insufficient total credit.';

$_['text_model_preview_title'] = 'The Future Goods Contract Preview';
$_['text_model_preview_notice'] = 'Please review and confirm the details of the Future Goods Contract as below.';
$_['text_model_preview_notice_change_percentage'] = 'The deposit percentage for this future goods product is scheduled to be changed. You may modify or sell this Future Goods Contract after the Marketplace has finished processing the submitted request to change the deposit percentage. Below is a preview of the contract, are you sure you wish to save and pay the deposit?';
$_['text_model_notice'] = 'Note: The selected delivery date should differ from other Future Goods Contracts of the same product.';
$_['text_model_preview_msg_total_balance'] = "The estimated total deposit for the Future Goods Contract (ID %s1) is %s2. This amount will be deducted from the total balance and refunded once the products have been delivered.";
$_['text_model_preview_msg_remaining_balance'] = "The estimated total deposit for the Future Goods Contract (ID %s1) is %s2. This amount will be deducted from the total credit and refunded once the products have been delivered.";

$_['text_add_contract_copy_success_msg'] = "The Future Goods Contract with delivery date %s has been created successfully.";
$_['text_add_contract_success_msg'] = "The Future Goods Contract with delivery date %s has been created successfully.";
$_['text_add_contract_error_msg'] = "The New Future Goods Product with delivery date %s has been created unsuccessfully.";
$_['text_add_contract_copy_success_msg_change_percentage'] = "The request to change deposit percentage for the Future Goods Contract (ID %s) has been submitted to the Marketplace successfully.";
$_['text_add_contract_success_msg_change_percentage'] = "The request to change deposit percentage for the Future Goods Contract (ID %s) has been submitted to the Marketplace successfully.";

// 合约列表
$_['text_list_title'] = 'Future Goods Offerings';
$_['text_list_create_contract'] = 'Create a Future Goods Contract';
$_['text_list_filter_id'] = 'Contract ID';
$_['text_list_filter_delivery_date'] = 'Delivery Date';
$_['text_list_filter_status'] = 'Contract Status';

$_['text_table_transaction_quantity'] = "The Overall Quantity <br> in the Existing Agreements";
$_['text_table_total_quantity'] = "Total Quantity <br> Available for This Contract";
$_['text_table_price'] = "Contract Price";
$_['text_table_status'] = "Contract Status";
$_['text_table_modified_time'] = "Last Modified Time";
$_['text_no_record_notice'] = "No Future Goods Contracts found. Please click the 'Create a Future Goods Contract' button to make your first Future Goods offering.";
$_['text_batch_no_contract_msg'] = "Please select at least one future goods contract.";

$_['text_action_delete'] = "Are you sure you wish to cancel this Future Goods Contract (ID:%s)?";
$_['text_action_delete_success_msg'] = "The Future Goods Contract has been canceled.";
$_['text_action_delete_error_msg'] = "The Future Goods Contract has been canceled unsuccessfully.";
$_['text_action_delete_error_msg_exist_agreement'] = "There are other related active agreements that are related this Future Goods Contract. This contract cannot be canceled.";
$_['text_action_terminate'] = "After the Future Goods Contract has been terminated, the Markeplace system will automatically refund the deposit for remaining products or any other excess deposit. Are you sure you wish to terminate this Future Goods Contract (ID:%s)?";
$_['text_action_terminate_success_msg'] = "The Future Goods Contract has been terminated. Please check your next bill for your refunded deposit.";
$_['text_action_terminate_error_msg'] = "The %s Future Goods Contracts have been terminated unsuccessfully.";
$_['text_action_terminate_error_msg_exist_apply_agreement'] = "The Future Goods Contract (ID %s) have related agreements which is waiting to be processed, please finish processing the agreements before terminating the contract.";
$_['text_action_batch_delete'] = "Are you sure you wish to cancel the selected %s Future Goods Contracts?";
$_['text_action_batch_delete_success_msg_2'] = "The %s1 Future Goods Contracts have been canceled. <br/>There are %s2 Future Goods Contracts that are related to Future Goods Agreements and cannot be canceled.";
$_['text_action_batch_delete_success_msg'] = "The %s1 Future Goods Contracts have been canceled.";

$_['text_action_batch_terminate'] = "Are you sure you wish to terminate the selected %s Future Goods Contracts?";
$_['text_action_batch_terminate_success_msg_2'] = "The %s1 Future Goods Contracts have been terminated successfully. <br/>There are %s2 Future Goods Contracts that cannot be terminated. <br/>Please contact the customer service for more details.";
$_['text_action_batch_terminate_success_msg'] = "The %s1 Future Goods Contracts have been terminated successfully.";

$_['text_tab_future_goods_contract'] = 'Future Goods Contract';
$_['text_tab_related_agreement'] = 'Related Agreements';
$_['text_tab_records_of_contract_changes'] = 'Records of Contract Changes';

// 编辑合约-详情
$_['text_edit_contract_title'] = 'Edit Future Goods Contract';
$_['text_view_contract_title'] = 'View Future Goods Contract';
$_['text_edit_form_transaction_quantity'] = 'The Overall Quantity in the Existing Agreements';
$_['text_edit_form_remaining_quantity'] = 'Remaining Available Quantity for Upcoming Agreements';
$_['text_not_show_reminder'] = "Don't show reminder anymore";
$_['text_edit_deposit_percentage_approved'] = 'The request to change deposit percentage has been approved.';
$_['text_edit_deposit_percentage_rejected'] = 'The request to change deposit percentage has been rejected.';
$_['text_edit_deposit_percentage_applied'] = 'The request to change deposit percentage has been pending.';
$_['text_edit_form_estimated_deposit_amount'] = 'Estimated Deposit Totals';
$_['text_edit_form_will_increase_deposit_amount'] = 'Will it increase the future goods deposit amount? ';
$_['text_edit_form_additional_deposit_amount'] = 'The difference in an additional deposit';

$_['text_edit_active_to_terminate'] = 'Once the Future Goods Contract has been terminated, any remaining product quantities will be unavailable for sale. The deposit for any remaining products will be refunded to you, please check your next bill for your refunded deposit. Are you sure you wish to terminate this Future Goods Contract (ID: %s)?';
$_['text_edit_terminate'] = 'Once the Future Goods Contract has been terminated, any excess deposit will be refunded back to you, please check your next bill for your refunded deposit. Are you sure you wish to terminate this Future Goods Contract  (ID: %s)?';
$_['text_edit_contract_success_msg'] = "The New Future Goods Product ( ID %s) has been edited successfully.";
$_['text_edit_contract_success_msg_1'] = "The request to change deposit percentage for the Future Goods Contract (ID %s) has been submitted to the Marketplace successfully.";
$_['text_edit_contract_error_msg'] = "The New Future Goods Product ( ID %s) has been edited unsuccessful.";

// 编辑合约-日志
$_['text_log_table_event'] = "Actions";
$_['text_log_table_quantity'] = "The Minimum Quantity <br/> for a Single Agreement";
$_['text_log_table_price'] = "Contract Price";
$_['text_log_table_bid'] = "Does this contract accept bid?";
$_['text_log_table_status'] = "Contract Status";
$_['text_log_table_method'] = "Settlement Method Option";

$_['text_log_verdict_approved'] = "(Approved)";
$_['text_log_verdict_rejected'] = "(Rejected)";

// 编辑合约-关联协议
$_['text_agreement_stat_quantity'] = "The Overall Quantity in the Existing Agreements";
$_['text_agreement_stat_uncompleted_quantity'] = "Incompleted Delivery Quantity";
$_['text_agreement_stat_delivered_quantity'] = "Delivered Quantity";
$_['text_agreement_stat_failed_delivered_quantity'] = "Failed Delivery Quantity";
$_['text_agreement_stat_delivery_countdown'] = "Delivery Countdown";

$_['text_agreement_delivery_low_stock_quantity'] = "The delivery of Future Goods Agreement (ID %s) cannot be completed due to the delivered quantity is greater than the inventory quantity minus the locked inventory.";
$_['text_agreement_batch_delivery_low_stock_quantity'] = "The delivery of Future Goods Agreements cannot be completed due to the delivered quantity is greater than the inventory quantity minus the locked inventory.";
$_['text_future_seller_apply_delivery_remark']   = 'The delivery for the %s PC(S) in the future goods agreement (Agreement ID: %s) has been completed successfully.';

$_['text_agreement_table_id'] = "Agreement ID";
$_['text_agreement_table_name'] = "Name";
$_['text_agreement_table_quantity'] = "Agreement <br> Quantity";
$_['text_agreement_table_amount'] = "Seller's Deposit";
$_['text_agreement_table_status'] = "Delivery Status";
$_['text_agreement_table_refund_status'] = "Status of Refunded Deposit";
$_['text_agreement_table_refund_time'] = "Seller's deposit - Time of Refund";
$_['text_agreement_table_actions'] = "Action";

$_['text_agreement_delivery_status_cancel'] = "Failed Delivery";
$_['text_agreement_delivery_status_complete'] = "Complete Delivery";
$_['text_agreement_delivery_status_incomplete'] = "Incomplete Delivery";

$_['text_agreement_table_refund_status_compensation_for_buyer'] = "Deposit paid to Buyer";
$_['text_agreement_table_refund_status_refund_seller'] = "Refund Seller's deposit";
$_['text_agreement_table_refund_status_pending_refund_seller'] = "Pending refund Seller's deposit";
$_['text_agreement_table_refund_status_pending_compensation_for_buyer'] = "Pending Deposit paid to Buyer";

$_['text_agreement_action_complete_delivery'] = "Complete Delivery";
$_['text_agreement_action_batch_complete_delivery'] = "Batch Complete Delivery";
$_['text_agreement_action_complete_delivery_request'] = "Deliver ahead of time";
$_['text_agreement_action_batch_complete_delivery_request'] = "Batch Submit requests to deliver ahead of time";

$_['text_agreement_action_complete_delivery_notice'] = "Are you sure to complete delivery for the future goods agreement (Agreement ID: %s)?";
$_['text_agreement_action_complete_delivery_success_msg'] = "The delivery for future goods agreement (Agreement ID: %s) has been completed successfully.";
$_['text_agreement_action_complete_delivery_error_msg'] = "The delivery for future goods agreement (Agreement ID: %s) cannot be completed.";
$_['text_agreement_action_complete_delivery_request_notice'] = "Are you sure to submit a request to Marketplace to deliver ahead of time for the future goods agreement (Agreement ID: %s)?";
$_['text_agreement_action_complete_delivery_request_success_msg'] = "The request to deliver ahead of time for future goods agreement (Agreement ID: %s) has been submitted.";
$_['text_agreement_action_complete_delivery_request_error_msg'] = "The request to deliver ahead of time for future goods agreement (Agreement ID: %s) cannot be submitted due to abnormal reasons, please check.";

$_['text_agreement_action_batch_complete_delivery_notice'] = "Are you sure to complete delivery for the selected %s future goods agreements?";
$_['text_agreement_action_batch_complete_delivery_success_msg_exist_uncompleted'] = "The delivery for %s1 future goods agreements has been completed. The delivery for %s2 future goods agreements cannot be completed.";
$_['text_agreement_action_batch_complete_delivery_success_msg'] = "The delivery for %s1 future goods agreements has been completed.";
$_['text_agreement_action_batch_complete_delivery_error_msg'] = "The delivery for %s1 future goods agreements cannot be completed.";
$_['text_agreement_action_batch_complete_delivery_request_notice'] = "Are you sure you want to submit request to deliver ahead of time for the selected %s future goods agreements?";
$_['text_agreement_action_batch_complete_delivery_request_success_msg_exist_uncompleted'] = "The request to deliver ahead of time for the %s1 future goods agreements has been submitted. The request to deliver ahead of time for %s2 future goods agreements cannot be submitted due to abnormal reasons, please check.";
$_['text_agreement_action_batch_complete_delivery_request_success_msg'] = "The request to deliver ahead of time for the %s1 future goods agreements has been submitted.";
$_['text_agreement_action_batch_complete_delivery_request_error_msg'] = "The request to deliver ahead of time for %s1 future goods agreements cannot be submitted due to abnormal reasons, please check.";

$_['text_future_seller_apply_early_delivery_remark'] = 'The request to deliver ahead of time has been submitted to Marketplace successfully, reason: ';
$_['text_no_record_default_notice'] = 'No Records!';
$_['error_product_price_proportion'] = 'The product price you would like to apply is too low. The Marketplace may charge the Seller an increased fulfillment fee based on current industry standards*. Are you sure you want to approve this bid request? <br>*To learn more, please visit the Help Center and view the \\\'Usage Fee Standard\\\' terms.';
//和上面的内容区别是中间增加了CWF shipping fee will incur an additional 25% surcharge.，如果修改了主要内容，下面部分也需要修改
$_['error_product_price_proportion_cwf'] = 'The product price you would like to apply is too low. The Marketplace may charge the Seller an increased fulfillment fee based on current industry standards*. CWF shipping fee will incur an additional 25% surcharge. Are you sure you want to approve this bid request? <br>*To learn more, please visit the Help Center and view the \\\'Usage Fee Standard\\\' terms.';
