<?php

/**
 *里氏代换原则
 */

/**
 *业务场景
 */

/**
 * * setp--1
 */
class A
{

    public function sum($a, $b)
    {
        echo $a + $b."<br/>";
    }

}

class B {

    public function main()
    {
        (new A)->sum(1,2);
    }

}

(new B())->main();

/**
 *
 * setp--2
 *后来业务发展 想让2个数相减 并且*100 原来的类不在适用 需要重写里面的方法
 *
 * 通过继承 来重写父类的方法
 *
 */

class C extends  A{

    public function sum($a,$b)
    {
        echo (($a-$b)*100)."<br>";
    }
    public function decr($a,$b)
    {
        echo ($a-$b)."<br>";
    }
}

(new C())->sum(12,10);
(new C())->decr(12,10);


/**
 *在第二步
 * 我们虽然实现了需求 但是发现 父类的功能已经发生变化了 改变这个类当初初衷 对代码的扩展非常差
 *我们采取 让 b c 继承更通俗的类
 * 采用依赖、聚合、组合等关系代替。举个栗子：
 */

abstract class  D{
    abstract function calculate($a,$b);

    public function decr($a,$b)
    {
        echo $this->calculate($a,$b)*100;
    }
}

class E extends D{

    public function calculate($a,$b)
    {
        echo $a+$b.'<br>';
    }


}


/**
 *f类对公共类进行了重写
 */
class F extends D{

    public function calculate($a,$b)
    {
        return $a-$b;
    }
}

(new E())->calculate(10,5);

(new F())->calculate(10,5);

(new F())->decr(10,5);