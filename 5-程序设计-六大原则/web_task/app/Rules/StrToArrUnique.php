<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class StrToArrUnique implements Rule
{
    /**
     * 字符串转换为数组后唯一校验（英文逗号间隔）
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $arr = explode(',', $value);
        if (count($arr) != count(array_unique($arr))) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return ':attribute is malformed or not unique.';
    }
}
