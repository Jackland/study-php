<?php

namespace App\Commands\Translation;

use App\Commands\Translation\Extractors\BaseExtractor;
use App\Commands\Translation\Extractors\ExtractorInterface;
use App\Commands\Translation\Extractors\PhpExtractor;
use App\Commands\Translation\Extractors\TwigExtractor;
use App\Enums\Common\LangLocaleEnum;
use Framework\Cache\Cache;
use Framework\Console\Command;
use Framework\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\VarExporter\VarExporter;

class ExportCommand extends Command
{
    protected $name = 'translation:export';
    protected $description = '翻译导出';
    protected $help = '';

    private $filesystem;
    private $cache;
    private $defaultCategory;
    private $extractor = [
        'twig' => TwigExtractor::class,
        'php' => PhpExtractor::class,
    ];
    // 扫描目录的白名单
    private $whiteListScanPaths = [
        'src',
        'catalog',
        'admin',
    ];
    // 忽略的扫描目录，key 为扫描目录，值为忽略的目录
    private $ignoreScanPaths = [
        'src' => ['Commands'],
        'catalog' => [
            'view/theme/yzcTheme/template/customerpartner/margin',
            'view/theme/yzcTheme/template/account/product_quotes/margin',
        ],
    ];
    // 忽略的扫描文件
    private $ignoreScanFiles = [
        'src/Catalog/Forms/Margin/ContractForm.php' => 1,
        'catalog/view/theme/yzcTheme/template/account/customerpartner/list_quotes_admin.twig' => 1,
    ];
    private $debug = false;
    private $markUnused = false; // 标记无用--暂时不使用
    private $deleteUnused = false; // 删除无用的--暂时不使用

    public function __construct(Application $app)
    {
        parent::__construct($app);

        $this->filesystem = new Filesystem();
        // 使用文件缓存，确保在本地缓存，防止上 redis 缓存之后造成多人共用导致提取 message 被缓存问题
        $this->cache = new Cache(
            new Psr16Cache(new FilesystemAdapter('translationExport', 0, aliases('@runtime/cache'))),
            new NullLogger()
        );
        $this->defaultCategory = trans()->getDefaultCategory();
    }

    protected function configure()
    {
        $defaultPaths = $this->whiteListScanPaths;
        $defaultLang = LangLocaleEnum::getValues();
        $this->addOption('path', 'p', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '文件目录或文件，相对于项目根目录，如：catalog/controller/customerpartner 或 catalog/controller/seller_center/index.php', $defaultPaths)
            ->addOption('lang', 'l', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, '语言国别，如：en-gb', $defaultLang)
            ->addOption('debug', null, InputOption::VALUE_OPTIONAL, 'debug 模式，将会输出当前在解析的文件路径等', false);
    }

    public function handle()
    {
        $scanPaths = $this->option('path');
        $langArr = $this->option('lang');
        if (!$this->checkScanPathSuccessful($scanPaths)) {
            return self::FAILURE;
        }
        $this->debug = $this->option('debug');

        $translationPath = aliases(config('translation.path'));
        $finder = $this->getScannedFiles($scanPaths);
        foreach ($finder as $file) {
            if (array_key_exists(str_replace('\\', '/', $file->getPathname()), $this->ignoreScanFiles)) {
                if ($this->debug) {
                    $this->output->writeln('Ignore File: ' . $file->getRealPath());
                }
                continue;
            }
            if ($this->debug) {
                $this->output->writeln('Extract Message From File: ' . $file->getRealPath());
            }
            $new = $this->extractMessages($file);
            foreach ($langArr as $lang) {
                $langPath = $this->buildPath($translationPath, $lang);
                $old = $this->getOldMessages($langPath, array_keys($new));
                $merged = $this->mergeMessages($new, $old);
                $this->putMessages($langPath, $merged);
            }
        }

        return 0;
    }

    /**
     * 获取解析器
     * @param $extension
     * @return ExtractorInterface
     */
    protected function getExtractor($extension): ExtractorInterface
    {
        $extractorClass = $this->extractor[$extension];
        if (is_string($extractorClass)) {
            $this->extractor[$extension] = app()->make($extractorClass);
            if ($this->extractor[$extension] instanceof BaseExtractor) {
                $this->extractor[$extension]->setDefaultCategory($this->defaultCategory);
            }
        }

        return $this->extractor[$extension];
    }

    /**
     * 获取扫描的所有文件
     * @param array $scanPaths
     * @return Finder|SplFileInfo[]
     */
    protected function getScannedFiles(array $scanPaths)
    {
        $finder = Finder::create()
            ->files();
        foreach ($scanPaths as $scanPath) {
            if ($this->filesystem->isFile($scanPath)) {
                $finder->in(dirname($scanPath))->name(basename($scanPath));
            } else {
                if (isset($this->ignoreScanPaths[$scanPath])) {
                    $finder->notPath($this->ignoreScanPaths[$scanPath]);
                }
                $supportExtensions = array_map(function ($ext) {
                    return '*.' . $ext;
                }, array_keys($this->extractor));
                $finder->in($scanPath)->name($supportExtensions);
            }
        }
        return $finder;
    }

