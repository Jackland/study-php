<?php
// Heading
$_['text_customer_order_upload'] = 'Upload Customer Orders';
$_['text_heading_title_details'] = 'Sales Orders Details';
$_['text_heading_title_other_instruction'] = 'External sales platform will call order import instructions';

// Text
$_['text_progress'] = "Upload Progress";
$_['text_sales_details'] = [
    'sales_order_id' => 'Sales Order ID:',
    'order_status'   => 'Order Status:',
    'order_date'     => 'Upload Time:',
    'order_from'     => 'Order From:',
    'item_list'      => 'Item List',
    'shipping_information'                   => 'Shipping Information',
    'signature_service_payment_details'      => 'Signature Service Payment Details',
    'order_comments'  => 'Order Comments',
    'item_code'       => 'Item Code',
    'number'           => 'No.',
    'item_comment'    => 'Item Comment',
    'store'           => 'Store(Brand)',
    'unit_price'      => 'Unit Price <br/> After Discount',
    'service_fee'     => 'Service Fee <br/> After Discount',
    'qty'             => 'QTY',
    'total_price'     => 'Total Price',
    'salesperson'     => 'Salesperson',
    'purchase_order'  => 'Purchase Order',
    'purchase_order_id'  => 'Purchase Order ID',
    'rma_id'          => 'RMA ID',
    'total_price_s'     => 'Total Price:',
    'ship_to_service' => 'Shipping Service',
    'shipped_date'    => 'Shipped Date',
    'ship_to_name'    => 'Shipping Recipient',
    'ship_to_phone'   => 'Recipient Phone #',
    'ship_to_email'   => 'Recipient Email Address',
    'ship_to_address' => 'Shipping Address',
    'sub_item_code'   => 'Sub-item Code',
    'sub_item_qty'    => 'Sub-item Qty',
    'item_status'     => 'Item Status',
    'carrier'         => 'Carrier',
    'tracking_number' => 'Tracking Number',
    'label'           => 'Label',
    'package_qty'     => 'Package Qty',
    'price_per_package' => 'Price per Package',
    'transaction_fee'   => 'Transaction Fee',
    'payment_method'    => 'Payment Method',
    'sub_total'         => 'Sub-Total:',
    'total_transaction_fee'          => 'Total Transaction Fee:',
    'total_signature_service_fee'    => 'Total Signature Service Fee:',
    'order_history'    => 'Order History',
    'date_added'       => 'Date Added',
    'status'           => 'Status',
    'comment'          => 'Comment',

];
$_['text_label'] = "Upload Label";
$_['text_label_details'] = "Label Details";
$_['text_preview_label'] = "Preview Label";
$_['text_close_confirm_info'] = "Are you sure to leave? Unsaved information will be discarded.Please go to the sales order page to upload the labels.";
$_['text_submit'] = "Submit";
$_['text_combo'] = "combo";
$_['text_item_code'] = "Item Code";
$_['text_item_asin_code'] = "Item Asin Code";
$_['text_ship_method'] = "Ship Method";
$_['text_tracking_number'] = "Tracking Number";
$_['text_qty'] = "QTY";
$_['text_subitem_code'] = "Sub-item Code";
$_['json_dropship_data'] = [
    'code' =>'2000',
    'text' =>'Success',
];
$_['text_file_size'] = ". Please upload the file in PDF format with a size of no more than 1MB.";
$_['text_combo_notice'] = ". If the product is a combo product, please upload one label for each sub-item and fill in the corresponding tracking numbers.";
$_['text_combo_submit_notice'] = ". Before submitting, please make sure the Tracking Number and Order ID are consistent with those in preview picture of uploaded Label.";
$_['text_preview_tracking_number'] = 'Preview Tracking Number:';
$_['text_preview_order_id'] = 'Preview Order ID:';


