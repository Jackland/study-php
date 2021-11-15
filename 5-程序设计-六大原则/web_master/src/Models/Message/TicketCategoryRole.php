<?php

namespace App\Models\Message;

use Framework\Model\EloquentModel;

/**
 * App\Models\Ticket\TicketCategory
 *
 * @property string $role
 * @property int $category_id
 * @property int $id
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\TicketCategoryRole newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\TicketCategoryRole newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Message\TicketCategoryRole query()
 * @mixin \Eloquent
 */
class TicketCategoryRole extends EloquentModel
{
    protected $table = 'oc_ticket_category_role';

    protected $fillable = [
        'role',
        'category_id',
    ];
}
