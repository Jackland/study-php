<?php

namespace App\Catalog\Forms\CustomerPartner\SellerStore\Traits;

use App\Catalog\Forms\CustomerPartner\SellerStore\Enums\SaveType;
use App\Catalog\Forms\CustomerPartner\SellerStore\Enums\TabType;
use App\Repositories\Seller\SellerStoreAuditRepository;
use Illuminate\Validation\Rule;

trait PreviewPublishFormTrait
{
    public $tab; // 当前所在 tab
    public $type; // 提交类型
    public $overwrite_audit = 0; // 是否覆盖当前待审核的，在提示有待审核后，传递该值做确认发布
    public $confirm_publish = 0; // 确认发布
    public $preview_key; // 当在预览页面点击publish时需要传递该值

    /**
     * @param TabType|string $tabTypeClass
     * @param SaveType|string $saveTypeClass
     * @return array
     */
    protected function getPreviewPublishRules($tabTypeClass, $saveTypeClass)
    {
        return [
            'tab' => ['required', Rule::in($tabTypeClass::getValues())],
            'type' => ['required', Rule::in($saveTypeClass::getValues())],
            'confirm_publish' => 'boolean',
            'overwrite_audit' => 'boolean',
            'preview_key' => Rule::requiredIf(function () {
                return $this->tab === TabType::PREVIEW;
            }),
        ];
    }

    /**
     * @param int $sellerId
     * @param $auditType
     * @return array|bool
     */
    protected function checkPreviewAndPublish($sellerId, $auditType)
    {
        // 校验预览数据是否变化
        if ($this->tab === TabType::PREVIEW && $this->type === SaveType::PUBLISH) {
            if (app(SellerStoreAuditRepository::class)->isPreviewDataChanged($sellerId, $auditType, $this->preview_key)) {
                return $this->result(['is_preview_change' => 1]);
            }
        }

        // 检查是否有待审核的
        if ($this->type === SaveType::PUBLISH) {
            if (!$this->overwrite_audit) {
                if (app(SellerStoreAuditRepository::class)->isSellerHasAudit($sellerId, $auditType)) {
                    return $this->result(['has_wait_audit' => 1]);
                }
            }
        }

        // 是否需要提示确认发布
        if ($this->type === SaveType::PUBLISH) {
            if (!$this->overwrite_audit && !$this->confirm_publish) {
                return $this->result(['need_confirm' => 1]);
            }
        }

        return true;
    }

    /**
     * @return array
     */
    protected function resultKeys()
    {
        return [
            'is_preview_change' => 0, // 预览的值是否发生变化
            'has_wait_audit' => 0, // 是否存在待审核
            'need_confirm' => 0, // 是否需要确认发布
        ];
    }

    /**
     * @return array
     */
    abstract protected function extraResultKeys(): array;

    /**
     * @param array $params
     * @return array
     */
    protected function result($params = []): array
    {
        $data = array_merge($this->resultKeys(), $this->extraResultKeys());
        foreach ($params as $key => $value) {
            if (!isset($data[$key])) {
                continue;
            }
            $data[$key] = $value;
        }

        return $data;
    }
}
