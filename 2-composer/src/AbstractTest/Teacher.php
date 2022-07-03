<?php
/**
 * Created by Study.php.
 * User: fuyunnan
 * Date: 2022/2/9
 * Time: 16:19
 */

namespace Acme\AbstractTest;

class Teacher extends Person
{
    public function work()
    {
        return '教书育人';
    }

    public function sleep()
    {
        return 'offices';
    }
}