$_['text_history'] = "Upload History";
$_['text_upload'] = 'Upload file';
$_['text_upload_tips'] = "Please note that only the labels of general selling platforms that include Home Depot, Overstock and Lowe's, are accepted by the GigaCloud B2B Marketplace Will Call service.";
$_['text_subitem_fill'] = 'To fill in the tracking number of the sub-item from a combo item, please send the application to Amazon first.';
$_['text_order_processing'] = 'Save order';
$_['text_order_upload_manifest'] = 'Upload Manifest';
$_['text_process_success'] = 'Process success!';
$_['text_dropship_process_success'] = 'You have successfully uploaded a file.';
$_['text_download_template_file'] = 'Download Template';
$_['text_upload_customer_orders_file'] = "Upload Customers' Orders File";
$_['text_choose_pickup_method'] = "Choose pickup method";
$_['text_improve_pickup_info'] = "Improve pickup information";
$_['text_use_existing_address'] = "I want to use an existing address";
$_['text_use_new_address'] = "I want to use a new address";
$_['text_order_number'] = 'order number';
//$_['text_has_no_cost_product'] = "The following products are not in stock and need to be purchased";
$_['text_has_no_cost_product'] = "The following products are not your inventory and need to be purchased to successfully ship.";
//$_['text_has_no_enter_product'] = "The following products, the platform is not entered, can not be purchased";
//$_['text_has_no_enter_product'] = "You are currently not authorised to retail following products or they don not exist on the platform.";
$_['text_has_no_exist_product'] = 'These products do not exist on the platform.';
$_['text_has_oversize_product'] = 'The following products are oversized items. Please confirm the address and delivery time with your customers first. Once confirmed or anything need to be modified, please notify Gigacloud by emailing service@gigacloudlogistics.com. Also, if you want to cancel any of these orders, please directly select to cancel them.';
$_['text_has_no_enter_product'] = 'You are currently not authorised to retail following products.';
//$_['text_has_process_success'] = "Products in the following order list are in stock and do not need to be purchased";
$_['text_has_process_success'] = "Items are already in your inventory; there is no need to re-order.";
$_['text_oversize_item_caution'] ="The order include oversized item,Please contact Platform Customer Service (Send Email: service@gigacloudlogistics.com).";
$_['text_auto_buy'] = "This order will perform an automatic purchase operation and does not support re-ordering.";
$_['text_notice_label'] = "<span style='color:red;font-weight: bold'>%s</span>&nbsp;of your sales orders cannot be shipped due to label issues. Please upload modified labels as soon as possible.&nbsp;<a style='cursor: pointer;color:#3A75DC' onclick='getRejectLabelView(this)'>Click to view</a>.";

$_['CUSTOMER_ORDER_STATUS_1'] = "New Order";
$_['CUSTOMER_ORDER_STATUS_2'] =  'Being Processed';
$_['CUSTOMER_ORDER_STATUS_4'] =  'On Hold';
$_['CUSTOMER_ORDER_STATUS_8'] =  'Part Out';
$_['CUSTOMER_ORDER_STATUS_16'] = 'Canceled';
$_['CUSTOMER_ORDER_STATUS_32'] = 'Complete';
$_['CUSTOMER_ORDER_STATUS_64'] = 'LTL Check';
$_['CUSTOMER_ORDER_STATUS_127'] = 'Pending Charges';
$_['CUSTOMER_ORDER_STATUS_128'] = 'ASR To Be Paid';
$_['CUSTOMER_ORDER_STATUS_129'] = 'Check Label';
$_['CUSTOMER_ORDER_STATUS_257'] = 'Waiting for Pick-up';

// radio
$_['radio_pickup_buyer'] = "Pickup at buyer";
$_['radio_pickup_seller'] = "Pickup at seller";


// Entry
$_['entry_upload'] = 'Upload File';
$_['entry_dropship_upload'] = 'Upload Amazon Dropship Order';
$_['entry_progress'] = "Progress";
$_['entry_contact_name'] = "Contact Name";
$_['entry_street_address'] = "Street Address";
$_['entry_city_town'] = "City/Town";
$_['entry_zip_code'] = "ZIP/Postal Code";
$_['entry_country'] = "Country";
$_['entry_zone'] = "State/Region/Province";
$_['entry_product_name'] = "Product Name";
$_['entry_qty'] = "Qty";
$_['entry_item_code'] = "Item Code";
$_['entry_seller'] = "Store Name";
$_['entry_price'] = "Unit Price";
$_['entry_quantity'] = "<span data-toggle=\"tooltip\" title=\"\" data-original-title=\"Quantity in seller’s stock\">Quantity in Stock</span>";


