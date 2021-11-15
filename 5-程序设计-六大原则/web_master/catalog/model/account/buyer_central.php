<?php
/**
 * Create by PHPSTORM
 * User: yaopengfei
 * Date: 2020/7/1
 * Time: 下午3:48
 */

use App\Enums\Product\ProductType;
use App\Enums\SalesOrder\CustomerSalesOrderMode;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;

/**
 * Class ModelAccountBuyerCentral
 * @property ModelCustomerpartnerSellerCenterIndex model_customerpartner_seller_center_index
 */

class ModelAccountBuyerCentral extends Model
{
    const TABLE_OC_LEASING_MANAGER_STATUS_INVALID = 0;
    const TABLE_OC_LEASING_MANAGER_STATUS_VALID = 1;

    const TABLE_OC_MARKETING_CAMPAIGN_NOT_RELEASE = 0;
    const TABLE_OC_MARKETING_CAMPAIGN_RELEASE = 1;

    const TABLE_OC_MARKETING_CAMPAIGN_TYPE_BANNER = 1;

    /**
     * buyer的招商经理姓名手机邮箱
     * @param int $buyerId
     * @return array
     */
    public function buyerLeasingManagerNamePhoneEmailByBuyerId(int $buyerId)
    {
        $result = $this->orm->table('tb_sys_buyer_account_manager as tsbam')
            ->join(DB_PREFIX . 'leasing_manager as olm', 'tsbam.AccountId', '=', 'olm.customer_id')
            ->join('tb_sys_user as su','olm.user_id','=','su.id')
            ->leftJoin('tb_upload_file as uf','su.picture_id','=','uf.id')
            ->select([
                'su.username',
                'su.email',
                'su.mobile_phone',
                'uf.path'
            ])
            ->where([
               ['tsbam.BuyerId', '=',$buyerId],
               ['olm.status', '=',1],
            ])
            ->first();
        return obj2array($result);
    }

    /**
     * 促销活动可限制条数
     * @param int $buyerCountryId
     * @param int $limit
     * @return array
     */
    public function promotionActivitiesExcludeBannerByCountryIdLimit(int $buyerCountryId, int $limit = 3)
    {
        $now = date('Y-m-d H:i:s');
        $results = $this->orm->table(DB_PREFIX . 'marketing_campaign')
            ->where('country_id', $buyerCountryId)
            ->where('type', '!=', self::TABLE_OC_MARKETING_CAMPAIGN_TYPE_BANNER)
            ->where('is_release', self::TABLE_OC_MARKETING_CAMPAIGN_RELEASE)
            ->where('effective_time', '<=', $now)
            ->where('expiration_time', '>', $now)
            ->orderBy("effective_time", "desc")
            ->limit($limit)
            ->selectRaw('code, name, effective_time  as apply_start_time, expiration_time as apply_end_time')
            ->get();

        return obj2array($results);
    }

    /**
     * buyer销售排名数据根据日期（可限制条数）
     * @param int $buyerId
     * @param string $date
     * @param int $limit
     * @return array
     */
    public function buyerCompletedSaleRankingsByBuyerIdDateLimit(int $buyerId, string $date, int $limit = 6)
    {
        $results = $this->orm->table('tb_sys_customer_sales_order as tscso')
            ->join('tb_sys_customer_sales_order_line as tscsol', 'tscsol.header_id', '=', 'tscso.id')
            ->leftJoin(DB_PREFIX . 'order_cloud_logistics as cl', 'cl.sales_order_id', '=', 'tscso.id')
            ->join('tb_sys_order_associated as tsoa', [['tscsol.id', '=', 'tsoa.sales_order_line_id'],['tscso.id', '=', 'tsoa.sales_order_id']])
            ->join(DB_PREFIX . 'order_product as oop', 'oop.order_product_id', '=', 'tsoa.order_product_id')
            ->join(DB_PREFIX . 'product as p', 'oop.product_id', '=', 'p.product_id')
            ->where('p.part_flag', 0)
            ->where('tscso.buyer_id', $buyerId)
            ->where('p.product_id', '!=', DELIVERY_CONFIRMATION_PRODUCT_ID)
            ->where('p.product_type', '!=', ProductType::COMPENSATION_FREIGHT) // 销售排行过滤补运费商品
            ->where(function ($query) {
                $query->where([['tscso.order_status', '=', CustomerSalesOrderStatus::COMPLETED], ['tscso.order_mode', '!=', CustomerSalesOrderMode::CLOUD_DELIVERY]])->orWhere([['cl.cwf_status', '=', 7], ['tscso.order_mode', '=', CustomerSalesOrderMode::CLOUD_DELIVERY]]);
            })
            ->where('tscso.create_time', '>=', $date)
            ->groupBy(['tscsol.item_code'])
            ->selectRaw('sum(tsoa.qty) as count, tscsol.item_code, oop.product_id')
            ->orderByRaw('count desc, oop.product_id desc')
            ->limit($limit)
            ->get();

        return obj2array($results);
    }

    public function productNameByProductIds(array $productIds)
    {
        $results = $this->orm->table(DB_PREFIX . 'product_description')
            ->whereIn('product_id', $productIds)
            ->pluck('name', 'product_id');

        return obj2array($results);
    }
}
