<?php
/**
 * Webkul Software.
 * @category  Webkul
 * @author    Webkul
 * @copyright Copyright (c) 2010-2016 Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 */
// Heading Goes here:
$_['heading_title']      	  = 'Return System ';

$_['entry_status']      	  = 'Status ';

// Text
$_['tab_general']     	 	  = 'Return Settings';
$_['tab_mailkeyowrds']     	= 'Mail Keywords';
$_['tab_labels']     	 	    = 'Add Shipping Label';

$_['text_labels']     	 	  = 'Upload Label(s)';

$_['text_module']     	 	  = 'Modules';
$_['text_edit']     	 	    = 'Edit Return System';
$_['text_success']    	 	  = 'Success: You have modified module Return System !';
$_['text_rma_return_add']	  = 'Return Address ';
$_['text_rma_return_add_info']= 'After send Shipping lable to customer this will be your return address for product.';

$_['text_rma_time']	  		  = 'Add Return Time ';
$_['text_rma_time_info']	  = 'You can add Time limit for customer, only less than these days customer can generate Return for any order. Use 0 or blank this field for unlimited time.';
$_['text_rma_enable']	  	  = 'Return Status ';
$_['text_rma_info_policy']	  = 'Return Policy ';
$_['text_rma_info_policy_info'] = 'Using this you can add policy from Informations which will display to customer at time of Add Return.';
$_['text_order_status']	 	  = 'Order Status for Return';
$_['text_order_status_info']  = 'Customer can place Return only for those status of order which is selected here.';
$_['text_image_extenstion']   = 'Allowed Image Extention';
$_['text_file_extenstion']    = 'Allowed File Extention';
$_['text_extenstion_size']    = 'Allowed File/Image Size';
$_['text_size_info']    	  = 'In Kb';
$_['text_extenstion_holder']  = 'jpg,JPG,png,PNG or Use * for All';


$_['text_rma_add_status']  	  = 'Manage Return Status';
$_['text_rma_add_reasons'] 	  = 'Manage Return Reasons';
$_['text_rma_manage']	  	  = 'Manage Return(s)';

$_['text_lable_image']	  	  = 'Image';
$_['text_lable_name']	  	  = 'Name';

$_['text_image_success']  	  = 'Success: %d Label(s) %s has been uploaded successfully.';
$_['text_success_delete']	  = 'Success: Shipping label(s) has been deleted successfully.';
$_['text_refresh']	  = 'Success: Return module has been successfully refreshed.';
$_['text_delete_table']	  = 'Success: All the Return module tables have been dropped';
$_['button_refresh']	  = 'Refresh Return module. It will delete all the Return data.';
$_['button_drop']	  = 'DROP all the Return table. It will delete all the Return module data except Return module setting';

// Error
$_['error_permission']  	  = 'Warning: You do not have permission to modify module Return System ';
$_['error_address']  	      = 'Warning: Return Address cannot be left blank.';
$_['error_system_orders']  	= 'Warning: Please select at least one order status for Return.';
$_['error_system_size']  	  = 'Warning: Allowed File size cannot be left empty.';
$_['error_system_time']  	  = 'Warning: Time cannot be negative.';
$_['text_refresh_module'] = 'Are you sure you want to refresh Return module';
$_['text_drop_module'] = 'Are you sure you want to DROP all the Return module tables';
// image file upload check error

$_['error_folder']     = 'Warning: Folder name must be a between 3 and 255!';
$_['error_exists']     = 'Warning: A file or directory with the same name already exists!';
$_['error_directory']  = 'Warning: Directory does not exist!';
$_['error_filetype']   = 'Warning: You are trying to upload incorrect image file type!';
$_['error_upload']     = 'Warning: File could not be uploaded for an unknown reason!';
$_['error_size']     = 'Warning: Please enter valid file size (Positive Integer)!';
$_['entry_add_store_credit']     = 'Store Credit Add Mail';
$_['tip_add_store_credit']     = 'This mail will be sent to customer on adding of store credit against Return';
$_['error_upload']            = 'Warning: File could not be uploaded for an unknown reason!';
$_['entry_theme']             = 'Voucher Theme';
$_['entry_seller_separate']   = 'Seller\'s Return Status';
$_['help_seller_separate']    = 'Seller\'s can manage their own Return Status such as default Status, solve and cancel Return Status.';

$_['entry_new_return_admin_mail']         = 'Mail to Admin on New Return';
$_['entry_new_return_seller_mail']        = 'Mail to Seller on New Return';
$_['entry_new_return_customer_mail']      = 'Mail to Customer on New Return';
$_['entry_status_to_customer_mail']       = 'Mail to Customer on Status Change';
$_['entry_status_to_seller_mail']         = 'Mail to Seller on Status Change';
$_['entry_status_to_admin_mail']          = 'Mail to Admin on Status Change';
$_['entry_message_to_seller_mail']        = 'Mail to Seller on Customer Message';
$_['entry_message_to_admin_mail']         = 'Mail to Admin on Customer Message';
$_['entry_message_to_customer_mail']      = 'Mail to Customer on Seller/Admin Reply';
$_['entry_message_to_seller_adminmail']   = 'Mail to Seller on Admin Reply';
$_['entry_message_to_admin_seller_mail']  = 'Mail to Admin on Seller Reply';
$_['entry_label_to_customer_mail']        = 'Mail to Customer on Shipping Label';

$_['entry_new_return_admin_mail_info']         = 'Mail to Admin on New Return';
$_['entry_new_return_seller_mail_info']        = 'Mail to Seller on New Return';
$_['entry_new_return_customer_mail_info']      = 'Mail to Customer on New Return';
$_['entry_status_to_customer_mail_info']       = 'Mail to Customer on Status Change';
$_['entry_status_to_seller_mail_info']         = 'Mail to Seller on Status Change';
$_['entry_status_to_admin_mail_info']          = 'Mail to Admin on Status Change';
$_['entry_message_to_seller_mail_info']        = 'Mail to Seller on Customer Message';
$_['entry_message_to_admin_mail_info']         = 'Mail to Admin on Customer Message';
$_['entry_message_to_customer_mail_info']      = 'Mail to Customer on Seller/Admin Reply';
$_['entry_message_to_seller_adminmail_info']   = 'Mail to Seller on Admin Reply';
$_['entry_message_to_admin_seller_mail_info']  = 'Mail to Admin on Seller Reply';
$_['entry_label_to_customer_mail_info']        = 'Mail to Customer on Shipping Label';
$_['entry_for']         	 = 'For';
$_['entry_code']      		 = 'Keyword';
