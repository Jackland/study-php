<?php
/**
 * Created by 7-arrayAccess.php.
 * User: fuyunnan
 * Date: 2022/1/14
 * Time: 14:48
 */

class Foo implements ArrayAccess{

    private static $_CACHE = [];

    public function offsetExists($offset)
    {
        if (!self::$_CACHE[$offset]) {
            self::$_CACHE[$offset] = 12121;
        }
        echo "Implement offsetExists() method.";
        // TODO: Implement offsetExists() method.
    }

    public function offsetGet($offset)
    {
        if (self::$_CACHE[$offset]) {
            return self::$_CACHE[$offset];
        }

        echo "Implement offsetGet() method.";
        // TODO: Implement offsetGet() method.
    }

    public function offsetSet($offset, $value)
    {
        if (self::$_CACHE[$offset] !== $value) {
            return self::$_CACHE[$offset] = $value;
        }

        echo "Implement offsetSet() method.";
        // TODO: Implement offsetSet() method.
    }

    public function offsetUnset($offset)
    {
        if (isset(self::$_CACHE[$offset])) {
           unset(self::$_CACHE[$offset]);
        }
        echo 'Implement offsetUnset() method.';
        // TODO: Implement offsetUnset() method.
    }

    public function get()
    {
        return self::$_CACHE;

    }
}
$foo = new Foo();

//Implement offsetExists() method
$t = isset($foo['how']);// 输出: 这里是 offsetExists() 方法 你输入的参数是 how
echo "----".'<br>';

var_dump($foo->get());
die;

//Implement offsetSet() method
$foo['setName'] = 12121;
echo "----".'<br>';

//Implement offsetGet() method
echo $foo['setName'];
echo "----".'<br>';


//Implement offsetUnset() method

unset($foo['setName']);
echo "----".'<br>';

