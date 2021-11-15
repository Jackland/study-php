<?php
// Heading
$_['heading_title']            = 'Cloud Wholesale Fulfillment Order Address';

$_['cwf_describ']='About Cloud Wholesale Fulfillment';
$_['cwf_describ_tips']='Click to view Cloud Wholesale Fulfillment Guide';
$_['info_feneral_information'] ='General Information';
$_['info_cwf_type']='Cloud Wholesale Fulfillment Category';
$_['info_cwf_type_tips']='The type of warehouse receiving the product';
$_['info_cwf_type_fba']='Amazon FBA warehouse';
$_['info_cwf_type_ohter']='Other (non FBA)';
$_['info_cwf_type_other_tips']='1. Buyer\'s brick-and-mortar store<br>2. Buyer\'s private warehouse<br>3. If delivering to a residential address, please note the Marketplace will provide only updated transportation statuses.';

$_['info_dock_title']='Does receiving address have loading dock?';
$_['info_dock_title_tips']='A truck loading/unloading area. If address is a physical store location, please select the most applicable option. This category will only impact the vehicle sent, fulfillment and delivery time frames will remain unchanged.';

$_['table_ship_to']='Shipping Address Information';
$_['table_recipient']='Recipient';
$_['table_recipient_tips']='If there is no recipient information,please fill in the name of shipper.';
$_['table_phone']='Phone';
$_['table_email']="Email";
$_['table_address']='Street Address';
$_['table_city']='City';
//$_['table_state']='State';
$_['table_zip_code']='Zip Code';
$_['table_united_states']='United States';
//$_['table_country']='Country';
$_['table_order_comments']='Other Comments';

//bill  info
$_['bill_title']='Bill of Lading';
$_['bill_title_tips']='Items being delivered to Amazon FBA are required to have the product information supplied by Amazon.';
$_['bill_info_shipment']='Shipment ID';
$_['bill_info_amazon_po']='Amazon PO ID';
$_['bill_info_amazon_ref']='Amazon Reference ID';
$_['fba_warehouse_code']='FBA Warehouse Code';
$_['fba_warehouse_code_placeholder']='FBA Warehouse Code';
$_['fba_warehouse_code_tips']="To find code: Go to ‘Send/Replenish Inventory’ Amazon page. Code located at the end of the ‘Ship To’ address in brackets.";
$_['fba_amazon_reference_number']='Amazon Reference Number';
$_['fba_amazon_reference_number_arn']='Amazon Reference Number(ARN)';
$_['bill_info_team_lift']='Team Lift Sign';
$_['bill_info_number_pallets']='Number of Pallets';
$_['bill_info_number_pallets_desc']='The actual number of pallets used will be updated within 2 business days.';
$_['bill_info_number_pallets_tips']='The actual quantity of pallets will be confirmed after the warehouse has completed putting the product on the pallets, also known as ‘Completed Prep’.';
$_['bill_info_pallet_label']='Pallet Label';
$_['bill_info_pallet_label_desc']='Please upload pallet label as soon as possible once get the feedback of ‘Number of Pallets’ from marketplace. Or, the shipment may be impacted. ';
$_['bill_info_pallet_label_tips']='When you have been notified of the actual pallet quantity, download the pallet label from Amazon and upload to the Giga Cloud Marketplace, which will then begin the shipping process once the labels are completely uploaded.';

$_['bill_table_head_order_num']='#';
$_['bill_table_head_image']='Image';
$_['bill_table_head_item_code']='Item Code';
$_['bill_table_head_mer_sku']='Merchant SKU';
$_['bill_table_head_mer_sku_tips']='SKU in box label is Merchant SKU from Amazon, if the Merchant SKU isn’t an exact match it may impact your shipment.';
$_['bill_table_head_fn_sku']='FNSKU';
$_['bill_table_head_fn_sku_tips']='SKU in item label is FNSKU from Amazon, if the FNSKU isn’t an exact match it may impact your shipment.';
$_['bill_table_head_store']='Store';
$_['bill_table_head_qty']='QTY';
$_['bill_table_head_package_label']='Box Label';
$_['bill_table_head_package_label_tips']='Each item code will have one package label file. Each label file will have enough labels for the quantities of each item code. Please check the SKUs and label quantities carefully. If they are incorrect,   it may impact the shipment.';
$_['bill_table_head_product_label']='Item Label';
$_['bill_table_head_product_label_tips']='Each item code will have one product label, please check the SKU in the file carefully, if incorrect, it may impact the shipment.';

