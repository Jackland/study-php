<?php

namespace Framework\View;

use Framework\Exception\InvalidConfigException;
use Framework\View\Events\ViewFactoryAfterRender;
use Framework\View\Events\ViewFactoryBeforeRender;
use Framework\View\Traits\ViewLayoutTrait;
use Framework\View\Traits\ViewSharedTrait;
use Framework\View\Traits\ViewWebTrait;
use Psr\Container\ContainerInterface;

/**
 * 视图工厂
 */
class ViewFactory
{
    const VIEW_WEB_HEAD_PLACEHOLDER = '<![CDATA[YZC-BLOCK-HEAD]]>';
    const VIEW_WEB_BODY_BEGIN_PLACEHOLDER = '<![CDATA[YII-BLOCK-BODY-BEGIN]]>';
    const VIEW_WEB_BODY_END_PLACEHOLDER = '<![CDATA[YII-BLOCK-BODY-END]]>';

    use ViewSharedTrait;
    use ViewLayoutTrait;
    use ViewWebTrait;

    private $container;
    private $finder;
    private $renderers;
    private $defaultExtension;

    public function __construct(ContainerInterface $container, ViewFinderInterface $finder, array $renderers, string $defaultExtension)
    {
        $this->container = $container;
        $this->finder = $finder;
        $this->renderers = $renderers;
        $this->defaultExtension = $defaultExtension;
    }

    public function getFinder(): ViewFinderInterface
    {
        return $this->finder;
    }

    /**
     * 渲染视图
     * 根据是否调用 withLayout 来决定是否需要渲染 layout
     * @param string $view
     * @param array $data
     * @return string
     * @throws InvalidConfigException
     */
    public function render(string $view, $data = []): string
    {
        $content = $this->renderContent($view, $data);

        if ($layoutPath = $this->getLayoutPath()) {
            $content = $this->renderContent($layoutPath, array_merge([
                'content' => $content,
            ], $this->getCurrentLayoutData()));
        }

        return $content;
    }

    /**
     * 渲染内容
     * @param string $view
     * @param array $data
     * @return string
     * @throws InvalidConfigException
     */
    public function renderContent(string $view, $data = [])
    {
        event(new ViewFactoryBeforeRender($view, $data, $this));

        $ext = $this->getExtension($view);
        if (!$ext) {
            $ext = $this->defaultExtension;
            $view .= '.' . $ext;
        }
        list($fullPath, $viewPath) = $this->finder->find($view);
        $data = array_merge($this->getShared(), $data);

        $content = $this->getRenderer($ext)->render($this, $fullPath, $viewPath, $data);

        event(new ViewFactoryAfterRender($view, $data, $this, $content));

        return $content;
    }

    /**
     * 获取文件后缀
     * @param string $path
     * @return string|null
     */
    protected function getExtension(string $path)
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * 获取渲染引擎
     * @param string $extension
     * @return ViewRendererInterface
     * @throws InvalidConfigException
     */
    protected function getRenderer(string $extension): ViewRendererInterface
    {
        if (!isset($this->renderers[$extension])) {
            throw new InvalidConfigException('renderers Not Config For .' . $extension . ' extension');
        }

        if (!$this->renderers[$extension] instanceof ViewRendererInterface) {
            $this->renderers[$extension] = $this->container->get($this->renderers[$extension]);
        }

        return $this->renderers[$extension];
    }
}
