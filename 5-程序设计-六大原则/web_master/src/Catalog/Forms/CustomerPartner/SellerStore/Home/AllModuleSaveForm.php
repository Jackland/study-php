<?php

namespace App\Catalog\Forms\CustomerPartner\SellerStore\Home;

use App\Catalog\Forms\CustomerPartner\SellerStore\Home\Enums\AllModuleSaveTab;
use App\Catalog\Forms\CustomerPartner\SellerStore\Home\Enums\AllModuleSaveType;
use App\Catalog\Forms\CustomerPartner\SellerStore\Traits\PreviewPublishFormTrait;
use App\Enums\Seller\SellerStoreAuditType;
use App\Enums\Seller\SellerStoreHome\ModuleType;
use App\Models\Seller\SellerStore\HomeModuleJson\SellerBasedModuleInterface;
use App\Models\Seller\SellerStoreAudit;
use App\Services\Seller\SellerStoreAuditService;
use Exception;
use Framework\Helper\Json;
use Framework\Model\RequestForm\RequestForm;

class AllModuleSaveForm extends RequestForm
{
    use PreviewPublishFormTrait;

    private $sellerId;

    public $modules;

    public function __construct()
    {
        $this->sellerId = customer()->getId();

        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        return array_merge(
            $this->getPreviewPublishRules(AllModuleSaveTab::class, AllModuleSaveType::class),
            [
                'modules' => 'required',
                'modules.*.type' => 'required',
                'modules.*.data' => 'array',
                'overwrite_audit' => 'boolean',
            ]
        );
    }

    /**
     * @inheritDoc
     */
    protected function getRuleMessages(): array
    {
        return [
            'modules.required' => __('这个当前页面无模块，请先添加模块', [], 'controller/seller_store')
        ];
    }

    public function save()
    {
        // 检查所有模块的完整性
        $dbData = [];
        $needFullValidate = $this->type === AllModuleSaveType::PUBLISH; // 仅发布审核时校验数据
        $errorModules = [];
        foreach ($this->modules as $index => $moduleInfo) {
            if ($needFullValidate && !$moduleInfo['data']) {
                // 模块未做任何操作直接保存时，data 为空对象
                $errorModules[] = $index;
                continue;
            }
            $module = ModuleType::getModuleModelByValue($moduleInfo['type']);
            if ($module instanceof SellerBasedModuleInterface) {
                $module->setSellerId($this->sellerId);
            }
            $module->setFullValidate($needFullValidate);
            $module->setValidateProductAvailable(false); // 全模块保存时不校验产品可用性
            $module->loadAttributes($moduleInfo['data']);
            $validator = $module->validateAttributes();
            if ($validator->fails()) {
                // 校验不通过
                throw new Exception($validator->errors()->first());
            }
            $dbData[] = [
                'type' => $moduleInfo['type'],
                'data' => $moduleInfo['data'] ? $module->getDBData() : [], // 空模块数据时不获取默认的 DBData
            ];
        }
        if (count($errorModules) > 0) {
            // 模块信息不完整错误
            return $this->result(['error_modules' => $errorModules]);
        }

        // 检查发布预览的流程
        $result = $this->checkPreviewAndPublish($this->sellerId, SellerStoreAuditType::HOME);
        if ($result !== true) {
            return $result;
        }

        // 保存数据
        /** @var SellerStoreAudit $audit */
        $audit = dbTransaction(function () use ($dbData) {
            $json = Json::encode($dbData);
            $sellerStoreAuditService = app(SellerStoreAuditService::class);

            if ($this->type === AllModuleSaveType::DRAFT) {
                // 保存草稿
                // 已有则覆盖，没有新建
                return $sellerStoreAuditService->updateOrCreateDraft($this->sellerId, SellerStoreAuditType::HOME, $json);
            }
            if ($this->type === AllModuleSaveType::PUBLISH) {
                // 发布审核
                // 删除目前所有待审核的
                $sellerStoreAuditService->deleteWaitAudit($this->sellerId, SellerStoreAuditType::HOME);
                // 新增一条待审核的
                $audit = $sellerStoreAuditService->createNewAudit($this->sellerId, SellerStoreAuditType::HOME, $json);
                if ($this->tab === AllModuleSaveTab::UNPUBLISHED) {
                    // 如果在草稿页面，则删除草稿
                    $sellerStoreAuditService->deleteDraft($this->sellerId, SellerStoreAuditType::HOME);
                }
                return $audit;
            }
            if ($this->type === AllModuleSaveType::PREVIEW) {
                // 预览
                return $sellerStoreAuditService->updateOrCreatePreview($this->sellerId, SellerStoreAuditType::HOME, $json);
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
            'error_modules' => [], // 数据不完整的模块
            'preview_key' => '', // 预览值，在保存成功时返回
        ];
    }
}
