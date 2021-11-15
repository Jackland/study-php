<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Enums\Customer\CustomerAccountingType;
use App\Enums\Warehouse\BatchTransactionType;
use App\Enums\Warehouse\SellerDeliveryLineType;
use App\Helper\SummernoteHtmlEncodeHelper;
use App\Repositories\Warehouse\InventoryRepository;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use App\Helper\CountryHelper;

class ControllerCustomerpartnerWarehouseInoutInventory extends AuthSellerController
{
    private $customerId;
    private $countryId;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        $this->customerId = $this->customer->getId();
        $this->countryId = $this->customer->getCountryId();
        if ($this->customer->getCountryId() != AMERICAN_COUNTRY_ID) {
            // 非O类美国Seller 不能操作
            return $this->redirect(['account/account'])->send();
        }

        $this->language->load('customerpartner/warehouse/inout_inventory');
    }

    public function index()
    {
        $data = $this->framework();
        $data['inoutInventoryCates'] = $this->getInoutInventoryCate();

        return $this->render('customerpartner/warehouse/inout_inventory', $data);
    }

    public function getList()
    {
        $validateStatus = $this->validateData();
        if ($validateStatus !== true) {
            return $this->jsonFailed($validateStatus);
        }

        $filter = $this->getRequestParam();
        $filter['page'] = $this->request->get('page', 1);
        $filter['pageLimit'] = $this->request->get('page_limit', 15);

        $result['rows'] = [];
        $result['total'] = 0;

        try {
            if ($filter['filter_create_start_date']) {
                $filter['filter_create_start_date'] = Carbon::createFromFormat('Y-m-d H:i:s', $filter['filter_create_start_date'], CountryHelper::getTimezone($this->countryId))->setTimezone(date_default_timezone_get())->toDateTimeString();
            }
            if ($filter['filter_create_end_date']) {
                $filter['filter_create_end_date'] = Carbon::createFromFormat('Y-m-d H:i:s', $filter['filter_create_end_date'], CountryHelper::getTimezone($this->countryId))->setTimezone(date_default_timezone_get())->toDateTimeString();
            }

            $inventoryRepo = app(InventoryRepository::class);
            $result['total'] = $inventoryRepo->getInoutRepositoryCount($this->customerId, $filter);
            $result['rows'] = $inventoryRepo->getInoutRepositoryList($this->customerId, $filter);

            $cata = array_column($this->getInoutInventoryCate(), 'key', 'value');
            foreach ($result['rows'] as $item) {
                $item->setAttribute('transaction_cate', $item->onhand_qty === 'inout' ? $cata[InventoryRepository::OUT_INVENTORY] : $cata[InventoryRepository::IN_INVENTORY]);
                $item->setAttribute('create_time_format', $item->create_time ? Carbon::createFromFormat('Y-m-d H:i:s', $item->create_time)->setTimezone(CountryHelper::getTimezone($this->countryId))->toDateTimeString() : '--');
                $item->setAttribute('transaction_type_format', $item->onhand_qty === 'inout' ? SellerDeliveryLineType::getDescription($item->transaction_type) : BatchTransactionType::getDescription($item->transaction_type));
                $item->setAttribute('transaction_type_tag', $item->onhand_qty === 'inout' ? InventoryRepository::OUT_INVENTORY : InventoryRepository::IN_INVENTORY);
            }
        } catch (Exception $e) {
            return $this->jsonFailed(__('系统繁忙，请稍后在试', [], 'controller/inventory'));
        }

        return $this->json($result);
    }

    public function getRequestParam()
    {
        $filter['filter_sku'] = trim($this->request->get('filter_sku', ''));
        $filter['filter_cate'] = $this->request->get('filter_cate', '');
        $filter['filter_type'] = $this->request->get('filter_type', '');
        $filter['filter_create_start_date'] = $this->request->get('filter_create_start_date', '');
        $filter['filter_create_end_date'] = $this->request->get('filter_create_end_date', '');
        $filter['filter_batch_no'] = trim($this->request->get('filter_remark', ''));

        return $filter;
    }

    // 根据筛选 入库/出库 获取对应 类型
    public function getInoutInventoryType()
    {
        $cate = $this->request->post('inoutInventoryCate');
        if (!in_array($cate, array_column($this->getInoutInventoryCate(), 'value'))) {
            $cate = InventoryRepository::IN_INVENTORY;
        }

        if ($cate == InventoryRepository::IN_INVENTORY) {
            $inoutInventoryTypes = BatchTransactionType::getAllTypeArr();
        } else {
            $inoutInventoryTypes = SellerDeliveryLineType::getAllTypeArr();
        }

        return $this->jsonSuccess($inoutInventoryTypes);
    }

    private function validateData()
    {
        $validationErrorMsg = [
            'in' => __('无效的入出库分类', [], 'controller/inventory'),
            'regex' => __('无效的筛选时间', [], 'controller/inventory')
        ];
        $validateData = [
            'filter_cate' => [
                Rule::in(array_column($this->getInoutInventoryCate(), 'value'))
            ],
            'filter_create_start_date' => [
                'regex:/^\d{4}-\d{2}-\d{2} (\d{2}:){2}\d{2}$/'
            ],
            'filter_create_end_date' => [
                'regex:/^\d{4}-\d{2}-\d{2} (\d{2}:){2}\d{2}$/'
            ],
        ];

        $validation = $this->request->validate($validateData, $validationErrorMsg);
        if ($validation->fails()) {
            return $validation->errors()->first();
        }

        $type = $this->request->post('filter_type', '');
        $cate = $this->request->post('filter_cate', InventoryRepository::IN_INVENTORY);
        if (is_numeric($type)) {
            if ($cate == InventoryRepository::IN_INVENTORY) {
                $types = BatchTransactionType::getValues();
            } else {
                $types = SellerDeliveryLineType::getValues();
            }
            if (!in_array($type, $types)) {
                return __('无效的入出库类型', [], 'controller/inventory');
            }
        }

        return true;
    }

    // 下载数据文件
    public function downloadData()
    {
        $filter = $this->getRequestParam();
        $filter['page'] = 1;
        $filter['pageLimit'] = 20000; // 默认单次下载数据量为 20000 条
        try {
            if ($filter['filter_create_start_date']) {
                $filter['filter_create_start_date'] = Carbon::createFromFormat('Y-m-d H:i:s', $filter['filter_create_start_date'], CountryHelper::getTimezone($this->countryId))->setTimezone(date_default_timezone_get())->toDateTimeString();
            }
            if ($filter['filter_create_end_date']) {
                $filter['filter_create_end_date'] = Carbon::createFromFormat('Y-m-d H:i:s', $filter['filter_create_end_date'], CountryHelper::getTimezone($this->countryId))->setTimezone(date_default_timezone_get())->toDateTimeString();
            }

            $inventoryRepo = app(InventoryRepository::class);
            $list = $inventoryRepo->getInoutRepositoryList($this->customerId, $filter, 2);
            $cate = array_column($this->getInoutInventoryCate(), 'key', 'value');

            $fileName = 'InventoryUpdate' . date('YmdHis') . '.xls';

            $head = [
                __('No.', [], 'controller/inventory'),
                __('Item Code', [], 'controller/inventory'),
                __('MPN', [], 'controller/inventory'),
                __('产品名称', [], 'controller/inventory'),
                __('入库/出库', [], 'controller/inventory'),
                __('类型', [], 'controller/inventory'),
                __('数量', [], 'controller/inventory'),
                __('备注', [], 'controller/inventory'),
                __('生成日期', [], 'controller/inventory')
            ];
            $content = [];
            $index = 1;
            foreach ($list as $item) {
                if ($item->onhand_qty === 'inout') { // 出库
                    $cate_format = $cate[InventoryRepository::OUT_INVENTORY];
                    $type_format = SellerDeliveryLineType::getDescription($item->transaction_type);
                    $remark = $item->transaction_type == SellerDeliveryLineType::PURCHASE_ORDER ? $item->batch_number : $item->common_two;
                } else { // 入库
                    $cate_format = $cate[InventoryRepository::IN_INVENTORY];
                    $type_format = BatchTransactionType::getDescription($item->transaction_type);
                    $remark = in_array($item->transaction_type,  [BatchTransactionType::INVENTORY_RECEIVE, BatchTransactionType::RMA_RETURN]) ? $item->common_one : $item->common_two;
                }
                $preData = [
                    $index++,
                    $item->sku,
                    $item->mpn,
                    SummernoteHtmlEncodeHelper::decode($item->name, true),
                    $cate_format,
                    $type_format,
                    (string)$item->original_qty,
                    $remark . "\t",
                    $item->create_time ? Carbon::createFromFormat('Y-m-d H:i:s', $item->create_time)->setTimezone(CountryHelper::getTimezone($this->countryId))->toDateTimeString() : '--'
                ];
                $content[] = $preData;
            }

            outputExcel($fileName, $head, $content);
        } catch (Exception $e) {
            return $this->jsonFailed(__('系统繁忙，请稍后在试', [], 'controller/inventory'));
        }
    }

    // 获取入出库分类
    private function getInoutInventoryCate()
    {
        return [
            ['value' => InventoryRepository::IN_INVENTORY, 'key' => __('入库', [], 'controller/inventory')],
            ['value' => InventoryRepository::OUT_INVENTORY, 'key' => __('出库', [], 'controller/inventory')]
        ];
    }

    private function framework()
    {
        $this->setDocumentInfo(__('入出库查询', [], 'catalog/document'));
        $breadcrumbs = $this->getBreadcrumbs([
            [
                'text' => __('库存管理', [], 'catalog/document'),
                'href' => 'javascript:void(0);'
            ],
            'current',
        ]);
        return [
            'breadcrumbs' => $breadcrumbs,
            'separate_column_left' => $this->load->controller('account/customerpartner/column_left'),
            'footer' => $this->load->controller('account/customerpartner/footer'),
            'header' => $this->load->controller('account/customerpartner/header'),
        ];
    }
}
