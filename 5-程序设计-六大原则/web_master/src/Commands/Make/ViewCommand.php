<?php

namespace App\Commands\Make;

use Framework\Console\Command;
use Framework\Console\Traits\MakeTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ViewCommand extends Command
{
    use MakeTrait;

    protected $name = 'make:view';
    protected $description = '创建视图文件';
    protected $help = '';

    protected function configure()
    {
        $this->configMake('@root/catalog/view/theme/yzcTheme/template', null);
        $this
            ->addArgument('name', InputArgument::REQUIRED, '名称，例如：account/fee_order/fee_order')
            ->addOption('seller', null, InputOption::VALUE_NONE, 'Seller 后台')
            ->addOption('modeNormal', null, InputOption::VALUE_NONE, '普通模式，默认为 webpack 模式（yzc_front）');
    }

    public function handle()
    {
        $path = $this->argument('name');
        list($isSeller, $modeNormal) = $this->options(['seller', 'modeNormal']);
        $stub = __DIR__ . '/stub/view.stub';
        if ($isSeller) {
            $path = 'customerpartner/' . $path;
            $stub = __DIR__ . '/stub/view_customer_partner.stub';
        }

        if ($modeNormal) {
            $html = <<<HTML
{{ css(['static/{$path}.css']) }}

<div id="app">
{name}
</div>
HTML;
        } else {
            // webpack 模式
            $html = <<<HTML
{{ webpack_entry_asset('{$path}') }}
<div id="app"></div>
HTML;
        }

        $this->generateFile(dirname($path), basename($path) . '.twig', [
            '{html}' => $html,
            '{name}' => $path,
            '{defaultCategory}' => 'catalog/view/' . $path
        ], $stub);

        return 0;
    }
}
