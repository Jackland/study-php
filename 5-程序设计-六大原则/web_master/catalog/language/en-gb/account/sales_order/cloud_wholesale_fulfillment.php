<?php
$_['heading_parent_title'] = 'Sales Order Management';
$_['heading_title'] = 'Cloud Wholesale Fulfillment';
$_['heading_title_order_info'] = 'Sales Order Details';
$_['heading_title_cloud_wholesale_fulfillment'] = 'Cloud Wholesale Fulfillment Order';


$_['btn_drop_shipping'] = 'Drop Shipping';
$_['btn_cloud_wholesale_fulfillment'] = 'Cloud Wholesale Fulfillment';


$_['status_0'] = 'Order Review';
$_['status_1'] = 'Pending Assignment';  //待分仓           \
$_['status_2'] = 'IN-Process Prep';     //待备货            \
$_['status_3'] = 'Completed Prep';      //已备货            -- 云送仓系统返回的状态
$_['status_4'] = 'Pending Shipment';    //待发货            /
$_['status_5'] = 'Shipping';          //部分发货           /
$_['status_6'] = 'Shipping';          //全部发货          /
$_['status_7'] = 'Delivered';           //已完成
$_['status_16'] = 'Canceled';           //已取消 只有客服才有权限操作

$_['status_0_2'] = 'PROCESSING';   // 集合了 0-2的状态
$_['status_5_6'] = 'SHIPPING';
$_['status_all'] = 'ALL';
$_['status_cancel'] = 'CANCELED';

$_['tips_status_0_2'] = 'Order currently being processed, has not yet entered preparation stage.';
$_['tips_status_3'] = 'Order has completed the preparation stage. If you are shipping this order to FBA warehouse, the Giga Cloud Marketplace has updated the actual pallet quantity. Please upload the pallet label files as soon as possible or it will impact the shipment.';
$_['tips_status_4'] = 'The shipment method has been confirmed and is waiting to be shipped out.';
$_['tips_status_5_6'] = 'Part of or all pallets have been shipped out.';
$_['tips_status_7'] = 'All pallets have been delivered.';
$_['tips_status_cancel'] = 'Canceled orders. You can only have your items returned to your inventory via submitting an RMA request.';
$_['tips_fba_cp'] = ' _x_ FBA orders have been prepared; the Giga Cloud Marketplace has updated the actual pallet quantity. Please upload the pallet label files as soon as possible or it will impact the shipment.';
$_['tips_tracking_information'] = 'For up-to-date tracking information, you can track your order via GigaCloud’s tracking status.';

// info

$_['info_text_order_id'] = 'Order ID';
$_['info_text_cwf_type'] = 'Fulfillment Type';
$_['info_text_cwf_type_fba'] = 'FBA';
$_['info_text_cwf_type_other'] = 'Other (non FBA)';
$_['info_text_order_status'] = 'Order Status';
$_['info_text_pallets_num'] = 'Number of Pallets';
$_['info_text_order_create_time'] = 'Creation Time';
$_['info_text_purchase_order_id'] = 'Purchase Order ID';
$_['info_text_order_rma_id'] = 'RMA ID';
$_['info_ship_to'] = 'Ship To';

$_['text_no_rma'] = '<span style="color: #FA6400">Not Applied RMA</span>';

$_['tips_column_volume'] = 'Volume will be rounded to the nearest tenth decimal.';
$_['tips_column_volume_inch'] = 'If the volume of a box is a decimal, it needs to be rounded up to the next whole number using the following unit: cubit feet (ft³). i.e. Volumes between 4.1-4.9 are to be rounded up to 5. To calculate order total volume: The volume of each box * Quantity of boxes';
$_['tips_total_volume_less_2'] = 'The volume of _origin_volume_ m³ is less than 2m³，the shipping fee is calculated by 2m³.'; // _total_volume_
$_['tips_total_volume_less_inch'] = 'The volume of _origin_volume_ ft³ is less than ' . CLOUD_LOGISTICS_VOLUME_LOWER . 'ft³，the shipping fee is calculated by ' . CLOUD_LOGISTICS_VOLUME_LOWER . 'ft³.';
$_['tips_save_label_btn'] = 'Click to upload the pallet label file. Once uploaded, it cannot be modified.';

$_['shipping_status_0'] = 'Pending Pick-Up';
$_['shipping_status_1'] = 'In-Transit'; //配送中
$_['shipping_status_2'] = 'Delivered';

// Error
$_['error_file_type'] = 'Pallet Label must be a .pdf file.';
$_['error_file_size'] = 'Pallet Label cannot exceed 30 M.';
$_['error_timeout'] = 'The connection has timed out. Please try again later.';
$_['error_upload_again'] = 'Please upload again';
$_['error_timeout_flush'] = 'Please upload again';
$_['error_pallet_label_upload'] = 'The Pallet Label cannot be modified.';

$_['volume_class_cm'] = "m³";
$_['length_class_cm'] = "cm";
$_['volume_class_inch'] = "ft³";
$_['length_class_inch'] = "in";