// Help
$_['help_upload'] = "Upload sales order .csv file";
$_['help_dropship_upload'] = "Upload .csv order file downloaded from Amazon.You don't need to convert it to Giga Cloud order template.";
$_['help_wayfair_upload'] = "Upload .csv order file downloaded from Wayfair.You don't need to convert it to Giga Cloud order template.";

// Error
$_['error_file'] = 'Upload file could not be found!';
$_['error_all_order_sku'] = 'All orders are invalid.';
$_['error_upload'] = 'File could not be uploaded!';
$_['error_delete'] = 'File could not be delete!';
$_['error_filetype'] = 'Invalid file type!';
$_['error_file_size'] = 'The file uploaded is not valid, please upload again.';
$_['error_file_tracking_number'] = 'Tracking number not recognized. Please review the label document or contact the Marketplace Customer Service for assistance.';
$_['error_install'] = 'Upload order file are taking place please wait a few seconds before trying to upload!';
$_['error_file_content'] = 'The columns of the uploaded file are inconsistent with the template,please check and re-upload.';
$_['error_file_empty'] = 'No data was found in the file.';
$_['order_id_missed'] = 'OrderId is empty ,please check the uploaded CSV file.';
$_['seller_id_missed'] = 'There are records that SellerId is empty ,please check the uploaded CSV file.';
$_['order_id_exist'] = 'OrderId is already exist ,please check the uploaded CSV file.';
$_['error_no_select_file'] = "Please select file upload";
$_['error_happened'] = 'something wrong happened. Please try it again.';
$_['error_improve_pickup_info'] = "Please improve pickup information";
$_['error_not_null'] = "Can not be left blank.";
$_['error_no_file'] = "No file!";
$_['error_input_contact_info'] = "Please enter a contact name!";
$_['error_input_street_address'] = "Please enter the street address!";
$_['error_input_city'] = "Please enter the city or town!";
$_['error_input_country'] = "Please select the country!";
$_['error_input_zone'] = "Please select the state/region/province!";
$_['error_cancel_param'] = "Invalid params!";
$_['error_can_cancel'] = "This order does not allow cancellation! You may refresh the page and try again later.";
$_['error_cannot_cancel'] = 'This sales order cannot be canceled, please contact our customer service team.';
$_['error_is_syncing'] = "This order is performing an order synchronization task. Please try this request again later.";
//$_['error_cancel_time_late'] = "Failed!Please contact Platform Customer Service<br>(send Email:help@b2b.gigacloudlogistics.com)";
$_['error_store_id_error'] = "Please contact our customer service team to process.";
$_['error_cancel_time_late'] = "We are processing your request, please check the processing status later.";
$_['error_response'] = "The system did not successfully process the request, please try again later.";
$_['error_invalid_param'] = "Invalid params!";
$_['error_sku_change'] = "The Item Code for this order is not allowed to be modified.";
$_['error_sku_exist'] = "The new Item Code does not exist in the system.";
$_['error_sku_combo'] = "The new Item Code cannot be a combo item.";
$_['error_sku_oversize'] = "The new Item Code cannot be an oversized item.";
$_['error_order_qty'] = "The order quantity is abnormal.";
$_['error_tracking_number'] = "Please only input numerical or alphabet values.";
$_['error_file_fill'] = "Failed, the selected orders contain labels that haven\'t been uploaded. Please submit after uploading all labels.";
$_['error_bol_fill'] = "Failed, the selected orders contain bol that haven\'t been uploaded. Please submit after uploading all labels.";
$_['error_tracking_number_fill'] = "Failed, the selected orders contain tracking numbers that haven\'t been filled. Please submit after filling in all tracking numbers.";
$_['error_tracking_number_repeated'] = "The selected orders contain repeat tracking numbers, it cannot be submitted.";
$_['error_repeat_submit'] = "Please do not repeat the submission.";
$_['error_file_repeated'] = "The label uploaded is repeat with the other label,please select again.";
$_['error_bol_repeated'] = "The bol uploaded is repeat with the other bol,please select again.";
$_['error_order_no_choose'] = "you haven\'t selected any order to submit.";
$_['warning_sku_empty'] = "Item Code can not be left blank.";
$_['error_cancel_oversize'] = "This order contains oversized details and is not allowed to be cancelled.";
$_['error_europe_tracking_number_fill'] = "Failed, the selected orders contain tracking numbers that haven't been filled. Please submit after filling in all tracking numbers.";
$_['error_us_other_file_size'] = "Uploaded label does not match the 4”x6” (102mmx152mm) format.  Please select the right file and upload again";
$_['error_us_wayfair_file_size'] = "Uploaded label does not match the 4\"x6\" (102mmx152mm) format.  Please select the right file and upload again.";
$_['error_us_other_order_review_approved'] = 'This order is being reviewed by customer service team. If you need to modify it, please contact our customer service.';
$_['error_is_contact_service'] = "Failed, Please contact customer service.";
$_['error_file_exceed'] = "The file loaded must not exceed 1MB.";

