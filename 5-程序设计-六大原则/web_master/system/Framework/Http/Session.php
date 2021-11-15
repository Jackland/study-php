<?php

namespace Framework\Http;

/**
 * @deprecated
 * @mixin \Framework\Session\Session
 */
class Session
{
    protected $newSession;

    public function __construct()
    {
        $this->newSession = app('session');
    }

    public function __call($name, $arguments)
    {
        return $this->newSession->{$name}(...$arguments);
    }
}
