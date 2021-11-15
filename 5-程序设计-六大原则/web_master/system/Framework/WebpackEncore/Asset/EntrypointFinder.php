<?php

namespace Framework\WebpackEncore\Asset;

use InvalidArgumentException;

class EntrypointFinder
{
    private $entrypointJsonPath;
    private $strictMode;

    public function __construct(string $entrypointJsonPath, bool $strictMode = true)
    {
        $this->entrypointJsonPath = $entrypointJsonPath;
        $this->strictMode = $strictMode;
    }

    public function getJsFiles(string $entryName): array
    {
        return $this->getEntryFiles($entryName, 'js');
    }

    public function getCssFiles(string $entryName): array
    {
        return $this->getEntryFiles($entryName, 'css');
    }

    private function getEntryFiles(string $entryName, string $type)
    {
        $entriesData = $this->getEntriesData();
        $entryData = $entriesData['entrypoints'][$entryName] ?? [];

        if (!isset($entryData[$type])) {
            return [];
        }

        return $entryData[$type];
    }

    private $entriesData;

    private function getEntriesData(): array
    {
        if ($this->entriesData !== null) {
            return $this->entriesData;
        }

        if (!file_exists($this->entrypointJsonPath)) {
            if (!$this->strictMode) {
                return [];
            }
            throw new InvalidArgumentException(sprintf('入口文件(%s)不存在,请确保 webpack 编译成功', $this->entrypointJsonPath));
        }

        $this->entriesData = json_decode(file_get_contents($this->entrypointJsonPath), true);
        if (!$this->entriesData || !isset($this->entriesData['entrypoints'])) {
            throw new InvalidArgumentException('json 格式异常:' . $this->entrypointJsonPath);
        }

        return $this->entriesData;
    }
}
