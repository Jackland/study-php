<?php
/**
 * Created by extends.php.
 * User: fuyunnan
 * Date: 2021/6/18
 * Time: 9:49
 */

/**
 *重学 php 基础类的继承
 */
class Person
{
    public $name;
    public $age;
    public $sex;
    public function __construct($name = "Alex", $age = 12, $sex = "Male")
    {
        $this->name = $name;
        $this->age = $age;
        $this->sex = $sex;
    }

    public function Say()
    {
        echo "My name is " . $this->name . ",and my age is " . $this->age . ",sex is " . $this->sex;
        echo "<br>";
    }
}

class Student extends Person
{
    public $grade;

    //覆盖父类中的构造方法，并多添加一个成员属性，用来创建对象并初始化成员属性
    public function __construct($name = "Alex", $age = 12, $sex = "Male", $grade = "Eight")
    {
        parent::__construct($name, $age, $sex); //调用父类中原本被覆盖的构造方法，为从父类继承过来的属性赋初值
        $this->grade = $grade;
    }

    public function Say()
    {
        parent::Say(); //调用父类中被覆盖的Say()方法
        echo $this->name . " is study in grade " . $this->grade . ".And my age is " . $this->age;
        echo "<br>";
    }
}

$p1 = new Student("John", 16, "Male");
$p1->grade = 8;
$p1->Say();