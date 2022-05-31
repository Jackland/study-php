<?php
/**
 * Created by baseDownloadJob.php.
 * User: fuyunnan
 * Date: 2022/5/27
 * Time: 15:58
 */

namespace Acme\AbstractTest;


abstract class baseDownloadJob extends baseJob
{

    use DebugUsageTrait;

    protected $isOpen = false;

    public function age()
    {
        return 111;
    }


    public function isDebugUsageEnable()
    {
        return $this->isOpen;
        
    }
}