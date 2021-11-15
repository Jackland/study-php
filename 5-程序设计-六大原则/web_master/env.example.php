<?php
/**
 * Created by PhpStorm.
 * User: lester.you <youchangming@oristand.com>
 * Date: 2019/4/18 11:00
 * Desc: An example of environment config.
 */

// 是否开启错误显示到页面
ini_set('display_errors', 1);   // 生产环境请设置为 0

define('OC_DEBUG', 0);
define('OC_ENV', 'prod');

// 域名 如果访问路径为 localhost/yzc
/**
 * 域名 (请求的路径)
 * 例如：
 *  域名       :  b2b.gigacloudlogistics.com
 *  IP         : 192.168.0.35
 *  IP+port   :  192.168.0.35:8080
 *  域名 + 路径:  localhost/yzc
 * 注意： 最后面不要加 "/"
 */
define('HOST_NAME', '');
define('HTTPS_ENABLE', false);

// Database
define('DB_DRIVER', 'mpdo');
define('DB_HOSTNAME', 'localhost');
define('DB_PORT', '3306');
define('DB_USERNAME', '');
define('DB_PASSWORD', '');
define('DB_DATABASE', 'yzc');
define('DB_PREFIX', 'oc_');

define('DB_READ_HOSTNAME','192.168.20.225');
define('DB_READ_USERNAME', 'tester');
define('DB_READ_PORT', '3306');
define('DB_READ_PASSWORD', 'tester@b2b.2020');
define('DB_READ_DATABASE', 'yzc_test_35_1');
//
//define('DB_WRITE_HOSTNAME','192.168.20.225');
//define('DB_WRITE_USERNAME', 'tester');
//define('DB_WRITE_PORT', '3306');
//define('DB_WRITE_PASSWORD', 'tester@b2b.2020');
//define('DB_WRITE_DATABASE', 'yzc_test_17');

// REDIS 配置
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PASSWORD', '');
define('REDIS_PORT', '6379');
define('REDIS_REDIS_DATABASE', 0);
define('REDIS_SESSION_DATABASE', 1);
define('REDIS_CACHE_DATABASE', 2);
define('REDIS_B2B_JAVA_DATABASE', 3); //和java 商品搜索配合使用的Redis库

// 各项适配器
define('SESSION_ADAPTER', 'db'); // db/redis/file
define('CACHE_ADAPTER', 'redis'); // redis/file
define('SESSION_TTL', 43200); // session 有效期

// yzcm地址
define('URL_YZCM', 'http://localhost:8088/yzcm');
define('URL_ES', 'http://t1.b2b.orsd.tech:8088/search/product');

// yzc_task_work 的地址
define('URL_TASK_WORK', 'http://t1.b2b.orsd.tech:8081/');

//爬虫地址
define('URL_REMOTE', 'http://localhost:8089/remote');

// 请求日志
define('REQ_LOG_SAVE', false);
define('REQ_LOG_SAVE_MONTH', 3);

// 站内信发邮件debug模式
define('COMMUNICATION_MAIL_DEBUG', false);

// 监控站内信
define('UNREPLY_DEBUG', false);

// 如果为 1, 则记录所有SQL
define('DEBUG',0);   //Production: 0
define('DB_SQL_RECORD_MIN_TIME', 1500);

// 是否显示错误信息
define('ERROR_DISPLAY', false);

// OMD中间件
define('OMD_POST_URL', '');
define('OMD_POST_API_KEY', '');

// ONsite接口
define('GIGA_ONSITE_API_URL', ''); //取消订单，修改地址，同步供应商
define('ONSITE_LOGIN_URL','http://sit.onsite.orsd.tech/login');
// 在库系统
define('OSJ_POST_URL', '');
define('OSJ_POST_API_KEY', '');

// b2b management api
define('B2B_MANAGEMENT_BASE_URL','http://192.168.0.35:8085/b2bmanage');
define('B2B_MANAGEMENT_AUTH_TOKEN','RuEV4D7nRJzi7ShojhjZN76dtM4BItZmYfzkY0Ou0RLqGyar8ruIhYTwchfE5ma8V7t5NP0uPm2YBfLNrm8wPitWGFzTuSJp8WFNrUyft81ivIgRNl0Oa6oQ5Ib2DiXSeh1pJw3IQAbKvq4ip0Ccq07u8Ypv8pGn5I6i0YYDXgW5EX1CzFKeuoPTbHt3waFVRHy6vkAU0cyx3UHF1ZRwvkFtR5Xpt3H6yWXCOWUSytOKbWNpWz8N1VbErWMk25eR');

