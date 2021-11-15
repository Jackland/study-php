<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Helper\CountryHelper;
use App\Helper\CurrencyHelper;
use App\Models\CustomerPartner\CustomerPartnerToCustomer;
use App\Models\Link\CustomerPartnerToProduct;
use App\Models\Product\ProQuoteDetail;
use App\Repositories\Seller\SellerRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * 阶梯价格
 * Class ControllerCustomerpartnerSpotPriceTieredPrice
 *
 * @property ModelCommonProduct $model_common_product
 * @property ModelCustomerpartnerSpotPrice $model_customerpartner_spot_price
 */
class ControllerCustomerpartnerSpotPriceTieredPrice extends AuthSellerController
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->load->model('common/product');
    }

    public function index()
    {
        $this->load->language('customerpartner/spot_price');
        $data['currency'] =
            $this->currency->getSymbolRight($this->session->data['currency'])
                ?: $this->currency->getSymbolLeft($this->session->data['currency']);
        $data['freight_download_template_url'] = $this->url->link('account/customerpartner/delicacyManagement/downloadTemplate');
        $this->response->setOutput($this->load->view('spot_price/tiered_price/list', $data));
    }

    public function edit()
    {
        $this->load->language('customerpartner/spot_price');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addScript("catalog/view/javascript/layer/layer.js");

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_parent_title'),
            'href' => 'javascript:void(0);'
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('customerpartner/spot_price/index', '', true)
        );

        if (
            $this->config->get('marketplace_separate_view') &&
            isset($this->session->data['marketplace_separate_view']) &&
            $this->session->data['marketplace_separate_view'] == 'separate'
        ) {
            $data['separate_view'] = true;
            $data['column_left'] = '';
            $data['column_right'] = '';
            $data['content_top'] = '';
            $data['content_bottom'] = '';
            $data['separate_column_left'] = $this->load->controller('account/customerpartner/column_left');
            $data['margin'] = "margin-left: 18%";
            $data['footer'] = $this->load->controller('account/customerpartner/footer');
            $data['header'] = $this->load->controller('account/customerpartner/header');
        } else {
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['column_right'] = $this->load->controller('common/column_right');
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');
        }
        // 美国
        $data['is_pickup'] = ($this->customer->isUSA()) ? 1 : 0;
        $data['is_jpy'] = ($this->customer->isJapan()) ? 1 : 0;
        $data['product_id'] = get_value_or_default($this->request->request, 'product_id');
        $data['is_outer'] = $this->customer->isNonInnerAccount() ? 1 : 0;
        $data['alarm_price'] = $this->model_common_product->getAlarmPrice((int)$data['product_id']);
        // 是否显示云送仓提醒
        $data['is_show_cwf_notice'] = app(SellerRepository::class)->isShowCwfNotice();

        $this->response->setOutput($this->load->view('spot_price/tiered_price/edit', $data));
    }

    // 保存setting
    public function store()
    {
        $co = new Collection(json_decode(file_get_contents('php://input'), true));
        $this->load->model('customerpartner/spot_price');
        /** @var ModelCustomerpartnerSpotPrice $modelCSpotPrice */
        $modelCSpotPrice = $this->model_customerpartner_spot_price;
        if (!$co->get('product_id')) {
            $this->response->returnJson('0');
        }

        //校验商品是否属于自己，防止seller切换seller时候，携带的商品不属于自己从而造成Bug
        $checkProduct = CustomerPartnerToProduct::query()
            ->where('customer_id', customer()->getId())
            ->where('product_id', $co->get('product_id'))
            ->exists();

        if (!$checkProduct) {
            $this->response->returnJson('0');
        }

        $res = $modelCSpotPrice->addTieredPrice(
            (int)$this->customer->getId(),
            (int)$co['product_id'],
            $co->get('data')
        );

        $this->response->returnJson($res ? '1' : '0');
    }

    // region 前端 api 接口

    // 删除所有
    public function delAll()
    {
        $ids = get_value_or_default($this->request->request, 'ids', []);
        $this->load->model('customerpartner/spot_price');
        /** @var ModelCustomerpartnerSpotPrice $modelSpotPrice */
        $modelSpotPrice = $this->model_customerpartner_spot_price;
        $product_ids = $this->orm
            ->table('oc_wk_pro_quote_details')
            ->where(['seller_id' => $this->customer->getId()])
            ->whereIn('id', $ids)
            ->groupBy('product_id')
            ->pluck('product_id')
            ->toArray();
        $res = true;
        foreach ($product_ids as $product_id) {
            $res = $res && $modelSpotPrice->delTieredPrice($this->customer->getId(), $product_id);
        }
        if ($res) {
            $this->response->returnJson(['code' => 0, 'msg' => 'This operation succeeds.']);
        } else {
            $this->response->returnJson(['code' => 1, 'msg' => 'This operation failed.']);
        }
    }

    public function delId()
    {
        $ids = get_value_or_default($this->request->request, 'ids', []);
        $res = $this->deleteIds($ids);
        $restIds = $res[1];
        if (count($restIds) === 0) {
            $this->response->returnJson(['code' => 0, 'msg' => 'This operation succeeds.']);
        } else {
            $this->response->returnJson(['code' => 1, 'msg' => 'This operation failed.']);
        }
    }

    private function deleteIds(array $ids): ?array
    {
        if (empty($ids)) return [true, []];
        $ret = [];
        $this->load->model('customerpartner/spot_price');
        /** @var ModelCustomerpartnerSpotPrice $modelSpotPrice */
        $modelSpotPrice = $this->model_customerpartner_spot_price;
        $flag = false;
        foreach ($ids as $id) {
            $res = $modelSpotPrice->delTieredPriceById((int)$this->customer->getId(), $id);
            $flag = $flag || $res;
            if (!$res) {
                $ret[] = $id;
            }
        }
        return [$flag, $ret];
    }

    public function getList()
    {
        $this->load->model('customerpartner/spot_price');
        /** @var ModelCustomerpartnerSpotPrice $modelSpotPrice */
        $modelSpotPrice = $this->model_customerpartner_spot_price;
        $data = $modelSpotPrice->getTieredPriceList($this->customer->getId(), $this->request->request);
        $this->response->returnJson($data);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function downloadSpotPrice()
    {
        $mpnOrSku = trim(request('filter_sku_mpn', ''));

        $seller = CustomerPartnerToCustomer::query()->find(customer()->getId());
        $query = ProQuoteDetail::queryRead()->alias('pq')
            ->join('oc_product as p', 'pq.product_id', '=', 'p.product_id')
            ->where('pq.seller_id', $seller->customer_id)
            ->when(!empty($mpnOrSku), function ($query) use ($mpnOrSku) {
                $query->where(function ($q) use ($mpnOrSku) {
                    $q->orWhere('p.sku', 'LIKE', "%{$mpnOrSku}%");
                    $q->orWhere('p.mpn', 'LIKE', "%{$mpnOrSku}%");
                });
            })
            ->orderBy('pq.product_id', 'desc')
            ->orderBy('pq.min_quantity', 'desc')
            ->orderBy('pq.id', 'desc');

        $currencyConfig = CurrencyHelper::getCurrencyConfig()[CurrencyHelper::getCurrentCode()];
        $titlePriceSymbol = $currencyConfig['symbol_left'] ?: $currencyConfig['symbol_right'];

        $excelDataList = [];
        foreach ($query->cursor() as $item) {
            /** @var ProQuoteDetail $item */
            $excelDataList[] = [
                'Store Name' => $seller->screenname . "\t",
                'Template ID' => $item->template_id . "\t",
                'MPN' => $item->mpn . "\t",
                'Item Code' => $item->sku . "\t",
                "Spot Price({$titlePriceSymbol}/Unit)" => CurrencyHelper::formatPrice($item->home_pick_up_price, [
                    'need_format' => true,
                    'is_symbol' => true,
                ]),
                'Selling Quantity' => $item->max_quantity == $item->min_quantity ? $item->min_quantity . "\t" : $item->min_quantity . ' - ' . $item->max_quantity,
            ];
        }
        if (empty($excelDataList)) {
            $excelDataList[] = [
                'Store Name' => $seller->screenname . "\t",
                'Template ID' => '',
                'MPN' => '',
                'Item Code' => '',
                "Spot Price({$titlePriceSymbol}/Unit)" => '',
                'Selling Quantity' => '',
            ];
        }

        $headers = array_keys($excelDataList[0]);
        array_unshift($excelDataList, $headers);

        $filename = 'Spot Price Offerings_' . Carbon::now()->setTimezone(CountryHelper::getTimezone(customer()->getCountryId()))->format('Ymd');

        set_time_limit(0);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($filename);
        $sheet->fromArray($excelDataList);
        $writer = IOFactory::createWriter($spreadsheet, 'Xls');
        //导出
        return $this->response->streamDownload(
            function () use ($writer) {
                $writer->save('php://output');
            }, $filename . '.xls', ['Content-Type' => 'application/vnd.ms-excel']
        );
    }

    /**
     * 获取产品详情
     */
    public function getProductPriceDetail()
    {
        $product_id = $this->request->query->get('product_id', 0);
        $product_sku = trim($this->request->query->get('product_sku', ''));
        if (!$product_id && !$product_sku) {
            return $this->response->json('0');
        }
        $res = db(DB_PREFIX . 'product as p')
            ->select(['p.product_id', 'p.mpn', 'p.sku', 'pd.name', 'p.price', 'p.freight'])
            ->join(DB_PREFIX . 'customerpartner_to_product as c2p', ['c2p.product_id' => 'p.product_id'])
            ->leftJoin(DB_PREFIX . 'product_description as pd', ['pd.product_id' => 'p.product_id'])
            ->where([
                'p.status' => 1,
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1,
                'p.product_type' => 0,
                'c2p.customer_id' => (int)$this->customer->getId(),
            ])
            ->when($product_id != 0, function (Builder $q) use ($product_id) {
                $q->where(['p.product_id' => $product_id,]);
            })
            ->when(!empty($product_sku), function (Builder $q) use ($product_sku) {
                $q->where(function (Builder $q) use ($product_sku) {
                    $q->orWhere(['p.sku' => $product_sku,]);
                    $q->orWhere(['p.mpn' => $product_sku,]);
                });
            })
            ->first();
        if (!$res) {
            return $this->response->json('0');
        }
        $res = get_object_vars($res);
        $res['name'] = htmlspecialchars_decode($res['name']);
        // 获取阶梯价格详情
        $this->load->model('customerpartner/spot_price');
        /** @var ModelCustomerpartnerSpotPrice $modelSpotPrice */
        $modelSpotPrice = $this->model_customerpartner_spot_price;
        $data = $modelSpotPrice->getTieredPriceDetail($this->customer->getId(), $res['product_id']);
        $res['is_quote'] = !empty($data) ? 1 : 0;
        $res['alarm_price'] = $this->model_common_product->getAlarmPrice((int)$res['product_id']);
        $res['quote_detail'] = $data;
        return $this->response->json($res);
    }

    /**
     * 获取关联商品的api
     */
    public function getProducts()
    {
        $co = new Collection(json_decode(file_get_contents('php://input'), true));
        $pageSize = $co->get('page_size', 5);
        $currentPage = $co->get('page', 1);
        /** @var Builder $query */
        $query = $this->orm
            ->table(DB_PREFIX . 'product as p')
            ->select([
                'p.product_id', 'p.mpn', 'p.sku', 'pd.name', 'p.price', 'p.freight'
            ])
            ->join(DB_PREFIX . 'customerpartner_to_product as c2p', ['c2p.product_id' => 'p.product_id'])
            ->leftJoin(DB_PREFIX . 'product_description as pd', ['pd.product_id' => 'p.product_id'])
            ->where([
                'p.status' => 1,
                'p.is_deleted' => 0,
                'p.buyer_flag' => 1,
                'p.product_type' => 0,
                'c2p.customer_id' => (int)$this->customer->getId(),
            ])
            ->when(
                !empty(trim($co->get('filter_search'))),
                function (Builder $q) use ($co) {
                    $q->where(function (Builder $q) use ($co) {
                        $filter = htmlspecialchars(trim($co->get('filter_search')));
                        $q->orWhere('p.mpn', 'like', "%{$filter}%")
                            ->orWhere('p.sku', 'like', "%{$filter}%");
                    });
                }
            )
            ->when(
                !empty($co->get('product_id')),
                function (Builder $q) use ($co) {
                    $q->whereNotIn('p.product_id', $co->get('product_id'));
                }
            )
            ->orderBy('p.product_id', 'desc');
        $total = $query->count();
        if ($total <= ($currentPage - 1) * $pageSize) $currentPage = 1;
        $this->load->model('customerpartner/spot_price');
        /** @var ModelCustomerpartnerSpotPrice $modelSpotPrice */
        $modelSpotPrice = $this->model_customerpartner_spot_price;
        /** @var Collection $res */
        $res = $query->forPage($currentPage, $pageSize)->get();
        $res = $res->map(function ($item) use ($modelSpotPrice) {
            $row = get_object_vars($item);
            $row['name'] = htmlspecialchars_decode($row['name']);
            $row['freight'] = number_format(
                $row['freight'], customer()->isJapan() ? 0 : 2, '.', ''
            );
            $row['price'] = number_format(
                $row['price'], customer()->isJapan() ? 0 : 2, '.', ''
            );
            // 获取阶梯价格详情
            $data = $modelSpotPrice->getTieredPriceDetail($this->customer->getId(), $row['product_id']);
            $row['is_quote'] = !empty($data) ? 1 : 0;
            $row['quote_detail'] = $data;
            return $row;
        });
        return $this->response->json(['data' => $res->toArray(), 'total' => $total, 'page' => $currentPage]);
    }

    // end region
}
