<?php

namespace Framework\Model\Eloquent\Relations;

use Framework\Model\EloquentModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;

class Joiner extends \Sofa\Eloquence\Relations\Joiner
{
    /**
     * @inheritDoc
     */
    protected function joinSegment(Model $parent, $segment, $type)
    {
        // 修改增加 alias 的支持
        list($alias, $segment) = $this->getAliasAndTableName($segment);
        // end

        $relation = $parent->{$segment}();
        $related = $relation->getRelated();
        // 修改增加 alias 的支持
        if ($alias) {
            $related->setAlias($alias);
        }
        // end
        $table = $related->getTable();

        if ($relation instanceof BelongsToMany || $relation instanceof HasManyThrough) {
            $this->joinIntermediate($parent, $relation, $type);
        }

        if (!$this->alreadyJoined($join = $this->getJoinClause($parent, $relation, $table, $type))) {
            $this->query->joins[] = $join;
        }

        return $related;
    }

    /**
     * @inheritDoc
     */
    protected function getJoinClause(Model $parent, Relation $relation, $table, $type)
    {
        /** @var EloquentModel $model */
        $model = $relation->getModel();
        $alias = $model->getAlias();
        if ($alias) {
            $table .= ' as ' . $alias;
        }

        return parent::getJoinClause($parent, $relation, $table, $type);
    }

    /**
     * @inheritDoc
     */
    protected function getJoinKeys(Relation $relation)
    {
        list($fk, $pk) = parent::getJoinKeys($relation);

        if ($relation instanceof HasOneOrMany) {
            /** @var EloquentModel $related */
            $related = $relation->getRelated();
            if ($related->getAlias()) {
                $fk = str_replace($related->getTable(), $related->getAlias(), $fk);
            }
        }

        return [$fk, $pk];
    }

    /**
     * 提取 alias 和 table
     * @param $table
     * @return array
     */
    private function getAliasAndTableName($table)
    {
        $alias = '';
        if (strpos($table, ' as ') !== false) {
            list($table, $alias) = explode(' as ', $table);
        }
        return [$alias, $table];
    }
}
