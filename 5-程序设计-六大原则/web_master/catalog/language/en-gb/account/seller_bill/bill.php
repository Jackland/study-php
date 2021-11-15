<?php

$_['seller_dashboard'] = 'Seller Central';
$_['billing_management'] = "Billing Management";
$_['text_bill'] = 'Invoices';
$_['text_separator'] = '';
$_['text_bill_detail'] = 'Invoice Detail';
$_['seller_bill_account_manage'] = 'Collection Management';
$_["seller_bill_account_manage_add"]='Create a Collection Account';
$_["seller_bill_account_manage_edit"]='Edit Collection Account';

$_['column_settlement_cycle'] = 'Settlement processed';
$_['column_settlement_item'] = 'Transaction Type';
$_['column_order_id'] = 'Order Number';
$_['column_sku_mpn'] = 'Item Code / MPN';

// 错误信息提示
$_['error_settlement_cycle'] = 'No in-process settlement periods currently, or your selected settlement period is invalid.';

//账单说明
$_['invoice_statements'] = 'Invoice Statements';
$_['invoice_statements_line1'] = 'Sales made between the 1st to the 15th of the month are settled on the 22nd. If the 22nd is not a working day, the settlement day will be delayed until the next working day. Sales made between the 16th to the end of the month will be settled on the 7th of the next month. If the 7th of the next month is not a working day, the settlement day will be delayed until the next working day.';
$_['invoice_statements_line2'] = 'Giga Cloud Fulfillment & packaging will hold sales revenue earned within the 7 days after the settlement day and transfer that revenue to the beginning balance of the next settlement period. This is done in the event that the registered Seller account does not generate enough income during the billable month to cover base services involved in shipping products to Buyers, which include but are not limited to: storage, reshipping, fulfillment fees, duty fees etc.';
$_['invoice_statements_line3'] = 'After the settlement date, your account\'s sales revenue will appear in your bank account within 3-5 working days. ';
$_['invoice_statements_line3_2'] = 'Within 3-5 working days after the settlement date, the receivables  will be remitted to the bank account attached to card with last 4 digits ending in %s. Please monitor your bank account to confirm it has been received. ';
$_['invoice_statements_line3_3'] = 'Within 3-5 working days after the settlement date, the receivables will be remitted to the Payoneer account attached to Payoneer ID with the last 4 digits ending in %s. Please monitor your Payoneer account to confirm it has been received. ';
$_['invoice_statements_line4'] = 'Please visit the <a target="_blank" href="%s"> Help Center </a> for information on how bill statements are calculated.';
$_['confirm_multiple_tip'] = 'For settlement amounts less than $500, a significant handling fee will be charged, please confirm if you would like to continue applying for a settlement.';
$_['settle_tip'] = 'Amounts less than $500 will be transferred by default to the next settlement cycle as the opening balance.';
$_['email_tip'] = 'If you have any questions or concerns regarding your invoice, please contact us at via email at account@gigacloudtech.com.';

$_['title_pay_info'] = 'The Giga Cloud Marketplace is scheduled to release your payment of %s on %s';
//结款周期
$_['settlement_processed'] = 'Settlement processed';   //结款周期
$_['settlement_period'] = 'Settlement period';
$_['settlement_processed_info'][1] = 'Verifying settlement';   //结算中 1
$_['settlement_processed_info'][2] = 'Settlement confirmed';   //已结算 2
$_['settlement_processed_info'][0] = 'in process';   //进行 0
//前后一笔账单
$_['previous_bill'] = 'previous bill';
$_['upcoming_bill'] = 'upcoming bill';
//期初余额
$_['beginning_balance'] = 'beginning balance';
$_['pre_beginning_balance'] = 'The remaining balance from the previous settlement';
$_['remaining_balance'] = 'Revenue on hold'; // 预留款
$_['total_beginning_balance'] = 'Total beginning balance';
//本期结算周期生成款项
$_['current_charges_generated'] = 'Charges generated during the current settlement period';

