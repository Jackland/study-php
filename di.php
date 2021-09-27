<?php
/**
 * Created by IOC.php.
 *
 * 通过实现一个工厂类的依赖注入

 * 依赖注入是从应用程序的角度在描述，可以把依赖注入，
 * 即：应用程序依赖容器创建并注入它所需要的外部资源
 *
 * User: fuyunnan
 * Date: 2021/6/17
 * Time: 10:53
 */


/**
 *思路
 *
 * 1，先定义一个 超能力生产激活的接口
 * 2，创建 x光 和 飓风超能力类
 * 3,创建一个超人类 并且约束 必须冲能力生产获取能力
 */


//定义一个接口的
interface SuperModuleInterface{
    //
    public function activate(array $target);

}

//x光超能力
class xPower implements SuperModuleInterface{

    public function activate(array $target)
    {
       echo 'get xPower'.implode(',',$target);
    }
}


//x光超能力
class bigWind implements SuperModuleInterface{

    public function activate(array $target)
    {
        echo 'get bigWind'.implode(',',$target);;
    }
}

class superMan{
    /**
     * @var SuperModuleInterface
     */
    private $module;

    public function __construct(SuperModuleInterface $module)
    {
        $this->module = $module;
    }

    public function main()
    {
        $this->module->activate([1,3,4]);
    }

}

$o = new superMan(new xPower());
$oc = new superMan(new bigWind());
$o->main();
$oc->main();