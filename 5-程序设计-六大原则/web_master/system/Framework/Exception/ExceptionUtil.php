<?php

namespace Framework\Exception;

use Throwable;

class ExceptionUtil
{
    /**
     * @param $e
     * @return \Exception
     */
    public static function coverThrowable2Exception($e)
    {
        if ($e instanceof \Exception) {
            throw $e;
        }

        if ($e instanceof \ParseError) {
            $severity = E_PARSE;
        } elseif ($e instanceof \TypeError) {
            $severity = E_RECOVERABLE_ERROR;
        } else {
            $severity = E_ERROR;
        }

        if ($e instanceof Throwable) {
            $newException = new \ErrorException(
                $e->getMessage(),
                $e->getCode(),
                $severity,
                $e->getFile(),
                $e->getLine(),
                $e->getPrevious()
            );

            $traceReflector = new \ReflectionProperty('Exception', 'trace');
            $traceReflector->setAccessible(true);
            $traceReflector->setValue($newException, $e->getTrace());
            return $newException;
        }

        return $e;
    }
}
