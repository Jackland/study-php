<?php

namespace Framework\View;

/**
 * 视图引擎
 */
interface ViewRendererInterface
{
    /**
     * 渲染内容
     * @param ViewFactory $view
     * @param string $fullPath 绝对路径
     * @param string $viewPath 视图路径，相对路径
     * @param array $data 参数
     * @return string
     */
    public function render(ViewFactory $view, string $fullPath, string $viewPath, array $data): string;
}
