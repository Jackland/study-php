<?php

use App\Components\Storage\StorageCloud;
use App\Models\Customer\Customer;
use App\Widgets\VATToolTipWidget;

/**
 * Class ControllerAccountCustomerpartnerRmaManagementRmaManage
 * @property ModelAccountCustomerpartnerMarginOrder $model_account_customerpartner_margin_order
 * @property ModelAccountCustomerpartnerFuturesOrder $model_account_customerpartner_futures_order
 * @property ModelCustomerpartnerRmaManagement $model_customerpartner_rma_management
 * @property ModelAccountRmaManagement $model_account_rma_management
 * @property ModelCatalogProduct $model_catalog_product
 * @property ModelToolImage $model_tool_image
 */
class ControllerAccountCustomerpartnerRmaManagementRmaManage extends Controller
{
    const APPLY_STATUS_LIST = [
        1 => 'Applied',
        2 => 'Processed',
        3 => 'Pending',
        4 => 'Canceled',
    ];

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->load->model('customerpartner/rma_management');
    }

    // 下载rma列表
    public function downloadRmaHistory()
    {
        $reasonList = $this->getRmaReasonList();
        $applyStatusList = static::APPLY_STATUS_LIST;
        $rma_ge = $this->model_customerpartner_rma_management->getRmaListGenerator($this->request->attributes->all());
        //12591 B2B记录各国别用户的操作时间
        $toZone = getZoneByCountry($this->session->get('country'));
        $time = dateFormat('America/Los_Angeles', $toZone, date("YmdHis", time()), 'YmdHis');
        //12591 end
        $fileName = "RMAReport" . $time . ".xls";
        $head = [
            'No.', 'RMA ID', 'RMA Date', 'Name', 'Order Id', 'Order Date', 'MPN',
            'Item Code', 'Reason', 'Applied for Reshipment', 'Reshipment ID', 'Reshipment MPN',
            'Reshipment ItemCode', 'Reshipment Quantity', 'Reshipment Result',
            'Applied for Refund', 'Refund', 'Refund Result', 'Customer\'s Comments', 'Status', 'Processed Date'
        ];
        $content = [];
        if ($rma_ge && $rma_ge instanceof Generator) {
            $index = 1;
            foreach ($rma_ge as $key => $result) {
                $result = get_object_vars($result);
                if ($result['rma_type'] == 2) {
                    $result['reorder_id'] = null;
                    $result['mpn'] = null;
                    $result['sku'] = null;
                    $result['reshipmentQty'] = null;
                }
                $content[$key] = [
                    $index++,
                    $result['rma_order_id'] . "\t",
                    $result['create_time'],
                    $result['nickName'],
                    $result['order_id'],
                    $result['order_create_date'],
                    $result['refundMpn'],
                    $result['refundSku'],
                    $reasonList[$result['reason_id']] ?? '',
                    $result['apply_for_reshipment'],
                    $result['reorder_id'],
                    $result['mpn'],
                    $result['sku'],
                    $result['reshipmentQty'],
                    $result['reshipment_result'],
                    $result['apply_for_refund'],
                    $result['actual_refund_amount'] > 0 ? $result['actual_refund_amount'] + $result['coupon_amount'] : '',
                    $result['refund_result'],
                    $result['comments'] ?? '',
                    $applyStatusList[$result['name']] ?? '',
                    $result['processed_date']
                ];
            }
        }
        outputExcel($fileName, $head, $content, $this->session);
    }

    // 下载重发rma列表
    public function downloadReshipment()
    {
        $reasonList = $this->getRmaReasonList();
        $applyStatusList = static::APPLY_STATUS_LIST;
        $rma_ge = $this->model_customerpartner_rma_management->getRmaListGenerator($this->request->attributes->all());
        //12591 B2B记录各国别用户的操作时间
        $toZone = getZoneByCountry($this->session->get('country'));
        $time = dateFormat('America/Los_Angeles', $toZone, date("YmdHis", time()), 'YmdHis');
        //12591 end
        $fileName = "Re_Order" . $time . ".xls";
        $head = [
            'RMA ID', 'Name', 'BuyerCode', 'Order ID', 'Order Date', 'Reshipment MPN',
            'Reshipment ItemCode', 'Reshipment Quantity', 'Reshipment Type', 'Customer\'s Comments',
            'Reason', 'Result', 'RMA Status', 'RMA Date', 'Processed Date',
        ];
        $content = [];
        if ($rma_ge && $rma_ge instanceof Generator) {
            foreach ($rma_ge as $key => $result) {
                $result = get_object_vars($result);
                if ($result['rma_type'] == 2) {
                    $result['reorder_id'] = null;
                    $result['mpn'] = null;
                    $result['sku'] = null;
                    $result['product_ids'] = null;
                    $result['reshipmentQty'] = null;
                }
                $product_types = [];
                if (!empty($result['product_ids'])) {
                    $product_ids = explode(',', $result['product_ids']);
                    foreach ($product_ids as $product_id) {
                        array_push(
                            $product_types,
                            $this->checkProductIsPart($product_id) ? 'Part' : 'New product'
                        );
                    }
                }
                $content[$key] = [
                    $result['rma_order_id'] . "\t",
                    $result['buyer_name'],
                    $result['buyer_code'],
                    $result['order_id'],
                    $result['order_create_date'],
                    $result['mpn'],
                    $result['sku'],
                    $result['reshipmentQty'],
                    join(';', $product_types),
                    $result['comments'] ?? '',
                    $reasonList[$result['reason_id']] ?? '',
                    $result['reshipment_result'],
                    $applyStatusList[$result['name']] ?? '',
                    $result['create_time'],
                    $result['processed_date']
                ];
            }
        }
        outputExcel($fileName, $head, $content, $this->session);
    }

    // 下载返金rma列表
    public function downloadRefund()
    {
        $reasonList = $this->getRmaReasonList();
        $applyStatusList = static::APPLY_STATUS_LIST;
        $rma_ge = $this->model_customerpartner_rma_management->getRmaListGenerator($this->request->attributes->all());
        //12591 B2B记录各国别用户的操作时间
        $toZone = getZoneByCountry($this->session->get('country'));
        $time = dateFormat('America/Los_Angeles', $toZone, date("YmdHis", time()), 'YmdHis');
        //12591 end
        $fileName = "Refund" . $time . ".xls";
        $head = [
            'RMA ID', 'Name', 'BuyerCode', 'Order ID', 'Create Date', 'Refund MPN',
            'Refund ItemCode', 'Refund Quantity', 'Applied Refund Amount', 'Seller\'s Refund Amount',
            'Customer\'s Comments', 'Reason', 'Result',
            'RMA Status', 'RMA Date', 'Processed Date'
        ];
        $content = [];
        if ($rma_ge && $rma_ge instanceof Generator) {
            foreach ($rma_ge as $key => $result) {
                $result = get_object_vars($result);
                if ($result['rma_type'] == 2) {
                    $result['reorder_id'] = null;
                    $result['mpn'] = null;
                    $result['sku'] = null;
                    $result['reshipmentQty'] = null;
                }
                $content[$key] = [
                    $result['rma_order_id'] . "\t",
                    $result['buyer_name'],
                    $result['buyer_code'],
                    $result['order_id'],
                    $result['order_create_date'],
                    $result['refundMpn'],
                    $result['refundSku'],
                    $result['refund_quantity'] ?? 0,
                    $result['apply_refund_amount'] + $result['coupon_amount'],
                    isset($result['actual_refund_amount']) ? $result['actual_refund_amount'] + $result['coupon_amount'] : 0,
                    $result['comments'] ?? '',
                    $reasonList[$result['reason_id']] ?? '',
                    $result['refund_result'],
                    $applyStatusList[$result['name']] ?? '',
                    $result['create_time'],
                    $result['processed_date'],
                ];
            }
        }
        outputExcel($fileName, $head, $content, $this->session);
    }

    public function getDateTime()
    {
        $data = date('Y-m-d H:i:s');
        $this->response->success($data);
    }

    // region api
    // 获取rma management页面
    public function getRmaManagementInfo()
    {
        $this->load->language('account/customerpartner/rma_management');
        $data['date'] = date('Y-m-d H:i:s');
        $this->response->setOutput($this->load->view('account/customerpartner/rma_management/rma_management_list', $data));
    }

    // reshipment order 页面
    public function getReshipmentOrderInfo()
    {
        $this->load->language('account/customerpartner/rma_management');
        $this->load->language('common/cwf');
        $this->response->setOutput($this->load->view('account/customerpartner/rma_management/reshipment_order_list'));
    }

    // refund application页面
    public function getRefundInfo()
    {
        $this->load->language('account/customerpartner/rma_management');
        $this->load->language('common/cwf');
        $this->response->setOutput($this->load->view('account/customerpartner/rma_management/refund_application_list'));
    }

    public function getRmaManagementList()
    {
        $this->getList();
    }

    protected function getList()
    {
        $filter_data = $this->request->attributes->all();
        $orderStatus = $this->getOrderStatus();
        $reasonList = $this->getRmaReasonList();
        // model
        $this->load->model('account/customerpartner/margin_order');
        $this->load->model('account/customerpartner/futures_order');
        $this->load->model('account/rma_management');
        $this->load->model('catalog/product');
        $this->load->model('tool/image');

        $rma_total = $this->model_customerpartner_rma_management->getRmaListCount($filter_data);
        $rma_array = $this->model_customerpartner_rma_management->getRmaList($filter_data);
        $rma_list = [];

        $buyerIds = array_column($rma_array, 'customer_id');
        $buyerCustomerModelMap = Customer::query()->with('buyer')->whereIn('customer_id', $buyerIds)->get()->keyBy('customer_id');
        foreach ($rma_array as $rma) {
            // 判断是否是现货保证金
            $margin_order_product_ids = $this->model_account_customerpartner_margin_order->getMarginAgreeInfo($rma['order_id']);
            $marginFlag = in_array($rma['rop_product_id'], array_keys($margin_order_product_ids));
            $agreement_info = $this->model_account_customerpartner_margin_order->getAgreementInfo(
                $margin_order_product_ids[$rma['rop_product_id']] ?? 0
            );
            // 判断是否是期货保证金
            $futures_order_product_ids = $this->model_account_customerpartner_futures_order->getFuturesAgreeInfo($rma['order_id']);
            $futuresFlag = in_array($rma['rop_product_id'], array_keys($futures_order_product_ids));
            $futures_agreement_info = $this->model_account_customerpartner_futures_order->getAgreementInfo(
                $futures_order_product_ids[$rma['rop_product_id']] ?? 0
            );
            // 日本seller的apply for refund为整数
            $rma['apply_refund_amount'] = $this->customer->isJapan()
                ? (int)$rma['apply_refund_amount']
                : $rma['apply_refund_amount'];
            //更改
            $skuArray = array();
            $mpnArray = array();
            if ($rma['from_customer_order_id']) {
                $res = $this->orm->table('tb_sys_customer_sales_reorder as csr')
                    ->leftJoin('tb_sys_customer_sales_reorder_line as csrl', 'csr.id', '=', 'csrl.reorder_header_id')
                    ->leftJoin('tb_sys_customer_sales_reorder_line_history as csrlh', 'csrlh.reorder_header_id', '=', 'csr.id')
                    ->leftJoin('oc_yzc_rma_order_product as rop', 'rop.rma_id', '=', 'csr.rma_id')
                    ->leftJoin('oc_product as op', 'op.product_id', '=', 'rop.product_id')
                    ->where('csr.rma_id', $rma['id'])
                    ->orderBy('csrlh.line_history_id', 'desc')
                    ->selectRaw('ifnull(csrlh.qty,csrl.qty) as qty,op.sku,op.mpn')->first();
                if ($res) {
                    array_push($skuArray, $res->sku);
                    array_push($mpnArray, $res->mpn);
                    if ($rma['rma_type'] != 2) {
                        $rma['apply_for_reshipment'] = 'Reshipment[QTY:' . $res->qty . ']';
                    }
                }
            } else {
                foreach (explode(",", $rma['sku']) as $sku) {
                    array_push($skuArray, $sku);
                }
                foreach (explode(",", $rma['mpn']) as $mpn) {
                    array_push($mpnArray, $mpn);
                }
            }
            // tags
            $tags = array();
            foreach (explode(",", $rma['rop_product_id']) as $id) {
                $tag_array = $this->model_catalog_product->getProductSpecificTag($id);
                $loop_tag = array();
                if (!empty($tag_array)) {
                    foreach ($tag_array as $tag) {
                        if (isset($tag['origin_icon']) && !empty($tag['origin_icon'])) {
                            //采用唯一图片  LTL.jpg  part.jpg  combo-img.png
                            $img_url = $this->model_tool_image->getOriginImageProductTags($tag['origin_icon']);
                            $loop_tag[] = '<img data-toggle="tooltip"  class="' . $tag['class_style'] . '" title="' . $tag['description'] . '" style="padding-left: 1px" src="' . $img_url . '">';
                        }
                    }
                }
                array_push($tags, $loop_tag);
            }
            // 获取rma文件信息
            $rmaFiles = [];
            $fileList = $this->model_account_rma_management->getRmaOrderFile($rma['id'], 1);
            $fileList->map(function ($item) use (&$rmaFiles) {
                $item = get_object_vars($item);
                $imageUrl =  StorageCloud::rmaFile()->getUrl($item['file_path']);
                array_push($rmaFiles, $imageUrl);
            });

            $rma_list[] = [
                'buyer_id' => $rma['customer_id'],
                'ex_vat' => VATToolTipWidget::widget(['customer' => $buyerCustomerModelMap->get($rma['customer_id']), 'is_show_vat' => true])->render(),
                'nickName' => $rma['nickName'],
                'is_home_pickup' => in_array($rma['customer_group_id'], COLLECTION_FROM_DOMICILE),
                'rma_order_id' => $rma['rma_order_id'],
                'id' => $rma['id'],
                'create_time' => $rma['create_time'],
                'seller_status' => $rma['seller_status'],
                'cancel_rma' => $rma['cancel_rma'],
                'refundSku' => $rma['refundSku'],
                'refundMpn' => $rma['refundMpn'],
                'status_refund' => $rma['status_refund'],
                'status_reshipment' => $rma['status_reshipment'],
                'name' => $rma['name'],
                'apply_for_reshipment' => $rma['apply_for_reshipment'],
                'apply_for_refund' => ($rma['apply_for_refund'] != 'No')
                    ? 'Refund[' . $this->currency->format($rma['apply_refund_amount'] + $rma['coupon_amount'], $this->session->get('currency')) . ']'
                    : '',
                'processed_date' => $rma['processed_date'],
                'tag' => $tags,
                'marginFlag' => $marginFlag,
                'margin_agreement_link' => dprintf(
                    $this->language->get('global_margin_sign'),
                    ['id' => $agreement_info ? $agreement_info['agreement_id'] : 0]
                ),
                'futuresFlag' => $futuresFlag,
                'futures_agreement_link' => dprintf(
                    $this->language->get('global_backend_futures_sign'),
                    [
                        'id' => $futures_agreement_info ? $futures_agreement_info['id'] : 0,
                        's_id' => $futures_agreement_info ? $futures_agreement_info['agreement_no'] : 0
                    ]
                ),
                'comments' => $rma['comments'],
                // reshipment order
                'order_id' => $rma['order_id'],
                'delivery_type' => $rma['delivery_type'],
                'reshipment' => [
                    'reshipment_id' => $rma['reshipment_id'],
                    'reshipment_order_status' => $orderStatus[$rma['reshipment_order_status']] ?? '',
                    'comments' => $rma['comments'],
                    'reshipment_quantity' => $rma['reshipmentQty'],
                    'seller_reshipment_comments' => $rma['seller_reshipment_comments'],
                    'files' => $rmaFiles,
                    'reason' => $reasonList[$rma['reason_id']] ?? '',
                    'sku' => $rma['sku'] ? explode(',', $rma['sku']) : [],
                    'mpn' => $rma['mpn'] ? explode(',', $rma['mpn']) : [],
                ],
                // refund application
                'refund' => [
                    'apply_amounts' => $this->currency->format($rma['apply_refund_amount'] + $rma['coupon_amount'], $this->session->get('currency')),
                    'actual_amounts' => $this->currency->format($rma['actual_refund_amount'] > 0 ?  $rma['actual_refund_amount'] + $rma['coupon_amount'] : '', $this->session->get('currency')),
                    'comments' => $rma['comments'],
                    'seller_refund_comments' => $rma['seller_refund_comments'],
                    'refund_quantity' => $rma['refund_quantity'],
                    'files' => $rmaFiles,
                    'reason' => $reasonList[$rma['reason_id']] ?? '',
                    'refund_status' => $rma['status_refund'],
                ],
                'sku' => $skuArray,
                'mpn' => $mpnArray,
                // 区分是否是7天处理的rma
                'isNewApplyRma' => strtotime($rma['create_time']) >= strtotime(configDB('new_rma_apply_date', '2021-10-28 05:00:00'))
            ];
        }
        return $this->json(['total' => $rma_total, 'rows' => $rma_list]);
    }
    // end region

    /**
     * @return array
     */
    private function getOrderStatus(): array
    {
        $res = $this->orm->table('tb_sys_dictionary')
            ->where('DicCategory', 'CUSTOMER_ORDER_STATUS')
            ->get(['DicKey', 'DicValue']);

        $res = $res->keyBy('DicKey')->map(function ($item) {
            return $item->DicValue;
        });

        return $res->toArray();
    }

    /**
     * @return array
     */
    private function getRmaReasonList(): array
    {
        $res = $this->orm->table('oc_yzc_rma_reason')
            ->where('status', 1)
            ->get(['reason_id', 'reason']);

        $res = $res->keyBy('reason_id')->map(function ($item) {
            return $item->reason;
        });

        return $res->toArray();
    }

    /**
     * 校验一个产品是不是配件
     * @param int $product_id
     * @return bool
     */
    private function checkProductIsPart(int $product_id): bool
    {

        $product_tags = $this->orm
            ->table('oc_product_to_tag')
            ->where('product_id', $product_id)
            ->pluck('tag_id')
            ->toArray();
        // hard code 1-ltl 2-part 3-combo
        // 参照表oc_tag oc_product_to_tag

        return in_array(2, $product_tags);
    }
}
