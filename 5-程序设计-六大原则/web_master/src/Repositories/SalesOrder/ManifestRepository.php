<?php

namespace App\Repositories\SalesOrder;

use App\Components\Storage\StorageCloud;
use App\Enums\Common\YesNoEnum;
use App\Enums\SalesOrder\CustomerSalesOrderMode;
use App\Enums\SalesOrder\HomePickImportMode;
use App\Models\Product\Product;
use App\Models\Product\Tag;
use App\Models\SalesOrder\CustomerSalesOrder;
use App\Models\SalesOrder\CustomerSalesOrderLine;
use Exception;
use Psr\SimpleCache\InvalidArgumentException;

class ManifestRepository
{
    /**
     * @param int $customerId
     * @param array $condition
     * @param bool $pagination
     * @return array
     * @throws Exception
     * @see ModelAccountCustomerOrderImport::getManifestManagementList()
     */
    public function getManifestManagementList(int $customerId, array $condition = [], bool $pagination = false): array
    {
        ini_set('memory_limit', '512M');

        // 缓存数据
        $list = $this->getManifestManagementData(...func_get_args());
        $total = count($list);
        // 分页
        if ($pagination) {
            $page = $condition['page'] ?? 1;
            $pageLimit = $condition['page_limit'] ?? 10;
            $start = ($page - 1) * $pageLimit;
            $list = array_slice($list, $start, $pageLimit);
        }

        // 获取sku
        $listOrders = collect(array_reduce(array_column($list, 'list'), 'array_merge', []));
        $itemCodes = array_reduce($listOrders->pluck('lines.*.item_code')->toArray(), 'array_merge', []);
        $skuProductMap = $this->getManifestLineProductsBySkusAndCustomerId($itemCodes, $customerId);

        foreach ($list as &$v) {
            foreach ($v['list'] as $k => $o) {
                $o['line_list'] = $this->formatOrderLines($o['lines'], $skuProductMap);
                $o['package_qty'] = array_sum(array_column($o['line_list'], 'package_qty'));
                $v['list'][$k] = $o;
            }
            $v['order_amount'] = count($v['order_id']);
            $v['package_qty'] = array_sum(array_column($v['list'], 'package_qty'));
            $v['order_id_all'] = implode('_', $v['order_id']);
        }
        unset($v);
        return [$list, $total];
    }

    /**
     * @param int $customerId
     * @param array $condition
     * @param bool $pagination
     * @return array
     * @throws InvalidArgumentException
     */
    private function getManifestManagementData(int $customerId, array $condition, bool $pagination): array
    {
        // 读取缓存数据
        $cacheKey = $customerId . $pagination;
        if (isset($condition['order_id'])) {
            $cacheKey .= trim($condition['order_id']);
        }
        if (isset($condition['is_synchroed'])) {
            $cacheKey .= $condition['is_synchroed'];
        }
        $cacheKey = md5($cacheKey);
        if (cache()->has($cacheKey)) {
            return json_decode(cache()->get($cacheKey), true);
        }

        $orders = CustomerSalesOrder::query()->alias('o')
            ->with([
                'file' => function ($query) {
                    $query->select(['file_name', 'deal_file_path', 'order_id']);
                },
                'lines' => function ($query) use ($condition) {
                    $query->select(['temp_id', 'is_synchroed', 'header_id', 'item_code', 'qty'])
                        ->when(isset($condition['is_synchroed']), function ($q) use ($condition) {
                        if ($condition['is_synchroed']) {
                            $q->whereNotNull('is_synchroed');
                        } else {
                            $q->whereNull('is_synchroed');
                        }
                    });
            }, 'lines.wayfairTemp'])
            ->where('o.order_mode', CustomerSalesOrderMode::PICK_UP)
            ->where('o.buyer_id', $customerId)
            ->where('o.import_mode', HomePickImportMode::IMPORT_MODE_WAYFAIR)
            ->when(isset($condition['order_id']) && !empty(trim($condition['order_id'])), function ($q) use ($condition) {
                $q->where('o.order_id', 'like', '%' . trim($condition['order_id']) . '%');
            })
            ->when(isset($condition['is_synchroed']), function ($q) use ($condition) {
                $q->leftJoinRelations('lines as l');
                if ($condition['is_synchroed']) {
                    $q->whereNotNull('l.is_synchroed');
                } else {
                    $q->whereNull('l.is_synchroed');
                }
            })
            ->select(['o.id', 'o.order_id', 'order_status'])
            ->groupBy('o.order_id')
            ->get();

        $do = [];
        $undo = [];
        foreach ($orders as $order) {
            /** @var CustomerSalesOrder $order */
            /** @var CustomerSalesOrderLine $firstLine */
            $firstLine = $order->lines->first();
            $wayfairTemp = $firstLine->wayfairTemp;
            $order['order_status_name'] = $order->order_status_show;
            $order['carrier_name'] = $wayfairTemp->carrier_name;
            $order['carrier_name_compare'] = strtoupper($wayfairTemp->carrier_name);
            $order['deal_file_path'] = $order->file->deal_file_path ?? '';
            $order['file_name'] = $order->file->file_name ?? '';
            $order['ready_for_pickup_date'] = $wayfairTemp->ready_for_pickup_date;
            $order['warehouse_name'] = $wayfairTemp->warehouse_name;

            if (!empty($order->file) && !empty($order->file->deal_file_path)) {
                $doKey = $order['ready_for_pickup_date'] . '|' . $order['carrier_name_compare'] . '|' . $order['warehouse_name'] . '|' . $order['deal_file_path'];
                $do[$doKey]['order_date'] = $order['ready_for_pickup_date'];
                $do[$doKey]['warehouse_name'] = $order['warehouse_name'];
                $do[$doKey]['carrier_name'] = $order['carrier_name'];
                $do[$doKey]['order_id'][] = $order->id;
                $do[$doKey]['deal_file_path'] = $order['deal_file_path'];
                $do[$doKey]['file_name'] = $order['file_name'];
                $do[$doKey]['is_uploaded'] = 1;
                if ($order['is_synchroed'] || (isset($do[$order['ready_for_pickup_date'] . '|' . $order['carrier_name_compare'] . '|' . $order['deal_file_path']]['is_synchroed']) && $do[$order['ready_for_pickup_date'] . '|' . $order['carrier_name_compare'] . '|' . $order['deal_file_path']]['is_synchroed'] == 1)) {
                    $do[$doKey]['is_synchroed'] = 1;
                } else {
                    $do[$doKey]['is_synchroed'] = 0;
                }
                $do[$doKey]['list'][] = $order->toArray();
            } else {
                $undoKey = $order['ready_for_pickup_date'] . '|' . $order['carrier_name_compare'] . '|' . $order['warehouse_name'];
                $undo[$undoKey]['order_date'] = $order['ready_for_pickup_date'];
                $undo[$undoKey]['warehouse_name'] = $order['warehouse_name'];
                $undo[$undoKey]['carrier_name'] = $order['carrier_name'];
                $undo[$undoKey]['order_id'][] = $order['id'];
                $undo[$undoKey]['is_uploaded'] = 0;
                $undo[$undoKey]['is_synchroed'] = 0;
                $undo[$undoKey]['list'][] = $order->toArray();;
            }
        }

        krsort($do);
        krsort($undo);
        $do = array_values($do);
        $undo = array_values($undo);
        $list = json_encode(array_merge($undo, $do));

        unset($orders);
        unset($do);
        unset($undo);

        cache()->set($cacheKey, $list, 180);

        return json_decode($list, true);
    }

