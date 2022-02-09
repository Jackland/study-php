<?php
/**
 * Created by Person.php.
 * User: fuyunnan
 * Date: 2022/2/9
 * Time: 16:14
 */

namespace Acme\AbstractTest;

abstract class Person{
    
    abstract function work();


    public static function type()
    {
        return [
            (new Farmer())->goBus(),
            Teacher::class
        ];

    }

    public function goBus()
    {
        return 'on bus';
    }

    public function goBicycle()
    {
        return 'on bike';
    }

}