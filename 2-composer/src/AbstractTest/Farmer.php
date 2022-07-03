<?php
/**
 * Created by Study.php.
 * User: fuyunnan
 * Date: 2022/2/9
 * Time: 16:19
 */

namespace Acme\AbstractTest;

class Farmer extends Person
{
    public function work()
    {
        return '种地';
    }

    public function goBus()
    {
        return '农名坐公交赶集';
    }

    public function sleep()
    {
        return '田间地头';
    }
}