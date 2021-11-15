<?php

namespace Framework\View;

class PhpRenderer implements ViewRendererInterface
{
    /**
     * @inheritDoc
     */
    public function render(ViewFactory $view, string $fullPath, string $viewPath, array $data): string
    {
        return $this->renderPhp($fullPath, $data);
    }

    protected function renderPhp($_file_, $_params_)
    {
        $_obInitialLevel_ = ob_get_level();
        ob_start();
        ob_implicit_flush(false);
        extract($_params_, EXTR_OVERWRITE);
        try {
            require $_file_;
            return ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $_obInitialLevel_) {
                if (!@ob_end_clean()) {
                    ob_clean();
                }
            }
            throw $e;
        }
    }
}