//button
$_['button_upload']='Upload File';
$_['button_attachments']='Attachments';
$_['button_check_out']="CHECK OUT";
$_['button_back_cart']="BACK TO CART";

$_['check_error_street_address'] = 'Street Address must be between 1 and %d characters!';
$_['check_error_txt_common']='%s must contain only letters, numbers, underscores, hypens, dot or parentheses and the length must be between %s and %s characters.';
$_['check_error_email']='email is not valid';
$_['check_error_combo']='%s is a combo item. Combo items cannot be shipped to Amazon FBA';
$_['check_error_available']='%s is not available.';
$_['check_error_size_error']='Incorrect dimensions/fulfillment fee data for Item Code %s. Please contact Giga Cloud customer service. ';
$_['check_error_quantity']='%s is not available in the desired quantity or not in stock!';
$_['check_contract_error_quantity']="The deposit for Future Goods Contract with agreements (ID %s) cannot be paid due to insufficient Seller's total quantity available for the contract, please contact the Seller to resolve this issue. ";
$_['check_error_load_package']='Box Label must be uploaded.';
$_['check_error_load_product']='Item label must be uploaded.';
$_['check_error_pallet_label']='Pallet Label must be uploaded.';
$_['check_error_pallet_label']='Pallet Label must be uploaded.';

$_['check_error_common_err_line']='An error occurred: Line %s, parameter-%s. Please refresh the page and try again.';
$_['check_error_common_err']='An error occurred: parameter-%s. Please refresh the page and try again.';
$_['check_error_cart_change']='Item quantity has been changed in Line %s. Current quantity is %s. Please confirm the quantity and submit.';
$_['check_error_cart_item_change']='Items in your cart has been changed, please refresh the page.';
$_['check_error_cart_item_price_change']='The product price has changed, please checkout again';
$_['check_error_cart_item_data_change']='Items in your cart has been changed, please refresh the page and submit again.';
$_['check_error_cart_item_combo']='%s is a combo item. Combo items cannot be shipped to Amazon FBA';
$_['error_futures_low_deposit'] = 'The deposit for Future Goods Contract with agreements (ID %s) cannot be paid due to insufficient Seller\'s deposit for the contract, please contact the Seller to resolve this issue.';
$_['save_success']='Saved successfully.';
$_['save_failed']='Save Failed! Please refresh page and try again.';

$_['upload_attachment_error']='Allowed file types: pdf, xls, jpg, png, and the file cannot exceed 30 M.';
$_['upload_size_type_error']=' must be a .pdf file and cannot exceed 30 M.';
$_['upload_error']='An error occurred in the upload, please try again.';
$_['upload_success']='Uploaded successfully.';
$_['upload_failed']='Upload Failed! Please upload again.';

$_['pallet_label_label'] = 'Pallet Label';
$_['pallet_label_tip'] = '1.Printing pallet labels on Amazon: Select ‘Plain Paper’ category on the print window under ‘Paper type’ to ensure 1 label per page.<br>2.Label amount should be based on estimated number of pallets. Labels will be placed on all 4 sides of each pallet.';
$_['team_lift_tip'] = 'Click the checkbox means the item is overweight, and need a overweight sign attached on the package. The marketplace system has estimated whether it is overweight according to the actual weight, but there may be deviation. Please choose whether it is necessary to attach an overweight label according to Amazon\'s requirements.';
$_['box_label_tip'] = '1.When generating box labels on Amazon, please select the \'plain paper\' category under the Paper type and ensure that there is only one label on each page of the PDF file.<br>2.The number of labels uploaded should be equivalent to the number of products in the single PDF file.';
$_['item_label_tip'] = '1.Print item labels using the Amazon Product link and ensure that there is only one label per page of the PDF file.<br>2.Only upload one label per product in the same PDF file.';
$_['volume_require_msg'] = 'The minimum volume requirement of ' . CLOUD_LOGISTICS_VOLUME_LOWER . 'ft³ has not been reached.';

$_['top_information_title'] = 'Learn more about Cloud Wholesale Fulfillment>>';

$_['volume_class'] = 'ft³';
$_['weight_class'] = 'lb';

$_['check_spot_agreement_error_quantity']='The quantity of products purchased must match the quantity specified in this Spot Price Agreement. Note: Any specified quantities from this agreement may only be purchased once.';
