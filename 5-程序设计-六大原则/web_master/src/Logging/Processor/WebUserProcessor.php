<?php

namespace App\Logging\Processor;

use Monolog\Processor\ProcessorInterface;

class WebUserProcessor implements ProcessorInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(array $records)
    {
        if (!isset($records['context']['ip'])) {
            $ip = app()->has('request')
                ? app()->get('request')->getUserIp()
                : null;
            $records['context']['ip'] = $ip ?: '0.0.0.0';
        }
        if (!isset($records['context']['customer_id'])) {
            $customerId = app()->has('session')
                ? (int)app()->get('session')->get('customer_id', 0)
                : 0;
            $records['context']['customer_id'] = $customerId;
        }
        return $records;
    }
}
