<?php
//title
$_['heading_seller_center'] = 'Seller Central';
$_['heading_title'] = 'Margin Offerings';
$_['heading_parent_title'] = 'Product Bidding';
$_['heading_title_template'] = 'Margin Template Setting';
$_['heading_title_add'] = 'Create Margin Offering';
$_['heading_title_edit'] = 'Edit Margin Offerings';
$_['heading_title_offerings'] = 'Margin Offerings';


// button
$_['button_create_title'] = 'Create New Margin Offerings';
$_['button_create'] = 'Create a Margin Offerings';
$_['button_add'] = 'Add margin offering';
$_['button_edit'] = 'Edit';
$_['button_save'] = 'Save';
$_['button_delete'] = 'Delete';
$_['button_all_delete'] = 'Delete all selected margin offerings';
$_['button_go_back'] = 'Back';
$_['button_question'] = 'Click here to view our margin policy.';

// Column
//table
$_['column_sequence_number'] = 'No.';
$_['column_template_id'] = 'Margin Offering ID';
$_['column_item_code'] = 'Item Code';
$_['column_mpn'] = 'MPN';
$_['column_day'] = 'Days to Purchase Order';
$_['column_num'] = 'Selling Quantity';
$_['column_price_special'] = 'Custom Price';   //(Unit)
$_['column_price_current']='Current Unit Price';
$_['column_price_current_tips']='The current listed price of product ';
$_['column_payment_ratio'] = 'Margin Rate';
$_['column_amount'] = 'Margin Amount';

$_['column_is_default'] = 'Is Default';
$_['column_modified_time'] = 'Last Modified';
$_['column_action'] = 'Actions';

$_['error_delete_tip'] = 'Delete this margin offering is not allowed！Please check if there are other margin offerings.';
$_['delete_single_0'] = 'Delete all margin offerings for this product.';
$_['delete_single_1'] = 'Delete this margin offering.';
$_['delete_multiple_tip'] = 'All margin offerings that involve these products are deleted.';


//form
$_['column_form_mpn'] = 'MPN';
$_['column_form_item_code'] = 'Item Code';
$_['column_form_margin_template'] = 'Margin Offering';
$_['column_form_margin_rate']='Margin Rate';
$_['column_form_original_price'] = 'Curt. Dropshipping Price';
$_['column_form_available_quantity'] = 'Available Quantity';
$_['column_form_min_num'] = 'Minimum Selling Quantity';
$_['column_form_max_num'] = 'Maximum Selling Quantity';
$_['column_form_price'] = 'Exclusive Price(%s/Unit)';
$_['column_form_day'] = 'Days to Purchase Order';
$_['column_form_earnest_money']='Margin';
$_['column_form_earnest_money_tips']='This price does not include fulfillment fee.';
$_['column_form_tail_money_per']='Final Payment Unit Price';
$_['column_form_tail_money_per_tips']='This price does not include fulfillment fee.';
$_['column_form_agreement_amount']='Agreement Amount';
$_['column_form_agreement_amount_tips']='This price does not include fulfillment fee.';
$_['column_form_payment_ratio'] = 'Margin Rate';
$_['column_form_amount'] = 'Amount of Margin';
$_['column_form_is_default'] = 'Default';
$_['column_form_mpn_sku'] = 'MPN/Item Code';
$_['column_form_freight'] = 'Curt. Freight';
$_['column_form_current_price'] = 'Current Unit Price';
$_['column_form_ex_price'] = 'Custom Price<br>(%s/Unit)';//'Exclusive Price(%s/Unit)';
$_['column_form_margin_amount'] = 'Margin Amount';
$_['table_earnest_money']='Margin';
$_['table_earnest_money_tips']='This price does not include fulfillment fee.';
$_['table_tail_money_per']='Final Payment Unit Price';
$_['table_tail_money_per_tips']='This price does not include fulfillment fee.';
$_['table_agreement_amount']='Agreement amount';
$_['table_agreement_amount_tips']='This price does not include fulfillment fee.';
$_['comfirm_delete_tip']='Are you sure you want to delete the margin offering of this item code?';
$_['confirm_delete_multiple_tip']='Select ‘OK’ to confirm you would like to delete the margin offerings that correlate with the selected Item Code(s).';
$_['del_success']='Deleted successfully';
$_['del_failed']='This action could not be completed';
//text
$_['text_sku_mpn'] = 'Search Item Code / MPN';

// Error
$_['error_select_one'] = 'Please select Item Code(s) to delete their correlating margin offering(s).';
$_['error_invalid_request'] = 'Request parameters are invalid.';
$_['error_no_product'] = 'Cannot find the matched item by this MPN or Item Code or, this item is not available, has been discarded or cannot be sold separately.';
$_['error_sold_day'] = 'This field should be positive integer.';
$_['error_sold_qty'] = 'The quantity field must be filled in with a positive integer and needs to be greater than 70% of the available quantity';
$_['error_exclusive_price'] = 'This field should be a positive real number that holds two decimals';
$_['error_discount'] = 'This field should be a positive integer not greater than 100.';
$_['error_limit_price'] = 'This field should be a four-digit positive integer.';
$_['error_limit_qty'] = 'This field should be a positive integer not greater than the value of sales quantity field.';

$_['error_min_num'] = 'The minimum quantity sold shall not be less than 5.';
$_['error_max_num'] = 'The maximum quantity to be sold must not exceed available quantity.';
$_['error_min_max'] = 'The maximum quantity cannot be less than the minimum quantity.';
//$_['error_day'] = 'This field should be a positive integer.';
$_['error_price'] = 'The exclusive price shall not be more than the original price.';
$_['error_price_lt_0'] = 'Custom Price is required. Please enter a number equal or greater than 0 and equal or less than 120% of Current Unit Price.';
$_['error_day']='Days to Purchase Order is required. Please enter an integer number greater than 0 and equal or less than 120.';
$_['error_price_japan'] = 'This field should be a positive integer.';
$_['error_form_submit'] = 'You have entered an invalid value. Please try again.';

