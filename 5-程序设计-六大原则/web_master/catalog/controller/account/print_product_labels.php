<?php

use App\Repositories\Warehouse\ReceiptRepository;
use App\Catalog\Controllers\AuthSellerController;

class ControllerAccountPrintProductLabels extends AuthSellerController
{
    public $data = [];


    public function index()
    {
        $this->load->language('account/print_product_labels');
        $this->document->setTitle(__('Label打印', [], 'catalog/view/account/print_product_labels'));
        if ($this->customer->getCountryId() == AMERICAN_COUNTRY_ID) {
            $data = $this->framework();
        } else {
            $data = $this->notAmericaFramework();
        }

        return $this->render('account/print_product_labels', $data);
    }

    public function printLabels()
    {
        $printList = trim($this->request->post('idMaps', ''), ' |');
        $printArr = explode('|', $printList);

        $printDataArr = [];
        if ($printArr) {
            foreach ($printArr as $item) {
                $info = explode(',', $item); // 标准格式 sku,mpn,qty
                if (count($info) < 3) {
                    continue;
                }
                list($sku, $mpn, $qty) = $info;
                if (!$sku || !$mpn || !is_numeric($qty) || $qty < 1 || ceil($qty) != $qty) {
                    continue;
                }
                $printData = [
                    'sku' => $sku,
                    'mpn' => $mpn,
                    'src' => $this->url->link(
                        'account/print_product_labels/printSku',
                        'filetype=PNG&dpi=120&scale=4&rotation=0&font_family=0&font_size=10&thickness=25&start=NULL&code=BCGcode128&text=' . $sku
                    )
                ];
                for ($i = 0; $i < $qty; $i++) {
                    $printDataArr[] = $printData;
                }
            }
        }

        $this->render('account/print_product_labels_print', ['productData' => $printDataArr]);
    }

    /**
     * 打印入库单列表
     *
     * @return Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getLists()
    {
        $receiveNumber = trim($this->request->get('filter_order_number', ''));
        $mpn = trim($this->request->get('filter_mpn', ''));
        $itemCode = trim($this->request->get('filter_item_code', ''));
        $customerId = $this->customer->getId();

        if (!$receiveNumber && !$mpn && !$itemCode) {
            $this->json([]);
        }

        $receiptRepo = app(ReceiptRepository::class);
        $list = $receiptRepo->getReceiptAutocompleteList($customerId, 0, $receiveNumber, $mpn, $itemCode);

        return $this->json($list);
    }

    /**
     * 筛选联想
     *
     * @return Symfony\Component\HttpFoundation\JsonResponse
     */
    public function autocomplete()
    {
        $receiveNumber = trim($this->request->post('filter_order_number', ''));
        $mpn = trim($this->request->post('filter_mpn', ''));
        $itemCode = trim($this->request->post('filter_item_code', ''));
        $checkColumn = $this->request->post('checkColumn', '');
        $customerId = $this->customer->getId();

        if (!$receiveNumber && !$mpn && !$itemCode) {
            $this->jsonSuccess([]);
        }
        // 聚焦入库单号筛选-检索入库单表
        $checkReceipt = 0;
        if ($checkColumn == 'filter_order_number') {
            $checkReceipt = 1;
        }
        $receiptRepo = app(ReceiptRepository::class);
        $list = $receiptRepo->getReceiptAutocompleteList($customerId, 1, $receiveNumber, $mpn, $itemCode, $checkReceipt)->toArray();
        if ($checkColumn == 'filter_order_number') {
            $list = array_unique(array_column($list, 'batch_number'));
        } elseif ($checkColumn == 'filter_mpn') {
            $list = array_unique(array_column($list, 'mpn'));
        } else {
            $list = array_unique(array_column($list, 'sku'));
        }
        return $this->json($list);
    }

