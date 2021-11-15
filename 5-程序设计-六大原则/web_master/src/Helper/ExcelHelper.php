<?php

namespace App\Helper;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class ExcelHelper
{
    /**
     * 导出excel，以固定格式传入，主要想可控制每列宽度
     * @param $expTitle
     * @param $expCellName
     * @param $expTableData
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public static function exportExcel($expTitle, $expCellName, $expData)
    {
        $cellNum = count($expCellName);
        $dataNum = count($expData);
        $lastHead = end($expCellName);
        $allColumn = 'A1:' . $lastHead[0] . ($dataNum + 1);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getStyle($allColumn)->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setWrapText(true);

        foreach ($expCellName as $item) {
            $sheet->setCellValue($item[0] . '1', $item[2]);
            $sheet->getColumnDimension($item[0]);
            $sheet->getColumnDimension($item[0])->setWidth($item[3]);
        }

        $sheet->getStyle('A1:' . $lastHead[0] . '1')->getFont()->setBold(true);
        for ($i = 0; $i < $dataNum; $i++) {
            for ($j = 0; $j < $cellNum; $j++) {
                //可处理数字科学记数法问题
                $sheet->setCellValueExplicit($expCellName[$j][0] . ($i + 2), $expData[$i][$expCellName[$j][1]], DataType::TYPE_STRING);
            }
        }

        @ob_end_clean();//清除缓冲区,避免乱码
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $expTitle . '"');
        header('Cache-Control: max-age=0');
        $writer = IOFactory::createWriter($spreadsheet, 'Xls');
        $writer->save('php://output');

    }
}
