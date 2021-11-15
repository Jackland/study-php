<?php

namespace App\Repositories\Seller;

use App\Components\Traits\RequestCachedDataTrait;
use App\Enums\Common\YesNoEnum;
use App\Enums\Customer\CustomerAccountingType;
use App\Models\Buyer\BuyerToSeller;
use App\Models\Customer\Customer;
use App\Models\Link\CustomerPartnerToProduct;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class SellerRepository
{
    use RequestCachedDataTrait;

    /**
     * 判断当前用户是否显示云送仓提醒
     *
     * @return bool
     */
    public function isShowCwfNotice()
    {
        //应用seller为美国，除了内部和服务店铺以及W 501和W606外的Seller
        if (customer()->isPartner()
            && customer()->isUSA()
            && !in_array(customer()->getAccountType(), [1, 4])
            && !in_array(customer()->getId(), [3222, 4125])) {
            return true;
        }
        return false;
    }

    /**
     * 获取系统默认退返品
     * @return array
     */
    public function getDefaultReturnWarranty()
    {
        $returnWarranty = [
            'return' => [
                'undelivered' => [
                    'days' => configDB('store_default_return_not_delivery_days'),
                    'rate' => configDB('store_default_return_not_delivery_rate'),
                    'allow_return' => 1,
                ],
                'delivered' => [
                    'before_days' => configDB('store_default_return_delivery_days'),
                    'after_days' => configDB('store_default_return_delivery_days'),
                    'delivered_checked' => 0,
                ],
            ],
            'warranty' => [
                'month' => configDB('store_default_warranty_months'),
                'conditions' => [],
            ],
        ];
        return $returnWarranty;
    }

    /**
     * 判断是否是seller
     * 如果是，返回customer对象
     * 如果不是，返回false
     *
     * @param int $sellerId
     *
     * @return Customer|false
     */
    public function isSeller($sellerId)
    {
        $key = [__CLASS__, __FUNCTION__, $sellerId];
        if ($data = $this->getRequestCachedData($key)) {
            return $data;
        }
        $sellerInfo = Customer::query()->alias('c')
            ->leftJoinRelations('seller as s')
            ->whereNotNull('s.customer_id')
            ->where('s.is_partner', 1)
            ->where('s.customer_id', $sellerId)
            ->first(['c.*']);
        if ($sellerInfo) {
            $this->setRequestCachedData($key, $data);
            return $sellerInfo;
        } else {
            return false;
        }
    }

    /**
     * 是否是外部seller（包括giga onside）
     *
     * @param int $sellerId
     * @return bool
     */
    public function isOutSeller($sellerId)
    {
        $sellerInfo = $this->isSeller($sellerId);
        if (!$sellerInfo) {
            return false;
        }
        return in_array($sellerInfo->accounting_type, CustomerAccountingType::outerAccount());
    }

    /**
     * 是否是外部seller(不包括giga onside)
     *
     * @param int $sellerId
     * @return bool
     */
    public function isOuterSellerNotGigaOnside($sellerId)
    {
        $sellerInfo = $this->isSeller($sellerId);
        if (!$sellerInfo) {
            return false;
        }
        return $sellerInfo->accounting_type == CustomerAccountingType::OUTER;
    }

    /**
     * 判断用户是否是指定国家的seller
     *
     * @param int $sellerId 需要判断的seller
     * @param int $checkCountry 需要判断的国家
     * @return bool
     */
    public function isCountrySeller(int $sellerId, int $checkCountry)
    {
        if (!$checkCountry) {
            return false;
        }
        $sellerInfo = $this->isSeller($sellerId);
        if (!$sellerInfo) {
            return false;
        }
        return $sellerInfo->country_id == $checkCountry;
    }

    /**
     * 是否是外部新seller
     * 判断条件是：产品首次财务入库时间在近2个月以内
     *
     * @param int $sellerId
     * @param int $month 月数
     * @return bool
     */
    public function isOutNewSeller($sellerId, $month = 2)
    {
        return $this->requestCachedData([__CLASS__, __FUNCTION__, $sellerId,$month], function () use ($sellerId,$month) {
            $sellerInfo = $this->isSeller($sellerId);
            if (!$sellerInfo) {
                return false;
            }
            // 判断是否是外部seller(包含America Native)
            if (!in_array($sellerInfo->accounting_type, CustomerAccountingType::outerAccount()) && $sellerInfo->accounting_type != CustomerAccountingType::AMERICA_NATIVE) {
                return false;
            }
            // 判断最早入库时间
            $receiveDate = CustomerPartnerToProduct::query()->alias('octp')
                ->leftJoinRelations('productExt as ope')
                ->where('octp.customer_id', $sellerId)
                ->whereNotNull('ope.receive_date')
                ->min('ope.receive_date');
            if (!$receiveDate) {
                // 如果不存在入库时间，则判定为不是new seller
                return false;
            }
            // 最早入库时间如果是两个月以内的则为新seller
            return Carbon::now()->subMonthNoOverflow($month)->lte(Carbon::parse($receiveDate));
        });

    }

    /**
     * 获取seller信息
     *
     * @param array $sellerId
     * @return Customer[]|Collection
     */
    public function getSellerInfo(array $sellerId)
    {
        return Customer::query()->alias('c')
            ->leftJoinRelations('seller as s')
            ->whereIn('c.customer_id', $sellerId)
            ->get(['c.customer_id', 's.screenname']);//需要其他参数自己加
    }

    /**
     * 获取seller的公司信息（公司名，公司地址，联系人，电话）
     * @param int $sellerId
     * @return array|string[]
     */
    public function getSellerCompanyInfo(int $sellerId): array
    {
        $telephone = Customer::query()->where('customer_id', $sellerId)->value('telephone');
        $company = $address = $name = '';

        $sellerClient = db('tb_seller_client_customer_map as cm')
            ->join('tb_seller_client as c', 'cm.seller_client_id', '=', 'c.id')
            ->where('cm.seller_id', $sellerId)
            ->where('c.account_status', YesNoEnum::YES)
            ->select(['cm.seller_client_id'])
            ->first();
        if (empty($sellerClient)) {
            return [$company, $address, $name, $telephone];
        }

        $contract = db('tb_seller_contract')
            ->where('seller_client_id', $sellerClient->seller_client_id)
            ->select('company_name', 'register_address')
            ->first();
        if (empty($contract)) {
            return [$company, $address, $name, $telephone];
        }

        $company = $contract->company_name;
        $address = $contract->register_address;

        $docker = db('tb_seller_client_docker')
            ->where('seller_client_id', $sellerClient->seller_client_id)
            ->select('name')
            ->first();
        if (empty($docker)) {
            return [$company, $address, $name, $telephone];
        }

        $name = $docker->name;

        return [$company, $address, $name, $telephone];
    }

    /**
     * 获取seller管理的所有buyer的简单信息
     * @param int $sellerId
     * @return array
     */
    public function getBuyersSimpleInfoBySellerId(int $sellerId): array
    {
        return BuyerToSeller::queryRead()->alias('bts')
            ->joinRelations('buyerCustomer as c')
            ->where('seller_id', $sellerId)
            ->where('buyer_control_status', YesNoEnum::YES)
            ->where('seller_control_status', YesNoEnum::YES)
            ->select(['c.customer_id', 'c.nickname', 'c.user_number'])
            ->get()
            ->toArray();
    }
}
