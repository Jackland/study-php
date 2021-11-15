<?php

namespace App\Components\View\Layouts;

interface LayoutInterface
{
    /**
     * 获取 layout 的参数
     * @return array
     */
    public function getParams(): array;
}
