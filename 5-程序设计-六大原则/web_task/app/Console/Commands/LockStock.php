<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class LockStock
 * @package App\Console\Commands
 */
class LockStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statistic:lock-stock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '在库库存减去锁定库存小于上架库存';

    protected $from = [];
    protected $to = [];
    protected $cc = [];
    protected $allServerEmail = [];
    protected $allBxEmail = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->from = Setting::getConfig('statistic_lock_stock_email_from', [config('mail.from.address')]);
        $this->to = Setting::getConfig('statistic_lock_stock_email_to', []);
        $this->cc = Setting::getConfig('statistic_lock_stock_email_cc', []);
        $this->allServerEmail = Setting::getConfig('all_server_email', []);
        $this->allBxEmail = Setting::getConfig('all_bx_email', []);
    }

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {

        $header = [
            'Seller', 'Seller Email', 'Item Code', 'Is Combo', '在库库存', '上架库存', '锁定库存(combo为0)','超库存(在库-上架-锁定)',
//            'Order ID', 'Buyer', '订单数量', 'Date Time'
        ];

        $data = $this->getData();
        $content = [];
        foreach ($data as $list) {

            $content[] = [
                ($list['seller_first_name'] ?? '') . ($list['seller_last_name'] ?? ''),
                $list['seller_email'] ?? '',
                $list['sku'],
                $list['is_combo'] ? 'Yes' : 'No',
                $list['stock_qty'],
                $list['quantity'],
                $list['lock_qty'],
                $list['stock_qty']-$list['quantity']-$list['lock_qty']
//                $list['order_id'] ?? '',
//                ($list['buyer_first_name'] ?? '') . ($list['buyer_last_name'] ?? '') . ($list['buyer_user_number'] ?? ''),
//                $list['op_qty'] ?? '',
//                $list['date_added'] ?? ''
            ];
        }

        $email_content = [
            'subject' => '在库库存减去锁定库存小于上架库存',
            'title' => '在库库存减去锁定库存小于上架库存',
            'header' => $header,
            'content' => $content,
        ];
        \Mail::to($this->to)
            ->cc($this->cc)
            ->send(new \App\Mail\StockQuantity($email_content, $this->from));
        $send_data = ['to' => $this->to, 'cc' => $this->cc];
        Log::info(json_encode(array_merge($email_content, $send_data)));
        echo date('Y-m-d H:i:s') . ' ' . $this->signature . ' 发送成功' . PHP_EOL;
    }

    /**
     * @return array
     */
    public function getData()
    {
        $normal_products = $this->getNormalProducts();

        $lock_stocks = $this->getLockStock();

        $combo_products = $this->getComboProducts();
        $combos = [];
        foreach ($combo_products as $combo_product) {
            $combos[$combo_product->product_id][] = $combo_product;
        }

        $results = [];
        foreach ($combos as $combo) {
            $son_stock_qtys = [0];
            $on_self_quantity = null;
            $product_id = null;
            $sku = null;
            foreach ($combo as $item) {
                is_null($on_self_quantity) && $on_self_quantity = $item->quantity;
                is_null($product_id) && $product_id = $item->product_id;
                is_null($sku) && $product_id = $item->sku;
                $lock_qty = isset($lock_stocks->{$item->set_product_id}) ? $lock_stocks->{$item->set_product_id}->total_lock_qty : 0;
                if (empty($item->qty)) {
                    continue;
                }
                $son_stock_qtys[] = ceil($item->total_batch_qty - $lock_qty) / $item->qty;
            }
            is_null($on_self_quantity) && $on_self_quantity = 0;
            $stock_qty = min($son_stock_qtys);
            if (is_null($product_id) || $stock_qty <= $on_self_quantity) {
                continue;
            }

            $results[$product_id] = [
                'product_id' => $product_id,
                'quantity' => $on_self_quantity,
                'stock_qty' => $stock_qty,
                'lock_qty' => 0,
                'is_combo' => 1,
            ];
        }

        foreach ($normal_products as $normal_product) {
            $results[$normal_product->product_id] = [
                'product_id' => $normal_product->product_id,
                'quantity' => $normal_product->quantity,
                'sku' => $normal_product->sku,
                'stock_qty' => $normal_product->total_batch_qty,
                'lock_qty' => $normal_product->total_lock_qty,
                'is_combo' => 0
            ];
        }

        $product_ids = array_keys($results);

//        $order_infos = $this->getOrderInfo($product_ids);
        $seller_infos = $this->getSellerInfoByProductIDs($product_ids);

        $_result = [];
        foreach ($results as $result) {
//            if (isset($order_infos[$result['product_id']])) {
//                foreach ($order_infos[$result['product_id']] as $item) {
//                    $_result[] = array_merge(
//                        $result,
//                        json_decode(json_encode($item), true),
//                        json_decode(json_encode($seller_infos[$result['product_id']] ?? []), true)
//                    );
//                }
//            } else {
//                $_result[] = array_merge($result, json_decode(json_encode($seller_infos[$result['product_id']] ?? []), true));
//            }
            $_result[] = array_merge($result, json_decode(json_encode($seller_infos[$result['product_id']] ?? []), true));
        }
        return $_result;
    }

    /**
     * @param $product_ids
     * @return \Illuminate\Support\Collection
     */
    public function getSellerInfoByProductIDs($product_ids)
    {
        return \DB::connection('mysql_proxy')
            ->table('oc_customerpartner_to_product as ctp')
            ->join('oc_customer as c', 'c.customer_id', '=', 'ctp.customer_id')
            ->select([
                'c.customer_id',
                'c.firstname as seller_first_name', 'c.lastname as seller_last_name', 'c.email as seller_email',
                'ctp.product_id',
            ])
            ->whereIn('ctp.product_id', $product_ids)
            ->get()
            ->keyBy('product_id');
    }

    /**
     * 非 combo 且 在库库存 - 锁定库存 < 上架库存
     *
     * @return array
     */
    public function getNormalProducts()
    {
        $sub = \DB::connection('mysql_proxy')
            ->table('oc_product_lock')
            ->select('product_id')
            ->selectRaw('sum(qty) as total_lock_qty')
            ->groupBy('product_id');
        return \DB::connection('mysql_proxy')
            ->table(\DB::raw("({$sub->toSql()}) as pl"))
            ->rightJoin('oc_product as p','p.product_id','=','pl.product_id')
            ->join('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->join('oc_customer as c', 'c.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('tb_sys_batch as sb', 'sb.product_id', '=', 'p.product_id')
            ->select([
                'p.product_id', 'p.quantity', 'p.sku',
            ])
            ->selectRaw('sum(ifnull(sb.onhand_qty,0)) as total_batch_qty,ifnull(pl.total_lock_qty,0) as total_lock_qty')
            ->mergeBindings($sub)
            ->where([
                ['p.is_deleted', '=', 0],
                ['c.status', '=', 1],
                ['c.customer_group_id', '<>', 23],
                ['p.combo_flag', '=', 0]
            ])
            ->when(count($this->allServerEmail) > 0, function (Builder $q) {
                $q->whereNotIn('c.email', $this->allServerEmail);
            })
            ->when(count($this->allBxEmail) > 0, function (Builder $q) {
                $q->whereNotIn('c.email', $this->allBxEmail);
            })
            ->groupBy('p.product_id', 'c.customer_id')
            ->havingRaw('total_batch_qty < quantity + total_lock_qty')
            ->get()
            ->keyBy('product_id')
            ->toArray();
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function getComboProducts()
    {
        return \DB::connection('mysql_proxy')
            ->table('oc_product as p')
            ->join('oc_customerpartner_to_product as ctp', 'ctp.product_id', '=', 'p.product_id')
            ->join('oc_customer as c', 'c.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('tb_sys_product_set_info as psi', 'psi.product_id', '=', 'p.product_id')
            ->leftJoin('tb_sys_batch as sb', 'sb.product_id', '=', 'psi.set_product_id')
            ->select([
                'p.product_id', 'p.quantity', 'psi.qty', 'psi.set_product_id', 'p.sku',
            ])
            ->selectRaw('sum(ifnull(sb.onhand_qty,0)) as total_batch_qty')
            ->where([
                ['p.is_deleted', '=', 0],
                ['c.status', '=', 1],
                ['c.customer_group_id', '<>', 23],
                ['p.combo_flag', '=', 1]
            ])
            ->when(count($this->allServerEmail) > 0, function (Builder $q) {
                $q->whereNotIn('c.email', $this->allServerEmail);
            })
            ->when(count($this->allBxEmail) > 0, function (Builder $q) {
                $q->whereNotIn('c.email', $this->allBxEmail);
            })
            ->groupBy('p.product_id', 'psi.qty', 'psi.set_product_id')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function getLockStock()
    {
        return \DB::connection('mysql_proxy')
            ->table('oc_product_lock')
            ->select([
                'product_id',
            ])
            ->selectRaw('sum(qty) as total_lock_qty')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');
    }

    /**
     * @param $product_ids
     * @return array
     */
    public function getOrderInfo($product_ids)
    {
        $temp = [];
        $date = date('Y-m-d H:i:s', strtotime('-5 day'));
        \DB::connection('mysql_proxy')
            ->table('oc_customerpartner_to_product as ctp')
            ->join('oc_product as p', 'p.product_id', '=', 'ctp.product_id')
            ->join('oc_customer as seller', 'seller.customer_id', '=', 'ctp.customer_id')
            ->leftJoin('oc_order_product as op', 'op.product_id', '=', 'ctp.product_id')
            ->join('oc_order as o', 'o.order_id', '=', 'op.order_id')
            ->join('oc_customer as buyer', 'buyer.customer_id', '=', 'o.customer_id')
            ->select([
                'p.product_id', 'p.sku',
                'seller.firstname as seller_first_name', 'seller.lastname as seller_last_name', 'seller.email as seller_email',
                'buyer.firstname as buyer_first_name', 'buyer.lastname as buyer_last_name', 'buyer.user_number as buyer_user_number',
                'op.quantity as op_qty', 'o.date_added', 'o.order_id',
            ])
            ->whereIn('ctp.product_id', array_unique($product_ids))
            ->where([
                ['seller.status', '=', 1],
                ['o.order_status_id', '=', 5],
            ])
            ->whereRaw('o.date_added >"' . $date . '"')
            ->orderBy('o.date_added', 'DESC')
            ->get()
            ->map(function ($value) use (&$temp) {
                $temp[$value->product_id][] = $value;
            });
        return $temp;
    }
}
