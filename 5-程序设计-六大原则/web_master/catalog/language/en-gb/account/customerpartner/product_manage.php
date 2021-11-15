<?php
//product manage
$_['heading_title_product_manage'] = 'Product Management';
$_['heading_title_inventory_manage'] = 'Inventory Query';
$_['heading_title_product_details'] = 'Product Details';
$_['heading_title_available_inventory'] = 'Available Inventory Management';
$_['heading_home'] = 'Home';
$_['heading_seller_center'] = 'Seller Central';
$_['text_import_products'] = 'Import Products';
$_['text_import_product_records'] = 'Upload History';
$_['text_effect_time'] = 'Date of Effect';
$_['text_status_on_shelves'] = 'Available';
$_['text_status_off_shelves'] = 'Unavailable';
$_['text_status_to_be_available'] = 'To be available';
$_['text_status_visible'] = 'Visible';
$_['text_status_invisible'] = 'Invisible';
$_['text_price_display'] = 'Display price';
$_['text_quantity_display'] = 'Display Quantity';
$_['text_new_price'] = 'Current Price';
$_['text_in_stock_qty'] = 'In stock quantity';
$_['text_next_shipment_qty'] = 'Estimated quantity of next arrival';
$_['text_next_shipment'] = 'Date of Next Arrival';
$_['text_status'] = 'Status';
$_['text_action'] = 'Action';
$_['text_change_modified_price'] = 'Change the price immediately if you don\'t fill in the effective time';
$_['help_price_display'] = 'Whether price is visible to non-coop buyers.';
$_['help_qty_display'] = 'Whether quantity is visible to non-coop buyers.';
$_['help_effect_time'] = 'Effect time using _time_zone_ timezone.If the price rises, it will take at least 24 hours to take effect.';

$_['entry_upload'] = 'Upload File';
$_['help_upload'] = 'Upload product information .csv file';
$_['text_download_template_file'] = ' Download Template';
$_['text_commodity_statistics'] = 'Download Product List';

$_['text_price_modal_title'] = 'Confirm your price changes';
$_['text_price_modal_yes'] = 'Confirm';
$_['text_price_modal_no'] = 'Refuse';
$_['text_confirm'] = 'Are you sure to change the price to 0?';
$_['text_refine_layer_content'] = 'Do you want to take refined management to the product?';
$_['text_success_product_update'] = 'Update product information successfully.';

// Entry
$_['entry_quantity'] = 'Quantity';

//Alert
$_['alert_set_unavailable'] = 'Do you confirm to set this product unavailable? This product will be removed from the group and the refined management settings will be invalid.';


// Column
$_['column_sku'] = 'Item Code';
$_['column_mpn'] = 'MPN';
$_['column_day_sell'] = '30-Day Sales Volume';
$_['column_all_sell'] = 'Total Sales Volume';
$_['column_sku_mpn'] = 'Item Code / MPN';
$_['column_in_stock_qty'] = 'In stock quantity';
$_['column_in_inventory_stock_qty'] = 'Quantity in [] is locked because this quantity of the product in a margin transaction, future goods transaction, sales orders being processed or inventory adjustment.';
$_['column_next_shipment'] = 'Date of Next Arrival';
$_['column_next_shipment_qty'] = 'Estimated quantity of next arrival';
$_['column_current_price'] = 'Current Price';
$_['column_modified_price'] = 'Modified Price';
$_['column_price_display'] = 'Display Price';
$_['column_quantity_display'] = 'Display Quantity';
$_['column_old_price'] = 'Prev. Dropshipping Price';
$_['column_quantity'] = 'Available Quantity';
$_['column_effect_time'] = 'Date of Effect';
$_['column_status'] = 'Status';
$_['column_freight'] = 'Freight';
$_['column_cur_home_pickup_price'] = 'Curt. Home Pickup Price';
$_['column_mod_home_pickup_price'] = 'Mod. Home Pickup Price';
$_['column_confirmed_home_pickup_price'] = 'Confirmed Home Pickup Price';
$_['column_stock_entries'] = 'Cumulative Number of Entries';
$_['column_stock_exits'] = 'Cumulative Number of Exits';
$_['column_qty_arrival'] = 'Estimated Quantity of Next Arrival';

// Tips
$_['tip_column_freight'] = 'The cost of shipping products through the B2B platform.';
$_['tip_column_curr_price'] = 'Current price excluding fulfillment of this product.';
$_['tip_column_curr_home_pickup_price'] = 'Total cost excluding fulfillment (buyer finds the logistics to pick up the products).';
$_['tip_column_in_stock_qty'] = 'Quantity in [] is locked because this quantity of the product in a margin transaction.';
$_['tip_column_time_limit_stock_qty'] = 'The inventory quantity specified in [] indicates that the products in this quantity are used for the Limited Sales Promotion.';


