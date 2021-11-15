<?php

namespace Framework\DI;

use ReflectionException;

class Container extends \Illuminate\Container\Container
{
    use DependencyLoadTrait;

    /**
     * @inheritDoc
     */
    public function call($callback, array $parameters = [], $defaultMethod = null)
    {
        // 替换 BoundMethod 处理 model 等需要 load 的问题
        return BoundMethod::call($this, $callback, $parameters, $defaultMethod);
    }

    protected function resolveDependencies(array $dependencies)
    {
        foreach ($dependencies as $dependency) {
            try {
                $dependency->getClass();
            } catch (ReflectionException $exception) {
                static::dependencyTrySolve($dependency);
            }
        }
        return parent::resolveDependencies($dependencies);
    }
}
