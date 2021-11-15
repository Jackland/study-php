<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Enums\Customer\CustomerAccountingType;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use App\Helper\CountryHelper;
use App\Enums\Warehouse\BatchTransactionType;
use App\Repositories\Warehouse\InventoryRepository;
use App\Helper\SummernoteHtmlEncodeHelper;

class ControllerCustomerpartnerWarehouseBatchInventory extends AuthSellerController
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

        $this->language->load('customerpartner/warehouse/batch_inventory');
    }

    public function index()
    {
        $data = $this->framework();
        $data['inventoryType'] = BatchTransactionType::getAllTypeArr();

        return $this->render('customerpartner/warehouse/batch_inventory', $data);
    }

    // 获取列表
    public function getList()
    {
        $validateStatus = $this->validateData();
        if ($validateStatus !== true) {
            return $this->jsonFailed($validateStatus);
        }

        $filter = $this->getRequestParam();
        $filter['page'] = $this->request->get('page', 1);
        $filter['pageLimit'] = $this->request->get('page_limit', 15);
        try {
            if ($filter['filter_create_start_date']) {
                $filter['filter_create_start_date'] = Carbon::createFromFormat('Y-m-d H:i:s', $filter['filter_create_start_date'], CountryHelper::getTimezone($this->countryId))->setTimezone(date_default_timezone_get())->toDateTimeString();
            }
            if ($filter['filter_create_end_date']) {
                $filter['filter_create_end_date'] = Carbon::createFromFormat('Y-m-d H:i:s', $filter['filter_create_end_date'], CountryHelper::getTimezone($this->countryId))->setTimezone(date_default_timezone_get())->toDateTimeString();
            }

            $inventoryRepo = app(InventoryRepository::class);
            $result['total'] = $inventoryRepo->getBatchRepositoryCount($this->customerId, $filter);
            $result['rows'] = $inventoryRepo->getBatchRepositoryList($this->customerId, $filter);

            foreach ($result['rows'] as $item) {
                $item->setAttribute('inventory_days', $this->calculateInventoryDay($item->create_time, $item->transaction_type));
                $item->setAttribute('create_time_format', $item->create_time ? $item->create_time->setTimezone(CountryHelper::getTimezone($this->countryId))->toDateTimeString() : '--');
                $item->setAttribute('transaction_type_format', BatchTransactionType::getDescription($item->transaction_type));
            }
        } catch (Exception $e) {
            return $this->jsonFailed(__('系统繁忙，请稍后在试', [], 'controller/inventory'));
        }

        return $this->json($result);
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
            $list = $inventoryRepo->getBatchRepositoryList($this->customerId, $filter, 2);

            $fileName = $fileName = 'BatchInventory' . date('YmdHis') . '.xls';

            $head = [
                __('No.', [], 'controller/inventory'),
                __('Item Code', [], 'controller/inventory'),
                __('MPN', [], 'controller/inventory'),
                __('产品名称', [], 'controller/inventory'),
                __('入库类型', [], 'controller/inventory'),
                __('仓库收货数量', [], 'controller/inventory'),
                __('当前在库数', [], 'controller/inventory'),
                __('入库天数', [], 'controller/inventory'),
                __('批次号', [], 'controller/inventory'),
                __('备注', [], 'controller/inventory'),
                __('批次生成日期', [], 'controller/inventory'),
                __('入库日期', [], 'controller/inventory')
            ];
            $content = [];
            $index = 1;
            foreach ($list as $item) {
                $preData = [
                    $index++,
                    $item->sku,
                    $item->mpn,
                    SummernoteHtmlEncodeHelper::decode($item->name, true),
                    BatchTransactionType::getDescription($item->transaction_type),
                    (string)$item->original_qty,
                    (string)$item->onhand_qty,
                    (string)$this->calculateInventoryDay($item->create_time, $item->transaction_type),
                    in_array($item->transaction_type, [BatchTransactionType::INVENTORY_RECEIVE, BatchTransactionType::RMA_RETURN]) ? (string)$item->common_one . "\t" : (string)$item->batch_number . "\t",
                    $item->common_two,
                    $item->create_time ? $item->create_time->setTimezone(CountryHelper::getTimezone($this->countryId))->toDateTimeString() : '--',
                    $item->receive_date ? Carbon::createFromFormat('Y-m-d H:i:s', $item->receive_date)->setTimezone(CountryHelper::getTimezone($this->countryId))->toDateTimeString() : '--',
                ];

                $content[] = $preData;
            }

            outputExcel($fileName, $head, $content);
        } catch (Exception $e) {
            return $this->jsonFailed(__('系统繁忙，请稍后在试', [], 'controller/inventory'));
        }
    }

    // 获取过滤参数
    private function getRequestParam()
    {
        $filter['filter_sku'] = trim($this->request->get('filter_sku', ''));
        $filter['filter_batch_no'] = trim($this->request->get('filter_batch_no', ''));
        $filter['filter_type'] = $this->request->get('filter_type', '');
        $filter['filter_create_start_date'] = trim($this->request->get('filter_create_start_date', ''));
        $filter['filter_create_end_date'] = trim($this->request->get('filter_create_end_date', ''));

        return $filter;
    }


    /**
     * 计算在库天数：
     *  1. 按当前用户所在国别时间计算（美国取太平洋时间）
     *  2. 当前时间没过中午12点：
     *      a. 入库单入库类型天数 = 当前时间 - 批次生成时间 + 3
     *      b. 其他类型 = 当前时间 - 批次生成时间
     *  3. 当前时间过了中午12点(>12)：
     *      a. 入库单入库类型天数 = 当前时间 - 批次生成时间 + 4
     *      b. 其他类型 = 当前时间 - 批次生成时间 + 1
     *  4. 时间计算先转换为天计算（单位为：天）
     *
     * @param string $receiveDate 入库时间(系统所在时区)
     * @param string $type 入库类型
     * @return int|string
     */
    private function calculateInventoryDay($receiveDate, int $type)
    {
        $calculateHouse = 12; // 计算临界小时
        if (empty($receiveDate)) {
            return 'N/A';
        }

        $start = Carbon::createFromFormat('Y-m-d H:i:s', $receiveDate, date_default_timezone_get())->setTimezone(CountryHelper::getTimezone($this->customer->getCountryId()))->format('Y-m-d');
        $end = Carbon::now()->setTimezone(CountryHelper::getTimezone($this->customer->getCountryId()))->format('Y-m-d');
        $days = Carbon::createFromFormat('Y-m-d', $end, CountryHelper::getTimezone($this->customer->getCountryId()))->diffInDays(Carbon::createFromFormat('Y-m-d', $start, CountryHelper::getTimezone($this->customer->getCountryId())));
        $house = Carbon::now()->format('H');
        $incDay = 0;
        if ($type == BatchTransactionType::INVENTORY_RECEIVE) {
            $incDay += 3;
        }
        if ($house > $calculateHouse) {
            $incDay += 1;
        }

        return $days + $incDay;
    }

    private function validateData()
    {
        $validationErrorMsg = [
            'in' => __('无效的入库类型', [], 'controller/inventory'),
            'regex' => __('无效的筛选时间', [], 'controller/inventory')
        ];
        $validateData = [
            'filter_type' => [
                Rule::in(BatchTransactionType::getValues())
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

        return true;
    }

    private function framework()
    {
        $this->setDocumentInfo(__('批次库存查询', [], 'catalog/document'));
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