//总单中表单的详情
$_['bill_type'] = array(
    'order' => 'Order',     //订单
    'refund' => 'Refund',   //返金
    'other' => 'Other service fees',     //其他服务费用
    'order_order_amount' => 'Sales Total',    //订单金额
    'order_promotion' => 'Promotions',    //产品促销
    'order_logistics' => 'Freight fee (shipping fee & packaging fee)',    //物流费
    'order_platform_fee' => 'Marketplace fees',   //平台费
    'refund_order_amount' => 'Sales Total',    //订单金额
    'refund_promotion' => 'Promotions',      //产品促销
    'refund_logistics' => 'Freight fee (shipping fee & packaging fee)',     //物流费
    'other_special_service_fee' => 'Additional service fees',     //非常规服务费用
    'other_storage_fee' => 'Inventory Storage Fee',    //仓储费
    'other_tax' => 'Other Fees(Ocean freight fee & Duty fee, etc.)',    //海运费、关税
    'order_normal' => 'Standard',     //普通订单
    'order_rma' => 'Reshipment',     //重发订单
    'order_margin_deposit' => 'Margin deposit',    //保证金订金订单
    'order_margin_tail' => 'Margin final payment ',     //保证金尾款订单
    'order_platform_fee_detail' => 'Marketplace fees',     //平台费
    'order_incentive_rebate' => 'Incentive campaign fee',    //激励活动费用返点交易(供货商承担)
    'order_futures_margin_deposit' => 'Future goods deposit',    //期货保证金订金订单
    'order_futures_to_spot_margin_deposit' => 'Transfer to margin order',    //期货保证金转现货定金订单
    'order_futures_margin_tail' => 'Future goods final payment',    //期货保证金转现货定金订单
    'refund_rma' => 'RMA return',     //RMA Return
    'refund_reshipment' => 'Reshipment refund',     //Reshipment返金
    'refund_incentive_rebate' => 'Incentive campaign fee',    //激励活动费用返点交易(平台承担)
    'other_special_service' => 'Additional service fees',    //非常规服务费用
    'other_storage' => 'Inventory Storage Fee',     //仓租
    'other_tax_detail' => 'Other Fees(Ocean freight fee & Duty fee, etc.)',    //海运费、关税
    'other_futures_margin' => 'Future Goods Deposit',    //seller期货保证金相关
    'other_futures_margin_detail' => 'Future Goods Deposit',    //seller期货保证金相关

    'reserve' => 'Beginning balance',
    'revenue' => 'Revenues',
    'payment' => 'Expenses',
    'other_payment' => 'Other fees',
    'settlement' => 'Ending balance',
    'interest' => 'Financial expenses for supply chain',
    'reserve_settlement' => 'Ending balance of last period',
    'reserve_check' => 'Supplier pays debt to the marketplace/Marketplace pays the supplier receivables',
    'reserve_interest' => 'Financial expenses for last supply chain period',
    'reserve_interest_check' => 'Supplier pays supply chain financial expenses to the marketplace',
    'revenue_value' => 'Goods value income',
    'revenue_logistic' => 'Freight & packaging fee income',
    'revenue_promotion' => 'Promotion and subsidy income',
    'payment_rebate' => 'Refund expenses',
    'payment_logistic' => 'Freight & packaging fee expenses',
    'payment_complex' => 'Complex transaction expenses',
    'payment_promotion' => 'Promotional activity expenses',
    'other_payment_logistic' => 'Freight & packaging fees',
    'other_payment_storage' => 'Inventory storage fees',
    'other_payment_platform' => 'Marketplace fees',
    'other_payment_sea' => 'Ocean freight fees',
    'other_payment_tax' => 'Tariffs',
    'other_payment_other' => 'Other fees',
    'settlement_reserve' => 'Beginning balance',
    'settlement_total' => 'Profit made in the current period',
    'interest_reserve_total' => 'Opening financial balance of supply chain',
    'interest_total' => 'Financial balance of supply chain accrued in the current period',
    'V2_order_normal' => 'Standard',
    'V2_order_rma' => 'Reshipment',
    'V2_order_futures_margin_deposit' => 'Future goods deposit',
    'V2_order_futures_to_spot_margin_deposit' => 'Transfer to margin order',
    'V2_order_futures_margin_tail' => 'Future goods final payment',
    'V2_refund_incentive_rebate' => 'Incentive campaign fees',
    'V2_refund_rma' => 'RMA return',
    'V2_order_incentive_rebate' => 'Incentive campaign fees',
    'V2_other_payment_logistic' => 'Freight & packaging fees',
    'V2_other_storage' => 'Inventory storage fees',
    'V2_other_tax_sea' => 'Ocean carriage fees',
    'V2_other_tax_tariff' => 'Tariff',
    'V2_other_special_service' => 'Additional service fees',
    'V2_other_futures_margin_detail' => 'Future Goods Deposit',
    'V2_interest_total_detail' => 'Financial expenses for supply chain',
    'V2_order_margin_deposit' => 'Margin deposit',
    'V2_order_margin_tail' => 'Margin final payment',
    'V2_order_platform_fee_detail' => 'Marketplace fees',
    'V2_payment_complex' => 'Failure to uphold agreement',


);

