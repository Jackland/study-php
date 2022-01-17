<?php
/**
 * Created by 6-instanceof.php.
 * User: fuyunnan
 * Date: 2022/1/14
 * Time: 14:19
 */


/**
 *(1）判断一个对象是否是某个类的实例，（2）判断一个对象是否实现了某个接口。
 */
class A{

}


$obj = new A();
if ($obj instanceof A) {
    echo 'A';
}

class ExampleClass implements ExampleInterface {

}

interface ExampleInterface{

}

$exampleInstance = new ExampleClass();
if($exampleInstance instanceof ExampleInterface){
    echo 'Yes, it is';
}else{
    echo 'No, it is not';
}