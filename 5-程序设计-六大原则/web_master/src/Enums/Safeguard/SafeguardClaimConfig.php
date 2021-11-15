<?php

namespace App\Enums\Safeguard;

use Framework\Enum\BaseEnum;

//理赔相关配置项写入此枚举类
class SafeguardClaimConfig extends BaseEnum
{
    const SALE_PLATFORM = 'SALE_PLATFORM'; //销售平台
    const CLAIM_PREFIX_CHAR = 'C'; //理赔单生成规则中间拼接的字符
    const CLAIM_MAX_NUMBER = 99; //一个保单最多生成99个理赔单
    const CLAIM_DOWNLOAD_NUMBER = 500;
    const CLAIM_UPLOAD_SIZE = 10; //上传最大值10M
    const PLATFORM_OR_REASON_TTL = 600; //reason和销售平台缓存时间
    const CLAIM_PROBLEM_DESC_LIMIT = 2000; //理赔 问题描述字符限制
    const HANDLE_KF_ROLE_ID = 6; //客服角色变化，java那边也是写死的，如果改的话，需要联动修改
    const HANDLE_CW_ROLE_ID = 2; //财务角色 php这边用不到，纯属记录
    const HANDLE_ZG_ROLE_ID = 26; //主管角色 php这边用不到
    const RETURN_CODE_80001 = 80001; //页面调整使用
}
