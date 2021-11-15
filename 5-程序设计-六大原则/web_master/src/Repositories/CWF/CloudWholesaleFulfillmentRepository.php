<?php

namespace App\Repositories\CWF;

use App\Logging\Logger;
use App\Models\CWF\CloudWholesaleFulfillmentAssociatePre;
use App\Models\CWF\CloudWholesaleFulfillmentFileExplain;
use App\Models\CWF\CloudWholesaleFulfillmentMatchStock;
use App\Models\CWF\OrderCloudLogistics;
use App\Services\CWF\CloudWholesaleFulfillmentService;
use Framework\Exception\Exception;

class CloudWholesaleFulfillmentRepository
{

    /**
     * 获取buyer now url
     * @param int $cwfFileUploadId
     * @return array|string
     */
    public function getBatchCWFBuyNowUrl(int $cwfFileUploadId)
    {
        $bool = CloudWholesaleFulfillmentMatchStock::query()
            ->where('cwf_file_upload_id', $cwfFileUploadId)
            ->exists();
        if ($bool) {
            return url()->to(['checkout/pre_order', 'delivery_type' => 2, 'cwf_file_upload_id' => $cwfFileUploadId]);
        }
        return url()->to(['checkout/pre_order', 'delivery_type' => 2]);
    }

    /**
     * @param int $cwfFileUploadId
     * @return string
     */
    public function getBatchCWFUploadInfo(int $cwfFileUploadId): string
    {
        $cwfMatchStock = CloudWholesaleFulfillmentMatchStock::query()->alias('ms')
            ->leftJoin('tb_cloud_wholesale_fulfillment_file_upload as fu', 'fu.id', 'ms.cwf_file_upload_id')
            ->where('ms.cwf_file_upload_id', $cwfFileUploadId)
            ->where('fu.create_id', customer()->getId())
            ->get();
        $data = [];
        if ($cwfMatchStock->isNotEmpty()) {
            foreach ($cwfMatchStock as $items) {
                $data[] = [
                    'product_id' => $items->product_id,
                    'transaction_type' => $items->transaction_type,
                    'quantity' => $items->quantity,
                    'cart_id' => '',
                    'agreement_id' => $items->agreement_id,
                    'add_cart_type' => 0, //0是默认或最优价，1是常规价加入,2是阶梯价加入
                ];
            }
            return base64_encode(json_encode($data));
        }

        return base64_encode(json_encode($data));

    }

    /**
     * 校验批量导单云送仓信息以及更新数据
     * @param int $cwfFileUploadId
     * @return array
     * @throws \Exception
     */
    public function checkCWFUploadInfo(int $cwfFileUploadId): array
    {
        $json = [
            'success' => true,
            'msg' => '',
        ];
        $cwfUploadInfo = CloudWholesaleFulfillmentFileExplain::query()
            ->where('cwf_file_upload_id', $cwfFileUploadId)
            ->whereNull('cwf_order_id')
            ->get();
        if ($cwfUploadInfo->isNotEmpty()) {
            $data = [];
            $matchProductMap = [];
            $orderLogistics = [];
            foreach ($cwfUploadInfo as $items) {
                $data[$items->flag_id][] = [
                    'sku' => $items->b2b_item_code,
                    'quantity' => $items->ship_to_qty,
                    'explain_id' => $items->id,
                ];
                if (!isset($orderLogistics[$items->flag_id])) {
                    $orderLogistics[$items->flag_id] = [
                        'service_type' => 0,
                        'has_dock' => $items->loading_dock_provided,
                        'recipient' => $items->ship_to_name,
                        'phone' => $items->ship_to_phone,
                        'email' => $items->ship_to_email,
                        'address' => $items->ship_to_address_detail,
                        'country' => $items->ship_to_country,
                        'city' => $items->ship_to_city,
                        'state' => $items->ship_to_state,
                        'zip_code' => $items->ship_to_postal_code,
                        'comments' => $items->order_comments,
                        'cwf_file_upload_id' => $cwfFileUploadId,
                    ];
                }
            }
            // 库存分配问题
            try {
                [$associateSolution, $allSolution] = $this->getAvailableQuantityAndPrice($data, $cwfFileUploadId);
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'msg' => $e->getMessage(),
                ];
            }
            // 校验体积通过
            foreach ($associateSolution as $items) {
                $aRet = $this->checkUploadFileVolume($items);
                if (!$aRet['success']) {
                    return [
                        'success' => false,
                        'msg' => $aRet['msg'],
                    ];
                }
            }
            // 记录云送仓associate的记录
            foreach ($allSolution as $sku => $items) {
                foreach ($items as $k => $v) {
                    $matchProductMap[$v['product_id']] = app(CloudWholesaleFulfillmentService::class)->updateCWFMatchStock($cwfFileUploadId, $sku, $v);
                }
            }
            // 记录云送仓产品整体需要购买的详细记录
            foreach ($associateSolution as $i => $items) {
                foreach ($items as $key => $value) {
                    foreach ($value['solution'] as $k => $v) {
                        $orderLogistics[$i]['items'][] = [
                            'item_code' => $value['sku'],
                            'qty' => $v['match_quantity'],
                            'product_id' => $v['product_id'],
                            'seller_id' => $v['seller_id'],
                        ];
                        CloudWholesaleFulfillmentAssociatePre::query()->insertGetId([
                            'cwf_match_stock_id' => $matchProductMap[$v['product_id']],
                            'file_explain_id' => $value['explain_id'],
                            'cwf_file_upload_id' => $cwfFileUploadId,
                            'buyer_id' => customer()->getId(),
                            'sku' => $value['sku'],
                            'quantity' => $value['quantity'],
                            'match_qty' => $v['match_quantity'],
                        ]);
                    }
                }
            }
            // 更新orderCloudLogistics
            app(CloudWholesaleFulfillmentService::class)->insertOrderCloudLogisticsInfo($orderLogistics);
            // 结束
            return $json;
        }

