<?php

namespace Framework\View;

use Closure;
use Framework\Aliases\Aliases;
use Framework\View\Exception\ViewFileNotFoundException;

class ViewFinder implements ViewFinderInterface
{
    private $basePath;
    private $themePaths;
    private $aliases;

    public function __construct(string $basePath, $themePaths = [], ?Aliases $aliases = null)
    {
        $this->basePath = $basePath;
        $this->themePaths = $themePaths;
        $this->aliases = $aliases;
    }

    /**
     * @inheritDoc
     */
    public function find(string $view): array
    {
        $paths = $this->getOrCacheViewFind($view, function ($view) {
            // alias
            if ($this->aliases && strpos($view, '@') === 0) {
                $path = $this->aliases->get($view);
                if (is_file($path)) {
                    return [$path, $view];
                }
            }
            // with theme
            foreach ($this->themePaths as $themePath) {
                $path = Util::buildPath($this->basePath, $themePath, $view);
                if (is_file($path)) {
                    return [$path, Util::buildPath($themePath, $view)];
                }
            }
            // no theme
            $path = Util::buildPath($this->basePath, $view);
            if (is_file($path)) {
                return [$path, $view];
            }

            return -1;
        });

        if ($paths === -1) {
            throw new ViewFileNotFoundException($view);
        }

        return $paths;
    }

    private $cachedPaths = [];

    /**
     * @param string $view
     * @param Closure $param
     * @return mixed
     */
    private function getOrCacheViewFind(string $view, Closure $param)
    {
        if (isset($this->cachedPaths[$view])) {
            return $this->cachedPaths[$view];
        }

        $this->cachedPaths[$view] = call_user_func($param, $view);

        return $this->cachedPaths[$view];
    }

    /**
     * @inheritDoc
     */
    public function exist(string $view): bool
    {
        try {
            $this->find($view);
        } catch (ViewFileNotFoundException $e) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
