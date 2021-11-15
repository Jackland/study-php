<?php

namespace App\Components\Pdf;

use Illuminate\Filesystem\Filesystem;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class MPdfGenerator
{
    protected $pdf;
    protected $files;

    private $_htmlContent;

    public function __construct($mpdfConfig = [], Filesystem $files = null)
    {
        $this->config($mpdfConfig);
        $this->files = $files ?: new Filesystem();
    }

    /**
     * 配置并生成一个 mpdf 实例
     * @param array $config
     * @return $this
     */
    public function config($config = []): self
    {
        $config = array_merge([
            'supportCN' => true, // 支持中文
        ], $config);

        $this->pdf = new Mpdf($config);

        if ($config['supportCN']) {
            $this->pdf->autoLangToFont = true;
            $this->pdf->autoScriptToLang = true;
            $this->pdf->useSubstitutions = true;
        }

        return $this;
    }

    /**
     * @return Mpdf
     */
    public function getMpdf(): Mpdf
    {
        if (!$this->pdf) {
            $this->config();
        }
        return $this->pdf;
    }

    /**
     * 加载 html 内容
     * @param string $html
     * @return $this
     */
    public function loadHtml(string $html): self
    {
        $this->_htmlContent = $html;
        $this->pdf->WriteHTML($html);
        return $this;
    }

    /**
     * 加载一个 html 文件或 url
     * @param string $file
     * @return $this
     */
    public function loadFile(string $file): self
    {
        return $this->loadHtml(file_get_contents($file));
    }

    /**
     * 加载视图
     * @param string $view
     * @param array $data
     * @return $this
     */
    public function loadView(string $view, array $data = []): self
    {
        $content = view()->render($view, $data);
        return $this->loadHtml($content);
    }

    /**
     * 生成 pdf
     * @param string|null $filename
     * @return string
     */
    public function generate(string $filename = null): string
    {
        $output = $this->pdf->Output('', Destination::STRING_RETURN);
        if ($filename) {
            $this->files->put($filename, $output);
        }
        return $output;
    }

    /**
     * 输出 html
     * @param string|null $filename
     * @return string
     */
    public function generateHtml(string $filename = null): string
    {
        $output = $this->_htmlContent;
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
}
