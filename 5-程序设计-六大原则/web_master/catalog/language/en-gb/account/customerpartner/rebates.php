<?php
//title
$_['heading_seller_center'] = 'Seller Central';
$_['heading_title'] = 'Rebate Offerings';
$_['heading_parent_title'] = 'Complex Transaction Management';
$_['heading_title_template'] = 'Rebate Offerings';
$_['heading_title_add'] = 'New Rebate Template';
$_['heading_title_edit'] = 'Modify Rebate Template';
$_['heading_title_copy'] = 'Copy Rebate Template';

//NEW  第四版返点  new rebate template
$_['new_head_title_tip']='The product will be removed from rebate template automatically when the product is not available/discarded or cannot be sold separately.';
$_['new_day']=array(
    'day'=>'DAYS',
    'required'=>'REQUIRED'
);
$_['new_min_quantity']=array(
    'min_quantity'=> 'MIN. TOTAL SELLING QUANTITY',
    'tips'=>'Minimum total selling quantity of all products involved in this rebate template. ',
    'total_inv'=>'Curt. total available inventory:',
    'error'=>'MIN. TOTAL SELLING QUANTITY should be a positive integer no greater than 80% of current total available inventory of all products involved in this rebate template.',
    'required'=>'REQUIRED'
);
$_['new_rebate']=array(
    'rebate'=>'CHOOSE YOUR REBATE',
    'rebate_tip'=>'Please select a rebate method from the following two methods.',
    'rebate_type_1'=>'Percentage(%UNIT)',
    'rabate_type_1_tip'=>'Each product gets a rebate by a certain percentage.',
    'rebate_type_2'=>'AMOUNT(%s/UNIT)',
    'rabate_type_2_tip'=>'Each product get a rebate by a certain amount.',
    'required'=>'REQUIRED'
);
$_['new_product']=array(
    'product'=>'PRODUCTS',
    'product_placeholder'=>'Item Code / MPN',
    'product_tip'=>'Products from a same product model in different colors can be involved in a same rebate template.',
    'error' => 'Cannot find the matched item, please input the correct Item Code or MPN, or the item may be unavailable, invalid or cannot be sold separately.',
);
$_['place_limit'] = array(
    'title' => 'AVAILABLE PLACE SETTING',
    'tip_title' => 'Set how many buyers could request the bid.',
    'no_limit' => 'No limit',
    'tip_no_limit' => 'No limit on how many bids could be approved.',
    'available_place' => 'Available Place',
    'tip_available_place' => 'Set the maximum number of places, the value should be a natural number greater than or equal to 0.',
    'left_tip' => 'places have been used',
    'required' => 'REQUIRED',
    'error'=>'Available place should be a natural number greater than or equal to 0.'
);
$_['read_agreement']='I\'ve read and agree to %s the terms of this agreement.';

//button
$_['button_save']='SAVE';
$_['button_save_apply']='SAVE & APPLY';
$_['button_save_apply_tips']='Save the template and apply to create a new template form.';
$_['button_cancel']='CANCEL';

//search table
$_['table_column_0']='';    //check_box
$_['table_column_1']='Image';
$_['table_column_2']='Item Code(MPN)';
$_['table_column_3']='Attribute';
$_['table_column_4']='Curt. Avaliable<br>Inventory';
$_['table_column_5']='Current Price';
$_['table_column_6']='Exclusive Price';
$_['table_column_6_tip']='Exclusive price excluding fulfillment during the period of rebate agreement. Only products purchase at this price by the buyer can be counted into the rebate agreement.';
$_['table_column_7']='Rebate Amount';
$_['table_column_7_tip']='The amount of rebate after the settlement day if the buyer satisfied the quantity and Agreed Minimum Selling Price during the period of agreement.';
$_['table_column_8']='Agreed Minimum<br> Selling Price';
$_['table_column_8_tip']='The final price sold on external platforms cannot be lower than this price.';

// rebate template list
$_['list_create_template']='CREATE REBATE TEMPLATE';
$_['list_download']='DOWNLOAD';
$_['list_table']=array(
    'column_1'=>'Template ID',
    'column_2'=>'Item Code(MPN)',
    'column_3'=>'Days',
    'column_4'=>'Min.Total Selling Quantity',
    'column_5'=>'Exclusive Price',
    'column_6'=>'Rebate/Unit',
    'column_7'=>'Used/Total Places',
    'column_8'=>'Last Modified',
    'column_9'=>'Action',
);


