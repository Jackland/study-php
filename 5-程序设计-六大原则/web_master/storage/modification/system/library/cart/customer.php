<?php

namespace Cart;

use App\Enums\Buyer\BuyerType;
use App\Enums\Common\CountryEnum;
use App\Enums\Customer\CustomerAccountingType;
use App\Listeners\Events\CustomerLoginFailedEvent;
use App\Listeners\Events\CustomerLoginSuccessEvent;
use App\Listeners\Events\CustomerLogoutAfterEvent;
use App\Listeners\Events\CustomerLogoutBeforeEvent;
use App\Models\Customer\Customer as CustomerModel;
use App\Services\Customer\CustomerService;
use App\Services\Stock\BuyerStockService;
use Exception;
use Illuminate\Database\Capsule\Manager;
use Registry;

/**
 * Class Customer
 * @package Cart
 * @property \DB\mPDO $db
 * @property Manager $orm
 */
class Customer
{
    /**
     * @var CustomerModel
     */
    private $model;

    public function __construct(Registry $registry)
    {
        $this->db = $registry->get('db');
        $this->orm = $registry->get('orm');

        $this->model = new CustomerModel();
        $this->initModel();
    }

    public function login($email, $password, $noPassword = false)
    {
        $data = app(CustomerService::class)->loginByEmail($email, $password, $noPassword);
        if (!$data) {
            return false;
        }
        $this->afterLogin($data);
        return true;
    }

    /**
     * 按照 id 进行登录，一般用于代码中的模拟某个用户登录
     * 使得后续的 customer() 等操作正常
     * @param int $customerId
     * @return bool
     */
    public function loginById(int $customerId)
    {
        $model = CustomerModel::find($customerId);
        if ($model) {
            $this->afterLogin($model);
            return true;
        }
        return false;
    }

    /**
     * 登录后操作
     * @param CustomerModel $model
     */
    private function afterLogin(CustomerModel $model)
    {
        session()->set('customer_id', $model->customer_id);

        CustomerModel::query()
            ->where('customer_id', $model->customer_id)
            ->update([
                'language_id' => (int)configDB('config_language_id'),
                'ip' => request()->getUserIp('127.0.0.1'),
            ]);

        $this->initModel();
    }

    public function logout()
    {
        event(new CustomerLogoutBeforeEvent($this));

        $session = session();
        $session->remove('customer_id');
        $session->remove('openModal');
        $session->remove('seller_authorized_menu_ids');

        $this->model = new CustomerModel();
        $this->_customer_id = null;

        event(new CustomerLogoutAfterEvent());
    }

