<?php

/**
 *定义一个最简单的工厂类
 */
class cat
{
    public function age()
    {
        return '猫12岁';
    }

    public function wight()
    {
        return '猫34斤';

    }

}

class dog
{
    public function age()
    {
        return '狗12岁';
    }

    public function wight()
    {
        return '狗34斤';
    }
}

class factory
{
    public static function aminle()
    {
        $facCat = $_GET['arv'] ?? 'cat';
        /**
         *这里定义变化类
         */
        return new $facCat();
    }
}

$obj = factory::aminle();
echo $obj->age();
echo $obj->wight();