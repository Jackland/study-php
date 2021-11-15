<?php
// Heading
$_['heading_title']            = 'Shopping Cart';
$_['shopping_cart']            = 'Shopping Cart';
$_['purchase_title']            = 'My Purchase List';

// Text
$_['text_success']             = 'Success: You have added <a href="%s" class="default-black-color">%s</a> to your <a href="%s" class="default-black-color">shopping cart</a>!';
$_['text_success_add_cart']    = 'Success: You have added <a href="%s" class="default-black-color">%s</a> to your <a href="%s" class="default-black-color">%s shopping cart</a>!';
$_['text_remove']              = 'Success: You have modified your shopping cart!';
$_['text_login']               = 'Attention: You must <a href="%s">login</a> or <a href="%s">create an account</a> to view prices!';
$_['text_items']               = '%s item(s) - %s';
$_['text_points']              = 'Reward Points: %s';
$_['text_next']                = 'What would you like to do next?';
$_['text_next_choice']         = 'Choose if you have a discount code or reward points you want to use or would like to estimate your delivery cost.';
$_['text_empty']               = 'Your shopping cart is empty!';
$_['text_day']                 = 'day';
$_['text_week']                = 'week';
$_['text_semi_month']          = 'half-month';
$_['text_month']               = 'month';
$_['text_year']                = 'year';
$_['text_trial']               = '%s every %s %s for %s payments then ';
$_['text_recurring']           = '%s every %s %s';
$_['text_payment_cancel']      = 'until canceled';
$_['text_recurring_item']      = 'Recurring Item';
$_['text_payment_recurring']   = 'Payment Profile';
$_['text_trial_description']   = '%s every %d %s(s) for %d payment(s) then';
$_['text_payment_description'] = '%s every %d %s(s) for %d payment(s)';
$_['text_payment_cancel']      = '%s every %d %s(s) until canceled';

// Column
$_['column_image']             = 'Image';
$_['column_name']              = 'Product Name';
$_['column_model']             = 'Store';
$_['column_quantity']          = 'Quantity';
$_['column_price']             = 'Unit Price';
$_['column_total']             = 'Total';
$_['column_sku']             = 'Item Code';
$_['column_mpn']             = 'MPN';
$_['column_seller']             = 'Store';
$_['column_unit_price']             = 'Unit Price After Discount';
$_['column_service_fee']             = 'Service Fee Per Unit After Discount';
$_['column_freight']             = 'Fulfillment Per Unit';
// Error

$_['error_min_cart_value']     = 'You can not proceed to checkout, minimum cart total should be %s!';
$_['error_product_quantity_restriction']     = 'Your product quantity limit(%s) already exceeded for product %s!';

$_['error_stock']              = 'Products marked with *** are not available in the desired quantity or not in stock!';
$_['error_minimum']            = 'Minimum order amount for %s is %s!';
$_['error_required']           = '%s required!';
$_['error_product']            = 'Warning: There are no products in your cart!';
$_['error_recurring_required'] = 'Please select a payment recurring!';
$_['text_message'] = 'The shopping cart contains removed items, please select again.';
$_['error_add_cart'] = 'This product can not add to cart! Please contact with seller to argue.';
$_['error_limit_cart'] = 'You have reached the shopping cart product quantity limit';
$_['error_europe_product_limit'] = 'Failed: Unable to add the additional item to the shopping cart!';
$_['error_unsupport_stock_limit'] = "This Seller’s products cannot be directly purchased for stock up. You must first upload a sales order and pay for the items, then ship the items immediately. ";
$_['error_transaction_add_cart'] = 'This product has other transaction type, it can not add to cart!';
$_['error_expire_time_add_cart'] = 'Agreement id %s is invalid, please select again.';
$_['add_cart_error'] = 'Add cart error.';
$_['error_seller'] = 'Any item in the store cannot be purchased, please contact Seller to process.';
$_['tips_volume'] = 'If the volume of a box is a decimal, it needs to be rounded up to the next whole number using the following unit: cubit feet (ft³). i.e. Volumes between 4.1-4.9 are to be rounded up to 5. To calculate order total volume: The volume of each box * Quantity of boxes.';

$_['tab_dropship'] = 'Drop Shipping';
$_['tab_home_pick'] = 'Pick Up';
$_['tab_cloud_logistics'] = 'Cloud Wholesale Fulfillment';
$_['cart_change_drop_ship'] = 'Add to cloud wholesale fulfillment cart.';
$_['cart_change_cloud_logistics'] = 'Add to drop shipping cart.';
$_['title_cloud_logistics_freight_details'] = 'Cloud Wholesale Fulfillment Freight Details';
$_['length_class'] = 'in';
$_['weight_class'] = 'lb';
$_['volume_class'] = 'ft³';
$_['cloud_logistics_volume_lower'] = CLOUD_LOGISTICS_VOLUME_LOWER;

//CWF 云送仓
$_['text_freight_cloud_wholesale']= 'Cloud Wholesale Fulfillment';
$_['cwf_freight_lear_more_btn']='Learn more >';
$_['weight_detail_tip']='<p>package%s: %.2flbs ×%s</p>';
$_['volume_combo_detail_tip']='<p>package%s: %.2f″ * %.2f″ * %.2f″ ×%s</p>';
$_['estimated_delivery_time_content']='Estimated delivery timeframes of CWF cannot be known in advance, please refer to the ETA in the tracking.';
$_['overweight_surcharge_tip']='For every 1lb/cubic foot exceeding %slb/cubic foot, a %s surcharge will be applied in addition to the base shipping fee';
$_['cwf_shop_fee_desc']='≥'.CLOUD_LOGISTICS_VOLUME_LOWER.'ft³';
$_['volume_tip']='Product volume needs to be rounded up to the next whole number';
$_['cwf_shop_fee_tip']='The minimum volume requirement per shipping address';
$_['volume_detail_tip']='%.2f″ * %.2f″ * %.2f″';
$_['base_freight_label']='Base Shipping Fee';
$_['overweight_surcharge_label']='Overweight Surcharge';
$_['package_fee_label']='Packing Fee';
$_['weight_class']='lbs';
$_['weight_tip']='Product Weight';

$_['error_futures_transaction_add_cart_exist'] = 'This item has already been in the shopping cart. Please access your cart to pay the due amount of the item.';
$_['error_transaction_add_cart_exist'] = 'Items quantity from Agreement successfully added to cart, click your cart to view. Maximum Agreement purchase quantity for items has been reached. ';
$_['error_not_found_cart'] = 'Item Code %s is no longer available, please refresh the page.';



