<?php

use App\Catalog\Controllers\AuthSellerController;
use App\Catalog\Search\CustomerPartner\Marketing\MarketingDiscountSearch;
use App\Catalog\Forms\CustomerPartner\Marketing\MarketingDiscountForm;
use App\Helper\CountryHelper;
use App\Models\Marketing\MarketingDiscount;
use App\Repositories\Marketing\MarketingDiscountRepository;
use App\Enums\Marketing\MarketingDiscountStatus;
use App\Enums\Common\YesNoEnum;
use Carbon\Carbon;

class ControllerCustomerpartnerMarketingCampaignDiscount extends AuthSellerController
{
    public function index()
    {
        $data = [];
        $search = new MarketingDiscountSearch(customer()->getId());
        $dataProvider = $search->search($this->request->query->all());
        $discountList = $dataProvider->getList();
        $timeZone = CountryHelper::getTimezone(customer()->getCountryId());

        foreach ($discountList as $discount) {
            /** @var MarketingDiscount $discount */
            $discount->effective_status = app(MarketingDiscountRepository::class)->getDiscountEffectiveStatus($discount);
            $discount->effective_status_name = MarketingDiscountStatus::getDescription($discount->effective_status);
            $discount->color_status_name = MarketingDiscountStatus::getColorDescription($discount->effective_status);

            //转成对应国别时间，js里面有直接取值
            $discount->effective_time_current_zone =  Carbon::parse($discount->effective_time)->timezone($timeZone)->toDateTimeString();
            $discount->expiration_time_current_zone =  Carbon::parse($discount->expiration_time)->timezone($timeZone)->toDateTimeString();
        }

        $data['list'] = $discountList;
        $data['total'] = $dataProvider->getTotalCount();
        $data['paginator'] = $dataProvider->getPaginator();
        $data['sort'] = $dataProvider->getSort();
        $data['search'] = $search->getSearchData();

        $data['discount_status_list'] = MarketingDiscountStatus::getViewItems();
        $data['seller_buyer_list'] = app(MarketingDiscountRepository::class)->getBuyerInfosAssociatedSeller(null);
        $data['country_id'] = $this->customer->getCountryId();

        return $this->render('customerpartner/marketing_campaign/discount/store_wide_discounts', $data);
    }

    public function store(MarketingDiscountForm $discountForm)
    {
        $result = $discountForm->save();
        return $result['code'] == 200 ? $this->jsonSuccess([],$result['msg']) : $this->jsonFailed($result['msg']);
    }

    public function del()
    {
        $discountId = $this->request->post('discount_id', 0);
        $existCheck = MarketingDiscount::query()->where('id', $discountId)->where('seller_id', customer()->getId())->exists();
        if (!$existCheck) {
            return $this->jsonFailed(__('当前活动不可删除', [], 'catalog/view/customerpartner/marketing_campaign/discount'));
        }

        $res = MarketingDiscount::query()->where('id', $discountId)->update(['is_del' => YesNoEnum::YES]);
        if ($res !== false) {
            return $this->jsonSuccess([],__('删除成功', [], 'catalog/view/customerpartner/marketing_campaign/discount'));
        }

        return $this->jsonFailed(__('删除失败', [], 'catalog/view/customerpartner/marketing_campaign/discount'));
    }

}
