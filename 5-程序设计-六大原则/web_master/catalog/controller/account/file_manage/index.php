<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Components\Storage\StorageCloud;
use App\Enums\Common\YesNoEnum;
use App\Enums\File\FileManageFileType;
use App\Models\File\CustomerFileManage;
use App\Repositories\File\CustomerFileManageRepository;
use App\Services\File\CustomerFileManageService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ControllerAccountFileManageIndex extends AuthSellerController
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->storage = StorageCloud::wkseller();
    }

    public function index()
    {
        $data = [];
        return $this->render('customerpartner/file_manage/index', $data, 'seller');
    }

    public function list()
    {
        $get = request()->get();
        if ((isset($get['file_type']) && !is_string($get['file_type'])) ||
            (isset($get['keyword']) && !is_string($get['keyword']))) {
            return $this->jsonFailed();
        }
        $isParent = isset($get['parent_id']);
        $sortKey = strtolower($get['sort_key'] ?? 'update_time');
        $sortVal = strtolower($get['sort_val'] ?? 'desc');
        $customerId = intval($this->customer->getId());
        $parentId = $isParent ? ((intval($get['parent_id']) >= 0) ? intval($get['parent_id']) : 0) : null;
        $params = [
            'file_type' => $get['file_type'] ?? 'all',
            'page' => intval($get['page'] ?? 1),
            'page_size' => intval($get['page_size'] ?? 20),
            'keyword' => $get['keyword'] ?? '',
            'sort_key' => $sortKey,
            'sort_val' => $sortVal,
        ];
        $data = app(CustomerFileManageRepository::class)->getList($customerId, $parentId, $isParent,  $params, ['id', 'file_path', 'name', 'update_time as time', 'file_size as size', 'is_dir', 'file_suffix as suffix', 'file_type as type']);
        foreach ($data['list'] as &$datum) {
            $datum['path'] = '';
            $datum['thumb'] = '';
            $datum['orig_url'] = '';
            if ($datum['is_dir'] == FileManageFileType::ID_DIR_FALSE) {
                $datum['orig_url'] = ltrim($datum['file_path'], 'image/');
                $datum['virtual_path'] = app(CustomerFileManageRepository::class)->getVirtualPath($customerId, $datum['id']);
                if ($datum['type'] == FileManageFileType::FILE_TYPE_IMAGE) {
                    $datum['path'] = StorageCloud::image()->getUrl($datum['orig_url'], ['check-exist' => false]);
                    $datum['thumb'] = StorageCloud::image()->getUrl($datum['orig_url'], ['w' => 100, 'h' => 100, 'no-image' => 'default/blank.png']);
                }
            }
            unset($datum['file_path']);
            unset($datum['type']);
        }
        return $this->jsonSuccess($data);
    }

    // 上传文件
    public function upload()
    {
        $files = $this->request->filesBag;
        $parentId = $this->request->post('parent_id', 0);
        $customerId = customer()->getId();
        // 上传是否正确
        /** @var UploadedFile $file */
        $file = $files->get('file');
        if(empty($file)){
            return $this->jsonFailed();
        }
        $fileName = $file->getClientOriginalName();
        $map['name'] = $fileName;
        // 文件名限1-100个字符，只能包含数字、字母，汉字- 或者 _ ，只能以数字或者字母汉字开头
        $arr = explode('.', $fileName);
        if (mb_strlen($arr[0]) > 100) {
            return $this->jsonFailed(__('文件名称不能超过100字符', [], 'controller/file_manage'), $map);
        }
        if (!preg_match('/^[\w\s\(\)\（\）\x{4e00}-\x{9fa5}-]{0,100}\.[a-z]{1,5}$/iu', $fileName)) {
            return $this->jsonFailed(__('文件名称只能包含数字、中英文、空格、（）- 或者 _', [], 'controller/file_manage'), $map);
        }
        if (!preg_match('/^[0-9a-zA-Z\x{4e00}-\x{9fa5}]/iu', $fileName)) {
            return $this->jsonFailed(__('文件名称只能以中英文或者数字开头', [], 'controller/file_manage'), $map);
        }
        if (!$file || !$file->isValid()) {
            return $this->jsonFailed(__('上传文件不合法', [], 'controller/file_manage'));
        }
        // 大小校验
        if (!app(CustomerFileManageRepository::class)->checkFileSizeValid($file)) {
            return $this->jsonFailed(__('单个图片文件大小不超过20M，其他文件不能超过50M', [], 'controller/file_manage'), $map);
        }
        // 类型校验
        if (!app(CustomerFileManageRepository::class)->checkFileTypeValid($file)) {
            return $this->jsonFailed(__('文件格式仅限图片jpeg/jpg/png/gif 或者文档doc(x)/xls(x)/ppt(x)/pdf/txt', [], 'controller/file_manage'), $map);
        }
        // 判断文件名是否存在
        if (app(CustomerFileManageRepository::class)->checkFileNameIsExist($customerId, $fileName, $parentId)) {
            return $this->jsonFailed(__('同一文件夹下，文件名称不能重复', [], 'controller/file_manage'), $map);
        }
        $filename = date('Ymd') . '_'
            . md5((html_entity_decode($fileName, ENT_QUOTES, 'UTF-8') . micro_time()))
            . '.' . $file->getClientOriginalExtension();
        $path = $customerId;
        $fullPath = $this->storage->writeFile($file, $path, $filename);
        $data['customer_id'] = $customerId;
        $data['name'] = $fileName;
        $data['parent_id'] = $parentId;
        $data['file_path'] = $fullPath;
        $data['file_size'] = round($file->getSize() / 1024, 2);
        $data['file_type'] = app(CustomerFileManageRepository::class)->fileType($file->getClientOriginalExtension());
        $data['file_suffix'] = $file->getClientOriginalExtension();
        $res = CustomerFileManage::query()
            ->insert($data);
        if (!$res) {
            return $this->jsonFailed();
        }
        return $this->jsonSuccess();
    }

    // 新建目录
    public function makeDir()
    {
        $name = $this->request->post('name');
        $parentId = $this->request->post('parent_id', 0);
        $customerId = customer()->getId();
        // 文件名限1-50个字符，只能包含数字、字母，汉字- 或者 _ ，只能以数字或者字母汉字开头
        if (!preg_match('/^[0-9a-zA-Z\x{4e00}-\x{9fa5}][\w\s\(\)\（\）\x{4e00}-\x{9fa5}-]{0,49}$/iu', $name)) {
            return $this->jsonFailed(__('文件夹名称不规范', [], 'controller/file_manage'));
        }
        // 判断文件夹名是否存在
        if (app(CustomerFileManageRepository::class)->checkFileNameIsExist($customerId, $name, $parentId)) {
            return $this->jsonFailed(__('文件夹名称不能重复', [], 'controller/file_manage'));
        }
        // 目录不能超过3级
        if (count(app(CustomerFileManageRepository::class)->getDirAncestry($customerId, $parentId)) >= CustomerFileManageRepository::DIR_LEVEL) {
            return $this->jsonFailed(__('文件夹移动失败，目前只支持3层文件目录', [], 'controller/file_manage'));
        }
        $data['customer_id'] = customer()->getId();
        $data['name'] = $name;
        $data['parent_id'] = $parentId;
        $data['is_dir'] = YesNoEnum::YES;
        $res = CustomerFileManage::query()
            ->insert($data);
        if (!$res) {
            return $this->jsonFailed();
        }
        return $this->jsonSuccess();
    }

    // 重命名
    public function rename()
    {
        $id = $this->request->post('id');
        $name = $this->request->post('name');
        $customerId = customer()->getId();
        if (!$id) {
            return $this->jsonFailed();
        }
        $file = CustomerFileManage::query()
            ->where('customer_id', $customerId)
            ->where('id', $id)
            ->first();
        if (empty($file)) {
            return $this->jsonFailed();
        }
        if ($file->is_dir) {
            $pattern = '/^[0-9a-zA-Z\x{4e00}-\x{9fa5}]{1}[\w\s\(\)\（\）\x{4e00}-\x{9fa5}-]{0,49}$/iu';
            $file->name = $name;
            $msg = '文件夹名称不规范';
        } else {
            if (mb_strlen($name) > 100) {
                return $this->jsonFailed(__('文件名称不能超过100字符', [], 'controller/file_manage'));
            }
            $pattern = '/^[0-9a-zA-Z\x{4e00}-\x{9fa5}]{1}[\w\s\(\)\（\）\x{4e00}-\x{9fa5}-]{0,99}\.[a-z]{1,5}$/iu';
            $file->name = $name . '.' . $file->file_suffix;
            $name = $file->name;
            $msg = '文件名称不规范';
        }
        if (!preg_match($pattern, $name)) {
            return $this->jsonFailed(__($msg, [], 'controller/file_manage'));
        }
        if (app(CustomerFileManageRepository::class)->checkFileNameIsExist($customerId, $name, $file->parent_id, $file->id)) {
            return $this->jsonFailed(__('同一文件夹下，文件名称不能重复', [], 'controller/file_manage'));
        }
        if (!$file->save()) {
            return $this->jsonFailed();
        }
        return $this->jsonSuccess([], __('文件重命名成功', [], 'controller/file_manage'));
    }

    // 移动文件
    public function move()
    {
        $ids = $this->request->post('ids');
        $ids = explode(',', $ids);
        $toParentId = $this->request->post('to_parent_id');
        $data = [];
        foreach ($ids as $id) {
            $data[] = app(CustomerFileManageService::class)->moveFile(customer()->getId(), $id, $toParentId);
        }
        if (!in_array(true, array_column($data, 'status'))) {
            return $this->jsonFailed($data[0]['msg']);
        }
        return $this->jsonSuccess($data, __('文件移动成功', [], 'controller/file_manage'));
    }

    // 删除文件
    public function delete()
    {
        $ids = $this->request->post('ids');
        $ids = explode(',', $ids);
        foreach ($ids as $id) {
            $file = CustomerFileManage::query()
                ->where('customer_id', customer()->getId())
                ->where('id', $id)
                ->first();
            if (empty($file)) {
                return $this->jsonFailed();
            }
            if ($file->is_dir) {
                $list = app(CustomerFileManageRepository::class)->getSubTree(customer()->getId(), $id, ['id', 'parent_id']);
                $listIds = array_column($list, 'id');
                CustomerFileManage::query()
                    ->where('customer_id', customer()->getId())
                    ->whereIn('id', $listIds)
                    ->update(['is_del' => YesNoEnum::YES]);
            }
            $file->is_del = YesNoEnum::YES;
            $file->save();
        }
        return $this->jsonSuccess([], __('文件删除成功', [], 'controller/file_manage'));
    }

    // 目录列表
    public function dirList()
    {
        $parentId = $this->request->get('parent_id', 0);
        $dirs = app(CustomerFileManageRepository::class)->dirList(customer()->getId(), $parentId);
        return $this->jsonSuccess($dirs);
    }

    // 复制路径
    public function copyPath()
    {
        $ids = $this->request->get('ids');
        $ids = explode(',', $ids);
        $data = [];
        foreach ($ids as $id) {
            $data[] = app(CustomerFileManageRepository::class)->getVirtualPath(customer()->getId(), $id);
        }
        $json['path'] = implode('|', array_filter($data));
        sleep(6);
        return $this->jsonSuccess($json);
    }
}
