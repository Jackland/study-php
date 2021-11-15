<?php

namespace Framework\View;

use Framework\View\Exception\ViewFileNotFoundException;

/**
 * 视图 Finder
 */
interface ViewFinderInterface
{
    /**
     * 查找视图，获取视图绝对路径
     * @param string $view
     * @return array [$fullPath, $viewPath]
     * @throws ViewFileNotFoundException
     */
    public function find(string $view): array;

    /**
     * 检查视图是否存在
     * @param string $view
     * @return bool
     */
    public function exist(string $view): bool;
}
