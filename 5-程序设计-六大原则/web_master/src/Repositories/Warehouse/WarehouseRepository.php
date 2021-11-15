<?php

namespace App\Repositories\Warehouse;

use App\Enums\Warehouse\SellerType;
use App\Models\Warehouse\WarehouseInfo;
use Framework\Model\Eloquent\Builder;
use App\Repositories\Setup\SetupRepository;

class WarehouseRepository
{
    /**
     * [getActiveAmericanWarehouse description] 仓库基础表中，所有美国在使用状态的仓库，需排除区域仓库（即虚拟的，非真正意义上存货的仓库，如CA，NJ，AT），排除ACME及onsite仓库
     *
     * return array 仓库信息列表
     */
    public static function getActiveAmericanWarehouse()
    {
        return WarehouseInfo::query()->alias('w')
                ->leftJoinRelations('attribute as a')
                ->leftJoin('tb_warehouses_to_seller as wts','wts.warehouse_id','=','a.warehouse_id')
                ->where([
                    ['w.address1','!=',SellerType::VIRTUAL_WAREHOUSE],
                    ['w.status','=',1],
                    ['w.country_id','=',AMERICAN_COUNTRY_ID],

                ])
                ->where(function (Builder $query){
                    $query->where('a.seller_assign', '=', 1)
                        ->orWhereNotNull('wts.seller_code');
                })
                ->whereNotIn('a.seller_type',[
                    SellerType::GIGA_ON_SITE,
                    SellerType::US_NATIVE,
                ])
                ->select('w.warehouseID','w.warehouseCode')
                ->groupBy('w.warehouseID')
                ->get()
                ->toArray();
    }

    /**
     * 检查是否为区域仓库
     * @param int $warehouseId
     * @return bool
     */
    public function checkIsVirtualWarehouse(int $warehouseId): bool
    {
        return WarehouseInfo::query()
            ->where('WarehouseID', $warehouseId)
            ->where('address1', SellerType::VIRTUAL_WAREHOUSE)
            ->exists();
    }

    /**
     * 检查是否为某种类型seller
     * @param int $warehouseId
     * @param string $sellerType
     * @return bool
     */
    public function checkWarehouseSellerType(int $warehouseId, string $sellerType): bool
    {
        return WarehouseInfo::query()->alias('w')
            ->leftJoinRelations('attribute as a')
            ->where('w.WarehouseID', $warehouseId)
            ->where('a.seller_type', $sellerType)
            ->exists();
    }

    /**
     * 检查是否为joy
     * @param int $warehouseId
     * @return bool
     */
    public function checkWarehouseSellerIsJoy(int $warehouseId): bool
    {
        $sellerIds = app(SetupRepository::class)->getValueByKey('JOY_BUY_SELLER_ID');
        return WarehouseInfo::query()->alias('w')
            ->leftJoin('tb_warehouses_to_seller as wts', 'wts.warehouse_id', '=', 'w.WarehouseID')
            ->where('w.WarehouseID', $warehouseId)
            ->whereIn('wts.seller_id', explode(',', $sellerIds))
            ->exists();
    }
}