$_['save_success']='Saved Successfully.';
$_['save_failed']='Failed to Save.';

//product modal
$_['margin_title'] = 'Place Bid';
$_['margin_submit_bid'] = 'Submit Margin Bid';
$_['margin_seller_template'] = '';
$_['margin_day'] = 'Days to Purchase Order';
$_['margin_num'] = 'Selling Quantity';
$_['margin_price'] = 'Exclusive Price(%s/Unit)';
$_['margin_payment_ratio'] = 'Margin Rate';
$_['margin_amount'] = 'Amount of Margin';
$_['margin_total_product_value'] = 'Margin Total Product Value';

$_['margin_bid_day'] = 'Agreement Days';
$_['margin_bid_num'] = 'Agreement Quantity';
$_['margin_bid_price'] = 'Agreement Unit Price';
$_['margin_bid_payment_ratio'] = 'Margin Rate';
$_['margin_bid_amount'] = 'Amount of Margin';
$_['margin_bid_ex_price']  = 'Custom Price(%s/Unit)';
$_['margin_bid_margin_amount'] = 'Margin Amount';

$_['margin_bid_message'] = 'Comments';
$_['margin_bid_message_placeholder'] = '1~2000 characters';
$_['margin_bid_cancel'] = 'Cancel';
$_['margin_bid_place_bid'] = 'Submit Bid';

$_['margin_bid_agree_text'] = 'I have read and agree to the terms and conditions of this agreement.';

$_['error_margin_bid_day'] = 'Days should be a positive integer.';
$_['error_margin_bid_qty'] = 'The Ordered Quantity shall not exceed <br>the available quantity and not less than 5.';
$_['error_margin_bid_price'] = 'Exclusive Price only allows to have maximum two decimal places.';
$_['error_margin_bid_price_japan'] = 'Exclusive Price shall be a positive integer.';
$_['error_margin_bid_price_too_few'] = 'Bid Price is required. Please enter a number equal or greater than 0 and equal or less than 120% of Current Unit Price.';

$_['error_margin_bid_qty_limit'] = 'Number of Units in Agreed Minimum Selling Price should be a natural number no greater than Minimum Selling Quantity.';
$_['error_margin_bid_message'] = 'Message can not be more than 2000 characters.';
$_['error_margin_agree_clause'] = 'Terms must be checked';

$_['error_margin_exist'] = 'You are currently in the rebate campaign of this product. Please join again after the current one is completed.';

$_['error_product_id'] = 'Product ID cannot be empty';
$_['error_payment_ratio'] = 'Payment ratio should be a positive number not greater than 100.';

$_['text_day_tip'] = 'The days of agreement more than 60 days. Are you sure you want to submit this agreement?';

$_['text_current_price_tip'] = 'Current price excluding fulfillment of this product.';
//$_['text_ex_price_tip'] = 'Exclusive price excluding fulfillment during the period of margin agreement.';
$_['text_ex_price_tip'] = 'The unit price of the product within margin offering.';
$_['text_margin_amount_tip'] = 'Initial Margin: 20% of Exclusive Price * Selling Quantity.';
$_['text_margin_amount_tip_bid'] = 'Initial Margin: 20% of Exclusive Price * Quantity of Agreement.';
$_['error_check_clause'] = 'Please tick the box at bottom to agree with the margin agreement.';

$_['column_margin_template_front_money']  = 'Margin';//现货保证模板金定金金额
$_['column_margin_template_tail_price']  = 'Final Payment Unit Price';//现货保证金模板尾款单价
$_['column_margin_template_agreement_money']  = 'Template Amount';//现货保证金模板协议金额
$_['column_margin_front_money'] = 'Agreement Margin';//现货保证金定金金额
$_['column_margin_tail_price'] = 'Agreement Final Payment Unit Price';//现货保证金尾款单价
$_['column_margin_agreement_money'] = 'Agreement Amount';//现货保证金协议金额


$_['margin_ratio_tip'] = 'The percentage of margin of exclusive price.';
$_['tip_margin_rate']  = 'The rate of margin in margin agreement';
$_['tip_margin_template_front_money']  = 'This price does not include fulfillment fee.';
$_['tip_margin_template_tail_price']  = 'This price does not include fulfillment fee.';
$_['tip_margin_template_agreement_money']  = 'This price does not include fulfillment fee.';

$_['tip_margin_front_money'] = 'This price does not include fulfillment fee.';
$_['tip_margin_tail_price'] = 'This price does not include fulfillment fee.';
$_['tip_margin_agreement_money'] = 'This price does not include fulfillment fee.';


//折叠展开
$_['btn_expand']='Expand';
$_['btn_collapse']='Minimize';
$_['no_records']='You currently do not have any margin offers. Click ‘CREATE a MARGIN OFFERINGS’ to create one.';


//产品信息
$_['product_info']='General Product Information';
$_['margin_tpl']='Margin Offerings';


//后台校验错误
$_['notice_no_tpl']='No records, please check and fill in the offering again.';
$_['notice_productid_error']='No records, please check and fill in the Item Code again.';
$_['notice_qty_change']='Available Quantity has changed, please check an fill in the offering again.';
//$_['notice_price_change']='Current Unit Price has changed, please check and fill in the offering again';
$_['notice_price_change']='Custom Price is required. Please enter a number equal or greater than 0 and equal or less than 120% of Current Unit Price.';
