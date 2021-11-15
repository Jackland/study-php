<?php

namespace App\Catalog\Forms\CWF;

use App\Components\Storage\StorageCloud;
use App\Models\CWF\CloudWholesaleFulfillmentFileExplain;
use App\Models\CWF\CloudWholesaleFulfillmentFileUpload;
use App\Models\File\FileUpload;
use App\Repositories\CWF\CloudWholesaleFulfillmentRepository;
use Framework\Model\RequestForm\RequestForm;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadForm extends RequestForm
{

    /** @var UploadedFile $file */
    protected $file;

    /**
     * @inheritDoc
     */
    protected function getRules(): array
    {
        return [
            'file' => [
                'required', 'file', 'max:51200',// 最大50M
                function ($attribute, $value, $fail) {
                    $type = strtolower($value->getClientMimeType());
                    $extension = strtolower($value->getClientExtension());
                    if (
                        !in_array($type,
                            [
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                            ]
                        )
                        || !in_array($extension, ['xls', 'xlsx'])
                    ) {
                        $fail('The order file should be .xls or.xlsx');
                    }
                },

            ]
        ];
    }

    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \Exception
     */
    public function save(): array
    {
        $data = $this->validateExcelData();
        $file = $this->file;
        // 上传文件到oss
        $filename = date('Ymd') . '_'
            . md5((html_entity_decode($file->getClientOriginalName(), ENT_QUOTES, 'UTF-8') . micro_time()))
            . '.' . $file->getClientOriginalExtension();
        $filePath = 'cwf' . DIRECTORY_SEPARATOR
            . (int)customer()->getId();
        StorageCloud::storage()->writeFile($this->file, $filePath, $filename);
        // oc_file_upload存储
        $fileUpload = new FileUpload();
        $fileUpload->path = 'storage' . DIRECTORY_SEPARATOR . $filePath . DIRECTORY_SEPARATOR . $filename;
        $fileUpload->name = $filename;
        $fileUpload->suffix = $file->getClientOriginalExtension();
        $fileUpload->size = $file->getSize();
        $fileUpload->mime_type = $file->getClientMimeType();
        $fileUpload->orig_name = $file->getClientOriginalName();
        $fileUpload->date_added = Carbon::now();
        $fileUpload->date_modified = Carbon::now();
        $fileUpload->add_operator = (int)customer()->getId();
        $fileUpload->save();
        // cwf 表存储上传信息
        $cwfFile = new CloudWholesaleFulfillmentFileUpload();
        $cwfFile->create_id = (int)customer()->getId();
        $cwfFile->file_upload_id = $fileUpload->file_upload_id;
        $cwfFile->is_validate_success = 1;
        // 记录上传日志
        if (isset($data['error'])) {
            $cwfFile->is_validate_success = 0;
            $cwfFile->error_info = join(';', $data['error']);
            $cwfFile->save();
            $data['id'] = 0;
            return $data;
        }
        $cwfFile->save();
        // cwf explain 表存储信息
        $resolveHeader = [
            'ship_to_name', 'ship_to_email', 'ship_to_phone',
            'ship_to_postal_code', 'ship_to_address_detail', 'ship_to_city',
            'ship_to_state', 'ship_to_country',
        ];
        $resolveTemp = [];
        foreach ($data as $rowIndex => $value) {
            $key = array_reduce($resolveHeader, function ($carry, $item) use ($value) {
                return $carry . ($value[$item] ?? '');
            }, '');
            if (!in_array($key, $resolveTemp)) {
                $resolveTemp[] = $key;
            }
            $data[$rowIndex]['flag_id'] = array_search($key, $resolveTemp);
            $data[$rowIndex]['cwf_file_upload_id'] = $cwfFile->id;
            $data[$rowIndex]['loading_dock_provided'] = $value['loading_dock_provided'] == 'YES' ? 1 : 0;
        }
        CloudWholesaleFulfillmentFileExplain::query()->insert($data);
        // 对接邰兴方法
        $cwfRepo = app(CloudWholesaleFulfillmentRepository::class);
        ['success' => $success, 'msg' => $error] = $cwfRepo->checkCWFUploadInfo($cwfFile->id);
        if ($success == false) {
            $cwfFile->is_validate_success = 0;
            $cwfFile->error_info = $error;
            $cwfFile->save();
            return ['error' => (array)$error, 'id' => 0,];
        }
        return ['error' => null, 'id' => $cwfFile->id];
    }


    protected function getAutoLoadRequestData(): array
    {
        return ['file' => $this->request->filesBag->get('file')];
    }

    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function validateExcelData(): array
    {
        ['mainHeader' => $header, 'data' => $data] = $this->getData();
        // 校验数据不为空
        if (empty($data)) {
            return ['error' => ['No data was found in the file.']];
        }
        // 先校验文件的第一行是否满足要求 防止用户上传错误的文件
        $resolveMainHeader = [
            'Sales Platform', 'OrderDate', 'B2BItemCode', 'ShipToQty', 'ShipToName',
            'ShipToEmail', 'ShipToPhone', 'ShipToPostalCode', 'ShipToAddressDetail', 'ShipToCity', 'ShipToState',
            'ShipToCountry', 'LoadingDockProvided', 'OrderComments', 'ShipToAttachmentUrl',
        ];
        if ($header != $resolveMainHeader) {
            return ['error' => ['The columns of the uploaded file are inconsistent with the template,please check and re-upload.']];
        }
        $fileDataValidate = new FileDataValidate();
        $fileDataValidate->loadAttributes(compact('data'));
        $validator = $fileDataValidate->validateAttributes();
        $errors = $validator->errors()->messages();
        $error = [];
        foreach ($errors as $key => $values) {
            [, $rowIndex,] = explode('.', $key);
            foreach ($values as $value) {
                $error[$value] = array_merge($error[$value] ?? [], [$rowIndex]);
            }
        }
        if (!empty($error)) {
            $retError = [];
            foreach ($error as $key => $value) {
                $rowList = join(',', $value);
                $retError[] = "Line:$rowList $key";
            }
            return ['error' => $retError];
        }
        // 二次校验
        // 合并地址匹配库存
        // 合并标准: ShipToName + ShipToEmail + ShipToPhone + ShipToPostalCode+ShipToAddressDetail+ShipToCity
        //          + ShipToState + ShipToCountry 完全一致 表示地址是一样的
        $resolveData = [];
        $resolveHeader = [
            'b2b_item_code', 'ship_to_name', 'ship_to_email', 'ship_to_phone',
            'ship_to_postal_code', 'ship_to_address_detail', 'ship_to_city',
            'ship_to_state', 'ship_to_country',
        ];
        foreach ($data as $rowIndex => $value) {
            $key = array_reduce($resolveHeader, function ($carry, $item) use ($value) {
                return $carry . ($value[$item] ?? '');
            }, '');
            $resolveData[$key] = array_merge($resolveData[$key] ?? [], [$rowIndex]);
        }
        $retError = [];
        foreach ($resolveData as $value) {
            if (count($value) >= 2) {
                $retError[] = 'Line:' . join(',', $value) . ' The row with the same address and the same Item Code cannot be more than one.';
            }
        }
        if (!empty($retError)) {
            return ['error' => $retError];
        }
        return $data;
    }

    /**
     * @return array
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function getData(): array
    {
        $file = $this->file;
        $reader = $this->getReader();
        $excel = $reader->load($file->getRealPath());
        $excel->setActiveSheetIndex(0); // 获取 第一个sheet的数据
        $worksheet = $excel->getActiveSheet();
        $data = [];
        $header = [
            'row_index',
            'sales_platform', 'order_date', 'b2b_item_code', 'ship_to_qty', 'ship_to_name',
            'ship_to_email', 'ship_to_phone', 'ship_to_postal_code', 'ship_to_address_detail', 'ship_to_city',
            'ship_to_state', 'ship_to_country', 'loading_dock_provided', 'order_comments', 'ship_to_attachment_url',
        ];
        // 获取第一行header数据
        $mainHeader = [];
        $highestRow = $worksheet->getHighestRow();
        if ($highestRow < 3) {
            return compact('mainHeader', 'data');
        }
        foreach ($worksheet->getRowIterator(1, 1) as $row) {
            $cellIterator = $row->getCellIterator('A', 'O'); // 获取范围A-O
            $cellIterator->setIterateOnlyExistingCells(false);
            foreach ($cellIterator as $cell) {
                $mainHeader[] = str_replace('*', '', trim((string)$cell->getValue()));
            }
        }
        foreach ($worksheet->getRowIterator(3, $highestRow) as $row) { // 从第三行开始读取excel数据
            $cellIterator = $row->getCellIterator('A', 'O'); // 获取范围A-O
            $cellIterator->setIterateOnlyExistingCells(false);
            $rowData = [$row->getRowIndex()];
            foreach ($cellIterator as $cell) {
                $value = trim((string)$cell->getValue());
                $value = str_replace([chr(194), chr(160)], '', $value);
                if (in_array($cell->getColumn(), ['C', 'L', 'M',])) { // C L M的excel数据需要大写
                    $value = strtoupper($value);
                }
                $rowData[] = $value;
            }
            // 去除空行 空行直接跳过 不予处理
            $tempData = array_filter($rowData);
            if (count($tempData) == 1) {
                continue;
            }
            $data[$row->getRowIndex()] = array_combine($header, $rowData);
        }
        return compact('mainHeader', 'data');
    }

    private function getReader()
    {
        $ext = strtolower($this->file->getClientOriginalExtension());
        if ($ext === 'xls') {
            return new Xls();
        } else {
            return new Xlsx();
        }
    }

}
