<?php

namespace App\Console\Commands;

use App\Models\CustomerPartner\DelicacyManagement;
use Illuminate\Console\Command;

class ClearInvalidDelicacyManagement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear:invalid_delicacy_management {limit?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清理无效的精细化管理数据';

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
     * @return mixed
     */
    public function handle()
    {
        $limit = $this->argument('limit') ?? 10;
        $delicacyManagement = new DelicacyManagement();
        $is_empty = false;
        $count = 0;
        do {
            $ids = $delicacyManagement->getInvalidData($limit >= 100 ? 100 : $limit);
            if (empty($ids)) {
                $is_empty = true;
            } else {
                $delicacyManagement->batchDeleteByIDs($ids);
            }
            $limit -= $limit >= 100 ? 100 : $limit;
            $count += count($ids);

        } while ($limit >= 100 && !$is_empty);

        echo date('Y-m-d H:i:s')
            . ' clear:invalid_delicacy_management '
            . ($this->argument('limit') ?? 10)
            . ($count ? ' 共处理' . $count . '条' : ' 无待处理数据') . PHP_EOL;
        return;
    }
}