//buyer  place bid
$_['modal_title']='Rebate Plans Invoiving';
$_['head_tip']='Certain item codes from a same product model are all qualified for the rebates plan';
$_['bid_button_bid_plan']='BID ON THIS PLAN';
$_['bid_button_place_bid']='PLACE BID ON THIS PLAN';
$_['bid_button_cancel']='CANCEL';
$_['bid_modal_table']=array(
    'day'=>'Days',
    'qty'=>'Min. Total Selling Quantity',
    'qty_tip'=>'Minimum total selling quantity of all valid products involved in this rebate template.',
    'image'=>'Image',
    'item_code'=>'Item Code',
    'cur_qty'=>'Curt. QTY Available',
    'min_bid_tip'=>'The curt. QTY available of valid products involved in this plan is %s. %s of them has been bid by other buyers. You can apply for maximum 80%% of %s.',
    'exclusive_price'=>'Exclusive Price(%s/Unit)',
    'exclusive_price_tip'=>'The item price excluding fulfillment during the period of rebate agreement.',
    'rebate_amount'=>'Rebate Amount(%s/Unit)',
    'rebate_amount_tip'=>'The amount of rebate after the settlement day if the buyer satisfied the quantity and Agreed Minimum Selling Price during the period of agreement.',
    'price_after_rebate'=>'Price after Rebate(%s/Unit)',
    'price_after_rebate_tip'=>'The item price excluding fulfillment deducted by the rebates.',
    'agree_min_price'=>'Agreed Minimum Selling Price',
    'agree_min_price_tip'=>'The final price sold on external platforms cannot be lower than this price.'
);

$_['bid_table_error_tip']=array(
    'tip_1'=>'You have participated in the rebate campaign of this product %s times and cannot participate again.',
    'tip_2'=>'You are in the rebate campaign (agreement id: %s) of this product and cannot participate again.'
);

$_['tpl_save_success']='saved successfully';






// button
$_['button_add'] = 'Add rebates template';
$_['button_edit'] = 'Edit';
$_['button_save'] = 'Save';
$_['button_delete'] = 'Delete this rebates template';
$_['button_all_delete'] = 'Delete all selected rebates templates';
$_['button_go_back'] = 'Back';

// Column
//table
$_['column_sequence_number'] = 'No.';
$_['column_template_id'] = 'Template ID';
$_['column_item_code'] = 'Item Code';
$_['column_mpn'] = 'MPN';
$_['column_current_freight'] = 'Curt. Freight';
$_['column_day'] = 'Days';
$_['column_qty'] = 'Minimum Selling Quantity';
$_['column_original_price'] = 'Original Price(Unit)';
$_['column_price_special'] = 'Exclusive Price(Unit)';
$_['column_discount'] = 'Rebate';
$_['column_discount_unit_price'] = 'Unit Price after Rebate';
$_['column_price_limit'] = 'Agreed Minimum Selling Price(Unit)';
$_['column_qty_limit'] = 'Number of Units in Agreed Minimum Selling Price';
$_['column_modify_date'] = 'Last Modified';
$_['column_action'] = 'Action';

$_['column_price_ds'] = 'Ref.Exclusive Dropshipping Price(Unit)';
$_['column_price'] = 'Exclusive Price(Unit)';
$_['column_ref_price_hp'] = 'Ref.Exclusive Home Pickup Price(Unit)';
$_['column_real_price_ds'] = 'Dropshipping Price after Rebate(Unit)';
$_['column_real_price_hp'] = 'Home Pickup Price after Rebate(Unit)';

//form
$_['column_form_mpn_sku'] = 'Item Code / MPN';
$_['column_form_mpn'] = 'MPN';
$_['column_form_item_code'] = 'Item Code';
$_['column_form_day'] = 'Days';
$_['column_form_qty'] = 'Minimum Selling Quantity';
$_['column_form_qty_available'] = 'Current available inventory:';
$_['column_form_exclusive_price'] = 'Exclusive Price';
$_['column_form_exclusive_ds_price'] = 'Ref.Exclusive Dropshipping Price';
$_['column_form_exclusive_hp_price'] = 'Exclusive Home Pickup Price';
$_['column_form_ref_exclusive_hp_price'] = 'Ref.Exclusive Home Pickup Price';
$_['column_form_default_radio'] = 'Original Price:';
$_['column_form_customize_radio'] = 'Custom Price(%s):';
$_['column_form_discount'] = 'Rebate';
$_['column_form_discount_amount'] = 'Rebate Amount(%s/Unit):';
$_['column_form_price_limit'] = 'The Percentage of Agreed Minimum Selling Price to Exclusive Dropshipping  Price';
$_['column_form_price_limit_amount'] = 'Agreed Minimum Selling Price(%s/Unit):';
$_['column_form_qty_limit'] = 'Number of Units in Agreed Minimum Selling Price';
$_['column_form_current_price'] = 'Current Price(%s/Unit):';
$_['column_form_current_freight'] = 'Current Freight(%s/Unit):';
$_['column_form_real_price_ds'] = 'Ref.Dropshipping Price after Rebate(%s/Unit):';
$_['column_form_real_price'] = 'Price after Rebate(%s/Unit):';
$_['column_form_currency_unit'] = '(%s/Unit):';

