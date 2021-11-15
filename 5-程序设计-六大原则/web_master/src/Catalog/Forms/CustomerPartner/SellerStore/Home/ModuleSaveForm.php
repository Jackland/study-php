<?php

namespace App\Catalog\Forms\CustomerPartner\SellerStore\Home;

use App\Enums\Seller\SellerStoreHome\ModuleType;
use App\Models\Seller\SellerStore\HomeModuleJson\SellerBasedModuleInterface;
use Exception;
use Framework\Model\RequestForm\RequestForm;
use Illuminate\Validation\Rule;

class ModuleSaveForm extends RequestForm
{
    public $type;
    public $data;

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        return [
            'type' => ['required', Rule::in(ModuleType::getValues())],
            'data' => 'required',
        ];
    }

    public function getViewData()
    {
        $module = ModuleType::getModuleModelByValue($this->type);
        if ($module instanceof SellerBasedModuleInterface) {
            // 基于 seller 的模块，因为是编辑模块，因此当前登录的用户就是 seller
            $module->setSellerId(customer()->getId());
        }
        $module->setFullValidate(false);
        $module->setValidateProductAvailable(true); // 单个模块保存时需要校验产品可用性
        $module->loadAttributes($this->data);
        $validator = $module->validateAttributes();
        if ($validator->fails()) {
            throw new Exception($validator->errors()->first());
        }
        $module->setProductUnavailableMark();
        $module->setSellerEdit();
        return $module->getViewData();
    }
}
