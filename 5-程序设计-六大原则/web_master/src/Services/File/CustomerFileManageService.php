<?php

namespace App\Services\File;

use App\Enums\Common\YesNoEnum;
use App\Models\File\CustomerFileManage;
use App\Repositories\File\CustomerFileManageRepository;

class CustomerFileManageService
{
    /**
     * 移动文件
     * @param int $customerId
     * @param int $id
     * @param $toParentId
     * @return array
     */
    public function moveFile($customerId, $id, $toParentId)
    {
        $file = CustomerFileManage::query()
            ->where('customer_id', $customerId)
            ->where('id', $id)
            ->first();
        $data['status'] = false;
        $data['msg'] = '';
        if (empty($file)) {
            $data['msg'] = __('文件不存在', [], 'controller/file_manage');
            return $data;
        }

        if ($toParentId > 0) {
            $toParent = CustomerFileManage::query()
                ->where('id', $toParentId)
                ->where('is_dir', YesNoEnum::YES)
                ->first();
            if (empty($toParent)) {
                return $data;
            }
        }
        if ($file->is_dir) {
            if ($file->parent_id == $toParentId) {
                $data['msg'] = __('不能将文件夹移动到自身或者其子目录下', [], 'controller/file_manage');
                return $data;
            }
            $valid = app(CustomerFileManageRepository::class)->checkMoveDirValid($customerId, $id, $toParentId);
            if (!$valid) {
                $data['msg'] = __('不能将文件夹移动到自身或者其子目录下', [], 'controller/file_manage');
                return $data;
            }
            if ($valid === 'dir_over') {
                $data['msg'] = __('文件夹移动失败，目前只支持3层文件目录', [], 'controller/file_manage');
                return $data;
            }
            if (app(CustomerFileManageRepository::class)->checkFileNameIsExist($customerId, $file->name, $toParentId)) {
                $data['msg'] = __('部分文件已存在,移动失败', [], 'controller/file_manage');
                return $data;
            }
        } else {
            if (app(CustomerFileManageRepository::class)->checkFileNameIsExist($customerId, $file->name, $toParentId)) {
                $data['msg'] = __('部分文件已存在,移动失败', [], 'controller/file_manage');
                return $data;
            }
        }
        $file->parent_id = $toParentId;
        if ($file->save()) {
            $data['status'] = true;
        }
        return $data;
    }
}