//text
$_['text_sku_mpn'] = 'Search Item Code/MPN';
$_['text_confirm'] = 'Do you confirm to delete this rebate template of this item[%s]? Buyers will not see the price information on this template.';
$_['text_confirm_all'] = 'Do you confirm to delete these %s rebate template? Buyers will not see the price information on this template.';
$_['text_freight_tip'] = 'The cost of shipping products through the B2B platform.';
$_['text_price_ds_tip'] = 'Reference exclusive price including<br> fulfillment during the period of rebate<br> agreement..This price may vary <br>due to the changing freight.';
$_['text_price_hp_tip'] = 'Exclusive price excluding fulfillment<br>during the period of rebate <br>agreement.';

// Error
$_['error_select_one'] = 'Please Select at least one template.';
$_['error_invalid_request'] = 'Request parameters are invalid.';
$_['error_no_product'] = 'Item associated with the input MPN/Item Code not found. This item may no longer be available, has been discarded or cannot be sold separately.';
$_['error_template_too_much'] = 'The number of templates of this item[%s] should be no greater than three.';
$_['error_sold_day'] = 'Days must be typed in numerical format and greater than 0. ';
$_['error_sold_qty'] = 'Minimum Selling Quantity must be typed in numerical format above 0 and no greater than 80% of available inventory.';
$_['error_exclusive_price'] = 'Exclusive Price only allows to have maximum two decimal places.';
$_['error_exclusive_price_jp'] = 'Exclusive Price should be positive integer.';
$_['error_discount'] = 'Rebate Rate must be a positive integer that no greater than 100.';
$_['error_limit_price'] = 'Agreed Minimum Selling Price allows a number with maximum two decimal numbers which should be equal to or greater than 0. ';
$_['error_limit_price_japan'] = 'Agreed Minimum Selling Price should be a positive integer or 0.';
$_['error_limit_qty'] = 'Number of Units in Agreed Minimum Selling Price should be a natural number no greater than Minimum Selling Quantity.';


//返点4期
$_['error_sold_qty_new'] = 'MIN. TOTAL SELLING QUANTITY should be a positive integer no greater than 80% of current total available inventory of all products involved in this rebate template.';
$_['error_select_rebate']='Please select a rebate method from the following two methods. 1. Each product gets a rebate by a certain rate. 2. Each product get a rebate by a certain amount.';
$_['error_rabate_value_rate']='Rebate Rate should round off up to 2 decimal place and not be greater than 100.';
$_['error_rabate_value_amount']='Rebate Amount allows a number with maximum two decimal numbers which should be equal to or greater than 0.';
$_['error_select_product']='Please select at least one product';
$_['error_cannt_find_product'] = 'Cannot find the matched item, please input the correct Item Code or MPN, or the item may be unavailable, invalid or cannot be sold separately.';
$_['error_new_exclusive']='Exclusive Price allows a number with maximum two decimal numbers which should be equal to or greater than 0';
$_['error_new_rebate']='Rebate Amount allows a number with maximum two decimal numbers which should be equal to or greater than 0, and equal to or less than Exclusive Price.';
$_['error_new_limit_qty']='Agreed Minimum Selling Price allows a number with maximum two decimal numbers which should be equal to or greater than 0. ';
$_['db_error']='Submitting failed. Please refresh the page and try again.';
$_['error_tpl_three']='The number of templates of this item[%s] should be no greater than three.';
$_['error_place_limit'] = 'Available place should be a natural number greater than or equal to 0.';
$_['error_0_unused_num'] = 'No places for this bid plan, it cannot be bid. Please bid other plans.';
$_['error_0_unused_num_seller'] = 'No places for this bid plan, you cannot approve the rebate agreement from this buyer.';

