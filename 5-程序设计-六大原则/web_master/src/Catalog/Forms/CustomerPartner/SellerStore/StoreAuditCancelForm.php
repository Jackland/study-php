<?php

namespace App\Catalog\Forms\CustomerPartner\SellerStore;

use App\Enums\Seller\SellerStoreAuditStatus;
use App\Enums\Seller\SellerStoreAuditType;
use App\Models\Seller\SellerStoreAudit;
use App\Services\Seller\SellerStoreAuditService;
use Framework\Model\RequestForm\RequestForm;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StoreAuditCancelForm extends RequestForm
{
    private $sellerId;

    public $id;
    public $confirm_cancel = 0; // 确认取消
    public $save_draft = -1; // 是否保存草稿, 0 不保存，1 保存，-1 检查草稿是否存在

    public function __construct()
    {
        parent::__construct();

        $this->sellerId = customer()->getId();
    }

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        return [
            'id' => 'required',
            'confirm_cancel' => 'bool',
            'save_draft' => Rule::in([0, 1, -1]),
        ];
    }

    public function cancel()
    {
        $audit = SellerStoreAudit::query()->find($this->id);
        // 审核记录不存在或已删除或非待审核，直接返回取消成功
        if (!$audit || $audit->is_deleted || $audit->status != SellerStoreAuditStatus::AUDIT_WAIT) {
            return $this->result(['success' => 1]);
        }
        // 是否确认取消
        if (!$this->confirm_cancel) {
            return $this->result(['need_confirm' => 1]);
        }
        // 是否存在草稿
        if ($audit->type == SellerStoreAuditType::HOME) {
            if ($this->save_draft == -1) {
                // 仅首页编辑有草稿的功能
                $sellerStoreDraft = SellerStoreAudit::query()
                    ->where('seller_id', $this->sellerId)
                    ->where('type', SellerStoreAuditType::HOME)
                    ->where('is_deleted', 0)
                    ->where('status', SellerStoreAuditStatus::DRAFT) // 草稿
                    ->orderByDesc('updated_at')
                    ->exists();
                if ($sellerStoreDraft) {
                    return $this->result(['draft_exist' => 1]);
                }
                return $this->result(['draft_empty' => 1]);
            }
        } else {
            // 其他无草稿的逻辑
            $this->save_draft = 0;
        }

        $sellerStoreAuditService = app(SellerStoreAuditService::class);
        // 不保存草稿
        if ($this->save_draft == 0) {
            // 删除审核数据
            $sellerStoreAuditService->deleteWaitAudit($this->sellerId, $audit->type);
            return $this->result(['success' => 1]);
        }
        // 保存草稿
        if ($this->save_draft === 1) {
            if ($audit->type != SellerStoreAuditType::HOME) {
                throw new InvalidArgumentException('仅店铺主页允许保存草稿');
            }
            // 删除审核数据
            $sellerStoreAuditService->deleteWaitAudit($this->sellerId, $audit->type);
            // 新建草稿
            $sellerStoreAuditService->updateOrCreateDraft($this->sellerId, $audit->type, $audit->audit_data);
            return $this->result(['success' => 1]);
        }
        throw new InvalidArgumentException('save_draft 错误');
    }

    private function result(array $params)
    {
        $data = [
            'need_confirm' => 0, // 是否需要确认
            'draft_exist' => 0, // 草稿想中是否有内容
            'draft_empty' => 0, // 草稿箱是否为空
            'success' => 0, // 是否取消成功
        ];

        foreach ($params as $key => $value) {
            if (!isset($data[$key])) {
                continue;
            }
            $data[$key] = $value;
        }

        return $data;
    }
}
