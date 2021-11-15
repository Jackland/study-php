<?php

namespace Catalog\model\filter;

trait BaseFilter
{

    public $request;
    public $builder;

    public function filter($query, array $validated)
    {

        $this->builder = $query;

        foreach ($validated as $name => $value) {
            if (method_exists($this, $name) && $value) {
                call_user_func_array([$this, $name], array_filter([$value]));
            }
        }
        return $this->builder;
    }

}