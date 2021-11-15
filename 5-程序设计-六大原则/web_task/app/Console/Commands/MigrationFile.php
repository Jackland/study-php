<?php

namespace App\Console\Commands;

use App\Models\File\CustomerFileManage;
use App\Models\File\FileUpload;
use Illuminate\Console\Command;
use OSS\Core\OssException;
use OSS\Model\ObjectInfo;
use OSS\OssClient;

class MigrationFile extends Command
{
    const FILE_TYPE_IMAGE = 1;

    const FILE_TYPE_DOCUMENT = 2;

    const FILE_TYPE_OTHER = 3;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
//    protected $signature = 'command:migration_file {--min=} {--max=}';
    protected $signature = 'command:migration_file {--n=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '迁移文件';

    protected $redis;
    protected $redisKey;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->redis = app('redis')->connection('default');
        $this->redisKey = 'migrationFileOriginDir';
        parent::__construct();
    }

    private $images = [
        'jpg', 'jpeg', 'gif', 'png'
    ];

    private $documents = [
        'doc', 'docx', 'xls', 'xlsx', 'pdf', 'ppt', 'pptx', 'txt'
    ];

    public $fileNUm = 0;
    public $dirNum = 0;

    /**
     * Execute the console command.
     * @throws \OSS\Core\OssException
     */
    public function handle()
    {
        $time = time();
        try {
            $this->redis->ping();
        } catch (\Exception $exception) {
            // 依赖redis, 存储目录结构
            echo "redis: " . $exception->getMessage();
            exit();
        }

        $n = intval($this->option('n'));
        $keyword = 'image/wkseller/';
        $ossClient = new OssClient(env('ALIYUN_ACCESS_ID'), env('ALIYUN_ACCESS_KEY'), env('ALIYUN_ENDPOINT'));
        $nextMarker = '';
        while (true) {
            try {
                $options = array(
                    'delimiter' => '',
                    'marker' => $nextMarker,
                    'prefix' => 'image/wkseller/' . $n,
                    'max-keys' => 1000
                );
                $listObjectInfo = $ossClient->listObjects(env('ALIYUN_BUCKET'), $options);
            } catch (OssException $e) {
                printf(__FUNCTION__ . ": FAILED\n");
                printf($e->getMessage() . "\n");
                return;
            }
            // 得到nextMarker，从上一次listObjects读到的最后一个文件的下一个文件开始继续获取文件列表。
            $nextMarker = $listObjectInfo->getNextMarker();
            $listObject = $listObjectInfo->getObjectList();
            if (empty($listObject)) {
                continue;
            }

            $ossList = [];
            foreach ($listObject as $k => $objectInfo) {
                if ($objectInfo->getSize() > 0) {
                    $ossList[$objectInfo->getKey()] = $objectInfo;
                }
            }
            $ossPaths = array_keys($ossList);
            $fileUpload = FileUpload::query()->select(['path', 'suffix', 'orig_name', 'size'])
                ->where('status', 1)->whereIn('path', $ossPaths)->get()->toArray();
            $fileUpload = array_column($fileUpload, null, 'path');
            foreach ($ossPaths as $ossPath) {
                $file = $fileUpload[$ossPath] ?? $this->objectToFileManage($ossList[$ossPath]);
                $params = [
                    'name' => $file['orig_name'],
                    'parent_id' => 0,
                    'file_path' => $file['path'],
                    'file_type' => $this->getFileType($file['suffix']),
                    'is_dir' => 0,
                    'file_suffix' => $file['suffix'],
                    'file_size' => $file['size']
                ];
                $path = substr($file['path'], strlen($keyword));
                $paths = explode('/', $path);
                if (empty($paths) || (count($paths) <= 1)) {
                    continue; // 忽略即可
                }
                if (intval($paths[0]) <= 0) {
                    continue; //忽略 不属于任何customer
                }
                $customerId = intval(array_shift($paths));
                array_pop($paths);
                $fullDir = $this->insertDir($customerId, $paths);
                if ($fullDir === false) {
                    $this->insertOne($customerId, $params);
                    echo "touch file: {$customerId}/" . $file['orig_name'] . "\n";
                    $this->fileNUm++;
                    continue;
                }
                $params['parent_id'] = $this->redis->hget($this->redisKey, $fullDir);
                $this->insertOne($customerId, $params);
                echo "touch file: {$fullDir}" . $file['orig_name'] . "\n";
                $this->fileNUm++;
            }
            if ($listObjectInfo->getIsTruncated() !== "true") {
                break;
            }
        }
        echo "done: " . (time() - $time) . " s\n";
//        echo "done, make dir num:" . $this->dirNum . ", touch file num:" . $this->fileNUm . "\n";
    }

    private function objectToFileManage(ObjectInfo $objectInfo)
    {
        $key = $objectInfo->getKey();
        $explode = explode('/', $key);
        $fileName = array_pop($explode);
        $position = strrpos($fileName, '.' );
        $suffix = ($position === false) ? '' : trim(substr($fileName, $position), '.');
        return [
            'path' => $key,
            'suffix' => $suffix,
            'orig_name' => $fileName,
            'size' => $objectInfo->getSize()
        ];
    }

    // 目录信息插入数据库
    private function insertDir($customerId, $paths)
    {
        if (empty($paths)) {
            return false;
        }
        $dir = $customerId . "/";
        $params = [
            'parent_id' => 0,
            'file_path' => '',
            'file_type' => 0,
            'is_dir' => 1,
            'file_suffix' => '',
        ];
        foreach ($paths as $key => $str) {
            $dir .= $str . "/";
            if ($this->redis->hexists($this->redisKey, $dir)) {
                continue; // 不需要创已经存在的目录
            }
            $params['name'] = $str;
            $parentDir = substr($dir, 0, strrpos($dir, $str . "/"));
            $params['parent_id'] = $this->redis->hget($this->redisKey, $parentDir) ?? 0;
            $this->redis->hset($this->redisKey, $dir, $this->insertOne($customerId, $params));
            echo "make dir: " . $dir . "\n";
            $this->dirNum++;
        }
        return $dir;
    }

    private function insertOne($customerId, $params)
    {
        $params['customer_id'] = $customerId;
        return CustomerFileManage::query()->create($params)->id ?? 0;
    }

    private function getFileType($suffix)
    {
        $suffix = strtolower($suffix);
        if (in_array($suffix, $this->images)) {
            return self::FILE_TYPE_IMAGE;
        }
        if (in_array($suffix, $this->documents)) {
            return self::FILE_TYPE_DOCUMENT;
        }
        return self::FILE_TYPE_OTHER;
    }

