<?php
/**
 * Created by 12-callable.php.
 * 本小节 是理解 闭包函数和 can_fun_u
 * User: fuyunnan
 * Date: 2022/6/23
 * Time: 17:43
 */
/**
 *1 定义一个闭包函数
 *
 *
 * 类比   function callable($data){
 * }
 */

function index()
{
    $callable = function ($data) {
        return otherMethod($data);
    };
    return callbackHandle($callable);
}

function callbackHandle($callable)
{
    $data = [1, 2, 3];
    return call_user_func_array($callable, [$data]);
}


function otherMethod($data)
{
    var_dump('回调方法');
    var_dump($data);
    return '回调方法';
}

/**
 *执行入口
 */
index();