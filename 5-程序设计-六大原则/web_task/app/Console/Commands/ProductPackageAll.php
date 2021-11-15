<?php

namespace App\Console\Commands;

use App\Services\Product\ProductService;
use Illuminate\Console\Command;

/**
 * Class ProductPackage
 * @package App\Console\Commands
 */
class ProductPackageAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:packAll {--type=} {--productGt=} {--acl=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '打包所有产品';



    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $type = $this->option('type');
        $acl = $this->option('acl');
        $productGt = $this->option('productGt');
        if ($acl && !in_array($acl, ['private', 'public'])) {
            $this->error('acl error');
            return;
        }
        echo date("Y-m-d H:i:s", time()) . ' -----product-pack-start------' . PHP_EOL;
        app(ProductService::class)->packAllProduct($type, $acl, $productGt);
        echo date("Y-m-d H:i:s", time()) . ' ------product-pack-end------' . PHP_EOL;
    }




}
