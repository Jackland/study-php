<?php

namespace App\Repositories\File;

use App\Enums\Common\YesNoEnum;
use App\Enums\File\FileManageFilePostfix;
use App\Enums\File\FileManageFileType;
use App\Models\File\CustomerFileManage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CustomerFileManageRepository
{
    const DIR_LEVEL = 3;
    // image png gif的mime-types
    const IMAGE_MIME_TYPES = [
        'image/gif', 'image/jpeg', 'image/x-citrix-jpeg', 'image/png',
        'image/x-citrix-png', 'image/x-png'
    ];
    // TODO 查询、判断、校验等方法

    /**
     * 查询文件列表
     * @param int $customerId
     * @param $parentId
     * @param bool $isParent
     * @param $params
     * @param $select
     * @return array
     */
    public function getList($customerId, $parentId, $isParent, $params, $select = [])
    {
        $query = CustomerFileManage::query()->where([
            'customer_id' => $customerId,
            'is_del' => YesNoEnum::NO
        ]);
        $keyword = trim($params['keyword'] ?? '');
        if (strlen($keyword) > 0) {
            $query->where('name', 'like', "%{$keyword}%");
        }
        $fileType = FileManageFileType::getFileType($params['file_type'] ?? '');
        if ($isParent) { // 只有需要限制父目录的逻辑
            $query->where('parent_id', $parentId);
            if ($fileType == FileManageFileType::FILE_TYPE_DIR) {
                $query->where('is_dir', FileManageFileType::ID_DIR_TRUE);
            } else if (in_array($fileType, [FileManageFileType::FILE_TYPE_IMAGE, FileManageFileType::FILE_TYPE_DOCUMENT, FileManageFileType::FILE_TYPE_OTHER])) {
                $query->where([
                    'is_dir' => FileManageFileType::ID_DIR_FALSE,
                    'file_type' => $fileType
                ]);
            }
        } else { // 具体类型的文件，查询所有目录下的文件, 不支持查询所有目录, 不需要限制父目录
            $query->where([
                'is_dir' => FileManageFileType::ID_DIR_FALSE,
                'file_type' => $fileType
            ]);
        }

        $sortKey = $params['sort_key'] ?? 'update_time';
        if (!in_array($params['sort_key'], app(CustomerFileManage::class)->getFillable())) { // 过数据库的字段要验证, 不然sql报错, 尴尬
            $sortKey = 'update_time';
        }
        $sortVal = $params['sort_val'] ?? 'desc';
        if (empty($sortVal) || !in_array($sortVal, ['asc', 'desc'])) {
            $sortVal = 'desc';
        }

        $query->orderBy('is_dir', 'desc')->orderBy($sortKey, $sortVal);
        $page = intval($params['page'] ?? 1);
        $page = ($page > 0) ? $page : 1;
        $pageSize = intval($params['page_size'] ?? 20);
        $pageSize = ($pageSize > 0) ? $pageSize : 20;
        $pageSize = ($pageSize > 1000) ? 1000 : $pageSize; // 最大值为 1000
        $offset = ($page - 1) * $pageSize;
        if (!empty($select)) {
            $query->select($select);
        }

        $count = $query->count();
        $list = $query->offset($offset)->limit($pageSize)->get()->toArray();
        $loaded = $offset + $pageSize;
        return [
            'list' => $list,
            'loaded' => ($loaded > $count) ? $count : $loaded,
            'count' => $count
        ];
    }


    /**
     * 校验文件上传尺寸 图片最大允许20M 其他文件最大允许50M
     * @param UploadedFile $file
     * @return bool
     */
    public function checkFileSizeValid(UploadedFile $file): bool
    {
        $status = true;
        if (
            in_array($file->getMimeType(), self::IMAGE_MIME_TYPES)
            && ceil($file->getSize() / 1024 / 1024 > 20)
        ) {
            $status = false;
        }
        if (
            !in_array($file->getMimeType(), self::IMAGE_MIME_TYPES)
            && ceil($file->getSize() / 1024 / 1024 > 50)
        ) {
            $status = false;
        }
        return $status;
    }

    /**
     * 校验文件类型合法
     * @param UploadedFile $file
     * @return bool
     */
    public function checkFileTypeValid(UploadedFile $file)
    {
        $suffix = strtolower($file->getClientOriginalExtension());
        if (in_array($suffix, FileManageFilePostfix::getImageAndDocumentTypes())) {
            return true;
        }
        return false;
    }

    /**
     * 返回文件所属类型
     * @param $suffix
     * @return int
     */
    public function fileType($suffix)
    {
        $suffix = strtolower($suffix);
        if (in_array($suffix, FileManageFilePostfix::getImageTypes())) {
            return FileManageFileType::FILE_TYPE_IMAGE;
        } elseif (in_array($suffix, FileManageFilePostfix::getDocumentTypes())) {
            return FileManageFileType::FILE_TYPE_DOCUMENT;
        } else {
            return FileManageFileType::FILE_TYPE_OTHER;
        }
    }

    /**
     * 判断文件名是否存在
     * @param int $customerId
     * @param $name
     * @param $parentId
     * @param int $excludeId
     * @return bool
     */
    public function checkFileNameIsExist($customerId, $name, $parentId, $excludeId = 0)
    {
        return CustomerFileManage::query()
            ->where('name', $name)
            ->where('customer_id', $customerId)
            ->where('parent_id', $parentId)
            ->where('is_del', YesNoEnum::NO)
            ->when($excludeId, function ($q) use ($excludeId) {
                $q->where('id', '!=', $excludeId);
            })
            ->exists();
    }

    /**
     * 获取用户目录
     * @param int $customerId
     * @param $parentId
     * @return CustomerFileManage[]|\Illuminate\Database\Eloquent\Collection
     */
    public function dirList($customerId, $parentId)
    {
        return CustomerFileManage::query()
            ->where('customer_id', $customerId)
            ->where('parent_id', $parentId)
            ->where('is_dir', YesNoEnum::YES)
            ->where('is_del', YesNoEnum::NO)
            ->orderBy('update_time', 'DESC')
            ->get(['id', 'parent_id', 'name']);
    }

    /**
     * 检查设置父类为子类
     * @param int $customerId
     * @param int $id
     * @param int $toParentId
     * @return bool
     */
    public function checkMoveDirValid($customerId, $id, $toParentId = 0)
    {
        // 获取目标目录的层级
        $list = $this->getDirAncestry($customerId, $toParentId);
        // 获取被移动目录的层级
        $list2 = $this->getSubDirTree($customerId, $id);
        foreach ($list as $item) {
            if ($item['id'] == $id) {
                return false;
            }
        }
        //判断是否超过3级
        if (count($list) + count($list2) >= self::DIR_LEVEL) {
            return 'dir_over';
        }
        return true;
    }

    /**
     * 获取目录资源家谱
     * @param int $customerId
     * @param int $parentId
     * @param bool $isReset
     * @return array
     */
    public function getDirAncestry($customerId, $parentId = 0, $isReset = false)
    {
        $list = CustomerFileManage::query()
            ->where('customer_id', $customerId)
            ->where('is_dir', YesNoEnum::YES)
            ->where('is_del', YesNoEnum::NO)
            ->get(['id', 'parent_id', 'name'])->toArray();
        return $this->ancestry($list, $parentId,$isReset);
    }

    /**
     * 获取子孙资源
     * @param int $customerId
     * @param int $id
     * @param string[] $select
     * @return array
     */
    public function getSubTree($customerId, $id = 0,$select=['id', 'parent_id', 'name'])
    {
        $list = CustomerFileManage::query()
            ->where('customer_id', $customerId)
            ->where('is_del', YesNoEnum::NO)
            ->get($select)->toArray();
        return $this->subTree($list, $id);
    }

    /**
     * 获取子孙目录
     * @param int $customerId
     * @param int $id
     * @return array
     */
    public function getSubDirTree($customerId, $id = 0)
    {
        $list = CustomerFileManage::query()
            ->where('customer_id', $customerId)
            ->where('is_dir', YesNoEnum::YES)
            ->where('is_del', YesNoEnum::NO)
            ->get(['id', 'parent_id', 'name'])->toArray();
        return $this->subTree($list, $id);
    }

    /**
     * 获取家谱树
     * @param array $data 待分类的数据
     * @param int $pid 要找的祖先节点
     * @param bool $isReset 是否重置静态变量 $ancestry
     * @return array
     */
    public function ancestry($data, $pid, $isReset = false)
    {
        static $ancestry = array();
        if ($isReset) {
            $ancestry = [];
        }
        foreach ($data as $key => $value) {
            if ($value['id'] == $pid) {
                $ancestry[] = $value;

                $this->ancestry($data, $value['parent_id']);
            }
        }
        return $ancestry;
    }

    /**
     * 获取子孙树
     * @param array $data 待分类的数据
     * @param int $id 要找的子节点id
     * @param int $lev 节点等级
     * @return array
     */
    public function subTree($data, $id = 0, $lev = 0)
    {
        static $son = array();

        foreach ($data as $key => $value) {
            if ($value['parent_id'] == $id) {
                $value['lev'] = $lev;
                $son[] = $value;
                $this->subTree($data, $value['id'], $lev + 1);
            }
        }

        return $son;
    }

    /**
     * 获取文件虚拟路径
     * @param int $customerId
     * @param int $id
     * @return string|null
     */
    public function getVirtualPath($customerId, $id)
    {
        $file = CustomerFileManage::query()
            ->where('customer_id', $customerId)
            ->where('is_dir', YesNoEnum::NO)
            ->where('id', $id)
            ->first(['name', 'parent_id', 'id']);
        if (empty($file)) {
            return null;
        }
        $ancestry = $this->getDirAncestry($customerId, $file->parent_id, true);
        sort($ancestry);
        $names = array_column($ancestry, 'name');
        array_push($names, $file->name);
        return implode('/', $names);
    }

}
