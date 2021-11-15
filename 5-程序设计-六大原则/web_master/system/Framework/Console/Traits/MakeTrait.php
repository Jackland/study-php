<?php

namespace Framework\Console\Traits;

use Framework\Console\CodeGenerator;
use Framework\Foundation\Application;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

trait MakeTrait
{
    protected function configMake($dirDefault, $stubDefault = null)
    {
        $this->addOptionPreview();
        $this->addOptionOverwrite();
        $this->addOptionDir($dirDefault);
        if ($stubDefault) {
            $this->addOptionStub($stubDefault);
        }
    }

    protected function addOptionPreview()
    {
        $this->addOption('preview', 'p', InputOption::VALUE_NONE, '预览');
    }

    protected function addOptionOverwrite()
    {
        $this->addOption('overwrite', 'f', InputOption::VALUE_NONE, '覆盖');
    }

    protected function addOptionDir($default)
    {
        $this->addOption('dir', 'd', InputOption::VALUE_REQUIRED, '写入文件的目录', $default);
    }

    protected function addOptionStub($default)
    {
        $this->addOption('stub', null, InputOption::VALUE_REQUIRED, '替换的模版', $default);
    }

    protected function generateFile($dirPath, $className, $data, $stub = null)
    {
        /** @var Application $app */
        $app = $this->app;
        $aliases = $app->pathAliases;
        if (strpos($dirPath, '@') === 0) {
            $dirPath = $aliases->get($dirPath);
            $dir = '';
        } else {
            $dir = $aliases->get($this->option('dir'));
        }
        $stub = $aliases->get($stub === null ? $this->option('stub') : $stub);
        $extension = strpos($className, '.') === false ? '.php' : '';
        $filename = implode('/', array_filter([$dir, $dirPath, $className . $extension]));

        $generator = new CodeGenerator($filename, $stub, $data);

        if ($this->option('preview')) {
            $this->writeln($generator->getFileContent());
            return;
        }
        if ($this->option('overwrite')) {
            $generator->generate();
            $this->writeSuccess('生成文件：' . $generator->filename);
            return;
        }
        if ($generator->isExist()) {
            $this->writeError('文件已存在：' . $generator->filename);
            $this->writeNote('可以使用 -p 预览 或 -f 覆盖');
            return;
        }

        $generator->generate();
        $this->writeSuccess('生成文件：' . $generator->filename);
    }

    /**
     * aa/bb => Aa\Bb
     * aaBb/cc_dd => AaBb/CcDd
     * Ab\CcDd => Ab\CcDd
     * Ab\\CcDd => Ab\CcDd
     *
     * @param string $name
     * @return string
     */
    public function normalizeClassName(string $name): string
    {
        $name = str_replace('/', '\\', $name);
        $name = implode(
            '\\',
            array_map(function ($name) {
                return Str::studly($name);
            }, explode('\\', $name))
        );
        return $name;
    }

    /**
     * 根据 class 名获取 namespace 和 类名
     * Ab\CcDd\XyZ => [Ab\CcDd, XyZ]
     * XyZ => ['', XyZ]
     *
     * 会优先调用 normalizeClassName 进行 className 格式化
     *
     * @param string $className
     * @return array
     */
    public function parseClassName(string $className): array
    {
        $className = $this->normalizeClassName($className);

        $p = strrpos($className, '\\');
        if ($p === false) {
            return [
                '',
                $className,
            ];
        }
        return [
            substr($className, 0, $p),
            substr($className, $p + 1)
        ];
    }
}