//dialog
$_['dialog_title_confirm'] = "Confirm";
$_['dialog_title_error'] = "Error";
//$_['dialog_title_info'] = "Info";
$_['dialog_title_info'] = "Notice";
$_['dialog_title_warning'] = "Warning";

$_['dialog_title_fail_log'] = "Failure log";
$_['dialog_title_edit_shipping'] = "Confirm";
$_['dialog_btn_yes'] = "Yes";
$_['dialog_btn_no'] = "No";
$_['dialog_btn_ok'] = "OK";
$_['dialog_btn_go_back'] = "Go Back";

//change order information Text
$_['text_button_view']     = "View";
$_['text_button_ok']     = "OK";
$_['text_button_edit_label']     = "Edit Label";
$_['text_button_manage_label']     = "Manage Labels";
$_['text_button_view_label']     = "View Label";
$_['text_button_manage_label']     = "Manage Labels";
$_['text_button_edit']     = "Edit";
$_['text_button_save']     = "Save";
$_['text_button_cancel']     = "Cancel";
$_['text_order_cancel']     = "Cancel Order";
$_['text_modify_shipping']     = "Modify Address";
$_['text_modify_sku']     = "Modify SKU";
$_['text_button_failure_log']     = "Click to see the reason for the failure.";
$_['text_button_redirect_dm']     = "Pay to ASR";
$_['text_sure_cancel']     = "Do you confirm to cancel this order?";
$_['text_sure_batch_cancel']     = "Do you confirm to cancel these orders?";
$_['text_remove_bind_stock_no']     = "Apply for RMA";
$_['text_remove_bind_stock_yes']     = "Keep in stock";
$_['text_remove_bind_stock_no_notice']     = "please go to \"RMA Management\" page to create RMA after the order is canceled.";
$_['text_remove_bind_stock_warning']     = "Warning：Please choose one of the above options";
$_['text_processing_method']    = 'Processing Method';
$_['text_placeholder']          = '1~200 Characters';
$_['text_cancellation_reason_warning'] = 'Warning:Cancellation reason is required and can not be more than 200 characters.';
$_['text_cancellation_reason']  = 'Cancellation Reason';
$_['text_cancel_success']     = "Canceled successfully.";
$_['text_homepick_europe_cancel_success']     = "Canceled successfully.";
$_['text_homepick_europe_cancel_failed']     = "Failed to cancel. Please contact the Marketplace Customer Service for assistance.";
//$_['text_cancel_failed']     = "Order cancellation failed.";
$_['text_change_sku_success']     = "Change Item Code successfully.";
$_['text_change_sku_failed']     = "Change Item Code failed.";
$_['text_change_ship_success']     = "Change Shipping information successfully.";
$_['text_change_ship_failed']     = "Change Shipping information failed.";
$_['text_autobuyer_modify_sku_success'] = "This operation may take a couple of minutes. Please wait, then refresh the page to see the operation result. If unsuccessful, click icon to see why.";

