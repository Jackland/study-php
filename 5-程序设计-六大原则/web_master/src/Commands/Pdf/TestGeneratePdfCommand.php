<?php

namespace App\Commands\Pdf;

use App\Helper\ImageHelper;
use App\Models\SalesOrder\CustomerSalesOrderPickUp;
use App\Repositories\SalesOrder\CustomerSalesOrderRepository;
use Illuminate\Console\Command;

class TestGeneratePdfCommand extends Command
{
    protected $signature = 'pdf:test-generate-pdf {--action= : 定义的操作}';
    protected $description = '测试生成 pdf';
    protected $help = '';

    public function handle()
    {
        $action = $this->option('action');
        $arr = explode('_', $action);
        $action = $arr[0];
        unset($arr[0]);
        $params = $arr;
        $this->{$action}(...$params);

        return 0;
    }

    // 简单测试组件可用
    // php console.php pdf:test-generate-pdf --action simple
    private function simple()
    {
        $savePath = __DIR__ . '/tmp/simple';
        $mPDF = true;
        $domPDF = true;

        if ($mPDF) {
            // 用 mpdf 生成
            app('mpdf')
                ->loadHtml('Hello World')
                ->generate($savePath . '.m.pdf');
        }

        if ($domPDF) {
            // 用 dompdf 生成
            app('dompdf')
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                ])
                ->loadHtml('Hello World')
                ->generate($savePath . '.dom.pdf');
        }

        $this->info('success: ' . $savePath);
    }

    // 自提货销售单生成 BOL 测试
    // php console.php pdf:test-generate-pdf --action salesOrderPickUpBol_1
    private function salesOrderPickUpBol($salesOrderPickUpId)
    {
        $salesOrderPickUp = CustomerSalesOrderPickUp::find($salesOrderPickUpId);
        $savePath = __DIR__ . '/tmp/bol_' . $salesOrderPickUp->id;
        $view = '@pdfTemplate/sales_order_pick_up_bol.twig';
        $pickUpList = app(CustomerSalesOrderRepository::class)->getSalesOrderAllItemsWithSubItems($salesOrderPickUp->salesOrder);
        $viewData = [
            'sales_order' => $salesOrderPickUp->salesOrder,
            'sales_order_pick_up' => $salesOrderPickUp,
            'warehouse' => $salesOrderPickUp->warehouse,
            'buyer_customer' => $salesOrderPickUp->salesOrder->buyerCustomer,
            'pick_up_list' => $pickUpList,
            'pick_up_list_total_qty' => array_sum(array_values($pickUpList)),
            'barcode' => app('barcode')->generateForImgSrc($salesOrderPickUp->bol_num),
            'logo' => ImageHelper::getImgSrcBase64Data('@public/image/logo/logo-logistics.png')
        ];
        $mPDF = true;
        $domPDF = false;

        if ($mPDF) {
            // 用 mpdf 生成
            app('mpdf')
                ->config([
                    'margin_left' => 5,
                    'margin_right' => 5,
                    'margin_top' => 6,
                    'margin_bottom' => 6,
                ])
                ->loadView($view, $viewData)
                //->generateHtml($savePath . '.m.html');
                ->generate($savePath . '.m.pdf');
        }

        if ($domPDF) {
            // 用 dompdf 生成
            app('dompdf')
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                ])
                ->loadView($view, $viewData)
                //->generateHtml($savePath . '.m.html');
                ->generate($savePath . '.dom.pdf');
        }

        $this->info('success: ' . $savePath);
    }
}
