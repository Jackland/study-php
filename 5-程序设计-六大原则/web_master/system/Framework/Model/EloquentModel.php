<?php

namespace Framework\Model;

use Framework\Model\Eloquent\Builder;
use Framework\Model\Eloquent\Query\Builder as QueryBuilder;
use Framework\Model\Traits\ConnectionTrait;
use Framework\Model\Traits\EnsureTrait;
use Framework\Model\Traits\RelationsAliasTrait;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Sofa\Eloquence\Eloquence;

/**
 * @method static Builder|static newQuery()
 * @method static Builder|static newModelQuery()
 */
class EloquentModel extends Model
{
    /**
     * @see https://github.com/jarektkaczyk/eloquence
     */
    use Eloquence;
    use RelationsAliasTrait;
    use ConnectionTrait;
    use EnsureTrait;

    /**
     * 表名前缀
     * @var string
     */
    protected $prefix = '';

    /**
     * 默认禁止更新timestamp
     * @var bool
     */
    public $timestamps = false;

    /**
     * @inheritDoc
     */
    public function getTable()
    {
        // 修改默认表明为下划线不带复数形式
        return $this->table ?? ($this->prefix . Str::snake(class_basename($this)));
    }

    /**
     * @inheritDoc
     */
    public function newEloquentBuilder($query)
    {
        // 替换 Builder 实现
        return new Builder($query);
    }

    /**
     * @see newEloquentBuilder
     * @return Builder|EloquentBuilder
     */
    public static function query()
    {
        // 无修改，仅为增加注释用于提示
        return parent::query();
    }

    /**
     * @inheritDoc
     */
    protected function newBaseQueryBuilder()
    {
        $conn = $this->getConnection();

        $grammar = $conn->getQueryGrammar();

        // 替换 Query\Builder 的实现
        return new QueryBuilder($conn, $grammar, $conn->getPostProcessor());
    }
}