    /**
     * 提取文件中的翻译内容
     * @param SplFileInfo $file
     * @return array [$category => $messagesArr]
     */
    protected function extractMessages(SplFileInfo $file): array
    {
        $messages = $this->getFileCachedMessages($file);
        if ($messages === false) {
            if ($this->debug) {
                $this->output->writeln('New Extract');
            }
            $messages = $this->getExtractor($file->getExtension())->extract($file);
            $this->setFileCachedMessages($file, $messages);
        }

        $result = [];
        foreach ($messages as $message) {
            if (!isset($result[$message[1]])) {
                $result[$message[1]] = [];
            }
            $result[$message[1]][$message[0]] = '@origin';
        }

        return $result;
    }

    /**
     * 根据 category 获取旧的 messages
     * @param string $langBasePath
     * @param array $categories
     * @return array [$category => $messagesArr]
     */
    protected function getOldMessages(string $langBasePath, array $categories): array
    {
        $result = [];
        foreach ($categories as $category) {
            $langPath = $this->buildPath($langBasePath, $category . '.php');
            if (!$this->filesystem->isFile($langPath)) {
                $result[$category] = [];
                continue;
            }
            $result[$category] = require $langPath; // 必须用 require 而不使用 require_once 因为需要多次获取，每次需要取新的
        }

        return $result;
    }

    /**
     * 合并翻译
     * @param array $messages [$category => [$messageKey => $messageValue]]
     * @param array $oldMessages [$category => [$messageKey => $messageValue]]
     * @return array
     */
    protected function mergeMessages(array $messages, array $oldMessages): array
    {
        $result = [];
        foreach ($messages as $category => $new) {
            $old = $oldMessages[$category];
            if (!$old) {
                // 全是新数据
                $result[$category] = $new;
                continue;
            }
            // 旧文件存在
            if ($messages = $this->mergeMessagesInDeep($new, $old)) {
                $result[$category] = $messages;
            }
        }

        return $result;
    }

    /**
     * 合并新旧翻译
     * @param array $new [$messageKey => $messageValue]
     * @param array $old [$messageKey => $messageValue]
     * @return array
     */
    protected function mergeMessagesInDeep(array $new, array $old)
    {
        $diffKey = array_diff_key($new, $old);
        if (!$diffKey) {
            // 新的字段在旧的中都存在，跳过
            return [];
        }

        $result = [];
        foreach ($old as $key => $value) {
            if (!array_key_exists($key, $new)) {
                // 无用的
                if ($this->markUnused) {
                    // 标记无用的
                    $result['@@' . $key] = $value;
                    continue;
                }
                if ($this->deleteUnused) {
                    // 删除无用的
                    continue;
                }
                // 保留
                $result[$key] = $value;
                continue;
            }
            // 有用
            // 暂时不考虑数组递归的情况
            /*if (is_array($value)) {
                $value = $this->mergeMessagesInDeep($new[$key], $value);
            }*/
            $result[$key] = $value;
            // 移除在新的中的
            unset($new[$key]);
        }
        if (!$new) {
            return $result;
        }
        return array_merge($result, $new);
    }

    /**
     * 写入翻译文件
     * @param string $langBasePath
     * @param array $messages
     * @throws \Symfony\Component\VarExporter\Exception\ExceptionInterface
     */
    protected function putMessages(string $langBasePath, array $messages)
    {
        foreach ($messages as $category => $messagesArr) {
            $langPath = $this->buildPath($langBasePath, $category . '.php');
            $dir = $this->filesystem->dirname($langPath);
            if (!$this->filesystem->isDirectory($dir)) {
                $this->filesystem->makeDirectory($dir, 0755, true);
            }

            $data = VarExporter::export($messagesArr);
            $content = <<<TEXT
<?php
/**
 * Generated By Command: {$this->name}
 */

return {$data};

TEXT;
            $this->filesystem->put($langPath, $content);

            $count = count($messagesArr);
            $this->output->writeln("Extract {$count} messages to: {$langPath}");
        }
    }

    protected function getFileCachedMessagesCacheKey(SplFileInfo $file)
    {
        return [__CLASS__, 'v5', $file->getRealPath()];
    }

    protected function getFileCachedMessages(SplFileInfo $file)
    {
        $data = $this->cache->get($this->getFileCachedMessagesCacheKey($file), false);
        if ($data === false) {
            return false;
        }
        list($messages, $time) = $data;
        if ($time !== $file->getMTime()) {
            return false;
        }
        return $messages;
    }

    protected function setFileCachedMessages(SplFileInfo $file, array $messages)
    {
        $this->cache->set($this->getFileCachedMessagesCacheKey($file), [$messages, $file->getMTime()]);
    }

    /**
     * @param mixed ...$paths
     * @return string
     */
    protected function buildPath(...$paths)
    {
        $startWithSeparator = isset($paths[0][0]) && $paths[0][0] === '/';
        return ($startWithSeparator ? '/' : '') . implode('/', array_map(function ($path) {
                return ltrim(str_replace('\\', '/', $path), '/');
            }, $paths));
    }

    /**
     * 检查扫描路劲是否在白名单中
     * @param array $scanPaths
     * @return bool
     */
    private function checkScanPathSuccessful(array $scanPaths)
    {
        foreach ($scanPaths as $scanPath) {
            if (!Str::startsWith($scanPath, $this->whiteListScanPaths)) {
                $this->writeError('all paths must start with: ' . implode(' or ', $this->whiteListScanPaths));
                return false;
            }
        }

        return true;
    }
}
