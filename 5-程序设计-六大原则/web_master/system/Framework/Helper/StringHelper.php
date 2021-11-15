<?php

namespace Framework\Helper;

use Illuminate\Support\Str;

class StringHelper extends Str
{
    /**
     * 通配符匹配
     *
     * - `\` 用于转译
     * - `*` 匹配所有字符，包括空格
     * - `?` 匹配任意单个字符
     * - `[seq]` 匹配所有在中括号内的字符
     * - `[a-z]` 匹配从 a 到 z
     * - `[!seq]` 匹配所有不在中括号内的字符
     *
     * @param string $pattern 匹配规则
     * @param string $string 匹配内容
     * @param array $options 选项
     *
     * - caseSensitive: bool, 大小写敏感，默认为 true
     * - escape: bool, 允许 \ 转译，默认为 true
     *
     * @return bool 是否匹配成功
     */
    public static function matchWildcard(string $pattern, string $string, array $options = []): bool
    {
        if ($pattern === '*') {
            return true;
        }

        $replacements = [
            '\\\\\\\\' => '\\\\',
            '\\\\\\*' => '[*]',
            '\\\\\\?' => '[?]',
            '\*' => '.*',
            '\?' => '.',
            '\[\!' => '[^',
            '\[' => '[',
            '\]' => ']',
            '\-' => '-',
        ];

        if (isset($options['escape']) && !$options['escape']) {
            unset($replacements['\\\\\\\\'], $replacements['\\\\\\*'], $replacements['\\\\\\?']);
        }

        $pattern = strtr(preg_quote($pattern, '#'), $replacements);
        $pattern = '#^' . $pattern . '$#us';

        if (isset($options['caseSensitive']) && !$options['caseSensitive']) {
            $pattern .= 'i';
        }

        return preg_match($pattern, $string) === 1;
    }

    /**
     * 转化文本中的所有为首字母大写，类似 ucwords
     *
     * @param string $string
     * @return string
     * @see https://php.net/manual/en/function.ucwords.php
     */
    public static function ucwords(string $string): string
    {
        $words = preg_split("/\s/u", $string, -1, PREG_SPLIT_NO_EMPTY);

        $ucfirst = array_map(static function ($word) {
            return static::ucfirst($word);
        }, $words);

        return implode(' ', $ucfirst);
    }

    /**
     * 驼峰转单词
     * 例如：'PostTag' -> 'Post Tag'.
     * @param string $string
     * @param bool $ucwords 是否首字母大写
     * @return string
     */
    public static function camel2words(string $string, bool $ucwords = true): string
    {
        $label = mb_strtolower(trim(str_replace([
            '-',
            '_',
            '.',
        ], ' ', preg_replace('/(?<!\p{Lu})(\p{Lu})|(\p{Lu})(?=\p{Ll})/u', ' \0', $string))));

        return $ucwords ? static::ucwords($label) : $label;
    }
}
