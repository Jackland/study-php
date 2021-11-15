<?php

namespace App\Catalog\Forms\CWF;

use App\Components\Traits\RequestCachedDataTrait;
use App\Enums\Customer\CustomerAccountingType;
use App\Models\Buyer\BuyerToSeller;
use App\Models\Product\Product;
use Framework\Model\BaseValidateModel;
use Illuminate\Support\Str;

class FileDataValidate extends BaseValidateModel
{
    use RequestCachedDataTrait;

    public $data = [];

    private $availableSellerList;

    public function __construct()
    {
        parent::__construct();
        $this->availableSellerList = BuyerToSeller::queryRead()
            ->select('seller_id')
            ->where('buyer_id', (int)customer()->getId())
            ->where('seller_control_status', 1)
            ->where('buyer_control_status', 1)
            ->get()
            ->pluck('seller_id')
            ->toArray();
    }

    protected function getRules(): array
    {
        return [
            'data' => 'array',
            'data.*.sales_platform' => 'string|max:20',
            'data.*.order_date' => 'string|max:25',
            'data.*.b2b_item_code' => [
                'required', 'string', 'max:30',
                function ($attribute, $value, $fail) {
                    $cacheKey = [__CLASS__, __FUNCTION__, $value, customer()->getId()];
                    $cacheData = $this->getRequestCachedData($cacheKey);
                    if ($cacheData !== null) {
                        $exists = $cacheData;
                    } else {
                        $exists = 0;
                        $productList = Product::queryRead()->alias('p')
                            ->with(['customerPartner'])
                            ->leftJoinRelations(['customerPartnerToProduct as ctp'])
                            ->leftJoin('oc_customer as c', 'c.customer_id', 'ctp.customer_id')
                            ->where([
                                'p.sku' => $value,
                                'c.country_id' => customer()->getCountryId(),
                                'p.status' => 1,
                                'p.buyer_flag' => 1,
                            ])
                            ->get();
                        if ($productList->isNotEmpty()) {
                            // reduce 也可以实现 这样处理是便于阅读
                            $resolveRes = $productList
                                ->map(function ($item) {
                                    return in_array($item->customerPartner->customer_id, $this->availableSellerList) ? 1 : 0;
                                })
                                ->toArray();
                            if (array_sum($resolveRes) == 0) {
                                $exists = -1;
                            } else {
                                // 需要补充下对giga onsite seller的校验 现在是不允许giga onsite 发送云送仓
                                $validProductList = $productList->filter(function ($item) {
                                    return in_array($item->customerPartner->customer_id, $this->availableSellerList);
                                });
                                $resolveRes2 = $validProductList
                                    ->map(function ($item) {
                                        return $item->customerPartner->accounting_type == CustomerAccountingType::GIGA_ONSIDE
                                            ? 0 : 1;
                                    })
                                    ->toArray();
                                // 如果全是giga onsite的产品 则结果求和为0
                                if (array_sum($resolveRes2) == 0) {
                                    $exists = -2;
                                } else {
                                    $exists = 1;
                                }

                            }
                        }
                        $this->setRequestCachedData($cacheKey, $exists);
                    }

                    if ($exists == 0) {
                        return $fail('This product does not exist in the Marketplace and cannot be purchased.');
                    }
                    if ($exists == -1) {
                        return $fail("$value: You do not have permission to purchase this product!");
                    }
                    if ($exists == -2) {
                        return $fail("The itemcode $value is not available for Cloud Wholesale Fulfillment currently. If you have any questions or concerns, please contact the online customer service.");
                    }
                }
            ],
            'data.*.ship_to_qty' => 'required|integer|gt:0',
            'data.*.ship_to_name' => 'required|string|max:100',
            'data.*.ship_to_email' => 'required|email|max:30',
            'data.*.ship_to_phone' => 'required|string|max:30',
            'data.*.ship_to_postal_code' => 'required|string|max:20',
            'data.*.ship_to_address_detail' => [
                'required', 'string', 'max:56',
                function ($attribute, $value, $fail) {
                    $str = strtolower(preg_replace('/[^a-zA-Z0-9]/i', '', $value));
                    $res = stripos($str, 'pobox');
                    $res_other = stripos($str, 'poboxes');
                    if ($res !== false || $res_other !== false) {
                        $fail("ShipToAddressDetail in P.O.BOX doesn't support delivery,Please see the instructions.");
                    }
                }
            ],
            'data.*.ship_to_city' => 'required|string|max:50',
            'data.*.ship_to_state' => [
                'required', 'string', 'max:30',
                function ($attribute, $value, $fail) {
                    if (
                        in_array(
                            strtoupper($value),
                            [
                                'PR', 'AK', 'HI', 'GU', 'AA',
                                'AE', 'AP', 'ALASKA', 'ARMED FORCES AMERICAS',
                                'ARMED FORCES EUROPE', 'ARMED FORCES PACIFIC', 'GUAM',
                                'HAWAII', 'PUERTO RICO'
                            ])
                    ) {
                        $fail("ShipToState in PR, AK, HI, GU, AA, AE, AP doesn't support delivery,Please see the instructions.");
                    }
                }
            ],
            'data.*.ship_to_country' => ['required', 'string',
                function ($attribute, $value, $fail) {
                    if (
                        !in_array(
                            strtoupper($value),
                            [
                                'US'
                            ])
                    ) {
                        $fail("ShipToCountry must be 'US'.");
                    }
                }
            ],
            'data.*.loading_dock_provided' => ['required', 'string',
                function ($attribute, $value, $fail) {
                    if (
                        !in_array(
                            strtoupper($value),
                            [
                                'YES', 'NO'
                            ])
                    ) {
                        $fail('LoadingDockProvided must be "YES" or "NO”!');
                    }
                }
            ],
            'data.*.order_comments' => 'string|max:2000',
            'data.*.ship_to_attachment_url' => 'string|max:800',

        ];
    }

