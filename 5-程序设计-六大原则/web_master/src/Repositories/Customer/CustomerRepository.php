<?php

namespace App\Repositories\Customer;

use App\Enums\Buyer\BuyerType;
use App\Enums\Common\CountryEnum;
use App\Enums\Common\YesNoEnum;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Customer\CustomerGroup;
use App\Models\Bd\LeasingManager;
use App\Models\Buyer\Buyer;
use App\Models\Buyer\BuyerAccountManager;
use App\Models\Buyer\BuyerToSeller;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerExts;
use App\Models\Customer\CustomerStockBlackList;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Link\OrderAssociated;
use App\Models\User\UserToCustomer;
use Framework\Model\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class CustomerRepository
{
    /**
     * 检查是否是 seller
     * @param int $customerId
     * @return bool
     */
    public function checkIsSeller(int $customerId): bool
    {
        return CustomerPartnerToCustomer::query()->where('customer_id', $customerId)->exists();
    }

    /**
     * 获取属于giga onsite分组的所有有效seller
     * oc_customer_exts 中存在此中可能性无agent_operation
     * @return Collection
     */
    public function getGigaOnsiteSellerList(): Collection
    {
        return CustomerPartnerToCustomer::query()
            ->alias('a')
            ->leftJoin('oc_customer as b', 'a.customer_id', '=', 'b.customer_id')
            ->leftJoin('oc_customer_exts as e', 'e.customer_id', '=', 'a.customer_id')
            ->where('b.status', 1)
            ->where('a.is_partner', 1)
            ->where('b.accounting_type', CustomerAccountingType::GIGA_ONSIDE)
            ->select(['a.screenname', 'b.user_number', 'b.customer_id', 'b.email'])
            ->selectRaw('ifnull(e.agent_operation,0) as agent_operation')
            ->get();
    }

    /**
     * 获取中国 buyer
     * BD 账户关联的 buyer
     * @param bool $asQuery
     * @return array|BuyerAccountManager|Builder
     */
    public function getChinaBuyerIds(bool $asQuery = false)
    {
        $bdIds = LeasingManager::query()->alias('a')
            ->where('country_id', CountryEnum::CHINA)
            ->where('status', 1)
            ->select('customer_id')
            ->distinct()
            ->pluck('customer_id')->toArray();
        if (!$bdIds) {
            return [];
        }
        $query = BuyerAccountManager::query()
            ->select('BuyerId')
            ->whereIn('AccountId', $bdIds);
        if ($asQuery) {
            return $query;
        }
        return $query->get()->pluck('BuyerId')->toArray();
    }

    /**
     * 获取中国 seller
     * 账户经理为中国的 buyer 帐号，所关联的 seller
     * @param bool $asQuery
     * @return array|BuyerToSeller|Builder
     */
    public function getChinaSellerIds(bool $asQuery = false)
    {
        $buyerIds = UserToCustomer::query()
            ->select('account_manager_id')
            ->where('country_id', CountryEnum::CHINA)
            ->distinct()
            ->pluck('account_manager_id')->toArray();
        if (!$buyerIds) {
            return [];
        }
        $query = BuyerToSeller::query()
            ->select('seller_id')
            ->whereIn('buyer_id', $buyerIds) // 中国
            ->where('seller_control_status', 1) // 关系正常
            ->where('buyer_control_status', 1) // 关系正常
        ;
        if ($asQuery) {
            return $query;
        }
        return $query->get()->pluck('seller_id')->toArray();
    }

    /**
     * 校验buyer隶属于的账户经理国别
     * @param int $sellerId
     * @param int $countryId 校验的账户经理的countryID
     * @return bool
     * @see CountryEnum
     */
    public function checkSellerAccountManagerCountry(int $sellerId, int $countryId): bool
    {
        return BuyerToSeller::query()->alias('bts')
            ->leftJoin('oc_sys_user_to_customer as utc', 'utc.account_manager_id', '=', 'bts.buyer_id')
            ->where('bts.seller_id', $sellerId)
            ->where('bts.seller_control_status', 1) // 关系正常
            ->where('bts.buyer_control_status', 1) // 关系正常
            ->where('utc.country_id', $countryId)
            ->exists();
    }

    /**
     * 获取某个销售订单关联的seller列表
     * @param int $salesOrderId
     * @return array
     */
    public function calculateSellerListBySalesOrderId(int $salesOrderId)
    {
        return OrderAssociated::query()
            ->alias('ass')
            ->leftJoin('oc_customer as oc', 'ass.seller_id', '=', 'oc.customer_id')
            ->select(['ass.seller_id', 'oc.accounting_type'])
            ->where([
                'ass.sales_order_id' => $salesOrderId,
            ])
            ->groupBy('ass.seller_id')
            ->get()
            ->toArray();
    }

    /**
     * 获取客户号
     * @param int $customerId
     * @return mixed
     */
    public function getCustomerNumber($customerId)
    {
        $customer = Customer::query()
            ->select(['firstname', 'lastname'])
            ->where(['customer_id' => $customerId])
            ->first();

        return $customer ? $customer->firstname . $customer->lastname : '';
    }

    /**
     * 获取账户经理
     * @param int $sellerId
     * @return string
     */
    public function getAccountManager($sellerId)
    {
        $customer = db('tb_seller_client_customer_map as scc')
            ->leftJoin('tb_seller_account_apply as ssa', 'ssa.id', '=', 'scc.apply_id')
            ->leftJoin('tb_sys_user as su', 'su.id', '=', 'ssa.manager_id')
            ->where('scc.seller_id', $sellerId)
            ->value('username');
        if ($customer) {
            return $customer;
        }
        $customer = db('oc_buyer_to_seller as bts')
            ->select(['oc.firstname', 'oc.lastname'])
            ->leftJoin('oc_customer as oc', 'oc.customer_id', '=', 'bts.buyer_id')
            ->where('oc.customer_group_id', 14)
            ->where('bts.seller_id', $sellerId)
            ->first();
        return $customer ? $customer->firstname . $customer->lastname : '';
    }

    /**
     * 确保是 customer 模型
     * @param int|Customer|\Cart\Customer $customer
     * @return Customer
     */
    public function ensureCustomerModel($customer): Customer
    {
        if (is_int($customer)) {
            $customer = Customer::find($customer);
        }
        if ($customer instanceof \Cart\Customer) {
            $customer = $customer->getModel();
        }
        if (!$customer instanceof Customer) {
            throw new InvalidArgumentException('unknown customer');
        }

        return $customer;
    }

    /**
     * 手机号是否需要验证
     * @param int|Customer|\Cart\Customer $customer
     * @return bool
     */
    public function isPhoneNeedVerify($customer): bool
    {
        $customer = $this->ensureCustomerModel($customer);

        if ($customer->telephone_verified_at > 0) {
            // 已经验证过的不需要验证
            return false;
        }
        if (in_array($customer->accounting_type, [
            CustomerAccountingType::OUTER,
            CustomerAccountingType::GIGA_ONSIDE,
            CustomerAccountingType::AMERICA_NATIVE,
        ])) {
            // 特定帐号类型的需要验证
            return true;
        }
        if (in_array($customer->customer_group_id, [
            CustomerGroup::SALE_BD,
        ])) {
            // 特定的帐号分组
            return true;
        }

        return false;
    }

    /**
     * 手机号是否可以修改
     * @param int|Customer|\Cart\Customer $customer
     * @param bool $includeVerify
     * @return bool
     */
    public function isPhoneCanChange($customer, bool $includeVerify = false): bool
    {
        $customer = $this->ensureCustomerModel($customer);

        if ($customer->telephone_verified_at > 0) {
            // 验证过的
            return true;
        }
        if ($includeVerify) {
            // 检查是否需要验证
            return $this->isPhoneNeedVerify($customer);
        }

        return false;
    }


    /**
     * 获取不同的产品or seller是否是不支持囤货的名单
     * @param array $data [data1,data2,data3]
     * @param int $type 0 默认为产品 1默认为seller
     * @return array
     */
    public function getUnsupportStockData(array $data, int $type = 0): array
    {
        // 上门取货不限制囤货黑名单
        if (customer()->isCollectionFromDomicile()) {
            return [];
        }
        $builder = CustomerExts::query()->alias('ce');
        if ($type) {
            return $builder->whereIn('csbl.customer_id', $data)
                ->where('ce.not_support_store_goods', YesNoEnum::YES)
                ->select('ce.customer_id')
                ->pluck('customer_id')
                ->toArray();
        }

        return $builder->leftJoinRelations(['customerpartnerToProduct as ctp'])
            ->whereIn('ctp.product_id', $data)
            ->where('ce.not_support_store_goods', YesNoEnum::YES)
            ->select('ctp.product_id')
            ->distinct()
            ->pluck('product_id')
            ->toArray();

    }

    /**
     * 手机号是否重复
     * @param string $phone
     * @param int|Customer|\Cart\Customer $customer
     * @return bool
     */
    public function isPhoneExist(string $phone, $customer): bool
    {
        $customer = $this->ensureCustomerModel($customer);

        if ($customer->is_partner) {
            // seller，同国家手机号不重复
            return CustomerPartnerToCustomer::query()->alias('a')
                ->leftJoinRelations('customer as b')
                ->where('b.telephone', $phone) // 同手机号
                ->where('b.country_id', $customer->country_id) // 同国家
                ->where('b.customer_id', '!=', $customer->customer_id) // 非自己
                ->exists();
        }

        // buyer，同国家同账号类型（上门取货、一件代发）不重复
        $buyers = Buyer::query()->alias('a')
            ->leftJoinRelations('customer as b')
            ->where('b.telephone', $phone) // 同手机号
            ->where('b.country_id', $customer->country_id) // 同国家
            ->where('b.customer_id', '!=', $customer->customer_id) // 非自己
            ->get(['b.customer_group_id'])->toArray();
        if (!$buyers) {
            return false;
        }
        $exist = false;
        foreach ($buyers as $buyer) {
            // buyer 同帐号类型的
            $buyerType = in_array($buyer['customer_group_id'], COLLECTION_FROM_DOMICILE)
                ? BuyerType::PICK_UP
                : BuyerType::DROP_SHIP;
            if ($buyerType === $customer->buyer_type) {
                $exist = true;
                break;
            }
        }
        return $exist;
    }

    /**
     * customer 是否可以自己修改密码
     * @param int|Customer|\Cart\Customer $customer
     * @return bool
     */
    public function isPasswordCanChangeByCustomerSelf($customer): bool
    {
        // 判断逻辑同手机号是否可以修改的规则
        return $this->isPhoneCanChange($customer, true);
    }
}
