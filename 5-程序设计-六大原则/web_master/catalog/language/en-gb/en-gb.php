<?php
// Locale
$_['code']                  = 'en';
$_['direction']             = 'ltr';
$_['date_format_short']     = 'Y-m-d';
$_['date_format_long']      = 'l dS F Y';
$_['time_format']           = 'h:i:s A';
$_['datetime_format']       = 'Y-m-d H:i:s';
$_['decimal_point']         = '.';
$_['thousand_point']        = ',';

// Text
$_['text_enabled']          = 'Enabled';
$_['text_disabled']         = 'Disabled';
$_['text_home']             = 'Home';
$_['text_home_icon']        = '<i class="fa fa-home"></i>';
$_['seller_dashboard']      = 'Seller Central';
$_['text_yes']              = 'Yes';
$_['text_no']               = 'No';
$_['text_none']             = ' --- None --- ';
$_['text_select']           = ' --- Please Select --- ';
$_['text_all_zones']        = 'All Zones';
$_['text_pagination']       = 'Showing %d to %d of %d (%d Pages)';
$_['text_pagination']       = '%d-%d of %d';
$_['text_loading']          = 'Loading...';
$_['text_no_results']       = 'No records.';

// Buttons
$_['button_address_add']    = 'Add Address';
$_['button_back']           = 'Back';
$_['button_close']          = 'Close';
$_['button_continue']       = 'Continue';
$_['button_cart']           = 'Add to Cart';
$_['button_cancel']         = 'Cancel';
$_['button_compare']        = 'Compare this Product';
$_['button_wishlist_add']       = 'Add to Saved Items';
$_['button_wishlist_remove']       = 'Remove from Saved Items';
$_['button_checkout']       = 'Checkout';
$_['button_confirm']        = 'Confirm Order';
$_['button_coupon']         = 'Apply Coupon';
$_['button_delete']         = 'Delete';
$_['button_download']       = 'Download';
$_['button_edit']           = 'Edit';
$_['button_filter']         = 'Filter';
$_['button_new_address']    = 'New Address';
$_['button_change_address'] = 'Change Address';
$_['button_reviews']        = 'Reviews';
$_['button_write']          = 'Write Review';
$_['button_login']          = 'Login';
$_['button_update']         = 'Update';
$_['button_remove']         = 'Remove';
$_['button_reorder']        = 'Reorder';
$_['button_return']         = 'Return';
$_['button_shopping']       = 'Continue Shopping';
$_['button_search']         = 'Search';
$_['button_shipping']       = 'Apply Shipping';
$_['button_submit']         = 'Submit';
$_['button_guest']          = 'Guest Checkout';
$_['button_view']           = 'View';
$_['button_voucher']        = 'Apply Gift Certificate';
$_['button_upload']         = 'Upload File';
$_['button_reward']         = 'Apply Points';
$_['button_quote']          = 'Get Quotes';
$_['button_list']           = 'List';
$_['button_grid']           = 'Grid';
$_['button_map']            = 'View Google Map';

