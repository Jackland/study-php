<?php

namespace Framework\Log\Processor;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\VarExporter\VarExporter;

class VarDumperProcessor implements ProcessorInterface
{
    const KEY = 'varDumper';

    /**
     * @inheritDoc
     */
    public function __invoke(array $records)
    {
        if (isset($records['context'][self::KEY]) && $records['context'][self::KEY]) {
            $records['context'][self::KEY] = $this->processFormat((array)$records['context'][self::KEY]) . "\n";
        }

        return $records;
    }

    protected function processFormat($context)
    {
        $formatter = new NormalizerFormatter();
        $context = $formatter->format($context);

        return '$varDumper = ' . VarExporter::export($context);
    }
}
