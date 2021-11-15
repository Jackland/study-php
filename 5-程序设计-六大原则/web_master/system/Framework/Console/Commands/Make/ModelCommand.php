<?php

namespace Framework\Console\Commands\Make;

use Carbon\Carbon;
use Doctrine\DBAL\DBALException;
use Framework\Console\Command;
use Framework\Console\Traits\MakeTrait;
use Framework\Exception\Http\NotFoundException;
use Framework\Model\EloquentModel;
use Illuminate\Database\Capsule\Manager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ModelCommand extends Command
{
    use MakeTrait;

    protected $name = 'make:model';
    protected $description = '创建模型';
    protected $help = '';

    public $dateClass = Carbon::class;

    protected function configure()
    {
        $this->configMake('@root/src/Models', __DIR__ . '/stub/model.stub');

        $this->addArgument('tableName', InputArgument::REQUIRED, '表名，例如：oc_order')
            ->addArgument('modelClass', InputArgument::OPTIONAL, '模型名，例如：product/product_set_info 或 Order/Order')
            ->addOption('relativePath', null, InputOption::VALUE_REQUIRED, '存储路径，相对 --dir，如：product')
            ->addOption('withRepository', 'r', InputOption::VALUE_NONE, '同时创建 Repository')
            ->addOption('withService', 's', InputOption::VALUE_NONE, '同时创建 Service');
    }

    public function handle()
    {
        list($tableName, $modelClass) = $this->arguments(['tableName', 'modelClass']);
        if (!$modelClass) {
            $modelClass = $this->guessModelClass($tableName);
        }
        $relativePath = $this->option('relativePath');

        list($commentStr, $primaryKeyStr, $timestampStr, $datesStr, $fillableStr) = $this->getInfoByDb($tableName);
        list($namespace, $className) = $this->parseClassName($modelClass);
        if ($relativePath) {
            $relativePath = $this->normalizeClassName($relativePath);
            $namespace = $relativePath . ($namespace ? '\\' . $namespace : '');
        }
        $fullModelClassName = 'App\Models' . ($namespace ? ('\\' . $namespace) : '') . '\\' . $className;
        $data = [
            '{{tableName}}' => $tableName,
            '{{className}}' => $className,
            '{{namespace}}' => $namespace ? ('\\' . $namespace) : '',
            '{{propertyComments}}' => $commentStr,
            '{{primaryKey}}' => $primaryKeyStr,
            '{{timestamp}}' => $timestampStr,
            '{{dates}}' => $datesStr,
            '{{fillable}}' => $fillableStr,
            '{{fullModelClassName}}' => $fullModelClassName,
        ];
        $this->generateFile($namespace, $className, $data);

        $className = ($namespace ? ($namespace . '/') : '') . $className;
        if ($this->option('withRepository')) {
            $this->call('make:repository', ['className' => $className]);
        }
        if ($this->option('withService')) {
            $this->call('make:service', ['className' => $className]);
        }

        // 自动更新 model 上的注释
        $this->call('ide-helper:models', [
            'model' => [$fullModelClassName],
            '--write' => true,
        ]);

        return Command::SUCCESS;
    }

    protected function guessModelClass($tableName)
    {
        $prefix = ['oc_', 'tb_sys_', 'oc_customerpartner_', 'tb_'];

        return strtr(str_replace($prefix, '', $tableName), [
            'customerpartner' => 'customer_partner',
        ]);
    }

    /**
     * @param string $tableName
     * @return array [$commentStr, $primaryKeyStr, $timestampStr, $datesStr, $fillableStr]
     */
    protected function getInfoByDb($tableName)
    {
        $schema = $this->app->get(Manager::class)->getConnection()->getDoctrineSchemaManager();
        if (!$schema->tablesExist([$tableName])) {
            throw new NotFoundException('表不存在：' . $tableName);
        }
        $table = $schema->listTableDetails($tableName);

        $comments = []; // 注释
        $timestampColumn = []; // created_at 和 updated_at 是否同时存在的记录
        $fillable = []; // fillable 字段，排除 primary_key、created_at、updated_at
        $dates = []; // dates 字段
        $dateAttributes = [EloquentModel::CREATED_AT, EloquentModel::UPDATED_AT]; // 自动转化为 DateTime 或 Carbon 类型的字段

        try {
            $primaryKeyColumns = $table->getPrimaryKeyColumns(); // 主键字段，不能 fillable
        } catch (DBALException $e) {
            // 无 primarykey
            $primaryKeyColumns = [];
        }
        $primaryKeyStr = '';
        if (!$primaryKeyColumns || count($primaryKeyColumns) > 1) {
            $primaryKeyStr = "\n    protected \$primaryKey = ''; // TODO 主键未知或大于1个";
        } else {
            if ($primaryKeyColumns[0] != 'id') {
                $primaryKeyStr = "\n    protected \$primaryKey = '{$primaryKeyColumns[0]}';";
            }
        }

        foreach ($table->getColumns() as $column) {
            $name = $column->getName();
            if (in_array($name, $dateAttributes)) {
                $timestampColumn[] = $name;
                $type = '\\' . $this->dateClass;
            } else {
                $type = $column->getType()->getName();
                switch ($type) {
                    case 'string':
                    case 'text':
                    case 'date':
                    case 'time':
                    case 'guid':
                    case 'datetimetz':
                    case 'decimal':
                        $type = 'string';
                        break;
                    case 'datetime':
                        $type = '\Illuminate\Support\Carbon';
                        $dates[] = "'{$column->getName()}',";
                        break;
                    case 'integer':
                    case 'bigint':
                    case 'boolean':
                    case 'smallint':
                        $type = 'int';
                        break;
                    case 'float':
                        $type = 'float';
                        break;
                    default:
                        $type = 'mixed';
                        break;
                }
                if (!in_array($column->getName(), $primaryKeyColumns)) {
                    $fillable[] = "'{$column->getName()}',";
                }
            }

            $nullable = !$column->getNotnull() ? '|null' : '';
            $comment = $column->getComment() ? str_replace(["\r", "\n"], ['\r', '\n'], $column->getComment()) : '';
            $comments[] = rtrim(" * @property {$type}{$nullable} \${$column->getName()} {$comment}");
        }

        $timestampStr = '';
        if (count($timestampColumn) >= 2) {
            $timestampStr = "\n    public \$timestamps = true;";
        }

        return [
            "\n" . implode("\n", $comments),
            $primaryKeyStr,
            $timestampStr,
            implode("\n        ", $dates),
            implode("\n        ", $fillable)
        ];
    }
}
