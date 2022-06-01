<?php
/**
 * Created by 11-bc.php.
 * User: fuyunnan
 * Date: 2022/5/31
 * Time: 15:36
 */

/**
 *BC 数学 函数
bcadd — 两个任意精度数字的加法计算
bccomp — 比较两个任意精度的数字
bcdiv — 两个任意精度的数字除法计算
bcmod — 任意精度数字取模
bcmul — 两个任意精度数字乘法计算
bcpow — 任意精度数字的乘方
bcpowmod — Raise an arbitrary precision number to another, reduced by a specified modulus
bcscale — 设置/获取所有 bc math 函数的默认小数点保留位数
bcsqrt — 任意精度数字的二次方根
bcsub — 两个任意精度数字的减法
 */
var_dump(bcadd(1,1,2));

var_dump(bccomp(13,12.2323,4));

var_dump(bcdiv(11,2222,6));