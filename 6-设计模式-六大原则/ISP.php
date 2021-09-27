<?php
/**
 * 接口隔离原则（InterfaceSegregation Principles）
 */

interface A
{

    public function method1();

    public function method2();

    public function method3();

    public function method4();

    public function method5();

}

class B
{

    public function depend1(A $a)
    {
        $a->method1();
    }

    public function depend2(A $a)
    {
        $a->method2();
    }

    public function depend3(A $a)
    {
        $a->method3();
    }
}

class C
{
    public function depend1(A $a)
    {
        $a->method1();
    }

    public function depend4(A $a)
    {
        $a->method4();
    }

    public function depend5(A $a)
    {
        $a->method5();
    }

}

class D implements A
{
    public function method1()
    {
        echo 'D调用了1方法' . "<br>";
    }

    public function method2()
    {
        echo 'D调用了2方法' . "<br>";
    }

    public function method3()
    {
        echo 'D调用了3方法' . "<br>";
    }

    public function method4()
    {
        echo 'D调用了4方法' . "<br>";
    }

    public function method5()
    {
        echo 'D调用了5方法' . "<br>";
    }

}

class F implements A
{
    public function method1()
    {
        echo 'F调用了1方法' . "<br>";
    }

    public function method2()
    {
        echo 'F调用了2方法' . "<br>";
    }

    public function method3()
    {
        echo 'F调用了3方法' . "<br>";
    }

    public function method4()
    {
        echo 'F调用了4方法' . "<br>";
    }

    public function method5()
    {
        echo 'F调用了5方法' . "<br>";
    }

}

class program
{

    public function man()
    {
        $b = new B();
        $b->depend1(new D());
        $b->depend2(new D());
        $b->depend3(new D());

        $c = new C();
        $c->depend1(new F());
        $c->depend4(new F());
        $c->depend5(new F());

    }

}

/**
 *这个为了建立依赖关系 b c 两个都实现了不必要的方法
 */
(new program())->man();


/**
 *改版
 */
interface Q
{
    public function method1();
}

interface W
{
    public function method2();

    public function method3();
}

interface  E
{
    public function method4();

    public function method5();
}

class T implements Q, W
{
    public function method1()
    {
        echo 'T实现1' . "<br>";
    }

    public function method2()
    {
        echo 'T实现2' . "<br>";
    }

    public function method3()
    {
        echo 'T实现3' . "<br>";
    }
}

class Y implements Q, E
{
    public function method1()
    {
        echo 'Y实现1' . "<br>";
    }

    public function method4()
    {
        echo 'Y实现4' . "<br>";
    }

    public function method5()
    {
        echo 'Y实现5' . "<br>";
    }
}


class O
{

    public function depend1(T $t)
    {
        $t->method1();
    }

    public function depend2(T $t)
    {
        $t->method2();
    }

    public function depend3(T $t)
    {
        $t->method3();
    }
}

class P
{
    public function depend1(Y $a)
    {
        $a->method1();
    }

    public function depend4(Y $a)
    {
        $a->method4();
    }

    public function depend5(Y $a)
    {
        $a->method5();
    }

}





class program1
{

    public function main()
    {
        $t = new O();
        $t->depend1(new T());
        $t->depend2(new T());
        $t->depend3(new T());

        $t = new P();
        $t->depend1(new Y());
        $t->depend4(new Y());
        $t->depend5(new Y());

    }
}

(new program1())->main();