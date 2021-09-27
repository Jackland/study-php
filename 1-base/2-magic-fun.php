<?php
/**
 * Created by 2-magic-fun.php.
 * User: fuyunnan
 * Date: 2021/9/23
 * Time: 15:32
 */

/**
 *1，__invoke()，调用函数的方式调用一个对象时的回应方法
 */
class Person

{
    public $sex;
    public $name;
    public $age;

    public function __construct($name = "", $age = 25, $sex = '男')
    {
        $this->name = $name;
        $this->age = $age;
        $this->sex = $sex;
    }

    public function __invoke()
    {
        echo '这可是一个对象哦';
    }

}

$person = new Person('小明'); // 初始赋值

$person() . '\n';
var_dump($person->name) . '\n';

var_dump(is_callable($person));