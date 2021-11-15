<?php

namespace App\Components\Rules;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;

/**
 * 手机号校验
 */
class CellphoneRule1 implements Rule, ExtendableInterface
{
    protected $message;

    /**
     * @inheritDoc
     */
    public function passes($attribute, $value)
    {
        if (!preg_match('/^1\d{10}$/', $value)) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function message()
    {
        return $this->message ?: static::replaceMessageWithParameters(app(Translator::class)->trans('validation.' . static::name()), []);
    }

    /**
     * @inheritDoc
     */
    public static function name(): string
    {
        return 'cellphone1';
    }

    /**
     * @inheritDoc
     */
    public static function validate(string $attribute, $value, array $parameters, Validator $validator): bool
    {
        $self = new static();
        return $self->passes($attribute, $value);
    }

    /**
     * @inheritDoc
     */
    public static function replacerMessage(string $message, string $attribute, string $rule, array $parameters, Validator $validator): string
    {
        return static::replaceMessageWithParameters($message, $parameters);
    }

    /**
     * 替换翻译文件中的内容
     * @param $message
     * @param array $parameters
     * @return string
     */
    protected static function replaceMessageWithParameters($message, array $parameters)
    {
        return $message;
    }
}