    protected function getAttributeLabels(): array
    {
        $titles = [
            'sales_platform', 'order_date', 'b2b_item_code', 'ship_to_qty', 'ship_to_name',
            'ship_to_email', 'ship_to_phone', 'ship_to_postal_code', 'ship_to_address_detail', 'ship_to_city',
            'ship_to_state', 'ship_to_country', 'loading_dock_provided', 'order_comments', 'ship_to_attachment_url',
        ];

        $ret = [];
        foreach ($titles as $title) {
            $ret["data.*.$title"] = Str::studly($title);
        }
        return $ret;
    }

    protected function getRuleMessages(): array
    {
        return [
            'data.*.sales_platform.max' => 'Sales PlatFrom must be between 0 and 20 characters!',
            'data.*.OrderDate.max' => 'OrderDate must be between 0 and 25 characters!',
            'data.*.b2b_item_code.required' => 'B2BItemCode must be between 1 and 30 characters!',
            'data.*.ship_to_qty.required' => 'ShipToQty format error,Please see the instructions.',
            'data.*.ship_to_qty.integer' => 'ShipToQty format error,Please see the instructions.',
            'data.*.ship_to_name.required' => 'ShipToName must be between 1 and 100 characters!',
            'data.*.ship_to_name.max' => 'ShipToName must be between 1 and 100 characters!',
            'data.*.ship_to_email.required' => 'ShipToEmail must be between 1 and 30 characters!',
            'data.*.ship_to_email.max' => 'ShipToEmail must be between 1 and 30 characters!',
            'data.*.ship_to_phone.required' => 'ShipToPhone must be between 1 and 30 characters!',
            'data.*.ship_to_phone.max' => 'ShipToPhone must be between 1 and 30 characters!',
            'data.*.ship_to_postal_code.required' => 'ShipToPostalCode must be between 1 and 20 characters!',
            'data.*.ship_to_postal_code.max' => 'ShipToPostalCode must be between 1 and 20 characters!',
            'data.*.ship_to_address_detail.required' => 'ShipToAddressDetail must be between 1 and 56 characters!',
            'data.*.ship_to_address_detail.max' => 'ShipToAddressDetail must be between 1 and 56 characters!',
            'data.*.ship_to_city.required' => 'ShipToCity must be between 1 and 50 characters!',
            'data.*.ship_to_city.max' => 'ShipToCity must be between 1 and 50 characters!',
            'data.*.ship_to_state.required' => 'ShipToState must be between 1 and 30 characters!',
            'data.*.ship_to_country.required' => "ShipToCountry must be 'US'.",
            'data.*.ship_to_state.max' => 'ShipToState must be between 1 and 30 characters!',
            'data.*.order_comments.max' => 'OrderComments must be between 0 and 2000 characters!',
            'data.*.ship_to_attachment_url.max' => 'ShipToAttachmentUrl must be between 0 and 800 characters!',
            'data.*.loading_dock_provided.required' => 'LoadingDockProvided can not be empty!',
        ];
    }

}
