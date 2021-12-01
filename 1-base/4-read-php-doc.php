<?php
/**
 * Created by 4-read-php-doc.php.
 * User: fuyunnan
 * Date: 2021/12/1
 * Time: 10:55
 */

/**
 *重新类继承接口的时候 可以不完全实现接口定义的方法
 *
 * 但是子类继承抽象类的时候 必须实现 未声明接口的方法
 */


interface A
{
    public function foo(string $s): string;

    public function bar(int $i): int;
}

// 抽象类可能仅实现了接口的一部分。
// 扩展该抽象类时必须实现剩余部分。
abstract class B implements A
{
    public function foo(string $s): string
    {
        return $s . PHP_EOL;
    }
}

class C extends B
{
    public function bar(int $i): int
    {
        return $i * 2;
    }
}
echo (new C())->bar(222);