<?php

namespace App\Catalog\Controllers;

use Controller;
use Framework\App;

class BaseController extends Controller
{
    /**
     * @param array|string $languages
     */
    protected function setLanguages($languages)
    {
        foreach ((array)$languages as $language) {
            $this->load->language($language);
        }
    }

    /**
     * 默认的 title 键名
     * @return string
     */
    protected function getDefaultDocumentTitleKey()
    {
        return 'heading_title';
    }

    /**
     * 设置页面信息
     * @param string $title
     * @param string $description
     * @param array $keywords
     */
    protected function setDocumentInfo($title = 'auto', $description = '', $keywords = [])
    {
        if ($title) {
            if ($title === 'auto') {
                $title = $this->getDefaultDocumentTitleKey();
            }
            $this->document->setTitle($this->language->get($title));
        }
        if ($description) {
            $this->document->setDescription($this->language->get($description));
        }
        if ($keywords) {
            $this->document->setKeywords(is_array($keywords) ? implode(',', $keywords) : $keywords);
        }
    }

    /**
     * 获取面包屑导航，默认为 home / current
     * @param array|string $breadcrumbs
     * @return array|array[]
     */
    protected function getBreadcrumbs($breadcrumbs = ['current'])
    {
        if (!$breadcrumbs) {
            return [];
        }
        $url = App::url();
        $breadcrumbs = array_map(function ($breadcrumb) use ($url) {
            if ($breadcrumb === 'home') {
                return [
                    'text' => __('首页', [], 'catalog/document'),
                    'href' => $url->to('common/home'),
                ];
            }
            if ($breadcrumb === 'current') {
                return [
                    'text' => $this->document->getTitle(),
                    'href' => $url->to(''),
                ];
            }
            return [
                'text' => $this->language->get($breadcrumb['text']),
                'href' => $url->to($breadcrumb['href']),
            ];
        }, (array)$breadcrumbs);

        return $breadcrumbs;
    }
}
