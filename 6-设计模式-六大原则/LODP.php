<?php
/**
 *
 *
迪米特原则（Law of Demeter，也称为最少知道原则Least Knowledge Principle）
 *
一个对象应该对其他对象保持最少的了解。类与类之间的关系越密切，耦合度越大。迪米特原则又叫最少知道原则，
 *
 * 即一个类对自己依赖的类知道的越少越好。也就是说，无论被依赖的类多么复杂，都尽量将逻辑封装在类的内部。对外只提供public方法，而不对外泄露任何信息。迪米特原则还有个更简单的定义：只与直接的朋友通信。什么是直接的朋友：每个对象都会与其他对象有耦合关系，只要两个对象之间有耦合关系，我们就称这两个对象之间是朋友关系。耦合的方式很多，依赖，关联，组合，聚合等。其中，我们称出现成员变量，方法参数，方法返回值中的类为直接的朋友，而出现在局部变量中的类不是直接的朋友。也就是说，陌生的类最好不要以局部变量的形式出现在类的内部。举个栗子：

 */


class company{

    public function getSubCompany()
    {
        $arr = [];
        for ($i=1;$i<=10;$i++){
            $arr [] = '分公司'.$i;
        }
        return $arr;
    }

}

class getCompany{

    public function main(company $com)
    {
        $arr = $com->getSubCompany();
        foreach ($arr as $item) {
            echo '分公司：'.$item."<br>";
        }
    }

}

$com = new company();
$getcom = new getCompany();
$getcom->main($com);


/**
 *实现了上个方法 我们还要对结果进行处理  显然这违背了最少知道原则
 */


class company1{

    public function getSubCompany()
    {
        for ($i=1;$i<=10;$i++){
            echo '分公司改良-'.$i."<br>";
        }
    }

}

class getCompany1{

    public function main(company1 $com)
    {
        $com->getSubCompany();
    }

}

$com1 = new company1();
$getcom = new getCompany1();
$getcom->main($com1);