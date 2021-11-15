<?php

namespace Framework\Model\Traits;

use Illuminate\Support\Str;

trait RelationsAliasTrait
{
    /**
     * 别名
     * @var string
     */
    protected $alias;

    /**
     * 设置别名
     * @param $alias
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    /**
     * 获取别名
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * @inheritDoc
     */
    public function qualifyColumn($column)
    {
        if (Str::contains($column, '.')) {
            return $column;
        }

        // 修改默认先取别名，否则用表名
        return ($this->getAlias() ?: $this->getTable()) . '.' . $column;
    }
}
