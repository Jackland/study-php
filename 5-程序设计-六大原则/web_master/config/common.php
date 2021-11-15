<?php
// Require env config.
require DIR_WORKSPACE . 'env.php';

// APP VERSION
define('APP_VERSION', "20211111");

// DIR
define('DIR_SYSTEM', DIR_WORKSPACE . 'system/');
define('DIR_IMAGE', DIR_WORKSPACE . 'image/');
define('DIR_STORAGE', DIR_WORKSPACE . 'storage/');
define('DIR_RUNTIME', DIR_WORKSPACE . 'runtime/');
define('DIR_CONFIG', DIR_SYSTEM . 'config/');
define('DIR_CACHE', DIR_STORAGE . 'cache/');
define('DIR_DOWNLOAD', DIR_STORAGE . 'download/');
define('DIR_LOGS', DIR_RUNTIME . 'logs/');
define('DIR_MODIFICATION', DIR_STORAGE . 'modification/');
define('DIR_SESSION', DIR_STORAGE . 'session/');
define('DIR_UPLOAD', DIR_STORAGE . 'upload/');

// dropship pdf 路径
define('DIR_DROPSHIP_FILE_UPLOAD', DIR_STORAGE . 'dropshipPdf/');

define('DIR_VOUCHER_FILE_UPLOAD', DIR_STORAGE . 'voucherFile/');

//库存查询上传文件
define('DIR_INVENTORY_UPLOAD', DIR_STORAGE . 'inventory/');

//价格更新文件
define('DIR_PRICE_FILE_UPLOAD', DIR_STORAGE . 'priceCSV/');

// RMA文件上传路径
define('DIR_RMA_FILE_UPLOAD', DIR_STORAGE . 'rmaFile/');

// 素材包下载路径
define('DIR_STORAGE_PRODUCT_PACKAGE', DIR_STORAGE . 'product_package/');

// 临时生成的文件夹路径 推荐以后所有生成的临时文件都放在改文件下
define('DIR_STORAGE_TEMP', DIR_STORAGE . 'temp/');

// 程序号
define('PROGRAM_CODE', 'V1.0');

define('DIR_BRAND', DIR_IMAGE . 'brand');

//产品文件上传路径
define('DIR_PRODUCT_UPLOAD', DIR_STORAGE . 'productCSV/');

//库存订阅，提醒数量
define('SUBSCRIBE_COST_QTY', 20);

//Brand 品牌上传路径
define('DIR_PRODUCT_PACKAGE', DIR_WORKSPACE . 'productPackage');
define('PRODUCT_PACKAGE', '/productPackage/');  // Url 链接

//Review File 客户评论上传附件
define('DIR_REVIEW_FILE', DIR_STORAGE . 'reviewFiles/');

// OpenCart API
define('OPENCART_SERVER', 'https://www.opencart.com/');

define('AUTH_KEY', 'eXpjbUFwaTp5emNtQXBpQDIwMTkwNTE1');

define('SYSTEM_EMAIL', 'b2b@gigacloudlogistics.com');//已废弃，请使用 configDB('system_email');

// 定义dropship的类型 13846 uk-dropship
define('UK_DROPSHIP', 'UK-DropShip-Buyer');
define('USA_DROPSHIP', 'US-DropShip-Buyer');
define('ALL_DROPSHIP', ['UK-DropShip-Buyer', 'US-DropShip-Buyer', 'B2B-WillCall']);
define('EUROPEAN_SPECIAL_BUYER', 'European special buyer');
define('SERVICE_STORE', '(340,491,631,838)');
define('SERVICE_STORE_ARRAY', [340, 491, 631, 838]);
//物流信息定义
define('LOGISTICS_TYPES', ['DEFAULT', 'UPS', 'ARROW', 'ABF', 'CEVA', 'UPS Surepost GRD Parcel', 'FedEx']);
define('LOGISTICS_VERIFY_TYPES', ['UPS', 'ARROW', 'ABF', 'CEVA', 'FedEx']);
define('VERIFY_WAREHOUSE_TYPES', ['ABF', 'CEVA']);
define('WILLCALL_DROPSHIP', ['B2B-WillCall']);

