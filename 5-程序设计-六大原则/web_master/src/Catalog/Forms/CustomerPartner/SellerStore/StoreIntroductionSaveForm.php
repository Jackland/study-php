<?php

namespace App\Catalog\Forms\CustomerPartner\SellerStore;

use App\Catalog\Forms\CustomerPartner\SellerStore\Enums\SaveType;
use App\Catalog\Forms\CustomerPartner\SellerStore\Enums\TabType;
use App\Catalog\Forms\CustomerPartner\SellerStore\Traits\PreviewPublishFormTrait;
use App\Enums\Seller\SellerStoreAuditType;
use App\Models\Seller\SellerStore\SellerStoreIntroductionJson;
use App\Models\Seller\SellerStoreAudit;
use App\Services\Seller\SellerStoreAuditService;
use Exception;
use Framework\Helper\Json;
use Framework\Model\RequestForm\AutoLoadAndValidateTrait;

class StoreIntroductionSaveForm extends SellerStoreIntroductionJson
{
    use AutoLoadAndValidateTrait;
    use PreviewPublishFormTrait;

    private $sellerId;
    private $request;

    public function __construct()
    {
        parent::__construct();

        $this->sellerId = (int)customer()->getId();
        $this->request = request();

        $this->autoLoadAndValidate();
    }

    protected function getRules(): array
    {
        return array_merge(
            parent::getRules(),
            $this->getPreviewPublishRules(TabType::class, SaveType::class)
        );
    }

    public function save()
    {
        // 检查发布预览的流程
        $result = $this->checkPreviewAndPublish($this->sellerId, SellerStoreAuditType::INTRODUCTION);
        if ($result !== true) {
            return $result;
        }

        // 保存数据
        /** @var SellerStoreAudit $audit */
        $audit = dbTransaction(function () use (&$audit) {
            $json = Json::encode($this->getDBData());
            $sellerStoreAuditService = app(SellerStoreAuditService::class);

            if ($this->type === SaveType::PREVIEW) {
                // 预览
                return $sellerStoreAuditService->updateOrCreatePreview($this->sellerId, SellerStoreAuditType::INTRODUCTION, $json);
            }
            if ($this->type === SaveType::PUBLISH) {
                // 提交审核
                // 删除目前所有待审核的
                $sellerStoreAuditService->deleteWaitAudit($this->sellerId, SellerStoreAuditType::INTRODUCTION);
                // 新建待审核
                return $sellerStoreAuditService->createNewAudit($this->sellerId, SellerStoreAuditType::INTRODUCTION, $json);
            }
            throw new Exception('type error');
        });

        return $this->result(['preview_key' => $audit->preview_key]);
    }

    /**
     * @inheritDoc
     */
    protected function extraResultKeys(): array
    {
        return [
            'preview_key' => '', // 预览值，在保存成功时返回
        ];
    }
}
