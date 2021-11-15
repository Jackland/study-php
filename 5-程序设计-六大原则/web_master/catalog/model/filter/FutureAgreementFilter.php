<?php

namespace Catalog\model\filter;

use App\Helper\CountryHelper;
use Catalog\model\filter\BaseFilter;

trait FutureAgreementFilter
{
    use BaseFilter;

    public function agreement_no($agreement_no)
    {
        return $this->builder->where('agreement_no', 'like', "%{$agreement_no}%");
    }

    public function name($name)
    {
        return $this->builder->where('c.nickname', 'like', '%' . $name . '%')
            ->orWhere('c.user_number', 'like', '%' . $name . '%');
    }

    public function store_name($store_name)
    {
        return $this->builder->where('c2c.screenname', 'like', '%' . $store_name . '%');
    }

    public function sku($sku)
    {
        return $this->builder->where('p.sku', 'like', '%' . $sku . '%');
    }

    public function date_from($date_from)
    {
        return $this->builder->where(function ($query) use ($date_from) {
            $query->where(function ($query) use ($date_from) {
                $query->where('fa.update_time', '>=', $date_from)
                    ->where('fa.agreement_status', '<', 7);
            })->orWhere(function ($query) use ($date_from) {
                $query->where('fd.update_time', '>=', $date_from)
                    ->where('fa.agreement_status', 7);
            });

        });
    }

    public function delivery_date_from($date_from)
    {
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $current_delivery_date_from = substr(dateFormat($fromZone,$toZone,  $date_from), 0, 10);//当前国别的日期

        return $this->builder->whereRaw('CASE WHEN fd.delivery_date IS NOT NULL THEN fd.delivery_date >= ? ELSE fa.expected_delivery_date >= ? END', [$date_from, $current_delivery_date_from]);
    }

    public function delivery_date_to($date_to)
    {
        $country = session('country', 'USA');
        $fromZone = CountryHelper::getTimezoneByCode('USA');
        $toZone = CountryHelper::getTimezoneByCode($country);
        $current_delivery_date_to   = substr(dateFormat($fromZone,$toZone,  $date_to), 0, 10);//当前国别的日期

        return $this->builder->whereRaw('CASE WHEN fd.delivery_date IS NOT NULL THEN fd.delivery_date <= ? ELSE fa.expected_delivery_date <= ? END', [$date_to, $current_delivery_date_to]);
    }

    public function date_to($date_to)
    {
        return $this->builder->where(function ($query) use ($date_to) {
            $query->where(function ($query) use ($date_to) {
                $query->where('fa.update_time', '<=', $date_to)
                    ->where('fa.agreement_status', '<', 7);
            })->orWhere(function ($query) use ($date_to) {
                $query->where('fd.update_time', '<=', $date_to)
                    ->where('fa.agreement_status', 7);
            });

        });
    }

