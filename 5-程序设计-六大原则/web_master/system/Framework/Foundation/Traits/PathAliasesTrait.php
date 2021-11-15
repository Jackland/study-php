<?php

namespace Framework\Foundation\Traits;

use Framework\Aliases\Aliases;
use InvalidArgumentException;

trait PathAliasesTrait
{
    /**
     * @var Aliases
     */
    public $pathAliases;

    public function loadPathAliases(array $items)
    {
        if (!isset($items['@root'])) {
            throw new InvalidArgumentException('@root aliases must be set');
        }
        if (!isset($items['@runtime'])) {
            $items['@runtime'] = '@root/runtime';
        }
        if (!isset($items['@vendor'])) {
            $items['@vendor'] = '@root/vendor';
        }

        $this->instance('pathAliases', $this->pathAliases = new Aliases($items));
    }
}
