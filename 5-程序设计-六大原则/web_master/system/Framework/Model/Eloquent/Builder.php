<?php

namespace Framework\Model\Eloquent;

use Framework\Model\EloquentModel;

class Builder extends \Sofa\Eloquence\Builder
{
    /**
     * 设置 AS 别名
     * @param $alias
     * @return $this
     */
    public function alias($alias)
    {
        $this->getModel()->setAlias($alias);
        $this->from($this->model->getTable() . ' as ' . $alias);

        return $this;
    }

    /**
     * @return static|\Illuminate\Database\Eloquent\Model|EloquentModel
     */
    public function getModel()
    {
        // 未做修改，仅增加注释用于代码提示
        return parent::getModel();
    }

    /**
     * @return \Illuminate\Database\Query\Builder|\Framework\Model\Eloquent\Query\Builder
     * @see EloquentModel::newBaseQueryBuilder()
     */
    public function getQuery()
    {
        // 未做修改，仅增加注释用于代码提示
        return parent::getQuery();
    }

    /**
     * eloquent when 的简单实现
     * @param $columns
     * @param string $boolean
     * @return $this
     */
    public function filterWhere($columns, $boolean = 'and')
    {
        if (!isset($columns[0])) {
            // 一维数组键值对形式：['key1' => 'value', 'key2' => 'value']
            $result = [];
            foreach ($columns as $key => $value) {
                $result[] = [$key, '=', $value];
            }
            $columns = $result;
        } else {
            if (!is_array($columns[0])) {
                // 一维数组单个 filter 形式：['key1', 'value']
                $columns = [$columns];
            }
        }
        // 二维数组形式：[['key1', 'value'], ['key2', '>', 'value']]
        foreach ($columns as $column) {
            $attribute = $column[0];
            if (count($column) == 2) {
                $operator = '=';
                $value = $column[1];
            } elseif (count($column) == 3) {
                $operator = $column[1];
                $value = $column[2];
            } else {
                // 配置错误时 value 固定为空
                $operator = '=';
                $value = null;
            }
            $checkValue = $value;
            if (strtolower($operator) === 'like') {
                $checkValue = trim($value, '%');
            }
            $this->when(!$this->isEmpty($checkValue), function (Builder $query) use ($attribute, $operator, $value, $boolean) {
                return $query->where($attribute, $operator, $value, $boolean);
            });
        }
        return $this;
    }

    /**
     * @param $value
     * @return bool
     */
    protected function isEmpty($value)
    {
        return $value === '' || $value === [] || $value === null || is_string($value) && trim($value) === '';
    }
}
