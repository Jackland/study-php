<?php

namespace Framework\View\Traits;

use Framework\Helper\Html;

/**
 * Web 视图 meta 相关的功能
 */
trait ViewWebMetaTrait
{
    private $title = '';
    private $metas = [];

    /**
     * 设置 title
     * @param string $title
     */
    public function title($title): void
    {
        $this->title = $title;
    }

    /**
     * 渲染 title
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title ?: '';
    }

    /**
     * 设置 meta
     * @param array $options
     */
    public function meta(array $options = []): void
    {
        $this->metas[] = $options;
    }

    /**
     * 渲染 meta
     * @return string
     */
    public function renderMeta(): string
    {
        $contents = [];
        foreach ($this->metas as $meta) {
            $contents[] = Html::tag('meta', '', $meta);
        }
        return implode("\n", $contents);
    }

    /**
     * 设置 description
     * @param string $description
     */
    public function description($description): void
    {
        $this->meta(['name' => 'description', 'content' => $description]);
    }

    /**
     * 设置 keywords
     * @param string|array $keywords
     */
    public function keywords($keywords): void
    {
        $this->meta(['name' => 'keywords', 'content' => implode(',', (array)$keywords)]);
    }
}