//Error
$_['error_mpn'] = 'MPN must be between 1 and 64 characters!';
$_['error_sku'] = 'Item Code must be between 1 and 64 characters!';
$_['error_sku_mpn'] = 'Item Code/MPN must be between 1 and 64 characters!';
$_['error_date'] = 'date formatter invalid!';
$_['error_stock_warning'] = 'Available quantity cannot be greater than the difference value between in stock quantity and locked quantity.';
$_['error_stock_warning_lock'] = 'Maximum locked quantity of this combo item sub-SKU is {0}. Available quantity of this combo item should equal to in-stock quantity of combo item minus locked quantity of sub-SKU.';
$_['error_no_record'] = 'No Records!';

$_['error_upload'] = 'File could not be uploaded!';
$_['error_filetype'] = 'Invalid file type!';
$_['error_install'] = 'Upload order file are taking place please wait a few seconds before trying to upload!';
$_['error_file_content'] = 'The content of the uploaded CSV file is incorrect.';

$_['error_product_code_miss'] = 'Updating an item requires specifying at least one of the item\'s Item Code or MPN.';
$_['error_product_duplicate'] = 'Found duplicate items,the duplicate line number is ';
$_['error_date_format'] = 'Date format input is incorrect,please enter it in a format similar to \"2019-01-31 23\".';
$_['error_price_nan'] = 'Price field requires input number.';
$_['error_price_negative'] = 'The value of the price cannot be negative.';
$_['error_price_positive'] = 'The value of the price should be a positive number.';
$_['error_price_jpn'] = 'Failed！ Seller\'s product price in Japan must be a non-negative integer. Please amend it.';
$_['error_price_double'] = 'Seller\'s product price must be an integer or two decimal places.';
$_['error_display_input'] = 'The Display Price field was entered incorrectly.';
$_['error_qty_display'] = 'The Display Quantity field was entered incorrectly.';
$_['error_qty_nan'] = 'Quantity field requires input number.';
$_['error_qty_negative'] = 'The value of the quantity cannot be negative.';
$_['error_qty_positive'] = 'The value of the quantity should be a positive integer.';
$_['error_status_input'] = 'The Status field was entered incorrectly.';
$_['error_no_product'] = 'The MPN and Item Code fields do not match the corresponding product. Please check the input of these two fields. Number of error lines:';
$_['error_stock_short'] = 'Available quantity cannot be greater than the difference value between in stock quantity and locked quantity.';
$_['error_price_increase_fast'] = 'For product(s) price increase, the date of effect must be more than 24 hours ahead of US PST time(%s).';
$_['error_price_new_product_fast_1_text'] = 'If the price of the product rises, it will take more than 24 hours from the last product modification operation.';
$_['error_price_new_product_fast_1_html'] = 'If the price of the product rises, it will take more than 24 hours from the last product modification operation.<br>';
$_['error_price_new_product_fast_2'] = 'The effect date you entered will be corrected to: %s.<br>Can you accept this change?';
$_['error_off_shelf_quantity'] = 'The product status is unavailable and the available quantity cannot be greater than 0.';
$_['error_upload_off_shelf'] = 'Saved successfully!\nNotice：If the product status is unavailable, system sets the available quantity to 0.';
$_['error_product_price_proportion'] = 'The product price you would like to apply is too low. The Marketplace may charge the Seller an increased fulfillment fee based on current industry standards*. Are you sure you wish to apply this price? <br>*To learn more, please visit the Help Center and view the \\\'Usage Fee Standard\\\' terms.';
$_['error_price_check_error'] = "The product price of {0}, you would like to apply is too low. The Marketplace may charge the Seller an increased fulfillment fee based on current industry standards*. Are you sure you wish to apply this price?
<br>*To learn more, please visit the Help Center and view the 'Usage Fee Standard' terms. ";
//和上面的内容区别是中间增加了CWF shipping fee will incur an additional 25% surcharge.，如果修改了主要内容，下面部分也需要修改
$_['error_product_price_proportion_cwf'] = 'The product price you would like to apply is too low. The Marketplace may charge the Seller an increased fulfillment fee based on current industry standards*. CWF shipping fee will incur an additional 25% surcharge. Are you sure you wish to apply this price? <br>*To learn more, please visit the Help Center and view the \\\'Usage Fee Standard\\\' terms.';
$_['error_price_check_error_cwf'] = "The product price of {0}, you would like to apply is too low. The Marketplace may charge the Seller an increased fulfillment fee based on current industry standards*. CWF shipping fee will incur an additional 25% surcharge. Are you sure you wish to apply this price?
<br>*To learn more, please visit the Help Center and view the 'Usage Fee Standard' terms. ";

$_['error_column_duplicate'] = ' Found duplicate items.';
$_['error_column_deposit'] = ' Deposit product can not be edited.';
$_['error_inventory_file_content'] = 'The content of the uploaded file is incorrect.';
$_['error_upload_column_content_air'] = ' is required and cannot be left blank, please check the file and upload again.';
$_['error_upload_column_content'] = '%s %s does not exist,please check the file and upload again.';
$_['success_upload'] = 'Available quantities of products has been uploaded successfully!';
$_['error_save'] = 'Saved Failed.';
$_['success_save'] = 'Saved Successfully.';
