<?php
/**
 *依赖倒转原则（Dependence Inversion Principle）

 */

/**
 * 赖倒置规定：高层模块不应该依赖于低层模块，二者都应该依赖其抽象；抽象不应该依赖于细节，细节应该依赖于抽象。因为相对于细节的多变性，抽象的东西要稳定的多。以抽象为基础搭建的架构要比以细节为基础的架构要稳定的多。依赖倒置的中心思想是面向接口编程。上层模块不应该依赖于下层模块，应该依赖于接口。从而使得下层模块依赖于上层的接口，降低耦合度，提高系统的弹性。
 */


/**
 *step 1
 */

class book{
    public function getContent()
    {
        return "这本书的内容很多的<br>";

    }

}

class news{
    public function getContent()
    {
        return "这本书的内容很多的。<br>";

    }
}


class sister{

    //原来book 不能随便改变
    public function read(Book $book)
    {
        echo $book->getContent();
    }

}

class main{

    public function run()
    {
        (new sister())->read(new book());
    }

}

/**
 *如果读报纸 或者其他
 */

//(new main())->run();


/**
 *step 2 修改方法
 *
 *
 * 4、优点
低层模块尽量都要有抽象类或接口,或者两者都有
变量的声明类型尽量是抽象类或接口
使用继承时遵循里氏替换原则
 *
 */

interface IReader{
    public function getContent();
}


class book1 implements IReader{

    public function getContent(){

        echo "获取报纸内容<br>";
    }

}

class news1 implements  IReader{

    public function getContent(){

        echo "获取漫画内容<br>";
    }
}

class mother {

    //原来book 不能随便改变
    public function read(IReader $reader)
    {
        echo $reader->getContent();
    }

}

/**
 *在这传入boo1对象
 *
 * 依赖关联
 *
 */

(new mother())->read(new book1());
(new mother())->read(new news1());