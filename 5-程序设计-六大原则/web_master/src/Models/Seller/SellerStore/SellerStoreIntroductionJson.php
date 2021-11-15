<?php

namespace App\Models\Seller\SellerStore;

use App\Helper\ArrayHelper;
use App\Models\Product\Category;
use Framework\Model\BaseValidateModel;
use ModelToolImage;

class SellerStoreIntroductionJson extends BaseValidateModel
{
    public $pics = [];
    public $store_intro = '';
    public $categories = []; // id 数组
    public $email = '';
    public $phone = '';
    public $wechat_pics = [];

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        return [
            'pics' => [
                'required',
                'array',
                function ($attribute, $value, $fail) {
                    if (is_array($value) && count($value) > 5) {
                        $fail($attribute . ' length must be less than or equal to 5.');
                    }
                }
            ],
            'store_intro' => 'required|string|max:2000',
            'categories' => 'required|array',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'wechat_pics' => 'nullable|array',
        ];
    }

    /**
     * 数据库保存的数据
     * @return array
     */
    public function getDBData(): array
    {
        return [
            'pics' => $this->pics,
            'store_intro' => $this->store_intro,
            'categories' => $this->categories,
            'email' => $this->email,
            'phone' => $this->phone,
            'wechat_pics' => $this->wechat_pics,
        ];
    }

    /**
     * 视图显示用的数据
     * @return array
     */
    public function getViewData(): array
    {
        /** @var ModelToolImage $toolImage */
        $toolImage = load()->model('tool/image');

        $pics = array_map(function ($item) use ($toolImage) {
            return [
                'pic' => $item,
                'pic_show' => $toolImage->resize($item)
            ];
        }, $this->pics ?? []);

        $categories = Category::query()
            ->select('category_id')
            ->with(['description'])
            ->whereIn('category_id', $this->categories)
            ->get()
            ->map(function (Category $model) {
                return ['id' => $model->category_id, 'name' => htmlspecialchars_decode($model->description->name)];
            })
            ->toArray();
        $categories = ArrayHelper::sortByGivenIndex($categories, 'id', $this->categories);

        // wechat pics
        $wechatPics = array_map(function ($item) use ($toolImage) {
            return [
                'pic' => $item,
                'pic_show' => $toolImage->resize($item, 120, 120)
            ];
        }, $this->wechat_pics ?? []);

        return [
            'pics' => $pics,
            'store_intro' => $this->store_intro,
            'categories' => $categories,
            'email' => $this->email,
            'phone' => $this->phone,
            'wechat_pics' => $wechatPics,
        ];
    }
}
