<?php
namespace App\Repositories\SpecialFee;
use App\Models\SpecialFee\SpecialServiceFee;
use App\Models\SpecialFee\SpecialServiceFeeFile;

class SpecialFeeRepository
{
    /**
     * 获取服务费用ID
     * @param int $receiveOrderId
     * @return mixed|null
     */
    public function getSpecFeeByReceiveOrderId(int $receiveOrderId)
    {
        return SpecialServiceFee::query()
            ->where('receive_order_id', $receiveOrderId)
            ->value('id');
    }

    /**
     * 获取特殊费用附件
     * @param $specId
     * @return mixed
     */
    public function getSpecFeeFiles($specId)
    {
        return SpecialServiceFeeFile::query()
            ->select(['id', 'file_name', 'file_path'])
            ->where(['header_id' => $specId, 'delete_flag' => 0])
            ->get();
    }

    /**
     * 获取入库单的特殊费用附件
     * @param $receiveOrderId
     * @return mixed
     */
    public function getSpecFeeFilesByReceiveOrderId($receiveOrderId)
    {
        $specId = $this->getSpecFeeByReceiveOrderId($receiveOrderId);
        if ($specId) {
            return $this->getSpecFeeFiles($specId);
        }
        return [];
    }

}
