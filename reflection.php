<?php
/**
 * Created by reflection.php.
 * User: fuyunnan
 * php 反射的学习
 * Date: 2021/6/17
 * Time: 20:00
 */

class User
{
    public     $username;
    protected  $age;
    private    $sex;
    public function __construct($args)
    {
        $this->username = $args['username'];
        $this->age = $args['age'];
        $this->sex = $args['sex'];
    }
    public function getUsername()
    {
        return $this->username;
    }
    private function getAge()
    {
        return $this->age;
    }
    public function getSex()
    {
        return $this->sex;
    }
}

# 为 User类创建ReflectionClass 类
//$reflect = new ReflectionClass('User');
//# 使用反射类实例化User 接受可变数目的参数，用于传递到类的构造函数，和 call_user_func() 很相似。
//$user = $reflect->newInstance(['username'=>'jesse', 'age'=>21, 'sex'=>'man']); //参数为数组，将以 array 形式传递到类的构造函数
//# 返回User类的实例。
//echo $user->getAge();


//Tip: 这里的参数数目为可变的，可为数组，字符串。构造内的值需要根据不同类型做出对应接受。个人喜欢数组直接传递方式。
class People extends User {


    public function getAge()
    {
        $reflect = new ReflectionClass('User');
        $instance = $reflect->newInstance(['username'=>'jesse', 'age'=>21, 'sex'=>'man']); //参数为数组，将以 array 形式传递到类的构造函数
        $method=  $reflect->getMethod('getAge');
        $method->setAccessible(true);
        echo $method->invoke($instance);

    }


//    public function main()
//    {
//        $reflect = new ReflectionClass('User');
//        $instance = $reflect->newInstance(['username'=>'jesse', 'age'=>21, 'sex'=>'man']); //参数为数组，将以 array 形式传递到类的构造函数
//        $method=  $reflect->getMethod('getAge');
//        $method->setAccessible(true);
//        echo $method->invoke($instance);
//    }

}

(new People(['username'=>'jesse', 'age'=>21, 'sex'=>'man']))->getAge();