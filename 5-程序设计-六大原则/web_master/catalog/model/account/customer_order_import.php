<?php

use App\Components\Storage\StorageCloud;
use App\Enums\Common\YesNoEnum;
use App\Enums\ModifyLog\CommonOrderProcessCode;
use App\Enums\Platform\PlatformMapping;
use App\Enums\Product\ProductTransactionType;
use App\Enums\Safeguard\SafeguardBillStatus;
use App\Enums\Safeguard\SafeguardClaimStatus;
use App\Enums\SalesOrder\CustomerSalesOrderLineItemStatus;
use App\Enums\SalesOrder\CustomerSalesOrderMode;
use App\Enums\SalesOrder\CustomerSalesOrderPickUpStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Enums\SalesOrder\HomePickCarrierType;
use App\Enums\SalesOrder\HomePickImportMode;
use App\Enums\SalesOrder\HomePickLabelReviewStatus;
use App\Enums\SalesOrder\HomePickPlatformType;
use App\Enums\SalesOrder\HomePickUploadType;
use App\Enums\SalesOrder\JapanSalesOrder;
use App\Enums\Track\TrackStatus;
use App\Helper\StringHelper;
use App\Logging\Logger;
use App\Models\Link\OrderAssociated;
use App\Models\Order\OrderCombo;
use App\Models\Product\ProductSetInfo;
use App\Models\Safeguard\SafeguardSalesOrderErrorLog;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use App\Models\SalesOrder\CustomerSalesOrderPickUpLineChange;
use App\Models\SalesOrder\HomePickLabelDetails;
use App\Models\SalesOrder\HomePickUploadFile;
use App\Models\Track\CustomerSalesOrderTracking;
use App\Models\Warehouse\WarehouseInfo;
use App\Repositories\Safeguard\SafeguardBillRepository;
use App\Repositories\Safeguard\SafeguardConfigRepository;
use App\Repositories\SalesOrder\CustomerOrderModifyLogRepository;
use App\Repositories\SalesOrder\CustomerSalesOrderRepository;
use App\Repositories\SalesOrder\Validate\salesOrderSkuValidate;
use App\Repositories\Track\TrackRepository;
use App\Services\Order\OrderService;
use Carbon\Carbon;
use Catalog\model\account\sales_order\SalesOrderManagement as home_pick_model;
use Catalog\model\customerpartner\SalesOrderManagement as sales_model;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class ModelAccountCustomerOrderImport
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelAccountMappingWarehouse $model_account_mapping_warehouse
 * @property ModelToolImage $model_tool_image
 * @property ModelToolPdf $model_tool_pdf
 * @property ModelAccountCustomerOrder $model_account_customer_order
 * @property ModelAccountDeliverySignature $model_account_deliverySignature
 * @property ModelCheckoutOrder $model_checkout_order
 *
 */
class ModelAccountCustomerOrderImport extends Model
{
    const OTHER_SHIP_METHOD = ['LTL', 'SMALL PARCEL'];
    private $sales_model;
    private $home_pick_model;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->sales_model = new sales_model($registry);
        $this->home_pick_model = new home_pick_model($registry);
    }

    public function judgeOrderIdExist($sellerId, $orderId)
    {
        $sql = "SELECT * FROM tb_sys_customer_sales_order WHERE 1=1 AND buyer_id = " . $this->customer->getId();
        if (isset($orderId)) {
            $sql .= " AND order_id = '" . $orderId . "'";
        }

        return $this->db->query($sql)->rows;
    }

    /**
     * [judgeCommonOrderIsExist description]
     * @param int $order_id
     * @param int $buyer_id
     * @return array
     */
    public function judgeCommonOrderIsExist($order_id, $buyer_id)
    {
        $map['order_id'] = $order_id;
        $map['buyer_id'] = $buyer_id;
        return $this->orm->table('tb_sys_customer_sales_order')->where($map)->value('id');
    }

    public function saveOrderFile($data)
    {
        $sql = "INSERT INTO " . DB_PREFIX . "customer_order_file (file_name, size, file_path, customer_id, import_mode, address_id, run_id, memo, create_user_name, create_time, program_code,handle_status) VALUES(";
        if (isset($data['file_name'])) {
            $sql .= "'" . $data['file_name'] . "',";
        } else {
            $sql .= " '',";
        }
        if (isset($data['size'])) {
            $sql .= $data['size'] . ",";
        } else {
            $sql .= 0 . ",";
        }
        if (isset($data['file_path'])) {
            $sql .= "'" . $data['file_path'] . "',";
        } else {
            $sql .= " '', ";
        }
        if (isset($data['customer_id'])) {
            $sql .= $data['customer_id'] . ",";
        } else {
            $sql .= "null,";
        }
        if (isset($data['import_mode'])) {
            $sql .= $data['import_mode'] . ",";
        } else {
            $sql .= "null,";
        }
        if (isset($data['address_id'])) {
            $sql .= $data['address_id'] . ",";
        } else {
            $sql .= "null,";
        }
        if (isset($data['run_id'])) {
            $sql .= $data['run_id'] . ",";
        } else {
            $sql .= "'" . time() . "',";
        }
        if (isset($data['memo'])) {
            $sql .= "'" . $data['memo'] . "',";
        } else {
            $sql .= "null,";
        }
        if (isset($data['create_user_name'])) {
            $sql .= "'" . $data['create_user_name'] . "',";
        } else {
            $sql .= 'null,';
        }
        if (isset($data['create_time'])) {
            $sql .= "'" . $data['create_time'] . "',";
        } else {
            $sql .= "NOW(),";
        }
        $sql .= "'" . PROGRAM_CODE . "',0)";
        $this->db->query($sql);
    }

    public function saveCustomerSalesOrderTemp($datas)
    {
        $sql = "INSERT INTO `tb_sys_customer_sales_order_temp`(shipped_date,ship_to_attachment_url,orders_from,order_id,line_item_number,email,order_date,bill_name,bill_address,bill_city,bill_state,bill_zip_code,bill_country,ship_name,ship_address1,ship_address2,ship_city,ship_state,ship_zip_code,ship_country,ship_phone,item_code,alt_item_id,product_name,qty,item_price,item_unit_discount,item_tax,discount_amount,tax_amount,ship_amount,order_total,payment_method,ship_company,ship_method,ship_service_level,brand_id,customer_comments,seller_id,buyer_id,run_id,memo,create_user_name,create_time,program_code) VALUES ";
        foreach ($datas as $data) {
            $sql .= "(";
            $sql .= (isset($data['shipped_date']) ? "'" . $this->db->escape(trim($data['shipped_date'])) . "'" : "null") . ",";
            $sql .= (isset($data['ship_to_attachment_url']) ? "'" . $this->db->escape(trim($data['ship_to_attachment_url'])) . "'" : "null") . ",";
            $sql .= (isset($data['orders_from']) ? "'" . $this->db->escape(trim($data['orders_from'])) . "'" : "''") . ",";
            $sql .= (isset($data['order_id']) ? "'" . $this->db->escape(trim($data['order_id'])) . "'" : "''") . ",";
            $sql .= (isset($data['line_item_number']) ? "'" . $this->db->escape(trim($data['line_item_number'])) . "'" : "''") . ",";
            $sql .= (isset($data['email']) ? "'" . $this->db->escape(trim($data['email'])) . "'" : "''") . ",";
            $sql .= (isset($data['order_date']) ? "'" . $this->db->escape(trim($data['order_date'])) . "'" : "''") . ",";
            $sql .= (isset($data['bill_name']) ? "'" . $this->db->escape(trim($data['bill_name'])) . "'" : "''") . ",";
            $sql .= (isset($data['bill_address']) ? "'" . $this->db->escape(trim($data['bill_address'])) . "'" : "''") . ",";
            $sql .= (isset($data['bill_city']) ? "'" . $this->db->escape(trim($data['bill_city'])) . "'" : "''") . ",";
            $sql .= (isset($data['bill_state']) ? "'" . $this->db->escape(trim($data['bill_state'])) . "'" : "''") . ",";
            $sql .= (isset($data['bill_zip_code']) ? "'" . $this->db->escape(trim($data['bill_zip_code'])) . "'" : "''") . ",";
            $sql .= (isset($data['bill_country']) ? "'" . $this->db->escape(trim($data['bill_country'])) . "'" : "''") . ",";
            $sql .= (isset($data['ship_name']) ? "'" . $this->db->escape(trim($data['ship_name'])) . "'" : "''") . ",";
            $sql .= (isset($data['ship_address1']) ? "'" . $this->db->escape(trim($data['ship_address1'])) . "'" : "''") . ",";
            $sql .= (isset($data['ship_address2']) ? "'" . $this->db->escape(trim($data['ship_address2'])) . "'" : "null") . ",";
            $sql .= (isset($data['ship_city']) ? "'" . $this->db->escape(trim($data['ship_city'])) . "'" : "''") . ",";
            $sql .= (isset($data['ship_state']) ? "'" . $this->db->escape(trim($data['ship_state'])) . "'" : "''") . ",";
            $sql .= (isset($data['ship_zip_code']) ? "'" . $this->db->escape(trim($data['ship_zip_code'])) . "'" : "''") . ",";
            $sql .= (isset($data['ship_country']) ? "'" . $this->db->escape(trim($data['ship_country'])) . "'" : "''") . ",";
            $sql .= (isset($data['ship_phone']) ? "'" . $this->db->escape(trim($data['ship_phone'])) . "'" : "''") . ",";
            $sql .= (isset($data['item_code']) ? "'" . $this->db->escape(trim($data['item_code'])) . "'" : "''") . ",";
            $sql .= (isset($data['alt_item_id']) ? "'" . $this->db->escape(trim($data['alt_item_id'])) . "'" : "''") . ",";
            $sql .= (isset($data['product_name']) ? "'" . $this->db->escape(trim($data['product_name'])) . "'" : "''") . ",";
            $sql .= (isset($data['qty']) ? $this->db->escape(trim($data['qty'])) : "0") . ",";
            $sql .= (isset($data['item_price']) ? $this->db->escape(trim($data['item_price'])) : "0") . ",";
            $sql .= (isset($data['item_unit_discount']) ? $this->db->escape(trim($data['item_unit_discount'])) : "0") . ",";
            $sql .= (isset($data['item_tax']) ? $this->db->escape(trim($data['item_tax'])) : "0") . ",";
            $sql .= (isset($data['discount_amount']) ? $this->db->escape(trim($data['discount_amount'])) : "0") . ",";
            $sql .= (isset($data['tax_amount']) ? $this->db->escape(trim($data['tax_amount'])) : "0") . ",";
            $sql .= (isset($data['ship_amount']) ? $this->db->escape(trim($data['ship_amount'])) : "0") . ",";
            $sql .= (isset($data['order_total']) ? $this->db->escape(trim($data['order_total'])) : "0") . ",";
            $sql .= (isset($data['payment_method']) ? "'" . $this->db->escape(trim($data['payment_method'])) . "'" : "''") . ",";
            $sql .= (isset($data['ship_company']) ? "'" . $this->db->escape(trim($data['ship_company'])) . "'" : "''") . ",";
            $sql .= (isset($data['ship_method']) ? "'" . $this->db->escape(trim($data['ship_method'])) . "'" : "''") . ",";
            $sql .= (isset($data['ship_service_level']) ? "'" . $this->db->escape(trim($data['ship_service_level'])) . "'" : "null") . ",";
            $sql .= (isset($data['brand_id']) ? "'" . $this->db->escape(trim($data['brand_id'])) . "'" : "null") . ",";
            $sql .= (isset($data['customer_comments']) ? "'" . $this->db->escape(trim($data['customer_comments'])) . "'" : "null") . ",";
            $sql .= (isset($data['seller_id']) ? $this->db->escape(trim($data['seller_id'])) : "null") . ",";
            $sql .= $this->db->escape(trim($data['buyer_id'])) . ",";
            $sql .= (isset($data['run_id']) ? "'" . $this->db->escape(trim($data['run_id'])) . "'" : "''") . ",";
            $sql .= (isset($data['memo']) ? "'" . $this->db->escape(trim($data['memo'])) . "'" : "''") . ",";
            $sql .= (isset($data['create_user_name']) ? "'" . $this->db->escape(trim($data['create_user_name'])) . "'" : "''") . ",";
            $sql .= (isset($data['create_time']) ? "'" . $this->db->escape(trim($data['create_time'])) . "'" : "''") . ",";
            $sql .= (isset($data['program_code']) ? "'" . $this->db->escape(trim($data['program_code'])) . "'" : "''");
            $sql .= "),";
        }
        $sql = substr($sql, 0, -1);
        $this->db->query($sql);
    }

    public function findCustomerSalesOrderTemp($run_id)
    {
        if (isset($run_id)) {
            $sql = "SELECT * FROM `tb_sys_customer_sales_order_temp` WHERE run_id = '" . $run_id . "'";
            $result = $this->db->query($sql);
            return $result->rows;
        }
    }

    /**
     * 批量更新订单状态
     * @param $salesOrderIds
     * @param $new_status
     */
    public function batchUpdateCustomerSalesOrderStatus($salesOrderIds, $new_status)
    {
        if (isset($salesOrderIds) && !empty($salesOrderIds)) {
            $sql = "UPDATE `tb_sys_customer_sales_order` cso SET cso.`order_status` = " . (int)$new_status . " , cso.ltl_process_status = null WHERE cso.`id` IN (" . implode(',', $salesOrderIds) . ")";
            $this->db->query($sql);
        }
    }

    public function updateCustomerSalesOrder($customerSalesOrderId)
    {
        if (isset($customerSalesOrderId)) {
            $check_status_sql = "SELECT cso.order_status,cso.ship_method FROM tb_sys_customer_sales_order cso WHERE cso.id = " . (int)$customerSalesOrderId;
            $check_query = $this->db->query($check_status_sql);
            if (isset($check_query->row['order_status']) && $check_query->row['order_status'] != CustomerSalesOrderStatus::ASR_TO_BE_PAID && $check_query->row['ship_method'] == 'ASR') {
                $sql = "UPDATE `tb_sys_customer_sales_order` cso SET cso.`order_status` = " . CustomerSalesOrderStatus::TO_BE_PAID . " WHERE cso.`id` = " . $customerSalesOrderId;
                $this->db->query($sql);
                $new_status = CustomerSalesOrderStatus::ASR_TO_BE_PAID;
            } else if (isset($check_query->row['order_status']) && $check_query->row['order_status'] != CustomerSalesOrderStatus::CANCELED) {
                $sql = "UPDATE `tb_sys_customer_sales_order` cso SET cso.`order_status` = " . CustomerSalesOrderStatus::BEING_PROCESSED . " WHERE cso.`id` = " . $customerSalesOrderId;
                $new_status = CustomerSalesOrderStatus::BEING_PROCESSED;
                $this->db->query($sql);
            } else {
                $new_status = CustomerSalesOrderStatus::CANCELED;
            }
            return $new_status;
        }
    }

    public function saveCustomerSalesOrders(array $datas)
    {
        $sql = "INSERT INTO `tb_sys_customer_sales_order`(customer_comments, yzc_order_id, order_id, order_date, email,shipped_date, ship_name, ship_address1, ship_address2, ship_city, ship_state, ship_zip_code, ship_country, ship_phone, ship_method, ship_service_level, ship_company, bill_name, bill_address, bill_city, bill_state, bill_zip_code, bill_country, orders_from, discount_amount, tax_amount, order_total, payment_method, store_name, store_id, buyer_id, line_count, run_id, order_status, order_mode, create_user_name, create_time, program_code, to_be_paid_time) VALUES ";
        $keys = array_keys($datas);
        foreach ($keys as $key) {
            $customerSalesOrder = $datas[$key];
            $sql .= "(";
            $sql .= ($customerSalesOrder->customer_comments != null ? "'" . $this->db->escape($customerSalesOrder->customer_comments) . "'" : "''") . ",";
            $sql .= ($customerSalesOrder->yzc_order_id != null ? "'" . $this->db->escape($customerSalesOrder->yzc_order_id) . "'" : "''") . ",";
            $sql .= ($customerSalesOrder->order_id != null ? "'" . $this->db->escape($customerSalesOrder->order_id) . "'" : "''") . ",";
            $sql .= ($customerSalesOrder->order_date != null ? "'" . $this->db->escape($customerSalesOrder->order_date) . "'" : "''") . ",";
            $sql .= ($customerSalesOrder->email != null ? "'" . $this->db->escape($customerSalesOrder->email) . "'" : "null") . ",";
            $sql .= ($customerSalesOrder->shipped_date != null ? "'" . $this->db->escape($customerSalesOrder->shipped_date) . "'" : "null") . ",";
            $sql .= ($customerSalesOrder->ship_name != null ? "'" . $this->db->escape($customerSalesOrder->ship_name) . "'" : "''") . ",";
            $sql .= ($customerSalesOrder->ship_address1 != null ? "'" . $this->db->escape($customerSalesOrder->ship_address1) . "'" : "''") . ",";
            $sql .= ($customerSalesOrder->ship_address2 != null ? "'" . $this->db->escape($customerSalesOrder->ship_address2) . "'" : "null") . ",";
            $sql .= ($customerSalesOrder->ship_city != null ? "'" . $this->db->escape($customerSalesOrder->ship_city) . "'" : "''") . ",";
            $sql .= ($customerSalesOrder->ship_state != null ? "'" . $this->db->escape($customerSalesOrder->ship_state) . "'" : "''") . ",";
            $sql .= ($customerSalesOrder->ship_zip_code != null ? "'" . $this->db->escape($customerSalesOrder->ship_zip_code) . "'" : "''") . ",";
            $sql .= ($customerSalesOrder->ship_country != null ? "'" . $this->db->escape($customerSalesOrder->ship_country) . "'" : "''") . ",";
            $sql .= ($customerSalesOrder->ship_phone != null ? "'" . $this->db->escape($customerSalesOrder->ship_phone) . "'" : "null") . ",";
            $sql .= ($customerSalesOrder->ship_method != null ? "'" . $this->db->escape($customerSalesOrder->ship_method) . "'" : "null") . ",";
            $sql .= ($customerSalesOrder->ship_service_level != null ? "'" . $this->db->escape($customerSalesOrder->ship_service_level) . "'" : "null") . ",";
            $sql .= ($customerSalesOrder->ship_company != null ? "'" . $this->db->escape($customerSalesOrder->ship_company) . "'" : "null") . ",";
            $sql .= ($customerSalesOrder->bill_name != null ? "'" . $this->db->escape($customerSalesOrder->bill_name) . "'" : "null") . ",";
            $sql .= ($customerSalesOrder->bill_address != null ? "'" . $this->db->escape($customerSalesOrder->bill_address) . "'" : "null") . ",";
            $sql .= ($customerSalesOrder->bill_city != null ? "'" . $this->db->escape($customerSalesOrder->bill_city) . "'" : "null") . ",";
            $sql .= ($customerSalesOrder->bill_state != null ? "'" . $this->db->escape($customerSalesOrder->bill_state) . "'" : "null") . ",";
            $sql .= ($customerSalesOrder->bill_zip_code != null ? "'" . $this->db->escape($customerSalesOrder->bill_zip_code) . "'" : "null") . ",";
            $sql .= ($customerSalesOrder->bill_country != null ? "'" . $this->db->escape($customerSalesOrder->bill_country) . "'" : "null") . ",";
            $sql .= ($customerSalesOrder->orders_from != null ? "'" . $this->db->escape($customerSalesOrder->orders_from) . "'" : "null") . ",";
            $sql .= ($customerSalesOrder->discount_amount != null ? $this->db->escape($customerSalesOrder->discount_amount) : "null") . ",";
            $sql .= ($customerSalesOrder->tax_amount != null ? $this->db->escape($customerSalesOrder->tax_amount) : "null") . ",";
            $sql .= ($customerSalesOrder->order_total != null ? $this->db->escape($customerSalesOrder->order_total) : "null") . ",";
            $sql .= ($customerSalesOrder->payment_method != null ? "'" . $this->db->escape($customerSalesOrder->payment_method) . "'" : "''") . ",";
            $sql .= ($customerSalesOrder->store_name != null ? "'" . $this->db->escape($customerSalesOrder->store_name) . "'" : "''") . ",";
            $sql .= $customerSalesOrder->store_id . ",";
            $sql .= $customerSalesOrder->buyer_id . ",";
            $sql .= $customerSalesOrder->line_count . ",";
            $sql .= $customerSalesOrder->run_id . ",";
            $sql .= ($customerSalesOrder->order_status != null ? "'" . $this->db->escape($customerSalesOrder->order_status) . "'" : "''") . ",";
            $sql .= $customerSalesOrder->order_mode . ",";
            $sql .= ($customerSalesOrder->create_user_name != null ? "'" . $this->db->escape($customerSalesOrder->create_user_name) . "'" : "null") . ",";
            $sql .= ($customerSalesOrder->create_time != null ? "'" . $this->db->escape($customerSalesOrder->create_time) . "'" : "null") . ",";
            $sql .= ($customerSalesOrder->program_code != null ? "'" . $this->db->escape($customerSalesOrder->program_code) . "'" : "null") . ",";
            $toBePaidTime = "null";
            if ($customerSalesOrder->order_status != null && $customerSalesOrder->order_status == CustomerSalesOrderStatus::TO_BE_PAID) {
                $toBePaidTime = Carbon::now()->toDateTimeString();
                $toBePaidTime = "'{$toBePaidTime}'";
            }
            $sql .= $toBePaidTime;
            $sql .= "),";
        }
        $sql = substr($sql, 0, -1);
        $this->db->query($sql);
        return $this->db->getLastId();
    }

    public function findCustomerSalesOrders($run_id)
    {
        if (isset($run_id)) {
            $sql = "SELECT * FROM `tb_sys_customer_sales_order` WHERE run_id = '" . $run_id . "'";
            $result = $this->db->query($sql);
            return $result->rows;
        }
    }

    public function saveCustomerSalesOrderLine(array $datas)
    {
        $sql = "INSERT INTO `tb_sys_customer_sales_order_line` (line_comments, temp_id, header_id, line_item_number, product_name, qty, item_price, item_unit_discount, item_tax, item_code, product_id, alt_item_id, ship_amount, image_id, seller_id, run_id, item_status, create_user_name, create_time) VALUES ";
        $keys = array_keys($datas);
        foreach ($keys as $key) {
            $customerSalesOrderLine = $datas[$key];
            $sql .= "(";
            $sql .= ($customerSalesOrderLine->line_comments != null ? "'" . $this->db->escape($customerSalesOrderLine->line_comments) . "'" : "''") . ",";
            $sql .= $customerSalesOrderLine->temp_id . ",";
            $sql .= $customerSalesOrderLine->header_id . ",";
            $sql .= $customerSalesOrderLine->line_item_number . ",";
            $sql .= ($customerSalesOrderLine->product_name != null ? "'" . $this->db->escape($customerSalesOrderLine->product_name) . "'" : "''") . ",";
            $sql .= ($customerSalesOrderLine->qty == null ? 'null' : $this->db->escape($customerSalesOrderLine->qty)) . ",";
            $sql .= ($customerSalesOrderLine->item_price == null ? 'null' : $this->db->escape($customerSalesOrderLine->item_price)) . ",";
            $sql .= ($customerSalesOrderLine->item_unit_discount == null ? 'null' : $this->db->escape($customerSalesOrderLine->item_unit_discount)) . ",";
            $sql .= ($customerSalesOrderLine->item_tax == null ? 'null' : $this->db->escape($customerSalesOrderLine->item_tax)) . ",";
            $sql .= ($customerSalesOrderLine->item_code != null ? "'" . $this->db->escape($customerSalesOrderLine->item_code) . "'" : "null") . ",";
            $sql .= ($customerSalesOrderLine->product_id != null ? $this->db->escape($customerSalesOrderLine->product_id) : "null") . ",";
            $sql .= ($customerSalesOrderLine->alt_item_id != null ? "'" . $this->db->escape($customerSalesOrderLine->alt_item_id) . "'" : "null") . ",";
            $sql .= ($customerSalesOrderLine->ship_amount != null ? $this->db->escape($customerSalesOrderLine->ship_amount) : "null") . ",";
            $sql .= (!empty($customerSalesOrderLine->image_id) ? $this->db->escape($customerSalesOrderLine->image_id) : "null") . ",";
            //            $sql .= $customerSalesOrderLine->seller_id . ",";
            $sql .= ($customerSalesOrderLine->seller_id != null ? "'" . $this->db->escape($customerSalesOrderLine->seller_id) . "'" : "null") . ",";
            $sql .= ($customerSalesOrderLine->run_id != null ? "'" . $this->db->escape($customerSalesOrderLine->run_id) . "'" : "null") . ",";
            $sql .= ($customerSalesOrderLine->item_status != null ? "'" . $this->db->escape($customerSalesOrderLine->item_status) . "'" : "null") . ",";
            $sql .= ($customerSalesOrderLine->create_user_name != null ? "'" . $this->db->escape($customerSalesOrderLine->create_user_name) . "'" : "null") . ",";
            $sql .= ($customerSalesOrderLine->create_time != null ? "'" . $this->db->escape($customerSalesOrderLine->create_time) . "'" : "null");
            $sql .= "),";
        }
        $sql = substr($sql, 0, -1);
        $this->db->query($sql);
    }

    public function getCustomerSalesOrderLineByRunId($run_id, $customer_flag = false)
    {
        if ($run_id) {
            $sql = "SELECT cso.*,csol.* FROM `tb_sys_customer_sales_order_line` csol LEFT JOIN tb_sys_customer_sales_order cso ON csol.header_id=cso.id WHERE csol.`run_id` = '" . $run_id . "'";
            if ($customer_flag) {
                $sql .= ' and cso.buyer_id=' . $this->customer->getId();
                $sql .= ' and csol.item_status = ' . CustomerSalesOrderLineItemStatus::PENDING;
                $sql .= ' and cso.order_status = ' . CustomerSalesOrderStatus::TO_BE_PAID;
                $sql .= ' and csol.header_id in (' . rtrim($this->request->get['order_id'], ',') . ') ';
            }
            return $this->db->query($sql)->rows;
        }
    }

    /**
     * [getCustomerSalesOrderLineByRunIdAndCustomerId description] 通过 run_id 和 customer_id
     * @param $run_id
     * @param int $customer_id
     * @param $manifest_flag
     * @return array
     */
    public function getCustomerSalesOrderLineByRunIdAndCustomerId($run_id, $customer_id, $manifest_flag = 0)
    {
        $map = [
            ['l.run_id', '=', $run_id],
            ['o.buyer_id', '=', $customer_id],
            ['o.order_status', '=', CustomerSalesOrderStatus::TO_BE_PAID],
        ];

        $order_id = $this->request->query->get('order_id', '');
        if ($manifest_flag) {
            $res = $this->orm->table('tb_sys_customer_sales_order_line as l')
                ->crossJoin('tb_sys_customer_sales_order_file as f', 'f.order_id', '=', 'l.header_id')
                ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
                ->where($map)
                ->when($order_id, function (Builder $q) use ($order_id) {
                    return $q->whereIn('l.header_id', explode(',', rtrim($order_id, ',')));
                })
                ->selectRaw('l.*')
                ->orderBy('l.id', 'DESC')
                ->get();
            return obj2array($res);
        } else {
            $res = $this->orm->table('tb_sys_customer_sales_order_line as l')
                ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
                ->where($map)
                ->when($order_id, function (Builder $q) use ($order_id) {
                    return $q->whereIn('l.header_id', explode(',', rtrim($order_id, ',')));
                })
                ->selectRaw('l.*')
                ->orderBy('l.id', 'DESC')
                ->get();
            return obj2array($res);
        }


    }

    public function getCustomerSalesOrderLineByHeaderId($headerId)
    {
        if ($headerId) {
            $sql = "SELECT * FROM `tb_sys_customer_sales_order_line` csol WHERE csol.`header_id` = '" . $headerId . "'";
            return $this->db->query($sql)->rows;
        }
    }

    public function getCustomerSalesOrderByRunId($run_id)
    {
        if ($run_id) {
            $sql = "SELECT * FROM `tb_sys_customer_sales_order` cso WHERE cso.`run_id` = '" . $run_id . "'";
            return $this->db->query($sql)->rows;
        }
    }

    public function getCustomerSalesOrderById($id)
    {
        if ($id) {
            $sql = "SELECT * FROM `tb_sys_customer_sales_order` cso WHERE cso.`id` = '" . $id . "'";
            return $this->db->query($sql)->row;
        }
    }

    public function getCustomerSalesOrderLineByRunIdAndHeaderId($run_id, $header_id)
    {
        if ($run_id) {
            $sql = "SELECT * FROM `tb_sys_customer_sales_order_line` csol WHERE csol.`run_id` = '" . $run_id . "' AND csol.`header_id` = " . $header_id;
            return $this->db->query($sql)->rows;
        }
    }

    public function getAllSellerName()
    {
        $sql = "SELECT CONCAT(c.`firstname`,' ',c.`lastname`) AS seller_name , c.`customer_id` FROM `oc_customer` c WHERE c.`customer_id` IN (SELECT ctc.`customer_id` FROM `oc_customerpartner_to_customer` ctc WHERE ctc.`is_partner` = 1)";
        return $this->db->query($sql)->rows;
    }

    public function saveNewAddress($newAddress)
    {
        $sql = "INSERT INTO `oc_address` (customer_id, firstname, lastname, company, address_1, address_2, city, postcode, country_id, zone_id, custom_field) VALUES (";
        $sql .= "'" . $newAddress['customer_id'] . "',";
        $sql .= "'" . $newAddress['contact_name'] . "',";
        $sql .= "'',";
        $sql .= "'',";
        $sql .= "'" . $newAddress['street_address'] . "',";
        $sql .= "'',";
        $sql .= "'" . $newAddress['city_town'] . "',";
        $sql .= "'" . $newAddress['zip_code'] . "',";
        $sql .= $newAddress['country_id'] . ",";
        $sql .= $newAddress['zone_id'] . ",";
        $sql .= "'')";
        $this->db->query($sql);
        return $this->db->query("SELECT LAST_INSERT_ID() AS address_id")->row['address_id'];
    }

    public function saveUploadVoucherFile($upload_voucher_file)
    {
        $sql = "INSERT INTO `oc_upload_voucher_file` (file_name, file_size, file_path, seller_id, customer_id, seller_order_number, run_id, create_user_name, create_time, program_code) VALUES (";
        $sql .= "'" . $upload_voucher_file['file_name'] . "',";
        $sql .= $upload_voucher_file['file_size'] . ",";
        $sql .= "'" . $upload_voucher_file['file_path'] . "',";
        $sql .= $upload_voucher_file['seller_id'] . ",";
        $sql .= $upload_voucher_file['customer_id'] . ",";
        $sql .= "'" . $upload_voucher_file['seller_order_number'] . "',";
        $sql .= $upload_voucher_file['run_id'] . ",";
        $sql .= "'" . $upload_voucher_file['create_user_name'] . "',";
        $sql .= "'" . date("Y-m-d H:i:s", time()) . "',";
        $sql .= "'" . $upload_voucher_file['program_code'] . "')";
        $this->db->query($sql);
    }

    public function getCustomerOrderFileByRunId($run_id, $customer_id = null)
    {
        if ($run_id) {
            $sql = "SELECT * FROM `oc_customer_order_file` cof WHERE  cof.`run_id` = " . $run_id;
            if ($customer_id)
                $sql .= ' and `customer_id`=' . $customer_id;
            return $this->db->query($sql)->row;
        }
    }

    public function findProductByItemCode($item_code)
    {
        $sql = "SELECT * FROM oc_product p WHERE p.`mpn` = '" . $item_code . "' OR p.`sku` = '" . $item_code . "'";
        return $this->db->query($sql);
    }

    //customerId必填参数
    public function findProductByItemCodeAndSellerId($item_code, $customId, $seller_id = null)
    {
        if (isset($seller_id) && $seller_id != null) {
            $sql = "SELECT 	ctp.customer_id,c2c.screenname,p.* FROM `oc_customerpartner_to_product` ctp LEFT JOIN `oc_product` p ON ctp.`product_id` = p.`product_id` LEFT JOIN oc_buyer_to_seller b2s on b2s.seller_id = ctp.customer_id LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id = ctp.customer_id  LEFT JOIN oc_customer cus ON cus.customer_id = ctp.customer_id  WHERE  cus.status=1 and b2s.buyer_id = " . $customId . " AND ctp.`customer_id` = " . $seller_id . " AND (p.`sku` = '" . $item_code . "')  AND  b2s.buy_status = 1 and b2s.buyer_control_status = 1 and b2s.seller_control_status = 1";
        } else {
            $sql = "SELECT 	ctp.customer_id,c2c.screenname,p.* FROM `oc_customerpartner_to_product` ctp LEFT JOIN `oc_product` p ON ctp.`product_id` = p.`product_id`  LEFT JOIN oc_buyer_to_seller b2s on b2s.seller_id = ctp.customer_id LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id = ctp.customer_id LEFT JOIN oc_customer cus ON cus.customer_id = ctp.customer_id  WHERE  cus.status=1 and b2s.buyer_id = " . $customId . " AND (p.`sku` = '" . $item_code . "')  AND  b2s.buy_status = 1 and b2s.buyer_control_status = 1 and b2s.seller_control_status = 1";
        }
        return $this->db->query($sql);
    }

    public function findProductByItemCodeAndSellerIdAndBuyerId($item_code, $seller_id, $buyer_id)
    {
        if (isset($seller_id) && $seller_id != null) {
            $sql = "SELECT 	ctp.customer_id,c2c.screenname,p.* FROM `oc_customerpartner_to_product` ctp LEFT JOIN `oc_product` p ON ctp.`product_id` = p.`product_id` LEFT JOIN oc_buyer_to_seller b2s on b2s.seller_id = ctp.customer_id LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id = ctp.customer_id WHERE b2s.buyer_id = " . $buyer_id . " AND ctp.`customer_id` = " . $seller_id . " AND ((p.`sku` = '" . $item_code . "' OR p.`mpn` = '" . $item_code . "') AND  b2s.buy_status = 1 and b2s.buyer_control_status = 1 and b2s.seller_control_status = 1 )";
        } else {
            $sql = "SELECT 	ctp.customer_id,c2c.screenname,p.* FROM `oc_customerpartner_to_product` ctp LEFT JOIN `oc_product` p ON ctp.`product_id` = p.`product_id`  LEFT JOIN oc_buyer_to_seller b2s on b2s.seller_id = ctp.customer_id LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id = ctp.customer_id WHERE b2s.buyer_id = " . $buyer_id . " AND ((p.`sku` = '" . $item_code . "' OR p.`mpn` = '" . $item_code . "') AND  b2s.buy_status = 1 and b2s.buyer_control_status = 1 and b2s.seller_control_status = 1 )";
        }
        return $this->db->query($sql);
    }

    public function findManufactureInfoByManufactureId($id)
    {
        $sql = "SELECT man.customer_id,man.image_id,man.can_brand FROM `oc_manufacturer` man WHERE man.manufacturer_id = " . $id;
        return $this->db->query($sql)->row;
    }

    public function findManufactureInfoByProductId($productId)
    {
        $sql = "SELECT cp.customer_id,man.image_id,man.can_brand FROM `oc_manufacturer` man LEFT JOIN oc_product pro ON man.manufacturer_id = pro.manufacturer_id LEFT JOIN `oc_customerpartner_to_product` cp ON cp.product_id = pro.product_id WHERE pro.product_id =" . $productId;
        return $this->db->query($sql)->row;
    }

    public function updateCustomerSalesOrderLineImageId($imageId, $id)
    {
        if ($imageId) {
            $sql = "UPDATE tb_sys_customer_sales_order_line csol SET csol.`image_id` = '" . $imageId . "'  WHERE csol.`id` = " . $id;
        } else {
            $sql = "UPDATE tb_sys_customer_sales_order_line csol SET csol.`image_id` = NULL WHERE csol.`id` = " . $id;
        }
        return $this->db->query($sql);
    }

    public function updateCustomerSalesOrderLineProductId($product_id, $id)
    {
        $sql = "UPDATE tb_sys_customer_sales_order_line csol SET csol.`product_id` = " . $product_id . "  WHERE csol.`id` = " . $id;
        return $this->db->query($sql);
    }

    public function findCostDetailByBuyerIdAndSkuId($buyer_id, $sku_id)
    {
        $sql = "SELECT * FROM `tb_sys_cost_detail` scd WHERE scd.`sku_id` = " . $sku_id . " AND scd.`buyer_id` = " . $buyer_id . " AND scd.`onhand_qty` > 0";
        return $this->db->query($sql);
    }

    public function saveSupplierOrder($supplierOrder)
    {
        $sql = "INSERT INTO `tb_sys_supplier_order`(order_mode, buyer_id, seller_id, supplier_order_id, purchase_date, purchase_total, purchase_invoice_total, purchase_status, address_id, create_user_name, create_time, program_code) VALUES (";
        $sql .= ($supplierOrder->order_mode != null ? $supplierOrder->order_mode : "null") . ",";
        $sql .= ($supplierOrder->buyer_id != null ? $supplierOrder->buyer_id : "null") . ",";
        $sql .= ($supplierOrder->seller_id != null ? $supplierOrder->seller_id : "null") . ",";
        $sql .= ($supplierOrder->supplier_order_id != null ? "'" . $supplierOrder->supplier_order_id . "'" : "null") . ",";
        $sql .= ($supplierOrder->purchase_date != null ? "'" . $supplierOrder->purchase_date . "'" : "null") . ",";
        $sql .= ($supplierOrder->purchase_total != null ? $supplierOrder->purchase_total : "null") . ",";
        $sql .= ($supplierOrder->purchase_invoice_total != null ? $supplierOrder->purchase_invoice_total : "null") . ",";
        $sql .= ($supplierOrder->purchase_status != null ? "'" . $supplierOrder->purchase_status . "'" : "null") . ",";
        $sql .= ($supplierOrder->address_id != null ? $supplierOrder->address_id : "null") . ",";
        $sql .= ($supplierOrder->create_user_name != null ? "'" . $supplierOrder->create_user_name . "'" : "null") . ",";
        $sql .= ($supplierOrder->create_time != null ? "'" . $supplierOrder->create_time . "'" : "null") . ",";
        $sql .= ($supplierOrder->program_code != null ? "'" . $supplierOrder->program_code . "'" : "null") . ")";
        $this->db->query($sql);
        return $this->db->getLastId();
    }

    public function savePurchaseOrder($purchaseOrder)
    {
        $sql = "INSERT INTO `tb_sys_purchase_order`(source_header_id, order_mode, seller_id, list_is_printed, voucher_is_printed, address_id, create_user_name, create_time, program_code) VALUES (";
        $sql .= ($purchaseOrder->source_header_id != null ? $purchaseOrder->source_header_id : "null") . ",";
        $sql .= ($purchaseOrder->order_mode != null ? $purchaseOrder->order_mode : "null") . ",";
        $sql .= ($purchaseOrder->seller_id != null ? $purchaseOrder->seller_id : "null") . ",";
        $sql .= ($purchaseOrder->list_is_printed != null ? $purchaseOrder->list_is_printed : "0") . ",";
        $sql .= ($purchaseOrder->voucher_is_printed != null ? $purchaseOrder->voucher_is_printed : "0") . ",";
        $sql .= ($purchaseOrder->address_id != null ? $purchaseOrder->address_id : "null") . ",";
        $sql .= ($purchaseOrder->create_user_name != null ? "'" . $purchaseOrder->create_user_name . "'" : "null") . ",";
        $sql .= ($purchaseOrder->create_time != null ? "'" . $purchaseOrder->create_time . "'" : "null") . ",";
        $sql .= ($purchaseOrder->program_code != null ? "'" . $purchaseOrder->program_code . "'" : "null") . ")";
        $this->db->query($sql);
        return $this->db->getLastId();
    }

    public function saveSupplierOrderLine($supplierOrderLine)
    {
        $sql = "INSERT INTO `tb_sys_supplier_order_line` (order_header_id, mpn, mpn_id, item_price, item_description, item_qty, item_status, create_user_name, create_time, program_code) VALUES (";
        $sql .= ($supplierOrderLine->order_header_id != null ? $supplierOrderLine->order_header_id : "null") . ",";
        $sql .= ($supplierOrderLine->mpn != null ? "'" . $supplierOrderLine->mpn . "'" : "null") . ",";
        $sql .= ($supplierOrderLine->mpn_id != null ? $supplierOrderLine->mpn_id : "null") . ",";
        $sql .= ($supplierOrderLine->item_price != null ? $supplierOrderLine->item_price : "null") . ",";
        $sql .= ($supplierOrderLine->item_description != null ? "'" . $supplierOrderLine->item_description . "'" : "null") . ",";
        $sql .= ($supplierOrderLine->item_qty != null ? $supplierOrderLine->item_qty : "null") . ",";
        $sql .= ($supplierOrderLine->item_status != null ? $supplierOrderLine->item_status : "null") . ",";
        $sql .= ($supplierOrderLine->create_user_name != null ? "'" . $supplierOrderLine->create_user_name . "'" : "null") . ",";
        $sql .= ($supplierOrderLine->create_time != null ? "'" . $supplierOrderLine->create_time . "'" : "null") . ",";
        $sql .= ($supplierOrderLine->program_code != null ? "'" . $supplierOrderLine->program_code . "'" : "null") . ")";
        $this->db->query($sql);
        return $this->db->getLastId();
    }

    public function savePurchaseOrderLine($purchaseOrderLine)
    {
        $sql = "INSERT INTO `tb_sys_purchase_order_line`(header_id, supplier_order_header_id, supplier_order_line_id, sku_id, qty, purchase_line_status, create_user_name, create_time, program_code) VALUES (";
        $sql .= ($purchaseOrderLine->header_id != null ? $purchaseOrderLine->header_id : "null") . ",";
        $sql .= ($purchaseOrderLine->supplier_order_header_id != null ? $purchaseOrderLine->supplier_order_header_id : "null") . ",";
        $sql .= ($purchaseOrderLine->supplier_order_line_id != null ? $purchaseOrderLine->supplier_order_line_id : "null") . ",";
        $sql .= ($purchaseOrderLine->sku_id != null ? $purchaseOrderLine->sku_id : "null") . ",";
        $sql .= ($purchaseOrderLine->qty != null ? $purchaseOrderLine->qty : "null") . ",";
        $sql .= ($purchaseOrderLine->purchase_line_status != null ? $purchaseOrderLine->purchase_line_status : "null") . ",";
        $sql .= ($purchaseOrderLine->create_user_name != null ? "'" . $purchaseOrderLine->create_user_name . "'" : "null") . ",";
        $sql .= ($purchaseOrderLine->create_time != null ? "'" . $purchaseOrderLine->create_time . "'" : "null") . ",";
        $sql .= ($purchaseOrderLine->program_code != null ? "'" . $purchaseOrderLine->program_code . "'" : "null") . ")";
        $this->db->query($sql);
        return $this->db->getLastId();
    }

    public function getPriceAndSellerNameByProductId($product_id)
    {
        $sql = "SELECT
                    p.price,
                    p.freight,
                    p.package_fee,
                    c2c.screenname,
                    p.buyer_flag,
                    p.status as p_status,
                    p.status,
                    p.quantity,
                    p.combo_flag
                FROM
                    oc_customerpartner_to_product c2p
                LEFT JOIN oc_product p ON p.product_id = c2p.product_id
                LEFT JOIN oc_customerpartner_to_customer c2c ON c2p.customer_id = c2c.customer_id
                WHERE
                    p.product_id =" . $product_id;
        //p.buyer_flag = 1  and
        $query = $this->db->query($sql);
        //13827 【B2B】【需求】Reorder页面和购买弹窗中的不存在在平台上的SKU表格拆分成未建立关联的SKU和平台上不存在的SKU两个表格
        //and (dm.product_display = 1 or dm.product_display is null)
        //14039 下架产品在Sales Order Management功能中隐藏价格
        //N126 这里需要重新计算价格
        //需要获取 price dm.product_display, quantity ，status
        $res = $query->row;
        $this->load->model('catalog/product');
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        if ($isCollectionFromDomicile) {
            $freight = 0;
        } else {
            $freight = $res['freight'];
        }
        $dm_info = $this->model_catalog_product->getDelicacyManagementInfoByNoView($product_id, $this->customer->getId());
        if (!$dm_info) {
            $res['product_display'] = null;
            $res['status'] = $res['p_status'];
            $res['freight'] = $freight + $res['package_fee'];
            $res['price'] = round($res['price'], 2) > 0 ? round(($res['price']), 2) : 0;
        } elseif (isset($dm_info) && $dm_info['product_display'] == 0) {
            $res['product_display'] = 0;
            $res['quantity'] = 0;
            $res['status'] = 0;
            $res['p_status'] = 0;
            $res['freight'] = $freight + $res['package_fee'];
            $res['price'] = round($res['price'], 2) > 0 ? round(($res['price']), 2) : 0;
        } elseif (isset($dm_info) && $dm_info['product_display'] == 1) {
            $res['product_display'] = 1;
            $res['status'] = $res['p_status'];
            $res['freight'] = $freight + $res['package_fee'];
            $res['price'] = round($dm_info['current_price'], 2);

        }
        return $res;
    }

    public function getPriceAndSellerNameByProductIdAndNoView($product_id)
    {
        $sql = "select p.price,p.freight,p.package_fee,p.buyer_flag,p.status as p_status,p.quantity,p.combo_flag,c2c.screenname,1 as product_display,p.status
                    from oc_customerpartner_to_product as c2p
                    left join oc_product as p on p.product_id = c2p.product_id
                    left join oc_customerpartner_to_customer as c2c on c2c.customer_id = c2p.customer_id
                    where p.product_id = " . $product_id . ' limit 1';
        $productResult = $this->db->query($sql)->row;
        //N126 上门取货价展示——Buyer PHP端价格展示
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        if ($isCollectionFromDomicile) {
            $freight = 0;
        } else {
            $freight = $productResult['freight'];
        }
        $this->load->model('catalog/product');
        $delicacyManagementInfo = $this->model_catalog_product->getDelicacyManagementInfoByNoView($product_id, $this->customer->getId());
        if (empty($productResult)) {
            // 不做任何更改
            $productResult['price'] = round(($productResult['price']), 2) > 0 ? round(($productResult['price'] + $freight + $productResult['package_fee']), 2) : 0;
        } elseif (isset($delicacyManagementInfo['product_display']) && $delicacyManagementInfo['product_display'] == 0) {
            $productResult['price'] = round(($productResult['price']), 2) > 0 ? round(($productResult['price'] + $freight + $productResult['package_fee']), 2) : 0;
            $productResult['quantity'] = 0;
            $productResult['product_display'] = 0;
            $productResult['status'] = 0;
            $productResult['p_status'] = 0;
        } elseif (isset($delicacyManagementInfo['product_display']) && $delicacyManagementInfo['product_display'] == 1) {
            isset($delicacyManagementInfo['current_price']) && $productResult['price'] = round($delicacyManagementInfo['current_price'], 2);
        } else {
            $productResult['price'] = round(($productResult['price']), 2) > 0 ? round(($productResult['price']), 2) : 0;

        }
        $productResult['freight'] = $freight + $productResult['package_fee'];
        return $productResult;
    }

    public function getUploadHistory()
    {
        //handle_status=1 AND
        $sql = "select * from oc_customer_order_file where  customer_id = " . (int)$this->customer->getId() . " order by create_time desc LIMIT 0,5";
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getSuccessfullyUploadHistoryBuilder($param)
    {
        $map = [
            ['customer_id', '=', $this->customer->getId()],
        ];
        if (isset($param['filter_orderDate_from'])) {
            //默认当天
            $timeList[] = $param['filter_orderDate_from'];
        } else {
            //这里可以写为最初成功时间
            $timeList[] = date('Y-m-d 00:00:00', 0);
        }

        if (isset($param['filter_orderDate_to'])) {
            $timeList[] = $param['filter_orderDate_to'];
        } else {
            $timeList[] = date('Y-m-d 23:59:59', time());
        }

        return db('oc_customer_order_file')
            ->where($map)
            ->whereBetween('create_time', $timeList);
    }

    /**
     * [getSuccessfullyUploadHistory description]
     * @param $param
     * @param $page
     * @param int $perPage
     * @return array
     */
    public function getSuccessfullyUploadHistory($param, $page, $perPage = 2): array
    {
        $builder = $this->getSuccessfullyUploadHistoryBuilder($param);
        return $builder
            ->orderBy('id', 'desc')
            ->forPage($page, $perPage)
            ->get()
            ->map(function ($v) {
                if (StorageCloud::orderCsv()->fileExists($v->file_path)) {
                    // 需要处理数据库里存储的path
                    $v->file_path = StorageCloud::orderCsv()->getUrl($v->file_path);
                }
                return (array)$v;
            })
            ->toArray();

    }

    /**
     * [getSuccessfullyUploadHistoryTotal description]
     * @param $param
     * @return int
     */
    public function getSuccessfullyUploadHistoryTotal($param)
    {
        $builder = $this->getSuccessfullyUploadHistoryBuilder($param);
        return $builder->count();

    }

    public function updateUploadStatus($runId, $customer_id = null)
    {
        $sql = "update oc_customer_order_file set handle_status=0 where run_id='" . $runId . "'";
        if ($customer_id)
            $sql .= ' and customer_id=' . $customer_id;
        $this->db->query("update oc_customer_order_file set handle_status=0 where run_id='" . $runId . "'");
    }

    /**
     * [updateUploadInfoStatus description]
     * @param $run_id
     * @param int $customer_id
     * @param $update_info
     */
    public function updateUploadInfoStatus($run_id, $customer_id, $update_info)
    {
        $map = [
            ['run_id', '=', $run_id],
            ['customer_id', '=', $customer_id],
        ];
        $this->orm->table(DB_PREFIX . 'customer_order_file')
            ->where($map)
            ->update($update_info);

    }

    public function countItemCodeFromOrder($item_code, $run_id)
    {
        $sql = "select  ord.order_id from tb_sys_customer_sales_order ord
                LEFT JOIN tb_sys_customer_sales_order_line line on line.header_id = ord.id
                where ord.order_status = " . CustomerSalesOrderStatus::TO_BE_PAID . " and ord.buyer_id =" . (int)$this->customer->getId() . "
                and line.item_code = '" . $item_code . "' and line.run_id = '" . $run_id . "'";
        $query = $this->db->query($sql);
        return $query->rows;

    }

    public function associateOrder($order_id, $line_id, $item_code)
    {
        Logger::salesOrder("绑定开始" . $order_id . "," . $line_id . "," . $item_code);
        if ($order_id != null) {
            $customer_id = $this->db->query("select customer_id from oc_order where order_id = " . $order_id)->row['customer_id'];
        } else {
            $customer_id = $this->customer->getId();
        }
        $orderAssociatedIds = [];
        $lineQty = $this->db->query("select line.id,line.qty,line.header_id,line.item_code from tb_sys_customer_sales_order so LEFT JOIN tb_sys_customer_sales_order_line line ON so.id = line.header_id where so.buyer_id =" . $customer_id . " AND  line.id = '" . $line_id . "'")->row;
        //未绑定销售订单的库存数量
        $sql = "SELECT
                    cost.seller_id,
                    cost.buyer_id,
                    cost.onhand_qty,
                    cost.id,
                    rline.oc_order_id,
                    cost.sku_id,
                    ocp.order_product_id,
                    cost.original_qty - ifnull(t.associateQty, 0)-ifnull(t2.qty,0) AS leftQty
                FROM
                    tb_sys_cost_detail cost
                LEFT JOIN oc_product p ON cost.sku_id = p.product_id
                LEFT JOIN tb_sys_receive_line rline ON rline.id = cost.source_line_id
                LEFT JOIN oc_order_product ocp ON (
                    ocp.order_id = rline.oc_order_id
                    AND ocp.product_id = cost.sku_id
                )
                LEFT JOIN (
                    SELECT
                        sum(qty) AS associateQty,
                        order_product_id
                    FROM
                        tb_sys_order_associated
					where buyer_id = " . $customer_id . "
                    GROUP BY
                        order_product_id
                ) t ON t.order_product_id = ocp.order_product_id
                LEFT JOIN (
                    SELECT
                        rop.product_id,
                        ro.order_id,
                        sum(rop.quantity) AS qty
                    FROM
                        oc_yzc_rma_order ro
                    LEFT JOIN oc_yzc_rma_order_product rop ON rop.rma_id = ro.id
                    WHERE
                        ro.buyer_id = " . $customer_id . "
                    AND ro.cancel_rma = 0
                    AND status_refund <> 2
                    AND ro.order_type = 2
                    GROUP BY
                        rop.product_id,ro.order_id
                ) t2 on t2.product_id=ocp.product_id and t2.order_id=ocp.order_id";
        $sql .= " WHERE cost.onhand_qty > 0 AND type = 1 AND  p.sku='" . $item_code . "' and cost.buyer_id = " . $customer_id;
        $costArr = $this->db->query($sql)->rows;
        foreach ($costArr as $cost) {
            if ($cost['leftQty'] >= $lineQty['qty']) {
                $discountsAmount = app(OrderService::class)->orderProductWillAssociateDiscountsAmount(intval($cost['order_product_id']), intval($lineQty['qty']), $this->customer->isJapan() ? 0 : 2);
                //剩余数量大于本条明细所需库存数
                $this->db->query("INSERT INTO tb_sys_order_associated  (sales_order_id,sales_order_line_id,order_id,order_product_id,qty,product_id,seller_id,buyer_id,image_id,CreateUserName,CreateTime,UpdateTime,ProgramCode,coupon_amount,campaign_amount) values(" . $lineQty['header_id'] . "," . $lineQty['id'] . "," . $cost['oc_order_id'] . "," . $cost['order_product_id'] . "," . $lineQty['qty'] . "," . $cost['sku_id'] . "," . $cost['seller_id'] . "," . $cost['buyer_id'] . ",NULL" . ",'php_purchase'" . ",NOW(),NOW(),'V1.0',{$discountsAmount['coupon_amount']},{$discountsAmount['campaign_amount']})");
                $orderAssociatedIds[] = $this->db->getLastId();
                break;
            } else {
                if ($cost['leftQty'] > 0) {
                    $discountsAmount = app(OrderService::class)->orderProductWillAssociateDiscountsAmount(intval($cost['order_product_id']), intval($cost['leftQty']), $this->customer->isJapan() ? 0 : 2);
                    $this->db->query("INSERT INTO tb_sys_order_associated  (sales_order_id,sales_order_line_id,order_id,order_product_id,qty,product_id,seller_id,buyer_id,image_id,CreateUserName,CreateTime,UpdateTime,ProgramCode,coupon_amount,campaign_amount) values(" . $lineQty['header_id'] . "," . $lineQty['id'] . "," . $cost['oc_order_id'] . "," . $cost['order_product_id'] . "," . $cost['leftQty'] . "," . $cost['sku_id'] . "," . $cost['seller_id'] . "," . $cost['buyer_id'] . ",NULL" . ",'php_purchase'" . ",NOW(),NOW(),'V1.0',{$discountsAmount['coupon_amount']},{$discountsAmount['campaign_amount']})");
                    $orderAssociatedIds[] = $this->db->getLastId();
                    $lineQty['qty'] = $lineQty['qty'] - $cost['leftQty'];
                }
            }
        }
        Logger::salesOrder("绑定结束" . $order_id . "," . $line_id . "," . $item_code);
        return $orderAssociatedIds;
    }

    public function associateComboProduct($order_id)
    {
        $sql = "select p.product_id,p.sku,c.order_id,ocp.order_product_id,ctp.customer_id from oc_order c left join oc_order_product ocp on ocp.order_id = c.order_id left join oc_product p on p.product_id = ocp.product_id  left join oc_customerpartner_to_product ctp on ctp.product_id = p.product_id where p.combo_flag = 1 and c.order_id =" . $order_id;
        $results = $this->db->query($sql)->rows;
        foreach ($results as $result) {
            $setInfos = $this->db->query("select combo.product_id,combo.mpn,combo.set_mpn,combo.qty,combo.set_product_id from tb_sys_product_set_info combo  where combo.product_id =" . $result['product_id'])->rows;
            foreach ($setInfos as $setInfo) {
                $setProduct = $this->db->query("select p.product_id,p.sku,p.danger_flag from oc_product p where p.product_id =" . $setInfo['set_product_id'])->row;
                if (isset($setProduct['product_id'])) {
                    $this->db->query("INSERT INTO tb_sys_order_combo (product_id,item_code,order_id,order_product_id,set_product_id,set_item_code,qty,danger_flag) values(" . $setInfo['product_id'] . ",'" . $result['sku'] . "'," . $order_id . "," . $result['order_product_id'] . "," . $setProduct['product_id'] . ",'" . $setProduct['sku'] . "'," . $setInfo['qty'] . "," . $setProduct['danger_flag'] . ")");
                } else {
                    $this->db->query("INSERT INTO tb_sys_order_combo (product_id,item_code,order_id,order_product_id,set_item_code,qty) values(" . $setInfo['product_id'] . ",'" . $result['sku'] . "'," . $order_id . "," . $result['order_product_id'] . ",'" . $setInfo['set_mpn'] . "'," . $setInfo['qty'] . ")");
                }
            }
        }
    }

    //更新订单明细表的combo_info
    public function updateCustomerSalesOrderLine($header_id, $line_id)
    {
        $results = OrderAssociated::query()->alias('a')
            ->leftJoinRelations(['product as p'])
            ->join('tb_sys_customer_sales_order_line as csol','csol.id','a.sales_order_line_id')
            ->where([
                'p.combo_flag' => 1,
                'a.sales_order_id' => $header_id,
                'a.sales_order_line_id' => $line_id,
            ])
            ->select([
                'p.combo_flag',
                'a.order_id',
                'a.order_product_id',
                'a.qty',
                'csol.combo_info'
            ])
            ->get();
        $combo_info_before = null;
        $comboJson = [];
        foreach ($results as $key => $result) {
            if ($result->combo_flag) {
                $comboJson[$key] = [];
                $comboInfos = OrderCombo::query()->where([
                    'order_id'=>$result->order_id,
                    'order_product_id'=>$result->order_product_id,
                ])
                ->get();
                $flag = true;
                foreach ($comboInfos as $key1 => $comboInfo) {
                    if ($flag) {
                        $comboJson[$key][$comboInfo->item_code] = $result->qty;
                        $flag = false;
                    }
                    $comboJson[$key][$comboInfo->set_item_code] = $comboInfo->qty;
                }

            }
            $combo_info_before = $result->combo_info;
        }
        $combo_map = json_decode($combo_info_before,true);
        foreach($comboJson as $key => $value){
            $combo_map[] = $value;
        }
        //更新comboInfo
        //array_unique 返回 key不连续
        $comboRet = array_values(array_unique($combo_map,SORT_REGULAR));
        Logger::salesOrder(['更新comboInfo', 'info' => "销售单id：{$header_id}，明细id：{$line_id}"]);
        Logger::salesOrder(['更新comboInfo', 'json' => $comboRet]);
        if($comboRet){
            CustomerSalesOrderLine::query()
                ->where('id', '=', $line_id)
                ->update([
                    'combo_info' => json_encode($comboRet),
                ]);
        }
    }

    /**
     * 查看可以购买的产品 出现多个product问题
     * @param $item_code
     * @param null $seller_id
     * @param null $buyerStore
     * @return mixed
     */
    public function findCanBuyProductByItemCodeAndSellerId($item_code, $seller_id = null, $buyerStore = null)
    {
        $customId = $this->customer->getId();
        if (!isset($customId)) {
            $customId = $this->session->get('customer_id');
        }

        if (isset($seller_id) && $seller_id != null) {
            $sql = "SELECT 	ctp.customer_id,c2c.screenname,p.* FROM `oc_customerpartner_to_product` ctp
                LEFT JOIN `oc_product` p ON ctp.`product_id` = p.`product_id`
                LEFT JOIN oc_buyer_to_seller b2s on b2s.seller_id = ctp.customer_id
                LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id = ctp.customer_id
                LEFT JOIN oc_customer cus ON cus.customer_id = ctp.customer_id
                WHERE  cus.status= 1 and b2s.buyer_id = " . $customId
                . " AND ctp.`customer_id` = " . $seller_id
                . " AND (p.`sku` = '" . $item_code
                . "')  AND  b2s.buy_status = 1 and b2s.buyer_control_status = 1 and b2s.seller_control_status = 1  and p.buyer_flag = 1
                    group by p.product_id ORDER BY p.product_id DESC";
        } else {
            //根据sku 判断是否有库存来获取sku
            $allProduct = $this->orm->table('oc_product')->where('sku', $item_code)->pluck('product_id')->toArray();
            $productList = [];
            foreach ($allProduct as $item) {
                if (isset($buyerStore[$item]) && $buyerStore[$item] > 0) {
                    array_push($productList, $item);
                }
            }

            if ($productList) {
                $productString = implode(',', $productList);
                $sql = "SELECT 	ctp.customer_id,c2c.screenname,p.*,case when dmg.id is not null then 0 when dm.product_display = 0 then 0 else 1 end as dm_product_display FROM `oc_customerpartner_to_product` ctp
                    LEFT JOIN `oc_product` p ON ctp.`product_id` = p.`product_id`
                    LEFT JOIN oc_buyer_to_seller b2s on b2s.seller_id = ctp.customer_id
                    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id = ctp.customer_id
                    LEFT JOIN oc_customer cus ON cus.customer_id = ctp.customer_id
                    LEFT JOIN oc_delicacy_management dm on p.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id=" . $customId . "
                    LEFT JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.status = 1 AND bgl.buyer_id = " . $customId . " AND bgl.seller_id = ctp.customer_id )
                    LEFT JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.status = 1 AND pgl.product_id = ctp.product_id AND pgl.seller_id = ctp.customer_id )
                    LEFT JOIN oc_delicacy_management_group AS dmg ON ( dmg.status = 1 AND dmg.buyer_group_id = bgl.buyer_group_id AND dmg.product_group_id = pgl.product_group_id  AND dmg.seller_id = ctp.customer_id)
                    WHERE  ((cus.status=1 and b2s.buyer_id = " . $customId . " AND (p.`sku` = '" . $item_code . "')
                    AND  b2s.buy_status = 1 and b2s.buyer_control_status = 1 and b2s.seller_control_status = 1  )
                    or (p.`product_id` in (" . $productString . ")))
                    group by p.product_id ORDER BY p.product_id DESC";
            } else {
                $sql = "SELECT 	ctp.customer_id,c2c.screenname,p.*,case when dmg.id is not null then 0 when dm.product_display = 0 then 0 else 1 end as dm_product_display FROM `oc_customerpartner_to_product` ctp
                    LEFT JOIN `oc_product` p ON ctp.`product_id` = p.`product_id`
                    LEFT JOIN oc_buyer_to_seller b2s on b2s.seller_id = ctp.customer_id
                    LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id = ctp.customer_id
                    LEFT JOIN oc_customer cus ON cus.customer_id = ctp.customer_id
                    LEFT JOIN oc_delicacy_management dm on p.product_id=dm.product_id and now()<dm.expiration_time and dm.buyer_id=" . $customId . "
                    LEFT JOIN oc_customerpartner_buyer_group_link AS bgl ON ( bgl.status = 1 AND bgl.buyer_id = " . $customId . " AND bgl.seller_id = ctp.customer_id )
                    LEFT JOIN oc_customerpartner_product_group_link AS pgl ON ( pgl.status = 1 AND pgl.product_id = ctp.product_id AND pgl.seller_id = ctp.customer_id )
                    LEFT JOIN oc_delicacy_management_group AS dmg ON ( dmg.status = 1 AND dmg.buyer_group_id = bgl.buyer_group_id AND dmg.product_group_id = pgl.product_group_id  AND dmg.seller_id = ctp.customer_id)
                    WHERE  cus.status=1 and b2s.buyer_id = " . $customId . " AND (p.`sku` = '" . $item_code . "')
                    AND  b2s.buy_status = 1 and b2s.buyer_control_status = 1 and b2s.seller_control_status = 1
                    group by p.product_id ORDER BY p.product_id DESC";
            }

        }
        return $this->db->query($sql);
    }

    /**
     * 根据buyerId和sku查询对于该buyer，此sku是否可以买到超大件的商品
     *
     * @param string $item_code
     * @param int $buyer_id
     * @return bool
     *
     * @author chenyang 2019/09/03
     */
    public function checkIsOversizeItemBySku($item_code, $buyer_id)
    {
        $sql = "SELECT COUNT(*) AS cnt
                FROM `oc_customerpartner_to_product` ctp
                INNER JOIN `oc_product` p ON ctp.`product_id` = p.`product_id`
                INNER JOIN oc_product_to_tag ptt ON ptt.`tag_id` = " . $this->config->get('tag_id_oversize') . " AND ptt.`product_id` = p.`product_id`
                LEFT JOIN oc_buyer_to_seller b2s ON b2s.seller_id = ctp.customer_id
                LEFT JOIN oc_customerpartner_to_customer c2c ON c2c.customer_id = ctp.customer_id
                LEFT JOIN oc_customer cus ON cus.customer_id = ctp.customer_id
                LEFT JOIN vw_delicacy_management dm ON p.product_id=dm.product_id AND NOW()<dm.expiration_time AND dm.buyer_id=" . (int)$buyer_id . "
                WHERE  cus.status=1 AND b2s.buyer_id = " . $buyer_id . " AND (p.`sku` = '" . $item_code . "')
                AND  b2s.buy_status = 1 AND b2s.buyer_control_status = 1 AND b2s.seller_control_status = 1";
        $query = $this->db->query($sql);

        if (isset($query->row)) {
            if ($query->row['cnt'] != 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * [judgeSkuIsExist description] 此方法copy自3194
     * @param $item_code
     * @param null $country_id
     * @return bool
     * date:2020/12/1 9:36
     */
    public function judgeSkuIsExist($item_code, $country_id = null)
    {
        if (null == $item_code)
            return false;
        if ($country_id) {
            $map = [
                ['p.sku', '=', $item_code],
                ['c.country_id', '=', $country_id],
            ];
            $product_id = $this->orm->table(DB_PREFIX . 'product as p')
                ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
                ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'ctp.customer_id')
                ->where($map)
                ->value('p.product_id');
        } else {
            $map = [
                ['p.buyer_flag', '=', 1],
                ['p.is_deleted', '=', 0],
                ['p.sku', '=', $item_code],
            ];
            $product_id = $this->orm->table(DB_PREFIX . 'product as p')->where($map)->value('product_id');
        }
        if ($product_id != null) {
            return $product_id;
        }

        return false;
    }

    /**
     * [getDropshipProductsInfoByCalc description] 获取dropship line数据详情
     * @param $lineList
     * @return array
     * @throws Exception
     */
    public function getDropshipProductsInfoByCalc($lineList)
    {
        $customerId = $this->customer->getId();
        $this->load->model('tool/image');
        $this->load->model('catalog/product');
        $this->load->model('account/product_quotes/margin_contract');
        // 产品未找到的产品数组
        $hasNoProductArr = array();
        //找不到sku 或者被禁用
        $hasNoExistProductArr = [];
        // 没库存需要购买的产品数组
        $hasNoCostArr = array();
        // 需要采购的产品
        $productArr = array();
        // 平台没有的产品
        $noProductArr = array();
        // 获取库存MAP
        $productCostMap = $this->customer->getProductCostMap($customerId);
        // product_id 和  seller_name 的对应关系
        $productSellerMap = $this->customer->getProductSellerMap($customerId);
        // temp 作为一个数据处理中间媒介
        $productCostMapTemp = $productCostMap;
        // 获取根据头表进行分组
        $headerCustomerSalesOrderMap = array();
        $costQtyArr = array();
        if ($lineList) {
            //超大件数组
            $oversize_array = array();
            foreach ($lineList as $key => $value) {
                //设置超大件标志
                $tag_array = $this->model_catalog_product->getProductSpecificTagByOrderLineId($value['id']);
                $tags = array();
                if (isset($tag_array)) {
                    foreach ($tag_array as $tag) {
                        if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                            //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                            $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                            $tags[] = '<img data-toggle="tooltip"   class="' . $tag['class_style'] . '"  title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                        }
                    }
                }
                $lineList[$key]['tag'] = $tags;
                // 查询oc_product 更新product_id;
                $itemCode = $value['item_code'];
                $sellerId = null;
                // 查询产品更新productId
                $oc_products = $this->findCanBuyProductByItemCodeAndSellerId($itemCode, $sellerId, $productCostMap)->rows;
                //$margin_product = $this->model_account_product_quotes_margin_contract->getMarginProductForBuyer($customerId, $itemCode);
                //order 信息
                $orderHeader = $this->getCustomerSalesOrderById($value['header_id']);
                $lineList[$key]['ship_address'] =
                    app('db-aes')->decrypt($orderHeader['ship_address1']) . app('db-aes')->decrypt($orderHeader['ship_address2']) . ',' .
                    app('db-aes')->decrypt($orderHeader['ship_city']) . ',' . $orderHeader['ship_state'] . ',' . $orderHeader['ship_zip_code'] . ',' . $orderHeader['ship_country'];
                $lineList[$key]['ship_name'] = app('db-aes')->decrypt($orderHeader['ship_name']);
                $lineList[$key]['order_id'] = $orderHeader['order_id'];
                if ($oc_products) {
                    $cost_qty = 0;
                    $sellerArr = array();
                    $sellerNameStr = '';
                    //$is_oversize = false;
                    foreach ($oc_products as $oc_product) {
                        $productId = $oc_product['product_id'];
                        //add by xxli 判断product_id的库存
                        if (isset($productCostMap[$productId])) {
                            // 有库存
                            $cost_qty += $productCostMap[$productId];
                            $sellerNameStr .= $productSellerMap[$productId] . ',';
                        }

                        $sellerArr[] = array(
                            'product_id' => $productId,
                            'name' => $oc_product['screenname']
                        );
                        //oversize_array 的 order 对应的状态
                        //buyer库存itemcode对应的数量
                        $costQtyArr[$itemCode] = $cost_qty;


                    }

                    //$lineList[$key]['is_oversize'] = $is_oversize;
                    $lineList[$key]['sellerArr'] = $sellerArr;
                    $lineList[$key]['stockQty'] = $cost_qty;
                    if ($sellerNameStr != '') {
                        $lineList[$key]['sellerStr'] = rtrim($sellerNameStr, ',');
                    } else {
                        $lineList[$key]['sellerStr'] = '';
                    }
                    //如果oc_products为1更新product_id
                    $canBuyProduct = $oc_products;
                    //sku 和 产品 一一对应
                    if (count($canBuyProduct) == 1) {
                        //更新 line 表的 product_id
                        $this->updateCustomerSalesOrderLineProductId($canBuyProduct[0]['product_id'], $value['id']);
                        $lineList[$key]['product_id'] = $canBuyProduct[0]['product_id'];
                    }

                } else {
                    // noProductArr 把line信息放进去
                    if (isset($noProductArr[$itemCode])) {
                        //{#12406 order fullfillment 2期#}
                        $customerSalesOrderLineEntity = $noProductArr[$itemCode];
                        $customerSalesOrderLineEntity['qty'] += (int)$lineList[$key]['qty'];
                        $customerSalesOrderLineEntity['qty_list'][] = $lineList[$key]['qty'];
                        $customerSalesOrderLineEntity['order_id_list'][] = $lineList[$key]['order_id'];
                        $noProductArr[$itemCode] = $customerSalesOrderLineEntity;
                    } else {
                        $noProductArr[$itemCode] = $lineList[$key];
                        $noProductArr[$itemCode]['qty_list'][] = $lineList[$key]['qty'];
                        $noProductArr[$itemCode]['order_id_list'][] = $lineList[$key]['order_id'];
                    }
                }
                //更新制造商icon ,品牌判断需要修改2019-01-10
                //$imageId = $this->getManufactureImageId($value['image_id'],$value['product_id']);
                // add by lilei 暂时都是1
                $imageId = 1;
                $this->updateCustomerSalesOrderLineImageId($imageId, $value['id']);
                if (isset($headerCustomerSalesOrderMap[$value['header_id']])) {
                    $headerCustomerSalesOrderMap[$value['header_id']][] = $lineList[$key];
                } else {
                    $headerCustomerSalesOrderMap[$value['header_id']] = array();
                    $headerCustomerSalesOrderMap[$value['header_id']][] = $lineList[$key];
                }

            }
            $keys = array_keys($headerCustomerSalesOrderMap);
            foreach ($keys as $orderNo => $k) {
                $lineDatas = $headerCustomerSalesOrderMap[$k];
                $index = 0;
                foreach ($lineDatas as $lineNo => $lineData) {
                    //验证 是否oversize 写入lineData
                    //                    if(isset($oversize_array[$lineData['header_id']])) {
                    //                        $lineData['is_oversize'] = $oversize_array[$lineData['header_id']];
                    //                    }
                    if (isset($costQtyArr[$lineData['item_code']])) {
                        $cost_qty = $costQtyArr[$lineData['item_code']];
                        if ($cost_qty > 0) {
                            if ($cost_qty >= (int)$lineData['qty']) {
                                // 减去已售未发
                                $cost_qty -= (int)$lineData['qty'];
                                $costQtyArr[$lineData['item_code']] = $cost_qty;
                                #12406 order fullfillment 二期#
                                //$sucessArr = $headerCustomerSalesOrderMap[$k];
                                //$sucessArr[$lineNo]['qty'] = (int)$lineData['qty'];
                                //$sucessArr[$lineNo]['order_status'] = 1;
                                //$productArr[$orderNo][] = $sucessArr[$lineNo];
                                $sucessArr = $headerCustomerSalesOrderMap[$k][$lineNo];
                                $sucessArr['qty'] = (int)$lineData['qty'];
                                $sucessArr['order_status'] = 1;
                                //$sucessArr['is_oversize'] = $lineData['is_oversize'];
                                $productArr[$orderNo][] = $sucessArr;
                                $index++;
                            } else {
                                $needToBuy = (int)$lineData['qty'] - $cost_qty;
                                $costQtyArr[$lineData['item_code']] = $cost_qty - (int)$lineData['qty'];
                                #12406 order fullfillment 二期#
                                //$sucessArr = $headerCustomerSalesOrderMap[$k];
                                //$sucessArr[$lineNo]['qty'] = $cost_qty;
                                //$sucessArr[$lineNo]['order_status'] = 1;
                                //$productArr[$orderNo][] = $sucessArr[$lineNo];
                                $sucessArr = $headerCustomerSalesOrderMap[$k][$lineNo];
                                $sucessArr['qty'] = $cost_qty;
                                $sucessArr['order_status'] = 1;
                                //$sucessArr['is_oversize'] = $lineData['is_oversize'];
                                $productArr[$orderNo][] = $sucessArr;
                                $cost_qty = $needToBuy;
                                $lineData['qty'] = $needToBuy;
                                $hasNoCostArr[] = $lineData;
                            }
                        } else {
                            $hasNoCostArr[] = $lineData;
                        }

                    }
                }
                // #24701 上门取货导单优化，在此处不在绑定采销关系
                $mapOrderSave = [];
                $mapOrderSave['id'] = $k;
                // 上门取货导单修改后订单上传完label后订单状态改为to be paid
                // 这里有可能因为并发的原因，订单已经变成BP了，不将BP的订单改为New Order
                $salesOrder = CustomerSalesOrder::query()->where($mapOrderSave)->select('order_status', 'to_be_paid_time')->first();
                if ($salesOrder && $salesOrder->order_status != CustomerSalesOrderStatus::BEING_PROCESSED) {
                    $toUpdate = [];
                    $toUpdate['order_status'] = CustomerSalesOrderStatus::TO_BE_PAID;
                    if (is_null($salesOrder->to_be_paid_time)) {
                        $toUpdate['to_be_paid_time'] = Carbon::now()->toDateTimeString();
                    }
                    CustomerSalesOrder::query()
                        ->where($mapOrderSave)
                        ->where('order_status', '<>', CustomerSalesOrderStatus::BEING_PROCESSED)
                        ->update($toUpdate);
                }
            }
            //$hasNoProductArr = array_values($noProductArr);
            foreach ($noProductArr as $customerSalesOrderLine) {
                //$hasNoProductArr[] = $customerSalesOrderLine;
                //13827【需求】Reorder页面和购买弹窗中的不存在在平台上的SKU表格拆分成未建立关联的SKU和平台上不存在的SKU两个表格
                //放入的只有两种情况 一种平台无sku 一种为未建立联系
                $exist_flag = $this->judgeSkuIsExist($customerSalesOrderLine['item_code']);
                if ($exist_flag) {
                    $hasNoProductArr[] = $customerSalesOrderLine;
                } else {
                    $hasNoExistProductArr[] = $customerSalesOrderLine;
                }
            }

        }
        $res['hasNoProductArr'] = $hasNoProductArr;
        $res['hasNoCostArr'] = $hasNoCostArr;
        $res['productArr'] = $productArr;
        //13827【需求】Reorder页面和购买弹窗中的不存在在平台上的SKU表格拆分成未建立关联的SKU和平台上不存在的SKU两个表格
        $res['hasNoExistProductArr'] = $hasNoExistProductArr;
        return $res;

    }

    /**
     * [getDropshipProductsLtlUpdate description]
     * @param $lineList
     * @return void
     * @throws Exception
     */
    public function getDropshipProductsLtlUpdate($lineList)
    {
        $customerId = $this->customer->getId();
        $this->load->model('catalog/product');
        $ltl_arr = [];
        foreach ($lineList as $key => $value) {
            $tag_array = $this->model_catalog_product->getProductSpecificTagByOrderLineId($value['id'], $this->config->get('tag_id_oversize'));
            if (isset($tag_array) && $tag_array['description'] == 'Oversized Item') {
                $this->orm->table('tb_sys_customer_sales_order_line')->where('id', $value['id'])->update(['item_status' => 64]);
                if (!in_array($value['header_id'], $ltl_arr)) {
                    $this->orm->table('tb_sys_customer_sales_order')->where('id', $value['header_id'])->update(['order_status' => CustomerSalesOrderStatus::LTL_CHECK]);
                    $ltl_arr[] = $value['header_id'];
                }
            }

        }


    }

    /**
     * [getManufactureImageId description] copy by allen.tai  edit by lxx
     * @param int $brandId
     * @param int $productId
     * @return array
     */
    private function getManufactureImageId($brandId, $productId)
    {
        $result = null;
        if (!empty($brandId)) {
            $userSet = $this->findManufactureInfoByManufactureId($brandId);
            if (!empty($productId)) {
                $sysSet = $this->findManufactureInfoByProductId($productId);
                if (!empty($userSet)) {
                    if (!empty($sysSet)) {
                        if (($this->customer->getId() == $userSet['customer_id']) && ($sysSet['can_brand'] == true)) {
                            $result = $userSet['image_id'];
                        } else {
                            $result = $sysSet['image_id'];
                        }
                    }
                } else {
                    if (!empty($sysSet)) {
                        $result = $sysSet['image_id'];
                    }
                }
            }
        } else {
            if (!empty($productId)) {
                $info = $this->findManufactureInfoByProductId($productId);
                if (!empty($info)) {
                    $result = $info['image_id'];
                }
            }
        }
        return $result;
    }

    /**
     * [verifyCommonOrderCsvUpload description] dropship 下 common order 不验证 ltl check
     * @param $data
     * @param $runId
     * @param int $country_id
     * @return bool|string
     */
    public function verifyCommonOrderCsvUpload($data, $runId, $country_id)
    {
        $orderArr = [];
        $verify_order = [];
        $now = date("Y-m-d H:i:s", time());
        // dropship 下的 common order
        $order_mode = HomePickUploadType::ORDER_MODE_HOMEPICK;
        if (isset($data) && count($data) > 0) {
            $index = 2;
            //已存在的订单OrderId
            $existentOrderIdArray = [];
            foreach ($data as $key => $value) {
                $index++;
                $flag = true;
                foreach ($value as $k => $v) {
                    if ($v != '') {
                        $flag = false;
                        break;
                    }
                }
                if ($flag) {
                    continue;
                }
                $res = $this->verifyCommonOrderCsvColumn($value, $key + 3, $country_id);
                //dropship 下的 common order 没有签收服务费
                if (strtoupper(trim($value['ShipToService'])) == 'ASR') {
                    $value['ShipToService'] = null;
                }
                if ($res !== true) {
                    $err = $res;
                    return $err;
                }
                //无order_id 丢失问题
                //无seller_id问题
                //查询是否建立订单order_id
                $checkResult = $this->judgeCommonOrderIsExist(trim($value['OrderId']), $this->customer->getId());
                if ($checkResult)
                    $existentOrderIdArray[] = trim($value['OrderId']);

                $flag_merge = false;
                //合并同一订单相同的sku的订单明细
                foreach ($orderArr as $ks => $order) {
                    if (trim($order['order_id']) == trim($value['OrderId']) && trim($order['item_code']) == trim($value['B2BItemCode'])) {
                        $orderArr[$key]['qty'] = $order['qty'] + $value['ShipToQty'];
                        $flag_merge = true;
                    }
                }
                if ($flag_merge) {
                    continue;
                }
                $orderArr[] = [
                    "orders_from" => $value['SalesPlatform'] == '' ? "" : $value['SalesPlatform'],
                    "order_id" => $value['OrderId'] == '' ? null : $value['OrderId'],
                    "line_item_number" => $value['LineItemNumber'] == '' ? null : $value['LineItemNumber'],
                    "email" => $value['ShipToEmail'] == '' ? null : $value['ShipToEmail'],
                    "order_date" => $value['OrderDate'] == '' ? null : $value['OrderDate'],
                    "bill_name" => $value['ShipToName'] == '' ? null : $value['ShipToName'],
                    "bill_address" => $value['ShipToAddressDetail'] == '' ? null : $value['ShipToAddressDetail'],
                    "bill_city" => $value['ShipToCity'] == '' ? null : $value['ShipToCity'],
                    "bill_state" => $value['ShipToState'] == '' ? null : $value['ShipToState'],
                    "bill_zip_code" => $value['ShipToPostalCode'] == '' ? null : $value['ShipToPostalCode'],
                    "bill_country" => $value['ShipToCountry'] == '' ? null : $value['ShipToCountry'],
                    "ship_name" => $value['ShipToName'] == '' ? null : $value['ShipToName'],
                    "ship_address1" => $value['ShipToAddressDetail'] == '' ? null : $value['ShipToAddressDetail'],
                    "ship_address2" => null,
                    "ship_city" => $value['ShipToCity'] == '' ? null : $value['ShipToCity'],
                    "ship_state" => $value['ShipToState'] == '' ? null : $value['ShipToState'],
                    "ship_zip_code" => $value['ShipToPostalCode'] == '' ? null : $value['ShipToPostalCode'],
                    "ship_country" => $value['ShipToCountry'] == '' ? null : $value['ShipToCountry'],
                    "ship_phone" => $value['ShipToPhone'] == '' ? null : $value['ShipToPhone'],
                    "item_code" => $value['B2BItemCode'] == '' ? null : strtoupper(trim($value['B2BItemCode'])),
                    "alt_item_id" => $value['BuyerSkuLink'] == '' ? null : $value['BuyerSkuLink'],
                    "product_name" => $value['BuyerSkuDescription'] == '' ? null : $value['BuyerSkuDescription'],
                    "qty" => $value['ShipToQty'] == '' ? null : $value['ShipToQty'],
                    "item_price" => $value['BuyerSkuCommercialValue'] == '' ? 1 : $value['BuyerSkuCommercialValue'],
                    "item_unit_discount" => null,
                    "item_tax" => null,
                    "discount_amount" => null,
                    "tax_amount" => null,
                    "ship_amount" => null,
                    "order_total" => 1,
                    "payment_method" => null,
                    "ship_company" => null,
                    "ship_method" => $value['ShipToService'] == '' ? null : strtoupper($value['ShipToService']),
                    "ship_service_level" => $value['ShipToServiceLevel'] == '' ? null : $value['ShipToServiceLevel'],
                    "brand_id" => $value['BuyerBrand'] == '' ? null : $value['BuyerBrand'],
                    "customer_comments" => $value['OrderComments'] == '' ? null : $value['OrderComments'],
                    "shipped_date" => $value['ShippedDate'] == '' ? null : trim($value['ShippedDate']),//13195OrderFulfillment订单导入模板调优
                    "ship_to_attachment_url" => $value['ShipToAttachmentUrl'] == '' ? null : $value['ShipToAttachmentUrl'],
                    //"seller_id"          => $sellerId,
                    "buyer_id" => $this->customer->getId(),
                    "run_id" => $runId,
                    "create_user_name" => $this->customer->getId(),
                    "create_time" => $now,
                    "update_user_name" => PROGRAM_CODE
                ];
                //order_id+lineItemNo不能相同
                $verify_order[] = trim($value['OrderId']) . '_' . $value['LineItemNumber'];

            }
            if (count($verify_order) != count(array_unique($verify_order))) {
                $err = "Order_id Duplicate,please check the uploaded CSV file.";
                return $err;
            }
            if (!empty($existentOrderIdArray)) {
                $err = 'OrderId:' . implode( '、', $existentOrderIdArray) . ' is already exist ,please check the uploaded CSV file.';
                return $err;
            }


            // 插入临时表
            $this->saveCustomerSalesOrderTemp($orderArr);
            // 根据RunId获取上步插入的临时表数据
            $orderTempArr = $this->findCustomerSalesOrderTemp($runId);
            // 订单头表数据
            $customerSalesOrderArr = [];
            //
            $yzc_order_id_number = $this->sequence->getYzcOrderIdNumber();
            $countArr = [];
            foreach ($orderTempArr as $orderTemp) {
                // 导入的订单以order-id进行分组
                $order_id = $orderTemp['order_id'];
                $customerSalesOrder = null;
                if (!isset($customerSalesOrderArr[$order_id])) {
                    $yzc_order_id_number++;
                    // 新订单头
                    $count = 1;
                    $countArr[$order_id] = $count;
                    $customerSalesOrder = new Yzc\CustomerSalesOrder($orderTemp, $order_mode);
                    $customerSalesOrder->yzc_order_id = "YC-" . $yzc_order_id_number;
                    $customerSalesOrderLine = new Yzc\CustomerSalesOrderLine($orderTemp);
                    // 计算LineItemNumber
                    $customerSalesOrderLine->line_item_number = $count;

                    // 明细条数
                    $customerSalesOrderLineArr = $customerSalesOrder->customer_sales_order_lines;
                    $customerSalesOrderLineArr[] = $customerSalesOrderLine;
                    $customerSalesOrder->customer_sales_order_lines = $customerSalesOrderLineArr;
                    // 总条数
                    $customerSalesOrder->line_count = $count;
                    $customerSalesOrderArr[$order_id] = $customerSalesOrder;
                } else {
                    $count = $countArr[$order_id];
                    $count++;
                    $countArr[$order_id] = $count;
                    $customerSalesOrder = $customerSalesOrderArr[$order_id];
                    $customerSalesOrderLine = new Yzc\CustomerSalesOrderLine($orderTemp, $order_mode);
                    // 计算LineItemNumber
                    $customerSalesOrderLine->line_item_number = $count;
                    // 明细条数
                    $customerSalesOrderLineArr = $customerSalesOrder->customer_sales_order_lines;
                    $customerSalesOrderLineArr[] = $customerSalesOrderLine;
                    $customerSalesOrder->customer_sales_order_lines = $customerSalesOrderLineArr;
                    // 总条数
                    $customerSalesOrder->line_count = $count;
                    //if(isset($orderTemp['ship_method']) && strcasecmp(trim($orderTemp['ship_method']),'ASR') == 0){
                    //    $customerSalesOrder->ship_method = 'ASR';
                    //}
                    $customerSalesOrderArr[$order_id] = $customerSalesOrder;

                }
            }
            $this->sequence->updateYzcOrderIdNumber($yzc_order_id_number);
            // 插入头表数据
            $this->saveCustomerSalesOrders($customerSalesOrderArr);
            // 获取上步插入的头表数据
            $customerSalesOrders = $this->findCustomerSalesOrders($runId);
            $customerSalesOrderLines = array();
            foreach ($customerSalesOrders as $entity) {
                $order_id = $entity['order_id'];
                $customerSalesOrder = $customerSalesOrderArr[$order_id];
                $customerSalesOrderLineArr = $customerSalesOrder->customer_sales_order_lines;
                foreach ($customerSalesOrderLineArr as $customerSalesOrderLine) {
                    $customerSalesOrderLine->header_id = $entity['id'];
                    $customerSalesOrderLines[] = $customerSalesOrderLine;
                }
            }
            // 插入明细表
            $this->saveCustomerSalesOrderLine($customerSalesOrderLines);
            return true;

        }


    }

    public function getOrderItemPrice($price, $country_id)
    {
        $price = trim($price);
        if ($country_id == AMERICAN_COUNTRY_ID && Str::startsWith($price, ['$'])) {
            $price = substr($price, strpos($price, '$') + 1);
        }

        if ($country_id == JAPAN_COUNTRY_ID && Str::startsWith($price, ['￥'])) {
            $price = substr($price, strpos($price, '￥') + 1);
        }

        if (($country_id == HomePickUploadType::BRITAIN_COUNTRY_ID)
            || ($country_id == HomePickUploadType::GERMANY_COUNTRY_ID)) {
            if (Str::startsWith($price, ['£'])) {
                $price = substr($price, 1 - utf8_strlen($price));
            }

        }

        return trim(str_ireplace(',', '', $price));
    }

    /**
     * [verifyDropshipCsvUpload description] 验证dropship上传的csv是否有问题
     * @param $data
     * @param $runId file 的 时间 配合custom_id 使用
     * @param int $importMode 4
     * @return array|bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function verifyDropshipCsvUpload($data, $runId, $importMode)
    {
        // 包装订单数据
        $country_id = $this->customer->getCountryId();
        $this->cache->delete($this->customer->getId() . '_' . 'dropship_csv');
        $this->cache->delete($this->customer->getId() . '_' . 'dropship_csv_key');
        $orderArr = array();
        $now = date("Y-m-d H:i:s", time());
        // 订单模式默认普通模式,这个是dropship
        $order_mode = HomePickUploadType::ORDER_MODE_HOMEPICK;
        if (isset($data) && count($data) > 0) {

            //已存在的订单OrderId
            $existentOrderIdArray = array();
            foreach ($data as $key => $value) {
                $flag = true;
                foreach ($value as $k => $v) {
                    if ($v != '') {
                        $flag = false;
                        break;
                    }
                }
                if ($flag) {
                    continue;
                }
                $res = $this->verifyDropshipCsvColumn($value, $key + 2);
                if ($res !== true) {
                    //$this->updateUploadStatus($runId,$this->customer->getId());
                    return $res;
                }
                //无order_id 丢失问题
                //无seller_id问题
                //查询是否建立订单order_id

                $checkResult = $this->judgeDropshipOrderIsExist(trim($data[$key]['Order ID']), $this->customer->getId());
                if ($checkResult)
                    $existentOrderIdArray[] = $value['Order ID'];
                //美国和英国  亚马逊导单统一
                if ($country_id == AMERICAN_COUNTRY_ID || $country_id == HomePickUploadType::BRITAIN_COUNTRY_ID) {
                    $warehouse_name = $this->getWarehouseCodeName($value['Warehouse Code'], PlatformMapping::AMAZON);
                    $orderArr[] = [
                        "order_id" => trim($data[$key]['Order ID']),
                        "order_status" => $value['Order Status'],
                        "warehouse_code" => $value['Warehouse Code'] == '' ? 'warehouse_code' : $value['Warehouse Code'],
                        "warehouse_name" => $warehouse_name == '' ? 'warehouse_code' : $warehouse_name,
                        "order_place_date" => $value['Order Place Date'] == '' ? null : $value['Order Place Date'],
                        "required_ship_date" => $value['Required Ship Date'] == '' ? null : $value['Required Ship Date'],
                        "ship_method" => $value['Ship Method'] == '' ? null : $value['Ship Method'],
                        "ship_method_code" => $value['Ship Method Code'] == '' ? null : $value['Ship Method Code'],
                        "ship_to_name" => $value['Ship To Name'] == '' ? null : $value['Ship To Name'],
                        "ship_to_address_line1" => $value['Ship To Address Line 1'] == '' ? null : $value['Ship To Address Line 1'],
                        "ship_to_address_line2" => $value['Ship To Address Line 2'] == '' ? null : $value['Ship To Address Line 2'],
                        "ship_to_address_line3" => $value['Ship To Address Line 3'] == '' ? null : $value['Ship To Address Line 3'],
                        "ship_to_state" => $value['Ship To State'] == '' ? null : $value['Ship To State'],
                        "ship_to_city" => $value['Ship To City'] == '' ? null : $value['Ship To City'],
                        "ship_to_zip_code" => $value['Ship To ZIP Code'] == '' ? null : $value['Ship To ZIP Code'],
                        "ship_to_country" => $value['Ship To Country'] == '' ? null : $value['Ship To Country'],
                        "phone_number" => $value['Phone Number'] == '' ? null : $value['Phone Number'],
                        "is_gift" => $value['Is it Gift?'] == '' ? null : $value['Is it Gift?'],
                        "item_cost" => $value['Item Cost'] == '' ? 1 : $value['Item Cost'],
                        "sku" => $value['SKU'] == '' ? null : strtoupper(trim($value['SKU'])),
                        "asin" => $value['ASIN'] == '' ? null : $value['ASIN'],
                        "item_title" => $value['Item Title'] == '' ? 'item_title' : $value['Item Title'],
                        "item_quantity" => $value['Item Quantity'] == '' ? null : $value['Item Quantity'],
                        "gift_message" => $value['Gift Message'] == '' ? null : $value['Gift Message'],
                        "tracking_id" => $value['Tracking ID'] == '' ? null : $value['Tracking ID'],
                        "shipped_date" => $value['Shipped Date'] == '' ? null : $value['Shipped Date'],
                        //"memo"                      => ,
                        "create_id" => $this->customer->getId(),
                        "create_time" => date('Y-m-d H:i:s', time()),
                        //"update_id"                 => null,
                        //"update_time"               => null,
                        "program_code" => PROGRAM_CODE,
                        "buyer_id" => $this->customer->getId(),
                        "run_id" => $runId,
                    ];
                }

            }


        }
        if (!empty($existentOrderIdArray)) {
            //$this->updateUploadStatus($runId,$this->customer->getId());
            return 'OrderId:' . implode('、', $existentOrderIdArray ) . ' is already exist ,please check the uploaded CSV file.';
        }
        //插入临时表之前
        // 13425 dropship
        $orderArr = $this->uniqueDropShipTempList($orderArr);
        $this->insertDropshipTempTable($orderArr);
        // 根据RunId 和customer_id
        $orderTempArr = $this->getDropshipTempRecordByRunid($runId, $this->customer->getId());
        //$orderTempArr[1]['order_id'] = $orderTempArr[0]['order_id'];
        //$orderTempArr[2]['order_id'] = $orderTempArr[0]['order_id'];
        // 订单头表就是 订单的非商品信息表
        $customerSalesDropshipOrderArr = [];
        $yzc_order_id_number = $this->sequence->getYzcOrderIdNumber();
        foreach ($orderTempArr as $key => $value) {
            //导入订单根据order_id来进行合并
            $order_id = $value['order_id'];
            $customerSalesDropshipOrder = null;
            $dropshipOrder = $this->getOrderColumnNameConversion($value, $order_mode, $country_id, $importMode);
            if (!isset($customerSalesDropshipOrderArr[$order_id])) {
                $yzc_order_id_number++;
                // 新订单头
                //获取一个插入dropship和tb_sys_customer_sales_order的映射关系
                $dropshipOrder['yzc_order_id'] = 'YC-' . $yzc_order_id_number;
                $customerSalesDropshipOrderArr[$order_id] = $dropshipOrder;
            } else {
                //订单信息有变动需要更改
                // line_count
                // order_total
                // line_item_number
                $tmp = $dropshipOrder['product_info'][0];
                $tmp['line_item_number'] = count($customerSalesDropshipOrderArr[$order_id]['product_info']) + 1;
                $customerSalesDropshipOrderArr[$order_id]['line_count'] = count($customerSalesDropshipOrderArr[$order_id]['product_info']) + 1;
                $customerSalesDropshipOrderArr[$order_id]['order_total'] += $tmp['item_price'] * $tmp['qty'];
                $customerSalesDropshipOrderArr[$order_id]['order_total'] = sprintf('%.2f', $customerSalesDropshipOrderArr[$order_id]['order_total']);
                $customerSalesDropshipOrderArr[$order_id]['product_info'][] = $tmp;
            }

        }
        $this->sequence->updateYzcOrderIdNumber($yzc_order_id_number);
        //插入order和line表
        $this->insertCustomerSalesOrderAndLine($customerSalesDropshipOrderArr);
        return true;


    }

    public function verifyEuropeWayFairCsvUpload($data, $runId, $importMode, $country_id, $customer_id)
    {
        // 包装订单数据
        $orderArr = [];
        //不能通过临时表来做
        $temp_table = 'tb_sys_customer_sales_wayfair_temp';
        // 订单模式默认普通模式,上一个是dropship 这个是wayfair
        $order_mode = HomePickUploadType::ORDER_MODE_HOMEPICK;
        if (isset($data) && count($data) > 0) {
            //已存在的订单OrderId
            $existentOrderIdArray = [];
            foreach ($data as $key => $value) {
                $flag = true;
                foreach ($value as $k => $v) {
                    if ($v != '') {
                        $flag = false;
                        break;
                    }
                }
                if ($flag) {
                    continue;
                }
                $res = $this->verifyEuropeWayFairCsvColumn($value, $key + 2, $country_id);
                if ($res !== true) {
                    return $res;
                }
                //无order_id 丢失问题
                //无seller_id问题
                //查询是否建立订单order_id

                $checkResult = $this->judgeOrderIsExist(trim($data[$key]['PO Number']), $this->customer->getId(), 'tb_sys_customer_sales_order');
                if ($checkResult) {
                    $existentOrderIdArray[] = $value['PO Number'];
                }
                $warehouse_name = $this->getWarehouseCodeName($value['Warehouse Name'], PlatformMapping::WAYFAIR);
                $orderArr[] = [
                    "order_id" => trim($value['PO Number']),
                    "warehouse_code" => $value['Warehouse Name'] == '' ? 'Warehouse Name' : $value['Warehouse Name'], // 这里的warehouse_code 是 Warehouse Name
                    "warehouse_name" => $warehouse_name == '' ? 'warehouse_code' : $warehouse_name,
                    "store_name" => $value['Store Name'] == null ? 'Wayfair' : $value['Store Name'],
                    "po_date" => $value['PO Date'] == '' ? null : $value['PO Date'],
                    "must_ship_by" => $value['Must Ship By'] == '' ? null : $value['Must Ship By'],
                    "backorder_date" => $value['Backorder Date'] == '' ? null : $value['Backorder Date'],
                    "order_status" => $value['Order Status'] == '' ? null : $value['Order Status'],
                    "item_code" => $value['item_code'] == '' ? null : $value['item_code'],
                    "item_#" => $value['Item Number'] == '' ? null : trim($value['Item Number']),
                    "item_name" => $value['Item Name'] == '' ? null : $value['Item Name'],
                    "quantity" => $value['Quantity'] == '' ? null : $value['Quantity'],
                    "wholesale_price" => $value['Wholesale Price'] == '' ? null : $value['Wholesale Price'],
                    "ship_method" => $value['Ship Method'] == '' ? null : $value['Ship Method'],
                    "carrier_name" => $value['Carrier Name'] == '' ? null : trim($value['Carrier Name']),
                    "shipping_account_number" => $value['Shipping Account Number'] == '' ? null : $value['Shipping Account Number'],
                    "ship_to_name" => $value['Ship To Name'] == '' ? null : $value['Ship To Name'],
                    "ship_to_address" => $value['Ship To Address'] . ' ' . $value['Ship To Address 2'] == '' ? null : $value['Ship To Address'] . ' ' . $value['Ship To Address 2'],
                    "ship_to_address2" => null,
                    "ship_to_city" => $value['Ship To City'] == '' ? null : $value['Ship To City'],
                    "ship_to_state" => $value['Ship To State'] == '' ? ' ' : $value['Ship To State'],
                    "ship_to_zip" => $value['Ship To Zip'] == '' ? null : $value['Ship To Zip'],
                    "ship_to_phone" => $value['Ship To Phone'] == '' ? null : $value['Ship To Phone'],
                    "inventory_at_po_time" => $value['Inventory at PO Time'] == '' ? null : $value['Inventory at PO Time'],
                    "inventory_send_date" => $value['Inventory Send Date'] == '' ? null : $value['Inventory Send Date'],
                    "ship_speed" => $value['Ship Speed'] == '' ? null : $value['Ship Speed'],
                    "po_date_&_time" => $value['PO Date & Time'] == '' ? null : $value['PO Date & Time'],
                    "registered_timestamp" => $value['Registered Timestamp'] == '' ? null : $value['Registered Timestamp'],
                    "customization_text" => $value['Customization Text'] == '' ? 'item_title' : $value['Customization Text'],
                    "event_name" => $value['Event Name'] == '' ? null : $value['Event Name'],
                    "event_id" => $value['Event ID'] == '' ? null : $value['Event ID'],
                    "event_start_date" => $value['Event Start Date'] == '' ? null : $value['Event Start Date'],
                    "event_end_date" => $value['Event End Date'] == '' ? null : $value['Event End Date'],
                    "event_type" => $value['Event Type'] == '' ? null : $value['Event Type'],
                    "backorder_reason" => $value['Backorder Reason'] == '' ? null : $value['Backorder Reason'],
                    "original_product_id" => $value['Original Product ID'] == '' ? null : $value['Original Product ID'],
                    "original_product_name" => $value['Original Product Name'] == '' ? null : $value['Original Product Name'],
                    "event_inventory_source" => $value['Event Inventory Source'] == '' ? null : $value['Event Inventory Source'],
                    "packing_slip_url" => $value['Packing Slip URL'] == '' ? null : $value['Packing Slip URL'],
                    "tracking_#" => $value['Tracking Number'] == '' ? null : $value['Tracking Number'],
                    "ready_for_pickup_date" => $value['Ready for Pickup Date'] == '' ? null : trim($value['Ready for Pickup Date']),
                    "sku" => $value['SKU'] == '' ? null : $value['SKU'],  //这个sku 非 product sku 没有实际意义
                    "destination_country" => $value['Destination Country'] == '' ? null : $value['Destination Country'],
                    "depot_id" => $value['Depot ID'] == '' ? null : $value['Depot ID'],
                    "depot_name" => $value['Depot Name'] == '' ? null : $value['Depot Name'],
                    "wholesale_event_source" => $value['Wholesale Event Source'] == '' ? null : $value['Wholesale Event Source'],
                    "wholesale_event_store_source" => $value['Wholesale Event Store Source'] == '' ? null : $value['Wholesale Event Store Source'],
                    "b2border" => $value['B2BOrder'] == '' ? null : $value['B2BOrder'],
                    "composite_wood_product" => $value['Composite Wood Product'] == '' ? null : $value['Composite Wood Product'],
                    "sales_channel" => $value['Sales Channel'] == '' ? null : $value['Sales Channel'],
                    //"memo"                      => ,
                    "create_id" => $customer_id,
                    "create_time" => date('Y-m-d H:i:s', time()),
                    //"update_id"                 => null,
                    //"update_time"               => null,
                    "program_code" => PROGRAM_CODE,
                    "buyer_id" => $customer_id,
                    "run_id" => $runId,
                ];

            }
        }
        if (!empty($existentOrderIdArray)) {
            return 'PO Number:[' . implode('、', $existentOrderIdArray) . '] is duplicate with the other order, please modify it and upload again.';
        }
        //插入临时表之前 不需要合并item code
        $this->insertTempTable($orderArr, $temp_table);
        // 根据RunId 和customer_id
        $orderTempArr = $this->getTempRecordByRunid($runId, $customer_id, $temp_table);
        // 订单头表就是 订单的非商品信息表
        $customerSalesWayfairOrderArr = [];
        $yzc_order_id_number = $this->sequence->getYzcOrderIdNumber();
        foreach ($orderTempArr as $key => $value) {
            //导入订单根据order_id来进行合并
            $order_id = $value['order_id'];
            $customerSalesWayfairOrder = null;
            $wayFairOrder = $this->getWayfairOrderColumnNameConversion($value, $order_mode, $country_id, $importMode);
            if (!isset($customerSalesWayfairOrderArr[$order_id])) {
                $yzc_order_id_number++;
                // 新订单头
                //获取一个插入dropship和tb_sys_customer_sales_order的映射关系
                $wayFairOrder['yzc_order_id'] = 'YC-' . $yzc_order_id_number;
                $customerSalesWayfairOrderArr[$order_id] = $wayFairOrder;
            } else {
                //订单信息有变动需要更改
                // line_count
                // order_total
                // line_item_number
                $tmp = $wayFairOrder['product_info'][0];
                $tmp['line_item_number'] = count($customerSalesWayfairOrderArr[$order_id]['product_info']) + 1;
                $customerSalesWayfairOrderArr[$order_id]['line_count'] = count($customerSalesWayfairOrderArr[$order_id]['product_info']) + 1;
                $customerSalesWayfairOrderArr[$order_id]['order_total'] += $tmp['item_price'] * $tmp['qty'];
                $customerSalesWayfairOrderArr[$order_id]['order_total'] = sprintf('%.2f', $customerSalesWayfairOrderArr[$order_id]['order_total']);
                $customerSalesWayfairOrderArr[$order_id]['product_info'][] = $tmp;
            }

        }
        $this->sequence->updateYzcOrderIdNumber($yzc_order_id_number);
        //插入order和line表
        $this->insertCustomerSalesOrderAndLine($customerSalesWayfairOrderArr);
        return true;


    }

    /**
     * [getManifestManagementList description]
     * @param int $customer_id
     * @param array $condition
     * @return array
     */
    public function getManifestManagementList($customer_id, $condition = [])
    {
        $list = $this->orm->table('tb_sys_customer_sales_order as o')
            ->leftjoin('tb_sys_customer_sales_wayfair_temp as t', [['o.order_id', '=', 't.order_id'], ['t.buyer_id', '=', 'o.buyer_id']])
            ->leftjoin('tb_sys_customer_sales_order_line as l', 'l.header_id', '=', 'o.id')
            ->leftjoin('tb_sys_customer_sales_order_file as f', 'f.order_id', '=', 'o.id')
            ->leftjoin('tb_sys_dictionary as d', function ($join) {
                $join->on('d.DicKey', '=', 'o.order_status')->where('d.DicCategory', '=', 'CUSTOMER_ORDER_STATUS');
            })
            ->where(
                [
                    ['o.order_mode', '=', CustomerSalesOrderMode::PICK_UP],
                    ['o.buyer_id', '=', $customer_id],
                    ['o.import_mode', '=', HomePickImportMode::IMPORT_MODE_WAYFAIR],
                ]
            )
            ->when(isset($condition['order_id']), function ($q) use ($condition) {
                return $q->where([['o.order_id', 'like', "%{$condition['order_id']}%"]]);
            })
            ->when(isset($condition['is_synchroed']), function ($q) use ($condition) {
                if ($condition['is_synchroed']) {
                    return $q->whereNotNull('is_synchroed');
                } else {
                    return $q->whereNull('is_synchroed');
                }
            })
            ->groupBy('o.order_id')
            ->select('o.id', 'o.order_id', 't.warehouse_name', 't.carrier_name', 't.ready_for_pickup_date', 'o.order_status', 'f.file_name', 'f.deal_file_path', 'd.DicValue as order_status_name', 'l.is_synchroed')
            //            ->forPage(($condition['page']), $condition['page_limit'])
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        //区分未填写和填写的order id
        $do = [];
        $undo = [];
        foreach ($list as $key => &$value) {
            $value['carrier_name_compare'] = strtoupper($value['carrier_name']);
            if ($value['deal_file_path']) {
                $do[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name'] . '|' . $value['deal_file_path']]['order_date'] = $value['ready_for_pickup_date'];
                $do[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name'] . '|' . $value['deal_file_path']]['warehouse_name'] = $value['warehouse_name'];
                $do[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name'] . '|' . $value['deal_file_path']]['carrier_name'] = $value['carrier_name'];
                $do[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name'] . '|' . $value['deal_file_path']]['order_id'][] = $value['id'];
                $do[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name'] . '|' . $value['deal_file_path']]['deal_file_path'] = $value['deal_file_path'];
                $do[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name'] . '|' . $value['deal_file_path']]['file_name'] = $value['file_name'];
                $do[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name'] . '|' . $value['deal_file_path']]['is_uploaded'] = 1;
                if ($value['is_synchroed'] || (isset($do[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['deal_file_path']]['is_synchroed']) && $do[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['deal_file_path']]['is_synchroed'] == 1)) {
                    $do[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name'] . '|' . $value['deal_file_path']]['is_synchroed'] = 1;
                } else {
                    $do[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name'] . '|' . $value['deal_file_path']]['is_synchroed'] = 0;
                }
                $do[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name'] . '|' . $value['deal_file_path']]['list'][] = $value;
            } else {
                $undo[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['order_date'] = $value['ready_for_pickup_date'];
                $undo[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['warehouse_name'] = $value['warehouse_name'];
                $undo[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['carrier_name'] = $value['carrier_name'];
                $undo[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['order_id'][] = $value['id'];
                $undo[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['is_uploaded'] = 0;
                $undo[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['is_synchroed'] = 0;
                $undo[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['list'][] = $value;
            }
        }
        krsort($do);
        krsort($undo);
        $do = array_values($do);
        $undo = array_values($undo);
        foreach ($undo as $key => $value) {
            foreach ($value['list'] as $ks => $vs) {
                $tmp = $this->getManifestLineInfo($vs['id'], $customer_id);
                $undo[$key]['list'][$ks]['package_qty'] = $tmp['package_qty'];
                $undo[$key]['list'][$ks]['line_list'] = $tmp['line_list'];
            }
            $undo[$key]['order_amount'] = count($value['order_id']);
            $undo[$key]['package_qty'] = array_sum(array_column($undo[$key]['list'], 'package_qty'));
            $undo[$key]['order_id_all'] = implode('_', $value['order_id']);


        }
        foreach ($do as $key => $value) {
            foreach ($value['list'] as $ks => $vs) {
                $tmp = $this->getManifestLineInfo($vs['id'], $customer_id);
                $do[$key]['list'][$ks]['package_qty'] = $tmp['package_qty'];
                $do[$key]['list'][$ks]['line_list'] = $tmp['line_list'];
            }
            $do[$key]['order_amount'] = count($value['order_id']);
            $do[$key]['package_qty'] = array_sum(array_column($do[$key]['list'], 'package_qty'));
            $do[$key]['order_id_all'] = implode('_', $value['order_id']);
        }
        return array_merge($undo, $do);
    }

    public function getManifestLineInfo($id, $customer_id)
    {
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $list = $this->orm->table('tb_sys_customer_sales_order_line as l')
            ->leftJoin('tb_sys_customer_sales_wayfair_temp as t', 't.id', '=', 'l.temp_id')
            ->where('l.header_id', $id)
            ->select('l.*', 't.item_# as t_item_code')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        $package_qty = 0;
        foreach ($list as $key => $value) {
            // 获取 product_id
            // 获取 tag
            // 获取 image
            // 获取当前line的包裹数
            // 根据sku 查询出一个有效的
            $value['product_id'] = $this->getFirstProductId($value['item_code'], $customer_id);
            $list[$key]['tag_array'] = $this->model_catalog_product->getProductTagHtmlForThumb($value['product_id']);
            $image = $this->orm->table(DB_PREFIX . 'product')->where('product_id', $value['product_id'])->value('image');
            $list[$key]['image_show'] = $this->model_tool_image->resize($image, 40, 40);
            $list[$key]['product_link'] = $this->url->link('product/product', 'product_id=' . $value['product_id']);
            // 获取当前的包裹数
            $single_qty = $this->orm->table('tb_sys_product_set_info as s')->where('s.product_id', $value['product_id'])->sum('qty');
            $list[$key]['package_qty'] = $single_qty == 0 ? $value['qty'] : ($value['qty'] * $single_qty);
            $package_qty += $list[$key]['package_qty'];
            if ($key == 0) {
                $list[$key]['is_show'] = 1;
                $list[$key]['row_span'] = count($list);
            } else {
                $list[$key]['row_span']['is_show'] = 0;
            }
        }

        $ret['line_list'] = $list;
        $ret['package_qty'] = $package_qty;
        return $ret;

    }

    public function getManifestListByRunId($runId, $customer_id, $importMode)
    {
        //先查询出所有的
        $list = $this->orm->table('tb_sys_customer_sales_order as o')
            ->leftjoin('tb_sys_customer_sales_wayfair_temp as t', [['o.order_id', '=', 't.order_id'], ['t.buyer_id', '=', 'o.buyer_id']])
            ->leftjoin('tb_sys_customer_sales_order_line as l', 'l.header_id', '=', 'o.id')
            ->leftjoin('tb_sys_customer_sales_order_file as f', 'f.order_id', '=', 'o.id')
            ->leftjoin('tb_sys_dictionary as d', function ($join) {
                $join->on('d.DicKey', '=', 'o.order_status')->where('d.DicCategory', '=', 'CUSTOMER_ORDER_STATUS');
            })
            ->where(
                [
                    'o.run_id' => $runId,
                    'o.buyer_id' => $customer_id,
                    'o.import_mode' => $importMode,
                ]
            )
            ->groupBy('o.order_id')
            ->select('o.id', 'o.order_id', 't.warehouse_name', 't.carrier_name', 't.ready_for_pickup_date', 'o.order_status', 'f.file_name', 'f.deal_file_path', 'd.DicValue as order_status_name', 'l.is_synchroed')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        //区分未填写和填写的order id
        $do = [];
        $undo = [];
        foreach ($list as $key => &$value) {
            $value['carrier_name_compare'] = strtoupper($value['carrier_name']);
            $value['pickup_date'] = date('n/j/Y', strtotime($value['ready_for_pickup_date']));
            if ($value['deal_file_path']) {
                $do[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['order_date'] = $value['ready_for_pickup_date'];
                $do[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['warehouse_name'] = $value['warehouse_name'];
                $do[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['carrier_name'] = $value['carrier_name'];
                $do[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['pickup_date'] = $value['pickup_date'];
                $do[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['order_id'][] = $value['id'];
                $do[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['list'][] = $value;
            } else {
                $undo[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['order_date'] = $value['ready_for_pickup_date'];
                $undo[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['carrier_name'] = $value['carrier_name'];
                $undo[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['warehouse_name'] = $value['warehouse_name'];
                $undo[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['pickup_date'] = $value['pickup_date'];
                $undo[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['order_id'][] = $value['id'];
                $undo[$value['ready_for_pickup_date'] . '|' . $value['carrier_name_compare'] . '|' . $value['warehouse_name']]['list'][] = $value;
            }
        }
        ksort($do);
        ksort($undo);
        $do = array_values($do);
        $undo = array_values($undo);
        foreach ($undo as $key => $value) {
            foreach ($value['list'] as $ks => $vs) {
                $tmp = $this->getManifestLineInfo($vs['id'], $customer_id);
                $undo[$key]['list'][$ks]['package_qty'] = $tmp['package_qty'];
                $undo[$key]['list'][$ks]['line_list'] = $tmp['line_list'];
            }
            $undo[$key]['order_amount'] = count($value['order_id']);
            $undo[$key]['package_qty'] = array_sum(array_column($undo[$key]['list'], 'package_qty'));
            $undo[$key]['order_id_all'] = implode('_', $value['order_id']);


        }
        foreach ($do as $key => $value) {
            foreach ($value['list'] as $ks => $vs) {
                $tmp = $this->getManifestLineInfo($vs['id'], $customer_id);
                $do[$key]['list'][$ks]['package_qty'] = $tmp['package_qty'];
                $do[$key]['list'][$ks]['line_list'] = $tmp['line_list'];
            }
            $do[$key]['order_amount'] = count($value['order_id']);
            $do[$key]['package_qty'] = array_sum(array_column($do[$key]['list'], 'package_qty'));
            $do[$key]['order_id_all'] = implode('_', $value['order_id']);
        }
        return array_merge($undo, $do);
    }


    public function verifyManifestIsValid($str)
    {
        $list = explode(';', $str);
        $order_arr = [];
        foreach ($list as $key => $value) {
            if ($value) {
                $v = explode(':', $value);
                if (count($v) == 2) {
                    $tmp = explode('_', str_ireplace('_label', '', $v[0]));
                    foreach ($tmp as $ks => $vs) {
                        $order_arr[] = $vs;
                    }
                }
            }
        }
        return $this->verifyOrderIsSynchroed($order_arr);
    }

    public function verifyOrderIsSynchroed($order_list)
    {
        $list = $this->orm->table('tb_sys_customer_sales_order_line as l')
            ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
            ->whereNotNull('l.is_synchroed')
            ->whereIn('o.id', $order_list)
            ->selectRaw('group_concat(distinct o.order_id) as order_id')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        if ($list[0]['order_id']) {
            return $list[0]['order_id'];
        }
        return false;
    }

    public function judgeOrderManifestFile($customer_id)
    {
        return $this->orm->table('tb_sys_customer_sales_order as o')
            ->leftjoin('tb_sys_customer_sales_order_file as f', 'f.order_id', '=', 'o.id')
            ->where(
                [
                    ['o.order_mode', '=', HomePickUploadType::ORDER_MODE_HOMEPICK],
                    ['o.buyer_id', '=', $customer_id],
                    ['o.import_mode', '=', HomePickImportMode::IMPORT_MODE_WAYFAIR],
                ]
            )
            ->whereNull('f.file_name')
            ->exists();
    }

    /**
     * [updateManifestFile description]
     * @param $str
     * @param int $customer_id
     * @return mixed
     * @throws Exception
     */
    public function updateManifestFile($str, $customer_id)
    {
        $list = explode(';', $str);
        $manifest_arr = [];
        $error = '';
        foreach ($list as $key => $value) {
            if ($value) {
                $v = explode(':', $value);
                if (count($v) == 2) {
                    $order_list = explode('_', str_ireplace('_label', '', $v[0]));
                    $flag = $this->verifyOrderIsSynchroed($order_list);
                    if ($flag == false) {
                        $manifest_arr[$key]['order_id'] = $order_list;
                        $manifest_arr[$key]['container_id'] = str_ireplace('_label', '', $v[0]);
                        $manifest_arr[$key]['file_name'] = $v[1];
                    } else {
                        $error .= $v[1] . ',';
                    }
                }
            }
        }
        foreach ($manifest_arr as $key => $value) {
            $file_info = $this->orm->table('tb_sys_customer_sales_dropship_upload_file')
                ->where([
                    'status' => 1,
                    'container_id' => $value['container_id'],
                ])
                ->orderBy('id', 'desc')
                ->limit(1)
                ->get()
                ->map(function ($v) {
                    return (array)$v;
                })
                ->toArray();
            if ($file_info) {
                $mapInsert['file_name'] = $file_info[0]['file_name'];
                $mapInsert['deal_file_name'] = $file_info[0]['file_name'];
                $mapInsert['file_path'] = $file_info[0]['file_path'];
                $mapInsert['deal_file_path'] = $file_info[0]['deal_file_path'];
            }
            $mapInsert['create_user_name'] = $customer_id;
            $mapInsert['program_code'] = PROGRAM_CODE;
            $mapInsert['status'] = 1;
            foreach ($value['order_id'] as $k => $v) {
                $tmp = $this->orm->table('tb_sys_customer_sales_order as o')
                    ->leftjoin('tb_sys_customer_sales_order_line as l', 'l.header_id', '=', 'o.id')
                    ->leftjoin('tb_sys_customer_sales_wayfair_temp as t', 't.id', '=', 'l.temp_id')
                    ->where(
                        [
                            'o.id' => $v,
                            't.buyer_id' => $customer_id,
                        ]
                    )
                    ->select('t.carrier_name', 't.ready_for_pickup_date')
                    ->get()
                    ->map(function ($s) {
                        return (array)$s;
                    })
                    ->toArray();
                $mapInsert['order_id'] = $v;
                $mapInsert['order_date'] = $tmp[0]['ready_for_pickup_date'];
                $mapInsert['carrier_name'] = $tmp[0]['carrier_name'];
                $mapInsert['create_time'] = date('Y-m-d H:i:s');
                //先查，是否是更新操作
                $exists = $this->orm->table('tb_sys_customer_sales_order_file')->where('order_id', $v)->exists();
                if (!$exists) {
                    $this->orm->table('tb_sys_customer_sales_order_file')->insertGetId($mapInsert);
                } else {
                    unset($mapInsert['create_time']);
                    $mapInsert['update_user_name'] = $customer_id;
                    $mapInsert['update_time'] = date('Y-m-d H:i:s');
                    $this->orm->table('tb_sys_customer_sales_order_file')->where('order_id', $v)->update($mapInsert);
                }

                //更新订单的详情
                //处理商品详情line表的数据
                $lineList = $this->orm->table('tb_sys_customer_sales_order_line as l')
                    ->leftjoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
                    ->where([
                        'l.header_id' => $v,
                    ])
                    ->whereIn('o.order_status', [CustomerSalesOrderStatus::TO_BE_PAID, CustomerSalesOrderStatus::CHECK_LABEL])
                    ->selectRaw('l.*')
                    ->get()
                    ->map(function ($m) {
                        return (array)$m;
                    })
                    ->toArray();
                //数量足够直接变成bp订单
                $exists = $this->orm->table('tb_sys_customer_sales_dropship_file_details')->where('order_id', $v)->exists();

                if ($lineList && $exists) {
                    $this->getDropshipProductsInfoByCalc($lineList);
                }
            }
        }
        $ret['count'] = count($manifest_arr);
        if ($error == '') {
            $ret['msg'] = count($manifest_arr) . ' manifest files were successfully uploaded.';
        } else {
            $ret['msg'] = trim($error, ',') . ' has corresponding order(s) synchronized to the delivery system and cannot be replaced. Other files uploaded successfully.';
        }
        return $ret;
    }

    public function getFirstProductId($item_code, $customer_id)
    {
        $ret = $this->orm->table(DB_PREFIX . 'product')
            ->where(
                [
                    'sku' => $item_code,
                    'status' => 1,
                    'buyer_flag' => 1,
                ]
            )
            ->pluck('product_id');
        $ret = obj2array($ret);
        if (count($ret) == 1) {
            return $ret[0];
        }
        $this->load->model('catalog/product');

        foreach ($ret as $key => $value) {
            $dm_info = $this->model_catalog_product->getDelicacyManagementInfoByNoView($value, $customer_id);

            if ($dm_info && $dm_info['product_display']) {
                return $value;
            }
        }

        return $this->orm->table(DB_PREFIX . 'product')
            ->where(
                [
                    'sku' => $item_code,
                ]
            )
            ->orderBy('product_id', 'desc')
            ->value('product_id');


    }

    /**
     * [verifyWayFairCsvUpload description]
     * @param $data
     * @param $runId
     * @param int $importMode
     * @return array | boolean
     */
    public function verifyWayFairCsvUpload($data, $runId, $importMode)
    {
        // 包装订单数据
        $country_id = $this->customer->getCountryId();
        $orderArr = [];
        //不能通过临时表来做
        $temp_table = 'tb_sys_customer_sales_wayfair_temp';
        $now = date("Y-m-d H:i:s", time());
        // 订单模式默认普通模式,上一个是dropship 这个是wayfair
        $order_mode = HomePickUploadType::ORDER_MODE_HOMEPICK;
        if (isset($data) && count($data) > 0) {

            //已存在的订单OrderId
            $existentOrderIdArray = [];
            foreach ($data as $key => $value) {
                $flag = true;
                foreach ($value as $k => $v) {
                    if ($v != '') {
                        $flag = false;
                        break;
                    }
                }
                if ($flag) {
                    continue;
                }
                $res = $this->verifyWayFairCsvColumn($value, $key + 2);
                if ($res !== true) {
                    return $res;
                }
                //无order_id 丢失问题
                //无seller_id问题
                //查询是否建立订单order_id

                $checkResult = $this->judgeOrderIsExist(trim($data[$key]['PO Number']), $this->customer->getId(), 'tb_sys_customer_sales_order');
                if ($checkResult) {
                    $existentOrderIdArray[] = $value['PO Number'];
                }
                $warehouse_name = $this->getWarehouseCodeName($value['Warehouse Name'], PlatformMapping::WAYFAIR);
                $orderArr[] = [
                    "order_id" => trim($value['PO Number']),
                    "warehouse_code" => $value['Warehouse Name'] == '' ? 'Warehouse Name' : $value['Warehouse Name'], // 这里的warehouse_code 是 Warehouse Name
                    "warehouse_name" => $warehouse_name == '' ? 'warehouse_code' : $warehouse_name,
                    "store_name" => $value['Store Name'] == null ? 'Wayfair' : $value['Store Name'],
                    "po_date" => $value['PO Date'] == '' ? null : $value['PO Date'],
                    "must_ship_by" => $value['Must Ship By'] == '' ? null : $value['Must Ship By'],
                    "backorder_date" => $value['Backorder Date'] == '' ? null : $value['Backorder Date'],
                    "order_status" => $value['Order Status'] == '' ? null : $value['Order Status'],
                    "item_code" => $value['item_code'] == '' ? null : strtoupper($value['item_code']),
                    "item_#" => $value['Item Number'] == '' ? null : trim($value['Item Number']),
                    "item_name" => $value['Item Name'] == '' ? null : $value['Item Name'],
                    "quantity" => $value['Quantity'] == '' ? null : $value['Quantity'],
                    "wholesale_price" => $value['Wholesale Price'] == '' ? null : $value['Wholesale Price'],
                    "ship_method" => $value['Ship Method'] == '' ? null : $value['Ship Method'],
                    "carrier_name" => $value['Carrier Name'] == '' ? null : trim($value['Carrier Name']),
                    "shipping_account_number" => $value['Shipping Account Number'] == '' ? null : $value['Shipping Account Number'],
                    "ship_to_name" => $value['Ship To Name'] == '' ? null : $value['Ship To Name'],
                    "ship_to_address" => $value['Ship To Address'] == '' ? null : $value['Ship To Address'],
                    "ship_to_address2" => $value['Ship To Address 2'] == '' ? null : $value['Ship To Address 2'],
                    "ship_to_city" => $value['Ship To City'] == '' ? null : $value['Ship To City'],
                    "ship_to_state" => $value['Ship To State'] == '' ? null : $value['Ship To State'],
                    "ship_to_zip" => $value['Ship To Zip'] == '' ? null : $value['Ship To Zip'],
                    "ship_to_phone" => $value['Ship To Phone'] == '' ? null : $value['Ship To Phone'],
                    "inventory_at_po_time" => $value['Inventory at PO Time'] == '' ? null : $value['Inventory at PO Time'],
                    "inventory_send_date" => $value['Inventory Send Date'] == '' ? null : $value['Inventory Send Date'],
                    "ship_speed" => $value['Ship Speed'] == '' ? null : $value['Ship Speed'],
                    "po_date_&_time" => $value['PO Date & Time'] == '' ? null : $value['PO Date & Time'],
                    "registered_timestamp" => $value['Registered Timestamp'] == '' ? null : $value['Registered Timestamp'],
                    "customization_text" => $value['Customization Text'] == '' ? 'item_title' : $value['Customization Text'],
                    "event_name" => $value['Event Name'] == '' ? null : $value['Event Name'],
                    "event_id" => $value['Event ID'] == '' ? null : $value['Event ID'],
                    "event_start_date" => $value['Event Start Date'] == '' ? null : $value['Event Start Date'],
                    "event_end_date" => $value['Event End Date'] == '' ? null : $value['Event End Date'],
                    "event_type" => $value['Event Type'] == '' ? null : $value['Event Type'],
                    "backorder_reason" => $value['Backorder Reason'] == '' ? null : $value['Backorder Reason'],
                    "original_product_id" => $value['Original Product ID'] == '' ? null : $value['Original Product ID'],
                    "original_product_name" => $value['Original Product Name'] == '' ? null : $value['Original Product Name'],
                    "event_inventory_source" => $value['Event Inventory Source'] == '' ? null : $value['Event Inventory Source'],
                    "packing_slip_url" => $value['Packing Slip URL'] == '' ? null : $value['Packing Slip URL'],
                    "tracking_#" => $value['Tracking #'] ?? null,
                    "ready_for_pickup_date" => $value['Ready for Pickup Date'] == '' ? null : $value['Ready for Pickup Date'],
                    "sku" => $value['SKU'] == '' ? null : $value['SKU'],  //这个sku 非 product sku 没有实际意义
                    "destination_country" => $value['Destination Country'] == '' ? null : $value['Destination Country'],
                    "depot_id" => $value['Depot ID'] == '' ? null : $value['Depot ID'],
                    "depot_name" => $value['Depot Name'] == '' ? null : $value['Depot Name'],
                    "wholesale_event_source" => $value['Wholesale Event Source'] == '' ? null : $value['Wholesale Event Source'],
                    "wholesale_event_store_source" => $value['Wholesale Event Store Source'] == '' ? null : $value['Wholesale Event Store Source'],
                    "b2border" => $value['B2BOrder'] == '' ? null : $value['B2BOrder'],
                    "composite_wood_product" => $value['Composite Wood Product'] == '' ? null : $value['Composite Wood Product'],
                    //"memo"                      => ,
                    "create_id" => $this->customer->getId(),
                    "create_time" => date('Y-m-d H:i:s', time()),
                    //"update_id"                 => null,
                    //"update_time"               => null,
                    "program_code" => PROGRAM_CODE,
                    "buyer_id" => $this->customer->getId(),
                    "run_id" => $runId,
                ];


            }


        }
        if (!empty($existentOrderIdArray)) {
            //$this->updateUploadStatus($runId,$this->customer->getId());
            return 'OrderId:' . implode('、', $existentOrderIdArray) . ' is already exist ,please check the uploaded CSV file.';
        }
        //插入临时表之前 不需要合并item code
        $this->insertTempTable($orderArr, $temp_table);
        // 根据RunId 和customer_id
        $orderTempArr = $this->getTempRecordByRunid($runId, $this->customer->getId(), $temp_table);
        // 订单头表就是 订单的非商品信息表
        $customerSalesWayfairOrderArr = [];
        $yzc_order_id_number = $this->sequence->getYzcOrderIdNumber();
        foreach ($orderTempArr as $key => $value) {
            //导入订单根据order_id来进行合并
            $order_id = $value['order_id'];
            $customerSalesWayfairOrder = null;
            $wayFairOrder = $this->getWayfairOrderColumnNameConversion($value, $order_mode, $country_id, $importMode);
            if (!isset($customerSalesWayfairOrderArr[$order_id])) {
                $yzc_order_id_number++;
                // 新订单头
                //获取一个插入dropship和tb_sys_customer_sales_order的映射关系
                $wayFairOrder['yzc_order_id'] = 'YC-' . $yzc_order_id_number;
                $customerSalesWayfairOrderArr[$order_id] = $wayFairOrder;
            } else {
                //订单信息有变动需要更改
                // line_count
                // order_total
                // line_item_number
                $tmp = $wayFairOrder['product_info'][0];
                $tmp['line_item_number'] = count($customerSalesWayfairOrderArr[$order_id]['product_info']) + 1;
                $customerSalesWayfairOrderArr[$order_id]['line_count'] = count($customerSalesWayfairOrderArr[$order_id]['product_info']) + 1;
                $customerSalesWayfairOrderArr[$order_id]['order_total'] += $tmp['item_price'] * $tmp['qty'];
                $customerSalesWayfairOrderArr[$order_id]['order_total'] = sprintf('%.2f', $customerSalesWayfairOrderArr[$order_id]['order_total']);
                $customerSalesWayfairOrderArr[$order_id]['product_info'][] = $tmp;
            }

        }
        $this->sequence->updateYzcOrderIdNumber($yzc_order_id_number);
        //插入order和line表
        $this->insertCustomerSalesOrderAndLine($customerSalesWayfairOrderArr);
        return true;


    }

    public function verifyWalmartUpload($data, $runId)
    {
        // 包装订单数据
        $customerId = $this->customer->getId();
        $orderArr = [];
        //不能通过临时表来做
        $temp_table = 'tb_sys_customer_sales_walmart_temp';
        $now = date("Y-m-d H:i:s", time());
        $order_mode = HomePickUploadType::ORDER_MODE_HOMEPICK;
        if ($data) {
            //已存在的订单OrderId
            $existentOrderIdArray = [];
            foreach ($data as $key => $value) {

                $checkResult = $this->judgeOrderIsExist($value['order_id'], $customerId, 'tb_sys_customer_sales_order');
                if ($checkResult) {
                    $existentOrderIdArray[] = $value['order_id'];
                }
                if ($value['warehouse_id']) {
                    $warehouseCode = $this->getWarehouseCodeName($value['ship_node'], PlatformMapping::WALMART);
                } else {
                    $warehouseCode = '';
                }
                $orderArr[] = [
                    'buyer_id' => $customerId,
                    'run_id' => $runId,
                    'order_id' => $value['order_id'],
                    'order' => $value['order'],
                    'order_date' => $value['order_date'],
                    'ship_by' => $value['ship_by'],
                    'ship_to_name' => $value['ship_to_name'],
                    'ship_to_address' => $value['ship_to_address'],
                    'ship_to_phone' => $value['ship_to_phone'],
                    'store_id' => intval($value['store_id']),
                    'ship_to_address1' => $value['ship_to_address1'],
                    'ship_to_address2' => $value['ship_to_address2'],
                    'city' => $value['city'],
                    'state' => $value['state'],
                    'zip' => $value['zip'],
                    'flids' => $value['flids'],
                    'ship_node' => $value['ship_node'],
                    'warehouse_code' => $warehouseCode,
                    'line' => $value['line'],
                    'upc' => $value['upc'],
                    'platform_sku' => $value['platform_sku'],
                    'item_code' => strtoupper($value['b2b_sku']),
                    'status' => $value['status'],
                    'item_description' => $value['item_description'],
                    'qty' => $value['qty'],
                    'ship_to' => $value['ship_to'],
                    'shipping_method' => $value['shipping_method'],
                    'requested_carrier_method' => $value['requested_carrier_method'],
                    'carrier' => $value['carrier'],
                    'update_status' => $value['update_status'],
                    'update_qty' => $value['update_qty'],
                    'tracking_number' => $value['tracking_number'],
                    'package_asn' => $value['package_asn'],
                    'create_time' => $now,
                    'update_time' => $now
                ];
            }
        }
        if (!empty($existentOrderIdArray)) {
            $err = 'PO#:' . implode('、', $existentOrderIdArray) . ' is already exist ,please check the uploaded file.';
            return ['flag' => false, 'err' => $err];
        }
        //插入临时表之前 不需要合并item code
        $this->insertTempTable($orderArr, $temp_table);
        // 根据RunId 和customer_id
        $orderTempArr = $this->getTempRecordByRunid($runId, $customerId, $temp_table);
        // 订单头表就是 订单的非商品信息表
        $customerSalesOrderArr = [];
        $yzc_order_id_number = $this->sequence->getYzcOrderIdNumber();
        foreach ($orderTempArr as $key => $value) {
            //导入订单根据order_id来进行合并
            $order_id = $value['order_id'];
            $customerSalesOrder = null;
            $walmartOrder = $this->getWalmartOrderColumnNameConversion($value, $order_mode);
            if (!isset($customerSalesOrderArr[$order_id])) {
                $yzc_order_id_number++;
                // 新订单头
                //获取一个插入dropship和tb_sys_customer_sales_order的映射关系
                $walmartOrder['yzc_order_id'] = 'YC-' . $yzc_order_id_number;
                $customerSalesOrderArr[$order_id] = $walmartOrder;
            } else {
                //订单信息有变动需要更改
                // line_count
                // order_total
                // line_item_number
                $tmp = $walmartOrder['product_info'][0];
                $tmp['line_item_number'] = count($customerSalesOrderArr[$order_id]['product_info']) + 1;
                $customerSalesOrderArr[$order_id]['line_count'] = count($customerSalesOrderArr[$order_id]['product_info']) + 1;
                $customerSalesOrderArr[$order_id]['product_info'][] = $tmp;
            }

        }
        $this->sequence->updateYzcOrderIdNumber($yzc_order_id_number);
        //插入order和line表
        $this->insertCustomerSalesOrderAndLine($customerSalesOrderArr);
        return ['flag' => true, 'err' => ''];
    }


    //获取warehouse 的名称
    public function getWarehouseCodeName($name, $platformId)
    {
        $map = [
            ['w.customer_id', '=', $this->customer->getId()],
            ['w.platform_warehouse_name', '=', $name],
            ['w.platform_id', '=', $platformId],
            ['w.status', '=', 1],
        ];
        return $this->orm->table(DB_PREFIX . 'mapping_warehouse as w')
            ->leftJoin('tb_warehouses as tw', 'tw.WarehouseID', '=', 'w.warehouse_id')
            ->where($map)->value('tw.WarehouseCode');
    }

    /**
     * [insertCustomerSalesOrderAndLine description] 插入order表和line表
     * @param $data
     */
    public function insertCustomerSalesOrderAndLine($data)
    {
        foreach ($data as $key => $value) {
            $tmp = $data[$key]['product_info'];
            unset($data[$key]['product_info']);
            $insertId = $this->orm->table('tb_sys_customer_sales_order')->insertGetId($data[$key]);
            if (isset($value['import_mode']) && $value['import_mode'] == HomePickImportMode::US_OTHER) {
                $label_view_map = [
                    'order_id' => $insertId,
                    'create_user_name' => $value['buyer_id'],
                    'buyer_id' => $value['buyer_id'],
                    'status' => null,
                ];
                $this->orm->table('tb_sys_customer_sales_order_label_review')->insert($label_view_map);
            }
            if ($insertId) {
                foreach ($tmp as $k => $v) {
                    $tmp[$k]['header_id'] = $insertId;
                    $insertChildId = $this->orm->table('tb_sys_customer_sales_order_line')->insertGetId($tmp[$k]);
                }
            }
        }

    }

    /**
     * [uniqueDropShipTempList description] bug 因为 order_id 和 item_code 相同导致没有合并
     * @param $list
     * @return array
     */
    public function uniqueDropShipTempList($list)
    {
        $temp = [];
        foreach ($list as $key => $value) {
            $key = $value['order_id'] . '_' . $value['sku'];
            if (isset($temp[$key])) {
                $temp[$key]['item_quantity'] += (int)$value['item_quantity'];
                $temp[$key]['tracking_id'] .= '&' . trim($value['tracking_id'], '&');
            } else {
                $temp[$key] = $value;
            }
        }
        return array_values($temp);
    }

    public function uniqueCommonOrderTempList($list)
    {
        $temp = [];
        foreach ($list as $key => $value) {
            $key = $value['order_id'] . '_' . $value['item_code'];
            if (isset($temp[$key])) {
                $temp[$key]['qty'] += (int)$value['qty'];
            } else {
                $temp[$key] = $value;
            }
        }
        return array_values($temp);
    }

    /**
     * [getOrderColumnNameConversion description] 将dropship order 转化成正常order 仅针对order_mode  = 3
     * @param $data
     * @param int $order_mode
     * @param int $country_id
     * @param int $importMode
     * @return array
     */
    public function getOrderColumnNameConversion($data, $order_mode, $country_id = AMERICAN_COUNTRY_ID, $importMode = 0)
    {
        $res = [];
        if ($country_id == AMERICAN_COUNTRY_ID) {
            $order_comments = 'US Dropship Order';
        } elseif ($country_id == HomePickUploadType::BRITAIN_COUNTRY_ID) {
            $order_comments = 'UK Dropship Order';
        } else {
            $order_comments = '';
        }
        $weekDay = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        if ($order_mode == HomePickUploadType::ORDER_MODE_HOMEPICK) {
            $res['order_id'] = $data['order_id'];
            $res['order_date'] = $weekDay[(int)date('w', strtotime($data['create_time']))] . ',' . date('F j Y h:i A', strtotime($data['create_time']));
            $res['email'] = $this->customer->getEmail();
            $res['ship_name'] = $data['ship_to_name'];
            $res['ship_address1'] = trim($data['ship_to_address_line1'] . ' ' . $data['ship_to_address_line2'] . ' ' . $data['ship_to_address_line3']);
            $res['ship_city'] = $data['ship_to_city'];
            $res['ship_state'] = $data['ship_to_state'];
            $res['ship_zip_code'] = $data['ship_to_zip_code'];
            $res['ship_country'] = $data['ship_to_country'];
            $res['ship_phone'] = $data['phone_number'];
            $res['ship_method'] = $data['ship_method'];
            $res['ship_service_level'] = $data['ship_method_code'];
            //$res['ship_company']        =  $data['ship_to_name'];
            $res['bill_name'] = $res['ship_name'];
            $res['bill_address'] = $res['ship_address1'];
            $res['bill_city'] = $res['ship_city'];
            $res['bill_state'] = $res['ship_state'];
            $res['bill_zip_code'] = $res['ship_zip_code'];
            $res['bill_country'] = $res['ship_country'];
            $res['orders_from'] = 'Amazon'; //default
            $res['discount_amount'] = '0.0000';
            $res['tax_amount'] = '0.0000';
            $item_cost = (float)$this->getOrderItemPrice($data['item_cost'], $country_id);

            $res['order_total'] = sprintf('%.2f', ($item_cost * $data['item_quantity']));
            //$res['payment_method']      =  $data['ship_to_name'];
            $res['store_name'] = 'yzc';
            $res['store_id'] = 888;
            $res['buyer_id'] = $data['buyer_id'];
            $res['customer_comments'] = $order_comments;
            $res['run_id'] = $data['run_id'];
            $res['order_status'] = CustomerSalesOrderStatus::CHECK_LABEL; //checkLabel
            $res['order_mode'] = $order_mode;
            $res['create_user_name'] = $data['create_id'];
            $res['create_time'] = $data['create_time'];
            $res['program_code'] = $data['program_code'];
            $res['line_count'] = 1;
            $res['update_temp_id'] = $data['id'];
            $res['import_mode'] = $importMode;
            $res['product_info'][0] = [
                'temp_id' => $data['id'],
                'line_item_number' => 1,
                'product_name' => $data['item_title'],
                'qty' => $data['item_quantity'],
                'item_price' => sprintf('%.2f', $item_cost),
                //'item_unit_discount'    => $data['id'],
                'item_tax' => 0,
                'item_code' => trim($data['sku']),
                'alt_item_id' => $data['asin'],
                'run_id' => $data['run_id'],
                'ship_amount' => 0,
                'line_comments' => $order_comments,
                'image_id' => 1,
                //'seller_id'             => $data['id'],
                'item_status' => 1,
                'create_user_name' => $data['create_id'],
                'create_time' => $data['create_time'],
                'program_code' => $data['program_code'],
            ];


        }
        return $res;

    }

    public function getWayFairOrderColumnNameConversion($data, $order_mode, $country_id = AMERICAN_COUNTRY_ID, $importMode = 0)
    {
        $res = [];
        $weekDay = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        if ($order_mode == HomePickUploadType::ORDER_MODE_HOMEPICK) {
            // $itemPrice
            if (intval($data['quantity']) == 0) {
                $itemPrice = floatval($data['wholesale_price']);
            } else {
                $itemPrice = floatval($data['wholesale_price']) / intval($data['quantity']);
            }
            $res['order_id'] = $data['order_id'];
            $res['order_date'] = $weekDay[(int)date('w', strtotime($data['create_time']))] . ',' . date('F j Y h:i A', strtotime($data['create_time']));
            $res['email'] = $this->customer->getEmail();
            $res['ship_name'] = $data['ship_to_name'];
            $res['ship_address1'] = trim($data['ship_to_address'] . ' ' . $data['ship_to_address2']);
            $res['ship_city'] = $data['ship_to_city'];
            $res['ship_state'] = $data['ship_to_state'];
            $res['ship_zip_code'] = $data['ship_to_zip'];
            $res['ship_country'] = $data['destination_country']; // 针对于ship country 判断是否需要商业发票
            $res['ship_phone'] = $data['ship_to_phone'];
            $res['ship_method'] = $data['ship_method'];
            $res['ship_service_level'] = $data['carrier_name'];
            //$res['ship_company']        =  $data['ship_to_name'];
            $res['bill_name'] = $res['ship_name'];
            $res['bill_address'] = $res['ship_address1'];
            $res['bill_city'] = $res['ship_city'];
            $res['bill_state'] = $res['ship_state'];
            $res['bill_zip_code'] = $res['ship_zip_code'];
            $res['bill_country'] = $res['ship_country'];
            $res['orders_from'] = $data['store_name']; //default
            $res['discount_amount'] = '0.0000';
            $res['tax_amount'] = '0.0000';
            $res['order_total'] = sprintf('%.2f', $data['wholesale_price']);
            //$res['payment_method']      =  $data['ship_to_name'];
            $res['store_name'] = $data['store_name'];
            $res['store_id'] = 888;
            $res['buyer_id'] = $data['buyer_id'];
            $res['customer_comments'] = 'Wayfair ' . $data['carrier_name'];
            $res['run_id'] = $data['run_id'];
            $res['order_status'] = CustomerSalesOrderStatus::CHECK_LABEL; //checkLabel
            $res['order_mode'] = $order_mode;
            $res['create_user_name'] = $data['create_id'];
            $res['create_time'] = $data['create_time'];
            $res['program_code'] = $data['program_code'];
            $res['line_count'] = 1;
            $res['update_temp_id'] = $data['id'];
            $res['import_mode'] = $importMode;
            $res['product_info'][0] = [
                'temp_id' => $data['id'],
                'line_item_number' => 1,
                'product_name' => $data['item_name'] == null ? 'product name' : $data['item_name'],
                'qty' => $data['quantity'],
                'item_price' => sprintf('%.2f', $itemPrice),
                'item_tax' => 0,
                'item_code' => $data['item_code'],
                'alt_item_id' => $data['item_code'],
                'run_id' => $data['run_id'],
                'ship_amount' => 0,
                'line_comments' => 'Wayfair ' . $data['carrier_name'],
                'image_id' => 1,
                'item_status' => 1,
                'create_user_name' => $data['create_id'],
                'create_time' => $data['create_time'],
                'program_code' => $data['program_code'],
            ];


        }
        return $res;

    }

    public function getWalmartOrderColumnNameConversion($data, $order_mode)
    {
        $res = [];
        $weekDay = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        $res['order_id'] = $data['order_id'];
        $res['order_date'] = $weekDay[(int)date('w', strtotime($data['create_time']))] . ',' . date('F j Y h:i A', strtotime($data['create_time']));
        $res['email'] = $this->customer->getEmail();
        $res['ship_name'] = $data['ship_to_name'];
        $res['ship_address1'] = trim($data['ship_to_address1']) . ' ' . $data['ship_to_address2'];
        $res['ship_address2'] = null;
        $res['ship_city'] = $data['city'];
        $res['ship_state'] = $data['state'];
        $res['ship_zip_code'] = $data['zip'];
        $res['ship_country'] = 'US'; //walmart 暂时只支持美国
        $res['ship_phone'] = $data['ship_to_phone'];
        $res['ship_method'] = $data['shipping_method'];
        $res['ship_service_level'] = empty($data['carrier']) ? $data['requested_carrier_method'] : $data['carrier'];
        $res['bill_name'] = $data['ship_to_name'];
        $res['bill_address'] = $data['ship_to_address'];
        $res['bill_city'] = $data['city'];
        $res['bill_state'] = $data['state'];
        $res['bill_zip_code'] = $data['zip'];
        //$res['bill_country']        =  $res['ship_country'];
        $res['orders_from'] = 'Walmart'; //default
        $res['discount_amount'] = '0.0000';
        $res['tax_amount'] = '0.0000';
        //$res['order_total']         =  sprintf('%.2f',$data['wholesale_price']);
        //$res['payment_method']      =  $data['ship_to_name'];
        $res['store_name'] = 'walmart';
        $res['store_id'] = 888;
        $res['buyer_id'] = $data['buyer_id'];
        $res['customer_comments'] = 'walmart ';
        $res['run_id'] = $data['run_id'];
        $res['order_status'] = CustomerSalesOrderStatus::CHECK_LABEL; //checkLabel
        $res['order_mode'] = $order_mode;
        $res['create_user_name'] = $data['buyer_id'];
        $res['create_time'] = $data['create_time'];

        $res['program_code'] = 'V1.0';
        $res['line_count'] = 1;//默认1，后面会更新
        $res['update_temp_id'] = $data['id'];
        $res['import_mode'] = HomePickImportMode::IMPORT_MODE_WALMART;//walmart
        $res['product_info'][0] = [
            'temp_id' => $data['id'],
            'line_item_number' => 1,
            'product_name' => $data['item_description'],
            'qty' => $data['qty'],
            'item_tax' => 0,
            'item_code' => $data['item_code'],
            'product_id' => $this->getValidProductIdBySku($data['item_code']),
            'run_id' => $data['run_id'],
            'ship_amount' => 0,
            'line_comments' => 'walmart',
            'image_id' => 1,
            'item_status' => 1,
            'create_time' => $data['create_time'],
            'create_user_name' => $data['buyer_id'],
            'program_code' => PROGRAM_CODE,
        ];

        return $res;

    }

    public function getCommonOrderColumnNameConversion($data, $order_mode, $country_id = AMERICAN_COUNTRY_ID)
    {
        $res = [];
        if ($country_id == AMERICAN_COUNTRY_ID) {
            $order_comments = 'US Dropship Order';
        } elseif ($country_id == HomePickUploadType::BRITAIN_COUNTRY_ID) {
            $order_comments = 'UK Dropship Order';
        } else {
            $order_comments = '';
        }
        $weekDay = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        if ($order_mode == 6) {
            $res['order_id'] = $data['order_id'];
            $res['order_date'] = $weekDay[(int)date('w', strtotime($data['create_time']))] . ',' . date('F j Y h:i A', strtotime($data['create_time']));
            $res['email'] = $this->customer->getEmail();
            $res['ship_name'] = $data['ship_to_name'];
            $res['ship_address1'] = trim($data['ship_to_address_line1'] . ' ' . $data['ship_to_address_line2'] . ' ' . $data['ship_to_address_line3']);
            $res['ship_city'] = $data['ship_to_city'];
            $res['ship_state'] = $data['ship_to_state'];
            $res['ship_zip_code'] = $data['ship_to_zip_code'];
            $res['ship_country'] = $data['ship_to_country'];
            $res['ship_phone'] = $data['phone_number'];
            $res['ship_method'] = $data['ship_method'];
            $res['ship_service_level'] = $data['ship_method_code'];
            //$res['ship_company']        =  $data['ship_to_name'];
            $res['bill_name'] = $res['ship_name'];
            $res['bill_address'] = $res['ship_address1'];
            $res['bill_city'] = $res['ship_city'];
            $res['bill_state'] = $res['ship_state'];
            $res['bill_zip_code'] = $res['ship_zip_code'];
            $res['bill_country'] = $res['ship_country'];
            $res['orders_from'] = 'Amazon'; //default
            $res['discount_amount'] = '0.0000';
            $res['tax_amount'] = '0.0000';
            $res['order_total'] = sprintf('%.2f', (float)$this->getOrderItemPrice($data['item_cost'], $country_id) * $data['item_quantity']);
            //$res['payment_method']      =  $data['ship_to_name'];
            $res['store_name'] = 'yzc';
            $res['store_id'] = 888;
            $res['buyer_id'] = $data['buyer_id'];
            $res['customer_comments'] = $order_comments;
            $res['run_id'] = $data['run_id'];
            $res['order_status'] = CustomerSalesOrderStatus::CHECK_LABEL; //checkLabel
            $res['order_mode'] = $order_mode;
            $res['create_user_name'] = $data['create_id'];
            $res['create_time'] = $data['create_time'];
            $res['program_code'] = $data['program_code'];
            $res['line_count'] = 1;
            $res['update_temp_id'] = $data['id'];
            $res['product_info'][0] = [
                'temp_id' => $data['id'],
                'line_item_number' => 1,
                'product_name' => $data['item_title'],
                'qty' => $data['item_quantity'],
                'item_price' => sprintf('%.2f', $this->getOrderItemPrice($data['item_cost'], $country_id)),
                //'item_unit_discount'    => $data['id'],
                'item_tax' => 0,
                'item_code' => trim($data['sku']),
                'alt_item_id' => $data['asin'],
                'run_id' => $data['run_id'],
                'ship_amount' => 0,
                'line_comments' => $order_comments,
                'image_id' => 1,
                //'seller_id'             => $data['id'],
                'item_status' => 1,
                'create_user_name' => $data['create_id'],
                'create_time' => $data['create_time'],
                'program_code' => $data['program_code'],
            ];


        }
        return $res;

    }

    /**
     * [getDropshipTempRecordByRunid description] 获取插入后的dropship的内容
     * @param $run_id
     * @param int $customer_id
     * @return array
     */
    public function getDropshipTempRecordByRunid($run_id, $customer_id)
    {
        $map = [
            ['run_id', '=', $run_id],
            ['buyer_id', '=', $customer_id],
        ];
        $res = $this->orm->table('tb_sys_customer_sales_dropship_temp')->where($map)->get();
        $res = obj2array($res);
        return $res;


    }

    public function getTempRecordByRunid($run_id, $customer_id, $table)
    {
        $map = [
            ['run_id', '=', $run_id],
            ['buyer_id', '=', $customer_id],
        ];
        $res = $this->orm->table($table)->where($map)->get();
        $res = obj2array($res);
        return $res;


    }

    public function getCommonOrderTempRecordByRunid($run_id, $customer_id)
    {
        $map = [
            ['run_id', '=', $run_id],
            ['buyer_id', '=', $customer_id],
        ];
        $res = $this->orm->table('tb_sys_customer_sales_order_temp')->where($map)->get();
        $res = obj2array($res);
        return $res;


    }

    /**
     * [insertDropshipTempTable description] csv 插入临时表数据
     * @param array $orderArr
     * @return bool
     */
    public function insertDropshipTempTable($orderArr)
    {
        return $this->orm->table('tb_sys_customer_sales_dropship_temp')->insert($orderArr);
    }

    /**
     * [insertTempTable description]
     * @param array $orderArr
     * @param string $table
     * @return bool
     */
    public function insertTempTable($orderArr, $table)
    {
        return $this->orm->table($table)->insert($orderArr);
    }

    public function insertCommonOrderTable($orderArr)
    {
        return $this->orm->table('tb_sys_customer_sales_order_temp')->insert($orderArr);
    }

    /**
     * [judgeDropshipOrderIsExist description] 查询是否建立临时订单dropship
     * @param string $order_id
     * @param int $buyer_id
     * @return array
     */
    public function judgeDropshipOrderIsExist($order_id, $buyer_id)
    {
        $map['order_id'] = $order_id;
        $map['buyer_id'] = $buyer_id;
        return $this->orm->table('tb_sys_customer_sales_order')->where($map)->value('id');
    }

    public function judgeOrderIsExist($order_id, $buyer_id, $table)
    {
        $map['order_id'] = $order_id;
        $map['buyer_id'] = $buyer_id;
        return $this->orm->table($table)->where($map)->value('id');
    }

    /**
     * [verifyCommonOrderCsvColumn description] 检验dropship下的 common order
     * @param $data
     * @param $index
     * @param int $country_id
     * @return bool|string
     */
    public function verifyCommonOrderCsvColumn($data, $index, $country_id)
    {
        if (strlen($data['SalesPlatform']) > 20) {
            //$this->model_account_customer_order_import->updateUploadStatus($runId);
            return 'Line' . $index . ",SalesPlatform must be between 0 and 20 characters!";

        }
        //#7239只能包含字母、数字、下划线(_)和连字符(-)
        if ($data['OrderId'] == '' || strlen($data['OrderId']) > 40 || !preg_match('/^[_0-9-a-zA-Z]{1,40}$/i', trim($data['OrderId']))) {
            //$this->model_account_customer_order_import->updateUploadStatus($runId);
            return 'Line' . $index . ",OrderId must be between 1 and 40 characters long and must only contain letters, numbers, - or _.";

        }
        if ($data['LineItemNumber'] == '' || strlen($data['LineItemNumber']) > 50) {
            //$this->model_account_customer_order_import->updateUploadStatus($runId);
            return 'Line' . $index . ",LineItemNumber must be between 1 and 50 characters!";

        }
        if (strlen($data['OrderDate']) > 25) {
            //$this->model_account_customer_order_import->updateUploadStatus($runId);
            return 'Line' . $index . ",OrderDate must be between 0 and 25 characters!";

        }
        $reg = '/^((?:19|20)\d\d)\/([1-9]|1[012])\/([1-9]|[12][0-9]|3[01])$/';
        $reg2 = '/^((?:19|20)\d\d)-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01])T(0[1-9]|1[0-9]|2[0-4]):([0-6][0-9]):([0-6][0-9])-(0[1-9]|1[0-9]|2[0-4]):([0-6][0-9])$/';
        if (strlen($data['BuyerBrand']) > 30) {
            //$this->model_account_customer_order_import->updateUploadStatus($runId);
            return 'Line' . $index . ",BuyerBrand must be between 0 and 30 characters!";

        }
        if (strlen($data['BuyerPlatformSku']) > 25) {
            //13584 需求，Buyer导入订单时校验ItemCode的录入itemCode去掉首尾的空格，
            //如果ITEMCODE不是由字母和数字组成的，那么提示BuyerItemCode有问题，这个文件不能导入
            //整个上传格式会发生变化
            //$this->model_account_customer_order_import->updateUploadStatus($runId);
            return 'Line' . $index . ",BuyerPlatformSku must be between 0 and 25 characters!";

        }
        if ($data['B2BItemCode'] == '' || strlen($data['B2BItemCode']) > 30) {
            //$this->model_account_customer_order_import->updateUploadStatus($runId);
            return 'Line' . $index . ",B2BItemCode must be between 1 and 30 characters!";

        }
        if (strlen($data['BuyerSkuDescription']) > 100) {
            //$this->model_account_customer_order_import->updateUploadStatus($runId);
            return 'Line' . $index . ",BuyerSkuDescription must be between 0 and 100 characters!";

        }
        $reg3 = '/^[0-9]+(.[0-9]{1,3})?$/';
        if ($data['BuyerSkuCommercialValue'] != '') {
            if (!preg_match($reg3, $data['BuyerSkuCommercialValue'])) {
                //$this->model_account_customer_order_import->updateUploadStatus($runId);
                return 'Line' . $index . ",BuyerSkuCommercialValue format error,Please see the instructions.";

            }
        }
        if (strlen($data['BuyerSkuLink']) > 50) {
            //$this->model_account_customer_order_import->updateUploadStatus($runId);
            return 'Line' . $index . ",BuyerSkuLink must be between 0 and 50 characters!";

        }
        //注意：产品数量正整数
        if ($data['ShipToQty'] == '' || !preg_match('/^[1-9][0-9]*$/', $data['ShipToQty'])) {
            //$this->model_account_customer_order_import->updateUploadStatus($runId);
            return 'Line' . $index . ",ShipToQty format error,Please see the instructions.";

        }
        //if(!empty($data['ShipToService']) && strcasecmp(trim($data['ShipToService']),'ASR') != 0){
        //    //$this->model_account_customer_order_import->updateUploadStatus($runId);
        //    return 'Line'.$index.",ShipToService format error,Please see the instructions.";
        //
        //}
        if ($country_id != AMERICAN_COUNTRY_ID) {
            $data['ShipToService'] = '';
        }
        if (strlen($data['ShipToServiceLevel']) > 50) {
            //$this->model_account_customer_order_import->updateUploadStatus($runId);
            return 'Line' . $index . ",ShipToServiceLevel must be between 0 and 50 characters!";

        }
        if (strlen($data['ShipToAttachmentUrl']) > 800) {
            //$this->model_account_customer_order_import->updateUploadStatus($runId);
            return 'Line' . $index . ",ShipToAttachmentUrl must be between 0 and 800 characters!";

        }
        if ($data['ShipToName'] == '' || strlen($data['ShipToName']) > 40) {
            //$this->model_account_customer_order_import->updateUploadStatus($runId);
            return 'Line' . $index . ",ShipToName must be between 1 and 40 characters!";

        }

        if ($data['ShipToEmail'] == '' || strlen($data['ShipToEmail']) > 90) {
            //$this->model_account_customer_order_import->updateUploadStatus($runId);
            return 'Line' . $index . ",ShipToEmail must be between 1 and 90 characters!";

        }

        if ($data['ShipToPhone'] == '' || strlen($data['ShipToPhone']) > 45) {
            //$this->model_account_customer_order_import->updateUploadStatus($runId);
            return 'Line' . $index . ",ShipToPhone must be between 1 and 45 characters!";

        }

        if ($data['ShipToPostalCode'] == '' || strlen($data['ShipToPostalCode']) > 18) {
            //$this->model_account_customer_order_import->updateUploadStatus($runId);
            return 'Line' . $index . ",ShipToPostalCode must be between 1 and 18 characters!";

        }

        if ($country_id == AMERICAN_COUNTRY_ID) {
            $characters = 56;
        } else {
            $characters = 80;
        }
        if (($data['ShipToAddressDetail'] == '') || (StringHelper::stringCharactersLen(trim($data['ShipToAddressDetail'])) > $this->config->get('config_b2b_address_len'))) {
            return 'Line' . $index . ",ShipToAddressDetail must be between 1 and " . $this->config->get('config_b2b_address_len') . " characters!";
        }

        if ($data['ShipToCity'] == '' || strlen($data['ShipToCity']) > 30) {
            //$this->model_account_customer_order_import->updateUploadStatus($runId);
            return 'Line' . $index . ",ShipToCity must be between 1 and 30 characters!";

        }

        //13195OrderFulfillment订单导入模板调优
        //日本需要对ShippedDate 这个字段
        if ($country_id == JAPAN_COUNTRY_ID) {
            //验证日本的ShippedDate
            $time_period = JapanSalesOrder::getShipDateList();
            $shippedDate = trim($data['ShippedDate']);
            //验证是否相等
            if ($shippedDate != '') {
                $period_time = substr($shippedDate, -12);
                if (in_array($period_time, $time_period)) {
                    $ship_time = substr($shippedDate, 0, strpos($shippedDate, 'T'));
                    $timestamp = strtotime($ship_time);
                    if ($timestamp == false) {
                        //$this->model_account_customer_order_import->updateUploadStatus($runId);
                        return 'Line' . $index . ",ShippedDate format error,Please see the instructions.";

                    }

                } else {
                    // $this->model_account_customer_order_import->updateUploadStatus($runId);
                    return 'Line' . $index . ",ShippedDate format error,Please see the instructions.";

                }
            }
        }
        $country = session('country');
        if ($country == 'USA') {
            if ($data['ShipToState'] == '' || strlen($data['ShipToState']) > 30) {
                //$this->model_account_customer_order_import->updateUploadStatus($runId);
                return 'Line' . $index . ",ShipToState must be between 1 and 30 characters!";

            } else {
                // add by lilei 13161
                //$groupName = $this->customer->getCustomerGroupName($this->customer->getId());
                //if(!in_array($groupName,WILLCALL_DROPSHIP)) {
                //    $shipToState = $data['ShipToState'] == null ? '' : strtoupper(trim($data['ShipToState']));
                //    $stateArray = ['PR', 'AK', 'HI', 'GU', 'AA', 'AE', 'AP', 'ALASKA', 'ARMED FORCES AMERICAS', 'ARMED FORCES EUROPE', 'ARMED FORCES PACIFIC', 'GUAM', 'HAWAII', 'PUERTO RICO'];
                //    if (in_array($shipToState, $stateArray)) {
                //        //$this->model_account_customer_order_import->updateUploadStatus($runId);
                //        return 'Line' . $index . ",ShipToState in PR, AK, HI, GU, AA, AE, AP doesn't support delivery,Please see the instructions.";
                //
                //    }
                //}
                // end 13161
            }
        } else {
            if (strlen($data['ShipToState']) > 30) {
                //$this->model_account_customer_order_import->updateUploadStatus($runId);
                return 'Line' . $index . ",ShipToState must be between 0 and 30 characters!";

            }
        }
        if ($country == 'USA') {
            if (strtoupper($data['ShipToCountry']) != 'US') {
                //$this->model_account_customer_order_import->updateUploadStatus($runId);
                return 'Line' . $index . ",ShipToCountry format error,Please see the instructions.";

            }
        } else if ($country == 'GBR') {
            if (strtoupper($data['ShipToCountry']) != 'UK') {
                //$this->model_account_customer_order_import->updateUploadStatus($runId);
                return 'Line' . $index . ",ShipToCountry format error,Please see the instructions.";

            }
        } else if ($country == 'DEU') {
            if (strtoupper($data['ShipToCountry']) != 'DE') {
                //$this->model_account_customer_order_import->updateUploadStatus($runId);
                return 'Line' . $index . ",ShipToCountry format error,Please see the instructions.";

            }
        } else if ($country == 'JPN') {
            if (strtoupper($data['ShipToCountry']) != 'JP') {
                //$this->model_account_customer_order_import->updateUploadStatus($runId);
                return 'Line' . $index . ",ShipToCountry format error,Please see the instructions.";

            }
        }
        if (strlen($data['OrderComments']) > 1500) {
            //$this->model_account_customer_order_import->updateUploadStatus($runId);
            return 'Line' . $index . ",OrderCommentsmust be between 0 and 1500 characters!";

        }

        return true;

    }

    public function verifyEuropeWayFairCsvColumn($data, $index, $country_id = AMERICAN_COUNTRY_ID)
    {
        //SalesPlatform 无 Amazon 默认导入
        //Warehouse Name 预处理已经验证 无需处理
        //﻿ PO Number Order ID  #7239只能包含字母、数字、下划线(_)和连字符(-)
        if ($data['PO Number'] == '' || strlen($data['PO Number']) > 28 || !preg_match('/^[_0-9-a-zA-Z]{1,28}$/i', trim($data['PO Number']))) {
            return 'Line [' . $index . '],[PO Number] must be between 1 and 28 characters long and must only contain letters, numbers, - or _.';
        }
        // Item # item code  预处理有映射 无需验证
        //LineItemNumber 无 同一个订单号对应的多条明细自动从1往后编号
        //OrderDate  无 订单上传成功的时间，格式同模板中的要求
        //BuyerPlatformSku B2BItemCode 同SKU
        //ShipToQty Quantity
        if (trim($data['Quantity']) == '') {
            return 'Line [' . $index . "],[Quantity] can not be left blank.";
        }
        if (!preg_match('/^[0-9]*$/', $data['Quantity']) || trim($data['Quantity']) == 0) {
            return 'Line [' . $index . "],[Quantity] format error.";
        }
        // Ship Method
        if ($data['Ship Method'] == '') {
            return 'Line [' . $index . '],[Ship Method] can not be left blank.';
        }
        // ShipToService Carrier Name
        if ($data['Carrier Name'] == '' || strlen($data['Carrier Name']) > 100) {
            return 'Line [' . $index . '],[Carrier Name] must be between 1 and 100 characters.';
        }

        //ShipToName  Ship To Name
        if ($data['Ship To Name'] == '' || strlen($data['Ship To Name']) > 90) {
            return 'Line [' . $index . '],[Ship To Name] must be between 1 and 90 characters.';
        }
        //ShipToEmail 无 默认为buyer邮箱
        // ShipToPhone Phone Number
        //BuyerSkuCommercialValue  Wholesale Price

        //#6084上门取货WayFair模板ship to address为空上传提示需明确
        if (trim($data['Ship To Address']) == '') {
            return 'Line [' . $index . '],[Ship To Address] can not be left blank.';
        }

        $address = $data['Ship To Address'] . ' ' . $data['Ship To Address 2'];
        if (trim($address) == '' || strlen($address) > 180) {
            return 'Line [' . $index . '],[Ship To Address] must be between 1 and 180 characters.';
        }

        // Ship To City
        if ($data['Ship To City'] == '' || strlen($data['Ship To City']) > 40) {
            return 'Line [' . $index . '],[Ship To City] must be between 1 and 40 characters.';
        }

        // Ship To State 不限制不配送的洲区域
        //if ($data['Ship To State'] == '' || strlen($data['Ship To State']) > 40) {
        //    return 'Line [' . $index . '],[Ship To State] must be between 1 and 40 characters.';
        //}

        // shipToPostalCode Ship To Zip
        if ($data['Ship To Zip'] == '' || strlen($data['Ship To Zip']) > 18) {
            return 'Line [' . $index . '],[Ship To ZIP] must be between 1 and 18 characters.';
        }

        // ShipToPhone Ship To Phone
        if ($data['Ship To Phone'] == '' || strlen($data['Ship To Phone']) > 45) {
            return 'Line [' . $index . '],[Ship To Phone] must be between 1 and 45 characters.';
        }

        // Ship To Country Destination Country
        if ($data['Destination Country'] == '') {
            return 'Line [' . $index . '],[Destination Country] can not be left blank.';
        }

        // Ready for Pickup Date 验证
        if ($data['Ready for Pickup Date'] == '') {
            return 'Line [' . $index . '],[Ready for Pickup Date] can not be left blank.';
        } else {
            if (!$this->isDateValid(trim($data['Ready for Pickup Date']))) {
                return 'Line [' . $index . '], The format of [Ready for Pickup Date] must be MM/dd/yyyy.';
            }
        }
        return true;


    }

    public function isDateValid($date, $formats = ['m/d/Y'])
    {
        $unixTime = strtotime($date);
        if (!$unixTime) { //无法用strtotime转换，说明日期格式非法
            return false;
        }

        //校验日期合法性，只要满足其中一个格式就可以
        foreach ($formats as $format) {
            if (date($format, $unixTime) == $date) {
                return true;
            }
        }

        return false;
    }


    public function verifyWayFairCsvColumn($data, $index, $country_id = AMERICAN_COUNTRY_ID)
    {
        //SalesPlatform 无 Amazon 默认导入
        //Warehouse Name 预处理已经验证 无需处理
        //﻿ PO Number Order ID  注：#7239只能包含字母、数字、下划线(_)和连字符(-)
        if ($data['PO Number'] == '' || strlen($data['PO Number']) > 28 || !preg_match('/^[_0-9-a-zA-Z]{1,28}$/i', trim($data['PO Number']))) {
            return 'Line' . $index . ',PO Number must be between 1 and 28 characters long and must only contain letters, numbers, - or _.';
        }
        // Item # item code  预处理有映射 无需验证
        //LineItemNumber 无 同一个订单号对应的多条明细自动从1往后编号
        //OrderDate  无 订单上传成功的时间，格式同模板中的要求
        //BuyerPlatformSku B2BItemCode 同SKU

        //ShipToQty Quantity
        //注意：产品数量正整数
        if ($data['Quantity'] == '' || !preg_match('/^[1-9][0-9]*$/', $data['Quantity'])) {
            return 'Line' . $index . ",Quantity format error.";
        }

        // ShipToService Carrier Name
        if ($data['Carrier Name'] == '' || strlen($data['Carrier Name']) > 100) {
            return 'Line' . $index . ',Carrier Name must be between 1 and 100 characters.';
        }

        $ship_method_code = $data['Carrier Name'];
        $count = 0;
        foreach (WAYFAIR_VERIFY_TYPES as $ks => $vs) {
            if (stripos($ship_method_code, $vs) !== false) {
                if ($ks != 1) {
                    $count++;
                }
            }
        }
        if ($count != 1) {
            return 'Line' . $index . ',Carrier Name format error.';
        }

        //ShipToName  Ship To Name
        if ($data['Ship To Name'] == '' || strlen($data['Ship To Name']) > 90) {
            return 'Line' . $index . ',Ship To Name must be between 1 and 90 characters.';
        }
        //ShipToEmail 无 默认为buyer邮箱
        // ShipToPhone Phone Number
        //BuyerSkuCommercialValue  Wholesale Price

        //#6084上门取货WayFair模板ship to address为空上传提示需明确
        if (trim($data['Ship To Address']) == '') {
            return 'Line' . $index . ',Ship To Address can not be left blank.';
        }

        $address = $data['Ship To Address'] . ' ' . $data['Ship To Address 2'];
        $len = $this->config->get('config_b2b_address_len');
        if (trim($address) == '' || strlen($address) > $len) {
            return 'Line' . $index . ",Ship To Address must be between 1 and {$len} characters.";
        }

        // Ship To City
        if ($data['Ship To City'] == '' || strlen($data['Ship To City']) > 40) {
            return 'Line' . $index . ',Ship To City must be between 1 and 40 characters.';
        }

        // Ship To State 不限制不配送的洲区域
        if ($data['Ship To State'] == '' || strlen($data['Ship To State']) > 40) {
            return 'Line' . $index . ',Ship To State must be between 1 and 40 characters.';
        }

        // shipToPostalCode Ship To Zip
        if ($data['Ship To Zip'] == '' || strlen($data['Ship To Zip']) > 18) {
            return 'Line' . $index . ',Ship To ZIP must be between 1 and 18 characters.';
        }

        // ShipToPhone Ship To Phone
        if ($data['Ship To Phone'] == '' || strlen($data['Ship To Phone']) > 45) {
            return 'Line' . $index . ',Ship To Phone must be between 1 and 45 characters.';
        }

        //Destination Country
        if ($data['Destination Country'] == '') {
            return 'Line' . $index . ',Destination Country can not be left blank.';
        }

        //Ship To Country Destination Country
        //if($data['Destination Country'] != 'US' && $data['Destination Country'] != 'us'){
        //    return  'Line' .$index. ',Destination Country format error.';
        //}
        return true;


    }

    /**
     * [dealErrorCode description] 根据
     * @param $str
     * @return array|bool
     */
    public function dealErrorCode($str)
    {
        $ret = [];
        $string_ret = '';
        $error_code = ['?', '？'];
        $length = mb_strlen($str);
        for ($i = 0; $i < $length; $i++) {
            if (in_array(mb_substr($str, $i, 1), $error_code)) {
                $ret[] = $i;
                $string_ret .= '<span style="color: red">' . mb_substr($str, $i, 1) . '</span>';
            } else {
                $string_ret .= mb_substr($str, $i, 1);
            }
        }
        if (count($ret)) {
            return $string_ret;
        } else {
            return false;
        }

    }


    public function getCommonOrderStatus($order_id, $run_id)
    {
        $ret = $this->orm->table('tb_sys_customer_sales_order as o')
            ->leftJoin('tb_sys_dictionary as d', 'd.Dickey', '=', 'o.order_status')
            ->where([
                'o.order_id' => $order_id,
                'o.run_id' => $run_id,
                'd.DicCategory' => 'CUSTOMER_ORDER_STATUS',
            ])
            ->select('o.order_status', 'd.DicValue')
            ->get()
            ->map(
                function ($value) {
                    return (array)$value;
                })
            ->toArray();
        return $ret;
    }


    /**
     * [verifyDropshipCsvColumn description] 根据国家进行不同的判断
     * @param $data
     * @param $index
     * @return bool|string
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function verifyDropshipCsvColumn($data, $index)
    {
        //SalesPlatform 无 Amazon 默认导入
        $country_id = $this->customer->getCountryId();
        //﻿Order ID   #7239只能包含字母、数字、下划线(_)和连字符(-)
        if ($data['Order ID'] == '' || strlen($data['Order ID']) > 28 || !preg_match('/^[_0-9-a-zA-Z]{1,28}$/i', trim($data['Order ID']))) {
            return 'Line' . $index . ',Order ID must be between 1 and 28 characters long and must only contain letters, numbers, - or _.';
        }
        //BuyerSkuCommercialValue  Item Cost
        if ($country_id == AMERICAN_COUNTRY_ID) {

            //LineItemNumber 无 同一个订单号对应的多条明细自动从1往后编号
            //OrderDate  无 订单上传成功的时间，格式同模板中的要求
            //BuyerPlatformSku B2BItemCode 同SKU
            if ($data['SKU'] == '' || strlen($data['SKU']) > 64) {//之前是18改为64
                return 'Line' . $index . ',SKU must be between 1 and 64 characters.';
            }
            if ($data['Item Cost'] != '') {
                $reg = '/^[0-9]+(.[0-9]{1,3})?$/';
                //形式为 $2,377.50
                $cost = $this->getOrderItemPrice($data['Item Cost'], $country_id);
                if (!preg_match($reg, $cost)) {
                    return 'Line' . $index . ',Item Cost format error.';
                }
            }
            //Ship To Country
            if ($data['Ship To Country'] != 'US' && $data['Ship To Country'] != 'us') {
                return 'Line' . $index . ',Ship To Country format error.';
            }
        } elseif ($country_id == HomePickUploadType::BRITAIN_COUNTRY_ID) {
            if ($data['SKU'] == '' || strlen($data['SKU']) > 64) {//之前是18改为64
                return 'Line' . $index . ',SKU must be between 1 and 64 characters.'; //英国以前限制100字符，暂时不合并代码 ;
            }
            if ($data['Item Cost'] != '') {
                $reg = '/^[0-9]+(.[0-9]{1,3})?$/';
                //形式为 £2,377.50
                //$cost = trim(str_ireplace(',', '', substr($data['Item Cost'], strpos($data['Item Cost'], '£') + 1))); 有问题,英镑符号比较特殊
                $cost = $this->getOrderItemPrice($data['Item Cost'], $country_id);
                if (!preg_match($reg, $cost)) {
                    return 'Line' . $index . ',Item Cost format error.';
                }
            }
            //Ship To Country
            if ($data['Ship To Country'] != 'GB' && $data['Ship To Country'] != 'gb') {
                return 'Line' . $index . ',Ship To Country format error.';
            }
        }

        //英国和美国 模板合并后，抽取出通用的

        // shipToPostalCode Ship To ZIP Code
        if ($data['Ship To ZIP Code'] == '' || strlen($data['Ship To ZIP Code']) > 18) {
            return 'Line' . $index . ',Ship To ZIP Code format error.';
        }

        // Ship To State 不限制不配送的洲区域
        if ($data['Ship To State'] == '' || strlen($data['Ship To State']) > 40) {
            return 'Line' . $index . ',Ship To State format error.';
        }


        //13846 英国和美国不同
        if (($data['Ship Method Code'] == '' && $data['Ship Method'] == '') || strlen($data['Order ID']) > 28) {

            return 'Line' . $index . ',Ship Method Code format error.';

        } else {
            //14310 线上增加AFB和CEVA提货方式
            $ship_method = $data['Ship Method'];
            $ship_method_code = $data['Ship Method Code'];
            $count = 0;
            foreach (LOGISTICS_VERIFY_TYPES as $ks => $vs) {
                if (stripos($ship_method_code, $vs) !== false || stripos($ship_method, $vs) !== false) {
                    $count++;
                }
            }
            //美国dropship 增加ship method code
            if ($count != 1)
                return 'Line' . $index . ',Ship Method Code format error.';
        }
        //ShipToQty Item Quantity
        //注意：产品数量正整数
        if ($data['Item Quantity'] == '' || !preg_match('/^[1-9][0-9]*$/', $data['Item Quantity'])) {
            return 'Line' . $index . ",Item Quantity format error.";
        }
        // ShipToService Ship Method Code
        if ($data['Ship Method Code'] == '' || strlen($data['Ship Method Code']) > 100) {
            return 'Line' . $index . ',Ship Method Code format error.';
        }
        //ShipToName  Ship To Name
        if ($data['Ship To Name'] == '' || strlen($data['Ship To Name']) > 90) {
            return 'Line' . $index . ',Ship To Name format error.';
        }
        //ShipToEmail 无 默认为buyer邮箱
        // ShipToPhone Phone Number
        if ($data['Phone Number'] == '' || strlen($data['Phone Number']) > 45) {
            return 'Line' . $index . ',Phone Number format error.';
        }
        //  ShipToAddressDetail  Ship To Address
        $address = $data['Ship To Address Line 1'] . ' ' . $data['Ship To Address Line 2'] . ' ' . $data['Ship To Address Line 3'];
        $len = $this->config->get('config_b2b_address_len');
        if (trim($address) == '' || strlen($address) > $len) {
            return 'Line' . $index . ',Ship To Address Line format error.';
        }
        // Ship To City
        if ($data['Ship To City'] == '' || strlen($data['Ship To City']) > 40) {
            return 'Line' . $index . ',Ship To City format error.';
        }

        //OrderComments 无
        //验证tracking number
        if ($data['Tracking ID'] == '') {
            return 'Line' . $index . ',Tracking ID format error.';
        } else {
            $list = explode('&', $data['Tracking ID']);
            if ((int)$data['Item Quantity'] == count($list)) {
                foreach ($list as $key => $value) {
                    if ($country_id == HomePickUploadType::BRITAIN_COUNTRY_ID) {
                        //英国不需要验证正则
                        $verify_flag = true;
                    } else {
                        $verify_flag = preg_match('/^[a-zA-Z0-9]*$/', $value);
                    }

                    if ($verify_flag) {
                        //验证
                        $map = [
                            ['f.status', '=', 1],
                            ['f.create_user_name', '=', Customer()->getId()],
                            ['o.order_status', '!=', CustomerSalesOrderStatus::CANCELED],
                            ['o.create_time', '>', Carbon::now()->subMonth(6)],
                        ];
                        $res = db('tb_sys_customer_sales_dropship_file_details as f')
                            ->where($map)
                            ->leftJoin('tb_sys_customer_sales_order as o', 'f.order_id', '=', 'o.id')
                            ->select('f.tracking_number')
                            ->get()
                            ->pluck('tracking_number')
                            ->toArray();
                        //这里应该验证该用户dropship 的 tracking_id 是不是重复 非取消的订单
                        $mapTemp = [
                            ['o.create_user_name', '=', Customer()->getId()],
                            ['o.create_time', '>', Carbon::now()->subMonth(6)],
                            ['t.create_id', '=', Customer()->getId()],
                            ['o.order_status', '!=', CustomerSalesOrderStatus::CANCELED],
                        ];
                        // 不等于cancel的订单
                        $tempRes = db('tb_sys_customer_sales_dropship_temp as t')
                            ->leftJoin('tb_sys_customer_sales_order as o', 'o.order_id', 't.order_id')
                            ->where($mapTemp)
                            ->select('t.tracking_id')
                            ->get()
                            ->pluck('tracking_id')
                            ->toArray();
                        //两者合并
                        $all_res = array_filter(
                            array_unique(
                                explode('&',
                                    strtolower(implode('&', array_merge($res, $tempRes)
                                        )
                                    )
                                )
                            )
                        );

                        if (in_array(strtolower($value), $all_res)) {
                            $order_id = $this->getPurchaseOrderIdByTrackingNumber($value);
                            return 'Line' . $index . ',Tracking ID：tracking number ' . $value . ' is repeat to the tracking number in order【' . $order_id . '】.';
                        } else {
                            $tracking_number_key = [];
                            //加入缓存 验证
                            if (null != $this->cache->get($this->customer->getId() . '_' . 'dropship_csv')) {
                                $tracking_number_list = $this->cache->get($this->customer->getId() . '_' . 'dropship_csv');
                                if (in_array(strtolower($value), $tracking_number_list)) {
                                    $ks = array_search($value, $tracking_number_list);
                                    $tracking_number_key = $this->cache->get($this->customer->getId() . '_' . 'dropship_csv_key');
                                    $pre_ks = $tracking_number_key[$ks];
                                    return 'Line' . $pre_ks . ',Tracking ID：tracking number ' . $value . ' is repeat to the tracking number in Line' . $index . '.';
                                }
                                $tracking_number_list[] = strtolower($value);
                                $this->cache->set($this->customer->getId() . '_' . 'dropship_csv', $tracking_number_list);
                                $tracking_number_key[] = $index;
                                $this->cache->set($this->customer->getId() . '_' . 'dropship_csv_key', $tracking_number_key);
                            } else {
                                $tracking_number_list[] = strtolower($value);
                                $this->cache->set($this->customer->getId() . '_' . 'dropship_csv', $tracking_number_list);
                                $tracking_number_key[] = $index;
                                $this->cache->set($this->customer->getId() . '_' . 'dropship_csv_key', $tracking_number_key);
                            }
                        }
                    } else {
                        return 'Line' . $index . ',Tracking ID format error.';
                    }
                }
            } else {
                return 'Line' . $index . ',Tracking ID：the quantity of tracking numbers is incorrect.';
            }
        }
        return true;

    }

    /**
     * @param $tracking_number
     * @return mixed|null
     */
    public function getPurchaseOrderIdByTrackingNumber($tracking_number)
    {
        //[紧急]_美国dropship 图片位置百分比&&trackingNumber 获取order_id
        $mapRes = [
            ['t.create_id', '=', customer()->getId()],
            ['o.create_user_name', '=', customer()->getId()],
            ['o.order_status', '!=', CustomerSalesOrderStatus::CANCELED],
            ['o.create_time', '>', Carbon::now()->subMonth(6)],
        ];
        $order_id = $this->orm->table('tb_sys_customer_sales_dropship_temp as t')
            ->where($mapRes)
            ->where(function ($query) use ($tracking_number) {
                $common_v = $tracking_number . '&';
                $query->where('tracking_id', 'like', "%{$common_v}")->orWhere('tracking_id', 'like', "%{$tracking_number}");
            })
            ->leftJoin('tb_sys_customer_sales_order as o', 'o.order_id', 't.order_id')
            ->limit(1)
            ->value('o.order_id');

        if ($order_id) {
            return $order_id;
        }

        $map = [
            ['f.status', '=', 1],
            ['f.create_user_name', '=', customer()->getId()],
            ['f.tracking_number', 'like', "{$tracking_number}"],
            ['o.order_status', '!=', CustomerSalesOrderStatus::CANCELED],
            ['o.create_time', '>', Carbon::now()->subMonth(6)],
        ];
        $order_id = $this->orm->table('tb_sys_customer_sales_dropship_file_details as f')
            ->where($map)
            ->leftJoin('tb_sys_customer_sales_order as o', 'f.order_id', '=', 'o.id')
            ->value('o.order_id');
        return $order_id;

    }

    public function getFileInfoByOrderId($id, $country_id)
    {
        $salesOrderInfo = CustomerSalesOrder::find($id);
        $import_mode = $salesOrderInfo->import_mode;
        $upload_file_info = [];
        if ($import_mode == HomePickImportMode::IMPORT_MODE_WAYFAIR) {
            if (in_array($country_id, EUROPE_COUNTRY_ID)) {
                $upload_file_info = $this->getEuropeWayfairSingleUploadFileInfo($id);
            } else {
                $upload_file_info = $this->getWayfairSingleUploadFileInfo($id);
            }
        } elseif ($import_mode == HomePickImportMode::IMPORT_MODE_AMAZON) {
            $upload_file_info = $this->getDropshipSingleUploadFileInfo($id);
        } elseif ($import_mode == HomePickImportMode::US_OTHER) {
            if ($country_id == AMERICAN_COUNTRY_ID) { //US上门取货common(也称other)导单上传文件详情
                $map = [
                    ['id', '=', $id],
                ];
                $upload_file_info = $this->getUsPickUpOtherUploadFileInfo($map, 1);
            }
        } else {
            //WALMART 获取walmart的上传文件详情
            $upload_file_info = $this->getWalmartSingleUploadFileInfo($id);
        }
        $ret['upload_file_info'] = $upload_file_info;
        $ret['import_mode'] = $import_mode;
        $ret['ship_country'] = $salesOrderInfo->ship_country;
        return $ret;
    }

    public function verifyHasManifestFile($order_id)
    {
        return $this->orm->table('tb_sys_customer_sales_order_file as f')
            ->where('order_id', $order_id)
            ->exists();
    }

    public function getEuropeWayfairSingleUploadFileInfo($order_id)
    {
        $this->session->remove('europe_wayfair_container_id_list');
        $this->session->remove('europe_wayfair_label_details');
        $europe_wayfair_container_id_list = [];
        $europe_wayfair_label_details = [];
        $map = [
            ['o.id', '=', $order_id],
        ];
        $res = $this->orm->table('tb_sys_customer_sales_order as o')
            ->leftJoin('tb_sys_customer_sales_order_file as f', 'o.id', '=', 'f.order_id')
            ->where($map)
            ->select(
                'o.id',
                'o.order_id',
                'o.order_status',
                'o.ship_service_level',
                'o.bol_path',
                'f.file_name',
                'f.file_path',
                'f.deal_file_path',
                'o.ship_method',
                'o.ship_service_level'
            )
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        if ($res) {
            foreach ($res as $key => $value) {
                $res[$key]['tracking_number_method'] = $this->getEuropeTrackingNumberMethod($value['ship_service_level']);
                $mapChild = [
                    ['l.header_id', '=', $value['id']],
                ];
                $childList = $this->orm->table('tb_sys_customer_sales_order_line as l')->
                leftJoin('tb_sys_customer_sales_wayfair_temp as t', 't.id', '=', 'l.temp_id')->
                leftJoin(DB_PREFIX . 'product as pc', 'pc.sku', '=', 'l.item_code')->
                leftJoin(DB_PREFIX . 'weight_class_description as wcd', 'pc.weight_class_id', '=', 'wcd.weight_class_id')
                    ->where($mapChild)
                    ->select('l.id as line_id', 'l.item_code', 'l.qty', 'l.line_item_number', 't.ship_method',
                        't.carrier_name as ship_method_code', 't.id as temp_id', 't.tracking_# as tracking_id', 'wcd.unit as unit_name'
                    )
                    ->selectRaw('round(pc.weight,2) as weight')->groupBy('l.id')
                    ->get()
                    ->map(function ($v) {
                        return (array)$v;
                    })
                    ->toArray();
                //根据sku 和 数量 处理成相对应的格式
                if (!$childList) {
                    unset($res[$key]);
                } else {
                    //根据sku 和 数量 处理成相对应的格式
                    $childrenList = $this->getSingleEuropeWayfairChildListComboInfo($childList, $value['id'], $europe_wayfair_container_id_list, $europe_wayfair_label_details);
                    $res[$key]['total_file_amount'] = $childrenList['total_file_amount'];
                    unset($childrenList['total_file_amount']);
                    $res[$key]['childrenList'] = $childrenList;
                }
            }
        }
        return $res;

    }


    /**
     * [getWayfairSingleUploadFileInfo description] 获取wayfair 订单上传label的详情
     * @param int $order_id
     * @return array
     * date:2019/9/25 11:55
     */
    public function getWayfairSingleUploadFileInfo($order_id)
    {
        $this->session->remove('wayfair_container_id_list');
        $this->session->remove('wayfair_bol');
        session()->remove('wayfair_tracking_info');
        $wayfair_container_id_list = [];
        $wayfair_bol = [];
        $map = [
            ['id', '=', $order_id],
        ];
        $res = $this->orm->table('tb_sys_customer_sales_order')->where($map)->select('id', 'order_id', 'order_status', 'ship_service_level', 'bol_path')->get();
        $res = obj2array($res);
        if ($res) {
            foreach ($res as $key => $value) {
                //根据 ship_service_level 确定是否需要bol
                $res[$key]['bol_flag'] = $this->verifyWayfairShipMethodCode($value['ship_service_level']);
                $res[$key]['bol_file_name'] = $this->orm->table('tb_sys_customer_sales_dropship_upload_file as f')->where('deal_file_path', $value['bol_path'])->value('file_name');
                if ($res[$key]['bol_flag']) {
                    $wayfair_bol[] = $value['id'] . '_bol';
                }
                $mapChild = [
                    ['l.header_id', '=', $value['id']],
                ];
                $childList = $this->orm->table('tb_sys_customer_sales_order_line as l')->
                leftJoin('tb_sys_customer_sales_wayfair_temp as t', 't.id', '=', 'l.temp_id')->
                leftJoin(DB_PREFIX . 'product as pc', 'pc.sku', '=', 'l.item_code')->
                leftJoin(DB_PREFIX . 'weight_class_description as wcd', 'pc.weight_class_id', '=', 'wcd.weight_class_id')
                    ->where($mapChild)->
                    select('l.id as line_id', 'l.item_code', 'l.qty', 'l.line_item_number', 't.ship_method', 't.carrier_name as ship_method_code', 't.id as temp_id', 't.tracking_# as tracking_id', 'wcd.unit as unit_name')
                    ->selectRaw('round(pc.weight,2) as weight')->groupBy('l.id')->get();
                //根据sku 和 数量 处理成相对应的格式
                $childList = obj2array($childList);
                if (!$childList) {
                    unset($res[$key]);
                } else {
                    //根据sku 和 数量 处理成相对应的格式
                    $childrenList = $this->getSingleWayfairChildListComboInfo($childList, $value['id'], $wayfair_container_id_list);
                    $res[$key]['total_file_amount'] = $childrenList['total_file_amount'];
                    unset($childrenList['total_file_amount']);
                    $res[$key]['childrenList'] = $childrenList;

                }

            }
            $this->session->set('wayfair_bol', $wayfair_bol);

        }
        return $res;

    }

    /**
     * [getDropshipSingleUploadFileInfo description] 获取订单所在的上传的文件
     * @param int $order_id
     * @return array
     */
    public function getDropshipSingleUploadFileInfo($order_id)
    {
        $this->session->remove('container_id_list');
        $container_id_list = [];
        $map = [
            ['id', '=', $order_id],
        ];
        $res = $this->orm->table('tb_sys_customer_sales_order')->where($map)->select('id', 'order_id', 'order_status', 'bol_path')->get();
        $res = obj2array($res);
        if ($res) {
            foreach ($res as $key => $value) {
                $mapChild = [
                    ['l.header_id', '=', $value['id']],
                ];
                $childList = $this->orm->table('tb_sys_customer_sales_order_line as l')
                    ->leftJoin('tb_sys_customer_sales_dropship_temp as t', 't.id', '=', 'l.temp_id')
                    ->leftJoin(DB_PREFIX . 'product as pc', 'pc.sku', '=', 'l.item_code')
                    ->leftJoin(DB_PREFIX . 'weight_class_description as wcd', 'pc.weight_class_id', '=', 'wcd.weight_class_id')
                    ->where($mapChild)
                    ->select('l.id as line_id', 'l.item_code', 'l.qty', 'l.line_item_number', 't.asin', 't.ship_method', 't.ship_method_code', 't.id as temp_id', 't.tracking_id', 'wcd.unit as unit_name')
                    ->selectRaw('round(pc.weight,2) as weight')->groupBy('l.id')->get();
                //根据sku 和 数量 处理成相对应的格式
                $childList = obj2array($childList);
                if (!$childList) {
                    unset($res[$key]);
                } else {
                    //根据sku 和 数量 处理成相对应的格式
                    $childrenList = $this->getSingleDropshipChildListComboInfo($childList, $value['id'], $container_id_list);
                    $res[$key]['total_file_amount'] = $childrenList['total_file_amount'];
                    unset($childrenList['total_file_amount']);
                    $res[$key]['childrenList'] = $childrenList;
                }

            }
        }

        return $res;


    }

    /**
     * [getDropshipUploadFileInfo description] 获取dropship 业务下的track number combo以及对应关系
     * @param $run_id
     * @param int $customer_id
     * @return array
     */
    public function getDropshipUploadFileInfo($run_id, $customer_id)
    {

        $this->session->remove('container_id_list');
        $container_id_list = [];
        $map = [
            ['run_id', '=', $run_id],
            ['buyer_id', '=', $customer_id],
        ];
        $res = $this->orm->table('tb_sys_customer_sales_order')->where($map)->select('id', 'order_id', 'order_status')->get();
        $res = obj2array($res);
        if ($res) {
            foreach ($res as $key => $value) {
                $mapChild = [
                    ['l.header_id', '=', $value['id']],
                ];
                $childList = $this->orm->table('tb_sys_customer_sales_order_line as l')
                    ->leftJoin('tb_sys_customer_sales_dropship_temp as t', 't.id', '=', 'l.temp_id')
                    ->leftJoin(DB_PREFIX . 'product as pc', 'pc.sku', '=', 'l.item_code')
                    ->leftJoin(DB_PREFIX . 'weight_class_description as wcd', 'pc.weight_class_id', '=', 'wcd.weight_class_id')
                    ->where($mapChild)
                    ->select('l.id as line_id', 'l.item_code', 'l.qty', 'l.line_item_number', 't.asin', 't.ship_method', 't.ship_method_code', 't.id as temp_id', 't.tracking_id', 'wcd.unit as unit_name')
                    ->selectRaw('round(pc.weight,2) as weight')
                    ->groupBy('l.id')
                    ->get();
                //根据sku 和 数量 处理成相对应的格式
                $childList = obj2array($childList);
                if (!$childList) {
                    unset($res[$key]);
                } else {
                    //根据sku 和 数量 处理成相对应的格式
                    $childrenList = $this->getDropshipChildListComboInfo($childList, $value['id'], $container_id_list);
                    $res[$key]['total_file_amount'] = $childrenList['total_file_amount'];
                    unset($childrenList['total_file_amount']);
                    $res[$key]['childrenList'] = $childrenList;

                }


            }

        }
        return $res;


    }

    /**
     * [verifyWayfairShipMethodCode description]
     * @param $ship_method_code
     * @return int
     */
    public function verifyWayfairShipMethodCode($ship_method_code)
    {
        $LTLTypeItems = HomePickCarrierType::getWayfairLTLTypeViewItems();
        foreach ($LTLTypeItems as $ks => $vs) {
            if (stripos($ship_method_code, $vs) !== false) {
                return 1;
            }
        }
        return 0;

    }


    public function verifyWalmartShipMethodCode($ship_method_code)
    {
        $LTLTypeItems = HomePickCarrierType::getWalmartLTLTypeViewItems();
        foreach ($LTLTypeItems as $ks => $vs) {
            if (strtolower($ship_method_code) == strtolower($vs)) {
                return 1;
            }
        }
        return 0;

    }

    /**
     * [getWalmartTrackingNumberMethod description] 验证tracking 是手填还是自动输入
     * @param $carrier
     * @return int
     */
    public function getWalmartTrackingNumberMethod($carrier)
    {
        foreach (WALMART_FILL_TRACKING_TYPES as $ks => $vs) {
            if (strtolower($carrier) == strtolower($vs)) {
                return 0;
            }
        }
        return 1;
    }

    public function getEuropeTrackingNumberMethod($carrier)
    {
        foreach (WAYFAIR_EUROPE_FILL_IN_TYPES as $ks => $vs) {
            if (strtolower($carrier) == strtolower($vs)) {
                return 0;
            }
        }
        return 1;
    }

    public function getWalmartSpecialTrackingMethod($carrier)
    {
        foreach (WALMART_SPECIAL_TRACKING_TYPES as $ks => $vs) {
            if (strtolower($carrier) == strtolower($vs)) {
                return 1;
            }
        }
        return 0;
    }


    /**
     * [getWayfairUploadFileInfo description] 获取wayfair 订单上传label的详情
     * @param $run_id
     * @param int $customer_id
     * @return array
     */
    public function getWayfairUploadFileInfo($run_id, $customer_id)
    {
        $this->session->remove('wayfair_container_id_list');
        $this->session->remove('wayfair_bol');
        $wayfair_container_id_list = [];
        $wayfair_bol = [];
        $map = [
            ['run_id', '=', $run_id],
            ['buyer_id', '=', $customer_id],
        ];
        $res = $this->orm->table('tb_sys_customer_sales_order')->where($map)->select('id', 'order_id', 'order_status', 'ship_service_level')->get();
        $res = obj2array($res);
        if ($res) {
            foreach ($res as $key => $value) {
                //根据 ship_service_level 确定是否需要bol
                $res[$key]['bol_flag'] = $this->verifyWayfairShipMethodCode($value['ship_service_level']);
                if ($res[$key]['bol_flag']) {
                    $wayfair_bol[] = $value['id'] . '_bol';
                }
                $mapChild = [
                    ['l.header_id', '=', $value['id']],
                ];
                $childList = $this->orm->table('tb_sys_customer_sales_order_line as l')->
                leftJoin('tb_sys_customer_sales_wayfair_temp as t', 't.id', '=', 'l.temp_id')->
                leftJoin(DB_PREFIX . 'product as pc', 'pc.sku', '=', 'l.item_code')->
                leftJoin(DB_PREFIX . 'weight_class_description as wcd', 'pc.weight_class_id', '=', 'wcd.weight_class_id')
                    ->where($mapChild)->
                    select('l.id as line_id', 'l.item_code', 'l.qty', 'l.line_item_number', 't.ship_method', 't.carrier_name as ship_method_code', 't.id as temp_id', 't.tracking_# as tracking_id', 'wcd.unit as unit_name')
                    ->selectRaw('round(pc.weight,2) as weight')->groupBy('l.id')->get();
                //根据sku 和 数量 处理成相对应的格式
                $childList = obj2array($childList);
                if (!$childList) {
                    unset($res[$key]);
                } else {
                    //根据sku 和 数量 处理成相对应的格式
                    $childrenList = $this->getWayfairChildListComboInfo($childList, $value['id'], $wayfair_container_id_list);
                    $res[$key]['total_file_amount'] = $childrenList['total_file_amount'];
                    unset($childrenList['total_file_amount']);
                    $res[$key]['childrenList'] = $childrenList;

                }

            }
            $this->session->set('wayfair_bol', $wayfair_bol);

        }
        return $res;

    }


    public function getEuropeWayfairUploadFileInfo($run_id, $customer_id)
    {
        $this->session->remove('europe_wayfair_container_id_list');
        $europe_wayfair_container_id_list = [];
        $map = [
            ['run_id', '=', $run_id],
            ['buyer_id', '=', $customer_id],
        ];
        $res = $this->orm->table('tb_sys_customer_sales_order as o')
            ->leftJoin('tb_sys_customer_sales_order_file as f', 'o.id', '=', 'f.order_id')
            ->where($map)
            ->select(
                'o.id',
                'o.order_id',
                'o.order_status',
                'o.ship_country',
                'o.ship_service_level',
                'o.bol_path',
                'f.file_name',
                'f.file_path',
                'f.deal_file_path',
                'o.ship_service_level'
            )
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        if ($res) {
            foreach ($res as $key => $value) {
                $res[$key]['tracking_number_method'] = $this->getEuropeTrackingNumberMethod($value['ship_service_level']);
                // 判断商业发票
                $res[$key]['home_pick_commercial_invoice'] = YesNoEnum::NO;
                if (
                    customer()->getCountryId() == HomePickUploadType::GERMANY_COUNTRY_ID
                    && in_array(strtoupper($value['ship_country']), $this->config->get('home_pick_commercial_invoice'))
                ) {
                    $res[$key]['home_pick_commercial_invoice'] = YesNoEnum::YES;
                }
                $mapChild = [
                    ['l.header_id', '=', $value['id']],
                ];
                $childList = $this->orm->table('tb_sys_customer_sales_order_line as l')
                    ->leftJoin('tb_sys_customer_sales_wayfair_temp as t', 't.id', '=', 'l.temp_id')
                    ->leftJoin(DB_PREFIX . 'product as pc', 'pc.sku', '=', 'l.item_code')
                    ->leftJoin(DB_PREFIX . 'weight_class_description as wcd', 'pc.weight_class_id', '=', 'wcd.weight_class_id')
                    ->where($mapChild)
                    ->select('l.id as line_id', 'l.item_code', 'l.qty', 'l.line_item_number', 't.ship_method',
                        't.carrier_name as ship_method_code', 't.id as temp_id', 't.tracking_# as tracking_id', 'wcd.unit as unit_name'
                    )
                    ->selectRaw('round(pc.weight,2) as weight')->groupBy('l.id')
                    ->get()
                    ->map(function ($v) {
                        return (array)$v;
                    })
                    ->toArray();
                //根据sku 和 数量 处理成相对应的格式
                if (!$childList) {
                    unset($res[$key]);
                } else {
                    //根据sku 和 数量 处理成相对应的格式
                    $childrenList = $this->getEuropeWayfairChildListComboInfo($childList, $value['id'], $europe_wayfair_container_id_list);
                    $res[$key]['total_file_amount'] = $childrenList['total_file_amount'];
                    unset($childrenList['total_file_amount']);
                    $res[$key]['childrenList'] = $childrenList;

                }

            }

        }
        return $res;

    }


    public function getWalmartUploadFileInfo($run_id, $buyer_id)
    {
        $this->session->remove('walmart_container_id_list');
        $this->session->remove('validation_rule');
        $this->session->remove('walmart_bol');
        $walmart_container_id_list = [];
        $validation_rule = [];
        $walmart_bol = [];
        $map = [
            ['run_id', '=', $run_id],
            ['buyer_id', '=', $buyer_id],
        ];
        $res = $this->orm->table('tb_sys_customer_sales_order')
            ->where($map)
            ->select('id', 'order_id', 'order_status', 'ship_service_level')
            ->get()
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();

        // 与wayfair 相比相同的地方
        // temp 表 ship to store 需要传store label
        // 不校验tracking的裁剪方式

        foreach ($res as $key => $value) {
            //根据 ship_service_level 确定是否需要bol
            $res[$key]['bol_flag'] = $this->verifyWalmartShipMethodCode($value['ship_service_level']);
            if ($res[$key]['bol_flag']) {
                $walmart_bol[] = $value['id'] . '_bol';
            }
            $mapChild = [
                ['l.header_id', '=', $value['id']],
            ];
            $childList = $this->orm->table('tb_sys_customer_sales_order_line as l')
                ->leftJoin('tb_sys_customer_sales_walmart_temp as t', 't.id', '=', 'l.temp_id')
                ->leftJoin(DB_PREFIX . 'product as p', 'p.sku', 'l.item_code')
                ->leftJoin(DB_PREFIX . 'weight_class_description as wcd', 'p.weight_class_id', '=', 'wcd.weight_class_id')
                ->where($mapChild)
                ->select('l.id as line_id', 'l.item_code', 'l.qty', 'l.line_item_number', 't.id as temp_id',
                    't.tracking_number', 't.package_asn', 't.store_id', 'wcd.unit as unit_name', 't.ship_to')
                ->selectRaw('round(p.weight,2) as weight')
                ->selectRaw('if(t.carrier <> "", t.carrier, t.requested_carrier_method) as carrier')
                ->groupBy('l.id')
                ->get()
                ->map(function ($vs) {
                    return (array)$vs;
                })
                ->toArray();
            $res[$key]['tracking_number_method'] = $this->getWalmartTrackingNumberMethod($childList[0]['carrier']);
            //is_special 可以跳过所有的验证
            $res[$key]['is_special'] = $this->getWalmartSpecialTrackingMethod($childList[0]['carrier']);
            //$res[$key]['is_special'] = array_rand([0,1]);
            //$res[$key]['tracking_number_method'] = 0;
            $validation_rule[$value['id']]['is_special'] = $res[$key]['is_special'];
            $validation_rule[$value['id']]['tracking_number_method'] = $res[$key]['tracking_number_method'];
            //根据sku 和 数量 处理成相对应的格式
            if (!$childList) {
                unset($res[$key]);
            } else {
                //根据sku 和 数量 处理成相对应的格式
                $childrenList = $this->getWalmartChildListComboInfo($childList, $value['id'], $res[$key]['bol_flag'], $walmart_container_id_list);
                $res[$key]['total_file_amount'] = $childrenList['total_file_amount'];
                unset($childrenList['total_file_amount']);
                $res[$key]['childrenList'] = $childrenList;

            }
            $this->session->set('walmart_bol', $walmart_bol);
            $this->session->set('validation_rule', $validation_rule);
        }

        return $res;
    }


    /**
     * [getWalmartSingleUploadFileInfo description]
     * @param int $order_id
     * @return array
     */
    public function getWalmartSingleUploadFileInfo($order_id)
    {
        $this->session->remove('walmart_container_id_list');
        $this->session->remove('walmart_bol');
        $this->session->remove('walmart_store_label');
        $this->session->remove('walmart_label_details');
        $walmart_container_id_list = [];
        $walmart_bol = [];
        $walmart_label_details = [];
        $map = [
            ['id', '=', $order_id],
        ];
        $res = $this->orm->table('tb_sys_customer_sales_order')
            ->where($map)
            ->select('id', 'order_id', 'order_status', 'ship_service_level', 'bol_path')
            ->get()
            ->map(function ($value) {
                return (array)$value;
            })
            ->toArray();

        // 与wayfair 相比相同的地方
        // temp 表 ship to store 需要传store label
        // 不校验tracking的裁剪方式
        if ($res) {
            foreach ($res as $key => $value) {
                //根据 ship_service_level 确定是否需要bol
                $res[$key]['bol_flag'] = $this->verifyWalmartShipMethodCode($value['ship_service_level']);
                $res[$key]['bol_file_name'] = $this->orm->table('tb_sys_customer_sales_dropship_upload_file as f')->where('deal_file_path', $value['bol_path'])->value('file_name');
                if ($res[$key]['bol_flag']) {
                    $walmart_bol[] = $value['id'] . '_bol';
                    $walmart_label_details['bol'][$value['id'] . '_bol'] = $res[$key]['bol_file_name'];
                }
                $mapChild = [
                    ['l.header_id', '=', $value['id']],
                ];
                $childList = $this->orm->table('tb_sys_customer_sales_order_line as l')
                    ->leftJoin('tb_sys_customer_sales_walmart_temp as t', 't.id', '=', 'l.temp_id')
                    ->leftJoin(DB_PREFIX . 'product as p', 'p.sku', 'l.item_code')
                    ->leftJoin(DB_PREFIX . 'weight_class_description as wcd', 'p.weight_class_id', '=', 'wcd.weight_class_id')
                    ->where($mapChild)
                    ->select('l.id as line_id', 'l.item_code', 'l.qty', 'l.line_item_number', 't.id as temp_id',
                        't.tracking_number', 't.package_asn', 't.store_id', 'wcd.unit as unit_name', 't.ship_to')
                    ->selectRaw('round(p.weight,2) as weight')
                    ->selectRaw('if(t.carrier <> "", t.carrier, t.requested_carrier_method) as carrier')
                    ->groupBy('l.id')
                    ->get()
                    ->map(function ($vs) {
                        return (array)$vs;
                    })
                    ->toArray();
                $res[$key]['tracking_number_method'] = $this->getWalmartTrackingNumberMethod($childList[0]['carrier']);
                //is_special 可以跳过所有的验证
                $res[$key]['is_special'] = $this->getWalmartSpecialTrackingMethod($childList[0]['carrier']);
                //$res[$key]['is_special'] = 0;
                //$res[$key]['tracking_number_method'] = 0;
                //根据sku 和 数量 处理成相对应的格式
                if (!$childList) {
                    unset($res[$key]);
                } else {
                    //根据sku 和 数量 处理成相对应的格式
                    $childrenList = $this->getSingleWalmartChildListComboInfo($childList, $value['id'], $res[$key]['bol_flag'], $walmart_container_id_list, $walmart_label_details);
                    $res[$key]['total_file_amount'] = $childrenList['total_file_amount'];
                    unset($childrenList['total_file_amount']);
                    $res[$key]['childrenList'] = $childrenList;
                }
            }
            $this->session->set('walmart_bol', $walmart_bol);
            $this->session->set('walmart_label_details', $walmart_label_details);
        }
        return $res;
    }


    public function getSingleWalmartChildListComboInfo($data, $order_id, $bol_flag, &$walmart_container_id_list, &$walmart_label_details)
    {
        $count = 0;
        if ($bol_flag) {
            $count++;
        }
        $container_arr = [];
        $container_store_label = [];
        foreach ($data as $key => $value) {
            // 验证是否是s2s
            if ($value['ship_to'] == 'Store') {
                //此处需要上传store_label
                $count++;
                $data[$key]['store_label_container_id'] = $order_id . '_' . $key . '_' . $value['temp_id'];
                $container_store_label[] = $order_id . '_' . $key . '_' . $value['temp_id'];

            }
            $comboInfo = $this->getComboInfoBySku($value['item_code']);
            if (!$comboInfo) {
                //非combo
                //验证 line id
                $data[$key]['is_combo'] = 0;
                for ($i = 0; $i < (int)$value['qty']; $i++) {

                    //$tracking_id_list = explode('&',$value['tracking_id']);
                    $mapLineInfo = [
                        ['line_id', '=', $value['line_id']],
                        ['status', '=', 1],
                        ['line_item_number', '=', $i + 1],
                    ];
                    $line_file_info = $this->orm->table('tb_sys_customer_sales_dropship_file_details')->where($mapLineInfo)->first();
                    $line_file_info = obj2array($line_file_info);

                    $count++;
                    $data[$key]['combo_info'][$i]['key'] = $i + 1;
                    $data[$key]['combo_info'][$i]['container_id'] = $order_id . '_' . $key . '_' . $value['temp_id'] . '_' . ($i + 1);
                    $container_arr[] = $data[$key]['combo_info'][$i]['container_id'];
                    if ($line_file_info) {
                        $data[$key]['combo_info'][$i]['tracking_id'] = $line_file_info['tracking_number'];
                        $data[$key]['combo_info'][$i]['file_name'] = $line_file_info['file_name'];
                        $data[$key]['combo_info'][$i]['file_path'] = $line_file_info['file_path'];
                        $data[$key]['combo_info'][$i]['deal_file_name'] = $line_file_info['deal_file_name'];
                        $data[$key]['combo_info'][$i]['deal_file_path'] = $line_file_info['deal_file_path'];
                        $data[$key]['combo_info'][$i]['store_id_img'] = $line_file_info['store_id_img'];
                        $data[$key]['combo_info'][$i]['package_asn_img'] = $line_file_info['package_asn_img'];
                        $data[$key]['combo_info'][$i]['store_deal_file_path'] = $line_file_info['store_deal_file_path'];
                        $data[$key]['combo_info'][$i]['store_deal_file_name'] = $line_file_info['store_deal_file_name'];
                        $data[$key]['combo_info'][$i]['file_id'] = $line_file_info['id'];
                        $data[$key]['combo_info'][$i]['tracking_number_img'] = $line_file_info['tracking_number_img'];
                        $data[$key]['combo_info'][$i]['order_id_img'] = $line_file_info['order_id_img'];
                        $data[$key]['combo_info'][$i]['store_order_id_img'] = $line_file_info['store_order_id_img'];
                        $data[$key]['combo_info'][$i]['weight_img'] = $line_file_info['weight_img'];
                        if (isset($data[$key]['store_label_container_id'])) {
                            $walmart_label_details['store_label'][$data[$key]['store_label_container_id']] = $line_file_info['store_deal_file_name'];
                        }

                        $walmart_label_details['common_label'][$data[$key]['combo_info'][$i]['container_id']] = $line_file_info['file_name'];
                        $walmart_label_details['tracking_number'][$data[$key]['combo_info'][$i]['container_id']] = $line_file_info['tracking_number'];

                    } else {

                        $data[$key]['combo_info'][$i]['tracking_id'] = null;

                    }

                }
            } else {
                $combo_count = array_sum(array_column($comboInfo, 'qty'));
                $data[$key]['is_combo'] = 1;
                $count_all = 0;
                foreach ($comboInfo as $k => $v) {
                    $data[$key]['combo_info'][$k] = $v;
                    $data[$key]['combo_info'][$k]['qty'] = $value['qty'] * $v['qty'];
                    $data[$key]['combo_info'][$k]['default_qty'] = $v['qty'];
                    $data[$key]['combo_info'][$k]['key'] = $k + 1;
                    $data[$key]['combo_count'] = $combo_count;
                    //$combo_key = $k + 1; // combo 所在第几个
                    for ($i = 0; $i < (int)$value['qty'] * $v['qty']; $i++) {
                        $combo_key = intval(floor($count_all / $value['qty'])) + 1;
                        $count_all++;
                        $mapLineInfo = [
                            ['line_id', '=', $value['line_id']],
                            ['status', '=', 1],
                            ['combo_sort', '=', $k + 1],
                            ['line_item_number', '=', $i + 1],
                        ];
                        $line_file_info = $this->orm->table('tb_sys_customer_sales_dropship_file_details')->where($mapLineInfo)->first();
                        $line_file_info = obj2array($line_file_info);
                        $count++;
                        $data[$key]['combo_info'][$k]['line'][$i]['key'] = $i + 1;
                        $data[$key]['combo_info'][$k]['line'][$i]['combo_key'] = $combo_key;
                        $data[$key]['combo_info'][$k]['line'][$i]['container_id'] = $order_id . '_' . $v['set_product_id'] . '_' . $value['temp_id'] . '_' . ($i + 1) . '_' . $combo_key . '_' . $combo_count;
                        $container_arr[] = $data[$key]['combo_info'][$k]['line'][$i]['container_id'];
                        if ($line_file_info) {
                            $data[$key]['combo_info'][$k]['line'][$i]['tracking_id'] = $line_file_info['tracking_number'];
                            $data[$key]['combo_info'][$k]['line'][$i]['file_name'] = $line_file_info['file_name'];
                            $data[$key]['combo_info'][$k]['line'][$i]['file_path'] = $line_file_info['file_path'];
                            $data[$key]['combo_info'][$k]['line'][$i]['deal_file_name'] = $line_file_info['deal_file_name'];
                            $data[$key]['combo_info'][$k]['line'][$i]['deal_file_path'] = $line_file_info['deal_file_path'];
                            $data[$key]['combo_info'][$k]['line'][$i]['store_id_img'] = $line_file_info['store_id_img'];
                            $data[$key]['combo_info'][$k]['line'][$i]['package_asn_img'] = $line_file_info['package_asn_img'];
                            $data[$key]['combo_info'][$k]['line'][$i]['store_deal_file_path'] = $line_file_info['store_deal_file_path'];
                            $data[$key]['combo_info'][$k]['line'][$i]['store_deal_file_name'] = $line_file_info['store_deal_file_name'];
                            $data[$key]['combo_info'][$k]['line'][$i]['file_id'] = $line_file_info['id'];
                            $data[$key]['combo_info'][$k]['line'][$i]['tracking_number_img'] = $line_file_info['tracking_number_img'];
                            $data[$key]['combo_info'][$k]['line'][$i]['order_id_img'] = $line_file_info['order_id_img'];
                            $data[$key]['combo_info'][$k]['line'][$i]['store_order_id_img'] = $line_file_info['store_order_id_img'];
                            $data[$key]['combo_info'][$k]['line'][$i]['weight_img'] = $line_file_info['weight_img'];
                            if (isset($data[$key]['store_label_container_id'])) {
                                $walmart_label_details['store_label'][$data[$key]['store_label_container_id']] = $line_file_info['store_deal_file_name'];
                            }
                            $walmart_label_details['common_label'][$data[$key]['combo_info'][$k]['line'][$i]['container_id']] = $line_file_info['file_name'];
                            $walmart_label_details['tracking_number'][$data[$key]['combo_info'][$k]['line'][$i]['container_id']] = $line_file_info['tracking_number'];
                        } else {
                            if ($k == 0 && $i < $value['qty']) {
                                $data[$key]['combo_info'][$k]['line'][$i]['tracking_id'] = null;
                            } else {
                                $data[$key]['combo_info'][$k]['line'][$i]['tracking_id'] = null;
                            }
                        }
                    }
                }
            }
            $data['total_file_amount'] = $count;
        }
        $this->session->set('walmart_store_label', $container_store_label);
        $this->session->set('walmart_label_details', $walmart_label_details);
        //upload 容器 id
        if ($this->session->has('walmart_container_id_list')) {
            $walmart_container_id_list[$order_id]['count'] = $count;
            $walmart_container_id_list[$order_id]['child_list'] = $container_arr;
            $walmart_container_id_list['id_list'] = array_unique(array_merge($walmart_container_id_list['id_list'], $container_arr));
            $this->session->set('walmart_container_id_list', $walmart_container_id_list);
        } else {
            $walmart_container_id_list[$order_id]['count'] = $count;
            $walmart_container_id_list[$order_id]['child_list'] = $container_arr;
            $walmart_container_id_list['id_list'] = $container_arr;
            $this->session->set('walmart_container_id_list', $walmart_container_id_list);
        }
        return $data;

    }

    public function getWalmartChildListComboInfo($data, $order_id, $bol_flag, &$walmart_container_id_list)
    {
        $count = 0;
        if ($bol_flag) {
            $count++;
        }
        $container_arr = [];
        $container_store_label = [];
        foreach ($data as $key => $value) {
            // 验证是否是s2s
            if ($value['ship_to'] == 'Store') {
                //此处需要上传store_label
                $count++;
                $data[$key]['store_label_container_id'] = $order_id . '_' . $key . '_' . $value['temp_id'];
                $container_store_label[] = $order_id . '_' . $key . '_' . $value['temp_id'];

            }
            $comboInfo = $this->getComboInfoBySku($value['item_code']);
            if (!$comboInfo) {
                //非combo
                $data[$key]['is_combo'] = 0;
                for ($i = 0; $i < (int)$value['qty']; $i++) {
                    $count++;
                    $data[$key]['combo_info'][$i]['key'] = $i + 1;
                    $data[$key]['combo_info'][$i]['container_id'] = $order_id . '_' . $key . '_' . $value['temp_id'] . '_' . ($i + 1);
                    $container_arr[] = $data[$key]['combo_info'][$i]['container_id'];
                    $data[$key]['combo_info'][$i]['tracking_id'] = null;
                }
            } else {

                $combo_count = array_sum(array_column($comboInfo, 'qty'));
                $data[$key]['is_combo'] = 1;
                $count_all = 0;
                foreach ($comboInfo as $k => $v) {

                    $data[$key]['combo_info'][$k] = $v;
                    $data[$key]['combo_info'][$k]['qty'] = $value['qty'] * $v['qty'];
                    $data[$key]['combo_info'][$k]['default_qty'] = $v['qty'];
                    $data[$key]['combo_info'][$k]['key'] = $k + 1;
                    //$combo_key = $k + 1;
                    $data[$key]['combo_count'] = $combo_count;
                    for ($i = 0; $i < (int)$value['qty'] * $v['qty']; $i++) {
                        $combo_key = intval(floor($count_all / $value['qty'])) + 1;
                        $count_all++;
                        $count++;
                        $data[$key]['combo_info'][$k]['line'][$i]['key'] = $i + 1;
                        $data[$key]['combo_info'][$k]['line'][$i]['combo_key'] = $combo_key;
                        $data[$key]['combo_info'][$k]['line'][$i]['container_id'] = $order_id . '_' . $v['set_product_id'] . '_' . $value['temp_id'] . '_' . ($i + 1) . '_' . $combo_key . '_' . $combo_count;
                        $container_arr[] = $data[$key]['combo_info'][$k]['line'][$i]['container_id'];
                        $data[$key]['combo_info'][$k]['line'][$i]['tracking_id'] = null;
                    }
                }
            }
            $data['total_file_amount'] = $count;
        }


        //upload 容器 id
        if ($this->session->has('walmart_container_id_list')) {
            $walmart_container_id_list[$order_id]['count'] = $count;
            $walmart_container_id_list[$order_id]['child_list'] = $container_arr;
            $walmart_container_id_list[$order_id]['walmart_store_label'] = $container_store_label;
            $walmart_container_id_list['id_list'] = array_unique(array_merge($walmart_container_id_list['id_list'], $container_arr));
            $walmart_container_id_list['order_id_list'][] = $order_id;
            $this->session->set('walmart_container_id_list', $walmart_container_id_list);
        } else {
            $walmart_container_id_list[$order_id]['count'] = $count;
            $walmart_container_id_list[$order_id]['child_list'] = $container_arr;
            $walmart_container_id_list[$order_id]['walmart_store_label'] = $container_store_label;
            $walmart_container_id_list['order_id_list'][] = $order_id;
            $walmart_container_id_list['id_list'] = $container_arr;
            $this->session->set('walmart_container_id_list', $walmart_container_id_list);
        }
        return $data;
    }


    /**
     * [judgeDropshipIsOverTime description] 返回是否可以编辑dropship
     * @param int $order_id
     * @return bool
     */
    public function judgeDropshipIsOverTime($order_id): bool
    {
        $data = CustomerSalesOrder::query()->alias('o')
            ->leftJoinRelations(['lines as l'])
            ->where('o.id', $order_id)
            ->select('o.order_status')
            ->value('order_status');
        return in_array($data, [CustomerSalesOrderStatus::TO_BE_PAID, CustomerSalesOrderStatus::CHECK_LABEL]);
    }

    /**
     * [dropshipSingleFileUploadDetails description]
     * @param $map
     * @param $upload_file_info
     * @param int $edit_flag
     * @return array | string
     */
    public function dropshipSingleFileUploadDetails($map, $upload_file_info, $edit_flag = 0)
    {
        $labels = 0;
        $file_arr = [];
        $file_list = explode(';', $map['file_str']);
        foreach ($file_list as $key => $value) {
            if ($value) {
                $tmp = explode(':', $value);
                $file_arr[current($tmp)] = end($tmp);
            }

        }
        foreach ($upload_file_info as $key => $value) {
            //获取订单id
            $order_id = $value['order_id'];
            foreach ($value['childrenList'] as $k => $v) {
                foreach ($v['combo_info'] as $ks => $vs) {
                    if ($edit_flag == 0) {
                        //数据更新
                        if ($v['is_combo'] == 0) {
                            if (isset($file_arr[$vs['container_id']])) {
                                $tracking_num = $map[$vs['container_id'] . '_input'];
                                $file_name = $file_arr[$vs['container_id']];
                                $mapFilePath = [
                                    ['container_id', '=', $vs['container_id']],
                                    ['status', '=', 1],
                                ];
                                $file_info = $this->orm->table('tb_sys_customer_sales_dropship_upload_file')
                                    ->where($mapFilePath)
                                    ->orderBy('id', 'desc')
                                    ->limit(1)
                                    ->first();
                                $filter_data = [
                                    //'line_id'               => $v['line_id'],
                                    //'temp_id'               => $v['temp_id'],
                                    //'order_id'              => $value['id'],
                                    //'is_combo'              => 0,
                                    //'sku'                   => $v['item_code'],
                                    //'qty'                   => $v['qty'],
                                    //'line_item_number'      => $vs['key'],
                                    'tracking_number' => $tracking_num,
                                    'file_name' => $file_name,
                                    'file_path' => $file_info->file_path,
                                    'deal_file_name' => $file_name,
                                    'deal_file_path' => $file_info->deal_file_path,
                                    'tracking_number_img' => $file_info->tracking_number_img,
                                    'order_id_img' => $file_info->order_id_img,
                                    'weight_img' => $file_info->weight_img,
                                    //'status'                => 1,
                                    'update_user_name' => $this->customer->getId(),
                                    'update_time' => date('Y-m-d H:i:s', time()),
                                    //'program_code'          => PROGRAM_CODE,
                                    //'run_id'                => $this->request->get['runId'],
                                ];
                                $labels++;
                                $this->orm->table('tb_sys_customer_sales_dropship_file_details')->where('id', $vs['file_id'])->update($filter_data);
                            }

                        } else {
                            //combo 品

                            foreach ($vs['line'] as $kline => $vline) {
                                $tracking_num = $map[$vline['container_id'] . '_input'];
                                if (isset($file_arr[$vline['container_id']])) {
                                    $file_name = $file_arr[$vline['container_id']];
                                    $mapFilePath = [
                                        ['container_id', '=', $vline['container_id']],
                                        ['status', '=', 1],
                                    ];
                                    $file_info = $this->orm->table('tb_sys_customer_sales_dropship_upload_file')->where($mapFilePath)
                                        ->orderBy('id', 'desc')->limit(1)->first();
                                    $filter_data = [
                                        'file_name' => $file_name,
                                        'file_path' => $file_info->file_path,
                                        'deal_file_name' => $file_name,
                                        'deal_file_path' => $file_info->deal_file_path,
                                        'tracking_number_img' => $file_info->tracking_number_img,
                                        'order_id_img' => $file_info->order_id_img,
                                        'weight_img' => $file_info->weight_img,
                                        'update_user_name' => $this->customer->getId(),
                                        'update_time' => date('Y-m-d H:i:s', time()),
                                        //'status'                => 1,
                                        //'create_user_name'      => $this->customer->getId(),
                                        //'create_time'           => date('Y-m-d H:i:s',time()),
                                        //'program_code'          => PROGRAM_CODE,
                                        //'run_id'                => $this->request->get['runId'],
                                    ];

                                }
                                $filter_data['tracking_number'] = $tracking_num;
                                $filter_data['update_user_name'] = $this->customer->getId();
                                $filter_data['update_time'] = date('Y-m-d H:i:s', time());
                                $labels++;
                                $this->orm->table('tb_sys_customer_sales_dropship_file_details')->where('id', $vline['file_id'])->update($filter_data);

                            }
                        }


                    } elseif ($edit_flag == 1) {

                        if ($v['is_combo'] == 0) {
                            //获取file_path
                            $tracking_num = $map[$vs['container_id'] . '_input'];
                            $file_name = $file_arr[$vs['container_id']];
                            $mapFilePath = [
                                ['container_id', '=', $vs['container_id']],
                                ['status', '=', 1],
                            ];
                            $file_info = $this->orm->table('tb_sys_customer_sales_dropship_upload_file')->where($mapFilePath)
                                ->orderBy('id', 'desc')->limit(1)->first();
                            $filter_data = [
                                'line_id' => $v['line_id'],
                                'temp_id' => $v['temp_id'],
                                'order_id' => $value['id'],
                                'is_combo' => 0,
                                'sku' => $v['item_code'],
                                'qty' => $v['qty'],
                                'line_item_number' => $vs['key'],
                                'tracking_number' => $tracking_num,
                                'file_name' => $file_name,
                                'file_path' => $file_info->file_path,
                                'deal_file_name' => $file_name,
                                'deal_file_path' => $file_info->deal_file_path,
                                'tracking_number_img' => $file_info->tracking_number_img,
                                'order_id_img' => $file_info->order_id_img,
                                'weight_img' => $file_info->weight_img,
                                'status' => 1,
                                'create_user_name' => $this->customer->getId(),
                                'create_time' => date('Y-m-d H:i:s', time()),
                                'program_code' => PROGRAM_CODE,
                                'run_id' => $this->orm->table('tb_sys_customer_sales_order')->where(['id' => $value['id']])->limit(1)->value('run_id'),
                            ];
                            $labels++;
                            $this->orm->table('tb_sys_customer_sales_dropship_file_details')->insert($filter_data);

                        } else {
                            //combo 品
                            foreach ($vs['line'] as $kline => $vline) {
                                $tracking_num = $map[$vline['container_id'] . '_input'];
                                $file_name = $file_arr[$vline['container_id']];
                                $mapFilePath = [
                                    ['container_id', '=', $vline['container_id']],
                                    ['status', '=', 1],
                                ];
                                $file_info = $this->orm->table('tb_sys_customer_sales_dropship_upload_file')->where($mapFilePath)
                                    ->orderBy('id', 'desc')->limit(1)->first();
                                $filter_data = [
                                    'line_id' => $v['line_id'],
                                    'temp_id' => $v['temp_id'],
                                    'order_id' => $value['id'],
                                    'is_combo' => 1,
                                    'sku' => $v['item_code'],
                                    'set_product_id' => $vs['set_product_id'],
                                    'combo_sort' => $ks + 1,
                                    'default_qty' => $vs['default_qty'],
                                    'qty' => $v['qty'],
                                    'line_item_number' => $vline['key'],
                                    'tracking_number' => $tracking_num,
                                    'file_name' => $file_name,
                                    'file_path' => $file_info->file_path,
                                    'deal_file_name' => $file_name,
                                    'deal_file_path' => $file_info->deal_file_path,
                                    'tracking_number_img' => $file_info->tracking_number_img,
                                    'order_id_img' => $file_info->order_id_img,
                                    'weight_img' => $file_info->weight_img,
                                    'status' => 1,
                                    'create_user_name' => $this->customer->getId(),
                                    'create_time' => date('Y-m-d H:i:s', time()),
                                    'program_code' => PROGRAM_CODE,
                                    'run_id' => $this->orm->table('tb_sys_customer_sales_order')->where(['id' => $value['id']])->limit(1)->value('run_id'),
                                ];
                                $labels++;
                                $this->orm->table('tb_sys_customer_sales_dropship_file_details')->insert($filter_data);

                            }


                        }


                    }

                }

            }
            if ($edit_flag == 1) {
                //更新 order_status 和 item_status
                $mapOrderUpdate = [
                    ['order_id', '=', $order_id],
                    ['create_user_name', '=', $this->customer->getId()],
                ];
                $this->orm->table('tb_sys_customer_sales_order')->where($mapOrderUpdate)->update(['order_status' => CustomerSalesOrderStatus::TO_BE_PAID]);
                $mapOrderLineUpdate = [
                    ['o.order_id', '=', $order_id],
                    ['o.create_user_name', '=', $this->customer->getId()],
                ];
                $this->orm->table('tb_sys_customer_sales_order_line as l')
                    ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
                    ->where($mapOrderLineUpdate)
                    ->update(['l.item_status' => CustomerSalesOrderLineItemStatus::PENDING]);
            }
        }
        return $labels;

    }

    /**
     * [judgeWalmartIsS2s description]
     * @param string $order_id
     * @return bool
     */
    public function judgeWalmartIsS2s($order_id)
    {
        $ship_to = $this->orm->table('tb_sys_customer_sales_walmart_temp')
            ->where([
                'order_id' => $order_id
            ])
            ->value('ship_to');

        if ($ship_to == 'Store') {
            return true;
        }

        return false;
    }

    /**
     * [getCustomerOrderAllInformation description] 根据销售订单获取所有的信息
     * @param int $order_id tb_sys_customer_sales_order表id主键
     * @param bool $tracking_privilege
     * @return array
     * @throws Exception
     */
    public function getCustomerOrderAllInformation($order_id, $tracking_privilege = false)
    {
        $mapOrder = [
            ['o.id', '=', $order_id],
        ];
        $mapLine = [
            ['l.header_id', '=', $order_id],
        ];
        $this->load->model('tool/image');
        $this->load->model('catalog/product');
        $base_info = CustomerSalesOrder::query()->alias('o')
            ->where($mapOrder)
            ->select([
                'o.order_id',
                'o.buyer_id',
                'o.order_status',
                'o.create_time',
                'o.orders_from',
                'o.id',
                'o.ship_name',
                'o.ship_phone',
                'o.email',
                'o.shipped_date',
                'o.ship_method',
                'o.ship_service_level',
                'o.ship_address1',
                'o.ship_city',
                'o.ship_state',
                'o.ship_zip_code',
                'o.ship_country',
                'o.order_mode',
                'o.customer_comments',
                'o.import_mode'])
            ->get()
            ->toArray();
        // walmart import mode = 7 的情况下需要添加s2s 标识
        $base_info = current($base_info);
        $base_info['customer_comments'] = trim($base_info['customer_comments']);
        $base_info['is_s2s'] = $this->judgeWalmartIsS2s($base_info['order_id']);
        $base_info['order_status_name'] = CustomerSalesOrderStatus::getDescription($base_info['order_status']);
        if ($base_info['order_mode'] == CustomerSalesOrderMode::DROP_SHIPPING) {
            //标红 且处理
            $base_info['ship_name_tips'] = '';
            $base_info['ship_phone_tips'] = '';
            $base_info['ship_address_tips'] = '';
            $judge_column = ['ship_phone', 'ship_address1', 'ship_state', 'ship_city', 'ship_zip_code'];
            foreach ($judge_column as $ks => $vs) {
                $s = $this->dealErrorCode($base_info[$vs]);
                if ($s != false) {
                    $base_info[$vs] = $s;
                    $column = 'text_error_column_' . $base_info['order_status'];
                    if ($ks == 0) {
                        $base_info['ship_phone_tips'] = '<i style="cursor:pointer" class="giga  icon-action-warning" data-toggle="tooltip" title="' . sprintf($this->language->get($column), 'Recipient Phone#') . '"></i>';
                    } else {
                        $base_info['ship_address_tips'] = '<i style="cursor:pointer" class="giga  icon-action-warning" data-toggle="tooltip" title="' . sprintf($this->language->get($column), 'Shipping Address') . '"></i>';
                    }
                }
            }
        }

        //自提货line明细，不展示删除状态的
        if ($base_info['import_mode'] == HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP) {
            $mapOrder = [
                ['o.id', '=', $order_id],
                ['l.item_status', '!=', CustomerSalesOrderLineItemStatus::DELETED],
            ];
            $mapLine = [
                ['l.header_id', '=', $order_id],
                ['l.item_status', '!=', CustomerSalesOrderLineItemStatus::DELETED],
            ];
        }
        //item_list 获取item_list
        $item_list = $this->orm->table('tb_sys_customer_sales_order_line as l')
            ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
            ->leftJoin('tb_sys_salesperson as sa', 'sa.id', '=', 'l.sales_person_id')
            ->leftJoin('tb_sys_order_associated as a', 'a.sales_order_line_id', '=', 'l.id')
            ->leftJoin(DB_PREFIX . 'yzc_rma_order as r', function ($join) {
                $join->on('r.from_customer_order_id', '=', 'o.order_id')->on('r.buyer_id', '=', 'o.buyer_id');
            })->leftJoin(DB_PREFIX . 'yzc_rma_order_product as rp', function ($join) {
                $join->on('rp.rma_id', '=', 'r.id')->on('a.product_id', '=', 'rp.product_id');
            })
            ->where($mapOrder)
            ->groupBy(['l.id'])
            ->select(
                'l.item_code',
                'l.qty',
                'o.sell_manager',
                'l.line_comments',
                'a.order_id as purchase_order_id',
                'l.id as line_id',
                'sa.name as sales_person_name'
            )
            ->selectRaw('group_concat(distinct rp.rma_id) as rma_order_id')
            ->get();
        $item_list = obj2array($item_list);
        $item_tag_list = [];
        $item_total_price = 0;
        $itemDiscountAmount = 0;
        //通过order_purchase_flag 判断是否没有采购订单
        foreach ($item_list as $key => $value) {
            // 获取tag
            //rma_order_id_list为数组
            $rma_order_id_list = explode(',', $value['rma_order_id']);
            foreach ($rma_order_id_list as $k => $v) {
                $rma_order_id_list[$k] = $this->orm->table(DB_PREFIX . 'yzc_rma_order as r')->where('id', $v)->value('rma_order_id');
            }
            $item_list[$key]['rma_order_id_list'] = $rma_order_id_list;
            // 获取产品tag
            if (!isset($item_tag_list[$value['item_code']])) {
                $tags = app(CustomerSalesOrderRepository::class)->getCustomerSalesOrderTags($value['line_id'], $value['item_code']);
                $item_tag_list[$value['item_code']] = $tags;
            } else {
                $tags = $item_tag_list[$value['item_code']];
            }
            //标记，加入
            $item_list[$key]['tag'] = $tags;
            $tmp = $this->getPurchaseOrderInfoByLineId($value['line_id']);
            if (false != $tmp) {
                $item_list[$key]['purchase_list'] = $tmp['purchase_list'];
                $item_list[$key]['total_price'] = $tmp['total_price'];
                $item_list[$key]['discount_amount'] = $tmp['discount_amount'];
                $item_list[$key]['final_total_price'] = $tmp['final_total_price'];
                $item_total_price += $tmp['sum'];
                $itemDiscountAmount += $tmp['discount_amount'];
                unset($tmp);
            }

        }
        //获取shipping information 放在base_info 里了
        //获取 sku的服务信息
        $signature_sub_item_list = [];
        $result = [];
        $trackingRepository = app(TrackRepository::class);
        if ($base_info['order_mode'] == HomePickUploadType::ORDER_MODE_HOMEPICK) {
            //获取信息
            //通过order_mode = 1 的查询方式获取
            $tmp = $this->orm->table('tb_sys_customer_sales_order_line as l')
                ->where($mapLine)
                ->select(['l.id', 'l.header_id', 'l.item_status', 'l.item_code as sku', 'l.qty', 'l.combo_info'])
                ->get()
                ->map(function ($item) {
                    $item = (array)$item;
                    $item['combo_info'] = !empty($item['combo_info'])
                        ? json_decode($item['combo_info'], true)
                        : [];
                    return $item;
                })
                ->toArray();
            $shipping_information = $tmp;
            foreach ($shipping_information as $key => $value) {
                $shipping_information[$key]['tag'] = $item_tag_list[$value['sku']];
                $shipping_information[$key]['item_status_name'] = CustomerSalesOrderLineItemStatus::getDescription($value['item_status']);
                //$shipping_information[$key]['tracking_number'] = explode(',',$value['tracking_number']);
                //验证是否为combo
                $item_code = $this->orm->table(DB_PREFIX . 'product as ts')
                    ->where('ts.sku', $value['sku'])
                    ->where('ts.is_deleted', 0)
                    ->orderBy('ts.product_id', 'desc')
                    ->value('ts.product_id');
                if ($item_code == null) {
                    $comboInfo = null;
                } else {
                    // 使用line里面的combo_info替换当前combo
                    // wangjinxin
                    $line_combo_info = !empty($value['combo_info'])
                        ? array_pop($value['combo_info'])
                        : null;
                    unset($line_combo_info[$value['sku']]);
                    $associate_info = $this->orm
                        ->table('tb_sys_order_associated')
                        ->where([
                            'sales_order_id' => $value['header_id'],
                            'sales_order_line_id' => $value['id']
                        ])
                        ->orderBy('id', 'desc')
                        ->first();
                    if (!empty($line_combo_info) && $associate_info) {
                        $comboInfo = $this->orm
                            ->table('oc_product as p')
                            ->leftJoin('oc_customerpartner_to_product as c2p', 'c2p.product_id', '=', 'p.product_id')
                            ->select([
                                'p.product_id as set_product_id', 'p.sku',
                            ])
                            ->selectRaw(new Expression("1 as qty"))
                            ->whereIn('p.sku', array_keys($line_combo_info))
                            ->where('c2p.customer_id', '=', $associate_info->seller_id)
                            ->get()
                            ->map(function ($item) use ($line_combo_info) {
                                $item = (array)$item;
                                if ($line_combo_info) {
                                    $item['qty'] = $line_combo_info[$item['sku']] ?? $item['qty'];
                                }
                                return $item;
                            })
                            ->toArray();
                    } else {
                        $comboInfo = $this->orm
                            ->table('tb_sys_product_set_info as s')
                            ->where('p.product_id', $item_code)
                            ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 's.product_id')
                            ->leftJoin(DB_PREFIX . 'product as pc', 'pc.product_id', '=', 's.set_product_id')
                            ->whereNotNull('s.set_product_id')
                            ->select('s.set_product_id', 's.qty', 'pc.sku')
                            ->get()
                            ->map(function ($item) use ($line_combo_info) {
                                $item = (array)$item;
                                return $item;
                            })
                            ->toArray();
                    }
                }

                if ($comboInfo) {
                    $length = count($comboInfo);
                    foreach ($comboInfo as $k => $v) {
                        //首先获取tacking_number
                        $mapTrackingInfo['d.line_id'] = $value['id'];
                        $mapTrackingInfo['d.sku'] = $value['sku'];
                        $mapTrackingInfo['p.sku'] = $v['sku'];
                        //$mapTrackingInfo['k.SalesOrderId'] =  $base_info['order_id'];
                        //获取pdf 和name
                        $tracking_info = $this->orm->table('tb_sys_customer_sales_dropship_file_details as d')
                            ->leftJoin(DB_PREFIX . 'product as  p', 'p.product_id', '=', 'd.set_product_id')
                            ->leftJoin('tb_sys_customer_sales_order_tracking as k', function ($join) use ($base_info) {
                                $join->on('d.line_id', '=', 'k.SalerOrderLineId')->on('k.Shipsku', '=', 'p.sku')->where('k.SalesOrderId', '=', $base_info['order_id']);
                            })
                            ->leftJoin('tb_sys_carriers as c', 'c.CarrierID', '=', 'k.LogisticeId')
                            ->where($mapTrackingInfo)
                            ->selectRaw("d.tracking_number as d_tracking_number,case when length(d.deal_file_name)>30 then concat(substring(d.deal_file_name,1,30),'...') else d.deal_file_name end as deal_file_name ,d.deal_file_path,k.status,k.TrackingNumber as tracking_number,d.store_deal_file_name,d.store_deal_file_path")
                            ->selectRaw('if(c.carrierName="Truck",k.ServiceLevelId,c.carrierName) AS carrier_name')
                            ->orderBy('k.status', 'desc')
                            ->get();
                        //相同combo下的产品应该是相同carrier
                        unset($mapTrackingInfo);
                        $tracking_info = obj2array($tracking_info);
                        $tracking_info = $this->dealTrackingNumberToShow($tracking_info, $base_info['order_mode']);
                        $shipping_information[$key]['tracking_number'] = $tracking_info['tracking_number'];
                        if ($shipping_information[$key]['tracking_number']) {
                            foreach ($tracking_info['tracking_number'] as $i => $iValue) {
                                $shipping_information[$key]['carrier_status'][$i] = $trackingRepository->getTrackingStatusByTrackingNumber($base_info['order_id'], $tracking_info['tracking_number'][$i]);
                            }
                        }
                        //英国订单,物流单号为JD开头,显示Carrier是Yodel
                        if ($this->customer->getCountryId() == HomePickUploadType::BRITAIN_COUNTRY_ID && 'JD' == substr($tracking_info['tracking_number'][0], 0, 2) && in_array($tracking_info['carrier_name'][0], CHANGE_CARRIER_NAME)) {
                            $shipping_information[$key]['carrier_name'] = ['Yodel'];
                        } elseif ($this->customer->getCountryId() == HomePickUploadType::BRITAIN_COUNTRY_ID && in_array($tracking_info['carrier_name'][0], CHANGE_CARRIER_NAME)) {
                            $shipping_information[$key]['carrier_name'] = ['WHISTL'];
                        } else {
                            $shipping_information[$key]['carrier_name'] = $tracking_info['carrier_name'];
                        }
                        $shipping_information[$key]['tracking_status'] = $tracking_info['status'];
                        $shipping_information[$key]['file_name'] = $tracking_info['deal_file_name'];
                        $shipping_information[$key]['file_path'] = $tracking_info['deal_file_path'];
                        $shipping_information[$key]['store_file_name'] = $tracking_info['store_deal_file_name'] ?? '';
                        $shipping_information[$key]['store_file_path'] = $tracking_info['store_deal_file_path'] ?? '';
                        $tmp_all = $shipping_information[$key];
                        if ($k == 0) {
                            $tmp_all['cross_row'] = $length;
                        }
                        $tmp_all['child_sku'] = $v['sku'];
                        $tmp_all['all_qty'] = $v['qty'] * $value['qty'];
                        $tmp_all['child_qty'] = $v['qty'];
                        //获取default_qty
                        if (isset($signature_sub_item_list[$value['sku']])) {
                            $signature_sub_item_list[$value['sku']] += $tmp_all['child_qty'];
                        } else {
                            $signature_sub_item_list[$value['sku']] = $tmp_all['child_qty'];
                        }
                        $result[] = $tmp_all;
                        unset($tmp_all);
                    }
                    if ($base_info['ship_method'] == 'ASR') {
                        //签收服务费 和combo 信息类似
                        $asr_list = $shipping_information[$key];
                    }

                } else {
                    //首先获取tacking_number
                    $mapTrackingInfo['d.line_id'] = $value['id'];
                    $mapTrackingInfo['d.sku'] = $value['sku'];
                    //$mapTrackingInfo['k.SalesOrderId'] =  $base_info['order_id'];
                    //获取pdf 和name
                    $tracking_info = $this->orm->table('tb_sys_customer_sales_dropship_file_details as d')
                        ->leftJoin(DB_PREFIX . 'product as  p', 'p.product_id', '=', 'd.set_product_id')
                        ->leftJoin('tb_sys_customer_sales_order_tracking as k', function ($join) use ($base_info) {
                            $join->on('d.line_id', '=', 'k.SalerOrderLineId')->on('k.Shipsku', '=', 'd.sku')->where('k.SalesOrderId', '=', $base_info['order_id']);
                        })
                        ->leftJoin('tb_sys_carriers as c', 'c.CarrierID', '=', 'k.LogisticeId')
                        ->where($mapTrackingInfo)->selectRaw("d.tracking_number as d_tracking_number,case when length(d.deal_file_name)>30 then concat(substring(d.deal_file_name,1,30),'...') else d.deal_file_name end as deal_file_name ,d.deal_file_path,k.status,k.TrackingNumber as tracking_number,d.store_deal_file_name,d.store_deal_file_path")
                        ->selectRaw('if(c.carrierName="Truck",k.ServiceLevelId,c.carrierName) AS carrier_name')
                        ->orderBy('k.status', 'desc')->get();
                    //相同combo下的产品应该是相同carrier
                    unset($mapTrackingInfo);
                    $tracking_info = obj2array($tracking_info);
                    if (!$tracking_info) {
                        $tracking_info['tracking_number'] = null;
                        $tracking_info['carrier_name'] = null;
                        $shipping_information[$key]['file_name'] = null;
                        $shipping_information[$key]['file_path'] = null;
                    }
                    $tracking_info = $this->dealTrackingNumberToShow($tracking_info, $base_info['order_mode']);
                    $shipping_information[$key]['tracking_number'] = $tracking_info['tracking_number'];
                    if ($tracking_info['tracking_number']) {
                        foreach ($tracking_info['tracking_number'] as $i => $iValue) {
                            $shipping_information[$key]['carrier_status'][$i] = $trackingRepository->getTrackingStatusByTrackingNumber($base_info['order_id'], $tracking_info['tracking_number'][$i]);
                        }
                    }
                    //英国订单,物流单号为JD开头,显示Carrier是Yodel
                    if ($this->customer->getCountryId() == HomePickUploadType::BRITAIN_COUNTRY_ID && 'JD' == substr($tracking_info['tracking_number'][0], 0, 2) && in_array($tracking_info['carrier_name'][0], CHANGE_CARRIER_NAME)) {
                        $shipping_information[$key]['carrier_name'] = ['Yodel'];
                    } elseif ($this->customer->getCountryId() == HomePickUploadType::BRITAIN_COUNTRY_ID && in_array($tracking_info['carrier_name'][0], CHANGE_CARRIER_NAME)) {
                        $shipping_information[$key]['carrier_name'] = ['WHISTL'];
                    } else {
                        $shipping_information[$key]['carrier_name'] = $tracking_info['carrier_name'];
                    }
                    $shipping_information[$key]['tracking_status'] = $tracking_info['status'];
                    $shipping_information[$key]['file_name'] = $tracking_info['deal_file_name'];
                    $shipping_information[$key]['file_path'] = $tracking_info['deal_file_path'];
                    $shipping_information[$key]['store_file_name'] = $tracking_info['store_deal_file_name'];
                    $shipping_information[$key]['store_file_path'] = $tracking_info['store_deal_file_path'];
                    $shipping_information[$key]['cross_row'] = 1;
                    $shipping_information[$key]['tag'] = $item_tag_list[$value['sku']];
                    $signature_sub_item_list[$value['sku']] = 0;
                    $result[] = $shipping_information[$key];

                }
            }
            unset($shipping_information);
            $shipping_information = $result;


        } else {
            //获取信息
            //通过order_mode = 1 的查询方式获取
            $tmp = $this->orm
                ->table('tb_sys_customer_sales_order_line as l')
                ->where('l.header_id', $order_id)
                ->select('l.id', 'l.header_id', 'l.item_status', 'l.item_code as sku', 'l.qty', 'l.combo_info')
                ->get()
                ->map(function ($item) {
                    $item = (array)$item;
                    $item['combo_info'] = !empty($item['combo_info'])
                        ? json_decode($item['combo_info'], true)
                        : [];
                    return $item;
                })
                ->toArray();
            $shipping_information = $tmp;
            foreach ($shipping_information as $key => $value) {
                $shipping_information[$key]['tag'] = $item_tag_list[$value['sku']];
                //验证是否为combo
                $item_code = $this->orm
                    ->table(DB_PREFIX . 'product as ts')
                    ->where('ts.sku', $value['sku'])
                    ->where('ts.is_deleted', 0)
                    ->orderBy('ts.product_id', 'desc')
                    ->value('ts.product_id');
                if ($item_code == null) {
                    $comboInfo = null;
                } else {
                    // 使用line里面的combo_info替换当前combo
                    // wangjinxin
                    $line_combo_info = !empty($value['combo_info'])
                        ? array_pop($value['combo_info'])
                        : null;
                    unset($line_combo_info[$value['sku']]);
                    $associate_info = $this->orm
                        ->table('tb_sys_order_associated')
                        ->where([
                            'sales_order_id' => $value['header_id'],
                            'sales_order_line_id' => $value['id']
                        ])
                        ->orderBy('id', 'desc')
                        ->first();
                    if (!empty($line_combo_info) && $associate_info) {
                        $comboInfo = $this->orm
                            ->table('oc_product as p')
                            ->leftJoin('oc_customerpartner_to_product as c2p', 'c2p.product_id', '=', 'p.product_id')
                            ->select([
                                'p.product_id as set_product_id', 'p.sku',
                            ])
                            ->selectRaw(new Expression("{$associate_info->product_id} as product_id"))
                            ->selectRaw(new Expression("1 as qty"))
                            ->whereIn('p.sku', array_keys($line_combo_info))
                            ->where('c2p.customer_id', '=', $associate_info->seller_id)
                            ->get()
                            ->map(function ($item) use ($line_combo_info) {
                                $item = (array)$item;
                                if ($line_combo_info) {
                                    $item['qty'] = $line_combo_info[$item['sku']] ?? $item['qty'];
                                }
                                return $item;
                            })
                            ->toArray();
                    } else {
                        $comboInfo = $this->orm
                            ->table('tb_sys_product_set_info as s')
                            ->where('p.product_id', $item_code)
                            ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 's.product_id')
                            ->leftJoin(DB_PREFIX . 'product as pc', 'pc.product_id', '=', 's.set_product_id')
                            ->whereNotNull('s.set_product_id')
                            ->select('p.product_id', 's.set_product_id', 's.qty', 'pc.sku')
                            ->get()
                            ->map(function ($item) use ($line_combo_info) {
                                $item = (array)$item;
                                return $item;
                            })
                            ->toArray();
                    }
                }
                if ($comboInfo) {
                    // 检测是否是
                    $length = count($comboInfo);
                    if (($tracking_privilege && $base_info['order_status'] == CustomerSalesOrderStatus::COMPLETED) || !$tracking_privilege) {
                        $mapExists['k.SalerOrderLineId'] = $value['id'];
                        $mapExists['k.SalesOrderId'] = $value['order_id'];
                        $mapExists['k.ShipSku'] = $value['sku'];
                        $trackingComboExists = CustomerSalesOrderTracking::query()->alias('k')
                            ->where($mapExists)
                            ->exists();
                    } else {
                        $trackingComboExists = false;
                    }

                    if ($trackingComboExists) {
                        foreach ($comboInfo as $k => $v) {
                            //首先获取tacking_number
                            $mapTrackingInfo['k.SalerOrderLineId'] = $value['id'];
                            $mapTrackingInfo['k.SalesOrderId'] = $base_info['order_id'];
                            if (($tracking_privilege && $base_info['order_status'] == CustomerSalesOrderStatus::COMPLETED && $k == 0) || (!$tracking_privilege && $k == 0)) {
                                $tracking_info = CustomerSalesOrderTracking::query()->alias('k')
                                    ->leftJoin('tb_sys_carriers as c', 'c.CarrierID', '=', 'k.LogisticeId')
                                    ->where($mapTrackingInfo)
                                    ->select(['k.TrackingNumber as tracking_number', 'k.status'])
                                    ->selectRaw('if(c.carrierName="Truck",k.ServiceLevelId,c.carrierName) AS carrier_name')
                                    ->orderBy('k.status', 'desc')
                                    ->get()
                                    ->toArray();
                            } else {
                                $tracking_info = [];
                            }
                            unset($mapTrackingInfo);
                            $tracking_info = $this->dealTrackingNumberToShow($tracking_info, $base_info['order_mode']);

                            $shipping_information[$key]['tracking_number'] = $tracking_info['tracking_number'];
                            if (is_array($tracking_info['tracking_number'])) {
                                foreach ($tracking_info['tracking_number'] as $i => $iValue) {
                                    $shipping_information[$key]['carrier_status'][$i] = $trackingRepository->getTrackingStatusByTrackingNumber($base_info['order_id'], $tracking_info['tracking_number'][$i]);
                                }
                            }
                            //英国订单,物流单号为JD开头,显示Carrier是Yodel
                            if ($this->customer->getCountryId() == HomePickUploadType::BRITAIN_COUNTRY_ID && 'JD' == substr($tracking_info['tracking_number'][0], 0, 2) && in_array($tracking_info['carrier_name'][0], CHANGE_CARRIER_NAME)) {
                                $shipping_information[$key]['carrier_name'] = ['Yodel'];
                            } elseif ($this->customer->getCountryId() == HomePickUploadType::BRITAIN_COUNTRY_ID && in_array($tracking_info['carrier_name'][0], CHANGE_CARRIER_NAME)) {
                                $shipping_information[$key]['carrier_name'] = ['WHISTL'];
                            } else {
                                $shipping_information[$key]['carrier_name'] = $tracking_info['carrier_name'];
                            }
                            $shipping_information[$key]['tracking_status'] = $tracking_info['status'];
                            $tmp_all = $shipping_information[$key];
                            if ($k == 0) {
                                $tmp_all['cross_row'] = $length;
                                $tmp_all['cross_tracking_row'] = $length;
                            }

                            $tmp_all['child_sku'] = $v['sku'];
                            $tmp_all['all_qty'] = $v['qty'] * $value['qty'];
                            $tmp_all['child_qty'] = $v['qty'];
                            //获取default_qty
                            if (isset($signature_sub_item_list[$value['sku']])) {
                                $signature_sub_item_list[$value['sku']] += $tmp_all['child_qty'];
                            } else {
                                $signature_sub_item_list[$value['sku']] = $tmp_all['child_qty'];
                            }
                            $result[] = $tmp_all;
                            unset($tmp_all);
                        }
                    } else {
                        foreach ($comboInfo as $k => $v) {
                            //首先获取tacking_number
                            $mapTrackingInfo['k.SalerOrderLineId'] = $value['id'];
                            $mapTrackingInfo['k.ShipSku'] = $v['sku'];
                            $mapTrackingInfo['k.SalesOrderId'] = $base_info['order_id'];
                            if (($tracking_privilege && $base_info['order_status'] == CustomerSalesOrderStatus::COMPLETED) || !$tracking_privilege) {
                                $tracking_info = CustomerSalesOrderTracking::query()->alias('k')
                                    ->leftJoin('tb_sys_carriers as c', 'c.CarrierID', '=', 'k.LogisticeId')
                                    ->where($mapTrackingInfo)
                                    ->select(['k.TrackingNumber as tracking_number', 'k.status'])
                                    ->selectRaw('if(c.carrierName="Truck",k.ServiceLevelId,c.carrierName) AS carrier_name')
                                    ->orderBy('k.status', 'desc')->get();
                                $tracking_info = obj2array($tracking_info);
                            } else {
                                $tracking_info = [];
                            }
                            unset($mapTrackingInfo);
                            $tracking_info = $this->dealTrackingNumberToShow($tracking_info, $base_info['order_mode']);

                            $shipping_information[$key]['tracking_number'] = $tracking_info['tracking_number'];
                            if ($tracking_info['tracking_number']) {
                                foreach ($tracking_info['tracking_number'] as $i => $iValue) {
                                    $shipping_information[$key]['carrier_status'][$i] = $trackingRepository->getTrackingStatusByTrackingNumber($base_info['order_id'], $tracking_info['tracking_number'][$i]);
                                }
                            }
                            //英国订单,物流单号为JD开头,显示Carrier是Yodel
                            if ($this->customer->getCountryId() == HomePickUploadType::BRITAIN_COUNTRY_ID && 'JD' == substr($tracking_info['tracking_number'][0], 0, 2) && in_array($tracking_info['carrier_name'][0], CHANGE_CARRIER_NAME)) {
                                $shipping_information[$key]['carrier_name'] = ['Yodel'];
                            } elseif ($this->customer->getCountryId() == HomePickUploadType::BRITAIN_COUNTRY_ID && in_array($tracking_info['carrier_name'][0], CHANGE_CARRIER_NAME)) {
                                $shipping_information[$key]['carrier_name'] = ['WHISTL'];
                            } else {
                                $shipping_information[$key]['carrier_name'] = $tracking_info['carrier_name'];
                            }
                            $shipping_information[$key]['tracking_status'] = $tracking_info['status'];
                            $tmp_all = $shipping_information[$key];
                            if ($k == 0) {
                                $tmp_all['cross_row'] = $length;
                            }
                            $tmp_all['cross_tracking_row'] = 1;
                            $tmp_all['child_sku'] = $v['sku'];
                            $tmp_all['all_qty'] = $v['qty'] * $value['qty'];
                            $tmp_all['child_qty'] = $v['qty'];
                            //获取default_qty
                            if (isset($signature_sub_item_list[$value['sku']])) {
                                $signature_sub_item_list[$value['sku']] += $tmp_all['child_qty'];
                            } else {
                                $signature_sub_item_list[$value['sku']] = $tmp_all['child_qty'];
                            }
                            $result[] = $tmp_all;
                            unset($tmp_all);
                        }
                    }
                    if ($base_info['ship_method'] == 'ASR') {
                        //签收服务费 和combo 信息类似
                        $asr_list = $shipping_information[$key];
                    }

                } else {
                    //获取tracking_number
                    $mapTrackingInfo['k.SalerOrderLineId'] = $value['id'];
                    $mapTrackingInfo['k.ShipSku'] = $value['sku'];
                    $mapTrackingInfo['k.SalesOrderId'] = $base_info['order_id'];
                    if (($tracking_privilege && $base_info['order_status'] == CustomerSalesOrderStatus::COMPLETED) || !$tracking_privilege) {
                        $tracking_info = CustomerSalesOrderTracking::query()->alias('k')
                            ->leftJoin('tb_sys_carriers as c', 'c.CarrierID', '=', 'k.LogisticeId')
                            ->where($mapTrackingInfo)
                            ->select(['k.TrackingNumber as tracking_number', 'k.status'])
                            ->selectRaw('if(c.carrierName="Truck",k.ServiceLevelId,c.carrierName) AS carrier_name')
                            ->orderBy('k.status', 'desc')
                            ->get();
                        $tracking_info = obj2array($tracking_info);
                    } else {
                        $tracking_info = [];
                    }
                    //一个处理tracking_info 的方法
                    $tracking_info = $this->dealTrackingNumberToShow($tracking_info, $base_info['order_mode']);
                    unset($mapTrackingInfo);
                    $shipping_information[$key]['tracking_number'] = $tracking_info['tracking_number'];
                    if ($tracking_info['tracking_number']) {
                        foreach ($tracking_info['tracking_number'] as $i => $iValue) {
                            $shipping_information[$key]['carrier_status'][$i] = $trackingRepository->getTrackingStatusByTrackingNumber($base_info['order_id'], $tracking_info['tracking_number'][$i]);
                        }
                    }
                    //英国订单,物流单号为JD开头,显示Carrier是Yodel
                    if ($this->customer->getCountryId() == HomePickUploadType::BRITAIN_COUNTRY_ID && 'JD' == substr($tracking_info['tracking_number'][0], 0, 2) && in_array($tracking_info['carrier_name'][0], CHANGE_CARRIER_NAME)) {
                        $shipping_information[$key]['carrier_name'] = ['Yodel'];
                    } elseif ($this->customer->getCountryId() == HomePickUploadType::BRITAIN_COUNTRY_ID && in_array($tracking_info['carrier_name'][0], CHANGE_CARRIER_NAME)) {
                        $shipping_information[$key]['carrier_name'] = ['WHISTL'];
                    } else {
                        $shipping_information[$key]['carrier_name'] = $tracking_info['carrier_name'];
                    }
                    $shipping_information[$key]['tracking_status'] = $tracking_info['status'];
                    $shipping_information[$key]['cross_row'] = 1;
                    $shipping_information[$key]['cross_tracking_row'] = 1;
                    $shipping_information[$key]['tag'] = $item_tag_list[$value['sku']];
                    $signature_sub_item_list[$value['sku']] = 0;
                    $result[] = $shipping_information[$key];
                }
            }
            unset($shipping_information);
            $shipping_information = $result;
        }
        //获取的
        //获取ASR 服务费
        $sub_total = 0;
        $fee_total = 0;
        $all_total = 0;
        if ($base_info['ship_method'] == 'ASR') {
            //根据数据查询是否是combo
            $signature_list = $this->orm->table('tb_sys_customer_sales_order_line as l')
                ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
                ->leftJoin('tb_sys_order_associated as a', 'a.sales_order_line_id', '=', 'l.id')
                ->leftJoin(DB_PREFIX . 'order as oco', 'a.order_id', '=', 'oco.order_id')
                ->leftJoin(DB_PREFIX . 'order_product as op', function ($join) {
                    $join->on('op.order_id', '=', 'a.order_id');
                })
                ->where('o.id', $order_id)
                ->where('op.product_id', $this->orm->table(DB_PREFIX . 'setting')->where('key', 'signature_service_us_product_id')->value('value'))
                ->select('oco.payment_method', 'l.item_code', 'l.qty', 'op.price', 'op.poundage', 'op.total', 'op.quantity', 'l.id as line_id')
                ->selectRaw('round(op.poundage/op.quantity,2) as per_poundage')
                ->get();
            $signature_list = obj2array($signature_list);
            foreach ($signature_list as $key => $value) {
                $tag_array = $this->model_catalog_product->getProductSpecificTagByOrderLineId($value['line_id']);
                $tags = array();
                if (isset($tag_array)) {
                    foreach ($tag_array as $tag) {
                        if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                            //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                            $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                            $tags[] = '<img data-toggle="tooltip" class="' . $tag['class_style'] . '" title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                        }
                    }
                }
                //标记，加入·
                $signature_list[$key]['tag'] = $tags;
                //获取他的子sub_item_qty
                if (isset($signature_sub_item_list[$value['item_code']]) && $signature_sub_item_list[$value['item_code']] != 0) {
                    $signature_list[$key]['sub_item_qty'] = (int)$signature_sub_item_list[$value['item_code']];
                    $signature_list[$key]['package_qty'] = $value['qty'] * $signature_list[$key]['sub_item_qty'];
                } else {
                    $signature_list[$key]['sub_item_qty'] = 0;
                    $signature_list[$key]['package_qty'] = $value['qty'];
                }
                //计算美国签收服务费，
                $signature_list[$key]['total'] = $this->currency->formatCurrencyPrice($value['price'] * $signature_list[$key]['package_qty'], $this->session->data['currency']);
                $signature_list[$key]['price'] = $this->currency->formatCurrencyPrice($value['price'], $this->session->data['currency']);
                $signature_list[$key]['poundage'] = $this->currency->formatCurrencyPrice($value['poundage'], $this->session->data['currency']);
                $sub_total += $value['price'] * $signature_list[$key]['package_qty'];
                $fee_total += $value['per_poundage'] * $signature_list[$key]['package_qty'];
                $all_total += $value['price'] * $signature_list[$key]['package_qty'];

            }

        } else {
            $signature_list = null;
        }

        $res['sub_total'] = $this->currency->formatCurrencyPrice($sub_total, $this->session->data['currency']);
        $res['fee_total'] = $this->currency->formatCurrencyPrice($fee_total, $this->session->data['currency']);
        $res['all_total'] = $this->currency->formatCurrencyPrice($all_total, $this->session->data['currency']);
        $res['shipping_information'] = $shipping_information;
        $res['signature_list'] = $signature_list;
        $res['item_list'] = $item_list;
        $res['item_discount_amount'] = $itemDiscountAmount;
        $res['item_total_price'] = $this->currency->formatCurrencyPrice($item_total_price, $this->session->data['currency']);
        $res['item_final_total_price'] = $this->currency->formatCurrencyPrice($item_total_price - $itemDiscountAmount, $this->session->data['currency']);
        $res['base_info'] = $base_info;
        return $res;

    }

    /**
     * [getTransactionInfoByOrderProductId description]
     * @param int $order_product_id 采购订单明细主键
     * @return array
     */
    public function getTransactionInfoByOrderProductId($order_product_id)
    {
        $ret = $this->orm->table(DB_PREFIX . 'order_product as oop')
            ->where('order_product_id', $order_product_id)
            ->selectRaw('type_id,agreement_id')
            ->first();
        if (empty($ret)) {
            return [];
        }
        if (ProductTransactionType::MARGIN == $ret->type_id) {
            $list = $this->orm->table(DB_PREFIX . 'order_product as oop')
                ->leftJoin('tb_sys_margin_agreement as a', 'a.id', '=', 'oop.agreement_id')
                ->leftJoin('tb_sys_margin_process as s', 's.margin_id', '=', 'a.id')
                ->where('oop.order_product_id', $order_product_id)
                ->selectRaw('s.advance_order_id,s.margin_agreement_id,a.deposit_per,a.price,s.margin_id,oop.type_id')
                ->get()
                ->map(function ($value) {
                    return (array)$value;
                })
                ->toArray();
            foreach ($list as $key => &$value) {
                $value['url'] = str_ireplace('&amp;', '&', $this->url->link('account/product_quotes/margin/detail_list', '&id=' . $value['margin_id'], true));
                $value['icon'] = sprintf(TRANSACTION_TYPE_ICON[2], $value['margin_agreement_id']);
            }
            return current($list);
        } elseif (ProductTransactionType::FUTURE == $ret->type_id) {
            $list = $this->orm->table(DB_PREFIX . 'order_product as oop')
                ->leftJoin('oc_futures_margin_agreement as a', 'a.id', '=', 'oop.agreement_id')
                ->where('oop.order_product_id', $order_product_id)
                ->selectRaw('a.contract_id, a.agreement_no,oop.agreement_id,oop.type_id')
                ->first();
            if ($list->contract_id) {
                //期货保证金二期
                $url = str_ireplace('&amp;', '&', $this->url->link('account/product_quotes/futures/buyerFuturesBidDetail', '&id=' . $list->agreement_id, true));
            } else {
                //期货保证金一期
                $url = str_ireplace('&amp;', '&', $this->url->link('account/product_quotes/futures/detail', '&id=' . $list->agreement_id, true));
            }
            $list->url = $url;
            $list->icon = sprintf(TRANSACTION_TYPE_ICON[3], $list->agreement_no);
            return obj2array($list);
        }
        return [];
    }

    /**
     * Buyer账号是否被限制查看运单号
     * 需求文档：美国站点关联美国招商经理的一件代发Buyer
     * 人话翻译：true 此Buyer被限制(即销售单的状态是completed时允许查看运单号)；false 此Buyer没有被限制(即可以随意查看运单号)
     * @param int $customer_id
     * @param bool $isCollectionFromDomicile
     * @param int $country_id
     * @return bool
     */
    public function getTrackingPrivilege($customer_id, $isCollectionFromDomicile, $country_id)
    {
        if (!$isCollectionFromDomicile && $country_id == AMERICAN_COUNTRY_ID) {
            return $this->orm->table('tb_sys_buyer_account_manager as m')
                ->leftJoin(DB_PREFIX . 'leasing_manager as l', 'l.customer_id', '=', 'm.AccountId')
                ->where(
                    [
                        'm.BuyerId' => $customer_id,
                        'l.country_id' => AMERICAN_COUNTRY_ID
                    ]
                )
                ->exists();
        } else {
            return false;
        }
    }

    /**
     * [dealTrackingNumberToShow description] 处理展示的数据 这个页面有更新数据
     * @param $data
     * @param int $order_mode
     * @return array
     */
    public function dealTrackingNumberToShow($data, $order_mode = 3)
    {
        $res = [];
        $real_status = '';
        $real_carrier_name = '';
        $real_tracking_number = '';
        $real_deal_file_name = '';
        $real_deal_file_path = '';
        $real_store_deal_file_name = '';
        $real_store_deal_file_path = '';
        $unique_tracking = [];
        $ShipDeliveryDate = '';
        if ($data) {
            foreach ($data as $key => $value) {
                if ($order_mode == HomePickUploadType::ORDER_MODE_HOMEPICK) {
                    //必须要对combo做首次的处理
                    if ($value['carrier_name'] == null && $value['tracking_number'] == null && $value['status'] == null) {
                        $unique_tracking[] = '#' . $value['d_tracking_number'] . '#1';
                    } else {
                        $unique_tracking[] = $value['carrier_name'] . '#' . $value['tracking_number'] . '#' . $value['status'];
                    }

                    $real_deal_file_name .= $value['deal_file_name'] . ',';
                    $real_deal_file_path .= $value['deal_file_path'] . ',';
                    $real_store_deal_file_name .= $value['store_deal_file_name'] . ',';
                    $real_store_deal_file_path .= $value['store_deal_file_path'] . ',';
                } else {
                    $tracking_number = explode(',', $value['tracking_number']);
                    $length = count($tracking_number);
                    $real_tracking_number .= $value['tracking_number'] . ',';
                    for ($i = 0; $i < $length; $i++) {
                        $real_status .= $value['status'] . ',';
                        $real_carrier_name .= $value['carrier_name'] . ',';
                        if (isset($value['ShipDeliveryDate'])) {
                            $ShipDeliveryDate .= $value['ShipDeliveryDate'] . ',';
                        }
                    }
                }

            }
            if ($order_mode == HomePickUploadType::ORDER_MODE_HOMEPICK) {
                $unique_tracking = array_unique($unique_tracking);
                foreach ($unique_tracking as $key => $value) {
                    $tmp = explode('#', $value);
                    $tracking_number = explode(',', $tmp[1]);
                    $length = count($tracking_number);
                    $real_tracking_number .= $tmp[1] . ',';
                    for ($i = 0; $i < $length; $i++) {
                        $real_status .= $tmp[2] . ',';
                        $real_carrier_name .= $tmp[0] . ',';
                    }
                }
                $res['status'] = explode(',', trim($real_status, ','));
                $res['tracking_number'] = explode(',', trim($real_tracking_number, ','));
                $res['carrier_name'] = explode(',', trim($real_carrier_name, ','));
                $res['deal_file_name'] = explode(',', trim($real_deal_file_name, ','));
                $res['store_deal_file_name'] = explode(',', trim($real_store_deal_file_name, ','));
                $res['deal_file_path'] = array_unique(explode(',', trim($real_deal_file_path, ',')));
                $res['store_deal_file_path'] = array_unique(explode(',', trim($real_store_deal_file_path, ',')));
            } else {
                $res['status'] = explode(',', trim($real_status, ','));
                $res['tracking_number'] = explode(',', trim($real_tracking_number, ','));
                $res['carrier_name'] = explode(',', trim($real_carrier_name, ','));
                if ($ShipDeliveryDate != '') {
                    $res['ShipDeliveryDate'] = explode(',', trim($ShipDeliveryDate, ','));
                }
            }

        } else {
            $res['tracking_number'] = null;
            $res['carrier_name'] = null;
            $res['status'] = null;
            $res['deal_file_name'] = null;
            $res['deal_file_path'] = null;
            $res['ShipDeliveryDate'] = null;
        }
        return $res;

    }

    /**
     * [getPurchaseOrderInfoByLineId description] 通过采购订单ID来获取 13377
     * @param $line_id
     * @return bool
     * @throws Exception
     */
    public function getPurchaseOrderInfoByLineId($line_id)
    {
        $country_id = $this->customer->getCountryId();
        $customer_id = $this->customer->getId();
        //获取所有的采购订单的信息
        $tmp = $this->orm->table('tb_sys_order_associated as a')
            //->leftJoin('tb_sys_customer_sales_order_line as l','a.sales_order_line_id','=','l.id')
            ->leftJoin(DB_PREFIX . 'customerpartner_to_customer as c', 'c.customer_id', '=', 'a.seller_id') //店铺
            ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'a.product_id')
            ->leftJoin(DB_PREFIX . 'manufacturer as m', 'm.manufacturer_id', '=', 'p.manufacturer_id') // 品牌
            ->leftJoin(DB_PREFIX . 'order as o', 'a.order_id', '=', 'o.order_id')
            ->leftJoin(DB_PREFIX . 'order_product as op', function ($join) {
                $join->on('op.order_id', '=', 'a.order_id')->on('op.product_id', '=', 'a.product_id');
            })
            ->leftJoin(DB_PREFIX . 'product_quote as pq', function ($join) {
                $join->on('pq.order_id', '=', 'a.order_id')->on('pq.product_id', '=', 'a.product_id');
            })
            ->where('a.sales_order_line_id', $line_id)
            ->where('a.buyer_id', $customer_id)
            ->select('a.qty', 'a.order_id as purchase_order_id', 'c.screenname', 'm.name', 'op.price as op_price', 'pq.price as pq_price', 'op.poundage', 'op.quantity as op_quantity', 'p.sku', 'op.service_fee_per', 'op.freight_per', 'op.package_fee', 'pq.amount_price_per', 'pq.amount_service_fee_per'
                , 'p.image', 'op.type_id', 'op.agreement_id', 'op.order_product_id', 'a.coupon_amount', 'a.campaign_amount')
            ->selectRaw(new Expression("IF(op.discount IS NULL, '', 100-op.discount) AS discountShow"))
            ->groupBy('a.id')
            ->get();
        $tmp = obj2array($tmp);
        //采购订单的价格需要通过国别来处理

        $isEurope = false;
        if ($this->country->isEuropeCountry($this->customer->getCountryId())) {
            $isEurope = true;
        }

        $this->load->model('tool/image');
        $isCollectionFromDomicile = $this->customer->isCollectionFromDomicile();
        if ($tmp) {
            $sum = 0;
            $discountAmount = 0;
            foreach ($tmp as $k => $v) {
                // 根据agreement_id 和 agreement_code
                $tmp[$k]['transaction_type'] = $this->getTransactionInfoByOrderProductId($v['order_product_id']);
                //                if($country_id == AMERICAN_COUNTRY_ID){
                //仅仅美国有议价
                $tmp[$k]['unit_price'] = $v['op_price'] - $v['amount_price_per'];
                //                }else{
                //                    $tmp[$k]['unit_price'] = $v['op_price'] + sprintf('%.2f',$v['service_fee']/$v['op_quantity']);
                //                }
                if ($isCollectionFromDomicile) {
                    $tmp[$k]['freight_per'] = 0;
                }

                if ($isEurope) {
                    $service_fee_per = $v['service_fee_per'];
                    //获取discount后的 真正的service fee
                    $service_fee_total = ($service_fee_per - (float)$v['amount_service_fee_per']) * $v['qty'];
                    $service_fee_total_pre = ($service_fee_per - (float)$v['amount_service_fee_per']);

                } else {
                    $service_fee_total = 0;
                    $service_fee_total_pre = 0;
                }
                if ($v['amount_price_per'] != null && $v['amount_price_per'] != '0.00') {
                    $tmp[$k]['amount_price_per_show'] = $this->currency->formatCurrencyPrice(-$v['amount_price_per'], $this->session->data['currency']);
                } else {
                    $tmp[$k]['amount_price_per'] = 0;
                }
                if ($v['amount_service_fee_per'] != null && $v['amount_service_fee_per'] != '0.00') {
                    $tmp[$k]['amount_service_fee_per_show'] = $this->currency->formatCurrencyPrice(-$v['amount_service_fee_per'], $this->session->data['currency']);
                } else {
                    $tmp[$k]['amount_service_fee_per'] = 0;
                }
                $tmp[$k]['image'] = $this->model_tool_image->resize($v['image'], 40, 40);

                $tmp[$k]['service_fee'] = sprintf('%.2f', $service_fee_total_pre);
                $tmp[$k]['service_fee_show'] = $this->currency->formatCurrencyPrice($service_fee_total_pre, $this->session->data['currency']);
                $freight = $tmp[$k]['freight_per'] + $tmp[$k]['package_fee'];
                $tmp[$k]['package_fee_tips_show'] = $this->currency->formatCurrencyPrice($tmp[$k]['package_fee'], $this->session->data['currency']);
                $tmp[$k]['freight_tips_show'] = $this->currency->formatCurrencyPrice($tmp[$k]['freight_per'], $this->session->data['currency']);

                $tmp[$k]['freight_show'] = $this->currency->formatCurrencyPrice($freight, $this->session->data['currency']);
                $tmp[$k]['unit_price_show'] = $this->currency->formatCurrencyPrice($tmp[$k]['unit_price'], $this->session->data['currency']);
                $tmp[$k]['discount_amount'] = $v['coupon_amount'] + $v['campaign_amount'];
                $tmp[$k]['total_price'] = sprintf('%.2f', ($tmp[$k]['unit_price'] + $freight) * $v['qty'] + $service_fee_total);
                $tmp[$k]['total_price_show'] = $this->currency->formatCurrencyPrice($tmp[$k]['total_price'], $this->session->data['currency']);
                $tmp[$k]['final_total_price_show'] = $this->currency->formatCurrencyPrice($tmp[$k]['total_price'] - $tmp[$k]['discount_amount'], $this->session->data['currency']);
                $tmp[$k]['poundage'] = sprintf('%.2f', $tmp[$k]['poundage'] / $tmp[$k]['op_quantity'] * $v['qty']);
                $tmp[$k]['poundage_show'] = $this->currency->formatCurrencyPrice($tmp[$k]['poundage'], $this->session->data['currency']);

                $discountAmount += $v['coupon_amount'] + $v['campaign_amount'];
                $sum += $tmp[$k]['total_price'];
            }
            $res['purchase_list'] = $tmp;
            $res['discount_amount'] = $discountAmount;
            $res['total_price'] = $this->currency->formatCurrencyPrice($sum, $this->session->data['currency']);
            $res['final_total_price'] = $this->currency->formatCurrencyPrice($sum - $discountAmount, $this->session->data['currency']);
            $res['sum'] = $sum;
            return $res;

        } else {
            return false;
        }

    }

    public function getTrackingNumberInfoByOrderParam($param)
    {
        $allRes = [];
        $shipping_information = $this->orm->table('tb_sys_customer_sales_order_line as l')
            ->leftJoin('tb_sys_customer_sales_order as o', 'l.header_id', 'o.id')
            ->leftJoin('tb_sys_order_associated as a', 'l.id', 'a.sales_order_line_id')
            ->leftJoin('oc_order as oco', 'a.order_id', 'oco.order_id')
            ->leftJoin('tb_sys_customer_sales_order_other_temp as ot', 'ot.id', 'l.temp_id')
            ->leftJoin('tb_sys_customer_sales_dropship_temp as dt', 'dt.id', 'l.temp_id')
            ->leftJoin('tb_sys_customer_sales_wayfair_temp as wt', 'wt.id', 'l.temp_id')
            ->leftJoin('tb_sys_customer_sales_walmart_temp as walt', 'walt.id', 'l.temp_id')
            ->leftJoin('tb_sys_customer_sales_order_pick_up as pu', 'pu.sales_order_id', 'o.id')
            ->whereIn('o.id', $param)
            ->orderBy('order_id')
            ->groupBy(['l.id'])
            ->select(
                'o.order_id',
                'o.ship_method',
                'l.item_code as sku',
                'l.qty as line_qty',
                'l.id',
                'o.order_status',
                'o.is_international',
                'o.create_time',
                'o.ship_name',
                'o.ship_address1',
                'o.ship_city',
                'o.ship_city',
                'o.ship_zip_code',
                'o.ship_state',
                'o.ship_country',
                'o.import_mode',
                'ot.other_platform_id',
                'pu.warehouse_id as pu_warehouse_id',
                'ot.warehouse_name as other_warehouse',
                'dt.warehouse_name as amazon_warehouse',
                'wt.warehouse_name as wayfair_warehouse',
                'walt.warehouse_code as walmart_warehouse'
            )
            ->selectRaw('group_concat(oco.date_modified) as `date_modified`')
            ->get()
            ->map(function ($v) {
                $v->ship_name = app('db-aes')->decrypt($v->ship_name);
                $v->ship_address1 = app('db-aes')->decrypt($v->ship_address1);
                $v->ship_city = app('db-aes')->decrypt($v->ship_city);
                return (array)$v;
            })
            ->toArray();
        $sku = [];
        foreach ($shipping_information as $key => $value) {
            //验证是否为combo
            $product_id = $this->getFirstProductId($value['sku'], $this->customer->getId());
            $combo_flag = $this->orm->table(DB_PREFIX . 'product as ts')
                ->where('ts.product_id', $product_id)
                ->value('ts.combo_flag');
            if ($combo_flag) {
                if (isset($sku[$value['sku']])) {
                    $comboInfo = $sku[$value['sku']];
                } else {
                    $comboInfo = $this->orm->table('tb_sys_product_set_info as s')
                        ->where('p.product_id', $product_id)
                        ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 's.product_id')
                        ->leftJoin(DB_PREFIX . 'product as pc', 'pc.product_id', '=', 's.set_product_id')
                        ->whereNotNull('s.set_product_id')->select('p.product_id', 's.set_product_id', 's.qty', 'pc.sku')
                        ->get()
                        ->map(function ($v) {
                            return (array)$v;
                        })
                        ->toArray();
                    $sku[$value['sku']] = $comboInfo;
                }
            } else {
                $comboInfo = null;
            }

            if ($comboInfo) {
                $length = count($comboInfo);
                $mapExists['k.SalerOrderLineId'] = $value['id'];
                $mapExists['k.SalesOrderId'] = $value['order_id'];
                $mapExists['k.ShipSku'] = $value['sku'];
                $trackingComboExists = CustomerSalesOrderTracking::query()->alias('k')
                        ->where($mapExists)
                        ->exists();

                if ($trackingComboExists) {
                    // 此处需要区分是无parent sku的combo 还是单条的数据
                    foreach ($comboInfo as $k => $v) {
                        //首先获取tacking_number
                        if ($k == 0) {
                            $mapTrackingInfo['k.SalerOrderLineId'] = $value['id'];
                            $mapTrackingInfo['k.SalesOrderId'] = $value['order_id'];
                            $mapTrackingInfo['k.status'] = 1;
                            $tracking_info = CustomerSalesOrderTracking::query()->alias('k')
                                ->leftJoin('tb_sys_carriers as c', 'c.CarrierID', '=', 'k.LogisticeId')
                                ->where($mapTrackingInfo)
                                ->select(['k.TrackingNumber as tracking_number', 'k.status', 'k.ShipDeliveryDate'])
                                ->selectRaw('if(c.carrierName="Truck",k.ServiceLevelId,c.carrierName) AS carrier_name')
                                ->orderBy('k.status', 'desc')
                                ->get()
                                ->toArray();
                            unset($mapTrackingInfo);
                        } else {
                            $tracking_info = [];
                        }
                        $tracking_info = $this->dealTrackingNumberToShow($tracking_info, 1);
                        $shipping_information[$key]['tracking_number'] = $tracking_info['tracking_number'];
                        $shipping_information[$key]['carrier_name'] = $tracking_info['carrier_name'];
                        $shipping_information[$key]['tracking_status'] = $tracking_info['status'];
                        $shipping_information[$key]['ShipDeliveryDate'] = $tracking_info['ShipDeliveryDate'];
                        $shipping_information[$key]['child_sku'] = $v['sku'];
                        $shipping_information[$key]['all_qty'] = $v['qty'] * $value['line_qty'];
                        $shipping_information[$key]['child_qty'] = $v['qty'];
                        if ($k == 0) {
                            $shipping_information[$key]['cross_span'] = $length;
                        } else {
                            $shipping_information[$key]['cross_span'] = 0;
                        }
                        $allRes[] = $shipping_information[$key];
                    }
                } else {
                    foreach ($comboInfo as $k => $v) {
                        //首先获取tacking_number
                        $mapTrackingInfo['k.SalerOrderLineId'] = $value['id'];
                        $mapTrackingInfo['k.SalesOrderId'] = $value['order_id'];
                        $mapTrackingInfo['k.ShipSku'] = $v['sku'];
                        $mapTrackingInfo['k.status'] = 1;
                        $tracking_info = CustomerSalesOrderTracking::query()->alias('k')
                            ->leftJoin('tb_sys_carriers as c', 'c.CarrierID', '=', 'k.LogisticeId')
                            ->where($mapTrackingInfo)
                            ->select(['k.TrackingNumber as tracking_number', 'k.status', 'k.ShipDeliveryDate'])
                            ->selectRaw('if(c.carrierName="Truck",k.ServiceLevelId,c.carrierName) AS carrier_name')
                            ->orderBy('k.status', 'desc')
                            ->get()
                            ->toArray();
                        unset($mapTrackingInfo);
                        $tracking_info = $this->dealTrackingNumberToShow($tracking_info, 1);
                        $shipping_information[$key]['tracking_number'] = $tracking_info['tracking_number'];
                        $shipping_information[$key]['carrier_name'] = $tracking_info['carrier_name'];
                        $shipping_information[$key]['tracking_status'] = $tracking_info['status'];
                        $shipping_information[$key]['ShipDeliveryDate'] = $tracking_info['ShipDeliveryDate'];
                        $shipping_information[$key]['child_sku'] = $v['sku'];
                        $shipping_information[$key]['all_qty'] = $v['qty'] * $value['line_qty'];
                        $shipping_information[$key]['child_qty'] = $v['qty'];
                        $shipping_information[$key]['cross_span'] = 1;
                        $allRes[] = $shipping_information[$key];
                    }
                }

            } else {
                //获取tracking_number
                $mapTrackingInfo['k.SalerOrderLineId'] = $value['id'];
                $mapTrackingInfo['k.SalesOrderId'] = $value['order_id'];
                $mapTrackingInfo['k.ShipSku'] = $value['sku'];
                $mapTrackingInfo['k.status'] = 1;
                $tracking_info = CustomerSalesOrderTracking::query()->alias('k')
                    ->leftJoin('tb_sys_carriers as c', 'c.CarrierID', '=', 'k.LogisticeId')
                    ->where($mapTrackingInfo)
                    ->select(['k.TrackingNumber as tracking_number', 'k.status', 'k.ShipDeliveryDate'])
                    ->selectRaw('if(c.carrierName="Truck",k.ServiceLevelId,c.carrierName) AS carrier_name')
                    ->orderBy('k.status', 'desc')
                    ->get()
                    ->toArray();
                $tracking_info = $this->dealTrackingNumberToShow($tracking_info, 1);
                unset($mapTrackingInfo);
                $shipping_information[$key]['tracking_number'] = $tracking_info['tracking_number'];
                $shipping_information[$key]['carrier_name'] = $tracking_info['carrier_name'];
                $shipping_information[$key]['tracking_status'] = $tracking_info['status'];
                $shipping_information[$key]['ShipDeliveryDate'] = $tracking_info['ShipDeliveryDate'];
                $shipping_information[$key]['child_sku'] = null;
                $shipping_information[$key]['all_qty'] = null;
                $shipping_information[$key]['child_qty'] = null;
                $shipping_information[$key]['cross_span'] = 1;
                $allRes[] = $shipping_information[$key];
            }
        }
        return $allRes;
    }

    private function dealHomePickStringToArray(string $string)
    {
        $ret = [];
        $arr = explode(';', $string);
        foreach ($arr as $key => $value) {
            if ($value) {
                $tmp = explode(':', $value);
                $ret[current($tmp)] = end($tmp);
            }
        }
        return $ret;
    }

    private function setHomePickRequestParam(array $param)
    {
        $validation = [
            'file_str',
            'bol_str',
            'store_input_str',
        ];

        $ret = [];
        foreach ($validation as $items) {
            if (isset($param[$items])) {
                $ret[] = $this->dealHomePickStringToArray($param[$items]);
            } else {
                $ret[] = [];
            }
        }
        return $ret;

    }

    private function setHomePickLabelDetailsData($data)
    {
        /** var HomePickUploadFile $file_info  */
        [
            $file_info,
            $commercial_invoice_file_name,
            $commercial_invoice_file_path,
            $store_id_img,
            $package_asn_img,
            $store_deal_file_path,
            $store_deal_file_name,
            $store_order_id_img,
        ] = $data;

        return [
            'file_name' => $file_info->file_name,
            'file_path' => $file_info->file_path,
            'deal_file_name' => $file_info->file_name,
            'deal_file_path' => $file_info->deal_file_path,
            'commercial_invoice_file_name' => $commercial_invoice_file_name,
            'commercial_invoice_file_path' => $commercial_invoice_file_path,
            'tracking_number_img' => $file_info->tracking_number_img,
            'order_id_img' => $file_info->order_id_img,
            'weight_img' => $file_info->weight_img,
            'store_id_img' => $store_id_img,
            'package_asn_img' => $package_asn_img,
            'store_deal_file_path' => $store_deal_file_path,
            'store_deal_file_name' => $store_deal_file_name,
            'store_order_id_img' => $store_order_id_img,
            'label_type' => $file_info->label_type,
            'update_user_name' => customer()->getId(),
            'update_time' => date('Y-m-d H:i:s'),
        ];
    }

    public function wayfairSingleFileUploadDetails($map, $upload_file_info, $edit_flag = 0, $importMode = 5, $country_id = AMERICAN_COUNTRY_ID, $run_id = 0)
    {
        $labels = 0;
        load()->model('tool/pdf');
        [$file_arr, $bol_arr, $store_arr] = $this->setHomePickRequestParam($map);
        //importMode = 5 7 bol 要生成
        //新增美国上门取货other导单
        // store label
        if ($importMode != HomePickImportMode::IMPORT_MODE_WALMART) {
            $store_arr = [];
        }
        foreach ($upload_file_info as $key => $value) {
            //获取订单id
            $order_id = $value['order_id'];
            //美国上门取货的other导单
            if ($importMode == HomePickImportMode::US_OTHER && $country_id == AMERICAN_COUNTRY_ID) {
                //将label审核状态变为未审核
                $this->updateLabelView(
                    ['order_id' => $value['id']],
                    ['status' => HomePickLabelReviewStatus::APPLIED]
                );
            }

            foreach ($value['childrenList'] as $k => $v) {
                // 验证walmart store label 有的情况下需要填写
                if (
                    $importMode == HomePickImportMode::IMPORT_MODE_WALMART
                    && isset($v['store_label_container_id'])
                ) {
                    $labels++;
                    $mapFilePath = [
                        ['container_id', '=', $v['store_label_container_id']],
                        ['status', '=', 1],
                    ];
                    $store_label_info = HomePickUploadFile::query()
                        ->where($mapFilePath)
                        ->orderBy('id', 'desc')
                        ->first();
                }

                $store_id_img = $store_label_info->store_id_img ?? null;
                $store_order_id_img = $store_label_info->order_id_img ?? null;
                $package_asn_img = $store_label_info->package_asn_img ?? null;
                $store_deal_file_path = $store_label_info->store_deal_file_path ?? null;
                $store_deal_file_name = $store_label_info->store_deal_file_name ?? null;
                foreach ($v['combo_info'] as $ks => $vs) {
                    if ($edit_flag == 0) {
                        //数据更新
                        if ($v['is_combo'] == 0) {
                            if ($importMode == HomePickImportMode::US_OTHER
                                && $country_id == AMERICAN_COUNTRY_ID) {
                                if (!isset($file_arr[$vs['container_id']])) {
                                    $vs['container_id'] = $vs['container_packing_slip_id'];
                                }
                            }
                            if (isset($file_arr[$vs['container_id']])
                                || isset($map[$vs['container_id'].'_invoice'])
                            ) {
                                $mapFilePath = [
                                    ['container_id', '=', $vs['container_id']],
                                    ['status', '=', 1],
                                ];
                                $file_info = HomePickUploadFile::query()
                                    ->where($mapFilePath)
                                    ->orderBy('id', 'desc')
                                    ->first();

                                if ($importMode == HomePickImportMode::IMPORT_MODE_WAYFAIR
                                    && $country_id == AMERICAN_COUNTRY_ID) {
                                    $tracking_num = $file_info->tracking_number;
                                } elseif ($importMode == HomePickImportMode::IMPORT_MODE_WAYFAIR
                                    && in_array($country_id, EUROPE_COUNTRY_ID)
                                ) {
                                    $tracking_num = $map[$vs['container_id'] . '_tracking_number'];
                                } elseif ($importMode == HomePickImportMode::IMPORT_MODE_AMAZON) {
                                    $tracking_num = $map[$vs['container_id'] . '_input'];
                                } else {
                                    $tracking_num = $map[$vs['container_id'] . '_tracking_number'];
                                }

                                [$commercial_invoice_file_name, $commercial_invoice_file_path] = $this->getCommercialInvoiceInfos($vs['container_id'], $importMode, $map);
                                $filter_data = $this->setHomePickLabelDetailsData([
                                    $file_info,
                                    $commercial_invoice_file_name,
                                    $commercial_invoice_file_path,
                                    $store_id_img,
                                    $package_asn_img,
                                    $store_deal_file_path,
                                    $store_deal_file_name,
                                    $store_order_id_img,
                                ]);
                                $filter_data['tracking_number'] = trim($tracking_num);
                                $labels++;
                                HomePickLabelDetails::query()
                                    ->where('id', $vs['file_id'])
                                    ->update($filter_data);
                                unset($filter_data);
                            }
                        } else {
                            //combo 品
                            foreach ($vs['line'] as $kline => $vline) {
                                if ($importMode == HomePickImportMode::US_OTHER
                                    && $country_id == AMERICAN_COUNTRY_ID) {
                                    if (!isset($file_arr[$vline['container_id']])) {
                                        $vline['container_id'] = $vline['container_packing_slip_id'];
                                    }
                                }

                                if (isset($file_arr[$vline['container_id']])
                                    || isset($map[$vline['container_id'].'_invoice'])
                                ) {
                                    $mapFilePath = [
                                        ['container_id', '=', $vline['container_id']],
                                        ['status', '=', 1],
                                    ];
                                    $file_info = HomePickUploadFile::query()
                                        ->where($mapFilePath)
                                        ->orderBy('id', 'desc')
                                        ->first();
                                    [$commercial_invoice_file_name, $commercial_invoice_file_path] = $this->getCommercialInvoiceInfos($vline['container_id'], $importMode, $map);
                                    $filter_data = $this->setHomePickLabelDetailsData([
                                        $file_info,
                                        $commercial_invoice_file_name,
                                        $commercial_invoice_file_path,
                                        $store_id_img,
                                        $package_asn_img,
                                        $store_deal_file_path,
                                        $store_deal_file_name,
                                        $store_order_id_img,
                                    ]);

                                    if ($importMode == HomePickImportMode::IMPORT_MODE_WAYFAIR) {
                                        $filter_data['tracking_number'] = $file_info->tracking_number;
                                    }
                                }
                                //存在可能性只更改tracking 不更改label
                                if ($importMode == HomePickImportMode::IMPORT_MODE_AMAZON) {
                                    $filter_data['tracking_number'] = trim($map[$vline['container_id'] . '_input']);
                                } elseif ($importMode == HomePickImportMode::IMPORT_MODE_WAYFAIR
                                    && in_array($country_id, EUROPE_COUNTRY_ID)) {
                                    $filter_data['tracking_number'] = trim($map[$vline['container_id'] . '_tracking_number']);
                                } elseif ($importMode == HomePickImportMode::IMPORT_MODE_WALMART || ($importMode == HomePickImportMode::US_OTHER && $country_id == AMERICAN_COUNTRY_ID)) {//新增美国上门取货other导单
                                    $filter_data['tracking_number'] = trim($map[$vline['container_id'] . '_tracking_number']);
                                }
                                $labels++;
                                if(isset($filter_data)){
                                    HomePickLabelDetails::query()
                                        ->where('id', $vline['file_id'])
                                        ->update($filter_data);
                                }
                                unset($filter_data);
                            }
                        }


                    } elseif ($edit_flag == 1) {

                        if ($v['is_combo'] == 0) {
                            if ($importMode == HomePickImportMode::US_OTHER
                                && $country_id == AMERICAN_COUNTRY_ID) {
                                if (!isset($file_arr[$vs['container_id']])) {
                                    $vs['container_id'] = $vs['container_packing_slip_id'];
                                }
                            }
                            $mapFilePath = [
                                ['container_id', '=', $vs['container_id']],
                                ['status', '=', 1],
                            ];
                            $file_info = HomePickUploadFile::query()
                                ->where($mapFilePath)
                                ->orderBy('id', 'desc')
                                ->first();

                            if ($importMode == HomePickImportMode::IMPORT_MODE_WAYFAIR
                                && $country_id == AMERICAN_COUNTRY_ID) {
                                $tracking_num = $file_info->tracking_number;
                            } elseif ($importMode == HomePickImportMode::IMPORT_MODE_WAYFAIR
                                && in_array($country_id, EUROPE_COUNTRY_ID)) {
                                $tracking_num = $map[$vs['container_id'] . '_tracking_number'];
                            } elseif ($importMode == HomePickImportMode::IMPORT_MODE_AMAZON) {
                                $tracking_num = $map[$vs['container_id'] . '_input'];
                            } else {
                                $tracking_num = $map[$vs['container_id'] . '_tracking_number'];
                            }
                            [$commercial_invoice_file_name, $commercial_invoice_file_path] = $this->getCommercialInvoiceInfos($vs['container_id'], $importMode, $map);
                            $filter_data = [
                                'line_id' => $v['line_id'],
                                'temp_id' => $v['temp_id'],
                                'order_id' => $value['id'],
                                'is_combo' => 0,
                                'sku' => $v['item_code'],
                                'qty' => $v['qty'],
                                'line_item_number' => $vs['key'],
                                'tracking_number' => trim($tracking_num),
                                'status' => 1,
                                'create_user_name' => customer()->getId(),
                                'create_time' => date('Y-m-d H:i:s'),
                                'program_code' => PROGRAM_CODE,
                                'run_id' => $run_id,
                            ];
                            $filter_data = array_merge($this->setHomePickLabelDetailsData([
                                $file_info,
                                $commercial_invoice_file_name,
                                $commercial_invoice_file_path,
                                $store_id_img,
                                $package_asn_img,
                                $store_deal_file_path,
                                $store_deal_file_name,
                                $store_order_id_img,
                            ]), $filter_data);
                            $labels++;
                            HomePickLabelDetails::query()->insert($filter_data);
                            unset($filter_data);
                        } else {
                            //combo 品
                            foreach ($vs['line'] as $kline => $vline) {
                                //other 平台需要判断是否有container_id 不同的上传
                                if ($importMode == HomePickImportMode::US_OTHER
                                    && $country_id == AMERICAN_COUNTRY_ID) {
                                    if (!isset($file_arr[$vline['container_id']])) {
                                        $vline['container_id'] = $vline['container_packing_slip_id'];
                                    }
                                }
                                $mapFilePath = [
                                    ['container_id', '=', $vline['container_id']],
                                    ['status', '=', 1],
                                ];
                                $file_info = HomePickUploadFile::query()
                                    ->where($mapFilePath)
                                    ->orderBy('id', 'desc')
                                    ->first();
                                if ($importMode == HomePickImportMode::IMPORT_MODE_WAYFAIR && $country_id == AMERICAN_COUNTRY_ID) {
                                    $tracking_num = $file_info->tracking_number;
                                } elseif ($importMode == HomePickImportMode::IMPORT_MODE_WAYFAIR && in_array($country_id, EUROPE_COUNTRY_ID)) {
                                    $tracking_num = $map[$vline['container_id'] . '_tracking_number'];
                                } elseif ($importMode == HomePickImportMode::IMPORT_MODE_AMAZON) {
                                    $tracking_num = $map[$vline['container_id'] . '_input'];
                                } else {
                                    $tracking_num = $map[$vline['container_id'] . '_tracking_number'];
                                }
                                [$commercial_invoice_file_name, $commercial_invoice_file_path] = $this->getCommercialInvoiceInfos($vline['container_id'], $importMode, $map);
                                $filter_data = [
                                    'line_id' => $v['line_id'],
                                    'temp_id' => $v['temp_id'],
                                    'order_id' => $value['id'],
                                    'is_combo' => 1,
                                    'sku' => $v['item_code'],
                                    'set_product_id' => $vs['set_product_id'],
                                    'combo_sort' => $ks + 1,
                                    'default_qty' => $vs['default_qty'],
                                    'qty' => $v['qty'],
                                    'line_item_number' => $vline['key'],
                                    'tracking_number' => trim($tracking_num),
                                    'status' => 1,
                                    'create_user_name' => $this->customer->getId(),
                                    'create_time' => date('Y-m-d H:i:s', time()),
                                    'program_code' => PROGRAM_CODE,
                                    'run_id' => $run_id,
                                ];
                                $filter_data = array_merge($this->setHomePickLabelDetailsData([
                                    $file_info,
                                    $commercial_invoice_file_name,
                                    $commercial_invoice_file_path,
                                    $store_id_img,
                                    $package_asn_img,
                                    $store_deal_file_path,
                                    $store_deal_file_name,
                                    $store_order_id_img,
                                ]), $filter_data);
                                $labels++;
                                HomePickLabelDetails::query()->insert($filter_data);
                                unset($filter_data);
                            }
                        }
                    }
                }
            }

            if ($edit_flag == 1) {
                //更新 order_status 和 item_status
                $mapOrderUpdate = [
                    ['order_id', '=', $order_id],
                    ['buyer_id', '=', $this->customer->getId()],
                ];
                //生成一个bol
                if ($importMode == HomePickImportMode::IMPORT_MODE_WAYFAIR) {
                    //更新bol到 tb_sys_customer_sales_order
                    if (isset($bol_arr[$value['id'] . '_bol'])) {
                        //查找 需要的 bol_pdf 地址
                        $mapFilePath = [
                            ['container_id', '=', $value['id'] . '_bol'], //唯一的
                            ['status', '=', 1],
                        ];
                        $bol_path = HomePickUploadFile::query()
                            ->where($mapFilePath)
                            ->orderBy('id', 'desc')
                            ->value('deal_file_path');
                        CustomerSalesOrder::query()
                            ->where($mapOrderUpdate)
                            ->update(
                                [
                                    'order_status' => CustomerSalesOrderStatus::TO_BE_PAID,
                                    'bol_path' => $bol_path,
                                    'bol_create_time' => date('Y-m-d H:i:s', time()),
                                    'bol_create_id' => $this->customer->getId(),
                                ]
                            );
                    }

                } elseif ($importMode == HomePickImportMode::IMPORT_MODE_WALMART
                    || ($importMode == HomePickImportMode::US_OTHER && $country_id == AMERICAN_COUNTRY_ID)
                ) {//新增美国上门取货other导单

                    //查找 需要的 bol_pdf 地址
                    if (isset($bol_arr[$value['id'] . '_bol'])) {
                        $mapFilePath = [
                            ['container_id', '=', $value['id'] . '_bol'], //唯一的
                            ['status', '=', 1],
                        ];
                        $bol_path = HomePickUploadFile::query()
                            ->where($mapFilePath)
                            ->orderBy('id', 'desc')
                            ->limit(1)->value('deal_file_path');
                        CustomerSalesOrder::query()
                            ->where($mapOrderUpdate)
                            ->update(
                                [
                                    'order_status' => CustomerSalesOrderStatus::TO_BE_PAID,
                                    'bol_path' => $bol_path,
                                    'bol_create_time' => date('Y-m-d H:i:s', time()),
                                    'bol_create_id' => $this->customer->getId(),
                                ]
                            );
                        $labels++;
                    }

                    // 更改label_review 为 0
                    $this->updateLabelView(
                        ['order_id' => $value['id']],
                        ['status' => HomePickLabelReviewStatus::APPLIED]
                    );

                } else {

                    CustomerSalesOrder::query()->where($mapOrderUpdate)->update(['order_status' => CustomerSalesOrderStatus::TO_BE_PAID]);
                    $this->model_tool_pdf->setDropshipBolData($value['id']);

                }

                $mapOrderLineUpdate = [
                    ['o.order_id', '=', $order_id],
                    ['o.create_user_name', '=', $this->customer->getId()],
                ];
                CustomerSalesOrderLine::query()->alias('l')
                    ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
                    ->where($mapOrderLineUpdate)
                    ->update(['l.item_status' => CustomerSalesOrderLineItemStatus::PENDING]);
            } else {

                $mapOrderUpdate = [
                    ['order_id', '=', $order_id],
                    ['buyer_id', '=', $this->customer->getId()],
                ];
                //生成一个bol
                if ($importMode == HomePickImportMode::IMPORT_MODE_WAYFAIR) {
                    //更新bol到 tb_sys_customer_sales_order
                    if (isset($bol_arr[$value['id'] . '_bol'])) {
                        //查找 需要的 bol_pdf 地址
                        $mapFilePath = [
                            ['container_id', '=', $value['id'] . '_bol'], //唯一的
                            ['status', '=', 1],
                        ];
                        $bol_path = HomePickUploadFile::query()
                            ->where($mapFilePath)
                            ->orderBy('id', 'desc')
                            ->value('deal_file_path');
                        CustomerSalesOrder::query()
                            ->where($mapOrderUpdate)
                            ->update(
                                [
                                    'bol_path' => $bol_path,
                                    'bol_create_time' => date('Y-m-d H:i:s', time()),
                                    'bol_create_id' => $this->customer->getId(),
                                ]
                            );
                    }
                } elseif ($importMode == HomePickImportMode::IMPORT_MODE_WALMART
                    || ($importMode == HomePickImportMode::US_OTHER && $country_id == AMERICAN_COUNTRY_ID)) {   //新增美国上门取货other导单
                    //查找 需要的 bol_pdf 地址
                    if (isset($bol_arr[$value['id'] . '_bol'])) {
                        $mapFilePath = [
                            ['container_id', '=', $value['id'] . '_bol'], //唯一的
                            ['status', '=', 1],
                        ];
                        $bol_path = HomePickUploadFile::query()
                            ->where($mapFilePath)
                            ->orderBy('id', 'desc')
                            ->value('deal_file_path');
                        $update_order_item = [
                            'order_status' => CustomerSalesOrderStatus::TO_BE_PAID,
                            'bol_path' => $bol_path,
                            'bol_create_time' => date('Y-m-d H:i:s', time()),
                            'bol_create_id' => $this->customer->getId(),
                        ];
                        //美国上门取货的other导单
                        if ($importMode == HomePickImportMode::US_OTHER
                            && $country_id == AMERICAN_COUNTRY_ID) {
                            //保持原来的状态
                            $update_order_item = [
                                'bol_path' => $bol_path,
                                'bol_create_time' => date('Y-m-d H:i:s', time()),
                                'bol_create_id' => $this->customer->getId(),
                            ];
                        }
                        CustomerSalesOrder::query()
                            ->where($mapOrderUpdate)
                            ->update($update_order_item);
                        $labels++;
                    }

                } else {
                    $this->model_tool_pdf->setDropshipBolData($value['id']);
                }
            }
        }
        return $labels;

    }

    public function updateLabelView($map, $condition)
    {
        $this->orm->table('tb_sys_customer_sales_order_label_review')
            ->where($map)
            ->update($condition);
    }


    /**
     * [dropshipFileUploadDetails description] 文件上传详情
     * @param $map
     * @param $upload_file_info
     * @param int $importMode
     * @param int $country_id
     * @return string
     * @throws Exception
     * @throws \League\Flysystem\FilesystemException
     */
    public function dropshipFileUploadDetails($map, $upload_file_info, $importMode = HomePickImportMode::IMPORT_MODE_AMAZON, $country_id = AMERICAN_COUNTRY_ID)
    {
        $labels = 0;
        load()->model('tool/pdf');
        [$file_arr, $bol_arr, $store_arr] = $this->setHomePickRequestParam($map);
        //importMode = 5 bol 要生成
        // store label
        if ($importMode != HomePickImportMode::IMPORT_MODE_WALMART) {
            $store_arr = [];
        }
        foreach ($upload_file_info as $key => $value) {
            //获取订单id
            $order_id = $value['order_id'];
            // 销售单的id
            $id = $value['id'];
            if (isset($map[$id])) {
                if ($map[$id] == 'on') {
                    foreach ($value['childrenList'] as $k => $v) {
                        // 验证walmart store label 有的情况下需要填写
                        if ($importMode == HomePickImportMode::IMPORT_MODE_WALMART && isset($v['store_label_container_id'])) {
                            $labels++;
                            $mapFilePath = [
                                ['container_id', '=', $v['store_label_container_id']],
                                ['status', '=', 1],
                            ];
                            $store_label_info = HomePickUploadFile::query()
                                ->where($mapFilePath)
                                ->orderBy('id', 'desc')
                                ->limit(1)
                                ->first();
                        }
                        $store_id_img = $store_label_info->store_id_img ?? null;
                        $package_asn_img = $store_label_info->package_asn_img ?? null;
                        $store_order_id_img = $store_label_info->store_deal_file_path ?? null;
                        $store_deal_file_path = $store_label_info->store_deal_file_path ?? null;
                        $store_deal_file_name = $store_label_info->store_deal_file_name ?? null;

                        foreach ($v['combo_info'] as $ks => $vs) {
                            if ($v['is_combo'] == 0) {
                                //获取file_path
                                if ($importMode == HomePickImportMode::US_OTHER && $country_id == AMERICAN_COUNTRY_ID) {
                                    if (!isset($file_arr[$vs['container_id']])) {
                                        $vs['container_id'] = $vs['container_packing_slip_id'];
                                    }
                                }
                                $mapFilePath = [
                                    ['container_id', '=', $vs['container_id']], //唯一的
                                    ['status', '=', 1],
                                ];
                                $file_info = HomePickUploadFile::query()
                                    ->where($mapFilePath)
                                    ->orderBy('id', 'desc')
                                    ->first();
                                if ($importMode == HomePickImportMode::IMPORT_MODE_WAYFAIR
                                    && $country_id == AMERICAN_COUNTRY_ID) {
                                    $tracking_num = $file_info->tracking_number;
                                } elseif ($importMode == HomePickImportMode::IMPORT_MODE_WAYFAIR
                                    && in_array($country_id, EUROPE_COUNTRY_ID)
                                ) {
                                    $tracking_num = $map[$vs['container_id'] . '_tracking_number'];
                                } elseif ($importMode == HomePickImportMode::IMPORT_MODE_AMAZON) {
                                    $tracking_num = $map[$vs['container_id'] . '_input'];
                                } else {
                                    $tracking_num = $map[$vs['container_id'] . '_tracking_number'];
                                }
                                [$commercial_invoice_file_name, $commercial_invoice_file_path] = $this->getCommercialInvoiceInfos($vs['container_id'], $importMode, $map);
                                $filter_data = [
                                    'line_id' => $v['line_id'],
                                    'temp_id' => $v['temp_id'],
                                    'order_id' => $value['id'],
                                    'is_combo' => 0,
                                    'sku' => $v['item_code'],
                                    'qty' => $v['qty'],
                                    'line_item_number' => $vs['key'],
                                    'tracking_number' => trim($tracking_num),
                                    'status' => 1,
                                    'create_user_name' => $this->customer->getId(),
                                    'create_time' => date('Y-m-d H:i:s', time()),
                                    'program_code' => PROGRAM_CODE,
                                    'run_id' => $this->request->get['runId'],
                                ];
                                $filter_data = array_merge($this->setHomePickLabelDetailsData([
                                    $file_info,
                                    $commercial_invoice_file_name,
                                    $commercial_invoice_file_path,
                                    $store_id_img,
                                    $package_asn_img,
                                    $store_deal_file_path,
                                    $store_deal_file_name,
                                    $store_order_id_img,
                                ]), $filter_data);
                                $labels++;
                                HomePickLabelDetails::query()->insert($filter_data);

                            } else {
                                //combo 品
                                foreach ($vs['line'] as $kline => $vline) {

                                    if ($importMode == HomePickImportMode::US_OTHER && $country_id == AMERICAN_COUNTRY_ID) {
                                        if (!isset($file_arr[$vline['container_id']])) {
                                            $vline['container_id'] = $vline['container_packing_slip_id'];
                                        }
                                    }
                                    $file_name = $file_arr[$vline['container_id']];
                                    $mapFilePath = [
                                        ['container_id', '=', $vline['container_id']],
                                        ['status', '=', 1],
                                    ];
                                    $file_info = HomePickUploadFile::query()
                                        ->where($mapFilePath)
                                        ->orderBy('id', 'desc')
                                        ->first();
                                    if ($importMode == HomePickImportMode::IMPORT_MODE_WAYFAIR
                                        && $country_id == AMERICAN_COUNTRY_ID) {
                                        $tracking_num = $file_info->tracking_number;
                                    } elseif ($importMode == HomePickImportMode::IMPORT_MODE_WAYFAIR
                                        && in_array($country_id, EUROPE_COUNTRY_ID)
                                    ) {
                                        $tracking_num = $map[$vline['container_id'] . '_tracking_number'];
                                    } elseif ($importMode == HomePickImportMode::IMPORT_MODE_AMAZON) {
                                        $tracking_num = $map[$vline['container_id'] . '_input'];
                                    } else {
                                        $tracking_num = $map[$vline['container_id'] . '_tracking_number'];
                                    }
                                    [$commercial_invoice_file_name, $commercial_invoice_file_path] = $this->getCommercialInvoiceInfos($vline['container_id'], $importMode, $map);
                                    $filter_data = [
                                        'line_id' => $v['line_id'],
                                        'temp_id' => $v['temp_id'],
                                        'order_id' => $value['id'],
                                        'is_combo' => 1,
                                        'sku' => $v['item_code'],
                                        'set_product_id' => $vs['set_product_id'],
                                        'combo_sort' => $ks + 1,
                                        'default_qty' => $vs['default_qty'],
                                        'qty' => $v['qty'],
                                        'line_item_number' => $vline['key'],
                                        'tracking_number' => trim($tracking_num),
                                        'status' => 1,
                                        'create_user_name' => $this->customer->getId(),
                                        'create_time' => date('Y-m-d H:i:s', time()),
                                        'program_code' => PROGRAM_CODE,
                                        'run_id' => $this->request->get['runId'],
                                    ];
                                    $filter_data = array_merge($this->setHomePickLabelDetailsData([
                                        $file_info,
                                        $commercial_invoice_file_name,
                                        $commercial_invoice_file_path,
                                        $store_id_img,
                                        $package_asn_img,
                                        $store_deal_file_path,
                                        $store_deal_file_name,
                                        $store_order_id_img,
                                    ]), $filter_data);
                                    $labels++;
                                    HomePickLabelDetails::query()->insert($filter_data);

                                }
                            }
                        }
                    }
                    //更新 order_status 和 item_status
                    $mapOrderUpdate = [
                        ['order_id', '=', $order_id],
                        ['buyer_id', '=', $this->customer->getId()],
                    ];
                    $mapOrderLineUpdate = [
                        ['o.order_id', '=', $order_id],
                        ['o.buyer_id', '=', $this->customer->getId()],
                    ];
                    CustomerSalesOrderLine::query()->alias('l')
                        ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
                        ->where($mapOrderLineUpdate)
                        ->update(['l.item_status' => CustomerSalesOrderLineItemStatus::PENDING]);
                    //生成一个bol
                    if ($importMode == HomePickImportMode::IMPORT_MODE_AMAZON) {
                        CustomerSalesOrder::query()
                            ->where($mapOrderUpdate)
                            ->update([
                                'order_status' => CustomerSalesOrderStatus::TO_BE_PAID,
                                'to_be_paid_time' => Carbon::now()->toDateTimeString()
                            ]);
                        $this->model_tool_pdf->setDropshipBolData($id);
                    } elseif ($importMode == HomePickImportMode::IMPORT_MODE_WAYFAIR) {
                        //更新bol到 tb_sys_customer_sales_order
                        if (in_array($country_id, EUROPE_COUNTRY_ID)) {
                            $verify_flag = $this->verifyHasManifestFile($id);
                            if ($verify_flag) {
                                CustomerSalesOrder::query()
                                    ->where($mapOrderUpdate)
                                    ->update([
                                        'order_status' => CustomerSalesOrderStatus::TO_BE_PAID,
                                        'to_be_paid_time' => Carbon::now()->toDateTimeString()
                                    ]);
                            }
                        } else {
                            if (isset($bol_arr[$id . '_bol'])) {
                                //查找 需要的 bol_pdf 地址
                                $mapFilePath = [
                                    ['container_id', '=', $id . '_bol'], //唯一的
                                    ['status', '=', 1],
                                ];
                                $bol_path = HomePickUploadFile::query()
                                    ->where($mapFilePath)
                                    ->orderBy('id', 'desc')
                                    ->value('deal_file_path');
                                CustomerSalesOrder::query()
                                    ->where($mapOrderUpdate)
                                    ->update(
                                        [
                                            'order_status' => CustomerSalesOrderStatus::TO_BE_PAID,
                                            'to_be_paid_time' => Carbon::now()->toDateTimeString(),
                                            'bol_path' => $bol_path,
                                            'bol_create_time' => date('Y-m-d H:i:s', time()),
                                            'bol_create_id' => $this->customer->getId(),
                                        ]
                                    );
                            } else {
                                CustomerSalesOrder::query()->where($mapOrderUpdate)->update(['order_status' => CustomerSalesOrderStatus::TO_BE_PAID, 'to_be_paid_time' => Carbon::now()->toDateTimeString()]);
                            }
                        }
                        unset($mapOrderUpdate);
                    } elseif ($importMode == HomePickImportMode::IMPORT_MODE_WALMART || ($importMode == HomePickImportMode::US_OTHER && $country_id == AMERICAN_COUNTRY_ID)) {//新增美国上门取货other导单
                        //查找 需要的 bol_pdf 地址
                        if (isset($bol_arr[$id . '_bol'])) {
                            $mapFilePath = [
                                ['container_id', '=', $value['id'] . '_bol'], //唯一的
                                ['status', '=', YesNoEnum::YES],
                            ];
                            $bol_path = HomePickUploadFile::query()
                                ->where($mapFilePath)
                                ->orderBy('id', 'desc')
                                ->value('deal_file_path');
                            CustomerSalesOrder::query()
                                ->where($mapOrderUpdate)
                                ->update(
                                    [
                                        'order_status' => CustomerSalesOrderStatus::TO_BE_PAID,
                                        'to_be_paid_time' => Carbon::now()->toDateTimeString(),
                                        'bol_path' => $bol_path,
                                        'bol_create_time' => date('Y-m-d H:i:s', time()),
                                        'bol_create_id' => $this->customer->getId(),
                                    ]
                                );
                            $labels++;
                        } else {
                            CustomerSalesOrder::query()
                                ->where($mapOrderUpdate)
                                ->update([
                                    'order_status' => CustomerSalesOrderStatus::TO_BE_PAID,
                                    'to_be_paid_time' => Carbon::now()->toDateTimeString()
                                ]);
                        }

                        $this->updateLabelView(
                            ['order_id' => $id],
                            ['status' => HomePickLabelReviewStatus::APPLIED]
                        );
                        unset($mapOrderUpdate);
                    }
                }
            } else {
                //更新 order_status 和 item_status
                $mapOrderUpdate = [
                    ['order_id', '=', $order_id],
                    ['create_user_name', '=', $this->customer->getId()],
                ];
                CustomerSalesOrder::query()
                    ->where($mapOrderUpdate)
                    ->update(['order_status' => CustomerSalesOrderStatus::CHECK_LABEL]);
                $mapOrderLineUpdate = [
                    ['o.order_id', '=', $order_id],
                    ['o.create_user_name', '=', $this->customer->getId()],
                ];
                CustomerSalesOrderLine::query()->alias('l')
                    ->where($mapOrderLineUpdate)
                    ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
                    ->update(['l.item_status' => CustomerSalesOrderLineItemStatus::PENDING]);
            }

        }

        return $labels;
    }

    private function getCommercialInvoiceInfos($containerId, $importMode, $map): array
    {
        $suffix = '_invoice';
        $fileName = $filePath = null;
        if ($importMode == HomePickImportMode::IMPORT_MODE_WAYFAIR
            && customer()->getCountryId() == HomePickUploadType::GERMANY_COUNTRY_ID
            && (isset($map[$containerId . $suffix]) && $map[$containerId . $suffix])
        ) {

            $file_info = HomePickUploadFile::query()
                ->where([
                    'container_id' => $containerId . $suffix,
                    'status' => YesNoEnum::YES
                ])
                ->orderBy('id', 'desc')
                ->first();
            $fileName = $file_info->deal_file_name ?? null;
            $filePath = $file_info->deal_file_path ?? null;

        }

        return [$fileName, $filePath];
    }

    /**
     * [judgeIsDropShip description] dropship 判断
     * @param int $customer_id
     * @return int
     */
    public function judgeIsDropShip($customer_id)
    {
        //这个验证规则仅仅针对于美国dropship
        //现在要兼容英国dropship
        $group_id = $this->customer->getGroupId();
        $default_group_name = $this->orm->table(DB_PREFIX . 'customer_group_description')
            ->where('customer_group_id', $group_id)
            ->limit(1)
            ->value('name');
        if (in_array($default_group_name, ALL_DROPSHIP) == true) {
            return 1;
        }
        return 0;
    }

    /**
     * [getAllUnexportDropshipOrderInfo description] 所有未处理的订单
     * @param int $order_mode
     * @param int $country_id
     * @return array
     */
    public function getAllUnexportDropshipOrderInfo($order_mode, $country_id = AMERICAN_COUNTRY_ID)
    {
        $map = [
            ['o.order_mode', '=', $order_mode],
            ['o.order_status', '=', CustomerSalesOrderStatus::BEING_PROCESSED],  // bp
            ['c.country_id', '=', $country_id],
        ];
        $data = $this->orm->table('tb_sys_customer_sales_order as o')->where($map)->where(function ($query) use ($country_id) {
            //英国dropship 同步字段不同 is_synchroed 美国dropship  is_exported
            if ($country_id == AMERICAN_COUNTRY_ID) {
                $query->where('l.is_exported', '=', 3)->orWhereNull('l.is_exported');
            } elseif ($country_id == HomePickUploadType::BRITAIN_COUNTRY_ID) {
                $query->whereIn('l.is_synchroed', [2, 3])->orWhereNull('l.is_synchroed');
            } else {
                $query->where('l.is_exported', '=', 3)->orWhereNull('l.is_exported');
            }
        })->
        leftJoin('tb_sys_customer_sales_order_line as l', 'l.header_id', '=', 'o.id')->
        leftJoin(DB_PREFIX . 'customer as c', 'c.customer_id', '=', 'o.buyer_id')
            ->groupBy('o.id')->select('o.id', 'o.yzc_order_id', 'o.order_id')->get();
        $data = obj2array($data);
        foreach ($data as $key => $value) {
            $mapLine['l.header_id'] = $value['id'];
            $tmp = $this->orm->table('tb_sys_customer_sales_order_line as l')->where($mapLine)->select('id')->get();
            $tmp = obj2array($tmp);
            if ($tmp) {
                foreach ($tmp as $k => $v) {
                    $mapFile['d.line_id'] = $v['id'];
                    $tmpChild = $this->orm->table('tb_sys_customer_sales_dropship_file_details as d')->where($mapFile)->
                    leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 'd.set_product_id')->groupBy('d.id')->
                    select('d.line_item_number', 'd.tracking_number', 'd.deal_file_path')
                        ->selectRaw('IFNULL(p.sku,d.sku) as sku')->get();
                    $tmpChild = obj2array($tmpChild);
                    $tmp[$k]['file_info'] = $tmpChild;

                }
            }
            $data[$key]['line_info'] = $tmp;
        }
        if (empty($data))
            return null;


        return $data;


    }

    /**
     * [getSingleDropshipChildListComboInfo description]
     * @param array $data
     * @param int $order_id tb_sys_customer_sales_order表的id
     * @param array $container_id_list
     * @return array
     */
    public function getSingleDropshipChildListComboInfo($data, $order_id, &$container_id_list)
    {
        $country_id = $this->customer->getCountryId();
        $count = 0;
        $container_arr = [];
        foreach ($data as $key => $value) {
            $comboInfo = $this->getComboInfoBySku($value['item_code']);
            if (!$comboInfo) {
                //非combo
                //获取tracking_number file_name
                //验证 line id
                $data[$key]['is_combo'] = 0;
                for ($i = 0; $i < (int)$value['qty']; $i++) {

                    $tracking_id_list = explode('&', $value['tracking_id']);
                    $mapLineInfo = [
                        ['line_id', '=', $value['line_id']],
                        ['status', '=', 1],
                        ['line_item_number', '=', $i + 1],
                    ];
                    $line_file_info = $this->orm->table('tb_sys_customer_sales_dropship_file_details')->where($mapLineInfo)->first();
                    $line_file_info = obj2array($line_file_info);

                    $count++;
                    $data[$key]['combo_info'][$i]['key'] = $i + 1;
                    $data[$key]['combo_info'][$i]['container_id'] = $order_id . '_' . $key . '_' . $value['temp_id'] . '_' . ($i + 1);
                    $container_arr[] = $data[$key]['combo_info'][$i]['container_id'];
                    if ($line_file_info) {
                        $data[$key]['combo_info'][$i]['tracking_id'] = $line_file_info['tracking_number'];
                        $data[$key]['combo_info'][$i]['file_name'] = strlen($line_file_info['file_name']) > 30 ? substr($line_file_info['file_name'], 0, 30) . "..." : $line_file_info['file_name'];
                        $data[$key]['combo_info'][$i]['file_path'] = $line_file_info['file_path'];
                        $data[$key]['combo_info'][$i]['deal_file_name'] = strlen($line_file_info['deal_file_name']) > 30 ? substr($line_file_info['deal_file_name'], 0, 30) . "..." : $line_file_info['deal_file_name'];
                        $data[$key]['combo_info'][$i]['deal_file_path'] = $line_file_info['deal_file_path'];
                        $data[$key]['combo_info'][$i]['file_id'] = $line_file_info['id'];
                        $data[$key]['combo_info'][$i]['tracking_number_img'] = $line_file_info['tracking_number_img'];
                        $data[$key]['combo_info'][$i]['order_id_img'] = $line_file_info['order_id_img'];
                        $data[$key]['combo_info'][$i]['weight_img'] = $line_file_info['weight_img'];

                    } else {

                        $data[$key]['combo_info'][$i]['tracking_id'] = $tracking_id_list[$i];

                    }

                }
            } else {
                $combo_count = array_sum(array_column($comboInfo, 'qty'));
                $data[$key]['is_combo'] = 1;
                $count_all = 0;
                foreach ($comboInfo as $k => $v) {

                    $data[$key]['combo_info'][$k] = $v;
                    $data[$key]['combo_info'][$k]['qty'] = $value['qty'] * $v['qty'];
                    $data[$key]['combo_info'][$k]['default_qty'] = $v['qty'];
                    $data[$key]['combo_info'][$k]['key'] = $k + 1;
                    $data[$key]['combo_count'] = $combo_count;
                    //$combo_key = $k + 1; // combo 所在第几个

                    //$value['qty']*$v['qty']
                    $tracking_id_list = explode('&', $value['tracking_id']);
                    for ($i = 0; $i < (int)$value['qty'] * $v['qty']; $i++) {
                        $combo_key = intval(floor($count_all / $value['qty'])) + 1;
                        $count_all++;
                        $mapLineInfo = [
                            ['line_id', '=', $value['line_id']],
                            ['status', '=', 1],
                            ['combo_sort', '=', $k + 1],
                            ['line_item_number', '=', $i + 1],
                        ];
                        $line_file_info = $this->orm->table('tb_sys_customer_sales_dropship_file_details')->where($mapLineInfo)->first();
                        $line_file_info = obj2array($line_file_info);


                        $count++;
                        $data[$key]['combo_info'][$k]['line'][$i]['key'] = $i + 1;
                        $data[$key]['combo_info'][$k]['line'][$i]['combo_key'] = $combo_key;
                        $data[$key]['combo_info'][$k]['line'][$i]['container_id'] = $order_id . '_' . $v['set_product_id'] . '_' . $value['temp_id'] . '_' . ($i + 1) . '_' . $combo_key . '_' . $combo_count;
                        $container_arr[] = $data[$key]['combo_info'][$k]['line'][$i]['container_id'];
                        if ($line_file_info) {
                            $data[$key]['combo_info'][$k]['line'][$i]['tracking_id'] = $line_file_info['tracking_number'];
                            $data[$key]['combo_info'][$k]['line'][$i]['file_name'] = strlen($line_file_info['file_name']) > 30 ? substr($line_file_info['file_name'], 0, 30) . "..." : $line_file_info['file_name'];
                            $data[$key]['combo_info'][$k]['line'][$i]['file_path'] = $line_file_info['file_path'];
                            $data[$key]['combo_info'][$k]['line'][$i]['deal_file_name'] = strlen($line_file_info['deal_file_name']) > 30 ? substr($line_file_info['deal_file_name'], 0, 30) . "..." : $line_file_info['deal_file_name'];
                            $data[$key]['combo_info'][$k]['line'][$i]['deal_file_path'] = $line_file_info['deal_file_path'];
                            $data[$key]['combo_info'][$k]['line'][$i]['file_id'] = $line_file_info['id'];
                            $data[$key]['combo_info'][$k]['line'][$i]['tracking_number_img'] = $line_file_info['tracking_number_img'];
                            $data[$key]['combo_info'][$k]['line'][$i]['order_id_img'] = $line_file_info['order_id_img'];
                            $data[$key]['combo_info'][$k]['line'][$i]['weight_img'] = $line_file_info['weight_img'];
                        } else {
                            if ($k == 0 && $i < $value['qty']) {
                                $data[$key]['combo_info'][$k]['line'][$i]['tracking_id'] = $tracking_id_list[$i];
                            } else {
                                $data[$key]['combo_info'][$k]['line'][$i]['tracking_id'] = null;
                            }
                        }


                    }

                }
            }
            $data['total_file_amount'] = $count;
        }
        //upload 容器 id
        if ($this->session->has('container_id_list')) {
            $container_id_list[$order_id]['count'] = $count;
            $container_id_list[$order_id]['child_list'] = $container_arr;
            $container_id_list['id_list'] = array_unique(array_merge($container_id_list['id_list'], $container_arr));
            $this->session->set('container_id_list', $container_id_list);
        } else {
            $container_id_list[$order_id]['count'] = $count;
            $container_id_list[$order_id]['child_list'] = $container_arr;
            $container_id_list['id_list'] = $container_arr;
            $this->session->set('container_id_list', $container_id_list);
        }

        return $data;


    }


    public function getSingleEuropeWayfairChildListComboInfo($data, $order_id, &$europe_wayfair_container_id_list, &$europe_wayfair_label_details)
    {
        $count = 0;
        $container_arr = [];
        foreach ($data as $key => $value) {
            $comboInfo = $this->getComboInfoBySku($value['item_code']);
            if (!$comboInfo) {
                //非combo
                //验证 line id
                $data[$key]['is_combo'] = 0;
                for ($i = 0; $i < (int)$value['qty']; $i++) {

                    //$tracking_id_list = explode('&',$value['tracking_id']);
                    $mapLineInfo = [
                        ['line_id', '=', $value['line_id']],
                        ['status', '=', 1],
                        ['line_item_number', '=', $i + 1],
                    ];
                    $line_file_info = $this->orm->table('tb_sys_customer_sales_dropship_file_details')
                        ->where($mapLineInfo)
                        ->first();
                    $line_file_info = obj2array($line_file_info);
                    $count++;
                    $data[$key]['combo_info'][$i]['key'] = $i + 1;
                    $data[$key]['combo_info'][$i]['container_id'] = $order_id . '_' . $key . '_' . $value['temp_id'] . '_' . ($i + 1);
                    $container_arr[] = $data[$key]['combo_info'][$i]['container_id'];
                    if ($line_file_info) {
                        $data[$key]['combo_info'][$i]['tracking_id'] = $line_file_info['tracking_number'];
                        $data[$key]['combo_info'][$i]['file_name'] = strlen($line_file_info['file_name']) > 30 ? substr($line_file_info['file_name'], 0, 30) . "..." : $line_file_info['file_name'];
                        $data[$key]['combo_info'][$i]['file_path'] = $line_file_info['file_path'];
                        $data[$key]['combo_info'][$i]['deal_file_name'] = strlen($line_file_info['deal_file_name']) > 30 ? substr($line_file_info['deal_file_name'], 0, 30) . "..." : $line_file_info['deal_file_name'];
                        $data[$key]['combo_info'][$i]['deal_file_path'] = $line_file_info['deal_file_path'];
                        $data[$key]['combo_info'][$i]['commercial_invoice_file_name'] = strlen($line_file_info['commercial_invoice_file_name']) > 30 ? substr($line_file_info['commercial_invoice_file_name'], 0, 30) . "..." : $line_file_info['commercial_invoice_file_name'];
                        $data[$key]['combo_info'][$i]['commercial_invoice_file_path'] = $line_file_info['commercial_invoice_file_path'];
                        $data[$key]['combo_info'][$i]['file_id'] = $line_file_info['id'];
                        $data[$key]['combo_info'][$i]['tracking_number_img'] = $line_file_info['tracking_number_img'];
                        $data[$key]['combo_info'][$i]['order_id_img'] = $line_file_info['order_id_img'];
                        $data[$key]['combo_info'][$i]['weight_img'] = $line_file_info['weight_img'];
                        $europe_wayfair_label_details['common_label'][$data[$key]['combo_info'][$i]['container_id']] = $line_file_info['file_name'];
                        $europe_wayfair_label_details['tracking_number'][$data[$key]['combo_info'][$i]['container_id']] = $line_file_info['tracking_number'];
                        $europe_wayfair_label_details['commercial_invoice'][$data[$key]['combo_info'][$i]['container_id']] = $line_file_info['commercial_invoice_file_name'];

                    } else {

                        $data[$key]['combo_info'][$i]['tracking_id'] = null;
                    }

                }
            } else {
                $combo_count = array_sum(array_column($comboInfo, 'qty'));
                $data[$key]['is_combo'] = 1;
                $count_all = 0;
                foreach ($comboInfo as $k => $v) {

                    $data[$key]['combo_info'][$k] = $v;
                    $data[$key]['combo_info'][$k]['qty'] = $value['qty'] * $v['qty'];
                    $data[$key]['combo_info'][$k]['default_qty'] = $v['qty'];
                    $data[$key]['combo_info'][$k]['key'] = $k + 1;
                    $data[$key]['combo_count'] = $combo_count;
                    for ($i = 0; $i < (int)$value['qty'] * $v['qty']; $i++) {
                        $combo_key = intval(floor($count_all / $value['qty'])) + 1;
                        $count_all++;
                        $mapLineInfo = [
                            ['line_id', '=', $value['line_id']],
                            ['status', '=', 1],
                            ['combo_sort', '=', $k + 1],
                            ['line_item_number', '=', $i + 1],
                        ];
                        $line_file_info = $this->orm->table('tb_sys_customer_sales_dropship_file_details')
                            ->where($mapLineInfo)
                            ->first();
                        $line_file_info = obj2array($line_file_info);
                        $count++;
                        $data[$key]['combo_info'][$k]['line'][$i]['key'] = $i + 1;
                        $data[$key]['combo_info'][$k]['line'][$i]['combo_key'] = $combo_key;
                        $data[$key]['combo_info'][$k]['line'][$i]['container_id'] = $order_id . '_' . $v['set_product_id'] . '_' . $value['temp_id'] . '_' . ($i + 1) . '_' . $combo_key . '_' . $combo_count;
                        $container_arr[] = $data[$key]['combo_info'][$k]['line'][$i]['container_id'];
                        if ($line_file_info) {
                            $data[$key]['combo_info'][$k]['line'][$i]['tracking_id'] = $line_file_info['tracking_number'];
                            $data[$key]['combo_info'][$k]['line'][$i]['file_name'] = strlen($line_file_info['file_name']) > 30 ? substr($line_file_info['file_name'], 0, 30) . "..." : $line_file_info['file_name'];
                            $data[$key]['combo_info'][$k]['line'][$i]['file_path'] = $line_file_info['file_path'];
                            $data[$key]['combo_info'][$k]['line'][$i]['deal_file_name'] = strlen($line_file_info['deal_file_name']) > 30 ? substr($line_file_info['deal_file_name'], 0, 30) . "..." : $line_file_info['deal_file_name'];
                            $data[$key]['combo_info'][$k]['line'][$i]['deal_file_path'] = $line_file_info['deal_file_path'];
                            $data[$key]['combo_info'][$k]['line'][$i]['commercial_invoice_file_name'] = strlen($line_file_info['commercial_invoice_file_name']) > 30 ? substr($line_file_info['commercial_invoice_file_name'], 0, 30) . "..." : $line_file_info['commercial_invoice_file_name'];
                            $data[$key]['combo_info'][$k]['line'][$i]['commercial_invoice_file_path'] = $line_file_info['commercial_invoice_file_path'];
                            $data[$key]['combo_info'][$k]['line'][$i]['file_id'] = $line_file_info['id'];
                            $data[$key]['combo_info'][$k]['line'][$i]['tracking_number_img'] = $line_file_info['tracking_number_img'];
                            $data[$key]['combo_info'][$k]['line'][$i]['order_id_img'] = $line_file_info['order_id_img'];
                            $data[$key]['combo_info'][$k]['line'][$i]['weight_img'] = $line_file_info['weight_img'];
                            $europe_wayfair_label_details['common_label'][$data[$key]['combo_info'][$k]['line'][$i]['container_id']] = $line_file_info['file_name'];
                            $europe_wayfair_label_details['tracking_number'][$data[$key]['combo_info'][$k]['line'][$i]['container_id']] = $line_file_info['tracking_number'];
                            $europe_wayfair_label_details['commercial_invoice'][$data[$key]['combo_info'][$k]['line'][$i]['container_id']] = $line_file_info['commercial_invoice_file_name'];
                        } else {
                            $data[$key]['combo_info'][$k]['line'][$i]['tracking_id'] = null;
                        }
                    }
                }
            }
            $data['total_file_amount'] = $count;
        }
        //upload 容器 id
        if (isset($this->session->data['europe_wayfair_container_id_list'])) {
            $europe_wayfair_container_id_list[$order_id]['count'] = $count;
            $europe_wayfair_container_id_list[$order_id]['child_list'] = $container_arr;
            $europe_wayfair_container_id_list['id_list'] = array_unique(array_merge($europe_wayfair_container_id_list['id_list'], $container_arr));
            session()->set('europe_wayfair_container_id_list', $europe_wayfair_container_id_list);
        } else {
            $europe_wayfair_container_id_list[$order_id]['count'] = $count;
            $europe_wayfair_container_id_list[$order_id]['child_list'] = $container_arr;
            $europe_wayfair_container_id_list['id_list'] = $container_arr;
            session()->set('europe_wayfair_container_id_list', $europe_wayfair_container_id_list);
        }
        session()->set('europe_wayfair_label_details', $europe_wayfair_label_details);
        return $data;

    }

    /**
     * [getSingleWayfairChildListComboInfo description]
     * @param $data
     * @param int $order_id tb_sys_customer_sales_order表的id
     * @param $wayfair_container_id_list
     * @return array
     */
    public function getSingleWayfairChildListComboInfo($data, $order_id, &$wayfair_container_id_list)
    {
        $count = 0;
        $container_arr = [];
        $trackingInfo = [];
        foreach ($data as $key => $value) {
            $comboInfo = $this->getComboInfoBySku($value['item_code']);
            if (!$comboInfo) {
                //非combo
                //验证 line id
                $data[$key]['is_combo'] = 0;
                for ($i = 0; $i < (int)$value['qty']; $i++) {

                    //$tracking_id_list = explode('&',$value['tracking_id']);
                    $mapLineInfo = [
                        ['line_id', '=', $value['line_id']],
                        ['status', '=', 1],
                        ['line_item_number', '=', $i + 1],
                    ];
                    $line_file_info = $this->orm->table('tb_sys_customer_sales_dropship_file_details')
                        ->where($mapLineInfo)
                        ->first();
                    $line_file_info = obj2array($line_file_info);
                    $count++;
                    $data[$key]['combo_info'][$i]['key'] = $i + 1;
                    $data[$key]['combo_info'][$i]['container_id'] = $order_id . '_' . $key . '_' . $value['temp_id'] . '_' . ($i + 1);
                    $container_arr[] = $data[$key]['combo_info'][$i]['container_id'];
                    if ($line_file_info) {
                        $data[$key]['combo_info'][$i]['tracking_id'] = $line_file_info['tracking_number'];
                        $data[$key]['combo_info'][$i]['file_name'] = strlen($line_file_info['file_name']) > 30 ? substr($line_file_info['file_name'], 0, 30) . "..." : $line_file_info['file_name'];
                        $data[$key]['combo_info'][$i]['file_path'] = $line_file_info['file_path'];
                        $data[$key]['combo_info'][$i]['deal_file_name'] = strlen($line_file_info['deal_file_name']) > 30 ? substr($line_file_info['deal_file_name'], 0, 30) . "..." : $line_file_info['deal_file_name'];
                        $data[$key]['combo_info'][$i]['deal_file_path'] = $line_file_info['deal_file_path'];
                        $data[$key]['combo_info'][$i]['file_id'] = $line_file_info['id'];
                        $data[$key]['combo_info'][$i]['tracking_number_img'] = $line_file_info['tracking_number_img'];
                        $data[$key]['combo_info'][$i]['order_id_img'] = $line_file_info['order_id_img'];
                        $data[$key]['combo_info'][$i]['weight_img'] = $line_file_info['weight_img'];
                        $trackingInfo[$data[$key]['combo_info'][$i]['container_id']] = [
                            'uploadId' => $data[$key]['combo_info'][$i]['container_id'],
                            'tracking_number' => $line_file_info['tracking_number'],
                            'order_id' => $line_file_info['order_id'],
                        ];

                    } else {

                        $data[$key]['combo_info'][$i]['tracking_id'] = null;

                    }

                }
            } else {
                $combo_count = array_sum(array_column($comboInfo, 'qty'));
                $data[$key]['is_combo'] = 1;
                $count_all = 0;
                foreach ($comboInfo as $k => $v) {

                    $data[$key]['combo_info'][$k] = $v;
                    $data[$key]['combo_info'][$k]['qty'] = $value['qty'] * $v['qty'];
                    $data[$key]['combo_info'][$k]['default_qty'] = $v['qty'];
                    $data[$key]['combo_info'][$k]['key'] = $k + 1;
                    $data[$key]['combo_count'] = $combo_count;
                    for ($i = 0; $i < (int)$value['qty'] * $v['qty']; $i++) {
                        $combo_key = intval(floor($count_all / $value['qty'])) + 1;
                        $count_all++;
                        $mapLineInfo = [
                            ['line_id', '=', $value['line_id']],
                            ['status', '=', 1],
                            ['combo_sort', '=', $k + 1],
                            ['line_item_number', '=', $i + 1],
                        ];
                        $line_file_info = $this->orm->table('tb_sys_customer_sales_dropship_file_details')
                            ->where($mapLineInfo)
                            ->first();
                        $line_file_info = obj2array($line_file_info);
                        $count++;
                        $data[$key]['combo_info'][$k]['line'][$i]['key'] = $i + 1;
                        $data[$key]['combo_info'][$k]['line'][$i]['combo_key'] = $combo_key;
                        $data[$key]['combo_info'][$k]['line'][$i]['container_id'] = $order_id . '_' . $v['set_product_id'] . '_' . $value['temp_id'] . '_' . ($i + 1) . '_' . $combo_key . '_' . $combo_count;
                        $container_arr[] = $data[$key]['combo_info'][$k]['line'][$i]['container_id'];
                        if ($line_file_info) {
                            $data[$key]['combo_info'][$k]['line'][$i]['tracking_id'] = $line_file_info['tracking_number'];
                            $data[$key]['combo_info'][$k]['line'][$i]['file_name'] = strlen($line_file_info['file_name']) > 30 ? substr($line_file_info['file_name'], 0, 30) . "..." : $line_file_info['file_name'];
                            $data[$key]['combo_info'][$k]['line'][$i]['file_path'] = $line_file_info['file_path'];
                            $data[$key]['combo_info'][$k]['line'][$i]['deal_file_name'] = strlen($line_file_info['deal_file_name']) > 30 ? substr($line_file_info['deal_file_name'], 0, 30) . "..." : $line_file_info['deal_file_name'];
                            $data[$key]['combo_info'][$k]['line'][$i]['deal_file_path'] = $line_file_info['deal_file_path'];
                            $data[$key]['combo_info'][$k]['line'][$i]['file_id'] = $line_file_info['id'];
                            $data[$key]['combo_info'][$k]['line'][$i]['tracking_number_img'] = $line_file_info['tracking_number_img'];
                            $data[$key]['combo_info'][$k]['line'][$i]['order_id_img'] = $line_file_info['order_id_img'];
                            $data[$key]['combo_info'][$k]['line'][$i]['weight_img'] = $line_file_info['weight_img'];
                            $trackingInfo[$data[$key]['combo_info'][$k]['line'][$i]['container_id']] = [
                                'uploadId' => $data[$key]['combo_info'][$k]['line'][$i]['container_id'],
                                'tracking_number' => $line_file_info['tracking_number'],
                                'order_id' => $line_file_info['order_id'],
                            ];
                        } else {
                            if ($k == 0 && $i < $value['qty']) {
                                //$data[$key]['combo_info'][$k]['line'][$i]['tracking_id'] = $tracking_id_list[$i] ;
                                $data[$key]['combo_info'][$k]['line'][$i]['tracking_id'] = null;
                            } else {
                                $data[$key]['combo_info'][$k]['line'][$i]['tracking_id'] = null;
                            }
                        }


                    }


                }

            }
            $data['total_file_amount'] = $count;
        }
        //upload 容器 id
        if (isset($this->session->data['wayfair_container_id_list'])) {
            $wayfair_container_id_list[$order_id]['count'] = $count;
            $wayfair_container_id_list[$order_id]['child_list'] = $container_arr;
            $wayfair_container_id_list['id_list'] = array_unique(array_merge($wayfair_container_id_list['id_list'], $container_arr));
            session()->set('wayfair_tracking_info', array_merge($trackingInfo, session('wayfair_tracking_info')));
        } else {
            $wayfair_container_id_list[$order_id]['count'] = $count;
            $wayfair_container_id_list[$order_id]['child_list'] = $container_arr;
            $wayfair_container_id_list['id_list'] = $container_arr;
            session()->set('wayfair_tracking_info', $trackingInfo);
        }
        session()->set('wayfair_container_id_list', $wayfair_container_id_list);

        return $data;

    }


    /**
     * [getDropshipChildListComboInfo description] 通过 childList sku 获取combo 信息 以及排列的点坐标
     * @param $data
     * @param int $order_id tb_sys_customer_sales_order表的id
     * @param $container_id_list
     * @return array
     */
    public function getDropshipChildListComboInfo($data, $order_id, &$container_id_list)
    {
        $count = 0;
        $container_arr = [];
        foreach ($data as $key => $value) {
            $comboInfo = $this->getComboInfoBySku($value['item_code']);
            if (!$comboInfo) {
                //非combo
                $data[$key]['is_combo'] = 0;
                $tracking_id_list = explode('&', $value['tracking_id']);
                for ($i = 0; $i < (int)$value['qty']; $i++) {
                    $count++;
                    $data[$key]['combo_info'][$i]['key'] = $i + 1;
                    $data[$key]['combo_info'][$i]['container_id'] = $order_id . '_' . $key . '_' . $value['temp_id'] . '_' . ($i + 1);
                    $container_arr[] = $data[$key]['combo_info'][$i]['container_id'];

                    $data[$key]['combo_info'][$i]['tracking_id'] = $tracking_id_list[$i];

                }
            } else {
                $combo_count = array_sum(array_column($comboInfo, 'qty'));
                $data[$key]['is_combo'] = 1;
                $count_all = 0;
                $tracking_id_list = explode('&', $value['tracking_id']);
                foreach ($comboInfo as $k => $v) {

                    $data[$key]['combo_info'][$k] = $v;
                    $data[$key]['combo_info'][$k]['qty'] = $value['qty'] * $v['qty'];
                    $data[$key]['combo_info'][$k]['default_qty'] = $v['qty'];
                    $data[$key]['combo_info'][$k]['key'] = $k + 1;
                    $data[$key]['combo_count'] = $combo_count;
                    //$combo_key = $k + 1; // combo 所在第几个
                    //$value['qty']*$v['qty']
                    for ($i = 0; $i < (int)$value['qty'] * $v['qty']; $i++) {
                        $combo_key = intval(floor($count_all / $value['qty'])) + 1;
                        $count_all++;
                        $count++;
                        $data[$key]['combo_info'][$k]['line'][$i]['key'] = $i + 1;
                        $data[$key]['combo_info'][$k]['line'][$i]['combo_key'] = $combo_key;
                        $data[$key]['combo_info'][$k]['line'][$i]['container_id'] = $order_id . '_' . $v['set_product_id'] . '_' . $value['temp_id'] . '_' . ($i + 1) . '_' . $combo_key . '_' . $combo_count;
                        $container_arr[] = $data[$key]['combo_info'][$k]['line'][$i]['container_id'];

                        if ($k == 0 && $i < $value['qty']) {
                            $data[$key]['combo_info'][$k]['line'][$i]['tracking_id'] = $tracking_id_list[$i];
                        } else {
                            $data[$key]['combo_info'][$k]['line'][$i]['tracking_id'] = null;
                        }

                    }
                    //获取 sub-item的长度
                    //初始点确定 20 20
                    //子item数字  38*6 + 38
                    //if(!$item_code_position){
                    //    $temp['x'] = 20;
                    //    $temp['y'] = 20;
                    //    $temp['id'] = $order_id.'_'.$key;
                    //
                    //}else{
                    //    $temp['x'] = 20;
                    //    $temp['y'] = 20 + $value['qty']*$v['qty']*38 + 38 ;
                    //}
                    //$item_code_position[] = $temp;
                }
                //$data[$key]['canvas_id'] = $order_id.'_'.$key;
                //
                //$this->session->data['canvas_info'][$order_id.'_'.$key] = $item_code_position;


            }
            $data['total_file_amount'] = $count;
        }
        //upload 容器 id
        if (isset($this->session->data['container_id_list'])) {
            $container_id_list[$order_id]['count'] = $count;
            $container_id_list[$order_id]['child_list'] = $container_arr;
            $container_id_list['id_list'] = array_unique(array_merge($container_id_list['id_list'], $container_arr));
            session()->set('container_id_list', $container_id_list);
        } else {
            $container_id_list[$order_id]['count'] = $count;
            $container_id_list[$order_id]['child_list'] = $container_arr;
            $container_id_list['id_list'] = $container_arr;
            session()->set('container_id_list', $container_id_list);
        }

        return $data;


    }

    public function getEuropeWayfairChildListComboInfo($data, $order_id, &$europe_wayfair_container_id_list)
    {
        $count = 0;
        $container_arr = [];
        foreach ($data as $key => $value) {
            $comboInfo = $this->getComboInfoBySku($value['item_code']);
            if (!$comboInfo) {
                //非combo
                //验证 line id
                $data[$key]['is_combo'] = 0;
                for ($i = 0; $i < (int)$value['qty']; $i++) {
                    $count++;
                    $data[$key]['combo_info'][$i]['key'] = $i + 1;
                    $data[$key]['combo_info'][$i]['container_id'] = $order_id . '_' . $key . '_' . $value['temp_id'] . '_' . ($i + 1);
                    $container_arr[] = $data[$key]['combo_info'][$i]['container_id'];
                    $data[$key]['combo_info'][$i]['tracking_id'] = null;
                }
            } else {
                $combo_count = array_sum(array_column($comboInfo, 'qty'));
                $data[$key]['is_combo'] = 1;
                $count_all = 0;
                foreach ($comboInfo as $k => $v) {
                    $data[$key]['combo_info'][$k] = $v;
                    $data[$key]['combo_info'][$k]['qty'] = $value['qty'] * $v['qty'];
                    $data[$key]['combo_info'][$k]['default_qty'] = $v['qty'];
                    $data[$key]['combo_info'][$k]['key'] = $k + 1;
                    $data[$key]['combo_count'] = $combo_count;
                    //$combo_key = $k + 1; // combo 所在第几个
                    //$value['qty']*$v['qty']
                    //$tracking_id_list = explode('&',$value['tracking_id']);
                    for ($i = 0; $i < (int)$value['qty'] * $v['qty']; $i++) {
                        $combo_key = intval(floor($count_all / $value['qty'])) + 1;
                        $count_all++;
                        $count++;
                        $data[$key]['combo_info'][$k]['line'][$i]['key'] = $i + 1;
                        $data[$key]['combo_info'][$k]['line'][$i]['combo_key'] = $combo_key;
                        $data[$key]['combo_info'][$k]['line'][$i]['container_id'] = $order_id . '_' . $v['set_product_id'] . '_' . $value['temp_id'] . '_' . ($i + 1) . '_' . $combo_key . '_' . $combo_count;
                        $container_arr[] = $data[$key]['combo_info'][$k]['line'][$i]['container_id'];
                        $data[$key]['combo_info'][$k]['line'][$i]['tracking_id'] = null;
                    }


                }

            }
            $data['total_file_amount'] = $count;
        }
        //upload 容器 id
        if ($this->session->has('europe_wayfair_container_id_list')) {
            $europe_wayfair_container_id_list[$order_id]['count'] = $count;
            $europe_wayfair_container_id_list[$order_id]['child_list'] = $container_arr;
            $europe_wayfair_container_id_list['id_list'] = array_unique(array_merge($europe_wayfair_container_id_list['id_list'], $container_arr));
            $this->session->set('europe_wayfair_container_id_list', $europe_wayfair_container_id_list);
        } else {
            $europe_wayfair_container_id_list[$order_id]['count'] = $count;
            $europe_wayfair_container_id_list[$order_id]['child_list'] = $container_arr;
            $europe_wayfair_container_id_list['id_list'] = $container_arr;
            $this->session->set('europe_wayfair_container_id_list', $europe_wayfair_container_id_list);
        }
        return $data;

    }

    public function getWayfairChildListComboInfo($data, $order_id, &$wayfair_container_id_list)
    {
        $count = 0;
        $container_arr = [];
        foreach ($data as $key => $value) {
            $comboInfo = $this->getComboInfoBySku($value['item_code']);
            if (!$comboInfo) {
                //非combo
                $data[$key]['is_combo'] = 0;
                for ($i = 0; $i < (int)$value['qty']; $i++) {
                    $count++;
                    $data[$key]['combo_info'][$i]['key'] = $i + 1;
                    $data[$key]['combo_info'][$i]['container_id'] = $order_id . '_' . $key . '_' . $value['temp_id'] . '_' . ($i + 1);
                    $container_arr[] = $data[$key]['combo_info'][$i]['container_id'];
                    $data[$key]['combo_info'][$i]['tracking_id'] = null;
                }
            } else {
                $combo_count = array_sum(array_column($comboInfo, 'qty'));
                $data[$key]['is_combo'] = 1;
                $count_all = 0;
                foreach ($comboInfo as $k => $v) {

                    $data[$key]['combo_info'][$k] = $v;
                    $data[$key]['combo_info'][$k]['qty'] = $value['qty'] * $v['qty'];
                    $data[$key]['combo_info'][$k]['default_qty'] = $v['qty'];
                    $data[$key]['combo_info'][$k]['key'] = $k + 1;
                    //$combo_key = $k + 1;
                    $data[$key]['combo_count'] = $combo_count;
                    for ($i = 0; $i < (int)$value['qty'] * $v['qty']; $i++) {
                        $combo_key = intval(floor($count_all / $value['qty'])) + 1;
                        $count_all++;
                        $count++;
                        $data[$key]['combo_info'][$k]['line'][$i]['key'] = $i + 1;
                        $data[$key]['combo_info'][$k]['line'][$i]['combo_key'] = $combo_key;
                        $data[$key]['combo_info'][$k]['line'][$i]['container_id'] = $order_id . '_' . $v['set_product_id'] . '_' . $value['temp_id'] . '_' . ($i + 1) . '_' . $combo_key . '_' . $combo_count;
                        $container_arr[] = $data[$key]['combo_info'][$k]['line'][$i]['container_id'];
                        $data[$key]['combo_info'][$k]['line'][$i]['tracking_id'] = null;

                    }

                }


            }
            $data['total_file_amount'] = $count;
        }
        //upload 容器 id
        if ($this->session->has('wayfair_container_id_list')) {
            $wayfair_container_id_list[$order_id]['count'] = $count;
            $wayfair_container_id_list[$order_id]['child_list'] = $container_arr;
            $wayfair_container_id_list['id_list'] = array_unique(array_merge($wayfair_container_id_list['id_list'], $container_arr));
            $this->session->set('wayfair_container_id_list', $wayfair_container_id_list);
        } else {
            $wayfair_container_id_list[$order_id]['count'] = $count;
            $wayfair_container_id_list[$order_id]['child_list'] = $container_arr;
            $wayfair_container_id_list['id_list'] = $container_arr;
            $this->session->set('wayfair_container_id_list', $wayfair_container_id_list);
        }
        return $data;


    }

    /**
     * [verifyDropshipCsvByMapping description] 通过映射来验证csv 数据是否合法
     * @param $data
     * @param int $country_id
     * @param bool $isCollectionFromDomicile true上门取货
     * @return array
     */
    public function verifyDropshipCsvByMapping($data, $country_id, $isCollectionFromDomicile)
    {
        // 1. 所有订单验证sku和订单是否唯一
        // 2. 验证 订单下的 ship_method_code格式是否统一
        // 3. 验证 一条数据中有多个ship_method_code
        // 4. 验证 CEVA 和 ABF 仓库是否符合映射关系  英国仓库必填且校验
        // 5. 验证 CEVA 和 ABF
        // 6. 验证 SKU 只要是当前国别存在过的Item Code就可以
        // 上门取货的销售订单，如果运输方式是LTL，需要增加仓库校验
        $order = [];
        $order_ship_method = [];
        $err = '';
        $customer_id = $this->customer->getId();
        $warehouse_list = [];
        $warehouse_code_map = [];
        $lineTransferSku = [];

        foreach ($data as $key => $value) {
            $sku = trim($value['SKU']);
            $b2bSku = $this->verifyPlatformSku($sku, $customer_id, 2);
            $order_sku_key = trim($value['Order ID']) . '_' . $sku;
            $order[$order_sku_key][] = $key + 2;
            $ship_method = $value['Ship Method'];
            $ship_method_code = $value['Ship Method Code'];
            $code_list = [];
            // 验证 SKU 只要是当前国别存在过的Item Code就可以
            $productId = $this->judgeSkuIsExist($sku, $country_id);
            if (!$productId) {
                $err .= 'Line' . ($key + 2) . ', [SKU] is invalid.' . '<br/>';
                break;
            }
            $lineTransferSku[$key + 2] = $sku;
            foreach (LOGISTICS_VERIFY_TYPES as $ks => $vs) {
                if (stripos($ship_method_code, $vs) !== false || stripos($ship_method, $vs) !== false) {
                    $code_list[] = $vs;
                }
            }
            $order_ship_method[trim($value['Order ID'])][$key + 2] = $code_list;
            $warehouse_flag = 0;
            foreach (VERIFY_WAREHOUSE_TYPES as $ks => $vs) {
                if (stripos($ship_method_code, $vs) !== false || stripos($ship_method, $vs) !== false) {
                    $warehouse_flag = 1;
                    break;
                }
            }
            if ($warehouse_flag && $country_id == AMERICAN_COUNTRY_ID) {
                //验证 CEVA 和 ABF 仓库是否符合映射关系
                $res = $this->verifyWarehouseIsExist($value['Warehouse Code'], $customer_id, 2);
                if (!$res) {
                    //$err .= 'Line' . ($key + 2) . ',Warehouse Code format error.' . '<br/>';
                } else {
                    $warehouse_list[trim($value['Order ID'])][] = $res;
                    $warehouse_code_map[trim($value['Order ID'])][] = $value['Warehouse Code'];
                }
            }
            //欧洲国家 暂时只有英国，德国没有亚马逊导单,必填项;
            if ($country_id == HomePickUploadType::BRITAIN_COUNTRY_ID) {
                $res = null;
                if (!empty($value['Warehouse Code'])) {
                    $res = $this->verifyWarehouseIsExist($value['Warehouse Code'], $customer_id, 2);
                }
                if (!$res) {
                    $err .= 'Line' . ($key + 2) . ',Warehouse Code format error.' . '<br/>';
                } else {
                    $warehouse_list[trim($value['Order ID'])][] = $res;
                    $warehouse_code_map[trim($value['Order ID'])][] = $value['Warehouse Code'];
                }
            }
            $retError = app(CustomerSalesOrderRepository::class)->checkHomePickLTLTypeWarehouse(HomePickImportMode::IMPORT_MODE_AMAZON, $key + 2, $sku, $b2bSku, $value['Warehouse Code'], $ship_method_code ? $ship_method_code : $ship_method, $country_id);
            if ($retError) {
                $err .= ($retError . '<br/>');
            }

            //注意：产品数量正整数 import 4
            if ($value['Item Quantity'] == '' || !preg_match('/^[1-9][0-9]*$/', $value['Item Quantity'])) {
                $err .= 'Line' . ($key + 2) . ",Item Quantity format error." . '<br/>';
            }
        }
        foreach ($order as $key => $value) {
            if (count($value) > 1) {
                $err .= 'This file has the same order details.Number of lines:' . implode(',', $value) . '<br/>';
            }
        }

        if (isset($warehouse_list)) {
            foreach ($warehouse_list as $key => $value) {
                if (count(array_unique($value)) > 1) {
                    $err .= 'Order ID [' . $key . '] Warehouse Code is not unique,it has ' . implode(',', array_unique($warehouse_code_map[$key])) . '<br/>';
                }
            }
        }
        foreach ($order_ship_method as $key => $value) {
            foreach ($value as $k => $v) {
                if (count($v) > 1 || count($v) == 0) {
                    $err .= 'Line' . $k . ',Ship Method Code/Ship Method format error.' . '<br/>';
                } else {
                    if ($warehouse_flag && $country_id == HomePickUploadType::BRITAIN_COUNTRY_ID) {
                        $err .= 'Line' . $k . ',Ship Method Code/Ship Method format error.' . '<br/>';
                    }
                    $order_ship_method_list[$key][] = $v[0];
                }
            }
        }
        if (isset($order_ship_method_list)) {
            foreach ($order_ship_method_list as $key => $value) {
                if (count(array_unique($value)) > 1) {
                    $err .= 'Order ID [' . $key . '] Ship Method Code/Ship Method is not unique,it has ' . implode(',', array_unique($value)) . '.' . '<br/>';

                }
            }
        }
        //验证err中是否含有 Warehouse Name format error. Item # format error.
        if (stripos($err, 'Warehouse Code format error.') !== false) {
            $final['err_href'] = '<a href="index.php?route=account/mapping_management" style="cursor: pointer;text-decoration: underline;" target="_blank" >Go to Mapping Management</a><br/>';
        }

        $skus = array_values($lineTransferSku);
        $verifyRet = app(salesOrderSkuValidate::class)->withSkus($skus)->validateSkus();
        if (!$verifyRet['code']) {
            $err .= sprintf($verifyRet['msg'], array_search($verifyRet['errorSku'], $lineTransferSku)) . '<br/>';
        }

        $final['err'] = $err;
        return $final;

    }

    public function verifyEuropeWayfairCsvByMapping($data, $customer_id, $country_id)
    {
        // 1. 所有订单验证Item Number和订单PO Number是否唯一
        // 2. 验证 订单下的 ship_method_code格式是否统一
        // 3. 验证 一条数据中有多个ship_method carrier name
        // 4. 验证 订单下的 Ready for Pickup Date格式是否统一
        // 上门取货的销售订单，如果运输方式是LTL，需要增加仓库校验
        $order = [];
        $order_ship_method = [];
        $order_pick_up_date = [];
        $err = '';
        $sku_err_no = '';
        $warehouse_err_no = '';
        $warehouse_list = [];
        $lineTransferSku = [];
        foreach ($data as $key => $value) {
            if (StringHelper::stringCharactersLen(trim($value['Ship To Address']) . trim($value['Ship To Address 2'])) > $this->config->get('config_b2b_address_len')) {
                $err .= 'Line [' . ($key + 2) . '],Ship To Address Line format error' . '<br/>';
            }
            //sku 全部需要转换
            //这里有个转换
            //sku 不存在则需要报错
            $platformSku = trim($value['Item Number']);
            $platformWarehouseName = trim($value['Warehouse Name']);
            //欧洲wayfile  验证仓库必填且存在映射
            if ($platformWarehouseName == '') {
                $err .= 'Line [' . ($key + 2) . '],[Warehouse Name] can not be left blank.';
            } else {
                $res_1 = $warehouseId = $this->verifyWarehouseIsExist($platformWarehouseName, $customer_id, 1);
                if ($res_1) {
                    $warehouse_list[trim($value['PO Number'])][] = $res_1;
                    $warehouse_code_map[trim($value['PO Number'])][] = $platformWarehouseName;
                } else {
                    $warehouse_err_no .= ($key + 2) . ',';
                }
            }


            $res = $this->verifyPlatformSku($platformSku, $customer_id, 1);
            if ($res) {
                $data[$key]['item_code'] = $res;
                $order_sku_key = trim($value['PO Number']) . '_' . $res;
                $order[$order_sku_key][] = $key + 2;
                $lineTransferSku[$key + 2] = $res;
            } else {
                //$err .= 'Line ['. ($key + 2) .'],[Item Number] format error.<br/>' ;
                if ($platformSku == '') {
                    $err .= 'Line [' . ($key + 2) . '],[Item Number] can not be left blank.';
                } else {
                    $sku_err_no .= ($key + 2) . ',';
                }
            }
            $ship_method = trim($value['Carrier Name']);
            $code_list = [];
            foreach (WAYFAIR_EUROPE_MAPPING as $ks => $vs) {
                if (strtolower($ks) == strtolower($ship_method) && $vs == $country_id) {
                    $code_list[] = $ks;
                }
            }
            $order_ship_method[trim($value['PO Number'])][$key + 2] = $code_list;
            $order_pick_up_date[trim($value['PO Number'])][] = trim($value['Ready for Pickup Date']);
        }

        if (isset($warehouse_list)) {
            foreach ($warehouse_list as $key => $value) {
                if (count(array_unique($value)) > 1) {
                    $err .= 'PO Number [' . $key . '] Warehouse Name is not unique,it has ' . implode(',', array_unique($warehouse_code_map[$key])) . '<br/>';
                }
            }
        }

        foreach ($order as $key => $value) {
            if (count($value) > 1) {
                $err .= 'Line [' . implode(',', $value) . '], [Item Number] is duplicate with the other order, please modify it and upload again.<br/>';
            }
        }

        foreach ($order_ship_method as $key => $value) {
            foreach ($value as $k => $v) {
                if (count($v) > 1 || count($v) == 0) {
                    $err .= 'Line [' . $k . '],[Carrier Name] is not valid or currently not accepted by our marketplace.<br/>';
                } else {
                    $order_ship_method_list[$key][] = $v[0];
                }
            }
        }
        if (isset($order_ship_method_list)) {
            foreach ($order_ship_method_list as $key => $value) {
                if (count(array_unique($value)) > 1) {
                    $err .= 'PO Number [' . $key . '] one order is not allowed to have different [Carrier Name].<br/>';
                }
            }
        }
        //验证 Ready for Pickup Date
        foreach ($order_pick_up_date as $key => $value) {
            if (count(array_unique($value)) > 1) {
                $err .= 'PO Number [' . $key . '] one order is not allowed to have different [Ready for Pickup Date].<br/>';
            }
        }
        //验证err中是否含有 Warehouse Name format error. Item Number format error.
        if ($sku_err_no) {
            $err = 'Line [' . trim($sku_err_no, ',') . '],cannot find the mapping of [Item Number].<a href="index.php?route=account/mapping_management" style="cursor: pointer;text-decoration: underline;" target="_blank" >Go to create/modify the mapping.</a>';
        }

        if ($warehouse_err_no) {
            $err = 'Line [' . trim($warehouse_err_no, ',') . '],cannot find the mapping of [Warehouse Name].<a href="index.php?route=account/mapping_management" style="cursor: pointer;text-decoration: underline;" target="_blank" >Go to create/modify the mapping.</a>';
        }

        //验证err中是否含有 Warehouse Name format error
        if (stripos($err, 'Warehouse Name format error.') !== false) {
            $ret['err_href'] = '<a href="index.php?route=account/mapping_management" style="cursor: pointer;text-decoration: underline;" target="_blank" >Go to Mapping Management</a><br/>';
        }

        if (!$data) {
            $err = 'No data was found in the file.<br/>';
        }

        $skus = array_values($lineTransferSku);
        $verifyRet = app(salesOrderSkuValidate::class)->withSkus($skus)->validateSkus();
        if (!$verifyRet['code']) {
            $err .= sprintf($verifyRet['msg'], array_search($verifyRet['errorSku'], $lineTransferSku)) . '<br/>';
        }

        $ret['err'] = $err;
        $ret['data'] = $data;
        return $ret;

    }

    public function verifyWayfairCsvByMapping($data, $country_id)
    {
        // 1. 所有订单验证Item #和订单PO Number是否唯一
        // 2. 验证 订单下的 ship_method_code格式是否统一
        // 3. 验证 一条数据中有多个ship_method carrier name
        // 4. 上门取货的销售订单，如果运输方式是LTL，需要增加仓库校验

        $order = [];
        $order_ship_method = [];
        $err = '';
        $customer_id = $this->customer->getId();
        $warehouse_list = [];
        $lineTransferSku = [];
        foreach ($data as $key => $value) {
            if (StringHelper::stringCharactersLen(trim($value['Ship To Address']) . trim($value['Ship To Address2'])) > $this->config->get('config_b2b_address_len')) {
                $err .= 'Line [' . ($key + 2) . '],Ship To Address Line format error' . '<br/>';
            }
            //sku 全部需要转换
            //这里有个转换
            //sku 不存在则需要报错
            $sku = $this->verifyPlatformSku(trim($value['Item Number']), $customer_id, 1);
            if ($sku) {
                $productId = $this->judgeSkuIsExist($sku, $country_id);
                if (!$productId) {
                    $err .= 'Line' . ($key + 2) . ', [Item Number] is invalid.' . '<br/>';
                    break;
                }
                $lineTransferSku[$key + 2] = $data[$key]['item_code'] = $sku;
                $order_sku_key = trim($value['PO Number']) . '_' . $sku;
                $order[$order_sku_key][] = $key + 2;
            } else {
                $err .= 'Line' . ($key + 2) . ',Item Number format error.' . '<br/>';
            }
            $ship_method = $value['Carrier Name'];
            $code_list = [];
            foreach (WAYFAIR_VERIFY_TYPES as $ks => $vs) {
                if (stripos($ship_method, $vs) !== false) {
                    if ($ks != 1) {
                        $code_list[] = $vs;
                    }
                }
            }
            $order_ship_method[trim($value['PO Number'])][$key + 2] = $code_list;

            if (in_array($country_id, EUROPE_COUNTRY_ID)) {
                //仓库必填且有映射
                $res = null;
                if (!empty($value['Warehouse Name'])) {
                    $res = $this->verifyWarehouseIsExist($value['Warehouse Name'], $customer_id, 1);
                }
                if (!$res) {
                    $err .= 'Line' . ($key + 2) . ',Warehouse Name format error.' . '<br/>';
                } else {
                    $warehouse_list[trim($value['PO Number'])][] = $res;
                    $warehouse_code_map[trim($value['PO Number'])][] = $value['Warehouse Name'];
                }
            } else {
                //原有逻辑
                $warehouse_flag = 0;
                $LTLTypeItems = HomePickCarrierType::getWayfairLTLTypeViewItems();
                foreach ($LTLTypeItems as $ks => $vs) {
                    if (stripos($ship_method, $vs) !== false) {
                        $warehouse_flag = 1;
                        break;
                    }
                }
                if ($warehouse_flag) {
                    //验证 CEVA 和 ABF 仓库是否符合映射关系
                    $res = $this->verifyWarehouseIsExist($value['Warehouse Name'], $customer_id, 1);
                    if (!$res) {
                        //$err .= 'Line' . ($key + 2) . ',Warehouse Name format error.' . '<br/>';
                    } else {
                        $warehouse_list[trim($value['PO Number'])][] = $res;
                        $warehouse_code_map[trim($value['PO Number'])][] = $value['Warehouse Name'];
                    }
                }
            }
            $retError = app(CustomerSalesOrderRepository::class)->checkHomePickLTLTypeWarehouse(HomePickImportMode::IMPORT_MODE_WAYFAIR, $key + 2, trim($value['Item Number']), $sku, $value['Warehouse Name'], $value['Carrier Name'], $country_id);
            if ($retError) {
                $err .= ($retError . '<br/>');
            }

            //注意：产品数量正整数 import 5
            if ($value['Quantity'] == '' || !preg_match('/^[1-9][0-9]*$/', $value['Quantity'])) {
                $err .= 'Line' . ($key + 2) . ",Item Quantity format error." . '<br/>';
            }
        }
        foreach ($order as $key => $value) {
            if (count($value) > 1) {
                $err .= 'This file has the same order details.Number of lines:' . implode(',', $value) . '<br/>';
            }
        }
        if (isset($warehouse_list)) {
            foreach ($warehouse_list as $key => $value) {
                if (count(array_unique($value)) > 1) {
                    $err .= 'PO Number [' . $key . '] Warehouse Name is not unique,it has ' . implode(',', array_unique($warehouse_code_map[$key])) . '<br/>';
                }
            }
        }
        foreach ($order_ship_method as $key => $value) {
            foreach ($value as $k => $v) {
                if (count($v) > 1 || count($v) == 0) {
                    $err .= 'Line' . $k . ',Carrier Name format error.' . '<br/>';
                } else {
                    $order_ship_method_list[$key][] = $v[0];
                }
            }
        }
        if (isset($order_ship_method_list)) {
            foreach ($order_ship_method_list as $key => $value) {
                if (count(array_unique($value)) > 1) {
                    $err .= 'PO Number [' . $key . '] Carrier Name is not unique,it has ' . implode(',', array_unique($value)) . '.' . '<br/>';

                }
            }
        }
        //验证err中是否含有 Warehouse Name format error. Item Number format error.
        if (stripos($err, 'Warehouse Name format error.') !== false || stripos($err, 'Item Number format error.') !== false) {
            $final['err_href'] = '<a href="index.php?route=account/mapping_management" style="cursor: pointer;text-decoration: underline;" target="_blank" >Go to Mapping Management</a>';
        }

        $skus = array_values($lineTransferSku);
        $verifyRet = app(salesOrderSkuValidate::class)->withSkus($skus)->validateSkus();
        if (!$verifyRet['code']) {
            $err .= sprintf($verifyRet['msg'], array_search($verifyRet['errorSku'], $lineTransferSku)) . '<br/>';
        }
        $final['err'] = $err;
        $final['data'] = $data;
        return $final;

    }

    /*
     * 校验导入的walmart订单文件 必填项与数据格式
     * 1、SKU是否建立关联关系
     * 2、PO#是否在tb_sys_customer_sales_order表内 buyer_id+order_id唯一
     * 3、excel中PO#+SKU唯一
     * 4、上门取货的销售订单，如果运输方式是LTL，需要增加仓库校验
     * */
    public function verifyWalmartData($data, $countryId)
    {
        $this->language->load('account/customer_order_import');
        $flag = true;
        $err = '';
        $customerId = $this->customer->getId();
        $orderSkuArr = [];
        $orderLineArr = [];
        $warehouse_list = [];
        $warehouse_code_map = [];
        $order_ship_method = [];
        $lineTransferSku = [];
        foreach ($data as $k => $v) {
            if (empty($v['order_id'])) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_empty'), $k + 2, 'PO#');
                break;
            }
            //#7239只能包含字母、数字、下划线(_)和连字符(-)
            if (strlen($v['order_id']) > 20 || !preg_match('/^[_0-9-a-zA-Z]{1,20}$/i', trim($v['order_id']))) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_PO#'), $k + 2, 'PO#', 1, 20);
                break;
            }
            if (empty($v['ship_to_name'])) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_empty'), $k + 2, 'Customer Name');
                break;
            }
            if (strlen($v['ship_to_name']) > 50) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_length'), $k + 2, 'Customer Name', 1, 50);
                break;
            }
            if (empty($v['ship_to_phone'])) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_empty'), $k + 2, 'Customer Phone Number');
                break;
            }
            if (strlen($v['ship_to_phone']) > 50) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_length'), $k + 2, 'Customer Phone Number', 1, 50);
                break;
            }
            if (empty($v['ship_to_address1'])) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_empty'), $k + 2, 'Ship to Address 1');
                break;
            }
            $len = $this->config->get('config_b2b_address_len'); // 公共长度字段
            if ((StringHelper::stringCharactersLen($v['ship_to_address1']) + StringHelper::stringCharactersLen($v['ship_to_address2'])) > $len) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_length'), $k + 2, 'Ship to Address ', 1, $len);
                break;
            }
            if (empty($v['city'])) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_empty'), $k + 2, 'City');
                break;
            }
            if (strlen($v['city']) > 50) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_length'), $k + 2, 'City', 1, 50);
                break;
            }
            if (empty($v['state'])) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_empty'), $k + 2, 'State');
                break;
            }
            if (strlen($v['state']) > 50) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_length'), $k + 2, 'State', 1, 50);
                break;
            }
            if (empty($v['zip'])) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_empty'), $k + 2, 'Zip');
                break;
            }
            if (strlen($v['zip']) > 50) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_length'), $k + 2, 'Zip', 1, 50);
                break;
            }
            if (empty($v['ship_node'])) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_empty'), $k + 2, 'Ship Node');
                break;
            }
            if (strlen($v['ship_node']) > 20) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_length'), $k + 2, 'Ship Node', 1, 20);
                break;
            }
            if (empty($v['line'])) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_empty'), $k + 2, 'Line#');
                break;
            }
            if ($v['line'] == '' || !preg_match('/^[1-9][0-9]*$/', $v['line']) || $v['line'] > 99) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_number'), $k + 2, 'Line#', 1, 99);
                break;
            }
            if (empty($v['platform_sku'])) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_empty'), $k + 2, 'SKU');
                break;
            }
            if (strlen($v['platform_sku']) > 50) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_length'), $k + 2, 'SKU', 1, 50);
                break;
            }

            //校验平台SKU是否已与B2B sku建立关联关系
            $b2bSku = $this->verifyPlatformSku($v['platform_sku'], $customerId, HomePickPlatformType::WALMART);
            if (!$b2bSku) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_map'), $k + 2, 'SKU') .
                    '<a href="index.php?route=account/mapping_management" style="cursor: pointer;text-decoration: underline;" target="_blank" >Go to create/modify the mapping.</a>';
                break;
            }
            $lineTransferSku[$k + 2] = $b2bSku;
            if (empty($v['qty'])) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_empty'), $k + 2, 'Qty');
                break;
            }
            //注意：产品数量正整数 import 7
            if (!preg_match('/^[1-9][0-9]*$/', $v['qty']) || $v['qty'] > 999) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_number'), $k + 2, 'Qty', 1, 999);
                break;
            }
            if (empty($v['ship_to'])) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_empty'), $k + 2, 'Ship To');
                break;
            }
            if (!in_array($v['ship_to'], ['Home', 'Store'])) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_valid'), $k + 2, 'Ship To');
                break;
            } elseif ($v['ship_to'] == 'Store') {
                // 此订单下第几行为Store
                $shipTo[$v['order_id']][$k + 2] = 'Store';
            } else {
                $shipTo[$v['order_id']][$k + 2] = 'Home';
            }
            if (empty($v['requested_carrier_method']) && empty($v['carrier'])) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_carrier_empty'), $k + 2, 'Requested Carrier Method', 'Carrier');
                break;
            }
            if (strlen($v['requested_carrier_method']) > 50 && $v['carrier'] > 50) {
                $flag = false;
                $name = $v['carrier'] ? 'Requested Carrier Method' : 'Carrier';
                $err = sprintf($this->language->get('error_walmart_field_length'), $k + 2, $name, 1, 50);
                break;
            }
            $code_list = [];
            foreach (WALMART_VERIFY_TYPES as $ks => $vs) {
                $name = $v['carrier'] ? $v['carrier'] : $v['requested_carrier_method'];
                if (strtolower($name) == strtolower($vs)) {
                    $code_list[] = $vs;
                }
            }
            $order_ship_method[trim($v['order_id'])][$k] = $code_list;

            $warehouse_flag = 0;
            $warehouseId = 0;
            $LTLTypeItems = HomePickCarrierType::getWalmartLTLTypeViewItems();
            foreach ($LTLTypeItems as $ks => $vs) {
                $name = $v['carrier'] ? $v['carrier'] : $v['requested_carrier_method'];
                if (strtolower($name) == strtolower($vs)) {
                    $warehouse_flag = 1;
                    break;
                }
            }
            if ($warehouse_flag) {
                //校验warehouse已建立关联关系
                $res = $this->verifyWarehouseIsExist($v['ship_node'], $customerId, HomePickPlatformType::WALMART);
                if (!$res) {
                    $flag = false;
                    $err = sprintf($this->language->get('error_walmart_field_map'), $k + 2, 'Ship Node') .
                        '<a href="index.php?route=account/mapping_management" style="cursor: pointer;text-decoration: underline;" target="_blank" >Go to create/modify the mapping.</a>';
                    break;
                } else {
                    $warehouseId = $res;
                }
            }
            $retError = app(CustomerSalesOrderRepository::class)->checkHomePickLTLTypeWarehouse(HomePickImportMode::IMPORT_MODE_WALMART, $k + 2, $v['platform_sku'], $b2bSku, $v['ship_node'], $v['carrier'], $countryId);
            if ($retError) {
                $flag = false;
                $err = $retError;
                break;
            }
            if ($v['ship_to'] == 'Store' && empty($v['package_asn'])) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_s2s_asn'), $k + 2, 'Package ASN');
                break;
            }
            // ship_to 需要保证store_id不为空
            if ($v['ship_to'] == 'Store' && empty($v['store_id'])) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_s2s_asn'), $k + 2, 'Store ID');
                break;
            }
            //判断订单号的唯一性 不可重复导入
            if ($this->judgeOrderIsExist($v['order_id'], $customerId, 'tb_sys_customer_sales_order')) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_po'), $k + 2, 'PO#');
                break;
            }
            $orderSkuStr = $v['order_id'] . '_' . $v['platform_sku'];
            $orderLineStr = $v['order_id'] . '_' . $v['line'];
            //excel导入的数据中 PO#+SKU唯一
            if (in_array($orderSkuStr, $orderSkuArr)) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_po_sku'), $k + 2, $v['order_id']);
                break;
            }
            $orderSkuArr[] = $orderSkuStr;
            if (in_array($orderLineStr, $orderLineArr)) {
                $flag = false;
                $err = sprintf($this->language->get('error_walmart_field_line'), $k + 2, 'Line#', $v['order_id']);
                break;
            }
            $orderLineArr[] = $orderLineStr;
            if ($warehouse_flag) {
                $warehouse_list[trim($v['order_id'])][] = $warehouseId;
                $warehouse_code_map[trim($v['order_id'])][] = $v['ship_node'];
                $data[$k]['warehouse_id'] = $warehouseId;
            } else {
                $data[$k]['warehouse_id'] = '';
            }
            $data[$k]['b2b_sku'] = $b2bSku;

        }
        if (!$err) {
            if (isset($warehouse_list)) {
                foreach ($warehouse_list as $key => $value) {
                    if (count(array_unique($value)) > 1) {
                        $flag = false;
                        $err .= 'PO# [' . $key . '] Ship Node is not unique,it has ' . implode(',', array_unique($warehouse_code_map[$key])) . '<br/>';
                    }
                }
            }
            foreach ($order_ship_method as $key => $value) {
                foreach ($value as $k => $v) {
                    if (count($v) > 1) {
                        $flag = false;
                        $err .= sprintf($this->language->get('error_walmart_field_format'), $k + 2, 'Carrier/Requested Carrier Method') . '<br/>';
                    } elseif (count($v) == 0) {
                        $flag = false;
                        $err .= sprintf($this->language->get('error_walmart_field_valid'), $k + 2, 'Carrier/Requested Carrier Method') . '<br/>';
                    } else {
                        $order_ship_method_list[$key][] = $v[0];
                    }
                }
            }
            if (isset($order_ship_method_list)) {
                foreach ($order_ship_method_list as $key => $value) {
                    if (count(array_unique($value)) > 1) {
                        $flag = false;
                        $err .= 'PO#: [' . $key . '] one order is not allowed to have different carriers.' . '<br/>';

                    }
                }
            }
            //一个订单中只要有一个产品的ship to是store，订单中其他产品对应的ship to字段就必须为store

            if (isset($shipTo)) {
                foreach ($shipTo as $key => $value) {
                    if (count(array_unique($value)) != 1) {
                        $flag = false;
                        $store = '';
                        foreach ($value as $k => $v) {
                            if ($v == 'Home') {
                                $store .= $k . ',';
                            }
                        }
                        $err = sprintf($this->language->get('error_walmart_field_s2s_store'), trim($store, ','), 'Ship To');
                        break;
                    }
                }
            }

        }

        $skus = array_values($lineTransferSku);
        $verifyRet = app(salesOrderSkuValidate::class)->withSkus($skus)->validateSkus();
        if (!$verifyRet['code']) {
            $err .= sprintf($verifyRet['msg'], array_search($verifyRet['errorSku'], $lineTransferSku));
            $flag = false;
        }

        return [
            'flag' => $flag,
            'err' => $err,
            'data' => $data
        ];
    }

    /**
     * [verifyPlatformSku description]
     * @param string $sku
     * @param int $customer_id
     * @param $platform_id 1 wayfair  2 amazon  3 walmart
     * @return string
     */
    public function verifyPlatformSku($sku, $customer_id, $platform_id = 2)
    {
        $map = [
            ['customer_id', '=', $customer_id],
            ['platform_sku', '=', $sku],
            ['status', '=', 1],
            ['platform_id', '=', $platform_id],
        ];
        return strval($this->orm->table(DB_PREFIX . 'mapping_sku')->where($map)->value('sku'));

    }

    public function verifyCommonOrderCsvByMapping($data)
    {
        // 1. 所有订单验证sku和订单是否唯一
        $order = [];
        $err = '';
        $lineTransferSku = [];
        foreach ($data as $key => $value) {
            //注意：产品数量正整数 import 6
            if ($value['ShipToQty'] == '' || !preg_match('/^[1-9][0-9]*$/', $value['ShipToQty'])) {
                $err .= 'Line' . ($key + 3) . ",ShipToQty format error." . '<br/>';
            }

            $order_sku_key = trim($value['OrderId']) . '_' . trim($value['B2BItemCode']);
            $order[$order_sku_key][] = $key + 3;
            $lineTransferSku[$key + 3] = $value['B2BItemCode'];
        }
        foreach ($order as $key => $value) {
            if (count($value) > 1) {
                $err .= 'This file has the same order details. Number of lines:' . implode(',', $value) . '<br/>';
            }
        }

        $skus = array_values($lineTransferSku);
        $verifyRet = app(salesOrderSkuValidate::class)->withSkus($skus)->validateSkus();
        if (!$verifyRet['code']) {
            $err .= sprintf($verifyRet['msg'], array_search($verifyRet['errorSku'], $lineTransferSku));
        }
        return $err;

    }

    /**
     * [verifyWarehouseIsExist description] 验证warehouse 是否存在
     * @param string $code
     * @param int $customer_id BuyerId
     * @param int $platform_id 1 wayfair  2 amazon
     * @return int
     */
    public function verifyWarehouseIsExist($code, $customer_id, $platform_id = 2)
    {
        $map = [
            ['customer_id', '=', $customer_id],
            ['platform_warehouse_name', '=', $code],
            ['status', '=', 1],
            ['platform_id', '=', $platform_id],
        ];
        return intval($this->orm->table(DB_PREFIX . 'mapping_warehouse')->where($map)->value('warehouse_id'));
    }

    /**
     * [initPretreatmentDropshipCsv description] 预处理csv 过滤出不需要的order
     * @param $data
     * @param int $country_id
     * @return array
     * @throws Exception
     */
    public function initPretreatmentDropshipCsv($data, $country_id = AMERICAN_COUNTRY_ID)
    {
        //验证item_code是否存在
        //item_code 和 warehouse 对应转换 oc_mapping_warehouse
        $order_undo = [];
        $order_do = [];
        $this->load->model('catalog/product');
        foreach ($data as $key => $value) {
            $item_code = trim($value['SKU']);
            $product_id = $this->judgeSkuIsExist($value['SKU'], $country_id);
            if (isset($order_undo[$value['Order ID']])) {
                //放置数据
                $tmp['item_code'] = $value['SKU'];
                $tmp['qty'] = $value['Item Quantity'];
                $tmp['tag_array'] = $this->model_catalog_product->getProductTagHtmlForThumb(
                    $product_id
                );
                $tmp['tracking_number'] = $value['Tracking ID'];
                $tmp['sale_order_id'] = $value['Order ID'];
                $order_undo[$value['Order ID']][] = $tmp;
                if (isset($order_do[$value['Order ID']])) {
                    //遇到的时候直接处理
                    foreach ($order_do[$value['Order ID']] as $ks => $vs) {
                        unset($data[$vs['key']]);
                        $order_undo[$value['Order ID']][] = $vs;
                    }
                    unset($order_do[$value['Order ID']]);

                }

                unset($tmp);
                unset($data[$key]);

            } else {

                if (null == $product_id) {
                    $tmp['item_code'] = $item_code;
                    $tmp['qty'] = $value['Item Quantity'];
                    $tmp['tag_array'] = $this->model_catalog_product->getProductTagHtmlForThumb(
                        $product_id
                    );
                    $tmp['tracking_number'] = $value['Tracking ID'];
                    $tmp['sale_order_id'] = $value['Order ID'];
                    $order_undo[$value['Order ID']][] = $tmp;
                    if (isset($order_do[$value['Order ID']])) {
                        //遇到的时候直接处理
                        foreach ($order_do[$value['Order ID']] as $ks => $vs) {
                            unset($data[$vs['key']]);
                            $order_undo[$value['Order ID']][] = $vs;
                        }
                        unset($order_do[$value['Order ID']]);

                    }
                    unset($tmp);
                    unset($data[$key]);

                } else {
                    $tmp['item_code'] = $item_code;
                    $tmp['qty'] = $value['Item Quantity'];
                    $tmp['tag_array'] = $this->model_catalog_product->getProductTagHtmlForThumb(
                        $product_id
                    );
                    $tmp['tracking_number'] = $value['Tracking ID'];
                    $tmp['sale_order_id'] = $value['Order ID'];
                    $tmp['key'] = $key;
                    $order_do[$value['Order ID']][] = $tmp;
                    if (isset($order_undo[$value['Order ID']])) {
                        //遇到的时候直接处理
                        foreach ($order_do[$value['Order ID']] as $ks => $vs) {
                            unset($data[$vs['key']]);
                            $order_undo[$value['Order ID']][] = $vs;
                        }
                        unset($order_do[$value['Order ID']]);

                    }
                    unset($tmp);

                }
            }
        }

        $result['data'] = $data;
        if ($order_undo) {
            foreach ($order_undo as $key => $value) {
                foreach ($value as $k => $v) {
                    $undo[] = $v;
                }
            }
            $undo_amount = count($order_undo);
        } else {
            $undo = null;
            $undo_amount = 0;
        }

        if ($order_do) {
            foreach ($order_do as $key => $value) {
                foreach ($value as $k => $v) {
                    $do[] = $v;
                }
            }
            $do_amount = count($order_do);
        } else {
            $do = null;
            $do_amount = 0;
        }
        $undo_all['list'] = $undo;
        $undo_all['amount'] = $undo_amount;
        $do_all['list'] = $do;
        $do_all['amount'] = $do_amount;
        $result['order_do'] = $do_all;
        $result['order_undo'] = $undo_all;
        return $result;

    }

    /**
     * [getValidProductIdBySku description]
     * @param $item_code
     * @return string
     */
    public function getValidProductIdBySku($item_code)
    {
        $map = [
            ['sku', '=', $item_code],
            ['status', '=', 1],
            ['buyer_flag', '=', 1],
        ];
        return $this->orm->table(DB_PREFIX . 'product')->where($map)->orderBy('product_id', 'desc')->value('product_id');
    }


    public function initPretreatmentWayFairCsv($data, $country_id = AMERICAN_COUNTRY_ID)
    {
        //验证Item Number是否存在
        //Item Number 和 PO Number
        $order_undo = [];
        $order_do = [];
        $this->load->model('catalog/product');
        foreach ($data as $key => $value) {
            $item_code = $value['item_code'];
            $productId = $this->judgeSkuIsExist($item_code, $country_id);
            if (isset($order_undo[$value['PO Number']])) {
                //放置数据
                $tmp['item_code'] = $item_code;
                $tmp['tag_array'] = $this->model_catalog_product->getProductTagHtmlForThumb(
                    $productId
                );
                $tmp['item_code_pre'] = $value['Item Number'];
                $tmp['qty'] = $value['Quantity'];
                $tmp['carrier_name'] = $value['Carrier Name'];
                $tmp['sale_order_id'] = $value['PO Number'];
                $order_undo[$value['PO Number']][] = $tmp;
                if (isset($order_do[$value['PO Number']])) {
                    //遇到的时候直接处理
                    foreach ($order_do[$value['PO Number']] as $ks => $vs) {
                        unset($data[$vs['key']]);
                        $order_undo[$value['PO Number']][] = $vs;
                    }
                    unset($order_do[$value['PO Number']]);

                }

                unset($tmp);
                unset($data[$key]);

            } else {

                if (null == $productId) {
                    $tmp['item_code'] = $item_code;
                    $tmp['tag_array'] = $this->model_catalog_product->getProductTagHtmlForThumb(
                        $productId
                    );
                    $tmp['item_code_pre'] = $value['Item Number'];
                    $tmp['qty'] = $value['Quantity'];
                    $tmp['carrier_name'] = $value['Carrier Name'];
                    $tmp['sale_order_id'] = $value['PO Number'];
                    $order_undo[$value['PO Number']][] = $tmp;
                    if (isset($order_do[$value['PO Number']])) {
                        //遇到的时候直接处理
                        foreach ($order_do[$value['PO Number']] as $ks => $vs) {
                            unset($data[$vs['key']]);
                            $order_undo[$value['PO Number']][] = $vs;
                        }
                        unset($order_do[$value['PO Number']]);

                    }
                    unset($tmp);
                    unset($data[$key]);

                } else {
                    $tmp['item_code'] = $item_code;
                    $tmp['tag_array'] = $this->model_catalog_product->getProductTagHtmlForThumb(
                        $productId
                    );
                    $tmp['item_code_pre'] = $value['Item Number'];
                    $tmp['qty'] = $value['Quantity'];
                    $tmp['carrier_name'] = $value['Carrier Name'];
                    $tmp['sale_order_id'] = $value['PO Number'];
                    $tmp['key'] = $key;
                    $order_do[$value['PO Number']][] = $tmp;
                    if (isset($order_undo[$value['PO Number']])) {
                        //遇到的时候直接处理
                        foreach ($order_do[$value['PO Number']] as $ks => $vs) {
                            unset($data[$vs['key']]);
                            $order_undo[$value['PO Number']][] = $vs;
                        }
                        unset($order_do[$value['PO Number']]);

                    }
                    unset($tmp);

                }
            }
        }
        $result['data'] = $data;
        if ($order_undo) {
            foreach ($order_undo as $key => $value) {
                foreach ($value as $k => $v) {
                    $undo[] = $v;
                }
            }
            $undo_amount = count($order_undo);
        } else {
            $undo = null;
            $undo_amount = 0;
        }
        if ($order_do) {
            foreach ($order_do as $key => $value) {
                foreach ($value as $k => $v) {
                    $do[] = $v;
                }
            }
            $do_amount = count($order_do);
        } else {
            $do = null;
            $do_amount = 0;
        }
        $undo_all['list'] = $undo;
        $undo_all['amount'] = $undo_amount;
        $do_all['list'] = $do;
        $do_all['amount'] = $do_amount;
        $result['order_do'] = $do_all;
        $result['order_undo'] = $undo_all;
        return $result;
    }

    /**
     * [verifyProductSkuIsExists description] 验证sku是否存在
     * @param string $sku
     * @return array
     */
    public function verifyProductSkuIsExists($sku)
    {
        return $this->orm->table(DB_PREFIX . 'product')->where('sku', $sku)->value('product_id');
    }

    public function getCustomerSalesIdByOrderId($order_id, $buyer_id)
    {
        return $this->db->query("SELECT id FROM tb_sys_customer_sales_order WHERE order_id='" . $order_id . "' and buyer_id=" . $buyer_id)->row['id'];
    }

    /**
     * 云送仓绑定
     * @param int $order_id
     * @param int $header_id
     * @return array
     */
    public function associateOrderForCWF($order_id, $header_id)
    {
        $orderInfos = $this->db->query("SELECT * FROM oc_order_product WHERE order_id=" . $order_id)->rows;
        //获取绑定关系的Id
        $orderAssociatedIds = [];
        foreach ($orderInfos as $orderInfo) {
            $lineQty = $this->db->query("SELECT * FROM tb_sys_customer_sales_order_line WHERE header_id=" . $header_id . " and product_id=" . $orderInfo['product_id'])->row;
            $discountsAmount = app(OrderService::class)->orderProductWillAssociateDiscountsAmount(intval($orderInfo['order_product_id']), intval($lineQty['qty']), $this->customer->isJapan() ? 0 : 2);
            $this->db->query("INSERT INTO tb_sys_order_associated
          (sales_order_id,sales_order_line_id,order_id,order_product_id,qty,product_id,seller_id,buyer_id,image_id,CreateUserName,CreateTime,UpdateTime,ProgramCode,coupon_amount,campaign_amount)
          values(" . $lineQty['header_id'] . "," . $lineQty['id'] . "," . $orderInfo['order_id'] . "," . $orderInfo['order_product_id'] . "," . $lineQty['qty'] . "," . $orderInfo['product_id'] . "," . $lineQty['seller_id'] . "," . $lineQty['create_user_name'] . ",NULL" . ",'php_purchase'" . ",NOW(),NOW(),'V1.0',{$discountsAmount['coupon_amount']}, {$discountsAmount['campaign_amount']})");
            $orderAssociatedIds[] = $this->db->getLastId();
        }
        return $orderAssociatedIds;
    }

    public function updateOrderCloudLogistics($order_id, $header_id, $logistics_id)
    {
        $this->db->query("update oc_order_cloud_logistics set order_id=" . $order_id . ",sales_order_id=" . $header_id . " where id=" . $logistics_id);
    }

    public function updateCustomerSalesOrderLineIsExported($header_id)
    {
        $this->db->query("update tb_sys_customer_sales_order_line set is_exported=1,exported_time= now(),is_synchroed=1,synchroed_time = now() where header_id=" . $header_id);
    }

    public function initPretreatmentWalmart($data)
    {
        $order_undo = [];
        $order_do = [];
        $this->load->model('catalog/product');
        foreach ($data as $key => $value) {

            //放置数据
            $tmp['item_code'] = $value['b2b_sku'];
            $tmp['tag_array'] = $this->model_catalog_product->getProductTagHtmlForThumb(
                $this->getValidProductIdBySku($value['b2b_sku'])
            );
            $tmp['item_code_pre'] = $value['platform_sku'];
            $tmp['qty'] = $value['qty'];
            $tmp['carrier_name'] = empty($value['carrier']) ? $value['requested_carrier_method'] : $value['carrier'];
            $tmp['sale_order_id'] = $value['order_id'];

            if (isset($order_undo[$value['order_id']])) {

                $order_undo[$value['order_id']][] = $tmp;
                if (isset($order_do[$value['order_id']])) {
                    //遇到的时候直接处理
                    foreach ($order_do[$value['order_id']] as $ks => $vs) {
                        unset($data[$vs['key']]);
                        $order_undo[$value['order_id']][] = $vs;
                    }
                    unset($order_do[$value['order_id']]);

                }
                unset($tmp);
                unset($data[$key]);

            } else {

                $res = $this->verifyProductSkuIsExists($tmp['item_code']);
                if (null == $res) {
                    $order_undo[$value['order_id']][] = $tmp;
                    if (isset($order_do[$value['order_id']])) {
                        //遇到的时候直接处理
                        foreach ($order_do[$value['order_id']] as $ks => $vs) {
                            unset($data[$vs['key']]);
                            $order_undo[$value['order_id']][] = $vs;
                        }
                        unset($order_do[$value['order_id']]);
                    }
                    unset($tmp);
                    unset($data[$key]);

                } else {
                    $tmp['key'] = $key;
                    $order_do[$value['order_id']][] = $tmp;
                    if (isset($order_undo[$value['order_id']])) {
                        //遇到的时候直接处理
                        foreach ($order_do[$value['order_id']] as $ks => $vs) {
                            unset($data[$vs['key']]);
                            $order_undo[$value['order_id']][] = $vs;
                        }
                        unset($order_do[$value['order_id']]);
                    }
                    unset($tmp);

                }
            }
        }
        $result['data'] = $data;
        if ($order_undo) {
            foreach ($order_undo as $key => $value) {
                foreach ($value as $k => $v) {
                    $undo[] = $v;
                }
            }
            $undo_amount = count($order_undo);
        } else {
            $undo = null;
            $undo_amount = 0;
        }
        if ($order_do) {
            foreach ($order_do as $key => $value) {
                foreach ($value as $k => $v) {
                    $do[] = $v;
                }
            }
            $do_amount = count($order_do);
        } else {
            $do = null;
            $do_amount = 0;
        }
        $undo_all['list'] = $undo;
        $undo_all['amount'] = $undo_amount;
        $do_all['list'] = $do;
        $do_all['amount'] = $do_amount;
        $result['order_do'] = $do_all;
        $result['order_undo'] = $undo_all;
        return $result;
    }

    /**
     * [verifyWayfairHeader description]
     * @param $csv_data
     * @param int $country_id
     * @return bool
     */
    public function verifyWayfairHeader($csv_data, $country_id)
    {
        if ($country_id == HomePickUploadType::BRITAIN_COUNTRY_ID || $country_id == HomePickUploadType::GERMANY_COUNTRY_ID) {
            $csv_header = [
                'Warehouse Name',            // 发货仓库名称
                'Store Name',                // 销售平台 SalesPlatform原
                'PO Number',                 // 订单号
                'PO Date',                   //
                'Must Ship By',              //
                'Backorder Date',            //
                'Order Status',              // 订单状态
                'Item Number',               // sku product中 sku B2BItemCode
                'Item Name',                 // product name
                'Quantity',                  // 数量  ShipToQty
                'Wholesale Price',           // BuyerSkuCommercialValue
                'Ship Method',               // 装运方法
                'Carrier Name',              // 物流名称
                'Shipping Account Number',   // 只保存在数据库，不在任何地方展示
                'Ship To Name',              // 收货方名称
                'Ship To Address',           // 地址1
                'Ship To Address 2',         // 地址2
                'Ship To City',              // 收货方城市
                'Ship To State',             // 收货州 （翻译）
                'Ship To Zip',               // 收货方邮政编码
                'Ship To Phone',             // 手机号码
                'Inventory at PO Time',
                'Inventory Send Date',
                'Ship Speed',
                'PO Date & Time',
                'Registered Timestamp',
                'Customization Text',
                'Event Name',
                'Event ID',
                'Event Start Date',
                'Event End Date',
                'Event Type',
                'Backorder Reason',
                'Original Product ID',
                'Original Product Name',
                'Event Inventory Source',
                'Packing Slip URL',
                'Tracking Number',                // Tracking Number不必填
                'Ready for Pickup Date',
                'SKU',
                'Destination Country',       // ShipToCountry 收货国
                'Depot ID',
                'Depot Name',
                'Wholesale Event Source',
                'Wholesale Event Store Source',
                'B2BOrder',
                'Composite Wood Product',
                'Sales Channel',
            ];
            if (isset($csv_data['keys']) && $csv_data['keys'] == $csv_header) {
                return true;
            }
        }

        return false;
    }


    public function getPurchaseOrderInfoById($id)
    {
        return $this->orm->table('tb_sys_customer_sales_order')
            ->where('id', $id)
            ->select('order_id', 'run_id', 'import_mode')
            ->first();
    }

    public function checkHomePickUploadFile(UploadedFile $fileInfo)
    {
        $json = [];
        //检测文件合法信息
        if ($fileInfo->isValid()) {
            $fileType = $fileInfo->getClientOriginalExtension();
            if (!in_array(strtolower($fileType), ControllerAccountCustomerOrder::PDF_SUFFIX)) {
                $json['error'] = $this->language->get('error_filetype');
            }

            if ($fileInfo->getError() != UPLOAD_ERR_OK) {
                $json['error'] = $this->language->get('error_upload_' . $fileInfo->getError());
            }
        } else {
            $json['error'] = $this->language->get('error_upload');
        }
        return $json;
    }

    public function insertDropshipUploadFile($fileData)
    {
        return $this->orm->table('tb_sys_customer_sales_dropship_upload_file')->insertGetId($fileData);
    }

    public function getCarrierInfoFromTempTable($import_mode, $temp_id)
    {
        if ($import_mode == HomePickImportMode::IMPORT_MODE_WALMART) {
            //amazon
            $temp = $this->orm->table('tb_sys_customer_sales_walmart_temp')
                ->where('id', $temp_id)
                ->select('item_code', 'carrier', 'requested_carrier_method')
                ->first();
            $sku = $temp->item_code;
            $ship_method_code = $temp->carrier;
            $ship_method = $temp->requested_carrier_method;
            $ship_speed = null;

        } elseif ($import_mode == HomePickImportMode::IMPORT_MODE_WAYFAIR) {
            //wayfair
            $temp = $this->orm->table('tb_sys_customer_sales_wayfair_temp')
                ->where('id', $temp_id)
                ->select('item_code', 'carrier_name', 'ship_method', 'ship_speed')
                ->first();
            $sku = $temp->item_code;
            $ship_method_code = $temp->carrier_name;
            $ship_method = $temp->ship_method;
            $ship_speed = $temp->ship_speed;
        } else {
            //dropship
            $temp = $this->orm->table('tb_sys_customer_sales_dropship_temp')
                ->where('id', $temp_id)
                ->select('sku', 'ship_method_code', 'ship_method')
                ->first();
            $sku = $temp->sku;
            $ship_method_code = $temp->ship_method_code;
            $ship_method = $temp->ship_method;
            $ship_speed = null;
        }

        return [
            'sku' => $sku,
            'ship_method_code' => $ship_method_code,
            'ship_method' => $ship_method,
            'ship_speed' => $ship_speed,
        ];
    }

    /**
     * [checkComboAmountBySku description]
     * @param string $sku
     * @return bool
     */
    public function checkComboAmountBySku(string $sku): bool
    {
        return ProductSetInfo::query()->alias('s')
            ->leftJoinRelations(['product as p'])
            ->where('p.sku', $sku)
            ->whereNotNull('s.set_product_id')
            ->exists();

    }

    public function getSkuByProductId($product_id)
    {
        return $this->orm->table(DB_PREFIX . 'product')->where('product_id', $product_id)->value('sku');
    }

    public function updateDropshipUploadFile($condition, $update)
    {
        $this->orm->table('tb_sys_customer_sales_dropship_upload_file')
            ->where($condition)
            ->update($update);
    }


    /**
     * 根据buyer_id取所有订单的Order From去重
     * @param int $buyer_id
     * @return array
     */
    public function getImportModelListByBuyerid($buyer_id)
    {
        $buyer_id = intval($buyer_id);
        $sql = "
    SELECT
        import_mode
    FROM tb_sys_customer_sales_order
    WHERE
        buyer_id={$buyer_id}
    GROUP BY import_mode
    ORDER BY import_mode ASC";
        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getComboInfoBySku($sku)
    {
        $productId = $this->orm->table(DB_PREFIX . 'product as ts')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'ts.product_id')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'ctp.customer_id')
            ->where('ts.sku', $sku)
            ->where([
                'ts.status' => 1,
                'ts.is_deleted' => 0,
                'c.status' => 1,
            ])
            ->orderBy('ts.product_id', 'desc')
            ->value('ts.product_id');
        if ($productId == null) {
            $productId = $this->orm->table(DB_PREFIX . 'product as ts')
                ->where('ts.sku', $sku)
                ->orderBy('ts.product_id', 'desc')
                ->value('ts.product_id');
        }
        return $this->orm->table('tb_sys_product_set_info as s')
            ->where('p.product_id', $productId)
            ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 's.product_id')
            ->leftJoin(DB_PREFIX . 'product as pc', 'pc.product_id', '=', 's.set_product_id')
            ->leftJoin(DB_PREFIX . 'weight_class_description as wcd', 'pc.weight_class_id', '=', 'wcd.weight_class_id')
            ->leftJoin(DB_PREFIX . 'weight_class_description as wcd_p', 'p.weight_class_id', '=', 'wcd_p.weight_class_id')
            ->whereNotNull('s.set_product_id')
            ->orderBy('pc.sku', 'asc')
            ->select('s.set_product_id', 's.qty', 'pc.sku', 'wcd.unit as unit_name', 'wcd_p.unit as all_unit_name')
            ->selectRaw('round(pc.weight,2) as weight,round(p.weight,2) as all_weight')
            ->get()
            ->map(function ($vs) {
                return (array)$vs;
            })
            ->toArray();

    }


    /**
     * [updatePurchaseOrderLineComboInfo description]
     * @param int $order_id
     * @param int $customer_id
     * @param int|string|null $run_id
     * date:2020/10/23 9:50
     */
    public function updatePurchaseOrderLineComboInfo($order_id, $customer_id, $run_id = null)
    {
        if ($run_id) {
            $map = [
                'o.run_id' => $run_id,
                'o.buyer_id' => $customer_id,
            ];
        } else {
            $map = [
                'o.id' => $order_id,
                'o.buyer_id' => $customer_id,
            ];
        }
        $ret = $this->orm->table('tb_sys_customer_sales_order_line as l')
            ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.header_id')
            ->where($map)
            ->select('l.id', 'l.item_code', 'l.qty')
            ->groupBy('l.id')
            ->get()
            ->map(function ($v) {
                return (array)$v;
            })
            ->toArray();
        $sku = [];
        foreach ($ret as $key => $value) {
            if (isset($sku[$value['item_code']])) {
                $product_id = $sku[$value['item_code']];
            } else {
                $product_id = $this->getFirstProductId($value['item_code'], $customer_id);
                $sku[$value['item_code']] = $product_id;
            }
            $combo_info = $this->sales_model->getSalesOrderLineComboInfo($product_id, 0, $value['qty']);
            if ($combo_info['combo_info']) {
                $combo_info['combo_info'] = json_encode($combo_info['combo_info']);
                $this->orm->table('tb_sys_customer_sales_order_line')
                    ->where('id', $value['id'])
                    ->update([
                        'label_combo_info' => $combo_info['combo_info'],
                    ]);
            }
        }
    }

    /**
     * [dealWithFileData description]
     * @param $data
     * @param $runId
     * @param int $importMode
     * @param int $customer_id
     * @param int $country_id
     * @return string
     */
    public function dealWithFileData($data, $runId, $importMode, $customer_id, $country_id)
    {
        //验证首行和第二行数据是否正确
        $verify_ret = $this->verifyFileDataFirstLine($data, $importMode);
        if ($verify_ret) {
            return $verify_ret;
        }
        //验证订单是否含有重复的order_id 和 sku 组合
        // 验证同一订单不允许有多个发货仓库
        $unique_ret = $this->verifyFileDataOrderUnique($data, $importMode, $country_id);
        if ($unique_ret['error']) {
            return $unique_ret['error'];
        }
        //验证并插入数据
        $column_ret = $this->insertFileInfoData($unique_ret['data'], $importMode, $runId, $country_id, $customer_id);
        if ($column_ret !== true) {
            return $column_ret;
        }
    }


    public function verifyFileDataFirstLine($data, $importMode)
    {
        $error = '';
        if ($importMode == HomePickImportMode::US_OTHER) {
            $excel_header = [
                '*Platform',                    // 销售平台
                '*Sales Order ID',              // 订单号
                '*B2B Item Code',               // sku
                '*Quantity',                    // sku数量
                '*Ship Method',                 // 运输类型
                '*Carrier',                     // 物流方式
                'B2B Warehouse Code',           // B2B仓库
                '*Customer Name',               // Buyer name
                'Customer Email',               // Buyer email
                '*Customer Phone Number',       // Buyer 电话
                '*Ship to Address 1',           // 发货地址
                'Ship to Address 2',            // 发货地址2
                '*Ship To City',                //
                '*Ship To State',               //
                '*Ship To ZIP Code',            //
                '*Ship To Country',             //
                'Tracking Number',              //
                'Remark',                       //
            ];
            // 验证第二行数据与给出数据是否相等
            if (!isset($data[0]) || $data[0] != $excel_header) {
                return $this->language->get('error_file_content');
            }
            // 数据行数等于2行，证明为空数据，需要进行处理
            if (count($data) == 1) {
                return $this->language->get('error_file_empty');
            }

        }
        return $error;
    }

    public function insertFileInfoData($data, $importMode, $runId, $country_id, $customer_id)
    {
        if ($importMode == HomePickImportMode::US_OTHER) {
            $temp_table = 'tb_sys_customer_sales_order_other_temp';
            $order_mode = HomePickUploadType::ORDER_MODE_HOMEPICK;
            //已存在的订单OrderId
            $existentOrderIdArray = [];
            $verify_order = [];
            $orderArr = [];
            foreach ($data as $key => $value) {
                $flag = true;
                foreach ($value as $k => $v) {
                    if ($v != '') {
                        $flag = false;
                        break;
                    }
                }
                if ($flag) {
                    continue;
                }
                $column_ret = $this->verifyOtherPlatformLineColumn($value, $key + 2, $country_id);
                if ($column_ret !== true) {
                    return $column_ret;
                }
                // 校验是否已经是插入的订单了
                $checkResult = $this->judgeCommonOrderIsExist(trim($value['OrderId']), $customer_id);
                if ($checkResult) {
                    $existentOrderIdArray[] = trim($value['OrderId']);
                }

                $orderArr[] = [
                    "platform" => $value['platform'] == '' ? "" : ucwords(strtolower($value['platform'])),
                    "other_platform_id" => $value['other_platform_id'],
                    "sales_order_id" => $value['OrderId'] == '' ? null : trim($value['OrderId']),
                    "item_code" => $value['B2BItemCode'] ?? null, //已做大写处理
                    "quantity" => $value['Quantity'] ?? null,
                    "ship_method" => strtoupper($value['Ship Method']),
                    "carrier" => strtoupper($value['Carrier']),
                    "warehouse_code" => (!isset($value['warehouseCode']) || empty($value['warehouseCode'])) ? null : strtoupper($value['warehouseCode']),
                    "warehouse_name" => (!isset($value['warehouseCode']) || empty($value['warehouseCode'])) ? null : strtoupper($value['warehouseCode']),
                    "warehouse_id" => $value['warehouseId'],
                    "ship_to_name" => $value['Customer Name'] == '' ? null : $value['Customer Name'],
                    "ship_to_email" => $value['Customer Email'] == '' ? null : $value['Customer Email'],
                    "ship_to_phone" => $value['Customer Phone Number'] == '' ? null : $value['Customer Phone Number'],
                    "ship_to_address" => $value['Ship to Address 1'] == '' ? null : $value['Ship to Address 1'],
                    "ship_to_address2" => $value['Ship to Address 2'] == '' ? null : $value['Ship to Address 2'],
                    "ship_to_city" => $value['Ship To City'] == '' ? null : $value['Ship To City'],
                    "ship_to_state" => $value['Ship To State'] == '' ? null : $value['Ship To State'],
                    "ship_to_zip" => $value['Ship To ZIP Code'] == '' ? null : $value['Ship To ZIP Code'],
                    "ship_to_country" => $value['Ship To Country'] == '' ? null : $value['Ship To Country'],
                    "tracking_number" => $value['Tracking Number'] == '' ? null : $value['Tracking Number'],
                    "remark" => $value['Remark'] == '' ? null : $value['Remark'],
                    "buyer_id" => $customer_id,
                    "run_id" => $runId,
                    "create_user_name" => $customer_id,
                    "create_time" => date('Y-m-d H:i:s'),
                    "program_code" => PROGRAM_CODE
                ];

            }

            if (!empty($existentOrderIdArray)) {
                return 'Order ID:' . implode(',', array_unique($existentOrderIdArray)) . ' is already exist ,please check the uploaded file.';
            }
            try {
                $this->orm->getConnection()->beginTransaction();
                //插入临时表之前 不需要合并item code
                $this->insertTempTable($orderArr, $temp_table);
                // 根据RunId 和customer_id
                $orderTempArr = $this->getTempRecordByRunid($runId, $customer_id, $temp_table);
                //订单头表数据
                $customerSalesOrderArr = [];
                $yzc_order_id_number = $this->sales_model->getYzcOrderIdNumber();
                foreach ($orderTempArr as $key => $value) {
                    //导入订单根据order_id来进行合并
                    $order_id = $value['sales_order_id'];
                    $salesOrder = $this->getOtherOrderColumnNameConversion($value, $order_mode, $customer_id, $country_id, $importMode);
                    if (!isset($customerSalesOrderArr[$order_id])) {
                        $yzc_order_id_number++;
                        // 新订单头
                        //获取一个插入dropship和tb_sys_customer_sales_order的映射关系
                        $salesOrder['yzc_order_id'] = 'YC-' . $yzc_order_id_number;
                        $customerSalesOrderArr[$order_id] = $salesOrder;
                    } else {
                        //订单信息有变动需要更改
                        // line_count
                        // order_total
                        // line_item_number
                        $tmp = $salesOrder['product_info'][0];
                        $tmp['line_item_number'] = count($customerSalesOrderArr[$order_id]['product_info']) + 1;
                        $customerSalesOrderArr[$order_id]['line_count'] = count($customerSalesOrderArr[$order_id]['product_info']) + 1;
                        $customerSalesOrderArr[$order_id]['order_total'] += $tmp['item_price'] * $tmp['qty'];
                        $customerSalesOrderArr[$order_id]['order_total'] = sprintf('%.2f', $customerSalesOrderArr[$order_id]['order_total']);
                        $customerSalesOrderArr[$order_id]['product_info'][] = $tmp;
                    }

                }
                $this->sales_model->updateYzcOrderIdNumber($yzc_order_id_number);
                $ret = $this->insertCustomerSalesOrderAndLine($customerSalesOrderArr);
                $this->orm->getConnection()->commit();

            } catch (Exception $e) {
                Logger::app('导入订单错误，something wrong happened. Please try it again.');
                Logger::app($e);
                $this->orm->getConnection()->rollBack();
                $ret = $this->language->get('error_happened');
            }
            return $ret;
        }
    }

    public function verifyFileDataOrderUnique($data, $import_mode, $country_id)
    {

        $order = [];
        $ship_method = [];
        $warehouse = [];
        $carrier = [];
        $platform = [];
        $error = '';
        $lineTransferSku = [];
        //1. order & sku 唯一
        //2. order & ship method 唯一
        //3. order & warehouse 唯一
        //3. order & platform 唯一
        $this->load->model('account/mapping_warehouse');
        if ($import_mode == HomePickImportMode::US_OTHER) {
            //excel 需要处理数据
            // 数组结构重组
            $data = $this->formatFileData($data);
            foreach ($data as $key => &$value) {
                $value['warehouseId'] = null;
                if (isset($value['Sales Order ID'])) {
                    $value['OrderId'] = $value['Sales Order ID'];
                }
                if (isset($value['B2B Item Code']) && trim($value['B2B Item Code']) != '') {
                    $value['B2BItemCode'] = $value['B2B Item Code'];
                } else {
                    $error = 'Line' . ($key + 2) . ", [B2B Item Code] can not be left blank.";
                    break;
                }
                $lineTransferSku[$key + 2] = $value['B2BItemCode'];
                $flag = $this->judgeSkuIsExist(trim($value['B2BItemCode']), $country_id);
                if (!$flag) {
                    $error = 'Line' . ($key + 2) . ", [B2B Item Code] : Item '" . $value['B2BItemCode'] . "' does not exist.";
                    break;
                }
                //校验只要是当前国别存在的Item Code就可以
                $value['OrderId'] = get_need_string($value['OrderId'], ["'", '"', ' ']);
                $value['B2BItemCode'] = strtoupper($value['B2BItemCode']);
                $value['shipMethod'] = strtoupper($value['Ship Method']);
                $value['platform'] = strtoupper($value['Platform']);
                // 这里需要特别注意别名homedepot Lowes、Lowe’s、Lowe‘s
                if ($value['platform'] == strtoupper(HomePickCarrierType::ALIAS_HOME_DEPOT)) {
                    $value['platform'] = strtoupper(HomePickPlatformType::getOtherPlatformTypeViewItems()[HomePickPlatformType::OTHER_HOME_DEPOT]);
                }
                // LOWES 别名
                if (in_array($value['platform'], HomePickCarrierType::ALIAS_LOWES)) {
                    $value['platform'] = strtoupper(HomePickPlatformType::getOtherPlatformTypeViewItems()[HomePickPlatformType::OTHER_LOWES]);
                }

                // carrier 别名 ups fedex
                $value['carrier_origin'] = $value['Carrier'];
                if (in_array(strtoupper($value['Carrier']),
                    [
                        HomePickCarrierType::FEDEX,
                        HomePickCarrierType::FEDEX_GROUND,
                        HomePickCarrierType::FEDEX_HOME_DELIVERY,
                    ]
                )) {
                    $value['Carrier'] = HomePickCarrierType::FEDEX_GROUND_HOME_DELIVERY;
                }

                if (strtoupper($value['Carrier']) == HomePickCarrierType::UPS) {
                    $value['Carrier'] = HomePickCarrierType::UPS_GROUND;
                }

                if ($value['shipMethod'] == self::OTHER_SHIP_METHOD[0]) {
                    $value['warehouseCode'] = strtoupper($value['B2B Warehouse Code']);
                    //校验超大件是否符合
                    if (trim($value['warehouseCode']) == '') {
                        $error = 'Line' . ($key + 2) . ",[B2B Warehouse Code] can not be left blank.";
                        break;
                    }
                    $warehouse_flag = $this->model_account_mapping_warehouse->getWarehouseIsExist(trim($value['warehouseCode']), $country_id);
                    if (!$warehouse_flag) {
                        $error = 'Line' . ($key + 2) . ", [B2B Warehouse Code] : Item '" . $value['B2B Warehouse Code'] . "' does not exist.";
                        break;
                    } else {
                        $value['warehouseId'] = $warehouse_flag;
                    }
                }
                $order_sku_key = trim($value['OrderId']) . '|' . trim($value['B2BItemCode']);
                $order[$order_sku_key][] = $key + 2;
                $carrier[trim($value['OrderId'])][] = strtoupper($value['Carrier']);
                $ship_method[trim($value['OrderId'])]['shipMethod'][] = trim($value['shipMethod']);
                $ship_method[trim($value['OrderId'])]['key'][] = $key + 2;
                $platform[trim($value['OrderId'])]['platform'][] = trim($value['platform']);
                $platform[trim($value['OrderId'])]['key'][] = $key + 2;
                if (isset($value['warehouseCode'])) {
                    $warehouse[trim($value['OrderId'])]['warehouseCode'][] = trim($value['warehouseCode']);
                    $warehouse[trim($value['OrderId'])]['key'][] = $key + 2;
                }
                $retError = app(CustomerSalesOrderRepository::class)->checkHomePickLTLTypeWarehouse($import_mode, $key + 2, trim($value['B2BItemCode']), trim($value['B2BItemCode']), trim(strtoupper($value['B2B Warehouse Code'])), trim($value['carrier_origin']), $country_id);
                if ($retError) {
                    $error = $retError;
                    break;
                }
            }
        }

        $skus = array_values($lineTransferSku);
        $verifyRet = app(salesOrderSkuValidate::class)->withSkus($skus)->validateSkus();
        if (!$verifyRet['code']) {
            $error = sprintf($verifyRet['msg'], array_search($verifyRet['errorSku'], $lineTransferSku));
        }

        $ret['data'] = $data;
        $ret['error'] = $error;
        if ($error) {
            return $ret;
        }
        foreach ($order as $ks => $vs) {
            $tmp = explode('|', $ks);
            if (count($vs) > 1) {
                $ret['error'] = 'Line' . $vs[0] . ", Duplicate Item Code '" . $tmp[1] . "' within Sales Order ID '" . $tmp[0] . "'";
                return $ret;
            }
        }
        foreach ($platform as $ks => $vs) {
            if (count(array_unique($vs['platform'])) > 1) {
                $ret['error'] = "Order ID:'" . $ks . "' one order is not allowed to have different Platform.";
                return $ret;
            }
        }

        foreach ($warehouse as $ks => $vs) {
            if (count(array_unique($vs['warehouseCode'])) > 1) {
                $ret['error'] = "Order ID:'" . $ks . "' one order is not allowed to have different B2B Warehouse Code.";
                return $ret;
            }
        }

        foreach ($ship_method as $ks => $vs) {
            if (count(array_unique($vs['shipMethod'])) > 1) {
                $ret['error'] = "Order ID:'" . $ks . "' one order is not allowed to have different Ship Method.";
                return $ret;
            }
        }

        foreach ($carrier as $ks => $vs) {
            if (count(array_unique($vs)) > 1) {
                $ret['error'] = "Order ID: '" . $ks . "' one order is not allowed to have different Carrier.";
                return $ret;
            }
        }

        return $ret;

    }


    public function formatFileData($data)
    {
        $first_key = 0;
        $ret = [];
        array_walk($data[$first_key], function (&$value, $key) {
            $value = trim($value, '*');
        });
        foreach ($data as $key => $value) {
            if ($first_key != $key) {
                $ret[] = array_combine($data[$first_key], $value);
            }
        }
        return $ret;
    }

    /* 美国上门取件上传文件信息
   * @param array $map
   * @param int $is_edit
   * @return array
   */
    public function getUsPickUpOtherUploadFileInfo(array $map, int $is_edit)
    {
        //移除缓存
        $this->session->remove('container_id_list');
        $this->session->remove('bol');
        $this->session->remove('label_details');

        $bol_arr = [];
        $label_details_arr = [];
        $container_all_arr = [];
        $container_child_all_arr = [];

        //sales_order 信息
        $order_builder = $this->orm->table('tb_sys_customer_sales_order')
            ->where($map)
            ->select('id', 'order_id', 'order_status', 'ship_method', 'bol_path', 'orders_from', 'ship_service_level')
            ->get();
        $res = obj2array($order_builder);

        if ($res) {
            foreach ($res as $key => $value) {
                //根据 ship_method 确定是否需要bol
                $res[$key]['bol_flag'] = strtoupper(trim($value['ship_method'])) == self::OTHER_SHIP_METHOD[0] ? 1 : 0;
                if ($res[$key]['bol_flag']) {
                    $bol_arr[] = $value['id'] . '_bol';
                    $label_details_arr['bol'][$value['id'] . '_bol'] = '';
                    //上传的bol文件名（编辑的时候才显示）
                    if ($is_edit == 1) {
                        $res[$key]['bol_file_name'] = $this->orm->table('tb_sys_customer_sales_dropship_upload_file as f')
                            ->where('deal_file_path', $value['bol_path'])
                            ->value('file_name');
                        $label_details_arr['bol'][$value['id'] . '_bol'] = $res[$key]['bol_file_name'];
                    }
                }

                //查询订单明细
                $child_builder = $this->orm->table('tb_sys_customer_sales_order_line as l')
                    ->leftJoin('tb_sys_customer_sales_order_other_temp as t', 't.id', '=', 'l.temp_id')
                    ->where('l.header_id', '=', $value['id'])
                    ->select(
                        'l.id as line_id', 'l.item_code', 'l.qty', 'l.line_item_number',
                        't.id as temp_id', 't.tracking_number', 't.ship_method', 't.carrier'
                    )
                    ->groupBy('l.id')
                    ->get();
                $childList = obj2array($child_builder);

                if ($childList) {
                    //$count:用来标记上传label的总数量 如果$res[$key]['bol_flag']为1则说明此时要label的数量为1，如果$res[$key]['bol_flag']为0则说明此时要label的数量为0，
                    $count = $res[$key]['bol_flag'];

                    //根据sku 和 数量 处理成相对应的格式
                    $childrenList = $this->getChildListComboInfo($childList, $value['id'], $count, $label_details_arr, $container_all_arr, $is_edit, $container_child_all_arr);
                    $res[$key]['total_file_amount'] = $count;
                    $res[$key]['childrenList'] = $childrenList;

                    $this->session->set('container_id_list', $container_child_all_arr);

                } else {
                    unset($res[$key]);
                }
            }
            $this->session->set('label_details', $label_details_arr);
            $this->session->set('bol', $bol_arr);
        }

        return $res;
    }

    /**
     * @param $data
     * @param int $order_id tb_sys_customer_sales_order表的id
     * @param $count
     * @param $label_details_arr
     * @param $container_all_arr
     * @param int $is_edit
     * @param $container_child_all_arr
     * @return mixed
     */
    public function getChildListComboInfo($data, $order_id, &$count, &$label_details_arr, &$container_all_arr, $is_edit, &$container_child_all_arr)
    {
        $container_arr = [];

        foreach ($data as $key => $value) {
            $cut_type = $this->getUsPickUpOtherCutType($value['carrier']);
            //查询对应商品的comb信息
            $comboInfo = $this->comboInfoByProductSku($value['item_code']);
            //非combo商品
            if (!$comboInfo) {
                $data[$key]['is_combo'] = 0;

                $qty = $value['qty'];

                $sort_key = 0;

                //处理成：一个qty对应一个label信息
                for ($i = 0; $i < (int)$qty; $i++) {
                    $count++;
                    $container_id = $order_id . '_' . $key . '_' . $value['temp_id'] . '_' . ($i + 1);
                    $data[$key]['combo_info'][$i]['key'] = $i + 1;
                    $data[$key]['combo_info'][$i]['container_id'] = $container_id . '_' . $cut_type;
                    $data[$key]['combo_info'][$i]['tracking_id'] = null;
                    //上传upload要用的裁剪方式
                    $data[$key]['combo_info'][$i]['cut_type'] = $cut_type;
                    //页面显示的序列
                    $sort_key++;
                    $data[$key]['combo_info'][$i]['sort_key'] = $sort_key;
                    array_push($container_arr, $data[$key]['combo_info'][$i]['container_id']);
                    array_push($container_all_arr, $data[$key]['combo_info'][$i]['container_id']);
                    if ($cut_type == HomePickCarrierType::US_PICK_UP_OTHER_LTL_LABEL) {
                        $data[$key]['combo_info'][$i]['container_packing_slip_id'] = $container_id . '_' . HomePickCarrierType::US_PICK_UP_OTHER_LTL_PACKING_SLIP;
                        array_push($container_arr, $data[$key]['combo_info'][$i]['container_packing_slip_id']);
                        array_push($container_all_arr, $data[$key]['combo_info'][$i]['container_packing_slip_id']);
                    }

                    //新导单进来，不用考虑是否已经上传过文件,编辑label需要考虑
                    if ($is_edit == 1) {
                        $map = [
                            ['line_id', '=', $value['line_id']],
                            ['status', '=', 1],
                            ['line_item_number', '=', $i + 1],
                        ];
                        $temp = $data[$key]['combo_info'][$i];
                        //根据tb_sys_customer_sales_order_line的主键查询去上传的label信息
                        $this->getLabelInfoBySalesOrderLineId($map, $temp, $container_id, $label_details_arr);
                        $data[$key]['combo_info'][$i] = $temp;
                    }
                }

            }

            //combo商品
            if ($comboInfo) {
                $data[$key]['is_combo'] = 1;

                //combo的数量
                $combo_count = array_sum(array_column($comboInfo, 'qty'));
                $data[$key]['combo_count'] = $combo_count;
                $count_all = 0;
                foreach ($comboInfo as $k => $v) {
                    $count_qty = $value['qty'] * $v['qty'];
                    $data[$key]['combo_info'][$k] = $v;
                    $data[$key]['combo_info'][$k]['qty'] = $count_qty;
                    $data[$key]['combo_info'][$k]['default_qty'] = $v['qty'];

                    $qty = $count_qty;

                    //$combo_key = $k + 1; // combo 所在第几个
                    $data[$key]['combo_info'][$k]['key'] = $k + 1;
                    $sort_key = 0;

                    for ($i = 0; $i < (int)$qty; $i++) {
                        $combo_key = intval(floor($count_all / $value['qty'])) + 1;
                        $count_all++;
                        $count++;

                        $data[$key]['combo_info'][$k]['line'][$i]['key'] = $i + 1;
                        $data[$key]['combo_info'][$k]['line'][$i]['combo_key'] = $combo_key;
                        //上传upload要用的裁剪方式
                        $data[$key]['combo_info'][$k]['line'][$i]['cut_type'] = $cut_type;
                        //页面显示的序列
                        $sort_key++;
                        $data[$key]['combo_info'][$k]['line'][$i]['sort_key'] = $sort_key;

                        if ($is_edit == 1) {//编辑
                            $container_id = $order_id . '_' . $v['set_product_id'] . '_' . $value['temp_id'] . '_' . ($i + 1) . '_' . $combo_key . '_' . $combo_count;
                            $data[$key]['combo_info'][$k]['line'][$i]['container_id'] = $container_id . '_' . $cut_type;
                            array_push($container_arr, $data[$key]['combo_info'][$k]['line'][$i]['container_id']);
                            array_push($container_all_arr, $data[$key]['combo_info'][$k]['line'][$i]['container_id']);
                            if ($cut_type == HomePickCarrierType::US_PICK_UP_OTHER_LTL_LABEL) {
                                $data[$key]['combo_info'][$k]['line'][$i]['container_packing_slip_id'] = $container_id . '_' . HomePickCarrierType::US_PICK_UP_OTHER_LTL_PACKING_SLIP;
                                array_push($container_arr, $data[$key]['combo_info'][$k]['line'][$i]['container_packing_slip_id']);
                                array_push($container_all_arr, $data[$key]['combo_info'][$k]['line'][$i]['container_packing_slip_id']);
                            }
                            $map = [
                                ['line_id', '=', $value['line_id']],
                                ['status', '=', 1],
                                ['combo_sort', '=', $k + 1],
                                ['line_item_number', '=', $i + 1],
                            ];
                            $temp = $data[$key]['combo_info'][$k]['line'][$i];
                            //根据tb_sys_customer_sales_order_line的主键查询去上传的label信息
                            $this->getLabelInfoBySalesOrderLineId($map, $temp, $container_id, $label_details_arr);
                            $data[$key]['combo_info'][$k]['line'][$i] = $temp;

                        } else {//新增
                            $container_id = $order_id . '_' . $v['set_product_id'] . '_' . $value['temp_id'] . '_' . ($i + 1) . '_' . $combo_key . '_' . $combo_count;
                            $data[$key]['combo_info'][$k]['line'][$i]['container_id'] = $container_id . '_' . $cut_type;
                            array_push($container_arr, $data[$key]['combo_info'][$k]['line'][$i]['container_id']);
                            array_push($container_all_arr, $data[$key]['combo_info'][$k]['line'][$i]['container_id']);
                            if ($cut_type == HomePickCarrierType::US_PICK_UP_OTHER_LTL_LABEL) {
                                $data[$key]['combo_info'][$k]['line'][$i]['container_packing_slip_id'] = $container_id . '_' . HomePickCarrierType::US_PICK_UP_OTHER_LTL_PACKING_SLIP;
                                array_push($container_arr, $data[$key]['combo_info'][$k]['line'][$i]['container_packing_slip_id']);
                                array_push($container_all_arr, $data[$key]['combo_info'][$k]['line'][$i]['container_packing_slip_id']);
                            }


                            $data[$key]['combo_info'][$k]['line'][$i]['tracking_id'] = null;
                        }

                    }
                }

            }
            //upload 容器 id
            $container_child_all_arr[$order_id]['count'] = $count;
            $container_child_all_arr[$order_id]['child_list'] = $container_arr;
        }
        $container_child_all_arr['id_list'] = $container_all_arr;

        return $data;
    }

    /**
     * @param $map
     * @param $temp
     * @param $container_id
     * @param $label_details_arr
     */
    public function getLabelInfoBySalesOrderLineId($map, &$temp, $container_id, &$label_details_arr)
    {
        $line_file_info = $this->orm->table('tb_sys_customer_sales_dropship_file_details')
            ->where($map)
            ->first();
        $line_file_info = obj2array($line_file_info);
        if ($line_file_info) {//有上传label的信息
            $temp['tracking_id'] = $line_file_info['tracking_number'];
            $temp['file_name'] = $line_file_info['file_name'];
            $temp['file_path'] = $line_file_info['file_path'];
            $temp['deal_file_name'] = $line_file_info['deal_file_name'];
            $temp['deal_file_path'] = $line_file_info['deal_file_path'];
            $temp['label_type'] = $line_file_info['label_type'];
            $temp['file_id'] = $line_file_info['id'];
            $temp['tracking_number_img'] = $line_file_info['tracking_number_img'];
            $temp['order_id_img'] = $line_file_info['order_id_img'];

            $label_details_arr['label_type'][$container_id] = $line_file_info['label_type'];
            $label_details_arr['common_label'][$container_id] = $line_file_info['file_name'];
            $label_details_arr['tracking_number'][$container_id] = $line_file_info['tracking_number'];
        } else {//没有上传label的信息
            $temp['tracking_id'] = null;
        }
    }


    /**
     * 根据商品sku查询对应的combo
     * @param string $sku
     * @return array
     */
    public function comboInfoByProductSku($sku)
    {
        //查询最新的商品
        $productId = $this->orm->table(DB_PREFIX . 'product as p')
            ->leftJoin('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->leftJoin('oc_customer as c', 'c.customer_id', '=', 'ctp.customer_id')
            ->where([
                ['p.sku', '=', $sku],
                ['p.is_deleted', '=', 0],
                ['p.status', '=', 1],
                ['c.status', '=', 1],
            ])
            ->orderBy('p.product_id', 'desc')
            ->value('p.product_id');
        //如果商品不存在，则也不存在combo商品的逻辑
        if ($productId == null) {
            $productId = $this->orm->table(DB_PREFIX . 'product as ts')
                ->where('ts.sku', $sku)
                ->orderBy('ts.product_id', 'desc')
                ->value('ts.product_id');

        }
        $combo_builder = $this->orm->table('tb_sys_product_set_info as s')
            ->where('p.product_id', $productId)
            ->leftJoin(DB_PREFIX . 'product as p', 'p.product_id', '=', 's.product_id')
            ->leftJoin(DB_PREFIX . 'product as pc', 'pc.product_id', '=', 's.set_product_id')
            ->whereNotNull('s.set_product_id')
            ->select('s.set_product_id', 's.qty', 'pc.sku')
            ->get();
        $comboInfo = obj2array($combo_builder);
        return $comboInfo;
    }

    public function verifyOtherPlatformLineColumn(&$data, $index, $country_id)
    {
        //Platform
        //可输入项有：
        //home depot
        //overstock
        //lowe's
        //other

        if (trim($data['platform']) == '') {
            return 'Line' . $index . ",[Platform] can not be left blank.";
        }

        if (!in_array(ucwords(strtolower($data['platform'])), HomePickPlatformType::getOtherPlatformTypeViewItems())) {
            return sprintf($this->language->get('error_other_field_platform'), $index, 'Platform', $data['Platform']);
        } else {
            $data['other_platform_id'] = array_search(ucwords(strtolower($data['platform'])), HomePickPlatformType::getOtherPlatformTypeViewItems());
        }
        //Sales Order ID
        //限制20位字符，且只能包含数字、字母、下划线(_)、连字符(-)
        if ($data['OrderId'] == '' || strlen($data['OrderId']) > 20 || !preg_match('/^[_0-9-a-zA-Z]{1,28}$/i', trim($data['OrderId']))) {
            return sprintf($this->language->get('error_other_field_order'), $index, 'Sales Order ID', '1', '20');
        }

        //*Quantity
        //注意：产品数量正整数
        if ($data['Quantity'] == '' || !preg_match('/^[1-9][0-9]*$/', $data['Quantity'])) {
            return 'Line' . $index . ",Quantity format error.";
        }

        //Carrier
        if (trim($data['Carrier']) == '') {
            return 'Line' . $index . ",[Carrier] can not be left blank.";
        }

        if (!in_array(strtoupper($data['Carrier']), [
            HomePickCarrierType::FEDEX_GROUND_HOME_DELIVERY,
            HomePickCarrierType::FEDEX_EXPRESS,
            HomePickCarrierType::UPS_GROUND,
            HomePickCarrierType::UPS_2ND_NEXT_DAY_AIR,
            HomePickCarrierType::ESTES,
            HomePickCarrierType::CEVA,
            HomePickCarrierType::RESI,
            HomePickCarrierType::EFW,
            HomePickCarrierType::DM_TRANSPORTATION,
            HomePickCarrierType::ABF,
            HomePickCarrierType::AMXL,
            HomePickCarrierType::OTHER,
        ])) {
            return sprintf($this->language->get('error_other_field_platform'), $index, 'Carrier', $data['carrier_origin']);
        }

        // Ship Method
        if (trim($data['Ship Method']) == '') {
            return 'Line' . $index . ",[Ship Method] can not be left blank.";
        }

        if (!in_array(strtoupper($data['Ship Method']), self::OTHER_SHIP_METHOD)) {
            return sprintf($this->language->get('error_other_field_platform'), $index, 'Ship Method', $data['Ship Method']);
        }

        if (strtoupper($data['Ship Method']) == self::OTHER_SHIP_METHOD[0]) {
            if (!in_array(strtoupper($data['Carrier']), HomePickCarrierType::getOtherLTLTypeViewItems())) {
                return 'Line' . $index . ", [Carrier]: Item '" . $data['Carrier'] . "' cannot be ship with the ship method LTL.";
            }
        } else {
            if (!in_array(strtoupper($data['Carrier']), HomePickCarrierType::getOtherNormalTypeViewItems())) {
                return 'Line' . $index . ", [Carrier]: Item '" . $data['Carrier'] . "' cannot be ship with the ship method Small Parcel.";
            }
        }


        //*Customer Name  Ship To Name
        if (trim($data['Customer Name']) == '') {
            return 'Line' . $index . ",[Customer Name] can not be left blank.";
        }

        if (strlen($data['Customer Name']) > 90) {
            return 'Line' . $index . ",[Customer Name] '" . $data['Customer Name'] . "' must be between 1 and 90 characters long.";
        }
        //Customer Email 无 默认为buyer邮箱
        //*Customer Phone Number Phone Number
        if (trim($data['Customer Phone Number']) == '') {
            return 'Line' . $index . ",[Customer Phone Number] can not be left blank.";
        }

        if (strlen($data['Customer Phone Number']) > 45) {
            return 'Line' . $index . ",[Customer Phone Number]  '" . $data['Customer Phone Number'] . "' must be between 1 and 45 characters long.";
        }

        //*Ship to Address
        if (trim($data['Ship to Address 1']) == '') {
            return 'Line' . $index . ",[Ship to Address 1] can not be left blank.";
        }

        $address = $data['Ship to Address 1'] . ' ' . $data['Ship to Address 2'];

        if (trim($address) == '') {
            return 'Line' . $index . ",[Ship to Address 1] can not be left blank.";
        }

        $len = $this->config->get('config_b2b_address_len');
        if (strlen($address) > $len) {
            return "Line {$index}, [Ship To Address] {$data['Ship To Address']} must be between 1 and {$len} characters long.";
        }

        // Ship To City
        if (trim($data['Ship To City']) == '') {
            return 'Line' . $index . ",[Ship To City] can not be left blank.";
        }

        if (strlen($data['Ship To City']) > 40) {
            return 'Line' . $index . ",[Ship To City]  '" . $data['Ship To City'] . "' must be between 1 and 40 characters long.";
        }

        // Ship To State 不限制不配送的洲区域
        if (trim($data['Ship To State']) == '') {
            return 'Line' . $index . ",[Ship To State] can not be left blank.";
        }

        if (strlen($data['Ship To State']) > 40) {
            return 'Line' . $index . ",[Ship To State]  '" . $data['Ship To State'] . "' must be between 1 and 40 characters long.";
        }

        // shipToPostalCode Ship To Zip
        if (trim($data['Ship To ZIP Code']) == '') {
            return 'Line' . $index . ",[Ship To ZIP Code] can not be left blank.";
        }

        if (strlen($data['Ship To ZIP Code']) > 18) {
            return 'Line' . $index . ",[Ship To ZIP Code]  '" . $data['Ship To ZIP Code'] . "' must be between 1 and 18 characters long.";
        }

        //Ship To Country
        if ($data['Ship To Country'] == '') {
            return 'Line' . $index . ",[Ship To Country] can not be left blank.";
        }

        //Tracking Number
        if (strlen($data['Tracking Number']) > 100) {
            return 'Line' . $index . ",[Tracking Number] '" . $data['Tracking Number'] . "' must be between 1 and 100 characters.";
        }
        //remark
        if (strlen($data['Remark']) > 100) {
            return 'Line' . $index . ",[Remark] '" . $data['Remark'] . "' must be between 1 and 100 characters.";
        }

        return true;
    }


    public function getOtherOrderColumnNameConversion($data, $order_mode, $customer_id, $country_id, $importMode)
    {
        $res = [];
        $order_comments = '';
        if ($data['remark']) {
            $order_comments = $data['remark'];
        }
        $weekDay = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        if ($order_mode == HomePickUploadType::ORDER_MODE_HOMEPICK && $country_id == AMERICAN_COUNTRY_ID) {
            $res['order_id'] = $data['sales_order_id'];
            $res['order_date'] = $weekDay[(int)date('w', strtotime($data['create_time']))] . ',' . date('F j Y h:i A', strtotime($data['create_time']));
            $res['email'] = $this->customer->getEmail();
            $res['ship_name'] = $data['ship_to_name'];
            $res['ship_address1'] = trim($data['ship_to_address'] . ' ' . $data['ship_to_address2']);
            $res['ship_city'] = $data['ship_to_city'];
            $res['ship_state'] = $data['ship_to_state'];
            $res['ship_zip_code'] = $data['ship_to_zip'];
            $res['ship_country'] = $data['ship_to_country'];
            $res['ship_phone'] = $data['ship_to_phone'];
            $res['ship_method'] = $data['ship_method'];
            $res['ship_service_level'] = $data['carrier'];
            $res['bill_name'] = $res['ship_name'];
            $res['bill_address'] = $res['ship_address1'];
            $res['bill_city'] = $res['ship_city'];
            $res['bill_state'] = $res['ship_state'];
            $res['bill_zip_code'] = $res['ship_zip_code'];
            $res['bill_country'] = $res['ship_country'];
            $res['orders_from'] = $data['platform'];
            $res['discount_amount'] = '0.0000';
            $res['tax_amount'] = '0.0000';
            $res['order_total'] = '0.00';
            $res['store_name'] = 'yzc';
            $res['store_id'] = 888;
            $res['buyer_id'] = $customer_id;
            $res['customer_comments'] = $order_comments;
            $res['run_id'] = $data['run_id'];
            $res['order_status'] = CustomerSalesOrderStatus::CHECK_LABEL; //checkLabel
            $res['order_mode'] = $order_mode;
            $res['import_mode'] = $importMode;
            $res['create_user_name'] = $customer_id;
            $res['create_time'] = $data['create_time'];
            $res['program_code'] = $data['program_code'];
            $res['line_count'] = 1;
            $res['update_temp_id'] = $data['id'];
            $res['product_info'][0] = [
                'temp_id' => $data['id'],
                'line_item_number' => 1,
                'product_name' => trim($data['item_code']),
                'qty' => $data['quantity'],
                'item_price' => '0.00',
                'item_tax' => 0,
                'item_code' => trim($data['item_code']),
                'alt_item_id' => $data['item_code'],
                'run_id' => $data['run_id'],
                'ship_amount' => 0,
                'line_comments' => $order_comments,
                'image_id' => 1,
                'item_status' => 1,
                'create_user_name' => $customer_id,
                'create_time' => $data['create_time'],
                'program_code' => $data['program_code'],
            ];


        }
        return $res;
    }

    /**
     * 美国上门取货的裁剪方式
     * @param $carrier 快递物流方式
     * @return int
     */
    public function getUsPickUpOtherCutType($carrier)
    {
        foreach (HomePickCarrierType::getOtherCutTypeViewItems() as $key => $val) {
            if (strtoupper(trim($carrier)) == trim($key)) {
                return intval($val);
            }
        }
        return HomePickCarrierType::US_PICK_UP_OTHER_LTL_PACKING_SLIP;
    }

    /**
     * 根据sales_order表的id与临时表的id获取美国上门取货other导单信息
     *
     * @param int $order_id tb_sys_customer_sales_order表的id
     * @param int $order_temp_id tb_sys_customer_sales_order_other_temp表的id
     * @return array
     */
    public function getUsSalesOrderInfoByOrderIdAndTempId($order_id, $order_temp_id)
    {
        $builder = $this->orm->table('tb_sys_customer_sales_order as so')
            ->leftJoin('tb_sys_customer_sales_order_other_temp as soot', 'so.order_id', '=', 'soot.sales_order_id')
            ->where([
                ['so.id', '=', $order_id],
                ['soot.id', '=', $order_temp_id],
            ])
            ->select('so.order_id', 'so.run_id', 'soot.item_code')
            ->first();
        return obj2array($builder);
    }

    public function getNoticeLabelNum(int $customer_id)
    {
        return $this->orm->table('tb_sys_customer_sales_order_label_review as l')
            ->leftJoin('tb_sys_customer_sales_order as o', 'o.id', '=', 'l.order_id')
            ->where([
                'l.buyer_id' => $customer_id,
                'l.status' => HomePickLabelReviewStatus::REJECTED,
            ])
            ->whereNotIn('o.order_status', [CustomerSalesOrderStatus::CANCELED])
            ->count();
    }

    /**
     * 获取上门取货other导单的订单状态与label审核状态
     * @param int $order_id tb_sys_customer_sales_order主键id
     * @return array
     */
    public function usPickUpOtherOrderAndReviewStatus(int $order_id)
    {
        $builder = $this->orm->table('tb_sys_customer_sales_order as o')
            ->leftJoin('tb_sys_customer_sales_order_label_review as olr', 'o.id', '=', 'olr.order_id')
            ->where([
                ['o.id', '=', $order_id]
            ])
            ->first(['olr.status', 'o.order_status']);
        return obj2array($builder);
    }

    public function updateSalesOrderInfo($map, $update)
    {
        $this->orm->table('tb_sys_customer_sales_order')
            ->where($map)
            ->update($update);
    }

    public function updateSalesOrderLineInfo($map, $update)
    {
        $this->orm->table('tb_sys_customer_sales_order_line')
            ->where($map)
            ->update($update);
    }

    public function getFileDetailsColumn($map, $column = 'id')
    {
        return $this->orm->table('tb_sys_customer_sales_dropship_file_details')
            ->where($map)
            ->value($column);
    }

    public function getSalesOrderColumn($map, $column = 'id')
    {
        return $this->orm->table('tb_sys_customer_sales_order')
            ->where($map)
            ->value($column);
    }

    public function getSalesOrderLineDetails($map)
    {
        return $this->orm->table('tb_sys_customer_sales_order_line')
            ->where($map)
            ->get();
    }

    private function getOrderModifyFailureLog($process_code, $id, $line_id = null)
    {
        $this->load->model("account/customer_order");
        $failure_log_array = $this->model_account_customer_order->getLastFailureLog($process_code, $id, $line_id);
        $failure_log_html = "";
        if (!empty($failure_log_array)) {
            foreach ($failure_log_array as $log_detail) {
                switch ($log_detail['process_code']) {
                    case 1:
                        $log_detail['process_code'] = $this->language->get('text_modify_shipping');
                        break;
                    case 2:
                        $log_detail['process_code'] = $this->language->get('text_modify_sku');
                        break;
                    case 3:
                        $log_detail['process_code'] = $this->language->get('text_order_cancel');
                        break;
                    default:
                        break;
                }
                $failure_log_html = "<table class=\"table table-hover\"><tbody>";
                $failure_log_html .= "<tr><th class=\"text-center\">" . $this->language->get('text_table_head_time') . "</th><td class=\"text-center\">" . preg_replace("/\s/", " ", $log_detail['operation_time']) . "</td></tr>";
                $failure_log_html .= "<tr><th class=\"text-center\">" . $this->language->get('text_table_head_type') . "</th><td class=\"text-center\">" . preg_replace("/\s/", " ", $log_detail['process_code']) . "</td></tr>";
                $failure_log_html .= "<tr><th class=\"text-center\">" . $this->language->get('text_table_head_before') . "</th><td class=\"text-center\">" . preg_replace("/\s/", " ", $log_detail['previous_status']) . "</td></tr>";
                $failure_log_html .= "<tr><th class=\"text-center\">" . $this->language->get('text_table_head_target') . "</th><td class=\"text-center\">" . preg_replace("/\s/", " ", $log_detail['target_status']) . "</td></tr>";
                $failure_log_html .= "<tr><th class=\"text-center\">" . $this->language->get('text_table_head_reason') . "</th><td class=\"text-center\">" . preg_replace("/\s/", " ", $log_detail['fail_reason']) . "</td></tr>";
                $failure_log_html .= "</tbody></table>";
            }
            return htmlentities($failure_log_html);
        }
    }

    /**
     * 美国上门取货other导单可编辑弹窗
     * @param int $order_id  tb_sys_customer_sales_order主键id
     * @return bool
     */
    public function judgeUsOtherCanEditUploadLabel($order_id)
    {
        $res = $this->usPickUpOtherOrderAndReviewStatus(intval($order_id));
        //以下2种状态，展示编辑的按钮
        //1.Check Label 、New Order 、Pending Charges
        if ($res['order_status'] == CustomerSalesOrderStatus::CHECK_LABEL || $res['order_status'] == CustomerSalesOrderStatus::TO_BE_PAID || $res['order_status'] == CustomerSalesOrderStatus::PENDING_CHARGES) {
            return true;
        }
        //2.BP 且label不能为审核通过
        if ($res['order_status'] == CustomerSalesOrderStatus::BEING_PROCESSED && $res['status'] != HomePickLabelReviewStatus::APPROVED) {
            return true;
        }
        return false;
    }


    public function getHomePickListInfos($data, $config)
    {
        load()->model('account/customer_order');
        load()->model('catalog/product');
        load()->model('account/deliverySignature');
        $ret = [];
        //自提货最新待确认信息
        $pickUpConfirmChange = CustomerSalesOrderPickUpLineChange::query()->whereIn('sales_order_id', array_column($data, 'id'))->where('is_buyer_accept', YesNoEnum::NO)->get()->keyBy('sales_order_id');
        // 查询销售单的保单信息
        $safeguardBills = app(SafeguardBillRepository::class)->getSafeguardBillsBySaleIds(array_column($data, 'id'));
        $salesOrderErrorLogs = SafeguardSalesOrderErrorLog::query()->whereIn('sales_order_id', array_column($data, 'id'))->get()->keyBy('sales_order_id');
        $tracking_array = $this->home_pick_model->getTrackingNumber(array_column($data, 'id'), array_column($data, 'order_id'));
        $tracking = [];
        $trackingRepository = app(TrackRepository::class);
        if ($tracking_array) {
            foreach ($tracking_array as $key => $value) {
                $tracking[$value['SalesOrderId']][] = $value;
            }
        }
        foreach ($data as $item) {
            $isApiOrder = false;
            if ($item['program_code'] == 'B2B SYN') { // 是通过api的单子
                $isApiOrder = true;
            }
            $len = $this->config->get('config_b2b_address_len');
            if ($isApiOrder) {
                $countryId = $this->customer->getCountryId();
                if ($countryId == AMERICAN_COUNTRY_ID) {
                    $len = $this->config->get('config_b2b_address_len_us1');
                } else if ($countryId == UK_COUNTRY_ID) {
                    $len = $this->config->get('config_b2b_address_len_uk');
                } else if ($countryId == DE_COUNTRY_ID) {
                    $len = $this->config->get('config_b2b_address_len_ude');
                } else if ($countryId == JAPAN_COUNTRY_ID) {
                    $len = $this->config->get('config_b2b_address_len_jp');
                }
            }

            $item['safeguard_bills'] = $safeguardBills[$item['id']] ?? [];
            $item['safeguard_bill_is_active'] = false;
            $item['safeguard_bill_is_pending'] = false;
            $item['safeguard_bill_pay_error'] = isset($salesOrderErrorLogs[$item['id']]);
            // 判断销售单是否有正在生效的保单
            if (!empty($item['safeguard_bills']) && $item['safeguard_bills']->contains('status', SafeguardBillStatus::ACTIVE)) {
                $item['safeguard_bills']->map(function ($bill) use (&$item) {
                    $bill['safeguard_claim_active_count'] = $bill->safeguardClaim->whereIn('status', [SafeguardClaimStatus::CLAIM_IN_PROGRESS, SafeguardClaimStatus::CLAIM_BACKED])->count();
                    $bill['safeguard_claim_succeed_count'] = $bill->safeguardClaim->whereIn('status', [SafeguardClaimStatus::CLAIM_SUCCEED, SafeguardClaimStatus::CLAIM_FAILED])->count();
                    $bill['safeguard_expiration_days'] = ceil(abs((strtotime($bill->expiration_time->toDateTimeString()) - time()) / (3600 * 24)));
                    $bill->safeguardConfig->title = app(SafeguardConfigRepository::class)->geiNewestConfig($bill->safeguardConfig->rid, customer()->getCountryId())->title;
                    if ($bill->effective_time->toDateTimeString() < date('Y-m-d H:i:s') && $bill->expiration_time->toDateTimeString() > date('Y-m-d H:i:s')) {
                        $item['safeguard_bill_is_active'] = true;
                    }
                    return $bill;
                });
            }

            //判断销售单是待生效的保单
            if (!empty($item['safeguard_bills']) && $item['safeguard_bills']->contains('status', SafeguardBillStatus::PENDING)) {
                $item['safeguard_bill_is_pending'] = true;
            }

            $trackingNumber = [];
            $carrierName = [];
            $carrierStatus = [];
            $trackStatus = [];
            $track_qty = 0;
            $tracking_array = [];
            $trackShipmentTag = 0;
            // 上门取货中的自动购买订单会将其变成null
            if (!$item['import_mode']) {
                $item['import_mode'] = HomePickImportMode::IMPORT_MODE_NORMAL;
            }
            // 美国站点关联美国招商经理的Buyer的一件代发销售订单，complete前不显示运单号
            if (($config['tracking_privilege'] && $item['order_status'] == CustomerSalesOrderStatus::COMPLETED)
                || !$config['tracking_privilege']) {
                if (isset($tracking[$item['order_id']])) {
                    $tracking_array = $tracking[$item['order_id']];
                }
            }
            // 暂时不加ups fedex的限制
            if (isset($tracking_array) && !empty($tracking_array)) {
                foreach ($tracking_array as $track) {
                    $track_qty = intval($track['qty']);
                    $track_temp = explode(',', $track['trackingNo']);
                    $track_size = sizeof($track_temp);
                    for ($i = 0; $i < $track_size; $i++) {
                        if ($item['ship_service_level']) {
                            $carrierName[] = $track['carrierName'] . '-[' . $item['ship_service_level'] . ']';
                        } else {
                            $carrierName[] = $track['carrierName'];
                        }
                        $carrierStatus = $trackingRepository->getTrackingStatusByTrackingNumber($item['order_id'], $track_temp[$i]);
                        if ($carrierStatus == TrackStatus::LABEL_CREATED) {
                            $carrierStatus = '';
                        }
                        if ($carrierStatus) {
                            $trackShipmentTag = 1;
                        }
                        $trackTemp = [
                            'trackingShipSku' => !empty($track['ShipSku']) ? $track['ShipSku'] : '',
                            'trackingNumber' => $track_temp[$i],
                            'trackingStatus' => $track['status'],
                            'carrierStatus' => $carrierStatus,
                            'carrierName' => $track['carrierName'],
                        ];
                        $trackingNumber[] = $trackTemp;
                    }
                }
            }

            $tag_array = $this->model_catalog_product->getProductSpecificTagByOrderHeaderId($item['id']);
            $tags = [];
            if (isset($tag_array) && !empty($tag_array)) {
                $tags = array_map(function ($tag) {
                    //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                    $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                    return '<img data-toggle="tooltip" class="' . $tag['class_style'] . '"  title="' . $tag['description'] . '"  style="padding-left: 1px" src="' . $img_url . '">';
                }, $tag_array);
            }

            if ($item['order_status'] == CustomerSalesOrderStatus::PENDING_CHARGES || $item['order_status'] == CustomerSalesOrderStatus::WAITING_FOR_PICK_UP
                || ($item['order_status'] == CustomerSalesOrderStatus::ON_HOLD && $item['pick_up_status'] == CustomerSalesOrderPickUpStatus::PICK_UP_TIMEOUT)) {
                // 费用待支付可以直接取消
                $canCancel = true;
                $isCancelling = false;
            } else {
                $canCancel = $this->model_account_customer_order->checkOrderCanBeCanceled($item['id'], $item['order_id'], $config['is_auto_buyer'], $this->customer->isCollectionFromDomicile());
                $isCancelling = $this->model_account_customer_order->checkIsProcessing($item['id'], CommonOrderProcessCode::CANCEL_ORDER);
            }
            //是否允许 Released，参考一件代发，销售单状态4是则允许
            $canReleased = false;
            // 状态要为on hold，并且自提货状态不能为timeout
            if ($item['order_status'] == CustomerSalesOrderStatus::ON_HOLD
                && $item['pick_up_status'] != CustomerSalesOrderPickUpStatus::PICK_UP_TIMEOUT) {
                $canReleased = true;
            }
            if (in_array($item['order_status'], [CustomerSalesOrderStatus::ON_HOLD, CustomerSalesOrderStatus::PENDING_CHARGES]) || $item['import_mode'] == HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP) {
                // 费用待支付不可修改地址
                $canEditShipping = false;
                $isEditingShipping = false;
            } else {
                $canEditShipping = $this->model_account_customer_order->checkShippingCanChange($item['id'], $config['is_auto_buyer']);
                $isEditingShipping = $this->model_account_customer_order->checkIsProcessing($item['id'], CommonOrderProcessCode::CHANGE_ADDRESS);
            }
            //修改标签
            $canManageLabels = false;
            $showIconExclamation = false;//显示感叹号
            if ($item['order_mode'] == HomePickUploadType::ORDER_MODE_HOMEPICK &&
                $item['import_mode'] != HomePickImportMode::IMPORT_MODE_NORMAL &&
                $item['import_mode'] != HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP) {
                if ($item['lr_status'] == 3 ) {
                    if (!in_array($item['order_status'], [CustomerSalesOrderStatus::ON_HOLD, CustomerSalesOrderStatus::CANCELED])) {
                        $canManageLabels = true;
                        $showIconExclamation = true;
                    }
                } else {
                    if (!in_array($item['order_status'], [CustomerSalesOrderStatus::ON_HOLD, CustomerSalesOrderStatus::CANCELED, CustomerSalesOrderStatus::LTL_CHECK])) {
                        $canManageLabels = true;
                    }
                }
            }
            $failure_log_html = $this->getOrderModifyFailureLog(CommonOrderProcessCode::CANCEL_ORDER, $item['id']);
            //如果取消操作的失败日志没有，再查询修改发货信息的错误日志
            if (!isset($failure_log_html) || empty($failure_log_html)) {
                $failure_log_html = $this->getOrderModifyFailureLog(CommonOrderProcessCode::CHANGE_ADDRESS, $item['id']);
            }

            $signature_fee = 'No';
            if (strcasecmp(trim($item['ship_method']), 'ASR') == 0) {
                $ds_product = $this->model_account_deliverySignature->getDeliverySignatureProduct(Customer()->getCountryId());
                if (!isset($ds_product) || empty($ds_product)) {
                    $signature_fee = '- -';
                } else {
                    $package_total = $this->model_account_deliverySignature->getASRPackageTotalByHeaderId($item['id'], Customer()->getId(), $ds_product['product_id']);
                    if (!isset($package_total) || $package_total == 0) {
                        $signature_fee = '- -';
                    } else {
                        $signature_fee = intval($package_total) * doubleval($ds_product['price']);
                        $signature_fee = $this->currency->format($signature_fee, $this->session->data['currency']);
                    }
                }
            }
            //  ShipName
            //  ShipPhone
            //  detail_address 这几列有?? 需要标红 而且需要感叹号提示
            $ship_name_tips = '';
            $ship_phone_tips = '';
            $ship_address_tips = '';
            $order_status_label = $this->model_account_customer_order->formatSalesOrderStatusLabel($item);///
            $removeBindStock = app(CustomerOrderModifyLogRepository::class)->getCancelOrderRemoveBindStockStatus($item['id'], $item['memo']);
            //100783 虚拟支付 采用虚拟支付的订单，取消时不可保留库存
            $hasVirtualPayment = false;
            if ($canCancel) {
                $hasVirtualPayment = $this->model_account_customer_order->hasVirtualPayment($item['id']);
            }

            if ($item['import_mode'] == HomePickImportMode::US_OTHER) {
                $otherPlatformList = HomePickPlatformType::getOtherPlatformTypeViewItems();
                $platform_name = $otherPlatformList[$item['other_platform_id']];
            } else {
                $platform_name = HomePickImportMode::getDescription($item['import_mode']);
            }

            $canEditUpload = $this->judgeDropshipIsOverTime($item['id']);
            //美国上门取货是否展示可编辑弹窗
            if ($item['import_mode'] == HomePickImportMode::US_OTHER && Customer()->getCountryId() == AMERICAN_COUNTRY_ID) {
                $canEditUpload = $this->judgeUsOtherCanEditUploadLabel($item['id']);
            }

            $warehouse_code = null;
            if (Customer()->getCountryId() == AMERICAN_COUNTRY_ID) {
                $warehouse_code = app(CustomerSalesOrderRepository::class)->getHomePickWarehouseCode($item['order_id'], Customer()->getId(), $item['import_mode']);
            }

            //自提货--显示取货日期
            $item['pick_up_date'] = '';
            if ($item['order_status'] == CustomerSalesOrderStatus::WAITING_FOR_PICK_UP
                || ($item['pick_up_status'] == CustomerSalesOrderPickUpStatus::IN_PREP && $item['order_status'] == CustomerSalesOrderStatus::BEING_PROCESSED)) {
                $item['pick_up_date'] = $item['pick_up_apply_date'];
            }
            //自提货--取货信息待确认剩余秒数
            $item['seconds_remaining'] = '';
            if (isset($pickUpConfirmChange[$item['id']]) && $item['pick_up_status'] == CustomerSalesOrderPickUpStatus::PICK_UP_INFO_TBC && $item['order_status'] == CustomerSalesOrderStatus::BEING_PROCESSED) {
                $pickUpConfirmInfo = $pickUpConfirmChange[$item['id']];
                $timeCountDown = (new Carbon())->diffInSeconds(Carbon::parse($pickUpConfirmInfo->create_time)->addHours(48), false);
                if ($timeCountDown > 0 && $timeCountDown <= (48 * 60 * 60)) {
                    $item['seconds_remaining'] = intval($timeCountDown);
                }
            }

            //是否可以修改自提货的取货信息
            $canEditPickUp = false;
            if (in_array($item['order_status'], [CustomerSalesOrderStatus::TO_BE_PAID, CustomerSalesOrderStatus::BEING_PROCESSED, CustomerSalesOrderStatus::PENDING_CHARGES])
                && $item['pick_up_status'] == CustomerSalesOrderPickUpStatus::DEFAULT && $item['import_mode'] == HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP) {
                $canEditPickUp = true;
            }
            $order_items = [
                'Number' => ++$config['numStart'],
                'Id' => $item['id'],
                'lr_status' => $item['lr_status'], // label_review 状态
                'import_mode' => $item['import_mode'],
                'platform_name' => $platform_name,
                'YzcOrderId' => $item['yzc_order_id'],
                'order_mode' => $item['order_mode'],
                'safeguard_bill_is_active' => $item['safeguard_bill_is_active'],
                'safeguard_bill_is_pending' => $item['safeguard_bill_is_pending'],
                'safeguard_bills' => $item['safeguard_bills'],
                'OrderId' => $item['order_id'],
                'OrderDate' => date($this->language->get('datetime_format'), strtotime($item['create_time'])),
                'Email' => $item['email'],
                'ShipName' => app('db-aes')->decrypt($item['ship_name']),
                'detail_address' => trim(app('db-aes')->decrypt($item['ship_address1']) . ',' . app('db-aes')->decrypt($item['ship_city']) . ',' . $item['ship_zip_code'] . ',' . $item['ship_state'] . ',' . $item['ship_country'] . $ship_address_tips, ','),
                'ShipAddress1' => preg_replace("/\s/", " ", app('db-aes')->decrypt($item['ship_address1'])),
                'ShipAddress2' => app('db-aes')->decrypt($item['ship_address2']),
                'ShipCity' => app('db-aes')->decrypt($item['ship_city']),
                'ShipState' => $item['ship_state'],
                'ShipZipCode' => $item['ship_zip_code'],
                'ShipCountry' => $item['ship_country'],
                'ShipPhone' => app('db-aes')->decrypt($item['ship_phone']) . $ship_phone_tips,
                'ShipMethod' => $item['ship_method'],
                'ShipServiceLevel' => $item['ship_service_level'],
                'ShipCompany' => $item['ship_company'],
                'BillName' => app('db-aes')->decrypt($item['bill_name']),
                'BillAddress' => app('db-aes')->decrypt($item['bill_address']),
                'BillCity' => app('db-aes')->decrypt($item['bill_city']),
                'BillState' => $item['bill_state'],
                'BillZipCode' => $item['bill_zip_code'],
                'BillCountry' => $item['bill_country'],
                'OrdersFrom' => $item['orders_from'],
                'DiscountAmount' => $item['discount_amount'],
                'TaxAmount' => $item['tax_amount'],
                'OrderTotal' => $item['order_total'],
                'PaymentMethod' => $item['payment_method'],
                'StoreName' => $item['store_name'],
                'StoreID' => $item['store_id'],
                'BuyerID' => $item['buyer_id'],
                'LineCount' => $item['line_count'],
                'CustomerComments' => preg_replace("/\s/", " ", $item['customer_comments']),
                'UpdateTempId' => $item['update_temp_id'],
                'RunId' => $item['run_id'],
                'OrderStatus' => $item['order_status'],
                'OrderStatusName' => CustomerSalesOrderStatus::getDescription($item['order_status']),
                'order_status_label' => $order_status_label,///
                'Memo' => $item['memo'],
                'CreateUserName' => $item['create_user_name'],
                'UpdateUserName' => $item['update_user_name'],
                'ProgramCode' => $item['program_code'],
                'TrackingNumber' => $trackingNumber,
                'TrackingStatus' => $trackStatus,
                'carrierStatus' => $carrierStatus,
                'trackShipmentTag' => $trackShipmentTag,
                'Qty' => $track_qty,
                'CarrierName' => array_unique($carrierName),
                'tag' => $tags,
                'canCancel' => $canCancel,
                'isCancelling' => $isCancelling,
                'canReleased' => $canReleased,
                'canEditShip' => $canEditShipping,
                'isEditingShip' => $isEditingShipping,
                'canManageLabels' => $canManageLabels,
                'showIconExclamation' => $showIconExclamation,
                'failure_log' => $failure_log_html,
                //13854 【需求】Sales Order Management功能中将OrderData 更新为订单导入时间，增加付款完成时间列
                'check_time' => max(explode(',', $item['date_modified'])),
                'canEditUpload' => (int)$canEditUpload,
                'ASRFee' => $signature_fee,
                'hasVirtualPayment' => (int)$hasVirtualPayment,
                'is_american' => (int)Customer()->isUSA(),
                'warehouse_code' => $warehouse_code,
                'address_limit_len' => $len, // 通过api的单子需要限制地址字符串长度, 给到前端埋在页面使用,目的不影响现有交互.
                'pick_up_date' => $item['pick_up_date'],
                'seconds_remaining' => $item['seconds_remaining'],
                'pick_up_status' => $item['pick_up_status'] ?? 0,
                'bp_sub_status_desc' => in_array($item['pick_up_status'], CustomerSalesOrderPickUpStatus::bpSubState()) ? CustomerSalesOrderPickUpStatus::getDescription($item['pick_up_status']) : '',
                'on_hold_sub_status_desc' => in_array($item['pick_up_status'], CustomerSalesOrderPickUpStatus::onHoldSubState()) ? CustomerSalesOrderPickUpStatus::getDescription($item['pick_up_status']) : '',
                'canEditPickUp' => $canEditPickUp,
                'remove_bind_stock' => $removeBindStock
            ];
            $ret[] = $order_items;
        }

        return $ret;
    }


    public function getHomePickDownLoadInfos($results, $param = [])
    {
        // 获取
        $warehouseInfo = WarehouseInfo::query()
            ->where([
                'country_id' => Customer()->getCountryId(),
                'status' => 1,
            ])
            ->get()
            ->keyBy('WarehouseCode')
            ->toArray();
        $warehouseInfo = array_change_key_case($warehouseInfo, CASE_UPPER);

        //运单号
        $trackingStatusArr = [];
        $filterDeliveryStatus = $param['filter_delivery_status'] ?? -1;
        if (customer()->isUSA() && !empty($results)) {
            $trackingStatusArr = app(TrackRepository::class)->getTrackingStatusBySalesOrder(array_unique(array_column($results, 'order_id')));
        }

        foreach ($results as &$result) {
            if (!$result['import_mode']) {
                $result['import_mode'] = HomePickImportMode::IMPORT_MODE_NORMAL;
            }
            $carrier_name = '';
            if ($result['carrier_name']) {
                if (count(array_unique($result['carrier_name'])) == 1) {
                    $carrier_name = current($result['carrier_name']);
                } else {
                    $carrier_name = implode(PHP_EOL, $result['carrier_name']);
                }
            }
            $tracking_number = '';
            $trackingStatus = '';

            if (customer()->isUSA()) {
                foreach ($result['tracking_number'] ?? [] as $key => $value) {
                    if ($filterDeliveryStatus > 0) {
                        $tempTrackingStatus = $trackingStatusArr[$result['order_id'] . '_' . trim($value)] ?? [];
                        if ($tempTrackingStatus && $tempTrackingStatus['carrier_status'] == $filterDeliveryStatus) {
                            if ($result['tracking_status'][$key] == 0) {
                                $tracking_number .= $value . ' (invalid) ' . PHP_EOL;
                            } else {
                                $tracking_number .= $value . PHP_EOL;
                            }
                            $trackingStatus .= TrackStatus::getDescription($filterDeliveryStatus) . PHP_EOL; //通过某个状态查，只能展示此状态的数据
                        }
                    } else {
                        if ($result['tracking_status'][$key] == 0) {
                            $tracking_number .= $value . ' (invalid) ' . PHP_EOL;
                        } else {
                            $tracking_number .= $value . PHP_EOL;
                        }
                        if (isset($trackingStatusArr[$result['order_id'] . '_' . trim($value)])) {
                            $trackingStatus .= ($trackingStatusArr[$result['order_id'] . '_' . trim($value)]['carrier_status_name'] ?? '') . PHP_EOL;
                        } else {
                            $trackingStatus .= 'N/A' . PHP_EOL;
                        }
                    }
                }
            } else {
                foreach ($result['tracking_number'] ?? [] as $key => $value) {
                    if ($result['tracking_status'][$key] == 0) {
                        $tracking_number .= $value . ' (invalid) ' . PHP_EOL;
                    } else {
                        $tracking_number .= $value . PHP_EOL;
                    }
                }
            }
            $shipDeliveryDate = '';
            if ($result['ShipDeliveryDate']) {
                if (count(array_unique($result['ShipDeliveryDate'])) == 1) {
                    $shipDeliveryDate = current($result['ShipDeliveryDate']);
                } else {
                    $shipDeliveryDate = implode(PHP_EOL, $result['ShipDeliveryDate']);
                }
            }

            $warehouseCode = null;
            $warehouseAddress = null;
            $platform_name = HomePickImportMode::getDescription($result['import_mode']);
            if (Customer()->getCountryId() == AMERICAN_COUNTRY_ID) {
                if ($result['import_mode'] == HomePickImportMode::US_OTHER) {
                    $otherPlatformList = HomePickPlatformType::getOtherPlatformTypeViewItems();
                    $platform_name = $otherPlatformList[$result['other_platform_id']];
                    $warehouseCode = in_array($result['other_warehouse'], ['warehouse_code', '']) ? null : $result['other_warehouse'];
                    if ($warehouseCode) {
                        $warehouseAddress = $warehouseInfo[strtoupper($warehouseCode)]['warehouseAddress'] ?? '';
                    }

                } else {
                    if ($result['import_mode'] == HomePickImportMode::IMPORT_MODE_AMAZON) {
                        $warehouseCode = in_array($result['amazon_warehouse'], ['warehouse_code', '']) ? null : $result['amazon_warehouse'];
                        if ($warehouseCode) {
                            $warehouseAddress = $warehouseInfo[strtoupper($warehouseCode)]['warehouseAddress'] ?? '';
                        }
                    }

                    if ($result['import_mode'] == HomePickImportMode::IMPORT_MODE_WAYFAIR) {
                        $warehouseCode = in_array($result['wayfair_warehouse'], ['warehouse_code', '']) ? null : $result['wayfair_warehouse'];
                        if ($warehouseCode) {
                            $warehouseAddress = $warehouseInfo[strtoupper($warehouseCode)]['warehouseAddress'] ?? '';
                        }

                    }

                    if ($result['import_mode'] == HomePickImportMode::IMPORT_MODE_WALMART) {
                        $warehouseCode = in_array($result['walmart_warehouse'], ['warehouse_code', '']) ? null : $result['walmart_warehouse'];
                        if ($warehouseCode) {
                            $warehouseAddress = $warehouseInfo[strtoupper($warehouseCode)]['warehouseAddress'] ?? '';
                        }
                    }
                    if ($result['import_mode'] == HomePickImportMode::IMPORT_MODE_BUYER_PICK_UP) {
                        $warehouseInfo = WarehouseInfo::query()->where('WarehouseID', $result['pu_warehouse_id'])->first();
                        $warehouseCode = $warehouseInfo ? $warehouseInfo->WarehouseCode : '';
                        $warehouseAddress = $warehouseInfo ? $warehouseInfo->warehouse_address : '';
                    }
                }

            }
            // 处理warehouse code
            $result['create_time'] = date($this->language->get('datetime_format'), strtotime($result['create_time']));
            $result['shipping_recipient'] = $result['ship_name'];
            $result['shipping_address'] = trim($result['ship_address1'] . ',' . $result['ship_city'] . ',' . $result['ship_zip_code'] . ',' . $result['ship_state'] . ',' . $result['ship_country'], ',');
            $result['carrier_name_deal'] = $carrier_name;
            $result['tracking_status_deal'] = $trackingStatus; //物流状态
            $result['tracking_number_deal'] = $tracking_number;
            $result['shipDeliveryDate_deal'] = $shipDeliveryDate;
            $result['warehouse_code'] = $warehouseCode;
            $result['warehouse_address'] = $warehouseAddress;
            $result['checkout_time'] = max(explode(',', $result['date_modified']));
            $result['status_name'] = CustomerSalesOrderStatus::getViewItems()[$result['order_status']];
            $result['platform'] = $platform_name;
        }
        return $results;
    }

}
