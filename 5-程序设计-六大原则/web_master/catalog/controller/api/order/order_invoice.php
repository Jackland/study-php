<?php

use App\Components\RemoteApi;
use App\Components\RemoteApi\B2BManager\Enums\FileResourceTypeEnum;
use App\Components\Storage\StorageCloud;
use App\Enums\Order\OcOrderTypeId;
use App\Enums\Order\OrderInvoiceStatus;
use App\Enums\Product\ProductType;
use App\Helper\CountryHelper;
use App\Helper\ImageHelper;
use App\Logging\Logger;
use App\Models\Customer\Customer;
use App\Repositories\Order\OrderInvoiceRepository;
use App\Services\Order\OrderInvoiceService;
use Framework\Helper\FileHelper;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class ControllerApiOrderOrderInvoice
 */
class ControllerApiOrderOrderInvoice extends ControllerApiBase
{
    private $invoiceRepo;
    private $customer;

    public function generateInvoice()
    {
        // 设置最大替换次数
        ini_set('max_execution_time', '300');
        ini_set('memory_limit', '1024M');
        ini_set('pcre.backtrack_limit', 50000000);

        $data = $this->request->post();
        $validator = $this->request->validateData($data, [
            'invoice_id' => 'required|int',
            'buyer_id' => 'required|int'
        ]);
        if ($validator->fails()) {
            return $this->jsonFailed($validator->errors()->first());
        }

        $this->invoiceRepo = app(OrderInvoiceRepository::class);
        $invoiceInfo = $this->invoiceRepo->getNeedDealInvoiceInfo($data['buyer_id'], $data['invoice_id']);
        if ($invoiceInfo) {
            $this->customer = Customer::where('customer_id', $data['buyer_id'])->first();
            if (! $this->customer ) {
                return $this->jsonFailed('Invalid Buyer');
            }

            $res = $this->createPdf($invoiceInfo);
            if ($res) {
                return $this->jsonSuccess();
            }
        }

        return $this->jsonFailed('Deal Failed');
    }