define('WAYFAIR_VERIFY_TYPES',[
    'UPS',
    'FedEx Express',
    'Estes-Express',
    'FedEx',
    'RoadRunner Transportation Services',
    'XPO Logistics',
    'Zenith Freight Lines',
    'A. Duie Pyle',
    'ABF Trucking',
    'Averitt Express',
    'YRC'
]);

define('WAYFAIR_EUROPE_VERIFY_TYPES',[
    'DHL Parcel UK',
    'XDP',
    'UPS - UK',
    'DPD',
]);
define('WAYFAIR_EUROPE_FILL_IN_TYPES',[
    'DPD',
]);
define('WAYFAIR_EUROPE_MAPPING',[
    'DHL Parcel UK'=>222,
    'XDP'          =>222,
    'UPS - UK'     =>222,
    'DPD'          =>81,
]);
define('WAYFAIR_FEDEX_TYPES',['Next Day Air','2nd Day Air']);
define('WAYFAIR_LTL_TYPES',[
    'Estes-Express',
    'RoadRunner Transportation Services',
    'XPO Logistics',
    'Zenith Freight Lines',
    'A. Duie Pyle',
    'ABF Trucking',
    'Averitt Express',
    'YRC'
]);
define('LTL_BOL_CUT_TYPES',33);
#walmart采用的物流方式
define('WALMART_CUT_TYPES',[
    'Yellow Freight System - S2S'  =>23,
    'Yellow Freight System'        =>23,
    'USPS Priority Mail'           =>18,
    'FedEx 2Day'                   =>25,
    'FedEx 3 Day (Upgrade)'        =>25,
    'FedEx Home Delivery no SDR'   =>26,
    'FedEx Ground'                 =>18,
    'UPS Ground - DSV'             =>19,
    'Estes Forwarding Worldwide Basic Delivery' =>20,
    'FedEx 2Day - S2S'             =>25,
    'Pilot Freight Basic Delivery' =>21,
    '(Standard) FedEx 2Day'        =>25,
    'UPS Second Day Air - S2S'     =>27,
    'UPS Ground - S2S'             =>19,
    'UPS Ground'                   =>19,
    'UPS Second day Air'           =>27,
    'FHD3.0 S2H XML67'             =>26,
    'FedEx Ground - S2S'           =>18,
    'FedEx Home Delivery'          =>26,
    'Seko Worldwide'               =>22,
    'Store Label'                  =>24,
    'UPS Next Day Air'             =>28,
    'UPS Next Day Air - S2S'       =>28,
]);
define('WALMART_VERIFY_TYPES',[
    'FedEx Ground',
    'FedEx 2Day',
    'FedEx Home Delivery',
    'FedEx Home Delivery no SDR',
    'FedEx 2Day - S2S',
    'FedEx Ground - S2S',
    'FHD3.0 S2H XML67',
    'UPS Ground',
    'UPS Second day Air',
    'UPS Ground - S2S',
    'UPS Ground - DSV',
    'UPS Second Day Air - S2S',
    'UPS Next Day Air - S2S',
    'UPS Next Day Air',
    'Estes Forwarding Worldwide Basic Delivery',
    'Pilot Freight Basic Delivery',
    'Seko Worldwide',
    'Yellow Freight System',
    'Yellow Freight System - S2S',

]);
#walmart超大件采用的物流方式 采用以下物流方式有BOL
define('WALMART_LTL_TYPES',[
    'Estes Forwarding Worldwide Basic Delivery',
    'Pilot Freight Basic Delivery',
    'Seko Worldwide',
    'Yellow Freight System',
    'Yellow Freight System - S2S',
]);

