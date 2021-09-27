<?php
/**
 * Created by call.php.
 *
 * 子类继承父类 会继承父类的魔术方法
 *
 * User: fuyunnan
 * Date: 2021/6/15
 * Time: 17:15
 */


/**
 * Class Demo
 * @method static static strTo($a,$b) 申明doc

 */
class Demo{

    public function __call($name, $arguments)
    {
        // 注意: $name 的值区分大小写
        echo "Calling object method '$name' "
            . implode(', ', $arguments). "\n";
    }

    /**  PHP 5.3.0之后版本  */
    public static function __callStatic($name, $arguments)
    {
        // 注意: $name 的值区分大小写
        echo "Calling static method '$name' "
            . implode(', ', $arguments). "\n";
    }
}


class sonDemo extends Demo {
    public function main()
    {
        Demo::strTo('static',555);
        (new Demo())->hello('aa',1111);
    }
}

(new sonDemo())->main();


