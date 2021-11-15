<?php

namespace Framework\View\Traits;

use Framework\Exception\InvalidConfigException;
use Framework\View\Util;

/**
 * 视图 layout 相关功能
 */
trait ViewLayoutTrait
{
    private $layoutBasePath = 'layouts';
    private $layout;
    private $layoutData = [];

    /**
     * 渲染视图时附带 layout
     * @param string $layout
     * @param array $data
     * @return ViewLayoutTrait
     */
    public function withLayout(string $layout, $data = [])
    {
        $new = clone $this;

        $new->layout = $layout;
        if ($data) {
            $this->setDefaultLayoutData([$layout => $data]);
        }

        return $new;
    }

    /**
     * 获取 layout 的路径
     * @return string
     */
    public function getLayoutPath()
    {
        if (!$this->layout) {
            return '';
        }
        return Util::buildPath($this->layoutBasePath, $this->layout);
    }

    /**
     * 设置默认的 layout 配置，可供 withLayout 时单独传递 layout
     * @param $data
     */
    public function setDefaultLayoutData($data)
    {
        foreach ($data as $layout => $layoutData) {
            if (isset($this->layoutData[$layout])) {
                $layoutData = array_merge($this->layoutData[$layout], $layoutData);
            }
            $this->layoutData[$layout] = $layoutData;
        }
    }

    /**
     * 根据 layout 获取 layout 的数据
     * @param $layout
     * @return array
     * @throws InvalidConfigException
     */
    public function getLayoutDataWithLayout($layout)
    {
        if (!isset($this->layoutData[$layout])) {
            return [];
        }
        $data = $this->layoutData[$layout];
        if (is_callable($data)) {
            $data = call_user_func($data);
        }
        if (is_array($data)) {
            return $data;
        }
        throw new InvalidConfigException();
    }

    /**
     * 获取当前的 layout 的数据
     * @return array
     * @throws InvalidConfigException
     */
    public function getCurrentLayoutData()
    {
        return $this->getLayoutDataWithLayout($this->layout);
    }
}