    /**
     * @param @param OrderInvoice $orderInvoice
     * @return bool
     * @throws \Framework\Exception\InvalidConfigException
     */
    private function createPdf($orderInvoice)
    {
        $invoice = $this->invoiceRepo->getInvoiceData(explode(',', $orderInvoice->order_ids), $orderInvoice->seller_id);

        if (empty($invoice['orderList'])) {
            return false;
        }

        $currency = CountryHelper::getCurrencyUnitNameById($this->customer->country_id);
        $subTotalAmount = 0;
        $promotionAmount = 0;
        $couponAmount = 0;
        $data = [];
        $orderData = [];

        foreach ($invoice['orderList'] as $item) {
            $subData = [];
            $priceQuote = $item->amount_price_per ? $item->amount_price_per : 0;
            $unitPrice = $item->price - $priceQuote;
            $unitFreight = $item->freight_per + $item->package_fee;
            $subTotal = ($item->price + $item->freight_per + $item->package_fee - $priceQuote) * $item->quantity;
            $subData['subTotalFormat'] = $this->currency->formatCurrencyPrice($subTotal, $currency);
            $subData['promotionFormat'] = $this->currency->formatCurrencyPrice($item->campaign_amount, $currency);
            $subData['couponFormat'] = $this->currency->formatCurrencyPrice($item->coupon_amount, $currency);
            $total = $subTotal - $item->coupon_amount - $item->campaign_amount;
            $subData['totalFormat'] =  $this->currency->formatCurrencyPrice($total, $currency);
            $subData['itemCode'] = $item->sku;
            $subData['orderId'] = $item->order_id;
            $subData['quantity'] = $item->quantity;
            $subData['agreementId'] = '';
            $subData['agreementProp'] = '';
            $subData['agreementDesc'] = '';
            if ($item->type_id == OcOrderTypeId::TYPE_MARGIN && isset($invoice['marginList'][$item->agreement_id])) { // 现货处理
                if ($item->product_type == ProductType::MARGIN_DEPOSIT) { // 现货头款
                    $subData['quantity'] = $invoice['marginList'][$item->agreement_id]['num'];
                }
                $subData['agreementId'] = $invoice['marginList'][$item->agreement_id]['agreement_id'];
                $subData['agreementProp'] = $item->product_type == ProductType::MARGIN_DEPOSIT
                    ? sprintf('%.2f', $invoice['marginList'][$item->agreement_id]['payment_ratio'] - (! empty($invoice['marginList'][$item->agreement_id]['buyer_payment_ratio']) ? $invoice['marginList'][$item->agreement_id]['buyer_payment_ratio'] : 0 )) . '%'
                    : sprintf('%.2f' ,(100 - $invoice['marginList'][$item->agreement_id]['payment_ratio'])) . '%';
                $subData['agreementDesc'] = 'M';
                $subData['itemCode'] = $invoice['marginList'][$item->agreement_id]['sku'];
            }
            if ($item->type_id == OcOrderTypeId::TYPE_FUTURE && isset($invoice['futureList'][$item['agreement_id']])) { // 期货处理
                if ($item->product_type == ProductType::FUTURE_MARGIN_DEPOSIT) { // 期货头款
                    $subData['quantity'] = $invoice['futureList'][$item->agreement_id]['num'];
                }
                $subData['agreementId'] = $invoice['futureList'][$item->agreement_id]['agreement_no'];
                $subData['agreementProp'] = $item->product_type ==  ProductType::FUTURE_MARGIN_DEPOSIT
                    ? $invoice['futureList'][$item->agreement_id]['buyer_payment_ratio'] . '%'
                    : sprintf('%.2f' ,(100 - $invoice['futureList'][$item->agreement_id]['buyer_payment_ratio'])) . '%';
                $subData['agreementDesc'] = 'F';
                $subData['itemCode'] = $invoice['futureList'][$item->agreement_id]['sku'];
            }
            if (in_array($item->product_type, [ProductType::MARGIN_DEPOSIT, ProductType::FUTURE_MARGIN_DEPOSIT])) {
                $subData['unitPriceFormat'] = $this->currency->formatCurrencyPrice($unitPrice/$subData['quantity'], $currency);
                $subData['unitFreightFormat'] = $this->currency->formatCurrencyPrice($unitFreight/$subData['quantity'], $currency);
            } else {
                $subData['unitPriceFormat'] = $this->currency->formatCurrencyPrice($unitPrice, $currency);
                $subData['unitFreightFormat'] = $this->currency->formatCurrencyPrice($unitFreight, $currency);
            }

            // 小计数据
            $subTotalAmount += $subTotal;
            $promotionAmount += $item->campaign_amount;
            $couponAmount += $item->coupon_amount;
            $data['list'][] = $subData;
            // 订单对象数据
            if (! isset($orderData[$item->order_id])) {
                $orderData[$item->order_id] = ['orderId' => $item->order_id, 'paymentMethod' => $item->payment_method, 'paymentDate' => $item->date_modified->format('m/d/Y H:i:s')];
            }
        }
        $data['subTotalAmount'] = $this->currency->formatCurrencyPrice($subTotalAmount, $currency);
        $data['promotionAmount'] = $this->currency->formatCurrencyPrice($promotionAmount, $currency);
        $data['couponAmount'] = $this->currency->formatCurrencyPrice($couponAmount, $currency);
        $totalAmount = $subTotalAmount - $promotionAmount - $couponAmount;
        $data['totalAmount'] = $this->currency->formatCurrencyPrice($totalAmount, $currency);
        $data['storeName'] = $orderInvoice->firstname . $orderInvoice->lastname;
        $data['orderData'] = $orderData;
        $fileName = 'Invoice_' . $data['storeName'] . $orderInvoice->serial_number . '.pdf';
        $data['fileName'] = mb_substr($fileName, 0, 25, 'utf-8') . '...';
        $nowTime = time();

        // 临时保存生成的 pdf
        $tmpSaveFilename = aliases('@runtime/tmp/' . $fileName);
        FileHelper::createDirectory(dirname($tmpSaveFilename));

        $mpdf = app('mpdf')->config([
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 36,
            'margin_bottom' => 30,
        ]);
        //$mpdf->shrink_tables_to_fit = 1; // 禁止表格大小调整
        $formatDate = $orderInvoice->create_time->format('m/d/Y H:i:s');
        $mpdf->getMpdf()->SetHTMLHeader($this->invoiceHeader(substr($formatDate, 0, 10), $orderInvoice->serial_number, $data['totalAmount'], $orderInvoice->screenname, $data['storeName']));
        $mpdf->getMpdf()->SetHTMLFooter($this->invoiceFooter($formatDate));
        $mpdf->getMpdf()->AddPage('L'); // 设置页面为横向
        $mpdf->loadView('@pdfTemplate/orderInvoice/invoice_pdf_body.twig', $data)->generate($tmpSaveFilename);

        // 上传文件
        $exception = null;
        $filePath = '';
        try {
            StorageCloud::invoiceFile()->writeFile(new UploadedFile($tmpSaveFilename, $fileName), $orderInvoice['buyer_id'], $fileName);
            $filePath =  StorageCloud::invoiceFile()->getFullPath($orderInvoice['buyer_id'] . '/' . $fileName);
        } catch (Throwable $e) {
            $exception = $e;
        } finally {
            @unlink($tmpSaveFilename);
        }
        if ($exception) {
            app(OrderInvoiceService::class)->updateInvoice($orderInvoice['id'], OrderInvoiceStatus::FAILURE, date('Y-m-d H:i:s', $nowTime));
            Logger::error($exception);
            return false;
        }

        app(OrderInvoiceService::class)->updateInvoice($orderInvoice['id'], OrderInvoiceStatus::SUCCESS, date('Y-m-d H:i:s', $nowTime), $filePath);
        return true;
    }

