<?php

namespace App\Exception;

use App\Repositories\Pay\PaymentReturnCodeRepository;
use Framework\Exception\Exception;
use Throwable;

class PaymentException extends Exception
{
    const ORDER_EXPIRED = 301;
    const LINE_OF_CREDIR_NOT_ENOUGH = 302;

    public $statusCode = 0;
    public $message = '支付错误';

    public $config;

    public function __construct($statusCode = 0, Throwable $previous = null)
    {
        $description = app(PaymentReturnCodeRepository::class)->getDescriptionByErrorCode($statusCode);
        parent::__construct($description, $statusCode, $previous);
    }
}