//product modal
$_['rebates_title'] = 'Place Bid';
$_['rebates_seller_template'] = 'Rebates Terms';
$_['rebates_day'] = 'Days';
$_['rebates_qty'] = 'Minimum Selling Quantity';
$_['rebates_price'] = 'Exclusive Price(Unit)';
$_['rebates_discount'] = 'Rebate Amount(Unit)';
$_['rebates_price_unit'] = 'Price after Rebate (Unit)';
$_['rebates_price_limit'] = 'Agreed Minimum Selling Price';
$_['rebates_qty_limit'] = 'Number of Units in Agreed Minimum Selling Price';
$_['rebates_original_term'] = 'Bid on the original terms or propose your own';

$_['rebates_bid_day'] = 'Days';
$_['rebates_bid_qty'] = 'Minimum Selling Quantity';
$_['rebates_bid_price'] = 'Exclusive Price(Unit)';
$_['rebates_bid_discount'] = 'Rebate Rate(%/Unit)';
$_['rebates_bid_discount_amount'] = 'Rebate Amount(%s/Unit)';
$_['rebates_bid_discount_price'] = 'Price after Rebate (Unit)';
$_['rebates_bid_price_limit'] = 'Agreed Minimum Selling Price(%s/Unit)';
$_['rebates_bid_qty_limit'] = 'Number of Units in Agreed Minimum Selling Price';
$_['rebates_bid_qty_max'] = 'You can bid a max of %s items';
$_['rebates_bid_about'] = 'About Rebates';

$_['rebates_bid_message'] = 'Remark';
$_['rebates_bid_message_placeholder'] = '1-2000 characters';
$_['rebates_bid_cancel'] = 'Cancel';
$_['rebates_bid_place_bid'] = 'Place Bid';

$_['rebates_bid_agree_text'] = 'I\'ve read and agree to %s the terms of this agreement.';

$_['error_rebates_bid_day'] = 'Days should be a positive integer.';
$_['error_rebates_bid_qty'] = 'Minimum Selling Quantity should be a positive integer no greater than 80% of available inventory.';
$_['error_rebates_bid_price'] = 'Exclusive Price only allows to have maximum two decimal places.';
$_['error_rebates_bid_price_jp'] = 'Exclusive Price should be a positive integer.';
$_['error_rebates_discount_amount'] = 'Rebate Amount allows a number with maximum two decimal numbers which should be equal to or greater than 0, and equal to or less than %s.';
$_['error_rebates_bid_discount'] = 'Rebate Rate must be a positive integer that no greater than 100.';
$_['error_rebates_bid_price_limit'] = 'Agreed Minimum Selling Price only allows to have maximum two decimal places.';
$_['error_rebates_bid_price_limit_jp'] = 'Agreed Minimum Selling Price should be a positive integer.';
$_['error_rebates_bid_qty_limit'] = 'Number of Units in Agreed Minimum Selling Price should be a natural number no greater than Minimum Selling Quantity.';
$_['error_rebates_bid_message'] = 'Message can not be more than 2000 characters.';
$_['error_rebates_agree_clause'] = 'Terms must be checked';


$_['error_rebates_exist'] = 'You are currently in the rebate campaign of this product. Please join again after the current one is completed.';
$_['error_rate'] = 'Rebate Rate should round off up to 2 decimal place and not be greater than 100.';
$_['error_amount'] = 'Rebate Amount allows a number with maximum two decimal numbers which should be equal to or greater than 0, and equal to or less than Exclusive Price.';
$_['error_product_price_proportion'] = 'The product price you would like to apply is too low. The Marketplace may charge the Seller an increased fulfillment fee based on current industry standards*. Are you sure you wish to apply this price? <br>*To learn more, please visit the Help Center and view the \\\'Usage Fee Standard\\\' terms.';
//和上面的内容区别是中间增加了CWF shipping fee will incur an additional 25% surcharge.，如果修改了主要内容，下面部分也需要修改
$_['error_product_price_proportion_cwf'] = 'The product price you would like to apply is too low. The Marketplace may charge the Seller an increased fulfillment fee based on current industry standards*. CWF shipping fee will incur an additional 25% surcharge. Are you sure you wish to apply this price? <br>*To learn more, please visit the Help Center and view the \\\'Usage Fee Standard\\\' terms.';

//返点4期
$_['error_remark']='Remark must be between 1 and 2000 characters';


