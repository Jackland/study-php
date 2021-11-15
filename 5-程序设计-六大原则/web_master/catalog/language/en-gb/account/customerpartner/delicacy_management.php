<?php
// Heading
$_['heading_title'] = "Exclusive Price";
$_['text_seller_center'] = 'Seller Central';
$_['heading_parent_title'] = 'Custom Management';
$_['buyer_list_title'] = "Refined Management to";
$_['delicacy_management_list'] = "Refined Management List";

// Title
$_['title_table_header'] = 'The following products are under refined management.';

// Column
$_['input_select_product'] = 'Item Code or MPN';
$_['input_select_buyer'] = 'Buyer Name';
$_['column_item_code'] = 'Item Code';
$_['column_MPM'] = 'MPN';
$_['column_product_name'] = 'Product Name';
$_['column_in_stock_quantity'] = 'Instock Quantity';
$_['column_on_shelf_quantity'] = 'Onshelf Quantity';
$_['column_current_basic_price'] = 'Current Basic Price';
$_['column_modified_price'] = 'Modified Price';
$_['column_effective_time'] = 'time of effect';

$_['column_buyer_nickname'] = 'Buyer Name';
$_['column_discount'] = 'Discount';
$_['column_remark'] = 'Remark';
$_['column_add_time'] = 'Added Time';


$_['column_base_price'] = 'Base Price';
$_['column_modified_base_price'] = 'Modified Base Price';
$_['column_expiration_time'] = 'Time of Failure';
$_['column_product_display'] = 'Product Display';
$_['column_action'] = 'Action';


// button
$_['btn_add'] = 'Add';
$_['btn_remove'] = 'Remove';


// Text
$_['text_sellers'] = 'Sellers';
$_['text_buyers'] = 'Buyers';
$_['text_add_sellers'] = 'Add Sellers';
$_['text_add_buyers'] = 'Add Buyers';
$_['text_other_buyers'] = 'Other Buyers';
$_['text_other_sellers'] = 'Other Sellers';
$_['text_buyer_email'] = 'Buyer Email';
$_['text_no_buyer_assign'] = 'There are no other buyer availables to assign to the seller';
$_['text_no_seller_assign'] = 'There are no other seller availables to assign to the buyer';
$_['text_add_seller_or_buyer'] = 'Add other sellers or buyers to this customer.';
$_['text_delete_data_tip'] = 'Do you confirm to delete the data?';
$_['text_set_discount'] = 'Set Discount';
$_['text_set_enable'] = 'Establish Cooperation';
$_['text_set_disable'] = 'Terminate Cooperation';
$_['text_set_discount_layer_title'] = 'Modify Discount';
$_['text_set_discount_layer_content'] = 'Terminate Cooperation';
$_['text_refine_layer_content'] = 'Do you want to take refined management to the buyer?';

$_['text_select_window_message'] = 'Batch operation can only set the products to be invisible to the buyer, if you want to set the exclusive price, you can only click the Add button to set the exclusive price for a single buyer.';

$_['text_invisible'] = 'Invisible';
$_['text_visible'] = 'Visible';
$_['text_24_price_protect'] = 'The item price has been raised, please set the time of effect after 24 hours from the current _current_country_ time ( <b>#</b> ).';

$_['text_batch_set_price_content_single'] = 'For _buyer_ :';
$_['text_batch_set_price_content'] = 'For _buyer_ and _buyer_num_ other selected buyers:';

// title tip
$_['tip_add_product'] = 'Add product(s) to the refined management list';
$_['tip_batch_remove_product'] = 'Remove product(s) from the refined management list.';
$_['tip_set_price'] = 'Set exclusive price';

$_['tip_add_buyer'] = 'Add buyer(s) to the refined management list.';
$_['tip_batch_remove_buyer'] = 'Remove buyer(s) from the refined management list.';

$_['tip_table_buyer_price'] = 'The price displayed to the buyer within the validity period=exclusive price x Discount.';
$_['tip_table_basic_price'] = 'The default price displayed to the buyer = Basic price x Discount.';
$_['tip_update_time'] = '_current_country_ Time';