// Error
$_['error_exception']       = 'Error Code(%s): %s in %s on line %s';
$_['error_upload_1']        = 'Warning: The uploaded file exceeds the upload_max_filesize directive in php.ini!';
$_['error_upload_2']        = 'Warning: The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form!';
$_['error_upload_3']        = 'Warning: The uploaded file was only partially uploaded!';
$_['error_upload_4']        = 'Warning: No file was uploaded!';
$_['error_upload_6']        = 'Warning: Missing a temporary folder!';
$_['error_upload_7']        = 'Warning: Failed to write file to disk!';
$_['error_upload_8']        = 'Warning: File upload stopped by extension!';
$_['error_upload_999']      = 'Warning: No error code available!';
$_['error_curl']            = 'CURL: Error Code(%s): %s';
$_['error_max_file_size']       = 'Maximum file size is ';
$_['error_max_file_num']       = 'Maximum number of files is ';
$_['error_upload_fail']       = 'Your upload triggered the following error:  ';
$_['error_only_support_file_type']       = 'Only the following file types are supported: ';
$_['error_product_price_proportion'] = 'The product price you would like to apply is too low. The Marketplace may charge the Seller an increased fulfillment fee based on current industry standards*. Are you sure you wish to apply this price? <br>*To learn more, please visit the Help Center and view the \\\'Usage Fee Standard\\\' terms.';
$_['error_product_price_approve'] = 'The product price you would like to apply is too low. The Marketplace may charge the Seller an increased fulfillment fee based on current industry standards*. Are you sure you want to approve this bid request? <br>*To learn more, please visit the Help Center and view the \\\'Usage Fee Standard\\\' terms.';
//和上面的内容区别是中间增加了CWF shipping fee will incur an additional 25% surcharge.，如果修改了主要内容，下面部分也需要修改
$_['error_product_price_proportion_cwf'] = 'The product price you would like to apply is too low. The Marketplace may charge the Seller an increased fulfillment fee based on current industry standards*. CWF shipping fee will incur an additional 25% surcharge. Are you sure you wish to apply this price? <br>*To learn more, please visit the Help Center and view the \\\'Usage Fee Standard\\\' terms.';
$_['error_product_price_approve_cwf'] = 'The product price you would like to apply is too low. The Marketplace may charge the Seller an increased fulfillment fee based on current industry standards*. CWF shipping fee will incur an additional 25% surcharge. Are you sure you want to approve this bid request? <br>*To learn more, please visit the Help Center and view the \\\'Usage Fee Standard\\\' terms.';

// 一些通用的保证金标志
$_['global_margin_sign'] = '<span class="flag-margin-order flag-hover {class}">
<span class="giga icon-margin"
 data-toggle="tooltip"
 data-href="index.php?route=account/product_quotes/margin_contract/view&agreement_id={id}"
  title="Click to view the margin agreement details for agreement ID {id}.">
</span>
</span>';

$_['global_backend_futures_sign'] = <<<HTML
 <span class="flag-margin-order flag-hover {class}">
<span class="giga icon-futures"
 data-toggle="tooltip"
 data-href="index.php?route=account/product_quotes/futures/sellerBidDetail&id={id}"
  title="Click to view the future goods agreement details for agreement ID {s_id}.">
</span>
</span>
HTML;

$_['global_backend_rebate_sign'] = <<<HTML
<a target="_blank" href="index.php?route=account/product_quotes/rebates_contract/rebatesAgreementList&act=view&agreement_id={id}">
<img
 src ="/image/product/rebate_15x15.png"
 data-toggle="tooltip"
 style="padding-left: 1px;position: relative;top: -2px;"
  title="Click to view the rebate agreement details for agreement ID {s_id}.">
</a>

HTML;

$_['global_frontend_futures_sign'] = <<<HTML
 <span class="flag-margin-order flag-hover {class}">
<span class="giga icon-futures"
 data-toggle="tooltip"
 data-href="index.php?route=account/product_quotes/futures/detail&id={id}"
  title="Click to view the future goods agreement details for agreement ID {s_id}.">
</span>
</span>
HTML;
$_['global_frontend_futures_second_sign'] = <<<HTML
 <span class="flag-margin-order flag-hover {class}">
<span class="giga icon-futures"
 data-toggle="tooltip"
 data-href="index.php?route=account/product_quotes/futures/buyerFuturesBidDetail&id={id}"
  title="Click to view the future goods agreement details for agreement ID {s_id}.">
</span>
</span>
HTML;
/* When doing translations only include the matching language code */

