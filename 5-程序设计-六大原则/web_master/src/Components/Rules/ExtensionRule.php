<?php

namespace App\Components\Rules;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ExtensionRule implements Rule, ExtendableInterface
{
    protected $extensions;
    protected $message;

    public function __construct($extensions = [])
    {
        $this->extensions = (array)$extensions;
    }

    /**
     * @inheritDoc
     */
    public function passes($attribute, $value)
    {
        if (!$value instanceof UploadedFile) {
            $this->message = $attribute . ' must be File';
            return false;
        }
        return in_array($value->getClientOriginalExtension(), $this->extensions);
    }

    /**
     * @inheritDoc
     */
    public function message()
    {
        return $this->message ?: static::replaceMessageWithParameters(app(Translator::class)->trans('validation.' . static::name()), $this->extensions);
    }

    /**
     * @inheritDoc
     */
    public static function name(): string
    {
        return 'extension';
    }

    /**
     * @inheritDoc
     */
    public static function validate(string $attribute, $value, array $parameters, Validator $validator): bool
    {
        $self = new static($parameters);
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
        return strtr($message, [
            ':values' => implode(',', $parameters),
        ]);
    }
}
