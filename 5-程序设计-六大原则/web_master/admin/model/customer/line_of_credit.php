<?php

use App\Components\UniqueGenerator\Enums\ServiceEnum;
use App\Enums\Charge\ChargeType;
use App\Enums\CreditLine\SysCreditLintPlatformTypeLevel;
use App\Models\Pay\LineOfCreditRecord;
use App\Repositories\Common\SerialNumberRepository;

/**
 * Class  ModelCustomerLineOfCredit
 */
class ModelCustomerLineOfCredit extends Model{
    const CURRENCY_USD = 'USD';
    const CURRENCY_GBP = 'GBP';
    const CURRENCY_EUR = 'EUR';
    const PAYMENT_TYPE_0 = 0;
    const PAYMENT_TYPE_1 = 1;
    const PAYMENT_TYPE_2 = 2;
    const PAYONEER = 'Payoneer';
    public function getLineOfCreditAmendantRecords($customerId){
        if($customerId){
            $sql = "SELECT COUNT(*) AS total FROM tb_sys_credit_line_amendment_record WHERE type_id in (" . ChargeType::getAdminTypes(true) . ") AND customer_id = " . (int)$customerId;
            $query = $this->db->query($sql);
            return get_value_or_default($query->row,'total',0);
        }else{
            return 0;
        }
    }

    public function getCompanyAccountList()
    {
        $list = [];
        $ret = $this->orm->table('tb_sys_company_account')->where('status', 0)->get();
        // 需要做国别&收款还是付款的区分
        foreach ($ret as $item) {
            //item 中需要处理一下
            if ($item->bank_name_simple == self::PAYONEER) {
                // collection_account  ****hk_g@163.com
                $item->account = '****' . substr($item->collection_account, strpos($item->collection_account, '@') - 4);
            } else {
                // 截取后四位
                $item->account = '****' . substr($item->collection_account, -4);
            }
            $item->collection_name = empty($item->collection_name) ? '' : $item->collection_name;

            if ($item->collection_payment_type != self::PAYMENT_TYPE_1) {
                $list['get'][] = $item;
            }
            if ($item->collection_payment_type != self::PAYMENT_TYPE_0) {
                $list['paid'][] = $item;
            }

        }
        return $list;
    }

    /**
     * @deprecated
     * 新方法使用-PlatformTypeRepository.getCreditLinePlatformTypeMap
     *
     * @param int $collectionPaymentType
     * @param int $accountType
     * @param int $status
     * @return array
     */
    public function getCreditLinePlatformTypeMap(int $collectionPaymentType, int $accountType, int $status)
    {
        return $this->orm->table('tb_sys_credit_line_platform_type')
            ->where('collection_payment_type', $collectionPaymentType)
            ->where('account_type', $accountType)
            ->where('status', $status)
            ->where('type_level', SysCreditLintPlatformTypeLevel::FIRST)
            ->pluck('name', 'type')
            ->toArray();
    }

    public function getUserNameById($userId,$type){
        if(isset($userId)){
            if($type == 1 || 6 == $type) {
                $sql = "SELECT CONCAT(firstname,' ',lastname) AS name FROM oc_user WHERE user_id = " . (int)$userId;
            }elseif($type == 5){
                $sql = "SELECT CONCAT(firstname,' ',lastname) AS name FROM oc_user WHERE user_id = " . (int)$userId;
            }else{
                $sql = "SELECT screenname AS name FROM oc_customerpartner_to_customer WHERE customer_id = " . (int)$userId;
            }
            $query = $this->db->query($sql);
            return $query->row['name'] ?? '';
        }
    }