//    public function handlerCopy()
//    {
//        try {
//            $this->redis->ping();
//        } catch (\Exception $exception) {
//            // 依赖redis, 存储目录结构
//            echo $exception->getMessage();
//            exit();
//        }
//        $min = intval($this->option('min'));
//        $max = intval($this->option('max'));
//        $keyword = 'image/wkseller/';
//        $query = FileUpload::query()
//            ->where('status', 1)
//            ->where('path', 'like', "{$keyword}%")->orderBy('file_upload_id');
//        $query->where('file_upload_id', '>=', $min);
//        if ($max > 0) {
//            $query->where('file_upload_id', '<=', $max);
//        }
//        $count = $query->count();
//        $limit = 1000;
//        $ceil = ceil($count / $limit);
//        for ($i = 1; $i <= $ceil; $i++) {
//            $list = $query->offset(($i - 1) * $limit)->limit($limit)->get()->toArray();
//            foreach ($list as $item) {
//                $params = [
//                    'name' => $item['orig_name'],
//                    'parent_id' => 0,
//                    'file_path' => $item['path'],
//                    'file_type' => $this->getFileType($item['suffix']),
//                    'is_dir' => 0,
//                    'file_suffix' => $item['suffix'],
//                    'file_size' => $item['size']
//                ];
//
//                $path = substr($item['path'], strlen($keyword));
//                $paths = explode('/', $path);
//                if (empty($paths) || count($paths) <= 1) {
//                    continue; // todo display log
//                }
//                if (intval($paths[0]) <= 0) {
//                    continue; //todo display log
//                }
//                $customerId = intval(array_shift($paths));
//                array_pop($paths);
//                $fullDir = $this->insertDir($customerId, $paths);
//                if ($fullDir === false) {
//                    $this->insertOne($customerId, $params);
//                    echo "touch file: /" . $item['orig_name'] . "\n";
//                    $this->fileNUm++;
//                    continue;
//                }
//                $params['parent_id'] = $this->redis->hget($this->redisKey, $fullDir);
//                $this->insertOne($customerId, $params);
//                echo "touch file: {$fullDir}" . $item['orig_name'] . "\n";
//                $this->fileNUm++;
//            }
//        }
//        echo "done, make dir num:" . $this->dirNum . ", touch file num:" . $this->fileNUm . "\n";
//        return ;
//    }
}
