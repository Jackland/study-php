<?php

namespace App\Services\FeeOrder;

use App\Components\BatchInsert;
use App\Enums\FeeOrder\StorageFeeStatus;
use App\Enums\Product\ProductTransactionType;
use App\Helper\CountryHelper;
use App\Logging\Logger;
use App\Models\Margin\MarginAgreement;
use App\Models\Order\Order;
use App\Models\StorageFee\StorageFee;
use App\Models\StorageFee\StorageFeeDetail;
use App\Repositories\FeeOrder\StorageFeeModeRepository;
use Carbon\Carbon;
use Framework\App;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use kriss\bcmath\BCS;

/**
 * 仓租费用计算
 */
class StorageFeeCalculateService
{
    private $feeModeRepo;

    public function __construct(StorageFeeModeRepository $feeModeRepo)
    {
        $this->feeModeRepo = $feeModeRepo;
    }

    /**
     * 根据国家计算一天仓租
     * 此方法不检验仓租是否已经计算过，会删除原来的，重新计算新的
     * 如果需要校验，请主动调用 StorageFeeRepository::checkHasCalculatedByCountry()
     * @param int $countryId 指定国家
     * @param string $date 指定计算日期，格式Y-m-d，确保已经处理过时区
     */
    public function calculateByCountryOneDay($countryId, $date)
    {
        dbTransaction(function () use ($countryId, $date) {
            $this->updateStorageFeeOneDate($countryId, $date);
        }, 3);
    }

    /**
     * 根据采购单计算该采购单的所有仓租
     * 此方法不检验仓租是否已经计算过，会删除原来的，重新计算新的
     * 如果需要校验，请主动调用 StorageFeeRepository::checkHasCalculatedByOrder()
     * @param int $orderId
     * @param string $untilDate 指定截至日期，格式Y-m-d
     */
    public function calculateByOrder($orderId, $untilDate)
    {
        dbTransaction(function () use ($orderId, $untilDate) {
            $this->updateStorageFeeByOrder($orderId, $untilDate);
        }, 3);
    }

    /**
     * 根据采购单计算该采购单的所有仓租
     * 此方法不检验仓租是否已经计算过，会删除原来的，重新计算新的
     * 如果需要校验，请主动调用 StorageFeeRepository::checkHasCalculatedByAgreement()
     * @param int $type 交易类型
     * @param int $agreementId 协议id
     * @param string $untilDate 指定截至日期，格式Y-m-d
     */
    public function calculateByAgreement($type, $agreementId, $untilDate)
    {
        dbTransaction(function () use ($type, $agreementId, $untilDate) {
            $this->updateStorageFeeByAgreement($type, $agreementId, $untilDate);
        }, 3);
    }

    /**
     * 更新某一日的所有仓租
     * @param int $countryId 国家
     * @param string $date 日期 Y-m-d，确保已经处理过时区
     */
    protected function updateStorageFeeOneDate($countryId, $date)
    {
        $timezone = CountryHelper::getTimezone($countryId);
        if (!$timezone) {
            Logger::storageFee([__FUNCTION__, '国家未定义时区', $countryId, $timezone], 'warning');
            return;
        }
        // 删除已有的 detail 记录
        StorageFeeDetail::query()->alias('a')
            ->leftJoinRelations(['storageFee as b'])
            ->where('a.fee_date', $date)
            ->where('b.country_id', $countryId)
            ->whereIn('b.status', StorageFeeStatus::needCalculateStatus())
            ->delete();
        // 获取所有需要计算仓租的
        $storageFees = StorageFee::query()
            ->whereIn('status', StorageFeeStatus::needCalculateStatus())
            ->where('country_id', $countryId)
            ->where('created_at', '<', $date)
            ->cursor(); // 分批查询，否则容易造成内存超出
        $batchInsert = new BatchInsert(); // 分批插入，否则会造成插入数据量过大
        $batchInsert->begin(StorageFeeDetail::class, 500);
        $ids = [];
        $currentDatetime = date('Y-m-d H:i:s');
        foreach ($storageFees as $storageFee) {
            /** @var StorageFee $storageFee */
            // 记录需要更新的主表
            $ids[] = $storageFee->id;
            // 子表记录
            $diffDay = $storageFee->created_at->setTimezone($timezone)->setTime(0, 0, 0)
                ->diffInDays(Carbon::parse($date), false); // 使用对应国别的时区计算当前第几天
            list($feeDay, $feeModeId, $feeModeVersion) = $this->calculateStorageFeeOneDay($storageFee->country_id, $diffDay, $storageFee->volume_m);
            $isInsert = $batchInsert->addRow([
                'storage_fee_id' => $storageFee->id,
                'fee_mode_id' => $feeModeId,
                'fee_mode_version' => $feeModeVersion,
                'fee_date' => $date,
                'day' => $diffDay,
                'fee_today' => $feeDay,
                'created_at' => $currentDatetime,
            ]);
            if ($isInsert) {
                // 分批次更新主表信息
                $this->updateStorageFeeInfo($ids);
                $ids = [];
            }
        }
        $isInsert = $batchInsert->end();
        if ($isInsert) {
            // 分批次更新主表信息
            $this->updateStorageFeeInfo($ids);
        }
    }

