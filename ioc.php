<?php

///简单的 ios
class Container
{
    public static function getInstance($class_name, $params = [])
    {
        // 获取反射实例
        $reflector = new ReflectionClass($class_name);
        // 获取反射实例的构造方法
        $constructor = $reflector->getConstructor();

        $di_params = [];
        if ($constructor) {
        // 获取反射实例构造方法的形参
            /**
             * var_dump($constructor->getParameters());
             * array (size=2)
             * 0 =>
             * object(ReflectionParameter)[3]
             * public 'name' => string 'a' (length=1)
             * 1 =>
             * object(ReflectionParameter)[4]
             * public 'name' => string 'count' (length=5)
             */
            foreach ($constructor->getParameters() as $param) {
                $class = $param->getClass();
                if ($class) { // 如果参数是一个类，创建实例
                    $di_params[] = new $class->name;
                }
            }
        }

        var_dump($di_params);
        $di_params = array_merge($di_params, $params);
        // 创建实例
        return $reflector->newInstanceArgs($di_params);
    }
}

// 有了 getInstance 方法，我们可以试一下自动注入依赖
class A
{
    public $count = 100;
}

class B
{
    protected $count = 1;

    public function __construct(A $a, $count)
    {
        $this->count = $a->count + $count;
    }

    public function getCount()
    {
        return $this->count;
    }
}

$b = Container::getInstance(B::class, [10]);
var_dump($b->getCount()); // result is 110