<?php

namespace App\Commands\OssTransfer;

use App\Components\Storage\StorageCloud;
use App\Models\Information\UploadInformation;
use Framework\Console\Command;

class InformationOssCommand extends Command
{

    protected $name = 'OssTransfer:information-oss';

    protected $description = '将后台catalog文件迁移到数据库（tb_upload_information）并且同时上传到阿里云';

    protected $openUploadOss = false;//是否开启上传到阿里云



    /**
     * description:跑线上的数据 重新上传到oss
     * author: fuyunnan
     * @param
     * @return string
     * @throws
     * Date: 2021/6/22
     */
    public function handle()
    {
        if (UploadInformation::count('id') > 0) {
            echo 'data already run over---'.PHP_EOL;
            return true;
        }
        $dir = DIR_IMAGE . 'catalog/';
        /**
         *sept 1 先找出所有文件夹
         */
        $this->scandirFolder($dir);
        echo '文件夹查找完毕;开始查找文件'.PHP_EOL;
        /**
         *sept 2 先找出所有文件
         */
        $this->addFile($dir);

        echo  'run shell ok time---'.date("Y-m-d H:i:s") . PHP_EOL;
        return true;
    }

    /**
     * description:
     * @param string $dir 要跑的文件路径
     * @return void
     */
    private function addFile(string $dir)
    {
        /**
         *将对应文件夹的文件导入进去
         */
        $arr_file = array();
        $this->tree($arr_file, $dir);

        if ($arr_file) {
            foreach ($arr_file as $item) {
                $temps = explode('/', $item);
                $lastTwoItem = array_slice($temps, -2, 1);
                $lastItem = array_slice($temps, -1, 1);

                /**
                 *去除不必要的文件
                 */
                if (in_array($lastItem[0], ['.gitignore', 'index.html','readme.md'])) {
                    continue;
                }

                //根目录文件
                if (count($temps) == 2 || count($temps) == 3) {
                    $pid = UploadInformation::query()
                        ->where('folder', $lastTwoItem[0] . '/')
                        ->first(['id','folder']);

                    if (count($temps) == 2 ) {
                        $filePath = 'image/catalog/' .$lastTwoItem[0]. $lastItem[0];
                    }else{
                        $filePath = 'image/catalog/' .$lastTwoItem[0].'/'. $lastItem[0];
                    }

                    UploadInformation::query()->insert([
                        'file_path' => $filePath,
                        'file_name' => $lastItem[0],
                        'pid' =>$pid['id'] ?? 0
                    ]);

                    if ($this->openUploadOss) {
                        $content = file_get_contents(DIR_IMAGE . 'catalog/' . $lastItem[0]);
                        StorageCloud::image()->write('/catalog/' . $lastItem[0], $content);
                    }
                } else {
                    $pid = UploadInformation::query()
                        ->where('folder', $lastTwoItem[0] . '/')
                        ->value('id');

                    $pidPath = $temps[1];
                    $filePath = $this->pathString($lastTwoItem[0]);

                    if (strstr($filePath, $pidPath) === false) {
                        $filePath =$pidPath.'/'.$filePath;
                    };

                    UploadInformation::query()->insert([
                        'file_path' => 'image/catalog/'. $filePath . $lastItem[0],
                        'pid' => $pid,
                        'file_name' => $lastItem[0]
                    ]);
                    if ($this->openUploadOss) {
                        $content = file_get_contents(DIR_IMAGE . 'catalog/' . $lastTwoItem[0] . '/' . $lastItem[0]);
                        StorageCloud::image()->write('/catalog/' . $lastTwoItem[0] . '/' . $lastItem[0], $content);
                    }
                }
            }
        }
    }


    /**
     * description:递归找目录
     * @return string
     */
    public function pathString($path)
    {
        //修复 可能存在上级目录
        $pidArr = UploadInformation::query()
            ->where('folder', $path . '/')
            ->first(['id', 'pid', 'folder']);

        $stringPath = '';
        //如果pid 不等于0 还应该找父级
        if ($pidArr['pid'] != 0) {
            $pidArrNew = UploadInformation::query()
                ->where('id',  $pidArr['pid'])
                ->first(['id', 'pid', 'folder']);
            $stringPath .= $pidArrNew['folder'];
            $stringPath .= $pidArr['folder'];
            $this->pathString($pidArrNew['folder']);

        }
        return $stringPath;


    }

    /**
     * description:修改后 递归树 查找所有的文件夹
     * author: fuyunnan
     * @param string $path folder path
     * @param int $pid 文件夹父级id
     * @return array
     * @throws
     * Date: 2021/6/22
     */
    private function scandirFolder(string $path, int $pid = 0)
    {
        $list = [];
        $temp_list = scandir($path);
        $g = 0;
        foreach ($temp_list as $file) {
            if ($file != ".." && $file != ".") {
                if (is_dir($path . "/" . $file)) {

                    $tempPath = explode('/',$path . "/" . $file);
                    $lastTwoItem = array_slice($tempPath, -2, 1);//倒数第二个
                    $lastItem = array_slice($tempPath, -1, 1);//倒数第一个

                    //如果父级已经存在
                    $pidExits = UploadInformation::query()->where(['folder' => $lastTwoItem[0] . '/'])->value('id');
                    if ($pidExits) {
                        $id = $pidExits;
                        UploadInformation::query()->insertGetId([
                            'folder' => $lastItem[0] . '/',
                            'pid' => $pidExits
                        ]);
                    }else{
                        //获取最后一个文件夹的名称
                        $id = UploadInformation::query()->insertGetId([
                            'folder' => $lastItem[0] . '/',
                            'pid' => $g > 2 ? 0 : $pid
                        ]);
                    }

                    $pid = $id;
                    //子文件夹，进行递归
                    $g++;
                    $list[][$file] = $this->scandirFolder($path . "/" . $file, $pid);
                } else {
                    $g = 100;
                    //根目录下的文件
                    $list[] = $file;
                }

            }
        }
        return $list;
    }


    /**
     * description:查找所有文件的递归树
     * author: fuyunnan
     * @param
     * @return void
     * @throws
     * Date: 2021/6/22
     */
    private function tree(&$arr_file, $directory, $dir_name = '')
    {
        $myDir = dir($directory);
        while ($file = $myDir->read()) {
            if ((is_dir($directory . '/' . $file)) and ($file != ".") and ($file != "..")) {
                $this->tree($arr_file, $directory . '/' . $file, $dir_name . '/' . $file);
            } else if (($file != ".") and ($file != "..")) {
                $arr_file[] = $dir_name . '/' . $file;
            }
        }
        $myDir->close();
    }
}
