<?php

use App\Components\Storage\StorageCloud;
use Framework\Storage\UnableToCreateDirectory;
use Framework\Storage\UnableToDeleteFile;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @property ModelUploadFile $model_upload_file
 */
class ControllerUploadUploadComponent extends Controller
{
    static $index = 0;

    private $storage;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        $this->storage = StorageCloud::wkseller();
    }

    public function upload_base()
    {
        return $this->load->view('upload/upload_base');
    }

    public function show_list()
    {
        return $this->load->view('upload/show_list');
    }

    public function upload_input()
    {
        $show_list = $this->load->controller('upload/upload_component/show_list');
        $upload_comp = $this->load->controller('upload/upload_component/upload_base');
        return $this->load->view('upload/upload_input', compact('upload_comp', 'show_list'));
    }

    // 获取目录
    public function getImageBaseDir()
    {
        if (!$this->customer->isLogged()) {
            return $this->returnJson([]);
        }
        $dir = $this->request->post('dir', '');
        return $this->returnJson($this->readDirs($this->getCustomerBasedPath($dir)));
    }

    // 获取目录下文件
    public function getListFromBaseDir()
    {
        if (!$this->customer->isLogged()) {
            return $this->returnJson([]);
        }
        $dir = $this->request->post('dir', '');
        return $this->returnJson([
            'dir' => $dir,
            'files' => $this->readDirFiles($this->getCustomerBasedPath($dir)),
        ]);
    }

    // 创建，删除 或者 重命名文件夹
    // 注意 这个方法只会返回fail 或者  ok
    public function makeDir()
    {
        if (!$this->customer->isLogged()) {
            return $this->returnJson('fail');
        }
        $post = $this->request->post();
        if (isset($post['name']) && !empty($post['name'])) {
            $post['name'] = str_replace(['\\', '/', ' '], '_', trim($post['name']));
        }
        $co = new Collection($post);
        // type:1 添加目录
        if ($co->get('type') == 1) {
            try {
                $dir = $co->get('path') . '/' . $co->get('name');
                $this->storage->createDirectory($this->getCustomerBasedPath($dir));
                $ret = ['code' => 0];
            } catch (UnableToCreateDirectory $e) {
                $ret = ['code' => 1, 'msg' => 'Add folder failed.'];
            }
            return $this->returnJson($ret);
        }
        // type:3 删除目录
        if ($co->get('type') == 3) {
            $isOk = $this->delDir($this->getCustomerBasedPath($co->get('path')));
            if ($isOk) {
                $ret = ['code' => 0];
            } else {
                $ret = ['code' => 0, 'type' => 'error', 'msg' => 'Some files failed to be deleted because they have been used. Others have been deleted successfully.'];
            }
            return $this->returnJson($ret);
        }

        return $this->returnJson(['code' => 1, 'msg' => 'type error']);
    }

    // 上传文件
    public function upload()
    {
        $this->language->load('upload/upload_component');
        $files = $this->request->filesBag;
        $dir = $this->request->post('directory', '');
        $ret = ['code' => 1, 'msg' => 'failed'];
        // 上传是否正确
        /** @var UploadedFile $file */
        $file = $files->get('file');
        if (!$file || !$file->isValid()) {
            $ret['msg'] = $this->language->get('error_file_upload');
            return $this->returnJson($ret);
        }
        // 大小校验
        if (!$this->checkFileSizeValid($file)) {
            $ret['msg'] = $this->language->get('error_file_size');
            return $this->returnJson($ret);
        }
        // 其他校验
        $filename = date('Ymd') . '_'
            . md5((html_entity_decode($file->getClientOriginalName(), ENT_QUOTES, 'UTF-8') . micro_time()))
            . '.' . $file->getClientOriginalExtension();

        $fullPath = $this->storage->writeFile($file, $this->getCustomerBasedPath($dir), $filename);
        $ret = ['code' => 0, 'msg' => 'Upload successfully.'];
        $this->afterUpload($fullPath, $file);

        return $this->returnJson($ret);
    }

    // image png gif的mime-types
    const IMAGE_MIME_TYPES = [
        'image/gif', 'image/jpeg', 'image/x-citrix-jpeg', 'image/png',
        'image/x-citrix-png', 'image/x-png'
    ];

    /**
     * 校验文件上传尺寸 图片最大允许20M 其他文件最大允许50M
     * @param UploadedFile $file
     * @return bool
     */
    private function checkFileSizeValid(UploadedFile $file): bool
    {
        $ret = true;
        if (
            in_array($file->getMimeType(), self::IMAGE_MIME_TYPES)
            && ceil($file->getSize() / 1024 / 1024 > 20)
        ) {
            $ret = false;
        }
        if (
            !in_array($file->getMimeType(), self::IMAGE_MIME_TYPES)
            && ceil($file->getSize() / 1024 / 1024 > 50)
        ) {
            $ret = false;
        }
        return $ret;
    }

    /**
     * 判断资源是否被引用
     * @param array $files [wkseller/{customerId}/xxx, wkseller/{customerId}/xxx]
     * @return array 不可以被删除的文件
     */
    protected function checkFileUsed($files)
    {
        if (!$files) {
            return [];
        }
        $arr = [
            'product' => 'image',
            'product_image' => 'image',
            'product_package_video' => 'video',
            'product_package_file' => 'file',
            'product_package_image' => 'image',
        ];
        $used = collect([]);
        foreach ($arr as $key => $item) {
            $images = $this->orm->table(DB_PREFIX . $key)
                ->select($item)
                ->whereIn($item, $files)
                ->get()->pluck($item)->toArray();
            if ($images) {
                $used = $used->merge($images);
            }
        }
        if ($used->isNotEmpty()) {
            return $used->toArray();
        }
        return [];
    }

    // 删除文件
    public function delFiles()
    {
        $files = $this->request->post('files', []);
        if (is_string($files)) {
            $files = explode(',', $files);
        }
        $canNotBeDeleted = $this->checkFileUsed($files);
        $canDeleteFiles = array_diff($files, $canNotBeDeleted);
        foreach ($canDeleteFiles as $file) {
            try {
                $this->storage->delete($this->storage->getRelativePath($this->normalize2ImageBasedPath($file)));
                $this->afterDelete($file);
            } catch (UnableToDeleteFile $e) {
                // 删除失败忽略
            }
        }
        if ($canNotBeDeleted) {
            $ret = ['code' => 0, 'type' => 'error', 'msg' => 'Some files failed to be deleted because they have been used. Others have been deleted successfully.'];
        } else {
            $ret = ['code' => 0, 'type' => 'success', 'msg' => 'success.'];
        }

        return $this->returnJson($ret);
    }

    public function download()
    {
        $file = $this->request->get('file');
        if (!$this->customer->isLogged() || !$file) {
            return $this->redirect('error/not_found');
        }
        $relativePath = $this->storage->getRelativePath($this->normalize2ImageBasedPath($file));
        if (!$this->storage->fileExists($relativePath)) {
            return $this->redirect('error/not_found');
        }

        // 获取文件名称
        $this->load->model('upload/file');
        /** @var ModelUploadFile $modelUploadFile */
        $modelUploadFile = $this->model_upload_file;
        if ($fileInfo = $modelUploadFile->getFileUploadInfoByPath($file)) {
            $fileName = $fileInfo['orig_name'] ?: $fileInfo['name'];
        } else {
            $fileName = substr($file, strrpos($file, '/') + 1);
        }

        return $this->storage->browserDownload($relativePath, $fileName);
    }

    /**
     * 读取根目录下所有的目录
     *
     * @param string $basePath customerBased 的目录
     * @return array
     */
    protected function readDirs(string $basePath): array
    {
        $info = $this->storage->listContents($basePath);
        static $index = 0;
        $ret = [];
        foreach ($info as $item) {
            if ($item['type'] !== 'dir') {
                continue;
            }
            $index++;
            $ret[] = [
                'id' => $index,
                'label' => $item['filename'],
                'path' => '/' . $this->normalize2CustomerRelativePath($item['path']),
                'children' => [], // 不递归查找，oss 使用时递归查询效率低下
            ];
        }
        return $ret;
    }

    /**
     * 读取根目录下一级的所有文件及目录
     *
     * @param string $basePath customerBased 的目录
     * @return array
     */
    protected function readDirFiles(string $basePath): array
    {
        $this->load->model('upload/file');
        /** @var ModelUploadFile $modelUploadFile */
        $modelUploadFile = $this->model_upload_file;
        $info = $this->storage->listContents($basePath);
        $ret = [];
        $tempRet = [];
        $tempFileRet = [];

        foreach ($info as $item) {
            $info = null;
            if ($item['type'] === 'file') {
                $info = $modelUploadFile->getFileUploadInfoByPath($item['path']);
            }
            $path = ltrim($item['path'], 'image/');
            $tempRes = [
                'url' => $path,
                'orig_url' => $path,
                'name' => $info ? ($info['orig_name'] ?: $info['name']) : $item['basename'],
                'file_id' => $info ? $info['file_upload_id'] : 0,
            ];
            if (isset($item['extension']) && in_array(strtolower($item['extension']), ['png', 'jpg', 'jpeg', 'bmp', 'gif', 'webp', 'tiff'])) {
                $tempRes['thumb'] = $this->storage->getUrl($this->normalize2CustomerBasedPath($item['path']), [
                    'w' => 100,
                    'check-exist' => false,
                ]);
                $tempRes['url'] = $this->storage->getUrl($this->normalize2CustomerBasedPath($item['path']), [
                    'check-exist' => false,
                ]);
                // 使用阿里云 oss 时检查文件宽高，由于需要通过url获取信息，因此性能非常低，所以暂时不启用
                /*list($width, $height) = $this->storage->getImageInfo($this->normalize2CustomerBasedPath($item['path']));
                $tempRes['width'] = $width;
                $tempRes['height'] = $height;*/
                $tempRes['type'] = 'image';
                $ret[] = $tempRes;
            } elseif ($item['type'] === 'file') {
                $tempFileRet[] = $tempRes;
            } else {
                $tempRet[] = $tempRes;
            }
        }
        // 这一步保证图片永远在前面 然后为文件 最后为文件夹
        return array_merge($ret, $tempFileRet, $tempRet);
    }

    /**
     * 删除目录以及该目录下所有文件和文件夹
     * @param string $basePath
     * @return bool
     */
    protected function delDir(string $basePath): bool
    {
        // 此处不使用 storage->deleteDir 是因为数据库需要修改对应状态
        $info = $this->storage->listContents($basePath);
        $files = [];
        $hasDeletedAll = true; // 需要删除的是否已经全部删除
        foreach ($info as $item) {
            if ($item['type'] === 'dir') {
                $isAllOk = $this->delDir($this->normalize2CustomerBasedPath($item['path']));
                if (!$isAllOk) {
                    $hasDeletedAll = false;
                }
            } else {
                $files[] = ltrim($item['path'], 'image/');
            }
        }
        $canNotDeleteFiles = $this->checkFileUsed($files);
        $canDeleteFiles = array_diff($files, $canNotDeleteFiles);
        foreach ($canDeleteFiles as $path) {
            try {
                $this->storage->delete($this->storage->getRelativePath($this->normalize2ImageBasedPath($path)));
                $this->afterDelete($path);
            } catch (UnableToDeleteFile $e) {
                // 删除失败忽略
                $hasDeletedAll = false;
            }
        }
        if (!$canNotDeleteFiles && $hasDeletedAll) {
            // 没有不可以删除的，并且可以删除的已经全部删除掉，删除目录
            try {
                $this->storage->deleteDirectory($this->normalize2CustomerBasedPath($basePath));
                return true;
            } catch (UnableToDeleteFile $e) {
                // 删除目录失败忽略
                return false;
            }
        }
        return false;
    }

    protected function returnJson($res)
    {
        return $this->json($res);
    }

    /**
     * 获取 {customerId}/xxx 的路径
     *
     * @param string $path /xxx 的路径
     * @return string
     */
    protected function getCustomerBasedPath($path): string
    {
        return trim($this->customer->getId() . '/' . ltrim($path, '/'), '/');
    }

    /**
     * 调整文件路径为 {customerId}/xxx 的路径
     *
     * @param string $path
     * @param bool $hasImage
     * @return string
     */
    protected function normalize2CustomerBasedPath($path, $hasImage = true): string
    {
        $wkpath = $this->storage->getFullPath('');
        if (!$hasImage) {
            $path = 'image/' . ltrim($path, '/');
        }
        return trim(str_replace($wkpath, '', $path), '/');
    }

    /**
     * 调整文件路径为 image/wkseller/{customerId}/xxx 的路径
     *
     * @param string $path 必须为 wkseller/{customerId}/xxx 或 image/wkseller/{customerId}/xxx 形式的路径
     * @return string
     */
    protected function normalize2ImageBasedPath($path)
    {
        $path = 'image/' . ltrim($path, 'image/');
        return $path;
    }

    /**
     * 调整文件路径为相对 {customerId} 的 xxx 的路径
     *
     * @param string $path
     * @return string
     */
    protected function normalize2CustomerRelativePath($path): string
    {
        return trim(str_replace($this->storage->getFullPath($this->customer->getId()), '', $path), '/');
    }

    /**
     * 解决path_info 中文问题
     * @param $filepath
     * @return array
     */
    protected function my_path_info($filepath)
    {
        $path_parts = [];
        if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $filepath) > 0) {
            $path_parts ['dirname'] = rtrim(substr($filepath, 0, strrpos($filepath, '/')), "/") . "/";
            $path_parts ['basename'] = ltrim(substr($filepath, strrpos($filepath, '/')), "/");
            $path_parts ['extension'] = substr(strrchr($filepath, '.'), 1);
            $path_parts ['filename'] = ltrim(substr($path_parts ['basename'], 0, strrpos($path_parts ['basename'], '.')), "/");
        } else {
            $path_parts = pathinfo($filepath);
        }

        return $path_parts;
    }

    /**
     * 上传成功后回调函数
     *
     * @param string $newPath 相对路径或url 相对于根目录的路径
     * @param UploadedFile $file
     */
    private function afterUpload(string $newPath, UploadedFile $file)
    {
        $pathInfo = $this->my_path_info($newPath);
        // 写入数据库
        $this->orm->table(DB_PREFIX . 'file_upload')
            ->insert([
                'path' => $newPath,
                'name' => $pathInfo['basename'],
                'suffix' => $pathInfo['extension'],
                'orig_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getClientMimeType(),
                'date_added' => date('Y-m-d H:i:s'),
                'date_modified' => date('Y-m-d H:i:s'),
                'status' => 1,
                'add_operator' => $this->customer->getId() ?: 0,
            ]);
    }

    /**
     * 删除文件
     *
     * @param string $filePath 相对路径 or url
     */
    private function afterDelete(string $filePath)
    {
        $this->orm->table(DB_PREFIX . 'file_upload')
            ->where(['path' => $this->normalize2ImageBasedPath($filePath),])
            ->update([
                'status' => 0,
                'date_modified' => date('Y-m-d H:i:s'),
                'del_operator' => $this->customer->getId() ?: 0,
            ]);
    }
}
