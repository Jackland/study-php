<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Catalog\Search\Warehouse\SellerInventoryAdjustSearch;
use App\Enums\Warehouse\SellerInventoryAdjustStatus;
use App\Repositories\Warehouse\SellerInventoryAdjustRepository;
use App\Services\Warehouse\SellerInventoryAdjustService;

class ControllerCustomerpartnerWarehouseInventoryLoss extends AuthSellerController
{
    public function index()
    {
        $data = $this->framework();
        $data['status'] = SellerInventoryAdjustStatus::getViewItems();
        return $this->render('customerpartner/warehouse/inventory_loss_list', $data);
    }

    public function getList()
    {
        $search = new SellerInventoryAdjustSearch($this->customer->getId());
        $dataProvider = $search->search($this->request->query->all());
        $data['total'] = $dataProvider->getTotalCount(); // 总计
        $data['rows'] = $dataProvider->getList()->map(function ($item) {
            $item['qty'] = $item->adjustDetail->sum('qty');
            $item['status_show'] = SellerInventoryAdjustStatus::getDescription($item->status);
            return $item;
        });
        return $this->response->json($data);
    }

    public function view()
    {
        $id = $this->request->get('inventory_id');
        if (empty($id)) {
            return $this->redirect('error/not_found');
        }
        $currency = $this->session->get('currency');
        $data = $this->framework();
        $data['page_type'] = $this->request->get('page_type', 'view');
        $data['adjust'] = app(SellerInventoryAdjustRepository::class)->getInventoryAdjustById($id, $this->customer->getId());
        $total = 0;
        if ($data['adjust']['version'] == 1) { // 历史版本处理
            if ($data['adjust']['status'] >= SellerInventoryAdjustStatus::TO_RECHARGE) {
                foreach ($data['adjust']->adjustDetail as $item) {
                    $total += $item->damages * $item->qty;
                }
                $data['total'] = $this->currency->formatCurrencyPrice($total, $currency);
            }
        } else {
            foreach ($data['adjust']->adjustDetail as $item) {
                $amount = 0;
                if (in_array($data['adjust']['status'], [SellerInventoryAdjustStatus::AUDITED, SellerInventoryAdjustStatus::TO_RECHARGE, SellerInventoryAdjustStatus::RECHARGED])) {
                    $amount = $item->damages;
                } else {
                    $item->compensate_amount >= $item->platform_declaration_amount ? $amount = $item->compensate_amount : $amount = $item->platform_declaration_amount;
                }
                $total +=  $amount * $item->qty;
            }
            $data['total'] = $this->currency->formatCurrencyPrice($total, $currency);
        }

        $data['country'] = $currency;
        $data['currency'] = $this->currency->getSymbolLeft($currency) . $this->currency->getSymbolRight($currency);
        $data['adjust']['status_show'] = SellerInventoryAdjustStatus::getDescription($data['adjust']['status']);
        return $this->render('customerpartner/warehouse/inventory_loss_view', $data);
    }

    public function doEdit()
    {
        return $this->jsonFailed(); // 33395 - Seller盘亏规则修改

        $data = $this->request->post();
        $customerId = $this->customer->getId();
        $rules = [
            'inventory_id' => [
                'required',
            ],
            'details' => [
                'required',
                'array',
            ],
            'files' => 'array|min:1|max:5',
            'files.*' => 'file|max:20480|extension:jpg,jpeg,png,pdf,PDF,xls,xlsx,doc,docx',
        ];
        $validation = $this->request->validate($rules);
        if ($validation->fails()) {
            return $this->jsonFailed($validation->errors()->first());
        }
        $files = $this->request->file('files');
        $res = app(SellerInventoryAdjustService::class)->updateInventoryAdjust($customerId, $data, $files);
        if ($res !== false) {
            return $this->jsonSuccess([], __('提交成功', [], 'controller/inventory'));
        }
        return $this->jsonFailed();
    }

    private function framework()
    {
        $this->setDocumentInfo(__('盘亏管理', [], 'catalog/document'));
        $breadcrumbs = $this->getBreadcrumbs([
            [
                'text' => __('库存管理', [], 'catalog/document'),
                'href' =>'javascript:void(0)'
            ],
            'current',
        ]);
        return [
            'breadcrumbs' => $breadcrumbs,
            'separate_column_left' => $this->load->controller('account/customerpartner/column_left'),
            'footer' => $this->load->controller('account/customerpartner/footer'),
            'header' => $this->load->controller('account/customerpartner/header'),
        ];
    }
}
