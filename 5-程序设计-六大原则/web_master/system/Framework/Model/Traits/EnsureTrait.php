<?php

namespace Framework\Model\Traits;

trait EnsureTrait
{
    /**
     * @param $modelOrId
     * @return static
     */
    public static function ensure($modelOrId)
    {
        if ($modelOrId instanceof static) {
            return $modelOrId;
        }
        if (
            is_int($modelOrId)
            || (is_numeric($modelOrId) && (int)$modelOrId == $modelOrId)
        ) {
            return static::findOrFail($modelOrId);
        }

        throw new \InvalidArgumentException('modelOrId invalid');
    }
}
