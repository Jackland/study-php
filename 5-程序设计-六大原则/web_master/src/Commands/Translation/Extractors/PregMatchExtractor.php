<?php

namespace App\Commands\Translation\Extractors;

use Symfony\Component\Finder\SplFileInfo;

class PregMatchExtractor extends BaseExtractor
{
    /**
     * @inheritDoc
     */
    public function extract(SplFileInfo $file): array
    {
        $content = file_get_contents($file->getRealPath());
        $result = [];
        $patterns = [
            // __('abc') 或 __('abc', {a:b}) 或 __('abc', {}, 'category')
            '/__\(\'(.*?)\'(.*?)\)/xu',
        ];
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            if (isset($matches[1]) && count($matches[1]) > 0) {
                foreach ($matches[1] as $index => $key) {
                    // $key: abc, $category: 无第三个参数取默认值，有第三个参数取第三个参数
                    $category = $this->getCategoryByFileAndMatchChar($file, $matches[2][$index]);
                    $result[] = [
                        $key,
                        $category,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * 根据文件和正则匹配的字段，获取 category
     * @param SplFileInfo $file
     * @param $matchChar
     * @return string
     */
    private function getCategoryByFileAndMatchChar(SplFileInfo $file, $matchChar)
    {
        if (!$matchChar) {
            return $this->getCategoryByFile($file);
        }
        $lastChar = mb_substr($matchChar, mb_strlen($matchChar) - 1, 1);
        if ($lastChar !== '\'') {
            return $this->getCategoryByFile($file);
        }
        $matchChar = rtrim($matchChar, '\'');
        $category = mb_substr($matchChar, strrpos($matchChar, '\'') + 1);
        /*if (in_array($category, TranslationCategoryAlias::getValues())) {
            return $this->getCategoryByFile($file);
        }*/
        return $category;
    }

    /**
     * @var false|array
     */
    private $_pathAlias = false;

    /**
     * 根据文件获取翻译的 category
     * @param SplFileInfo $file
     * @return string
     */
    private function getCategoryByFile(SplFileInfo $file)
    {
        /*if ($this->_pathAlias === false) {
            $root = str_replace('\\', '/', aliases('@root'));
            $aliases = [
                'catalog/controller' => 'catalog/controller',
                'catalog/view/theme/yzcTheme/template' => 'catalog/view',
                'catalog/view/theme/default/template' => 'catalog/view',
                'catalog/view/theme/giga/template' => 'catalog/view',
                'admin/controller' => 'admin/controller',
                'admin/view/template' => 'admin/view',
            ];
            foreach ($aliases as $path => $alias) {
                $this->_pathAlias[$this->buildPath($root, $path)] = $alias;
            }
        }

        $filePath = str_replace('\\', '/', $file->getRealPath());
        foreach ($this->_pathAlias as $prefix => $categoryPrefix) {
            if (strpos($filePath, $prefix) === 0) {
                return $this->buildPath($categoryPrefix, str_replace([$prefix, '.' . $file->getExtension()], ['', ''], $filePath));
            }
        }*/
        return $this->getDefaultCategory();
    }

    /**
     * @param mixed ...$paths
     * @return string
     */
    protected function buildPath(...$paths)
    {
        $startWithSeparator = isset($paths[0][0]) && $paths[0][0] === '/';
        return ($startWithSeparator ? '/' : '') . implode('/', array_map(function ($path) {
                return ltrim(str_replace('\\', '/', $path), '/');
            }, $paths));
    }
}
