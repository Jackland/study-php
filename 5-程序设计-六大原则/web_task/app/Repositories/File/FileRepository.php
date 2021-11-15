<?php

namespace App\Repositories\File;

use App\Enums\Common\YesNoEnum;
use App\Models\File\FileUploadMenu;

/**
 * Class FileRepository
 * @package App\Repositories\File
 */
class FileRepository
{
    public function getFilesByMenuId($menuId)
    {
        return FileUploadMenu::query()
            ->from('tb_file_upload_menu as m')
            ->leftJoin('tb_file_upload_detail as d', 'm.id', '=', 'd.menu_id')
            ->select(['m.id', 'd.file_name', 'd.file_path', 'd.file_suffix', 'd.file_size'])
            ->where('m.id', $menuId)
            ->where('m.status', YesNoEnum::YES)
            ->where('d.delete_flag', YesNoEnum::NO)
            ->where('d.file_status', YesNoEnum::NO)
            ->get();
    }
    /**
     * 判断资源是否被引用
     * @param array $files [wkseller/{customerId}/xxx, wkseller/{customerId}/xxx]
     * @return array 不可以被删除的文件
     */
    public function checkFileUsed($files)
    {
        if (!$files) {
            return [];
        }
        $arr = [
            'oc_product' => 'image',
            'oc_product_image' => 'image',
            'oc_product_package_video' => 'video',
            'oc_product_package_file' => 'file',
            'oc_product_package_image' => 'image',
            'oc_product_certification_document' => 'path',
        ];
        $used = collect([]);
        foreach ($arr as $key => $item) {
            $images = \DB::table($key)
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
}