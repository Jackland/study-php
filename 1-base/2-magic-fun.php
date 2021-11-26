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
//class Person
//
//{
//    public $sex;
//    public $name;
//    public $age;
//
//    public function __construct($name = "", $age = 25, $sex = '男')
//    {
//        $this->name = $name;
//        $this->age = $age;
//        $this->sex = $sex;
//    }
//
//    public function __invoke()
//    {
//        echo '这可是一个对象哦';
//    }
//
//}
//
//$person = new Person('小明'); // 初始赋值
//
//$person() . '\n';
//var_dump($person->name) . '\n';
//
//var_dump(is_callable($person));


/**
 * =================== __get __set
 * 先来了解一下PHP类中的__get和__set函数
 * 当我们试图获取一个不可达属性时(比如private)，类会自动调用__get函数。
 * 当试图设置一个不可达属性时(比如private)，类会自动调用__set函数,我们一起来看个例子：
 * __set( $property, $value )` 方法用来设置私有属性， 给一个未定义的属性赋值时，此方法会被触发，传递的参数是被设置的属性名和值。
 */
class Person
{
    public $name = '周伯通';
    private $sex = '男';

//    public function __get($name){
//        echo '个人信息:'.$name.$this->sex;
//    }

    public function __set($aa,$bb)
    {
        $this->$aa = $bb;
    }
    public function getSex(){
        echo $this->sex; //获取新的属性
    }

}

$class = new Person();
$class->sex = '女';
echo $class->getSex();