    /**
     * @param array $lines
     * @param array $skuProductMap
     * @return array
     */
    private function formatOrderLines(array $lines, array $skuProductMap): array
    {
        foreach ($lines as $k => &$line) {
            $line['t_item_code'] = $line['wayfair_temp']['item_#'];
            /** @var Product $product */
            $product = $skuProductMap[$line['item_code']] ?? new Product();
            $line['tag_array'] = $product->tags;
            $line['image_show'] = StorageCloud::image()->getUrl($product->image, ['w' => 40, 'h' => 40]);
            $line['product_link'] = url(['product/product', 'product_id' => $product->product_id]);

            $singleQty = $product->combos->sum('qty');
            $line['package_qty'] = $singleQty == 0 ? $line['qty'] : ($line['qty'] * $singleQty);


            if ($k == 0) {
                $line['is_show'] = 1;
                $line['row_span'] = count($lines);
            } else {
                $line['row_span'] = 0;
                $line['is_show'] = 0;
            }
        }
        unset($line);

        return $lines;
    }

    /**
     * 获取sku的产品信息
     * @param array $skus
     * @param int $customerId
     * @return array
     * @throws Exception
     */
    private function getManifestLineProductsBySkusAndCustomerId(array $skus, int $customerId): array
    {
        $allProducts = Product::query()
            ->with(['tags', 'combos'])
            ->whereIn('sku', $skus)
            ->orderByDesc('product_id')
            ->get()
            ->each(function ($product) {
                /** @var Product $product */
                $tags = [];
                if ($product->tags->isNotEmpty()) {
                    foreach ($product->tags as $tag) {
                        /** @var Tag $tag */
                        $tags[] = $tag->tag_widget;
                    }
                }
                unset($product->tags);
                $product->tags = $tags;
            });
        $skuProductsMap = $allProducts->groupBy('sku');

        $returnSkuProduct = [];
        foreach ($skuProductsMap as $sku => $products) {
            $validProducts = $products->where('status', YesNoEnum::YES)->where('buyer_flag', YesNoEnum::YES);
            if ($validProducts->count() == 1) {
                $returnSkuProduct[$sku] = $validProducts->first();
                continue;
            }

            foreach ($validProducts as $product) {
                // 循环中依然是sql查询，可优化
                $result = $this->getDelicacyManagementInfoByNoView($product->product_id, $customerId);
                if ($result && $result['product_display']) {
                    $returnSkuProduct[$sku] = $product;
                    continue 2;
                }
            }

            $returnSkuProduct[$sku] = $products->first();
        }

        return $returnSkuProduct;
    }

    /**
     * 缓存数据
     * @var array
     */
    private $_productDelicacyManagementInfo = [];

    /**
     * @param int $productId
     * @param int $customerId
     * @return mixed
     * @throws Exception
     */
    private function getDelicacyManagementInfoByNoView(int $productId, int $customerId)
    {
        if (isset($this->_productDelicacyManagementInfo[$productId])) {
            return $this->_productDelicacyManagementInfo[$productId];
        }

        /** @var \ModelCatalogProduct $modelCatalogProduct */
        $modelCatalogProduct = load()->model('catalog/product');
        $result = $modelCatalogProduct->getDelicacyManagementInfoByNoView($productId, $customerId);

        $this->_productDelicacyManagementInfo[$productId] = $result;

        return $result;
    }
}
