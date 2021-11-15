<?php

namespace App\Widgets;

use App\Enums\Country\Country;
use App\Models\Customer\Customer;
use Framework\Widget\Widget;

class VATToolTipWidget extends Widget
{
    /**
     * @var Customer
     */
    public $customer;

    /**
     * 是否展示vat
     * @var bool
     */
    public $is_show_vat = false;

    public function run()
    {
        if ($this->customer->country_id != Country::GERMANY) {
            return '';
        }

        if (!$this->customer->is_eu_vat_buyer) {
            return '';
        }

        return $this->getView()->render('@widgets/vat_tool_tip', [
            'vat' => $this->customer->vat,
            'is_show_vat' => $this->is_show_vat,
        ]);
    }
}
