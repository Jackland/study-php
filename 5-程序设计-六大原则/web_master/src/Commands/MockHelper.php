<?php

namespace App\Commands;

use Framework\Exception\NotSupportException;
use Framework\Http\Response;

class MockHelper
{
    /**
     * 模拟用户信息
     * 用于在 command 测试时，调用的一些方法中存在使用 customer() session('country') 等应该只在 html 模式下才应该有的数据时，模拟 html 数据
     * @param int $customerId 不需要登录时为0
     * @param array $config
     */
    public static function mockCustomer($customerId = 0, $config = [])
    {
        if (!app()->isConsole()) {
            throw new NotSupportException('非 command 下不允许调用！');
        }

        $config = array_merge([
            'country' => null, // 当未登录时可以设置国别，登录后设置无效
        ], $config);
        if ($config['country']) {
            session()->set('country', $config['country']);
        }

        app()->singleton('response', Response::class);
        load()->controller('startup/customer');
        if ($customerId) {
            customer()->loginById($customerId);
        }
        load()->controller('startup/startup');
    }
}
