<?php

namespace App\Components\Rules;

use Illuminate\Contracts\Validation\Validator;

interface ExtendableInterface
{
    /**
     * 名称
     * @return string
     */
    public static function name(): string;

    /**
     * 执行验证
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @param Validator $validator
     * @return bool
     */
    public static function validate(string $attribute, $value, array $parameters, Validator $validator): bool;

    /**
     * 替换翻译
     * @param string $message
     * @param string $attribute
     * @param string $rule
     * @param array $parameters
     * @param Validator $validator
     * @return string
     */
    public static function replacerMessage(string $message, string $attribute, string $rule, array $parameters, Validator $validator): string;
}
