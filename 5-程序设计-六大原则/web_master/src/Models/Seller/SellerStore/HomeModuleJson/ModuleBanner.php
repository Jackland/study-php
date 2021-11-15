<?php

namespace App\Models\Seller\SellerStore\HomeModuleJson;

use App\Helper\RouteHelper;
use Illuminate\Support\Str;
use ModelToolImage;

class ModuleBanner extends BaseModule
{
    public $banners = [];

    /**
     * @inheritDoc
     */
    public function getRules(): array
    {
        $bannerPicPrefix = ['wkseller/', 'wkmisc/'];
        $bannerLinkDomain = RouteHelper::getSiteAbsoluteUrl();
        $dynamicRequired = $this->isFullValidate() ? 'required' : 'nullable';

        return [
            'banners' => $dynamicRequired,
            'banners.*.pic' => ['required', function ($attribute, $value, $fail) use ($bannerPicPrefix) {
                if (!Str::startsWith($value, $bannerPicPrefix)) {
                    $fail($attribute . ' is not start with ' . implode(' or ', $bannerPicPrefix));
                }
            }],
            'banners.*.link' => ['required', function ($attribute, $value, $fail) use ($bannerLinkDomain) {
                if (!Str::startsWith($value, $bannerLinkDomain)) {
                    $fail(__('请输入站内链接！', [], 'controller/seller_store'));
                }
            }],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getDBData(): array
    {
        return [
            'banners' => $this->banners,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getViewData(): array
    {
        /** @var ModelToolImage $toolImage */
        $toolImage = load()->model('tool/image');

        $banners = array_map(function ($item) use ($toolImage) {
            $item['pic_show'] = $toolImage->resize($item['pic']);
            return $item;
        }, $this->banners);

        return [
            'banners' => $banners,
        ];
    }

    /**
     * @inheritDoc
     */
    public function canShowForBuyer(array $dbData): bool
    {
        if (!parent::canShowForBuyer($dbData)) {
            return false;
        }

        if (count($this->banners) > 0) {
            return true;
        }

        return false;
    }
}