    public function status($status)
    {
        switch ($status) {
            case 1:
            {//待处理
                $this->builder->whereIn('fa.agreement_status', [1, 2, 4, 6])
                    ->where('fa.ignore', 0);
                break;
            }
            case 2:
            {//待交付
                $this->builder->where('fd.delivery_status', 1);
                break;
            }
            case 3:
            {//待交割
                $this->builder->whereIn('fd.delivery_status', [3, 7]);
                break;
            }
            case 4:
            {//待支付
                $this->builder->where('fa.agreement_status', 3)
                    ->where('fd.delivery_status', 6);
                break;
            }
            case 5:
            {// seller待审批
                $this->builder->where('fd.delivery_status', 5);
                break;
            }
            case 6:
            {// seller待处理
                $this->builder->whereIn('fa.agreement_status', [1, 2]);
                break;
            }
            case 7:
            {// buyer to be paid
                $this->builder->where('fd.delivery_status', 6);
                break;
            }

            case 8:
            {// 即将到交货日期前七天和即将超时的协议1小时
                $fromTz = TENSE_TIME_ZONES_NO[getPSTOrPDTFromDate(date('Y-m-d H:i:s'))];
                $toTz = ($this->customer->isUSA() || $this->session->get('country', 'USA') == 'USA') ? $fromTz : COUNTRY_TIME_ZONES_NO[$this->session->get('country')];

                $last_tips_date = date('Y-m-d H:i:s',time() - 23*3600);
                $last_end_date = date('Y-m-d H:i:s',time() - 24*3600);
                $expected_delivery_date_start = date('Y-m-d', strtotime(changeOutPutByZone(date('Y-m-d H:i:s'), $this->session)));
                $expected_delivery_date_end = date('Y-m-d', strtotime(changeOutPutByZone(date('Y-m-d H:i:s'), $this->session)) + 7*86400);
                $future_margin_start = changeOutPutByZone(date('Y-m-d H:i:s',time() - 7*86400), $this->session);
                $future_margin_end   = changeOutPutByZone(date('Y-m-d H:i:s',time() - 7*86400 + 3600), $this->session);

                $condition = [
                    'last_tips_date' => $last_tips_date,
                    'last_end_date' => $last_end_date,
                    'expected_delivery_date_start' => $expected_delivery_date_start,
                    'expected_delivery_date_end' => $expected_delivery_date_end,
                    'future_margin_start' => $future_margin_start,
                    'future_margin_end' => $future_margin_end,
                    'from_tz' => $fromTz,
                    'to_tz' => $toTz,
                ];

                $this->builder->where(function ($query) use ($condition) {
                    $query->where(function ($query) use ($condition) {
                        $query->where('fa.update_time', '>=', $condition['last_end_date'])
                            ->where('fa.update_time', '<=', $condition['last_tips_date'])
                            ->whereIn('fa.agreement_status', [1,2,3]);
                    })->orWhere(function ($query) use ($condition) {
                        $query->where('fa.expected_delivery_date', '>=', $condition['expected_delivery_date_start'])
                            ->where('fa.expected_delivery_date', '<', $condition['expected_delivery_date_end'])
                            ->where('fd.delivery_status', 1);
                    })->orWhere(function ($query) use ($condition) {
                        $query->where('fd.confirm_delivery_date', '>=', $condition['last_end_date'])
                            ->where('fd.confirm_delivery_date', '<=', $condition['last_tips_date'])
                            ->where([
                                'fd.delivery_status'=> 6,
                                'fd.delivery_type'=> 2,
                            ]);
                    })->orWhere(function ($query) use ($condition) {
                        $query->where([
                            'fd.delivery_status'=> 6,
                            'fd.delivery_type'=> 1,
                        ])->whereRaw("CONCAT(DATE(CONVERT_TZ(fd.confirm_delivery_date, ?, ?)), ' 23:59:59') between ? and ?", [
                            $condition['from_tz'],
                            $condition['to_tz'],
                            $condition['future_margin_start'],
                            $condition['future_margin_end']
                        ]);
                    });
                });
                break;
            }

            case 9:
            {//待审批的协议
                $this->builder->whereNotNull('aa.id');
                break;
            }

        }
    }

    public function agreement_status($agreement_status)
    {
        return $this->builder->where('fa.agreement_status', $agreement_status);
    }

    public function delivery_status($delivery_status)
    {
        return $this->builder->when(in_array($delivery_status ,\ModelFuturesAgreement::DELIVERY_BEING_PROCESSED), function ($q) use ($delivery_status) {
                    return $q->whereIn('fd.delivery_status', \ModelFuturesAgreement::DELIVERY_BEING_PROCESSED);
                })
                ->when(!in_array($delivery_status ,\ModelFuturesAgreement::DELIVERY_BEING_PROCESSED), function ($q) use ($delivery_status) {
                    return $q->where('fd.delivery_status', $delivery_status);
                });

    }

    public function item_code($item_code)
    {
        return $this->builder->where(function ($query) use ($item_code) {
            $query->where('p.sku', 'like', '%' . $item_code . '%')
                ->orWhere('p.mpn', 'like', '%' . $item_code . '%');
        });
    }
}
