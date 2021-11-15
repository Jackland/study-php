<?php

namespace App\Repositories\Product;

use App\Helper\CountryHelper;
use App\Models\Product\ProductImportBatch;
use App\Models\Product\ProductImportBatchErrorReport;
use Carbon\Carbon;

class ProductImportRepository
{
    /**
     * 获取某个用户最近几次批量上传的记录
     * @param int $customerId
     * @param int $limit
     * @param array|string[] $columns
     * @return ProductImportBatch[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getProductImportsLimitByCustomerId(int $customerId, int $countryId, int $limit = 5, array $columns = ['*'])
    {
        $timeZone = CountryHelper::getTimezone($countryId);
        $result = ProductImportBatch::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get($columns);

        foreach ($result as &$item) {
            $item->create_time = Carbon::parse($item->create_time)->timezone($timeZone)->toDateTimeString();
        }
        return $result ? $result->toArray() : [];
    }

    /**
     * 获取某个用户批量上传的历史记录可根据时间筛选
     * @param int $customerId
     * @param string $rangeBeginDate
     * @param string $rangeEndDate
     * @param int $perPage
     * @param int $page
     * @param array|string[] $columns
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getProductImportsPaginateByCustomerIdAndRangeTime(int $customerId, string $rangeBeginDate = '', string $rangeEndDate = '', int $perPage = 1, int $page = 1, array $columns = ['*'])
    {
        $rangeBeginDate = $rangeEndDate ? changeInputByZone($rangeBeginDate . ' 00:00:00', session()->get('country')) : '';
        $rangeEndDate = $rangeEndDate ? changeInputByZone($rangeEndDate . ' 23:59:59', session()->get('country')) : '';

        return ProductImportBatch::query()
            ->where('customer_id', $customerId)
            ->when(!empty($rangeBeginDate), function ($query) use ($rangeBeginDate) {
                $query->where('create_time', '>=', $rangeBeginDate);
            })
            ->when(!empty($rangeEndDate), function ($query) use ($rangeEndDate) {
                $query->where('create_time', '<=', $rangeEndDate);
            })
            ->orderByDesc('id')
            ->paginate($perPage, $columns, 'page', $page);
    }

    /**
     * 获取某次导入的错误报告
     * @param int $batchId
     * @param int $customerId
     * @return ProductImportBatchErrorReport[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getProductImportErrorReportsByBatchId(int $batchId, int $customerId)
    {
        return ProductImportBatchErrorReport::query()->where('batch_id', $batchId)->where('customer_id', $customerId)->get();
    }
}