$_['tip_item_code_mpn'] = 'Only products that are available <br>and can be sold separately are qualified for rebates.';
$_['tip_day']  = 'Rebate agreement term.';
$_['tip_min_selling_qty'] = 'The minimum quantity the buyer <br> should buy during the period of<br> agreement.';
$_['tip_rebate'] = 'Please select a rebate method from rebate by rate and rebate by amount.';
$_['tip_rate'] = 'The percentage of rebate of <br> exclusive price excluding fulfillment after the settlement day if the <br> buyer satisfied the quantity and<br> Agreed Minimum Selling Price<br> during the period of agreement.';
$_['tip_amount'] = 'The amount of rebate after the<br> settlement day if the buyer satisfied<br> the quantity and Agreed Minimum<br> Selling Price during the period of<br> agreement.';
$_['tip_price_limit'] = 'The final price the buyer sell this<br> product on external platforms<br> cannot be lower than this price.';
$_['tip_current_price'] = 'The current price excluding fulfillment of this product.';
$_['tip_exclusive'] = 'Exclusive price excluding fulfillment <br> during the period of rebate <br> agreement.';

$_['tip_bid_day'] = 'Rebate agreement term.';
$_['tip_bid_min_qty'] = 'The minimum quantity the buyer should buy during the period of agreement.';
$_['tip_bid_exclusive_price'] = 'The item price excluding fulfillment during the period of rebate agreement.';
$_['tip_bid_rebate_amount'] = 'The amount of rebate after the settlement day if the buyer satisfied the quantity and Agreed Minimum Selling Price during the period of agreement.';
$_['tip_bid_rebate_price'] = 'The item price excluding fulfillment deducted by the rebates.';
$_['tip_bid_limit_price'] = 'The final price sold on external platforms cannot be lower than this price.';
$_['tip_bid_max_qty'] = 'The current available quantity of this product is %s. You can apply for maximum 80%% of them.';


//返点4期
$_['expand_tips']='Expand/Collapse';
$_['product_add_relation']='This series of product has set rebate template and you have added new products of the same model. If you want to include them to the rebate template, please modify the rebate template';
$_['product_del_relation']='This series of product has set %s rebate template and you have removed %s products from the same model. If you want to remove them from the rebate template, please modify the rebate template. However, it will not affect the active agreements.';
$_['product_add_del_relation']='This series of product has set %s rebate template. You have added new products of the same model. If you want to include them to the rebate template, please modify the rebate template. Meanwhile, you have removed %s products from the same model. If you want to remove them from the rebate template, please modify the rebate template. However, it will not affect the active agreements.';
$_['rebate_del_product']='The product will be removed from rebate template automatically when the product is not available/discarded or cannot be sold separately.';

$_['product_remove_relation']='You have removed products from the same model. If you want to remove them from the rebate template, please modify the rebate template.However, it will not affect the active agreements.';
$_['product_add_and_remove_relation']='This series of product has set rebate template. You have added new products of the same model. If you want to include them to the rebate template, please modify the rebate template. Meanwhile, you have removed product from the same model. If you want to remove them from the rebate template, please modify the rebate template. However, it will not affect the active agreements.';

$_['rebate_edit_product_sold_partly_yes_to_no_rebate']='You have set this product can not be sold separately, if you want to remove them from the rebate template, please modify the rebate template.However, it will not affect the active agreements.';
$_['rebate_edit_product_sold_partly_yes_to_no_exclusive']='You have set this product can not be sold separately, if you want to remove them from the rebate exclusive price, please modify the exclusive price.However, it will not affect the active agreements.';
$_['rebate_edit_product_sold_partly_yes_to_no_both']='You have set this product can not be sold separately, if you want to remove them from the rebate template(exclusive price), please modify the rebate template(exclusive price). However, it will not affect the active agreements.';

//页面错误, 重新刷新页面
$_['page_error']='Submitting failed. Please refresh the page and try again.';

$_['rebate_or_exclusive_price_notice_for_product_unavailable'] = 'You have set this product as unavailable, if you want to remove them from the rebate template(exclusive price), please modify the rebate template(exclusive price). However, it will not affect the active agreements.';
$_['exclusive_price_notice_for_product_unavailable'] = 'You have set this product as unavailable, if you want to remove them from the exclusive price, please modify the exclusive price. However, it will not affect the active agreements.';
$_['rebate_notice_for_product_unavailable'] = 'You have set this product as unavailable, if you want to remove them from the rebate template, please modify the rebate template. However, it will not affect the active agreements.';