// Datepicker
//$_['datepicker']            = 'af';
//$_['datepicker']            = 'ar-dz';
//$_['datepicker']            = 'ar-kw';
//$_['datepicker']            = 'ar-ly';
//$_['datepicker']            = 'ar-ma';
//$_['datepicker']            = 'ar-sa';
//$_['datepicker']            = 'ar-tn';
//$_['datepicker']            = 'ar';
//$_['datepicker']            = 'az';
//$_['datepicker']            = 'be';
//$_['datepicker']            = 'bg';
//$_['datepicker']            = 'bn';
//$_['datepicker']            = 'bo';
//$_['datepicker']            = 'br';
//$_['datepicker']            = 'bs';
//$_['datepicker']            = 'ca';
//$_['datepicker']            = 'cs';
//$_['datepicker']            = 'cv';
//$_['datepicker']            = 'cy';
//$_['datepicker']            = 'da';
//$_['datepicker']            = 'de-at';
//$_['datepicker']            = 'de-ch';
//$_['datepicker']            = 'de';
//$_['datepicker']            = 'dv';
//$_['datepicker']            = 'el';
//$_['datepicker']            = 'en-au';
//$_['datepicker']            = 'en-ca';
$_['datepicker']            = 'en-gb';
//$_['datepicker']            = 'en-ie';
//$_['datepicker']            = 'en-nz';
//$_['datepicker']            = 'eo';
//$_['datepicker']            = 'es-do';
//$_['datepicker']            = 'es';
//$_['datepicker']            = 'et';
//$_['datepicker']            = 'eu';
//$_['datepicker']            = 'fa';
//$_['datepicker']            = 'fi';
//$_['datepicker']            = 'fo';
//$_['datepicker']            = 'fr-ca';
//$_['datepicker']            = 'fr-ch';
//$_['datepicker']            = 'fr';
//$_['datepicker']            = 'fy';
//$_['datepicker']            = 'gd';
//$_['datepicker']            = 'gl';
//$_['datepicker']            = 'gom-latn';
//$_['datepicker']            = 'he';
//$_['datepicker']            = 'hi';
//$_['datepicker']            = 'hr';
//$_['datepicker']            = 'hu';
//$_['datepicker']            = 'hy-am';
//$_['datepicker']            = 'id';
//$_['datepicker']            = 'is';
//$_['datepicker']            = 'it';
//$_['datepicker']            = 'ja';
//$_['datepicker']            = 'jv';
//$_['datepicker']            = 'ka';
//$_['datepicker']            = 'kk';
//$_['datepicker']            = 'km';
//$_['datepicker']            = 'kn';
//$_['datepicker']            = 'ko';
//$_['datepicker']            = 'ky';
//$_['datepicker']            = 'lb';
//$_['datepicker']            = 'lo';
//$_['datepicker']            = 'lt';
//$_['datepicker']            = 'lv';
//$_['datepicker']            = 'me';
//$_['datepicker']            = 'mi';
//$_['datepicker']            = 'mk';
//$_['datepicker']            = 'ml';
//$_['datepicker']            = 'mr';
//$_['datepicker']            = 'ms-my';
//$_['datepicker']            = 'ms';
//$_['datepicker']            = 'my';
//$_['datepicker']            = 'nb';
//$_['datepicker']            = 'ne';
//$_['datepicker']            = 'nl-be';
//$_['datepicker']            = 'nl';
//$_['datepicker']            = 'nn';
//$_['datepicker']            = 'pa-in';
//$_['datepicker']            = 'pl';
//$_['datepicker']            = 'pt-br';
//$_['datepicker']            = 'pt';
//$_['datepicker']            = 'ro';
//$_['datepicker']            = 'ru';
//$_['datepicker']            = 'sd';
//$_['datepicker']            = 'se';
//$_['datepicker']            = 'si';
//$_['datepicker']            = 'sk';
//$_['datepicker']            = 'sl';
//$_['datepicker']            = 'sq';
//$_['datepicker']            = 'sr-cyrl';
//$_['datepicker']            = 'sr';
//$_['datepicker']            = 'ss';
//$_['datepicker']            = 'sv';
//$_['datepicker']            = 'sw';
//$_['datepicker']            = 'ta';
//$_['datepicker']            = 'te';
//$_['datepicker']            = 'tet';
//$_['datepicker']            = 'th';
//$_['datepicker']            = 'tl-ph';
//$_['datepicker']            = 'tlh';
//$_['datepicker']            = 'tr';
//$_['datepicker']            = 'tzl';
//$_['datepicker']            = 'tzm-latn';
//$_['datepicker']            = 'tzm';
//$_['datepicker']            = 'uk';
//$_['datepicker']            = 'ur';
//$_['datepicker']            = 'uz-latn';
//$_['datepicker']            = 'uz';
//$_['datepicker']            = 'vi';
//$_['datepicker']            = 'x-pseudo';
//$_['datepicker']            = 'yo';
//$_['datepicker']            = 'zh-cn';
//$_['datepicker']            = 'zh-hk';
//$_['datepicker']            = 'zh-tw';
$_['txt_运费'] = 'Fulfillment';
