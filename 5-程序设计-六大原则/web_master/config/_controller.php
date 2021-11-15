<?php

use App\Helper\CountryHelper;
use App\Helper\CurrencyHelper;
use App\Helper\LangHelper;

return [
    // 渲染 ajax 请求时用的 layout
    'render_ajax_layout' => 'ajax',
    // 渲染 yzc_front 模式的全局传参
    'render_front_global' => function () {
        // page_only 为保留参数名
        return [
            'lang' => LangHelper::getCurrentCode(),
            'country_id' => CountryHelper::getCurrentId(),
            'country_code' => CountryHelper::getCurrentCode(),
            'currency_code' => CurrencyHelper::getCurrentCode(),
            'currency_config' => CurrencyHelper::getCurrencyConfig(),
        ];
    }
];
