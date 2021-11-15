<?php

namespace Framework\Helper;

use Framework\Helper\Modifier\ModifierInterface;
use Framework\Helper\Modifier\ReverseBlockMerge;
use Illuminate\Support\Arr;

class ArrayHelper extends Arr
{
    /**
     * 移除某个 key，并返回该 key 的值
     * 例如：
     * // $array = ['type' => 'A', 'options' => [1, 2]];
     * $type = ArrayHelper::remove($array, 'type');
     * // $type = 'A';
     * // $array = ['options' => [1, 2]];
     *
     * @param array $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function remove(array &$array, string $key, $default = null)
    {
        if (array_key_exists($key, $array)) {
            $value = $array[$key];
            unset($array[$key]);

            return $value;
        }

        return $default;
    }

    /**
     * 是否是数组 或 [[\Traversable]]
     *
     * @param mixed $var
     * @return bool
     * @see http://php.net/manual/en/function.is-array.php
     */
    public static function isTraversable($var): bool
    {
        return is_iterable($var);
    }

    /**
     * 检查值是否存在
     *
     * @param mixed $needle 值
     * @param iterable $haystack 数组
     * @param bool $strict 是否用 === 比较
     * @return bool
     * @see http://php.net/manual/en/function.in-array.php
     */
    public static function isIn($needle, iterable $haystack, bool $strict = false): bool
    {
        if (is_array($haystack)) {
            return in_array($needle, $haystack, $strict);
        }

        foreach ($haystack as $value) {
            if ($needle == $value && (!$strict || $needle === $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieves the value of an array element or object property with the given key or property name.
     * If the key does not exist in the array or object, the default value will be returned instead.
     *
     * The key may be specified in a dot format to retrieve the value of a sub-array or the property
     * of an embedded object. In particular, if the key is `x.y.z`, then the returned value would
     * be `$array['x']['y']['z']` or `$array->x->y->z` (if `$array` is an object). If `$array['x']`
     * or `$array->x` is neither an array nor an object, the default value will be returned.
     * Note that if the array already has an element `x.y.z`, then its value will be returned
     * instead of going through the sub-arrays. So it is better to be done specifying an array of key names
     * like `['x', 'y', 'z']`.
     *
     * Below are some usage examples,
     *
     * ```php
     * // working with array
     * $username = \Yiisoft\Arrays\ArrayHelper::getValue($_POST, 'username');
     * // working with object
     * $username = \Yiisoft\Arrays\ArrayHelper::getValue($user, 'username');
     * // working with anonymous function
     * $fullName = \Yiisoft\Arrays\ArrayHelper::getValue($user, function ($user, $defaultValue) {
     *     return $user->firstName . ' ' . $user->lastName;
     * });
     * // using dot format to retrieve the property of embedded object
     * $street = \Yiisoft\Arrays\ArrayHelper::getValue($users, 'address.street');
     * // using an array of keys to retrieve the value
     * $value = \Yiisoft\Arrays\ArrayHelper::getValue($versions, ['1.0', 'date']);
     * ```
     *
     * @param array|object $array array or object to extract value from
     * @param string|\Closure|array $key key name of the array element, an array of keys or property name of the object,
     * or an anonymous function returning the value. The anonymous function signature should be:
     * `function($array, $defaultValue)`.
     * @param mixed $default the default value to be returned if the specified array key does not exist. Not used when
     * getting value from an object.
     * @return mixed the value of the element if found, default value otherwise
     */
    public static function getValue($array, $key, $default = null)
    {
        if ($key instanceof \Closure) {
            return $key($array, $default);
        }

        if (!is_array($array) && !is_object($array)) {
            throw new \InvalidArgumentException(
                'getValue() can not get value from ' . gettype($array) . '. Only array and object are supported.'
            );
        }

        if (is_array($key)) {
            $lastKey = array_pop($key);
            foreach ($key as $keyPart) {
                $array = static::getValue($array, $keyPart, $default);
            }
            return static::getValue($array, $lastKey, $default);
        }

        if (is_array($array) && array_key_exists((string)$key, $array)) {
            return $array[$key];
        }

        if (strpos($key, '.') !== false) {
            foreach (explode('.', $key) as $part) {
                if (is_array($array)) {
                    if (!array_key_exists($part, $array)) {
                        return $default;
                    }
                    $array = $array[$part];
                } elseif (is_object($array)) {
                    if (!property_exists($array, $part) && empty($array)) {
                        return $default;
                    }
                    $array = $array->$part;
                }
            }

            return $array;
        }

        if (is_object($array)) {
            // this is expected to fail if the property does not exist, or __get() is not implemented
            // it is not reliably possible to check whether a property is accessible beforehand
            return $array->$key;
        }

        return $default;
    }

    /**
     * Merges two or more arrays into one recursively.
     * If each array has an element with the same string key value, the latter
     * will overwrite the former (different from array_merge_recursive).
     * Recursive merging will be conducted if both arrays have an element of array
     * type and are having the same key.
     * For integer-keyed elements, the elements from the latter array will
     * be appended to the former array.
     * You can use modifiers to change merging result.
     * @param array $args arrays to be merged
     * @return array the merged array (the original arrays are not changed.)
     */
    public static function merge(...$args): array
    {
        $lastArray = end($args);
        if (isset($lastArray[ReverseBlockMerge::class]) && $lastArray[ReverseBlockMerge::class] instanceof ReverseBlockMerge) {
            reset($args);
            return self::applyModifiers(self::performReverseBlockMerge(...$args));
        }

        return self::applyModifiers(self::performMerge(...$args));
    }

    private static function performMerge(...$args): array
    {
        $res = array_shift($args) ?: [];
        while (!empty($args)) {
            foreach (array_shift($args) as $k => $v) {
                if (is_int($k)) {
                    if (array_key_exists($k, $res) && $res[$k] !== $v) {
                        $res[] = $v;
                    } else {
                        $res[$k] = $v;
                    }
                } elseif (is_array($v) && isset($res[$k]) && is_array($res[$k])) {
                    $res[$k] = self::performMerge($res[$k], $v);
                } else {
                    $res[$k] = $v;
                }
            }
        }

        return $res;
    }

    private static function performReverseBlockMerge(...$args): array
    {
        $res = array_pop($args) ?: [];
        while (!empty($args)) {
            foreach (array_pop($args) as $k => $v) {
                if (is_int($k)) {
                    if (array_key_exists($k, $res) && $res[$k] !== $v) {
                        $res[] = $v;
                    } else {
                        $res[$k] = $v;
                    }
                } elseif (is_array($v) && isset($res[$k]) && is_array($res[$k])) {
                    $res[$k] = self::performReverseBlockMerge($v, $res[$k]);
                } elseif (!isset($res[$k])) {
                    $res[$k] = $v;
                }
            }
        }

        return $res;
    }

    public static function applyModifiers(array $data): array
    {
        $modifiers = [];
        foreach ($data as $k => $v) {
            if ($v instanceof ModifierInterface) {
                $modifiers[$k] = $v;
                unset($data[$k]);
            } elseif (is_array($v)) {
                $data[$k] = self::applyModifiers($v);
            }
        }
        foreach ($modifiers as $key => $modifier) {
            $data = $modifier->apply($data, $key);
        }
        return $data;
    }
}
