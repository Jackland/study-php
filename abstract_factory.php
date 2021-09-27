<?php


/**
 *通过抽象类来约束字类必须 实现的方法 保证代码逻辑的严谨和整齐性
 */

abstract class operations
{
    protected $_numA = 0;
    protected $_numB = 0;
    protected $result = 0;

    public function __construct($a, $b)
    {
        $this->_numA = $a;
        $this->_numB = $b;
    }

    //抽象方法所有子类必须实现该方法

    protected abstract function getResult();
}


class add extends operations
{

    public function getResult()
    {
        $this->result = $this->_numA + $this->_numB;
        return $this->result;
    }

}


class div extends operations
{

    public function getResult()
    {
        $this->result = $this->_numA / $this->_numB;
        return $this->result;
    }
}

class factoryAb
{
    private static $obj;
    public static function createObj($numA, $numB, $type)
    {
        if ($type == '+') {
            self::$obj =  new add($numA, $numB);
        }
        if ($type == '/') {
            self::$obj = new div($numA, $numB);
        }
        return self::$obj;
    }
}

$obj =  factoryAb::createObj(10,2,'+');
$obj2 =  factoryAb::createObj(10,2,'/');
echo $obj->getResult().PHP_EOL;
echo $obj2->getResult();