$_['settlement_amount'] = 'Settlement amount';
$_['closing_balance'] = 'Closing balance';     //期末余额
$_['total_balance'] = 'Total balance';
$_['revenue_on_hold'] = 'Revenue on hold';
$_['received_amount'] = 'Received amount';
$_['settlement_date'] = 'Settlement date';
$_['sub_total'] = 'Subtotal';

// invoice Detail
$_['column_produce_date'] = 'Incurred Time';
$_['column_bill_type'] = 'Bill Type';
$_['column_order_num'] = 'Order Number';
$_['column_bill_type_son'] = 'Bill Type Detail';
$_['column_relate_order_num'] = 'Relate Order Number';
$_['column_item_code_mpn'] = 'Item Code<br>MPN';
$_['column_total'] = 'Total';
$_['column_freight'] = 'Fulfillment Fee';
$_['column_charge_detail'] = 'Charge Detail';
$_['column_buyer_freight'] = 'Fulfillment & packaging fees paid by Buyer';
$_['column_back_freight'] = 'Fulfillment & packaging fees returned to Buyer';
$_['column_product_total'] = 'Total Value of goods';
$_['column_quantity'] = 'Quantity';
//tips
$_['tip_red'] = 'Your last 7-day sales revenue is negative.';
$_['tip_blue'] = 'Giga Cloud Fulfillment & packaging will hold sales revenue earned within the 7 days after the settlement day and transfer that revenue to the beginning balance of the next settlement period. ';

#结算日期---总单底部显示
$_['end_settlement_data'][0] = 'Transfer amount inviate on '; //正在进行
$_['end_settlement_data'][1] = 'Transfer amount scheduled to inviate on'; //正在进行

# 下载csv tips
$_['download_tips'] = 'Download Invoice';

# 账单总单蓝框下第一行 结算时间和金额   1:状态  2金额正负   3.有无银行卡号
$_['start_bill_overview'][0][0] = 'Transfer of <b>%s</b> scheduled to initiate on <b>%s</b>.';   //正在进行---总金额为正
$_['start_bill_overview'][0][1] = 'Please note: your total balance is currently negative.';   //正在进行---总金额为负数
//结算中（总金额为非负数,无银行卡号） 1.预计结款金额  2.预计结款时间
$_['start_bill_overview'][1][0][0] = 'Transfer of <b>%s</b> scheduled to initiate on <b>%s</b>. Please contact business representative to provide your transfer account.';
//结算中（总金额为非负数,有银行卡号）   1.银行卡后三位  2.预计结款金额  3.预计结款时间  4.流水号
$_['start_bill_overview'][1][0][1] = 'A transfer was made to your bank account ending in %s in the amount of <b>%s</b> on <b>%s</b>.<br>Funds can take 3-5 business days to appear in your bank account.<br>Transfer ID for this fund transfer: %s.';
//结算中（总金额为负数）
$_['start_bill_overview'][1][1] = 'Please note: your total balance is negative, please pay in time. ';
//已结算（实际结算金额金额为0）    1.实际结算金额  2.实际结算日期
$_['start_bill_overview'][2][0] = 'Transfer of <b>%s</b> initiate on <b>%s</b>.';
//已结算（实际结算金额金额为正数） 1.银行卡后三位  2.实际结款金额  3.实际结款时间  4.流水号
$_['start_bill_overview'][2][1] = 'The Giga Cloud Marketplace is scheduled to release your balance is <b>%s</b> in this settlement period.';


