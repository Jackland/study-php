<?php

namespace App\Models\Warehouse;

use App\Models\Customer\Customer;
use Eloquent;
use Framework\Model\Eloquent\Builder;
use Framework\Model\EloquentModel;
use Illuminate\Database\Eloquent\Collection;

/**
 * App\Models\Warehouse\WarehouseInfo
 *
 * @property int $WarehouseID
 * @property string $WarehouseCode
 * @property string|null $Address1
 * @property string|null $Address2
 * @property string|null $Address3
 * @property string|null $Country
 * @property string|null $ZipCode
 * @property string|null $City
 * @property string|null $State
 * @property string|null $phone_number
 * @property string|null $warehouse_contact
 * @property int|null $country_id
 * @property int $status 仓库是否有效标志位：0-无效；1-有效
 * @property Collection|Customer[] $sellers 关联的seller
 * @property Collection|WarehousesToAttribute[] $attribute 仓库属性
 * @property-read int|null $attribute_count
 * @property-read mixed $warehouse_address
 * @property-read string $full_address
 * @property-read int|null $sellers_count
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\WarehouseInfo newModelQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\WarehouseInfo newQuery()
 * @method static \Framework\Model\Eloquent\Builder|\App\Models\Warehouse\WarehouseInfo query()
 * @mixin \Eloquent
 */
class WarehouseInfo extends EloquentModel
{
    protected $table = 'tb_warehouses';
    protected $primaryKey = 'WarehouseID';
    protected $appends = ['warehouseAddress'];
    protected $dates = [

    ];

    protected $fillable = [
        'WarehouseCode',
        'Address1',
        'Address2',
        'Address3',
        'Country',
        'ZipCode',
        'City',
        'State',
        'phone_number',
        'warehouse_contact',
        'country_id',
        'status',
    ];

    public function sellers()
    {
        return $this->belongsToMany(Customer::class, 'tb_warehouses_to_seller', 'warehouse_id', 'seller_id');
    }

    public function attribute()
    {
        return $this->hasMany(WarehousesToAttribute::class, 'warehouse_id');
    }

    public function getWarehouseAddressAttribute($key)
    {
        $string = '';
        $string .= trim($this->Address1).',';
        if(trim($this->Address2)){
            $string .= trim($this->Address2).',';
        }
        if(trim($this->Address3)){
            $string .= trim($this->Address3).',';
        }
        if(trim(trim($this->City))){
            $string .= trim($this->City).',';
        }

        return trim($string,',');
    }

    /**
     * @return string Address1,Address2,Address3,City,State,ZipCode
     */
    public function getFullAddressAttribute()
    {
        $address = $this->warehouse_address;
        if ($this->State) {
            $address .= ',' . trim($this->State);
        }
        if ($this->ZipCode) {
            $address .= ',' . trim($this->ZipCode);
        }
        return $address;
    }
}
