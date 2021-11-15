<?php

namespace Framework\Console;

use Framework\Exception\Http\NotFoundException;
use Illuminate\Filesystem\Filesystem;

class CodeGenerator
{
    public $filename;
    public $template;
    public $templateData = [];

    protected $filesystem;

    public function __construct($filename, $template, $templateData)
    {
        $this->filename = str_replace('\\', '/', $filename);
        $this->template = $template;
        $this->templateData = $templateData;

        $this->filesystem = new Filesystem();
    }

    public function isExist()
    {
        return $this->filesystem->exists($this->filename);
    }

    public function generate()
    {
        $path = $this->filesystem->dirname($this->filename);
        if (!$this->filesystem->isDirectory($path)) {
            $this->filesystem->makeDirectory($path, 0755, true);
        }
        if (!$this->filesystem->exists($this->template)) {
            throw new NotFoundException('模版文件不存在：' . $this->template);
        }
        $this->filesystem->put($this->filename, $this->getFileContent());
    }

    public function getFileContent()
    {
        if (!$this->filesystem->exists($this->template)) {
            throw new NotFoundException('模版文件不存在：' . $this->template);
        }
        return strtr(file_get_contents($this->template), $this->templateData);
    }
}