    /**
     * 更新某个采购单从入仓到指定日期的所有仓租
     * @param int $orderId
     * @param string $untilDate Y-m-d，确保已经处理过时区
     * @throws \Exception
     */
    protected function updateStorageFeeByOrder($orderId, $untilDate)
    {
        $order = Order::find($orderId);
        if (!$order) {
            Logger::storageFee([__FUNCTION__, 'orderId 不存在', $orderId], 'warning');
            return;
        }
        $storageFees = StorageFee::query()
            ->where('order_id', $orderId)
            ->whereIn('status', StorageFeeStatus::needCalculateStatus())
            ->get();
        if ($storageFees->isEmpty()) {
            Logger::storageFee([__FUNCTION__, '无仓租', $orderId], 'warning');
            return;
        }
        $this->updateStorageFee($storageFees, $untilDate);
    }

    /**
     * 更新某个采购单从入仓到指定日期的所有仓租
     * @param int $type
     * @param int $agreementId
     * @param string $untilDate Y-m-d，确保已经处理过时区
     * @throws \Exception
     */
    protected function updateStorageFeeByAgreement($type, $agreementId, $untilDate)
    {
        if($type != ProductTransactionType::MARGIN){
            Logger::storageFee([__FUNCTION__, '协议类型不支持', $type], 'warning');
            return;
        }
        $agreement = MarginAgreement::find($agreementId);
        if (!$agreement) {
            Logger::storageFee([__FUNCTION__, '协议不存在', $agreementId], 'warning');
            return;
        }
        $storageFees = StorageFee::query()
            ->where('transaction_type_id', $type)
            ->where('agreement_id', $agreementId)
            ->whereIn('status', StorageFeeStatus::needCalculateStatus())
            ->get();
        if ($storageFees->isEmpty()) {
            Logger::storageFee([__FUNCTION__, '无仓租', $agreementId], 'warning');
            return;
        }
        $this->updateStorageFee($storageFees, $untilDate);
    }

