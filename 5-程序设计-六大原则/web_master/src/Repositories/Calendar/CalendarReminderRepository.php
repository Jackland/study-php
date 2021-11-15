<?php

namespace App\Repositories\Calendar;


use App\Enums\Future\FuturesMarginAgreementStatus;
use App\Enums\Future\FuturesMarginDeliveryStatus;
use App\Enums\Future\FuturesVersion;
use App\Enums\Margin\MarginAgreementStatus;
use App\Enums\Order\OcOrderStatus;
use App\Enums\Spot\SpotProductQuoteStatus;
use App\Models\Futures\FuturesMarginAgreement;
use App\Models\Margin\MarginAgreement;
use App\Models\Order\Order;
use App\Models\Rebate\RebateAgreement;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

/**
 * Class CalendarReminderRepository
 * 注意：方法中已重新设置时区
 * @package App\Repositories\Calendar
 */
class CalendarReminderRepository
{
    /**
     * 日历星期标题
     */
    const TITLE_OF_WEEK = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    /**
     * 待处理事件
     * @param string $fromZone
     * @param string $toZone
     * @param array $dateUS ['时间戳']
     * @param int|null $time 此时此刻的时间戳
     * @return array
     */
    protected function getEvent($fromZone, $toZone, $dateUS, $time = null)
    {
        if (is_null($time)) {
            $time = time();
        }
        foreach ($dateUS as $key => $value) {
            $dateUS[$key] = date('Y-m-d H:i:s', $value);
        }
        $YmdNow = date('Y-m-d', $time);
        $dateNow = date('Y-m-d H:i:s', $time);
        $timeServer = time();
        $dateServer = date('Y-m-d H:i:s', $timeServer);
        $dateServerZone = dateFormat($fromZone, $toZone, $dateServer, 'Y-m-d H:i:s');
        $dayServerZone = substr($dateServerZone, 0, 10);


        $listDayZone2Event = [];// 二维数组，一维 key=dayZone，二维是列表
        $listDayZoneTimeKey2Event = [];//二维数组， 一维 key=dayZone，二维 key='Y-m-d H:i'，三维是列表

        $dateUSstart = reset($dateUS);
        $dateUSEnd = end($dateUS);

        $dateUSstartInt = strtotime($dateUSstart) - 86400 * 7;
        $dateUSstartString = date('Y-m-d H:i:s', $dateUSstartInt);

        $dayToZoneFirst = dateFormat($fromZone, $toZone, $dateUSstartString, 'Y-m-d');
        $dayToZoneLast = dateFormat($fromZone, $toZone, $dateUSEnd, 'Y-m-d');

        $dayToZoneStar = $dayToZoneFirst . ' 00:00:00';
        $dayToZoneEnd = $dayToZoneLast . ' 23:59:59';
        $start = changeToUSADate(session(), $dayToZoneStar);
        $end = changeToUSADate(session(), $dayToZoneEnd);

        $startInt = strtotime($start);
        $endInt = strtotime($end);

        //region To be paid；订单未支付时，提醒订单支付截止时间
        $second = intval(configDB('expire_time') * 60);
        $collection = Order::query()->select('order_id', 'date_added', 'date_added as date')
            ->where('customer_id', '=', customer()->getId())
            ->where('order_status_id', '=', OcOrderStatus::TO_BE_PAID)
            ->where('date_added', '>', date('Y-m-d H:i:s', $timeServer - $second))
            ->where('date_added', '<', date('Y-m-d H:i:s', $timeServer))
            ->orderBy('date_added')
            ->get();
        foreach ($collection as $key => $value) {
            $tmpInt = strtotime($value->date) + $second;
            $tmpInt2Str = date('Y-m-d H:i:s', $tmpInt);
            $YmdDB = date('Y-m-d', $tmpInt);
            $dateTmp = ($YmdDB < $YmdNow) ? ($dateNow) : $tmpInt2Str;
            $timeKey = dateFormat($fromZone, $toZone, date('Y-m-d H:i:s', $tmpInt), 'Y-m-d H:i');
            $tmp = [];
            $tmp['mark'] = 'ToBePaid';
            $tmp['url'] = url()->to(['account/order', 'order_id' => $value->order_id]);
            $tmp['title'] = 'To be paid';
            $tmp['time'] = $this->getLeftTime($dateServerZone, dateFormat($fromZone, $toZone, date('Y-m-d H:i:s', strtotime($value->date) + $second), 'Y-m-d H:i:s'));
            $tmp['description'] = 'The Purchase Order (ID: %s) is to be paid';
            $tmp['orderNo'] = $value->order_id;
            $dayZone = dateFormat($fromZone, $toZone, $dateTmp, 'Y-m-d');
            $listDayZoneTimeKey2Event[$dayZone][$timeKey][] = $tmp;
        }
        //endregion
        //region Margin；Seller同意后，Buyer24小时内未支付定金，协议过期的提示
        $collection = MarginAgreement::query()
            ->select('id', 'agreement_id', 'create_time', 'create_time as date')
            ->where('buyer_id', '=', customer()->getId())
            ->where('status', '=', MarginAgreementStatus::APPROVED)
            ->where('is_bid', '=', 1)
            ->where('create_time', '>=', date('Y-m-d H:i:s', $timeServer - 86400))
            ->where('create_time', '<=', date('Y-m-d H:i:s', $timeServer))
            ->get();
        foreach ($collection as $key => $value) {
            $tmpInt = strtotime($value->date) + 86400;
            $tmpInt2Str = date('Y-m-d H:i:s', $tmpInt);
            $YmdDB = date('Y-m-d', $tmpInt);
            $dateTmp = ($YmdDB < $YmdNow) ? ($dateNow) : $tmpInt2Str;
            $timeKey = dateFormat($fromZone, $toZone, date('Y-m-d H:i:s', $tmpInt), 'Y-m-d H:i');
            $tmp = [];
            $tmp['mark'] = 'Margin';
            $tmp['url'] = url()->to(['account/product_quotes/wk_quote_my', 'tab' => 2, 'no' => $value->agreement_id]);
            $tmp['title'] = 'Margin';
            $tmp['time'] = $this->getLeftTime($dateServerZone, $timeKey);
            $tmp['description'] = 'The Agreement (ID: %s) has been expired since the deposit was not paid within the specified time.';
            $tmp['orderNo'] = $value->agreement_id;
            $dayZone = dateFormat($fromZone, $toZone, $dateTmp, 'Y-m-d');
            $listDayZoneTimeKey2Event[$dayZone][$timeKey][] = $tmp;
        }
        //endregion
        //region Margin；现货协议到期的时间点
        $collection = MarginAgreement::query()->alias('a')
            ->select('id', 'agreement_id', 'expire_time as date')
            ->where('buyer_id', '=', customer()->getId())
            ->where('a.status', '=', MarginAgreementStatus::SOLD)
            ->where('expire_time', '>=', $start)
            ->where('expire_time', '<=', $end)
            ->get();
        foreach ($collection as $key => $value) {
            $tmp = [];
            $tmp['mark'] = 'Margin';
            $tmp['url'] = url()->to(['account/product_quotes/wk_quote_my', 'tab' => 2, 'no' => $value->agreement_id]);
            $tmp['title'] = 'Margin';
            $tmp['time'] = 'All Day';
            $tmp['description'] = 'Due date of Margin Agreement (ID: %s).';
            $tmp['orderNo'] = $value->agreement_id;
            $dayZone = dateFormat($fromZone, $toZone, $value->date, 'Y-m-d');
            $listDayZone2Event[$dayZone][] = $tmp;
        }
        //endregion
        //region Rebates；返点协议到期的时间点
        $collection = RebateAgreement::query()
            ->select('id', 'agreement_code', 'expire_time as date')
            ->where('buyer_id', '=', customer()->getId())
            ->where('status', '=', 3)
            ->whereIn('rebate_result', [1, 2, 6])
            ->where('expire_time', '>=', $start)
            ->where('expire_time', '<=', $end)
            ->get();
        foreach ($collection as $key => $value) {
            $timeKey = dateFormat($fromZone, $toZone, $value->date, 'Y-m-d H:i');
            $tmp = [];
            $tmp['mark'] = 'Rebates';
            $tmp['url'] = url()->to(['account/product_quotes/wk_quote_my', 'tab' => 1, 'no' => $value->agreement_code]);
            $tmp['title'] = 'Rebates';
            $tmp['time'] = $this->getLeftTime($dateServerZone, $timeKey, $tmp['mark']);
            $tmp['description'] = 'Due date of Rebate Agreement (ID: %s).';
            $tmp['orderNo'] = $value->agreement_code;
            $dayZone = dateFormat($fromZone, $toZone, $value->date, 'Y-m-d');
            $listDayZoneTimeKey2Event[$dayZone][$timeKey][] = $tmp;
        }
        //endregion
        //region Future；Seller同意后，Buyer24小时内未支付定金，协议过期的提示
        $collection = FuturesMarginAgreement::query()->alias('fa')
            ->select('id', 'agreement_no', 'create_time as date')
            ->where('buyer_id', '=', customer()->getId())
            ->where('fa.agreement_status', '=', FuturesMarginAgreementStatus::APPROVED)
            ->where(function (Builder $query) {
                $query->where([
                    ['fa.contract_id', '<>', 0],
                    ['fa.is_bid', '=', 0],
                    ['fa.agreement_status', '=', FuturesMarginAgreementStatus::SOLD],
                ])->orWhere([
                    ['fa.contract_id', '<>', 0],
                    ['fa.is_bid', '=', 1],
                ])->orWhere([
                    ['fa.contract_id', '=', 0],
                ]);
            })
            ->where('create_time', '>', date('Y-m-d H:i:s', $timeServer - 86400))
            ->where('create_time', '<=', date('Y-m-d H:i:s', $timeServer))
            ->get();
        foreach ($collection as $key => $value) {
            $tmpInt = strtotime($value->date) + 86400;
            $tmpInt2Str = date('Y-m-d H:i:s', $tmpInt);
            $YmdDB = date('Y-m-d', $tmpInt);
            $dateTmp = ($YmdDB < $YmdNow) ? ($dateNow) : $tmpInt2Str;
            $timeKey = dateFormat($fromZone, $toZone, $tmpInt2Str, 'Y-m-d H:i');
            $tmp = [];
            $tmp['mark'] = 'Future';
            $tmp['url'] = url()->to(['account/product_quotes/wk_quote_my', 'tab' => 3, 'no' => $value->agreement_no]);
            $tmp['title'] = 'Future';
            $tmp['time'] = $this->getLeftTime($dateServerZone, $timeKey);
            $tmp['description'] = 'The Agreement (ID: %s) has been expired since the deposit was not paid within the specified time.';
            $tmp['orderNo'] = $value->agreement_no;
            $dayZone = dateFormat($fromZone, $toZone, $dateTmp, 'Y-m-d');
            $listDayZoneTimeKey2Event[$dayZone][$timeKey][] = $tmp;
        }
        //endregion
        //region Future；期货协议交割的时间点，to be delivered
        $collection = FuturesMarginAgreement::query()->alias('fa')
            ->leftJoin('oc_futures_margin_delivery as fd', 'fa.id', '=', 'fd.agreement_id')
            ->select('fa.id', 'fa.agreement_no', 'fa.expected_delivery_date as dayZone')
            ->where('fa.buyer_id', '=', customer()->getId())
            ->where('fa.agreement_status', '=', FuturesMarginAgreementStatus::SOLD)
            ->where('fd.delivery_status', '=', FuturesMarginDeliveryStatus::TO_BE_DELIVERED)
            ->where('fa.ignore', '=', 0)
            ->where(function (Builder $query) {
                $query->where([
                    ['fa.contract_id', '<>', 0],
                    ['fa.is_bid', '=', 0],
                    ['fa.agreement_status', '=', FuturesMarginAgreementStatus::SOLD],
                ])->orWhere([
                    ['fa.contract_id', '<>', 0],
                    ['fa.is_bid', '=', 1],
                ])->orWhere([
                    ['fa.contract_id', '=', 0],
                ]);
            })
            ->where('expected_delivery_date', '>=', $dayToZoneFirst)
            ->where('expected_delivery_date', '<=', $dayToZoneLast)
            ->get();
        foreach ($collection as $key => $value) {
            $tmp = [];
            $tmp['mark'] = 'Future';
            $tmp['url'] = url()->to(['account/product_quotes/wk_quote_my', 'tab' => 3, 'no' => $value->agreement_no]);
            $tmp['title'] = 'Future';
            $tmp['time'] = 'All Day';
            $tmp['description'] = 'Settlement date of Future Goods Agreement (ID: %s).';
            $tmp['orderNo'] = $value->agreement_no;
            $listDayZone2Event[$value->dayZone][] = $tmp;
        }
        //endregion
        //region Future；期货协议尾款支付截止的时间点（当前为交割日的7天后）交割状态为Tobepaid状态
        $collection = FuturesMarginAgreement::query()->alias('fa')
            ->leftJoin('oc_futures_margin_delivery as fd', 'fa.id', '=', 'fd.agreement_id')
            ->select('fa.id', 'fa.agreement_no', 'fa.create_time', 'fa.version', 'fd.confirm_delivery_date')
            ->selectRaw(new Expression('CASE fa.version WHEN ' . FuturesVersion::VERSION . ' THEN DATE_ADD( DATE_FORMAT( fd.confirm_delivery_date, "%Y-%m-%d %H:%i:%s" ), INTERVAL 1 DAY )
            ELSE DATE_ADD( DATE_FORMAT( fd.confirm_delivery_date, "%Y-%m-%d 23:59:59" ), INTERVAL 7 DAY )
            END AS expire_time'))
            ->where('fa.buyer_id', '=', customer()->getId())
            ->where('fa.ignore', '=', 0)
            ->where('fd.delivery_status', FuturesMarginDeliveryStatus::TO_BE_PAID)
            ->where('fd.delivery_date', '>=', $dayToZoneFirst)
            ->where('fd.delivery_date', '<=', $dayToZoneLast)
            ->get();
        foreach ($collection as $key => $value) {
            if ($value->version >= FuturesVersion::VERSION) {
                $timeTitle = $this->getLeftTime($dateServer, $value->expire_time);
            } else {
                $timeTitle = 'All Day';
            }
            $tmp = [];
            $tmp['mark'] = 'Future';
            $tmp['url'] = url()->to(['account/product_quotes/wk_quote_my', 'tab' => 3, 'no' => $value->agreement_no]);
            $tmp['title'] = 'Future';
            $tmp['time'] = $timeTitle;
            $tmp['description'] = 'Due payment date of Future Goods Agreement (ID %s).';
            $tmp['orderNo'] = $value->agreement_no;
            $dayZone = dateFormat($fromZone, $toZone, $value->expire_time, 'Y-m-d');
            $listDayZone2Event[$dayZone][] = $tmp;

            if ($value->version >= FuturesVersion::VERSION) {
                $timeKeyCreate = dateFormat($fromZone, $toZone, date('Y-m-d H:i:s', strtotime($value->expire_time) - 86400), 'Y-m-d H:i');
                $dayZoneCreate = substr($timeKeyCreate, 0, 10);
                if ($dayZoneCreate != $dayZone && $dayZoneCreate >=  $dayServerZone) {
                    $listDayZoneTimeKey2Event[$dayZoneCreate][$timeKeyCreate][] = $tmp;
                }
            }
        }
        //endregion
        //region Spot；Seller同意后，Buyer24小时内未支付，协议过期的提示
        $collection = DB::table('oc_product_quote')
            ->select('id', 'agreement_no', 'product_id', 'date_added as date')
            ->where('customer_id', '=', customer()->getId())
            ->where('status', '=', SpotProductQuoteStatus::APPROVED)
            ->where('date_added', '>=', date('Y-m-d H:i:s', $timeServer - 86400))
            ->where('date_added', '<=', date('Y-m-d H:i:s', $timeServer))
            ->get();
        foreach ($collection as $key => $value) {
            $tmpInt = strtotime($value->date) + 86400;
            $tmpInt2Str = date('Y-m-d H:i:s', $tmpInt);
            $YmdDB = date('Y-m-d', $tmpInt);
            $dateTmp = ($YmdDB < $YmdNow) ? ($dateNow) : $tmpInt2Str;
            $timeKey = dateFormat($fromZone, $toZone, $tmpInt2Str, 'Y-m-d H:i');
            $tmp = [];
            $tmp['mark'] = 'Spot';
            $tmp['url'] = url()->to(['account/product_quotes/wk_quote_my', 'tab' => 0, 'no' => $value->agreement_no]);
            $tmp['title'] = 'Spot';
            $tmp['time'] = $this->getLeftTime($dateServerZone, $timeKey);
            $tmp['description'] = 'The Agreement (ID: %s) has been expired since the payment was not completed within the specified time.';
            $tmp['orderNo'] = $value->agreement_no;
            $dayZone = dateFormat($fromZone, $toZone, $dateTmp, 'Y-m-d');
            $listDayZoneTimeKey2Event[$dayZone][$timeKey][] = $tmp;
        }
        //endregion

        //region 如果是 'Y-m-d H:i'，那么就升序靠前
        foreach ($listDayZoneTimeKey2Event as $keyDayZone => $valueTimeZoneList) {
            ksort($valueTimeZoneList);
            $dayEventList = [];
            foreach ($valueTimeZoneList as $valueList) {
                $dayEventList = array_merge($dayEventList, $valueList);
            }

            if (isset($listDayZone2Event[$keyDayZone])) {
                $listDayZone2Event[$keyDayZone] = array_merge($dayEventList, $listDayZone2Event[$keyDayZone]);
            } else {
                $listDayZone2Event[$keyDayZone] = $dayEventList;
            }
        }
        //endregion

        return $listDayZone2Event;
    }

    /**
     * @param string $fromZone
     * @param string $toZone
     * @param int $dayStartInt 时间戳
     * @param int $dayEndInt 时间戳
     * @param int $time 此时此刻的时间戳
     * @param int $timeEndMonthEvent 最后一个要查询待处理事件的月份的时间戳
     * @return array
     */
    protected function getListDay($fromZone, $toZone, $dayStartInt, $dayEndInt, $time, $timeEndMonthEvent)
    {
        $dayZone2ItemMul = [];//key=dayZone 为了得到日历
        $dayZone2TimestampArr = [];//          为了得到要去查询待处理事件的时间戳
        $dayToday = date('Y-m-d', $time);
        $yearMonthEndSpecial = date('Ym', $timeEndMonthEvent);

        while ($dayStartInt <= $dayEndInt) {
            $item = [
                'week' => date('D', $dayStartInt),
                'ymd' => date('Y-m-d', $dayStartInt),
                'year' => date('Y', $dayStartInt),
                'month' => date('m', $dayStartInt),
                'day' => date('j', $dayStartInt),
                'dayEnglish' => date('l, F j', $dayStartInt) . '<sup>' . date('S', $dayStartInt) . '</sup>',
            ];

            if (date('Y-m', $dayStartInt) < date('Y-m', $time)) {
                $item['timeline'] = '-1';
                $item['listEvent'] = [];
                $dayZone = date('Y-m-d', $dayStartInt);
                $dayZone2ItemMul[$dayZone] = $item;
            } elseif (date('Y-m', $dayStartInt) == date('Y-m', $time)) {
                $dayZone = date('Y-m-d', $dayStartInt);
                if (date('Y-m-d', $dayStartInt) < $dayToday) {
                    $item['timeline'] = '-1';
                    $item['listEvent'] = [];
                    $dayZone2ItemMul[$dayZone] = $item;
                } elseif (date('Y-m-d', $dayStartInt) == $dayToday) {
                    $item['timeline'] = '0';
                    $item['listEvent'] = [];
                    $dayZone2ItemMul[$dayZone] = $item;
                    end($dayZone2ItemMul);
                    $keyIndex = key($dayZone2ItemMul);
                    $dayZone2TimestampArr[$keyIndex] = $dayStartInt;
                } else {//date('Y-m-d', $dayStartInt) > $dayToday
                    $item['timeline'] = '1';
                    $item['listEvent'] = [];
                    $dayZone2ItemMul[$dayZone] = $item;
                    end($dayZone2ItemMul);
                    $keyIndex = key($dayZone2ItemMul);
                    $dayZone2TimestampArr[$keyIndex] = $dayStartInt;
                }
            } else {//date('Y-m', $dayStartInt) > date('Y-m', $time)
                $item['timeline'] = '1';
                $item['listEvent'] = [];
                $dayZone = date('Y-m-d', $dayStartInt);
                $dayZone2ItemMul[$dayZone] = $item;

                if (date('Ym', $dayStartInt) - $yearMonthEndSpecial == 0) {
                    end($dayZone2ItemMul);
                    $keyIndex = key($dayZone2ItemMul);
                    $dayZone2TimestampArr[$keyIndex] = $dayStartInt;
                }
            }
            $dayStartInt = $dayStartInt + 86400;
        }
        return [
            'dayZone2ItemMul' => $dayZone2ItemMul,
            'dayZone2TimestampArr' => $dayZone2TimestampArr
        ];
    }

    /**
     * 日历待办事项
     * 注意：方法中已重新设置时区
     * @param string $fromZone
     * @param string $toZone
     * @param bool $checkEvent 是否查询待处理事件
     * @return array
     */
    public function getRemainder($fromZone, $toZone, $checkEvent = false)
    {
        date_default_timezone_set($toZone);
        $time = time();
        $timestamp = $time;
        //两周
        $dayFirstOfWeekInt = ($timestamp - date('w') * 86400);//星期中的第几天，数字表示；0（表示星期天）到 6（表示星期六）
        $dayLastOfWeekNextInt = $dayFirstOfWeekInt + 86400 * 13;
        $result = $this->getListDay($fromZone, $toZone, $dayFirstOfWeekInt, $dayLastOfWeekNextInt, $timestamp, $dayLastOfWeekNextInt);
        $dayZone2ItemMul = $result['dayZone2ItemMul'];
        //$weekDayZone2TimestampArr = $result['dayZone2TimestampArr'];
        $objCollectionWeek = [
            'year' => date('Y', $timestamp),//4 位数字完整表示的年份
            'month' => date('m', $timestamp),//数字表示的月份，有前导零
            'monthEnglish' => date('F', $timestamp),//月份，完整的文本格式
            'titleOfWeek' => self::TITLE_OF_WEEK,
            'listDay' => $dayZone2ItemMul,];

        //本月
        $dayFirstOfMonth = date('Y-m-01', $time);           //指定日期月第一天
        $dayFirstOfMonthInt = strtotime($dayFirstOfMonth);
        $dayFirstOfPageInt = ($dayFirstOfMonthInt - date('w', $dayFirstOfMonthInt) * 86400);//指定日期日历页第一天
        $dayLastOfPageInt = $dayFirstOfPageInt + 86400 * 41;//指定日期日历页最后一天
        $result = $this->getListDay($fromZone, $toZone, $dayFirstOfPageInt, $dayLastOfPageInt, $timestamp, $dayLastOfPageInt);
        $dayZone2ItemMul = $result['dayZone2ItemMul'];
        $monthNowDayZone2TimestampArr = $result['dayZone2TimestampArr'];
        $monthNow = [
            'year' => date('Y', $dayFirstOfMonthInt),
            'month' => date('m', $dayFirstOfMonthInt),
            'monthEnglish' => date('F', $dayFirstOfMonthInt),
            'titleOfWeek' => self::TITLE_OF_WEEK,
            'listDay' => $dayZone2ItemMul,];

        //下一月
        $time = strtotime(date('Y-m-t', time())) + 86400; //下一月第一天
        $dayFirstOfMonth = date('Y-m-01', $time);           //指定日期月第一天
        $dayFirstOfMonthInt = strtotime($dayFirstOfMonth);
        $dayFirstOfPageInt = ($dayFirstOfMonthInt - date('w', $dayFirstOfMonthInt) * 86400);//指定日期日历页第一天
        $dayLastOfPageInt = $dayFirstOfPageInt + 86400 * 41;//指定日期日历页最后一天
        $result = $this->getListDay($fromZone, $toZone, $dayFirstOfPageInt, $dayLastOfPageInt, $timestamp, $dayFirstOfMonthInt);
        $dayZone2ItemMul = $result['dayZone2ItemMul'];
        $monthNextDayZone2TimestampArr = $result['dayZone2TimestampArr'];
        $monthNext = [
            'year' => date('Y', $dayFirstOfMonthInt),
            'month' => date('m', $dayFirstOfMonthInt),
            'monthEnglish' => date('F', $dayFirstOfMonthInt),
            'titleOfWeek' => self::TITLE_OF_WEEK,
            'listDay' => $dayZone2ItemMul,];

        date_default_timezone_set($fromZone);

        //待处理事件
        if ($checkEvent) {
            $dayZone2TimestampArr = array_merge($monthNowDayZone2TimestampArr, $monthNextDayZone2TimestampArr);
            $listDayZone2Event = $this->getEvent($fromZone, $toZone, $dayZone2TimestampArr, $timestamp);
            foreach ($listDayZone2Event as $dayZone => $listEvent) {
                if (isset($objCollectionWeek['listDay'][$dayZone])) {
                    $objCollectionWeek['listDay'][$dayZone]['listEvent'] = $listDayZone2Event[$dayZone];
                }
                if (isset($monthNow['listDay'][$dayZone])) {
                    $monthNow['listDay'][$dayZone]['listEvent'] = $listDayZone2Event[$dayZone];
                }
                if (isset($monthNext['listDay'][$dayZone])) {
                    $monthNext['listDay'][$dayZone]['listEvent'] = $listDayZone2Event[$dayZone];
                }
            }
        }

        $listCollectionMonth = [$monthNow, $monthNext,];

        $result = [
            'objCollectionWeek' => $objCollectionWeek,
            'listCollectionMonth' => $listCollectionMonth,
        ];

        return $result;
    }

    /**
     * 日历待办事项——Buyer Center
     * 注意：方法中已重新设置时区
     * @param string $fromZone
     * @param string $toZone
     * @param bool $checkEvent 是否查询待处理事件
     * @return array
     */
    public function getRemainderCenter($fromZone, $toZone, $checkEvent = false)
    {
        date_default_timezone_set($toZone);
        $time = time();
        $timestamp = $time;
        //两周
        $dayFirstOfWeekInt = ($timestamp - date('w') * 86400);//星期中的第几天，数字表示；0（表示星期天）到 6（表示星期六）
        $dayLastOfWeekNextInt = $dayFirstOfWeekInt + 86400 * 13;
        $result = $this->getListDay($fromZone, $toZone, $dayFirstOfWeekInt, $dayLastOfWeekNextInt, $timestamp, $dayLastOfWeekNextInt);
        $dayZone2ItemMul = $result['dayZone2ItemMul'];
        $weekDayZone2TimestampArr = $result['dayZone2TimestampArr'];
        $objCollectionWeek = [
            'year' => date('Y', $timestamp),//4 位数字完整表示的年份
            'month' => date('m', $timestamp),//数字表示的月份，有前导零
            'monthEnglish' => date('F', $timestamp),//月份，完整的文本格式
            'titleOfWeek' => self::TITLE_OF_WEEK,
            'listDay' => $dayZone2ItemMul,];

        //本月
        $dayFirstOfMonth = date('Y-m-01', $time);           //指定日期月第一天
        $dayFirstOfMonthInt = strtotime($dayFirstOfMonth);
        $dayFirstOfPageInt = ($dayFirstOfMonthInt - date('w', $dayFirstOfMonthInt) * 86400);//指定日期日历页第一天
        $dayLastOfPageInt = $dayFirstOfPageInt + 86400 * 41;//指定日期日历页最后一天
        $result = $this->getListDay($fromZone, $toZone, $dayFirstOfPageInt, $dayLastOfPageInt, $timestamp, $dayLastOfPageInt);
        $dayZone2ItemMul = $result['dayZone2ItemMul'];
        $monthNowDayZone2TimestampArr = $result['dayZone2TimestampArr'];
        $monthNow = [
            'year' => date('Y', $dayFirstOfMonthInt),
            'month' => date('m', $dayFirstOfMonthInt),
            'monthEnglish' => date('F', $dayFirstOfMonthInt),
            'titleOfWeek' => self::TITLE_OF_WEEK,
            'listDay' => $dayZone2ItemMul,];

        //下一月
        $time = strtotime(date('Y-m-t', time())) + 86400; //下一月第一天
        $dayFirstOfMonth = date('Y-m-01', $time);           //指定日期月第一天
        $dayFirstOfMonthInt = strtotime($dayFirstOfMonth);
        $dayFirstOfPageInt = ($dayFirstOfMonthInt - date('w', $dayFirstOfMonthInt) * 86400);//指定日期日历页第一天
        $dayLastOfPageInt = $dayFirstOfPageInt + 86400 * 41;//指定日期日历页最后一天
        $result = $this->getListDay($fromZone, $toZone, $dayFirstOfPageInt, $dayLastOfPageInt, $timestamp, $dayFirstOfMonthInt);
        $dayZone2ItemMul = $result['dayZone2ItemMul'];
        $monthNextDayZone2TimestampArr = $result['dayZone2TimestampArr'];
        $monthNext = [
            'year' => date('Y', $dayFirstOfMonthInt),
            'month' => date('m', $dayFirstOfMonthInt),
            'monthEnglish' => date('F', $dayFirstOfMonthInt),
            'titleOfWeek' => self::TITLE_OF_WEEK,
            'listDay' => $dayZone2ItemMul,];

        date_default_timezone_set($fromZone);

        //待处理事件
        if ($checkEvent) {
            $dayZone2TimestampArr = $weekDayZone2TimestampArr;
            $listDayZone2Event = $this->getEvent($fromZone, $toZone, $dayZone2TimestampArr, $timestamp);
            foreach ($listDayZone2Event as $dayZone => $listEvent) {
                if (isset($objCollectionWeek['listDay'][$dayZone])) {
                    $objCollectionWeek['listDay'][$dayZone]['listEvent'] = $listDayZone2Event[$dayZone];
                }
                if (isset($monthNow['listDay'][$dayZone])) {
                    $monthNow['listDay'][$dayZone]['listEvent'] = $listDayZone2Event[$dayZone];
                }
                if (isset($monthNext['listDay'][$dayZone])) {
                    $monthNext['listDay'][$dayZone]['listEvent'] = $listDayZone2Event[$dayZone];
                }
            }
        }

        $listCollectionMonth = [$monthNow, $monthNext,];

        $result = [
            'objCollectionWeek' => $objCollectionWeek,
            'listCollectionMonth' => $listCollectionMonth,
        ];

        return $result;
    }

    /**
     * @param string $dateNow Y-m-d H:i:s 同一个时区的时间字符串
     * @param string $dateTarget Y-m-d H:i:s 同一个时区的时间字符串
     * @param string $mark
     * @return string
     */
    protected function getLeftTime($dateNow, $dateTarget, $mark = '')
    {
        $timeNow = strtotime($dateNow);
        $timeTarget = strtotime($dateTarget);
        if ($timeNow > $timeTarget) {
            return '';
        }

        switch ($mark) {
            case 'Rebates':
                $zNow = date('z', $timeNow);//年份中的第几天
                $zTarget = date('z', $timeTarget);
                if ($zTarget - $zNow < 2) {
                    $timeDiff = ($timeTarget - $timeNow);
                    $leftHour = str_pad(intval($timeDiff / 3600), 2, 0, STR_PAD_LEFT);
                    $leftMinute = str_pad(intval($timeDiff % 3600 / 60), 2, 0, STR_PAD_LEFT);
                    return $leftHour . 'h ' . $leftMinute . 'm left';
                } else {
                    return 'Expire at ' . mb_substr($dateTarget, 11, 5);
                }
                break;
            default:
                $timeDiff = ($timeTarget - $timeNow);
                $leftHour = str_pad(intval($timeDiff / 3600), 2, 0, STR_PAD_LEFT);
                $leftMinute = str_pad(intval($timeDiff % 3600 / 60), 2, 0, STR_PAD_LEFT);
                return $leftHour . 'h ' . $leftMinute . 'm left';
                break;
        }
    }
}
