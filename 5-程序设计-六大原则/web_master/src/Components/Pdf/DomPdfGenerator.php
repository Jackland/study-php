<?php

namespace App\Components\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Throwable;

class DomPdfGenerator
{
    protected $pdf;
    protected $files;

    protected $rendered = false;
    protected $fontFamily;

    public function __construct(Dompdf $dompdf, Filesystem $files = null)
    {
        $this->pdf = $dompdf;
        $this->files = $files ?: new Filesystem();
    }

    /**
     * @return Dompdf
     */
    public function getDomPdf(): Dompdf
    {
        return $this->pdf;
    }

    /**
     * @see Dompdf::setPaper()
     */
    public function setPaper($size = 'A4', $orientation = 'portrait'): self
    {
        $this->pdf->setPaper($size, $orientation);
        return $this;
    }

    /**
     * 增加 pdf 信息
     * @param array $infos
     * @return $this
     */
    public function addInfo(array $infos): self
    {
        foreach ($infos as $name => $value) {
            $this->pdf->add_info($name, $value);
        }
        return $this;
    }

    /**
     * 设置配置
     * @param array|Options $options
     * @param bool $append 是否追加配置参数
     * @return $this
     * @see Options 可配置的参数
     */
    public function setOptions($options, $append = true): self
    {
        if (is_array($options)) {
            $attributes = $options;
            $options = $append ? $this->getOptions() : new Options();
            $options->set($attributes);
        }
        $this->pdf->setOptions($options);
        return $this;
    }

    /**
     * 设置支持的字体
     * 支持的字体见: @vendor/dompdf/dompdf/lib/fonts/dompdf_font_family_cache.php
     * @param string $fontName
     * @return $this
     */
    public function setFontFamily(string $fontName): self
    {
        $this->fontFamily = $fontName;
        return $this;
    }

    /**
     * @return Options
     */
    public function getOptions(): Options
    {
        return $this->pdf->getOptions();
    }

    /**
     * 加载 html 内容
     * @param string $html
     * @param null $encoding
     * @return $this
     */
    public function loadHtml(string $html, $encoding = null): self
    {
        $content = $this->convertEntities($html);
        $this->pdf->loadHtml($content, $encoding);
        $this->rendered = false;
        return $this;
    }

    /**
     * 加载一个 html 文件或 url
     * @param string $file
     * @param null $encoding
     * @return $this
     */
    public function loadFile(string $file, $encoding = null): self
    {
        $this->pdf->loadHtmlFile($file, $encoding);
        $this->rendered = false;
        return $this;
    }

    /**
     * 加载视图
     * @param string $view
     * @param array $data
     * @param null $encoding
     * @return $this
     */
    public function loadView(string $view, array $data = [], $encoding = null): self
    {
        $content = view()->render($view, $data);
        return $this->loadHtml($content, $encoding);
    }

    /**
     * 生成 pdf
     * @param string|null $filename
     * @return string
     */
    public function generate(string $filename = null): string
    {
        if (!$this->rendered) {
            $this->render();
        }
        $output = $this->pdf->output();
        if ($filename) {
            $this->files->put($filename, $output);
        }
        return $output;
    }

    /**
     * 保存 pdf
     * @param string $filename
     * @return $this
     */
    public function save(string $filename): self
    {
        $this->generate($filename);
        return $this;
    }

    /**
     * 输出 html
     * @param string|null $filename
     * @return string
     */
    public function generateHtml(string $filename = null): string
    {
        $output = $this->pdf->outputHtml();
        if ($filename) {
            $this->files->put($filename, $output);
        }
        return $output;
    }

    /**
     * 渲染 pdf 到内存
     */
    protected function render()
    {
        $this->pdf->render();
        $this->rendered = true;
    }

    /**
     * @param $content
     * @return string
     */
    protected function convertEntities($content): string
    {
        $content = strtr($content, [
            '€' => '&#0128;',
            '£' => '&pound;',
        ]);
        if ($this->fontFamily) {
            $exist = $this->pdf->getFontMetrics()->getFamily($this->fontFamily);
            if (!$exist) {
                // 不存在该字体时自动载入
                try {
                    app(Kernel::class)->call('pdf:dompdf-load-fonts --quiet');
                } catch (Throwable $e) {
                    if (OC_DEBUG) {
                        throw $e;
                    }
                }
            }
            // 加入字体
            $content = "<style>body{font-family: '{$this->fontFamily}'}</style>" . $content;
        }
        return $content;
    }
}