    public function getLineOfCreditAmendantRecordRow($data = array()){
        $sql = "
    SELECT
        ar.*
        ,currency.code AS currency_name
    FROM tb_sys_credit_line_amendment_record AS ar
    LEFT JOIN oc_customer AS c ON c.customer_id=ar.customer_id
    LEFT JOIN oc_country AS country ON country.country_id=c.country_id
    LEFT JOIN oc_currency AS currency ON currency.currency_id=country.currency_id";

        if(isset($data['customer_id'])){
            $sql .= " WHERE ar.customer_id = " . (int)$data['customer_id'] . " AND ar.type_id in(" . ChargeType::getAdminTypes(true) . ") ";
        }else{
            $sql .= " WHERE 1=2";
        }

        $sql .= " ORDER BY ar.date_added DESC ";

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getTotalCustomers($data = array())
    {
        $sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customer WHERE 1=1 ";

        if (!empty($data['name'])) {
            $sql .= " AND CONCAT(firstname, ' ', lastname) LIKE '%" . $this->db->escape($data['name']) . "%'";
        }

        if (!empty($data['email'])) {
            $sql .= " AND email LIKE '" . $this->db->escape($data['email']) . "%'";
        }

        if (isset($data['status']) && $data['status'] !== '') {
            $sql .= " AND status = '" . (int)$data['status'] . "'";
        }

        if (isset($data['role']) && $data['role'] !== '') {
            if($data['role'] == '1'){
                $sql .= " AND customer_id IN (SELECT customer_id FROM oc_customerpartner_to_customer)";
            }
            if($data['role'] == '0'){
                $sql .= " AND customer_id NOT IN (SELECT customer_id FROM oc_customerpartner_to_customer)";
            }
        }

        $query = $this->db->query($sql);

        return $query->row['total'];
    }

    public function getCustomers($data = array())
    {
        $sql = "
    SELECT
        c.*
        , cgd.*
        , CONCAT(c.firstname, ' ', c.lastname) AS name
        , cgd.name AS customer_group
        , (CASE WHEN ctc.`is_partner` IS NULL THEN 0 ELSE ctc.`is_partner` END) AS is_partner
        , IF(c.country_id=222, 'UK', country.iso_code_2) AS country_name
        , currency.`code` AS currency_code
    FROM " . DB_PREFIX . "customer c
    LEFT JOIN " . DB_PREFIX . "customer_group_description cgd ON (c.customer_group_id = cgd.customer_group_id)
    LEFT JOIN " . DB_PREFIX . "customerpartner_to_customer ctc ON c.`customer_id` = ctc.`customer_id`
    LEFT JOIN " . DB_PREFIX . "country AS country ON country.country_id=c.country_id
    LEFT JOIN " . DB_PREFIX . "currency AS currency ON currency.currency_id=country.currency_id ";

        $sql .= " WHERE cgd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

        if (!empty($data['name'])) {
            $sql .= " AND CONCAT(c.firstname, ' ', c.lastname) LIKE '%" . $this->db->escape($data['name']) . "%'";
        }

        if (!empty($data['email'])) {
            $sql .= " AND c.email LIKE '" . $this->db->escape($data['email']) . "%'";
        }

        if (isset($data['status']) && $data['status'] !== '') {
            $sql .= " AND c.status = '" . (int)$data['status'] . "'";
        }

        if (isset($data['role']) && $data['role'] !== '') {
            if($data['role'] == '1'){
                $sql .= " AND c.customer_id IN (SELECT customer_id FROM oc_customerpartner_to_customer)";
            }
            if($data['role'] == '0'){
                $sql .= " AND c.customer_id NOT IN (SELECT customer_id FROM oc_customerpartner_to_customer)";
            }
        }

        $sql .= " ORDER BY name";

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function updateCustomerInfo($updateDate)
    {
        $sql = "UPDATE `" . DB_PREFIX . "customer` oc SET oc.`line_of_credit` = " . $updateDate['balance'] . " WHERE oc.`customer_id` = " . $updateDate['customerId'];
        $this->db->query($sql);
    }

    public function saveAmendantRecord($updateDate)
    {
        $memo = "";
        if(isset($updateDate['memo']) && $updateDate['memo']!=''){
            $memo = $updateDate['memo'];
        }
        $serialNumber = SerialNumberRepository::getDateSerialNumber(ServiceEnum::AMENDMENT_RECORD_NO);
        $data['serial_number'] = $serialNumber;
        $data['customer_id'] = (int)$updateDate['customerId'];
        $data['old_line_of_credit'] = (double)$updateDate['oldBalance'];
        $data['new_line_of_credit'] = (double)$updateDate['balance'];
        $data['date_added'] = date('Y-m-d H:i:s');
        $data['operator_id'] = (int)$updateDate['operatorId'];
        $data['memo'] = $memo;

        if(!isset($updateDate['typeId'])||!$updateDate['typeId']){
            $data['type_id'] = 1;
        }else if($updateDate['typeId'] == 2){
            $data['type_id'] = 2;
            $data['header_id'] = $updateDate['orderId'];
        }else if (6 == $updateDate['typeId']){
            $data['type_id'] = 6;
        }

        if(isset($updateDate['company_account_id']) && $updateDate['company_account_id']){
            $data['company_account_id'] = $updateDate['company_account_id'];
        }

        if(isset($updateDate['platform_date']) && $updateDate['platform_date']){
            $data['platform_date'] = $updateDate['platform_date'];
        }

        if(isset($updateDate['platform_get_type_id']) && $updateDate['platform_get_type_id'] != '-1'){
            $data['platform_get_type_id'] = $updateDate['platform_get_type_id'];
        }

        if(isset($updateDate['platform_pay_type_id']) && $updateDate['platform_pay_type_id'] != '-1'){
            $data['platform_pay_type_id'] = $updateDate['platform_pay_type_id'];
        }

        if(isset($updateDate['platform_second_type_id']) && $updateDate['platform_second_type_id'] != '-1'){
            $data['platform_second_type_id'] = $updateDate['platform_second_type_id'];
        }

        if (isset($updateDate['third_trade_number']) && $updateDate['third_trade_number']) {
            $data['pay_faild_third_trade_number'] = $updateDate['third_trade_number'];
        }

        if (isset($updateDate['fee_number']) && $updateDate['fee_number']) {
            $data['account_transfer_number'] = $updateDate['fee_number'];
        }

        if (isset($updateDate['pay_exchange_rate']) && $updateDate['pay_exchange_rate']) {
            $data['exchange_us_rate'] = $updateDate['pay_exchange_rate'];
        }

        LineOfCreditRecord::insert($data);
    }
}