$_['text_error_column_title'] = 'All or part of the address below is not a valid shipping address, a question mark will appear on Sales Order page. please modify as needed. If you continue to receive this message, get a valid address through cancelling the order, downloading the language pack of the region in question, filling in the information using the language of that same region and submitting the address again. If you are still experiencing problems, please contact customer service.';
$_['text_error_column_1'] = 'The [%s] is not valid, please modify as needed. If you continue to receive this message, get a valid address through cancelling the order, downloading the language pack of the region in question, filling in the information using the language of that same region and submitting the address again.';
$_['text_error_column_128'] = 'The [%s] is not valid, please modify as needed. If you continue to receive this message, get a valid address through cancelling the order, downloading the language pack of the region in question, filling in the information using the language of that same region and submitting the address again.';
$_['text_error_column_2'] = 'The [%s] is not valid, please modify as needed. If you continue to receive this message, get a valid address through cancelling the order, downloading the language pack of the region in question, filling in the information using the language of that same region and submitting the address again.If you are still experiencing problems, please contact customer service.';
$_['text_error_column_64'] = 'The [%s] is not valid. If you continue to receive this message, get a valid address through cancelling the order, downloading the language pack of the region in question, filling in the information using the language of that same region and submitting the address again.If you are still experiencing problems, please contact customer service.';

$_['text_cancel_wait']     = "Your sales order cancellation request has been successfully submitted. To view the processed result, refresh the page. If the cancellation failed, please contact Customer Service below to request intercept of sales order.";
$_['text_update_address_wait']     = "Address modification request for your sales order has been successfully submitted. <br/>To view the processing result, please refresh the page. <br/>If the address modification failed, please contact Customer Service.";
$_['text_cancel_seller_wait']     = "This operation may take a couple of minutes. Please wait, then refresh the page to see the operation result. If unsuccessful, click [!] to see why.";
$_['text_cancel_failed']     = "Action cannot be completed at this time.This order is being packed.Please send email to service@b2b.gigacloudlogistics.com for assistance.";
$_['text_ltl_cancel_failed']     = "This order has been handled for shipment, please contact our online customer service team to cancel.";
$_['text_zk_cancel_failed']     = "This order has been handled for shipment, please contact our online customer service team to cancel."; //和上面的翻译一致，产品要求先用这个，后续统一调整
$_['text_sku_wait']        = "This operation may take a couple of minutes. Please wait, then refresh the page to see the operation result. If unsuccessful, click [!] to see why.";
$_['text_sku_inventory_1']      = "Are you sure you want to change the Item Code from [%s] to [%s] as the current inventory is %s ? If yes,you need to purchase %s more.";
$_['text_sku_inventory_2']      = "Are you sure you want to change the Item Code from [%s] to [%s],as the current inventory is %s ? If yes, your inventory is enough and you do not need to purchase any item. The items not needing to be purchased in the table will be removed.";

//table
$_['text_table_head_time']     = "Operation Time";
$_['text_table_head_type']     = "Operation Type";
$_['text_table_head_before']     = "Previous Status";
$_['text_table_head_target']     = "Target Status";
$_['text_table_head_reason']     = "Failure Reason";

//label
$_['text_ship_label_name']     = "Shipping Recipient";
$_['text_ship_label_email']     = "Recipient Email Address";
$_['text_ship_label_phone']     = "Recipient Phone #";
$_['text_ship_label_address']     = "Shipping Address Detail";
$_['text_ship_address']     = "Shipping Address";
$_['text_ship_label_city']     = "Shipping City";
$_['text_ship_label_state']     = "Shipping State";
$_['text_ship_label_code']     = "Shipping Postal Code";
$_['text_ship_label_country']     = "Shipping Country";
$_['text_ship_label_comments']     = "OrderComments";
$_['text_select_country']     = " --- Select Country --- ";
$_['text_select_state']     = " --- Select Region / State --- ";

