<?php

namespace App\Components\PageViewSafe\CaptchaTransfer;

use App\Components\AES;

class TransferEncryption
{
    private $aes;

    public function __construct($aesKey, $aesIv)
    {
        $this->aes = new AES($aesKey, $aesIv);
    }

    public function encrypt(TransferInterface $transfer): string
    {
        $data = json_encode($transfer->getData(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $this->aes->encrypt($data);
    }

    public function decrypt(string $data, $transferClass): ?TransferInterface
    {
        $data = $this->aes->decrypt($data);
        if (!$data) {
            return null;
        }
        $data = json_decode($data, true);
        if (!$data) {
            return null;
        }
        /** @var TransferInterface $transferClass */
        return $transferClass::loadFromData($data);
    }
}