    /**
     * Invoice PDF页眉
     *
     * @param string $nowDate 时间 m/d/Y
     * @param string $serialNumber 客户编号
     * @param string $totalAmount 带符号总金额
     * @param string $screenName 店铺名称
     * @param string $storeName 店铺编号
     * @return string
     * @throws \Framework\Exception\InvalidConfigException
     */
    private function invoiceHeader($nowDate, $serialNumber, $totalAmount, $screenName, $storeName)
    {
        static $header;
        if (empty($header)) {
            $header = view()->render('@pdfTemplate/orderInvoice/invoice_pdf_header', [
                'invoiceDate' => '{{invoiceDate}}',
                'serialNumber' => '{{serialNumber}}',
                'totalAmount' => '{{totalAmount}}',
                'screenName' => '{{screenName}}',
                'storeName' => '{{storeName}}',
                'nickName' => '{{nickName}}',
                'logo' => ImageHelper::getImgSrcBase64Data('@public/image/invoice/invoice_logo.jpg', 'jpg')
            ]);
        }
        $nickName = $this->customer->nickname . '(' . $this->customer->user_number . ')';

        return str_ireplace(['{{invoiceDate}}', '{{serialNumber}}', '{{totalAmount}}', '{{screenName}}', '{{storeName}}', '{{nickName}}'],
            [$nowDate, $serialNumber, $totalAmount, $screenName, $storeName, $nickName], $header);
    }

    /**
     * Invoice PDF页脚
     *
     * @param string $updateDate
     * @return
     * @throws \Framework\Exception\InvalidConfigException
     */
    private function invoiceFooter($updateDate)
    {
        static $footer;
        if (empty($footer)) {
            $footer = view()->render('@pdfTemplate/orderInvoice/invoice_pdf_footer', [
                'invoiceDate' => '{{invoiceDate}}'
            ]);
        }

        return str_ireplace('{{invoiceDate}}', $updateDate, $footer);
    }
}