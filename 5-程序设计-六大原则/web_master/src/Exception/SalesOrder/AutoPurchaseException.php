<?php

namespace App\Exception\SalesOrder;

use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Exception\SalesOrder\Enums\AutoPurchaseCode;
use Framework\Exception\Exception;
use Illuminate\Support\Str;
use Throwable;

class AutoPurchaseException extends Exception
{
    /**
     * @var int|mixed
     */
    public $sales_order_status = 0;

    /**
     * @var string
     */
    public $sku = '';

    public function __construct($code = '000', $sku = '', $salesOrderStatus = 0, Throwable $previous = null)
    {
        $this->sales_order_status = $salesOrderStatus;
        $this->sku = $sku;

        parent::__construct($this->getCodeMessage($code), $code, $previous);
    }

    /**
     * @param string $code
     * @return string
     */
    private function getCodeMessage(string $code): string
    {
        $message = AutoPurchaseCode::getViewItems()[$code];

        if (Str::contains($message, '#replace_status#')) {
            $statusName = CustomerSalesOrderStatus::getViewItems()[$this->sales_order_status] ?? 'error';
            $message = str_replace('#replace_status#', $statusName, $message);
        }

        if (Str::contains($message, '#replace_sku#')) {
            $message = str_replace('#replace_sku#', $this->sku ?: 'error', $message);
        }

        return $message;
    }
}