$_['error_ship_label_name']     = "ShipToName must be between 1 and 40 characters!";
$_['error_ship_label_email']     = "ShipToEmail must be between 1 and 90 characters!";
$_['error_ship_label_email_reg']     = "The format of the email address is incorrect";
$_['error_ship_label_phone']     = "ShipToPhone must be between 1 and 45 characters!";
$_['error_ship_label_address']     = "ShipToAddressDetail must be between 1 and 80 characters!";
$_['error_ship_label_address_1']     = "ShipToAddressDetail must be between 1 and %d characters!";
$_['error_ship_label_city']     = "ShipToCity must be between 1 and 30 characters!";
$_['error_ship_label_state']     = "The Shipping State field needs to select a Region/State option";
$_['error_ship_label_state_length']     = "ShipToState must be between 1 and 30 characters!";
$_['error_ship_label_code']     = "ShipToPostalCode must be between 1 and 18 characters!";
$_['error_ship_label_country']     = "The Shipping Country field needs to select a country option";
$_['error_ship_label_us_country']     = "The Shipping Country must be 'US' or 'CA'.";
$_['error_ship_label_us_code']     = "The Shipping Postal Code field must Include only numbers,-.";
$_['error_ship_label_comments']     = "The maximum length of the OrderComments field is 1500";

$_['error_margin_expire']     = "The margin agreement ID %s was expired. (Margin Validity:%s~%s)";
$_['error_margin_approve_expire']     = "The margin agreement ID %s was expired. ";
$_['error_spot_approve_expire']     = "The spot agreement ID %s was expired. ";
$_['error_rebate_approve_expire']     = "The rebate agreement ID %s was expired. ";
$_['error_future_margin_approve_expire']     = "The future margin agreement ID %s was expired.";
$_['error_wayfair_file_content'] = 'The columns of the uploaded file are inconsistent with the template,please check and re-upload.';


$_['error_walmart_field_PO#']     = "Line [%s], [%s] must be between [%s] and [%s] characters long and must only contain letters, numbers, - or _.";
$_['error_walmart_field_empty']     = "Line [%s], [%s] can not be left blank.";
$_['error_walmart_field_carrier_empty']     = "Line [%s], [%s] or [%s] can not be left blank.";
$_['error_walmart_field_length']    = "Line [%s], [%s] must be between [%s] and [%s] characters.";
$_['error_walmart_field_zip']    = "Line [%s], [%s] must Include only numbers,-.";
$_['error_walmart_field_number']    = "Line [%s], [%s] must be between [%s] and [%s] integer.";
$_['error_walmart_field_format']    = "Line [%s], [%s] format error.";
$_['error_walmart_field_valid']     = "Line [%s], [%s] is not valid or currently not accepted by our marketplace.";
$_['error_walmart_field_map']       = "Line [%s], cannot find the mapping of [%s]. ";
$_['error_walmart_field_po']        = "Line [%s], [%s] is duplicate with the other order, please modify it and upload again. ";
$_['error_walmart_field_s2s_asn']   = "Line [%s], [%s] of S2S order can not be left blank.";
$_['error_walmart_field_s2s_store'] = "Line [%s], [%s] of S2S order must be Store.";
$_['error_walmart_field_po_sku']    = "SKU in line [%s] should be different from SKUs in other order details in this order.";
$_['error_walmart_field_line']    = "Line [%s], [%s] cannot be the same for the same order: [%s]";
$_['error_walmart_filetype']    = "The pickup order file from walmart should be .xls or .xlsx!";
$_['error_associate_to_order']    = "to be paid 有绑定关系";
$_['error_other_field_platform']    = "Line %s, [%s]:'%s' is currently not supported by Gigacloud.<a target='_blank' href='index.php?route=account/customer_order/otherInstructionHref' style='cursor: pointer;color:#3A75DC;text-decoration:underline' >Click for help</a>";
$_['error_other_field_order']     = "Line %s, [%s] must be between %s and %s characters long and must only contain letters, numbers, - or _.";
$_['error_upload_no_data']     = 'No data was found in the file.';


