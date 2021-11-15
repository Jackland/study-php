<?php

namespace Framework\Model\Traits;

use Framework\Exception\InvalidArgumentException;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\Validator;
use ReflectionClass;

trait FormModeTrait
{
    /**
     * @var false|array
     */
    public $attributes = false;
    /**
     * @var false|array
     */
    public $attributeLabels = false;
    /**
     * @var false|array
     */
    public $attributeHints = false;
    /**
     * @var false|array
     */
    public $attributePlaceHolders = false;
    /**
     * 执行 validate() 之后，在有错误时不为空
     * @var false|Validator
     */
    public $validator = false;

    /**
     * 初始化数据
     */
    public function initFormAttributes()
    {
        $this->attributes = $this->collectAttributes();
        $this->attributeLabels = $this->attributeLabels();
    }

    /**
     * 载入数据
     * @param $data
     * @return bool
     */
    public function load($data)
    {
        // 考虑增加 data 命名形式
        // 考虑增加 安全属性 的判断
        foreach ($data as $attribute => $value) {
            if (in_array($attribute, $this->attributes)) {
                $this->{$attribute} = $value;
            }
        }

        return true;
    }

    /**
     * @param $attribute
     * @return mixed
     */
    public function getAttributeValue($attribute)
    {
        if ($this->attributes === false) {
            $this->attributes = $this->collectAttributes();
        }

        if (!in_array($attribute, $this->attributes)) {
            $class = get_class($this);
            throw new InvalidArgumentException("Undefined property: \"$class::$attribute\".");
        }

        return $this->{$attribute};
    }

    /**
     * @return array
     */
    protected function collectAttributes()
    {
        $attributes = [];

        $class = new ReflectionClass($this);
        foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic()) {
                $attributes[] = $property->getName();
            }
        }

        return $attributes;
    }

    /**
     * @return array
     */
    public function attributeLabels()
    {
        return [];
    }

    /**
     * @param $attribute
     * @return string
     */
    public function getAttributeLabel($attribute)
    {
        if ($this->attributeLabels === false) {
            $this->attributeLabels = $this->attributeLabels();
        }

        return $this->attributesLabels[$attribute] ?? $this->generateAttributeLabel($attribute);
    }

    /**
     * @param $attribute
     * @return string
     */
    protected function generateAttributeLabel($attribute)
    {
        return ucwords(str_replace(['-', '_', '.'], ' ', $attribute));
    }

    /**
     * @return array
     */
    public function attributeHints()
    {
        return [];
    }

    /**
     * @param $attribute
     * @return string
     */
    public function getAttributeHint($attribute)
    {
        if ($this->attributeHints === false) {
            $this->attributeHints = $this->attributeHints();
        }

        return $this->attributeHints[$attribute] ?? '';
    }

    /**
     * @return array
     */
    public function attributePlaceHolders()
    {
        return $this->attributeLabels();
    }

    /**
     * @param $attribute
     * @return string
     */
    public function getAttributePlaceholder($attribute)
    {
        if ($this->attributePlaceHolders === false) {
            $this->attributePlaceHolders = $this->attributePlaceHolders();
        }

        return $this->attributePlaceHolders[$attribute] ?? '';
    }

    /**
     * @return array
     */
    public function attributeRules()
    {
        return [];
    }

    /**
     * @return array
     */
    public function attributeRuleMessages()
    {
        return [];
    }

    /**
     * @param null $attribute
     * @return string|null
     */
    public function getFirstError($attribute = null)
    {
        if ($this->validator) {
            return $this->validator->errors()->first($attribute);
        }

        return null;
    }

    /**
     * @return bool
     */
    public function validate()
    {
        $values = [];
        foreach ($this->attributes as $attribute) {
            $values = $this->getAttributeValue($attribute);
        }
        if (!$values) {
            return true;
        }
        $validator = app()->get(Factory::class)->make($values, $this->attributeRules(), $this->attributeRuleMessages(), $this->attributeLabels);
        if ($validator->fails()) {
            $this->validator = $validator;
            return false;
        }
        return true;
    }
}
