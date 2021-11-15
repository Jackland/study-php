<?php

namespace App\Commands\Translation\Extractors;

use Symfony\Component\Finder\SplFileInfo;

interface ExtractorInterface
{
    /**
     * 提取
     * @param SplFileInfo $file
     * @return array [[$message, $category]]
     */
    public function extract(SplFileInfo $file): array;
}
