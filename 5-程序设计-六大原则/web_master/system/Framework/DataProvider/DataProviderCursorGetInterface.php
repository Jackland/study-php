<?php

namespace Framework\DataProvider;

interface DataProviderCursorGetInterface
{
    /**
     * 获取 cursor 的 list
     * 针对大数据量时可以节省内存使用
     * @return \Generator|mixed
     */
    public function getListWithCursor();
}
