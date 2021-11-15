<?php

namespace Framework\DI;

use ReflectionException;

class BoundMethod extends \Illuminate\Container\BoundMethod
{
    use DependencyLoadTrait;

    protected static function addDependencyForCallParameter($container, $parameter, array &$parameters, &$dependencies)
    {
        try {
            static::addDependencyForCallParameterChange($container, $parameter, $parameters, $dependencies);
        } catch (ReflectionException $e) {
            static::dependencyTrySolve($parameter);
            static::addDependencyForCallParameterChange($container, $parameter, $parameters, $dependencies);
        }
    }

    protected static function addDependencyForCallParameterChange($container, $parameter, array &$parameters, &$dependencies)
    {
        if (array_key_exists($parameter->name, $parameters)) {
            $dependencies[] = $parameters[$parameter->name];

            unset($parameters[$parameter->name]);
        } elseif ($parameter->getClass() && array_key_exists($parameter->getClass()->name, $parameters)) {
            $dependencies[] = $parameters[$parameter->getClass()->name];

            unset($parameters[$parameter->getClass()->name]);
        } elseif ($parameter->getClass()) {
            $dependencies[] = $container->make($parameter->getClass()->name);
        }
        // 修改移除存在默认值时 dependencies 中追加默认值的问题
        // 原因：原系统中 load->controller() 中的参数为可以传入字符串或数组，且未命名，导致如果 route 的参数上有默认参数时不能正确赋值
        // 比如 route: form($product_id=null), load->controller('form', '21133'), 得到的 $product_id 将任然还是 null
        // 因此移除获取默认参数
        /*elseif ($parameter->isDefaultValueAvailable()) {
            $dependencies[] = $parameter->getDefaultValue();
        }*/
    }
}
