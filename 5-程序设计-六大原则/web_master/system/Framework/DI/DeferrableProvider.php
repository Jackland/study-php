<?php

namespace Framework\DI;

interface DeferrableProvider
{
    /**
     * defer 加载时使用
     * @return array
     */
    public function provides();
}
