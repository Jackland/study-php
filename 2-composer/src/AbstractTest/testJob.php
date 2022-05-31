<?php
/**
 * Created by testJob.php.
 * User: fuyunnan
 * Date: 2022/5/27
 * Time: 15:57
 */

namespace Acme\AbstractTest;


class testJob extends baseDownloadJob
{
    public $maxTime = 100;
    public $isOpen = true;


    /**
     *2022 5.27
     * 当你看见这个的时候你应该懂的
     * 抽象类或者类之间的继承
     *
     * 子类是可以重写父类的受保护或者公有的属性！！！
     * 可以当作子类实现了父类的功能 ！！！
     *
     */


    public function index()
    {
        $this->debugUsage();

        echo $this->test();
        return 111;

    }
}