    private function initModel()
    {
        if (!session()->has('customer_id')) {
            // session 中不存在时未登录
            return;
        }
        $this->model = CustomerModel::query()
            ->where('customer_id', session('customer_id'))
            ->where('status', 1)
            ->first();
        if (!$this->model) {
            // session 中的id用户未找到
            $this->logout();
            return;
        }
        // 记录ip变更
        $userIp = request()->getUserIp('127.0.0.1');
        $ipExist = db('oc_customer_ip')
            ->where('customer_id', $this->getId())
            ->where('ip', $userIp)
            ->exists();
        if (!$ipExist) {
            db('oc_customer_ip')->insert([
                'customer_id' => $this->getId(),
                'ip' => $userIp,
                'date_added' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * @return CustomerModel|null
     */
    public function getModel()
    {
        return $this->model;
    }

    public function getProductSellerMap($customer_id)
    {
        //edit lxx 2019-04-05 只查询采购订单库存
        $productCostMap = array();
        $costResult = $this->db->query("SELECT scd.`sku_id`, group_concat(distinct oc.screenname) as sellername FROM `tb_sys_cost_detail` scd,oc_customerpartner_to_customer oc WHERE scd.seller_id = oc.customer_id and scd.`buyer_id` = " . $customer_id . " AND scd.`onhand_qty` > 0 AND scd.type = 1 GROUP BY scd.`sku_id`")->rows;
        if ($costResult && count($costResult) > 0) {
            foreach ($costResult as $costData) {
                $productCostMap[$costData['sku_id']] = $costData['sellername'];
            }
        }
        return $productCostMap;
    }

    /**
     * 获取buyer可绑定的闲余库存
     * @param int $customer_id buyerID
     * @param int|null $oc_order_id 指定的采购号，可不填。
     * @return array
     * @throws Exception
     */
    public function getProductCostMap($customer_id, $oc_order_id = null)
    {
        // 获取在库数量
        $productCostMap = [];
        $sql = "SELECT
                    scd.`sku_id`,
                    sum(
                        scd.original_qty - ifnull(soa.associatedQty, 0) - ifnull(t.qty,0)
                    ) qty
                FROM
                    `tb_sys_cost_detail` scd
                LEFT JOIN tb_sys_receive_line srl ON srl.id = scd.source_line_id
                LEFT JOIN oc_order_product ocp ON (
                    ocp.order_id = srl.oc_order_id
                    AND scd.sku_id = ocp.product_id
                )
                LEFT JOIN (
                    SELECT
                        sum(qty) AS associatedQty,
                        order_product_id
                    FROM
                        tb_sys_order_associated
						where buyer_id = " . $customer_id . "
                    GROUP BY
                        order_product_id
                ) soa ON ocp.order_product_id = soa.order_product_id
                LEFT JOIN
                (
                    SELECT
                     ro.order_id,rop.product_id,sum(rop.quantity) as qty
                   FROM
                     oc_yzc_rma_order ro
                   LEFT JOIN oc_yzc_rma_order_product rop ON rop.rma_id = ro.id
                   WHERE
                       ro.buyer_id = " . $customer_id . "
                   AND ro.cancel_rma = 0
                   AND status_refund <> 2
                   AND ro.order_type = 2
                   group by rop.product_id,ro.order_id
                ) t on t.product_id=scd.sku_id and srl.oc_order_id=t.order_id ";
        if (!empty($oc_order_id)) {
            $sql .= " WHERE scd.`buyer_id` = " . $customer_id . " AND srl.oc_order_id = " . (int)$oc_order_id . " AND scd.`onhand_qty` > 0 AND scd.type = 1 GROUP BY scd.sku_id ";
        } else {
            $sql .= " WHERE scd.`buyer_id` = " . $customer_id . " AND scd.`onhand_qty` > 0 AND scd.type = 1 GROUP BY scd.sku_id ";
        }

        $costResult = $this->db->query($sql)->rows;
        // 添加buyer库存锁定
        if ($costResult && count($costResult) > 0) {
            $productCostMap = array_combine(array_column($costResult, 'sku_id'), array_column($costResult, 'qty'));
            $lockArr = app(BuyerStockService::class)
                ->getLockQuantityIndexByProductIdByProductIds(array_column($costResult, 'sku_id'), $customer_id);
            foreach ($productCostMap as $skuId => $qty) {
                $productCostMap[$skuId] = max($qty - ($lockArr[$skuId] ?? 0), 0);
            }
        }

        return $productCostMap;
    }

    /**
     * 采购订单已经申请退返品
     * @param int $customer_id
     * @return array
     */
    public function getPurchaseRmaCostMap($customer_id)
    {
        $PurchaseRmaCostMap = array();
        $sql = "SELECT
                    rop.product_id,ifnull(sum(rop.quantity),0) as qty
                FROM
                    oc_yzc_rma_order ro
                LEFT JOIN oc_yzc_rma_order_product rop ON rop.rma_id = ro.id
                WHERE
                    ro.buyer_id = " . $customer_id . "
                AND ro.cancel_rma = 0
                AND seller_status <> 3
                AND ro.order_type = 2
                group by rop.product_id";
        $costResult = $this->db->query($sql)->rows;
        if ($costResult && count($costResult) > 0) {
            foreach ($costResult as $costData) {
                $PurchaseRmaCostMap[$costData['product_id']] = $costData['qty'];
            }
        }
        return $PurchaseRmaCostMap;
    }

    public function isLogged()
    {
        return !!$this->model->customer_id;
    }

    /**
     * 用于临时记录 customer_id，配合 setId 和 getId 使用
     * @see setId()
     * @var string|int
     */
    private $_customer_id = null;

    public function getId()
    {
        if ($this->_customer_id) {
            return $this->_customer_id;
        }
        return $this->model->customer_id;
    }

    /**
     * 设置 id
     * 极其不建议使用！！！
     * 目前保留仅仅暂时为了减让旧代码
     * @param int $id
     */
    public function setId($id)
    {
        $this->_customer_id = $id;
    }

    //判断当前用户是否有
    public function has_cwf_freight()
    {
        if ($this->getCountryId() && $this->getCountryId() == CountryEnum::AMERICA && !$this->isCollectionFromDomicile()) {
//            if ($this->getGroupId() == 23 || in_array($this->getId(), array(340, 491, 631, 838)) || in_array($this->getId(), array(694, 696, 746, 907, 908))) {
            return true;
//            }
        }
        return false;
    }

    // 是测试店铺或服务、保证金店铺
    public function is_test_store()
    {
        if ($this->getGroupId() == 23 || in_array($this->getId(), array(340, 491, 631, 838)) || in_array($this->getId(), array(694, 696, 746, 907, 908))) {
            return true;
        }
        return false;
    }

    /**
     * [isCollectionFromDomicile description] 验证是否是上门取货的buyer
     * @return boolean
     */
    public function isCollectionFromDomicile()
    {
        return $this->model->buyer_type === BuyerType::PICK_UP && !$this->isPartner();
    }

    /**
     * 是否是 seller
     * @return bool
     */
    public function isPartner(): bool
    {
        if (!$this->isLogged()) {
            return false;
        }
        return $this->model->is_partner;
    }

    public function getCountryId()
    {
        return $this->model->country_id;
    }

    public function getFirstName()
    {
        return $this->model->firstname;
    }

    public function getLogisticsCustomerName()
    {
        return $this->model->logistics_customer_name;
    }

    /**
     * [getTrusteeship description] Buyer是否在平台上托管
     * @return int
     */
    public function getTrusteeship()
    {
        return $this->model->trusteeship;
    }

    public function getLastName()
    {
        return $this->model->lastname;
    }

    public function getGroupId()
    {
        return $this->model->customer_group_id;
    }

    public function getEmail()
    {
        return $this->model->email;
    }

    public function getTelephone()
    {
        return $this->model->telephone;
    }

    public function getValidMaskTelephone()
    {
        return $this->model->valid_mask_telephone;
    }

    public function getNewsletter()
    {
        return $this->model->newsletter;
    }

    public function getAddressId()
    {
        return $this->model->address_id;
    }

    public function getUserMode()
    {
        return $this->model->user_mode;
    }

    public function getAdditionalFlag()
    {
        return $this->model->additional_flag;
    }

    public function getServiceAgreement()
    {
        return $this->model->service_agreement;
    }

    public function getBalance()
    {
        if (!$this->isLogged()) {
            return 0;
        }

        $query = $this->db->query("SELECT SUM(amount) AS total FROM " . DB_PREFIX . "customer_transaction WHERE customer_id = '" . $this->getId() . "'");
        return $query->row['total'];
    }

    public function getRewardPoints()
    {
        if (!$this->isLogged()) {
            return 0;
        }

        $query = $this->db->query("SELECT SUM(points) AS total FROM " . DB_PREFIX . "customer_reward WHERE customer_id = '" . $this->getId() . "'");
        return $query->row['total'];
    }

    public function getLineOfCredit()
    {
        if (!$this->isLogged()) {
            return 0.00;
        }

        $query = $this->db->query("SELECT TRUNCATE(line_of_credit,2) as credit  FROM " . DB_PREFIX . "customer WHERE customer_id = '" . $this->getId() . "' for update");
        if (isset($query->row['credit'])) {
            return $query->row['credit'];
        } else {
            return 0.00;
        }
    }

    /**
     * 获取用户昵称
     */
    public function getNickName()
    {
        if (empty($this->model->nickname) && empty($this->model->user_number)) {
            return '';
        }
        return $this->model->nickname . '(' . $this->model->user_number . ')';
    }

    /**
     * 获取用户 user_number
     * @return mixed
     */
    public function getUserNumber()
    {
        return $this->model->user_number;
    }

    /**
     * 该用户是否需要展示服务费
     * @param int $customer_id
     * @return mixed
     * @author xxl
     */
    public function showServiceFee($customer_id)
    {
        $result = $this->db->query('select COUNT(*) as total from oc_customer where country_id in(222,81) and customer_id=' . $customer_id);
        return $result->row['total'];
    }

    public function getCustomerGroupName($customer_id)
    {
        $result = $this->db->query("select name from oc_customer oc LEFT JOIN oc_customer_group_description cgd on cgd.customer_group_id=oc.customer_group_id WHERE oc.customer_id=" . intval($customer_id));
        return $result->row['name'];
    }

    /**
     * @return int
     * @see CustomerAccountingType
     */
    public function getAccountType()
    {
        return $this->model->accounting_type;
    }

    /**
     * 当前国家是否为欧洲国家
     * @return bool
     */
    public function isEurope()
    {
        return in_array($this->getCountryId(), EUROPE_COUNTRY_ID);
    }


    public function getLineOfCreditBySeller($customer_id)
    {
        $query = $this->db->query("SELECT TRUNCATE(ifnull(line_of_credit,0),2) as credit  FROM " . DB_PREFIX . "customer WHERE customer_id = '" . (int)$customer_id . "' for update");
        if (isset($query->row['credit'])) {
            return $query->row['credit'];
        }
    }

    /**
     *  当前国家是否为美国
     * @return bool
     */
    public function isUSA()
    {
        return $this->getCountryId() == AMERICAN_COUNTRY_ID;
    }

    /**
     * 当前国家是否为日本
     * @return bool
     */
    public function isJapan()
    {
        return $this->getCountryId() == JAPAN_COUNTRY_ID;
    }

    /**
     * 当前用户是否为外部seller
     * @return bool
     */
    public function isOuterAccount(): bool
    {
        return ($this->isPartner() && $this->getAccountType() == 2) || $this->isGigaOnsiteSeller();
    }

    /**
     * 当前用户是否为内部seller
     * @return bool
     */
    public function isInnerAccount(): bool
    {
        return $this->isPartner() && $this->getAccountType() == 1;
    }

    /**
     * 当前用户是否为外部Buyer
     * @return bool
     */
    public function isOuterBuyer(): bool
    {
        return (!$this->isPartner()) && $this->getAccountType() == 2;
    }

    /**
     * 当前用户是否为内部Buyer
     * @return bool
     */
    public function isInnerBuyer(): bool
    {
        return (!$this->isPartner()) && $this->getAccountType() == 1;
    }

    /**
     * 当前用户是否为giga onsite seller
     * @return bool
     */
    public function isGigaOnsiteSeller(): bool
    {
        return $this->isPartner() && $this->getAccountType() == 6;
    }

    /**
     * 1、自动购买产销异体，2、自动购买纯销售用户，3、自动购买产销一体，4、非自动购买用户.....
     * 当前用户属性
     */
    public function getAccountAttributes()
    {
        return $this->model->account_attributes;
    }

    /**
     * 当前用户是否是 内部 自动购买-产销异体 or 内部 自动购买-FBA自提
     * */
    public function innerAutoBuyAttr1()
    {
        if (1 == $this->getAccountType() && 1 == $this->getAccountAttributes()) {//自动购买 产销异体
            return true;
        }
        if (3 == $this->getAccountType() && 8 == $this->getAccountAttributes()) {//自动购买 产销异体 测试账号
            return true;
        }
        if (1 == $this->getAccountType() && 14 == $this->getAccountAttributes()) {//内部 自动购买 FBA自提
            return true;
        }

        return false;
    }

    /**
     * 当前用户是否是 内部 自动购买-产销异体 or 内部 自动购买-FBA自提
     * @param int $buyerId
     * @return bool
     * @throws Exception
     */
    public function innerAutoBuyAttr1ByBuyerId($buyerId)
    {
        $sql = 'select accounting_type,account_attributes from oc_customer where customer_id=' . $buyerId;
        $info = $this->db->query($sql);
        if ($info->row) {
            if (1 == $info->row['accounting_type'] && 1 == $info->row['account_attributes']) {
                return true;
            }
            if (3 == $info->row['accounting_type'] && 8 == $info->row['account_attributes']) {//自动购买 产销异体 测试账号
                return true;
            }
            if (1 == $info->row['accounting_type'] && 14 == $info->row['account_attributes']) {//内部 自动购买 FBA自提
                return true;
            }
        }
        return false;
    }


    /**
     * 获取用户Airwallex账户余额
     * @param $airwallexId
     * @return float
     */
    public function getAirwallexAccountBalance($airwallexId)
    {
        $postData = array(
            'buyerId' => $this->getId(),
            'behalfOf' => $airwallexId
        );
        $airwallexBalance = post_url(URL_YZCM . '/api/airwallex/currentBalances', http_build_query($postData));
        // 获取账户余额
        $airwallexBalance = json_decode($airwallexBalance, true);
        $airwallexAccountBalance = 0.0;
        if ($airwallexBalance['code'] == 200) {
            $balance = $airwallexBalance['data'];
            // 当前Buyer国别
            $countryId = $this->getCountryId();
            foreach ($balance as $balanceData) {
                if ($countryId == 223) {
                    // 美国
                    if ($balanceData['currency'] == 'USD') {
                        $airwallexAccountBalance = $balanceData['available_amount'];
                        break;
                    }
                } else if ($countryId == 107) {
                    // 日本
                    if ($balanceData['currency'] == 'JPY') {
                        $airwallexAccountBalance = $balanceData['available_amount'];
                        break;
                    }
                } else if ($countryId == 222) {
                    // 英国
                    if ($balanceData['currency'] == 'GBP') {
                        $airwallexAccountBalance = $balanceData['available_amount'];
                        break;
                    }
                } else if ($countryId == 81) {
                    // 德国
                    if ($balanceData['currency'] == 'EUR') {
                        $airwallexAccountBalance = $balanceData['available_amount'];
                        break;
                    }
                }
            }
        }
        return $airwallexAccountBalance;
    }


    /**
     * 判断当前账号是否为测试账号
     * @return bool
     * user：wangjinxin
     * date：2020/6/15 16:19
     */
    public function isTesterAccount(): bool
    {
        return $this->getAccountType() == 3;
    }


    /**
     * 判断当前用户 非内部
     * @return bool
     * @deprecated
     */
    public function isNonInnerAccount(): bool
    {
        // 美国的非内部和测试seller
        return ($this->isUSA() && ($this->getAccountType() != 1)) || ($this->isPartner() && $this->isTesterAccount());
    }

    /**
     * 是否是内部FBA
     * */
    public function isInnerFBA()
    {
        if (1 == $this->getAccountType() && 14 == $this->getAccountAttributes()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取customer_exts表中的配置值
     *
     * @param int $type 目前支持 1：是否支持自动购买  2：是否支持导单  3：是否需要二级密码校验
     *
     * @return mixed|null 如果表中没有记录返回null， 返回值没有转换成bool是因为为了兼容以后其他配置值的数据类型
     *                    如果类型不在支持范围内，也会返回null
     */
    public function getCustomerExt(int $type)
    {
        $typeFields = [
            1 => 'auto_buy',
            2 => 'import_order',
            3 => 'second_passwd',
        ];
        if (!array_key_exists($type, $typeFields)) {
            return null;
        }
        $field = $typeFields[$type];
        return $this->orm->table(DB_PREFIX . 'customer_exts')->where('customer_id', $this->getId())->value($field);
    }

    /**
     * @Author xxl
     * @Description 查询账号登录ip
     * @Date 15:27 2020/10/14
     * @return String ip
     **/
    public function getIp()
    {
        return $this->model->ip;
    }

    /**
     * 是否是欧盟buyer
     * @return bool
     */
    public function isEuVatBuyer(): bool
    {
        if (!$this->isLogged()) {
            return false;
        }
        return $this->model->is_eu_vat_buyer;
    }
}
