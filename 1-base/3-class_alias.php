<?php
/**
 * Created by 3-class_alias.php.
 * User: fuyunnan
 * Date: 2021/11/24
 * Time: 17:05
 */

/**
 *于class_alias — 为一个类创建别名
 *
 * 用户定义的类 original 创建别名 alias。 这个别名类和原有的类完全相同。
 */
class foo { }

class_alias('foo', 'bar');

$a = new foo;
$b = new bar;
var_dump($a,$b);
// the objects are the same
var_dump($a == $b, $a === $b);
var_dump($a instanceof $b);

// the classes are the same
echo "==========";
var_dump($a instanceof foo);
var_dump($a instanceof bar);

var_dump($b instanceof foo);
var_dump($b instanceof bar);