//define('ENV_DROPSHIP_YZCM','dev_35');
//define('ENV_DROPSHIP_YZCM','dev_17');
//define('ENV_DROPSHIP_YZCM','pro');

//define('ENV_SPHINX','pro_test');
//define('ENV_SPHINX','dev_35');
//define('ENV_SPHINX','dev_17');
//define('ENV_SPHINX','pro');

//define('SPHINX_HOST','');
//define('SPHINX_PORT','');

define('REBATE_PLACE_LIMIT_START_TIME', '2020-2-26 01:00:00');

// 企业微信机器人报警的 webhook key
define('WE_CHAT_BUSINESS_ROBOT_KEY', ''); // 推送报警信息

// CNZZ 统计配置
define('CNZZ_ANALYSIS_URL', ''); // CNZZ 统计的 js 代码，如：'https://s4.cnzz.com/z_stat.php?id=1279194549&web_id=1279194549'
define('CNZZ_ANALYSIS_ID', ''); // CNZZ 统计的 js 代码中的站点 id，如：1279194549
define('CNZZ_ANALYSIS_EVENT_CONSOLE_LOG', false); // 点击事件时是否 console 输出日志

// 阿里 OSS
define('IMAGE_NOT_EXIST_LOG', false); // 图片不存在时记录日志
define('DEBUG_IMAGE_GET_TIME', false); // 是否开启检查获取图片时的 timeline 时间
define('DEFAULT_CHECK_EXIST_WHEN_GET_IMAGE', true); // 默认是否在获取图片url时检查图片是否存在
define('ALI_OSS_AK', ''); // 阿里OSS的AK，为空时使用本地文件存储
define('ALI_OSS_SK', '');
define('ALI_OSS_BUCKET', 'btbfile');
define('ALI_OSS_ENDPOINT', 'oss-cn-hongkong.aliyuncs.com');
define('ALI_OSS_DOMAIN', 'http://btbfile.oss-cn-hongkong.aliyuncs.com');
define('ALI_OSS_IS_CNAME', false);
define('ALI_OSS_IS_URL_SIGN', true);
define('ALI_OSS_URL_SIGN_TIMEOUT', 180);

// 短信
define('SMS_REAL_SEND', false); // 是否真实发送，测试时设为 false，不真实发送
define('SMS_CAN_SEND_EVERYONE', false); // 是否可以发给任何人，测试时设为 false，需要配置 oc_setting 表里的 sms_can_send_white_list 配置，前提需要 SMS_REAL_SEND 为 true
define('SMS_ALI_AK', ''); // 阿里云短信 AK
define('SMS_ALI_SK', ''); // 阿里云短信 SK
define('SMS_ALI_SIGN', '大健云仓'); // 阿里云短信签名

// 日志开关，可以同时开启
define('CHANNEL_LOG_MODE_SPLIT', true); // 通道日志分开记录
define('CHANNEL_LOG_MODE_MIX', false); // 通道日志组合记录
define('CHANNEL_LOG_MODE_MIX_SKIP', ['search']); // 聚合通道忽略某些日志

// buyer seller 推荐的 debug 日志
define('BUYER_SELLER_RECOMMEND_PROCESS_DEBUG', false);

// 页面访问安全控制
if (file_exists(__DIR__ . '/config/__safe_checker.php')) {
    define('PAGE_VIEW_SAFE_CONFIG', require(__DIR__ . '/config/__safe_checker.php'));
}

// 系统维护
define('IS_DOWN', false);
define('DOWN_END_TIME', '1979-01-01 00:00:00'); // 太平洋时区
define('DOWN_ESTIMATE_TIME', '19:24-21:05');
define('DOWN_TOKEN', 'afdafda;');

define('JOY_BUY_API_URL', 'http://test.gigacloudlogistics.com');
define('JOY_BUY_USERNAME', 'b2b_tester');
define('JOY_BUY_PASSWORD', 'test12345');
define('JOY_BUY_APP_KEY', 'app_20210319000001368055');

// 海云系统
define('URL_MARITIME', 'http://sit.maritime.orsd.tech');

// 文件下载签名KEY-此处是测试配置
define('FILE_DOWNLOAD_SIGN_KEY', 'K9HehZNd2do9OdiQ-dev');

//admin后台ip白名单配置(在ip地址或者ip段任意一个 即可访问到admin)
define('ENABLE_ADMIN_WHITE', true);//默认开启
define('ADMIN_ALLOW_IPS', '127.0.0.1');//ex : 192.168.0.21;192.168.0.65
define('ADMIN_ALLOW_NETWORK', '');// ex: 192.168.0.1/24
