<?php
/**
 * Created by MyClass.php.
 * User: fuyunnan
 * Date: 2021/9/27
 * Time: 13:34
 */

namespace App;
use Composer\Script\Event;

class MyClass
{

    public static function postUpdate(Event $event)
    {
        echo 111;
        echo PHP_EOL;
//        $composer = $event->getComposer();
        // do stuff
    }

    public static function postPackageInstall(Event $event)
    {
        echo 222;
        echo PHP_EOL;
//        $installedPackage = $event->getOperation()->getPackage();
        // do stuff
    }

    public static function warmCache(Event $event)
    {
//        $installedPackage = $event->getComposer();
//        var_dump($installedPackage);
        echo 333;
        echo PHP_EOL;
        // make cache toasty
    }
    
}