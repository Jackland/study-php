<?php

namespace App\Models\Message;

use Framework\Model\EloquentModel;

/**
 * App\Models\Ticket\TicketCategory
 *
 * @property int $category_id
 * @property string $name
 * @property int $sort_order 升序
 * @property int $parent_id 0顶级
 * @property int $level 方便管理员在表中查看，0 Submit a Ticket For，1 Ticket Type
 * @property int $is_deleted 1软删除 0正常数据
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Message\TicketCategoryRole[] $roles
 * @property-read int|null $roles_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\TicketCategory newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\TicketCategory newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\TicketCategory query()
 * @mixin \Eloquent
 */
class TicketCategory extends EloquentModel
{
    protected $table = 'oc_ticket_category';
    protected $primaryKey = 'category_id';

    protected $dates = [

    ];

    protected $fillable = [
        'name',
        'sort_order',
        'parent_id',
        'level',
        'is_deleted',
    ];

    public function roles()
    {
        return $this->hasMany(TicketCategoryRole::class,'category_id','category_id');
    }
}