        return [
            'success' => false,
            'msg' => __('上传文件中的upload Id为非法数据', [], 'repositories/cwf'),
        ];
    }

    /**
     * 根据cwfUploadId来再次确认体积是否有发生变化
     * @param int $cwfFileUploadId
     * @return array
     * @throws \Exception
     */
    public function checkVolumeByUploadId(int $cwfFileUploadId): array
    {
        $cwfIds = CloudWholesaleFulfillmentFileExplain::where('cwf_file_upload_id', $cwfFileUploadId)
            ->pluck('cwf_order_id')
            ->toArray();
        $productInfos = OrderCloudLogistics::query()->alias('ocl')
            ->leftJoinRelations(['items as i'])
            ->whereIn('ocl.id', $cwfIds)
            ->select(['i.product_id', 'i.qty as quantity', 'ocl.id', 'ocl.recipient', 'ocl.phone', 'ocl.address'])
            ->get()
            ->toArray();
        $data = [];
        foreach ($productInfos as $key => $value) {
            $data[$value['id']][] = $value;
        }
        $ret = [];
        foreach ($data as $key => $value) {
            $ret = $this->checkPreOrderCwfVolume($value);
            if ($ret['success'] == false) {
                return $ret;
            }
        }

        return $ret;

    }

    /**
     * 云送仓导单批量体积校验
     * @param array $infos
     * @param string $column
     * @return bool
     * @throws \Exception
     */
    private function checkVolume(array $infos, string $column = 'quantity'): bool
    {
        $freight = load()->library('yzc/freight');
        $productIds = array_column($infos, 'product_id');
        $volumeAll = 0;
        $cloudWholesaleFulfillmentData = $freight->getFreightAndPackageFeeByProducts($productIds);
        foreach ($infos as $key => $value) {
            if (!isset($cloudWholesaleFulfillmentData[$value['product_id']])
                || !$cloudWholesaleFulfillmentData[$value['product_id']]) {
                $json = [
                    'success' => false,
                    'msg' => __('云送仓导单无此产品信息', [], 'repositories/cwf'),
                ];
                Logger::cloudWholesaleFulfillment("云送仓导单无此产品信息product_id:{$value['product_id']}", 'error');
                break;
            } else {
                // 获取数组层级
                $temp = $cloudWholesaleFulfillmentData[$value['product_id']];
                if (isset($temp['volume_inch'])) {
                    $volumeAll += $temp['volume_inch'] * $value[$column];
                } else {
                    foreach ($temp as $k => $v) {
                        $volumeAll += $v['volume_inch'] * $v['qty'] * $value[$column];
                    }
                }
            }
        }
        if (bccomp($volumeAll, CLOUD_LOGISTICS_VOLUME_LOWER) === -1) {
            return false;
        }
        return true;

    }

    /**
     * 校验preOrder 云送仓体积
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function checkPreOrderCwfVolume(array $data): array
    {
        $json = [
            'success' => true,
            'msg' => '',
        ];

        $ret = $this->checkVolume($data);
        if (!$ret) {
            Logger::cloudWholesaleFulfillment('云送仓校验体积失败', 'error');
            return [
                'success' => false,
                'msg' => __(':recipient,:phone,:address :原云送仓尺寸报错提醒',
                    [
                        'recipient' => $data[0]['recipient'],
                        'phone' => $data[0]['phone'],
                        'address' => $data[0]['address'],
                    ], 'repositories/cwf'),
            ];
        }
        return $json;
    }


    /**
     * 首次上传校验体积是否满足条件
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function checkUploadFileVolume(array $data): array
    {
        $json = [
            'success' => true,
            'msg' => '',
        ];
        // 根据地址拆分
        // 获取所有的productIds ,需要校验combo,combo 层级为三层结构
        $column = 'match_quantity';
        $infos = [];
        $explainIds = [];
        foreach ($data as $key => $value) {
            $explainIds[] = $value['explain_id'];
            $infos = array_merge($infos, $value['solution']);
        }

        $ret = $this->checkVolume($infos, $column);
        if (!$ret) {
            Logger::cloudWholesaleFulfillment('云送仓批量导单校验体积失败', 'error');
            $tmp = CloudWholesaleFulfillmentFileExplain::query()
                ->whereIn('id', $explainIds)
                ->select('row_index')
                ->distinct()
                ->get()
                ->pluck('row_index')
                ->toArray();

            return [
                'success' => false,
                'msg' => __('Line :row :云送仓批量导单校验体积失败', ['row' => implode(',', $tmp)], 'repositories/cwf'),
            ];
        }
        return $json;

    }

    /**
     * @param array $data
     * @param int $cwfFileUploadId
     * @return array[]
     * @throws \Exception
     */
    public function getAvailableQuantityAndPrice(array $data, int $cwfFileUploadId): array
    {
        $allMatchStock = [];
        foreach ($data as $key => $value) {
            foreach ($value as $k => $v) {
                if (!isset($allMatchStock[$v['sku']])) {
                    $allMatchStock[$v['sku']] = $v['quantity'];
                } else {
                    $allMatchStock[$v['sku']] += $v['quantity'];
                }
            }
        }
        // 根据云送仓批量订单匹配库存以及price
        // 不需要验证匹配囤货的库存，云送仓不走囤货库存
        /** @var \ModelCatalogSearch $model */
        $model = load()->model('catalog/search');
        $unavailableProductIds = $model->unSeeProductId(customer()->getId());
        $allSolution = [];
        foreach ($allMatchStock as $key => $value) {
            $sku = $key;
            $quantity = $value;
            $model = new CloudWholesaleFulfillmentMatchStockRepository($sku, $quantity, $unavailableProductIds);
            [$leftQty, $solution] = $model->getMatchInfo();

            if ($leftQty > 0) {
                // 获取 sku 所在的行数
                $tmp = CloudWholesaleFulfillmentFileExplain::query()
                    ->where('cwf_file_upload_id', $cwfFileUploadId)
                    ->where('b2b_item_code', $sku)
                    ->select('row_index')
                    ->distinct()
                    ->get()
                    ->pluck('row_index')
                    ->toArray();

                $errMsg = __('Line :row :平台无可购买的库存数量，请检查下您的需求是否可调整', ['row' => implode(',', $tmp)], 'repositories/cwf');
                throw new \Exception($errMsg);
            }
            $allSolution[$key] = $solution;
        }
        $currentSolution = $allSolution;
        foreach ($data as $key => $value) {
            foreach ($value as $k => $item) {
                $data[$key][$k]['solution'] = $this->matchStockInfo($item['quantity'], $currentSolution[$item['sku']]);
            }
        }

        return [$data, $allSolution];
    }


    /**
     * 自定义匹配库存
     * @param $quantity
     * @param $data
     * @return array
     */
    private function matchStockInfo($quantity, &$data): array
    {
        $ret = [];
        foreach ($data as $key => &$value) {
            if ($value['quantity'] >= $quantity) {
                $value['match_quantity'] = $quantity;
                $value['quantity'] -= $quantity;
                $ret[] = $value;
                break;
            } else {
                if ($value['quantity'] <= 0) {
                    continue;
                } else {

                    $value['match_quantity'] = $value['quantity'];
                    $quantity -= $value['quantity'];
                    $value['quantity'] = 0;
                    $ret[] = $value;
                }
            }
        }
        return $ret;
    }


}
