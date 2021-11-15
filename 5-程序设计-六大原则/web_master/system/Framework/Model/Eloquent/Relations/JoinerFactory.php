<?php

namespace Framework\Model\Eloquent\Relations;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;

class JoinerFactory extends \Sofa\Eloquence\Relations\JoinerFactory
{
    /**
     * @inheritDoc
     */
    public static function make($query, Model $model = null)
    {
        if ($query instanceof EloquentBuilder) {
            $model = $query->getModel();
            $query = $query->getQuery();
        }

        // 替换 Joiner 的实现
        return new Joiner($query, $model);
    }
}
