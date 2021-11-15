<?php

namespace App\Services\SalesOrder;

use App\Components\RemoteApi;
use App\Components\RemoteApi\B2BManager\Enums\FileResourceTypeEnum;
use App\Components\UniqueGenerator;
use App\Enums\SalesOrder\CustomerSalesOrderPickUpStatus;
use App\Enums\SalesOrder\CustomerSalesOrderStatus;
use App\Helper\ImageHelper;
use App\Logging\Logger;
use App\Models\SalesOrder\CustomerSalesOrderPickUp;
use App\Repositories\SalesOrder\CustomerSalesOrderRepository;
use Framework\Helper\FileHelper;
use Throwable;

class SalesOrderPickUpService
{
    /**
     * 生成自提货的 BOL 文件，子状态变为备货中
     * 允许多次调用，每次调用都会重新生成 BOL 文件和 BOL 单号（因为存在可以多次回到待确认的情况）
     * @param int $salesOrderId
     * @return bool
     */
    public function generateBOL(int $salesOrderId): bool
    {
        $salesOrderPickUp = CustomerSalesOrderPickUp::query()->where('sales_order_id', $salesOrderId)->first();
        if (!$salesOrderPickUp) {
            return false;
        }
        $canGenerate = false;
        $generateBOLNum = true;
        if ($salesOrderPickUp->pick_up_status == CustomerSalesOrderPickUpStatus::PICK_UP_INFO_TBC) {
            // 待确认可以生成（待确认->备货中）
            $canGenerate = true;
        }
        if (
            !$canGenerate
            && $salesOrderPickUp->pick_up_status == CustomerSalesOrderPickUpStatus::DEFAULT
            && $salesOrderPickUp->salesOrder->order_status == CustomerSalesOrderStatus::BEING_PROCESSED
        ) {
            // 销售单BP，子状态默认可以生成（BP->备货中）
            $canGenerate = true;
        }
        if (
            !$canGenerate
            && $salesOrderPickUp->pick_up_status == CustomerSalesOrderPickUpStatus::IN_PREP
        ) {
            // 如果状态已经是备货中的，可以重新生成，一般为BOL异常的时候，或者仓库在备货中时又做了全部分单
            $canGenerate = true;
            $generateBOLNum = false; // 备货中的不修改 bol 单号，因为备货中 buyer 已经可以下载 BOL 文件了，防止 buyer 下载后又变化了
        }
        if (!$canGenerate) {
            return false;
        }
        // 生成提货单号
        if ($generateBOLNum) {
            $salesOrderPickUp->bol_num = UniqueGenerator::date()
                ->service(UniqueGenerator\Enums\ServiceEnum::SALES_ORDER_PICK_UP_BOL_NUM)
                ->fullYear()
                ->random();
        }
        // 临时保存生成的 pdf
        $tmpSaveFilename = aliases('@runtime/tmp/salesOrderPickUpBOL_' . $salesOrderPickUp->id . '.pdf');
        FileHelper::createDirectory(dirname($tmpSaveFilename));
        $pickUpList = app(CustomerSalesOrderRepository::class)->getSalesOrderAllItemsWithSubItems($salesOrderPickUp->salesOrder);
        app('mpdf')
            ->config([
                'margin_left' => 5,
                'margin_right' => 5,
                'margin_top' => 6,
                'margin_bottom' => 6,
            ])
            ->loadView('@pdfTemplate/sales_order_pick_up_bol.twig', [
                'sales_order' => $salesOrderPickUp->salesOrder,
                'sales_order_pick_up' => $salesOrderPickUp,
                'warehouse' => $salesOrderPickUp->warehouse,
                'buyer_customer' => $salesOrderPickUp->salesOrder->buyerCustomer,
                'pick_up_list' => $pickUpList,
                'pick_up_list_total_qty' => array_sum(array_values($pickUpList)),
                'barcode' => app('barcode')->generateForImgSrc($salesOrderPickUp->bol_num),
                'logo' => ImageHelper::getImgSrcBase64Data('@public/image/logo/logo-logistics.png')
            ])
            ->generate($tmpSaveFilename);
        // 上传文件
        $exception = null;
        try {
            $data = RemoteApi::file()->upload(FileResourceTypeEnum::SALES_ORDER_PICK_UP_BOL, $tmpSaveFilename);
            RemoteApi::file()->confirmUpload($data->menuId, $data->list->pluck('subId')->all());
            $salesOrderPickUp->bol_file_id = $data->menuId;
        } catch (Throwable $e) {
            $exception = $e;
        } finally {
            @unlink($tmpSaveFilename);
        }
        if ($exception) {
            Logger::error($exception);
            return false;
        }
        // 修改为备货中
        $salesOrderPickUp->pick_up_status = CustomerSalesOrderPickUpStatus::IN_PREP;
        // 保存数据
        return $salesOrderPickUp->save();
    }
}