define('WALMART_FILL_TRACKING_TYPES',[
    'Seko Worldwide',
    'YRC',
    'Yellow Freight System',
    'Yellow Freight System - S2S',
    'Pilot Freight Basic Delivery-Label',
    'Pilot Freight Basic Delivery',
    'FedEx Home Delivery no SDR',
    'FedEx Home Delivery',
]);

define('WALMART_SPECIAL_TRACKING_TYPES',[
    'Estes Forwarding Worldwide Basic Delivery',
]);
define('DROPSHIP_TYPE',[
    0 => 'Other External Platform',
    4 => 'Amazon',
    5 => 'Wayfair',
    6 => 'Other External Platform',
    7 => 'Walmart',

]);
define('PRODUCT_SHOW_ID', [694, 696, 746, 907, 908, 838, 631, 491, 340]);
define('COLLECTION_FROM_DOMICILE', [25, 24, 26]);

// 81:德 107:日 222:英 223:美
define('QUOTE_ENABLE_COUNTRY', [81, 107, 222, 223]);

define('DE_COUNTRY_ID', 81);
define('JAPAN_COUNTRY_ID', 107);
define('UK_COUNTRY_ID', 222);

define('AMERICAN_COUNTRY_ID', 223);

// 欧洲国家： 德国 81, 英国 222
define('EUROPE_COUNTRY_ID', [81, 222]);
define('NEW_ARRIVAL_DAY', 45);

// 详情页 dropship 定义时间 [2,2,8]
define('BUSINESS_DAYS', [
    222 => [2, 2, 8],
    223 => ['2-7', 3, 15],
    107 => [2, 2, 4],
    81 => [2, 2, 8]
]);
define('BUSINESS_DAYS_CWF', [
    222 => [5, 5, 15],
    223 => [5, 5, 15],
    107 => [5, 5, 15],
    81 => [5, 5, 15]
]);

define('SERVICE_TYPE', ['dropshipping', 'home pickup','cloud wholesale fulfillment']);
define('REBATE_TIMES',5);   // 2020-4-1 19:17:02 张新要求 由 2 改为 5

//N-94首页 Unused分组店铺（暂时不对外开放的店铺）
define('HOME_HIDE_CUSTOMER_GROUP', [17, 18, 19, 20, 23]);//17 DE-Seller-Unused, 18 JP-Seller-Unused, 19 US-Seller-Unused, 20 UK-Seller-Unused，oc_customer表customer_group_id字段
//N-94首页
define('HOME_HIDE_CUSTOMER', [
    694,696,746,907,908,//保证金店铺 694=>bxw@gigacloudlogistics.com(外部产品)，696=>bxo@gigacloudlogistics.com，746=>nxb@gigacloudlogistics.com，907=>UX_B@oristand.com，908=>DX_B@oristand.com
    340,491,631,838,//服务店铺产品 340=>service@gigacloudlogistics.com(美) 491=>serviceuk@gigacloudlogistics.com(英) 631=>servicejp@gigacloudlogistics.com(日) 838=>DE-SERVICE@oristand.com(德)
]);//oc_customer表customer_id字段。
//N-94首页 测试店铺
define('HOME_HIDE_ACCOUNTING_TYPE', [3,4]);//oc_customer表accounting_type字段，3测试店铺 4服务店铺

define('TRANSACTION_TYPE_ICON',[
    0 => '',
    1 => '<span data-toggle="tooltip" title="Rebate">R</span>',
    2 => '<span data-toggle="tooltip" title="Click to view the margin agreement details for agreement ID %s.">M</span>',
    3 => '<span data-toggle="tooltip" title="Click to view the future goods agreement details for agreement ID %s." >F</span>',
    4 => '',
    5 => '',
]);


/**
 * N-624国别与时区城市 已作废、已作废、已作废。
 * @deprecated
 * @see CountryHelper::getTimezoneByCode()
 */
