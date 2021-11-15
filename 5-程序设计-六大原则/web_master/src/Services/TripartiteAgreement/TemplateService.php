<?php

namespace App\Services\TripartiteAgreement;

use App\Components\Storage\StorageCloud;
use App\Components\Storage\StorageLocal;
use App\Models\Tripartite\TripartiteAgreement;
use App\Models\Tripartite\TripartiteAgreementTemplate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use TCPDF;

/**
 * 协议模板
 * Class TemplateService
 * @package App\Services\TripartiteAgreement
 */
class TemplateService
{
    const KEYWORD_AGREEMENT_VALIDITY01 = 'AgreementValidity01';
    const KEYWORD_AGREEMENT_VALIDITY02 = 'AgreementValidity02';
    const KEYWORD_BUYER_COMPANY = 'BuyerCompany';
    const KEYWORD_BUYER_ACCOUNT_NAME = 'BuyerAccountName';
    const KEYWORD_BUYER_ADDRESS = 'BuyerAddress';
    const KEYWORD_BUYER_NAME = 'BuyerName';
    const KEYWORD_BUYER_TELEPHONE = 'BuyerTelephone';
    const KEYWORD_SELLER_COMPANY = 'SellerCompany';
    const KEYWORD_SELLER_ACCOUNT_NAME = 'SellerAccountName';
    const KEYWORD_SELLER_ADDRESS = 'SellerAddress';
    const KEYWORD_SELLER_NAME = 'SellerName';
    const KEYWORD_SELLER_TELEPHONE = 'SellerTelephone';
    const KEYWORD_PARAMETER = 'parameter';

    private $keywordPrefix = '#';
    private $keywordPostfix = '#';

    private $readonlyKeywords = [
        self::KEYWORD_AGREEMENT_VALIDITY01,
        self::KEYWORD_AGREEMENT_VALIDITY02,
        self::KEYWORD_BUYER_COMPANY,
        self::KEYWORD_BUYER_ACCOUNT_NAME,
        self::KEYWORD_BUYER_ADDRESS,
        self::KEYWORD_BUYER_NAME,
        self::KEYWORD_BUYER_TELEPHONE,
        self::KEYWORD_SELLER_COMPANY,
        self::KEYWORD_SELLER_ACCOUNT_NAME,
        self::KEYWORD_SELLER_ADDRESS,
        self::KEYWORD_SELLER_NAME,
        self::KEYWORD_SELLER_TELEPHONE,
    ];

    /**
     * 可输入的关键字 例如 parameter01,parameter02 规定后面数字只能2位
     * @var string
     */
    private $writeKeyword = self::KEYWORD_PARAMETER;

    private $keywordHtml = '<span class="giga-template-value KEY">VAL</span>';

    /**
     * 生成替换值
     * @param array $keyValueMap ['AgreementValidity' => value, ]
     * @param array $replaces
     * @return array
     */
    public function generateReplaceValue(array $keyValueMap, array $replaces = []): array
    {
        foreach ($keyValueMap as $key => $value) {
            if (!$this->checkKeywordCorrect($key)) {
                continue;
            }

            $replaces[$this->spliceKeyword($key)] = $this->spliceValue($key, strval($value));
        }

        return $replaces;
    }

    /**
     * 查找所有可输入值的参数名
     * @param array $replaces
     * @return array
     */
    public function findParameters(array $replaces): array
    {
        return array_values(array_filter(array_map(function ($k) {
            if (Str::contains($k, $this->writeKeyword)) {
                return rtrim(ltrim($k, $this->keywordPrefix), $this->keywordPostfix);
            }
            return '';
        }, array_keys($replaces))));
    }

    /**
     * 检查公司信息
     * @param TripartiteAgreementTemplate $template
     * @param array $replaceMap ['BuyerCompany' => '', 'SellerAddress' => 'aa']
     * @return bool
     */
    public function checkCompanyInfo(TripartiteAgreementTemplate $template, array $replaceMap): bool
    {
        $templateReplaces = $template->template_replaces;
        foreach ($replaceMap as $key => $value) {
            if (!isset($templateReplaces[$this->spliceKeyword($key)])) {
                continue;
            }

            if (empty($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 替换模板内容
     * @param string $content
     * @param array $replaces
     * @return string
     */
    public function replaceTemplateContent(string $content, array $replaces): string
    {
        return str_replace(array_keys($replaces), array_values($replaces), $content);
    }

    /**
     * 生成pdf，通过path控制是否下载，为空时下载
     * @param TripartiteAgreement $agreement
     * @param string $path
     * @return string|\Symfony\Component\HttpFoundation\Response
     */
    public function generatePdf(TripartiteAgreement $agreement, string $path = '')
    {
        $html = app(TemplateService::class)->replaceTemplateContent($agreement->template->content, array_merge($agreement->template->template_replaces, $agreement->template_replaces));
        // 存储本地pdf
        $localDir = 'temp/tripartite_agreement/';
        if (!is_dir(DIR_STORAGE . $localDir)) {
            mkdir(iconv("UTF-8", "GBK", DIR_STORAGE . $localDir), 0777, true);
        }
        $filename = $agreement->agreement_no . '.pdf';
        $localPath = DIR_STORAGE . $localDir . $filename;
        if (file_exists($localPath)) {
            unlink($localPath);
        }
       app('mpdf')->config([
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 6,
            'margin_bottom' => 6,
        ])->loadHtml($html)->generate($localPath);

        // 保存到oss
        if ($path) {
            StorageCloud::storage()->writeFile(new UploadedFile($localPath, $filename), ltrim(trim($path, '/'), 'storage'), $filename);
            if (file_exists($localPath)) {
                unlink($localPath);
            }
            return rtrim($path, '/') . '/' . $filename;
        }

        return StorageLocal::storage()->browserDownload($localDir . $filename, "{$agreement->title}.pdf");
    }

    /**
     * @param string $key
     * @return string
     */
    private function spliceKeyword(string $key): string
    {
        return $this->keywordPrefix . $key . $this->keywordPostfix;
    }

    /**
     * @param string $key
     * @param string $value
     * @return string
     */
    private function spliceValue(string $key, string $value): string
    {
        return str_replace(['KEY', 'VAL'], [$key, $value], $this->keywordHtml);
    }

    /**
     * @param string $key
     * @return bool
     */
    private function checkKeywordCorrect(string $key): bool
    {
        if (in_array($key, $this->readonlyKeywords)) {
            return true;
        }

        if (!Str::contains($key, $this->writeKeyword)) {
            return false;
        }

        $num = Str::after($key, $this->writeKeyword);
        if (strlen($num) == 2 && is_numeric($num)) {
            return true;
        }

        return false;
    }
}