$_['tip_edit_product'] = 'Edit.';
$_['tip_remove_product'] = 'Remove';

$_['tip_btn_edit'] = 'Edit';
$_['tip_btn_save'] = 'Save';
$_['tip_btn_cancel'] = 'Cancel';
$_['tip_btn_remove'] = 'Remove';

$_['tip_table_effective_time'] = 'Set a _current_country_ time that the mod. exclusive price will be taken into effect. If you don’t set this time, the mod. exclusive price will be taken into effect immediately.';
$_['tip_table_expiration_time'] = 'Set a _current_country_ time that the mod. exclusive price will be invalid. If you don’t set this time, the mod. exclusive price will be valid permanently.';
$_['tip_table_product_display'] = 'Whether product is visible to this buyer.';


$_['tip_buy_status'] = 'Control whether the buyer can purchase the product.';
$_['tip_price_status'] = 'Control whether the buyer can see the price.';
$_['tip_discount'] = 'The final price for buyer equals to the original price multiplied by the discount.';
$_['tip_coop_status_seller'] = 'Seller\\\'s cooperation status.';
$_['tip_coop_status_buyer'] = 'Buyer\\\'s state of cooperation.';

$_['tip_bid_not_edit'] = 'Exclusive price for rebate is not allowed to be modified';
$_['tip_bid_not_delete'] = 'Exclusive price for rebate is not allowed to be deleted';

$_['tip_drop_shipping_logo'] = 'Dropshipping Buyer';
$_['tip_home_pickup_logo'] = 'Pick up Buyer';
$_['tip_current_freight'] = 'Current Giga Cloud freight for reference.';
$_['tip_curt_exc_home_pickup_price'] = 'Current exclusive price excluding fulfillment.';
$_['tip_curt_exc_drop_shipping_price'] = 'Current exclusive price including freight.';
$_['tip_mod_exc_home_pickup_price'] = 'This exclusive price is the price excludes freight. For home pickup buyers, they will directly see this price. For dropshippping buyers, they will see the price added the real-time freight.';
$_['tip_ref_exc_drop_shipping_price'] = 'Reference dropshipping price including freight which corresponds to the mod. exclusive home pickup price.';
$_['tip_current_price'] = 'Current price excluding fulfillment of this product.';

$_['tip_exc_home_pickup_price'] = 'This exclusive price is the price excludes freight. For home pickup buyers, they will directly see this price. For dropshippping buyers, they will see the price added the real-time freight.';
$_['tip_mod_exc_price'] = 'Modified exclusive price excluding fulfillment.';

// Error
$_['error_save_discount'] = 'Discount value in the range of 0~1!';
$_['error_choose_checkboxes'] = 'Please select at least one buyer!';
$_['error_choose_buyer'] = 'Please select at least one buyer!';
$_['error_choose_product'] = 'Please select at least one product!';
$_['error_try_again'] = 'Please try again!';
$_['error_enter_discount'] = 'Please Enter the new discount!';
$_['error_choose_time'] = 'The time of failure should be greater than the time of effect!';
$_['error_established_cooperation'] = 'You have not established cooperation with this buyer!';
$_['error_enter_buyer_price'] = 'Please enter the mod. exclusive price!';
$_['error_common'] = 'The current page stays too long. Please refresh the page and try again.';
$_['error_product_price_proportion'] = 'The product price you would like to apply is too low. The Marketplace may charge the Seller an increased fulfillment fee based on current industry standards*. <br>*To learn more, please visit the Help Center and view the \\\'Usage Fee Standard\\\' terms.';
//和上面的内容区别是中间增加了CWF shipping fee will incur an additional 25% surcharge.，如果修改了主要内容，下面部分也需要修改
$_['error_product_price_proportion_cwf'] = 'The product price you would like to apply is too low. The Marketplace may charge the Seller an increased fulfillment fee based on current industry standards*. CWF shipping fee will incur an additional 25% surcharge. <br>*To learn more, please visit the Help Center and view the \\\'Usage Fee Standard\\\' terms.';

//Alert
$_['alert_batch_add_setting'] = 'Do you confirm the modification of selected _buyers_ buyers?';
