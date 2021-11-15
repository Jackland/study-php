<?php

namespace App\Models\Seller\SellerStore\HomeModuleJson;

use App\Enums\Seller\SellerStoreHome\ModuleStoreIntroductionIcon;
use Illuminate\Validation\Rule;
use ModelToolImage;

class ModuleStoreIntroduction extends BaseModule
{
    public $pics = [];
    public $desc = '';
    public $tags = [];

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        $dynamicRequired = $this->isFullValidate() ? 'required' : 'nullable';
        return [
            'pics' => 'array',
            'pics.*.pic' => 'required|string',
            'desc' => 'string|max:2000',
            'tags' => [$dynamicRequired, 'array', function ($attribute, $value, $fail) {
                if (is_array($value) && $value && count($value) != 4) {
                    $fail($attribute . ' size must 4.');
                }
            }],
            'tags.*.icon' => ['required', Rule::in(ModuleStoreIntroductionIcon::getValues())],
            'tags.*.title' => 'required|string|max:50',
            'tags.*.content' => 'string|max:200',
        ];
    }

    /**
     * @inheritDoc
     */
    public function getDBData(): array
    {
        $tags = array_map(function ($item) {
            unset($item['icon_show']);
            return $item;
        }, $this->tags);
        $pics = array_map(function ($item) {
            unset($item['pic_show']);
            return $item;
        }, $this->pics);
        return [
            'pics' => $pics,
            'desc' => $this->desc,
            'tags' => $tags,
        ];
    }

    private $_viewData;

    /**
     * @inheritDoc
     */
    public function getViewData(): array
    {
        if ($this->_viewData) {
            return $this->_viewData;
        }

        $tags = array_map(function ($item) {
            $item['icon_show'] = ModuleStoreIntroductionIcon::getDescription($item['icon']);
            return $item;
        }, $this->tags);

        /** @var ModelToolImage $imageModel */
        $imageModel = load()->model('tool/image');
        $pics = array_map(function ($item) use ($imageModel) {
            $item['pic_show'] = $imageModel->resize($item['pic']);
            return $item;
        }, $this->pics);

        $this->_viewData = [
            'pics' => $pics,
            'desc' => $this->desc,
            'tags' => $tags,
        ];
        return $this->_viewData;
    }

    /**
     * @inheritDoc
     */
    public function canShowForBuyer(array $dbData): bool
    {
        if (!parent::canShowForBuyer($dbData)) {
            return false;
        }

        if (count($this->tags) > 0) {
            return true;
        }
        if (count($this->pics) > 0) {
            return true;
        }
        if ($this->desc) {
            return true;
        }

        return false;
    }
}
