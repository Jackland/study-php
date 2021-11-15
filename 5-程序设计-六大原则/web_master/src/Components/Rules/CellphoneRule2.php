<?php

namespace App\Components\Rules;

/**
 * 手机号校验
 */
class CellphoneRule2 extends CellphoneRule1
{
    /**
     * @inheritDoc
     */
    public function passes($attribute, $value)
    {
        if (!preg_match('/^1[345789]{1}\d{9}$/', $value)) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public static function name(): string
    {
        return 'cellphone2';
    }
}
