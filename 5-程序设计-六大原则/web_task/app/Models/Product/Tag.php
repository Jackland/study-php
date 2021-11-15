<?php

namespace App\Models\Product;


use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    public $timestamps = false;
    protected $table = 'oc_product_to_tag';
    protected $guarded = [];
}