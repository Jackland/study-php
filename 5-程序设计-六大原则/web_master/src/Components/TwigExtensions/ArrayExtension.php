<?php

namespace App\Components\TwigExtensions;

use InvalidArgumentException;

class ArrayExtension extends AbsTwigExtension
{
    protected $filters = [
        'array_filter',
    ];

    protected $functions = [
        'array_filter'
    ];

    public function array_filter($arr, $config = [])
    {
        $config = array_merge([
            'attribute' => '', // $arr 循环的 $item 下的 key，为空时使用 item
            'attributeFn' => null, // 对 attribute 的值进行某个 fn 处理，比如 count/intval 等
            'operator' => '!==', // 比较
            'value' => null, // 比较值
            'reIndexKey' => true, // 是否重新构建索引
        ], $config);

        $arr = array_filter($arr, function ($item) use ($config) {
            $value = $config['attribute'] ? $item[$config['attribute']] : $item;
            if ($config['attributeFn']) {
                $value = call_user_func($config['attributeFn'], $value);
            }
            return $this->compare($value, $config['value'], $config['operator']);
        });
        if ($config['reIndexKey']) {
            return array_values($arr);
        }
        return $arr;
    }

    private function compare($value1, $value2, $operator): bool
    {
        switch ($operator) {
            case '==':
                return $value1 == $value2;
            case '===':
                return $value1 === $value2;
            case '!=':
                return $value1 != $value2;
            case '!==':
                return $value1 !== $value2;
            case '>':
                return $value1 > $value2;
            case '>=':
                return $value1 >= $value2;
            case '<':
                return $value1 < $value2;
            case '<=':
                return $value1 <= $value2;
            default:
                throw new InvalidArgumentException('operator not support: ' . $operator);
        }
    }
}
