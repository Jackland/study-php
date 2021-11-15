<?php

namespace Framework\Log\Processor;

use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;
use Psr\Log\LogLevel;
use Symfony\Component\VarExporter\VarExporter;

class WebServerProcessor implements ProcessorInterface
{
    const KEY = 'webServerVars';

    public $vars = [
        '_GET',
        '_POST',
        '_FILES',
        '_COOKIE',
        '_SESSION',
        '_SERVER',
    ];

    public $minLevel = Logger::ERROR;

    public function __construct($minLevel = LogLevel::ERROR, $vars = null)
    {
        if ($vars !== null && is_array($vars)) {
            $this->vars = $vars;
        }
        $this->minLevel = Logger::toMonologLevel($minLevel);
    }

    /**
     * @inheritDoc
     */
    public function __invoke(array $records)
    {
        if (!isset($records['context'][self::KEY])) {
            if ($records['level'] >= $this->minLevel) {
                $records['context'][self::KEY] = $this->getWebServerVarsStr($this->vars);
            }
        } else {
            $records['context'][self::KEY] = $this->getWebServerVarsStr($records['context'][self::KEY]);
        }

        if (isset($records['context'][self::KEY]) && $records['context'][self::KEY]) {
            $records['context'][self::KEY] .= "\n";
        }

        return $records;
    }

    protected function getWebServerVarsStr($vars)
    {
        $context = [];
        foreach ($GLOBALS as $key => $value) {
            if (in_array($key, $vars)) {
                $context[$key] = $value;
            }
        }

        $result = [];
        foreach ($context as $key => $value) {
            $result[] = "\${$key} = " . VarExporter::export($value);
        }

        return implode("\n", $result);
    }
}
