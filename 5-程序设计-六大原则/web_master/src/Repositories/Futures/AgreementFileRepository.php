<?php
namespace App\Repositories\Futures;
use App\Models\Futures\FuturesAgreementFile;

class AgreementFileRepository {
    /**
     * 返回附件列表
     * @param int $messageId
     * @return mixed
     */
    function getFilesByMessageId($messageId){
        return FuturesAgreementFile::query()
            ->where('message_id',$messageId)
            ->get();
    }
}
