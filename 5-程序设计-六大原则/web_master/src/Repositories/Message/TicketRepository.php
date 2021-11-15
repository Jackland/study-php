<?php

namespace App\Repositories\Message;

use App\Models\Message\Ticket;
use App\Models\Message\TicketCategory;

class TicketRepository
{
    /**
     * 获取ticket详情
     *
     * @param int $id ID
     * @return mixed
     */
    public function getTicketInfoById($id)
    {
        return Ticket::alias('t')
            ->leftJoinRelations(['sysUser as su'])
            ->select('t.*', 'su.status as user_status')
            ->where('t.id', $id)
            ->first();
    }

    /**
     * 获取指定parent下的分类
     *
     * @param int $parentId
     * @param bool|null $isBuyer
     * @return TicketCategory[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getCategoriesByParentId(int $parentId = 0, bool $isBuyer = null)
    {
        return TicketCategory::query()
            ->where('parent_id', $parentId)
            ->when(!is_null($isBuyer), function ($query) use ($isBuyer) {
                $query->whereHas('roles', function ($roleQuery) use ($isBuyer) {
                    $role = 'buyer';
                    if (!$isBuyer) {
                        $role = 'seller';
                    }
                    $roleQuery->where('role', '=', $role);
                });
            })
            ->orderBy('sort_order')
            ->get();
    }
}