    public function printSku()
    {
        define('CODEGE_DIR', DIR_STORAGE . 'vendor/barcodegen/barcodegen');
        define('CODEGE_HTML_DIR', CODEGE_DIR . '/html');
        define('CODEGE_CLASS_DIR', CODEGE_DIR . '/class');
        define('IN_CB', true);
        include_once(CODEGE_HTML_DIR . '/include/function.php');
        $requiredKeys = ['code', 'filetype', 'dpi', 'scale', 'rotation', 'font_family', 'font_size', 'text'];
        foreach ($requiredKeys as $key) {
            if (!isset($_GET[$key])) {
                $this->redirectError();
            }
        }
        if (!preg_match('/^[A-Za-z0-9]+$/', $_GET['code'])) {
            $this->redirectError();
        }
        $code = $_GET['code'];
        if (!file_exists(CODEGE_HTML_DIR . '/config' . DIRECTORY_SEPARATOR . $code . '.php')) {
            $this->redirectError();
        }
        include_once(CODEGE_HTML_DIR . '/config' . DIRECTORY_SEPARATOR . $code . '.php');
        $class_dir = CODEGE_CLASS_DIR;
        require_once($class_dir . DIRECTORY_SEPARATOR . 'BCGColor.php');
        require_once($class_dir . DIRECTORY_SEPARATOR . 'BCGBarcode.php');
        require_once($class_dir . DIRECTORY_SEPARATOR . 'BCGDrawing.php');
        require_once($class_dir . DIRECTORY_SEPARATOR . 'BCGFontFile.php');

        if (!include_once($class_dir . DIRECTORY_SEPARATOR . $classFile)) {
            $this->redirectError();
        }
        include_once(CODEGE_HTML_DIR . '/config' . DIRECTORY_SEPARATOR . $baseClassFile);
        $filetypes = ['PNG' => BCGDrawing::IMG_FORMAT_PNG, 'JPEG' => BCGDrawing::IMG_FORMAT_JPEG, 'GIF' => BCGDrawing::IMG_FORMAT_GIF];
        $drawException = null;
        try {
            $color_black = new BCGColor(0, 0, 0);
            $color_white = new BCGColor(255, 255, 255);
            $code_generated = new $className();
            if (function_exists('baseCustomSetup')) {
                baseCustomSetup($code_generated, $_GET);
            }
            if (function_exists('customSetup')) {
                customSetup($code_generated, $_GET);
            }
            $code_generated->setScale(max(1, min(4, $_GET['scale'])));
            $code_generated->setBackgroundColor($color_white);
            $code_generated->setForegroundColor($color_black);
            if ($_GET['text'] !== '') {
                $text = convertText($_GET['text']);
                $code_generated->parse($text);
            }
        } catch (Exception $exception) {
            $drawException = $exception;
        }
        $drawing = new BCGDrawing('', $color_white);
        if ($drawException) {
            $drawing->drawException($drawException);
        } else {
            $drawing->setBarcode($code_generated);
            $drawing->setRotationAngle($_GET['rotation']);
            $drawing->setDPI($_GET['dpi'] === 'NULL' ? null : max(72, min(300, intval($_GET['dpi']))));
            $drawing->draw();
        }
        switch ($_GET['filetype']) {
            case 'PNG':
                header('Content-Type: image/png');
                break;
            case 'JPEG':
                header('Content-Type: image/jpeg');
                break;
            case 'GIF':
                header('Content-Type: image/gif');
                break;
        }

        $drawing->finish($filetypes[$_GET['filetype']]);

    }

    /**
     * 跳转错误页面
     */
    public function redirectError()
    {
        $this->response->redirect($this->url->link('error/not_found'));
    }

    public function isLogin(): bool
    {
        /** @var \Cart\Customer $customer */
        $customer = $this->registry->get('customer');
        return (bool)$customer->isLogged();
    }

    private function framework()
    {
        $breadcrumbs = [
            [
                'text' => __('入库管理', [], 'catalog/view/customerpartner/warehouse/receipt_list'),
                'href' => 'javascript:void(0);',
            ],
            [
                'text' => __('Label打印', [], 'catalog/view/account/print_product_labels'),
                'href' => $this->url->link('account/print_product_labels'),
            ]
        ];
        return [
            'breadcrumbs' => $breadcrumbs,
            'separate_view' => true,
            'is_america' => true,
            'separate_column_left' => $this->load->controller('account/customerpartner/column_left'),
            'footer' => $this->load->controller('account/customerpartner/footer'),
            'header' => $this->load->controller('account/customerpartner/header'),
        ];
    }

    private function notAmericaFramework()
    {
        $breadcrumbs = [
            [
                'text' => __('主页', [], 'common'),
                'href' => $this->url->link('common/home'),
            ],
            [
                'text' => __('Label打印', [], 'catalog/view/account/print_product_labels'),
                'href' => $this->url->link('account/print_product_labels'),
            ]
        ];

        return [
            'breadcrumbs' => $breadcrumbs,
            'is_america' => false,
            'column_left' => $this->load->controller('common/column_left'),
            'content_top' => $this->load->controller('common/column_right'),
            'content_bottom' => $this->load->controller('common/content_bottom'),
            'footer' => $this->load->controller('common/footer'),
            'header' => $this->load->controller('common/header')
        ];
    }

}
