<?php

namespace App\Helper;

use Illuminate\Support\Collection;
use RuntimeException;

class ArrayHelper
{
    /**
     * 多个数组按key求和
     * array ...$arrays ['a'=>1,'b'=>2],['a'=>2,'c'=>3]
     * @return array  ['a'=>3,'b'=>2,c=>3]
     */
    public static function arrayValueSum()
    {
        $res = array();
        foreach (func_get_args() as $arr) {
            foreach ($arr as $k => $v) {
                if (!isset($res[$k])) {
                    $res[$k] = $v;
                } else {
                    $res[$k] += $v;
                }
            }
        }
        return $res;
    }

    /**
     * 对 $data 中的数据按照给定 $key 为的 $index 的值排序
     * $data = [['id' => 1], ['id' => 3], ['id' => 5],];
     * $result = ArrayHelper::sortByGivenIndex($data, 'id', [1, 5, 3]); // [['id' => 1], ['id' => 5], ['id' => 3],];
     *
     * @param array|Collection $data 二维数组
     * @param string $sortKey 指定排序的键，必须确保该键的值是唯一的，否则会丢数据
     * @param array $sortValues 指定排序的顺序
     * @param array $config
     * @return array
     */
    public static function sortByGivenIndex($data, string $sortKey, array $sortValues, array $config = []): array
    {
        $config = array_merge([
            'skipIfNotExist' => true, // 如果 index 在 data 中不存在时跳过
            'dropNotInIndex' => false, // 将不在 index 中的丢弃
        ], $config);
        $data = collect($data)->keyBy($sortKey);
        $result = [];
        foreach ($sortValues as $sortValue) {
            if (!isset($data[$sortValue])) {
                if ($config['skipIfNotExist']) {
                    continue;
                }
                throw new RuntimeException("data 中无 {$sortKey} 为 {$sortValue} 的");
            }
            $result[] = $data[$sortValue];
            unset($data[$sortValue]);
        }
        if ($data && !$config['dropNotInIndex']) {
            $result = array_merge($result, $data->values()->all());
        }
        return $result;
    }
}
