<?php

namespace Framework\Model;

abstract class BaseValidateModel
{
    public function __construct()
    {
    }

    /**
     * 加载数据到当前对象的属性上
     * @param array $params
     */
    public function loadAttributes(array $params)
    {
        $rules = $this->getRules();
        foreach ($params as $key => $value) {
            if (array_key_exists($key, $rules) && property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * 校验在 rules 中定义的，已经赋值到当前对象上的数据
     * @return \Illuminate\Contracts\Validation\Factory|\Illuminate\Contracts\Validation\Validator
     */
    public function validateAttributes()
    {
        $rules = $this->getRules();
        $attributes = [];
        foreach ($rules as $attribute => $rule) {
            if (!property_exists($this, $attribute)) {
                // 跳过不存在的属性，确保rules中存在key为'aa.bb'形式的可以正常校验
                continue;
            }
            $attributes[$attribute] = $this->{$attribute};
        }
        return validator($attributes, $this->getRules(), $this->getRuleMessages(), $this->getAttributeLabels());
    }

    /**
     * 校验规则
     * @return array
     */
    abstract protected function getRules(): array;

    /**
     * 自定义校验错误提示
     * @return array
     */
    protected function getRuleMessages(): array
    {
        return [];
    }

    /**
     * 自定义校验错误提示中的属性字段名
     * @return array
     */
    protected function getAttributeLabels(): array
    {
        return [];
    }
}
