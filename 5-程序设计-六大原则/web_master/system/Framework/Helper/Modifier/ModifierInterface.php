<?php

namespace Framework\Helper\Modifier;

/**
 * Interface ModifierInterface
 */
interface ModifierInterface
{
    public function apply(array $data, $key): array;
}
