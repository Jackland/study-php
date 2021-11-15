<?php


namespace App\Services\File;


use App\Enums\Common\YesNoEnum;
use App\Models\File\CustomerFileManage;
use App\Repositories\File\FileRepository;
use Storage;

class FileService
{
    /**
     * 废弃文件转移到回收站
     */
    public function moveToTrash()
    {
        $files = CustomerFileManage::query()
            ->where('is_dir', YesNoEnum::NO)
            ->where('is_del', YesNoEnum::YES)
            ->get(['file_path', 'id'])->map(function ($item) {
                $item->file_path = substr($item->file_path, 6);
                return $item;
            });
        $used = app(FileRepository::class)->checkFileUsed($files->pluck('file_path'));
        foreach ($used as &$item) {
            $item = 'image/' . $item;
        }
        $trashFiles = CustomerFileManage::query()
            ->where('is_dir', YesNoEnum::NO)
            ->where('is_del', YesNoEnum::YES)
            ->whereNotIn('file_path', $used)
            ->get(['id', 'file_path']);
        foreach ($trashFiles as $file) {
            if (Storage::cloud()->exists($file->file_path)) {
                Storage::cloud()->move($file->file_path, '/trash/' . date('Ymd') . '/' . $file->file_path);
            }
            $file->delete();
        }
    }

    /**
     * 清空某一天回收站
     */
    public function deleteDirectory()
    {
        $deleteLimitDay = 1;
        for ($i = 0; $i < 7; $i++) {
            $path = '/trash/' . date('Ymd', strtotime("-$deleteLimitDay day"));
            $deleteLimitDay++;
            Storage::cloud()->deleteDirectory($path);
        }
    }

}