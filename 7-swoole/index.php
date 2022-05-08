<?php
/**
 * Created by index.php.
 * User: fuyunnan
 * Date: 2020/11/22`
 * Time: 10:39
 */

/**
 *具体业务逻辑类的方法
 */
class Myname{

    public function OutPutMyName(){

        return 'name is jaysontree';
    }
    public function age(){

        return 'age is 18';
    }

}
class Myage{

    public function OutPutMyAge(){

        return 'age is 18';
    }

    public function age(){

        return 'age is 18';
    }

}

/**
 *工厂入口类
 */
class NameFactory{

    public static function Namefunc(){

        return new Myname();
    }
}

$obj=NameFactory::Namefunc();

echo $obj->OutPutMyName();
echo $obj->age();
/**
 *----------------------------------------------------------------
 */

//定义一个抽象类

abstract class operation

{

    protected $_numA = 0;

    protected $_numB = 0;

    protected $_result = 0;

    public function __construct($a, $b)

    {

        $this->_numA = $a;

        $this->_numB = $b;

    }

//抽象方法所有子类必须实现该方法

    protected abstract function getResult();

}

//加法运算

class operationAdd extends operation

{

    public function getResult()

    {

        $this->_result = $this->_numA + $this->_numB;

        return $this->_result;

    }

}

//减法运算

class operationSub extends operation

{

    public function getResult()

    {

        $this->_result = $this->_numA - $this->_numB;

        return $this->_result;

    }

}

//乘法运算

class operationMul extends operation

{

    public function getResult()

    {

        $this->_result = $this->_numA * $this->_numB;

        return $this->_result;

    }

}

//除法运算

class operationDiv extends operation

{

    public function getResult()

    {

        $this->_result = $this->_numA / $this->_numB;

        return $this->_result;

    }

}

//定义工厂类

class operationFactory

{

//创建保存示例的静态成员变量

    private static $obj;

//创建实例的静态方法

    public static function CreateOperation($type, $a, $b)

    {

        switch ($type) {

            case '+':

                self::$obj = new operationAdd($a, $b);

                break;

            case '-':

                self::$obj = new operationSub($a, $b);

                break;

            case '*':

                self::$obj = new operationMul($a, $b);

                break;

            case '/':

                self::$obj = new operationDiv($a, $b);

                break;

        }

//最后返回这个实例

        return self::$obj;

    }

}

//最后我们使用工厂模式

$obj = operationFactory::CreateOperation('+', 100, 20);

echo $obj->getResult();