define('COUNTRY_TIME_ZONE', [
    'USA' => 'America/Los_Angeles',
    'GBR' => 'Europe/London',
    'JPN' => 'Asia/Tokyo',
    'DEU' => 'Europe/Berlin'
]);

//N-640
!defined('REBATE_PLACE_LIMIT_START_TIME') && define('REBATE_PLACE_LIMIT_START_TIME', '2020-3-26 01:00:00');

//N-294 交割时 转现货保证金定金支付比例
define('MARGIN_PAYMENT_RATIO', 0.2);

/**
 * 所有的国家和时区对应关系 已作废、已作废、已作废。
 * @deprecated
 * @see CountryHelper::getTimezoneByCode()
 */
define('COUNTRY_TIME_ZONES', [
    'DEU' => 'Europe/Berlin',
    'JPN' => 'Asia/Tokyo',
    'GBR' => 'Europe/London',
    'USA' => 'America/Los_Angeles'
]);

// 所有的国家和时区相差时间的对应关系
define('COUNTRY_TIME_ZONES_NO', [
    'DEU' => '+01:00',
    'JPN' => '+09:00',
    'GBR' => '+00:00',
]);
// 美国太平洋时间时制映射
define('TENSE_TIME_ZONES_NO', [
    'PDT' => '-07:00',
    'PST' => '-08:00',
]);

// 需要做时间转换的国家
define('CHANGE_TIME_COUNTRIES', ['DEU', 'JPN' , 'GBR',]);

//维护的基础运费费率(102497改为按立方英尺计算，所以费率调整为1.5)，已经废除在这配置，改到setting中key=cwf_base_cloud_freight_rate
define('BASE_CLOUD_FREIGHT_RATE',1.5);

//云送仓最小运送体积100ft³
define('CLOUD_LOGISTICS_VOLUME_LOWER',100);

//核算运费DIM常数
define('DIM',250);

define('SHOW_BILLING_MANAGEMENT_SELLER',[76,2998]);

//平台运营
define('GIGACLOUD_PLATFROM_BUYER', [
    1115,//'pingtaiyunyingbuyer@gigacloudlogistics.com',
    1863,//'JPpingtaiyunyingbuyer@gigacloudlogistics.com'
    1864,//'DEpingtaiyunyingbuyer@gigacloudlogistics.com',
    1865,//'GBpingtaiyunyingbuyer@gigacloudlogistics.com',
]);
//平台运营
define('GIGACLOUD_PLATFROM_SELLER', [
    1116,//'pingtaiyunyingseller@gigacloudlogistics.com',
    2595,//'JPpingtaiyunyingseller@gigacloudlogistics.com',
    2774,//'GBpingtaiyunyingseller@gigacloudlogistics.com',
    2779,//'DEpingtaiyunyingseller@gigacloudlogistics.com',
]);
define('PRODUCT_PRICE_PROPORTION', 0.4);

//102458 美国本土Seller隐藏Credit Management功能 鉴别美国站点美国本土Seller的标志：与美国账户经理毛喆(PHP-account management：maozhe@oristand.com建立账号)关联的Seller。
//102574 在Product List页面提示文案：未与maozhe@oristand.com建立关联的外部Seller账号，先显示中文，等翻译，有翻译后显示中英文
define('RELATION_USA_SELLER_SPECIAL_ACCOUNT_MANAGER_EMAILS', ["maozhe@oristand.com"]);//已废弃，请使用 configDB('relation_usa_seller_special_account_manager_emails', []);

// 签收服务产品ID
define('DELIVERY_CONFIRMATION_PRODUCT_ID', 15527);

//欧洲需要修改运输公司
define('CHANGE_CARRIER_NAME', ['WHISTL', 'WHISTL_RB2']);

//#1170 【PHP】Fedex签收服务相关功能禁用
define('DISABLE_SHIP_TO_SERVICE', true);
