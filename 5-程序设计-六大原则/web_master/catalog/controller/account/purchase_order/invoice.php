<?php

use App\Catalog\Controllers\AuthBuyerController;
use App\Components\RemoteApi;
use App\Components\RemoteApi\B2BManager\Enums\FileResourceTypeEnum;
use App\Components\Storage\StorageCloud;
use App\Enums\Order\OcOrderTypeId;
use App\Enums\Order\OrderInvoiceStatus;
use App\Enums\Product\ProductType;
use App\Helper\ImageHelper;
use App\Logging\Logger;
use App\Models\Order\OrderInvoice;
use App\Repositories\Order\OrderInvoiceRepository;
use App\Services\Order\OrderInvoiceService;
use Framework\DataProvider\Paginator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ControllerAccountPurchaseOrderInvoice extends AuthBuyerController
{
    private $buyerId;
    private $invoiceRepo;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->buyerId = $this->customer->getId();
        $this->invoiceRepo = app(OrderInvoiceRepository::class);
    }

    /**
     * Invoice
     *
     * @return string
     */
    public function index()
    {
        $paginator = new Paginator(
            ['defaultPageSize' => 10]
        );

        $paginator->setTotalCount($this->invoiceRepo->getInvoiceTotal($this->buyerId));
        $list = $this->invoiceRepo->getInvoiceList($this->buyerId, $paginator->getOffset(), $paginator->getLimit());

        $data['list'] = $list;
        $data['paginator'] = $paginator;

        return $this->render('account/purchase_order/invoice/invoice_list', $data);
    }

    /**
     * 获取指定Invoice的最新状态
     */
    public function getInvoiceLastInfo()
    {
        $invoiceIds = trim($this->request->post('ids'));
        if (empty($invoiceIds)) {
            return $this->jsonSuccess();
        }
        $list = $this->invoiceRepo->getInvoiceLastInfo($this->buyerId, explode(',', $invoiceIds));
        return $this->jsonSuccess($list);
    }

    /**
     * PDF文件下载
     */
    public function download()
    {
        $id = $this->request->get('id', '');
        if (! $id) {
            return $this->redirect(['common/home'])->send();
        }
        $invoiceInfo = OrderInvoice::where('id', $id)->where('buyer_id', $this->buyerId)->first();
        if (! $invoiceInfo || ! $invoiceInfo->file_path) {
            return $this->redirect(['common/home'])->send();
        }

        return StorageCloud::root()->browserDownload($invoiceInfo->file_path);
    }
}
