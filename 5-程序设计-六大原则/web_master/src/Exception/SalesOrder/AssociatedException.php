<?php

namespace App\Exception\SalesOrder;

use Framework\Exception\Exception;
use Throwable;

class AssociatedException extends Exception
{
    /**
     * @var int|mixed
     */
    public $salesOrderId = 0;

    /**
     * @var string
     */
    public $salesOrder = '';

    public function __construct($message = "", $code = 0, $salesOrderId = 0, $saleOrder = '', Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->salesOrderId = $salesOrderId;
        $this->salesOrder = $saleOrder;
    }

    /**
     * @return int|mixed
     */
    public function getSalesOrderId()
    {
        return $this->salesOrderId;
    }

    /**
     * @return mixed
     */
    public function getSalesOrder()
    {
        return $this->salesOrder;
    }
}
