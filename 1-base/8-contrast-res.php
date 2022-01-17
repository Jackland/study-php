<?php
/**
 * Created by 8-contrast-res.php.
 * User: fuyunnan
 * Date: 2022/1/14
 * Time: 15:12
 */
/**
 *列出一些php 常见的运算笔记 混淆点
 */

// != !==
$a = 11; $b ='11';
var_dump($a != $b); //false
echo "<br>======<br>";
var_dump($a !== $b);//true
echo "<br>======<br>";
var_dump(!NULL);