    /**
     * 更新仓租
     *
     * @param StorageFee[]|Collection $storageFees
     * @param string $untilDate Y-m-d，确保已经处理过时区
     * @throws \Exception
     */
    private function updateStorageFee($storageFees, string $untilDate)
    {
        // 删除已有的 detail 记录
        StorageFeeDetail::query()
            ->whereIn('storage_fee_id', $storageFees->pluck('id')->toArray())
            ->delete();
        $batchInsert = new BatchInsert(); // 分批插入，否则会造成插入数据量过大
        $batchInsert->begin(StorageFeeDetail::class, 500);
        $ids = [];
        $currentDatetime = date('Y-m-d H:i:s');
        foreach ($storageFees as $storageFee) {
            $timezone = CountryHelper::getTimezone($storageFee->country_id);
            if (!$timezone) {
                Logger::storageFee([__FUNCTION__, '国家未定义时区', $storageFee->id, $storageFee->country_id, $timezone], 'warning');
                return;
            }
            $startDate = $storageFee->created_at->setTimezone($timezone)->setTime(0, 0, 0); // 使用对应国别的时区计算天数
            $nextDate = $storageFee->created_at->setTimezone($timezone)->setTime(0, 0, 0);
            while (true) {
                if (!in_array($storageFee->id, $ids)) {
                    // 记录需要更新的主表
                    $ids[] = $storageFee->id;
                }
                $nextDate = $nextDate->addDay(1);
                if ($nextDate->isAfter(Carbon::parse($untilDate))) {
                    break;
                }
                $diffDay = $startDate->diffInDays($nextDate, false); // 当前第几天
                list($feeDay, $feeModeId, $feeModeVersion) = $this->calculateStorageFeeOneDay($storageFee->country_id, $diffDay, $storageFee->volume_m);
                $isInsert = $batchInsert->addRow([
                    'storage_fee_id' => $storageFee->id,
                    'fee_mode_id' => $feeModeId,
                    'fee_mode_version' => $feeModeVersion,
                    'fee_date' => $nextDate->format('Y-m-d'), // 使用系统时区保存仓租日期
                    'day' => $diffDay,
                    'fee_today' => $feeDay,
                    'created_at' => $currentDatetime,
                ]);
                if ($isInsert) {
                    // 分批次更新主表信息
                    $this->updateStorageFeeInfo($ids);
                    $ids = [];
                }
            }
        }
        $isInsert = $batchInsert->end();
        if ($isInsert) {
            // 分批次更新主表信息
            $this->updateStorageFeeInfo($ids);
        }
    }

    /**
     * 更新仓租主表信息
     * @param array $ids
     */
    protected function updateStorageFeeInfo(array $ids)
    {
        $storageFeeTable = (new StorageFee())->getTable();
        $storageFeeDetailTable = (new StorageFeeDetail())->getTable();
        $orm = App::orm();
        // 更新 总仓租 = detail 表下 fee_today 的 sum
        $subQuery = $orm->table($storageFeeDetailTable)
            ->selectRaw('sum(fee_today)')
            ->whereRaw('storage_fee_id = ' . $storageFeeTable . '.id')
            ->toSql();
        $orm->table($storageFeeTable)
            ->whereIn('id', $ids)
            ->update(['fee_total' => new Expression("({$subQuery})")]);
        // 更新 待支付 = 总支付 - 已支付
        $orm->table($storageFeeTable)
            ->whereIn('id', $ids)
            ->update(['fee_unpaid' => new Expression('fee_total-fee_paid')]);
        // 更新 天数 = detail 表下 day 的 max
        $subQuery = $orm->table($storageFeeDetailTable)
            ->selectRaw('max(day)')
            ->whereRaw('storage_fee_id = ' . $storageFeeTable . '.id')
            ->toSql();
        $orm->table($storageFeeTable)
            ->whereIn('id', $ids)
            ->update(['days' => new Expression("({$subQuery})")]);
    }

    /**
     * 获取某个体积单天的仓储费
     * @param int $countryId 国家
     * @param int $day 天数
     * @param float $volume 体积
     * @return array [$feeDay, $feeModeId, $feeModeVersion]
     */
    public function calculateStorageFeeOneDay($countryId, $day, $volume)
    {
        $feeMode = $this->feeModeRepo->getFeeModeByCountryAndDay($countryId, $day);
        if (!$feeMode) {
            return [0, 0, 0];
        }
        // 单位体积仓租费率
        $feeDay = $this->feeModeRepo->getFeeByMode($feeMode);
        if ($feeDay > 0) {
            // 费率小于等于 0 时日仓租费为 0
            // 计算体积仓租费
            $map = [
                // 国别 => 保留小数位数
                '107' => 0,
            ];
            $config = [
                'scale' => 2,
                'round' => true,
            ];
            if (isset($map[$countryId])) {
                $config['scale'] = $map[$countryId];
            }
            $feeDay = BCS::create($feeDay, $config)->mul($volume);
            if ($feeDay->isLargerThan(0)) {
                $feeDay = $feeDay->getResult();
            } else {
                // 有体积时取最小值 0.01 或 1
                $feeDay = 1 / pow(10, $config['scale']);
            }
        }

        return [$feeDay, $feeMode->id, $feeMode->mode_version];
    }
}
